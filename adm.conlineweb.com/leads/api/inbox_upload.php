<?php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_permissions.php';
require_once dirname(__DIR__) . '/includes/inbox_helpers.php';

cw_hub_migrate($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$leadId = (int) ($_POST['id'] ?? 0);
if ($leadId <= 0 || empty($_FILES['archivo']['name'])) {
    echo json_encode(['success' => false, 'message' => 'Archivo requerido']);
    exit;
}

$stmt = $conn->prepare('SELECT * FROM leads WHERE id = ? AND eliminado = 0 LIMIT 1');
$stmt->bind_param('i', $leadId);
$stmt->execute();
$lead = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$lead) {
    echo json_encode(['success' => false, 'message' => 'Lead no encontrado']);
    exit;
}

if (!cw_hub_can_edit_lead($lead)) {
    echo json_encode(['success' => false, 'message' => 'Sin permiso']);
    exit;
}

$file = $_FILES['archivo'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Error al subir archivo']);
    exit;
}

$maxSize = 10 * 1024 * 1024;
if ((int) $file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'message' => 'Máximo 10 MB']);
    exit;
}

$allowed = [
    'pdf' => 'application/pdf',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'txt' => 'text/plain',
];

$origName = basename((string) $file['name']);
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
if (!isset($allowed[$ext])) {
    echo json_encode(['success' => false, 'message' => 'Tipo de archivo no permitido']);
    exit;
}

$dir = cw_inbox_upload_dir($leadId);
if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
    echo json_encode(['success' => false, 'message' => 'No se pudo crear carpeta']);
    exit;
}

$stored = bin2hex(random_bytes(8)) . '.' . $ext;
$dest = $dir . '/' . $stored;
if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['success' => false, 'message' => 'No se pudo guardar']);
    exit;
}

$uid = cw_inbox_uid();
$now = date('Y-m-d H:i:s');
$mime = $allowed[$ext];
$size = (int) $file['size'];

$ins = $conn->prepare('INSERT INTO cw_lead_adjuntos (lead_id, nombre_original, nombre_archivo, mime_type, tamano, usuario_id, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?)');
$ins->bind_param('isssiis', $leadId, $origName, $stored, $mime, $size, $uid, $now);
$ins->execute();
$adjuntoId = (int) $ins->insert_id;
$ins->close();

$desc = 'Archivo adjunto: ' . $origName;
$act = $conn->prepare('INSERT INTO cw_lead_actividades (lead_id, tipo, descripcion, usuario_id, created_at) VALUES (?, ?, ?, ?, ?)');
$tipo = 'nota';
$act->bind_param('issis', $leadId, $tipo, $desc, $uid, $now);
$act->execute();
$act->close();

$upd = $conn->prepare('UPDATE leads SET ultima_interaccion = ? WHERE id = ?');
$upd->bind_param('si', $now, $leadId);
$upd->execute();
$upd->close();

echo json_encode([
    'success' => true,
    'message' => 'Archivo subido',
    'adjunto' => [
        'id' => $adjuntoId,
        'nombre_original' => $origName,
        'tamano' => $size,
        'created_at' => $now,
        'usuario' => cw_inbox_user_name($conn, $uid),
    ],
], JSON_UNESCAPED_UNICODE);
