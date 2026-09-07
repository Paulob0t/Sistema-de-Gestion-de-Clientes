<?php
/**
 * Sincroniza el plan rehab blog completo → DataTable (pestaña Rehab blog).
 *
 * Reglas:
 *   - 2 artículos / día laborable (Lun–Vie) = 1 tanda
 *   - Fecha <= hoy → status pending (habilitada, listo para IA manual)
 *   - Fecha > hoy  → status scheduled (programada, aún no habilitada)
 *   - Templates siguen noindex / fuera de sitemap hasta rehab + regenerar
 *
 * Uso:
 *   php analytics/cli_sync_blog_rehab_queue.php
 *   php analytics/cli_sync_blog_rehab_queue.php --reset-today
 *   php analytics/cli_sync_blog_rehab_queue.php --enable-only
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/conn.php';
require_once dirname(__DIR__) . '/includes/cw_seo_mexico_ai.php';
require_once dirname(__DIR__) . '/includes/cw_seo_mexico_ai_autonomy.php';

$siteRoot = dirname(__DIR__, 2) . '/conlineweb.com';
require_once $siteRoot . '/includes/blog/rehab-queue.php';

$resetToday = false;
$enableOnly = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--reset-today') {
        $resetToday = true;
    } elseif ($arg === '--enable-only') {
        $enableOnly = true;
    }
}

cw_seo_mexico_ai_proposals_ensure_table($conn);
cw_seo_mexico_ai_runtime_ensure_table($conn);
cw_seo_mexico_ai_runtime_set($conn, 'ai_propose_paused', '1');

$pauseFile = dirname(__DIR__) . '/storage/seo_mexico_ai_propose.pause';
if (!is_dir(dirname($pauseFile))) {
    @mkdir(dirname($pauseFile), 0755, true);
}
if (!is_file($pauseFile)) {
    file_put_contents($pauseFile, "Pausa: prioridad rehab blog por tandas.\n");
}

$today = date('Y-m-d');

if ($resetToday) {
    // Regenera plan empezando hoy (incluye fin de semana solo en el primer día si aplica)
    $day = new DateTimeImmutable($today);
    $perDay = BLOG_REHAB_PER_DAY;
    $candidates = blog_rehab_candidate_posts();
    $items = [];
    $countOnDay = 0;
    $tanda = 1;
    foreach ($candidates as $i => $post) {
        if ($countOnDay >= $perDay) {
            $day = blog_rehab_next_workday($day->modify('+1 day'));
            $countOnDay = 0;
            $tanda++;
        }
        $items[] = [
            'id' => 'rehab-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
            'tanda' => $tanda,
            'slug' => (string) $post['slug'],
            'title' => (string) ($post['title'] ?? $post['slug']),
            'category' => (string) ($post['category'] ?? ''),
            'keyword' => (string) ($post['keyword'] ?? ''),
            'scheduled_for' => $day->format('Y-m-d'),
            'ai_action' => blog_rehab_ai_action($post),
            'status' => 'pending',
            'reminded_at' => null,
            'done_at' => null,
            'enabled_at' => null,
            'url' => cw_site_url('blog/articulo/' . $post['slug'] . '/'),
        ];
        $countOnDay++;
    }
    $data = [
        'version' => 2,
        'reset_at' => date('c'),
        'notify_email' => BLOG_REHAB_NOTIFY_EMAIL,
        'per_day' => $perDay,
        'rules' => blog_rehab_plan_rules(),
        'plan_start' => $items[0]['scheduled_for'] ?? $today,
        'plan_end' => $items[count($items) - 1]['scheduled_for'] ?? $today,
        'total' => count($items),
        'tandas' => (int) ($items[count($items) - 1]['tanda'] ?? $tanda),
        'items' => $items,
    ];
    blog_rehab_queue_save($data);
    echo "plan_reset today={$today} total=" . count($items) . " tandas={$data['tandas']}\n";
}

$queue = blog_rehab_queue_load();
// Asegura campo tanda si el JSON es viejo
$needSave = false;
$tandaCursor = 0;
$lastDate = '';
foreach ($queue['items'] as &$it) {
    $d = (string) ($it['scheduled_for'] ?? '');
    if ($d !== $lastDate) {
        $tandaCursor++;
        $lastDate = $d;
    }
    if (empty($it['tanda'])) {
        $it['tanda'] = $tandaCursor;
        $needSave = true;
    }
}
unset($it);
if ($needSave) {
    $queue['version'] = 2;
    $queue['rules'] = blog_rehab_plan_rules();
    $queue['tandas'] = $tandaCursor;
    blog_rehab_queue_save($queue);
    echo "tandas_backfilled={$tandaCursor}\n";
}

if (!$enableOnly) {
    // Limpia propuestas rehab abiertas previas (pending/scheduled/working)
    $conn->query(
        "DELETE FROM cw_seo_mexico_ai_proposals
         WHERE target_key LIKE 'rehab:%'
           AND status IN ('pending','working','processing','review','scheduled')"
    );
    echo 'cleared_rehab_open=' . $conn->affected_rows . "\n";
}

$created = 0;
$updated = 0;
$enabled = 0;
$scheduled = 0;

foreach (($queue['items'] ?? []) as $item) {
    if (in_array(($item['status'] ?? ''), ['done', 'skipped'], true)) {
        continue;
    }
    $slug = (string) ($item['slug'] ?? '');
    if ($slug === '') {
        continue;
    }
    $sched = (string) ($item['scheduled_for'] ?? '');
    $tanda = (int) ($item['tanda'] ?? 0);
    $isEnabled = $sched !== '' && $sched <= $today;
    $dbStatus = $isEnabled ? 'pending' : 'scheduled';
    if ($isEnabled) {
        $enabled++;
    } else {
        $scheduled++;
    }

    $targetKey = 'rehab:' . $slug;
    $title = 'Tanda ' . $tanda . ' · ' . $sched . ' · ' . (string) ($item['title'] ?? $slug);
    $url = (string) ($item['url'] ?? '');
    if ($url !== '' && !str_starts_with($url, 'http')) {
        $url = 'https://conlineweb.com' . (str_starts_with($url, '/') ? $url : '/' . $url);
    }
    if ($url === '') {
        $url = 'https://conlineweb.com/blog/articulo/' . $slug . '/';
    }
    $aiAction = (string) ($item['ai_action'] ?? '');
    $rationale = $aiAction !== '' ? $aiAction : ('Rehab template «' . $slug . '» · tanda ' . $tanda);
    if (mb_strlen($rationale) < 20) {
        $rationale .= ' Acción IA manual: FAQs + bloque práctico único.';
    }
    $summary = $isEnabled
        ? ('HABILITADA · tanda ' . $tanda . ' · ' . $sched . ' · IA manual')
        : ('PROGRAMADA · tanda ' . $tanda . ' · se habilita el ' . $sched);

    $before = json_encode([
        'quality' => 'template',
        'slug' => $slug,
        'source' => 'rehab-queue',
        'tanda' => $tanda,
    ], JSON_UNESCAPED_UNICODE);
    $after = json_encode([
        'rationale' => $rationale,
        'slug' => $slug,
        'url' => $url,
        'scheduled_for' => $sched,
        'tanda' => $tanda,
        'rehab_id' => (string) ($item['id'] ?? ''),
        'manual_ai' => true,
        'batch_enabled' => $isEnabled,
        'quality_target' => 'standard|gold',
        'ai_action' => $aiAction,
        'sitemap_note' => 'Sigue noindex/fuera de sitemap hasta rehab + regenerar sitemap-blog.',
    ], JSON_UNESCAPED_UNICODE);
    $detail = "Tanda: {$tanda}\nFecha: {$sched}\nEstado: "
        . ($isEnabled ? 'HABILITADA (trabajar hoy/atrasada)' : 'PROGRAMADA (aún no)')
        . "\nURL: {$url}\n\n{$rationale}";

    if ($enableOnly) {
        // Solo promociona scheduled → pending cuando llega la fecha
        if (!$isEnabled) {
            continue;
        }
        $stmt = $conn->prepare(
            "UPDATE cw_seo_mexico_ai_proposals
             SET status='pending', title=?, prompt_summary=?, detail=?, after_json=?, before_json=?
             WHERE target_key=? AND status='scheduled'"
        );
        $stmt->bind_param('ssssss', $title, $summary, $detail, $after, $before, $targetKey);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $updated += $stmt->affected_rows;
        }
        $stmt->close();
        continue;
    }

    $kind = 'blog_improve';
    $uid = 0;
    $ins = $conn->prepare(
        'INSERT INTO cw_seo_mexico_ai_proposals
         (kind, status, title, target_key, target_url, prompt_summary, detail, before_json, after_json, created_by, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $ins->bind_param(
        'sssssssssi',
        $kind,
        $dbStatus,
        $title,
        $targetKey,
        $url,
        $summary,
        $detail,
        $before,
        $after,
        $uid
    );
    $ins->execute();
    if ($ins->insert_id > 0) {
        $created++;
    }
    $ins->close();
}

$summaryPlan = blog_rehab_plan_summary($today);
echo "created={$created} updated={$updated}\n";
echo "enabled_open={$enabled} scheduled={$scheduled}\n";
echo "plan={$summaryPlan['plan_start']} → {$summaryPlan['plan_end']} · tandas={$summaryPlan['tandas_total']} · per_day={$summaryPlan['per_day']}\n";
if (!empty($summaryPlan['today_batch'])) {
    $tb = $summaryPlan['today_batch'];
    echo "today_tanda={$tb['tanda']} date={$tb['date']} count={$tb['count']}\n";
}
if (!empty($summaryPlan['next_batch'])) {
    $nb = $summaryPlan['next_batch'];
    echo "next_tanda={$nb['tanda']} date={$nb['date']} count={$nb['count']}\n";
}
exit(0);
