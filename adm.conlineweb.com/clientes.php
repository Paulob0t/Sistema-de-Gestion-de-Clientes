<?php
header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set("display_errors", 1);
include "menu.php";
include "conn.php";
include "conn_hostingpro.php"; // Esta BD contiene los clientes de ConlineWeb

// Sistema activo: conlineweb (default), hostingpro o planpro
$sistema = 'conlineweb';
if (isset($_GET['sistema'])) {
    if ($_GET['sistema'] === 'hostingpro') {
        $sistema = 'hostingpro';
    } elseif ($_GET['sistema'] === 'planpro') {
        $sistema = 'planpro';
    }
}
$db = ($sistema === 'hostingpro' || $sistema === 'planpro') ? $conn_hp : $conn;

$tiene_columna_transferido = false;
if ($sistema === 'conlineweb') {
    $checkCol = $db->query("SHOW COLUMNS FROM clientes LIKE 'transferido'");
    $tiene_columna_transferido = $checkCol && $checkCol->num_rows > 0;
}

$select_clientes = $tiene_columna_transferido
    ? "id, empresa, nombre_contacto, correo, telefono, actualizado, eliminado, transferido, fecha_transferencia, usuario_transferencia"
    : "id, empresa, nombre_contacto, correo, telefono, actualizado, eliminado";
$select_clientes_join = $tiene_columna_transferido
    ? "c.id, c.empresa, c.nombre_contacto, c.correo, c.telefono, c.actualizado, c.eliminado, c.transferido, c.fecha_transferencia, c.usuario_transferencia"
    : "c.id, c.empresa, c.nombre_contacto, c.correo, c.telefono, c.actualizado, c.eliminado";

// Verificar el tipo de usuario para filtrar clientes
$uid = $_SESSION['uid'] ?? null;
$tipo = intval($_SESSION['tipo'] ?? 0);

// Si es tipo 5 (ventas), solo mostrar clientes que ellos registraron
if ($tipo === 5) {
    // Filtro para Plan Pro: solo clientes con pagos en sistema='conlineweb'
    if ($sistema === 'planpro') {
        $sql = "SELECT DISTINCT $select_clientes_join
                FROM clientes c
                INNER JOIN login l ON l.id = c.id
                INNER JOIN pagos p ON c.id = p.id_clie
                WHERE c.usuario_registro = ? AND p.sistema = 'conlineweb' AND l.id_tipo_usuario = 0";
    } else {
        $sql = "SELECT $select_clientes_join
                FROM clientes c
                INNER JOIN login l ON l.id = c.id
                WHERE c.usuario_registro = ? AND l.id_tipo_usuario = 0";
    }
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Para otros tipos, mostrar todos los clientes (solo login tipo 0)
    // Filtro para Plan Pro: solo clientes con pagos en sistema='conlineweb'
    if ($sistema === 'planpro') {
        $sql = "SELECT DISTINCT $select_clientes_join
                FROM clientes c
                INNER JOIN login l ON l.id = c.id
                INNER JOIN pagos p ON c.id = p.id_clie
                WHERE p.sistema = 'conlineweb' AND l.id_tipo_usuario = 0";
    } else {
        $sql = "SELECT $select_clientes_join
                FROM clientes c
                INNER JOIN login l ON l.id = c.id
                WHERE l.id_tipo_usuario = 0";
    }
    $result = $db->query($sql);
}

$clientes = array();
$clientes_activos = array();
$clientes_eliminados = array();
$clientes_transferidos = array();
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $clientes[] = $row;
        $esTransferido = $tiene_columna_transferido && isset($row['transferido']) && (int) $row['transferido'] === 1;
        if ($esTransferido) {
            $clientes_transferidos[] = $row;
        } elseif (isset($row['eliminado']) && $row['eliminado'] == 1) {
            $clientes_eliminados[] = $row;
        } else {
            $clientes_activos[] = $row;
        }
    }
    $result->data_seek(0);
}

// Calcular estadísticas para el dashboard
$total_clientes = count($clientes_activos);
$clientes_30_dias = 0;

foreach ($clientes_activos as $cliente) {
    if (!empty($cliente['actualizado']) && $cliente['actualizado'] !== '0000-00-00') {
        $dias_actualizacion = (strtotime(date('Y-m-d')) - strtotime($cliente['actualizado'])) / (60 * 60 * 24);
        if ($dias_actualizacion <= 30) {
            $clientes_30_dias++;
        }
    }
}

// ══════════════════════════════════════════════════════════════
// CONSULTAR CLIENTES DE CONLINEWEB.COM (conlineweb_hosting)
// Usamos $conn_hp porque apunta a la misma BD
// Para Plan Pro: filtrar solo clientes con pagos en sistema='conlineweb'
// ══════════════════════════════════════════════════════════════
$clientes_cw = array();
$clientes_cw_activos = array();
$clientes_cw_eliminados = array();

// Verificar que la conexión a HostingPro/ConlineWeb existe
if ($conn_hp !== null) {
    if ($sistema === 'planpro') {
        // Plan Pro: solo clientes con pagos registrados en sistema='conlineweb'
        $sql_cw = "SELECT DISTINCT c.id, c.empresa, c.nombre_contacto, c.correo, c.telefono, c.actualizado, c.eliminado
                   FROM clientes c
                   INNER JOIN login l ON l.id = c.id
                   INNER JOIN pagos p ON c.id = p.id_clie
                   WHERE p.sistema = 'conlineweb' AND l.id_tipo_usuario = 0";
    } else {
        $sql_cw = "SELECT c.id, c.empresa, c.nombre_contacto, c.correo, c.telefono, c.actualizado, c.eliminado
                   FROM clientes c
                   INNER JOIN login l ON l.id = c.id
                   WHERE l.id_tipo_usuario = 0";
    }
    $result_cw = $conn_hp->query($sql_cw);

    if ($result_cw && $result_cw->num_rows > 0) {
        while ($row = $result_cw->fetch_assoc()) {
            $clientes_cw[] = $row;
            if (isset($row['eliminado']) && $row['eliminado'] == 1) {
                $clientes_cw_eliminados[] = $row;
            } else {
                $clientes_cw_activos[] = $row;
            }
        }
        $result_cw->data_seek(0);
    }
} else {
    error_log("clientes.php: No se pudo conectar a ConlineWeb DB. Los clientes de CW no se mostrarán.");
}

$total_clientes_cw = count($clientes_cw_activos);
$clientes_cw_30_dias = 0;

foreach ($clientes_cw_activos as $cliente) {
    if (!empty($cliente['actualizado']) && $cliente['actualizado'] !== '0000-00-00') {
        $dias_actualizacion = (strtotime(date('Y-m-d')) - strtotime($cliente['actualizado'])) / (60 * 60 * 24);
        if ($dias_actualizacion <= 30) {
            $clientes_cw_30_dias++;
        }
    }
}

// Reporte de transferencias (auditoría para jefe / admin)
$reporte_transferencias = [];
$transferencia_totales = [
    'clientes' => 0,
    'dominios' => 0,
    'hostings' => 0,
    'pagos' => 0,
];

if ($sistema === 'conlineweb' && $tiene_columna_transferido) {
    $checkLog = $conn->query("SHOW TABLES LIKE 'clientes_transferencia_log'");
    if ($checkLog && $checkLog->num_rows > 0) {
        $sqlReporte = "SELECT l.cliente_id, l.usuario_id, l.fecha_transferencia, l.destino_sistema,
                              l.dominios_transferidos, l.hostings_transferidos, l.pagos_transferidos,
                              c.empresa, c.nombre_contacto, c.correo, c.telefono,
                              COALESCE(u.usuario, CONCAT('Usuario #', l.usuario_id)) AS responsable
                       FROM clientes_transferencia_log l
                       INNER JOIN clientes c ON c.id = l.cliente_id
                       LEFT JOIN login u ON u.id = l.usuario_id
                       ORDER BY l.fecha_transferencia DESC";
        $resReporte = $conn->query($sqlReporte);
        if ($resReporte) {
            while ($fila = $resReporte->fetch_assoc()) {
                $reporte_transferencias[(int) $fila['cliente_id']] = $fila;
                $transferencia_totales['clientes']++;
                $transferencia_totales['dominios'] += (int) $fila['dominios_transferidos'];
                $transferencia_totales['hostings'] += (int) $fila['hostings_transferidos'];
                $transferencia_totales['pagos'] += (int) $fila['pagos_transferidos'];
            }
        }
    }
}

$ids_cw_activos_sin_transferir = [];
if ($sistema === 'hostingpro' && $conn) {
    $checkColCw = $conn->query("SHOW COLUMNS FROM clientes LIKE 'transferido'");
    $tieneTransferidoCw = $checkColCw && $checkColCw->num_rows > 0;
    $sqlCwActivos = $tieneTransferidoCw
        ? "SELECT id FROM clientes WHERE eliminado = 0 AND IFNULL(transferido, 0) = 0"
        : "SELECT id FROM clientes WHERE eliminado = 0";
    $resCwActivos = $conn->query($sqlCwActivos);
    if ($resCwActivos && $resCwActivos->num_rows > 0) {
        while ($filaCw = $resCwActivos->fetch_assoc()) {
            $ids_cw_activos_sin_transferir[] = (int) $filaCw['id'];
        }
    }
}

/**
 * Conteos por cliente para la tabla (dominios / hosting / proyectos).
 */
$conteo_dominios = [];
$conteo_hostings = [];
$conteo_proyectos = [];
$conteo_pagos_pend = [];
$conteo_dominios_cw = [];
$conteo_hostings_cw = [];
$conteo_proyectos_cw = [];
$conteo_pagos_pend_cw = [];

$cargarConteos = static function ($mysqli, string $sql, string $idField = 'cliente_id'): array {
    $map = [];
    if (!$mysqli) {
        return $map;
    }
    $res = @$mysqli->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $map[(int) $r[$idField]] = (int) $r['total'];
        }
    }
    return $map;
};

$conteo_dominios = $cargarConteos($db, "SELECT cliente_id, COUNT(*) AS total FROM dominios WHERE IFNULL(eliminado, 0) = 0 GROUP BY cliente_id");
$conteo_hostings = $cargarConteos($db, "SELECT cliente_id, COUNT(*) AS total FROM hosting WHERE IFNULL(eliminado, 0) = 0 GROUP BY cliente_id");
$conteo_pagos_pend = $cargarConteos(
    $db,
    "SELECT id_clie AS cliente_id, COUNT(*) AS total FROM pagos WHERE estatus = 0 AND Registro = 0 GROUP BY id_clie"
);

if ($sistema === 'conlineweb' && $conn) {
    $conteo_proyectos = $cargarConteos($conn, "SELECT id_cliente AS cliente_id, COUNT(*) AS total FROM proyectos GROUP BY id_cliente");
} else {
    $chkProy = @$db->query("SHOW TABLES LIKE 'proyectos'");
    if ($chkProy && $chkProy->num_rows > 0) {
        $conteo_proyectos = $cargarConteos($db, "SELECT id_cliente AS cliente_id, COUNT(*) AS total FROM proyectos GROUP BY id_cliente");
    }
}

if ($conn_hp !== null) {
    $conteo_dominios_cw = $cargarConteos($conn_hp, "SELECT cliente_id, COUNT(*) AS total FROM dominios WHERE IFNULL(eliminado, 0) = 0 GROUP BY cliente_id");
    $conteo_hostings_cw = $cargarConteos($conn_hp, "SELECT cliente_id, COUNT(*) AS total FROM hosting WHERE IFNULL(eliminado, 0) = 0 GROUP BY cliente_id");
    $conteo_pagos_pend_cw = $cargarConteos(
        $conn_hp,
        "SELECT id_clie AS cliente_id, COUNT(*) AS total FROM pagos WHERE estatus = 0 AND Registro = 0 AND sistema = 'conlineweb' GROUP BY id_clie"
    );
    $chkProyHp = @$conn_hp->query("SHOW TABLES LIKE 'proyectos'");
    if ($chkProyHp && $chkProyHp->num_rows > 0) {
        $conteo_proyectos_cw = $cargarConteos($conn_hp, "SELECT id_cliente AS cliente_id, COUNT(*) AS total FROM proyectos GROUP BY id_cliente");
    } elseif ($conn) {
        // Plan Pro / HostingPro: proyectos viven en ConlineWeb principal
        $conteo_proyectos_cw = $cargarConteos($conn, "SELECT id_cliente AS cliente_id, COUNT(*) AS total FROM proyectos GROUP BY id_cliente");
    }
}

// Checklist reseñas Google (módulo mensajes WhatsApp)
require_once __DIR__ . '/includes/cw_client_messages_service.php';
require_once __DIR__ . '/includes/cw_client_verification_service.php';
$cwMsgData = cw_client_messages_load();
$cwGoogleReviews = is_array($cwMsgData['google_reviews'] ?? null) ? $cwMsgData['google_reviews'] : [];
$cwReviewSistema = $sistema === 'planpro' ? 'planpro' : $sistema;
$cwVerificationAll = cw_client_verification_load_all();
?>

<!DOCTYPE html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="css/admin-platform.css" rel="stylesheet">
    <link href="vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
    <link href="css/admin-consultas.css?v=20250714" rel="stylesheet">
    <link href="css/admin-datatables.css?v=20250716a" rel="stylesheet">
    <style>
        #dataTableAdmActivos th.cli-count-col,
        #dataTableCwActivos th.cli-count-col,
        #dataTableAdmActivos td.cli-count-col,
        #dataTableCwActivos td.cli-count-col {
            text-align: center !important;
            vertical-align: middle;
        }
        .cli-count {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.28rem;
            min-width: 4.5rem;
            min-height: 2.35rem;
            padding: 0.4rem 0.7rem;
            border-radius: 10px;
            font-size: 1.05rem;
            font-weight: 750;
            line-height: 1.15;
            text-align: center;
        }
        .cli-count__num {
            font-size: 1.12rem;
            font-weight: 800;
            line-height: 1;
        }
        .cli-count__label {
            display: block;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.01em;
            text-transform: lowercase;
            opacity: 0.92;
            line-height: 1.1;
        }
        .cli-count--dom { background: #dbeafe; color: #1e40af; }
        .cli-count--host { background: #d1fae5; color: #065f46; }
        .cli-count--proy { background: #ede9fe; color: #5b21b6; }
        .cli-count--ok {
            background: #f1f5f9;
            color: #64748b;
            font-size: 0.92rem;
            font-weight: 650;
            min-width: auto;
            padding: 0.4rem 0.75rem;
            gap: 0;
        }
        .cli-count--pend {
            background: #ffedd5;
            color: #c2410c;
        }
        .cli-review {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            min-width: 7.5rem;
            justify-content: center;
            padding: 0.35rem 0.55rem;
            border-radius: 10px;
            font-size: 0.78rem;
            font-weight: 700;
            border: 1px solid transparent;
            cursor: pointer;
            user-select: none;
            line-height: 1.2;
            background: #fff7ed;
            color: #9a3412;
            border-color: #fed7aa;
        }
        .cli-review input {
            margin: 0;
            width: 1rem;
            height: 1rem;
            cursor: pointer;
            accent-color: #059669;
        }
        .cli-review.is-done {
            background: #ecfdf5;
            color: #047857;
            border-color: #a7f3d0;
        }
        .cli-review__label { white-space: nowrap; }
        .cli-review-wrap {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            gap: 0.35rem;
        }
        .cli-review-ask {
            border: 0;
            background: #000147;
            color: #fff;
            font-size: 0.68rem;
            font-weight: 700;
            border-radius: 8px;
            padding: 0.28rem 0.55rem;
            cursor: pointer;
            line-height: 1.2;
            white-space: nowrap;
        }
        .cli-review-ask:hover { filter: brightness(1.08); }
        .cli-review-ask .fa-paper-plane { margin-right: 0.2rem; }
        .cli-verify-btn {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            gap: 0.2rem;
            min-width: 7.2rem;
            border: 1px solid #c7d2fe;
            background: #eef2ff;
            color: #3730a3;
            border-radius: 10px;
            padding: 0.4rem 0.55rem;
            cursor: pointer;
            font-weight: 700;
            line-height: 1.15;
        }
        .cli-verify-btn:hover { filter: brightness(0.98); }
        .cli-verify-btn.has-code {
            background: #ecfdf5;
            border-color: #a7f3d0;
            color: #047857;
        }
        .cli-verify-btn__code {
            font-size: 0.95rem;
            font-weight: 800;
            letter-spacing: 0.08em;
        }
        .cli-verify-btn__hint {
            font-size: 0.65rem;
            font-weight: 700;
            opacity: 0.9;
        }
        .cw-verify-current {
            text-align: center;
            padding: 1rem;
            border-radius: 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            margin-bottom: 1rem;
        }
        .cw-verify-current__code {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: 0.2em;
            color: #000147;
        }
        .cw-verify-history {
            max-height: 240px;
            overflow: auto;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
        }
        .cw-verify-history table {
            width: 100%;
            margin: 0;
            font-size: 0.86rem;
        }
        .cw-verify-history th,
        .cw-verify-history td {
            padding: 0.55rem 0.7rem;
            border-bottom: 1px solid #eef2f7;
            text-align: left;
        }
        .cw-verify-history tr:last-child td { border-bottom: 0; }
        .cw-verify-history .is-current td {
            background: #ecfdf5;
            font-weight: 700;
            color: #047857;
        }
        .cw-msg-review-check {
            margin-top: 0.65rem;
            padding: 0.55rem 0.7rem;
            border-radius: 10px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.86rem;
            font-weight: 650;
            color: #9a3412;
        }
        .cw-msg-review-check.is-done {
            background: #ecfdf5;
            border-color: #a7f3d0;
            color: #047857;
        }
        .cw-msg-review-check input {
            width: 1.05rem;
            height: 1.05rem;
            accent-color: #059669;
            cursor: pointer;
        }
        .adm-act--wa {
            background: #ecfdf5 !important;
            color: #047857 !important;
            border: 1px solid #a7f3d0 !important;
        }
        .adm-act--wa:hover {
            background: #d1fae5 !important;
            color: #065f46 !important;
        }
        .adm-act--wa .fa-whatsapp {
            color: #25d366;
        }
        .cw-msg-modal .modal-content {
            border: 0;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.18);
        }
        .cw-msg-modal .modal-header {
            background: linear-gradient(135deg, #000147 0%, #0b0b5c 100%);
            color: #fff;
            border: 0;
            padding: 1rem 1.25rem;
        }
        .cw-msg-modal .modal-header .close { color: #fff; opacity: .85; text-shadow: none; }
        .cw-msg-modal .cw-msg-layout {
            display: grid;
            grid-template-columns: 240px 1fr;
            min-height: 420px;
        }
        .cw-msg-modal .cw-msg-list {
            border-right: 1px solid #e2e8f0;
            background: #f8fafc;
            padding: .75rem;
            overflow-y: auto;
            max-height: 520px;
        }
        .cw-msg-modal .cw-msg-item {
            display: block;
            width: 100%;
            text-align: left;
            border: 1px solid transparent;
            background: #fff;
            border-radius: 10px;
            padding: .65rem .75rem;
            margin-bottom: .45rem;
            cursor: pointer;
            transition: border-color .15s, box-shadow .15s;
        }
        .cw-msg-modal .cw-msg-item:hover,
        .cw-msg-modal .cw-msg-item.is-active {
            border-color: #93c5fd;
            box-shadow: 0 4px 12px rgba(37, 99, 235, .12);
        }
        .cw-msg-modal .cw-msg-item__title {
            font-size: .86rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }
        .cw-msg-modal .cw-msg-item__cat {
            font-size: .7rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .cw-msg-modal .cw-msg-editor { padding: 1rem 1.15rem; }
        .cw-msg-modal .form-label { font-weight: 650; font-size: .82rem; color: #334155; }
        .cw-msg-modal textarea.form-control { min-height: 220px; font-size: .92rem; line-height: 1.5; }
        .cw-msg-modal .cw-msg-vars {
            font-size: .75rem;
            color: #64748b;
            margin-top: .35rem;
        }
        .cw-msg-modal .cw-msg-vars code {
            background: #e2e8f0;
            border-radius: 4px;
            padding: .05rem .3rem;
            font-size: .72rem;
        }
        .cw-msg-modal .cw-msg-client {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 10px;
            padding: .65rem .8rem;
            margin-bottom: .9rem;
            font-size: .88rem;
        }
        @media (max-width: 768px) {
            .cw-msg-modal .cw-msg-layout { grid-template-columns: 1fr; }
            .cw-msg-modal .cw-msg-list { max-height: 180px; border-right: 0; border-bottom: 1px solid #e2e8f0; }
        }
    </style>
</head>

<body>
<!-- Content Wrapper -->
<div id="content-wrapper" class="d-flex flex-column">
    <!-- Main Content -->
    <div id="content">
        <div class="container-xl my-4 py-4 legacy-touch" style="max-width: 96% !important;">
            
            <!-- Header -->
            <div class="d-flex align-items-center justify-content-between mb-3 fade-in-up flex-wrap" style="gap: .75rem;">
                <div>
                    <h1 class="h2 mb-1" style="color: var(--primary-dark); font-weight: 700; letter-spacing: -0.02em;">Clientes</h1>
                    <p class="text-secondary-custom mb-0" style="font-weight: 500;">Gestión y seguimiento de clientes</p>
                </div>
                <?php if ($sistema === 'conlineweb'): ?>
                <button type="button" class="btn-modern" style="background:#000147;color:#fff;border:none;padding:8px 16px;border-radius:10px;font-weight:600;" onclick="cwMsgOpenManager()">
                    <i class="fas fa-comments"></i> Mensajes y comunicados
                </button>
                <?php endif; ?>
            </div>

            <!-- Selector de sistema -->
            <div class="mb-4 fade-in-up">
                <div class="adm-sistema-switch">
                    <a href="?sistema=conlineweb" class="adm-sistema-switch__link <?php echo $sistema === 'conlineweb' ? 'is-active' : ''; ?>">
                        <i class="fas fa-server"></i> ADM ConlineWeb
                    </a>
                    <a href="?sistema=hostingpro" class="adm-sistema-switch__link <?php echo $sistema === 'hostingpro' ? 'is-active' : ''; ?>">
                        <i class="fas fa-rocket"></i> ADM HostingPro
                    </a>
                    <a href="?sistema=planpro" class="adm-sistema-switch__link <?php echo $sistema === 'planpro' ? 'is-active' : ''; ?>">
                        <i class="fas fa-shopping-cart"></i> Plan Pro
                    </a>
                </div>
            </div>
            
            <!-- Dashboard Cards -->
            <div class="row mb-4 fade-in-up">
                <?php if ($sistema !== 'planpro'): ?>
                <!-- Cards para ADM ConlineWeb / HostingPro -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stat-card p-4" onclick="mostrarTodos()">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number"><?php echo $total_clientes; ?></div>
                                <div class="stat-label mt-1">Clientes Activos</div>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-users fa-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($sistema === 'conlineweb' && $tiene_columna_transferido): ?>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stat-card stat-card--warning p-4" onclick="$('#adm-transferidos-tab').click();">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number"><?php echo $transferencia_totales['clientes']; ?></div>
                                <div class="stat-label mt-1">Transferidos a Hosting Pro</div>
                                <div class="stat-card__meta">
                                    <?php echo $transferencia_totales['dominios']; ?> dom ·
                                    <?php echo $transferencia_totales['hostings']; ?> host ·
                                    <?php echo $transferencia_totales['pagos']; ?> pagos
                                </div>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-exchange-alt fa-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stat-card p-4" onclick="filtrarActualizados()">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number"><?php echo $clientes_30_dias; ?></div>
                                <div class="stat-label mt-1">Actualizados (30 días)</div>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-sync-alt fa-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stat-card p-4" onclick="<?php echo ($sistema === 'conlineweb' && $tiene_columna_transferido) ? 'exportarCSVTransferidos()' : 'exportarCSV()'; ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number">
                                    <i class="fas fa-file-excel fa-2x" style="color: var(--success);"></i>
                                </div>
                                <div class="stat-label mt-1"><?php echo ($sistema === 'conlineweb' && $tiene_columna_transferido) ? 'Exportar transferencias' : 'Exportar Datos'; ?></div>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-download fa-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <!-- Cards para Plan Pro -->
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="stat-card p-4" onclick="$('#cw-activos-tab').click();">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number" style="color: #2563eb;"><?php echo $total_clientes_cw; ?></div>
                                <div class="stat-label mt-1">Total Plan Pro</div>
                            </div>
                            <div class="stat-icon" style="background: #dbeafe; color: #2563eb;">
                                <i class="fas fa-shopping-cart fa-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="stat-card p-4" onclick="$('#cw-activos-tab').click();">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number" style="color: #10b981;"><?php echo count($clientes_cw_activos); ?></div>
                                <div class="stat-label mt-1">Clientes Activos</div>
                            </div>
                            <div class="stat-icon" style="background: #d1fae5; color: #10b981;">
                                <i class="fas fa-user-check fa-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="stat-card p-4" onclick="$('#cw-eliminados-tab').click();">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number" style="color: #f59e0b;"><?php echo count($clientes_cw_eliminados); ?></div>
                                <div class="stat-label mt-1">Clientes Eliminados</div>
                            </div>
                            <div class="stat-icon" style="background: #fef3c7; color: #f59e0b;">
                                <i class="fas fa-user-slash fa-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Tabs modernos -->
            <ul class="nav nav-tabs-modern" id="clientesTabs" role="tablist">
                <?php if ($sistema !== 'planpro'): ?>
                <!-- TABS PARA ADM -->
                <li class="nav-item" role="presentation">
                    <a class="nav-link active" id="adm-activos-tab" data-toggle="tab" href="#adm-activos" role="tab">
                        <i class="fas fa-user-check"></i>Activos
                        <span class="badge bg-success ms-1" style="background: var(--success) !important; color: white;"><?php echo count($clientes_activos); ?></span>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="adm-eliminados-tab" data-toggle="tab" href="#adm-eliminados" role="tab">
                        <i class="fas fa-user-slash"></i>Eliminados
                        <span class="badge bg-danger ms-1" style="background: var(--danger) !important; color: white;"><?php echo count($clientes_eliminados); ?></span>
                    </a>
                </li>
                <?php if ($sistema === 'conlineweb' && $tiene_columna_transferido): ?>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="adm-transferidos-tab" data-toggle="tab" href="#adm-transferidos" role="tab">
                        <i class="fas fa-exchange-alt"></i>Transferidos
                        <span class="badge ms-1" style="background: #f59e0b !important; color: white;"><?php echo count($clientes_transferidos); ?></span>
                    </a>
                </li>
                <?php endif; ?>
                <?php else: ?>
                <!-- TABS PARA PLAN PRO -->
                <li class="nav-item" role="presentation">
                    <a class="nav-link active" id="cw-activos-tab" data-toggle="tab" href="#cw-activos" role="tab">
                        <i class="fas fa-user-check"></i>Activos
                        <span class="badge bg-info ms-1" style="background: var(--info) !important; color: white;"><?php echo count($clientes_cw_activos); ?></span>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="cw-eliminados-tab" data-toggle="tab" href="#cw-eliminados" role="tab">
                        <i class="fas fa-user-slash"></i>Eliminados
                        <span class="badge bg-warning ms-1" style="background: var(--warning) !important; color: white;"><?php echo count($clientes_cw_eliminados); ?></span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            
            <div class="tab-content" id="clientesTabsContent">
                <?php if ($sistema !== 'planpro'): ?>
                <!-- ═══════════════════════════════════════════════════════ -->
                <!-- TAB ADM - ACTIVOS -->
                <!-- ═══════════════════════════════════════════════════════ -->
                <div class="tab-pane fade show active" id="adm-activos" role="tabpanel">
                    <div class="modern-table mt-3">
                        <?php if (count($clientes_activos) > 0): ?>
                        <table class="table" id="dataTableAdmActivos" width="100%" cellspacing="0">
                            <thead>
                                <tr style="background: var(--primary-soft);">
                                    <th><i class="fas fa-hashtag"></i>ID</th>
                                    <th><i class="fas fa-building"></i>Empresa</th>
                                    <th><i class="fas fa-user"></i>Contacto</th>
                                    <th class="cli-count-col"><i class="fas fa-globe"></i>Dominios</th>
                                    <th class="cli-count-col"><i class="fas fa-server"></i>Hostings</th>
                                    <th class="cli-count-col"><i class="fas fa-project-diagram"></i>Proyectos</th>
                                    <th class="cli-count-col"><i class="fas fa-credit-card"></i>Pagos</th>
                                    <th class="cli-count-col"><i class="fab fa-google"></i>Reseña</th>
                                    <th class="cli-count-col"><i class="fas fa-shield-alt"></i>Verificación</th>
                                    <th><i class="fas fa-cog"></i>Acciones</th>
                                  </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clientes_activos as $row):
                                    $cid = (int) $row['id'];
                                    $n_dom = (int) ($conteo_dominios[$cid] ?? 0);
                                    $n_host = (int) ($conteo_hostings[$cid] ?? 0);
                                    $n_proy = (int) ($conteo_proyectos[$cid] ?? 0);
                                    $n_pend = (int) ($conteo_pagos_pend[$cid] ?? 0);
                                    $reviewKey = cw_client_google_review_key($cid, $cwReviewSistema);
                                    $reviewDone = !empty($cwGoogleReviews[$reviewKey]['done']);
                                ?>
                                <tr>
                                    <td><span class="badge-id">#<?php echo $row["id"]; ?></span></td>
                                    <td><strong><?php echo htmlspecialchars($row["empresa"]); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row["nombre_contacto"]); ?></td>
                                    <td class="cli-count-col">
                                        <span class="cli-count cli-count--dom">
                                            <span class="cli-count__num"><?php echo $n_dom; ?></span>
                                            <span class="cli-count__label"><?php echo $n_dom === 1 ? 'dominio' : 'dominios'; ?></span>
                                        </span>
                                    </td>
                                    <td class="cli-count-col">
                                        <span class="cli-count cli-count--host">
                                            <span class="cli-count__num"><?php echo $n_host; ?></span>
                                            <span class="cli-count__label"><?php echo $n_host === 1 ? 'hosting' : 'hostings'; ?></span>
                                        </span>
                                    </td>
                                    <td class="cli-count-col">
                                        <span class="cli-count cli-count--proy">
                                            <span class="cli-count__num"><?php echo $n_proy; ?></span>
                                            <span class="cli-count__label"><?php echo $n_proy === 1 ? 'proyecto' : 'proyectos'; ?></span>
                                        </span>
                                    </td>
                                    <td class="cli-count-col">
                                        <?php if ($n_pend > 0): ?>
                                            <span class="cli-count cli-count--pend" title="<?php echo $n_pend; ?> pago(s) pendiente(s)">
                                                <span class="cli-count__num"><?php echo $n_pend; ?></span>
                                                <span class="cli-count__label"><?php echo $n_pend === 1 ? 'pendiente' : 'pendientes'; ?></span>
                                            </span>
                                        <?php else: ?>
                                            <span class="cli-count cli-count--ok">Sin pendientes</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="cli-count-col">
                                        <div class="cli-review-wrap">
                                            <label class="cli-review <?php echo $reviewDone ? 'is-done' : ''; ?>" title="Marcar si ya dejó reseña en Google">
                                                <input type="checkbox"
                                                    class="cw-google-review-check"
                                                    data-cliente-id="<?php echo $cid; ?>"
                                                    data-sistema="<?php echo htmlspecialchars($cwReviewSistema, ENT_QUOTES, 'UTF-8'); ?>"
                                                    <?php echo $reviewDone ? 'checked' : ''; ?>>
                                                <span class="cli-review__label"><?php echo $reviewDone ? 'Con reseña' : 'Sin reseña'; ?></span>
                                            </label>
                                            <button type="button"
                                                class="cli-review-ask"
                                                title="Solicitar reseña por WhatsApp o correo"
                                                onclick='cwSolicitarResena(<?= json_encode([
                                                    'id' => $cid,
                                                    'nombre' => (string) ($row['nombre_contacto'] ?? ''),
                                                    'empresa' => (string) ($row['empresa'] ?? ''),
                                                    'correo' => (string) ($row['correo'] ?? ''),
                                                    'telefono' => (string) ($row['telefono'] ?? ''),
                                                    'sistema' => (string) $cwReviewSistema,
                                                ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) ?>)'>
                                                <i class="fas fa-paper-plane"></i>Solicitar
                                            </button>
                                        </div>
                                    </td>
                                    <?php
                                    $verifyKey = cw_client_verification_key($cid, $cwReviewSistema);
                                    $verifyRow = $cwVerificationAll[$verifyKey] ?? null;
                                    $verifyCode = is_array($verifyRow) ? ($verifyRow['current'] ?? null) : null;
                                    ?>
                                    <td class="cli-count-col">
                                        <button type="button"
                                            class="cli-verify-btn <?php echo $verifyCode ? 'has-code' : ''; ?>"
                                            data-cliente-id="<?php echo $cid; ?>"
                                            data-sistema="<?php echo htmlspecialchars($cwReviewSistema, ENT_QUOTES, 'UTF-8'); ?>"
                                            title="Historial y envío de código de verificación"
                                            onclick='cwVerifyOpen(<?= json_encode([
                                                'id' => $cid,
                                                'nombre' => (string) ($row['nombre_contacto'] ?? ''),
                                                'empresa' => (string) ($row['empresa'] ?? ''),
                                                'correo' => (string) ($row['correo'] ?? ''),
                                                'sistema' => (string) $cwReviewSistema,
                                            ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) ?>)'>
                                            <span class="cli-verify-btn__code"><?php echo $verifyCode ? htmlspecialchars((string) $verifyCode, ENT_QUOTES, 'UTF-8') : '------'; ?></span>
                                            <span class="cli-verify-btn__hint"><?php echo $verifyCode ? 'Ver historial' : 'Sin código'; ?></span>
                                        </button>
                                    </td>
                                    <td>
                                        <div class="adm-actions">
                                            <button onclick="window.location.href='detalle_cliente.php?id=<?php echo $row['id']; ?>&sistema=<?php echo $sistema; ?>'" class="adm-act adm-act--view">
                                                <i class="fas fa-eye"></i>Ver Info
                                            </button>
                                            <button onclick="eliminarCliente(<?php echo $row['id']; ?>)" class="adm-act adm-act--danger">
                                                <i class="fas fa-trash"></i>Eliminar
                                            </button>
                                            <?php if ($sistema !== 'conlineweb' && $n_pend > 0): ?>
                                            <button type="button"
                                                class="adm-act adm-act--wa"
                                                title="Enviar todos los pagos pendientes por WhatsApp"
                                                onclick="mandarWhatsAppPagosPendientes(<?php echo (int) $row['id']; ?>, '<?php echo htmlspecialchars((string) ($row['telefono'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars((string) ($row['nombre_contacto'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>')">
                                                <i class="fab fa-whatsapp"></i>Pagos pendientes (<?php echo (int) $n_pend; ?>)
                                            </button>
                                            <?php endif; ?>
                                            <?php if ($sistema === 'hostingpro' && !in_array((int) $row['id'], $ids_cw_activos_sin_transferir, true)): ?>
                                            <button type="button" onclick="transferirClienteConlineWeb(<?php echo $row['id']; ?>)" class="adm-act adm-act--warn">
                                                <i class="fas fa-exchange-alt"></i>Transferir a ConlineWeb
                                            </button>
                                            <?php endif; ?>
                                            <?php if ($sistema === 'conlineweb'): ?>
                                            <button onclick="mandarWhatsApp('<?php echo htmlspecialchars($row['telefono'], ENT_QUOTES, 'UTF-8'); ?>', <?php echo $row['id']; ?>)" class="adm-act adm-act--success">
                                                <i class="fab fa-whatsapp"></i>WhatsApp
                                            </button>
                                            <?php if ($n_pend > 0): ?>
                                            <button type="button"
                                                class="adm-act adm-act--wa"
                                                title="Enviar todos los pagos pendientes por WhatsApp"
                                                onclick="mandarWhatsAppPagosPendientes(<?php echo (int) $row['id']; ?>, '<?php echo htmlspecialchars((string) ($row['telefono'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars((string) ($row['nombre_contacto'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>')">
                                                <i class="fab fa-whatsapp"></i>Pagos pendientes (<?php echo (int) $n_pend; ?>)
                                            </button>
                                            <?php endif; ?>
                                            <button type="button"
                                                class="adm-act adm-act--warn"
                                                onclick='cwMsgOpenForClient(<?= json_encode([
                                                    'id' => (int) $row['id'],
                                                    'nombre' => (string) ($row['nombre_contacto'] ?? ''),
                                                    'empresa' => (string) ($row['empresa'] ?? ''),
                                                    'correo' => (string) ($row['correo'] ?? ''),
                                                    'telefono' => (string) ($row['telefono'] ?? ''),
                                                    'sistema' => (string) $cwReviewSistema,
                                                ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) ?>)'>
                                                <i class="fas fa-paper-plane"></i>Mensaje
                                            </button>
                                            <button onclick="enviarAccesos(<?php echo $row['id']; ?>, '<?php echo addslashes($row['correo']); ?>', '<?php echo $row['telefono']; ?>')" class="adm-act adm-act--info">
                                                <i class="fas fa-key"></i>Enviar Accesos
                                            </button>
                                            <?php if (empty($row['transferido'])): ?>
                                            <button type="button" onclick="transferirCliente(<?php echo $row['id']; ?>)" class="adm-act adm-act--warn">
                                                <i class="fas fa-exchange-alt"></i>Transferir
                                            </button>
                                            <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-secondary-custom">No se encontraron clientes activos.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- ═══════════════════════════════════════════════════════ -->
                <!-- TAB ADM - ELIMINADOS -->
                <!-- ═══════════════════════════════════════════════════════ -->
                <div class="tab-pane fade" id="adm-eliminados" role="tabpanel">
                    <div class="modern-table mt-3">
                        <?php if (count($clientes_eliminados) > 0): ?>
                        <table class="table" id="dataTableAdmEliminados" width="100%" cellspacing="0">
                            <thead>
                                <tr style="background: var(--primary-soft);">
                                    <th><i class="fas fa-hashtag"></i>ID</th>
                                    <th><i class="fas fa-building"></i>Empresa</th>
                                    <th><i class="fas fa-user"></i>Contacto</th>
                                    <th><i class="fas fa-envelope"></i>Correo</th>
                                    <th><i class="fas fa-calendar"></i>Fecha Eliminación</th>
                                    <th><i class="fas fa-cog"></i>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clientes_eliminados as $row): ?>
                                <tr style="opacity: 0.8;">
                                    <td><span class="badge-id">#<?php echo $row["id"]; ?></span></td>
                                    <td><strong><?php echo htmlspecialchars($row["empresa"]); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row["nombre_contacto"]); ?></td>
                                    <td><i class="fas fa-envelope"></i><?php echo htmlspecialchars($row["correo"]); ?></td>
                                    <td><?php echo $row["actualizado"]; ?></td>
                                    <td>
                                        <div class="adm-actions">
                                            <button onclick="restaurarCliente(<?php echo $row['id']; ?>)" class="adm-act adm-act--success">
                                                <i class="fas fa-undo"></i>Restaurar
                                            </button>
                                            <button onclick="enviarAccesos(<?php echo $row['id']; ?>, '<?php echo addslashes($row['correo']); ?>', '<?php echo $row['telefono']; ?>')" class="adm-act adm-act--info">
                                                <i class="fas fa-key"></i>Enviar Accesos
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
                            <p class="text-secondary-custom">No se encontraron clientes eliminados.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($sistema === 'conlineweb' && $tiene_columna_transferido): ?>
                <!-- TAB ADM - TRANSFERIDOS -->
                <div class="tab-pane fade" id="adm-transferidos" role="tabpanel">
                    <div class="alert mt-3 mb-3" style="background:linear-gradient(135deg,#fffbeb 0%,#fef3c7 100%);border:1px solid #f59e0b;border-radius:12px;padding:16px;">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <div>
                                <strong style="color:#92400e;"><i class="fas fa-chart-bar"></i> Reporte de transferencias ConlineWeb → Hosting Pro</strong>
                                <p style="margin:6px 0 0;color:#78350f;font-size:13px;">
                                    <?php echo $transferencia_totales['clientes']; ?> clientes ·
                                    <?php echo $transferencia_totales['dominios']; ?> dominios ·
                                    <?php echo $transferencia_totales['hostings']; ?> hostings ·
                                    <?php echo $transferencia_totales['pagos']; ?> pagos migrados
                                </p>
                            </div>
                            <button type="button" onclick="exportarCSVTransferidos()" class="btn-modern" style="background:#d97706;color:#fff;border:none;padding:8px 18px;border-radius:10px;font-weight:600;">
                                <i class="fas fa-file-csv"></i> Descargar reporte CSV
                            </button>
                        </div>
                    </div>
                    <div class="modern-table mt-3">
                        <?php if (count($clientes_transferidos) > 0): ?>
                        <table class="table" id="dataTableAdmTransferidos" width="100%" cellspacing="0">
                            <thead>
                                <tr style="background: #fef3c7;">
                                    <th><i class="fas fa-hashtag"></i>ID</th>
                                    <th><i class="fas fa-building"></i>Empresa</th>
                                    <th><i class="fas fa-user"></i>Contacto</th>
                                    <th><i class="fas fa-envelope"></i>Correo</th>
                                    <th><i class="fas fa-globe"></i>Dom.</th>
                                    <th><i class="fas fa-server"></i>Host.</th>
                                    <th><i class="fas fa-credit-card"></i>Pagos</th>
                                    <th><i class="fas fa-user-shield"></i>Responsable</th>
                                    <th><i class="fas fa-calendar"></i>Fecha</th>
                                    <th><i class="fas fa-cog"></i>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clientes_transferidos as $row):
                                    $log = $reporte_transferencias[(int) $row['id']] ?? null;
                                ?>
                                <tr>
                                    <td>
                                        <span class="badge-id">#<?php echo $row["id"]; ?></span>
                                        <span class="badge ms-1" style="background:#f59e0b;color:#fff;">Transferido</span>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($row["empresa"]); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row["nombre_contacto"]); ?></td>
                                    <td><?php echo htmlspecialchars($row["correo"]); ?></td>
                                    <td><span class="badge" style="background:#dbeafe;color:#1e40af;"><?php echo (int) ($log['dominios_transferidos'] ?? 0); ?></span></td>
                                    <td><span class="badge" style="background:#d1fae5;color:#065f46;"><?php echo (int) ($log['hostings_transferidos'] ?? 0); ?></span></td>
                                    <td><span class="badge" style="background:#ede9fe;color:#5b21b6;"><?php echo (int) ($log['pagos_transferidos'] ?? 0); ?></span></td>
                                    <td><?php echo htmlspecialchars($log['responsable'] ?? ('Usuario #' . ($row['usuario_transferencia'] ?? '-'))); ?></td>
                                    <td><?php echo htmlspecialchars($log['fecha_transferencia'] ?? $row['fecha_transferencia'] ?? '-'); ?></td>
                                    <td>
                                        <div class="adm-actions">
                                            <button onclick="window.location.href='detalle_cliente.php?id=<?php echo $row['id']; ?>&sistema=conlineweb'" class="adm-act adm-act--view">
                                                <i class="fas fa-eye"></i>Auditoría
                                            </button>
                                            <button type="button" onclick="window.location.href='detalle_cliente.php?id=<?php echo $row['id']; ?>&sistema=hostingpro'" class="adm-act adm-act--hosting">
                                                <i class="fas fa-rocket"></i>HostingPro
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-exchange-alt fa-3x text-muted mb-3"></i>
                            <p class="text-secondary-custom">No hay clientes transferidos a Hosting Pro.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php else: ?>
                
                <!-- ═══════════════════════════════════════════════════════ -->
                <!-- TAB CONLINEWEB PLAN PRO - ACTIVOS -->
                <!-- ═══════════════════════════════════════════════════════ -->
                <div class="tab-pane fade show active" id="cw-activos" role="tabpanel">
                    <div class="alert" style="background: linear-gradient(135deg, #e0f2fe 0%, #dbeafe 100%); border: 1px solid #60a5fa; border-radius: 12px; padding: 16px; margin-bottom: 20px;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <i class="fas fa-shopping-cart" style="color: #2563eb; font-size: 20px;"></i>
                            <div>
                                <strong style="color: #1e40af;">Clientes de ConlineWeb Plan Pro</strong>
                                <p style="margin: 4px 0 0 0; color: #1e3a8a; font-size: 13px;">Usuarios que se registraron comprando planes en <a href="https://conlineweb.com/identificacion.php" target="_blank" style="color: #1e40af; font-weight: 600;">conlineweb.com/identificacion.php</a></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modern-table mt-3">
                        <?php if (count($clientes_cw_activos) > 0): ?>
                        <table class="table" id="dataTableCwActivos" width="100%" cellspacing="0">
                            <thead>
                                <tr style="background: var(--primary-soft);">
                                    <th><i class="fas fa-hashtag"></i>ID</th>
                                    <th><i class="fas fa-building"></i>Empresa</th>
                                    <th><i class="fas fa-user"></i>Contacto</th>
                                    <th class="cli-count-col"><i class="fas fa-globe"></i>Dominios</th>
                                    <th class="cli-count-col"><i class="fas fa-server"></i>Hostings</th>
                                    <th class="cli-count-col"><i class="fas fa-project-diagram"></i>Proyectos</th>
                                    <th class="cli-count-col"><i class="fas fa-credit-card"></i>Pagos</th>
                                    <th class="cli-count-col"><i class="fab fa-google"></i>Reseña</th>
                                    <th class="cli-count-col"><i class="fas fa-shield-alt"></i>Verificación</th>
                                    <th><i class="fas fa-cog"></i>Acciones</th>
                                  </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clientes_cw_activos as $row):
                                    $cid = (int) $row['id'];
                                    $n_dom = (int) ($conteo_dominios_cw[$cid] ?? 0);
                                    $n_host = (int) ($conteo_hostings_cw[$cid] ?? 0);
                                    $n_proy = (int) ($conteo_proyectos_cw[$cid] ?? 0);
                                    $n_pend = (int) ($conteo_pagos_pend_cw[$cid] ?? 0);
                                    $reviewKeyCw = cw_client_google_review_key($cid, 'planpro');
                                    $reviewDoneCw = !empty($cwGoogleReviews[$reviewKeyCw]['done']);
                                ?>
                                <tr>
                                    <td><span class="badge-id" style="background: #dbeafe; color: #1e40af;">#CW-<?php echo $row["id"]; ?></span></td>
                                    <td><strong><?php echo htmlspecialchars($row["empresa"]); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row["nombre_contacto"]); ?></td>
                                    <td class="cli-count-col">
                                        <span class="cli-count cli-count--dom">
                                            <span class="cli-count__num"><?php echo $n_dom; ?></span>
                                            <span class="cli-count__label"><?php echo $n_dom === 1 ? 'dominio' : 'dominios'; ?></span>
                                        </span>
                                    </td>
                                    <td class="cli-count-col">
                                        <span class="cli-count cli-count--host">
                                            <span class="cli-count__num"><?php echo $n_host; ?></span>
                                            <span class="cli-count__label"><?php echo $n_host === 1 ? 'hosting' : 'hostings'; ?></span>
                                        </span>
                                    </td>
                                    <td class="cli-count-col">
                                        <span class="cli-count cli-count--proy">
                                            <span class="cli-count__num"><?php echo $n_proy; ?></span>
                                            <span class="cli-count__label"><?php echo $n_proy === 1 ? 'proyecto' : 'proyectos'; ?></span>
                                        </span>
                                    </td>
                                    <td class="cli-count-col">
                                        <?php if ($n_pend > 0): ?>
                                            <span class="cli-count cli-count--pend" title="<?php echo $n_pend; ?> pago(s) pendiente(s)">
                                                <span class="cli-count__num"><?php echo $n_pend; ?></span>
                                                <span class="cli-count__label"><?php echo $n_pend === 1 ? 'pendiente' : 'pendientes'; ?></span>
                                            </span>
                                        <?php else: ?>
                                            <span class="cli-count cli-count--ok">Sin pendientes</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="cli-count-col">
                                        <div class="cli-review-wrap">
                                            <label class="cli-review <?php echo $reviewDoneCw ? 'is-done' : ''; ?>" title="Marcar si ya dejó reseña en Google">
                                                <input type="checkbox"
                                                    class="cw-google-review-check"
                                                    data-cliente-id="<?php echo $cid; ?>"
                                                    data-sistema="planpro"
                                                    <?php echo $reviewDoneCw ? 'checked' : ''; ?>>
                                                <span class="cli-review__label"><?php echo $reviewDoneCw ? 'Con reseña' : 'Sin reseña'; ?></span>
                                            </label>
                                            <button type="button"
                                                class="cli-review-ask"
                                                title="Solicitar reseña por WhatsApp o correo"
                                                onclick='cwSolicitarResena(<?= json_encode([
                                                    'id' => $cid,
                                                    'nombre' => (string) ($row['nombre_contacto'] ?? ''),
                                                    'empresa' => (string) ($row['empresa'] ?? ''),
                                                    'correo' => (string) ($row['correo'] ?? ''),
                                                    'telefono' => (string) ($row['telefono'] ?? ''),
                                                    'sistema' => 'planpro',
                                                ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) ?>)'>
                                                <i class="fas fa-paper-plane"></i>Solicitar
                                            </button>
                                        </div>
                                    </td>
                                    <?php
                                    $verifyKeyCw = cw_client_verification_key($cid, 'planpro');
                                    $verifyRowCw = $cwVerificationAll[$verifyKeyCw] ?? null;
                                    $verifyCodeCw = is_array($verifyRowCw) ? ($verifyRowCw['current'] ?? null) : null;
                                    ?>
                                    <td class="cli-count-col">
                                        <button type="button"
                                            class="cli-verify-btn <?php echo $verifyCodeCw ? 'has-code' : ''; ?>"
                                            data-cliente-id="<?php echo $cid; ?>"
                                            data-sistema="planpro"
                                            title="Historial y envío de código de verificación"
                                            onclick='cwVerifyOpen(<?= json_encode([
                                                'id' => $cid,
                                                'nombre' => (string) ($row['nombre_contacto'] ?? ''),
                                                'empresa' => (string) ($row['empresa'] ?? ''),
                                                'correo' => (string) ($row['correo'] ?? ''),
                                                'sistema' => 'planpro',
                                            ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) ?>)'>
                                            <span class="cli-verify-btn__code"><?php echo $verifyCodeCw ? htmlspecialchars((string) $verifyCodeCw, ENT_QUOTES, 'UTF-8') : '------'; ?></span>
                                            <span class="cli-verify-btn__hint"><?php echo $verifyCodeCw ? 'Ver historial' : 'Sin código'; ?></span>
                                        </button>
                                    </td>
                                    <td>
                                        <div class="adm-actions">
                                            <button onclick="window.location.href='detalle_cliente.php?id=<?php echo $row['id']; ?>&sistema=<?php echo $sistema; ?>'" class="adm-act adm-act--view">
                                                <i class="fas fa-eye"></i>Ver Info
                                            </button>
                                            <button onclick="eliminarCliente(<?php echo $row['id']; ?>)" class="adm-act adm-act--danger">
                                                <i class="fas fa-trash"></i>Eliminar
                                            </button>
                                            <?php if ($n_pend > 0): ?>
                                            <button type="button"
                                                class="adm-act adm-act--wa"
                                                title="Enviar todos los pagos pendientes por WhatsApp"
                                                onclick="mandarWhatsAppPagosPendientes(<?php echo (int) $row['id']; ?>, '<?php echo htmlspecialchars((string) ($row['telefono'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars((string) ($row['nombre_contacto'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>')">
                                                <i class="fab fa-whatsapp"></i>Pagos pendientes (<?php echo (int) $n_pend; ?>)
                                            </button>
                                            <?php endif; ?>
                                            <button onclick="window.open('https://conlineweb.com/plan_pro.php', '_blank')" class="adm-act adm-act--info">
                                                <i class="fas fa-external-link-alt"></i>Ver en Plan Pro
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
                            <p class="text-secondary-custom">No se encontraron clientes activos en Plan Pro.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- ═══════════════════════════════════════════════════════ -->
                <!-- TAB CONLINEWEB PLAN PRO - ELIMINADOS -->
                <!-- ═══════════════════════════════════════════════════════ -->
                <div class="tab-pane fade" id="cw-eliminados" role="tabpanel">
                    <div class="alert" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border: 1px solid #fbbf24; border-radius: 12px; padding: 16px; margin-bottom: 20px;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <i class="fas fa-exclamation-triangle" style="color: #d97706; font-size: 20px;"></i>
                            <div>
                                <strong style="color: #92400e;">Clientes Eliminados de Plan Pro</strong>
                                <p style="margin: 4px 0 0 0; color: #78350f; font-size: 13px;">Usuarios que compraron planes en conlineweb.com y fueron eliminados</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modern-table mt-3">
                        <?php if (count($clientes_cw_eliminados) > 0): ?>
                        <table class="table" id="dataTableCwEliminados" width="100%" cellspacing="0">
                            <thead>
                                <tr style="background: var(--primary-soft);">
                                    <th><i class="fas fa-hashtag"></i>ID</th>
                                    <th><i class="fas fa-building"></i>Empresa</th>
                                    <th><i class="fas fa-user"></i>Contacto</th>
                                    <th><i class="fas fa-envelope"></i>Correo</th>
                                    <th><i class="fas fa-calendar"></i>Fecha Eliminación</th>
                                    <th><i class="fas fa-cog"></i>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clientes_cw_eliminados as $row): ?>
                                <tr style="opacity: 0.8;">
                                    <td><span class="badge-id" style="background: #fed7aa; color: #92400e;">#CW-<?php echo $row["id"]; ?></span></td>
                                    <td><strong><?php echo htmlspecialchars($row["empresa"]); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row["nombre_contacto"]); ?></td>
                                    <td><i class="fas fa-envelope"></i><?php echo htmlspecialchars($row["correo"]); ?></td>
                                    <td><?php echo $row["actualizado"]; ?></td>
                                    <td>
                                        <div class="adm-actions">
                                            <button onclick="window.location.href='detalle_cliente.php?id=<?php echo $row['id']; ?>&sistema=<?php echo $sistema; ?>'" class="adm-act adm-act--view">
                                                <i class="fas fa-eye"></i>Ver Info
                                            </button>
                                            <button onclick="restaurarCliente(<?php echo $row['id']; ?>)" class="adm-act adm-act--success">
                                                <i class="fas fa-undo"></i>Restaurar
                                            </button>
                                            <button onclick="window.open('https://conlineweb.com/plan_pro.php', '_blank')" class="adm-act adm-act--info">
                                                <i class="fas fa-external-link-alt"></i>Ver en Plan Pro
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
                            <p class="text-secondary-custom">No se encontraron clientes eliminados en Plan Pro.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal verificación de cuenta -->
<div class="modal fade" id="cwVerifyModal" tabindex="-1" role="dialog" aria-labelledby="cwVerifyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border:0;border-radius:16px;overflow:hidden;">
            <div class="modal-header" style="background:linear-gradient(135deg,#000147 0%,#0b0b5c 100%);color:#fff;border:0;">
                <h5 class="modal-title" id="cwVerifyModalLabel"><i class="fas fa-shield-alt mr-2"></i> Código de verificación</h5>
                <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Cerrar" style="color:#fff;opacity:.85;text-shadow:none;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="cwVerifyClientBox" class="mb-3" style="font-size:.9rem;color:#475569;"></div>
                <div class="cw-verify-current">
                    <div style="font-size:.75rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.35rem;">Código actual</div>
                    <div class="cw-verify-current__code" id="cwVerifyCurrentCode">------</div>
                    <div style="font-size:.78rem;color:#64748b;margin-top:.35rem;" id="cwVerifyCurrentMeta">Aún no se ha enviado un código</div>
                </div>
                <div style="font-size:.8rem;font-weight:700;color:#64748b;margin-bottom:.4rem;">Historial de envíos</div>
                <div class="cw-verify-history">
                    <table>
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Enviado</th>
                                <th>Correo</th>
                            </tr>
                        </thead>
                        <tbody id="cwVerifyHistoryBody">
                            <tr><td colspan="3" class="text-muted">Sin envíos todavía</td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="mb-0 mt-3" style="font-size:.78rem;color:#94a3b8;">
                    Cada envío genera un código nuevo. Solo el código actual es el vigente.
                </p>
            </div>
            <div class="modal-footer" style="border-top:1px solid #e2e8f0;">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" id="cwVerifySendBtn" onclick="cwVerifySend()">
                    <i class="fas fa-envelope"></i> Generar y enviar por correo
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal mensajes / comunicados -->
<div class="modal fade cw-msg-modal" id="cwMsgModal" tabindex="-1" role="dialog" aria-labelledby="cwMsgModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cwMsgModalLabel"><i class="fas fa-comments mr-2"></i> Mensajes y comunicados</h5>
                <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <div class="cw-msg-layout">
                    <aside class="cw-msg-list">
                        <button type="button" class="btn btn-sm btn-outline-primary btn-block mb-2" onclick="cwMsgNewTemplate()">
                            <i class="fas fa-plus"></i> Nueva plantilla
                        </button>
                        <div id="cwMsgTemplateList"></div>
                    </aside>
                    <div class="cw-msg-editor">
                        <div id="cwMsgClientBox" class="cw-msg-client d-none"></div>
                        <div id="cwMsgReviewCheckWrap" class="cw-msg-review-check d-none">
                            <input type="checkbox" id="cwMsgReviewDone">
                            <label for="cwMsgReviewDone" id="cwMsgReviewDoneLabel" class="mb-0" style="cursor:pointer;">
                                Sin reseña
                            </label>
                        </div>
                        <div class="form-group mb-2">
                            <label class="form-label" for="cwMsgTitle">Título</label>
                            <input type="text" class="form-control" id="cwMsgTitle" placeholder="Ej. Solicitar reseña Google">
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6 mb-2">
                                <label class="form-label" for="cwMsgCategory">Categoría</label>
                                <input type="text" class="form-control" id="cwMsgCategory" placeholder="reseñas, comunicados…">
                            </div>
                            <div class="form-group col-md-6 mb-2">
                                <label class="form-label" for="cwMsgReviewUrl">Link reseñas Google Business</label>
                                <input type="url" class="form-control" id="cwMsgReviewUrl" placeholder="https://g.page/r/.../review">
                            </div>
                        </div>
                        <div class="form-group mb-2">
                            <label class="form-label" for="cwMsgBody">Mensaje</label>
                            <textarea class="form-control" id="cwMsgBody" placeholder="Redacta el comunicado… Variables: {nombre} {empresa} {correo} {telefono} {link_resenas}"></textarea>
                            <small class="text-muted d-block mt-1">Tip: evita emojis; en WhatsApp de PC suelen verse mal al abrir el enlace.</small>
                            <p class="cw-msg-vars mb-0">Variables: <code>{nombre}</code> <code>{empresa}</code> <code>{correo}</code> <code>{telefono}</code> <code>{link_resenas}</code></p>
                        </div>
                        <input type="hidden" id="cwMsgId" value="">
                    </div>
                </div>
            </div>
            <div class="modal-footer flex-wrap" style="gap:.4rem;">
                <button type="button" class="btn btn-outline-danger mr-auto" id="cwMsgDeleteBtn" onclick="cwMsgDeleteTemplate()">
                    <i class="fas fa-trash"></i> Eliminar
                </button>
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" onclick="cwMsgSaveTemplate()">
                    <i class="fas fa-save"></i> Guardar
                </button>
                <button type="button" class="btn btn-success" id="cwMsgSendBtn" onclick="cwMsgSendWhatsApp()">
                    <i class="fab fa-whatsapp"></i> Enviar por WhatsApp
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap core JavaScript-->
<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="vendor/jquery-easing/jquery.easing.min.js"></script>
<script src="js/sb-admin-2.min.js"></script>
<script src="vendor/datatables/jquery.dataTables.min.js"></script>
<script src="vendor/datatables/dataTables.bootstrap4.min.js"></script>
<script src="js/admin-datatables-defaults.js?v=20250714"></script>
<script src="js/demo/datatables-demo.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
var clientesData = <?php echo json_encode($clientes); ?>;
var clientesCwData = <?php echo json_encode($clientes_cw); ?>;
var reporteTransferencias = <?php echo json_encode(array_values($reporte_transferencias), JSON_UNESCAPED_UNICODE); ?>;
var sistemaActivo = '<?php echo $sistema; ?>';
var dataTableAdmActivos = null;
var dataTableAdmEliminados = null;
var dataTableAdmTransferidos = null;
var dataTableCwActivos = null;
var dataTableCwEliminados = null;

$(document).ready(function() {
    // Inicializar DataTables
    function initDataTables() {
        // ADM - Activos
        if ($('#dataTableAdmActivos').length && !$.fn.DataTable.isDataTable('#dataTableAdmActivos')) {
            dataTableAdmActivos = $('#dataTableAdmActivos').DataTable({
                searching: true,
                language: {
                    search: "Buscar:",
                    searchPlaceholder: "Ingrese término...",
                    lengthMenu: "Mostrar _MENU_ registros",
                    info: "Mostrando _START_ a _END_ de _TOTAL_ clientes",
                    paginate: {
                        first: "Primero",
                        last: "Último",
                        next: "Siguiente",
                        previous: "Anterior"
                    }
                },
                pageLength: 25,
                order: [[0, 'desc']]
            });
        }
        
        // ADM - Eliminados
        if ($('#dataTableAdmEliminados').length && !$.fn.DataTable.isDataTable('#dataTableAdmEliminados')) {
            dataTableAdmEliminados = $('#dataTableAdmEliminados').DataTable({
                searching: true,
                language: {
                    search: "Buscar:",
                    searchPlaceholder: "Ingrese término...",
                    lengthMenu: "Mostrar _MENU_ registros",
                    info: "Mostrando _START_ a _END_ de _TOTAL_ clientes"
                },
                pageLength: 25,
                order: [[0, 'desc']]
            });
        }

        if ($('#dataTableAdmTransferidos').length && !$.fn.DataTable.isDataTable('#dataTableAdmTransferidos')) {
            dataTableAdmTransferidos = $('#dataTableAdmTransferidos').DataTable({
                searching: true,
                language: {
                    search: "Buscar:",
                    searchPlaceholder: "Ingrese término...",
                    lengthMenu: "Mostrar _MENU_ registros",
                    info: "Mostrando _START_ a _END_ de _TOTAL_ clientes"
                },
                pageLength: 25,
                order: [[4, 'desc']]
            });
        }
        
        // CONLINEWEB - Activos
        if ($('#dataTableCwActivos').length && !$.fn.DataTable.isDataTable('#dataTableCwActivos')) {
            dataTableCwActivos = $('#dataTableCwActivos').DataTable({
                searching: true,
                language: {
                    search: "Buscar:",
                    searchPlaceholder: "Ingrese término...",
                    lengthMenu: "Mostrar _MENU_ registros",
                    info: "Mostrando _START_ a _END_ de _TOTAL_ clientes Plan Pro",
                    paginate: {
                        first: "Primero",
                        last: "Último",
                        next: "Siguiente",
                        previous: "Anterior"
                    }
                },
                pageLength: 25,
                order: [[0, 'desc']]
            });
        }
        
        // CONLINEWEB - Eliminados
        if ($('#dataTableCwEliminados').length && !$.fn.DataTable.isDataTable('#dataTableCwEliminados')) {
            dataTableCwEliminados = $('#dataTableCwEliminados').DataTable({
                searching: true,
                language: {
                    search: "Buscar:",
                    searchPlaceholder: "Ingrese término...",
                    lengthMenu: "Mostrar _MENU_ registros",
                    info: "Mostrando _START_ a _END_ de _TOTAL_ clientes Plan Pro"
                },
                pageLength: 25,
                order: [[0, 'desc']]
            });
        }
    }
    
    initDataTables();
    initDataTables();
    
    // Inicializar tabs
    $('#clientesTabs a').on('click', function (e) {
        e.preventDefault();
        $(this).tab('show');
    });
    
    // Reajustar DataTables al cambiar de tab
    $('#clientesTabs').on('shown.bs.tab', function (e) {
        if (dataTableAdmActivos) dataTableAdmActivos.columns.adjust();
        if (dataTableAdmEliminados) dataTableAdmEliminados.columns.adjust();
        if (dataTableAdmTransferidos) dataTableAdmTransferidos.columns.adjust();
        if (dataTableCwActivos) dataTableCwActivos.columns.adjust();
        if (dataTableCwEliminados) dataTableCwEliminados.columns.adjust();
    });

    $(window).on('resize.admDt', function () {
        [dataTableAdmActivos, dataTableAdmEliminados, dataTableAdmTransferidos, dataTableCwActivos, dataTableCwEliminados].forEach(function (dt) {
            if (dt) dt.columns.adjust();
        });
    });
});

// Funciones de filtro rápido
function mostrarTodos() {
    $('#adm-activos-tab').tab('show');
    setTimeout(function() {
        if (dataTableAdmActivos) dataTableAdmActivos.search('').draw();
        Swal.fire('Mostrando todos', 'Se muestran todos los clientes de ADM', 'success');
    }, 100);
}

function filtrarActualizados() {
    $('#adm-activos-tab').tab('show');
    setTimeout(function() {
        const hoy = new Date();
        const hace30Dias = new Date();
        hace30Dias.setDate(hoy.getDate() - 30);
        
        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            const fechaStr = data[5];
            if (!fechaStr || fechaStr === '-') return false;
            const partes = fechaStr.split('-');
            if (partes.length !== 3) return false;
            const fecha = new Date(partes[0], partes[1] - 1, partes[2]);
            return fecha >= hace30Dias && fecha <= hoy;
        });
        dataTableAdmActivos.draw();
        $.fn.dataTable.ext.search.pop();
        Swal.fire('Filtro aplicado', 'Mostrando clientes actualizados en los últimos 30 días', 'success');
    }, 100);
}

function exportarCSV() {
    if (!dataTableAdmActivos) return;
    const data = dataTableAdmActivos.rows().data();
    let csv = "ID,Empresa,Contacto,Correo,Teléfono,Última Actualización\n";
    
    for (let i = 0; i < data.length; i++) {
        const row = data[i];
        const id = row[0].replace(/[^0-9]/g, '');
        csv += `"${id}","${row[1]}","${row[2]}","${row[3].replace(/<[^>]*>/g, '').trim()}","${row[4].replace(/<[^>]*>/g, '').trim()}","${row[5]}"\n`;
    }
    
    const blob = new Blob(["\uFEFF" + csv], { type: "text/csv;charset=utf-8;" });
    const link = document.createElement("a");
    const url = URL.createObjectURL(blob);
    link.setAttribute("href", url);
    link.setAttribute("download", "clientes_exportacion_" + new Date().toISOString().slice(0,10) + ".csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
    
    Swal.fire('Exportación completada', 'El archivo CSV ha sido descargado', 'success');
}

function exportarCSVTransferidos() {
    if (!reporteTransferencias.length) {
        Swal.fire('Sin datos', 'Aún no hay transferencias registradas para exportar.', 'info');
        return;
    }

    let csv = 'ID Cliente,Empresa,Contacto,Correo,Telefono,Dominios,Hostings,Pagos,Responsable,Fecha transferencia,Destino\n';

    reporteTransferencias.forEach(function(row) {
        const esc = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`;
        csv += [
            esc(row.cliente_id),
            esc(row.empresa),
            esc(row.nombre_contacto),
            esc(row.correo),
            esc(row.telefono),
            esc(row.dominios_transferidos),
            esc(row.hostings_transferidos),
            esc(row.pagos_transferidos),
            esc(row.responsable),
            esc(row.fecha_transferencia),
            esc(row.destino_sistema)
        ].join(',') + '\n';
    });

    const blob = new Blob(["\uFEFF" + csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'reporte_transferencias_hostingpro_' + new Date().toISOString().slice(0, 10) + '.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(link.href);

    Swal.fire('Reporte listo', 'CSV de transferencias descargado para tu jefe.', 'success');
}

// ===== FUNCIONES ORIGINALES - SIN MODIFICAR =====

function eliminarCliente(id) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: "¡No podrás revertir esto!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'eliminar_cliente.php',
                type: 'POST',
                data: { id: id, sistema: sistemaActivo },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire('¡Eliminado!', 'El cliente ha sido eliminado.', 'success').then(() => {
                            location.href = 'clientes.php?sistema=' + sistemaActivo;
                        });
                    } else {
                        Swal.fire('Error', 'No se pudo eliminar el cliente.', 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Ocurrió un error en la petición.', 'error');
                }
            });
        }
    });
}

function restaurarCliente(id) {
    Swal.fire({
        title: '¿Restaurar cliente?',
        text: "¿Deseas activar este cliente de nuevo?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, restaurar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'eliminar_cliente.php',
                type: 'POST',
                data: { id: id, restaurar: 1, sistema: sistemaActivo },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire('¡Restaurado!', 'El cliente ha sido activado.', 'success').then(() => {
                            location.href = 'clientes.php?sistema=' + sistemaActivo;
                        });
                    } else {
                        Swal.fire('Error', 'No se pudo restaurar el cliente.', 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Ocurrió un error en la petición.', 'error');
                }
            });
        }
    });
}

function enviarAccesos(clienteId, correo, telefono) {
    Swal.fire({
        title: '¿Enviar accesos al portal?',
        html: `Se enviarán los accesos por correo a:<br><strong>${correo}</strong><br><br>¿Deseas continuar?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#17a2b8',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, enviar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Enviando...',
                html: 'Por favor espera mientras enviamos los accesos',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            $.ajax({
                url: 'enviar_accesos.php',
                type: 'POST',
                data: { cliente_id: clienteId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            title: '¡Correo enviado!',
                            html: `Los accesos han sido enviados al correo:<br><strong>${correo}</strong><br><br>¿Deseas enviar también por WhatsApp?`,
                            icon: 'success',
                            showCancelButton: true,
                            confirmButtonColor: '#28a745',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: 'Sí, enviar WhatsApp',
                            cancelButtonText: 'No, gracias'
                        }).then((whatsappResult) => {
                            if (whatsappResult.isConfirmed) {
                                enviarAccesosWhatsApp(telefono, response.usuario, response.contrasena, clienteId);
                            }
                        });
                    } else {
                        Swal.fire('Error', response.message || 'No se pudieron enviar los accesos.', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire('Error', 'Ocurrió un error en la petición: ' + error, 'error');
                }
            });
        }
    });
}

function enviarAccesosWhatsApp(telefono, usuario, contrasena, clienteId) {
    let numero = telefono.replace(/[\s\-\(\)\+]/g, '');
    if (numero.startsWith('00')) {
        numero = numero.substring(2);
    }
    if (/^\d{10}$/.test(numero)) {
        numero = '52' + numero;
    }
    if (numero.length < 10 || numero.length > 15) {
        Swal.fire('Número inválido', 'El número de WhatsApp no tiene el formato adecuado.', 'error');
        return;
    }
    const mensaje = `Hola, te compartimos tus accesos al portal de cliente de ConlineWeb:

Portal de Cliente: https://cliente.conlineweb.com/

Usuario: ${usuario}
Contraseña: ${contrasena}

Desde el portal podrás cambiar tu contraseña en cualquier momento desde la opción "Restablecer Contraseña".

IMPORTANTE: Para solicitar cambios o dar seguimiento a tu proyecto de página web y otros proyectos, te recomendamos usar siempre el portal de cliente como canal principal.

¡Gracias por tu confianza en ConlineWeb!`;
    const url = `https://wa.me/${numero}?text=${encodeURIComponent(mensaje)}`;
    window.open(url, '_blank');
    Swal.fire('WhatsApp abierto', 'Se ha abierto WhatsApp con el mensaje de accesos', 'success');
}

function transferirCliente(id) {
    Swal.fire({
        title: '¿Transferir cliente?',
        html: `Se transferirá el cliente <strong>#${id}</strong> de <strong>ConlineWeb</strong> a <strong>HostingPro</strong>.<br><br>
               Se incluirán: login, datos del cliente, hostings, dominios e historial de pagos.<br>
               El ID del cliente se conservará en ambos sistemas.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#f59e0b',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, transferir',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Transfiriendo...', html: 'Por favor espera', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
            $.ajax({
                url: 'transferir_cliente.php',
                type: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({ icon: 'success', title: '¡Transferido!', text: response.message }).then(() => location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: response.message });
                    }
                },
                error: function() {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Error al conectar con el servidor' });
                }
            });
        }
    });
}

function transferirClienteConlineWeb(id) {
    Swal.fire({
        title: '¿Transferir a ConlineWeb?',
        html: `Se transferirá el cliente <strong>#${id}</strong> de <strong>HostingPro</strong> a <strong>ConlineWeb</strong>.<br><br>
               Se incluirán: login, datos del cliente, hostings, dominios e historial de pagos.<br>
               El ID del cliente se conservará en ambos sistemas.<br>
               El cliente quedará activo en ConlineWeb y se marcará como eliminado en Hosting Pro.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#000147',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, transferir',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Transfiriendo...', html: 'Por favor espera', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
            $.ajax({
                url: 'transferir_cliente_conlineweb.php',
                type: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({ icon: 'success', title: '¡Transferido!', text: response.message }).then(() => location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: response.message });
                    }
                },
                error: function() {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Error al conectar con el servidor' });
                }
            });
        }
    });
}

function mandarWhatsApp(telefono, id) {
    let numero = telefono.replace(/[\s\-\(\)\+]/g, '');
    if (numero.startsWith('00')) {
        numero = numero.substring(2);
    }
    if (/^\d{10}$/.test(numero)) {
        numero = '52' + numero;
    }
    if (numero.length < 10 || numero.length > 15) {
        Swal.fire('Número inválido', 'El número de WhatsApp no tiene el formato adecuado.', 'error');
        return;
    }
    Swal.fire({
        title: 'Obteniendo accesos...',
        html: 'Por favor espera',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    $.ajax({
        url: 'enviar_accesos.php',
        type: 'POST',
        data: { cliente_id: id },
        dataType: 'json',
        success: function(response) {
            Swal.close();
            if (response.success) {
                const usuario = response.usuario;
                const contrasena = response.contrasena;
                const mensaje = `Hola, te compartimos tus accesos al portal de cliente de ConlineWeb:

Portal de Cliente: https://cliente.conlineweb.com/

Usuario: ${usuario}
Contraseña: ${contrasena}

¡Gracias por tu confianza!`;
                const url = `https://wa.me/${numero}?text=${encodeURIComponent(mensaje)}`;
                window.open(url, '_blank');
            } else {
                Swal.fire('Error', 'No se pudieron obtener los accesos: ' + response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            Swal.close();
            Swal.fire('Error', 'Ocurrió un error al obtener los accesos: ' + error, 'error');
        }
    });
}

function mandarWhatsAppPagosPendientes(clienteId, telefono, nombre) {
    const sistema = <?php echo json_encode($sistema, JSON_UNESCAPED_UNICODE); ?>;
    Swal.fire({
        title: 'Preparando pagos pendientes...',
        html: 'Agrupando los cargos de <strong>' + (nombre || 'este cliente') + '</strong>',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    $.ajax({
        url: 'generar_mensaje_whatsapp_pagos_pendientes.php',
        type: 'POST',
        dataType: 'json',
        data: { cliente_id: clienteId, sistema: sistema },
        success: function (res) {
            if (!res || !res.success) {
                Swal.fire('Sin pendientes', (res && res.message) ? res.message : 'No se pudo generar el mensaje', 'info');
                return;
            }
            let numero = String(res.telefono || telefono || '').replace(/[\s\-\(\)\+]/g, '');
            if (numero.startsWith('00')) numero = numero.substring(2);
            if (/^\d{10}$/.test(numero)) numero = '52' + numero;
            if (numero.length < 10 || numero.length > 15) {
                Swal.fire('Número inválido', 'El cliente no tiene un teléfono de WhatsApp válido.', 'error');
                return;
            }
            const url = 'https://wa.me/' + numero + '?text=' + encodeURIComponent(res.mensaje || '');
            window.open(url, '_blank');
            Swal.fire({
                icon: 'success',
                title: 'WhatsApp listo',
                html: 'Se abrió un mensaje con <strong>' + (res.count || 0) + '</strong> pago(s) pendiente(s)<br>Total: <strong>$' +
                    Number(res.total || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) +
                    ' ' + (res.currency || 'MXN') + '</strong>',
                confirmButtonText: 'Entendido'
            });
        },
        error: function () {
            Swal.fire('Error', 'No se pudo consultar los pagos pendientes', 'error');
        }
    });
}

/* ── Mensajes / comunicados (plantillas + WhatsApp) ── */
var cwMsgState = {
    data: null,
    client: null,
    selectedId: null,
    reviewSistema: <?= json_encode($cwReviewSistema, JSON_UNESCAPED_UNICODE) ?>
};

function cwMsgApiUrl(action) {
    return 'api/client_messages.php?action=' + encodeURIComponent(action);
}

function cwMsgReviewKey(clienteId, sistema) {
    return String(sistema || 'conlineweb') + ':' + String(clienteId || 0);
}

function cwMsgIsReviewDone(clienteId, sistema) {
    var key = cwMsgReviewKey(clienteId, sistema);
    var reviews = (cwMsgState.data && cwMsgState.data.google_reviews) || {};
    return !!(reviews[key] && reviews[key].done);
}

function cwMsgSyncReviewUi(clienteId, sistema, done) {
    var sel = '.cw-google-review-check[data-cliente-id="' + clienteId + '"][data-sistema="' + sistema + '"]';
    $(sel).each(function () {
        this.checked = !!done;
        $(this).closest('.cli-review').toggleClass('is-done', !!done);
        $(this).siblings('.cli-review__label').text(done ? 'Con reseña' : 'Sin reseña');
    });
    if (cwMsgState.client && Number(cwMsgState.client.id) === Number(clienteId)) {
        var clientSistema = cwMsgState.client.sistema || cwMsgState.reviewSistema;
        if (String(clientSistema) === String(sistema)) {
            $('#cwMsgReviewDone').prop('checked', !!done);
            $('#cwMsgReviewCheckWrap').toggleClass('is-done', !!done);
            $('#cwMsgReviewDoneLabel').text(done ? 'Con reseña' : 'Sin reseña');
        }
    }
}

function cwMsgSetGoogleReview(clienteId, sistema, done, $input) {
    var prev = !done;
    $.ajax({
        url: cwMsgApiUrl('set_google_review'),
        type: 'POST',
        contentType: 'application/json',
        dataType: 'json',
        data: JSON.stringify({
            action: 'set_google_review',
            cliente_id: Number(clienteId),
            sistema: sistema || 'conlineweb',
            done: !!done
        }),
        success: function (res) {
            if (!res || !res.ok) {
                if ($input && $input.length) $input.prop('checked', prev);
                Swal.fire('Error', (res && res.error) || 'No se pudo guardar la reseña', 'error');
                return;
            }
            if (res.data) cwMsgState.data = res.data;
            cwMsgSyncReviewUi(clienteId, sistema, !!done);
        },
        error: function () {
            if ($input && $input.length) $input.prop('checked', prev);
            Swal.fire('Error', 'No se pudo guardar el checklist de reseña', 'error');
        }
    });
}

function cwResenaReviewUrl() {
    var fromSettings = cwMsgState.data
        && cwMsgState.data.settings
        && cwMsgState.data.settings.google_review_url;
    return String(fromSettings || 'https://g.page/r/CQ-1-K_N5AAEEBM/review').trim()
        || 'https://g.page/r/CQ-1-K_N5AAEEBM/review';
}

function cwResenaMensajeTexto(client) {
    var nombre = (client && client.nombre) ? String(client.nombre).trim() : 'cliente';
    var empresa = (client && client.empresa) ? String(client.empresa).trim() : 'su empresa';
    var url = cwResenaReviewUrl();
    return 'Hola ' + nombre + ',\n\n'
        + 'Espero que tú y el equipo de ' + empresa + ' se encuentren muy bien.\n\n'
        + 'En ConlineWeb valoramos mucho la confianza que depositaron en nosotros. '
        + 'Si su experiencia ha sido positiva, nos ayudaría mucho una reseña en Google: '
        + 'motiva al equipo y ayuda a más negocios a encontrarnos.\n\n'
        + 'Pueden dejarla aquí (toma menos de un minuto):\n'
        + url + '\n\n'
        + 'Mil gracias de antemano.\n'
        + 'Equipo ConlineWeb';
}

function cwSolicitarResena(client) {
    if (!client || !client.id) {
        Swal.fire('Cliente inválido', 'No se pudo identificar al cliente.', 'error');
        return;
    }

    var ensureData = function (done) {
        if (cwMsgState.data) {
            done();
            return;
        }
        cwMsgLoad(done);
    };

    ensureData(function () {
        Swal.fire({
            icon: 'question',
            title: 'Solicitar reseña en Google',
            html: '¿Cómo quieres enviarle la solicitud a <strong>'
                + cwMsgEscapeHtml(client.nombre || 'este cliente')
                + '</strong>?',
            showDenyButton: true,
            showCancelButton: true,
            confirmButtonText: '<i class="fab fa-whatsapp"></i> WhatsApp',
            denyButtonText: '<i class="fas fa-envelope"></i> Correo',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#25d366',
            denyButtonColor: '#000147'
        }).then(function (result) {
            if (result.isConfirmed) {
                cwSolicitarResenaWhatsApp(client);
            } else if (result.isDenied) {
                cwSolicitarResenaCorreo(client);
            }
        });
    });
}

function cwSolicitarResenaWhatsApp(client) {
    var numero = cwMsgNormalizePhone(client.telefono);
    if (numero.length < 10 || numero.length > 15) {
        Swal.fire('Número inválido', 'El cliente no tiene un teléfono de WhatsApp válido.', 'error');
        return;
    }
    var body = cwResenaMensajeTexto(client);
    window.open('https://wa.me/' + numero + '?text=' + encodeURIComponent(body), '_blank');
    Swal.fire({
        icon: 'success',
        title: 'WhatsApp listo',
        text: 'Se abrió el mensaje de solicitud de reseña.',
        timer: 1600,
        showConfirmButton: false
    });
}

function cwSolicitarResenaCorreo(client) {
    var correo = String((client && client.correo) || '').trim();
    if (!correo) {
        Swal.fire('Sin correo', 'Este cliente no tiene correo registrado.', 'warning');
        return;
    }
    Swal.fire({
        title: 'Enviando correo...',
        allowOutsideClick: false,
        didOpen: function () { Swal.showLoading(); }
    });
    $.ajax({
        url: 'api/enviar_solicitud_resena.php',
        type: 'POST',
        contentType: 'application/json',
        dataType: 'json',
        data: JSON.stringify({
            cliente_id: Number(client.id),
            sistema: client.sistema || cwMsgState.reviewSistema || 'conlineweb'
        }),
        success: function (res) {
            if (!res || !res.ok) {
                Swal.fire('Error', (res && res.error) || 'No se pudo enviar el correo', 'error');
                return;
            }
            Swal.fire({
                icon: 'success',
                title: 'Correo enviado',
                text: res.message || ('Solicitud enviada a ' + correo),
                timer: 2000,
                showConfirmButton: false
            });
        },
        error: function (xhr) {
            var msg = 'No se pudo enviar el correo';
            try {
                var j = JSON.parse(xhr.responseText || '{}');
                if (j && j.error) msg = j.error;
            } catch (e) {}
            Swal.fire('Error', msg, 'error');
        }
    });
}

/* ── Códigos de verificación (historial + envío correo) ── */
var cwVerifyState = {
    client: null,
    data: null
};

function cwVerifyApiUrl(action) {
    return 'api/client_verification.php?action=' + encodeURIComponent(action);
}

function cwVerifyFormatDate(iso) {
    if (!iso) return '—';
    var d = new Date(iso);
    if (isNaN(d.getTime())) return String(iso);
    var pad = function (n) { return String(n).padStart(2, '0'); };
    return pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + '/' + d.getFullYear()
        + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
}

function cwVerifySyncTableButton(clienteId, sistema, code) {
    var $btn = $('.cli-verify-btn[data-cliente-id="' + clienteId + '"][data-sistema="' + sistema + '"]');
    if (!$btn.length) return;
    $btn.toggleClass('has-code', !!code);
    $btn.find('.cli-verify-btn__code').text(code || '------');
    $btn.find('.cli-verify-btn__hint').text(code ? 'Ver historial' : 'Sin código');
}

function cwVerifyRender(data) {
    cwVerifyState.data = data || { current: null, updated_at: null, history: [] };
    var current = cwVerifyState.data.current || null;
    $('#cwVerifyCurrentCode').text(current || '------');
    if (current && cwVerifyState.data.updated_at) {
        $('#cwVerifyCurrentMeta').text('Enviado ' + cwVerifyFormatDate(cwVerifyState.data.updated_at));
    } else {
        $('#cwVerifyCurrentMeta').text('Aún no se ha enviado un código');
    }

    var history = cwVerifyState.data.history || [];
    var $body = $('#cwVerifyHistoryBody');
    if (!history.length) {
        $body.html('<tr><td colspan="3" class="text-muted">Sin envíos todavía</td></tr>');
        return;
    }
    var html = '';
    history.forEach(function (item, idx) {
        var isCurrent = idx === 0 && current && String(item.code) === String(current);
        html += '<tr class="' + (isCurrent ? 'is-current' : '') + '">'
            + '<td>' + cwMsgEscapeHtml(item.code || '') + (isCurrent ? ' · actual' : '') + '</td>'
            + '<td>' + cwMsgEscapeHtml(cwVerifyFormatDate(item.sent_at)) + '</td>'
            + '<td>' + cwMsgEscapeHtml(item.correo || '—') + '</td>'
            + '</tr>';
    });
    $body.html(html);
}

function cwVerifyOpen(client) {
    cwVerifyState.client = client || null;
    if (!cwVerifyState.client || !cwVerifyState.client.id) {
        Swal.fire('Cliente inválido', 'No se pudo abrir la verificación.', 'error');
        return;
    }
    var c = cwVerifyState.client;
    $('#cwVerifyClientBox').html(
        '<strong>Cliente:</strong> ' + cwMsgEscapeHtml(c.nombre || '—')
        + ' · <strong>Empresa:</strong> ' + cwMsgEscapeHtml(c.empresa || '—')
        + ' · <strong>Correo:</strong> ' + cwMsgEscapeHtml(c.correo || 'Sin correo')
    );
    cwVerifyRender({ current: null, updated_at: null, history: [] });

    var el = document.getElementById('cwVerifyModal');
    if (window.bootstrap && bootstrap.Modal) {
        bootstrap.Modal.getOrCreateInstance(el).show();
    } else {
        $('#cwVerifyModal').modal('show');
    }

    $.ajax({
        url: cwVerifyApiUrl('get'),
        type: 'GET',
        dataType: 'json',
        data: {
            cliente_id: c.id,
            sistema: c.sistema || cwMsgState.reviewSistema || 'conlineweb'
        },
        success: function (res) {
            if (res && res.ok) cwVerifyRender(res.data);
        }
    });
}

function cwVerifySend() {
    var c = cwVerifyState.client;
    if (!c || !c.id) return;
    if (!String(c.correo || '').trim()) {
        Swal.fire('Sin correo', 'Este cliente no tiene correo registrado.', 'warning');
        return;
    }
    var $btn = $('#cwVerifySendBtn');
    $btn.prop('disabled', true);
    Swal.fire({
        title: 'Generando y enviando...',
        allowOutsideClick: false,
        didOpen: function () { Swal.showLoading(); }
    });
    $.ajax({
        url: cwVerifyApiUrl('send'),
        type: 'POST',
        contentType: 'application/json',
        dataType: 'json',
        data: JSON.stringify({
            action: 'send',
            cliente_id: Number(c.id),
            sistema: c.sistema || cwMsgState.reviewSistema || 'conlineweb'
        }),
        success: function (res) {
            $btn.prop('disabled', false);
            if (!res || !res.ok) {
                Swal.fire('Error', (res && res.error) || 'No se pudo enviar el código', 'error');
                return;
            }
            cwVerifyRender(res.data);
            cwVerifySyncTableButton(
                c.id,
                c.sistema || cwMsgState.reviewSistema || 'conlineweb',
                res.code || (res.data && res.data.current)
            );
            Swal.fire({
                icon: 'success',
                title: 'Código enviado',
                html: 'Se envió el código <strong>' + cwMsgEscapeHtml(res.code || '') + '</strong><br>'
                    + cwMsgEscapeHtml(res.message || ''),
                timer: 2200,
                showConfirmButton: false
            });
        },
        error: function (xhr) {
            $btn.prop('disabled', false);
            var msg = 'No se pudo enviar el código';
            try {
                var j = JSON.parse(xhr.responseText || '{}');
                if (j && j.error) msg = j.error;
            } catch (e) {}
            Swal.fire('Error', msg, 'error');
        }
    });
}

function cwMsgEscapeHtml(str) {
    return String(str == null ? '' : str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function cwMsgNormalizePhone(telefono) {
    let numero = String(telefono || '').replace(/[\s\-\(\)\+]/g, '');
    if (numero.startsWith('00')) numero = numero.substring(2);
    if (/^\d{10}$/.test(numero)) numero = '52' + numero;
    return numero;
}

function cwMsgRenderVars(body, client, reviewUrl) {
    var map = {
        '{nombre}': (client && client.nombre) || '',
        '{empresa}': (client && client.empresa) || '',
        '{correo}': (client && client.correo) || '',
        '{telefono}': (client && client.telefono) || '',
        '{link_resenas}': reviewUrl || '',
        '{link_reseñas}': reviewUrl || ''
    };
    return String(body || '').replace(/\{nombre\}|\{empresa\}|\{correo\}|\{telefono\}|\{link_resenas\}|\{link_reseñas\}/g, function (m) {
        return map[m] != null ? map[m] : m;
    });
}

/** WhatsApp Desktop (PC) suele romper emojis vía wa.me: se limpian al enviar. */
function cwMsgSanitizeForDesktop(text) {
    return String(text || '')
        .replace(/[\u{1F300}-\u{1FAFF}]/gu, '')
        .replace(/[\u{2600}-\u{27BF}]/gu, '')
        .replace(/[\u{FE00}-\u{FE0F}]/gu, '')
        .replace(/\u200D/g, '')
        .replace(/[ \t]+\n/g, '\n')
        .replace(/\n{3,}/g, '\n\n')
        .trim();
}

function cwMsgLoad(thenFn) {
    $.ajax({
        url: cwMsgApiUrl('list'),
        type: 'GET',
        dataType: 'json',
        success: function (res) {
            if (!res || !res.ok) {
                Swal.fire('Error', (res && res.error) || 'No se pudieron cargar las plantillas', 'error');
                return;
            }
            cwMsgState.data = res.data;
            if (typeof thenFn === 'function') thenFn();
        },
        error: function () {
            Swal.fire('Error', 'No se pudo conectar con la API de mensajes', 'error');
        }
    });
}

function cwMsgRenderList() {
    var $list = $('#cwMsgTemplateList');
    $list.empty();
    var templates = (cwMsgState.data && cwMsgState.data.templates) || [];
    templates.forEach(function (tpl) {
        var active = tpl.id === cwMsgState.selectedId ? ' is-active' : '';
        $list.append(
            '<button type="button" class="cw-msg-item' + active + '" data-id="' + cwMsgEscapeHtml(tpl.id) + '">' +
            '<span class="cw-msg-item__cat">' + cwMsgEscapeHtml(tpl.category || 'general') + '</span>' +
            '<p class="cw-msg-item__title">' + cwMsgEscapeHtml(tpl.title) + '</p>' +
            '</button>'
        );
    });
    $list.find('.cw-msg-item').on('click', function () {
        cwMsgSelectTemplate($(this).data('id'));
    });
}

function cwMsgSelectTemplate(id) {
    var templates = (cwMsgState.data && cwMsgState.data.templates) || [];
    var tpl = templates.find(function (t) { return t.id === id; });
    if (!tpl) return;
    cwMsgState.selectedId = tpl.id;
    $('#cwMsgId').val(tpl.id);
    $('#cwMsgTitle').val(tpl.title || '');
    $('#cwMsgCategory').val(tpl.category || '');
    $('#cwMsgBody').val(tpl.body || '');
    cwMsgRenderList();
}

function cwMsgFillSettings() {
    var url = (cwMsgState.data && cwMsgState.data.settings && cwMsgState.data.settings.google_review_url) || '';
    $('#cwMsgReviewUrl').val(url);
}

function cwMsgUpdateClientBox() {
    var c = cwMsgState.client;
    var $box = $('#cwMsgClientBox');
    var $send = $('#cwMsgSendBtn');
    var $reviewWrap = $('#cwMsgReviewCheckWrap');
    if (!c) {
        $box.addClass('d-none').html('');
        $reviewWrap.addClass('d-none');
        $send.prop('disabled', true).attr('title', 'Abre el modal desde Acciones de un cliente para enviar');
        return;
    }
    var sistema = c.sistema || cwMsgState.reviewSistema || 'conlineweb';
    var done = cwMsgIsReviewDone(c.id, sistema);
    $box.removeClass('d-none').html(
        '<strong>Cliente:</strong> ' + cwMsgEscapeHtml(c.nombre || '—') +
        ' · <strong>Empresa:</strong> ' + cwMsgEscapeHtml(c.empresa || '—') +
        ' · <strong>WhatsApp:</strong> ' + cwMsgEscapeHtml(c.telefono || 'Sin teléfono')
    );
    $reviewWrap.removeClass('d-none').toggleClass('is-done', done);
    $('#cwMsgReviewDone').prop('checked', done);
    $('#cwMsgReviewDoneLabel').text(done ? 'Con reseña' : 'Sin reseña');
    $send.prop('disabled', false).removeAttr('title');
}

function cwMsgShowModal() {
    var el = document.getElementById('cwMsgModal');
    if (window.bootstrap && bootstrap.Modal) {
        bootstrap.Modal.getOrCreateInstance(el).show();
    } else {
        $('#cwMsgModal').modal('show');
    }
}

function cwMsgOpenManager() {
    cwMsgState.client = null;
    cwMsgLoad(function () {
        cwMsgFillSettings();
        cwMsgUpdateClientBox();
        var first = (cwMsgState.data.templates || [])[0];
        if (first) cwMsgSelectTemplate(first.id);
        else cwMsgNewTemplate();
        cwMsgShowModal();
    });
}

function cwMsgOpenForClient(client) {
    cwMsgState.client = client || null;
    if (cwMsgState.client && !cwMsgState.client.sistema) {
        cwMsgState.client.sistema = cwMsgState.reviewSistema;
    }
    cwMsgLoad(function () {
        cwMsgFillSettings();
        cwMsgUpdateClientBox();
        var review = (cwMsgState.data.templates || []).find(function (t) { return t.id === 'review_google'; });
        var first = review || (cwMsgState.data.templates || [])[0];
        if (first) cwMsgSelectTemplate(first.id);
        else cwMsgNewTemplate();
        cwMsgShowModal();
    });
}

function cwMsgNewTemplate() {
    cwMsgState.selectedId = null;
    $('#cwMsgId').val('');
    $('#cwMsgTitle').val('');
    $('#cwMsgCategory').val('comunicados');
    $('#cwMsgBody').val('Hola *{nombre}*,\n\n');
    cwMsgRenderList();
}

function cwMsgSaveTemplate() {
    var payload = {
        action: 'save_template',
        id: $('#cwMsgId').val(),
        title: $('#cwMsgTitle').val().trim(),
        category: $('#cwMsgCategory').val().trim() || 'general',
        body: $('#cwMsgBody').val()
    };
    if (!payload.title || !payload.body) {
        Swal.fire('Faltan datos', 'Escribe un título y el mensaje.', 'warning');
        return;
    }
    var reviewUrl = $('#cwMsgReviewUrl').val().trim();
    $.ajax({
        url: cwMsgApiUrl('save_template'),
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(payload),
        dataType: 'json',
        success: function (res) {
            if (!res || !res.ok) {
                Swal.fire('Error', (res && res.error) || 'No se guardó', 'error');
                return;
            }
            // Guardar también el link de reseñas
            $.ajax({
                url: cwMsgApiUrl('save_settings'),
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ action: 'save_settings', google_review_url: reviewUrl }),
                dataType: 'json',
                complete: function () {
                    cwMsgState.data = res.data || (cwMsgState.data);
                    if (res.template) {
                        cwMsgState.selectedId = res.template.id;
                        $('#cwMsgId').val(res.template.id);
                    }
                    if (res.data) cwMsgState.data = res.data;
                    cwMsgLoad(function () {
                        cwMsgFillSettings();
                        cwMsgSelectTemplate(cwMsgState.selectedId || (res.template && res.template.id));
                        Swal.fire({ icon: 'success', title: 'Guardado', timer: 1400, showConfirmButton: false });
                    });
                }
            });
        },
        error: function () {
            Swal.fire('Error', 'No se pudo guardar la plantilla', 'error');
        }
    });
}

function cwMsgDeleteTemplate() {
    var id = $('#cwMsgId').val();
    if (!id) {
        cwMsgNewTemplate();
        return;
    }
    Swal.fire({
        title: '¿Eliminar plantilla?',
        text: 'Esta acción no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(function (r) {
        if (!r.isConfirmed) return;
        $.ajax({
            url: cwMsgApiUrl('delete_template'),
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ action: 'delete_template', id: id }),
            dataType: 'json',
            success: function (res) {
                if (!res || !res.ok) {
                    Swal.fire('Error', (res && res.error) || 'No se eliminó', 'error');
                    return;
                }
                cwMsgState.data = res.data;
                var first = (res.data.templates || [])[0];
                if (first) cwMsgSelectTemplate(first.id);
                else cwMsgNewTemplate();
                cwMsgRenderList();
            }
        });
    });
}

function cwMsgSendWhatsApp() {
    var client = cwMsgState.client;
    if (!client) {
        Swal.fire('Elige un cliente', 'Abre el modal desde el botón Mensaje en Acciones del cliente.', 'info');
        return;
    }
    var numero = cwMsgNormalizePhone(client.telefono);
    if (numero.length < 10 || numero.length > 15) {
        Swal.fire('Número inválido', 'El cliente no tiene un teléfono de WhatsApp válido.', 'error');
        return;
    }
    var reviewUrl = $('#cwMsgReviewUrl').val().trim()
        || ((cwMsgState.data && cwMsgState.data.settings && cwMsgState.data.settings.google_review_url) || '');
    var body = cwMsgSanitizeForDesktop(cwMsgRenderVars($('#cwMsgBody').val(), client, reviewUrl));
    if (!body.trim()) {
        Swal.fire('Mensaje vacío', 'Redacta o elige una plantilla antes de enviar.', 'warning');
        return;
    }
    // Persistir link de reseñas si lo editaron
    if (reviewUrl) {
        $.ajax({
            url: cwMsgApiUrl('save_settings'),
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ action: 'save_settings', google_review_url: reviewUrl })
        });
    }
    var url = 'https://wa.me/' + numero + '?text=' + encodeURIComponent(body);
    window.open(url, '_blank');
    Swal.fire({
        icon: 'success',
        title: 'WhatsApp listo',
        text: 'Se abrió WhatsApp con el mensaje para ' + (client.nombre || 'el cliente') + '.',
        timer: 1800,
        showConfirmButton: false
    });
}

$(document).on('change', '.cw-google-review-check', function () {
    var $input = $(this);
    cwMsgSetGoogleReview(
        $input.data('cliente-id'),
        $input.data('sistema') || cwMsgState.reviewSistema,
        $input.is(':checked'),
        $input
    );
});

$(document).on('change', '#cwMsgReviewDone', function () {
    var client = cwMsgState.client;
    if (!client || !client.id) {
        $(this).prop('checked', false);
        return;
    }
    cwMsgSetGoogleReview(
        client.id,
        client.sistema || cwMsgState.reviewSistema,
        $(this).is(':checked'),
        $(this)
    );
});
</script>
</body>
</html>