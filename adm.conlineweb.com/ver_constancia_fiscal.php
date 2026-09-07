<?php
/**
 * Redirección a la URL canónica compartida.
 */
require_once __DIR__ . '/auth_middleware.php';
require_once __DIR__ . '/includes/cw_constancia_fiscal.php';

$filename = cw_constancia_fiscal_filename_from_stored($_GET['f'] ?? '');
if ($filename === null) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Archivo no válido';
    exit;
}

header('Location: ' . cw_constancia_fiscal_public_url($filename), true, 302);
exit;
