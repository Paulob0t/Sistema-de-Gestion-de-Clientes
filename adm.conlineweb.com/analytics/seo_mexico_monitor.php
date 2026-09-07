<?php
/**
 * Monitor de correcciones y contenido IA (páginas / blogs) en cPanel.
 * Siempre se visualiza la propuesta antes de implementar.
 */
require_once dirname(__DIR__) . '/auth_middleware.php';
require_once dirname(__DIR__) . '/conn.php';
require_once dirname(__DIR__) . '/includes/cw_hub_config.php';
require_once dirname(__DIR__) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__) . '/includes/cw_hub_permissions.php';
require_once dirname(__DIR__) . '/includes/cw_seo_mexico_monitor.php';
require_once dirname(__DIR__) . '/includes/cw_seo_mexico_checklist.php';
require_once dirname(__DIR__) . '/includes/cw_site_ai_brain.php';
require_once dirname(__DIR__) . '/includes/cw_site_ai_alerts.php';
require_once dirname(__DIR__) . '/includes/adm_paths.php';

cw_hub_migrate($conn);
cw_hub_require('hub.analytics.view');
$brainStatus = cw_site_ai_brain_status($conn);
$aiAlerts = cw_site_ai_alerts_list($conn, 8, false);
$aiAlertsUnread = cw_site_ai_alerts_unread_count($conn);

$filterStatus = preg_replace('/[^a-z_]/', '', strtolower((string) ($_GET['status'] ?? 'all'))) ?? 'all';
$filterSource = preg_replace('/[^a-z_]/', '', strtolower((string) ($_GET['source'] ?? 'all'))) ?? 'all';
$filterContent = preg_replace('/[^a-z_]/', '', strtolower((string) ($_GET['content'] ?? 'all'))) ?? 'all';
$filterQueue = preg_replace('/[^a-z_]/', '', strtolower((string) ($_GET['queue'] ?? 'auto'))) ?? 'auto';
if (!in_array($filterQueue, ['auto', 'rehab'], true)) {
    $filterQueue = 'auto';
}

$itemsAllRaw = cw_seo_mexico_monitor_feed($conn, [
    'status' => 'all',
    'source' => 'all',
    'content' => 'all',
    'queue' => 'all',
    'limit' => 800,
]);
$queueAutoOpen = 0;
$queueRehabOpen = 0; // habilitadas (pending/working)
$queueRehabScheduled = 0;
foreach ($itemsAllRaw as $itRaw) {
    $st = (string) ($itRaw['work_status'] ?? '');
    $q = (string) ($itRaw['queue'] ?? 'auto');
    if ($q === 'rehab') {
        if (in_array($st, ['pending', 'working'], true)) {
            $queueRehabOpen++;
        } elseif ($st === 'scheduled') {
            $queueRehabScheduled++;
        }
        continue;
    }
    if (in_array($st, ['pending', 'working'], true)) {
        $queueAutoOpen++;
    }
}

$itemsAll = cw_seo_mexico_monitor_feed($conn, [
    'status' => 'all',
    'source' => $filterSource,
    'content' => 'all',
    'queue' => $filterQueue,
    'limit' => $filterQueue === 'rehab' ? 700 : 120,
]);
$stats = cw_seo_mexico_monitor_stats($itemsAll);
$items = cw_seo_mexico_monitor_feed($conn, [
    'status' => $filterStatus,
    'source' => $filterSource,
    'content' => $filterContent,
    'queue' => $filterQueue,
    'limit' => $filterQueue === 'rehab' ? 700 : 100,
]);

$pagesNew = array_values(array_filter(
    $itemsAll,
    static fn ($it) => ($it['content_type'] ?? '') === 'page'
        && !empty($it['is_new_content'])
        && ($it['work_status'] ?? '') === 'done'
        && ($it['source'] ?? '') === 'ai'
));
$pagesUpdated = array_values(array_filter(
    $itemsAll,
    static fn ($it) => ($it['content_type'] ?? '') === 'page'
        && empty($it['is_new_content'])
        && ($it['work_status'] ?? '') === 'done'
        && ($it['source'] ?? '') === 'ai'
));
$blogsNew = array_values(array_filter(
    $itemsAll,
    static fn ($it) => ($it['content_type'] ?? '') === 'blog'
        && !empty($it['is_new_content'])
        && ($it['work_status'] ?? '') === 'done'
        && ($it['source'] ?? '') === 'ai'
));
$blogsUpdated = array_values(array_filter(
    $itemsAll,
    static fn ($it) => ($it['content_type'] ?? '') === 'blog'
        && empty($it['is_new_content'])
        && ($it['work_status'] ?? '') === 'done'
        && ($it['source'] ?? '') === 'ai'
));
// Compat: totales (nuevos + actualizados)
$pagesImplemented = array_merge($pagesNew, $pagesUpdated);
$blogsImplemented = array_merge($blogsNew, $blogsUpdated);
$proposalsOpen = array_values(array_filter(
    $itemsAll,
    static fn ($it) => ($it['source'] ?? '') === 'ai' && in_array(($it['work_status'] ?? ''), ['pending', 'working'], true)
));

$aiAvailable = cw_seo_mexico_ai_available();
$plazas = cw_seo_mexico_priority_plazas();
$blogCategories = array_values(cw_seo_mexico_ai_blog_categories());
$blogPosts = cw_seo_mexico_ai_blog_existing_posts(40);
$isRehabQueue = $filterQueue === 'rehab';
$rehabPlanSummary = null;
$rehabIndexHealth = null;
if ($isRehabQueue) {
    $rehabJson = dirname(__DIR__, 2) . '/conlineweb.com/includes/blog/rehab-queue.php';
    if (is_readable($rehabJson)) {
        require_once $rehabJson;
        if (function_exists('blog_rehab_plan_summary')) {
            $rehabPlanSummary = blog_rehab_plan_summary();
        }
        if (function_exists('blog_rehab_index_health')) {
            $rehabIndexHealth = blog_rehab_index_health();
        }
    }
}
$queueQs = static function (string $queue, string $status = '', string $content = '', string $source = '') use ($filterStatus, $filterContent, $filterSource): string {
    $status = $status !== '' ? $status : $filterStatus;
    $content = $content !== '' ? $content : $filterContent;
    $source = $source !== '' ? $source : $filterSource;
    return '?queue=' . rawurlencode($queue)
        . '&content=' . rawurlencode($content)
        . '&source=' . rawurlencode($source)
        . '&status=' . rawurlencode($status);
};
$monitorApiUrl = adm_href('analytics/api/seo_mexico_monitor.php');
$designPreviewBaseUrl = adm_href('analytics/seo_mexico_design_preview.php');
$checklistUrl = adm_href('analytics/seo_mexico_checklist.php');

include dirname(__DIR__) . '/menu.php';
?>
<link href="<?= adm_href('vendor/datatables/dataTables.bootstrap4.min.css') ?>" rel="stylesheet">
<link href="<?= adm_href('css/admin-datatables.css') ?>?v=20250715" rel="stylesheet">
<div class="adm-page-shell">
<div class="container-fluid px-0">
<?php
$websiteTab = 'seo_monitor';
$websiteHeroTitle = $isRehabQueue ? 'Rehab blog' : 'Cola de propuestas automáticas';
$websiteHeroSub = $isRehabQueue
    ? 'Cada lunes a viernes a las 8:00 se habilitan 2 blogs. Aquí ves cuáles salieron y cuáles ya se rehabilitaron.'
    : 'Aquí revisas lo que la IA preparó automáticamente. Nada se publica solo: vista previa → apruebas → se aplica.';
$websiteShowPeriod = false;
require dirname(__DIR__) . '/includes/website_module_shell.php';

function seo_mon_esc(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

$seoUxActive = 'monitor';
require dirname(__DIR__) . '/includes/seo_module_nav.php';
?>

<section class="seo-mon" aria-label="<?= $isRehabQueue ? 'Rehab blog' : 'Cola de propuestas IA' ?>">
  <div class="seo-mon-queue-tabs" role="tablist" aria-label="Tipo de cola">
    <a class="seo-mon-queue-tab <?= !$isRehabQueue ? 'is-active' : '' ?>"
       role="tab" aria-selected="<?= !$isRehabQueue ? 'true' : 'false' ?>"
       href="<?= seo_mon_esc($queueQs('auto', 'all', 'all', 'all')) ?>">
      <strong>Propuestas automáticas</strong>
      <span>Cron · chat · hubs · URLs cortas</span>
      <?php if ($queueAutoOpen > 0): ?>
      <em class="seo-mon-queue-badge"><?= (int) $queueAutoOpen ?></em>
      <?php endif; ?>
    </a>
    <a class="seo-mon-queue-tab is-rehab <?= $isRehabQueue ? 'is-active' : '' ?>"
       role="tab" aria-selected="<?= $isRehabQueue ? 'true' : 'false' ?>"
       href="<?= seo_mon_esc($queueQs('rehab', 'all', 'all', 'ai')) ?>">
      <strong>Rehab blog</strong>
      <span>2 por día · lun–vie 8:00</span>
      <?php if ($queueRehabOpen > 0): ?>
      <em class="seo-mon-queue-badge" title="Habilitadas hoy/atrasadas"><?= (int) $queueRehabOpen ?></em>
      <?php elseif ($queueRehabScheduled > 0): ?>
      <em class="seo-mon-queue-badge" title="Programadas"><?= (int) $queueRehabScheduled ?></em>
      <?php endif; ?>
    </a>
  </div>

  <div class="seo-ux-panel seo-mon-guide">
    <h3><?= $isRehabQueue ? '¿Qué haces en Rehab blog?' : '¿Qué haces en esta pantalla?' ?></h3>
    <?php if ($isRehabQueue): ?>
    <p class="seo-ux-lead">
      Es automático: lun–vie a las <strong>8:00</strong> se habilitan <strong>2</strong>.
      Para validar: el cron debe decir <strong>OK</strong> con hora, y esos 2 deben aparecer en <strong>Hoy</strong>.
      En <strong>Rehabilitados</strong> ves la fecha y hora en que se cerró cada uno.
    </p>
    <?php else: ?>
    <p class="seo-ux-lead">
      1) Generas propuestas ·
      2) Abres <strong>Detalle</strong> ·
      3) Puedes <strong>aclarar</strong> o <strong>editar</strong> la solicitud ·
      4) Apruebas e implementas (o rechazas).
      Al aplicar se actualizan
      <a href="https://conlineweb.com/indice/" target="_blank" rel="noopener">/indice/</a>,
      sitemaps y LLMs.
      <br><small class="text-muted"><?= seo_mon_esc((string) ($brainStatus['message'] ?? '')) ?></small>
    </p>
    <div class="seo-ux-actions-primary">
      <?php if ($aiAvailable): ?>
      <button type="button" class="btn btn-primary seo-ux-btn-action seo-ux-btn-feedback" id="seoMonAutonomyBtn"
        title="La IA elige temas útiles y crea varias propuestas pendientes">
        <i class="fas fa-lightbulb" aria-hidden="true"></i>
        <span class="seo-ux-btn-label">Generar propuestas automáticas</span>
      </button>
      <?php endif; ?>
      <a class="btn btn-outline-primary seo-ux-btn-action" href="<?= seo_mon_esc(adm_href('analytics/seo_mexico_ai_prompt.php')) ?>">
        <i class="fas fa-comments"></i> Ir al chat con la IA
      </a>
      <a class="btn btn-outline-primary seo-ux-btn-action" href="<?= seo_mon_esc($checklistUrl) ?>">
        <i class="fas fa-search"></i> Ir a auditoría de código
      </a>
    </div>
    <div class="seo-ux-actions-secondary seo-ux-toolbar">
      <button type="button" class="btn btn-outline-secondary btn-sm seo-ux-btn-feedback" id="seoMonBrainSeedBtn"
        title="Carga/actualiza reglas, design system y objetivos en el cerebro">
        <i class="fas fa-brain" aria-hidden="true"></i>
        <span class="seo-ux-btn-label">Actualizar conocimiento base</span>
      </button>
      <button type="button" class="btn btn-outline-secondary btn-sm seo-ux-btn-feedback" id="seoMonSyncDirBtn"
        title="Regenera sitemap.xml, sitemap-blog, llms.txt/json e índice">
        <i class="fas fa-sync-alt" aria-hidden="true"></i>
        <span class="seo-ux-btn-label">Actualizar sitemaps e índice</span>
      </button>
      <a class="btn btn-outline-secondary btn-sm" href="https://conlineweb.com/indice/" target="_blank" rel="noopener">
        <i class="fas fa-external-link-alt"></i> Abrir directorio público
      </a>
    </div>
    <?php endif; ?>
    <div id="seoMonOpsStatus" class="seo-mon-ops-status" hidden aria-live="polite"></div>
  </div>

  <?php if ($aiAlerts !== []): ?>
  <div class="seo-mon-alerts" style="margin:0 0 1rem;padding:.85rem 1rem;border-radius:12px;background:#eff6ff;border:1px solid #bfdbfe">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;flex-wrap:wrap;margin-bottom:.45rem">
      <strong style="color:#1e3a8a"><i class="bi bi-bell-fill"></i> Alertas IA
        <?php if ($aiAlertsUnread > 0): ?>
        <span class="badge badge-primary"><?= (int) $aiAlertsUnread ?> sin leer</span>
        <?php endif; ?>
      </strong>
      <small class="text-muted">También en campanita y correo</small>
    </div>
    <ul style="margin:0;padding-left:1.1rem;font-size:.86rem">
      <?php foreach ($aiAlerts as $al): ?>
      <li style="margin:.2rem 0<?= (int) ($al['is_read'] ?? 0) === 0 ? ';font-weight:600' : '' ?>">
        <?= seo_mon_esc((string) ($al['title'] ?? '')) ?> —
        <?= seo_mon_esc(mb_substr((string) ($al['body'] ?? ''), 0, 140)) ?>
        <span class="text-muted">(<?= seo_mon_esc((string) ($al['created_at'] ?? '')) ?>)</span>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <?php if (!$isRehabQueue): ?>
  <div class="seo-mon-stats seo-mon-stats-6">
    <article class="seo-mon-stat is-page">
      <span>Páginas · pendientes</span>
      <strong><?= (int) $stats['pages_pending'] ?></strong>
    </article>
    <article class="seo-mon-stat is-page-done">
      <span>Páginas · implementadas</span>
      <strong><?= (int) $stats['pages_done'] ?></strong>
    </article>
    <article class="seo-mon-stat is-blog">
      <span>Blogs · pendientes</span>
      <strong><?= (int) $stats['blogs_pending'] ?></strong>
    </article>
    <article class="seo-mon-stat is-blog-done">
      <span>Blogs · implementados</span>
      <strong><?= (int) $stats['blogs_done'] ?></strong>
    </article>
    <article class="seo-mon-stat is-working">
      <span>En trabajo</span>
      <strong><?= (int) $stats['working'] ?></strong>
    </article>
    <article class="seo-mon-stat is-done">
      <span>Terminados (total)</span>
      <strong><?= (int) $stats['done'] ?></strong>
    </article>
  </div>
  <?php endif; ?>

  <?php if (!$isRehabQueue): ?>
  <div class="seo-mon-cats" role="tablist">
    <a class="seo-mon-cat <?= $filterContent === 'all' && $filterSource === 'all' ? 'is-active' : '' ?>"
       href="<?= seo_mon_esc($queueQs($filterQueue, $filterStatus, 'all', 'all')) ?>">Ver todo</a>
    <?php if (!$isRehabQueue): ?>
    <a class="seo-mon-cat <?= $filterContent === 'page_new' ? 'is-active' : '' ?>"
       href="<?= seo_mon_esc($queueQs('auto', $filterStatus, 'page_new', 'ai')) ?>">Páginas nuevas</a>
    <a class="seo-mon-cat <?= $filterContent === 'page_updated' || $filterContent === 'page' ? 'is-active' : '' ?>"
       href="<?= seo_mon_esc($queueQs('auto', $filterStatus, 'page_updated', 'ai')) ?>">Páginas actualizadas</a>
    <a class="seo-mon-cat <?= $filterContent === 'blog_new' ? 'is-active' : '' ?>"
       href="<?= seo_mon_esc($queueQs('auto', $filterStatus, 'blog_new', 'ai')) ?>">Blogs nuevos</a>
    <a class="seo-mon-cat <?= $filterContent === 'blog_updated' || $filterContent === 'blog' ? 'is-active' : '' ?>"
       href="<?= seo_mon_esc($queueQs('auto', $filterStatus, 'blog_updated', 'ai')) ?>">Blogs actualizados</a>
    <a class="seo-mon-cat <?= $filterContent === 'fix' ? 'is-active' : '' ?>"
       href="<?= seo_mon_esc($queueQs('auto', $filterStatus, 'fix', 'all')) ?>">Correcciones</a>
    <a class="seo-mon-cat <?= $filterContent === 'design' ? 'is-active' : '' ?>"
       href="<?= seo_mon_esc($queueQs('auto', $filterStatus, 'design', 'ai')) ?>">Diseño</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if (!$isRehabQueue): ?>
  <div class="seo-mon-generate seo-ux-panel seo-mon-generate-compact" id="seoMonGenerate">
    <div class="seo-mon-gen-compact-head">
      <div class="seo-ux-gen-title">
        <h3>Crear propuestas</h3>
        <small>La IA prepara el borrador · tú decides en la cola de abajo</small>
      </div>
      <?php if ($aiAvailable): ?>
      <div class="seo-mon-gen-toolbar" role="group" aria-label="Acciones para crear propuestas">
        <button type="button" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#seoMonModalHub">
          <i class="fas fa-map-marker-alt" aria-hidden="true"></i> Hub ciudad
        </button>
        <button type="button" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#seoMonModalBlog">
          <i class="fas fa-pen-nib" aria-hidden="true"></i> Blog nuevo
        </button>
        <button type="button" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#seoMonModalBlogImprove">
          <i class="fas fa-sync" aria-hidden="true"></i> Mejorar blog
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-toggle="modal" data-target="#seoMonModalMaintain">
          <i class="fas fa-wrench" aria-hidden="true"></i> Mantenimiento
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-toggle="modal" data-target="#seoMonModalDesign">
          <i class="fas fa-palette" aria-hidden="true"></i> Diseño UI
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-toggle="modal" data-target="#seoMonModalAdmin">
          <i class="fas fa-cogs" aria-hidden="true"></i> Admin
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-toggle="modal" data-target="#seoMonModalCliente">
          <i class="fas fa-user-circle" aria-hidden="true"></i> Portal cliente
        </button>
      </div>
      <?php endif; ?>
    </div>
    <?php if (!$aiAvailable): ?>
    <p class="seo-mon-warn">IA no activa. Configura OpenAI para poder crear propuestas.</p>
    <?php else: ?>
    <p class="seo-mon-gen-compact-hint">Pulsa una acción · se abre el formulario · la propuesta queda pendiente en la tabla (no modifica el sitio todavía).</p>
    <?php endif; ?>
  </div>
  <?php else:
    $ih = is_array($rehabIndexHealth) ? $rehabIndexHealth : [];
    $ihCron = is_array($ih['cron'] ?? null) ? $ih['cron'] : [];
    $ihBoard = is_array($ih['board'] ?? null) ? $ih['board'] : [];
    $ihWorking = is_array($ihBoard['working'] ?? null) ? $ihBoard['working'] : [];
    $ihDone = is_array($ihBoard['done'] ?? null) ? $ihBoard['done'] : [];
    $ihPending = is_array($ihBoard['pending'] ?? null) ? $ihBoard['pending'] : [];
    $ihTodayN = 0;
    foreach ($ihWorking as $wRow) {
        if (!empty($wRow['is_today'])) {
            $ihTodayN++;
        }
    }
    $cronOk = !empty($ihCron['ran_today']);
    $cronExpected = (int) date('N') <= 5;
    $cronAtRaw = trim((string) ($ihCron['at'] ?? ''));
    $tzMx = new DateTimeZone('America/Mexico_City');
    $fmtRehabWhen = static function (string $raw, bool $withTime) use ($tzMx): string {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        $hasTime = (bool) preg_match('/T\d{2}:\d{2}|\s\d{2}:\d{2}/', $raw);
        try {
            $dt = (new DateTimeImmutable($raw))->setTimezone($tzMx);
            if ($withTime && $hasTime) {
                return $dt->format('d/m/Y H:i');
            }
            return $dt->format('d/m/Y');
        } catch (Throwable $e) {
            return $raw;
        }
    };
    $cronAtLabel = $fmtRehabWhen($cronAtRaw, true);
    $rehabRows = [];
    foreach ($ihWorking as $row) {
        $row['_bucket'] = 'hoy';
        $row['_estado'] = !empty($row['is_today']) ? 'Hoy (habilitado)' : 'Atrasado';
        $rehabRows[] = $row;
    }
    foreach ($ihDone as $row) {
        $row['_bucket'] = 'hechos';
        $row['_estado'] = 'Rehabilitado';
        $rehabRows[] = $row;
    }
    foreach ($ihPending as $row) {
        $row['_bucket'] = 'cola';
        $row['_estado'] = 'En cola';
        $rehabRows[] = $row;
    }
  ?>
  <div class="seo-ux-panel seo-mon-rehab-simple" id="seoMonRehabNote">
    <div class="seo-mon-rehab-strip">
      <span class="seo-mon-rehab-pill <?= $cronOk ? 'is-ok' : ($cronExpected ? 'is-bad' : 'is-off') ?>">
        Cron 8:00 <?= $cronOk ? ('OK' . ($cronAtLabel !== '' ? ' · ' . $cronAtLabel : '')) : ($cronExpected ? 'aún no corre' : 'fin de semana') ?>
      </span>
      <span class="seo-mon-rehab-pill">Hoy habilitados: <strong><?= (int) $ihTodayN ?></strong> / 2</span>
      <span class="seo-mon-rehab-pill">Rehabilitados: <strong><?= count($ihDone) ?></strong></span>
      <span class="seo-mon-rehab-pill">En cola: <strong><?= count($ihPending) ?></strong></span>
    </div>
    <div class="seo-mon-rehab-tabs" id="seoMonRehabTabs" role="tablist">
      <button type="button" class="seo-mon-rehab-tab is-on" data-rehab-tab="hoy" role="tab" aria-selected="true">
        Hoy <em><?= count($ihWorking) ?></em>
      </button>
      <button type="button" class="seo-mon-rehab-tab" data-rehab-tab="hechos" role="tab" aria-selected="false">
        Rehabilitados <em><?= count($ihDone) ?></em>
      </button>
      <button type="button" class="seo-mon-rehab-tab" data-rehab-tab="cola" role="tab" aria-selected="false">
        Cola <em><?= count($ihPending) ?></em>
      </button>
    </div>
    <div class="table-responsive">
      <table class="table table-striped table-hover seo-mon-dt" id="seoMonRehabDt" width="100%" cellspacing="0">
        <thead>
          <tr>
            <th>Artículo</th>
            <th>Habilitado</th>
            <th>Rehabilitado (fecha y hora)</th>
            <th>Estado</th>
            <th>Ver</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rehabRows as $rr):
            $hab = $fmtRehabWhen((string) ($rr['enabled_at'] ?? ''), true);
            if ($hab === '') {
                $hab = $fmtRehabWhen((string) ($rr['scheduled_for'] ?? ''), false);
            }
            $reh = $fmtRehabWhen((string) ($rr['done_at'] ?? ''), true);
            if ($reh === '') {
                $reh = $fmtRehabWhen((string) ($rr['date_modified'] ?? ''), false);
            }
            $bucket = (string) ($rr['_bucket'] ?? '');
            $url = (string) ($rr['url'] ?? '#');
            $habOrder = trim((string) ($rr['enabled_at'] ?? $rr['scheduled_for'] ?? ''));
            $rehOrder = trim((string) ($rr['done_at'] ?? $rr['date_modified'] ?? ''));
          ?>
          <tr data-bucket="<?= seo_mon_esc($bucket) ?>">
            <td>
              <strong class="seo-mon-dt-title"><?= seo_mon_esc((string) ($rr['title'] ?? $rr['slug'] ?? '')) ?></strong>
              <div class="seo-mon-dt-sub"><?= seo_mon_esc((string) ($rr['slug'] ?? '')) ?></div>
            </td>
            <td data-order="<?= seo_mon_esc($habOrder) ?>"><?= $hab !== '' ? seo_mon_esc($hab) : '<span class="text-muted">—</span>' ?></td>
            <td data-order="<?= seo_mon_esc($rehOrder) ?>"><?= $reh !== '' ? seo_mon_esc($reh) : '<span class="text-muted">—</span>' ?></td>
            <td><span class="seo-mon-rehab-state is-<?= seo_mon_esc($bucket) ?>"><?= seo_mon_esc((string) ($rr['_estado'] ?? '')) ?></span></td>
            <td class="seo-mon-dt-actions">
              <a class="btn btn-outline-primary btn-sm" href="<?= seo_mon_esc($url) ?>" target="_blank" rel="noopener">Abrir</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <div id="seoMonStatus" class="seo-mon-flash" hidden></div>

  <?php if (!$isRehabQueue): ?>
  <div class="seo-mon-catalogs seo-mon-catalogs-4">
    <div class="seo-mon-catalog">
      <h4>Páginas implementadas <small>(nuevas)</small> · <?= count($pagesNew) ?></h4>
      <?php if ($pagesNew === []): ?>
      <p class="seo-mon-empty-mini">Aún no hay páginas nuevas publicadas por IA.</p>
      <?php else: ?>
      <ul>
        <?php foreach (array_slice($pagesNew, 0, 10) as $pg): ?>
        <li>
          <span class="seo-mon-tag is-page is-new">Nueva</span>
          <a href="<?= seo_mon_esc((string) ($pg['url'] ?? '#')) ?>" target="_blank" rel="noopener">
            <?= seo_mon_esc((string) ($pg['title'] ?? '')) ?>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <a class="seo-mon-catalog-link" href="?content=page_new&source=ai&status=done">Ver filtro →</a>
    </div>
    <div class="seo-mon-catalog">
      <h4>Páginas actualizadas <small>(existentes)</small> · <?= count($pagesUpdated) ?></h4>
      <?php if ($pagesUpdated === []): ?>
      <p class="seo-mon-empty-mini">Aún no hay hubs/páginas existentes actualizadas.</p>
      <?php else: ?>
      <ul>
        <?php foreach (array_slice($pagesUpdated, 0, 10) as $pg): ?>
        <li>
          <span class="seo-mon-tag is-page is-updated">Actualizada</span>
          <a href="<?= seo_mon_esc((string) ($pg['url'] ?? '#')) ?>" target="_blank" rel="noopener">
            <?= seo_mon_esc((string) ($pg['title'] ?? '')) ?>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <a class="seo-mon-catalog-link" href="?content=page_updated&source=ai&status=done">Ver filtro →</a>
    </div>
    <div class="seo-mon-catalog">
      <h4>Blogs implementados <small>(nuevos)</small> · <?= count($blogsNew) ?></h4>
      <?php if ($blogsNew === []): ?>
      <p class="seo-mon-empty-mini">Aún no hay artículos nuevos publicados por IA.</p>
      <?php else: ?>
      <ul>
        <?php foreach (array_slice($blogsNew, 0, 10) as $bg): ?>
        <li>
          <span class="seo-mon-tag is-blog is-new">Nuevo</span>
          <a href="<?= seo_mon_esc((string) ($bg['url'] ?? '#')) ?>" target="_blank" rel="noopener">
            <?= seo_mon_esc((string) ($bg['title'] ?? '')) ?>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <a class="seo-mon-catalog-link" href="?content=blog_new&source=ai&status=done">Ver filtro →</a>
    </div>
    <div class="seo-mon-catalog">
      <h4>Blogs actualizados <small>(existentes)</small> · <?= count($blogsUpdated) ?></h4>
      <?php if ($blogsUpdated === []): ?>
      <p class="seo-mon-empty-mini">Aún no hay artículos existentes mejorados por IA.</p>
      <?php else: ?>
      <ul>
        <?php foreach (array_slice($blogsUpdated, 0, 10) as $bg): ?>
        <li>
          <span class="seo-mon-tag is-blog is-updated">Actualizado</span>
          <a href="<?= seo_mon_esc((string) ($bg['url'] ?? '#')) ?>" target="_blank" rel="noopener">
            <?= seo_mon_esc((string) ($bg['title'] ?? '')) ?>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <a class="seo-mon-catalog-link" href="<?= seo_mon_esc($queueQs('auto', 'done', 'blog_updated', 'ai')) ?>">Ver filtro →</a>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!$isRehabQueue): ?>
  <div class="seo-ux-panel seo-mon-table-panel">
    <div class="seo-mon-table-head seo-dt-table-head">
      <div class="seo-dt-head-copy">
        <h3>Propuestas automáticas</h3>
        <p class="seo-ux-lead seo-dt-head-lead">
          <?= count($proposalsOpen) ?> abiertas · <?= count($items) ?> en este filtro.
          Usa <strong>Detalle</strong> para aclarar, editar y luego aprobar o rechazar.
        </p>
      </div>
      <div class="seo-dt-toolbar">
        <form method="get" class="seo-dt-filters seo-mon-filters">
          <input type="hidden" name="queue" value="<?= seo_mon_esc($filterQueue) ?>">
          <input type="hidden" name="content" value="<?= seo_mon_esc($filterContent) ?>">
          <input type="hidden" name="source" value="<?= seo_mon_esc($filterSource) ?>">
          <label>
            Estado
            <select name="status" onchange="this.form.submit()">
              <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>Todos</option>
              <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>><?= $isRehabQueue ? 'Habilitadas' : 'Pendiente' ?></option>
              <?php if ($isRehabQueue): ?>
              <option value="scheduled" <?= $filterStatus === 'scheduled' ? 'selected' : '' ?>>Programadas</option>
              <?php endif; ?>
              <option value="working" <?= $filterStatus === 'working' ? 'selected' : '' ?>>En revisión</option>
              <option value="done" <?= $filterStatus === 'done' ? 'selected' : '' ?>>Terminado</option>
              <option value="failed" <?= $filterStatus === 'failed' ? 'selected' : '' ?>>Rechazado</option>
            </select>
          </label>
        </form>
        <button type="button" class="btn seo-dt-refresh-btn" id="seoMonRefreshBtn" title="Recargar tabla">
          <i class="fas fa-sync-alt" aria-hidden="true"></i>
          <span>Actualizar tabla</span>
        </button>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-striped table-hover seo-mon-dt" id="seoMonProposalsTable" width="100%" cellspacing="0">
        <thead>
          <tr>
            <th>Generada</th>
            <th>Título</th>
            <th>Portal</th>
            <th>Tipo</th>
            <th>Estado</th>
            <th>URL</th>
            <th>Implementada</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $it):
            $st = (string) ($it['work_status'] ?? 'pending');
            $url = (string) ($it['url'] ?? '');
            $isHttp = str_starts_with($url, 'http');
            $typeLabel = (string) ($it['type_label'] ?? cw_seo_mexico_monitor_type_label($it));
            $subtypeLabel = (string) ($it['subtype_label'] ?? cw_seo_mexico_monitor_subtype_label($it));
            $portalMeta = [
                'portal' => (string) ($it['portal'] ?? ''),
                'portal_label' => (string) ($it['portal_label'] ?? ''),
            ];
            if ($portalMeta['portal'] === '' || $portalMeta['portal_label'] === '') {
                $portalMeta = cw_seo_mexico_monitor_portal($it);
            }
            $portalKey = (string) ($portalMeta['portal'] ?? 'site');
            $portalLabel = (string) ($portalMeta['portal_label'] ?? 'conlineweb.com');
            $isAi = ($it['source'] ?? '') === 'ai';
            $proposalId = $isAi ? (int) ($it['source_id'] ?? 0) : 0;
            $canDetail = $proposalId > 0;
            $createdSort = (string) ($it['created_at'] ?? '');
            $implementedAt = trim((string) ($it['finished_at'] ?? $it['applied_at'] ?? ''));
          ?>
          <tr data-status="<?= seo_mon_esc($st) ?>" data-portal="<?= seo_mon_esc($portalKey) ?>">
            <td data-order="<?= seo_mon_esc($createdSort) ?>">
              <?= seo_mon_esc($createdSort !== '' ? $createdSort : '—') ?>
            </td>
            <td>
              <strong class="seo-mon-dt-title"><?= seo_mon_esc((string) ($it['title'] ?? 'Propuesta')) ?></strong>
              <?php if ($subtypeLabel !== ''): ?>
              <div class="seo-mon-dt-sub"><?= seo_mon_esc($subtypeLabel) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <span class="seo-mon-portal is-<?= seo_mon_esc($portalKey) ?>" title="<?= seo_mon_esc($portalLabel) ?>">
                <?= seo_mon_esc($portalLabel) ?>
              </span>
            </td>
            <td>
              <span class="seo-mon-tag is-<?= seo_mon_esc((string) ($it['content_type'] ?? 'other')) ?>"><?= seo_mon_esc($typeLabel) ?></span>
            </td>
            <td>
              <span class="seo-mon-badge seo-mon-badge-<?= seo_mon_esc($st) ?>"><?= seo_mon_esc((string) ($it['work_label'] ?? $st)) ?></span>
            </td>
            <td class="seo-mon-dt-url">
              <?php if ($url !== '' && $isHttp): ?>
              <a href="<?= seo_mon_esc($url) ?>" target="_blank" rel="noopener" title="<?= seo_mon_esc($url) ?>">
                <?= seo_mon_esc(mb_strlen($url) > 48 ? mb_substr($url, 0, 48) . '…' : $url) ?>
              </a>
              <?php elseif ($url !== ''): ?>
              <code><?= seo_mon_esc(mb_substr($url, 0, 48)) ?></code>
              <?php else: ?>
              <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td data-order="<?= seo_mon_esc($implementedAt) ?>">
              <?php if ($implementedAt !== ''): ?>
                <?= seo_mon_esc($implementedAt) ?>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td class="seo-mon-dt-actions">
              <?php if ($canDetail): ?>
              <button type="button" class="btn btn-primary btn-sm seo-mon-preview-btn"
                data-proposal-id="<?= $proposalId ?>"
                title="Fundamento, detalle y antes/después">
                <i class="fas fa-search"></i> Detalle
              </button>
              <?php elseif (($it['source'] ?? '') === 'audit' && !empty($it['auto_fixable'])): ?>
              <a class="btn btn-success btn-sm" href="<?= seo_mon_esc($checklistUrl) ?>#seoFixQueue">Aplicar corrección</a>
              <?php elseif ($isHttp && $st === 'done'): ?>
              <a class="btn btn-outline-primary btn-sm" href="<?= seo_mon_esc($url) ?>" target="_blank" rel="noopener">Abrir URL</a>
              <?php else: ?>
              <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</section>

<!-- Modales: crear propuestas (formularios fuera de la vista principal) -->
<div class="modal fade seo-mon-gen-modal" id="seoMonModalHub" tabindex="-1" role="dialog" aria-labelledby="seoMonModalHubTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="seoMonModalHubTitle">Página / hub de ciudad</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <p class="seo-mon-modal-lead">Genera title, meta, H1 y hero para el hub de la plaza elegida.</p>
        <label class="seo-ux-field-label" for="seoMonCity">Ciudad</label>
        <select id="seoMonCity" class="form-control">
          <?php foreach ($plazas as $plaza): ?>
          <option value="<?= seo_mon_esc((string) ($plaza['slug'] ?? '')) ?>"><?= seo_mon_esc((string) ($plaza['city'] ?? '')) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary seo-ux-btn-action" id="seoMonProposePageBtn">Crear propuesta de página</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade seo-mon-gen-modal" id="seoMonModalBlog" tabindex="-1" role="dialog" aria-labelledby="seoMonModalBlogTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="seoMonModalBlogTitle">Artículo de blog nuevo</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <p class="seo-mon-modal-lead">Elige categoría · la IA genera el tema y el borrador del artículo.</p>
        <label class="seo-ux-field-label" for="seoMonBlogCat">Categoría</label>
        <select id="seoMonBlogCat" class="form-control">
          <?php if ($blogCategories === []): ?>
          <option value="seo">SEO y Posicionamiento</option>
          <?php else: ?>
            <?php foreach ($blogCategories as $bc): ?>
            <option value="<?= seo_mon_esc((string) ($bc['slug'] ?? '')) ?>"><?= seo_mon_esc((string) ($bc['name'] ?? $bc['slug'] ?? '')) ?></option>
            <?php endforeach; ?>
          <?php endif; ?>
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary seo-ux-btn-action" id="seoMonProposeBlogBtn">Generar propuesta de artículo</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade seo-mon-gen-modal" id="seoMonModalBlogImprove" tabindex="-1" role="dialog" aria-labelledby="seoMonModalBlogImproveTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="seoMonModalBlogImproveTitle">Mejorar artículo publicado</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <label class="seo-ux-field-label" for="seoMonBlogImprove">Artículo</label>
        <select id="seoMonBlogImprove" class="form-control">
          <option value="">— Elige un post —</option>
          <?php foreach ($blogPosts as $bp): ?>
          <option value="<?= seo_mon_esc((string) ($bp['slug'] ?? '')) ?>">
            [<?= seo_mon_esc((string) ($bp['category'] ?? '')) ?>] <?= seo_mon_esc((string) ($bp['title'] ?? '')) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary seo-ux-btn-action" id="seoMonImproveBlogBtn">Crear propuesta de mejora</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade seo-mon-gen-modal" id="seoMonModalMaintain" tabindex="-1" role="dialog" aria-labelledby="seoMonModalMaintainTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="seoMonModalMaintainTitle">Cambio puntual en el sitio</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <p class="seo-mon-modal-lead">Texto, icono o sección · parche search/replace seguro para tu aprobación.</p>
        <label class="seo-ux-field-label" for="seoMonMaintain">Instrucción</label>
        <input type="text" class="form-control" id="seoMonMaintain" maxlength="500" placeholder="Ej: cambia el subtítulo del hero en index.php a…">
        <label class="seo-ux-field-label" for="seoMonMaintainPath" style="margin-top:.75rem">Archivo (opcional)</label>
        <input type="text" class="form-control" id="seoMonMaintainPath" maxlength="220" placeholder="ej. index.php">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary seo-ux-btn-action" id="seoMonMaintainBtn">Crear propuesta de mantenimiento</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade seo-mon-gen-modal" id="seoMonModalDesign" tabindex="-1" role="dialog" aria-labelledby="seoMonModalDesignTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="seoMonModalDesignTitle">Diseño UI con preview</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <p class="seo-mon-modal-lead">Primero ves la maqueta; solo si te convence, implementas.</p>
        <label class="seo-ux-field-label" for="seoMonDesign">Descripción del diseño</label>
        <input type="text" class="form-control" id="seoMonDesign" maxlength="600" placeholder="Ej: reinventar hero de León con cards alineadas al design system…">
        <div class="form-row" style="margin-top:.75rem">
          <div class="col-md-6">
            <label class="seo-ux-field-label" for="seoMonDesignUrl">URL (opcional)</label>
            <input type="text" class="form-control" id="seoMonDesignUrl" maxlength="300" placeholder="https://…">
          </div>
          <div class="col-md-6">
            <label class="seo-ux-field-label" for="seoMonDesignPath">Archivo (opcional)</label>
            <input type="text" class="form-control" id="seoMonDesignPath" maxlength="220" placeholder="desarrollo-de-software.php">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary seo-ux-btn-action" id="seoMonDesignBtn">Crear diseño con preview</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade seo-mon-gen-modal" id="seoMonModalAdmin" tabindex="-1" role="dialog" aria-labelledby="seoMonModalAdminTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="seoMonModalAdminTitle">Mejoras Admin (adm.conlineweb.com)</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <p class="seo-mon-modal-lead">Todas las vistas del panel · sin auth, cobros Stripe, conn ni SMTP.</p>
        <label class="seo-ux-field-label" for="seoMonAdminView">Vista</label>
        <select id="seoMonAdminView" class="form-control">
          <option value="panel">Cualquier vista del panel</option>
          <option value="monitor">Cola de propuestas IA</option>
          <option value="checklist">Auditoría / Checklist</option>
          <option value="chat_ia">Chat / Colaboración IA</option>
          <option value="shell">Menú / inicio admin</option>
          <option value="clientes">Clientes</option>
          <option value="dominios">Dominios</option>
          <option value="hosting">Hosting</option>
          <option value="pagos_ui">Pagos (solo UI)</option>
          <option value="tickets">Tickets</option>
          <option value="tickets_ext">Tickets externos</option>
          <option value="leads">Leads / CRM</option>
          <option value="solicitudes">Solicitudes</option>
          <option value="cotizaciones">Cotizaciones</option>
          <option value="chat_live">Chat en vivo / WhatsApp</option>
          <option value="briefings">Briefings / proyectos</option>
          <option value="analytics">Analytics / reportes</option>
          <option value="external">Tareas externas SEO</option>
          <option value="plazas">Plazas GEO</option>
          <option value="urls">URLs SEO</option>
          <option value="email">Plantillas correo (diseño)</option>
          <option value="admin_css">CSS / JS plataforma</option>
        </select>
        <label class="seo-ux-field-label" for="seoMonAdminInstr" style="margin-top:.75rem">Qué mejorar (opcional)</label>
        <input type="text" class="form-control" id="seoMonAdminInstr" maxlength="500" placeholder="Si vacío, la IA elige según el propósito de la vista">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-outline-secondary seo-ux-btn-action" id="seoMonAdminBatchBtn">3 vistas clave</button>
        <button type="button" class="btn btn-primary seo-ux-btn-action" id="seoMonAdminViewBtn">Proponer mejora</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade seo-mon-gen-modal" id="seoMonModalCliente" tabindex="-1" role="dialog" aria-labelledby="seoMonModalClienteTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="seoMonModalClienteTitle">Portal cliente (cliente.conlineweb.com)</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <p class="seo-mon-modal-lead">UX + diseño de correo · no pagos, auth, DNS ni SMTP.</p>
        <label class="seo-ux-field-label" for="seoMonClienteView">Vista</label>
        <select id="seoMonClienteView" class="form-control">
          <option value="shell">Shell / navegación</option>
          <option value="home">Inicio portal</option>
          <option value="tickets">Tickets / soporte</option>
          <option value="sitios">Mis sitios</option>
          <option value="hosting">Hosting</option>
          <option value="dominios">Dominios</option>
          <option value="productos">Productos</option>
          <option value="css">Estilos del portal</option>
          <option value="email">Plantilla correo (diseño)</option>
        </select>
        <label class="seo-ux-field-label" for="seoMonClienteInstr" style="margin-top:.75rem">Qué mejorar (opcional)</label>
        <input type="text" class="form-control" id="seoMonClienteInstr" maxlength="500" placeholder="Si vacío, la IA elige según el propósito">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-outline-secondary seo-ux-btn-action" id="seoMonClienteBatchBtn">3 vistas clave</button>
        <button type="button" class="btn btn-primary seo-ux-btn-action" id="seoMonClienteViewBtn">Proponer mejora</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal detalle de propuesta -->
<div class="modal fade" id="seoMonPreviewModal" tabindex="-1" role="dialog" aria-labelledby="seoMonPreviewTitle" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="seoMonPreviewTitle">Detalle de la propuesta</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body" id="seoMonPreviewBody">
        <p class="text-muted">Cargando detalle…</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-warning" id="seoMonWorkingBtn" disabled
          title="Marca la propuesta como en revisión">
          Marcar en revisión
        </button>
        <button type="button" class="btn btn-outline-danger" id="seoMonRejectBtn" disabled
          title="Descarta la propuesta; no se escribe nada en el sitio">
          Rechazar propuesta
        </button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
        <button type="button" class="btn btn-success" id="seoMonApplyBtn" disabled
          title="Escribe los cambios en el sitio público">
          Aprobar e implementar en el sitio
        </button>
      </div>
    </div>
  </div>
</div>

<style>
.seo-mon{margin:1rem 0 2rem}
.seo-mon-guide{display:flex;flex-wrap:wrap;justify-content:space-between;gap:.75rem;padding:.85rem 1rem;border-radius:12px;background:#f8fafc;border:1px solid rgba(15,23,42,.08);margin-bottom:.85rem}
.seo-mon-guide p{margin:0;max-width:52rem;font-size:.88rem;color:#334155;line-height:1.45}
.seo-mon-stats{display:grid;gap:.65rem;margin-bottom:.85rem}
.seo-mon-stats-6{grid-template-columns:repeat(6,1fr)}
@media(max-width:1100px){.seo-mon-stats-6{grid-template-columns:repeat(3,1fr)}}
@media(max-width:700px){.seo-mon-stats-6{grid-template-columns:1fr 1fr}}
.seo-mon-stat{padding:.7rem .8rem;border-radius:12px;background:#fff;border:1px solid rgba(15,23,42,.08)}
.seo-mon-stat span{display:block;font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.03em;color:#64748b}
.seo-mon-stat strong{display:block;margin-top:.15rem;font-size:1.4rem;font-weight:800;color:#0f172a}
.seo-mon-stat.is-page,.seo-mon-stat.is-page-done{border-color:rgba(14,165,233,.35);background:#f0f9ff}
.seo-mon-stat.is-blog,.seo-mon-stat.is-blog-done{border-color:rgba(168,85,247,.3);background:#faf5ff}
.seo-mon-stat.is-working{border-color:rgba(37,99,235,.3);background:#eff6ff}
.seo-mon-stat.is-done{border-color:rgba(22,163,74,.3);background:#f0fdf4}
.seo-mon-cats{display:flex;flex-wrap:wrap;gap:.4rem;margin-bottom:.85rem}
.seo-mon-cat{padding:.4rem .75rem;border-radius:999px;font-size:.78rem;font-weight:800;text-decoration:none;color:#475569;background:#e2e8f0}
.seo-mon-cat.is-active{background:#0f172a;color:#fff}
.seo-mon-queue-tabs{display:grid;grid-template-columns:1fr 1fr;gap:.65rem;margin:0 0 1rem}
@media(max-width:720px){.seo-mon-queue-tabs{grid-template-columns:1fr}}
.seo-mon-queue-tab{position:relative;display:flex;flex-direction:column;gap:.15rem;padding:.85rem 1rem;border-radius:14px;text-decoration:none;color:#334155;background:#fff;border:1px solid #e2e8f0;box-shadow:0 1px 2px rgba(15,23,42,.04)}
.seo-mon-queue-tab strong{font-size:.95rem;font-weight:800;color:#0f172a}
.seo-mon-queue-tab span{font-size:.76rem;color:#64748b;line-height:1.35}
.seo-mon-queue-tab.is-active{border-color:#2563eb;background:#eff6ff;box-shadow:0 0 0 1px rgba(37,99,235,.25)}
.seo-mon-queue-tab.is-rehab.is-active{border-color:#7c3aed;background:#f5f3ff;box-shadow:0 0 0 1px rgba(124,58,237,.25)}
.seo-mon-queue-badge{position:absolute;top:.65rem;right:.75rem;min-width:1.4rem;padding:.1rem .4rem;border-radius:999px;background:#0f172a;color:#fff;font-size:.72rem;font-style:normal;font-weight:800;text-align:center}
.seo-mon-queue-tab.is-rehab .seo-mon-queue-badge{background:#6d28d9}
.seo-mon-rehab-simple{margin:0 0 1rem;padding:.85rem 1rem;border:1px solid #ddd6fe;background:#faf8ff;border-radius:12px}
.seo-mon-rehab-strip{display:flex;flex-wrap:wrap;gap:.45rem;margin:0 0 .75rem}
.seo-mon-rehab-pill{display:inline-flex;align-items:center;gap:.25rem;padding:.35rem .65rem;border-radius:999px;background:#fff;border:1px solid #e9d5ff;font-size:.78rem;color:#5b21b6}
.seo-mon-rehab-pill strong{font-weight:800}
.seo-mon-rehab-pill.is-ok{border-color:#86efac;background:#ecfdf5;color:#14532d}
.seo-mon-rehab-pill.is-bad{border-color:#fca5a5;background:#fef2f2;color:#991b1b}
.seo-mon-rehab-pill.is-off{border-color:#e2e8f0;background:#f8fafc;color:#64748b}
.seo-mon-rehab-tabs{display:flex;flex-wrap:wrap;gap:.4rem;margin:0 0 .75rem}
.seo-mon-rehab-tab{border:1px solid #cbd5e1;background:#fff;color:#334155;border-radius:999px;padding:.4rem .8rem;font-size:.8rem;font-weight:700;cursor:pointer}
.seo-mon-rehab-tab em{font-style:normal;margin-left:.25rem;background:#e2e8f0;border-radius:999px;padding:.05rem .4rem;font-size:.7rem}
.seo-mon-rehab-tab.is-on[data-rehab-tab="hoy"]{background:#fff7ed;border-color:#ea580c;color:#9a3412}
.seo-mon-rehab-tab.is-on[data-rehab-tab="hechos"]{background:#ecfdf5;border-color:#15803d;color:#14532d}
.seo-mon-rehab-tab.is-on[data-rehab-tab="cola"]{background:#f1f5f9;border-color:#475569;color:#0f172a}
.seo-mon-rehab-tab.is-on em{background:rgba(15,23,42,.12)}
.seo-mon-rehab-state{display:inline-block;padding:.15rem .5rem;border-radius:999px;font-size:.72rem;font-weight:800}
.seo-mon-rehab-state.is-hoy{background:#ffedd5;color:#9a3412}
.seo-mon-rehab-state.is-hechos{background:#dcfce7;color:#14532d}
.seo-mon-rehab-state.is-cola{background:#e2e8f0;color:#334155}
.seo-mon-badge-scheduled{background:#ede9fe;color:#5b21b6}
.seo-mon-generate-compact{margin-bottom:.65rem;padding:.65rem 1rem;border-radius:12px;border:1px solid rgba(37,99,235,.2);background:#eff6ff}
.seo-mon-gen-compact-head{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap}
.seo-mon-gen-compact-head .seo-ux-gen-title h3{margin:0;font-size:.9rem;font-weight:800;color:#1e3a8a}
.seo-mon-gen-compact-head .seo-ux-gen-title small{display:block;margin-top:.15rem;font-size:.74rem;color:#64748b}
.seo-mon-gen-toolbar{display:flex;flex-wrap:wrap;gap:.35rem;align-items:center}
.seo-mon-gen-compact-hint{margin:.5rem 0 0;font-size:.78rem;color:#64748b;line-height:1.4}
.seo-mon-modal-lead{margin:0 0 .85rem;font-size:.84rem;color:#64748b;line-height:1.45}
.seo-mon-generate h3{margin:0 0 .55rem;font-size:.9rem;font-weight:800;color:#1e3a8a}
.seo-mon-warn{margin:0;color:#9a3412;font-size:.84rem}
.seo-mon-gen-grid{display:grid;grid-template-columns:1fr 1fr;gap:.65rem}
@media(max-width:900px){.seo-mon-gen-grid{grid-template-columns:1fr}}
.seo-mon-gen-box{background:#fff;border-radius:10px;padding:.65rem;border:1px solid rgba(37,99,235,.12)}
.seo-mon-gen-box-wide{grid-column:1 / -1}
.seo-mon-gen-box label{display:block;font-size:.72rem;font-weight:800;color:#64748b;margin-bottom:.35rem;text-transform:uppercase}
.seo-mon-hint{display:block;margin-top:.35rem;font-size:.72rem;color:#64748b;line-height:1.35}
.seo-mon-row{display:flex;gap:.4rem;flex-wrap:wrap;align-items:center}
.seo-mon-row select,.seo-mon-row input{flex:1;min-width:120px;padding:.4rem .55rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.84rem}
.seo-mon-row-blog input{flex:2;min-width:160px}
.seo-mon-catalogs{display:grid;grid-template-columns:1fr 1fr;gap:.65rem;margin-bottom:.9rem}
.seo-mon-catalogs-4{grid-template-columns:repeat(4,minmax(0,1fr))}
@media(max-width:1200px){.seo-mon-catalogs-4{grid-template-columns:1fr 1fr}}
@media(max-width:800px){.seo-mon-catalogs,.seo-mon-catalogs-4{grid-template-columns:1fr}}
.seo-mon-catalog{background:#fff;border:1px solid rgba(15,23,42,.08);border-radius:12px;padding:.75rem .85rem;display:flex;flex-direction:column}
.seo-mon-catalog h4{margin:0 0 .45rem;font-size:.82rem;font-weight:800;color:#0f172a}
.seo-mon-catalog h4 small{display:inline;font-weight:600;color:#64748b;font-size:.72rem}
.seo-mon-catalog ul{margin:0;padding:0;list-style:none;max-height:180px;overflow:auto;flex:1}
.seo-mon-catalog li{padding:.3rem 0;border-bottom:1px solid #f1f5f9;font-size:.8rem}
.seo-mon-catalog a{color:#0369a1;word-break:break-word}
.seo-mon-catalog-link{display:inline-block;margin-top:.55rem;font-size:.72rem;font-weight:700;color:#0369a1;text-decoration:none}
.seo-mon-catalog-link:hover{text-decoration:underline}
.seo-mon-empty-mini{margin:0;font-size:.8rem;color:#94a3b8}
.seo-mon-tag.is-updated{background:#ffedd5;color:#9a3412}
.seo-mon-tag{display:inline-block;padding:.12rem .45rem;border-radius:999px;font-size:.66rem;font-weight:800;margin-right:.25rem}
.seo-mon-tag.is-page{background:#e0f2fe;color:#0369a1}
.seo-mon-tag.is-blog{background:#f3e8ff;color:#7e22ce}
.seo-mon-tag.is-fix{background:#f1f5f9;color:#475569}
.seo-mon-tag.is-design{background:#ecfeff;color:#0e7490}
.seo-mon-tag.is-external{background:#fef3c7;color:#92400e}
.seo-mon-tag.is-new{background:#dcfce7;color:#166534}
.seo-mon-design-frame{width:100%;min-height:420px;border:1px solid #cbd5e1;border-radius:12px;background:#0a0a0f;margin-top:.65rem}
.seo-mon-flash{margin-bottom:.65rem;padding:.55rem .7rem;border-radius:8px;background:#dbeafe;color:#1e3a8a;font-size:.84rem}
.seo-mon-flash.is-err{background:#fef2f2;color:#991b1b}
.seo-mon-table-panel{padding:1rem 1.1rem 1.15rem}
.seo-mon-dt{font-size:.84rem}
.seo-mon-dt td{vertical-align:middle}
.seo-mon-dt-title{font-size:.86rem;color:#0f172a}
.seo-mon-dt-sub{font-size:.72rem;color:#64748b;font-weight:600;margin-top:.1rem}
.seo-mon-dt-url{max-width:200px;word-break:break-all;font-size:.78rem}
.seo-mon-dt-actions{white-space:nowrap}
.seo-mon-portal{display:inline-block;padding:.15rem .45rem;border-radius:8px;font-size:.68rem;font-weight:800;letter-spacing:.01em}
.seo-mon-portal.is-site{background:#ecfdf5;color:#166534;border:1px solid rgba(22,163,74,.25)}
.seo-mon-portal.is-admin{background:#eff6ff;color:#1d4ed8;border:1px solid rgba(37,99,235,.28)}
.seo-mon-portal.is-cliente{background:#fff7ed;color:#c2410c;border:1px solid rgba(234,88,12,.28)}
.seo-mon-badge{display:inline-block;padding:.15rem .5rem;border-radius:999px;font-size:.68rem;font-weight:800}
.seo-mon-badge-pending{background:#fef3c7;color:#92400e}
.seo-mon-badge-working{background:#dbeafe;color:#1e40af}
.seo-mon-badge-done{background:#dcfce7;color:#166534}
.seo-mon-badge-failed{background:#fee2e2;color:#991b1b}
.seo-mon-ba{display:grid;grid-template-columns:1fr 1fr;gap:.55rem}
@media(max-width:700px){.seo-mon-ba{grid-template-columns:1fr}}
.seo-mon-ba > div{padding:.5rem .6rem;border-radius:10px;background:#f8fafc;font-size:.78rem;border:1px solid rgba(15,23,42,.06)}
.seo-mon-ba strong{display:block;font-size:.66rem;text-transform:uppercase;color:#64748b;margin-bottom:.2rem}
.seo-mon-ba .is-before{border-left:3px solid #f59e0b}
.seo-mon-ba .is-after{border-left:3px solid #16a34a}
.seo-mon-ba p{margin:0;white-space:pre-wrap}
.seo-mon-ba-exec{margin-top:.45rem}
.seo-mon-ba-exec > div{background:#f0fdf4}
.seo-mon-preview-detail{margin:.65rem 0;padding:.65rem .75rem;border-radius:10px;background:#fffbeb;border:1px solid #fde68a;font-size:.82rem;white-space:pre-wrap;line-height:1.45;color:#78350f;max-height:260px;overflow:auto}
.seo-mon-preview-rationale{margin:.65rem 0;padding:.65rem .75rem;border-radius:10px;background:#eff6ff;border:1px solid #bfdbfe;font-size:.82rem;line-height:1.45;color:#1e3a8a}
.seo-mon-preview-html{margin-top:.65rem;padding:.75rem;border:1px solid #e2e8f0;border-radius:10px;background:#fff;max-height:360px;overflow:auto;font-size:.86rem;line-height:1.5}
.seo-mon-preview-meta{font-size:.8rem;color:#475569;margin:.35rem 0}
.seo-mon-preview-fields{display:grid;gap:.35rem;margin:.5rem 0;font-size:.82rem}
.seo-mon-preview-fields div{padding:.4rem .55rem;background:#f8fafc;border-radius:8px}
.seo-mon-edit{margin-top:.85rem;padding:.75rem .85rem;border-radius:12px;border:1px solid rgba(37,99,235,.25);background:#f8fafc}
.seo-mon-edit h5{margin:0 0 .45rem;font-size:.88rem;font-weight:800;color:#1e3a8a}
.seo-mon-edit p.hint{margin:0 0 .55rem;font-size:.76rem;color:#64748b;line-height:1.4}
.seo-mon-edit label{display:block;font-size:.7rem;font-weight:800;text-transform:uppercase;color:#64748b;margin:.45rem 0 .2rem}
.seo-mon-edit input,.seo-mon-edit textarea,.seo-mon-edit select{width:100%;padding:.45rem .55rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.82rem;background:#fff}
.seo-mon-edit textarea{min-height:72px;font-family:inherit;line-height:1.4}
.seo-mon-edit textarea.is-code{font-family:ui-monospace,Consolas,monospace;font-size:.74rem;min-height:110px}
.seo-mon-edit-actions{display:flex;flex-wrap:wrap;gap:.45rem;margin-top:.65rem}
.seo-mon-chat{margin:.7rem 0 0;padding:.65rem .7rem;border-radius:10px;border:1px solid rgba(15,118,110,.28);background:#f0fdfa}
.seo-mon-chat h6{margin:0 0 .35rem;font-size:.78rem;font-weight:800;color:#115e59}
.seo-mon-chat-log{max-height:220px;overflow:auto;margin:0 0 .5rem;padding:.35rem;display:flex;flex-direction:column;gap:.4rem}
.seo-mon-chat-msg{padding:.4rem .55rem;border-radius:8px;font-size:.8rem;line-height:1.4}
.seo-mon-chat-msg.is-user{background:#fff;border:1px solid #99f6e4;align-self:flex-end;max-width:92%}
.seo-mon-chat-msg.is-assistant{background:#ccfbf1;border:1px solid #5eead4;align-self:flex-start;max-width:96%}
.seo-mon-chat-msg small{display:block;font-size:.65rem;color:#64748b;margin-bottom:.15rem}
.seo-mon-clar-list{margin:.4rem 0 .55rem;padding:0;list-style:none;max-height:160px;overflow:auto}
.seo-mon-clar-list li{padding:.4rem .55rem;border-radius:8px;background:#fff;border:1px solid #e2e8f0;margin-bottom:.35rem;font-size:.78rem;color:#334155}
.seo-mon-clar-list small{display:block;color:#94a3b8;font-size:.68rem;margin-bottom:.15rem}
</style>

<script>
(function () {
  var apiUrl = <?= json_encode($monitorApiUrl, JSON_UNESCAPED_UNICODE) ?>;
  var designPreviewBase = <?= json_encode($designPreviewBaseUrl, JSON_UNESCAPED_UNICODE) ?>;
  var flash = document.getElementById('seoMonStatus');
  var opsStatus = document.getElementById('seoMonOpsStatus');
  var previewBody = document.getElementById('seoMonPreviewBody');
  var applyBtn = document.getElementById('seoMonApplyBtn');
  var rejectBtn = document.getElementById('seoMonRejectBtn');
  var workingBtn = document.getElementById('seoMonWorkingBtn');
  var currentId = 0;
  var previewed = false;
  var currentProposal = null;

  function showFlash(msg, isErr, opts) {
    opts = opts || {};
    var state = opts.state || (isErr ? 'err' : 'ok');
    if (flash) {
      flash.hidden = false;
      flash.classList.toggle('is-err', !!isErr || state === 'err');
      flash.textContent = msg || '';
    }
    if (opsStatus) {
      opsStatus.hidden = false;
      opsStatus.classList.remove('is-busy', 'is-ok', 'is-err');
      if (state === 'busy') opsStatus.classList.add('is-busy');
      else if (state === 'err' || isErr) opsStatus.classList.add('is-err');
      else opsStatus.classList.add('is-ok');
      var title = opts.title || (state === 'busy' ? 'Ejecutando…' : (isErr ? 'No se completó' : 'Listo'));
      var filesHtml = '';
      if (opts.files && opts.files.length) {
        filesHtml = '<span class="seo-ops-files">Archivos: ' + opts.files.map(esc).join(', ') + '</span>';
      }
      opsStatus.innerHTML = '<span class="seo-ops-title">' + esc(title) + '</span>'
        + '<span>' + esc(msg || '') + '</span>' + filesHtml;
      try { opsStatus.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); } catch (e) {}
    }
  }

  function setBtnBusy(btn, busyLabel) {
    if (!btn) return;
    if (!btn.getAttribute('data-label')) {
      var lab = btn.querySelector('.seo-ux-btn-label');
      btn.setAttribute('data-label', lab ? lab.textContent : btn.textContent.trim());
      btn.setAttribute('data-html', btn.innerHTML);
    }
    btn.disabled = true;
    btn.classList.add('is-busy');
    btn.classList.remove('is-ok', 'is-err');
    var label = busyLabel || 'Ejecutando…';
    btn.innerHTML = '<i class="fas fa-spinner fa-spin seo-ux-btn-spinner" aria-hidden="true"></i> '
      + '<span class="seo-ux-btn-label">' + esc(label) + '</span>';
  }

  function setBtnDone(btn, ok, doneLabel) {
    if (!btn) return;
    btn.classList.remove('is-busy');
    btn.classList.toggle('is-ok', !!ok);
    btn.classList.toggle('is-err', !ok);
    var restore = btn.getAttribute('data-html') || btn.innerHTML;
    var label = doneLabel || (ok ? 'Hecho' : 'Error');
    btn.innerHTML = '<i class="fas ' + (ok ? 'fa-check' : 'fa-exclamation-triangle') + '" aria-hidden="true"></i> '
      + '<span class="seo-ux-btn-label">' + esc(label) + '</span>';
    setTimeout(function () {
      btn.disabled = false;
      btn.classList.remove('is-ok', 'is-err', 'is-busy');
      btn.innerHTML = restore;
    }, ok ? 2200 : 3200);
  }
  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function openModal() {
    if (window.jQuery && jQuery.fn.modal) {
      jQuery('#seoMonPreviewModal').modal('show');
    } else {
      var m = document.getElementById('seoMonPreviewModal');
      if (m) { m.style.display = 'block'; m.classList.add('show'); }
    }
  }
  function setActionButtons(p) {
    var canApply = !!(p && p.can_apply);
    var st = (p && p.work_status) || '';
    var after = (p && (p.after_raw || p.after)) || {};
    var isRehab = !!(p && (
      p.queue === 'rehab'
      || (p.target_key && String(p.target_key).indexOf('rehab:') === 0)
      || after.manual_ai
      || after.brain_owned
    ));
    if (applyBtn) {
      applyBtn.disabled = !canApply;
      if (isRehab) {
        applyBtn.textContent = 'Marcar rehab hecha (sin tocar archivos)';
        applyBtn.title = 'Cierra la tanda en el cerebro. No modifica diseño ni código del sitio.';
      } else {
        applyBtn.textContent = 'Aprobar e implementar en el sitio';
        applyBtn.title = 'Escribe los cambios en el sitio público';
      }
    }
    if (rejectBtn) rejectBtn.disabled = !canApply;
    if (workingBtn) {
      workingBtn.disabled = !(canApply && st !== 'working');
      workingBtn.style.display = canApply ? '' : 'none';
    }
  }

  function field(label, name, value, opts) {
    opts = opts || {};
    var tag = opts.area ? 'textarea' : 'input';
    var cls = opts.code ? ' class="is-code"' : '';
    var rows = opts.rows ? ' rows="' + opts.rows + '"' : '';
    var type = opts.area ? '' : ' type="text"';
    var html = '<label for="seoMonF_' + esc(name) + '">' + esc(label) + '</label>';
    if (opts.area) {
      html += '<textarea id="seoMonF_' + esc(name) + '" data-field="' + esc(name) + '"' + cls + rows + '>'
        + esc(value == null ? '' : value) + '</textarea>';
    } else {
      html += '<input id="seoMonF_' + esc(name) + '" data-field="' + esc(name) + '"' + type
        + ' value="' + esc(value == null ? '' : value) + '">';
    }
    return html;
  }

  function buildEditPanel(p) {
    if (!p || !p.can_edit) return '';
    var after = p.after || {};
    var kind = p.kind || '';
    var html = '<div class="seo-mon-edit" id="seoMonEditPanel">';
    html += '<h5>Chat, aclaraciones y edición (antes de implementar)</h5>';
    html += '<p class="hint">El chat aplica correcciones al borrador (SEO, diseño, copy, parches). Nada se publica hasta «Aprobar e implementar».</p>';

    var chat = Array.isArray(p.refine_chat) ? p.refine_chat : (Array.isArray(after.refine_chat) ? after.refine_chat : []);
    html += '<div class="seo-mon-chat" id="seoMonProposalChat">';
    html += '<h6><i class="fas fa-comments"></i> Chat de la propuesta</h6>';
    html += '<p class="hint" style="margin-bottom:.4rem">Pide correcciones concretas. La IA actualiza el borrador.</p>';
    html += '<div class="seo-mon-chat-log" id="seoMonChatLog">';
    if (chat.length) {
      chat.forEach(function (m) {
        var role = (m && m.role) || '';
        var cls = role === 'assistant' ? 'is-assistant' : 'is-user';
        var who = role === 'assistant' ? 'IA (cerebro)' : 'Tú';
        html += '<div class="seo-mon-chat-msg ' + cls + '"><small>' + esc(who)
          + (m.at ? (' · ' + esc(m.at)) : '') + '</small>' + esc(m.content || '') + '</div>';
      });
    } else {
      html += '<p class="hint" style="margin:0">Sin mensajes aún. Ej.: «Acorta el H1», «Enfoca León Gto.», «Corrige el parche CSS del hero».</p>';
    }
    html += '</div>';
    html += '<label for="seoMonChatInput">Mensaje al cerebro</label>';
    html += '<textarea id="seoMonChatInput" rows="3" placeholder="Ej. Corrige el título SEO, refuerza intención local y actualiza el parche del hero sin inventar precios."></textarea>';
    html += '<div class="seo-mon-edit-actions">';
    html += '<button type="button" class="btn btn-success btn-sm" id="seoMonChatSendBtn"><i class="fas fa-paper-plane"></i> Enviar y aplicar corrección al borrador</button>';
    html += '</div></div>';

    var clar = Array.isArray(p.clarifications) ? p.clarifications : [];
    html += '<hr style="margin:.85rem 0;border:0;border-top:1px solid #e2e8f0">';
    html += '<strong style="font-size:.72rem;color:#64748b;text-transform:uppercase">Aclaraciones (nota fija)</strong>';
    if (clar.length) {
      html += '<ul class="seo-mon-clar-list">';
      clar.forEach(function (c) {
        html += '<li><small>' + esc(c.at || '') + (c.by ? (' · usuario #' + c.by) : '') + '</small>'
          + esc(c.text || '') + '</li>';
      });
      html += '</ul>';
    } else {
      html += '<p class="hint">Aún no hay aclaraciones fijas.</p>';
    }
    html += '<label for="seoMonClarNote">Nueva aclaración (sin IA)</label>';
    html += '<textarea id="seoMonClarNote" rows="2" placeholder="Nota registrada; el chat de arriba sí aplica correcciones con IA."></textarea>';
    html += '<div class="seo-mon-edit-actions">';
    html += '<button type="button" class="btn btn-outline-primary btn-sm" id="seoMonClarSaveBtn"><i class="fas fa-sticky-note"></i> Guardar aclaración</button>';
    html += '</div>';

    html += '<hr style="margin:.85rem 0;border:0;border-top:1px solid #e2e8f0">';
    html += '<strong style="font-size:.72rem;color:#64748b;text-transform:uppercase">Editar propuesta a mano</strong>';
    html += field('Título de la solicitud', 'meta_title', p.title || '');
    html += field('URL destino', 'meta_url', p.url || '');
    html += field('Fundamento (rationale)', 'rationale', p.rationale || after.rationale || '', { area: true, rows: 3 });

    if ((p.content_type === 'page') || kind === 'hub_text') {
      html += field('Title SEO', 'title', after.title || '', { area: true, rows: 2 });
      html += field('H1', 'h1', after.h1 || '');
      html += field('Meta description', 'description', after.description || '', { area: true, rows: 2 });
      html += field('Hero / subtítulo', 'hero_subtitle', after.hero_subtitle || '', { area: true, rows: 2 });
    } else if (p.content_type === 'blog' || kind === 'blog_post' || kind === 'blog_improve') {
      html += field('Slug', 'slug', after.slug || '');
      html += field('Title', 'title', after.title || '', { area: true, rows: 2 });
      html += field('Excerpt', 'excerpt', after.excerpt || '', { area: true, rows: 2 });
      html += field('Keyword', 'keyword', after.keyword || '');
      html += field('HTML del artículo', 'html', after.html || '', { area: true, rows: 8, code: true });
    } else if (kind === 'design_ui' || p.content_type === 'design') {
      html += field('Resumen', 'summary', after.summary || '', { area: true, rows: 2 });
      html += field('Preview HTML (opcional)', 'preview_html', after.preview_html || '', { area: true, rows: 5, code: true });
      html += field('Parches JSON [{path,search,replace}]', 'patches_json', JSON.stringify(after.patches || [], null, 2), { area: true, rows: 8, code: true });
    } else if (kind === 'audit_fix') {
      html += field('Título hallazgo', 'title', after.title || p.title || '');
      html += field('Corrección planificada', 'correction', after.correction || '', { area: true, rows: 3 });
    } else if (kind === 'external_task' || kind === 'external_rec' || (p.content_type === 'external')) {
      html += field('Canal (GSC/GBP/…)', 'channel', after.channel || '', { area: false });
      html += field('Evidencia', 'evidence', after.evidence || '', { area: true, rows: 3 });
      html += field('Plan / corrección operativa', 'correction', after.correction || '', { area: true, rows: 4 });
      html += field('Resumen', 'summary', after.summary || '', { area: true, rows: 2 });
    } else {
      // site_patch, admin_patch, cliente_patch, validate leftovers, etc.
      html += field('Resumen', 'summary', after.summary || '', { area: true, rows: 2 });
      html += field('Archivo (path)', 'path', after.path || p.target_key || '');
      html += field('Buscar (search)', 'search', after.search || '', { area: true, rows: 4, code: true });
      html += field('Reemplazar por (replace)', 'replace', after.replace || '', { area: true, rows: 4, code: true });
      if (kind === 'admin_patch' || kind === 'cliente_patch') {
        html += field('Tipo de cambio', 'change_type', after.change_type || '');
        html += field('Congruencia', 'congruence', after.congruence || '', { area: true, rows: 2 });
      }
    }

    html += '<div class="seo-mon-edit-actions">';
    html += '<button type="button" class="btn btn-primary btn-sm" id="seoMonEditSaveBtn"><i class="fas fa-save"></i> Guardar cambios en la propuesta</button>';
    html += '</div></div>';
    return html;
  }

  function collectEditPayload() {
    var panel = document.getElementById('seoMonEditPanel');
    if (!panel) return null;
    var payload = { proposal_id: currentId, after: {} };
    var titleEl = panel.querySelector('[data-field="meta_title"]');
    var urlEl = panel.querySelector('[data-field="meta_url"]');
    if (titleEl) payload.title = titleEl.value.trim();
    if (urlEl) payload.target_url = urlEl.value.trim();
    panel.querySelectorAll('[data-field]').forEach(function (el) {
      var key = el.getAttribute('data-field') || '';
      if (!key || key === 'meta_title' || key === 'meta_url') return;
      if (key === 'patches_json') {
        try {
          payload.after.patches = JSON.parse(el.value || '[]');
        } catch (e) {
          payload._patches_error = 'JSON de parches inválido';
        }
        return;
      }
      payload.after[key] = el.value;
    });
    return payload;
  }

  function saveClarification() {
    if (!apiUrl || !currentId) return;
    var ta = document.getElementById('seoMonClarNote');
    var note = ta ? ta.value.trim() : '';
    if (note.length < 3) {
      showFlash('Escribe una aclaración (mín. 3 caracteres).', true);
      return;
    }
    var btn = document.getElementById('seoMonClarSaveBtn');
    if (btn) btn.disabled = true;
    fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'add_clarification', proposal_id: currentId, note: note })
    }).then(function (r) { return r.json(); }).then(function (data) {
      if (btn) btn.disabled = false;
      if (!data || !data.ok) {
        showFlash((data && data.error) || 'No se guardó la aclaración', true);
        return;
      }
      showFlash(data.message || 'Aclaración guardada', false);
      if (data.proposal) renderProposal(data.proposal);
    }).catch(function () {
      if (btn) btn.disabled = false;
      showFlash('Error de red al guardar aclaración', true);
    });
  }

  function sendProposalChat() {
    if (!apiUrl || !currentId) return;
    var ta = document.getElementById('seoMonChatInput');
    var message = ta ? ta.value.trim() : '';
    if (message.length < 3) {
      showFlash('Escribe el mensaje del chat (mín. 3 caracteres).', true);
      return;
    }
    var btn = document.getElementById('seoMonChatSendBtn');
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Aplicando corrección…';
    }
    showFlash('La IA está corrigiendo el borrador de la propuesta…', false);
    fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'refine_proposal_chat', proposal_id: currentId, message: message })
    }).then(function (r) { return r.json(); }).then(function (data) {
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar y aplicar corrección al borrador';
      }
      if (!data || !data.ok) {
        showFlash((data && data.error) || 'No se pudo corregir la propuesta', true);
        return;
      }
      showFlash((data.reply ? (data.reply + ' — ') : '') + (data.message || 'Borrador actualizado'), false);
      if (data.proposal) renderProposal(data.proposal);
    }).catch(function () {
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar y aplicar corrección al borrador';
      }
      showFlash('Error de red en el chat de la propuesta (timeout o falló la IA)', true);
    });
  }

  function saveProposalEdits() {
    if (!apiUrl || !currentId) return;
    var payload = collectEditPayload();
    if (!payload) return;
    if (payload._patches_error) {
      showFlash(payload._patches_error, true);
      return;
    }
    delete payload._patches_error;
    payload.action = 'update_proposal';
    var btn = document.getElementById('seoMonEditSaveBtn');
    if (btn) btn.disabled = true;
    fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).then(function (r) { return r.json(); }).then(function (data) {
      if (btn) btn.disabled = false;
      if (!data || !data.ok) {
        showFlash((data && data.error) || 'No se guardaron los cambios', true);
        return;
      }
      showFlash(data.message || 'Propuesta actualizada', false);
      if (data.proposal) renderProposal(data.proposal);
    }).catch(function () {
      if (btn) btn.disabled = false;
      showFlash('Error de red al guardar edición', true);
    });
  }

  function renderProposal(p) {
    if (!p || !previewBody) return;
    currentId = parseInt(p.id || 0, 10);
    currentProposal = p;
    previewed = true;
    setActionButtons(p);
    var title = document.getElementById('seoMonPreviewTitle');
    var typeLabel = p.type_label || p.content_label || 'Propuesta';
    var portalLabel = p.portal_label || (
      p.portal === 'admin' ? 'adm.conlineweb.com'
        : (p.portal === 'cliente' ? 'cliente.conlineweb.com' : 'conlineweb.com')
    );
    var portalKey = p.portal === 'admin' ? 'admin' : (p.portal === 'cliente' ? 'cliente' : 'site');
    if (title) title.textContent = 'Detalle · ' + typeLabel;
    var after = p.after || {};
    var before = p.before || {};
    var html = '';
    html += '<p><span class="seo-mon-portal is-' + esc(portalKey) + '">' + esc(portalLabel) + '</span> ';
    html += '<span class="seo-mon-tag is-' + esc(p.content_type || '') + '">' + esc(typeLabel) + '</span> ';
    if (p.subtype_label) {
      html += '<span class="seo-mon-tag is-fix">' + esc(p.subtype_label) + '</span> ';
    }
    html += '<span class="seo-mon-badge seo-mon-badge-' + esc(p.work_status || '') + '">' + esc(p.work_label || '') + '</span>';
    html += '</p>';
    html += '<h4 style="font-weight:800">' + esc(p.title || '') + '</h4>';
    html += '<p class="seo-mon-preview-meta">';
    html += '<strong>Portal:</strong> ' + esc(portalLabel);
    html += ' · <strong>Generada:</strong> ' + esc(p.created_at || '—');
    html += ' · <strong>Implementada:</strong> ' + esc(p.applied_at || '—');
    html += '</p>';
    var urlLabel = p.url_label || (p.url_kind === 'new_page' ? 'URL nueva' : 'URL donde se aplica');
    if (p.url) {
      html += '<p class="seo-mon-preview-meta"><strong>' + esc(urlLabel) + ':</strong> <a href="'
        + esc(p.url) + '" target="_blank" rel="noopener">' + esc(p.url) + '</a></p>';
    } else {
      html += '<p class="seo-mon-preview-meta" style="color:#b45309"><strong>URL:</strong> falta en la propuesta</p>';
    }
    if (p.rationale) {
      html += '<div class="seo-mon-preview-rationale"><strong>Fundamento</strong><br>' + esc(p.rationale) + '</div>';
    }
    if (p.detail) {
      html += '<div class="seo-mon-preview-detail"><strong>Detalle</strong>\n' + esc(p.detail) + '</div>';
    } else if (!p.rationale && (p.before_summary || p.after_summary)) {
      html += '<div class="seo-mon-preview-detail"><strong>Detalle</strong>\nSin detalle ampliado en esta propuesta.</div>';
    }
    html += '<div class="seo-mon-ba"><div class="is-before"><strong>Antes (propuesta)</strong><p>' + esc(p.before_summary || '—') + '</p></div>';
    html += '<div class="is-after"><strong>Después (propuesta)</strong><p>' + esc(p.after_summary || '—') + '</p></div></div>';
    if (p.executed_before_summary || p.executed_after_summary) {
      html += '<div class="seo-mon-ba seo-mon-ba-exec" style="margin-top:.55rem">';
      html += '<div class="is-before"><strong>Antes (ejecutado)</strong><p>' + esc(p.executed_before_summary || '—') + '</p></div>';
      html += '<div class="is-after"><strong>Después (ejecutado)</strong><p>' + esc(p.executed_after_summary || '—') + '</p></div>';
      html += '</div>';
    }
    if ((p.kind || '') === 'admin_patch' || (p.kind || '') === 'cliente_patch') {
      var fileLabel = (p.kind || '') === 'cliente_patch' ? 'Archivo cliente' : 'Archivo admin';
      html += '<div class="seo-mon-preview-fields">';
      html += '<div><strong>Vista:</strong> ' + esc(after.view_label || after.view_key || '') + '</div>';
      html += '<div><strong>Propósito de la vista:</strong> ' + esc(after.view_purpose || '') + '</div>';
      html += '<div><strong>Congruencia:</strong> ' + esc(after.congruence || '') + '</div>';
      html += '<div><strong>Tipo de cambio:</strong> ' + esc(after.change_type || p.type_label || '') + '</div>';
      html += '<div><strong>' + esc(fileLabel) + ':</strong> <code>' + esc(after.path || p.target_key || '') + '</code></div>';
      html += '</div>';
      html += '<div class="seo-mon-ba" style="margin-top:.45rem"><div class="is-before"><strong>Buscar</strong><p>' + esc(after.search || '—') + '</p></div>';
      html += '<div class="is-after"><strong>Reemplazar por</strong><p>' + esc(after.replace || '—') + '</p></div></div>';
    }
    if (p.content_type === 'design' || (p.kind || '') === 'design_ui') {
      html += '<div class="seo-mon-preview-fields">';
      html += '<div><strong>Nivel:</strong> ' + esc(p.reinvention_level || after.reinvention_level || 'evolve') + '</div>';
      html += '<div><strong>Alcance:</strong> ' + esc(p.design_scope || after.scope || 'section') + '</div>';
      html += '<div><strong>Parches al aprobar:</strong> ' + esc(String(p.patches_count != null ? p.patches_count : (after.patches ? after.patches.length : 0))) + '</div>';
      html += '<div><strong>Archivo:</strong> <code>' + esc(after.path || p.target_key || '') + '</code></div>';
      html += '<div><strong>Resumen:</strong> ' + esc(after.summary || '') + '</div>';
      html += '</div>';
      var patches = Array.isArray(after.patches) ? after.patches : [];
      if (patches.length) {
        html += '<div style="margin-top:.55rem"><strong style="font-size:.78rem;color:#64748b">Parches (search → replace)</strong></div>';
        patches.forEach(function (pt, idx) {
          html += '<div class="seo-mon-preview-fields" style="margin-top:.35rem;border:1px solid #e2e8f0;border-radius:10px;padding:.55rem">';
          html += '<div><strong>#' + (idx + 1) + ' Archivo:</strong> <code>' + esc((pt && pt.path) || '') + '</code></div>';
          html += '<div class="seo-mon-ba" style="margin-top:.35rem"><div class="is-before"><strong>Buscar</strong><pre style="white-space:pre-wrap;margin:.25rem 0 0;font-size:.74rem">' + esc((pt && pt.search) || '—') + '</pre></div>';
          html += '<div class="is-after"><strong>Reemplazar por</strong><pre style="white-space:pre-wrap;margin:.25rem 0 0;font-size:.74rem">' + esc((pt && pt.replace) || '—') + '</pre></div></div>';
          html += '</div>';
        });
      } else {
        html += '<p class="seo-mon-preview-meta" style="color:#b45309"><strong>Sin parches de archivo:</strong> al implementar solo se registra la dirección visual; el sitio no cambia. Regenera la propuesta con path <code>desarrollo-de-software.php</code> (archivo, no carpeta).</p>';
      }
      var prevUrl = designPreviewBase + (designPreviewBase.indexOf('?') >= 0 ? '&' : '?') + 'id=' + encodeURIComponent(p.id || currentId);
      html += '<p class="seo-mon-preview-meta"><a href="' + esc(prevUrl) + '" target="_blank" rel="noopener"><strong>Abrir preview en pestaña nueva</strong></a></p>';
      html += '<iframe class="seo-mon-design-frame" title="Preview diseño" src="' + esc(prevUrl) + '"></iframe>';
      html += '<p class="seo-mon-preview-meta" style="color:#0e7490"><strong>Paso obligatorio:</strong> analiza el preview. Solo implementa si la propuesta es viable y alineada al design system.</p>';
    }
    if (p.content_type === 'page') {
      html += '<div class="seo-mon-preview-fields">';
      html += '<div><strong>Title:</strong> ' + esc(after.title || '') + '</div>';
      html += '<div><strong>H1:</strong> ' + esc(after.h1 || '') + '</div>';
      html += '<div><strong>Meta:</strong> ' + esc(after.description || '') + '</div>';
      html += '<div><strong>Hero:</strong> ' + esc(after.hero_subtitle || '') + '</div>';
      html += '</div>';
    }
    if (p.content_type === 'blog') {
      html += '<div class="seo-mon-preview-fields">';
      html += '<div><strong>Slug:</strong> ' + esc(after.slug || '') + '</div>';
      html += '<div><strong>Excerpt:</strong> ' + esc(after.excerpt || '') + '</div>';
      html += '<div><strong>Keyword:</strong> ' + esc(after.keyword || '') + '</div>';
      html += '</div>';
      if (p.preview_html) {
        html += '<strong style="font-size:.78rem;color:#64748b">Contenido HTML (vista previa)</strong>';
        html += '<div class="seo-mon-preview-html">' + p.preview_html + '</div>';
      }
    } else if ((p.kind || '') === 'site_patch' || after.path) {
      html += '<div class="seo-mon-preview-fields">';
      html += '<div><strong>Archivo:</strong> ' + esc(after.path || p.target_key || '') + '</div>';
      html += '<div><strong>Tipo:</strong> ' + esc(after.change_type || 'copy') + '</div>';
      html += '<div><strong>Resumen:</strong> ' + esc(after.summary || '') + '</div>';
      html += '<div><strong>Buscar:</strong><pre style="white-space:pre-wrap;margin:.25rem 0 0;font-size:.78rem">' + esc(after.search || '') + '</pre></div>';
      html += '<div><strong>Reemplazar por:</strong><pre style="white-space:pre-wrap;margin:.25rem 0 0;font-size:.78rem">' + esc(after.replace || '') + '</pre></div>';
      html += '</div>';
    }
    if (p.can_apply) {
      html += '<p class="seo-mon-preview-meta" style="margin-top:.75rem;color:#92400e"><strong>Importante:</strong> aún no está en el sitio. Usa el chat para corregir el borrador, o edita a mano; al implementar se guarda el antes/después real.</p>';
      html += buildEditPanel(p);
    } else if (p.apply_log) {
      html += '<p class="seo-mon-preview-meta" style="margin-top:.75rem"><strong>Log:</strong> ' + esc(p.apply_log) + '</p>';
      var clarDone = Array.isArray(p.clarifications) ? p.clarifications : [];
      var chatDone = Array.isArray(p.refine_chat) ? p.refine_chat : [];
      if (chatDone.length) {
        html += '<div class="seo-mon-edit"><h5>Chat de la propuesta (histórico)</h5><div class="seo-mon-chat-log">';
        chatDone.forEach(function (m) {
          var cls = (m.role === 'assistant') ? 'is-assistant' : 'is-user';
          html += '<div class="seo-mon-chat-msg ' + cls + '"><small>' + esc(m.at || '') + '</small>'
            + esc(m.content || '') + '</div>';
        });
        html += '</div></div>';
      }
      if (clarDone.length) {
        html += '<div class="seo-mon-edit"><h5>Aclaraciones (histórico)</h5><ul class="seo-mon-clar-list">';
        clarDone.forEach(function (c) {
          html += '<li><small>' + esc(c.at || '') + '</small>' + esc(c.text || '') + '</li>';
        });
        html += '</ul></div>';
      }
    }
    previewBody.innerHTML = html;
    var clarBtn = document.getElementById('seoMonClarSaveBtn');
    var editBtn = document.getElementById('seoMonEditSaveBtn');
    var chatBtn = document.getElementById('seoMonChatSendBtn');
    if (clarBtn) clarBtn.addEventListener('click', saveClarification);
    if (editBtn) editBtn.addEventListener('click', saveProposalEdits);
    if (chatBtn) chatBtn.addEventListener('click', sendProposalChat);
    var chatLogEl = document.getElementById('seoMonChatLog');
    if (chatLogEl) chatLogEl.scrollTop = chatLogEl.scrollHeight;
    openModal();
  }

  function loadProposal(id) {
    if (!apiUrl || !id) return;
    previewed = false;
    currentId = id;
    setActionButtons({ can_apply: false, work_status: '' });
    if (previewBody) previewBody.innerHTML = '<p class="text-muted">Cargando detalle…</p>';
    openModal();
    fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'get_proposal', proposal_id: id })
    }).then(function (r) { return r.json(); }).then(function (data) {
      if (!data || !data.ok || !data.proposal) {
        showFlash((data && data.error) || 'No se pudo cargar la propuesta', true);
        if (previewBody) previewBody.innerHTML = '<p class="text-danger">Error al cargar.</p>';
        return;
      }
      renderProposal(data.proposal);
      showFlash('Detalle listo. Revisa y decide si implementar.', false);
    }).catch(function () {
      showFlash('Error de red al cargar propuesta', true);
    });
  }

  function markWorking(id) {
    if (!apiUrl || !id) return;
    if (workingBtn) workingBtn.disabled = true;
    fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'set_working', proposal_id: id })
    }).then(function (r) { return r.json(); }).then(function (data) {
      if (!data || !data.ok) {
        if (workingBtn) workingBtn.disabled = false;
        showFlash((data && data.error) || 'No se pudo marcar', true);
        return;
      }
      showFlash('Marcada en revisión', false);
      setTimeout(function () { location.reload(); }, 600);
    }).catch(function () {
      if (workingBtn) workingBtn.disabled = false;
      showFlash('Error de red al marcar en revisión', true);
    });
  }

  function closeGenModals() {
    if (typeof jQuery !== 'undefined' && jQuery.fn.modal) {
      jQuery('.seo-mon-gen-modal').modal('hide');
    }
  }

  function propose(action, body, btn) {
    if (!apiUrl || !btn) return;
    var prev = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = 'Generando…';
    showFlash('Generando propuesta con IA… no se escribe al sitio todavía.', false);
    fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(Object.assign({ action: action }, body || {}))
    }).then(function (r) { return r.json(); }).then(function (data) {
      btn.disabled = false;
      btn.innerHTML = prev;
      if (!data || !data.ok) {
        showFlash((data && data.error) || 'No se pudo generar', true);
        return;
      }
      closeGenModals();
      showFlash(data.message || 'Propuesta lista. Ábrela para revisarla.', false);
      if (data.proposal) {
        renderProposal(data.proposal);
      } else if (data.proposal_id) {
        loadProposal(data.proposal_id);
      }
    }).catch(function () {
      btn.disabled = false;
      btn.innerHTML = prev;
      showFlash('Error de red al generar', true);
    });
  }

  document.addEventListener('click', function (ev) {
    var previewEl = ev.target && ev.target.closest ? ev.target.closest('.seo-mon-preview-btn') : null;
    if (previewEl) {
      loadProposal(parseInt(previewEl.getAttribute('data-proposal-id') || '0', 10));
    }
  });
  if (workingBtn) {
    workingBtn.addEventListener('click', function () {
      markWorking(currentId);
    });
  }

  var pageBtn = document.getElementById('seoMonProposePageBtn');
  var blogBtn = document.getElementById('seoMonProposeBlogBtn');
  if (pageBtn) {
    pageBtn.addEventListener('click', function () {
      var city = (document.getElementById('seoMonCity') || {}).value || '';
      propose('propose_hub', { city: city }, pageBtn);
    });
  }
  if (blogBtn) {
    blogBtn.addEventListener('click', function () {
      var cat = (document.getElementById('seoMonBlogCat') || {}).value || 'seo';
      propose('propose_blog', { category: cat }, blogBtn);
    });
  }
  var improveBtn = document.getElementById('seoMonImproveBlogBtn');
  if (improveBtn) {
    improveBtn.addEventListener('click', function () {
      var slug = (document.getElementById('seoMonBlogImprove') || {}).value || '';
      if (!slug) {
        showFlash('Elige un artículo existente para mejorar.', true);
        return;
      }
      propose('propose_blog_improve', { slug: slug }, improveBtn);
    });
  }
  var maintainBtn = document.getElementById('seoMonMaintainBtn');
  if (maintainBtn) {
    maintainBtn.addEventListener('click', function () {
      var instruction = ((document.getElementById('seoMonMaintain') || {}).value || '').trim();
      var path = ((document.getElementById('seoMonMaintainPath') || {}).value || '').trim();
      if (instruction.length < 12) {
        showFlash('Describe el cambio de mantenimiento (mín. 12 caracteres).', true);
        return;
      }
      propose('propose_maintain', { instruction: instruction, path: path }, maintainBtn);
    });
  }
  var designBtn = document.getElementById('seoMonDesignBtn');
  if (designBtn) {
    designBtn.addEventListener('click', function () {
      var instruction = ((document.getElementById('seoMonDesign') || {}).value || '').trim();
      var path = ((document.getElementById('seoMonDesignPath') || {}).value || '').trim();
      var targetUrl = ((document.getElementById('seoMonDesignUrl') || {}).value || '').trim();
      if (instruction.length < 12) {
        showFlash('Describe la propuesta de diseño (mín. 12 caracteres).', true);
        return;
      }
      propose('propose_design', { instruction: instruction, path: path, target_url: targetUrl }, designBtn);
    });
  }
  var adminViewBtn = document.getElementById('seoMonAdminViewBtn');
  if (adminViewBtn) {
    adminViewBtn.addEventListener('click', function () {
      var view = (document.getElementById('seoMonAdminView') || {}).value || 'monitor';
      var instruction = ((document.getElementById('seoMonAdminInstr') || {}).value || '').trim();
      propose('propose_admin_view', { view: view, instruction: instruction }, adminViewBtn);
    });
  }
  var adminBatchBtn = document.getElementById('seoMonAdminBatchBtn');
  if (adminBatchBtn) {
    adminBatchBtn.addEventListener('click', function () {
      var instruction = ((document.getElementById('seoMonAdminInstr') || {}).value || '').trim();
      var prev = adminBatchBtn.innerHTML;
      adminBatchBtn.disabled = true;
      adminBatchBtn.innerHTML = 'Generando…';
      showFlash('IA analizando vistas admin (Monitor, Checklist, Chat)…', false);
      fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'propose_admin_views',
          views: ['monitor', 'checklist', 'chat'],
          instruction: instruction,
          max: 3
        })
      }).then(function (r) { return r.json(); }).then(function (data) {
        adminBatchBtn.disabled = false;
        adminBatchBtn.innerHTML = prev;
        if (!data || !data.ok) {
          showFlash((data && data.message) || (data && data.error) || 'No se generaron propuestas', true);
          return;
        }
        closeGenModals();
        showFlash(data.message || 'Propuestas admin listas', false);
        setTimeout(function () { location.reload(); }, 1000);
      }).catch(function () {
        adminBatchBtn.disabled = false;
        adminBatchBtn.innerHTML = prev;
        showFlash('Error de red al proponer vistas admin', true);
      });
    });
  }
  var clienteViewBtn = document.getElementById('seoMonClienteViewBtn');
  if (clienteViewBtn) {
    clienteViewBtn.addEventListener('click', function () {
      var view = (document.getElementById('seoMonClienteView') || {}).value || 'shell';
      var instruction = ((document.getElementById('seoMonClienteInstr') || {}).value || '').trim();
      propose('propose_cliente_view', { view: view, instruction: instruction }, clienteViewBtn);
    });
  }
  var clienteBatchBtn = document.getElementById('seoMonClienteBatchBtn');
  if (clienteBatchBtn) {
    clienteBatchBtn.addEventListener('click', function () {
      var instruction = ((document.getElementById('seoMonClienteInstr') || {}).value || '').trim();
      var prev = clienteBatchBtn.innerHTML;
      clienteBatchBtn.disabled = true;
      clienteBatchBtn.innerHTML = 'Generando…';
      showFlash('IA analizando portal cliente (Shell, Tickets, Mis sitios)…', false);
      fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'propose_cliente_views',
          views: ['shell', 'tickets', 'sitios'],
          instruction: instruction,
          max: 3
        })
      }).then(function (r) { return r.json(); }).then(function (data) {
        clienteBatchBtn.disabled = false;
        clienteBatchBtn.innerHTML = prev;
        if (!data || !data.ok) {
          showFlash((data && data.message) || (data && data.error) || 'No se generaron propuestas', true);
          return;
        }
        closeGenModals();
        showFlash(data.message || 'Propuestas cliente listas', false);
        setTimeout(function () { location.reload(); }, 1000);
      }).catch(function () {
        clienteBatchBtn.disabled = false;
        clienteBatchBtn.innerHTML = prev;
        showFlash('Error de red al proponer vistas cliente', true);
      });
    });
  }
  var brainSeedBtn = document.getElementById('seoMonBrainSeedBtn');
  if (brainSeedBtn) {
    brainSeedBtn.addEventListener('click', function () {
      setBtnBusy(brainSeedBtn, 'Actualizando conocimiento…');
      showFlash('Reforzando hechos del cerebro (misión, design system, SEO/GEO, multi-portal)…', false, {
        state: 'busy',
        title: 'Actualizando conocimiento base'
      });
      fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'brain_seed' })
      }).then(function (r) { return r.json(); }).then(function (data) {
        var ok = !!(data && data.ok);
        var msg = (data && data.message) || (data && data.error) || (ok ? 'Conocimiento actualizado' : 'No se pudo actualizar');
        showFlash(msg, !ok, {
          state: ok ? 'ok' : 'err',
          title: ok ? 'Conocimiento base actualizado' : 'Error en conocimiento base'
        });
        setBtnDone(brainSeedBtn, ok, ok ? 'Conocimiento listo' : 'Falló conocimiento');
      }).catch(function () {
        showFlash('Error de red al actualizar el conocimiento base', true, {
          state: 'err',
          title: 'Error de red'
        });
        setBtnDone(brainSeedBtn, false, 'Error de red');
      });
    });
  }

  if (applyBtn) {
    applyBtn.addEventListener('click', function () {
      if (!currentId || !previewed) {
        showFlash('Primero abre Detalle para revisar la propuesta.', true);
        return;
      }
      var after = (currentProposal && (currentProposal.after_raw || currentProposal.after)) || {};
      var isRehab = !!(currentProposal && (
        currentProposal.queue === 'rehab'
        || (currentProposal.target_key && String(currentProposal.target_key).indexOf('rehab:') === 0)
        || after.manual_ai
        || after.brain_owned
      ));
      var confirmMsg = isRehab
        ? '¿Marcar esta rehab como hecha?\n\nNO se modificarán archivos ni el diseño del sitio. Solo se cierra la tarea en el cerebro/cola.'
        : '¿Implementar esta propuesta en el código del sitio? Se hará backup.';
      if (!window.confirm(confirmMsg)) return;
      applyBtn.disabled = true;
      applyBtn.textContent = isRehab ? 'Cerrando rehab…' : 'Implementando…';
      fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'apply', proposal_id: currentId, previewed: true })
      }).then(function (r) { return r.json(); }).then(function (data) {
        if (!data || !data.ok) {
          applyBtn.disabled = false;
          setActionButtons(currentProposal || { can_apply: true });
          showFlash((data && data.error) || 'No se pudo implementar', true);
          return;
        }
        showFlash(data.message || (isRehab ? 'Rehab cerrada sin tocar el sitio.' : 'Implementado en el sitio. Recargando…'), false);
        setTimeout(function () { location.reload(); }, 900);
      }).catch(function () {
        applyBtn.disabled = false;
        setActionButtons(currentProposal || { can_apply: true });
        showFlash('Error de red al implementar', true);
      });
    });
  }
  if (rejectBtn) {
    rejectBtn.addEventListener('click', function () {
      if (!currentId) return;
      rejectBtn.disabled = true;
      fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'reject', proposal_id: currentId })
      }).then(function (r) { return r.json(); }).then(function (data) {
        if (!data || !data.ok) {
          rejectBtn.disabled = false;
          showFlash((data && data.error) || 'No se pudo rechazar', true);
          return;
        }
        showFlash('Propuesta rechazada. Recargando…', false);
        setTimeout(function () { location.reload(); }, 700);
      });
    });
  }

  var refreshBtn = document.getElementById('seoMonRefreshBtn');
  if (refreshBtn) refreshBtn.addEventListener('click', function () { location.reload(); });

  var syncDirBtn = document.getElementById('seoMonSyncDirBtn');
  if (syncDirBtn) {
    syncDirBtn.addEventListener('click', function () {
      setBtnBusy(syncDirBtn, 'Actualizando sitemaps…');
      showFlash('Regenerando sitemap.xml, sitemap-blog, sitemap-index y llms…', false, {
        state: 'busy',
        title: 'Actualizando sitemaps e índice'
      });
      fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'sync_directory' })
      }).then(function (r) { return r.json(); }).then(function (data) {
        var ok = !!(data && data.ok);
        var msg = (data && data.message) || (data && data.error) || (ok ? 'Sitemaps actualizados' : 'Sync falló');
        var files = (data && data.files) ? data.files : [];
        showFlash(msg, !ok, {
          state: ok ? 'ok' : 'err',
          title: ok ? 'Sitemap actualizado con éxito' : 'Sitemap no se pudo actualizar',
          files: files
        });
        setBtnDone(syncDirBtn, ok, ok ? 'Sitemaps OK' : 'Sitemap falló');
      }).catch(function () {
        showFlash('Error de red al sincronizar sitemaps/índice', true, {
          state: 'err',
          title: 'Error de red'
        });
        setBtnDone(syncDirBtn, false, 'Error de red');
      });
    });
  }

  var autonomyBtn = document.getElementById('seoMonAutonomyBtn');
  if (autonomyBtn) {
    autonomyBtn.addEventListener('click', function () {
      setBtnBusy(autonomyBtn, 'Generando propuestas…');
      showFlash('IA pensando temas y generando propuestas iniciales… (no publica)', false, {
        state: 'busy',
        title: 'Generando propuestas'
      });
      fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'autonomy_run',
          max_blogs: 2,
          max_hubs: 1,
          max_improves: 1,
          max_actions: 4
        })
      }).then(function (r) { return r.json(); }).then(function (data) {
        if (data && data.skipped) {
          showFlash(data.skipped, true, { state: 'err', title: 'Omitido' });
          setBtnDone(autonomyBtn, false, 'Omitido');
          return;
        }
        var n = (data && data.created && data.created.length) ? data.created.length : 0;
        var ok = !!(data && (data.ok || n > 0));
        showFlash((data && data.message) || (data && data.error) || 'Listo', !ok, {
          state: ok ? 'ok' : 'err',
          title: ok ? ('Propuestas creadas: ' + n) : 'No se crearon propuestas'
        });
        setBtnDone(autonomyBtn, ok, ok ? ('Creadas: ' + n) : 'Sin propuestas');
        if (n > 0) {
          setTimeout(function () { location.reload(); }, 1200);
        }
      }).catch(function () {
        showFlash('Error de red en autonomía IA', true, { state: 'err', title: 'Error de red' });
        setBtnDone(autonomyBtn, false, 'Error de red');
      });
    });
  }

  (function openGenModalFromQuery() {
    if (typeof jQuery === 'undefined' || !jQuery.fn.modal) return;
    var map = {
      hub: '#seoMonModalHub',
      blog: '#seoMonModalBlog',
      blog_improve: '#seoMonModalBlogImprove',
      maintain: '#seoMonModalMaintain',
      design: '#seoMonModalDesign',
      admin: '#seoMonModalAdmin',
      cliente: '#seoMonModalCliente'
    };
    var params = new URLSearchParams(window.location.search || '');
    var key = (params.get('open') || '').trim().toLowerCase();
    if (!key || !map[key]) return;
    jQuery(map[key]).modal('show');
  })();
})();
</script>
<script src="<?= adm_href('analytics/js/datatables-es.js') ?>"></script>
<script src="<?= adm_href('vendor/datatables/jquery.dataTables.min.js') ?>"></script>
<script src="<?= adm_href('vendor/datatables/dataTables.bootstrap4.min.js') ?>"></script>
<script>
(function () {
  if (typeof jQuery === 'undefined' || !jQuery.fn.DataTable) return;
  var $table = jQuery('#seoMonProposalsTable');
  if ($table.length && !jQuery.fn.DataTable.isDataTable($table)) {
    $table.DataTable({
      order: [[0, 'desc']],
      pageLength: 25,
      lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Todos']],
      columnDefs: [
        { orderable: false, targets: [7] },
        { className: 'text-nowrap', targets: [0, 2, 3, 4, 6] }
      ],
      language: window.VA_DT_LANG_ES || {},
      autoWidth: false
    });
  }

  var $rehab = jQuery('#seoMonRehabDt');
  if ($rehab.length && !jQuery.fn.DataTable.isDataTable($rehab)) {
    var rehabDt = $rehab.DataTable({
      order: [[1, 'asc']],
      pageLength: 25,
      lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Todos']],
      columnDefs: [
        { orderable: false, targets: [4] },
        { className: 'text-nowrap', targets: [1, 2, 3] }
      ],
      language: window.VA_DT_LANG_ES || {},
      autoWidth: false
    });
    jQuery.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
      if (settings.nTable !== $rehab[0]) return true;
      var bucket = jQuery(rehabDt.row(dataIndex).node()).attr('data-bucket') || '';
      var on = document.querySelector('#seoMonRehabTabs .seo-mon-rehab-tab.is-on');
      var want = on ? (on.getAttribute('data-rehab-tab') || 'hoy') : 'hoy';
      return bucket === want;
    });
    rehabDt.draw();
    jQuery('#seoMonRehabTabs').on('click', '.seo-mon-rehab-tab', function () {
      var btn = this;
      jQuery('#seoMonRehabTabs .seo-mon-rehab-tab').each(function () {
        var on = this === btn;
        this.classList.toggle('is-on', on);
        this.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      rehabDt.draw();
    });
  }
})();
</script>

</div>
</div>
