<?php
/**
 * Sirve imágenes subidas para plantillas de mailing (público, solo uploads).
 * Optimizado: headers cortos, CORS abierto, sin auth (OpenAI/email clients).
 */
declare(strict_types=1);

@ini_set('zlib.output_compression', '0');
@ini_set('display_errors', '0');

$f = basename((string) ($_GET['f'] ?? ''));
if ($f === '' || !preg_match('/^[a-zA-Z0-9._-]+$/', $f)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Bad request');
}

$path = dirname(__DIR__) . '/storage/mailing/uploads/' . $f;
if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Not found');
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$types = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
];
if (!isset($types[$ext])) {
    http_response_code(415);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Unsupported');
}

$size = filesize($path);
if ($size === false) {
    http_response_code(500);
    exit('Size error');
}

header('Content-Type: ' . $types[$ext]);
header('Content-Length: ' . (string) $size);
header('Cache-Control: public, max-age=604800, immutable');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('X-Content-Type-Options: nosniff');
header('Accept-Ranges: bytes');

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Evitar buffers que ralenticen la descarga
while (ob_get_level() > 0) {
    ob_end_clean();
}

$fp = fopen($path, 'rb');
if ($fp === false) {
    http_response_code(500);
    exit('Read error');
}
fpassthru($fp);
fclose($fp);
exit;
