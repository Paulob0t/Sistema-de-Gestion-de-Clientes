<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../conn.php';

// Leer el cuerpo de la petición
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// LOG: Datos recibidos
error_log("[confirmar_pago_solicitud] === INICIO DE SOLICITUD ===");
error_log("[confirmar_pago_solicitud] Datos RAW recibidos: " . $input);
error_log("[confirmar_pago_solicitud] Datos parseados: " . json_encode($data, JSON_UNESCAPED_UNICODE));

// Validar que se recibieron datos
if (!$data) {
    error_log("[confirmar_pago_solicitud] ERROR: Datos inválidos o no proporcionados");
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Datos inválidos o no proporcionados'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Obtener parámetros
$solicitud_id = isset($data['solicitud_id']) ? (int) $data['solicitud_id'] : 0;
$stripe_payment_id = isset($data['stripe_payment_id']) ? trim($data['stripe_payment_id']) : null;

// LOG: Parámetros extraídos
error_log("[confirmar_pago_solicitud] Parámetros extraídos:");
error_log("[confirmar_pago_solicitud]   - solicitud_id: " . $solicitud_id);
error_log("[confirmar_pago_solicitud]   - stripe_payment_id: " . ($stripe_payment_id ?: 'NULL'));

// Validaciones
if ($solicitud_id <= 0) {
    error_log("[confirmar_pago_solicitud] ERROR: ID de solicitud inválido");
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'ID de solicitud inválido'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Obtener datos de la solicitud
    $stmt = $conn->prepare("
        SELECT id, id_cliente, dominio, proyecto, titulo, descripcion, 
               precio_acordado, fecha_limite, metodo_pago, pagado, prioridad
        FROM solicitud_whatsapp 
        WHERE id = ?
        LIMIT 1
    ");
    
    if (!$stmt) {
        error_log("[confirmar_pago_solicitud] ERROR SQL prepare: " . $conn->error);
        throw new Exception("Error al preparar consulta: " . $conn->error);
    }
    
    $stmt->bind_param("i", $solicitud_id);
    
    if (!$stmt->execute()) {
        error_log("[confirmar_pago_solicitud] ERROR SQL execute: " . $stmt->error);
        $stmt->close();
        throw new Exception("Error al ejecutar consulta: " . $stmt->error);
    }
    
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        error_log("[confirmar_pago_solicitud] ERROR: Solicitud no encontrada: " . $solicitud_id);
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Solicitud no encontrada'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $solicitud = $result->fetch_assoc();
    $stmt->close();

    // Verificar si ya está pagada
    if ($solicitud['pagado'] == 1) {
        error_log("[confirmar_pago_solicitud] ADVERTENCIA: La solicitud ya estaba pagada");
        echo json_encode([
            'success' => true,
            'message' => 'La solicitud ya estaba pagada',
            'ticket_id' => $solicitud['ticket_id'] ?? null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    error_log("[confirmar_pago_solicitud] Solicitud encontrada. Iniciando proceso de pago...");

    // Marcar como pagado
    $stmt_update = $conn->prepare("
        UPDATE solicitud_whatsapp 
        SET pagado = 1, 
            fecha_pago = NOW(),
            stripe_payment_id = ?
        WHERE id = ?
    ");
    $stmt_update->bind_param("si", $stripe_payment_id, $solicitud_id);
    
    if (!$stmt_update->execute()) {
        error_log("[confirmar_pago_solicitud] ERROR al actualizar solicitud: " . $stmt_update->error);
        throw new Exception("Error al actualizar el estado de pago: " . $stmt_update->error);
    }
    $stmt_update->close();

    error_log("[confirmar_pago_solicitud] ✓ Solicitud marcada como pagada");

    // Crear ticket en la tabla solicitudes
    $stmt_ticket = $conn->prepare("
        INSERT INTO solicitudes 
        (id_cliente, titulo, descripcion, prioridad, fecha_solicitud, fecha_lim, estado, nombreMSJ, id_proyecto, id_solicitud_whatsapp) 
        VALUES (?, ?, ?, ?, NOW(), ?, 'pendiente', 'WhatsApp Bot', NULL, ?)
    ");

    $nombreMSJ = "WhatsApp Bot - Pago confirmado ($" . number_format($solicitud['precio_acordado'], 2) . " MXN)";
    $descripcion_completa = $solicitud['descripcion'] . "\n\n--- Información de pago ---\n";
    $descripcion_completa .= "Precio acordado: $" . number_format($solicitud['precio_acordado'], 2) . " MXN\n";
    $descripcion_completa .= "Método de pago: " . $solicitud['metodo_pago'] . "\n";
    if ($stripe_payment_id) {
        $descripcion_completa .= "Stripe Payment ID: " . $stripe_payment_id . "\n";
    }
    if ($solicitud['dominio']) {
        $descripcion_completa .= "Dominio: " . $solicitud['dominio'] . "\n";
    }
    if ($solicitud['proyecto']) {
        $descripcion_completa .= "Proyecto: " . $solicitud['proyecto'] . "\n";
    }

    $stmt_ticket->bind_param(
        "issssi",
        $solicitud['id_cliente'],
        $solicitud['titulo'],
        $descripcion_completa,
        $solicitud['prioridad'],
        $solicitud['fecha_limite'],
        $solicitud_id
    );

    if ($stmt_ticket->execute()) {
        $ticket_id = $stmt_ticket->insert_id;
        
        if ($ticket_id <= 0) {
            error_log("[confirmar_pago_solicitud] ERROR: ticket insert_id inválido (" . $ticket_id . ")");
            error_log("[confirmar_pago_solicitud] affected_rows: " . $stmt_ticket->affected_rows);
            $stmt_ticket->close();
            throw new Exception("Error: No se pudo obtener el ID del ticket insertado");
        }
        
        $stmt_ticket->close();

        error_log("[confirmar_pago_solicitud] ✓ Ticket creado exitosamente: #" . $ticket_id);

        // Actualizar la solicitud con el ID del ticket
        $stmt_update_ticket = $conn->prepare("
            UPDATE solicitud_whatsapp 
            SET ticket_id = ?
            WHERE id = ?
        ");
        $stmt_update_ticket->bind_param("ii", $ticket_id, $solicitud_id);
        $stmt_update_ticket->execute();
        $stmt_update_ticket->close();

        error_log("[confirmar_pago_solicitud] ✓ Solicitud actualizada con ticket_id: " . $ticket_id);

        // Respuesta exitosa
        echo json_encode([
            'success' => true,
            'ticket_id' => $ticket_id,
            'solicitud_id' => $solicitud_id,
            'message' => 'Pago confirmado y ticket creado exitosamente',
            'precio_acordado' => $solicitud['precio_acordado']
        ], JSON_UNESCAPED_UNICODE);
    } else {
        error_log("[confirmar_pago_solicitud] ERROR SQL al crear ticket: " . $stmt_ticket->error);
        throw new Exception("Error al crear el ticket: " . $stmt_ticket->error);
    }

} catch (Exception $e) {
    error_log("[confirmar_pago_solicitud] ERROR CATCH: " . $e->getMessage());
    error_log("[confirmar_pago_solicitud] Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al procesar la confirmación de pago: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
