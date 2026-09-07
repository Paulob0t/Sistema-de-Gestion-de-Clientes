<?php
require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_permissions.php';
require_once dirname(__DIR__, 2) . '/includes/cw_seo_mexico_kpis.php';
require_once dirname(__DIR__, 2) . '/includes/adm_paths.php';

header('Content-Type: application/json; charset=utf-8');

cw_hub_migrate($conn);
if (!cw_hub_can('hub.analytics.view')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sin permiso']);
    exit;
}

$period = $_GET['period'] ?? '90d';
$from = $_GET['from'] ?? null;
$to = $_GET['to'] ?? null;
[$dateFrom, $dateTo] = cw_hub_period_dates($period, $from, $to);

$status = (string) ($_GET['status'] ?? 'leads');
$scope = (string) ($_GET['scope'] ?? 'site');
$plaza = isset($_GET['plaza']) ? (string) $_GET['plaza'] : null;
$path = isset($_GET['path']) ? (string) $_GET['path'] : null;
$categoria = isset($_GET['categoria']) ? (string) $_GET['categoria'] : null;

$result = cw_seo_mexico_kpis_leads_list(
    $conn,
    $dateFrom,
    $dateTo,
    $status,
    $scope,
    $plaza,
    $path,
    $categoria
);

// URLs absolutas de detalle en admin
foreach ($result['leads'] as &$lead) {
    $lead['detalle_url'] = adm_href((string) ($lead['detalle_url'] ?? ''));
}
unset($lead);

echo json_encode([
    'success' => !empty($result['ok']),
    'data' => $result,
], JSON_UNESCAPED_UNICODE | (defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0));
