<?php
// Config centralizada para subidas de tickets
// Detecta el esquema y host actuales
$__scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$__host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$__baseDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
if($__baseDir === '.' || $__baseDir === '/') $__baseDir = '';

// Ruta física base (dentro del proyecto del cliente)
define('TICKETS_UPLOAD_FS', __DIR__ . '/uploads/tickets/');

// URL base pública (respeta subcarpeta si existe)
define('TICKETS_UPLOAD_URL', $__scheme . $__host . ($__baseDir ? '/' . trim($__baseDir,'/') : '') . '/uploads/tickets/');

// Archivo de log
define('TICKETS_UPLOAD_LOG', __DIR__ . '/uploads/upload_debug.log');

// Asegurar directorio
if(!is_dir(TICKETS_UPLOAD_FS)) {
    @mkdir(TICKETS_UPLOAD_FS, 0755, true);
}
?>
