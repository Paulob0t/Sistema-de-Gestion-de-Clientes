<?php
/**
 * API códigos de verificación de cuenta (solo correo).
 * actions: get | send
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

define('AUTH_MIDDLEWARE_FUNCTIONS_ONLY', true);
require_once dirname(__DIR__) . '/auth_middleware.php';
require_once dirname(__DIR__) . '/includes/cw_client_verification_service.php';
require_once dirname(__DIR__) . '/includes/cw_email_brand.php';

adm_start_session();

if (!isAdminSessionValid() && !loadSessionFromCookies()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autorizado'], JSON_UNESCAPED_UNICODE);
    exit;
}

$tipo = (int) ($_SESSION['tipo'] ?? 0);
if ($tipo < 1 || $tipo > 5) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sin permiso'], JSON_UNESCAPED_UNICODE);
    exit;
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = (string) ($_GET['action'] ?? $_POST['action'] ?? 'get');

$input = $_POST;
$raw = file_get_contents('php://input');
if (is_string($raw) && $raw !== '' && strpos((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== false) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = array_merge($input, $decoded);
        if (isset($decoded['action'])) {
            $action = (string) $decoded['action'];
        }
    }
}

$clienteId = (int) ($input['cliente_id'] ?? $_GET['cliente_id'] ?? 0);
$sistema = trim((string) ($input['sistema'] ?? $_GET['sistema'] ?? 'conlineweb'));
if ($sistema === '') {
    $sistema = 'conlineweb';
}

try {
    if ($action === 'get') {
        if ($clienteId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Cliente inválido'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $data = cw_client_verification_get($clienteId, $sistema);
        echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'send') {
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($clienteId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Cliente inválido'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        require_once dirname(__DIR__) . '/conn.php';
        require_once dirname(__DIR__) . '/conn_hostingpro.php';
        $db = ($sistema === 'hostingpro' || $sistema === 'planpro')
            ? ($conn_hp ?? $conn)
            : $conn;

        if (!($db instanceof mysqli)) {
            echo json_encode(['ok' => false, 'error' => 'Sin conexión a base de datos'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $db->prepare('SELECT id, empresa, nombre_contacto, correo FROM clientes WHERE id = ? LIMIT 1');
        if (!$stmt) {
            echo json_encode(['ok' => false, 'error' => 'Error al consultar cliente'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $stmt->bind_param('i', $clienteId);
        $stmt->execute();
        $cliente = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$cliente) {
            echo json_encode(['ok' => false, 'error' => 'Cliente no encontrado'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $correo = trim((string) ($cliente['correo'] ?? ''));
        if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['ok' => false, 'error' => 'El cliente no tiene un correo válido'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $byUid = (int) ($_SESSION['uid'] ?? 0) ?: null;
        $reg = cw_client_verification_register_send($clienteId, $sistema, $correo, $byUid);
        if (empty($reg['ok']) || empty($reg['code'])) {
            echo json_encode(['ok' => false, 'error' => $reg['error'] ?? 'No se pudo generar el código'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $code = (string) $reg['code'];
        $nombre = trim((string) ($cliente['nombre_contacto'] ?? 'Cliente'));
        $empresa = trim((string) ($cliente['empresa'] ?? ''));
        $nombreSafe = cw_email_h($nombre !== '' ? $nombre : 'Cliente');
        $empresaSafe = cw_email_h($empresa !== '' ? $empresa : 'su empresa');
        $codeSafe = cw_email_h($code);

        $contenido = cw_email_p('Hola <strong>' . $nombreSafe . '</strong>,')
            . cw_email_p(
                'Recibimos una solicitud para validar la cuenta de <strong>' . $empresaSafe . '</strong> en ConlineWeb.'
            )
            . cw_email_p('Usa este código de verificación:')
            . '<div style="margin:18px 0;text-align:center;">'
            . '<div style="display:inline-block;padding:14px 22px;border-radius:14px;background:#f8fafc;'
            . 'border:1px solid #e2e8f0;font-size:28px;font-weight:800;letter-spacing:.18em;color:#000147;">'
            . $codeSafe . '</div></div>'
            . cw_email_alert(
                'Este código es de un solo uso. Si no solicitaste este mensaje, puedes ignorarlo.',
                'info'
            )
            . cw_email_p('Equipo ConlineWeb', 0);

        $html = cw_email_wrap([
            'title' => 'Código de verificación de cuenta',
            'content' => $contenido,
            'footer' => 'ConlineWeb · León, Guanajuato · WhatsApp 477 118 1285',
            'badge' => 'Verificación',
            'badge_variant' => 'neutral',
        ]);

        require dirname(__DIR__) . '/PHPMailer/src/Exception.php';
        require dirname(__DIR__) . '/PHPMailer/src/PHPMailer.php';
        require dirname(__DIR__) . '/PHPMailer/src/SMTP.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        $mail->Host = 'smtp.gmail.com';
        $mail->Username = 'servicios@conlineweb.com';
        $mail->Password = 'wcglkgcxfebsauqo';
        $mail->setFrom('servicios@conlineweb.com', 'ConlineWeb');
        $mail->addAddress($correo, $nombre !== '' ? $nombre : $correo);
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->isHTML(true);
        $mail->Subject = 'Tu código de verificación ConlineWeb: ' . $code;
        $mail->Body = $html;
        $mail->AltBody = "Hola {$nombre},\n\n"
            . "Tu código de verificación es: {$code}\n\n"
            . "Equipo ConlineWeb";
        $mail->send();

        echo json_encode([
            'ok' => true,
            'message' => 'Código enviado a ' . $correo,
            'correo' => $correo,
            'code' => $code,
            'data' => $reg['data'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Acción no válida'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('client_verification: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error del servidor'], JSON_UNESCAPED_UNICODE);
}
