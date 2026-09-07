<?php
/**
 * Sync del directorio público /indice/ + sitemaps + llms tras publicar contenido IA.
 */
require_once __DIR__ . '/cw_seo_mexico_autofix.php';
require_once __DIR__ . '/cw_seo_mexico_ai.php';

function cw_seo_mexico_directory_site_placement_file(): ?string
{
    $root = cw_seo_mexico_autofix_site_root();
    if ($root === null) {
        return null;
    }
    return $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'content-placement.php';
}

/**
 * @return array<string,mixed>
 */
function cw_seo_mexico_directory_placement_pack(): array
{
    $file = cw_seo_mexico_directory_site_placement_file();
    if ($file && is_readable($file)) {
        require_once $file;
        if (function_exists('cw_content_placement_rules')) {
            return cw_content_placement_rules();
        }
    }
    return [
        'directory_url' => 'https://conlineweb.com/indice/',
        'note' => 'Archivo content-placement.php no legible',
        'rules' => [],
    ];
}

/**
 * Texto corto para system prompts de la IA.
 */
function cw_seo_mexico_directory_ai_context(): string
{
    $pack = cw_seo_mexico_directory_placement_pack();
    $lines = [
        'Directorio público del sitio: ' . ($pack['directory_url'] ?? 'https://conlineweb.com/indice/'),
        'Al generar contenido DEBES indicar kind de colocación y respetar la ruta canónica.',
        'Reglas:',
    ];
    foreach (($pack['rules'] ?? []) as $r) {
        $lines[] = '- ' . ($r['kind'] ?? '') . ': ' . ($r['when'] ?? '')
            . ' → ' . ($r['url_pattern'] ?? '')
            . ' (sección índice: ' . ($r['indice_section'] ?? '') . ')';
    }
    $lines[] = 'Categorías blog permitidas: ' . implode(', ', array_keys(cw_seo_mexico_ai_blog_categories()));
    $lines[] = 'Tras publicar, el sistema actualiza /indice/, sitemap.xml, sitemap-blog.xml, sitemap-index.xml, llms.txt y llms.json.';
    return implode("\n", $lines);
}

/**
 * Ejecuta un script PHP del sitio en proceso aislado.
 *
 * @return array{ok:bool,output?:string,error?:string,code?:int}
 */
function cw_seo_mexico_directory_run_site_script(string $relativeScript): array
{
    $root = cw_seo_mexico_autofix_site_root();
    if ($root === null) {
        return ['ok' => false, 'error' => 'Sitio no encontrado'];
    }
    $script = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativeScript);
    if (!is_readable($script)) {
        return ['ok' => false, 'error' => $relativeScript . ' ausente'];
    }

    $php = defined('PHP_BINARY') && PHP_BINARY !== '' ? PHP_BINARY : 'php';
    $candidates = array_unique(array_filter([
        $php,
        'c:\\xampp\\php\\php.exe',
        '/usr/local/bin/php',
        '/usr/bin/php',
        'php',
    ]));
    foreach ($candidates as $bin) {
        $cmd = escapeshellarg($bin) . ' ' . escapeshellarg($script) . ' 2>&1';
        $out = [];
        $code = 1;
        @exec($cmd, $out, $code);
        $text = implode("\n", $out);
        if ($code === 0 || str_contains($text, 'Wrote') || str_contains($text, 'Añadidas') || str_contains($text, 'Eliminadas')) {
            return ['ok' => true, 'output' => $text, 'code' => $code];
        }
        if (stripos($text, 'not recognized') !== false || stripos($text, 'No such file') !== false) {
            continue;
        }
        // Otro error real del script
        if ($text !== '') {
            return ['ok' => false, 'error' => $text, 'code' => $code];
        }
    }

    // Fallback: require en proceso actual
    try {
        ob_start();
        require $script;
        $buf = (string) ob_get_clean();
        return ['ok' => true, 'output' => $buf, 'code' => 0];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Regenera sitemap-blog.xml en el sitio local.
 *
 * @return array{ok:bool,urls?:int,error?:string,path?:string}
 */
function cw_seo_mexico_directory_regen_blog_sitemap(): array
{
    $run = cw_seo_mexico_directory_run_site_script('includes/blog/generate-sitemap-blog.php');
    if (empty($run['ok'])) {
        return ['ok' => false, 'error' => (string) ($run['error'] ?? 'sitemap-blog falló')];
    }
    $text = (string) ($run['output'] ?? '');
    $urls = 0;
    if (preg_match('/Wrote\s+(\d+)/', $text, $m)) {
        $urls = (int) $m[1];
    }
    return [
        'ok' => true,
        'urls' => $urls,
        'path' => 'sitemap-blog.xml',
        'output' => $text,
    ];
}

/**
 * Regenera / sincroniza sitemap.xml (México canónico).
 *
 * @return array{ok:bool,error?:string,path?:string,output?:string}
 */
function cw_seo_mexico_directory_regen_mexico_sitemap(): array
{
    $run = cw_seo_mexico_directory_run_site_script('includes/mexico/sync-sitemap-mexico.php');
    if (empty($run['ok'])) {
        return ['ok' => false, 'error' => (string) ($run['error'] ?? 'sitemap México falló')];
    }
    return [
        'ok' => true,
        'path' => 'sitemap.xml',
        'output' => (string) ($run['output'] ?? ''),
    ];
}

/**
 * Actualiza lastmod de sitemap-index.xml.
 *
 * @return array{ok:bool,error?:string,path?:string}
 */
function cw_seo_mexico_directory_regen_sitemap_index(): array
{
    $root = cw_seo_mexico_autofix_site_root();
    if ($root === null) {
        return ['ok' => false, 'error' => 'Sitio no encontrado'];
    }
    $today = date('Y-m-d');
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
        . '  <sitemap>' . "\n"
        . '    <loc>https://conlineweb.com/sitemap.xml</loc>' . "\n"
        . '    <lastmod>' . $today . '</lastmod>' . "\n"
        . '  </sitemap>' . "\n"
        . '  <sitemap>' . "\n"
        . '    <loc>https://conlineweb.com/sitemap-blog.xml</loc>' . "\n"
        . '    <lastmod>' . $today . '</lastmod>' . "\n"
        . '  </sitemap>' . "\n"
        . '</sitemapindex>' . "\n";

    $write = cw_seo_mexico_autofix_write('sitemap-index.xml', $xml, 'sitemap-index lastmod');
    if (empty($write['ok'])) {
        // fallback directo si whitelist/write falla
        $path = $root . DIRECTORY_SEPARATOR . 'sitemap-index.xml';
        if (@file_put_contents($path, $xml) === false) {
            return ['ok' => false, 'error' => (string) ($write['error'] ?? 'No se pudo escribir sitemap-index.xml')];
        }
    }
    return ['ok' => true, 'path' => 'sitemap-index.xml'];
}

/**
 * Regenera llms.txt + llms.json.
 *
 * @return array{ok:bool,pages?:int,error?:string}
 */
function cw_seo_mexico_directory_regen_llms(): array
{
    $root = cw_seo_mexico_autofix_site_root();
    if ($root === null) {
        return ['ok' => false, 'error' => 'Sitio no encontrado'];
    }

    $run = cw_seo_mexico_directory_run_site_script('includes/generate-llms.php');
    if (!empty($run['ok'])) {
        $text = (string) ($run['output'] ?? '');
        $pages = 0;
        if (preg_match('/Wrote\s+(\d+)/', $text, $m)) {
            $pages = (int) $m[1];
        }
        return [
            'ok' => true,
            'pages' => $pages,
            'txt' => 'llms.txt',
            'json' => 'llms.json',
            'output' => $text,
        ];
    }

    // Fallback: cargar funciones y escribir
    $script = $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'generate-llms.php';
    if (!is_readable($script)) {
        return ['ok' => false, 'error' => 'generate-llms.php ausente'];
    }
    require_once $script;
    if (!function_exists('cw_llms_write')) {
        return ['ok' => false, 'error' => 'cw_llms_write no disponible'];
    }
    $result = cw_llms_write($root);
    if (empty($result['ok'])) {
        return ['ok' => false, 'error' => (string) ($result['error'] ?? 'llms falló')];
    }
    return $result;
}

/**
 * Sync completo de discovery: sitemaps + LLMs (+ opcional índice extra ya manejado aparte).
 *
 * @return array{ok:bool,blog?:array,mexico?:array,index?:array,llms?:array,message:string,files:list<string>}
 */
function cw_seo_mexico_directory_sync_discovery(bool $includeMexico = true): array
{
    $files = [];
    $blog = cw_seo_mexico_directory_regen_blog_sitemap();
    if (!empty($blog['ok'])) {
        $files[] = 'sitemap-blog.xml';
    }

    $mexico = ['ok' => true, 'skipped' => !$includeMexico];
    if ($includeMexico) {
        $mexico = cw_seo_mexico_directory_regen_mexico_sitemap();
        if (!empty($mexico['ok'])) {
            $files[] = 'sitemap.xml';
        }
    }

    $index = cw_seo_mexico_directory_regen_sitemap_index();
    if (!empty($index['ok'])) {
        $files[] = 'sitemap-index.xml';
    }

    $llms = cw_seo_mexico_directory_regen_llms();
    if (!empty($llms['ok'])) {
        $files[] = 'llms.txt';
        $files[] = 'llms.json';
    }

    $ok = !empty($blog['ok']) && !empty($index['ok']) && !empty($llms['ok'])
        && ($includeMexico ? !empty($mexico['ok']) : true);

    $parts = [];
    if (!empty($blog['ok'])) {
        $parts[] = 'sitemap-blog' . (!empty($blog['urls']) ? ' (' . (int) $blog['urls'] . ')' : '');
    }
    if ($includeMexico && !empty($mexico['ok'])) {
        $parts[] = 'sitemap México';
    }
    if (!empty($index['ok'])) {
        $parts[] = 'sitemap-index';
    }
    if (!empty($llms['ok'])) {
        $parts[] = 'llms' . (!empty($llms['pages']) ? ' (' . (int) $llms['pages'] . ' URLs)' : '');
    }

    return [
        'ok' => $ok,
        'blog' => $blog,
        'mexico' => $mexico,
        'index' => $index,
        'llms' => $llms,
        'files' => $files,
        'message' => $ok
            ? ('Discovery actualizado: ' . implode(', ', $parts))
            : ('Sync parcial/fallido: blog=' . (!empty($blog['ok']) ? 'ok' : 'fail')
                . ' mexico=' . (!empty($mexico['ok']) ? 'ok' : 'fail')
                . ' index=' . (!empty($index['ok']) ? 'ok' : 'fail')
                . ' llms=' . (!empty($llms['ok']) ? 'ok' : 'fail')),
    ];
}

/**
 * Upsert entrada en content-directory-extra.php para /indice/.
 *
 * @param array{label:string,path:string,section:string,search?:string,kind?:string} $item
 * @return array{ok:bool,applied?:bool,error?:string}
 */
function cw_seo_mexico_directory_upsert_extra(array $item): array
{
    $label = trim((string) ($item['label'] ?? ''));
    $path = trim((string) ($item['path'] ?? ''));
    $section = trim((string) ($item['section'] ?? 'dir-ia-extra'));
    if ($label === '' || $path === '') {
        return ['ok' => false, 'error' => 'label/path requeridos'];
    }
    $path = ltrim(str_replace('\\', '/', $path), '/');
    $entry = [
        'label' => $label,
        'path' => $path,
        'section' => $section,
        'search' => (string) ($item['search'] ?? strtolower($label . ' ' . $path)),
        'kind' => (string) ($item['kind'] ?? ''),
        'updated_at' => date('c'),
    ];

    $rel = 'includes/content-directory-extra.php';
    $root = cw_seo_mexico_autofix_site_root();
    if ($root === null) {
        return ['ok' => false, 'error' => 'Sitio no encontrado'];
    }
    $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $list = [];
    if (is_file($abs)) {
        $loaded = include $abs;
        if (is_array($loaded)) {
            $list = $loaded;
        }
    }
    $byPath = [];
    foreach ($list as $row) {
        if (!empty($row['path'])) {
            $byPath[ltrim((string) $row['path'], '/')] = $row;
        }
    }
    $byPath[$path] = $entry;
    $export = var_export(array_values($byPath), true);
    $php = "<?php\n/** URLs extra para /indice/ — sync automático IA/admin */\nreturn " . $export . ";\n";
    return cw_seo_mexico_autofix_write($rel, $php, 'directory extra upsert');
}

/**
 * Sync completo tras publicar: extra en índice + sitemaps + LLMs.
 *
 * @return array{ok:bool,indice_url:string,sitemap?:array,discovery?:array,extra?:array,placement?:array,message:string}
 */
function cw_seo_mexico_directory_sync_after_publish(
    string $kind,
    array $meta,
    int $userId = 0,
    ?mysqli $conn = null
): array {
    $placementFile = cw_seo_mexico_directory_site_placement_file();
    if ($placementFile && is_readable($placementFile)) {
        require_once $placementFile;
    }

    $mapKind = $kind === 'hub_text' ? 'hub_city' : $kind;
    $resolved = function_exists('cw_content_placement_resolve')
        ? cw_content_placement_resolve($mapKind, $meta)
        : ['ok' => false];

    $extraResult = ['ok' => true, 'applied' => false];
    if (in_array($kind, ['blog_post', 'blog_improve', 'policy', 'landing_service', 'hub_text'], true)) {
        $urlPath = '';
        if (!empty($resolved['url'])) {
            $urlPath = (string) (parse_url((string) $resolved['url'], PHP_URL_PATH) ?: '');
            $urlPath = ltrim($urlPath, '/');
        }
        if ($urlPath === '' && !empty($meta['slug'])) {
            if ($kind === 'blog_post' || $kind === 'blog_improve') {
                $urlPath = 'blog/articulo/' . $meta['slug'] . '/';
            } elseif ($kind === 'hub_text') {
                $urlPath = 'mexico/ciudades/' . $meta['slug'] . '/';
            } else {
                $urlPath = (string) $meta['slug'] . '/';
            }
        }
        if ($urlPath !== '') {
            $extraResult = cw_seo_mexico_directory_upsert_extra([
                'label' => (string) ($meta['title'] ?? $meta['slug'] ?? 'Contenido IA'),
                'path' => $urlPath,
                'section' => (string) ($resolved['indice_section'] ?? 'dir-ia-extra'),
                'search' => strtolower((string) ($meta['title'] ?? '') . ' ' . (string) ($meta['category'] ?? '') . ' ' . $urlPath),
                'kind' => $kind,
            ]);
            if ($conn instanceof mysqli && !empty($extraResult['ok']) && !empty($extraResult['applied'])) {
                cw_seo_mexico_autofix_log(
                    $conn,
                    'directory_extra',
                    'includes/content-directory-extra.php',
                    true,
                    $urlPath,
                    null,
                    $userId,
                    null
                );
            }
        }
    }

    // Siempre regenerar discovery: sitemaps + LLMs (México también en hubs)
    $includeMexico = in_array($kind, ['hub_text', 'hub_city', 'landing_service', 'service_geo'], true)
        || !in_array($kind, ['blog_post', 'blog_improve'], true);
    // Para blogs también conviene refrescar México de vez en cuando; coste bajo → siempre true
    $discovery = cw_seo_mexico_directory_sync_discovery(true);

    return [
        'ok' => !empty($discovery['ok']),
        'indice_url' => 'https://conlineweb.com/indice/',
        'placement' => $resolved,
        'extra' => $extraResult,
        'sitemap' => $discovery['blog'] ?? [],
        'discovery' => $discovery,
        'message' => 'Directorio /indice/ + sitemaps + LLMs sincronizados'
            . (!empty($discovery['files']) ? ' · ' . implode(', ', $discovery['files']) : ''),
    ];
}
