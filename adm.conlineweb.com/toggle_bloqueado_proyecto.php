<?php
header('Content-Type: application/json');
include 'conn.php';

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_proyecto'])) {
    $id = (int)$_POST['id_proyecto'];
    
    // Obtener el valor actual de bloqueado
    $query = "SELECT bloqueado FROM proyectos WHERE id_proyecto = $id";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $nuevo_valor = ((int)$row['bloqueado'] == 0) ? 1 : 0;
        
        $update = "UPDATE proyectos SET bloqueado = $nuevo_valor WHERE id_proyecto = $id";
        if ($conn->query($update)) {
            $response['success'] = true;
            $response['bloqueado'] = $nuevo_valor;
            $response['message'] = 'Estado de bloqueo actualizado correctamente.';
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