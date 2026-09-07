<?php
/**
 * Plantillas HTML de correo — módulo de pagos.
 */
$pagoEmailBrandCandidates = [
    dirname(__DIR__, 2) . '/includes/cw_email_brand.php',
    dirname(__DIR__) . '/includes/cw_email_brand.php',
    '/home/conlineweb/includes/cw_email_brand.php',
];
foreach ($pagoEmailBrandCandidates as $pagoEmailBrandFile) {
    if (is_file($pagoEmailBrandFile)) {
        require_once $pagoEmailBrandFile;
        break;
    }
}
unset($pagoEmailBrandCandidates, $pagoEmailBrandFile);

if (!function_exists('cw_email_service_rows')) {
    /**
     * Fallback local si el includes compartido en servidor no está actualizado.
     *
     * @param list<array{tipo?:string,nombre?:string,monto?:float|int|string,nueva_fecha?:string,currency?:string}> $servicios
     */
    function cw_email_service_rows(array $servicios): string
    {
        $h = static function (?string $value): string {
            if (function_exists('cw_email_h')) {
                return cw_email_h($value);
            }

            return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        };
        $p = static function (string $html, int $marginBottom = 16) use ($h): string {
            if (function_exists('cw_email_p')) {
                return cw_email_p($html, $marginBottom);
            }

            return '<p style="margin:0 0 ' . $marginBottom . 'px;font-size:15px;line-height:1.6;color:#334155;">' . $html . '</p>';
        };

        if ($servicios === []) {
            return $p('<span style="color:#64748b;">Sin detalle de servicios.</span>', 0);
        }

        $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px;border-collapse:collapse;">';
        foreach ($servicios as $svc) {
            if (!is_array($svc)) {
                continue;
            }
            $tipo = trim((string) ($svc['tipo'] ?? 'Servicio'));
            $nombre = trim((string) ($svc['nombre'] ?? ''));
            $currency = strtoupper(trim((string) ($svc['currency'] ?? 'MXN')));
            $monto = $svc['monto'] ?? null;
            $montoTxt = ($monto !== null && $monto !== '')
                ? '$' . number_format((float) $monto, 2) . ' ' . $h($currency)
                : '';
            $fecha = trim((string) ($svc['nueva_fecha'] ?? ''));
            $fechaTxt = '';
            if ($fecha !== '') {
                $ts = strtotime($fecha);
                $fechaTxt = $ts ? 'Vigente hasta ' . date('d/m/Y', $ts) : 'Vigente hasta ' . $h($fecha);
            }

            $meta = array_filter([$montoTxt, $fechaTxt], static fn ($v) => $v !== '');
            $metaHtml = $meta !== []
                ? '<p style="margin:4px 0 0;font-size:13px;color:#64748b;line-height:1.45;">' . implode(' · ', $meta) . '</p>'
                : '';

            $html .= '<tr><td style="padding:12px 14px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;">'
                . '<p style="margin:0;font-size:14px;font-weight:700;color:#000147;">'
                . $h($tipo . ($nombre !== '' ? ': ' . $nombre : ''))
                . '</p>'
                . $metaHtml
                . '</td></tr>'
                . '<tr><td style="height:8px;font-size:0;line-height:0;">&nbsp;</td></tr>';
        }
        $html .= '</table>';

        return $html;
    }
}

function pago_email_logo_url(): string
{
    return cw_email_logo_url();
}

function pago_email_wrap(string $titulo, string $contenido, string $pie = ''): string
{
    if ($pie === '') {
        $pie = 'Este mensaje fue enviado automáticamente. Por favor, no respondas a este correo.';
    }

    return cw_email_wrap([
        'title' => $titulo,
        'content' => $contenido,
        'footer' => $pie,
        'badge' => 'Pagos',
        'badge_variant' => 'neutral',
    ]);
}

function pago_email_summary_card(string $html): string
{
    return cw_email_card($html);
}

function pago_email_cta(string $url, string $texto = 'Realizar pago'): string
{
    return cw_email_cta($url, $texto, 'primary');
}

/** Texto de respaldo del enlace (evita URLs enormes que rompen el layout del correo) */
function pago_email_link_fallback(string $url): string
{
    $url = trim($url);
    if ($url === '' || strpos($url, '#pago') !== false || strpos($url, '#enlace-de-pago') !== false) {
        return '';
    }
    // Stripe/checkout: no volcar la URL completa en el cuerpo (puede verse “cortada”)
    if (strlen($url) > 120) {
        return cw_email_p(
            '<span style="font-size:12px;color:#94a3b8;line-height:1.5;">Si el botón no funciona, solicite un nuevo enlace de pago a soporte o responda este correo.</span>',
            20
        );
    }

    return cw_email_p(
        '<span style="font-size:12px;color:#94a3b8;word-break:break-word;">Si el botón no funciona: ' . cw_email_h($url) . '</span>',
        20
    );
}

/** Texto intro dinámico según tipo de cobro (hosting / dominio / genérico). */
function pago_email_intro_pendiente(
    string $tipoServicio,
    string $servicioNombre,
    string $dominioNombre,
    string $fechaLimite
): string {
    $tipo = strtolower(trim($tipoServicio));
    if ($tipo === '1' || $tipo === 'hosting') {
        $tipo = 'hosting';
    } elseif ($tipo === '2' || $tipo === 'dominio') {
        $tipo = 'dominio';
    } else {
        // Inferir por nombres si no vino tipo explícito
        $hay = function_exists('mb_strtolower')
            ? mb_strtolower($servicioNombre . ' ' . $dominioNombre)
            : strtolower($servicioNombre . ' ' . $dominioNombre);
        if (strpos($hay, 'hosting') !== false || strpos($hay, 'alojamiento') !== false) {
            $tipo = 'hosting';
        } elseif (strpos($hay, 'dominio') !== false) {
            $tipo = 'dominio';
        } else {
            $tipo = '';
        }
    }

    $fecha = trim($fechaLimite);
    if ($fecha === '' || $fecha === '0000-00-00') {
        $fecha = 'por definir';
    }

    if ($tipo === 'hosting') {
        $servicio = trim($servicioNombre);
        if ($servicio === '') {
            $servicio = 'tu hosting';
        }
        $dom = trim($dominioNombre);
        $domHtml = $dom !== ''
            ? ' asociado al dominio <strong>' . cw_email_h($dom) . '</strong>'
            : '';

        return 'Te recordamos que tienes un pago pendiente de la renovación de tu hosting <strong>'
            . cw_email_h($servicio) . '</strong>' . $domHtml
            . ', con vencimiento el <strong>' . cw_email_h($fecha) . '</strong>. '
            . 'Te pedimos atenderlo a tiempo para mantener el sitio y los correos activos.';
    }

    if ($tipo === 'dominio') {
        $dominio = trim($dominioNombre);
        if ($dominio === '') {
            $dominio = trim($servicioNombre);
        }
        if ($dominio === '') {
            $dominio = 'tu dominio';
        }

        return 'Te recordamos que tienes un pago pendiente de la renovación del dominio <strong>'
            . cw_email_h($dominio) . '</strong>, con vencimiento el <strong>' . cw_email_h($fecha) . '</strong>. '
            . 'Realízalo a tiempo para conservar el registro y evitar que quede disponible para terceros.';
    }

    return 'Tienes un pago pendiente correspondiente a tu servicio con ConlineWeb. '
        . 'Te compartimos el detalle para que puedas completarlo a la brevedad.';
}

function pago_email_pendiente(
    string $concepto,
    string $monto_formateado,
    string $moneda,
    string $fecha_limite_pago,
    string $session_url,
    string $tipoServicio = '',
    string $servicioNombre = '',
    string $dominioNombre = ''
): string {
    // Si no pasaron servicio/dominio, intentar sacar pistas del concepto
    if ($servicioNombre === '' && $dominioNombre === '' && $concepto !== '') {
        $servicioNombre = $concepto;
    }

    $intro = pago_email_intro_pendiente($tipoServicio, $servicioNombre, $dominioNombre, $fecha_limite_pago);

    $contenido = cw_email_p('Hola,')
        . cw_email_p($intro)
        . pago_email_summary_card(
            '<p style="margin:0 0 8px;"><strong style="color:#000147;">Concepto:</strong> ' . cw_email_h($concepto) . '</p>'
            . '<p style="margin:0 0 8px;"><strong style="color:#000147;">Monto:</strong> ' . cw_email_h($moneda) . ' ' . cw_email_h($monto_formateado) . '</p>'
            . '<p style="margin:0;"><strong style="color:#000147;">Vencimiento:</strong> ' . cw_email_h($fecha_limite_pago) . '</p>'
        )
        . cw_email_p('<strong style="color:#000147;">Pago en línea</strong>', 12)
        . cw_email_p('Puedes pagar de forma segura con tarjeta de crédito o débito:', 12)
        . pago_email_cta($session_url, 'Pagar ahora')
        . pago_email_link_fallback($session_url)
        . cw_email_p('<strong style="color:#000147;">Transferencia bancaria</strong>', 12)
        . cw_email_card(
            '<strong>Titular:</strong> Jose Antonio Martinez Karam<br>'
            . '<strong>Banco:</strong> Santander<br>'
            . '<strong>Cuenta:</strong> 60622161632<br>'
            . '<strong>CLABE:</strong> 014225606221616325<br>'
            . '<strong>Referencia:</strong> ' . cw_email_h($concepto),
            ''
        )
        . cw_email_alert(
            '<strong>Confirmación:</strong> Una vez realizado el pago, envía tu comprobante por WhatsApp al <strong>477 118 1285</strong>.',
            'warning'
        )
        . cw_email_p('<span style="font-size:14px;color:#64748b;">Si ya realizaste el pago, puedes ignorar este mensaje.</span>', 0);

    return pago_email_wrap('Recordatorio de pago pendiente', $contenido);
}

function pago_email_confirmacion(string $nombreCliente, string $fechaHora, string $detalleExtra = ''): string
{
    $contenido = cw_email_p('Hola <strong>' . cw_email_h($nombreCliente) . '</strong>,')
        . cw_email_p('Confirmamos que hemos recibido tu pago correctamente. ¡Gracias por tu preferencia!')
        . pago_email_summary_card(
            '<p style="margin:0 0 8px;"><strong style="color:#000147;">Estado:</strong> <span style="color:#047857;font-weight:700;">Pagado</span></p>'
            . '<p style="margin:0;"><strong style="color:#000147;">Fecha de pago:</strong> ' . cw_email_h($fechaHora) . '</p>'
        )
        . $detalleExtra;

    return pago_email_wrap('Confirmación de pago recibido', $contenido);
}

function pago_email_multiple(
    float $total_pagado,
    string $currency,
    int $cantidad_servicios,
    string $pago_grupal_id,
    string $fecha_pago,
    string $lista_servicios_html
): string {
    $contenido = cw_email_p('Hola,')
        . cw_email_p('Tu <strong>pago múltiple</strong> se procesó correctamente. Este es el resumen:')
        . cw_email_card(
            '<p style="margin:0 0 8px;font-size:22px;font-weight:800;color:#000147;">$' . number_format($total_pagado, 2) . ' ' . cw_email_h(strtoupper($currency)) . '</p>'
            . '<p style="margin:0 0 6px;"><strong>Servicios:</strong> ' . (int) $cantidad_servicios . '</p>'
            . '<p style="margin:0 0 6px;"><strong>Fecha:</strong> ' . cw_email_h($fecha_pago) . '</p>'
            . '<p style="margin:0;"><strong>Referencia:</strong> ' . cw_email_h($pago_grupal_id) . '</p>',
            'Total pagado'
        )
        . cw_email_p('<strong style="color:#000147;">Servicios renovados</strong>', 12)
        . $lista_servicios_html
        . cw_email_p('<span style="color:#64748b;">Gracias por confiar en ConlineWeb.</span>', 0);

    return pago_email_wrap('Confirmación de pago múltiple', $contenido);
}

function pago_email_servicios_list(array $servicios): string
{
    return cw_email_service_rows($servicios);
}

/**
 * Aviso urgente: hosting vencido — plazo de 5 días y eliminación 1 día después.
 */
function pago_email_vencido_hosting(
    string $nombreCliente,
    string $servicio,
    string $dominio,
    string $plan,
    string $monto_formateado,
    string $moneda,
    string $fecha_vencimiento,
    string $fecha_plazo,
    string $fecha_eliminacion,
    string $session_url = '',
    string $etapa = 'eliminacion'
): string {
    if (!in_array($etapa, ['vencimiento', 'plazo', 'eliminacion'], true)) {
        $etapa = 'eliminacion';
    }
    $detalle = '<p style="margin:0 0 8px;"><strong style="color:#000147;">Cliente:</strong> ' . cw_email_h($nombreCliente) . '</p>'
        . '<p style="margin:0 0 8px;"><strong style="color:#000147;">Servicio:</strong> Hosting ' . cw_email_h($servicio) . '</p>';
    if ($plan !== '') {
        $detalle .= '<p style="margin:0 0 8px;"><strong style="color:#000147;">Plan:</strong> ' . cw_email_h($plan) . '</p>';
    }
    if ($dominio !== '') {
        $detalle .= '<p style="margin:0 0 8px;"><strong style="color:#000147;">Dominio asociado:</strong> ' . cw_email_h($dominio) . '</p>';
    }
    $detalle .= '<p style="margin:0 0 8px;"><strong style="color:#000147;">Fecha de vencimiento:</strong> ' . cw_email_h($fecha_vencimiento) . '</p>'
        . '<p style="margin:0 0 8px;"><strong style="color:#000147;">Fecha de plazo:</strong> ' . cw_email_h($fecha_plazo) . ' <span style="color:#64748b;">(5 días después del vencimiento)</span></p>'
        . '<p style="margin:0 0 8px;"><strong style="color:#b91c1c;">Fecha de eliminación:</strong> ' . cw_email_h($fecha_eliminacion) . ' <span style="color:#64748b;">(1 día después del plazo)</span></p>'
        . '<p style="margin:0;"><strong style="color:#000147;">Monto pendiente:</strong> ' . cw_email_h($moneda) . ' ' . cw_email_h($monto_formateado) . '</p>';

    if ($etapa === 'vencimiento') {
        $titulo = 'Alerta de vencimiento';
        $aviso = '<strong>Alerta de vencimiento:</strong> la fecha de vencimiento de su hosting <strong>ya llegó</strong> (' . cw_email_h($fecha_vencimiento) . '). '
            . 'Tiene un plazo de 5 días, hasta el <strong>' . cw_email_h($fecha_plazo) . '</strong>. '
            . 'Si no realiza el pago, la cuenta se eliminará el <strong>' . cw_email_h($fecha_eliminacion) . '</strong>.';
        $badge = 'Vencimiento';
    } elseif ($etapa === 'plazo') {
        $titulo = 'Alerta de plazo';
        $aviso = '<strong>Alerta de plazo:</strong> su hosting venció el <strong>' . cw_email_h($fecha_vencimiento) . '</strong>. '
            . 'Cuenta con <strong>5 días de plazo</strong> (hasta el ' . cw_email_h($fecha_plazo) . ') para regularizar el pago. '
            . 'Le pedimos tomarlo en cuenta para evitar la suspensión o eliminación del servicio.';
        $badge = 'Plazo';
    } else {
        $titulo = 'Alerta de eliminación';
        $aviso = '<strong>Alerta de eliminación:</strong> ya pasó 1 día después de la fecha de plazo. '
            . 'Su cuenta de hosting será <strong>eliminada a partir del ' . cw_email_h($fecha_eliminacion) . '</strong>.';
        $badge = 'Eliminación';
    }

    $contenido = cw_email_p('Hola <strong>' . cw_email_h($nombreCliente) . '</strong>,')
        . cw_email_alert($aviso, $etapa === 'plazo' ? 'warning' : 'danger');
    if ($etapa === 'plazo') {
        $contenido .= cw_email_p('Este aviso es para que tenga presente el plazo de 5 días y pueda realizar el pago a tiempo.');
    } else {
        $contenido .= cw_email_p('Es <strong>necesario que realice un respaldo (backup)</strong> de sus archivos, bases de datos y correos cuanto antes, antes de que la cuenta sea eliminada.');
    }
    $contenido .= pago_email_summary_card($detalle);

    // Botón + enlace de pago (mismo CTA que el recordatorio pendiente)
    if ($session_url !== '') {
        $contenido .= cw_email_p('<strong style="color:#000147;">Pago en línea</strong>', 12)
            . cw_email_p('Puede pagar de forma segura con tarjeta de crédito o débito:', 12)
            . pago_email_cta($session_url, 'Pagar ahora')
            . pago_email_link_fallback($session_url);
    }

    $contenido .= cw_email_p('<strong style="color:#000147;">Transferencia bancaria</strong>', 12)
        . cw_email_card(
            '<strong>Titular:</strong> Jose Antonio Martinez Karam<br>'
            . '<strong>Banco:</strong> Santander<br>'
            . '<strong>Cuenta:</strong> 60622161632<br>'
            . '<strong>CLABE:</strong> 014225606221616325<br>'
            . '<strong>Referencia:</strong> ' . cw_email_h($servicio !== '' ? $servicio : $dominio),
            ''
        )
        . cw_email_p(
            '<span style="font-size:12px;color:#64748b;line-height:1.5;">'
            . '<strong style="color:#475569;">Confirmación:</strong> Envíe su comprobante por WhatsApp al <strong style="color:#0f172a;">477 118 1285</strong>.'
            . '</span>',
            16
        )
        . cw_email_p('<span style="font-size:13px;color:#64748b;">Si ya realizó el pago, ignore este mensaje y contacte a soporte para confirmar la reactivación.</span>', 0);

    return cw_email_wrap([
        'title' => $titulo,
        'content' => $contenido,
        'footer' => 'Este mensaje fue enviado automáticamente. Por favor, no respondas a este correo.',
        'badge' => $badge,
        'badge_variant' => 'warning',
    ]);
}

/**
 * Aviso urgente: dominio vencido — riesgo de pérdida.
 */
function pago_email_vencido_dominio(
    string $nombreCliente,
    string $dominio,
    string $monto_formateado,
    string $moneda,
    string $fecha_limite_pago,
    string $session_url = ''
): string {
    $detalle = '<p style="margin:0 0 8px;"><strong style="color:#000147;">Cliente:</strong> ' . cw_email_h($nombreCliente) . '</p>'
        . '<p style="margin:0 0 8px;"><strong style="color:#000147;">Dominio:</strong> ' . cw_email_h($dominio) . '</p>'
        . '<p style="margin:0 0 8px;"><strong style="color:#000147;">Fecha límite de pago:</strong> ' . cw_email_h($fecha_limite_pago) . '</p>'
        . '<p style="margin:0;"><strong style="color:#000147;">Monto pendiente:</strong> ' . cw_email_h($moneda) . ' ' . cw_email_h($monto_formateado) . '</p>';

    $contenido = cw_email_p('Hola <strong>' . cw_email_h($nombreCliente) . '</strong>,')
        . cw_email_alert(
            '<strong>Aviso urgente:</strong> ya pasó la fecha límite de pago del dominio <strong>' . cw_email_h($dominio) . '</strong>. '
            . 'Es probable que el dominio <strong>se pierda</strong> si no se renueva de inmediato.',
            'danger'
        )
        . cw_email_p('Le recomendamos regularizar el pago cuanto antes para evitar que el dominio quede disponible para terceros o entre en periodo de redención con costos adicionales.')
        . pago_email_summary_card($detalle);

    if ($session_url !== '') {
        $contenido .= cw_email_p('<strong style="color:#000147;">Renueve su dominio ahora</strong>', 12)
            . cw_email_p('Puede pagar de forma segura con tarjeta:', 12)
            . pago_email_cta($session_url, 'Pagar ahora')
            . pago_email_link_fallback($session_url);
    }

    $contenido .= cw_email_p('<strong style="color:#000147;">Transferencia bancaria</strong>', 12)
        . cw_email_card(
            '<strong>Titular:</strong> Jose Antonio Martinez Karam<br>'
            . '<strong>Banco:</strong> Santander<br>'
            . '<strong>Cuenta:</strong> 60622161632<br>'
            . '<strong>CLABE:</strong> 014225606221616325<br>'
            . '<strong>Referencia:</strong> ' . cw_email_h($dominio),
            ''
        )
        . cw_email_p(
            '<span style="font-size:12px;color:#64748b;line-height:1.5;">'
            . '<strong style="color:#475569;">Confirmación:</strong> Envíe su comprobante por WhatsApp al <strong style="color:#0f172a;">477 118 1285</strong>.'
            . '</span>',
            16
        )
        . cw_email_p('<span style="font-size:13px;color:#64748b;">Si ya realizó el pago, ignore este mensaje y contacte a soporte.</span>', 0);

    return cw_email_wrap([
        'title' => 'Aviso urgente: dominio vencido',
        'content' => $contenido,
        'footer' => 'Este mensaje fue enviado automáticamente. Por favor, no respondas a este correo.',
        'badge' => 'Urgente',
        'badge_variant' => 'warning',
    ]);
}
