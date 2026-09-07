<?php
/**
 * API IA SEO México.
 * POST JSON:
 *  { "action": "status"|"propose_hub"|"propose_blog"|"apply"|"list", ... }
 */
require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_config.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_permissions.php';
require_once dirname(__DIR__, 2) . '/includes/cw_seo_mexico_ai.php';

header('Content-Type: application/json; charset=utf-8');
@set_time_limit(120);

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

if ($action === 'status') {
    $st = cw_seo_mexico_ai_status();
    echo json_encode([
        'ok' => true,
        'ai_available' => !empty($st['active']),
        'ai_configured' => !empty($st['configured']),
        'model' => (string) ($st['model'] ?? cw_seo_mexico_ai_model()),
        'pending' => count(cw_seo_mexico_ai_list_proposals($conn, 'pending', 50)),
        'site_root' => cw_seo_mexico_site_root_path(),
        'status' => $st,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'validate') {
    $result = cw_seo_mexico_ai_validate_connection();
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'list') {
    $status = (string) ($data['status'] ?? 'pending');
    echo json_encode([
        'ok' => true,
        'items' => cw_seo_mexico_ai_list_proposals($conn, $status, 40),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'propose_hub') {
    $slug = (string) ($data['city'] ?? $data['slug'] ?? '');
    $result = cw_seo_mexico_ai_propose_hub_text($conn, $slug, $uid);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'propose_blog') {
    $topic = (string) ($data['topic'] ?? '');
    $cat = (string) ($data['category'] ?? 'seo');
    $result = cw_seo_mexico_ai_propose_blog($conn, $topic, $cat, $uid);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'propose_blog_improve') {
    $slug = (string) ($data['slug'] ?? '');
    $result = cw_seo_mexico_ai_propose_blog_improve($conn, $slug, $uid);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'blog_catalog') {
    echo json_encode([
        'ok' => true,
        'categories' => array_values(cw_seo_mexico_ai_blog_categories()),
        'posts' => cw_seo_mexico_ai_blog_existing_posts(50),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'propose_audit_fix') {
    $findingId = (int) ($data['finding_id'] ?? $data['id'] ?? 0);
    $result = cw_seo_mexico_ai_propose_audit_fix($conn, $findingId, $uid);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'propose_audit_fixes') {
    $limit = isset($data['limit']) ? (int) $data['limit'] : 40;
    $result = cw_seo_mexico_ai_enqueue_open_audit_fixes($conn, $uid, $limit);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'apply') {
    $id = (int) ($data['proposal_id'] ?? $data['id'] ?? 0);
    $result = cw_seo_mexico_ai_apply_proposal($conn, $id, $uid);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'reject') {
    $id = (int) ($data['proposal_id'] ?? $data['id'] ?? 0);
    $result = cw_seo_mexico_ai_reject_proposal($conn, $id);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Acción inválida'], JSON_UNESCAPED_UNICODE);
