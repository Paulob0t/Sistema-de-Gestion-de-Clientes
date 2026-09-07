<?php
require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_config.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_analytics.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_permissions.php';

header('Content-Type: application/json; charset=utf-8');

cw_hub_migrate($conn);
if (!cw_hub_can('hub.analytics.view')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sin permiso']);
    exit;
}

$sessionId = trim((string) ($_GET['session_id'] ?? ''));
if ($sessionId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'session_id requerido']);
    exit;
}

$trace = cw_analytics_session_trace_payload($conn, $sessionId);
if (!$trace) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Sesión no encontrada']);
    exit;
}

echo json_encode([
    'success' => true,
    'timezone' => CW_HUB_TIMEZONE,
    'trace' => $trace,
], JSON_UNESCAPED_UNICODE);
