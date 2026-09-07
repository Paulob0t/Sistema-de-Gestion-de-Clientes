<?php
// Endpoint interno llamado desde tickets_vista.php para generar un token seguro.
// Método: POST (id_cliente, id_proyecto opcional)
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/link_helper.php';
require_once __DIR__.'/conn.php';

function respond($a){ echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }

if($_SERVER['REQUEST_METHOD']!=='POST') respond(['success'=>false,'message'=>'Método no permitido']);
$idC = isset($_POST['id_cliente']) ? (int)$_POST['id_cliente'] : 0;
$idP = isset($_POST['id_proyecto']) ? (int)$_POST['id_proyecto'] : 0;
// nombreMSJ opcional
$nombreMSJ = isset($_POST['nombreMSJ']) ? trim((string)$_POST['nombreMSJ']) : '';
if($idC<=0) respond(['success'=>false,'message'=>'id_cliente requerido']);

// Validar que el cliente exista
$cliStmt = $conn->prepare('SELECT id FROM clientes WHERE id=? LIMIT 1');
$cliStmt->bind_param('i',$idC);
$cliStmt->execute();
$cliRes = $cliStmt->get_result();
if(!$cliRes->num_rows){ respond(['success'=>false,'message'=>'Cliente no existe']); }
$cliStmt->close();

if($idP>0){
    $pStmt = $conn->prepare('SELECT id_proyecto FROM proyectos WHERE id_proyecto=? AND id_cliente=? LIMIT 1');
    $pStmt->bind_param('ii',$idP,$idC);
    $pStmt->execute();
    $pRes = $pStmt->get_result();
    if(!$pRes->num_rows){ respond(['success'=>false,'message'=>'Proyecto no coincide con cliente']); }
    $pStmt->close();
}

$token = generar_link_token($idC, $idP ?: null, null, $nombreMSJ !== '' ? $nombreMSJ : null);
respond(['success'=>true,'token'=>$token]);
