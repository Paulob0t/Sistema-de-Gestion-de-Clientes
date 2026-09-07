<?php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_config.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__) . '/includes/leads_helpers.php';
require_once dirname(__DIR__, 2) . '/includes/proyec_levantamiento_admin.php';

cw_hub_migrate($conn);
proyec_migrate($conn);

$servicios = CW_HUB_SERVICIOS;
$pipelineLabels = CW_HUB_WEBSITE_PIPELINE;

$user = getAuthenticatedUser();
$usuarioId = (int) ($user['id'] ?? 0);

$filters = cw_web_lead_filter_params();
[$where, $types, $params] = cw_web_lead_list_where(
    $filters['dateFrom'],
    $filters['dateTo'],
    $filters['status'],
    $filters['origen']
);

$sql = "SELECT id, nombre, apellido, correo, telefono, empresa, requerimiento, servicio, pagina_origen, fuente,
               pipeline_estado, notas, fecha_registro, ultima_interaccion, session_id, web
        FROM leads
        WHERE {$where}
        ORDER BY fecha_registro DESC
        LIMIT 500";

$stmt = $conn->prepare($sql);
$data = [];

if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $notas = cw_web_lead_parse_notas($row['notas'] ?? '[]');
        $ultimaGestion = cw_web_lead_last_gestion_note($notas);
        $ultimaNota = '';
        if (!empty($notas)) {
            $ultimaNota = (string) ($notas[count($notas) - 1]['nota'] ?? '');
        }

        $estado = cw_web_lead_normalize_estado($row['pipeline_estado'] ?? 'lead');

        $svcKey = (string) ($row['servicio'] ?? 'otro');
        $origenInfo = cw_web_lead_origen_info($row['fuente'] ?? '', $row['pagina_origen'] ?? '');
        $webInfo = cw_web_lead_web_info($row['web'] ?? '', $row['pagina_origen'] ?? '');
        $nombre = trim((string) ($row['nombre'] ?? ''));
        $apellido = trim((string) ($row['apellido'] ?? ''));
        $nombreCompleto = trim($nombre . ($apellido !== '' ? ' ' . $apellido : ''));
        $data[] = [
            'id' => (int) $row['id'],
            'nombre' => $nombre,
            'apellido' => $apellido,
            'nombre_completo' => $nombreCompleto !== '' ? $nombreCompleto : $nombre,
            'correo' => $row['correo'] ?? '',
            'telefono' => $row['telefono'] ?? '',
            'empresa' => trim((string) ($row['empresa'] ?? '')),
            'servicio' => $servicios[$svcKey] ?? $svcKey,
            'servicio_key' => $svcKey,
            'requerimiento' => $row['requerimiento'] ?? '',
            'pagina_origen' => $row['pagina_origen'] ?? '',
            'fuente' => $row['fuente'] ?? '',
            'web' => $webInfo['key'],
            'web_label' => $webInfo['label'],
            'web_class' => $webInfo['class'],
            'origen_key' => $origenInfo['key'],
            'origen_label' => $origenInfo['label'],
            'origen_class' => $origenInfo['class'],
            'pipeline_estado' => $estado,
            'pipeline_label' => $pipelineLabels[$estado] ?? $estado,
            'ultima_nota' => cw_web_lead_note_preview($ultimaGestion !== '' ? $ultimaGestion : $ultimaNota),
            'ultima_nota_full' => $ultimaGestion !== '' ? $ultimaGestion : $ultimaNota,
            'total_notas' => count($notas),
            'notas_json' => json_encode($notas, JSON_UNESCAPED_UNICODE),
            'fecha_registro' => $row['fecha_registro'] ?? '',
            'ultima_interaccion' => $row['ultima_interaccion'] ?? '',
            'session_id' => $row['session_id'] ?? '',
            'has_session' => trim((string) ($row['session_id'] ?? '')) !== '',
        ];
    }
}

$leadIds = array_column($data, 'id');
$formFlags = proyec_admin_leads_form_flags_bulk($conn, $leadIds, $usuarioId);

$clienteByLead = [];
$clienteByCorreo = [];
if ($leadIds !== []) {
    $idList = implode(',', array_map('intval', $leadIds));
    $cliRes = $conn->query(
        "SELECT id, id_lead, correo
         FROM clientes
         WHERE eliminado = 0
           AND (id_lead IN ({$idList}) OR correo IN (
                SELECT correo FROM leads WHERE id IN ({$idList}) AND origen_web = 1 AND eliminado = 0
           ))"
    );
    if ($cliRes) {
        while ($c = $cliRes->fetch_assoc()) {
            $cid = (int) ($c['id'] ?? 0);
            $lid = (int) ($c['id_lead'] ?? 0);
            $mail = strtolower(trim((string) ($c['correo'] ?? '')));
            if ($lid > 0) {
                $clienteByLead[$lid] = $cid;
            }
            if ($mail !== '') {
                $clienteByCorreo[$mail] = $cid;
            }
        }
    }
}

foreach ($data as &$row) {
    $flags = $formFlags[(int) $row['id']] ?? [
        'tiene_formulario' => false,
        'cambios_pendientes' => 0,
        'progreso_pct' => 0,
        'paso_actual' => 1,
        'fecha_actualizacion' => null,
    ];
    $row['proyec_tiene_formulario'] = !empty($flags['tiene_formulario']);
    $row['proyec_cambios'] = (int) ($flags['cambios_pendientes'] ?? 0);
    $row['proyec_progreso'] = (int) ($flags['progreso_pct'] ?? 0);
    $row['proyec_paso'] = (int) ($flags['paso_actual'] ?? 1);
    $row['proyec_actualizado'] = $flags['fecha_actualizacion'] ?? '';

    $cid = $clienteByLead[(int) $row['id']] ?? 0;
    if ($cid <= 0) {
        $mail = strtolower(trim((string) ($row['correo'] ?? '')));
        if ($mail !== '' && isset($clienteByCorreo[$mail])) {
            $cid = (int) $clienteByCorreo[$mail];
        }
    }
    $row['cliente_id'] = $cid;
    $row['es_cliente'] = $cid > 0;
}
unset($row);

echo json_encode([
    'data' => $data,
    'filters' => [
        'period' => $filters['period'],
        'status' => $filters['status'],
        'origen' => $filters['origen'],
        'date_from' => $filters['dateFrom'],
        'date_to' => $filters['dateTo'],
        'total' => count($data),
    ],
], JSON_UNESCAPED_UNICODE);
