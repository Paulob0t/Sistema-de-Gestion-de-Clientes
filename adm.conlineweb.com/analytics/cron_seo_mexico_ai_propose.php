<?php
/**
 * Cron autonomía IA — piensa y genera propuestas (NO publica).
 *
 * CLI (cPanel Cron Jobs):
 *   /usr/local/bin/php .../adm.conlineweb.com/analytics/cron_seo_mexico_ai_propose.php
 *
 * HTTP:
 *   https://adm.conlineweb.com/analytics/cron_seo_mexico_ai_propose.php?token=CW_HUB_CRON_SECRET
 *
 * Sugerencia: 2–3 veces al día (ej. 08:00, 14:00, 20:00 hora México)
 *   0 8,14,20 * * * /usr/local/bin/php .../cron_seo_mexico_ai_propose.php >/dev/null 2>&1
 *
 * Rehab blog (correo + habilitar tanda) corre SOLO en la pasada de las 08:00 Lun–Vie.
 */
declare(strict_types=1);

@ini_set('memory_limit', '256M');
@set_time_limit(420);

require_once dirname(__DIR__) . '/conn.php';
require_once dirname(__DIR__) . '/includes/cw_hub_config.php';
require_once dirname(__DIR__) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__) . '/includes/cw_seo_mexico_ai_autonomy.php';

$isCli = PHP_SAPI === 'cli';
$tokenOk = isset($_GET['token']) && defined('CW_HUB_CRON_SECRET')
    && hash_equals(CW_HUB_CRON_SECRET, (string) $_GET['token']);

if (!$isCli && !$tokenOk) {
    http_response_code(403);
    exit('Forbidden');
}

if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}

function seo_ai_cron_out(string $msg): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

$lockDir = dirname(__DIR__) . '/storage';
if (!is_dir($lockDir)) {
    @mkdir($lockDir, 0755, true);
}
$lockFile = $lockDir . '/seo_mexico_ai_propose.lock';
$lockFp = @fopen($lockFile, 'c+');
if ($lockFp === false || !flock($lockFp, LOCK_EX | LOCK_NB)) {
    seo_ai_cron_out('SKIP: otra corrida de propuestas en curso (lock).');
    exit(0);
}
ftruncate($lockFp, 0);
fwrite($lockFp, (string) getmypid() . ' ' . date('c'));
fflush($lockFp);

date_default_timezone_set('America/Mexico_City');

/**
 * Rehab blog via el cron de las 08:00 (mismo job 8/14/20).
 * Solo lun–vie, solo en la ventana de las 8, una vez por día.
 */
function seo_ai_cron_run_blog_rehab(mysqli $conn): void
{
    $dow = (int) date('N');
    $hour = (int) date('G');
    if ($dow >= 6) {
        seo_ai_cron_out('Rehab: fin de semana, skip.');
        return;
    }
    if ($hour < 8 || $hour >= 10) {
        seo_ai_cron_out('Rehab: solo en la corrida de las 08:00 (ahora ' . date('H:i') . ').');
        return;
    }

    $stamp = dirname(__DIR__) . '/storage/blog_rehab_cron_' . date('Y-m-d') . '.ok';
    if (is_file($stamp)) {
        seo_ai_cron_out('Rehab: ya corrió hoy.');
        return;
    }

    require_once dirname(__DIR__) . '/includes/cw_site_ai_blog_rehab.php';
    $sync = cw_site_ai_blog_rehab_brain_sync($conn, 0, false);
    seo_ai_cron_out('Rehab sync: ' . ((empty($sync['ok']) ? 'ERROR ' : 'OK ') . (string) ($sync['message'] ?? '')));

    $code2 = null;
    $siteRemind = dirname(__DIR__, 2) . '/conlineweb.com/includes/blog/rehab-queue-remind.php';
    if (is_readable($siteRemind)) {
        $php = PHP_BINARY !== '' ? PHP_BINARY : 'php';
        passthru(escapeshellarg($php) . ' ' . escapeshellarg($siteRemind), $code2);
        seo_ai_cron_out('Rehab remind_exit=' . (int) $code2);
    } else {
        seo_ai_cron_out('Rehab: SKIP remind (rehab-queue-remind.php no encontrado)');
    }

    @file_put_contents($stamp, date('c') . PHP_EOL);
    if (function_exists('blog_rehab_cron_log_append')) {
        blog_rehab_cron_log_append([
            'ok' => !empty($sync['ok']),
            'sync' => (string) ($sync['message'] ?? ''),
            'remind_exit' => $code2,
        ]);
    } else {
        $logFile = dirname(__DIR__) . '/storage/blog_rehab_cron_log.json';
        $rows = is_file($logFile) ? (json_decode((string) file_get_contents($logFile), true) ?: []) : [];
        $rows[] = [
            'at' => date('c'),
            'date' => date('Y-m-d'),
            'ok' => !empty($sync['ok']),
            'sync' => (string) ($sync['message'] ?? ''),
            'remind_exit' => $code2,
        ];
        @file_put_contents($logFile, json_encode(array_slice($rows, -40), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}

seo_ai_cron_out('=== Cron IA: pensar y proponer (sin publicar) ===');
seo_ai_cron_run_blog_rehab($conn);

$pauseFile = $lockDir . '/seo_mexico_ai_propose.pause';
if (is_file($pauseFile)) {
    $why = trim((string) @file_get_contents($pauseFile));
    seo_ai_cron_out('SKIP: propuestas pausadas'
        . ($why !== '' ? (' — ' . $why) : '')
        . ' (quita storage/seo_mexico_ai_propose.pause para reactivar).');
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    exit(0);
}

try {
    cw_hub_migrate($conn);
    if (!cw_seo_mexico_ai_available()) {
        seo_ai_cron_out('SKIP: OpenAI no configurada.');
        exit(0);
    }

    $result = cw_seo_mexico_ai_autonomy_run($conn, 0, [
        'max_blogs' => 2,
        'max_hubs' => 1,
        'max_improves' => 1,
        'max_pending' => 12,
        'max_actions' => 4,
        'max_short_urls' => 2,
    ]);

    if (!empty($result['skipped'])) {
        seo_ai_cron_out('SKIP: ' . $result['skipped']);
        exit(0);
    }

    if (empty($result['ok']) && empty($result['created'])) {
        seo_ai_cron_out('ERROR: ' . (string) ($result['error'] ?? $result['message'] ?? 'falló'));
        exit(1);
    }

    $created = is_array($result['created'] ?? null) ? $result['created'] : [];
    seo_ai_cron_out('OK planned=' . (int) ($result['planned'] ?? 0)
        . ' created=' . count($created)
        . ' · ' . (string) ($result['message'] ?? ''));
    foreach ($created as $c) {
        seo_ai_cron_out('  + #' . (int) ($c['proposal_id'] ?? 0)
            . ' ' . (string) ($c['kind'] ?? '')
            . ' ' . (string) ($c['url'] ?? ''));
    }
    foreach (($result['errors'] ?? []) as $err) {
        seo_ai_cron_out('  ! ' . (string) $err);
    }
} catch (Throwable $e) {
    seo_ai_cron_out('EXCEPTION: ' . $e->getMessage());
    exit(1);
} finally {
    if (is_resource($lockFp) || (is_object($lockFp) && method_exists($lockFp, 'close'))) {
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
    }
}

seo_ai_cron_out('=== Fin ===');
exit(0);
