<?php
/**
 * Prueba aislada del correo de alerta de login (mismo código que iniciarSesion).
 *
 * Uso (cPanel):
 *   https://cliente.conlineweb.com/test_login_alert.php?key=cw-login-alert-test-2026
 *   https://cliente.conlineweb.com/test_login_alert.php?key=...&to=tu@correo.com
 *
 * BORRAR o renombrar este archivo cuando termine la prueba.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$expectedKey = 'cw-login-alert-test-2026';
$key = (string) ($_GET['key'] ?? '');
if (!hash_equals($expectedKey, $key)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'key inválida'], JSON_UNESCAPED_UNICODE);
    exit;
}

$candidates = [
    __DIR__ . '/includes/cw_portal_login_alert.php',
    dirname(__DIR__) . '/includes/cw_portal_login_alert.php',
    '/home/conlineweb/cliente.conlineweb.com/includes/cw_portal_login_alert.php',
    '/home/conlineweb/includes/cw_portal_login_alert.php',
];
$loadedFrom = null;
foreach ($candidates as $path) {
    if (is_file($path)) {
        require_once $path;
        $loadedFrom = $path;
        break;
    }
}

$out = [
    'ok' => false,
    'loaded_from' => $loadedFrom,
    'phpmailer' => null,
    'recipients' => null,
    'smtp_host' => null,
    'smtp_user' => null,
    'send_ok' => null,
    'log_tail' => [],
    'error' => null,
];

if ($loadedFrom === null || !function_exists('cw_portal_notify_login')) {
    $out['error'] = 'No se pudo cargar cw_portal_login_alert.php';
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$toOverride = trim((string) ($_GET['to'] ?? ''));
if ($toOverride !== '' && filter_var($toOverride, FILTER_VALIDATE_EMAIL)) {
    putenv('CW_LOGIN_ALERT_TO=' . $toOverride);
}

$out['phpmailer'] = function_exists('cw_portal_login_alert_load_phpmailer')
    ? cw_portal_login_alert_load_phpmailer()
    : false;
$out['recipients'] = function_exists('cw_portal_login_alert_recipients')
    ? cw_portal_login_alert_recipients()
    : [];
if (function_exists('cw_portal_login_alert_smtp')) {
    $smtp = cw_portal_login_alert_smtp();
    $out['smtp_host'] = $smtp['host'] ?? null;
    $out['smtp_user'] = $smtp['user'] ?? null;
}

try {
    $out['send_ok'] = cw_portal_notify_login([
        'uid' => 999001,
        'usuario' => 'prueba-alerta-login',
        'tipo' => 1,
        'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
        'user_agent' => 'test_login_alert.php',
        'environment' => 'prueba-cpanel',
        'skip_geo' => true,
        'force' => true,
    ]);
    $out['ok'] = (bool) $out['send_ok'];
} catch (Throwable $e) {
    $out['error'] = $e->getMessage();
}

$logCandidates = [
    __DIR__ . '/logs/login_alerts.log',
    '/home/conlineweb/cliente.conlineweb.com/logs/login_alerts.log',
    dirname(__DIR__) . '/logs/login_alerts.log',
    sys_get_temp_dir() . '/cw_login_alerts_log/login_alerts.log',
];
foreach ($logCandidates as $logFile) {
    if (!is_file($logFile)) {
        continue;
    }
    $lines = @file($logFile, FILE_IGNORE_NEW_LINES);
    if (is_array($lines)) {
        $out['log_file'] = $logFile;
        $out['log_tail'] = array_slice($lines, -15);
    }
    break;
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
