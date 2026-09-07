<?php
/**
 * Server-Sent Events — Portal del Cliente
 */
require_once dirname(__DIR__, 2) . '/includes/cliente_session.php';
cliente_start_session();

if (!cliente_is_logged_in()) {
    http_response_code(401);
    exit;
}

require_once dirname(__DIR__, 3) . '/adm.conlineweb.com/conn.php';
require_once dirname(__DIR__, 3) . '/adm.conlineweb.com/includes/cw_chat_migrate.php';
require_once dirname(__DIR__, 3) . '/adm.conlineweb.com/includes/cw_chat_realtime.php';
require_once dirname(__DIR__, 3) . '/adm.conlineweb.com/includes/cw_chat_alerts.php';

cw_chat_migrate($conn);
cw_chat_sse_headers();

$clienteId = (int) $_SESSION['uid'];
$uuid = trim((string) ($_GET['uuid'] ?? ''));
$afterId = (int) ($_GET['after_id'] ?? 0);
$lastVersion = (int) ($_GET['version'] ?? 0);

if ($uuid === '') {
    cw_chat_sse_send('error', ['message' => 'UUID requerido']);
    exit;
}

$conv = cw_chat_get_conversation_by_uuid($conn, $uuid);
if (!$conv || (int) ($conv['cliente_id'] ?? 0) !== $clienteId) {
    cw_chat_sse_send('error', ['message' => 'Conversación no encontrada']);
    exit;
}

// Liberar bloqueo de sesión para no impedir send.php / typing.php en paralelo
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$convId = (int) $conv['id'];
$start = time();
$maxRuntime = 50;
$lastTypingAgente = false;

cw_chat_sse_send('connected', ['conversation_id' => $convId]);

while ((time() - $start) < $maxRuntime) {
    if (connection_aborted()) {
        break;
    }

    cw_chat_alert_process_due($conn);

    $conv = cw_chat_get_conversation($conn, $convId);
    if (!$conv) {
        break;
    }

    $version = (int) ($conv['realtime_version'] ?? 0);
    $typingAgente = cw_chat_typing_active($conv['agente_typing_at'] ?? null);
    $typingChanged = $typingAgente !== $lastTypingAgente;
    $lastTypingAgente = $typingAgente;

    if ($version !== $lastVersion) {
        $snapshot = cw_chat_realtime_snapshot($conn, $convId, $afterId, 'cliente');
        $lastVersion = (int) ($snapshot['version'] ?? $version);
        if (!empty($snapshot['messages'])) {
            $last = end($snapshot['messages']);
            $afterId = (int) ($last['id'] ?? $afterId);
        }
        cw_chat_sse_send('update', $snapshot);
    } elseif ($typingChanged) {
        cw_chat_sse_send('update', [
            'version' => $version,
            'messages' => [],
            'typing' => ['agente' => $typingAgente],
        ]);
    } else {
        cw_chat_sse_send('ping', ['ts' => time()]);
    }

    sleep(2);
}

cw_chat_sse_send('reconnect', ['after_id' => $afterId, 'version' => $lastVersion]);
