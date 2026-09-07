<?php
require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_config.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_permissions.php';
require_once dirname(__DIR__, 2) . '/includes/cw_seo_mexico_checklist.php';

header('Content-Type: application/json; charset=utf-8');

cw_hub_migrate($conn);
if (!cw_hub_can('hub.analytics.view')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sin permiso']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$raw = file_get_contents('php://input');
$data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : $_POST;
if (!is_array($data)) {
    $data = [];
}

$taskKey = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($data['task_key'] ?? ''))) ?? '';
$done = filter_var($data['done'] ?? false, FILTER_VALIDATE_BOOLEAN);
$notes = array_key_exists('notes', $data) ? (string) $data['notes'] : null;
$uid = (int) ($_SESSION['uid'] ?? 0);

// La 1ª tarea solo se completa aplicando plazas + historial (no un check vacío)
if ($taskKey === 'plazas_prioridad') {
    if (!$done) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'Esta tarea ya está aplicada con plazas e historial; no se puede reabrir desde el check.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $apply = cw_seo_mexico_checklist_apply_plazas_prioridad($conn, $uid);
    $rows = cw_seo_mexico_checklist_load($conn);
    $stats = cw_seo_mexico_checklist_stats($rows);
    $item = cw_seo_mexico_checklist_task_detail($conn, $taskKey);
    echo json_encode([
        'ok' => !empty($apply['ok']) && !empty($apply['complete']),
        'error' => $apply['error'] ?? null,
        'applied' => !empty($apply['applied']),
        'item' => $item,
        'stats' => $stats,
        'reload' => true,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = cw_seo_mexico_checklist_set_done($conn, $taskKey, $done, $uid, $notes);
$rows = cw_seo_mexico_checklist_load($conn);
$stats = cw_seo_mexico_checklist_stats($rows);

echo json_encode([
    'ok' => !empty($result['ok']),
    'error' => $result['error'] ?? null,
    'item' => $result['item'] ?? null,
    'stats' => $stats,
], JSON_UNESCAPED_UNICODE);
