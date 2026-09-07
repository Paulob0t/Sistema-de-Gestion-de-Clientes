<?php
header('Content-Type: application/json');
require_once 'conn.php'; // Ajusta la ruta si es necesario

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_proyecto'])) {
    $id = intval($_POST['id_proyecto']);
    
    // Obtener el estado actual de 'publicada'
    $sql = "SELECT publicada FROM proyectos WHERE id_proyecto = $id";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $nuevoEstado = $row['publicada'] ? 0 : 1;
        
        // Actualizar
        $update = "UPDATE proyectos SET publicada = $nuevoEstado WHERE id_proyecto = $id";
        if ($conn->query($update)) {
            $response['success'] = true;
            $response['publicada'] = $nuevoEstado;
            $response['message'] = $nuevoEstado ? 'Proyecto marcado como público.' : 'Proyecto marcado como no público.';
        } else {
            $response['message'] = 'Error al actualizar: ' . $conn->error;
        }
    } else {
        $response['message'] = 'Proyecto no encontrado.';
    }
} else {
    $response['message'] = 'Solicitud inválida.';
}

echo json_encode($response);
?>