<?php
/**
 * Widget flotante: cerebro del sistema (núcleo visual + voz + IA).
 * Incluir desde menu.php cuando el usuario puede ver analytics.
 */
if (!empty($GLOBALS['cw_brain_widget_rendered'])) {
    return;
}
$GLOBALS['cw_brain_widget_rendered'] = true;

if (!function_exists('adm_href')) {
    require_once __DIR__ . '/adm_paths.php';
}

$cwBrainApi = adm_href('analytics/api/seo_mexico_ai_prompt.php');
$cwBrainCss = adm_href('assets/css/cw-brain-float.css') . '?v=5';
$cwBrainJs = adm_href('assets/js/cw-brain-float.js') . '?v=6';
$cwBrainMonitor = adm_href('analytics/seo_mexico_monitor.php');
?>
<link rel="stylesheet" href="<?= htmlspecialchars($cwBrainCss, ENT_QUOTES, 'UTF-8') ?>">
<div class="cw-brain-root" id="cwBrainRoot" aria-live="polite">
  <div class="cw-brain-panel" id="cwBrainPanel" role="dialog" aria-label="Cerebro del sistema">
    <div class="cw-brain-head">
      <div>
        <h3>Cerebro ConlineWeb</h3>
        <p>Base de conocimiento · órdenes · voz amable</p>
      </div>
      <div class="cw-brain-head-actions">
        <button type="button" class="cw-brain-icon-btn is-on" id="cwBrainSpeakToggle" title="Voz del cerebro: activada">
          <i class="fas fa-volume-up"></i>
        </button>
        <a class="cw-brain-icon-btn" href="<?= htmlspecialchars($cwBrainMonitor, ENT_QUOTES, 'UTF-8') ?>" title="Ir a Monitor / cola">
          <i class="fas fa-tasks"></i>
        </a>
        <button type="button" class="cw-brain-icon-btn" id="cwBrainClose" title="Cerrar">
          <i class="fas fa-times"></i>
        </button>
      </div>
    </div>
    <div class="cw-brain-status" id="cwBrainStatus">Listo para ayudarte.</div>
    <div class="cw-brain-voice-row">
      <label for="cwBrainVoice">Voz humana</label>
      <select id="cwBrainVoice" title="Solo voces naturales / neurales en español">
        <option value="">Cargando voces…</option>
      </select>
      <button type="button" class="cw-brain-icon-btn" id="cwBrainVoicePreview" title="Probar voz">
        <i class="fas fa-play"></i>
      </button>
    </div>
    <div class="cw-brain-log" id="cwBrainLog"></div>
    <div class="cw-brain-compose">
      <label class="sr-only" for="cwBrainInput">Mensaje al cerebro</label>
      <textarea id="cwBrainInput" placeholder="Ej. Revisa desarrollo-de-software y propón mejoras SEO… o habla con el micrófono."></textarea>
      <div class="cw-brain-actions">
        <button type="button" class="btn btn-outline-light btn-sm btn-mic" id="cwBrainMic">
          <i class="fas fa-microphone"></i> Hablar
        </button>
        <button type="button" class="btn btn-info btn-sm" id="cwBrainSend">
          <i class="fas fa-paper-plane"></i> Enviar
        </button>
        <button type="button" class="btn btn-success btn-sm" id="cwBrainExec" disabled>
          <i class="fas fa-bolt"></i> Ejecutar orden
        </button>
      </div>
    </div>
  </div>

  <span class="cw-brain-speak-bubble" id="cwBrainSpeakBubble" aria-hidden="true" hidden>
    <span class="cw-brain-speak-label">Hablando</span>
    <span class="cw-brain-wave" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span>
  </span>

  <button type="button" class="cw-brain-fab" id="cwBrainFab" title="Abrir cerebro del sistema" aria-label="Abrir cerebro del sistema">
    <span class="cw-brain-badge" id="cwBrainBadge">!</span>
    <span class="cw-brain-orb" aria-hidden="true">
      <span class="cw-brain-halo"></span>
      <span class="cw-brain-core-layer cw-brain-core-outer"></span>
      <span class="cw-brain-core-layer cw-brain-core-middle"></span>
      <span class="cw-brain-orbit cw-brain-orbit-1">
        <span class="cw-brain-sat"></span>
      </span>
      <span class="cw-brain-orbit cw-brain-orbit-2">
        <span class="cw-brain-sat"></span>
      </span>
      <span class="cw-brain-orbit cw-brain-orbit-3">
        <span class="cw-brain-sat"></span>
      </span>
      <span class="cw-brain-beam cw-brain-beam-a"></span>
      <span class="cw-brain-beam cw-brain-beam-b"></span>
      <span class="cw-brain-beam cw-brain-beam-c"></span>
      <span class="cw-brain-core-layer cw-brain-core-inner"></span>
    </span>
  </button>
</div>
<script>
window.CW_BRAIN_CFG = {
  apiUrl: <?= json_encode($cwBrainApi, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
  monitorUrl: <?= json_encode($cwBrainMonitor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
};
</script>
<script src="<?= htmlspecialchars($cwBrainJs, ENT_QUOTES, 'UTF-8') ?>" defer></script>
<style>.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0}</style>
