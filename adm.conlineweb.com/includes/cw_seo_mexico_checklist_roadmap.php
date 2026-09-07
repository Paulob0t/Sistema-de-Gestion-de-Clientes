<?php
/**
 * Roadmap SEO/GEO — clasificación implementado / corrección / nueva / futura.
 */

/**
 * @return list<string>
 */
function cw_seo_mexico_roadmap_categories(): array
{
    return [
        'SEO Técnico',
        'Arquitectura SEO',
        'Contenido SEO',
        'GEO / Inteligencia Artificial',
        'SEO Local México',
        'Rendimiento',
    ];
}

/**
 * @return array<string, string>
 */
function cw_seo_mexico_roadmap_category_map(): array
{
    return [
        'plazas_prioridad' => 'SEO Local México',
        'gsc_baseline' => 'SEO Técnico',
        'analytics_leads_geo' => 'SEO Local México',
        'kpi_90d' => 'SEO Técnico',
        'rewrite_hubs_plazas' => 'Contenido SEO',
        'rewrite_servicio_geo' => 'Contenido SEO',
        'casos_locales' => 'SEO Local México',
        'blog_internlink' => 'Arquitectura SEO',
        'sitemap_completo' => 'SEO Técnico',
        'canibalizacion' => 'Contenido SEO',
        'short_urls_fase1' => 'SEO Local México',
        'short_urls_fase2' => 'SEO Local México',
        'short_urls_fase3' => 'SEO Local México',
        'short_urls_fase4' => 'SEO Local México',
        'cwv_mobile' => 'Rendimiento',
        'marca_consistente' => 'SEO Local México',
        'gbp_optimizado' => 'SEO Local México',
        'reseñas_google' => 'SEO Local México',
        'citaciones_locales' => 'SEO Local México',
        'contenido_geo_ia' => 'GEO / Inteligencia Artificial',
        'audit_live_engine' => 'SEO Técnico',
        'title' => 'SEO Técnico',
        'meta_description' => 'SEO Técnico',
        'h1' => 'SEO Técnico',
        'canonical' => 'SEO Técnico',
        'robots' => 'SEO Técnico',
        'robots_http' => 'SEO Técnico',
        'sitemap_http' => 'SEO Técnico',
        'schema' => 'GEO / Inteligencia Artificial',
        'og_tags' => 'SEO Técnico',
        'thin_content' => 'Contenido SEO',
        'nap_live' => 'SEO Local México',
        'http_status' => 'SEO Técnico',
        'img_alt' => 'SEO Técnico',
        'whatsapp_cta' => 'Contenido SEO',
        'hreflang' => 'SEO Técnico',
        'redirects' => 'SEO Técnico',
        'cwv' => 'Rendimiento',
    ];
}

/**
 * @return list<string>
 */
function cw_seo_mexico_roadmap_audit_check_types(): array
{
    return [
        'title', 'meta_description', 'h1', 'canonical', 'robots', 'schema',
        'og_tags', 'thin_content', 'nap_live', 'http_status', 'img_alt',
        'whatsapp_cta', 'sitemap_http', 'robots_http',
    ];
}

/**
 * @return list<string>
 */
function cw_seo_mexico_roadmap_future_task_keys(): array
{
    return [
        'contenido_geo_ia', 'gbp_optimizado', 'reseñas_google', 'citaciones_locales',
        'casos_locales',
    ];
}

/**
 * @return list<string>
 */
function cw_seo_mexico_roadmap_new_implementation_checks(): array
{
    return [
        'schema', 'sitemap_http', 'robots_http', 'hreflang', 'faq_schema',
    ];
}

function cw_seo_mexico_roadmap_resolve_category(string $taskKey, string $checkType = ''): string
{
    $map = cw_seo_mexico_roadmap_category_map();
    if ($checkType !== '' && isset($map[$checkType])) {
        return $map[$checkType];
    }
    $base = preg_replace('/^(audit_|external_|ext_|ms_)/', '', $taskKey) ?? $taskKey;
    if (isset($map[$base])) {
        return $map[$base];
    }
    if (str_starts_with($taskKey, 'audit_') || str_starts_with($taskKey, 'ms_')) {
        return 'SEO Técnico';
    }
    if (str_starts_with($taskKey, 'external_') || str_starts_with($taskKey, 'ext_')) {
        return 'SEO Local México';
    }
    if (str_contains($taskKey, 'blog') || str_contains($taskKey, 'content') || str_contains($taskKey, 'rewrite')) {
        return 'Contenido SEO';
    }
    if (str_contains($taskKey, 'cwv') || str_contains($taskKey, 'perf')) {
        return 'Rendimiento';
    }
    if (str_contains($taskKey, 'geo') || str_contains($taskKey, 'ia') || str_contains($taskKey, 'schema')) {
        return 'GEO / Inteligencia Artificial';
    }
    return 'SEO Técnico';
}

function cw_seo_mexico_roadmap_severity_priority(string $severity): string
{
    return match (strtolower($severity)) {
        'critical' => 'Crítica',
        'high' => 'Alta',
        'medium' => 'Media',
        'low' => 'Baja',
        default => 'Media',
    };
}

function cw_seo_mexico_roadmap_status_label(string $status): string
{
    return match ($status) {
        'implemented' => '✅ Implementado',
        'correction' => '🟡 Corrección requerida',
        'new' => '❌ Nueva implementación requerida',
        'future' => '🔮 Mejora futura',
        default => $status,
    };
}

/**
 * @param array<string,mixed> $row
 * @param array<string,int> $openByCheckType
 */
function cw_seo_mexico_roadmap_classify_task(array $row, array $openByCheckType = []): array
{
    $key = (string) ($row['task_key'] ?? '');
    $done = !empty($row['done']);
    $desc = (string) ($row['description'] ?? '');
    $corr = (string) ($row['correction'] ?? '');
    $detail = is_array($row['detail'] ?? null) ? $row['detail'] : [];
    $checkType = (string) ($detail['check_type'] ?? '');
    if ($checkType === '' && str_starts_with($key, 'audit_')) {
        $checkType = preg_replace('/^audit_/', '', $key) ?? '';
    }
    $category = cw_seo_mexico_roadmap_resolve_category($key, $checkType);
    $urls = is_array($detail['urls'] ?? null) ? $detail['urls'] : [];
    $severity = (string) ($detail['severity'] ?? 'medium');
    $isDynamic = !empty($row['is_dynamic']) || str_starts_with($key, 'audit_') || str_starts_with($key, 'external_');
    $isPartial = str_contains(strtolower($corr), 'parcial')
        || str_contains(strtolower($corr), 'aún falt')
        || str_contains(strtolower($corr), 'faltan')
        || (($detail['status'] ?? '') === 'parcial');
    $isFutureKey = in_array($key, cw_seo_mexico_roadmap_future_task_keys(), true)
        && !$done
        && !$isPartial;

    $roadmapStatus = 'new';
    $actionType = 'Nueva implementación';

    if ($done) {
        $roadmapStatus = 'implemented';
        $actionType = 'Validación';
    } elseif ($isPartial) {
        $roadmapStatus = 'correction';
        $actionType = 'Corrección';
    } elseif ($isFutureKey) {
        $roadmapStatus = 'future';
        $actionType = 'Optimización';
    } elseif ($isDynamic && !$done) {
        $openCount = $checkType !== '' ? (int) ($openByCheckType[$checkType] ?? 0) : 0;
        $isMissing = in_array($checkType, cw_seo_mexico_roadmap_new_implementation_checks(), true)
            || str_contains(strtolower($desc . $corr), 'sin json-ld')
            || str_contains(strtolower($desc . $corr), 'no existe')
            || str_contains(strtolower($desc . $corr), 'ausente')
            || str_contains(strtolower($desc . $corr), 'falta ');
        if ($isMissing && $openCount > 0) {
            $roadmapStatus = 'new';
            $actionType = 'Nueva implementación';
        } else {
            $roadmapStatus = 'correction';
            $actionType = 'Corrección';
        }
    } elseif ($corr !== '' && !$done) {
        $roadmapStatus = 'correction';
        $actionType = 'Corrección';
    }

    $current = $done
        ? ($corr !== '' ? $corr : 'Implementado y verificado en el checklist.')
        : ($desc !== '' ? $desc : 'Pendiente de ejecutar.');
    $problem = $done ? '' : ($isPartial
        ? 'Implementación parcial detectada.'
        : ($isDynamic ? 'Hallazgos abiertos en la última auditoría.' : 'Tarea del plan SEO/GEO sin completar.'));
    $solution = $done ? '' : ((string) ($row['correction'] ?? $detail['user_correction'] ?? 'Ejecutar según descripción del checklist.'));

    $impactSeo = in_array($category, ['SEO Técnico', 'Arquitectura SEO', 'Contenido SEO'], true) ? 'Alto' : 'Medio';
    $impactGeo = in_array($category, ['GEO / Inteligencia Artificial', 'SEO Local México'], true) ? 'Alto' : 'Medio';
    if ($roadmapStatus === 'implemented') {
        $impactSeo = '—';
        $impactGeo = '—';
    }

    $plan = is_array($row['plan'] ?? null) ? $row['plan'] : (is_array($detail['plan'] ?? null) ? $detail['plan'] : []);
    $effort = trim((string) ($plan['effort'] ?? ''));
    if ($effort === '') {
        $effort = match ($actionType) {
            'Corrección' => '2–8 horas',
            'Nueva implementación' => '1–3 días',
            'Optimización' => '1–2 semanas',
            'Validación' => '30 min',
            default => 'Variable',
        };
    }

    return [
        'id' => $key,
        'task_key' => $key,
        'action_type' => $actionType,
        'roadmap_status' => $roadmapStatus,
        'roadmap_label' => cw_seo_mexico_roadmap_status_label($roadmapStatus),
        'category' => $category,
        'name' => (string) ($row['title'] ?? $key),
        'description' => $desc,
        'current_state' => $current,
        'problem' => $problem,
        'solution' => $solution,
        'priority' => $done ? '—' : cw_seo_mexico_roadmap_severity_priority($severity),
        'impact_seo' => $impactSeo,
        'impact_geo' => $impactGeo,
        'urls' => array_slice($urls, 0, 12),
        'estimated_time' => $effort,
        'dependencies' => [],
        'status' => $done ? 'done' : 'pending',
        'done' => $done,
        'check_type' => $checkType,
        'phase_label' => (string) ($row['phase_label'] ?? ''),
    ];
}

/**
 * @param list<array<string,mixed>> $rows
 * @return array<string, list<array<string,mixed>>>
 */
function cw_seo_mexico_roadmap_group(array $rows, mysqli $conn, ?array $auditLastRun = null): array
{
    $openByCheckType = [];
    $res = $conn->query(
        "SELECT check_type, COUNT(*) AS c FROM cw_seo_mexico_audit_findings
         WHERE status = 'open' GROUP BY check_type"
    );
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $openByCheckType[(string) $r['check_type']] = (int) $r['c'];
        }
        $res->free();
    }

    $groups = [
        'implemented' => [],
        'correction' => [],
        'new' => [],
        'future' => [],
    ];
    $seenKeys = [];

    foreach ($rows as $row) {
        $item = cw_seo_mexico_roadmap_classify_task($row, $openByCheckType);
        $key = (string) $item['task_key'];
        if ($key === 'audit_live_engine') {
            continue;
        }
        $seenKeys[$key] = true;
        $st = (string) $item['roadmap_status'];
        if (!isset($groups[$st])) {
            $st = 'new';
        }
        $groups[$st][] = $item;
    }

    // Checks de auditoría sin hallazgos abiertos → implementado (solo tras corrida real)
    $hasAudit = is_array($auditLastRun) && (int) ($auditLastRun['urls_total'] ?? 0) > 0;
    if ($hasAudit) {
        foreach (cw_seo_mexico_roadmap_audit_check_types() as $ct) {
        if ((int) ($openByCheckType[$ct] ?? 0) > 0) {
            continue;
        }
        $auditKey = 'audit_' . preg_replace('/[^a-z0-9_]/', '', $ct);
        if (isset($seenKeys[$auditKey])) {
            continue;
        }
        $labels = [
            'title' => 'Titles optimizados en URLs auditadas',
            'meta_description' => 'Meta descriptions en URLs auditadas',
            'h1' => 'H1 presentes en páginas auditadas',
            'canonical' => 'Canonical configurado',
            'robots' => 'Directivas robots correctas (sin noindex indebido)',
            'schema' => 'JSON-LD detectado en páginas clave',
            'og_tags' => 'Open Graph configurado',
            'thin_content' => 'Contenido suficiente en hubs/servicios auditados',
            'nap_live' => 'Señales NAP visibles en vivo',
            'http_status' => 'URLs auditadas responden HTTP OK',
            'img_alt' => 'Imágenes con ALT adecuado',
            'whatsapp_cta' => 'CTA WhatsApp detectable',
            'sitemap_http' => 'Sitemap accesible en producción',
            'robots_http' => 'robots.txt accesible en producción',
        ];
        $groups['implemented'][] = [
            'id' => 'check_ok_' . $ct,
            'task_key' => '',
            'action_type' => 'Validación',
            'roadmap_status' => 'implemented',
            'roadmap_label' => cw_seo_mexico_roadmap_status_label('implemented'),
            'category' => cw_seo_mexico_roadmap_resolve_category('', $ct),
            'name' => $labels[$ct] ?? ('Check ' . $ct . ' OK'),
            'description' => 'Sin hallazgos abiertos en la última auditoría para este control.',
            'current_state' => 'Cumple en las URLs auditadas.',
            'problem' => '',
            'solution' => '',
            'priority' => '—',
            'impact_seo' => '—',
            'impact_geo' => '—',
            'urls' => [],
            'estimated_time' => '—',
            'dependencies' => [],
            'status' => 'done',
            'done' => true,
            'check_type' => $ct,
            'phase_label' => 'Auditoría',
        ];
        }
    }

    $summary = is_array($auditLastRun['summary'] ?? null) ? $auditLastRun['summary'] : [];
    if (is_array($summary['roadmap_implemented'] ?? null)) {
        foreach ($summary['roadmap_implemented'] as $extra) {
            if (!is_array($extra)) {
                continue;
            }
            $extra['roadmap_status'] = 'implemented';
            $extra['roadmap_label'] = cw_seo_mexico_roadmap_status_label('implemented');
            $groups['implemented'][] = $extra;
        }
    }

    foreach ($groups as &$list) {
        usort($list, static function ($a, $b) {
            $prio = ['Crítica' => 0, 'Alta' => 1, 'Media' => 2, 'Baja' => 3, '—' => 9];
            $pa = $prio[(string) ($a['priority'] ?? 'Media')] ?? 5;
            $pb = $prio[(string) ($b['priority'] ?? 'Media')] ?? 5;
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }
            return strnatcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });
    }
    unset($list);

    return $groups;
}

/**
 * @param list<array<string,mixed>> $rows
 */
function cw_seo_mexico_roadmap_enrich_rows(array &$rows, mysqli $conn): void
{
    $openByCheckType = [];
    $res = $conn->query(
        "SELECT check_type, COUNT(*) AS c FROM cw_seo_mexico_audit_findings
         WHERE status = 'open' GROUP BY check_type"
    );
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $openByCheckType[(string) $r['check_type']] = (int) $r['c'];
        }
        $res->free();
    }
    foreach ($rows as &$row) {
        $row['roadmap'] = cw_seo_mexico_roadmap_classify_task($row, $openByCheckType);
    }
    unset($row);
}

/**
 * Enriquece detail de finding/tarea dinámica para el roadmap.
 *
 * @param array<string,mixed> $finding
 * @param array<string,mixed> $detail
 * @return array<string,mixed>
 */
function cw_seo_mexico_roadmap_detail_from_finding(array $finding, array $detail): array
{
    $checkType = (string) ($finding['check_type'] ?? $detail['check_type'] ?? '');
    $evidence = (string) ($finding['evidence'] ?? '');
    $correction = (string) ($finding['correction'] ?? '');
    $isMissing = in_array($checkType, cw_seo_mexico_roadmap_new_implementation_checks(), true)
        || str_contains(strtolower($evidence), 'sin ')
        || str_contains(strtolower($evidence), 'no hay')
        || str_contains(strtolower($evidence), 'falta');
    $roadmapStatus = $isMissing ? 'new' : 'correction';
    $actionType = $isMissing ? 'Nueva implementación' : 'Corrección';
    $category = cw_seo_mexico_roadmap_resolve_category('', $checkType);

    $detail['roadmap'] = [
        'action_type' => $actionType,
        'roadmap_status' => $roadmapStatus,
        'roadmap_label' => cw_seo_mexico_roadmap_status_label($roadmapStatus),
        'category' => $category,
        'current_state' => $evidence !== '' ? $evidence : 'Detectado en auditoría.',
        'problem' => (string) ($finding['title'] ?? ''),
        'solution' => $correction,
        'priority' => cw_seo_mexico_roadmap_severity_priority((string) ($finding['severity'] ?? 'medium')),
        'impact_seo' => in_array($category, ['SEO Técnico', 'Arquitectura SEO', 'Contenido SEO'], true) ? 'Alto' : 'Medio',
        'impact_geo' => in_array($category, ['GEO / Inteligencia Artificial', 'SEO Local México'], true) ? 'Alto' : 'Medio',
        'estimated_time' => $isMissing ? '1–3 días' : '2–8 horas',
    ];

    return $detail;
}

/**
 * @param list<array<string,mixed>> $items
 */
function cw_seo_mexico_roadmap_attach_to_work_feed(array &$items): void
{
    foreach ($items as &$it) {
        $key = (string) ($it['task_key'] ?? '');
        $checkType = (string) ($it['check_type'] ?? '');
        if ($key !== '' && !empty($it['done'])) {
            $it['roadmap_status'] = 'implemented';
            $it['roadmap_label'] = cw_seo_mexico_roadmap_status_label('implemented');
            $it['roadmap_category'] = cw_seo_mexico_roadmap_resolve_category($key, $checkType);
            $it['action_type'] = 'Validación';
            continue;
        }
        if (($it['source'] ?? '') === 'finding') {
            $isMissing = in_array($checkType, cw_seo_mexico_roadmap_new_implementation_checks(), true);
            $it['roadmap_status'] = $isMissing ? 'new' : 'correction';
            $it['roadmap_label'] = cw_seo_mexico_roadmap_status_label($it['roadmap_status']);
            $it['roadmap_category'] = cw_seo_mexico_roadmap_resolve_category($key, $checkType);
            $it['action_type'] = $isMissing ? 'Nueva implementación' : 'Corrección';
            $it['priority'] = cw_seo_mexico_roadmap_severity_priority((string) ($it['severity'] ?? 'medium'));
            continue;
        }
        $it['roadmap_status'] = !empty($it['done']) ? 'implemented' : 'new';
        $it['roadmap_label'] = cw_seo_mexico_roadmap_status_label($it['roadmap_status']);
        $it['roadmap_category'] = cw_seo_mexico_roadmap_resolve_category($key, $checkType);
        $it['action_type'] = !empty($it['done']) ? 'Validación' : 'Nueva implementación';
    }
    unset($it);
}

/**
 * @param list<array<string,mixed>> $items
 * @return list<array<string,mixed>>
 */
function cw_seo_mexico_roadmap_filter_actionable(array $items): array
{
    return array_values(array_filter(
        $items,
        static fn ($it) => ($it['roadmap_status'] ?? '') !== 'implemented'
            && empty($it['done'])
    ));
}

/**
 * @param array<string, list<array<string,mixed>>> $groups
 * @return array{implemented:int,correction:int,new:int,future:int,pending:int}
 */
function cw_seo_mexico_roadmap_stats(array $groups): array
{
    return [
        'implemented' => count($groups['implemented'] ?? []),
        'correction' => count($groups['correction'] ?? []),
        'new' => count($groups['new'] ?? []),
        'future' => count($groups['future'] ?? []),
        'pending' => count($groups['correction'] ?? []) + count($groups['new'] ?? []) + count($groups['future'] ?? []),
    ];
}

/**
 * Historial de correcciones terminadas: solo implementadas, AutoFix OK o tareas hechas.
 * Avances parciales y pendientes NO entran aquí (siguen en la cola).
 *
 * @param array<string,string> $titleByKey
 * @param array<string,bool> $moduleTaskKeys vacío = sin filtrar por módulo
 * @param list<array<string,mixed>> $closedFindings hallazgos ya cerrados (fixed/resolved)
 * @return list<array<string,mixed>>
 */
function cw_seo_mexico_checklist_correction_history(
    mysqli $conn,
    array $titleByKey,
    array $moduleTaskKeys,
    array $closedFindings,
    int $limit = 80
): array {
    $limit = max(10, min(120, $limit));
    $items = [];
    $seen = [];

    $push = static function (array $row) use (&$items, &$seen, $limit): void {
        if (empty($row['completed'])) {
            return;
        }
        if (count($items) >= $limit) {
            return;
        }
        $id = (string) ($row['id'] ?? '');
        if ($id === '') {
            $id = md5(json_encode([
                $row['type'] ?? '',
                $row['created_at'] ?? '',
                $row['summary'] ?? '',
            ], JSON_UNESCAPED_UNICODE));
        }
        if (isset($seen[$id])) {
            return;
        }
        $seen[$id] = true;
        $items[] = $row;
    };

    foreach (cw_seo_mexico_checklist_activity_feed($conn, $limit) as $ev) {
        $kind = (string) ($ev['kind'] ?? 'update');
        if ($kind !== 'autofix' || empty($ev['ok'])) {
            continue;
        }
        $tk = (string) ($ev['task_key'] ?? '');
        if ($moduleTaskKeys !== [] && $tk !== '' && !isset($moduleTaskKeys[$tk])) {
            continue;
        }
        $push([
            'id' => 'feed_' . md5($tk . ($ev['created_at'] ?? '') . ($ev['summary'] ?? '')),
            'type' => 'autofix',
            'type_label' => 'AutoFix aplicado',
            'title' => $titleByKey[$tk] ?? ($tk !== '' ? $tk : 'Corrección automática'),
            'summary' => (string) ($ev['summary'] ?? ''),
            'detail' => (string) ($ev['detail'] ?? ''),
            'url' => (string) ($ev['meta'] ?? ''),
            'task_key' => $tk,
            'ok' => true,
            'completed' => true,
            'created_at' => (string) ($ev['created_at'] ?? ''),
        ]);
    }

    foreach ($closedFindings as $f) {
        $status = strtolower(trim((string) ($f['status'] ?? '')));
        $auto = !empty($f['auto_applied']);
        $isDone = $auto || in_array($status, ['fixed', 'resolved'], true);
        if (!$isDone) {
            continue;
        }
        $tk = (string) ($f['task_key'] ?? '');
        if ($moduleTaskKeys !== [] && $tk !== '' && !isset($moduleTaskKeys[$tk])) {
            continue;
        }
        $correction = trim((string) ($f['correction'] ?? ''));
        $push([
            'id' => 'finding_' . (int) ($f['id'] ?? 0),
            'type' => $auto ? 'autofix' : 'correction',
            'type_label' => $auto ? 'AutoFix · hallazgo' : 'Corrección implementada',
            'title' => (string) ($f['title'] ?? 'Hallazgo'),
            'summary' => $correction !== '' ? $correction : (string) ($f['evidence'] ?? ''),
            'detail' => (string) ($f['evidence'] ?? ''),
            'url' => (string) ($f['url'] ?? ''),
            'task_key' => $tk,
            'finding_id' => (int) ($f['id'] ?? 0),
            'ok' => true,
            'completed' => true,
            'created_at' => (string) ($f['updated_at'] ?? $f['created_at'] ?? ''),
        ]);
    }

    $catalogMap = function_exists('cw_seo_mexico_checklist_catalog_map')
        ? cw_seo_mexico_checklist_catalog_map()
        : [];

    $res = $conn->query(
        "SELECT task_key, title, done_at, notes, detail_json
         FROM cw_seo_mexico_checklist
         WHERE done = 1 AND done_at IS NOT NULL AND TRIM(done_at) != ''
         ORDER BY done_at DESC
         LIMIT 40"
    );
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $tk = (string) ($r['task_key'] ?? '');
            if ($moduleTaskKeys !== [] && !isset($moduleTaskKeys[$tk])) {
                continue;
            }
            $correction = '';
            if (!empty($r['detail_json'])) {
                $decoded = json_decode((string) $r['detail_json'], true);
                if (is_array($decoded) && !empty($decoded['user_correction'])) {
                    $correction = trim((string) $decoded['user_correction']);
                }
            }
            if ($correction === '' && isset($catalogMap[$tk])) {
                $correction = trim((string) ($catalogMap[$tk]['correction'] ?? ''));
            }
            $notes = trim((string) ($r['notes'] ?? ''));
            $push([
                'id' => 'task_done_' . $tk . '_' . ($r['done_at'] ?? ''),
                'type' => 'task_done',
                'type_label' => 'Tarea terminada',
                'title' => (string) ($r['title'] ?? $titleByKey[$tk] ?? $tk),
                'summary' => $correction !== '' ? $correction : ($notes !== '' ? $notes : 'Marcada como hecha'),
                'detail' => $notes,
                'url' => '',
                'task_key' => $tk,
                'ok' => true,
                'completed' => true,
                'created_at' => (string) ($r['done_at'] ?? ''),
            ]);
        }
        $res->free();
    }

    usort($items, static fn (array $a, array $b): int => strcmp(
        (string) ($b['created_at'] ?? ''),
        (string) ($a['created_at'] ?? '')
    ));

    return array_slice($items, 0, $limit);
}
