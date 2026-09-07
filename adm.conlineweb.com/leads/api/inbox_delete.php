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

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

$stmt = $conn->prepare('SELECT * FROM leads WHERE id = ? AND eliminado = 0 LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$lead = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$lead) {
    echo json_encode(['success' => false, 'message' => 'Lead no encontrado']);
    exit;
}

if (!cw_hub_can_edit_lead($lead)) {
    echo json_encode(['success' => false, 'message' => 'Sin permiso para dar de baja']);
    exit;
}

$now = date('Y-m-d H:i:s');
$uid = cw_inbox_uid();

$upd = $conn->prepare('UPDATE leads SET eliminado = 1, ultima_interaccion = ? WHERE id = ?');
$upd->bind_param('si', $now, $id);
$ok = $upd->execute();
$upd->close();

if ($ok) {
    $desc = 'Lead dado de baja desde Bandeja CRM.';
    $act = $conn->prepare('INSERT INTO cw_lead_actividades (lead_id, tipo, descripcion, usuario_id, created_at) VALUES (?, ?, ?, ?, ?)');
    $tipo = 'seguimiento';
    $act->bind_param('issis', $id, $tipo, $desc, $uid, $now);
    $act->execute();
    $act->close();
}

echo json_encode([
    'success' => $ok,
    'message' => $ok ? 'Lead dado de baja correctamente' : 'No se pudo dar de baja',
], JSON_UNESCAPED_UNICODE);
