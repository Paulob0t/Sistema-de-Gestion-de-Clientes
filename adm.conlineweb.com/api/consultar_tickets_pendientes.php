<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__.'/../conn.php';

try {
    error_log("[API Tickets Pendientes] Iniciando consulta de tickets pendientes");
    
    $sql = "SELECT titulo, fecha_lim as fecha_estimada 
            FROM solicitudes 
            WHERE estado = 'Pendiente' 
            ORDER BY fecha_lim ASC";
    
    error_log("[API Tickets Pendientes] SQL: " . $sql);
    
    $result = $conn->query($sql);
    
    if ($result) {
        $tickets = [];
        while ($row = $result->fetch_assoc()) {
            $tickets[] = [
                'titulo' => $row['titulo'],
                'fecha_estimada' => $row['fecha_estimada']
            ];
        }
        
        error_log("[API Tickets Pendientes] ✓ Consulta exitosa - Total: " . count($tickets) . " tickets");
        
        echo json_encode([
            'success' => true,
            'total' => count($tickets),
            'tickets' => $tickets
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } else {
        error_log("[API Tickets Pendientes] ✗ Error en query: " . $conn->error);
        throw new Exception($conn->error);
    }
} catch (Exception $e) {
    error_log("[API Tickets Pendientes] ✗ EXCEPCIÓN: " . $e->getMessage());
    error_log("[API Tickets Pendientes] Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al consultar tickets: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
