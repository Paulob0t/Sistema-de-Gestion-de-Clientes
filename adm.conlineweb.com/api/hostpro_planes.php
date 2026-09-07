<?php
// Solo logging, NO mostrar errores como HTML
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Headers PRIMERO antes de cualquier output
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar OPTIONS request (CORS preflight)
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

// Log para debug
error_log("API HOSTPRO PLANES - Método: $method");
error_log("API HOSTPRO PLANES - Input: " . print_r($input, true));

// ==================== GET - LISTAR PLANES ====================
if ($method === 'GET') {
    $tipo = $_GET['tipo'] ?? null;
    
    $sql = "SELECT * FROM planes_admin WHERE 1=1";
    if ($tipo) {
        $sql .= " AND tipo = ?";
    }
    $sql .= " ORDER BY orden ASC, id ASC";
    
    $stmt = $conn_hp->prepare($sql);
    if ($tipo) {
        $stmt->bind_param('s', $tipo);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $planes = [];
    while ($row = $result->fetch_assoc()) {
        // Obtener características del plan
        $sql_carac = "SELECT c.id AS caracteristica_id, c.nombre, c.tipo_dato, c.icono, pc.valor, pc.orden
                      FROM plan_caracteristicas pc
                      JOIN caracteristicas c ON pc.caracteristica_id = c.id
                      WHERE pc.plan_id = ?
                      ORDER BY pc.orden ASC";
        $stmt_carac = $conn_hp->prepare($sql_carac);
        $stmt_carac->bind_param('i', $row['id']);
        $stmt_carac->execute();
        $result_carac = $stmt_carac->get_result();
        
        $row['caracteristicas'] = [];
        while ($carac = $result_carac->fetch_assoc()) {
            $row['caracteristicas'][] = $carac;
        }
        
        $planes[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $planes]);
}

// ==================== POST - CREAR PLAN ====================
elseif ($method === 'POST') {
    // Validar que tenemos datos
    if (!$input) {
        echo json_encode(['success' => false, 'error' => 'Datos JSON inválidos']);
        exit;
    }
    
    // Valores con defaults
    $nombre = $input['nombre'] ?? '';
    $tipo = $input['tipo'] ?? 'hosting';
    $precio = floatval($input['precio'] ?? 0);
    $precio_usd = floatval($input['precio_usd'] ?? 0);
    $precio_renovacion = floatval($input['precio_renovacion'] ?? 0);
    $precio_renovacion_usd = floatval($input['precio_renovacion_usd'] ?? 0);
    $descripcion = $input['descripcion'] ?? '';
    $icono = $input['icono'] ?? '';
    $orden = intval($input['orden'] ?? 0);
    $destacado = intval($input['destacado'] ?? 0);
    $activo = intval($input['activo'] ?? 1);
    
    $sql = "INSERT INTO planes_admin (nombre, tipo, precio, precio_usd, precio_renovacion, precio_renovacion_usd, descripcion, icono, orden, destacado, activo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn_hp->prepare($sql);
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Error preparando query: ' . $conn_hp->error]);
        exit;
    }
    
    $stmt->bind_param(
        'ssddddssiii',
        $nombre,
        $tipo,
        $precio,
        $precio_usd,
        $precio_renovacion,
        $precio_renovacion_usd,
        $descripcion,
        $icono,
        $orden,
        $destacado,
        $activo
    );
    
    if ($stmt->execute()) {
        $plan_id = $conn_hp->insert_id;
        
        // Insertar características si vienen
        try {
            if (!empty($input['caracteristicas'])) {
                $sql_carac = "INSERT INTO plan_caracteristicas (plan_id, caracteristica_id, valor, orden) VALUES (?, ?, ?, ?)";
                $stmt_carac = $conn_hp->prepare($sql_carac);
                
                if ($stmt_carac) {
                    foreach ($input['caracteristicas'] as $idx => $carac) {
                        $carac_id = $carac['id'] ?? 0;
                        $carac_valor = $carac['valor'] ?? '';
                        $stmt_carac->bind_param('iisi', $plan_id, $carac_id, $carac_valor, $idx);
                        $stmt_carac->execute();
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error características POST: " . $e->getMessage());
            // Continuar aunque falle características
        }
        
        echo json_encode(['success' => true, 'id' => $plan_id]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error ejecutando: ' . $stmt->error]);
    }
}

// ==================== PUT - ACTUALIZAR PLAN ====================
elseif ($method === 'PUT') {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'ID requerido']);
        exit;
    }
    
    // Validar que tenemos datos
    if (!$input) {
        echo json_encode(['success' => false, 'error' => 'Datos JSON inválidos']);
        exit;
    }
    
    // Valores con defaults para evitar NULL
    $nombre = $input['nombre'] ?? '';
    $tipo = $input['tipo'] ?? 'hosting';
    $precio = floatval($input['precio'] ?? 0);
    $precio_usd = floatval($input['precio_usd'] ?? 0);
    $precio_renovacion = floatval($input['precio_renovacion'] ?? 0);
    $precio_renovacion_usd = floatval($input['precio_renovacion_usd'] ?? 0);
    $descripcion = $input['descripcion'] ?? '';
    $icono = $input['icono'] ?? '';
    $orden = intval($input['orden'] ?? 0);
    $destacado = intval($input['destacado'] ?? 0);
    $activo = intval($input['activo'] ?? 1);
    
    $sql = "UPDATE planes_admin SET nombre=?, tipo=?, precio=?, precio_usd=?, precio_renovacion=?, precio_renovacion_usd=?, descripcion=?, icono=?, orden=?, destacado=?, activo=? WHERE id=?";
    $stmt = $conn_hp->prepare($sql);
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Error preparando query: ' . $conn_hp->error]);
        exit;
    }
    
    $stmt->bind_param(
        'ssddddssiiii',
        $nombre,
        $tipo,
        $precio,
        $precio_usd,
        $precio_renovacion,
        $precio_renovacion_usd,
        $descripcion,
        $icono,
        $orden,
        $destacado,
        $activo,
        $id
    );
    
    if ($stmt->execute()) {
        // Actualizar características: eliminar las viejas e insertar las nuevas
        try {
            $conn_hp->query("DELETE FROM plan_caracteristicas WHERE plan_id = $id");
            
            if (!empty($input['caracteristicas'])) {
                $sql_carac = "INSERT INTO plan_caracteristicas (plan_id, caracteristica_id, valor, orden) VALUES (?, ?, ?, ?)";
                $stmt_carac = $conn_hp->prepare($sql_carac);
                
                if ($stmt_carac) {
                    foreach ($input['caracteristicas'] as $idx => $carac) {
                        $carac_id = $carac['id'] ?? 0;
                        $carac_valor = $carac['valor'] ?? '';
                        $stmt_carac->bind_param('iisi', $id, $carac_id, $carac_valor, $idx);
                        $stmt_carac->execute();
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error características PUT: " . $e->getMessage());
            // Continuar aunque falle características
        }
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error ejecutando: ' . $stmt->error]);
    }
}

// ==================== DELETE - ELIMINAR PLAN ====================
elseif ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'ID requerido']);
        exit;
    }
    
    // Primero eliminar las características del plan
    $conn_hp->query("DELETE FROM plan_caracteristicas WHERE plan_id = $id");
    
    // Luego eliminar el plan
    $stmt = $conn_hp->prepare("DELETE FROM planes_admin WHERE id = ?");
    $stmt->bind_param('i', $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }
}

$conn_hp->close();
