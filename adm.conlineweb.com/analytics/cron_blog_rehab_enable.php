<?php
/**
 * Cron diario del cerebro: habilita tanda Rehab blog + recordatorio.
 *
 * Preferido: va dentro del cron de las 08:00
 *   analytics/cron_seo_mexico_ai_propose.php (0 8,14,20 — rehab solo a las 08:00 Lun–Vie)
 *
 * Este archivo queda como fallback si se llama aparte:
 *   0 8 * * 1-5 php .../adm.conlineweb.com/analytics/cron_blog_rehab_enable.php
 */
declare(strict_types=1);

@ini_set('memory_limit', '256M');
@set_time_limit(300);

$isCli = PHP_SAPI === 'cli';
require_once dirname(__DIR__) . '/conn.php';
require_once dirname(__DIR__) . '/includes/cw_hub_config.php';
require_once dirname(__DIR__) . '/includes/cw_site_ai_brain.php';
require_once dirname(__DIR__) . '/includes/cw_site_ai_blog_rehab.php';

$tokenOk = isset($_GET['token']) && defined('CW_HUB_CRON_SECRET')
    && hash_equals(CW_HUB_CRON_SECRET, (string) $_GET['token']);
if (!$isCli && !$tokenOk) {
    http_response_code(403);
    exit('Forbidden');
}
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}

function rehab_cron_out(string $msg): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

rehab_cron_out('=== Cerebro IA · habilitar tanda Rehab blog ===');

$sync = cw_site_ai_blog_rehab_brain_sync($conn, 0, false);
rehab_cron_out(($sync['ok'] ?? false) ? ('OK ' . ($sync['message'] ?? '')) : ('ERROR ' . ($sync['message'] ?? '')));

$siteRemind = dirname(__DIR__, 2) . '/conlineweb.com/includes/blog/rehab-queue-remind.php';
if (is_readable($siteRemind)) {
    $php = PHP_BINARY !== '' ? PHP_BINARY : 'php';
    passthru(escapeshellarg($php) . ' ' . escapeshellarg($siteRemind), $code2);
    rehab_cron_out('remind_exit=' . (int) $code2);
} else {
    rehab_cron_out('SKIP remind: rehab-queue-remind.php no encontrado');
}

rehab_cron_out('=== Fin ===');
exit(!empty($sync['ok']) ? 0 : 1);
