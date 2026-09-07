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

// Consulta principal para obtener hosting o servicios web (Plan Pro)
if ($sistema === 'planpro') {
    // Para Plan Pro: mostrar servicios_web (planes comprados en plan_pro.php)
    $sql = "SELECT DISTINCT 
                sw.id AS id_orden,
                sw.cliente_id,
                sw.dominio AS nom_host,
                sw.plan_nombre AS dominio,
                sw.plan_nombre AS producto,
                sw.plan_categoria AS tipo_producto,
                sw.plan_precio AS costo_producto,
                sw.fecha_contratacion,
                sw.fecha_vencimiento AS fecha_pago,
                sw.periodicidad,
                sw.moneda AS currency,
                sw.id_forma_pago,
                CASE WHEN sw.estado = 'activo' THEN 1 ELSE 0 END AS estado_producto,
                sw.eliminado,
                sw.usuario_cpanel AS usuario,
                sw.password_cpanel AS contrasena,
                sw.url_cpanel AS url_admin,
                c.nombre_contacto,
                c.facturacion
            FROM servicios_web sw
            LEFT JOIN clientes c ON sw.cliente_id = c.id
            INNER JOIN pagos p ON sw.id = p.id_servicio AND p.tipo_servicio = 1
            WHERE p.sistema = 'conlineweb'
            ORDER BY sw.fecha_vencimiento >= CURDATE() DESC, sw.fecha_vencimiento ASC";
} else {
    // Para ADM ConlineWeb y HostingPro: mostrar hosting tradicional
    $sql = "SELECT DISTINCT h.*, c.nombre_contacto, c.facturacion
            FROM hosting h
            LEFT JOIN clientes c ON h.cliente_id = c.id
            ORDER BY h.fecha_pago >= CURDATE() DESC, h.fecha_pago ASC";
}

$result = $db->query($sql);
// Verificar si hay resultados y convertirlos a array
$hosting_activos = [];
$hosting_eliminados = [];
$hosting_inactivos = [];
$hosting_activos_estado = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        if (isset($row['eliminado']) && $row['eliminado'] == 1) {
            $hosting_eliminados[] = $row;
        } else {
            // Separar entre activos e inactivos por estado_producto
            if ($row['estado_producto'] == 1) {
                $hosting_activos_estado[] = $row;
            } else {
                $hosting_inactivos[] = $row;
            }
            $hosting_activos[] = $row;
        }
    }
}

$sqlPagos = "SELECT * FROM pagos WHERE tipo_servicio = 1";
$resultPagos = $db->query($sqlPagos);

/**
 * URL directa a cPanel/WHM a partir del registro de hosting.
 */
function adm_hosting_cpanel_url(array $row): string
{
    $candidates = [
        trim((string) ($row['url_acceso'] ?? '')),
        trim((string) ($row['url_admin'] ?? '')),
        trim((string) ($row['url_cpanel'] ?? '')),
    ];

    foreach ($candidates as $url) {
        if ($url === '' || $url === '---') {
            continue;
        }
        if (preg_match('/^https?:\/\//i', $url)) {
            return rtrim($url, '/');
        }
        $host = $url;
        if (strpos($host, ':') === false) {
            $host .= (stripos($host, 'whm') !== false) ? ':2086' : ':2083';
        }
        return 'https://' . ltrim($host, '/');
    }

    $nomHost = trim((string) ($row['nom_host'] ?? ''));
    if ($nomHost !== '' && $nomHost !== '---') {
        if (preg_match('/^https?:\/\//i', $nomHost)) {
            return rtrim($nomHost, '/');
        }
        $host = preg_replace('#^https?://#i', '', $nomHost);
        $host = rtrim((string) $host, '/');
        if ($host === '') {
            return '';
        }
        if (strpos($host, ':') === false) {
            $host .= (stripos($host, 'whm') !== false) ? ':2086' : ':2083';
        }
        return 'https://' . $host;
    }

    $dominio = trim((string) ($row['dominio'] ?? ''));
    $dominio = preg_replace('#^https?://#i', '', $dominio);
    $dominio = preg_replace('#^www\.#i', '', (string) $dominio);
    $dominio = rtrim((string) $dominio, '/');
    if ($dominio !== '' && $dominio !== '---') {
        return 'https://cpanel.' . $dominio . ':2083';
    }

    return '';
}

function adm_hosting_panel_user(array $row): string
{
    $user = trim((string) ($row['usuario'] ?? ''));
    if ($user === '' || $user === '---') {
        $user = trim((string) ($row['usuario_cpanel'] ?? ''));
    }
    return ($user !== '' && $user !== '---') ? $user : '';
}

function adm_hosting_panel_pass(array $row): string
{
    $pass = trim((string) ($row['contrasena'] ?? ''));
    if ($pass === '' || $pass === '---') {
        $pass = trim((string) ($row['password_cpanel'] ?? ''));
    }
    if ($pass === '' || $pass === '---') {
        $pass = trim((string) ($row['contrasena_normal'] ?? ''));
    }
    return ($pass !== '' && $pass !== '---') ? $pass : '';
}

function adm_hosting_panel_type(array $row, string $url): string
{
    $nomHost = (string) ($row['nom_host'] ?? '');
    if (
        strpos($url, ':2086') !== false
        || stripos($url, 'whm') !== false
        || stripos($nomHost, 'whm') !== false
    ) {
        return 'whm';
    }
    return 'cpanel';
}

/**
 * Markup del botón de auto-login a cPanel/WHM (mismo flujo que portal clientes).
 */
function adm_hosting_cpanel_button(array $row): string
{
    $url = adm_hosting_cpanel_url($row);
    $user = adm_hosting_panel_user($row);
    $pass = adm_hosting_panel_pass($row);
    if ($url === '' || $user === '' || $pass === '') {
        return '';
    }
    $type = adm_hosting_panel_type($row, $url);
    $label = $type === 'whm' ? 'WHM' : 'cPanel';

    return '<button type="button"'
        . ' class="adm-act adm-act--info abrir-cpanel-btn"'
        . ' title="Entrar a ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ' (auto-login)"'
        . ' data-panel-url="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"'
        . ' data-panel-user="' . htmlspecialchars($user, ENT_QUOTES, 'UTF-8') . '"'
        . ' data-panel-pass="' . htmlspecialchars($pass, ENT_QUOTES, 'UTF-8') . '"'
        . ' data-panel-type="' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '"'
        . '>'
        . '<i class="fas fa-server"></i>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
        . '</button>';
}

// Verificar si hay resultados y convertirlos a array
$pagos = [];
if ($resultPagos && $resultPagos->num_rows > 0) {
    $pagos = $resultPagos->fetch_all(MYSQLI_ASSOC);
}

// Calcular estadísticas para el dashboard
$hoy = date('Y-m-d');
$mes_anterior = date('Y-m-d', strtotime('-1 month'));

// Contadores para servicios por vencer
$vencidos = 0;
$por_vencer_30 = 0;
$por_vencer_15 = 0;
$por_vencer_7 = 0;

foreach ($hosting_activos_estado as $hosting) {
    if (!empty($hosting['fecha_pago']) && $hosting['fecha_pago'] !== '0000-00-00') {
        $fecha_pago = $hosting['fecha_pago'];
        
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
foreach ($hosting_activos_estado as $hosting) {
    if (!empty($hosting['fecha_pago']) && $hosting['fecha_pago'] !== '0000-00-00') {
        $fecha_pago = $hosting['fecha_pago'];
        if ($fecha_pago >= $mes_anterior && $fecha_pago < $hoy) {
            $servicios_vencidos_mes_anterior[] = $hosting;
        }
    }
}
?>

<!DOCTYPE html>
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
<!-- Content Wrapper -->
<div id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <div class="container-xl my-4 py-4 legacy-touch" style="max-width: 96% !important;">
            
            <!-- Header -->
            <div class="d-flex align-items-center justify-content-between mb-4 fade-in-up">
                <div>
                    <h1 class="h2 mb-1" style="color: var(--primary-dark); font-weight: 700; letter-spacing: -0.02em;">
                        <?php echo $sistema === 'planpro' ? 'Planes Web' : 'Hosting'; ?>
                    </h1>
                    <p class="text-secondary-custom mb-0" style="font-weight: 500;">
                        <?php echo $sistema === 'planpro' ? 'Planes contratados en ConlineWeb.com' : 'Gestión y seguimiento de servicios de hosting'; ?>
                    </p>
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
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stat-card p-4" onclick="filtrarPorEstado('vencidos')">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number"><?php echo $vencidos; ?></div>
                                <div class="stat-label mt-1"><?php echo $sistema === 'planpro' ? 'Planes Vencidos' : 'Servicios Vencidos'; ?></div>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-exclamation-triangle fa-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
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
                
                <div class="col-xl-3 col-md-6 mb-4">
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
                
                <div class="col-xl-3 col-md-6 mb-4">
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
            </div>
            
            <!-- Alert Servicios Vencidos Mes Anterior -->
            <?php if (!empty($servicios_vencidos_mes_anterior)): ?>
            <div class="alert-modern mb-4 fade-in-up">
                <div class="d-flex align-items-start">
                    <i class="fas fa-bell text-warning me-3 mt-1" style="color: #f59e0b;"></i>
                    <div>
                        <strong class="d-block mb-1" style="color: #92400e;">¡Atención!</strong>
                        <span style="color: #78350f;"><?php echo count($servicios_vencidos_mes_anterior); ?> <?php echo $sistema === 'planpro' ? 'plan(es)' : 'servicio(s)'; ?> vencieron el mes pasado:</span>
                        <ul class="mt-2 mb-0" style="color: #78350f;">
                            <?php foreach ($servicios_vencidos_mes_anterior as $servicio): ?>
                                <li>
                                    <strong><?php echo htmlspecialchars($servicio['nom_host'] ?? $servicio['dominio']); ?></strong> - 
                                    <?php echo htmlspecialchars($servicio['nombre_contacto']); ?> - 
                                    Vencimiento: <?php echo date("d/m/Y", strtotime($servicio['fecha_pago'])); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <button type="button" class="btn-close ms-auto" data-dismiss="alert"></button>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Tabs principales -->
            <ul class="nav nav-tabs-modern" id="hostingTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="activos-tab" data-toggle="tab" href="#activos" role="tab">
                        <i class="<?php echo $sistema === 'planpro' ? 'fas fa-box-open' : 'fas fa-server'; ?> me-2"></i>Activos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="eliminados-tab" data-toggle="tab" href="#eliminados" role="tab">
                        <i class="fas fa-trash-alt me-2"></i>Eliminados
                    </a>
                </li>
            </ul>
            
            <div class="tab-content" id="hostingTabContent">
                <!-- TAB ACTIVOS -->
                <div class="tab-pane fade show active" id="activos" role="tabpanel">
                    <!-- Sub-tabs para filtrar por estado (Activos/Inactivos) -->
                    <ul class="nav sub-tabs" id="estadoSubTab" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="sub-activos-tab" data-toggle="tab" href="#sub-activos" role="tab">
                                <i class="fas fa-play-circle me-1"></i>Activos
                                <span class="badge bg-success ms-1" style="background: var(--success) !important;"><?php echo count($hosting_activos_estado); ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="sub-inactivos-tab" data-toggle="tab" href="#sub-inactivos" role="tab">
                                <i class="fas fa-stop-circle me-1"></i>Inactivos
                                <span class="badge bg-danger ms-1" style="background: var(--danger) !important;"><?php echo count($hosting_inactivos); ?></span>
                            </a>
                        </li>
                    </ul>
                    
                    <div class="tab-content">
                        <!-- Sub-tab: Servicios Activos -->
                        <div class="tab-pane fade show active" id="sub-activos" role="tabpanel">
                            <!-- Filtros -->
                            <div class="filter-section">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <div class="filter-label">Rango de Fechas</div>
                                        <input type="text" id="rangoFechasPago" class="form-control-modern w-100" placeholder="Seleccionar rango">
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
                                        <select id="filtroEstadoPago" class="form-control-modern w-100">
                                            <option value="todos">Todos</option>
                                            <option value="vencidos">Vencidos</option>
                                            <option value="proximos">Próximos a vencer</option>
                                            <option value="futuros">Futuros</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="filter-label">&nbsp;</div>
                                        <button class="btn-clear w-100" onclick="limpiarFiltros()">
                                            <i class="fas fa-eraser me-2"></i>Limpiar
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Tabla de Servicios Activos -->
                            <div class="modern-table">
                                <?php if (count($hosting_activos_estado) > 0): ?>
                                    <table class="table" id="dataTableActivos" width="100%">
                                        <thead>
                                            <tr style="background: var(--primary-soft);">
                                                <th>ID</th>
                                                <th>Cliente</th>
                                                <th><?php echo $sistema === 'planpro' ? 'Dominio' : 'Hosting'; ?></th>
                                                <th>Estado</th>
                                                <th>Plan</th>
                                                <th>Fecha <?php echo $sistema === 'planpro' ? 'Vencimiento' : 'Pago'; ?></th>
                                                <th>Estado Pago</th>
                                                <th>Costo</th>
                                                <th>Moneda</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($hosting_activos_estado as $row): 
                                                $status_class = '';
                                                $status_text = '';
                                                $days_text = '';
                                                $dot_class = '';
                                                
                                                if (!empty($row["fecha_pago"]) && $row["fecha_pago"] !== '0000-00-00') {
                                                    $fecha_pago = $row["fecha_pago"];
                                                    if ($fecha_pago < $hoy) {
                                                        $status_text = 'Vencido';
                                                        $dot_class = 'vencido';
                                                        $days_text = '';
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
                                                    <td>
                                                        <span class="badge-id">#<?php echo $row["id_orden"]; ?></span>
                                                    </td>
                                                    <td>
                                                        <div class="fw-semibold" style="color: var(--text-primary);"><?php echo $row["nombre_contacto"]; ?></div>
                                                        <small class="text-secondary-custom" style="font-size: 12px;">ID: <?php echo $row["cliente_id"]; ?></small>
                                                    </td>
                                                    <td>
                                                        <div class="fw-semibold" style="color: var(--text-primary);"><?php echo $row["nom_host"]; ?></div>
                                                        <small class="text-secondary-custom" style="font-size: 12px;"><?php echo $row["dominio"]; ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge-status badge-active">
                                                            <i class="fas fa-check-circle me-1"></i>Activo
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="fw-medium"><?php echo $row["producto"]; ?></div>
                                                        <small class="text-secondary-custom"><?php echo $row["tipo_producto"]; ?></small>
                                                    </td>
                                                    <td class="fw-medium" style="color: var(--text-primary);">
                                                        <?php echo (!empty($row["fecha_pago"]) && $row["fecha_pago"] !== '0000-00-00') ? date("d/m/Y", strtotime($row["fecha_pago"])): "-"; ?>
                                                    </td>
                                                    <td>
                                                        <div class="status-indicator">
                                                            <span class="status-dot <?php echo $dot_class; ?>"></span>
                                                            <span class="fw-medium" style="color: var(--text-primary);"><?php echo $status_text; ?></span>
                                                        </div>
                                                        <?php echo $days_text; ?>
                                                    </td>
                                                    <td class="fw-semibold" style="color: var(--text-primary);">
                                                        <?php echo (!empty($row["costo_producto"]) && $row["costo_producto"] !== '000') ? '$' . number_format($row["costo_producto"], 2) : "-"; ?>
                                                    </td>
                                                    <td class="fw-medium" style="color: var(--text-primary);">
                                                        <?php echo $row["id_forma_pago"] == 1 ? 'MXN' : ($row["id_forma_pago"] == 2 ? 'USD' : '-'); ?>
                                                    </td>
                                                    <td>
                                                        <div class="adm-actions">
                                                            <?= adm_hosting_cpanel_button($row) ?>
                                                            <button type="button" class="adm-act adm-act--success enviar-correo-btn" data-id="<?php echo $row["id_orden"]; ?>" title="Enviar correo">
                                                                <i class="fas fa-paper-plane"></i>Correo
                                                            </button>
                                                            <button type="button" class="adm-act adm-act--warn editar-info-btn" data-id="<?php echo $row["id_orden"]; ?>" title="Editar">
                                                                <i class="fas fa-pencil-alt"></i>Editar
                                                            </button>
                                                            <button type="button" class="adm-act adm-act--danger eliminar-hosting-btn" data-id="<?php echo $row["id_orden"]; ?>" title="Eliminar">
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
                                        <p class="text-secondary-custom">No se encontraron servicios de hosting activos</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Sub-tab: Servicios Inactivos -->
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
                            
                            <!-- Tabla de Servicios Inactivos -->
                            <div class="modern-table">
                                <?php if (count($hosting_inactivos) > 0): ?>
                                    <table class="table" id="dataTableInactivos" width="100%">
                                        <thead>
                                            <tr style="background: var(--primary-soft);">
                                                <th>ID</th>
                                                <th>Cliente</th>
                                                <th><?php echo $sistema === 'planpro' ? 'Dominio' : 'Hosting'; ?></th>
                                                <th>Estado</th>
                                                <th>Plan</th>
                                                <th>Fecha <?php echo $sistema === 'planpro' ? 'Vencimiento' : 'Pago'; ?></th>
                                                <th>Estado Pago</th>
                                                <th>Costo</th>
                                                <th>Moneda</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($hosting_inactivos as $row): 
                                                $status_text = '';
                                                $dot_class = '';
                                                
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
                                                    <td>
                                                        <span class="badge-id">#<?php echo $row["id_orden"]; ?></span>
                                                    </td>
                                                    <td>
                                                        <div class="fw-semibold" style="color: var(--text-primary);"><?php echo $row["nombre_contacto"]; ?></div>
                                                        <small class="text-secondary-custom" style="font-size: 12px;">ID: <?php echo $row["cliente_id"]; ?></small>
                                                    </td>
                                                    <td>
                                                        <div class="fw-semibold" style="color: var(--text-primary);"><?php echo $row["nom_host"]; ?></div>
                                                        <small class="text-secondary-custom" style="font-size: 12px;"><?php echo $row["dominio"]; ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge-status badge-inactive">
                                                            <i class="fas fa-stop-circle me-1"></i>Inactivo
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="fw-medium"><?php echo $row["producto"]; ?></div>
                                                        <small class="text-secondary-custom"><?php echo $row["tipo_producto"]; ?></small>
                                                    </td>
                                                    <td class="fw-medium" style="color: var(--text-primary);">
                                                        <?php echo (!empty($row["fecha_pago"]) && $row["fecha_pago"] !== '0000-00-00') ? date("d/m/Y", strtotime($row["fecha_pago"])): "-"; ?>
                                                    </td>
                                                    <td>
                                                        <div class="status-indicator">
                                                            <span class="status-dot <?php echo $dot_class; ?>"></span>
                                                            <span class="fw-medium" style="color: var(--text-primary);"><?php echo $status_text; ?></span>
                                                        </div>
                                                    </td>
                                                    <td class="fw-semibold" style="color: var(--text-primary);">
                                                        <?php echo (!empty($row["costo_producto"]) && $row["costo_producto"] !== '000') ? '$' . number_format($row["costo_producto"], 2) : "-"; ?>
                                                    </td>
                                                    <td class="fw-medium" style="color: var(--text-primary);">
                                                        <?php echo $row["id_forma_pago"] == 1 ? 'MXN' : ($row["id_forma_pago"] == 2 ? 'USD' : '-'); ?>
                                                    </td>
                                                    <td>
                                                        <div class="adm-actions">
                                                            <?= adm_hosting_cpanel_button($row) ?>
                                                            <button type="button" class="adm-act adm-act--success enviar-correo-btn" data-id="<?php echo $row["id_orden"]; ?>" title="Enviar correo">
                                                                <i class="fas fa-paper-plane"></i>Correo
                                                            </button>
                                                            <button type="button" class="adm-act adm-act--warn editar-info-btn" data-id="<?php echo $row["id_orden"]; ?>" title="Editar">
                                                                <i class="fas fa-pencil-alt"></i>Editar
                                                            </button>
                                                            <button type="button" class="adm-act adm-act--danger eliminar-hosting-btn" data-id="<?php echo $row["id_orden"]; ?>" title="Eliminar">
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
                                        <p class="text-secondary-custom">No se encontraron servicios de hosting inactivos</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="tab-pane fade" id="eliminados" role="tabpanel">
                    <div class="modern-table mt-3">
                        <?php if (count($hosting_eliminados) > 0): ?>
                            <table class="table" id="dataTableEliminados" width="100%">
                                <thead>
                                    <tr style="background: var(--primary-soft);">
                                        <th>ID</th>
                                        <th>Cliente</th>
                                        <th><?php echo $sistema === 'planpro' ? 'Dominio' : 'Hosting'; ?></th>
                                        <th>Plan</th>
                                        <th>Fecha <?php echo $sistema === 'planpro' ? 'Vencimiento' : 'Pago'; ?></th>
                                        <th>Costo</th>
                                        <th>Moneda</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($hosting_eliminados as $row): ?>
                                        <tr style="opacity: 0.8;">
                                            <td><span class="badge-id">#<?php echo $row["id_orden"]; ?></span></td>
                                            <td>
                                                <div class="fw-semibold" style="color: var(--text-primary);"><?php echo $row["nombre_contacto"]; ?></div>
                                                <small class="text-secondary-custom" style="font-size: 12px;">ID: <?php echo $row["cliente_id"]; ?></small>
                                            </td>
                                            <td>
                                                <div class="fw-semibold" style="color: var(--text-primary);"><?php echo $row["nom_host"]; ?></div>
                                                <small class="text-secondary-custom" style="font-size: 12px;"><?php echo $row["dominio"]; ?></small>
                                            </td>
                                            <td>
                                                <div class="fw-medium"><?php echo $row["producto"]; ?></div>
                                                <small class="text-secondary-custom"><?php echo $row["tipo_producto"]; ?></small>
                                            </td>
                                            <td class="fw-medium" style="color: var(--text-primary);">
                                                <?php echo (!empty($row["fecha_pago"]) && $row["fecha_pago"] !== '0000-00-00') ? date("d/m/Y", strtotime($row["fecha_pago"])): "-"; ?>
                                            </td>
                                            <td class="fw-semibold" style="color: var(--text-primary);">
                                                <?php echo (!empty($row["costo_producto"]) && $row["costo_producto"] !== '000') ? '$' . number_format($row["costo_producto"], 2) : "-"; ?>
                                            </td>
                                            <td class="fw-medium" style="color: var(--text-primary);">
                                                <?php echo $row["id_forma_pago"] == 1 ? 'MXN' : ($row["id_forma_pago"] == 2 ? 'USD' : '-'); ?>
                                            </td>
                                            <td>
                                                <div class="adm-actions">
                                                    <button type="button" class="adm-act adm-act--success restaurar-hosting-btn" data-id="<?php echo $row["id_orden"]; ?>" title="Restaurar">
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
                                <p class="text-secondary-custom">No se encontraron servicios de hosting eliminados</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Edición -->
<div class="modal fade bd-example-modal-xl" id="editarHostingModal" tabindex="-1" role="dialog">
    <!-- ... contenido del modal igual que antes ... -->
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
$(document).ready(function() {
    let pagos = <?php echo json_encode($pagos); ?>;
    let hosting = <?php echo json_encode(array_merge($hosting_activos, $hosting_eliminados)); ?>;
    const sistemaActivo = '<?php echo $sistema; ?>';
    let dataTableActivos = null;
    let dataTableInactivos = null;
    let dataTableEliminados = null;

    console.log("Datos de pagos:", pagos);
    console.log("Datos de hosting:", hosting);
    
    // Inicializar DataTables
    function initDataTables() {
        // Tabla de activos
        if ($('#dataTableActivos').length && !$.fn.DataTable.isDataTable('#dataTableActivos')) {
            dataTableActivos = $('#dataTableActivos').DataTable({
                searching: true,
                language: {
                    search: "Buscar:",
                    searchPlaceholder: "Buscar...",
                    lengthMenu: "Mostrar _MENU_ registros",
                    info: "Mostrando _START_ a _END_ de _TOTAL_ registros"
                },
                columnDefs: [{ type: 'date', targets: [5] }],
                order: [[5, 'asc']],
                pageLength: 25
            });
        }
        
        // Tabla de inactivos
        if ($('#dataTableInactivos').length && !$.fn.DataTable.isDataTable('#dataTableInactivos')) {
            dataTableInactivos = $('#dataTableInactivos').DataTable({
                searching: true,
                language: {
                    search: "Buscar:",
                    searchPlaceholder: "Buscar...",
                    lengthMenu: "Mostrar _MENU_ registros",
                    info: "Mostrando _START_ a _END_ de _TOTAL_ registros"
                },
                columnDefs: [{ type: 'date', targets: [5] }],
                pageLength: 25
            });
        }
        
        // Tabla de eliminados
        if ($('#dataTableEliminados').length && !$.fn.DataTable.isDataTable('#dataTableEliminados')) {
            dataTableEliminados = $('#dataTableEliminados').DataTable({
                searching: true,
                language: {
                    search: "Buscar:",
                    searchPlaceholder: "Buscar..."
                },
                columnDefs: [{ type: 'date', targets: [7, 8] }]
            });
        }
    }
    
    // Filtros para activos
    flatpickr("#rangoFechasPago", {
        mode: "range",
        dateFormat: "d/m/Y",
        locale: "es",
        onClose: function(selectedDates) {
            if (selectedDates.length === 2 && dataTableActivos) {
                $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                    const fechaPagoStr = data[5];
                    if (!fechaPagoStr || fechaPagoStr === '-') return true;
                    const partes = fechaPagoStr.split('/');
                    const fecha = new Date(partes[2], partes[1] - 1, partes[0]);
                    return fecha >= selectedDates[0] && fecha <= selectedDates[1];
                });
                dataTableActivos.draw();
                $.fn.dataTable.ext.search.pop();
            }
        }
    });
    
    // Filtros para inactivos
    flatpickr("#rangoFechasPagoInactivos", {
        mode: "range",
        dateFormat: "d/m/Y",
        locale: "es",
        onClose: function(selectedDates) {
            if (selectedDates.length === 2 && dataTableInactivos) {
                $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                    const fechaPagoStr = data[5];
                    if (!fechaPagoStr || fechaPagoStr === '-') return true;
                    const partes = fechaPagoStr.split('/');
                    const fecha = new Date(partes[2], partes[1] - 1, partes[0]);
                    return fecha >= selectedDates[0] && fecha <= selectedDates[1];
                });
                dataTableInactivos.draw();
                $.fn.dataTable.ext.search.pop();
            }
        }
    });
    
    // Filtros para tabla de activos
    $('#filtroMesActivos, #filtroAnioActivos').on('change', function() {
        if (!dataTableActivos) return;
        const mes = $('#filtroMesActivos').val();
        const anio = $('#filtroAnioActivos').val();
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
        dataTableActivos.draw();
        $.fn.dataTable.ext.search.pop();
    });
    
    $('#filtroEstadoPago').on('change', function() {
        if (!dataTableActivos) return;
        const estado = $(this).val();
        const hoy = new Date();
        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            const fechaPagoStr = data[5];
            if (!fechaPagoStr || fechaPagoStr === '-') return estado === 'todos';
            const partes = fechaPagoStr.split('/');
            const fecha = new Date(partes[2], partes[1] - 1, partes[0]);
            switch(estado) {
                case 'vencidos': return fecha < hoy;
                case 'proximos': 
                    const diffDays = Math.ceil((fecha - hoy) / (1000 * 60 * 60 * 24));
                    return diffDays > 0 && diffDays <= 30;
                case 'futuros': return fecha > hoy;
                default: return true;
            }
        });
        dataTableActivos.draw();
        $.fn.dataTable.ext.search.pop();
    });
    
    // Filtros para tabla de inactivos
    $('#filtroMesInactivos, #filtroAnioInactivos').on('change', function() {
        if (!dataTableInactivos) return;
        const mes = $('#filtroMesInactivos').val();
        const anio = $('#filtroAnioInactivos').val();
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
        dataTableInactivos.draw();
        $.fn.dataTable.ext.search.pop();
    });
    
    $('#filtroEstadoPagoInactivos').on('change', function() {
        if (!dataTableInactivos) return;
        const estado = $(this).val();
        const hoy = new Date();
        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            const fechaPagoStr = data[5];
            if (!fechaPagoStr || fechaPagoStr === '-') return estado === 'todos';
            const partes = fechaPagoStr.split('/');
            const fecha = new Date(partes[2], partes[1] - 1, partes[0]);
            switch(estado) {
                case 'vencidos': return fecha < hoy;
                case 'futuros': return fecha > hoy;
                default: return true;
            }
        });
        dataTableInactivos.draw();
        $.fn.dataTable.ext.search.pop();
    });
    
    window.limpiarFiltros = function() {
        $('#rangoFechasPago').val('');
        $('#filtroMesActivos').val('todos');
        $('#filtroAnioActivos').val('todos');
        $('#filtroEstadoPago').val('todos');
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
    
    window.filtrarPorEstado = function(estado) {
        $('#activos-tab').tab('show');
        setTimeout(function() {
            $('#sub-activos-tab').tab('show');
            setTimeout(function() {
                switch(estado) {
                    case 'vencidos':
                        $('#filtroEstadoPago').val('vencidos').trigger('change');
                        break;
                    case '30_dias':
                    case '15_dias':
                    case '7_dias':
                        $('#filtroEstadoPago').val('proximos').trigger('change');
                        break;
                }
            }, 200);
        }, 200);
    };
    
    // Las funciones originales (enviar correo, editar, eliminar, restaurar) se mantienen igual
    function admLoginHostingPanel(btn) {
        const panelUrl = (btn.getAttribute('data-panel-url') || '').trim();
        const panelUser = (btn.getAttribute('data-panel-user') || '').trim();
        const panelPass = (btn.getAttribute('data-panel-pass') || '').trim();
        const panelType = (btn.getAttribute('data-panel-type') || 'cpanel').toLowerCase();

        if (!panelUrl || !panelUser || !panelPass) {
            Swal.fire('Error', 'Credenciales del panel incompletas en este hosting.', 'error');
            return;
        }

        const validPorts = ['2083', '2086', '2087', '2095', '2096'];
        const portMatch = panelUrl.match(/:(\d+)/);
        const port = portMatch ? portMatch[1] : (panelUrl.indexOf('2086') !== -1 ? '2086' : '2083');
        if (!panelUrl.match(/^https?:\/\/.+(:\d+)?\/?$/i) || validPorts.indexOf(port) === -1) {
            Swal.fire({
                title: 'URL inválida',
                html: 'La URL del panel debe usar puertos: <strong>' + validPorts.join(', ') + '</strong>',
                icon: 'error'
            });
            return;
        }

        const label = panelType === 'whm' ? 'WHM' : 'cPanel';
        Swal.fire({
            title: 'Conectando a ' + label + '...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = panelUrl.replace(/\/+$/, '') + '/login/';
        form.target = '_blank';
        form.style.display = 'none';

        const fields = [
            { name: 'user', value: panelUser },
            { name: 'pass', value: panelPass },
            { name: 'goto_uri', value: '/' }
        ];
        if (panelType === 'whm') {
            fields.push({ name: 'goto', value: '1' }, { name: 'force_ssl', value: '1' });
        } else {
            fields.push({ name: 'cpsession', value: 'automatic' }, { name: 'login', value: '1' });
        }

        fields.forEach(function (f) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = f.name;
            input.value = f.value;
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
        setTimeout(function () {
            form.remove();
            Swal.close();
        }, 1000);
    }

    $(document).on('click', '.abrir-cpanel-btn', function () {
        admLoginHostingPanel(this);
    });

    $('.enviar-correo-btn').click(function() {
        const hostingId = $(this).data('id');
        let pagosFiltrados = pagos.filter((pago) => pago.id_servicio == hostingId && pago.estatus == 0);

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
                    pagosFiltrados.forEach(pago => {
                        $.ajax({
                            url: 'reenviar_correos_hosting.php',
                            type: 'POST',
                            dataType: 'json',
                            data: { id: pago.id },
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire('Enviado', response.message, 'success');
                                } else {
                                    Swal.fire('Error', response.message, 'error');
                                }
                            },
                            error: function() {
                                Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
                            }
                        });
                    });
                }
            });
        } else {
            Swal.fire('Info', 'No hay correos pendientes para este servicio', 'info');
        }
    });
    
    $(document).on('click', '.editar-info-btn', function () {
        const id = $(this).data('id');
        const servicio = hosting.find(h => h.id_orden == id);
        if (!servicio) {
            Swal.fire('Error', 'No se encontró el hosting solicitado', 'error');
            return;
        }
        const url = `formulario_hosting.php?edit=1&id_hosting=${servicio.id_orden}&id_cliente=${servicio.cliente_id}&sistema=${sistemaActivo}`;
        window.location.href = url;
    });

    $(document).on('click', '.eliminar-hosting-btn', function () {
        const idHosting = $(this).data('id');
        Swal.fire({
            title: '¿Eliminar hosting?',
            text: '¿Estás seguro de que deseas eliminar este servicio?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#000147',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'eliminar_hosting.php',
                    type: 'POST',
                    dataType: 'json',
                    data: { id: idHosting, eliminar: 1, sistema: sistemaActivo },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('¡Eliminado!', response.message || 'El hosting ha sido eliminado.', 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error', response.message || 'No se pudo eliminar el hosting.', 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
                    }
                });
            }
        });
    });

    $(document).on('click', '.restaurar-hosting-btn', function () {
        const idHosting = $(this).data('id');
        Swal.fire({
            title: '¿Restaurar hosting?',
            text: '¿Deseas restaurar este servicio de hosting?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, restaurar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'eliminar_hosting.php',
                    type: 'POST',
                    dataType: 'json',
                    data: { id: idHosting, eliminar: 0, sistema: sistemaActivo },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('¡Restaurado!', response.message || 'El hosting ha sido restaurado.', 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error', response.message || 'No se pudo restaurar el hosting.', 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
                    }
                });
            }
        });
    });
    
    initDataTables();
    
    // Al cambiar entre sub-tabs, reajustar DataTables
    $('#sub-activos-tab, #sub-inactivos-tab').on('shown.bs.tab', function (e) {
        if ($.fn.DataTable.isDataTable('#dataTableActivos')) {
            $('#dataTableActivos').DataTable().columns.adjust().draw();
        }
        if ($.fn.DataTable.isDataTable('#dataTableInactivos')) {
            $('#dataTableInactivos').DataTable().columns.adjust().draw();
        }
    });
    
    $('#hostingTab').on('shown.bs.tab', function (e) {
        initDataTables();
        $.fn.dataTable.tables({visible: true, api: true}).columns.adjust();
    });
    
    $('#activos-tab').trigger('shown.bs.tab');

    $(window).on('resize.admHostingDt', function () {
        $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
    });
});
</script>
</body>
</html>