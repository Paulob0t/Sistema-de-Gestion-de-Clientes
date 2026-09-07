<?php
require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_chat_migrate.php';
require_once dirname(__DIR__, 2) . '/includes/cw_chat_realtime.php';
require_once dirname(__DIR__, 2) . '/includes/cw_chat_alerts.php';
require_once dirname(__DIR__) . '/includes/inbox_helpers.php';

cw_chat_migrate($conn);
cw_chat_sse_headers();

$id = (int) ($_GET['id'] ?? 0);
$afterId = (int) ($_GET['after_id'] ?? 0);
$lastVersion = (int) ($_GET['version'] ?? 0);
$listOnly = (int) ($_GET['list'] ?? 0) === 1;

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$start = time();
$maxRuntime = 50;
$lastTypingCliente = false;
$lastTypingAgente = false;

$listCursor = trim((string) ($_GET['since'] ?? ''));
if ($listOnly && $listCursor === '') {
    $r = $conn->query('SELECT DATE_SUB(NOW(), INTERVAL 3 MINUTE) AS t');
    $listCursor = $r ? (string) ($r->fetch_assoc()['t'] ?? date('Y-m-d H:i:s', time() - 180)) : date('Y-m-d H:i:s', time() - 180);
}
$lastCountsHash = '';

cw_chat_sse_send('connected', ['mode' => $listOnly ? 'inbox' : 'conversation']);

while ((time() - $start) < $maxRuntime) {
    if (connection_aborted()) {
        break;
    }

    cw_chat_alert_process_due($conn);

    if ($listOnly) {
        $counts = cw_chat_global_counts($conn);
        $hash = md5(json_encode($counts));
        if ($hash !== $lastCountsHash) {
            $lastCountsHash = $hash;
            cw_chat_sse_send('counts', $counts);
        }

        $prevCursor = $listCursor;
        $delta = cw_chat_list_delta($conn, $listCursor, 25);
        if ($delta !== []) {
            foreach ($delta as $row) {
                $ts = (string) ($row['ultimo_mensaje_at'] ?? '');
                if ($ts > $listCursor) {
                    $listCursor = $ts;
                }
            }
            if ($listCursor > $prevCursor) {
                cw_chat_sse_send('list_delta', [
                    'rows' => $delta,
                    'counts' => $counts,
                    'cursor' => $listCursor,
                ]);
            }
        }

        sleep(4);
        continue;
    }

    if ($id <= 0) {
        $counts = cw_chat_global_counts($conn);
        $hash = md5(json_encode($counts));
        if ($hash !== $lastCountsHash) {
            $lastCountsHash = $hash;
            cw_chat_sse_send('counts', $counts);
        }
        sleep(4);
        continue;
    }

    $conv = cw_chat_get_conversation($conn, $id);
    if (!$conv) {
        break;
    }

    $version = (int) ($conv['realtime_version'] ?? 0);
    $typingCliente = cw_chat_typing_active($conv['cliente_typing_at'] ?? null);
    $typingAgente = cw_chat_typing_active($conv['agente_typing_at'] ?? null);
    $typingChanged = ($typingCliente !== $lastTypingCliente) || ($typingAgente !== $lastTypingAgente);
    $lastTypingCliente = $typingCliente;
    $lastTypingAgente = $typingAgente;

    if ($version !== $lastVersion) {
        $snapshot = cw_chat_realtime_snapshot($conn, $id, $afterId, 'agente');
        $lastVersion = (int) ($snapshot['version'] ?? $version);
        if (!empty($snapshot['messages'])) {
            $last = end($snapshot['messages']);
            $afterId = (int) ($last['id'] ?? $afterId);
        }
        $snapshot['counts'] = cw_chat_global_counts($conn);
        $snapshot['conversation_meta'] = [
            'estado' => $conv['estado'],
            'estado_label' => cw_chat_estado_label($conv['estado'] ?? 'bot'),
            'responsable_id' => $conv['responsable_id'] ? (int) $conv['responsable_id'] : null,
        ];
        cw_chat_sse_send('update', $snapshot);
    } elseif ($typingChanged) {
        cw_chat_sse_send('update', [
            'version' => $version,
            'messages' => [],
            'typing' => ['cliente' => $typingCliente, 'agente' => $typingAgente],
            'counts' => cw_chat_global_counts($conn),
        ]);
    } else {
        cw_chat_sse_send('ping', ['ts' => time()]);
    }

    sleep(2);
}

cw_chat_sse_send('reconnect', ['after_id' => $afterId, 'version' => $lastVersion, 'cursor' => $listCursor]);
