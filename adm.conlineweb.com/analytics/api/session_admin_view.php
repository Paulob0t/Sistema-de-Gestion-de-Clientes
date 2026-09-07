<?php
require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_config.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_analytics.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_permissions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

cw_hub_migrate($conn);

if (!cw_hub_can('hub.analytics.view')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sin permiso']);
    exit;
}

$data = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($data)) {
    $data = $_POST;
}

$sessionId = trim((string) ($data['session_id'] ?? ''));
if ($sessionId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'session_id requerido']);
    exit;
}

$adminUid = (int) ($_SESSION['uid'] ?? 0);
$ok = cw_analytics_record_admin_session_view($conn, $sessionId, $adminUid);

echo json_encode([
    'success' => $ok,
    'session_id' => $sessionId,
    'viewed_at' => cw_hub_now(),
    'admin_uid' => $adminUid,
], JSON_UNESCAPED_UNICODE);
