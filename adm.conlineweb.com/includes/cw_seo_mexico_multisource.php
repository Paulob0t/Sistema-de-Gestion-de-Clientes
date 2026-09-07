<?php
/**
 * Análisis multi-fuente SEO México.
 * Contrasta: código local · HTML en vivo · sitemap · robots · redirects · NAP · overrides.
 * Cada desajuste → finding → tarea dinámica (fase 06) para corrección continua.
 */
require_once __DIR__ . '/cw_seo_mexico_checklist.php';
// Usa funciones de cw_seo_mexico_audit.php en runtime (require desde audit_run).

/**
 * @return list<array<string,mixed>>
 */
function cw_seo_mexico_multisource_analyze(array $opts = []): array
{
    $deep = !empty($opts['deep']);
    $findings = [];
    $base = rtrim(cw_seo_mexico_audit_base_url(), '/');
    $root = cw_seo_mexico_site_root_path();
    $nap = cw_seo_mexico_audit_nap_needles();

    $findings = array_merge($findings, cw_seo_mexico_multisource_code_inventory($root));
    $findings = array_merge($findings, cw_seo_mexico_multisource_sitemap_drift($root, $base));
    $findings = array_merge($findings, cw_seo_mexico_multisource_robots_live($base));
    $findings = array_merge($findings, cw_seo_mexico_multisource_short_redirects($base, $deep));
    $findings = array_merge($findings, cw_seo_mexico_multisource_override_vs_live($root, $base, $deep));
    $findings = array_merge($findings, cw_seo_mexico_multisource_nap_consistency($root, $base, $nap));

    return $findings;
}

/**
 * @return list<array<string,mixed>>
 */
function cw_seo_mexico_multisource_code_inventory(?string $root): array
{
    $out = [];
    if ($root === null || $root === '') {
        $out[] = [
            'finding_key' => 'ms_code_root_missing',
            'check_type' => 'ms_code_inventory',
            'severity' => 'critical',
            'url' => cw_seo_mexico_audit_base_url() . '/',
            'title' => 'Árbol local conlineweb.com no encontrado junto al admin',
            'evidence' => 'cw_seo_mexico_site_root_path() = null. Sin código local no se puede contrastar ni AutoFix.',
            'correction' => 'En el mismo servidor que adm, debe existir la carpeta conlineweb.com (o ajustar rutas).',
            'auto_fixable' => false,
            'meta' => ['source' => 'code'],
        ];
        return $out;
    }

    $required = [
        'includes/cw-nap.php' => 'Fuente única NAP',
        'includes/mexico/city-hub-overrides.php' => 'Overrides hubs plazas',
        'includes/mexico/service-geo-overrides.php' => 'Overrides servicio×plaza',
        'includes/mexico/city-blog-links.php' => 'Enlaces blog↔geo',
        'includes/mexico/cannibalization.php' => 'Política canibalización',
        'includes/mexico/page-factory.php' => 'Factory páginas México',
        'sitemap.xml' => 'Sitemap México',
        'sitemap-blog.xml' => 'Sitemap blog',
        'sitemap-index.xml' => 'Índice de sitemaps',
        'robots.txt' => 'Robots',
    ];
    $missing = [];
    foreach ($required as $rel => $label) {
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!is_file($path)) {
            $missing[] = $rel . ' (' . $label . ')';
        }
    }
    if ($missing !== []) {
        $out[] = [
            'finding_key' => 'ms_code_files_missing',
            'check_type' => 'ms_code_inventory',
            'severity' => 'critical',
            'url' => 'file://conlineweb.com',
            'title' => 'Faltan archivos clave del SEO México en código local',
            'evidence' => implode(' · ', array_slice($missing, 0, 12)),
            'correction' => 'Restaurar o desplegar los archivos faltantes del plan SEO México.',
            'auto_fixable' => false,
            'meta' => ['source' => 'code', 'missing' => $missing],
        ];
    }

    // Overrides: cantidad mínima
    $hubFile = $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'mexico' . DIRECTORY_SEPARATOR . 'city-hub-overrides.php';
    if (is_file($hubFile)) {
        require_once $hubFile;
        $slugs = function_exists('mx_city_hub_override_slugs')
            ? mx_city_hub_override_slugs()
            : (function_exists('mx_city_hub_overrides_map') ? array_keys(mx_city_hub_overrides_map()) : []);
        if (count($slugs) < 8) {
            $out[] = [
                'finding_key' => 'ms_code_hubs_incomplete',
                'check_type' => 'ms_code_inventory',
                'severity' => 'high',
                'url' => 'file://includes/mexico/city-hub-overrides.php',
                'title' => 'Overrides de hubs incompletos en código',
                'evidence' => count($slugs) . ' plazas con override (mínimo 8)',
                'correction' => 'Completar city-hub-overrides.php para las 10 plazas prioritarias.',
                'auto_fixable' => false,
                'meta' => ['source' => 'code', 'count' => count($slugs)],
            ];
        }
    }

    return $out;
}

/**
 * @return list<string>
 */
function cw_seo_mexico_multisource_parse_sitemap_locs(string $xml): array
{
    $locs = [];
    if (preg_match_all('/<loc>\s*([^<]+)\s*<\/loc>/i', $xml, $m)) {
        foreach ($m[1] as $loc) {
            $locs[] = trim(html_entity_decode($loc, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
    }
    return $locs;
}

/**
 * @return list<array<string,mixed>>
 */
function cw_seo_mexico_multisource_sitemap_drift(?string $root, string $base): array
{
    $out = [];
    $live = cw_seo_mexico_audit_fetch($base . '/sitemap.xml', 20);
    $index = cw_seo_mexico_audit_fetch($base . '/sitemap-index.xml', 15);
    $blog = cw_seo_mexico_audit_fetch($base . '/sitemap-blog.xml', 15);

    if (empty($live['ok'])) {
        $out[] = [
            'finding_key' => 'ms_live_sitemap_down',
            'check_type' => 'ms_sitemap_drift',
            'severity' => 'critical',
            'url' => $base . '/sitemap.xml',
            'title' => 'Sitemap México no responde en vivo',
            'evidence' => 'HTTP ' . (int) ($live['status'] ?? 0),
            'correction' => 'Publicar sitemap.xml en producción y verificar permisos/CDN.',
            'auto_fixable' => false,
            'meta' => ['source' => 'live'],
        ];
        return $out;
    }

    $liveLocs = cw_seo_mexico_multisource_parse_sitemap_locs((string) ($live['body'] ?? ''));
    $shorts = array_values(array_filter($liveLocs, static function ($u) {
        return (bool) preg_match('#https?://[^/]+/mexico/[a-z0-9\-]+/?$#i', $u)
            && !str_contains($u, '/ciudades/')
            && !str_contains($u, '/estados/')
            && !str_contains($u, '/servicios');
    }));
    if ($shorts !== []) {
        $out[] = [
            'finding_key' => 'ms_live_sitemap_shorts',
            'check_type' => 'ms_sitemap_drift',
            'severity' => 'high',
            'url' => $base . '/sitemap.xml',
            'title' => 'Sitemap en vivo incluye rutas cortas (canibalización)',
            'evidence' => count($shorts) . ' shorts · ej: ' . ($shorts[0] ?? ''),
            'correction' => 'Quitar shorts del sitemap; solo URLs canónicas /mexico/ciudades/…',
            'auto_fixable' => false,
            'meta' => ['source' => 'live_vs_policy', 'samples' => array_slice($shorts, 0, 5)],
        ];
    }

    if (empty($index['ok'])) {
        $out[] = [
            'finding_key' => 'ms_live_sitemap_index_down',
            'check_type' => 'ms_sitemap_drift',
            'severity' => 'high',
            'url' => $base . '/sitemap-index.xml',
            'title' => 'sitemap-index.xml no responde en vivo',
            'evidence' => 'HTTP ' . (int) ($index['status'] ?? 0),
            'correction' => 'Publicar sitemap-index.xml (México + blog) y darlo de alta en GSC.',
            'auto_fixable' => false,
            'meta' => ['source' => 'live'],
        ];
    } else {
        $idxBody = (string) ($index['body'] ?? '');
        if (!str_contains($idxBody, 'sitemap.xml') || !str_contains($idxBody, 'sitemap-blog.xml')) {
            $out[] = [
                'finding_key' => 'ms_live_sitemap_index_incomplete',
                'check_type' => 'ms_sitemap_drift',
                'severity' => 'medium',
                'url' => $base . '/sitemap-index.xml',
                'title' => 'Índice de sitemaps incompleto en vivo',
                'evidence' => 'Debe listar sitemap.xml y sitemap-blog.xml',
                'correction' => 'Actualizar sitemap-index.xml en producción.',
                'auto_fixable' => false,
                'meta' => ['source' => 'live'],
            ];
        }
    }

    if (empty($blog['ok']) || count(cw_seo_mexico_multisource_parse_sitemap_locs((string) ($blog['body'] ?? ''))) < 1) {
        $out[] = [
            'finding_key' => 'ms_live_sitemap_blog_empty',
            'check_type' => 'ms_sitemap_drift',
            'severity' => 'medium',
            'url' => $base . '/sitemap-blog.xml',
            'title' => 'Sitemap del blog vacío o caído en vivo',
            'evidence' => 'HTTP ' . (int) ($blog['status'] ?? 0),
            'correction' => 'Regenerar/publicar sitemap-blog.xml.',
            'auto_fixable' => false,
            'meta' => ['source' => 'live'],
        ];
    }

    // Drift local vs vivo (si hay árbol local)
    if ($root && is_file($root . DIRECTORY_SEPARATOR . 'sitemap.xml')) {
        $localXml = (string) @file_get_contents($root . DIRECTORY_SEPARATOR . 'sitemap.xml');
        $localLocs = cw_seo_mexico_multisource_parse_sitemap_locs($localXml);
        $liveSet = array_fill_keys($liveLocs, true);
        $missingLive = [];
        foreach ($localLocs as $loc) {
            if (!isset($liveSet[$loc]) && str_contains($loc, '/mexico/ciudades/')) {
                $missingLive[] = $loc;
            }
        }
        if (count($missingLive) >= 3) {
            $out[] = [
                'finding_key' => 'ms_sitemap_deploy_drift',
                'check_type' => 'ms_sitemap_drift',
                'severity' => 'high',
                'url' => $base . '/sitemap.xml',
                'title' => 'Drift deploy: sitemap local ≠ sitemap en vivo',
                'evidence' => count($missingLive) . ' URLs México en local que no están en vivo · ej: ' . ($missingLive[0] ?? ''),
                'correction' => 'Subir sitemap.xml actualizado a producción (cPanel/FTP/deploy).',
                'auto_fixable' => false,
                'meta' => ['source' => 'code_vs_live', 'missing_count' => count($missingLive), 'samples' => array_slice($missingLive, 0, 8)],
            ];
        }

        // Plazas prioritarias deben estar en vivo
        $priorityMissing = [];
        foreach (cw_seo_mexico_priority_plazas() as $p) {
            $slug = (string) ($p['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $need = $base . '/mexico/ciudades/' . $slug . '/';
            $found = false;
            foreach ($liveLocs as $loc) {
                if (rtrim($loc, '/') === rtrim($need, '/')) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $priorityMissing[] = $need;
            }
        }
        if ($priorityMissing !== []) {
            $out[] = [
                'finding_key' => 'ms_sitemap_priority_missing',
                'check_type' => 'ms_sitemap_drift',
                'severity' => 'high',
                'url' => $base . '/sitemap.xml',
                'title' => 'Plazas prioritarias ausentes del sitemap en vivo',
                'evidence' => implode(' · ', array_slice($priorityMissing, 0, 6)),
                'correction' => 'Incluir hubs canónicos de las 10 plazas en sitemap.xml y redeploy.',
                'auto_fixable' => false,
                'meta' => ['source' => 'policy_vs_live', 'missing' => $priorityMissing],
            ];
        }
    }

    return $out;
}

/**
 * @return list<array<string,mixed>>
 */
function cw_seo_mexico_multisource_robots_live(string $base): array
{
    $out = [];
    $fetch = cw_seo_mexico_audit_fetch($base . '/robots.txt', 12);
    if (empty($fetch['ok'])) {
        $out[] = [
            'finding_key' => 'ms_robots_down',
            'check_type' => 'ms_robots',
            'severity' => 'high',
            'url' => $base . '/robots.txt',
            'title' => 'robots.txt no responde en vivo',
            'evidence' => 'HTTP ' . (int) ($fetch['status'] ?? 0),
            'correction' => 'Publicar robots.txt con Sitemap: apuntando al índice.',
            'auto_fixable' => false,
            'meta' => ['source' => 'live'],
        ];
        return $out;
    }
    $body = (string) ($fetch['body'] ?? '');
    if (!preg_match('/sitemap\s*:\s*.+/i', $body)) {
        $out[] = [
            'finding_key' => 'ms_robots_no_sitemap',
            'check_type' => 'ms_robots',
            'severity' => 'medium',
            'url' => $base . '/robots.txt',
            'title' => 'robots.txt sin declaración Sitemap',
            'evidence' => 'No se encontró línea Sitemap:',
            'correction' => 'Agregar Sitemap: https://conlineweb.com/sitemap-index.xml',
            'auto_fixable' => false,
            'meta' => ['source' => 'live'],
        ];
    }
    return $out;
}

/**
 * @return list<array<string,mixed>>
 */
function cw_seo_mexico_multisource_short_redirects(string $base, bool $deep): array
{
    $out = [];
    $plazas = cw_seo_mexico_priority_plazas();
    $sample = $deep ? $plazas : array_slice($plazas, 0, 4);
    $bad = [];
    foreach ($sample as $p) {
        $slug = (string) ($p['slug'] ?? '');
        if ($slug === '') {
            continue;
        }
        $short = $base . '/mexico/' . rawurlencode($slug) . '/';
        $canon = $base . '/mexico/ciudades/' . rawurlencode($slug) . '/';
        $fetch = cw_seo_mexico_audit_fetch($short, 12);
        $final = rtrim((string) ($fetch['final_url'] ?? ''), '/') . '/';
        $canonN = rtrim($canon, '/') . '/';
        $ok = !empty($fetch['ok']) && (
            strcasecmp($final, $canonN) === 0
            || str_contains(strtolower($final), '/mexico/ciudades/' . strtolower($slug))
        );
        if (!$ok) {
            $bad[] = $short . ' → ' . ($fetch['final_url'] ?? 'n/a') . ' (HTTP ' . (int) ($fetch['status'] ?? 0) . ')';
        }
        usleep(40000);
    }
    if ($bad !== []) {
        $out[] = [
            'finding_key' => 'ms_short_redirect_fail',
            'check_type' => 'ms_redirects',
            'severity' => 'high',
            'url' => $base . '/mexico/',
            'title' => 'Rutas cortas no redirigen a canónica en vivo',
            'evidence' => implode(' · ', array_slice($bad, 0, 6)),
            'correction' => 'Verificar stubs 301 /mexico/{slug}/ → /mexico/ciudades/{slug}/ en producción.',
            'auto_fixable' => true,
            'meta' => ['source' => 'live', 'failures' => $bad],
        ];
    }
    return $out;
}

/**
 * Código (overrides) vs HTML servido en vivo.
 *
 * @return list<array<string,mixed>>
 */
function cw_seo_mexico_multisource_override_vs_live(?string $root, string $base, bool $deep): array
{
    $out = [];
    if ($root === null) {
        return $out;
    }
    $hubFile = $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'mexico' . DIRECTORY_SEPARATOR . 'city-hub-overrides.php';
    if (!is_file($hubFile)) {
        return $out;
    }
    require_once $hubFile;
    if (!function_exists('mx_city_hub_overrides_map') || !function_exists('mx_city_hub_override')) {
        return $out;
    }
    $map = mx_city_hub_overrides_map();
    $slugs = array_keys($map);
    $sample = $deep ? $slugs : array_slice($slugs, 0, 5);
    $titleMismatch = [];
    $h1Mismatch = [];
    $liveDown = [];

    $norm = static function (string $s): string {
        $s = mb_strtolower(trim($s));
        $s = str_replace(['—', '–', '−'], '-', $s);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        return $s;
    };

    foreach ($sample as $slug) {
        $slug = (string) $slug;
        // Efectivo = mapa base + ai-hub-patches (lo que sirve la web en vivo)
        $expected = mx_city_hub_override($slug);
        if (!is_array($expected) || $expected === []) {
            $expected = $map[$slug] ?? null;
        }
        if (!is_array($expected)) {
            continue;
        }
        $url = $base . '/mexico/ciudades/' . rawurlencode($slug) . '/';
        $fetch = cw_seo_mexico_audit_fetch($url, 15);
        if (empty($fetch['ok'])) {
            $liveDown[] = $url . ' HTTP ' . (int) ($fetch['status'] ?? 0);
            continue;
        }
        $parsed = cw_seo_mexico_audit_parse_html((string) ($fetch['body'] ?? ''));
        $expTitle = trim((string) ($expected['title'] ?? ''));
        $liveTitle = trim((string) ($parsed['title'] ?? ''));
        if ($expTitle !== '' && $liveTitle !== '') {
            $nExp = $norm($expTitle);
            $nLive = $norm($liveTitle);
            if ($nExp !== $nLive) {
                $core = mb_substr($nExp, 0, 36);
                if ($core === '' || mb_stripos($nLive, $core) === false) {
                    $titleMismatch[] = $slug . ': live="' . mb_substr($liveTitle, 0, 70) . '"';
                }
            }
        }
        $expH1 = trim((string) ($expected['h1'] ?? ''));
        $liveH1 = trim((string) ($parsed['h1'] ?? ''));
        $expH1Alt = function_exists('cw_title_case') ? cw_title_case($expH1) : $expH1;
        if ($expH1 !== '' && $liveH1 !== '') {
            $nExpH = $norm($expH1);
            $nExpHt = $norm($expH1Alt);
            $nLiveH = $norm($liveH1);
            if ($nLiveH !== $nExpH && $nLiveH !== $nExpHt) {
                $coreH = mb_substr($nExpH, 0, 24);
                if ($coreH === '' || mb_stripos($nLiveH, $coreH) === false) {
                    $h1Mismatch[] = $slug . ': live="' . mb_substr($liveH1, 0, 60) . '"';
                }
            }
        }
        usleep(50000);
    }

    if ($liveDown !== []) {
        $out[] = [
            'finding_key' => 'ms_hub_live_down',
            'check_type' => 'ms_override_vs_live',
            'severity' => 'critical',
            'url' => $base . '/mexico/ciudades/',
            'title' => 'Hubs de plaza caídos al contrastar con overrides',
            'evidence' => implode(' · ', array_slice($liveDown, 0, 6)),
            'correction' => 'Crear/reparar páginas geo en producción (stubs o deploy).',
            'auto_fixable' => true,
            'meta' => ['source' => 'code_vs_live'],
        ];
    }
    if ($titleMismatch !== []) {
        $out[] = [
            'finding_key' => 'ms_title_drift',
            'check_type' => 'ms_override_vs_live',
            'severity' => 'high',
            'url' => $base . '/mexico/ciudades/',
            'title' => 'Title en vivo no coincide con overrides de código',
            'evidence' => implode(' · ', array_slice($titleMismatch, 0, 6)),
            'correction' => 'Redeploy de city-hub-overrides.php / page-factory, o limpiar caché CDN.',
            'auto_fixable' => false,
            'meta' => ['source' => 'code_vs_live', 'mismatches' => $titleMismatch],
        ];
    }
    if ($h1Mismatch !== []) {
        $out[] = [
            'finding_key' => 'ms_h1_drift',
            'check_type' => 'ms_override_vs_live',
            'severity' => 'medium',
            'url' => $base . '/mexico/ciudades/',
            'title' => 'H1 en vivo no coincide con overrides de código',
            'evidence' => implode(' · ', array_slice($h1Mismatch, 0, 6)),
            'correction' => 'Verificar que el hub use mx_city_hub_overrides y redeploy.',
            'auto_fixable' => false,
            'meta' => ['source' => 'code_vs_live', 'mismatches' => $h1Mismatch],
        ];
    }

    return $out;
}

/**
 * ¿Footer / meta / page-factory México / contacto usan cw-nap?
 *
 * @return array{ok:bool,unwired:list<string>}
 */
function cw_seo_mexico_multisource_nap_wire_status(?string $root): array
{
    if ($root === null) {
        return ['ok' => false, 'unwired' => ['sitio no encontrado']];
    }
    $napFile = $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'cw-nap.php';
    if (!is_file($napFile)) {
        return ['ok' => false, 'unwired' => ['includes/cw-nap.php (ausente)']];
    }
    $wire = [
        'includes/footer.php',
        'includes/cw-seo-meta.php',
        'includes/mexico/page-factory.php',
        'contacto.php',
    ];
    $unwired = [];
    foreach ($wire as $rel) {
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!is_file($path)) {
            $unwired[] = $rel . ' (ausente)';
            continue;
        }
        $src = (string) @file_get_contents($path);
        if ($src === '' || (!str_contains($src, 'cw-nap') && !str_contains($src, 'cw_nap'))) {
            $unwired[] = $rel;
        }
    }
    return ['ok' => $unwired === [], 'unwired' => $unwired];
}

function cw_seo_mexico_multisource_nap_is_wired(?string $root): bool
{
    return !empty(cw_seo_mexico_multisource_nap_wire_status($root)['ok']);
}

/**
 * @param array{phone:string,email:string,street:string,name:string} $nap
 * @return list<array<string,mixed>>
 */
function cw_seo_mexico_multisource_nap_consistency(?string $root, string $base, array $nap): array
{
    $out = [];
    if ($root === null) {
        return $out;
    }
    $napFile = $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'cw-nap.php';
    if (!is_file($napFile)) {
        return $out;
    }

    $wireStatus = cw_seo_mexico_multisource_nap_wire_status($root);
    $unwired = $wireStatus['unwired'] ?? [];
    if ($unwired !== []) {
        $out[] = [
            'finding_key' => 'ms_nap_not_wired',
            'check_type' => 'ms_nap',
            'severity' => 'high',
            'url' => 'file://includes/cw-nap.php',
            'title' => 'NAP no cableado en archivos clave del código',
            'evidence' => implode(' · ', $unwired),
            'correction' => 'Incluir require/uso de cw_nap() en footer, schema y contacto.',
            'auto_fixable' => false,
            'meta' => ['source' => 'code'],
        ];
    }

    // Live: home + contacto deben reflejar NAP
    foreach ([$base . '/', $base . '/contacto/'] as $url) {
        $fetch = cw_seo_mexico_audit_fetch($url, 14);
        if (empty($fetch['ok'])) {
            continue;
        }
        $hay = (string) ($fetch['body'] ?? '');
        $phoneDigits = preg_replace('/\D+/', '', $nap['phone'] ?? '') ?? '';
        $phoneOk = ($nap['phone'] ?? '') !== '' && (
            str_contains($hay, $nap['phone'])
            || ($phoneDigits !== '' && str_contains(preg_replace('/\D+/', '', $hay) ?? '', $phoneDigits))
        );
        $emailOk = ($nap['email'] ?? '') !== '' && stripos($hay, $nap['email']) !== false;
        if (!$phoneOk || !$emailOk) {
            $miss = [];
            if (!$phoneOk) {
                $miss[] = 'teléfono';
            }
            if (!$emailOk) {
                $miss[] = 'email';
            }
            $out[] = [
                'finding_key' => 'ms_nap_live_' . md5($url),
                'check_type' => 'ms_nap',
                'severity' => 'high',
                'url' => $url,
                'title' => 'NAP código ≠ NAP en HTML en vivo',
                'evidence' => 'Falta en vivo: ' . implode(', ', $miss) . ' (fuente cw-nap.php)',
                'correction' => 'Redeploy del footer/schema que consume cw-nap.php.',
                'auto_fixable' => true,
                'meta' => ['source' => 'code_vs_live', 'missing' => $miss],
            ];
        }
    }

    return $out;
}

/**
 * Aplica findings multi-fuente al run actual (crea/reabre tareas).
 *
 * @return array{findings:int,tasks_created:int,auto_applied:int,by_type:array<string,int>}
 */
function cw_seo_mexico_multisource_apply_to_run(mysqli $conn, int $runId, int $userId = 0, array $opts = []): array
{
    $findings = 0;
    $tasksCreated = 0;
    $autoApplied = 0;
    $byType = [];
    $deferFix = !empty($opts['defer_autofix']);

    $staleClosed = 0;
    if (empty($opts['findings_only_engine'])) {
        $items = cw_seo_mexico_multisource_analyze($opts);
        foreach ($items as $f) {
            $up = cw_seo_mexico_audit_upsert_finding($conn, $runId, $f, $userId, [
                'defer_autofix' => $deferFix,
            ]);
            if (empty($up['ok'])) {
                continue;
            }
            $findings++;
            if (!empty($up['task_created'])) {
                $tasksCreated++;
            }
            if (!empty($up['auto_applied'])) {
                $autoApplied++;
            }
            $t = (string) ($f['check_type'] ?? 'ms_other');
            $byType[$t] = ($byType[$t] ?? 0) + 1;
        }
        // Si NAP (u otro ms_*) ya no falla, cerrar el open viejo; si no, la tarea queda colgada
        if (function_exists('cw_seo_mexico_audit_close_stale_findings')) {
            $staleClosed = cw_seo_mexico_audit_close_stale_findings($conn, $runId, 'ms_', $userId);
        }
    } else {
        $findings = (int) ($opts['precomputed_count'] ?? 0);
    }

    // Tarea maestra del motor multi-fuente
    $engineDetail = json_encode([
        'source' => 'multisource',
        'dynamic' => true,
        'user_description' => 'Contrasta continuamente código local, HTML en vivo, sitemaps, robots, redirects y NAP. Si hay drift, abre tareas de corrección.',
        'user_correction' => 'Usa el botón «Ejecutar correcciones» para AutoFix con reporte antes/después, o corrige y reaudita.',
        'last_run_id' => $runId,
        'sources' => ['code', 'live_html', 'sitemap', 'robots', 'redirects', 'nap', 'overrides'],
        'plan' => [
            'can_start_now' => true,
            'start' => date('Y-m-d'),
            'due' => date('Y-m-d', strtotime('+3 days')),
            'effort' => 'continuo',
            'window' => 'cada cron / cada auditoría',
            'note' => 'El 100% del plan código no basta: este motor valida orden real multi-fuente.',
        ],
    ], JSON_UNESCAPED_UNICODE);
    $engDone = $findings === 0 ? 1 : 0;
    $eng = $conn->prepare(
        'INSERT INTO cw_seo_mexico_checklist (task_key, phase, title, description, sort_order, done, detail_json, updated_at)
         VALUES (\'audit_multisource_engine\', \'06_auditoria_viva\', \'Motor multi-fuente (código ↔ vivo)\', ?, 885, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE description = VALUES(description), done = VALUES(done), detail_json = VALUES(detail_json), updated_at = NOW()'
    );
    if ($eng) {
        $desc = 'Última corrida #' . $runId . ': ' . $findings . ' desajustes multi-fuente · fuentes: código, HTML vivo, sitemap, robots, redirects, NAP.';
        $eng->bind_param('sis', $desc, $engDone, $engineDetail);
        $eng->execute();
        $eng->close();
    }

    return [
        'findings' => $findings,
        'tasks_created' => $tasksCreated,
        'auto_applied' => $autoApplied,
        'by_type' => $byType,
        'stale_closed' => $staleClosed,
    ];
}
