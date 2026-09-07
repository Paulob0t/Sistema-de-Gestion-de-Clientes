<?php
/**
 * Checklist SEO México — catálogo + persistencia en DB.
 */
require_once __DIR__ . '/cw_seo_mexico_checklist_catalog_data.php';

/**
 * code = correcciones en sitio/admin/AutoFix | external = GSC, GBP, ops, contenido humano
 */
function cw_seo_mexico_checklist_normalize_execution($execution): string
{
    $e = strtolower(trim((string) $execution));
    return $e === 'external' ? 'external' : 'code';
}

/**
 * @param list<array<string,mixed>> $rows
 * @return list<array<string,mixed>>
 */
function cw_seo_mexico_checklist_filter_by_execution(array $rows, string $execution): array
{
    $want = cw_seo_mexico_checklist_normalize_execution($execution);
    $out = [];
    foreach ($rows as $row) {
        $exec = cw_seo_mexico_checklist_normalize_execution($row['execution'] ?? 'code');
        if ($exec === $want) {
            $out[] = $row;
        }
    }
    return $out;
}

/**
 * Normaliza el plan de ejecución de una tarea del catálogo.
 *
 * @param mixed $plan
 * @return array{can_start_now:bool,start:?string,due:?string,effort:string,window:string,note:string}|null
 */
function cw_seo_mexico_checklist_normalize_plan($plan): ?array
{
    if (!is_array($plan)) {
        return null;
    }
    $start = trim((string) ($plan['start'] ?? ''));
    $due = trim((string) ($plan['due'] ?? ''));
    if ($start === '' && $due === '') {
        return null;
    }

    return [
        'can_start_now' => !empty($plan['can_start_now']),
        'start' => $start !== '' ? $start : null,
        'due' => $due !== '' ? $due : null,
        'effort' => trim((string) ($plan['effort'] ?? '')),
        'window' => trim((string) ($plan['window'] ?? '')),
        'note' => trim((string) ($plan['note'] ?? '')),
    ];
}

/**
 * @return list<array<string,mixed>>
 */
function cw_seo_mexico_checklist_catalog(): array
{
    $phases = [
        '01_fundamentos' => '1 · Fundamentos (hacer primero)',
        '02_contenido'   => '2 · Contenido por plaza',
        '03_tecnico'     => '3 · Técnico / sitemap',
        '04_autoridad'   => '4 · Autoridad y reputación',
        '05_medicion'    => '5 · Medición y escala',
    ];

    $items = cw_seo_mexico_checklist_catalog_items();
    foreach ($items as &$item) {
        $item['phase_label'] = $phases[$item['phase']] ?? $item['phase'];
        if (!isset($item['correction'])) {
            $item['correction'] = '';
        }
        $item['execution'] = cw_seo_mexico_checklist_normalize_execution($item['execution'] ?? 'code');
        $item['plan'] = cw_seo_mexico_checklist_normalize_plan($item['plan'] ?? null);
    }
    unset($item);

    return $items;
}

/**
 * @return array<string, array<string,mixed>>
 */
function cw_seo_mexico_checklist_catalog_map(): array
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (cw_seo_mexico_checklist_catalog() as $item) {
            $map[(string) $item['key']] = $item;
        }
    }
    return $map;
}

function cw_seo_mexico_checklist_sync(mysqli $conn): void
{
    $stmt = $conn->prepare(
        'INSERT INTO cw_seo_mexico_checklist (task_key, phase, title, description, sort_order, updated_at)
         VALUES (?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
            phase = VALUES(phase),
            title = VALUES(title),
            description = VALUES(description),
            sort_order = VALUES(sort_order),
            updated_at = NOW()'
    );
    if (!$stmt) {
        return;
    }

    foreach (cw_seo_mexico_checklist_catalog() as $item) {
        $key = $item['key'];
        $phase = $item['phase'];
        $title = $item['title'];
        $desc = $item['description'];
        $sort = (int) $item['sort'];
        $stmt->bind_param('ssssi', $key, $phase, $title, $desc, $sort);
        $stmt->execute();
    }
    $stmt->close();
}

/**
 * @return list<array<string,mixed>>
 */
function cw_seo_mexico_checklist_load(mysqli $conn): array
{
    cw_seo_mexico_checklist_sync($conn);

    $rows = [];
    $res = $conn->query(
        'SELECT task_key, phase, title, description, sort_order, done, done_at, done_by, notes, detail_json, updated_at
         FROM cw_seo_mexico_checklist
         ORDER BY phase ASC, sort_order ASC, task_key ASC'
    );
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        $res->free();
    }

    $catalogMap = cw_seo_mexico_checklist_catalog_map();
    $labels = [];
    foreach ($catalogMap as $c) {
        $labels[$c['phase']] = $c['phase_label'];
    }
    foreach ($rows as &$row) {
        $key = (string) ($row['task_key'] ?? '');
        $cat = $catalogMap[$key] ?? null;
        $phaseLabels = $labels + [
            '06_auditoria_viva' => '6 · Auditoría en vivo (auto)',
            '01_fundamentos' => '1 · Fundamentos',
            '02_contenido' => '2 · Contenido',
            '04_autoridad' => '4 · Autoridad / GEO',
            '05_medicion' => '5 · Medición',
        ];
        $row['phase_label'] = $phaseLabels[$row['phase']] ?? $row['phase'];
        $row['done'] = (int) $row['done'] === 1;
        $row['detail'] = null;
        if (!empty($row['detail_json'])) {
            $decoded = json_decode((string) $row['detail_json'], true);
            if (is_array($decoded)) {
                $row['detail'] = $decoded;
            }
        }
        // Textos de usuario: catálogo + override opcional en detail_json
        if (is_array($cat)) {
            $row['title'] = (string) ($cat['title'] ?? $row['title']);
            $row['description'] = (string) ($cat['description'] ?? $row['description'] ?? '');
            $row['correction'] = (string) ($cat['correction'] ?? '');
            $row['execution'] = cw_seo_mexico_checklist_normalize_execution($cat['execution'] ?? 'code');
            $row['plan'] = is_array($cat['plan'] ?? null) ? $cat['plan'] : null;
        } else {
            // Tareas dinámicas: código (audit_*) o externas (external_*/ext_*)
            $row['correction'] = is_array($row['detail'] ?? null)
                ? (string) ($row['detail']['user_correction'] ?? '')
                : '';
            $row['plan'] = null;
            $detailExec = is_array($row['detail'] ?? null)
                ? cw_seo_mexico_checklist_normalize_execution($row['detail']['execution'] ?? '')
                : 'code';
            $looksExternal = $detailExec === 'external'
                || str_starts_with($key, 'external_')
                || str_starts_with($key, 'ext_');
            $row['execution'] = $looksExternal ? 'external' : 'code';
            $row['is_dynamic'] = !empty($row['detail']['dynamic'])
                || str_starts_with($key, 'audit_')
                || str_starts_with($key, 'external_');
        }
        if (is_array($row['detail'] ?? null)) {
            if (!empty($row['detail']['user_description'])) {
                $row['description'] = (string) $row['detail']['user_description'];
            }
            if (!empty($row['detail']['user_correction'])) {
                $row['correction'] = (string) $row['detail']['user_correction'];
            }
            // Override manual de fechas en detail_json.plan (opcional)
            $detailPlan = cw_seo_mexico_checklist_normalize_plan($row['detail']['plan'] ?? null);
            if ($detailPlan !== null) {
                $row['plan'] = $detailPlan;
            }
        }
    }
    unset($row);

    return $rows;
}

/**
 * Pendientes con calendario, ordenados por fecha límite.
 *
 * @param list<array<string,mixed>> $rows
 * @return list<array<string,mixed>>
 */
function cw_seo_mexico_checklist_pending_schedule(array $rows): array
{
    $out = [];
    foreach ($rows as $row) {
        if (!empty($row['done'])) {
            continue;
        }
        $plan = is_array($row['plan'] ?? null) ? $row['plan'] : null;
        if ($plan === null) {
            continue;
        }
        $out[] = [
            'task_key' => (string) ($row['task_key'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'phase_label' => (string) ($row['phase_label'] ?? ''),
            'plan' => $plan,
            'due_ts' => !empty($plan['due']) ? strtotime((string) $plan['due']) : PHP_INT_MAX,
        ];
    }
    usort($out, static function (array $a, array $b): int {
        $nowFirst = ((int) !empty($a['plan']['can_start_now'])) <=> ((int) !empty($b['plan']['can_start_now']));
        if ($nowFirst !== 0) {
            return -$nowFirst; // can_start_now primero
        }
        return ($a['due_ts'] <=> $b['due_ts']);
    });

    return $out;
}

/**
 * Formatea YYYY-MM-DD a d/m/Y.
 */
function cw_seo_mexico_checklist_fmt_date(?string $ymd): string
{
    $ymd = trim((string) $ymd);
    if ($ymd === '') {
        return '—';
    }
    $ts = strtotime($ymd);
    return $ts ? date('d/m/Y', $ts) : $ymd;
}

/**
 * @return array{ok:bool,error?:string,item?:array<string,mixed>}
 */
function cw_seo_mexico_checklist_set_done(mysqli $conn, string $taskKey, bool $done, int $userId = 0, ?string $notes = null): array
{
    $taskKey = preg_replace('/[^a-z0-9_]/', '', strtolower($taskKey)) ?? '';
    if ($taskKey === '') {
        return ['ok' => false, 'error' => 'Tarea inválida'];
    }

    cw_seo_mexico_checklist_sync($conn);

    $notesVal = $notes !== null ? mb_substr(trim($notes), 0, 500) : null;
    $uid = max(0, $userId);

    if ($done) {
        if ($notesVal !== null) {
            $stmt = $conn->prepare(
                'UPDATE cw_seo_mexico_checklist
                 SET done = 1, done_at = NOW(), done_by = ?, notes = ?, updated_at = NOW()
                 WHERE task_key = ?'
            );
            if (!$stmt) {
                return ['ok' => false, 'error' => 'No se pudo preparar la actualización'];
            }
            $stmt->bind_param('iss', $uid, $notesVal, $taskKey);
        } else {
            $stmt = $conn->prepare(
                'UPDATE cw_seo_mexico_checklist
                 SET done = 1, done_at = NOW(), done_by = ?, updated_at = NOW()
                 WHERE task_key = ?'
            );
            if (!$stmt) {
                return ['ok' => false, 'error' => 'No se pudo preparar la actualización'];
            }
            $stmt->bind_param('is', $uid, $taskKey);
        }
    } else {
        $stmt = $conn->prepare(
            'UPDATE cw_seo_mexico_checklist
             SET done = 0, done_at = NULL, done_by = NULL, updated_at = NOW()
             WHERE task_key = ?'
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'No se pudo preparar la actualización'];
        }
        $stmt->bind_param('s', $taskKey);
    }

    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        return ['ok' => false, 'error' => 'No se pudo guardar'];
    }

    $item = null;
    $stmtG = $conn->prepare(
        'SELECT task_key, phase, title, description, sort_order, done, done_at, done_by, notes
         FROM cw_seo_mexico_checklist WHERE task_key = ? LIMIT 1'
    );
    if ($stmtG) {
        $stmtG->bind_param('s', $taskKey);
        $stmtG->execute();
        $res = $stmtG->get_result();
        $item = $res ? $res->fetch_assoc() : null;
        $stmtG->close();
        if ($item) {
            $item['done'] = (int) $item['done'] === 1;
        }
    }

    return ['ok' => true, 'item' => $item];
}

/**
 * @param list<array<string,mixed>> $rows
 * @return array{total:int,done:int,pct:int}
 */
function cw_seo_mexico_checklist_stats(array $rows): array
{
    $total = count($rows);
    $done = 0;
    foreach ($rows as $r) {
        if (!empty($r['done'])) {
            $done++;
        }
    }
    $pct = $total > 0 ? (int) round(($done / $total) * 100) : 0;
    return ['total' => $total, 'done' => $done, 'pct' => $pct];
}

/**
 * 10 plazas prioritarias (90 días) — demanda + cobertura actual en /mexico/.
 *
 * @return list<array{city:string,slug:string,estado:string,estado_slug:string,motivo:string,url:string}>
 */
function cw_seo_mexico_priority_plazas(): array
{
    return [
        ['city' => 'Ciudad de México', 'slug' => 'cdmx', 'estado' => 'Ciudad de México', 'estado_slug' => 'ciudad-de-mexico', 'motivo' => 'Mayor mercado digital y competencia; prioridad nacional', 'url' => 'https://conlineweb.com/mexico/ciudades/cdmx/'],
        ['city' => 'Guadalajara', 'slug' => 'guadalajara', 'estado' => 'Jalisco', 'estado_slug' => 'jalisco', 'motivo' => 'Hub tech / occidente; alta intención comercial', 'url' => 'https://conlineweb.com/mexico/ciudades/guadalajara/'],
        ['city' => 'Monterrey', 'slug' => 'monterrey', 'estado' => 'Nuevo León', 'estado_slug' => 'nuevo-leon', 'motivo' => 'Industria y corporativos; ticket alto', 'url' => 'https://conlineweb.com/mexico/ciudades/monterrey/'],
        ['city' => 'León', 'slug' => 'leon', 'estado' => 'Guanajuato', 'estado_slug' => 'guanajuato', 'motivo' => 'Base operativa ConlineWeb / Bajío; cierre local', 'url' => 'https://conlineweb.com/mexico/ciudades/leon/'],
        ['city' => 'Querétaro', 'slug' => 'queretaro', 'estado' => 'Querétaro', 'estado_slug' => 'queretaro', 'motivo' => 'Corredor industrial y startups en crecimiento', 'url' => 'https://conlineweb.com/mexico/ciudades/queretaro/'],
        ['city' => 'Puebla', 'slug' => 'puebla', 'estado' => 'Puebla', 'estado_slug' => 'puebla', 'motivo' => 'Automotriz, educación y comercio con demanda web', 'url' => 'https://conlineweb.com/mexico/ciudades/puebla/'],
        ['city' => 'Mérida', 'slug' => 'merida', 'estado' => 'Yucatán', 'estado_slug' => 'yucatan', 'motivo' => 'Nearshoring y servicios; expansión península', 'url' => 'https://conlineweb.com/mexico/ciudades/merida/'],
        ['city' => 'Tijuana', 'slug' => 'tijuana', 'estado' => 'Baja California', 'estado_slug' => 'baja-california', 'motivo' => 'Comercio binacional y maquila digitalizable', 'url' => 'https://conlineweb.com/mexico/ciudades/tijuana/'],
        ['city' => 'Cancún', 'slug' => 'cancun', 'estado' => 'Quintana Roo', 'estado_slug' => 'quintana-roo', 'motivo' => 'Turismo internacional; e-commerce y reservas', 'url' => 'https://conlineweb.com/mexico/ciudades/cancun/'],
        ['city' => 'Toluca', 'slug' => 'toluca', 'estado' => 'Estado de México', 'estado_slug' => 'estado-de-mexico', 'motivo' => 'Corredor industrial metropolitano', 'url' => 'https://conlineweb.com/mexico/ciudades/toluca/'],
    ];
}

function cw_seo_mexico_checklist_log_update(mysqli $conn, string $taskKey, string $summary, string $detail = '', int $userId = 0): void
{
    $taskKey = preg_replace('/[^a-z0-9_]/', '', strtolower($taskKey)) ?? '';
    if ($taskKey === '') {
        return;
    }
    $summary = mb_substr(trim($summary), 0, 255);
    $detail = trim($detail);
    $uid = max(0, $userId);
    $stmt = $conn->prepare(
        'INSERT INTO cw_seo_mexico_checklist_updates (task_key, summary, detail, created_by, created_at)
         VALUES (?, ?, ?, ?, NOW())'
    );
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('sssi', $taskKey, $summary, $detail, $uid);
    $stmt->execute();
    $stmt->close();
}

/**
 * @return list<array<string,mixed>>
 */
function cw_seo_mexico_checklist_updates(mysqli $conn, ?string $taskKey = null, int $limit = 100): array
{
    $limit = max(1, min(200, $limit));
    $out = [];
    if ($taskKey !== null && $taskKey !== '') {
        $taskKey = preg_replace('/[^a-z0-9_]/', '', strtolower($taskKey)) ?? '';
        $stmt = $conn->prepare(
            'SELECT id, task_key, summary, detail, created_by, created_at
             FROM cw_seo_mexico_checklist_updates
             WHERE task_key = ?
             ORDER BY created_at DESC, id DESC
             LIMIT ' . $limit
        );
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('s', $taskKey);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $conn->query(
            'SELECT id, task_key, summary, detail, created_by, created_at
             FROM cw_seo_mexico_checklist_updates
             ORDER BY created_at DESC, id DESC
             LIMIT ' . $limit
        );
    }

    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $out[] = $r;
        }
        if (isset($stmt) && $stmt instanceof mysqli_stmt) {
            $stmt->close();
        } else {
            $res->free();
        }
    }

    return $out;
}

/**
 * Ejecuta la 1ª tarea solo cuando queda completa de verdad:
 * done=1 + detail_json con plazas + fila en historial de actualizaciones.
 *
 * @return array{ok:bool,applied:bool,complete:bool,plazas:list<array<string,mixed>>,error?:string}
 */
function cw_seo_mexico_checklist_apply_plazas_prioridad(mysqli $conn, int $userId = 0): array
{
    cw_seo_mexico_checklist_sync($conn);
    $plazas = cw_seo_mexico_priority_plazas();
    $names = [];
    $newSlugs = [];
    foreach ($plazas as $p) {
        $names[] = (string) $p['city'];
        $newSlugs[] = (string) $p['slug'];
    }
    sort($newSlugs);

    $notes = 'Plazas prioritarias (90 días): ' . implode(', ', $names) . '.';
    $criteria = [
        'Demanda digital / PIB regional',
        'Ciudades ya existentes en /mexico/ciudades/',
        'Mix nacional (centro, norte, occidente, Bajío, península, frontera)',
        'Base operativa ConlineWeb en León, Gto.',
    ];

    $key = 'plazas_prioridad';
    $chk = $conn->prepare('SELECT done, done_at, notes, detail_json FROM cw_seo_mexico_checklist WHERE task_key = ? LIMIT 1');
    $already = false;
    $prevDetail = null;
    if ($chk) {
        $chk->bind_param('s', $key);
        $chk->execute();
        $res = $chk->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $chk->close();
        if ($row) {
            $already = (int) ($row['done'] ?? 0) === 1;
            if (!empty($row['detail_json'])) {
                $decoded = json_decode((string) $row['detail_json'], true);
                if (is_array($decoded)) {
                    $prevDetail = $decoded;
                }
            }
        }
    }

    $prevSlugs = [];
    if (is_array($prevDetail['plazas'] ?? null)) {
        foreach ($prevDetail['plazas'] as $p) {
            if (!empty($p['slug'])) {
                $prevSlugs[] = (string) $p['slug'];
            }
        }
    }
    sort($prevSlugs);
    $samePlazas = $prevSlugs === $newSlugs && count($newSlugs) >= 8;

    $existingUpdates = cw_seo_mexico_checklist_updates($conn, $key, 1);
    $hasUpdateLog = $existingUpdates !== [];

    // Completa de verdad: marcada + detalle de plazas + historial
    if ($already && $samePlazas && $hasUpdateLog) {
        return [
            'ok' => true,
            'applied' => false,
            'complete' => true,
            'plazas' => is_array($prevDetail['plazas'] ?? null) ? $prevDetail['plazas'] : $plazas,
        ];
    }

    $frozenUntil = !empty($prevDetail['frozen_until'])
        ? (string) $prevDetail['frozen_until']
        : date('Y-m-d', strtotime('+90 days'));

    $detail = [
        'version' => 1,
        'frozen_until' => $frozenUntil,
        'count' => count($plazas),
        'plazas' => $plazas,
        'criteria' => $criteria,
        'applied_at' => date('c'),
    ];
    $detailJson = json_encode($detail, JSON_UNESCAPED_UNICODE);
    if ($detailJson === false) {
        return ['ok' => false, 'applied' => false, 'complete' => false, 'plazas' => $plazas, 'error' => 'No se pudo serializar el detalle'];
    }

    $uid = max(0, $userId);
    // Forzar done=1 siempre que la lista quede aplicada (no dejar solo notes sin check)
    $stmt = $conn->prepare(
        'UPDATE cw_seo_mexico_checklist
         SET done = 1,
             done_at = IF(done = 1 AND done_at IS NOT NULL, done_at, NOW()),
             done_by = IF(done = 1 AND done_by IS NOT NULL AND done_by > 0, done_by, ?),
             notes = ?,
             detail_json = ?,
             updated_at = NOW()
         WHERE task_key = ?'
    );
    if (!$stmt) {
        return ['ok' => false, 'applied' => false, 'complete' => false, 'plazas' => $plazas, 'error' => 'No se pudo preparar UPDATE (¿falta detail_json?)'];
    }
    $stmt->bind_param('isss', $uid, $notes, $detailJson, $key);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) {
        return ['ok' => false, 'applied' => false, 'complete' => false, 'plazas' => $plazas, 'error' => 'No se pudo marcar la tarea'];
    }

    // Solo registrar historial si faltaba o cambió la lista
    if (!$hasUpdateLog || !$samePlazas || !$already) {
        $summary = (!$already || !$hasUpdateLog)
            ? 'Lista de 10 plazas prioritarias definida y congelada 90 días'
            : 'Lista de plazas prioritarias actualizada (' . count($plazas) . ')';
        $logDetail = "Plazas:\n- " . implode("\n- ", $names)
            . "\n\nVigencia hasta: " . $frozenUntil
            . "\nCriterios: " . implode('; ', $criteria);
        cw_seo_mexico_checklist_log_update($conn, $key, $summary, $logDetail, $uid);
    }

    // Verificar que realmente quedó done + historial
    $verify = cw_seo_mexico_checklist_task_detail($conn, $key);
    $verifyDone = !empty($verify['done']);
    $verifyUpdates = cw_seo_mexico_checklist_updates($conn, $key, 1);
    $complete = $verifyDone && $verifyUpdates !== [];

    return [
        'ok' => $complete,
        'applied' => true,
        'complete' => $complete,
        'plazas' => $plazas,
        'error' => $complete ? null : 'La tarea no quedó completa (check o historial faltante)',
    ];
}

/**
 * Ruta local a un include México del sitio (si adm y conlineweb comparten el mismo parent).
 */
function cw_seo_mexico_site_mexico_include_path(string $filename): ?string
{
    $filename = basename($filename);
    $candidates = [
        dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'conlineweb.com' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'mexico' . DIRECTORY_SEPARATOR . $filename,
        dirname(__DIR__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'conlineweb.com' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'mexico' . DIRECTORY_SEPARATOR . $filename,
    ];
    foreach ($candidates as $path) {
        $real = realpath($path);
        if ($real !== false && is_file($real)) {
            return $real;
        }
    }
    return null;
}

/** @deprecated use cw_seo_mexico_site_mexico_include_path */
function cw_seo_mexico_site_city_overrides_path(): ?string
{
    return cw_seo_mexico_site_mexico_include_path('city-hub-overrides.php');
}

/**
 * Marca una tarea del checklist como hecha con detalle + historial (tras aplicar en sitio/proceso).
 *
 * @param array<string,mixed>|null $detail
 * @return array{ok:bool,applied:bool,error?:string}
 */
function cw_seo_mexico_checklist_complete_task(
    mysqli $conn,
    string $taskKey,
    string $summary,
    string $detailText = '',
    int $userId = 0,
    ?array $detail = null,
    ?string $notes = null
): array {
    $taskKey = preg_replace('/[^a-z0-9_]/', '', strtolower($taskKey)) ?? '';
    if ($taskKey === '') {
        return ['ok' => false, 'applied' => false, 'error' => 'Tarea inválida'];
    }

    cw_seo_mexico_checklist_sync($conn);

    $detailJson = $detail !== null ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null;
    if ($detail !== null && $detailJson === false) {
        return ['ok' => false, 'applied' => false, 'error' => 'detail_json inválido'];
    }

    $chk = $conn->prepare('SELECT done, detail_json FROM cw_seo_mexico_checklist WHERE task_key = ? LIMIT 1');
    $already = false;
    $sameDetail = false;
    if ($chk) {
        $chk->bind_param('s', $taskKey);
        $chk->execute();
        $res = $chk->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $chk->close();
        if ($row) {
            $already = (int) ($row['done'] ?? 0) === 1;
            if ($detailJson !== null) {
                $sameDetail = trim((string) ($row['detail_json'] ?? '')) === $detailJson;
            } else {
                $sameDetail = $already;
            }
        }
    }

    $updates = cw_seo_mexico_checklist_updates($conn, $taskKey, 1);
    if ($already && $sameDetail && $updates !== []) {
        return ['ok' => true, 'applied' => false];
    }

    $uid = max(0, $userId);
    $notesVal = $notes !== null ? $notes : mb_substr(trim($summary), 0, 500);

    if ($detailJson !== null) {
        $stmt = $conn->prepare(
            'UPDATE cw_seo_mexico_checklist
             SET done = 1,
                 done_at = IF(done = 1 AND done_at IS NOT NULL, done_at, NOW()),
                 done_by = IF(done = 1 AND done_by IS NOT NULL AND done_by > 0, done_by, ?),
                 notes = ?,
                 detail_json = ?,
                 updated_at = NOW()
             WHERE task_key = ?'
        );
        if (!$stmt) {
            return ['ok' => false, 'applied' => false, 'error' => 'No se pudo preparar UPDATE'];
        }
        $stmt->bind_param('isss', $uid, $notesVal, $detailJson, $taskKey);
    } else {
        $stmt = $conn->prepare(
            'UPDATE cw_seo_mexico_checklist
             SET done = 1,
                 done_at = IF(done = 1 AND done_at IS NOT NULL, done_at, NOW()),
                 done_by = IF(done = 1 AND done_by IS NOT NULL AND done_by > 0, done_by, ?),
                 notes = ?,
                 updated_at = NOW()
             WHERE task_key = ?'
        );
        if (!$stmt) {
            return ['ok' => false, 'applied' => false, 'error' => 'No se pudo preparar UPDATE'];
        }
        $stmt->bind_param('iss', $uid, $notesVal, $taskKey);
    }

    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) {
        return ['ok' => false, 'applied' => false, 'error' => 'No se pudo marcar la tarea'];
    }

    if (!$already || !$sameDetail || $updates === []) {
        cw_seo_mexico_checklist_log_update($conn, $taskKey, $summary, $detailText, $uid);
    }

    return ['ok' => true, 'applied' => true];
}

/**
 * Si el sitio ya tiene overrides de hubs prioritarios, marca rewrite_hubs_plazas.
 *
 * @return array{ok:bool,applied:bool,complete:bool,slugs:list<string>,error?:string}
 */
function cw_seo_mexico_checklist_apply_rewrite_hubs(mysqli $conn, int $userId = 0): array
{
    $path = cw_seo_mexico_site_city_overrides_path();
    if ($path === null) {
        // En producción adm/sitio pueden estar separados: no desmarcar; solo reportar
        $task = cw_seo_mexico_checklist_task_detail($conn, 'rewrite_hubs_plazas');
        $done = !empty($task['done']);
        return [
            'ok' => true,
            'applied' => false,
            'complete' => $done,
            'slugs' => [],
            'error' => $done ? null : 'Sube city-hub-overrides.php al sitio y vuelve a abrir el checklist (o marca tras deploy).',
        ];
    }

    require_once $path;
    if (!function_exists('mx_city_hub_override_slugs') || !defined('MX_CITY_HUB_OVERRIDES_VERSION')) {
        return ['ok' => false, 'applied' => false, 'complete' => false, 'slugs' => [], 'error' => 'Overrides inválidos'];
    }

    $slugs = mx_city_hub_override_slugs();
    $priority = array_map(static function ($p) {
        return (string) $p['slug'];
    }, cw_seo_mexico_priority_plazas());
    sort($slugs);
    $prioSorted = $priority;
    sort($prioSorted);

    if (count($slugs) < 8) {
        return ['ok' => false, 'applied' => false, 'complete' => false, 'slugs' => $slugs, 'error' => 'Faltan overrides de plazas'];
    }

    $version = (string) MX_CITY_HUB_OVERRIDES_VERSION;
    $names = [];
    $pages = [];
    $urls = [];
    foreach (cw_seo_mexico_priority_plazas() as $p) {
        if (!in_array($p['slug'], $slugs, true)) {
            continue;
        }
        $names[] = $p['city'];
        $url = 'https://conlineweb.com/mexico/ciudades/' . $p['slug'] . '/';
        $urls[] = $url;
        $pages[] = [
            'city' => $p['city'],
            'slug' => $p['slug'],
            'estado' => $p['estado'],
            'url' => $url,
            'changed' => ['title', 'description', 'h1', 'hero_subtitle', 'services_p1', 'services_p2', 'ia_content', 'faqs'],
        ];
    }
    // Incluir overrides que no estén en la lista canónica de prioridad
    foreach ($slugs as $slug) {
        $found = false;
        foreach ($pages as $pg) {
            if ($pg['slug'] === $slug) {
                $found = true;
                break;
            }
        }
        if ($found) {
            continue;
        }
        $url = 'https://conlineweb.com/mexico/ciudades/' . $slug . '/';
        $urls[] = $url;
        $pages[] = [
            'city' => $slug,
            'slug' => $slug,
            'estado' => '',
            'url' => $url,
            'changed' => ['title', 'description', 'h1', 'hero_subtitle', 'services_p1', 'services_p2', 'ia_content', 'faqs'],
        ];
    }

    $detail = [
        'version' => $version,
        'file' => 'conlineweb.com/includes/mexico/city-hub-overrides.php',
        'wired_in' => 'page-factory.php → mx_page_ciudad()',
        'slugs' => $slugs,
        'count' => count($slugs),
        'covers_priority' => $slugs === $prioSorted || count(array_intersect($slugs, $priority)) >= 8,
        'urls' => $urls,
        'pages' => $pages,
        'deploy_required' => [
            'conlineweb.com/includes/mexico/city-hub-overrides.php',
            'conlineweb.com/includes/mexico/page-factory.php',
        ],
    ];

    $summary = 'Hubs reescritos: ' . count($pages) . ' URLs de plazas prioritarias (v' . $version . ')';
    $logDetail = "URLs modificadas (hubs):\n";
    foreach ($pages as $i => $it) {
        $logDetail .= ($i + 1) . '. ' . $it['city'] . ' — ' . $it['url'] . "\n";
    }
    $logDetail .= "\nArchivo: city-hub-overrides.php\nVersión: {$version}\nCampos únicos: title, description, h1, hero, servicios, FAQs locales.";

    $result = cw_seo_mexico_checklist_complete_task(
        $conn,
        'rewrite_hubs_plazas',
        $summary,
        $logDetail,
        $userId,
        $detail,
        'Contenido único publicado en hubs: ' . implode(', ', $names !== [] ? $names : $slugs) . '.'
    );

    return [
        'ok' => !empty($result['ok']),
        'applied' => !empty($result['applied']),
        'complete' => !empty($result['ok']),
        'slugs' => $slugs,
        'error' => $result['error'] ?? null,
    ];
}

/**
 * Si el sitio ya tiene overrides servicio×plaza, marca rewrite_servicio_geo.
 *
 * @return array{ok:bool,applied:bool,complete:bool,count:int,error?:string}
 */
function cw_seo_mexico_checklist_apply_rewrite_servicio_geo(mysqli $conn, int $userId = 0): array
{
    $path = cw_seo_mexico_site_mexico_include_path('service-geo-overrides.php');
    if ($path === null) {
        $task = cw_seo_mexico_checklist_task_detail($conn, 'rewrite_servicio_geo');
        $done = !empty($task['done']);
        return [
            'ok' => true,
            'applied' => false,
            'complete' => $done,
            'count' => 0,
            'error' => $done ? null : 'Sube service-geo-overrides.php al sitio y vuelve a abrir el checklist.',
        ];
    }

    require_once $path;
    if (!function_exists('mx_service_geo_priority_city_slugs')
        || !function_exists('mx_service_geo_priority_service_slugs')
        || !defined('MX_SERVICE_GEO_OVERRIDES_VERSION')) {
        return ['ok' => false, 'applied' => false, 'complete' => false, 'count' => 0, 'error' => 'Overrides servicio×geo inválidos'];
    }

    $svcNames = [
        'desarrollo-web' => 'Desarrollo Web',
        'software-a-medida' => 'Software a Medida',
        'ecommerce' => 'Tiendas en Línea',
        'seo' => 'SEO y Posicionamiento',
        'inteligencia-artificial' => 'Inteligencia Artificial',
    ];
    $cityNames = [];
    foreach (cw_seo_mexico_priority_plazas() as $p) {
        $cityNames[(string) $p['slug']] = (string) $p['city'];
    }

    $pages = [];
    foreach (mx_service_geo_priority_city_slugs() as $citySlug) {
        foreach (mx_service_geo_priority_service_slugs() as $svcSlug) {
            $pages[] = [
                'city' => $cityNames[$citySlug] ?? $citySlug,
                'slug' => $citySlug,
                'estado' => $svcNames[$svcSlug] ?? $svcSlug,
                'url' => 'https://conlineweb.com/mexico/ciudades/' . $citySlug . '/servicios/' . $svcSlug . '/',
                'changed' => ['title', 'description', 'h1', 'hero_subtitle', 'services_p1', 'services_p2', 'ia_content', 'faqs'],
            ];
        }
    }

    if (count($pages) < 40) {
        return ['ok' => false, 'applied' => false, 'complete' => false, 'count' => count($pages), 'error' => 'Faltan páginas servicio×plaza (esperado 50)'];
    }

    $version = (string) MX_SERVICE_GEO_OVERRIDES_VERSION;

    $detail = [
        'version' => $version,
        'file' => 'conlineweb.com/includes/mexico/service-geo-overrides.php',
        'wired_in' => 'page-factory.php → mx_page_servicio_geo()',
        'count' => count($pages),
        'cities' => 10,
        'services' => 5,
        'urls' => array_column($pages, 'url'),
        'pages' => $pages,
        'deploy_required' => [
            'conlineweb.com/includes/mexico/service-geo-overrides.php',
            'conlineweb.com/includes/mexico/page-factory.php',
        ],
    ];

    $summary = 'Servicio × plaza reforzado: ' . count($pages) . ' URLs prioritarias (v' . $version . ')';
    $logDetail = "10 plazas × 5 servicios = " . count($pages) . " URLs\nServicios: desarrollo-web, software-a-medida, ecommerce, seo, inteligencia-artificial\n\nEjemplos:\n";
    foreach (array_slice($pages, 0, 8) as $i => $it) {
        $logDetail .= ($i + 1) . '. ' . $it['city'] . ' / ' . $it['estado'] . ' — ' . $it['url'] . "\n";
    }
    $logDetail .= "… y " . max(0, count($pages) - 8) . " más.\nArchivo: service-geo-overrides.php";

    $result = cw_seo_mexico_checklist_complete_task(
        $conn,
        'rewrite_servicio_geo',
        $summary,
        $logDetail,
        $userId,
        $detail,
        'Contenido único en ' . count($pages) . ' páginas servicio × plaza prioritarias.'
    );

    return [
        'ok' => !empty($result['ok']),
        'applied' => !empty($result['applied']),
        'complete' => !empty($result['ok']),
        'count' => count($pages),
        'error' => $result['error'] ?? null,
    ];
}

/**
 * Ruta al root del sitio público (conlineweb.com), si existe junto al adm.
 */
function cw_seo_mexico_site_root_path(): ?string
{
    $candidates = [
        dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'conlineweb.com',
        dirname(__DIR__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'conlineweb.com',
    ];
    $fallback = null;
    foreach ($candidates as $path) {
        $real = realpath($path);
        if ($real === false || !is_dir($real)) {
            continue;
        }
        // Preferir sitio con sitemap; si no, basta includes/ (cPanel mismo servidor)
        if (is_file($real . DIRECTORY_SEPARATOR . 'sitemap.xml')) {
            return $real;
        }
        if ($fallback === null && is_dir($real . DIRECTORY_SEPARATOR . 'includes')) {
            $fallback = $real;
        }
    }
    return $fallback;
}

/**
 * Si sitemap.xml tiene canónicas México (sin short), marca sitemap_completo.
 *
 * @return array{ok:bool,applied:bool,complete:bool,urls:int,error?:string}
 */
function cw_seo_mexico_checklist_apply_sitemap_completo(mysqli $conn, int $userId = 0): array
{
    $root = cw_seo_mexico_site_root_path();
    if ($root === null) {
        $task = cw_seo_mexico_checklist_task_detail($conn, 'sitemap_completo');
        $done = !empty($task['done']);
        return [
            'ok' => true,
            'applied' => false,
            'complete' => $done,
            'urls' => 0,
            'error' => $done ? null : 'No se encontró sitemap.xml del sitio local.',
        ];
    }

    $sitemapPath = $root . DIRECTORY_SEPARATOR . 'sitemap.xml';
    $xml = file_get_contents($sitemapPath);
    if ($xml === false || $xml === '') {
        return ['ok' => false, 'applied' => false, 'complete' => false, 'urls' => 0, 'error' => 'sitemap.xml ilegible'];
    }

    $locPath = $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'mexico' . DIRECTORY_SEPARATOR . 'locations.php';
    if (!is_readable($locPath)) {
        return ['ok' => false, 'applied' => false, 'complete' => false, 'urls' => 0, 'error' => 'locations.php no encontrado'];
    }
    /** @var array<string,mixed> $data */
    $data = require $locPath;
    $estados = array_keys($data['estados'] ?? []);
    $ciudades = array_keys($data['ciudades'] ?? []);
    $servicios = array_keys($data['servicios'] ?? []);

    $need = [
        'https://conlineweb.com/mexico/',
        'https://conlineweb.com/mexico/estados/',
        'https://conlineweb.com/mexico/ciudades/',
        'https://conlineweb.com/mexico/servicios/',
    ];
    foreach ($servicios as $s) {
        $need[] = "https://conlineweb.com/mexico/servicios/{$s}/";
    }
    foreach ($estados as $s) {
        $need[] = "https://conlineweb.com/mexico/estados/{$s}/";
        $need[] = "https://conlineweb.com/mexico/estados/{$s}/servicios/";
        foreach ($servicios as $svc) {
            $need[] = "https://conlineweb.com/mexico/estados/{$s}/servicios/{$svc}/";
        }
    }
    foreach ($ciudades as $s) {
        $need[] = "https://conlineweb.com/mexico/ciudades/{$s}/";
        $need[] = "https://conlineweb.com/mexico/ciudades/{$s}/servicios/";
        foreach ($servicios as $svc) {
            $need[] = "https://conlineweb.com/mexico/ciudades/{$s}/servicios/{$svc}/";
        }
    }

    $missing = [];
    foreach ($need as $u) {
        if (!str_contains($xml, '<loc>' . $u . '</loc>')) {
            $missing[] = $u;
        }
    }

    preg_match_all('#<loc>(https://conlineweb\.com/mexico/[^<]+)</loc>#', $xml, $m);
    $shorts = [];
    foreach ($m[1] as $u) {
        if (str_contains($u, '/mexico/estados/')
            || str_contains($u, '/mexico/ciudades/')
            || str_contains($u, '/mexico/servicios/')
            || preg_match('#https://conlineweb\.com/mexico/$#', $u)) {
            continue;
        }
        if (preg_match('#https://conlineweb\.com/mexico/[a-z0-9-]+/#', $u)) {
            $shorts[] = $u;
        }
    }

    // Blog sitemap + indexación (índice + robots)
    $blogPath = $root . DIRECTORY_SEPARATOR . 'sitemap-blog.xml';
    $indexPath = $root . DIRECTORY_SEPARATOR . 'sitemap-index.xml';
    $robotsPath = $root . DIRECTORY_SEPARATOR . 'robots.txt';
    $blogXml = is_readable($blogPath) ? (string) file_get_contents($blogPath) : '';
    $indexXml = is_readable($indexPath) ? (string) file_get_contents($indexPath) : '';
    $robotsTxt = is_readable($robotsPath) ? (string) file_get_contents($robotsPath) : '';
    $blogUrls = $blogXml !== '' ? substr_count($blogXml, '<url>') : 0;
    $indexHasMain = str_contains($indexXml, 'https://conlineweb.com/sitemap.xml');
    $indexHasBlog = str_contains($indexXml, 'https://conlineweb.com/sitemap-blog.xml');
    $robotsHasIndex = str_contains($robotsTxt, 'sitemap-index.xml');
    $robotsHasBlog = str_contains($robotsTxt, 'sitemap-blog.xml');

    $blogErrors = [];
    if ($blogUrls < 1) {
        $blogErrors[] = 'sitemap-blog.xml vacío o ausente';
    }
    if (!$indexHasMain || !$indexHasBlog) {
        $blogErrors[] = 'sitemap-index.xml debe listar sitemap.xml y sitemap-blog.xml';
    }
    if (!$robotsHasIndex || !$robotsHasBlog) {
        $blogErrors[] = 'robots.txt debe declarar sitemap-index y sitemap-blog';
    }

    if ($missing !== [] || $shorts !== [] || $blogErrors !== []) {
        $parts = [];
        if ($missing !== []) {
            $parts[] = 'faltan ' . count($missing) . ' canónicas México';
        }
        if ($shorts !== []) {
            $parts[] = 'shorts=' . count($shorts);
        }
        if ($blogErrors !== []) {
            $parts[] = implode('; ', $blogErrors);
        }
        return [
            'ok' => false,
            'applied' => false,
            'complete' => false,
            'urls' => count($need) - count($missing),
            'error' => 'Sitemap incompleto: ' . implode(' | ', $parts),
        ];
    }

    $hubSvcCount = count($estados) + count($ciudades);
    $svcGeoCount = (count($estados) + count($ciudades)) * count($servicios);
    $pages = [
        ['city' => 'Hub México', 'url' => 'https://conlineweb.com/mexico/', 'estado' => 'nacional'],
        ['city' => 'Hub servicios', 'url' => 'https://conlineweb.com/mexico/servicios/', 'estado' => 'nacional'],
        ['city' => 'Sitemap index', 'url' => 'https://conlineweb.com/sitemap-index.xml', 'estado' => 'indexación'],
        ['city' => 'Sitemap blog', 'url' => 'https://conlineweb.com/sitemap-blog.xml', 'estado' => (string) $blogUrls . ' URLs'],
        ['city' => 'Hubs geo /servicios/', 'url' => 'https://conlineweb.com/mexico/ciudades/{slug}/servicios/', 'estado' => (string) $hubSvcCount],
        ['city' => 'Servicio × geo', 'url' => 'https://conlineweb.com/mexico/{tipo}/{slug}/servicios/{svc}/', 'estado' => (string) $svcGeoCount],
    ];
    foreach (cw_seo_mexico_priority_plazas() as $p) {
        $pages[] = [
            'city' => $p['city'],
            'slug' => $p['slug'],
            'estado' => $p['estado'],
            'url' => $p['url'],
        ];
    }

    $gscSubmit = 'https://conlineweb.com/sitemap-index.xml';
    $detail = [
        'version' => '2026-07-18.3',
        'file' => 'conlineweb.com/sitemap.xml + sitemap-blog.xml + sitemap-index.xml',
        'wired_in' => 'sync-sitemap-mexico.php + generate-sitemap-blog.php + robots.txt',
        'status' => 'completo',
        'canonical_urls' => count($need),
        'mexico_locs_in_sitemap' => count($m[1]),
        'blog_urls' => $blogUrls,
        'shorts' => 0,
        'hub_servicios_geo' => $hubSvcCount,
        'servicio_x_geo' => $svcGeoCount,
        'gsc' => [
            'submit_url' => $gscSubmit,
            'label' => 'Google Search Console — sitemap a dar de alta',
            'instruction' => 'En GSC → Sitemaps, agrega SOLO esta URL. El índice ya incluye México (sitemap.xml) y el blog (sitemap-blog.xml). No hace falta dar de alta los hijos por separado.',
            'included' => [
                'https://conlineweb.com/sitemap.xml',
                'https://conlineweb.com/sitemap-blog.xml',
            ],
            'do_not_submit_separately' => true,
        ],
        'indexacion' => [
            'sitemap_index' => $gscSubmit,
            'sitemap_main' => 'https://conlineweb.com/sitemap.xml',
            'sitemap_blog' => 'https://conlineweb.com/sitemap-blog.xml',
            'robots' => true,
        ],
        'pages' => $pages,
        'deploy_required' => [
            'conlineweb.com/sitemap.xml',
            'conlineweb.com/sitemap-blog.xml',
            'conlineweb.com/sitemap-index.xml',
            'conlineweb.com/robots.txt',
            'conlineweb.com/includes/mexico/sync-sitemap-mexico.php',
        ],
    ];

    $summary = 'Sitemaps indexables: México ' . count($need) . ' + blog ' . $blogUrls . ' (sin short)';
    $logDetail = "GSC — dar de alta SOLO:\n{$gscSubmit}\n";
    $logDetail .= "(cubre sitemap.xml + sitemap-blog.xml; no hace falta alta por separado)\n\n";
    $logDetail .= "México canónico: hub + /servicios/ + geo (sin short)\n";
    $logDetail .= 'Canónicas México: ' . count($need) . "\n";
    $logDetail .= 'Hubs /servicios/ geo: ' . $hubSvcCount . "\n";
    $logDetail .= 'Servicio × geo: ' . $svcGeoCount . "\n";
    $logDetail .= 'Blog sitemap: ' . $blogUrls . " URLs\n";
    $logDetail .= "Shorts: 0\n";

    $result = cw_seo_mexico_checklist_complete_task(
        $conn,
        'sitemap_completo',
        $summary,
        $logDetail,
        $userId,
        $detail,
        'GSC: dar de alta ' . $gscSubmit . ' (cubre México + blog).'
    );

    return [
        'ok' => !empty($result['ok']),
        'applied' => !empty($result['applied']),
        'complete' => !empty($result['ok']),
        'urls' => count($need),
        'blog_urls' => $blogUrls,
        'error' => $result['error'] ?? null,
    ];
}

/**
 * Si landings geo tienen mitigaciones CWV móvil, marca cwv_mobile.
 *
 * @return array{ok:bool,applied:bool,complete:bool,plazas:int,error?:string}
 */
function cw_seo_mexico_checklist_apply_cwv_mobile(mysqli $conn, int $userId = 0): array
{
    $root = cw_seo_mexico_site_root_path();
    if ($root === null) {
        $task = cw_seo_mexico_checklist_task_detail($conn, 'cwv_mobile');
        $done = !empty($task['done']);
        return [
            'ok' => true,
            'applied' => false,
            'complete' => $done,
            'plazas' => 0,
            'error' => $done ? null : 'No se encontró el sitio local.',
        ];
    }

    $tpl = $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'mexico' . DIRECTORY_SEPARATOR . 'home-template.php';
    $styles = $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'mexico' . DIRECTORY_SEPARATOR . 'mx-landing-head-styles.php';
    $particles = $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'particles-lazy.js';
    $audit = $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'mexico' . DIRECTORY_SEPARATOR . 'audit-cwv-mobile.php';

    $errors = [];
    $tplSrc = is_readable($tpl) ? (string) file_get_contents($tpl) : '';
    $stylesSrc = is_readable($styles) ? (string) file_get_contents($styles) : '';
    $partSrc = is_readable($particles) ? (string) file_get_contents($particles) : '';

    if ($tplSrc === '' || !str_contains($tplSrc, "define('CW_PERF_HOME'") || !str_contains($tplSrc, 'mx-cwv-critical')) {
        $errors[] = 'home-template sin CW_PERF_HOME / CSS crítico CWV';
    }
    if ($stylesSrc === '' || !str_contains($stylesSrc, 'mxLandingCssDeferred') || !str_contains($stylesSrc, 'cw_perf_async_stylesheet')) {
        $errors[] = 'mx-landing-head-styles sin CSS defer';
    }
    if ($partSrc === '' || !str_contains($partSrc, 'mx-mexico')) {
        $errors[] = 'particles-lazy sin skip móvil en mexico';
    }
    if (!is_readable($audit)) {
        $errors[] = 'Falta audit-cwv-mobile.php';
    }

    if ($errors !== []) {
        return [
            'ok' => false,
            'applied' => false,
            'complete' => false,
            'plazas' => 0,
            'error' => implode(' | ', $errors),
        ];
    }

    $plazas = cw_seo_mexico_priority_plazas();
    $pages = [];
    foreach ($plazas as $p) {
        $url = 'https://conlineweb.com/mexico/ciudades/' . $p['slug'] . '/';
        $pages[] = [
            'city' => $p['city'],
            'slug' => $p['slug'],
            'estado' => $p['estado'],
            'url' => $url,
            'motivo' => 'PSI móvil: https://pagespeed.web.dev/analysis?url=' . rawurlencode($url) . '&form_factor=mobile',
        ];
    }

    $samplePath = $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'mexico' . DIRECTORY_SEPARATOR . 'cwv-mobile-sample.json';
    $sample = null;
    if (is_readable($samplePath)) {
        $decoded = json_decode((string) file_get_contents($samplePath), true);
        if (is_array($decoded)) {
            $sample = $decoded;
        }
    }

    $detail = [
        'version' => '2026-07-18.1',
        'file' => 'conlineweb.com/includes/mexico/home-template.php + mx-landing-head-styles.php + particles-lazy.js',
        'wired_in' => 'CW_PERF_HOME + CSS defer + sin particles móvil + audit-cwv-mobile.php',
        'status' => 'completo',
        'thresholds' => ['lcp_ms' => 2500, 'cls' => 0.10, 'strategy' => 'mobile'],
        'fixes' => [
            'Fuentes / Font Awesome async (CW_PERF_HOME) en landings geo',
            'CSS secundario async; demos iframe CSS fuera de geo',
            'Particles desactivados en móvil (body.mx-mexico)',
            'CSS crítico: min-height H1 + contain hero / nexus',
        ],
        'gsc' => [
            'submit_url' => 'https://pagespeed.web.dev/',
            'label' => 'Validación CWV móvil (PageSpeed / CrUX)',
            'instruction' => 'Tras subir los fixes a producción, valida cada plaza top en PageSpeed (móvil). En GSC → Experiencia → Core Web Vitals revisa URLs lentas del grupo móvil. Meta lab: LCP ≤ 2.5s y CLS ≤ 0.10.',
            'included' => array_column($pages, 'url'),
        ],
        'sample_psi' => $sample,
        'pages' => $pages,
        'deploy_required' => [
            'conlineweb.com/includes/mexico/home-template.php',
            'conlineweb.com/includes/mexico/mx-landing-head-styles.php',
            'conlineweb.com/assets/js/particles-lazy.js',
            'conlineweb.com/includes/mexico/audit-cwv-mobile.php',
        ],
    ];

    $summary = 'CWV móvil geo: mitigaciones LCP/CLS en 10 plazas top (v2026-07-18.1)';
    $logDetail = "Mitigaciones aplicadas en landings geo (móvil):\n";
    foreach ($detail['fixes'] as $i => $fix) {
        $logDetail .= ($i + 1) . '. ' . $fix . "\n";
    }
    $logDetail .= "\nUmbrales: LCP ≤ 2500 ms · CLS ≤ 0.10\n";
    $logDetail .= "Auditoría: php includes/mexico/audit-cwv-mobile.php\n";
    $logDetail .= "Revalidar en PSI móvil tras deploy. GSC → Core Web Vitals (móvil).\n\n";
    foreach ($pages as $i => $pg) {
        $logDetail .= ($i + 1) . '. ' . $pg['city'] . ' — ' . $pg['url'] . "\n";
    }

    $result = cw_seo_mexico_checklist_complete_task(
        $conn,
        'cwv_mobile',
        $summary,
        $logDetail,
        $userId,
        $detail,
        'CWV móvil: fixes LCP/CLS en plazas top; revalidar PSI tras deploy.'
    );

    return [
        'ok' => !empty($result['ok']),
        'applied' => !empty($result['applied']),
        'complete' => !empty($result['ok']),
        'plazas' => count($pages),
        'error' => $result['error'] ?? null,
    ];
}

/**
 * Si short→canónica está endurecido y sin discovery de cortos, marca canibalizacion.
 *
 * @return array{ok:bool,applied:bool,complete:bool,rules:int,error?:string}
 */
function cw_seo_mexico_checklist_apply_canibalizacion(mysqli $conn, int $userId = 0): array
{
    $root = cw_seo_mexico_site_root_path();
    if ($root === null) {
        $task = cw_seo_mexico_checklist_task_detail($conn, 'canibalizacion');
        $done = !empty($task['done']);
        return [
            'ok' => true,
            'applied' => false,
            'complete' => $done,
            'rules' => 0,
            'error' => $done ? null : 'No se encontró el sitio local.',
        ];
    }

    $errors = [];
    $cannPath = $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'mexico' . DIRECTORY_SEPARATOR . 'cannibalization.php';
    $pfPath = $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'mexico' . DIRECTORY_SEPARATOR . 'page-factory.php';
    $siteIndexPath = $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'site-index-data.php';
    $sitemapPath = $root . DIRECTORY_SEPARATOR . 'sitemap.xml';

    if (!is_readable($cannPath) || !is_readable($pfPath)) {
        $errors[] = 'Faltan cannibalization.php o page-factory.php';
    }

    $pfSrc = is_readable($pfPath) ? (string) file_get_contents($pfPath) : '';
    if ($pfSrc === '' || !str_contains($pfSrc, 'function mx_page_ciudad(string $slug, bool $short')
        || !str_contains($pfSrc, 'function mx_page_estado(string $slug, bool $short')) {
        $errors[] = 'page-factory sin flag $short en hubs ciudad/estado';
    }

    $siteIndex = is_readable($siteIndexPath) ? (string) file_get_contents($siteIndexPath) : '';
    if ($siteIndex === '' || str_contains($siteIndex, 'dir-mexico-rutas-cortas')) {
        $errors[] = 'site-index-data aún lista rutas cortas';
    }

    $sitemap = is_readable($sitemapPath) ? (string) file_get_contents($sitemapPath) : '';
    $shortsInSitemap = 0;
    if ($sitemap !== '') {
        preg_match_all('#<loc>(https://conlineweb\.com/mexico/[^<]+)</loc>#', $sitemap, $m);
        foreach ($m[1] as $u) {
            if (str_contains($u, '/mexico/estados/')
                || str_contains($u, '/mexico/ciudades/')
                || str_contains($u, '/mexico/servicios/')
                || preg_match('#https://conlineweb\.com/mexico/$#', $u)) {
                continue;
            }
            if (preg_match('#https://conlineweb\.com/mexico/[a-z0-9-]+/#', $u)) {
                $shortsInSitemap++;
            }
        }
    }
    if ($shortsInSitemap > 0) {
        $errors[] = 'sitemap aún tiene ' . $shortsInSitemap . ' URLs short';
    }

    $sampleShort = $root . DIRECTORY_SEPARATOR . 'mexico' . DIRECTORY_SEPARATOR . 'leon' . DIRECTORY_SEPARATOR . 'index.php';
    $sampleSrc = is_readable($sampleShort) ? (string) file_get_contents($sampleShort) : '';
    if ($sampleSrc === '' || (!str_contains($sampleSrc, ', true)') && !str_contains($sampleSrc, ',true)'))) {
        $errors[] = 'Stub corto /mexico/leon/ sin $short=true';
    }

    // Contar stubs cortos con true
    $shortStubsOk = 0;
    $shortStubsBad = 0;
    $mexicoDir = $root . DIRECTORY_SEPARATOR . 'mexico';
    if (is_dir($mexicoDir)) {
        foreach (scandir($mexicoDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === 'ciudades' || $entry === 'estados' || $entry === 'servicios') {
                continue;
            }
            $idx = $mexicoDir . DIRECTORY_SEPARATOR . $entry . DIRECTORY_SEPARATOR . 'index.php';
            if (!is_file($idx)) {
                continue;
            }
            $src = (string) file_get_contents($idx);
            if (str_contains($src, ', true)') || str_contains($src, ',true)')) {
                $shortStubsOk++;
            } else {
                $shortStubsBad++;
            }
        }
    }
    if ($shortStubsOk < 30) {
        $errors[] = 'Pocos stubs cortos con 301 forzado (ok=' . $shortStubsOk . ')';
    }
    if ($shortStubsBad > 0) {
        $errors[] = $shortStubsBad . ' stubs cortos sin $short=true';
    }

    if ($errors !== []) {
        return [
            'ok' => false,
            'applied' => false,
            'complete' => false,
            'rules' => 0,
            'error' => implode(' | ', $errors),
        ];
    }

    // Cargar política (sin ejecutar page-factory completo si es pesado: cannibalization necesita mx_locations)
    require_once $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'cw-hub-config.php';
    require_once $pfPath;
    $policy = mx_cannibalization_policy();
    $rules = $policy['rules'];
    $overlap = $policy['overlap_slugs'];

    $pages = [
        [
            'city' => 'URL fuerte',
            'url' => 'https://conlineweb.com/mexico/ciudades/{slug}/',
            'estado' => 'canónica',
            'motivo' => 'Landing geo larga (ciudad/estado)',
        ],
        [
            'city' => 'Duplicado débil',
            'url' => 'https://conlineweb.com/mexico/{slug}/',
            'estado' => '301',
            'motivo' => 'Ruta corta → canónica (no indexar)',
        ],
        [
            'city' => 'Servicio nacional',
            'url' => 'https://conlineweb.com/mexico/servicios/{svc}/',
            'estado' => 'nacional',
            'motivo' => 'Intención cobertura México (no ciudad)',
        ],
        [
            'city' => 'Servicio local',
            'url' => 'https://conlineweb.com/mexico/ciudades/cdmx/servicios/seo/',
            'estado' => 'local',
            'motivo' => 'Intención plaza concreta',
        ],
    ];
    foreach ($overlap as $slug) {
        $pages[] = [
            'city' => 'Homónimo: ' . $slug,
            'url' => 'https://conlineweb.com/mexico/estados/' . $slug . '/',
            'estado' => 'estado diferenciado',
            'motivo' => 'Title/H1 con “estado de…” vs ciudad',
        ];
        $pages[] = [
            'city' => 'Homónimo ciudad: ' . $slug,
            'url' => 'https://conlineweb.com/mexico/ciudades/' . $slug . '/',
            'estado' => 'ciudad',
            'motivo' => 'Title ancla ciudad + estado',
        ];
    }

    $detail = [
        'version' => (string) MX_CANNIBALIZATION_VERSION,
        'file' => 'conlineweb.com/includes/mexico/cannibalization.php',
        'wired_in' => 'page-factory ($short) + stubs 301 + sitemap sin short + site-index sin cortos',
        'status' => 'completo',
        'short_stubs_301' => $shortStubsOk,
        'shorts_in_sitemap' => 0,
        'overlap_slugs' => $overlap,
        'rules' => $rules,
        'pages' => $pages,
        'deploy_required' => [
            'conlineweb.com/includes/mexico/page-factory.php',
            'conlineweb.com/includes/mexico/cannibalization.php',
            'conlineweb.com/includes/mexico/generate-pages.php',
            'conlineweb.com/includes/site-index-data.php',
            'conlineweb.com/mexico/{slug}/index.php (45 stubs cortos)',
        ],
    ];

    $summary = 'Canibalización México: 301 shorts + 1 URL fuerte por intención (v' . MX_CANNIBALIZATION_VERSION . ')';
    $logDetail = "Política anti-canibalización aplicada\n";
    $logDetail .= "Stubs cortos con 301 forzado: {$shortStubsOk}\n";
    $logDetail .= "Shorts en sitemap: 0\n";
    $logDetail .= 'Homónimos ciudad/estado: ' . implode(', ', $overlap) . "\n\n";
    foreach ($rules as $i => $rule) {
        $logDetail .= ($i + 1) . '. ' . $rule['intent'] . "\n";
        $logDetail .= '   Ganadora: ' . $rule['winner'] . "\n";
        $logDetail .= '   Débil: ' . $rule['weak'] . ' → ' . $rule['action'] . "\n";
    }

    $result = cw_seo_mexico_checklist_complete_task(
        $conn,
        'canibalizacion',
        $summary,
        $logDetail,
        $userId,
        $detail,
        '1 URL fuerte por intención: shorts 301, nacional≠local, homónimos diferenciados.'
    );

    return [
        'ok' => !empty($result['ok']),
        'applied' => !empty($result['applied']),
        'complete' => !empty($result['ok']),
        'rules' => count($rules),
        'error' => $result['error'] ?? null,
    ];
}

/**
 * Si el sitio ya enlaza blog ↔ hubs prioritarios, marca blog_internlink.
 *
 * @return array{ok:bool,applied:bool,complete:bool,cities:int,error?:string}
 */
function cw_seo_mexico_checklist_apply_blog_internlink(mysqli $conn, int $userId = 0): array
{
    $path = cw_seo_mexico_site_mexico_include_path('city-blog-links.php');
    if ($path === null) {
        $task = cw_seo_mexico_checklist_task_detail($conn, 'blog_internlink');
        $done = !empty($task['done']);
        return [
            'ok' => true,
            'applied' => false,
            'complete' => $done,
            'cities' => 0,
            'error' => $done ? null : 'Sube city-blog-links.php al sitio y vuelve a abrir el checklist.',
        ];
    }

    require_once $path;
    if (!function_exists('mx_city_blog_slugs_map')
        || !function_exists('mx_blog_geo_slugs_map')
        || !defined('MX_CITY_BLOG_LINKS_VERSION')) {
        return ['ok' => false, 'applied' => false, 'complete' => false, 'cities' => 0, 'error' => 'city-blog-links.php inválido'];
    }

    $hubMap = mx_city_blog_slugs_map();
    $priority = array_map(static function ($p) {
        return (string) $p['slug'];
    }, cw_seo_mexico_priority_plazas());

    $missingHub = [];
    foreach ($priority as $slug) {
        $posts = $hubMap[$slug] ?? [];
        if (!is_array($posts) || $posts === []) {
            $missingHub[] = $slug;
        }
    }

    $seoLocalPath = dirname($path, 2) . DIRECTORY_SEPARATOR . 'blog' . DIRECTORY_SEPARATOR . 'content'
        . DIRECTORY_SEPARATOR . 'seo-local-mexico-guia-completa.php';
    $seoLocalHtml = is_readable($seoLocalPath) ? (string) file_get_contents($seoLocalPath) : '';
    $missingInArticle = [];
    foreach ($priority as $slug) {
        if ($seoLocalHtml === '' || !str_contains($seoLocalHtml, 'mexico/ciudades/' . $slug . '/')) {
            $missingInArticle[] = $slug;
        }
    }

    if ($missingHub !== [] || $missingInArticle !== []) {
        return [
            'ok' => false,
            'applied' => false,
            'complete' => false,
            'cities' => count($priority) - count(array_unique(array_merge($missingHub, $missingInArticle))),
            'error' => 'Enlaces incompletos. Hub→blog: ' . implode(', ', $missingHub ?: ['ok'])
                . ' | Artículo SEO local→geo: ' . implode(', ', $missingInArticle ?: ['ok']),
        ];
    }

    $version = (string) MX_CITY_BLOG_LINKS_VERSION;
    $pages = [];
    foreach (cw_seo_mexico_priority_plazas() as $p) {
        $pages[] = [
            'city' => $p['city'],
            'slug' => $p['slug'],
            'estado' => $p['estado'],
            'url' => $p['url'],
            'changed' => ['hub→blog', 'blog→hub (seo-local)'],
        ];
    }

    $blogPosts = array_keys(mx_blog_geo_slugs_map());
    $detail = [
        'version' => $version,
        'file' => 'conlineweb.com/includes/mexico/city-blog-links.php',
        'wired_in' => 'page-factory.php (hubs) + blog page-factory/post-template + seo-local content',
        'status' => 'completo',
        'cities' => count($pages),
        'hub_to_blog' => true,
        'blog_to_geo_posts' => $blogPosts,
        'anchor_article' => 'https://conlineweb.com/blog/articulo/seo-local-mexico-guia-completa/',
        'pages' => $pages,
        'deploy_required' => [
            'conlineweb.com/includes/mexico/city-blog-links.php',
            'conlineweb.com/includes/mexico/page-factory.php',
            'conlineweb.com/includes/mexico/home-template.php',
            'conlineweb.com/includes/blog/page-factory.php',
            'conlineweb.com/includes/blog/post-template.php',
            'conlineweb.com/includes/blog/content/seo-local-mexico-guia-completa.php',
            'conlineweb.com/includes/blog/content/como-elegir-agencia-desarrollo-web-mexico.php',
            'conlineweb.com/assets/css/pages/blog.css',
        ],
    ];

    $summary = 'Blog ↔ geo prioritarias: 10 hubs + artículo SEO local (v' . $version . ')';
    $logDetail = "Hub → blog: bloque en 10 plazas prioritarias\n";
    $logDetail .= "Blog → geo: seo-local enlaza las 10 ciudades; bloque Cobertura local en "
        . count($blogPosts) . " artículos\n\n";
    foreach ($pages as $i => $it) {
        $logDetail .= ($i + 1) . '. ' . $it['city'] . ' — ' . $it['url'] . "\n";
    }
    $logDetail .= "\nAncla: " . $detail['anchor_article'];

    $result = cw_seo_mexico_checklist_complete_task(
        $conn,
        'blog_internlink',
        $summary,
        $logDetail,
        $userId,
        $detail,
        'Enlaces cruzados blog ↔ 10 plazas prioritarias publicados en código local.'
    );

    return [
        'ok' => !empty($result['ok']),
        'applied' => !empty($result['applied']),
        'complete' => !empty($result['ok']),
        'cities' => count($pages),
        'error' => $result['error'] ?? null,
    ];
}

/**
 * NAP / marca consistente en conlineweb.com (fuente única + superficies clave).
 *
 * @return array{ok:bool,applied:bool,complete:bool,error?:string}
 */
function cw_seo_mexico_checklist_apply_marca_consistente(mysqli $conn, int $userId = 0): array
{
    $root = cw_seo_mexico_site_root_path();
    if ($root === null) {
        // Fallback: carpeta hermana aunque no haya sitemap.xml en el chequeo anterior
        $fallback = realpath(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'conlineweb.com');
        $root = ($fallback !== false && is_dir($fallback)) ? $fallback : null;
    }
    if ($root === null) {
        return ['ok' => false, 'applied' => false, 'complete' => false, 'error' => 'No se encontró conlineweb.com local'];
    }

    $napFile = $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'cw-nap.php';
    if (!is_file($napFile)) {
        return ['ok' => false, 'applied' => false, 'complete' => false, 'error' => 'Falta includes/cw-nap.php'];
    }

    require_once $napFile;
    if (!function_exists('cw_nap')) {
        return ['ok' => false, 'applied' => false, 'complete' => false, 'error' => 'cw_nap() no disponible'];
    }
    $nap = cw_nap();
    $phone = (string) ($nap['phone_primary_schema'] ?? '');
    $website = (string) ($nap['website'] ?? '');
    $name = (string) ($nap['name'] ?? '');
    if ($phone === '' || $website === '' || $name === '') {
        return ['ok' => false, 'applied' => false, 'complete' => false, 'error' => 'NAP incompleto en cw-nap.php'];
    }

    $wired = [
        'includes/cw-nap.php',
        'includes/footer.php',
        'includes/cw-seo-meta.php',
        'includes/mexico/page-factory.php',
        'includes/legal/company.php',
        'contacto.php',
        'index.php',
    ];
    foreach ($wired as $rel) {
        $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!is_file($abs)) {
            return ['ok' => false, 'applied' => false, 'complete' => false, 'error' => 'Falta archivo: conlineweb.com/' . $rel];
        }
    }
    $wiredLabels = array_map(static fn ($r) => 'conlineweb.com/' . $r, $wired);

    $detail = [
        'status' => 'completo',
        'scope' => 'conlineweb.com',
        'nap' => [
            'name' => $name,
            'alternate_name' => (string) ($nap['alternate_name'] ?? ''),
            'address' => (string) ($nap['address_full'] ?? ''),
            'phone_primary' => (string) ($nap['phone_primary_display'] ?? ''),
            'phone_secondary' => (string) ($nap['phone_secondary_display'] ?? ''),
            'email' => (string) ($nap['email'] ?? ''),
            'website' => $website,
            'whatsapp_digits' => (string) ($nap['whatsapp_digits'] ?? ''),
        ],
        'wired_in' => $wiredLabels,
        'note' => 'GBP y directorios externos se alinean aparte cuando haya acceso a la ficha.',
        'updated' => (string) ($nap['updated'] ?? date('Y-m-d')),
    ];

    $summary = 'NAP unificado en conlineweb.com (fuente cw-nap.php)';
    $logDetail = "Marca: {$name}\n"
        . 'Tel: ' . ($nap['phone_primary_display'] ?? '') . "\n"
        . 'Web: ' . $website . "\n"
        . 'Dirección: ' . ($nap['address_full'] ?? '') . "\n"
        . 'Correo: ' . ($nap['email'] ?? '') . "\n"
        . "Superficies: footer, schema home, SEO meta, México page-factory, contacto, legal.\n"
        . 'Pendiente externo: Google Business Profile / citaciones (misma NAP).';

    $result = cw_seo_mexico_checklist_complete_task(
        $conn,
        'marca_consistente',
        $summary,
        $logDetail,
        $userId,
        $detail,
        'NAP canónico en sitio público; footer/schema/contacto/México leen includes/cw-nap.php.'
    );

    return [
        'ok' => !empty($result['ok']),
        'applied' => !empty($result['applied']),
        'complete' => !empty($result['ok']),
        'error' => $result['error'] ?? null,
    ];
}

/**
 * @return array<string,mixed>|null
 */
function cw_seo_mexico_checklist_task_detail(mysqli $conn, string $taskKey): ?array
{
    $taskKey = preg_replace('/[^a-z0-9_]/', '', strtolower($taskKey)) ?? '';
    if ($taskKey === '') {
        return null;
    }
    $stmt = $conn->prepare(
        'SELECT task_key, phase, title, description, sort_order, done, done_at, done_by, notes, detail_json, updated_at
         FROM cw_seo_mexico_checklist WHERE task_key = ? LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $taskKey);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!$row) {
        return null;
    }
    $row['done'] = (int) $row['done'] === 1;
    $row['detail'] = null;
    if (!empty($row['detail_json'])) {
        $decoded = json_decode((string) $row['detail_json'], true);
        if (is_array($decoded)) {
            $row['detail'] = $decoded;
        }
    }
    return $row;
}

/**
 * Etiqueta de estado unificada (misma semántica que Monitor).
 */
function cw_seo_mexico_checklist_work_status_label(string $status): string
{
    return match ($status) {
        'pending' => 'Pendiente',
        'working' => 'En revisión',
        'done' => 'Terminado',
        'failed' => 'Rechazado',
        default => $status,
    };
}

/**
 * Tipo corto para DataTable del checklist.
 *
 * @param array<string,mixed> $item
 */
function cw_seo_mexico_checklist_work_type_label(array $item): string
{
    $source = (string) ($item['source'] ?? '');
    if ($source === 'finding') {
        if (!empty($item['can_autofix'])) {
            return 'AutoFix';
        }
        $sev = strtolower((string) ($item['severity'] ?? ''));
        return $sev !== '' ? ('Hallazgo · ' . $sev) : 'Hallazgo';
    }
    $phase = (string) ($item['phase'] ?? '');
    $key = (string) ($item['task_key'] ?? '');
    if (str_starts_with($key, 'audit_')) {
        return 'Auditoría';
    }
    if ($phase !== '') {
        $num = preg_replace('/^(\d+).*/', '$1', $phase) ?? '';
        if ($num !== '' && $num !== $phase) {
            return 'Fase ' . $num;
        }
    }
    return !empty($item['is_dynamic']) ? 'Dinámica' : 'Tarea plan';
}

/**
 * Cola unificada checklist + findings para DataTable (estilo Monitor).
 *
 * @param list<array<string,mixed>> $taskRows filas del módulo (ya filtradas por execution)
 * @param array<string, array<string,mixed>> $taskMeta mapa task_key => meta modal (in_progress, etc.)
 * @param list<array<string,mixed>> $openFindings
 * @param list<array<string,mixed>> $closedFindings
 * @return list<array<string,mixed>>
 */
function cw_seo_mexico_checklist_work_feed(
    array $taskRows,
    array $taskMeta,
    array $openFindings = [],
    array $closedFindings = [],
    array $opts = []
): array {
    $plazasLocked = !empty($opts['plazas_locked']);
    $items = [];

    foreach ($taskRows as $row) {
        $key = (string) ($row['task_key'] ?? '');
        if ($key === '') {
            continue;
        }
        $meta = is_array($taskMeta[$key] ?? null) ? $taskMeta[$key] : [];
        $isDone = !empty($row['done']);
        $inProgress = !empty($meta['in_progress']);
        $work = $isDone ? 'done' : ($inProgress ? 'working' : 'pending');
        $detail = is_array($row['detail'] ?? null) ? $row['detail'] : (is_array($meta['pages'] ?? null) ? [] : []);
        $url = '';
        if (is_array($meta['pages'] ?? null) && $meta['pages'] !== []) {
            $url = (string) (($meta['pages'][0]['url'] ?? '') ?: '');
        } elseif (is_array($detail['urls'] ?? null) && $detail['urls'] !== []) {
            $url = (string) $detail['urls'][0];
        }
        $created = (string) ($row['updated_at'] ?? '');
        if ($created === '' && !empty($meta['plan']['start'])) {
            $created = (string) $meta['plan']['start'];
        }
        $implemented = $isDone ? (string) ($row['done_at'] ?? $row['updated_at'] ?? '') : '';
        $locked = ($key === 'plazas_prioridad') && $plazasLocked;
        $item = [
            'id' => 'task-' . $key,
            'source' => 'task',
            'source_id' => $key,
            'task_key' => $key,
            'finding_id' => 0,
            'title' => (string) ($row['title'] ?? $key),
            'phase' => (string) ($row['phase'] ?? ''),
            'phase_label' => (string) ($row['phase_label'] ?? ''),
            'is_dynamic' => !empty($meta['is_dynamic'])
                || str_starts_with($key, 'audit_')
                || str_starts_with($key, 'external_'),
            'severity' => '',
            'work_status' => $work,
            'work_label' => cw_seo_mexico_checklist_work_status_label($work),
            'url' => $url,
            'created_at' => $created,
            'implemented_at' => $implemented,
            'description' => (string) ($row['description'] ?? $meta['description'] ?? ''),
            'correction' => (string) ($row['correction'] ?? $meta['correction'] ?? ''),
            'evidence' => (string) ($meta['notes'] ?? $row['notes'] ?? ''),
            'can_autofix' => false,
            'can_toggle' => !$locked,
            'locked' => $locked,
            'done' => $isDone,
        ];
        $item['type_label'] = cw_seo_mexico_checklist_work_type_label($item);
        $items[] = $item;
    }

    $mapFinding = static function (array $f, string $work) use (&$items): void {
        $fid = (int) ($f['id'] ?? 0);
        if ($fid < 1) {
            return;
        }
        $canFix = $work !== 'done' && !empty($f['auto_fixable']) && empty($f['auto_applied']);
        $item = [
            'id' => 'finding-' . $fid,
            'source' => 'finding',
            'source_id' => $fid,
            'task_key' => (string) ($f['task_key'] ?? ''),
            'finding_id' => $fid,
            'title' => (string) ($f['title'] ?? 'Hallazgo'),
            'phase' => '06_auditoria_viva',
            'phase_label' => 'Auditoría',
            'is_dynamic' => true,
            'severity' => (string) ($f['severity'] ?? 'medium'),
            'work_status' => $work,
            'work_label' => cw_seo_mexico_checklist_work_status_label($work),
            'url' => (string) ($f['url'] ?? ''),
            'created_at' => (string) ($f['created_at'] ?? ''),
            'implemented_at' => $work === 'done' ? (string) ($f['updated_at'] ?? $f['created_at'] ?? '') : '',
            'description' => (string) ($f['evidence'] ?? ''),
            'correction' => (string) ($f['correction'] ?? ''),
            'evidence' => (string) ($f['evidence'] ?? ''),
            'can_autofix' => $canFix,
            'can_toggle' => false,
            'locked' => false,
            'done' => $work === 'done',
            'check_type' => (string) ($f['check_type'] ?? ''),
        ];
        $item['type_label'] = cw_seo_mexico_checklist_work_type_label($item);
        $items[] = $item;
    };

    foreach ($openFindings as $f) {
        $mapFinding($f, 'pending');
    }
    foreach ($closedFindings as $f) {
        $mapFinding($f, 'done');
    }

    usort($items, static function ($a, $b) {
        $rank = ['pending' => 0, 'working' => 1, 'done' => 2, 'failed' => 3];
        $ra = $rank[(string) ($a['work_status'] ?? '')] ?? 9;
        $rb = $rank[(string) ($b['work_status'] ?? '')] ?? 9;
        if ($ra !== $rb) {
            return $ra <=> $rb;
        }
        return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
    });

    return $items;
}

/**
 * @param list<array<string,mixed>> $items
 * @return array{pending:int,working:int,done:int,total:int,autofix:int}
 */
function cw_seo_mexico_checklist_work_stats(array $items): array
{
    $s = ['pending' => 0, 'working' => 0, 'done' => 0, 'total' => count($items), 'autofix' => 0];
    foreach ($items as $it) {
        $st = (string) ($it['work_status'] ?? 'pending');
        if (isset($s[$st])) {
            $s[$st]++;
        }
        if (!empty($it['can_autofix'])) {
            $s['autofix']++;
        }
    }
    return $s;
}
