<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../auth_middleware.php';
require_once __DIR__ . '/../includes/cw_twilio_config.php';

// ============================================================
// CONFIGURACIÓN DE LA PLANTILLA DE SALUDO
// ============================================================
// Content SID: Twilio Console > Messaging > Content Template Builder
$twilio = cw_twilio_config();
define('TEMPLATE_CONTENT_SID', $twilio['template_sid'] !== ''
    ? $twilio['template_sid']
    : 'HXb4f3d05dccbda05a03321376294e1d43');

// ¿Tu plantilla tiene variables? Ej: "Hola {{1}}, habla Alexa de EFEGE..."
// {{1}} = nombre del cliente
define('TEMPLATE_TIENE_VARIABLES', false);
// ============================================================

function json_fail(int $code, string $msg): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

$telefono      = isset($_POST['telefono'])       ? trim((string)$_POST['telefono'])       : '';
$nombreCliente = isset($_POST['nombre_cliente']) ? trim((string)$_POST['nombre_cliente']) : '';

if ($telefono === '') {
    json_fail(400, 'Teléfono requerido');
}

// ---------------------------------------------------------------
// Normalización robusta del teléfono
// ---------------------------------------------------------------
$startsWithPlus = strlen($telefono) > 0 && $telefono[0] === '+';
$digits = preg_replace('/\D+/', '', $telefono);

if ($digits === '') {
    json_fail(400, 'El teléfono no contiene dígitos válidos (recibido: "' . $telefono . '")');
}

if (strlen($digits) < 7) {
    json_fail(400, 'Número demasiado corto para ser válido (recibido: "' . $telefono . '")');
}

if (strlen($digits) > 15) {
    json_fail(400, 'Número demasiado largo para formato E.164 (recibido: "' . $telefono . '")');
}

// Caso 1: 10 dígitos sin código de país → asumir México (521XXXXXXXXXX)
if (!$startsWithPlus && strlen($digits) === 10) {
    $digits = '521' . $digits;
}
// Caso 2: 52 + 10 dígitos → falta el 1 de WhatsApp MX
elseif (preg_match('/^52\d{10}$/', $digits)) {
    $digits = '521' . substr($digits, 2);
}
// Caso 3: 521 + 10 dígitos → formato correcto WhatsApp MX
elseif (preg_match('/^521\d{10}$/', $digits)) {
    // ya correcto
}
// Caso 4: 52 + 11 o más dígitos → quedar con últimos 10
elseif (preg_match('/^52\d{11,}$/', $digits)) {
    $digits = '521' . substr($digits, -10);
}
// Caso 5: cualquier otro código de país (Chile 56, Colombia 57, USA 1, etc.)
// → confiar en el número tal como viene; ya tiene suficientes dígitos

$toE164 = '+' . $digits;

// Validación final de longitud E.164 (7–15 dígitos)
if (strlen($digits) < 7 || strlen($digits) > 15) {
    json_fail(400, 'El número resultante no es un E.164 válido: ' . $toE164);
}

error_log("enviar_mensaje_plantilla → teléfono recibido: \"{$telefono}\" → normalizado: {$toE164}");

$TWILIO_ACCOUNT_SID = $twilio['sid'];
$TWILIO_AUTH_TOKEN  = $twilio['token'];
$TWILIO_FROM        = $twilio['from'];

if ($TWILIO_ACCOUNT_SID === '' || $TWILIO_AUTH_TOKEN === '' || str_contains($TWILIO_AUTH_TOKEN, 'PEGAR_AQUI')) {
    json_fail(500, cw_twilio_auth_error_hint('Falta Auth Token válido'));
}

if (TEMPLATE_CONTENT_SID === '' || TEMPLATE_CONTENT_SID === 'HX_AQUI_TU_CONTENT_SID') {
    json_fail(500, 'Debes configurar TEMPLATE_CONTENT_SID / TWILIO_TEMPLATE_SID');
}

// Construir payload para Twilio
$postFields = [
    'From'       => $TWILIO_FROM,
    'To'         => 'whatsapp:' . $toE164,
    'ContentSid' => TEMPLATE_CONTENT_SID,
];

// Si la plantilla tiene variables, añade ContentVariables
// {{1}} = nombre del cliente recibido por POST
if (TEMPLATE_TIENE_VARIABLES) {
    $variables = [
        '1' => $nombreCliente !== '' ? $nombreCliente : 'Cliente',
    ];
    $postFields['ContentVariables'] = json_encode($variables, JSON_UNESCAPED_UNICODE);
}

// Enviar vía Twilio
$url = "https://api.twilio.com/2010-04-01/Accounts/{$TWILIO_ACCOUNT_SID}/Messages.json";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($postFields),
    CURLOPT_USERPWD        => $TWILIO_ACCOUNT_SID . ':' . $TWILIO_AUTH_TOKEN,
    CURLOPT_TIMEOUT        => 15,
]);
$resp    = curl_exec($ch);
$http    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

error_log("enviar_mensaje_plantilla → HTTP {$http}, sid={$TWILIO_ACCOUNT_SID}, body: " . $resp);

if ($curlErr !== '') {
    json_fail(502, 'Error cURL: ' . $curlErr);
}

if ($http < 200 || $http >= 300) {
    $detail = '';
    $decoded = json_decode((string)$resp, true);
    if (is_array($decoded) && isset($decoded['message'])) {
        $detail = (string) $decoded['message'];
    }
    if ($http === 401 || strcasecmp($detail, 'Authenticate') === 0) {
        json_fail(502, cw_twilio_auth_error_hint($detail));
    }
    json_fail(502, 'No se pudo enviar la plantilla' . ($detail !== '' ? ': ' . $detail : " (HTTP {$http})"));
}

// ------------------------------------------------------------------
// Registrar en el archivo de sesión local para que el modal lo muestre
// ------------------------------------------------------------------
$sessionDir = realpath(__DIR__ . '/../whatsapp/sessions');
if ($sessionDir !== false && is_dir($sessionDir)) {
    $sessionFile = $sessionDir . DIRECTORY_SEPARATOR . 'whatsapp_' . $digits . '.json';
    $metaFile    = $sessionDir . DIRECTORY_SEPARATOR . 'whatsapp_' . $digits . '_meta.json';

    $messages = [];
    if (is_file($sessionFile)) {
        $raw = @file_get_contents($sessionFile);
        $messages = json_decode((string)$raw, true);
        if (!is_array($messages)) {
            $messages = [];
        }
    }

    $messages[] = [
        'role'      => 'assistant',
        'content'   => '👋 [Plantilla de saludo enviada]',
        'timestamp' => time(),
        'template'  => true,
    ];

    @file_put_contents(
        $sessionFile,
        json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );

    // Actualizar meta
    $meta = [];
    if (is_file($metaFile)) {
        $meta = json_decode((string)@file_get_contents($metaFile), true);
        if (!is_array($meta)) {
            $meta = [];
        }
    }
    $meta['ultima_actualizacion']    = time();
    $meta['agent_active']            = true;
    $meta['agent_last_interaction']  = time();
    $meta['agent_session_id']        = session_id();

    @file_put_contents(
        $metaFile,
        json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

echo json_encode([
    'success' => true,
    'message' => 'Plantilla de saludo enviada correctamente',
    'to'      => $toE164,
], JSON_UNESCAPED_UNICODE);
