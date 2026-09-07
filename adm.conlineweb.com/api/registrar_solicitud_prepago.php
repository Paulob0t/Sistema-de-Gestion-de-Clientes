<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../conn.php';

// Leer el cuerpo de la petición
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// LOG: Datos recibidos
error_log("[registrar_solicitud_prepago] === INICIO DE SOLICITUD ===");
error_log("[registrar_solicitud_prepago] Datos RAW recibidos: " . $input);
error_log("[registrar_solicitud_prepago] Datos parseados: " . json_encode($data, JSON_UNESCAPED_UNICODE));

// Validar que se recibieron datos
if (!$data) {
    error_log("[registrar_solicitud_prepago] ERROR: Datos inválidos o no proporcionados");
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Datos inválidos o no proporcionados'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Obtener parámetros
$cliente_id = isset($data['cliente_id']) ? (int) $data['cliente_id'] : null;
$telefono = isset($data['telefono']) ? trim($data['telefono']) : null;
$dominio = isset($data['dominio']) ? trim($data['dominio']) : null;
$proyecto = isset($data['proyecto']) ? trim($data['proyecto']) : null;
$titulo = isset($data['titulo']) ? trim($data['titulo']) : '';
$descripcion = isset($data['descripcion']) ? trim($data['descripcion']) : '';
$precio_acordado = isset($data['precio_acordado']) ? (float) $data['precio_acordado'] : 0;
$fecha_limite = isset($data['fecha_limite']) ? trim($data['fecha_limite']) : '';
$metodo_pago = isset($data['metodo_pago']) ? strtolower(trim($data['metodo_pago'])) : '';
$prioridad = isset($data['prioridad']) ? strtolower(trim($data['prioridad'])) : 'media';

// LOG: Parámetros extraídos
error_log("[registrar_solicitud_prepago] Parámetros extraídos:");
error_log("[registrar_solicitud_prepago]   - cliente_id: " . ($cliente_id ?: 'NULL'));
error_log("[registrar_solicitud_prepago]   - telefono: " . ($telefono ?: 'NULL'));
error_log("[registrar_solicitud_prepago]   - dominio: " . ($dominio ?: 'NULL'));
error_log("[registrar_solicitud_prepago]   - proyecto: " . ($proyecto ?: 'NULL'));
error_log("[registrar_solicitud_prepago]   - titulo: " . $titulo);
error_log("[registrar_solicitud_prepago]   - precio_acordado: " . $precio_acordado);
error_log("[registrar_solicitud_prepago]   - metodo_pago: " . $metodo_pago);
error_log("[registrar_solicitud_prepago]   - prioridad: " . $prioridad);

// Validaciones
if (empty($titulo)) {
    error_log("[registrar_solicitud_prepago] ERROR: El título es obligatorio");
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'El título es obligatorio'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($descripcion)) {
    error_log("[registrar_solicitud_prepago] ERROR: La descripción es obligatoria");
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'La descripción es obligatoria'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($precio_acordado <= 0) {
    error_log("[registrar_solicitud_prepago] ERROR: El precio acordado debe ser mayor a cero");
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'El precio acordado debe ser mayor a cero'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validar método de pago
if (!in_array($metodo_pago, ['transferencia', 'tarjeta'])) {
    error_log("[registrar_solicitud_prepago] ERROR: Método de pago inválido: " . $metodo_pago);
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Método de pago inválido. Debe ser "transferencia" o "tarjeta"'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Normalizar prioridad
if (!in_array($prioridad, ['alta', 'media', 'baja'])) {
    $prioridad = 'media';
}

// Validar y formatear fecha límite
$fecha_lim_sql = null;
if (!empty($fecha_limite)) {
    $fecha_timestamp = strtotime($fecha_limite);
    if ($fecha_timestamp !== false) {
        $fecha_lim_sql = date('Y-m-d H:i:s', $fecha_timestamp);
    }
}

// Si no se proporcionó fecha límite o no se pudo parsear, usar fecha por defecto (3 días)
if ($fecha_lim_sql === null) {
    $fecha_lim_sql = date('Y-m-d H:i:s', strtotime('+3 days'));
    error_log("[registrar_solicitud_prepago] ℹ️ No se proporcionó fecha límite, usando fecha por defecto: " . $fecha_lim_sql);
}

try {
    // LOG: Datos finales antes de insertar
    error_log("[registrar_solicitud_prepago] === PREPARANDO INSERCIÓN ===");
    error_log("[registrar_solicitud_prepago] cliente_id: " . ($cliente_id ?: 'NULL'));
    error_log("[registrar_solicitud_prepago] telefono: " . ($telefono ?: 'NULL'));
    error_log("[registrar_solicitud_prepago] dominio: " . ($dominio ?: 'NULL'));
    error_log("[registrar_solicitud_prepago] proyecto: " . ($proyecto ?: 'NULL'));
    error_log("[registrar_solicitud_prepago] titulo: " . $titulo);
    error_log("[registrar_solicitud_prepago] descripcion: " . $descripcion);
    error_log("[registrar_solicitud_prepago] precio_acordado: " . $precio_acordado);
    error_log("[registrar_solicitud_prepago] fecha_limite: " . $fecha_lim_sql);
    error_log("[registrar_solicitud_prepago] metodo_pago: " . $metodo_pago);
    error_log("[registrar_solicitud_prepago] prioridad: " . $prioridad);

    // Insertar solicitud en la tabla solicitud_whatsapp
    $stmt_insert = $conn->prepare("
        INSERT INTO solicitud_whatsapp 
        (id_cliente, telefono, dominio, proyecto, titulo, descripcion, fecha_solicitud, precio_acordado, fecha_limite, metodo_pago, pagado, prioridad) 
        VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, 0, ?)
    ");

    if (!$stmt_insert) {
        error_log("[registrar_solicitud_prepago] ERROR SQL prepare: " . $conn->error);
        throw new Exception("Error al preparar consulta de inserción: " . $conn->error);
    }

    $stmt_insert->bind_param(
        "isssssdsss",
        $cliente_id,
        $telefono,
        $dominio,
        $proyecto,
        $titulo,
        $descripcion,
        $precio_acordado,
        $fecha_lim_sql,
        $metodo_pago,
        $prioridad
    );

    if ($stmt_insert->execute()) {
        $solicitud_id = $stmt_insert->insert_id;
        
        if ($solicitud_id <= 0) {
            error_log("[registrar_solicitud_prepago] ERROR: insert_id inválido (" . $solicitud_id . ")");
            error_log("[registrar_solicitud_prepago] affected_rows: " . $stmt_insert->affected_rows);
            $stmt_insert->close();
            throw new Exception("Error: No se pudo obtener el ID de la solicitud insertada");
        }
        
        $stmt_insert->close();

        error_log("[registrar_solicitud_prepago] ✓ Solicitud pre-pago creada exitosamente: #" . $solicitud_id);

        // Respuesta exitosa
        echo json_encode([
            'success' => true,
            'solicitud_id' => $solicitud_id,
            'message' => 'Solicitud registrada exitosamente. Esperando confirmación de pago.',
            'metodo_pago' => $metodo_pago,
            'precio_acordado' => $precio_acordado
        ], JSON_UNESCAPED_UNICODE);
    } else {
        error_log("[registrar_solicitud_prepago] ERROR SQL execute: " . $stmt_insert->error);
        error_log("[registrar_solicitud_prepago] errno: " . $stmt_insert->errno);
        $stmt_insert->close();
        throw new Exception("Error al insertar la solicitud: " . $stmt_insert->error);
    }

} catch (Exception $e) {
    error_log("[registrar_solicitud_prepago] ERROR CATCH: " . $e->getMessage());
    error_log("[registrar_solicitud_prepago] Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al procesar la solicitud: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
