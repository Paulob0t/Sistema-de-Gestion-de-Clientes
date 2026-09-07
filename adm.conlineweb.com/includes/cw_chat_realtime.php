<?php
/**
 * Tiempo real: presencia, typing, SSE snapshots, contadores
 */
require_once __DIR__ . '/cw_chat_service.php';

function cw_chat_bump_realtime(mysqli $conn, int $conversacionId): void
{
    $stmt = $conn->prepare('UPDATE cw_chat_conversaciones SET realtime_version = realtime_version + 1 WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $conversacionId);
        $stmt->execute();
        $stmt->close();
    }
}

function cw_chat_typing_active(?string $at, int $seconds = 8): bool
{
    if (!$at) {
        return false;
    }
    return (time() - strtotime($at)) <= $seconds;
}

function cw_chat_set_typing(mysqli $conn, int $conversacionId, string $actor, bool $active): void
{
    $col = $actor === 'agente' ? 'agente_typing_at' : 'cliente_typing_at';
    $val = $active ? cw_chat_now() : null;
    if ($val) {
        $stmt = $conn->prepare("UPDATE cw_chat_conversaciones SET $col = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('si', $val, $conversacionId);
            $stmt->execute();
            $stmt->close();
        }
    } else {
        $stmt = $conn->prepare("UPDATE cw_chat_conversaciones SET $col = NULL WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $conversacionId);
            $stmt->execute();
            $stmt->close();
        }
    }
}

/** Conversaciones modificadas desde un timestamp (para sync incremental de bandeja). */
function cw_chat_list_delta(mysqli $conn, string $since, int $limit = 30): array
{
    $stmt = $conn->prepare('SELECT c.*, cl.nombre_contacto, cl.empresa, cl.correo, cl.telefono,
            a.Nombre AS responsable_nombre
        FROM cw_chat_conversaciones c
        LEFT JOIN clientes cl ON c.cliente_id = cl.id
        LEFT JOIN agentes a ON c.responsable_id = a.Idusu
        WHERE c.eliminado = 0
          AND GREATEST(
            COALESCE(c.ultimo_mensaje_at, c.iniciada_at),
            COALESCE(c.leido_admin_at, c.iniciada_at)
        ) > ?
        ORDER BY COALESCE(c.ultimo_mensaje_at, c.iniciada_at) DESC
        LIMIT ?');
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('si', $since, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $minSinResp = (int) cw_chat_config_get($conn, 'alerta_sin_respuesta_minutos', '5');
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $out[] = cw_chat_format_conversation_row($conn, $row, $minSinResp);
    }
    $stmt->close();
    return $out;
}

function cw_chat_touch_presence(mysqli $conn, int $conversacionId, string $side): void
{
    if ($side === 'cliente') {
        $stmt = $conn->prepare('UPDATE cw_chat_conversaciones SET cliente_online_at = NOW() WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('i', $conversacionId);
            $stmt->execute();
            $stmt->close();
        }
    } elseif ($side === 'agente' && function_exists('cw_inbox_uid')) {
        $uid = cw_inbox_uid();
        if ($uid > 0) {
            $stmt = $conn->prepare("INSERT INTO cw_chat_agentes_estado (agente_id, estado, ultima_conexion)
                VALUES (?, 'online', NOW()) ON DUPLICATE KEY UPDATE estado='online', ultima_conexion=NOW()");
            if ($stmt) {
                $stmt->bind_param('i', $uid);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

function cw_chat_mark_client_read(mysqli $conn, int $conversacionId): void
{
    $now = cw_chat_now();
    $stmt = $conn->prepare("UPDATE cw_chat_mensajes SET leido_at = ?
        WHERE conversacion_id = ? AND remitente_tipo IN ('agente','bot') AND leido_at IS NULL");
    if ($stmt) {
        $stmt->bind_param('si', $now, $conversacionId);
        $stmt->execute();
        $stmt->close();
    }
    $upd = $conn->prepare('UPDATE cw_chat_conversaciones SET leido_cliente_at = ? WHERE id = ?');
    if ($upd) {
        $upd->bind_param('si', $now, $conversacionId);
        $upd->execute();
        $upd->close();
    }
}

/** @return array<int,array<string,mixed>> */
function cw_chat_get_messages_before(mysqli $conn, int $conversacionId, int $beforeId, int $limit = 30): array
{
    $stmt = $conn->prepare('SELECT m.*, COALESCE(a.Nombre, l.usuario) AS remitente_nombre
        FROM cw_chat_mensajes m
        LEFT JOIN agentes a ON m.remitente_tipo = "agente" AND m.remitente_id = a.Idusu
        LEFT JOIN login l ON m.remitente_tipo = "agente" AND m.remitente_id = l.id
        WHERE m.conversacion_id = ? AND m.id < ?
        ORDER BY m.id DESC LIMIT ?');
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('iii', $conversacionId, $beforeId, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = cw_chat_format_message_row($row);
    }
    $stmt->close();
    return array_reverse($rows);
}

/** @return array<string,mixed> */
function cw_chat_realtime_snapshot(mysqli $conn, int $conversacionId, int $afterId, string $role = 'cliente'): array
{
    $conv = cw_chat_get_conversation($conn, $conversacionId);
    if (!$conv) {
        return ['error' => 'not_found'];
    }

    if ($role === 'cliente') {
        cw_chat_touch_presence($conn, $conversacionId, 'cliente');
        cw_chat_mark_client_read($conn, $conversacionId);
    } else {
        cw_chat_touch_presence($conn, $conversacionId, 'agente');
        cw_chat_mark_admin_read($conn, $conversacionId);
    }

    $messages = cw_chat_get_messages($conn, $conversacionId, $afterId > 0 ? $afterId : null);

    $readUpdates = [];
    if ($role === 'cliente') {
        $rStmt = $conn->prepare("SELECT id, leido_at FROM cw_chat_mensajes
            WHERE conversacion_id = ? AND remitente_tipo = 'cliente' AND leido_at IS NOT NULL
            ORDER BY id DESC LIMIT 20");
        if ($rStmt) {
            $rStmt->bind_param('i', $conversacionId);
            $rStmt->execute();
            $rRes = $rStmt->get_result();
            while ($rRow = $rRes->fetch_assoc()) {
                $readUpdates[] = ['id' => (int) $rRow['id'], 'delivery' => 'read'];
            }
            $rStmt->close();
        }
    }

    return [
        'version' => (int) ($conv['realtime_version'] ?? 0),
        'messages' => $messages,
        'read_updates' => $readUpdates,
        'conversation' => [
            'id' => (int) $conv['id'],
            'estado' => $conv['estado'],
            'estado_label' => cw_chat_estado_label($conv['estado']),
            'ultimo_mensaje_at' => $conv['ultimo_mensaje_at'],
        ],
        'typing' => [
            'cliente' => cw_chat_typing_active($conv['cliente_typing_at'] ?? null),
            'agente' => cw_chat_typing_active($conv['agente_typing_at'] ?? null),
        ],
        'presence' => [
            'cliente_online' => cw_chat_typing_active($conv['cliente_online_at'] ?? null, 45),
        ],
    ];
}

/** @return array<string,int> */
function cw_chat_global_counts(mysqli $conn): array
{
    $counts = [
        'sin_responder' => 0,
        'nuevas' => 0,
        'pendientes' => 0,
        'activas' => 0,
        'por_revisar' => 0,
    ];

    $r = $conn->query("SELECT
        SUM(CASE WHEN estado != 'cerrada' AND sin_respuesta_desde IS NOT NULL THEN 1 ELSE 0 END) AS sin_responder,
        SUM(CASE WHEN DATE(iniciada_at) = CURDATE() THEN 1 ELSE 0 END) AS nuevas,
        SUM(CASE WHEN estado IN ('pendiente_humano','humano') THEN 1 ELSE 0 END) AS pendientes,
        SUM(CASE WHEN estado != 'cerrada' THEN 1 ELSE 0 END) AS activas,
        SUM(CASE WHEN estado != 'cerrada'
            AND (
                leido_admin_at IS NULL
                OR (ultimo_mensaje_at > leido_admin_at AND ultimo_mensaje_remitente = 'cliente')
                OR (
                    prioridad = 'alta'
                    AND motivo_escalamiento IS NOT NULL
                    AND TRIM(motivo_escalamiento) != ''
                    AND estado IN ('bot','activa','pendiente_humano')
                )
            )
            THEN 1 ELSE 0 END) AS por_revisar
        FROM cw_chat_conversaciones WHERE eliminado = 0");
    if ($r && ($row = $r->fetch_assoc())) {
        foreach ($counts as $k => $_) {
            $counts[$k] = (int) ($row[$k] ?? 0);
        }
    }

    return $counts;
}

function cw_chat_sse_headers(): void
{
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', '1');
    }
    @ini_set('zlib.output_compression', '0');
    @ini_set('output_buffering', 'off');
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
}

function cw_chat_sse_send(string $event, array $data): void
{
    echo 'event: ' . $event . "\n";
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    if (ob_get_level()) {
        ob_flush();
    }
    flush();
}
