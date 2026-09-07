<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
$mensaje = isset($_POST['mensaje']) ? trim((string)$_POST['mensaje']) : '';

if ($telefono === '' || $mensaje === '') {
    json_fail(400, 'Teléfono y mensaje son requeridos');
}

// Limpiar teléfono (solo dígitos)
$digits = preg_replace('/\D+/', '', $telefono);

if ($digits === '') {
    json_fail(400, 'Teléfono inválido');
}

// Normalizar formato WhatsApp México: 521XXXXXXXXXX
if (preg_match('/^52\d{10}$/', $digits)) {
    $digits = '521' . substr($digits, 2);
} elseif (preg_match('/^52(?!1)\d{10,}$/', $digits)) {
    $digits = '521' . substr($digits, -10);
} elseif (strlen($digits) === 10) {
    // Si es solo 10 dígitos, asumir México
    $digits = '521' . $digits;
}

$toE164 = '+' . $digits;

// Configuración Twilio
require_once __DIR__ . '/../includes/cw_twilio_config.php';
$twilio = cw_twilio_config();
$TWILIO_ACCOUNT_SID = $twilio['sid'];
$TWILIO_AUTH_TOKEN = $twilio['token'];
$TWILIO_FROM = $twilio['from'];

if ($TWILIO_ACCOUNT_SID === '' || $TWILIO_AUTH_TOKEN === '' || $TWILIO_FROM === '' || str_contains($TWILIO_AUTH_TOKEN, 'PEGAR_AQUI')) {
    json_fail(500, cw_twilio_auth_error_hint('Falta Auth Token válido'));
}

// Log para debug
error_log("Enviando mensaje WhatsApp: FROM={$TWILIO_FROM}, TO={$toE164}");

// Función para enviar mensaje
function twilio_send_whatsapp_text(string $sid, string $token, string $from, string $to, string $body): array {
    $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";
    $post = http_build_query([
        'From' => $from,
        'To' => 'whatsapp:' . $to,
        'Body' => $body,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_USERPWD => $sid . ':' . $token,
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    return [
        'http_code' => $http,
        'error' => $err,
        'raw' => $resp,
    ];
}

// Enviar mensaje
$send = twilio_send_whatsapp_text($TWILIO_ACCOUNT_SID, $TWILIO_AUTH_TOKEN, $TWILIO_FROM, $toE164, $mensaje);

// Log de la respuesta completa para debug
error_log("Twilio Response - HTTP Code: {$send['http_code']}, Body: {$send['raw']}");

if ($send['http_code'] < 200 || $send['http_code'] >= 300) {
    $errorDetail = '';
    $errorCode = '';
    
    if ($send['raw']) {
        $decoded = json_decode($send['raw'], true);
        if (isset($decoded['message'])) {
            $errorDetail = $decoded['message'];
        }
        if (isset($decoded['code'])) {
            $errorCode = $decoded['code'];
        }
        
        // Log completo para debug
        error_log("Error Twilio: " . $send['raw']);
    }
    
    // Mensaje más específico según el error
    if (strpos($errorDetail, 'Channel') !== false || strpos($errorDetail, 'From address') !== false) {
        json_fail(502, 'El número de WhatsApp FROM no está configurado en tu cuenta de Twilio. Verifica que uses el número correcto del Sandbox de WhatsApp o un número aprobado. Error: ' . $errorDetail);
    }
    
    json_fail(502, 'No se pudo enviar el mensaje' . ($errorDetail ? ': ' . $errorDetail : '') . ($errorCode ? ' (Código: ' . $errorCode . ')' : ''));
}

// Verificar que la respuesta de Twilio sea válida
$twilioResponse = json_decode($send['raw'], true);
if (!$twilioResponse || !isset($twilioResponse['sid'])) {
    error_log("Respuesta de Twilio inválida: " . $send['raw']);
    json_fail(502, 'Respuesta inválida de Twilio');
}

// Log del SID del mensaje enviado
error_log("Mensaje enviado exitosamente. SID: {$twilioResponse['sid']}, Status: {$twilioResponse['status']}");

// Guardar en el log de conversación
$sessionDir = realpath(__DIR__ . '/../whatsapp/sessions');
error_log("Directorio de sesiones: " . ($sessionDir ?: 'NO ENCONTRADO'));

if ($sessionDir !== false && is_dir($sessionDir)) {
    $sessionFile = $sessionDir . DIRECTORY_SEPARATOR . 'whatsapp_' . $digits . '.json';
    $metaFile = $sessionDir . DIRECTORY_SEPARATOR . 'whatsapp_' . $digits . '_meta.json';
    
    error_log("Guardando en archivo: " . $sessionFile);

    $messages = [];
    if (is_file($sessionFile)) {
        $messages = json_decode((string)@file_get_contents($sessionFile), true);
        if (!is_array($messages)) $messages = [];
        error_log("Mensajes existentes: " . count($messages));
    } else {
        error_log("Archivo de conversación no existe, se creará nuevo");
    }

    // Asegurar que existe el mensaje de sistema
    if (empty($messages) || !isset($messages[0]['role']) || $messages[0]['role'] !== 'system') {
        $promptPath = realpath(__DIR__ . '/../whatsapp/prompt_efege.txt');
        $systemPrompt = ($promptPath && is_file($promptPath)) 
            ? (string)@file_get_contents($promptPath) 
            : 'Eres Alexa, asistente virtual de Efege.';
        array_unshift($messages, ['role' => 'system', 'content' => $systemPrompt]);
    }

    // Agregar mensaje saliente
    $messages[] = [
        'role' => 'assistant', 
        'content' => $mensaje,
        'timestamp' => time()
    ];
    
    $saved = @file_put_contents($sessionFile, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    
    if ($saved === false) {
        error_log("ERROR: No se pudo guardar el archivo de conversación");
    } else {
        error_log("Conversación guardada exitosamente. Total mensajes: " . count($messages));
    }

    // Actualizar metadata
    $lastInboundIdx = -1;
    foreach ($messages as $i => $item) {
        if (!is_array($item)) continue;
        $role = isset($item['role']) ? (string)$item['role'] : '';
        $content = isset($item['content']) ? trim((string)$item['content']) : '';
        if ($content === '' || $role === 'system') continue;
        if ($role === 'user') {
            $lastInboundIdx = (int)$i;
        }
    }

    $meta = [];
    if (is_file($metaFile)) {
        $mraw = @file_get_contents($metaFile);
        $meta = json_decode((string)$mraw, true);
        if (!is_array($meta)) $meta = [];
    }
    $meta['agent_last_sent_inbound_idx'] = $lastInboundIdx;
    $meta['agent_last_sent_at'] = gmdate('c');
    $meta['ultima_actualizacion'] = time();
    
    // Marcar que un agente humano está manejando activamente la conversación
    // Esto evitará que el bot de OpenAI responda automáticamente
    $meta['agent_active'] = true;
    $meta['agent_last_interaction'] = time();
    $meta['agent_session_id'] = session_id();
    
    $metaSaved = @file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    
    if ($metaSaved === false) {
        error_log("ERROR: No se pudo guardar el archivo de metadata");
    } else {
        error_log("Metadata guardada exitosamente. Agente activo marcado.");
    }
} else {
    error_log("ERROR: Directorio de sesiones no encontrado o no es válido");
}

echo json_encode([
    'success' => true,
    'message' => 'Mensaje enviado correctamente',
    'data' => [
        'telefono' => $toE164,
        'mensaje' => $mensaje,
        'twilio_sid' => $twilioResponse['sid'] ?? null,
        'twilio_status' => $twilioResponse['status'] ?? null,
        'from' => $TWILIO_FROM
    ]
], JSON_UNESCAPED_UNICODE);
