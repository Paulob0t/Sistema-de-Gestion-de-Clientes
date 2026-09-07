<?php
session_start();
require_once '../conn.php';

// Verificar que sea usuario tipo 4 (empresa)
// if(!isset($_SESSION['tipo']) || $_SESSION['tipo'] != 4) {
//     http_response_code(403);
//     echo json_encode(['error' => 'Acceso denegado']);
//     exit;
// }

try {
    $empresa_id = $_SESSION['uid'];
    
    // Obtener el idCliente desde la tabla agentes usando el JSON en idEmpresa
    $query_agente = "SELECT idEmpresa FROM agentes WHERE Idusu = ?";
    $stmt_agente = $conexion->prepare($query_agente);
    $stmt_agente->bind_param("i", $empresa_id);
    $stmt_agente->execute();
    $result_agente = $stmt_agente->get_result();
    
    if ($result_agente->num_rows == 0) {
        echo json_encode(['error' => 'No se encontró información de la empresa']);
        exit;
    }
    
    $agente_data = $result_agente->fetch_assoc();
    $idEmpresa_json = $agente_data['idEmpresa'];
    
    // Decodificar el JSON para obtener el idCliente
    $empresa_info = json_decode($idEmpresa_json, true);
    
    if (!$empresa_info || !isset($empresa_info['idCliente'])) {
        echo json_encode(['error' => 'Información de empresa no válida']);
        exit;
    }
    
    $id_cliente = $empresa_info['idCliente'];
    
    // Obtener los proyectos del cliente
    $query_proyectos = "SELECT id_proyecto as id, nombre_proyecto as nombre, descripcion, fecha_creacion 
                        FROM proyectos 
                        WHERE id_cliente = ? 
                        ORDER BY fecha_creacion DESC";
    
    $stmt_proyectos = $conexion->prepare($query_proyectos);
    $stmt_proyectos->bind_param("i", $id_cliente);
    $stmt_proyectos->execute();
    $result_proyectos = $stmt_proyectos->get_result();
    
    $proyectos = [];
    while ($row = $result_proyectos->fetch_assoc()) {
        $proyectos[] = $row;
    }
    
    header('Content-Type: application/json');
    echo json_encode($proyectos);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor: ' . $e->getMessage()]);
}
?>