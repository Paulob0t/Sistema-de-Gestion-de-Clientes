<?php
require_once dirname(__DIR__) . '/conn.php';

foreach (['hosting', 'dominios', 'pagos', 'planes'] as $t) {
    echo "=== $t ===\n";
    $r = $conn->query("SHOW COLUMNS FROM $t");
    while ($c = $r->fetch_assoc()) {
        echo $c['Field'] . "\n";
    }
}

$tests = [
    "SELECT h.id_orden AS id, h.dominio, h.fecha_pago, h.estado_producto AS estatus, h.nom_host, h.url_acceso, p.nombre AS nombre_plan FROM hosting h LEFT JOIN planes p ON h.producto = p.id WHERE h.cliente_id = 1 AND h.eliminado = 0 LIMIT 1",
    "SELECT d.id_dominio AS id, d.url_dominio AS dominio, d.fecha_pago, d.estado_dominio AS estatus FROM dominios d WHERE d.cliente_id = 1 AND d.eliminado = 0 LIMIT 1",
];
foreach ($tests as $sql) {
    $r = $conn->query($sql);
    echo ($r ? 'OK' : 'FAIL ' . $conn->error) . " :: " . substr($sql, 0, 60) . "\n";
    if ($r && $row = $r->fetch_assoc()) {
        echo json_encode($row) . "\n";
    }
}
