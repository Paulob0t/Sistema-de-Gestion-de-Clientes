<?php
header('Content-Type: application/json');
include 'conn.php';

$response = ['success' => false, 'message' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_proyecto']) && isset($_POST['nueva_posicion'])) {
    $id = (int)$_POST['id_proyecto'];
    $nueva = (int)$_POST['nueva_posicion'];
    
    // Verificar que no esté ocupada por otro proyecto
    $check = $conn->query("SELECT id_proyecto FROM proyectos WHERE posicion = $nueva AND id_proyecto != $id");
    if ($check && $check->num_rows > 0) {
        $response['message'] = 'La posición ya está ocupada por otro proyecto.';
    } else {
        $update = "UPDATE proyectos SET posicion = $nueva WHERE id_proyecto = $id";
        if ($conn->query($update)) {
            $response['success'] = true;
            $response['message'] = 'Posición actualizada correctamente.';
        } else {
            $response['message'] = 'Error al actualizar: ' . $conn->error;
        }
    }
} else {
    $response['message'] = 'Solicitud inválida.';
}
echo json_encode($response);
?>