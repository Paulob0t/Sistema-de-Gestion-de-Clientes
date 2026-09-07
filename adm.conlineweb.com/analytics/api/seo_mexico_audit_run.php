<?php
/**
 * POST: auditoría multi-fuente SEO México → findings → checklist dinámico
 * → encola correcciones auto-fixables como propuestas (sin escribir aún).
 */
require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_config.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_permissions.php';
require_once dirname(__DIR__, 2) . '/includes/cw_seo_mexico_audit.php';
require_once dirname(__DIR__, 2) . '/includes/cw_seo_mexico_ai.php';

header('Content-Type: application/json; charset=utf-8');
@set_time_limit(200);
@ini_set('display_errors', '0');

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

$uid = (int) ($_SESSION['uid'] ?? 0);

try {
    $result = cw_seo_mexico_audit_run($conn, $uid, 'mexico_priority', [
        'source' => 'manual',
        'time_budget' => 150,
        'multisource_deep' => false,
        // Nunca aplicar correcciones desde el botón: van a cola de propuestas
        'defer_autofix' => true,
        // También genera tareas/recomendaciones SEO/GEO externas (GSC, GBP…)
        'external_ai' => true,
    ]);

    if (!is_array($result)) {
        $result = ['ok' => false, 'error' => 'Respuesta de auditoría inválida'];
    }

    // Encolar hallazgos auto-corregibles + tareas SEO/GEO externas (aprobar/rechazar)
    if (!empty($result['ok'])) {
        $queued = cw_seo_mexico_ai_enqueue_open_audit_fixes($conn, $uid, 40);
        $extQueued = cw_seo_mexico_ai_enqueue_open_external_tasks($conn, $uid, 20);
        $result['proposals_queued'] = (int) ($queued['queued'] ?? 0);
        $result['proposals_skipped'] = (int) ($queued['skipped'] ?? 0);
        $result['proposals_failed'] = (int) ($queued['failed'] ?? 0);
        $result['external_queued'] = (int) ($extQueued['queued'] ?? 0);
        $result['external_skipped'] = (int) ($extQueued['skipped'] ?? 0);
        $result['external_failed'] = (int) ($extQueued['failed'] ?? 0);
        $result['proposals_message'] = trim(
            (string) ($queued['message'] ?? '') . ' ' . (string) ($extQueued['message'] ?? '')
        );
        $result['proposals_ids'] = array_values(array_unique(array_merge(
            is_array($queued['proposal_ids'] ?? null) ? $queued['proposal_ids'] : [],
            is_array($extQueued['proposal_ids'] ?? null) ? $extQueued['proposal_ids'] : []
        )));
        $extSummary = is_array($result['summary']['external_seo_geo'] ?? null)
            ? $result['summary']['external_seo_geo']
            : [];
        $result['external_findings'] = (int) ($extSummary['findings'] ?? 0);
        $result['external_message'] = (string) ($extSummary['message'] ?? '');
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Error al auditar: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
