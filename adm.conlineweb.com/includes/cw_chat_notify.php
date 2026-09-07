<?php
/**
 * Notificaciones por correo del Chat Inteligente
 */
require_once __DIR__ . '/cw_chat_service.php';
require_once __DIR__ . '/cw_chat_client_context.php';
require_once __DIR__ . '/chat_email_template.php';

function cw_chat_notify_enabled(mysqli $conn): bool
{
    return cw_chat_config_get($conn, 'alerta_email_activa', '1') === '1';
}

function cw_chat_notify_recipients(mysqli $conn): array
{
    $list = [];

    // Prioridad: correo de soporte del chatbot (Chat → Configuración).
    $soporte = trim(cw_chat_config_get($conn, 'email_soporte', ''));
    if ($soporte !== '' && filter_var($soporte, FILTER_VALIDATE_EMAIL)) {
        $list[] = $soporte;
    }

    if (function_exists('cw_hub_alert_recipients') || file_exists(__DIR__ . '/cw_hub_notify.php')) {
        require_once __DIR__ . '/cw_hub_notify.php';
        foreach (cw_hub_alert_recipients(null) as $addr) {
            $addr = trim((string) $addr);
            if ($addr !== '' && filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                $list[] = $addr;
            }
        }
    }

    if ($list === []) {
        $list[] = 'servicios@conlineweb.com';
    }

    return array_values(array_unique($list));
}

/**
 * @return array{ok:bool,error?:string,recipients?:list<string>}
 */
function cw_chat_send_email(mysqli $conn, string $subject, string $html, bool $force = false): array
{
    if (!$force && !cw_chat_notify_enabled($conn)) {
        return ['ok' => false, 'error' => 'Alertas desactivadas'];
    }

    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        require_once dirname(__DIR__) . '/PHPMailer/src/Exception.php';
        require_once dirname(__DIR__) . '/PHPMailer/src/PHPMailer.php';
        require_once dirname(__DIR__) . '/PHPMailer/src/SMTP.php';
    }
    require_once dirname(__DIR__) . '/smtp_config_helper.php';

    $recipients = cw_chat_notify_recipients($conn);
    if ($recipients === []) {
        return ['ok' => false, 'error' => 'Sin destinatarios'];
    }

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        configure_phpmailer_by_system($mail, 'conlineweb');
        foreach ($recipients as $addr) {
            $mail->addAddress($addr);
        }
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $html;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html));
        $mail->send();
        return ['ok' => true, 'recipients' => $recipients];
    } catch (Throwable $e) {
        $detail = $e->getMessage();
        if (isset($mail) && !empty($mail->ErrorInfo)) {
            $detail = $mail->ErrorInfo;
        }
        error_log('[cw_chat_send_email] ' . $detail . ' | to=' . implode(',', $recipients));
        return ['ok' => false, 'error' => $detail, 'recipients' => $recipients];
    }
}

function cw_chat_notify_new_conversation(mysqli $conn, int $conversacionId): void
{
    $conv = cw_chat_get_conversation($conn, $conversacionId);
    if (!$conv || empty($conv['cliente_id'])) {
        return;
    }
    $ctx = cw_chat_client_context($conn, (int) $conv['cliente_id']);
    $cliente = $ctx['cliente']['nombre_contacto'] ?? 'Cliente';
    $html = chat_email_render([
        'titulo' => 'Nueva conversación de chat',
        'parrafos' => [
            '<strong>' . htmlspecialchars($cliente, ENT_QUOTES, 'UTF-8') . '</strong> inició una conversación desde el panel del cliente.',
            'Conversación #' . $conversacionId,
        ],
        'cta_texto' => 'Abrir en bandeja',
        'cta_url' => 'https://adm.conlineweb.com/leads/inbox.php?tab=chat&id=' . $conversacionId,
    ]);
    cw_chat_send_email($conn, '[Chat] Nueva conversación — ' . $cliente, $html);
}

function cw_chat_notify_new_message(mysqli $conn, int $conversacionId, string $mensaje): void
{
    $conv = cw_chat_get_conversation($conn, $conversacionId);
    if (!$conv || empty($conv['cliente_id'])) {
        return;
    }
    $ctx = cw_chat_client_context($conn, (int) $conv['cliente_id']);
    $cliente = $ctx['cliente']['nombre_contacto'] ?? 'Cliente';
    $preview = htmlspecialchars(mb_substr($mensaje, 0, 300), ENT_QUOTES, 'UTF-8');
    $html = chat_email_render([
        'titulo' => 'Nuevo mensaje de chat',
        'parrafos' => [
            '<strong>' . htmlspecialchars($cliente, ENT_QUOTES, 'UTF-8') . '</strong> envió un mensaje:',
            '<em>' . $preview . '</em>',
        ],
        'cta_texto' => 'Responder en bandeja',
        'cta_url' => 'https://adm.conlineweb.com/leads/inbox.php?tab=chat&id=' . $conversacionId,
    ]);
    cw_chat_send_email($conn, '[Chat] Nuevo mensaje — ' . $cliente, $html);
}

/**
 * Correo de “hablar con un agente”. Siempre intenta enviar (aunque alertas generales estén off).
 * @return array{ok:bool,error?:string,skipped?:bool,recipients?:list<string>}
 */
function cw_chat_notify_escalation(mysqli $conn, int $conversacionId, string $motivo): array
{
    $conv = cw_chat_get_conversation($conn, $conversacionId);
    $cliente = 'Cliente';
    if ($conv && !empty($conv['cliente_id'])) {
        $ctx = cw_chat_client_context($conn, (int) $conv['cliente_id']);
        $cliente = $ctx['cliente']['nombre_contacto'] ?? $cliente;
    }
    $html = chat_email_render([
        'titulo' => 'Conversación escalada a humano',
        'parrafos' => [
            'La conversación de <strong>' . htmlspecialchars($cliente, ENT_QUOTES, 'UTF-8') . '</strong> requiere atención humana.',
            'Motivo: ' . htmlspecialchars($motivo, ENT_QUOTES, 'UTF-8'),
        ],
        'alerta_tipo' => 'warning',
        'alerta_texto' => 'Prioridad alta — responder lo antes posible.',
        'cta_texto' => 'Atender conversación',
        'cta_url' => 'https://adm.conlineweb.com/leads/inbox.php?tab=chat&id=' . $conversacionId,
    ]);
    // force=true: el pedido de agente no debe quedar bloqueado por “Alertas email = Desactivadas”.
    return cw_chat_send_email($conn, '[Chat] Escalamiento — ' . $cliente, $html, true);
}

/**
 * Envía el correo de escalamiento como máximo 1 vez cada $cooldownMin minutos (si el envío fue OK).
 * Si falló SMTP, el siguiente clic reintenta.
 * @return array{ok:bool,error?:string,skipped?:bool,recipients?:list<string>}
 */
function cw_chat_notify_escalation_once(mysqli $conn, int $conversacionId, string $motivo, int $cooldownMin = 10): array
{
    $conv = cw_chat_get_conversation($conn, $conversacionId);
    $meta = [];
    if ($conv && !empty($conv['metadata'])) {
        $decoded = json_decode((string) $conv['metadata'], true);
        if (is_array($decoded)) {
            $meta = $decoded;
        }
    }
    $last = isset($meta['escalation_email_at']) ? strtotime((string) $meta['escalation_email_at']) : false;
    if ($last && (time() - $last) < max(1, $cooldownMin) * 60) {
        return ['ok' => true, 'skipped' => true];
    }

    $result = cw_chat_notify_escalation($conn, $conversacionId, $motivo);
    if (!empty($result['ok'])) {
        $meta['escalation_email_at'] = date('Y-m-d H:i:s');
        $json = json_encode($meta, JSON_UNESCAPED_UNICODE);
        $upd = $conn->prepare('UPDATE cw_chat_conversaciones SET metadata = ? WHERE id = ?');
        if ($upd) {
            $upd->bind_param('si', $json, $conversacionId);
            $upd->execute();
            $upd->close();
        }
    } else {
        error_log('[cw_chat] escalate email FAIL conv#' . $conversacionId . ': ' . ($result['error'] ?? 'unknown'));
    }
    return $result;
}

function cw_chat_notify_assigned(mysqli $conn, int $conversacionId, int $agenteId): void
{
    $conv = cw_chat_get_conversation($conn, $conversacionId);
    if (!$conv) {
        return;
    }
    $html = chat_email_render([
        'titulo' => 'Conversación asignada',
        'parrafos' => [
            'Se te asignó la conversación #' . $conversacionId . '.',
        ],
        'cta_texto' => 'Ver conversación',
        'cta_url' => 'https://adm.conlineweb.com/leads/inbox.php?tab=chat&id=' . $conversacionId,
    ]);
    cw_chat_send_email($conn, '[Chat] Conversación asignada #' . $conversacionId, $html);
}

function cw_chat_notify_closed(mysqli $conn, int $conversacionId): void
{
    $html = chat_email_render([
        'titulo' => 'Conversación cerrada',
        'parrafos' => ['La conversación #' . $conversacionId . ' fue cerrada.'],
    ]);
    cw_chat_send_email($conn, '[Chat] Conversación cerrada #' . $conversacionId, $html);
}

function cw_chat_notify_unanswered(mysqli $conn, int $conversacionId): void
{
    $conv = cw_chat_get_conversation($conn, $conversacionId);
    if (!$conv || empty($conv['sin_respuesta_desde'])) {
        return;
    }
    $min = (int) cw_chat_config_get($conn, 'alerta_sin_respuesta_minutos', '30');
    $diff = time() - strtotime((string) $conv['sin_respuesta_desde']);
    if ($diff < $min * 60) {
        return;
    }
    $html = chat_email_render([
        'titulo' => 'Conversación sin respuesta',
        'parrafos' => [
            'La conversación #' . $conversacionId . ' lleva más de ' . $min . ' minutos sin respuesta del equipo.',
        ],
        'alerta_tipo' => 'warning',
        'alerta_texto' => 'El cliente está esperando respuesta.',
        'cta_texto' => 'Responder ahora',
        'cta_url' => 'https://adm.conlineweb.com/leads/inbox.php?tab=chat&id=' . $conversacionId,
    ]);
    cw_chat_send_email($conn, '[Chat] Sin respuesta #' . $conversacionId, $html);
}
