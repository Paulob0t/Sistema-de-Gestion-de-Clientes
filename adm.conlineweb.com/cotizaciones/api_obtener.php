<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/../auth_middleware.php';
require_once __DIR__ . '/db/conexion.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID inválido']);
    exit;
}

$stmt = $conexion->prepare("
    SELECT c.*, u.usuario AS created_by_name
    FROM cotizaciones c
    LEFT JOIN usuarios u ON c.created_by = u.id
    WHERE c.id = ?
");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Cotización no encontrada']);
    exit;
}

$row = $result->fetch_assoc();

$items = json_decode($row['items'], true) ?? [];
$solicitudesIds = json_decode($row['solicitudes_ids'], true) ?? [];

echo json_encode([
    'success' => true,
    'cotizacion' => [
        'id' => (int)$row['id'],
        'folio' => $row['folio'],
        'cliente_nombre' => $row['cliente_nombre'] ?? '-',
        'proyecto_nombre' => $row['proyecto_nombre'] ?? '-',
        'solicitudes_ids' => $solicitudesIds,
        'items' => $items,
        'subtotal' => (float)$row['subtotal'],
        'descuento_porcentaje' => (float)$row['descuento_porcentaje'],
        'descuento_monto' => (float)$row['descuento_monto'],
        'total' => (float)$row['total'],
        'moneda' => $row['moneda'],
        'estatus' => $row['estatus'],
        'pdf_path' => $row['pdf_path'],
        'created_by' => $row['created_by_name'] ?? '',
        'created_at' => $row['created_at'],
    ],
]);
