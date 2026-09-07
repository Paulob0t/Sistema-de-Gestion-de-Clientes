<?php
require_once dirname(__DIR__) . '/auth_middleware.php';
require_once dirname(__DIR__) . '/conn.php';
require_once dirname(__DIR__) . '/includes/cw_hub_config.php';
require_once dirname(__DIR__) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__) . '/includes/cw_hub_permissions.php';
require_once dirname(__DIR__) . '/includes/adm_paths.php';
require_once dirname(__DIR__) . '/includes/cw_seo_sitemap_directory.php';

cw_hub_migrate($conn);
cw_hub_require('hub.analytics.view');

$dir = cw_seo_sitemap_directory_build();
$filter = isset($_GET['sec']) ? preg_replace('/[^a-z\-]/', '', (string) $_GET['sec']) : '';

include dirname(__DIR__) . '/menu.php';
?>
<link href="<?= adm_href('analytics/css/seo-mexico-kpis.css') ?>?v=6" rel="stylesheet">
<link href="<?= adm_href('analytics/css/seo-sitemap-directory.css') ?>?v=6" rel="stylesheet">
<div class="adm-page-shell">
<div class="container-fluid px-0">
<?php
$websiteTab = 'seo_directory';
$websiteHeroTitle = 'Directorio SEO / GEO';
$websiteHeroSub = 'Solo lo que SÍ se indexa y aporta · keyword · por qué se queda · URLs reales';
$websiteShowPeriod = false;
require dirname(__DIR__) . '/includes/website_module_shell.php';
?>

<div class="ssd-objective">
  <strong>Objetivo del sitio</strong>
  <p><?= htmlspecialchars($dir['objective']) ?></p>
</div>

<section class="va-kpi-grid va-kpi-grid-4 ssd-kpis" aria-label="Resumen sitemap">
  <article class="va-kpi">
    <div class="va-kpi-head"><span class="title">Sitemap principal</span><span class="icon"><i class="bi bi-diagram-3"></i></span></div>
    <div class="num"><?= number_format($dir['sitemap_main_count']) ?></div>
    <div class="va-kpi-sub">URLs en sitemap.xml</div>
  </article>
  <article class="va-kpi">
    <div class="va-kpi-head"><span class="title">Sitemap blog</span><span class="icon"><i class="bi bi-journal-text"></i></span></div>
    <div class="num"><?= number_format($dir['sitemap_blog_count']) ?></div>
    <div class="va-kpi-sub">URLs en sitemap-blog.xml</div>
  </article>
  <article class="va-kpi lw-kpi-cierre">
    <div class="va-kpi-head"><span class="title">Grupos indexables</span><span class="icon"><i class="bi bi-folder2-open"></i></span></div>
    <div class="num"><?= count($dir['sections']) ?></div>
    <div class="va-kpi-sub">Money · geo · blog</div>
  </article>
  <article class="va-kpi lw-kpi-visitas">
    <div class="va-kpi-head"><span class="title">Actualizado</span><span class="icon"><i class="bi bi-clock"></i></span></div>
    <div class="num ssd-kpi-time"><?= htmlspecialchars(substr($dir['generated_at'], 11, 5)) ?></div>
    <div class="va-kpi-sub"><?= htmlspecialchars(substr($dir['generated_at'], 0, 10)) ?></div>
  </article>
</section>

<nav class="ssd-filter" aria-label="Filtrar sección">
  <a href="<?= adm_href('analytics/seo_sitemap_directory.php') ?>" class="<?= $filter === '' ? 'active' : '' ?>">Todas</a>
  <?php foreach ($dir['sections'] as $sec): ?>
  <a href="<?= adm_href('analytics/seo_sitemap_directory.php') ?>?sec=<?= urlencode($sec['id']) ?>"
     class="ssd-pill ssd-pill--<?= htmlspecialchars($sec['tone']) ?> <?= $filter === $sec['id'] ? 'active' : '' ?>">
    <?= htmlspecialchars(explode('·', $sec['title'])[0]) ?>
  </a>
  <?php endforeach; ?>
</nav>

<p class="seo-kpi-note">
  <strong>A–F:</strong> lo que SÍ se queda indexado y aporta (con “por qué se queda”).
  <strong>G:</strong> páginas que debilitan y hoy están en sitemap — hay que consolidarlas.
  En patrones geo/blog, despliega para ver las URLs reales.
</p>

<?php foreach ($dir['sections'] as $sec):
    if ($filter !== '' && $filter !== $sec['id']) {
        continue;
    }
    $tone = htmlspecialchars($sec['tone']);
?>
<article class="va-panel ssd-section ssd-section--<?= $tone ?>" id="sec-<?= htmlspecialchars($sec['id']) ?>">
  <div class="va-panel-head ssd-section-head">
    <div>
      <h2><?= htmlspecialchars($sec['title']) ?></h2>
      <p class="ssd-meta">
        <span class="ssd-badge ssd-badge--<?= $tone ?>"><?= htmlspecialchars($sec['decision']) ?></span>
        <?= htmlspecialchars($sec['role']) ?>
      </p>
      <p class="ssd-obj"><i class="bi bi-bullseye"></i> <?= htmlspecialchars($sec['objective']) ?></p>
    </div>
    <?php if (!empty($sec['stats']['total'])): ?>
    <div class="ssd-stats">
      <?php if ($sec['tone'] === 'weaken'): ?>
      <span class="ssd-missing"><strong><?= (int) $sec['stats']['in'] ?></strong> indexadas · debilitan</span>
      <?php else: ?>
      <span><strong><?= (int) $sec['stats']['in'] ?></strong> en sitemap</span>
      <?php if ((int) $sec['stats']['missing'] > 0): ?>
      <span class="ssd-missing"><strong><?= (int) $sec['stats']['missing'] ?></strong> faltan</span>
      <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="table-responsive">
    <table class="table table-sm ssd-table mb-0">
      <thead>
        <tr>
          <th>URL / patrón</th>
          <th>Keyword</th>
          <th>Por qué se queda</th>
          <th>Acción</th>
          <th>Sitemap</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($sec['items'] as $idx => $item):
            $in = !empty($item['in_sitemap']);
            $children = $item['children'] ?? [];
            $hasChildren = $children !== [];
            $rowId = 'ssd-' . $sec['id'] . '-' . $idx;
            $rowClass = '';
            if ($sec['tone'] === 'weaken' && $in) {
                $rowClass = 'ssd-row--danger';
            } elseif ($sec['tone'] !== 'weaken' && !$in && !str_contains($item['url'], '{')) {
                $rowClass = 'ssd-row--warn';
            }
        ?>
        <tr class="<?= $rowClass ?>">
          <td>
            <?php if ($hasChildren): ?>
            <button type="button" class="ssd-expand" data-target="<?= htmlspecialchars($rowId) ?>" aria-expanded="false">
              <i class="bi bi-chevron-right" aria-hidden="true"></i>
              <code class="ssd-pattern"><?= htmlspecialchars($item['url']) ?></code>
              <span class="ssd-expand-count"><?= count($children) ?> URLs</span>
            </button>
            <?php elseif (str_starts_with($item['url'], '/') && !str_contains($item['url'], '{')): ?>
            <a class="ssd-url" href="https://conlineweb.com<?= htmlspecialchars($item['url']) ?>" target="_blank" rel="noopener noreferrer">
              <code><?= htmlspecialchars($item['url']) ?></code>
            </a>
            <?php else: ?>
            <code class="ssd-pattern"><?= htmlspecialchars($item['url']) ?></code>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($item['keyword']) ?></td>
          <td class="ssd-note"><?= htmlspecialchars($item['why'] ?? '') ?></td>
          <td><?= htmlspecialchars($item['action']) ?></td>
          <td>
            <span class="ssd-status ssd-status--<?= $sec['tone'] === 'weaken' && $in ? 'danger' : ($in ? 'ok' : 'off') ?>">
              <?= htmlspecialchars($item['status_label']) ?>
            </span>
          </td>
        </tr>
        <?php if ($hasChildren): ?>
        <tr class="ssd-children-row" id="<?= htmlspecialchars($rowId) ?>" hidden>
          <td colspan="5">
            <div class="ssd-children">
              <div class="ssd-children-head">
                URLs reales de este grupo
                · <?= (int) ($item['children_in'] ?? 0) ?>/<?= count($children) ?> en sitemap
              </div>
              <ul class="ssd-children-list">
                <?php foreach ($children as $child): ?>
                <li class="<?= !empty($child['in_sitemap']) ? 'is-in' : 'is-out' ?>">
                  <a href="https://conlineweb.com<?= htmlspecialchars($child['url']) ?>" target="_blank" rel="noopener noreferrer">
                    <code><?= htmlspecialchars($child['url']) ?></code>
                  </a>
                  <span class="ssd-child-label"><?= htmlspecialchars($child['label']) ?></span>
                  <span class="ssd-child-kw"><?= htmlspecialchars($child['keyword']) ?></span>
                  <span class="ssd-status ssd-status--<?= !empty($child['in_sitemap']) ? 'ok' : 'off' ?>">
                    <?= !empty($child['in_sitemap']) ? 'EN' : 'FALTA' ?>
                  </span>
                </li>
                <?php endforeach; ?>
              </ul>
            </div>
          </td>
        </tr>
        <?php endif; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</article>
<?php endforeach; ?>

<article class="va-panel">
  <div class="va-panel-head">
    <h2><i class="bi bi-link-45deg mr-1"></i> Enlaces útiles</h2>
  </div>
  <div class="ssd-links adm-actions">
    <a class="adm-act" href="<?= adm_href('analytics/seo_mexico_urls.php') ?>"><i class="bi bi-bar-chart"></i> Rendimiento URLs</a>
    <a class="adm-act" href="<?= adm_href('analytics/seo_mexico_plazas.php') ?>"><i class="bi bi-geo-alt"></i> Plazas</a>
    <a class="adm-act" href="<?= adm_href('analytics/seo_mexico_checklist.php') ?>"><i class="bi bi-code-slash"></i> Auditoría código</a>
    <a class="adm-act" href="https://conlineweb.com/sitemap.xml" target="_blank" rel="noopener noreferrer"><i class="bi bi-filetype-xml"></i> sitemap.xml</a>
    <a class="adm-act" href="https://conlineweb.com/sitemap-blog.xml" target="_blank" rel="noopener noreferrer"><i class="bi bi-journal"></i> sitemap-blog.xml</a>
  </div>
</article>

</div>
</div>
<script>
(function () {
  document.querySelectorAll('.ssd-expand').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = btn.getAttribute('data-target');
      var row = id ? document.getElementById(id) : null;
      if (!row) return;
      var open = row.hasAttribute('hidden');
      if (open) {
        row.removeAttribute('hidden');
        btn.setAttribute('aria-expanded', 'true');
        btn.classList.add('is-open');
      } else {
        row.setAttribute('hidden', 'hidden');
        btn.setAttribute('aria-expanded', 'false');
        btn.classList.remove('is-open');
      }
    });
  });
})();
</script>
</body></html>
