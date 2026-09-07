<?php
require_once __DIR__ . '/auth_middleware.php';

error_reporting(E_ALL);
ini_set("display_errors", 1);
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/conn_hostingpro.php';
require_once __DIR__ . '/plan_helper.php';

date_default_timezone_set('America/Mexico_City');

/** Normaliza fecha a Y-m-d o null si es inválida / 0000-00-00. */
function cw_dash_fecha_valida(?string $raw): ?string
{
    $raw = trim((string) $raw);
    if ($raw === '' || strpos($raw, '0000-00-00') === 0) {
        return null;
    }
    $ts = strtotime($raw);
    if ($ts === false) {
        return null;
    }
    return date('Y-m-d', $ts);
}

/**
 * Fecha límite real del pago pendiente:
 * 1) pagos.fecha_limite_pago si es válida
 * 2) si no, fecha_pago del hosting/dominio
 */
function cw_dash_fecha_limite_efectiva(array $pago): ?string
{
    $propia = cw_dash_fecha_valida($pago['fecha_limite_pago'] ?? null);
    if ($propia !== null) {
        return $propia;
    }
    $tipo = (string) ($pago['tipo_servicio'] ?? '');
    if ($tipo === '2') {
        return cw_dash_fecha_valida($pago['fecha_pago_dominio_ref'] ?? null);
    }
    if ($tipo === '1') {
        return cw_dash_fecha_valida($pago['fecha_pago_hosting_ref'] ?? null);
    }
    return null;
}

/**
 * @return array{fecha:?string,vencido:bool,dias:?int,estado:string}
 */
function cw_dash_estado_vencimiento(?string $fechaYmd, string $hoy): array
{
    if ($fechaYmd === null || $fechaYmd === '') {
        return ['fecha' => null, 'vencido' => false, 'dias' => null, 'estado' => 'sin_fecha'];
    }
    $dias = (int) floor((strtotime($fechaYmd) - strtotime($hoy)) / 86400);
    if ($dias < 0) {
        return ['fecha' => $fechaYmd, 'vencido' => true, 'dias' => $dias, 'estado' => 'vencido'];
    }
    if ($dias <= 7) {
        return ['fecha' => $fechaYmd, 'vencido' => false, 'dias' => $dias, 'estado' => 'prox7'];
    }
    if ($dias <= 30) {
        return ['fecha' => $fechaYmd, 'vencido' => false, 'dias' => $dias, 'estado' => 'prox30'];
    }
    return ['fecha' => $fechaYmd, 'vencido' => false, 'dias' => $dias, 'estado' => 'ok'];
}

function cw_dash_enrich_pendientes(array $rows): array
{
    foreach ($rows as &$pago) {
        $pago['fecha_limite_efectiva'] = cw_dash_fecha_limite_efectiva($pago);
    }
    unset($pago);
    usort($rows, static function ($a, $b) {
        $fa = $a['fecha_limite_efectiva'] ?? '';
        $fb = $b['fecha_limite_efectiva'] ?? '';
        if ($fa === '' && $fb === '') {
            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        }
        if ($fa === '') {
            return 1;
        }
        if ($fb === '') {
            return -1;
        }
        return strcmp($fa, $fb);
    });
    return $rows;
}

/** Expresión SQL: fecha límite efectiva (pago → servicio). */
function cw_dash_sql_fecha_limite_efectiva(string $alias = 'p'): string
{
    return "CASE
        WHEN {$alias}.fecha_limite_pago IS NOT NULL AND {$alias}.fecha_limite_pago != '0000-00-00' AND {$alias}.fecha_limite_pago != '0000-00-00 00:00:00'
            THEN DATE({$alias}.fecha_limite_pago)
        WHEN {$alias}.tipo_servicio = '2' THEN DATE(d.fecha_pago)
        WHEN {$alias}.tipo_servicio = '1' THEN DATE(h.fecha_pago)
        ELSE NULL
    END";
}

$sistema = (isset($_GET['sistema']) && $_GET['sistema'] === 'hostingpro') ? 'hostingpro' : 'conlineweb';
$conn = ($sistema === 'hostingpro') ? $conn_hp : $conn;

$mes_kpi = isset($_GET['mes_kpi']) ? intval($_GET['mes_kpi']) : date('n');
$anio_kpi = isset($_GET['anio_kpi']) ? intval($_GET['anio_kpi']) : date('Y');
$fecha_inicio_kpi = sprintf('%04d-%02d-01', $anio_kpi, $mes_kpi);
$fecha_fin_kpi = date('Y-m-t', strtotime("+1 month", strtotime($fecha_inicio_kpi)));
$fechaLimiteSql = cw_dash_sql_fecha_limite_efectiva('p');

$sql_pendientes = "SELECT 
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
WHERE p.estatus = 0 AND p.Registro = 0 AND (p.sistema = ? OR p.sistema IS NULL OR p.sistema = '')
ORDER BY p.id DESC";
$stmt_pend = $conn->prepare($sql_pendientes);
$stmt_pend->bind_param("s", $sistema);
$stmt_pend->execute();
$res_pend = $stmt_pend->get_result();
$pagos_pendientes = cw_dash_enrich_pendientes($res_pend->fetch_all(MYSQLI_ASSOC));
$stmt_pend->close();

$hosting_pendientes = array_filter($pagos_pendientes, fn($r) => $r['tipo_servicio'] == 1 && (!isset($r['manual']) || $r['manual'] != 1));
$dominios_pendientes = array_filter($pagos_pendientes, fn($r) => $r['tipo_servicio'] == 2 && (!isset($r['manual']) || $r['manual'] != 1));
$manuales_pendientes = array_filter($pagos_pendientes, fn($r) => isset($r['manual']) && $r['manual'] == 1);

$total_pendientes = count($pagos_pendientes);
$total_hosting = count($hosting_pendientes);
$total_dominios = count($dominios_pendientes);
$total_manuales = count($manuales_pendientes);

$sql_pend_mes_cur = "SELECT p.currency, SUM(p.monto) as total
FROM pagos p
LEFT JOIN dominios d ON p.tipo_servicio = '2' AND p.id_servicio = d.id_dominio
LEFT JOIN hosting h ON p.tipo_servicio = '1' AND p.id_servicio = h.id_orden
WHERE p.estatus = 0 AND p.Registro = 0
  AND ($fechaLimiteSql) BETWEEN ? AND ?
  AND (p.sistema = ? OR p.sistema IS NULL OR p.sistema = '')
GROUP BY p.currency";
$stmt_pmc = $conn->prepare($sql_pend_mes_cur);
$stmt_pmc->bind_param("sss", $fecha_inicio_kpi, $fecha_fin_kpi, $sistema);
$stmt_pmc->execute();
$pend_mes_cur = [];
foreach($stmt_pmc->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    $cur = strtoupper(trim($row['currency'] ?? '')) ?: 'MXN';
    $pend_mes_cur[$cur] = floatval($row['total']);
}
$stmt_pmc->close();

$sql_pag_mes_cur = "SELECT currency, SUM(monto) as total FROM pagos WHERE estatus = 1 AND Registro = 0 AND fecha_pago BETWEEN ? AND ? AND (sistema = ? OR sistema IS NULL OR sistema = '') GROUP BY currency";
$stmt_pagc = $conn->prepare($sql_pag_mes_cur);
$stmt_pagc->bind_param("sss", $fecha_inicio_kpi, $fecha_fin_kpi, $sistema);
$stmt_pagc->execute();
$pag_mes_cur = [];
foreach($stmt_pagc->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    $cur = strtoupper(trim($row['currency'] ?? '')) ?: 'MXN';
    $pag_mes_cur[$cur] = floatval($row['total']);
}
$stmt_pagc->close();

$total_pend_mes = array_sum($pend_mes_cur);
$total_pag_mes = array_sum($pag_mes_cur);
$total_ingresos_mes = $total_pend_mes + $total_pag_mes;

$sql_avg = "SELECT AVG(monto) as avg FROM pagos WHERE estatus = 0 AND Registro = 0 AND (sistema = ? OR sistema IS NULL OR sistema = '')";
$stmt_avg = $conn->prepare($sql_avg);
$stmt_avg->bind_param("s", $sistema);
$stmt_avg->execute();
$promedio_pendiente = floatval($stmt_avg->get_result()->fetch_assoc()['avg'] ?? 0);
$stmt_avg->close();

$sql_global_pend = "SELECT 
    p.*,
    c.nombre_contacto AS cliente,
    TRIM(c.correo) AS correo_cliente,
    CASE 
        WHEN p.tipo_servicio = '2' THEN d.url_dominio
        WHEN p.tipo_servicio = '1' THEN h.nom_host
        ELSE p.concepto
    END AS nombre_servicio,
    h.producto,
    d.fecha_pago AS fecha_pago_dominio_ref,
    h.fecha_pago AS fecha_pago_hosting_ref
FROM pagos p
LEFT JOIN clientes c ON p.id_clie = c.id
LEFT JOIN dominios d ON p.tipo_servicio = '2' AND p.id_servicio = d.id_dominio
LEFT JOIN hosting h ON p.tipo_servicio = '1' AND p.id_servicio = h.id_orden
WHERE p.estatus = 0 AND p.Registro = 0 AND (p.sistema = ? OR p.sistema IS NULL OR p.sistema = '')
ORDER BY p.id DESC";
$stmt_gp = $conn->prepare($sql_global_pend);
$stmt_gp->bind_param("s", $sistema);
$stmt_gp->execute();
$global_pendientes = cw_dash_enrich_pendientes($stmt_gp->get_result()->fetch_all(MYSQLI_ASSOC));
$stmt_gp->close();

$total_global_pend = count($global_pendientes);
$monto_global_pend = array_sum(array_map(fn($p) => floatval($p['monto']), $global_pendientes));

$global_cur = [];
foreach($global_pendientes as $p) {
    $cur = strtoupper(trim($p['currency'] ?? '')) ?: 'MXN';
    $global_cur[$cur] = ($global_cur[$cur] ?? 0) + floatval($p['monto']);
}

$hoy = date('Y-m-d');
$semana = date('Y-m-d', strtotime('+15 days'));
$proximos = array_values(array_filter($pagos_pendientes, function ($p) use ($hoy, $semana) {
    $fecha = $p['fecha_limite_efectiva'] ?? null;
    return $fecha && $fecha >= $hoy && $fecha <= $semana;
}));

$total_pagos_mes = $total_pend_mes + $total_pag_mes;
$tasa_cumplimiento = $total_pagos_mes > 0 ? ($total_pag_mes / $total_pagos_mes) * 100 : 0;

$distribucion = ['Dominios' => 0, 'Hostings' => 0, 'Servicios' => 0];
foreach ($pagos_pendientes as $p) {
    $monto = floatval($p['monto']);
    if ($p['tipo_servicio'] == 2) $distribucion['Dominios'] += $monto;
    elseif ($p['tipo_servicio'] == 1) $distribucion['Hostings'] += $monto;
    else $distribucion['Servicios'] += $monto;
}

$meses_labels = [];
$pendientes_mensual = [];
$pagados_mensual = [];
for ($i = 5; $i >= 0; $i--) {
    $fecha_mes = strtotime("-$i months", strtotime($fecha_inicio_kpi));
    $anio_ev = date('Y', $fecha_mes);
    $mes_ev = date('n', $fecha_mes);
    $inicio_mes = sprintf('%04d-%02d-01', $anio_ev, $mes_ev);
    $fin_mes = date('Y-m-t', strtotime($inicio_mes));
    
    $sql_ev = "SELECT
        SUM(CASE WHEN p.estatus = 0 THEN p.monto ELSE 0 END) as pend,
        SUM(CASE WHEN p.estatus = 1 THEN p.monto ELSE 0 END) as pag
      FROM pagos p
      LEFT JOIN dominios d ON p.tipo_servicio = '2' AND p.id_servicio = d.id_dominio
      LEFT JOIN hosting h ON p.tipo_servicio = '1' AND p.id_servicio = h.id_orden
      WHERE p.Registro = 0
        AND ($fechaLimiteSql) BETWEEN ? AND ?
        AND (p.sistema = ? OR p.sistema IS NULL OR p.sistema = '')";
    $stmt_ev = $conn->prepare($sql_ev);
    $stmt_ev->bind_param("sss", $inicio_mes, $fin_mes, $sistema);
    $stmt_ev->execute();
    $row_ev = $stmt_ev->get_result()->fetch_assoc();
    $pendientes_mensual[] = floatval($row_ev['pend'] ?? 0);
    $pagados_mensual[] = floatval($row_ev['pag'] ?? 0);
    $meses_labels[] = date('M Y', $fecha_mes);
    $stmt_ev->close();
}

$sql_count = "SELECT COUNT(*) as cnt
FROM pagos p
LEFT JOIN dominios d ON p.tipo_servicio = '2' AND p.id_servicio = d.id_dominio
LEFT JOIN hosting h ON p.tipo_servicio = '1' AND p.id_servicio = h.id_orden
WHERE p.estatus = 0 AND p.Registro = 0
  AND ($fechaLimiteSql) BETWEEN ? AND ?
  AND (p.sistema = ? OR p.sistema IS NULL OR p.sistema = '')";
$stmt_cnt = $conn->prepare($sql_count);
$stmt_cnt->bind_param("sss", $fecha_inicio_kpi, $fecha_fin_kpi, $sistema);
$stmt_cnt->execute();
$total_pend_mes_count = intval($stmt_cnt->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt_cnt->close();

$sql_count_pag = "SELECT COUNT(*) as cnt FROM pagos WHERE estatus = 1 AND Registro = 0 AND fecha_pago BETWEEN ? AND ? AND (sistema = ? OR sistema IS NULL OR sistema = '')";
$stmt_cnt_pag = $conn->prepare($sql_count_pag);
$stmt_cnt_pag->bind_param("sss", $fecha_inicio_kpi, $fecha_fin_kpi, $sistema);
$stmt_cnt_pag->execute();
$total_pag_mes_count = intval($stmt_cnt_pag->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt_cnt_pag->close();

$sql_pend_list = "SELECT 
    p.*,
    c.nombre_contacto AS cliente,
    TRIM(c.correo) AS correo_cliente,
    CASE 
        WHEN p.tipo_servicio = '2' THEN d.url_dominio
        WHEN p.tipo_servicio = '1' THEN h.nom_host
        ELSE p.concepto
    END AS nombre_servicio,
    h.producto,
    d.fecha_pago AS fecha_pago_dominio_ref,
    h.fecha_pago AS fecha_pago_hosting_ref
FROM pagos p
LEFT JOIN clientes c ON p.id_clie = c.id
LEFT JOIN dominios d ON p.tipo_servicio = '2' AND p.id_servicio = d.id_dominio
LEFT JOIN hosting h ON p.tipo_servicio = '1' AND p.id_servicio = h.id_orden
WHERE p.estatus = 0 AND p.Registro = 0
  AND ($fechaLimiteSql) BETWEEN ? AND ?
  AND (p.sistema = ? OR p.sistema IS NULL OR p.sistema = '')
ORDER BY ($fechaLimiteSql) ASC";
$stmt_pl = $conn->prepare($sql_pend_list);
$stmt_pl->bind_param("sss", $fecha_inicio_kpi, $fecha_fin_kpi, $sistema);
$stmt_pl->execute();
$pendientes_list = cw_dash_enrich_pendientes($stmt_pl->get_result()->fetch_all(MYSQLI_ASSOC));
$stmt_pl->close();

$pend_cur = [];
foreach($pendientes_list as $p) {
    $cur = strtoupper(trim($p['currency'] ?? '')) ?: 'MXN';
    $pend_cur[$cur] = ($pend_cur[$cur] ?? 0) + floatval($p['monto']);
}

$sql_pag_list = "SELECT 
    p.*,
    c.nombre_contacto AS cliente,
    TRIM(c.correo) AS correo_cliente,
    CASE 
        WHEN p.tipo_servicio = '2' THEN d.url_dominio
        WHEN p.tipo_servicio = '1' THEN h.nom_host
        ELSE p.concepto
    END AS nombre_servicio,
    h.producto
FROM pagos p
LEFT JOIN clientes c ON p.id_clie = c.id
LEFT JOIN dominios d ON p.tipo_servicio = '2' AND p.id_servicio = d.id_dominio
LEFT JOIN hosting h ON p.tipo_servicio = '1' AND p.id_servicio = h.id_orden
WHERE p.estatus = 1 AND p.Registro = 0 AND p.fecha_pago BETWEEN ? AND ? AND (p.sistema = ? OR p.sistema IS NULL OR p.sistema = '')
ORDER BY p.fecha_pago DESC";
$stmt_pgl = $conn->prepare($sql_pag_list);
$stmt_pgl->bind_param("sss", $fecha_inicio_kpi, $fecha_fin_kpi, $sistema);
$stmt_pgl->execute();
$pagados_list = $stmt_pgl->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_pgl->close();

$pag_cur = [];
foreach($pagados_list as $p) {
    $cur = strtoupper(trim($p['currency'] ?? '')) ?: 'MXN';
    $pag_cur[$cur] = ($pag_cur[$cur] ?? 0) + floatval($p['monto']);
}

$sql_anios_filtro = "SELECT DISTINCT YEAR(fecha_limite_pago) as anio FROM pagos WHERE fecha_limite_pago IS NOT NULL AND fecha_limite_pago != '0000-00-00' AND (sistema = ? OR sistema IS NULL OR sistema = '')
    UNION
    SELECT DISTINCT YEAR(fecha_pago) as anio FROM pagos WHERE fecha_pago IS NOT NULL AND fecha_pago != '0000-00-00' AND (sistema = ? OR sistema IS NULL OR sistema = '')
    ORDER BY anio DESC";
$stmt_af = $conn->prepare($sql_anios_filtro);
$stmt_af->bind_param("ss", $sistema, $sistema);
$stmt_af->execute();
$anios_filtro = array_column($stmt_af->get_result()->fetch_all(MYSQLI_ASSOC), 'anio');
$stmt_af->close();
if(empty($anios_filtro)) $anios_filtro = [date('Y')];

$sql_meses_filtro = "SELECT DISTINCT MONTH(fecha_limite_pago) as mes FROM pagos WHERE YEAR(fecha_limite_pago) = ? AND fecha_limite_pago IS NOT NULL AND fecha_limite_pago != '0000-00-00' AND (sistema = ? OR sistema IS NULL OR sistema = '')
    UNION
    SELECT DISTINCT MONTH(fecha_pago) as mes FROM pagos WHERE YEAR(fecha_pago) = ? AND fecha_pago IS NOT NULL AND fecha_pago != '0000-00-00' AND (sistema = ? OR sistema IS NULL OR sistema = '')
    ORDER BY mes ASC";
$stmt_mf = $conn->prepare($sql_meses_filtro);
$stmt_mf->bind_param("isis", $anio_kpi, $sistema, $anio_kpi, $sistema);
$stmt_mf->execute();
$meses_filtro = array_column($stmt_mf->get_result()->fetch_all(MYSQLI_ASSOC), 'mes');
$stmt_mf->close();
if(empty($meses_filtro)) $meses_filtro = [$mes_kpi];
if(!in_array($mes_kpi, $meses_filtro)) $mes_kpi = $meses_filtro[0];

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
WHERE p.estatus = 1 AND p.Registro = 0 AND (p.sistema = ? OR p.sistema IS NULL OR p.sistema = '')
ORDER BY p.fecha_pago DESC";
$stmt_ap = $conn->prepare($sql_aprobados);
$stmt_ap->bind_param("s", $sistema);
$stmt_ap->execute();
$aprobados = $stmt_ap->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_ap->close();

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
WHERE p.Registro = 1 AND (p.sistema = ? OR p.sistema IS NULL OR p.sistema = '')
ORDER BY p.fecha DESC, p.hora DESC";
$stmt_el = $conn->prepare($sql_eliminados);
$stmt_el->bind_param("s", $sistema);
$stmt_el->execute();
$eliminados = $stmt_el->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_el->close();

$sql_anios = "SELECT YEAR(fecha_pago) as anio, COUNT(*) as total FROM pagos WHERE estatus = 1 AND Registro = 0 AND fecha_pago IS NOT NULL AND fecha_pago != '0000-00-00' AND (sistema = ? OR sistema IS NULL OR sistema = '') GROUP BY YEAR(fecha_pago) ORDER BY anio DESC";
$stmt_anios = $conn->prepare($sql_anios);
$stmt_anios->bind_param("s", $sistema);
$stmt_anios->execute();
$anios_disponibles = $stmt_anios->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_anios->close();

$admPageTitle = 'Dashboard de Pagos';
require_once __DIR__ . '/includes/adm_head_meta.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?= htmlspecialchars(adm_document_title($admPageTitle), ENT_QUOTES, 'UTF-8') ?></title>
    <?= adm_favicon_markup() ?>
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="css/admin-platform.css" rel="stylesheet">
    <link href="vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="js/sb-admin-2.min.js"></script>
    <script src="vendor/chart.js/Chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        :root {
            --primary-dark: #000147;
            --primary: #1a1f6b;
            --primary-light: #2d3388;
            --primary-soft: #eef0ff;
            --accent: #6366f1;
            --accent-light: #818cf8;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --surface: #ffffff;
            --background: #f1f5f9;
            --border: #e2e8f0;
            --text-primary: #0f172a;
            --text-secondary: #64748b;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.04);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.08), 0 2px 4px -2px rgba(0,0,0,0.05);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.08), 0 4px 6px -4px rgba(0,0,0,0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.06);
        }
        body { background: var(--background); }
        .page-header { padding: 1.5rem 0; background: transparent; }
        .page-title { color: var(--text-primary); font-size: 1.75rem; font-weight: 800; letter-spacing: -0.025em; margin-bottom: 0.25rem; }
        .page-subtitle { color: var(--text-secondary); font-size: 0.95rem; font-weight: 400; }
        .system-tabs { display: flex; gap: 0; margin-bottom: 1rem; background: var(--surface); border-radius: 12px; border: 1px solid var(--border); overflow: hidden; width: fit-content; }
        .system-tab { padding: 0.75rem 1.75rem; font-weight: 600; font-size: 0.875rem; color: var(--text-secondary); text-decoration: none; transition: all 0.2s; cursor: pointer; border: none; background: transparent; }
        .system-tab:hover { color: var(--primary); background: var(--primary-soft); }
        .system-tab.active { color: #fff; background: var(--primary-dark); }
        .card-modern { background: var(--surface); border-radius: 16px; border: 1px solid var(--border); box-shadow: var(--shadow-sm); transition: all 0.25s ease; }
        .card-modern:hover { box-shadow: var(--shadow-md); }
        .filter-bar { background: var(--surface); border-radius: 16px; padding: 1.25rem 1.5rem; border: 1px solid var(--border); box-shadow: var(--shadow-sm); }
        .filter-select { border: 1px solid var(--border); border-radius: 10px; padding: 0.6rem 1rem; font-size: 0.875rem; font-weight: 500; color: var(--text-primary); background: var(--surface); cursor: pointer; transition: all 0.2s; min-width: 120px; }
        .filter-select:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
        .btn-filter { background: var(--primary-dark); color: #fff; border: none; border-radius: 10px; padding: 0.6rem 1.5rem; font-weight: 600; font-size: 0.875rem; cursor: pointer; transition: all 0.2s; }
        .btn-filter:hover { background: var(--primary); transform: translateY(-1px); box-shadow: var(--shadow-md); }
        .kpi-card { background: var(--surface); border-radius: 16px; padding: 1.5rem; border: 1px solid var(--border); box-shadow: var(--shadow-sm); transition: all 0.25s ease; height: 100%; }
        .kpi-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
        .kpi-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; }
        .kpi-icon.warning { background: #fff7ed; color: #ea580c; }
        .kpi-icon.success { background: #dcfce7; color: #16a34a; }
        .kpi-icon.total { background: #ede9fe; color: #7c3aed; }
        .kpi-icon.info { background: #e0f2fe; color: #0284c7; }
        .kpi-icon.alert { background: #fee2e2; color: #dc2626; }
        .kpi-icon.percent { background: #f0fdf4; color: #15803d; }
        .kpi-currency { margin-top: 0.35rem; }
        .kpi-currency .cur-row { font-size: 1.25rem; font-weight: 700; color: var(--text-primary); line-height: 1.3; }
        .kpi-currency .cur-row .cur-label { font-weight: 700; min-width: 36px; display: inline-block; }
        .kpi-currency .cur-row.usd .cur-label { color: #10b981; }
        .kpi-currency .cur-row.mxn .cur-label { color: #f59e0b; }
        .kpi-currency .cur-row.other .cur-label { color: var(--text-secondary); }
        .kpi-label { font-size: 0.8rem; font-weight: 500; color: var(--text-secondary); margin-top: 0.25rem; }
        .kpi-sub { font-size: 0.75rem; color: #94a3b8; margin-top: 0.125rem; }
        .compliance-bar { height: 4px; background: #e2e8f0; border-radius: 99px; margin-top: 0.75rem; overflow: hidden; }
        .compliance-bar-fill { height: 100%; border-radius: 99px; background: linear-gradient(90deg, #10b981, #059669); transition: width 0.6s ease; }
        .kpi-card.clickable { cursor: pointer; position: relative; }
        .kpi-card.clickable::after { content: '\f054'; font-family: 'Font Awesome 5 Free'; font-weight: 900; position: absolute; top: 12px; right: 12px; font-size: 0.65rem; color: #cbd5e1; opacity: 0; transition: opacity 0.2s; }
        .kpi-card.clickable:hover::after { opacity: 1; }
        .modal-modern .modal-content { border-radius: 20px; border: none; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
        .modal-modern .modal-header { background: var(--primary-dark); color: #fff; border-radius: 20px 20px 0 0; padding: 1.25rem 1.5rem; }
        .modal-modern .modal-header .close { color: #fff; opacity: 0.7; text-shadow: none; }
        .modal-modern .modal-header .close:hover { opacity: 1; }
        .modal-modern .modal-body { padding: 0; }
        .modal-modern .modal-footer { border-top: 1px solid var(--border); padding: 1rem 1.5rem; }
        .modal-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .modal-table th { background: #fafbff; padding: 0.75rem 1rem; font-weight: 600; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 1; white-space: nowrap; }
        .modal-table td { padding: 0.75rem 1rem; border-bottom: 1px solid #f1f5f9; color: var(--text-primary); vertical-align: middle; white-space: nowrap; }
        .modal-table .col-modal-servicio { min-width: 180px; max-width: 250px; }
        .modal-table .col-modal-servicio textarea { border: none; background: transparent; resize: none; width: 100%; font-size: 0.85rem; color: var(--text-primary); font-family: inherit; padding: 0; overflow-y: auto; max-height: 60px; line-height: 1.4; }
        .modal-table .col-modal-servicio textarea:focus { outline: none; }
        .modal-table .col-modal-fecha { min-width: 140px; }
        .modal-table tbody tr:hover { background: #fafbff; }
        .modal-table-wrap { max-height: 400px; overflow-y: auto; }
        .modal-total-bar { background: #fafbff; border-top: 2px solid var(--border); padding: 0.75rem 1rem; font-weight: 700; font-size: 0.9rem; color: var(--text-primary); }
        .chart-card { background: var(--surface); border-radius: 16px; border: 1px solid var(--border); box-shadow: var(--shadow-sm); overflow: hidden; }
        .chart-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); background: #fafbff; }
        .chart-title { font-size: 0.95rem; font-weight: 700; color: var(--text-primary); margin: 0; }
        .chart-body { padding: 1.5rem; }
        .vencimientos-card { background: var(--surface); border-radius: 16px; border: 1px solid var(--border); box-shadow: var(--shadow-sm); overflow: hidden; }
        .section-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .section-title { font-size: 1rem; font-weight: 700; color: var(--text-primary); margin: 0; }
        .badge-count { background: #fff7ed; color: #ea580c; font-weight: 600; font-size: 0.75rem; padding: 0.35rem 0.75rem; border-radius: 99px; }
        .table-modern { width: 100%; border-collapse: collapse; }
        .table-modern th { background: #fafbff; padding: 0.875rem 0.75rem; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); border-bottom: 1px solid var(--border); white-space: nowrap; }
        .table-modern td { padding: 0.75rem; border-bottom: 1px solid #f1f5f9; font-size: 0.85rem; color: var(--text-primary); vertical-align: middle; }
        .table-modern tbody tr:hover { background: #fafbff; }
        .col-id { width: 70px; }
        .col-tipo { width: 85px; }
        .col-servicio { max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .col-plan { max-width: 100px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .col-fecha { width: 140px; min-width: 140px; white-space: nowrap; }
        .col-estado { width: 130px; min-width: 130px; white-space: nowrap; }
        .col-forma { width: 110px; white-space: nowrap; }
        .col-monto { width: 100px; text-align: right; white-space: nowrap; }
        .col-moneda { width: 70px; white-space: nowrap; }
        .dt-nowrap { white-space: nowrap !important; }
        .badge-type { padding: 0.3rem 0.75rem; border-radius: 99px; font-size: 0.7rem; font-weight: 600; display: inline-block; }
        .badge-hosting { background: #e0e7ff; color: #3730a3; }
        .badge-dominio { background: #dcfce7; color: #166534; }
        .badge-manual { background: #fef3c7; color: #92400e; }
        .badge-status { padding: 0.3rem 0.75rem; border-radius: 99px; font-size: 0.7rem; font-weight: 600; }
        .badge-pagado { background: #dcfce7; color: #166534; }
        .badge-vencido { background: #fee2e2; color: #991b1b; }
        .badge-proximo { background: #fef3c7; color: #92400e; }
        .badge-forma { padding: 0.3rem 0.75rem; border-radius: 99px; font-size: 0.7rem; font-weight: 600; }
        .badge-tarjeta { background: #e0e7ff; color: #3730a3; }
        .badge-transferencia { background: #dcfce7; color: #166534; }
        .badge-efectivo { background: #fef3c7; color: #92400e; }
        .btn-action { border-radius: 8px; padding: 0.4rem 0.75rem; font-size: 0.7rem; font-weight: 600; border: none; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.35rem; }
        .btn-action:hover { transform: translateY(-1px); box-shadow: var(--shadow-md); }
        .btn-pay { background: var(--success); color: #fff; }
        .btn-resend { background: var(--info); color: #fff; }
        .btn-whatsapp { background: #25D366; color: #fff; }
        .btn-delete { background: var(--danger); color: #fff; }
        tfoot.totales td { font-weight: 700; background: #fafbff; border-top: 2px solid var(--border); padding: 1rem; font-size: 0.875rem; }
        .periodo-badge { background: var(--primary-soft); color: var(--primary-dark); padding: 0.5rem 1rem; border-radius: 10px; font-size: 0.85rem; font-weight: 500; }
        @media (max-width: 768px) {
            .kpi-value { font-size: 1.5rem; }
            .kpi-label { font-size: 0.7rem; }
            .kpi-sub { font-size: 0.65rem; }
            .kpi-currency .cur-row { font-size: 1.05rem; }
        }
        .kpi-row { display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: nowrap; overflow-x: auto; }
        .kpi-row .kpi-item { flex: 1; min-width: 0; }
        .kpi-row .kpi-card { padding: 1rem; }
        .kpi-row .kpi-value { font-size: 1.5rem; }
        .kpi-row .kpi-icon { width: 40px; height: 40px; font-size: 1rem; border-radius: 10px; }
        .kpi-row .kpi-label { font-size: 0.72rem; }
        .kpi-row .kpi-sub { font-size: 0.65rem; }
    </style>
</head>
<body>
<div id="wrapper">
    <?php include 'menu.php'; ?>
    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            <div class="page-header">
                <div class="container-fluid">
                    <div>
                        <h4 class="page-title"><i class="fas fa-chart-bar mr-2"></i>Dashboard de Pagos</h4>
                        <p class="page-subtitle">Gestión y seguimiento de cobros</p>
                    </div>
                </div>
            </div>
            
            <div class="container-fluid content-wrapper legacy-touch">
                <div class="system-tabs mb-3">
                    <a href="?sistema=conlineweb&mes_kpi=<?=$mes_kpi?>&anio_kpi=<?=$anio_kpi?>" class="system-tab <?=$sistema === 'conlineweb' ? 'active' : ''?>">
                        <i class="fas fa-globe mr-1"></i>conlineweb
                    </a>
                    <a href="?sistema=hostingpro&mes_kpi=<?=$mes_kpi?>&anio_kpi=<?=$anio_kpi?>" class="system-tab <?=$sistema === 'hostingpro' ? 'active' : ''?>">
                        <i class="fas fa-server mr-1"></i>HostingPro
                    </a>
                </div>

                <div class="filter-bar mb-4">
                    <form method="GET" class="d-flex align-items-end flex-wrap">
                        <input type="hidden" name="sistema" value="<?=$sistema?>">
                        <div class="mr-4">
                            <label class="d-block mb-1" style="font-size:0.75rem;font-weight:600;color:var(--text-secondary)">Año</label>
                            <select name="anio_kpi" class="filter-select" onchange="this.form.submit()">
                                <?php foreach($anios_filtro as $anio): ?>
                                    <option value="<?=$anio?>" <?=$anio==$anio_kpi?'selected':''?>><?=$anio?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mr-4">
                            <label class="d-block mb-1" style="font-size:0.75rem;font-weight:600;color:var(--text-secondary)">Mes</label>
                            <select name="mes_kpi" class="filter-select">
                                <?php foreach($meses_filtro as $mes): ?>
                                    <option value="<?=$mes?>" <?=$mes==$mes_kpi?'selected':''?>><?=date('F',mktime(0,0,0,$mes,1))?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mr-4">
                            <button type="submit" class="btn-filter"><i class="fas fa-chart-line mr-1"></i>Actualizar</button>
                        </div>
                        <div class="ml-auto">
                            <span class="periodo-badge"><i class="far fa-calendar mr-1"></i><?= date('F Y', strtotime($fecha_inicio_kpi)) ?></span>
                        </div>
                    </form>
                </div>

                <div class="mb-4">
                    <div class="d-flex align-items-center mb-3">
                        <div style="width:4px;height:20px;background:var(--accent);border-radius:2px;margin-right:10px"></div>
                        <h6 class="mb-0 font-weight-bold" style="color:var(--text-primary);font-size:0.85rem">RESUMEN DEL MES</h6>
                    </div>
                    <div class="kpi-row">
                        <div class="kpi-item">
                            <div class="kpi-card clickable" data-toggle="modal" data-target="#modalPendientes">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="kpi-currency">
                                            <?php if(isset($pend_mes_cur['MXN'])): ?>
                                                <div class="cur-row mxn"><span class="cur-label">MXN</span> $<?=number_format($pend_mes_cur['MXN'],2)?></div>
                                            <?php endif; ?>
                                            <?php if(isset($pend_mes_cur['USD'])): ?>
                                                <div class="cur-row usd"><span class="cur-label">USD</span> $<?=number_format($pend_mes_cur['USD'],2)?></div>
                                            <?php endif; ?>
                                            <?php foreach($pend_mes_cur as $cur => $val): if($cur !== 'USD' && $cur !== 'MXN'): ?>
                                                <div class="cur-row other"><span class="cur-label"><?=$cur?></span> $<?=number_format($val,2)?></div>
                                            <?php endif; endforeach; ?>
                                        </div>
                                        <div class="kpi-label">Pendiente por Cobrar</div>
                                        <div class="kpi-sub"><?=$total_pend_mes_count?> pagos</div>
                                    </div>
                                    <div class="kpi-icon warning"><i class="fas fa-hourglass-half"></i></div>
                                </div>
                            </div>
                        </div>
                        <div class="kpi-item">
                            <div class="kpi-card clickable" data-toggle="modal" data-target="#modalRecibidos">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="kpi-currency">
                                            <?php if(isset($pag_mes_cur['MXN'])): ?>
                                                <div class="cur-row mxn"><span class="cur-label">MXN</span> $<?=number_format($pag_mes_cur['MXN'],2)?></div>
                                            <?php endif; ?>
                                            <?php if(isset($pag_mes_cur['USD'])): ?>
                                                <div class="cur-row usd"><span class="cur-label">USD</span> $<?=number_format($pag_mes_cur['USD'],2)?></div>
                                            <?php endif; ?>
                                            <?php foreach($pag_mes_cur as $cur => $val): if($cur !== 'USD' && $cur !== 'MXN'): ?>
                                                <div class="cur-row other"><span class="cur-label"><?=$cur?></span> $<?=number_format($val,2)?></div>
                                            <?php endif; endforeach; ?>
                                        </div>
                                        <div class="kpi-label">Recibido</div>
                                        <div class="kpi-sub"><?=$total_pag_mes_count?> pagos</div>
                                    </div>
                                    <div class="kpi-icon success"><i class="fas fa-check-circle"></i></div>
                                </div>
                            </div>
                        </div>
                        <div class="kpi-item">
                            <div class="kpi-card">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="kpi-currency">
                                            <?php
                                            $ingresos_mes_cur = [];
                                            foreach($pend_mes_cur as $cur => $val) { if(!isset($ingresos_mes_cur[$cur])) $ingresos_mes_cur[$cur] = 0; $ingresos_mes_cur[$cur] += $val; }
                                            foreach($pag_mes_cur as $cur => $val) { if(!isset($ingresos_mes_cur[$cur])) $ingresos_mes_cur[$cur] = 0; $ingresos_mes_cur[$cur] += $val; }
                                            ?>
                                            <?php if(isset($ingresos_mes_cur['MXN'])): ?>
                                                <div class="cur-row mxn"><span class="cur-label">MXN</span> $<?=number_format($ingresos_mes_cur['MXN'],2)?></div>
                                            <?php endif; ?>
                                            <?php if(isset($ingresos_mes_cur['USD'])): ?>
                                                <div class="cur-row usd"><span class="cur-label">USD</span> $<?=number_format($ingresos_mes_cur['USD'],2)?></div>
                                            <?php endif; ?>
                                            <?php foreach($ingresos_mes_cur as $cur => $val): if($cur !== 'USD' && $cur !== 'MXN'): ?>
                                                <div class="cur-row other"><span class="cur-label"><?=$cur?></span> $<?=number_format($val,2)?></div>
                                            <?php endif; endforeach; ?>
                                        </div>
                                        <div class="kpi-label">Total del Mes</div>
                                        <div class="kpi-sub"><?= date('M Y', strtotime($fecha_inicio_kpi)) ?></div>
                                    </div>
                                    <div class="kpi-icon total"><i class="fas fa-wallet"></i></div>
                                </div>
                            </div>
                        </div>
                        <div class="kpi-item">
                            <div class="kpi-card">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="kpi-value percent"><?= number_format($tasa_cumplimiento,1) ?>%</div>
                                        <div class="kpi-label">Tasa de Cobro</div>
                                        <div class="compliance-bar"><div class="compliance-bar-fill" style="width:<?= min($tasa_cumplimiento,100) ?>%"></div></div>
                                    </div>
                                    <div class="kpi-icon percent"><i class="fas fa-chart-pie"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <div class="d-flex align-items-center mb-3">
                        <div style="width:4px;height:20px;background:var(--danger);border-radius:2px;margin-right:10px"></div>
                        <h6 class="mb-0 font-weight-bold" style="color:var(--text-primary);font-size:0.85rem">INDICADORES Y ALERTAS</h6>
                    </div>
                    <div class="kpi-row">
                        <div class="kpi-item">
                            <div class="kpi-card clickable" data-toggle="modal" data-target="#modalGlobalPendientes">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="kpi-currency">
                                            <?php if(isset($global_cur['MXN'])): ?>
                                                <div class="cur-row mxn"><span class="cur-label">MXN</span> $<?=number_format($global_cur['MXN'],2)?></div>
                                            <?php endif; ?>
                                            <?php if(isset($global_cur['USD'])): ?>
                                                <div class="cur-row usd"><span class="cur-label">USD</span> $<?=number_format($global_cur['USD'],2)?></div>
                                            <?php endif; ?>
                                            <?php foreach($global_cur as $cur => $val): if($cur !== 'USD' && $cur !== 'MXN'): ?>
                                                <div class="cur-row other"><span class="cur-label"><?=$cur?></span> $<?=number_format($val,2)?></div>
                                            <?php endif; endforeach; ?>
                                        </div>
                                        <div class="kpi-label">Global Pendiente</div>
                                        <div class="kpi-sub"><?=$total_global_pend?> pagos sin cobrar</div>
                                    </div>
                                    <div class="kpi-icon alert"><i class="fas fa-exclamation-circle"></i></div>
                                </div>
                            </div>
                        </div>
                        <div class="kpi-item">
                            <div class="kpi-card">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="kpi-value info">$<?= number_format($promedio_pendiente,2) ?></div>
                                        <div class="kpi-label">Promedio por Pago</div>
                                        <div class="kpi-sub">Global pendientes</div>
                                    </div>
                                    <div class="kpi-icon info"><i class="fas fa-calculator"></i></div>
                                </div>
                            </div>
                        </div>
                        <div class="kpi-item">
                            <div class="kpi-card">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="kpi-value alert"><?= count($proximos) ?></div>
                                        <div class="kpi-label">Vencen en 15 días</div>
                                        <div class="kpi-sub">Requieren atención</div>
                                    </div>
                                    <div class="kpi-icon alert"><i class="fas fa-exclamation-triangle"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-xl-6 col-lg-6 mb-3">
                        <div class="chart-card h-100">
                            <div class="chart-header"><h6 class="chart-title"><i class="fas fa-chart-line mr-2" style="color:var(--accent)"></i>Evolución últimos 6 meses</h6></div>
                            <div class="chart-body"><canvas id="trendChart" style="width:100%;height:200px"></canvas></div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-lg-3 mb-3">
                        <div class="chart-card h-100">
                            <div class="chart-header"><h6 class="chart-title"><i class="fas fa-chart-pie mr-2" style="color:var(--accent)"></i>Distribución por Tipo</h6></div>
                            <div class="chart-body"><canvas id="distChart" style="width:100%;height:200px"></canvas></div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-lg-3 mb-3">
                        <div class="chart-card h-100">
                            <div class="chart-header"><h6 class="chart-title"><i class="fas fa-tasks mr-2" style="color:var(--accent)"></i>Estado de Pagos</h6></div>
                            <div class="chart-body"><canvas id="statusChart" style="width:100%;height:200px"></canvas></div>
                        </div>
                    </div>
                </div>

                <div class="vencimientos-card mb-4">
                    <div class="section-header">
                        <h6 class="section-title"><i class="fas fa-calendar-week mr-2" style="color:var(--warning)"></i>Próximos Vencimientos (15 días)</h6>
                        <?php if(count($proximos)>0): ?>
                            <span class="badge-count"><?= count($proximos) ?> pendientes</span>
                        <?php endif; ?>
                    </div>
                    <div class="p-0">
                        <?php if(count($proximos)>0): ?>
                        <div class="table-responsive">
                            <table class="table-modern">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Cliente</th>
                                        <th>Servicio</th>
                                        <th>Fecha de pago</th>
                                        <th>Monto</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($proximos as $v):
                                        $st = cw_dash_estado_vencimiento($v['fecha_limite_efectiva'] ?? null, $hoy);
                                        $dias = $st['dias'] ?? 0;
                                    ?>
                                    <tr>
                                        <td><span class="badge-type <?=$v['tipo_servicio']==2?'badge-dominio':($v['tipo_servicio']==1?'badge-hosting':'badge-manual')?>"><?= $v['tipo_servicio']==2?'Dominio':($v['tipo_servicio']==1?'Hosting':'Manual') ?></span></td>
                                        <td><strong><?= htmlspecialchars($v['cliente']) ?></strong></td>
                                        <td><?= htmlspecialchars($v['nombre_servicio']??$v['producto']??$v['concepto']) ?></td>
                                        <td>
                                            <span class="badge-status badge-proximo">Pendiente</span>
                                            <span class="badge-status <?=$st['estado']==='prox7'?'badge-vencido':'badge-proximo'?>"><?=$dias?> días</span>
                                        </td>
                                        <td><strong>$<?= number_format($v['monto'],2) ?></strong></td>
                                        <td>
                                            <button class="btn-action btn-pay mark-paid-btn" data-id="<?=$v['id']?>"><i class="fas fa-check"></i></button>
                                            <button class="btn-action btn-resend reenviar-btn" data-id="<?=$v['id']?>" data-tipo="<?=$v['tipo_servicio']?>" data-manual="<?=isset($v['manual'])?$v['manual']:0?>"><i class="fas fa-paper-plane"></i></button>
                                            <button class="btn-action btn-whatsapp whatsapp-btn" data-id="<?=$v['id']?>" data-cliente="<?=htmlspecialchars($v['cliente'])?>"><i class="fab fa-whatsapp"></i></button>
                                            <button class="btn-action btn-delete eliminar-btn" data-id="<?=$v['id']?>"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-check-circle mb-3" style="font-size:3rem;color:var(--success)"></i>
                                <p class="text-muted mb-0">No hay vencimientos próximos</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="vencimientos-card mb-4">
                    <div class="section-header">
                        <h6 class="section-title"><i class="fas fa-clock mr-2" style="color:var(--warning)"></i>Pagos Pendientes</h6>
                        <span class="badge-count"><?=$total_pendientes?> registros</span>
                    </div>
                    <div class="p-0">
                        <div class="table-responsive">
                            <table id="tablaPendientesUnificada" class="table-modern mb-0" width="100%">
                                <thead>
                                    <tr>
                                        <th class="col-id">ID</th>
                                        <th class="col-tipo">Tipo</th>
                                        <th>Cliente</th>
                                        <th class="col-servicio">Servicio / Concepto</th>
                                        <th class="col-plan">Plan</th>
                                        <th class="col-fecha">Fecha de pago</th>
                                        <th class="col-estado">Estado</th>
                                        <th class="col-forma">Forma Pago</th>
                                        <th class="col-monto">Monto</th>
                                        <th class="col-moneda">Moneda</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($pagos_pendientes as $r):
                                        $st = cw_dash_estado_vencimiento($r['fecha_limite_efectiva'] ?? null, $hoy);
                                        $vencido = $st['vencido'];
                                        $diasRestantes = $st['dias'];
                                        $prox7  = $st['estado'] === 'prox7';
                                        $prox30 = $st['estado'] === 'prox30';
                                        $esManual = isset($r['manual']) && $r['manual'] == 1;
                                        $tipo = $esManual ? 'Manual' : ($r['tipo_servicio'] == 1 ? 'Hosting' : 'Dominio');
                                        $servicio = htmlspecialchars($r['nombre_servicio'] ?? $r['producto'] ?? $r['concepto'] ?? '-');
                                        $plan = (!$esManual && $r['tipo_servicio'] == 1) ? obtenerNombrePlan($conn, $r['producto']) : '-';
                                    ?>
                                    <tr>
                                        <td class="col-id"><code class="text-muted">#<?=$r['id']?></code></td>
                                        <td class="col-tipo"><span class="badge-type <?=$esManual?'badge-manual':($r['tipo_servicio']==1?'badge-hosting':'badge-dominio')?>"><?=$tipo?></span></td>
                                        <td>
                                            <strong><?=htmlspecialchars($r['cliente'])?></strong>
                                            <div class="text-muted" style="font-size:0.7rem">ID <?=$r['id_clie']?></div>
                                        </td>
                                        <td class="col-servicio" title="<?=$servicio?>"><?=$servicio?></td>
                                        <td class="col-plan"><?=$plan?></td>
                                        <td class="col-fecha" data-order="<?=$st['fecha'] ?? '9999-99-99'?>"><span class="badge-status badge-proximo">Pendiente</span></td>
                                        <td class="col-estado">
                                            <?php if($st['estado']==='vencido'):?><span class="badge-status badge-vencido"><i class="fas fa-times-circle"></i> Vencido</span>
                                            <?php elseif($st['estado']==='prox7'):?><span class="badge-status badge-proximo"><i class="fas fa-exclamation-triangle"></i> <?=$diasRestantes?> días</span>
                                            <?php elseif($st['estado']==='prox30'):?><span class="badge-status badge-proximo"><i class="fas fa-clock"></i> <?=$diasRestantes?> días</span>
                                            <?php elseif($st['estado']==='ok'):?><span class="badge-status" style="background:#e2e8f0;color:#475569"><?=$diasRestantes?> días</span>
                                            <?php else:?><span class="badge-status" style="background:#f1f5f9;color:#94a3b8">Sin fecha</span><?php endif;?>
                                        </td>
                                        <td class="col-forma">
                                            <?php if($r['forma_pago']==1):?><span class="badge-forma badge-tarjeta">Tarjeta</span>
                                            <?php elseif($r['forma_pago']==2):?><span class="badge-forma badge-transferencia">Transferencia</span>
                                            <?php elseif($r['forma_pago']==3):?><span class="badge-forma badge-efectivo">Efectivo</span>
                                            <?php else:?><span class="badge-forma" style="background:#f1f5f9;color:#94a3b8">Pendiente</span><?php endif;?>
                                        </td>
                                        <td class="col-monto" data-order="<?=$r['monto']+0?>"><strong>$<?=number_format($r['monto'],2)?></strong></td>
                                        <td class="col-moneda"><?=htmlspecialchars($r['currency']??'MXN')?></td>
                                    </tr>
                                    <?php endforeach;?>
                                </tbody>
                                <tfoot class="totales">
                                    <?php
                                    $totales_moneda = [];
                                    foreach($pagos_pendientes as $p) {
                                        $cur = strtoupper(trim($p['currency'] ?? '')) ?: 'MXN';
                                        $totales_moneda[$cur] = ($totales_moneda[$cur] ?? 0) + floatval($p['monto']);
                                    }
                                    foreach($totales_moneda as $cur => $tot): ?>
                                    <tr>
                                        <td colspan="8" class="text-right">Total Pendiente <strong><?=$cur?></strong>:</td>
                                        <td><strong>$<?=number_format($tot,2)?></strong></td>
                                        <td><?=$cur?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/datatables/jquery.dataTables.min.js"></script>
<script src="vendor/datatables/dataTables.bootstrap4.min.js"></script>
<script>
const sistema = '<?=$sistema?>';
const hoy = '<?=$hoy?>';

new Chart(document.getElementById('trendChart'), { 
    type: 'line', 
    data: { 
        labels: <?=json_encode($meses_labels)?>, 
        datasets: [
            { label: 'Pendiente $', data: <?=json_encode($pendientes_mensual)?>, borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,0.1)', borderWidth: 2, fill: true, tension: 0.4, pointRadius: 3, pointBackgroundColor: '#f59e0b' },
            { label: 'Pagado $', data: <?=json_encode($pagados_mensual)?>, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.1)', borderWidth: 2, fill: true, tension: 0.4, pointRadius: 3, pointBackgroundColor: '#10b981' }
        ] 
    }, 
    options: { 
        responsive: true, 
        maintainAspectRatio: false,
        plugins: { legend: { display: true, position: 'top', labels: { usePointStyle: true, padding: 15 } } },
        scales: { y: { beginAtZero: true, grid: { color: '#f1f5f9' } }, x: { grid: { display: false } } }
    } 
});

new Chart(document.getElementById('distChart'), { 
    type: 'doughnut', 
    data: { 
        labels: ['Dominios','Hostings','Servicios'], 
        datasets: [{ data: [<?=$distribucion['Dominios']?>,<?=$distribucion['Hostings']?>,<?=$distribucion['Servicios']?>], backgroundColor: ['#6366f1','#10b981','#3b82f6'], borderWidth: 0 }] 
    }, 
    options: { 
        responsive: true,
        maintainAspectRatio: false,
        cutout: '65%',
        plugins: { legend: { display: true, position: 'bottom', labels: { usePointStyle: true, padding: 10, font: { size: 11 } } } }
    } 
});

new Chart(document.getElementById('statusChart'), { 
    type: 'doughnut', 
    data: { 
        labels: ['Pagados','Pendientes'], 
        datasets: [{ data: [<?=$total_pag_mes_count?>,<?=$total_pend_mes_count?>], backgroundColor: ['#10b981','#f59e0b'], borderWidth: 0 }] 
    }, 
    options: { 
        responsive: true,
        maintainAspectRatio: false,
        cutout: '65%',
        plugins: { legend: { display: true, position: 'bottom', labels: { usePointStyle: true, padding: 10, font: { size: 11 } } } }
    } 
});

$(document).ready(function(){
    var tablaPendientes = $('#tablaPendientesUnificada').DataTable({
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
        pageLength: 25,
        order: [[5, 'asc']],
        responsive: true,
        dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rtip',
        columnDefs: [
            { targets: [0, 1, 5, 6, 7, 8, 9], orderable: false },
            { targets: '_all', className: 'dt-nowrap' }
        ]
    });
    $(window).on('resize.admDashDt', function () {
        if (tablaPendientes) tablaPendientes.columns.adjust();
    });
});

$(document).on('click', '.mark-paid-btn', function(){
    let id = $(this).data('id');
    Swal.fire({ title:'Marcar como pagado', text:'¿Confirmar el pago?', icon:'question', showCancelButton:true, confirmButtonText:'Sí, pagado', cancelButtonText:'Cancelar', confirmButtonColor:'#10b981' }).then(res=>{
        if(res.isConfirmed){
            $.post('aprobar_pago.php', {id:id, forma_pago:2, sistema:sistema}, (r)=>{
                if(r.success) Swal.fire('Pagado','Pago registrado correctamente.','success').then(()=>location.reload());
                else Swal.fire('Error', r.message || 'No se pudo registrar.', 'error');
            },'json').fail(()=>Swal.fire('Error','Error de conexión.','error'));
        }
    });
});

$(document).on('click', '.reenviar-btn', function(){
    let id     = $(this).data('id');
    let tipo   = $(this).data('tipo');
    let manual = parseInt($(this).data('manual'));
    let url;
    if(manual === 1) url = 'reenviar_correo_pago_pendienteManual.php';
    else if(tipo == 1) url = 'reenviar_correos_hosting.php';
    else url = 'reenviar_correos_dominios.php';

    Swal.fire({
        title: 'Reenviar recordatorio',
        text: 'Se enviará un correo al cliente. ¿Continuar?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Enviar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#3b82f6'
    }).then(function(res){
        if (!res.isConfirmed) return;

        Swal.fire({
            title: 'Enviando correo...',
            text: 'Por favor espera.',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: function(){ Swal.showLoading(); }
        });

        $.ajax({
            url: url,
            type: 'POST',
            data: { id: id, pago_id: id, resend: 1, sistema: sistema },
            dataType: 'json',
            timeout: 30000,
            success: function(r){
                if(r && r.success){
                    Swal.fire('Enviado', r.message || 'Correo enviado correctamente.', 'success');
                } else {
                    Swal.fire('Error', (r && r.message) ? r.message : 'No se pudo enviar el correo.', 'error');
                }
            },
            error: function(xhr, status){
                var msg = (status === 'timeout')
                    ? 'El servidor tardó demasiado. Verifica la configuración SMTP.'
                    : 'Error al conectar: ' + xhr.status + ' ' + xhr.statusText;
                Swal.fire('Error', msg, 'error');
            }
        });
    });
});

$(document).on('shown.bs.modal', '.modal-modern', function(){
    $(this).find('.col-modal-servicio textarea').each(function(){
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });
});

$(document).on('click', '.whatsapp-btn', function(){
    let id = $(this).data('id');
    Swal.fire({ title:'Generando mensaje...', didOpen:()=>Swal.showLoading() });
    $.post('generar_mensaje_whatsapp_pago.php', {id:id, sistema:sistema}, (r)=>{
        if(r.success){
            Swal.close();
            window.open('https://wa.me/'+r.telefono+'?text='+encodeURIComponent(r.mensaje), '_blank');
        } else Swal.fire('Error', r.message || 'No se pudo generar el mensaje.', 'error');
    },'json').fail(()=>Swal.fire('Error','Error de conexión.','error'));
});

$(document).on('click', '.eliminar-btn', function(){
    let id = $(this).data('id');
    Swal.fire({ title:'Eliminar pago', text:'¿Marcar este pago como eliminado?', icon:'warning', showCancelButton:true, confirmButtonText:'Sí, eliminar', cancelButtonText:'Cancelar', confirmButtonColor:'#ef4444' }).then(res=>{
        if(res.isConfirmed){
            $.post('eliminar_pago.php', {id:id, sistema:sistema}, (r)=>{
                if(r.success) Swal.fire('Eliminado','Pago eliminado correctamente.','success').then(()=>location.reload());
                else Swal.fire('Error', r.message || 'No se pudo eliminar.', 'error');
            },'json').fail(()=>Swal.fire('Error','Error de conexión.','error'));
        }
    });
});
</script>

<!-- Modal Pendientes -->
<div class="modal fade modal-modern" id="modalPendientes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-hourglass-half mr-2"></i>Pagos Pendientes - <?= date('F Y', strtotime($fecha_inicio_kpi)) ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="modal-table-wrap">
                    <?php if(count($pendientes_list) > 0): ?>
                    <table class="modal-table">
                        <thead>
                            <tr><th>ID</th><th>Tipo</th><th>Cliente</th><th class="col-modal-servicio">Servicio</th><th class="col-modal-fecha">Fecha de pago</th><th>Monto</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($pendientes_list as $p):
                                $st = cw_dash_estado_vencimiento($p['fecha_limite_efectiva'] ?? null, $hoy);
                                $dias = $st['dias'];
                                $esManual = isset($p['manual']) && $p['manual'] == 1;
                                $tipo = $esManual ? 'Manual' : ($p['tipo_servicio'] == 1 ? 'Hosting' : 'Dominio');
                            ?>
                            <tr>
                                <td><code class="text-muted">#<?=$p['id']?></code></td>
                                <td><span class="badge-type <?=$esManual?'badge-manual':($p['tipo_servicio']==1?'badge-hosting':'badge-dominio')?>"><?=$tipo?></span></td>
                                <td><strong><?=htmlspecialchars($p['cliente'])?></strong></td>
                                <td class="col-modal-servicio"><textarea readonly rows="1"><?=htmlspecialchars($p['nombre_servicio'] ?? '-')?></textarea></td>
                                <td class="col-modal-fecha">
                                    <span class="badge-status badge-proximo">Pendiente</span>
                                    <?php if($dias !== null):?><span class="ml-1 badge-status <?=$st['vencido']?'badge-vencido':'badge-proximo'?>"><?=$dias?> d</span><?php endif;?>
                                </td>
                                <td>
                                    <strong>$<?=number_format($p['monto'],2)?></strong>
                                    <?php $pc = strtoupper(trim($p['currency'] ?? '')) ?: 'MXN'; ?>
                                    <span class="ml-1" style="font-size:0.65rem;font-weight:600;color:var(--text-secondary);"><?=$pc?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="text-center py-5"><i class="fas fa-inbox mb-3" style="font-size:2.5rem;color:#cbd5e1"></i><p class="text-muted mb-0">No hay pagos pendientes en este período</p></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if(count($pendientes_list) > 0): ?>
            <div class="modal-total-bar" style="background:#fafbff;border-top:2px solid var(--border);padding:0.75rem 1rem;">
                <div class="d-flex flex-wrap justify-content-end gap-3">
                    <?php foreach($pend_cur as $cur => $tot): ?>
                    <div class="d-flex align-items-center" style="gap:6px;">
                        <span class="badge" style="background:var(--primary-soft);color:var(--primary-dark);font-weight:700;font-size:0.7rem;padding:3px 8px;border-radius:6px;"><?=$cur?></span>
                        <span style="color:var(--warning);font-weight:700;font-size:1rem">$<?=number_format($tot,2)?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius:10px;font-weight:600">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Recibidos -->
<div class="modal fade modal-modern" id="modalRecibidos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-check-circle mr-2" style="color:#10b981"></i>Pagos Recibidos - <?= date('F Y', strtotime($fecha_inicio_kpi)) ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="modal-table-wrap">
                    <?php if(count($pagados_list) > 0): ?>
                    <table class="modal-table">
                        <thead>
                            <tr><th>ID</th><th>Tipo</th><th>Cliente</th><th>Servicio</th><th>Fecha Pago</th><th>Forma Pago</th><th>Monto</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($pagados_list as $p):
                                $fecha = $p['fecha_pago'] ?? '';
                                $esManual = isset($p['manual']) && $p['manual'] == 1;
                                $tipo = $esManual ? 'Manual' : ($p['tipo_servicio'] == 1 ? 'Hosting' : 'Dominio');
                                $forma = $p['forma_pago']==1?'Tarjeta':($p['forma_pago']==2?'Transferencia':($p['forma_pago']==3?'Efectivo':'-'));
                            ?>
                            <tr>
                                <td><code class="text-muted">#<?=$p['id']?></code></td>
                                <td><span class="badge-type <?=$esManual?'badge-manual':($p['tipo_servicio']==1?'badge-hosting':'badge-dominio')?>"><?=$tipo?></span></td>
                                <td><strong><?=htmlspecialchars($p['cliente'])?></strong></td>
                                <td class="col-modal-servicio"><textarea readonly rows="1"><?=htmlspecialchars($p['nombre_servicio'] ?? '-')?></textarea></td>
                                <td><?=$fecha?date('d/m/Y',strtotime($fecha)):'-'?></td>
                                <td><span class="badge-forma <?=$p['forma_pago']==1?'badge-tarjeta':($p['forma_pago']==2?'badge-transferencia':'badge-efectivo')?>"><?=$forma?></span></td>
                                <td>
                                    <strong>$<?=number_format($p['monto'],2)?></strong>
                                    <?php $rc = strtoupper(trim($p['currency'] ?? '')) ?: 'MXN'; ?>
                                    <span class="ml-1" style="font-size:0.65rem;font-weight:600;color:var(--text-secondary);"><?=$rc?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="text-center py-5"><i class="fas fa-inbox mb-3" style="font-size:2.5rem;color:#cbd5e1"></i><p class="text-muted mb-0">No hay pagos recibidos en este período</p></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if(count($pagados_list) > 0): ?>
            <div class="modal-total-bar" style="background:#fafbff;border-top:2px solid var(--border);padding:0.75rem 1rem;">
                <div class="d-flex flex-wrap justify-content-end gap-3">
                    <?php foreach($pag_cur as $cur => $tot): ?>
                    <div class="d-flex align-items-center" style="gap:6px;">
                        <span class="badge" style="background:var(--primary-soft);color:var(--primary-dark);font-weight:700;font-size:0.7rem;padding:3px 8px;border-radius:6px;"><?=$cur?></span>
                        <span style="color:var(--success);font-weight:700;font-size:1rem">$<?=number_format($tot,2)?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius:10px;font-weight:600">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Global Pendientes -->
<div class="modal fade modal-modern" id="modalGlobalPendientes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--danger)">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-exclamation-circle mr-2"></i>Todos los Pagos Pendientes (Global)</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="modal-table-wrap">
                    <?php if(count($global_pendientes) > 0): ?>
                    <table class="modal-table">
                        <thead>
                            <tr><th>ID</th><th>Tipo</th><th>Cliente</th><th class="col-modal-servicio">Servicio</th><th class="col-modal-fecha">Fecha de pago</th><th>Monto</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($global_pendientes as $p):
                                $st = cw_dash_estado_vencimiento($p['fecha_limite_efectiva'] ?? null, $hoy);
                                $dias = $st['dias'];
                                $esManual = isset($p['manual']) && $p['manual'] == 1;
                                $tipo = $esManual ? 'Manual' : ($p['tipo_servicio'] == 1 ? 'Hosting' : 'Dominio');
                            ?>
                            <tr>
                                <td><code class="text-muted">#<?=$p['id']?></code></td>
                                <td><span class="badge-type <?=$esManual?'badge-manual':($p['tipo_servicio']==1?'badge-hosting':'badge-dominio')?>"><?=$tipo?></span></td>
                                <td><strong><?=htmlspecialchars($p['cliente'])?></strong></td>
                                <td class="col-modal-servicio"><textarea readonly rows="1"><?=htmlspecialchars($p['nombre_servicio'] ?? '-')?></textarea></td>
                                <td class="col-modal-fecha">
                                    <span class="badge-status badge-proximo">Pendiente</span>
                                    <?php if($dias !== null):?><span class="ml-1 badge-status <?=$st['vencido']?'badge-vencido':'badge-proximo'?>"><?=$dias?> d</span><?php endif;?>
                                </td>
                                <td>
                                    <strong>$<?=number_format($p['monto'],2)?></strong>
                                    <?php $gc = strtoupper(trim($p['currency'] ?? '')) ?: 'MXN'; ?>
                                    <span class="ml-1" style="font-size:0.65rem;font-weight:600;color:var(--text-secondary);"><?=$gc?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="text-center py-5"><i class="fas fa-check-circle mb-3" style="font-size:2.5rem;color:#10b981"></i><p class="text-muted mb-0">No hay pagos pendientes globales</p></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if(count($global_pendientes) > 0): ?>
            <div class="modal-total-bar" style="background:#fafbff;border-top:2px solid var(--border);padding:0.75rem 1rem;">
                <div class="d-flex flex-wrap justify-content-end gap-3">
                    <?php foreach($global_cur as $cur => $tot): ?>
                    <div class="d-flex align-items-center" style="gap:6px;">
                        <span class="badge" style="background:var(--primary-soft);color:var(--primary-dark);font-weight:700;font-size:0.7rem;padding:3px 8px;border-radius:6px;"><?=$cur?></span>
                        <span style="color:var(--danger);font-weight:700;font-size:1rem">$<?=number_format($tot,2)?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius:10px;font-weight:600">Cerrar</button>
            </div>
        </div>
    </div>
</div>

</body>
</html>