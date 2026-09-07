<?php
// Endpoint AJAX para enviar correo de asignación independientemente del guardado
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

require_once __DIR__.'/bootstrap_conexion.php';
require_once __DIR__.'/enviar_correo_asignacion.php';

function out($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

// Validar datos mínimos
$ticketId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$titulo = isset($_POST['titulo']) ? trim($_POST['titulo']) : '';
$cliente_id = isset($_POST['cliente_id']) && $_POST['cliente_id'] !== '' ? (int)$_POST['cliente_id'] : null;
$usuario_asignado = isset($_POST['usuario_asignado']) ? (int)$_POST['usuario_asignado'] : 0;

if ($ticketId <= 0 || $usuario_asignado <= 0) {
    out(['ok'=>false,'error'=>'Datos insuficientes (id o usuario_asignado inválidos).','debug'=>$_POST]);
}

try {
    $ok = enviar_correo_asignacion($conexion, $ticketId, $titulo, $cliente_id, $usuario_asignado);
    out(['ok'=> (bool)$ok]);
} catch (Throwable $e) {
    out(['ok'=>false,'error'=>$e->getMessage()]);
}
?>
