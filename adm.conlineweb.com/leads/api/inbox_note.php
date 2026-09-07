<?php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_permissions.php';
require_once dirname(__DIR__) . '/includes/inbox_helpers.php';

cw_hub_migrate($conn);
cw_hub_ensure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$leadId = (int) ($_POST['id'] ?? 0);
$nota = trim((string) ($_POST['nota'] ?? ''));
$tipo = in_array($_POST['tipo'] ?? '', ['nota', 'llamada', 'mensaje', 'email', 'seguimiento'], true)
    ? $_POST['tipo'] : 'nota';

if ($leadId <= 0 || $nota === '') {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$stmt = $conn->prepare('SELECT * FROM leads WHERE id = ? AND eliminado = 0 LIMIT 1');
$stmt->bind_param('i', $leadId);
$stmt->execute();
$lead = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$lead) {
    echo json_encode(['success' => false, 'message' => 'Lead no encontrado']);
    exit;
}

if (!cw_hub_can_edit_lead($lead)) {
    echo json_encode(['success' => false, 'message' => 'Sin permiso para editar']);
    exit;
}

$uid = cw_inbox_uid();
$now = date('Y-m-d H:i:s');

$ins = $conn->prepare('INSERT INTO cw_lead_actividades (lead_id, tipo, descripcion, usuario_id, created_at) VALUES (?, ?, ?, ?, ?)');
$ins->bind_param('issis', $leadId, $tipo, $nota, $uid, $now);
if (!$ins->execute()) {
    echo json_encode(['success' => false, 'message' => 'No se pudo guardar la nota']);
    exit;
}
$ins->close();

$notas = json_decode($lead['notas'] ?? '[]', true);
if (!is_array($notas)) {
    $notas = [];
}
$notas[] = [
    'nota' => $nota,
    'fecha' => $now,
    'usuario_id' => $uid,
    'tipo' => $tipo,
];
$notasJson = json_encode($notas, JSON_UNESCAPED_UNICODE);

$upd = $conn->prepare('UPDATE leads SET notas = ?, ultima_interaccion = ?, inbox_leido_at = ? WHERE id = ?');
$upd->bind_param('sssi', $notasJson, $now, $now, $leadId);
$upd->execute();
$upd->close();

echo json_encode([
    'success' => true,
    'message' => 'Nota registrada',
    'fecha' => $now,
    'usuario' => cw_inbox_user_name($conn, $uid),
], JSON_UNESCAPED_UNICODE);
