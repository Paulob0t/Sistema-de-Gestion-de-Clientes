<?php
header('Content-Type: application/json');
include "conn.php";
include "conn_hostingpro.php";

$sistema = (isset($_GET['sistema']) && $_GET['sistema'] === 'hostingpro') ? 'hostingpro' : 'conlineweb';
$conn = ($sistema === 'hostingpro') ? $conn_hp : $conn;
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['error' => 'ID de pago no proporcionado']);
    exit;
}

$clienteid= $_GET['id'];

try {
    $sql = "SELECT 
            p.*,
            c.nombre_contacto AS cliente,
            c.correo AS correo_cliente,
            CASE 
                WHEN p.tipo_servicio = '2' THEN d.url_dominio
                WHEN p.tipo_servicio = '1' THEN CONCAT('Producto ', h.tipo_producto)
                ELSE p.concepto
            END AS nombre_servicio,
            CASE
                WHEN p.tipo_servicio = '1' THEN pl.nombre
                ELSE NULL
            END AS nombre_plan,
            CASE
                WHEN p.tipo_servicio = '2' THEN d.fecha_pago
                WHEN p.tipo_servicio = '1' THEN h.fecha_pago
                WHEN p.tipo_servicio = '0' THEN p.fecha_limite_pago
                ELSE p.fecha_limite_pago
            END AS fecha_limite_pago,
            CASE
                WHEN p.tipo_servicio = '2' THEN d.fecha_pago
                WHEN p.tipo_servicio = '1' THEN h.fecha_pago
                WHEN p.tipo_servicio = '0' THEN p.fecha_limite_pago
                ELSE NULL
            END AS fecha_vencimiento_servicio
        FROM pagos p
        LEFT JOIN clientes c ON p.id_clie = c.id
        LEFT JOIN dominios d ON p.tipo_servicio = '2' AND p.id_servicio = d.id_dominio
        LEFT JOIN hosting h ON p.tipo_servicio = '1' AND p.id_servicio = h.id_orden
        LEFT JOIN planes pl ON h.producto = pl.id
        WHERE p.id_clie = ?
        ORDER BY p.fecha_pago >= CURDATE() DESC, p.fecha_pago ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $clienteid);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $pago = $result->fetch_assoc();
        echo json_encode($pago);
    } else {
        echo json_encode(['error' => 'Pago no encontrado']);
    }

    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['error' => 'Error en la base de datos: ' . $e->getMessage()]);
}

$conn->close();
?>
