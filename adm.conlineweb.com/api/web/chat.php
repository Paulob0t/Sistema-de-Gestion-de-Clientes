<?php
/**
 * Chat asistido web → conversación CRM (canal web) en vivo.
 * POST JSON actions: start | log | message | poll | history
 */
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cw_hub_api_fail('Método no permitido', 405);
}
cw_hub_validate_request();

require_once dirname(__DIR__, 2) . '/includes/cw_chat_migrate.php';
require_once dirname(__DIR__, 2) . '/includes/cw_chat_service.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_notify.php';
cw_chat_migrate($conn);

$data = cw_hub_json_input();
$action = preg_replace('/[^a-z_]/', '', strtolower((string) ($data['action'] ?? 'message'))) ?? 'message';

require_once dirname(__DIR__, 2) . '/includes/cw_web_chat_reply.php';

/** @return array<string,mixed>|null */
function cw_web_chat_resolve_conv(mysqli $conn, array $data): ?array
{
    $uuid = cw_hub_s($data['conversation_uuid'] ?? $data['uuid'] ?? '', 36);
    $convId = (int) ($data['conversation_id'] ?? 0);
    $sessionId = cw_hub_s($data['session_id'] ?? '', 64);

    if ($uuid !== '') {
        $conv = cw_chat_get_conversation_by_uuid($conn, $uuid);
        if ($conv && (string) ($conv['canal'] ?? '') === 'web') {
            return $conv;
        }
    }
    if ($convId > 0) {
        $conv = cw_chat_get_conversation($conn, $convId);
        if ($conv && (string) ($conv['canal'] ?? '') === 'web') {
            return $conv;
        }
    }
    if ($sessionId !== '') {
        return cw_chat_find_web_by_session($conn, $sessionId);
    }
    return null;
}

/** @return list<array<string,mixed>> */
function cw_web_chat_public_messages(mysqli $conn, int $convId, ?int $afterId = null): array
{
    $rows = cw_chat_get_messages($conn, $convId, $afterId, 200);
    $out = [];
    foreach ($rows as $row) {
        $tipo = (string) ($row['remitente_tipo'] ?? '');
        $role = 'system';
        if ($tipo === 'cliente') {
            $role = 'user';
        } elseif ($tipo === 'bot' || $tipo === 'agente') {
            $role = $tipo === 'agente' ? 'agent' : 'bot';
        }
        $out[] = [
            'id' => (int) ($row['id'] ?? 0),
            'role' => $role,
            'remitente_tipo' => $tipo,
            'contenido' => (string) ($row['contenido'] ?? ''),
            'enviado_at' => (string) ($row['enviado_at'] ?? ''),
        ];
    }
    return $out;
}

// ── start: crear/recuperar conversación + alerta ingreso ──
if ($action === 'start') {
    $sessionId = cw_hub_s($data['session_id'] ?? '', 64);
    $servicio = cw_hub_s($data['servicio'] ?? 'otro', 100);
    $pagina = cw_hub_s($data['pagina_origen'] ?? '', 2000);
    $interes = cw_hub_s($data['interes'] ?? $data['asunto'] ?? '', 200);
    $uuidHint = cw_hub_s($data['conversation_uuid'] ?? '', 36);

    if ($sessionId === '') {
        cw_hub_api_fail('session_id requerido');
    }

    try {
        $created = cw_chat_get_or_create_web_session_conversation($conn, $sessionId, [
            'servicio' => $servicio,
            'pagina_origen' => $pagina,
            'interes' => $interes,
            'conversation_uuid' => $uuidHint,
        ]);
    } catch (Throwable $e) {
        error_log('chat.php start: ' . $e->getMessage());
        cw_hub_api_fail('No se pudo iniciar el chat', 500);
    }

    $conv = $created['conversation'];
    $convId = (int) ($conv['id'] ?? 0);
    $isNew = !empty($created['created']);

    // Web: IA/bot activos por defecto. Solo queda en humano si un asesor ya tomó control.
    $estadoStart = (string) ($conv['estado'] ?? 'bot');
    $responsableId = (int) ($conv['responsable_id'] ?? 0);
    if (!$isNew && $convId > 0 && $estadoStart === 'humano' && $responsableId <= 0) {
        if (function_exists('cw_chat_set_bot_enabled')) {
            try {
                cw_chat_set_bot_enabled($conn, $convId, true, null);
                $conv = cw_chat_get_conversation($conn, $convId) ?: $conv;
                $estadoStart = (string) ($conv['estado'] ?? 'bot');
            } catch (Throwable $e) {
                error_log('chat.php start re-enable bot: ' . $e->getMessage());
            }
        }
    }

    if ($isNew) {
        try {
            cw_hub_notify_web_chat_started($conn, $convId, [
                'pagina_origen' => $pagina,
                'interes' => $interes,
                'servicio' => $servicio,
                'session_id' => $sessionId,
            ]);
        } catch (Throwable $e) {
            error_log('chat.php start notify failed #' . $convId . ': ' . $e->getMessage());
        }
    }

    $messages = cw_web_chat_public_messages($conn, $convId);
    $lastId = 0;
    foreach ($messages as $m) {
        if ((int) $m['id'] > $lastId) {
            $lastId = (int) $m['id'];
        }
    }

    $aiAvailable = false;
    try {
        require_once dirname(__DIR__, 2) . '/includes/cw_seo_mexico_ai.php';
        $aiAvailable = function_exists('cw_seo_mexico_ai_available') && cw_seo_mexico_ai_available();
    } catch (Throwable $e) {
        $aiAvailable = false;
    }

    echo json_encode([
        'success' => true,
        'created' => $isNew,
        'conversation_id' => $convId,
        'conversation_uuid' => (string) ($conv['uuid'] ?? ''),
        'estado' => $estadoStart,
        'bot_activo' => function_exists('cw_chat_bot_activo') ? cw_chat_bot_activo($estadoStart) : ($estadoStart !== 'humano' && $estadoStart !== 'cerrada'),
        'ai_available' => $aiAvailable,
        'lead_id' => (int) ($conv['lead_id'] ?? 0),
        'inbox_url' => cw_hub_admin_chat_inbox_url($convId),
        'messages' => $messages,
        'last_message_id' => $lastId,
    ], JSON_UNESCAPED_UNICODE);
    cw_hub_api_exit();
}

// ── history / poll ──
if ($action === 'history' || $action === 'poll') {
    $conv = cw_web_chat_resolve_conv($conn, $data);
    if (!$conv) {
        cw_hub_api_fail('Conversación no encontrada', 404);
    }
    $convId = (int) $conv['id'];
    $afterId = (int) ($data['after_id'] ?? 0);
    $messages = cw_web_chat_public_messages($conn, $convId, $afterId > 0 ? $afterId : null);
    $lastId = $afterId;
    foreach ($messages as $m) {
        if ((int) $m['id'] > $lastId) {
            $lastId = (int) $m['id'];
        }
    }
    echo json_encode([
        'success' => true,
        'conversation_id' => $convId,
        'conversation_uuid' => (string) ($conv['uuid'] ?? ''),
        'estado' => (string) ($conv['estado'] ?? 'bot'),
        'lead_id' => (int) ($conv['lead_id'] ?? 0),
        'messages' => $messages,
        'last_message_id' => $lastId,
        'inbox_url' => cw_hub_admin_chat_inbox_url($convId),
    ], JSON_UNESCAPED_UNICODE);
    cw_hub_api_exit();
}

// ── log: sincronizar mensaje local (sin auto-respuesta) ──
if ($action === 'log') {
    $conv = cw_web_chat_resolve_conv($conn, $data);
    if (!$conv) {
        cw_hub_api_fail('Conversación no encontrada', 404);
    }
    $convId = (int) $conv['id'];
    $mensaje = cw_hub_s($data['mensaje'] ?? $data['message'] ?? '', 2000);
    $role = strtolower(cw_hub_s($data['role'] ?? 'user', 20));
    if (mb_strlen($mensaje) < 1) {
        cw_hub_api_fail('Mensaje vacío');
    }

    $map = [
        'user' => 'cliente',
        'cliente' => 'cliente',
        'bot' => 'bot',
        'assistant' => 'bot',
        'agent' => 'agente',
        'agente' => 'agente',
        'system' => 'sistema',
        'sistema' => 'sistema',
    ];
    $remitente = $map[$role] ?? 'cliente';
    $tipoMsg = $remitente === 'sistema' ? 'sistema' : 'texto';

    $msgId = cw_chat_add_message($conn, $convId, $remitente, null, $mensaje, $tipoMsg, [
        'origen' => 'web_chat_sync',
        'role' => $role,
    ]);

    echo json_encode([
        'success' => true,
        'message_id' => $msgId,
        'conversation_id' => $convId,
        'conversation_uuid' => (string) ($conv['uuid'] ?? ''),
        'estado' => (string) ($conv['estado'] ?? 'bot'),
    ], JSON_UNESCAPED_UNICODE);
    cw_hub_api_exit();
}

// ── message: mensaje del visitante (+ bot si no hay agente) ──
if ($action === 'message') {
    $conv = cw_web_chat_resolve_conv($conn, $data);
    if (!$conv) {
        cw_hub_api_fail('Conversación no encontrada', 404);
    }
    $convId = (int) $conv['id'];
    $mensaje = cw_hub_s($data['mensaje'] ?? $data['message'] ?? '', 2000);
    $servicio = cw_hub_s($data['servicio'] ?? 'otro', 100);
    $pagina = cw_hub_s($data['pagina_origen'] ?? '', 2000);
    $interes = cw_hub_s($data['interes'] ?? $data['asunto'] ?? '', 200);
    $citaConfirmada = !empty($data['cita_confirmada']);
    $adviceMode = !empty($data['advice_mode']);
    $visitorNombre = cw_hub_s($data['visitor_nombre'] ?? '', 120);
    $visitorTelefono = cw_hub_s($data['visitor_telefono'] ?? '', 20);
    $registered = !empty($data['registered']);
    $registrationPending = !empty($data['registration_pending']);
    $registrationStep = cw_hub_s($data['registration_step'] ?? '', 20);
    $businessHoursOpen = !isset($data['business_hours_open']) || !empty($data['business_hours_open']);
    $businessHoursNote = cw_hub_s($data['business_hours_note'] ?? '', 300);
    $locale = strtolower(cw_hub_s($data['locale'] ?? 'es', 8));
    if ($locale !== 'en') {
        $locale = 'es';
    }
    if ($registered) {
        $adviceMode = true;
    }

    if (mb_strlen($mensaje) < 1) {
        cw_hub_api_fail('Escribe un mensaje');
    }

    $skipReply = !empty($data['skip_reply']) || str_starts_with($mensaje, '[Sistema]');
    $remitente = $skipReply ? 'sistema' : 'cliente';

    $userMsgId = cw_chat_add_message($conn, $convId, $remitente, null, $mensaje, $skipReply ? 'sistema' : 'texto', [
        'origen' => 'web_chat',
        'pagina' => $pagina,
    ]);

    // Recargar estado (agente pudo tomar control)
    $conv = cw_chat_get_conversation($conn, $convId) ?: $conv;
    $estado = (string) ($conv['estado'] ?? 'bot');
    $botActivo = function_exists('cw_chat_bot_activo') ? cw_chat_bot_activo($estado) : !in_array($estado, ['humano', 'cerrada'], true);

    $meta = [];
    if (!empty($conv['metadata'])) {
        $decoded = json_decode((string) $conv['metadata'], true);
        if (is_array($decoded)) {
            $meta = $decoded;
        }
    }
    if ($servicio === 'otro' && !empty($meta['servicio'])) {
        $servicio = (string) $meta['servicio'];
    }
    if ($interes === '' && !empty($meta['interes'])) {
        $interes = cw_hub_s((string) $meta['interes'], 200);
    } elseif ($interes === '' && !empty($meta['mensaje_inicial'])) {
        $interes = cw_hub_s((string) $meta['mensaje_inicial'], 200);
    }

    $reply = '';
    $replySource = 'none';
    $aiAvailable = false;
    $botMsgId = 0;

    if (!$skipReply && $botActivo) {
        require_once dirname(__DIR__, 2) . '/includes/cw_web_chat_ai.php';
        require_once dirname(__DIR__, 2) . '/includes/cw_seo_mexico_ai.php';
        $aiAvailable = function_exists('cw_seo_mexico_ai_available') && cw_seo_mexico_ai_available();

        $out = ['reply' => '', 'intent' => 'general'];
        $histRows = cw_chat_get_messages_recent($conn, $convId, 12);
        $history = [];
        foreach ($histRows as $hr) {
            $tipo = (string) ($hr['remitente_tipo'] ?? '');
            $content = trim((string) ($hr['contenido'] ?? ''));
            if ($content === '' || $tipo === 'sistema') {
                continue;
            }
            $history[] = [
                'role' => $tipo === 'cliente' ? 'user' : 'assistant',
                'content' => $content,
            ];
        }
        if ($history !== []) {
            $last = $history[count($history) - 1];
            if (($last['role'] ?? '') === 'user' && ($last['content'] ?? '') === $mensaje) {
                array_pop($history);
            }
        }

        // 1) IA primero si hay clave; 2) reglas solo si no hay conexión / falla la IA
        if ($aiAvailable) {
            try {
                $aiOut = cw_web_chat_ai_try(
                    $conn,
                    $mensaje,
                    $servicio,
                    $pagina,
                    $interes,
                    $citaConfirmada,
                    $adviceMode || $registered,
                    $history,
                    $visitorNombre,
                    $visitorTelefono,
                    $registered,
                    $businessHoursOpen,
                    $businessHoursNote,
                    $registrationPending,
                    $registrationStep,
                    $locale
                );
                if (is_array($aiOut) && trim((string) ($aiOut['reply'] ?? '')) !== '') {
                    $reply = (string) $aiOut['reply'];
                    $replySource = 'ai';
                    $out['intent'] = (string) ($aiOut['intent'] ?? 'ai');
                }
            } catch (Throwable $e) {
                error_log('chat.php hybrid AI: ' . $e->getMessage());
            }
        }

        if ($reply === '') {
            $out = cw_web_chat_reply(
                $mensaje,
                $servicio,
                $pagina,
                $interes,
                $citaConfirmada,
                $adviceMode || $registered,
                $visitorNombre,
                $registered,
                $businessHoursOpen,
                $businessHoursNote,
                $registrationPending,
                $registrationStep,
                $locale
            );
            $reply = (string) ($out['reply'] ?? '');
            $replySource = $aiAvailable ? 'rules_fallback' : 'rules';
        }

        if ($reply !== '') {
            $botMsgId = cw_chat_add_message($conn, $convId, 'bot', null, $reply, 'texto', [
                'origen' => $replySource === 'ai' ? 'web_assist_ai' : 'web_assist',
                'intent' => $out['intent'] ?? 'general',
                'source' => $replySource,
            ]);
        }
    }

    $lastMessageId = max((int) $userMsgId, (int) $botMsgId);

    echo json_encode([
        'success' => true,
        'reply' => $reply,
        'reply_source' => $replySource,
        'ai_available' => $aiAvailable,
        'bot_activo' => $botActivo,
        'estado' => $estado,
        'conversation_id' => $convId,
        'conversation_uuid' => (string) ($conv['uuid'] ?? ''),
        'lead_id' => (int) ($conv['lead_id'] ?? 0),
        'inbox_url' => cw_hub_admin_chat_inbox_url($convId),
        'message_id' => (int) $userMsgId,
        'bot_message_id' => (int) $botMsgId,
        'last_message_id' => $lastMessageId,
    ], JSON_UNESCAPED_UNICODE);
    cw_hub_api_exit();
}

cw_hub_api_fail('Acción no soportada', 400);
