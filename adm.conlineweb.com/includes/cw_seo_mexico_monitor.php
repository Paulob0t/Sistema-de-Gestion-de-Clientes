<?php
/**
 * Monitor de correcciones y módulos IA / AutoFix en el sitio (cPanel).
 * Unifica: propuestas IA, hallazgos abiertos y log de AutoFix con antes/después/URL.
 */
require_once __DIR__ . '/cw_seo_mexico_ai.php';
require_once __DIR__ . '/cw_seo_mexico_autofix.php';
require_once __DIR__ . '/cw_seo_mexico_audit.php';

/**
 * @return list<array<string,mixed>>
 */
function cw_seo_mexico_ai_list_proposals_any(mysqli $conn, int $limit = 60, string $status = 'all'): array
{
    cw_seo_mexico_ai_proposals_ensure_table($conn);
    $limit = max(1, min(800, $limit));
    $status = preg_replace('/[^a-z_]/', '', strtolower($status)) ?? 'all';
    $sql = 'SELECT id, kind, status, title, target_key, target_url, prompt_summary, detail,
                   before_json, after_json, executed_before_json, executed_after_json,
                   apply_log, created_by, applied_by, created_at, applied_at
            FROM cw_seo_mexico_ai_proposals';
    if ($status !== 'all' && $status !== '') {
        $sql .= " WHERE status = '" . $conn->real_escape_string($status) . "'";
    }
    $sql .= ' ORDER BY id DESC LIMIT ' . $limit;
    $rows = [];
    $res = $conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $r['before'] = json_decode((string) ($r['before_json'] ?? ''), true) ?: [];
            $r['after'] = json_decode((string) ($r['after_json'] ?? ''), true) ?: [];
            $r['executed_before'] = json_decode((string) ($r['executed_before_json'] ?? ''), true) ?: [];
            $r['executed_after'] = json_decode((string) ($r['executed_after_json'] ?? ''), true) ?: [];
            $r['detail'] = (string) ($r['detail'] ?? '');
            $rows[] = $r;
        }
        $res->free();
    }
    return $rows;
}

/**
 * Resume texto legible de before/after (arrays o strings).
 *
 * @param mixed $data
 */
function cw_seo_mexico_monitor_summarize($data, int $max = 280): string
{
    if (is_string($data)) {
        $t = trim($data);
        return mb_strlen($t) > $max ? mb_substr($t, 0, $max) . '…' : $t;
    }
    if (!is_array($data)) {
        return '—';
    }
    if (isset($data['summary']) && is_string($data['summary']) && $data['summary'] !== '') {
        return cw_seo_mexico_monitor_summarize($data['summary'], $max);
    }
    $parts = [];
    foreach (['title', 'h1', 'description', 'hero_subtitle', 'excerpt', 'slug', 'topic', 'status', 'path', 'summary', 'change_type', 'rationale'] as $k) {
        if (!empty($data[$k]) && is_scalar($data[$k])) {
            $parts[] = $k . ': ' . (string) $data[$k];
        }
    }
    if ($parts === [] && !empty($data['html']) && is_string($data['html'])) {
        $parts[] = mb_substr(strip_tags($data['html']), 0, 160);
    }
    if (!empty($data['patches']) && is_array($data['patches'])) {
        $okN = 0;
        $failN = 0;
        foreach ($data['patches'] as $pt) {
            if (!is_array($pt)) {
                continue;
            }
            if (!empty($pt['fragment_present']) || !empty($pt['ok'])) {
                $okN++;
            } else {
                $failN++;
            }
            if (!empty($pt['path'])) {
                $parts[] = 'path:' . (string) $pt['path'];
            }
        }
        $parts[] = 'parches_ok:' . $okN;
        if ($failN > 0) {
            $parts[] = 'parches_falla:' . $failN;
        }
        if (isset($data['patches_count'])) {
            $parts[] = 'patches_count:' . (int) $data['patches_count'];
        }
    }
    if ($parts === [] && $data === []) {
        return '— (sin datos de ejecución; la propuesta aún no se aplicó o falló antes de escribir)';
    }
    if ($parts === []) {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        return cw_seo_mexico_monitor_summarize(is_string($json) ? $json : '—', $max);
    }
    return cw_seo_mexico_monitor_summarize(implode(' · ', $parts), $max);
}

/**
 * Clasifica contenido IA: página (hub) vs blog nuevo vs corrección técnica.
 *
 * @return array{content_type:string,content_label:string,is_new_content:bool,module:string}
 */
function cw_seo_mexico_monitor_classify_ai(string $kind): array
{
    if ($kind === 'blog_post') {
        return [
            'content_type' => 'blog',
            'content_label' => 'Blog nuevo',
            'is_new_content' => true,
            'module' => 'IA · Blog nuevo',
        ];
    }
    if ($kind === 'blog_improve') {
        return [
            'content_type' => 'blog',
            'content_label' => 'Blog actualizado',
            'is_new_content' => false,
            'module' => 'IA · Mejora continua blog',
        ];
    }
    if ($kind === 'short_url_commercial') {
        return [
            'content_type' => 'page',
            'content_label' => 'Landing URL corta',
            'is_new_content' => true,
            'module' => 'IA · URL corta comercial',
        ];
    }
    if ($kind === 'hub_text') {
        return [
            'content_type' => 'page',
            'content_label' => 'Página actualizada',
            'is_new_content' => false, // hub de ciudad ya existente (SEO/copy)
            'module' => 'IA · Actualización de página',
        ];
    }
    if ($kind === 'site_patch') {
        return [
            'content_type' => 'fix',
            'content_label' => 'Mantenimiento web',
            'is_new_content' => false,
            'module' => 'IA · Músculo / mantenimiento',
        ];
    }
    if ($kind === 'design_ui') {
        return [
            'content_type' => 'design',
            'content_label' => 'Rediseño',
            'is_new_content' => false,
            'module' => 'IA · Diseño con preview',
        ];
    }
    if ($kind === 'admin_validate') {
        return [
            'content_type' => 'fix',
            'content_label' => 'Validación admin',
            'is_new_content' => false,
            'module' => 'IA · Validación de updates',
        ];
    }
    if ($kind === 'admin_patch' || str_starts_with($kind, 'admin_')) {
        return [
            'content_type' => 'fix',
            'content_label' => 'Mejora admin',
            'is_new_content' => false,
            'module' => 'IA · Admin SEO',
        ];
    }
    if ($kind === 'cliente_patch' || str_starts_with($kind, 'cliente_')) {
        return [
            'content_type' => 'fix',
            'content_label' => 'Mejora portal cliente',
            'is_new_content' => false,
            'module' => 'IA · Portal cliente',
        ];
    }
    if ($kind === 'audit_fix') {
        return [
            'content_type' => 'fix',
            'content_label' => 'Corrección auditoría',
            'is_new_content' => false,
            'module' => 'Auditoría · AutoFix',
        ];
    }
    if ($kind === 'external_task' || $kind === 'external_rec') {
        return [
            'content_type' => 'external',
            'content_label' => $kind === 'external_rec' ? 'Recomendación SEO/GEO' : 'Tarea SEO/GEO externa',
            'is_new_content' => false,
            'module' => 'SEO/GEO · Externo (GSC/GBP)',
        ];
    }
    return [
        'content_type' => 'other',
        'content_label' => 'IA',
        'is_new_content' => false,
        'module' => 'IA SEO',
    ];
}

/**
 * Portal destino de la propuesta (sitio público vs admin).
 *
 * @param array<string,mixed> $item
 * @return array{portal:string,portal_label:string}
 */
function cw_seo_mexico_monitor_portal(array $item): array
{
    $kind = strtolower((string) ($item['kind'] ?? ''));
    $explicit = strtolower((string) ($item['portal'] ?? ($item['after_raw']['portal'] ?? ($item['after']['portal'] ?? ''))));
    if (in_array($explicit, ['cliente', 'client', 'cliente.conlineweb.com', 'clientes'], true)
        || str_starts_with($kind, 'cliente_')
        || $kind === 'cliente_patch'
    ) {
        return ['portal' => 'cliente', 'portal_label' => 'cliente.conlineweb.com'];
    }
    if (in_array($explicit, ['admin', 'adm', 'adm.conlineweb.com'], true)
        || str_starts_with($kind, 'admin_')
        || $kind === 'admin_patch'
    ) {
        return ['portal' => 'admin', 'portal_label' => 'adm.conlineweb.com'];
    }

    $path = str_replace('\\', '/', (string) (
        $item['target_key'] ?? ($item['after_raw']['path'] ?? ($item['after']['path'] ?? ''))
    ));
    $url = strtolower((string) ($item['url'] ?? ($item['target_url'] ?? '')));
    if (str_contains($url, 'cliente.conlineweb.com')) {
        return ['portal' => 'cliente', 'portal_label' => 'cliente.conlineweb.com'];
    }
    $adminHints = [
        'analytics/',
        'includes/cw_seo_',
        'includes/cw_site_ai_',
        'includes/seo_module',
        'adm.conlineweb.com',
    ];
    foreach ($adminHints as $h) {
        if ($path !== '' && str_contains(strtolower($path), $h)) {
            return ['portal' => 'admin', 'portal_label' => 'adm.conlineweb.com'];
        }
        if ($url !== '' && str_contains($url, $h)) {
            return ['portal' => 'admin', 'portal_label' => 'adm.conlineweb.com'];
        }
    }

    // Hallazgos/autofix del checklist = sitio público
    return ['portal' => 'site', 'portal_label' => 'conlineweb.com'];
}

/**
 * Tipo de acción para DataTable: Nuevo · Actualización · Mejora · Mantenimiento · Rediseño · Corrección.
 *
 * @param array<string,mixed> $item
 */
function cw_seo_mexico_monitor_type_label(array $item): string
{
    $kind = (string) ($item['kind'] ?? '');
    $ct = (string) ($item['content_type'] ?? '');
    $source = (string) ($item['source'] ?? '');
    $isNew = !empty($item['is_new_content']);

    if ($kind === 'external_task' || $kind === 'external_rec' || $ct === 'external') {
        return $kind === 'external_rec' ? 'Recomendación' : 'Tarea externa';
    }
    if ($kind === 'short_url_commercial') {
        return 'Landing nueva';
    }
    if ($source === 'audit' || $source === 'autofix' || $kind === 'audit_fix') {
        return 'Corrección';
    }
    if ($kind === 'design_ui' || $ct === 'design') {
        return 'Rediseño';
    }
    if ($kind === 'admin_validate' || strtolower((string) (
        $item['after_raw']['change_type'] ?? ($item['after']['change_type'] ?? '')
    )) === 'validacion') {
        return 'Validación';
    }
    if ($kind === 'site_patch' || $kind === 'admin_patch' || $kind === 'cliente_patch' || $ct === 'fix') {
        $change = strtolower((string) (
            $item['after_raw']['change_type'] ?? ($item['after']['change_type'] ?? '')
        ));
        if (in_array($change, ['improve', 'mejora', 'enhancement', 'ux'], true)) {
            return 'Mejora';
        }
        if (in_array($change, ['update', 'actualizacion', 'actualización', 'copy'], true)) {
            return 'Actualización';
        }
        if ($kind === 'admin_patch' || $kind === 'cliente_patch') {
            return 'Mejora';
        }
        return 'Mantenimiento';
    }
    if ($kind === 'blog_improve') {
        return 'Mejora';
    }
    if ($kind === 'hub_text') {
        return 'Actualización';
    }
    if ($isNew || $kind === 'blog_post') {
        return 'Nuevo';
    }
    if ($ct === 'page') {
        return $isNew ? 'Nuevo' : 'Actualización';
    }
    if ($ct === 'blog') {
        return $isNew ? 'Nuevo' : 'Mejora';
    }
    return (string) ($item['content_label'] ?? 'IA');
}

/**
 * Subtipo legible (página, blog, etc.) para modal / detalle.
 *
 * @param array<string,mixed> $item
 */
function cw_seo_mexico_monitor_subtype_label(array $item): string
{
    $kind = (string) ($item['kind'] ?? '');
    $ct = (string) ($item['content_type'] ?? '');
    $isNew = !empty($item['is_new_content']);
    if ($kind === 'design_ui' || $ct === 'design') {
        return 'UI / diseño';
    }
    if ($ct === 'page') {
        return $isNew ? 'Página' : 'Página existente';
    }
    if ($ct === 'blog') {
        return $isNew ? 'Artículo' : 'Artículo existente';
    }
    if ($kind === 'external_task' || $kind === 'external_rec' || $ct === 'external') {
        return 'SEO/GEO externo';
    }
    if ($ct === 'fix' || $kind === 'site_patch' || $kind === 'admin_patch') {
        return 'Código';
    }
    if (($item['source'] ?? '') === 'audit') {
        return 'Hallazgo';
    }
    return (string) ($item['content_label'] ?? '');
}

/**
 * @return array{
 *   pending:int,working:int,done:int,failed:int,total:int,
 *   pages_pending:int,pages_done:int,blogs_pending:int,blogs_done:int
 * }
 */
function cw_seo_mexico_monitor_stats(array $items): array
{
    $stats = [
        'pending' => 0,
        'working' => 0,
        'done' => 0,
        'failed' => 0,
        'total' => count($items),
        'pages_pending' => 0,
        'pages_done' => 0,
        'blogs_pending' => 0,
        'blogs_done' => 0,
    ];
    foreach ($items as $it) {
        $st = (string) ($it['work_status'] ?? 'pending');
        if (isset($stats[$st])) {
            $stats[$st]++;
        }
        $ct = (string) ($it['content_type'] ?? '');
        if ($ct === 'page') {
            if (in_array($st, ['pending', 'working'], true)) {
                $stats['pages_pending']++;
            } elseif ($st === 'done') {
                $stats['pages_done']++;
            }
        } elseif ($ct === 'blog') {
            if (in_array($st, ['pending', 'working'], true)) {
                $stats['blogs_pending']++;
            } elseif ($st === 'done') {
                $stats['blogs_done']++;
            }
        }
    }
    return $stats;
}

/**
 * ¿Es propuesta del plan rehab blog (manual por fechas), no de autonomía IA?
 *
 * @param array<string,mixed> $proposalOrItem
 */
function cw_seo_mexico_monitor_is_rehab(array $proposalOrItem): bool
{
    $targetKey = (string) ($proposalOrItem['target_key'] ?? '');
    if (str_starts_with($targetKey, 'rehab:')) {
        return true;
    }
    $after = $proposalOrItem['after'] ?? $proposalOrItem['after_raw'] ?? null;
    if (is_string($after)) {
        $decoded = json_decode($after, true);
        $after = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($after)) {
        $after = [];
    }
    if (!empty($after['manual_ai']) || (($after['source'] ?? '') === 'rehab-queue')) {
        return true;
    }
    if (!empty($after['rehab_id']) || !empty($after['scheduled_for'])) {
        // Solo si el título/clave indica rehab (evita falsos positivos en otros kinds)
        $title = (string) ($proposalOrItem['title'] ?? '');
        if (str_starts_with($title, 'Rehab blog') || str_starts_with($targetKey, 'rehab:')) {
            return true;
        }
    }
    $before = $proposalOrItem['before'] ?? $proposalOrItem['before_raw'] ?? null;
    if (is_string($before)) {
        $decoded = json_decode($before, true);
        $before = is_array($decoded) ? $decoded : [];
    }
    if (is_array($before) && (($before['source'] ?? '') === 'rehab-queue')) {
        return true;
    }
    return false;
}

/**
 * Feed unificado para el monitor.
 *
 * @param array{status?:string,source?:string,content?:string,queue?:string,limit?:int} $opts
 * @return list<array<string,mixed>>
 */
function cw_seo_mexico_monitor_feed(mysqli $conn, array $opts = []): array
{
    $limit = max(10, min(800, (int) ($opts['limit'] ?? 80)));
    $filterStatus = preg_replace('/[^a-z_]/', '', strtolower((string) ($opts['status'] ?? 'all'))) ?? 'all';
    $filterSource = preg_replace('/[^a-z_]/', '', strtolower((string) ($opts['source'] ?? 'all'))) ?? 'all';
    $filterContent = preg_replace('/[^a-z_]/', '', strtolower((string) ($opts['content'] ?? 'all'))) ?? 'all';
    $filterQueue = preg_replace('/[^a-z_]/', '', strtolower((string) ($opts['queue'] ?? 'all'))) ?? 'all';
    if (!in_array($filterQueue, ['all', 'auto', 'rehab'], true)) {
        $filterQueue = 'all';
    }
    $items = [];

    // 1) Propuestas IA (páginas/hubs + blogs nuevos)
    if ($filterSource === 'all' || $filterSource === 'ai' || $filterSource === 'ai_page' || $filterSource === 'ai_blog') {
        foreach (cw_seo_mexico_ai_list_proposals_any($conn, $limit, 'all') as $p) {
            $st = (string) ($p['status'] ?? 'pending');
            $work = 'pending';
            if ($st === 'applied') {
                $work = 'done';
            } elseif ($st === 'rejected') {
                $work = 'failed';
            } elseif ($st === 'working' || $st === 'processing') {
                $work = 'working';
            } elseif ($st === 'scheduled') {
                $work = 'scheduled';
            }
            $kind = (string) ($p['kind'] ?? '');
            $cls = cw_seo_mexico_monitor_classify_ai($kind);
            if ($filterSource === 'ai_page' && $cls['content_type'] !== 'page') {
                continue;
            }
            if ($filterSource === 'ai_blog' && $cls['content_type'] !== 'blog') {
                continue;
            }
            $files = [];
            if ($st === 'applied' && !empty($p['apply_log'])) {
                if (preg_match('/Archivos:\s*(.+)$/u', (string) $p['apply_log'], $m)) {
                    $files = array_map('trim', explode(',', $m[1]));
                }
            }
            $afterRaw = is_array($p['after'] ?? null) ? $p['after'] : [];
            $detailText = trim((string) ($p['detail'] ?? ''));
            if ($detailText === '' && !empty($afterRaw['rationale'])) {
                $detailText = (string) $afterRaw['rationale'];
            }
            if ($detailText === '') {
                $detailText = (string) ($p['prompt_summary'] ?? '');
            }
            $execBefore = is_array($p['executed_before'] ?? null) ? $p['executed_before'] : [];
            $execAfter = is_array($p['executed_after'] ?? null) ? $p['executed_after'] : [];
            $urlMeta = cw_seo_mexico_ai_resolve_public_url(
                $kind,
                (string) ($p['target_key'] ?? ''),
                (string) ($p['target_url'] ?? ''),
                $afterRaw
            );
            $pageUrl = (string) ($urlMeta['url'] ?? $p['target_url'] ?? $afterRaw['url'] ?? '');
            $isRehab = cw_seo_mexico_monitor_is_rehab([
                'target_key' => (string) ($p['target_key'] ?? ''),
                'title' => (string) ($p['title'] ?? ''),
                'after' => $afterRaw,
                'before' => is_array($p['before'] ?? null) ? $p['before'] : [],
            ]);
            $items[] = [
                'id' => 'ai-' . (int) $p['id'],
                'source' => 'ai',
                'source_id' => (int) $p['id'],
                'kind' => $kind,
                'queue' => $isRehab ? 'rehab' : 'auto',
                'target_key' => (string) ($p['target_key'] ?? ''),
                'content_type' => $cls['content_type'],
                'content_label' => $cls['content_label'],
                'is_new_content' => $cls['is_new_content'],
                'module' => $isRehab ? 'Rehab blog · plan por fechas' : $cls['module'],
                'title' => (string) ($p['title'] ?? 'Propuesta IA'),
                'work_status' => $work,
                'work_label' => $isRehab && $work === 'pending'
                    ? 'Habilitada'
                    : ($isRehab && $work === 'scheduled' ? 'Programada' : cw_seo_mexico_monitor_status_label($work)),
                'url' => $pageUrl,
                'url_label' => (string) ($urlMeta['url_label'] ?? ($afterRaw['url_label'] ?? 'URL destino')),
                'url_kind' => (string) ($urlMeta['url_kind'] ?? ($afterRaw['url_kind'] ?? 'page')),
                'before' => cw_seo_mexico_monitor_summarize($p['before'] ?? []),
                'after' => cw_seo_mexico_monitor_summarize($afterRaw),
                'before_raw' => $p['before'] ?? [],
                'after_raw' => $afterRaw,
                'executed_before' => $execBefore !== [] ? cw_seo_mexico_monitor_summarize($execBefore, 320) : '',
                'executed_after' => $execAfter !== [] ? cw_seo_mexico_monitor_summarize($execAfter, 320) : '',
                'executed_before_raw' => $execBefore,
                'executed_after_raw' => $execAfter,
                'preview_html' => (string) ($afterRaw['html'] ?? ''),
                'requires_preview' => in_array($work, ['pending', 'working'], true),
                'files' => $files,
                'detail' => $detailText,
                'rationale' => (string) ($afterRaw['rationale'] ?? ''),
                'prompt_summary' => (string) ($p['prompt_summary'] ?? ''),
                'apply_log' => (string) ($p['apply_log'] ?? ''),
                'created_at' => (string) ($p['created_at'] ?? ''),
                'applied_at' => (string) ($p['applied_at'] ?? ''),
                'finished_at' => (string) ($p['applied_at'] ?? ''),
                'ok' => $work === 'done',
                'scheduled_for' => (string) ($afterRaw['scheduled_for'] ?? ''),
            ];
            $last = array_key_last($items);
            $portal = cw_seo_mexico_monitor_portal($items[$last]);
            $items[$last]['portal'] = $portal['portal'];
            $items[$last]['portal_label'] = $portal['portal_label'];
            $items[$last]['type_label'] = $isRehab ? 'Rehab blog' : cw_seo_mexico_monitor_type_label($items[$last]);
            $items[$last]['subtype_label'] = $isRehab
                ? ('Programado ' . (($afterRaw['scheduled_for'] ?? '') !== '' ? (string) $afterRaw['scheduled_for'] : '—') . ' · IA manual')
                : cw_seo_mexico_monitor_subtype_label($items[$last]);
        }
    }

    // 2) Hallazgos abiertos (pendientes / en trabajo) — solo cola automática
    if ($filterQueue !== 'rehab' && ($filterSource === 'all' || $filterSource === 'audit')) {
        $open = cw_seo_mexico_audit_open_findings($conn, min(60, $limit));
        foreach ($open as $f) {
            $auto = !empty($f['auto_fixable']) && empty($f['auto_applied']);
            $work = $auto ? 'pending' : 'working';
            $items[] = [
                'id' => 'finding-' . (int) ($f['id'] ?? 0),
                'source' => 'audit',
                'source_id' => (int) ($f['id'] ?? 0),
                'kind' => (string) ($f['check_type'] ?? 'finding'),
                'queue' => 'auto',
                'content_type' => 'fix',
                'content_label' => 'Corrección',
                'is_new_content' => false,
                'module' => 'Auditoría · corrección',
                'title' => (string) ($f['title'] ?? 'Hallazgo'),
                'work_status' => $work,
                'work_label' => cw_seo_mexico_monitor_status_label($work),
                'url' => (string) ($f['url'] ?? ''),
                'before' => (string) ($f['evidence'] ?? 'Detectado en auditoría'),
                'after' => (string) ($f['correction'] ?? 'Pendiente de aplicar'),
                'before_raw' => ['evidence' => $f['evidence'] ?? ''],
                'after_raw' => ['correction' => $f['correction'] ?? ''],
                'preview_html' => '',
                'requires_preview' => false,
                'files' => [],
                'detail' => (string) ($f['task_key'] ?? ''),
                'apply_log' => '',
                'created_at' => (string) ($f['created_at'] ?? ''),
                'finished_at' => '',
                'ok' => false,
                'auto_fixable' => $auto,
            ];
            $last = array_key_last($items);
            $portal = cw_seo_mexico_monitor_portal($items[$last]);
            $items[$last]['portal'] = $portal['portal'];
            $items[$last]['portal_label'] = $portal['portal_label'];
            $items[$last]['type_label'] = cw_seo_mexico_monitor_type_label($items[$last]);
            $items[$last]['subtype_label'] = cw_seo_mexico_monitor_subtype_label($items[$last]);
        }
    }

    // 3) Log AutoFix (terminados en servidor) — solo cola automática
    if ($filterQueue !== 'rehab' && ($filterSource === 'all' || $filterSource === 'autofix')) {
        foreach (cw_seo_mexico_autofix_recent($conn, min(60, $limit)) as $fx) {
            $detailRaw = (string) ($fx['detail'] ?? '');
            $report = json_decode($detailRaw, true);
            $url = '';
            $before = $detailRaw;
            $after = '';
            if (is_array($report) && !empty($report['report'])) {
                $url = (string) ($report['url'] ?? '');
                $before = (string) ($report['before'] ?? $before);
                $after = (string) ($report['after'] ?? '');
            }
            if ($url === '' && str_starts_with((string) ($fx['rel_path'] ?? ''), 'http')) {
                $url = (string) $fx['rel_path'];
            }
            $ok = !empty($fx['ok']);
            $work = $ok ? 'done' : 'failed';
            $rel = (string) ($fx['rel_path'] ?? '');
            $act = (string) ($fx['action'] ?? '');
            $isAiFix = str_starts_with($act, 'ai_');
            $ct = $isAiFix
                ? (str_contains($act, 'blog') ? 'blog' : (str_contains($act, 'hub') ? 'page' : 'fix'))
                : 'fix';
            $items[] = [
                'id' => 'autofix-' . (int) ($fx['id'] ?? 0),
                'source' => 'autofix',
                'source_id' => (int) ($fx['id'] ?? 0),
                'kind' => $act,
                'queue' => 'auto',
                'content_type' => $ct,
                'content_label' => $ct === 'blog' ? 'Blog (AutoFix)' : ($ct === 'page' ? 'Página (AutoFix)' : 'Corrección'),
                'is_new_content' => $isAiFix,
                'module' => 'AutoFix · código en cPanel',
                'title' => 'AutoFix · ' . ($act !== '' ? $act : 'corrección'),
                'work_status' => $work,
                'work_label' => cw_seo_mexico_monitor_status_label($work),
                'url' => $url !== '' ? $url : ($rel !== '' ? 'file://' . $rel : ''),
                'before' => cw_seo_mexico_monitor_summarize($before),
                'after' => cw_seo_mexico_monitor_summarize($after !== '' ? $after : ($ok ? 'Aplicado en ' . $rel : $detailRaw)),
                'before_raw' => is_array($report) ? ($report['before'] ?? $before) : $before,
                'after_raw' => is_array($report) ? ($report['after'] ?? $after) : $after,
                'preview_html' => '',
                'requires_preview' => false,
                'files' => $rel !== '' && !str_starts_with($rel, 'http') ? [$rel] : [],
                'detail' => (string) ($fx['finding_key'] ?? ''),
                'apply_log' => $detailRaw,
                'created_at' => (string) ($fx['created_at'] ?? ''),
                'finished_at' => (string) ($fx['created_at'] ?? ''),
                'ok' => $ok,
            ];
            $last = array_key_last($items);
            $portal = cw_seo_mexico_monitor_portal($items[$last]);
            $items[$last]['portal'] = $portal['portal'];
            $items[$last]['portal_label'] = $portal['portal_label'];
            $items[$last]['type_label'] = cw_seo_mexico_monitor_type_label($items[$last]);
            $items[$last]['subtype_label'] = cw_seo_mexico_monitor_subtype_label($items[$last]);
        }
    }

    usort($items, static function (array $a, array $b): int {
        $ta = (string) ($a['created_at'] ?? '');
        $tb = (string) ($b['created_at'] ?? '');
        return strcmp($tb, $ta);
    });

    if ($filterStatus !== 'all' && $filterStatus !== '') {
        $items = array_values(array_filter(
            $items,
            static fn ($it) => ($it['work_status'] ?? '') === $filterStatus
        ));
    }
    if ($filterContent !== 'all' && $filterContent !== '') {
        $items = array_values(array_filter(
            $items,
            static function ($it) use ($filterContent): bool {
                $ct = (string) ($it['content_type'] ?? '');
                $isNew = !empty($it['is_new_content']);
                return match ($filterContent) {
                    'page_new' => $ct === 'page' && $isNew,
                    'page_updated' => $ct === 'page' && !$isNew,
                    'blog_new' => $ct === 'blog' && $isNew,
                    'blog_updated' => $ct === 'blog' && !$isNew,
                    default => $ct === $filterContent,
                };
            }
        ));
    }
    if ($filterQueue === 'rehab' || $filterQueue === 'auto') {
        $items = array_values(array_filter(
            $items,
            static function ($it) use ($filterQueue): bool {
                $q = (string) ($it['queue'] ?? 'auto');
                return $q === $filterQueue;
            }
        ));
    }

    return array_slice($items, 0, $limit);
}

/**
 * Detalle completo de propuesta IA para vista previa (antes de implementar).
 *
 * @return array{ok:bool,proposal?:array<string,mixed>,error?:string}
 */
function cw_seo_mexico_monitor_get_proposal(mysqli $conn, int $proposalId): array
{
    $row = cw_seo_mexico_ai_get_proposal($conn, $proposalId);
    if ($row === null) {
        return ['ok' => false, 'error' => 'Propuesta no encontrada'];
    }
    $kind = (string) ($row['kind'] ?? '');
    $cls = cw_seo_mexico_monitor_classify_ai($kind);
    $st = (string) ($row['status'] ?? 'pending');
    $work = match ($st) {
        'applied' => 'done',
        'rejected' => 'failed',
        'working', 'processing' => 'working',
        default => 'pending',
    };
    $afterRow = is_array($row['after'] ?? null) ? $row['after'] : [];
    $urlMeta = cw_seo_mexico_ai_resolve_public_url(
        $kind,
        (string) ($row['target_key'] ?? ''),
        (string) ($row['target_url'] ?? ''),
        $afterRow
    );
    $proposalForLabel = [
        'kind' => $kind,
        'content_type' => $cls['content_type'],
        'content_label' => $cls['content_label'],
        'is_new_content' => $cls['is_new_content'],
        'target_key' => (string) ($row['target_key'] ?? ''),
        'url' => (string) ($urlMeta['url'] ?? $row['target_url'] ?? ''),
        'after' => $afterRow,
        'after_raw' => $afterRow,
        'source' => 'ai',
    ];
    $portal = cw_seo_mexico_monitor_portal($proposalForLabel);

    return [
        'ok' => true,
        'proposal' => [
            'id' => (int) ($row['id'] ?? 0),
            'kind' => $kind,
            'content_type' => $cls['content_type'],
            'content_label' => $cls['content_label'],
            'type_label' => cw_seo_mexico_monitor_type_label($proposalForLabel),
            'subtype_label' => cw_seo_mexico_monitor_subtype_label($proposalForLabel),
            'portal' => $portal['portal'],
            'portal_label' => $portal['portal_label'],
            'is_new_content' => $cls['is_new_content'],
            'module' => $cls['module'],
            'title' => (string) ($row['title'] ?? ''),
            'status' => $st,
            'work_status' => $work,
            'work_label' => cw_seo_mexico_monitor_status_label($work),
            'url' => (string) ($urlMeta['url'] ?? $row['target_url'] ?? ''),
            'url_label' => (string) ($urlMeta['url_label'] ?? 'URL destino'),
            'url_kind' => (string) ($urlMeta['url_kind'] ?? 'page'),
            'target_key' => (string) ($row['target_key'] ?? ''),
            'queue' => cw_seo_mexico_monitor_is_rehab([
                'target_key' => (string) ($row['target_key'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'after' => $afterRow,
                'before' => is_array($row['before'] ?? null) ? $row['before'] : [],
            ]) ? 'rehab' : 'auto',
            'prompt_summary' => (string) ($row['prompt_summary'] ?? ''),
            'detail' => (string) ($row['detail'] ?? ''),
            'rationale' => (string) (($row['after']['rationale'] ?? '') ?: ''),
            'before' => $row['before'] ?? [],
            'after' => $row['after'] ?? [],
            'after_raw' => $afterRow,
            'before_summary' => cw_seo_mexico_monitor_summarize($row['before'] ?? []),
            'after_summary' => cw_seo_mexico_monitor_summarize($row['after'] ?? []),
            'executed_before' => $row['executed_before'] ?? [],
            'executed_after' => $row['executed_after'] ?? [],
            'executed_before_summary' => cw_seo_mexico_monitor_summarize($row['executed_before'] ?? []),
            'executed_after_summary' => cw_seo_mexico_monitor_summarize($row['executed_after'] ?? []),
            'preview_html' => (string) (($row['after']['html'] ?? $row['after']['preview_html'] ?? '') ?: ''),
            'preview_document' => (string) (($row['after']['preview_document'] ?? '') ?: ''),
            'reinvention_level' => (string) (($row['after']['reinvention_level'] ?? '') ?: ''),
            'design_scope' => (string) (($row['after']['scope'] ?? '') ?: ''),
            'patches_count' => is_array($row['after']['patches'] ?? null) ? count($row['after']['patches']) : 0,
            'can_apply' => in_array($st, ['pending', 'working'], true),
            'can_edit' => in_array($st, ['pending', 'working'], true) && !cw_seo_mexico_monitor_is_rehab([
                'target_key' => (string) ($row['target_key'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'after' => $afterRow,
                'before' => is_array($row['before'] ?? null) ? $row['before'] : [],
            ]),
            'clarifications' => is_array($afterRow['clarifications'] ?? null) ? $afterRow['clarifications'] : [],
            'refine_chat' => is_array($afterRow['refine_chat'] ?? null) ? $afterRow['refine_chat'] : [],
            'human_edits' => is_array($afterRow['human_edits'] ?? null) ? $afterRow['human_edits'] : [],
            'created_at' => (string) ($row['created_at'] ?? ''),
            'applied_at' => (string) ($row['applied_at'] ?? ''),
            'apply_log' => (string) ($row['apply_log'] ?? ''),
        ],
    ];
}

function cw_seo_mexico_monitor_status_label(string $status): string
{
    return match ($status) {
        'pending' => 'Pendiente',
        'working' => 'En revisión',
        'done' => 'Terminado',
        'failed' => 'Rechazado',
        'scheduled' => 'Programada',
        default => $status,
    };
}

/**
 * Marca propuesta IA como «en trabajo» (alguien la está revisando/aplicando).
 */
function cw_seo_mexico_monitor_set_ai_working(mysqli $conn, int $proposalId, int $userId = 0): array
{
    cw_seo_mexico_ai_proposals_ensure_table($conn);
    // Asegurar columna status admite working (VARCHAR ya)
    $id = max(0, $proposalId);
    if ($id < 1) {
        return ['ok' => false, 'error' => 'ID inválido'];
    }
    $stmt = $conn->prepare(
        "UPDATE cw_seo_mexico_ai_proposals
         SET status = 'working'
         WHERE id = ? AND status = 'pending'"
    );
    if (!$stmt) {
        return ['ok' => false, 'error' => 'No se pudo actualizar'];
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $n = $stmt->affected_rows;
    $stmt->close();
    if ($n < 1) {
        return ['ok' => false, 'error' => 'Solo propuestas pendientes pasan a «en trabajo»'];
    }
    return ['ok' => true, 'message' => 'Marcada en trabajo', 'proposal_id' => $id];
}
