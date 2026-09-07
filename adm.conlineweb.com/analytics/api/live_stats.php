<?php
require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_analytics.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_permissions.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_notify.php';

header('Content-Type: application/json; charset=utf-8');

cw_hub_migrate($conn);
if (!cw_hub_can('hub.analytics.view')) {
    http_response_code(403);
    echo json_encode(['success' => false]);
    exit;
}

cw_analytics_web_filter($_GET['web'] ?? 'all');

$action = $_GET['action'] ?? 'snapshot';

if ($action === 'reminders') {
    echo json_encode([
        'success' => true,
        'upcoming' => cw_hub_upcoming_reminders($conn, 72, 20),
        'pending' => cw_hub_pending_reminders($conn, 20),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'visits') {
    echo json_encode(['success' => true, 'data' => cw_analytics_visits_live_snapshot($conn)], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => true, 'data' => cw_analytics_live_snapshot($conn)], JSON_UNESCAPED_UNICODE);
