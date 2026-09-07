<?php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__, 2) . '/website/includes/leads_helpers.php';

cw_hub_migrate($conn);

$id = (int) ($_POST['id'] ?? 0);
$notaNueva = trim((string) ($_POST['nota'] ?? ''));

if ($id <= 0 || $notaNueva === '') {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$stmt = $conn->prepare('SELECT notas FROM leads WHERE id = ? AND eliminado = 0 AND origen_web = 1 LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Lead no encontrado']);
    exit;
}

$notas = json_decode($row['notas'] ?? '[]', true);
if (!is_array($notas)) {
    $notas = [];
}

$notas[] = [
    'nota' => $notaNueva,
    'fecha' => date('Y-m-d H:i:s'),
    'usuario_id' => (int) ($_SESSION['uid'] ?? 0),
    'tipo' => 'gestion',
];

$notasJson = json_encode($notas, JSON_UNESCAPED_UNICODE);
$now = date('Y-m-d H:i:s');

$upd = $conn->prepare('UPDATE leads SET notas = ?, ultima_interaccion = ? WHERE id = ? AND eliminado = 0 AND origen_web = 1');
$upd->bind_param('ssi', $notasJson, $now, $id);
$ok = $upd->execute();
$err = $ok ? '' : ($upd->error ?: $conn->error);
$upd->close();

if (!$ok) {
    echo json_encode(['success' => false, 'message' => 'No se pudo guardar: ' . $err], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success' => true,
    'ultima_nota' => cw_web_lead_note_preview($notaNueva),
    'ultima_nota_full' => $notaNueva,
    'total_notas' => count($notas),
    'notas_json' => $notasJson,
]);
