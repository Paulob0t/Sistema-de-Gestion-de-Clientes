<?php
require_once dirname(__DIR__) . '/auth_middleware.php';
require_once dirname(__DIR__) . '/conn.php';
require_once dirname(__DIR__) . '/includes/cw_hub_config.php';
require_once dirname(__DIR__) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__) . '/includes/cw_hub_permissions.php';
require_once dirname(__DIR__) . '/includes/adm_paths.php';
require_once dirname(__DIR__) . '/includes/cw_seo_mexico_geo_status.php';

cw_hub_migrate($conn);
cw_hub_require('hub.analytics.view');

$filter = isset($_GET['st']) ? preg_replace('/[^a-z]/', '', (string) $_GET['st']) : 'all';
$allowed = ['all', 'done', 'partial', 'pending', 'manual'];
if (!in_array($filter, $allowed, true)) {
    $filter = 'all';
}

$status = cw_seo_mexico_geo_status_build();
if ($filter !== 'all') {
    $status = cw_seo_geo_status_filter_sections($status, $filter);
}
$summary = $status['summary'];

include dirname(__DIR__) . '/menu.php';
?>
<link href="<?= adm_href('analytics/css/seo-mexico-kpis.css') ?>?v=6" rel="stylesheet">
<link href="<?= adm_href('analytics/css/seo-geo-status.css') ?>?v=1" rel="stylesheet">
<div class="adm-page-shell">
<div class="container-fluid px-0">
<?php
$websiteTab = 'seo_geo_status';
$websiteHeroTitle = 'Estado SEO / GEO México';
$websiteHeroSub = 'Qué ya está implementado en código · qué falta · qué depende de ti (GSC, Ads, producción)';
$websiteShowPeriod = false;
require dirname(__DIR__) . '/includes/website_module_shell.php';
?>

<div class="sgs-objective">
  <strong>Cómo leer esta pantalla</strong>
  <p>
    <span class="sgs-badge sgs-badge--done">Listo</span> = implementado y verificado en el código local.
    <span class="sgs-badge sgs-badge--partial">Parcial</span> = avanzado pero incompleto.
    <span class="sgs-badge sgs-badge--pending">Falta</span> = pendiente en el proyecto.
    <span class="sgs-badge sgs-badge--manual">Manual</span> = fuera del código (Search Console, cPanel, Ads).
  </p>
</div>

<?php if (!$status['site_root_ok']): ?>
<div class="alert alert-warning sgs-alert">
  No se encontró <code>conlineweb.com/sitemap.xml</code> en esta instalación. Los números pueden estar incompletos.
</div>
<?php endif; ?>

<section class="va-kpi-grid va-kpi-grid-5 sgs-kpis" aria-label="Resumen estado SEO GEO">
  <article class="va-kpi sgs-kpi-done">
    <div class="va-kpi-head"><span class="title">Listo</span><span class="icon"><i class="bi bi-check-circle"></i></span></div>
    <div class="num"><?= (int) $summary['done'] ?></div>
    <div class="va-kpi-sub">Implementado</div>
  </article>
  <article class="va-kpi sgs-kpi-partial">
    <div class="va-kpi-head"><span class="title">Parcial</span><span class="icon"><i class="bi bi-dash-circle"></i></span></div>
    <div class="num"><?= (int) $summary['partial'] ?></div>
    <div class="va-kpi-sub">Falta pulir</div>
  </article>
  <article class="va-kpi sgs-kpi-pending">
    <div class="va-kpi-head"><span class="title">Falta</span><span class="icon"><i class="bi bi-x-circle"></i></span></div>
    <div class="num"><?= (int) $summary['pending'] ?></div>
    <div class="va-kpi-sub">Por hacer en código</div>
  </article>
  <article class="va-kpi sgs-kpi-manual">
    <div class="va-kpi-head"><span class="title">Manual</span><span class="icon"><i class="bi bi-person-check"></i></span></div>
    <div class="num"><?= (int) $summary['manual'] ?></div>
    <div class="va-kpi-sub">Tú / externo</div>
  </article>
  <article class="va-kpi lw-kpi-visitas">
    <div class="va-kpi-head"><span class="title">Avance código</span><span class="icon"><i class="bi bi-speedometer2"></i></span></div>
    <div class="num"><?= (int) $summary['percent_code'] ?>%</div>
    <div class="va-kpi-sub"><?= (int) $summary['combos'] ?> combos · sitemap <?= (int) $summary['sitemap_need'] ?></div>
  </article>
</section>

<nav class="sgs-filter" aria-label="Filtrar por estado">
  <?php
  $filters = [
      'all' => 'Todos',
      'done' => 'Listo',
      'partial' => 'Parcial',
      'pending' => 'Falta',
      'manual' => 'Manual',
  ];
  foreach ($filters as $key => $label):
  ?>
  <a href="<?= adm_href('analytics/seo_mexico_geo_status.php') ?><?= $key !== 'all' ? '?st=' . urlencode($key) : '' ?>"
     class="<?= $filter === $key ? 'active' : '' ?>"><?= htmlspecialchars($label) ?></a>
  <?php endforeach; ?>
</nav>

<article class="va-panel sgs-next">
  <div class="va-panel-head">
    <h2><i class="bi bi-signpost-split"></i> Siguiente paso recomendado</h2>
  </div>
  <ol class="sgs-next-list">
    <?php foreach ($status['next_steps'] as $step): ?>
    <li><?= htmlspecialchars($step) ?></li>
    <?php endforeach; ?>
  </ol>
  <p class="sgs-meta">Actualizado <?= htmlspecialchars($status['generated_at']) ?> · fuente: <?= htmlspecialchars(basename($status['site_root'])) ?></p>
</article>

<?php foreach ($status['sections'] as $sec):
    if (empty($sec['items']) && empty($sec['table'] ?? [])) {
        continue;
    }
?>
<article class="va-panel sgs-section">
  <div class="va-panel-head sgs-section-head">
    <div>
      <h2><?= htmlspecialchars($sec['title']) ?></h2>
      <?php if (!empty($sec['subtitle'])): ?>
      <p class="sgs-sub"><?= htmlspecialchars($sec['subtitle']) ?></p>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($sec['items'])): ?>
  <div class="sgs-items">
    <?php foreach ($sec['items'] as $item):
        $st = (string) ($item['status'] ?? 'pending');
    ?>
    <div class="sgs-item sgs-item--<?= htmlspecialchars($st) ?>">
      <div class="sgs-item-top">
        <span class="sgs-badge sgs-badge--<?= htmlspecialchars($st) ?>"><?= htmlspecialchars((string) $item['status_label']) ?></span>
        <h3><?= htmlspecialchars((string) $item['title']) ?></h3>
      </div>
      <p class="sgs-plain"><?= htmlspecialchars((string) $item['plain']) ?></p>
      <?php if (!empty($item['evidence'])): ?>
      <p class="sgs-evidence"><i class="bi bi-clipboard-data"></i> <?= htmlspecialchars((string) $item['evidence']) ?></p>
      <?php endif; ?>
      <?php if (!empty($item['action'])): ?>
      <p class="sgs-action"><i class="bi bi-arrow-right-circle"></i> <strong>Qué hacer:</strong> <?= htmlspecialchars((string) $item['action']) ?></p>
      <?php endif; ?>
      <?php if (!empty($item['link'])): ?>
      <p class="sgs-link"><a href="<?= adm_href((string) $item['link']) ?>">Abrir módulo relacionado →</a></p>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (!empty($sec['table'])): ?>
  <div class="table-responsive sgs-table-wrap">
    <table class="table table-sm sgs-table mb-0">
      <thead>
        <tr>
          <th>Ciudad</th>
          <th>Prioridad</th>
          <th>Hub ciudad</th>
          <th>Patch JSON</th>
          <th>Servicio×ciudad</th>
          <th>Casos reales</th>
          <th>Estado</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($sec['table'] as $row):
            $rs = (string) ($row['status'] ?? 'pending');
            $labels = ['done' => 'Listo', 'partial' => 'Parcial', 'pending' => 'Falta'];
        ?>
        <tr>
          <td><strong><?= htmlspecialchars((string) $row['name']) ?></strong></td>
          <td><span class="sgs-tier sgs-tier--<?= strtolower(htmlspecialchars((string) $row['tier'])) ?>"><?= htmlspecialchars((string) $row['tier']) ?></span></td>
          <td><?= !empty($row['hub']) ? '✓' : '—' ?></td>
          <td><?= !empty($row['patch']) ? '✓' : '—' ?></td>
          <td><?= !empty($row['svc_geo']) ? '✓' : '—' ?></td>
          <td><?= !empty($row['cases']) ? '✓' : '—' ?></td>
          <td>
            <span class="sgs-badge sgs-badge--<?= htmlspecialchars($rs) ?>"><?= htmlspecialchars($labels[$rs] ?? $rs) ?></span>
            <?php if (!empty($row['missing'])): ?>
            <span class="sgs-missing-hint">Falta: <?= htmlspecialchars(implode(', ', $row['missing'])) ?></span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="sgs-table-note">
    <strong>P1</strong> = atacar primero (León, CDMX, Monterrey, Guadalajara) ·
    <strong>P2</strong> = fase 2 · <strong>P3</strong> = Veracruz/Oaxaca tras datos P1/P2
  </p>
  <?php endif; ?>
</article>
<?php endforeach; ?>

<p class="seo-kpi-note">
  Este panel lee el código en <code>conlineweb.com</code> automáticamente. Para tareas operativas detalladas usa
  <a href="<?= adm_href('analytics/seo_mexico_checklist.php') ?>">SEO · Auditoría código</a> y
  <a href="<?= adm_href('analytics/seo_mexico_external.php') ?>">SEO · Tareas externas</a>.
</p>

</div>
</div>
</div>
</div>
</body>
</html>
