<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../auth_middleware.php';
include __DIR__ . '/../conn.php';

// Verificar que se reciban los datos necesarios
if (!isset($_POST['id']) || !isset($_POST['nota'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Datos incompletos'
    ]);
    exit;
}

$lead_id = intval($_POST['id']);
$nota_nueva = trim($_POST['nota']);

// Validar que la nota no esté vacía
if (empty($nota_nueva)) {
    echo json_encode([
        'success' => false,
        'message' => 'La nota no puede estar vacía'
    ]);
    exit;
}

try {
    // Obtener las notas actuales del lead
    $stmt = $conn->prepare("SELECT notas FROM leads WHERE id = ? AND eliminado = 0");
    $stmt->bind_param("i", $lead_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Lead no encontrado'
        ]);
        exit;
    }
    
    $lead = $result->fetch_assoc();
    $notas_actuales = $lead['notas'];
    
    // Decodificar las notas existentes o crear array vacío
    $notas_array = [];
    if (!empty($notas_actuales)) {
        $notas_array = json_decode($notas_actuales, true);
        if (!is_array($notas_array)) {
            $notas_array = [];
        }
    }
    
    // Agregar la nueva nota con fecha y hora actual
    $nueva_nota = [
        'nota' => $nota_nueva,
        'fecha' => date('Y-m-d H:i:s')
    ];
    
    array_push($notas_array, $nueva_nota);
    
    // Codificar de nuevo a JSON
    $notas_json = json_encode($notas_array, JSON_UNESCAPED_UNICODE);
    
    // Actualizar la base de datos
    $stmt = $conn->prepare("UPDATE leads SET notas = ?, fecha_modificacion = NOW() WHERE id = ?");
    $stmt->bind_param("si", $notas_json, $lead_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Nota agregada exitosamente',
            'data' => [
                'total_notas' => count($notas_array),
                'ultima_nota' => $nota_nueva
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error al agregar la nota: ' . $conn->error
        ]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
