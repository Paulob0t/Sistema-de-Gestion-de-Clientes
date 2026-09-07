<?php

/**
 * Conexión unificada para el módulo solicitudes.
 * En producción usa conn.php del root; en local, db/conexion.php.
 */
if (!isset($conexion) || !($conexion instanceof mysqli)) {
    $conexion = null;
}
if (!isset($conn) || !($conn instanceof mysqli)) {
    $conn = null;
}

if (!$conexion && !$conn) {
    $rootConn = __DIR__ . '/../conn.php';
    if (is_file($rootConn)) {
        require_once $rootConn;
        if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
            $conexion = $conn;
        }
    }
    if (!$conexion) {
        $localConn = __DIR__ . '/db/conexion.php';
        if (!is_file($localConn)) {
            http_response_code(500);
            die('Error: no se encontró archivo de conexión (conn.php ni db/conexion.php).');
        }
        require_once $localConn;
    }
}

if (isset($conexion) && $conexion instanceof mysqli && (!$conn || !($conn instanceof mysqli))) {
    $conn = $conexion;
} elseif (isset($conn) && $conn instanceof mysqli && (!$conexion || !($conexion instanceof mysqli))) {
    $conexion = $conn;
}

if (!$conexion || $conexion->connect_error) {
    http_response_code(500);
    die('Error de conexión a la base de datos.');
}
