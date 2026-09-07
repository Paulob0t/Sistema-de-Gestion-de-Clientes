<?php
/**
 * Permisos Hub Analítico + CRM por tipo de usuario.
 * 1=admin, 2=solicitudes, 3=agente, 4=empresa, 5=gestión leads
 */
require_once __DIR__ . '/cw_hub_config.php';
require_once __DIR__ . '/adm_session.php';

function cw_hub_ensure_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        adm_start_session();
    }
}

function cw_hub_user_tipo(): int
{
    cw_hub_ensure_session();
    $tipo = (int) ($_SESSION['tipo'] ?? 0);
    if ($tipo === 0 && defined('ADM_DEV_LOCAL') && ADM_DEV_LOCAL && !adm_is_production_host()) {
        return 1;
    }
    return $tipo;
}

function cw_hub_user_id(): int
{
    cw_hub_ensure_session();
    $uid = (int) ($_SESSION['uid'] ?? 0);
    if ($uid === 0 && defined('ADM_DEV_LOCAL') && ADM_DEV_LOCAL && !adm_is_production_host()) {
        return 1;
    }
    return $uid;
}

function cw_hub_permissions_map(): array
{
    return [
        'hub.analytics.view'   => [1, 2, 3, 5],
        'hub.analytics.export' => [1, 2],
        'hub.reports.pdf'      => [1, 2],
        'hub.crm.view'         => [1, 2, 3, 5],
        'hub.crm.edit'         => [1, 2, 3, 5],
        'hub.crm.assign'       => [1, 2],
        'hub.crm.kanban'       => [1, 2, 3, 5],
    ];
}

function cw_hub_can(string $permission): bool
{
    $tipo = cw_hub_user_tipo();
    $allowed = cw_hub_permissions_map()[$permission] ?? [];
    return in_array($tipo, $allowed, true);
}

function cw_hub_require(string $permission): void
{
    if (!cw_hub_can($permission)) {
        header('Location: /index.php?hub_error=acceso_denegado');
        exit;
    }
}

function cw_hub_can_edit_lead(array $lead): bool
{
    if (!cw_hub_can('hub.crm.edit')) {
        return false;
    }
    if (cw_hub_user_tipo() === 5) {
        if (!empty($lead['origen_web'])) {
            return true;
        }
        return (int) ($lead['usuario_registro'] ?? 0) === cw_hub_user_id();
    }
    return true;
}

function cw_hub_list_responsables(mysqli $conn): array
{
    $rows = [];
    $r = @$conn->query("SELECT id, nombre FROM agentes WHERE idEmpresa IS NULL OR idEmpresa = '' ORDER BY nombre");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function cw_hub_responsable_nombre(mysqli $conn, ?int $id): string
{
    if (!$id) {
        return '—';
    }
    $stmt = $conn->prepare('SELECT nombre FROM agentes WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row['nombre'] ?? ('#' . $id);
}
