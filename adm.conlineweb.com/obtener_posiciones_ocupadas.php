<?php
header('Content-Type: application/json');
include 'conn.php';

$response = ['success' => false, 'posiciones' => []];
if (isset($_GET['id_proyecto'])) {
    $id = (int)$_GET['id_proyecto'];
    $sql = "SELECT posicion FROM proyectos WHERE id_proyecto != $id AND posicion IS NOT NULL ORDER BY posicion ASC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $response['posiciones'][] = (int)$row['posicion'];
        }
        $response['success'] = true;
    } else {
        $response['message'] = 'Error en la consulta.';
    }
} else {
    $response['message'] = 'Falta ID del proyecto.';
}
echo json_encode($response);
?>