<?php
/**
 * Script para restaurar pagos eliminados (cambiar Registro de 1 a 0)
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0); // No mostrar errores en producción

// Incluir archivo de conexión
include "conn.php";
include "conn_hostingpro.php";

$sistema = (isset($_POST['sistema']) && $_POST['sistema'] === 'hostingpro') ? 'hostingpro' : 'conlineweb';
$conn = ($sistema === 'hostingpro') ? $conn_hp : $conn;

// Verificar que la petición sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Método de petición no válido'
    ]);
    exit;
}

// Obtener el ID del pago
$id_pago = isset($_POST['id']) ? intval($_POST['id']) : 0;

// Validar que el ID sea válido
if ($id_pago <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID de pago no válido'
    ]);
    exit;
}

try {
    // Verificar que el pago existe y está eliminado
    $sql_verificar = "SELECT id, estatus, Registro FROM pagos WHERE id = ?";
    $stmt_verificar = $conn->prepare($sql_verificar);
    
    if (!$stmt_verificar) {
        throw new Exception('Error al preparar consulta de verificación: ' . $conn->error);
    }
    
    $stmt_verificar->bind_param("i", $id_pago);
    $stmt_verificar->execute();
    $resultado = $stmt_verificar->get_result();
    
    if ($resultado->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'El pago no existe'
        ]);
        $stmt_verificar->close();
        $conn->close();
        exit;
    }
    
    $pago = $resultado->fetch_assoc();
    
    // Verificar si NO está eliminado
    if ($pago['Registro'] == 0) {
        echo json_encode([
            'success' => false,
            'message' => 'El pago no está eliminado, no se puede restaurar'
        ]);
        $stmt_verificar->close();
        $conn->close();
        exit;
    }
    
    $stmt_verificar->close();
    
    // Restaurar el pago (cambiar Registro a 0)
    $sql_restaurar = "UPDATE pagos SET Registro = 0 WHERE id = ?";
    $stmt_restaurar = $conn->prepare($sql_restaurar);
    
    if (!$stmt_restaurar) {
        throw new Exception('Error al preparar consulta de restauración: ' . $conn->error);
    }
    
    $stmt_restaurar->bind_param("i", $id_pago);
    
    if ($stmt_restaurar->execute()) {
        if ($stmt_restaurar->affected_rows > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'El pago ha sido restaurado correctamente',
                'id_pago' => $id_pago
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No se pudo restaurar el pago. Intente nuevamente.'
            ]);
        }
    } else {
        throw new Exception('Error al ejecutar restauración: ' . $stmt_restaurar->error);
    }
    
    $stmt_restaurar->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor: ' . $e->getMessage()
    ]);
} finally {
    // Cerrar conexión
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
?>
