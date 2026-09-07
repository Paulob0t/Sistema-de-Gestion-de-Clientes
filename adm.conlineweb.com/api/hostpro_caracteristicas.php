<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Headers JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../conn_hostingpro.php';

if (!$conn_hp) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión a la base de datos']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// ==================== GET - LISTAR CARACTERÍSTICAS ====================
if ($method === 'GET') {
    $sql = "SELECT * FROM caracteristicas ORDER BY orden_global ASC";
    $result = $conn_hp->query($sql);
    
    $caracteristicas = [];
    while ($row = $result->fetch_assoc()) {
        $caracteristicas[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $caracteristicas]);
}

// ==================== POST - CREAR CARACTERÍSTICA ====================
elseif ($method === 'POST') {
    $sql = "INSERT INTO caracteristicas (nombre, tipo_dato, icono, orden_global) VALUES (?, ?, ?, ?)";
    $stmt = $conn_hp->prepare($sql);
    $stmt->bind_param(
        'sssi',
        $input['nombre'],
        $input['tipo_dato'],
        $input['icono'],
        $input['orden_global']
    );
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'id' => $conn_hp->insert_id]);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }
}

// ==================== DELETE - ELIMINAR CARACTERÍSTICA ====================
elseif ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'ID requerido']);
        exit;
    }
    
    $stmt = $conn_hp->prepare("DELETE FROM caracteristicas WHERE id = ?");
    $stmt->bind_param('i', $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }
}

$conn_hp->close();
