<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../error.log');

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../auth_middleware.php';

$telefono = isset($_POST['telefono']) ? trim((string)$_POST['telefono']) : '';
if ($telefono === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Teléfono requerido']);
    exit;
}

$digits = preg_replace('/\D+/', '', $telefono);

$sessionsDir = realpath(__DIR__ . '/../whatsapp/sessions');
if (!$sessionsDir) {
    echo json_encode(['success' => true, 'agente_activo' => false]);
    exit;
}

$metaFile = $sessionsDir . '/whatsapp_' . $digits . '_meta.json';
if (!file_exists($metaFile)) {
    echo json_encode(['success' => true, 'agente_activo' => false]);
    exit;
}

$meta = json_decode((string)@file_get_contents($metaFile), true) ?: [];
$agenteActivo = !empty($meta['agent_active']);

echo json_encode(['success' => true, 'agente_activo' => $agenteActivo]);
