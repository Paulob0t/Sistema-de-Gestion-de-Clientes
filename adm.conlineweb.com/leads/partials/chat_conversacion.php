<?php
/** @var array $conv @var array $messages @var array $ctx @var int $id */
$waDigits = preg_replace('/\D/', '', (string) ($ctx['cliente']['telefono'] ?? ''));
$estado = $conv['estado'] ?? 'bot';
$botActivo = cw_chat_bot_activo($estado);
$botNombre = cw_chat_config_get($conn, 'bot_nombre', 'Asistente ConlineWeb');
?>
<div class="crm-conversation ch-conversation" data-chat-id="<?= (int) $id ?>">
  <header class="crm-conv-header">
    <div class="crm-conv-head-main">
      <?php
        $headName = $ctx['cliente']['nombre_contacto']
            ?? (!empty($conv['lead_id']) ? ('Lead #' . (int) $conv['lead_id']) : ('Cliente #' . ($conv['cliente_id'] ?? '?')));
        if (!empty($ctx['es_sesion_web']) && empty($ctx['cliente']['nombre_contacto'])) {
            $headName = 'Visitante en vivo';
        }
      ?>
      <h2><?= htmlspecialchars((string) $headName) ?></h2>
      <p class="crm-conv-sub">
        Chat #<?= (int) $id ?>
        <?php if (($conv['canal'] ?? '') === 'web'): ?> · Sesión web<?php endif; ?>
        <?php if (!empty($ctx['sesion']['session_short'])): ?> · ID <?= htmlspecialchars((string) $ctx['sesion']['session_short']) ?><?php endif; ?>
        <?php if (!empty($conv['lead_id'])): ?> · Lead #<?= (int) $conv['lead_id'] ?><?php endif; ?>
        <?php if (!empty($ctx['es_sesion_web']) && empty($conv['lead_id'])): ?> · Sin registrar aún<?php endif; ?>
        <?php if (!empty($ctx['cliente']['correo'])): ?> · <?= htmlspecialchars($ctx['cliente']['correo']) ?><?php endif; ?>
        <?php if (!empty($ctx['cliente']['telefono'])): ?> · <?= htmlspecialchars($ctx['cliente']['telefono']) ?><?php endif; ?>
      </p>
    </div>
    <div class="crm-conv-head-actions">
      <span class="crm-status-pill <?= cw_chat_estado_class($estado) ?>"><?= htmlspecialchars(cw_chat_estado_label($estado)) ?></span>
      <?php if ($waDigits !== ''): ?>
      <a href="https://wa.me/<?= htmlspecialchars($waDigits) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-success" title="WhatsApp"><i class="bi bi-whatsapp"></i></a>
      <?php endif; ?>
      <button type="button" class="btn btn-sm btn-outline-light js-chat-assign" data-id="<?= (int) $id ?>" title="Asignarme"><i class="bi bi-person-check"></i></button>
      <button type="button" class="btn btn-sm btn-outline-light js-chat-close" data-id="<?= (int) $id ?>" title="Cerrar"><i class="bi bi-check-circle"></i></button>
      <button type="button" class="btn btn-sm btn-outline-danger js-chat-delete" data-id="<?= (int) $id ?>" data-nombre="<?= htmlspecialchars((string) $headName, ENT_QUOTES, 'UTF-8') ?>" title="Eliminar de la bandeja"><i class="bi bi-trash"></i></button>
    </div>
  </header>

  <section class="crm-conv-summary ch-ctx-summary">
    <details class="ch-ctx-panel" open>
      <summary class="ch-ctx-panel__summary">
        <span><i class="bi bi-person-badge"></i> <?= !empty($ctx['es_sesion_web']) ? 'Contexto de la sesión' : 'Contexto del cliente' ?></span>
        <span class="ch-ctx-panel__hint">clic para plegar</span>
      </summary>
      <div class="ch-ctx-panel__body">
        <?= cw_chat_context_summary_html($ctx) ?>
        <?php if (!empty($conv['motivo_escalamiento'])): ?>
        <div class="ch-escalamiento"><strong>Escalamiento:</strong> <?= htmlspecialchars($conv['motivo_escalamiento']) ?></div>
        <?php endif; ?>
      </div>
    </details>
  </section>

  <?php if ($estado !== 'cerrada'): ?>
  <section class="ch-bot-control" id="chBotControl" data-id="<?= (int) $id ?>" data-bot-active="<?= $botActivo ? '1' : '0' ?>">
    <div class="ch-bot-control__info">
      <span class="ch-bot-control__icon"><i class="bi bi-robot"></i></span>
      <div>
        <strong class="ch-bot-control__title"><?= htmlspecialchars($botNombre) ?></strong>
        <p class="ch-bot-control__status" id="chBotStatusText">
          <?= $botActivo
            ? 'Activo — responde automáticamente al cliente hasta que tomes el control.'
            : 'Pausado — solo un asesor humano puede responder al cliente.' ?>
        </p>
      </div>
    </div>
    <div class="ch-bot-control__actions">
      <?php if ($botActivo): ?>
      <button type="button" class="btn btn-sm btn-outline-primary js-chat-bot-disable" data-id="<?= (int) $id ?>">
        <i class="bi bi-person-fill-check"></i> Tomar control
      </button>
      <?php else: ?>
      <button type="button" class="btn btn-sm btn-success js-chat-bot-enable" data-id="<?= (int) $id ?>">
        <i class="bi bi-robot"></i> Activar chatbot
      </button>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="ch-messages-wrap">
    <div class="ch-typing-indicator" id="chTypingIndicator">El cliente está escribiendo…</div>
    <?php if (!empty($hasMore)): ?>
    <div class="ch-load-more-wrap">
      <button type="button" class="btn btn-sm btn-outline-secondary" id="chLoadMoreBtn" data-id="<?= (int) $id ?>">Cargar mensajes anteriores</button>
    </div>
    <?php endif; ?>
    <div class="ch-messages" id="chMessagesTimeline">
      <?php foreach ($messages as $msg):
        $tipo = $msg['remitente_tipo'];
        $icons = ['cliente' => 'bi-person-fill', 'bot' => 'bi-robot', 'agente' => 'bi-headset', 'sistema' => 'bi-info-circle'];
      ?>
      <article class="ch-msg ch-msg--<?= htmlspecialchars($tipo) ?>" data-id="<?= (int) $msg['id'] ?>">
        <span class="ch-msg-avatar"><i class="bi <?= $icons[$tipo] ?? 'bi-chat' ?>"></i></span>
        <div class="ch-msg-body-wrap">
          <header class="ch-msg-head">
            <strong><?= htmlspecialchars($msg['remitente_nombre']) ?></strong>
            <time><?= htmlspecialchars($msg['enviado_at']) ?></time>
          </header>
          <div class="ch-msg-body"><?= nl2br(htmlspecialchars($msg['contenido'])) ?></div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </section>

  <?php if ($estado !== 'cerrada'): ?>
  <footer class="ch-reply-bar">
    <form id="chReplyForm" class="ch-reply-form">
      <input type="hidden" name="id" value="<?= (int) $id ?>">
      <textarea name="mensaje" class="form-control" rows="2" placeholder="<?= !empty($ctx['es_sesion_web']) ? 'Escribe al visitante de esta sesión…' : 'Escribe tu respuesta al cliente…' ?>" required></textarea>
      <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-send-fill"></i> Enviar</button>
    </form>
  </footer>
  <?php endif; ?>
</div>
