<?php
require_once dirname(__DIR__, 2) . '/includes/cliente_session.php';
cliente_start_session();
header('Content-Type: application/json; charset=utf-8');

if (!cliente_is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false]);
    exit;
}

require_once dirname(__DIR__, 3) . '/adm.conlineweb.com/conn.php';
require_once dirname(__DIR__, 3) . '/adm.conlineweb.com/includes/cw_chat_migrate.php';
require_once dirname(__DIR__, 3) . '/adm.conlineweb.com/includes/cw_chat_realtime.php';

cw_chat_migrate($conn);

$uuid = trim((string) ($_GET['uuid'] ?? ''));
$clienteId = (int) $_SESSION['uid'];
$conv = cw_chat_get_conversation_by_uuid($conn, $uuid);
if (!$conv || (int) ($conv['cliente_id'] ?? 0) !== $clienteId) {
    echo json_encode(['success' => false, 'unread' => 0]);
    exit;
}

$r = $conn->prepare("SELECT COUNT(*) c FROM cw_chat_mensajes
    WHERE conversacion_id = ? AND remitente_tipo IN ('agente','bot') AND leido_at IS NULL");
$unread = 0;
if ($r) {
    $id = (int) $conv['id'];
    $r->bind_param('i', $id);
    $r->execute();
    $unread = (int) ($r->get_result()->fetch_assoc()['c'] ?? 0);
    $r->close();
}

echo json_encode(['success' => true, 'unread' => $unread]);
