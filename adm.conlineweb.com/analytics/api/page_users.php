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

$path = trim((string) ($_GET['path'] ?? ''));
if ($path === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'path requerido']);
    exit;
}

$period = $_GET['period'] ?? '30d';
$from = $_GET['from'] ?? null;
$to = $_GET['to'] ?? null;
cw_analytics_web_filter($_GET['web'] ?? 'all');
[$dateFrom, $dateTo] = cw_hub_period_dates($period, $from, $to);

$rows = cw_analytics_page_users($conn, $path, $dateFrom, $dateTo);
$users = cw_analytics_page_users_payload($rows);

$totalTime = 0;
foreach ($users as $u) {
    $totalTime += (int) ($u['time_on_page'] ?? 0);
}
$avgTime = count($users) > 0 ? (int) round($totalTime / count($users)) : 0;

echo json_encode([
    'success' => true,
    'path' => $path,
    'period' => $period,
    'from' => $dateFrom,
    'to' => $dateTo,
    'timezone' => CW_HUB_TIMEZONE,
    'summary' => [
        'visits' => count($users),
        'unique_visitors' => count(array_unique(array_column($users, 'visitor_id'))),
        'avg_time' => $avgTime,
        'avg_time_fmt' => cw_format_duration($avgTime),
        'total_time' => $totalTime,
        'total_time_fmt' => cw_format_duration($totalTime),
    ],
    'users' => $users,
], JSON_UNESCAPED_UNICODE);
