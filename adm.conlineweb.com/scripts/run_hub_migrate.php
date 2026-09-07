<?php
/**
 * CLI: crea tablas/columnas Hub (cw_*) en admin_clientes.
 * Uso: php scripts/run_hub_migrate.php
 */
require_once dirname(__DIR__) . '/conn.php';
require_once dirname(__DIR__) . '/includes/cw_hub_config.php';
require_once dirname(__DIR__) . '/includes/cw_hub_migrate.php';

if (!$conn || $conn->connect_error) {
    fwrite(STDERR, "Error de conexión: " . ($conn->connect_error ?? 'sin conn') . PHP_EOL);
    exit(1);
}

echo "BD: " . ($conn->query('SELECT DATABASE()')->fetch_row()[0] ?? '?') . PHP_EOL;

cw_hub_migrate($conn);

$tables = [
    'cw_analytics_sessions',
    'cw_analytics_pageviews',
    'cw_analytics_events',
    'cw_analytics_admin_session_views',
    'cw_lead_actividades',
    'cw_lead_adjuntos',
    'leads',
    'tablas_leads',
];

foreach ($tables as $t) {
    $r = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($t) . "'");
    echo $t . ': ' . ($r && $r->num_rows ? 'OK' : 'FALTA') . PHP_EOL;
}

$cols = ['origen_web', 'inbox_leido_at', 'pipeline_estado', 'servicio', 'session_id', 'fuente', 'pagina_origen', 'whatsapp_enviado', 'whatsapp_enviado_fecha'];
foreach ($cols as $c) {
    $r = $conn->query("SHOW COLUMNS FROM leads LIKE '" . $conn->real_escape_string($c) . "'");
    if ($r && $r->num_rows) {
        echo 'leads.' . $c . ': OK' . PHP_EOL;
        continue;
    }
    if ($c === 'whatsapp_enviado') {
        $conn->query('ALTER TABLE leads ADD COLUMN whatsapp_enviado TINYINT(1) NOT NULL DEFAULT 0');
    } elseif ($c === 'whatsapp_enviado_fecha') {
        $conn->query('ALTER TABLE leads ADD COLUMN whatsapp_enviado_fecha DATETIME NULL DEFAULT NULL');
    }
    echo 'leads.' . $c . ': CREADA' . PHP_EOL;
}

echo "Migración completada." . PHP_EOL;
