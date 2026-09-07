<?php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_permissions.php';
require_once dirname(__DIR__, 2) . '/includes/cw_chat_migrate.php';
require_once dirname(__DIR__, 2) . '/includes/cw_chatbot_service.php';

cw_hub_require('hub.crm.view');
cw_chat_migrate($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$permanent = (int) ($_POST['permanent'] ?? 0) === 1;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    if ($permanent) {
        $stmt = $conn->prepare('DELETE FROM cw_chat_conocimiento WHERE id = ?');
        if (!$stmt) {
            throw new RuntimeException('No se pudo eliminar');
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        $message = $ok ? 'Respuesta eliminada permanentemente' : 'No se encontró el registro';
    } else {
        $stmt = $conn->prepare('UPDATE cw_chat_conocimiento SET activo = 0, updated_at = NOW() WHERE id = ?');
        if (!$stmt) {
            throw new RuntimeException('No se pudo desactivar');
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        $message = $ok ? 'Respuesta desactivada' : 'No se encontró el registro';
    }

    cw_chatbot_invalidate_cache();

    echo json_encode(['success' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
