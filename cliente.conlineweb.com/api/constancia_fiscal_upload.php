<?php
/**
 * Compatibilidad: adm ya usa el módulo compartido local.
 * Este endpoint sigue guardando con la misma función compartida.
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/includes/cw_constancia_fiscal.php';

if (!defined('CW_CONSTANCIA_UPLOAD_KEY')) {
    define('CW_CONSTANCIA_UPLOAD_KEY', 'cwhub_k8m2p9x4v7n1q5w3r6t0y2z8');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$key = (string) ($_SERVER['HTTP_X_CW_CONSTANCIA_KEY'] ?? $_POST['key'] ?? '');
if ($key === '' || !hash_equals(CW_CONSTANCIA_UPLOAD_KEY, $key)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

$clientId = (int) ($_POST['client_id'] ?? 0);
if ($clientId <= 0 || !isset($_FILES['constancia_fiscal'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Datos incompletos']);
    exit;
}

$result = cw_constancia_fiscal_save_upload($clientId, $_FILES['constancia_fiscal']);
http_response_code(!empty($result['ok']) ? 200 : 400);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
