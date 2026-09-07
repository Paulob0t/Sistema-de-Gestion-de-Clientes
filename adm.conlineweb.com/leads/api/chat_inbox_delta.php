<?php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_chat_migrate.php';
require_once dirname(__DIR__, 2) . '/includes/cw_chat_realtime.php';

cw_chat_migrate($conn);

$since = trim((string) ($_GET['since'] ?? ''));
if ($since === '') {
    $since = date('Y-m-d H:i:s', time() - 120);
}

$rows = cw_chat_list_delta($conn, $since, 40);
$counts = cw_chat_global_counts($conn);

echo json_encode([
    'success' => true,
    'data' => $rows,
    'counts' => $counts,
    'server_time' => date('Y-m-d H:i:s'),
], JSON_UNESCAPED_UNICODE);
