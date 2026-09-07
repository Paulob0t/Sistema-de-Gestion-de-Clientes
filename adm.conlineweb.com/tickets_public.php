<?php
require_once __DIR__.'/link_helper.php';
require_once __DIR__.'/conn.php';
require_once __DIR__.'/solicitudes/helpers_media.php';

// Detectar existencia de tabla de notas para evitar errores si no existe
$hasNotasTable = false;
try {
    if($chk = $conn->query("SHOW TABLES LIKE 'solicitudes_notas'")){
        $hasNotasTable = $chk->num_rows>0; $chk->close();
    }
} catch(Exception $e) { $hasNotasTable = false; }

$token = isset($_GET['token']) ? $_GET['token'] : '';
$val = validar_link_token($token);
if(!$val['valid']){
    http_response_code(400);
    ?><!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Link inválido</title><style>body{font-family:system-ui;background:#0f1420;color:#eee;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0} .card{background:#1e2633;padding:34px 42px;border-radius:18px;max-width:480px;box-shadow:0 10px 32px -8px rgba(0,0,0,.55);} h1{margin:0 0 14px;font-size:1.3rem;} .msg{opacity:.8;font-size:.85rem;margin-bottom:22px;} a{color:#8ab4ff;text-decoration:none;} a:hover{text-decoration:underline;}</style></head><body><div class="card"><h1>Link inválido</h1><div class="msg">Motivo: <?= htmlspecialchars($val['error']??'desconocido') ?></div><a href="/">Inicio</a></div></body></html><?php
    exit;
}

$idCliente = (int)$val['id_cliente'];
$idProyecto = (int)$val['id_proyecto'];
$nombreMSJ = isset($val['nombreMSJ']) ? trim((string)$val['nombreMSJ']) : '';

// Obtener datos del cliente
$cliNombre = 'Cliente';
if($stmt = $conn->prepare('SELECT empresa, nombre_contacto FROM clientes WHERE id=? LIMIT 1')){
    $stmt->bind_param('i',$idCliente); $stmt->execute(); $r = $stmt->get_result();
    if($row=$r->fetch_assoc()){
        $cliNombre = ($row['nombre_contacto']? $row['nombre_contacto'].' - ':'').$row['empresa'];
    }
    $stmt->close();
}

$where = 'WHERE s.id_cliente = '.$idCliente;
if($idProyecto>0){ $where .= ' AND s.id_proyecto='.(int)$idProyecto; }

$sqlNotasJoin = $hasNotasTable ? "LEFT JOIN (SELECT solicitud_id, COUNT(*) cnt FROM solicitudes_notas GROUP BY solicitud_id) sn ON sn.solicitud_id = s.id" : "";
$sql = "SELECT s.id, s.id_proyecto, s.titulo, s.estado, s.prioridad, s.fecha_solicitud, s.fecha_lim, s.fecha_termina, s.descripcion, s.nombreMSJ, p.nombre_proyecto, ".($hasNotasTable?"COALESCE(sn.cnt,0)":"0")." AS notas_count FROM solicitudes s LEFT JOIN proyectos p ON s.id_proyecto=p.id_proyecto $sqlNotasJoin $where ORDER BY s.fecha_solicitud DESC";
$res = $conn->query($sql);
$tickets=[];
if($res){ while($row=$res->fetch_assoc()){ $tickets[]=$row; } }

function formatFecha($f){ if(!$f||$f==='0000-00-00 00:00:00') return '-'; return date('d/m/Y H:i', strtotime($f)); }
?><!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Tickets - Vista Cliente</title><meta name="viewport" content="width=device-width,initial-scale=1" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&family=Montserrat:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
<style>
:root {
    --bg-primary: #0a0f1c;
    --bg-secondary: #151b2b;
    --bg-card: #1a2235;
    --bg-hover: #212b43;
    --accent-primary: #6366f1;
    --accent-secondary: #8b5cf6;
    --text-primary: #f1f5f9;
    --text-secondary: #94a3b8;
    --text-muted: #64748b;
    --success: #10b981;
    --warning: #f59e0b;
    --error: #ef4444;
    --info: #3b82f6;
    --border: #2d3748;
    --shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    --gradient: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Montserrat', 'Segoe UI', system-ui, -apple-system, sans-serif;
    background: var(--bg-primary);
    color: var(--text-primary);
    min-height: 100vh;
    line-height: 1.6;
}

.container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 20px;
}

/* Header Styles - Alineado a la izquierda */
.header {
    padding: 30px 0 20px;
    text-align: left;
}

.header-content {
    background: var(--bg-card);
    padding: 25px 30px;
    border-radius: 16px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
}

.header-title {
    font-family: 'Ubuntu', sans-serif;
    font-size: 1.8rem;
    font-weight: 700;
    color: #ffffff; /* Título en blanco */
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.header-badge {
    display: inline-block;
    background: var(--gradient);
    color: white;
    padding: 6px 12px;
    border-radius: 10px;
    font-size: 0.7rem;
    font-weight: 300;
    letter-spacing: 0.5px;
}

.client-info {
    color: var(--text-secondary);
    font-size: 0.85rem;
    margin-top: 8px;
}

/* Main Content */
.main-content {
    padding: 20px 0 40px;
}

.dashboard-card {
    background: var(--bg-card);
    border-radius: 16px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    overflow: hidden;
    margin-bottom: 30px;
}

.card-header {
    padding: 20px 25px;
    border-bottom: 1px solid var(--border);
    background: rgba(255, 255, 255, 0.02);
}

.card-title {
    font-family: 'Ubuntu', sans-serif;
    font-size: 1.2rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-title i {
    color: var(--accent-primary);
}

/* Filters & Stats Layout */
.filters-container {
    padding: 20px 25px;
    border-bottom: 1px solid var(--border);
    background: rgba(255, 255, 255, 0.01);
}

.search-group {
    margin-bottom: 10px;
}

.search-box {
    position: relative;
    max-width: 400px;
}

.search-input {
    width: 100%;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    color: var(--text-primary);
    padding: 12px 45px 12px 15px;
    border-radius: 10px;
    font-size: 0.85rem;
    outline: none;
    transition: all 0.3s ease;
}

.search-input:focus {
    border-color: var(--accent-primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.search-icon {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
}

/* Contenedor 50/50 para filtros y estadísticas */
.filters-and-stats-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    align-items: end; /* Alinea las dos columnas en la parte inferior */
}

/* Contenedor para los filtros */
.selects-wrapper {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 12px;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.filter-label {
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.filter-select {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    color: var(--text-primary);
    padding: 10px 12px;
    border-radius: 10px;
    font-size: 0.8rem;
    outline: none;
    transition: all 0.3s ease;
    cursor: pointer;
}

.filter-select:focus {
    border-color: var(--accent-primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.filter-select:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Estilos para el selector de fechas */
.filter-select[readonly] {
    cursor: pointer;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23cbd5e1' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3crect x='3' y='4' width='18' height='18' rx='2' ry='2'%3e%3c/rect%3e%3cline x1='16' y1='2' x2='16' y2='6'%3e%3c/line%3e%3cline x1='8' y1='2' x2='8' y2='6'%3e%3c/line%3e%3cline x1='3' y1='10' x2='21' y2='10'%3e%3c/line%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 10px center;
    background-size: 16px;
    padding-right: 35px;
}

/* Personalización de Flatpickr para el tema oscuro */
.flatpickr-calendar {
    background: var(--bg-card) !important;
    border: 1px solid var(--border) !important;
    box-shadow: var(--shadow) !important;
}

.flatpickr-calendar .flatpickr-months {
    background: var(--bg-secondary) !important;
}

.flatpickr-calendar .flatpickr-weekdays {
    background: var(--bg-secondary) !important;
}

.flatpickr-calendar .flatpickr-day {
    color: var(--text-primary) !important;
}

.flatpickr-calendar .flatpickr-day:hover {
    background: var(--bg-hover) !important;
    color: var(--text-primary) !important;
}

.flatpickr-calendar .flatpickr-day.selected {
    background: var(--accent-primary) !important;
    color: white !important;
}

.flatpickr-calendar .flatpickr-day.inRange {
    background: rgba(99, 102, 241, 0.2) !important;
    color: var(--text-primary) !important;
}

/* Stats - adaptado al nuevo layout */
.stats-container {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    margin-top: 6px; /* Espacio entre el título "Estadísticos" y los contadores */
}

.stat-item {
    background: var(--bg-secondary);
    padding: 12px 16px;
    border-radius: 10px;
    font-size: 0.8rem;
    font-weight: 600;
    border: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 8px;
    /* Se elimina flex-grow para que no se estiren */
}

.stat-value {
    color: var(--accent-primary);
    font-size: 1.1rem;
    font-weight: 700;
}

/* Table */
.table-container {
    overflow-x: auto;
    position: relative;
}

.tickets-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8rem;
    table-layout: fixed;
    min-width: 800px;
}

.tickets-table th {
    background: rgba(255, 255, 255, 0.02);
    padding: 15px 12px;
    text-align: left;
    font-weight: 600;
    color: var(--text-secondary);
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid var(--border);
    position: sticky;
    top: 0;
    z-index: 10;
}

.tickets-table td {
    padding: 15px 12px;
    border-bottom: 1px solid var(--border);
    vertical-align: top;
    word-wrap: break-word;
}

.tickets-table tr {
    transition: all 0.3s ease;
}

.tickets-table tr:not(:last-child) {
    border-bottom: 1px solid var(--border);
}

.tickets-table tr:hover {
    background: var(--bg-hover);
}

    /* Ajuste de anchos (se agrega columna Notas como última antes de Descripción) */
    .tickets-table th:nth-child(1), .tickets-table td:nth-child(1) { width: 4.5%; }
    .tickets-table th:nth-child(2), .tickets-table td:nth-child(2) { width: 14%; }
    .tickets-table th:nth-child(3), .tickets-table td:nth-child(3) { width: 14%; }
    .tickets-table th:nth-child(4), .tickets-table td:nth-child(4) { width: 10%; }
    .tickets-table th:nth-child(5), .tickets-table td:nth-child(5) { width: 9%; }
    .tickets-table th:nth-child(6), .tickets-table td:nth-child(6) { width: 11%; }
    .tickets-table th:nth-child(7), .tickets-table td:nth-child(7) { width: 7%; }
    .tickets-table th:nth-child(8), .tickets-table td:nth-child(8) { width: 30%; }

/* Badges */
.status-badge {
    padding: 6px 10px;
    border-radius: 8px;
    font-size: 0.7rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.status-pending { background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); }
.status-process { background: rgba(59, 130, 246, 0.15); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); }
.status-finished { background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); }

.priority-badge {
    padding: 5px 8px;
    border-radius: 6px;
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.priority-high { background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
.priority-medium { background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); }
.priority-low { background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); }

/* Description Module */
.desc-wrapper {
    position: relative;
}

.desc {
    white-space: pre-line;
    font-size: 0.7rem;
    line-height: 1.25;
    margin-top: 4px;
    overflow-wrap: break-word;
}

.desc ul {
    margin: 4px 0 4px 18px;
    padding: 0;
    list-style: disc;
}

.desc li {
    margin: 2px 0;
}

.desc .kv {
    margin: 2px 0;
}

.desc .kv b {
    color: #8ab4ff;
    font-weight: 600;
}

.desc .color-badge {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 12px;
    font-size: 0.55rem;
    font-weight: 600;
    margin: 2px 4px 2px 0;
    border: 1px solid rgba(255, 255, 255, 0.15);
}

.desc-collapsed {
    max-height: 140px;
    overflow: hidden;
    mask: linear-gradient(#000 110px, transparent);
}

.desc-toggle {
    display: inline-block;
    margin-top: 4px;
    font-size: 0.6rem;
    background: #1f2a3a;
    border: 1px solid #314255;
    color: #b9d3ff;
    padding: 4px 8px;
    border-radius: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.desc-toggle:hover {
    background: #253449;
}

/* Media Grid */
.img-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 6px;
}

.img-grid img {
    width: 70px;
    height: 70px;
    object-fit: cover;
    border-radius: 6px;
    cursor: pointer;
    border: 1px solid rgba(255, 255, 255, 0.15);
    transition: all 0.3s ease;
}

.img-grid img:hover {
    transform: scale(1.05);
    border-color: var(--accent-primary);
}

.file-list {
    margin-top: 6px;
}

.file-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.65rem;
    margin: 3px 0;
}

.file-item a {
    color: #8ab4ff;
    text-decoration: none;
    word-break: break-all;
}

.file-item a:hover {
    text-decoration: underline;
}

.ticket-dates {
    font-size: 0.65rem;
    opacity: 0.75;
    white-space: pre-line;
}

/* Lightbox */
.lightbox {
    position: fixed;
    inset: 0;
    background: rgba(10, 15, 28, 0.95);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    z-index: 3500; /* elevado para sobreponer modal de notas (z-index 2000) */
    backdrop-filter: blur(10px);
}

.lightbox.active {
    opacity: 1;
    visibility: visible;
}

.lightbox-content {
    position: relative;
    max-width: 90vw;
    max-height: 90vh;
}

.lightbox-img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    border-radius: 12px;
    box-shadow: var(--shadow);
}

.lightbox-close {
    position: absolute;
    top: -50px;
    right: 0;
    background: var(--error);
    border: none;
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.lightbox-close:hover {
    background: #dc2626;
    transform: scale(1.1);
}

/* Footer */
.footer {
    text-align: center;
    padding: 20px 0;
    color: var(--text-muted);
    font-size: 0.7rem;
}

/* Empty State */
.no-data {
    padding: 40px 20px;
    text-align: center;
    font-size: 0.8rem;
    opacity: 0.7;
}

/* Responsive */
@media (max-width: 1200px) {
    .container {
        max-width: 95%;
    }
}

@media (max-width: 992px) {
    .filters-and-stats-container {
        grid-template-columns: 1fr;
    }
    
    .selects-wrapper {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
}

@media (max-width: 600px) {
    .selects-wrapper {
        grid-template-columns: 1fr;
    }
    
    .stats-container {
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
    }
}

@media (max-width: 768px) {
    .container {
        padding: 0 15px;
    }
    
    .header-content {
        padding: 20px;
    }
    
    .card-header,
    .filters-container {
        padding: 15px 20px;
    }
    
    .tickets-table {
        font-size: 0.75rem;
    }
    
    .tickets-table th,
    .tickets-table td {
        padding: 12px 8px;
    }
    
    .stat-item {
        width: 100%;
        justify-content: space-between;
    }
    
    .tickets-table th:nth-child(1), .tickets-table td:nth-child(1) { width: 7%; }
    .tickets-table th:nth-child(2), .tickets-table td:nth-child(2) { width: 19%; }
    .tickets-table th:nth-child(3), .tickets-table td:nth-child(3) { width: 17%; }
    .tickets-table th:nth-child(4), .tickets-table td:nth-child(4) { width: 11%; }
    .tickets-table th:nth-child(5), .tickets-table td:nth-child(5) { width: 11%; }
    .tickets-table th:nth-child(6), .tickets-table td:nth-child(6) { width: 12%; }
    .tickets-table th:nth-child(7), .tickets-table td:nth-child(7) { width: 9%; }
    .tickets-table th:nth-child(8), .tickets-table td:nth-child(8) { width: 14%; }
}

@media (max-width: 480px) {
    .header {
        padding: 20px 0 15px;
    }
    
    .header-title {
        font-size: 1.4rem;
    }
    
    .search-box {
        max-width: 100%;
    }
    
    .tickets-table {
        font-size: 0.7rem;
    }
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.dashboard-card {
    animation: fadeIn 0.6s ease;
}

.tickets-table tr {
    animation: fadeIn 0.4s ease;
}
</style></head>
<body>
<div class="container">
    <header class="header">
        <div class="header-content">
            <h1 class="header-title">
                <i class="fas fa-ticket-alt"></i>
                Estatus de Solicitudes
                <span class="header-badge">VISTA PÚBLICA</span>
            </h1>
            <div class="client-info">
                <i class="fas fa-user"></i>
                Cliente: <?= htmlspecialchars($cliNombre) ?>
                <?= $idProyecto>0? ' | Proyecto filtrado #'.(int)$idProyecto:'' ?>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="dashboard-card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-list"></i>
                    Listado de Tickets
                </h2>
            </div>

            <div class="filters-container">
                <div class="search-group">
                    <div class="search-box">
                        <input type="text" class="search-input" id="searchInput" placeholder="Buscar en títulos, descripciones...">
                        <i class="fas fa-search search-icon"></i>
                    </div>
                </div>

                <div class="filters-and-stats-container">

                    <div class="selects-wrapper">
                        <div class="filter-group">
                            <label class="filter-label" for="fltProyecto">
                                <i class="fas fa-project-diagram"></i>
                                Proyecto
                            </label>
                            <select class="filter-select" id="fltProyecto">
                                <option value="">Todos los proyectos</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label" for="fltEstado">
                                <i class="fas fa-filter"></i>
                                Estado
                            </label>
                            <select class="filter-select" id="fltEstado">
                                <option value="">Todos los estados</option>
                                <option value="Pendiente">Pendiente</option>
                                <option value="En Proceso">En Proceso</option>
                                <option value="Finalizado">Finalizado</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label" for="fltPrioridad">
                                <i class="fas fa-exclamation-triangle"></i>
                                Prioridad
                            </label>
                            <select class="filter-select" id="fltPrioridad">
                                <option value="">Todas las prioridades</option>
                                <option value="Alta">Alta</option>
                                <option value="Media">Media</option>
                                <option value="Baja">Baja</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label" for="fltFechaRango">
                                <i class="fas fa-calendar-alt"></i>
                                Rango de Fechas
                            </label>
                            <input type="text" class="filter-select" id="fltFechaRango" placeholder="Seleccionar rango..." readonly>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label" for="fltNombreMSJ">
                                <i class="fas fa-user-tag"></i>
                                Nombre MSJ
                            </label>
                            <select class="filter-select" id="fltNombreMSJ">
                                <option value="">Todos los nombres</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="filter-label">
                            <i class="fas fa-chart-pie"></i>
                            Estadísticos
                        </label>
                        <div class="stats-container" id="countsBar"></div>
                    </div>

                </div>
            </div>

            <div class="table-container">
                <table class="tickets-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Título</th>
                            <th>Proyecto</th>
                            <th>Estado</th>
                            <th>Prioridad</th>
                            <th>Fechas</th>
                            <th>Notas</th>
                            <th>Descripción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!$tickets): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="no-data">No hay tickets registrados.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($tickets as $t): ?>
                                <?php
                                $descData = json_decode($t['descripcion'], true);
                                if (!is_array($descData)) {
                                    $try = json_decode(stripslashes((string) $t['descripcion']), true);
                                    if (is_array($try)) {
                                        $descData = $try;
                                    }
                                }
                                $descText = is_array($descData) ? (string) ($descData['text'] ?? $t['descripcion']) : (string) $t['descripcion'];
                                $imgsRaw = (is_array($descData) && isset($descData['images']) && is_array($descData['images'])) ? $descData['images'] : [];
                                $filesRaw = (is_array($descData) && isset($descData['files']) && is_array($descData['files'])) ? $descData['files'] : [];
                                $imgs = cw_ticket_media_urls($imgsRaw);
                                $files = cw_ticket_media_urls($filesRaw);
                                $estadoClass = $t['estado']==='Pendiente'?'status-pending':($t['estado']==='En Proceso'?'status-process':($t['estado']==='Finalizado'?'status-finished':''));
                                $prioClass = $t['prioridad']==='Alta'?'priority-high':($t['prioridad']==='Media'?'priority-medium':'priority-low');
                                $rowProyectoId = (int)$t['id_proyecto'];
                                $rowNombreMSJ = trim((string)($t['nombreMSJ'] ?? ''));
                                ?>
                                <tr data-proyecto="<?= $rowProyectoId ?>" 
                                    data-estado="<?= htmlspecialchars($t['estado']) ?>" 
                                    data-nombremsj="<?= htmlspecialchars($rowNombreMSJ, ENT_QUOTES) ?>"
                                    data-prioridad="<?= htmlspecialchars($t['prioridad']) ?>"
                                    data-fecha="<?= htmlspecialchars($t['fecha_solicitud']) ?>"
                                    data-notas="<?= (int)($t['notas_count'] ?? 0) ?>"
                                    data-archivos="<?= (count($imgs) + count($files)) > 0 ? '1' : '0' ?>">
                                    <td><strong>#<?= (int)$t['id'] ?></strong></td>
                                    <td><?= htmlspecialchars($t['titulo']) ?></td>
                                    <td><?= htmlspecialchars($t['nombre_proyecto']??'-') ?></td>
                                    <td>
                                        <span class="status-badge <?= $estadoClass ?>">
                                            <?= htmlspecialchars($t['estado']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="priority-badge <?= $prioClass ?>">
                                            <?= htmlspecialchars($t['prioridad']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="ticket-dates">
                                            Ini: <?= formatFecha($t['fecha_solicitud']) ?>
                                            Lim: <?= formatFecha($t['fecha_lim']) ?>
                                            Fin: <?= formatFecha($t['fecha_termina']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php $notasCount = (int)($t['notas_count'] ?? 0); ?>
                                        <button type="button" class="notes-btn" data-ticket="<?= (int)$t['id'] ?>" aria-label="Ver notas del ticket #<?= (int)$t['id'] ?>">
                                            <i class="fas fa-comments"></i>
                                            <span class="notes-count <?= $notasCount>0?'has':'empty' ?>" title="Notas"><?= $notasCount ?></span>
                                        </button>
                                    </td>
                                    <td>
                                        <div class="desc-wrapper">
                                            <div class="desc" data-raw="<?= htmlspecialchars($descText, ENT_QUOTES) ?>">
                                                <?= htmlspecialchars($descText) ?>
                                            </div>
                                            <button type="button" class="desc-toggle" style="display:none">Ver más</button>
                                        </div>
                                        
                                        <?php if($imgs): ?>
                                            <div class="img-grid">
                                                <?php foreach($imgs as $ii=>$img): ?>
                                                    <?php
                                                    $safe = htmlspecialchars($img, ENT_QUOTES, 'UTF-8');
                                                    $altRel = '';
                                                    if (strpos($img, '/solicitudes/uploads/solicitudes/') !== false) {
                                                        $altRel = htmlspecialchars(str_replace('/solicitudes/uploads/solicitudes/', '/solicitudes/uploads/', $img), ENT_QUOTES, 'UTF-8');
                                                    } elseif (strpos($img, '/solicitudes/uploads/') !== false) {
                                                        $altRel = htmlspecialchars(str_replace('/solicitudes/uploads/', '/solicitudes/uploads/solicitudes/', $img), ENT_QUOTES, 'UTF-8');
                                                    }
                                                    ?>
                                                    <img src="<?= $safe ?>" data-src="<?= $safe ?>"<?= $altRel ? ' data-alt-src="' . $altRel . '"' : '' ?> alt="Referencia visual" loading="lazy" />
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if($files): ?>
                                            <div class="file-list">
                                                <?php foreach($files as $f): ?>
                                                    <?php $safe=htmlspecialchars($f, ENT_QUOTES, 'UTF-8'); $base=htmlspecialchars(basename(urldecode(parse_url($f, PHP_URL_PATH) ?: $f)), ENT_QUOTES, 'UTF-8'); ?>
                                                    <div class="file-item">
                                                        <i class="fas fa-paperclip"></i>
                                                        <a href="<?= $safe ?>" target="_blank" rel="noopener"><?= $base ?></a>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <footer class="footer">
        <p>Enlace generado de forma segura. Si necesitas revocarlo, contacta al administrador.</p>
    </footer>
</div>

<div class="lightbox" id="lb">
    <div class="lightbox-content">
        <img class="lightbox-img" id="lbImg" src="" alt="" />
        <button class="lightbox-close" id="lbClose">
            <i class="fas fa-times"></i>
        </button>
    </div>
</div>

<!-- Modal Notas -->
<div class="notes-modal-overlay" id="notesModal">
    <div class="notes-modal">
        <div class="notes-modal-header">
            <h3 class="notes-modal-title"><i class="fas fa-comments"></i> Notas Ticket <span id="notesModalTicket"></span></h3>
            <button class="notes-modal-close" id="notesModalClose" aria-label="Cerrar">&times;</button>
        </div>
        <div class="notes-modal-body" id="notesModalBody">
            <div class="notes-loading" id="notesLoading" style="display:none;">Cargando notas...</div>
            <div class="notes-list" id="notesList"></div>
        </div>
        <div class="notes-modal-footer">
            <button type="button" class="notes-modal-btn" id="notesModalOk">Cerrar</button>
        </div>
    </div>
</div>

<script>
// --- Estilos dinámicos para notas (incrustados) ---
const notesStyle = document.createElement('style');
notesStyle.textContent = `
    .notes-btn { background: var(--bg-secondary); border:1px solid var(--border); color: var(--text-secondary); padding:6px 10px; border-radius:10px; font-size:0.65rem; font-weight:600; display:inline-flex; align-items:center; gap:6px; cursor:pointer; position:relative; transition:.25s; }
    .notes-btn:hover { background: var(--bg-hover); color: var(--text-primary); }
    .notes-count { background:#334155; color:#cbd5e1; padding:2px 6px; border-radius:20px; font-size:.55rem; font-weight:700; min-width:20px; text-align:center; display:inline-block; }
    .notes-count.has { background: var(--accent-primary); color:#fff; }
    .notes-count.empty { opacity:.45; }
    .notes-modal-overlay { position:fixed; inset:0; background:rgba(10,15,28,.85); backdrop-filter:blur(6px); display:flex; align-items:center; justify-content:center; z-index:2000; opacity:0; visibility:hidden; transition:.3s; }
    .notes-modal-overlay.active { opacity:1; visibility:visible; }
    .notes-modal { width:100%; max-width:640px; background:var(--bg-card); border:1px solid var(--border); border-radius:18px; display:flex; flex-direction:column; max-height:82vh; box-shadow:var(--shadow); }
    .notes-modal-header { padding:16px 22px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; }
    .notes-modal-title { margin:0; font-size:1rem; font-family:'Ubuntu',sans-serif; font-weight:600; display:flex; align-items:center; gap:8px; }
    .notes-modal-close { background:none; border:none; color: var(--text-secondary); font-size:1.4rem; cursor:pointer; line-height:1; }
    .notes-modal-close:hover { color: var(--text-primary); }
    .notes-modal-body { padding:18px 22px 8px; overflow-y:auto; flex:1; }
    .notes-loading { font-size:.75rem; opacity:.7; }
    .note-item { border:1px solid var(--border); background:var(--bg-secondary); padding:10px 12px 10px; border-radius:12px; margin:0 0 12px; font-size:.7rem; line-height:1.35; position:relative; }
    .note-header { display:flex; justify-content:space-between; align-items:center; margin:0 0 6px; font-size:.6rem; text-transform:uppercase; letter-spacing:.5px; color: var(--text-secondary); }
    .note-author { font-weight:600; color: var(--accent-primary); font-size:.65rem; }
    .note-date { font-size:.55rem; opacity:.7; }
    .note-text { white-space:pre-line; font-size:.7rem; }
    .note-images { display:flex; flex-wrap:wrap; gap:6px; margin-top:6px; }
    .note-images img { width:64px; height:64px; object-fit:cover; border-radius:8px; cursor:pointer; border:1px solid rgba(255,255,255,.15); }
    .notes-modal-footer { padding:14px 20px 18px; border-top:1px solid var(--border); text-align:right; }
    .notes-modal-btn { background: var(--gradient); border:none; color:#fff; padding:10px 18px; border-radius:12px; font-size:.65rem; font-weight:600; letter-spacing:.5px; cursor:pointer; }
    .notes-modal-btn:hover { filter:brightness(1.05); }
`;
document.head.appendChild(notesStyle);

// Lightbox functionality
const lb = document.getElementById('lb');
const lbImg = document.getElementById('lbImg');
const lbClose = document.getElementById('lbClose');

function closeLightbox() { 
    lb.classList.remove('active');
    document.body.style.overflow = 'auto';
}

lbClose.onclick = closeLightbox;
lb.addEventListener('click', e => { 
    if(e.target === lb) closeLightbox(); 
});

function swapPathOnce(el) {
    if(el.dataset.swapped) return false;
    el.dataset.swapped = '1';
    const alt = el.getAttribute('data-alt-src');
    if(alt){
        el.src = alt;
        return true;
    }
    if(el.src.includes('/uploads/solicitudes/')) { 
        el.src = el.src.replace('/uploads/solicitudes/','/solicitudes/uploads/solicitudes/'); 
        return true; 
    }
    if(el.src.includes('/solicitudes/uploads/solicitudes/')) { 
        el.src = el.src.replace('/solicitudes/uploads/solicitudes/','/solicitudes/uploads/'); 
        return true; 
    }
    if(el.src.includes('/solicitudes/uploads/') && !el.src.includes('/solicitudes/uploads/solicitudes/')) {
        el.src = el.src.replace('/solicitudes/uploads/','/solicitudes/uploads/solicitudes/');
        return true;
    }
    return false;
}

function openLightbox(src) {
    lbImg.style.opacity = '0';
    lb.classList.add('active');
    document.body.style.overflow = 'hidden';
    
    lbImg.onload = () => { 
        lbImg.style.opacity = '1'; 
    };
    
    lbImg.onerror = function() {
        if(!swapPathOnce(lbImg)) {
            if(!document.getElementById('lbErrMsg')) {
                const msg = document.createElement('div');
                msg.id = 'lbErrMsg';
                msg.style.color = '#94a3b8';
                msg.style.textAlign = 'center';
                msg.style.marginTop = '15px';
                msg.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Imagen no disponible';
                lb.querySelector('.lightbox-content').appendChild(msg);
            }
        }
    };
    
    const err = document.getElementById('lbErrMsg'); 
    if(err) err.remove();
    
    lbImg.removeAttribute('data-swapped');
    lbImg.src = src;
}

// Initialize thumbnails
function initThumbnail(img) {
    img.onerror = () => swapPathOnce(img);
    img.addEventListener('click', () => openLightbox(img.currentSrc || img.src || img.getAttribute('data-src')));
    if(!img.getAttribute('src')){
        const raw = img.getAttribute('data-src');
        if(raw) img.src = normalizeMediaPath(raw);
    }
}

Array.from(document.querySelectorAll('.img-grid img')).forEach(initThumbnail);

// Filter functionality
const searchInput = document.getElementById('searchInput');
const fltProyecto = document.getElementById('fltProyecto');
const fltEstado = document.getElementById('fltEstado');
const fltPrioridad = document.getElementById('fltPrioridad');
const fltFechaRango = document.getElementById('fltFechaRango');
const fltNombreMSJ = document.getElementById('fltNombreMSJ');
const countsBar = document.getElementById('countsBar');
const rows = Array.from(document.querySelectorAll('.tickets-table tbody tr'));
const tokenNombreMSJ = <?= json_encode($nombreMSJ) ?> || '';

// Variables para el rango de fechas
let fechaInicio = null;
let fechaFin = null;

// Build projects list
const proyectosSet = new Map();
const nombresMSJSet = new Set();

rows.forEach(r => {
    const pid = r.getAttribute('data-proyecto');
    const nombreCelda = r.children[2]?.textContent.trim();
    const nombreMSJ = r.getAttribute('data-nombremsj');
    
    // Proyectos
    if(pid && pid !== '0' && nombreCelda && nombreCelda !== '-') {
        if(!proyectosSet.has(pid)) proyectosSet.set(pid, nombreCelda);
    }
    
    // Nombres MSJ
    if(nombreMSJ && nombreMSJ.trim() !== '') {
        nombresMSJSet.add(nombreMSJ.trim());
    }
});

// Populate projects select
proyectosSet.forEach((nombre, pid) => {
    const opt = document.createElement('option');
    opt.value = pid; 
    opt.textContent = nombre; 
    fltProyecto.appendChild(opt);
});

// Populate nombres MSJ select
Array.from(nombresMSJSet).sort().forEach(nombre => {
    const opt = document.createElement('option');
    opt.value = nombre; 
    opt.textContent = nombre; 
    fltNombreMSJ.appendChild(opt);
});

// Helper function for date range filtering
function isInDateRange(fechaStr) {
    if (!fechaStr || fechaStr === '0000-00-00 00:00:00') return false;
    if (!fechaInicio || !fechaFin) return true;
    
    const fecha = new Date(fechaStr);
    const inicio = new Date(fechaInicio);
    const fin = new Date(fechaFin);
    
    // Ajustar las fechas para incluir todo el día
    inicio.setHours(0, 0, 0, 0);
    fin.setHours(23, 59, 59, 999);
    
    return fecha >= inicio && fecha <= fin;
}

// Lock project select if token has fixed project
const tokenProyecto = <?= $idProyecto>0 ? (int)$idProyecto : 0 ?>;
if(tokenProyecto > 0) {
    fltProyecto.value = String(tokenProyecto);
    fltProyecto.disabled = true;
}

// Apply filters function
function aplicarFiltros() {
    const projVal = fltProyecto.value;
    const estVal = fltEstado.value;
    const prioVal = fltPrioridad.value;
    const nombreMSJVal = fltNombreMSJ.value;
    const searchVal = searchInput.value.toLowerCase().trim();
    const msjTokenVal = tokenNombreMSJ ? tokenNombreMSJ.toLowerCase() : '';
    
    let cPend = 0, cProc = 0, cFin = 0, cAlta = 0, cMedia = 0, cBaja = 0;
    let total = 0, visibles = 0;
    
    rows.forEach(r => {
        const estado = r.getAttribute('data-estado');
        const pid = r.getAttribute('data-proyecto');
        const prioridad = r.getAttribute('data-prioridad');
        const fecha = r.getAttribute('data-fecha');
        const nombreMSJ = r.getAttribute('data-nombremsj') || '';
        const nmsj = nombreMSJ.toLowerCase();
        const titulo = r.children[1]?.textContent.toLowerCase() || '';
        const descripcion = r.children[7]?.textContent.toLowerCase() || '';
        
        total++;
        
        let show = true;
        
        // Filtros
        if(projVal && pid !== projVal) show = false;
        if(estVal && estado !== estVal) show = false;
        if(prioVal && prioridad !== prioVal) show = false;
        if(nombreMSJVal && nombreMSJ !== nombreMSJVal) show = false;
        if(!isInDateRange(fecha)) show = false;
        if(searchVal && !titulo.includes(searchVal) && !descripcion.includes(searchVal)) show = false;
        if(msjTokenVal && !nmsj.includes(msjTokenVal)) show = false;
        
        if(show) {
            r.style.display = '';
            visibles++;
            
            // Contadores por estado
            if(estado === 'Pendiente') cPend++; 
            else if(estado === 'En Proceso') cProc++; 
            else if(estado === 'Finalizado') cFin++;
            
            // Contadores por prioridad
            if(prioridad === 'Alta') cAlta++;
            else if(prioridad === 'Media') cMedia++;
            else if(prioridad === 'Baja') cBaja++;
            
        } else {
            r.style.display = 'none';
        }
    });
    
    // Actualizar estadísticas
    countsBar.innerHTML = `
        <div class="stat-item">
            <i class="fas fa-eye"></i>
            <span>Visibles:</span>
            <span class="stat-value">${visibles}</span>
        </div>
        <div class="stat-item">
            <i class="fas fa-clock"></i>
            <span>Pendientes:</span>
            <span class="stat-value">${cPend}</span>
        </div>
        <div class="stat-item">
            <i class="fas fa-cog"></i>
            <span>En Proceso:</span>
            <span class="stat-value">${cProc}</span>
        </div>
        <div class="stat-item">
            <i class="fas fa-check-circle"></i>
            <span>Finalizados:</span>
            <span class="stat-value">${cFin}</span>
        </div>
        <div class="stat-item">
            <i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i>
            <span>Prioridad Alta:</span>
            <span class="stat-value">${cAlta}</span>
        </div>
        <div class="stat-item">
            <i class="fas fa-balance-scale" style="color: #f59e0b;"></i>
            <span>Prioridad Media:</span>
            <span class="stat-value">${cMedia}</span>
        </div>
        <div class="stat-item">
            <i class="fas fa-arrow-down" style="color: #10b981;"></i>
            <span>Prioridad Baja:</span>
            <span class="stat-value">${cBaja}</span>
        </div>
    `;
}

// Event listeners
searchInput.addEventListener('input', aplicarFiltros);
fltProyecto.addEventListener('change', aplicarFiltros);
fltEstado.addEventListener('change', aplicarFiltros);
fltPrioridad.addEventListener('change', aplicarFiltros);
fltNombreMSJ.addEventListener('change', aplicarFiltros);

// Configurar calendario de rango de fechas
document.addEventListener('DOMContentLoaded', function() {
    if (typeof flatpickr !== 'undefined') {
        flatpickr(fltFechaRango, {
            mode: 'range',
            dateFormat: 'd/m/Y',
            locale: {
                firstDayOfWeek: 1,
                weekdays: {
                    shorthand: ['Dom', 'Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab'],
                    longhand: ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado']
                },
                months: {
                    shorthand: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
                    longhand: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']
                },
                rangeSeparator: ' a ',
                weekAbbreviation: 'Sem',
                scrollTitle: 'Desplázate para incrementar',
                toggleTitle: 'Haz clic para alternar'
            },
            onChange: function(selectedDates) {
                if (selectedDates.length === 2) {
                    fechaInicio = selectedDates[0];
                    fechaFin = selectedDates[1];
                } else if (selectedDates.length === 1) {
                    fechaInicio = selectedDates[0];
                    fechaFin = selectedDates[0];
                } else {
                    fechaInicio = null;
                    fechaFin = null;
                }
                aplicarFiltros();
            },
            onClear: function() {
                fechaInicio = null;
                fechaFin = null;
                aplicarFiltros();
            }
        });
    }
});

aplicarFiltros();

// Description formatting and expand/collapse
function normalizeRawText(txt){
    if(!txt) return '';
    try { txt = txt.replace(/\\r\\n/g,'\n').replace(/\\n/g,'\n').replace(/\\t/g,'\t'); } catch(e){}
    if((txt.startsWith('"') && txt.endsWith('"')) || (txt.startsWith("'") && txt.endsWith("'"))){
        txt = txt.substring(1, txt.length-1);
    }
    txt = txt.replace(/\\u00([0-9a-fA-F]{2})/g,(m,g1)=>{
        try { return decodeURIComponent('%'+g1); } catch(e){ return m; }
    });
    txt = txt.replace(/\\"/g,'"');
    return txt.trim();
}

function buildHtmlFromText(raw){
    const out = [];
    const norm = normalizeRawText(raw);
    if(!norm) return '';
    const lines = norm.split(/\n+/).map(l=>l.trim()).filter(l=>l.length);
    if(!lines.length) return '';
    const kvRegex = /^([A-Za-zÁÉÍÓÚÑáéíóú0-9_\-\.\s]{2,40})\s*[:：]\s*(.+)$/;
    let i=0;
    while(i<lines.length){
        const line = lines[i];
        if(/^[-*•]\s+/.test(line)){
            const listItems=[];
            while(i<lines.length && /^[-*•]\s+/.test(lines[i])){ listItems.push(lines[i].replace(/^[-*•]\s+/,'').trim()); i++; }
            out.push('<ul>'+listItems.map(li=>'<li>'+escapeHtml(li)+'</li>').join('')+'</ul>');
            continue;
        }
        let m = line.match(kvRegex);
        if(m){
            out.push('<div class="kv"><b>'+escapeHtml(capitalize(m[1].trim()))+':</b> '+escapeHtml(m[2].trim())+'</div>');
            i++; continue;
        }
        const hexOnly = line.match(/^(#[0-9a-fA-F]{3,8})$/);
        if(hexOnly){
            const h = hexOnly[1].toUpperCase();
            out.push('<span class="color-badge" style="background:'+h+'33;color:'+h+';">'+h+'</span>');
            i++; continue;
        }
        out.push('<p>'+escapeHtml(line)+'</p>');
        i++;
    }
    return out.join('');
}

function escapeHtml(s){
    return s.replace(/[&<>"']/g, c=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]||c));
}
function capitalize(str){ return str.charAt(0).toUpperCase()+str.slice(1); }

function enhanceDescriptions(){
    document.querySelectorAll('.desc').forEach(descEl=>{
        const raw = descEl.getAttribute('data-raw') || descEl.textContent || '';
        const html = buildHtmlFromText(raw);
        if(html){
            descEl.innerHTML = html;
            descEl.style.whiteSpace='normal';
        }
        const wrapper = descEl.closest('.desc-wrapper');
        const toggle = wrapper.querySelector('.desc-toggle');
        if(descEl.scrollHeight > 150){
            descEl.classList.add('desc-collapsed');
            toggle.style.display='inline-block';
            toggle.addEventListener('click',()=>{
                const collapsed = descEl.classList.toggle('desc-collapsed');
                toggle.textContent = collapsed? 'Ver más':'Ver menos';
            });
        }
    });
}

// Initialize on load
document.addEventListener('DOMContentLoaded', () => {
    enhanceDescriptions();
});

// --- Normalización de rutas de medios (descripciones) ---
function normalizeMediaPath(p){
    if(!p) return p;
    if(/^https?:\/\//i.test(p) || p.startsWith('data:') || p.startsWith('blob:')) return p;
    if(p.startsWith('/')) return p;
    if(p.startsWith('solicitudes/')) return '/' + p;
    if(p.startsWith('uploads/')) return '/solicitudes/' + p;
    return '/solicitudes/uploads/solicitudes/' + p.replace(/^\/+/, '');
}

// --- Notas (carga y modal) ---
const notesModal = document.getElementById('notesModal');
const notesModalClose = document.getElementById('notesModalClose');
const notesModalOk = document.getElementById('notesModalOk');
const notesList = document.getElementById('notesList');
const notesLoading = document.getElementById('notesLoading');
const notesModalTicket = document.getElementById('notesModalTicket');

function openNotesModal(ticketId){
    notesModalTicket.textContent = '#' + ticketId;
    notesList.innerHTML='';
    notesLoading.style.display='block';
    notesModal.classList.add('active');
    document.body.style.overflow='hidden';
    fetch('tickets_public_notas.php?token=<?= urlencode($token) ?>&id='+encodeURIComponent(ticketId))
        .then(r=>r.json())
        .then(data=>{
            notesLoading.style.display='none';
            if(!data.success){
                notesList.innerHTML = '<div style="font-size:.7rem;opacity:.7;">'+(data.message?escapeHtml(data.message):'No disponible')+'</div>';
                return;
            }
            if(!data.notes.length){
                notesList.innerHTML = '<div style="font-size:.7rem;opacity:.7;">No hay notas registradas</div>';
                return;
            }
            notesList.innerHTML = data.notes.map(n=>{
                const imgs = (n.images||[]).map(src=>'<img src="'+escapeHtml(src)+'" alt="img" />').join('');
                return `<div class="note-item">\n<div class="note-header"><span class="note-author">${escapeHtml(n.autor||'')}</span><span class="note-date">${escapeHtml(n.fecha||'')}</span></div>\n<div class="note-text">${escapeHtml(n.texto||'')}</div>${imgs?`<div class="note-images">${imgs}</div>`:''}</div>`;
            }).join('');
            // Click imagen -> lightbox reutilizado
            notesList.querySelectorAll('.note-images img').forEach(img=>{
                img.addEventListener('click',()=>openLightbox(img.getAttribute('src')));
            });
        })
        .catch(err=>{
            notesLoading.style.display='none';
            notesList.innerHTML='<div style="font-size:.7rem;color:#ef4444;">Error al cargar</div>';
        });
}

function closeNotesModal(){
    notesModal.classList.remove('active');
    document.body.style.overflow='auto';
}
notesModalClose.addEventListener('click', closeNotesModal);
notesModalOk.addEventListener('click', closeNotesModal);
notesModal.addEventListener('click', e=>{ if(e.target===notesModal) closeNotesModal(); });

document.querySelectorAll('.notes-btn').forEach(btn=>{
    btn.addEventListener('click', ()=> openNotesModal(btn.getAttribute('data-ticket')));
});
</script>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>

</body>
</html>