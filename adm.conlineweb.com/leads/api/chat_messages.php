<?php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_chat_migrate.php';
require_once dirname(__DIR__, 2) . '/includes/cw_chat_realtime.php';

cw_chat_migrate($conn);

$id = (int) ($_GET['id'] ?? 0);
$beforeId = (int) ($_GET['before_id'] ?? 0);
$limit = min(50, max(10, (int) ($_GET['limit'] ?? 30)));

if ($id <= 0 || $beforeId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Parámetros inválidos']);
    exit;
}

$messages = cw_chat_get_messages_before($conn, $id, $beforeId, $limit);
$hasMore = count($messages) >= $limit;

echo json_encode([
    'success' => true,
    'messages' => $messages,
    'has_more' => $hasMore,
], JSON_UNESCAPED_UNICODE);
