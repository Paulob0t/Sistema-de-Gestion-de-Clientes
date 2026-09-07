<?php
/**
 * Músculo del sitio — propuestas de mantenimiento (copy / sección / icono)
 * con aprobación humana. Solo rutas whitelist.
 */
declare(strict_types=1);

require_once __DIR__ . '/cw_seo_mexico_ai.php';
require_once __DIR__ . '/cw_site_ai_brain.php';

/**
 * Rutas relativas permitidas para parches de mantenimiento.
 *
 * @return list<string>
 */
function cw_site_ai_maintain_allowed_prefixes(): array
{
    return [
        'includes/layout-nav.php',
        'includes/footer.php',
        'includes/menu.php',
        'includes/cw-nap.php',
        'includes/cw-brand.php',
        'includes/cw-design-system.php',
        'includes/cw-seo-meta.php',
        'includes/cw-faq-section.php',
        'includes/email_templates.php',
        'includes/head-common.php',
        'includes/head-embed.php',
        'includes/content-placement.php',
        'includes/content-directory-extra.php',
        'includes/generate-llms.php',
        'includes/home-',
        'includes/hub-',
        'includes/mexico/',
        'includes/blog/',
        'includes/landing-sections/',
        'includes/legal/',
        'includes/leon/',
        'index.php',
        'contacto/',
        'seo/',
        'seo.php',
        'paginas-web/',
        'paginas-web.php',
        'tienda-online/',
        'tienda-online.php',
        'desarrollo-de-software/',
        'desarrollo-de-software.php',
        'diseno-de-paginas-web/',
        'diseno-de-paginas-web.php',
        'soluciones-inteligencia-artificial/',
        'soluciones-inteligencia-artificial.php',
        'software-para-empresas/',
        'software-para-empresas.php',
        'software-para-empresas-leon.php',
        'agencia-de-desarrollo-web/',
        'agencia-de-desarrollo-web.php',
        'agencia-de-desarrollo-web-mx.php',
        'soluciones-corporativas/',
        'soluciones-corporativas.php',
        'tu-web-gratis/',
        'tu-web-gratis.php',
        'mexico/',
        'blog/',
        'assets/css/',
        'llms.txt',
        'llms.json',
    ];
}

/**
 * Normaliza carpetas fantasma → archivo real del sitio (ej. desarrollo-de-software/ → .php).
 */
function cw_site_ai_maintain_resolve_path(string $relPath): ?string
{
    $rel = str_replace('\\', '/', trim($relPath));
    $rel = ltrim($rel, '/');
    if ($rel === '' || str_contains($rel, '..')) {
        return null;
    }
    $root = function_exists('cw_seo_mexico_autofix_site_root') ? cw_seo_mexico_autofix_site_root() : null;
    if ($root === null) {
        return null;
    }

    $candidates = [];
    if (preg_match('/\.(php|css|js|txt|json)$/i', $rel)) {
        $candidates[] = $rel;
    } else {
        $base = rtrim($rel, '/');
        $candidates[] = $base . '.php';
        $candidates[] = $base . '/index.php';
        $candidates[] = $base;
    }

    foreach ($candidates as $cand) {
        if (!cw_site_ai_maintain_path_allowed($cand)) {
            continue;
        }
        $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $cand);
        if (is_file($abs) && is_readable($abs)) {
            return $cand;
        }
    }
    return null;
}

function cw_site_ai_maintain_path_allowed(string $relPath): bool
{
    $rel = str_replace('\\', '/', ltrim($relPath, '/'));
    if ($rel === '' || str_contains($rel, '..')) {
        return false;
    }
    // Bloqueos duros (credenciales / auth / pagos / infra)
    $deny = [
        'conn.php', 'conn2.php', '.env', 'secrets', 'password', 'credential',
        'cw-credentials', 'cw-db.php', 'cw-session', 'cw-security',
        'vendor/', 'cgi-bin/', 'tcpdf/', 'stripe', 'pago.php', 'pago_stripe',
        'auth', 'uploads/', 'config/', 'admin/',
    ];
    foreach ($deny as $d) {
        if (stripos($rel, $d) !== false) {
            return false;
        }
    }
    foreach (cw_site_ai_maintain_allowed_prefixes() as $p) {
        $base = rtrim($p, '/');
        if ($rel === $base || $rel === $base . '.php' || str_starts_with($rel, $p) || str_starts_with($rel, $base . '/')) {
            return true;
        }
    }
    return false;
}

/**
 * Propone un parche de mantenimiento (search/replace) con IA.
 *
 * @return array{ok:bool,proposal_id?:int,after?:array,error?:string}
 */
function cw_site_ai_maintain_propose_patch(
    mysqli $conn,
    string $instruction,
    string $relPath = '',
    int $userId = 0
): array {
    $instruction = trim($instruction);
    if (mb_strlen($instruction) < 12) {
        return ['ok' => false, 'error' => 'Indica con más detalle el cambio (mín. 12 caracteres)'];
    }
    if (!cw_seo_mexico_ai_available()) {
        return ['ok' => false, 'error' => 'IA no disponible'];
    }

    $root = cw_seo_mexico_autofix_site_root();
    if ($root === null) {
        return ['ok' => false, 'error' => 'Sitio no encontrado'];
    }

    $relPath = str_replace('\\', '/', ltrim(trim($relPath), '/'));
    $filePreview = '';
    if ($relPath !== '' && cw_site_ai_maintain_path_allowed($relPath)) {
        $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
        if (is_readable($abs)) {
            $raw = (string) file_get_contents($abs);
            $filePreview = mb_substr($raw, 0, 12000);
        }
    }

    $brain = cw_site_ai_brain_context($conn, 18);
    $allowed = implode(', ', cw_site_ai_maintain_allowed_prefixes());

    require_once __DIR__ . '/cw_seo_mexico_ai_constitution.php';
    $system = 'Eres el músculo de mantenimiento del sitio público ConlineWeb (conlineweb.com) '
        . 'y parte del comité senior (diseño + PM + programador) / MASTER SEO-GEO.\n'
        . cw_seo_mexico_ai_master_brief() . "\n"
        . mb_substr(cw_seo_mexico_ai_domain_mastery_brief(), 0, 900) . "\n"
        . mb_substr(cw_seo_mexico_ai_committee_brief(), 0, 900) . "\n"
        . mb_substr(cw_seo_mexico_ai_seo_geo_playbook(), 0, 1600) . "\n"
        . "{$brain}\n"
        . "REGLA SEO/GEO: cada parche de copy/sección debe mejorar intención, claridad local o internos; "
        . "rationale debe nombrar keyword/intención o GEO.\n"
        . "REGLA DE DISEÑO (prioridad alta):\n"
        . "- Mantén y estandariza el design system ACTUAL (dark cyber/tech de ConlineWeb).\n"
        . "- Usa variables CSS existentes de assets/css/base.css (y cw-hub.css / footer.css).\n"
        . "- No inventes otro look (tema claro genérico, tipografías nuevas, paletas ajenas).\n"
        . "- Si actualizas UI: evoluciona dentro del estilo vigente; unifica inconsistencias al estándar.\n"
        . "- Plantillas de correo (email_templates.php): SOLO diseño visual; respeta marca de correo.\n"
        . "- Cambios visuales mínimos, coherentes y reversibles.\n"
        . 'Responde SOLO JSON con keys: path, search, replace, change_type, summary, rationale. '
        . 'change_type: copy|icon|section|style|email_design. '
        . 'path debe estar bajo: ' . $allowed . '. '
        . 'search debe ser un fragmento EXACTO existente (si hay preview). '
        . 'replace es el nuevo fragmento. Cambios mínimos, seguros, SEO/UX y fieles al design system. '
        . 'No toques credenciales, pagos ni JS de tracking. Sin emojis.';

    $user = "Instrucción de mantenimiento:\n{$instruction}\n";
    if ($relPath !== '') {
        $user .= "Archivo sugerido: {$relPath}\n";
    }
    if ($filePreview !== '') {
        $user .= "Preview actual del archivo (recortado):\n```\n{$filePreview}\n```\n";
    } else {
        $user .= "No hay preview; elige el path más seguro de la whitelist y un search corto verificable.\n";
    }

    $chat = cw_seo_mexico_ai_chat([
        ['role' => 'system', 'content' => $system],
        ['role' => 'user', 'content' => $user],
    ], 0.35);
    if (empty($chat['ok'])) {
        return ['ok' => false, 'error' => (string) ($chat['error'] ?? 'IA falló')];
    }
    $after = json_decode((string) ($chat['content'] ?? ''), true);
    if (!is_array($after) || empty($after['path']) || empty($after['search']) || !isset($after['replace'])) {
        return ['ok' => false, 'error' => 'JSON de mantenimiento incompleto', 'raw' => $chat['content'] ?? ''];
    }

    $path = str_replace('\\', '/', ltrim((string) $after['path'], '/'));
    if (!cw_site_ai_maintain_path_allowed($path)) {
        return ['ok' => false, 'error' => 'Ruta fuera de whitelist: ' . $path];
    }
    $search = (string) $after['search'];
    $replace = (string) $after['replace'];
    if ($search === '' || $search === $replace) {
        return ['ok' => false, 'error' => 'search/replace inválidos'];
    }

    $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    $beforeContent = is_readable($abs) ? (string) file_get_contents($abs) : '';
    if ($beforeContent !== '' && !str_contains($beforeContent, $search)) {
        return ['ok' => false, 'error' => 'El texto a reemplazar no existe en el archivo actual'];
    }

    $afterNorm = [
        'path' => $path,
        'search' => $search,
        'replace' => $replace,
        'change_type' => preg_replace('/[^a-z_]/', '', strtolower((string) ($after['change_type'] ?? 'copy'))) ?: 'copy',
        'summary' => trim((string) ($after['summary'] ?? $instruction)),
        'rationale' => trim((string) ($after['rationale'] ?? '')),
        'mode' => 'site_patch',
    ];
    if (mb_strlen($afterNorm['rationale']) < 20) {
        $afterNorm['rationale'] = 'Parche de mantenimiento en «' . $path . '»: '
            . mb_substr($afterNorm['summary'] !== '' ? $afterNorm['summary'] : $instruction, 0, 220);
    }
    $before = [
        'path' => $path,
        'snippet' => $search,
        'instruction' => $instruction,
    ];
    $urlMeta = cw_seo_mexico_ai_resolve_public_url('site_patch', $path, '', $afterNorm);
    $pageUrl = (string) ($urlMeta['url'] ?? ('https://conlineweb.com/' . $path));
    $afterNorm['url'] = $pageUrl;
    $afterNorm['url_label'] = (string) ($urlMeta['url_label'] ?? 'URL de página afectada');
    $afterNorm['url_kind'] = (string) ($urlMeta['url_kind'] ?? 'page');

    $saved = cw_seo_mexico_ai_save_proposal(
        $conn,
        'site_patch',
        'Mantenimiento · ' . mb_substr($afterNorm['summary'], 0, 80),
        $path,
        $pageUrl,
        'Mantenimiento sitio: ' . mb_substr($instruction, 0, 160),
        $before,
        $afterNorm,
        $userId
    );
    if (empty($saved['ok'])) {
        return $saved;
    }
    return [
        'ok' => true,
        'proposal_id' => (int) $saved['id'],
        'after' => $afterNorm,
        'content_type' => 'fix',
        'message' => 'Propuesta de mantenimiento lista. Revísala en Monitor antes de implementar.',
    ];
}

/**
 * Aplica propuesta site_patch.
 *
 * @param array<string,mixed> $after
 * @return array{ok:bool,applied:bool,path?:string,backup?:string,error?:string}
 */
function cw_site_ai_maintain_apply_patch(array $after, string $reason = ''): array
{
    $pathRaw = str_replace('\\', '/', ltrim((string) ($after['path'] ?? ''), '/'));
    $search = (string) ($after['search'] ?? '');
    $replace = (string) ($after['replace'] ?? '');
    if ($pathRaw === '' || $search === '') {
        return ['ok' => false, 'applied' => false, 'error' => 'Parche incompleto'];
    }
    $path = cw_site_ai_maintain_resolve_path($pathRaw) ?? $pathRaw;
    if (!cw_site_ai_maintain_path_allowed($path)) {
        return ['ok' => false, 'applied' => false, 'error' => 'Ruta no permitida: ' . $pathRaw];
    }
    // Usar autofix_replace si la ruta también está en whitelist autofix; si no, write manual con backup
    if (function_exists('cw_seo_mexico_autofix_is_allowed') && cw_seo_mexico_autofix_is_allowed($path)) {
        return cw_seo_mexico_autofix_replace($path, $search, $replace, $reason !== '' ? $reason : 'site_patch');
    }

    $root = cw_seo_mexico_autofix_site_root();
    if ($root === null) {
        return ['ok' => false, 'applied' => false, 'error' => 'Sitio no encontrado'];
    }
    $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    if (!is_file($abs)) {
        $hint = '';
        if (!str_contains($pathRaw, '.php') && is_file($root . DIRECTORY_SEPARATOR . rtrim($pathRaw, '/') . '.php')) {
            $hint = ' ¿Quisiste decir ' . rtrim($pathRaw, '/') . '.php?';
        }
        return ['ok' => false, 'applied' => false, 'error' => 'Archivo no existe: ' . $pathRaw . $hint];
    }
    $src = (string) file_get_contents($abs);
    if (!str_contains($src, $search)) {
        return ['ok' => false, 'applied' => false, 'error' => 'Fragmento search no encontrado al aplicar'];
    }
    $next = str_replace($search, $replace, $src);
    // Backup local
    $bakDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'autofix_backups';
    if (!is_dir($bakDir)) {
        @mkdir($bakDir, 0755, true);
    }
    $bak = $bakDir . DIRECTORY_SEPARATOR . date('Ymd_His') . '__' . preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $path) . '.bak';
    @copy($abs, $bak);
    if (@file_put_contents($abs, $next) === false) {
        return ['ok' => false, 'applied' => false, 'error' => 'No se pudo escribir', 'backup' => $bak];
    }
    if (preg_match('/\.php$/i', $abs)) {
        $lint = cw_seo_mexico_autofix_php_lint($abs);
        if (empty($lint['ok'])) {
            @copy($bak, $abs);
            return ['ok' => false, 'applied' => false, 'error' => 'php -l falló; rollback', 'backup' => $bak];
        }
    }
    return ['ok' => true, 'applied' => true, 'path' => $path, 'backup' => $bak];
}
