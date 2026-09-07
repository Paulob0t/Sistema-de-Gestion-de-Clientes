<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__.'/link_helper.php';
require_once __DIR__.'/conn.php';
require_once __DIR__.'/solicitudes/helpers_media.php';

$token = isset($_GET['token']) ? $_GET['token'] : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if(!$token || $id<=0){
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Parámetros inválidos']);
    exit;
}

$val = validar_link_token($token);
if(!$val['valid']){
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Token inválido']);
    exit;
}

$idCliente = (int)$val['id_cliente'];
$idProyecto = (int)$val['id_proyecto'];

// Confirmar que el ticket pertenece al cliente (y proyecto si aplica)
$cond = 'id = ? AND id_cliente = ?';
$params = [$id, $idCliente];
$types = 'ii';
if($idProyecto>0){
    $cond .= ' AND id_proyecto = ?';
    $params[] = $idProyecto; $types .= 'i';
}
$stmt = $conn->prepare("SELECT id FROM solicitudes WHERE $cond LIMIT 1");
if(!$stmt){
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Error interno']);
    exit;
}
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
if(!$res || !$res->fetch_assoc()){
    http_response_code(404);
    echo json_encode(['success'=>false,'message'=>'Ticket no encontrado']);
    exit;    
}
$stmt->close();

// Verificar existencia de tabla notas
$hasNotas = false;
try { if($chk = $conn->query("SHOW TABLES LIKE 'solicitudes_notas'")){ $hasNotas = $chk->num_rows>0; $chk->close(); } } catch(Exception $e){ $hasNotas=false; }
if(!$hasNotas){
    echo json_encode(['success'=>true,'notes'=>[]]);
    exit;
}

$q = $conn->prepare("SELECT autor, nota, fecha_creacion FROM solicitudes_notas WHERE solicitud_id=? ORDER BY fecha_creacion DESC");
$q->bind_param('i', $id);
$q->execute();
$r = $q->get_result();
$notes = [];
while($row = $r->fetch_assoc()){
    $data = json_decode($row['nota'], true);
    if (!is_array($data)) {
        $try = json_decode(stripslashes((string) $row['nota']), true);
        if (is_array($try)) {
            $data = $try;
        }
    }
    $imgs = (is_array($data) && isset($data['images']) && is_array($data['images'])) ? $data['images'] : [];
    $notes[] = [
        'autor' => (string)$row['autor'],
        'texto' => is_array($data) && isset($data['text']) ? (string)$data['text'] : '',
        'images' => cw_ticket_media_urls($imgs),
        'fecha' => date('d/m/Y H:i', strtotime($row['fecha_creacion']))
    ];
}
$q->close();

echo json_encode(['success'=>true,'notes'=>$notes]);
