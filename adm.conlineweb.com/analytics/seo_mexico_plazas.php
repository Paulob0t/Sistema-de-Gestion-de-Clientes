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

$uid = (int) ($_SESSION['uid'] ?? 0);
cw_seo_mexico_checklist_apply_kpi_90d($conn, $uid);

$period = $_GET['period'] ?? '90d';
$from = $_GET['from'] ?? null;
$to = $_GET['to'] ?? null;
[$dateFrom, $dateTo] = cw_hub_period_dates($period, $from, $to);
$snap = cw_seo_mexico_kpis_snapshot($conn, $dateFrom, $dateTo);
$totals = $snap['totals'];
$inds = cw_seo_mexico_kpi_indicators_map();

/**
 * @param array{key:string,label:string,short:string,about:string,how:string,icon:string} $ind
 */
function seo_kpi_info_block(array $ind): string
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
$websiteTab = 'seo_plazas';
$websiteHeroTitle = 'Plazas';
$websiteHeroSub = 'KPIs en vivo por ciudad prioritaria · visitas, leads y semáforo del plan SEO México';
$websiteShowPeriod = true;
require dirname(__DIR__) . '/includes/website_module_shell.php';
?>

<p class="seo-kpi-note">
  <strong>Cómo leer este panel:</strong> cada indicador tiene “¿De qué va?” para explicar qué mide.
  Las metas de 90 días están abajo; el semáforo por plaza indica si ya genera contactos útiles.
  Impresiones/clics de Google Search Console se revisan aparte (mensual).
</p>

<section class="va-kpi-grid va-kpi-grid-4" aria-label="Totales geo">
  <?php
  $cards = [
      ['key' => 'visitas', 'class' => 'lw-kpi-visitas', 'value' => (int) $totals['visitas'], 'suffix' => '', 'click' => null],
      ['key' => 'leads', 'class' => 'lw-kpi-lead', 'value' => (int) $totals['leads'], 'suffix' => '', 'click' => 'leads'],
      ['key' => 'calificados', 'class' => 'lw-kpi-calificado', 'value' => (int) $totals['calificados'], 'suffix' => '', 'click' => 'calificados'],
      ['key' => 'tasa_lead', 'class' => 'lw-kpi-cierre', 'value' => (float) $totals['tasa_lead'], 'suffix' => '%', 'click' => null],
  ];
  foreach ($cards as $card):
      $ind = $inds[$card['key']];
      if ($card['click']) {
          $numHtml = cw_seo_mexico_kpi_lead_btn_html((int) $card['value'], (string) $card['click'], 'mexico');
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
    <?= seo_kpi_info_block($ind) ?>
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

<article class="va-panel" style="margin-bottom:1.25rem">
  <div class="va-panel-head">
    <h2><i class="bi bi-bullseye mr-1"></i> Metas del plan 90 días</h2>
  </div>
  <div class="va-panel-body">
    <div class="seo-kpi-goals">
      <?php foreach ($snap['goal_progress'] as $goal): ?>
      <div class="seo-kpi-goal <?= !empty($goal['ok']) ? 'is-ok' : '' ?>">
        <div class="seo-kpi-goal-top">
          <span class="seo-kpi-goal-label"><?= htmlspecialchars((string) $goal['label']) ?></span>
          <span class="seo-kpi-goal-num">
            <?= htmlspecialchars(rtrim(rtrim(number_format((float) $goal['current'], 2, '.', ''), '0'), '.')) ?>
            / <?= htmlspecialchars(rtrim(rtrim(number_format((float) $goal['target'], 2, '.', ''), '0'), '.')) ?>
            <?= htmlspecialchars((string) $goal['unit']) ?>
          </span>
        </div>
        <div class="seo-kpi-goal-bar" aria-hidden="true"><span style="width:<?= (int) $goal['pct'] ?>%"></span></div>
        <details class="seo-kpi-info">
          <summary><i class="bi bi-info-circle"></i> ¿De qué va?</summary>
          <div class="seo-kpi-info-body">
            <p><?= htmlspecialchars((string) $goal['about']) ?></p>
          </div>
        </details>
      </div>
      <?php endforeach; ?>
    </div>
    <?php
    $sem = $inds['semaforo'];
    echo seo_kpi_info_block($sem);
    ?>
  </div>
</article>

<article class="va-panel">
  <div class="va-panel-head">
    <h2><i class="bi bi-geo-alt mr-1"></i> Rendimiento por plaza</h2>
    <div class="va-panel-actions adm-actions">
      <a class="adm-act" href="<?= adm_href('analytics/seo_mexico_urls.php') . '?period=' . urlencode($period) ?>"><i class="bi bi-link-45deg"></i> URLs</a>
      <a class="adm-act" href="<?= adm_href('analytics/seo_mexico_checklist.php') ?>"><i class="bi bi-code-slash"></i> Código / sitio</a>
      <a class="adm-act" href="<?= adm_href('analytics/seo_mexico_external.php') ?>"><i class="bi bi-box-arrow-up-right"></i> Tareas externas</a>
    </div>
  </div>
  <div class="va-panel-body p-0">
    <div class="modern-table va-table-wrap">
      <table class="table va-table w-100" id="plazasKpiTable">
        <thead>
          <tr>
            <th>Plaza</th>
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
        <?php foreach ($snap['plazas'] as $row):
            $semClass = 'seo-sem-' . preg_replace('/[^a-z]/', '', (string) $row['semaforo']);
            $urlsQs = '?period=' . urlencode($period) . '&plaza=' . urlencode((string) $row['slug']);
        ?>
          <tr data-slug="<?= htmlspecialchars((string) $row['slug']) ?>">
            <td>
              <a class="seo-plaza-link" href="<?= htmlspecialchars((string) $row['url']) ?>" target="_blank" rel="noopener">
                <?= htmlspecialchars((string) $row['city']) ?>
              </a>
              <span class="seo-plaza-meta">
                <?= htmlspecialchars((string) $row['estado']) ?> ·
                <a href="<?= htmlspecialchars(adm_href('analytics/seo_mexico_urls.php') . $urlsQs) ?>">ver URLs</a>
              </span>
            </td>
            <td class="seo-kpi-num"><?= (int) $row['visitas'] ?></td>
            <td class="seo-kpi-num"><?= (int) $row['sesiones'] ?></td>
            <td class="seo-kpi-num"><?= cw_seo_mexico_kpi_lead_btn_html((int) $row['leads'], 'leads', 'plaza', ['data-plaza' => (string) $row['slug']]) ?></td>
            <td class="seo-kpi-num"><?= cw_seo_mexico_kpi_lead_btn_html((int) $row['calificados'], 'calificados', 'plaza', ['data-plaza' => (string) $row['slug']]) ?></td>
            <td class="seo-kpi-num"><?= cw_seo_mexico_kpi_lead_btn_html((int) $row['cerrados'], 'cerrados', 'plaza', ['data-plaza' => (string) $row['slug']]) ?></td>
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

<p class="text-muted small mt-3 mb-0" id="seoPlazasUpdated">
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
  var apiUrl = <?= json_encode(adm_url('analytics/api/seo_plazas_stats.php'), JSON_UNESCAPED_UNICODE) ?>;
  var leadsApi = <?= json_encode(adm_url('analytics/api/seo_leads_list.php'), JSON_UNESCAPED_UNICODE) ?>;

  if (window.SeoLeadsModal) {
    window.SeoLeadsModal.init({ apiUrl: leadsApi, period: period, from: from, to: to });
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
  var apiUrl = <?= json_encode(adm_url('analytics/api/seo_plazas_stats.php'), JSON_UNESCAPED_UNICODE) ?>;

  $('#plazasKpiTable').DataTable({
    scrollX: true,
    order: [[3, 'desc']],
    pageLength: 25,
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
    var upd = document.getElementById('seoPlazasUpdated');
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
