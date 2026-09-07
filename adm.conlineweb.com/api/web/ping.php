<?php
/**
 * Ping del hub público (verifica API key + origen/proxy).
 */
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cw_hub_api_fail('Método no permitido', 405);
}
cw_hub_validate_request();

$data = cw_hub_json_input();
$site = strtolower(cw_hub_s($data['sitio'] ?? $data['web'] ?? ($_SERVER['HTTP_X_CW_PROXY_SITE'] ?? ''), 8));
if (!in_array($site, ['mx', 'cl', 'us'], true)) {
    $site = '';
}

echo json_encode([
    'success' => true,
    'pong' => true,
    'site' => $site,
    'origin' => $_SERVER['HTTP_ORIGIN'] ?? '',
    'proxy_site' => $_SERVER['HTTP_X_CW_PROXY_SITE'] ?? '',
    'server_time' => cw_hub_now(),
    'tracking_hosts' => CW_HUB_TRACKING_HOSTS,
], JSON_UNESCAPED_UNICODE);
cw_hub_api_exit();
