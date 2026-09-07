<?php
/**
 * Directorio maestro SEO/GEO — solo URLs que SÍ se indexan y aportan.
 * Cada fila: keyword · por qué se queda · URLs reales (desplegables en patrones).
 */

declare(strict_types=1);

function cw_seo_public_site_root(): string
{
    static $root = null;
    if ($root !== null) {
        return $root;
    }
    $candidates = [
        dirname(__DIR__, 2) . '/conlineweb.com',
        dirname(__DIR__) . '/../conlineweb.com',
    ];
    foreach ($candidates as $c) {
        if (is_dir($c) && is_file($c . '/sitemap.xml')) {
            $root = $c;
            return $root;
        }
    }
    $root = dirname(__DIR__, 2) . '/conlineweb.com';
    return $root;
}

/** @return list<string> */
function cw_seo_sitemap_paths(string $file = 'sitemap.xml'): array
{
    $path = cw_seo_public_site_root() . '/' . ltrim($file, '/');
    if (!is_file($path)) {
        return [];
    }
    $xml = (string) file_get_contents($path);
    if ($xml === '') {
        return [];
    }
    preg_match_all('#<loc>https?://(?:www\.)?conlineweb\.com([^<]*)</loc>#i', $xml, $m);
    $out = [];
    foreach ($m[1] as $p) {
        $p = rawurldecode((string) $p);
        if ($p === '' || $p === '/') {
            $out[] = '/';
            continue;
        }
        if ($p[0] !== '/') {
            $p = '/' . $p;
        }
        $out[] = $p;
    }
    return array_values(array_unique($out));
}

/**
 * @return array{estados:array<string,string>,ciudades:array<string,string>,servicios:array<string,string>}
 */
function cw_seo_dir_geo_labels(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $estados = [];
    $ciudades = [];
    $servicios = [
        'desarrollo-web' => 'Desarrollo web',
        'ecommerce' => 'E-commerce',
        'software-a-medida' => 'Software a medida',
        'inteligencia-artificial' => 'Inteligencia artificial',
        'seo' => 'SEO / GEO',
    ];
    $pf = cw_seo_public_site_root() . '/includes/mexico/page-factory.php';
    if (is_file($pf)) {
        require_once $pf;
        if (function_exists('mx_locations')) {
            $data = mx_locations();
            foreach (($data['estados'] ?? []) as $slug => $row) {
                $estados[(string) $slug] = (string) ($row['name'] ?? $slug);
            }
            foreach (($data['ciudades'] ?? []) as $slug => $row) {
                $ciudades[(string) $slug] = (string) ($row['name'] ?? $slug);
            }
            foreach (($data['servicios'] ?? []) as $slug => $row) {
                if (isset($servicios[$slug])) {
                    continue;
                }
                $servicios[(string) $slug] = (string) ($row['name'] ?? $slug);
            }
        }
    }
    $cache = compact('estados', 'ciudades', 'servicios');
    return $cache;
}

/**
 * @param list<string> $sitemapPaths
 * @return list<array{url:string,label:string,keyword:string,in_sitemap:bool}>
 */
function cw_seo_dir_expand_children(string $pattern, array $sitemapPaths, string $kwTemplate): array
{
    $geo = cw_seo_dir_geo_labels();
    $children = [];

    $push = static function (string $url, string $label, string $keyword) use (&$children, $sitemapPaths): void {
        $norm = $url;
        if ($norm !== '/' && !str_ends_with($norm, '/')) {
            $norm .= '/';
        }
        $in = in_array($norm, $sitemapPaths, true) || in_array(rtrim($norm, '/') ?: '/', $sitemapPaths, true);
        $children[] = [
            'url' => $norm,
            'label' => $label,
            'keyword' => $keyword,
            'in_sitemap' => $in,
        ];
    };

    if ($pattern === '/mexico/estados/{estado}/') {
        foreach ($geo['estados'] as $slug => $name) {
            $push(
                "/mexico/estados/{$slug}/",
                $name,
                str_replace('{estado}', $name, $kwTemplate)
            );
        }
    } elseif ($pattern === '/mexico/estados/{estado}/servicios/') {
        foreach ($geo['estados'] as $slug => $name) {
            $push(
                "/mexico/estados/{$slug}/servicios/",
                "Servicios · {$name}",
                str_replace('{estado}', $name, $kwTemplate)
            );
        }
    } elseif ($pattern === '/mexico/estados/{estado}/servicios/{svc}/') {
        foreach ($geo['estados'] as $slug => $name) {
            foreach ($geo['servicios'] as $svc => $svcName) {
                $push(
                    "/mexico/estados/{$slug}/servicios/{$svc}/",
                    "{$svcName} · {$name}",
                    str_replace(['{servicio}', '{estado}', '{svc}'], [$svcName, $name, $svcName], $kwTemplate)
                );
            }
        }
    } elseif ($pattern === '/mexico/ciudades/{ciudad}/') {
        foreach ($geo['ciudades'] as $slug => $name) {
            $push(
                "/mexico/ciudades/{$slug}/",
                $name,
                str_replace('{ciudad}', $name, $kwTemplate)
            );
        }
    } elseif ($pattern === '/mexico/ciudades/{ciudad}/servicios/') {
        foreach ($geo['ciudades'] as $slug => $name) {
            $push(
                "/mexico/ciudades/{$slug}/servicios/",
                "Servicios · {$name}",
                str_replace('{ciudad}', $name, $kwTemplate)
            );
        }
    } elseif ($pattern === '/mexico/ciudades/{ciudad}/servicios/{svc}/') {
        foreach ($geo['ciudades'] as $slug => $name) {
            foreach ($geo['servicios'] as $svc => $svcName) {
                $push(
                    "/mexico/ciudades/{$slug}/servicios/{$svc}/",
                    "{$svcName} · {$name}",
                    str_replace(['{servicio}', '{ciudad}', '{svc}'], [$svcName, $name, $svcName], $kwTemplate)
                );
            }
        }
    } elseif ($pattern === '/blog/articulo/{slug}/') {
        foreach ($sitemapPaths as $p) {
            if (!preg_match('#^/blog/articulo/([^/]+)/?$#', $p, $m)) {
                continue;
            }
            $slug = $m[1];
            $label = ucwords(str_replace('-', ' ', $slug));
            $push($p, $label, $label);
        }
        usort($children, static fn($a, $b) => strcmp($a['url'], $b['url']));
    }

    return $children;
}

/** @return array{in_sitemap:bool,count:int} */
function cw_seo_dir_sitemap_hit(string $urlOrPattern, array $sitemapPaths): array
{
    $urlOrPattern = trim($urlOrPattern);
    if ($urlOrPattern === '') {
        return ['in_sitemap' => false, 'count' => 0];
    }
    if (str_contains($urlOrPattern, '{')) {
        $rx = '#^' . preg_quote($urlOrPattern, '#') . '$#';
        $rx = str_replace(
            ['\{estado\}', '\{ciudad\}', '\{svc\}', '\{slug\}', '\{plaza\}'],
            ['[^/]+', '[^/]+', '[^/]+', '[^/]+', '[^/]+'],
            $rx
        );
        $n = 0;
        foreach ($sitemapPaths as $p) {
            if (preg_match($rx, $p)) {
                $n++;
            }
        }
        return ['in_sitemap' => $n > 0, 'count' => $n];
    }
    $norm = $urlOrPattern;
    if ($norm !== '/' && !str_ends_with($norm, '/')) {
        $norm .= '/';
    }
    $hit = in_array($norm, $sitemapPaths, true) || in_array(rtrim($norm, '/') ?: '/', $sitemapPaths, true);
    return ['in_sitemap' => $hit, 'count' => $hit ? 1 : 0];
}

/**
 * Catálogo: solo lo que SÍ se queda indexado y aporta al objetivo.
 *
 * @return list<array>
 */
function cw_seo_sitemap_directory_catalog(): array
{
    return [
        [
            'id' => 'money',
            'title' => 'A · Money pages nacionales',
            'role' => 'Keyword comercial nacional — 1 URL fuerte por servicio',
            'decision' => 'INDEXAR',
            'tone' => 'keep',
            'objective' => 'Captar demanda B2B México y convertir a cotización WhatsApp',
            'items' => [
                [
                    'url' => '/',
                    'keyword' => 'agencia digital / desarrollo web México (marca)',
                    'why' => 'Home de autoridad: concentra marca y reparte a las money pages + cotización.',
                    'action' => 'Indexar',
                ],
                [
                    'url' => '/paginas-web/',
                    'keyword' => 'páginas web corporativas México',
                    'why' => 'Ganadora de la keyword nacional de web; es la página que debe rankear y convertir.',
                    'action' => 'Indexar',
                ],
                [
                    'url' => '/tienda-online/',
                    'keyword' => 'tienda online / e-commerce México',
                    'why' => 'Ganadora nacional e-commerce; captura intención de venta 24/7.',
                    'action' => 'Indexar',
                ],
                [
                    'url' => '/software-para-empresas/',
                    'keyword' => 'software a medida / software para empresas México',
                    'why' => 'Ganadora nacional software; lead B2B de sistemas a medida.',
                    'action' => 'Indexar',
                ],
                [
                    'url' => '/soluciones-inteligencia-artificial/',
                    'keyword' => 'inteligencia artificial para empresas México',
                    'why' => 'Ganadora nacional IA; demanda de automatización y chatbots.',
                    'action' => 'Indexar',
                ],
                [
                    'url' => '/seo/',
                    'keyword' => 'SEO y GEO / posicionamiento web México',
                    'why' => 'Ganadora nacional SEO+GEO; posiciona el servicio de visibilidad.',
                    'action' => 'Indexar',
                ],
                [
                    'url' => '/hosting-administrado/',
                    'keyword' => 'hosting empresarial administrado México',
                    'why' => 'Money page de hosting B2B. cPanel, VPS Linux WHM/cPanel y servidor dedicado Linux WHM/cPanel con NVMe. Sitios, bases MySQL, correo y sistemas operados por ConlineWeb.',
                    'action' => 'Indexar',
                ],
            ],
        ],
        [
            'id' => 'conversion',
            'title' => 'B · Conversión y confianza',
            'role' => 'No pelean keyword de servicio; empujan a cotizar',
            'decision' => 'INDEXAR',
            'tone' => 'keep',
            'objective' => 'Generar confianza corporativa y cerrar a WhatsApp / asesoría',
            'items' => [
                [
                    'url' => '/asesoria-web-gratuita/',
                    'keyword' => 'asesoría web gratuita / cotizar proyecto',
                    'why' => 'Puerta transaccional: guía + formulario WhatsApp sin diluir money pages.',
                    'action' => 'Indexar',
                ],
                [
                    'url' => '/demos-y-precios/',
                    'keyword' => 'demos y precios servicios digitales',
                    'why' => 'Consideración con demos y pisos oficiales; acelera la decisión de compra.',
                    'action' => 'Indexar',
                ],
                [
                    'url' => '/proceso-de-trabajo/',
                    'keyword' => 'proceso de trabajo desarrollo digital',
                    'why' => 'E-E-A-T de método y plazos; reduce fricción antes de cotizar.',
                    'action' => 'Indexar',
                ],
                [
                    'url' => '/proyectos/',
                    'keyword' => 'portafolio / casos de éxito',
                    'why' => 'Prueba social real; refuerza confianza corporativa.',
                    'action' => 'Indexar',
                ],
                [
                    'url' => '/contacto/',
                    'keyword' => 'contacto ConlineWeb',
                    'why' => 'Canal directo de contacto NAP + conversión.',
                    'action' => 'Indexar',
                ],
                [
                    'url' => '/soluciones-corporativas/',
                    'keyword' => 'empresa / nosotros ConlineWeb',
                    'why' => 'Página de empresa (quiénes somos) para autoridad y confianza B2B.',
                    'action' => 'Indexar',
                ],
            ],
        ],
        [
            'id' => 'mexico-hub',
            'title' => 'C · Hub México (cobertura)',
            'role' => 'Directorio nacional — enruta a plaza/servicio',
            'decision' => 'INDEXAR',
            'tone' => 'keep',
            'objective' => 'Descubrimiento geográfico sin canibalizar money pages raíz',
            'items' => [
                [
                    'url' => '/mexico/',
                    'keyword' => 'desarrollo web y software en todo México (hub)',
                    'why' => 'Hub de cobertura: conecta estados, ciudades y servicios.',
                    'action' => 'Indexar',
                ],
                [
                    'url' => '/mexico/estados/',
                    'keyword' => 'desarrollo web por estado México',
                    'why' => 'Índice de 32 estados; facilita crawl y enlazado interno.',
                    'action' => 'Indexar',
                ],
                [
                    'url' => '/mexico/ciudades/',
                    'keyword' => 'desarrollo web ciudades México',
                    'why' => 'Índice de 19 ciudades estratégicas de negocio.',
                    'action' => 'Indexar',
                ],
                [
                    'url' => '/mexico/servicios/',
                    'keyword' => 'servicios digitales México (índice)',
                    'why' => 'Índice de los 5 servicios del hub México.',
                    'action' => 'Indexar',
                ],
            ],
        ],
        [
            'id' => 'mexico-svc-nac',
            'title' => 'D · Servicios México (puente cobertura)',
            'role' => 'Indexables con rol de cobertura — no pelean keyword comercial de la raíz',
            'decision' => 'INDEXAR (cobertura)',
            'tone' => 'watch',
            'objective' => 'Puente a geo: cobertura nacional + elige tu plaza',
            'items' => [
                [
                    'url' => '/mexico/servicios/desarrollo-web/',
                    'keyword' => 'desarrollo web en México (cobertura)',
                    'why' => 'Se queda como puente a ciudades/estados; la venta nacional la gana /paginas-web/.',
                    'action' => 'Indexar · copy de cobertura',
                ],
                [
                    'url' => '/mexico/servicios/ecommerce/',
                    'keyword' => 'e-commerce en México (cobertura)',
                    'why' => 'Puente geo e-commerce; no compite con /tienda-online/.',
                    'action' => 'Indexar · copy de cobertura',
                ],
                [
                    'url' => '/mexico/servicios/software-a-medida/',
                    'keyword' => 'software a medida en México (cobertura)',
                    'why' => 'Puente geo software; no compite con /software-para-empresas/.',
                    'action' => 'Indexar · copy de cobertura',
                ],
                [
                    'url' => '/mexico/servicios/inteligencia-artificial/',
                    'keyword' => 'IA para empresas en México (cobertura)',
                    'why' => 'Puente geo IA; no compite con la landing IA raíz.',
                    'action' => 'Indexar · copy de cobertura',
                ],
                [
                    'url' => '/mexico/servicios/seo/',
                    'keyword' => 'SEO en México (cobertura)',
                    'why' => 'Puente geo SEO; no compite con /seo/.',
                    'action' => 'Indexar · copy de cobertura',
                ],
            ],
        ],
        [
            'id' => 'geo',
            'title' => 'E · Servicio × región (canónicas)',
            'role' => 'Keyword = servicio + plaza · money pages locales',
            'decision' => 'INDEXAR',
            'tone' => 'keep',
            'objective' => 'Rankear búsquedas locales y convertir por plaza',
            'items' => [
                [
                    'url' => '/mexico/estados/{estado}/',
                    'keyword' => 'desarrollo web en {estado}',
                    'why' => 'Hub por estado: ancla local y enlaza a los 5 servicios de esa plaza.',
                    'action' => 'Indexar',
                    'expandable' => true,
                ],
                [
                    'url' => '/mexico/estados/{estado}/servicios/',
                    'keyword' => 'servicios digitales en {estado}',
                    'why' => 'Índice de servicios del estado; mejora crawl y UX local.',
                    'action' => 'Indexar',
                    'expandable' => true,
                ],
                [
                    'url' => '/mexico/estados/{estado}/servicios/{svc}/',
                    'keyword' => '{servicio} en {estado}',
                    'why' => 'Money page local por estado: captura “servicio + estado” y cotiza por WhatsApp.',
                    'action' => 'Indexar',
                    'expandable' => true,
                ],
                [
                    'url' => '/mexico/ciudades/{ciudad}/',
                    'keyword' => 'desarrollo web en {ciudad}',
                    'why' => 'Hub ciudad estratégica: mayor intención comercial local.',
                    'action' => 'Indexar',
                    'expandable' => true,
                ],
                [
                    'url' => '/mexico/ciudades/{ciudad}/servicios/',
                    'keyword' => 'servicios digitales en {ciudad}',
                    'why' => 'Índice de servicios de la ciudad.',
                    'action' => 'Indexar',
                    'expandable' => true,
                ],
                [
                    'url' => '/mexico/ciudades/{ciudad}/servicios/{svc}/',
                    'keyword' => '{servicio} en {ciudad}',
                    'why' => 'Money page local por ciudad: máxima intención + conversión.',
                    'action' => 'Indexar',
                    'expandable' => true,
                ],
            ],
        ],
        [
            'id' => 'blog',
            'title' => 'F · Blog (autoridad temática)',
            'role' => 'Soporte SEO — no sustituye money pages',
            'decision' => 'INDEXAR (sitemap-blog)',
            'tone' => 'blog',
            'objective' => 'Clusters editoriales que enlazan a money pages y cotización',
            'items' => [
                [
                    'url' => '/blog/',
                    'keyword' => 'blog ConlineWeb / guías digitales',
                    'why' => 'Hub editorial; organiza clusters y enlazado interno.',
                    'action' => 'Indexar',
                ],
                [
                    'url' => '/blog/categoria/desarrollo-web/',
                    'keyword' => 'guías desarrollo web',
                    'why' => 'Cluster que refuerza /paginas-web/ con long-tails.',
                    'action' => 'Indexar',
                ],
                [
                    'url' => '/blog/categoria/seo/',
                    'keyword' => 'guías SEO / SEO local',
                    'why' => 'Cluster que refuerza /seo/.',
                    'action' => 'Indexar',
                ],
                [
                    'url' => '/blog/categoria/ecommerce/',
                    'keyword' => 'guías e-commerce / tienda online',
                    'why' => 'Cluster que refuerza /tienda-online/.',
                    'action' => 'Indexar',
                ],
                [
                    'url' => '/blog/categoria/software/',
                    'keyword' => 'guías software empresarial',
                    'why' => 'Cluster que refuerza /software-para-empresas/.',
                    'action' => 'Indexar',
                ],
                [
                    'url' => '/blog/categoria/inteligencia-artificial/',
                    'keyword' => 'guías IA para empresas',
                    'why' => 'Cluster que refuerza la landing de IA.',
                    'action' => 'Indexar',
                ],
                [
                    'url' => '/blog/categoria/marketing-digital/',
                    'keyword' => 'marketing digital empresas',
                    'why' => 'Cluster transversal de captación; enlaza a servicios y cotización.',
                    'action' => 'Indexar',
                ],
                [
                    'url' => '/blog/articulo/{slug}/',
                    'keyword' => 'long-tail por artículo',
                    'why' => 'Artículos indexables que aportan long-tail y deben enlazar a money page + WhatsApp.',
                    'action' => 'Indexar (selectivo)',
                    'expandable' => true,
                ],
            ],
        ],
        [
            'id' => 'support-kw',
            'title' => 'G · Soporte indexado (keyword propia)',
            'role' => 'Indexadas con keyword distinta a las money pages; enlazan a la URL ganadora',
            'decision' => 'INDEXAR · no money page',
            'tone' => 'keep',
            'objective' => 'Cubrir intención adyacente (agencia / método / UI-UX) sin canibalizar las money pages',
            'items' => [
                [
                    'url' => '/agencia-de-desarrollo-web/',
                    'keyword' => 'agencia de desarrollo web México',
                    'why' => 'Misma plantilla/diseño que Nosotros (/soluciones-corporativas/). Keyword de agencia/partner; ficha empresa en Nosotros; money pages aparte.',
                    'action' => 'Indexar · keyword propia · diseño = Nosotros · CTA → WhatsApp + Nosotros',
                ],
                [
                    'url' => '/desarrollo-de-software/',
                    'keyword' => 'desarrollo de software a medida (método)',
                    'why' => 'Explica el cómo (sprints, pruebas, entrega). El qué vive en /software-para-empresas/.',
                    'action' => 'Indexar · keyword propia · CTA → /software-para-empresas/',
                ],
                [
                    'url' => '/diseno-de-paginas-web/',
                    'keyword' => 'diseño UI/UX web corporativo',
                    'why' => 'Diseño/rediseño e interfaz — no paquetes de sitio completo (eso es /paginas-web/).',
                    'action' => 'Indexar · keyword propia · CTA → /paginas-web/',
                ],
            ],
        ],
        [
            'id' => 'weaken',
            'title' => 'H · Páginas que debilitan (hoy indexadas)',
            'role' => 'Están en sitemap pero canibalizan keyword o envían señal “barato” / genérico',
            'decision' => 'REVISAR · no money page',
            'tone' => 'weaken',
            'objective' => 'Identificar lo indexado que debilita el objetivo para consolidar, noindex o 301',
            'items' => [
                [
                    'url' => '/tu-web-gratis/',
                    'keyword' => 'web gratis (legacy)',
                    'why' => 'Señal “barato” — ya no debe indexarse. 301 permanente a asesoría.',
                    'action' => '301 aplicado → /asesoria-web-gratuita/ · fuera sitemap',
                    'legacy_redirect' => true,
                ],
                [
                    'url' => '/indice/',
                    'keyword' => 'índice del sitio',
                    'why' => 'Utilidad de descubrimiento; no aporta captación B2B. Sigue indexable por enlaces.',
                    'action' => 'Fuera de sitemap.xml · sin noindex · decisión aplicada',
                ],
                [
                    'url' => '/documentacion/',
                    'keyword' => 'documentación',
                    'why' => 'Soporte técnico; no money page. Hub fuera del sitemap comercial; artículos siguen en sitemap-docs.xml.',
                    'action' => 'Fuera de sitemap.xml · indexable · cluster en sitemap-docs.xml',
                ],
            ],
        ],
    ];
}

/**
 * @return array{
 *   objective:string,
 *   generated_at:string,
 *   sitemap_main_count:int,
 *   sitemap_blog_count:int,
 *   sections:list<array>
 * }
 */
function cw_seo_sitemap_directory_build(): array
{
    $main = cw_seo_sitemap_paths('sitemap.xml');
    $blog = cw_seo_sitemap_paths('sitemap-blog.xml');
    $sections = [];

    foreach (cw_seo_sitemap_directory_catalog() as $sec) {
        $items = [];
        $in = 0;
        $missing = 0;
        foreach ($sec['items'] as $item) {
            $paths = ($sec['id'] === 'blog') ? $blog : $main;
            $url = (string) $item['url'];
            $hit = cw_seo_dir_sitemap_hit($url, $paths);
            if ($sec['tone'] === 'weaken') {
                if (!empty($item['legacy_redirect'])) {
                    $status = $hit['in_sitemap']
                        ? 'AÚN en sitemap · quitar'
                        : '301 · fuera sitemap';
                } else {
                    $status = $hit['in_sitemap']
                        ? 'EN sitemap · debilita'
                        : 'fuera sitemap';
                }
            } else {
                $status = $hit['in_sitemap']
                    ? ($hit['count'] > 1 ? 'EN sitemap · ' . $hit['count'] . ' URLs' : 'EN sitemap')
                    : 'FALTA en sitemap';
            }
            if ($hit['in_sitemap']) {
                $in++;
            } else {
                $missing++;
            }

            $children = [];
            if (!empty($item['expandable']) || str_contains($url, '{')) {
                $children = cw_seo_dir_expand_children($url, $paths, (string) $item['keyword']);
            }

            $items[] = array_merge($item, [
                'in_sitemap' => $hit['in_sitemap'],
                'match_count' => $hit['count'],
                'status_label' => $status,
                'why' => (string) ($item['why'] ?? $item['note'] ?? ''),
                'children' => $children,
                'children_in' => count(array_filter($children, static fn($c) => !empty($c['in_sitemap']))),
            ]);
        }
        $sections[] = array_merge($sec, [
            'items' => $items,
            'stats' => ['in' => $in, 'missing' => $missing, 'total' => count($items)],
        ]);
    }

    return [
        'objective' => 'Captar empresas en México (web, e-commerce, software, IA, SEO) → confianza corporativa → cotización/asesoría WhatsApp. No vender “barato” ni contenido genérico.',
        'generated_at' => date('Y-m-d H:i:s'),
        'sitemap_main_count' => count($main),
        'sitemap_blog_count' => count($blog),
        'sections' => $sections,
    ];
}
