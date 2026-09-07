<?php
/**
 * Webhook entrante n8n → actualizar lead (pipeline, nota).
 * POST JSON + header X-CW-Hub-Key
 */
require_once dirname(__DIR__) . '/includes/cw_hub_config.php';
require_once dirname(__DIR__) . '/conn.php';
require_once dirname(__DIR__) . '/includes/cw_hub_migrate.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST only']);
    exit;
}

$key = $_SERVER['HTTP_X_CW_HUB_KEY'] ?? '';
if (!hash_equals(CW_HUB_API_KEY, $key)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

cw_hub_migrate($conn);
$data = json_decode(file_get_contents('php://input'), true) ?: [];

$leadId = (int) ($data['lead_id'] ?? 0);
$pipeline = trim((string) ($data['pipeline_estado'] ?? ''));
$nota = trim((string) ($data['nota'] ?? ''));

if ($leadId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'lead_id required']);
    exit;
}

$now = date('Y-m-d H:i:s');

if ($pipeline !== '' && isset(CW_HUB_PIPELINE[$pipeline])) {
    $stmt = $conn->prepare('UPDATE leads SET pipeline_estado = ?, ultima_interaccion = ? WHERE id = ? AND eliminado = 0');
    $stmt->bind_param('ssi', $pipeline, $now, $leadId);
    $stmt->execute();
    $stmt->close();
} else {
    $stmt = $conn->prepare('UPDATE leads SET ultima_interaccion = ? WHERE id = ? AND eliminado = 0');
    $stmt->bind_param('si', $now, $leadId);
    $stmt->execute();
    $stmt->close();
}

if ($nota !== '') {
    $ins = $conn->prepare("INSERT INTO cw_lead_actividades (lead_id, tipo, descripcion, created_at) VALUES (?, 'mensaje', ?, ?)");
    $ins->bind_param('iss', $leadId, $nota, $now);
    $ins->execute();
    $ins->close();
}

echo json_encode(['success' => true, 'lead_id' => $leadId]);
