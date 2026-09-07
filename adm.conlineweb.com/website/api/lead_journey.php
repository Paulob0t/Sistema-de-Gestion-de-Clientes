<?php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_config.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_analytics.php';

cw_hub_migrate($conn);

$leadId = (int) ($_GET['id'] ?? $_GET['lead_id'] ?? 0);
if ($leadId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID de lead requerido']);
    exit;
}

try {
    $journey = cw_analytics_lead_journey_payload($conn, $leadId);
    if (!$journey) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Lead no encontrado']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'timezone' => CW_HUB_TIMEZONE,
        'journey' => $journey,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[lead_journey.php] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al cargar el recorrido',
    ], JSON_UNESCAPED_UNICODE);
}
