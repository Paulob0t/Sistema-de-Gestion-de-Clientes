<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

session_start();
// if (!isset($_SESSION['uid'])) {
//     echo json_encode(['success' => false, 'message' => 'No autorizado']);
//     exit;
// }

$clienteId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$usuarioId = (int) $_SESSION['uid'];

if ($clienteId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de cliente no válido']);
    exit;
}

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/conn_hostingpro.php';
require_once __DIR__ . '/includes/transferencia_cliente_service.php';

$resultado = transferir_cliente_a_hostingpro($conn, $conn_hp, $clienteId, $usuarioId);

if ($resultado['success']) {
    echo json_encode([
        'success' => true,
        'message' => $resultado['message'],
        'cliente_id' => $resultado['data']['cliente_id'] ?? $clienteId,
        'dominios' => $resultado['data']['dominios'] ?? 0,
        'hostings' => $resultado['data']['hostings'] ?? 0,
        'pagos' => $resultado['data']['pagos'] ?? 0,
    ]);
    exit;
}

echo json_encode([
    'success' => false,
    'message' => $resultado['message'],
]);
