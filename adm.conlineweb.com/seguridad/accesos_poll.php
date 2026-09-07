<?php
/**
 * Actualización en vivo para Seguridad → Accesos (JSON).
 * Compatible local (MAMP) y cPanel.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

@ini_set('display_errors', '0');

require_once dirname(__DIR__) . '/auth_middleware.php';
require_once dirname(__DIR__) . '/conn.php';

$cwLoadShared = static function ($basename) {
    $candidates = [
        dirname(__DIR__) . '/includes/' . $basename,
        dirname(__DIR__) . '/../includes/' . $basename,
        '/home/conlineweb/includes/' . $basename,
    ];
    foreach ($candidates as $path) {
        if (is_file($path)) {
            require_once $path;
            return true;
        }
    }

    return false;
};

$tipoUser = (int) ($_SESSION['tipo'] ?? 0);
if ($tipoUser !== 1 && $tipoUser !== 2) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'acceso_denegado'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli) || !$cwLoadShared('cw_portal_login_log.php')) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'modulo_no_disponible'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ownEventId = (int) ($_SESSION['cw_login_event_id'] ?? 0);
$idsRaw = trim((string) ($_GET['ids'] ?? ''));
$ids = [];
if ($idsRaw !== '') {
    foreach (explode(',', $idsRaw) as $part) {
        $id = (int) trim($part);
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    $ids = array_values($ids);
    if (count($ids) > 300) {
        $ids = array_slice($ids, 0, 300);
    }
}

try {
    cw_portal_login_log_ensure_table($conn);
    $stats = cw_portal_login_log_stats($conn, 24, 5);
    $sessions = [];

    foreach ($ids as $eventId) {
        $row = cw_portal_login_log_find($conn, $eventId);
        if ($row === null) {
            continue;
        }
        $st = cw_portal_login_log_session_state($row);
        $etype = (string) ($row['event_type'] ?? '');
        $canClose = $etype === 'success'
            && !empty($st['is_open'])
            && $eventId !== $ownEventId;

        $sessions[(string) $eventId] = [
            'code' => $st['code'],
            'label' => $st['label'],
            'is_open' => !empty($st['is_open']),
            'is_closed' => !empty($st['is_closed']),
            'is_live' => !empty($st['is_live']),
            'started_fmt' => $st['started_fmt'],
            'ended_fmt' => $st['ended_fmt'],
            'last_seen_fmt' => $st['last_seen_fmt'],
            'close_by' => $st['close_by'],
            'close_label' => $st['close_label'],
            'ended_note' => $st['ended_note'],
            'can_close' => $canClose,
            'is_own' => $eventId === $ownEventId,
        ];
    }

    $tz = new DateTimeZone('America/Mexico_City');
    $now = new DateTimeImmutable('now', $tz);

    echo json_encode([
        'ok' => true,
        'at' => $now->format('d/m/Y H:i:s'),
        'stats' => $stats,
        'sessions' => $sessions,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('seguridad/accesos_poll: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'consulta_fallida'], JSON_UNESCAPED_UNICODE);
}
