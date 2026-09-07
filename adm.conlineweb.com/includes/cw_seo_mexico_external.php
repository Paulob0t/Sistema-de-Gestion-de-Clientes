<?php
/**
 * Análisis SEO/GEO externo: GSC, GBP/Maps, reseñas, citaciones, casos locales, medición.
 * Genera hallazgos + tareas checklist (execution=external) + propuestas en Monitor.
 * No escribe en el sitio: son acciones humanas fuera del código.
 */

/**
 * Fase checklist según tipo externo.
 */
function cw_seo_mexico_external_phase_for(string $checkType): string
{
    $t = strtolower($checkType);
    if (str_starts_with($t, 'ext_gsc_baseline') || str_starts_with($t, 'ext_analytics')) {
        return '01_fundamentos';
    }
    if (str_starts_with($t, 'ext_casos') || str_starts_with($t, 'ext_content')) {
        return '02_contenido';
    }
    if (str_starts_with($t, 'ext_gsc_') || str_starts_with($t, 'ext_panel') || str_starts_with($t, 'ext_ciudades')) {
        return '05_medicion';
    }
    return '04_autoridad';
}

/**
 * ¿Es hallazgo de trabajo externo (no código)?
 */
function cw_seo_mexico_external_is_check(string $checkType, array $finding = []): bool
{
    if (strtolower((string) ($finding['execution'] ?? '')) === 'external') {
        return true;
    }
    $t = strtolower($checkType);
    return str_starts_with($t, 'ext_') || str_starts_with($t, 'external_');
}

/**
 * Señales del run actual para priorizar recomendaciones externas.
 *
 * @return array{by_type:array<string,int>,nap_issues:int,thin:int,schema:int,title:int,open_catalog:list<string>}
 */
function cw_seo_mexico_external_signals(mysqli $conn, int $runId = 0): array
{
    $byType = [];
    $sql = "SELECT check_type, COUNT(*) AS c FROM cw_seo_mexico_audit_findings
            WHERE status = 'open'";
    if ($runId > 0) {
        $sql .= ' AND run_id = ' . (int) $runId;
    }
    $sql .= ' GROUP BY check_type';
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $byType[(string) ($row['check_type'] ?? '')] = (int) ($row['c'] ?? 0);
        }
        $res->free();
    }

    $openCatalog = [];
    $cres = $conn->query(
        "SELECT task_key FROM cw_seo_mexico_checklist WHERE done = 0
         AND task_key IN (
           'gsc_baseline','analytics_leads_geo','gbp_activo','reseñas_ritmo',
           'citaciones_enlaces','casos_locales','panel_gsc_mensual'
         )"
    );
    if ($cres) {
        while ($row = $cres->fetch_assoc()) {
            $openCatalog[] = (string) ($row['task_key'] ?? '');
        }
        $cres->free();
    }

    return [
        'by_type' => $byType,
        'nap_issues' => (int) (($byType['nap_live'] ?? 0) + ($byType['ms_nap'] ?? 0)),
        'thin' => (int) ($byType['thin_content'] ?? 0),
        'schema' => (int) ($byType['schema'] ?? 0),
        'title' => (int) ($byType['title'] ?? 0),
        'open_catalog' => $openCatalog,
    ];
}

/**
 * Genera hallazgos externos (regla + opcional IA).
 *
 * @return list<array<string,mixed>>
 */
function cw_seo_mexico_external_build_findings(mysqli $conn, int $runId = 0, array $opts = []): array
{
    require_once __DIR__ . '/cw_seo_mexico_checklist.php';

    $signals = cw_seo_mexico_external_signals($conn, $runId);
    $openCat = array_flip($signals['open_catalog']);
    $plazas = cw_seo_mexico_priority_plazas();
    $plazaNames = [];
    foreach ($plazas as $p) {
        $n = trim((string) ($p['name'] ?? $p['slug'] ?? ''));
        if ($n !== '') {
            $plazaNames[] = $n;
        }
    }
    $plazaList = implode(', ', array_slice($plazaNames, 0, 10));
    $base = 'https://conlineweb.com';
    $findings = [];

    $push = static function (
        array &$out,
        string $key,
        string $type,
        string $severity,
        string $title,
        string $evidence,
        string $correction,
        string $channel,
        string $url = 'https://conlineweb.com/'
    ): void {
        $out[] = [
            'finding_key' => $key,
            'check_type' => $type,
            'severity' => $severity,
            'url' => $url,
            'title' => $title,
            'evidence' => $evidence,
            'correction' => $correction,
            'auto_fixable' => false,
            'execution' => 'external',
            'meta' => [
                'channel' => $channel,
                'kind' => 'external_task',
                'execution' => 'external',
            ],
        ];
    };

    // 1) GSC baseline / indexación México
    if (isset($openCat['gsc_baseline']) || ($signals['title'] + $signals['thin']) >= 2) {
        $push(
            $findings,
            'ext_gsc_baseline_mx',
            'ext_gsc_baseline',
            'high',
            'SEO externo · Baseline Google Search Console (México)',
            'El análisis del sitio detectó señales on-page; falta foto inicial en GSC de /mexico/ '
            . '(impresiones, clics, páginas). Plazas: ' . $plazaList . '.',
            'En GSC: propiedad conlineweb.com → Rendimiento filtrado a páginas /mexico/ → '
            . 'exportar CSV + capturas. Registrar baseline (fecha, impresiones, clics, CTR) '
            . 'y enviar a índice el sitemap https://conlineweb.com/sitemap-index.xml si aún no está.',
            'google_search_console',
            $base . '/indice/'
        );
    }

    // 2) GBP / Maps (GEO)
    if (isset($openCat['gbp_activo']) || $signals['nap_issues'] > 0 || $signals['schema'] > 0) {
        $napNote = $signals['nap_issues'] > 0
            ? 'Hay hallazgos NAP on-site: alinear la misma NAP en Google Business Profile.'
            : 'GEO local requiere ficha Maps viva aunque el sitio esté bien.';
        $push(
            $findings,
            'ext_gbp_completo',
            'ext_gbp',
            'high',
            'GEO externo · Google Business Profile (Maps) completo y activo',
            $napNote . ' Plazas prioritarias: ' . $plazaList . '.',
            'Completar GBP: categorías (agencia/desarrollo web), servicios ConlineWeb, horarios, '
            . 'fotos reales, descripción con León/México, NAP idéntica al sitio, 1–2 publicaciones/mes. '
            . 'Verificar que el sitio web de la ficha sea https://conlineweb.com.',
            'google_business_profile',
            $base . '/contacto/'
        );
    }

    // 3) Reseñas
    if (isset($openCat['reseñas_ritmo']) || isset($openCat['gbp_activo'])) {
        $push(
            $findings,
            'ext_reviews_rhythm',
            'ext_reviews',
            'medium',
            'GEO externo · Ritmo de reseñas reales (Maps)',
            'Sin reseñas constantes, el GEO local pierde confianza frente a competencia local.',
            'Definir meta 4–8 reseñas/mes post-entrega. Proceso: pedir reseña solo a clientes satisfechos; '
            . 'responder todas en ≤72h. Prohibido comprar reseñas.',
            'google_reviews',
            $base . '/'
        );
    }

    // 4) Citaciones / backlinks
    if (isset($openCat['citaciones_enlaces'])) {
        $push(
            $findings,
            'ext_citations_block',
            'ext_citations',
            'medium',
            'SEO externo · Primer bloque de citaciones y enlaces',
            'Autoridad off-site aún pendiente en el plan 90 días (directorios, partners, menciones).',
            'Conseguir 8–15 menciones/enlaces de calidad (directorios México serios, partners, notas). '
            . 'NAP consistente. Priorizar anclas de marca + servicio (sin spam).',
            'citations_backlinks',
            $base . '/'
        );
    }

    // 5) Casos locales (contenido humano / evidencia)
    if (isset($openCat['casos_locales']) || $signals['thin'] >= 1) {
        $push(
            $findings,
            'ext_local_cases',
            'ext_casos_locales',
            'high',
            'GEO/contenido · Casos o pruebas reales por plaza top',
            ($signals['thin'] >= 1
                ? 'Hay landings con contenido delgado; faltan pruebas locales. '
                : 'Checklist externo pendiente. ')
            . 'Plazas: ' . $plazaList . '.',
            'Publicar ≥1 caso/evidencia real por plaza prioritaria faltante (no inventar). '
            . 'Fuente: entregas reales, captura autorizada o testimonio. Priorizar Querétaro, Puebla, '
            . 'Mérida, Tijuana, Cancún, Toluca si aún faltan.',
            'local_proof',
            $base . '/mexico/ciudades/'
        );
    }

    // 6) Leads vs plazas
    if (isset($openCat['analytics_leads_geo'])) {
        $push(
            $findings,
            'ext_leads_vs_plazas',
            'ext_analytics_leads',
            'medium',
            'Medición externa · Cruzar leads reales vs plazas SEO',
            'Hay que validar si el SEO local invierte donde ya hay demanda (WhatsApp/formularios).',
            'En adm: revisar leads por ciudad vs las 10 plazas. Anotar plazas con leads sin página fuerte '
            . 'y plazas con página fuerte sin leads. Ajustar prioridad comercial/SEO.',
            'analytics_crm',
            'https://adm.conlineweb.com/analytics/'
        );
    }

    // 7) Revisión mensual GSC
    if (isset($openCat['panel_gsc_mensual']) || isset($openCat['gsc_baseline'])) {
        $push(
            $findings,
            'ext_gsc_monthly',
            'ext_gsc_monthly',
            'low',
            'SEO externo · Hábito mensual GSC por plaza',
            'Tras el baseline, hace falta ritmo de medición (impresiones → clics → leads) por ciudad.',
            'Cada mes: en GSC filtrar por plaza/URL hub, anotar tendencia y decidir seguir/pausar '
            . 'tras ~90 días sin movimiento. Guardar nota en checklist externo.',
            'google_search_console',
            $base . '/indice/'
        );
    }

    // 8) Indexación / cobertura México (si hubo problemas técnicos)
    $techHits = (int) (($signals['by_type']['sitemap_http'] ?? 0)
        + ($signals['by_type']['robots_http'] ?? 0)
        + ($signals['by_type']['ms_sitemap_drift'] ?? 0)
        + ($signals['by_type']['canonical'] ?? 0));
    if ($techHits > 0) {
        $push(
            $findings,
            'ext_gsc_coverage',
            'ext_gsc_coverage',
            'high',
            'SEO externo · Revisar cobertura e indexación en GSC',
            'La auditoría encontró ' . $techHits . ' señal(es) técnicas (sitemap/robots/canonical/drift) '
            . 'que deben contrastarse en Search Console (Cobertura / Páginas).',
            'En GSC: Cobertura o Páginas → revisar errores/excluidas de /mexico/. '
            . 'Inspeccionar URL de 2–3 hubs prioritarios. Solicitar indexación si procede. '
            . 'Confirmar que solo canónicas largas estén en sitemap.',
            'google_search_console',
            $base . '/sitemap-index.xml'
        );
    }

    // 9) Pass IA: recomendaciones adicionales priorizadas
    if (!empty($opts['with_ai']) && function_exists('cw_seo_mexico_ai_available') && cw_seo_mexico_ai_available()) {
        $aiExtra = cw_seo_mexico_external_ai_recommendations($conn, $signals, $plazaList, $findings);
        foreach ($aiExtra as $extra) {
            if (!is_array($extra)) {
                continue;
            }
            $k = (string) ($extra['finding_key'] ?? '');
            if ($k === '') {
                continue;
            }
            $dup = false;
            foreach ($findings as $f) {
                if (($f['finding_key'] ?? '') === $k) {
                    $dup = true;
                    break;
                }
            }
            if (!$dup) {
                $findings[] = $extra;
            }
        }
    }

    // Límite manejable
    return array_slice($findings, 0, 12);
}

/**
 * Recomendaciones extra vía IA (JSON), acotadas a canales externos.
 *
 * @param list<array<string,mixed>> $existing
 * @return list<array<string,mixed>>
 */
function cw_seo_mexico_external_ai_recommendations(
    mysqli $conn,
    array $signals,
    string $plazaList,
    array $existing
): array {
    require_once __DIR__ . '/cw_seo_mexico_ai.php';
    require_once __DIR__ . '/cw_seo_mexico_ai_constitution.php';

    $existKeys = [];
    foreach ($existing as $f) {
        $existKeys[] = (string) ($f['finding_key'] ?? '');
    }

    $system = 'Eres MASTER SEO/GEO ConlineWeb. Generas SOLO tareas/recomendaciones EXTERNAS '
        . "(GSC, GBP/Maps, reseñas, citaciones, outreach, medición, casos reales).\n"
        . cw_seo_mexico_ai_master_brief() . "\n"
        . mb_substr(cw_seo_mexico_ai_seo_geo_playbook(), 0, 1200) . "\n"
        . "NO propongas cambios de código HTML/CSS/PHP. Responde SOLO JSON:\n"
        . '{"items":[{"finding_key":"ext_ai_...","check_type":"ext_rec","severity":"high|medium|low",'
        . '"title":"...","evidence":"...","correction":"...","channel":"gsc|gbp|reviews|citations|other"}]}\n'
        . 'Máximo 4 items. finding_key único prefijo ext_ai_. Español México, sin emojis.';

    $user = "Plazas: {$plazaList}\nSeñales auditoría: "
        . json_encode($signals, JSON_UNESCAPED_UNICODE)
        . "\nYa generados (no repetir): " . implode(', ', $existKeys);

    $chat = cw_seo_mexico_ai_chat([
        ['role' => 'system', 'content' => $system],
        ['role' => 'user', 'content' => $user],
    ], 0.35);
    if (empty($chat['ok'])) {
        return [];
    }
    $parsed = json_decode((string) ($chat['content'] ?? ''), true);
    if (!is_array($parsed) || !is_array($parsed['items'] ?? null)) {
        return [];
    }

    $out = [];
    foreach ($parsed['items'] as $it) {
        if (!is_array($it)) {
            continue;
        }
        $key = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) ($it['finding_key'] ?? ''))) ?? '';
        if ($key === '' || !str_starts_with($key, 'ext_')) {
            $key = 'ext_ai_' . substr(md5((string) ($it['title'] ?? microtime())), 0, 10);
        }
        $title = trim((string) ($it['title'] ?? ''));
        $correction = trim((string) ($it['correction'] ?? ''));
        if (mb_strlen($title) < 12 || mb_strlen($correction) < 20) {
            continue;
        }
        $sev = strtolower((string) ($it['severity'] ?? 'medium'));
        if (!in_array($sev, ['critical', 'high', 'medium', 'low'], true)) {
            $sev = 'medium';
        }
        $out[] = [
            'finding_key' => $key,
            'check_type' => 'ext_rec',
            'severity' => $sev,
            'url' => 'https://conlineweb.com/',
            'title' => mb_substr($title, 0, 255),
            'evidence' => trim((string) ($it['evidence'] ?? 'Recomendación SEO/GEO externa del análisis.')),
            'correction' => $correction,
            'auto_fixable' => false,
            'execution' => 'external',
            'meta' => [
                'channel' => (string) ($it['channel'] ?? 'other'),
                'kind' => 'external_rec',
                'execution' => 'external',
                'source' => 'ai',
            ],
        ];
        if (count($out) >= 4) {
            break;
        }
    }
    return $out;
}

/**
 * Aplica hallazgos externos al run: findings + tareas checklist.
 *
 * @return array{ok:bool,findings:int,tasks_created:int,by_type:array<string,int>,proposal_ids?:list<int>}
 */
function cw_seo_mexico_external_apply_to_run(
    mysqli $conn,
    int $runId,
    int $userId = 0,
    array $opts = []
): array {
    require_once __DIR__ . '/cw_seo_mexico_audit.php';

    $withAi = array_key_exists('with_ai', $opts)
        ? !empty($opts['with_ai'])
        : (function_exists('cw_seo_mexico_ai_available') && cw_seo_mexico_ai_available());

    $items = cw_seo_mexico_external_build_findings($conn, $runId, [
        'with_ai' => $withAi,
    ]);

    $findings = 0;
    $tasksCreated = 0;
    $byType = [];

    foreach ($items as $f) {
        if (!is_array($f)) {
            continue;
        }
        $f['execution'] = 'external';
        $f['auto_fixable'] = false;
        $up = cw_seo_mexico_audit_upsert_finding($conn, $runId, $f, $userId, [
            'defer_autofix' => true,
        ]);
        if (!empty($up['ok'])) {
            $findings++;
            if (!empty($up['task_created'])) {
                $tasksCreated++;
            }
            $t = (string) ($f['check_type'] ?? 'ext_other');
            $byType[$t] = ($byType[$t] ?? 0) + 1;
        }
    }

    return [
        'ok' => true,
        'findings' => $findings,
        'tasks_created' => $tasksCreated,
        'by_type' => $byType,
        'message' => "SEO/GEO externo: {$findings} tarea(s)/recomendación(es), {$tasksCreated} checklist.",
    ];
}
