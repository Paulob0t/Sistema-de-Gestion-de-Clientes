<?php
require_once __DIR__ . '/../auth_middleware.php';
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);
include __DIR__ . '/../conn.php';

$uid = $_SESSION['uid'] ?? null;
$tipo = intval($_SESSION['tipo'] ?? 0);

try {
    if (isset($_GET['id'])) {
        $id = intval($_GET['id']);
        
        $stmt = $conn->prepare("SELECT id, nombre, correo, telefono, requerimiento, empresa, pais, estatus, notas, fecha_registro FROM leads WHERE id = ? AND eliminado = 0");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            echo json_encode(['success' => true, 'data' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lead no encontrado']);
        }
        
    } else {
        echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>
