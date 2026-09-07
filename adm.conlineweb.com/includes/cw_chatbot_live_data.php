<?php
/**
 * Respuestas del chatbot con datos reales del cliente (hosting, dominios, pagos).
 * Solo usa información consultada en BD; no inventa montos ni fechas.
 */
require_once __DIR__ . '/cw_chat_client_context.php';

$vencHelper = dirname(__DIR__, 2) . '/cliente.conlineweb.com/includes/cliente_dias_vencimiento.php';
if (is_file($vencHelper)) {
    require_once $vencHelper;
}
if (!function_exists('cliente_dias_vencimiento')) {
    function cliente_dias_vencimiento($fecha): ?int
    {
    if (empty($fecha) || $fecha === '0000-00-00' || $fecha === '0001-01-01') {
        return null;
    }
        $ts = strtotime($fecha);
        if ($ts === false) {
            return null;
        }
        return (int) floor(($ts - strtotime('today')) / 86400);
    }
}

function cw_chatbot_format_fecha(?string $fecha): string
{
    if (empty($fecha) || $fecha === '0000-00-00' || $fecha === '0000-00-00 00:00:00' || $fecha === '0001-01-01') {
        return 'sin fecha registrada';
    }
    $ts = strtotime($fecha);
    if ($ts === false) {
        return 'sin fecha registrada';
    }
    $meses = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];
    return date('j', $ts) . ' de ' . $meses[(int) date('n', $ts)] . ' de ' . date('Y', $ts);
}

function cw_chatbot_format_monto($monto, ?string $currency): string
{
    $amount = number_format((float) $monto, 2, '.', ',');
    $cur = strtoupper(trim((string) $currency));
    if ($cur === 'USD' || $cur === 'US') {
        return 'USD $' . $amount;
    }
    return '$' . $amount . ' MXN';
}

function cw_chatbot_vencimiento_texto(?string $fecha): string
{
    if (!function_exists('cliente_dias_vencimiento')) {
        return 'vence el ' . cw_chatbot_format_fecha($fecha);
    }
    $dias = cliente_dias_vencimiento($fecha);
    if ($dias === null) {
        return 'sin fecha de vencimiento registrada';
    }
    if ($dias < 0) {
        $n = abs($dias);
        return 'venció el ' . cw_chatbot_format_fecha($fecha) . ' (hace ' . $n . ' día' . ($n === 1 ? '' : 's') . ')';
    }
    if ($dias === 0) {
        return 'vence hoy (' . cw_chatbot_format_fecha($fecha) . ')';
    }
    return 'vence el ' . cw_chatbot_format_fecha($fecha) . ' (' . $dias . ' día' . ($dias === 1 ? '' : 's') . ' restante' . ($dias === 1 ? '' : 's') . ')';
}

/** Texto directo para el cliente: días restantes + fecha */
function cw_chatbot_dias_restantes_cliente(?string $fecha): string
{
    if (!function_exists('cliente_dias_vencimiento')) {
        return 'La fecha de vencimiento es el **' . cw_chatbot_format_fecha($fecha) . '**.';
    }
    $dias = cliente_dias_vencimiento($fecha);
    if ($dias === null) {
        return 'No tengo una fecha de vencimiento registrada para este servicio.';
    }
    $fechaFmt = cw_chatbot_format_fecha($fecha);
    if ($dias < 0) {
        $n = abs($dias);
        return 'Venció el **' . $fechaFmt . '** (hace **' . $n . ' día' . ($n === 1 ? '' : 's') . '**). Te recomendamos renovar cuanto antes en **Mis pagos**.';
    }
    if ($dias === 0) {
        return 'Vence **hoy** (' . $fechaFmt . '). Renueva hoy mismo desde **Mis pagos** para evitar interrupciones.';
    }
    return 'Te quedan **' . $dias . ' día' . ($dias === 1 ? '' : 's') . '** para el vencimiento (fecha: **' . $fechaFmt . '**).';
}

function cw_chatbot_live_is_expiry_query(string $searchText): bool
{
    return (bool) preg_match(
        '/\b(vence|vencimiento|vencen|caduca|caducidad|cu[aá]ndo vence|fecha de vencimiento|fecha de pago|renovar|renovaci[oó]n|cu[aá]ntos d[ií]as|d[ií]as (le quedan|restantes|faltan|para vencer)|tiempo (para|que) vence|plazo para renovar)\b/u',
        $searchText
    );
}

function cw_chatbot_live_has_account_context(string $searchText): bool
{
    // Evitar que «mi contraseña / mi usuario» dispare datos de hosting/dominios.
    if (preg_match('/\b(contrase[nñ]a|password|usuario|login|ingreso|correo de (acceso|login)|sesi[oó]n)\b/u', $searchText)) {
        return false;
    }
    $patterns = [
        '/\b(mis (hosting|dominios|pagos|servicios|tickets)|mi (hosting|dominio|pago|servicio|ticket|sitio|plan))\b/u',
        '/\b(tengo|debo|adeudo|adeudos)\b.*\b(pago|pagos|pendiente|adeudo)?/u',
        '/\b(pagos? pendientes?|adeudos?)\b/u',
        '/\b(cu[aá]ndo vence|cu[aá]nto debo|cu[aá]nto tiempo tengo|que servicios tengo|qué servicios tengo|cu[aá]ntos servicios)\b/u',
    ];
    foreach ($patterns as $p) {
        if (preg_match($p, $searchText)) {
            return true;
        }
    }
    return false;
}

function cw_chatbot_live_is_howto_only(string $searchText): bool
{
    return (bool) preg_match(
        '/\b(c[oó]mo|pasos para|donde (creo|configuro|administro)|desde (el |)cpanel|zonas dns|email accounts|crear correo|registros dns)\b/u',
        $searchText
    );
}

function cw_chatbot_live_should_handle(string $mensaje, array $context, bool $isFollowup): bool
{
    if ($isFollowup && !empty($context['ultima_lista_tipo'])) {
        return true;
    }
    $m = mb_strtolower(trim($mensaje));
    if (cw_chatbot_live_is_howto_only($m)) {
        return false;
    }
    // Cliente logueado: vigencia y pagos se resuelven con tablas hosting, dominios y pagos
    if (preg_match('/\b(vigencia|vencimiento|vencen?|caduca|renovar|renovaci[oó]n|cu[aá]ndo vence|cu[aá]ntos d[ií]as|d[ií]as (restantes|faltan|le quedan|de vigencia))\b/u', $m)) {
        return true;
    }
    if (preg_match('/\b(pagos? pendientes?|adeudo|adeudos|debo|tengo (que )?pagar|mis pagos|debo pagar)\b/u', $m)) {
        return true;
    }
    if (preg_match('/\b(mi hosting|mis hosting|mi dominio|mis dominios|mis servicios|que servicios tengo|qué servicios tengo|cu[aá]ntos servicios|mi sitio|mi pagina|mi página)\b/u', $m)) {
        return true;
    }
    if (cw_chatbot_live_has_account_context($m)) {
        return true;
    }
    return false;
}

function cw_chatbot_is_account_data_query(string $mensaje): bool
{
    return cw_chatbot_live_should_handle($mensaje, [], false);
}

/**
 * @return string|null pagos|vencimientos|servicios|hosting|dominios
 */
function cw_chatbot_live_detect_topic(string $mensaje, array $context, bool $isFollowup): ?string
{
    $m = mb_strtolower(trim($mensaje));
    $expanded = mb_strtolower(cw_chatbot_expand_message($mensaje));

    if ($isFollowup && !empty($context['ultima_lista_tipo'])) {
        if (cw_chatbot_extract_ordinal($mensaje) !== null) {
            return (string) $context['ultima_lista_tipo'];
        }
        if (preg_match('/\b(ese|esa|ese dominio|ese hosting|ese pago|el primero|el segundo)\b/u', $m)) {
            return (string) $context['ultima_lista_tipo'];
        }
    }

    if (preg_match('/\b(vence|vencimiento|vigencia|vencen|renovar|renovaci[oó]n|caduca|caducidad|cu[aá]ndo vence|cu[aá]ntos d[ií]as|d[ií]as (restantes|faltan|le quedan))\b/u', $m)) {
        if (preg_match('/\b(dominio|dominios|\.com|\.mx|\.net)\b/u', $m) || preg_match('/\b(dominio|dominios)\b/u', $expanded)) {
            return 'dominios';
        }
        if (preg_match('/\b(hosting|alojamiento|servidor|hospedaje|plan|sitio)\b/u', $m) || preg_match('/\b(hosting|alojamiento|servidor)\b/u', $expanded)) {
            return 'hosting';
        }
        return 'vencimientos';
    }

    if (preg_match('/\b(pago|pagos|adeudo|adeudos|debo|pendiente|pendientes|cobro|factura|comprobante|cu[aá]nto debo|tengo que pagar)\b/u', $m)) {
        return 'pagos';
    }

    if (preg_match('/\b(mis servicios|que servicios tengo|qué servicios tengo|servicios contratados|resumen de servicios)\b/u', $m)) {
        return 'servicios';
    }

    if (preg_match('/\b(mi hosting|mis hosting|mis planes|plan de hosting|cu[aá]ntos hosting|mi sitio|mi pagina|mi página|sitio caido|sitio caído|cpanel)\b/u', $m)) {
        return 'hosting';
    }

    if (preg_match('/\b(mis dominios|mi dominio|cu[aá]ntos dominios|lista de dominios)\b/u', $m)) {
        return 'dominios';
    }

    return null;
}

/**
 * @return array{matched:bool,respuesta?:string,intencion?:string,escalar?:bool,guardar_contexto?:array<string,mixed>}
 */
function cw_chatbot_try_live_response(
    mysqli $conn,
    int $clienteId,
    string $mensaje,
    array $context,
    bool $isFollowup
): array {
    if ($clienteId <= 0) {
        return ['matched' => false];
    }

    $topic = cw_chatbot_live_detect_topic($mensaje, $context, $isFollowup);
    if ($topic === null) {
        return ['matched' => false];
    }

    if (cw_chatbot_live_is_howto_only(mb_strtolower(trim($mensaje))) && !cw_chatbot_live_has_account_context(mb_strtolower(trim($mensaje)))) {
        return ['matched' => false];
    }

    $data = cw_chat_client_context($conn, $clienteId);
    if (empty($data['cliente'])) {
        return ['matched' => false];
    }

    $ordinal = cw_chatbot_extract_ordinal($mensaje);

    switch ($topic) {
        case 'pagos':
            return cw_chatbot_live_build_pagos($data, $context, $ordinal);
        case 'hosting':
            return cw_chatbot_live_build_hosting_vence($data, $ordinal);
        case 'dominios':
            return cw_chatbot_live_build_dominios_vence($data, $ordinal);
        case 'vencimientos':
            return cw_chatbot_live_build_vencimientos($data);
        case 'servicios':
            return cw_chatbot_live_build_servicios($data);
        default:
            return ['matched' => false];
    }
}

/**
 * @param array<string,mixed> $data
 * @return array{matched:bool,respuesta:string,intencion:string,escalar:bool,guardar_contexto:array<string,mixed>}
 */
function cw_chatbot_live_build_pagos(array $data, array $context, ?int $ordinal): array
{
    $pendientes = $data['pagos_detalle'] ?? [];
    $count = count($pendientes);

    if ($count === 0) {
        return cw_chatbot_live_result(
            'pagos',
            "Revisé tu cuenta: **no tienes pagos pendientes**. Tu historial está en **Mis pagos**.",
            []
        );
    }

    if ($ordinal !== null && $ordinal >= 1 && $ordinal <= $count) {
        $p = $pendientes[$ordinal - 1];
        $texto = cw_chatbot_live_format_pago_line($p, $ordinal);
        $texto .= "\n\nPuedes pagarlo desde **Mis pagos** en tu panel. Te recomendamos liquidarlo lo antes posible para evitar suspensión del servicio.";
        return cw_chatbot_live_result('pagos', $texto, [
            'ultima_lista_tipo' => 'pagos',
            'ultima_lista_total' => $count,
        ]);
    }

    $lines = ["Tienes **{$count} pago" . ($count === 1 ? '' : 's') . " pendiente" . ($count === 1 ? '' : 's') . "**:\n"];
    foreach ($pendientes as $i => $p) {
        $lines[] = cw_chatbot_live_format_pago_line($p, $i + 1);
    }
    $lines[] = "\nPuedes pagar desde **Mis pagos** en tu panel. Si un servicio ya venció, liquídalo cuanto antes para reactivarlo.";
    if ($count > 1) {
        $lines[] = "Si quieres detalle de uno en particular, dime «el primero», «el segundo», etc.";
    }

    return cw_chatbot_live_result('pagos', implode("\n", $lines), [
        'ultima_lista_tipo' => 'pagos',
        'ultima_lista_total' => $count,
    ]);
}

/**
 * @param array<string,mixed> $p
 */
function cw_chatbot_live_format_pago_line(array $p, int $num): string
{
    $nombre = trim((string) ($p['nombre_servicio'] ?? $p['concepto'] ?? 'Servicio'));
    $monto = cw_chatbot_format_monto($p['monto'] ?? 0, $p['currency'] ?? 'MXN');
    $limite = trim((string) ($p['fecha_limite_pago'] ?? ''));
    if ($limite !== '' && $limite !== '0000-00-00') {
        $vence = 'fecha límite de pago: ' . cw_chatbot_dias_restantes_cliente($limite);
    } else {
        $vence = 'vigencia del servicio: ' . cw_chatbot_dias_restantes_cliente((string) ($p['fecha_vigencia_servicio'] ?? ''));
    }

    return "{$num}. **{$nombre}** — {$monto} — {$vence}";
}

/** Vigencia calculada desde fecha_pago (hosting / dominios) */
function cw_chatbot_vigencia_desde_fecha_pago(?string $fechaPago, string $tabla = ''): string
{
    if ($fechaPago === null || $fechaPago === '' || $fechaPago === '0000-00-00' || $fechaPago === '0001-01-01') {
        return 'sin fecha de vigencia registrada';
    }
    return cw_chatbot_dias_restantes_cliente($fechaPago);
}

/** Dominio gestionado por ConlineWeb (registrado = 1 en tabla dominios) */
function cw_chatbot_dominio_es_gestionado(array $d): bool
{
    return (int) ($d['registrado'] ?? 0) === 1;
}

function cw_chatbot_dominio_externo_texto(string $nombre, bool $detalle = false): string
{
    $texto = '**' . $nombre . '** — No está registrado ni gestionado por ConlineWeb. **No puedes renovarlo aquí.** '
        . 'Verifica la vigencia y la renovación con tu **proveedor de dominio externo**.';
    if ($detalle) {
        $texto .= "\n   Si deseas que ConlineWeb lo administre, revisa la opción de transferencia en la sección **Dominios** de tu panel.";
    }
    return $texto;
}

/**
 * @param array<string,mixed> $d
 */
function cw_chatbot_dominio_gestionado_detalle(array $d, bool $incluirRenovacion = true): string
{
    $nombre = trim((string) ($d['dominio'] ?? 'Dominio'));
    $texto = '**' . $nombre . '**:' . "\n" . cw_chatbot_vigencia_desde_fecha_pago((string) ($d['fecha_pago'] ?? ''));
    if ((int) ($d['estatus'] ?? 0) !== 1) {
        $texto .= "\n\n⚠️ Este dominio está **pendiente de pago**. Liquídalo en **Mis pagos**.";
    } elseif ($incluirRenovacion) {
        $texto .= "\n\nPuedes renovarlo desde **Mis pagos**. Te recomendamos hacerlo al menos **5 días antes** del vencimiento.";
    }
    return $texto;
}

/**
 * @param array<string,mixed> $d
 */
function cw_chatbot_dominio_linea_lista(array $d, int $num): string
{
    $nombre = trim((string) ($d['dominio'] ?? 'Dominio'));
    if (!cw_chatbot_dominio_es_gestionado($d)) {
        return $num . '. ' . cw_chatbot_dominio_externo_texto($nombre);
    }
    $line = $num . '. **' . $nombre . '** — ' . cw_chatbot_vigencia_desde_fecha_pago((string) ($d['fecha_pago'] ?? ''));
    if ((int) ($d['estatus'] ?? 0) !== 1) {
        $line .= ' — ⚠️ pendiente de pago';
    }
    return $line;
}

/**
 * @param array<int,array<string,mixed>> $items
 * @return array{gestionados:array<int,array<string,mixed>>,externos:array<int,array<string,mixed>>}
 */
function cw_chatbot_dominios_clasificar(array $items): array
{
    $gestionados = [];
    $externos = [];
    foreach ($items as $d) {
        if (cw_chatbot_dominio_es_gestionado($d)) {
            $gestionados[] = $d;
        } else {
            $externos[] = $d;
        }
    }
    return ['gestionados' => $gestionados, 'externos' => $externos];
}

/**
 * @param array<string,mixed> $data
 */
function cw_chatbot_live_build_hosting_vence(array $data, ?int $ordinal): array
{
    $items = $data['hosting'] ?? [];
    $count = count($items);

    if ($count === 0) {
        return cw_chatbot_live_result(
            'hosting',
            'No encontré servicios de hosting en tu cuenta. Si acabas de contratar, puede tardar unas horas en aparecer. Revisa la sección **Hosting** en tu panel.',
            ['ultima_lista_tipo' => 'hosting']
        );
    }

    if ($ordinal !== null && $ordinal >= 1 && $ordinal <= $count) {
        $h = $items[$ordinal - 1];
        $dominio = trim((string) ($h['dominio'] ?? 'tu hosting'));
        $texto = 'Tu hosting **' . $dominio . '**:' . "\n"
            . cw_chatbot_vigencia_desde_fecha_pago((string) ($h['fecha_pago'] ?? ''));
        if ((int) ($h['estatus'] ?? 0) !== 1) {
            $texto .= "\n\n⚠️ Este servicio está **pendiente de pago**. Liquídalo en **Mis pagos**.";
        } else {
            $texto .= "\n\nPuedes renovar desde **Mis pagos** en tu panel. Te recomendamos hacerlo al menos **5 días antes** del vencimiento.";
        }
        return cw_chatbot_live_result('hosting', $texto, [
            'ultima_lista_tipo' => 'hosting',
            'ultima_lista_total' => $count,
        ]);
    }

    if ($count === 1) {
        $h = $items[0];
        $dominio = trim((string) ($h['dominio'] ?? 'tu hosting'));
        $texto = 'Tu hosting **' . $dominio . '**:' . "\n"
            . cw_chatbot_vigencia_desde_fecha_pago((string) ($h['fecha_pago'] ?? ''));
        if ((int) ($h['estatus'] ?? 0) !== 1) {
            $texto .= "\n\n⚠️ Este servicio está **pendiente de pago**. Liquídalo en **Mis pagos**.";
        } else {
            $texto .= "\n\nRenueva desde **Mis pagos**. Te recomendamos pagar al menos **5 días antes** del vencimiento.";
        }
        return cw_chatbot_live_result('hosting', $texto, [
            'ultima_lista_tipo' => 'hosting',
            'ultima_lista_total' => 1,
        ]);
    }

    $lines = ["Estos son tus servicios de **hosting** y su vigencia:\n"];
    foreach ($items as $i => $h) {
        $dominio = trim((string) ($h['dominio'] ?? 'Hosting'));
        $lines[] = ($i + 1) . '. **' . $dominio . '** — ' . cw_chatbot_vigencia_desde_fecha_pago((string) ($h['fecha_pago'] ?? ''), 'hosting');
        if ((int) ($h['estatus'] ?? 0) !== 1) {
            $lines[count($lines) - 1] .= ' — ⚠️ pendiente de pago';
        }
    }
    $lines[] = "\nSi quieres detalle de uno, dime «el primero», «el segundo», etc.";
    $lines[] = 'Renueva desde **Mis pagos** en tu panel.';

    return cw_chatbot_live_result('hosting', implode("\n", $lines), [
        'ultima_lista_tipo' => 'hosting',
        'ultima_lista_total' => $count,
    ]);
}

/**
 * @param array<string,mixed> $data
 */
function cw_chatbot_live_build_dominios_vence(array $data, ?int $ordinal): array
{
    $items = $data['dominios'] ?? [];
    $count = count($items);

    if ($count === 0) {
        return cw_chatbot_live_result(
            'dominios',
            'No encontré dominios en tu cuenta. Revisa la sección **Dominios** en tu panel o contáctanos si esperabas ver alguno.',
            ['ultima_lista_tipo' => 'dominios']
        );
    }

    $clasificados = cw_chatbot_dominios_clasificar($items);
    $gestionados = $clasificados['gestionados'];
    $externos = $clasificados['externos'];

    if ($ordinal !== null && $ordinal >= 1 && $ordinal <= $count) {
        $d = $items[$ordinal - 1];
        $nombre = trim((string) ($d['dominio'] ?? 'tu dominio'));
        if (!cw_chatbot_dominio_es_gestionado($d)) {
            $texto = 'El dominio **' . $nombre . '** no está registrado ni gestionado por ConlineWeb.' . "\n\n"
                . cw_chatbot_dominio_externo_texto($nombre, true);
        } else {
            $texto = cw_chatbot_dominio_gestionado_detalle($d);
        }
        return cw_chatbot_live_result('dominios', $texto, [
            'ultima_lista_tipo' => 'dominios',
            'ultima_lista_total' => $count,
        ]);
    }

    if ($count === 1) {
        $d = $items[0];
        if (!cw_chatbot_dominio_es_gestionado($d)) {
            $nombre = trim((string) ($d['dominio'] ?? 'tu dominio'));
            $texto = 'El dominio **' . $nombre . '** no está registrado con ConlineWeb.' . "\n\n"
                . cw_chatbot_dominio_externo_texto($nombre, true);
        } else {
            $texto = cw_chatbot_dominio_gestionado_detalle($d);
        }
        return cw_chatbot_live_result('dominios', $texto, [
            'ultima_lista_tipo' => 'dominios',
            'ultima_lista_total' => 1,
        ]);
    }

    if (count($gestionados) === 1 && $ordinal === null && count($externos) > 0) {
        $d = $gestionados[0];
        $texto = cw_chatbot_dominio_gestionado_detalle($d, false);
        $texto .= "\n\n(Tienes **" . count($externos) . ' dominio' . (count($externos) === 1 ? '' : 's')
            . ' con otro proveedor** que no puedes renovar aquí. Pregúntame por «dominios externos» si quieres verlos.)';
        return cw_chatbot_live_result('dominios', $texto, [
            'ultima_lista_tipo' => 'dominios',
            'ultima_lista_total' => $count,
        ]);
    }

    if (count($gestionados) === 0) {
        $lines = ["Los dominios en tu cuenta **no están registrados con ConlineWeb**. No podemos gestionar su vigencia ni renovación desde aquí:\n"];
        foreach ($externos as $i => $d) {
            $lines[] = cw_chatbot_dominio_linea_lista($d, $i + 1);
        }
        $lines[] = "\nPara vigencia y renovación, verifica con el **proveedor externo** donde registraste cada dominio.";
        return cw_chatbot_live_result('dominios', implode("\n", $lines), [
            'ultima_lista_tipo' => 'dominios',
            'ultima_lista_total' => $count,
        ]);
    }

    $lines = [];
    if (count($gestionados) > 0) {
        $lines[] = '**Dominios registrados con ConlineWeb** (puedes renovar en **Mis pagos**):';
        foreach ($gestionados as $i => $d) {
            $lines[] = cw_chatbot_dominio_linea_lista($d, $i + 1);
        }
    }
    if (count($externos) > 0) {
        $lines[] = "\n**Dominios con otro proveedor** (no renovables aquí):";
        $offset = count($gestionados);
        foreach ($externos as $i => $d) {
            $lines[] = cw_chatbot_dominio_linea_lista($d, $offset + $i + 1);
        }
        $lines[] = "\nLos dominios externos deben renovarse con su proveedor. ConlineWeb no los gestiona.";
    }
    $lines[] = "\nSi quieres detalle de uno, dime «el primero», «el segundo», etc.";

    return cw_chatbot_live_result('dominios', implode("\n", $lines), [
        'ultima_lista_tipo' => 'dominios',
        'ultima_lista_total' => $count,
    ]);
}

/**
 * @param array<string,mixed> $data
 */
function cw_chatbot_live_build_hosting(array $data, array $context, ?int $ordinal): array
{
    $items = $data['hosting'] ?? [];
    $count = count($items);

    if ($count === 0) {
        return cw_chatbot_live_result(
            'hosting',
            'No encontré servicios de hosting activos en tu cuenta. Si acabas de contratar, puede tardar unas horas en aparecer. También puedes revisar la sección **Hosting** en tu panel.',
            []
        );
    }

    if ($ordinal !== null && $ordinal >= 1 && $ordinal <= $count) {
        $h = $items[$ordinal - 1];
        $texto = cw_chatbot_live_format_hosting_line($h, $ordinal, true);
        $texto .= "\n\nAccede a cPanel desde **Hosting** o **Mis sitios web** en tu panel.";
        return cw_chatbot_live_result('hosting', $texto, [
            'ultima_lista_tipo' => 'hosting',
            'ultima_lista_total' => $count,
        ]);
    }

    $lines = ["Estos son tus servicios de **hosting** ({$count}):\n"];
    foreach ($items as $i => $h) {
        $lines[] = cw_chatbot_live_format_hosting_line($h, $i + 1, false);
    }
    $lines[] = "\nLos datos de cPanel y FTP están en la sección **Hosting** de tu panel.";
    if ($count > 1) {
        $lines[] = 'Pregúntame por «el primero», «el segundo», etc. si necesitas detalle de uno.';
    }

    return cw_chatbot_live_result('hosting', implode("\n", $lines), [
        'ultima_lista_tipo' => 'hosting',
        'ultima_lista_total' => $count,
    ]);
}

/**
 * @param array<string,mixed> $h
 */
function cw_chatbot_live_format_hosting_line(array $h, int $num, bool $detalle): string
{
    $dominio = trim((string) ($h['dominio'] ?? 'Sin dominio'));
    $plan = trim((string) ($h['nombre_plan'] ?? ''));
    $vence = cw_chatbot_vigencia_desde_fecha_pago((string) ($h['fecha_pago'] ?? ''), 'hosting');
    $activo = (int) ($h['estatus'] ?? 0) === 1;

    $line = "{$num}. **{$dominio}**";
    if ($plan !== '') {
        $line .= " ({$plan})";
    }
    $line .= " — {$vence}";
    if (!$activo) {
        $line .= ' — ⚠️ pendiente de pago';
    }
    if ($detalle && !empty($h['nom_host'])) {
        $line .= "\n   Servidor: " . $h['nom_host'];
    }
    return $line;
}

/**
 * @param array<string,mixed> $data
 */
function cw_chatbot_live_build_dominios(array $data, array $context, ?int $ordinal): array
{
    $items = $data['dominios'] ?? [];
    $count = count($items);

    if ($count === 0) {
        return cw_chatbot_live_result(
            'dominios',
            'No encontré dominios registrados en tu cuenta. Revisa la sección **Dominios** en tu panel o contáctanos si esperabas ver alguno.',
            []
        );
    }

    if ($ordinal !== null && $ordinal >= 1 && $ordinal <= $count) {
        $d = $items[$ordinal - 1];
        $nombre = trim((string) ($d['dominio'] ?? 'Dominio'));
        if (!cw_chatbot_dominio_es_gestionado($d)) {
            $texto = cw_chatbot_dominio_externo_texto($nombre, true);
        } else {
            $texto = cw_chatbot_live_format_dominio_line($d, $ordinal, true);
            $texto .= "\n\nPuedes gestionar DNS desde **Dominios** en tu panel o desde cPanel → Zone Editor.";
        }
        return cw_chatbot_live_result('dominios', $texto, [
            'ultima_lista_tipo' => 'dominios',
            'ultima_lista_total' => $count,
        ]);
    }

    $clasificados = cw_chatbot_dominios_clasificar($items);
    $gestionados = $clasificados['gestionados'];
    $externos = $clasificados['externos'];

    if (count($gestionados) === 0) {
        $lines = ["Los dominios en tu cuenta **no están registrados con ConlineWeb**. No podemos gestionarlos ni renovarlos desde aquí:\n"];
        foreach ($externos as $i => $d) {
            $lines[] = cw_chatbot_dominio_linea_lista($d, $i + 1);
        }
        $lines[] = "\nPara vigencia, DNS o renovación, verifica con el **proveedor externo** de cada dominio.";
        return cw_chatbot_live_result('dominios', implode("\n", $lines), [
            'ultima_lista_tipo' => 'dominios',
            'ultima_lista_total' => $count,
        ]);
    }

    $lines = [];
    if (count($gestionados) > 0) {
        $lines[] = '**Dominios registrados con ConlineWeb:**';
        foreach ($gestionados as $i => $d) {
            $lines[] = cw_chatbot_live_format_dominio_line($d, $i + 1, false);
        }
    }
    if (count($externos) > 0) {
        $lines[] = "\n**Dominios con otro proveedor** (no gestionados por ConlineWeb):";
        $offset = count($gestionados);
        foreach ($externos as $i => $d) {
            $lines[] = cw_chatbot_dominio_linea_lista($d, $offset + $i + 1);
        }
    }
    $lines[] = "\nAdministra DNS y renovaciones de dominios con ConlineWeb en la sección **Dominios** del panel.";
    if ($count > 1) {
        $lines[] = 'Pregúntame por «el primero», «el segundo», etc. para más detalle.';
    }

    return cw_chatbot_live_result('dominios', implode("\n", $lines), [
        'ultima_lista_tipo' => 'dominios',
        'ultima_lista_total' => $count,
    ]);
}
/**
 * @param array<string,mixed> $d
 */
function cw_chatbot_live_format_dominio_line(array $d, int $num, bool $detalle): string
{
    $nombre = trim((string) ($d['dominio'] ?? 'Dominio'));
    if (!cw_chatbot_dominio_es_gestionado($d)) {
        $line = $num . '. ' . cw_chatbot_dominio_externo_texto($nombre);
        if ($detalle) {
            $line .= "\n   No gestionado por ConlineWeb — verifica con tu proveedor externo.";
        }
        return $line;
    }
    $line = $num . '. **' . $nombre . '** — ' . cw_chatbot_vigencia_desde_fecha_pago((string) ($d['fecha_pago'] ?? ''));
    if ((int) ($d['estatus'] ?? 0) !== 1) {
        $line .= ' — ⚠️ pendiente de pago';
    }
    if ($detalle) {
        $line .= "\n   Gestionado por ConlineWeb — puedes renovar en **Mis pagos**.";
    }
    return $line;
}

/**
 * @param array<string,mixed> $data
 */
function cw_chatbot_live_build_vencimientos(array $data): array
{
    $lines = ["Esta es la vigencia de tus servicios:\n"];
    $hay = false;

    foreach ($data['hosting'] ?? [] as $h) {
        $dominio = trim((string) ($h['dominio'] ?? 'Hosting'));
        $lines[] = '• **Hosting ' . $dominio . '** — ' . cw_chatbot_vigencia_desde_fecha_pago((string) ($h['fecha_pago'] ?? ''), 'hosting');
        $hay = true;
    }
    foreach ($data['dominios'] ?? [] as $d) {
        $nombre = trim((string) ($d['dominio'] ?? 'Dominio'));
        if (!cw_chatbot_dominio_es_gestionado($d)) {
            $lines[] = '• ' . cw_chatbot_dominio_externo_texto($nombre);
        } else {
            $lines[] = '• **Dominio ' . $nombre . '** — ' . cw_chatbot_vigencia_desde_fecha_pago((string) ($d['fecha_pago'] ?? ''));
            $hay = true;
        }
    }

    if (!$hay) {
        return cw_chatbot_live_result(
            'vencimientos',
            'No encontré servicios con fecha de vencimiento en tu cuenta. Si esperabas ver alguno, revisa **Hosting** y **Dominios** en tu panel.',
            []
        );
    }

    $pendientes = (int) ($data['pagos_pendientes'] ?? 0);
    if ($pendientes > 0) {
        $lines[] = "\nTambién tienes **{$pendientes} pago" . ($pendientes === 1 ? '' : 's') . ' pendiente' . ($pendientes === 1 ? '' : 's') . '**. Puedes liquidarlos en **Mis pagos**.';
    }
    $lines[] = "\nTe recomendamos renovar al menos **5 días antes** del vencimiento para evitar interrupciones.";

    return cw_chatbot_live_result('vencimientos', implode("\n", $lines), [
        'ultima_lista_tipo' => 'vencimientos',
    ]);
}

/**
 * @param array<string,mixed> $data
 */
function cw_chatbot_live_build_servicios(array $data): array
{
    $hosting = count($data['hosting'] ?? []);
    $clasificados = cw_chatbot_dominios_clasificar($data['dominios'] ?? []);
    $domGestionados = count($clasificados['gestionados']);
    $domExternos = count($clasificados['externos']);
    $pendientes = (int) ($data['pagos_pendientes'] ?? 0);
    $tickets = (int) ($data['tickets_abiertos'] ?? 0);
    $ticketsPend = (int) ($data['tickets_pendientes'] ?? 0);

    $lines = ["Resumen de tu cuenta:\n"];
    $lines[] = "• **Hosting:** {$hosting} servicio" . ($hosting === 1 ? '' : 's');
    $lines[] = "• **Dominios con ConlineWeb:** {$domGestionados}";
    if ($domExternos > 0) {
        $lines[] = "• **Dominios con otro proveedor:** {$domExternos} (no renovables aquí)";
    }
    $lines[] = "• **Pagos pendientes:** {$pendientes}";
    $lines[] = "• **Tickets abiertos:** {$tickets}" . ($ticketsPend > 0 ? " ({$ticketsPend} pendientes)" : '');

    if ($pendientes > 0) {
        $lines[] = "\nTienes pagos por liquidar. Ve a **Mis pagos** para ver montos y fechas.";
    }

    $proximos = cw_chatbot_live_proximos_vencimientos($data);
    if ($proximos !== '') {
        $lines[] = "\n**Próximos vencimientos:**\n" . $proximos;
    }

    $lines[] = "\nTambién puedo listar tus tickets («mis tickets») o crear uno («crear ticket»).";

    return cw_chatbot_live_result('servicios', implode("\n", $lines), [
        'ultima_lista_tipo' => 'servicios',
    ]);
}

/**
 * @param array<string,mixed> $data
 */
function cw_chatbot_live_proximos_vencimientos(array $data): string
{
    $items = [];
    foreach ($data['hosting'] ?? [] as $h) {
        $fecha = (string) ($h['fecha_pago'] ?? '');
        if ($fecha !== '' && $fecha !== '0000-00-00' && function_exists('cliente_dias_vencimiento')) {
            $dias = cliente_dias_vencimiento($fecha);
            if ($dias !== null && $dias <= 60) {
                $items[] = ['dias' => $dias, 'label' => 'Hosting ' . ($h['dominio'] ?? ''), 'fecha' => $fecha, 'tabla' => 'hosting'];
            }
        }
    }
    foreach ($data['dominios'] ?? [] as $d) {
        if (!cw_chatbot_dominio_es_gestionado($d)) {
            continue;
        }
        $fecha = (string) ($d['fecha_pago'] ?? '');
        if ($fecha !== '' && $fecha !== '0000-00-00' && $fecha !== '0001-01-01' && function_exists('cliente_dias_vencimiento')) {
            $dias = cliente_dias_vencimiento($fecha);
            if ($dias !== null && $dias <= 60) {
                $items[] = ['dias' => $dias, 'label' => 'Dominio ' . ($d['dominio'] ?? ''), 'fecha' => $fecha, 'tabla' => 'dominios'];
            }
        }
    }
    if ($items === []) {
        return '';
    }
    usort($items, static fn ($a, $b) => $a['dias'] <=> $b['dias']);
    $lines = [];
    foreach (array_slice($items, 0, 5) as $it) {
        $lines[] = '• **' . $it['label'] . '** — ' . cw_chatbot_vigencia_desde_fecha_pago($it['fecha'], (string) ($it['tabla'] ?? 'servicio'));
    }
    return implode("\n", $lines);
}

/**
 * @param array<string,mixed> $extraContext
 * @return array{matched:bool,respuesta:string,intencion:string,escalar:bool,guardar_contexto:array<string,mixed>,origen:string}
 */
function cw_chatbot_live_result(string $intencion, string $respuesta, array $extraContext): array
{
    $ctx = array_merge([
        'ultima_intencion' => $intencion,
        'ultimo_conocimiento_id' => 0,
        'ultimo_titulo' => 'datos de tu cuenta',
        'sin_match_consecutivos' => 0,
        'ofrecio_asesor' => false,
        'esperando_confirmacion' => false,
        'ultima_lista_tipo' => $extraContext['ultima_lista_tipo'] ?? null,
    ], $extraContext);

    return [
        'matched' => true,
        'respuesta' => $respuesta,
        'intencion' => $intencion,
        'conocimiento_id' => 0,
        'escalar' => false,
        'guardar_contexto' => $ctx,
        'origen' => 'datos_cliente',
    ];
}

/**
 * @return array<int,array<string,mixed>>
 */
function cw_chatbot_collect_expiry_items(array $data, int $withinDays = 7): array
{
    $items = [];

    foreach ($data['hosting'] ?? [] as $h) {
        $row = cw_chatbot_expiry_item_from_row('hosting', (string) ($h['dominio'] ?? 'Hosting'), (string) ($h['fecha_pago'] ?? ''), $withinDays);
        if ($row) {
            $row['id'] = 'h' . (int) ($h['id'] ?? 0);
            $items[] = $row;
        }
    }
    foreach ($data['dominios'] ?? [] as $d) {
        if (!cw_chatbot_dominio_es_gestionado($d)) {
            continue;
        }
        $row = cw_chatbot_expiry_item_from_row('dominio', (string) ($d['dominio'] ?? 'Dominio'), (string) ($d['fecha_pago'] ?? ''), $withinDays);
        if ($row) {
            $row['id'] = 'd' . (int) ($d['id'] ?? 0);
            $items[] = $row;
        }
    }

    usort($items, static fn ($a, $b) => ($a['dias'] ?? 999) <=> ($b['dias'] ?? 999));
    return $items;
}

/**
 * @return array<string,mixed>|null
 */
function cw_chatbot_expiry_item_from_row(string $tipo, string $nombre, string $fecha, int $withinDays): ?array
{
    if (!function_exists('cliente_dias_vencimiento')) {
        return null;
    }
    $dias = cliente_dias_vencimiento($fecha);
    if ($dias === null || $dias > $withinDays) {
        return null;
    }

    $label = $tipo === 'hosting' ? 'Hosting' : 'Dominio';
    if ($dias < 0) {
        $n = abs($dias);
        $texto = 'venció hace **' . $n . ' día' . ($n === 1 ? '' : 's') . '** (' . cw_chatbot_format_fecha($fecha) . ')';
        $urgencia = 'vencido';
    } elseif ($dias === 0) {
        $texto = 'vence **hoy** (' . cw_chatbot_format_fecha($fecha) . ')';
        $urgencia = 'hoy';
    } else {
        $texto = 'vence en **' . $dias . ' día' . ($dias === 1 ? '' : 's') . '** (' . cw_chatbot_format_fecha($fecha) . ')';
        $urgencia = 'proximo';
    }

    return [
        'tipo' => $tipo,
        'nombre' => $nombre,
        'fecha' => $fecha,
        'dias' => $dias,
        'label' => $label,
        'texto' => $texto,
        'urgencia' => $urgencia,
    ];
}

/**
 * @return array{show:bool,message?:string,items?:array<int,array<string,mixed>>,fingerprint?:string,pendientes?:int}
 */
function cw_chatbot_build_expiry_alert_payload(mysqli $conn, int $clienteId, int $withinDays = 7): array
{
    $empty = ['show' => false, 'items' => [], 'pendientes' => 0];
    if ($clienteId <= 0) {
        return $empty;
    }

    $data = cw_chat_client_context($conn, $clienteId);
    if (empty($data['cliente'])) {
        return $empty;
    }

    $items = cw_chatbot_collect_expiry_items($data, $withinDays);
    $pendientes = (int) ($data['pagos_pendientes'] ?? 0);

    if ($items === [] && $pendientes === 0) {
        return $empty;
    }

    $lines = [];
    if ($items !== []) {
        $lines[] = '**Aviso:** Tienes servicios próximos a vencer o ya vencidos:';
        foreach ($items as $it) {
            $lines[] = '• **' . $it['label'] . ' ' . $it['nombre'] . '** — ' . $it['texto'];
        }
    }

    if ($pendientes > 0) {
        $lines[] = '• Tienes **' . $pendientes . ' pago' . ($pendientes === 1 ? '' : 's') . ' pendiente' . ($pendientes === 1 ? '' : 's') . '** en tu cuenta.';
    }

    $lines[] = "\nRenueva desde **Mis pagos** en tu panel. Si quieres detalle, pregúntame: «¿cuándo vence mi hosting?» o «¿tengo pagos pendientes?».";

    $fpParts = [];
    foreach ($items as $it) {
        $fpParts[] = ($it['id'] ?? '') . ':' . ($it['dias'] ?? '');
    }
    if ($pendientes > 0) {
        $fpParts[] = 'pagos:' . $pendientes;
    }

    return [
        'show' => true,
        'message' => implode("\n", $lines),
        'items' => $items,
        'pendientes' => $pendientes,
        'fingerprint' => substr(md5(implode('|', $fpParts)), 0, 12),
    ];
}
