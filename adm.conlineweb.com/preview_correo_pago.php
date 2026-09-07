<?php
/**
 * Vista previa del correo de pago (sin envío).
 * - JSON (default): meta del correo
 * - HTML: ?render=1&id=&modo=&sistema=  (para iframe, correo completo)
 */
ini_set('display_errors', '0');

if (ob_get_level()) {
    ob_clean();
}

function preview_pago_json(array $data): void
{
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function preview_pago_html(string $html): void
{
    while (ob_get_level()) {
        ob_end_clean();
    }
    // Permitir embeber en el modal de pagos (mismo origen)
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store');
    header('X-Frame-Options: SAMEORIGIN', true);
    header("Content-Security-Policy: frame-ancestors 'self'", true);
    echo $html;
    exit;
}

try {
    if (!defined('CW_BRAIN_WIDGET_DISABLE')) {
        define('CW_BRAIN_WIDGET_DISABLE', true);
    }
    // Vista previa en iframe del modal de pagos
    if (!defined('CW_ALLOW_SAMEORIGIN_FRAME')) {
        define('CW_ALLOW_SAMEORIGIN_FRAME', true);
    }
    require_once __DIR__ . '/auth_middleware.php';
    // Por si el middleware ya mandó DENY
    if (!headers_sent()) {
        header('X-Frame-Options: SAMEORIGIN', true);
        header("Content-Security-Policy: frame-ancestors 'self'", true);
    }
    require_once __DIR__ . '/conn.php';
    require_once __DIR__ . '/conn_hostingpro.php';
    require_once __DIR__ . '/includes/pago_correo_build.php';

    $req = array_merge($_GET, $_POST);
    $sistema = (isset($req['sistema']) && $req['sistema'] === 'hostingpro') ? 'hostingpro' : 'conlineweb';
    if (isset($req['sistema']) && $req['sistema'] === 'planpro') {
        $sistema = 'planpro';
    }
    $db = ($sistema === 'hostingpro' || $sistema === 'planpro') ? $conn_hp : $conn;
    if (!isset($db) || $db->connect_error) {
        throw new Exception('Error de conexión a base de datos');
    }

    $id = isset($req['id']) ? (int) $req['id'] : 0;
    $modosOk = ['pendiente', 'vencido', 'vencimiento', 'plazo', 'eliminacion'];
    $modoReq = isset($req['modo']) ? (string) $req['modo'] : 'pendiente';
    $modo = in_array($modoReq, $modosOk, true) ? $modoReq : 'pendiente';
    $asHtml = !empty($req['render']);
    if ($id <= 0) {
        throw new Exception('ID de pago no válido');
    }

    // En preview no metemos un hash largo que rompa el layout; el link real se genera al enviar
    $previewPayUrl = 'https://adm.conlineweb.com/#pago';
    $built = pago_correo_build($db, $id, $modo, $previewPayUrl);
    if (empty($built['ok'])) {
        throw new Exception($built['error'] ?? 'No se pudo generar la vista previa');
    }

    if ($asHtml) {
        preview_pago_html($built['html']);
    }

    preview_pago_json([
        'success' => true,
        'asunto' => $built['asunto'],
        'correo' => $built['correo'],
        'cliente' => $built['cliente'],
        'tipo' => $built['tipo'],
        'modo' => $built['modo'],
        'meta' => $built['meta'] ?? [],
        'html' => $built['html'],
        'preview_url' => 'preview_correo_pago.php?' . http_build_query([
            'render' => 1,
            'id' => $id,
            'modo' => $modo,
            'sistema' => $sistema,
        ]),
        'nota' => 'Vista previa. El enlace de pago real se genera al confirmar el envío.',
    ]);
} catch (Throwable $e) {
    $wantHtml = !empty($_GET['render']) || !empty($_POST['render']);
    if ($wantHtml) {
        preview_pago_html(
            '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Error</title></head>'
            . '<body style="font-family:Arial,sans-serif;padding:2rem;color:#b91c1c;">'
            . '<p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p></body></html>'
        );
    }
    preview_pago_json(['success' => false, 'message' => $e->getMessage()]);
}
