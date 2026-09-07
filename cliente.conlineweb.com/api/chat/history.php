<?php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/includes/cliente_session.php';
cliente_start_session();

if (!cliente_is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once dirname(__DIR__, 3) . '/adm.conlineweb.com/conn.php';
require_once dirname(__DIR__, 3) . '/adm.conlineweb.com/includes/cw_chat_migrate.php';
require_once dirname(__DIR__, 3) . '/adm.conlineweb.com/includes/cw_chat_realtime.php';

cw_chat_migrate($conn);

$clienteId = (int) $_SESSION['uid'];
$uuid = trim((string) ($_GET['uuid'] ?? ''));
$beforeId = (int) ($_GET['before_id'] ?? 0);
$limit = min(50, max(10, (int) ($_GET['limit'] ?? 30)));

if ($uuid === '' || $beforeId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Parámetros inválidos']);
    exit;
}

$conv = cw_chat_get_conversation_by_uuid($conn, $uuid);
if (!$conv || (int) ($conv['cliente_id'] ?? 0) !== $clienteId) {
    echo json_encode(['success' => false, 'message' => 'Conversación no encontrada']);
    exit;
}

$messages = cw_chat_get_messages_before($conn, (int) $conv['id'], $beforeId, $limit);

echo json_encode([
    'success' => true,
    'messages' => $messages,
    'has_more' => count($messages) >= $limit,
], JSON_UNESCAPED_UNICODE);
