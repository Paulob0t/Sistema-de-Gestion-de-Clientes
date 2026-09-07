<?php
/**
 * Chatbot: consultar y crear tickets del cliente (solicitudes por id_cliente).
 */
require_once __DIR__ . '/cw_cliente_tickets_service.php';

function cw_chatbot_tickets_wants_list(string $mensaje): bool
{
    $m = mb_strtolower(trim($mensaje));
    if (!preg_match('/\b(ticket|tickets|solicitud|solicitudes)\b/u', $m)) {
        return false;
    }
    // No listar si está pidiendo crear o es solo howto
    if (cw_chatbot_tickets_wants_create($m)) {
        return false;
    }
    if (preg_match('/\b(c[oó]mo|pasos para|donde|d[oó]nde)\b/u', $m) && !preg_match('/\b(tengo|mis|cu[aá]ntos|estado|pendientes?)\b/u', $m)) {
        return false;
    }
    return (bool) preg_match(
        '/\b(mis tickets|mis solicitudes|tickets pendientes|solicitudes pendientes|cu[aá]ntos tickets|estado (de )?(mis )?tickets|ver (mis )?tickets|lista de tickets|tickets abiertos)\b/u',
        $m
    ) || (bool) preg_match('/\b(ticket|tickets)\b/u', $m)
        && (bool) preg_match('/\b(pendiente|pendientes|abiertos?|tengo|estado|seguimiento)\b/u', $m);
}

function cw_chatbot_tickets_wants_create(string $mensaje): bool
{
    $m = mb_strtolower(trim($mensaje));
    if (preg_match('/\b(c[oó]mo|pasos para|donde|d[oó]nde|explicame|explícame)\b/u', $m)
        && preg_match('/\b(ticket|solicitud)\b/u', $m)
        && !preg_match('/\b(quiero|necesito|genera|generar|abrir|abre|crea|crear|levantar|registrar)\b/u', $m)) {
        return false;
    }
    return (bool) preg_match(
        '/\b((quiero|necesito|vamos a|puedes|me puedes|deseo)\s+(abrir|crear|generar|levantar|registrar)\s+(un\s+)?(ticket|solicitud)'
        . '|(abrir|crear|generar|levantar|registrar)\s+(un\s+)?(ticket|solicitud|tickets)'
        . '|nuevo ticket|nueva solicitud|abrir ticket|crear ticket|generar ticket|generar solicitud)\b/u',
        $m
    );
}

function cw_chatbot_tickets_cancel_intent(string $mensaje): bool
{
    $m = mb_strtolower(trim($mensaje));
    return (bool) preg_match('/^(cancelar|cancela|olvidalo|olv[ií]dalo|no|salir|detener|ya no)([\s!,.]*)?$/u', $m)
        || (bool) preg_match('/\b(cancelar (el )?ticket|ya no (quiero|necesito)|olvidalo)\b/u', $m);
}

/**
 * @return array{matched:bool,respuesta?:string,intencion?:string,escalar?:bool,guardar_contexto?:array<string,mixed>,origen?:string}
 */
function cw_chatbot_tickets_build_list(mysqli $conn, int $clienteId, string $mensaje): array
{
    $m = mb_strtolower($mensaje);
    $filtro = 'abiertos';
    if (preg_match('/\bpendientes?\b/u', $m) && !preg_match('/\ben proceso|proceso\b/u', $m)) {
        $filtro = 'pendientes';
    } elseif (preg_match('/\bfinalizados?\b/u', $m)) {
        $filtro = 'finalizados';
    }

    $stats = cw_cliente_tickets_stats($conn, $clienteId);
    $rows = cw_cliente_tickets_list($conn, $clienteId, $filtro, 12);

    $lines = [
        'Revisé tus tickets en la cuenta:',
        "• **Pendientes:** {$stats['pendientes']}",
        "• **En proceso:** {$stats['en_proceso']}",
        "• **Finalizados:** {$stats['finalizados']}",
        "• **Abiertos (no finalizados):** {$stats['abiertos']}",
        '',
    ];

    if (!$rows) {
        if ($filtro === 'pendientes') {
            $lines[] = 'No tienes tickets en estado **Pendiente** ahora mismo.';
        } elseif ($filtro === 'finalizados') {
            $lines[] = 'No encontré tickets finalizados recientes.';
        } else {
            $lines[] = 'No tienes tickets abiertos. Si necesitas soporte, puedo **crear un ticket** desde este chat.';
        }
        $lines[] = "\nLo ideal: **registrar tu ticket** (botón abajo o «crear ticket» aquí). Si tienes dudas, usa WhatsApp.";
        return [
            'matched' => true,
            'respuesta' => implode("\n", $lines),
            'intencion' => 'tickets',
            'escalar' => false,
            'origen' => 'tickets',
            'guardar_contexto' => [
                'ultima_intencion' => 'tickets',
                'ultima_lista_tipo' => 'tickets',
                'ultima_lista_total' => 0,
                'ticket_draft' => null,
            ],
        ];
    }

    $label = $filtro === 'pendientes' ? 'pendientes' : ($filtro === 'finalizados' ? 'finalizados' : 'abiertos');
    $lines[] = 'Tus tickets **' . $label . '**:';
    foreach ($rows as $i => $t) {
        $n = $i + 1;
        $fecha = !empty($t['fecha_solicitud']) ? date('d/m/Y', strtotime((string) $t['fecha_solicitud'])) : '—';
        $lines[] = "{$n}. **#{$t['id']}** — {$t['titulo']}\n   Estado: **{$t['estado']}** · Prioridad: {$t['prioridad']} · {$fecha}";
    }
        $lines[] = "\nLo ideal: **registrar ticket** para seguimiento. Si tienes dudas, WhatsApp.\n¿Necesitas algo más? Si ya es todo, dime «gracias».";



    return [
        'matched' => true,
        'respuesta' => implode("\n", $lines),
        'intencion' => 'tickets',
        'escalar' => false,
        'origen' => 'tickets',
        'guardar_contexto' => [
            'ultima_intencion' => 'tickets',
            'ultima_lista_tipo' => 'tickets',
            'ultima_lista_total' => count($rows),
            'ticket_draft' => null,
        ],
    ];
}

/**
 * @param array<string,mixed> $context
 * @return array{matched:bool,respuesta?:string,intencion?:string,escalar?:bool,guardar_contexto?:array<string,mixed>,origen?:string}
 */
function cw_chatbot_tickets_start_create(array $context): array
{
    $draft = [
        'paso' => 'titulo',
        'titulo' => '',
        'descripcion' => '',
        'prioridad' => '',
    ];
    return [
        'matched' => true,
        'respuesta' => "Claro. Lo ideal es **registrar tu ticket** para dar seguimiento.\n\n"
            . "Puedes crearlo aquí en el chat, en **Mis Tickets**, o por WhatsApp si tienes dudas.\n\n"
            . "1/3 — ¿Cuál es el **asunto**? (mínimo 5 caracteres)\n"
            . "Escribe **cancelar** para salir.",
        'intencion' => 'crear_ticket',
        'escalar' => false,
        'origen' => 'tickets',
        'guardar_contexto' => array_merge($context, [
            'ultima_intencion' => 'crear_ticket',
            'ticket_draft' => $draft,
            'sin_match_consecutivos' => 0,
            'esperando_confirmacion' => false,
        ]),
    ];
}

/**
 * Continúa el flujo multi-paso de alta de ticket.
 *
 * @param array<string,mixed> $context
 * @return array{matched:bool,respuesta?:string,intencion?:string,escalar?:bool,guardar_contexto?:array<string,mixed>,origen?:string}
 */
function cw_chatbot_tickets_handle_draft(
    mysqli $conn,
    int $clienteId,
    string $mensajeUsuario,
    array $context
): array {
    $draft = $context['ticket_draft'] ?? null;
    if (!is_array($draft) || empty($draft['paso'])) {
        return ['matched' => false];
    }

    $raw = trim($mensajeUsuario);
    $m = mb_strtolower($raw);

    if (cw_chatbot_tickets_cancel_intent($m)) {
        $ctx = $context;
        $ctx['ticket_draft'] = null;
        $ctx['ultima_intencion'] = 'tickets';
        return [
            'matched' => true,
            'respuesta' => 'Listo, cancelé la creación del ticket. Si más adelante lo necesitas, escribe «crear ticket». ¿Te ayudo con otra cosa?',
            'intencion' => 'crear_ticket_cancelado',
            'escalar' => false,
            'origen' => 'tickets',
            'guardar_contexto' => $ctx,
        ];
    }

    $paso = (string) $draft['paso'];

    if ($paso === 'titulo') {
        if (mb_strlen($raw) < 5) {
            return [
                'matched' => true,
                'respuesta' => 'El asunto debe tener al menos **5 caracteres**. ¿Me lo das de nuevo? (o escribe **cancelar**)',
                'intencion' => 'crear_ticket',
                'escalar' => false,
                'origen' => 'tickets',
                'guardar_contexto' => $context,
            ];
        }
        $draft['titulo'] = $raw;
        $draft['paso'] = 'descripcion';
        $ctx = $context;
        $ctx['ticket_draft'] = $draft;
        return [
            'matched' => true,
            'respuesta' => "Perfecto. Asunto: **{$raw}**\n\n2/3 — Ahora describe el problema o lo que necesitas (mínimo **10 caracteres**).",
            'intencion' => 'crear_ticket',
            'escalar' => false,
            'origen' => 'tickets',
            'guardar_contexto' => $ctx,
        ];
    }

    if ($paso === 'descripcion') {
        if (mb_strlen($raw) < 10) {
            return [
                'matched' => true,
                'respuesta' => 'La descripción debe tener al menos **10 caracteres**. Cuéntame un poco más del caso.',
                'intencion' => 'crear_ticket',
                'escalar' => false,
                'origen' => 'tickets',
                'guardar_contexto' => $context,
            ];
        }
        $draft['descripcion'] = $raw;
        $draft['paso'] = 'prioridad';
        $ctx = $context;
        $ctx['ticket_draft'] = $draft;
        return [
            'matched' => true,
            'respuesta' => "Gracias.\n\n3/3 — ¿Qué **prioridad** le damos?\n• **Alta** — sitio caído / crítico\n• **Media** — normal\n• **Baja** — no urgente\n\nResponde con Alta, Media o Baja.",
            'intencion' => 'crear_ticket',
            'escalar' => false,
            'origen' => 'tickets',
            'guardar_contexto' => $ctx,
        ];
    }

    if ($paso === 'prioridad') {
        $prio = 'Media';
        if (preg_match('/\b(alta|urgente|high)\b/u', $m)) {
            $prio = 'Alta';
        } elseif (preg_match('/\b(baja|low)\b/u', $m)) {
            $prio = 'Baja';
        } elseif (preg_match('/\b(media|normal|medium)\b/u', $m)) {
            $prio = 'Media';
        } elseif (!preg_match('/^(alta|media|baja|urgente|normal)$/u', $m)) {
            return [
                'matched' => true,
                'respuesta' => 'Indica la prioridad con una palabra: **Alta**, **Media** o **Baja**.',
                'intencion' => 'crear_ticket',
                'escalar' => false,
                'origen' => 'tickets',
                'guardar_contexto' => $context,
            ];
        }

        $created = cw_cliente_ticket_create(
            $conn,
            $clienteId,
            (string) $draft['titulo'],
            (string) $draft['descripcion'],
            $prio,
            ['origen' => 'Chat Portal', 'nombre_msj' => 'Chat Portal']
        );

        $ctx = $context;
        $ctx['ticket_draft'] = null;
        $ctx['ultima_intencion'] = 'crear_ticket_ok';
        $ctx['ultimo_ticket_id'] = (int) ($created['ticket_id'] ?? 0);
        $ctx['ofrecio_mas_ayuda'] = true;
        $ctx['sin_match_consecutivos'] = 0;

        if (empty($created['ok'])) {
            return [
                'matched' => true,
                'respuesta' => 'No pude registrar el ticket: ' . ($created['message'] ?? 'error desconocido')
                    . "\n\nPuedes intentarlo de nuevo con «crear ticket» o desde **Mis Tickets**.",
                'intencion' => 'crear_ticket_error',
                'escalar' => false,
                'origen' => 'tickets',
                'guardar_contexto' => $ctx,
            ];
        }

        $msg = (string) ($created['message'] ?? cw_cliente_tickets_confirm_message(
            (int) $created['ticket_id'],
            (int) ($created['pending_ahead'] ?? 0)
        ));
        if (empty($created['email_sent'])) {
            $msg .= "\n\n_(No pude confirmar el envío del correo; el ticket sí quedó registrado.)_";
        }
        $msg .= "\n\n¿Necesitas algo más? Si ya es todo, dime «gracias» o «ya es todo» y me despido.";

        return [
            'matched' => true,
            'respuesta' => $msg,
            'intencion' => 'crear_ticket_ok',
            'escalar' => false,
            'origen' => 'tickets',
            'prioridad_alta' => ($prio === 'Alta'),
            'guardar_contexto' => $ctx,
        ];
    }

    return ['matched' => false];
}

/**
 * Entrada principal de tickets para el bot (listar o iniciar creación).
 *
 * @param array<string,mixed> $context
 * @return array{matched:bool,respuesta?:string,intencion?:string,escalar?:bool,guardar_contexto?:array<string,mixed>,origen?:string}
 */
function cw_chatbot_tickets_try(
    mysqli $conn,
    int $clienteId,
    string $mensajeUsuario,
    array $context
): array {
    if ($clienteId <= 0) {
        return ['matched' => false];
    }
    if (!empty($context['ticket_draft']) && is_array($context['ticket_draft'])) {
        return cw_chatbot_tickets_handle_draft($conn, $clienteId, $mensajeUsuario, $context);
    }
    $m = mb_strtolower(trim($mensajeUsuario));
    if (cw_chatbot_tickets_wants_create($m)) {
        return cw_chatbot_tickets_start_create($context);
    }
    if (cw_chatbot_tickets_wants_list($m)) {
        return cw_chatbot_tickets_build_list($conn, $clienteId, $m);
    }
    return ['matched' => false];
}
