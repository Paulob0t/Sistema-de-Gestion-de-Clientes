<?php
/**
 * Alertas por correo inteligentes — diferidas, sin duplicados, cancelables.
 *
 * Reglas:
 * - No enviar al recibir cada mensaje.
 * - Programar alerta T+N minutos tras mensaje del cliente.
 * - Cancelar si el agente abre/responde o el mensaje fue leído.
 * - Un solo correo pendiente por conversación (se reinicia el temporizador).
 */
require_once __DIR__ . '/cw_chat_service.php';
require_once __DIR__ . '/cw_chat_notify.php';

function cw_chat_alert_delay_minutes(mysqli $conn): int
{
    $min = (int) cw_chat_config_get($conn, 'alerta_sin_respuesta_minutos', '5');
    return max(1, min(120, $min));
}

/** Programa o reinicia alerta para una conversación con mensaje del cliente. */
function cw_chat_alert_schedule(mysqli $conn, int $conversacionId, int $mensajeId): void
{
    if (!cw_chat_notify_enabled($conn)) {
        return;
    }

    $conv = cw_chat_get_conversation($conn, $conversacionId);
    if (!$conv || ($conv['estado'] ?? '') === 'cerrada') {
        return;
    }
    if (($conv['ultimo_mensaje_remitente'] ?? '') !== 'cliente') {
        return;
    }

    $delay = cw_chat_alert_delay_minutes($conn);
    $scheduled = date('Y-m-d H:i:s', time() + ($delay * 60));
    $now = cw_chat_now();

    $stmt = $conn->prepare("SELECT id FROM cw_chat_alertas_pendientes
        WHERE conversacion_id = ? AND estado = 'pendiente' LIMIT 1");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $conversacionId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $aid = (int) $row['id'];
        $upd = $conn->prepare("UPDATE cw_chat_alertas_pendientes
            SET ultimo_mensaje_id = ?, programada_at = ?, updated_at = ?
            WHERE id = ? AND estado = 'pendiente'");
        if ($upd) {
            $upd->bind_param('issi', $mensajeId, $scheduled, $now, $aid);
            $upd->execute();
            $upd->close();
        }
        return;
    }

    $ins = $conn->prepare("INSERT INTO cw_chat_alertas_pendientes
        (conversacion_id, ultimo_mensaje_id, programada_at, estado, created_at)
        VALUES (?,?,?,'pendiente',?)");
    if ($ins) {
        $ins->bind_param('iiss', $conversacionId, $mensajeId, $scheduled, $now);
        $ins->execute();
        $ins->close();
    }
}

/** Cancela alertas pendientes (agente abrió, respondió o leyó). */
function cw_chat_alert_cancel(mysqli $conn, int $conversacionId, string $motivo = 'atendida'): void
{
    $now = cw_chat_now();
    $stmt = $conn->prepare("UPDATE cw_chat_alertas_pendientes
        SET estado = 'cancelada', cancel_motivo = ?, updated_at = ?
        WHERE conversacion_id = ? AND estado = 'pendiente'");
    if ($stmt) {
        $stmt->bind_param('ssi', $motivo, $now, $conversacionId);
        $stmt->execute();
        $stmt->close();
    }
}

/** Procesa alertas vencidas (llamar desde SSE/cron de forma oportunista). */
function cw_chat_alert_process_due(mysqli $conn, int $limit = 15): int
{
    static $lastRun = 0;
    if ((time() - $lastRun) < 20) {
        return 0;
    }
    $lastRun = time();

    if (!cw_chat_notify_enabled($conn)) {
        return 0;
    }

    $now = cw_chat_now();
    $stmt = $conn->prepare("SELECT a.* FROM cw_chat_alertas_pendientes a
        WHERE a.estado = 'pendiente' AND a.programada_at <= ?
        ORDER BY a.programada_at ASC LIMIT ?");
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('si', $now, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();

    $sent = 0;
    foreach ($rows as $alert) {
        $convId = (int) $alert['conversacion_id'];
        $msgId = (int) $alert['ultimo_mensaje_id'];
        $alertId = (int) $alert['id'];

        $conv = cw_chat_get_conversation($conn, $convId);
        if (!$conv || ($conv['estado'] ?? '') === 'cerrada') {
            cw_chat_alert_mark($conn, $alertId, 'cancelada', 'cerrada');
            continue;
        }

        if (($conv['ultimo_mensaje_remitente'] ?? '') !== 'cliente') {
            cw_chat_alert_mark($conn, $alertId, 'cancelada', 'respondida');
            continue;
        }

        if (empty($conv['sin_respuesta_desde'])) {
            cw_chat_alert_mark($conn, $alertId, 'cancelada', 'respondida');
            continue;
        }

        $ultimoAt = strtotime((string) ($conv['ultimo_mensaje_at'] ?? ''));
        $leidoAdmin = !empty($conv['leido_admin_at']) ? strtotime((string) $conv['leido_admin_at']) : 0;
        if ($leidoAdmin > 0 && $leidoAdmin >= $ultimoAt) {
            cw_chat_alert_mark($conn, $alertId, 'cancelada', 'leida');
            continue;
        }

        $msgStmt = $conn->prepare('SELECT contenido FROM cw_chat_mensajes WHERE id = ? AND conversacion_id = ? LIMIT 1');
        $preview = '';
        if ($msgStmt) {
            $msgStmt->bind_param('ii', $msgId, $convId);
            $msgStmt->execute();
            $mrow = $msgStmt->get_result()->fetch_assoc();
            $msgStmt->close();
            $preview = $mrow['contenido'] ?? ($conv['ultimo_mensaje_preview'] ?? '');
        }

        cw_chat_notify_new_message($conn, $convId, $preview);
        cw_chat_alert_mark($conn, $alertId, 'enviada', null);
        $sent++;
    }

    return $sent;
}

function cw_chat_alert_mark(mysqli $conn, int $alertId, string $estado, ?string $motivo): void
{
    $now = cw_chat_now();
    $stmt = $conn->prepare("UPDATE cw_chat_alertas_pendientes
        SET estado = ?, cancel_motivo = ?, updated_at = ?, enviada_at = CASE WHEN ? = 'enviada' THEN ? ELSE enviada_at END
        WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('sssssi', $estado, $motivo, $now, $estado, $now, $alertId);
        $stmt->execute();
        $stmt->close();
    }
}
