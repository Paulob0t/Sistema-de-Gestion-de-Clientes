<?php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_permissions.php';
require_once dirname(__DIR__, 2) . '/includes/cw_chat_migrate.php';
require_once dirname(__DIR__, 2) . '/includes/cw_chatbot_service.php';

cw_hub_require('hub.crm.view');
cw_chat_migrate($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$titulo = trim((string) ($_POST['titulo'] ?? ''));
$pregunta = trim((string) ($_POST['pregunta'] ?? ''));
$respuesta = trim((string) ($_POST['respuesta'] ?? ''));
$palabras = trim((string) ($_POST['palabras_clave'] ?? ''));
$intencion = trim((string) ($_POST['intencion'] ?? ''));
$categoriaRaw = trim((string) ($_POST['categoria_id'] ?? ''));
$categoriaId = $categoriaRaw !== '' ? (int) $categoriaRaw : null;
$prioridad = (int) ($_POST['prioridad'] ?? 10);
$activo = (int) ($_POST['activo'] ?? 1) ? 1 : 0;
$now = date('Y-m-d H:i:s');

if ($titulo === '' || $pregunta === '' || $respuesta === '') {
    echo json_encode(['success' => false, 'message' => 'Campos obligatorios incompletos']);
    exit;
}

try {
    if ($id > 0) {
        if ($categoriaId !== null && $categoriaId > 0) {
            $stmt = $conn->prepare('UPDATE cw_chat_conocimiento SET categoria_id=?, titulo=?, pregunta=?, respuesta=?, palabras_clave=?, intencion=?, prioridad=?, activo=?, updated_at=? WHERE id=?');
            if (!$stmt) {
                throw new RuntimeException('No se pudo preparar la actualización');
            }
            $stmt->bind_param('isssssiisi', $categoriaId, $titulo, $pregunta, $respuesta, $palabras, $intencion, $prioridad, $activo, $now, $id);
        } else {
            $stmt = $conn->prepare('UPDATE cw_chat_conocimiento SET categoria_id=NULL, titulo=?, pregunta=?, respuesta=?, palabras_clave=?, intencion=?, prioridad=?, activo=?, updated_at=? WHERE id=?');
            if (!$stmt) {
                throw new RuntimeException('No se pudo preparar la actualización');
            }
            $stmt->bind_param('sssssiisi', $titulo, $pregunta, $respuesta, $palabras, $intencion, $prioridad, $activo, $now, $id);
        }
        $stmt->execute();
        $stmt->close();
    } else {
        if ($categoriaId !== null && $categoriaId > 0) {
            $stmt = $conn->prepare('INSERT INTO cw_chat_conocimiento (categoria_id, titulo, pregunta, respuesta, palabras_clave, intencion, prioridad, activo, created_at) VALUES (?,?,?,?,?,?,?,?,?)');
            if (!$stmt) {
                throw new RuntimeException('No se pudo preparar el registro');
            }
            $stmt->bind_param('isssssiis', $categoriaId, $titulo, $pregunta, $respuesta, $palabras, $intencion, $prioridad, $activo, $now);
        } else {
            $stmt = $conn->prepare('INSERT INTO cw_chat_conocimiento (categoria_id, titulo, pregunta, respuesta, palabras_clave, intencion, prioridad, activo, created_at) VALUES (NULL,?,?,?,?,?,?,?,?)');
            if (!$stmt) {
                throw new RuntimeException('No se pudo preparar el registro');
            }
            $stmt->bind_param('sssssiis', $titulo, $pregunta, $respuesta, $palabras, $intencion, $prioridad, $activo, $now);
        }
        $stmt->execute();
        $stmt->close();
    }

    cw_chatbot_invalidate_cache();

    echo json_encode(['success' => true, 'message' => 'Guardado'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
