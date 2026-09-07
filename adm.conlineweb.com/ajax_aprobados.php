<?php
include "conn.php";
include "conn_hostingpro.php";

$sistema = (isset($_POST['sistema']) && $_POST['sistema'] === 'hostingpro') ? 'hostingpro' : 'conlineweb';
$conn = ($sistema === 'hostingpro') ? $conn_hp : $conn;

header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'data' => [], 'totales' => [], 'filtros_aplicados' => false];

try {
    $anio = isset($_POST['anio']) ? $_POST['anio'] : '';
    $mes = isset($_POST['mes']) ? $_POST['mes'] : '';
    $fecha_inicio = isset($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : '';
    $fecha_fin = isset($_POST['fecha_fin']) ? $_POST['fecha_fin'] : '';

    $sql = "SELECT 
        p.*,
        c.nombre_contacto AS cliente,
        TRIM(c.correo) AS correo_cliente,
        CASE 
            WHEN p.tipo_servicio = '2' THEN d.url_dominio
            WHEN p.tipo_servicio = '1' THEN h.nom_host
            ELSE NULL
        END AS nombre_servicio,
        h.producto
    FROM pagos p
    LEFT JOIN clientes c ON p.id_clie = c.id
    LEFT JOIN dominios d ON p.tipo_servicio = '2' AND p.id_servicio = d.id_dominio
    LEFT JOIN hosting h ON p.tipo_servicio = '1' AND p.id_servicio = h.id_orden
    WHERE p.estatus = 1 AND (p.sistema = '$sistema' OR p.sistema IS NULL OR p.sistema = '')";

    $where_conditions = [];
    
    if (!empty($fecha_inicio) && !empty($fecha_fin)) {
        $where_conditions[] = "DATE(p.fecha_pago) BETWEEN '" . $conn->real_escape_string($fecha_inicio) . "' AND '" . $conn->real_escape_string($fecha_fin) . "'";
        $response['filtros_aplicados'] = true;
    } elseif (!empty($fecha_inicio)) {
        $where_conditions[] = "DATE(p.fecha_pago) >= '" . $conn->real_escape_string($fecha_inicio) . "'";
        $response['filtros_aplicados'] = true;
    } elseif (!empty($fecha_fin)) {
        $where_conditions[] = "DATE(p.fecha_pago) <= '" . $conn->real_escape_string($fecha_fin) . "'";
        $response['filtros_aplicados'] = true;
    }
    
    if (!empty($anio) && empty($fecha_inicio) && empty($fecha_fin)) {
        $where_conditions[] = "YEAR(p.fecha_pago) = '" . $conn->real_escape_string($anio) . "'";
        $response['filtros_aplicados'] = true;
    }
    
    if (!empty($mes) && empty($fecha_inicio) && empty($fecha_fin)) {
        $where_conditions[] = "MONTH(p.fecha_pago) = '" . $conn->real_escape_string($mes) . "'";
        $response['filtros_aplicados'] = true;
    }
    
    if (!empty($where_conditions)) {
        $sql .= " AND " . implode(" AND ", $where_conditions);
    }
    
    $sql .= " ORDER BY p.fecha_pago DESC;";
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $pagos = $result->fetch_all(MYSQLI_ASSOC);
        $response['data'] = $pagos;
        
        // Calcular totales por moneda
        $totales = [];
        foreach ($pagos as $pago) {
            $moneda = $pago['currency'];
            $monto = floatval($pago['monto']);
            if (!isset($totales[$moneda])) {
                $totales[$moneda] = 0;
            }
            $totales[$moneda] += $monto;
        }
        $response['totales'] = $totales;
    }
    
    $response['success'] = true;
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>