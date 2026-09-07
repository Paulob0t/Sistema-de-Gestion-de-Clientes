<?php
/**
 * Verificación módulo SEO México (post-deploy).
 * https://adm.conlineweb.com/analytics/seo_deploy_check.php
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/auth_middleware.php';
require_once dirname(__DIR__) . '/conn.php';
require_once dirname(__DIR__) . '/includes/cw_hub_config.php';
require_once dirname(__DIR__) . '/includes/cw_hub_permissions.php';

cw_hub_require('hub.analytics.view');

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);
$checks = [];
$ok = true;

function seo_dc(array &$checks, bool &$ok, string $id, string $label, bool $pass, string $detail = ''): void
{
    $checks[] = ['id' => $id, 'label' => $label, 'ok' => $pass, 'detail' => $detail];
    if (!$pass) {
        $ok = false;
    }
}

$requiredFiles = [
    'analytics/seo_mexico_checklist.php',
    'analytics/seo_mexico_external.php',
    'analytics/seo_mexico_monitor.php',
    'analytics/seo_mexico_ai_prompt.php',
    'analytics/seo_mexico_plazas.php',
    'analytics/seo_mexico_urls.php',
    'analytics/css/seo-module.css',
    'includes/website_module_shell.php',
    'includes/seo_module_nav.php',
    'includes/cw_seo_mexico_checklist.php',
    'includes/cw_seo_mexico_checklist_catalog_data.php',
    'includes/cw_seo_mexico_checklist_roadmap.php',
    'includes/cw_seo_mexico_short_urls_strategy.php',
    'includes/cw_seo_mexico_audit.php',
    'includes/cw_seo_mexico_autofix.php',
    'includes/cw_seo_mexico_ai.php',
    'includes/cw_seo_mexico_ai_constitution.php',
    'includes/cw_seo_mexico_kpis.php',
    'includes/cw_seo_mexico_external.php',
    'includes/seo_mexico_page_guard.php',
    'includes/cw_seo_mexico_monitor.php',
    'menu.php',
];

foreach ($requiredFiles as $rel) {
    $path = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $exists = is_file($path);
    $detail = $exists ? 'OK' : 'FALTA en servidor — subir por FTP/cPanel';
    if ($exists && str_ends_with($rel, 'seo_mexico_checklist.php')) {
        $sz = (int) filesize($path);
        if ($sz < 140000) {
            $exists = false;
            $detail = "truncado ({$sz} bytes; esperado ~158000) — resubir en binario";
        } else {
            $detail = "OK ({$sz} bytes)";
        }
    }
    if ($exists && str_ends_with($rel, 'cw_seo_mexico_checklist_catalog_data.php')) {
        $sz = (int) filesize($path);
        if ($sz < 15000) {
            $exists = false;
            $detail = "truncado ({$sz} bytes)";
        }
    }
    seo_dc($checks, $ok, 'file_' . md5($rel), $rel, $exists, $detail);
}

$menuBody = is_file($root . '/menu.php') ? (string) file_get_contents($root . '/menu.php') : '';
seo_dc(
    $checks,
    $ok,
    'menu_seo_links',
    'menu.php incluye enlaces SEO checklist',
    str_contains($menuBody, 'seo_mexico_checklist.php') && str_contains($menuBody, 'seo_mexico_external.php'),
    str_contains($menuBody, 'seo_mexico_checklist.php') ? 'enlaces presentes' : 'menu.php desactualizado'
);

$shellBody = is_file($root . '/includes/website_module_shell.php')
    ? (string) file_get_contents($root . '/includes/website_module_shell.php') : '';
seo_dc(
    $checks,
    $ok,
    'shell_seo_tabs',
    'website_module_shell.php incluye pestañas SEO',
    str_contains($shellBody, 'seo_mexico_checklist.php'),
    str_contains($shellBody, 'seo_mexico_checklist.php') ? 'OK' : 'shell desactualizado'
);

$dbOk = isset($conn) && $conn && !$conn->connect_error;
seo_dc($checks, $ok, 'db', 'Conexión MySQL', $dbOk, $dbOk ? 'OK' : 'sin BD');

if ($dbOk) {
    require_once dirname(__DIR__) . '/includes/cw_hub_migrate.php';
    try {
        cw_hub_migrate($conn);
        seo_dc($checks, $ok, 'migrate', 'cw_hub_migrate ejecutado', true, 'OK');
    } catch (Throwable $e) {
        seo_dc($checks, $ok, 'migrate', 'cw_hub_migrate', false, $e->getMessage());
    }

    foreach ([
        'cw_seo_mexico_checklist',
        'cw_seo_mexico_checklist_updates',
        'cw_seo_mexico_audit_runs',
        'cw_seo_mexico_ai_proposals',
    ] as $table) {
        $r = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
        $exists = $r && $r->num_rows > 0;
        seo_dc($checks, $ok, 'table_' . $table, "Tabla {$table}", $exists, $exists ? 'OK' : 'ejecutar hub_migrate.php');
    }
}

seo_dc(
    $checks,
    $ok,
    'php_version',
    'PHP >= 8.0',
    PHP_VERSION_ID >= 80000,
    PHP_VERSION
);

$loadOk = false;
$loadDetail = 'omitido';
if (PHP_VERSION_ID >= 80000 && is_file($root . '/includes/cw_seo_mexico_checklist_roadmap.php')) {
    try {
        require_once $root . '/includes/cw_seo_mexico_checklist_roadmap.php';
        $loadOk = function_exists('cw_seo_mexico_roadmap_group');
        $loadDetail = $loadOk ? 'roadmap cargado OK' : 'roadmap sin función esperada';
    } catch (Throwable $e) {
        $loadDetail = $e->getMessage();
    }
} elseif (PHP_VERSION_ID < 80000) {
    $loadDetail = 'PHP ' . PHP_VERSION . ' — requiere 8.0+';
}
seo_dc($checks, $ok, 'checklist_bootstrap', 'Carga includes/cw_seo_mexico_checklist_roadmap.php', $loadOk, $loadDetail);

echo json_encode([
    'ok' => $ok,
    'host' => $_SERVER['HTTP_HOST'] ?? '',
    'php' => PHP_VERSION,
    'user_tipo' => (int) ($_SESSION['tipo'] ?? 0),
    'hub_analytics' => cw_hub_can('hub.analytics.view'),
    'urls' => [
        'checklist' => 'analytics/seo_mexico_checklist.php',
        'external' => 'analytics/seo_mexico_external.php',
    ],
    'checks' => $checks,
    'next_steps' => $ok
        ? ['Abrir analytics/seo_mexico_checklist.php', 'Menú: Web Site → SEO · Auditoría código']
        : ['Subir archivos con ok:false', 'Ejecutar analytics/hub_migrate.php', 'Actualizar menu.php y website_module_shell.php'],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
