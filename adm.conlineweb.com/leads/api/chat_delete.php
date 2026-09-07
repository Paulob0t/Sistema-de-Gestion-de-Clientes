<?php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_chat_migrate.php';
require_once dirname(__DIR__, 2) . '/includes/cw_chat_service.php';
require_once dirname(__DIR__) . '/includes/inbox_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

cw_chat_migrate($conn);

$ids = [];
if (isset($_POST['ids']) && is_array($_POST['ids'])) {
    foreach ($_POST['ids'] as $rawId) {
        $ids[] = (int) $rawId;
    }
} elseif (isset($_POST['ids']) && is_string($_POST['ids'])) {
    foreach (preg_split('/\s*,\s*/', $_POST['ids']) as $rawId) {
        $ids[] = (int) $rawId;
    }
} else {
    $ids[] = (int) ($_POST['id'] ?? 0);
}

$ids = array_values(array_unique(array_filter($ids, static function ($id) {
    return $id > 0;
})));

if ($ids === []) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

$uid = cw_inbox_uid();
$result = cw_chat_soft_delete($conn, $ids, $uid > 0 ? $uid : null);
$okCount = (int) ($result['ok'] ?? 0);
$failCount = (int) ($result['failed'] ?? 0);
$total = count($ids);
$ok = $okCount > 0;

if ($total === 1) {
    $message = $ok ? 'Chat eliminado de la bandeja' : 'No se pudo eliminar el chat';
} elseif ($failCount === 0) {
    $message = $okCount . ' chats eliminados de la bandeja';
} elseif ($okCount === 0) {
    $message = 'No se pudo eliminar ninguno de los chats seleccionados';
} else {
    $message = $okCount . ' eliminados, ' . $failCount . ' no encontrados o con error';
}

echo json_encode([
    'success' => $ok,
    'deleted' => $okCount,
    'failed' => $failCount,
    'message' => $message,
], JSON_UNESCAPED_UNICODE);
