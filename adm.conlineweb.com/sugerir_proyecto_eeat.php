<?php
/**
 * Sugiere campos E-E-A-T a partir de la URL del proyecto.
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/includes/helpers_proyectos_eeat.php';
require_once __DIR__ . '/includes/helpers_proyectos_tipo.php';

function respond_eeat(array $arr): void
{
    echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método no permitido');
    }
    $src = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    $url = isset($src['url']) ? trim((string) $src['url']) : '';
    $tipo = isset($src['tipo_proyecto']) ? adm_proyecto_tipo_normalize($src['tipo_proyecto']) : 0;
    if ($url === '') {
        throw new Exception('Falta la URL del proyecto');
    }
    $data = adm_proyecto_sugerir_eeat_desde_url($url, $tipo);
    respond_eeat(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    respond_eeat(['success' => false, 'message' => $e->getMessage()]);
}
