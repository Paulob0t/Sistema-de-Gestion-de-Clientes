<?php
/**
 * Contexto del cliente para asesores (servicios, tickets, pagos)
 */

/** @return array<string,mixed> */
function cw_chat_client_context(mysqli $conn, int $clienteId): array
{
    $ctx = [
        'cliente' => null,
        'hosting' => [],
        'dominios' => [],
        'pagos_pendientes' => 0,
        'pagos_total' => 0,
        'pagos_detalle' => [],
        'tickets_abiertos' => 0,
        'tickets_cerrados' => 0,
        'tickets_pendientes' => 0,
        'tickets_en_proceso' => 0,
        'tickets_recientes' => [],
        'productos' => 0,
        'ultima_conexion' => null,
    ];

    $stmt = $conn->prepare('SELECT id, nombre_contacto, empresa, correo, telefono FROM clientes WHERE id = ? AND eliminado = 0 LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $clienteId);
        $stmt->execute();
        $ctx['cliente'] = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
    }

    if (!$ctx['cliente']) {
        return $ctx;
    }

    $hq = $conn->prepare("SELECT h.id_orden AS id, h.dominio, h.fecha_pago, h.fecha_pago AS fecha_vencimiento,
        h.estado_producto AS estatus, h.nom_host, h.url_acceso, p.nombre AS nombre_plan
        FROM hosting h
        LEFT JOIN planes p ON h.producto = p.id
        WHERE h.cliente_id = ? AND h.eliminado = 0
        ORDER BY (h.fecha_pago IN ('0000-00-00', '0001-01-01') OR h.fecha_pago IS NULL) ASC, h.fecha_pago ASC
        LIMIT 15");
    if ($hq) {
        $hq->bind_param('i', $clienteId);
        $hq->execute();
        $res = $hq->get_result();
        while ($row = $res->fetch_assoc()) {
            $ctx['hosting'][] = cw_chat_enrich_vigencia_row($row, 'hosting');
        }
        $hq->close();
    }

    $dq = $conn->prepare("SELECT d.id_dominio AS id, d.url_dominio AS dominio, d.fecha_pago, d.fecha_pago AS fecha_vencimiento,
        d.estado_dominio AS estatus, d.registrado, d.url_cpanel
        FROM dominios d
        WHERE d.cliente_id = ? AND d.eliminado = 0
        ORDER BY d.registrado DESC, (d.fecha_pago IN ('0000-00-00', '0001-01-01') OR d.fecha_pago IS NULL) ASC, d.fecha_pago ASC
        LIMIT 15");
    if ($dq) {
        $dq->bind_param('i', $clienteId);
        $dq->execute();
        $res = $dq->get_result();
        while ($row = $res->fetch_assoc()) {
            $ctx['dominios'][] = cw_chat_enrich_vigencia_row($row, 'dominio');
        }
        $dq->close();
    }

    $pq = $conn->prepare("SELECT
        SUM(CASE WHEN estatus != 1 AND Registro = 0 THEN 1 ELSE 0 END) pendientes,
        COUNT(*) total
        FROM pagos WHERE id_clie = ?");
    if ($pq) {
        $pq->bind_param('i', $clienteId);
        $pq->execute();
        $row = $pq->get_result()->fetch_assoc();
        $ctx['pagos_pendientes'] = (int) ($row['pendientes'] ?? 0);
        $ctx['pagos_total'] = (int) ($row['total'] ?? 0);
        $pq->close();
    }

    $pdq = $conn->prepare("SELECT
            p.id, p.monto, p.currency, p.concepto, p.fecha_limite_pago, p.fecha_pago, p.tipo_servicio, p.id_servicio, p.estatus,
            CASE
                WHEN p.tipo_servicio = '2' THEN d.url_dominio
                WHEN p.tipo_servicio = '1' THEN CONCAT('Hosting ', COALESCE(h.dominio, h.tipo_producto, 'servicio'))
                ELSE COALESCE(p.concepto, 'Servicio')
            END AS nombre_servicio,
            CASE
                WHEN p.tipo_servicio = '2' THEN d.fecha_pago
                WHEN p.tipo_servicio = '1' THEN h.fecha_pago
                ELSE COALESCE(p.fecha_limite_pago, p.fecha_pago)
            END AS fecha_vigencia_servicio
        FROM pagos p
        LEFT JOIN dominios d ON p.tipo_servicio = '2' AND p.id_servicio = d.id_dominio AND d.eliminado = 0
        LEFT JOIN hosting h ON p.tipo_servicio = '1' AND p.id_servicio = h.id_orden AND h.eliminado = 0
        WHERE p.id_clie = ? AND p.estatus != 1 AND p.Registro = 0
        ORDER BY COALESCE(NULLIF(p.fecha_limite_pago, '0000-00-00'), p.fecha_pago) ASC
        LIMIT 10");
    if ($pdq) {
        $pdq->bind_param('i', $clienteId);
        $pdq->execute();
        $res = $pdq->get_result();
        while ($row = $res->fetch_assoc()) {
            $ctx['pagos_detalle'][] = $row;
        }
        $pdq->close();
    }

    $tq = $conn->prepare("SELECT
        SUM(CASE WHEN estado != 'Finalizado' THEN 1 ELSE 0 END) abiertos,
        SUM(CASE WHEN estado = 'Finalizado' THEN 1 ELSE 0 END) cerrados
        FROM solicitudes WHERE id_cliente = ?");
    if ($tq) {
        $tq->bind_param('i', $clienteId);
        $tq->execute();
        $row = $tq->get_result()->fetch_assoc();
        $ctx['tickets_abiertos'] = (int) ($row['abiertos'] ?? 0);
        $ctx['tickets_cerrados'] = (int) ($row['cerrados'] ?? 0);
        $tq->close();
    }

    if (!function_exists('cw_cliente_tickets_stats')) {
        $ticketsSvc = __DIR__ . '/cw_cliente_tickets_service.php';
        if (is_file($ticketsSvc)) {
            require_once $ticketsSvc;
        }
    }
    if (function_exists('cw_cliente_tickets_stats')) {
        $st = cw_cliente_tickets_stats($conn, $clienteId);
        $ctx['tickets_pendientes'] = $st['pendientes'];
        $ctx['tickets_en_proceso'] = $st['en_proceso'];
        $ctx['tickets_abiertos'] = $st['abiertos'];
        $ctx['tickets_cerrados'] = $st['finalizados'];
    }

    $tr = $conn->prepare("SELECT id, titulo, estado, prioridad, fecha_solicitud FROM solicitudes
        WHERE id_cliente = ? ORDER BY fecha_solicitud DESC LIMIT 10");
    if ($tr) {
        $tr->bind_param('i', $clienteId);
        $tr->execute();
        $res = $tr->get_result();
        while ($row = $res->fetch_assoc()) {
            $ctx['tickets_recientes'][] = $row;
        }
        $tr->close();
    }

    $ctx['productos'] = count($ctx['hosting']) + count($ctx['dominios']);
    $ctx['ultima_conexion'] = null;

    return $ctx;
}

/**
 * Calcula vigencia desde fecha_pago de hosting/dominios.
 *
 * @param array<string,mixed> $row
 * @return array<string,mixed>
 */
function cw_chat_enrich_vigencia_row(array $row, string $tipo): array
{
    $fecha = (string) ($row['fecha_pago'] ?? $row['fecha_vencimiento'] ?? '');
    $row['fecha_pago'] = $fecha;
    $row['fecha_vencimiento'] = $fecha;
    $row['tipo_servicio_tabla'] = $tipo;

    if (function_exists('cliente_dias_vencimiento')) {
        $row['dias_restantes'] = cliente_dias_vencimiento($fecha);
    } else {
        $row['dias_restantes'] = null;
    }

    return $row;
}

/**
 * Contexto mínimo para conversaciones canal web ligadas a leads.
 * @return array<string,mixed>
 */
function cw_chat_lead_context(mysqli $conn, int $leadId): array
{
    $ctx = [
        'cliente' => null,
        'hosting' => [],
        'dominios' => [],
        'pagos_pendientes' => 0,
        'pagos_total' => 0,
        'pagos_detalle' => [],
        'tickets_abiertos' => 0,
        'tickets_cerrados' => 0,
        'tickets_pendientes' => 0,
        'tickets_en_proceso' => 0,
        'tickets_recientes' => [],
        'productos' => 0,
        'ultima_conexion' => null,
        'es_lead_web' => true,
        'lead' => null,
    ];

    $stmt = $conn->prepare('SELECT id, nombre, correo, telefono, empresa, servicio, requerimiento, pagina_origen, fuente, pipeline_estado
        FROM leads WHERE id = ? AND eliminado = 0 LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $leadId);
        $stmt->execute();
        $lead = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        if ($lead) {
            $ctx['lead'] = $lead;
            $ctx['cliente'] = [
                'id' => null,
                'nombre_contacto' => $lead['nombre'] ?? '',
                'empresa' => $lead['empresa'] ?? '',
                'correo' => $lead['correo'] ?? '',
                'telefono' => $lead['telefono'] ?? '',
            ];
        }
    }

    return $ctx;
}

/**
 * Contexto de sesión web (visitante anónimo o aún sin lead).
 *
 * @param array<string,mixed> $conv
 * @return array<string,mixed>
 */
function cw_chat_web_session_context(mysqli $conn, array $conv): array
{
    $meta = [];
    if (!empty($conv['metadata'])) {
        $decoded = json_decode((string) $conv['metadata'], true);
        if (is_array($decoded)) {
            $meta = $decoded;
        }
    }

    $sessionId = trim((string) ($meta['session_id'] ?? ''));
    $sessionShort = $sessionId !== '' ? mb_substr($sessionId, -8) : '';
    $nombreMeta = trim((string) ($meta['nombre'] ?? ''));
    $displayName = $nombreMeta !== ''
        ? $nombreMeta
        : ('Visitante en vivo' . ($sessionShort !== '' ? (' · ' . $sessionShort) : ''));

    return [
        'cliente' => [
            'id' => null,
            'nombre_contacto' => $displayName,
            'empresa' => '',
            'correo' => trim((string) ($meta['correo'] ?? $meta['email'] ?? '')),
            'telefono' => trim((string) ($meta['telefono'] ?? $meta['whatsapp'] ?? '')),
        ],
        'hosting' => [],
        'dominios' => [],
        'pagos_pendientes' => 0,
        'pagos_total' => 0,
        'pagos_detalle' => [],
        'tickets_abiertos' => 0,
        'tickets_cerrados' => 0,
        'tickets_pendientes' => 0,
        'tickets_en_proceso' => 0,
        'tickets_recientes' => [],
        'productos' => 0,
        'ultima_conexion' => null,
        'es_lead_web' => false,
        'es_sesion_web' => true,
        'lead' => null,
        'sesion' => [
            'conversation_id' => (int) ($conv['id'] ?? 0),
            'session_id' => $sessionId,
            'session_short' => $sessionShort,
            'pagina_origen' => trim((string) ($meta['pagina_origen'] ?? '')),
            'interes' => trim((string) ($meta['interes'] ?? $meta['mensaje_inicial'] ?? '')),
            'servicio' => trim((string) ($meta['servicio'] ?? '')),
            'registrado' => !empty($conv['lead_id']),
            'iniciada_at' => (string) ($conv['iniciada_at'] ?? ''),
        ],
    ];
}

function cw_chat_context_summary_html(array $ctx): string
{
    if (!empty($ctx['es_sesion_web'])) {
        $s = $ctx['sesion'] ?? [];
        $c = $ctx['cliente'] ?? [];
        $html = '<div class="ch-ctx-grid">';
        $html .= '<div><span>Visitante</span><strong>' . htmlspecialchars((string) ($c['nombre_contacto'] ?? 'Visitante en vivo'), ENT_QUOTES, 'UTF-8') . '</strong></div>';
        $html .= '<div><span>Registro</span><strong>' . (!empty($s['registrado']) ? 'Con lead' : 'Sin registrar aún') . '</strong></div>';
        $short = (string) ($s['session_short'] ?? '');
        $sid = (string) ($s['session_id'] ?? '');
        $html .= '<div><span>Sesión</span><strong>' . htmlspecialchars($short !== '' ? $short : ($sid !== '' ? $sid : '—'), ENT_QUOTES, 'UTF-8') . '</strong></div>';
        $html .= '<div><span>Chat #</span><strong>#' . (int) ($s['conversation_id'] ?? 0) . '</strong></div>';
        $svc = trim((string) ($s['servicio'] ?? ''));
        $int = trim((string) ($s['interes'] ?? ''));
        $html .= '<div><span>Servicio</span><strong>' . htmlspecialchars($svc !== '' ? $svc : '—', ENT_QUOTES, 'UTF-8') . '</strong></div>';
        $html .= '<div><span>Interés</span><strong>' . htmlspecialchars($int !== '' ? $int : '—', ENT_QUOTES, 'UTF-8') . '</strong></div>';
        $pagina = trim((string) ($s['pagina_origen'] ?? ''));
        if ($pagina !== '') {
            $safe = htmlspecialchars($pagina, ENT_QUOTES, 'UTF-8');
            $html .= '<div style="grid-column:1/-1"><span>Página</span><strong><a href="' . $safe . '" target="_blank" rel="noopener">' . $safe . '</a></strong></div>';
        }
        $correo = trim((string) ($c['correo'] ?? ''));
        $tel = trim((string) ($c['telefono'] ?? ''));
        if ($correo !== '') {
            $html .= '<div><span>Correo</span><strong>' . htmlspecialchars($correo, ENT_QUOTES, 'UTF-8') . '</strong></div>';
        }
        if ($tel !== '') {
            $html .= '<div><span>Teléfono</span><strong>' . htmlspecialchars($tel, ENT_QUOTES, 'UTF-8') . '</strong></div>';
        }
        $html .= '</div>';
        return $html;
    }

    $c = $ctx['cliente'] ?? [];
    $nombre = htmlspecialchars($c['nombre_contacto'] ?? '—', ENT_QUOTES, 'UTF-8');
    $empresa = htmlspecialchars($c['empresa'] ?? '—', ENT_QUOTES, 'UTF-8');
    $correo = htmlspecialchars($c['correo'] ?? '—', ENT_QUOTES, 'UTF-8');
    $tel = htmlspecialchars($c['telefono'] ?? '—', ENT_QUOTES, 'UTF-8');
    $esLead = !empty($ctx['es_lead_web']);

    $html = '<div class="ch-ctx-grid">';
    $html .= '<div><span>' . ($esLead ? 'Lead web' : 'Cliente') . '</span><strong>' . $nombre . '</strong></div>';
    $html .= '<div><span>Empresa</span><strong>' . $empresa . '</strong></div>';
    $html .= '<div><span>Correo</span><strong>' . $correo . '</strong></div>';
    $html .= '<div><span>Teléfono</span><strong>' . $tel . '</strong></div>';
    if ($esLead && !empty($ctx['lead'])) {
        $html .= '<div><span>Servicio</span><strong>' . htmlspecialchars((string) ($ctx['lead']['servicio'] ?? '—'), ENT_QUOTES, 'UTF-8') . '</strong></div>';
        $html .= '<div><span>Pipeline</span><strong>' . htmlspecialchars((string) ($ctx['lead']['pipeline_estado'] ?? '—'), ENT_QUOTES, 'UTF-8') . '</strong></div>';
        $html .= '<div><span>Fuente</span><strong>' . htmlspecialchars((string) ($ctx['lead']['fuente'] ?? 'website'), ENT_QUOTES, 'UTF-8') . '</strong></div>';
        $html .= '<div><span>Lead ID</span><strong>#' . (int) ($ctx['lead']['id'] ?? 0) . '</strong></div>';
    } else {
        $html .= '<div><span>Hosting</span><strong>' . count($ctx['hosting']) . ' servicio(s)</strong></div>';
        $html .= '<div><span>Dominios</span><strong>' . count($ctx['dominios']) . ' dominio(s)</strong></div>';
        $html .= '<div><span>Pagos pendientes</span><strong>' . (int) $ctx['pagos_pendientes'] . '</strong></div>';
        $html .= '<div><span>Tickets abiertos</span><strong>' . (int) $ctx['tickets_abiertos'] . '</strong></div>';
    }
    $html .= '</div>';

    if (!empty($ctx['tickets_recientes'])) {
        $nTickets = count($ctx['tickets_recientes']);
        $html .= '<details class="ch-ctx-tickets">';
        $html .= '<summary>'
            . '<span class="ch-ctx-tickets__label">'
            . '<i class="bi bi-ticket-detailed"></i>'
            . '<span class="ch-ctx-tickets__title-text">Tickets recientes</span>'
            . '<span class="ch-ctx-tickets__count">' . $nTickets . '</span>'
            . '</span>'
            . '</summary>';
        $html .= '<ul class="ch-ctx-tickets__list">';
        foreach ($ctx['tickets_recientes'] as $t) {
            $html .= '<li>'
                . '<span class="ch-ctx-tickets__id">#' . (int) $t['id'] . '</span>'
                . '<span class="ch-ctx-tickets__title">' . htmlspecialchars($t['titulo'], ENT_QUOTES, 'UTF-8') . '</span>'
                . '<em class="ch-ctx-tickets__state">' . htmlspecialchars($t['estado'], ENT_QUOTES, 'UTF-8') . '</em>'
                . '</li>';
        }
        $html .= '</ul></details>';
    }

    return $html;
}
