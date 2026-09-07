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
require_once dirname(__DIR__, 3) . '/adm.conlineweb.com/includes/cw_chat_service.php';

$clienteId = (int) $_SESSION['uid'];
$uuid = trim((string) ($_GET['uuid'] ?? ''));
$afterId = (int) ($_GET['after_id'] ?? 0);

if ($uuid === '') {
    echo json_encode(['success' => false, 'message' => 'UUID requerido']);
    exit;
}

cw_chat_migrate($conn);
$conv = cw_chat_get_conversation_by_uuid($conn, $uuid);
if (!$conv || (int) ($conv['cliente_id'] ?? 0) !== $clienteId) {
    echo json_encode(['success' => false, 'message' => 'Conversación no encontrada']);
    exit;
}

$convId = (int) $conv['id'];
$messages = cw_chat_get_messages($conn, $convId, $afterId > 0 ? $afterId : null);

echo json_encode([
    'success' => true,
    'messages' => $messages,
    'conversation' => [
        'estado' => $conv['estado'],
        'estado_label' => cw_chat_estado_label($conv['estado']),
    ],
], JSON_UNESCAPED_UNICODE);
