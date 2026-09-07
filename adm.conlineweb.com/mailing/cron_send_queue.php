<?php
/**
 * Cron: procesa cola de mailing programada.
 * CLI: php mailing/cron_send_queue.php
 * HTTP: /mailing/cron_send_queue.php?token=MAILING_CRON_2026
 */
declare(strict_types=1);

define('MAILING_CRON_SECRET', 'MAILING_CRON_2026');

$isCli = (PHP_SAPI === 'cli');
$tokenOk = isset($_GET['token']) && is_string($_GET['token']) && hash_equals(MAILING_CRON_SECRET, $_GET['token']);
if (!$isCli && !$tokenOk) {
    http_response_code(403);
    exit("Acceso no autorizado\n");
}

require_once dirname(__DIR__) . '/conn.php';
require_once dirname(__DIR__) . '/includes/cw_mailing_migrate.php';
require_once dirname(__DIR__) . '/includes/cw_mailing_schedule.php';

if (!($conn instanceof mysqli)) {
    fwrite(STDERR, "Sin conexión\n");
    exit(1);
}

cw_mailing_migrate($conn);
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 40;
$res = cw_mailing_process_queue($conn, $limit);

$out = json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
if ($isCli) {
    echo $out;
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo $out;
}
