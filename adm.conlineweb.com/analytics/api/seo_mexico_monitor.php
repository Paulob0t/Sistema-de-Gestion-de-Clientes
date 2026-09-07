<?php
/**
 * API Monitor correcciones / módulos IA.
 * POST JSON actions:
 *  list|stats|set_working|get_proposal|add_clarification|update_proposal|
 *  refine_proposal_chat|
 *  propose_hub|propose_blog|propose_blog_improve|autonomy_run|sync_directory|apply|reject
 */
require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_config.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_permissions.php';
require_once dirname(__DIR__, 2) . '/includes/cw_seo_mexico_monitor.php';

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
$action = preg_replace('/[^a-z_]/', '', strtolower((string) ($data['action'] ?? 'list'))) ?? 'list';

if ($action === 'list' || $action === 'stats') {
    $opts = [
        'status' => (string) ($data['status'] ?? 'all'),
        'source' => (string) ($data['source'] ?? 'all'),
        'content' => (string) ($data['content'] ?? 'all'),
        'limit' => (int) ($data['limit'] ?? 80),
    ];
    $items = cw_seo_mexico_monitor_feed($conn, $opts);
    $all = cw_seo_mexico_monitor_feed($conn, [
        'status' => 'all',
        'source' => (string) ($data['source'] ?? 'all'),
        'content' => 'all',
        'limit' => (int) ($data['limit'] ?? 100),
    ]);
    echo json_encode([
        'ok' => true,
        'items' => $action === 'stats' ? [] : $items,
        'stats' => cw_seo_mexico_monitor_stats($all),
        'generated_at' => date('c'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'set_working') {
    $id = (int) ($data['proposal_id'] ?? $data['id'] ?? 0);
    echo json_encode(cw_seo_mexico_monitor_set_ai_working($conn, $id, $uid), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'get_proposal') {
    $id = (int) ($data['proposal_id'] ?? $data['id'] ?? 0);
    echo json_encode(cw_seo_mexico_monitor_get_proposal($conn, $id), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'add_clarification') {
    require_once dirname(__DIR__, 2) . '/includes/cw_seo_mexico_ai.php';
    $id = (int) ($data['proposal_id'] ?? $data['id'] ?? 0);
    $note = (string) ($data['note'] ?? $data['clarification'] ?? '');
    $result = cw_seo_mexico_ai_add_proposal_clarification($conn, $id, $note, $uid);
    if (!empty($result['ok'])) {
        $detail = cw_seo_mexico_monitor_get_proposal($conn, $id);
        $result['proposal'] = $detail['proposal'] ?? null;
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'update_proposal') {
    require_once dirname(__DIR__, 2) . '/includes/cw_seo_mexico_ai.php';
    $id = (int) ($data['proposal_id'] ?? $data['id'] ?? 0);
    $patch = [
        'title' => $data['title'] ?? null,
        'target_url' => $data['target_url'] ?? null,
        'after' => is_array($data['after'] ?? null) ? $data['after'] : [],
    ];
    // Permitir campos sueltos en el root del POST
    foreach ([
        'rationale', 'summary', 'title', 'description', 'h1', 'hero_subtitle',
        'excerpt', 'keyword', 'html', 'slug', 'path', 'search', 'replace',
        'change_type', 'correction', 'preview_html', 'patches',
    ] as $k) {
        if (array_key_exists($k, $data) && $k !== 'title') {
            $patch['after'][$k] = $data[$k];
        } elseif ($k === 'title' && array_key_exists('after_title', $data)) {
            $patch['after']['title'] = $data['after_title'];
        }
    }
    if (isset($data['title']) && is_string($data['title'])) {
        $patch['title'] = $data['title'];
        if (($patch['after']['title'] ?? '') === '') {
            $patch['after']['title'] = $data['title'];
        }
    }
    $result = cw_seo_mexico_ai_update_proposal_draft($conn, $id, $patch, $uid);
    if (!empty($result['ok'])) {
        $detail = cw_seo_mexico_monitor_get_proposal($conn, $id);
        $result['proposal'] = $detail['proposal'] ?? null;
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'refine_proposal_chat') {
    require_once dirname(__DIR__, 2) . '/includes/cw_seo_mexico_ai.php';
    @set_time_limit(120);
    $id = (int) ($data['proposal_id'] ?? $data['id'] ?? 0);
    $message = (string) ($data['message'] ?? $data['note'] ?? $data['text'] ?? '');
    $result = cw_seo_mexico_ai_refine_proposal_chat($conn, $id, $message, $uid);
    if (!empty($result['ok'])) {
        $detail = cw_seo_mexico_monitor_get_proposal($conn, $id);
        $result['proposal'] = $detail['proposal'] ?? null;
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'propose_hub') {
    $slug = (string) ($data['city'] ?? $data['slug'] ?? '');
    $result = cw_seo_mexico_ai_propose_hub_text($conn, $slug, $uid);
    if (!empty($result['ok']) && !empty($result['proposal_id'])) {
        $detail = cw_seo_mexico_monitor_get_proposal($conn, (int) $result['proposal_id']);
        $result['proposal'] = $detail['proposal'] ?? null;
        $result['content_type'] = 'page';
        $result['message'] = 'Propuesta de página lista. Revísala antes de implementar.';
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'propose_blog') {
    $topic = (string) ($data['topic'] ?? '');
    $cat = (string) ($data['category'] ?? 'seo');
    $result = cw_seo_mexico_ai_propose_blog($conn, $topic, $cat, $uid);
    if (!empty($result['ok']) && !empty($result['proposal_id'])) {
        $detail = cw_seo_mexico_monitor_get_proposal($conn, (int) $result['proposal_id']);
        $result['proposal'] = $detail['proposal'] ?? null;
        $result['content_type'] = 'blog';
        $result['message'] = 'Propuesta de blog nuevo lista. Revísala antes de implementar.';
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'propose_blog_improve') {
    $slug = (string) ($data['slug'] ?? '');
    $result = cw_seo_mexico_ai_propose_blog_improve($conn, $slug, $uid);
    if (!empty($result['ok']) && !empty($result['proposal_id'])) {
        $detail = cw_seo_mexico_monitor_get_proposal($conn, (int) $result['proposal_id']);
        $result['proposal'] = $detail['proposal'] ?? null;
        $result['content_type'] = 'blog';
        $result['message'] = 'Propuesta de mejora lista. Revísala antes de implementar.';
    }
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

if ($action === 'brain_status' || $action === 'brain_seed') {
    require_once dirname(__DIR__, 2) . '/includes/cw_site_ai_brain.php';
    if ($action === 'brain_seed') {
        $seed = cw_site_ai_brain_seed($conn, $uid);
        $status = cw_site_ai_brain_status($conn);
        $seeded = (int) ($seed['seeded'] ?? 0);
        $facts = (int) ($status['facts'] ?? 0);
        $msg = !empty($seed['ok'])
            ? ('Cerebro actualizado: ' . $seeded . ' hechos · '
                . (string) ($seed['message'] ?? '')
                . ' · hechos activos: ' . $facts . '.')
            : (string) ($seed['error'] ?? $seed['message'] ?? 'No se pudo actualizar el conocimiento base');
        echo json_encode(array_merge($status, $seed, [
            'ok' => !empty($seed['ok']),
            'message' => $msg,
            'action' => 'brain_seed',
        ]), JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(cw_site_ai_brain_status($conn), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'propose_maintain') {
    require_once dirname(__DIR__, 2) . '/includes/cw_site_ai_maintain.php';
    @set_time_limit(180);
    $instruction = (string) ($data['instruction'] ?? $data['topic'] ?? '');
    $path = (string) ($data['path'] ?? '');
    $result = cw_site_ai_maintain_propose_patch($conn, $instruction, $path, $uid);
    if (!empty($result['ok']) && !empty($result['proposal_id'])) {
        $detail = cw_seo_mexico_monitor_get_proposal($conn, (int) $result['proposal_id']);
        $result['proposal'] = $detail['proposal'] ?? null;
        $result['content_type'] = 'fix';
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'propose_design') {
    require_once dirname(__DIR__, 2) . '/includes/cw_site_ai_design.php';
    @set_time_limit(240);
    $instruction = (string) ($data['instruction'] ?? $data['topic'] ?? '');
    $path = (string) ($data['path'] ?? '');
    $targetUrl = (string) ($data['target_url'] ?? $data['url'] ?? '');
    $result = cw_site_ai_design_propose($conn, $instruction, $targetUrl, $path, $uid);
    if (!empty($result['ok']) && !empty($result['proposal_id'])) {
        $detail = cw_seo_mexico_monitor_get_proposal($conn, (int) $result['proposal_id']);
        $result['proposal'] = $detail['proposal'] ?? null;
        $result['content_type'] = 'design';
        $result['preview_url'] = '../seo_mexico_design_preview.php?id=' . (int) $result['proposal_id'];
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'propose_admin_view' || $action === 'propose_admin_views') {
    require_once dirname(__DIR__, 2) . '/includes/cw_site_ai_admin.php';
    @set_time_limit(360);
    $instruction = (string) ($data['instruction'] ?? $data['topic'] ?? '');
    if ($action === 'propose_admin_views') {
        $views = $data['views'] ?? $data['view_keys'] ?? [];
        if (!is_array($views)) {
            $views = [];
        }
        $max = max(1, min(4, (int) ($data['max'] ?? 3)));
        $result = cw_site_ai_admin_propose_views_batch($conn, $views, $instruction, $uid, $max);
        if (!empty($result['created'][0]['proposal_id'])) {
            $detail = cw_seo_mexico_monitor_get_proposal($conn, (int) $result['created'][0]['proposal_id']);
            $result['proposal'] = $detail['proposal'] ?? null;
        }
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
    $viewKey = (string) ($data['view'] ?? $data['view_key'] ?? 'monitor');
    $result = cw_site_ai_admin_propose_view($conn, $viewKey, $instruction, $uid);
    if (!empty($result['ok']) && !empty($result['proposal_id'])) {
        $detail = cw_seo_mexico_monitor_get_proposal($conn, (int) $result['proposal_id']);
        $result['proposal'] = $detail['proposal'] ?? null;
        $result['content_type'] = 'fix';
        $result['portal'] = 'admin';
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'admin_views_catalog') {
    require_once dirname(__DIR__, 2) . '/includes/cw_site_ai_admin.php';
    $catalog = [];
    foreach (cw_site_ai_admin_views_catalog() as $key => $v) {
        $catalog[] = [
            'key' => $key,
            'label' => (string) ($v['label'] ?? $key),
            'purpose' => (string) ($v['purpose'] ?? ''),
        ];
    }
    echo json_encode(['ok' => true, 'views' => $catalog], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'propose_cliente_view' || $action === 'propose_cliente_views') {
    require_once dirname(__DIR__, 2) . '/includes/cw_site_ai_cliente.php';
    @set_time_limit(360);
    $instruction = (string) ($data['instruction'] ?? $data['topic'] ?? '');
    if ($action === 'propose_cliente_views') {
        $views = $data['views'] ?? $data['view_keys'] ?? [];
        if (!is_array($views)) {
            $views = [];
        }
        $max = max(1, min(4, (int) ($data['max'] ?? 3)));
        $result = cw_site_ai_cliente_propose_views_batch($conn, $views, $instruction, $uid, $max);
        if (!empty($result['created'][0]['proposal_id'])) {
            $detail = cw_seo_mexico_monitor_get_proposal($conn, (int) $result['created'][0]['proposal_id']);
            $result['proposal'] = $detail['proposal'] ?? null;
        }
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
    $viewKey = (string) ($data['view'] ?? $data['view_key'] ?? 'shell');
    $result = cw_site_ai_cliente_propose_view($conn, $viewKey, $instruction, $uid);
    if (!empty($result['ok']) && !empty($result['proposal_id'])) {
        $detail = cw_seo_mexico_monitor_get_proposal($conn, (int) $result['proposal_id']);
        $result['proposal'] = $detail['proposal'] ?? null;
        $result['content_type'] = 'fix';
        $result['portal'] = 'cliente';
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'autonomy_run' || $action === 'think_and_propose') {
    require_once dirname(__DIR__, 2) . '/includes/cw_seo_mexico_ai_autonomy.php';
    @set_time_limit(420);
    $opts = [
        'max_blogs' => max(0, min(4, (int) ($data['max_blogs'] ?? 2))),
        'max_hubs' => max(0, min(3, (int) ($data['max_hubs'] ?? 1))),
        'max_improves' => max(0, min(3, (int) ($data['max_improves'] ?? 1))),
        'max_pending' => max(1, min(40, (int) ($data['max_pending'] ?? 12))),
        'max_actions' => max(1, min(6, (int) ($data['max_actions'] ?? 4))),
    ];
    $result = cw_seo_mexico_ai_autonomy_run($conn, $uid, $opts);
    $result['content_type'] = 'mixed';
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'directory_rules' || $action === 'sync_directory') {
    require_once dirname(__DIR__, 2) . '/includes/cw_seo_mexico_directory_sync.php';
    if ($action === 'directory_rules') {
        echo json_encode([
            'ok' => true,
            'indice_url' => 'https://conlineweb.com/indice/',
            'placement' => cw_seo_mexico_directory_placement_pack(),
            'ai_context' => cw_seo_mexico_directory_ai_context(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // Sync forzado: sitemaps (México + blog + index) + llms.txt/json
    @set_time_limit(180);
    $discovery = cw_seo_mexico_directory_sync_discovery(true);
    $ok = !empty($discovery['ok']);
    $files = is_array($discovery['files'] ?? null) ? $discovery['files'] : [];
    $baseMsg = (string) ($discovery['message'] ?? ($ok ? 'Sync listo' : 'Sync falló'));
    if ($ok) {
        $msg = 'Sitemaps e índice actualizados con éxito. '
            . $baseMsg
            . ($files !== [] ? ' · Archivos: ' . implode(', ', $files) : '')
            . ' · Ver https://conlineweb.com/indice/';
    } else {
        $msg = 'No se pudo completar la actualización de sitemaps/índice. ' . $baseMsg
            . ' Revisa que el sitio (conlineweb.com) esté accesible desde el admin y con permisos de escritura.';
    }
    echo json_encode([
        'ok' => $ok,
        'indice_url' => 'https://conlineweb.com/indice/',
        'discovery' => $discovery,
        'files' => $files,
        'message' => $msg,
        'action' => 'sync_directory',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'apply') {
    $id = (int) ($data['proposal_id'] ?? $data['id'] ?? 0);
    $previewed = !empty($data['previewed']);
    if (!$previewed) {
        echo json_encode([
            'ok' => false,
            'error' => 'Debes abrir la vista previa de la propuesta antes de implementar.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
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
