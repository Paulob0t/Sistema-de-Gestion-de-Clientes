<?php
/**
 * Render HTML de una plantilla de correo (solo preview, sin envío).
 * Protegido por sesión admin — usado como src del iframe en templates.php
 */
if (!defined('CW_BRAIN_WIDGET_DISABLE')) {
    define('CW_BRAIN_WIDGET_DISABLE', true);
}
require_once dirname(__DIR__) . '/auth_middleware.php';
require_once __DIR__ . '/../includes/email_preview_samples.php';

$catalog = email_preview_catalog();
$v = isset($_GET['v']) ? (string) $_GET['v'] : '';

if ($v === '' || !isset($catalog[$v])) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>No encontrada</title></head>'
        . '<body style="font-family:Montserrat,Arial,sans-serif;padding:2rem;color:#334155">'
        . '<p>Plantilla no encontrada.</p></body></html>';
    exit;
}

$html = email_preview_render($v);
if ($html === '') {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Error</title></head>'
        . '<body style="font-family:Montserrat,Arial,sans-serif;padding:2rem;color:#334155">'
        . '<p>No se pudo generar la vista previa.</p></body></html>';
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
echo $html;
