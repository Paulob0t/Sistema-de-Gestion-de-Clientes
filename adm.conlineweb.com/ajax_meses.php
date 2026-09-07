<?php
include "conn.php";
include "conn_hostingpro.php";

$sistema = (isset($_POST['sistema']) && $_POST['sistema'] === 'hostingpro') ? 'hostingpro' : 'conlineweb';
$conn = ($sistema === 'hostingpro') ? $conn_hp : $conn;

header('Content-Type: application/json');

$response = ['success' => false, 'meses' => []];

if (isset($_POST['anio']) && !empty($_POST['anio'])) {
    $anio = $conn->real_escape_string($_POST['anio']);
    
    $sql = "SELECT 
        MONTH(fecha_pago) as mes_numero,
        COUNT(*) as total_pagos
    FROM pagos 
    WHERE estatus = 1 
        AND fecha_pago IS NOT NULL 
        AND fecha_pago != '0000-00-00'
        AND YEAR(fecha_pago) = '$anio'
    GROUP BY MONTH(fecha_pago)
    ORDER BY mes_numero ASC";
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $meses = [];
        while ($row = $result->fetch_assoc()) {
            $meses[] = $row;
        }
        $response['meses'] = $meses;
        $response['success'] = true;
    } else {
        $response['success'] = true;
        $response['meses'] = [];
    }
}

echo json_encode($response);
?>