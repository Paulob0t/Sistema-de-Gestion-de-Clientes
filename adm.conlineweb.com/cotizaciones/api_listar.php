<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/../auth_middleware.php';
require_once __DIR__ . '/db/conexion.php';

$estatus = $_GET['estatus'] ?? 'Activa';
$limit = min((int)($_GET['limit'] ?? 50), 200);
$offset = max((int)($_GET['offset'] ?? 0), 0);

$where = '';
$params = [];
$types = '';

if ($estatus !== 'Todas') {
    $where = "WHERE c.estatus = ?";
    $params[] = $estatus;
    $types .= 's';
}

$params[] = $limit;
$types .= 'i';
$params[] = $offset;
$types .= 'i';

$totalQuery = "SELECT COUNT(*) AS total FROM cotizaciones c {$where}";
$totalResult = $conexion->query($totalQuery);
$totalCount = 0;
if ($totalResult) {
    $totalRow = $totalResult->fetch_assoc();
    $totalCount = (int)($totalRow['total'] ?? 0);
}

$stmt = $conexion->prepare("
    SELECT c.id, c.folio, c.cliente_nombre, c.proyecto_nombre,
           c.subtotal, c.descuento_porcentaje, c.descuento_monto, c.total,
           c.moneda, c.estatus, c.created_at
    FROM cotizaciones c
    {$where}
    ORDER BY c.created_at DESC
    LIMIT ? OFFSET ?
");

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$cotizaciones = [];
while ($row = $result->fetch_assoc()) {
    $cotizaciones[] = [
        'id' => (int)$row['id'],
        'folio' => $row['folio'],
        'cliente_nombre' => $row['cliente_nombre'] ?? '-',
        'proyecto_nombre' => $row['proyecto_nombre'] ?? '-',
        'subtotal' => (float)$row['subtotal'],
        'descuento_porcentaje' => (float)$row['descuento_porcentaje'],
        'descuento_monto' => (float)$row['descuento_monto'],
        'total' => (float)$row['total'],
        'moneda' => $row['moneda'],
        'estatus' => $row['estatus'],
        'created_at' => $row['created_at'],
    ];
}

echo json_encode([
    'success' => true,
    'cotizaciones' => $cotizaciones,
    'total' => $totalCount,
]);
