<?php
/**
 * Script para cambiar manualmente el estatus de pago de un dominio
 * 0 = No pagado
 * 1 = Pagado
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set("display_errors", 1);

// Incluir archivo de conexión
include "conn.php";

// Verificar que se recibió el método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener datos del POST
$id_dominio = isset($_POST['id_dominio']) ? intval($_POST['id_dominio']) : 0;
$nuevo_estatus = isset($_POST['estatus_pago']) ? intval($_POST['estatus_pago']) : 0;

// Validar que se recibió un ID válido
if ($id_dominio <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de dominio no válido']);
    exit;
}

// Validar que el estatus sea 0 o 1
if ($nuevo_estatus !== 0 && $nuevo_estatus !== 1) {
    echo json_encode(['success' => false, 'message' => 'Estatus de pago no válido. Debe ser 0 (No pagado) o 1 (Pagado)']);
    exit;
}

try {
    // Actualizar el estatus de pago del dominio
    $sql = "UPDATE dominios SET estatus_pago = ? WHERE id_dominio = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $nuevo_estatus, $id_dominio);
    
    if ($stmt->execute()) {
        $estatus_texto = $nuevo_estatus == 1 ? 'Pagado' : 'No pagado';
        echo json_encode([
            'success' => true, 
            'message' => "Estatus de pago actualizado correctamente a: {$estatus_texto}",
            'nuevo_estatus' => $nuevo_estatus,
            'estatus_texto' => $estatus_texto
        ]);
    } else {
        throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>
