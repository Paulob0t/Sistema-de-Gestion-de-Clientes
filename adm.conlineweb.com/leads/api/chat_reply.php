<?php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_chat_migrate.php';
require_once dirname(__DIR__, 2) . '/includes/cw_chat_service.php';
require_once dirname(__DIR__, 2) . '/includes/cw_chat_alerts.php';
require_once dirname(__DIR__) . '/includes/inbox_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

cw_chat_migrate($conn);

$id = (int) ($_POST['id'] ?? 0);
$mensaje = trim((string) ($_POST['mensaje'] ?? ''));
$uid = cw_inbox_uid();

if ($id <= 0 || $mensaje === '') {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$conv = cw_chat_get_conversation($conn, $id);
if (!$conv || ($conv['estado'] ?? '') === 'cerrada') {
    echo json_encode(['success' => false, 'message' => 'Conversación no disponible']);
    exit;
}

if (($conv['estado'] ?? '') === 'pendiente_humano') {
    cw_chat_assign_agent($conn, $id, $uid);
} elseif (cw_chat_bot_activo($conv['estado'] ?? '')) {
    $stmt = $conn->prepare("UPDATE cw_chat_conversaciones SET estado = 'humano', responsable_id = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('ii', $uid, $id);
        $stmt->execute();
        $stmt->close();
    }
    if (!function_exists('cw_chat_alert_cancel')) {
        require_once dirname(__DIR__, 2) . '/includes/cw_chat_alerts.php';
    }
    cw_chat_alert_cancel($conn, $id, 'respuesta_agente');
}

$msgId = cw_chat_add_message($conn, $id, 'agente', $uid, $mensaje);
cw_chat_alert_cancel($conn, $id, 'respuesta_agente');

$formatted = null;
if ($msgId > 0) {
    $stmt = $conn->prepare('SELECT m.*, COALESCE(a.Nombre, l.usuario) AS remitente_nombre
        FROM cw_chat_mensajes m
        LEFT JOIN agentes a ON m.remitente_tipo = "agente" AND m.remitente_id = a.Idusu
        LEFT JOIN login l ON m.remitente_tipo = "agente" AND m.remitente_id = l.id
        WHERE m.id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $msgId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $formatted = cw_chat_format_message_row($row);
        }
    }
}

echo json_encode([
    'success' => true,
    'message_id' => $msgId,
    'message' => $formatted,
    'text' => 'Mensaje enviado',
    'estado' => [
        'estado' => ($updated = cw_chat_get_conversation($conn, $id)) ? ($updated['estado'] ?? 'humano') : 'humano',
        'estado_label' => cw_chat_estado_label(($updated['estado'] ?? 'humano')),
        'estado_class' => cw_chat_estado_class(($updated['estado'] ?? 'humano')),
        'bot_activo' => cw_chat_bot_activo(($updated['estado'] ?? 'humano')),
    ],
], JSON_UNESCAPED_UNICODE);
