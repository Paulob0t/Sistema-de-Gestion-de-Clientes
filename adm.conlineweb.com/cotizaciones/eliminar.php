<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../auth_middleware.php';
require_once __DIR__ . '/db/conexion.php';

$input = json_decode(file_get_contents('php://input'), true);
$id = isset($input['id']) ? (int)$input['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID inválido']);
    exit;
}

$stmt = $conexion->prepare("SELECT pdf_path FROM cotizaciones WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    if (!empty($row['pdf_path']) && file_exists($row['pdf_path'])) {
        unlink($row['pdf_path']);
    }
}

$stmt = $conexion->prepare("UPDATE cotizaciones SET estatus = 'Cancelada' WHERE id = ?");
$stmt->bind_param('i', $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $stmt->error]);
}
