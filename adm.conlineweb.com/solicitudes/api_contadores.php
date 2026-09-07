<?php
/**
 * Contadores de solicitudes por estado (JSON).
 */
if (!defined('CW_BRAIN_WIDGET_DISABLE')) {
    define('CW_BRAIN_WIDGET_DISABLE', true);
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../auth_middleware.php';
require_once __DIR__ . '/bootstrap_conexion.php';
require_once __DIR__ . '/helpers_agentes.php';

$out = [
    'success' => true,
    'counts' => [
        'Pendiente' => 0,
        'En Proceso' => 0,
        'Finalizado' => 0,
    ],
];

try {
    if (!isset($conexion) || !($conexion instanceof mysqli) || $conexion->connect_error) {
        throw new Exception('Sin conexión a base de datos');
    }

    $id_agente = isset($_GET['id_agente']) ? (int) $_GET['id_agente'] : 0;
    $where = '';
    if ($id_agente > 0) {
        $where = ' WHERE ' . generarFiltroAgente($id_agente);
    }

    $sql = "SELECT TRIM(estado) AS estado, COUNT(*) AS total FROM solicitudes $where GROUP BY TRIM(estado)";
    $res = $conexion->query($sql);

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $estado = isset($row['estado']) ? trim((string) $row['estado']) : '';
            $total = isset($row['total']) ? (int) $row['total'] : 0;
            // Normalizar variantes comunes
            if (strcasecmp($estado, 'Pendiente') === 0) {
                $out['counts']['Pendiente'] = $total;
            } elseif (strcasecmp($estado, 'En Proceso') === 0 || strcasecmp($estado, 'En proceso') === 0) {
                $out['counts']['En Proceso'] = $total;
            } elseif (strcasecmp($estado, 'Finalizado') === 0 || strcasecmp($estado, 'Finalizada') === 0) {
                $out['counts']['Finalizado'] = $total;
            } elseif (array_key_exists($estado, $out['counts'])) {
                $out['counts'][$estado] = $total;
            }
        }
        $res->close();
    } else {
        throw new Exception('Error en consulta: ' . $conexion->error);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'No se pudieron obtener los contadores',
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
