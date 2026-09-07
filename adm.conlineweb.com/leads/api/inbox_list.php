<?php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__) . '/includes/inbox_helpers.php';

cw_hub_migrate($conn);

$q = trim((string) ($_GET['q'] ?? ''));
$filtro = trim((string) ($_GET['filtro'] ?? ''));

$sql = "SELECT id, nombre, correo, telefono, pipeline_estado, estatus, notas,
               fecha_registro, ultima_interaccion, inbox_leido_at, servicio, origen_web
        FROM leads WHERE eliminado = 0";
$params = [];
$types = '';

if ($q !== '') {
    $sql .= " AND (nombre LIKE ? OR correo LIKE ? OR telefono LIKE ? OR CAST(id AS CHAR) = ?)";
    $like = '%' . $q . '%';
    $types .= 'ssss';
    $params = [$like, $like, $like, $q];
}

$sql .= ' ORDER BY COALESCE(ultima_interaccion, fecha_registro) DESC LIMIT 300';

$stmt = $conn->prepare($sql);
$data = [];
$stats = ['total' => 0, 'pendientes' => 0, 'sin_seguimiento' => 0, 'por_revisar' => 0];

if ($stmt) {
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $notas = json_decode($row['notas'] ?? '[]', true);
        if (!is_array($notas)) {
            $notas = [];
        }
        $flags = cw_inbox_lead_flags($conn, $row);

        if ($filtro === 'pendientes' && $flags['pendientes'] <= 0 && !$flags['por_revisar']) {
            continue;
        }
        if ($filtro === 'sin_seguimiento' && !$flags['sin_seguimiento']) {
            continue;
        }
        if ($filtro === 'por_revisar' && !$flags['por_revisar']) {
            continue;
        }

        $ultimaMov = $row['ultima_interaccion'] ?: $row['fecha_registro'];
        $badgeCount = $flags['pendientes'] + ($flags['por_revisar'] ? 1 : 0);

        $stats['total']++;
        if ($flags['pendientes'] > 0) {
            $stats['pendientes']++;
        }
        if ($flags['sin_seguimiento']) {
            $stats['sin_seguimiento']++;
        }
        if ($flags['por_revisar']) {
            $stats['por_revisar']++;
        }

        $data[] = [
            'id' => (int) $row['id'],
            'nombre' => $row['nombre'] ?? '',
            'telefono' => $row['telefono'] ?? '',
            'pipeline_estado' => $row['pipeline_estado'] ?? 'nuevo',
            'pipeline_label' => cw_inbox_pipeline_label($row['pipeline_estado'] ?? 'nuevo'),
            'status_class' => cw_inbox_status_class($row['pipeline_estado'] ?? 'nuevo'),
            'ultima_movimiento' => $ultimaMov,
            'ultima_nota' => cw_inbox_last_note_summary($notas),
            'flags' => $flags,
            'badge_count' => $badgeCount,
            'origen_web' => (int) ($row['origen_web'] ?? 0),
        ];
    }
    $stmt->close();
}

echo json_encode(['success' => true, 'data' => $data, 'stats' => $stats], JSON_UNESCAPED_UNICODE);
