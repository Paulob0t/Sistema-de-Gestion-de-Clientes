<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1. Conexiones a BD
include "conn.php";
include "conn_hostingpro.php";

// 2. PHPMailer (ANTES de los helpers)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require "PHPMailer/src/Exception.php";
require "PHPMailer/src/PHPMailer.php";
require "PHPMailer/src/SMTP.php";

// 3. Helpers (DESPUÉS de PHPMailer)
include "smtp_config_helper.php";
include "hostpro_email_helper.php";
require_once __DIR__ . "/includes/pago_email_template.php";

// 4. Variables del POST
$pago_id = $_POST['pago_id'];
$is_resend = isset($_POST['resend']) ? $_POST['resend'] : 0;
$sistema = (isset($_POST['sistema']) && $_POST['sistema'] === 'hostingpro') ? 'hostingpro' : 'conlineweb';
$conn = ($sistema === 'hostingpro') ? $conn_hp : $conn;

function enviarCorreoPago($correo_destino, $asunto, $datos_pago, $session_url, $sistema = 'conlineweb', $nombre_cliente = 'Cliente') {
    $monto_formateado = number_format($datos_pago['monto'], 2);
    $moneda = $datos_pago['currency'];
    $concepto = $datos_pago['concepto'];
    $fecha_limite_pago = $datos_pago['fecha_limite_pago'];
    if (!empty($fecha_limite_pago) && $fecha_limite_pago !== '0000-00-00' && strpos((string) $fecha_limite_pago, '/') === false) {
        $ts = strtotime((string) $fecha_limite_pago);
        if ($ts !== false) {
            $fecha_limite_pago = date('d/m/Y', $ts);
        }
    }
    $tipoServicio = (string) ($datos_pago['tipo_servicio'] ?? '');
    $servicioNombre = (string) ($datos_pago['servicio_nombre'] ?? $concepto);
    $dominioNombre = (string) ($datos_pago['dominio_nombre'] ?? '');
    
    // Si es HostPro, usar su template
    if ($sistema === 'hostingpro') {
        $fecha_vencimiento = isset($datos_pago['fecha_limite_pago']) && $datos_pago['fecha_limite_pago'] !== '0000-00-00' 
            ? date('d/m/Y', strtotime($datos_pago['fecha_limite_pago'])) 
            : date('d/m/Y', strtotime('+3 days'));
        
        $mensaje = hostpro_template_pago_pendiente(
            $nombre_cliente,
            $concepto,
            $monto_formateado,
            $moneda,
            $fecha_vencimiento,
            $session_url,
            'pago manual'
        );
        
        $mail = new PHPMailer(true);
        configure_phpmailer_by_system($mail, 'hostingpro');
        $mail->addAddress($correo_destino, $nombre_cliente);
        $mail->Subject = $asunto;
        $mail->isHTML(true);
        $mail->Body = $mensaje;
        
        try {
            $mail->send();
            return ['success' => true, 'message' => 'Correo enviado correctamente'];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al enviar: ' . $e->getMessage(),
                'debug' => $mail->ErrorInfo
            ];
        }
    }
    
    $mensaje = pago_email_pendiente(
        $concepto,
        $monto_formateado,
        $moneda,
        $fecha_limite_pago,
        $session_url,
        $tipoServicio,
        $servicioNombre,
        $dominioNombre
    );

    $correoRemitente = "servicios@conlineweb.com";
    $nombreRemitente = "ConlineWeb";

    $mail = new PHPMailer(true);

    try {
        configure_phpmailer_by_system($mail, 'conlineweb');
        $mail->addAddress($correo_destino);
        $mail->Subject = $asunto;
        $mail->isHTML(true);
        $mail->Body = $mensaje;
        $mail->CharSet = 'UTF-8';

        $mail->send();
        return ['success' => true, 'message' => 'Correo enviado correctamente'];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Error al enviar el correo: ' . $e->getMessage(),
            'debug' => $mail->ErrorInfo
        ];
    }
}

if ($is_resend) {
    $sql = "SELECT p.monto, p.currency, p.concepto, c.correo, c.nombre_contacto
            FROM pagos p
            JOIN clientes c ON p.id_clie = c.id
            WHERE p.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $pago_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $payment_data = $result->fetch_assoc();

    if (!$payment_data) {
        echo json_encode(['success' => false, 'error' => 'Pago no encontrado']);
        exit;
    }

    $data = [
        'id' => $pago_id,
        'nombre' => $payment_data['nombre_contacto'],
        'costo' => $payment_data['monto'],
        'moneda' => $payment_data['currency'],
        'concepto' => $payment_data['concepto'],
      'correo' => $payment_data['correo'],
      'sistema' => ((isset($_POST['sistema']) && $_POST['sistema'] === 'hostingpro') ? 'hostingpro' : 'conlineweb')
    ];

    $ch = curl_init('https://adm.conlineweb.com/procesar_pago.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    if ($response === false) {
        echo json_encode([
            'success' => false,
            'message' => 'Error en la conexión con el servidor de pagos',
            'curl_error' => curl_error($ch)
        ]);
        curl_close($ch);
        exit;
    }

    curl_close($ch);
    $payment_result = json_decode($response, true);
    $session_url = $payment_result['session_url'];
    $session_id = $payment_result['session_id'];

    $update_sql = "UPDATE pagos SET session_id = ?, id_pago = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ssi", $session_id, $session_id, $pago_id);
    $update_stmt->execute();
}

$sql_datos = "SELECT p.monto, p.currency, p.concepto, p.tipo_servicio, p.fecha_limite_pago,
                     c.correo, c.nombre_contacto
              FROM pagos p
              JOIN clientes c ON p.id_clie = c.id
              WHERE p.id = ?";
$stmt_datos = $conn->prepare($sql_datos);
$stmt_datos->bind_param("i", $pago_id);
$stmt_datos->execute();
$result = $stmt_datos->get_result();
$datos_pago = $result->fetch_assoc();

if (!$datos_pago) {
    echo json_encode(['success' => false, 'error' => 'Datos de pago no encontrados']);
    exit;
}

$tipoPago = (int) ($datos_pago['tipo_servicio'] ?? 0);
if ($tipoPago === 1) {
    $datos_pago['servicio_nombre'] = (string) $datos_pago['concepto'];
} elseif ($tipoPago === 2) {
    $datos_pago['dominio_nombre'] = (string) $datos_pago['concepto'];
}

$resultado_correo = enviarCorreoPago(
    $datos_pago['correo'],
    "Pago Pendiente - " . $datos_pago['concepto'],
    $datos_pago,
    $session_url,
    $sistema,
    $datos_pago['nombre_contacto'] ?? 'Cliente'
);

echo json_encode([
    'success' => true,
    'email_result' => $resultado_correo,
    'session_url' => $session_url,
    'session_id' => $session_id
]);
$conn->close();
