<?php
/**
 * KPIs en vivo por plaza prioritaria (SEO México · 90 días).
 */
require_once __DIR__ . '/cw_seo_mexico_checklist.php';
require_once __DIR__ . '/cw_hub_analytics.php';

/**
 * Definición de indicadores + texto “de qué va” para la UI.
 *
 * @return list<array{key:string,label:string,short:string,about:string,how:string,icon:string}>
 */
function cw_seo_mexico_kpi_indicators(): array
{
    return [
        [
            'key' => 'visitas',
            'label' => 'Visitas',
            'short' => 'Tráfico a páginas de la plaza',
            'about' => 'Cuántas veces se abrieron las páginas de esa ciudad (hub + servicios locales) en el periodo.',
            'how' => 'Se cuenta cada vista en URLs que empiezan por /mexico/ciudades/{plaza}/. Sirve para ver si hay demanda o si Google/visitas aún no llegan.',
            'icon' => 'bi-eye',
        ],
        [
            'key' => 'sesiones',
            'label' => 'Sesiones',
            'short' => 'Visitas únicas de sesión',
            'about' => 'Cuántas visitas distintas (sesiones) hubo en páginas de esa plaza, no solo recargas de la misma persona.',
            'how' => 'Una persona puede generar varias vistas; la sesión agrupa su recorrido. Ayuda a no inflar el tráfico con refrescos.',
            'icon' => 'bi-people',
        ],
        [
            'key' => 'leads',
            'label' => 'Leads',
            'short' => 'Contactos desde la plaza',
            'about' => 'Personas que escribieron por WhatsApp o formulario dejando como origen una página de esa ciudad.',
            'how' => 'Se toma el campo página de origen del lead web. Es el KPI comercial más importante del plan local.',
            'icon' => 'bi-person-plus',
        ],
        [
            'key' => 'calificados',
            'label' => 'Calificados',
            'short' => 'Leads con avance comercial',
            'about' => 'Leads de esa plaza que ya pasaron de “nuevo” a calificado, seguimiento, propuesta o cerrado.',
            'how' => 'Mide calidad, no solo cantidad. Si hay muchos leads pero casi ninguno califica, hay que revisar oferta o mensaje.',
            'icon' => 'bi-stars',
        ],
        [
            'key' => 'cerrados',
            'label' => 'Cerrados',
            'short' => 'Ventas / cierres',
            'about' => 'Leads originados en páginas de esa plaza marcados como cerrados en el CRM.',
            'how' => 'Es el resultado final del embudo. En SEO local tarda más en moverse; se revisa junto a calificados.',
            'icon' => 'bi-check2-circle',
        ],
        [
            'key' => 'tasa_lead',
            'label' => 'Tasa lead',
            'short' => 'Visitas que se convierten en lead',
            'about' => 'Porcentaje de visitas a la plaza que terminan en un contacto (WhatsApp/formulario).',
            'how' => 'Fórmula: leads ÷ visitas × 100. Si hay tráfico pero tasa baja, el problema suele ser la página o la oferta, no solo el SEO.',
            'icon' => 'bi-percent',
        ],
        [
            'key' => 'geo_local',
            'label' => 'Visitantes locales',
            'short' => 'Sesiones con geo cerca de la plaza',
            'about' => 'Sesiones cuya ubicación estimada (IP/navegador) coincide con la ciudad o estado de la plaza.',
            'how' => 'Es una señal aproximada (no exacta). Sirve para ver si llegan vecinos de esa zona, además de quien llega por la URL local.',
            'icon' => 'bi-geo-alt',
        ],
        [
            'key' => 'semaforo',
            'label' => 'Semáforo',
            'short' => 'Estado vs meta del plan',
            'about' => 'Resumen rápido: verde = la plaza ya genera contacto útil; ámbar = hay tráfico pero aún no convierte; rojo = sin movimiento.',
            'how' => 'Verde: ≥1 lead calificado o ≥2 leads. Ámbar: hay visitas o 1 lead nuevo. Rojo: sin visitas ni leads en el periodo.',
            'icon' => 'bi-traffic-light',
        ],
    ];
}

/**
 * @return array<string, array{key:string,label:string,short:string,about:string,how:string,icon:string}>
 */
function cw_seo_mexico_kpi_indicators_map(): array
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (cw_seo_mexico_kpi_indicators() as $ind) {
            $map[$ind['key']] = $ind;
        }
    }
    return $map;
}

/**
 * Metas del plan 90 días (guardadas también en checklist kpi_90d).
 *
 * @return array<string,mixed>
 */
function cw_seo_mexico_kpi_targets(): array
{
    return [
        'window_days' => 90,
        'panel_url' => 'analytics/seo_mexico_plazas.php',
        'goals' => [
            [
                'key' => 'plazas_con_trafico',
                'label' => 'Plazas con tráfico',
                'target' => 5,
                'unit' => 'plazas',
                'about' => 'En 90 días, al menos 5 de las 10 plazas prioritarias deben tener visitas en sus landings locales.',
            ],
            [
                'key' => 'plazas_con_leads',
                'label' => 'Plazas con leads',
                'target' => 3,
                'unit' => 'plazas',
                'about' => 'Al menos 3 plazas deben generar 1 o más leads (WhatsApp/form) desde sus URLs geo.',
            ],
            [
                'key' => 'leads_geo_total',
                'label' => 'Leads geo totales',
                'target' => 8,
                'unit' => 'leads',
                'about' => 'Suma mínima de leads originados en páginas /mexico/ciudades/ de las plazas prioritarias en el periodo de 90 días.',
            ],
            [
                'key' => 'tasa_calificados',
                'label' => 'Calificación de leads',
                'target' => 25,
                'unit' => '%',
                'about' => 'De los leads geo, al menos 25% deberían avanzar a calificado/seguimiento/propuesta/cerrado.',
            ],
        ],
        'plaza_rules' => [
            'green' => '≥1 lead calificado o ≥2 leads en el periodo',
            'amber' => 'Hay visitas (o 1 lead nuevo) pero aún no hay tracción comercial clara',
            'red' => 'Sin visitas ni leads: revisar indexación, enlaces o pausar tras 90 días',
        ],
        'note_gsc' => 'Impresiones, clics y posición de Google Search Console se revisan aparte (mensual); este panel mide tráfico y leads reales del sitio.',
    ];
}

/**
 * Extrae slug de plaza desde un path o URL.
 */
function cw_seo_mexico_kpi_slug_from_path(string $pathOrUrl): ?string
{
    $path = parse_url($pathOrUrl, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        $path = $pathOrUrl;
    }
    $path = strtolower($path);
    if (preg_match('#/mexico/ciudades/([a-z0-9\-]+)#', $path, $m)) {
        return $m[1];
    }
    return null;
}

/**
 * @param list<array{city:string,slug:string,estado:string}> $plazas
 * @return array<string, list<string>>
 */
function cw_seo_mexico_kpi_geo_aliases(array $plazas): array
{
    $aliases = [];
    foreach ($plazas as $p) {
        $slug = (string) ($p['slug'] ?? '');
        if ($slug === '') {
            continue;
        }
        $list = [
            mb_strtolower((string) ($p['city'] ?? '')),
            mb_strtolower((string) ($p['estado'] ?? '')),
            str_replace('-', ' ', $slug),
        ];
        if ($slug === 'cdmx') {
            $list[] = 'ciudad de mexico';
            $list[] = 'ciudad de méxico';
            $list[] = 'mexico city';
            $list[] = 'cdmx';
            $list[] = 'df';
        }
        if ($slug === 'queretaro') {
            $list[] = 'querétaro';
        }
        if ($slug === 'merida') {
            $list[] = 'mérida';
        }
        if ($slug === 'cancun') {
            $list[] = 'cancún';
        }
        if ($slug === 'leon') {
            $list[] = 'león';
        }
        $aliases[$slug] = array_values(array_unique(array_filter($list)));
    }
    return $aliases;
}

/**
 * @return array{
 *   period:array{from:string,to:string},
 *   targets:array<string,mixed>,
 *   indicators:list<array<string,mixed>>,
 *   totals:array<string,int|float>,
 *   goal_progress:list<array<string,mixed>>,
 *   plazas:list<array<string,mixed>>
 * }
 */
function cw_seo_mexico_kpis_snapshot(mysqli $conn, string $from, string $to): array
{
    $plazasDef = cw_seo_mexico_priority_plazas();
    $aliases = cw_seo_mexico_kpi_geo_aliases($plazasDef);

    $buckets = [];
    foreach ($plazasDef as $p) {
        $slug = (string) $p['slug'];
        $buckets[$slug] = [
            'slug' => $slug,
            'city' => (string) $p['city'],
            'estado' => (string) $p['estado'],
            'url' => (string) $p['url'],
            'visitas' => 0,
            'sesiones' => 0,
            'leads' => 0,
            'calificados' => 0,
            'cerrados' => 0,
            'geo_local' => 0,
            'tasa_lead' => 0.0,
            'tasa_cierre' => 0.0,
            'semaforo' => 'red',
            'semaforo_label' => 'Sin movimiento',
        ];
    }

    $pvScope = cw_analytics_url_scope_sql('url');
    $sqlPv = "SELECT path, COUNT(*) AS visitas, COUNT(DISTINCT session_id) AS sesiones
              FROM cw_analytics_pageviews
              WHERE viewed_at BETWEEN ? AND ?
                AND path LIKE '/mexico/ciudades/%'{$pvScope}
              GROUP BY path";
    $stmt = $conn->prepare($sqlPv);
    if ($stmt) {
        $stmt->bind_param('ss', $from, $to);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $slug = cw_seo_mexico_kpi_slug_from_path((string) ($row['path'] ?? ''));
            if ($slug === null || !isset($buckets[$slug])) {
                continue;
            }
            $buckets[$slug]['visitas'] += (int) ($row['visitas'] ?? 0);
            $buckets[$slug]['sesiones'] += (int) ($row['sesiones'] ?? 0);
        }
        $stmt->close();
    }

    $leadScope = cw_analytics_lead_scope_sql();
    $sqlLeads = "SELECT pagina_origen, pipeline_estado FROM leads
                 WHERE eliminado = 0 AND origen_web = 1
                   AND fecha_registro BETWEEN ? AND ?
                   AND pagina_origen IS NOT NULL AND pagina_origen != ''
                   AND pagina_origen LIKE '%/mexico/ciudades/%'{$leadScope}";
    $stmtL = $conn->prepare($sqlLeads);
    if ($stmtL) {
        $stmtL->bind_param('ss', $from, $to);
        $stmtL->execute();
        $resL = $stmtL->get_result();
        while ($row = $resL->fetch_assoc()) {
            $slug = cw_seo_mexico_kpi_slug_from_path((string) ($row['pagina_origen'] ?? ''));
            if ($slug === null || !isset($buckets[$slug])) {
                continue;
            }
            $buckets[$slug]['leads']++;
            $pe = (string) ($row['pipeline_estado'] ?? 'nuevo');
            if ($pe === 'cerrado') {
                $buckets[$slug]['cerrados']++;
            }
            if (in_array($pe, ['calificado', 'seguimiento', 'propuesta', 'cerrado'], true)) {
                $buckets[$slug]['calificados']++;
            }
        }
        $stmtL->close();
    }

    $sessScope = cw_analytics_url_scope_sql('landing_url');
    $sqlGeo = "SELECT LOWER(TRIM(COALESCE(geo_city,''))) AS city,
                      LOWER(TRIM(COALESCE(region,''))) AS region,
                      COUNT(*) AS c
               FROM cw_analytics_sessions
               WHERE first_seen BETWEEN ? AND ?{$sessScope}
                 AND (geo_city IS NOT NULL AND geo_city != '' OR region IS NOT NULL AND region != '')
               GROUP BY city, region";
    $stmtG = $conn->prepare($sqlGeo);
    if ($stmtG) {
        $stmtG->bind_param('ss', $from, $to);
        $stmtG->execute();
        $resG = $stmtG->get_result();
        while ($row = $resG->fetch_assoc()) {
            $city = (string) ($row['city'] ?? '');
            $region = (string) ($row['region'] ?? '');
            $count = (int) ($row['c'] ?? 0);
            foreach ($aliases as $slug => $names) {
                if (!isset($buckets[$slug])) {
                    continue;
                }
                foreach ($names as $name) {
                    if ($name === '') {
                        continue;
                    }
                    if ($city === $name || $region === $name
                        || ($city !== '' && str_contains($city, $name))
                        || ($region !== '' && str_contains($region, $name))) {
                        $buckets[$slug]['geo_local'] += $count;
                        break;
                    }
                }
            }
        }
        $stmtG->close();
    }

    $totals = [
        'visitas' => 0,
        'sesiones' => 0,
        'leads' => 0,
        'calificados' => 0,
        'cerrados' => 0,
        'geo_local' => 0,
        'plazas_con_trafico' => 0,
        'plazas_con_leads' => 0,
        'plazas_verdes' => 0,
    ];

    $rows = [];
    foreach ($plazasDef as $p) {
        $slug = (string) $p['slug'];
        $b = $buckets[$slug];
        $vis = (int) $b['visitas'];
        $leads = (int) $b['leads'];
        $cal = (int) $b['calificados'];
        $b['tasa_lead'] = $vis > 0 ? round(($leads / $vis) * 100, 2) : 0.0;
        $b['tasa_cierre'] = $leads > 0 ? round(((int) $b['cerrados'] / $leads) * 100, 2) : 0.0;

        if ($cal >= 1 || $leads >= 2) {
            $b['semaforo'] = 'green';
            $b['semaforo_label'] = 'En meta';
            $totals['plazas_verdes']++;
        } elseif ($vis >= 1 || $leads === 1) {
            $b['semaforo'] = 'amber';
            $b['semaforo_label'] = 'En proceso';
        } else {
            $b['semaforo'] = 'red';
            $b['semaforo_label'] = 'Sin movimiento';
        }

        if ($vis > 0) {
            $totals['plazas_con_trafico']++;
        }
        if ($leads > 0) {
            $totals['plazas_con_leads']++;
        }

        $totals['visitas'] += $vis;
        $totals['sesiones'] += (int) $b['sesiones'];
        $totals['leads'] += $leads;
        $totals['calificados'] += $cal;
        $totals['cerrados'] += (int) $b['cerrados'];
        $totals['geo_local'] += (int) $b['geo_local'];

        $rows[] = $b;
    }

    usort($rows, static function ($a, $b) {
        return ($b['leads'] <=> $a['leads'])
            ?: ($b['visitas'] <=> $a['visitas'])
            ?: strcmp((string) $a['city'], (string) $b['city']);
    });

    $totals['tasa_lead'] = $totals['visitas'] > 0
        ? round(($totals['leads'] / $totals['visitas']) * 100, 2)
        : 0.0;
    $totals['tasa_calificados'] = $totals['leads'] > 0
        ? round(($totals['calificados'] / $totals['leads']) * 100, 2)
        : 0.0;

    $targets = cw_seo_mexico_kpi_targets();
    $goalProgress = [];
    foreach ($targets['goals'] as $goal) {
        $key = (string) $goal['key'];
        $current = 0.0;
        if ($key === 'plazas_con_trafico') {
            $current = (float) $totals['plazas_con_trafico'];
        } elseif ($key === 'plazas_con_leads') {
            $current = (float) $totals['plazas_con_leads'];
        } elseif ($key === 'leads_geo_total') {
            $current = (float) $totals['leads'];
        } elseif ($key === 'tasa_calificados') {
            $current = (float) $totals['tasa_calificados'];
        }
        $target = (float) $goal['target'];
        $pct = $target > 0 ? min(100, (int) round(($current / $target) * 100)) : 0;
        $goalProgress[] = [
            'key' => $key,
            'label' => $goal['label'],
            'about' => $goal['about'],
            'unit' => $goal['unit'],
            'target' => $target,
            'current' => $current,
            'pct' => $pct,
            'ok' => $current >= $target,
        ];
    }

    return [
        'period' => ['from' => $from, 'to' => $to],
        'targets' => $targets,
        'indicators' => cw_seo_mexico_kpi_indicators(),
        'totals' => $totals,
        'goal_progress' => $goalProgress,
        'plazas' => $rows,
        'generated_at' => (new DateTimeImmutable('now', cw_hub_tz_mx()))->format('Y-m-d H:i:s'),
    ];
}

/**
 * Indicadores con copy orientado a URL (misma estructura que plazas).
 *
 * @return list<array{key:string,label:string,short:string,about:string,how:string,icon:string}>
 */
function cw_seo_mexico_kpi_indicators_url(): array
{
    return [
        [
            'key' => 'visitas',
            'label' => 'Visitas',
            'short' => 'Veces que se abrió esta URL',
            'about' => 'Cuántas veces se abrió esta página concreta en el periodo.',
            'how' => 'Cada carga de esa ruta cuenta como visita. Sirve para ver si esa URL ya recibe tráfico.',
            'icon' => 'bi-eye',
        ],
        [
            'key' => 'sesiones',
            'label' => 'Sesiones',
            'short' => 'Visitas distintas a esta URL',
            'about' => 'Cuántas sesiones distintas vieron esta URL (no solo recargas).',
            'how' => 'Una misma persona puede generar varias vistas; la sesión agrupa su visita.',
            'icon' => 'bi-people',
        ],
        [
            'key' => 'leads',
            'label' => 'Leads',
            'short' => 'Contactos desde esta URL',
            'about' => 'Personas que contactaron (WhatsApp/form) dejando esta página como origen.',
            'how' => 'Es la señal más clara de que esta URL convierte, no solo atrae visitas.',
            'icon' => 'bi-person-plus',
        ],
        [
            'key' => 'calificados',
            'label' => 'Calificados',
            'short' => 'Leads con avance comercial',
            'about' => 'Leads de esta URL que ya avanzaron en el CRM (calificado o más).',
            'how' => 'Mide calidad del contacto que genera esa página.',
            'icon' => 'bi-stars',
        ],
        [
            'key' => 'cerrados',
            'label' => 'Cerrados',
            'short' => 'Cierres desde esta URL',
            'about' => 'Leads originados en esta URL marcados como cerrados.',
            'how' => 'Resultado final del embudo atribuido a esta página.',
            'icon' => 'bi-check2-circle',
        ],
        [
            'key' => 'tasa_lead',
            'label' => 'Tasa lead',
            'short' => 'Conversión visita → lead',
            'about' => 'Porcentaje de visitas a esta URL que terminan en lead.',
            'how' => 'Fórmula: leads ÷ visitas × 100. Si hay tráfico y tasa baja, revisa mensaje o CTA de esa página.',
            'icon' => 'bi-percent',
        ],
        [
            'key' => 'geo_local',
            'label' => 'Visitantes locales',
            'short' => 'Geo local (páginas México)',
            'about' => 'En URLs de México: sesiones con ubicación estimada de esa ciudad/estado. En el resto del sitio suele ir en 0.',
            'how' => 'Señal aproximada (IP/navegador). Útil sobre todo en hubs y servicios por ciudad.',
            'icon' => 'bi-geo-alt',
        ],
        [
            'key' => 'semaforo',
            'label' => 'Semáforo',
            'short' => 'Estado de esta URL',
            'about' => 'Verde = ya genera contacto útil; ámbar = hay tráfico sin conversión clara; rojo = sin movimiento.',
            'how' => 'Verde: ≥1 lead calificado o ≥2 leads. Ámbar: hay visitas o 1 lead. Rojo: sin visitas ni leads.',
            'icon' => 'bi-traffic-light',
        ],
    ];
}

/**
 * @return array<string,string>
 */
function cw_seo_mexico_kpi_service_labels(): array
{
    return [
        'desarrollo-web' => 'Desarrollo web',
        'software-a-medida' => 'Software a medida',
        'ecommerce' => 'Ecommerce',
        'seo' => 'SEO',
        'inteligencia-artificial' => 'Inteligencia artificial',
    ];
}

/**
 * Categorías del select (toda la web).
 *
 * @return array<string,string>
 */
function cw_site_kpi_categories(): array
{
    return [
        'inicio' => 'Inicio',
        'servicios' => 'Servicios nacionales',
        'mexico' => 'México (todo)',
        'mexico_ciudad' => 'México · ciudades',
        'mexico_estado' => 'México · estados',
        'blog' => 'Blog (todo)',
        'blog_articulo' => 'Blog · artículos',
        'blog_categoria' => 'Blog · categorías',
        'conversion' => 'Contacto / conversión',
        'portafolio' => 'Proyectos / demos',
        'legal' => 'Legal / políticas',
        'otra' => 'Otras',
    ];
}

/**
 * Normaliza paths locales/sucios a ruta canónica del sitio.
 */
function cw_site_kpi_normalize_path(string $pathOrUrl): string
{
    $path = parse_url($pathOrUrl, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        $path = $pathOrUrl;
    }
    $path = strtolower(trim($path));
    if ($path === '') {
        return '/';
    }
    if ($path[0] !== '/') {
        $path = '/' . $path;
    }
    $prefixes = [
        '/sistemasconlineweb/conlineweb.com',
        '/proyecto/conlineweb.com',
        '/conlineweb.com',
    ];
    foreach ($prefixes as $prefix) {
        if (str_starts_with($path, $prefix)) {
            $path = substr($path, strlen($prefix));
            if ($path === '' || $path === false) {
                $path = '/';
            }
            if ($path[0] !== '/') {
                $path = '/' . $path;
            }
            break;
        }
    }
    $path = preg_replace('#/+#', '/', $path) ?? $path;
    if ($path === '/' || $path === '') {
        return '/';
    }
    if (!str_ends_with($path, '/')) {
        $path .= '/';
    }
    return $path;
}

/**
 * @return array<string,string> path => label
 */
function cw_site_kpi_national_service_labels(): array
{
    return [
        '/' => 'Inicio del sitio',
        '/paginas-web/' => 'Páginas web',
        '/diseno-de-paginas-web/' => 'Diseño de páginas web',
        '/desarrollo-de-software/' => 'Desarrollo de software',
        '/software-para-empresas/' => 'Software para empresas',
        '/tienda-online/' => 'Tienda online',
        '/seo/' => 'SEO',
        '/soluciones-inteligencia-artificial/' => 'Soluciones de IA',
        '/soluciones-corporativas/' => 'Soluciones corporativas',
        '/agencia-de-desarrollo-web/' => 'Agencia de desarrollo web',
        '/paginas-web-leon-gto/' => 'Páginas web León Gto',
        '/inteligencia-artificial-leon/' => 'IA en León',
        '/contacto/' => 'Contacto',
        '/asesoria-web-gratuita/' => 'Asesoría web gratuita',
        '/demos-y-precios/' => 'Demos y precios',
        '/tu-web-gratis/' => 'Tu web gratis',
        '/proyectos/' => 'Proyectos / portafolio',
        '/tienda-online-demo/' => 'Demo tienda online',
        '/proceso-de-trabajo/' => 'Proceso de trabajo',
        '/documentacion/' => 'Documentación',
        '/indice/' => 'Índice del sitio',
        '/mexico/' => 'Hub México',
        '/blog/' => 'Blog (listado)',
        '/centro-politicas/' => 'Centro de políticas',
        '/terminos-condiciones/' => 'Términos y condiciones',
        '/politica-privacidad/' => 'Política de privacidad',
        '/politica-propiedad-intelectual/' => 'Propiedad intelectual',
    ];
}

/**
 * Carga títulos de blog si el sitio local está disponible.
 *
 * @return array{posts: array<string,string>, categories: array<string,string>}
 */
function cw_site_kpi_blog_catalog(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = ['posts' => [], 'categories' => []];
    $factory = dirname(__DIR__, 2) . '/conlineweb.com/includes/blog/page-factory.php';
    if (!is_readable($factory)) {
        return $cache;
    }
    try {
        require_once $factory;
        if (function_exists('blog_posts')) {
            foreach (blog_posts() as $p) {
                $slug = (string) ($p['slug'] ?? '');
                $title = trim((string) ($p['title'] ?? ''));
                if ($slug !== '' && $title !== '') {
                    $cache['posts'][$slug] = $title;
                }
            }
        }
        if (function_exists('blog_categories')) {
            foreach (blog_categories() as $slug => $cat) {
                $name = trim((string) ($cat['name'] ?? $slug));
                if (is_string($slug) && $slug !== '') {
                    $cache['categories'][$slug] = $name !== '' ? $name : $slug;
                }
            }
        }
    } catch (Throwable $e) {
        // Catálogo opcional: si falla el require, seguimos con analytics.
    }
    return $cache;
}

/**
 * Etiqueta humana de cualquier URL del sitio.
 *
 * @return array{path:string,categoria:string,categoria_label:string,plaza_slug:string,plaza:string,tipo:string,label:string,about:string,url:string}
 */
function cw_seo_mexico_kpi_url_meta(string $path): array
{
    $path = cw_site_kpi_normalize_path($path);
    $cats = cw_site_kpi_categories();
    $national = cw_site_kpi_national_service_labels();
    $svcLabels = cw_seo_mexico_kpi_service_labels();
    $blog = cw_site_kpi_blog_catalog();
    $fullUrl = 'https://conlineweb.com' . ($path === '/' ? '/' : $path);

    $plazas = [];
    foreach (cw_seo_mexico_priority_plazas() as $p) {
        $plazas[(string) $p['slug']] = $p;
    }

    $base = [
        'path' => $path,
        'categoria' => 'otra',
        'categoria_label' => $cats['otra'],
        'plaza_slug' => '',
        'plaza' => '',
        'tipo' => 'otra',
        'label' => 'Página del sitio',
        'about' => 'URL de conlineweb.com.',
        'url' => $fullUrl,
    ];

    if ($path === '/') {
        return array_merge($base, [
            'categoria' => 'inicio',
            'categoria_label' => $cats['inicio'],
            'tipo' => 'inicio',
            'label' => 'Inicio del sitio',
            'about' => 'Home principal de ConlineWeb.',
        ]);
    }

    if (isset($national[$path])) {
        $label = $national[$path];
        if (in_array($path, ['/contacto/', '/asesoria-web-gratuita/', '/demos-y-precios/', '/tu-web-gratis/'], true)) {
            return array_merge($base, [
                'categoria' => 'conversion',
                'categoria_label' => $cats['conversion'],
                'tipo' => 'conversion',
                'label' => $label,
                'about' => 'Página orientada a contacto, cotización o conversión.',
            ]);
        }
        if (in_array($path, ['/proyectos/', '/tienda-online-demo/', '/documentacion/', '/proceso-de-trabajo/'], true)) {
            return array_merge($base, [
                'categoria' => 'portafolio',
                'categoria_label' => $cats['portafolio'],
                'tipo' => 'portafolio',
                'label' => $label,
                'about' => 'Evidencia, demos o proceso de trabajo.',
            ]);
        }
        if (in_array($path, ['/centro-politicas/', '/terminos-condiciones/', '/politica-privacidad/', '/politica-propiedad-intelectual/'], true)) {
            return array_merge($base, [
                'categoria' => 'legal',
                'categoria_label' => $cats['legal'],
                'tipo' => 'legal',
                'label' => $label,
                'about' => 'Página legal o de políticas.',
            ]);
        }
        if ($path === '/blog/') {
            return array_merge($base, [
                'categoria' => 'blog',
                'categoria_label' => $cats['blog'],
                'tipo' => 'blog_home',
                'label' => 'Blog (listado)',
                'about' => 'Listado principal del blog.',
            ]);
        }
        if ($path === '/mexico/') {
            return array_merge($base, [
                'categoria' => 'mexico',
                'categoria_label' => $cats['mexico'],
                'tipo' => 'mexico_hub',
                'label' => 'Hub México',
                'about' => 'Entrada a la cobertura local en México.',
            ]);
        }
        if ($path === '/indice/') {
            return array_merge($base, [
                'categoria' => 'otra',
                'categoria_label' => $cats['otra'],
                'tipo' => 'indice',
                'label' => $label,
                'about' => 'Índice / mapa de páginas del sitio.',
            ]);
        }
        return array_merge($base, [
            'categoria' => 'servicios',
            'categoria_label' => $cats['servicios'],
            'tipo' => 'servicio_nacional',
            'label' => $label,
            'about' => 'Landing nacional de servicio u oferta.',
        ]);
    }

    if (preg_match('#^/blog/articulo/([a-z0-9\-]+)/$#', $path, $m)) {
        $slug = $m[1];
        $title = $blog['posts'][$slug] ?? ucfirst(str_replace('-', ' ', $slug));
        return array_merge($base, [
            'categoria' => 'blog_articulo',
            'categoria_label' => $cats['blog_articulo'],
            'tipo' => 'blog_articulo',
            'label' => $title,
            'about' => 'Artículo del blog.',
        ]);
    }

    if (preg_match('#^/blog/categoria/([a-z0-9\-]+)/$#', $path, $m)) {
        $slug = $m[1];
        $name = $blog['categories'][$slug] ?? ucfirst(str_replace('-', ' ', $slug));
        return array_merge($base, [
            'categoria' => 'blog_categoria',
            'categoria_label' => $cats['blog_categoria'],
            'tipo' => 'blog_categoria',
            'label' => 'Categoría blog · ' . $name,
            'about' => 'Listado de artículos de esta categoría.',
        ]);
    }

    if (str_starts_with($path, '/blog/')) {
        return array_merge($base, [
            'categoria' => 'blog',
            'categoria_label' => $cats['blog'],
            'tipo' => 'blog',
            'label' => 'Blog',
            'about' => 'Página del blog.',
        ]);
    }

    if (preg_match('#^/mexico/ciudades/([a-z0-9\-]+)/servicios/([a-z0-9\-]+)/$#', $path, $m)) {
        $city = $m[1];
        $svc = $m[2];
        $cityName = (string) ($plazas[$city]['city'] ?? ucfirst(str_replace('-', ' ', $city)));
        $svcName = $svcLabels[$svc] ?? ucfirst(str_replace('-', ' ', $svc));
        return array_merge($base, [
            'categoria' => 'mexico_ciudad',
            'categoria_label' => $cats['mexico_ciudad'],
            'plaza_slug' => $city,
            'plaza' => $cityName,
            'tipo' => 'mexico_servicio_ciudad',
            'label' => $svcName . ' en ' . $cityName,
            'about' => 'Landing servicio × ciudad.',
        ]);
    }

    if (preg_match('#^/mexico/ciudades/([a-z0-9\-]+)/$#', $path, $m)) {
        $city = $m[1];
        $cityName = (string) ($plazas[$city]['city'] ?? ucfirst(str_replace('-', ' ', $city)));
        return array_merge($base, [
            'categoria' => 'mexico_ciudad',
            'categoria_label' => $cats['mexico_ciudad'],
            'plaza_slug' => $city,
            'plaza' => $cityName,
            'tipo' => 'mexico_hub_ciudad',
            'label' => 'Hub de ' . $cityName,
            'about' => 'Página principal de ciudad.',
        ]);
    }

    if (preg_match('#^/mexico/estados/([a-z0-9\-]+)/servicios/([a-z0-9\-]+)/$#', $path, $m)) {
        $estado = ucfirst(str_replace('-', ' ', $m[1]));
        $svcName = $svcLabels[$m[2]] ?? ucfirst(str_replace('-', ' ', $m[2]));
        return array_merge($base, [
            'categoria' => 'mexico_estado',
            'categoria_label' => $cats['mexico_estado'],
            'tipo' => 'mexico_servicio_estado',
            'label' => $svcName . ' en ' . $estado,
            'about' => 'Landing servicio × estado.',
        ]);
    }

    if (preg_match('#^/mexico/estados/([a-z0-9\-]+)/$#', $path, $m)) {
        $estado = ucfirst(str_replace('-', ' ', $m[1]));
        return array_merge($base, [
            'categoria' => 'mexico_estado',
            'categoria_label' => $cats['mexico_estado'],
            'tipo' => 'mexico_hub_estado',
            'label' => 'Estado · ' . $estado,
            'about' => 'Hub de estado en México.',
        ]);
    }

    // Rutas cortas legacy /mexico/{ciudad}/...
    if (preg_match('#^/mexico/([a-z0-9\-]+)/servicios/([a-z0-9\-]+)/$#', $path, $m)
        && !in_array($m[1], ['ciudades', 'estados'], true)) {
        $city = $m[1];
        $cityName = (string) ($plazas[$city]['city'] ?? ucfirst(str_replace('-', ' ', $city)));
        $svcName = $svcLabels[$m[2]] ?? ucfirst(str_replace('-', ' ', $m[2]));
        return array_merge($base, [
            'categoria' => 'mexico_ciudad',
            'categoria_label' => $cats['mexico_ciudad'],
            'plaza_slug' => $city,
            'plaza' => $cityName,
            'tipo' => 'mexico_servicio_corto',
            'label' => $svcName . ' en ' . $cityName . ' (ruta corta)',
            'about' => 'URL corta de servicio × ciudad (canónica suele ser /mexico/ciudades/...).',
        ]);
    }

    if (preg_match('#^/mexico/([a-z0-9\-]+)/$#', $path, $m)
        && !in_array($m[1], ['ciudades', 'estados'], true)) {
        $city = $m[1];
        $cityName = (string) ($plazas[$city]['city'] ?? ucfirst(str_replace('-', ' ', $city)));
        return array_merge($base, [
            'categoria' => 'mexico',
            'categoria_label' => $cats['mexico'],
            'plaza_slug' => isset($plazas[$city]) ? $city : '',
            'plaza' => $cityName,
            'tipo' => 'mexico_corto',
            'label' => 'México · ' . $cityName,
            'about' => 'URL geo México (ruta corta o estado/ciudad).',
        ]);
    }

    if (str_starts_with($path, '/mexico/')) {
        return array_merge($base, [
            'categoria' => 'mexico',
            'categoria_label' => $cats['mexico'],
            'tipo' => 'mexico',
            'label' => 'Página México',
            'about' => 'Cobertura local México.',
        ]);
    }

    if (preg_match('#(contacto|asesoria|cotiz|whatsapp|demo|gratis)#', $path)) {
        return array_merge($base, [
            'categoria' => 'conversion',
            'categoria_label' => $cats['conversion'],
            'tipo' => 'conversion',
            'label' => cw_site_kpi_pretty_slug($path),
            'about' => 'Página con intención de contacto o conversión.',
        ]);
    }

    if (preg_match('#(politica|terminos|privacidad|aviso-legal|cookies)#', $path)) {
        return array_merge($base, [
            'categoria' => 'legal',
            'categoria_label' => $cats['legal'],
            'tipo' => 'legal',
            'label' => cw_site_kpi_pretty_slug($path),
            'about' => 'Página legal o de políticas.',
        ]);
    }

    $pretty = cw_site_kpi_pretty_slug($path);
    return array_merge($base, [
        'label' => $pretty !== '' ? $pretty : 'Página del sitio',
        'about' => 'URL de conlineweb.com sin categoría específica.',
    ]);
}

function cw_site_kpi_pretty_slug(string $path): string
{
    $raw = trim(str_replace('-', ' ', trim($path, '/')));
    if ($raw === '') {
        return '';
    }
    return mb_convert_case($raw, MB_CASE_TITLE, 'UTF-8');
}

/**
 * Inventario sembrado: home, servicios, México prioritario, blog completo.
 *
 * @return list<string>
 */
function cw_seo_mexico_kpi_priority_paths(): array
{
    $paths = array_keys(cw_site_kpi_national_service_labels());
    $services = array_keys(cw_seo_mexico_kpi_service_labels());
    foreach (cw_seo_mexico_priority_plazas() as $p) {
        $slug = (string) $p['slug'];
        $paths[] = '/mexico/ciudades/' . $slug . '/';
        foreach ($services as $svc) {
            $paths[] = '/mexico/ciudades/' . $slug . '/servicios/' . $svc . '/';
        }
    }
    $blog = cw_site_kpi_blog_catalog();
    foreach ($blog['posts'] as $slug => $_title) {
        $paths[] = '/blog/articulo/' . $slug . '/';
    }
    foreach ($blog['categories'] as $slug => $_name) {
        $paths[] = '/blog/categoria/' . $slug . '/';
    }
    return array_values(array_unique(array_map('cw_site_kpi_normalize_path', $paths)));
}

/**
 * @return array{semaforo:string,semaforo_label:string}
 */
function cw_seo_mexico_kpi_semaforo(int $visitas, int $leads, int $calificados): array
{
    if ($calificados >= 1 || $leads >= 2) {
        return ['semaforo' => 'green', 'semaforo_label' => 'En meta'];
    }
    if ($visitas >= 1 || $leads === 1) {
        return ['semaforo' => 'amber', 'semaforo_label' => 'En proceso'];
    }
    return ['semaforo' => 'red', 'semaforo_label' => 'Sin movimiento'];
}

/**
 * ¿La categoría del filtro aplica a esta URL?
 */
function cw_site_kpi_categoria_match(string $filter, string $categoria): bool
{
    if ($filter === '' || $filter === 'todas') {
        return true;
    }
    if ($filter === 'mexico') {
        return str_starts_with($categoria, 'mexico');
    }
    if ($filter === 'blog') {
        return str_starts_with($categoria, 'blog');
    }
    return $categoria === $filter;
}

/**
 * Rendimiento por URL de toda la web (categorizado).
 *
 * @return array{
 *   period:array{from:string,to:string},
 *   indicators:list<array<string,mixed>>,
 *   totals:array<string,int|float>,
 *   urls:list<array<string,mixed>>,
 *   categories:array<string,string>,
 *   category_counts:array<string,int>,
 *   categoria_filter:?string,
 *   plaza_filter:?string,
 *   generated_at:string
 * }
 */
function cw_seo_mexico_kpis_urls_snapshot(
    mysqli $conn,
    string $from,
    string $to,
    ?string $plazaFilter = null,
    ?string $categoriaFilter = null
): array {
    $plazaFilter = $plazaFilter !== null
        ? (preg_replace('/[^a-z0-9\-]/', '', strtolower($plazaFilter)) ?? '')
        : '';
    if ($plazaFilter === '') {
        $plazaFilter = null;
    }
    $categoriaFilter = $categoriaFilter !== null
        ? (preg_replace('/[^a-z0-9_]/', '', strtolower($categoriaFilter)) ?? '')
        : '';
    if ($categoriaFilter === '' || $categoriaFilter === 'todas') {
        $categoriaFilter = null;
    }

    $aliases = cw_seo_mexico_kpi_geo_aliases(cw_seo_mexico_priority_plazas());
    $buckets = [];

    $ensure = static function (string $path) use (&$buckets): string {
        $norm = cw_site_kpi_normalize_path($path);
        if (isset($buckets[$norm])) {
            return $norm;
        }
        $meta = cw_seo_mexico_kpi_url_meta($norm);
        $buckets[$norm] = [
            'path' => $norm === '/' ? '/' : $norm,
            'url' => $meta['url'],
            'categoria' => $meta['categoria'],
            'categoria_label' => $meta['categoria_label'],
            'plaza_slug' => $meta['plaza_slug'],
            'plaza' => $meta['plaza'],
            'tipo' => $meta['tipo'],
            'label' => $meta['label'],
            'about' => $meta['about'],
            'visitas' => 0,
            'sesiones' => 0,
            'leads' => 0,
            'calificados' => 0,
            'cerrados' => 0,
            'geo_local' => 0,
            'tasa_lead' => 0.0,
            'tasa_cierre' => 0.0,
            'semaforo' => 'red',
            'semaforo_label' => 'Sin movimiento',
        ];
        return $norm;
    };

    foreach (cw_seo_mexico_kpi_priority_paths() as $p) {
        $ensure($p);
    }

    $pvScope = cw_analytics_url_scope_sql('p.url');
    $sqlPv = "SELECT p.path, COUNT(*) AS visitas, COUNT(DISTINCT p.session_id) AS sesiones
              FROM cw_analytics_pageviews p
              WHERE p.viewed_at BETWEEN ? AND ?
                AND p.path IS NOT NULL AND p.path != ''{$pvScope}
              GROUP BY p.path";
    $stmt = $conn->prepare($sqlPv);
    if ($stmt) {
        $stmt->bind_param('ss', $from, $to);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $path = (string) ($row['path'] ?? '');
            if ($path === '') {
                continue;
            }
            $norm = $ensure($path);
            $buckets[$norm]['visitas'] += (int) ($row['visitas'] ?? 0);
            $buckets[$norm]['sesiones'] += (int) ($row['sesiones'] ?? 0);
        }
        $stmt->close();
    }

    $leadScope = cw_analytics_lead_scope_sql();
    $sqlLeads = "SELECT pagina_origen, pipeline_estado FROM leads
                 WHERE eliminado = 0 AND origen_web = 1
                   AND fecha_registro BETWEEN ? AND ?
                   AND pagina_origen IS NOT NULL AND pagina_origen != ''{$leadScope}";
    $stmtL = $conn->prepare($sqlLeads);
    if ($stmtL) {
        $stmtL->bind_param('ss', $from, $to);
        $stmtL->execute();
        $resL = $stmtL->get_result();
        while ($row = $resL->fetch_assoc()) {
            $path = parse_url((string) ($row['pagina_origen'] ?? ''), PHP_URL_PATH);
            if (!is_string($path) || $path === '') {
                continue;
            }
            $norm = $ensure($path);
            $buckets[$norm]['leads']++;
            $pe = (string) ($row['pipeline_estado'] ?? 'nuevo');
            if ($pe === 'cerrado') {
                $buckets[$norm]['cerrados']++;
            }
            if (in_array($pe, ['calificado', 'seguimiento', 'propuesta', 'cerrado'], true)) {
                $buckets[$norm]['calificados']++;
            }
        }
        $stmtL->close();
    }

    $sqlGeo = "SELECT p.path,
                      LOWER(TRIM(COALESCE(s.geo_city,''))) AS city,
                      LOWER(TRIM(COALESCE(s.region,''))) AS region,
                      COUNT(DISTINCT p.session_id) AS c
               FROM cw_analytics_pageviews p
               INNER JOIN cw_analytics_sessions s ON s.session_id = p.session_id
               WHERE p.viewed_at BETWEEN ? AND ?
                 AND p.path IS NOT NULL AND p.path != ''{$pvScope}
                 AND (s.geo_city IS NOT NULL AND s.geo_city != '' OR s.region IS NOT NULL AND s.region != '')
               GROUP BY p.path, city, region";
    $stmtG = $conn->prepare($sqlGeo);
    if ($stmtG) {
        $stmtG->bind_param('ss', $from, $to);
        $stmtG->execute();
        $resG = $stmtG->get_result();
        while ($row = $resG->fetch_assoc()) {
            $path = (string) ($row['path'] ?? '');
            if ($path === '') {
                continue;
            }
            $norm = $ensure($path);
            $slug = (string) ($buckets[$norm]['plaza_slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $names = $aliases[$slug] ?? [];
            $city = (string) ($row['city'] ?? '');
            $region = (string) ($row['region'] ?? '');
            $count = (int) ($row['c'] ?? 0);
            foreach ($names as $name) {
                if ($name === '') {
                    continue;
                }
                if ($city === $name || $region === $name
                    || ($city !== '' && str_contains($city, $name))
                    || ($region !== '' && str_contains($region, $name))) {
                    $buckets[$norm]['geo_local'] += $count;
                    break;
                }
            }
        }
        $stmtG->close();
    }

    $categoryCounts = [];
    foreach (array_keys(cw_site_kpi_categories()) as $ck) {
        $categoryCounts[$ck] = 0;
    }

    $totals = [
        'visitas' => 0,
        'sesiones' => 0,
        'leads' => 0,
        'calificados' => 0,
        'cerrados' => 0,
        'geo_local' => 0,
        'urls_con_trafico' => 0,
        'urls_con_leads' => 0,
        'urls_verdes' => 0,
        'urls_total' => 0,
    ];

    $rows = [];
    foreach ($buckets as $b) {
        $cat = (string) ($b['categoria'] ?? 'otra');
        if (isset($categoryCounts[$cat])) {
            $categoryCounts[$cat]++;
        } else {
            $categoryCounts['otra'] = ($categoryCounts['otra'] ?? 0) + 1;
        }

        if ($categoriaFilter !== null && !cw_site_kpi_categoria_match($categoriaFilter, $cat)) {
            continue;
        }
        if ($plazaFilter !== null && ($b['plaza_slug'] ?? '') !== $plazaFilter) {
            continue;
        }

        $vis = (int) $b['visitas'];
        $leads = (int) $b['leads'];
        $cal = (int) $b['calificados'];
        $b['tasa_lead'] = $vis > 0 ? round(($leads / $vis) * 100, 2) : 0.0;
        $b['tasa_cierre'] = $leads > 0 ? round(((int) $b['cerrados'] / $leads) * 100, 2) : 0.0;
        $sem = cw_seo_mexico_kpi_semaforo($vis, $leads, $cal);
        $b['semaforo'] = $sem['semaforo'];
        $b['semaforo_label'] = $sem['semaforo_label'];

        if ($vis > 0) {
            $totals['urls_con_trafico']++;
        }
        if ($leads > 0) {
            $totals['urls_con_leads']++;
        }
        if ($sem['semaforo'] === 'green') {
            $totals['urls_verdes']++;
        }

        $totals['visitas'] += $vis;
        $totals['sesiones'] += (int) $b['sesiones'];
        $totals['leads'] += $leads;
        $totals['calificados'] += $cal;
        $totals['cerrados'] += (int) $b['cerrados'];
        $totals['geo_local'] += (int) $b['geo_local'];
        $totals['urls_total']++;

        $rows[] = $b;
    }

    usort($rows, static function ($a, $b) {
        return ($b['leads'] <=> $a['leads'])
            ?: ($b['visitas'] <=> $a['visitas'])
            ?: strcmp((string) $a['path'], (string) $b['path']);
    });

    $totals['tasa_lead'] = $totals['visitas'] > 0
        ? round(($totals['leads'] / $totals['visitas']) * 100, 2)
        : 0.0;

    // Conteos agregados para filtros padre
    $categoryCounts['mexico'] = ($categoryCounts['mexico'] ?? 0)
        + ($categoryCounts['mexico_ciudad'] ?? 0)
        + ($categoryCounts['mexico_estado'] ?? 0);
    $categoryCounts['blog'] = ($categoryCounts['blog'] ?? 0)
        + ($categoryCounts['blog_articulo'] ?? 0)
        + ($categoryCounts['blog_categoria'] ?? 0);

    return [
        'period' => ['from' => $from, 'to' => $to],
        'indicators' => cw_seo_mexico_kpi_indicators_url(),
        'totals' => $totals,
        'urls' => $rows,
        'categories' => cw_site_kpi_categories(),
        'category_counts' => $categoryCounts,
        'categoria_filter' => $categoriaFilter,
        'plaza_filter' => $plazaFilter,
        'generated_at' => (new DateTimeImmutable('now', cw_hub_tz_mx()))->format('Y-m-d H:i:s'),
    ];
}

/**
 * Botón clicable de conteo (abre modal de registros).
 *
 * @param array<string,string> $attrs data-plaza / data-path / data-categoria
 */
function cw_seo_mexico_kpi_lead_btn_html(int $count, string $status, string $scope, array $attrs = []): string
{
    $count = max(0, $count);
    $status = preg_replace('/[^a-z_]/', '', strtolower($status)) ?? 'leads';
    $scope = preg_replace('/[^a-z_]/', '', strtolower($scope)) ?? 'site';
    $cls = 'seo-lead-count' . ($count === 0 ? ' is-zero' : '');
    // onclick inline como respaldo si el listener delegado falla
    $html = '<button type="button" class="' . htmlspecialchars($cls) . '" data-seo-leads="1"'
        . ' data-status="' . htmlspecialchars($status) . '"'
        . ' data-scope="' . htmlspecialchars($scope) . '"'
        . ' data-count="' . $count . '"'
        . ' title="Ver registros"'
        . ' onclick="return window.SeoLeadsModal ? (window.SeoLeadsModal.open(this), false) : false;"';
    foreach ($attrs as $k => $v) {
        if ($v === null || $v === '') {
            continue;
        }
        $html .= ' ' . htmlspecialchars((string) $k) . '="' . htmlspecialchars((string) $v) . '"';
    }
    $html .= '>' . number_format($count) . '</button>';
    return $html;
}

/**
 * ¿El pipeline cuenta como calificado / cerrado (misma lógica que KPIs)?
 *
 * @return array{is_lead:bool,is_calificado:bool,is_cerrado:bool}
 */
function cw_seo_mexico_kpi_lead_status_flags(?string $pipelineEstado): array
{
    $pe = strtolower(trim((string) $pipelineEstado));
    if ($pe === '' || $pe === 'nuevo') {
        $pe = 'lead';
    }
    $isCerrado = in_array($pe, ['cerrado', 'cierre'], true);
    $isCalificado = $isCerrado || in_array($pe, ['calificado', 'seguimiento', 'propuesta'], true);

    return [
        'is_lead' => true,
        'is_calificado' => $isCalificado,
        'is_cerrado' => $isCerrado,
    ];
}

/**
 * Lista leads del cruce KPI (plaza / URL / totales).
 *
 * @return array{ok:bool,title:string,status:string,count:int,leads:list<array<string,mixed>>,error?:string}
 */
function cw_seo_mexico_kpis_leads_list(
    mysqli $conn,
    string $from,
    string $to,
    string $status = 'leads',
    string $scope = 'site',
    ?string $plaza = null,
    ?string $path = null,
    ?string $categoria = null
): array {
    $status = preg_replace('/[^a-z_]/', '', strtolower($status)) ?? 'leads';
    if (!in_array($status, ['leads', 'calificados', 'cerrados'], true)) {
        $status = 'leads';
    }
    $scope = preg_replace('/[^a-z_]/', '', strtolower($scope)) ?? 'site';
    $plaza = $plaza !== null ? (preg_replace('/[^a-z0-9\-]/', '', strtolower($plaza)) ?? '') : '';
    $path = $path !== null ? cw_site_kpi_normalize_path($path) : '';
    $categoria = $categoria !== null
        ? (preg_replace('/[^a-z0-9_]/', '', strtolower($categoria)) ?? '')
        : '';

    $labels = [
        'leads' => 'Leads',
        'calificados' => 'Calificados',
        'cerrados' => 'Cerrados',
    ];
    $title = $labels[$status];

    $leadScope = cw_analytics_lead_scope_sql();
    $sql = "SELECT id, nombre, correo, telefono, servicio, pagina_origen, fuente,
                   pipeline_estado, fecha_registro, session_id
            FROM leads
            WHERE eliminado = 0 AND origen_web = 1
              AND fecha_registro BETWEEN ? AND ?{$leadScope}
            ORDER BY fecha_registro DESC
            LIMIT 500";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['ok' => false, 'title' => $title, 'status' => $status, 'count' => 0, 'leads' => [], 'error' => 'No se pudo consultar leads'];
    }
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $res = $stmt->get_result();

    $pipelineLabels = defined('CW_HUB_PIPELINE') ? CW_HUB_PIPELINE : [];
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $origen = (string) ($row['pagina_origen'] ?? '');
        $norm = $origen !== '' ? cw_site_kpi_normalize_path($origen) : '';
        $meta = $norm !== '' ? cw_seo_mexico_kpi_url_meta($norm) : [
            'path' => '',
            'plaza_slug' => '',
            'categoria' => 'otra',
            'label' => 'Sin página de origen',
        ];

        if ($scope === 'plaza' && $plaza !== '') {
            if (($meta['plaza_slug'] ?? '') !== $plaza && !str_contains($norm, '/mexico/ciudades/' . $plaza . '/')) {
                continue;
            }
        } elseif ($scope === 'path' && $path !== '') {
            if ($path === '/') {
                if ($norm !== '/') {
                    continue;
                }
            } elseif ($norm !== $path && !str_starts_with($norm, rtrim($path, '/') . '/')) {
                continue;
            }
        } elseif ($scope === 'mexico') {
            if (!str_starts_with($norm, '/mexico/')) {
                continue;
            }
        }
        // scope=site → todos

        if ($categoria !== '' && $categoria !== 'todas') {
            $cat = (string) ($meta['categoria'] ?? 'otra');
            if (!cw_site_kpi_categoria_match($categoria, $cat)) {
                continue;
            }
        }

        $flags = cw_seo_mexico_kpi_lead_status_flags($row['pipeline_estado'] ?? '');
        if ($status === 'calificados' && !$flags['is_calificado']) {
            continue;
        }
        if ($status === 'cerrados' && !$flags['is_cerrado']) {
            continue;
        }

        $pe = strtolower(trim((string) ($row['pipeline_estado'] ?? 'lead')));
        if ($pe === '' || $pe === 'nuevo') {
            $pe = 'lead';
        }
        $peLabel = (string) ($pipelineLabels[$pe] ?? ucfirst($pe));

        $out[] = [
            'id' => (int) ($row['id'] ?? 0),
            'nombre' => trim((string) ($row['nombre'] ?? '')) ?: '—',
            'correo' => trim((string) ($row['correo'] ?? '')) ?: '—',
            'telefono' => trim((string) ($row['telefono'] ?? '')) ?: '—',
            'servicio' => trim((string) ($row['servicio'] ?? '')) ?: '—',
            'pipeline_estado' => $pe,
            'pipeline_label' => $peLabel,
            'pagina_origen' => $origen,
            'path' => $norm !== '' ? $norm : '—',
            'path_label' => (string) ($meta['label'] ?? '—'),
            'plaza' => (string) ($meta['plaza'] ?? ''),
            'fecha_registro' => function_exists('cw_hub_format_datetime')
                ? cw_hub_format_datetime((string) ($row['fecha_registro'] ?? ''))
                : (string) ($row['fecha_registro'] ?? ''),
            'fecha_sort' => (string) ($row['fecha_registro'] ?? ''),
            'detalle_url' => 'leads/detalle.php?id=' . (int) ($row['id'] ?? 0),
        ];
    }
    $stmt->close();

    if ($scope === 'plaza' && $plaza !== '') {
        $title .= ' · ' . ucfirst(str_replace('-', ' ', $plaza));
    } elseif ($scope === 'path' && $path !== '') {
        $title .= ' · ' . $path;
    } elseif ($scope === 'mexico') {
        $title .= ' · México geo';
    }

    return [
        'ok' => true,
        'title' => $title,
        'status' => $status,
        'count' => count($out),
        'leads' => $out,
        'period' => ['from' => $from, 'to' => $to],
        'scope' => $scope,
    ];
}

/**
 * Marca kpi_90d como hecha y deja las metas + enlace al panel.
 *
 * @return array{ok:bool,applied:bool,complete?:bool,error?:string}
 */
function cw_seo_mexico_checklist_apply_kpi_90d(mysqli $conn, int $userId = 0): array
{
    $targets = cw_seo_mexico_kpi_targets();
    $detail = [
        'status' => 'completo',
        'panel' => $targets['panel_url'],
        'targets' => $targets,
        'indicators' => array_map(static function ($i) {
            return [
                'key' => $i['key'],
                'label' => $i['label'],
                'about' => $i['about'],
            ];
        }, cw_seo_mexico_kpi_indicators()),
        'measured_live' => ['visitas', 'sesiones', 'leads', 'calificados', 'cerrados', 'tasa_lead', 'geo_local', 'semaforo'],
        'measured_external' => ['gsc_impressions', 'gsc_clicks', 'gsc_position'],
    ];

    $summary = 'KPIs 90 días definidos con panel en vivo por plaza';
    $detailText = "Panel: analytics/seo_mexico_plazas.php\n"
        . "Metas: ≥5 plazas con tráfico, ≥3 con leads, ≥8 leads geo, ≥25% calificados.\n"
        . "Semáforo por plaza: verde (≥1 calificado o ≥2 leads), ámbar (tráfico sin tracción), rojo (sin movimiento).\n"
        . 'GSC (impresiones/clics) se revisa aparte de forma mensual.';

    $res = cw_seo_mexico_checklist_complete_task(
        $conn,
        'kpi_90d',
        $summary,
        $detailText,
        $userId,
        $detail,
        'Panel Plazas 90d con metas y medición en vivo de visitas/leads.'
    );
    $res['complete'] = !empty($res['ok']);
    return $res;
}
