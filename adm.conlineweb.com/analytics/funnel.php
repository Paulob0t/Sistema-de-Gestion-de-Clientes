<?php
require_once dirname(__DIR__) . '/auth_middleware.php';
require_once dirname(__DIR__) . '/conn.php';
require_once dirname(__DIR__) . '/includes/cw_hub_config.php';
require_once dirname(__DIR__) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__) . '/includes/cw_hub_analytics.php';
require_once dirname(__DIR__) . '/includes/cw_hub_permissions.php';

cw_hub_migrate($conn);
cw_hub_require('hub.analytics.view');

require_once dirname(__DIR__) . '/includes/adm_paths.php';

$period = $_GET['period'] ?? '30d';
$from = $_GET['from'] ?? null;
$to = $_GET['to'] ?? null;
$websiteWebFilter = cw_analytics_normalize_web($_GET['web'] ?? 'all');
cw_analytics_web_filter($websiteWebFilter);
[$dateFrom, $dateTo] = cw_hub_period_dates($period, $from, $to);

$funnel = cw_analytics_funnel($conn, $dateFrom, $dateTo, 40);
$totals = cw_analytics_funnel_totals($funnel);

include dirname(__DIR__) . '/menu.php';
?>
<link href="<?= adm_href('vendor/datatables/dataTables.bootstrap4.min.css') ?>" rel="stylesheet">
<div class="adm-page-shell">
<div class="container-fluid px-0">
<?php
$websiteTab = 'funnel';
$websiteHeroTitle = 'Funnel comercial';
$websiteHeroSub = 'Visitas → leads → calificados → cierre por página · .com /us · .cl';
$websiteShowPeriod = true;
require dirname(__DIR__) . '/includes/website_module_shell.php';
?>

<section class="va-kpi-grid va-kpi-grid-4" aria-label="Totales del funnel">
  <article class="va-kpi lw-kpi-visitas">
    <div class="va-kpi-head"><span class="title">Visitas</span><span class="icon"><i class="bi bi-eye"></i></span></div>
    <div class="num"><?= number_format($totals['visitas']) ?></div>
  </article>
  <article class="va-kpi lw-kpi-lead">
    <div class="va-kpi-head"><span class="title">Leads</span><span class="icon"><i class="bi bi-person-plus"></i></span></div>
    <div class="num"><?= number_format($totals['leads']) ?></div>
  </article>
  <article class="va-kpi lw-kpi-calificado">
    <div class="va-kpi-head"><span class="title">Calificados</span><span class="icon"><i class="bi bi-stars"></i></span></div>
    <div class="num"><?= number_format($totals['calificados']) ?></div>
  </article>
  <article class="va-kpi lw-kpi-cierre">
    <div class="va-kpi-head"><span class="title">Cerrados</span><span class="icon"><i class="bi bi-check2-circle"></i></span></div>
    <div class="num"><?= number_format($totals['cerrados']) ?></div>
  </article>
</section>

<article class="va-panel">
  <div class="va-panel-head">
    <h2><i class="bi bi-funnel mr-1"></i> Tráfico × Leads × Conversión</h2>
    <?php if (defined('ADM_DEV_LOCAL') && ADM_DEV_LOCAL || cw_hub_can('hub.analytics.export')): ?>
    <div class="va-panel-actions adm-actions">
      <a href="<?= adm_href('analytics/reportes.php') ?>?export=funnel&format=xls&period=<?= urlencode($period) ?><?= ($websiteWebFilter ?? 'all') !== 'all' ? '&web=' . urlencode($websiteWebFilter) : '' ?>" class="adm-act adm-act--success"><i class="fas fa-file-excel"></i>Excel</a>
      <a href="<?= adm_href('analytics/reportes.php') ?>?export=overview&format=pdf&period=<?= urlencode($period) ?><?= ($websiteWebFilter ?? 'all') !== 'all' ? '&web=' . urlencode($websiteWebFilter) : '' ?>" class="adm-act adm-act--danger"><i class="fas fa-file-pdf"></i>PDF</a>
    </div>
    <?php endif; ?>
  </div>
  <div class="va-panel-body p-0">
    <div class="modern-table va-table-wrap">
    <table class="table va-table w-100" id="funnelTable">
      <thead>
        <tr>
          <th>Página</th>
          <th>Visitas</th>
          <th class="fn-th-lead">Leads</th>
          <th class="fn-th-calificado">Calificados</th>
          <th class="fn-th-cierre">Cerrados</th>
          <th>Tasa lead</th>
          <th>Tasa cierre</th>
          <th>Funnel visual</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($funnel as $row):
        $pct = min(100, (float) $row['tasa_lead']);
      ?>
      <tr>
        <td><code><?= htmlspecialchars($row['path']) ?></code><br><small class="text-muted"><?= htmlspecialchars(mb_substr($row['title']??'',0,50)) ?></small></td>
        <td><?= (int)$row['visitas'] ?></td>
        <td class="fn-col-lead"><strong><?= (int)$row['leads'] ?></strong></td>
        <td class="fn-col-calificado"><?= (int)$row['calificados'] ?></td>
        <td class="fn-col-cierre"><?= (int)$row['cerrados'] ?></td>
        <td><span class="fn-rate"><?= $row['tasa_lead'] ?>%</span></td>
        <td><span class="fn-rate fn-rate-cierre"><?= $row['tasa_cierre'] ?>%</span></td>
        <td style="min-width:120px"><div class="fn-bar"><span style="width:<?= $pct ?>%"></span></div></td>
      </tr>
      <?php endforeach; if (empty($funnel)): ?>
      <tr><td colspan="8" class="text-center text-muted">Sin datos en este periodo para el sitio seleccionado (Todos / MX · .com / US · /us / CL · .cl).</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>
</article>

</div><!-- .va-shell -->
</div><!-- .container-fluid px-0 -->
</div><!-- .adm-page-shell -->
</div><!-- menu container-fluid -->
</div><!-- menu content-wrapper -->
<script src="<?= adm_href('analytics/js/datatables-es.js') ?>"></script>
<script src="<?= adm_href('vendor/datatables/jquery.dataTables.min.js') ?>"></script>
<script src="<?= adm_href('vendor/datatables/dataTables.bootstrap4.min.js') ?>"></script>
<script>$('#funnelTable').DataTable({ scrollX: true, order: [[2,'desc']], pageLength: 25, language: window.VA_DT_LANG_ES || {} });</script>
</body></html>
