<?php
/**
 * API plantillas de mensajes/comunicados (clientes.php → WhatsApp).
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

define('AUTH_MIDDLEWARE_FUNCTIONS_ONLY', true);
require_once dirname(__DIR__) . '/auth_middleware.php';
require_once dirname(__DIR__) . '/includes/cw_client_messages_service.php';

adm_start_session();

if (!isAdminSessionValid() && !loadSessionFromCookies()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autorizado'], JSON_UNESCAPED_UNICODE);
    exit;
}

$tipo = (int) ($_SESSION['tipo'] ?? 0);
if ($tipo < 1 || $tipo > 5) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sin permiso'], JSON_UNESCAPED_UNICODE);
    exit;
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = (string) ($_GET['action'] ?? $_POST['action'] ?? 'list');

try {
    if ($action === 'list' && $method === 'GET') {
        echo json_encode(['ok' => true, 'data' => cw_client_messages_load()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $input = $_POST;
    $raw = file_get_contents('php://input');
    if (is_string($raw) && $raw !== '' && strpos((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== false) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $input = array_merge($input, $decoded);
            if (isset($decoded['action'])) {
                $action = (string) $decoded['action'];
            }
        }
    }

    if ($action === 'save_template') {
        $result = cw_client_messages_upsert_template([
            'id' => (string) ($input['id'] ?? ''),
            'title' => (string) ($input['title'] ?? ''),
            'category' => (string) ($input['category'] ?? 'general'),
            'body' => (string) ($input['body'] ?? ''),
        ]);
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'delete_template') {
        $result = cw_client_messages_delete_template((string) ($input['id'] ?? ''));
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'save_settings') {
        $data = cw_client_messages_load();
        $data['settings']['google_review_url'] = trim((string) ($input['google_review_url'] ?? ''));
        if (!cw_client_messages_save($data)) {
            echo json_encode(['ok' => false, 'error' => 'No se pudo guardar configuración'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'set_google_review') {
        $clienteId = (int) ($input['cliente_id'] ?? 0);
        $sistemaReview = trim((string) ($input['sistema'] ?? 'conlineweb'));
        if ($sistemaReview === '') {
            $sistemaReview = 'conlineweb';
        }
        $doneRaw = $input['done'] ?? false;
        $done = filter_var($doneRaw, FILTER_VALIDATE_BOOLEAN)
            || $doneRaw === 1
            || $doneRaw === '1'
            || $doneRaw === 'true'
            || $doneRaw === true;
        $note = trim((string) ($input['note'] ?? ''));
        $byUid = (int) ($_SESSION['uid'] ?? 0) ?: null;

        $result = cw_client_google_review_set($clienteId, $sistemaReview, $done, $byUid, $note);
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Acción no válida'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
