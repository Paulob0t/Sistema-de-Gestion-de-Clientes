<?php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_permissions.php';
require_once dirname(__DIR__, 2) . '/includes/cw_chat_migrate.php';
require_once dirname(__DIR__, 2) . '/includes/cw_chat_seed_pro.php';
require_once dirname(__DIR__, 2) . '/includes/cw_chatbot_service.php';

cw_hub_require('hub.crm.view');
cw_chat_migrate($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $inserted = cw_chat_seed_pro($conn);
    cw_chatbot_invalidate_cache();

    $total = 0;
    $r = $conn->query('SELECT COUNT(*) c FROM cw_chat_conocimiento WHERE activo = 1');
    if ($r) {
        $total = (int) ($r->fetch_assoc()['c'] ?? 0);
    }

    echo json_encode([
        'success' => true,
        'message' => $inserted > 0
            ? "Se importaron $inserted nuevas respuestas."
            : 'No hay entradas nuevas por importar (ya existen).',
        'inserted' => $inserted,
        'total_activas' => $total,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al importar: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
