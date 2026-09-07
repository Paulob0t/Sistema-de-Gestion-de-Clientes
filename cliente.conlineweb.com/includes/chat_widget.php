<link rel="stylesheet" href="assets/css/chat-widget.css?v=10">

<button type="button" class="cw-chat-fab" id="cwChatFab" title="Chat de soporte" aria-label="Abrir chat de soporte">
    <span class="cw-chat-fab__pulse" aria-hidden="true"></span>
    <span class="cw-chat-fab__btn">
        <i class="bi bi-chat-dots-fill" aria-hidden="true"></i>
        <span class="cw-chat-fab__label">Chat en vivo</span>
        <span class="cw-chat-fab__badge" id="cwChatFabBadge" hidden>0</span>
        <span class="cw-chat-fab__dot" aria-hidden="true"></span>
    </span>
</button>

<div class="cw-chat-root" id="cwChatRoot">
<div class="cw-chat-panel" id="cwChatPanel" role="dialog" aria-modal="true" aria-label="Chat de soporte ConlineWeb">
    <div class="cw-chat-panel__head">
        <div class="cw-chat-panel__head-info">
            <div class="cw-chat-panel__avatar" aria-hidden="true"><i class="bi bi-chat-dots-fill"></i></div>
            <div>
                <h3 id="cwChatBotName">Asistente ConlineWeb</h3>
                <div class="cw-chat-panel__status">
                    <span class="cw-chat-panel__status-dot"></span>
                    <span id="cwChatStatusLabel">En línea</span>
                </div>
                <div class="cw-chat-conn">
                    <span class="cw-chat-conn__dot cw-chat-conn__dot--connecting" id="cwChatConnDot"></span>
                    <span id="cwChatConnLabel">Conectando…</span>
                </div>
            </div>
        </div>
        <div class="cw-chat-panel__head-actions">
            <button type="button" class="cw-chat-panel__expand" id="cwChatExpand" aria-label="Ampliar chat" title="Ampliar">
                <i class="bi bi-arrows-fullscreen" aria-hidden="true"></i>
            </button>
            <button type="button" class="cw-chat-panel__close" id="cwChatClose" aria-label="Cerrar chat">
                <i class="bi bi-x" aria-hidden="true"></i>
            </button>
        </div>
    </div>
    <div class="cw-chat-panel__messages" id="cwChatMessages">
        <div class="cw-chat-empty">Cargando conversación…</div>
    </div>
    <div class="cw-chat-typing" id="cwChatTyping" hidden aria-live="polite">
        <span class="cw-chat-typing__avatar" aria-hidden="true"><i class="bi bi-chat-dots-fill"></i></span>
        <div class="cw-chat-typing__body">
            <span class="cw-chat-typing__label" id="cwChatTypingLabel">Procesando…</span>
            <span class="cw-chat-typing__dots" aria-hidden="true"><i></i><i></i><i></i></span>
        </div>
    </div>
    <div class="cw-chat-panel__foot">
        <div class="cw-chat-send-status" id="cwChatSendStatus" hidden aria-live="polite"></div>
        <form class="cw-chat-panel__form" id="cwChatForm">
            <textarea class="cw-chat-panel__input" id="cwChatInput" rows="1"
                placeholder="Escribe tu mensaje…" maxlength="4000" aria-label="Mensaje"></textarea>
            <button type="submit" class="cw-chat-panel__send" id="cwChatSend" aria-label="Enviar">
                <i class="bi bi-arrow-up-short cw-chat-send-icon" aria-hidden="true"></i>
                <span class="cw-chat-send-spinner" aria-hidden="true"></span>
            </button>
        </form>
        <div class="cw-chat-panel__actions">
            <a href="tickets.php" class="cw-chat-panel__ticket" title="Registrar ticket">
                <i class="bi bi-inbox-fill" aria-hidden="true"></i> Registrar ticket
            </a>
            <a href="https://wa.me/524771181285?text=Hola%2C%20necesito%20ayuda%20con%20mi%20cuenta%20ConlineWeb" target="_blank" rel="noopener" class="cw-chat-panel__wa" id="cwChatWaLink" title="WhatsApp +52 477 118 1285">
                <i class="bi bi-whatsapp" aria-hidden="true"></i> WhatsApp
            </a>
            <button type="button" class="cw-chat-panel__agent" id="cwChatAgentBtn" data-cw-send="quiero hablar con un agente" title="Hablar con un agente">
                <i class="bi bi-headset" aria-hidden="true"></i> Hablar con agente
            </button>
        </div>
        <p class="cw-chat-panel__hint">Ayudo con hosting/cPanel, dominios/DNS, pagos, tickets y tu cuenta. En cualquier momento puedes pedir un agente.</p>
    </div>
</div>
</div>

<script src="assets/js/chat-widget.js?v=12"></script>
