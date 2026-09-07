<?php
require_once dirname(__DIR__) . '/auth_middleware.php';
require_once dirname(__DIR__) . '/conn.php';
require_once dirname(__DIR__) . '/includes/cw_hub_config.php';
require_once dirname(__DIR__) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__) . '/includes/cw_hub_analytics.php';
require_once dirname(__DIR__) . '/includes/cw_hub_permissions.php';
require_once dirname(__DIR__) . '/includes/cw_hub_reports.php';

cw_hub_migrate($conn);

$period = $_GET['period'] ?? '30d';
$websiteWebFilter = cw_analytics_normalize_web($_GET['web'] ?? 'all');
cw_analytics_web_filter($websiteWebFilter);
[$dateFrom, $dateTo] = cw_hub_period_dates($period);
$periodLabels = ['today'=>'Hoy','7d'=>'7 días','week'=>'Semana','30d'=>'30 días','month'=>'Mes','quarter'=>'Trimestre','year'=>'Año'];
$periodLabel = $periodLabels[$period] ?? $period;
$webQs = $websiteWebFilter !== 'all' ? '&web=' . urlencode($websiteWebFilter) : '';

if (isset($_GET['export'])) {
    $type = $_GET['export'];
    $format = $_GET['format'] ?? 'csv';

    if ($format === 'pdf') {
        cw_hub_require('hub.reports.pdf');
        cw_hub_report_pdf_overview($conn, $dateFrom, $dateTo, $periodLabel);
    }

    cw_hub_require('hub.analytics.export');

    if ($format === 'xls' && $type === 'funnel') {
        cw_hub_report_export_funnel_excel($conn, $dateFrom, $dateTo);
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reporte_' . $type . '_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

    if ($type === 'leads') {
        fputcsv($out, ['ID', 'Fecha', 'Nombre', 'Email', 'WhatsApp', 'Servicio', 'Página origen', 'Pipeline', 'Fuente', 'Responsable']);
        $leadScope = cw_analytics_lead_scope_sql();
        $q = $conn->prepare("SELECT l.id, l.fecha_registro, l.nombre, l.correo, l.telefono, l.servicio, l.pagina_origen, l.pipeline_estado, l.fuente, l.responsable_id
            FROM leads l WHERE l.eliminado=0 AND l.origen_web=1 AND l.fecha_registro BETWEEN ? AND ?{$leadScope} ORDER BY l.fecha_registro DESC");
        $q->bind_param('ss', $dateFrom, $dateTo);
        $q->execute();
        $r = $q->get_result();
        while ($row = $r->fetch_assoc()) {
            $row['responsable_id'] = cw_hub_responsable_nombre($conn, (int) ($row['responsable_id'] ?? 0));
            fputcsv($out, $row);
        }
    } elseif ($type === 'funnel') {
        fputcsv($out, ['Ruta', 'Título', 'Visitas', 'Usuarios', 'Leads', 'Calificados', 'Cerrados', 'Tasa lead', 'Tasa cierre']);
        foreach (cw_analytics_funnel($conn, $dateFrom, $dateTo, 500) as $p) {
            fputcsv($out, [$p['path'], $p['title'], $p['visitas'], $p['usuarios'], $p['leads'], $p['calificados'], $p['cerrados'], $p['tasa_lead'] . '%', $p['tasa_cierre'] . '%']);
        }
    } elseif ($type === 'pages') {
        fputcsv($out, ['Ruta', 'Título', 'Visitas', 'Usuarios', 'Tiempo prom (s)', 'Conversiones']);
        foreach (cw_analytics_top_pages($conn, $dateFrom, $dateTo, 500) as $p) {
            fputcsv($out, [$p['path'], $p['title'], $p['visitas'], $p['usuarios'], $p['tiempo_prom'], $p['conversiones']]);
        }
    } else {
        fputcsv($out, ['Fecha', 'Páginas vistas']);
        foreach (cw_analytics_daily($conn, $dateFrom, $dateTo) as $d) {
            fputcsv($out, [$d['d'], $d['v']]);
        }
    }
    fclose($out);
    exit;
}

cw_hub_require('hub.analytics.view');

require_once dirname(__DIR__) . '/includes/adm_paths.php';

$devMode = defined('ADM_DEV_LOCAL') && ADM_DEV_LOCAL;
$canExport = $devMode || cw_hub_can('hub.analytics.export');
$canPdf = $devMode || cw_hub_can('hub.reports.pdf');

include dirname(__DIR__) . '/menu.php';
?>
<div class="adm-page-shell">
<div class="container-fluid px-0">
<?php
$websiteTab = 'reportes';
$websiteHeroTitle = 'Exportar reportes';
$websiteHeroSub = 'Descarga CSV, Excel y PDF del tráfico y leads del sitio';
$websiteShowPeriod = true;
require dirname(__DIR__) . '/includes/website_module_shell.php';
?>

<div class="va-report-grid">
  <?php
  $reports = [
    ['traffic', 'Tráfico web', 'Páginas vistas por día', 'bi-graph-up', 'va-report-traffic'],
    ['pages', 'Páginas', 'Ranking URL con conversiones', 'bi-bar-chart', 'va-report-pages'],
    ['leads', 'Leads', 'Contactos captados desde la web', 'bi-person-lines-fill', 'va-report-leads'],
    ['funnel', 'Funnel comercial', 'Visitas → leads → cierre por URL', 'bi-funnel', 'va-report-funnel'],
  ];
  foreach ($reports as $r): ?>
  <article class="va-report-card <?= $r[4] ?>">
    <div class="va-report-icon"><i class="bi <?= $r[3] ?>"></i></div>
    <h3><?= htmlspecialchars($r[1]) ?></h3>
    <p><?= htmlspecialchars($r[2]) ?></p>
    <div class="va-report-actions adm-actions">
      <?php if ($canExport): ?>
      <a href="?export=<?= urlencode($r[0]) ?>&format=csv&period=<?= urlencode($period) . $webQs ?>" class="adm-act adm-act--view"><i class="fas fa-file-csv"></i>CSV</a>
      <?php if ($r[0] === 'funnel'): ?>
      <a href="?export=funnel&format=xls&period=<?= urlencode($period) . $webQs ?>" class="adm-act adm-act--success"><i class="fas fa-file-excel"></i>Excel</a>
      <?php endif; ?>
      <?php else: ?>
      <span class="va-report-locked"><i class="bi bi-lock"></i> Exportación restringida</span>
      <?php endif; ?>
    </div>
  </article>
  <?php endforeach; ?>
</div>
<?php if ($canPdf): ?>
<article class="va-panel va-report-pdf">
  <div class="va-panel-body d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div>
      <h2 class="h5 mb-1 font-weight-bold"><i class="bi bi-file-earmark-pdf text-danger mr-1"></i> Reporte ejecutivo PDF</h2>
      <p class="text-muted small mb-0">Resumen + funnel top 15 en un solo documento.</p>
    </div>
    <div class="adm-actions">
      <a href="?export=overview&format=pdf&period=<?= urlencode($period) . $webQs ?>" class="adm-act adm-act--danger"><i class="fas fa-download"></i>Descargar PDF</a>
    </div>
  </div>
</article>
<?php endif; ?>
<p class="va-footnote">Periodo seleccionado: <strong><?= htmlspecialchars($periodLabel) ?></strong><?php if ($websiteWebFilter !== 'all'): ?> · Sitio: <strong><?= htmlspecialchars(strtoupper($websiteWebFilter)) ?></strong><?php endif; ?>. Tipos 1–2: exportación completa.</p>
</div><!-- .va-shell -->
</div><!-- .container-fluid px-0 -->
</div><!-- .adm-page-shell -->
</div><!-- menu container-fluid -->
</div><!-- menu content-wrapper -->
</body></html>
