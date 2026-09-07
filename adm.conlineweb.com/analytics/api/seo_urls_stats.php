<?php
require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_permissions.php';
require_once dirname(__DIR__, 2) . '/includes/cw_seo_mexico_kpis.php';

header('Content-Type: application/json; charset=utf-8');

cw_hub_migrate($conn);
if (!cw_hub_can('hub.analytics.view')) {
    http_response_code(403);
    echo json_encode(['success' => false]);
    exit;
}

$period = $_GET['period'] ?? '90d';
$from = $_GET['from'] ?? null;
$to = $_GET['to'] ?? null;
$plaza = isset($_GET['plaza']) ? (string) $_GET['plaza'] : null;
$categoria = isset($_GET['categoria']) ? (string) $_GET['categoria'] : null;
[$dateFrom, $dateTo] = cw_hub_period_dates($period, $from, $to);
$snap = cw_seo_mexico_kpis_urls_snapshot($conn, $dateFrom, $dateTo, $plaza, $categoria);

echo json_encode([
    'success' => true,
    'data' => $snap,
], JSON_UNESCAPED_UNICODE);
