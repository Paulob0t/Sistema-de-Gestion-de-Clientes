<?php

require_once dirname(__DIR__, 2) . '/includes/cw_hub_config.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_permissions.php';

function cw_inbox_uid(): int
{
    cw_hub_ensure_session();
    $uid = (int) ($_SESSION['uid'] ?? 0);
    if ($uid === 0 && defined('ADM_DEV_LOCAL') && ADM_DEV_LOCAL) {
        return 1;
    }
    return $uid;
}

function cw_inbox_user_name(mysqli $conn, ?int $uid): string
{
    if (!$uid) {
        return 'Sistema';
    }
    return cw_hub_responsable_nombre($conn, $uid);
}

function cw_inbox_pipeline_label(?string $estado): string
{
    $estado = trim((string) ($estado ?? 'nuevo'));
    if ($estado === '') {
        $estado = 'nuevo';
    }
    return CW_HUB_PIPELINE[$estado] ?? ucfirst($estado);
}

function cw_inbox_status_class(?string $estado): string
{
    $map = [
        'nuevo' => 'st-nuevo',
        'lead' => 'st-lead',
        'calificado' => 'st-calificado',
        'seguimiento' => 'st-seguimiento',
        'propuesta' => 'st-propuesta',
        'cierre' => 'st-cierre',
        'cerrado' => 'st-cerrado',
        'perdido' => 'st-perdido',
    ];
    $estado = trim((string) ($estado ?? 'nuevo'));
    return $map[$estado] ?? 'st-lead';
}

function cw_inbox_note_preview(string $text, int $max = 72): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text));
    if ($text === '') {
        return '';
    }
    if (mb_strlen($text) <= $max) {
        return $text;
    }
    return mb_substr($text, 0, $max - 1) . '…';
}

function cw_inbox_last_note_summary(array $notas): string
{
    if (empty($notas)) {
        return '';
    }
    $last = $notas[count($notas) - 1];
    return cw_inbox_note_preview((string) ($last['nota'] ?? ''));
}

/** @return array{pendientes:int,sin_seguimiento:bool,estatus_reciente:bool,por_revisar:bool} */
function cw_inbox_lead_flags(mysqli $conn, array $lead): array
{
    $id = (int) ($lead['id'] ?? 0);
    $pendientes = 0;

    $stmt = $conn->prepare('SELECT COUNT(*) c FROM cw_lead_actividades
        WHERE lead_id = ? AND proxima_accion IS NOT NULL AND proxima_accion <= NOW() AND recordatorio_notificado = 0');
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $pendientes = (int) ($row['c'] ?? 0);
        $stmt->close();
    }

    $ultima = $lead['ultima_interaccion'] ?? $lead['fecha_registro'] ?? null;
    $sinSeguimiento = !$ultima || strtotime((string) $ultima) < strtotime('-7 days');

    $estatusReciente = false;
    $sq = $conn->prepare("SELECT id FROM cw_lead_actividades
        WHERE lead_id = ? AND descripcion LIKE 'Estado cambiado%' AND created_at >= DATE_SUB(NOW(), INTERVAL 72 HOUR) LIMIT 1");
    if ($sq) {
        $sq->bind_param('i', $id);
        $sq->execute();
        $estatusReciente = (bool) $sq->get_result()->fetch_assoc();
        $sq->close();
    }

    $leido = $lead['inbox_leido_at'] ?? null;
    $porRevisar = false;
    if ($ultima) {
        $porRevisar = !$leido || strtotime((string) $leido) < strtotime((string) $ultima);
    }

    return [
        'pendientes' => $pendientes,
        'sin_seguimiento' => $sinSeguimiento,
        'estatus_reciente' => $estatusReciente,
        'por_revisar' => $porRevisar,
    ];
}

function cw_inbox_mark_read(mysqli $conn, int $leadId): void
{
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare('UPDATE leads SET inbox_leido_at = ? WHERE id = ? AND eliminado = 0');
    if ($stmt) {
        $stmt->bind_param('si', $now, $leadId);
        $stmt->execute();
        $stmt->close();
    }
}

function cw_inbox_upload_dir(int $leadId): string
{
    return dirname(__DIR__) . '/uploads/' . $leadId;
}

/** @return list<array<string,mixed>> */
function cw_inbox_get_adjuntos(mysqli $conn, int $leadId): array
{
    $rows = [];
    $stmt = $conn->prepare('SELECT * FROM cw_lead_adjuntos WHERE lead_id = ? ORDER BY created_at ASC');
    if (!$stmt) {
        return $rows;
    }
    $stmt->bind_param('i', $leadId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

/**
 * @param list<array<string,mixed>> $acts
 * @param list<array<string,mixed>> $adjuntos
 * @return list<array<string,mixed>>
 */
function cw_inbox_build_timeline(mysqli $conn, array $lead, array $acts, array $adjuntos): array
{
    $events = [];

    $events[] = [
        'kind' => 'registro',
        'date' => $lead['fecha_registro'] ?? date('Y-m-d H:i:s'),
        'user' => 'Sistema',
        'title' => 'Lead registrado',
        'body' => 'Se creó el registro en el CRM.',
        'meta' => [],
    ];

    $notas = json_decode($lead['notas'] ?? '[]', true);
    if (is_array($notas)) {
        foreach ($notas as $n) {
            $text = trim((string) ($n['nota'] ?? ''));
            if ($text === '') {
                continue;
            }
            $events[] = [
                'kind' => str_starts_with($text, 'Estado cambiado') ? 'estatus' : 'nota',
                'date' => $n['fecha'] ?? ($lead['fecha_registro'] ?? date('Y-m-d H:i:s')),
                'user' => cw_inbox_user_name($conn, (int) ($n['usuario_id'] ?? 0)),
                'title' => str_starts_with($text, 'Lead captado') ? 'Captura web' : 'Nota',
                'body' => $text,
                'meta' => ['legacy' => true],
            ];
        }
    }

    foreach ($acts as $a) {
        $desc = trim((string) ($a['descripcion'] ?? ''));
        if ($desc === '') {
            continue;
        }
        $isStatus = str_starts_with($desc, 'Estado cambiado');
        $events[] = [
            'kind' => $isStatus ? 'estatus' : (string) ($a['tipo'] ?? 'nota'),
            'date' => $a['created_at'] ?? date('Y-m-d H:i:s'),
            'user' => cw_inbox_user_name($conn, (int) ($a['usuario_id'] ?? 0)),
            'title' => $isStatus ? 'Cambio de estatus' : ucfirst((string) ($a['tipo'] ?? 'nota')),
            'body' => $desc,
            'meta' => [
                'proxima_accion' => $a['proxima_accion'] ?? null,
                'actividad_id' => (int) ($a['id'] ?? 0),
            ],
        ];
    }

    foreach ($adjuntos as $f) {
        $events[] = [
            'kind' => 'archivo',
            'date' => $f['created_at'] ?? date('Y-m-d H:i:s'),
            'user' => cw_inbox_user_name($conn, (int) ($f['usuario_id'] ?? 0)),
            'title' => 'Archivo adjunto',
            'body' => (string) ($f['nombre_original'] ?? 'Archivo'),
            'meta' => [
                'adjunto_id' => (int) ($f['id'] ?? 0),
                'mime_type' => $f['mime_type'] ?? '',
                'tamano' => (int) ($f['tamano'] ?? 0),
            ],
        ];
    }

    usort($events, static function ($a, $b) {
        return strtotime((string) $a['date']) <=> strtotime((string) $b['date']);
    });

    return $events;
}

function cw_inbox_format_bytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return round($bytes / 1048576, 1) . ' MB';
}
