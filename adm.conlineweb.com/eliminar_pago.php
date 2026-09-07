<?php
require_once __DIR__ . '/auth_middleware.php';
/**
 * Script para realizar eliminación lógica (soft delete) de pagos
 * Actualiza el campo Registro = 1 para ocultar el registro sin eliminarlo físicamente
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0); // No mostrar errores en producción

// Incluir archivo de conexión
include "conn.php";
include "conn_hostingpro.php";

$sistema = 'conlineweb';
if (isset($_POST['sistema'])) {
    if ($_POST['sistema'] === 'hostingpro') {
        $sistema = 'hostingpro';
    } elseif ($_POST['sistema'] === 'planpro') {
        $sistema = 'planpro';
    }
}
$conn = ($sistema === 'hostingpro' || $sistema === 'planpro') ? $conn_hp : $conn;

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
    // Verificar que el pago existe y no está ya eliminado
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
    
    // Verificar si ya está eliminado
    if ($pago['Registro'] == 1) {
        echo json_encode([
            'success' => false,
            'message' => 'El pago ya ha sido eliminado'
        ]);
        $stmt_verificar->close();
        $conn->close();
        exit;
    }
    
    $stmt_verificar->close();
    
    // Realizar la eliminación lógica (soft delete)
    $sql_eliminar = "UPDATE pagos SET Registro = 1 WHERE id = ?";
    $stmt_eliminar = $conn->prepare($sql_eliminar);
    
    if (!$stmt_eliminar) {
        throw new Exception('Error al preparar consulta de eliminación: ' . $conn->error);
    }
    
    $stmt_eliminar->bind_param("i", $id_pago);
    
    if ($stmt_eliminar->execute()) {
        if ($stmt_eliminar->affected_rows > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'El pago ha sido eliminado correctamente',
                'id_pago' => $id_pago
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No se pudo eliminar el pago. Intente nuevamente.'
            ]);
        }
    } else {
        throw new Exception('Error al ejecutar eliminación: ' . $stmt_eliminar->error);
    }
    
    $stmt_eliminar->close();
    
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
