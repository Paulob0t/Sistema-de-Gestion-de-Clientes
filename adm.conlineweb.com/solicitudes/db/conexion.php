<?php
$conexion = new mysqli("localhost", "admin_clientes", "NPJidzGipy-@", "admin_clientes");

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}
?>
