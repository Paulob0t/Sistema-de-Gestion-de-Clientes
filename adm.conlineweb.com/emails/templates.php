<?php
/**
 * Catálogo de plantillas de correo — listado + vista previa.
 */
require_once dirname(__DIR__) . '/auth_middleware.php';
require_once dirname(__DIR__) . '/includes/adm_paths.php';
require_once dirname(__DIR__) . '/includes/email_preview_samples.php';

$catalog = email_preview_catalog();
$projectsMeta = [
    'adm' => ['title' => 'Admin', 'short' => 'Admin', 'color' => '#6366f1'],
    'cliente' => ['title' => 'Portal cliente', 'short' => 'Cliente', 'color' => '#10b981'],
    'conlineweb' => ['title' => 'Sitio web', 'short' => 'Web', 'color' => '#000147'],
    'hostpro' => ['title' => 'HostPro', 'short' => 'HostPro', 'color' => '#0891b2'],
];

$filterProject = isset($_GET['p']) ? (string) $_GET['p'] : '';
if ($filterProject !== '' && !isset($projectsMeta[$filterProject])) {
    $filterProject = '';
}

$selected = isset($_GET['v']) ? (string) $_GET['v'] : '';
if ($selected !== '' && !isset($catalog[$selected])) {
    $selected = '';
}

// Agrupar
$grouped = [];
$counts = [];
foreach ($catalog as $key => $meta) {
    $proj = $meta['project'] ?? 'adm';
    $counts[$proj] = ($counts[$proj] ?? 0) + 1;
    if ($filterProject !== '' && $proj !== $filterProject) {
        continue;
    }
    $group = $meta['group'] ?? 'General';
    $grouped[$proj][$group][] = [
        'key' => $key,
        'label' => $meta['label'] ?? $key,
        'sort' => (int) ($meta['sort'] ?? 999),
    ];
}
foreach ($grouped as $proj => $groups) {
    ksort($groups);
    foreach ($groups as $gName => $items) {
        usort($items, static function ($a, $b) {
            return ($a['sort'] <=> $b['sort']) ?: strcmp($a['label'], $b['label']);
        });
        $grouped[$proj][$gName] = $items;
    }
    $grouped[$proj] = $groups;
}

if ($selected === '') {
    foreach ($grouped as $groups) {
        foreach ($groups as $items) {
            if (!empty($items[0]['key'])) {
                $selected = $items[0]['key'];
                break 2;
            }
        }
    }
}

$selectedMeta = $selected !== '' ? ($catalog[$selected] ?? null) : null;
$totalVisible = 0;
foreach ($grouped as $groups) {
    foreach ($groups as $items) {
        $totalVisible += count($items);
    }
}

include dirname(__DIR__) . '/menu.php';

$admPageTitle = 'Plantillas de correo';
$admPageSubtitle = 'Catálogo y vista previa (sin envío)';
$admPageIcon = 'bi bi-envelope-paper';
$admHeaderVariant = 'compact';
$admBreadcrumbs = [
    ['label' => 'Inicio', 'href' => adm_href('index.php')],
    ['label' => 'Plantillas de correo'],
];

$baseUrl = adm_href('emails/templates.php');
$qs = static function (array $params) use ($baseUrl) {
    $params = array_filter($params, static function ($v) {
        return $v !== null && $v !== '';
    });
    return $params === [] ? $baseUrl : $baseUrl . '?' . http_build_query($params);
};
?>
<link rel="stylesheet" href="<?= htmlspecialchars(adm_href('emails/emails-templates.css'), ENT_QUOTES, 'UTF-8') ?>?v=2">
<div class="adm-page-shell">
<div class="container-fluid px-0">
<?php include dirname(__DIR__) . '/includes/adm_page_header.php'; ?>

<div class="em-tpl-page">
  <div class="em-tpl__filters" role="navigation" aria-label="Filtrar por proyecto">
    <a class="em-tpl__chip<?= $filterProject === '' ? ' is-active' : '' ?>" href="<?= htmlspecialchars($qs([]), ENT_QUOTES, 'UTF-8') ?>">
      Todas <span><?= (int) array_sum($counts) ?></span>
    </a>
    <?php foreach ($projectsMeta as $pid => $pm): ?>
    <a class="em-tpl__chip<?= $filterProject === $pid ? ' is-active' : '' ?>"
       href="<?= htmlspecialchars($qs(['p' => $pid]), ENT_QUOTES, 'UTF-8') ?>"
       title="<?= htmlspecialchars($pm['title'], ENT_QUOTES, 'UTF-8') ?>">
      <?= htmlspecialchars($pm['short'], ENT_QUOTES, 'UTF-8') ?>
      <span><?= (int) ($counts[$pid] ?? 0) ?></span>
    </a>
    <?php endforeach; ?>
  </div>

  <div class="em-tpl">
    <aside class="em-tpl__sidebar" aria-label="Listado de plantillas">
      <div class="em-tpl__sidebar-head">
        <h2>Plantillas</h2>
        <p class="em-tpl__count"><?= (int) $totalVisible ?> plantilla<?= $totalVisible === 1 ? '' : 's' ?></p>
      </div>

      <div class="em-tpl__list">
        <?php if ($grouped === []): ?>
          <p class="em-tpl__empty">No hay plantillas en este filtro.</p>
        <?php endif; ?>
        <?php foreach ($grouped as $proj => $groups): ?>
          <div class="em-tpl__project">
            <h2 class="em-tpl__project-title" style="--em-chip:<?= htmlspecialchars($projectsMeta[$proj]['color'] ?? '#64748b', ENT_QUOTES, 'UTF-8') ?>">
              <span class="em-tpl__project-dot" aria-hidden="true"></span>
              <?= htmlspecialchars($projectsMeta[$proj]['title'] ?? $proj, ENT_QUOTES, 'UTF-8') ?>
            </h2>
            <?php foreach ($groups as $groupName => $items): ?>
              <h3 class="em-tpl__group"><?= htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8') ?></h3>
              <ul class="em-tpl__items">
                <?php foreach ($items as $item): ?>
                <li>
                  <a class="em-tpl__item<?= $selected === $item['key'] ? ' is-active' : '' ?>"
                     href="<?= htmlspecialchars($qs([
                         'p' => $filterProject !== '' ? $filterProject : null,
                         'v' => $item['key'],
                     ]), ENT_QUOTES, 'UTF-8') ?>">
                    <span class="em-tpl__item-label"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    <code class="em-tpl__item-key"><?= htmlspecialchars($item['key'], ENT_QUOTES, 'UTF-8') ?></code>
                  </a>
                </li>
                <?php endforeach; ?>
              </ul>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </aside>

    <section class="em-tpl__preview" aria-label="Vista previa">
      <?php if ($selected === '' || !$selectedMeta): ?>
        <div class="em-tpl__preview-empty">
          <i class="bi bi-envelope-open" aria-hidden="true"></i>
          <p>Seleccione una plantilla para ver la vista previa.</p>
        </div>
      <?php else: ?>
        <header class="em-tpl__preview-bar">
          <div>
            <h2><?= htmlspecialchars($selectedMeta['label'] ?? $selected, ENT_QUOTES, 'UTF-8') ?></h2>
            <p>
              <span class="em-tpl__badge"><?= htmlspecialchars($projectsMeta[$selectedMeta['project']]['title'] ?? $selectedMeta['project'], ENT_QUOTES, 'UTF-8') ?></span>
              <span class="em-tpl__badge em-tpl__badge--muted"><?= htmlspecialchars($selectedMeta['group'] ?? 'General', ENT_QUOTES, 'UTF-8') ?></span>
              <code><?= htmlspecialchars($selected, ENT_QUOTES, 'UTF-8') ?></code>
            </p>
          </div>
          <div class="em-tpl__preview-actions">
            <a class="btn btn-sm btn-outline-primary"
               href="<?= htmlspecialchars(adm_href('emails/render.php?v=' . rawurlencode($selected)), ENT_QUOTES, 'UTF-8') ?>"
               target="_blank" rel="noopener">
              <i class="bi bi-box-arrow-up-right"></i> Abrir
            </a>
          </div>
        </header>
        <div class="em-tpl__frame-wrap">
          <iframe
            class="em-tpl__frame"
            title="Vista previa del correo"
            src="<?= htmlspecialchars(adm_href('emails/render.php?v=' . rawurlencode($selected)), ENT_QUOTES, 'UTF-8') ?>"
          ></iframe>
        </div>
      <?php endif; ?>
    </section>
  </div>
</div>

</div>
</div>
</body>
</html>
