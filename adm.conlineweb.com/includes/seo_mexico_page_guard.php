<?php
/**
 * Bootstrap temprano módulo SEO México (antes de require de includes pesados).
 * Evita pantalla en blanco en cPanel cuando falta un archivo, PHP < 8 o error fatal.
 */
declare(strict_types=1);

function seo_mexico_page_guard_html(string $title, string $message, array $extra = []): void
{
    if (!headers_sent()) {
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
    }
    $host = htmlspecialchars((string) ($_SERVER['HTTP_HOST'] ?? 'adm.conlineweb.com'), ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
    echo '<style>body{font-family:system-ui,sans-serif;padding:2rem;max-width:760px;margin:auto;line-height:1.5;color:#1a1a1a}';
    echo 'code{background:#f3f4f6;padding:.15em .4em;border-radius:4px;font-size:.92em}ul{padding-left:1.25rem}a{color:#0d6efd}</style></head><body>';
    echo '<h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';
    echo '<p>' . $message . '</p>';
    if ($extra !== []) {
        echo '<ul>';
        foreach ($extra as $line) {
            echo '<li>' . htmlspecialchars((string) $line, ENT_QUOTES, 'UTF-8') . '</li>';
        }
        echo '</ul>';
    }
    echo '<p><strong>Host:</strong> ' . $host . ' · <strong>PHP:</strong> ' . htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p><a href="/analytics/seo_deploy_check.php">Diagnóstico JSON (seo_deploy_check.php)</a></p>';
    echo '</body></html>';
    exit;
}

function seo_mexico_page_guard_bootstrap(string $admRoot): void
{
    if (defined('SEO_MEXICO_PAGE_GUARD_OK')) {
        return;
    }

    if (PHP_VERSION_ID < 80000) {
        seo_mexico_page_guard_html(
            'SEO México — PHP incompatible',
            'Este módulo requiere <strong>PHP 8.0 o superior</strong>. En cPanel: MultiPHP Manager → selecciona el subdominio <code>adm</code> → PHP 8.1+.',
            ['Versión actual: ' . PHP_VERSION]
        );
    }

    @ini_set('memory_limit', '512M');
    @ini_set('max_execution_time', '180');

    register_shutdown_function(static function (): void {
        $err = error_get_last();
        if ($err === null) {
            return;
        }
        $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($err['type'], $fatal, true)) {
            return;
        }
        if (headers_sent()) {
            return;
        }
        $msg = (string) ($err['message'] ?? 'Error fatal');
        $file = (string) ($err['file'] ?? '');
        $line = (int) ($err['line'] ?? 0);
        $shortFile = $file !== '' ? basename($file) : 'desconocido';
        seo_mexico_page_guard_html(
            'SEO México — error al cargar',
            'El servidor detuvo la página por un error fatal (pantalla en blanco evitada). Revisa el error_log de cPanel o vuelve a subir los archivos del módulo.',
            [
                $msg,
                'Archivo: ' . $shortFile . ($line > 0 ? ' (línea ' . $line . ')' : ''),
                'Si el mensaje menciona memoria: sube memory_limit en MultiPHP INI Editor.',
                'Si menciona un include: sube toda la carpeta includes/ desde local.',
            ]
        );
    });

    $incDir = $admRoot . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR;
    $analyticsDir = $admRoot . DIRECTORY_SEPARATOR . 'analytics' . DIRECTORY_SEPARATOR;

    $requiredIncludes = [
        'cw_seo_mexico_checklist.php',
        'cw_seo_mexico_checklist_catalog_data.php',
        'cw_seo_mexico_checklist_roadmap.php',
        'cw_seo_mexico_short_urls_strategy.php',
        'cw_seo_mexico_audit.php',
        'cw_seo_mexico_autofix.php',
        'cw_seo_mexico_ai.php',
        'cw_seo_mexico_ai_constitution.php',
        'cw_seo_mexico_kpis.php',
        'cw_seo_mexico_external.php',
        'website_module_shell.php',
        'seo_module_nav.php',
    ];

    $missing = [];
    foreach ($requiredIncludes as $name) {
        if (!is_file($incDir . $name)) {
            $missing[] = 'includes/' . $name;
        }
    }

    $checklistPage = $analyticsDir . 'seo_mexico_checklist.php';
    if (!is_file($checklistPage)) {
        $missing[] = 'analytics/seo_mexico_checklist.php';
    } else {
        $size = (int) filesize($checklistPage);
        if ($size < 140000) {
            $missing[] = 'analytics/seo_mexico_checklist.php truncado (' . $size . ' bytes; esperado ~158 KB — vuelve a subir por FTP en modo binario)';
        }
    }

    $catalogData = $incDir . 'cw_seo_mexico_checklist_catalog_data.php';
    if (is_file($catalogData)) {
        $catSize = (int) filesize($catalogData);
        if ($catSize < 15000) {
            $missing[] = 'includes/cw_seo_mexico_checklist_catalog_data.php truncado (' . $catSize . ' bytes)';
        }
    }

    if ($missing !== []) {
        seo_mexico_page_guard_html(
            'SEO México — despliegue incompleto',
            'Faltan archivos o la subida a cPanel quedó incompleta. Sube <code>includes/</code> y <code>analytics/</code> desde tu copia local (File Manager o FTP binario).',
            $missing
        );
    }

    define('SEO_MEXICO_PAGE_GUARD_OK', true);
}
