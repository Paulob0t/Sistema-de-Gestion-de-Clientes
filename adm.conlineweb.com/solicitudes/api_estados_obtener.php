<?php
require_once __DIR__.'/../auth_middleware.php';
require_once __DIR__ . '/bootstrap_conexion.php';

header('Content-Type: application/json; charset=utf-8');

$ids = [];
if (!empty($_REQUEST['ids'])) {
    $raw = trim($_REQUEST['ids']);
    $parts = preg_split('/[\,\s]+/', $raw);
    foreach ($parts as $p) {
        $i = (int)$p;
        if ($i > 0) $ids[] = $i;
    }
}

if (empty($ids)) {
    echo json_encode(['success' => false, 'error' => 'No ids']);
    exit;
}

$in = implode(',', array_map('intval', $ids));

try {
    $sql = "SELECT id, estado, validado FROM solicitudes WHERE id IN ($in)";
    $res = $conexion->query(query: $sql);
    $out = ['success' => true, 'items' => []];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $out['items'][(int)$r['id']] = ['estado' => $r['estado'], 'validado' => (int)$r['validado']];
        }
    }
    echo json_encode($out);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>
