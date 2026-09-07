<?php
/**
 * Cron recordatorios comerciales — ejecutar cada 5–15 min.
 * CLI: php analytics/cron_recordatorios.php
 * HTTP: /analytics/cron_recordatorios.php?token=SECRET
 */
require_once dirname(__DIR__) . '/conn.php';
require_once dirname(__DIR__) . '/includes/cw_hub_config.php';
require_once dirname(__DIR__) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__) . '/includes/cw_hub_notify.php';

$isCli = php_sapi_name() === 'cli';
$tokenOk = isset($_GET['token']) && hash_equals(CW_HUB_CRON_SECRET, (string) $_GET['token']);
if (!$isCli && !$tokenOk) {
    http_response_code(403);
    exit('Forbidden');
}

if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}

cw_hub_migrate($conn);

function cron_out(string $msg): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

cron_out('=== Cron recordatorios Hub ===');

$pending = cw_hub_pending_reminders($conn, 30);
cron_out('Pendientes: ' . count($pending));

foreach ($pending as $act) {
    $leadId = (int) $act['lead_id'];
    $stmt = $conn->prepare('SELECT * FROM leads WHERE id = ? AND eliminado = 0 LIMIT 1');
    $stmt->bind_param('i', $leadId);
    $stmt->execute();
    $lead = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$lead) {
        continue;
    }

    cw_hub_notify_reminder($conn, $act, $lead);

    $aid = (int) $act['id'];
    $upd = $conn->prepare('UPDATE cw_lead_actividades SET recordatorio_notificado = 1 WHERE id = ?');
    $upd->bind_param('i', $aid);
    $upd->execute();
    $upd->close();

    cron_out("Notificado lead #{$leadId} — actividad #{$aid}");
}

cron_out('=== Fin ===');
