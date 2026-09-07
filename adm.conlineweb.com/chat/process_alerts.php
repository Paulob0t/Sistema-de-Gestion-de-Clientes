<?php
/**
 * Procesa alertas de correo pendientes (ejecutar vía cron cada 1-2 min).
 * También se invoca oportunísticamente desde los streams SSE.
 */
require_once dirname(__DIR__) . '/conn.php';
require_once dirname(__DIR__) . '/includes/cw_chat_migrate.php';
require_once dirname(__DIR__) . '/includes/cw_chat_alerts.php';

cw_chat_migrate($conn);

$sent = cw_chat_alert_process_due($conn, 30);

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true, 'sent' => $sent, 'time' => date('c')], JSON_UNESCAPED_UNICODE);
