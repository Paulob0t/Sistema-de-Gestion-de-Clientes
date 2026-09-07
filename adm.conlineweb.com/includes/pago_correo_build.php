<?php
/**
 * Construye HTML de correos de pago (pendiente / vencido) sin enviar.
 */
require_once __DIR__ . '/pago_email_template.php';

/**
 * @return array{ok:bool,error?:string,html?:string,asunto?:string,correo?:string,cliente?:string,tipo?:string,modo?:string,meta?:array}
 */
function pago_correo_cargar_fila(mysqli $db, int $id): array
{
    $stmt = $db->prepare(
        "SELECT p.id, p.id_servicio, p.id_clie, p.tipo_servicio, p.monto, p.currency, p.concepto,
                p.fecha_limite_pago, p.session_id, p.manual,
                TRIM(c.correo) AS correo, c.nombre_contacto,
                d.url_dominio, d.fecha_pago AS fecha_pago_dominio, d.costo_dominio,
                h.nom_host, h.dominio AS hosting_dominio, h.fecha_pago AS fecha_pago_hosting,
                h.costo_producto, h.producto AS producto_id,
                pl.nombre AS plan_nombre
         FROM pagos p
         JOIN clientes c ON p.id_clie = c.id
         LEFT JOIN dominios d ON p.tipo_servicio = '2' AND p.id_servicio = d.id_dominio
         LEFT JOIN hosting h ON p.tipo_servicio = '1' AND p.id_servicio = h.id_orden
         LEFT JOIN planes pl ON h.producto = pl.id
         WHERE p.id = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Error al preparar consulta'];
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return ['ok' => false, 'error' => 'No se encontró el pago #' . $id];
    }
    return ['ok' => true, 'row' => $row];
}

function pago_correo_fecha_limite(array $row): string
{
    $tipo = (int) ($row['tipo_servicio'] ?? 0);
    $fechaRaw = trim((string) ($row['fecha_limite_pago'] ?? ''));
    if ($fechaRaw === '' || $fechaRaw === '0000-00-00') {
        if ($tipo === 1) {
            $fechaRaw = trim((string) ($row['fecha_pago_hosting'] ?? ''));
        } elseif ($tipo === 2) {
            $fechaRaw = trim((string) ($row['fecha_pago_dominio'] ?? ''));
        }
    }
    return ($fechaRaw === '' || $fechaRaw === '0000-00-00') ? '' : $fechaRaw;
}

function pago_correo_monto_moneda(array $row): array
{
    $tipo = (int) ($row['tipo_servicio'] ?? 0);
    $monto = (float) ($row['monto'] ?? 0);
    if ($monto <= 0) {
        $monto = (float) ($tipo === 1 ? ($row['costo_producto'] ?? 0) : ($row['costo_dominio'] ?? 0));
    }
    $moneda = strtoupper(trim((string) ($row['currency'] ?? 'MXN'))) ?: 'MXN';
    return [$monto, $moneda];
}

/**
 * @param string $sessionUrl URL de pago (puede ser placeholder en preview)
 * @return array{ok:bool,error?:string,html?:string,asunto?:string,correo?:string,cliente?:string,tipo?:string,modo?:string,meta?:array}
 */
function pago_correo_build(mysqli $db, int $id, string $modo, string $sessionUrl = ''): array
{
    $modosAlerta = ['vencido', 'vencimiento', 'plazo', 'eliminacion'];
    if (!in_array($modo, $modosAlerta, true)) {
        $modo = 'pendiente';
    }
    $loaded = pago_correo_cargar_fila($db, $id);
    if (empty($loaded['ok'])) {
        return $loaded;
    }
    $row = $loaded['row'];
    $tipo = (int) $row['tipo_servicio'];
    $correo = trim((string) ($row['correo'] ?? ''));
    $nombre = trim((string) ($row['nombre_contacto'] ?? 'Cliente'));
    if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'El cliente no tiene un correo válido'];
    }

    [$monto, $moneda] = pago_correo_monto_moneda($row);
    $montoFmt = number_format($monto, 2);
    $fechaRaw = pago_correo_fecha_limite($row);
    $fechaLimiteFmt = $fechaRaw !== '' ? date('d/m/Y', strtotime($fechaRaw)) : 'Por definir';

    $concepto = trim((string) ($row['concepto'] ?? ''));
    if ($concepto === '') {
        if ($tipo === 1) {
            $concepto = 'Renovación Hosting ' . ($row['nom_host'] ?: $row['hosting_dominio'] ?: '');
        } elseif ($tipo === 2) {
            $concepto = 'Renovación dominio ' . ($row['url_dominio'] ?? '');
        } else {
            $concepto = 'Pago #' . $id;
        }
    }

    if (in_array($modo, ['vencido', 'vencimiento', 'plazo', 'eliminacion'], true) && $sessionUrl === '') {
        return ['ok' => false, 'error' => 'No hay enlace de pago Stripe válido para enviar la alerta'];
    }

    if ($sessionUrl === '') {
        $sessionUrl = 'https://adm.conlineweb.com/#pago';
    }

    if (in_array($modo, ['vencido', 'vencimiento', 'plazo', 'eliminacion'], true)) {
        if ($fechaRaw === '') {
            return ['ok' => false, 'error' => 'El pago no tiene fecha límite; no aplica aviso de alerta'];
        }
        $fechaLimiteTs = strtotime($fechaRaw);
        if ($fechaLimiteTs === false) {
            return ['ok' => false, 'error' => 'Fecha límite inválida'];
        }
        $fechaPlazoTs = strtotime('+5 days', $fechaLimiteTs);
        $fechaEliminacionTs = strtotime('+1 day', $fechaPlazoTs);
        $fechaPlazoFmt = date('d/m/Y', $fechaPlazoTs);
        $fechaEliminacionFmt = date('d/m/Y', $fechaEliminacionTs);
        $etapaHosting = $modo === 'vencido' ? 'eliminacion' : $modo;

        if ($tipo === 1) {
            $servicio = trim((string) ($row['nom_host'] ?? ''));
            $dominio = trim((string) ($row['hosting_dominio'] ?? ''));
            $plan = trim((string) ($row['plan_nombre'] ?? ''));
            if ($servicio === '') {
                $servicio = $dominio !== '' ? $dominio : ('Orden #' . $row['id_servicio']);
            }
            $asuntos = [
                'vencimiento' => 'Alerta de vencimiento: su hosting venció — ' . $servicio,
                'plazo' => 'Alerta de plazo: tiene 5 días de plazo para regularizar su hosting — ' . $servicio,
                'eliminacion' => 'Alerta de eliminación: su cuenta de hosting será eliminada — ' . $servicio,
            ];
            $asunto = $asuntos[$etapaHosting] ?? $asuntos['eliminacion'];
            $html = pago_email_vencido_hosting(
                $nombre, $servicio, $dominio, $plan, $montoFmt, $moneda,
                $fechaLimiteFmt, $fechaPlazoFmt, $fechaEliminacionFmt, $sessionUrl, $etapaHosting
            );
            $tipoLabel = 'Hosting';
        } elseif ($tipo === 2) {
            $dominio = trim((string) ($row['url_dominio'] ?? ''));
            if ($dominio === '') {
                $dominio = 'Dominio #' . $row['id_servicio'];
            }
            $asunto = 'Aviso urgente: dominio vencido — riesgo de pérdida — ' . $dominio;
            $html = pago_email_vencido_dominio(
                $nombre, $dominio, $montoFmt, $moneda, $fechaLimiteFmt, $sessionUrl
            );
            $tipoLabel = 'Dominio';
        } else {
            $conceptoLower = mb_strtolower($concepto);
            if (strpos($conceptoLower, 'hosting') !== false || strpos($conceptoLower, 'alojamiento') !== false) {
                $asunto = 'Aviso urgente: servicio vencido — cuenta por eliminar';
                $html = pago_email_vencido_hosting(
                    $nombre, $concepto, '', '', $montoFmt, $moneda,
                    $fechaLimiteFmt, $fechaPlazoFmt, $fechaEliminacionFmt, $sessionUrl, $etapaHosting
                );
                $tipoLabel = 'Hosting';
            } else {
                $asunto = 'Aviso urgente: servicio vencido — riesgo de pérdida';
                $html = pago_email_vencido_dominio(
                    $nombre, $concepto, $montoFmt, $moneda, $fechaLimiteFmt, $sessionUrl
                );
                $tipoLabel = 'Dominio';
            }
        }

        return [
            'ok' => true,
            'html' => $html,
            'asunto' => $asunto,
            'correo' => $correo,
            'cliente' => $nombre,
            'tipo' => $tipoLabel,
            'modo' => $modo,
            'meta' => [
                'fecha_limite' => $fechaLimiteFmt,
                'fecha_plazo' => $fechaPlazoFmt ?? null,
                'fecha_eliminacion' => $fechaEliminacionFmt ?? null,
                'monto' => $montoFmt,
                'moneda' => $moneda,
                'concepto' => $concepto,
            ],
        ];
    }

    // Pendiente (recordatorio normal)
    $servicioNombre = '';
    $dominioNombre = '';
    $tipoKey = '';
    if ($tipo === 1) {
        $servicioNombre = trim((string) ($row['nom_host'] ?? ''));
        $dominioNombre = trim((string) ($row['hosting_dominio'] ?? ''));
        if ($servicioNombre === '') {
            $servicioNombre = $dominioNombre !== '' ? $dominioNombre : ('Orden #' . $row['id_servicio']);
        }
        $asunto = 'Recordatorio de pago pendiente — Hosting' . ($servicioNombre !== '' ? ' ' . $servicioNombre : '');
        $tipoLabel = 'Hosting';
        $tipoKey = 'hosting';
    } elseif ($tipo === 2) {
        $dominioNombre = trim((string) ($row['url_dominio'] ?? ''));
        if ($dominioNombre === '') {
            $dominioNombre = 'Dominio #' . $row['id_servicio'];
        }
        $servicioNombre = $dominioNombre;
        $asunto = 'Recordatorio de pago pendiente — Dominio' . ($dominioNombre !== '' ? ' ' . $dominioNombre : '');
        $tipoLabel = 'Dominio';
        $tipoKey = 'dominio';
    } else {
        $asunto = 'Recordatorio de pago pendiente';
        $tipoLabel = 'Manual';
        $conceptoLower = function_exists('mb_strtolower') ? mb_strtolower($concepto) : strtolower($concepto);
        if (strpos($conceptoLower, 'hosting') !== false || strpos($conceptoLower, 'alojamiento') !== false) {
            $tipoKey = 'hosting';
            $servicioNombre = $concepto;
        } elseif (strpos($conceptoLower, 'dominio') !== false) {
            $tipoKey = 'dominio';
            $dominioNombre = $concepto;
        }
    }

    $html = pago_email_pendiente(
        $concepto,
        $montoFmt,
        $moneda,
        $fechaLimiteFmt,
        $sessionUrl,
        $tipoKey,
        $servicioNombre,
        $dominioNombre
    );

    return [
        'ok' => true,
        'html' => $html,
        'asunto' => $asunto,
        'correo' => $correo,
        'cliente' => $nombre,
        'tipo' => $tipoLabel,
        'modo' => 'pendiente',
        'meta' => [
            'fecha_limite' => $fechaLimiteFmt,
            'monto' => $montoFmt,
            'moneda' => $moneda,
            'concepto' => $concepto,
        ],
    ];
}
