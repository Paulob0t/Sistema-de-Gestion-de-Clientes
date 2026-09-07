<?php
/**
 * Módulo interactivo de prompts con IA (entorno adm + sitio).
 */
require_once dirname(__DIR__) . '/auth_middleware.php';
require_once dirname(__DIR__) . '/conn.php';
require_once dirname(__DIR__) . '/includes/cw_hub_config.php';
require_once dirname(__DIR__) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__) . '/includes/cw_hub_permissions.php';
require_once dirname(__DIR__) . '/includes/cw_seo_mexico_ai_prompt.php';
require_once dirname(__DIR__) . '/includes/adm_paths.php';

cw_hub_migrate($conn);
cw_hub_require('hub.analytics.view');

$aiStatus = cw_seo_mexico_ai_status();
$aiActive = !empty($aiStatus['active']);
$presets = cw_seo_mexico_ai_prompt_presets();
$sessions = cw_seo_mexico_ai_prompt_list_sessions($conn, 25);
$sessionId = (int) ($_GET['session'] ?? 0);
$activeSession = $sessionId > 0 ? cw_seo_mexico_ai_prompt_get_session($conn, $sessionId) : null;
$messages = $activeSession ? cw_seo_mexico_ai_prompt_messages($conn, $sessionId, 100) : [];

$apiUrl = adm_href('analytics/api/seo_mexico_ai_prompt.php');
$monitorUrl = adm_href('analytics/seo_mexico_monitor.php');

include dirname(__DIR__) . '/menu.php';
?>
<div class="adm-page-shell">
<div class="container-fluid px-0">
<?php
$websiteTab = 'seo_ai_prompt';
$websiteHeroTitle = 'Chat con la IA del sitio';
$websiteHeroSub = 'Pide revisar páginas, corregir textos, ampliar copy, diseño o admin. Luego «Crear propuesta en la cola» y aprueba en Monitor.';
$websiteShowPeriod = false;
require dirname(__DIR__) . '/includes/website_module_shell.php';

function seo_pr_esc(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

$seoUxActive = 'chat';
require dirname(__DIR__) . '/includes/seo_module_nav.php';
?>

<section class="seo-pr" aria-label="Chat con la IA del sitio">
  <div class="seo-ux-panel">
    <h3>Cómo usarlo</h3>
    <p class="seo-ux-lead">
      La IA puede <strong>analizar páginas concretas</strong>, corregir/ampliar textos, proponer diseño y mejoras admin.
      <strong>Enviar mensaje</strong> = analiza y responde.
      <strong>Crear propuesta en la cola</strong> = formaliza la solicitud en Monitor (aún no publica).
    </p>
    <div class="seo-pr-top" style="margin:0">
      <div class="seo-pr-indicator <?= $aiActive ? 'is-on' : 'is-off' ?>">
        <span class="seo-pr-dot"></span>
        <?= seo_pr_esc((string) ($aiStatus['label'] ?? 'IA')) ?>
        · <?= seo_pr_esc((string) ($aiStatus['model'] ?? cw_seo_mexico_ai_model())) ?>
      </div>
      <a class="btn btn-primary btn-sm seo-ux-btn-action" href="<?= seo_pr_esc($monitorUrl) ?>">
        <i class="fas fa-tasks"></i> Ir a la cola de propuestas
      </a>
    </div>
  </div>

  <?php if (!$aiActive): ?>
  <p class="seo-pr-warn"><?= seo_pr_esc((string) ($aiStatus['detail'] ?? 'IA no activa')) ?>. Configura OpenAI para usar el chat.</p>
  <?php endif; ?>

  <div class="seo-pr-layout">
    <aside class="seo-pr-side">
      <button type="button" class="btn btn-primary btn-block btn-sm seo-ux-btn-action" id="seoPrNewBtn" <?= $aiActive ? '' : 'disabled' ?>>
        <i class="fas fa-plus"></i> Empezar chat nuevo
      </button>
      <h4>Chats recientes</h4>
      <ul class="seo-pr-sessions" id="seoPrSessions">
        <?php if ($sessions === []): ?>
        <li class="seo-pr-empty">Sin conversaciones aún.</li>
        <?php else: ?>
          <?php foreach ($sessions as $s): ?>
          <li class="<?= (int) ($s['id'] ?? 0) === $sessionId ? 'is-active' : '' ?>">
            <a href="?session=<?= (int) ($s['id'] ?? 0) ?>">
              <strong><?= seo_pr_esc((string) ($s['title'] ?? 'Sesión')) ?></strong>
              <small><?= seo_pr_esc((string) ($s['mode'] ?? '')) ?> · <?= seo_pr_esc((string) ($s['updated_at'] ?? '')) ?></small>
            </a>
          </li>
          <?php endforeach; ?>
        <?php endif; ?>
      </ul>
    </aside>

    <div class="seo-pr-main">
      <div class="seo-pr-modes">
        <label>Tipo de ayuda</label>
        <select id="seoPrMode" <?= $aiActive ? '' : 'disabled' ?>>
          <option value="ask" <?= ($activeSession['mode'] ?? '') === 'ask' ? 'selected' : '' ?>>Preguntar / planificar</option>
          <option value="ops" <?= ($activeSession['mode'] ?? '') === 'ops' ? 'selected' : '' ?>>Pasos operativos</option>
          <option value="admin" <?= ($activeSession['mode'] ?? '') === 'admin' ? 'selected' : '' ?>>Actualizar panel admin</option>
          <option value="validate" <?= ($activeSession['mode'] ?? '') === 'validate' ? 'selected' : '' ?>>Validar actualizaciones</option>
          <option value="maintain" <?= ($activeSession['mode'] ?? '') === 'maintain' ? 'selected' : '' ?>>Cambios en el sitio</option>
          <option value="design" <?= ($activeSession['mode'] ?? '') === 'design' ? 'selected' : '' ?>>Diseño con preview</option>
          <option value="draft_page" <?= ($activeSession['mode'] ?? '') === 'draft_page' ? 'selected' : '' ?>>Borrador de página (JSON)</option>
          <option value="draft_blog" <?= ($activeSession['mode'] ?? '') === 'draft_blog' ? 'selected' : '' ?>>Borrador de blog (JSON)</option>
        </select>
      </div>

      <div class="seo-pr-presets">
        <span>Atajos:</span>
        <?php foreach ($presets as $pr): ?>
        <button type="button" class="seo-pr-chip" data-preset="<?= seo_pr_esc((string) ($pr['id'] ?? '')) ?>"
          data-mode="<?= seo_pr_esc((string) ($pr['mode'] ?? 'ask')) ?>"
          data-prompt="<?= seo_pr_esc((string) ($pr['prompt'] ?? '')) ?>"
          <?= $aiActive ? '' : 'disabled' ?>>
          <?= seo_pr_esc((string) ($pr['label'] ?? '')) ?>
        </button>
        <?php endforeach; ?>
      </div>

      <div class="seo-pr-chat" id="seoPrChat">
        <?php if ($messages === []): ?>
        <div class="seo-pr-welcome">
          <p>Puedes pedir: «Revisa desarrollo-de-software.php», «Corrige el hero», «Amplía esta sección», «Propón diseño», blogs, hubs o admin.</p>
          <p><strong>Flujo:</strong> escribe la página/tarea → <em>Enviar mensaje</em> (analiza) → <em>Crear propuesta en la cola</em> → aprueba en Monitor.</p>
        </div>
        <?php else: ?>
          <?php foreach ($messages as $m):
            $role = (string) ($m['role'] ?? 'user');
          ?>
          <div class="seo-pr-msg is-<?= seo_pr_esc($role) ?>">
            <div class="seo-pr-msg-role"><?= $role === 'assistant' ? 'IA' : 'Tú' ?></div>
            <div class="seo-pr-msg-body"><?= nl2br(seo_pr_esc((string) ($m['content'] ?? ''))) ?></div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div id="seoPrFlash" class="seo-pr-flash" hidden></div>

      <form class="seo-pr-composer" id="seoPrForm">
        <textarea id="seoPrInput" rows="3" maxlength="8000"
          placeholder="Ej. «Revisa https://conlineweb.com/desarrollo-de-software/ y corrige textos» o «Propón diseño alineado a software-para-empresas»"
          <?= $aiActive ? '' : 'disabled' ?>></textarea>
        <div class="seo-pr-composer-actions">
          <button type="submit" class="btn btn-primary seo-ux-btn-action" id="seoPrSendBtn" <?= $aiActive ? '' : 'disabled' ?>
            title="Solo envía el mensaje al chat; no modifica el sitio">
            <i class="fas fa-paper-plane"></i> Enviar mensaje
          </button>
          <button type="button" class="btn btn-success seo-ux-btn-action" id="seoPrExecBtn" <?= $aiActive && $sessionId > 0 ? '' : 'disabled' ?>
            title="Interpreta tu petición y crea una propuesta pendiente en la cola">
            <i class="fas fa-tasks"></i> Crear propuesta en la cola
          </button>
          <button type="button" class="btn btn-outline-success seo-ux-btn-action" id="seoPrToProposalBtn" disabled
            title="Usa esto cuando la IA ya respondió con un JSON de página o blog">
            Guardar borrador JSON como propuesta
          </button>
          <p class="seo-pr-composer-hint">
            «Crear propuesta en la cola» no publica: encola corrección de página, diseño, blog, hub, admin o validación.
          </p>
        </div>
      </form>
    </div>
  </div>
</section>

<style>
.seo-pr{margin:1rem 0 2rem}
.seo-pr-top{display:flex;justify-content:space-between;align-items:center;gap:.75rem;margin-bottom:.75rem;flex-wrap:wrap}
.seo-pr-indicator{display:inline-flex;align-items:center;gap:.4rem;padding:.3rem .7rem;border-radius:999px;font-size:.78rem;font-weight:800}
.seo-pr-indicator.is-on{background:#dcfce7;color:#166534}
.seo-pr-indicator.is-off{background:#fee2e2;color:#991b1b}
.seo-pr-dot{width:.5rem;height:.5rem;border-radius:50%;background:currentColor}
.seo-pr-warn{padding:.65rem .8rem;border-radius:10px;background:#fff7ed;color:#9a3412;font-size:.86rem;border:1px solid rgba(234,88,12,.25)}
.seo-pr-layout{display:grid;grid-template-columns:240px 1fr;gap:.85rem;min-height:560px}
@media(max-width:900px){.seo-pr-layout{grid-template-columns:1fr}}
.seo-pr-side{background:#fff;border:1px solid rgba(15,23,42,.08);border-radius:14px;padding:.75rem}
.seo-pr-side h4{margin:.75rem 0 .4rem;font-size:.72rem;font-weight:800;text-transform:uppercase;color:#64748b}
.seo-pr-sessions{list-style:none;margin:0;padding:0;max-height:480px;overflow:auto}
.seo-pr-sessions li{margin:0 0 .25rem}
.seo-pr-sessions a{display:block;padding:.45rem .5rem;border-radius:8px;text-decoration:none;color:#334155;font-size:.8rem}
.seo-pr-sessions li.is-active a,.seo-pr-sessions a:hover{background:#eff6ff;color:#1e40af}
.seo-pr-sessions strong{display:block;font-size:.82rem}
.seo-pr-sessions small{display:block;color:#94a3b8;font-size:.68rem;margin-top:.1rem}
.seo-pr-empty{font-size:.8rem;color:#94a3b8;padding:.4rem}
.seo-pr-main{background:#fff;border:1px solid rgba(15,23,42,.08);border-radius:14px;padding:.85rem 1rem;display:flex;flex-direction:column;min-height:560px}
.seo-pr-modes{display:flex;align-items:center;gap:.5rem;margin-bottom:.55rem}
.seo-pr-modes label{font-size:.72rem;font-weight:800;color:#64748b;text-transform:uppercase}
.seo-pr-modes select{padding:.35rem .55rem;border-radius:8px;border:1px solid #cbd5e1;font-size:.84rem}
.seo-pr-presets{display:flex;flex-wrap:wrap;gap:.35rem;align-items:center;margin-bottom:.65rem}
.seo-pr-presets > span{font-size:.72rem;font-weight:700;color:#64748b}
.seo-pr-chip{border:1px solid #cbd5e1;background:#f8fafc;border-radius:999px;padding:.25rem .6rem;font-size:.72rem;font-weight:700;color:#334155;cursor:pointer}
.seo-pr-chip:hover:not(:disabled){background:#e0f2fe;border-color:#7dd3fc}
.seo-pr-chip:disabled{opacity:.5;cursor:not-allowed}
.seo-pr-chat{flex:1;overflow:auto;max-height:420px;padding:.5rem;background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;margin-bottom:.65rem}
.seo-pr-welcome{padding:.85rem;font-size:.86rem;color:#475569;line-height:1.45}
.seo-pr-msg{margin:0 0 .65rem;padding:.65rem .75rem;border-radius:12px;max-width:92%}
.seo-pr-msg.is-user{background:#dbeafe;margin-left:auto}
.seo-pr-msg.is-assistant{background:#fff;border:1px solid #e2e8f0}
.seo-pr-msg-role{font-size:.68rem;font-weight:800;text-transform:uppercase;color:#64748b;margin-bottom:.25rem}
.seo-pr-msg-body{font-size:.86rem;color:#0f172a;line-height:1.45;white-space:pre-wrap;word-break:break-word}
.seo-pr-flash{margin-bottom:.55rem;padding:.5rem .65rem;border-radius:8px;background:#dbeafe;color:#1e3a8a;font-size:.82rem}
.seo-pr-flash.is-err{background:#fef2f2;color:#991b1b}
.seo-pr-composer textarea{width:100%;border:1px solid #cbd5e1;border-radius:10px;padding:.65rem .75rem;font-size:.9rem;resize:vertical;min-height:84px}
.seo-pr-composer-actions{display:flex;flex-wrap:wrap;gap:.45rem;margin-top:.5rem}
.btn-block{display:block;width:100%}
</style>

<script>
(function () {
  var apiUrl = <?= json_encode($apiUrl, JSON_UNESCAPED_UNICODE) ?>;
  var monitorUrl = <?= json_encode($monitorUrl, JSON_UNESCAPED_UNICODE) ?>;
  var sessionId = <?= (int) $sessionId ?>;
  var proposalReady = false;
  var chat = document.getElementById('seoPrChat');
  var form = document.getElementById('seoPrForm');
  var input = document.getElementById('seoPrInput');
  var modeEl = document.getElementById('seoPrMode');
  var sendBtn = document.getElementById('seoPrSendBtn');
  var toPropBtn = document.getElementById('seoPrToProposalBtn');
  var execBtn = document.getElementById('seoPrExecBtn');
  var flash = document.getElementById('seoPrFlash');
  var newBtn = document.getElementById('seoPrNewBtn');

  function showFlash(msg, isErr) {
    if (!flash) return;
    flash.hidden = false;
    flash.classList.toggle('is-err', !!isErr);
    flash.textContent = msg || '';
  }
  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }
  function appendMsg(role, content) {
    if (!chat) return;
    var welcome = chat.querySelector('.seo-pr-welcome');
    if (welcome) welcome.remove();
    var div = document.createElement('div');
    div.className = 'seo-pr-msg is-' + role;
    div.innerHTML = '<div class="seo-pr-msg-role">' + (role === 'assistant' ? 'IA' : 'Tú') + '</div>'
      + '<div class="seo-pr-msg-body">' + esc(content).replace(/\n/g, '<br>') + '</div>';
    chat.appendChild(div);
    chat.scrollTop = chat.scrollHeight;
  }
  function renderMessages(list) {
    if (!chat) return;
    chat.innerHTML = '';
    if (!list || !list.length) {
      chat.innerHTML = '<div class="seo-pr-welcome"><p>Conversación vacía. Escribe un prompt.</p></div>';
      return;
    }
    list.forEach(function (m) {
      appendMsg(m.role === 'assistant' ? 'assistant' : 'user', m.content || '');
    });
  }
  function updateProposalBtn() {
    var mode = (modeEl && modeEl.value) || 'ask';
    if (toPropBtn) {
      // Solo borradores JSON (página/blog) usan este botón secundario
      toPropBtn.disabled = !(sessionId > 0 && proposalReady && (mode === 'draft_page' || mode === 'draft_blog'));
    }
    if (execBtn) {
      // Cualquier modo con sesión: crear propuesta (texto, diseño, página, admin…)
      execBtn.disabled = !(sessionId > 0);
    }
  }

  document.querySelectorAll('.seo-pr-chip').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (input) input.value = btn.getAttribute('data-prompt') || '';
      if (modeEl) modeEl.value = btn.getAttribute('data-mode') || 'ask';
      input && input.focus();
    });
  });

  if (newBtn) {
    newBtn.addEventListener('click', function () {
      fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'new_session', mode: (modeEl && modeEl.value) || 'ask' })
      }).then(function (r) { return r.json(); }).then(function (data) {
        if (!data || !data.ok) {
          showFlash((data && data.error) || 'No se pudo crear sesión', true);
          return;
        }
        location.href = '?session=' + data.session_id;
      });
    });
  }

  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!apiUrl || !sendBtn || sendBtn.disabled) return;
      var prompt = (input && input.value || '').trim();
      if (prompt.length < 2) {
        showFlash('Escribe un prompt.', true);
        return;
      }
      var mode = (modeEl && modeEl.value) || 'ask';
      var prev = sendBtn.innerHTML;
      sendBtn.disabled = true;
      sendBtn.innerHTML = 'Ejecutando…';
      appendMsg('user', prompt);
      if (input) input.value = '';
      showFlash('Consultando IA con contexto del sistema…', false);
      fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'run', prompt: prompt, mode: mode, session_id: sessionId || 0 })
      }).then(function (r) { return r.json(); }).then(function (data) {
        sendBtn.disabled = false;
        sendBtn.innerHTML = prev;
        if (!data || !data.ok) {
          showFlash((data && data.error) || 'Error al ejecutar prompt', true);
          return;
        }
        if (data.session_id && data.session_id !== sessionId) {
          sessionId = data.session_id;
          if (window.history && history.replaceState) {
            history.replaceState({}, '', '?session=' + sessionId);
          }
        }
        if (data.messages) renderMessages(data.messages);
        else if (data.reply) appendMsg('assistant', data.reply);
        proposalReady = !!data.proposal_ready;
        updateProposalBtn();
        showFlash(data.message || 'Listo. Si quieres aplicar, usa «Ejecutar petición → propuesta».', false);
      }).catch(function () {
        sendBtn.disabled = false;
        sendBtn.innerHTML = prev;
        showFlash('Error de red', true);
      });
    });
  }

  function goMonitor(data) {
    var ct = (data && data.content_type) || '';
    var q = ct === 'blog' ? '?content=blog&source=ai' : (ct === 'fix' ? '?content=fix&source=ai' : '?source=ai');
    location.href = monitorUrl + q;
  }

  if (execBtn) {
    execBtn.addEventListener('click', function () {
      if (!sessionId || execBtn.disabled) return;
      var extra = (input && input.value || '').trim();
      execBtn.disabled = true;
      var prev = execBtn.innerHTML;
      execBtn.innerHTML = 'Creando propuesta…';
      showFlash('Interpretando petición y creando propuesta (sin escribir al sitio)…', false);
      fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'execute_request',
          session_id: sessionId,
          instruction: extra
        })
      }).then(function (r) { return r.json(); }).then(function (data) {
        execBtn.innerHTML = prev;
        updateProposalBtn();
        if (!data || !data.ok) {
          showFlash((data && data.error) || 'No se pudo ejecutar la petición', true);
          if (data && data.messages) renderMessages(data.messages);
          return;
        }
        if (data.messages) renderMessages(data.messages);
        showFlash((data.message || 'Propuesta creada') + ' Abriendo Monitor…', false);
        setTimeout(function () { goMonitor(data); }, 1000);
      }).catch(function () {
        execBtn.innerHTML = prev;
        updateProposalBtn();
        showFlash('Error de red', true);
      });
    });
  }

  if (toPropBtn) {
    toPropBtn.addEventListener('click', function () {
      if (!sessionId || toPropBtn.disabled) return;
      toPropBtn.disabled = true;
      fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'to_proposal', session_id: sessionId })
      }).then(function (r) { return r.json(); }).then(function (data) {
        if (!data || !data.ok) {
          updateProposalBtn();
          showFlash((data && data.error) || 'No se pudo crear propuesta', true);
          return;
        }
        showFlash((data.message || 'Propuesta creada') + ' Abriendo Monitor…', false);
        setTimeout(function () { goMonitor(data); }, 900);
      }).catch(function () {
        updateProposalBtn();
        showFlash('Error de red', true);
      });
    });
  }

  if (modeEl) modeEl.addEventListener('change', updateProposalBtn);
  updateProposalBtn();
})();
</script>

</div>
</div>
