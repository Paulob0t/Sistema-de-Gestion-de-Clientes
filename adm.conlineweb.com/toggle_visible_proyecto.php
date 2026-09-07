<?php
header('Content-Type: application/json');
include 'conn.php';
session_start();

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_proyecto'])) {
    $id_proyecto = intval($_POST['id_proyecto']);
    
    // Obtener el estado actual de 'mostrar'
    $sql = "SELECT mostrar FROM proyectos WHERE id_proyecto = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_proyecto);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $nuevo_estado = $row['mostrar'] == 1 ? 0 : 1;
        
        // Actualizar el estado
        $update_sql = "UPDATE proyectos SET mostrar = ? WHERE id_proyecto = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ii", $nuevo_estado, $id_proyecto);
        
        if ($update_stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Estado actualizado correctamente';
            $response['mostrar'] = $nuevo_estado;
        } else {
            $response['message'] = 'Error al actualizar el estado';
        }
        $update_stmt->close();
    } else {
        $response['message'] = 'Proyecto no encontrado';
    }
    $stmt->close();
} else {
    $response['message'] = 'Solicitud inválida';
}

echo json_encode($response);
?>