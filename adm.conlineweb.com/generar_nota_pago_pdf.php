<?php
/**
 * Descarga / vista de Nota de Pago (PDF) desde el panel de pagos.
 * GET: id (pago), sistema=conlineweb|hostingpro|planpro, inline=1 (ver en navegador)
 */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', 0);
date_default_timezone_set('America/Mexico_City');

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/conn_hostingpro.php';
require_once __DIR__ . '/includes/cw_nota_pago_pdf.php';

$sistema = 'conlineweb';
if (isset($_GET['sistema'])) {
    if ($_GET['sistema'] === 'hostingpro') {
        $sistema = 'hostingpro';
    } elseif ($_GET['sistema'] === 'planpro') {
        $sistema = 'planpro';
    }
}
$conn = ($sistema === 'hostingpro' || $sistema === 'planpro') ? $conn_hp : $conn;

$pagoId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$inline = isset($_GET['inline']) && (string) $_GET['inline'] === '1';

if ($pagoId <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'ID de pago inválido';
    exit;
}

$result = cw_generar_nota_pago_pdf_por_id($conn, $pagoId);
if (empty($result['ok']) || empty($result['path']) || !is_file($result['path'])) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $result['error'] ?? 'No se pudo generar el PDF';
    exit;
}

$path = $result['path'];
$filename = $result['filename'] ?? ('nota_pago_' . $pagoId . '.pdf');
$disposition = $inline ? 'inline' : 'attachment';

header('Content-Type: application/pdf');
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($path);
@unlink($path);
exit;
