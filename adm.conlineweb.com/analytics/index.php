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

$visits = cw_analytics_visits_overview($conn, $dateFrom, $dateTo);
$delta = cw_analytics_period_delta($conn, $dateFrom, $dateTo);
$daily = cw_analytics_daily($conn, $dateFrom, $dateTo);
$dailyUsers = cw_analytics_daily_users($conn, $dateFrom, $dateTo);
$topPages = cw_analytics_top_pages($conn, $dateFrom, $dateTo, 10);
$byDevice = cw_analytics_by_device($conn, $dateFrom, $dateTo, 6);
$byBrowser = cw_analytics_by_browser($conn, $dateFrom, $dateTo, 6);
$byUtm = cw_analytics_by_utm($conn, $dateFrom, $dateTo, 8);
$byReferrer = cw_analytics_top_referrers($conn, $dateFrom, $dateTo, 8);
$byCountry = cw_analytics_by_country($conn, $dateFrom, $dateTo, 8);

$labels = array_column($daily, 'd');
$pvValues = array_map('intval', array_column($daily, 'v'));
$userMap = [];
foreach ($dailyUsers as $du) {
    $userMap[$du['d']] = (int) $du['u'];
}
$userValues = array_map(static fn($d) => $userMap[$d] ?? 0, $labels);

$maxUtm = !empty($byUtm) ? (int) max(array_column($byUtm, 'c')) : 0;
$maxRef = !empty($byReferrer) ? (int) max(array_column($byReferrer, 'c')) : 0;
$maxCountry = !empty($byCountry) ? (int) max(array_column($byCountry, 'c')) : 0;
$totalPvPeriod = max(1, (int) $visits['pageviews']);

$activeTab = 'overview';

include dirname(__DIR__) . '/menu.php';
?>
<div class="adm-page-shell">
<div class="container-fluid px-0">
<?php include __DIR__ . '/includes/visits_shell.php'; ?>

<section class="va-live-strip" aria-label="Métricas en vivo de hoy">
  <article class="va-live-card">
    <div class="label">Visitas hoy</div>
    <div class="value" id="livePageviews">—</div>
    <div class="hint">Páginas vistas</div>
  </article>
  <article class="va-live-card">
    <div class="label">Usuarios hoy</div>
    <div class="value" id="liveUsers">—</div>
    <div class="hint">Visitantes únicos</div>
  </article>
  <article class="va-live-card">
    <div class="label">Activos ahora</div>
    <div class="value" id="liveActive">—</div>
    <div class="hint">Últimos 5 minutos</div>
  </article>
  <article class="va-live-card">
    <div class="label">Duración prom.</div>
    <div class="value" id="liveAvgTime">—</div>
    <div class="hint">Tiempo en página hoy</div>
  </article>
</section>

<section class="va-kpi-grid" aria-label="Resumen del periodo">
  <?php
  $kpis = [
    ['Páginas vistas', number_format($visits['pageviews']), 'bi-eye', $delta['pageviews']],
    ['Usuarios únicos', number_format($visits['users']), 'bi-people', $delta['users']],
    ['Sesiones', number_format($visits['sessions']), 'bi-diagram-3', $delta['sessions']],
    ['Duración prom.', cw_format_duration($visits['avgSec']), 'bi-clock-history', $delta['avgSec']],
    ['Páginas / sesión', number_format($visits['pagesPerSession'], 1), 'bi-layers', null],
    ['Prom. diario', $labels ? number_format(round($visits['pageviews'] / max(1, count($labels)))) : '0', 'bi-calendar3', null],
  ];
  foreach ($kpis as $kpi): ?>
  <article class="va-kpi">
    <div class="va-kpi-head">
      <span class="title"><?= htmlspecialchars($kpi[0]) ?></span>
      <span class="icon"><i class="bi <?= $kpi[2] ?>"></i></span>
    </div>
    <div class="num"><?= $kpi[1] ?></div>
    <?php if ($kpi[3] !== null): ?>
    <?= va_render_delta((float) $kpi[3]) ?> <span class="text-muted small ml-1">vs periodo anterior</span>
    <?php endif; ?>
  </article>
  <?php endforeach; ?>
</section>

<div class="va-grid-2">
  <article class="va-panel">
    <div class="va-panel-head"><h2>Tráfico diario</h2></div>
    <div class="va-panel-body"><canvas id="chartTraffic" height="110"></canvas></div>
  </article>
  <article class="va-panel">
    <div class="va-panel-head">
      <h2>Hoy por hora</h2>
      <span class="text-muted small">Hora Ciudad de México (CDMX)</span>
    </div>
    <div class="va-panel-body"><canvas id="chartLiveHourly" height="110"></canvas></div>
  </article>
</div>

<div class="va-grid-3">
  <article class="va-panel">
    <div class="va-panel-head"><h2>Dispositivos y navegadores</h2></div>
    <div class="va-panel-body">
      <canvas id="chartDevices" height="160"></canvas>
      <?php
      $maxBrowser = !empty($byBrowser) ? (int) max(array_column($byBrowser, 'c')) : 0;
      if (!empty($byBrowser)):
      ?>
      <div class="mt-3 pt-3 border-top">
        <div class="small text-uppercase font-weight-bold text-muted mb-2">Navegadores</div>
        <?= va_render_rank_list($byBrowser, $maxBrowser) ?>
      </div>
      <?php endif; ?>
    </div>
  </article>
  <article class="va-panel">
    <div class="va-panel-head"><h2>Origen geográfico</h2></div>
    <div class="va-panel-body"><?= va_render_rank_list($byCountry, $maxCountry) ?></div>
  </article>
  <article class="va-panel">
    <div class="va-panel-head"><h2>Referrers</h2></div>
    <div class="va-panel-body"><?= va_render_rank_list($byReferrer, $maxRef) ?></div>
  </article>
</div>

<div class="va-grid-2">
  <article class="va-panel">
    <div class="va-panel-head"><h2>Fuentes UTM</h2></div>
    <div class="va-panel-body"><?= va_render_rank_list($byUtm, $maxUtm) ?></div>
  </article>
  <article class="va-panel va-panel-note">
    <div class="va-panel-head"><h2>Cómo se detecta el origen</h2></div>
    <div class="va-panel-body">
      <p class="text-muted small mb-2"><i class="bi bi-globe-americas"></i> <strong>IP del visitante</strong> — país, estado y ciudad aproximada (producción).</p>
      <p class="text-muted small mb-2"><i class="bi bi-clock"></i> <strong>Zona horaria del navegador</strong> — respaldo sin pedir permiso (útil en local).</p>
      <p class="text-muted small mb-0"><i class="bi bi-translate"></i> <strong>Idioma del navegador</strong> — señal adicional para afinar el país.</p>
    </div>
  </article>
</div>

<article class="va-panel">
  <div class="va-panel-head">
    <h2>Top páginas visitadas</h2>
    <a href="<?= adm_href('analytics/pages.php') ?>?period=<?= urlencode($period) ?><?= ($websiteWebFilter ?? 'all') !== 'all' ? '&web=' . urlencode($websiteWebFilter) : '' ?>" class="adm-act adm-act--view"><i class="fas fa-list-ol"></i>Ver ranking</a>
  </div>
  <div class="va-table-wrap modern-table">
    <?php if (empty($topPages)): ?>
    <div class="va-empty">
      Sin visitas en este periodo.<br>
      <?php if (defined('ADM_DEV_LOCAL') && ADM_DEV_LOCAL): ?>
      Navega en <strong>localhost/proyecto/conlineweb.com</strong> para generar datos de prueba.
      <?php else: ?>
      El tráfico se registra desde <strong>conlineweb.com</strong>.
      <?php endif; ?>
    </div>
    <?php else: ?>
    <table class="table va-table" id="topPagesTable">
      <thead>
        <tr>
          <th>Página</th>
          <th>Visitas</th>
          <th>Usuarios</th>
          <th>Tiempo prom.</th>
          <th>Participación</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($topPages as $p):
        $vis = (int) $p['visitas'];
        $share = round(($vis / $totalPvPeriod) * 100, 1);
      ?>
      <tr>
        <td>
          <code><?= htmlspecialchars($p['path']) ?></code>
          <?php if (!empty($p['title'])): ?>
          <br><small class="text-muted"><?= htmlspecialchars(mb_substr($p['title'], 0, 70)) ?></small>
          <?php endif; ?>
        </td>
        <td data-order="<?= $vis ?>"><strong><?= number_format($vis) ?></strong></td>
        <td><?= number_format((int) $p['usuarios']) ?></td>
        <td data-order="<?= (int) ($p['tiempo_prom'] ?? 0) ?>"><?= cw_format_duration((int) ($p['tiempo_prom'] ?? 0)) ?></td>
        <td data-order="<?= $share ?>">
          <div class="d-flex align-items-center gap-2">
            <div class="va-share-bar flex-grow-1"><span style="width:<?= min(100, $share) ?>%"></span></div>
            <small class="text-muted"><?= $share ?>%</small>
          </div>
        </td>
        <td>
          <div class="adm-actions">
            <button type="button"
                    class="adm-act adm-act--view va-page-detail-btn"
                    data-path="<?= htmlspecialchars($p['path'], ENT_QUOTES) ?>"
                    data-title="<?= htmlspecialchars($p['title'] ?? $p['path'], ENT_QUOTES) ?>">
              <i class="fas fa-eye"></i>Ver detalle
            </button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</article>

<div class="va-top-modal" id="pageUsersModal" hidden aria-hidden="true">
  <div class="va-top-modal-backdrop" data-close-page-modal></div>
  <div class="va-top-modal-dialog va-page-users-dialog" role="dialog" aria-modal="true" aria-labelledby="pageUsersModalTitle">
    <div class="va-top-detail-head">
      <div>
        <h3 id="pageUsersModalTitle">Usuarios en página</h3>
        <p class="va-top-modal-sub" id="pageUsersModalSub">URL, origen, geolocalización, hora CDMX del servidor y tiempo por cada visita.</p>
      </div>
      <button type="button" class="va-top-detail-close" data-close-page-modal aria-label="Cerrar">&times;</button>
    </div>
    <div class="va-page-users-summary" id="pageUsersSummary"></div>
    <div class="va-top-modal-body va-page-users-body">
      <div class="va-page-users-loading" id="pageUsersLoading" hidden>
        <i class="bi bi-arrow-repeat spin"></i> Cargando visitas…
      </div>
      <div class="va-table-wrap">
        <table class="va-table va-top-detail-table" id="pageUsersTable" style="width:100%">
          <thead>
            <tr>
              <th>Visitante</th>
              <th>URL</th>
              <th>Referrer</th>
              <th>País</th>
              <th>Región / Ciudad</th>
              <th>Hora (CDMX)</th>
              <th>Tiempo</th>
              <th>Dispositivo</th>
              <th>Navegador</th>
              <th>Sesión</th>
            </tr>
          </thead>
          <tbody id="pageUsersTableBody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

</div><!-- .va-shell -->
</div><!-- .container-fluid px-0 -->
</div><!-- .adm-page-shell -->
</div><!-- menu container-fluid -->
</div><!-- menu content-wrapper -->

<script src="<?= adm_href('vendor/chart.js/Chart.min.js') ?>"></script>
<link href="<?= adm_href('vendor/datatables/dataTables.bootstrap4.min.css') ?>" rel="stylesheet">
<script src="<?= adm_href('analytics/js/datatables-es.js') ?>"></script>
<script src="<?= adm_href('vendor/datatables/jquery.dataTables.min.js') ?>"></script>
<script src="<?= adm_href('vendor/datatables/dataTables.bootstrap4.min.js') ?>"></script>
<script>
(function () {
  var brand = '#000147';
  var accent = '#ffc107';
  var success = '#10b981';
  var info = '#3b82f6';

  new Chart(document.getElementById('chartTraffic'), {
    type: 'line',
    data: {
      labels: <?= json_encode($labels) ?>,
      datasets: [
        {
          label: 'Páginas vistas',
          data: <?= json_encode($pvValues) ?>,
          borderColor: brand,
          backgroundColor: 'rgba(0,1,71,.07)',
          fill: true,
          tension: 0.35,
          pointRadius: 2
        },
        {
          label: 'Usuarios únicos',
          data: <?= json_encode($userValues) ?>,
          borderColor: success,
          backgroundColor: 'transparent',
          borderDash: [4, 3],
          tension: 0.35,
          pointRadius: 2
        }
      ]
    },
    options: {
      responsive: true,
      interaction: { mode: 'index', intersect: false },
      scales: { y: { beginAtZero: true } }
    }
  });

  var deviceLabels = <?= json_encode(array_column($byDevice, 'label')) ?>;
  var deviceValues = <?= json_encode(array_map('intval', array_column($byDevice, 'c'))) ?>;
  if (deviceLabels.length) {
    new Chart(document.getElementById('chartDevices'), {
      type: 'doughnut',
      data: {
        labels: deviceLabels,
        datasets: [{
          data: deviceValues,
          backgroundColor: [brand, success, info, accent, '#6366f1', '#94a3b8']
        }]
      },
      options: {
        responsive: true,
        legend: { position: 'bottom', labels: { boxWidth: 12, fontSize: 11 } }
      }
    });
  }
})();
</script>
<script src="<?= adm_href('analytics/js/page-users-modal.js') ?>"></script>
<script>
VAPageUsersModal.init({ period: <?= json_encode($period) ?>, web: <?= json_encode($websiteWebFilter ?? 'all') ?> });
</script>
<script src="<?= adm_href('analytics/js/visits-live.js') ?>"></script>
</body></html>
