/**
 * Contador en vivo + centro de notificaciones (campanita) para chat.
 * Cada mensaje genera UNA sola notificación (deduplicada por conversación + timestamp).
 */
(function () {
    'use strict';

    // En producción (raíz del dominio) ADM_BASE es "" — no usar !ADM_BASE (falsy).
    if (typeof window.ADM_BASE === 'undefined') return;

    var BASE = window.ADM_BASE || '';
    var API = BASE + '/leads/api';
    var INBOX_CHAT = BASE + '/leads/inbox.php?tab=chat';
    var STORAGE_KEY = 'cw_chat_notified_v2';
    var es = null;
    var booted = false;
    var lastCounts = { por_revisar: 0, sin_responder: 0 };
    var notifiedKeys = loadNotifiedKeys();
    var notifications = [];
    var panelOpen = false;
    var ui = null;
    var streamPausedByInbox = false;

    function loadNotifiedKeys() {
        try {
            var raw = sessionStorage.getItem(STORAGE_KEY);
            return raw ? JSON.parse(raw) : {};
        } catch (e) {
            return {};
        }
    }

    function saveNotifiedKeys() {
        try {
            var keys = Object.keys(notifiedKeys);
            if (keys.length > 300) {
                keys.sort();
                keys.slice(0, keys.length - 300).forEach(function (k) { delete notifiedKeys[k]; });
            }
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(notifiedKeys));
        } catch (e) { /* ignore */ }
    }

    function notifyKey(row) {
        var id = row.id || row.conversacion_id || 0;
        var ts = row.ultimo_mensaje_at || row.enviado_at || '';
        return String(id) + '|' + String(ts);
    }

    function menuCount(counts) {
        counts = counts || {};
        return Math.max(counts.por_revisar || 0, counts.sin_responder || 0, counts.sin_respuesta || 0);
    }

    function updateBadges(counts) {
        if (counts) {
            lastCounts = {
                por_revisar: counts.por_revisar || 0,
                sin_responder: counts.sin_responder || counts.sin_respuesta || 0
            };
        }
        var n = menuCount(lastCounts);
        document.querySelectorAll('[data-chat-menu-badge]').forEach(function (el) {
            if (n > 0) {
                el.textContent = n > 99 ? '99+' : String(n);
                el.removeAttribute('hidden');
            } else {
                el.setAttribute('hidden', 'hidden');
            }
        });
        document.querySelectorAll('[data-chat-module-badge]').forEach(function (el) {
            if (n > 0) {
                el.textContent = n > 99 ? '99+' : String(n);
                el.removeAttribute('hidden');
            } else {
                el.setAttribute('hidden', 'hidden');
            }
        });
        updateBellBadge();
    }

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s || '';
        return d.innerHTML;
    }

    function isInboxChatPage() {
        if (location.pathname.indexOf('inbox.php') === -1) return false;
        var params = new URLSearchParams(location.search);
        return params.get('tab') === 'chat';
    }

    function isViewingChat(convId) {
        if (location.pathname.indexOf('inbox.php') === -1) return false;
        var params = new URLSearchParams(location.search);
        if (params.get('tab') !== 'chat') return false;
        var openId = parseInt(params.get('id'), 10) || 0;
        if (!convId) return openId > 0;
        return openId > 0 && openId === convId;
    }

    function buildUI() {
        if (ui) return ui;
        var bar = document.createElement('div');
        bar.id = 'cwNotifyBar';
        bar.className = 'cw-notify-bar';
        bar.innerHTML =
            '<button type="button" id="cwNotifyBell" class="cw-notify-bell" aria-label="Notificaciones de chat" aria-expanded="false">' +
            '<i class="bi bi-bell-fill"></i>' +
            '<span class="cw-notify-bell-badge" id="cwNotifyBellBadge" hidden>0</span>' +
            '</button>' +
            '<div class="cw-notify-panel" id="cwNotifyPanel" hidden>' +
            '<div class="cw-notify-panel__head">' +
            '<strong>Notificaciones de chat</strong>' +
            '<button type="button" class="cw-notify-panel__mark" id="cwNotifyMarkAll">Marcar leídas</button>' +
            '</div>' +
            '<div class="cw-notify-panel__list" id="cwNotifyList">' +
            '<p class="cw-notify-panel__empty">Sin notificaciones nuevas</p>' +
            '</div>' +
            '<a href="' + esc(INBOX_CHAT) + '" class="cw-notify-panel__footer">Ir a Chat en vivo</a>' +
            '</div>';
        document.body.appendChild(bar);

        bar.querySelector('#cwNotifyBell').addEventListener('click', function (e) {
            e.stopPropagation();
            panelOpen = !panelOpen;
            var panel = bar.querySelector('#cwNotifyPanel');
            var btn = bar.querySelector('#cwNotifyBell');
            if (panelOpen) {
                panel.removeAttribute('hidden');
                btn.setAttribute('aria-expanded', 'true');
                btn.classList.add('is-open');
            } else {
                panel.setAttribute('hidden', 'hidden');
                btn.setAttribute('aria-expanded', 'false');
                btn.classList.remove('is-open');
            }
        });

        bar.querySelector('#cwNotifyMarkAll').addEventListener('click', function () {
            notifications.forEach(function (n) { n.read = true; });
            renderPanel();
            updateBellBadge();
        });

        bar.querySelector('#cwNotifyList').addEventListener('click', function (e) {
            var link = e.target.closest('.cw-notify-item');
            if (!link) return;
            var key = link.getAttribute('data-key');
            notifications.forEach(function (n) {
                if (n.key === key) n.read = true;
            });
            renderPanel();
            updateBellBadge();
        });

        document.addEventListener('click', function (e) {
            if (!bar.contains(e.target)) {
                panelOpen = false;
                bar.querySelector('#cwNotifyPanel').setAttribute('hidden', 'hidden');
                bar.querySelector('#cwNotifyBell').setAttribute('aria-expanded', 'false');
                bar.querySelector('#cwNotifyBell').classList.remove('is-open');
            }
        });

        ui = bar;
        return ui;
    }

    function updateBellBadge() {
        buildUI();
        var unreadNotifs = notifications.filter(function (n) { return !n.read; }).length;
        var pending = menuCount(lastCounts);
        var unread = Math.max(unreadNotifs, pending);
        var badge = document.getElementById('cwNotifyBellBadge');
        var bell = document.getElementById('cwNotifyBell');
        if (!badge || !bell) return;
        if (unread > 0) {
            badge.textContent = unread > 9 ? '9+' : String(unread);
            badge.removeAttribute('hidden');
            bell.classList.add('has-unread');
        } else {
            badge.setAttribute('hidden', 'hidden');
            bell.classList.remove('has-unread');
        }
    }

    function renderPanel() {
        buildUI();
        var list = document.getElementById('cwNotifyList');
        if (!list) return;
        if (!notifications.length) {
            list.innerHTML = '<p class="cw-notify-panel__empty">Sin notificaciones nuevas</p>';
            return;
        }
        list.innerHTML = notifications.slice(0, 20).map(function (n) {
            return '<a href="' + esc(n.url) + '" class="cw-notify-item' + (n.read ? ' is-read' : '') + '" data-key="' + esc(n.key) + '">' +
                '<span class="cw-notify-item__icon"><i class="bi bi-chat-left-text-fill"></i></span>' +
                '<span class="cw-notify-item__body">' +
                '<strong>' + esc(n.title) + '</strong>' +
                '<span>' + esc(n.text) + '</span>' +
                '<time>' + esc(n.time) + '</time>' +
                '</span></a>';
        }).join('');
    }

    function addNotification(row, opts) {
        opts = opts || {};
        if (!row || !row.id) return false;
        if (!opts.forceSeed && isViewingChat(row.id)) return false;

        var key = notifyKey(row);
        if (notifiedKeys[key] && !opts.forceSeed) return false;

        notifiedKeys[key] = 1;
        saveNotifiedKeys();

        var existing = notifications.find(function (n) { return n.id === row.id; });
        if (existing && existing.key === key) {
            if (opts.forceSeed) {
                existing.read = existing.read || false;
                renderPanel();
                updateBellBadge();
            }
            return false;
        }

        var preview = row.solicita_agente
            ? 'Solicita hablar con un agente'
            : (row.ultimo_mensaje_preview || 'Nuevo mensaje en el chat');
        var item = {
            key: key,
            id: row.id,
            title: row.nombre || ('Cliente #' + row.id),
            text: String(preview).substring(0, 100),
            time: row.ultimo_mensaje_at || '',
            url: INBOX_CHAT + '&id=' + row.id,
            read: !!opts.seedRead
        };

        notifications = notifications.filter(function (n) { return n.id !== row.id; });
        notifications.unshift(item);
        if (notifications.length > 30) notifications.length = 30;

        renderPanel();
        updateBellBadge();

        if (!opts.quiet) {
            var bell = document.getElementById('cwNotifyBell');
            if (bell) {
                bell.classList.add('is-ring');
                setTimeout(function () { bell.classList.remove('is-ring'); }, 900);
            }

            try {
                if (window.Notification && Notification.permission === 'granted') {
                    new Notification(item.title, { body: item.text, tag: 'cw-chat-' + key });
                }
            } catch (e) { /* ignore */ }
        }

        return true;
    }

    function needsAlert(row) {
        if (!row) return false;
        return !!(row.por_revisar || row.sin_respuesta || row.sin_responder || row.solicita_agente);
    }

    function notifyRows(rows, opts) {
        if (!rows || !rows.length) return;
        rows.forEach(function (row) {
            if (!needsAlert(row)) return;
            addNotification(row, opts);
        });
    }

    function seedPendingNotifications() {
        fetch(API + '/chat_inbox_list.php?filtro=por_revisar', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success || !res.data) return;
                notifyRows(res.data, { forceSeed: true, quiet: true, seedRead: false });
                // También sin respuesta
                return fetch(API + '/chat_inbox_list.php?filtro=sin_respuesta', { credentials: 'same-origin' });
            })
            .then(function (r) { return r ? r.json() : null; })
            .then(function (res) {
                if (res && res.success && res.data) {
                    notifyRows(res.data, { forceSeed: true, quiet: true, seedRead: false });
                }
                renderPanel();
                updateBellBadge();
            })
            .catch(function () {});
    }

    function handleCounts(counts) {
        counts = counts || {};
        updateBadges(counts);

        if (!booted) {
            lastCounts = {
                por_revisar: counts.por_revisar || 0,
                sin_responder: counts.sin_responder || counts.sin_respuesta || 0
            };
            booted = true;
            seedPendingNotifications();
            return;
        }

        var grew = (counts.por_revisar || 0) > (lastCounts.por_revisar || 0)
            || (counts.sin_responder || 0) > (lastCounts.sin_responder || 0);

        lastCounts = {
            por_revisar: counts.por_revisar || 0,
            sin_responder: counts.sin_responder || counts.sin_respuesta || 0
        };
        updateBellBadge();

        if (grew) {
            fetch(API + '/chat_inbox_delta.php?since=' + encodeURIComponent(new Date(Date.now() - 30000).toISOString().slice(0, 19).replace('T', ' ')), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.success && res.data) notifyRows(res.data);
                })
                .catch(function () {});
        }
    }

    function stopStream() {
        if (es) { es.close(); es = null; }
    }

    function startStream() {
        stopStream();
        es = new EventSource(API + '/chat_stream.php?list=1');
        es.addEventListener('counts', function (e) {
            try { handleCounts(JSON.parse(e.data)); } catch (err) { /* ignore */ }
        });
        es.addEventListener('list_delta', function (e) {
            try {
                var data = JSON.parse(e.data);
                if (data.counts) updateBadges(data.counts);
                if (data.rows) notifyRows(data.rows);
            } catch (err) { /* ignore */ }
        });
        es.addEventListener('reconnect', function () {
            stopStream();
            setTimeout(startStream, 800);
        });
        es.onerror = function () {
            stopStream();
            setTimeout(function () {
                if (!streamPausedByInbox) startStream();
            }, 5000);
        };
    }

    function init() {
        buildUI();
        renderPanel();
        updateBellBadge();

        fetch(API + '/chat_counts.php', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success && res.counts) handleCounts(res.counts);
                // Mantener stream activo también en inbox: la campanita no debe quedar muda.
                startStream();
            })
            .catch(function () {
                startStream();
            });

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                fetch(API + '/chat_counts.php', { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res.success && res.counts) updateBadges(res.counts);
                    });
            }
        });

        try {
            if (window.Notification && Notification.permission === 'default') {
                // No forzar prompt; el badge de campanita ya cubre el caso.
            }
        } catch (e) { /* ignore */ }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.cwChatMenuLive = {
        refresh: function () {
            fetch(API + '/chat_counts.php', { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.success && res.counts) updateBadges(res.counts);
                });
        },
        setCounts: updateBadges,
        notifyRows: notifyRows,
        addNotification: addNotification,
        dismissConv: function (convId) {
            notifications = notifications.filter(function (n) { return n.id !== convId; });
            renderPanel();
            updateBellBadge();
        },
        // Ya no se apaga el stream: inbox usa el suyo para la lista, campanita sigue viva.
        resumeStream: function () {
            streamPausedByInbox = false;
            if (!es) startStream();
        },
        pauseStream: function () {
            streamPausedByInbox = false;
            if (!es) startStream();
        }
    };
})();
