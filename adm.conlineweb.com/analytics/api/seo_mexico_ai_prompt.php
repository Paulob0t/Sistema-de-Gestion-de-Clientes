<?php
/**
 * API prompts interactivos IA.
 * POST: new_session|list_sessions|get_session|run|to_proposal|execute_request|presets|status
 */
require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_config.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_permissions.php';
require_once dirname(__DIR__, 2) . '/includes/cw_seo_mexico_ai_prompt.php';

header('Content-Type: application/json; charset=utf-8');
@set_time_limit(120);

cw_hub_migrate($conn);
// Disponible para cualquier admin autenticado (el cerebro flotante vive en todas las vistas)
$uid = (int) ($_SESSION['uid'] ?? 0);
if ($uid < 1) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sesión no válida'], JSON_UNESCAPED_UNICODE);
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

$action = preg_replace('/[^a-z_]/', '', strtolower((string) ($data['action'] ?? ''))) ?? '';

if ($action === 'status') {
    $st = cw_seo_mexico_ai_status();
    echo json_encode([
        'ok' => true,
        'ai_active' => !empty($st['active']),
        'status' => $st,
        'model' => cw_seo_mexico_ai_model(),
        'presets' => cw_seo_mexico_ai_prompt_presets(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'presets') {
    echo json_encode(['ok' => true, 'presets' => cw_seo_mexico_ai_prompt_presets()], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'new_session') {
    $result = cw_seo_mexico_ai_prompt_create_session(
        $conn,
        (string) ($data['mode'] ?? 'ask'),
        $uid,
        (string) ($data['title'] ?? '')
    );
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'list_sessions') {
    echo json_encode([
        'ok' => true,
        'sessions' => cw_seo_mexico_ai_prompt_list_sessions($conn, (int) ($data['limit'] ?? 30)),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'get_session') {
    $id = (int) ($data['session_id'] ?? $data['id'] ?? 0);
    $sess = cw_seo_mexico_ai_prompt_get_session($conn, $id);
    if ($sess === null) {
        echo json_encode(['ok' => false, 'error' => 'Sesión no encontrada'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode([
        'ok' => true,
        'session' => $sess,
        'messages' => cw_seo_mexico_ai_prompt_messages($conn, $id, 100),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'run') {
    $result = cw_seo_mexico_ai_prompt_run(
        $conn,
        (string) ($data['prompt'] ?? ''),
        (string) ($data['mode'] ?? 'ask'),
        (int) ($data['session_id'] ?? 0),
        $uid
    );
    if (!empty($result['ok']) && !empty($result['session_id'])) {
        $result['messages'] = cw_seo_mexico_ai_prompt_messages($conn, (int) $result['session_id'], 100);
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'to_proposal') {
    $id = (int) ($data['session_id'] ?? 0);
    $result = cw_seo_mexico_ai_prompt_to_proposal($conn, $id, $uid);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'execute_request') {
    @set_time_limit(180);
    $id = (int) ($data['session_id'] ?? 0);
    $result = cw_seo_mexico_ai_prompt_execute_request(
        $conn,
        $id,
        (string) ($data['instruction'] ?? ''),
        $uid
    );
    if (!empty($result['ok']) && $id > 0) {
        $result['messages'] = cw_seo_mexico_ai_prompt_messages($conn, $id, 100);
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Acción inválida'], JSON_UNESCAPED_UNICODE);
