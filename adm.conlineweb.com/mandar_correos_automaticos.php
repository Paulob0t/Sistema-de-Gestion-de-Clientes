<?php
header('Content-Type: application/json');
ob_start();
error_reporting(E_ALL);
ini_set("display_errors", 1);

include "conn.php";
$sqlPagos = "SELECT * FROM pagos WHERE estatus = 0";
$resultPagos = $conn->query($sqlPagos);

// Verificar si hay resultados y convertirlos a array
$pagos = [];
if ($resultPagos && $resultPagos->num_rows > 0) {
    $pagos = $resultPagos->fetch_all(MYSQLI_ASSOC);

 $baseUrl = 'https://adm.conlineweb.com/'; // Cambia esto a tu dominio o ruta local

foreach ($pagos as $pago) {
    $id = $pago['id'];
    $tipo = $pago['tipo_servicio'];

    if ($tipo == 1) {
        $url = $baseUrl . 'reenviar_correos_hosting.php';
    } elseif ($tipo == 2) {
        $url = $baseUrl . 'reenviar_correos_dominios.php';
    } else {
        continue;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, ['id' => $id]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo "Error al enviar correo para el pago ID $id: " . curl_error($ch);
    } else {
        echo "Respuesta para el pago ID $id: $response";
    }

    curl_close($ch);
}

}



?>
