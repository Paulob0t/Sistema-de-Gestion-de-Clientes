<?php
// Asignar agente a ticket (solo si estaba sin asignar) y enviar correo
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

require_once __DIR__.'/bootstrap_conexion.php';
require_once __DIR__.'/enviar_correo_asignacion.php';

function respond($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

$ticketId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$usuarioAsignado = isset($_POST['usuario_asignado']) ? (int)$_POST['usuario_asignado'] : 0;

if ($ticketId <= 0 || $usuarioAsignado <= 0) {
    respond(['ok'=>false,'error'=>'Parámetros inválidos']);
}

try {
    // Verificar ticket y que actualmente no tenga agente
    $stmt = $conexion->prepare("SELECT id, titulo, id_cliente, usuario_asignado FROM solicitudes WHERE id=? LIMIT 1");
    if(!$stmt){ respond(['ok'=>false,'error'=>'Error prepare select']); }
    $stmt->bind_param('i', $ticketId);
    $stmt->execute();
    $res = $stmt->get_result();
    if(!$res || !$res->num_rows){ respond(['ok'=>false,'error'=>'Ticket no encontrado']); }
    $row = $res->fetch_assoc();
    $stmt->close();

    if(!empty($row['usuario_asignado'])){
        respond(['ok'=>false,'error'=>'El ticket ya tiene un agente asignado']);
    }

    // Actualizar asignación
    $stmtU = $conexion->prepare("UPDATE solicitudes SET usuario_asignado=? WHERE id=?");
    if(!$stmtU){ respond(['ok'=>false,'error'=>'Error prepare update']); }
    $stmtU->bind_param('ii', $usuarioAsignado, $ticketId);
    if(!$stmtU->execute()){
        respond(['ok'=>false,'error'=>'No se pudo actualizar asignación']);
    }
    $stmtU->close();

    // Obtener nombre del agente
    $nombreAgente = 'Agente';
    if($rsAg = $conexion->prepare("SELECT nombre FROM agentes WHERE id=?")){
        $rsAg->bind_param('i', $usuarioAsignado);
        $rsAg->execute();
        $rAg = $rsAg->get_result();
        if($rAg && $rAg->num_rows){ $nombreAgente = $rAg->fetch_assoc()['nombre']; }
        $rsAg->close();
    }

    // Enviar correo (silencioso en errores)
    try {
        enviar_correo_asignacion($conexion, $ticketId, $row['titulo'], $row['id_cliente'], $usuarioAsignado);
    } catch(Throwable $e) {
        // Registrar si quieres: error_log('Error correo asignación: '.$e->getMessage());
    }

    respond(['ok'=>true,'agente_nombre'=>$nombreAgente]);
} catch (Throwable $e) {
    respond(['ok'=>false,'error'=>$e->getMessage()]);
}
