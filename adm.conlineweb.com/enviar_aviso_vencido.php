<?php
/**
 * Envía aviso de servicio vencido (hosting o dominio) al cliente.
 * Hosting: eliminación en 5 días + respaldo. Dominio: riesgo de pérdida.
 */
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

if (ob_get_level()) {
    ob_clean();
}
ob_start();

function aviso_vencido_json(array $data): void
{
    while (ob_get_level()) {
        ob_end_clean();
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if (!defined('CW_BRAIN_WIDGET_DISABLE')) {
        define('CW_BRAIN_WIDGET_DISABLE', true);
    }
    require_once __DIR__ . '/auth_middleware.php';
    require_once __DIR__ . '/conn.php';
    require_once __DIR__ . '/conn_hostingpro.php';
    require_once __DIR__ . '/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';
    require_once __DIR__ . '/smtp_config_helper.php';
    require_once __DIR__ . '/includes/pago_correo_build.php';

    $sistema = (isset($_POST['sistema']) && $_POST['sistema'] === 'hostingpro') ? 'hostingpro' : 'conlineweb';
    if (isset($_POST['sistema']) && $_POST['sistema'] === 'planpro') {
        $sistema = 'planpro';
    }
    $db = ($sistema === 'hostingpro' || $sistema === 'planpro') ? $conn_hp : $conn;

    if (!isset($db) || $db->connect_error) {
        throw new Exception('Error de conexión a base de datos');
    }

    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if ($id <= 0) {
        throw new Exception('ID de pago no válido');
    }
    $modosOk = ['vencido', 'vencimiento', 'plazo', 'eliminacion'];
    $modo = isset($_POST['modo']) ? (string) $_POST['modo'] : 'eliminacion';
    if (!in_array($modo, $modosOk, true)) {
        $modo = 'eliminacion';
    }

    $loaded = pago_correo_cargar_fila($db, $id);
    if (empty($loaded['ok'])) {
        throw new Exception($loaded['error'] ?? 'Pago no encontrado');
    }
    $row = $loaded['row'];
    $correo = trim((string) ($row['correo'] ?? ''));
    $nombre = trim((string) ($row['nombre_contacto'] ?? 'Cliente'));
    [$monto, $moneda] = pago_correo_monto_moneda($row);

    $conceptoPago = trim((string) ($row['concepto'] ?? ''));
    if ($conceptoPago === '') {
        $tipoTmp = (int) $row['tipo_servicio'];
        if ($tipoTmp === 1) {
            $conceptoPago = 'Renovación Hosting ' . ($row['nom_host'] ?: $row['hosting_dominio'] ?: '');
        } else {
            $conceptoPago = 'Renovación dominio ' . ($row['url_dominio'] ?? '');
        }
    }

    // Mismo flujo que el recordatorio: crear checkout Stripe obligatorio (botón + enlace reales)
    $paymentData = [
        'id' => $id,
        'nombre' => $nombre,
        'costo' => $monto,
        'moneda' => $moneda,
        'concepto' => $conceptoPago,
        'correo' => $correo,
        'sistema' => $sistema === 'hostingpro' ? 'hostingpro' : 'conlineweb',
    ];

    $ch = curl_init('https://adm.conlineweb.com/procesar_pago.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($paymentData),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new Exception('Error al conectar con Stripe: ' . $curlErr);
    }
    if ($httpCode !== 200) {
        throw new Exception('Error HTTP al procesar pago: ' . $httpCode);
    }

    $paymentResult = json_decode($response, true);
    if (!is_array($paymentResult) || empty($paymentResult['success'])) {
        $err = is_array($paymentResult) ? ($paymentResult['error'] ?? 'Error desconocido') : 'Respuesta inválida';
        throw new Exception('Error en Stripe: ' . $err);
    }
    if (empty($paymentResult['session_url']) || empty($paymentResult['session_id'])) {
        throw new Exception('URL de pago no recibida de Stripe');
    }

    $sessionUrl = (string) $paymentResult['session_url'];
    $sid = (string) $paymentResult['session_id'];
    $up = $db->prepare('UPDATE pagos SET session_id = ? WHERE id = ?');
    if ($up) {
        $up->bind_param('si', $sid, $id);
        $up->execute();
        $up->close();
    }

    $built = pago_correo_build($db, $id, $modo, $sessionUrl);
    if (empty($built['ok'])) {
        throw new Exception($built['error'] ?? 'No se pudo generar el correo');
    }

    $mail = new PHPMailer(true);
    configure_phpmailer_by_system($mail, $sistema === 'hostingpro' ? 'hostingpro' : 'conlineweb');
    $mail->addAddress($built['correo'], $built['cliente']);
    $mail->Subject = $built['asunto'];
    $mail->isHTML(true);
    $mail->Body = $built['html'];
    $mail->CharSet = 'UTF-8';
    $mail->send();

    aviso_vencido_json([
        'success' => true,
        'message' => ($built['asunto'] ?? 'Alerta') . ' enviada a ' . $built['correo'],
        'tipo' => strtolower((string) $built['tipo']),
        'correo' => $built['correo'],
        'fecha_eliminacion' => $built['meta']['fecha_eliminacion'] ?? null,
        'payment_link' => $sessionUrl,
    ]);
} catch (PHPMailerException $e) {
    aviso_vencido_json(['success' => false, 'message' => 'Error al enviar correo: ' . $e->getMessage()]);
} catch (Throwable $e) {
    aviso_vencido_json(['success' => false, 'message' => $e->getMessage()]);
}
