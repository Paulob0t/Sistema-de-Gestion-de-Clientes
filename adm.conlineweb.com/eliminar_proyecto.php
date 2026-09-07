<?php
require_once __DIR__ . '/auth_middleware.php';
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

function respond(array $data): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    require_once __DIR__ . '/conn.php';
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception('Error de conexión a la base de datos');
    }

    $id_proyecto = isset($_POST['id_proyecto']) ? filter_var($_POST['id_proyecto'], FILTER_VALIDATE_INT) : false;
    if ($id_proyecto === false || $id_proyecto <= 0) {
        throw new Exception('ID de proyecto no válido');
    }

    $stmtCheck = $conn->prepare('SELECT id_proyecto, nombre_proyecto FROM proyectos WHERE id_proyecto = ? LIMIT 1');
    if (!$stmtCheck) {
        throw new Exception('Error al preparar la consulta');
    }
    $stmtCheck->bind_param('i', $id_proyecto);
    $stmtCheck->execute();
    $proyecto = $stmtCheck->get_result()->fetch_assoc();
    $stmtCheck->close();

    if (!$proyecto) {
        throw new Exception('Proyecto no encontrado');
    }

    $conn->begin_transaction();

    try {
        $stmtTags = $conn->prepare('DELETE FROM proyectos_tags WHERE id_proyecto = ?');
        if ($stmtTags) {
            $stmtTags->bind_param('i', $id_proyecto);
            $stmtTags->execute();
            $stmtTags->close();
        }

        $stmtDel = $conn->prepare('DELETE FROM proyectos WHERE id_proyecto = ?');
        if (!$stmtDel) {
            throw new Exception('Error al preparar eliminación');
        }
        $stmtDel->bind_param('i', $id_proyecto);
        if (!$stmtDel->execute() || $stmtDel->affected_rows < 1) {
            $stmtDel->close();
            throw new Exception('No se pudo eliminar el proyecto');
        }
        $stmtDel->close();

        $conn->commit();
    } catch (Throwable $inner) {
        $conn->rollback();
        throw $inner;
    }

    respond([
        'success' => true,
        'message' => 'Proyecto eliminado correctamente',
        'id_proyecto' => $id_proyecto,
        'nombre_proyecto' => $proyecto['nombre_proyecto'] ?? '',
    ]);
} catch (Throwable $e) {
    respond([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
