<?php
/**
 * Genera un mensaje de WhatsApp con TODOS los pagos pendientes de un cliente.
 * Incluye datos para transferencia, días restantes e iconos Unicode (visibles en PC).
 */
header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
ini_set('display_errors', 0);

try {
    require_once __DIR__ . '/conn.php';
    require_once __DIR__ . '/conn_hostingpro.php';

    $clienteId = isset($_POST['cliente_id']) ? (int) $_POST['cliente_id'] : (isset($_GET['cliente_id']) ? (int) $_GET['cliente_id'] : 0);
    if ($clienteId <= 0) {
        throw new Exception('ID de cliente no válido');
    }

    $sistema = isset($_POST['sistema']) ? trim((string) $_POST['sistema']) : (isset($_GET['sistema']) ? trim((string) $_GET['sistema']) : 'conlineweb');
    if (!in_array($sistema, ['conlineweb', 'hostingpro', 'planpro'], true)) {
        $sistema = 'conlineweb';
    }
    $connDb = ($sistema === 'hostingpro') ? $conn_hp : $conn;

    $stCli = $connDb->prepare('SELECT id, nombre_contacto, empresa, telefono, correo FROM clientes WHERE id = ? LIMIT 1');
    if (!$stCli) {
        throw new Exception('Error al preparar consulta de cliente');
    }
    $stCli->bind_param('i', $clienteId);
    $stCli->execute();
    $cliente = $stCli->get_result()->fetch_assoc();
    $stCli->close();

    if (!$cliente) {
        throw new Exception('Cliente no encontrado');
    }
    if (empty($cliente['telefono'])) {
        throw new Exception('El cliente no tiene teléfono registrado');
    }

    $sql = "SELECT
                p.id,
                p.monto,
                p.currency,
                p.concepto,
                p.fecha_limite_pago,
                p.tipo_servicio,
                p.session_id,
                CASE
                    WHEN p.tipo_servicio = '2' THEN d.url_dominio
                    WHEN p.tipo_servicio = '1' THEN h.nom_host
                    ELSE NULL
                END AS nombre_servicio,
                d.fecha_pago AS fecha_pago_dominio_ref,
                h.fecha_pago AS fecha_pago_hosting_ref
            FROM pagos p
            LEFT JOIN dominios d ON p.tipo_servicio = '2' AND p.id_servicio = d.id_dominio
            LEFT JOIN hosting h ON p.tipo_servicio = '1' AND p.id_servicio = h.id_orden
            WHERE p.id_clie = ? AND p.estatus = 0 AND p.Registro = 0";

    if ($sistema === 'planpro') {
        $sql .= " AND p.sistema = 'conlineweb'";
    } elseif ($sistema === 'conlineweb') {
        $sql .= " AND (p.sistema = 'conlineweb' OR p.sistema IS NULL OR p.sistema = '')";
    }

    $sql .= ' ORDER BY COALESCE(NULLIF(p.fecha_limite_pago, \'0000-00-00\'), \'9999-12-31\') ASC, p.id ASC';

    $st = $connDb->prepare($sql);
    if (!$st) {
        throw new Exception('Error al preparar consulta de pagos');
    }
    $st->bind_param('i', $clienteId);
    $st->execute();
    $res = $st->get_result();
    $pagos = [];
    while ($row = $res->fetch_assoc()) {
        $pagos[] = $row;
    }
    $st->close();

    if (!$pagos) {
        throw new Exception('Este cliente no tiene pagos pendientes');
    }

    $nombre = trim((string) ($cliente['nombre_contacto'] ?? ''));
    if ($nombre === '') {
        $nombre = trim((string) ($cliente['empresa'] ?? 'Cliente'));
    }

    $hoy = new DateTimeImmutable('today');

    $resolverFechaLimite = static function (array $pago): ?DateTimeImmutable {
        $rawFecha = (string) ($pago['fecha_limite_pago'] ?? '');
        if ($rawFecha === '' || $rawFecha === '0000-00-00') {
            if ((string) ($pago['tipo_servicio'] ?? '') === '2') {
                $rawFecha = (string) ($pago['fecha_pago_dominio_ref'] ?? '');
            } elseif ((string) ($pago['tipo_servicio'] ?? '') === '1') {
                $rawFecha = (string) ($pago['fecha_pago_hosting_ref'] ?? '');
            }
        }
        if ($rawFecha === '' || $rawFecha === '0000-00-00') {
            return null;
        }
        $ts = strtotime(substr($rawFecha, 0, 10) . ' 00:00:00');
        if (!$ts) {
            return null;
        }
        return (new DateTimeImmutable('@' . $ts))->setTimezone(new DateTimeZone(date_default_timezone_get() ?: 'America/Mexico_City'));
    };

    $textoDias = static function (?DateTimeImmutable $fecha, DateTimeImmutable $hoy): string {
        if (!$fecha) {
            return 'Sin fecha limite registrada';
        }
        $fechaSolo = $fecha->setTime(0, 0, 0);
        $diff = (int) $hoy->diff($fechaSolo)->format('%r%a');
        if ($diff > 1) {
            return $diff . ' dias restantes';
        }
        if ($diff === 1) {
            return '1 dia restante';
        }
        if ($diff === 0) {
            return 'Vence HOY';
        }
        $atraso = abs($diff);
        if ($atraso === 1) {
            return 'Vencido hace 1 dia';
        }
        return 'Vencido hace ' . $atraso . ' dias';
    };

    $total = 0.0;
    $currency = 'MXN';
    $lineas = [];
    $n = 0;
    $pagosOut = [];
    foreach ($pagos as $pago) {
        $n++;
        $monto = (float) ($pago['monto'] ?? 0);
        $total += $monto;
        if (!empty($pago['currency'])) {
            $currency = (string) $pago['currency'];
        }

        $tipo = 'Servicio';
        if ((string) ($pago['tipo_servicio'] ?? '') === '1') {
            $tipo = 'Hosting';
        } elseif ((string) ($pago['tipo_servicio'] ?? '') === '2') {
            $tipo = 'Dominio';
        }

        $fechaObj = $resolverFechaLimite($pago);
        $fechaLim = $fechaObj ? $fechaObj->format('d/m/Y') : '';
        $diasTxt = $textoDias($fechaObj, $hoy);
        $diffDays = null;
        if ($fechaObj) {
            $diffDays = (int) $hoy->diff($fechaObj->setTime(0, 0, 0))->format('%r%a');
        }

        $concepto = trim((string) ($pago['concepto'] ?? ''));
        if ($concepto === '') {
            $concepto = $tipo;
        }
        $servicio = trim((string) ($pago['nombre_servicio'] ?? ''));

        // Marcadores de texto (WhatsApp Desktop a menudo no muestra bien los emojis del enlace wa.me)
        $iconoEstado = '[OK]';
        if ($diffDays !== null) {
            if ($diffDays < 0) {
                $iconoEstado = '[VENCIDO]';
            } elseif ($diffDays === 0) {
                $iconoEstado = '[HOY]';
            } elseif ($diffDays <= 3) {
                $iconoEstado = '[URGENTE]';
            } else {
                $iconoEstado = '[OK]';
            }
        }

        $linea = $n . ') *' . $concepto . '*';
        if ($servicio !== '') {
            $linea .= "\n   - Servicio: " . $servicio;
        }
        $linea .= "\n   - Monto: $" . number_format($monto, 2) . ' ' . ($pago['currency'] ?: $currency);
        if ($fechaLim !== '') {
            $linea .= "\n   - Vence: " . $fechaLim . ' ' . $iconoEstado;
        }
        $linea .= "\n   - Plazo: " . $diasTxt;
        $lineas[] = $linea;

        $pagosOut[] = [
            'id' => (int) ($pago['id'] ?? 0),
            'concepto' => $concepto,
            'monto' => $monto,
            'currency' => (string) ($pago['currency'] ?? 'MXN'),
            'fecha_limite' => $fechaLim,
            'dias_restantes' => $diffDays,
            'dias_texto' => $diasTxt,
        ];
    }

    $portal = 'https://cliente.conlineweb.com/';
    $refTransfer = 'Cliente #' . $clienteId . ' - ' . $nombre;

    $mensaje = "Hola *" . $nombre . "*\n\n";
    $mensaje .= "Te compartimos el resumen de tus *pagos pendientes* (" . count($pagos) . "):\n\n";
    $mensaje .= implode("\n\n", $lineas);
    $mensaje .= "\n\n----------------------------------------\n";
    $mensaje .= "*TOTAL A PAGAR: $" . number_format($total, 2) . ' ' . $currency . "*\n";
    $mensaje .= "----------------------------------------\n\n";

    $mensaje .= "*DATOS PARA TRANSFERENCIA*\n";
    $mensaje .= "Titular: Jose Antonio Martinez Karam\n";
    $mensaje .= "Banco: Santander\n";
    $mensaje .= "Cuenta: 60622161632\n";
    $mensaje .= "CLABE: 014225606221616325\n";
    $mensaje .= "Referencia: " . $refTransfer . "\n\n";

    $mensaje .= "Despues de transferir, envia tu comprobante por WhatsApp al *477 118 1285*.\n\n";
    $mensaje .= "Tambien puedes pagar en linea desde tu portal:\n";
    $mensaje .= $portal . "\n\n";
    $mensaje .= "Si ya realizaste alguno de estos pagos, omite este mensaje o avisanos para actualizarlo.\n\n";
    $mensaje .= "Gracias por tu atencion.\n";
    $mensaje .= "Equipo ConlineWeb";

    $telefono = preg_replace('/\D+/', '', (string) $cliente['telefono']);
    if ($telefono !== '' && strlen($telefono) === 10 && substr($telefono, 0, 2) !== '52') {
        $telefono = '52' . $telefono;
    }

    echo json_encode([
        'success' => true,
        'telefono' => $telefono,
        'mensaje' => $mensaje,
        'cliente' => $nombre,
        'total' => $total,
        'currency' => $currency,
        'count' => count($pagos),
        'pagos' => $pagosOut,
        'cuenta' => [
            'titular' => 'Jose Antonio Martinez Karam',
            'banco' => 'Santander',
            'cuenta' => '60622161632',
            'clabe' => '014225606221616325',
            'whatsapp_comprobante' => '477 118 1285',
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
