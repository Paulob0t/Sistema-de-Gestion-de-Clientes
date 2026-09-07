<?php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_config.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_permissions.php';

cw_hub_migrate($conn);
cw_hub_ensure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$ids = [];
if (isset($_POST['ids']) && is_array($_POST['ids'])) {
    foreach ($_POST['ids'] as $rawId) {
        $ids[] = (int) $rawId;
    }
} elseif (isset($_POST['ids']) && is_string($_POST['ids'])) {
    foreach (preg_split('/\s*,\s*/', $_POST['ids']) as $rawId) {
        $ids[] = (int) $rawId;
    }
} else {
    $ids[] = (int) ($_POST['id'] ?? 0);
}

$ids = array_values(array_unique(array_filter($ids, static function ($id) {
    return $id > 0;
})));

if ($ids === []) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

$now = date('Y-m-d H:i:s');
$uid = cw_hub_user_id();
$okCount = 0;
$failCount = 0;

$sel = $conn->prepare('SELECT id, nombre FROM leads WHERE id = ? AND eliminado = 0 AND origen_web = 1 LIMIT 1');
$upd = $conn->prepare('UPDATE leads SET eliminado = 1, ultima_interaccion = ? WHERE id = ? AND origen_web = 1');
$act = $conn->prepare('INSERT INTO cw_lead_actividades (lead_id, tipo, descripcion, usuario_id, created_at) VALUES (?, ?, ?, ?, ?)');
$tipo = 'seguimiento';
$desc = 'Lead dado de baja desde Leads Website.';

foreach ($ids as $id) {
    $sel->bind_param('i', $id);
    $sel->execute();
    $lead = $sel->get_result()->fetch_assoc();
    if (!$lead) {
        $failCount++;
        continue;
    }

    $upd->bind_param('si', $now, $id);
    if (!$upd->execute()) {
        $failCount++;
        continue;
    }

    $act->bind_param('issis', $id, $tipo, $desc, $uid, $now);
    $act->execute();
    $okCount++;
}

$sel->close();
$upd->close();
$act->close();

$total = count($ids);
$ok = $okCount > 0;

if ($total === 1) {
    $message = $ok ? 'Lead dado de baja correctamente' : 'No se pudo dar de baja';
} elseif ($failCount === 0) {
    $message = $okCount . ' leads dados de baja correctamente';
} elseif ($okCount === 0) {
    $message = 'No se pudo dar de baja ninguno de los leads seleccionados';
} else {
    $message = $okCount . ' dados de baja, ' . $failCount . ' no encontrados o con error';
}

echo json_encode([
    'success' => $ok,
    'deleted' => $okCount,
    'failed' => $failCount,
    'message' => $message,
], JSON_UNESCAPED_UNICODE);
