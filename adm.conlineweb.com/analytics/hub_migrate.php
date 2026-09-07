<?php
/**
 * Migración Hub vía navegador (producción cPanel)
 * https://adm.conlineweb.com/analytics/hub_migrate.php
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/auth_middleware.php';
require_once dirname(__DIR__) . '/conn.php';
require_once dirname(__DIR__) . '/includes/cw_hub_config.php';
require_once dirname(__DIR__) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__) . '/includes/cw_hub_permissions.php';

cw_hub_require('hub.analytics.view');

$ran = false;
$error = '';
$tables = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    if (!$conn || $conn->connect_error) {
        $error = 'Sin conexión a BD: ' . ($conn->connect_error ?? '');
    } else {
        try {
            cw_hub_migrate($conn);
            $ran = true;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

if ($conn && !$conn->connect_error) {
    $list = [
        'cw_analytics_sessions',
        'cw_analytics_pageviews',
        'cw_analytics_events',
        'cw_analytics_admin_session_views',
    ];
    foreach ($list as $t) {
        $r = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($t) . "'");
        $tables[$t] = $r && $r->num_rows > 0;
    }
}

include dirname(__DIR__) . '/menu.php';
?>
<div class="adm-page-shell">
    <div class="container-fluid px-0">
        <?php
        $admPageTitle = 'Migración base de datos — CW Hub';
        $admPageSubtitle = 'Crea o actualiza tablas y columnas de analytics, sesiones y leads web';
        $admPageIcon = 'bi bi-database-gear';
        include dirname(__DIR__) . '/includes/adm_page_header.php';
        ?>
        <div class="row">
            <div class="col-lg-8">
                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php elseif ($ran): ?>
                    <div class="alert alert-success">Migración ejecutada correctamente.</div>
                <?php endif; ?>

                <table class="table table-sm table-bordered bg-white">
                    <thead><tr><th>Tabla</th><th>Estado</th></tr></thead>
                    <tbody>
                    <?php foreach ($tables as $name => $exists): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($name) ?></code></td>
                            <td><?= $exists ? '<span class="text-success">OK</span>' : '<span class="text-warning">Falta</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <form method="post" class="mt-3" onsubmit="return confirm('¿Ejecutar migración en producción?');">
                    <input type="hidden" name="confirm" value="1">
                    <button type="submit" class="btn btn-primary">Ejecutar migración</button>
                    <a href="deploy_check.php" class="btn btn-outline-secondary ms-2">Verificar deploy</a>
                </form>

                <p class="text-muted small mt-4">Alternativa CLI: <code>php scripts/run_hub_migrate.php</code></p>
            </div>
        </div>
    </div>
</div>
</body></html>
