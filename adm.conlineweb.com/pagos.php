<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
include "menu.php";
include "conn.php";
include "conn_hostingpro.php";
include "plan_helper.php";

// Sistema activo: conlineweb (default), hostingpro o planpro
$sistema = 'conlineweb';
if (isset($_GET['sistema'])) {
    if ($_GET['sistema'] === 'hostingpro') {
        $sistema = 'hostingpro';
    } elseif ($_GET['sistema'] === 'planpro') {
        $sistema = 'planpro';
    }
}
// Plan Pro usa la misma BD que ConlineWeb (conlineweb_hosting)
$conn = ($sistema === 'hostingpro' || $sistema === 'planpro') ? $conn_hp : $conn;

// Obtener fechas del formulario si existen (para la vista inicial)
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';
$filtro_anio = isset($_GET['filtro_anio']) ? $_GET['filtro_anio'] : '';
$filtro_mes = isset($_GET['filtro_mes']) ? $_GET['filtro_mes'] : '';

// Consulta para pagos pendientes FILTRANDO POR SISTEMA
// Para Plan Pro: solo pagos con sistema='conlineweb' (sin NULL ni vacíos)
$where_sistema = ($sistema === 'planpro') 
    ? "p.sistema = 'conlineweb'" 
    : "(p.sistema = ? OR p.sistema IS NULL OR p.sistema = '')";

$sql = "SELECT 
    p.*,
    c.nombre_contacto AS cliente,
    TRIM(c.correo) AS correo_cliente,
    c.telefono AS telefono_cliente,
    CASE 
        WHEN p.tipo_servicio = '2' THEN d.url_dominio
        WHEN p.tipo_servicio = '1' THEN h.nom_host
        ELSE NULL
    END AS nombre_servicio,
    h.producto,
    d.fecha_pago AS fecha_pago_dominio_ref,
    h.fecha_pago AS fecha_pago_hosting_ref
FROM pagos p
LEFT JOIN clientes c ON p.id_clie = c.id
LEFT JOIN dominios d ON p.tipo_servicio = '2' AND p.id_servicio = d.id_dominio
LEFT JOIN hosting h ON p.tipo_servicio = '1' AND p.id_servicio = h.id_orden
WHERE p.estatus = 0 AND p.Registro = 0 AND $where_sistema
ORDER BY p.fecha_pago >= CURDATE() DESC, p.fecha_pago ASC;";

if ($sistema === 'planpro') {
    $result = $conn->query($sql);
} else {
    $stmt_pendientes = $conn->prepare($sql);
    $stmt_pendientes->bind_param("s", $sistema);
    $stmt_pendientes->execute();
    $result = $stmt_pendientes->get_result();
    $stmt_pendientes->close();
}

$pagos_pendientes = [];
if ($result && $result->num_rows > 0) {
    $pagos_pendientes = $result->fetch_all(MYSQLI_ASSOC);
}

foreach ($pagos_pendientes as &$pago) {
    $fecha_limite_efectiva = $pago['fecha_limite_pago'] ?? '';

    if (empty($fecha_limite_efectiva) || $fecha_limite_efectiva === '0000-00-00') {
        if ((string)($pago['tipo_servicio'] ?? '') === '2') {
            $fecha_limite_efectiva = $pago['fecha_pago_dominio_ref'] ?? '';
        } elseif ((string)($pago['tipo_servicio'] ?? '') === '1') {
            $fecha_limite_efectiva = $pago['fecha_pago_hosting_ref'] ?? '';
        }
    }

    if (empty($fecha_limite_efectiva) || $fecha_limite_efectiva === '0000-00-00') {
        $fecha_limite_efectiva = '';
    }

    $pago['fecha_limite_efectiva'] = $fecha_limite_efectiva;
}
unset($pago);

usort($pagos_pendientes, function ($a, $b) {
    $fa = $a['fecha_limite_efectiva'] ?? '';
    $fb = $b['fecha_limite_efectiva'] ?? '';

    if ($fa === '' && $fb === '') {
        return ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
    }
    if ($fa === '') {
        return 1;
    }
    if ($fb === '') {
        return -1;
    }

    $cmp = strcmp($fa, $fb);
    if ($cmp !== 0) {
        return $cmp;
    }

    return ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
});

// Separar pagos pendientes por tipo
$hosting_pendientes = array_filter($pagos_pendientes, function($row) { return $row['tipo_servicio'] == 1 && (!isset($row['manual']) || $row['manual'] != 1); });
$dominios_pendientes = array_filter($pagos_pendientes, function($row) { return $row['tipo_servicio'] == 2 && (!isset($row['manual']) || $row['manual'] != 1); });
$manuales_pendientes = array_filter($pagos_pendientes, function($row) { return isset($row['manual']) && $row['manual'] == 1; });

/**
 * Suma montos pendientes agrupados por moneda.
 */
$cw_sumar_montos_por_moneda = static function (array $pagos): array {
    $out = [];
    foreach ($pagos as $pago) {
        $moneda = trim((string) ($pago['currency'] ?? 'MXN'));
        if ($moneda === '') {
            $moneda = 'MXN';
        }
        if (!isset($out[$moneda])) {
            $out[$moneda] = 0.0;
        }
        $out[$moneda] += (float) ($pago['monto'] ?? 0);
    }
    ksort($out);
    return $out;
};

$totales_hosting_pendientes = $cw_sumar_montos_por_moneda($hosting_pendientes);
$totales_dominios_pendientes = $cw_sumar_montos_por_moneda($dominios_pendientes);
$totales_manuales_pendientes = $cw_sumar_montos_por_moneda($manuales_pendientes);

// Consulta para aprobados con filtro de fechas, año, mes Y SISTEMA
// Para Plan Pro: solo pagos con sistema='conlineweb' (sin NULL ni vacíos)
$where_sistema_aprobados = ($sistema === 'planpro') 
    ? "p.sistema = 'conlineweb'" 
    : "(p.sistema = '$sistema' OR p.sistema IS NULL OR p.sistema = '')";

$sql_aprobados = "SELECT 
    p.*,
    c.nombre_contacto AS cliente,
    TRIM(c.correo) AS correo_cliente,
    c.telefono AS telefono_cliente,
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
WHERE p.estatus = 1 AND $where_sistema_aprobados";

$where_conditions = [];
if (!empty($fecha_inicio) && !empty($fecha_fin)) {
    $where_conditions[] = "DATE(p.fecha_pago) BETWEEN '$fecha_inicio' AND '$fecha_fin'";
} elseif (!empty($fecha_inicio)) {
    $where_conditions[] = "DATE(p.fecha_pago) >= '$fecha_inicio'";
} elseif (!empty($fecha_fin)) {
    $where_conditions[] = "DATE(p.fecha_pago) <= '$fecha_fin'";
}

if (!empty($filtro_anio) && empty($fecha_inicio) && empty($fecha_fin)) {
    $where_conditions[] = "YEAR(p.fecha_pago) = '$filtro_anio'";
}

if (!empty($filtro_mes) && empty($fecha_inicio) && empty($fecha_fin)) {
    $where_conditions[] = "MONTH(p.fecha_pago) = '$filtro_mes'";
}

if (!empty($where_conditions)) {
    $sql_aprobados .= " AND " . implode(" AND ", $where_conditions);
}

$sql_aprobados .= " ORDER BY p.fecha_pago DESC;";

$result_aprobados = $conn->query($sql_aprobados);
$aprobados = [];
if ($result_aprobados && $result_aprobados->num_rows > 0) {
    $aprobados = $result_aprobados->fetch_all(MYSQLI_ASSOC);
}

// Obtener años disponibles con cantidad de pagos FILTRANDO POR SISTEMA
// Para Plan Pro: solo pagos con sistema='conlineweb'
$where_sistema_anios = ($sistema === 'planpro') 
    ? "sistema = 'conlineweb'" 
    : "(sistema = '$sistema' OR sistema IS NULL OR sistema = '')";

$sql_anios = "SELECT 
    YEAR(fecha_pago) as anio, 
    COUNT(*) as total_pagos 
FROM pagos 
WHERE estatus = 1 AND fecha_pago IS NOT NULL AND fecha_pago != '0000-00-00' AND $where_sistema_anios
GROUP BY YEAR(fecha_pago) 
ORDER BY anio DESC";
$result_anios = $conn->query($sql_anios);
$anios_disponibles = [];
if ($result_anios && $result_anios->num_rows > 0) {
    while ($row = $result_anios->fetch_assoc()) {
        $anios_disponibles[] = $row;
    }
}

// Obtener meses disponibles con pagos para el año seleccionado FILTRANDO POR SISTEMA
$meses_disponibles = [];
if (!empty($filtro_anio)) {
    $sql_meses = "SELECT 
        MONTH(fecha_pago) as mes_numero,
        COUNT(*) as total_pagos
    FROM pagos 
    WHERE estatus = 1 
        AND fecha_pago IS NOT NULL 
        AND fecha_pago != '0000-00-00'
        AND YEAR(fecha_pago) = '$filtro_anio'
        AND $where_sistema_anios
    GROUP BY MONTH(fecha_pago)
    ORDER BY mes_numero ASC";
    $result_meses = $conn->query($sql_meses);
    if ($result_meses && $result_meses->num_rows > 0) {
        while ($row = $result_meses->fetch_assoc()) {
            $meses_disponibles[] = $row;
        }
    }
}

$nombres_meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

// Calcular total de pagos aprobados por moneda
$monedas_aprobadas = [];
$mostrarTotalAprobados = false;
if (!empty($aprobados)) {
    foreach ($aprobados as $pago) {
        $moneda = $pago['currency'];
        $monto = floatval($pago['monto']);
        if (!isset($monedas_aprobadas[$moneda])) {
            $monedas_aprobadas[$moneda] = 0;
        }
        $monedas_aprobadas[$moneda] += $monto;
        $mostrarTotalAprobados = true;
    }
}

// Consulta para pagos eliminados FILTRANDO POR SISTEMA
// Para Plan Pro: solo pagos con sistema='conlineweb'
$where_sistema_eliminados = ($sistema === 'planpro') 
    ? "p.sistema = 'conlineweb'" 
    : "(p.sistema = '$sistema' OR p.sistema IS NULL OR p.sistema = '')";

$sql_eliminados = "SELECT 
    p.*,
    c.nombre_contacto AS cliente,
    TRIM(c.correo) AS correo_cliente,
    c.telefono AS telefono_cliente,
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
WHERE p.Registro = 1 AND $where_sistema_eliminados
ORDER BY p.fecha DESC, p.hora DESC;";

$result_eliminados = $conn->query($sql_eliminados);
$eliminados = [];
if ($result_eliminados && $result_eliminados->num_rows > 0) {
    $eliminados = $result_eliminados->fetch_all(MYSQLI_ASSOC);
}

// Estadísticas para el dashboard
$total_pendientes = count($pagos_pendientes);
$total_hosting = count($hosting_pendientes);
$total_dominios = count($dominios_pendientes);
$total_manuales = count($manuales_pendientes);
$total_aprobados = count($aprobados);
$total_eliminados = count($eliminados);

$hoy = date('Y-m-d');
$vencidos = 0;
$por_vencer_30 = 0;
$hosting_alerta_vencimiento = [];
$hosting_alerta_plazo = [];
$hosting_alerta_eliminacion = [];

foreach ($pagos_pendientes as $pago) {
    if (!empty($pago['fecha_limite_efectiva']) && $pago['fecha_limite_efectiva'] !== '0000-00-00') {
        $fecha_limite = $pago['fecha_limite_efectiva'];
        if ($fecha_limite < $hoy) {
            $vencidos++;
        } else {
            $dias = (strtotime($fecha_limite) - strtotime($hoy)) / (60 * 60 * 24);
            if ($dias <= 30) {
                $por_vencer_30++;
            }
        }
    }
}

foreach ($hosting_pendientes as $pago) {
    $fecha_venc = $pago['fecha_limite_efectiva'] ?? '';
    if (empty($fecha_venc) || $fecha_venc === '0000-00-00') {
        continue;
    }
    $fecha_plazo = date('Y-m-d', strtotime($fecha_venc . ' +5 days'));
    $fecha_eliminacion = date('Y-m-d', strtotime($fecha_plazo . ' +1 day'));
    $item = [
        'cliente' => $pago['cliente'] ?? '',
        'servicio' => $pago['nombre_servicio'] ?? ($pago['producto'] ?? 'Hosting'),
        'fecha_vencimiento' => $fecha_venc,
        'fecha_plazo' => $fecha_plazo,
        'fecha_eliminacion' => $fecha_eliminacion,
    ];
    if ($fecha_eliminacion <= $hoy) {
        $hosting_alerta_eliminacion[] = $item;
    } elseif ($fecha_plazo <= $hoy) {
        $hosting_alerta_plazo[] = $item;
    } elseif ($fecha_venc <= $hoy) {
        $hosting_alerta_vencimiento[] = $item;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="css/admin-platform.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link href="vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
    <link href="css/admin-datatables.css?v=20250715" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    
    <style>
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        
        :root {
            --primary-dark: #000147;
            --primary: #1a1f6b;
            --primary-light: #2d3388;
            --primary-soft: #eef0ff;
            --secondary: #64748b;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --dark: #0f172a;
            --light: #f8fafc;
            --border: #e2e8f0;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #64748b;
        }
        
        body {
            background: #f1f5f9;
            font-family: 'Inter', sans-serif;
            color: var(--text-primary);
        }
        
        /* Dashboard Cards Modernos */
        .stat-card {
            background: white;
            border-radius: 24px;
            border: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-dark), var(--primary-light));
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px -12px rgba(0, 1, 71, 0.15);
        }
        
        .stat-icon {
            width: 52px;
            height: 52px;
            background: var(--primary-soft);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-dark);
        }
        
        .stat-number {
            font-size: 34px;
            font-weight: 700;
            color: var(--dark);
            line-height: 1.2;
            letter-spacing: -0.02em;
        }
        
        .stat-label {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
            letter-spacing: 0.2px;
        }
        
        /* Filtros Modernos */
        .filter-section {
            background: white;
            border-radius: 24px;
            padding: 24px;
            margin-bottom: 28px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
        }
        
        .filter-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }
        
        .form-control-modern {
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 12px 16px;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
            transition: all 0.2s;
            background: white;
            width: 100%;
        }
        
        .form-control-modern:focus {
            border-color: var(--primary-dark);
            box-shadow: 0 0 0 3px rgba(0, 1, 71, 0.08);
            outline: none;
        }
        
        /* Botones Modernos Mejorados */
        .btn-modern-filter {
            border-radius: 14px;
            padding: 12px 24px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s;
            border: none;
            letter-spacing: 0.3px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .btn-modern-filter:hover {
            transform: translateY(-2px);
        }
        
        .btn-filter {
            background: var(--primary-dark);
            color: white;
            box-shadow: 0 2px 4px rgba(0, 1, 71, 0.1);
        }
        
        .btn-filter:hover {
            background: var(--primary);
            box-shadow: 0 4px 12px rgba(0, 1, 71, 0.2);
        }
        
        .btn-clear-filter {
            background: var(--secondary);
            color: white;
        }
        
        .btn-clear-filter:hover {
            background: #475569;
            box-shadow: 0 4px 12px rgba(71, 85, 105, 0.2);
        }
        
        .btn-refresh {
            background: var(--info);
            color: white;
        }
        
        .btn-refresh:hover {
            background: #2563eb;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
        }
        
        /* Tabla Moderna */
        .modern-table {
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid var(--border);
            background: white;
        }
        
        .modern-table table {
            width: 100%;
        }
        
        .modern-table thead th {
            background: #fafbff;
            color: var(--text-primary);
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding: 18px 16px;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }
        
        .modern-table thead th i {
            margin-right: 8px;
        }
        
        .modern-table tbody tr {
            transition: all 0.2s ease;
            border-bottom: 1px solid var(--border);
        }
        
        .modern-table tbody tr:hover {
            background: #fefefe;
            box-shadow: inset 0 0 0 1px var(--primary-soft);
        }
        
        .modern-table tbody td {
            padding: 16px 16px;
            vertical-align: middle;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border);
        }
        
        /* Badges de estado */
        .badge-status {
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.2px;
            display: inline-block;
        }
        
        .badge-pendiente {
            background: #ffedd5;
            color: #b45309;
        }
        
        .badge-aprobado {
            background: #dcfce7;
            color: #15803d;
        }
        
        .badge-eliminado {
            background: #fee2e2;
            color: #b91c1c;
        }
        
        .badge-hosting {
            background: #e0e7ff;
            color: #1e40af;
        }
        
        .badge-dominio {
            background: #dcfce7;
            color: #15803d;
        }
        
        .badge-manual {
            background: #fef3c7;
            color: #b45309;
        }
        
        /* Indicador de vencimiento */
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }
        
        .status-dot.vencido {
            background: var(--danger);
            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4);
            animation: pulse-red 1.8s infinite;
        }
        
        .status-dot.warning {
            background: var(--warning);
        }
        
        .status-dot.success {
            background: var(--success);
        }
        
        @keyframes pulse-red {
            0% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4);
            }
            70% {
                box-shadow: 0 0 0 8px rgba(239, 68, 68, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
            }
        }
        
        .days-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 24px;
            font-size: 12px;
            font-weight: 500;
            margin-top: 6px;
        }
        
        .days-badge.critical {
            background: #fee2e2;
            color: #b91c1c;
        }
        
        .days-badge.normal {
            background: var(--primary-soft);
            color: var(--primary-dark);
        }
        
        /* Tabs Modernos */
        .nav-tabs-modern {
            border-bottom: 2px solid var(--border);
            margin-bottom: 28px;
        }
        
        .nav-tabs-modern .nav-link {
            border: none;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 14px;
            padding: 12px 28px;
            position: relative;
            letter-spacing: 0.2px;
            background: transparent;
        }
        
        .nav-tabs-modern .nav-link i {
            margin-right: 10px;
        }
        
        .nav-tabs-modern .nav-link.active {
            color: var(--primary-dark);
            background: transparent;
        }
        
        .nav-tabs-modern .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--primary-dark);
        }
        
        /* Sub Tabs con contadores */
        .sub-tabs {
            border-bottom: 1px solid var(--border);
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .sub-tabs .nav-tabs {
            border-bottom: none;
        }
        
        .sub-tabs .nav-link {
            border: none;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 14px;
            padding: 10px 20px;
            position: relative;
            background: transparent;
        }
        
        .sub-tabs .nav-link i {
            margin-right: 8px;
        }
        
        .sub-tabs .nav-link.active {
            color: var(--primary-dark);
            background: transparent;
        }
        
        .sub-tabs .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--primary-dark);
        }
        
        .sub-tabs .badge-count {
            background: var(--primary-soft);
            color: var(--primary-dark);
            margin-left: 8px;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .btn-refresh-tables {
            padding: 8px 20px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            background: var(--info);
            color: white;
            border: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-refresh-tables:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }
        
        .btn-refresh-tables i {
            font-size: 14px;
        }
        
        /* Botones de acción */
        .btn-modern {
            border-radius: 12px;
            padding: 8px 14px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s;
            border: none;
            letter-spacing: 0.2px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-modern:hover {
            transform: translateY(-1px);
        }
        
        .btn-success-modern {
            background: var(--success);
            color: white;
        }
        
        .btn-success-modern:hover {
            background: #059669;
        }
        
        .btn-info-modern {
            background: var(--info);
            color: white;
        }
        
        .btn-info-modern:hover {
            background: #2563eb;
        }
        
        .btn-primary-modern {
            background: var(--primary-dark);
            color: white;
        }
        
        .btn-primary-modern:hover {
            background: var(--primary);
        }
        
        .btn-warning-modern {
            background: var(--warning);
            color: white;
        }
        
        .btn-warning-modern:hover {
            background: #d97706;
        }
        
        .btn-danger-modern {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger-modern:hover {
            background: #dc2626;
        }
        
        .btn-whatsapp-modern {
            background: #25D366;
            color: white;
        }
        
        .btn-whatsapp-modern:hover {
            background: #128C7E;
        }
        
        /* Alert Moderna */
        .alert-modern {
            border-radius: 20px;
            border: none;
            padding: 18px 24px;
            margin-bottom: 28px;
        }
        
        .alert-warning {
            background: #fffbeb;
            border-left: 4px solid var(--warning);
            color: #78350f;
        }
        
        .alert-info {
            background: #eff6ff;
            border-left: 4px solid var(--info);
            color: #1e40af;
        }

        .alert-danger {
            background: #fef2f2;
            border-left: 4px solid #dc2626;
            color: #7f1d1d;
        }

        .alert-orange {
            background: #fff7ed;
            border-left: 4px solid #ea580c;
            color: #9a3412;
        }
        
        .total-card {
            background: var(--primary-soft);
            border-radius: 20px;
            padding: 20px 24px;
            margin-top: 24px;
            border-left: 4px solid var(--success);
        }
        
        .total-card--pendiente {
            background: #fffbeb;
            border-left-color: var(--warning);
        }
        
        .total-card--pendiente h5 {
            color: #b45309;
        }
        
        .total-card h5 {
            margin: 0 0 8px 0;
            color: var(--success);
            font-weight: 700;
            font-size: 16px;
        }

        .total-card h5 i {
            margin-right: 0.55rem;
        }
        
        .total-amount {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-dark);
        }

        .total-card--pendiente .total-amount {
            font-size: 20px;
            margin-top: 4px;
        }
        
        .btn-group {
            gap: 6px;
        }
        
        .fw-semibold {
            font-weight: 600;
        }
        
        .fw-medium {
            font-weight: 500;
        }
        
        .badge-id {
            background: var(--primary-soft);
            color: var(--primary-dark);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .concepto-cell {
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            cursor: help;
        }
        
        /* Animaciones */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .fade-in-up {
            animation: fadeInUp 0.5s ease-out;
        }
        
        /* Badge de conteo en selectores */
        .select-badge {
            background: var(--primary-soft);
            color: var(--primary-dark);
            border-radius: 20px;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
        }
        
        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--light);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--text-muted);
        }
        
        /* Mejoras en selects */
        select.form-control-modern option {
            padding: 10px;
        }
        
        .filter-row {
            display: flex;
            gap: 16px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        
        .filter-item {
            flex: 1;
            min-width: 150px;
        }
        
        .filter-actions {
            display: flex;
            gap: 12px;
            margin-top: 28px;
        }
        
        /* Loading overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            display: none;
            justify-content: center;
            align-items: center;
        }
        
        .loading-spinner {
            background: white;
            border-radius: 16px;
            padding: 20px 30px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }
        
        .table-loading {
            text-align: center;
            padding: 40px;
        }
        
        .table-loading i {
            font-size: 32px;
            color: var(--primary-dark);
            margin-bottom: 12px;
        }
        
        @media (max-width: 768px) {
            .filter-row {
                flex-direction: column;
            }
            .filter-item {
                width: 100%;
            }
            .filter-actions {
                flex-direction: column;
            }
            .sub-tabs {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }
            .sub-tabs .nav-tabs {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
            <span>Actualizando tablas...</span>
        </div>
    </div>
    
    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            <div class="container-xl my-4 py-4 legacy-touch" style="max-width: 96% !important;">
                
                <!-- Header -->
                <div class="d-flex align-items-center justify-content-between mb-4 fade-in-up">
                    <div>
                        <h1 class="h2 mb-1" style="color: var(--primary-dark); font-weight: 700; letter-spacing: -0.02em;">
                            <i class="fas fa-credit-card me-2"></i> &nbsp;&nbsp;Gestión de Pagos
                        </h1>
                        <p class="text-secondary-custom mb-0" style="font-weight: 500;">Administración de pagos pendientes, aprobados y eliminados</p>
                    </div>
                </div>

                <div class="mb-4 fade-in-up">
                    <div style="display:inline-flex; background:#eef0ff; border-radius:14px; padding:4px; gap:4px;">
                        <a href="?sistema=conlineweb"
                           style="text-decoration:none; padding:8px 22px; border-radius:10px; font-size:13px; font-weight:600; letter-spacing:0.2px; transition:all 0.2s;
                                  <?php echo $sistema === 'conlineweb' ? 'background:var(--primary-dark);color:#fff;box-shadow:0 2px 8px rgba(0,1,71,0.25);' : 'color:var(--primary-dark);'; ?>">
                            <i class="fas fa-server" style="margin-right:6px;"></i>ADM ConlineWeb
                        </a>
                        <a href="?sistema=hostingpro"
                           style="text-decoration:none; padding:8px 22px; border-radius:10px; font-size:13px; font-weight:600; letter-spacing:0.2px; transition:all 0.2s;
                                  <?php echo $sistema === 'hostingpro' ? 'background:var(--primary-dark);color:#fff;box-shadow:0 2px 8px rgba(0,1,71,0.25);' : 'color:var(--primary-dark);'; ?>">
                            <i class="fas fa-rocket" style="margin-right:6px;"></i>ADM HostingPro
                        </a>
                        <a href="?sistema=planpro"
                           style="text-decoration:none; padding:8px 22px; border-radius:10px; font-size:13px; font-weight:600; letter-spacing:0.2px; transition:all 0.2s;
                                  <?php echo $sistema === 'planpro' ? 'background:var(--primary-dark);color:#fff;box-shadow:0 2px 8px rgba(0,1,71,0.25);' : 'color:var(--primary-dark);'; ?>">
                            <i class="fas fa-shopping-cart" style="margin-right:6px;"></i>Plan Pro
                        </a>
                    </div>
                </div>
                
                <!-- Dashboard Cards -->
                <div class="row mb-4 fade-in-up">
                    <div class="col-xl-2 col-md-4 mb-4">
                        <div class="stat-card p-4" onclick="filtrarPorTab('pendientes')">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stat-number"><?php echo $total_pendientes; ?></div>
                                    <div class="stat-label mt-1">Pagos Pendientes</div>
                                </div>
                                <div class="stat-icon">
                                    <i class="fas fa-clock fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 mb-4">
                        <div class="stat-card p-4" onclick="filtrarPorTipo('hosting')">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stat-number"><?php echo $total_hosting; ?></div>
                                    <div class="stat-label mt-1">Hosting</div>
                                </div>
                                <div class="stat-icon">
                                    <i class="fas fa-server fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 mb-4">
                        <div class="stat-card p-4" onclick="filtrarPorTipo('dominio')">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stat-number"><?php echo $total_dominios; ?></div>
                                    <div class="stat-label mt-1">Dominios</div>
                                </div>
                                <div class="stat-icon">
                                    <i class="fas fa-globe fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 mb-4">
                        <div class="stat-card p-4" onclick="filtrarPorTipo('manual')">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stat-number"><?php echo $total_manuales; ?></div>
                                    <div class="stat-label mt-1">Servicios Manuales</div>
                                </div>
                                <div class="stat-icon">
                                    <i class="fas fa-tools fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 mb-4">
                        <div class="stat-card p-4" onclick="filtrarPorTab('aprobados')">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stat-number"><?php echo $total_aprobados; ?></div>
                                    <div class="stat-label mt-1">Aprobados</div>
                                </div>
                                <div class="stat-icon">
                                    <i class="fas fa-check-circle fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 mb-4">
                        <div class="stat-card p-4" onclick="filtrarPorTab('eliminados')">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stat-number"><?php echo $total_eliminados; ?></div>
                                    <div class="stat-label mt-1">Eliminados</div>
                                </div>
                                <div class="stat-icon">
                                    <i class="fas fa-trash-alt fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Alert de pagos vencidos -->
                <?php if ($vencidos > 0): ?>
                <div class="alert-modern alert-warning mb-4 fade-in-up">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-exclamation-triangle me-3 mt-1" style="color: #f59e0b;"></i>&nbsp;&nbsp;
                        <div>
                             <strong class="d-block mb-1"> ¡Atención!</strong>
                            <span><?php echo $vencidos; ?> pago(s) tienen la fecha límite vencida.</span>
                            <?php if ($por_vencer_30 > 0): ?>
                                <span>Además, <?php echo $por_vencer_30; ?> pago(s) vencerán en los próximos 30 días.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($hosting_alerta_vencimiento)): ?>
                <div class="alert-modern alert-warning mb-4 fade-in-up">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-calendar-times me-3 mt-1" style="color: #f59e0b;"></i>&nbsp;&nbsp;
                        <div>
                            <strong class="d-block mb-1">Alerta de vencimiento (hosting)</strong>
                            <span><?php echo count($hosting_alerta_vencimiento); ?> hosting(s) ya llegaron a la fecha de vencimiento. Tienen 5 días de plazo.</span>
                            <ul class="mt-2 mb-0">
                                <?php foreach ($hosting_alerta_vencimiento as $item): ?>
                                <li>
                                    <strong><?php echo htmlspecialchars((string) $item['servicio']); ?></strong>
                                    — <?php echo htmlspecialchars((string) $item['cliente']); ?>
                                    — vencimiento <?php echo date('d/m/Y', strtotime($item['fecha_vencimiento'])); ?>
                                    — plazo hasta <?php echo date('d/m/Y', strtotime($item['fecha_plazo'])); ?>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($hosting_alerta_plazo)): ?>
                <div class="alert-modern alert-orange mb-4 fade-in-up">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-hourglass-end me-3 mt-1" style="color: #ea580c;"></i>&nbsp;&nbsp;
                        <div>
                            <strong class="d-block mb-1">Alerta de plazo (hosting)</strong>
                            <span><?php echo count($hosting_alerta_plazo); ?> hosting(s) ya cumplieron los 5 días de plazo. Mañana corresponde eliminación.</span>
                            <ul class="mt-2 mb-0">
                                <?php foreach ($hosting_alerta_plazo as $item): ?>
                                <li>
                                    <strong><?php echo htmlspecialchars((string) $item['servicio']); ?></strong>
                                    — <?php echo htmlspecialchars((string) $item['cliente']); ?>
                                    — plazo <?php echo date('d/m/Y', strtotime($item['fecha_plazo'])); ?>
                                    — eliminación <?php echo date('d/m/Y', strtotime($item['fecha_eliminacion'])); ?>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($hosting_alerta_eliminacion)): ?>
                <div class="alert-modern alert-danger mb-4 fade-in-up">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-trash-alt me-3 mt-1" style="color: #dc2626;"></i>&nbsp;&nbsp;
                        <div>
                            <strong class="d-block mb-1">Alerta de eliminación (hosting)</strong>
                            <span><?php echo count($hosting_alerta_eliminacion); ?> hosting(s) ya pasaron 1 día después del plazo. El servicio debe eliminarse.</span>
                            <ul class="mt-2 mb-0">
                                <?php foreach ($hosting_alerta_eliminacion as $item): ?>
                                <li>
                                    <strong><?php echo htmlspecialchars((string) $item['servicio']); ?></strong>
                                    — <?php echo htmlspecialchars((string) $item['cliente']); ?>
                                    — venció el <?php echo date('d/m/Y', strtotime($item['fecha_vencimiento'])); ?>
                                    — plazo <?php echo date('d/m/Y', strtotime($item['fecha_plazo'])); ?>
                                    — <strong>eliminación <?php echo date('d/m/Y', strtotime($item['fecha_eliminacion'])); ?></strong>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Tabs principales -->
                <ul class="nav nav-tabs-modern" id="pagosTab" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="pendientes-tab" data-toggle="tab" href="#pendientes" role="tab">
                            <i class="fas fa-clock me-2"></i>Pendientes
                            <span class="badge bg-warning ms-1" style="background: var(--warning) !important; color: white;"><?php echo $total_pendientes; ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="aprobados-tab" data-toggle="tab" href="#aprobados" role="tab">
                            <i class="fas fa-check-circle me-2"></i>Aprobados
                            <span class="badge bg-success ms-1" style="background: var(--success) !important;"><?php echo $total_aprobados; ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="eliminados-tab" data-toggle="tab" href="#eliminados" role="tab">
                            <i class="fas fa-trash-alt me-2"></i>Eliminados
                            <span class="badge bg-danger ms-1" style="background: var(--danger) !important;"><?php echo $total_eliminados; ?></span>
                        </a>
                    </li>
                </ul>
                
                <div class="tab-content" id="pagosTabContent">
                    <!-- TAB PENDIENTES -->
                    <div class="tab-pane fade show active" id="pendientes" role="tabpanel">
                        <!-- Sub-tabs para tipos de servicio con botón de actualizar -->
                        <div class="sub-tabs">
                            <ul class="nav nav-tabs" id="tipoSubTab" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" id="sub-hosting-tab" data-toggle="tab" href="#sub-hosting" role="tab">
                                        <i class="fas fa-server me-1"></i>Hosting
                                        <span class="badge-count" id="badge-hosting-count"><?php echo $total_hosting; ?></span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="sub-dominio-tab" data-toggle="tab" href="#sub-dominio" role="tab">
                                        <i class="fas fa-globe me-1"></i>Dominios
                                        <span class="badge-count" id="badge-dominio-count"><?php echo $total_dominios; ?></span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="sub-manual-tab" data-toggle="tab" href="#sub-manual" role="tab">
                                        <i class="fas fa-tools me-1"></i>Servicios Manuales
                                        <span class="badge-count" id="badge-manual-count"><?php echo $total_manuales; ?></span>
                                    </a>
                                </li>
                            </ul>
                            <button class="btn-refresh-tables" id="btnRefreshTablas" onclick="actualizarTablasPendientes()">
                                <i class="fas fa-sync-alt"></i> Actualizar Pagos
                            </button>
                        </div>
                        
                        <div class="tab-content">
                            <!-- Sub-tab: Hosting Pendientes -->
                            <div class="tab-pane fade show active" id="sub-hosting" role="tabpanel">
                                <!-- Filtros -->
                                <div class="filter-section">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <div class="filter-label">Buscar</div>
                                            <input type="text" id="searchHosting" class="form-control-modern" placeholder="Buscar por cliente, servicio...">
                                        </div>
                                        <div class="col-md-3">
                                            <div class="filter-label">Estado de Vencimiento</div>
                                            <select id="filtroEstadoHosting" class="form-control-modern">
                                                <option value="todos">Todos</option>
                                                <option value="vencidos">Vencidos</option>
                                                <option value="proximos">Próximos a vencer (30 días)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="filter-label">Forma de Pago</div>
                                            <select id="filtroFormaPagoHosting" class="form-control-modern">
                                                <option value="todos">Todos</option>
                                                <option value="1">Tarjeta</option>
                                                <option value="2">Transferencia</option>
                                                <option value="3">Efectivo</option>
                                                <option value="0">Pendiente</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="filter-label">&nbsp;</div>
                                            <button class="btn-modern-filter btn-clear-filter w-100" onclick="limpiarFiltrosHosting()">
                                                <i class="fas fa-eraser me-2"></i>Limpiar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="modern-table" id="contenedorHosting">
                                    <?php if (!empty($hosting_pendientes)): ?>
                                        <table class="table" id="tablaHosting" width="100%">
                                            <thead>
                                                    <th>ID</th>
                                                    <th>Cliente</th>
                                                    <th>Servicio</th>
                                                    <th>Plan</th>
                                                    <th>Fecha de vencimiento</th>
                                                    <th>Fecha de plazo</th>
                                                    <th>Fecha de eliminación</th>
                                                    <th>Monto</th>
                                                    <th>Moneda</th>
                                                    <th>Concepto</th>
                                                    <th>Acciones</th>
                                                </thead>
                                            <tbody>
                                                <?php foreach ($hosting_pendientes as $row): 
                                                    $estatus_vencimiento = '';
                                                    $dias_text = '';
                                                    $hoy_date = new DateTime($hoy);
                                                    $fecha_limite_raw = $row['fecha_limite_efectiva'] ?? '';
                                                    $fecha_limite = !empty($fecha_limite_raw) && $fecha_limite_raw !== '0000-00-00' ? new DateTime($fecha_limite_raw) : null;
                                                    
                                                    if ($fecha_limite) {
                                                        if ($fecha_limite_raw <= $hoy) {
                                                            $estatus_vencimiento = 'vencido';
                                                            $dias_text = '<span class="days-badge critical"><i class="fas fa-exclamation-circle me-1"></i>Alerta de vencimiento</span>';
                                                        } else {
                                                            $dias = $hoy_date->diff($fecha_limite)->days;
                                                            if ($dias <= 30) {
                                                                $estatus_vencimiento = 'proximo';
                                                                $dias_text = '<span class="days-badge critical"><i class="fas fa-hourglass-half me-1"></i>' . $dias . ' días</span>';
                                                            } else {
                                                                $dias_text = '<span class="days-badge normal"><i class="fas fa-calendar-day me-1"></i>' . $dias . ' días</span>';
                                                            }
                                                        }
                                                    } else {
                                                        $dias_text = '<span class="days-badge normal">Sin fecha</span>';
                                                    }
                                                    $fecha_venc_fmt = '';
                                                    $fecha_plazo_fmt = '';
                                                    $fecha_elim_fmt = '';
                                                    $fecha_plazo_raw = '';
                                                    $fecha_elim_raw = '';
                                                    if (!empty($fecha_limite_raw) && $fecha_limite_raw !== '0000-00-00') {
                                                        $fecha_venc_fmt = date('d/m/Y', strtotime($fecha_limite_raw));
                                                        $fecha_plazo_raw = date('Y-m-d', strtotime($fecha_limite_raw . ' +5 days'));
                                                        $fecha_elim_raw = date('Y-m-d', strtotime($fecha_plazo_raw . ' +1 day'));
                                                        $fecha_plazo_fmt = date('d/m/Y', strtotime($fecha_plazo_raw));
                                                        $fecha_elim_fmt = date('d/m/Y', strtotime($fecha_elim_raw));
                                                    }
                                                ?>
                                                <tr data-estado-vencimiento="<?php echo $estatus_vencimiento; ?>" data-forma-pago="<?php echo $row['forma_pago']; ?>" data-monto="<?php echo htmlspecialchars((string) $row['monto'], ENT_QUOTES, 'UTF-8'); ?>" data-currency="<?php echo htmlspecialchars((string) ($row['currency'] ?? 'MXN'), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <td><span class="badge-id">#<?php echo $row["id"]; ?></span></td>
                                                    <td>
                                                        <div class="fw-semibold"><?php echo htmlspecialchars($row["cliente"] ?? ''); ?></div>
                                                        <small class="text-secondary-custom">ID: <?php echo $row["id_clie"]; ?></small>
                                                     </div>
                                                     </td>
                                                     <td>
                                                        <div class="fw-semibold"><?php echo htmlspecialchars($row["nombre_servicio"] ?? $row["producto"] ?? 'N/A'); ?></div>
                                                        <small class="text-secondary-custom">Servicio ID: <?php echo $row["id_servicio"]; ?></small>
                                                     </td>
                                                     <td><?php echo obtenerNombrePlan($conn, $row['producto']); ?></td>
                                                     <td>
                                                        <div class="status-indicator">
                                                            <?php if ($fecha_limite_raw !== '' && $fecha_limite_raw <= $hoy): ?>
                                                                <span class="status-dot vencido"></span>
                                                            <?php elseif ($fecha_limite): ?>
                                                                <span class="status-dot warning"></span>
                                                            <?php else: ?>
                                                                <span class="status-dot"></span>
                                                            <?php endif; ?>
                                                            <?php echo $fecha_venc_fmt !== '' ? $fecha_venc_fmt : '-'; ?>
                                                        </div>
                                                        <?php echo $dias_text; ?>
                                                     </td>
                                                     <td>
                                                        <?php if ($fecha_plazo_fmt !== ''): ?>
                                                            <div class="status-indicator">
                                                                <?php if ($fecha_plazo_raw <= $hoy): ?>
                                                                    <span class="status-dot vencido"></span>
                                                                <?php else: ?>
                                                                    <span class="status-dot warning"></span>
                                                                <?php endif; ?>
                                                                <?php echo htmlspecialchars($fecha_plazo_fmt); ?>
                                                            </div>
                                                            <?php if ($fecha_plazo_raw <= $hoy): ?>
                                                                <span class="days-badge critical"><i class="fas fa-hourglass-end me-1"></i>Alerta de plazo</span>
                                                            <?php elseif ($estatus_vencimiento === 'vencido'): ?>
                                                                <span class="days-badge critical"><i class="fas fa-hourglass-half me-1"></i>Plazo de 5 días</span>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            -
                                                        <?php endif; ?>
                                                     </td>
                                                     <td>
                                                        <?php if ($fecha_elim_fmt !== ''): ?>
                                                            <div class="status-indicator">
                                                                <?php if ($fecha_elim_raw <= $hoy): ?>
                                                                    <span class="status-dot vencido"></span>
                                                                <?php else: ?>
                                                                    <span class="status-dot warning"></span>
                                                                <?php endif; ?>
                                                                <?php echo htmlspecialchars($fecha_elim_fmt); ?>
                                                            </div>
                                                            <?php if ($fecha_elim_raw <= $hoy): ?>
                                                                <span class="days-badge critical"><i class="fas fa-trash-alt me-1"></i>Alerta de eliminación</span>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            -
                                                        <?php endif; ?>
                                                     </td>
                                                    <td class="fw-semibold">$<?php echo number_format($row["monto"], 2); ?></td>
                                                    <td class="fw-medium"><?php echo $row["currency"]; ?></td>
                                                    <td class="concepto-cell" title="<?php echo htmlspecialchars($row["concepto"] ?? ''); ?>"><?php echo htmlspecialchars($row["concepto"] ?? ''); ?></td>
                                                    <td>
                                                        <div class="adm-actions">
                                                            <button type="button" class="adm-act adm-act--success aprobar-btn" data-id="<?php echo $row['id']; ?>" data-servicio="<?php echo $row['id_servicio']; ?>" data-tipo="<?php echo $row['tipo_servicio']; ?>">
                                                                <i class="fas fa-check-circle"></i>Aprobar
                                                            </button>
                                                            <button type="button" class="adm-act adm-act--info reenviar-btn" data-id="<?php echo $row['id']; ?>" data-tipo="<?php echo $row['tipo_servicio']; ?>" data-correo="<?php echo htmlspecialchars($row['correo_cliente'] ?? ''); ?>" data-manual="<?php echo isset($row['manual']) ? $row['manual'] : 0; ?>">
                                                                <i class="fas fa-paper-plane"></i>Reenviar
                                                            </button>
                                                            <button type="button" class="adm-act adm-act--warn alerta-hosting-btn" data-alerta="vencimiento" data-id="<?php echo $row['id']; ?>" data-tipo="<?php echo $row['tipo_servicio']; ?>" data-correo="<?php echo htmlspecialchars($row['correo_cliente'] ?? ''); ?>" title="Enviar correo de alerta de vencimiento">
                                                                <i class="fas fa-calendar-times"></i>Alerta vencimiento
                                                            </button>
                                                            <button type="button" class="adm-act adm-act--warn alerta-hosting-btn" data-alerta="plazo" data-id="<?php echo $row['id']; ?>" data-tipo="<?php echo $row['tipo_servicio']; ?>" data-correo="<?php echo htmlspecialchars($row['correo_cliente'] ?? ''); ?>" title="Enviar correo de alerta de plazo" style="border-color:#ea580c;color:#c2410c;background:#fff7ed;">
                                                                <i class="fas fa-hourglass-end"></i>Alerta plazo
                                                            </button>
                                                            <button type="button" class="adm-act adm-act--danger alerta-hosting-btn" data-alerta="eliminacion" data-id="<?php echo $row['id']; ?>" data-tipo="<?php echo $row['tipo_servicio']; ?>" data-correo="<?php echo htmlspecialchars($row['correo_cliente'] ?? ''); ?>" title="Enviar correo de alerta de eliminación">
                                                                <i class="fas fa-trash-alt"></i>Alerta eliminación
                                                            </button>
                                                            <?php if(!empty($row['telefono_cliente'])): ?>
                                                            <button type="button" class="adm-act adm-act--success whatsapp-pago-btn" 
                                                                data-id="<?php echo $row['id']; ?>"
                                                                data-cliente="<?php echo htmlspecialchars($row['cliente'] ?? ''); ?>">
                                                                <i class="fab fa-whatsapp"></i>WhatsApp
                                                            </button>
                                                            <?php endif; ?>
                                                            <button type="button" class="adm-act adm-act--danger eliminar-btn" data-id="<?php echo $row['id']; ?>">
                                                                <i class="fas fa-trash"></i>Eliminar
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php else: ?>
                                        <div class="text-center py-5">
                                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                            <p class="text-secondary-custom">No se encontraron pagos pendientes de Hosting</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="total-card total-card--pendiente" id="totalHostingPendientes">
                                    <h5><i class="fas fa-calculator me-2"></i>Total pendiente Hosting</h5>
                                    <div id="totalHostingPendientesMontos">
                                        <?php if (!empty($totales_hosting_pendientes)): ?>
                                            <?php foreach ($totales_hosting_pendientes as $moneda => $total): ?>
                                                <div class="total-amount" data-moneda="<?php echo htmlspecialchars($moneda); ?>">
                                                    $<?php echo number_format($total, 2); ?> <?php echo htmlspecialchars($moneda); ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="total-amount">$0.00</div>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted mt-2 d-block">Suma de montos pendientes en esta pestaña</small>
                                </div>
                            </div>
                            
                            <!-- Sub-tab: Dominios Pendientes -->
                            <div class="tab-pane fade" id="sub-dominio" role="tabpanel">
                                <div class="filter-section">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <div class="filter-label">Buscar</div>
                                            <input type="text" id="searchDominio" class="form-control-modern" placeholder="Buscar por cliente, servicio...">
                                        </div>
                                        <div class="col-md-3">
                                            <div class="filter-label">Estado de Vencimiento</div>
                                            <select id="filtroEstadoDominio" class="form-control-modern">
                                                <option value="todos">Todos</option>
                                                <option value="vencidos">Vencidos</option>
                                                <option value="proximos">Próximos a vencer (30 días)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="filter-label">Forma de Pago</div>
                                            <select id="filtroFormaPagoDominio" class="form-control-modern">
                                                <option value="todos">Todos</option>
                                                <option value="1">Tarjeta</option>
                                                <option value="2">Transferencia</option>
                                                <option value="3">Efectivo</option>
                                                <option value="0">Pendiente</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="filter-label">&nbsp;</div>
                                            <button class="btn-modern-filter btn-clear-filter w-100" onclick="limpiarFiltrosDominio()">
                                                <i class="fas fa-eraser me-2"></i>Limpiar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="modern-table" id="contenedorDominio">
                                    <?php if (!empty($dominios_pendientes)): ?>
                                        <table class="table" id="tablaDominio" width="100%">
                                            <thead>
                                                <th>ID</th>
                                                    <th>Cliente</th>
                                                    <th>Dominio</th>
                                                    <th>Fecha Límite</th>
                                                    <th>Monto</th>
                                                    <th>Moneda</th>
                                                    <th>Concepto</th>
                                                    <th>Acciones</th>
                                                </thead>
                                            <tbody>
                                                <?php foreach ($dominios_pendientes as $row): 
                                                    $estatus_vencimiento = '';
                                                    $dias_text = '';
                                                    $hoy_date = new DateTime($hoy);
                                                    $fecha_limite_raw = $row['fecha_limite_efectiva'] ?? '';
                                                    $fecha_limite = !empty($fecha_limite_raw) && $fecha_limite_raw !== '0000-00-00' ? new DateTime($fecha_limite_raw) : null;
                                                    
                                                    if ($fecha_limite) {
                                                        if ($fecha_limite < $hoy_date) {
                                                            $estatus_vencimiento = 'vencido';
                                                            $dias_text = '<span class="days-badge critical"><i class="fas fa-exclamation-circle me-1"></i>Vencido</span>';
                                                        } else {
                                                            $dias = $hoy_date->diff($fecha_limite)->days;
                                                            if ($dias <= 30) {
                                                                $estatus_vencimiento = 'proximo';
                                                                $dias_text = '<span class="days-badge critical"><i class="fas fa-hourglass-half me-1"></i>' . $dias . ' días</span>';
                                                            } else {
                                                                $dias_text = '<span class="days-badge normal"><i class="fas fa-calendar-day me-1"></i>' . $dias . ' días</span>';
                                                            }
                                                        }
                                                    } else {
                                                        $dias_text = '<span class="days-badge normal">Sin fecha</span>';
                                                    }
                                                ?>
                                                <tr data-estado-vencimiento="<?php echo $estatus_vencimiento; ?>" data-forma-pago="<?php echo $row['forma_pago']; ?>" data-monto="<?php echo htmlspecialchars((string) $row['monto'], ENT_QUOTES, 'UTF-8'); ?>" data-currency="<?php echo htmlspecialchars((string) ($row['currency'] ?? 'MXN'), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <td><span class="badge-id">#<?php echo $row["id"]; ?></span></td>
                                                     <td>
                                                        <div class="fw-semibold"><?php echo htmlspecialchars($row["cliente"] ?? ''); ?></div>
                                                        <small class="text-secondary-custom">ID: <?php echo $row["id_clie"]; ?></small>
                                                     </div>
                                                     </td>
                                                     <td>
                                                        <div class="fw-semibold"><?php echo htmlspecialchars($row["nombre_servicio"] ?? ''); ?></div>
                                                        <small class="text-secondary-custom">Servicio ID: <?php echo $row["id_servicio"]; ?></small>
                                                     </td>
                                                     <td>
                                                        <div class="status-indicator">
                                                            <?php if ($fecha_limite && $fecha_limite < $hoy_date): ?>
                                                                <span class="status-dot vencido"></span>
                                                            <?php elseif ($fecha_limite): ?>
                                                                <span class="status-dot warning"></span>
                                                            <?php else: ?>
                                                                <span class="status-dot"></span>
                                                            <?php endif; ?>
                                                            <?php echo !empty($fecha_limite_raw) ? date("d/m/Y", strtotime($fecha_limite_raw)) : "-"; ?>
                                                        </div>
                                                        <?php echo $dias_text; ?>
                                                     </td>
                                                    <td class="fw-semibold">$<?php echo number_format($row["monto"], 2); ?></td>
                                                    <td class="fw-medium"><?php echo $row["currency"]; ?></td>
                                                    <td class="concepto-cell" title="<?php echo htmlspecialchars($row["concepto"] ?? ''); ?>"><?php echo htmlspecialchars($row["concepto"] ?? ''); ?></td>
                                                    <td>
                                                        <div class="adm-actions">
                                                            <button type="button" class="adm-act adm-act--success aprobar-btn" data-id="<?php echo $row['id']; ?>" data-servicio="<?php echo $row['id_servicio']; ?>" data-tipo="<?php echo $row['tipo_servicio']; ?>">
                                                                <i class="fas fa-check-circle"></i>Aprobar
                                                            </button>
                                                            <button type="button" class="adm-act adm-act--info reenviar-btn" data-id="<?php echo $row['id']; ?>" data-tipo="<?php echo $row['tipo_servicio']; ?>" data-correo="<?php echo htmlspecialchars($row['correo_cliente'] ?? ''); ?>" data-manual="<?php echo isset($row['manual']) ? $row['manual'] : 0; ?>">
                                                                <i class="fas fa-paper-plane"></i>Reenviar
                                                            </button>
                                                            <?php if ($estatus_vencimiento === 'vencido'): ?>
                                                            <button type="button" class="adm-act adm-act--warn aviso-vencido-btn" data-id="<?php echo $row['id']; ?>" data-tipo="<?php echo $row['tipo_servicio']; ?>" data-correo="<?php echo htmlspecialchars($row['correo_cliente'] ?? ''); ?>" title="Aviso: dominio en riesgo de pérdida">
                                                                <i class="fas fa-exclamation-triangle"></i>Aviso vencido
                                                            </button>
                                                            <?php endif; ?>
                                                            <?php if(!empty($row['telefono_cliente'])): ?>
                                                            <button type="button" class="adm-act adm-act--success whatsapp-pago-btn" 
                                                                data-id="<?php echo $row['id']; ?>"
                                                                data-cliente="<?php echo htmlspecialchars($row['cliente'] ?? ''); ?>">
                                                                <i class="fab fa-whatsapp"></i>WhatsApp
                                                            </button>
                                                            <?php endif; ?>
                                                            <button type="button" class="adm-act adm-act--danger eliminar-btn" data-id="<?php echo $row['id']; ?>">
                                                                <i class="fas fa-trash"></i>Eliminar
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php else: ?>
                                        <div class="text-center py-5">
                                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                            <p class="text-secondary-custom">No se encontraron pagos pendientes de Dominios</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="total-card total-card--pendiente" id="totalDominioPendientes">
                                    <h5><i class="fas fa-calculator me-2"></i>Total pendiente Dominios</h5>
                                    <div id="totalDominioPendientesMontos">
                                        <?php if (!empty($totales_dominios_pendientes)): ?>
                                            <?php foreach ($totales_dominios_pendientes as $moneda => $total): ?>
                                                <div class="total-amount" data-moneda="<?php echo htmlspecialchars($moneda); ?>">
                                                    $<?php echo number_format($total, 2); ?> <?php echo htmlspecialchars($moneda); ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="total-amount">$0.00</div>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted mt-2 d-block">Suma de montos pendientes en esta pestaña</small>
                                </div>
                            </div>
                            
                            <!-- Sub-tab: Servicios Manuales Pendientes -->
                            <div class="tab-pane fade" id="sub-manual" role="tabpanel">
                                <div class="filter-section">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <div class="filter-label">Buscar</div>
                                            <input type="text" id="searchManual" class="form-control-modern" placeholder="Buscar por cliente, concepto...">
                                        </div>
                                        <div class="col-md-3">
                                            <div class="filter-label">Estado de Vencimiento</div>
                                            <select id="filtroEstadoManual" class="form-control-modern">
                                                <option value="todos">Todos</option>
                                                <option value="vencidos">Vencidos</option>
                                                <option value="proximos">Próximos a vencer (30 días)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="filter-label">Forma de Pago</div>
                                            <select id="filtroFormaPagoManual" class="form-control-modern">
                                                <option value="todos">Todos</option>
                                                <option value="1">Tarjeta</option>
                                                <option value="2">Transferencia</option>
                                                <option value="3">Efectivo</option>
                                                <option value="0">Pendiente</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="filter-label">&nbsp;</div>
                                            <button class="btn-modern-filter btn-clear-filter w-100" onclick="limpiarFiltrosManual()">
                                                <i class="fas fa-eraser me-2"></i>Limpiar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="modern-table" id="contenedorManual">
                                    <?php if (!empty($manuales_pendientes)): ?>
                                        <table class="table" id="tablaManual" width="100%">
                                            <thead>
                                                    <th>ID</th>
                                                    <th>Cliente</th>
                                                    <th>Concepto</th>
                                                    <th>Fecha Límite</th>
                                                    <th>Forma Pago</th>
                                                    <th>Monto</th>
                                                    <th>Moneda</th>
                                                    <th>Acciones</th>
                                                </thead>
                                            <tbody>
                                                <?php foreach ($manuales_pendientes as $row): 
                                                    $estatus_vencimiento = '';
                                                    $dias_text = '';
                                                    $hoy_date = new DateTime($hoy);
                                                    $fecha_limite_raw = $row['fecha_limite_efectiva'] ?? '';
                                                    $fecha_limite = !empty($fecha_limite_raw) && $fecha_limite_raw !== '0000-00-00' ? new DateTime($fecha_limite_raw) : null;
                                                    
                                                    if ($fecha_limite) {
                                                        if ($fecha_limite < $hoy_date) {
                                                            $estatus_vencimiento = 'vencido';
                                                            $dias_text = '<span class="days-badge critical"><i class="fas fa-exclamation-circle me-1"></i>Vencido</span>';
                                                        } else {
                                                            $dias = $hoy_date->diff($fecha_limite)->days;
                                                            if ($dias <= 30) {
                                                                $estatus_vencimiento = 'proximo';
                                                                $dias_text = '<span class="days-badge critical"><i class="fas fa-hourglass-half me-1"></i>' . $dias . ' días</span>';
                                                            } else {
                                                                $dias_text = '<span class="days-badge normal"><i class="fas fa-calendar-day me-1"></i>' . $dias . ' días</span>';
                                                            }
                                                        }
                                                    } else {
                                                        $dias_text = '<span class="days-badge normal">Sin fecha</span>';
                                                    }
                                                ?>
                                                <tr data-estado-vencimiento="<?php echo $estatus_vencimiento; ?>" data-forma-pago="<?php echo $row['forma_pago']; ?>" data-monto="<?php echo htmlspecialchars((string) $row['monto'], ENT_QUOTES, 'UTF-8'); ?>" data-currency="<?php echo htmlspecialchars((string) ($row['currency'] ?? 'MXN'), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <td><span class="badge-id">#<?php echo $row["id"]; ?></span></td>
                                                     <td>
                                                        <div class="fw-semibold"><?php echo htmlspecialchars($row["cliente"] ?? ''); ?></div>
                                                        <small class="text-secondary-custom">ID: <?php echo $row["id_clie"]; ?></small>
                                                     </div>
                                                     </td>
                                                    <td class="concepto-cell" title="<?php echo htmlspecialchars($row["concepto"] ?? ''); ?>"><?php echo htmlspecialchars($row["concepto"] ?? ''); ?></td>
                                                    <td>
                                                        <div class="status-indicator">
                                                            <?php if ($fecha_limite && $fecha_limite < $hoy_date): ?>
                                                                <span class="status-dot vencido"></span>
                                                            <?php elseif ($fecha_limite): ?>
                                                                <span class="status-dot warning"></span>
                                                            <?php else: ?>
                                                                <span class="status-dot"></span>
                                                            <?php endif; ?>
                                                            <?php echo !empty($fecha_limite_raw) ? date("d/m/Y", strtotime($fecha_limite_raw)) : "-"; ?>
                                                        </div>
                                                        <?php echo $dias_text; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge-status <?php echo $row['forma_pago'] == 1 ? 'badge-aprobado' : ($row['forma_pago'] == 2 ? 'badge-info' : ($row['forma_pago'] == 3 ? 'badge-warning' : 'badge-pendiente')); ?>">
                                                            <?php echo $row["forma_pago"] == 1 ? "Tarjeta" : ($row["forma_pago"] == 2 ? "Transferencia" : ($row["forma_pago"] == 3 ? "Efectivo" : "Pendiente")); ?>
                                                        </span>
                                                    </td>
                                                    <td class="fw-semibold">$<?php echo number_format($row["monto"], 2); ?></td>
                                                    <td class="fw-medium"><?php echo $row["currency"]; ?></td>
                                                    <td>
                                                        <div class="adm-actions">
                                                            <button type="button" class="adm-act adm-act--success aprobar-btn" data-id="<?php echo $row['id']; ?>" data-servicio="<?php echo $row['id_servicio']; ?>" data-tipo="<?php echo $row['tipo_servicio']; ?>">
                                                                <i class="fas fa-check-circle"></i>Aprobar
                                                            </button>
                                                            <button type="button" class="adm-act adm-act--info reenviar-btn" data-id="<?php echo $row['id']; ?>" data-tipo="<?php echo $row['tipo_servicio']; ?>" data-correo="<?php echo htmlspecialchars($row['correo_cliente'] ?? ''); ?>" data-manual="<?php echo isset($row['manual']) ? $row['manual'] : 1; ?>">
                                                                <i class="fas fa-paper-plane"></i>Reenviar
                                                            </button>
                                                            <?php if ($estatus_vencimiento === 'vencido'): ?>
                                                            <button type="button" class="adm-act adm-act--warn aviso-vencido-btn" data-id="<?php echo $row['id']; ?>" data-tipo="<?php echo $row['tipo_servicio']; ?>" data-correo="<?php echo htmlspecialchars($row['correo_cliente'] ?? ''); ?>" title="Aviso de servicio vencido">
                                                                <i class="fas fa-exclamation-triangle"></i>Aviso vencido
                                                            </button>
                                                            <?php endif; ?>
                                                            <?php if(!empty($row['telefono_cliente'])): ?>
                                                            <button type="button" class="adm-act adm-act--success whatsapp-pago-btn" 
                                                                data-id="<?php echo $row['id']; ?>"
                                                                data-cliente="<?php echo htmlspecialchars($row['cliente'] ?? ''); ?>">
                                                                <i class="fab fa-whatsapp"></i>WhatsApp
                                                            </button>
                                                            <?php endif; ?>
                                                            <button type="button" class="adm-act adm-act--danger eliminar-btn" data-id="<?php echo $row['id']; ?>">
                                                                <i class="fas fa-trash"></i>Eliminar
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php else: ?>
                                        <div class="text-center py-5">
                                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                            <p class="text-secondary-custom">No se encontraron pagos pendientes de Servicios Manuales</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="total-card total-card--pendiente" id="totalManualPendientes">
                                    <h5><i class="fas fa-calculator me-2"></i>Total pendiente Manuales</h5>
                                    <div id="totalManualPendientesMontos">
                                        <?php if (!empty($totales_manuales_pendientes)): ?>
                                            <?php foreach ($totales_manuales_pendientes as $moneda => $total): ?>
                                                <div class="total-amount" data-moneda="<?php echo htmlspecialchars($moneda); ?>">
                                                    $<?php echo number_format($total, 2); ?> <?php echo htmlspecialchars($moneda); ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="total-amount">$0.00</div>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted mt-2 d-block">Suma de montos pendientes en esta pestaña</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- TAB APROBADOS -->
                    <div class="tab-pane fade" id="aprobados" role="tabpanel">
                        <div class="filter-section">
                            <div class="filter-row">
                                <div class="filter-item">
                                    <div class="filter-label">
                                        <i class="fas fa-calendar-alt me-1"></i>Año
                                    </div>
                                    <select name="filtro_anio_ajax" class="form-control-modern" id="filtro_anio_ajax">
                                        <option value="">Todos los años</option>
                                        <?php foreach ($anios_disponibles as $anio_data): ?>
                                            <option value="<?php echo $anio_data['anio']; ?>" <?php echo $filtro_anio == $anio_data['anio'] ? 'selected' : ''; ?>>
                                                <?php echo $anio_data['anio']; ?> 
                                                <span class="select-badge">(<?php echo $anio_data['total_pagos']; ?> pagos)</span>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="filter-item">
                                    <div class="filter-label">
                                        <i class="fas fa-calendar-week me-1"></i>Mes
                                    </div>
                                    <select name="filtro_mes_ajax" class="form-control-modern" id="filtro_mes_ajax">
                                        <option value="">Todos los meses</option>
                                        <?php if (!empty($meses_disponibles)): ?>
                                            <?php foreach ($meses_disponibles as $mes_data): ?>
                                                <option value="<?php echo $mes_data['mes_numero']; ?>" <?php echo $filtro_mes == $mes_data['mes_numero'] ? 'selected' : ''; ?>>
                                                    <?php echo $nombres_meses[$mes_data['mes_numero']]; ?>
                                                    <span class="select-badge">(<?php echo $mes_data['total_pagos']; ?> pagos)</span>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                
                                <div class="filter-item">
                                    <div class="filter-label">
                                        <i class="fas fa-calendar-plus me-1"></i>Fecha Inicio
                                    </div>
                                    <input type="date" class="form-control-modern" id="fecha_inicio_ajax" value="<?php echo htmlspecialchars($fecha_inicio); ?>">
                                </div>
                                
                                <div class="filter-item">
                                    <div class="filter-label">
                                        <i class="fas fa-calendar-minus me-1"></i>Fecha Fin
                                    </div>
                                    <input type="date" class="form-control-modern" id="fecha_fin_ajax" value="<?php echo htmlspecialchars($fecha_fin); ?>">
                                </div>
                            </div>
                            
                            <div class="filter-actions">
                                <button type="button" class="btn-modern-filter btn-filter" id="btnFiltrarAprobados">
                                    <i class="fas fa-search me-2"></i>Filtrar
                                </button>
                                <button type="button" class="btn-modern-filter btn-clear-filter" id="btnLimpiarFiltrosAprobados">
                                    <i class="fas fa-eraser me-2"></i>Limpiar
                                </button>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <div class="filter-label">
                                        <i class="fas fa-search me-1"></i>Búsqueda rápida
                                    </div>
                                    <input type="text" id="searchAprobados" class="form-control-modern" placeholder="Buscar por cliente, servicio, concepto...">
                                </div>
                            </div>
                        </div>
                        
                        <div class="modern-table" id="contenedorAprobados">
                            <?php if (!empty($aprobados)): ?>
                                <table class="table" id="tablaAprobados" width="100%">
                                    <thead>
                                            <th>ID</th>
                                            <th>Cliente</th>
                                            <th>Tipo</th>
                                            <th>Servicio</th>
                                            <th>Fecha Pago</th>
                                            <th>Forma Pago</th>
                                            <th>Monto</th>
                                            <th>Moneda</th>
                                            <th>Concepto</th>
                                            <th>Acciones</th>
                                        </thead>
                                    <tbody>
                                        <?php foreach ($aprobados as $row): ?>
                                        <tr>
                                            <td><span class="badge-id">#<?php echo $row["id"]; ?></span></td>
                                             <td>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($row["cliente"] ?? ''); ?></div>
                                                <small class="text-secondary-custom">ID: <?php echo $row["id_clie"]; ?></small>
                                             </div>
                                             </td>
                                             <td>
                                                <span class="badge-status <?php echo $row['tipo_servicio'] == 1 ? 'badge-hosting' : ($row['tipo_servicio'] == 2 ? 'badge-dominio' : 'badge-manual'); ?>">
                                                    <?php echo $row['tipo_servicio'] == 1 ? 'Hosting' : ($row['tipo_servicio'] == 2 ? 'Dominio' : 'Servicio'); ?>
                                                </span>
                                             </td>
                                             <td>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($row["nombre_servicio"] ?? $row["producto"] ?? 'N/A'); ?></div>
                                                <small class="text-secondary-custom">Servicio ID: <?php echo $row["id_servicio"]; ?></small>
                                             </td>
                                             <td><?php echo !empty($row["fecha_pago"]) && $row["fecha_pago"] !== "0000-00-00" ? date("d/m/Y", strtotime($row["fecha_pago"])) : "-"; ?></td>
                                             <td>
                                                <span class="badge-status badge-aprobado">
                                                    <?php echo $row["forma_pago"] == 1 ? "Tarjeta" : ($row["forma_pago"] == 2 ? "Transferencia" : ($row["forma_pago"] == 3 ? "Efectivo" : "Pendiente")); ?>
                                                </span>
                                             </td>
                                            <td class="fw-semibold">$<?php echo number_format($row["monto"], 2); ?></td>
                                            <td class="fw-medium"><?php echo $row["currency"]; ?></td>
                                            <td class="concepto-cell" title="<?php echo htmlspecialchars($row["concepto"] ?? ''); ?>"><?php echo htmlspecialchars($row["concepto"] ?? ''); ?></td>
                                            <td>
                                                <div class="adm-actions">
                                                    <button type="button" class="adm-act adm-act--view ver-reporte-btn" data-id="<?php echo $row['id']; ?>">
                                                        <i class="fas fa-eye"></i>Ver
                                                    </button>
                                                    <button type="button" class="adm-act adm-act--info generar-pdf-btn" data-id="<?php echo $row['id']; ?>">
                                                        <i class="fas fa-download"></i>PDF
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-secondary-custom">No se encontraron pagos aprobados</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="total-card" id="totalAprobadosContainer" style="<?php echo $mostrarTotalAprobados ? '' : 'display: none;'; ?>">
                            <h5><i class="fas fa-chart-line me-2"></i>Total de Pagos Aprobados</h5>
                            <?php foreach ($monedas_aprobadas as $moneda => $total): ?>
                                <div class="total-amount" data-moneda="<?php echo htmlspecialchars($moneda); ?>">
                                    <?php echo number_format($total, 2); ?> <?php echo htmlspecialchars($moneda); ?>
                                </div>
                            <?php endforeach; ?>
                            <small class="text-muted mt-2 d-block" id="totalAprobadosNota">
                                * Solo se incluyen los pagos con estatus aprobado
                            </small>
                        </div>
                    </div>
                    
                    <!-- TAB ELIMINADOS -->
                    <div class="tab-pane fade" id="eliminados" role="tabpanel">
                        <div class="filter-section">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="filter-label">Buscar</div>
                                    <input type="text" id="searchEliminados" class="form-control-modern" placeholder="Buscar por cliente, servicio...">
                                </div>
                                <div class="col-md-3">
                                    <div class="filter-label">Tipo de Servicio</div>
                                    <select id="filtroTipoEliminados" class="form-control-modern">
                                        <option value="todos">Todos</option>
                                        <option value="1">Hosting</option>
                                        <option value="2">Dominio</option>
                                        <option value="manual">Servicio Manual</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <div class="filter-label">&nbsp;</div>
                                    <button class="btn-modern-filter btn-clear-filter w-100" onclick="limpiarFiltrosEliminados()">
                                        <i class="fas fa-eraser me-2"></i>Limpiar
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="modern-table" id="contenedorEliminados">
                            <?php if (!empty($eliminados)): ?>
                                <div class="alert-modern alert-warning mb-4">
                                    <i class="fas fa-exclamation-triangle me-2"></i>Esta sección muestra los pagos que han sido marcados como eliminados.
                                </div>
                                <table class="table" id="tablaEliminados" width="100%">
                                    <thead>
                                            <th>ID</th>
                                            <th>Cliente</th>
                                            <th>Tipo</th>
                                            <th>Servicio</th>
                                            <th>Fecha Creación</th>
                                            <th>Fecha Pago</th>
                                            <th>Forma Pago</th>
                                            <th>Monto</th>
                                            <th>Moneda</th>
                                            <th>Concepto</th>
                                            <th>Acciones</th>
                                        </thead>
                                    <tbody>
                                        <?php foreach ($eliminados as $row): ?>
                                        <tr data-tipo-servicio="<?php echo $row['tipo_servicio'] == 1 ? '1' : ($row['tipo_servicio'] == 2 ? '2' : 'manual'); ?>">
                                            <td><span class="badge-id">#<?php echo $row["id"]; ?></span></td>
                                              <td>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($row["cliente"] ?? ''); ?></div>
                                                <small class="text-secondary-custom">ID: <?php echo $row["id_clie"]; ?></small>
                                              </div>
                                              </td>
                                              <td>
                                                <span class="badge-status <?php echo $row['tipo_servicio'] == 1 ? 'badge-hosting' : ($row['tipo_servicio'] == 2 ? 'badge-dominio' : 'badge-manual'); ?>">
                                                    <?php echo $row['tipo_servicio'] == 1 ? 'Hosting' : ($row['tipo_servicio'] == 2 ? 'Dominio' : 'Servicio'); ?>
                                                </span>
                                              </td>
                                              <td>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($row["nombre_servicio"] ?? $row["producto"] ?? 'N/A'); ?></div>
                                                <small class="text-secondary-custom">Servicio ID: <?php echo $row["id_servicio"]; ?></small>
                                              </td>
                                              <td><?php echo !empty($row["fecha"]) ? date("d/m/Y", strtotime($row["fecha"])) : "-"; ?></td>
                                              <td>
                                                <?php if (!empty($row["fecha_pago"]) && $row["fecha_pago"] !== "0000-00-00"): ?>
                                                    <?php echo date("d/m/Y", strtotime($row["fecha_pago"])); ?>
                                                <?php else: ?>
                                                    Sin pagar
                                                <?php endif; ?>
                                              </td>
                                              <td>
                                                <span class="badge-status <?php echo $row["estatus"] == 1 ? 'badge-aprobado' : 'badge-pendiente'; ?>">
                                                    <?php echo $row["forma_pago"] == 1 ? "Tarjeta" : ($row["forma_pago"] == 2 ? "Transferencia" : ($row["forma_pago"] == 3 ? "Efectivo" : "Pendiente")); ?>
                                                </span>
                                              </td>
                                            <td class="fw-semibold">$<?php echo number_format($row["monto"], 2); ?></td>
                                            <td class="fw-medium"><?php echo $row["currency"]; ?></td>
                                            <td class="concepto-cell" title="<?php echo htmlspecialchars($row["concepto"] ?? ''); ?>"><?php echo htmlspecialchars($row["concepto"] ?? ''); ?></td>
                                            <td>
                                                <div class="adm-actions">
                                                    <?php if (isset($row["estatus"]) && $row["estatus"] == 1): ?>
                                                        <button type="button" class="adm-act adm-act--view ver-reporte-btn" data-id="<?php echo $row['id']; ?>">
                                                            <i class="fas fa-eye"></i>Ver
                                                        </button>
                                                        <button type="button" class="adm-act adm-act--info generar-pdf-btn" data-id="<?php echo $row['id']; ?>">
                                                            <i class="fas fa-download"></i>PDF
                                                        </button>
                                                    <?php endif; ?>
                                                    <button type="button" class="adm-act adm-act--warn restaurar-btn" data-id="<?php echo $row['id']; ?>">
                                                        <i class="fas fa-undo"></i>Restaurar
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-secondary-custom">No hay pagos eliminados</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Plantilla residual (el PDF real se genera en servidor; se mantiene alineada al portal cliente) -->
    <div id="nota-pago-template" style="display: none;">
        <div class="nota-pago" style="width:100%;max-width:800px;margin:0 auto;padding:36px 40px;font-family:Montserrat,Arial,sans-serif;background:#fff;color:#0f172a;box-sizing:border-box;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:24px;margin-bottom:28px;padding-bottom:24px;border-bottom:3px solid #000147;">
                <div>
                    <img src="images/logo-conline.png" alt="ConlineWeb" style="width:150px;height:auto;margin-bottom:12px;display:block;" onerror="this.style.display='none'">
                    <p style="margin:2px 0;font-size:13px;color:#64748b;"><strong style="color:#000147;">ConlineWeb</strong></p>
                    <p style="margin:2px 0;font-size:13px;color:#64748b;">info@conlineweb.com</p>
                    <p style="margin:2px 0;font-size:13px;color:#64748b;">+52 477 118 1285</p>
                </div>
                <div style="text-align:right;">
                    <h1 style="margin:0;color:#000147;font-size:26px;font-weight:800;letter-spacing:.02em;">COMPROBANTE DE PAGO</h1>
                    <div style="margin-top:12px;font-size:13px;color:#475569;line-height:1.6;">
                        <div><strong style="color:#0f172a;">Referencia:</strong> <span id="numero-pago"></span></div>
                        <div><strong style="color:#0f172a;">Fecha de emisión:</strong> <span id="fecha-emision"></span></div>
                        <div><strong style="color:#0f172a;">Fecha de pago:</strong> <span id="fecha-pago-encabezado">-</span></div>
                    </div>
                </div>
            </div>
            <div style="margin-bottom:24px;">
                <h3 style="font-size:14px;font-weight:700;color:#000147;text-transform:uppercase;letter-spacing:.06em;margin:0 0 12px;padding-bottom:8px;border-bottom:1px solid #e2e8f0;">Información del cliente</h3>
                <table style="width:100%;border-collapse:collapse;">
                    <tr><td style="padding:8px 0;width:32%;font-weight:600;color:#64748b;font-size:14px;">Nombre</td><td style="padding:8px 0;font-size:14px;" id="cliente-nombre">-</td></tr>
                    <tr><td style="padding:8px 0;font-weight:600;color:#64748b;font-size:14px;">Correo</td><td style="padding:8px 0;font-size:14px;" id="cliente-correo">-</td></tr>
                </table>
            </div>
            <div style="margin-bottom:24px;">
                <h3 style="font-size:14px;font-weight:700;color:#000147;text-transform:uppercase;letter-spacing:.06em;margin:0 0 12px;padding-bottom:8px;border-bottom:1px solid #e2e8f0;">Detalle del pago</h3>
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr>
                            <th style="padding:12px 14px;text-align:left;background:#000147;color:#fff;font-size:12px;font-weight:600;text-transform:uppercase;">Descripción</th>
                            <th style="padding:12px 14px;text-align:center;background:#000147;color:#fff;font-size:12px;font-weight:600;text-transform:uppercase;">Cant.</th>
                            <th style="padding:12px 14px;text-align:right;background:#000147;color:#fff;font-size:12px;font-weight:600;text-transform:uppercase;">Precio unitario</th>
                            <th style="padding:12px 14px;text-align:right;background:#000147;color:#fff;font-size:12px;font-weight:600;text-transform:uppercase;">Total</th>
                        </tr>
                    </thead>
                    <tbody id="detalle-servicio"></tbody>
                </table>
            </div>
            <div style="display:flex;justify-content:flex-end;margin:20px 0 28px;">
                <div style="width:280px;">
                    <table style="width:100%;border-collapse:collapse;">
                        <tr><td style="padding:8px 0;text-align:right;padding-right:16px;color:#64748b;font-size:14px;">Subtotal</td><td style="padding:8px 0;text-align:right;font-weight:600;width:120px;" id="subtotal">$0.00</td></tr>
                        <tr><td style="padding-top:14px;border-top:2px solid #000147;text-align:right;padding-right:16px;font-size:16px;font-weight:800;color:#000147;">Total</td><td style="padding-top:14px;border-top:2px solid #000147;text-align:right;font-size:16px;font-weight:800;color:#000147;" id="total">$0.00</td></tr>
                    </table>
                </div>
            </div>
            <div style="margin-bottom:24px;">
                <h3 style="font-size:14px;font-weight:700;color:#000147;text-transform:uppercase;letter-spacing:.06em;margin:0 0 12px;padding-bottom:8px;border-bottom:1px solid #e2e8f0;">Información adicional</h3>
                <table style="width:100%;border-collapse:collapse;">
                    <tr><td style="padding:8px 0;width:32%;font-weight:600;color:#64748b;font-size:14px;">Método de pago</td><td style="padding:8px 0;font-size:14px;" id="forma-pago">-</td></tr>
                    <tr><td style="padding:8px 0;font-weight:600;color:#64748b;font-size:14px;">Fecha de pago</td><td style="padding:8px 0;font-size:14px;" id="fecha-pago">-</td></tr>
                    <tr><td style="padding:8px 0;font-weight:600;color:#64748b;font-size:14px;">Estado</td><td style="padding:8px 0;font-size:14px;" id="estatus-pago">-</td></tr>
                </table>
            </div>
            <div style="margin-top:32px;padding:16px 18px;background:#f8fafc;border-radius:10px;border:1px solid #e2e8f0;font-size:12px;color:#64748b;text-align:center;line-height:1.5;">
                <strong>Confirmación:</strong> Este comprobante acredita el pago del servicio mencionado.
                Vigencia del servicio hasta: <span id="fecha-expiracion"></span>.
            </div>
            <p style="margin-top:20px;text-align:center;font-size:11px;color:#94a3b8;font-style:italic;">Documento informativo. No constituye comprobante fiscal.</p>
        </div>
    </div>

    <!-- Modal vista previa correo de pago -->
    <div class="modal fade" id="modalPreviewCorreoPago" tabindex="-1" role="dialog" aria-labelledby="modalPreviewCorreoPagoTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document" style="max-width:960px;">
            <div class="modal-content" style="border:0;border-radius:14px;overflow:hidden;">
                <div class="modal-header" style="background:#0f172a;color:#fff;border:0;">
                    <div>
                        <h5 class="modal-title mb-1" id="modalPreviewCorreoPagoTitle">Vista previa del correo</h5>
                        <small class="d-block" style="opacity:.8;" id="previewCorreoMeta">—</small>
                    </div>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar" style="opacity:.9;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-0" style="background:#e8edf2;">
                    <div id="previewCorreoInfo" class="px-3 py-2" style="background:#fff;border-bottom:1px solid rgba(15,23,42,.08);font-size:.85rem;">
                        <div><strong>Para:</strong> <span id="previewCorreoTo">—</span></div>
                        <div><strong>Asunto:</strong> <span id="previewCorreoAsunto">—</span></div>
                        <div class="text-muted mt-1" id="previewCorreoNota" style="font-size:.78rem;"></div>
                    </div>
                    <div id="previewCorreoLoading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2 mb-0 text-muted">Generando vista previa…</p>
                    </div>
                    <iframe id="previewCorreoFrame" title="Vista previa del correo" style="display:none;width:100%;height:68vh;min-height:480px;border:0;background:#fff;"></iframe>
                    <div id="previewCorreoError" class="alert alert-danger m-3" style="display:none;"></div>
                </div>
                <div class="modal-footer" style="background:#fff;border-top:1px solid rgba(15,23,42,.08);">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnConfirmarEnvioCorreo" disabled>
                        <i class="fas fa-paper-plane mr-1"></i> Confirmar y enviar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="js/sb-admin-2.min.js"></script>
    <script src="vendor/datatables/jquery.dataTables.min.js"></script>
    <script src="vendor/datatables/dataTables.bootstrap4.min.js"></script>
    
    <script>
        const pagos = <?php echo json_encode(array_merge($pagos_pendientes, $aprobados)); ?>;
        const sistemaActivo = '<?php echo $sistema; ?>';
        
        function formatCurrency(amount, currency) {
            return new Intl.NumberFormat('es-MX', {
                style: 'currency',
                currency: currency || 'MXN',
                minimumFractionDigits: 2
            }).format(amount);
        }
        
        // Mostrar loading overlay
        function showLoading() {
            $('#loadingOverlay').css('display', 'flex');
        }
        
        function hideLoading() {
            $('#loadingOverlay').css('display', 'none');
        }
        
        function renderTotalesPendientesHtml(totales) {
            const entries = Object.keys(totales || {});
            if (!entries.length) {
                return '<div class="total-amount">$0.00</div>';
            }
            return entries.map(function (moneda) {
                const n = parseFloat(totales[moneda]) || 0;
                const txt = (typeof formatNumber === 'function')
                    ? formatNumber(n)
                    : n.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                return '<div class="total-amount" data-moneda="' + moneda + '">$' + txt + ' ' + moneda + '</div>';
            }).join('');
        }

        function pintarTotalesPendientes(selector, totales) {
            const el = document.querySelector(selector);
            if (!el) return;
            el.innerHTML = renderTotalesPendientesHtml(totales || {});
        }

        function recalcularTotalesPendientesVisibles(tablaSel, montosSel) {
            const sums = {};
            $(tablaSel + ' tbody tr').each(function () {
                // No usar :visible: en pestañas Bootstrap ocultas todas las filas
                // dan false y el total queda en $0. Solo excluir display:none del filtro.
                if (this.style.display === 'none') {
                    return;
                }
                const monto = parseFloat($(this).attr('data-monto') || '0') || 0;
                let moneda = String($(this).attr('data-currency') || 'MXN').trim();
                if (!moneda) moneda = 'MXN';
                sums[moneda] = (sums[moneda] || 0) + monto;
            });
            pintarTotalesPendientes(montosSel, sums);
        }

        function sincronizarTotalesPendientesDesdeResponse(response) {
            if (!response) return;
            if (response.hosting) pintarTotalesPendientes('#totalHostingPendientesMontos', response.hosting.totales || {});
            if (response.dominios) pintarTotalesPendientes('#totalDominioPendientesMontos', response.dominios.totales || {});
            if (response.manuales) pintarTotalesPendientes('#totalManualPendientesMontos', response.manuales.totales || {});
        }

        // Función para actualizar solo las tablas pendientes vía AJAX
        function actualizarTablasPendientes() {
            showLoading();
            
            $.ajax({
                url: 'ajax_actualizar_tablas_pendientes.php',
                type: 'POST',
                data: { sistema: sistemaActivo },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Actualizar tabla de Hosting
                        if (response.hosting.empty) {
                            $('#contenedorHosting').html(`
                                <div class="text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-secondary-custom">No se encontraron pagos pendientes de Hosting</p>
                                </div>
                            `);
                            $('#badge-hosting-count').text('0');
                        } else {
                            $('#contenedorHosting').html(`
                                <table class="table" id="tablaHosting" width="100%">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Cliente</th>
                                            <th>Servicio</th>
                                            <th>Plan</th>
                                            <th>Fecha de vencimiento</th>
                                            <th>Fecha de plazo</th>
                                            <th>Fecha de eliminación</th>
                                            <th>Monto</th>
                                            <th>Moneda</th>
                                            <th>Concepto</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${response.hosting.html}
                                    </tbody>
                                </table>
                            `);
                            // Actualizar badge de conteo
                            const hostingCount = $('#tablaHosting tbody tr').length;
                            $('#badge-hosting-count').text(hostingCount);
                        }
                        
                        // Actualizar tabla de Dominios
                        if (response.dominios.empty) {
                            $('#contenedorDominio').html(`
                                <div class="text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-secondary-custom">No se encontraron pagos pendientes de Dominios</p>
                                </div>
                            `);
                            $('#badge-dominio-count').text('0');
                        } else {
                            $('#contenedorDominio').html(`
                                <table class="table" id="tablaDominio" width="100%">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Cliente</th>
                                            <th>Dominio</th>
                                            <th>Fecha Límite</th>
                                            <th>Monto</th>
                                            <th>Moneda</th>
                                            <th>Concepto</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${response.dominios.html}
                                    </tbody>
                                </table>
                            `);
                            const dominiosCount = $('#tablaDominio tbody tr').length;
                            $('#badge-dominio-count').text(dominiosCount);
                        }
                        
                        // Actualizar tabla de Manuales
                        if (response.manuales.empty) {
                            $('#contenedorManual').html(`
                                <div class="text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-secondary-custom">No se encontraron pagos pendientes de Servicios Manuales</p>
                                </div>
                            `);
                            $('#badge-manual-count').text('0');
                        } else {
                            $('#contenedorManual').html(`
                                <table class="table" id="tablaManual" width="100%">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Cliente</th>
                                            <th>Concepto</th>
                                            <th>Fecha Límite</th>
                                            <th>Forma Pago</th>
                                            <th>Monto</th>
                                            <th>Moneda</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${response.manuales.html}
                                    </tbody>
                                </table>
                            `);
                            const manualesCount = $('#tablaManual tbody tr').length;
                            $('#badge-manual-count').text(manualesCount);
                        }
                        
                        // Reaplicar filtros después de actualizar
                        filtrarTablaHosting();
                        filtrarTablaDominio();
                        filtrarTablaManual();
                        sincronizarTotalesPendientesDesdeResponse(response);
                        // Si hay filtros activos, recalcular con filas visibles
                        recalcularTotalesPendientesVisibles('#tablaHosting', '#totalHostingPendientesMontos');
                        recalcularTotalesPendientesVisibles('#tablaDominio', '#totalDominioPendientesMontos');
                        recalcularTotalesPendientesVisibles('#tablaManual', '#totalManualPendientesMontos');
                        
                        Swal.fire({
                            icon: 'success',
                            title: 'Tablas actualizadas',
                            text: 'Los pagos pendientes se han actualizado correctamente',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire('Error', response.message || 'Error al actualizar las tablas', 'error');
                    }
                    hideLoading();
                },
                error: function(xhr, status, error) {
                    console.error('Error AJAX:', error);
                    Swal.fire('Error', 'Error de conexión al servidor', 'error');
                    hideLoading();
                }
            });
        }
        
        // Funciones de filtrado para tablas (client-side)
        function filtrarTablaHosting() {
            const search = $('#searchHosting').val().toLowerCase();
            const estado = $('#filtroEstadoHosting').val();
            const formaPago = $('#filtroFormaPagoHosting').val();
            
            $('#tablaHosting tbody tr').each(function() {
                let mostrar = true;
                const texto = $(this).text().toLowerCase();
                const estadoRow = $(this).data('estado-vencimiento');
                const formaRow = $(this).data('forma-pago');
                
                if (search && !texto.includes(search)) mostrar = false;
                if (estado !== 'todos' && estadoRow !== estado) mostrar = false;
                if (formaPago !== 'todos' && String(formaRow) !== formaPago) mostrar = false;
                
                $(this).toggle(mostrar);
            });
            recalcularTotalesPendientesVisibles('#tablaHosting', '#totalHostingPendientesMontos');
        }
        
        function filtrarTablaDominio() {
            const search = $('#searchDominio').val().toLowerCase();
            const estado = $('#filtroEstadoDominio').val();
            const formaPago = $('#filtroFormaPagoDominio').val();
            
            $('#tablaDominio tbody tr').each(function() {
                let mostrar = true;
                const texto = $(this).text().toLowerCase();
                const estadoRow = $(this).data('estado-vencimiento');
                const formaRow = $(this).data('forma-pago');
                
                if (search && !texto.includes(search)) mostrar = false;
                if (estado !== 'todos' && estadoRow !== estado) mostrar = false;
                if (formaPago !== 'todos' && String(formaRow) !== formaPago) mostrar = false;
                
                $(this).toggle(mostrar);
            });
            recalcularTotalesPendientesVisibles('#tablaDominio', '#totalDominioPendientesMontos');
        }
        
        function filtrarTablaManual() {
            const search = $('#searchManual').val().toLowerCase();
            const estado = $('#filtroEstadoManual').val();
            const formaPago = $('#filtroFormaPagoManual').val();
            
            $('#tablaManual tbody tr').each(function() {
                let mostrar = true;
                const texto = $(this).text().toLowerCase();
                const estadoRow = $(this).data('estado-vencimiento');
                const formaRow = $(this).data('forma-pago');
                
                if (search && !texto.includes(search)) mostrar = false;
                if (estado !== 'todos' && estadoRow !== estado) mostrar = false;
                if (formaPago !== 'todos' && String(formaRow) !== formaPago) mostrar = false;
                
                $(this).toggle(mostrar);
            });
            recalcularTotalesPendientesVisibles('#tablaManual', '#totalManualPendientesMontos');
        }
        
        function filtrarTablaAprobados() {
            const search = $('#searchAprobados').val().toLowerCase();
            $('#tablaAprobados tbody tr').each(function() {
                const texto = $(this).text().toLowerCase();
                $(this).toggle(!search || texto.includes(search));
            });
        }
        
        function filtrarTablaEliminados() {
            const search = $('#searchEliminados').val().toLowerCase();
            const tipo = $('#filtroTipoEliminados').val();
            
            $('#tablaEliminados tbody tr').each(function() {
                let mostrar = true;
                const texto = $(this).text().toLowerCase();
                const tipoRow = $(this).data('tipo-servicio');
                
                if (search && !texto.includes(search)) mostrar = false;
                if (tipo !== 'todos' && tipoRow !== tipo) mostrar = false;
                
                $(this).toggle(mostrar);
            });
        }
        
        // Funciones de limpieza
        window.limpiarFiltrosHosting = function() {
            $('#searchHosting').val('');
            $('#filtroEstadoHosting').val('todos');
            $('#filtroFormaPagoHosting').val('todos');
            filtrarTablaHosting();
            Swal.fire('Filtros limpiados', '', 'success');
        };
        
        window.limpiarFiltrosDominio = function() {
            $('#searchDominio').val('');
            $('#filtroEstadoDominio').val('todos');
            $('#filtroFormaPagoDominio').val('todos');
            filtrarTablaDominio();
            Swal.fire('Filtros limpiados', '', 'success');
        };
        
        window.limpiarFiltrosManual = function() {
            $('#searchManual').val('');
            $('#filtroEstadoManual').val('todos');
            $('#filtroFormaPagoManual').val('todos');
            filtrarTablaManual();
            Swal.fire('Filtros limpiados', '', 'success');
        };
        
        window.limpiarFiltrosEliminados = function() {
            $('#searchEliminados').val('');
            $('#filtroTipoEliminados').val('todos');
            filtrarTablaEliminados();
            Swal.fire('Filtros limpiados', '', 'success');
        };
        
        // Función AJAX para cargar aprobados con filtros
        function cargarAprobados(filtros) {
            showLoading();
            $.ajax({
                url: 'ajax_aprobados.php',
                type: 'POST',
                data: { ...filtros, sistema: sistemaActivo },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        actualizarTablaAprobados(response.data, response.totales, response.filtros_aplicados);
                    } else {
                        Swal.fire('Error', response.message || 'Error al cargar datos', 'error');
                    }
                    hideLoading();
                },
                error: function(xhr, status, error) {
                    console.error('Error AJAX:', error);
                    Swal.fire('Error', 'Error de conexión al servidor', 'error');
                    hideLoading();
                }
            });
        }
        
        function actualizarTablaAprobados(data, totales, filtrosAplicados) {
            const contenedor = $('#contenedorAprobados');
            const totalContainer = $('#totalAprobadosContainer');
            
            if (data.length === 0) {
                contenedor.html(`
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-secondary-custom">No se encontraron pagos aprobados</p>
                        ${filtrosAplicados ? '<small class="text-muted">Intenta con otros filtros o limpiar la búsqueda</small>' : ''}
                    </div>
                `);
                totalContainer.hide();
                return;
            }
            
            let html = `
                <table class="table" id="tablaAprobados" width="100%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>Tipo</th>
                            <th>Servicio</th>
                            <th>Fecha Pago</th>
                            <th>Forma Pago</th>
                            <th>Monto</th>
                            <th>Moneda</th>
                            <th>Concepto</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            data.forEach(pago => {
                const tipoClass = pago.tipo_servicio == 1 ? 'badge-hosting' : (pago.tipo_servicio == 2 ? 'badge-dominio' : 'badge-manual');
                const tipoTexto = pago.tipo_servicio == 1 ? 'Hosting' : (pago.tipo_servicio == 2 ? 'Dominio' : 'Servicio');
                const formaPagoTexto = pago.forma_pago == 1 ? 'Tarjeta' : (pago.forma_pago == 2 ? 'Transferencia' : (pago.forma_pago == 3 ? 'Efectivo' : 'Pendiente'));
                
                html += `
                    <tr>
                        <td><span class="badge-id">#${pago.id}</span></td>
                        <td>
                            <div class="fw-semibold">${escapeHtml(pago.cliente || '')}</div>
                            <small class="text-secondary-custom">ID: ${pago.id_clie}</small>
                        </td>
                        <td><span class="badge-status ${tipoClass}">${tipoTexto}</span></td>
                        <td>
                            <div class="fw-semibold">${escapeHtml(pago.nombre_servicio || pago.producto || 'N/A')}</div>
                            <small class="text-secondary-custom">Servicio ID: ${pago.id_servicio}</small>
                        </td>
                        <td>${pago.fecha_pago && pago.fecha_pago !== '0000-00-00' ? formatDate(pago.fecha_pago) : '-'}</td>
                        <td><span class="badge-status badge-aprobado">${formaPagoTexto}</span></td>
                        <td class="fw-semibold">$${formatNumber(pago.monto)}</td>
                        <td class="fw-medium">${pago.currency}</td>
                        <td class="concepto-cell" title="${escapeHtml(pago.concepto || '')}">${escapeHtml(pago.concepto || '')}</td>
                        <td>
                            <div class="adm-actions">
                                <button type="button" class="adm-act adm-act--view ver-reporte-btn" data-id="${pago.id}">
                                    <i class="fas fa-eye"></i>Ver
                                </button>
                                <button type="button" class="adm-act adm-act--info generar-pdf-btn" data-id="${pago.id}">
                                    <i class="fas fa-download"></i>PDF
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            html += `</tbody></table>`;
            contenedor.html(html);
            
            // Actualizar totales
            if (totales && Object.keys(totales).length > 0) {
                let totalHtml = '<h5><i class="fas fa-chart-line me-2"></i>Total de Pagos Aprobados</h5>';
                for (const [moneda, total] of Object.entries(totales)) {
                    totalHtml += `<div class="total-amount" data-moneda="${moneda}">${formatNumber(total)} ${moneda}</div>`;
                }
                totalHtml += `<small class="text-muted mt-2 d-block">* Solo se incluyen los pagos con estatus aprobado`;
                if (filtrosAplicados) {
                    totalHtml += ` con los filtros seleccionados`;
                }
                totalHtml += `</small>`;
                totalContainer.html(totalHtml);
                totalContainer.show();
            } else {
                totalContainer.hide();
            }
            
            // Reasignar eventos a los nuevos botones
            $('.ver-reporte-btn').on('click', function() {
                const id = $(this).data('id');
                verReporte(id);
            });
            
            $('.generar-pdf-btn').on('click', function() {
                const id = $(this).data('id');
                generarReporte(id);
            });
        }
        
        // Funciones auxiliares
        function escapeHtml(text) {
            if (!text) return '';
            return text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }
        
        function formatNumber(number) {
            return new Intl.NumberFormat('es-MX', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(number);
        }
        
        function formatDate(dateString) {
            if (!dateString || dateString === '0000-00-00') return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('es-MX');
        }
        
        // Función para filtrar desde el dashboard
        window.filtrarPorTab = function(tab) {
            if (tab === 'pendientes') {
                $('#pendientes-tab').tab('show');
            } else if (tab === 'aprobados') {
                $('#aprobados-tab').tab('show');
            } else if (tab === 'eliminados') {
                $('#eliminados-tab').tab('show');
            }
        };
        
        window.filtrarPorTipo = function(tipo) {
            $('#pendientes-tab').tab('show');
            setTimeout(function() {
                if (tipo === 'hosting') {
                    $('#sub-hosting-tab').tab('show');
                } else if (tipo === 'dominio') {
                    $('#sub-dominio-tab').tab('show');
                } else if (tipo === 'manual') {
                    $('#sub-manual-tab').tab('show');
                }
            }, 200);
        };
        
        // Funciones de acciones
        window.aprobarPago = function(idPago, id_servicio, tipo_servicio) {
            const metodos = {
                '2': 'Transferencia',
                '1': 'Tarjeta',
                '3': 'Efectivo'
            };
            Swal.fire({
                title: 'Aprobar pago',
                text: '¿Cómo fue realizado el pago?',
                icon: 'question',
                input: 'radio',
                inputOptions: metodos,
                inputValidator: (value) => {
                    if (!value) return 'Selecciona un método de pago';
                },
                showCancelButton: true,
                confirmButtonText: 'Continuar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#000147',
                cancelButtonColor: '#6c757d'
            }).then((result) => {
                if (!result.isConfirmed) return;
                const formaPago = parseInt(result.value, 10);
                const metodoNombre = metodos[String(formaPago)] || 'este método';

                // Doble confirmación para Transferencia y Tarjeta
                if (formaPago === 1 || formaPago === 2) {
                    Swal.fire({
                        title: '¿Estás seguro?',
                        html: '¿Estás seguro de aprobar el pago por <strong>' + metodoNombre + '</strong>?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, aprobar',
                        cancelButtonText: 'No, cancelar',
                        confirmButtonColor: '#198754',
                        cancelButtonColor: '#6c757d',
                        reverseButtons: true
                    }).then((confirmacion) => {
                        if (confirmacion.isConfirmed) {
                            procesarAprobacion(idPago, formaPago, id_servicio, tipo_servicio);
                        }
                    });
                    return;
                }

                procesarAprobacion(idPago, formaPago, id_servicio, tipo_servicio);
            });
        };
        
        function procesarAprobacion(idPago, formaPago, id_servicio, tipo_servicio) {
            Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            $.ajax({
                url: 'aprobar_pago.php',
                type: 'POST',
                data: { id: idPago, forma_pago: formaPago, id_servicio: id_servicio, tipo_servicio: tipo_servicio, sistema: sistemaActivo },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Aprobado',
                            showConfirmButton: false,
                            timer: 1200
                        });
                        actualizarTablasPendientes();
                    } else {
                        Swal.fire('Error', res.message || 'Error al aprobar', 'error');
                    }
                },
                error: function() { Swal.fire('Error', 'Error de conexión', 'error'); }
            });
        }
        
        function enviarCorreoConfirmacion(idPago, tipoServicio, correoCliente, manual) {
            Swal.fire({ title: 'Enviando confirmación...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            let url = manual == 1 ? "reenviar_correo_manual.php" : (tipoServicio == 1 ? "enviar_correo_confirmacion_hosting.php" : "enviar_correo_confirmacion_dominio.php");
            $.ajax({
                url: url,
                type: 'POST',
                data: { id: idPago, tipo_servicio: tipoServicio, sistema: sistemaActivo, ...(manual == 1 && { pago_id: idPago, resend: 1 }) },
                success: function() { 
                    Swal.fire('Correo enviado', 'Confirmación enviada correctamente', 'success');
                    actualizarTablasPendientes();
                },
                error: function() { 
                    Swal.fire('Advertencia', 'Pago aprobado pero correo no enviado', 'warning');
                    actualizarTablasPendientes();
                }
            });
        }
        
        var previewCorreoCtx = null;

        function etiquetaAlertaPago(modo) {
            if (modo === 'vencimiento') return 'Alerta de vencimiento';
            if (modo === 'plazo') return 'Alerta de plazo';
            if (modo === 'eliminacion' || modo === 'vencido') return 'Alerta de eliminación';
            return 'Recordatorio de pago';
        }

        function esModoAlertaPago(modo) {
            return ['vencido', 'vencimiento', 'plazo', 'eliminacion'].indexOf(modo) !== -1;
        }

        function abrirPreviewCorreoPago(opts) {
            var modo = opts.modo || 'pendiente';
            if (modo === 'vencido') modo = 'eliminacion';
            previewCorreoCtx = {
                id: opts.id,
                tipo: opts.tipo,
                correo: opts.correo || '',
                manual: opts.manual || 0,
                modo: esModoAlertaPago(modo) ? modo : 'pendiente'
            };

            $('#previewCorreoLoading').show();
            $('#previewCorreoFrame').hide().removeAttr('srcdoc').attr('src', 'about:blank');
            $('#previewCorreoError').hide().text('');
            $('#previewCorreoTo').text(previewCorreoCtx.correo || '—');
            $('#previewCorreoAsunto').text('…');
            $('#previewCorreoNota').text('');
            $('#previewCorreoMeta').text(
                etiquetaAlertaPago(previewCorreoCtx.modo) +
                ' · Pago #' + previewCorreoCtx.id
            );
            $('#btnConfirmarEnvioCorreo')
                .prop('disabled', true)
                .toggleClass('btn-warning', esModoAlertaPago(previewCorreoCtx.modo))
                .toggleClass('btn-primary', !esModoAlertaPago(previewCorreoCtx.modo))
                .html(esModoAlertaPago(previewCorreoCtx.modo)
                    ? '<i class="fas fa-envelope mr-1"></i> Enviar ' + etiquetaAlertaPago(previewCorreoCtx.modo).toLowerCase()
                    : '<i class="fas fa-paper-plane mr-1"></i> Confirmar y enviar');

            $('#modalPreviewCorreoPago').modal('show');

            $.ajax({
                url: 'preview_correo_pago.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    id: previewCorreoCtx.id,
                    modo: previewCorreoCtx.modo,
                    sistema: sistemaActivo
                },
                success: function(res) {
                    $('#previewCorreoLoading').hide();
                    if (!res || !res.success) {
                        $('#previewCorreoError').text((res && res.message) ? res.message : 'No se pudo generar la vista previa').show();
                        return;
                    }
                    $('#previewCorreoTo').text(res.cliente && res.correo ? (res.cliente + ' · ' + res.correo) : (res.correo || '—'));
                    $('#previewCorreoAsunto').text(res.asunto || '—');
                    $('#previewCorreoNota').text(res.nota || '');
                    $('#previewCorreoMeta').text(
                        etiquetaAlertaPago(res.modo || previewCorreoCtx.modo) +
                        (res.tipo ? ' · ' + res.tipo : '') +
                        ' · Pago #' + previewCorreoCtx.id
                    );
                    var $frame = $('#previewCorreoFrame');
                    // Preferir srcdoc: evita 2ª petición bloqueada por X-Frame-Options / redirects
                    if (res.html) {
                        $frame.removeAttr('src').attr('srcdoc', res.html).show();
                    } else {
                        var frameUrl = res.preview_url || (
                            'preview_correo_pago.php?render=1&id=' + encodeURIComponent(previewCorreoCtx.id) +
                            '&modo=' + encodeURIComponent(previewCorreoCtx.modo) +
                            '&sistema=' + encodeURIComponent(sistemaActivo) +
                            '&_=' + Date.now()
                        );
                        if (frameUrl.indexOf('?') !== -1 && frameUrl.indexOf('_=') === -1) {
                            frameUrl += '&_=' + Date.now();
                        }
                        $frame.removeAttr('srcdoc').attr('src', frameUrl).show();
                    }
                    $('#btnConfirmarEnvioCorreo').prop('disabled', false);
                    previewCorreoCtx.correo = res.correo || previewCorreoCtx.correo;
                },
                error: function(xhr) {
                    $('#previewCorreoLoading').hide();
                    var msg = 'Error de conexión al generar la vista previa';
                    if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    } else if (xhr && xhr.status) {
                        msg += ' (HTTP ' + xhr.status + ')';
                    }
                    $('#previewCorreoError').text(msg).show();
                }
            });
        }

        function ejecutarEnvioCorreoDesdePreview() {
            if (!previewCorreoCtx) return;
            var ctx = previewCorreoCtx;
            var $btn = $('#btnConfirmarEnvioCorreo');
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Enviando…');

            var url, data;
            if (esModoAlertaPago(ctx.modo)) {
                url = 'enviar_aviso_vencido.php';
                data = { id: ctx.id, sistema: sistemaActivo, modo: ctx.modo };
            } else if (ctx.manual == 1) {
                url = 'reenviar_correo_pago_pendienteManual.php';
                data = { id: ctx.id, pago_id: ctx.id, resend: 1, sistema: sistemaActivo };
            } else if (String(ctx.tipo) === '1') {
                url = 'reenviar_correos_hosting.php';
                data = { id: ctx.id, sistema: sistemaActivo };
            } else {
                url = 'reenviar_correos_dominios.php';
                data = { id: ctx.id, sistema: sistemaActivo };
            }

            $.ajax({
                url: url,
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function(res) {
                    $('#modalPreviewCorreoPago').modal('hide');
                    if (res && res.success) {
                        var okMsg = res.message || res.email_status || 'Correo enviado correctamente';
                        if (okMsg === true || okMsg === 'Enviado') okMsg = 'Correo enviado correctamente';
                        Swal.fire('Enviado', okMsg, 'success');
                    } else {
                        Swal.fire('Error', (res && (res.message || res.error)) ? (res.message || res.error) : 'No se pudo enviar el correo', 'error');
                    }
                },
                error: function(xhr) {
                    $('#modalPreviewCorreoPago').modal('hide');
                    // Algunos endpoints antiguos no siempre responden JSON perfecto
                    if (xhr.status >= 200 && xhr.status < 300) {
                        Swal.fire('Enviado', 'Correo procesado', 'success');
                    } else {
                        Swal.fire('Error', 'Error de conexión al enviar', 'error');
                    }
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<i class="fas fa-paper-plane mr-1"></i> Confirmar y enviar');
                }
            });
        }

        $(document).on('click', '#btnConfirmarEnvioCorreo', function() {
            ejecutarEnvioCorreoDesdePreview();
        });

        window.reenviarCorreo = function(idPago, tipoServicio, correoCliente, manual) {
            abrirPreviewCorreoPago({
                id: idPago,
                tipo: tipoServicio,
                correo: correoCliente,
                manual: manual,
                modo: 'pendiente'
            });
        };

        window.enviarAvisoVencido = function(idPago, tipoServicio, correoCliente, modoAlerta) {
            abrirPreviewCorreoPago({
                id: idPago,
                tipo: tipoServicio,
                correo: correoCliente,
                manual: 0,
                modo: modoAlerta || 'eliminacion'
            });
        };
        
        window.enviarWhatsAppPago = function(idPago, cliente) {
            // Mostrar loading mientras se genera el mensaje
            Swal.fire({
                title: 'Generando mensaje...',
                html: 'Por favor espera',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Hacer llamada AJAX para generar el mensaje completo
            $.ajax({
                url: 'generar_mensaje_whatsapp_pago.php',
                type: 'POST',
                data: { 
                    id: idPago,
                    sistema: sistemaActivo
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Cerrar el loading
                        Swal.close();
                        
                        // Codificar el mensaje para URL
                        const mensajeCodificado = encodeURIComponent(response.mensaje);
                        const urlWhatsApp = `https://wa.me/${response.telefono}?text=${mensajeCodificado}`;
                        
                        // Mostrar confirmación antes de abrir WhatsApp
                        Swal.fire({
                            title: '¿Enviar mensaje por WhatsApp?',
                            html: `Se abrirá WhatsApp con un mensaje para:<br><strong>${response.cliente}</strong>`,
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonText: '<i class="fab fa-whatsapp"></i> Sí, abrir WhatsApp',
                            cancelButtonText: 'Cancelar',
                            confirmButtonColor: '#25D366'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Abrir WhatsApp en una nueva ventana
                                window.open(urlWhatsApp, '_blank');
                            }
                        });
                    } else {
                        Swal.fire('Error', response.message || 'No se pudo generar el mensaje', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error AJAX:', error);
                    Swal.fire('Error', 'Error de conexión al generar el mensaje', 'error');
                }
            });
        };
        
        window.eliminarPago = function(idPago) {
            Swal.fire({
                title: '¿Eliminar este pago?',
                text: 'Será marcado como eliminado.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({ title: 'Eliminando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                    $.ajax({
                        url: 'eliminar_pago.php',
                        type: 'POST',
                        data: { id: idPago, sistema: sistemaActivo },
                        dataType: 'json',
                        success: function(res) {
                            if (res.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Eliminado',
                                    showConfirmButton: false,
                                    timer: 1200
                                });
                                actualizarTablasPendientes();
                            } else {
                                Swal.fire('Error', res.message, 'error');
                            }
                        },
                        error: function() { Swal.fire('Error', 'Error de conexión', 'error'); }
                    });
                }
            });
        };
        
        window.restaurarPago = function(idPago) {
            Swal.fire({
                title: '¿Restaurar este pago?',
                text: 'Volverá a aparecer en las listas.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, restaurar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({ title: 'Restaurando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                    $.ajax({
                        url: 'restaurar_pago.php',
                        type: 'POST',
                        data: { id: idPago, sistema: sistemaActivo },
                        dataType: 'json',
                        success: function(res) {
                            if (res.success) {
                                Swal.fire('Restaurado', res.message, 'success').then(() => location.reload());
                            } else {
                                Swal.fire('Error', res.message, 'error');
                            }
                        },
                        error: function() { Swal.fire('Error', 'Error de conexión', 'error'); }
                    });
                }
            });
        };
        
        // Nota de pago PDF (servidor TCPDF, mismo diseño que el correo)
        function urlNotaPagoPdf(pagoId, inline) {
            return 'generar_nota_pago_pdf.php?id=' + encodeURIComponent(pagoId)
                + '&sistema=' + encodeURIComponent(sistemaActivo)
                + (inline ? '&inline=1' : '');
        }

        window.generarReporte = function(pagoId) {
            const pago = pagos.find(p => p.id == pagoId);
            if (!pago || pago.estatus != 1) {
                Swal.fire('Error', 'No se puede generar el reporte', 'error');
                return;
            }
            Swal.fire({ title: 'Generando PDF...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            const a = document.createElement('a');
            a.href = urlNotaPagoPdf(pagoId, false);
            a.download = '';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            setTimeout(function() {
                Swal.close();
                Swal.fire('PDF listo', 'La nota de pago se está descargando.', 'success');
            }, 600);
        };

        window.verReporte = function(pagoId) {
            const pago = pagos.find(p => p.id == pagoId);
            if (!pago || pago.estatus != 1) {
                Swal.fire('Error', 'No se puede generar el reporte', 'error');
                return;
            }
            window.open(urlNotaPagoPdf(pagoId, true), '_blank');
        };
        
        // Event listeners
        $(document).ready(function() {
            // Eventos de filtrado (client-side)
            $('#searchHosting, #filtroEstadoHosting, #filtroFormaPagoHosting').on('input change', filtrarTablaHosting);
            $('#searchDominio, #filtroEstadoDominio, #filtroFormaPagoDominio').on('input change', filtrarTablaDominio);
            $('#searchManual, #filtroEstadoManual, #filtroFormaPagoManual').on('input change', filtrarTablaManual);
            $('#searchAprobados').on('input', filtrarTablaAprobados);
            $('#searchEliminados, #filtroTipoEliminados').on('input change', filtrarTablaEliminados);
            
            // Eventos AJAX para aprobados
            $('#btnFiltrarAprobados').on('click', function() {
                const filtros = {
                    anio: $('#filtro_anio_ajax').val(),
                    mes: $('#filtro_mes_ajax').val(),
                    fecha_inicio: $('#fecha_inicio_ajax').val(),
                    fecha_fin: $('#fecha_fin_ajax').val()
                };
                cargarAprobados(filtros);
            });
            
            $('#btnLimpiarFiltrosAprobados').on('click', function() {
                $('#filtro_anio_ajax').val('');
                $('#filtro_mes_ajax').val('');
                $('#fecha_inicio_ajax').val('');
                $('#fecha_fin_ajax').val('');
                $('#searchAprobados').val('');
                cargarAprobados({});
            });
            
            // Actualizar meses disponibles cuando cambia el año
            $('#filtro_anio_ajax').on('change', function() {
                const anio = $(this).val();
                if (anio) {
                    $.ajax({
                        url: 'ajax_meses.php',
                        type: 'POST',
                        data: { anio: anio, sistema: sistemaActivo },
                        dataType: 'json',
                        success: function(response) {
                            const mesSelect = $('#filtro_mes_ajax');
                            mesSelect.html('<option value="">Todos los meses</option>');
                            if (response.meses && response.meses.length > 0) {
                                const nombresMeses = {
                                    1: 'Enero', 2: 'Febrero', 3: 'Marzo', 4: 'Abril',
                                    5: 'Mayo', 6: 'Junio', 7: 'Julio', 8: 'Agosto',
                                    9: 'Septiembre', 10: 'Octubre', 11: 'Noviembre', 12: 'Diciembre'
                                };
                                response.meses.forEach(mes => {
                                    mesSelect.append(`<option value="${mes.mes_numero}">${nombresMeses[mes.mes_numero]} <span class="select-badge">(${mes.total_pagos} pagos)</span></option>`);
                                });
                                mesSelect.prop('disabled', false);
                            } else {
                                mesSelect.append('<option value="" disabled>No hay pagos en este año</option>');
                                mesSelect.prop('disabled', true);
                            }
                        }
                    });
                } else {
                    $('#filtro_mes_ajax').html('<option value="">Todos los meses</option>').prop('disabled', false);
                }
            });
            
            // Eventos de botones dinámicos
            $(document).on('click', '.aprobar-btn', function() {
                const id = $(this).data('id');
                const servicio = $(this).data('servicio');
                const tipo = $(this).data('tipo');
                aprobarPago(id, servicio, tipo);
            });
            
            $(document).on('click', '.reenviar-btn', function() {
                const id = $(this).data('id');
                const tipo = $(this).data('tipo');
                const correo = $(this).data('correo');
                const manual = $(this).data('manual');
                reenviarCorreo(id, tipo, correo, manual);
            });

            $(document).on('click', '.aviso-vencido-btn', function() {
                const id = $(this).data('id');
                const tipo = $(this).data('tipo');
                const correo = $(this).data('correo');
                enviarAvisoVencido(id, tipo, correo, 'vencido');
            });

            $(document).on('click', '.alerta-hosting-btn', function() {
                const id = $(this).data('id');
                const tipo = $(this).data('tipo');
                const correo = $(this).data('correo');
                const alerta = $(this).data('alerta') || 'eliminacion';
                enviarAvisoVencido(id, tipo, correo, alerta);
            });
            
            $(document).on('click', '.whatsapp-pago-btn', function() {
                const id = $(this).data('id');
                const cliente = $(this).data('cliente');
                enviarWhatsAppPago(id, cliente);
            });
            
            $(document).on('click', '.eliminar-btn', function() {
                const id = $(this).data('id');
                eliminarPago(id);
            });
            
            $(document).on('click', '.restaurar-btn', function() {
                const id = $(this).data('id');
                restaurarPago(id);
            });
            
            $(document).on('click', '.generar-pdf-btn', function() {
                const id = $(this).data('id');
                generarReporte(id);
            });
            
            $(document).on('click', '.ver-reporte-btn', function() {
                const id = $(this).data('id');
                verReporte(id);
            });
            
            // Inicializar filtros
            filtrarTablaHosting();
            filtrarTablaDominio();
            filtrarTablaManual();
            filtrarTablaAprobados();
            filtrarTablaEliminados();
        });
        
        
        function actualizarTablasPendientes() {
            showLoading();
            
            $.ajax({
                url: 'ajax_actualizar_tablas_pendientes.php',
                type: 'POST',
                data: { sistema: sistemaActivo },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Actualizar tabla de Hosting
                        if (response.hosting.empty) {
                            $('#contenedorHosting').html(`
                                <div class="text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-secondary-custom">No se encontraron pagos pendientes de Hosting</p>
                                </div>
                            `);
                            $('#badge-hosting-count').text('0');
                        } else {
                            $('#contenedorHosting').html(`
                                <table class="table" id="tablaHosting" width="100%">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Cliente</th>
                                            <th>Servicio</th>
                                            <th>Plan</th>
                                            <th>Fecha de vencimiento</th>
                                            <th>Fecha de plazo</th>
                                            <th>Fecha de eliminación</th>
                                            <th>Monto</th>
                                            <th>Moneda</th>
                                            <th>Concepto</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${response.hosting.html}
                                    </tbody>
                                </table>
                            `);
                            const hostingCount = $('#tablaHosting tbody tr').length;
                            $('#badge-hosting-count').text(hostingCount);
                        }
                        
                        // Actualizar tabla de Dominios
                        if (response.dominios.empty) {
                            $('#contenedorDominio').html(`
                                <div class="text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-secondary-custom">No se encontraron pagos pendientes de Dominios</p>
                                </div>
                            `);
                            $('#badge-dominio-count').text('0');
                        } else {
                            $('#contenedorDominio').html(`
                                <table class="table" id="tablaDominio" width="100%">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Cliente</th>
                                            <th>Dominio</th>
                                            <th>Fecha Límite</th>
                                            <th>Monto</th>
                                            <th>Moneda</th>
                                            <th>Concepto</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${response.dominios.html}
                                    </tbody>
                                </table>
                            `);
                            const dominiosCount = $('#tablaDominio tbody tr').length;
                            $('#badge-dominio-count').text(dominiosCount);
                        }
                        
                        // Actualizar tabla de Manuales
                        if (response.manuales.empty) {
                            $('#contenedorManual').html(`
                                <div class="text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-secondary-custom">No se encontraron pagos pendientes de Servicios Manuales</p>
                                </div>
                            `);
                            $('#badge-manual-count').text('0');
                        } else {
                            $('#contenedorManual').html(`
                                <table class="table" id="tablaManual" width="100%">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Cliente</th>
                                            <th>Concepto</th>
                                            <th>Fecha Límite</th>
                                            <th>Forma Pago</th>
                                            <th>Monto</th>
                                            <th>Moneda</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${response.manuales.html}
                                    </tbody>
                                </table>
                            `);
                            const manualesCount = $('#tablaManual tbody tr').length;
                            $('#badge-manual-count').text(manualesCount);
                        }
                        
                        // Reaplicar filtros después de actualizar
                        filtrarTablaHosting();
                        filtrarTablaDominio();
                        filtrarTablaManual();
                        sincronizarTotalesPendientesDesdeResponse(response);
                        recalcularTotalesPendientesVisibles('#tablaHosting', '#totalHostingPendientesMontos');
                        recalcularTotalesPendientesVisibles('#tablaDominio', '#totalDominioPendientesMontos');
                        recalcularTotalesPendientesVisibles('#tablaManual', '#totalManualPendientesMontos');
                        
                        hideLoading();
                    } else {
                        Swal.fire('Error', response.message || 'Error al actualizar las tablas', 'error');
                        hideLoading();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error AJAX:', error);
                    Swal.fire('Error', 'Error de conexión al servidor', 'error');
                    hideLoading();
                }
            });
        }
        
    </script>
</body>
</html>