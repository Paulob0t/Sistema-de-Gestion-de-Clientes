<?php
require_once dirname(__DIR__) . '/auth_middleware.php';
require_once dirname(__DIR__) . '/conn.php';
require_once dirname(__DIR__) . '/includes/cw_hub_config.php';
require_once dirname(__DIR__) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__) . '/includes/cw_hub_permissions.php';
require_once dirname(__DIR__) . '/includes/cw_seo_mexico_kpis.php';
require_once dirname(__DIR__) . '/includes/adm_paths.php';

cw_hub_migrate($conn);
cw_hub_require('hub.analytics.view');

$period = $_GET['period'] ?? '90d';
$from = $_GET['from'] ?? null;
$to = $_GET['to'] ?? null;
$plazaFilter = isset($_GET['plaza']) ? (string) $_GET['plaza'] : null;
$categoriaFilter = isset($_GET['categoria']) ? (string) $_GET['categoria'] : null;
[$dateFrom, $dateTo] = cw_hub_period_dates($period, $from, $to);
$snap = cw_seo_mexico_kpis_urls_snapshot($conn, $dateFrom, $dateTo, $plazaFilter, $categoriaFilter);
$totals = $snap['totals'];
$inds = [];
foreach ($snap['indicators'] as $ind) {
    $inds[$ind['key']] = $ind;
}
$plazas = cw_seo_mexico_priority_plazas();
$categories = $snap['categories'] ?? cw_site_kpi_categories();
$catCounts = $snap['category_counts'] ?? [];

/**
 * @param array{key:string,label:string,short:string,about:string,how:string,icon:string} $ind
 */
function seo_url_kpi_info_block(array $ind): string
{
    $label = htmlspecialchars((string) $ind['label']);
    $about = htmlspecialchars((string) $ind['about']);
    $how = htmlspecialchars((string) $ind['how']);
    return '<details class="seo-kpi-info">'
        . '<summary><i class="bi bi-info-circle"></i> ¿De qué va?</summary>'
        . '<div class="seo-kpi-info-body">'
        . '<p><strong>' . $label . '</strong> — ' . $about . '</p>'
        . '<p class="seo-kpi-info-how">' . $how . '</p>'
        . '</div></details>';
}

include dirname(__DIR__) . '/menu.php';
?>
<link href="<?= adm_href('analytics/css/seo-mexico-kpis.css') ?>?v=6" rel="stylesheet">
<div class="adm-page-shell">
<div class="container-fluid px-0">
<?php
$websiteTab = 'seo_urls';
$websiteHeroTitle = 'URLs';
$websiteHeroSub = 'Rendimiento de toda la web · categorizadas (blog, México, servicios, contacto…)';
$websiteShowPeriod = true;
require dirname(__DIR__) . '/includes/website_module_shell.php';
?>

<p class="seo-kpi-note">
  <strong>Cómo usarlo:</strong> aquí están las URLs del sitio (no solo México): blog, servicios, contacto, hubs, etc.
  Usa el filtro de categoría para enfocarte. Cada fila dice de qué va la página.
</p>

<section class="va-kpi-grid va-kpi-grid-4" aria-label="Totales por URL">
  <?php
  $cards = [
      ['key' => 'visitas', 'class' => 'lw-kpi-visitas', 'value' => (int) $totals['visitas'], 'suffix' => '', 'click' => null],
      ['key' => 'leads', 'class' => 'lw-kpi-lead', 'value' => (int) $totals['leads'], 'suffix' => '', 'click' => 'leads'],
      ['key' => 'calificados', 'class' => 'lw-kpi-calificado', 'value' => (int) $totals['calificados'], 'suffix' => '', 'click' => 'calificados'],
      ['key' => 'tasa_lead', 'class' => 'lw-kpi-cierre', 'value' => (float) $totals['tasa_lead'], 'suffix' => '%', 'click' => null],
  ];
  $kpiLeadAttrs = [];
  if (!empty($snap['plaza_filter'])) {
      $kpiLeadAttrs['data-plaza'] = (string) $snap['plaza_filter'];
  }
  if (!empty($snap['categoria_filter'])) {
      $kpiLeadAttrs['data-categoria'] = (string) $snap['categoria_filter'];
  }
  foreach ($cards as $card):
      $ind = $inds[$card['key']];
      if ($card['click']) {
          $scope = !empty($snap['plaza_filter']) ? 'plaza' : 'site';
          $numHtml = cw_seo_mexico_kpi_lead_btn_html((int) $card['value'], (string) $card['click'], $scope, $kpiLeadAttrs);
      } else {
          $numHtml = htmlspecialchars($card['suffix'] === '%'
              ? number_format((float) $card['value'], 2) . '%'
              : number_format((int) $card['value']));
      }
  ?>
  <article class="va-kpi <?= htmlspecialchars($card['class']) ?>" data-kpi="<?= htmlspecialchars($card['key']) ?>">
    <div class="va-kpi-head">
      <span class="title"><?= htmlspecialchars($ind['label']) ?></span>
      <span class="icon"><i class="bi <?= htmlspecialchars($ind['icon']) ?>"></i></span>
    </div>
    <div class="num" data-kpi-num="<?= htmlspecialchars($card['key']) ?>"><?= $numHtml ?></div>
    <div class="va-kpi-sub"><?= htmlspecialchars($ind['short']) ?><?= $card['click'] ? ' · clic para ver registros' : '' ?></div>
    <?= seo_url_kpi_info_block($ind) ?>
  </article>
  <?php endforeach; ?>
</section>

<details class="seo-kpi-guide">
  <summary><i class="bi bi-info-circle"></i> Guía de indicadores (de qué va cada columna)</summary>
  <div class="seo-kpi-guide-grid">
    <?php foreach ($snap['indicators'] as $ind): ?>
    <div class="seo-kpi-guide-item">
      <strong><i class="bi <?= htmlspecialchars($ind['icon']) ?>"></i> <?= htmlspecialchars($ind['label']) ?></strong>
      <p><?= htmlspecialchars($ind['about']) ?></p>
      <p style="margin-top:.35rem"><?= htmlspecialchars($ind['how']) ?></p>
    </div>
    <?php endforeach; ?>
  </div>
</details>

<article class="va-panel">
  <div class="va-panel-head">
    <h2><i class="bi bi-link-45deg mr-1"></i> Rendimiento por URL</h2>
    <div class="va-panel-actions adm-actions">
      <a class="adm-act" href="<?= adm_href('analytics/seo_mexico_plazas.php') . '?period=' . urlencode($period) ?>"><i class="bi bi-geo-alt"></i> Plazas</a>
      <a class="adm-act" href="<?= adm_href('analytics/seo_mexico_checklist.php') ?>"><i class="bi bi-code-slash"></i> Código / sitio</a>
      <a class="adm-act" href="<?= adm_href('analytics/seo_mexico_external.php') ?>"><i class="bi bi-box-arrow-up-right"></i> Externas</a>
    </div>
  </div>
  <div class="va-panel-body">
    <form method="get" class="seo-filter-row">
      <input type="hidden" name="period" value="<?= htmlspecialchars($period) ?>">
      <?php if ($period === 'custom'): ?>
      <input type="hidden" name="from" value="<?= htmlspecialchars((string) $from) ?>">
      <input type="hidden" name="to" value="<?= htmlspecialchars((string) $to) ?>">
      <?php endif; ?>

      <label for="categoriaFilter">Categoría</label>
      <select name="categoria" id="categoriaFilter" class="va-select" onchange="this.form.submit()" aria-label="Filtrar por categoría">
        <option value="">Todas las categorías</option>
        <?php foreach ($categories as $ck => $clabel):
            $cnt = (int) ($catCounts[$ck] ?? 0);
            $selected = ($snap['categoria_filter'] ?? '') === $ck ? 'selected' : '';
        ?>
        <option value="<?= htmlspecialchars($ck) ?>" <?= $selected ?>>
          <?= htmlspecialchars($clabel) ?><?= $cnt > 0 ? ' (' . $cnt . ')' : '' ?>
        </option>
        <?php endforeach; ?>
      </select>

      <label for="plazaFilter">Plaza</label>
      <select name="plaza" id="plazaFilter" class="va-select" onchange="this.form.submit()" aria-label="Filtrar por plaza">
        <option value="">Todas las plazas</option>
        <?php foreach ($plazas as $p): ?>
        <option value="<?= htmlspecialchars((string) $p['slug']) ?>" <?= ($snap['plaza_filter'] ?? '') === $p['slug'] ? 'selected' : '' ?>>
          <?= htmlspecialchars((string) $p['city']) ?>
        </option>
        <?php endforeach; ?>
      </select>

      <span class="text-muted small">
        <?= (int) ($totals['urls_total'] ?? count($snap['urls'])) ?> URLs ·
        <?= (int) $totals['urls_con_trafico'] ?> con tráfico ·
        <?= (int) $totals['urls_con_leads'] ?> con leads
      </span>
    </form>
  </div>
  <div class="va-panel-body p-0">
    <div class="modern-table va-table-wrap">
      <table class="table va-table w-100" id="urlsKpiTable">
        <thead>
          <tr>
            <th>URL</th>
            <th>Categoría</th>
            <th>De qué va</th>
            <th class="seo-kpi-num">Visitas</th>
            <th class="seo-kpi-num">Sesiones</th>
            <th class="seo-kpi-num">Leads</th>
            <th class="seo-kpi-num">Calificados</th>
            <th class="seo-kpi-num">Cerrados</th>
            <th class="seo-kpi-num">Tasa lead</th>
            <th class="seo-kpi-num">Visitantes locales</th>
            <th class="seo-kpi-num">Semáforo</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($snap['urls'] as $row):
            $semClass = 'seo-sem-' . preg_replace('/[^a-z]/', '', (string) $row['semaforo']);
            $pathShow = ((string) $row['path'] === '/') ? '/' : (string) $row['path'];
        ?>
          <tr>
            <td>
              <a class="seo-url-link" href="<?= htmlspecialchars((string) $row['url']) ?>" target="_blank" rel="noopener">
                <?= htmlspecialchars($pathShow) ?>
              </a>
              <?php if (!empty($row['plaza'])): ?>
              <span class="seo-url-meta"><?= htmlspecialchars((string) $row['plaza']) ?></span>
              <?php endif; ?>
            </td>
            <td>
              <span class="seo-url-meta" style="font-weight:700;color:#334155">
                <?= htmlspecialchars((string) ($row['categoria_label'] ?? $row['tipo'] ?? '')) ?>
              </span>
            </td>
            <td>
              <div class="seo-url-about">
                <strong><?= htmlspecialchars((string) $row['label']) ?></strong>
                <?= htmlspecialchars((string) $row['about']) ?>
              </div>
            </td>
            <td class="seo-kpi-num"><?= (int) $row['visitas'] ?></td>
            <td class="seo-kpi-num"><?= (int) $row['sesiones'] ?></td>
            <td class="seo-kpi-num"><?= cw_seo_mexico_kpi_lead_btn_html((int) $row['leads'], 'leads', 'path', ['data-path' => (string) $row['path']]) ?></td>
            <td class="seo-kpi-num"><?= cw_seo_mexico_kpi_lead_btn_html((int) $row['calificados'], 'calificados', 'path', ['data-path' => (string) $row['path']]) ?></td>
            <td class="seo-kpi-num"><?= cw_seo_mexico_kpi_lead_btn_html((int) $row['cerrados'], 'cerrados', 'path', ['data-path' => (string) $row['path']]) ?></td>
            <td class="seo-kpi-num"><?= number_format((float) $row['tasa_lead'], 2) ?>%</td>
            <td class="seo-kpi-num"><?= (int) $row['geo_local'] ?></td>
            <td class="seo-kpi-num">
              <span class="seo-sem <?= htmlspecialchars($semClass) ?>">
                <span class="seo-sem-dot" aria-hidden="true"></span>
                <?= htmlspecialchars((string) $row['semaforo_label']) ?>
              </span>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</article>

<p class="text-muted small mt-3 mb-0" id="seoUrlsUpdated">
  Actualizado: <?= htmlspecialchars((string) $snap['generated_at']) ?> (hora CDMX)
</p>

</div>
</div>
</div>
</div>
<?php require __DIR__ . '/include_seo_leads_modal.php'; ?>
<script src="<?= adm_href('analytics/js/seo-leads-modal.js') ?>?v=5"></script>
<script>
(function () {
  var period = <?= json_encode($period, JSON_UNESCAPED_UNICODE) ?>;
  var from = <?= json_encode($from, JSON_UNESCAPED_UNICODE) ?>;
  var to = <?= json_encode($to, JSON_UNESCAPED_UNICODE) ?>;
  var plaza = <?= json_encode($snap['plaza_filter'], JSON_UNESCAPED_UNICODE) ?>;
  var categoria = <?= json_encode($snap['categoria_filter'], JSON_UNESCAPED_UNICODE) ?>;
  var leadsApi = <?= json_encode(adm_url('analytics/api/seo_leads_list.php'), JSON_UNESCAPED_UNICODE) ?>;

  if (window.SeoLeadsModal) {
    window.SeoLeadsModal.init({
      apiUrl: leadsApi,
      period: period,
      from: from,
      to: to,
      plaza: plaza,
      categoria: categoria
    });
  } else {
    console.error('SeoLeadsModal no cargó. Revisa analytics/js/seo-leads-modal.js');
  }
})();
</script>
<script src="<?= adm_href('analytics/js/datatables-es.js') ?>"></script>
<script src="<?= adm_href('vendor/datatables/jquery.dataTables.min.js') ?>"></script>
<script src="<?= adm_href('vendor/datatables/dataTables.bootstrap4.min.js') ?>"></script>
<script>
(function () {
  var period = <?= json_encode($period, JSON_UNESCAPED_UNICODE) ?>;
  var from = <?= json_encode($from, JSON_UNESCAPED_UNICODE) ?>;
  var to = <?= json_encode($to, JSON_UNESCAPED_UNICODE) ?>;
  var plaza = <?= json_encode($snap['plaza_filter'], JSON_UNESCAPED_UNICODE) ?>;
  var categoria = <?= json_encode($snap['categoria_filter'], JSON_UNESCAPED_UNICODE) ?>;
  var apiUrl = <?= json_encode(adm_url('analytics/api/seo_urls_stats.php'), JSON_UNESCAPED_UNICODE) ?>;

  $('#urlsKpiTable').DataTable({
    scrollX: true,
    order: [[5, 'desc']],
    pageLength: 50,
    language: window.VA_DT_LANG_ES || {}
  });

  function fmtInt(n) { return Number(n || 0).toLocaleString('es-MX'); }
  function fmtPct(n) { return Number(n || 0).toFixed(2) + '%';

  function paintLeadBtn(wrap, value) {
    if (!wrap) return;
    var btn = wrap.querySelector('[data-seo-leads]');
    var n = Number(value || 0);
    if (btn) {
      btn.textContent = fmtInt(n);
      btn.setAttribute('data-count', String(n));
      btn.removeAttribute('disabled');
      btn.classList.toggle('is-zero', n <= 0);
      return;
    }
    wrap.textContent = fmtInt(n);
  }

  function paint(data) {
    if (!data || !data.totals) return;
    var t = data.totals;
    var visitasEl = document.querySelector('[data-kpi-num="visitas"]');
    var tasaEl = document.querySelector('[data-kpi-num="tasa_lead"]');
    if (visitasEl) visitasEl.textContent = fmtInt(t.visitas);
    paintLeadBtn(document.querySelector('[data-kpi-num="leads"]'), t.leads);
    paintLeadBtn(document.querySelector('[data-kpi-num="calificados"]'), t.calificados);
    if (tasaEl) tasaEl.textContent = fmtPct(t.tasa_lead);
    var upd = document.getElementById('seoUrlsUpdated');
    if (upd && data.generated_at) {
      upd.textContent = 'Actualizado: ' + data.generated_at + ' (hora CDMX)';
    }
    var live = document.getElementById('liveUpdated');
    if (live && data.generated_at) {
      var parts = String(data.generated_at).split(' ');
      live.textContent = parts[1] || data.generated_at;
    }
  }

  function refresh() {
    var qs = '?period=' + encodeURIComponent(period || '90d');
    if (period === 'custom' && from && to) {
      qs += '&from=' + encodeURIComponent(from) + '&to=' + encodeURIComponent(to);
    }
    if (plaza) qs += '&plaza=' + encodeURIComponent(plaza);
    if (categoria) qs += '&categoria=' + encodeURIComponent(categoria);
    fetch(apiUrl + qs, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j && j.success) paint(j.data);
      })
      .catch(function () {});
  }

  paint({ totals: <?= json_encode($totals, JSON_UNESCAPED_UNICODE) ?>, generated_at: <?= json_encode($snap['generated_at'], JSON_UNESCAPED_UNICODE) ?> });
  setInterval(refresh, 45000);
})();
</script>
</body></html>
