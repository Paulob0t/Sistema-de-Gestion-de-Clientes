(function () {
    'use strict';

    var API = 'api/chat';
    var SEND_TIMEOUT_MS = 28000;
    var TYPING_HIDE_MS = 12000;
    var state = {
        uuid: localStorage.getItem('cw_chat_uuid') || '',
        lastId: 0,
        version: 0,
        open: false,
        sending: false,
        botName: 'Asistente ConlineWeb',
        eventSource: null,
        typingTimer: null,
        botTypingTimer: null,
        sendAbort: null,
        unread: 0,
        userNearBottom: true,
        oldestId: null,
        bootstrapped: false,
        loadingHistory: false,
        seenIds: {},
        pendingContent: null
    };

    function $(id) { return document.getElementById(id); }

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s || '';
        return d.innerHTML;
    }

    function formatTime(iso) {
        if (!iso) return '';
        var d = new Date(String(iso).replace(' ', 'T'));
        if (isNaN(d.getTime())) return '';
        return d.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
    }

    function linkify(text) {
        var escaped = esc(text);
        escaped = escaped.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        return escaped.replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>');
    }

    function avatarFor(tipo) {
        // Iconos compatibles con bootstrap-icons del portal (sin bi-robot / bi-send-fill).
        var icons = { cliente: 'bi-person-fill', bot: 'bi-chat-dots-fill', agente: 'bi-headset', sistema: 'bi-info-circle' };
        return '<span class="cw-chat-avatar cw-chat-avatar--' + tipo + '"><i class="bi ' + (icons[tipo] || 'bi-chat-dots') + '" aria-hidden="true"></i></span>';
    }

    function deliveryIcon(msg) {
        if (msg.remitente_tipo !== 'cliente') return '';
        var d = msg.delivery || (msg.leido_at ? 'read' : 'delivered');
        if (d === 'read') return '<i class="bi bi-check2-all cw-chat-tick cw-chat-tick--read"></i>';
        return '<i class="bi bi-check2 cw-chat-tick"></i>';
    }

    function renderActions(msg) {
        var meta = msg.metadata || {};
        var acciones = meta.acciones;
        if (!acciones || !acciones.length) return '';
        var html = '<div class="cw-chat-msg__actions">';
        for (var i = 0; i < acciones.length; i++) {
            var a = acciones[i] || {};
            var label = esc(a.label || 'Acción');
            var icon = a.icon ? ('<i class="bi ' + esc(a.icon) + '"></i> ') : '';
            var cls = 'cw-chat-action' + (a.primary ? ' cw-chat-action--primary' : '') +
                (a.tipo === 'whatsapp' ? ' cw-chat-action--wa' : '');
            if (a.tipo === 'send' && a.text) {
                html += '<button type="button" class="' + cls + '" data-cw-send="' + esc(a.text) + '">' + icon + label + '</button>';
            } else if (a.url) {
                var target = (a.tipo === 'whatsapp' || /^https?:\/\//i.test(a.url)) ? ' target="_blank" rel="noopener"' : '';
                html += '<a class="' + cls + '" href="' + esc(a.url) + '"' + target + '>' + icon + label + '</a>';
            }
        }
        html += '</div>';
        return html;
    }

    function renderMessage(msg, animate) {
        var tipo = msg.remitente_tipo || 'bot';
        var id = String(msg.id);
        var cls = 'cw-chat-msg cw-chat-msg--' + tipo + (animate ? ' cw-chat-msg--in' : '');
        var nombre = msg.remitente_nombre || (tipo === 'cliente' ? 'Tú' : state.botName);
        return '<div class="' + cls + '" data-id="' + esc(id) + '" data-tipo="' + esc(tipo) + '">' +
            avatarFor(tipo) +
            '<div class="cw-chat-msg__bubble">' +
            '<div class="cw-chat-msg__text">' + linkify(msg.contenido).replace(/\n/g, '<br>') + '</div>' +
            renderActions(msg) +
            '<span class="cw-chat-msg__meta">' + esc(nombre) + ' · <time>' + formatTime(msg.enviado_at) + '</time> ' + deliveryIcon(msg) + '</span>' +
            '</div></div>';
    }

    function scrollToBottom(force) {
        var box = $('cwChatMessages');
        if (!box) return;
        if (force || state.userNearBottom) {
            box.scrollTop = box.scrollHeight;
        }
    }

    function markSeen(id) {
        if (id == null || id === '') return;
        var key = String(id);
        if (key.indexOf('tmp') === 0) return;
        state.seenIds[key] = true;
        var n = parseInt(key, 10);
        if (!isNaN(n) && n > state.lastId) state.lastId = n;
    }

    function isSeen(id) {
        return !!state.seenIds[String(id)];
    }

    /** Quita burbujas temporales del mismo texto (evita duplicado SSE + optimistic). */
    function removeTempsMatching(contenido) {
        var box = $('cwChatMessages');
        if (!box) return;
        var needle = (contenido || '').replace(/\s+/g, ' ').trim();
        box.querySelectorAll('[data-id^="tmp-"]').forEach(function (el) {
            var textEl = el.querySelector('.cw-chat-msg__text');
            var txt = (textEl ? textEl.innerText : el.innerText || '').replace(/\s+/g, ' ').trim();
            if (!needle || txt === needle || txt.indexOf(needle) === 0) {
                el.remove();
            }
        });
    }

    function removeAllTemps() {
        var box = $('cwChatMessages');
        if (!box) return;
        box.querySelectorAll('[data-id^="tmp-"]').forEach(function (el) { el.remove(); });
    }

    function updateReadTicks(updates) {
        if (!updates || !updates.length) return;
        updates.forEach(function (u) {
            var el = document.querySelector('[data-id="' + u.id + '"] .cw-chat-msg__meta');
            if (!el) return;
            var tick = el.querySelector('.cw-chat-tick');
            if (tick) {
                tick.className = 'bi bi-check2-all cw-chat-tick cw-chat-tick--read';
            } else {
                el.insertAdjacentHTML('beforeend', ' <i class="bi bi-check2-all cw-chat-tick cw-chat-tick--read"></i>');
            }
        });
    }

    function loadOlderMessages() {
        if (!state.uuid || !state.oldestId || state.loadingHistory) return;
        state.loadingHistory = true;
        var box = $('cwChatMessages');
        var prevHeight = box ? box.scrollHeight : 0;
        apiGet(API + '/history.php?uuid=' + encodeURIComponent(state.uuid) + '&before_id=' + state.oldestId + '&limit=25')
            .then(function (res) {
                if (!res.success || !res.messages || !res.messages.length) return;
                res.messages.forEach(function (msg) {
                    if (isSeen(msg.id)) return;
                    if (box && !box.querySelector('[data-id="' + msg.id + '"]')) {
                        box.insertAdjacentHTML('afterbegin', renderMessage(msg, false));
                    }
                    markSeen(msg.id);
                    if (!state.oldestId || msg.id < state.oldestId) state.oldestId = msg.id;
                });
                if (box) box.scrollTop = box.scrollHeight - prevHeight;
            })
            .finally(function () { state.loadingHistory = false; });
    }

    function bindScroll() {
        var box = $('cwChatMessages');
        if (!box || box._cwScroll) return;
        box._cwScroll = true;
        box.addEventListener('scroll', function () {
            var diff = box.scrollHeight - box.scrollTop - box.clientHeight;
            state.userNearBottom = diff < 80;
            if (box.scrollTop < 60 && state.oldestId) {
                loadOlderMessages();
            }
        });
    }

    function appendMessages(messages, replace) {
        var box = $('cwChatMessages');
        if (!box) return;
        if (replace) {
            box.innerHTML = '';
            state.oldestId = null;
            state.seenIds = {};
            state.lastId = 0;
        }
        if (!messages || !messages.length) {
            if (replace) box.innerHTML = '<div class="cw-chat-empty">Escribe tu consulta y te ayudamos al instante.</div>';
            return;
        }
        var empty = box.querySelector('.cw-chat-empty');
        if (empty) empty.remove();

        var gotReply = false;
        messages.forEach(function (msg) {
            var realId = msg.id;
            var idStr = String(realId);

            if (idStr.indexOf('tmp') === 0) {
                if (!box.querySelector('[data-id="' + idStr + '"]')) {
                    box.insertAdjacentHTML('beforeend', renderMessage(msg, true));
                }
                return;
            }

            if (isSeen(realId) || box.querySelector('[data-id="' + realId + '"]')) {
                markSeen(realId);
                return;
            }

            // Si llega el mensaje real del cliente por SSE, quitar el temporal.
            if ((msg.remitente_tipo || '') === 'cliente') {
                removeTempsMatching(msg.contenido || state.pendingContent || '');
            }

            box.insertAdjacentHTML('beforeend', renderMessage(msg, true));
            markSeen(realId);
            if (!state.oldestId || realId < state.oldestId) state.oldestId = realId;
            if (msg.remitente_tipo && msg.remitente_tipo !== 'cliente') gotReply = true;
        });
        if (gotReply) {
            hideTyping();
            setSendStatus('Respuesta lista', 'ok');
            state.pendingContent = null;
            setTimeout(function () { setSendStatus(''); }, 1200);
        }
        scrollToBottom(false);
    }

    function setConn(status) {
        var dot = $('cwChatConnDot');
        var label = $('cwChatConnLabel');
        if (dot) {
            dot.className = 'cw-chat-conn__dot cw-chat-conn__dot--' + status;
        }
        if (label) {
            label.textContent = status === 'live' ? 'En vivo' : (status === 'connecting' ? 'Conectando…' : 'Reconectando…');
        }
    }

    function setStatus(label) {
        var el = $('cwChatStatusLabel');
        if (el) el.textContent = label || 'En línea';
    }

    function hideTyping() {
        var el = $('cwChatTyping');
        if (!el) return;
        el.classList.remove('is-visible');
        el.hidden = true;
        clearTimeout(state.botTypingTimer);
        state.botTypingTimer = null;
    }

    function showTyping(who, show, customLabel) {
        var el = $('cwChatTyping');
        var label = $('cwChatTypingLabel');
        if (!el) return;
        if (!show) {
            hideTyping();
            return;
        }
        var text = customLabel || '';
        if (!text) {
            if (who === 'agente') text = 'Un asesor está escribiendo…';
            else if (who === 'sending') text = 'Enviando tu mensaje…';
            else text = 'El asistente está respondiendo…';
        }
        if (label) label.textContent = text;
        else el.textContent = text;
        el.hidden = false;
        el.classList.add('is-visible');
        clearTimeout(state.botTypingTimer);
        state.botTypingTimer = setTimeout(hideTyping, TYPING_HIDE_MS);
        scrollToBottom(true);
    }

    function setSendStatus(text, mode) {
        var el = $('cwChatSendStatus');
        if (!el) return;
        if (!text) {
            el.hidden = true;
            el.classList.remove('is-visible', 'is-error', 'is-ok');
            el.innerHTML = '';
            return;
        }
        var spin = (mode !== 'error' && mode !== 'ok')
            ? '<span class="cw-chat-send-status__spin" aria-hidden="true"></span>'
            : '';
        el.innerHTML = spin + '<span>' + esc(text) + '</span>';
        el.classList.toggle('is-error', mode === 'error');
        el.classList.toggle('is-ok', mode === 'ok');
        el.classList.add('is-visible');
        el.hidden = false;
    }

    function setSendingUi(active) {
        var form = $('cwChatForm');
        var btn = $('cwChatSend');
        var input = $('cwChatInput');
        if (form) form.classList.toggle('is-sending', !!active);
        if (btn) {
            btn.disabled = !!active;
            btn.classList.toggle('is-loading', !!active);
            btn.setAttribute('aria-busy', active ? 'true' : 'false');
        }
        if (input) input.setAttribute('aria-busy', active ? 'true' : 'false');
    }

    function markTempSending(tmpId, pending) {
        var tmp = document.querySelector('[data-id="' + tmpId + '"]');
        if (!tmp) return;
        tmp.classList.toggle('is-sending', !!pending);
        var meta = tmp.querySelector('.cw-chat-msg__meta');
        if (!meta) return;
        var badge = meta.querySelector('.cw-chat-msg__pending');
        if (pending) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'cw-chat-msg__pending';
                meta.appendChild(badge);
            }
            badge.textContent = 'Enviando…';
        } else if (badge) {
            badge.remove();
        }
    }

    function setFabBadge(n) {
        state.unread = n;
        var b = $('cwChatFabBadge');
        if (!b) return;
        if (n > 0 && !state.open) {
            b.textContent = n > 9 ? '9+' : String(n);
            b.hidden = false;
        } else {
            b.hidden = true;
        }
    }

    function showExpiryAlert(alert) {
        if (!alert || !alert.show || !alert.message) return;
        var fp = alert.fingerprint || 'alert';
        var dayKey = 'cw_chat_expiry_' + fp + '_' + new Date().toDateString();
        if (localStorage.getItem(dayKey)) return;

        var msgId = 'expiry-' + fp;
        if (isSeen(msgId)) {
            localStorage.setItem(dayKey, '1');
            return;
        }

        localStorage.setItem(dayKey, '1');
        appendMessages([{
            id: msgId,
            remitente_tipo: 'sistema',
            remitente_nombre: 'Aviso',
            contenido: alert.message,
            enviado_at: new Date().toISOString()
        }], false);
        state.seenIds[msgId] = true;
    }

    function fetchExpiryAlert() {
        return apiGet(API + '/expiry_alert.php?days=7')
            .then(function (res) {
                if (res.success && res.expiry_alert) {
                    showExpiryAlert(res.expiry_alert);
                }
            })
            .catch(function () {});
    }

    function apiGet(url) {
        return fetch(url, { credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            });
    }

    function apiPost(url, data, options) {
        options = options || {};
        var fd = new FormData();
        Object.keys(data).forEach(function (k) {
            if (data[k] !== undefined && data[k] !== null) fd.append(k, data[k]);
        });
        var opts = { method: 'POST', body: fd, credentials: 'same-origin' };
        if (options.signal) opts.signal = options.signal;
        return fetch(url, opts).then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        });
    }

    function sendTyping(active) {
        if (!state.uuid) return;
        apiPost(API + '/typing.php', { uuid: state.uuid, typing: active ? 1 : 0 }).catch(function () {});
    }

    function pollUnread() {
        if (!state.uuid || state.open) return;
        apiGet(API + '/unread.php?uuid=' + encodeURIComponent(state.uuid))
            .then(function (res) {
                if (res.success) setFabBadge(res.unread || 0);
            })
            .catch(function () {});
    }

    function bootstrap() {
        var url = API + '/bootstrap.php';
        if (state.uuid) url += '?uuid=' + encodeURIComponent(state.uuid);
        return apiGet(url).then(function (res) {
            if (!res.success) throw new Error(res.message || 'Error');
            state.uuid = res.conversation.uuid;
            localStorage.setItem('cw_chat_uuid', state.uuid);
            state.botName = res.bot_name || state.botName;
            var headName = $('cwChatBotName');
            if (headName) headName.textContent = state.botName;
            setStatus(res.conversation.estado_label);
            appendMessages(res.messages, true);
            if (res.messages && res.messages.length) {
                state.oldestId = res.messages[0].id;
            }
            state.bootstrapped = true;
            setFabBadge(0);
            return res;
        });
    }

    function stopStream() {
        if (state.eventSource) {
            try { state.eventSource.close(); } catch (e) { /* ignore */ }
            state.eventSource = null;
        }
    }

    function startStream() {
        stopStream();
        if (!state.uuid) return;
        setConn('connecting');
        var url = API + '/stream.php?uuid=' + encodeURIComponent(state.uuid) +
            '&after_id=' + state.lastId + '&version=' + state.version;
        var es = new EventSource(url);
        state.eventSource = es;

        es.addEventListener('connected', function () { setConn('live'); });
        es.addEventListener('update', function (e) {
            try {
                var data = JSON.parse(e.data);
                state.version = data.version || state.version;
                if (data.conversation) setStatus(data.conversation.estado_label);
                if (data.messages && data.messages.length) {
                    appendMessages(data.messages, false);
                    if (state.open) setFabBadge(0);
                    else pollUnread();
                }
                if (data.typing) {
                    if (data.typing.agente) {
                        showTyping('agente', true);
                    } else if (!state.sending) {
                        hideTyping();
                    }
                }
                if (data.read_updates) updateReadTicks(data.read_updates);
            } catch (err) { /* ignore */ }
        });
        es.addEventListener('reconnect', function () {
            es.close();
            setTimeout(startStream, 400);
        });
        es.onerror = function () {
            setConn('reconnecting');
            es.close();
            setTimeout(startStream, 2000);
        };
    }

    function newClientToken() {
        return 'ct_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 10);
    }

    function sendMessage() {
        var input = $('cwChatInput');
        var btn = $('cwChatSend');
        if (!input || state.sending) return;
        var text = input.value.trim();
        if (!text || !state.uuid) return;

        state.sending = true;
        state.pendingContent = text;
        setSendingUi(true);
        sendTyping(false);
        setSendStatus('Enviando tu mensaje…', 'loading');
        showTyping('sending', true, 'Enviando tu mensaje…');

        var clientToken = newClientToken();
        var tmpId = 'tmp-' + clientToken;

        // Un solo optimistic bubble
        removeAllTemps();
        appendMessages([{
            id: tmpId,
            remitente_tipo: 'cliente',
            remitente_nombre: 'Tú',
            contenido: text,
            enviado_at: new Date().toISOString(),
            delivery: 'delivered'
        }], false);
        markTempSending(tmpId, true);
        input.value = '';
        autoResizeInput();

        var controller = new AbortController();
        state.sendAbort = controller;
        var timeoutId = setTimeout(function () { controller.abort(); }, SEND_TIMEOUT_MS);
        var gotBot = false;

        apiPost(API + '/send.php', {
            uuid: state.uuid,
            mensaje: text,
            client_token: clientToken
        }, { signal: controller.signal })
            .then(function (res) {
                if (!res.success) {
                    removeAllTemps();
                    input.value = text;
                    setSendStatus(res.message || 'No se pudo enviar. Intenta de nuevo.', 'error');
                    showTyping('sending', false);
                    setTimeout(function () { setSendStatus(''); }, 3500);
                    return;
                }

                var tmp = document.querySelector('[data-id="' + tmpId + '"]');
                var mid = res.message_id ? String(res.message_id) : '';
                markTempSending(tmpId, false);

                if (mid) {
                    if (isSeen(mid) || document.querySelector('[data-id="' + mid + '"]')) {
                        if (tmp) tmp.remove();
                        markSeen(mid);
                    } else if (tmp) {
                        tmp.setAttribute('data-id', mid);
                        markSeen(mid);
                    } else {
                        appendMessages([{
                            id: res.message_id,
                            remitente_tipo: 'cliente',
                            remitente_nombre: 'Tú',
                            contenido: text,
                            enviado_at: new Date().toISOString(),
                            delivery: 'delivered'
                        }], false);
                    }
                } else if (tmp) {
                    tmp.remove();
                }

                if (res.conversation) setStatus(res.conversation.estado_label);

                setSendStatus('Mensaje enviado. El asistente está respondiendo…', 'loading');
                showTyping('bot', true, 'El asistente está respondiendo…');

                if (res.bot_messages && res.bot_messages.length) {
                    appendMessages(res.bot_messages, false);
                    gotBot = true;
                    setSendStatus('Respuesta lista', 'ok');
                    hideTyping();
                    setTimeout(function () { setSendStatus(''); }, 1200);
                }
            })
            .catch(function (err) {
                markTempSending(tmpId, false);
                if (err && err.name === 'AbortError') {
                    removeTempsMatching(text);
                    setSendStatus('El envío tardó demasiado. Revisa si apareció tu mensaje.', 'error');
                } else {
                    removeAllTemps();
                    input.value = text;
                    setSendStatus('Error de conexión. Intenta de nuevo.', 'error');
                }
                hideTyping();
                setTimeout(function () { setSendStatus(''); }, 4000);
            })
            .finally(function () {
                clearTimeout(timeoutId);
                state.sending = false;
                state.sendAbort = null;
                setSendingUi(false);
                if (btn) btn.disabled = false;
                if (input) input.focus();

                if (gotBot) {
                    state.pendingContent = null;
                    return;
                }

                // Sin respuesta aún: mantener feedback visible mientras llega por SSE
                if (state.pendingContent) {
                    setSendStatus('Mensaje enviado. Esperando respuesta…', 'loading');
                    showTyping('bot', true, 'El asistente está respondiendo…');
                    clearTimeout(state.botTypingTimer);
                    state.botTypingTimer = setTimeout(function () {
                        hideTyping();
                        setSendStatus('');
                        state.pendingContent = null;
                    }, 10000);
                }
            });
    }

    function syncExpandButton() {
        var btn = $('cwChatExpand');
        var panel = $('cwChatPanel');
        if (!btn || !panel) return;
        var expanded = panel.classList.contains('is-expanded');
        var icon = btn.querySelector('i');
        btn.setAttribute('aria-label', expanded ? 'Reducir chat' : 'Ampliar chat');
        btn.setAttribute('title', expanded ? 'Reducir' : 'Ampliar');
        if (icon) {
            icon.className = expanded ? 'bi bi-fullscreen-exit' : 'bi bi-arrows-fullscreen';
        }
    }

    function isMobileChat() {
        return window.matchMedia && window.matchMedia('(max-width: 1199.98px)').matches;
    }

    function setMobileChatLock(on) {
        var root = $('cwChatRoot');
        if (root) root.classList.toggle('is-open', !!on);

        if (on && isMobileChat()) {
            document.documentElement.classList.add('cw-chat-noscroll');
            document.body.classList.add('cw-chat-noscroll', 'cw-chat-mobile-open');
        } else {
            document.documentElement.classList.remove('cw-chat-noscroll', 'cw-chat-keyboard-open');
            document.body.classList.remove('cw-chat-noscroll', 'cw-chat-mobile-open');
            document.body.style.removeProperty('top');
            document.body.style.removeProperty('position');
            document.body.style.removeProperty('width');
        }
    }

    function updateViewportForKeyboard() {
        var panel = $('cwChatPanel');
        var html = document.documentElement;
        if (!state.open || !isMobileChat()) {
            html.style.removeProperty('--cw-chat-vv-top');
            html.style.removeProperty('--cw-chat-vv-height');
            html.classList.remove('cw-chat-keyboard-open');
            if (panel) {
                panel.style.removeProperty('top');
                panel.style.removeProperty('height');
                panel.style.removeProperty('max-height');
                panel.style.removeProperty('bottom');
            }
            return;
        }

        var vv = window.visualViewport;
        var layoutH = window.innerHeight || document.documentElement.clientHeight || 0;
        var vvH = vv ? vv.height : layoutH;
        var vvTop = vv ? vv.offsetTop : 0;
        var top = Math.max(0, Math.round(vvTop));
        var usable = Math.max(200, Math.round(vvH));
        var kb = Math.max(0, Math.round(layoutH - vvH - vvTop));
        var kbOpen = kb > 70;

        html.style.setProperty('--cw-chat-vv-top', top + 'px');
        html.style.setProperty('--cw-chat-vv-height', usable + 'px');
        html.classList.toggle('cw-chat-keyboard-open', kbOpen);

        if (panel) {
            panel.style.top = top + 'px';
            panel.style.height = usable + 'px';
            panel.style.maxHeight = usable + 'px';
            panel.style.bottom = 'auto';
        }

        scrollToBottom(false);
    }

    function scheduleViewportSync() {
        updateViewportForKeyboard();
        window.setTimeout(updateViewportForKeyboard, 60);
        window.setTimeout(updateViewportForKeyboard, 180);
        window.setTimeout(updateViewportForKeyboard, 360);
        window.setTimeout(function () {
            updateViewportForKeyboard();
            scrollToBottom(false);
        }, 520);
    }

    var viewportHandlersBound = false;

    function bindViewportHandlers() {
        if (viewportHandlersBound) return;
        viewportHandlersBound = true;

        function onVv() {
            if (state.open && isMobileChat()) updateViewportForKeyboard();
        }

        window.addEventListener('resize', onVv);
        window.addEventListener('orientationchange', function () {
            window.setTimeout(onVv, 120);
        });
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', onVv);
            window.visualViewport.addEventListener('scroll', onVv);
        }
    }

    function syncMobileChrome() {
        var closeBtn = $('cwChatClose');
        if (!closeBtn) return;
        var icon = closeBtn.querySelector('i');
        if (!icon) return;
        if (isMobileChat()) {
            icon.className = 'bi bi-arrow-left';
            closeBtn.setAttribute('aria-label', 'Volver');
            closeBtn.setAttribute('title', 'Volver');
        } else {
            icon.className = 'bi bi-x';
            closeBtn.setAttribute('aria-label', 'Cerrar chat');
            closeBtn.setAttribute('title', 'Cerrar');
        }
    }

    function autoResizeInput() {
        var input = $('cwChatInput');
        if (!input || !isMobileChat()) {
            if (input) input.style.height = '';
            return;
        }
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 120) + 'px';
    }

    function toggleExpand() {
        var panel = $('cwChatPanel');
        if (!panel || !panel.classList.contains('is-open')) return;
        panel.classList.toggle('is-expanded');
        syncExpandButton();
    }

    function openPanel() {
        state.open = true;
        var panel = $('cwChatPanel');
        if (panel) panel.classList.add('is-open');
        syncMobileChrome();
        setMobileChatLock(true);
        bindViewportHandlers();
        scheduleViewportSync();
        var boot = state.bootstrapped && state.uuid
            ? Promise.resolve(null)
            : bootstrap();
        boot.then(function (res) {
            bindScroll();
            startStream();
            setFabBadge(0);
            if (res && res.expiry_alert) {
                showExpiryAlert(res.expiry_alert);
            } else {
                fetchExpiryAlert();
            }
            var input = $('cwChatInput');
            if (input) {
                if (isMobileChat()) {
                    window.setTimeout(function () {
                        input.focus();
                        scheduleViewportSync();
                    }, 280);
                } else {
                    input.focus();
                }
            }
        }).catch(function () {
            alert('No se pudo cargar el chat. Recarga la página.');
        });
    }

    function closePanel() {
        state.open = false;
        var panel = $('cwChatPanel');
        if (panel) {
            panel.classList.remove('is-open');
            panel.classList.remove('is-expanded');
            panel.style.removeProperty('top');
            panel.style.removeProperty('height');
            panel.style.removeProperty('max-height');
            panel.style.removeProperty('bottom');
        }
        syncExpandButton();
        syncMobileChrome();
        setMobileChatLock(false);
        updateViewportForKeyboard();
        stopStream();
        sendTyping(false);
        hideTyping();
        pollUnread();
    }

    function togglePanel() {
        if (state.open) closePanel();
        else openPanel();
    }

    function init() {
        var fab = $('cwChatFab');
        var closeBtn = $('cwChatClose');
        var form = $('cwChatForm');
        var input = $('cwChatInput');

        if (fab) fab.addEventListener('click', togglePanel);
        if (closeBtn) closeBtn.addEventListener('click', closePanel);
        var expandBtn = $('cwChatExpand');
        if (expandBtn) expandBtn.addEventListener('click', toggleExpand);
        syncExpandButton();

        // Un solo canal de envío: submit del form (evita Enter + submit duplicado)
        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                e.stopPropagation();
                sendMessage();
            });
        }

        if (input) {
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (form && typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        sendMessage();
                    }
                }
            });
            input.addEventListener('input', function () {
                autoResizeInput();
                clearTimeout(state.typingTimer);
                sendTyping(true);
                state.typingTimer = setTimeout(function () { sendTyping(false); }, 1200);
            });
            input.addEventListener('focus', function () {
                if (isMobileChat()) scheduleViewportSync();
            });
        }

        bindViewportHandlers();
        syncMobileChrome();
        window.addEventListener('resize', syncMobileChrome);

        if (state.uuid) pollUnread();
        setInterval(pollUnread, 20000);

        var messagesBox = $('cwChatMessages');
        if (messagesBox) {
            messagesBox.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-cw-send]');
                if (!btn) return;
                e.preventDefault();
                var text = btn.getAttribute('data-cw-send') || '';
                if (!text || state.sending) return;
                var input = $('cwChatInput');
                if (input) input.value = text;
                sendMessage();
            });
        }

        var agentBtn = $('cwChatAgentBtn');
        if (agentBtn) {
            agentBtn.addEventListener('click', function (e) {
                e.preventDefault();
                if (state.sending) return;
                if (!state.open) openPanel();
                var input = $('cwChatInput');
                if (input) input.value = agentBtn.getAttribute('data-cw-send') || 'quiero hablar con un agente';
                sendMessage();
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.cwChatWidget = { open: openPanel, close: closePanel };
})();
