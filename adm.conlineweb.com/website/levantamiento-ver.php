<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__, 2) . '/includes/proyec_levantamiento_admin.php';
require_once __DIR__ . '/includes/proyec_admin_view_render.php';

cw_hub_migrate($conn);
proyec_migrate($conn);

$leadId = (int) ($_GET['lead_id'] ?? 0);
$lead = $leadId > 0 ? proyec_admin_load_lead_summary($conn, $leadId) : null;
$project = ($lead && $leadId > 0) ? proyec_admin_get_project_by_lead($conn, $leadId) : null;
$hasContent = $project && proyec_admin_project_has_content($project);

$user = getAuthenticatedUser();
$usuarioId = (int) ($user['id'] ?? 0);

if ($hasContent) {
    proyec_admin_mark_project_seen($conn, $leadId, $usuarioId);
}

$activeLink = ($leadId > 0) ? proyec_get_active_access($conn, $leadId) : null;
$formClientUrl = $activeLink ? proyec_form_url_by_token((string) $activeLink['token']) : '';

$summary = $hasContent ? proyec_admin_build_project_summary($project, $lead) : null;
$historial = $hasContent ? proyec_admin_recent_historial($conn, $leadId, 15) : [];
$steps = proyec_admin_step_titles();

$admPageTitle = 'Levantamiento de requerimientos';

include dirname(__DIR__, 2) . '/menu.php';
?>
<link href="<?= adm_href('website/css/leads-website.css') ?>?v=14" rel="stylesheet">

<div class="adm-page-shell">
<div class="container-fluid px-0 lw-proyec-view-page">
  <div class="lw-proyec-view-top">
    <div>
      <a href="<?= adm_href('website/leads.php') ?>" class="lw-proyec-back"><i class="bi bi-arrow-left"></i> Volver a leads</a>
      <h1 class="lw-proyec-view-title"><i class="bi bi-journal-text"></i> Levantamiento de requerimientos</h1>
      <?php if ($lead): ?>
      <p class="lw-proyec-view-sub">
        <?= proyec_admin_h($lead['contacto'] ?: $lead['empresa'] ?: 'Lead #' . $leadId) ?>
        · ID <?= (int) $leadId ?>
        <?php if (!empty($lead['correo'])): ?> · <?= proyec_admin_h($lead['correo']) ?><?php endif; ?>
      </p>
      <?php endif; ?>
    </div>
    <?php if ($hasContent && $formClientUrl !== ''): ?>
    <a href="<?= proyec_admin_h($formClientUrl) ?>" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener">
      <i class="bi bi-box-arrow-up-right"></i> Abrir formulario del cliente
    </a>
    <?php endif; ?>
  </div>

  <?php if (!$lead): ?>
  <div class="alert alert-warning">Lead no encontrado.</div>
  <?php elseif (!$hasContent): ?>
  <div class="alert alert-info">Este lead aún no tiene información capturada en el formulario de levantamiento.</div>
  <?php else: ?>
  <div class="lw-proyec-view-meta">
    <div class="lw-proyec-view-stat">
      <span class="lw-proyec-view-stat__label">Progreso</span>
      <strong><?= (int) ($summary['progreso_pct'] ?? 0) ?>%</strong>
      <small><?= proyec_admin_h((string) ($summary['paso_titulo'] ?? '')) ?></small>
    </div>
    <div class="lw-proyec-view-stat">
      <span class="lw-proyec-view-stat__label">Última actualización</span>
      <strong><?= proyec_admin_h(proyec_admin_format_datetime((string) ($summary['fecha_actualizacion'] ?? ''))) ?></strong>
      <small>Guardado en vivo</small>
    </div>
    <div class="lw-proyec-view-stat">
      <span class="lw-proyec-view-stat__label">Módulos documentados</span>
      <strong><?= (int) ($summary['modulos_activos'] ?? 0) ?> / <?= (int) ($summary['modulos_total'] ?? 12) ?></strong>
      <small>Secciones opcionales activas</small>
    </div>
    <div class="lw-proyec-view-stat">
      <span class="lw-proyec-view-stat__label">Estado</span>
      <strong><?= proyec_admin_h(ucfirst((string) ($summary['estado'] ?? 'borrador'))) ?></strong>
      <small>Proyecto #<?= (int) ($summary['project_id'] ?? 0) ?></small>
    </div>
  </div>

  <?php if ($historial !== []): ?>
  <details class="lw-proyec-history">
    <summary><i class="bi bi-clock-history"></i> Actividad reciente (<?= count($historial) ?>)</summary>
    <ul class="lw-proyec-history__list">
      <?php foreach ($historial as $h): ?>
      <li>
        <time><?= proyec_admin_h(proyec_admin_format_datetime((string) ($h['created_at'] ?? ''))) ?></time>
        <span><?= proyec_admin_h((string) ($h['resumen'] ?? '')) ?></span>
        <em><?= proyec_admin_h($steps[(int) ($h['paso'] ?? 0)] ?? '') ?></em>
      </li>
      <?php endforeach; ?>
    </ul>
  </details>
  <?php endif; ?>

  <div class="lw-proyec-view-content">
    <?= proyec_admin_render_full_project($project, $lead) ?>
  </div>
  <?php endif; ?>
</div>
</div>
