<?php
/**
 * WhatsApp Hub — plantilla Twilio para leads web (reutiliza infraestructura existente).
 */
require_once __DIR__ . '/cw_hub_config.php';

define('CW_HUB_WA_SESSIONS_DIR', dirname(__DIR__) . '/whatsapp/sessions');

function cw_hub_normalize_phone(string $telefono): ?array
{
    $startsWithPlus = strlen($telefono) > 0 && $telefono[0] === '+';
    $digits = preg_replace('/\D+/', '', $telefono);
    if ($digits === '' || strlen($digits) < 7) {
        return null;
    }
    if (!$startsWithPlus && strlen($digits) === 10) {
        $digits = '521' . $digits;
    } elseif (preg_match('/^52\d{10}$/', $digits)) {
        $digits = '521' . substr($digits, 2);
    } elseif (preg_match('/^52\d{11,}$/', $digits)) {
        $digits = '521' . substr($digits, -10);
    }
    if (strlen($digits) < 7 || strlen($digits) > 15) {
        return null;
    }
    return ['e164' => '+' . $digits, 'digits' => $digits];
}

function cw_hub_send_welcome_template(string $telefono, string $nombre, int $leadId, mysqli $conn): array
{
    $norm = cw_hub_normalize_phone($telefono);
    if (!$norm) {
        return ['ok' => false, 'error' => 'Teléfono inválido'];
    }

    require_once __DIR__ . '/cw_twilio_config.php';
    $tw = cw_twilio_config();
    $sid = $tw['sid'];
    $token = $tw['token'];
    $from = $tw['from'];
    $templateSid = $tw['template_sid'] !== '' ? $tw['template_sid'] : CW_HUB_TWILIO_TEMPLATE_SID;

    $postFields = [
        'From' => $from,
        'To' => 'whatsapp:' . $norm['e164'],
        'ContentSid' => $templateSid,
    ];

    $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postFields),
        CURLOPT_USERPWD => $sid . ':' . $token,
        CURLOPT_TIMEOUT => 15,
    ]);
    curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http < 200 || $http >= 300) {
        $err = $http === 401
            ? cw_twilio_auth_error_hint('Authenticate')
            : 'Twilio HTTP ' . $http;
        return ['ok' => false, 'error' => $err];
    }

    cw_hub_register_wa_session($norm['digits'], $nombre);

    $upd = $conn->prepare('UPDATE leads SET whatsapp_enviado = 1, whatsapp_enviado_fecha = NOW() WHERE id = ?');
    $upd->bind_param('i', $leadId);
    $upd->execute();
    $upd->close();

    return ['ok' => true];
}

function cw_hub_register_wa_session(string $digits, string $nombre): void
{
    if (!is_dir(CW_HUB_WA_SESSIONS_DIR)) {
        @mkdir(CW_HUB_WA_SESSIONS_DIR, 0755, true);
    }
    $sessionFile = CW_HUB_WA_SESSIONS_DIR . '/whatsapp_' . $digits . '.json';
    $metaFile = CW_HUB_WA_SESSIONS_DIR . '/whatsapp_' . $digits . '_meta.json';

    $messages = [];
    if (is_file($sessionFile)) {
        $messages = json_decode((string) file_get_contents($sessionFile), true) ?: [];
    }
    $messages[] = [
        'role' => 'assistant',
        'content' => '👋 [Bienvenida automática — lead web]',
        'timestamp' => time(),
        'template' => true,
        'origen' => 'cw_hub',
    ];
    file_put_contents($sessionFile, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

    $meta = is_file($metaFile) ? (json_decode((string) file_get_contents($metaFile), true) ?: []) : [];
    $meta['ultima_actualizacion'] = time();
    $meta['client_name'] = $nombre;
    $meta['origen_lead'] = 'web_hub';
    file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}
