<?php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/includes/cliente_session.php';
cliente_start_session();

if (!cliente_is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

require_once dirname(__DIR__, 3) . '/adm.conlineweb.com/conn.php';
require_once dirname(__DIR__, 3) . '/adm.conlineweb.com/includes/cw_chat_service.php';
require_once dirname(__DIR__, 3) . '/adm.conlineweb.com/includes/cw_chatbot_service.php';
require_once dirname(__DIR__, 3) . '/adm.conlineweb.com/includes/cw_chat_alerts.php';

$clienteId = (int) $_SESSION['uid'];
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
$uuid = trim((string) ($_POST['uuid'] ?? ''));
$mensaje = trim((string) ($_POST['mensaje'] ?? ''));
$clientToken = trim((string) ($_POST['client_token'] ?? ''));
if ($clientToken !== '' && !preg_match('/^[a-zA-Z0-9_-]{8,64}$/', $clientToken)) {
    $clientToken = '';
}

if ($uuid === '' || $mensaje === '') {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

if (mb_strlen($mensaje) > 4000) {
    echo json_encode(['success' => false, 'message' => 'Mensaje demasiado largo']);
    exit;
}

$lockName = null;

try {
    cw_chat_migrate($conn);
    $conv = cw_chat_get_conversation_by_uuid($conn, $uuid);
    if (!$conv || (int) ($conv['cliente_id'] ?? 0) !== $clienteId) {
        echo json_encode(['success' => false, 'message' => 'Conversación no encontrada']);
        exit;
    }

    $convId = (int) $conv['id'];
    $estado = (string) ($conv['estado'] ?? 'bot');

    if ($estado === 'cerrada') {
        echo json_encode(['success' => false, 'message' => 'Esta conversación está cerrada. Inicia un nuevo chat.']);
        exit;
    }

    // Candado para evitar doble INSERT por carrera (doble clic / 2 pestañas).
    $lockName = 'cw_send_' . $convId . '_' . substr(md5($clienteId . '|' . $mensaje . '|' . $clientToken), 0, 16);
    $lockStmt = $conn->prepare('SELECT GET_LOCK(?, 8) AS got');
    $gotLock = 0;
    if ($lockStmt) {
        $lockStmt->bind_param('s', $lockName);
        $lockStmt->execute();
        $lockRow = $lockStmt->get_result()->fetch_assoc();
        $gotLock = (int) ($lockRow['got'] ?? 0);
        $lockStmt->close();
    }

    $botMessages = [];
    $duplicado = false;
    $msgId = 0;

    $dup = cw_chat_find_recent_cliente_duplicate($conn, $convId, $clienteId, $mensaje, $clientToken, 30);
    if ($dup) {
        $msgId = (int) $dup['id'];
        $duplicado = true;
        $after = cw_chat_get_messages($conn, $convId, $msgId);
        foreach ($after as $nm) {
            $tipo = $nm['remitente_tipo'] ?? '';
            if ($tipo === 'bot' || $tipo === 'sistema') {
                $botMessages[] = $nm;
            }
        }
        // Si el mensaje quedó sin respuesta del bot y el bot está activo, responder ahora.
        if ($botMessages === [] && cw_chat_bot_activo($estado)) {
            $context = cw_chatbot_recent_user_messages($conn, $convId);
            $botResult = cw_chatbot_respond($conn, $convId, $mensaje, $context, $clienteId);
            if (!empty($botResult['matched']) && !empty($botResult['respuesta'])) {
                $botMeta = [
                    'intencion' => $botResult['intencion'] ?? null,
                    'conocimiento_id' => $botResult['conocimiento_id'] ?? null,
                    'origen' => $botResult['origen'] ?? 'base_conocimiento',
                    'client_token' => $clientToken !== '' ? $clientToken : null,
                ];
                if (!empty($botResult['acciones']) && is_array($botResult['acciones'])) {
                    $botMeta['acciones'] = $botResult['acciones'];
                }
                $botId = cw_chat_add_message($conn, $convId, 'bot', null, $botResult['respuesta'], 'texto', $botMeta);
                if ($botId > 0) {
                    $botMessages[] = cw_chat_format_message_row([
                        'id' => $botId,
                        'conversacion_id' => $convId,
                        'remitente_tipo' => 'bot',
                        'remitente_id' => null,
                        'contenido' => $botResult['respuesta'],
                        'tipo' => 'texto',
                        'metadata' => json_encode($botMeta, JSON_UNESCAPED_UNICODE),
                        'enviado_at' => cw_chat_now(),
                        'leido_at' => null,
                    ]);
                }
            }
        }
    } else {
        $meta = $clientToken !== '' ? ['client_token' => $clientToken] : null;
        $msgId = cw_chat_add_message($conn, $convId, 'cliente', $clienteId, $mensaje, 'texto', $meta);
        if ($msgId <= 0) {
            throw new RuntimeException('No se pudo guardar el mensaje');
        }
        cw_chat_alert_schedule($conn, $convId, $msgId);

        if (cw_chat_bot_activo($estado)) {
            // Si quedó en pendiente_humano por escalamiento viejo, volver a modo bot
            // (solo staff con «Tomar control» deja estado=humano).
            if ($estado === 'pendiente_humano' || $estado === 'activa') {
                $fix = $conn->prepare("UPDATE cw_chat_conversaciones SET estado = 'bot' WHERE id = ? AND estado IN ('pendiente_humano','activa')");
                if ($fix) {
                    $fix->bind_param('i', $convId);
                    $fix->execute();
                    $fix->close();
                }
            }

            $context = cw_chatbot_recent_user_messages($conn, $convId);
            $botResult = cw_chatbot_respond($conn, $convId, $mensaje, $context, $clienteId);

            if (!empty($botResult['matched']) && !empty($botResult['respuesta'])) {
                $botMeta = [
                    'intencion' => $botResult['intencion'] ?? null,
                    'conocimiento_id' => $botResult['conocimiento_id'] ?? null,
                    'origen' => $botResult['origen'] ?? 'base_conocimiento',
                ];
                if (!empty($botResult['acciones']) && is_array($botResult['acciones'])) {
                    $botMeta['acciones'] = $botResult['acciones'];
                }
                $botId = cw_chat_add_message($conn, $convId, 'bot', null, $botResult['respuesta'], 'texto', $botMeta);
                if ($botId > 0) {
                    $botMessages[] = cw_chat_format_message_row([
                        'id' => $botId,
                        'conversacion_id' => $convId,
                        'remitente_tipo' => 'bot',
                        'remitente_id' => null,
                        'contenido' => $botResult['respuesta'],
                        'tipo' => 'texto',
                        'metadata' => json_encode($botMeta, JSON_UNESCAPED_UNICODE),
                        'enviado_at' => cw_chat_now(),
                        'leido_at' => null,
                    ]);
                }
            } elseif (empty($botResult['matched'])) {
                // Garantía: si el motor no matcheó, al menos avisar (bot no se queda mudo).
                $fallback = "No pude resolver eso con certeza desde aquí.\n\nPuedes conversar con un agente. ¿Te gustaría conversar con uno o no?";
                $fallbackMeta = [
                    'intencion' => 'fallback',
                    'origen' => 'fallback',
                    'acciones' => function_exists('cw_chatbot_ticket_wa_actions')
                        ? cw_chatbot_ticket_wa_actions($conn)
                        : [],
                ];
                $botId = cw_chat_add_message($conn, $convId, 'bot', null, $fallback, 'texto', $fallbackMeta);
                if ($botId > 0) {
                    $botMessages[] = cw_chat_format_message_row([
                        'id' => $botId,
                        'conversacion_id' => $convId,
                        'remitente_tipo' => 'bot',
                        'remitente_id' => null,
                        'contenido' => $fallback,
                        'tipo' => 'texto',
                        'metadata' => json_encode($fallbackMeta, JSON_UNESCAPED_UNICODE),
                        'enviado_at' => cw_chat_now(),
                        'leido_at' => null,
                    ]);
                }
            }

            // Pedido de agente: campanita + correo (reintenta si SMTP falló).
            $intencion = (string) ($botResult['intencion'] ?? '');
            $wantsAgent = $intencion === 'contacto_humano'
                || (!empty($botResult['prioridad_alta']) && $intencion === 'contacto_humano')
                || (function_exists('cw_chatbot_wants_human') && cw_chatbot_wants_human($mensaje));

            if ($wantsAgent) {
                cw_chat_flag_needs_human($conn, $convId, $intencion !== '' ? $intencion : 'contacto_humano');
                if (!function_exists('cw_chat_notify_escalation_once')) {
                    require_once dirname(__DIR__, 3) . '/adm.conlineweb.com/includes/cw_chat_notify.php';
                }
                cw_chat_notify_escalation_once(
                    $conn,
                    $convId,
                    'El cliente pidió hablar con un agente desde el chat en vivo'
                );
            } elseif (!empty($botResult['prioridad_alta'])) {
                cw_chat_flag_needs_human($conn, $convId, $intencion !== '' ? $intencion : 'prioridad_alta');
            }
        }
    }

    if ($gotLock && $lockName) {
        $rel = $conn->prepare('SELECT RELEASE_LOCK(?)');
        if ($rel) {
            $rel->bind_param('s', $lockName);
            $rel->execute();
            $rel->close();
        }
        $lockName = null;
    }

    $updatedConv = cw_chat_get_conversation($conn, $convId);

    echo json_encode([
        'success' => true,
        'message_id' => $msgId,
        'duplicado' => $duplicado,
        'conversation' => [
            'id' => $convId,
            'uuid' => $updatedConv['uuid'] ?? $uuid,
            'estado' => $updatedConv['estado'] ?? $estado,
            'estado_label' => cw_chat_estado_label($updatedConv['estado'] ?? $estado),
            'bot_activo' => cw_chat_bot_activo($updatedConv['estado'] ?? $estado),
        ],
        'bot_messages' => $botMessages,
        'escalated' => false,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($lockName) {
        $rel = $conn->prepare('SELECT RELEASE_LOCK(?)');
        if ($rel) {
            $rel->bind_param('s', $lockName);
            $rel->execute();
            $rel->close();
        }
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al enviar mensaje']);
}
