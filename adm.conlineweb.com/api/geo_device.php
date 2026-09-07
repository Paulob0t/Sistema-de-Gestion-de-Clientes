<?php
/**
 * API: registrar GPS del dispositivo para acceso ADM (cookie 90 días).
 */
declare(strict_types=1);

define('CW_ADM_SKIP_GEO_GATE', true);

require_once __DIR__ . '/../auth_middleware.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!adm_is_production_host()) {
    echo json_encode(['status' => 'ok', 'skipped' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

$uid = (int) ($_SESSION['uid'] ?? 0);
$tipo = (int) ($_SESSION['tipo'] ?? 0);
if ($uid <= 0 || $tipo < 1 || $tipo > 5 || !isAdminSessionValid()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Sesión no válida'], JSON_UNESCAPED_UNICODE);
    exit;
}

$geoFile = dirname(__DIR__, 2) . '/includes/cw_adm_device_geo.php';
if (!is_file($geoFile)) {
    $geoFile = dirname(__DIR__) . '/includes/cw_adm_device_geo.php';
}
require_once $geoFile;

$deviceId = (string) ($_POST['device_id'] ?? '');
$latRaw = trim((string) ($_POST['geo_lat'] ?? ''));
$lngRaw = trim((string) ($_POST['geo_lng'] ?? ''));
$accRaw = trim((string) ($_POST['geo_accuracy'] ?? ''));

if ($latRaw === '' || $lngRaw === '' || !is_numeric($latRaw) || !is_numeric($lngRaw)) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Coordenadas inválidas'], JSON_UNESCAPED_UNICODE);
    exit;
}

$lat = (float) $latRaw;
$lng = (float) $lngRaw;
$accuracy = $accRaw !== '' && is_numeric($accRaw) ? (int) round((float) $accRaw) : null;

if (!cw_adm_device_geo_save($uid, $deviceId, $lat, $lng, $accuracy)) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar la ubicación del dispositivo'], JSON_UNESCAPED_UNICODE);
    exit;
}

$loginEventId = (int) ($_SESSION['cw_login_event_id'] ?? 0);
if ($loginEventId > 0) {
    try {
        if (!isset($conn) || !($conn instanceof mysqli)) {
            include __DIR__ . '/../conn.php';
        }
        if (isset($conn) && $conn instanceof mysqli) {
            $logFile = dirname(__DIR__, 2) . '/includes/cw_portal_login_log.php';
            if (!is_file($logFile)) {
                $logFile = __DIR__ . '/../includes/cw_portal_login_log.php';
            }
            if (is_file($logFile)) {
                require_once $logFile;
            }
            cw_adm_device_geo_attach_to_login_event($conn, $loginEventId, $lat, $lng, $accuracy);
        }
    } catch (Throwable $e) {
        error_log('api/geo_device: ' . $e->getMessage());
    }
}

echo json_encode(['status' => 'ok'], JSON_UNESCAPED_UNICODE);
