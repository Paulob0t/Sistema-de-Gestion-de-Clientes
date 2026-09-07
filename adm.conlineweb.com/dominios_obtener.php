<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

include 'conn.php';
include 'conn_hostingpro.php';

$sistema = (isset($_GET['sistema']) && $_GET['sistema'] === 'hostingpro') ? 'hostingpro' : 'conlineweb';
$db = ($sistema === 'hostingpro') ? $conn_hp : $conn;
$db->set_charset("utf8");

$sql = "SELECT d.id_dominio AS id, d.url_dominio, d.usuario, d.contrasena, c.nombre_contacto
        FROM dominios d
        LEFT JOIN clientes c ON d.cliente_id = c.id
        WHERE d.eliminado = 0";

$cliente_id = null;
if (isset($_GET['cliente_id']) && intval($_GET['cliente_id']) > 0) {
    $cliente_id = intval($_GET['cliente_id']);
} elseif (isset($_GET['id']) && intval($_GET['id']) > 0) {
    $cliente_id = intval($_GET['id']);
}

if ($cliente_id) {
    $sql .= " AND d.cliente_id = ?";
}

$stmt = $db->prepare($sql);
if ($stmt && $cliente_id) {
    $stmt->bind_param('i', $cliente_id);
}

$result = false;
if ($stmt && $stmt->execute()) {
    $result = $stmt->get_result();
}

$todosDominios = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $todosDominios[] = $row;
    }
}

echo json_encode($todosDominios);

if (isset($stmt)) {
    $stmt->close();
}
if (isset($conn)) {
    $conn->close();
}
if (isset($conn_hp)) {
    $conn_hp->close();
}
