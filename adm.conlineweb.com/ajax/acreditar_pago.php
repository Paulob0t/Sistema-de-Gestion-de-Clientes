<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../error.log');

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../auth_middleware.php';
require_once __DIR__ . '/../conn.php';

function json_fail(int $code, string $message): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

// Obtener parámetros
$telefono = isset($_POST['telefono']) ? trim((string)$_POST['telefono']) : '';
if ($telefono === '') {
    json_fail(400, 'Teléfono requerido');
}

// Normalizar dígitos
$digits = preg_replace('/\D+/', '', $telefono);
if (preg_match('/^52\d{10}$/', $digits)) {
    $digits = '521' . substr($digits, 2);
} elseif (strlen($digits) === 10) {
    $digits = '521' . $digits;
}
$toE164 = '+' . $digits;

// Rutas de archivos de sesión
$sessionsDir = realpath(__DIR__ . '/../whatsapp/sessions');
if (!$sessionsDir) {
    json_fail(500, 'No se encontró el directorio de sesiones');
}
$sessionFile = $sessionsDir . '/whatsapp_' . $digits . '.json';
$metaFile    = $sessionsDir . '/whatsapp_' . $digits . '_meta.json';

if (!file_exists($metaFile)) {
    json_fail(404, 'No se encontró el meta del cliente');
}

$meta = json_decode((string)@file_get_contents($metaFile), true) ?: [];

$solicitudId = $meta['pending_solicitud_id'] ?? null;

// Fallback: si no hay pending_solicitud_id en el meta, buscar en BD la solicitud
// más reciente sin pagar para este número de teléfono
if (!$solicitudId) {
    // Buscar con el teléfono en diferentes formatos posibles
    $posiblesTelefonos = array_unique([$digits, '+' . $digits, $toE164]);
    $placeholders = implode(',', array_fill(0, count($posiblesTelefonos), '?'));
    $types = str_repeat('s', count($posiblesTelefonos));

    $stmt = $conn->prepare("
        SELECT id FROM solicitud_whatsapp
        WHERE pagado = 0 AND telefono IN ($placeholders)
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->bind_param($types, ...$posiblesTelefonos);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $solicitudId = (int)$row['id'];
        error_log("[AcreditarPago] pending_solicitud_id recuperado de BD: {$solicitudId}");
    }
}

$ticketId    = null;
$confirmData = [];

// 1. Si hay solicitud en el sistema, confirmarla vía API
if ($solicitudId) {
    $apiUrl   = 'https://adm.conlineweb.com/api/confirmar_pago_solicitud.php';
    $postData = json_encode(['solicitud_id' => $solicitudId]);

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postData,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $apiResponse = curl_exec($ch);
    $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError   = curl_error($ch);
    curl_close($ch);

    error_log("[AcreditarPago] API confirmar_pago_solicitud - HTTP {$httpCode} - Response: " . substr($apiResponse ?: '', 0, 300));

    if (!$curlError && ($httpCode === 200 || $httpCode === 201)) {
        $confirmData = json_decode($apiResponse, true) ?: [];
        $ticketId    = $confirmData['ticket_id'] ?? null;
    } else {
        error_log("[AcreditarPago] Advertencia: No se pudo confirmar vía API (HTTP {$httpCode}). Se procede a notificar al cliente de todas formas.");
    }
} else {
    error_log("[AcreditarPago] Sin pending_solicitud_id — acreditando manualmente sin API.");
}

// 2. Enviar mensaje de confirmación al cliente vía Twilio
$TWILIO_ACCOUNT_SID = getenv('TWILIO_ACCOUNT_SID') ?: 'ACe545cc9bfdfb41f417c8e1cc34062678';
$TWILIO_AUTH_TOKEN  = getenv('TWILIO_AUTH_TOKEN')  ?: '6068c519f69979eefe00f63d61ad25a8';
$TWILIO_FROM        = getenv('TWILIO_FROM')         ?: 'whatsapp:+15557419621';

$mensajeCliente  = "✅ *¡Tu pago ha sido confirmado y acreditado!*\n\n";
if ($ticketId) {
    $mensajeCliente .= "📋 *Ticket:* #{$ticketId}\n";
    $mensajeCliente .= "📌 *Solicitud:* " . ($confirmData['titulo'] ?? "Solicitud #{$solicitudId}") . "\n";
}
$mensajeCliente .= "⏱️ *Los días hábiles de trabajo comienzan a partir de hoy.*\n\n";
$mensajeCliente .= "Nuestro equipo ya comenzará a trabajar en tu solicitud. Te notificaremos cuando esté lista. 😊";

$urlTwilio = "https://api.twilio.com/2010-04-01/Accounts/{$TWILIO_ACCOUNT_SID}/Messages.json";
$twilioPost = http_build_query([
    'From' => $TWILIO_FROM,
    'To'   => 'whatsapp:' . $toE164,
    'Body' => $mensajeCliente,
]);

$chT = curl_init($urlTwilio);
curl_setopt_array($chT, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $twilioPost,
    CURLOPT_USERPWD        => $TWILIO_ACCOUNT_SID . ':' . $TWILIO_AUTH_TOKEN,
    CURLOPT_TIMEOUT        => 15,
]);
$twilioResp     = curl_exec($chT);
$twilioHttpCode = curl_getinfo($chT, CURLINFO_HTTP_CODE);
curl_close($chT);

error_log("[AcreditarPago] Twilio send - HTTP {$twilioHttpCode}");

// 3. Guardar el mensaje enviado en el historial de la conversación
if (file_exists($sessionFile)) {
    $messages = json_decode((string)@file_get_contents($sessionFile), true) ?: [];
    $messages[] = [
        'role'      => 'assistant',
        'content'   => $mensajeCliente,
        'timestamp' => time(),
        'origen'    => 'admin_acreditacion',
    ];
    @file_put_contents($sessionFile, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

// 4. Limpiar flags de pago pendiente en meta
unset($meta['comprobante_pendiente'], $meta['comprobante_at'], $meta['pending_solicitud_id'], $meta['pending_payment_method']);
$meta['pago_acreditado_at'] = time();
if ($ticketId) {
    $meta['ticket_id'] = $ticketId;
}
@file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

echo json_encode([
    'success'        => true,
    'ticket_id'      => $ticketId,
    'ticket_created' => $ticketId !== null,
    'message'        => 'Pago acreditado y cliente notificado correctamente',
    'twilio_ok'      => ($twilioHttpCode >= 200 && $twilioHttpCode < 300),
], JSON_UNESCAPED_UNICODE);
