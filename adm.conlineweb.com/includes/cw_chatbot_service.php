<?php
/**
 * Motor del chatbot inteligente — base de conocimiento, contexto e intención.
 * Solo responde con entradas de cw_chat_conocimiento; nunca inventa información.
 */
require_once __DIR__ . '/cw_chat_service.php';
require_once __DIR__ . '/cw_chatbot_live_data.php';
require_once __DIR__ . '/cw_chatbot_tickets.php';

/** @return array<string,array<int,string>> */
function cw_chatbot_synonyms(): array
{
    return [
        'pago' => ['pagar', 'pagos', 'pague', 'cobro', 'cobrar', 'factura', 'facturacion', 'facturación', 'debo', 'adeudo', 'adeudos', 'comprobante', 'recibo', 'liquidar', 'saldar', 'stripe', 'tarjeta', 'cargo', 'cuanto debo', 'cuánto debo'],
        'hosting' => ['alojamiento', 'servidor', 'host', 'espacio', 'disco', 'cpanel', 'whm', 'ftp', 'web hosting', 'plan', 'planes', 'hospedaje', 'mi sitio', 'mi pagina', 'mi página', 'sitio web', 'pagina web', 'página web', 'sitio caido', 'sitio caído', 'no carga', 'error 500'],
        'dominio' => ['dominios', 'dns', 'nameserver', 'nameservers', 'ns1', 'ns2', 'transferencia', 'epp', 'auth code', 'apuntar', 'zone editor', 'registro dns', 'whois'],
        'ticket' => ['tickets', 'soporte', 'solicitud', 'solicitudes', 'caso', 'reporte', 'incidencia', 'reclamo', 'abrir ticket', 'crear ticket', 'nuevo ticket', 'mis tickets'],
        'correo' => ['email', 'e-mail', 'imap', 'smtp', 'outlook', 'thunderbird', 'buzon', 'buzón', 'mail', 'correos'],
        'ssl' => ['https', 'certificado', 'candado', 'inseguro', 'no seguro', 'lets encrypt'],
        'wordpress' => ['wordpress', 'wp', 'cms'],
        'renovar' => ['renovacion', 'renovación', 'vence', 'vencimiento', 'vencimientos', 'caduca', 'vigencia', 'cuando vence', 'cuándo vence', 'dias restantes', 'días restantes'],
        'cuenta' => ['panel', 'dashboard', 'portal', 'acceso', 'login', 'ingresar', 'contraseña', 'password', 'usuario', 'sesion', 'sesión', 'mi cuenta'],
        'asesor' => ['agente', 'humano', 'persona', 'atencion humana', 'atención humana', 'ejecutivo', 'hablar con alguien'],
        'whatsapp' => ['wa', 'wsp', 'wasap', 'whats app'],
        'horario' => ['horarios', 'atienden', 'atencion', 'atención', 'abierto', 'disponible'],
        'contacto' => ['telefono', 'teléfono', 'llamar', 'comunicar'],
    ];
}

function cw_chatbot_normalize_text(string $text): string
{
    $t = mb_strtolower(trim($text));
    return strtr($t, [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n',
    ]);
}

function cw_chatbot_scope_hint(): string
{
    return "Puedo ayudarte con:\n"
        . "• **Hosting** y **cPanel**\n"
        . "• **Dominios** y **DNS**\n"
        . "• **Pagos** y renovaciones\n"
        . "• **Tickets** de soporte\n"
        . "• Tu **cuenta** en el panel\n\n"
        . "En cualquier momento puedes pedir **hablar con un agente**.";
}

function cw_chatbot_is_out_of_scope(string $mensaje): bool
{
    $m = cw_chatbot_normalize_text(cw_chatbot_expand_message($mensaje));
    $out = ['seo', 'posicionamiento', 'marketing', 'publicidad', 'redes sociales', 'ads', 'campana', 'desarrollo web a medida', 'diseno grafico', 'landing page', 'programar app', 'cotizacion de proyecto'];
    $in = ['hosting', 'cpanel', 'dominio', 'dns', 'pago', 'ticket', 'correo', 'ssl', 'cuenta', 'portal', 'contrasena', 'vencimiento', 'renovar', 'sitio', 'servidor', 'factura'];
    $hasOut = false;
    foreach ($out as $kw) {
        if (mb_strpos($m, cw_chatbot_normalize_text($kw)) !== false) {
            $hasOut = true;
            break;
        }
    }
    if (!$hasOut) {
        return false;
    }
    foreach ($in as $kw) {
        if (mb_strpos($m, $kw) !== false) {
            return false;
        }
    }
    return true;
}

/** @return array<string,mixed> */
function cw_chatbot_context_get(mysqli $conn, int $conversacionId): array
{
    $conv = cw_chat_get_conversation($conn, $conversacionId);
    if (!$conv || empty($conv['metadata'])) {
        return [];
    }
    $meta = json_decode((string) $conv['metadata'], true);
    return is_array($meta['bot_context'] ?? null) ? $meta['bot_context'] : [];
}

function cw_chatbot_context_save(mysqli $conn, int $conversacionId, array $context): void
{
    $conv = cw_chat_get_conversation($conn, $conversacionId);
    $meta = [];
    if ($conv && !empty($conv['metadata'])) {
        $decoded = json_decode((string) $conv['metadata'], true);
        if (is_array($decoded)) {
            $meta = $decoded;
        }
    }
    $meta['bot_context'] = $context;
    $json = json_encode($meta, JSON_UNESCAPED_UNICODE);
    $stmt = $conn->prepare('UPDATE cw_chat_conversaciones SET metadata = ? WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('si', $json, $conversacionId);
        $stmt->execute();
        $stmt->close();
    }
}

function cw_chatbot_text_has_phrase(string $haystack, string $needle): bool
{
    $needle = trim($needle);
    if ($needle === '') {
        return false;
    }
    // Evitar que «panel» coincida dentro de «cpanel», etc.
    $pattern = '/(?:^|[^\p{L}\p{N}])' . preg_quote($needle, '/') . '(?:[^\p{L}\p{N}]|$)/u';
    return (bool) preg_match($pattern, $haystack);
}

function cw_chatbot_expand_message(string $mensaje): string
{
    $expanded = mb_strtolower(trim($mensaje));
    $norm = cw_chatbot_normalize_text($mensaje);
    foreach (cw_chatbot_synonyms() as $canonical => $syns) {
        $hit = cw_chatbot_text_has_phrase($expanded, $canonical)
            || cw_chatbot_text_has_phrase($norm, cw_chatbot_normalize_text($canonical));
        if (!$hit) {
            foreach ($syns as $syn) {
                $synL = mb_strtolower($syn);
                if (cw_chatbot_text_has_phrase($expanded, $synL) || cw_chatbot_text_has_phrase($norm, cw_chatbot_normalize_text($synL))) {
                    $hit = true;
                    break;
                }
            }
        }
        if (!$hit) {
            continue;
        }
        if (!cw_chatbot_text_has_phrase($expanded, $canonical)) {
            $expanded .= ' ' . $canonical;
        }
        foreach (array_slice($syns, 0, 8) as $syn) {
            $synL = mb_strtolower($syn);
            if (!cw_chatbot_text_has_phrase($expanded, $synL)) {
                $expanded .= ' ' . $synL;
            }
        }
    }
    return $expanded;
}

/** @return array<int,string> */
function cw_chatbot_tokenize(string $text): array
{
    $text = mb_strtolower($text);
    $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
    $parts = preg_split('/\s+/u', trim($text));
    $stop = ['el', 'la', 'los', 'las', 'de', 'del', 'un', 'una', 'y', 'o', 'en', 'mi', 'me', 'que', 'como', 'por', 'para', 'con', 'es', 'a', 'se', 'lo', 'le', 'al', 'su', 'ya', 'si', 'sí', 'no', 'muy', 'mas', 'más'];
    $out = [];
    foreach ($parts as $p) {
        if (mb_strlen($p) < 2 || in_array($p, $stop, true)) {
            continue;
        }
        $out[] = $p;
    }
    return array_values(array_unique($out));
}

function cw_chatbot_is_explicit_followup(string $mensaje, array $context): bool
{
    if ($context === []) {
        return false;
    }
    $m = mb_strtolower(trim($mensaje));
    if (mb_strlen($m) > 120) {
        return false;
    }
    $patterns = [
        '/\b(ese|esa|eso|este|esta|esto|aquel|aquella|el mismo|la misma)\b/u',
        '/\b(segundo|segunda|tercer|tercera|cuarto|cuarta|primero|primera|ultimo|último)\b/u',
        '/\b(opci[oó]n|plan|paquete)\s*\d+/u',
        '/\b(y eso|y ese|y esa|el primero|el segundo|el tercero)\b/u',
        '/^\s*(si|sí|ok|vale|claro|exacto|correcto|adelante)\s*[\?\!\.]*\s*$/u',
    ];
    foreach ($patterns as $p) {
        if (preg_match($p, $m)) {
            return true;
        }
    }
    return false;
}

function cw_chatbot_is_followup(string $mensaje, array $context): bool
{
    return cw_chatbot_is_explicit_followup($mensaje, $context);
}

function cw_chatbot_extract_ordinal(string $mensaje): ?int
{
    $map = [
        1 => ['primero', 'primera', '1er', '1ra', 'uno', 'una', 'opcion 1', 'opción 1', 'numero 1', 'número 1'],
        2 => ['segundo', 'segunda', '2do', '2da', 'dos', 'opcion 2', 'opción 2', 'numero 2', 'número 2'],
        3 => ['tercer', 'tercera', 'tercero', '3er', '3ra', 'tres', 'opcion 3', 'opción 3'],
        4 => ['cuarto', 'cuarta', 'cuatro', 'opcion 4', 'opción 4'],
    ];
    $m = mb_strtolower($mensaje);
    foreach ($map as $num => $words) {
        foreach ($words as $w) {
            if (mb_strpos($m, $w) !== false) {
                return $num;
            }
        }
    }
    if (preg_match('/\b(\d+)\b/u', $m, $match)) {
        $n = (int) $match[1];
        return ($n >= 1 && $n <= 10) ? $n : null;
    }
    return null;
}

/**
 * El cliente pide hablar con una persona. NO desactiva el bot:
 * solo se responde con contacto; el bot sigue activo hasta deshabilitación manual.
 */
function cw_chatbot_wants_human(string $mensaje): bool
{
    $triggers = [
        'hablar con alguien', 'hablar con una persona', 'persona real',
        'quiero que me llamen', 'quiero asesor', 'necesito asesor',
        'conectame con un asesor', 'conéctame con un asesor', 'conecta con un asesor',
        'hablar con un asesor', 'hablar con un humano', 'hablar con un agente',
        'pasame con un asesor', 'pásame con un asesor', 'atencion humana', 'atención humana',
        'quiero un humano', 'necesito un humano',
    ];
    $m = mb_strtolower($mensaje);
    foreach ($triggers as $t) {
        if (mb_strpos($m, $t) !== false) {
            return true;
        }
    }
    return (bool) preg_match('/\b(asesor|humano|supervisor)\b/u', $m)
        && (bool) preg_match('/\b(quiero|necesito|hablar|conectar|pasar|pasame|pásame|llamar)\b/u', $m);
}

function cw_chatbot_user_confirms_human_offer(string $mensaje, array $context): bool
{
    if (empty($context['ofrecio_asesor'])) {
        return false;
    }
    $m = mb_strtolower(trim($mensaje));
    if (preg_match('/^\s*(si|sí|ok|vale|claro|adelante|por favor|yes)\s*[.!?]?\s*$/u', $m)) {
        return true;
    }
    return cw_chatbot_wants_human($m);
}

function cw_chatbot_user_declines_human_offer(string $mensaje, array $context): bool
{
    if (empty($context['ofrecio_asesor'])) {
        return false;
    }
    $m = mb_strtolower(trim($mensaje));
    return (bool) preg_match('/^\s*(no|nop|nel|no gracias|mejor no|ahora no)\s*[.!?]?\s*$/u', $m);
}

/** Número WhatsApp de soporte (solo dígitos, con código país). */
function cw_chatbot_whatsapp_number(mysqli $conn): string
{
    $raw = cw_chat_config_get($conn, 'whatsapp_numero', '524771181285');
    $digits = preg_replace('/\D/', '', (string) $raw);
    if ($digits === '') {
        return '524771181285';
    }
    // Si viene a 10 dígitos MX, anteponer 52.
    if (strlen($digits) === 10) {
        return '52' . $digits;
    }
    return $digits;
}

function cw_chatbot_whatsapp_url(mysqli $conn, string $prefill = ''): string
{
    $url = 'https://wa.me/' . cw_chatbot_whatsapp_number($conn);
    if ($prefill !== '') {
        $url .= '?text=' . rawurlencode($prefill);
    }
    return $url;
}

function cw_chatbot_whatsapp_display(mysqli $conn): string
{
    $n = cw_chatbot_whatsapp_number($conn);
    if (str_starts_with($n, '52') && strlen($n) === 12) {
        return '+52 ' . substr($n, 2, 3) . ' ' . substr($n, 5, 3) . ' ' . substr($n, 8, 2) . ' ' . substr($n, 10, 2);
    }
    return '+' . $n;
}

/**
 * Botones CTA: registrar ticket (preferido) + WhatsApp.
 *
 * @return list<array{tipo:string,label:string,url?:string,text?:string,icon?:string,primary?:bool}>
 */
function cw_chatbot_ticket_wa_actions(mysqli $conn, string $waPrefill = 'Hola, necesito ayuda con mi cuenta ConlineWeb'): array
{
    return [
        [
            'tipo' => 'link',
            'label' => 'Registrar ticket',
            'url' => 'tickets.php',
            'icon' => 'bi-inbox-fill',
            'primary' => true,
        ],
        [
            'tipo' => 'whatsapp',
            'label' => 'WhatsApp',
            'url' => cw_chatbot_whatsapp_url($conn, $waPrefill),
            'icon' => 'bi-whatsapp',
            'primary' => false,
        ],
        [
            'tipo' => 'send',
            'label' => 'Hablar con agente',
            'text' => 'quiero hablar con un agente',
            'icon' => 'bi-headset',
            'primary' => false,
        ],
    ];
}

function cw_chatbot_human_contact_reply(mysqli $conn): string
{
    $waDisp = cw_chatbot_whatsapp_display($conn);
    return "Un agente puede atenderte ahora.\n\n"
        . "Lo ideal: **registrar tu ticket** para seguimiento.\n"
        . "Dudas rápidas: WhatsApp **{$waDisp}**.\n\n"
        . "Usa los botones de abajo. También puedes escribir «quiero un agente» en cualquier momento.";
}

function cw_chatbot_agent_offer_reply(): string
{
    return "No pude resolver eso con certeza desde aquí.\n\n"
        . cw_chatbot_scope_hint() . "\n\n"
        . "Puedes conversar con un agente. ¿Te gustaría conversar con uno o no?";
}

function cw_chatbot_out_of_scope_reply(): string
{
    return "Ese tema está fuera de lo que resuelvo en este chat.\n\n"
        . cw_chatbot_scope_hint() . "\n\n"
        . "Puedes conversar con un agente. ¿Te gustaría conversar con uno o no?";
}

/** Saludo / presentación */
function cw_chatbot_is_greeting(string $mensaje): bool
{
    $m = mb_strtolower(trim($mensaje));
    if ($m === '') {
        return false;
    }
    if (preg_match('/^(hola+|holi|hi+|hello|hey|buenas?|buen\s*d[ií]a|buenos\s*d[ií]as|buenas\s*tardes|buenas\s*noches|qu[eé]\s*tal|que\s*tal|saludos?)([\s!,.🙂😊👋]*)?$/u', $m)) {
        return true;
    }
    return (bool) preg_match('/^(hola|buenos d[ií]as|buenas tardes|buenas noches)\b/u', $m)
        && mb_strlen($m) <= 40
        && !preg_match('/\b(hosting|dominio|pago|ticket|vence|factura|cpanel)\b/u', $m);
}

/** Despedida / agradecimiento de cierre */
function cw_chatbot_is_farewell(string $mensaje): bool
{
    return cw_chatbot_is_conversation_closing($mensaje, []);
}

/**
 * Detecta cierre natural de conversación (gracias, ya es todo, nada más, etc.).
 *
 * @param array<string,mixed> $context
 */
function cw_chatbot_is_conversation_closing(string $mensaje, array $context = []): bool
{
    $m = mb_strtolower(trim($mensaje));
    if ($m === '') {
        return false;
    }
    $norm = cw_chatbot_normalize_text($m);

    // Respuestas a oferta de agente se manejan aparte.
    if (!empty($context['ofrecio_asesor']) && preg_match('/^(si|sí|no|nop|nel|ok|vale|claro|yes)([\s!,.]*)?$/u', $m)) {
        return false;
    }

    // Si pide otro tema concreto, no es despedida.
    if (preg_match('/\b(hosting|dominio|dns|pago|ticket|cpanel|vence|factura|contrasena|contraseña|cuando|cu[aá]ndo|como|c[oó]mo|quiero|necesito abrir|crear ticket)\b/u', $m)
        && !preg_match('/\b(gracias|ya (es|fue) todo|nada mas|nada más|no ocupo|no necesito)\b/u', $m)) {
        return false;
    }

    if (preg_match('/^(gracias|muchas gracias|mil gracias|thanks|thank you|ty|ok gracias|vale gracias)([\s!,.]*)?$/u', $m)) {
        return true;
    }
    if (preg_match('/^(adi[oó]s|hasta luego|nos vemos|bye|chao|bye bye)([\s!,.]*)?$/u', $m)) {
        return true;
    }
    if (preg_match('/\b(ya (es|fue) todo|eso (es|seria|sería) todo|era todo|con eso (es todo|quedo|quedé|basta)|nada mas|nada más|no ocupo( nada)?( mas| más)?|no necesito( nada)?( mas| más)?|ya no (necesito|ocupo|quiero|es necesario)|ya quede|ya quedé|ya quedo|ya quedó|ya esta (listo|bien)|ya está (listo|bien)|listo gracias|perfecto gracias|excelente gracias|muy bien gracias|por ahora (es todo|nada)|no por ahora|era eso|eso era todo)\b/u', $norm)
        || preg_match('/\b(ya (es|fue) todo|eso (es|seria|sería) todo|nada mas|nada más|no ocupo|no necesito|ya no (necesito|ocupo)|listo gracias|perfecto gracias)\b/u', $m)) {
        return true;
    }

    // "no" / "listo" / "perfecto" como cierre si el bot ofreció más ayuda o acabó un ticket.
    $closingSoft = (bool) preg_match('/^(no|nop|nel|no gracias|listo|perfecto|excelente|esta bien|está bien|muy bien|ok|okay|vale)([\s!,.]*)?$/u', $m);
    if ($closingSoft && empty($context['ofrecio_asesor']) && (!empty($context['ofrecio_mas_ayuda']) || in_array((string) ($context['ultima_intencion'] ?? ''), ['crear_ticket_ok', 'despedida', 'tickets', 'pagos', 'hosting', 'dominios', 'servicios', 'datos_cliente', 'confirmacion'], true))) {
        if (in_array($m, ['ok', 'okay', 'vale'], true) && empty($context['ofrecio_mas_ayuda']) && empty($context['ultima_intencion'])) {
            return false;
        }
        if (in_array($m, ['ok', 'okay', 'vale', 'perfecto', 'listo', 'excelente', 'muy bien', 'esta bien', 'está bien'], true)
            && empty($context['ofrecio_mas_ayuda'])
            && !in_array((string) ($context['ultima_intencion'] ?? ''), ['crear_ticket_ok', 'pagos', 'hosting', 'dominios', 'tickets', 'servicios', 'datos_cliente', 'confirmacion'], true)) {
            return false;
        }
        return true;
    }

    return (bool) preg_match('/\b(gracias|adi[oó]s|hasta luego|nos vemos)\b/u', $m)
        && mb_strlen($m) <= 64
        && !preg_match('/\b(como|c[oó]mo|cuando|cu[aá]ndo|donde|d[oó]nde)\b/u', $m);
}

/**
 * Despedida congruente con lo que se venía hablando.
 *
 * @param array<string,mixed> $context
 */
function cw_chatbot_farewell_reply(mysqli $conn, array $context = []): string
{
    $intent = (string) ($context['ultima_intencion'] ?? '');
    $nombre = cw_chat_config_get($conn, 'bot_nombre', 'Asistente ConlineWeb');

    if ($intent === 'crear_ticket_ok' || (!empty($context['ultimo_ticket_id']))) {
        $tid = (int) ($context['ultimo_ticket_id'] ?? 0);
        $ref = $tid > 0 ? " (#{$tid})" : '';
        return "¡Perfecto! Tu ticket{$ref} ya quedó registrado y el equipo lo atenderá.\n\n"
            . "Si más adelante necesitas algo sobre hosting, dominios, pagos o tu cuenta, aquí estaré. "
            . "También puedes pedir un **agente** cuando quieras.\n\n¡Que tengas un excelente día!";
    }

    if (in_array($intent, ['tickets', 'doc_crear_ticket'], true)) {
        return "¡Listo! Quedo atento si más adelante quieres revisar o crear otro ticket.\n\n"
            . "Recuerda: en cualquier momento puedes pedir hablar con un **agente**. ¡Hasta pronto!";
    }

    if (in_array($intent, ['pagos', 'hosting', 'dominios', 'vencimientos', 'servicios', 'datos_cliente'], true)
        || str_starts_with($intent, 'datos_')) {
        $tema = 'tu cuenta';
        if (str_contains($intent, 'pago')) {
            $tema = 'tus pagos';
        } elseif (str_contains($intent, 'hosting')) {
            $tema = 'tu hosting';
        } elseif (str_contains($intent, 'dominio')) {
            $tema = 'tus dominios';
        }
        return "¡Con mucho gusto! Me alegra haberte ayudado con {$tema}.\n\n"
            . "Si surge otra duda, escríbeme o pide un **agente**. Que tengas un excelente día.";
    }

    return "¡Con mucho gusto!\n\n"
        . "Si más adelante necesitas algo sobre hosting/cPanel, dominios/DNS, pagos, tickets o tu cuenta, aquí estaré ({$nombre}).\n"
        . "También puedes pedir un **agente** cuando quieras.\n\n¡Que tengas un excelente día!";
}

/** Confirmaciones cortas (ok, vale, etc.) — no cierre */
function cw_chatbot_is_ack(string $mensaje): bool
{
    $m = mb_strtolower(trim($mensaje));
    return (bool) preg_match('/^(ok|okay|vale|claro|perfecto|entendido|de acuerdo|listo|genial|excelente|muy bien|esta bien|est[aá] bien)([\s!,.]*)?$/u', $m);
}

function cw_chatbot_greeting_reply(mysqli $conn): string
{
    $name = cw_chat_config_get($conn, 'bot_nombre', 'Asistente ConlineWeb');
    return "¡Hola! Soy **{$name}**.\n\n"
        . cw_chatbot_scope_hint() . "\n\n"
        . "¿Qué necesitas hoy? Ejemplos: «¿cuándo vence mi hosting?», «tengo un pago pendiente» o «crear ticket».";
}

function cw_chatbot_ack_reply(mysqli $conn): string
{
    return "¡Perfecto! ¿Necesitas algo más sobre hosting, dominios, pagos, tickets o tu cuenta?\n"
        . "Si ya es todo, dime «gracias» o «ya es todo» y me despido. También puedes pedir un **agente** cuando quieras.";
}

/** Añade un cierre amable a respuestas de datos / KB (sin repetir si ya es cortés). */
function cw_chatbot_soften_reply(string $respuesta, string $intencion = ''): string
{
    $r = trim($respuesta);
    if ($r === '') {
        return $r;
    }
    $softIntents = ['saludo_hola', 'despedida_gracias', 'confirmacion_ok', 'ayuda_general', 'contacto_humano', 'aclaracion', 'sin_respuesta', 'tickets', 'crear_ticket', 'crear_ticket_ok', 'crear_ticket_cancelado', 'crear_ticket_error', 'asesor_rechazado', 'fuera_alcance', 'saludo', 'despedida'];
    if (in_array($intencion, $softIntents, true)) {
        return $r;
    }
    if (preg_match('/\?[\s]*$/u', $r)) {
        return $r;
    }
    if (preg_match('/(¿necesitas algo m[aá]s|estoy para|con gusto|excelente d[ií]a|escr[ií]beme)/iu', $r)) {
        return $r;
    }
    if (in_array($intencion, ['hosting', 'dominios', 'pagos', 'vencimientos', 'servicios', 'datos_cliente'], true)
        || str_starts_with($intencion, 'datos_')) {
        return $r . "\n\n¿Necesitas algo más sobre esto? Si ya es todo, dime «gracias» o «ya es todo».";
    }
    return $r . "\n\n¿Te ayudo con algo más? Si ya quedó resuelto, dime «gracias» y me despido.";
}

function cw_chatbot_reply_offers_more_help(string $respuesta): bool
{
    return (bool) preg_match('/\b(necesitas algo m[aá]s|te ayudo con algo m[aá]s|ya es todo|algo m[aá]s sobre)\b/iu', $respuesta);
}

function cw_chatbot_detect_intent(string $mensaje): string
{
    $intents = [
        'hosting' => ['hosting', 'alojamiento', 'servidor', 'cpanel', 'ftp', 'espacio', 'disco', 'plan hosting', 'hospedaje', 'sitio caido', 'error 500'],
        'dominios' => ['dominio', 'dominios', 'dns', 'transferencia', 'nameserver', 'registro', 'apuntar', 'epp'],
        'pagos' => ['pago', 'pagos', 'factura', 'facturacion', 'facturación', 'cobro', 'tarjeta', 'pendiente', 'renovar', 'comprobante', 'stripe', 'adeudo'],
        'tickets' => ['ticket', 'tickets', 'soporte', 'solicitud', 'incidencia', 'reclamo'],
        'correo' => ['correo', 'email', 'imap', 'smtp', 'outlook', 'buzón', 'buzon'],
        'ssl' => ['ssl', 'https', 'certificado', 'candado'],
        'wordpress' => ['wordpress', 'wp', 'sitio web', 'página web', 'pagina web', 'cms'],
        'panel' => ['panel', 'dashboard', 'portal', 'acceso', 'contraseña', 'login', 'ingresar', 'cuenta'],
        'horario' => ['horario', 'horarios', 'atienden', 'atencion', 'atención'],
        'contacto' => ['contacto', 'telefono', 'teléfono', 'whatsapp', 'llamar', 'asesor', 'agente'],
    ];

    $best = 'general';
    $bestScore = 0;
    foreach ($intents as $intent => $keywords) {
        $score = 0;
        foreach ($keywords as $kw) {
            if (mb_strpos($mensaje, $kw) !== false) {
                $score += mb_strlen($kw) >= 5 ? 3 : 2;
            }
        }
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $intent;
        }
    }
    return $best;
}

/** @return array<int,array<string,mixed>> */
function cw_chatbot_load_knowledge(mysqli $conn): array
{
    if (isset($GLOBALS['cw_chatbot_kb_cache']) && is_array($GLOBALS['cw_chatbot_kb_cache'])) {
        return $GLOBALS['cw_chatbot_kb_cache'];
    }
    $stmt = $conn->prepare('SELECT k.*, c.nombre AS categoria_nombre, c.slug AS categoria_slug
        FROM cw_chat_conocimiento k
        LEFT JOIN cw_chat_categorias c ON k.categoria_id = c.id
        WHERE k.activo = 1
        ORDER BY k.prioridad DESC, k.veces_usada DESC');
    if (!$stmt) {
        return [];
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    $GLOBALS['cw_chatbot_kb_cache'] = $rows;
    return $rows;
}

function cw_chatbot_invalidate_cache(): void
{
    unset($GLOBALS['cw_chatbot_kb_cache']);
}

/** @param array<string,mixed> $entry */
function cw_chatbot_score_entry(array $entry, string $mensaje, array $tokens, string $intent, array $context, bool $isFollowup): float
{
    $score = 0.0;
    $searchFields = mb_strtolower(
        ($entry['pregunta'] ?? '') . ' ' .
        ($entry['palabras_clave'] ?? '') . ' ' .
        ($entry['titulo'] ?? '') . ' ' .
        ($entry['intencion'] ?? '')
    );
    $mensajeNorm = cw_chatbot_normalize_text($mensaje);
    $fieldsNorm = cw_chatbot_normalize_text($searchFields);

    $entryIntent = (string) ($entry['intencion'] ?? '');
    if ($entryIntent !== '' && $entryIntent === $intent) {
        $score += 4.0;
    }
    if ($isFollowup && !empty($context['ultima_intencion'])) {
        $score += 3.0;
    }
    if ($isFollowup && !empty($context['ultimo_conocimiento_id']) && (int) $entry['id'] === (int) $context['ultimo_conocimiento_id']) {
        $score += 1.5;
    }

    foreach ($tokens as $token) {
        $tokN = cw_chatbot_normalize_text($token);
        if (mb_strlen($token) >= 3 && (mb_strpos($searchFields, $token) !== false || mb_strpos($fieldsNorm, $tokN) !== false)) {
            $score += 2.5;
        }
    }

    $keywords = preg_split('/[,;]+/u', (string) ($entry['palabras_clave'] ?? ''));
    foreach ($keywords as $kw) {
        $kw = trim(mb_strtolower($kw));
        if ($kw === '' || mb_strlen($kw) < 3) {
            continue;
        }
        $kwN = cw_chatbot_normalize_text($kw);
        if (mb_strpos($mensaje, $kw) !== false || mb_strpos($mensajeNorm, $kwN) !== false) {
            $score += 5.0;
        }
    }

    $pregunta = mb_strtolower((string) ($entry['pregunta'] ?? ''));
    if ($pregunta !== '' && mb_strlen($mensaje) >= 6) {
        similar_text($mensajeNorm, cw_chatbot_normalize_text($pregunta), $pct);
        if ($pct >= 70) {
            $score += 12.0;
        } elseif ($pct >= 50) {
            $score += 6.0;
        } elseif ($pct >= 38) {
            $score += 2.5;
        }
        if (mb_strlen($mensaje) >= 10 && (mb_strpos($pregunta, $mensaje) !== false || mb_strpos($mensaje, $pregunta) !== false)) {
            $score += 6.0;
        }
    }

    $score += ((int) ($entry['prioridad'] ?? 0)) * 0.05;

    return $score;
}

/**
 * @return array<int,array<string,mixed>>
 */
function cw_chatbot_rank_matches(mysqli $conn, string $mensajeActual, string $mensajeBusqueda, array $context, bool $isFollowup): array
{
    $intent = cw_chatbot_detect_intent(cw_chatbot_expand_message($mensajeActual));
    if ($isFollowup && !empty($context['ultima_intencion'])) {
        $intent = (string) $context['ultima_intencion'];
    }

    $ordinal = cw_chatbot_extract_ordinal($mensajeActual);
    $entries = cw_chatbot_load_knowledge($conn);
    $tokens = cw_chatbot_tokenize($mensajeActual);
    $ranked = [];

    foreach ($entries as $entry) {
        $entryIntent = (string) ($entry['intencion'] ?? '');
        if ($isFollowup && !empty($context['ultima_intencion']) && $entryIntent !== '' && $entryIntent !== $context['ultima_intencion']) {
            continue;
        }
        $score = cw_chatbot_score_entry($entry, $mensajeActual, $tokens, $intent, $context, $isFollowup);
        if ($score > 0) {
            $entry['_score'] = $score;
            $ranked[] = $entry;
        }
    }

    usort($ranked, function ($a, $b) {
        return ($b['_score'] <=> $a['_score']);
    });

    if ($ordinal !== null && count($ranked) > 1) {
        $intentRows = array_values(array_filter($ranked, function ($r) use ($intent) {
            return ($r['intencion'] ?? '') === $intent || $intent === 'general';
        }));
        if (isset($intentRows[$ordinal - 1])) {
            $picked = $intentRows[$ordinal - 1];
            $picked['_score'] = ($picked['_score'] ?? 0) + 20;
            array_unshift($ranked, $picked);
            $ranked = array_values(array_unique($ranked, SORT_REGULAR));
        }
    }

    return $ranked;
}

function cw_chatbot_format_answer(string $respuesta): string
{
    $respuesta = trim(preg_replace("/\n{3,}/", "\n\n", $respuesta));
    return $respuesta;
}

/** @return array<int,array<string,mixed>> */
function cw_chatbot_last_bot_turn(mysqli $conn, int $conversacionId): array
{
    $stmt = $conn->prepare("SELECT contenido, metadata FROM cw_chat_mensajes
        WHERE conversacion_id = ? AND remitente_tipo = 'bot'
        ORDER BY id DESC LIMIT 1");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $conversacionId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return [];
    }
    $meta = [];
    if (!empty($row['metadata'])) {
        $decoded = json_decode((string) $row['metadata'], true);
        if (is_array($decoded)) {
            $meta = $decoded;
        }
    }
    return ['contenido' => $row['contenido'], 'metadata' => $meta];
}

function cw_chatbot_build_search_text(string $mensaje, array $contextMessages, array $context, bool $isFollowup): string
{
    $parts = [cw_chatbot_expand_message($mensaje)];
    if ($isFollowup) {
        if (!empty($context['ultima_intencion'])) {
            $parts[] = $context['ultima_intencion'];
        }
        if (!empty($context['ultimo_titulo'])) {
            $parts[] = $context['ultimo_titulo'];
        }
        foreach (array_slice($contextMessages, -2) as $ctx) {
            $parts[] = cw_chatbot_expand_message($ctx);
        }
    }
    return implode(' ', $parts);
}

function cw_chatbot_topic_changed(string $mensaje, array $context): bool
{
    if (empty($context['ultima_intencion'])) {
        return false;
    }
    $prev = (string) $context['ultima_intencion'];
    $current = cw_chatbot_detect_intent(cw_chatbot_expand_message($mensaje));
    if ($current === 'general' || $prev === $current) {
        return false;
    }
    $liveIntents = ['pagos', 'hosting', 'dominios', 'vencimientos', 'servicios', 'datos_cliente', 'tickets', 'crear_ticket'];
    if (in_array($prev, $liveIntents, true) && in_array($current, $liveIntents, true)) {
        return $prev !== $current;
    }
    return $prev !== $current;
}

function cw_chatbot_response_key(array $result): string
{
    if (!empty($result['conocimiento_id'])) {
        return 'kb:' . (int) $result['conocimiento_id'];
    }
    if (!empty($result['intencion'])) {
        return 'live:' . (string) $result['intencion'] . ':' . substr(md5((string) ($result['respuesta'] ?? '')), 0, 8);
    }
    return 'txt:' . substr(md5((string) ($result['respuesta'] ?? '')), 0, 12);
}

/**
 * @param array<int,string> $contextMessages
 * @return array{matched:bool,respuesta?:string,intencion?:string,conocimiento_id?:int,escalar?:bool,clarify?:bool,guardar_contexto?:array<string,mixed>}
 */
function cw_chatbot_respond(mysqli $conn, int $conversacionId, string $mensajeUsuario, array $contextMessages = [], int $clienteId = 0): array
{
    $mensaje = mb_strtolower(trim($mensajeUsuario));
    if ($mensaje === '') {
        return ['matched' => false, 'escalar' => false];
    }

    $context = cw_chatbot_context_get($conn, $conversacionId);

    // Pedir asesor tiene prioridad incluso si hay un ticket a medias.
    if (cw_chatbot_wants_human($mensaje)) {
        $ctxSave = array_merge($context, [
            'sin_match_consecutivos' => 0,
            'ofrecio_asesor' => true,
            'esperando_confirmacion' => false,
            'ultima_intencion' => 'contacto_humano',
            'ultima_respuesta_key' => 'human_contact',
        ]);
        cw_chatbot_context_save($conn, $conversacionId, $ctxSave);
        return [
            'matched' => true,
            'respuesta' => cw_chatbot_human_contact_reply($conn),
            'intencion' => 'contacto_humano',
            'escalar' => false,
            'prioridad_alta' => true,
            'origen' => 'contacto',
            'acciones' => cw_chatbot_ticket_wa_actions($conn, 'Hola, quiero hablar con un asesor de ConlineWeb'),
        ];
    }

    // Flujo de alta de ticket en curso: tiene prioridad sobre saludo/KB.
    if ($clienteId > 0 && !empty($context['ticket_draft']) && is_array($context['ticket_draft'])) {
        // Si en medio del alta dice gracias / ya es todo → cancelar y despedir.
        if (cw_chatbot_is_conversation_closing($mensajeUsuario, array_merge($context, ['ofrecio_mas_ayuda' => true]))) {
            $ctx = $context;
            $ctx['ticket_draft'] = null;
            $ctx['ultima_intencion'] = 'despedida';
            $ctx['ofrecio_mas_ayuda'] = false;
            cw_chatbot_context_save($conn, $conversacionId, $ctx);
            return [
                'matched' => true,
                'respuesta' => "Listo, cancelé la creación del ticket.\n\n" . cw_chatbot_farewell_reply($conn, $context),
                'intencion' => 'despedida',
                'escalar' => false,
                'origen' => 'cortesia',
            ];
        }
        $ticketFlow = cw_chatbot_tickets_handle_draft($conn, $clienteId, $mensajeUsuario, $context);
        if (!empty($ticketFlow['matched'])) {
            if (!empty($ticketFlow['guardar_contexto'])) {
                cw_chatbot_context_save($conn, $conversacionId, $ticketFlow['guardar_contexto']);
            }
            return [
                'matched' => true,
                'respuesta' => (string) ($ticketFlow['respuesta'] ?? ''),
                'intencion' => (string) ($ticketFlow['intencion'] ?? 'crear_ticket'),
                'escalar' => false,
                'origen' => 'tickets',
                'prioridad_alta' => !empty($ticketFlow['prioridad_alta']),
            ];
        }
    }

    $isFollowup = cw_chatbot_is_followup($mensaje, $context);
    // No borrar contexto si el cliente está cerrando la conversación.
    $isClosingMsg = cw_chatbot_is_conversation_closing($mensajeUsuario, $context)
        || cw_chatbot_is_conversation_closing($mensaje, $context);
    if (!$isFollowup && !$isClosingMsg && cw_chatbot_topic_changed($mensaje, $context) && empty($context['ticket_draft'])) {
        $context = [
            'sin_match_consecutivos' => (int) ($context['sin_match_consecutivos'] ?? 0),
        ];
    }

    // Cortesía primero: saludos, despedidas y confirmaciones.
    if (cw_chatbot_is_greeting($mensaje)) {
        cw_chatbot_context_save($conn, $conversacionId, [
            'ultima_intencion' => 'saludo',
            'ultima_respuesta_key' => 'saludo',
            'sin_match_consecutivos' => 0,
            'ofrecio_asesor' => false,
            'ofrecio_mas_ayuda' => false,
        ]);
        return [
            'matched' => true,
            'respuesta' => cw_chatbot_greeting_reply($conn),
            'intencion' => 'saludo',
            'escalar' => false,
            'origen' => 'cortesia',
            'acciones' => cw_chatbot_ticket_wa_actions($conn),
        ];
    }
    if (cw_chatbot_is_conversation_closing($mensajeUsuario, $context) || cw_chatbot_is_conversation_closing($mensaje, $context)) {
        cw_chatbot_context_save($conn, $conversacionId, [
            'ultima_intencion' => 'despedida',
            'ultima_respuesta_key' => 'despedida',
            'sin_match_consecutivos' => 0,
            'ofrecio_mas_ayuda' => false,
            'ofrecio_asesor' => false,
            'ultimo_ticket_id' => $context['ultimo_ticket_id'] ?? null,
        ]);
        return [
            'matched' => true,
            'respuesta' => cw_chatbot_farewell_reply($conn, $context),
            'intencion' => 'despedida',
            'escalar' => false,
            'origen' => 'cortesia',
        ];
    }

    // Oferta de agente: sí / no
    if (cw_chatbot_user_declines_human_offer($mensaje, $context)) {
        cw_chatbot_context_save($conn, $conversacionId, array_merge($context, [
            'ofrecio_asesor' => false,
            'ultima_intencion' => 'asesor_rechazado',
            'sin_match_consecutivos' => 0,
        ]));
        return [
            'matched' => true,
            'respuesta' => "Entendido. Sigo aquí si necesitas algo más.\n\nPara seguimiento, lo ideal es **registrar tu ticket**. Si tienes dudas, usa WhatsApp.\nSi ya es todo, dime «gracias» y me despido.",
            'intencion' => 'asesor_rechazado',
            'escalar' => false,
            'origen' => 'cortesia',
            'acciones' => cw_chatbot_ticket_wa_actions($conn),
        ];
    }
    if (cw_chatbot_is_ack($mensaje) && empty($context['ofrecio_asesor'])) {
        cw_chatbot_context_save($conn, $conversacionId, [
            'ultima_intencion' => (string) ($context['ultima_intencion'] ?? 'confirmacion'),
            'ultima_respuesta_key' => 'ack',
            'sin_match_consecutivos' => 0,
            'ofrecio_mas_ayuda' => true,
            'ultimo_ticket_id' => $context['ultimo_ticket_id'] ?? null,
        ]);
        return [
            'matched' => true,
            'respuesta' => cw_chatbot_ack_reply($conn),
            'intencion' => 'confirmacion',
            'escalar' => false,
            'origen' => 'cortesia',
        ];
    }

    // Pedir asesor: responde con contacto pero NO desactiva el bot (solo staff lo pausa).
    if (cw_chatbot_wants_human($mensaje) || cw_chatbot_user_confirms_human_offer($mensaje, $context)) {
        $ctxSave = array_merge($context, [
            'sin_match_consecutivos' => 0,
            'ofrecio_asesor' => true,
            'esperando_confirmacion' => false,
            'ultima_intencion' => 'contacto_humano',
            'ultima_respuesta_key' => 'human_contact',
        ]);
        cw_chatbot_context_save($conn, $conversacionId, $ctxSave);
        return [
            'matched' => true,
            'respuesta' => cw_chatbot_human_contact_reply($conn),
            'intencion' => 'contacto_humano',
            'escalar' => false,
            'prioridad_alta' => true,
            'origen' => 'contacto',
            'acciones' => cw_chatbot_ticket_wa_actions($conn, 'Hola, quiero hablar con un asesor de ConlineWeb'),
        ];
    }

    $searchText = cw_chatbot_build_search_text($mensaje, $contextMessages, $context, $isFollowup);
    $searchText = mb_strtolower($searchText);

    // Fuera de alcance: SEO, marketing, desarrollo, etc.
    if (cw_chatbot_is_out_of_scope($mensajeUsuario)) {
        cw_chatbot_context_save($conn, $conversacionId, array_merge($context, [
            'ofrecio_asesor' => true,
            'ultima_intencion' => 'fuera_alcance',
            'sin_match_consecutivos' => (int) ($context['sin_match_consecutivos'] ?? 0) + 1,
        ]));
        return [
            'matched' => true,
            'respuesta' => cw_chatbot_out_of_scope_reply(),
            'intencion' => 'fuera_alcance',
            'escalar' => false,
            'origen' => 'alcance',
            'acciones' => cw_chatbot_ticket_wa_actions($conn),
        ];
    }

    // Tickets del cliente: listar pendientes / crear solicitud desde el chat.
    if ($clienteId > 0) {
        $ticketRes = cw_chatbot_tickets_try($conn, $clienteId, $mensajeUsuario, $context);
        if (!empty($ticketRes['matched']) && !empty($ticketRes['respuesta'])) {
            $acciones = $ticketRes['acciones'] ?? null;
            if ($acciones === null && in_array(($ticketRes['intencion'] ?? ''), ['tickets', 'crear_ticket', 'crear_ticket_ok'], true)) {
                $acciones = cw_chatbot_ticket_wa_actions($conn);
            }
            $ticketReply = cw_chatbot_soften_reply((string) $ticketRes['respuesta'], (string) ($ticketRes['intencion'] ?? 'tickets'));
            $ctxTicket = $ticketRes['guardar_contexto'] ?? $context;
            $ctxTicket['ofrecio_mas_ayuda'] = cw_chatbot_reply_offers_more_help($ticketReply)
                || (($ticketRes['intencion'] ?? '') === 'crear_ticket_ok');
            if (($ticketRes['intencion'] ?? '') === 'crear_ticket_ok') {
                $ctxTicket['ultima_intencion'] = 'crear_ticket_ok';
            }
            cw_chatbot_context_save($conn, $conversacionId, $ctxTicket);
            return [
                'matched' => true,
                'respuesta' => $ticketReply,
                'intencion' => (string) ($ticketRes['intencion'] ?? 'tickets'),
                'escalar' => false,
                'origen' => 'tickets',
                'prioridad_alta' => !empty($ticketRes['prioridad_alta']),
                'acciones' => $acciones,
            ];
        }
    }

    if ($clienteId > 0 && cw_chatbot_live_should_handle($mensaje, $context, $isFollowup)) {
        $live = cw_chatbot_try_live_response($conn, $clienteId, $mensaje, $context, $isFollowup);
        if (!empty($live['matched']) && !empty($live['respuesta'])) {
            $respKey = cw_chatbot_response_key($live);
            if (!$isFollowup && ($context['ultima_respuesta_key'] ?? '') === $respKey) {
                return [
                    'matched' => true,
                    'respuesta' => '¡Claro! ¿Quieres más detalle de algún servicio? Puedes decirme «el primero», «mis pagos pendientes» o el nombre del dominio/hosting.',
                    'intencion' => 'aclaracion',
                    'escalar' => false,
                    'origen' => 'datos_cliente',
                ];
            }
            $ctxSave = $live['guardar_contexto'] ?? [];
            $ctxSave['ultima_respuesta_key'] = $respKey;
            $intencion = $live['intencion'] ?? 'datos_cliente';
            $liveReply = cw_chatbot_soften_reply(cw_chatbot_format_answer((string) $live['respuesta']), $intencion);
            $ctxSave['ofrecio_mas_ayuda'] = cw_chatbot_reply_offers_more_help($liveReply);
            $ctxSave['ultima_intencion'] = $intencion;
            cw_chatbot_context_save($conn, $conversacionId, $ctxSave);
            return [
                'matched' => true,
                'respuesta' => $liveReply,
                'intencion' => $intencion,
                'conocimiento_id' => 0,
                'escalar' => false,
                'origen' => $live['origen'] ?? 'datos_cliente',
            ];
        }
        return [
            'matched' => true,
            'respuesta' => "Disculpa, en este momento no pude consultar tus servicios.\n\nPuedes revisarlos en **Hosting**, **Dominios** y **Mis pagos** del panel, o reformular la pregunta y lo intentamos de nuevo. ¿Quieres que te oriente con otra cosa mientras tanto?",
            'intencion' => 'datos_cliente_error',
            'escalar' => false,
            'origen' => 'datos_cliente',
        ];
    }

    if (!$isFollowup && mb_strlen($mensaje) < 3 && !preg_match('/\d/u', $mensaje)) {
        return [
            'matched' => true,
            'respuesta' => cw_chatbot_greeting_reply($conn),
            'intencion' => 'saludo',
            'escalar' => false,
            'origen' => 'cortesia',
        ];
    }

    $ranked = cw_chatbot_rank_matches($conn, $mensaje, $searchText, $context, $isFollowup);
    $best = $ranked[0] ?? null;
    $bestScore = $best ? (float) ($best['_score'] ?? 0) : 0.0;

    $thresholdMatch = 8.5;
    $thresholdClarify = 5.5;

    if ($best && $bestScore >= $thresholdMatch) {
        cw_chatbot_increment_usage($conn, (int) $best['id']);
        $respuesta = cw_chatbot_format_answer((string) $best['respuesta']);
        $respKey = 'kb:' . (int) $best['id'];
        if (!$isFollowup && ($context['ultima_respuesta_key'] ?? '') === $respKey) {
            $aclaracion = cw_chat_config_get($conn, 'mensaje_aclaracion',
                'Entiendo. ¿Podrías decirme con más detalle qué necesitas? Por ejemplo: pagos, hosting, dominios, facturación o soporte técnico.');
            cw_chatbot_context_save($conn, $conversacionId, array_merge($context, [
                'sin_match_consecutivos' => (int) ($context['sin_match_consecutivos'] ?? 0) + 1,
                'ofrecio_asesor' => false,
                'esperando_confirmacion' => false,
            ]));
            return [
                'matched' => true,
                'respuesta' => $aclaracion,
                'intencion' => 'aclaracion',
                'escalar' => false,
            ];
        }
        $newContext = [
            'ultima_intencion' => (string) ($best['intencion'] ?? cw_chatbot_detect_intent($searchText)),
            'ultimo_conocimiento_id' => (int) $best['id'],
            'ultimo_titulo' => (string) ($best['titulo'] ?? ''),
            'ultima_respuesta_key' => $respKey,
            'sin_match_consecutivos' => 0,
            'ofrecio_asesor' => false,
            'ultima_lista_tipo' => null,
        ];
        $kbReply = cw_chatbot_soften_reply($respuesta, $newContext['ultima_intencion']);
        $newContext['ofrecio_mas_ayuda'] = cw_chatbot_reply_offers_more_help($kbReply);
        cw_chatbot_context_save($conn, $conversacionId, $newContext);

        return [
            'matched' => true,
            'respuesta' => $kbReply,
            'intencion' => $newContext['ultima_intencion'],
            'conocimiento_id' => (int) $best['id'],
            'escalar' => false,
        ];
    }

    if ($best && $bestScore >= $thresholdClarify) {
        $titulo = (string) ($best['titulo'] ?? 'este tema');
        $aclaracion = cw_chat_config_get($conn, 'mensaje_aclaracion',
            '¿Te refieres a «{titulo}»? Si es así, puedo darte más detalles o conectarte con un asesor.');
        $aclaracion = str_replace('{titulo}', $titulo, $aclaracion);

        $newContext = [
            'ultima_intencion' => (string) ($best['intencion'] ?? cw_chatbot_detect_intent($searchText)),
            'ultimo_conocimiento_id' => (int) $best['id'],
            'ultimo_titulo' => $titulo,
            'ultima_respuesta_key' => 'kb:' . (int) $best['id'],
            'esperando_confirmacion' => true,
            'sin_match_consecutivos' => 0,
            'ofrecio_asesor' => false,
            'ultima_lista_tipo' => null,
        ];
        cw_chatbot_context_save($conn, $conversacionId, $newContext);

        if (!empty($context['esperando_confirmacion']) && $isFollowup) {
            cw_chatbot_increment_usage($conn, (int) $best['id']);
            return [
                'matched' => true,
                'respuesta' => cw_chatbot_format_answer((string) $best['respuesta']),
                'intencion' => $newContext['ultima_intencion'],
                'conocimiento_id' => (int) $best['id'],
                'escalar' => false,
            ];
        }

        return [
            'matched' => true,
            'respuesta' => $aclaracion,
            'intencion' => $newContext['ultima_intencion'],
            'clarify' => true,
            'escalar' => false,
        ];
    }

    cw_chat_log_unanswered($conn, $conversacionId, $mensajeUsuario, cw_chatbot_detect_intent($searchText));

    $sinMatch = (int) ($context['sin_match_consecutivos'] ?? 0) + 1;
    $msgSinResp = cw_chatbot_agent_offer_reply();

    cw_chatbot_context_save($conn, $conversacionId, array_merge($context, [
        'sin_match_consecutivos' => $sinMatch,
        'ofrecio_asesor' => true,
        'esperando_confirmacion' => false,
        'ultima_respuesta_key' => 'sin_respuesta',
    ]));

    // Nunca escalar automáticamente: el bot permanece activo hasta deshabilitación manual.
    return [
        'matched' => true,
        'respuesta' => $msgSinResp,
        'intencion' => 'sin_respuesta',
        'escalar' => false,
        'acciones' => cw_chatbot_ticket_wa_actions($conn),
    ];
}

function cw_chatbot_increment_usage(mysqli $conn, int $id): void
{
    $stmt = $conn->prepare('UPDATE cw_chat_conocimiento SET veces_usada = veces_usada + 1 WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
}

function cw_chatbot_get_bot_name(mysqli $conn): string
{
    return cw_chat_config_get($conn, 'bot_nombre', 'Asistente ConlineWeb');
}

/** @return array<int,string> */
function cw_chatbot_recent_user_messages(mysqli $conn, int $conversacionId, int $limit = 6): array
{
    $stmt = $conn->prepare("SELECT contenido FROM cw_chat_mensajes
        WHERE conversacion_id = ? AND remitente_tipo = 'cliente'
        ORDER BY id DESC LIMIT ?");
    $out = [];
    if ($stmt) {
        $stmt->bind_param('ii', $conversacionId, $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $out[] = $row['contenido'];
        }
        $stmt->close();
    }
    return array_reverse($out);
}

function cw_chatbot_clear_context(mysqli $conn, int $conversacionId): void
{
    cw_chatbot_context_save($conn, $conversacionId, []);
}
