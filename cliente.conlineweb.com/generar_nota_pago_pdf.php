<?php
/**
 * Descarga del mismo comprobante PDF que adm (generador compartido).
 * GET: id (pago), inline=1 (ver en navegador)
 */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', 0);
date_default_timezone_set('America/Mexico_City');

require_once __DIR__ . '/includes/cliente_session.php';
cliente_start_session();

if (!cliente_is_logged_in()) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Sesión no válida';
    exit;
}

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/../adm.conlineweb.com/includes/cw_nota_pago_pdf.php';

$pagoId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$inline = isset($_GET['inline']) && (string) $_GET['inline'] === '1';
$uid = (int) ($_SESSION['uid'] ?? 0);

if ($pagoId <= 0 || $uid <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Solicitud inválida';
    exit;
}

$chk = $conn->prepare('SELECT id, estatus FROM pagos WHERE id = ? AND id_clie = ? LIMIT 1');
if (!$chk) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Error de consulta';
    exit;
}
$chk->bind_param('ii', $pagoId, $uid);
$chk->execute();
$pago = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$pago) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Pago no encontrado';
    exit;
}

if ((int) $pago['estatus'] !== 1) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'El comprobante estará disponible cuando el pago se marque como pagado.';
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
