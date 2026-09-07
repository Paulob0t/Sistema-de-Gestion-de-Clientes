<?php
include "conn.php";

// Obtener mes y año actuales
$mes_inicio = date('n');
$anio_inicio = date('Y');

// Calcular la fecha de inicio (primer día del mes actual)
$fecha_inicio = sprintf('%04d-%02d-01', $anio_inicio, $mes_inicio);

// Calcular la fecha de fin (último día del mes siguiente)
    $fecha_fin = date('Y-m-t', strtotime("+1 month", strtotime($fecha_inicio)));

// Consulta SQL (sin filtrar estatus)
$sql_pagos = "SELECT id, id_clie AS cliente_id, concepto AS nombre, fecha_limite_pago, tipo_servicio and estatus = 0
              FROM pagos 
              WHERE fecha_limite_pago BETWEEN ? AND ? 
              ORDER BY fecha_limite_pago ASC";

$stmt = $conn->prepare($sql_pagos);
$stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
$stmt->execute();
$res = $stmt->get_result();

$servicios = [];
while($row = $res->fetch_assoc()) {
    $tipo = $row['tipo_servicio'] == 2 ? 'dominio' : ($row['tipo_servicio'] == 1 ? 'hosting' : 'pago');
    $servicios[] = [
        'tipo' => $tipo,
        'cliente_id' => $row['cliente_id'],
        'nombre' => $row['nombre'],
        'fecha_limite_pago' => $row['fecha_limite_pago']
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Servicios por Fecha de Pago</title>
</head>
<body>
    <h3>Servicios con pago entre <?= date('d/M/Y', strtotime($fecha_inicio)) ?> y <?= date('d/M/Y', strtotime($fecha_fin)) ?>:</h3>
    <table border="1" cellpadding="6">
        <thead>
            <tr>
                <th>Tipo</th>
                <th>ID Cliente</th>
                <th>Nombre</th>
                <th>Fecha de Pago</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($servicios as $s): ?>
            <tr>
                <td><?= ucfirst($s['tipo']) ?></td>
                <td><?= $s['cliente_id'] ?? '-' ?></td>
                <td><?= htmlspecialchars($s['nombre']) ?></td>
                <td><?= date('d/m/Y', strtotime($s['fecha_limite_pago'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($servicios)): ?>
            <tr><td colspan="4">No hay servicios en el rango seleccionado.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
