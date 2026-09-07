<?php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_chat_migrate.php';
require_once dirname(__DIR__, 2) . '/includes/cw_chat_service.php';
require_once dirname(__DIR__, 2) . '/includes/cw_chat_realtime.php';

try {
    cw_chat_migrate($conn);
    cw_chat_ensure_soft_delete_columns($conn);

    $q = trim((string) ($_GET['q'] ?? ''));
    $filtro = trim((string) ($_GET['filtro'] ?? ''));
    $scope = trim((string) ($_GET['scope'] ?? 'all'));
    if ($scope !== 'web_sessions') {
        $scope = 'all';
    }

    $rows = cw_chat_list_admin($conn, $q, $filtro, $scope);
    $stats = cw_chat_stats_list($conn, $rows);
    $global = cw_chat_global_counts($conn);
    $stats = array_merge($stats, $global);

    echo json_encode(['success' => true, 'data' => $rows, 'stats' => $stats], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'No se pudo cargar la lista de chats',
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
