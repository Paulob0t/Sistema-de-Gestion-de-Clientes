<?php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_chat_migrate.php';
require_once dirname(__DIR__, 2) . '/includes/cw_chat_service.php';
require_once dirname(__DIR__) . '/includes/inbox_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

cw_chat_migrate($conn);

$id = (int) ($_POST['id'] ?? 0);
$uid = cw_inbox_uid();

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

cw_chat_close($conn, $id, $uid);

echo json_encode(['success' => true, 'message' => 'Conversación cerrada']);
