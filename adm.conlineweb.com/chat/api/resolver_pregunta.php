<?php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_permissions.php';
require_once dirname(__DIR__, 2) . '/includes/cw_chat_migrate.php';

cw_hub_require('hub.crm.view');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

cw_chat_migrate($conn);

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

$stmt = $conn->prepare('UPDATE cw_chat_preguntas_sin_respuesta SET resuelta = 1, updated_at = NOW() WHERE id = ?');
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    exit;
}
$stmt->bind_param('i', $id);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $ok], JSON_UNESCAPED_UNICODE);
