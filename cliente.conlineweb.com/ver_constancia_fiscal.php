<?php
/**
 * Visor HTML de constancia (mismo dominio que el archivo).
 * Fuente: /constancias_fiscales/{archivo}
 * Permite embeber desde adm.conlineweb.com sin proxy en adm.
 */
require_once __DIR__ . '/includes/cw_constancia_fiscal.php';

$filename = cw_constancia_fiscal_filename_from_stored($_GET['f'] ?? '');
if ($filename === null) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Archivo no válido';
    exit;
}

$info = cw_constancia_fiscal_parse($filename);
$fileUrl = cw_constancia_fiscal_public_url($filename);
$local = cw_constancia_fiscal_upload_dir() . $filename;

if (!is_file($local)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Constancia no encontrada';
    exit;
}

$type = $info['type'] ?? 'pdf';
$safeUrl = htmlspecialchars($fileUrl, ENT_QUOTES, 'UTF-8');
$safeName = htmlspecialchars($filename, ENT_QUOTES, 'UTF-8');

// El PDF ya es público; este HTML solo envuelve para el iframe del admin
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=120');
header("Content-Security-Policy: frame-ancestors 'self' https://adm.conlineweb.com http://adm.conlineweb.com https://*.conlineweb.com");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#000147">
    <title><?php echo $safeName; ?></title>
    <style>
        html, body { margin: 0; height: 100%; background: #fff; }
        img, embed, iframe, object {
            display: block;
            width: 100%;
            height: 100%;
            border: 0;
        }
        img { object-fit: contain; padding: 8px; box-sizing: border-box; }
    </style>
</head>
<body>
<?php if ($type === 'image'): ?>
    <img src="<?php echo $safeUrl; ?>" alt="Constancia fiscal">
<?php else: ?>
    <embed src="<?php echo $safeUrl; ?>" type="application/pdf">
<?php endif; ?>
</body>
</html>
