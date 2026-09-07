<?php
/**
 * AutoFix API (mismo servidor).
 * POST JSON:
 *  { "action": "fix_one"|"fix_open"|"preview_open"|"write"|"replace"|"city_stub"|"list", ... }
 */
require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_config.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_permissions.php';
require_once dirname(__DIR__, 2) . '/includes/cw_seo_mexico_autofix.php';
require_once dirname(__DIR__, 2) . '/includes/cw_seo_mexico_audit.php';

header('Content-Type: application/json; charset=utf-8');
@set_time_limit(180);

cw_hub_migrate($conn);
if (!cw_hub_can('hub.analytics.view')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sin permiso'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : $_POST;
if (!is_array($data)) {
    $data = [];
}

$uid = (int) ($_SESSION['uid'] ?? 0);
$action = preg_replace('/[^a-z_]/', '', strtolower((string) ($data['action'] ?? ''))) ?? '';

if ($action === 'fix_one') {
    $findingId = (int) ($data['finding_id'] ?? $data['id'] ?? 0);
    $result = cw_seo_mexico_autofix_fix_one_by_id($conn, $findingId, $uid);
    // Misma forma de items[] que el lote, para reutilizar el reporte en UI
    if (!empty($result['item'])) {
        $result['items'] = [$result['item']];
        $result['applied'] = !empty($result['applied']) ? 1 : 0;
        $result['failed'] = empty($result['ok']) || (!empty($result['ok']) && empty($result['applied']) && empty($result['item']['ok'])) ? 1 : 0;
        $result['skipped'] = !empty($result['ok']) && empty($result['applied']) ? 1 : 0;
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'fix_open') {
    $limit = isset($data['limit']) ? (int) $data['limit'] : 25;
    $result = cw_seo_mexico_autofix_fix_open_with_report($conn, $uid, $limit);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'preview_open') {
    $open = cw_seo_mexico_autofix_open_fixable($conn, 40);
    $preview = [];
    foreach ($open as $f) {
        $url = (string) ($f['url'] ?? '');
        $snap = cw_seo_mexico_autofix_snapshot_url($url);
        $preview[] = [
            'finding_id' => (int) ($f['id'] ?? 0),
            'finding_key' => (string) ($f['finding_key'] ?? ''),
            'title' => (string) ($f['title'] ?? ''),
            'check_type' => (string) ($f['check_type'] ?? ''),
            'url' => $url,
            'severity' => (string) ($f['severity'] ?? ''),
            'correction' => (string) ($f['correction'] ?? ''),
            'before' => $snap,
        ];
    }
    echo json_encode([
        'ok' => true,
        'count' => count($preview),
        'root' => cw_seo_mexico_autofix_site_root(),
        'items' => $preview,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = ['ok' => false, 'error' => 'Acción inválida'];

if ($action === 'write') {
    $rel = (string) ($data['archivo'] ?? $data['rel_path'] ?? '');
    $content = (string) ($data['contenido'] ?? $data['content'] ?? '');
    $reason = (string) ($data['reason'] ?? 'API write');
    $result = cw_seo_mexico_autofix_write($rel, $content, $reason);
    cw_seo_mexico_autofix_log(
        $conn,
        'api_write',
        $rel,
        !empty($result['ok']) && !empty($result['applied']),
        (string) ($result['error'] ?? $reason),
        $result['backup'] ?? null,
        $uid,
        null
    );
} elseif ($action === 'replace') {
    $rel = (string) ($data['archivo'] ?? $data['rel_path'] ?? '');
    $search = (string) ($data['buscar'] ?? $data['search'] ?? '');
    $replace = (string) ($data['reemplazar'] ?? $data['replace'] ?? '');
    $reason = (string) ($data['reason'] ?? 'API replace');
    $result = cw_seo_mexico_autofix_replace($rel, $search, $replace, $reason);
    cw_seo_mexico_autofix_log(
        $conn,
        'api_replace',
        $rel,
        !empty($result['ok']) && !empty($result['applied']),
        (string) ($result['error'] ?? $reason),
        $result['backup'] ?? null,
        $uid,
        null
    );
} elseif ($action === 'city_stub') {
    $city = (string) ($data['city'] ?? '');
    $svc = (string) ($data['service'] ?? '');
    $result = cw_seo_mexico_autofix_city_stub($city, $svc);
    cw_seo_mexico_autofix_log(
        $conn,
        'api_city_stub',
        (string) ($result['path'] ?? ''),
        !empty($result['ok']) && !empty($result['applied']),
        (string) ($result['error'] ?? 'stub'),
        $result['backup'] ?? null,
        $uid,
        null
    );
} elseif ($action === 'list') {
    echo json_encode([
        'ok' => true,
        'root' => cw_seo_mexico_autofix_site_root(),
        'whitelist' => cw_seo_mexico_autofix_whitelist(),
        'recent' => cw_seo_mexico_autofix_recent($conn, 30),
        'open_fixable' => count(cw_seo_mexico_autofix_open_fixable($conn, 80)),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
