<?php
/**
 * AutoFix seguro mismo servidor (adm + conlineweb.com en cPanel).
 * Flujo: validar whitelist → backup → escribir → php -l → rollback si falla.
 */
require_once __DIR__ . '/cw_seo_mexico_checklist.php';

/**
 * @return list<string> prefijos relativos permitidos dentro de conlineweb.com
 */
function cw_seo_mexico_autofix_whitelist(): array
{
    return [
        'mexico/ciudades/',
        'mexico/estados/',
        'includes/mexico/',
        'includes/blog/content/',
        'includes/blog/posts/manifest.ai.php',
        'includes/content-directory-extra.php',
        'includes/generate-llms.php',
        'includes/layout-nav.php',
        'includes/footer.php',
        'includes/cw-nap.php',
        'sitemap-blog.xml',
        'sitemap.xml',
        'sitemap-index.xml',
        'llms.txt',
        'llms.json',
        'blog/articulo/',
        'index.php',
        'contacto/',
        'seo/',
        'paginas-web/',
        'tienda-online/',
        'desarrollo-de-software/',
        'diseno-de-paginas-web/',
        'soluciones-inteligencia-artificial/',
        'software-para-empresas/',
        'agencia-de-desarrollo-web/',
        'soluciones-corporativas/',
        'tu-web-gratis/',
        'assets/css/',
    ];
}

function cw_seo_mexico_autofix_site_root(): ?string
{
    $root = cw_seo_mexico_site_root_path();
    if ($root !== null) {
        return $root;
    }
    $candidates = [
        dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'conlineweb.com',
        dirname(__DIR__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'conlineweb.com',
    ];
    foreach ($candidates as $path) {
        $real = realpath($path);
        if ($real !== false && is_dir($real) && is_dir($real . DIRECTORY_SEPARATOR . 'includes')) {
            return $real;
        }
    }
    return null;
}

function cw_seo_mexico_autofix_backup_dir(): string
{
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'autofix_backups';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

/**
 * Convierte ruta absoluta a relativa del sitio; null si fuera de root.
 */
function cw_seo_mexico_autofix_rel_path(string $absPath): ?string
{
    $root = cw_seo_mexico_autofix_site_root();
    if ($root === null) {
        return null;
    }
    $real = realpath($absPath);
    $rootReal = realpath($root);
    if ($real === false || $rootReal === false) {
        // archivo nuevo: validar que el path propuesto está bajo root
        $norm = str_replace('\\', '/', $absPath);
        $rootNorm = rtrim(str_replace('\\', '/', $root), '/');
        if (strpos($norm, $rootNorm . '/') !== 0) {
            return null;
        }
        return ltrim(substr($norm, strlen($rootNorm)), '/');
    }
    $rootNorm = rtrim(str_replace('\\', '/', $rootReal), '/');
    $fileNorm = str_replace('\\', '/', $real);
    if (strpos($fileNorm, $rootNorm . '/') !== 0 && $fileNorm !== $rootNorm) {
        return null;
    }
    return ltrim(substr($fileNorm, strlen($rootNorm)), '/');
}

function cw_seo_mexico_autofix_is_allowed(string $relPath): bool
{
    $rel = str_replace('\\', '/', ltrim($relPath, '/'));
    if ($rel === '' || str_contains($rel, '..')) {
        return false;
    }
    // Bloqueos duros
    $deny = ['conn.php', '.env', 'wp-config', 'vendor/', 'node_modules/', 'cgi-bin/'];
    foreach ($deny as $d) {
        if (stripos($rel, $d) !== false) {
            return false;
        }
    }
    foreach (cw_seo_mexico_autofix_whitelist() as $prefix) {
        if (strpos($rel, $prefix) === 0) {
            return true;
        }
    }
    return false;
}

function cw_seo_mexico_autofix_php_lint(string $absPath): array
{
    if (!preg_match('/\.php$/i', $absPath)) {
        return ['ok' => true, 'output' => 'skip_non_php'];
    }
    $php = defined('PHP_BINARY') && PHP_BINARY !== '' ? PHP_BINARY : 'php';
    // En cPanel a veces PHP_BINARY no sirve; probar rutas comunes
    $candidates = array_unique(array_filter([
        $php,
        'php',
        '/usr/local/bin/php',
        '/usr/bin/php',
        'c:\\xampp\\php\\php.exe',
    ]));
    $out = '';
    $code = 1;
    foreach ($candidates as $bin) {
        $cmd = escapeshellarg($bin) . ' -l ' . escapeshellarg($absPath) . ' 2>&1';
        $lines = [];
        @exec($cmd, $lines, $code);
        $out = implode("\n", $lines);
        if ($code === 0 || str_contains($out, 'No syntax errors')) {
            return ['ok' => true, 'output' => $out, 'bin' => $bin];
        }
        // si el bin no existe, probar siguiente
        if (stripos($out, 'not recognized') !== false || stripos($out, 'No such file') !== false) {
            continue;
        }
        return ['ok' => false, 'output' => $out, 'bin' => $bin];
    }
    // Sin php CLI: aceptar escritura con check básico de tags
    $src = (string) @file_get_contents($absPath);
    if ($src !== '' && str_contains($src, '<?php') && substr_count($src, '<?') >= substr_count($src, '?>')) {
        return ['ok' => true, 'output' => 'lint_skipped_no_cli_basic_ok'];
    }
    return ['ok' => false, 'output' => $out ?: 'php -l no disponible'];
}

/**
 * @return array{ok:bool,applied:bool,path?:string,backup?:string,error?:string}
 */
function cw_seo_mexico_autofix_write(string $relPath, string $contents, string $reason = ''): array
{
    $relPath = str_replace('\\', '/', ltrim($relPath, '/'));
    if (!cw_seo_mexico_autofix_is_allowed($relPath)) {
        return ['ok' => false, 'applied' => false, 'error' => 'Ruta fuera de whitelist: ' . $relPath];
    }
    $root = cw_seo_mexico_autofix_site_root();
    if ($root === null) {
        return ['ok' => false, 'applied' => false, 'error' => 'Sitio conlineweb.com no encontrado en este servidor'];
    }
    $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
    $dir = dirname($abs);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return ['ok' => false, 'applied' => false, 'error' => 'No se pudo crear directorio'];
    }

    $backup = null;
    $hadFile = is_file($abs);
    if ($hadFile) {
        $stamp = date('Ymd_His');
        $safe = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $relPath) ?? 'file';
        $backup = cw_seo_mexico_autofix_backup_dir() . DIRECTORY_SEPARATOR . $stamp . '__' . $safe . '.bak';
        if (!@copy($abs, $backup)) {
            return ['ok' => false, 'applied' => false, 'error' => 'No se pudo crear backup'];
        }
    }

    if (@file_put_contents($abs, $contents) === false) {
        return ['ok' => false, 'applied' => false, 'error' => 'No se pudo escribir archivo', 'backup' => $backup];
    }

    $lint = cw_seo_mexico_autofix_php_lint($abs);
    if (empty($lint['ok'])) {
        if ($hadFile && $backup && is_file($backup)) {
            @copy($backup, $abs);
        } elseif (!$hadFile) {
            @unlink($abs);
        }
        return [
            'ok' => false,
            'applied' => false,
            'error' => 'php -l falló; rollback aplicado. ' . ($lint['output'] ?? ''),
            'backup' => $backup,
            'path' => $relPath,
        ];
    }

    return [
        'ok' => true,
        'applied' => true,
        'path' => $relPath,
        'backup' => $backup,
        'reason' => $reason,
        'lint' => $lint['output'] ?? '',
    ];
}

/**
 * @return array{ok:bool,applied:bool,path?:string,backup?:string,error?:string}
 */
function cw_seo_mexico_autofix_replace(string $relPath, string $search, string $replace, string $reason = ''): array
{
    $relPath = str_replace('\\', '/', ltrim($relPath, '/'));
    if (!cw_seo_mexico_autofix_is_allowed($relPath)) {
        return ['ok' => false, 'applied' => false, 'error' => 'Ruta fuera de whitelist'];
    }
    $root = cw_seo_mexico_autofix_site_root();
    if ($root === null) {
        return ['ok' => false, 'applied' => false, 'error' => 'Sitio no encontrado'];
    }
    $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
    if (!is_file($abs)) {
        return ['ok' => false, 'applied' => false, 'error' => 'Archivo no existe'];
    }
    $src = (string) file_get_contents($abs);
    if ($search === '' || !str_contains($src, $search)) {
        return ['ok' => false, 'applied' => false, 'error' => 'Texto a reemplazar no encontrado'];
    }
    $next = str_replace($search, $replace, $src);
    if ($next === $src) {
        return ['ok' => true, 'applied' => false, 'path' => $relPath, 'error' => 'Sin cambios'];
    }
    return cw_seo_mexico_autofix_write($relPath, $next, $reason);
}

function cw_seo_mexico_autofix_log(
    mysqli $conn,
    string $action,
    string $relPath,
    bool $ok,
    string $detail = '',
    ?string $backup = null,
    int $userId = 0,
    ?string $findingKey = null
): void {
    $stmt = $conn->prepare(
        'INSERT INTO cw_seo_mexico_autofix_log
         (action, rel_path, ok, detail, backup_path, finding_key, created_by, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    if (!$stmt) {
        return;
    }
    $okI = $ok ? 1 : 0;
    $uid = max(0, $userId);
    $backupS = (string) ($backup ?? '');
    $findingS = (string) ($findingKey ?? '');
    $stmt->bind_param('ssisssi', $action, $relPath, $okI, $detail, $backupS, $findingS, $uid);
    $stmt->execute();
    $stmt->close();
}

/**
 * @return list<array<string,mixed>>
 */
function cw_seo_mexico_autofix_recent(mysqli $conn, int $limit = 20): array
{
    $limit = max(1, min(100, $limit));
    $rows = [];
    $res = $conn->query(
        "SELECT id, action, rel_path, ok, detail, backup_path, finding_key, created_at
         FROM cw_seo_mexico_autofix_log
         ORDER BY id DESC LIMIT {$limit}"
    );
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        $res->free();
    }
    return $rows;
}

/**
 * Resuelve task_key a partir de finding_key (auditoría).
 */
function cw_seo_mexico_autofix_resolve_task_key(mysqli $conn, ?string $findingKey): ?string
{
    $findingKey = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $findingKey)) ?? '';
    if ($findingKey === '') {
        return null;
    }
    $stmt = $conn->prepare(
        'SELECT task_key FROM cw_seo_mexico_audit_findings
         WHERE finding_key = ? AND task_key IS NOT NULL AND task_key != \'\'
         ORDER BY id DESC LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $findingKey);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    $tk = trim((string) ($row['task_key'] ?? ''));
    return $tk !== '' ? $tk : null;
}

/**
 * Feed unificado: actualizaciones de checklist + AutoFix (misma dinámica de historial).
 *
 * @return list<array<string,mixed>>
 */
function cw_seo_mexico_checklist_activity_feed(mysqli $conn, int $limit = 60): array
{
    $limit = max(10, min(120, $limit));
    $feed = [];

    $updates = cw_seo_mexico_checklist_updates($conn, null, $limit);
    foreach ($updates as $u) {
        $feed[] = [
            'kind' => 'update',
            'task_key' => (string) ($u['task_key'] ?? ''),
            'summary' => (string) ($u['summary'] ?? 'Actualización'),
            'detail' => (string) ($u['detail'] ?? ''),
            'ok' => true,
            'created_at' => (string) ($u['created_at'] ?? ''),
            'meta' => '',
        ];
    }

    foreach (cw_seo_mexico_autofix_recent($conn, (int) ceil($limit / 2)) as $fx) {
        $tk = cw_seo_mexico_autofix_resolve_task_key($conn, $fx['finding_key'] ?? null);
        $act = (string) ($fx['action'] ?? '');
        if ($tk === null && strpos($act, 'nap') !== false) {
            $tk = 'marca_consistente';
        }
        if ($tk === null && strpos($act, 'stub') !== false) {
            $tk = 'audit_http_status';
        }
        $feed[] = [
            'kind' => 'autofix',
            'task_key' => (string) ($tk ?? 'audit_live_engine'),
            'summary' => 'AutoFix · ' . (string) ($fx['action'] ?? 'fix'),
            'detail' => trim(
                (string) ($fx['rel_path'] ?? '') . "\n"
                . (string) ($fx['detail'] ?? '')
                . (!empty($fx['backup_path']) ? "\nBackup: " . $fx['backup_path'] : '')
            ),
            'ok' => !empty($fx['ok']),
            'created_at' => (string) ($fx['created_at'] ?? ''),
            'meta' => (string) ($fx['rel_path'] ?? ''),
        ];
    }

    usort($feed, static function (array $a, array $b): int {
        return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
    });

    return array_slice($feed, 0, $limit);
}

/**
 * Genera stub geo faltante (mismo servidor).
 *
 * @return array{ok:bool,applied:bool,path?:string,backup?:?string,error?:string}
 */
function cw_seo_mexico_autofix_city_stub(string $citySlug, string $serviceSlug = ''): array
{
    $citySlug = preg_replace('/[^a-z0-9\-]/', '', strtolower($citySlug)) ?? '';
    if ($citySlug === '') {
        return ['ok' => false, 'applied' => false, 'error' => 'slug inválido'];
    }

    $isServicesHub = ($serviceSlug === 'servicios');
    if ($isServicesHub) {
        $serviceSlug = '';
    } elseif ($serviceSlug !== '') {
        $serviceSlug = preg_replace('/[^a-z0-9\-]/', '', strtolower($serviceSlug)) ?? '';
    }

    if ($isServicesHub) {
        $rel = "mexico/ciudades/{$citySlug}/servicios/index.php";
        $php = "<?php\nrequire_once __DIR__ . '/../../../../includes/mexico/page-factory.php';\n"
            . "\$page = mx_page_geo_servicios_hub('ciudad', " . var_export($citySlug, true) . ");\n"
            . "if (\$page === null) { http_response_code(404); exit; }\n"
            . "mx_render(\$page);\n";
    } elseif ($serviceSlug !== '') {
        $rel = "mexico/ciudades/{$citySlug}/servicios/{$serviceSlug}/index.php";
        $php = "<?php\nrequire_once __DIR__ . '/../../../../../includes/mexico/page-factory.php';\n"
            . "\$page = mx_page_servicio_geo('ciudad', " . var_export($citySlug, true) . ', ' . var_export($serviceSlug, true) . ");\n"
            . "if (\$page === null) { http_response_code(404); exit; }\n"
            . "mx_render(\$page);\n";
    } else {
        $rel = "mexico/ciudades/{$citySlug}/index.php";
        $php = "<?php\nrequire_once __DIR__ . '/../../../includes/mexico/page-factory.php';\n"
            . "\$page = mx_page_ciudad(" . var_export($citySlug, true) . ");\n"
            . "if (\$page === null) { http_response_code(404); exit; }\n"
            . "mx_render(\$page);\n";
    }

    $root = cw_seo_mexico_autofix_site_root();
    if ($root === null) {
        return ['ok' => false, 'applied' => false, 'error' => 'Sitio no encontrado'];
    }
    $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (is_file($abs)) {
        return ['ok' => true, 'applied' => false, 'path' => $rel];
    }

    return cw_seo_mexico_autofix_write($rel, $php, 'Crear stub geo faltante (HTTP 404)');
}

/**
 * Foto rápida de una URL (o ruta archivo) para reporte antes/después.
 *
 * @return array<string,mixed>
 */
function cw_seo_mexico_autofix_snapshot_url(string $url): array
{
    $url = trim($url);
    if ($url === '' || str_starts_with($url, 'file://')) {
        $rel = str_replace('file://', '', $url);
        $root = cw_seo_mexico_autofix_site_root();
        $exists = false;
        if ($root && $rel !== '') {
            $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($rel, '/'));
            $exists = is_file($abs);
        }
        return [
            'url' => $url,
            'kind' => 'file',
            'ok' => $exists,
            'status' => $exists ? 200 : 0,
            'title' => '',
            'h1' => '',
            'final_url' => $url,
            'summary' => $exists ? ('Archivo existe: ' . $rel) : ('Archivo ausente: ' . ($rel !== '' ? $rel : $url)),
        ];
    }

    if (!preg_match('#^https?://#i', $url)) {
        return [
            'url' => $url,
            'kind' => 'other',
            'ok' => false,
            'status' => 0,
            'title' => '',
            'h1' => '',
            'final_url' => $url,
            'summary' => 'Sin URL HTTP para medir',
        ];
    }

    if (!function_exists('cw_seo_mexico_audit_fetch')) {
        require_once __DIR__ . '/cw_seo_mexico_audit.php';
    }

    $fetch = cw_seo_mexico_audit_fetch($url, 14);
    $parsed = [];
    if (!empty($fetch['body']) && !str_contains(strtolower($url), 'sitemap') && !str_contains(strtolower($url), 'robots.txt')) {
        $parsed = cw_seo_mexico_audit_parse_html((string) $fetch['body']);
    }
    $status = (int) ($fetch['status'] ?? 0);
    $ok = !empty($fetch['ok']);
    $title = (string) ($parsed['title'] ?? '');
    $h1 = (string) ($parsed['h1'] ?? '');
    $final = (string) ($fetch['final_url'] ?? $url);
    $parts = ['HTTP ' . $status];
    if ($title !== '') {
        $parts[] = 'title: ' . mb_substr($title, 0, 90);
    } elseif ($h1 !== '') {
        $parts[] = 'h1: ' . mb_substr($h1, 0, 70);
    }
    if ($final !== '' && rtrim($final, '/') !== rtrim($url, '/')) {
        $parts[] = '→ ' . $final;
    }

    return [
        'url' => $url,
        'kind' => 'http',
        'ok' => $ok,
        'status' => $status,
        'title' => $title,
        'h1' => $h1,
        'final_url' => $final,
        'summary' => implode(' · ', $parts),
    ];
}

/**
 * Hallazgos abiertos auto-corregibles.
 *
 * @return list<array<string,mixed>>
 */
function cw_seo_mexico_autofix_open_fixable(mysqli $conn, int $limit = 40): array
{
    $limit = max(1, min(80, $limit));
    $rows = [];
    $res = $conn->query(
        "SELECT id, finding_key, check_type, severity, url, title, evidence, correction, auto_fixable, auto_applied, task_key, meta_json
         FROM cw_seo_mexico_audit_findings
         WHERE status = 'open' AND auto_fixable = 1 AND auto_applied = 0
         ORDER BY FIELD(severity,'critical','high','medium','low'), id ASC
         LIMIT {$limit}"
    );
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        $res->free();
    }
    return $rows;
}

/**
 * Corrige UN hallazgo y devuelve reporte antes/después + URL.
 *
 * @param array<string,mixed> $finding fila de cw_seo_mexico_audit_findings
 * @return array{ok:bool,applied:bool,item:array<string,mixed>,error?:string}
 */
function cw_seo_mexico_autofix_fix_one_finding(mysqli $conn, array $finding, int $userId = 0): array
{
    if (!function_exists('cw_seo_mexico_audit_auto_fix')) {
        require_once __DIR__ . '/cw_seo_mexico_audit.php';
    }

    $root = cw_seo_mexico_autofix_site_root();
    if ($root === null) {
        return [
            'ok' => false,
            'applied' => false,
            'item' => [],
            'error' => 'conlineweb.com no está en este servidor; AutoFix no puede escribir.',
        ];
    }

    if (empty($finding['auto_fixable'])) {
        return [
            'ok' => false,
            'applied' => false,
            'item' => [],
            'error' => 'Este hallazgo no es auto-corregible (requiere acción manual).',
        ];
    }
    if ((string) ($finding['status'] ?? 'open') !== 'open' || !empty($finding['auto_applied'])) {
        return [
            'ok' => false,
            'applied' => false,
            'item' => [],
            'error' => 'Este hallazgo ya no está abierto o ya fue corregido.',
        ];
    }

    $url = (string) ($finding['url'] ?? '');
    $fkey = (string) ($finding['finding_key'] ?? '');
    $title = (string) ($finding['title'] ?? $fkey);
    $taskKey = (string) ($finding['task_key'] ?? '');
    $findingId = (int) ($finding['id'] ?? 0);

    $before = cw_seo_mexico_autofix_snapshot_url($url);
    $fix = cw_seo_mexico_audit_auto_fix($finding, $conn, $userId);
    usleep(120000);
    $after = cw_seo_mexico_autofix_snapshot_url($url);

    $didApply = !empty($fix['applied']);
    $ok = !empty($fix['ok']);
    if ($didApply && $findingId > 0) {
        $conn->query(
            'UPDATE cw_seo_mexico_audit_findings
             SET auto_applied = 1, status = \'fixed\', updated_at = NOW()
             WHERE id = ' . $findingId
        );
    }

    $relPath = (string) ($fix['path'] ?? '');
    $item = [
        'finding_id' => $findingId,
        'finding_key' => $fkey,
        'task_key' => $taskKey,
        'title' => $title,
        'check_type' => (string) ($finding['check_type'] ?? ''),
        'url' => $url,
        'rel_path' => $relPath,
        'applied' => $didApply,
        'ok' => $ok,
        'error' => (string) ($fix['error'] ?? ($fix['note'] ?? '')),
        'before' => $before,
        'after' => $after,
        'improved' => (!empty($after['ok']) && empty($before['ok']))
            || ((int) ($after['status'] ?? 0) > 0 && (int) ($after['status'] ?? 0) < 400
                && ((int) ($before['status'] ?? 0) >= 400 || (int) ($before['status'] ?? 0) === 0)),
    ];

    $detailJson = json_encode([
        'report' => true,
        'url' => $url,
        'before' => $before['summary'] ?? '',
        'after' => $after['summary'] ?? '',
        'before_status' => $before['status'] ?? null,
        'after_status' => $after['status'] ?? null,
        'improved' => $item['improved'],
        'error' => $item['error'],
    ], JSON_UNESCAPED_UNICODE);

    cw_seo_mexico_autofix_log(
        $conn,
        $didApply ? 'fix_one' : 'fix_one_skip',
        $relPath !== '' ? $relPath : $url,
        $didApply || ($ok && $item['improved']),
        (string) $detailJson,
        isset($fix['backup']) ? (string) $fix['backup'] : null,
        $userId,
        $fkey
    );

    if ($taskKey !== '') {
        cw_seo_mexico_checklist_log_update(
            $conn,
            $taskKey,
            ($didApply ? 'Corrección aplicada' : 'Corrección intentada') . ': ' . mb_substr($title, 0, 120),
            "URL: {$url}\nANTES: " . ($before['summary'] ?? '') . "\nDESPUÉS: " . ($after['summary'] ?? '')
            . ($relPath !== '' ? "\nArchivo: {$relPath}" : '')
            . ($item['error'] !== '' ? "\nNota: " . $item['error'] : ''),
            $userId
        );
    }

    return [
        'ok' => true,
        'applied' => $didApply,
        'item' => $item,
        'message' => $didApply
            ? 'Corrección aplicada en ' . ($url !== '' ? $url : $relPath)
            : ('Sin escritura: ' . ($item['error'] !== '' ? $item['error'] : 'ya existía o no aplica')),
    ];
}

/**
 * Carga hallazgo por id y corrige con reporte.
 *
 * @return array{ok:bool,applied:bool,item:array<string,mixed>,error?:string,message?:string}
 */
function cw_seo_mexico_autofix_fix_one_by_id(mysqli $conn, int $findingId, int $userId = 0): array
{
    $findingId = max(0, $findingId);
    if ($findingId < 1) {
        return ['ok' => false, 'applied' => false, 'item' => [], 'error' => 'finding_id inválido'];
    }
    $stmt = $conn->prepare(
        'SELECT id, finding_key, check_type, severity, status, url, title, evidence, correction,
                auto_fixable, auto_applied, task_key, meta_json
         FROM cw_seo_mexico_audit_findings WHERE id = ? LIMIT 1'
    );
    if (!$stmt) {
        return ['ok' => false, 'applied' => false, 'item' => [], 'error' => 'No se pudo consultar el hallazgo'];
    }
    $stmt->bind_param('i', $findingId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!is_array($row)) {
        return ['ok' => false, 'applied' => false, 'item' => [], 'error' => 'Hallazgo no encontrado'];
    }
    return cw_seo_mexico_autofix_fix_one_finding($conn, $row, $userId);
}

/**
 * Ejecuta correcciones abiertas (lote) y arma reporte antes/después + URL.
 *
 * @return array{ok:bool,applied:int,failed:int,skipped:int,items:list<array<string,mixed>>,error?:string}
 */
function cw_seo_mexico_autofix_fix_open_with_report(mysqli $conn, int $userId = 0, int $limit = 25): array
{
    $root = cw_seo_mexico_autofix_site_root();
    if ($root === null) {
        return [
            'ok' => false,
            'applied' => 0,
            'failed' => 0,
            'skipped' => 0,
            'items' => [],
            'error' => 'conlineweb.com no está en este servidor; AutoFix no puede escribir.',
        ];
    }

    $findings = cw_seo_mexico_autofix_open_fixable($conn, $limit);
    if ($findings === []) {
        return [
            'ok' => true,
            'applied' => 0,
            'failed' => 0,
            'skipped' => 0,
            'items' => [],
            'message' => 'No hay hallazgos abiertos auto-corregibles. Ejecuta primero una auditoría.',
        ];
    }

    $items = [];
    $applied = 0;
    $failed = 0;
    $skipped = 0;

    foreach ($findings as $f) {
        $one = cw_seo_mexico_autofix_fix_one_finding($conn, $f, $userId);
        $item = is_array($one['item'] ?? null) ? $one['item'] : [];
        if ($item !== []) {
            $items[] = $item;
        }
        if (!empty($one['applied'])) {
            $applied++;
        } elseif (!empty($one['ok']) && empty($one['applied'])) {
            $skipped++;
        } else {
            $failed++;
        }
    }

    return [
        'ok' => true,
        'applied' => $applied,
        'failed' => $failed,
        'skipped' => $skipped,
        'items' => $items,
        'root' => $root,
        'message' => "Correcciones: {$applied} aplicadas · {$skipped} sin cambio · {$failed} fallidas",
    ];
}
