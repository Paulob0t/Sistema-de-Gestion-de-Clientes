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
require_once dirname(__DIR__, 3) . '/adm.conlineweb.com/includes/cw_chat_migrate.php';
require_once dirname(__DIR__, 3) . '/adm.conlineweb.com/includes/cw_chatbot_live_data.php';

$clienteId = (int) $_SESSION['uid'];
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

try {
    cw_chat_migrate($conn);
    $withinDays = max(1, min(30, (int) ($_GET['days'] ?? 7)));
    $alert = cw_chatbot_build_expiry_alert_payload($conn, $clienteId, $withinDays);

    echo json_encode([
        'success' => true,
        'expiry_alert' => $alert,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al consultar avisos']);
}
