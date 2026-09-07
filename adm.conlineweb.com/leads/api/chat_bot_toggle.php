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
$action = trim((string) ($_POST['action'] ?? ''));
$uid = cw_inbox_uid();

if ($id <= 0 || !in_array($action, ['enable', 'disable'], true)) {
    echo json_encode(['success' => false, 'message' => 'Parámetros inválidos']);
    exit;
}

try {
    $result = cw_chat_set_bot_enabled($conn, $id, $action === 'enable', $uid > 0 ? $uid : null);

    $msgId = 0;
    $stmt = $conn->prepare('SELECT id FROM cw_chat_mensajes WHERE conversacion_id = ? ORDER BY id DESC LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $msgId = (int) ($row['id'] ?? 0);
    }

    $formatted = null;
    if ($msgId > 0) {
        $mStmt = $conn->prepare('SELECT m.*, NULL AS remitente_nombre FROM cw_chat_mensajes m WHERE m.id = ? LIMIT 1');
        if ($mStmt) {
            $mStmt->bind_param('i', $msgId);
            $mStmt->execute();
            $mRow = $mStmt->get_result()->fetch_assoc();
            $mStmt->close();
            if ($mRow) {
                $mRow['remitente_nombre'] = 'Sistema';
                $formatted = cw_chat_format_message_row($mRow);
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => $action === 'enable' ? 'Chatbot activado' : 'Chatbot desactivado — control humano',
        'estado' => $result,
        'system_message' => $formatted,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
