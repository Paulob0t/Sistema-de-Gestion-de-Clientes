<?php

require_once __DIR__ . '/includes/cliente_session.php';
cliente_start_session();
header('Content-Type: application/json');

// Verificar que el usuario esté logueado
if (!cliente_is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

include 'conn.php';
$usrid = $_SESSION['uid'];

try {
    // Validar que se recibieron los datos requeridos
    if(!isset($_POST['ticket_id']) || !isset($_POST['comentario'])) {
        echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos']);
        exit();
    }

    $ticket_id = (int)$_POST['ticket_id'];
    $comentario = mysqli_real_escape_string($conn, trim($_POST['comentario']));

    // Validar comentario
    if(empty($comentario) || strlen($comentario) < 5) {
        echo json_encode(['success' => false, 'message' => 'El comentario debe tener al menos 5 caracteres']);
        exit();
    }

    // Verificar que el ticket pertenezca al cliente
    $ticket_check = mysqli_query($conn, "
        SELECT id, titulo 
        FROM solicitudes 
        WHERE id = $ticket_id AND id_cliente = $usrid
    ");

    if(mysqli_num_rows($ticket_check) == 0) {
        echo json_encode(['success' => false, 'message' => 'Ticket no encontrado o no tienes permisos']);
        exit();
    }

    // Verificar existencia de la tabla solicitudes_notas
    $check = mysqli_query($conn, "SHOW TABLES LIKE 'solicitudes_notas'");
    if (!$check || mysqli_num_rows($check) === 0) { 
        echo json_encode(['success' => false, 'message' => 'Tabla no encontrada']);
        exit(); 
    }

    // Obtener el nombre del autor (de la tabla clientes)
    $autor_query = mysqli_query($conn, "SELECT nombre_contacto FROM clientes WHERE id = $usrid");
    if (mysqli_num_rows($autor_query) == 0) {
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
        exit();
    }
    $autor_row = mysqli_fetch_assoc($autor_query);
    $autor = mysqli_real_escape_string($conn, $autor_row['nombre_contacto']);

    // Preparar datos de la nota (sin imágenes, como en notas_guardar.php)
    $notaData = [
        'text' => $comentario,
        'images' => []
    ];
    $notaJson = mysqli_real_escape_string($conn, json_encode($notaData));

    // Insertar en solicitudes_notas
    $sql = "INSERT INTO solicitudes_notas (solicitud_id, autor, nota, fecha_creacion) VALUES ($ticket_id, '$autor', '$notaJson', NOW())";
    if (mysqli_query($conn, $sql)) {
        echo json_encode([
            'success' => true, 
            'message' => 'Comentario agregado exitosamente'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al agregar el comentario']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
?>