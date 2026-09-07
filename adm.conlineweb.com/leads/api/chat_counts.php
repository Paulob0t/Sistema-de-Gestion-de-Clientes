<?php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_chat_migrate.php';
require_once dirname(__DIR__, 2) . '/includes/cw_chat_realtime.php';

cw_chat_migrate($conn);
echo json_encode(['success' => true, 'counts' => cw_chat_global_counts($conn)], JSON_UNESCAPED_UNICODE);
