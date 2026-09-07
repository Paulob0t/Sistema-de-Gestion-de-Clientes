<?php
require_once dirname(__DIR__) . '/auth_middleware.php';
require_once dirname(__DIR__) . '/conn.php';
require_once dirname(__DIR__) . '/includes/cw_hub_migrate.php';
require_once __DIR__ . '/includes/inbox_helpers.php';

cw_hub_migrate($conn);

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    exit('No encontrado');
}

$stmt = $conn->prepare('SELECT a.*, l.eliminado FROM cw_lead_adjuntos a INNER JOIN leads l ON l.id = a.lead_id WHERE a.id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || (int) ($row['eliminado'] ?? 0) === 1) {
    http_response_code(404);
    exit('No encontrado');
}

$path = cw_inbox_upload_dir((int) $row['lead_id']) . '/' . $row['nombre_archivo'];
if (!is_file($path)) {
    http_response_code(404);
    exit('Archivo no disponible');
}

$mime = $row['mime_type'] ?: 'application/octet-stream';
$inline = isset($_GET['view']) && str_starts_with($mime, 'image/');

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace('"', '', $row['nombre_original']) . '"');
readfile($path);
exit;
