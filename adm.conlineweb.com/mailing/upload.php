<?php
/**
 * Subida de imagen para plantillas mailing.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

define('AUTH_MIDDLEWARE_FUNCTIONS_ONLY', true);
require_once dirname(__DIR__) . '/auth_middleware.php';
require_once dirname(__DIR__) . '/includes/adm_paths.php';
require_once dirname(__DIR__) . '/includes/cw_mailing_service.php';

adm_start_session();

if (!isAdminSessionValid() && !loadSessionFromCookies()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autorizado'], JSON_UNESCAPED_UNICODE);
    exit;
}

$tipo = (int) ($_SESSION['tipo'] ?? 0);
if ($tipo < 1 || $tipo > 5) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sin permiso'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_FILES['image']) || !is_array($_FILES['image'])) {
    echo json_encode(['ok' => false, 'error' => 'No se recibió imagen'], JSON_UNESCAPED_UNICODE);
    exit;
}

$file = $_FILES['image'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    echo json_encode(['ok' => false, 'error' => 'Error al subir archivo'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ((int) ($file['size'] ?? 0) > 2 * 1024 * 1024) {
    echo json_encode(['ok' => false, 'error' => 'Máximo 2 MB'], JSON_UNESCAPED_UNICODE);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = (string) $finfo->file((string) $file['tmp_name']);
$map = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
];
if (!isset($map[$mime])) {
    echo json_encode(['ok' => false, 'error' => 'Formato no permitido (JPG/PNG/GIF/WebP)'], JSON_UNESCAPED_UNICODE);
    exit;
}

$dir = cw_mailing_uploads_dir();
$name = 'ml_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $map[$mime];
$dest = $dir . '/' . $name;
if (!move_uploaded_file((string) $file['tmp_name'], $dest)) {
    echo json_encode(['ok' => false, 'error' => 'No se pudo guardar'], JSON_UNESCAPED_UNICODE);
    exit;
}

$url = cw_mailing_media_url($name);
echo json_encode(['ok' => true, 'url' => $url, 'filename' => $name], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
