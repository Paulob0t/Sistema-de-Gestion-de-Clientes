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

$pages = cw_analytics_top_pages($conn, $dateFrom, $dateTo, 100);
$visits = cw_analytics_visits_overview($conn, $dateFrom, $dateTo);
$totalPv = max(1, (int) $visits['pageviews']);
$topTiers = [
    5 => cw_analytics_top_pages_tier($pages, 5),
    10 => cw_analytics_top_pages_tier($pages, 10),
    20 => cw_analytics_top_pages_tier($pages, 20),
];
$activeTab = 'pages';

include dirname(__DIR__) . '/menu.php';
?>
<link href="<?= adm_href('vendor/datatables/dataTables.bootstrap4.min.css') ?>" rel="stylesheet">
<div class="adm-page-shell">
<div class="container-fluid px-0">
<?php include __DIR__ . '/includes/visits_shell.php'; ?>

<section class="va-kpi-grid va-kpi-grid-4" aria-label="Totales del periodo">
  <article class="va-kpi">
    <div class="va-kpi-head"><span class="title">Páginas indexadas</span><span class="icon"><i class="bi bi-files"></i></span></div>
    <div class="num"><?= number_format(count($pages)) ?></div>
  </article>
  <article class="va-kpi">
    <div class="va-kpi-head"><span class="title">Visitas totales</span><span class="icon"><i class="bi bi-eye"></i></span></div>
    <div class="num"><?= number_format($visits['pageviews']) ?></div>
  </article>
  <article class="va-kpi">
    <div class="va-kpi-head"><span class="title">Usuarios únicos</span><span class="icon"><i class="bi bi-people"></i></span></div>
    <div class="num"><?= number_format($visits['users']) ?></div>
  </article>
  <article class="va-kpi">
    <div class="va-kpi-head"><span class="title">Duración prom.</span><span class="icon"><i class="bi bi-clock"></i></span></div>
    <div class="num"><?= cw_format_duration($visits['avgSec']) ?></div>
  </article>
</section>

<section class="va-top-tiers" aria-label="Top URLs más visitadas">
  <div class="va-top-tiers-head">
    <h2>Top URLs por volumen de tráfico</h2>
    <p class="text-muted small mb-0">Ranking por cantidad de vistas. Haz clic en una tarjeta para ver cada URL con sus visitas y tiempo promedio por vista.</p>
  </div>
  <div class="va-top-cards">
    <?php foreach ($topTiers as $limit => $tier):
      $share = round(($tier['visitas'] / $totalPv) * 100, 1);
      $urlLabel = $tier['count'] === 1 ? 'URL visitada' : 'URLs visitadas';
    ?>
    <button type="button"
            class="va-top-card"
            data-top="<?= (int) $limit ?>"
            aria-expanded="false"
            aria-controls="topPagesModal">
      <span class="va-top-card-label">Top <?= (int) $limit ?> · mayor tráfico</span>
      <span class="va-top-card-num"><?= number_format($tier['count']) ?></span>
      <span class="va-top-card-unit"><?= $urlLabel ?></span>
      <span class="va-top-card-meta"><i class="bi bi-eye"></i> <?= number_format($tier['visitas']) ?> vistas en total</span>
      <?php if (!empty($tier['topPath'])): ?>
      <span class="va-top-card-leader">
        <i class="bi bi-trophy"></i>
        <code><?= htmlspecialchars(mb_substr($tier['topPath'], 0, 42)) ?></code>
        · <?= number_format($tier['topVisits']) ?> vistas
      </span>
      <?php endif; ?>
      <span class="va-top-card-share"><?= $share ?>% del tráfico del periodo</span>
      <span class="va-top-card-action"><i class="fas fa-eye"></i>Ver detalle</span>
    </button>
    <?php endforeach; ?>
  </div>
</section>

<article class="va-panel">
  <div class="va-panel-head">
    <h2>Ranking de páginas</h2>
    <span class="text-muted small"><?= htmlspecialchars(substr(cw_hub_period_dates_mx($period, $from, $to)[0], 0, 10)) ?> — <?= htmlspecialchars(substr(cw_hub_period_dates_mx($period, $from, $to)[1], 0, 10)) ?></span>
  </div>
  <div class="va-panel-body p-0">
    <div class="modern-table va-table-wrap">
    <?php if (empty($pages)): ?>
    <div class="va-empty">Sin visitas registradas en este periodo.</div>
    <?php else: ?>
    <table class="table va-table" id="pagesTable">
      <thead>
        <tr>
          <th>Ruta</th>
          <th>Título</th>
          <th>Visitas</th>
          <th>Usuarios</th>
          <th>Tiempo prom. por vista</th>
          <th>Participación</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($pages as $p):
        $vis = (int) $p['visitas'];
        $share = round(($vis / $totalPv) * 100, 2);
      ?>
      <tr>
        <td><code><?= htmlspecialchars($p['path']) ?></code></td>
        <td><?= htmlspecialchars($p['title'] ?? '') ?></td>
        <td data-order="<?= $vis ?>"><strong><?= number_format($vis) ?></strong></td>
        <td><?= number_format((int) $p['usuarios']) ?></td>
        <td><?= cw_format_duration((int) ($p['tiempo_prom'] ?? 0)) ?></td>
        <td data-order="<?= $share ?>">
          <div class="d-flex align-items-center">
            <div class="va-share-bar flex-grow-1 mr-2"><span style="width:<?= min(100, $share) ?>%"></span></div>
            <small><?= $share ?>%</small>
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
  </div>
</article>

</div><!-- .va-shell -->
</div><!-- .container-fluid px-0 -->
</div><!-- .adm-page-shell -->
</div><!-- menu container-fluid -->
</div><!-- menu content-wrapper -->

<div class="va-top-modal" id="topPagesModal" hidden aria-hidden="true">
  <div class="va-top-modal-backdrop" data-close-modal></div>
  <div class="va-top-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="topPagesDetailTitle">
    <div class="va-top-detail-head">
      <div>
        <h3 id="topPagesDetailTitle">Top 5 URLs por tráfico</h3>
        <p class="va-top-modal-sub" id="topPagesDetailSub">Visitas individuales y tiempo promedio por vista de cada ruta.</p>
      </div>
      <button type="button" class="va-top-detail-close" aria-label="Cerrar detalle">&times;</button>
    </div>
    <div class="va-top-modal-body">
      <?php foreach ($topTiers as $limit => $tier): ?>
      <div class="va-top-detail-panel" data-top-panel="<?= (int) $limit ?>" hidden>
        <?= va_render_top_pages_detail_list($tier['pages'], $totalPv) ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="va-top-modal" id="pageUsersModal" hidden aria-hidden="true">
  <div class="va-top-modal-backdrop" data-close-page-modal></div>
  <div class="va-top-modal-dialog va-page-users-dialog" role="dialog" aria-modal="true" aria-labelledby="pageUsersModalTitle">
    <div class="va-top-detail-head">
      <div>
        <h3 id="pageUsersModalTitle">Usuarios en página</h3>
        <p class="va-top-modal-sub" id="pageUsersModalSub">URL, origen, geolocalización, hora y tiempo por cada visita.</p>
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

<script src="<?= adm_href('analytics/js/datatables-es.js') ?>"></script>
<script src="<?= adm_href('vendor/datatables/jquery.dataTables.min.js') ?>"></script>
<script src="<?= adm_href('vendor/datatables/dataTables.bootstrap4.min.js') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var cards = document.querySelectorAll('.va-top-card');
  var modal = document.getElementById('topPagesModal');
  var title = document.getElementById('topPagesDetailTitle');
  var sub = document.getElementById('topPagesDetailSub');
  var panels = document.querySelectorAll('.va-top-detail-panel');
  var closeBtn = modal ? modal.querySelector('.va-top-detail-close') : null;
  var backdrop = modal ? modal.querySelector('[data-close-modal]') : null;

  if (!cards.length || !modal) return;

  function showTier(limit) {
    cards.forEach(function (card) {
      var isActive = card.getAttribute('data-top') === String(limit);
      card.classList.toggle('is-active', isActive);
      card.setAttribute('aria-expanded', isActive ? 'true' : 'false');
    });
    panels.forEach(function (panel) {
      panel.hidden = panel.getAttribute('data-top-panel') !== String(limit);
    });
    if (title) title.textContent = 'Top ' + limit + ' URLs por tráfico';
    if (sub) sub.textContent = limit + ' rutas con mayor volumen de vistas · visitas individuales y tiempo promedio por vista.';
    modal.removeAttribute('hidden');
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('va-top-modal-open');
  }

  function hideDetail() {
    modal.setAttribute('hidden', 'hidden');
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('va-top-modal-open');
    cards.forEach(function (card) {
      card.classList.remove('is-active');
      card.setAttribute('aria-expanded', 'false');
    });
  }

  cards.forEach(function (card) {
    card.addEventListener('click', function () {
      showTier(card.getAttribute('data-top'));
    });
  });

  if (closeBtn) closeBtn.addEventListener('click', hideDetail);
  if (backdrop) backdrop.addEventListener('click', hideDetail);

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal.classList.contains('is-open')) hideDetail();
  });
});
</script>
<script src="<?= adm_href('analytics/js/page-users-modal.js') ?>"></script>
<script>
VAPageUsersModal.init({ period: <?= json_encode($period) ?>, web: <?= json_encode($websiteWebFilter ?? 'all') ?> });
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var table = document.getElementById('pagesTable');
  if (!table || typeof window.jQuery === 'undefined' || !jQuery.fn.DataTable) return;
  try {
    jQuery('#pagesTable').DataTable({
      scrollX: true,
      order: [[2, 'desc']],
      pageLength: 25,
      language: window.VA_DT_LANG_ES || {}
    });
  } catch (e) {
    console.warn('DataTables no disponible:', e);
  }
});
</script>
</body></html>
