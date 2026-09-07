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
require_once dirname(__DIR__, 3) . '/adm.conlineweb.com/includes/cw_chatbot_service.php';
require_once dirname(__DIR__, 3) . '/adm.conlineweb.com/includes/cw_chatbot_live_data.php';

$clienteId = (int) $_SESSION['uid'];
$uuid = trim((string) ($_GET['uuid'] ?? $_POST['uuid'] ?? ''));

try {
    cw_chat_migrate($conn);
    $result = cw_chat_get_or_create_conversation($conn, $clienteId, $uuid !== '' ? $uuid : null);
    $conv = $result['conversation'];
    $convId = (int) ($conv['id'] ?? 0);

    if ($result['created'] && $convId > 0) {
        // Sin correo inmediato: solo se alertará si el cliente escribe y nadie atiende en 5 min.
    }

    $messages = $convId > 0 ? cw_chat_get_messages_recent($conn, $convId, 40) : [];
    $botName = cw_chatbot_get_bot_name($conn);
    $enHorario = cw_chat_is_business_hours($conn);
    $expiryAlert = cw_chatbot_build_expiry_alert_payload($conn, $clienteId, 7);

    echo json_encode([
        'success' => true,
        'conversation' => [
            'id' => $convId,
            'uuid' => $conv['uuid'] ?? '',
            'estado' => $conv['estado'] ?? 'bot',
            'estado_label' => cw_chat_estado_label($conv['estado'] ?? 'bot'),
        ],
        'messages' => $messages,
        'bot_name' => $botName,
        'business_hours' => $enHorario,
        'created' => $result['created'],
        'expiry_alert' => $expiryAlert,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al iniciar chat']);
}
