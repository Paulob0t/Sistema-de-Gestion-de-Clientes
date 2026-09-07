<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../auth_middleware.php';
include __DIR__ . '/../conn.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        
        $stmt = $conn->prepare("UPDATE leads SET eliminado = 1 WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Lead marcado como eliminado exitosamente']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Lead no encontrado']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al marcar el lead como eliminado']);
        }
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Solicitud inválida']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>
