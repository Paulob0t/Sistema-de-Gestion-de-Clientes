<?php
require_once dirname(__DIR__) . '/auth_middleware.php';
require_once dirname(__DIR__) . '/conn.php';
require_once dirname(__DIR__) . '/includes/cw_hub_config.php';
require_once dirname(__DIR__) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__) . '/includes/cw_hub_permissions.php';
require_once dirname(__DIR__) . '/includes/adm_paths.php';
require_once __DIR__ . '/includes/inbox_helpers.php';

cw_hub_migrate($conn);

$id = (int) ($_GET['id'] ?? 0);
$partial = isset($_GET['partial']);

if ($id <= 0) {
    if ($partial) {
        http_response_code(400);
        echo '<p class="text-danger p-4">ID inválido.</p>';
        exit;
    }
    header('Location: ' . adm_href('leads/inbox.php'));
    exit;
}

$stmt = $conn->prepare('SELECT * FROM leads WHERE id = ? AND eliminado = 0 LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$lead = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$lead) {
    if ($partial) {
        http_response_code(404);
        echo '<p class="text-danger p-4">Lead no encontrado.</p>';
        exit;
    }
    header('Location: ' . adm_href('leads/inbox.php'));
    exit;
}

if (!$partial) {
    header('Location: ' . adm_href('leads/inbox.php?id=' . $id));
    exit;
}

cw_inbox_mark_read($conn, $id);

$acts = [];
$aq = $conn->prepare('SELECT * FROM cw_lead_actividades WHERE lead_id = ? ORDER BY created_at ASC');
$aq->bind_param('i', $id);
$aq->execute();
$acts = $aq->get_result()->fetch_all(MYSQLI_ASSOC);
$aq->close();

$adjuntos = cw_inbox_get_adjuntos($conn, $id);
$timeline = cw_inbox_build_timeline($conn, $lead, $acts, $adjuntos);
$svc = CW_HUB_SERVICIOS[$lead['servicio'] ?? ''] ?? ($lead['servicio'] ?? '—');
$canEdit = cw_hub_can_edit_lead($lead);
$responsableNombre = cw_hub_responsable_nombre($conn, (int) ($lead['responsable_id'] ?? 0));

header('Content-Type: text/html; charset=utf-8');
include __DIR__ . '/partials/conversacion.php';
