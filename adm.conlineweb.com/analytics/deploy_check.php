<?php
/**
 * Verificación post-deploy — requiere sesión admin con hub.analytics.view
 * https://adm.conlineweb.com/analytics/deploy_check.php
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/auth_middleware.php';
require_once dirname(__DIR__) . '/conn.php';
require_once dirname(__DIR__) . '/includes/cw_hub_config.php';

cw_hub_require('hub.analytics.view');

header('Content-Type: application/json; charset=utf-8');

$checks = [];
$ok = true;

function dc_add(array &$checks, bool &$ok, string $id, string $label, bool $pass, string $detail = ''): void
{
    $checks[] = [
        'id' => $id,
        'label' => $label,
        'ok' => $pass,
        'detail' => $detail,
    ];
    if (!$pass) {
        $ok = false;
    }
}

// BD
$dbOk = $conn && !$conn->connect_error;
$dbName = '';
if ($dbOk) {
    $r = $conn->query('SELECT DATABASE()');
    $dbName = (string) ($r?->fetch_row()[0] ?? '');
}
dc_add($checks, $ok, 'db', 'Conexión MySQL', $dbOk, $dbOk ? $dbName : ($conn->connect_error ?? 'sin conexión'));

// Modo producción
$isLocal = defined('ADM_DEV_LOCAL') && ADM_DEV_LOCAL;
dc_add($checks, $ok, 'prod_mode', 'Modo producción (sin ADM_DEV_LOCAL)', !$isLocal, $isLocal ? 'ADM_DEV_LOCAL activo — quitar cw_hub_config.local.php' : 'OK');
dc_add($checks, $ok, 'prod_filter', 'Filtro solo tráfico producción', CW_HUB_ANALYTICS_PRODUCTION_ONLY, CW_HUB_ANALYTICS_PRODUCTION_ONLY ? 'activo' : 'desactivado');

// Zona horaria
dc_add($checks, $ok, 'tz', 'Zona horaria CDMX', CW_HUB_TIMEZONE === 'America/Mexico_City', CW_HUB_TIMEZONE . ' → ' . cw_hub_now());

// Extensiones PHP
dc_add($checks, $ok, 'curl', 'Extensión curl', function_exists('curl_init'), function_exists('curl_init') ? 'OK' : 'Requerida para proxy web→adm');
dc_add($checks, $ok, 'mysqli', 'Extensión mysqli', extension_loaded('mysqli'), extension_loaded('mysqli') ? 'OK' : 'FALTA');

// Tablas Hub
$requiredTables = [
    'cw_analytics_sessions',
    'cw_analytics_pageviews',
    'cw_analytics_events',
    'cw_analytics_admin_session_views',
    'cw_lead_actividades',
    'cw_lead_adjuntos',
    'leads',
];
foreach ($requiredTables as $table) {
    $exists = false;
    if ($dbOk) {
        $r = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
        $exists = $r && $r->num_rows > 0;
    }
    dc_add($checks, $ok, 'table_' . $table, "Tabla {$table}", $exists, $exists ? 'existe' : 'ejecutar hub_migrate.php');
}

// Columnas críticas
$requiredCols = [
    'cw_analytics_sessions' => ['device_type', 'screen_w', 'viewport_w', 'country', 'region', 'geo_city', 'geo_lat', 'geo_lng', 'geo_address', 'contact_nombre', 'lead_id'],
    'cw_analytics_pageviews' => ['country', 'region', 'geo_city', 'visitor_id'],
    'leads' => ['origen_web', 'session_id', 'pagina_origen'],
];
foreach ($requiredCols as $table => $cols) {
    foreach ($cols as $col) {
        $exists = false;
        if ($dbOk) {
            $r = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '" . $conn->real_escape_string($col) . "'");
            $exists = $r && $r->num_rows > 0;
        }
        dc_add($checks, $ok, "col_{$table}_{$col}", "{$table}.{$col}", $exists, $exists ? 'OK' : 'migración pendiente');
    }
}

// API key sincronizada (mismo valor en config)
$keyLen = strlen(CW_HUB_API_KEY);
dc_add($checks, $ok, 'api_key', 'API key Hub definida', $keyLen >= 20, 'longitud ' . $keyLen);

// CORS htaccess
$corsFile = dirname(__DIR__) . '/api/web/.htaccess';
dc_add($checks, $ok, 'cors_htaccess', 'api/web/.htaccess (CORS)', is_file($corsFile), is_file($corsFile) ? 'presente' : 'FALTA');

// Archivos analytics nuevos
$requiredFiles = [
    'analytics/sessions.php',
    'analytics/js/datatables-es.js',
    'analytics/js/page-users-modal.js',
    'analytics/api/sessions_list.php',
    'analytics/api/session_trace.php',
    'analytics/api/session_admin_view.php',
    'analytics/api/page_users.php',
    'website/api/lead_journey.php',
    'includes/cw_hub_analytics.php',
    'includes/cw_hub_migrate.php',
    'analytics/seo_mexico_checklist.php',
    'analytics/seo_mexico_external.php',
    'includes/cw_seo_mexico_short_urls_strategy.php',
    'includes/website_module_shell.php',
    'analytics/seo_deploy_check.php',
];
$root = dirname(__DIR__);
foreach ($requiredFiles as $rel) {
    $path = $root . '/' . $rel;
    dc_add($checks, $ok, 'file_' . str_replace('/', '_', $rel), $rel, is_file($path), is_file($path) ? 'OK' : 'no encontrado en servidor');
}

// Conteos rápidos
$counts = [];
if ($dbOk) {
    foreach ([
        'sessions' => 'SELECT COUNT(*) c FROM cw_analytics_sessions',
        'pageviews' => 'SELECT COUNT(*) c FROM cw_analytics_pageviews',
        'leads_web' => 'SELECT COUNT(*) c FROM leads WHERE eliminado=0 AND origen_web=1',
        'admin_views' => 'SELECT COUNT(*) c FROM cw_analytics_admin_session_views',
    ] as $k => $sql) {
        $r = $conn->query($sql);
        $counts[$k] = (int) ($r?->fetch_assoc()['c'] ?? 0);
    }
}

// Remoto web (adm alcanzable)
$remoteOk = false;
$remoteDetail = CW_HUB_REMOTE_URL ?? 'n/a';
if (function_exists('curl_init')) {
    $ch = curl_init('https://adm.conlineweb.com/api/web/bootstrap.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_NOBODY => true,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $remoteOk = $code >= 200 && $code < 500;
    $remoteDetail = 'HTTP ' . $code;
}
dc_add($checks, $ok, 'adm_reachable', 'adm.conlineweb.com API accesible', $remoteOk, $remoteDetail);

echo json_encode([
    'ok' => $ok,
    'host' => $_SERVER['HTTP_HOST'] ?? '',
    'deploy_ready' => $ok,
    'counts' => $counts,
    'checks' => $checks,
    'next_steps' => $ok
        ? ['Probar https://conlineweb.com/ y verificar POST track.php', 'Revisar analytics/sessions.php']
        : ['Ejecutar analytics/hub_migrate.php', 'Revisar checks con ok:false', 'Ver DEPLOY-PRODUCCION.md'],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
