<?php
/**
 * Módulo checklist SEO México.
 * $seoChecklistModule = 'code' (correcciones sitio/AutoFix) | 'external' (GSC, GBP, ops).
 * seo_mexico_external.php solo define el módulo y requiere este archivo.
 */
$seoAdmRoot = dirname(__DIR__);
require_once $seoAdmRoot . '/includes/seo_mexico_page_guard.php';
seo_mexico_page_guard_bootstrap($seoAdmRoot);

require_once $seoAdmRoot . '/auth_middleware.php';
require_once $seoAdmRoot . '/conn.php';
require_once $seoAdmRoot . '/includes/cw_hub_config.php';
require_once $seoAdmRoot . '/includes/cw_hub_migrate.php';
require_once $seoAdmRoot . '/includes/cw_hub_permissions.php';
require_once $seoAdmRoot . '/includes/cw_seo_mexico_checklist.php';
require_once $seoAdmRoot . '/includes/cw_seo_mexico_checklist_roadmap.php';
require_once $seoAdmRoot . '/includes/cw_seo_mexico_short_urls_strategy.php';
require_once $seoAdmRoot . '/includes/cw_seo_mexico_audit.php';
require_once $seoAdmRoot . '/includes/cw_seo_mexico_autofix.php';
require_once $seoAdmRoot . '/includes/cw_seo_mexico_ai.php';
require_once $seoAdmRoot . '/includes/cw_seo_mexico_ai_constitution.php';
require_once $seoAdmRoot . '/includes/cw_seo_mexico_kpis.php';
require_once $seoAdmRoot . '/includes/adm_paths.php';

cw_hub_migrate($conn);
cw_hub_require('hub.analytics.view');

$uid = (int) ($_SESSION['uid'] ?? 0);
$seoChecklistModule = cw_seo_mexico_checklist_normalize_execution($seoChecklistModule ?? 'code');
$isCodeModule = $seoChecklistModule === 'code';

$applyResult = ['complete' => false];
$applyHubs = ['complete' => false];
$applySvcGeo = ['complete' => false];
$applyBlogLink = ['complete' => false];
$applySitemap = ['complete' => false];
$applyCanibal = ['complete' => false];
$applyCwv = ['complete' => false];
$applyKpi = ['complete' => false];
$applyNap = ['complete' => false];

if ($isCodeModule) {
    $applyResult = cw_seo_mexico_checklist_apply_plazas_prioridad($conn, $uid);
    $applyHubs = cw_seo_mexico_checklist_apply_rewrite_hubs($conn, $uid);
    $applySvcGeo = cw_seo_mexico_checklist_apply_rewrite_servicio_geo($conn, $uid);
    $applyBlogLink = cw_seo_mexico_checklist_apply_blog_internlink($conn, $uid);
    $applySitemap = cw_seo_mexico_checklist_apply_sitemap_completo($conn, $uid);
    $applyCanibal = cw_seo_mexico_checklist_apply_canibalizacion($conn, $uid);
    $applyCwv = cw_seo_mexico_checklist_apply_cwv_mobile($conn, $uid);
    $applyKpi = cw_seo_mexico_checklist_apply_kpi_90d($conn, $uid);
    $applyNap = cw_seo_mexico_checklist_apply_marca_consistente($conn, $uid);
    // Cierra finding NAP colgado si el cableado en código ya es correcto
    if (function_exists('cw_seo_mexico_audit_heal_nap_wired')) {
        cw_seo_mexico_audit_heal_nap_wired($conn, $uid);
    }
    cw_seo_mexico_short_urls_sync_checklist($conn, $uid);
}

$shortUrlsStrategy = $isCodeModule ? cw_seo_mexico_short_urls_strategy_summary($conn) : ['phases' => []];
$shortUrlsModule = $isCodeModule ? cw_seo_mexico_short_urls_module_data($conn) : null;

$allRows = cw_seo_mexico_checklist_load($conn);
$rows = cw_seo_mexico_checklist_filter_by_execution($allRows, $seoChecklistModule);
$stats = cw_seo_mexico_checklist_stats($rows);
$statsOther = cw_seo_mexico_checklist_stats(
    cw_seo_mexico_checklist_filter_by_execution($allRows, $isCodeModule ? 'external' : 'code')
);
$pendingSchedule = cw_seo_mexico_checklist_pending_schedule($rows);
$scheduleNow = array_values(array_filter($pendingSchedule, static fn ($r) => !empty($r['plan']['can_start_now'])));
$scheduleLater = array_values(array_filter($pendingSchedule, static fn ($r) => empty($r['plan']['can_start_now'])));
$updates = cw_seo_mexico_checklist_updates($conn, null, 80);
$plazasUpdates = cw_seo_mexico_checklist_updates($conn, 'plazas_prioridad', 20);
$hubsUpdates = cw_seo_mexico_checklist_updates($conn, 'rewrite_hubs_plazas', 5);
$svcGeoUpdates = cw_seo_mexico_checklist_updates($conn, 'rewrite_servicio_geo', 5);
$plazasTask = cw_seo_mexico_checklist_task_detail($conn, 'plazas_prioridad') ?? [];
$plazasDetail = is_array($plazasTask['detail'] ?? null) ? $plazasTask['detail'] : [];
$plazas = is_array($plazasDetail['plazas'] ?? null)
    ? $plazasDetail['plazas']
    : cw_seo_mexico_priority_plazas();
$frozenUntil = (string) ($plazasDetail['frozen_until'] ?? date('Y-m-d', strtotime('+90 days')));
$plazasComplete = !empty($applyResult['complete']) && !empty($plazasTask['done']) && $plazasUpdates !== [];

$byPhase = [];
foreach ($rows as $row) {
    $phase = (string) $row['phase'];
    if (!isset($byPhase[$phase])) {
        $byPhase[$phase] = [
            'label' => (string) ($row['phase_label'] ?? $phase),
            'items' => [],
            'done' => 0,
            'total' => 0,
        ];
    }
    $byPhase[$phase]['items'][] = $row;
    $byPhase[$phase]['total']++;
    if (!empty($row['done'])) {
        $byPhase[$phase]['done']++;
    }
}

$titleByKey = [];
foreach ($rows as $r) {
    $titleByKey[(string) $r['task_key']] = (string) $r['title'];
}

/** @var array<string, array<string,mixed>> $taskDetailsForModal */
$taskDetailsForModal = [];
foreach ($rows as $item) {
    $key = (string) $item['task_key'];
    $detail = is_array($item['detail'] ?? null) ? $item['detail'] : [];
    $detailPages = [];
    if (is_array($detail['pages'] ?? null)) {
        $detailPages = $detail['pages'];
    } elseif (is_array($detail['urls'] ?? null)) {
        foreach ($detail['urls'] as $u) {
            $detailPages[] = ['city' => '', 'url' => (string) $u];
        }
    } elseif ($key === 'plazas_prioridad' && is_array($detail['plazas'] ?? null)) {
        foreach ($detail['plazas'] as $p) {
            $detailPages[] = [
                'city' => (string) ($p['city'] ?? ''),
                'url' => (string) ($p['url'] ?? ''),
                'estado' => (string) ($p['estado'] ?? ''),
                'motivo' => (string) ($p['motivo'] ?? ''),
            ];
        }
    }

    $taskUpdates = [];
    if ($key === 'plazas_prioridad') {
        $taskUpdates = $plazasUpdates;
    } elseif ($key === 'rewrite_hubs_plazas') {
        $taskUpdates = $hubsUpdates;
    } elseif ($key === 'rewrite_servicio_geo') {
        $taskUpdates = $svcGeoUpdates;
    } else {
        foreach ($updates as $u) {
            if (($u['task_key'] ?? '') === $key) {
                $taskUpdates[] = $u;
            }
        }
    }

    $hasDetail = trim((string) ($item['notes'] ?? '')) !== ''
        || trim((string) ($item['description'] ?? '')) !== ''
        || trim((string) ($item['correction'] ?? '')) !== ''
        || $detailPages !== []
        || $taskUpdates !== []
        || $detail !== [];

    $status = strtolower(trim((string) ($detail['status'] ?? '')));
    $pendingRaw = [];
    if (is_array($detail['plazas_prioritarias_pendientes'] ?? null)) {
        $pendingRaw = $detail['plazas_prioritarias_pendientes'];
    } elseif (is_array($detail['pending'] ?? null)) {
        $pendingRaw = $detail['pending'];
    }
    $pendingLabels = [
        'queretaro' => 'Querétaro',
        'puebla' => 'Puebla',
        'merida' => 'Mérida',
        'tijuana' => 'Tijuana',
        'cancun' => 'Cancún',
        'toluca' => 'Toluca',
        'cdmx' => 'Ciudad de México',
        'leon' => 'León',
        'monterrey' => 'Monterrey',
        'guadalajara' => 'Guadalajara',
        'aguascalientes' => 'Aguascalientes',
    ];
    $pendingList = [];
    foreach ($pendingRaw as $p) {
        $slug = strtolower(trim((string) $p));
        if ($slug === '') {
            continue;
        }
        $pendingList[] = $pendingLabels[$slug] ?? ucfirst($slug);
    }
    $inProgress = !$item['done'] && ($status === 'parcial' || $status === 'en_proceso' || $pendingList !== []);

    $gsc = is_array($detail['gsc'] ?? null) ? $detail['gsc'] : null;
    $gscPayload = null;
    if (is_array($gsc) && trim((string) ($gsc['submit_url'] ?? '')) !== '') {
        $gscPayload = [
            'submit_url' => (string) $gsc['submit_url'],
            'label' => (string) ($gsc['label'] ?? 'Google Search Console'),
            'instruction' => (string) ($gsc['instruction'] ?? ''),
            'included' => array_values(array_filter(array_map('strval', is_array($gsc['included'] ?? null) ? $gsc['included'] : []))),
        ];
    }

    $plan = is_array($item['plan'] ?? null) ? $item['plan'] : null;
    $planPayload = null;
    if (is_array($plan)) {
        $planPayload = [
            'can_start_now' => !empty($plan['can_start_now']),
            'start' => (string) ($plan['start'] ?? ''),
            'due' => (string) ($plan['due'] ?? ''),
            'start_fmt' => cw_seo_mexico_checklist_fmt_date($plan['start'] ?? null),
            'due_fmt' => cw_seo_mexico_checklist_fmt_date($plan['due'] ?? null),
            'effort' => (string) ($plan['effort'] ?? ''),
            'window' => (string) ($plan['window'] ?? ''),
            'note' => (string) ($plan['note'] ?? ''),
        ];
    }

    $taskDetailsForModal[$key] = [
        'title' => (string) ($item['title'] ?? $key),
        'description' => (string) ($item['description'] ?? ''),
        'correction' => trim((string) ($item['correction'] ?? '')),
        'done' => !empty($item['done']),
        'done_at' => $item['done_at'] ?? null,
        'notes' => trim((string) ($item['notes'] ?? '')),
        'pages' => $detailPages,
        'deploy_required' => is_array($detail['deploy_required'] ?? null) ? $detail['deploy_required'] : [],
        'frozen_until' => (string) ($detail['frozen_until'] ?? ''),
        'version' => (string) ($detail['version'] ?? ''),
        'file' => (string) ($detail['file'] ?? ''),
        'status' => $status,
        'in_progress' => $inProgress,
        'pending' => $pendingList,
        'plan' => $planPayload,
        'gsc' => $gscPayload,
        'updates' => array_map(static function ($u) {
            return [
                'summary' => (string) ($u['summary'] ?? ''),
                'detail' => (string) ($u['detail'] ?? ''),
                'created_at' => (string) ($u['created_at'] ?? ''),
            ];
        }, $taskUpdates),
        'has_detail' => $hasDetail || $inProgress || $gscPayload !== null || $planPayload !== null,
    ];
}

$toggleUrl = adm_href('analytics/api/seo_checklist_toggle.php');
$auditRunUrl = adm_href('analytics/api/seo_mexico_audit_run.php');
$auditLastRun = cw_seo_mexico_audit_last_run($conn);
$auditOpenFindings = cw_seo_mexico_audit_open_findings($conn, 25);
$auditHealthHist = cw_seo_mexico_audit_health_history($conn, 14);
$auditWorst = cw_seo_mexico_audit_worst_urls($conn, $auditLastRun ? (int) $auditLastRun['id'] : null, 8);
$auditHealthLast = $auditHealthHist !== [] ? $auditHealthHist[count($auditHealthHist) - 1] : null;
$cronSecret = defined('CW_HUB_CRON_SECRET') ? CW_HUB_CRON_SECRET : '';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = (string) ($_SERVER['HTTP_HOST'] ?? 'adm.conlineweb.com');
$cronHttpUrl = $scheme . '://' . $host . '/analytics/cron_seo_mexico_audit.php?token=' . rawurlencode($cronSecret);
$aiMission = cw_seo_mexico_ai_mission();
$autofixRecent = cw_seo_mexico_autofix_recent($conn, 12);
$autofixRoot = cw_seo_mexico_autofix_site_root();
// Hallazgos abiertos: código/sitio vs SEO/GEO externo
$openFindingsAll = cw_seo_mexico_audit_open_findings($conn, 60, $isCodeModule ? 'code' : 'external');
$autofixOpenFixable = [];
foreach ($openFindingsAll as $qf) {
    if (!empty($qf['auto_fixable']) && empty($qf['auto_applied'])) {
        $autofixOpenFixable[] = $qf;
    }
}
$autofixOpenCount = count($autofixOpenFixable);
$openFindingsCount = count($openFindingsAll);
$autofixByTask = [];
$findingsByTask = [];
foreach ($openFindingsAll as $qf) {
    $tk = (string) ($qf['task_key'] ?? '');
    if ($tk === '') {
        continue;
    }
    if (!isset($findingsByTask[$tk])) {
        $findingsByTask[$tk] = [];
    }
    $findingsByTask[$tk][] = $qf;
    if (!empty($qf['auto_fixable']) && empty($qf['auto_applied'])) {
        if (!isset($autofixByTask[$tk])) {
            $autofixByTask[$tk] = [];
        }
        $autofixByTask[$tk][] = $qf;
    }
}
$autofixApiUrl = adm_href('analytics/api/seo_mexico_autofix.php');
$aiApiUrl = adm_href('analytics/api/seo_mexico_ai.php');
$aiStatus = $isCodeModule ? cw_seo_mexico_ai_status() : [
    'active' => false,
    'configured' => false,
    'label' => 'IA no aplica',
    'detail' => 'Solo en módulo código/sitio',
    'model' => cw_seo_mexico_ai_model(),
    'key_source' => 'none',
    'key_suffix' => '',
    'key_format_ok' => false,
    'curl_ok' => function_exists('curl_init'),
    'host' => '',
    'production_host' => false,
    'mock' => false,
    'site_writable' => false,
];
$aiAvailable = $isCodeModule && !empty($aiStatus['active']);
$aiPendingProposals = [];
if ($isCodeModule) {
    require_once dirname(__DIR__) . '/includes/cw_seo_mexico_monitor.php';
    $aiPendingProposals = array_values(array_filter(
        cw_seo_mexico_ai_list_proposals_any($conn, 30, 'all'),
        static fn ($p) => in_array((string) ($p['status'] ?? ''), ['pending', 'working'], true)
    ));
}
$aiHubPlazas = $isCodeModule ? cw_seo_mexico_priority_plazas() : [];
$aiBlogCategories = $isCodeModule ? array_values(cw_seo_mexico_ai_blog_categories()) : [];
$aiBlogPosts = $isCodeModule ? cw_seo_mexico_ai_blog_existing_posts(30) : [];
$monitorUrl = adm_href('analytics/seo_mexico_monitor.php');
$activityFeedAll = cw_seo_mexico_checklist_activity_feed($conn, 50);
$moduleTaskKeys = [];
foreach ($rows as $r) {
    $moduleTaskKeys[(string) ($r['task_key'] ?? '')] = true;
}
$activityFeed = [];
foreach ($activityFeedAll as $ev) {
    $tk = (string) ($ev['task_key'] ?? '');
    if ($tk !== '' && isset($moduleTaskKeys[$tk])) {
        $activityFeed[] = $ev;
    }
}

// Enriquecer modales con historial unificado (updates + autofix) por tarea
$activityByTask = [];
foreach ($activityFeedAll as $ev) {
    $tk = (string) ($ev['task_key'] ?? '');
    if ($tk === '' || !isset($moduleTaskKeys[$tk])) {
        continue;
    }
    if (!isset($activityByTask[$tk])) {
        $activityByTask[$tk] = [];
    }
    if (count($activityByTask[$tk]) < 25) {
        $activityByTask[$tk][] = $ev;
    }
}
foreach ($taskDetailsForModal as $tk => &$modalRow) {
    $extra = $activityByTask[$tk] ?? [];
    $merged = [];
    foreach ($modalRow['updates'] as $u) {
        $merged[] = [
            'kind' => 'update',
            'summary' => (string) ($u['summary'] ?? ''),
            'detail' => (string) ($u['detail'] ?? ''),
            'created_at' => (string) ($u['created_at'] ?? ''),
            'ok' => true,
        ];
    }
    foreach ($extra as $ev) {
        if (($ev['kind'] ?? '') !== 'autofix') {
            continue;
        }
        $merged[] = [
            'kind' => 'autofix',
            'summary' => (string) ($ev['summary'] ?? 'AutoFix'),
            'detail' => (string) ($ev['detail'] ?? ''),
            'created_at' => (string) ($ev['created_at'] ?? ''),
            'ok' => !empty($ev['ok']),
        ];
    }
    usort($merged, static fn ($a, $b) => strcmp((string) $b['created_at'], (string) $a['created_at']));
    $modalRow['updates'] = array_slice($merged, 0, 30);
    $modalRow['updates_count'] = count($modalRow['updates']);
    $modalRow['last_update'] = $modalRow['updates'][0] ?? null;
    $modalRow['is_dynamic'] = strpos($tk, 'audit_') === 0 || !empty($modalRow['status']);
    $modalRow['has_autofix'] = false;
    foreach ($modalRow['updates'] as $u) {
        if (($u['kind'] ?? '') === 'autofix') {
            $modalRow['has_autofix'] = true;
            break;
        }
    }
    if ($modalRow['updates_count'] > 0) {
        $modalRow['has_detail'] = true;
    }
}
unset($modalRow);

// Conteos claros para la cabecera
$countPending = 0;
$countProgress = 0;
$countDone = (int) ($stats['done'] ?? 0);
$countDynamic = 0;
$countAutofixOk = 0;
foreach ($rows as $r) {
    $k = (string) ($r['task_key'] ?? '');
    $meta = $taskDetailsForModal[$k] ?? [];
    if (empty($r['done'])) {
        $countPending++;
        if (!empty($meta['in_progress'])) {
            $countProgress++;
        }
    }
    if (!empty($meta['is_dynamic']) || strpos($k, 'audit_') === 0) {
        $countDynamic++;
    }
    if (!empty($meta['has_autofix'])) {
        $countAutofixOk++;
    }
}
$countFindings = $openFindingsCount;
$autofixOkRecent = 0;
foreach ($autofixRecent as $fx) {
    if (!empty($fx['ok'])) {
        $autofixOkRecent++;
    }
}

$closedFindingsAll = cw_seo_mexico_audit_closed_findings($conn, 40);
// En módulo externo solo mostrar hallazgos ext_*; en código, excluirlos
if ($isCodeModule) {
    $closedFindingsAll = array_values(array_filter(
        $closedFindingsAll,
        static fn ($f) => !str_starts_with((string) ($f['check_type'] ?? ''), 'ext_')
            && !str_starts_with((string) ($f['check_type'] ?? ''), 'external_')
    ));
} else {
    $closedFindingsAll = array_values(array_filter(
        $closedFindingsAll,
        static fn ($f) => str_starts_with((string) ($f['check_type'] ?? ''), 'ext_')
            || str_starts_with((string) ($f['check_type'] ?? ''), 'external_')
    ));
}
$correctionHistory = cw_seo_mexico_checklist_correction_history(
    $conn,
    $titleByKey,
    $moduleTaskKeys,
    $closedFindingsAll,
    80
);
$correctionHistoryCount = count($correctionHistory);
$aiPendingCount = count($aiPendingProposals);
$tabActive = preg_replace('/[^a-z_]/', '', strtolower((string) ($_GET['tab'] ?? 'pending'))) ?? 'pending';
if (!in_array($tabActive, ['pending', 'proposals', 'short_urls', 'roadmap', 'history', 'tools'], true)) {
    $tabActive = 'pending';
}
$aiProposalKindLabel = static function (string $kindRaw): string {
    return match ($kindRaw) {
        'audit_fix' => 'Corrección auditoría',
        'hub_text' => 'Texto hub',
        'short_url_commercial' => 'URL corta comercial',
        'blog_post' => 'Blog nuevo',
        'blog_improve' => 'Mejora blog',
        'design_ui' => 'Diseño',
        'site_patch' => 'Parche sitio',
        default => $kindRaw !== '' ? $kindRaw : 'Propuesta',
    };
};
cw_seo_mexico_roadmap_enrich_rows($rows, $conn);
$roadmapGroups = cw_seo_mexico_roadmap_group(
    $rows,
    $conn,
    is_array($auditLastRun) ? $auditLastRun : null
);
$roadmapStats = cw_seo_mexico_roadmap_stats($roadmapGroups);
$workFeed = cw_seo_mexico_checklist_work_feed(
    $rows,
    $taskDetailsForModal,
    $isCodeModule ? $openFindingsAll : [],
    $closedFindingsAll,
    ['plazas_locked' => $plazasComplete]
);
// Vincular hallazgos auto-fixables con propuestas pendientes (aprobar / rechazar)
$auditFixProposalMap = $isCodeModule ? cw_seo_mexico_ai_pending_audit_fix_map($conn) : [];
foreach ($workFeed as &$wfItem) {
    $fid = (int) ($wfItem['finding_id'] ?? 0);
    if ($fid > 0 && isset($auditFixProposalMap[$fid])) {
        $wfItem['proposal_id'] = (int) $auditFixProposalMap[$fid];
        $wfItem['can_approve'] = true;
        $wfItem['can_autofix'] = false; // no aplicar directo: cola de propuestas
        $wfItem['work_status'] = 'working';
        $wfItem['work_label'] = 'En cola de propuestas';
        $wfItem['type_label'] = 'Propuesta · corrección';
    } elseif (!empty($wfItem['can_autofix'])) {
        $wfItem['can_enqueue'] = true;
    }
}
unset($wfItem);
cw_seo_mexico_roadmap_attach_to_work_feed($workFeed);
$workFeedStats = cw_seo_mexico_checklist_work_stats($workFeed);
$filterWorkStatus = preg_replace('/[^a-z_]/', '', strtolower((string) ($_GET['status'] ?? 'all'))) ?? 'all';
if (!in_array($filterWorkStatus, ['all', 'pending', 'working', 'done'], true)) {
    $filterWorkStatus = 'all';
}
$workFeedFiltered = $filterWorkStatus === 'all'
    ? $workFeed
    : array_values(array_filter(
        $workFeed,
        static fn ($it) => ($it['work_status'] ?? '') === $filterWorkStatus
    ));
if ($filterWorkStatus === 'all') {
    $workFeedFiltered = array_values(array_filter(
        $workFeedFiltered,
        static fn ($it) => ($it['roadmap_status'] ?? '') !== 'implemented'
    ));
}
$tabPendingCount = count(array_values(array_filter(
    $workFeed,
    static fn ($it) => ($it['roadmap_status'] ?? '') !== 'implemented'
        && in_array((string) ($it['work_status'] ?? 'pending'), ['pending', 'working'], true)
)));
$tabCounts = [
    'pending' => $tabPendingCount,
    'proposals' => $aiPendingCount,
    'short_urls' => (int) ($shortUrlsModule['pending_proposals'] ?? 0),
    'roadmap' => (int) ($roadmapStats['pending'] ?? 0),
    'history' => $correctionHistoryCount,
];
$roadmapBucketLabels = [
    'correction' => 'Corrección',
    'new' => 'Nueva',
    'future' => 'Futura',
    'implemented' => 'Implementado',
];
$roadmapTableRows = [];
foreach (['correction', 'new', 'future', 'implemented'] as $roadmapBucket) {
    foreach ($roadmapGroups[$roadmapBucket] ?? [] as $rm) {
        if (!is_array($rm)) {
            continue;
        }
        $roadmapTableRows[] = [
            'bucket' => $roadmapBucket,
            'bucket_label' => $roadmapBucketLabels[$roadmapBucket] ?? $roadmapBucket,
            'name' => (string) ($rm['name'] ?? ''),
            'category' => (string) ($rm['category'] ?? ''),
            'priority' => (string) ($rm['priority'] ?? ''),
            'impact_seo' => (string) ($rm['impact_seo'] ?? ''),
            'solution' => (string) ($rm['solution'] ?? ''),
            'current_state' => (string) ($rm['current_state'] ?? ''),
            'problem' => (string) ($rm['problem'] ?? ''),
            'task_key' => (string) ($rm['task_key'] ?? ''),
        ];
    }
}
$workFeedById = [];
foreach ($workFeed as $wf) {
    $workFeedById[(string) ($wf['id'] ?? '')] = $wf;
}
$systemDoneBits = [
    !empty($plazasComplete) ? 'Plazas' : null,
    !empty($applyHubs['complete']) ? 'Hubs' : null,
    !empty($applySvcGeo['complete']) ? 'Servicio×plaza' : null,
    !empty($applyBlogLink['complete']) ? 'Blog↔geo' : null,
    !empty($applySitemap['complete']) ? 'Sitemap' : null,
    !empty($applyCanibal['complete']) ? 'Canibalización' : null,
    !empty($applyCwv['complete']) ? 'CWV' : null,
    !empty($applyKpi['complete']) ? 'KPIs' : null,
    !empty($applyNap['complete']) ? 'NAP' : null,
];
$systemDoneBits = array_values(array_filter($systemDoneBits));
$systemWarnBits = array_values(array_filter([
    empty($applyHubs['complete']) && !empty($applyHubs['error']) ? 'Hubs' : null,
    empty($applySvcGeo['complete']) && !empty($applySvcGeo['error']) ? 'Servicio×plaza' : null,
    empty($applyBlogLink['complete']) && !empty($applyBlogLink['error']) ? 'Blog↔geo' : null,
    empty($applySitemap['complete']) && !empty($applySitemap['error']) ? 'Sitemap' : null,
    empty($applyCanibal['complete']) && !empty($applyCanibal['error']) ? 'Canibalización' : null,
    empty($applyCwv['complete']) && !empty($applyCwv['error']) ? 'CWV' : null,
    empty($applyNap['complete']) && !empty($applyNap['error']) ? 'NAP' : null,
]));

include dirname(__DIR__) . '/menu.php';
?>
<link href="<?= adm_href('vendor/datatables/dataTables.bootstrap4.min.css') ?>" rel="stylesheet">
<link href="<?= adm_href('css/admin-datatables.css') ?>?v=20250715" rel="stylesheet">
<link href="<?= adm_href('analytics/css/seo-module.css') ?>?v=20250722" rel="stylesheet">
<div class="adm-page-shell">
<div class="container-fluid px-0">
<?php
$websiteTab = $isCodeModule ? 'seo_checklist' : 'seo_external';
$websiteHeroTitle = $isCodeModule ? 'Auditoría y correcciones del sitio' : 'Tareas SEO/GEO externas';
$websiteHeroSub = $isCodeModule
    ? 'Detecta problemas en el sitio y encola correcciones + tareas SEO/GEO externas (GSC, GBP). Evidencia y aprobación humana.'
    : 'Tareas y recomendaciones del análisis: Search Console, Google Business/Maps, reseñas, citaciones, casos locales y medición.';
$websiteShowPeriod = false;
require dirname(__DIR__) . '/includes/website_module_shell.php';

$codeUrl = adm_href('analytics/seo_mexico_checklist.php');
$externalUrl = adm_href('analytics/seo_mexico_external.php');
$pendingHere = max(0, (int) $stats['total'] - (int) $stats['done']);
$pendingOther = max(0, (int) $statsOther['total'] - (int) $statsOther['done']);

$seoUxActive = $isCodeModule ? 'audit' : 'external';
require dirname(__DIR__) . '/includes/seo_module_nav.php';
?>

<!-- Separador de módulos -->
<nav class="seo-module-switch" aria-label="Tipo de tareas SEO">
  <a href="<?= htmlspecialchars($codeUrl) ?>" class="<?= $isCodeModule ? 'is-active' : '' ?>">
    <strong>Auditoría de código</strong>
    <span><?= $isCodeModule ? $pendingHere : $pendingOther ?> pendientes · correcciones técnicas</span>
  </a>
  <a href="<?= htmlspecialchars($externalUrl) ?>" class="<?= !$isCodeModule ? 'is-active' : '' ?>">
    <strong>Tareas externas</strong>
    <span><?= !$isCodeModule ? $pendingHere : $pendingOther ?> pendientes · GSC, GBP, ops</span>
  </a>
</nav>

<!-- 1. Guía (colapsada) -->
<details class="seo-guide-compact">
  <summary><?= $isCodeModule ? 'Guía rápida · Roadmap SEO/GEO' : 'Guía rápida · Tareas externas' ?></summary>
<section class="seo-guide" aria-label="Cómo usar este checklist">
  <?php if ($isCodeModule): ?>
  <h2>Roadmap SEO/GEO — plan de implementación</h2>
  <ol class="seo-guide-steps">
    <li><strong>1. Auditar</strong><span>Contrasta código + HTML vivo + sitemap + robots + NAP. Lo correcto se marca ✅ sin crear tarea.</span></li>
    <li><strong>2. Clasificar</strong><span>🟡 Corrección si existe pero falla · ❌ Nueva si no existe · 🔮 Mejora futura opcional.</span></li>
    <li><strong>3. Ejecutar</strong><span>Cola de pendientes abajo: prioridad, impacto SEO/GEO y acción recomendada.</span></li>
  </ol>
  <?php else: ?>
  <h2>¿De qué va este módulo?</h2>
  <ol class="seo-guide-steps">
    <li><strong>1. Análisis genera tareas</strong><span>Al pulsar «Buscar problemas» en Auditoría de código, el análisis también crea tareas/recomendaciones SEO·GEO externas (GSC, Maps, reseñas…).</span></li>
    <li><strong>2. Ejecutar ops</strong><span>Exportar GSC, completar GBP, pedir reseñas, citaciones, casos reales — no AutoFix de código.</span></li>
    <li><strong>3. Aprobar / marcar hecho</strong><span>En Monitor apruebas la propuesta externa; aquí marcas la tarea con evidencia cuando la completes.</span></li>
  </ol>
  <?php if ($openFindingsCount > 0): ?>
  <p class="seo-ux-lead" style="margin:.55rem 0 0">
    Hay <strong><?= (int) $openFindingsCount ?></strong> hallazgo(s) SEO/GEO externo(s) abiertos de la última auditoría.
    Revísalos en la cola o en <a href="<?= htmlspecialchars(adm_href('analytics/seo_mexico_monitor.php'), ENT_QUOTES, 'UTF-8') ?>">Monitor IA</a>.
  </p>
    <?php endif; ?>
    <div id="seoAiStatus" class="seo-ai-status" hidden></div>
    <?php endif; ?>
</section>
</details>

<!-- 2. Estado de un vistazo -->
<section class="seo-status" id="seoAuditPanel" aria-label="Estado general">
  <div class="seo-status-top">
    <div class="seo-status-progress">
      <div class="seo-check-pct" id="seoCheckPct"><?= (int) $stats['pct'] ?>%</div>
      <div>
        <div class="seo-check-bar" aria-hidden="true"><span id="seoCheckBar" style="width:<?= (int) $stats['pct'] ?>%"></span></div>
        <div class="seo-check-meta" id="seoCheckMeta">
          <?= (int) $stats['done'] ?> de <?= (int) $stats['total'] ?>
          <?= $isCodeModule ? 'correcciones de código/sitio' : 'tareas externas' ?> completadas
        </div>
      </div>
    </div>
    <?php if ($isCodeModule): ?>
    <div class="seo-status-actions">
      <?php
        $aiTone = (string) ($aiStatus['tone'] ?? (!empty($aiStatus['active']) ? 'on' : 'off'));
        $aiToneClass = $aiTone === 'on' ? 'is-on' : ($aiTone === 'warn' ? 'is-warn' : 'is-off');
      ?>
      <a href="#seoAiPanel" class="seo-ai-chip <?= $aiToneClass ?>" id="seoAiChip" data-tab-jump="tools"
        title="<?= htmlspecialchars((string) ($aiStatus['detail'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <span class="seo-ai-dot" aria-hidden="true"></span>
        <span id="seoAiChipLabel"><?= htmlspecialchars((string) ($aiStatus['label'] ?? 'IA'), ENT_QUOTES, 'UTF-8') ?></span>
      </a>
      <button type="button" class="btn btn-primary" id="seoAuditRunBtn"
        title="Analiza el sitio, detecta problemas y los encola como propuestas para aprobar o rechazar">
        <i class="fas fa-satellite-dish"></i> Buscar problemas ahora
      </button>
      <button type="button" class="btn btn-outline-secondary btn-sm" data-tab-jump="tools" title="Panel IA y auditoría">
        <i class="fas fa-robot"></i> IA y herramientas
      </button>
    </div>
    <?php else: ?>
    <a class="btn btn-outline-primary" href="<?= htmlspecialchars($codeUrl) ?>">
      <i class="fas fa-code"></i> Ir a auditoría de código
    </a>
    <?php endif; ?>
  </div>
  <div id="seoAuditStatus" class="seo-audit-status" hidden></div>
  <?php if ($isCodeModule): ?>
  <div id="seoFixReport" class="seo-fix-report" hidden>
    <div class="seo-fix-report__head">
      <h3>Reporte de corrección</h3>
      <p id="seoFixReportMeta">Antes → después · URL aplicada</p>
    </div>
    <div id="seoFixReportList" class="seo-fix-report__list"></div>
  </div>

  <?php endif; ?>

  <div class="seo-status-cols">
    <article class="seo-status-card is-pending">
      <h3>Pendientes</h3>
      <p class="seo-status-num"><?= (int) ($workFeedStats['pending'] ?? 0) ?></p>
      <ul>
        <li><?= (int) ($workFeedStats['working'] ?? 0) ?> en revisión</li>
        <?php if ($isCodeModule): ?>
        <li><?= (int) $openFindingsCount ?> hallazgos abiertos</li>
        <li>
          <button type="button" class="seo-status-link" data-tab-jump="proposals" style="margin:0">
            <?= (int) $aiPendingCount ?> propuestas IA
          </button>
        </li>
        <?php endif; ?>
      </ul>
      <button type="button" class="seo-status-link" data-tab-jump="pending" data-filter-jump="pending">Ver cola</button>
    </article>
    <article class="seo-status-card is-done">
      <h3><?= $isCodeModule ? 'Completadas' : 'Hechas (externo)' ?></h3>
      <p class="seo-status-num"><?= (int) $countDone ?></p>
      <ul>
        <?php if ($isCodeModule): ?>
        <li><?= (int) $correctionHistoryCount ?> terminadas en historial</li>
        <li><?= (int) $autofixOkRecent ?> AutoFix recientes OK</li>
        <?php else: ?>
        <li><?= (int) $countProgress ?> en progreso</li>
        <li><?= (int) $pendingOther ?> pendientes en código</li>
        <?php endif; ?>
      </ul>
      <button type="button" class="seo-status-link" data-tab-jump="history">Ver historial</button>
    </article>
    <?php if ($isCodeModule): ?>
    <article class="seo-status-card is-run">
      <h3>Última ejecución</h3>
      <?php if (is_array($auditLastRun)): ?>
      <p class="seo-status-num"><?= (int) ($auditHealthLast['score_avg'] ?? 0) ?><small>/100</small></p>
      <ul>
        <li>Corrida #<?= (int) $auditLastRun['id'] ?> · <?= htmlspecialchars((string) ($auditLastRun['summary']['source'] ?? $auditHealthLast['source'] ?? 'manual'), ENT_QUOTES, 'UTF-8') ?></li>
        <li>URLs OK <?= (int) $auditLastRun['urls_ok'] ?>/<?= (int) $auditLastRun['urls_total'] ?></li>
        <li>Auto-fix <?= (int) $auditLastRun['auto_applied'] ?> · <?= htmlspecialchars((string) ($auditLastRun['finished_at'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></li>
      </ul>
      <?php else: ?>
      <p class="seo-status-num">—</p>
      <ul><li>Aún no hay auditoría. Pulsa el botón de arriba o configura el cron.</li></ul>
      <?php endif; ?>
      <?php if (is_array($auditHealthLast)): ?>
      <div class="seo-mini-scores">
        <span>SEO <?= (int) $auditHealthLast['score_seo_avg'] ?></span>
        <span>Téc <?= (int) $auditHealthLast['score_tech_avg'] ?></span>
        <span>GEO <?= (int) $auditHealthLast['score_geo_avg'] ?></span>
        <span>Conv <?= (int) $auditHealthLast['score_conv_avg'] ?></span>
      </div>
      <?php endif; ?>
    </article>
    <?php else: ?>
    <article class="seo-status-card is-run">
      <h3>Enfoque de este módulo</h3>
      <p class="seo-status-num" style="font-size:1.15rem;line-height:1.25;margin:.35rem 0 .5rem">Ops · no código</p>
      <ul>
        <li>Google Search Console</li>
        <li>Google Business / reseñas / citaciones</li>
        <li>Casos reales y seguimiento mensual</li>
      </ul>
      <a class="seo-status-link" href="<?= htmlspecialchars($codeUrl) ?>">Ver módulo código →</a>
    </article>
    <?php endif; ?>
  </div>

  <?php if ($isCodeModule && ($systemDoneBits !== [] || $systemWarnBits !== [])): ?>
  <div class="seo-system-bits">
    <?php if ($systemDoneBits !== []): ?>
    <p><strong>Ya aplicado en el plan:</strong> <?= htmlspecialchars(implode(' · ', $systemDoneBits), ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if ($systemWarnBits !== []): ?>
    <p class="is-warn"><strong>Revisar:</strong> <?= htmlspecialchars(implode(' · ', $systemWarnBits), ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</section>

<style>
/* Módulos */
.seo-module-switch{display:grid;grid-template-columns:1fr 1fr;gap:.65rem;margin:0 0 1rem}
@media(max-width:700px){.seo-module-switch{grid-template-columns:1fr}}
.seo-module-switch a{display:block;padding:.85rem 1rem;border-radius:12px;border:1px solid rgba(15,23,42,.1);background:#fff;text-decoration:none;color:inherit;transition:border-color .15s,background .15s}
.seo-module-switch a:hover{border-color:rgba(37,99,235,.35);background:#f8fbff}
.seo-module-switch a.is-active{border-color:#0f172a;background:#0f172a;color:#fff}
.seo-module-switch a strong{display:block;font-size:.95rem;font-weight:800;margin-bottom:.2rem}
.seo-module-switch a span{display:block;font-size:.78rem;opacity:.8;line-height:1.35}
.seo-module-switch a.is-active span{opacity:.75;color:#cbd5e1}
/* Guía + estado */
.seo-guide{background:#fff;border:1px solid rgba(15,23,42,.08);border-radius:14px;padding:1rem 1.15rem;margin:0 0 1rem}
.seo-guide h2{margin:0 0 .65rem;font-size:1.05rem;font-weight:800;color:#0f172a}
.seo-guide-steps{margin:0;padding:0;list-style:none;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.65rem}
@media(max-width:800px){.seo-guide-steps{grid-template-columns:1fr}}
.seo-guide-steps li{padding:.7rem .8rem;border-radius:10px;background:#f8fafc;border:1px solid rgba(15,23,42,.06)}
.seo-guide-steps strong{display:block;font-size:.82rem;color:#0f172a;margin-bottom:.2rem}
.seo-guide-steps span{display:block;font-size:.8rem;color:#64748b;line-height:1.4}
.seo-status{background:#fff;border:1px solid rgba(15,23,42,.08);border-radius:14px;padding:1rem 1.15rem;margin:0 0 1.15rem;display:flex;flex-direction:column;gap:1rem}
.seo-status-top{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;padding-bottom:.85rem;border-bottom:1px solid rgba(15,23,42,.06);margin-bottom:0}
.seo-status-actions{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center}
.seo-fix-badge{display:inline-block;margin-left:.35rem;padding:.1rem .45rem;border-radius:999px;background:rgba(255,255,255,.25);font-size:.72rem;font-weight:800}
.seo-ai-panel{margin:.85rem 0 0;padding:.85rem 1rem;border-radius:12px;border:1px solid rgba(37,99,235,.22);background:#eff6ff}
.seo-ai-panel-head{display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;margin-bottom:.45rem}
.seo-ai-panel h3{margin:0;font-size:.9rem;font-weight:800;color:#1e3a8a}
.seo-ai-panel > p{margin:0 0 .65rem;font-size:.82rem;color:#1e40af;line-height:1.45}
.seo-ai-indicator,.seo-ai-chip{display:inline-flex;align-items:center;gap:.4rem;padding:.28rem .65rem;border-radius:999px;font-size:.75rem;font-weight:800;text-decoration:none;border:1px solid transparent;line-height:1.2}
.seo-ai-indicator.is-on,.seo-ai-chip.is-on{background:#dcfce7;color:#166534;border-color:rgba(22,163,74,.35)}
.seo-ai-indicator.is-off,.seo-ai-chip.is-off{background:#fee2e2;color:#991b1b;border-color:rgba(220,38,38,.3)}
.seo-ai-indicator.is-warn,.seo-ai-chip.is-warn{background:#ffedd5;color:#9a3412;border-color:rgba(234,88,12,.35)}
.seo-ai-indicator.is-check,.seo-ai-chip.is-check{background:#dbeafe;color:#1e40af;border-color:rgba(37,99,235,.3)}
.seo-ai-dot{width:.55rem;height:.55rem;border-radius:50%;background:currentColor;box-shadow:0 0 0 3px rgba(255,255,255,.55);flex-shrink:0}
.seo-ai-indicator.is-on .seo-ai-dot,.seo-ai-chip.is-on .seo-ai-dot{animation:seoAiPulse 1.6s ease-in-out infinite}
@keyframes seoAiPulse{0%,100%{opacity:1}50%{opacity:.45}}
.seo-ai-diag{margin:0 0 .7rem;padding:.65rem .75rem;border-radius:10px;background:#fff;border:1px solid rgba(37,99,235,.15)}
.seo-ai-diag ul{margin:0 0 .45rem;padding:0;list-style:none;font-size:.78rem;color:#334155;line-height:1.45}
.seo-ai-diag li{margin:0 0 .15rem}
.seo-ai-diag p{margin:0 0 .55rem;font-size:.78rem;color:#64748b}
.seo-ai-diag code{font-size:.72rem;background:#f1f5f9;padding:.05rem .3rem;border-radius:4px}
.seo-ai-warn{color:#9a3412 !important;background:#fff7ed;border:1px solid rgba(234,88,12,.25);padding:.55rem .7rem;border-radius:8px}
.seo-status-actions{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}
.seo-ai-gen-compact{margin-bottom:.65rem;padding:.65rem 0}
.seo-ai-gen-compact>p{margin:0 0 .5rem;font-size:.82rem;color:#1e40af;line-height:1.45}
.seo-ai-gen-toolbar{display:flex;flex-wrap:wrap;gap:.35rem;align-items:center}
.seo-ai-gen-hint{margin:.45rem 0 0;font-size:.72rem;color:#64748b}
.seo-ck-gen-modal .modal-body .form-control,.seo-ck-gen-modal .modal-body select{font-size:.9rem}
.seo-ck-gen-modal .seo-ck-modal-lead{margin:0 0 .75rem;font-size:.84rem;color:#64748b;line-height:1.45}
.seo-ai-status{margin:.4rem 0;padding:.5rem .65rem;border-radius:8px;background:#dbeafe;color:#1e3a8a;font-size:.82rem}
.seo-ai-status.is-err{background:#fef2f2;color:#991b1b}
.seo-ai-preview{margin:.5rem 0;padding:.65rem;border-radius:10px;background:#fff;border:1px solid rgba(15,23,42,.08)}
.seo-ai-preview h4{margin:0 0 .45rem;font-size:.85rem;font-weight:800}
.seo-ai-preview .seo-fix-ba{margin-top:.35rem}
.seo-ai-preview pre{margin:.35rem 0 0;max-height:220px;overflow:auto;font-size:.72rem;background:#f8fafc;padding:.5rem;border-radius:8px;white-space:pre-wrap}
.seo-ai-pending{margin-top:.55rem}
.seo-ai-pending h4{margin:0 0 .35rem;font-size:.8rem;font-weight:800;color:#1e3a8a}
.seo-ai-empty{margin:0;font-size:.8rem;color:#64748b}
.seo-ai-list{margin:0;padding:0;list-style:none;max-height:320px;overflow:auto}
.seo-proposals-table{font-size:.84rem;width:100%;table-layout:fixed}
.seo-proposals-table td,.seo-proposals-table th{vertical-align:middle}
.seo-proposals-table th:nth-child(1){width:11rem}
.seo-proposals-table th:nth-child(3){width:28%}
.seo-proposals-table th:nth-child(4){width:15rem}
.seo-proposals-type{white-space:nowrap}
.seo-proposals-url{font-size:.78rem;word-break:break-all;line-height:1.35}
.seo-proposals-actions{white-space:nowrap}
.seo-proposals-actions .btn{margin:.1rem .15rem .1rem 0}
.seo-proposals-title strong{display:block;color:#0f172a;font-size:.86rem;line-height:1.35}
.seo-fix-queue{margin:.75rem 0 0;padding:.75rem .9rem;border-radius:12px;border:1px solid rgba(22,163,74,.25);background:#f0fdf4}
.seo-fix-queue h3{margin:0 0 .35rem;font-size:.85rem;font-weight:800;color:#166534}
.seo-fix-queue p{margin:0 0 .55rem;font-size:.8rem;color:#15803d}
.seo-fix-queue-list{margin:0;padding:0;list-style:none;max-height:380px;overflow:auto}
.seo-fix-queue-item{display:flex;align-items:flex-start;justify-content:space-between;gap:.75rem;padding:.65rem 0;border-bottom:1px solid rgba(22,163,74,.12);font-size:.78rem;color:#14532d}
.seo-fix-queue-item:last-child{border-bottom:0}
.seo-fix-queue-main{flex:1;min-width:0}
.seo-fix-queue-main strong{display:block;margin:.15rem 0 .2rem;font-size:.84rem;color:#14532d}
.seo-fix-queue-main a{color:#166534;word-break:break-all;display:block}
.seo-fix-queue-main small{display:block;margin-top:.2rem;color:#3f6212}
.seo-fix-one-btn{flex-shrink:0;white-space:nowrap;font-weight:700 !important;padding:.45rem .85rem !important}
.seo-fix-one-btn:disabled{opacity:.65}
.seo-fix-empty{margin:0;font-size:.86rem;color:#166534;line-height:1.45}
.seo-fix-queue-item.is-fixable{background:rgba(255,255,255,.65);border-radius:10px;padding:.65rem .55rem;margin-bottom:.35rem;border:1px solid rgba(22,163,74,.35)}
.seo-fix-queue-item.is-manual{opacity:.92}
.seo-fix-manual-label{flex-shrink:0;font-size:.72rem;font-weight:700;color:#64748b;padding:.35rem .5rem;border:1px dashed rgba(100,116,139,.4);border-radius:8px}
.seo-fix-hint{display:block;margin-top:.25rem;color:#3f6212 !important}
.seo-item-fix-list{margin-top:.55rem;padding:.55rem .7rem;border-radius:8px;border:1px solid rgba(22,163,74,.28);background:#f0fdf4}
.seo-item-fix-list strong{display:block;font-size:.7rem;text-transform:uppercase;letter-spacing:.03em;color:#166534;margin-bottom:.35rem}
.seo-item-fix-row{display:flex;align-items:flex-start;justify-content:space-between;gap:.5rem;padding:.4rem 0;border-bottom:1px solid rgba(22,163,74,.12);font-size:.78rem}
.seo-item-fix-row:last-child{border-bottom:0}
.seo-item-fix-row a{color:#166534;word-break:break-all}
@media(max-width:700px){
  .seo-fix-queue-item{flex-direction:column;align-items:stretch}
  .seo-item-fix-row{flex-direction:column;align-items:stretch}
}
.seo-fix-report{margin:.85rem 0 0;border-radius:12px;border:1px solid rgba(15,23,42,.1);background:#fff;overflow:hidden}
.seo-fix-report__head{padding:.75rem 1rem;background:#0f172a;color:#fff}
.seo-fix-report__head h3{margin:0;font-size:.95rem;font-weight:800}
.seo-fix-report__head p{margin:.25rem 0 0;font-size:.78rem;color:#94a3b8}
.seo-fix-report__list{padding:.65rem;max-height:420px;overflow:auto}
.seo-fix-card{border:1px solid rgba(15,23,42,.08);border-radius:10px;padding:.7rem .8rem;margin-bottom:.5rem;background:#f8fafc}
.seo-fix-card:last-child{margin-bottom:0}
.seo-fix-card.is-ok{border-color:rgba(22,163,74,.35);background:#f0fdf4}
.seo-fix-card.is-fail{border-color:rgba(220,38,38,.3);background:#fef2f2}
.seo-fix-card h4{margin:0 0 .35rem;font-size:.86rem;font-weight:800;color:#0f172a}
.seo-fix-card .seo-fix-url{display:block;font-size:.78rem;color:#0369a1;word-break:break-all;margin-bottom:.45rem}
.seo-fix-ba{display:grid;grid-template-columns:1fr 1fr;gap:.5rem}
@media(max-width:700px){.seo-fix-ba{grid-template-columns:1fr}}
.seo-fix-ba div{padding:.45rem .55rem;border-radius:8px;background:#fff;border:1px solid rgba(15,23,42,.06);font-size:.75rem;line-height:1.4;color:#334155}
.seo-fix-ba strong{display:block;font-size:.68rem;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.2rem;color:#64748b}
.seo-fix-ba .is-before{border-left:3px solid #f59e0b}
.seo-fix-ba .is-after{border-left:3px solid #16a34a}
.seo-fix-card .seo-fix-file{margin-top:.4rem;font-size:.72rem;color:#64748b}
.seo-status-progress{display:flex;align-items:center;gap:.85rem;flex:1;min-width:220px}
.seo-status-progress .seo-check-bar{flex:1;min-width:140px}
.seo-check-bar{height:10px;border-radius:999px;background:rgba(15,23,42,.08);overflow:hidden}
.seo-check-bar>span{display:block;height:100%;background:linear-gradient(90deg,#16a34a,#22c55e);border-radius:999px;transition:width .25s ease}
.seo-check-pct{font-weight:800;font-size:1.5rem;color:#0f172a;min-width:3.5rem}
.seo-check-meta{color:#64748b;font-size:.86rem;margin-top:.25rem}
.seo-audit-status{margin:0 0 .85rem;padding:.65rem .8rem;border-radius:10px;background:#eff6ff;border:1px solid rgba(37,99,235,.25);color:#1e3a8a;font-size:.86rem}
.seo-audit-status.is-err{background:#fef2f2;border-color:rgba(220,38,38,.3);color:#991b1b}
.seo-status-cols{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem;margin:0}
@media(max-width:900px){.seo-status-cols{grid-template-columns:1fr}}
.seo-status-card{border-radius:12px;padding:.85rem .95rem;border:1px solid rgba(15,23,42,.08);background:#f8fafc}
.seo-status-card.is-pending{border-color:rgba(245,158,11,.35);background:#fffbeb}
.seo-status-card.is-done{border-color:rgba(16,185,129,.35);background:#ecfdf5}
.seo-status-card.is-run{border-color:rgba(37,99,235,.25);background:#eff6ff}
.seo-status-card h3{margin:0;font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
.seo-status-card.is-pending h3{color:#b45309}
.seo-status-card.is-done h3{color:#047857}
.seo-status-card.is-run h3{color:#1d4ed8}
.seo-status-num{margin:.2rem 0 .45rem;font-size:2rem;font-weight:800;line-height:1;color:#0f172a}
.seo-status-num small{font-size:.95rem;font-weight:700;color:#64748b}
.seo-status-card ul{margin:0;padding:0;list-style:none;font-size:.78rem;color:#475569;line-height:1.45}
.seo-status-card li{margin:.15rem 0}
.seo-status-link{margin-top:.55rem;border:0;background:none;padding:0;font-size:.78rem;font-weight:700;color:#1d4ed8;cursor:pointer}
.seo-status-link:hover{text-decoration:underline}
.seo-mini-scores{display:flex;flex-wrap:wrap;gap:.35rem;margin-top:.55rem}
.seo-mini-scores span{font-size:.68rem;font-weight:700;padding:.15rem .4rem;border-radius:6px;background:rgba(255,255,255,.7);color:#1e40af}
.seo-system-bits{margin-top:.85rem;padding-top:.75rem;border-top:1px solid rgba(15,23,42,.06);font-size:.82rem;color:#334155}
.seo-system-bits p{margin:0 0 .35rem}
.seo-system-bits p:last-child{margin:0}
.seo-system-bits .is-warn{color:#92400e}
.seo-work-head{display:flex;justify-content:space-between;align-items:flex-end;gap:1rem;flex-wrap:wrap;margin:0 0 .75rem}
.seo-work-head h2{margin:0;font-size:1.05rem;font-weight:800;color:#0f172a}
.seo-work-head p{margin:.2rem 0 0;font-size:.82rem;color:#64748b}
.seo-filters{display:flex;flex-wrap:wrap;gap:.4rem}
.seo-filter{border:1px solid rgba(15,23,42,.12);background:#fff;color:#334155;font-size:.78rem;font-weight:700;padding:.4rem .75rem;border-radius:999px;cursor:pointer}
.seo-filter:hover{border-color:rgba(37,99,235,.35);background:#eff6ff}
.seo-filter.is-active{background:#0f172a;border-color:#0f172a;color:#fff}
.seo-agenda{background:#fff;border:1px solid rgba(15,23,42,.08);border-radius:14px;margin:0 0 1rem;overflow:hidden}
.seo-agenda-head{padding:.75rem 1rem;background:#f8fafc;border-bottom:1px solid rgba(15,23,42,.06)}
.seo-agenda-head h2{margin:0;font-size:.95rem;font-weight:700;color:#0f172a}
.seo-agenda-head p{margin:.25rem 0 0;font-size:.8rem;color:#64748b;line-height:1.4}
.seo-agenda-cols{display:grid;grid-template-columns:1fr 1fr;gap:0}
@media(max-width:900px){.seo-agenda-cols{grid-template-columns:1fr}}
.seo-agenda-col{padding:.75rem .9rem}
.seo-agenda-col + .seo-agenda-col{border-left:1px solid rgba(15,23,42,.06)}
@media(max-width:900px){.seo-agenda-col + .seo-agenda-col{border-left:0;border-top:1px solid rgba(15,23,42,.06)}}
.seo-agenda-col h3{margin:0 0 .55rem;font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
.seo-agenda-item{display:block;padding:.55rem .65rem;border-radius:10px;border:1px solid rgba(15,23,42,.08);background:#f8fafc;margin-bottom:.4rem;text-decoration:none;color:inherit}
.seo-agenda-item:hover{border-color:rgba(37,99,235,.35);background:#eff6ff}
.seo-agenda-item:last-child{margin-bottom:0}
.seo-agenda-item .t{display:block;font-size:.84rem;font-weight:700;color:#0f172a;margin-bottom:.15rem}
.seo-agenda-item .m{display:block;font-size:.74rem;color:#64748b;line-height:1.35}
.seo-agenda-item .e{display:inline-block;margin-top:.2rem;font-size:.7rem;font-weight:700;color:#1d4ed8}
.seo-agenda-empty{font-size:.8rem;color:#94a3b8;margin:0}
.seo-more{background:#fff;border:1px solid rgba(15,23,42,.08);border-radius:14px;margin:1.15rem 0;overflow:hidden}
.seo-more>summary{cursor:pointer;padding:.85rem 1.05rem;font-weight:700;font-size:.95rem;color:#0f172a;background:#f8fafc;list-style:none}
.seo-more>summary::-webkit-details-marker{display:none}
.seo-more>summary::after{content:"▼";float:right;font-size:.7rem;color:#94a3b8;margin-top:.25rem}
.seo-more[open]>summary::after{content:"▲"}
.seo-more-body{padding:0 1.05rem 1.05rem}
.seo-more-body h3{margin:1rem 0 .45rem;font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
.seo-more-list{margin:0;padding:0;list-style:none;max-height:240px;overflow:auto}
.seo-more-list li{padding:.4rem 0;border-bottom:1px solid rgba(15,23,42,.06);font-size:.8rem;line-height:1.4;color:#334155}
.seo-more-list a{color:#0369a1;word-break:break-all}
.seo-sev{display:inline-block;padding:.1rem .4rem;border-radius:999px;font-size:.65rem;font-weight:800;text-transform:uppercase;margin-right:.35rem}
.seo-sev-critical{background:#7f1d1d;color:#fecaca}
.seo-sev-high{background:#9a3412;color:#ffedd5}
.seo-sev-medium{background:#854d0e;color:#fef9c3}
.seo-sev-low{background:#334155;color:#e2e8f0}
.seo-cron-help{margin-top:.75rem;font-size:.8rem;color:#475569}
.seo-cron-help ol{margin:.4rem 0 0;padding-left:1.2rem;line-height:1.5}
.seo-cron-help code{display:inline-block;margin-top:.2rem;padding:.2rem .4rem;border-radius:6px;background:#f1f5f9;color:#0f172a;word-break:break-all}
.seo-activity{margin-top:.5rem}
.seo-activity-list{max-height:280px;overflow:auto}
.seo-activity-item{width:100%;display:flex;align-items:flex-start;gap:.65rem;text-align:left;border:1px solid rgba(15,23,42,.06);background:#fff;border-radius:10px;padding:.6rem .7rem;margin-bottom:.4rem;cursor:pointer}
.seo-activity-item:hover{border-color:rgba(37,99,235,.35);background:#f8fbff}
.seo-activity-item:last-child{margin-bottom:0}
.seo-activity-body{flex:1;min-width:0}
.seo-activity-body strong{display:block;font-size:.84rem;color:#0f172a;margin-bottom:.1rem}
.seo-activity-body small{display:block;font-size:.74rem;color:#64748b;line-height:1.35}
.seo-activity-go{flex-shrink:0;font-size:.7rem;font-weight:700;color:#1d4ed8;margin-top:.15rem}
.seo-phase{background:#fff;border:1px solid rgba(15,23,42,.08);border-radius:14px;margin-bottom:1rem;overflow:hidden}
.seo-phase-head{display:flex;justify-content:space-between;align-items:center;gap:.75rem;padding:.9rem 1.1rem;background:#f8fafc;border-bottom:1px solid rgba(15,23,42,.06)}
.seo-phase-head h2{margin:0;font-size:1rem;font-weight:700;color:#0f172a}
.seo-phase-count{font-size:.8rem;font-weight:600;color:#64748b}
.seo-item{display:flex;gap:.85rem;align-items:flex-start;padding:1rem 1.1rem;border-bottom:1px solid rgba(15,23,42,.05)}
.seo-item:last-child{border-bottom:0}
.seo-item.is-done{background:rgba(22,163,74,.04)}
.seo-item.is-done .seo-item-title{color:#166534}
.seo-item.is-progress{background:rgba(245,158,11,.06)}
.seo-item.is-hidden-filter{display:none !important}
.seo-phase.is-hidden-filter{display:none !important}
.seo-item-title-row{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin:0 0 .25rem}
.seo-item-title-row .seo-item-title{margin:0}
.seo-tag{display:inline-flex;align-items:center;gap:.25rem;padding:.18rem .55rem;border-radius:999px;font-size:.68rem;font-weight:800;letter-spacing:.03em;text-transform:uppercase}
.seo-tag-progress{background:#fffbeb;color:#b45309;border:1px solid rgba(245,158,11,.45)}
.seo-tag-done{background:#ecfdf5;color:#047857;border:1px solid rgba(16,185,129,.35)}
.seo-tag-now{background:#eff6ff;color:#1d4ed8;border:1px solid rgba(37,99,235,.35)}
.seo-tag-later{background:#f1f5f9;color:#475569;border:1px solid rgba(100,116,139,.35)}
.seo-tag-autofix{background:#ecfeff;color:#0e7490;border:1px solid rgba(8,145,178,.35)}
.seo-tag-dynamic{background:#f5f3ff;color:#6d28d9;border:1px solid rgba(109,40,217,.3)}
.seo-plan-box{margin:.55rem 0 0;padding:.65rem .75rem;border-radius:10px;border:1px solid rgba(37,99,235,.2);background:#f8fbff;color:#1e3a8a;font-size:.82rem;line-height:1.45}
.seo-plan-box.is-later{border-color:rgba(100,116,139,.25);background:#f8fafc;color:#334155}
.seo-plan-box strong{display:block;margin-bottom:.25rem;font-size:.72rem;text-transform:uppercase;letter-spacing:.03em;color:#1d4ed8}
.seo-plan-box.is-later strong{color:#475569}
.seo-plan-meta{display:flex;flex-wrap:wrap;gap:.35rem .75rem;margin:.2rem 0 .35rem;font-size:.78rem;font-weight:600}
.seo-pending-box{margin:.55rem 0 0;padding:.65rem .75rem;border-radius:10px;border:1px solid rgba(245,158,11,.28);background:#fffbeb;color:#92400e;font-size:.82rem;line-height:1.45}
.seo-pending-box strong{display:block;margin-bottom:.25rem;font-size:.72rem;text-transform:uppercase;letter-spacing:.03em;color:#b45309}
.seo-pending-box ul{margin:.25rem 0 0;padding-left:1.1rem}
.seo-pending-box li{margin:.15rem 0}
.seo-check{appearance:none;-webkit-appearance:none;width:1.35rem;height:1.35rem;margin-top:.15rem;border:2px solid #94a3b8;border-radius:6px;background:#fff;cursor:pointer;flex-shrink:0;display:grid;place-items:center}
.seo-check:checked{border-color:#16a34a;background:#16a34a}
.seo-check:checked::after{content:"✓";color:#fff;font-size:.85rem;font-weight:800;line-height:1}
.seo-check:disabled{opacity:.85;cursor:default}
.seo-item-main{flex:1;min-width:0}
.seo-item-top{display:flex;align-items:flex-start;justify-content:space-between;gap:.75rem}
.seo-item-title{font-weight:700;font-size:.95rem;color:#0f172a;margin:0 0 .25rem}
.seo-item-desc{margin:0;font-size:.86rem;color:#64748b;line-height:1.45}
.seo-item-block{margin-top:.55rem;padding:.55rem .7rem;border-radius:8px;background:#f8fafc;border:1px solid rgba(15,23,42,.06)}
.seo-item-block strong{display:block;font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:#64748b;margin-bottom:.2rem}
.seo-item-block p{margin:0;font-size:.84rem;color:#334155;line-height:1.45}
.seo-item-block.is-correction{background:#ecfdf5;border-color:rgba(22,163,74,.18)}
.seo-item-block.is-correction strong{color:#166534}
.seo-item-timeline{margin-top:.55rem;padding:.55rem .7rem;border-radius:8px;background:#f8fafc;border:1px solid rgba(15,23,42,.06);font-size:.8rem;color:#475569}
.seo-item-timeline strong{display:block;font-size:.7rem;text-transform:uppercase;letter-spacing:.03em;color:#64748b;margin-bottom:.25rem}
.seo-item-timeline button{border:0;background:none;color:#1d4ed8;font-weight:700;font-size:.78rem;padding:0;cursor:pointer}
.seo-item-foot{margin-top:.45rem;font-size:.75rem;color:#94a3b8}
.seo-btn-detail{flex-shrink:0;white-space:nowrap;border:1px solid rgba(15,23,42,.12);background:#fff;color:#0f172a;font-size:.78rem;font-weight:600;padding:.35rem .7rem;border-radius:8px;cursor:pointer}
.seo-btn-detail:hover{background:#f8fafc;border-color:rgba(15,23,42,.22)}
.seo-btn-detail:disabled{opacity:.45;cursor:not-allowed}
.seo-toast{position:fixed;right:1rem;bottom:1rem;z-index:100000;background:#0f172a;color:#fff;padding:.7rem 1rem;border-radius:10px;font-size:.85rem;opacity:0;pointer-events:none;transition:opacity .2s}
.seo-toast.show{opacity:1}
#seoDetailModal .seo-modal-about,
#seoDetailModal .seo-modal-correction{padding:.7rem .8rem;border-radius:10px;font-size:.86rem;line-height:1.45;color:#334155}
#seoDetailModal .seo-modal-about{background:#f8fafc;border:1px solid rgba(15,23,42,.06)}
#seoDetailModal .seo-modal-correction{background:#ecfdf5;border:1px solid rgba(22,163,74,.2)}
#seoDetailModal .modal-header{border-bottom:1px solid rgba(15,23,42,.08)}
#seoDetailModal .modal-title{font-weight:700;font-size:1.05rem}
#seoDetailModal .seo-modal-section{margin-bottom:1.1rem}
#seoDetailModal .seo-modal-section:last-child{margin-bottom:0}
#seoDetailModal .seo-modal-label{display:block;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#64748b;margin-bottom:.35rem}
#seoDetailModal .seo-modal-notes{padding:.7rem .8rem;border-radius:10px;background:#f1f5f9;color:#334155;font-size:.86rem;line-height:1.45;white-space:pre-wrap}
#seoDetailModal .seo-modal-urls{margin:0;padding-left:1.15rem}
#seoDetailModal .seo-modal-urls li{margin:.35rem 0;font-size:.86rem;color:#334155}
#seoDetailModal .seo-modal-urls a{color:#0369a1;word-break:break-all}
#seoDetailModal .seo-modal-meta{display:block;font-size:.75rem;color:#64748b;margin-top:.1rem}
#seoDetailModal .seo-modal-update{padding:.7rem .8rem;border-radius:10px;border:1px solid rgba(5,150,105,.22);background:#ecfdf5;margin-bottom:.55rem}
#seoDetailModal .seo-modal-update strong{display:block;font-size:.86rem;color:#065f46;margin-bottom:.25rem}
#seoDetailModal .seo-modal-update pre{margin:0;white-space:pre-wrap;font-family:inherit;font-size:.82rem;color:#334155}
#seoDetailModal .seo-modal-update.is-autofix{border-color:rgba(8,145,178,.28);background:#ecfeff}
#seoDetailModal .seo-modal-update.is-autofix strong{color:#0e7490}
#seoDetailModal .seo-modal-update.is-fail{border-color:rgba(220,38,38,.28);background:#fef2f2}
#seoDetailModal .seo-modal-update.is-fail strong{color:#b91c1c}
#seoDetailModal .seo-modal-pending{padding:.7rem .8rem;border-radius:10px;border:1px solid rgba(245,158,11,.35);background:#fffbeb;color:#92400e}
#seoDetailModal .seo-modal-pending ul{margin:.35rem 0 0;padding-left:1.15rem}
#seoDetailModal .seo-modal-gsc{padding:.85rem .95rem;border-radius:10px;border:1px solid rgba(3,105,161,.28);background:#f0f9ff;color:#0c4a6e}
#seoDetailModal .seo-modal-gsc strong{display:block;font-size:.9rem;margin-bottom:.35rem;color:#075985}
#seoDetailModal .seo-modal-gsc a{font-weight:700;word-break:break-all;color:#0369a1}
#seoDetailModal .seo-modal-gsc p{margin:.45rem 0 0;font-size:.84rem;line-height:1.45}
#seoDetailModal .seo-modal-gsc ul{margin:.4rem 0 0;padding-left:1.15rem;font-size:.82rem}
@media(max-width:640px){
  .seo-item{padding:.9rem;flex-wrap:wrap}
  .seo-item-top{flex-direction:column;align-items:stretch}
  .seo-btn-detail{align-self:flex-start}
}
.seo-ck-table-panel{padding:1rem 1.1rem 1.15rem}
.seo-ck-now-hint{margin:0 0 .75rem;font-size:.8rem;color:#334155}
.seo-ck-now-chip{display:inline-block;margin:.15rem .25rem 0 0;padding:.2rem .55rem;border-radius:999px;border:1px solid #bfdbfe;background:#eff6ff;color:#1d4ed8;font-size:.72rem;font-weight:700;cursor:pointer}
.seo-ck-dt{font-size:.84rem}
.seo-ck-dt td{vertical-align:middle}
.seo-ck-dt-title{font-size:.86rem;color:#0f172a}
.seo-ck-dt-url{max-width:200px;word-break:break-all;font-size:.78rem}
.seo-ck-dt-actions{white-space:nowrap}
.seo-ck-dt-actions .btn{margin:.1rem .15rem .1rem 0}
.seo-mon-badge{display:inline-block;padding:.15rem .5rem;border-radius:999px;font-size:.68rem;font-weight:800}
.seo-mon-badge-pending{background:#fef3c7;color:#92400e}
.seo-mon-badge-working{background:#dbeafe;color:#1e40af}
.seo-mon-badge-done{background:#dcfce7;color:#166534}
.seo-mon-badge-failed{background:#fee2e2;color:#991b1b}
.seo-ck-preview-meta{font-size:.8rem;color:#475569;margin:.35rem 0}
.seo-ck-preview-block{margin:.55rem 0;padding:.65rem .75rem;border-radius:10px;background:#f8fafc;border:1px solid rgba(15,23,42,.06);font-size:.82rem;white-space:pre-wrap;line-height:1.45;color:#334155}
.seo-ck-preview-block.is-evidence{background:#fffbeb;border-color:#fde68a;color:#78350f}
.seo-ck-preview-block.is-correction{background:#ecfdf5;border-color:rgba(22,163,74,.25);color:#14532d}
.seo-roadmap{margin:1rem 0 1.25rem;display:grid;gap:.85rem}
.seo-roadmap-summary{display:flex;flex-wrap:wrap;gap:.45rem}
.seo-roadmap-chip{display:inline-flex;align-items:center;gap:.35rem;padding:.28rem .62rem;border-radius:999px;font-size:.74rem;font-weight:700;border:1px solid rgba(15,23,42,.1);background:#fff}
.seo-roadmap-chip.is-ok{color:#166534;background:#ecfdf5;border-color:rgba(22,163,74,.25)}
.seo-roadmap-chip.is-warn{color:#92400e;background:#fffbeb;border-color:rgba(245,158,11,.35)}
.seo-roadmap-chip.is-new{color:#991b1b;background:#fef2f2;border-color:rgba(220,38,38,.25)}
.seo-roadmap-chip.is-future{color:#1e40af;background:#eff6ff;border-color:rgba(59,130,246,.28)}
.seo-roadmap-panel{background:#fff;border:1px solid rgba(15,23,42,.08);border-radius:12px;overflow:hidden}
.seo-roadmap-panel summary{cursor:pointer;padding:.75rem 1rem;font-weight:700;font-size:.92rem;background:#f8fafc;list-style:none}
.seo-roadmap-panel summary::-webkit-details-marker{display:none}
.seo-roadmap-list{margin:0;padding:0;list-style:none}
.seo-roadmap-item{padding:.7rem 1rem;border-top:1px solid rgba(15,23,42,.06);font-size:.82rem}
.seo-roadmap-item-head{display:flex;flex-wrap:wrap;align-items:center;gap:.4rem;margin-bottom:.25rem}
.seo-roadmap-item-head strong{color:#0f172a;font-size:.86rem}
.seo-roadmap-tag{font-size:.68rem;font-weight:800;padding:.12rem .45rem;border-radius:999px;background:#f1f5f9;color:#475569}
.seo-roadmap-meta{color:#64748b;font-size:.76rem;line-height:1.4}
.seo-roadmap-meta em{font-style:normal;color:#334155}
.seo-guide-compact{margin:0 0 1rem;border:1px solid rgba(15,23,42,.08);border-radius:14px;background:#fff;overflow:hidden}
.seo-guide-compact>summary{cursor:pointer;padding:.75rem 1rem;font-weight:700;font-size:.88rem;color:#0f172a;background:#f8fafc;list-style:none}
.seo-guide-compact>summary::-webkit-details-marker{display:none}
.seo-guide-compact .seo-guide{margin:0;border:0;border-radius:0;border-top:1px solid rgba(15,23,42,.06)}
.seo-status-chips{display:none}
.seo-ck-tabs{display:flex;flex-wrap:wrap;gap:.35rem;margin:0 0 .85rem;padding:.35rem;background:#f1f5f9;border-radius:12px;border:1px solid rgba(15,23,42,.06)}
.seo-ck-tab-btn{border:0;background:transparent;padding:.45rem .75rem;border-radius:8px;font-size:.82rem;font-weight:700;color:#64748b;cursor:pointer;line-height:1.2}
.seo-ck-tab-btn:hover{color:#0f172a;background:rgba(255,255,255,.65)}
.seo-ck-tab-btn.is-active{background:#fff;color:#0f172a;box-shadow:0 1px 3px rgba(15,23,42,.08)}
.seo-ck-tab-count{display:inline-block;margin-left:.25rem;padding:.05rem .4rem;border-radius:999px;font-size:.68rem;background:#e2e8f0;color:#475569}
.seo-ck-tab-btn.is-active .seo-ck-tab-count{background:#dbeafe;color:#1d4ed8}
.seo-ck-tab-panel{display:block}
.seo-ck-tab-panel[hidden]{display:none!important}
.seo-roadmap-item-compact{border-top:1px solid rgba(15,23,42,.06)}
.seo-roadmap-item-compact>summary{cursor:pointer;padding:.65rem 1rem;list-style:none}
.seo-roadmap-item-compact>summary::-webkit-details-marker{display:none}
.seo-roadmap-item-compact[open]>summary{background:#fafafa}
.seo-roadmap-item-compact .seo-roadmap-meta{margin:0;padding:0 1rem .75rem}
.seo-history{background:#fff;border:1px solid rgba(15,23,42,.08);border-radius:14px;padding:1rem 1.1rem}
.seo-history-head h2{margin:0;font-size:1.02rem;font-weight:800;color:#0f172a}
.seo-history-head p{margin:.25rem 0 .75rem;font-size:.82rem;color:#64748b}
.seo-history-filters{display:flex;flex-wrap:wrap;gap:.35rem;margin-bottom:.85rem}
.seo-history-filters button{border:1px solid rgba(15,23,42,.1);background:#fff;padding:.28rem .62rem;border-radius:999px;font-size:.74rem;font-weight:700;color:#64748b;cursor:pointer}
.seo-history-filters button.is-active{background:#0f172a;color:#fff;border-color:#0f172a}
.seo-history-timeline{margin:0;padding:0;list-style:none;max-height:520px;overflow:auto}
.seo-history-item{display:grid;grid-template-columns:7.5rem 1fr auto;gap:.65rem;padding:.65rem 0;border-bottom:1px solid rgba(15,23,42,.06);align-items:start;font-size:.82rem}
@media(max-width:700px){.seo-history-item{grid-template-columns:1fr;gap:.35rem}}
.seo-history-item time{font-size:.74rem;color:#94a3b8;font-variant-numeric:tabular-nums}
.seo-history-body strong{display:block;color:#0f172a;margin-bottom:.15rem}
.seo-history-body p{margin:0;color:#64748b;line-height:1.4;font-size:.78rem}
.seo-history-body a{color:#0369a1;word-break:break-all;font-size:.76rem}
.seo-history-actions{display:flex;gap:.35rem;flex-wrap:wrap;justify-content:flex-end}
.seo-ai-panel{margin:0 0 1rem;padding:.85rem 1rem;border-radius:12px;border:1px solid rgba(37,99,235,.22);background:#eff6ff}
#seoMoreDetail{background:#fff;border:1px solid rgba(15,23,42,.08);border-radius:14px;padding:1rem 1.05rem;margin-top:1rem}
.seo-proposals-panel{background:#fff;border:1px solid rgba(15,23,42,.08);border-radius:14px;padding:1rem 1.1rem}
.seo-proposals-head{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;margin-bottom:.85rem}
.seo-proposals-head h3{margin:0;font-size:1.02rem;font-weight:800;color:#0f172a}
.seo-proposals-head p{margin:.25rem 0 0;font-size:.82rem;color:#64748b}
.seo-proposals-links{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center}
.seo-proposals-table{font-size:.84rem}
.seo-proposals-table td{vertical-align:middle}
.seo-proposals-actions{white-space:nowrap}
.seo-proposals-actions .btn{margin:.1rem .15rem .1rem 0}
.seo-ck-dt-wrap .dataTables_wrapper{width:100%}
.seo-ck-dt-wrap .dataTables_filter input,.seo-ck-dt-wrap .dataTables_length select{font-size:.84rem}
.seo-short-url-panel{background:#fff;border:1px solid rgba(15,23,42,.08);border-radius:14px;padding:1rem 1.15rem;margin:0 0 1rem}
.seo-short-url-panel h3{margin:0 0 .35rem;font-size:1rem;font-weight:800;color:#0f172a}
.seo-short-url-panel p.lead{margin:0 0 .75rem;font-size:.82rem;color:#64748b;line-height:1.45}
.seo-short-url-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.65rem}
.seo-short-url-phase{border:1px solid rgba(15,23,42,.08);border-radius:10px;padding:.65rem .75rem;background:#f8fafc;font-size:.78rem}
.seo-short-url-phase.is-active{border-color:#2563eb;background:#eff6ff;box-shadow:0 0 0 1px rgba(37,99,235,.15)}
.seo-short-url-phase.is-done{opacity:.85}
.seo-short-url-phase strong{display:block;font-size:.82rem;color:#0f172a;margin-bottom:.2rem}
.seo-short-url-meta{color:#64748b;line-height:1.35}
.seo-short-url-bar{height:6px;background:#e2e8f0;border-radius:99px;margin-top:.45rem;overflow:hidden}
.seo-short-url-bar span{display:block;height:100%;background:linear-gradient(90deg,#2563eb,#06b6d4);border-radius:99px}
.seo-short-url-rules{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-top:.85rem;font-size:.76rem}
@media(max-width:768px){.seo-short-url-rules{grid-template-columns:1fr}}
.seo-short-url-rules ul{margin:.25rem 0 0;padding-left:1.1rem;color:#475569}
.seo-mod-short{background:#fff;border:1px solid rgba(15,23,42,.08);border-radius:14px;padding:1.15rem 1.25rem;margin-bottom:1rem}
.seo-mod-short__hero{margin-bottom:1.1rem;padding-bottom:1rem;border-bottom:1px solid rgba(15,23,42,.06)}
.seo-mod-short__hero h2{margin:0 0 .4rem;font-size:1.15rem;font-weight:800;color:#0f172a}
.seo-mod-short__hero p{margin:0;font-size:.86rem;color:#475569;line-height:1.5}
.seo-mod-short__grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:.85rem;margin:.85rem 0}
.seo-mod-short__card{border:1px solid rgba(15,23,42,.08);border-radius:12px;padding:.85rem;background:#f8fafc;font-size:.8rem}
.seo-mod-short__card h3{margin:0 0 .45rem;font-size:.88rem;font-weight:800;color:#0f172a}
.seo-mod-short__card ul{margin:.35rem 0 0;padding-left:1.15rem;color:#475569;line-height:1.45}
.seo-mod-short__card p{margin:0;color:#64748b;line-height:1.45}
.seo-mod-short__kpi{display:flex;flex-wrap:wrap;gap:.65rem;margin:.75rem 0}
.seo-mod-short__kpi span{display:inline-flex;align-items:center;gap:.35rem;padding:.35rem .65rem;border-radius:999px;background:#eff6ff;color:#1e40af;font-size:.76rem;font-weight:600}
.seo-mod-short__kpi span.is-warn{background:#fff7ed;color:#c2410c}
.seo-mod-short__phase{margin-top:1rem;border:1px solid rgba(15,23,42,.08);border-radius:12px;overflow:hidden}
.seo-mod-short__phase-head{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.5rem;padding:.75rem 1rem;background:#f1f5f9;cursor:pointer}
.seo-mod-short__phase-head h3{margin:0;font-size:.92rem;font-weight:800;color:#0f172a}
.seo-mod-short__phase-head.is-active{background:#eff6ff;border-bottom:1px solid rgba(37,99,235,.12)}
.seo-mod-short__phase-body{padding:.85rem 1rem;display:none}
.seo-mod-short__phase-body.is-open{display:block}
.seo-mod-short__phase-meta{font-size:.78rem;color:#64748b;line-height:1.45}
.seo-mod-short-status{display:inline-block;padding:.15rem .45rem;border-radius:6px;font-size:.72rem;font-weight:600}
.seo-mod-short-status.is-pendiente{background:#f1f5f9;color:#64748b}
.seo-mod-short-status.is-propuesta{background:#fef3c7;color:#b45309}
.seo-mod-short-status.is-aplicada{background:#dcfce7;color:#15803d}
.seo-mod-short-status.is-rechazada{background:#fee2e2;color:#b91c1c}
.seo-mod-short-url{font-size:.74rem;word-break:break-all;color:#334155}
.seo-mod-short-table{font-size:.78rem}
</style>

<nav class="seo-ck-tabs" aria-label="Secciones del checklist">
  <button type="button" class="seo-ck-tab-btn <?= $tabActive === 'pending' ? 'is-active' : '' ?>" data-tab="pending">
    Pendientes <span class="seo-ck-tab-count"><?= (int) $tabCounts['pending'] ?></span>
  </button>
  <?php if ($isCodeModule): ?>
  <button type="button" class="seo-ck-tab-btn <?= $tabActive === 'proposals' ? 'is-active' : '' ?>" data-tab="proposals">
    Propuestas <span class="seo-ck-tab-count"><?= (int) $tabCounts['proposals'] ?></span>
  </button>
  <button type="button" class="seo-ck-tab-btn <?= $tabActive === 'short_urls' ? 'is-active' : '' ?>" data-tab="short_urls">
    URLs cortas <span class="seo-ck-tab-count"><?= (int) $tabCounts['short_urls'] ?></span>
  </button>
  <?php endif; ?>
  <button type="button" class="seo-ck-tab-btn <?= $tabActive === 'roadmap' ? 'is-active' : '' ?>" data-tab="roadmap">
    Roadmap <span class="seo-ck-tab-count"><?= (int) $tabCounts['roadmap'] ?></span>
  </button>
  <button type="button" class="seo-ck-tab-btn <?= $tabActive === 'history' ? 'is-active' : '' ?>" data-tab="history">
    Historial <span class="seo-ck-tab-count"><?= (int) $tabCounts['history'] ?></span>
  </button>
  <button type="button" class="seo-ck-tab-btn <?= $tabActive === 'tools' ? 'is-active' : '' ?>" data-tab="tools">
    Generar con IA
  </button>
</nav>

<div class="seo-ck-tab-panel" data-tab-panel="pending" id="seoTabPending" <?= $tabActive !== 'pending' ? 'hidden' : '' ?>>

<!-- 3. Cola de tareas (DataTable, estilo Monitor) -->
<section class="seo-work" id="seoChecklist" aria-label="Cola de tareas SEO">
  <div class="seo-ux-panel seo-ck-table-panel" id="seoFixQueue">
    <div class="seo-ck-table-head seo-dt-table-head">
      <div class="seo-dt-head-copy">
        <h3><?= $isCodeModule ? 'Cola de ejecución' : 'Cola de tareas externas' ?></h3>
        <p class="seo-ux-lead seo-dt-head-lead">
          <?= (int) count($workFeedFiltered) ?> en filtro ·
          <?= (int) ($workFeedStats['pending'] ?? 0) ?> pendientes.
          Detalle en modal para URL, evidencia e implementación.
        </p>
      </div>
      <div class="seo-dt-toolbar">
        <form method="get" class="seo-dt-filters seo-ck-filters">
          <input type="hidden" name="tab" value="pending">
          <label>
            Estado
            <select name="status" onchange="this.form.submit()">
              <option value="all" <?= $filterWorkStatus === 'all' ? 'selected' : '' ?>>Todos</option>
              <option value="pending" <?= $filterWorkStatus === 'pending' ? 'selected' : '' ?>>Pendiente</option>
              <option value="working" <?= $filterWorkStatus === 'working' ? 'selected' : '' ?>>En revisión</option>
              <option value="done" <?= $filterWorkStatus === 'done' ? 'selected' : '' ?>>Terminado</option>
            </select>
          </label>
        </form>
        <button type="button" class="btn seo-dt-refresh-btn" id="seoCkRefreshBtn" title="Recargar tabla">
          <i class="fas fa-sync-alt" aria-hidden="true"></i>
          <span>Actualizar tabla</span>
        </button>
      </div>
    </div>

    <?php if ($scheduleNow !== []): ?>
    <p class="seo-ck-now-hint">
      <strong>Trabajar ahora:</strong>
      <?php foreach (array_slice($scheduleNow, 0, 4) as $sch): ?>
      <button type="button" class="seo-ck-now-chip seo-ck-detail-btn"
        data-work-id="task-<?= htmlspecialchars((string) $sch['task_key'], ENT_QUOTES, 'UTF-8') ?>">
        <?= htmlspecialchars((string) $sch['title'], ENT_QUOTES, 'UTF-8') ?>
      </button>
      <?php endforeach; ?>
    </p>
    <?php endif; ?>

    <div class="table-responsive seo-ck-dt-wrap">
      <table class="table table-striped table-hover seo-ck-dt" id="seoCkWorkTable" width="100%" cellspacing="0">
        <thead>
          <tr>
            <th>Generada</th>
            <th>Título</th>
            <th>Roadmap</th>
            <th>Prioridad</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($workFeedFiltered as $it):
            $st = (string) ($it['work_status'] ?? 'pending');
            $url = (string) ($it['url'] ?? '');
            $isHttp = str_starts_with($url, 'http');
            $created = (string) ($it['created_at'] ?? '');
            $implemented = trim((string) ($it['implemented_at'] ?? ''));
            $workId = (string) ($it['id'] ?? '');
            $findingId = (int) ($it['finding_id'] ?? 0);
            $taskKey = (string) ($it['task_key'] ?? '');
          ?>
          <tr data-work-id="<?= htmlspecialchars($workId, ENT_QUOTES, 'UTF-8') ?>"
            data-status="<?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?>"
            <?php if ($findingId > 0): ?>data-finding-row="<?= $findingId ?>"<?php endif; ?>
            <?php if (($it['source'] ?? '') === 'task' && $taskKey !== ''): ?>id="task-<?= htmlspecialchars($taskKey, ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>>
            <td data-order="<?= htmlspecialchars($created, ENT_QUOTES, 'UTF-8') ?>">
              <?= htmlspecialchars($created !== '' ? $created : '—', ENT_QUOTES, 'UTF-8') ?>
            </td>
            <td>
              <strong class="seo-ck-dt-title"><?= htmlspecialchars((string) ($it['title'] ?? 'Tarea'), ENT_QUOTES, 'UTF-8') ?></strong>
            </td>
            <td>
              <span class="seo-roadmap-tag"><?= htmlspecialchars((string) ($it['roadmap_label'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></span>
              <?php if (($it['roadmap_category'] ?? '') !== '' && ($it['roadmap_category'] ?? '') !== '—'): ?>
              <span class="text-muted" style="font-size:.72rem;display:block;margin-top:.15rem"><?= htmlspecialchars((string) $it['roadmap_category'], ENT_QUOTES, 'UTF-8') ?></span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars((string) ($it['priority'] ?? 'Media'), ENT_QUOTES, 'UTF-8') ?></td>
            <td>
              <span class="seo-mon-badge seo-mon-badge-<?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars((string) ($it['work_label'] ?? $st), ENT_QUOTES, 'UTF-8') ?>
              </span>
            </td>
            <td class="seo-ck-dt-actions">
              <button type="button" class="btn btn-primary btn-sm seo-ck-detail-btn"
                data-work-id="<?= htmlspecialchars($workId, ENT_QUOTES, 'UTF-8') ?>"
                title="Ver evidencia y acciones">
                <i class="fas fa-search"></i> Detalle
              </button>
              <?php if (!empty($it['can_approve']) && (int) ($it['proposal_id'] ?? 0) > 0): ?>
              <button type="button" class="btn btn-success btn-sm seo-ai-apply-btn"
                data-proposal-id="<?= (int) $it['proposal_id'] ?>"
                title="Aprobar e implementar la corrección">
                <i class="fas fa-check"></i> Aprobar
              </button>
              <button type="button" class="btn btn-outline-danger btn-sm seo-ai-reject-btn"
                data-proposal-id="<?= (int) $it['proposal_id'] ?>"
                title="Rechazar esta corrección">
                Rechazar
              </button>
              <?php elseif (!empty($it['can_enqueue']) && $findingId > 0): ?>
              <button type="button" class="btn btn-outline-primary btn-sm seo-enqueue-fix-btn"
                data-finding-id="<?= $findingId ?>"
                data-finding-title="<?= htmlspecialchars((string) ($it['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <i class="fas fa-inbox"></i> Encolar
              </button>
              <?php elseif (($it['source'] ?? '') === 'task' && !empty($it['can_toggle']) && empty($it['done'])): ?>
              <button type="button" class="btn btn-outline-success btn-sm seo-ck-toggle-btn"
                data-task-key="<?= htmlspecialchars($taskKey, ENT_QUOTES, 'UTF-8') ?>"
                data-done="1">
                Marcar hecha
              </button>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
</div>

<?php if ($isCodeModule): ?>
<div class="seo-ck-tab-panel" data-tab-panel="proposals" id="seoTabProposals" <?= $tabActive !== 'proposals' ? 'hidden' : '' ?>>
<section class="seo-proposals-panel" aria-label="Cola de propuestas IA">
  <div class="seo-proposals-head">
    <div>
      <h3>Cola de propuestas IA</h3>
      <p>
        <?= (int) $aiPendingCount ?> pendiente(s) de revisión · aprueba, rechaza o revisa el antes/después antes de publicar.
      </p>
    </div>
    <div class="seo-proposals-links">
      <button type="button" class="btn btn-outline-primary btn-sm" data-tab-jump="tools">
        <i class="fas fa-magic"></i> Generar nueva
      </button>
      <a href="<?= htmlspecialchars($monitorUrl ?? '', ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-secondary btn-sm">
        Monitor completo →
      </a>
    </div>
  </div>

  <div id="seoAiPreview" class="seo-ai-preview" hidden></div>

  <?php if ($aiPendingProposals === []): ?>
  <p class="seo-ai-empty">No hay propuestas en cola. Genera una desde <strong>Generar con IA</strong> o al auditar el sitio.</p>
  <?php else: ?>
  <div class="table-responsive seo-ck-dt-wrap">
    <table class="table table-striped table-hover seo-proposals-table seo-ck-dt" id="seoProposalsTable" width="100%" cellspacing="0">
      <thead>
        <tr>
          <th>Tipo</th>
          <th>Título</th>
          <th>URL destino</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody id="seoAiPendingList">
        <?php foreach ($aiPendingProposals as $prop):
          $kindRaw = (string) ($prop['kind'] ?? '');
          $kindLabel = $aiProposalKindLabel($kindRaw);
          $propId = (int) ($prop['id'] ?? 0);
          $propUrl = (string) ($prop['target_url'] ?? '');
          $propSummary = trim((string) ($prop['prompt_summary'] ?? ''));
        ?>
        <tr data-proposal-id="<?= $propId ?>">
          <td class="seo-proposals-type">
            <span class="seo-tag seo-tag-now"><?= htmlspecialchars($kindLabel, ENT_QUOTES, 'UTF-8') ?></span>
          </td>
          <td class="seo-proposals-title">
            <strong><?= htmlspecialchars((string) ($prop['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
            <?php if ($propSummary !== ''): ?>
            <div class="text-muted" style="font-size:.74rem;margin-top:.15rem;line-height:1.35"><?= htmlspecialchars(mb_strlen($propSummary) > 120 ? mb_substr($propSummary, 0, 117) . '…' : $propSummary, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
          </td>
          <td class="seo-proposals-url">
            <?php if ($propUrl !== ''): ?>
            <a href="<?= htmlspecialchars($propUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars($propUrl, ENT_QUOTES, 'UTF-8') ?></a>
            <?php else: ?>
            <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td class="seo-proposals-actions">
            <button type="button" class="btn btn-outline-secondary btn-sm seo-ai-review-btn"
              data-proposal-id="<?= $propId ?>">Revisar</button>
            <button type="button" class="btn btn-success btn-sm seo-ai-apply-btn"
              data-proposal-id="<?= $propId ?>"><i class="fas fa-check"></i> Aprobar</button>
            <button type="button" class="btn btn-outline-danger btn-sm seo-ai-reject-btn"
              data-proposal-id="<?= $propId ?>">Rechazar</button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>
</div>
<?php endif; ?>

<?php if ($isCodeModule && is_array($shortUrlsModule)): ?>
<div class="seo-ck-tab-panel" data-tab-panel="short_urls" id="seoTabShortUrls" <?= $tabActive !== 'short_urls' ? 'hidden' : '' ?>>
<section class="seo-mod-short" aria-label="Módulo URLs cortas comerciales">

  <div class="seo-mod-short__hero">
    <h2><?= htmlspecialchars((string) ($shortUrlsModule['purpose']['title'] ?? 'URLs cortas comerciales'), ENT_QUOTES, 'UTF-8') ?></h2>
    <p><?= htmlspecialchars((string) ($shortUrlsModule['purpose']['lead'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    <div class="seo-mod-short__kpi">
      <span>Sitemap hoy ~<?= (int) ($shortUrlsModule['baseline_sitemap'] ?? 1202) ?></span>
      <span>Meta final ~<?= (int) ($shortUrlsModule['final_sitemap'] ?? 1502) ?></span>
      <span><?= (int) ($shortUrlsModule['total_urls'] ?? 0) ?> URLs en plan</span>
      <?php if (!empty($shortUrlsModule['summary']['active_phase'])): ?>
      <span class="is-warn">Fase activa: <?= htmlspecialchars((string) ($shortUrlsModule['summary']['active_phase']['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
      <?php endif; ?>
      <span><?= (int) ($shortUrlsModule['pending_proposals'] ?? 0) ?> propuesta(s) URL corta en cola</span>
    </div>
  </div>

  <div class="seo-mod-short__grid">
    <article class="seo-mod-short__card">
      <h3>Para qué sirve</h3>
      <ul>
        <?php foreach (($shortUrlsModule['purpose']['benefits'] ?? []) as $b): ?>
        <li><?= htmlspecialchars((string) $b, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
      </ul>
    </article>
    <article class="seo-mod-short__card">
      <h3>Cómo ejecuta la IA</h3>
      <p><strong>Cron:</strong> <?= htmlspecialchars((string) ($shortUrlsModule['execution']['cron'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
      <p style="margin-top:.35rem"><strong>Frecuencia:</strong> <?= htmlspecialchars((string) ($shortUrlsModule['execution']['cron_schedule'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
      <p style="margin-top:.35rem"><strong>Por corrida:</strong> <?= htmlspecialchars((string) ($shortUrlsModule['execution']['per_run'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
      <p style="margin-top:.35rem"><strong>Tipo propuesta:</strong> <code><?= htmlspecialchars((string) ($shortUrlsModule['execution']['proposal_kind'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></p>
      <p style="margin-top:.35rem"><?= htmlspecialchars((string) ($shortUrlsModule['execution']['approval'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
      <button type="button" class="btn btn-outline-primary btn-sm mt-2" data-tab-jump="proposals">Ir a Propuestas</button>
    </article>
    <article class="seo-mod-short__card">
      <h3>Contenido que genera por URL</h3>
      <ul>
        <?php foreach (($shortUrlsModule['execution']['content_generated'] ?? []) as $cg): ?>
        <li><?= htmlspecialchars((string) $cg, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
      </ul>
    </article>
    <article class="seo-mod-short__card">
      <h3>Al aprobar una propuesta</h3>
      <ul>
        <?php foreach (($shortUrlsModule['execution']['on_apply'] ?? []) as $oa): ?>
        <li><?= htmlspecialchars((string) $oa, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
      </ul>
    </article>
  </div>

  <div class="seo-mod-short__grid">
    <article class="seo-mod-short__card">
      <h3>Hacer</h3>
      <ul>
        <?php foreach (($shortUrlsModule['rules_do'] ?? []) as $rule): ?>
        <li><?= htmlspecialchars((string) $rule, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
      </ul>
    </article>
    <article class="seo-mod-short__card">
      <h3>No hacer</h3>
      <ul>
        <?php foreach (($shortUrlsModule['rules_dont'] ?? []) as $rule): ?>
        <li><?= htmlspecialchars((string) $rule, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
      </ul>
    </article>
    <article class="seo-mod-short__card">
      <h3>Servicios (5)</h3>
      <ul>
        <?php foreach (($shortUrlsModule['service_labels'] ?? []) as $slug => $label): ?>
        <li><code><?= htmlspecialchars((string) $slug, ENT_QUOTES, 'UTF-8') ?></code> — <?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
      </ul>
      <p style="margin-top:.5rem">Patrón: <code>/mexico/{plaza}/servicios/{servicio}/</code></p>
    </article>
    <article class="seo-mod-short__card">
      <h3>Proyección sitemap</h3>
      <ul>
        <li>Hoy: ~<?= (int) ($shortUrlsModule['baseline_sitemap'] ?? 1202) ?></li>
        <?php foreach (($shortUrlsModule['phases'] ?? []) as $ph): ?>
        <li><?= htmlspecialchars((string) ($ph['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>:
          +<?= (int) ($ph['actual_url_count'] ?? 0) ?> → ~<?= (int) ($ph['sitemap_after'] ?? 0) ?></li>
        <?php endforeach; ?>
      </ul>
    </article>
  </div>

  <?php foreach (($shortUrlsModule['phases'] ?? []) as $pi => $phase):
    $prog = is_array($phase['progress'] ?? null) ? $phase['progress'] : [];
    $pct = (int) ($prog['pct'] ?? 0);
    $phaseOpen = !empty($phase['is_active']) || $pi === 0;
    $plan = is_array($phase['plan'] ?? null) ? $phase['plan'] : [];
  ?>
  <div class="seo-mod-short__phase" data-short-phase="<?= htmlspecialchars((string) ($phase['key'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    <div class="seo-mod-short__phase-head<?= $phaseOpen ? ' is-active' : '' ?>" role="button" tabindex="0"
      aria-expanded="<?= $phaseOpen ? 'true' : 'false' ?>">
      <div>
        <h3><?= htmlspecialchars((string) ($phase['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
          <?php if (!empty($phase['optional'])): ?><span class="text-muted">(opcional)</span><?php endif; ?>
          <?php if (!empty($phase['is_active'])): ?> <span class="seo-tag seo-tag-now">Activa</span><?php endif; ?>
          <?php if (!empty($phase['is_done'])): ?> <span class="seo-tag">Completada</span><?php endif; ?>
        </h3>
        <div class="seo-mod-short__phase-meta">
          <?= htmlspecialchars((string) ($phase['scope'] ?? ''), ENT_QUOTES, 'UTF-8') ?> ·
          <?= htmlspecialchars(cw_seo_mexico_checklist_fmt_date((string) ($plan['start'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
          → <?= htmlspecialchars(cw_seo_mexico_checklist_fmt_date((string) ($plan['due'] ?? '')), ENT_QUOTES, 'UTF-8') ?> ·
          Progreso <?= (int) ($prog['done'] ?? 0) ?>/<?= (int) ($prog['total'] ?? 0) ?> (<?= $pct ?>%)
          <?php if (!empty($phase['gsc_gate'])): ?> · Requiere revisión GSC<?php endif; ?>
        </div>
        <?php if (!empty($plan['note'])): ?>
        <div class="seo-mod-short__phase-meta" style="margin-top:.25rem"><?= htmlspecialchars((string) $plan['note'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
      </div>
      <button type="button" class="btn btn-link btn-sm seo-ck-detail-btn"
        data-work-id="task-<?= htmlspecialchars((string) ($phase['key'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        Tarea checklist
      </button>
    </div>
    <div class="seo-mod-short__phase-body<?= $phaseOpen ? ' is-open' : '' ?>">
      <div class="table-responsive seo-ck-dt-wrap">
        <table class="table table-striped table-hover seo-mod-short-table seo-ck-dt seoShortUrlsPhaseTable" width="100%" cellspacing="0"
          data-phase-key="<?= htmlspecialchars((string) ($phase['key'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          <thead>
            <tr>
              <th>Plaza</th>
              <th>Servicio</th>
              <th>URL corta (indexar)</th>
              <th>Hub largo</th>
              <th>Estado</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (($phase['urls'] ?? []) as $urow):
              $st = (string) ($urow['status'] ?? 'pendiente');
            ?>
            <tr>
              <td><?= htmlspecialchars((string) ($urow['plaza_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string) ($urow['service_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
              <td class="seo-mod-short-url">
                <a href="<?= htmlspecialchars((string) ($urow['short_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars((string) ($urow['short_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a>
              </td>
              <td class="seo-mod-short-url">
                <a href="<?= htmlspecialchars((string) ($urow['long_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars((string) ($urow['long_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a>
              </td>
              <td>
                <span class="seo-mod-short-status is-<?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?>">
                  <?= htmlspecialchars((string) ($urow['status_label'] ?? $st), ENT_QUOTES, 'UTF-8') ?>
                </span>
                <?php if ((int) ($urow['proposal_id'] ?? 0) > 0): ?>
                <button type="button" class="btn btn-link btn-sm p-0 seo-ai-review-btn"
                  data-proposal-id="<?= (int) $urow['proposal_id'] ?>">Ver propuesta</button>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endforeach; ?>

  <div class="seo-ux-panel seo-ck-table-panel" style="margin-top:1rem">
    <div class="seo-ck-table-head seo-dt-table-head">
      <div class="seo-dt-head-copy">
        <h3>Mapa completo — todas las URLs del plan (<?= (int) ($shortUrlsModule['total_urls'] ?? 0) ?>)</h3>
        <p class="seo-ux-lead seo-dt-head-lead">Listado maestro con fase, plaza, servicio, URLs y estado de ejecución IA.</p>
      </div>
    </div>
    <div class="table-responsive seo-ck-dt-wrap">
      <table class="table table-striped table-hover seo-mod-short-table seo-ck-dt" id="seoShortUrlsMasterTable" width="100%" cellspacing="0">
        <thead>
          <tr>
            <th>Fase</th>
            <th>Plaza</th>
            <th>Servicio</th>
            <th>URL corta</th>
            <th>Estado</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (($shortUrlsModule['all_urls'] ?? []) as $urow):
            $st = (string) ($urow['status'] ?? 'pendiente');
          ?>
          <tr>
            <td><?= htmlspecialchars((string) ($urow['phase_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) ($urow['plaza_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) ($urow['service_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td class="seo-mod-short-url">
              <a href="<?= htmlspecialchars((string) ($urow['short_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars((string) ($urow['short_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a>
            </td>
            <td>
              <span class="seo-mod-short-status is-<?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars((string) ($urow['status_label'] ?? $st), ENT_QUOTES, 'UTF-8') ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</section>
</div>
<?php endif; ?>

<div class="seo-ck-tab-panel" data-tab-panel="roadmap" id="seoTabRoadmap" <?= $tabActive !== 'roadmap' ? 'hidden' : '' ?>>
<section class="seo-roadmap" id="seoRoadmap" aria-label="Roadmap SEO GEO">
  <div class="seo-roadmap-summary">
    <span class="seo-roadmap-chip is-ok">✅ Implementado · <?= (int) ($roadmapStats['implemented'] ?? 0) ?></span>
    <span class="seo-roadmap-chip is-warn">🟡 Correcciones · <?= (int) ($roadmapStats['correction'] ?? 0) ?></span>
    <span class="seo-roadmap-chip is-new">❌ Nuevas · <?= (int) ($roadmapStats['new'] ?? 0) ?></span>
    <span class="seo-roadmap-chip is-future">🔮 Futuras · <?= (int) ($roadmapStats['future'] ?? 0) ?></span>
  </div>

  <div class="seo-ux-panel seo-ck-table-panel" style="margin-top:.85rem">
    <div class="seo-ck-table-head seo-dt-table-head">
      <div class="seo-dt-head-copy">
        <h3>Plan SEO/GEO</h3>
        <p class="seo-ux-lead seo-dt-head-lead">
          <?= (int) $tabCounts['roadmap'] ?> pendiente(s) de roadmap · <?= count($roadmapTableRows) ?> elemento(s) en total.
        </p>
      </div>
    </div>
    <?php if ($roadmapTableRows === []): ?>
    <p class="seo-roadmap-item seo-roadmap-meta" style="margin:0">Sin elementos en el roadmap.</p>
    <?php else: ?>
    <div class="table-responsive seo-ck-dt-wrap">
      <table class="table table-striped table-hover seo-ck-dt" id="seoRoadmapTable" width="100%" cellspacing="0">
        <thead>
          <tr>
            <th>Estado</th>
            <th>Elemento</th>
            <th>Categoría</th>
            <th>Prioridad</th>
            <th>Impacto SEO</th>
            <th>Acción / situación</th>
            <th>Detalle</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($roadmapTableRows as $rmRow):
            $bucket = (string) ($rmRow['bucket'] ?? '');
            $bucketClass = match ($bucket) {
                'implemented' => 'seo-tag-done',
                'correction' => 'seo-tag-now',
                'new' => 'seo-tag-progress',
                default => 'seo-tag-now',
            };
            $detailParts = array_filter([
                ($rmRow['solution'] ?? '') !== '' ? 'Acción: ' . $rmRow['solution'] : '',
                ($rmRow['current_state'] ?? '') !== '' ? 'Situación: ' . $rmRow['current_state'] : '',
                ($rmRow['problem'] ?? '') !== '' ? 'Problema: ' . $rmRow['problem'] : '',
            ]);
            $detailText = implode(' · ', $detailParts);
            if (mb_strlen($detailText) > 220) {
                $detailText = mb_substr($detailText, 0, 217) . '…';
            }
            $taskKeyRm = (string) ($rmRow['task_key'] ?? '');
          ?>
          <tr data-roadmap-bucket="<?= htmlspecialchars($bucket, ENT_QUOTES, 'UTF-8') ?>">
            <td data-search="<?= htmlspecialchars($bucket, ENT_QUOTES, 'UTF-8') ?>">
              <span class="seo-tag <?= $bucketClass ?>"><?= htmlspecialchars((string) ($rmRow['bucket_label'] ?? $bucket), ENT_QUOTES, 'UTF-8') ?></span>
            </td>
            <td><strong><?= htmlspecialchars((string) ($rmRow['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong></td>
            <td><?= htmlspecialchars((string) (($rmRow['category'] ?? '') !== '' ? $rmRow['category'] : '—'), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) (($rmRow['priority'] ?? '') !== '' ? $rmRow['priority'] : '—'), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) (($rmRow['impact_seo'] ?? '') !== '' && ($rmRow['impact_seo'] ?? '') !== '—' ? $rmRow['impact_seo'] : '—'), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($detailText !== '' ? $detailText : '—', ENT_QUOTES, 'UTF-8') ?></td>
            <td class="seo-ck-dt-actions">
              <?php if ($taskKeyRm !== ''): ?>
              <button type="button" class="btn btn-outline-secondary btn-sm seo-ck-detail-btn"
                data-work-id="task-<?= htmlspecialchars($taskKeyRm, ENT_QUOTES, 'UTF-8') ?>">Detalle</button>
              <?php else: ?>
              <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</section>
</div>

<div class="seo-ck-tab-panel" data-tab-panel="history" id="seoTabHistory" <?= $tabActive !== 'history' ? 'hidden' : '' ?>>
<section class="seo-history" aria-label="Historial de correcciones">
  <div class="seo-history-head">
    <h2>Historial de correcciones</h2>
    <p>Solo lo ya implementado o terminado. Avances y pendientes siguen en la cola de ejecución.</p>
    <div class="seo-history-filters" id="seoHistoryFilters">
      <button type="button" class="is-active" data-history-filter="all">Todas</button>
      <button type="button" data-history-filter="autofix">AutoFix</button>
      <button type="button" data-history-filter="correction">Manuales</button>
      <button type="button" data-history-filter="task_done">Tareas terminadas</button>
    </div>
  </div>
  <?php if ($correctionHistory === []): ?>
  <p style="margin:0;font-size:.82rem;color:#64748b">Aún no hay correcciones terminadas en este módulo. Las pendientes aparecen en la pestaña Pendientes.</p>
  <?php else: ?>
  <div class="table-responsive seo-ck-dt-wrap">
    <table class="table table-striped table-hover seo-ck-dt" id="seoHistoryTable" width="100%" cellspacing="0">
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Tipo</th>
          <th>Título</th>
          <th>Resumen</th>
          <th>URL</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($correctionHistory as $h):
          $hType = (string) ($h['type'] ?? 'update');
          $hOk = !empty($h['ok']);
          $hTagClass = $hType === 'autofix'
              ? ($hOk ? 'seo-tag-done' : 'seo-tag-progress')
              : ($hType === 'task_done' ? 'seo-tag-done' : 'seo-tag-now');
          $createdAt = (string) ($h['created_at'] ?? '');
          $dateFmt = $createdAt !== ''
              ? cw_seo_mexico_checklist_fmt_date(substr($createdAt, 0, 10)) . (strlen($createdAt) > 10 ? ' ' . substr($createdAt, 11, 5) : '')
              : '—';
          $tk = (string) ($h['task_key'] ?? '');
          $summary = (string) ($h['summary'] ?? '');
          if (mb_strlen($summary) > 180) {
              $summary = mb_substr($summary, 0, 177) . '…';
          }
          $hUrl = (string) ($h['url'] ?? '');
        ?>
        <tr data-history-type="<?= htmlspecialchars($hType, ENT_QUOTES, 'UTF-8') ?>">
          <td data-order="<?= htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($dateFmt, ENT_QUOTES, 'UTF-8') ?></td>
          <td data-search="<?= htmlspecialchars($hType, ENT_QUOTES, 'UTF-8') ?>">
            <span class="seo-history-type-key" aria-hidden="true" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)"><?= htmlspecialchars($hType, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="seo-tag <?= $hTagClass ?>"><?= htmlspecialchars((string) ($h['type_label'] ?? $hType), ENT_QUOTES, 'UTF-8') ?></span>
          </td>
          <td><strong><?= htmlspecialchars((string) ($h['title'] ?? 'Corrección'), ENT_QUOTES, 'UTF-8') ?></strong></td>
          <td><?php if ($summary !== ''): ?><?= htmlspecialchars($summary, ENT_QUOTES, 'UTF-8') ?><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
          <td class="seo-ck-dt-url">
            <?php if ($hUrl !== ''): ?>
            <a href="<?= htmlspecialchars($hUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars($hUrl, ENT_QUOTES, 'UTF-8') ?></a>
            <?php else: ?>
            <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td class="seo-ck-dt-actions">
            <?php if ($tk !== ''): ?>
            <button type="button" class="btn btn-outline-secondary btn-sm seo-ck-detail-btn"
              data-work-id="task-<?= htmlspecialchars($tk, ENT_QUOTES, 'UTF-8') ?>">Detalle</button>
            <?php elseif (!empty($h['finding_id'])): ?>
            <button type="button" class="btn btn-outline-secondary btn-sm seo-ck-detail-btn"
              data-work-id="finding-<?= (int) $h['finding_id'] ?>">Detalle</button>
            <?php else: ?>
            <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>
</div>

<div class="seo-ck-tab-panel" data-tab-panel="tools" id="seoTabTools" <?= $tabActive !== 'tools' ? 'hidden' : '' ?>>
  <?php if ($isCodeModule): ?>
  <div class="seo-ai-panel" id="seoAiPanel">
    <div class="seo-ai-panel-head">
      <h3><i class="fas fa-robot"></i> IA: textos SEO y blogs nuevos
        <a href="<?= htmlspecialchars($monitorUrl ?? '', ENT_QUOTES, 'UTF-8') ?>" class="btn btn-link btn-sm p-0 ml-2" style="font-weight:700;font-size:.78rem">Ver monitor →</a>
      </h3>
      <?php
        $aiTonePanel = (string) ($aiStatus['tone'] ?? (!empty($aiStatus['active']) ? 'on' : 'off'));
        $aiTonePanelClass = $aiTonePanel === 'on' ? 'is-on' : ($aiTonePanel === 'warn' ? 'is-warn' : 'is-off');
      ?>
      <div class="seo-ai-indicator <?= $aiTonePanelClass ?>" id="seoAiIndicator"
        title="<?= htmlspecialchars((string) ($aiStatus['detail'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <span class="seo-ai-dot" aria-hidden="true"></span>
        <span class="seo-ai-indicator-label" id="seoAiIndicatorLabel"><?= htmlspecialchars((string) ($aiStatus['label'] ?? 'IA'), ENT_QUOTES, 'UTF-8') ?></span>
      </div>
    </div>
    <div class="seo-ai-diag" id="seoAiDiag">
      <ul>
        <li><strong>Estado:</strong> <span id="seoAiDiagState"><?= htmlspecialchars((string) ($aiStatus['label'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></span></li>
        <li><strong>Modelo:</strong> <?= htmlspecialchars((string) ($aiStatus['model'] ?? ''), ENT_QUOTES, 'UTF-8') ?></li>
        <li><strong>Sitio escribible:</strong> <?= !empty($aiStatus['site_writable']) ? 'sí' : 'no detectado' ?></li>
      </ul>
      <p id="seoAiDiagDetail"><?= htmlspecialchars((string) ($aiStatus['detail'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
      <button type="button" class="btn btn-outline-primary btn-sm" id="seoAiValidateBtn">
        <i class="fas fa-plug"></i> Validar conexión con OpenAI
      </button>
    </div>
    <?php if (!$aiAvailable): ?>
    <p class="seo-ai-warn" id="seoAiWarn">
      La IA no está activa. Revisa <code>OPENAI_API_KEY</code> y pulsa <strong>Validar conexión</strong>.
    </p>
    <?php else: ?>
    <div class="seo-ai-gen-compact">
      <p>Elige una acción concreta. Cada botón crea una propuesta pendiente (no modifica el sitio todavía).</p>
      <div class="seo-ai-gen-toolbar" role="group" aria-label="Crear propuestas con IA">
        <button type="button" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#seoCkModalHub">
          <i class="fas fa-map-marker-alt" aria-hidden="true"></i> Hub ciudad
        </button>
        <button type="button" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#seoCkModalBlog">
          <i class="fas fa-pen-nib" aria-hidden="true"></i> Blog nuevo
        </button>
        <button type="button" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#seoCkModalBlogImprove">
          <i class="fas fa-sync" aria-hidden="true"></i> Mejorar blog
        </button>
        <a href="<?= htmlspecialchars($monitorUrl ?? '', ENT_QUOTES, 'UTF-8') ?>?open=maintain" class="btn btn-outline-secondary btn-sm">
          <i class="fas fa-wrench" aria-hidden="true"></i> Mantenimiento
        </a>
        <a href="<?= htmlspecialchars($monitorUrl ?? '', ENT_QUOTES, 'UTF-8') ?>?open=design" class="btn btn-outline-secondary btn-sm">
          <i class="fas fa-palette" aria-hidden="true"></i> Diseño UI
        </a>
        <a href="<?= htmlspecialchars($monitorUrl ?? '', ENT_QUOTES, 'UTF-8') ?>?open=admin" class="btn btn-outline-secondary btn-sm">
          <i class="fas fa-cogs" aria-hidden="true"></i> Admin
        </a>
        <a href="<?= htmlspecialchars($monitorUrl ?? '', ENT_QUOTES, 'UTF-8') ?>?open=cliente" class="btn btn-outline-secondary btn-sm">
          <i class="fas fa-user-circle" aria-hidden="true"></i> Portal cliente
        </a>
      </div>
      <p class="seo-ai-gen-hint">Las propuestas aparecen en la pestaña <button type="button" class="seo-status-link" data-tab-jump="proposals" style="margin:0;padding:0;border:0;background:none">Propuestas</button>. Mantenimiento, diseño y portales se gestionan en el <a href="<?= htmlspecialchars($monitorUrl ?? '', ENT_QUOTES, 'UTF-8') ?>">monitor IA</a>.</p>
    </div>
    <?php endif; ?>
    <div id="seoAiStatus" class="seo-ai-status" hidden></div>
  </div>
  <?php endif; ?>

  <div class="seo-more-body" id="seoMoreDetail">
    <?php if ($isCodeModule): ?>
    <?php if ($auditOpenFindings !== []): ?>
    <h3>Hallazgos abiertos (<?= count($auditOpenFindings) ?>)</h3>
    <ul class="seo-more-list">
      <?php foreach ($auditOpenFindings as $f): ?>
      <li data-finding-row="<?= (int) ($f['id'] ?? 0) ?>">
        <span class="seo-sev seo-sev-<?= htmlspecialchars((string) $f['severity'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $f['severity'], ENT_QUOTES, 'UTF-8') ?></span>
        <strong><?= htmlspecialchars((string) $f['title'], ENT_QUOTES, 'UTF-8') ?></strong>
        · <a href="<?= htmlspecialchars((string) $f['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars((string) $f['url'], ENT_QUOTES, 'UTF-8') ?></a>
        <?php
          $fidH = (int) ($f['id'] ?? 0);
          $propH = $auditFixProposalMap[$fidH] ?? 0;
        ?>
        <?php if ($propH > 0): ?>
        <button type="button" class="btn btn-sm btn-success seo-ai-apply-btn ml-1"
          data-proposal-id="<?= (int) $propH ?>">Aprobar</button>
        <button type="button" class="btn btn-sm btn-outline-danger seo-ai-reject-btn"
          data-proposal-id="<?= (int) $propH ?>">Rechazar</button>
        <?php elseif (!empty($f['auto_fixable']) && empty($f['auto_applied'])): ?>
        <button type="button" class="btn btn-sm btn-outline-primary seo-enqueue-fix-btn ml-1"
          data-finding-id="<?= $fidH ?>"
          data-finding-title="<?= htmlspecialchars((string) ($f['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          Encolar corrección
        </button>
        <?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <?php if ($auditWorst !== []): ?>
    <h3>URLs con menor score</h3>
    <ul class="seo-more-list">
      <?php foreach ($auditWorst as $w): ?>
      <li>
        <span class="seo-sev <?= (int) $w['score_overall'] < 60 ? 'seo-sev-high' : 'seo-sev-medium' ?>"><?= (int) $w['score_overall'] ?></span>
        <strong><?= htmlspecialchars((string) $w['label'], ENT_QUOTES, 'UTF-8') ?></strong>
        · <a href="<?= htmlspecialchars((string) $w['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars((string) $w['url'], ENT_QUOTES, 'UTF-8') ?></a>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <div class="seo-cron-help">
      <h3>Cron en cPanel (producción)</h3>
      <ol>
        <li>cPanel → <strong>Cron Jobs</strong> → una vez al día (ej. 03:15).</li>
        <li>CLI:
          <code>/usr/local/bin/php /home/USUARIO/public_html/adm.conlineweb.com/analytics/cron_seo_mexico_audit.php</code>
        </li>
        <li>HTTP:
          <code>curl -fsS "<?= htmlspecialchars($cronHttpUrl, ENT_QUOTES, 'UTF-8') ?>"</code>
        </li>
      </ol>
    </div>
    <?php else: ?>
    <p style="margin:0;font-size:.82rem;color:#64748b">
      Las correcciones técnicas y AutoFix están en
      <a href="<?= htmlspecialchars($codeUrl) ?>">Auditoría de código</a>.
    </p>
    <?php endif; ?>
  </div>
</div>

</div>

<!-- Modales: crear propuestas IA (checklist) -->
<div class="modal fade seo-ck-gen-modal" id="seoCkModalHub" tabindex="-1" role="dialog" aria-labelledby="seoCkModalHubTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="seoCkModalHubTitle">Página / hub de ciudad</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <p class="seo-ck-modal-lead">Genera title, meta, H1 y hero para el hub de la plaza elegida.</p>
        <label for="seoAiCity">Ciudad</label>
        <select id="seoAiCity" class="form-control">
          <?php foreach ($aiHubPlazas as $plaza): ?>
          <option value="<?= htmlspecialchars((string) ($plaza['slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars((string) ($plaza['city'] ?? $plaza['slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="seoAiProposeHubBtn"><i class="fas fa-magic"></i> Crear propuesta</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade seo-ck-gen-modal" id="seoCkModalBlog" tabindex="-1" role="dialog" aria-labelledby="seoCkModalBlogTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="seoCkModalBlogTitle">Artículo de blog nuevo</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <p class="seo-ck-modal-lead">Elige categoría · la IA genera el tema y el borrador del artículo.</p>
        <label for="seoAiBlogCat">Categoría</label>
        <select id="seoAiBlogCat" class="form-control">
          <?php if ($aiBlogCategories === []): ?>
          <option value="seo">SEO y Posicionamiento</option>
          <?php else: ?>
            <?php foreach ($aiBlogCategories as $bc): ?>
            <option value="<?= htmlspecialchars((string) ($bc['slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
              <?= htmlspecialchars((string) ($bc['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
            </option>
            <?php endforeach; ?>
          <?php endif; ?>
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="seoAiProposeBlogBtn"><i class="fas fa-magic"></i> Generar propuesta</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade seo-ck-gen-modal" id="seoCkModalBlogImprove" tabindex="-1" role="dialog" aria-labelledby="seoCkModalBlogImproveTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="seoCkModalBlogImproveTitle">Mejorar artículo publicado</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <label for="seoAiBlogImprove">Artículo</label>
        <select id="seoAiBlogImprove" class="form-control">
          <option value="">— Elige un post —</option>
          <?php foreach ($aiBlogPosts as $bp): ?>
          <option value="<?= htmlspecialchars((string) ($bp['slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            [<?= htmlspecialchars((string) ($bp['category'] ?? ''), ENT_QUOTES, 'UTF-8') ?>]
            <?= htmlspecialchars((string) ($bp['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-outline-primary" id="seoAiImproveBlogBtn"><i class="fas fa-sync"></i> Crear propuesta de mejora</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal detalle de tarea / hallazgo -->
<div class="modal fade" id="seoDetailModal" tabindex="-1" role="dialog" aria-labelledby="seoDetailModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="seoDetailModalTitle">Detalle</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" id="seoDetailModalBody">
        <p class="text-muted mb-0">Selecciona una fila de la tabla.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-success" id="seoCkModalApproveBtn" hidden disabled>
          <i class="fas fa-check"></i> Aprobar e implementar
        </button>
        <button type="button" class="btn btn-outline-danger" id="seoCkModalRejectBtn" hidden disabled>
          Rechazar
        </button>
        <button type="button" class="btn btn-outline-primary" id="seoCkModalEnqueueBtn" hidden disabled>
          <i class="fas fa-inbox"></i> Encolar corrección
        </button>
        <button type="button" class="btn btn-outline-success" id="seoCkModalToggleBtn" hidden disabled>
          Marcar hecha
        </button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<div class="seo-toast" id="seoToast" role="status" aria-live="polite"></div>

<script>
(function () {
  var apiUrl = <?= json_encode($toggleUrl, JSON_UNESCAPED_UNICODE) ?>;
  var auditUrl = <?= json_encode($auditRunUrl, JSON_UNESCAPED_UNICODE) ?>;
  var fixUrl = <?= json_encode($autofixApiUrl ?? '', JSON_UNESCAPED_UNICODE) ?>;
  var aiUrl = <?= json_encode($aiApiUrl ?? '', JSON_UNESCAPED_UNICODE) ?>;
  var aiProposals = <?= json_encode($aiPendingProposals ?? [], JSON_UNESCAPED_UNICODE) ?>;
  var aiStatusBoot = <?= json_encode($aiStatus ?? [], JSON_UNESCAPED_UNICODE) ?>;
  var taskDetails = <?= json_encode($taskDetailsForModal, JSON_UNESCAPED_UNICODE) ?>;
  var workFeedById = <?= json_encode($workFeedById, JSON_UNESCAPED_UNICODE) ?>;
  var toast = document.getElementById('seoToast');
  var pctEl = document.getElementById('seoCheckPct');
  var barEl = document.getElementById('seoCheckBar');
  var metaEl = document.getElementById('seoCheckMeta');
  var modalTitle = document.getElementById('seoDetailModalTitle');
  var modalBody = document.getElementById('seoDetailModalBody');
  var modalApproveBtn = document.getElementById('seoCkModalApproveBtn');
  var modalRejectBtn = document.getElementById('seoCkModalRejectBtn');
  var modalEnqueueBtn = document.getElementById('seoCkModalEnqueueBtn');
  var modalToggleBtn = document.getElementById('seoCkModalToggleBtn');
  var auditBtn = document.getElementById('seoAuditRunBtn');
  var auditStatus = document.getElementById('seoAuditStatus');
  var fixReport = document.getElementById('seoFixReport');
  var fixReportList = document.getElementById('seoFixReportList');
  var fixReportMeta = document.getElementById('seoFixReportMeta');
  var currentWorkId = '';

  function parseJsonResponse(r) {
    return r.text().then(function (txt) {
      var data = null;
      try { data = txt ? JSON.parse(txt) : null; } catch (e) { data = null; }
      if (!r.ok) {
        var msg = (data && data.error) ? data.error : ('HTTP ' + r.status + (txt ? ': ' + String(txt).slice(0, 180) : ''));
        return { ok: false, error: msg };
      }
      if (!data || typeof data !== 'object') {
        return { ok: false, error: 'Respuesta no JSON del servidor' + (txt ? ': ' + String(txt).slice(0, 160) : '') };
      }
      return data;
    });
  }

  function renderFixReport(data, prepend) {
    if (!fixReport || !fixReportList) return;
    fixReport.hidden = false;
    var items = (data && data.items) ? data.items : [];
    if (fixReportMeta) {
      fixReportMeta.textContent = (data && data.message)
        ? data.message
        : ('Aplicadas: ' + (data.applied || 0));
    }
    if (!items.length) {
      if (!prepend) {
        fixReportList.innerHTML = '<p style="margin:.5rem;font-size:.84rem;color:#64748b">'
          + esc((data && data.message) || (data && data.error) || 'Sin correcciones aplicadas.')
          + '</p>';
      }
      return;
    }
    var html = '';
    items.forEach(function (it) {
      var cls = it.applied || it.improved ? 'is-ok' : (it.ok ? '' : 'is-fail');
      var before = (it.before && it.before.summary) ? it.before.summary : '—';
      var after = (it.after && it.after.summary) ? it.after.summary : '—';
      var url = it.url || '#';
      html += '<article class="seo-fix-card ' + cls + '">';
      html += '<h4>' + esc(it.title || it.finding_key || 'Corrección') + '</h4>';
      html += '<a class="seo-fix-url" href="' + esc(url) + '" target="_blank" rel="noopener">' + esc(url) + '</a>';
      html += '<div class="seo-fix-ba">';
      html += '<div class="is-before"><strong>Antes</strong>' + esc(before) + '</div>';
      html += '<div class="is-after"><strong>Después</strong>' + esc(after) + '</div>';
      html += '</div>';
      if (it.rel_path) {
        html += '<div class="seo-fix-file">Archivo: <code>' + esc(it.rel_path) + '</code>';
        if (it.applied) html += ' · aplicado';
        else if (it.error) html += ' · ' + esc(it.error);
        html += '</div>';
      } else if (it.error) {
        html += '<div class="seo-fix-file">' + esc(it.error) + '</div>';
      }
      html += '</article>';
    });
    if (prepend) {
      fixReportList.innerHTML = html + fixReportList.innerHTML;
    } else {
      fixReportList.innerHTML = html;
    }
    fixReport.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function runEnqueueFix(btn) {
    if (!aiUrl || !btn || btn.disabled) return;
    var fid = parseInt(btn.getAttribute('data-finding-id') || '0', 10);
    if (!fid) return;
    var prev = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Encolando…';
    if (auditStatus) {
      auditStatus.hidden = false;
      auditStatus.classList.remove('is-err');
      auditStatus.textContent = 'Encolando corrección como propuesta…';
    }
    fetch(aiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'propose_audit_fix', finding_id: fid })
    }).then(parseJsonResponse).then(function (data) {
      if (!data || data.ok === false) {
        if (auditStatus) {
          auditStatus.classList.add('is-err');
          auditStatus.textContent = (data && data.error) ? data.error : 'Error al encolar';
        }
        btn.disabled = false;
        btn.innerHTML = prev;
        showToast((data && data.error) || 'No se pudo encolar');
        return;
      }
      if (auditStatus) {
        auditStatus.textContent = data.message || ('Propuesta #' + data.proposal_id + ' en cola');
      }
      showToast((data.message || 'Encolada') + ' · recargando…');
      setTimeout(function () { location.reload(); }, 700);
    }).catch(function () {
      if (auditStatus) {
        auditStatus.classList.add('is-err');
        auditStatus.textContent = 'Error de red al encolar.';
      }
      btn.disabled = false;
      btn.innerHTML = prev;
    });
  }

  document.addEventListener('click', function (ev) {
    var enqueueBtn = ev.target && ev.target.closest ? ev.target.closest('.seo-enqueue-fix-btn') : null;
    if (enqueueBtn) runEnqueueFix(enqueueBtn);
  });

  var aiStatus = document.getElementById('seoAiStatus');
  var aiPreview = document.getElementById('seoAiPreview');
  var aiProposeHubBtn = document.getElementById('seoAiProposeHubBtn');
  var aiProposeBlogBtn = document.getElementById('seoAiProposeBlogBtn');
  var aiValidateBtn = document.getElementById('seoAiValidateBtn');

  function setAiStatus(msg, isErr) {
    if (!aiStatus) return;
    aiStatus.hidden = false;
    aiStatus.classList.toggle('is-err', !!isErr);
    aiStatus.textContent = msg || '';
  }

  function closeCkGenModals() {
    if (typeof jQuery !== 'undefined' && jQuery.fn.modal) {
      jQuery('.seo-ck-gen-modal').modal('hide');
    }
  }

  function paintAiIndicator(st) {
    if (!st) return;
    var tone = st.tone || (st.active ? 'on' : 'off');
    var cls = tone === 'on' ? 'is-on' : (tone === 'warn' ? 'is-warn' : 'is-off');
    var els = [
      document.getElementById('seoAiIndicator'),
      document.getElementById('seoAiChip')
    ];
    els.forEach(function (el) {
      if (!el) return;
      el.classList.remove('is-on', 'is-off', 'is-warn', 'is-check');
      el.classList.add(cls);
      el.title = st.detail || '';
    });
    var label = st.label || (st.active ? 'IA activa' : 'IA inactiva');
    var lab1 = document.getElementById('seoAiIndicatorLabel');
    var lab2 = document.getElementById('seoAiChipLabel');
    if (lab1) lab1.textContent = label;
    if (lab2) lab2.textContent = label;
    var state = document.getElementById('seoAiDiagState');
    if (state) state.textContent = label;
    var detail = document.getElementById('seoAiDiagDetail');
    if (detail) detail.textContent = st.detail || '';
  }

  paintAiIndicator(aiStatusBoot);

  function runAiValidate() {
    if (!aiUrl || !aiValidateBtn || aiValidateBtn.disabled) return;
    var prev = aiValidateBtn.innerHTML;
    aiValidateBtn.disabled = true;
    aiValidateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Validando…';
    var ind = document.getElementById('seoAiIndicator');
    var chip = document.getElementById('seoAiChip');
    [ind, chip].forEach(function (el) {
      if (!el) return;
      el.classList.remove('is-on', 'is-off');
      el.classList.add('is-check');
    });
    var lab1 = document.getElementById('seoAiIndicatorLabel');
    var lab2 = document.getElementById('seoAiChipLabel');
    if (lab1) lab1.textContent = 'Validando…';
    if (lab2) lab2.textContent = 'Validando…';
    setAiStatus('Comprobando clave y respuesta de OpenAI…', false);
    fetch(aiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'validate' })
    }).then(function (r) { return r.json(); }).then(function (data) {
      aiValidateBtn.disabled = false;
      aiValidateBtn.innerHTML = prev;
      var st = (data && data.status) ? data.status : null;
      if (st) paintAiIndicator(st);
      if (!data || !data.ok) {
        setAiStatus((data && data.error) || 'Validación fallida: IA no activa', true);
        showToast((data && data.error) || 'IA no activa');
        return;
      }
      var msg = (data.message || 'IA activa y verificada')
        + (data.latency_ms != null ? (' · ' + data.latency_ms + ' ms') : '');
      setAiStatus(msg, false);
      showToast(msg);
    }).catch(function () {
      aiValidateBtn.disabled = false;
      aiValidateBtn.innerHTML = prev;
      paintAiIndicator(aiStatusBoot);
      setAiStatus('Error de red al validar OpenAI.', true);
    });
  }

  if (aiValidateBtn) {
    aiValidateBtn.addEventListener('click', runAiValidate);
  }

  function findProposal(id) {
    id = parseInt(id, 10);
    for (var i = 0; i < aiProposals.length; i++) {
      if (parseInt(aiProposals[i].id, 10) === id) return aiProposals[i];
    }
    return null;
  }

  function renderAiPreview(prop, extra) {
    if (!aiPreview || !prop) return;
    aiPreview.hidden = false;
    var before = prop.before || {};
    var after = prop.after || {};
    var html = '<h4>' + esc(prop.title || 'Propuesta') + '</h4>';
    if (prop.target_url) {
      html += '<a class="seo-fix-url" href="' + esc(prop.target_url) + '" target="_blank" rel="noopener">'
        + esc(prop.target_url) + '</a>';
    }
    if (prop.kind === 'hub_text') {
      html += '<div class="seo-fix-ba">';
      html += '<div class="is-before"><strong>Antes</strong>'
        + esc((before.title || '—') + ' · ' + (before.h1 || '')) + '<br>'
        + esc(before.description || '') + '</div>';
      html += '<div class="is-after"><strong>Después</strong>'
        + esc((after.title || '—') + ' · ' + (after.h1 || '')) + '<br>'
        + esc(after.description || '') + '</div>';
      html += '</div>';
      if (after.hero_subtitle) {
        html += '<p style="margin:.45rem 0 0;font-size:.78rem"><strong>Hero:</strong> '
          + esc(after.hero_subtitle) + '</p>';
      }
    } else if (prop.kind === 'blog_post') {
      html += '<p style="font-size:.8rem;margin:.25rem 0"><strong>' + esc(after.title || '')
        + '</strong><br>' + esc(after.excerpt || '') + '</p>';
      html += '<pre>' + esc((after.html || '').slice(0, 2500))
        + ((after.html || '').length > 2500 ? '…' : '') + '</pre>';
    }
    if (after.rationale) {
      html += '<p style="margin:.45rem 0 0;font-size:.76rem;color:#475569"><em>'
        + esc(after.rationale) + '</em></p>';
    }
    if (extra) html += '<p style="margin:.45rem 0 0;font-size:.8rem;color:#166534">' + esc(extra) + '</p>';
    if (prop.id) {
      html += '<div style="margin-top:.55rem;display:flex;gap:.4rem;flex-wrap:wrap">';
      html += '<button type="button" class="btn btn-success btn-sm seo-ai-apply-btn" data-proposal-id="'
        + esc(String(prop.id)) + '"><i class="fas fa-check"></i> Aprobar e implementar</button>';
      html += '<button type="button" class="btn btn-outline-danger btn-sm seo-ai-reject-btn" data-proposal-id="'
        + esc(String(prop.id)) + '">Rechazar propuesta</button>';
      html += '</div>';
    }
    aiPreview.innerHTML = html;
    aiPreview.querySelectorAll('.seo-ai-apply-btn').forEach(function (b) {
      b.addEventListener('click', function () { runAiApply(b); });
    });
    aiPreview.querySelectorAll('.seo-ai-reject-btn').forEach(function (b) {
      b.addEventListener('click', function () { runAiReject(b); });
    });
    aiPreview.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function runAiPropose(action, body, btn) {
    if (!aiUrl || !btn || btn.disabled) return;
    var prev = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generando…';
    setAiStatus('Llamando a OpenAI… esto puede tomar 15–60 s.', false);
    fetch(aiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(Object.assign({ action: action }, body || {}))
    }).then(function (r) { return r.json(); }).then(function (data) {
      btn.disabled = false;
      btn.innerHTML = prev;
      if (!data || !data.ok) {
        setAiStatus((data && data.error) || 'No se pudo generar la propuesta', true);
        return;
      }
      closeCkGenModals();
      var prop = {
        id: data.proposal_id,
        kind: action === 'propose_blog_improve'
          ? 'blog_improve'
          : (action === 'propose_blog' ? 'blog_post' : 'hub_text'),
        title: (action === 'propose_blog' || action === 'propose_blog_improve')
          ? (((action === 'propose_blog_improve' ? 'Mejora blog · ' : 'Blog · ') + ((data.after && data.after.title) || '')))
          : ('Hub · ' + (body.city || '')),
        target_url: data.url || '',
        before: data.before || {},
        after: data.after || {}
      };
      aiProposals.unshift(prop);
      if (action === 'propose_blog' && data.after) {
        var blogTopic = (data.after.topic || data.after.title || '').trim();
        setAiStatus(
          blogTopic !== ''
            ? ('Tema generado: «' + blogTopic + '» · propuesta #' + data.proposal_id)
            : ('Propuesta #' + data.proposal_id + ' lista. Revísala y aplica si te convence.'),
          false
        );
      } else {
        setAiStatus('Propuesta #' + data.proposal_id + ' lista. Revísala y aplica si te convence.', false);
      }
      renderAiPreview(prop);
      showToast('Propuesta IA generada');
      if (typeof window.seoSwitchTab === 'function') {
        window.seoSwitchTab('proposals', 'seoTabProposals');
      }
    }).catch(function () {
      btn.disabled = false;
      btn.innerHTML = prev;
      setAiStatus('Error de red al llamar a la IA.', true);
    });
  }

  function runAiApply(btn) {
    if (!aiUrl || !btn || btn.disabled) return;
    var id = parseInt(btn.getAttribute('data-proposal-id') || '0', 10);
    if (!id) return;
    if (!window.confirm('¿Aplicar esta propuesta al código del sitio? Se hará backup automático.')) return;
    var prev = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Aplicando…';
    setAiStatus('Escribiendo en conlineweb.com…', false);
    fetch(aiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'apply', proposal_id: id })
    }).then(parseJsonResponse).then(function (data) {
      if (!data || !data.ok) {
        btn.disabled = false;
        btn.innerHTML = prev;
        setAiStatus((data && data.error) || 'No se pudo aplicar', true);
        showToast((data && data.error) || 'No se pudo aplicar');
        return;
      }
      setAiStatus(data.message || 'Aplicado al código.', false);
      document.querySelectorAll('[data-proposal-id="' + id + '"]').forEach(function (el) {
        if (el.tagName === 'LI' || el.tagName === 'TR') el.style.opacity = '0.45';
        if (el.classList && el.classList.contains('seo-ai-apply-btn')) {
          el.disabled = true;
          el.innerHTML = '✓ Aplicada';
        }
      });
      if (data.url && fixReport) {
        renderFixReport({
          message: data.message || 'IA aplicada',
          applied: 1,
          items: [{
            applied: true,
            title: 'IA · propuesta #' + id,
            url: data.url,
            before: { summary: JSON.stringify(data.before || {}).slice(0, 180) },
            after: { summary: (data.files || []).join(', ') },
            rel_path: (data.files && data.files[0]) || ''
          }]
        }, true);
      }
      showToast((data.message || 'Propuesta aplicada') + ' · recargando…');
      setTimeout(function () { location.reload(); }, 900);
    }).catch(function () {
      btn.disabled = false;
      btn.innerHTML = prev;
      setAiStatus('Error de red al aplicar.', true);
    });
  }

  function runAiReject(btn) {
    if (!aiUrl || !btn || btn.disabled) return;
    var id = parseInt(btn.getAttribute('data-proposal-id') || '0', 10);
    if (!id) return;
    if (!window.confirm('¿Rechazar esta propuesta? No se aplicará la corrección.')) return;
    btn.disabled = true;
    fetch(aiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'reject', proposal_id: id })
    }).then(parseJsonResponse).then(function (data) {
      if (!data || !data.ok) {
        btn.disabled = false;
        setAiStatus((data && data.error) || 'No se pudo rechazar', true);
        return;
      }
      document.querySelectorAll('li[data-proposal-id="' + id + '"]').forEach(function (el) {
        el.remove();
      });
      if (aiPreview) aiPreview.hidden = true;
      setAiStatus('Propuesta rechazada.', false);
      showToast('Propuesta rechazada · recargando…');
      setTimeout(function () { location.reload(); }, 700);
    }).catch(function () {
      btn.disabled = false;
      setAiStatus('Error de red al rechazar.', true);
    });
  }

  if (aiProposeHubBtn) {
    aiProposeHubBtn.addEventListener('click', function () {
      var city = (document.getElementById('seoAiCity') || {}).value || '';
      runAiPropose('propose_hub', { city: city }, aiProposeHubBtn);
    });
  }
  if (aiProposeBlogBtn) {
    aiProposeBlogBtn.addEventListener('click', function () {
      var cat = (document.getElementById('seoAiBlogCat') || {}).value || 'seo';
      setAiStatus('Generando tema y propuesta del artículo…', false);
      runAiPropose('propose_blog', { category: cat }, aiProposeBlogBtn);
    });
  }
  var aiImproveBlogBtn = document.getElementById('seoAiImproveBlogBtn');
  if (aiImproveBlogBtn) {
    aiImproveBlogBtn.addEventListener('click', function () {
      var slug = (document.getElementById('seoAiBlogImprove') || {}).value || '';
      if (!slug) {
        setAiStatus('Elige un artículo existente para mejorar.', true);
        return;
      }
      runAiPropose('propose_blog_improve', { slug: slug }, aiImproveBlogBtn);
    });
  }
  document.querySelectorAll('.seo-ai-apply-btn').forEach(function (btn) {
    btn.addEventListener('click', function () { runAiApply(btn); });
  });
  document.querySelectorAll('.seo-ai-reject-btn').forEach(function (btn) {
    btn.addEventListener('click', function () { runAiReject(btn); });
  });
  document.querySelectorAll('.seo-ai-review-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var prop = findProposal(btn.getAttribute('data-proposal-id'));
      if (prop) renderAiPreview(prop);
      else setAiStatus('No se encontró el detalle de la propuesta.', true);
    });
  });

  if (auditBtn && auditUrl) {
    auditBtn.addEventListener('click', function () {
      if (auditBtn.disabled) return;
      auditBtn.disabled = true;
      var prev = auditBtn.innerHTML;
      auditBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Auditando…';
      if (auditStatus) {
        auditStatus.hidden = false;
        auditStatus.classList.remove('is-err');
        auditStatus.textContent = 'Analizando multi-fuente (código ↔ vivo ↔ sitemap ↔ NAP ↔ redirects). Puede tomar ~1–2 min…';
      }
      fetch(auditUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: '{}'
      }).then(parseJsonResponse).then(function (data) {
        if (!data || !data.ok) {
          if (auditStatus) {
            auditStatus.classList.add('is-err');
            auditStatus.textContent = (data && data.error) ? data.error : 'Error al auditar';
          }
          auditBtn.disabled = false;
          auditBtn.innerHTML = prev;
          showToast((data && data.error) || 'Error al auditar');
          return;
        }
        if (auditStatus) {
          var healthAvg = (data.health && data.health.score_avg != null) ? data.health.score_avg : '—';
          var msN = (data.summary && data.summary.multisource && data.summary.multisource.findings != null)
            ? data.summary.multisource.findings : '—';
          var pq = (data.proposals_queued != null) ? data.proposals_queued : 0;
          var ps = (data.proposals_skipped != null) ? data.proposals_skipped : 0;
          var eq = (data.external_queued != null) ? data.external_queued : 0;
          var ef = (data.external_findings != null) ? data.external_findings : 0;
          auditStatus.textContent = 'Listo · corrida #' + data.run_id
            + ' · URLs ' + data.urls_scanned + '/' + data.urls_total
            + ' · salud ' + healthAvg + '/100'
            + ' · hallazgos ' + data.findings_open
            + ' · multi-fuente ' + msN
            + ' · externos SEO/GEO ' + ef
            + ' · propuestas código ' + pq
            + ' · propuestas externas ' + eq
            + (ps ? (' · ya en cola ' + ps) : '')
            + '. Recargando para aprobar/rechazar…';
        }
        setTimeout(function () { location.reload(); }, 1100);
      }).catch(function (err) {
        if (auditStatus) {
          auditStatus.classList.add('is-err');
          auditStatus.textContent = 'Error al auditar: ' + (err && err.message ? err.message : 'red/timeout');
        }
        auditBtn.disabled = false;
        auditBtn.innerHTML = prev;
      });
    });
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function fmtDate(raw) {
    if (!raw) return '';
    var d = new Date(String(raw).replace(' ', 'T'));
    if (isNaN(d.getTime())) return String(raw);
    return d.toLocaleString('es-MX', {
      day: '2-digit', month: '2-digit', year: 'numeric',
      hour: '2-digit', minute: '2-digit'
    });
  }

  function showToast(msg) {
    if (!toast) return;
    toast.textContent = msg;
    toast.classList.add('show');
    setTimeout(function () { toast.classList.remove('show'); }, 1800);
  }

  function updateStats(stats) {
    if (!stats) return;
    if (pctEl) pctEl.textContent = (stats.pct || 0) + '%';
    if (barEl) barEl.style.width = (stats.pct || 0) + '%';
    if (metaEl) metaEl.textContent = (stats.done || 0) + ' de ' + (stats.total || 0) + ' tareas del plan completadas';
  }

  function openDetailModal() {
    if (window.jQuery && jQuery.fn.modal) {
      jQuery('#seoDetailModal').modal('show');
    }
  }

  function setModalActions(item) {
    currentWorkId = item && item.id ? String(item.id) : '';
    var canApprove = !!(item && item.can_approve && item.proposal_id);
    var canEnqueue = !!(item && item.can_enqueue && item.finding_id);
    if (modalApproveBtn) {
      modalApproveBtn.hidden = !canApprove;
      modalApproveBtn.disabled = !canApprove;
      if (canApprove) {
        modalApproveBtn.setAttribute('data-proposal-id', String(item.proposal_id));
      }
    }
    if (modalRejectBtn) {
      modalRejectBtn.hidden = !canApprove;
      modalRejectBtn.disabled = !canApprove;
      if (canApprove) {
        modalRejectBtn.setAttribute('data-proposal-id', String(item.proposal_id));
      }
    }
    if (modalEnqueueBtn) {
      modalEnqueueBtn.hidden = !canEnqueue;
      modalEnqueueBtn.disabled = !canEnqueue;
      if (canEnqueue) {
        modalEnqueueBtn.setAttribute('data-finding-id', String(item.finding_id));
        modalEnqueueBtn.setAttribute('data-finding-title', item.title || '');
      }
    }
    if (modalToggleBtn) {
      var canToggle = !!(item && item.source === 'task' && item.can_toggle);
      modalToggleBtn.hidden = !canToggle;
      modalToggleBtn.disabled = !canToggle;
      if (canToggle) {
        var markDone = !item.done;
        modalToggleBtn.textContent = markDone ? 'Marcar hecha' : 'Reabrir tarea';
        modalToggleBtn.setAttribute('data-task-key', item.task_key || '');
        modalToggleBtn.setAttribute('data-done', markDone ? '1' : '0');
      }
    }
  }

  function renderTaskDetail(key) {
    var d = taskDetails[key];
    if (!d || !modalBody || !modalTitle) return false;
    modalTitle.textContent = d.title || 'Detalle de la tarea';
    var html = '';
    html += '<div class="seo-modal-section"><span class="seo-modal-label">Estado</span>';
    if (d.done) {
      html += '<div>Completada' + (d.done_at ? ' · ' + esc(fmtDate(d.done_at)) : '') + '</div>';
    } else if (d.in_progress) {
      html += '<div><span class="seo-tag seo-tag-progress">En revisión</span></div>';
    } else {
      html += '<div>Pendiente</div>';
    }
    if (d.version) html += '<div class="seo-modal-meta">Versión: ' + esc(d.version) + '</div>';
    if (d.frozen_until) html += '<div class="seo-modal-meta">Vigencia hasta: ' + esc(d.frozen_until) + '</div>';
    if (d.file) html += '<div class="seo-modal-meta">Archivo: ' + esc(d.file) + '</div>';
    html += '</div>';
    if (!d.done && d.plan) {
      html += '<div class="seo-modal-section"><span class="seo-modal-label">Calendario</span>';
      html += '<div class="seo-plan-box' + (d.plan.can_start_now ? '' : ' is-later') + '">';
      html += '<strong>' + (d.plan.can_start_now ? 'Trabajar ahora' : 'Plazo estimado') + '</strong>';
      html += '<div class="seo-plan-meta">';
      html += '<span>Inicio: ' + esc(d.plan.start_fmt || d.plan.start || '—') + '</span>';
      html += '<span>Límite: ' + esc(d.plan.due_fmt || d.plan.due || '—') + '</span>';
      html += '</div>';
      if (d.plan.note) html += '<p style="margin:0">' + esc(d.plan.note) + '</p>';
      html += '</div></div>';
    }
    if (d.description) {
      html += '<div class="seo-modal-section"><span class="seo-modal-label">De qué va</span><div class="seo-modal-about">' + esc(d.description) + '</div></div>';
    }
    if (d.correction) {
      html += '<div class="seo-modal-section"><span class="seo-modal-label">Corrección / avance</span><div class="seo-modal-correction">' + esc(d.correction) + '</div></div>';
    }
    if (d.notes) {
      html += '<div class="seo-modal-section"><span class="seo-modal-label">Notas</span><div class="seo-modal-notes">' + esc(d.notes) + '</div></div>';
    }
    if (d.pages && d.pages.length) {
      html += '<div class="seo-modal-section"><span class="seo-modal-label">URLs (' + d.pages.length + ')</span><ol class="seo-modal-urls">';
      d.pages.forEach(function (pg) {
        var url = (pg.url || '').trim();
        if (!url) return;
        html += '<li>';
        if (pg.city) html += '<b>' + esc(pg.city) + '</b><br>';
        html += '<a href="' + esc(url) + '" target="_blank" rel="noopener">' + esc(url) + '</a></li>';
      });
      html += '</ol></div>';
    }
    if (d.updates && d.updates.length) {
      html += '<div class="seo-modal-section"><span class="seo-modal-label">Historial (' + d.updates.length + ')</span>';
      d.updates.forEach(function (u) {
        html += '<div class="seo-modal-update"><strong>' + esc(u.summary || 'Actualización') + '</strong>';
        if (u.created_at) html += '<span class="seo-modal-meta">' + esc(fmtDate(u.created_at)) + '</span>';
        if (u.detail) html += '<pre>' + esc(u.detail) + '</pre>';
        html += '</div>';
      });
      html += '</div>';
    }
    modalBody.innerHTML = html;
    return true;
  }

  function renderWorkDetail(workId) {
    var item = workFeedById[workId];
    if (!item || !modalBody || !modalTitle) return;
    setModalActions(item);
    if (item.source === 'task' && item.task_key && renderTaskDetail(item.task_key)) {
      openDetailModal();
      return;
    }
    modalTitle.textContent = (item.type_label || 'Detalle') + ' · ' + (item.title || '');
    var html = '';
    html += '<p><span class="seo-tag seo-tag-progress">' + esc(item.type_label || '') + '</span> ';
    html += '<span class="seo-mon-badge seo-mon-badge-' + esc(item.work_status || '') + '">' + esc(item.work_label || '') + '</span></p>';
    html += '<p class="seo-ck-preview-meta"><strong>Generada:</strong> ' + esc(item.created_at || '—');
    html += ' · <strong>Implementada:</strong> ' + esc(item.implemented_at || '—') + '</p>';
    if (item.url) {
      html += '<p class="seo-ck-preview-meta"><strong>URL:</strong> <a href="' + esc(item.url) + '" target="_blank" rel="noopener">' + esc(item.url) + '</a></p>';
    }
    if (item.evidence || item.description) {
      html += '<div class="seo-ck-preview-block is-evidence"><strong>Evidencia</strong>\n' + esc(item.evidence || item.description || '') + '</div>';
    }
    if (item.correction) {
      html += '<div class="seo-ck-preview-block is-correction"><strong>Corrección</strong>\n' + esc(item.correction) + '</div>';
    }
    if (item.task_key) {
      html += '<p class="seo-ck-preview-meta">Tarea vinculada: <code>' + esc(item.task_key) + '</code></p>';
    }
    modalBody.innerHTML = html;
    openDetailModal();
  }

  function toggleTask(key, done, btn) {
    if (!apiUrl || !key) return;
    if (btn) btn.disabled = true;
    fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ task_key: key, done: !!done })
    }).then(function (r) { return r.json(); }).then(function (data) {
      if (!data || !data.ok) {
        if (btn) btn.disabled = false;
        showToast((data && data.error) || 'No se pudo guardar');
        return;
      }
      updateStats(data.stats);
      showToast(done ? 'Tarea marcada como hecha · recargando…' : 'Tarea reabierta · recargando…');
      setTimeout(function () { location.reload(); }, 700);
    }).catch(function () {
      if (btn) btn.disabled = false;
      showToast('Error de red');
    });
  }

  document.addEventListener('click', function (ev) {
    var detailBtn = ev.target && ev.target.closest ? ev.target.closest('.seo-ck-detail-btn') : null;
    if (detailBtn) {
      renderWorkDetail(detailBtn.getAttribute('data-work-id') || '');
      return;
    }
    var toggleBtn = ev.target && ev.target.closest ? ev.target.closest('.seo-ck-toggle-btn') : null;
    if (toggleBtn) {
      toggleTask(
        toggleBtn.getAttribute('data-task-key') || '',
        toggleBtn.getAttribute('data-done') === '1',
        toggleBtn
      );
    }
  });

  document.querySelectorAll('[data-seo-detail]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var key = btn.getAttribute('data-seo-detail') || '';
      var workId = 'task-' + key;
      if (workFeedById[workId]) {
        renderWorkDetail(workId);
      } else if (renderTaskDetail(key)) {
        setModalActions({
          source: 'task',
          task_key: key,
          can_toggle: true,
          done: !!(taskDetails[key] && taskDetails[key].done),
          id: workId
        });
        openDetailModal();
      }
    });
  });

  if (modalApproveBtn) {
    modalApproveBtn.addEventListener('click', function () { runAiApply(modalApproveBtn); });
  }
  if (modalRejectBtn) {
    modalRejectBtn.addEventListener('click', function () { runAiReject(modalRejectBtn); });
  }
  if (modalEnqueueBtn) {
    modalEnqueueBtn.addEventListener('click', function () { runEnqueueFix(modalEnqueueBtn); });
  }
  if (modalToggleBtn) {
    modalToggleBtn.addEventListener('click', function () {
      toggleTask(
        modalToggleBtn.getAttribute('data-task-key') || '',
        modalToggleBtn.getAttribute('data-done') === '1',
        modalToggleBtn
      );
    });
  }

  var refreshBtn = document.getElementById('seoCkRefreshBtn');
  if (refreshBtn) refreshBtn.addEventListener('click', function () { location.reload(); });

  function seoSwitchTab(name, scrollId) {
    document.querySelectorAll('[data-tab-panel]').forEach(function (panel) {
      panel.hidden = panel.getAttribute('data-tab-panel') !== name;
    });
    document.querySelectorAll('.seo-ck-tab-btn[data-tab]').forEach(function (btn) {
      btn.classList.toggle('is-active', btn.getAttribute('data-tab') === name);
    });
    try { localStorage.setItem('seoCkTab', name); } catch (e) {}
    if (typeof window.seoCkInitTabTables === 'function') {
      window.seoCkInitTabTables(name);
    }
    if (scrollId) {
      var el = document.getElementById(scrollId);
      if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }
  window.seoSwitchTab = seoSwitchTab;

  seoSwitchTab(<?= json_encode($tabActive, JSON_UNESCAPED_UNICODE) ?>);

  document.querySelectorAll('.seo-ck-tab-btn[data-tab]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      seoSwitchTab(btn.getAttribute('data-tab') || 'pending');
    });
  });

  document.querySelectorAll('[data-tab-jump]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      if (btn.tagName === 'A') e.preventDefault();
      var tab = btn.getAttribute('data-tab-jump') || 'pending';
      var scrollId = tab === 'pending' ? 'seoChecklist'
        : (tab === 'proposals' ? 'seoTabProposals'
        : (tab === 'short_urls' ? 'seoTabShortUrls'
        : (tab === 'tools' ? 'seoAiPanel'
        : (tab === 'history' ? 'seoTabHistory' : null))));
      seoSwitchTab(tab, scrollId);
      var filter = btn.getAttribute('data-filter-jump');
      if (filter === 'pending' && tab === 'pending') {
        var sel = document.querySelector('.seo-ck-filters select[name="status"]');
        if (sel && sel.value !== 'pending') {
          sel.value = 'pending';
          if (sel.form) sel.form.submit();
        }
      }
      if (filter === 'done' && tab === 'history') {
        var histBtn = document.querySelector('[data-history-filter="task_done"]');
        if (histBtn) histBtn.click();
      }
    });
  });

  document.querySelectorAll('[data-history-filter]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var filter = btn.getAttribute('data-history-filter') || 'all';
      document.querySelectorAll('[data-history-filter]').forEach(function (b) {
        b.classList.toggle('is-active', b === btn);
      });
      if (window.historyDtApi) {
        window.historyDtApi.column(1).search(filter === 'all' ? '' : filter).draw();
        return;
      }
      document.querySelectorAll('#seoHistoryTable tbody tr').forEach(function (row) {
        row.hidden = filter !== 'all' && row.getAttribute('data-history-type') !== filter;
      });
    });
  });

  document.querySelectorAll('.seo-mod-short__phase-head').forEach(function (head) {
    function togglePhase(open) {
      var body = head.nextElementSibling;
      if (!body || !body.classList.contains('seo-mod-short__phase-body')) return;
      var isOpen = typeof open === 'boolean' ? open : !body.classList.contains('is-open');
      body.classList.toggle('is-open', isOpen);
      head.classList.toggle('is-active', isOpen);
      head.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      if (isOpen && typeof window.seoCkInitTabTables === 'function') {
        window.seoCkInitTabTables('short_urls');
      }
    }
    head.addEventListener('click', function (e) {
      if (e.target.closest('.seo-ck-detail-btn, .seo-ai-review-btn, a, button')) return;
      togglePhase();
    });
    head.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        togglePhase();
      }
    });
  });
})();
</script>
<script src="<?= adm_href('analytics/js/datatables-es.js') ?>"></script>
<script src="<?= adm_href('vendor/datatables/jquery.dataTables.min.js') ?>"></script>
<script src="<?= adm_href('vendor/datatables/dataTables.bootstrap4.min.js') ?>"></script>
<script>
(function ($) {
  'use strict';
  if (!$ || !$.fn.DataTable) return;

  var dtLang = window.VA_DT_LANG_ES || {};

  function baseOpts(extra) {
    return $.extend(true, {
      pageLength: 25,
      lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Todos']],
      language: dtLang,
      autoWidth: false,
      deferRender: true
    }, extra || {});
  }

  function adjustDt(api) {
    if (!api) return;
    try {
      api.columns.adjust().draw(false);
    } catch (e) { /* ignore */ }
  }

  function initWorkTable() {
    var $t = $('#seoCkWorkTable');
    if (!$t.length) return null;
    if ($.fn.DataTable.isDataTable($t)) return $t.DataTable();
    return $t.DataTable(baseOpts({
      order: [[0, 'desc']],
      columnDefs: [
        { orderable: false, targets: [5] },
        { className: 'text-nowrap', targets: [0, 2, 4] }
      ]
    }));
  }

  function initProposalsTable() {
    var $t = $('#seoProposalsTable');
    if (!$t.length) return null;
    if ($.fn.DataTable.isDataTable($t)) return $t.DataTable();
    return $t.DataTable(baseOpts({
      order: [[1, 'asc']],
      columnDefs: [
        { orderable: false, targets: [3] },
        { className: 'text-nowrap', targets: [0] }
      ]
    }));
  }

  function initHistoryTable() {
    var $t = $('#seoHistoryTable');
    if (!$t.length) return null;
    if ($.fn.DataTable.isDataTable($t)) return $t.DataTable();
    var api = $t.DataTable(baseOpts({
      order: [[0, 'desc']],
      columnDefs: [
        { orderable: false, targets: [5] },
        { className: 'text-nowrap', targets: [0, 1] }
      ]
    }));
    window.historyDtApi = api;
    return api;
  }

  function initRoadmapTable() {
    var $t = $('#seoRoadmapTable');
    if (!$t.length) return null;
    if ($.fn.DataTable.isDataTable($t)) return $t.DataTable();
    return $t.DataTable(baseOpts({
      order: [[0, 'asc'], [1, 'asc']],
      columnDefs: [
        { orderable: false, targets: [6] },
        { className: 'text-nowrap', targets: [0, 3, 4] }
      ]
    }));
  }

  function initShortUrlsPhaseTables() {
    var apis = [];
    $('.seoShortUrlsPhaseTable').each(function () {
      var $t = $(this);
      var $body = $t.closest('.seo-mod-short__phase-body');
      if ($body.length && !$body.hasClass('is-open')) return;
      if ($.fn.DataTable.isDataTable($t)) {
        apis.push($t.DataTable());
        return;
      }
      apis.push($t.DataTable(baseOpts({
        order: [[0, 'asc'], [1, 'asc']],
        columnDefs: [
          { orderable: false, targets: [4] },
          { className: 'text-nowrap', targets: [0, 1] }
        ]
      })));
    });
    return apis;
  }

  function initShortUrlsMasterTable() {
    var $t = $('#seoShortUrlsMasterTable');
    if (!$t.length) return null;
    if ($.fn.DataTable.isDataTable($t)) return $t.DataTable();
    return $t.DataTable(baseOpts({
      order: [[0, 'asc'], [1, 'asc'], [2, 'asc']],
      columnDefs: [
        { className: 'text-nowrap', targets: [0, 1, 2, 4] }
      ]
    }));
  }

  window.seoCkInitTabTables = function (tab) {
    var api = null;
    var phaseApis = [];
    if (tab === 'pending') api = initWorkTable();
    else if (tab === 'proposals') api = initProposalsTable();
    else if (tab === 'history') api = initHistoryTable();
    else if (tab === 'roadmap') api = initRoadmapTable();
    else if (tab === 'short_urls') {
      phaseApis = initShortUrlsPhaseTables();
      api = initShortUrlsMasterTable();
    }
    if (api) {
      window.setTimeout(function () { adjustDt(api); }, 0);
      window.setTimeout(function () { adjustDt(api); }, 140);
    }
    phaseApis.forEach(function (pApi) {
      window.setTimeout(function () { adjustDt(pApi); }, 0);
      window.setTimeout(function () { adjustDt(pApi); }, 140);
    });
  };

  window.seoCkInitTabTables(<?= json_encode($tabActive, JSON_UNESCAPED_UNICODE) ?>);
})(jQuery);
</script>

</div>
</div>
