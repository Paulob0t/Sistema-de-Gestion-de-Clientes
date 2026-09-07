<?php
header('Content-Type: application/json');
include 'conn.php';

if (!isset($_POST['id_proyecto']) || empty($_POST['id_proyecto'])) {
    echo json_encode(['success' => false, 'message' => 'ID de proyecto no proporcionado']);
    exit;
}

$id_proyecto = (int)$_POST['id_proyecto'];

// Obtener el estado actual
$sql = "SELECT activo FROM proyectos WHERE id_proyecto = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id_proyecto);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Proyecto no encontrado']);
    exit;
}

$row = $result->fetch_assoc();
$actual = (int)$row['activo'];
$nuevo = ($actual === 1) ? 0 : 1;

// Actualizar el estado
$sql_update = "UPDATE proyectos SET activo = ? WHERE id_proyecto = ?";
$stmt_update = $conn->prepare($sql_update);
$stmt_update->bind_param('ii', $nuevo, $id_proyecto);

if ($stmt_update->execute()) {
    echo json_encode([
        'success' => true,
        'activo' => $nuevo,
        'message' => 'Estado actualizado correctamente'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar el estado'
    ]);
}

$stmt->close();
$stmt_update->close();
$conn->close();
?>
