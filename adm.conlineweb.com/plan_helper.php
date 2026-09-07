<?php
// Devuelve el nombre del plan según el id
function obtenerNombrePlan($conn, $id_plan) {
    $nombre = '';
    if ($id_plan) {
        $sql = "SELECT nombre FROM planes WHERE id = " . intval($id_plan) . " LIMIT 1";
        $result = $conn->query($sql);
        if ($result && $row = $result->fetch_assoc()) {
            $nombre = $row['nombre'];
        }
    }
    return $nombre;
}
?>
