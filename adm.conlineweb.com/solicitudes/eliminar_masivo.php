<?php
require_once __DIR__.'/../auth_middleware.php';
require_once __DIR__ . '/bootstrap_conexion.php';

// Detectar si es una petición AJAX
$isAjax = isset($_POST['ajax']) && $_POST['ajax'] == '1';

// Validar que se recibieron IDs
if (!isset($_POST['ids']) || empty($_POST['ids'])) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No se proporcionaron IDs de tickets']);
        exit;
    } else {
        header("Location: index.php?error=no_ids");
        exit;
    }
}

// Obtener y validar los IDs
$ids = $_POST['ids'];
if (!is_array($ids)) {
    $ids = explode(',', $ids);
}

// Filtrar y validar IDs
$validIds = array_filter(array_map('intval', $ids), function($id) {
    return $id > 0;
});

if (empty($validIds)) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No se proporcionaron IDs válidos']);
        exit;
    } else {
        header("Location: index.php?error=invalid_ids");
        exit;
    }
}

try {
    // Iniciar transacción para mantener integridad
    $conexion->begin_transaction();
    
    $deletedCount = 0;
    $errors = [];
    
    // Verificar si existe la tabla de notas
    $checkTable = $conexion->query("SHOW TABLES LIKE 'solicitudes_notas'");
    $hasNotasTable = ($checkTable && $checkTable->num_rows > 0);
    
    foreach ($validIds as $id) {
        try {
            // Eliminar notas asociadas primero (si la tabla existe)
            if ($hasNotasTable) {
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
                throw new Exception("Error al preparar la consulta para ID $id: " . $conexion->error);
            }
            
            $stmt->bind_param("i", $id);
            
            if (!$stmt->execute()) {
                throw new Exception("Error al eliminar ID $id: " . $stmt->error);
            }
            
            if ($stmt->affected_rows > 0) {
                $deletedCount++;
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            $errors[] = "ID $id: " . $e->getMessage();
        }
    }
    
    // Confirmar transacción
    $conexion->commit();
    
    if ($isAjax) {
        header('Content-Type: application/json');
        
        if ($deletedCount > 0) {
            $message = $deletedCount === 1 
                ? "Se eliminó 1 ticket correctamente" 
                : "Se eliminaron $deletedCount tickets correctamente";
            
            if (!empty($errors)) {
                $message .= ". Algunos tickets no se pudieron eliminar.";
            }
            
            echo json_encode([
                'success' => true,
                'message' => $message,
                'deleted' => $deletedCount,
                'total' => count($validIds),
                'errors' => $errors
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No se pudo eliminar ningún ticket',
                'errors' => $errors
            ]);
        }
        exit;
    } else {
        header("Location: index.php?deleted=$deletedCount");
        exit;
    }
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conexion->rollback();
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Error al eliminar tickets: ' . $e->getMessage()
        ]);
        exit;
    } else {
        header("Location: index.php?error=delete_failed");
        exit;
    }
}
?>
