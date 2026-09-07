<?php
require_once __DIR__ . '/auth_middleware.php';
include 'conn.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'Solicitud inválida']);
    exit;
}

$id   = intval($_POST['id']);
$stmt = $conn->prepare("DELETE FROM briefings WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'No se encontró el registro']);
}
$stmt->close();
$conn->close();