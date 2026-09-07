<?php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_chat_migrate.php';
require_once dirname(__DIR__, 2) . '/includes/cw_chat_realtime.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false]);
    exit;
}

cw_chat_migrate($conn);

$id = (int) ($_POST['id'] ?? 0);
$typing = (int) ($_POST['typing'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false]);
    exit;
}

cw_chat_set_typing($conn, $id, 'agente', $typing === 1);
cw_chat_touch_presence($conn, $id, 'agente');

echo json_encode(['success' => true]);
