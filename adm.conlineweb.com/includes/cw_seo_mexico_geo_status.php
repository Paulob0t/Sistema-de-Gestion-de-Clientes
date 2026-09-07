<?php
/**
 * Estado SEO/GEO México — lectura automática del código en conlineweb.com.
 * Responde: qué ya está implementado, qué falta y qué es manual (GSC, Ads, etc.).
 */
declare(strict_types=1);

require_once __DIR__ . '/cw_seo_sitemap_directory.php';

/** @return list<string> */
function cw_seo_geo_php_bins(): array
{
    return [
        'php',
        '/usr/local/bin/php',
        '/usr/bin/php',
        '/Applications/MAMP/bin/php/php/bin/php',
        dirname(__DIR__, 2) . '/../bin/php/php/bin/php',
    ];
}

function cw_seo_geo_php_bin(): ?string
{
    static $bin = null;
    if ($bin !== null) {
        return $bin === '' ? null : $bin;
    }
    foreach (cw_seo_geo_php_bins() as $candidate) {
        if ($candidate === 'php') {
            $out = [];
            $code = 1;
            @exec('command -v php 2>/dev/null', $out, $code);
            if ($code === 0 && !empty($out[0]) && is_executable(trim($out[0]))) {
                $bin = trim($out[0]);
                return $bin;
            }
            continue;
        }
        if (is_executable($candidate)) {
            $bin = $candidate;
            return $bin;
        }
    }
    $bin = '';
    return null;
}

/**
 * @return array{exit:int, output:string, ran:bool}
 */
function cw_seo_geo_run_site_script(string $relativeScript): array
{
    $php = cw_seo_geo_php_bin();
    $root = cw_seo_public_site_root();
    $script = $root . '/' . ltrim($relativeScript, '/');
    if (!$php || !is_file($script)) {
        return ['exit' => 1, 'output' => '', 'ran' => false];
    }
    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($script) . ' 2>&1';
    $out = [];
    $code = 1;
    @exec($cmd, $out, $code);
    return ['exit' => (int) $code, 'output' => implode("\n", $out), 'ran' => true];
}

/**
 * @return array{need:int,missing:int,shorts:int,ok:bool,missing_sample:list<string>}
 */
function cw_seo_geo_sitemap_audit(): array
{
    $root = cw_seo_public_site_root();
    $pf = $root . '/includes/mexico/page-factory.php';
    if (!is_file($pf)) {
        return ['need' => 0, 'missing' => 0, 'shorts' => 0, 'ok' => false, 'missing_sample' => []];
    }
    require_once $pf;
    $data = mx_locations();
    $xml = @file_get_contents($root . '/sitemap.xml') ?: '';
    if ($xml === '') {
        return ['need' => 0, 'missing' => 0, 'shorts' => 0, 'ok' => false, 'missing_sample' => ['sitemap.xml no encontrado']];
    }

    $need = [
        'https://conlineweb.com/mexico/',
        'https://conlineweb.com/mexico/estados/',
        'https://conlineweb.com/mexico/ciudades/',
        'https://conlineweb.com/mexico/servicios/',
    ];
    foreach (array_keys($data['servicios']) as $s) {
        $need[] = "https://conlineweb.com/mexico/servicios/{$s}/";
    }
    foreach (array_keys($data['estados']) as $s) {
        $need[] = "https://conlineweb.com/mexico/estados/{$s}/";
        $need[] = "https://conlineweb.com/mexico/estados/{$s}/servicios/";
        foreach (array_keys($data['servicios']) as $svc) {
            $need[] = "https://conlineweb.com/mexico/estados/{$s}/servicios/{$svc}/";
        }
    }
    foreach (array_keys($data['ciudades']) as $s) {
        $need[] = "https://conlineweb.com/mexico/ciudades/{$s}/";
        $need[] = "https://conlineweb.com/mexico/ciudades/{$s}/servicios/";
        foreach (array_keys($data['servicios']) as $svc) {
            $need[] = "https://conlineweb.com/mexico/ciudades/{$s}/servicios/{$svc}/";
        }
    }

    $missing = [];
    foreach ($need as $u) {
        if (!str_contains($xml, '<loc>' . $u . '</loc>')) {
            $missing[] = $u;
        }
    }

    preg_match_all('#<loc>(https://conlineweb\.com/mexico/[^<]*)</loc>#', $xml, $m);
    $shorts = [];
    foreach (array_values(array_unique($m[1] ?? [])) as $u) {
        if (str_contains($u, '/mexico/estados/')
            || str_contains($u, '/mexico/ciudades/')
            || str_contains($u, '/mexico/servicios/')
            || preg_match('#https://conlineweb\.com/mexico/$#', $u)) {
            continue;
        }
        if (preg_match('#https://conlineweb\.com/mexico/[a-z0-9-]+/#', $u)) {
            $shorts[] = $u;
        }
    }

    return [
        'need' => count($need),
        'missing' => count($missing),
        'shorts' => count($shorts),
        'ok' => $missing === [] && $shorts === [],
        'missing_sample' => array_slice($missing, 0, 5),
    ];
}

/** @return array<string, array{name:string,tier:string}> */
function cw_seo_geo_commercial_cities(): array
{
    return [
        'leon' => ['name' => 'León', 'tier' => 'P1'],
        'cdmx' => ['name' => 'Ciudad de México', 'tier' => 'P1'],
        'monterrey' => ['name' => 'Monterrey', 'tier' => 'P1'],
        'guadalajara' => ['name' => 'Guadalajara', 'tier' => 'P1'],
        'queretaro' => ['name' => 'Querétaro', 'tier' => 'P2'],
        'puebla' => ['name' => 'Puebla', 'tier' => 'P2'],
        'tijuana' => ['name' => 'Tijuana', 'tier' => 'P2'],
        'toluca' => ['name' => 'Toluca', 'tier' => 'P2'],
        'cancun' => ['name' => 'Cancún', 'tier' => 'P2'],
        'merida' => ['name' => 'Mérida', 'tier' => 'P2'],
        'veracruz' => ['name' => 'Veracruz', 'tier' => 'P3'],
        'oaxaca' => ['name' => 'Oaxaca', 'tier' => 'P3'],
    ];
}

/**
 * @param 'done'|'partial'|'pending'|'manual' $status
 * @return array<string, mixed>
 */
function cw_seo_geo_status_item(
    string $key,
    string $title,
    string $plain,
    string $status,
    string $evidence = '',
    ?string $action = null,
    ?string $link = null
): array {
    $labels = [
        'done' => 'Listo',
        'partial' => 'Parcial',
        'pending' => 'Falta',
        'manual' => 'Manual',
    ];

    return [
        'key' => $key,
        'title' => $title,
        'plain' => $plain,
        'status' => $status,
        'status_label' => $labels[$status] ?? $status,
        'evidence' => $evidence,
        'action' => $action,
        'link' => $link,
    ];
}

/** @return array<string, mixed> */
function cw_seo_mexico_geo_status_build(): array
{
    $root = cw_seo_public_site_root();
    $pf = $root . '/includes/mexico/page-factory.php';
    $hasFactory = is_file($pf);
    if ($hasFactory) {
        require_once $pf;
    }

    $sections = [];
    $counts = ['done' => 0, 'partial' => 0, 'pending' => 0, 'manual' => 0];

    $track = static function (array $item) use (&$counts): array {
        $s = (string) ($item['status'] ?? 'pending');
        if (isset($counts[$s])) {
            $counts[$s]++;
        }
        return $item;
    };

    // --- Arquitectura ---
    $arch = [];
    $estados = $hasFactory ? count(mx_locations()['estados']) : 0;
    $ciudades = $hasFactory ? count(mx_locations()['ciudades']) : 0;
    $servicios = $hasFactory ? count(mx_locations()['servicios']) : 0;
    $combos = ($estados + $ciudades) * max(1, $servicios);

    $arch[] = $track(cw_seo_geo_status_item(
        'geo_scale',
        'Capa geo México (estados + ciudades + servicios)',
        'El sitio tiene la estructura para generar landings por región y servicio.',
        ($estados === 32 && $ciudades === 19 && $servicios === 5) ? 'done' : 'partial',
        "{$estados} estados · {$ciudades} ciudades · {$servicios} servicios · {$combos} combos servicio×geo"
    ));

    $sitemap = cw_seo_geo_sitemap_audit();
    $arch[] = $track(cw_seo_geo_status_item(
        'sitemap_canonical',
        'Sitemap solo con URLs canónicas largas',
        'Google debe indexar rutas /mexico/ciudades/… y /mexico/estados/… — no URLs cortas.',
        $sitemap['ok'] ? 'done' : ($sitemap['missing'] > 0 ? 'pending' : 'partial'),
        "Esperadas {$sitemap['need']} · faltan {$sitemap['missing']} · URLs cortas en sitemap: {$sitemap['shorts']}",
        $sitemap['ok'] ? null : 'Ejecutar includes/mexico/sync-sitemap-mexico.php y volver a auditar',
        'analytics/seo_sitemap_directory.php'
    ));

    $shortWired = $hasFactory && str_contains((string) file_get_contents($pf), 'short-url-commercial-overrides');
    $shortFile = is_file($root . '/includes/mexico/short-url-commercial-overrides.php');
    $shortCount = 0;
    if ($shortFile) {
        $raw = include $root . '/includes/mexico/short-url-commercial-overrides.php';
        $shortCount = is_array($raw) ? count($raw) : 0;
    }
    $arch[] = $track(cw_seo_geo_status_item(
        'short_urls_policy',
        'URLs cortas /mexico/{slug}/servicios/{svc}/',
        'Hoy redirigen 301 a la URL larga (correcto para SEO). El copy comercial corto existe pero no se muestra en vivo.',
        'partial',
        "Copy preparado: {$shortCount} landings · conectado al sitio: " . ($shortWired ? 'sí' : 'no'),
        $shortWired ? null : 'Decidir: mantener 301 + Ads a URL larga (recomendado) o conectar short-url-commercial-overrides.php',
    ));

    $sections[] = ['id' => 'architecture', 'title' => '1. Arquitectura y URLs', 'subtitle' => 'Lo técnico que Google ve', 'items' => $arch];

    // --- Validadores ---
    $quality = [];
    $kw = cw_seo_geo_run_site_script('tools/validate-mexico-seo-keywords.php');
    $kwPass = 0;
    $kwFail = 0;
    if ($kw['ran'] && preg_match('/PASS=(\d+)\s+FAIL=(\d+)/', $kw['output'], $m)) {
        $kwPass = (int) $m[1];
        $kwFail = (int) $m[2];
    }
    $quality[] = $track(cw_seo_geo_status_item(
        'validator_keywords',
        'Keywords en title, H1 y meta por landing',
        'Cada página geo debe nombrar región + servicio (no copy genérico).',
        (!$kw['ran'] ? 'partial' : ($kwFail === 0 && $kwPass >= 360 ? 'done' : 'pending')),
        $kw['ran'] ? "PASS={$kwPass} · FAIL={$kwFail}" : 'Validador no ejecutado en este servidor',
        $kwFail > 0 ? 'Corregir fallos en tools/validate-mexico-seo-keywords.php' : null
    ));

    $p5 = cw_seo_geo_run_site_script('tools/validate-mexico-prompt5.php');
    $p5Fail = null;
    if ($p5['ran'] && preg_match('/TOTAL FAIL:\s*(\d+)/', $p5['output'], $m)) {
        $p5Fail = (int) $m[1];
    }
    $quality[] = $track(cw_seo_geo_status_item(
        'validator_prompt5',
        'Calidad de copy corporativo (Prompts 5–7)',
        'Sin jerga ambigua, dilución genérica ni referencias a calles hiperlocales.',
        (!$p5['ran'] ? 'partial' : ($p5Fail === 0 ? 'done' : 'pending')),
        $p5['ran'] ? ('TOTAL FAIL: ' . ($p5Fail ?? '?')) : 'Validador no ejecutado en este servidor',
        ($p5Fail ?? 1) > 0 ? 'Revisar salida de tools/validate-mexico-prompt5.php' : null,
        'analytics/seo_mexico_checklist.php'
    ));

    $quality[] = $track(cw_seo_geo_status_item(
        'pattern4_lists',
        'Listas tipo catálogo (Patrón 4)',
        'Revisión humana de secciones con demasiadas viñetas genéricas.',
        'manual',
        'No se valida automáticamente',
        'Revisar landings prioritarias P1 en navegador'
    ));

    $sections[] = ['id' => 'quality', 'title' => '2. Copy y validación automática', 'subtitle' => 'Lo que el código ya revisa', 'items' => $quality];

    // --- Plazas ---
    $hubSlugs = $hasFactory && function_exists('mx_city_hub_override_slugs') ? mx_city_hub_override_slugs() : [];
    $svcGeoSlugs = $hasFactory && function_exists('mx_service_geo_priority_city_slugs') ? mx_service_geo_priority_city_slugs() : [];
    $caseSlugs = $hasFactory && function_exists('mx_city_cases_slugs') ? mx_city_cases_slugs() : [];
    $patchDir = $root . '/includes/mexico/ai-hub-patches';
    $patchSlugs = [];
    foreach (glob($patchDir . '/*.json') ?: [] as $f) {
        $patchSlugs[] = basename($f, '.json');
    }

    $plazaRows = [];
    foreach (cw_seo_geo_commercial_cities() as $slug => $meta) {
        $hub = in_array($slug, $hubSlugs, true);
        $patch = in_array($slug, $patchSlugs, true);
        $svcGeo = in_array($slug, $svcGeoSlugs, true);
        $cases = in_array($slug, $caseSlugs, true);
        $score = (int) $hub + (int) $patch + (int) $svcGeo + (int) $cases;
        if ($score >= 3) {
            $rowStatus = 'done';
        } elseif ($score >= 2) {
            $rowStatus = 'partial';
        } else {
            $rowStatus = 'pending';
        }
        $missing = [];
        if (!$hub) {
            $missing[] = 'hub PHP';
        }
        if (!$patch) {
            $missing[] = 'patch JSON';
        }
        if (!$svcGeo) {
            $missing[] = 'copy servicio×ciudad';
        }
        if (!$cases) {
            $missing[] = 'casos reales';
        }
        $plazaRows[] = [
            'slug' => $slug,
            'name' => $meta['name'],
            'tier' => $meta['tier'],
            'hub' => $hub,
            'patch' => $patch,
            'svc_geo' => $svcGeo,
            'cases' => $cases,
            'status' => $rowStatus,
            'missing' => $missing,
        ];
    }

    $hubDone = count(array_filter($plazaRows, static fn ($r) => $r['hub']));
    $svcGeoDone = count(array_filter($plazaRows, static fn ($r) => $r['svc_geo']));
    $plazas = [];
    $plazas[] = $track(cw_seo_geo_status_item(
        'city_hubs',
        'Hubs de ciudad (/mexico/ciudades/{slug}/)',
        'Página principal de cada plaza con hero, servicios y FAQs propios.',
        $hubDone === 12 ? 'done' : 'partial',
        "{$hubDone}/12 ciudades comerciales con hub PHP",
        $hubDone < 12 ? 'Completar city-hub-overrides.php para plazas faltantes' : null
    ));
    $plazas[] = $track(cw_seo_geo_status_item(
        'service_geo_copy',
        'Landings servicio × ciudad (copy único)',
        'Páginas /ciudades/{slug}/servicios/{svc}/ con ángulo local por plaza prioritaria.',
        $svcGeoDone >= 10 ? ($svcGeoDone === 12 ? 'done' : 'partial') : 'pending',
        "{$svcGeoDone}/12 plazas · faltan Veracruz y Oaxaca en service-geo-overrides.php",
        $svcGeoDone < 12 ? 'Añadir contexto local en service-geo-overrides.php (P3)' : null
    ));
    $casesDone = count(array_filter($plazaRows, static fn ($r) => $r['cases']));
    $plazas[] = $track(cw_seo_geo_status_item(
        'city_cases',
        'Casos reales por ciudad (prueba social)',
        'Bloque de proyectos verificados en hubs geo.',
        $casesDone >= 8 ? 'partial' : 'pending',
        "{$casesDone}/12 plazas comerciales · 5 ciudades con casos en city-cases.php",
        'Agregar casos aprobados por cliente en city-cases.php'
    ));

    $sections[] = [
        'id' => 'plazas',
        'title' => '3. Plazas comerciales (12 ciudades)',
        'subtitle' => 'León, CDMX, MTY, GDL (P1) · 6 ciudades P2 · Veracruz/Oaxaca P3',
        'items' => $plazas,
        'table' => $plazaRows,
    ];

    // --- Manual / externo ---
    $manual = [];
    $manual[] = $track(cw_seo_geo_status_item(
        'deploy_production',
        'Publicar archivos en producción (cPanel)',
        'El código local puede estar listo pero el sitio en vivo aún no.',
        'manual',
        'Comparar versión en conlineweb.com vs carpeta local',
        'Subir includes/mexico/* y tools/* tras validar en local'
    ));
    $manual[] = $track(cw_seo_geo_status_item(
        'search_console',
        'Search Console — queries comerciales P1',
        'Medir impresiones y clics de las 20 combinaciones prioritarias (4 ciudades × 5 servicios).',
        'manual',
        '20 combos P1 · herramienta: tools/mexico-commercial-keywords-matrix.php --tier=P1',
        'Registrar queries en GSC y revisar cada 7 días',
        'analytics/seo_mexico_external.php'
    ));
    $manual[] = $track(cw_seo_geo_status_item(
        'google_ads',
        'Campañas Google Ads',
        'Apuntar anuncios a URLs canónicas largas (no URLs cortas).',
        'manual',
        'Landing: /mexico/ciudades/{slug}/servicios/{svc}/',
        'Usar matriz comercial como lista de keywords'
    ));
    $manual[] = $track(cw_seo_geo_status_item(
        'indexation',
        'Indexación real en Google',
        'Tener sitemap ≠ estar posicionado. Requiere tiempo y autoridad.',
        'manual',
        'Monitorear cobertura e impresiones en GSC',
        null
    ));

    $sections[] = ['id' => 'manual', 'title' => '4. Fuera del código (tú lo haces)', 'subtitle' => 'Despliegue, GSC, Ads e indexación', 'items' => $manual];

    $autoTotal = $counts['done'] + $counts['partial'] + $counts['pending'];
    $autoDone = $counts['done'];
    $percent = $autoTotal > 0 ? (int) round(($autoDone / $autoTotal) * 100) : 0;

    $nextSteps = [];
    if ($sitemap['missing'] > 0) {
        $nextSteps[] = 'Sincronizar sitemap (' . $sitemap['missing'] . ' URLs faltantes)';
    }
    if ($svcGeoDone < 12) {
        $nextSteps[] = 'Completar copy servicio×ciudad para Veracruz y Oaxaca';
    }
    if ($casesDone < 12) {
        $nextSteps[] = 'Sumar casos reales en más plazas P1/P2';
    }
    $nextSteps[] = 'Subir a producción y activar seguimiento Search Console P1';
    if ($nextSteps === []) {
        $nextSteps[] = 'Mantener monitoreo GSC y escalar de P1 → P2 → P3';
    }

    return [
        'generated_at' => date('Y-m-d H:i:s'),
        'site_root' => $root,
        'site_root_ok' => is_dir($root) && is_file($root . '/sitemap.xml'),
        'summary' => [
            'done' => $counts['done'],
            'partial' => $counts['partial'],
            'pending' => $counts['pending'],
            'manual' => $counts['manual'],
            'percent_code' => $percent,
            'combos' => $combos,
            'sitemap_need' => $sitemap['need'],
        ],
        'next_steps' => array_slice($nextSteps, 0, 4),
        'sections' => $sections,
    ];
}

function cw_seo_geo_status_filter_sections(array $status, string $filter): array
{
    if ($filter === '' || $filter === 'all') {
        return $status;
    }
    $out = $status;
    foreach ($out['sections'] as &$sec) {
        $sec['items'] = array_values(array_filter(
            $sec['items'] ?? [],
            static fn ($item) => ($item['status'] ?? '') === $filter
        ));
    }
    unset($sec);
    return $out;
}
