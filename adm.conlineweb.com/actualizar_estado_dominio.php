<?php
/**
 * Script para actualizar el estado de un dominio (Activo/Inactivo)
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
$id_dominio = isset($_POST['id_dominio']) ? intval($_POST['id_dominio']) : 0;
$nuevo_estado = isset($_POST['estado']) ? intval($_POST['estado']) : 0;

// Validar que se recibió un ID válido
if ($id_dominio <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de dominio no válido']);
    exit;
}

// Validar que el estado sea 0 o 1
if ($nuevo_estado !== 0 && $nuevo_estado !== 1) {
    echo json_encode(['success' => false, 'message' => 'Estado no válido. Debe ser 0 (Inactivo) o 1 (Activo)']);
    exit;
}

try {
    // Actualizar el estado del dominio
    $sql = "UPDATE dominios SET estado_dominio = ? WHERE id_dominio = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $nuevo_estado, $id_dominio);
    
    if (!$stmt->execute()) {
        throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
    }

    $estado_texto = $nuevo_estado === 1 ? 'Activo' : 'Inactivo';
    if ($stmt->affected_rows > 0) {
        echo json_encode([
            'success' => true,
            'message' => "Estado actualizado correctamente a: {$estado_texto}",
            'nuevo_estado' => $nuevo_estado,
            'estado_texto' => $estado_texto,
        ]);
    } else {
        // Sin filas afectadas: verificar si ya tenía ese estado
        $chk = $conn->prepare('SELECT estado_dominio FROM dominios WHERE id_dominio = ? LIMIT 1');
        if (!$chk) {
            throw new Exception('No se pudo verificar el dominio');
        }
        $chk->bind_param('i', $id_dominio);
        $chk->execute();
        $res = $chk->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $chk->close();
        if ($row === null) {
            echo json_encode(['success' => false, 'message' => 'El dominio no existe']);
        } elseif ((int) $row['estado_dominio'] === $nuevo_estado) {
            echo json_encode([
                'success' => true,
                'message' => "El dominio ya estaba en estado: {$estado_texto}",
                'nuevo_estado' => $nuevo_estado,
                'estado_texto' => $estado_texto,
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No se realizaron cambios']);
        }
    }

    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>
