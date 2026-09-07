<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
include "conn.php";

$id      = isset($_POST['id'])      ? (int)$_POST['id']      : 0;
$estatus = isset($_POST['estatus']) ? (int)$_POST['estatus'] : 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

// Asegurar que estatus solo sea 0 o 1
$estatus = $estatus === 1 ? 1 : 0;

$stmt = $conn->prepare("UPDATE briefings SET estatus = ? WHERE id = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Error al preparar consulta']);
    exit;
}

$stmt->bind_param('ii', $estatus, $id);
$ok = $stmt->execute();
$stmt->close();

echo json_encode([
    'success' => $ok,
    'message' => $ok ? 'Estatus actualizado' : 'No se pudo actualizar'
]);