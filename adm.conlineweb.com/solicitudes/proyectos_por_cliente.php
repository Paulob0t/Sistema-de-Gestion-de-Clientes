<?php
// Endpoint: devuelve proyectos por cliente para poblar el select din¨¢mico.
// Ajuste: se usa la misma conexi¨®n que el resto del sistema (conn.php) para evitar
// inconsistencias entre host "localhost" y host remoto (cpanel.conlineweb.com).
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function respond($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

require_once __DIR__ . '/bootstrap_conexion.php';

if(!$conexion || !($conexion instanceof mysqli) || $conexion->connect_error){
    respond(['success'=>false,'message'=>'No se pudo establecer conexi¨®n a la base de datos']);
}

try {
    if (!isset($_GET['id_cliente'])) {
        throw new Exception('Falta id_cliente');
    }
    $id = filter_var($_GET['id_cliente'], FILTER_VALIDATE_INT);
    if ($id === false) throw new Exception('id_cliente inv¨¢lido');

    $sql = 'SELECT id_proyecto, nombre_proyecto FROM proyectos WHERE id_cliente = ? ORDER BY id_proyecto DESC';
    $stmt = $conexion->prepare($sql);
    if (!$stmt) throw new Exception('Error al preparar consulta');
    $stmt->bind_param('i', $id);
    if(!$stmt->execute()) throw new Exception('Error al ejecutar');
    $res = $stmt->get_result();
    $proyectos = [];
    while ($row = $res->fetch_assoc()) {
        $proyectos[] = [
            'id_proyecto' => (int)$row['id_proyecto'],
            'nombre_proyecto' => $row['nombre_proyecto']
        ];
    }
    $stmt->close();

    respond(['success' => true, 'count'=>count($proyectos), 'proyectos' => $proyectos]);
} catch (Exception $e) {
    respond(['success' => false, 'message' => $e->getMessage()]);
}
