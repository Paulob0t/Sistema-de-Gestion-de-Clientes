<?php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_config.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_migrate.php';

cw_hub_migrate($conn);

$id = (int) ($_POST['id'] ?? 0);
$estado = trim((string) ($_POST['pipeline_estado'] ?? ''));
$allowed = array_keys(CW_HUB_WEBSITE_PIPELINE);

if ($id <= 0 || !in_array($estado, $allowed, true)) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

$now = date('Y-m-d H:i:s');
$stmt = $conn->prepare('UPDATE leads SET pipeline_estado = ?, ultima_interaccion = ? WHERE id = ? AND eliminado = 0 AND origen_web = 1');
$stmt->bind_param('ssi', $estado, $now, $id);
$ok = $stmt->execute();
$stmt->close();

if ($ok) {
    $label = CW_HUB_WEBSITE_PIPELINE[$estado] ?? $estado;
    $desc = 'Estatus website: ' . $label;
    $uid = (int) ($_SESSION['uid'] ?? 0);
    $act = $conn->prepare('INSERT INTO cw_lead_actividades (lead_id, tipo, descripcion, usuario_id, created_at) VALUES (?, ?, ?, ?, ?)');
    $tipo = 'seguimiento';
    $act->bind_param('issis', $id, $tipo, $desc, $uid, $now);
    $act->execute();
    $act->close();
}

echo json_encode([
    'success' => $ok,
    'label' => CW_HUB_WEBSITE_PIPELINE[$estado] ?? $estado,
]);
