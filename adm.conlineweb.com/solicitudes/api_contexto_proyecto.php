<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../auth_middleware.php';
require_once __DIR__ . '/bootstrap_conexion.php';
require_once __DIR__ . '/../includes/requerimientos_contexto_service.php';

$idCliente = isset($_GET['id_cliente']) ? (int) $_GET['id_cliente'] : 0;
$idProyecto = isset($_GET['id_proyecto']) ? (int) $_GET['id_proyecto'] : 0;

if ($idCliente <= 0 && $idProyecto <= 0) {
    echo json_encode([
        'success' => true,
        'contexto' => ['cliente' => null, 'proyecto' => null, 'texto' => ''],
        'saludo' => requerimientos_saludo_con_contexto(['cliente' => null, 'proyecto' => null]),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$contexto = requerimientos_cargar_contexto($conn, $idCliente > 0 ? $idCliente : null, $idProyecto > 0 ? $idProyecto : null);

echo json_encode([
    'success' => true,
    'contexto' => $contexto,
    'saludo' => requerimientos_saludo_con_contexto($contexto),
], JSON_UNESCAPED_UNICODE);
