<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../auth_middleware.php';
require_once __DIR__ . '/bootstrap_conexion.php';
require_once __DIR__ . '/../includes/requerimientos_chat_service.php';
require_once __DIR__ . '/../includes/requerimientos_contexto_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'JSON inválido']);
    exit;
}

$idCliente = isset($input['id_cliente']) ? (int) $input['id_cliente'] : 0;
$idProyecto = isset($input['id_proyecto']) ? (int) $input['id_proyecto'] : 0;
$contexto = requerimientos_cargar_contexto(
    $conn,
    $idCliente > 0 ? $idCliente : null,
    $idProyecto > 0 ? $idProyecto : null
);
$contextoTexto = $contexto['texto'] ?? '';

$messages = [];
if (!empty($input['reset'])) {
    echo json_encode([
        'success' => true,
        'mensaje_chat' => requerimientos_saludo_con_contexto($contexto),
        'documento' => [
            'resumen_solicitud' => '',
            'detalle_tecnico' => '',
            'requerimientos_funcionales' => [],
            'criterios_aceptacion' => [],
            'dudas_abiertas' => [],
            'prioridad_sugerida' => 'Media',
            'titulo_sugerido' => '',
        ],
        'listo_para_ticket' => false,
        'descripcion_markdown' => '',
        'contexto' => $contexto,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawMessages = $input['messages'] ?? [];
if (!is_array($rawMessages) || count($rawMessages) === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Se requiere al menos un mensaje']);
    exit;
}

foreach ($rawMessages as $msg) {
    if (!is_array($msg)) {
        continue;
    }
    $role = $msg['role'] ?? '';
    if (!in_array($role, ['user', 'assistant'], true)) {
        continue;
    }

    $content = trim((string) ($msg['content'] ?? ''));
    $images = $msg['images'] ?? [];
    $sanitized = [];
    if ($role === 'user' && is_array($images)) {
        foreach (array_slice($images, 0, 5) as $img) {
            $url = trim((string) $img);
            if ($url !== '' && preg_match('#^data:image/(jpeg|jpg|png|gif|webp);base64,#i', $url)) {
                $sanitized[] = $url;
            }
        }
    }

    if ($content === '' && empty($sanitized)) {
        continue;
    }

    $entry = ['role' => $role, 'content' => $content];
    if (!empty($sanitized)) {
        $entry['images'] = $sanitized;
    }
    $messages[] = $entry;
}

if (count($messages) === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Mensajes inválidos']);
    exit;
}

$result = requerimientos_chat_call($messages, $contextoTexto);

if (!$result['success']) {
    http_response_code(500);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

$result['descripcion_markdown'] = requerimientos_documento_a_descripcion($result['documento']);
$result['contexto'] = $contexto;
echo json_encode($result, JSON_UNESCAPED_UNICODE);
