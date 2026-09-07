<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../auth_middleware.php';
require_once __DIR__ . '/../includes/transcripcion_audio_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_FILES['audio']) || !is_uploaded_file($_FILES['audio']['tmp_name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No se recibió archivo de audio'], JSON_UNESCAPED_UNICODE);
    exit;
}

$file = $_FILES['audio'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Error al subir el audio (código ' . $file['error'] . ')'], JSON_UNESCAPED_UNICODE);
    exit;
}

$maxBytes = 25 * 1024 * 1024;
if ($file['size'] > $maxBytes) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'El audio es demasiado grande (máx. 25 MB)'], JSON_UNESCAPED_UNICODE);
    exit;
}

$mime = '';
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $mime = (string) finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    }
}
if ($mime === '' && function_exists('mime_content_type')) {
    $mime = (string) mime_content_type($file['tmp_name']);
}
if ($mime === '') {
    $mime = (string) ($file['type'] ?? '');
}

$allowed = [
    'audio/webm', 'audio/ogg', 'audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/x-wav',
    'audio/mp4', 'audio/x-m4a', 'audio/m4a', 'video/webm', 'application/octet-stream',
];
$ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
$extOk = in_array($ext, ['webm', 'ogg', 'mp3', 'wav', 'm4a', 'mp4', 'mpeg'], true);

if ($mime !== '' && !in_array($mime, $allowed, true) && strpos($mime, 'audio/') !== 0 && $mime !== 'video/webm' && !$extOk) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Formato de audio no soportado (' . $mime . ')'], JSON_UNESCAPED_UNICODE);
    exit;
}

$nombre = $file['name'] ?: 'grabacion.webm';
$result = transcribir_audio_openai($file['tmp_name'], $nombre);

if (!$result['success']) {
    http_response_code(500);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
