<?php
/**
 * API alertas IA (campanita).
 * POST/GET JSON: list|unread_count|mark_read|mark_all
 */
require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_config.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_permissions.php';
require_once dirname(__DIR__, 2) . '/includes/cw_site_ai_alerts.php';
require_once dirname(__DIR__, 2) . '/includes/adm_paths.php';

header('Content-Type: application/json; charset=utf-8');

cw_hub_migrate($conn);
// Campanita visible a usuarios autenticados con analytics o CRM
if (!cw_hub_can('hub.analytics.view') && !cw_hub_can('hub.crm.view')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sin permiso'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
if (!is_array($data)) {
    $data = $_POST ?: $_GET;
}
if (!is_array($data)) {
    $data = [];
}

$action = preg_replace('/[^a-z_]/', '', strtolower((string) ($data['action'] ?? 'list'))) ?? 'list';

if ($action === 'list' || $action === 'unread_count') {
    $unreadOnly = !empty($data['unread_only']);
    $items = cw_site_ai_alerts_list($conn, (int) ($data['limit'] ?? 25), $unreadOnly);
    $mapped = [];
    foreach ($items as $it) {
        $mapped[] = [
            'id' => (int) ($it['id'] ?? 0),
            'key' => 'ai-' . (int) ($it['id'] ?? 0),
            'proposal_id' => (int) ($it['proposal_id'] ?? 0),
            'kind' => (string) ($it['kind'] ?? ''),
            'kind_label' => cw_site_ai_alerts_kind_label((string) ($it['kind'] ?? '')),
            'title' => (string) ($it['title'] ?? ''),
            'text' => (string) ($it['body'] ?? ''),
            'url' => (string) ($it['url'] ?? adm_href('analytics/seo_mexico_monitor.php')),
            'read' => (int) ($it['is_read'] ?? 0) === 1,
            'time' => (string) ($it['created_at'] ?? ''),
            'source' => 'seo_ai',
        ];
    }
    echo json_encode([
        'ok' => true,
        'unread' => cw_site_ai_alerts_unread_count($conn),
        'items' => $action === 'unread_count' ? [] : $mapped,
        'monitor_url' => adm_href('analytics/seo_mexico_monitor.php'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'mark_read') {
    echo json_encode(cw_site_ai_alerts_mark_read($conn, (int) ($data['id'] ?? 0), false), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'mark_all') {
    echo json_encode(cw_site_ai_alerts_mark_read($conn, 0, true), JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Acción inválida'], JSON_UNESCAPED_UNICODE);
