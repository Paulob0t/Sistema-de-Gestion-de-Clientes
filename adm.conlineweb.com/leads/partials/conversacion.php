<?php
/** @var array $lead @var array $timeline @var array $adjuntos @var bool $canEdit @var string $responsableNombre @var string $svc @var int $id */
$pipelineOpts = CW_HUB_PIPELINE;
$estado = $lead['pipeline_estado'] ?? 'nuevo';
$waDigits = preg_replace('/\D/', '', (string) ($lead['telefono'] ?? ''));
?>
<div class="crm-conversation" data-lead-id="<?= (int) $id ?>">
  <header class="crm-conv-header">
    <div class="crm-conv-head-main">
      <h2><?= htmlspecialchars($lead['nombre'] ?? '') ?></h2>
      <p class="crm-conv-sub">
        #<?= (int) $id ?>
        <?php if (!empty($lead['correo'])): ?> · <?= htmlspecialchars($lead['correo']) ?><?php endif; ?>
        <?php if (!empty($lead['telefono'])): ?> · <?= htmlspecialchars($lead['telefono']) ?><?php endif; ?>
      </p>
    </div>
    <div class="crm-conv-head-actions">
      <?php if ($canEdit): ?>
      <select class="crm-pipeline-select js-pipeline <?= cw_inbox_status_class($estado) ?>" data-id="<?= (int) $id ?>">
        <?php foreach ($pipelineOpts as $key => $label): ?>
        <option value="<?= htmlspecialchars($key) ?>" <?= $estado === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
        <?php endforeach; ?>
      </select>
      <?php else: ?>
      <span class="crm-status-pill <?= cw_inbox_status_class($estado) ?>"><?= htmlspecialchars(cw_inbox_pipeline_label($estado)) ?></span>
      <?php endif; ?>
      <?php if ($waDigits !== ''): ?>
      <a href="https://wa.me/<?= htmlspecialchars($waDigits) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-success"><i class="bi bi-whatsapp"></i></a>
      <?php endif; ?>
      <?php if ($canEdit): ?>
      <button type="button" class="btn btn-sm btn-outline-light js-delete-lead" data-id="<?= (int) $id ?>" data-nombre="<?= htmlspecialchars($lead['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>" title="Dar de baja">
        <i class="bi bi-trash"></i>
      </button>
      <?php endif; ?>
    </div>
  </header>

  <section class="crm-conv-summary">
    <div class="crm-summary-grid">
      <div><span>Servicio</span><strong><?= htmlspecialchars($svc) ?></strong></div>
      <div><span>Responsable</span><strong><?= htmlspecialchars($responsableNombre) ?></strong></div>
      <div><span>Registro</span><strong><?= htmlspecialchars($lead['fecha_registro'] ?? '—') ?></strong></div>
      <div><span>Última interacción</span><strong><?= htmlspecialchars($lead['ultima_interaccion'] ?? '—') ?></strong></div>
      <?php
      $origenLabel = $lead['fuente'] ?? '—';
      if ((int) ($lead['origen_web'] ?? 0) === 1) {
          if (!function_exists('cw_web_lead_origen_info')) {
              require_once dirname(__DIR__, 2) . '/website/includes/leads_helpers.php';
          }
          $origenLabel = cw_web_lead_origen_info($lead['fuente'] ?? '', $lead['pagina_origen'] ?? '')['label'];
      }
      ?>
      <div class="crm-summary-wide"><span>Origen</span><strong><?= htmlspecialchars($origenLabel) ?></strong></div>
      <?php if (!empty($lead['pagina_origen'])): ?>
      <div class="crm-summary-wide"><span>Página</span><strong><?= htmlspecialchars($lead['pagina_origen']) ?></strong></div>
      <?php endif; ?>
      <?php if (!empty($lead['requerimiento'])): ?>
      <div class="crm-summary-wide"><span>Requerimiento</span><strong><?= nl2br(htmlspecialchars($lead['requerimiento'])) ?></strong></div>
      <?php endif; ?>
    </div>
  </section>

  <section class="crm-timeline-wrap">
    <div class="crm-timeline" id="crmTimeline">
      <?php foreach ($timeline as $ev):
        $kind = $ev['kind'] ?? 'nota';
        $meta = $ev['meta'] ?? [];
      ?>
      <article class="crm-event crm-event-<?= htmlspecialchars($kind) ?>">
        <div class="crm-event-marker"></div>
        <div class="crm-event-body">
          <header class="crm-event-head">
            <strong><?= htmlspecialchars($ev['title'] ?? ucfirst($kind)) ?></strong>
            <time><?= htmlspecialchars($ev['date'] ?? '') ?></time>
          </header>
          <p class="crm-event-user"><?= htmlspecialchars($ev['user'] ?? 'Sistema') ?></p>
          <div class="crm-event-text"><?= nl2br(htmlspecialchars($ev['body'] ?? '')) ?></div>
          <?php if ($kind === 'archivo' && !empty($meta['adjunto_id'])): ?>
          <div class="crm-event-files">
            <a href="<?= adm_href('leads/download_adjunto.php?id=' . (int) $meta['adjunto_id']) ?>" class="crm-file-link"><i class="bi bi-download"></i> Descargar</a>
            <?php if (str_starts_with((string) ($meta['mime_type'] ?? ''), 'image/')): ?>
            <a href="<?= adm_href('leads/download_adjunto.php?id=' . (int) $meta['adjunto_id'] . '&view=1') ?>" target="_blank" class="crm-file-link"><i class="bi bi-eye"></i> Ver</a>
            <?php endif; ?>
            <span class="crm-file-size"><?= htmlspecialchars(cw_inbox_format_bytes((int) ($meta['tamano'] ?? 0))) ?></span>
          </div>
          <?php endif; ?>
          <?php if (!empty($meta['proxima_accion'])): ?>
          <p class="crm-event-reminder"><i class="bi bi-alarm"></i> Recordatorio: <?= htmlspecialchars($meta['proxima_accion']) ?></p>
          <?php endif; ?>
        </div>
      </article>
      <?php endforeach; ?>
      <?php if (empty($timeline)): ?>
      <p class="text-muted px-3">Sin historial todavía.</p>
      <?php endif; ?>
    </div>
  </section>

  <?php if ($canEdit): ?>
  <footer class="crm-composer">
    <form id="crmNoteForm" class="crm-note-form" enctype="multipart/form-data">
      <input type="hidden" name="id" value="<?= (int) $id ?>">
      <div class="crm-composer-row">
        <select name="tipo" class="form-control form-control-sm crm-note-type">
          <option value="nota">Nota</option>
          <option value="llamada">Llamada</option>
          <option value="mensaje">Mensaje</option>
          <option value="email">Correo</option>
          <option value="seguimiento">Seguimiento</option>
        </select>
        <label class="crm-upload-btn mb-0">
          <i class="bi bi-paperclip"></i> Adjuntar
          <input type="file" name="archivo" id="crmFileInput" hidden accept=".pdf,.png,.jpg,.jpeg,.gif,.webp,.doc,.docx,.xls,.xlsx,.txt">
        </label>
      </div>
      <textarea name="nota" class="form-control" rows="2" placeholder="Escribe una nota o comentario de seguimiento…" required maxlength="4000"></textarea>
      <div class="crm-composer-actions">
        <span id="crmFileLabel" class="crm-file-label text-muted small"></span>
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-send"></i> Enviar nota</button>
      </div>
    </form>
  </footer>
  <?php else: ?>
  <footer class="crm-composer crm-composer-readonly">
    <p class="text-muted mb-0 small">Vista de solo lectura — no tienes permiso para editar este lead.</p>
  </footer>
  <?php endif; ?>
</div>
