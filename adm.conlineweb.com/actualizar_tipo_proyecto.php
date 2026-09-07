<?php
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
    require_once __DIR__ . '/includes/helpers_proyectos_tipo.php';

    if (!isset($conn) || $conn->connect_error) {
        throw new Exception('Error de conexión');
    }
    if (!adm_proyectos_ensure_tipo_proyecto($conn)) {
        throw new Exception('No se pudo preparar el campo tipo_proyecto');
    }

    $id = isset($_POST['id_proyecto']) ? filter_var($_POST['id_proyecto'], FILTER_VALIDATE_INT) : false;
    $tipo = isset($_POST['tipo_proyecto']) ? adm_proyecto_tipo_normalize($_POST['tipo_proyecto']) : null;

    if ($id === false || $id <= 0) {
        throw new Exception('ID de proyecto inválido');
    }
    if ($tipo === null || !array_key_exists($tipo, adm_proyecto_tipos_map())) {
        throw new Exception('Tipo de proyecto inválido');
    }

    $stmt = $conn->prepare('UPDATE proyectos SET tipo_proyecto = ? WHERE id_proyecto = ?');
    if (!$stmt) {
        throw new Exception('Error al preparar actualización');
    }
    $stmt->bind_param('ii', $tipo, $id);
    if (!$stmt->execute() || $stmt->affected_rows < 0) {
        $stmt->close();
        throw new Exception('No se pudo actualizar el tipo');
    }
    $stmt->close();

    respond([
        'success' => true,
        'message' => 'Tipo de proyecto actualizado',
        'tipo_proyecto' => $tipo,
        'tipo_label' => adm_proyecto_tipo_label($tipo),
        'id_proyecto' => $id,
    ]);
} catch (Throwable $e) {
    respond([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
