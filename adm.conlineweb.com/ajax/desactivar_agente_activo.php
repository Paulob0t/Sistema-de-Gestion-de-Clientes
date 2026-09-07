<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../auth_middleware.php';

// Obtener parámetros
$telefono = isset($_POST['telefono']) ? trim((string)$_POST['telefono']) : '';

if ($telefono === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Teléfono es requerido']);
    exit;
}

// Limpiar teléfono (solo dígitos)
$digits = preg_replace('/\D+/', '', $telefono);

if ($digits === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Teléfono inválido']);
    exit;
}

// Buscar el archivo de metadata
$sessionDir = realpath(__DIR__ . '/../whatsapp/sessions');

if ($sessionDir === false || !is_dir($sessionDir)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Directorio de sesiones no encontrado']);
    exit;
}

$metaFile = $sessionDir . DIRECTORY_SEPARATOR . 'whatsapp_' . $digits . '_meta.json';

if (!file_exists($metaFile)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Sesión no encontrada']);
    exit;
}

// Cargar metadata
$metaRaw = @file_get_contents($metaFile);
$meta = json_decode($metaRaw, true) ?: [];

// Desactivar el flag de agente activo
$meta['agent_active'] = false;
$meta['agent_deactivated_at'] = gmdate('c');

// Si hubo handoff a humano, permitir que el bot retome la conversación
// (el webhook no responde automáticamente cuando human_handoff está activo)
if (isset($meta['human_handoff'])) {
    unset($meta['human_handoff']);
}
if (isset($meta['human_handoff_requested_at'])) {
    unset($meta['human_handoff_requested_at']);
}
if (isset($meta['human_handoff_reason'])) {
    unset($meta['human_handoff_reason']);
}

// Limpieza opcional de metadatos relacionados
if (isset($meta['agent_last_interaction'])) {
    unset($meta['agent_last_interaction']);
}

// Guardar metadata
$saved = @file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

if ($saved === false) {
    error_log("ERROR: No se pudo guardar el archivo de metadata");
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'No se pudo actualizar la sesión']);
    exit;
}

error_log("Agente desactivado / bot reactivado para teléfono: " . $digits);

echo json_encode([
    'success' => true,
    'message' => 'Bot reactivado. El asistente automático responderá los próximos mensajes.'
], JSON_UNESCAPED_UNICODE);
