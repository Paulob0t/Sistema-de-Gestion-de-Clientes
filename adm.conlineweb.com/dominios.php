<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
include "menu.php";
include "conn.php";
include "conn_hostingpro.php";

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
$db = ($sistema === 'hostingpro' || $sistema === 'planpro') ? $conn_hp : $conn;

// Consulta principal para obtener todos los dominios ordenados
// Si es Plan Pro, filtrar solo dominios comprados en conlineweb.com/plan_pro.php
$sql = "SELECT DISTINCT d.*, c.nombre_contacto, c.facturacion
        FROM dominios d
        LEFT JOIN clientes c ON d.cliente_id = c.id";

// Filtro para Plan Pro: solo dominios con pago registrado en sistema='conlineweb'
if ($sistema === 'planpro') {
    $sql .= " INNER JOIN pagos p ON d.id_dominio = p.id_servicio AND p.tipo_servicio = 2
              WHERE p.sistema = 'conlineweb'";
}

$sql .= " ORDER BY d.fecha_pago >= CURDATE() DESC, d.fecha_pago ASC";
$result = $db->query($sql);

$dominios_activos = [];
$dominios_eliminados = [];
$dominios = [];
$dominios_activos_estado = [];
$dominios_inactivos = [];
$dominios_registrados = [];
$dominios_no_registrados = [];

if ($result && $result->num_rows > 0) {
    $dominios = $result->fetch_all(MYSQLI_ASSOC);
    
    foreach ($dominios as $row) {
        if (isset($row['eliminado']) && $row['eliminado'] == 1) {
            $dominios_eliminados[] = $row;
        } else {
            $dominios_activos[] = $row;
            
            // Separar por estado del dominio (Activo/Inactivo)
            if ($row['estado_dominio'] == 1) {
                $dominios_activos_estado[] = $row;
            } else {
                $dominios_inactivos[] = $row;
            }
            
            // Separar por gestión: registrado=1 ConlineWeb · 0 proveedor externo
            if ((int) ($row['registrado'] ?? 0) === 1) {
                $dominios_registrados[] = $row;
            } else {
                $dominios_no_registrados[] = $row;
            }
        }
    }
}

$sqlPagos = "SELECT * FROM pagos WHERE tipo_servicio = 2";
$resultPagos = $db->query($sqlPagos);
$pagos = [];
if ($resultPagos && $resultPagos->num_rows > 0) {
    $pagos = $resultPagos->fetch_all(MYSQLI_ASSOC);
}

// Calcular estadísticas para el dashboard
$hoy = date('Y-m-d');
$mes_anterior = date('Y-m-d', strtotime('-1 month'));

// Contadores para servicios por vencer (solo para activos)
$vencidos = 0;
$por_vencer_30 = 0;
$por_vencer_15 = 0;
$por_vencer_7 = 0;
$pagados = 0;
$no_pagados = 0;

foreach ($dominios_activos_estado as $dominio) {
    // Contar pagados/no pagados
    if (isset($dominio['estatus_pago']) && $dominio['estatus_pago'] == 1) {
        $pagados++;
    } else {
        $no_pagados++;
    }
    
    if (!empty($dominio['fecha_pago']) && $dominio['fecha_pago'] !== '0000-00-00') {
        $fecha_pago = $dominio['fecha_pago'];
        
        if ($fecha_pago < $hoy) {
            $vencidos++;
        } else {
            $dias_diferencia = (strtotime($fecha_pago) - strtotime($hoy)) / (60 * 60 * 24);
            
            if ($dias_diferencia <= 30) {
                $por_vencer_30++;
            }
            if ($dias_diferencia <= 15) {
                $por_vencer_15++;
            }
            if ($dias_diferencia <= 7) {
                $por_vencer_7++;
            }
        }
    }
}

// Servicios que vencieron el mes pasado
$servicios_vencidos_mes_anterior = [];
foreach ($dominios_activos_estado as $dominio) {
    if (!empty($dominio['fecha_pago']) && $dominio['fecha_pago'] !== '0000-00-00') {
        $fecha_pago = $dominio['fecha_pago'];
        if ($fecha_pago >= $mes_anterior && $fecha_pago < $hoy) {
            $servicios_vencidos_mes_anterior[] = $dominio;
        }
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
    <link href="vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
    <link href="css/admin-consultas.css?v=20250714" rel="stylesheet">
    <link href="css/admin-datatables.css?v=20250715" rel="stylesheet">
</head>
<body>
    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            <div class="container-xl my-4 py-4 legacy-touch" style="max-width: 96% !important;">
                
                <!-- Header -->
                <div class="d-flex align-items-center justify-content-between mb-4 fade-in-up">
                    <div>
                        <h1 class="h2 mb-1" style="color: var(--primary-dark); font-weight: 700; letter-spacing: -0.02em;">Dominios</h1>
                        <p class="text-secondary-custom mb-0" style="font-weight: 500;">Gestión y seguimiento de dominios</p>
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
                        <div class="stat-card p-4" onclick="filtrarPorEstado('vencidos')">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stat-number"><?php echo $vencidos; ?></div>
                                    <div class="stat-label mt-1">Vencidos</div>
                                </div>
                                <div class="stat-icon">
                                    <i class="fas fa-exclamation-triangle fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 mb-4">
                        <div class="stat-card p-4" onclick="filtrarPorEstado('30_dias')">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stat-number"><?php echo $por_vencer_30; ?></div>
                                    <div class="stat-label mt-1">Próximos 30 días</div>
                                </div>
                                <div class="stat-icon">
                                    <i class="fas fa-calendar-week fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 mb-4">
                        <div class="stat-card p-4" onclick="filtrarPorEstado('15_dias')">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stat-number"><?php echo $por_vencer_15; ?></div>
                                    <div class="stat-label mt-1">Próximos 15 días</div>
                                </div>
                                <div class="stat-icon">
                                    <i class="fas fa-hourglass-half fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 mb-4">
                        <div class="stat-card p-4" onclick="filtrarPorEstado('7_dias')">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stat-number"><?php echo $por_vencer_7; ?></div>
                                    <div class="stat-label mt-1">Próximos 7 días</div>
                                </div>
                                <div class="stat-icon">
                                    <i class="fas fa-clock fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 mb-4">
                        <div class="stat-card p-4" onclick="filtrarPorEstado('pagados')">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stat-number"><?php echo $pagados; ?></div>
                                    <div class="stat-label mt-1">Pagados</div>
                                </div>
                                <div class="stat-icon">
                                    <i class="fas fa-check-circle fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 mb-4">
                        <div class="stat-card p-4" onclick="filtrarPorEstado('no_pagados')">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stat-number"><?php echo $no_pagados; ?></div>
                                    <div class="stat-label mt-1">No Pagados</div>
                                </div>
                                <div class="stat-icon">
                                    <i class="fas fa-times-circle fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Alert Servicios Vencidos Mes Anterior -->
                <?php if (!empty($servicios_vencidos_mes_anterior)): ?>
                <div class="alert-modern mb-4 fade-in-up">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-bell text-warning me-3 mt-1" style="color: #f59e0b;"></i>
                        <div>
                            <strong class="d-block mb-1" style="color: #92400e;">¡Atención!</strong>
                            <span style="color: #78350f;"><?php echo count($servicios_vencidos_mes_anterior); ?> dominio(s) vencieron el mes pasado:</span>
                            <ul class="mt-2 mb-0" style="color: #78350f;">
                                <?php foreach ($servicios_vencidos_mes_anterior as $dominio): ?>
                                    <li>
                                        <strong><?php echo htmlspecialchars($dominio['url_dominio']); ?></strong> - 
                                        <?php echo htmlspecialchars($dominio['nombre_contacto']); ?> - 
                                        Vencimiento: <?php echo date("d/m/Y", strtotime($dominio['fecha_pago'])); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <button type="button" class="btn-close ms-auto" data-dismiss="alert"></button>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Tabs principales -->
                <ul class="nav nav-tabs-modern" id="dominiosTab" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="activos-tab" data-toggle="tab" href="#activos" role="tab">
                            <i class="fas fa-globe me-2"></i>Activos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="eliminados-tab" data-toggle="tab" href="#eliminados" role="tab">
                            <i class="fas fa-trash-alt me-2"></i>Eliminados
                        </a>
                    </li>
                </ul>
                
                <div class="tab-content" id="dominiosTabContent">
                    <!-- TAB ACTIVOS -->
                    <div class="tab-pane fade show active" id="activos" role="tabpanel">
                        <!-- Sub-tabs para estado y registro -->
                        <ul class="nav sub-tabs" id="estadoSubTab" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="sub-activos-tab" data-toggle="tab" href="#sub-activos" role="tab">
                                    <i class="fas fa-play-circle me-1"></i>Activos
                                    <span class="badge bg-success ms-1" style="background: var(--success) !important;"><?php echo count($dominios_activos_estado); ?></span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="sub-inactivos-tab" data-toggle="tab" href="#sub-inactivos" role="tab">
                                    <i class="fas fa-stop-circle me-1"></i>Inactivos
                                    <span class="badge bg-danger ms-1" style="background: var(--danger) !important;"><?php echo count($dominios_inactivos); ?></span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="sub-registrados-tab" data-toggle="tab" href="#sub-registrados" role="tab">
                                    <i class="fas fa-building me-1"></i>ConlineWeb
                                    <span class="badge bg-success ms-1" style="background: var(--success) !important;"><?php echo count($dominios_registrados); ?></span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="sub-no-registrados-tab" data-toggle="tab" href="#sub-no-registrados" role="tab">
                                    <i class="fas fa-external-link-alt me-1"></i>Externos
                                    <span class="badge bg-warning ms-1" style="background: var(--warning) !important;"><?php echo count($dominios_no_registrados); ?></span>
                                </a>
                            </li>
                        </ul>
                        
                        <div class="tab-content">
                            <!-- Sub-tab: Dominios Activos (estado = 1) -->
                            <div class="tab-pane fade show active" id="sub-activos" role="tabpanel">
                                <!-- Filtros -->
                                <div class="filter-section">
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <div class="filter-label">Rango de Fechas</div>
                                            <input type="text" id="rangoFechasPagoActivos" class="form-control-modern w-100" placeholder="Seleccionar rango">
                                        </div>
                                        <div class="col-md-2">
                                            <div class="filter-label">Mes</div>
                                            <select id="filtroMesActivos" class="form-control-modern w-100">
                                                <option value="todos">Todos</option>
                                                <option value="0">Enero</option>
                                                <option value="1">Febrero</option>
                                                <option value="2">Marzo</option>
                                                <option value="3">Abril</option>
                                                <option value="4">Mayo</option>
                                                <option value="5">Junio</option>
                                                <option value="6">Julio</option>
                                                <option value="7">Agosto</option>
                                                <option value="8">Septiembre</option>
                                                <option value="9">Octubre</option>
                                                <option value="10">Noviembre</option>
                                                <option value="11">Diciembre</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="filter-label">Año</div>
                                            <select id="filtroAnioActivos" class="form-control-modern w-100">
                                                <option value="todos">Todos</option>
                                                <?php
                                                $anio_actual = date('Y');
                                                for($i = $anio_actual - 2; $i <= $anio_actual + 2; $i++) {
                                                    echo "<option value='$i'>$i</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="filter-label">Estado de Pago</div>
                                            <select id="filtroEstadoPagoActivos" class="form-control-modern w-100">
                                                <option value="todos">Todos</option>
                                                <option value="vencidos">Vencidos</option>
                                                <option value="proximos">Próximos a vencer</option>
                                                <option value="pagados">Pagados</option>
                                                <option value="no_pagados">No pagados</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="filter-label">&nbsp;</div>
                                            <button class="btn-clear w-100" onclick="limpiarFiltrosActivos()">
                                                <i class="fas fa-eraser me-2"></i>Limpiar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Tabla de Dominios Activos -->
                                <div class="modern-table">
                                    <?php if (count($dominios_activos_estado) > 0): ?>
                                        <table class="table" id="dataTableActivos" width="100%">
                                            <thead>
                                                <tr style="background: var(--primary-soft);">
                                                    <th>ID</th>
                                                    <th>Cliente</th>
                                                    <th>Dominio</th>
                                                    <th>Gestión</th>
                                                    <th>Estado</th>
                                                    <th>Proveedor</th>
                                                    <th>Fecha Pago</th>
                                                    <th>Estado Pago</th>
                                                    <th>Costo</th>
                                                    <th>Moneda</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($dominios_activos_estado as $row): 
                                                    $status_text = '';
                                                    $days_text = '';
                                                    $dot_class = '';
                                                    $estatus_pago = isset($row["estatus_pago"]) ? $row["estatus_pago"] : 0;
                                                    
                                                    if (!empty($row["fecha_pago"]) && $row["fecha_pago"] !== '0000-00-00') {
                                                        $fecha_pago = $row["fecha_pago"];
                                                        if ($fecha_pago < $hoy) {
                                                            $status_text = 'Vencido';
                                                            $dot_class = 'vencido';
                                                        } else {
                                                            $dias = (strtotime($fecha_pago) - strtotime($hoy)) / (60 * 60 * 24);
                                                            $dias_rounded = round($dias);
                                                            
                                                            if ($dias <= 7) {
                                                                $dot_class = 'warning';
                                                                $status_text = 'Crítico';
                                                                $days_text = "<span class='days-badge critical'><i class='fas fa-exclamation-circle me-1'></i> {$dias_rounded} días</span>";
                                                            } elseif ($dias <= 15) {
                                                                $dot_class = 'warning';
                                                                $status_text = 'Pronto';
                                                                $days_text = "<span class='days-badge warning'><i class='fas fa-hourglass-half me-1'></i> {$dias_rounded} días</span>";
                                                            } elseif ($dias <= 30) {
                                                                $dot_class = 'info';
                                                                $status_text = 'Próximo';
                                                                $days_text = "<span class='days-badge normal'><i class='fas fa-calendar-day me-1'></i> {$dias_rounded} días</span>";
                                                            } else {
                                                                $dot_class = 'success';
                                                                $status_text = 'Al día';
                                                                $days_text = "<span class='days-badge normal'><i class='fas fa-check-circle me-1'></i> {$dias_rounded} días</span>";
                                                            }
                                                        }
                                                    } else {
                                                        $status_text = 'Sin fecha';
                                                        $dot_class = 'secondary';
                                                        $days_text = '<span class="days-badge normal">Sin definir</span>';
                                                    }
                                                ?>
                                                    <tr>
                                                        <td><span class="badge-id">#<?php echo $row["id_dominio"]; ?></span></td>
                                                        <td>
                                                            <div class="fw-semibold"><?php echo $row["nombre_contacto"]; ?></div>
                                                            <small class="text-secondary-custom">ID: <?php echo $row["cliente_id"]; ?></small>
                                                        </td>
                                                        <td>
                                                            <div class="fw-semibold"><?php echo $row["url_dominio"]; ?></div>
                                                            <small class="text-secondary-custom"><?php echo $row["proveedor"]; ?></small>
                                                        </td>
                                                        <td>
                                                            <?php if ((int) ($row['registrado'] ?? 0) === 1): ?>
                                                            <span class="badge-status badge-registrado" title="Gestionado por ConlineWeb">
                                                                <i class="fas fa-building me-1"></i>ConlineWeb
                                                            </span>
                                                            <?php else: ?>
                                                            <span class="badge-status badge-no-registrado" title="Proveedor externo">
                                                                <i class="fas fa-external-link-alt me-1"></i>Externo
                                                            </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge-status badge-active">
                                                                <i class="fas fa-check-circle me-1"></i>Activo
                                                            </span>
                                                        </td>
                                                        <td><?php echo $row["proveedor"]; ?></td>
                                                        <td class="fw-medium">
                                                            <?php echo !empty($row["fecha_pago"]) && $row["fecha_pago"] !== "0000-00-00" ? date("d/m/Y", strtotime($row["fecha_pago"])) : "-"; ?>
                                                        </td>
                                                        <td>
                                                            <div class="status-indicator">
                                                                <?php if ($estatus_pago == 0): ?>
                                                                    <span class="status-dot vencido"></span>
                                                                    <span class="badge-status badge-no-pagado">No pagado</span>
                                                                <?php else: ?>
                                                                    <span class="status-dot success"></span>
                                                                    <span class="badge-status badge-pagado">Pagado</span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <?php echo $days_text; ?>
                                                        </td>
                                                        <td class="fw-semibold"><?php echo !empty($row["costo_dominio"]) ? '$' . number_format($row["costo_dominio"], 2) : "-"; ?></td>
                                                        <td class="fw-medium"><?php echo $row["id_forma_pago"] == 1 ? 'MXN' : ($row["id_forma_pago"] == 2 ? 'USD' : '-'); ?></td>
                                                         <td>
                                                            <div class="adm-actions">
                                                                <?php if ($estatus_pago == 0): ?>
                                                                    <button type="button" class="adm-act adm-act--info marcar-pagado-btn" data-id="<?php echo $row["id_dominio"]; ?>" title="Marcar como pagado">
                                                                        <i class="fas fa-check"></i>Marcar pagado
                                                                    </button>
                                                                <?php endif; ?>
                                                                <button type="button" class="adm-act adm-act--success enviar-correo-btn" data-id="<?php echo $row["id_dominio"]; ?>" title="Enviar correo">
                                                                    <i class="fas fa-paper-plane"></i>Correo
                                                                </button>
                                                                <button type="button" class="adm-act adm-act--secondary toggle-estado-dominio-btn"
                                                                        data-id="<?php echo (int) $row['id_dominio']; ?>"
                                                                        data-estado="<?php echo (int) ($row['estado_dominio'] ?? 1); ?>"
                                                                        title="Desactivar dominio">
                                                                    <i class="fas fa-toggle-on"></i>Desactivar
                                                                </button>
                                                                <button type="button" class="adm-act adm-act--warn editar-info-btn" data-id="<?php echo $row["id_dominio"]; ?>" title="Editar">
                                                                    <i class="fas fa-pencil-alt"></i>Editar
                                                                </button>
                                                                <button type="button" class="adm-act adm-act--danger eliminar-dominio-btn" data-id="<?php echo $row["id_dominio"]; ?>" title="Eliminar">
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
                                            <p class="text-secondary-custom">No se encontraron dominios activos</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Sub-tab: Dominios Inactivos (estado = 0) -->
                            <div class="tab-pane fade" id="sub-inactivos" role="tabpanel">
                                <!-- Filtros para inactivos -->
                                <div class="filter-section">
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <div class="filter-label">Rango de Fechas</div>
                                            <input type="text" id="rangoFechasPagoInactivos" class="form-control-modern w-100" placeholder="Seleccionar rango">
                                        </div>
                                        <div class="col-md-2">
                                            <div class="filter-label">Mes</div>
                                            <select id="filtroMesInactivos" class="form-control-modern w-100">
                                                <option value="todos">Todos</option>
                                                <option value="0">Enero</option>
                                                <option value="1">Febrero</option>
                                                <option value="2">Marzo</option>
                                                <option value="3">Abril</option>
                                                <option value="4">Mayo</option>
                                                <option value="5">Junio</option>
                                                <option value="6">Julio</option>
                                                <option value="7">Agosto</option>
                                                <option value="8">Septiembre</option>
                                                <option value="9">Octubre</option>
                                                <option value="10">Noviembre</option>
                                                <option value="11">Diciembre</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="filter-label">Año</div>
                                            <select id="filtroAnioInactivos" class="form-control-modern w-100">
                                                <option value="todos">Todos</option>
                                                <?php
                                                for($i = $anio_actual - 2; $i <= $anio_actual + 2; $i++) {
                                                    echo "<option value='$i'>$i</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="filter-label">Estado de Pago</div>
                                            <select id="filtroEstadoPagoInactivos" class="form-control-modern w-100">
                                                <option value="todos">Todos</option>
                                                <option value="vencidos">Vencidos</option>
                                                <option value="futuros">Futuros</option>
                                                <option value="pagados">Pagados</option>
                                                <option value="no_pagados">No pagados</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="filter-label">&nbsp;</div>
                                            <button class="btn-clear w-100" onclick="limpiarFiltrosInactivos()">
                                                <i class="fas fa-eraser me-2"></i>Limpiar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="modern-table">
                                    <?php if (count($dominios_inactivos) > 0): ?>
                                        <table class="table" id="dataTableInactivos" width="100%">
                                            <thead>
                                                <tr style="background: var(--primary-soft);">
                                                    <th>ID</th>
                                                    <th>Cliente</th>
                                                    <th>Dominio</th>
                                                    <th>Gestión</th>
                                                    <th>Estado</th>
                                                    <th>Proveedor</th>
                                                    <th>Fecha Pago</th>
                                                    <th>Estado Pago</th>
                                                    <th>Costo</th>
                                                    <th>Moneda</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($dominios_inactivos as $row): 
                                                    $status_text = '';
                                                    $dot_class = '';
                                                    $estatus_pago = isset($row["estatus_pago"]) ? $row["estatus_pago"] : 0;
                                                    
                                                    if (!empty($row["fecha_pago"]) && $row["fecha_pago"] !== '0000-00-00') {
                                                        $fecha_pago = $row["fecha_pago"];
                                                        if ($fecha_pago < $hoy) {
                                                            $status_text = 'Vencido';
                                                            $dot_class = 'vencido';
                                                        } else {
                                                            $status_text = 'Futuro';
                                                            $dot_class = 'info';
                                                        }
                                                    } else {
                                                        $status_text = 'Sin fecha';
                                                        $dot_class = 'secondary';
                                                    }
                                                ?>
                                                    <tr style="opacity: 0.8;">
                                                        <td><span class="badge-id">#<?php echo $row["id_dominio"]; ?></span></td>
                                                        <td>
                                                            <div class="fw-semibold"><?php echo $row["nombre_contacto"]; ?></div>
                                                            <small class="text-secondary-custom">ID: <?php echo $row["cliente_id"]; ?></small>
                                                        </td>
                                                        <td>
                                                            <div class="fw-semibold"><?php echo $row["url_dominio"]; ?></div>
                                                            <small class="text-secondary-custom"><?php echo $row["proveedor"]; ?></small>
                                                        </td>
                                                        <td>
                                                            <?php if ((int) ($row['registrado'] ?? 0) === 1): ?>
                                                            <span class="badge-status badge-registrado" title="Gestionado por ConlineWeb">
                                                                <i class="fas fa-building me-1"></i>ConlineWeb
                                                            </span>
                                                            <?php else: ?>
                                                            <span class="badge-status badge-no-registrado" title="Proveedor externo">
                                                                <i class="fas fa-external-link-alt me-1"></i>Externo
                                                            </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge-status badge-inactive">
                                                                <i class="fas fa-stop-circle me-1"></i>Inactivo
                                                            </span>
                                                        </td>
                                                        <td><?php echo $row["proveedor"]; ?></td>
                                                        <td class="fw-medium">
                                                            <?php echo !empty($row["fecha_pago"]) && $row["fecha_pago"] !== "0000-00-00" ? date("d/m/Y", strtotime($row["fecha_pago"])) : "-"; ?>
                                                        </td>
                                                        <td>
                                                            <div class="status-indicator">
                                                                <?php if ($estatus_pago == 0): ?>
                                                                    <span class="status-dot vencido"></span>
                                                                    <span class="badge-status badge-no-pagado">No pagado</span>
                                                                <?php else: ?>
                                                                    <span class="status-dot success"></span>
                                                                    <span class="badge-status badge-pagado">Pagado</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                        <td class="fw-semibold"><?php echo !empty($row["costo_dominio"]) ? '$' . number_format($row["costo_dominio"], 2) : "-"; ?></td>
                                                        <td class="fw-medium"><?php echo $row["id_forma_pago"] == 1 ? 'MXN' : ($row["id_forma_pago"] == 2 ? 'USD' : '-'); ?></td>
                                                        <td>
                                                            <div class="adm-actions">
                                                                <?php if ($estatus_pago == 0): ?>
                                                                    <button type="button" class="adm-act adm-act--info marcar-pagado-btn" data-id="<?php echo $row["id_dominio"]; ?>" title="Marcar como pagado">
                                                                        <i class="fas fa-check"></i>Marcar pagado
                                                                    </button>
                                                                <?php endif; ?>
                                                                <button type="button" class="adm-act adm-act--success enviar-correo-btn" data-id="<?php echo $row["id_dominio"]; ?>" title="Enviar correo">
                                                                    <i class="fas fa-paper-plane"></i>Correo
                                                                </button>
                                                                <button type="button" class="adm-act adm-act--success toggle-estado-dominio-btn"
                                                                        data-id="<?php echo (int) $row['id_dominio']; ?>"
                                                                        data-estado="<?php echo (int) ($row['estado_dominio'] ?? 0); ?>"
                                                                        title="Activar dominio">
                                                                    <i class="fas fa-toggle-off"></i>Activar
                                                                </button>
                                                                <button type="button" class="adm-act adm-act--warn editar-info-btn" data-id="<?php echo $row["id_dominio"]; ?>" title="Editar">
                                                                    <i class="fas fa-pencil-alt"></i>Editar
                                                                </button>
                                                                <button type="button" class="adm-act adm-act--danger eliminar-dominio-btn" data-id="<?php echo $row["id_dominio"]; ?>" title="Eliminar">
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
                                            <p class="text-secondary-custom">No se encontraron dominios inactivos</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Sub-tab: Dominios Registrados -->
                            <div class="tab-pane fade" id="sub-registrados" role="tabpanel">
                                <!-- Filtros para registrados -->
                                <div class="filter-section">
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <div class="filter-label">Rango de Fechas</div>
                                            <input type="text" id="rangoFechasPagoRegistrados" class="form-control-modern w-100" placeholder="Seleccionar rango">
                                        </div>
                                        <div class="col-md-2">
                                            <div class="filter-label">Mes</div>
                                            <select id="filtroMesRegistrados" class="form-control-modern w-100">
                                                <option value="todos">Todos</option>
                                                <option value="0">Enero</option>
                                                <option value="1">Febrero</option>
                                                <option value="2">Marzo</option>
                                                <option value="3">Abril</option>
                                                <option value="4">Mayo</option>
                                                <option value="5">Junio</option>
                                                <option value="6">Julio</option>
                                                <option value="7">Agosto</option>
                                                <option value="8">Septiembre</option>
                                                <option value="9">Octubre</option>
                                                <option value="10">Noviembre</option>
                                                <option value="11">Diciembre</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="filter-label">Año</div>
                                            <select id="filtroAnioRegistrados" class="form-control-modern w-100">
                                                <option value="todos">Todos</option>
                                                <?php
                                                for($i = $anio_actual - 2; $i <= $anio_actual + 2; $i++) {
                                                    echo "<option value='$i'>$i</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="filter-label">Estado de Pago</div>
                                            <select id="filtroEstadoPagoRegistrados" class="form-control-modern w-100">
                                                <option value="todos">Todos</option>
                                                <option value="vencidos">Vencidos</option>
                                                <option value="proximos">Próximos a vencer</option>
                                                <option value="pagados">Pagados</option>
                                                <option value="no_pagados">No pagados</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="filter-label">&nbsp;</div>
                                            <button class="btn-clear w-100" onclick="limpiarFiltrosRegistrados()">
                                                <i class="fas fa-eraser me-2"></i>Limpiar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="modern-table">
                                    <?php if (count($dominios_registrados) > 0): ?>
                                        <table class="table" id="dataTableRegistrados" width="100%">
                                            <thead>
                                                <tr style="background: var(--primary-soft);">
                                                    <th>ID</th>
                                                    <th>Cliente</th>
                                                    <th>Dominio</th>
                                                    <th>Gestión</th>
                                                    <th>Estado</th>
                                                    <th>Proveedor</th>
                                                    <th>Fecha Pago</th>
                                                    <th>Estado Pago</th>
                                                    <th>Costo</th>
                                                    <th>Moneda</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($dominios_registrados as $row): 
                                                    $status_text = '';
                                                    $days_text = '';
                                                    $dot_class = '';
                                                    $estatus_pago = isset($row["estatus_pago"]) ? $row["estatus_pago"] : 0;
                                                    
                                                    if (!empty($row["fecha_pago"]) && $row["fecha_pago"] !== '0000-00-00') {
                                                        $fecha_pago = $row["fecha_pago"];
                                                        if ($fecha_pago < $hoy) {
                                                            $status_text = 'Vencido';
                                                            $dot_class = 'vencido';
                                                        } else {
                                                            $dias = (strtotime($fecha_pago) - strtotime($hoy)) / (60 * 60 * 24);
                                                            $dias_rounded = round($dias);
                                                            
                                                            if ($dias <= 7) {
                                                                $dot_class = 'warning';
                                                                $status_text = 'Crítico';
                                                                $days_text = "<span class='days-badge critical'><i class='fas fa-exclamation-circle me-1'></i> {$dias_rounded} días</span>";
                                                            } elseif ($dias <= 15) {
                                                                $dot_class = 'warning';
                                                                $status_text = 'Pronto';
                                                                $days_text = "<span class='days-badge warning'><i class='fas fa-hourglass-half me-1'></i> {$dias_rounded} días</span>";
                                                            } elseif ($dias <= 30) {
                                                                $dot_class = 'info';
                                                                $status_text = 'Próximo';
                                                                $days_text = "<span class='days-badge normal'><i class='fas fa-calendar-day me-1'></i> {$dias_rounded} días</span>";
                                                            } else {
                                                                $dot_class = 'success';
                                                                $status_text = 'Al día';
                                                                $days_text = "<span class='days-badge normal'><i class='fas fa-check-circle me-1'></i> {$dias_rounded} días</span>";
                                                            }
                                                        }
                                                    } else {
                                                        $status_text = 'Sin fecha';
                                                        $dot_class = 'secondary';
                                                        $days_text = '<span class="days-badge normal">Sin definir</span>';
                                                    }
                                                ?>
                                                    <tr>
                                                        <td><span class="badge-id">#<?php echo $row["id_dominio"]; ?></span></td>
                                                        <td>
                                                            <div class="fw-semibold"><?php echo $row["nombre_contacto"]; ?></div>
                                                            <small class="text-secondary-custom">ID: <?php echo $row["cliente_id"]; ?></small>
                                                        </td>
                                                        <td>
                                                            <div class="fw-semibold"><?php echo $row["url_dominio"]; ?></div>
                                                            <small class="text-secondary-custom"><?php echo $row["proveedor"]; ?></small>
                                                        </td>
                                                        <td>
                                                            <span class="badge-status badge-registrado">
                                                                <i class="fas fa-building me-1"></i>ConlineWeb
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge-status <?php echo $row["estado_dominio"] == 1 ? 'badge-active' : 'badge-inactive'; ?>">
                                                                <?php echo $row["estado_dominio"] == 1 ? 'Activo' : 'Inactivo'; ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo $row["proveedor"]; ?></td>
                                                        <td class="fw-medium">
                                                            <?php echo !empty($row["fecha_pago"]) && $row["fecha_pago"] !== "0000-00-00" ? date("d/m/Y", strtotime($row["fecha_pago"])) : "-"; ?>
                                                        </td>
                                                        <td>
                                                            <div class="status-indicator">
                                                                <?php if ($estatus_pago == 0): ?>
                                                                    <span class="status-dot vencido"></span>
                                                                    <span class="badge-status badge-no-pagado">No pagado</span>
                                                                <?php else: ?>
                                                                    <span class="status-dot success"></span>
                                                                    <span class="badge-status badge-pagado">Pagado</span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <?php echo $days_text; ?>
                                                        </td>
                                                        <td class="fw-semibold"><?php echo !empty($row["costo_dominio"]) ? '$' . number_format($row["costo_dominio"], 2) : "-"; ?></td>
                                                        <td class="fw-medium"><?php echo $row["id_forma_pago"] == 1 ? 'MXN' : ($row["id_forma_pago"] == 2 ? 'USD' : '-'); ?></td>
                                                        <td>
                                                            <div class="adm-actions">
                                                                <?php if ($estatus_pago == 0): ?>
                                                                    <button type="button" class="adm-act adm-act--info marcar-pagado-btn" data-id="<?php echo $row["id_dominio"]; ?>" title="Marcar como pagado">
                                                                        <i class="fas fa-check"></i>Marcar pagado
                                                                    </button>
                                                                <?php endif; ?>
                                                                <button type="button" class="adm-act adm-act--success enviar-correo-btn" data-id="<?php echo $row["id_dominio"]; ?>" title="Enviar correo">
                                                                    <i class="fas fa-paper-plane"></i>Correo
                                                                </button>
                                                                <?php
                                                                $estReg = (int) ($row['estado_dominio'] ?? 0);
                                                                $btnCls = $estReg === 1 ? 'adm-act--secondary' : 'adm-act--success';
                                                                $btnIcon = $estReg === 1 ? 'toggle-on' : 'toggle-off';
                                                                $btnTxt = $estReg === 1 ? 'Desactivar' : 'Activar';
                                                                ?>
                                                                <button type="button" class="adm-act <?= $btnCls ?> toggle-estado-dominio-btn"
                                                                        data-id="<?php echo (int) $row['id_dominio']; ?>"
                                                                        data-estado="<?= $estReg ?>"
                                                                        title="<?= $btnTxt ?> dominio">
                                                                    <i class="fas fa-<?= $btnIcon ?>"></i><?= $btnTxt ?>
                                                                </button>
                                                                <button type="button" class="adm-act adm-act--warn editar-info-btn" data-id="<?php echo $row["id_dominio"]; ?>" title="Editar">
                                                                    <i class="fas fa-pencil-alt"></i>Editar
                                                                </button>
                                                                <button type="button" class="adm-act adm-act--danger eliminar-dominio-btn" data-id="<?php echo $row["id_dominio"]; ?>" title="Eliminar">
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
                                            <p class="text-secondary-custom">No hay dominios gestionados por ConlineWeb</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Sub-tab: Dominios No Registrados -->
                            <div class="tab-pane fade" id="sub-no-registrados" role="tabpanel">
                                <!-- Filtros para no registrados -->
                                <div class="filter-section">
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <div class="filter-label">Rango de Fechas</div>
                                            <input type="text" id="rangoFechasPagoNoRegistrados" class="form-control-modern w-100" placeholder="Seleccionar rango">
                                        </div>
                                        <div class="col-md-2">
                                            <div class="filter-label">Mes</div>
                                            <select id="filtroMesNoRegistrados" class="form-control-modern w-100">
                                                <option value="todos">Todos</option>
                                                <option value="0">Enero</option>
                                                <option value="1">Febrero</option>
                                                <option value="2">Marzo</option>
                                                <option value="3">Abril</option>
                                                <option value="4">Mayo</option>
                                                <option value="5">Junio</option>
                                                <option value="6">Julio</option>
                                                <option value="7">Agosto</option>
                                                <option value="8">Septiembre</option>
                                                <option value="9">Octubre</option>
                                                <option value="10">Noviembre</option>
                                                <option value="11">Diciembre</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="filter-label">Año</div>
                                            <select id="filtroAnioNoRegistrados" class="form-control-modern w-100">
                                                <option value="todos">Todos</option>
                                                <?php
                                                for($i = $anio_actual - 2; $i <= $anio_actual + 2; $i++) {
                                                    echo "<option value='$i'>$i</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="filter-label">Estado de Pago</div>
                                            <select id="filtroEstadoPagoNoRegistrados" class="form-control-modern w-100">
                                                <option value="todos">Todos</option>
                                                <option value="vencidos">Vencidos</option>
                                                <option value="proximos">Próximos a vencer</option>
                                                <option value="pagados">Pagados</option>
                                                <option value="no_pagados">No pagados</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="filter-label">&nbsp;</div>
                                            <button class="btn-clear w-100" onclick="limpiarFiltrosNoRegistrados()">
                                                <i class="fas fa-eraser me-2"></i>Limpiar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="modern-table">
                                    <?php if (count($dominios_no_registrados) > 0): ?>
                                        <table class="table" id="dataTableNoRegistrados" width="100%">
                                            <thead>
                                                <tr style="background: var(--primary-soft);">
                                                    <th>ID</th>
                                                    <th>Cliente</th>
                                                    <th>Dominio</th>
                                                    <th>Gestión</th>
                                                    <th>Estado</th>
                                                    <th>Proveedor</th>
                                                    <th>Fecha Pago</th>
                                                    <th>Estado Pago</th>
                                                    <th>Costo</th>
                                                    <th>Moneda</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($dominios_no_registrados as $row): 
                                                    $status_text = '';
                                                    $days_text = '';
                                                    $dot_class = '';
                                                    $estatus_pago = isset($row["estatus_pago"]) ? $row["estatus_pago"] : 0;
                                                    
                                                    if (!empty($row["fecha_pago"]) && $row["fecha_pago"] !== '0000-00-00') {
                                                        $fecha_pago = $row["fecha_pago"];
                                                        if ($fecha_pago < $hoy) {
                                                            $status_text = 'Vencido';
                                                            $dot_class = 'vencido';
                                                        } else {
                                                            $dias = (strtotime($fecha_pago) - strtotime($hoy)) / (60 * 60 * 24);
                                                            $dias_rounded = round($dias);
                                                            
                                                            if ($dias <= 7) {
                                                                $dot_class = 'warning';
                                                                $status_text = 'Crítico';
                                                                $days_text = "<span class='days-badge critical'><i class='fas fa-exclamation-circle me-1'></i> {$dias_rounded} días</span>";
                                                            } elseif ($dias <= 15) {
                                                                $dot_class = 'warning';
                                                                $status_text = 'Pronto';
                                                                $days_text = "<span class='days-badge warning'><i class='fas fa-hourglass-half me-1'></i> {$dias_rounded} días</span>";
                                                            } elseif ($dias <= 30) {
                                                                $dot_class = 'info';
                                                                $status_text = 'Próximo';
                                                                $days_text = "<span class='days-badge normal'><i class='fas fa-calendar-day me-1'></i> {$dias_rounded} días</span>";
                                                            } else {
                                                                $dot_class = 'success';
                                                                $status_text = 'Al día';
                                                                $days_text = "<span class='days-badge normal'><i class='fas fa-check-circle me-1'></i> {$dias_rounded} días</span>";
                                                            }
                                                        }
                                                    } else {
                                                        $status_text = 'Sin fecha';
                                                        $dot_class = 'secondary';
                                                        $days_text = '<span class="days-badge normal">Sin definir</span>';
                                                    }
                                                ?>
                                                    <tr>
                                                        <td><span class="badge-id">#<?php echo $row["id_dominio"]; ?></span></td>
                                                        <td>
                                                            <div class="fw-semibold"><?php echo $row["nombre_contacto"]; ?></div>
                                                            <small class="text-secondary-custom">ID: <?php echo $row["cliente_id"]; ?></small>
                                                        </td>
                                                        <td>
                                                            <div class="fw-semibold"><?php echo $row["url_dominio"]; ?></div>
                                                            <small class="text-secondary-custom"><?php echo $row["proveedor"]; ?></small>
                                                        </td>
                                                        <td>
                                                            <span class="badge-status badge-no-registrado">
                                                                <i class="fas fa-external-link-alt me-1"></i>Externo
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge-status <?php echo $row["estado_dominio"] == 1 ? 'badge-active' : 'badge-inactive'; ?>">
                                                                <?php echo $row["estado_dominio"] == 1 ? 'Activo' : 'Inactivo'; ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo $row["proveedor"]; ?></td>
                                                        <td class="fw-medium">
                                                            <?php echo !empty($row["fecha_pago"]) && $row["fecha_pago"] !== "0000-00-00" ? date("d/m/Y", strtotime($row["fecha_pago"])) : "-"; ?>
                                                        </td>
                                                        <td>
                                                            <div class="status-indicator">
                                                                <?php if ($estatus_pago == 0): ?>
                                                                    <span class="status-dot vencido"></span>
                                                                    <span class="badge-status badge-no-pagado">No pagado</span>
                                                                <?php else: ?>
                                                                    <span class="status-dot success"></span>
                                                                    <span class="badge-status badge-pagado">Pagado</span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <?php echo $days_text; ?>
                                                        </td>
                                                        <td class="fw-semibold"><?php echo !empty($row["costo_dominio"]) ? '$' . number_format($row["costo_dominio"], 2) : "-"; ?></td>
                                                        <td class="fw-medium"><?php echo $row["id_forma_pago"] == 1 ? 'MXN' : ($row["id_forma_pago"] == 2 ? 'USD' : '-'); ?></td>
                                                        <td>
                                                            <div class="adm-actions">
                                                                <?php if ($estatus_pago == 0): ?>
                                                                    <button type="button" class="adm-act adm-act--info marcar-pagado-btn" data-id="<?php echo $row["id_dominio"]; ?>" title="Marcar como pagado">
                                                                        <i class="fas fa-check"></i>Marcar pagado
                                                                    </button>
                                                                <?php endif; ?>
                                                                <button type="button" class="adm-act adm-act--success enviar-correo-btn" data-id="<?php echo $row["id_dominio"]; ?>" title="Enviar correo">
                                                                    <i class="fas fa-paper-plane"></i>Correo
                                                                </button>
                                                                <?php
                                                                $estExt = (int) ($row['estado_dominio'] ?? 0);
                                                                $btnClsE = $estExt === 1 ? 'adm-act--secondary' : 'adm-act--success';
                                                                $btnIconE = $estExt === 1 ? 'toggle-on' : 'toggle-off';
                                                                $btnTxtE = $estExt === 1 ? 'Desactivar' : 'Activar';
                                                                ?>
                                                                <button type="button" class="adm-act <?= $btnClsE ?> toggle-estado-dominio-btn"
                                                                        data-id="<?php echo (int) $row['id_dominio']; ?>"
                                                                        data-estado="<?= $estExt ?>"
                                                                        title="<?= $btnTxtE ?> dominio">
                                                                    <i class="fas fa-<?= $btnIconE ?>"></i><?= $btnTxtE ?>
                                                                </button>
                                                                <button type="button" class="adm-act adm-act--warn editar-info-btn" data-id="<?php echo $row["id_dominio"]; ?>" title="Editar">
                                                                    <i class="fas fa-pencil-alt"></i>Editar
                                                                </button>
                                                                <button type="button" class="adm-act adm-act--danger eliminar-dominio-btn" data-id="<?php echo $row["id_dominio"]; ?>" title="Eliminar">
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
                                            <p class="text-secondary-custom">No hay dominios en proveedor externo</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- TAB ELIMINADOS -->
                    <div class="tab-pane fade" id="eliminados" role="tabpanel">
                        <div class="filter-section">
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <div class="filter-label">Mes</div>
                                    <select id="filtroMesEliminados" class="form-control-modern w-100">
                                        <option value="todos">Todos</option>
                                        <option value="0">Enero</option>
                                        <option value="1">Febrero</option>
                                        <option value="2">Marzo</option>
                                        <option value="3">Abril</option>
                                        <option value="4">Mayo</option>
                                        <option value="5">Junio</option>
                                        <option value="6">Julio</option>
                                        <option value="7">Agosto</option>
                                        <option value="8">Septiembre</option>
                                        <option value="9">Octubre</option>
                                        <option value="10">Noviembre</option>
                                        <option value="11">Diciembre</option>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <div class="filter-label">Año</div>
                                    <select id="filtroAnioEliminados" class="form-control-modern w-100">
                                        <option value="todos">Todos</option>
                                        <?php
                                        for($i = $anio_actual - 2; $i <= $anio_actual + 2; $i++) {
                                            echo "<option value='$i'>$i</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <div class="filter-label">&nbsp;</div>
                                    <button class="btn-clear w-100" onclick="limpiarFiltrosEliminados()">
                                        <i class="fas fa-eraser me-2"></i>Limpiar
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="modern-table">
                            <?php if (count($dominios_eliminados) > 0): ?>
                                <table class="table" id="dataTableEliminados" width="100%">
                                    <thead>
                                        <tr style="background: var(--primary-soft);">
                                            <th>ID</th>
                                            <th>Cliente</th>
                                            <th>Dominio</th>
                                            <th>Estado</th>
                                            <th>Proveedor</th>
                                            <th>Fecha Pago</th>
                                            <th>Costo</th>
                                            <th>Moneda</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($dominios_eliminados as $row): ?>
                                            <tr>
                                                <td><span class="badge-id">#<?php echo $row["id_dominio"]; ?></span></td>
                                                <td>
                                                    <div class="fw-semibold"><?php echo $row["nombre_contacto"]; ?></div>
                                                    <small class="text-secondary-custom">ID: <?php echo $row["cliente_id"]; ?></small>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold"><?php echo $row["url_dominio"]; ?></div>
                                                    <small class="text-secondary-custom"><?php echo $row["proveedor"]; ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge-status badge-eliminado">Eliminado</span>
                                                </td>
                                                <td><?php echo $row["proveedor"]; ?></td>
                                                <td class="fw-medium">
                                                    <?php echo !empty($row["fecha_pago"]) && $row["fecha_pago"] !== "0000-00-00" ? date("d/m/Y", strtotime($row["fecha_pago"])) : "-"; ?>
                                                </td>
                                                <td class="fw-semibold"><?php echo !empty($row["costo_dominio"]) ? '$' . number_format($row["costo_dominio"], 2) : "-"; ?></td>
                                                <td class="fw-medium"><?php echo $row["id_forma_pago"] == 1 ? 'MXN' : ($row["id_forma_pago"] == 2 ? 'USD' : '-'); ?></td>
                                                <td>
                                                    <div class="adm-actions">
                                                        <button type="button" class="adm-act adm-act--success restaurar-dominio-btn" data-id="<?php echo $row["id_dominio"]; ?>">
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
                                    <p class="text-secondary-custom">No se encontraron dominios eliminados</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Edición con iframe -->
    <div class="modal fade" id="editarDominioModal" tabindex="-1" role="dialog" aria-labelledby="editarDominioModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content" style="border-radius: 24px; border: none;">
                <div class="modal-header" style="border-bottom: 1px solid var(--border); padding: 20px 24px;">
                    <h5 class="modal-title" style="font-weight: 700; color: var(--primary-dark);" id="editarDominioModalLabel">Editar Dominio</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0" style="height: 80vh;">
                    <iframe id="iframeEditarDominio" src="" style="width:100%;height:100%;border:none;border-radius: 0 0 24px 24px;"></iframe>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="js/sb-admin-2.min.js"></script>
    <script src="vendor/datatables/jquery.dataTables.min.js"></script>
    <script src="vendor/datatables/dataTables.bootstrap4.min.js"></script>
    <script src="js/admin-datatables-defaults.js?v=20250714"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function () {
            let pagos = <?php echo json_encode($pagos); ?>;
            let dominios = <?php echo json_encode($dominios); ?>;
            const sistemaActivo = '<?php echo $sistema; ?>';
            let dataTableActivos = null;
            let dataTableInactivos = null;
            let dataTableRegistrados = null;
            let dataTableNoRegistrados = null;
            let dataTableEliminados = null;
            
            // Inicializar DataTables
            function initDataTables() {
                // Tabla de activos
                if ($('#dataTableActivos').length && !$.fn.DataTable.isDataTable('#dataTableActivos')) {
                    dataTableActivos = $('#dataTableActivos').DataTable({
                        searching: true,
                        language: { search: "Buscar:", searchPlaceholder: "Buscar...", lengthMenu: "Mostrar _MENU_ registros", info: "Mostrando _START_ a _END_ de _TOTAL_ registros" },
                        columnDefs: [{ type: 'date', targets: [6] }],
                        order: [[6, 'asc']],
                        pageLength: 25
                    });
                }
                
                // Tabla de inactivos
                if ($('#dataTableInactivos').length && !$.fn.DataTable.isDataTable('#dataTableInactivos')) {
                    dataTableInactivos = $('#dataTableInactivos').DataTable({
                        searching: true,
                        language: { search: "Buscar:", searchPlaceholder: "Buscar...", lengthMenu: "Mostrar _MENU_ registros", info: "Mostrando _START_ a _END_ de _TOTAL_ registros" },
                        columnDefs: [{ type: 'date', targets: [6] }],
                        pageLength: 25
                    });
                }
                
                // Tabla de registrados
                if ($('#dataTableRegistrados').length && !$.fn.DataTable.isDataTable('#dataTableRegistrados')) {
                    dataTableRegistrados = $('#dataTableRegistrados').DataTable({
                        searching: true,
                        language: { search: "Buscar:", searchPlaceholder: "Buscar...", lengthMenu: "Mostrar _MENU_ registros", info: "Mostrando _START_ a _END_ de _TOTAL_ registros" },
                        columnDefs: [{ type: 'date', targets: [6] }],
                        order: [[6, 'asc']],
                        pageLength: 25
                    });
                }
                
                // Tabla de no registrados
                if ($('#dataTableNoRegistrados').length && !$.fn.DataTable.isDataTable('#dataTableNoRegistrados')) {
                    dataTableNoRegistrados = $('#dataTableNoRegistrados').DataTable({
                        searching: true,
                        language: { search: "Buscar:", searchPlaceholder: "Buscar...", lengthMenu: "Mostrar _MENU_ registros", info: "Mostrando _START_ a _END_ de _TOTAL_ registros" },
                        columnDefs: [{ type: 'date', targets: [6] }],
                        order: [[6, 'asc']],
                        pageLength: 25
                    });
                }
                
                // Tabla de eliminados
                if ($('#dataTableEliminados').length && !$.fn.DataTable.isDataTable('#dataTableEliminados')) {
                    dataTableEliminados = $('#dataTableEliminados').DataTable({
                        searching: true,
                        language: { search: "Buscar:", searchPlaceholder: "Buscar..." },
                        columnDefs: [{ type: 'date', targets: [5] }]
                    });
                }
            }
            
            // Función genérica para aplicar filtros de fecha, mes, año y estado
            function aplicarFiltros(dataTable, fechaIndex, mesSelectId, anioSelectId, estadoSelectId, hoy) {
                const rangoFechas = $(`#${dataTable.table().container().id.replace('dataTable', 'rangoFechasPago')}`).val();
                const mes = $(mesSelectId).val();
                const anio = $(anioSelectId).val();
                const estado = $(estadoSelectId).val();
                
                $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                    const fechaPagoStr = data[fechaIndex];
                    if (!fechaPagoStr || fechaPagoStr === '-') return true;
                    const partes = fechaPagoStr.split('/');
                    const fecha = new Date(partes[2], partes[1] - 1, partes[0]);
                    
                    // Filtro por estado de pago
                    if (estado !== 'todos') {
                        const isPagado = data[fechaIndex + 1].includes('Pagado');
                        switch(estado) {
                            case 'vencidos': if (fecha >= hoy) return false; break;
                            case 'proximos': 
                                const diffDays = Math.ceil((fecha - hoy) / (1000 * 60 * 60 * 24));
                                if (!(diffDays > 0 && diffDays <= 30)) return false;
                                break;
                            case 'pagados': if (!isPagado) return false; break;
                            case 'no_pagados': if (isPagado) return false; break;
                            case 'futuros': if (fecha <= hoy) return false; break;
                        }
                    }
                    
                    // Filtro por mes y año
                    const fechaMes = parseInt(partes[1]) - 1;
                    const fechaAnio = parseInt(partes[2]);
                    let mesMatch = (mes === 'todos' || fechaMes === parseInt(mes));
                    let anioMatch = (anio === 'todos' || fechaAnio === parseInt(anio));
                    
                    return mesMatch && anioMatch;
                });
                dataTable.draw();
                $.fn.dataTable.ext.search.pop();
            }
            
            // Configurar filtros para cada tabla
            function setupFilters() {
                // Filtros para activos
                flatpickr("#rangoFechasPagoActivos", { mode: "range", dateFormat: "d/m/Y", locale: "es" });
                $('#filtroMesActivos, #filtroAnioActivos, #filtroEstadoPagoActivos').on('change', function() {
                    if (dataTableActivos) aplicarFiltros(dataTableActivos, 6, '#filtroMesActivos', '#filtroAnioActivos', '#filtroEstadoPagoActivos', new Date());
                });
                
                // Filtros para inactivos
                flatpickr("#rangoFechasPagoInactivos", { mode: "range", dateFormat: "d/m/Y", locale: "es" });
                $('#filtroMesInactivos, #filtroAnioInactivos, #filtroEstadoPagoInactivos').on('change', function() {
                    if (dataTableInactivos) aplicarFiltros(dataTableInactivos, 6, '#filtroMesInactivos', '#filtroAnioInactivos', '#filtroEstadoPagoInactivos', new Date());
                });
                
                // Filtros para registrados
                flatpickr("#rangoFechasPagoRegistrados", { mode: "range", dateFormat: "d/m/Y", locale: "es" });
                $('#filtroMesRegistrados, #filtroAnioRegistrados, #filtroEstadoPagoRegistrados').on('change', function() {
                    if (dataTableRegistrados) aplicarFiltros(dataTableRegistrados, 6, '#filtroMesRegistrados', '#filtroAnioRegistrados', '#filtroEstadoPagoRegistrados', new Date());
                });
                
                // Filtros para no registrados
                flatpickr("#rangoFechasPagoNoRegistrados", { mode: "range", dateFormat: "d/m/Y", locale: "es" });
                $('#filtroMesNoRegistrados, #filtroAnioNoRegistrados, #filtroEstadoPagoNoRegistrados').on('change', function() {
                    if (dataTableNoRegistrados) aplicarFiltros(dataTableNoRegistrados, 6, '#filtroMesNoRegistrados', '#filtroAnioNoRegistrados', '#filtroEstadoPagoNoRegistrados', new Date());
                });
                
                // Filtros para eliminados
                $('#filtroMesEliminados, #filtroAnioEliminados').on('change', function() {
                    if (dataTableEliminados) {
                        const mes = $('#filtroMesEliminados').val();
                        const anio = $('#filtroAnioEliminados').val();
                        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                            const fechaPagoStr = data[5];
                            if (!fechaPagoStr || fechaPagoStr === '-') return mes === 'todos' && anio === 'todos';
                            const partes = fechaPagoStr.split('/');
                            const fechaMes = parseInt(partes[1]) - 1;
                            const fechaAnio = parseInt(partes[2]);
                            let mesMatch = (mes === 'todos' || fechaMes === parseInt(mes));
                            let anioMatch = (anio === 'todos' || fechaAnio === parseInt(anio));
                            return mesMatch && anioMatch;
                        });
                        dataTableEliminados.draw();
                        $.fn.dataTable.ext.search.pop();
                    }
                });
            }
            
            // Funciones de limpieza
            window.limpiarFiltrosActivos = function() {
                $('#rangoFechasPagoActivos').val('');
                $('#filtroMesActivos').val('todos');
                $('#filtroAnioActivos').val('todos');
                $('#filtroEstadoPagoActivos').val('todos');
                if (dataTableActivos) dataTableActivos.search('').draw();
                Swal.fire('Filtros limpiados', '', 'success');
            };
            
            window.limpiarFiltrosInactivos = function() {
                $('#rangoFechasPagoInactivos').val('');
                $('#filtroMesInactivos').val('todos');
                $('#filtroAnioInactivos').val('todos');
                $('#filtroEstadoPagoInactivos').val('todos');
                if (dataTableInactivos) dataTableInactivos.search('').draw();
                Swal.fire('Filtros limpiados', '', 'success');
            };
            
            window.limpiarFiltrosRegistrados = function() {
                $('#rangoFechasPagoRegistrados').val('');
                $('#filtroMesRegistrados').val('todos');
                $('#filtroAnioRegistrados').val('todos');
                $('#filtroEstadoPagoRegistrados').val('todos');
                if (dataTableRegistrados) dataTableRegistrados.search('').draw();
                Swal.fire('Filtros limpiados', '', 'success');
            };
            
            window.limpiarFiltrosNoRegistrados = function() {
                $('#rangoFechasPagoNoRegistrados').val('');
                $('#filtroMesNoRegistrados').val('todos');
                $('#filtroAnioNoRegistrados').val('todos');
                $('#filtroEstadoPagoNoRegistrados').val('todos');
                if (dataTableNoRegistrados) dataTableNoRegistrados.search('').draw();
                Swal.fire('Filtros limpiados', '', 'success');
            };
            
            window.limpiarFiltrosEliminados = function() {
                $('#filtroMesEliminados').val('todos');
                $('#filtroAnioEliminados').val('todos');
                if (dataTableEliminados) dataTableEliminados.search('').draw();
            };
            
            // Función para filtrar desde el dashboard
            window.filtrarPorEstado = function(estado) {
                $('#activos-tab').tab('show');
                setTimeout(function() {
                    $('#sub-activos-tab').tab('show');
                    setTimeout(function() {
                        switch(estado) {
                            case 'vencidos':
                                $('#filtroEstadoPagoActivos').val('vencidos').trigger('change');
                                break;
                            case '30_dias':
                            case '15_dias':
                            case '7_dias':
                                $('#filtroEstadoPagoActivos').val('proximos').trigger('change');
                                break;
                            case 'pagados':
                                $('#filtroEstadoPagoActivos').val('pagados').trigger('change');
                                break;
                            case 'no_pagados':
                                $('#filtroEstadoPagoActivos').val('no_pagados').trigger('change');
                                break;
                        }
                    }, 200);
                }, 200);
            };
            
            // Funciones originales (marcar pagado, enviar correo, editar, eliminar, restaurar)
            $(document).on('click', '.marcar-pagado-btn', function () {
                const $btn = $(this);
                const id_dominio = $btn.data('id');
                
                Swal.fire({
                    title: '¿Marcar como pagado?',
                    text: '¿Estás seguro de que deseas marcar este dominio como pagado?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#000147',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sí, marcar como pagado',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
                        
                        $.ajax({
                            url: 'cambiar_estatus_pago_dominio.php',
                            type: 'POST',
                            dataType: 'json',
                            data: { id_dominio: id_dominio, estatus_pago: 1 },
                            success: function (response) {
                                if (response.success) {
                                    location.reload();
                                } else {
                                    $btn.prop('disabled', false).html('<i class="fas fa-check"></i>');
                                    Swal.fire('Error', response.message || 'No se pudo actualizar el estatus', 'error');
                                }
                            },
                            error: function () {
                                $btn.prop('disabled', false).html('<i class="fas fa-check"></i>');
                                Swal.fire('Error', 'Error al conectar con el servidor', 'error');
                            }
                        });
                    }
                });
            });
            
            $('.enviar-correo-btn').click(function () {
                const dominioId = $(this).data('id');
                let pagosFiltrados = pagos.filter((pago) => pago.id_servicio == dominioId && pago.estatus == 0);
                
                if (pagosFiltrados.length > 0) {
                    Swal.fire({
                        title: '¿Enviar correos?',
                        text: `Se enviarán ${pagosFiltrados.length} correos. ¿Deseas continuar?`,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#000147',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Sí, enviar',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            Swal.fire({ title: 'Enviando correos...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
                            
                            let promises = pagosFiltrados.map(pago => {
                                return $.ajax({ url: 'reenviar_correos_dominios.php', type: 'POST', dataType: 'json', data: { id: pago.id } });
                            });
                            
                            Promise.all(promises).then(function(results) {
                                Swal.close();
                                let successCount = results.filter(r => r.success).length;
                                let errorCount = results.filter(r => !r.success).length;
                                if (errorCount === 0) {
                                    Swal.fire('Éxito', `Todos los ${successCount} correos se enviaron correctamente`, 'success');
                                } else {
                                    Swal.fire('Resultado', `${successCount} correos enviados, ${errorCount} fallidos`, 'info');
                                }
                            }).catch(function(error) {
                                Swal.fire('Error', 'Ocurrió un error al enviar los correos', 'error');
                            });
                        }
                    });
                } else {
                    Swal.fire('Información', 'No hay correos pendientes por enviar para este dominio', 'info');
                }
            });
            
            $(document).on('click', '.toggle-estado-dominio-btn', function () {
                const idDominio = parseInt($(this).data('id'), 10) || 0;
                const estadoActual = parseInt($(this).data('estado'), 10);
                if (!idDominio) {
                    Swal.fire('Error', 'ID de dominio no válido', 'error');
                    return;
                }
                const nuevoEstado = estadoActual === 1 ? 0 : 1;
                const accion = nuevoEstado === 1 ? 'Activar' : 'Desactivar';
                Swal.fire({
                    title: '¿' + accion + ' dominio?',
                    text: 'El dominio pasará a estado ' + (nuevoEstado === 1 ? 'Activo' : 'Inactivo') + '.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: nuevoEstado === 1 ? '#16a34a' : '#dc2626',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sí, ' + accion.toLowerCase(),
                    cancelButtonText: 'Cancelar'
                }).then(function (result) {
                    if (!result.isConfirmed) return;
                    Swal.fire({ title: 'Actualizando...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
                    $.ajax({
                        url: 'actualizar_estado_dominio.php',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            id_dominio: idDominio,
                            estado: nuevoEstado,
                            sistema: sistemaActivo
                        },
                        success: function (response) {
                            Swal.close();
                            if (response && response.success) {
                                Swal.fire('¡Listo!', response.message || 'Estado actualizado', 'success')
                                    .then(function () { location.reload(); });
                            } else {
                                Swal.fire('Error', (response && response.message) || 'No se pudo actualizar el estado', 'error');
                            }
                        },
                        error: function () {
                            Swal.fire('Error', 'Error al conectar con el servidor', 'error');
                        }
                    });
                });
            });

            $(document).on('click', '.editar-info-btn', function () {
                const id = $(this).data('id');
                const dominio = dominios.find(d => d.id_dominio == id);
                if (dominio) {
                    const url = `https://adm.conlineweb.com/formulario_dominio.php?edit=1&id_dominio=${dominio.id_dominio}&id_cliente=${dominio.cliente_id}&iframe=1&novalid=1&sistema=${sistemaActivo}`;
                    $('#iframeEditarDominio').attr('src', url);
                    $('#editarDominioModal').modal('show');
                } else {
                    Swal.fire('Error', 'No se encontró el dominio solicitado', 'error');
                }
            });
            
            $(document).on('click', '.eliminar-dominio-btn', function () {
                const id_dominio = $(this).data('id');
                Swal.fire({
                    title: '¿Eliminar dominio?',
                    text: '¿Estás seguro de que deseas eliminar este dominio?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#000147',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({ title: 'Eliminando...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
                        $.ajax({
                            url: 'eliminar_dominio.php',
                            type: 'POST',
                            dataType: 'json',
                            data: { id: id_dominio, eliminar: 1, sistema: sistemaActivo },
                            success: function (response) {
                                Swal.close();
                                if (response.success) {
                                    Swal.fire('¡Eliminado!', 'El dominio ha sido eliminado correctamente', 'success').then(() => { location.reload(); });
                                } else {
                                    Swal.fire('Error', response.message || 'No se pudo eliminar', 'error');
                                }
                            },
                            error: function () { Swal.fire('Error', 'Error al conectar con el servidor', 'error'); }
                        });
                    }
                });
            });
            
            $(document).on('click', '.restaurar-dominio-btn', function () {
                const id_dominio = $(this).data('id');
                Swal.fire({
                    title: '¿Restaurar dominio?',
                    text: '¿Estás seguro de que deseas restaurar este dominio?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#000147',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Sí, restaurar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({ title: 'Restaurando...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
                        $.ajax({
                            url: 'eliminar_dominio.php',
                            type: 'POST',
                            dataType: 'json',
                            data: { id: id_dominio, eliminar: 0, sistema: sistemaActivo },
                            success: function (response) {
                                Swal.close();
                                if (response.success) {
                                    Swal.fire('¡Restaurado!', 'El dominio ha sido restaurado correctamente', 'success').then(() => { location.reload(); });
                                } else {
                                    Swal.fire('Error', response.message || 'No se pudo restaurar', 'error');
                                }
                            },
                            error: function () { Swal.fire('Error', 'Error al conectar con el servidor', 'error'); }
                        });
                    }
                });
            });
            
            window.addEventListener('message', function(event) {
                if (event.data && event.data.tipo === 'dominioActualizado') {
                    $('#editarDominioModal').modal('hide');
                    location.reload();
                }
            });
            
            initDataTables();
            setupFilters();
            
            // Al cambiar entre sub-tabs, reajustar DataTables
            $('#sub-activos-tab, #sub-inactivos-tab, #sub-registrados-tab, #sub-no-registrados-tab').on('shown.bs.tab', function (e) {
                if ($.fn.DataTable.isDataTable('#dataTableActivos')) $('#dataTableActivos').DataTable().columns.adjust().draw();
                if ($.fn.DataTable.isDataTable('#dataTableInactivos')) $('#dataTableInactivos').DataTable().columns.adjust().draw();
                if ($.fn.DataTable.isDataTable('#dataTableRegistrados')) $('#dataTableRegistrados').DataTable().columns.adjust().draw();
                if ($.fn.DataTable.isDataTable('#dataTableNoRegistrados')) $('#dataTableNoRegistrados').DataTable().columns.adjust().draw();
            });
            
            $('#dominiosTab').on('shown.bs.tab', function (e) {
                initDataTables();
                $.fn.dataTable.tables({visible: true, api: true}).columns.adjust();
            });
            
            $('#activos-tab').trigger('shown.bs.tab');

            $(window).on('resize.admDominiosDt', function () {
                $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
            });
        });
    </script>
</body>
</html>