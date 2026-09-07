<?php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_config.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__, 2) . '/website/includes/leads_helpers.php';

cw_hub_migrate($conn);

function cw_web_create_fail(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    cw_web_create_fail('Método no permitido', 405);
}

$nombre = trim((string) ($_POST['nombre'] ?? ''));
$apellido = trim((string) ($_POST['apellido'] ?? ''));
$correo = trim((string) ($_POST['correo'] ?? ''));
$empresa = trim((string) ($_POST['empresa'] ?? ''));
$telefonoLocal = cw_web_lead_normalize_mx_phone((string) ($_POST['telefono'] ?? ''));
$servicio = trim((string) ($_POST['servicio'] ?? 'otro'));
$requerimiento = trim((string) ($_POST['requerimiento'] ?? ''));
$nota = trim((string) ($_POST['nota'] ?? ''));

if (mb_strlen($nombre) > 150) {
    $nombre = mb_substr($nombre, 0, 150);
}
if (mb_strlen($apellido) > 150) {
    $apellido = mb_substr($apellido, 0, 150);
}
if (mb_strlen($empresa) > 150) {
    $empresa = mb_substr($empresa, 0, 150);
}

$servicios = CW_HUB_SERVICIOS;
if ($servicio === '' || !isset($servicios[$servicio])) {
    $servicio = '';
}
$servicioLabel = $servicio !== '' ? $servicios[$servicio] : 'Registro manual';

$telefono = '';
$pais = 'México';
if (strlen($telefonoLocal) === 10) {
    $telefono = '+52' . $telefonoLocal;
} elseif ($telefonoLocal !== '') {
    $telefono = $telefonoLocal;
}

$fuente = 'registro_manual';
$estatus = 'Activo';
$pipeline = 'lead';
$origenWeb = 1;
$eliminado = 0;
$now = date('Y-m-d H:i:s');
$uid = (int) ($_SESSION['uid'] ?? 0);
$paginaOrigen = 'Admin — Leads Website';

if ($requerimiento === '' && $nota !== '') {
    $requerimiento = $nota;
} elseif ($requerimiento === '') {
    $requerimiento = $servicioLabel;
}

$notas = [[
    'nota' => 'Registro manual desde Leads Website. Servicio: ' . $servicioLabel,
    'fecha' => $now,
    'usuario_id' => $uid,
]];
if ($nota !== '') {
    $notas[] = [
        'nota' => $nota,
        'fecha' => $now,
        'usuario_id' => $uid,
        'tipo' => 'gestion',
    ];
}
$notasJson = json_encode($notas, JSON_UNESCAPED_UNICODE);
if ($notasJson === false) {
    $notasJson = '[]';
}

$hasApellido = false;
$colChk = $conn->query("SHOW COLUMNS FROM leads LIKE 'apellido'");
if ($colChk && $colChk->num_rows > 0) {
    $hasApellido = true;
}

if ($hasApellido) {
    $ins = $conn->prepare("INSERT INTO leads
        (nombre, apellido, correo, telefono, requerimiento, empresa, pais, estatus, notas, usuario_registro,
         servicio, pagina_origen, fuente, pipeline_estado, ultima_interaccion, origen_web, eliminado, fecha_registro)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$ins) {
        error_log('[website/create] prepare: ' . $conn->error);
        cw_web_create_fail('No se pudo preparar el registro del lead', 500);
    }
    $ins->bind_param(
        'sssssssssisssssiis',
        $nombre,
        $apellido,
        $correo,
        $telefono,
        $requerimiento,
        $empresa,
        $pais,
        $estatus,
        $notasJson,
        $uid,
        $servicio,
        $paginaOrigen,
        $fuente,
        $pipeline,
        $now,
        $origenWeb,
        $eliminado,
        $now
    );
} else {
    $nombreStore = trim($nombre . ($apellido !== '' ? ' ' . $apellido : ''));
    $ins = $conn->prepare("INSERT INTO leads
        (nombre, correo, telefono, requerimiento, empresa, pais, estatus, notas, usuario_registro,
         servicio, pagina_origen, fuente, pipeline_estado, ultima_interaccion, origen_web, eliminado, fecha_registro)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$ins) {
        error_log('[website/create] prepare: ' . $conn->error);
        cw_web_create_fail('No se pudo preparar el registro del lead', 500);
    }
    $ins->bind_param(
        'ssssssssisssssiis',
        $nombreStore,
        $correo,
        $telefono,
        $requerimiento,
        $empresa,
        $pais,
        $estatus,
        $notasJson,
        $uid,
        $servicio,
        $paginaOrigen,
        $fuente,
        $pipeline,
        $now,
        $origenWeb,
        $eliminado,
        $now
    );
}

if (!$ins->execute()) {
    error_log('[website/create] execute: ' . $ins->error);
    $ins->close();
    cw_web_create_fail('No se pudo registrar el lead', 500);
}

$leadId = (int) $ins->insert_id;
$ins->close();

if ($leadId <= 0) {
    cw_web_create_fail('No se obtuvo el ID del lead', 500);
}

$act = $conn->prepare("INSERT INTO cw_lead_actividades (lead_id, tipo, descripcion, usuario_id, created_at) VALUES (?, 'seguimiento', ?, ?, ?)");
if ($act) {
    $desc = 'Lead registrado manualmente desde Leads Website.';
    $act->bind_param('isis', $leadId, $desc, $uid, $now);
    $act->execute();
    $act->close();
}

$origenInfo = cw_web_lead_origen_info($fuente, $paginaOrigen);

echo json_encode([
    'success' => true,
    'message' => 'Lead registrado correctamente',
    'lead_id' => $leadId,
    'origen_label' => $origenInfo['label'],
], JSON_UNESCAPED_UNICODE);
