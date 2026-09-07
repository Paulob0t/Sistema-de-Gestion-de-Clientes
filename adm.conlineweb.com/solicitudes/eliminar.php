<?php
require_once __DIR__.'/../auth_middleware.php';
require_once __DIR__ . '/bootstrap_conexion.php';

// Detectar si es una petición AJAX
$isAjax = isset($_POST['ajax']) && $_POST['ajax'] == '1';

// Validar que se recibió el ID
if (!isset($_POST['id']) || empty($_POST['id'])) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'ID de solicitud no proporcionado']);
        exit;
    } else {
        header("Location: index.php?error=no_id");
        exit;
    }
}

$id = intval($_POST['id']);

// Verificar que el ID sea válido
if ($id <= 0) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    } else {
        header("Location: index.php?error=invalid_id");
        exit;
    }
}

try {
    // Iniciar transacción para mantener integridad
    $conexion->begin_transaction();
    
    // Primero eliminar las notas asociadas (si la tabla existe)
    $checkTable = $conexion->query("SHOW TABLES LIKE 'solicitudes_notas'");
    if ($checkTable && $checkTable->num_rows > 0) {
        $sqlNotas = "DELETE FROM solicitudes_notas WHERE solicitud_id = ?";
        $stmtNotas = $conexion->prepare($sqlNotas);
        if ($stmtNotas) {
            $stmtNotas->bind_param("i", $id);
            $stmtNotas->execute();
            $stmtNotas->close();
        }
    }
    
    // Eliminar la solicitud
    $sql = "DELETE FROM solicitudes WHERE id = ?";
    $stmt = $conexion->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conexion->error);
    }
    
    $stmt->bind_param("i", $id);
    
    if (!$stmt->execute()) {
        throw new Exception("Error al eliminar: " . $stmt->error);
    }
    
    $filasAfectadas = $stmt->affected_rows;
    $stmt->close();
    
    // Confirmar transacción
    $conexion->commit();
    
    if ($isAjax) {
        header('Content-Type: application/json');
        if ($filasAfectadas > 0) {
            echo json_encode([
                'success' => true, 
                'message' => 'Solicitud eliminada correctamente',
                'id' => $id
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'No se encontró la solicitud con ID: ' . $id
            ]);
        }
        exit;
    } else {
        header("Location: index.php?deleted=1");
        exit;
    }
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conexion->rollback();
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => 'Error al eliminar la solicitud: ' . $e->getMessage()
        ]);
        exit;
    } else {
        header("Location: index.php?error=delete_failed");
        exit;
    }
}
?>
