<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../conn.php';

// Obtener el teléfono del parámetro GET
$client_phone = $_GET['client_phone'] ?? '';

if (empty($client_phone)) {
    echo json_encode([
        'success' => false,
        'error' => 'Número de teléfono requerido'
    ]);
    exit;
}

try {
    // Limpiar el número para la búsqueda (solo dígitos)
    $phoneDigits = preg_replace('/\D+/', '', $client_phone);
    
    // Buscar con diferentes formatos posibles en la tabla contactos
    $stmt = $conn->prepare("
        SELECT id, empresa, nombre, telefono 
        FROM contactos 
        WHERE REPLACE(REPLACE(REPLACE(telefono, '+', ''), '-', ''), ' ', '') LIKE ?
           OR REPLACE(REPLACE(REPLACE(telefono, '+', ''), '-', ''), ' ', '') LIKE ?
           OR REPLACE(REPLACE(REPLACE(telefono, '+', ''), '-', ''), ' ', '') LIKE ?
    ");
    
    // Probar con últimos 10 dígitos
    $last10Digits = substr($phoneDigits, -10);
    $pattern1 = '%' . $last10Digits;
    $pattern2 = $last10Digits . '%';
    $pattern3 = '%' . $last10Digits . '%';
    
    $stmt->bind_param("sss", $pattern1, $pattern2, $pattern3);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $contacto = $result->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'es_vip' => true,
            'data' => [
                'id' => $contacto['id'],
                'nombre' => $contacto['nombre'],
                'empresa' => $contacto['empresa'],
                'telefono' => $contacto['telefono']
            ],
            'message' => 'Cliente VIP detectado'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'es_vip' => false,
            'message' => 'Cliente no encontrado en lista VIP'
        ]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error al verificar cliente VIP: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
