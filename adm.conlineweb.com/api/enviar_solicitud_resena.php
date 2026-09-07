<?php
/**
 * Envía solicitud de reseña Google por correo.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

define('AUTH_MIDDLEWARE_FUNCTIONS_ONLY', true);
require_once dirname(__DIR__) . '/auth_middleware.php';
require_once dirname(__DIR__) . '/includes/cw_client_messages_service.php';
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

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = $_POST;
$raw = file_get_contents('php://input');
if (is_string($raw) && $raw !== '' && strpos((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== false) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = array_merge($input, $decoded);
    }
}

$clienteId = (int) ($input['cliente_id'] ?? 0);
$sistema = trim((string) ($input['sistema'] ?? 'conlineweb'));
if ($sistema === '') {
    $sistema = 'conlineweb';
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

$stmt = $db->prepare('SELECT id, empresa, nombre_contacto, correo, telefono FROM clientes WHERE id = ? LIMIT 1');
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

$msgData = cw_client_messages_load();
$reviewUrl = trim((string) ($msgData['settings']['google_review_url'] ?? ''));
if ($reviewUrl === '') {
    $reviewUrl = 'https://g.page/r/CQ-1-K_N5AAEEBM/review';
}

$nombre = trim((string) ($cliente['nombre_contacto'] ?? 'Cliente'));
$empresa = trim((string) ($cliente['empresa'] ?? ''));

$html = cw_email_google_review_request([
    'nombre' => $nombre !== '' ? $nombre : 'Cliente',
    'empresa' => $empresa,
    'review_url' => $reviewUrl,
    'footer' => 'ConlineWeb · León, Guanajuato · WhatsApp 477 118 1285',
]);

require dirname(__DIR__) . '/PHPMailer/src/Exception.php';
require dirname(__DIR__) . '/PHPMailer/src/PHPMailer.php';
require dirname(__DIR__) . '/PHPMailer/src/SMTP.php';

$mail = new PHPMailer\PHPMailer\PHPMailer(true);

try {
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
    $mail->Subject = 'ConlineWeb te invita a dejar una reseña en Google';
    $mail->Body = $html;
    $mail->AltBody = "Hola {$nombre},\n\n"
        . "Somos el equipo de ConlineWeb y te escribimos para pedirte un favor:\n"
        . "si tu experiencia fue positiva, ¿nos dejarías una reseña en nuestro perfil de Google?\n\n"
        . "{$reviewUrl}\n\n"
        . "Este mensaje lo envía ConlineWeb, no Google.\n\n"
        . "Mil gracias.\nEquipo ConlineWeb";

    $mail->send();

    echo json_encode([
        'ok' => true,
        'message' => 'Correo enviado a ' . $correo,
        'correo' => $correo,
        'review_url' => $reviewUrl,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('enviar_solicitud_resena: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo enviar el correo'], JSON_UNESCAPED_UNICODE);
}
