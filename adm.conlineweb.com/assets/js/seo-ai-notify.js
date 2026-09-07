/**
 * Alertas SEO/IA en la campanita del adm (+ badge).
 * Comparte UI con chat-menu-live si existe; si no, crea la campanita.
 */
(function () {
  'use strict';
  if (typeof window.ADM_BASE === 'undefined') return;

  var BASE = window.ADM_BASE || '';
  var API = BASE + '/analytics/api/seo_mexico_alerts.php';
  var MONITOR = BASE + '/analytics/seo_mexico_monitor.php';
  var aiItems = [];
  var pollTimer = null;

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function ensureBell() {
    if (document.getElementById('cwNotifyBar')) return;
    var bar = document.createElement('div');
    bar.id = 'cwNotifyBar';
    bar.className = 'cw-notify-bar';
    bar.innerHTML =
      '<button type="button" id="cwNotifyBell" class="cw-notify-bell" aria-label="Notificaciones" aria-expanded="false">' +
      '<i class="bi bi-bell-fill"></i>' +
      '<span class="cw-notify-bell-badge" id="cwNotifyBellBadge" hidden>0</span>' +
      '</button>' +
      '<div class="cw-notify-panel" id="cwNotifyPanel" hidden>' +
      '<div class="cw-notify-panel__head">' +
      '<strong>Notificaciones</strong>' +
      '<button type="button" class="cw-notify-panel__mark" id="cwNotifyMarkAll">Marcar leídas</button>' +
      '</div>' +
      '<div class="cw-notify-panel__list" id="cwNotifyList">' +
      '<p class="cw-notify-panel__empty">Sin notificaciones nuevas</p>' +
      '</div>' +
      '<a href="' + esc(MONITOR) + '" class="cw-notify-panel__footer" id="cwNotifyFooter">Ir a Monitor IA</a>' +
      '</div>';
    document.body.appendChild(bar);

    var panelOpen = false;
    bar.querySelector('#cwNotifyBell').addEventListener('click', function (e) {
      e.stopPropagation();
      panelOpen = !panelOpen;
      var panel = bar.querySelector('#cwNotifyPanel');
      var btn = bar.querySelector('#cwNotifyBell');
      if (panelOpen) {
        panel.removeAttribute('hidden');
        btn.setAttribute('aria-expanded', 'true');
        btn.classList.add('is-open');
        refresh();
      } else {
        panel.setAttribute('hidden', 'hidden');
        btn.setAttribute('aria-expanded', 'false');
        btn.classList.remove('is-open');
      }
    });
    bar.querySelector('#cwNotifyMarkAll').addEventListener('click', function () {
      markAll();
    });
    document.addEventListener('click', function (e) {
      if (!bar.contains(e.target)) {
        panelOpen = false;
        bar.querySelector('#cwNotifyPanel').setAttribute('hidden', 'hidden');
        bar.querySelector('#cwNotifyBell').setAttribute('aria-expanded', 'false');
        bar.querySelector('#cwNotifyBell').classList.remove('is-open');
      }
    });
  }

  function updateHeadAndFooter() {
    var head = document.querySelector('#cwNotifyPanel .cw-notify-panel__head strong');
    if (head) head.textContent = 'Notificaciones';
    var footer = document.getElementById('cwNotifyFooter');
    if (!footer) {
      var panel = document.getElementById('cwNotifyPanel');
      if (panel) {
        var a = document.createElement('a');
        a.id = 'cwNotifyFooter';
        a.className = 'cw-notify-panel__footer';
        a.href = MONITOR;
        a.textContent = 'Ir a Monitor IA';
        panel.appendChild(a);
      }
    } else {
      footer.href = MONITOR;
      if (footer.textContent.indexOf('Chat') !== -1 || footer.textContent.indexOf('Monitor') === -1) {
        footer.textContent = 'Ir a Monitor IA / Chat';
      }
    }
  }

  function unreadAi() {
    return aiItems.filter(function (n) { return !n.read; }).length;
  }

  function bumpBadge() {
    var badge = document.getElementById('cwNotifyBellBadge');
    var bell = document.getElementById('cwNotifyBell');
    if (!badge || !bell) return;
    var current = parseInt(badge.textContent, 10) || 0;
    if (badge.hasAttribute('hidden')) current = 0;
    // Si chat ya puso un número, sumamos IA no leídas sin pisar a ciegas:
    // usamos max(chatVisible, aiUnread) aproximando desde el texto actual + ai.
    var ai = unreadAi();
    var total = Math.max(current, ai);
    // Si el badge solo refleja chat, preferimos current + nuevas AI no contadas
    if (ai > 0 && current === 0) total = ai;
    if (ai > 0 && current > 0) total = current; // chat-menu-live ya actualiza; re-render panel
    if (ai > 0) {
      // Forzar al menos las AI
      var chatOnly = window.__cwChatUnreadApprox || 0;
      total = Math.max(ai + chatOnly, ai, current);
    }
    if (total > 0) {
      badge.textContent = total > 9 ? '9+' : String(total);
      badge.removeAttribute('hidden');
      bell.classList.add('has-unread');
      if (ai > 0) bell.classList.add('is-ring');
      setTimeout(function () { bell.classList.remove('is-ring'); }, 900);
    }
  }

  function renderAiIntoPanel() {
    var list = document.getElementById('cwNotifyList');
    if (!list || !aiItems.length) return;

    // Prefijar items IA al inicio del panel
    var aiHtml = aiItems.slice(0, 12).map(function (n) {
      return '<a href="' + esc(n.url) + '" class="cw-notify-item cw-notify-item--ai' + (n.read ? ' is-read' : '') + '" data-ai-id="' + esc(n.id) + '" data-key="' + esc(n.key) + '">' +
        '<span class="cw-notify-item__icon"><i class="bi bi-robot"></i></span>' +
        '<span class="cw-notify-item__body">' +
        '<strong>' + esc(n.title) + '</strong>' +
        '<span>' + esc(n.text) + '</span>' +
        '<time>' + esc(n.time) + '</time>' +
        '</span></a>';
    }).join('');

    var empty = list.querySelector('.cw-notify-panel__empty');
    var existingAi = list.querySelectorAll('.cw-notify-item--ai');
    existingAi.forEach(function (el) { el.remove(); });

    if (empty && aiItems.length) {
      empty.remove();
    }
    list.insertAdjacentHTML('afterbegin', aiHtml);

    if (!list.querySelector('.cw-notify-item') && !list.querySelector('.cw-notify-panel__empty')) {
      list.innerHTML = '<p class="cw-notify-panel__empty">Sin notificaciones nuevas</p>';
    }

    list.querySelectorAll('.cw-notify-item--ai').forEach(function (el) {
      el.addEventListener('click', function () {
        var id = parseInt(el.getAttribute('data-ai-id') || '0', 10);
        if (id > 0) markOne(id);
      });
    });
  }

  function markOne(id) {
    fetch(API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'mark_read', id: id })
    }).then(function () {
      aiItems.forEach(function (n) { if (n.id === id) n.read = true; });
      renderAiIntoPanel();
      bumpBadge();
    }).catch(function () {});
  }

  function markAll() {
    fetch(API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'mark_all' })
    }).then(function () {
      aiItems.forEach(function (n) { n.read = true; });
      renderAiIntoPanel();
      bumpBadge();
    }).catch(function () {});
  }

  function refresh() {
    ensureBell();
    updateHeadAndFooter();
    fetch(API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'list', limit: 20 })
    }).then(function (r) { return r.json(); }).then(function (data) {
      if (!data || !data.ok) return;
      aiItems = Array.isArray(data.items) ? data.items : [];
      renderAiIntoPanel();
      bumpBadge();
    }).catch(function () {});
  }

  function boot() {
    ensureBell();
    updateHeadAndFooter();
    refresh();
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(refresh, 45000);

    var markBtn = document.getElementById('cwNotifyMarkAll');
    if (markBtn && !markBtn.dataset.aiBound) {
      markBtn.dataset.aiBound = '1';
      markBtn.addEventListener('click', function () { markAll(); });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
