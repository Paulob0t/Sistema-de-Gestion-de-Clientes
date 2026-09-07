<?php
/**
 * Plan Rehab Blog — operado por el cerebro IA integrado.
 *
 * El cerebro conoce, habilita por tandas y deja propuestas en la cola
 * «Rehab blog» (separada de propuestas automáticas). Ejecución IA manual + aprobación humana.
 * Templates: noindex / fuera de sitemap hasta standard|gold.
 */
declare(strict_types=1);

/**
 * Ruta al JSON del plan (sitio público).
 */
function cw_site_ai_blog_rehab_queue_path(): string
{
    static $path = null;
    if ($path !== null) {
        return $path;
    }
    $candidates = [
        dirname(__DIR__, 2) . '/conlineweb.com/includes/blog/rehab-queue.php',
        dirname(__DIR__) . '/../../conlineweb.com/includes/blog/rehab-queue.php',
    ];
    foreach ($candidates as $p) {
        if (is_readable($p)) {
            $path = $p;
            return $path;
        }
    }
    $path = $candidates[0];
    return $path;
}

function cw_site_ai_blog_rehab_load_helpers(): bool
{
    $p = cw_site_ai_blog_rehab_queue_path();
    if (!is_readable($p)) {
        return false;
    }
    require_once $p;
    return function_exists('blog_rehab_plan_summary');
}

/**
 * Brief fijo para system prompts del cerebro / chat / autonomía.
 */
function cw_site_ai_blog_rehab_brief(): string
{
    return <<<'TXT'
=== REHAB BLOG (cerebro integrado · plan por tandas) ===
Cola SEPARADA en Monitor → pestaña «Rehab blog» (NO mezclar con propuestas automáticas).

Objetivo
- Rehabilitar ~684 artículos quality=template → standard|gold con IA MANUAL.
- Mientras sean template: robots noindex,follow y FUERA de sitemap-blog.xml.
- Al terminar cada uno: quality standard/gold + regenerar sitemap-blog → vuelve a indexar.

Cadencia (obligatoria)
- 2 artículos por tanda / día laborable (Lunes–Viernes).
- Sábado y domingo: no se habilita tanda nueva.
- Cada mañana el cerebro HABÍLITA solo la tanda del día (status pending).
- El resto queda status=scheduled (programada) hasta su fecha.

Qué NO hace el cerebro (mientras el plan esté activo)
- NO crea blog_post ni blog_improve nuevos en cola automática: el backlog ya llega ~hasta 2027.
- El blog “nuevo” queda cubierto por la rehabilitación gradual de templates.

Qué SÍ propone el cerebro en cola automática
- URLs cortas comerciales (fases), hubs GEO débiles, mantenimiento/copy, diseño con preview, auditoría.
- En Rehab blog: solo habilitar/recordar la tanda del día.

Operación
- Kind rehab: blog_improve · target_key=rehab:{slug} · manual_ai=true.
- El humano (o Cursor) ejecuta la acción IA del detalle; luego aprueba/aplica en Monitor.
- Cron cerebro: analytics/cron_seo_mexico_ai_propose.php a las 08:00 Lun–Vie (mismo job 8/14/20; rehab solo a las 8) + «Actualizar conocimiento base».

Fundamento
- Anti-thin content / E-E-A-T: FAQs únicas, bloque práctico real, sin clientes/métricas inventadas.
=== FIN REHAB BLOG ===
TXT;
}

/**
 * @return array<string,mixed>
 */
function cw_site_ai_blog_rehab_summary(): array
{
    if (!cw_site_ai_blog_rehab_load_helpers()) {
        return [
            'ok' => false,
            'error' => 'rehab-queue.php no disponible',
            'total' => 0,
        ];
    }
    $sum = blog_rehab_plan_summary();
    $sum['ok'] = true;
    $sum['rules_text'] = cw_site_ai_blog_rehab_brief();
    return $sum;
}

/**
 * Inserta/actualiza una fila rehab en cw_seo_mexico_ai_proposals.
 *
 * @param array<string,mixed> $item
 * @return array{ok:bool,id?:int,status?:string,created?:bool}
 */
function cw_site_ai_blog_rehab_upsert_proposal(mysqli $conn, array $item, string $today): array
{
    require_once __DIR__ . '/cw_seo_mexico_ai.php';
    cw_seo_mexico_ai_proposals_ensure_table($conn);

    $slug = (string) ($item['slug'] ?? '');
    if ($slug === '') {
        return ['ok' => false];
    }
    $sched = (string) ($item['scheduled_for'] ?? '');
    $tanda = (int) ($item['tanda'] ?? 0);
    $isEnabled = $sched !== '' && $sched <= $today;
    $dbStatus = $isEnabled ? 'pending' : 'scheduled';
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
    $rationale = $aiAction !== '' ? $aiAction : ('Rehab template «' . $slug . '» · tanda ' . $tanda . ' · cerebro IA.');
    if (mb_strlen($rationale) < 20) {
        $rationale .= ' Acción IA manual: FAQs únicas + bloque práctico.';
    }
    $summary = $isEnabled
        ? ('HABILITADA por cerebro · tanda ' . $tanda . ' · ' . $sched)
        : ('PROGRAMADA por cerebro · tanda ' . $tanda . ' · se habilita ' . $sched);

    $before = json_encode([
        'quality' => 'template',
        'slug' => $slug,
        'source' => 'rehab-queue',
        'brain' => true,
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
        'brain_owned' => true,
        'batch_enabled' => $isEnabled,
        'quality_target' => 'standard|gold',
        'ai_action' => $aiAction,
        'sitemap_note' => 'noindex/fuera de sitemap hasta rehab + regenerar sitemap-blog.',
    ], JSON_UNESCAPED_UNICODE);
    $detail = "Origen: cerebro IA integrado\nTanda: {$tanda}\nFecha: {$sched}\nEstado: "
        . ($isEnabled ? 'HABILITADA' : 'PROGRAMADA')
        . "\nURL: {$url}\n\n{$rationale}";

    // ¿Ya existe abierta?
    $chk = $conn->prepare(
        "SELECT id, status FROM cw_seo_mexico_ai_proposals
         WHERE target_key = ? AND status IN ('pending','working','scheduled') LIMIT 1"
    );
    if ($chk) {
        $chk->bind_param('s', $targetKey);
        $chk->execute();
        $ex = $chk->get_result()->fetch_assoc();
        $chk->close();
        if ($ex) {
            $id = (int) $ex['id'];
            $old = (string) ($ex['status'] ?? '');
            $newStatus = $old === 'working' ? 'working' : $dbStatus;
            $upd = $conn->prepare(
                'UPDATE cw_seo_mexico_ai_proposals
                 SET status=?, title=?, target_url=?, prompt_summary=?, detail=?, before_json=?, after_json=?
                 WHERE id=?'
            );
            $upd->bind_param('sssssssi', $newStatus, $title, $url, $summary, $detail, $before, $after, $id);
            $upd->execute();
            $upd->close();
            return ['ok' => true, 'id' => $id, 'status' => $newStatus, 'created' => false];
        }
    }

    $kind = 'blog_improve';
    $uid = 0;
    $ins = $conn->prepare(
        'INSERT INTO cw_seo_mexico_ai_proposals
         (kind, status, title, target_key, target_url, prompt_summary, detail, before_json, after_json, created_by, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    if (!$ins) {
        return ['ok' => false];
    }
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
    $id = (int) $ins->insert_id;
    $ins->close();
    return ['ok' => $id > 0, 'id' => $id, 'status' => $dbStatus, 'created' => true];
}

/**
 * El cerebro sincroniza el plan completo a la cola Rehab + conocimiento.
 *
 * @return array{ok:bool,message:string,enabled?:int,scheduled?:int,created?:int,updated?:int}
 */
function cw_site_ai_blog_rehab_brain_sync(mysqli $conn, int $userId = 0, bool $fullResync = false): array
{
    require_once __DIR__ . '/cw_site_ai_brain.php';
    if (!cw_site_ai_blog_rehab_load_helpers()) {
        return ['ok' => false, 'message' => 'Plan rehab no encontrado (rehab-queue.php)'];
    }

    $today = date('Y-m-d');
    $queue = blog_rehab_queue_load();
    $items = is_array($queue['items'] ?? null) ? $queue['items'] : [];

    // Backfill tanda
    $tandaCursor = 0;
    $lastDate = '';
    $needSave = false;
    foreach ($items as &$it) {
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
        $queue['items'] = $items;
        $queue['version'] = 2;
        $queue['rules'] = blog_rehab_plan_rules();
        $queue['tandas'] = $tandaCursor;
        blog_rehab_queue_save($queue);
    }

    if ($fullResync) {
        $conn->query(
            "DELETE FROM cw_seo_mexico_ai_proposals
             WHERE target_key LIKE 'rehab:%'
               AND status IN ('pending','working','processing','review','scheduled')"
        );
    }

    $nowIso = date('c');
    $needSaveEnabled = false;
    foreach ($items as &$itEnable) {
        if (in_array(($itEnable['status'] ?? ''), ['done', 'skipped'], true)) {
            continue;
        }
        $schedEn = (string) ($itEnable['scheduled_for'] ?? '');
        if ($schedEn !== '' && $schedEn <= $today && empty($itEnable['enabled_at'])) {
            $itEnable['enabled_at'] = $nowIso;
            $needSaveEnabled = true;
        }
    }
    unset($itEnable);
    if ($needSaveEnabled) {
        $queue['items'] = $items;
        blog_rehab_queue_save($queue);
    }

    $created = 0;
    $updated = 0;
    $enabled = 0;
    $scheduled = 0;
    foreach ($items as $item) {
        if (in_array(($item['status'] ?? ''), ['done', 'skipped'], true)) {
            continue;
        }
        // fullResync: todo el plan; sync diario: habilitadas + ventana próxima 14 días
        $sched = (string) ($item['scheduled_for'] ?? '');
        if (!$fullResync) {
            $until = date('Y-m-d', strtotime('+14 days'));
            if ($sched > $until) {
                continue;
            }
        }
        $res = cw_site_ai_blog_rehab_upsert_proposal($conn, $item, $today);
        if (empty($res['ok'])) {
            continue;
        }
        if (!empty($res['created'])) {
            $created++;
        } else {
            $updated++;
        }
        if (($res['status'] ?? '') === 'pending' || ($res['status'] ?? '') === 'working') {
            $enabled++;
        } else {
            $scheduled++;
        }
    }

    $sum = blog_rehab_plan_summary($today);
    $planText = cw_site_ai_blog_rehab_brief()
        . "\nEstado vivo ({$today}):"
        . "\n- Plan: " . ($sum['plan_start'] ?? '') . ' → ' . ($sum['plan_end'] ?? '')
        . "\n- Total: " . (int) ($sum['total'] ?? 0) . ' · Tandas: ' . (int) ($sum['tandas_total'] ?? 0)
        . ' · ' . (int) ($sum['per_day'] ?? 2) . '/día laborable'
        . "\n- Habilitadas abiertas: " . (int) ($sum['enabled_open'] ?? 0)
        . ' · Programadas: ' . (int) ($sum['scheduled_open'] ?? 0)
        . ' · Hechas: ' . (int) ($sum['done'] ?? 0);

    if (!empty($sum['today_batch'])) {
        $tb = $sum['today_batch'];
        $planText .= "\n- HOY tanda " . (int) $tb['tanda'] . ' · ' . $tb['date']
            . ' · ' . (int) $tb['count'] . ' artículos (' . (int) $tb['pending'] . ' pending)';
    } elseif (!empty($sum['next_batch'])) {
        $nb = $sum['next_batch'];
        $planText .= "\n- Próxima tanda " . (int) $nb['tanda'] . ' · ' . $nb['date']
            . ' · ' . (int) $nb['count'] . ' artículos';
    }
    $planText .= "\n- Cola UI: Monitor → Rehab blog (queue=rehab). Automáticas = otra pestaña.";

    cw_site_ai_brain_upsert(
        $conn,
        'blog_rehab_plan',
        'strategy',
        'Rehab blog · plan por tandas (cerebro)',
        $planText,
        'brain_rehab',
        100,
        $userId
    );

    $todayLines = 'Tanda del día ' . $today . ': ';
    if (!empty($sum['today_batch'])) {
        $todayLines .= 'Tanda #' . (int) $sum['today_batch']['tanda']
            . ' habilitada · trabajar en pestaña Rehab blog · IA manual.';
    } else {
        $todayLines .= 'Sin tanda hoy (fin de semana o sin cupo). No habilitar extras.';
    }
    cw_site_ai_brain_upsert(
        $conn,
        'blog_rehab_today',
        'ops',
        'Rehab blog · tanda de hoy',
        $todayLines,
        'brain_rehab',
        99,
        $userId
    );

    cw_site_ai_brain_upsert(
        $conn,
        'blog_queues_separation',
        'ops',
        'Dos colas en Monitor + qué propone el cerebro',
        '1) Rehab blog: plan template→gold por tandas (ya agendado ~hasta 2027). '
        . 'El cerebro NO crea blogs nuevos ni blog_improve sueltos mientras haya backlog rehab. '
        . 'Solo habilita la tanda del día + recordatorio. '
        . '2) Propuestas automáticas (sin blogs): hubs GEO, URLs cortas comerciales, '
        . 'mantenimiento/copy whitelist, diseño UI con preview, auditoría/correcciones. '
        . 'Templates: noindex hasta rehab.',
        'brain_rehab',
        100,
        $userId
    );

    cw_site_ai_brain_upsert(
        $conn,
        'brain_priorities_while_rehab',
        'strategy',
        'Prioridades del cerebro mientras dura el plan rehab',
        "Con ~684 templates ya en fila por tandas hasta {$sum['plan_end']}, el cerebro NO debe saturar con más artículos. "
        . "Orden de trabajo:\n"
        . "1) Habilitar tanda rehab del día (2 arts Lun–Vie) y avisar por correo.\n"
        . "2) URLs cortas comerciales (fase activa) si aplica.\n"
        . "3) Reforzar hubs de ciudad débiles (title/H1/meta GEO).\n"
        . "4) Mantenimiento/diseño/admin solo con fundamento y preview.\n"
        . "5) Blogs NUEVOS: prohibido hasta vaciar o pausar explícitamente el plan rehab.\n"
        . 'Publicar siempre con aprobación humana.',
        'brain_rehab',
        100,
        $userId
    );

    cw_site_ai_brain_log(
        $conn,
        'blog_rehab_plan',
        'brain_rehab_sync',
        "full=" . ($fullResync ? '1' : '0') . " created={$created} updated={$updated} enabled={$enabled} scheduled={$scheduled}",
        null,
        $userId
    );

    // Mientras haya backlog rehab: NO generar blogs automáticos (sí hubs/URLs/otros)
    require_once __DIR__ . '/cw_seo_mexico_ai_autonomy.php';
    if (function_exists('cw_seo_mexico_ai_runtime_set')) {
        $backlog = (int) ($sum['enabled_open'] ?? 0) + (int) ($sum['scheduled_open'] ?? 0);
        cw_seo_mexico_ai_runtime_set($conn, 'ai_blog_propose_paused', $backlog > 0 ? '1' : '0');
        // Quitar pausa total si quedó de versiones anteriores (no bloquear hubs/URLs)
        cw_seo_mexico_ai_runtime_set($conn, 'ai_propose_paused', '0');
    }

    return [
        'ok' => true,
        'message' => "Cerebro rehab: creadas {$created}, actualizadas {$updated}, "
            . "habilitadas {$enabled}, programadas {$scheduled}"
            . ' · blogs auto: pausados (plan hasta ' . (string) ($sum['plan_end'] ?? '') . ')',
        'enabled' => $enabled,
        'scheduled' => $scheduled,
        'created' => $created,
        'updated' => $updated,
        'summary' => $sum,
    ];
}
