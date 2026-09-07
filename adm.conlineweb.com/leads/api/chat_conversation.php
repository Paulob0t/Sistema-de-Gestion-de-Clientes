<?php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_chat_migrate.php';
require_once dirname(__DIR__, 2) . '/includes/cw_chat_service.php';
require_once dirname(__DIR__, 2) . '/includes/cw_chat_client_context.php';
require_once dirname(__DIR__) . '/includes/inbox_helpers.php';

cw_chat_migrate($conn);

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

$conv = cw_chat_get_conversation($conn, $id);
if (!$conv) {
    echo json_encode(['success' => false, 'message' => 'Conversación no encontrada']);
    exit;
}

cw_chat_mark_admin_read($conn, $id);
$messages = cw_chat_get_messages_recent($conn, $id, 40);
$totalStmt = $conn->prepare('SELECT COUNT(*) c FROM cw_chat_mensajes WHERE conversacion_id = ?');
$hasMore = false;
if ($totalStmt) {
    $totalStmt->bind_param('i', $id);
    $totalStmt->execute();
    $total = (int) ($totalStmt->get_result()->fetch_assoc()['c'] ?? 0);
    $totalStmt->close();
    $hasMore = $total > count($messages);
}
if (!empty($conv['cliente_id'])) {
    $ctx = cw_chat_client_context($conn, (int) $conv['cliente_id']);
} elseif (!empty($conv['lead_id'])) {
    $ctx = cw_chat_lead_context($conn, (int) $conv['lead_id']);
} elseif (($conv['canal'] ?? '') === 'web') {
    $ctx = cw_chat_web_session_context($conn, $conv);
} else {
    $ctx = [];
}

ob_start();
include dirname(__DIR__) . '/partials/chat_conversacion.php';
$html = ob_get_clean();

echo json_encode(['success' => true, 'html' => $html, 'has_more' => $hasMore], JSON_UNESCAPED_UNICODE);
