<?php
require_once dirname(__DIR__, 2) . '/includes/cliente_session.php';
cliente_start_session();
header('Content-Type: application/json; charset=utf-8');

if (!cliente_is_logged_in() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(401);
    echo json_encode(['success' => false]);
    exit;
}

require_once dirname(__DIR__, 3) . '/adm.conlineweb.com/conn.php';
require_once dirname(__DIR__, 3) . '/adm.conlineweb.com/includes/cw_chat_migrate.php';
require_once dirname(__DIR__, 3) . '/adm.conlineweb.com/includes/cw_chat_realtime.php';

cw_chat_migrate($conn);

$uuid = trim((string) ($_POST['uuid'] ?? ''));
$typing = (int) ($_POST['typing'] ?? 0);
$clienteId = (int) $_SESSION['uid'];
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$conv = cw_chat_get_conversation_by_uuid($conn, $uuid);
if (!$conv || (int) ($conv['cliente_id'] ?? 0) !== $clienteId) {
    echo json_encode(['success' => false]);
    exit;
}

cw_chat_set_typing($conn, (int) $conv['id'], 'cliente', $typing === 1);
cw_chat_touch_presence($conn, (int) $conv['id'], 'cliente');

echo json_encode(['success' => true]);
