<?php
$conexion = new mysqli("localhost", "root", "", "proyectos");

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}
?>
