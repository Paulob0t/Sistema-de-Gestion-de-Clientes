<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once dirname(__DIR__, 2) . '/auth_middleware.php';
    require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_migrate.php';
    require_once dirname(__DIR__, 2) . '/includes/proyec_levantamiento_admin.php';

    cw_hub_migrate($conn);
    proyec_migrate($conn);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al iniciar el módulo: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$user = getAuthenticatedUser();
$usuarioId = (int) ($user['id'] ?? 0);

function proyec_view_json(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input') ?: '';
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        $body = $_POST;
    }

    $action = trim((string) ($body['action'] ?? ''));
    $leadId = (int) ($body['lead_id'] ?? 0);

    if ($action === 'mark_seen' && $leadId > 0) {
        proyec_admin_mark_project_seen($conn, $leadId, $usuarioId);
        proyec_view_json([
            'success' => true,
            'message' => 'Marcado como visto',
            'cambios_pendientes' => 0,
        ]);
    }

    proyec_view_json(['success' => false, 'message' => 'Acción no reconocida'], 400);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    proyec_view_json(['success' => false, 'message' => 'Método no permitido'], 405);
}

$leadId = (int) ($_GET['lead_id'] ?? 0);
if ($leadId <= 0) {
    proyec_view_json(['success' => false, 'message' => 'Lead inválido'], 400);
}

$lead = proyec_admin_load_lead_summary($conn, $leadId);
if ($lead === null) {
    proyec_view_json(['success' => false, 'message' => 'Lead no encontrado'], 404);
}

$project = proyec_admin_get_project_by_lead($conn, $leadId);
if ($project === null || !proyec_admin_project_has_content($project)) {
    proyec_view_json([
        'success' => true,
        'has_project' => false,
        'lead' => $lead,
        'message' => 'Este lead aún no tiene información en el formulario de levantamiento.',
    ]);
}

$markSeen = !empty($_GET['mark_seen']);
if ($markSeen) {
    proyec_admin_mark_project_seen($conn, $leadId, $usuarioId);
}

$cambios = proyec_admin_count_unseen_changes($conn, $leadId, $usuarioId);
$historial = proyec_admin_recent_historial($conn, $leadId, 12);
$steps = proyec_admin_step_titles();
$modalPayload = proyec_admin_build_modal_payload($project, $lead, $conn, $leadId);

$historialOut = [];
foreach ($historial as $row) {
    $paso = (int) ($row['paso'] ?? 0);
    $historialOut[] = [
        'paso' => $paso,
        'paso_titulo' => $steps[$paso] ?? ('Paso ' . $paso),
        'accion' => (string) ($row['accion'] ?? ''),
        'resumen' => (string) ($row['resumen'] ?? ''),
        'created_at' => proyec_admin_format_datetime((string) ($row['created_at'] ?? '')),
    ];
}

proyec_view_json([
    'success' => true,
    'has_project' => true,
    'lead_id' => $leadId,
    'lead' => $lead,
    'summary' => $modalPayload['summary'],
    'sections' => $modalPayload['sections'],
    'stats' => $modalPayload['stats'],
    'cambios_pendientes' => $cambios,
    'historial' => $historialOut,
    'form_client_url' => $modalPayload['form_client_url'],
    'marked_seen' => $markSeen,
]);
