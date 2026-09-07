<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__.'/../conn.php';

/**
 * Normaliza números de teléfono a formato E.164
 */
function normalize_phone_e164($phone) {
    $digits = preg_replace('/\D+/', '', (string)$phone);
    if ($digits === '') return '';
    
    // MX: 52 + 10 dígitos -> +521 + 10 dígitos
    if (strpos($digits, '52') === 0 && strlen($digits) === 12) {
        return '+521' . substr($digits, 2);
    }
    // MX ya correcto: 521 + 10 dígitos
    if (strpos($digits, '521') === 0 && strlen($digits) === 13) {
        return '+' . $digits;
    }
    // MX local 10 dígitos
    if (strlen($digits) === 10) {
        return '+521' . $digits;
    }
    // US/CA típico
    if (strlen($digits) === 11 && strpos($digits, '1') === 0) {
        return '+' . $digits;
    }
    
    return '+' . $digits;
}

// Obtener parámetros
$client_name = isset($_POST['client_name']) ? trim($_POST['client_name']) : '';
$client_email = isset($_POST['client_email']) ? trim($_POST['client_email']) : '';
$client_phone = isset($_POST['client_phone']) ? trim($_POST['client_phone']) : '';

error_log("[registrar_cliente_nuevo] ========== INICIO ==========");
error_log("[registrar_cliente_nuevo] Datos recibidos: name=$client_name, email=$client_email, phone=$client_phone");

// Validar datos obligatorios
if (empty($client_name) || empty($client_email) || empty($client_phone)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Faltan datos obligatorios: client_name, client_email y client_phone son requeridos'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validar formato de email
if (!filter_var($client_email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'El formato del correo electrónico no es válido'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Verificar conexión
    if (!$conn || $conn->connect_error) {
        throw new Exception("Error de conexión a la base de datos");
    }
    
    // Normalizar teléfono
    $phone_normalized = normalize_phone_e164($client_phone);
    
    // Verificar si el cliente ya existe (por email o teléfono)
    $stmt_check = $conn->prepare("
        SELECT id, nombre_contacto, correo, telefono 
        FROM clientes 
        WHERE correo = ? OR telefono = ? OR telefono = ?
        LIMIT 1
    ");
    $stmt_check->bind_param("sss", $client_email, $client_phone, $phone_normalized);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows > 0) {
        $existing = $result_check->fetch_assoc();
        $stmt_check->close();
        
        error_log("[registrar_cliente_nuevo] Cliente ya existe: ID=" . $existing['id']);
        
        echo json_encode([
            'success' => true,
            'message' => 'El cliente ya está registrado en el sistema',
            'data' => [
                'cliente_id' => $existing['id'],
                'nombre' => $existing['nombre_contacto'],
                'email' => $existing['correo'],
                'telefono' => $existing['telefono'],
                'ya_existia' => true
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    $stmt_check->close();
    
    // Insertar nuevo cliente
    $stmt_insert = $conn->prepare("
        INSERT INTO clientes (nombre_contacto, correo, telefono)
        VALUES (?, ?, ?)
    ");
    
    if (!$stmt_insert) {
        throw new Exception("Error al preparar consulta de inserción: " . $conn->error);
    }
    
    $stmt_insert->bind_param("sss", $client_name, $client_email, $phone_normalized);
    
    if (!$stmt_insert->execute()) {
        throw new Exception("Error al insertar cliente: " . $stmt_insert->error);
    }
    
    $new_client_id = $stmt_insert->insert_id;
    $stmt_insert->close();
    
    error_log("[registrar_cliente_nuevo] ✓ Cliente registrado exitosamente: ID=$new_client_id");
    
    echo json_encode([
        'success' => true,
        'message' => 'Cliente registrado exitosamente',
        'data' => [
            'cliente_id' => $new_client_id,
            'nombre' => $client_name,
            'email' => $client_email,
            'telefono' => $phone_normalized,
            'ya_existia' => false
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("[registrar_cliente_nuevo] ========== EXCEPCIÓN ==========");
    error_log("[registrar_cliente_nuevo] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al registrar el cliente: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
