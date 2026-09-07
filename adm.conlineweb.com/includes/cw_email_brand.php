<?php
/**
 * Proxy a la ruta canónica de marca de correo.
 * Fuente: sistema/includes/cw_email_brand.php
 */
declare(strict_types=1);

$__cwBrandPaths = [
    dirname(__DIR__, 2) . '/includes/cw_email_brand.php',
    '/home/conlineweb/includes/cw_email_brand.php',
];
foreach ($__cwBrandPaths as $__cwBrandPath) {
    if (is_file($__cwBrandPath)) {
        require_once $__cwBrandPath;
        break;
    }
}
unset($__cwBrandPaths, $__cwBrandPath);
