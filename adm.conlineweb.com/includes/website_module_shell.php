<?php
/**
 * Shell unificado — módulo Web Site (analytics + leads).
 *
 * Variables:
 *   $websiteTab       overview|funnel|pages|sessions|reportes|leads|seo_checklist|seo_external|seo_monitor|seo_ai_prompt|seo_plazas|seo_urls|seo_directory|seo_geo_status
 *   $websiteHeroTitle título del hero (opcional)
 *   $websiteHeroSub   subtítulo (opcional)
 *   $websiteShowPeriod mostrar selector de periodo (bool)
 *   $websiteWebFilter all|mx|us|cl (opcional; se lee de $_GET['web'])
 *   $period, $dateFrom, $dateTo, $from, $to (analytics)
 */
$websiteTab = $websiteTab ?? 'overview';
$websiteHeroTitle = $websiteHeroTitle ?? 'Web Site · conlineweb.com';
$websiteHeroSub = $websiteHeroSub ?? 'Analytics, tráfico y leads del sitio público';
$websiteShowPeriod = !empty($websiteShowPeriod);
$period = $period ?? ($_GET['period'] ?? '30d');
$websiteWebFilter = function_exists('cw_analytics_normalize_web')
    ? cw_analytics_normalize_web($websiteWebFilter ?? ($_GET['web'] ?? 'all'))
    : 'all';
if (function_exists('cw_analytics_web_filter')) {
    cw_analytics_web_filter($websiteWebFilter);
}
$isLocal = defined('ADM_DEV_LOCAL') && ADM_DEV_LOCAL;
$modeLabel = function_exists('cw_analytics_visits_mode_label')
    ? cw_analytics_visits_mode_label()
    : 'Producción · conlineweb.com';

$periodQs = '?period=' . urlencode($period);
if ($websiteWebFilter !== 'all') {
    $periodQs .= '&web=' . urlencode($websiteWebFilter);
}
$leadsQs = $periodQs;
if (($websiteStatusFilter ?? '') !== '') {
    $leadsQs .= '&status=' . urlencode($websiteStatusFilter);
}
if (($websiteOrigenFilter ?? '') !== '') {
    $leadsQs .= '&origen=' . urlencode($websiteOrigenFilter);
}
$showWebTabs = in_array($websiteTab, ['overview', 'funnel', 'pages', 'sessions', 'reportes'], true);
$webOpts = function_exists('cw_analytics_web_options') ? cw_analytics_web_options() : [
    'all' => 'Todos',
    'mx' => 'MX · .com',
    'us' => 'US · /us',
    'cl' => 'CL · .cl',
];
?>
<link href="<?= adm_href('css/admin-datatables.css') ?>?v=20250715" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;500;600;700;800&display=swap" rel="stylesheet">
<link href="<?= adm_href('analytics/css/visits-dashboard.css') ?>?v=15" rel="stylesheet">
<link href="<?= adm_href('website/css/leads-website.css') ?>?v=16" rel="stylesheet">

<div class="va-shell legacy-touch">
<section class="va-hero">
  <div class="va-hero-top">
    <div>
      <h1><?= htmlspecialchars($websiteHeroTitle) ?></h1>
      <p class="va-hero-sub"><?= htmlspecialchars($websiteHeroSub) ?></p>
    </div>
    <div class="va-badges">
      <span class="va-badge"><i class="bi bi-<?= $isLocal ? 'laptop' : 'globe2' ?>"></i> <?= htmlspecialchars($modeLabel) ?></span>
      <?php if ($websiteTab === 'leads'): ?>
      <span class="va-badge live"><span class="va-pulse"></span> CRM Website</span>
      <?php elseif ($websiteTab === 'seo_plazas' || $websiteTab === 'seo_urls' || $websiteTab === 'seo_directory' || $websiteTab === 'seo_geo_status'): ?>
      <span class="va-badge"><i class="bi bi-flag"></i> Plan 90 días</span>
      <?php if ($websiteTab !== 'seo_directory'): ?>
      <span class="va-badge live"><span class="va-pulse"></span> En vivo · <span id="liveUpdated">—</span></span>
      <?php else: ?>
      <span class="va-badge live"><span class="va-pulse"></span> Mapa sitemap</span>
      <?php endif; ?>
      <?php elseif ($websiteTab === 'seo_checklist' || $websiteTab === 'seo_external' || $websiteTab === 'seo_monitor' || $websiteTab === 'seo_ai_prompt'): ?>
      <span class="va-badge"><i class="bi bi-flag"></i> Plan 90 días</span>
      <?php if ($websiteTab === 'seo_monitor'): ?>
      <span class="va-badge live"><span class="va-pulse"></span> Cola de propuestas</span>
      <?php elseif ($websiteTab === 'seo_ai_prompt'): ?>
      <span class="va-badge live"><span class="va-pulse"></span> Chat IA</span>
      <?php elseif ($websiteTab === 'seo_checklist'): ?>
      <span class="va-badge live"><span class="va-pulse"></span> Auditoría código</span>
      <?php endif; ?>
      <?php elseif ($websiteTab !== 'reportes'): ?>
      <span class="va-badge live"><span class="va-pulse"></span> En vivo · <span id="liveUpdated">—</span></span>
      <?php endif; ?>
    </div>
  </div>

  <nav class="va-nav va-nav-website" aria-label="Módulo Web Site">
    <a href="<?= adm_href('analytics/index.php') . $periodQs ?>" class="<?= $websiteTab === 'overview' ? 'active' : '' ?>">
      <i class="bi bi-graph-up"></i> Dashboard
    </a>
    <a href="<?= adm_href('analytics/funnel.php') . $periodQs ?>" class="<?= $websiteTab === 'funnel' ? 'active' : '' ?>">
      <i class="bi bi-funnel"></i> Funnel
    </a>
    <a href="<?= adm_href('analytics/pages.php') . $periodQs ?>" class="<?= $websiteTab === 'pages' ? 'active' : '' ?>">
      <i class="bi bi-bar-chart"></i> Por página
    </a>
    <a href="<?= adm_href('analytics/sessions.php') . $periodQs ?>" class="<?= $websiteTab === 'sessions' ? 'active' : '' ?>">
      <i class="bi bi-person-badge"></i> Por sesión
    </a>
    <a href="<?= adm_href('analytics/reportes.php') . $periodQs ?>" class="<?= $websiteTab === 'reportes' ? 'active' : '' ?>">
      <i class="bi bi-file-earmark-spreadsheet"></i> Reportes
    </a>
    <a href="<?= adm_href('website/leads.php') . $leadsQs ?>" class="<?= $websiteTab === 'leads' ? 'active' : '' ?>">
      <i class="bi bi-person-lines-fill"></i> Leads Website
    </a>
    <a href="<?= adm_href('analytics/seo_mexico_checklist.php') ?>" class="<?= $websiteTab === 'seo_checklist' ? 'active' : '' ?>">
      <i class="bi bi-code-slash"></i> Auditoría código
    </a>
    <a href="<?= adm_href('analytics/seo_mexico_external.php') ?>" class="<?= $websiteTab === 'seo_external' ? 'active' : '' ?>">
      <i class="bi bi-box-arrow-up-right"></i> Tareas externas
    </a>
    <a href="<?= adm_href('analytics/seo_mexico_monitor.php') ?>" class="<?= $websiteTab === 'seo_monitor' ? 'active' : '' ?>">
      <i class="bi bi-activity"></i> Cola propuestas
    </a>
    <a href="<?= adm_href('analytics/seo_mexico_ai_prompt.php') ?>" class="<?= $websiteTab === 'seo_ai_prompt' ? 'active' : '' ?>">
      <i class="bi bi-chat-dots"></i> Chat IA
    </a>
    <a href="<?= adm_href('analytics/seo_mexico_plazas.php') . $periodQs ?>" class="<?= $websiteTab === 'seo_plazas' ? 'active' : '' ?>">
      <i class="bi bi-geo-alt"></i> Plazas
    </a>
    <a href="<?= adm_href('analytics/seo_mexico_urls.php') . $periodQs ?>" class="<?= $websiteTab === 'seo_urls' ? 'active' : '' ?>">
      <i class="bi bi-link-45deg"></i> URLs
    </a>
    <a href="<?= adm_href('analytics/seo_mexico_geo_status.php') ?>" class="<?= $websiteTab === 'seo_geo_status' ? 'active' : '' ?>">
      <i class="bi bi-list-check"></i> Estado GEO
    </a>
    <a href="<?= adm_href('analytics/seo_sitemap_directory.php') ?>" class="<?= $websiteTab === 'seo_directory' ? 'active' : '' ?>">
      <i class="bi bi-folder2-open"></i> Directorio SEO
    </a>
  </nav>

  <?php if ($showWebTabs):
    $webBasePath = match ($websiteTab) {
        'funnel' => 'analytics/funnel.php',
        'pages' => 'analytics/pages.php',
        'sessions' => 'analytics/sessions.php',
        'reportes' => 'analytics/reportes.php',
        default => 'analytics/index.php',
    };
    $webQsBase = '?period=' . urlencode($period);
    if ($period === 'custom') {
        if (!empty($from)) {
            $webQsBase .= '&from=' . urlencode((string) $from);
        }
        if (!empty($to)) {
            $webQsBase .= '&to=' . urlencode((string) $to);
        }
    }
  ?>
  <nav class="va-web-tabs" aria-label="Sitio web">
    <?php foreach ($webOpts as $webKey => $webLabel):
      $href = adm_href($webBasePath) . $webQsBase . ($webKey !== 'all' ? '&web=' . urlencode($webKey) : '');
    ?>
    <a href="<?= htmlspecialchars($href) ?>"
       class="va-web-tab<?= $websiteWebFilter === $webKey ? ' is-active' : '' ?>"
       data-web="<?= htmlspecialchars($webKey) ?>">
      <?= htmlspecialchars($webLabel) ?>
    </a>
    <?php endforeach; ?>
  </nav>
  <?php endif; ?>

  <?php if ($websiteShowPeriod && function_exists('cw_analytics_visits_period_options')):
    $periodOpts = cw_analytics_visits_period_options();
    $dateFrom = $dateFrom ?? date('Y-m-d');
    $dateTo = $dateTo ?? date('Y-m-d');
    $from = $from ?? null;
    $to = $to ?? null;
  ?>
  <form method="get" class="va-period-form mt-3">
    <?php if ($websiteWebFilter !== 'all'): ?>
    <input type="hidden" name="web" value="<?= htmlspecialchars($websiteWebFilter) ?>">
    <?php endif; ?>
    <?php if ($websiteTab === 'leads'): ?>
    <input type="hidden" name="status" value="<?= htmlspecialchars($websiteStatusFilter ?? '') ?>">
    <input type="hidden" name="origen" value="<?= htmlspecialchars($websiteOrigenFilter ?? '') ?>">
    <?php endif; ?>
    <select name="period" class="va-select va-select-on-dark" onchange="this.form.submit()" aria-label="Periodo">
      <?php foreach ($periodOpts as $k => $l): ?>
      <option value="<?= htmlspecialchars($k) ?>" <?= $period === $k ? 'selected' : '' ?>><?= htmlspecialchars($l) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($period === 'custom'): ?>
    <input type="date" name="from" class="va-select va-select-on-dark" value="<?= htmlspecialchars(substr($dateFrom, 0, 10)) ?>">
    <input type="date" name="to" class="va-select va-select-on-dark" value="<?= htmlspecialchars(substr($dateTo, 0, 10)) ?>">
    <button type="submit" class="btn-apply">Aplicar</button>
    <?php endif; ?>
  </form>
  <?php endif; ?>
</section>
<script>
window.CW_ANALYTICS_WEB = <?= json_encode($websiteWebFilter, JSON_UNESCAPED_UNICODE) ?>;
</script>
