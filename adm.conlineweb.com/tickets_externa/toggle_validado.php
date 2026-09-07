<?php
require_once 'auth_externa.php';
header('Content-Type: application/json; charset=utf-8');

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$validado = isset($_POST['validado']) ? (int)$_POST['validado'] : null;

if ($id <= 0 || ($validado !== 0 && $validado !== 1)) {
    echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
    exit;
}

// Verificar que el ticket pertenezca al cliente (línea Italia)
$check = $conn->prepare('SELECT id, id_cliente, estado, fecha_termina FROM solicitudes WHERE id = ? LIMIT 1');
$check->bind_param('i', $id);
$check->execute();
$res = $check->get_result();
if ($res->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Ticket no encontrado']);
    exit;
}
$row = $res->fetch_assoc();
if ($row['id_cliente'] != $cliente_id) {
    echo json_encode(['success' => false, 'error' => 'No tienes permiso para modificar este ticket']);
    exit;
}

$current_estado = $row['estado'] ?? '';
$current_fecha_termina = $row['fecha_termina'] ?? '0000-00-00 00:00:00';

// Preparar actualización
if ($validado === 1) {
    // Al validar: NO sobreescribir estado ni usuario_asignado para no perder datos al actualizar el ticket.
    // Solo marcar validado=1 y establecer fecha_termina solo si no existía antes.
    if ($current_fecha_termina === null || $current_fecha_termina === '0000-00-00 00:00:00') {
        $fecha_termina = date('Y-m-d H:i:s');
    } else {
        $fecha_termina = $current_fecha_termina; // mantener la existente
    }
    $stmt = $conn->prepare('UPDATE solicitudes SET validado = 1, fecha_termina = ? WHERE id = ?');
    $stmt->bind_param('si', $fecha_termina, $id);
} else {
    // Desvalidar: siempre regresar a Pendiente, limpiar usuario_asignado y resetear fecha_termina.
    $nuevo_estado = 'Pendiente';
    $fecha_termina = '0000-00-00 00:00:00';
    $stmt = $conn->prepare('UPDATE solicitudes SET validado = 0, estado = ?, usuario_asignado = NULL, fecha_termina = ? WHERE id = ?');
    $stmt->bind_param('ssi', $nuevo_estado, $fecha_termina, $id);
}

if ($stmt === false) {
    echo json_encode(['success' => false, 'error' => 'Error en la preparación de la consulta']);
    exit;
}

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
    exit;
} else {
    echo json_encode(['success' => false, 'error' => $stmt->error]);
    exit;
}

?>
