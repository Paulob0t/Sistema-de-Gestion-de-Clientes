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
error_log("API PLANPRO PLANES - Método: $method");
error_log("API PLANPRO PLANES - Input: " . print_r($input, true));

// ==================== GET - LISTAR PLANES ====================
if ($method === 'GET') {
    // Si viene un ID específico, traer solo ese plan
    if (isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $sql = "SELECT * FROM planes_conlineweb WHERE id_plan = ? LIMIT 1";
        $stmt = $conn_hp->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $plan = $result->fetch_assoc();
        
        if ($plan) {
            echo json_encode(['success' => true, 'data' => $plan]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Plan no encontrado']);
        }
        exit;
    }
    
    // Listar todos los planes con filtros opcionales
    $categoria = $_GET['categoria'] ?? null;
    $activo = isset($_GET['activo']) ? intval($_GET['activo']) : null;
    
    $sql = "SELECT * FROM planes_conlineweb WHERE 1=1";
    $params = [];
    $types = '';
    
    if ($categoria) {
        $sql .= " AND categoria = ?";
        $params[] = $categoria;
        $types .= 's';
    }
    
    if ($activo !== null) {
        $sql .= " AND activo = ?";
        $params[] = $activo;
        $types .= 'i';
    }
    
    $sql .= " ORDER BY orden ASC, es_popular DESC, precio_actual ASC, id_plan ASC";
    
    $stmt = $conn_hp->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $planes = [];
    while ($row = $result->fetch_assoc()) {
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
    $nombre_plan = $input['nombre_plan'] ?? '';
    $categoria = $input['categoria'] ?? 'plan_basico';
    $precio_original = floatval($input['precio_original'] ?? 0);
    $precio_actual = floatval($input['precio_actual'] ?? 0);
    $ahorro = floatval($input['ahorro'] ?? 0);
    $periodicidad = $input['periodicidad'] ?? 'pago único';
    $descripcion_corta = $input['descripcion_corta'] ?? '';
    $caracteristicas = $input['caracteristicas'] ?? '[]';
    $icono = $input['icono'] ?? '🌐';
    $orden = intval($input['orden'] ?? 0);
    $es_popular = intval($input['es_popular'] ?? 0);
    $tiene_oferta = intval($input['tiene_oferta'] ?? 0);
    $activo = intval($input['activo'] ?? 1);
    
    // Validar JSON de características
    if (!empty($caracteristicas) && json_decode($caracteristicas) === null) {
        echo json_encode(['success' => false, 'error' => 'El JSON de características no es válido']);
        exit;
    }
    
    $sql = "INSERT INTO planes_conlineweb (nombre_plan, categoria, precio_original, precio_actual, ahorro, periodicidad, descripcion_corta, caracteristicas, icono, orden, es_popular, tiene_oferta, activo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn_hp->prepare($sql);
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Error al preparar consulta: ' . $conn_hp->error]);
        exit;
    }
    
    $stmt->bind_param('ssdddssssiiii', 
        $nombre_plan, 
        $categoria, 
        $precio_original, 
        $precio_actual, 
        $ahorro, 
        $periodicidad, 
        $descripcion_corta, 
        $caracteristicas, 
        $icono, 
        $orden,
        $es_popular,
        $tiene_oferta, 
        $activo
    );
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'id' => $conn_hp->insert_id]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al crear plan: ' . $stmt->error]);
    }
}

// ==================== PUT - ACTUALIZAR PLAN ====================
elseif ($method === 'PUT') {
    if (!isset($_GET['id'])) {
        echo json_encode(['success' => false, 'error' => 'ID de plan no especificado']);
        exit;
    }
    
    $id = intval($_GET['id']);
    
    if (!$input) {
        echo json_encode(['success' => false, 'error' => 'Datos JSON inválidos']);
        exit;
    }
    
    // Construir UPDATE dinámicamente solo con campos proporcionados
    $updates = [];
    $params = [];
    $types = '';
    
    if (isset($input['nombre_plan'])) {
        $updates[] = 'nombre_plan = ?';
        $params[] = $input['nombre_plan'];
        $types .= 's';
    }
    
    if (isset($input['categoria'])) {
        $updates[] = 'categoria = ?';
        $params[] = $input['categoria'];
        $types .= 's';
    }
    
    if (isset($input['precio_original'])) {
        $updates[] = 'precio_original = ?';
        $params[] = floatval($input['precio_original']);
        $types .= 'd';
    }
    
    if (isset($input['precio_actual'])) {
        $updates[] = 'precio_actual = ?';
        $params[] = floatval($input['precio_actual']);
        $types .= 'd';
    }
    
    if (isset($input['ahorro'])) {
        $updates[] = 'ahorro = ?';
        $params[] = floatval($input['ahorro']);
        $types .= 'd';
    }
    
    if (isset($input['periodicidad'])) {
        $updates[] = 'periodicidad = ?';
        $params[] = $input['periodicidad'];
        $types .= 's';
    }
    
    if (isset($input['descripcion_corta'])) {
        $updates[] = 'descripcion_corta = ?';
        $params[] = $input['descripcion_corta'];
        $types .= 's';
    }
    
    if (isset($input['caracteristicas'])) {
        // Validar JSON
        if (!empty($input['caracteristicas']) && json_decode($input['caracteristicas']) === null) {
            echo json_encode(['success' => false, 'error' => 'El JSON de características no es válido']);
            exit;
        }
        $updates[] = 'caracteristicas = ?';
        $params[] = $input['caracteristicas'];
        $types .= 's';
    }
    
    if (isset($input['icono'])) {
        $updates[] = 'icono = ?';
        $params[] = $input['icono'];
        $types .= 's';
    }
    
    if (isset($input['orden'])) {
        $updates[] = 'orden = ?';
        $params[] = intval($input['orden']);
        $types .= 'i';
    }
    
    if (isset($input['es_popular'])) {
        $updates[] = 'es_popular = ?';
        $params[] = intval($input['es_popular']);
        $types .= 'i';
    }
    
    if (isset($input['tiene_oferta'])) {
        $updates[] = 'tiene_oferta = ?';
        $params[] = intval($input['tiene_oferta']);
        $types .= 'i';
    }
    
    if (isset($input['activo'])) {
        $updates[] = 'activo = ?';
        $params[] = intval($input['activo']);
        $types .= 'i';
    }
    
    if (empty($updates)) {
        echo json_encode(['success' => false, 'error' => 'No hay campos para actualizar']);
        exit;
    }
    
    // Agregar el ID al final
    $params[] = $id;
    $types .= 'i';
    
    $sql = "UPDATE planes_conlineweb SET " . implode(', ', $updates) . " WHERE id_plan = ?";
    
    $stmt = $conn_hp->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Error al preparar consulta: ' . $conn_hp->error]);
        exit;
    }
    
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al actualizar plan: ' . $stmt->error]);
    }
}

// ==================== DELETE - ELIMINAR PLAN ====================
elseif ($method === 'DELETE') {
    if (!isset($_GET['id'])) {
        echo json_encode(['success' => false, 'error' => 'ID de plan no especificado']);
        exit;
    }
    
    $id = intval($_GET['id']);
    
    // Verificar si el plan tiene servicios asociados
    $sql_check = "SELECT COUNT(*) as count FROM servicios_web WHERE plan_nombre = (SELECT nombre_plan FROM planes_conlineweb WHERE id_plan = ?)";
    $stmt_check = $conn_hp->prepare($sql_check);
    $stmt_check->bind_param('i', $id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $row_check = $result_check->fetch_assoc();
    
    if ($row_check['count'] > 0) {
        echo json_encode(['success' => false, 'error' => 'No se puede eliminar el plan porque tiene servicios asociados. Desactívalo en su lugar.']);
        exit;
    }
    
    $sql = "DELETE FROM planes_conlineweb WHERE id_plan = ?";
    $stmt = $conn_hp->prepare($sql);
    $stmt->bind_param('i', $id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Plan no encontrado']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al eliminar plan: ' . $stmt->error]);
    }
}

else {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}
?>
