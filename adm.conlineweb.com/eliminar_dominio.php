<?php
require_once __DIR__ . '/auth_middleware.php';
header('Content-Type: application/json; charset=utf-8');
include 'conn.php';
include 'conn_hostingpro.php';

// Detectar sistema: conlineweb, hostingpro o planpro
$sistema = 'conlineweb';
if (isset($_POST['sistema'])) {
    if ($_POST['sistema'] === 'hostingpro') {
        $sistema = 'hostingpro';
    } elseif ($_POST['sistema'] === 'planpro') {
        $sistema = 'planpro';
    }
}
$db = ($sistema === 'hostingpro' || $sistema === 'planpro') ? $conn_hp : $conn;

$response = ['success' => false];
$permanente = isset($_POST['permanente']) && (int) $_POST['permanente'] === 1;

if (isset($_POST['id']) && (isset($_POST['eliminar']) || $permanente)) {
    $id = intval($_POST['id']);
    $eliminar = isset($_POST['eliminar']) ? intval($_POST['eliminar']) : 1;
    $clienteId = isset($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0;

    if ($eliminar === 1 || $permanente) {
        $sqlValidacion = "SELECT COUNT(*) AS total FROM pagos WHERE tipo_servicio = 2 AND id_servicio = ? AND estatus = 0 AND Registro = 0";
        $stmtValidacion = $db->prepare($sqlValidacion);
        if ($stmtValidacion) {
            $stmtValidacion->bind_param('i', $id);
            $stmtValidacion->execute();
            $res = $stmtValidacion->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmtValidacion->close();

            if (!empty($row['total']) && intval($row['total']) > 0) {
                $response['message'] = 'No se puede eliminar: el dominio tiene pagos pendientes asociados.';
                echo json_encode($response, JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    }

    if ($permanente) {
        if ($clienteId > 0) {
            $sql = "DELETE FROM dominios WHERE id_dominio = ? AND cliente_id = ?";
            $stmt = $db->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('ii', $id, $clienteId);
            }
        } else {
            $sql = "DELETE FROM dominios WHERE id_dominio = ?";
            $stmt = $db->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('i', $id);
            }
        }

        if ($stmt) {
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $response['success'] = true;
                $response['message'] = 'Dominio eliminado permanentemente de la base de datos';
            } else {
                $response['message'] = 'No se encontró el dominio o no se pudo eliminar';
            }
            $stmt->close();
        } else {
            $response['message'] = 'Error al preparar la eliminación permanente';
        }
    } else {
        $sql = "UPDATE dominios SET eliminado = ? WHERE id_dominio = ?";
        $stmt = $db->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('ii', $eliminar, $id);
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = $eliminar === 1 ? 'Dominio eliminado correctamente' : 'Dominio restaurado correctamente';
            } else {
                $response['message'] = 'Error al ejecutar la consulta';
            }
            $stmt->close();
        } else {
            $response['message'] = 'Error al preparar la consulta';
        }
    }
} else {
    $response['message'] = 'Datos insuficientes';
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
