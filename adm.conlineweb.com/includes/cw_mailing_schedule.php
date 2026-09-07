<?php
/**
 * Programación y cola de envíos mailing.
 * Cada plantilla puede llevar su propio horario (ahora / fecha / días+hora).
 */
declare(strict_types=1);

require_once __DIR__ . '/cw_mailing_service.php';

function cw_mailing_tz(): DateTimeZone
{
    try {
        return new DateTimeZone('America/Mexico_City');
    } catch (Throwable $e) {
        return new DateTimeZone('UTC');
    }
}

/**
 * Calcula slots de envío para un ítem de plantilla.
 *
 * @param array{mode?:string,scheduled_at?:string,weekdays?:list<int>,send_time?:string} $item
 * @return array{ok:bool,error?:string,slots?:list<DateTimeImmutable>}
 */
function cw_mailing_compute_slots(array $item): array
{
    $mode = (string) ($item['mode'] ?? 'now');
    if (!in_array($mode, ['now', 'once', 'weekly'], true)) {
        return ['ok' => false, 'error' => 'Modo inválido'];
    }

    $tz = cw_mailing_tz();
    $now = new DateTimeImmutable('now', $tz);
    $slots = [];

    if ($mode === 'now') {
        $slots[] = $now;
    } elseif ($mode === 'once') {
        $scheduledAt = trim((string) ($item['scheduled_at'] ?? ''));
        if ($scheduledAt === '') {
            return ['ok' => false, 'error' => 'Indica fecha y hora'];
        }
        try {
            $at = new DateTimeImmutable(str_replace('T', ' ', $scheduledAt), $tz);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Fecha/hora inválida'];
        }
        if ($at < $now->modify('-5 minutes')) {
            return ['ok' => false, 'error' => 'La fecha debe ser futura'];
        }
        $slots[] = $at;
    } else {
        $weekdays = $item['weekdays'] ?? [];
        if (!is_array($weekdays)) {
            $weekdays = [];
        }
        $weekdays = array_values(array_unique(array_filter(array_map('intval', $weekdays), static fn ($d) => $d >= 1 && $d <= 7)));
        sort($weekdays);
        if ($weekdays === []) {
            return ['ok' => false, 'error' => 'Selecciona al menos un día'];
        }
        $time = trim((string) ($item['send_time'] ?? ''));
        if ($time === '' || !preg_match('/^\d{2}:\d{2}/', $time)) {
            return ['ok' => false, 'error' => 'Indica la hora (HH:MM)'];
        }
        $hh = (int) substr($time, 0, 2);
        $mm = (int) substr($time, 3, 2);
        $todayN = (int) $now->format('N');
        $map = [];
        for ($week = 0; $week < 2; $week++) {
            foreach ($weekdays as $n) {
                $diff = ($n - $todayN + 7) % 7;
                $daysAhead = $diff + ($week * 7);
                $slot = $now->modify('+' . $daysAhead . ' days')->setTime($hh, $mm, 0);
                if ($slot <= $now) {
                    $slot = $slot->modify('+7 days');
                }
                $map[$slot->format('Y-m-d H:i:s')] = $slot;
            }
        }
        ksort($map);
        $slots = array_values($map);
    }

    if ($slots === []) {
        return ['ok' => false, 'error' => 'No se pudo calcular horarios'];
    }

    return ['ok' => true, 'slots' => $slots];
}

/**
 * Campaña multi-plantilla: cada ítem = plantilla + su propio horario.
 *
 * @param list<int> $recipientIds
 * @param list<array{template_id:int,mode?:string,scheduled_at?:string,weekdays?:list<int>,send_time?:string}> $items
 * @return array{ok:bool,error?:string,job_id?:int,queued?:int,sent_now?:int,failed_now?:int,message?:string}
 */
function cw_mailing_schedule_multi(
    mysqli $conn,
    string $audience,
    array $recipientIds,
    array $items,
    ?int $createdBy
): array {
    if (!in_array($audience, ['cliente', 'lead'], true)) {
        return ['ok' => false, 'error' => 'Audiencia inválida'];
    }
    $recipientIds = array_values(array_unique(array_filter(array_map('intval', $recipientIds), static fn ($id) => $id > 0)));
    if ($recipientIds === []) {
        return ['ok' => false, 'error' => 'Selecciona al menos un destinatario'];
    }
    if (count($recipientIds) > 80) {
        return ['ok' => false, 'error' => 'Máximo 80 destinatarios por lote'];
    }
    if ($items === [] || count($items) > 10) {
        return ['ok' => false, 'error' => 'Agrega entre 1 y 10 plantillas'];
    }

    $prepared = [];
    $seenTpl = [];
    foreach ($items as $idx => $item) {
        if (!is_array($item)) {
            return ['ok' => false, 'error' => 'Ítem de plantilla inválido'];
        }
        $tid = (int) ($item['template_id'] ?? 0);
        if ($tid <= 0) {
            return ['ok' => false, 'error' => 'Plantilla #' . ($idx + 1) . ' sin seleccionar'];
        }
        if (isset($seenTpl[$tid])) {
            return ['ok' => false, 'error' => 'No repitas la misma plantilla en el lote'];
        }
        $seenTpl[$tid] = true;
        $tpl = cw_mailing_get_template($conn, $tid);
        if (!$tpl || !(int) ($tpl['active'] ?? 0)) {
            return ['ok' => false, 'error' => 'Plantilla #' . ($idx + 1) . ' no disponible'];
        }
        $slotRes = cw_mailing_compute_slots($item);
        if (empty($slotRes['ok'])) {
            return ['ok' => false, 'error' => 'Plantilla #' . ($idx + 1) . ': ' . ($slotRes['error'] ?? 'horario inválido')];
        }
        $prepared[] = [
            'template_id' => $tid,
            'mode' => (string) ($item['mode'] ?? 'now'),
            'slots' => $slotRes['slots'],
        ];
    }

    $people = cw_mailing_resolve_recipients($conn, $audience, $recipientIds);
    if ($people === []) {
        return ['ok' => false, 'error' => 'Ningún destinatario con correo válido'];
    }

    $first = $prepared[0];
    $secondId = isset($prepared[1]) ? (int) $prepared[1]['template_id'] : null;
    $modes = array_unique(array_map(static fn ($p) => $p['mode'], $prepared));
    $jobMode = count($modes) === 1 ? $modes[0] : 'once';
    $recCount = count($people);
    $createdByVal = $createdBy ?? 0;
    $tpl2Sql = $secondId ? (string) $secondId : 'NULL';

    $okIns = $conn->query(
        "INSERT INTO cw_mailing_jobs
        (audience, template_id_1, template_id_2, mode, scheduled_at, weekdays, send_time, template2_delay_hours, recipient_count, queue_count, status, created_by)
        VALUES (
            '" . $conn->real_escape_string($audience) . "',
            " . (int) $first['template_id'] . ",
            {$tpl2Sql},
            '" . $conn->real_escape_string($jobMode) . "',
            NULL, '', NULL, 0,
            " . (int) $recCount . ",
            0,
            'pending',
            " . (int) $createdByVal . "
        )"
    );
    if (!$okIns) {
        return ['ok' => false, 'error' => 'Error al guardar job: ' . $conn->error];
    }
    $jobId = (int) $conn->insert_id;

    $qStmt = $conn->prepare(
        'INSERT INTO cw_mailing_queue
        (job_id, template_id, audience, audience_id, email, name, company, phone, send_at, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending\')'
    );
    if (!$qStmt) {
        return ['ok' => false, 'error' => 'No se pudo preparar la cola'];
    }

    $queued = 0;
    $hasNow = false;
    foreach ($prepared as $p) {
        foreach ($p['slots'] as $slot) {
            /** @var DateTimeImmutable $slot */
            if ($slot <= new DateTimeImmutable('now', cw_mailing_tz())) {
                $hasNow = true;
            }
            $sendAt = $slot->format('Y-m-d H:i:s');
            $tid = (int) $p['template_id'];
            foreach ($people as $person) {
                $aid = (int) $person['id'];
                $email = (string) $person['email'];
                $name = (string) $person['name'];
                $company = (string) $person['company'];
                $phone = (string) $person['phone'];
                $qStmt->bind_param('iisisssss', $jobId, $tid, $audience, $aid, $email, $name, $company, $phone, $sendAt);
                if ($qStmt->execute()) {
                    $queued++;
                }
            }
        }
    }
    $qStmt->close();
    $conn->query('UPDATE cw_mailing_jobs SET queue_count=' . (int) $queued . ' WHERE id=' . (int) $jobId);

    $sentNow = 0;
    $failedNow = 0;
    if ($hasNow || $jobMode === 'now') {
        $proc = cw_mailing_process_queue($conn, 200);
        $sentNow = (int) ($proc['sent'] ?? 0);
        $failedNow = (int) ($proc['failed'] ?? 0);
    }
    $conn->query(
        "UPDATE cw_mailing_jobs j SET j.status='done'
         WHERE j.id=" . (int) $jobId . "
           AND NOT EXISTS (SELECT 1 FROM cw_mailing_queue q WHERE q.job_id=j.id AND q.status='pending')"
    );

    $nTpl = count($prepared);
    return [
        'ok' => true,
        'job_id' => $jobId,
        'queued' => $queued,
        'sent_now' => $sentNow,
        'failed_now' => $failedNow,
        'message' => "{$nTpl} plantilla(s) · cola {$queued} · enviados ahora {$sentNow} · fallidos {$failedNow}.",
    ];
}

/**
 * Compat: firma antigua (1–2 plantillas + un horario global).
 *
 * @param list<int> $recipientIds
 * @param list<int> $weekdays
 */
function cw_mailing_schedule_campaign(
    mysqli $conn,
    string $audience,
    array $recipientIds,
    int $templateId1,
    int $templateId2,
    string $mode,
    ?string $scheduledAt,
    array $weekdays,
    ?string $sendTime,
    int $template2DelayHours,
    ?int $createdBy
): array {
    $items = [[
        'template_id' => $templateId1,
        'mode' => $mode,
        'scheduled_at' => (string) ($scheduledAt ?? ''),
        'weekdays' => $weekdays,
        'send_time' => (string) ($sendTime ?? ''),
    ]];
    if ($templateId2 > 0) {
        $tz = cw_mailing_tz();
        $baseSlots = cw_mailing_compute_slots($items[0]);
        if (empty($baseSlots['ok'])) {
            return ['ok' => false, 'error' => $baseSlots['error'] ?? 'Horario inválido'];
        }
        /** @var DateTimeImmutable $first */
        $first = $baseSlots['slots'][0];
        $delay = max(0, min(24 * 30, $template2DelayHours));
        $secondAt = $first->modify('+' . $delay . ' hours');
        $items[] = [
            'template_id' => $templateId2,
            'mode' => 'once',
            'scheduled_at' => $secondAt->format('Y-m-d H:i'),
            'weekdays' => [],
            'send_time' => '',
        ];
    }

    return cw_mailing_schedule_multi($conn, $audience, $recipientIds, $items, $createdBy);
}

/**
 * @param list<int> $ids
 * @return list<array{id:int,email:string,name:string,company:string,phone:string}>
 */
function cw_mailing_resolve_recipients(mysqli $conn, string $audience, array $ids): array
{
    $out = [];
    foreach ($ids as $id) {
        $id = (int) $id;
        if ($id <= 0) {
            continue;
        }
        if ($audience === 'cliente') {
            $st = $conn->prepare(
                'SELECT id, empresa, nombre_contacto, correo, telefono FROM clientes WHERE id=? AND IFNULL(eliminado,0)=0 LIMIT 1'
            );
            if (!$st) {
                continue;
            }
            $st->bind_param('i', $id);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            if (!$row) {
                continue;
            }
            $email = trim((string) ($row['correo'] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $out[] = [
                'id' => (int) $row['id'],
                'email' => $email,
                'name' => (string) ($row['nombre_contacto'] ?? ''),
                'company' => (string) ($row['empresa'] ?? ''),
                'phone' => (string) ($row['telefono'] ?? ''),
            ];
        } else {
            $st = $conn->prepare(
                'SELECT id, nombre, apellido, empresa, correo, telefono FROM leads WHERE id=? AND IFNULL(eliminado,0)=0 LIMIT 1'
            );
            if (!$st) {
                continue;
            }
            $st->bind_param('i', $id);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            if (!$row) {
                continue;
            }
            $email = trim((string) ($row['correo'] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $full = trim(((string) ($row['nombre'] ?? '')) . ' ' . ((string) ($row['apellido'] ?? '')));
            $out[] = [
                'id' => (int) $row['id'],
                'email' => $email,
                'name' => $full !== '' ? $full : (string) ($row['nombre'] ?? ''),
                'company' => (string) ($row['empresa'] ?? ''),
                'phone' => (string) ($row['telefono'] ?? ''),
            ];
        }
    }

    return $out;
}

/**
 * @return array{ok:bool,sent:int,failed:int,skipped:int}
 */
function cw_mailing_process_queue(mysqli $conn, int $limit = 40): array
{
    $limit = max(1, min(100, $limit));
    $tz = cw_mailing_tz();
    $now = (new DateTimeImmutable('now', $tz))->format('Y-m-d H:i:s');

    $res = $conn->query(
        "SELECT * FROM cw_mailing_queue
         WHERE status='pending' AND send_at <= '" . $conn->real_escape_string($now) . "'
         ORDER BY send_at ASC, id ASC
         LIMIT {$limit}"
    );
    if (!$res) {
        return ['ok' => false, 'sent' => 0, 'failed' => 0, 'skipped' => 0];
    }

    $sent = 0;
    $failed = 0;
    while ($row = $res->fetch_assoc()) {
        $qid = (int) $row['id'];
        $tpl = cw_mailing_get_template($conn, (int) $row['template_id']);
        if (!$tpl) {
            $conn->query("UPDATE cw_mailing_queue SET status='failed', error_message='Plantilla no encontrada', processed_at=NOW() WHERE id={$qid}");
            $failed++;
            continue;
        }
        $recipient = [
            'name' => (string) ($row['name'] ?? ''),
            'company' => (string) ($row['company'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
        ];
        $sendRes = cw_mailing_send_one(
            $conn,
            $tpl,
            $recipient,
            (string) $row['audience'],
            (int) $row['audience_id'],
            null
        );
        if (!empty($sendRes['ok'])) {
            $sid = (int) ($sendRes['send_id'] ?? 0);
            $conn->query("UPDATE cw_mailing_queue SET status='sent', send_id={$sid}, processed_at=NOW() WHERE id={$qid}");
            $sent++;
        } else {
            $err = $conn->real_escape_string(mb_substr((string) ($sendRes['error'] ?? 'Error'), 0, 480));
            $conn->query("UPDATE cw_mailing_queue SET status='failed', error_message='{$err}', processed_at=NOW() WHERE id={$qid}");
            $failed++;
        }
        usleep(100000);
    }

    $conn->query(
        "UPDATE cw_mailing_jobs j
         SET j.status='done'
         WHERE j.status='pending'
           AND NOT EXISTS (SELECT 1 FROM cw_mailing_queue q WHERE q.job_id=j.id AND q.status='pending')"
    );

    return ['ok' => true, 'sent' => $sent, 'failed' => $failed, 'skipped' => 0];
}

/** @return list<array<string,mixed>> */
function cw_mailing_list_queue(mysqli $conn, int $limit = 60): array
{
    $limit = max(1, min(120, $limit));
    $res = $conn->query(
        "SELECT q.*, t.title AS template_title
         FROM cw_mailing_queue q
         LEFT JOIN cw_mailing_templates t ON t.id = q.template_id
         ORDER BY FIELD(q.status,'pending','failed','sent','cancelled'), q.send_at ASC
         LIMIT {$limit}"
    );
    if (!$res) {
        return [];
    }
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $out[] = $row;
    }

    return $out;
}
