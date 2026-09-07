<?php
/**
 * Script para actualizar el estado de un hosting (Activo/Inactivo)
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set("display_errors", 1);

// Incluir archivos de conexión
include "conn.php";
include "conn_hostingpro.php";

// Detectar sistema desde POST
$sistema = 'conlineweb';
if (isset($_POST['sistema'])) {
    if ($_POST['sistema'] === 'hostingpro') {
        $sistema = 'hostingpro';
    } elseif ($_POST['sistema'] === 'planpro') {
        $sistema = 'planpro';
    }
}
$conn = ($sistema === 'hostingpro' || $sistema === 'planpro') ? $conn_hp : $conn;

// Verificar que se recibió el método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener datos del POST
$id_hosting = isset($_POST['id_hosting']) ? intval($_POST['id_hosting']) : 0;
$nuevo_estado = isset($_POST['estado']) ? intval($_POST['estado']) : 0;

// Validar que se recibió un ID válido
if ($id_hosting <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de hosting no válido']);
    exit;
}

// Validar que el estado sea 0 o 1
if ($nuevo_estado !== 0 && $nuevo_estado !== 1) {
    echo json_encode(['success' => false, 'message' => 'Estado no válido. Debe ser 0 (Inactivo) o 1 (Activo)']);
    exit;
}

try {
    // Actualizar el estado del hosting
    $sql = "UPDATE hosting SET estado_producto = ? WHERE id_orden = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $nuevo_estado, $id_hosting);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $estado_texto = $nuevo_estado == 1 ? 'Activo' : 'Inactivo';
            echo json_encode([
                'success' => true, 
                'message' => "Estado actualizado correctamente a: {$estado_texto}",
                'nuevo_estado' => $nuevo_estado,
                'estado_texto' => $estado_texto
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'No se realizaron cambios. El hosting podría no existir o ya tenía ese estado.'
            ]);
        }
    } else {
        throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>
