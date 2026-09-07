<?php
/**
 * Cron auditoría SEO México — pensado para cPanel productivo.
 *
 * CLI (recomendado en cPanel → Cron Jobs):
 *   /usr/local/bin/php /home/USUARIO/public_html/adm.conlineweb.com/analytics/cron_seo_mexico_audit.php
 *
 * HTTP (alternativa si el hosting no permite PHP CLI en cron):
 *   https://adm.conlineweb.com/analytics/cron_seo_mexico_audit.php?token=CW_HUB_CRON_SECRET
 *
 * Sugerencia cPanel: 1 vez al día a las 03:15 (hora México)
 *   15 3 * * * /usr/local/bin/php .../cron_seo_mexico_audit.php >/dev/null 2>&1
 */
declare(strict_types=1);

@ini_set('memory_limit', '256M');
@set_time_limit(300);

require_once dirname(__DIR__) . '/conn.php';
require_once dirname(__DIR__) . '/includes/cw_hub_config.php';
require_once dirname(__DIR__) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__) . '/includes/cw_seo_mexico_audit.php';
require_once dirname(__DIR__) . '/includes/cw_seo_mexico_ai.php';

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

function seo_cron_out(string $msg): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

// Lock anti-solape (cPanel a veces dispara 2 veces)
$lockDir = dirname(__DIR__) . '/storage';
if (!is_dir($lockDir)) {
    @mkdir($lockDir, 0755, true);
}
$lockFile = $lockDir . '/seo_mexico_audit.lock';
$lockFp = @fopen($lockFile, 'c+');
if ($lockFp === false || !flock($lockFp, LOCK_EX | LOCK_NB)) {
    seo_cron_out('SKIP: otra auditoría está en curso (lock).');
    exit(0);
}
ftruncate($lockFp, 0);
fwrite($lockFp, (string) getmypid() . ' ' . date('c'));
fflush($lockFp);

seo_cron_out('=== Cron SEO México Audit (cPanel) ===');

try {
    cw_hub_migrate($conn);
    $result = cw_seo_mexico_audit_run($conn, 0, 'mexico_priority', [
        'source' => 'cron',
        'time_budget' => 300,
        'multisource_deep' => true,
        'defer_autofix' => true,
        'external_ai' => true,
    ]);

    if (empty($result['ok'])) {
        seo_cron_out('ERROR: ' . (string) ($result['error'] ?? 'falló'));
        exit(1);
    }

    $fixQ = cw_seo_mexico_ai_enqueue_open_audit_fixes($conn, 0, 30);
    $extQ = cw_seo_mexico_ai_enqueue_open_external_tasks($conn, 0, 15);

    $h = is_array($result['health'] ?? null) ? $result['health'] : [];
    $extN = (int) (($result['summary']['external_seo_geo']['findings'] ?? 0));
    seo_cron_out('OK run #' . (int) ($result['run_id'] ?? 0)
        . ' status=' . (string) ($result['status'] ?? '')
        . ' urls=' . (int) ($result['urls_scanned'] ?? 0) . '/' . (int) ($result['urls_total'] ?? 0)
        . ' hallazgos=' . (int) ($result['findings_open'] ?? 0)
        . ' externos=' . $extN
        . ' prop_fix=' . (int) ($fixQ['queued'] ?? 0)
        . ' prop_ext=' . (int) ($extQ['queued'] ?? 0)
        . ' salud_avg=' . (int) ($h['score_avg'] ?? 0)
        . ' salud_min=' . (int) ($h['score_min'] ?? 0));
} catch (Throwable $e) {
    seo_cron_out('EXCEPTION: ' . $e->getMessage());
    exit(1);
} finally {
    if (is_resource($lockFp)) {
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
    }
}

seo_cron_out('=== Fin ===');
exit(0);
