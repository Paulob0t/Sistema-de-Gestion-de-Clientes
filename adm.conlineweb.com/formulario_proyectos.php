<?php
$is_iframe = !empty($_GET['iframe']);
if (!$is_iframe) include 'menu.php';
include 'conn.php';
require_once __DIR__ . '/includes/helpers_clientes.php';
require_once __DIR__ . '/includes/adm_proyecto_preview_helpers.php';
require_once __DIR__ . '/includes/helpers_proyectos_tipo.php';
require_once __DIR__ . '/includes/helpers_proyectos_eeat.php';

$clientesActivos = solicitudes_clientes_activos($conn);
adm_proyectos_ensure_tipo_proyecto($conn);
adm_proyectos_ensure_eeat_columns($conn);

// Obtener proyectos para la tabla (solo si no es iframe)
$has_descripcion_tecnica = false;
try {
    $chkDescTec = $conn->query("SHOW COLUMNS FROM proyectos LIKE 'descripcion_tecnica'");
    if ($chkDescTec && $chkDescTec->num_rows > 0) {
        $has_descripcion_tecnica = true;
    }
} catch (Throwable $e) {}

if (!$is_iframe) {
    $campo_desc_tecnica = $has_descripcion_tecnica ? 'p.descripcion_tecnica' : 'NULL AS descripcion_tecnica';
    $sql_proyectos = "SELECT p.id_proyecto, p.id_cliente, p.nombre_proyecto, p.tipo_proyecto, p.url, p.descripcion, {$campo_desc_tecnica}, p.fecha_creacion, p.mostrar, p.posicion, p.activo, p.bloqueado, p.industria, p.ciudad, p.alias_publico, p.resultado, p.caso_destacado, c.nombre_contacto, c.correo, c.empresa
                      FROM proyectos p
                      LEFT JOIN clientes c ON c.id = p.id_cliente
                      ORDER BY p.posicion ASC, p.id_proyecto DESC";
    $result_proyectos = $conn->query($sql_proyectos);
}

// Preselección opcional de cliente por GET
$id_cliente_default = isset($_GET['id_cliente']) ? (int) $_GET['id_cliente'] : 0;
if ($id_cliente_default > 0 && !solicitudes_cliente_es_activo($conn, $id_cliente_default)) {
    $id_cliente_default = 0;
}

// Cargar tags y agrupar por subtitulo (titulo_tags tiene id 1 = Categorías)
$sql_tags = "SELECT t.id, t.id_titulo, t.subtitulo, t.tags FROM tags t ORDER BY t.id_titulo, t.id";
$result_tags = $conn->query($sql_tags);
$tags_by_group = [];
if ($result_tags && $result_tags->num_rows > 0) {
    while ($r = $result_tags->fetch_assoc()) {
        $group_id = (int)$r['id_titulo'];
        $tags_json = $r['tags'];
        $items = json_decode($tags_json, true);
        if (!is_array($items)) $items = [];
        $tags_by_group[$group_id][] = [
            'id' => (int)$r['id'],
            'subtitulo' => $r['subtitulo'],
            'items' => $items
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if ($is_iframe): require_once __DIR__ . '/includes/adm_head_meta.php'; ?>
    <title><?= htmlspecialchars(adm_document_title('Registro de proyecto'), ENT_QUOTES, 'UTF-8') ?></title>
    <?= adm_favicon_markup() ?>
    <?php endif; ?>

    <!-- Styles modernos -->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="css/admin-platform.css" rel="stylesheet">
    <link href="vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
    <?php adm_proyecto_tipo_styles(); ?>
    
    <style>
        /* ===== ESTILOS MODERNOS (igual que antes) ===== */
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
        
        .card-modern {
            background: white;
            border-radius: 24px;
            border: none;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .card-modern:hover {
            box-shadow: 0 12px 24px -12px rgba(0, 1, 71, 0.15);
        }
        
        .modern-table {
            border-radius: 24px;
            overflow-x: auto;
            border: 1px solid var(--border);
            background: white;
        }
        
        .modern-table table {
            width: 100%;
            min-width: 1200px;
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
        
        .badge-modern {
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.2px;
            display: inline-block;
        }
        
        .badge-success-modern {
            background: #dcfce7;
            color: #15803d;
        }
        
        .badge-secondary-modern {
            background: #f1f5f9;
            color: #475569;
        }
        
        .badge-danger-modern {
            background: #fee2e2;
            color: #b91c1c;
        }
        
        .badge-warning-modern {
            background: #fef3c7;
            color: #b45309;
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
        
        .btn-primary-custom {
            background: var(--primary-dark);
            color: white;
            border-radius: 10px;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s;
            border: none;
        }
        
        .btn-primary-custom:hover {
            background: var(--primary);
            transform: translateY(-1px);
        }
        
        .btn-success-modern {
            background: var(--success);
            color: white;
            border-radius: 10px;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s;
            border: none;
        }
        
        .btn-success-modern:hover {
            background: #059669;
            transform: translateY(-1px);
        }
        
        .btn-outline-modern {
            background: transparent;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s;
            color: var(--text-secondary);
        }
        
        .btn-outline-modern:hover {
            background: var(--primary-soft);
            border-color: var(--primary-light);
            color: var(--primary-dark);
        }

        .btn-danger-modern {
            background: #dc2626;
            color: white;
            border-radius: 10px;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s;
            border: none;
        }

        .btn-danger-modern:hover {
            background: #b91c1c;
            color: white;
            transform: translateY(-1px);
        }
        
        .filter-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 40px;
            padding: 6px 16px;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s;
            user-select: none;
        }
        .filter-pill i { font-size: 12px; }
        .filter-pill.active {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            color: white;
        }
        .filter-pill.active i { color: white; }
        .filter-pill:hover:not(.active) {
            background: var(--primary-soft);
            border-color: var(--primary-light);
        }

        .proy-tipo-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1.15rem;
            padding: 0.45rem;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }
        .proy-tipo-tab {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            border: 1px solid transparent;
            background: transparent;
            color: var(--text-secondary);
            font-weight: 650;
            font-size: 0.8125rem;
            line-height: 1.2;
            padding: 0.65rem 0.95rem;
            border-radius: 12px;
            cursor: pointer;
            transition: background 0.15s, color 0.15s, border-color 0.15s, box-shadow 0.15s;
            white-space: nowrap;
        }
        .proy-tipo-tab i {
            font-size: 0.85rem;
            opacity: 0.85;
        }
        .proy-tipo-tab .proy-tipo-tab__count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 1.5rem;
            height: 1.35rem;
            padding: 0 0.4rem;
            border-radius: 999px;
            background: #e2e8f0;
            color: #475569;
            font-size: 0.72rem;
            font-weight: 750;
        }
        .proy-tipo-tab:hover:not(.active) {
            background: var(--primary-soft);
            color: var(--primary-dark);
        }
        .proy-tipo-tab.active {
            background: var(--primary-dark);
            color: #fff;
            border-color: var(--primary-dark);
            box-shadow: 0 6px 16px rgba(0, 1, 71, 0.18);
        }
        .proy-tipo-tab.active .proy-tipo-tab__count {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
        }
        @media (max-width: 767.98px) {
            .proy-tipo-tabs {
                flex-wrap: nowrap;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
        }

        .filter-shell {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 1.15rem 1.25rem 1.05rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }
        .filter-shell__top {
            display: grid;
            grid-template-columns: minmax(0, 1.4fr) minmax(220px, 0.9fr) auto;
            gap: 0.85rem;
            align-items: end;
            margin-bottom: 1rem;
        }
        .filter-field label {
            display: block;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 0.4rem;
        }
        .filter-search {
            position: relative;
        }
        .filter-search i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            pointer-events: none;
        }
        .filter-search input {
            width: 100%;
            height: 44px;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 0 14px 0 40px;
            font-size: 0.92rem;
            background: #f8fafc;
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
        }
        .filter-search input:focus {
            outline: none;
            background: #fff;
            border-color: #a5b4fc;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        }
        .filter-shell .custom-select,
        .filter-shell select {
            height: 44px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: #f8fafc;
            font-size: 0.9rem;
            padding: 0 12px;
        }
        .filter-shell .custom-select:focus,
        .filter-shell select:focus {
            outline: none;
            border-color: #a5b4fc;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
            background: #fff;
        }
        .btn-clear-filters {
            height: 44px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: #fff;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.85rem;
            padding: 0 16px;
            white-space: nowrap;
        }
        .btn-clear-filters:hover {
            background: #fef2f2;
            border-color: #fecaca;
            color: #b91c1c;
        }
        .filter-shell__groups {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.85rem;
        }
        .filter-block {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 0.75rem 0.85rem;
        }
        .filter-block__title {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 0.55rem;
        }
        .filter-block .filter-group {
            gap: 6px;
        }
        .filter-shell__footer {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-top: 0.95rem;
            padding-top: 0.85rem;
            border-top: 1px solid var(--border);
        }
        .filter-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
        }
        .stat-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0.7rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
            background: #f1f5f9;
            color: #334155;
        }
        .stat-chip strong { font-weight: 800; }
        .stat-chip--ok { background: #dcfce7; color: #166534; }
        .stat-chip--warn { background: #fef3c7; color: #92400e; }
        .stat-chip--danger { background: #fee2e2; color: #991b1b; }
        .active-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            align-items: center;
            min-height: 28px;
        }
        .active-filter-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.25rem 0.55rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            background: var(--primary-soft);
            color: var(--primary-dark);
        }
        .url-cell {
            display: block;
            max-width: 280px;
            font-size: 0.8rem;
            line-height: 1.35;
            word-break: break-all;
            color: #1d4ed8;
            text-decoration: none;
        }
        .url-cell:hover { text-decoration: underline; color: #1e40af; }
        .btn-group-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
        }
        .btn-group-actions .btnVistaPreviaProyecto,
        .btn-group-actions .btn-outline-modern,
        .btn-group-actions .btnToggleActivo,
        .btn-group-actions .btnTogglePublicado,
        .btn-group-actions .btnToggleBloqueado,
        .btn-group-actions .btnEliminarProyecto,
        .btn-group-actions .btn-success-modern,
        .btn-group-actions .btn-danger-modern {
            min-width: 36px;
            padding: 7px 10px;
        }
        .btn-group-actions .btn-label { display: none; }
        .page-header-bar {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }
        .page-header-bar h1 {
            font-size: clamp(1.55rem, 2.2vw, 1.9rem);
            margin: 0 0 0.25rem;
            color: var(--primary-dark);
            font-weight: 800;
            letter-spacing: -0.02em;
        }
        .page-header-bar p {
            margin: 0;
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.92rem;
        }
        .modern-table {
            border-radius: 18px;
            overflow-x: auto;
            border: 1px solid var(--border);
            background: white;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }
        .modern-table table { min-width: 1280px; }
        .modern-table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #f8fafc;
            white-space: nowrap;
        }
        .dataTables_wrapper .dataTables_filter { display: none; }
        .dataTables_wrapper .row { align-items: center; padding: 0.65rem 0.85rem; }
        @media (max-width: 1100px) {
            .filter-shell__top { grid-template-columns: 1fr; }
            .filter-shell__groups { grid-template-columns: 1fr; }
        }
        
        .filter-group {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
        
        .filter-label {
            font-weight: 600;
            font-size: 13px;
            color: var(--text-muted);
            margin-right: 4px;
        }
        
        .results-counter {
            font-size: 13px;
            background: var(--primary-soft);
            padding: 4px 12px;
            border-radius: 30px;
            color: var(--primary-dark);
            font-weight: 500;
        }
        
        .tags-container {
            max-height: 280px;
            overflow: auto;
            background: #fafbff;
            border-radius: 16px;
            padding: 16px;
        }
        
        .form-check-inline {
            margin-right: 1rem;
            margin-bottom: 0.5rem;
        }
        
        .text-truncate-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            max-height: 3em;
            line-height: 1.5em;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in-up {
            animation: fadeInUp 0.5s ease-out;
        }
        
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
        
        .modal-content {
            border-radius: 24px;
            border: none;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
        }
        
        .modal-header {
            border-bottom: 1px solid var(--border);
            background: #fafbff;
            border-radius: 24px 24px 0 0;
            padding: 1.25rem 1.5rem;
        }
        
        .modal-header .modal-title {
            font-weight: 700;
            color: var(--primary-dark);
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .form-control, .custom-select {
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 0.6rem 1rem;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .form-control:focus, .custom-select:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(0,1,71,0.1);
        }
        
        .required-field::after {
            content: " *";
            color: var(--danger);
        }
        
        legend {
            font-size: 1rem;
            font-weight: 600;
            color: var(--primary-dark);
        }
        
        .btn-group-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .text-link {
            color: var(--primary-dark);
            text-decoration: none;
            transition: color 0.2s;
        }
        
        .text-link:hover {
            color: var(--primary);
            text-decoration: underline;
        }
        
        /* Estilo para lista de posiciones ocupadas */
        .posiciones-lista {
            background: #f8fafc;
            border-radius: 12px;
            padding: 12px;
            margin-top: 15px;
        }
        .posicion-item {
            display: inline-block;
            background: white;
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 4px 12px;
            margin: 4px;
            font-size: 13px;
        }
        .posicion-item.actual {
            background: var(--primary-soft);
            border-color: var(--primary-light);
            color: var(--primary-dark);
            font-weight: 600;
        }
    </style>
    
    <?php if ($is_iframe): ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php endif; ?>
</head>
<body class="bg-light">

<div id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <div class="container-xl my-4 py-4 legacy-touch" style="max-width: 96% !important;">
            
            <?php if (!$is_iframe): ?>
            <?php
            $stats_total = 0; $stats_activos = 0; $stats_publicados = 0; $stats_privados = 0;
            $stats_por_tipo = [0 => 0, 1 => 0, 2 => 0, 3 => 0];
            if (isset($result_proyectos) && $result_proyectos && $result_proyectos->num_rows > 0) {
                $result_proyectos->data_seek(0);
                while ($st = $result_proyectos->fetch_assoc()) {
                    $stats_total++;
                    if (!empty($st['activo'])) $stats_activos++;
                    if (!empty($st['mostrar'])) $stats_publicados++;
                    if (!empty($st['bloqueado'])) $stats_privados++;
                    $tipoSt = adm_proyecto_tipo_normalize($st['tipo_proyecto'] ?? 0);
                    $stats_por_tipo[$tipoSt] = ($stats_por_tipo[$tipoSt] ?? 0) + 1;
                }
                $result_proyectos->data_seek(0);
            }
            $tipo_tab_icons = [
                0 => 'fa-globe',
                1 => 'fa-code',
                2 => 'fa-bullhorn',
                3 => 'fa-ellipsis-h',
            ];
            ?>
            <!-- Header -->
            <div class="page-header-bar fade-in-up">
                <div>
                    <h1>Proyectos</h1>
                    <p>Busca, filtra y gestiona el portafolio en un solo lugar</p>
                </div>
                <button id="btnNuevoProyecto" class="btn-primary-custom" style="padding: 12px 22px; font-size: 0.9rem;">
                    <i class="fas fa-plus mr-2"></i>Nuevo proyecto
                </button>
            </div>

            <!-- Tabs por tipo de proyecto -->
            <div class="proy-tipo-tabs fade-in-up" id="proyTipoTabs" role="tablist" aria-label="Filtrar por tipo de proyecto">
                <button type="button" class="proy-tipo-tab active" data-filter="tipo" data-value="todos" role="tab" aria-selected="true">
                    <i class="fas fa-layer-group"></i>
                    <span>Todos</span>
                    <span class="proy-tipo-tab__count"><?php echo (int) $stats_total; ?></span>
                </button>
                <?php foreach (adm_proyecto_tipos_map() as $tipoId => $tipoLabel): ?>
                <button type="button" class="proy-tipo-tab" data-filter="tipo" data-value="<?php echo (int) $tipoId; ?>" role="tab" aria-selected="false">
                    <i class="fas <?php echo htmlspecialchars($tipo_tab_icons[(int) $tipoId] ?? 'fa-folder', ENT_QUOTES, 'UTF-8'); ?>"></i>
                    <span><?php echo htmlspecialchars($tipoLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="proy-tipo-tab__count"><?php echo (int) ($stats_por_tipo[(int) $tipoId] ?? 0); ?></span>
                </button>
                <?php endforeach; ?>
            </div>

            <!-- Filtros intuitivos -->
            <div class="filter-shell fade-in-up">
                <div class="filter-shell__top">
                    <div class="filter-field">
                        <label for="filtroBusqueda">Buscar</label>
                        <div class="filter-search">
                            <i class="fas fa-search"></i>
                            <input type="search" id="filtroBusqueda" placeholder="Empresa, cliente, proyecto o URL…" autocomplete="off">
                        </div>
                    </div>
                    <div class="filter-field">
                        <label for="filtroEmpresa">Empresa</label>
                        <select id="filtroEmpresa" class="custom-select w-100">
                            <option value="">Todas las empresas</option>
                            <?php
                            $sql_empresas = "SELECT DISTINCT c.id, c.empresa
                                             FROM clientes c
                                             INNER JOIN login l ON l.id = c.id
                                             INNER JOIN proyectos p ON p.id_cliente = c.id
                                             WHERE l.id_tipo_usuario = 0
                                               AND c.eliminado = 0
                                               AND c.empresa IS NOT NULL AND c.empresa != ''
                                             ORDER BY c.empresa ASC";
                            $result_empresas = $conn->query($sql_empresas);
                            if ($result_empresas && $result_empresas->num_rows > 0) {
                                while ($emp = $result_empresas->fetch_assoc()) {
                                    echo '<option value="'.htmlspecialchars($emp['empresa']).'">'.htmlspecialchars($emp['empresa']).'</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="filter-field">
                        <label>&nbsp;</label>
                        <button type="button" id="btnLimpiarFiltros" class="btn-clear-filters w-100">
                            <i class="fas fa-times mr-1"></i> Limpiar filtros
                        </button>
                    </div>
                </div>

                <div class="filter-shell__groups">
                    <div class="filter-block">
                        <div class="filter-block__title"><i class="fas fa-power-off"></i> Estado</div>
                        <div class="filter-group">
                            <div class="filter-pill active" data-filter="estado" data-value="todos">Todos</div>
                            <div class="filter-pill" data-filter="estado" data-value="activo"><i class="fas fa-check-circle"></i> Activo</div>
                            <div class="filter-pill" data-filter="estado" data-value="inactivo"><i class="fas fa-pause-circle"></i> Inactivo</div>
                        </div>
                    </div>
                    <div class="filter-block">
                        <div class="filter-block__title"><i class="fas fa-globe"></i> Publicado</div>
                        <div class="filter-group">
                            <div class="filter-pill active" data-filter="publicado" data-value="todos">Todos</div>
                            <div class="filter-pill" data-filter="publicado" data-value="si"><i class="fas fa-eye"></i> Sí</div>
                            <div class="filter-pill" data-filter="publicado" data-value="no"><i class="fas fa-eye-slash"></i> No</div>
                        </div>
                    </div>
                    <div class="filter-block">
                        <div class="filter-block__title"><i class="fas fa-lock"></i> Visibilidad</div>
                        <div class="filter-group">
                            <div class="filter-pill active" data-filter="privado" data-value="todos">Todos</div>
                            <div class="filter-pill" data-filter="privado" data-value="no"><i class="fas fa-unlock"></i> Público</div>
                            <div class="filter-pill" data-filter="privado" data-value="si"><i class="fas fa-lock"></i> Privado</div>
                        </div>
                    </div>
                </div>

                <div class="filter-shell__footer">
                    <div class="filter-stats">
                        <span class="stat-chip"><strong><?php echo (int)$stats_total; ?></strong> total</span>
                        <span class="stat-chip stat-chip--ok"><strong><?php echo (int)$stats_activos; ?></strong> activos</span>
                        <span class="stat-chip"><strong><?php echo (int)$stats_publicados; ?></strong> publicados</span>
                        <span class="stat-chip stat-chip--warn"><strong><?php echo (int)$stats_privados; ?></strong> privados</span>
                        <span class="results-counter" id="resultadosCounter">Mostrando <span id="visibleCount">0</span></span>
                    </div>
                    <div class="active-filters" id="activeFilters"></div>
                </div>
            </div>

            <!-- Tabla de proyectos moderna -->
            <div class="modern-table fade-in-up">
                <table class="table" id="tablaProyectos" width="100%" cellspacing="0">
                    <thead>
                                <tr>
                            <th><i class="fas fa-hashtag"></i> ID</th>
                            <th><i class="fas fa-sort-numeric-down"></i> Posición</th>
                            <th><i class="fas fa-building"></i> Empresa</th>
                            <th><i class="fas fa-user"></i> Cliente</th>
                            <th><i class="fas fa-project-diagram"></i> Proyecto</th>
                            <th><i class="fas fa-layer-group"></i> Tipo</th>
                            <th><i class="fas fa-award"></i> Caso</th>
                            <th><i class="fas fa-desktop"></i> Vista previa</th>
                            <th><i class="fas fa-link"></i> URL</th>
                            <th><i class="fas fa-power-off"></i> Estado</th>
                            <th><i class="fas fa-globe"></i> Publicado</th>
                            <th><i class="fas fa-lock"></i> Privado</th>
                            <th><i class="fas fa-cog"></i> Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (isset($result_proyectos) && $result_proyectos && $result_proyectos->num_rows > 0): ?>
                            <?php while ($row = $result_proyectos->fetch_assoc()): ?>
                            <?php
                                $is_activo = ((int)($row['activo'] ?? 1) === 1);
                                $is_publicado = ((int)($row['mostrar'] ?? 0) === 1);
                                $is_bloqueado = ((int)($row['bloqueado'] ?? 0) === 1);
                                $url_raw = trim((string)($row['url'] ?? ''));
                                $tipo_proy = adm_proyecto_tipo_normalize($row['tipo_proyecto'] ?? 0);
                            ?>
                            <tr data-empresa="<?php echo htmlspecialchars($row['empresa'] ?? ''); ?>"
                                data-estado="<?php echo $is_activo ? 'activo' : 'inactivo'; ?>"
                                data-publicado="<?php echo $is_publicado ? 'si' : 'no'; ?>"
                                data-privado="<?php echo $is_bloqueado ? 'si' : 'no'; ?>"
                                data-tipo="<?php echo (int) $tipo_proy; ?>"
                                data-posicion="<?php echo (int)$row['posicion']; ?>">
                                <td><span class="badge-id">#<?php echo (int)$row['id_proyecto']; ?></span></td>
                                <td>
                                    <span class="badge-modern" style="background: var(--primary-soft); color: var(--primary-dark);" id="posicion-span-<?php echo $row['id_proyecto']; ?>">
                                        <?php echo !empty($row['posicion']) ? (int)$row['posicion'] : '—'; ?>
                                    </span>
                                </td>
                                <td class="fw-semibold" style="color: var(--primary-dark);">
                                    <?php echo htmlspecialchars($row['empresa'] ?? '—'); ?>
                                </td>
                                <td>
                                    <a href="detalle_cliente.php?id=<?php echo (int)$row['id_cliente']; ?>" class="text-link">
                                        <?php echo htmlspecialchars(($row['nombre_contacto'] ?: 'Cliente #'.$row['id_cliente'])); ?>
                                    </a>
                                    <div class="small text-muted"><?php echo htmlspecialchars($row['correo'] ?? ''); ?></div>
                                </td>
                                <td class="fw-semibold"><?php echo htmlspecialchars($row['nombre_proyecto']); ?></td>
                                <td><?php echo adm_proyecto_tipo_select_cell((int) $row['id_proyecto'], $tipo_proy); ?></td>
                                <td style="min-width:140px; max-width:220px;">
                                    <?php
                                    $esCaso = ((int) ($row['caso_destacado'] ?? 0) === 1);
                                    $industriaRow = trim((string) ($row['industria'] ?? ''));
                                    $resultadoRow = trim((string) ($row['resultado'] ?? ''));
                                    ?>
                                    <?php if ($esCaso): ?>
                                        <span class="badge-modern badge-success-modern mb-1 d-inline-block"><i class="fas fa-award mr-1"></i>Destacado</span>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                    <?php if ($industriaRow !== ''): ?>
                                        <div class="small text-muted"><?php echo htmlspecialchars($industriaRow); ?></div>
                                    <?php endif; ?>
                                    <?php if ($resultadoRow !== ''): ?>
                                        <div class="small" style="color:var(--primary-dark);"><?php echo htmlspecialchars(mb_substr($resultadoRow, 0, 80)); ?><?php echo mb_strlen($resultadoRow) > 80 ? '…' : ''; ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo adm_proyecto_preview_cell($url_raw, (string) ($row['nombre_proyecto'] ?? '')); ?></td>
                                <td style="min-width:200px; max-width:300px;">
                                    <?php if ($url_raw !== ''): ?>
                                        <a href="<?php echo htmlspecialchars($url_raw); ?>" target="_blank" rel="noopener" class="url-cell" title="<?php echo htmlspecialchars($url_raw); ?>">
                                            <?php echo htmlspecialchars($url_raw); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-modern <?php echo $is_activo ? 'badge-success-modern' : 'badge-secondary-modern'; ?>">
                                        <i class="fas <?php echo $is_activo ? 'fa-check-circle' : 'fa-times-circle'; ?> mr-1"></i>
                                        <?php echo $is_activo ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-modern <?php echo $is_publicado ? 'badge-success-modern' : 'badge-secondary-modern'; ?>" id="publicado-badge-<?php echo $row['id_proyecto']; ?>">
                                        <i class="fas <?php echo $is_publicado ? 'fa-check-circle' : 'fa-times-circle'; ?> mr-1"></i>
                                        <?php echo $is_publicado ? 'Publicado' : 'No publicado'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-modern <?php echo $is_bloqueado ? 'badge-danger-modern' : 'badge-success-modern'; ?>" id="bloqueado-badge-<?php echo $row['id_proyecto']; ?>">
                                        <i class="fas <?php echo $is_bloqueado ? 'fa-lock' : 'fa-lock-open'; ?> mr-1"></i>
                                        <?php echo $is_bloqueado ? 'Privado' : 'Público'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group-actions">
                                        <button type="button" class="btn-outline-modern btnVistaPreviaProyecto"
                                            data-id="<?php echo (int)$row['id_proyecto']; ?>"
                                            data-url="<?php echo htmlspecialchars($url_raw, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-nombre="<?php echo htmlspecialchars($row['nombre_proyecto'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            title="Vista previa"
                                            <?php echo $url_raw === '' ? 'disabled' : ''; ?>>
                                            <i class="fas fa-desktop"></i>
                                        </button>
                                        <button type="button" class="btn-outline-modern btnEditar" data-id="<?php echo (int)$row['id_proyecto']; ?>" title="Editar proyecto">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn-outline-modern btnPosicion" data-id="<?php echo (int)$row['id_proyecto']; ?>" data-posicion-actual="<?php echo (int)$row['posicion']; ?>" title="Cambiar posición">
                                            <i class="fas fa-sort-numeric-down"></i>
                                        </button>
                                        <button type="button" class="btnToggleActivo <?php echo $is_activo ? 'btn-success-modern' : 'btn-outline-modern'; ?>" data-id="<?php echo (int)$row['id_proyecto']; ?>" title="<?php echo $is_activo ? 'Desactivar' : 'Activar'; ?>">
                                            <i class="fas fa-<?php echo $is_activo ? 'check-circle' : 'times-circle'; ?>"></i>
                                        </button>
                                        <button type="button" class="btnTogglePublicado <?php echo $is_publicado ? 'btn-success-modern' : 'btn-outline-modern'; ?>" data-id="<?php echo (int)$row['id_proyecto']; ?>" title="<?php echo $is_publicado ? 'Quitar de publicados' : 'Publicar'; ?>">
                                            <i class="fas fa-<?php echo $is_publicado ? 'eye' : 'eye-slash'; ?>"></i>
                                        </button>
                                        <button type="button" class="btnToggleBloqueado <?php echo $is_bloqueado ? 'btn-danger-modern' : 'btn-outline-modern'; ?>" data-id="<?php echo (int)$row['id_proyecto']; ?>" title="<?php echo $is_bloqueado ? 'Hacer público' : 'Hacer privado'; ?>">
                                            <i class="fas fa-<?php echo $is_bloqueado ? 'lock' : 'lock-open'; ?>"></i>
                                        </button>
                                        <button type="button" class="btn-danger-modern btnEliminarProyecto" data-id="<?php echo (int)$row['id_proyecto']; ?>" data-nombre="<?php echo htmlspecialchars($row['nombre_proyecto'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" title="Eliminar proyecto">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php if ($is_iframe): ?>
            <!-- Modo iframe: solo formulario -->
            <div class="card-modern p-4 fade-in-up">
                <div class="d-flex align-items-center mb-4">
                    <i class="fas fa-project-diagram fa-2x" style="color: var(--primary-dark); margin-right: 12px;"></i>
                    <h1 class="h3 mb-0" style="color: var(--primary-dark); font-weight: 700;">Registro de Proyecto</h1>
                </div>
            <?php endif; ?>

            <!-- Modal para editar posición -->
            <?php if (!$is_iframe): ?>
            <div class="modal fade" id="modalPosicion" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fas fa-sort-numeric-down mr-2"></i>Cambiar posición del proyecto</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" id="posicion_proyecto_id" value="">
                            <div class="mb-3">
                                <label for="nueva_posicion" class="form-label fw-semibold">Nueva posición (número entero)</label>
                                <input type="number" class="form-control" id="nueva_posicion" min="0" max="999" step="1">
                                <small class="text-muted">Los proyectos se ordenan de menor a mayor posición.</small>
                            </div>
                            <div class="posiciones-lista">
                                <strong><i class="fas fa-chart-line"></i> Posiciones ocupadas actualmente:</strong>
                                <div id="listaPosicionesOcupadas" class="mt-2">
                                    <!-- Aquí se cargarán dinámicamente -->
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn-outline-modern" data-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn-primary-custom" id="guardarPosicionBtn">Guardar posición</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Modal / Formulario de proyecto (existente) -->
            <?php if (!$is_iframe): ?>
            <div class="modal fade" id="modalProyecto" tabindex="-1" role="dialog" aria-labelledby="modalProyectoLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalProyectoLabel">Nuevo Proyecto</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
            <?php endif; ?>

            <form id="formProyecto" class="needs-validation" novalidate>
                <input type="hidden" id="id_proyecto" name="id_proyecto" value="">
                <fieldset class="border p-4 mb-4 rounded-3" style="border-color: var(--border) !important;">
                    <legend class="float-none w-auto px-2" style="color: var(--primary-dark); font-weight: 600;">Información del Proyecto</legend>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="cliente" class="form-label required-field">Cliente</label>
                            <select id="cliente" name="cliente" class="custom-select" required>
                                <option value="" disabled <?php echo $id_cliente_default === 0 ? 'selected' : ''; ?>>Selecciona un cliente</option>
                                <?php foreach ($clientesActivos as $row): ?>
                                    <option value="<?= (int) $row['id'] ?>" <?= $id_cliente_default === (int) $row['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars(adm_proyecto_cliente_option_label($row), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Por favor selecciona un cliente</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="nombre_proyecto" class="form-label required-field">Nombre del Proyecto</label>
                            <input type="text" class="form-control" id="nombre_proyecto" name="nombre_proyecto" maxlength="150" placeholder="Ej. Nuevo Sistema Web" required>
                            <div class="invalid-feedback">Ingresa el nombre del proyecto</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="tipo_proyecto" class="form-label required-field">Tipo de proyecto</label>
                            <select id="tipo_proyecto" name="tipo_proyecto" class="custom-select" required>
                                <?php echo adm_proyecto_tipo_options_html(0, false); ?>
                            </select>
                            <div class="invalid-feedback">Selecciona el tipo de proyecto</div>
                        </div>

                        <div class="col-12 mb-3">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="4" placeholder="Descripción general del proyecto (opcional)"></textarea>
                        </div>

                        <div class="col-12 mb-3">
                            <label for="descripcion_tecnica" class="form-label">Descripción técnica</label>
                            <textarea class="form-control" id="descripcion_tecnica" name="descripcion_tecnica" rows="5" placeholder="Stack, arquitectura, integraciones, APIs, hosting, accesos, notas para desarrollo..."></textarea>
                        </div>
                        
                        <div class="col-md-9 mb-3">
                            <label for="url_proyecto" class="form-label">URL del Proyecto (opcional)</label>
                            <div class="input-group">
                                <input type="url" class="form-control" id="url_proyecto" name="url_proyecto" placeholder="https://ejemplo.com/proyecto" maxlength="255">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-primary" id="btnSugerirEeat" title="Sugerir caso desde la URL">
                                        <i class="fas fa-magic mr-1"></i> Sugerir desde URL
                                    </button>
                                </div>
                            </div>
                            <small class="form-text text-muted">Si el proyecto ya está en línea, ingresa la URL. Puedes sugerir industria/alias desde el dominio (el resultado se completa a mano).</small>
                            <div id="eeatSuggestNotes" class="small text-muted mt-1" style="display:none;"></div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="posicion" class="form-label"><i class="fas fa-sort-numeric-down mr-1"></i>Posición en Web</label>
                            <input type="number" class="form-control" id="posicion" name="posicion" min="0" max="999" placeholder="0" value="0">
                            <small class="form-text text-muted">Orden de aparición (menor = primero)</small>
                        </div>

                        <div class="col-12 mb-2">
                            <fieldset class="border p-3 rounded-3" style="border-color: var(--border) !important; background:#fafbff;">
                                <legend class="float-none w-auto px-2" style="color: var(--primary-dark); font-weight: 600; font-size:0.95rem;">Caso E-E-A-T (portafolio)</legend>
                                <p class="small text-muted mb-3">Datos para mostrar prueba de trabajo en el portafolio. Sin nombres del equipo. Usa alias anonimizado si el cliente no autoriza su marca.</p>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="industria" class="form-label">Industria</label>
                                        <input type="text" class="form-control" id="industria" name="industria" maxlength="120" placeholder="Ej. Retail, Salud, Educación">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="ciudad" class="form-label">Ciudad / región</label>
                                        <input type="text" class="form-control" id="ciudad" name="ciudad" maxlength="120" placeholder="Ej. León, Gto.">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="alias_publico" class="form-label">Alias público</label>
                                        <input type="text" class="form-control" id="alias_publico" name="alias_publico" maxlength="150" placeholder="Ej. Pyme retail León">
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label for="problema" class="form-label">Problema / situación inicial</label>
                                        <textarea class="form-control" id="problema" name="problema" rows="2" placeholder="Qué pasaba antes del proyecto"></textarea>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label for="solucion" class="form-label">Solución entregada</label>
                                        <textarea class="form-control" id="solucion" name="solucion" rows="2" placeholder="Qué implementó ConlineWeb"></textarea>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label for="resultado" class="form-label">Resultado</label>
                                        <textarea class="form-control" id="resultado" name="resultado" rows="2" placeholder="Logro concreto (sin inventar métricas)"></textarea>
                                    </div>
                                    <div class="col-12 mb-0">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" value="1" id="caso_destacado" name="caso_destacado">
                                            <label class="form-check-label" for="caso_destacado">
                                                Destacar como caso en portafolio (muestra bloque problema → solución → resultado)
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </fieldset>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="mostrar_chk" name="mostrar">
                                <label class="form-check-label" for="mostrar_chk">
                                    Mostrar en la pantalla de proyectos
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label fw-semibold">Categorías (elige una o varias)</label>
                            <div class="tags-container" id="tags_categorias">
                                <?php
                                if (!empty($tags_by_group[1])) {
                                    foreach ($tags_by_group[1] as $grp) {
                                        echo '<div class="mb-3"><strong class="text-primary">' . htmlspecialchars($grp['subtitulo']) . '</strong><div class="d-flex flex-wrap mt-2">';
                                        foreach ($grp['items'] as $idx => $text) {
                                            $checkboxId = 'cat_' . $grp['id'] . '_' . $idx;
                                            echo '<div class="form-check form-check-inline">';
                                            echo '<input class="form-check-input tag-check categoria-check" type="checkbox" id="'. $checkboxId .'" data-group="'. $grp['id'] .'" data-index="'. $idx .'" value="'. htmlspecialchars($text, ENT_QUOTES) .'">';
                                            echo '<label class="form-check-label" for="'. $checkboxId .'">'. htmlspecialchars($text) .'</label>';
                                            echo '</div>';
                                        }
                                        echo '</div></div>';
                                    }
                                } else {
                                    echo '<div class="text-muted">No hay categorías definidas.</div>';
                                }
                                ?>
                            </div>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label fw-semibold">Tecnologías (elige una o varias)</label>
                            <div class="tags-container" id="tags_tecnologias">
                                <?php
                                $otherGroups = $tags_by_group;
                                unset($otherGroups[1]);
                                if (!empty($otherGroups)) {
                                    foreach ($otherGroups as $groups) {
                                        foreach ($groups as $grp) {
                                            echo '<div class="mb-3"><strong class="text-primary">' . htmlspecialchars($grp['subtitulo']) . '</strong><div class="d-flex flex-wrap mt-2">';
                                            foreach ($grp['items'] as $idx => $text) {
                                                $checkboxId = 'tec_' . $grp['id'] . '_' . $idx;
                                                echo '<div class="form-check form-check-inline">';
                                                echo '<input class="form-check-input tag-check tecnologia-check" type="checkbox" id="'. $checkboxId .'" data-group="'. $grp['id'] .'" data-index="'. $idx .'" value="'. htmlspecialchars($text, ENT_QUOTES) .'">';
                                                echo '<label class="form-check-label" for="'. $checkboxId .'">'. htmlspecialchars($text) .'</label>';
                                                echo '</div>';
                                            }
                                            echo '</div></div>';
                                        }
                                    }
                                } else {
                                    echo '<div class="text-muted">No hay tecnologías definidas.</div>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </fieldset>

                <div class="d-flex justify-content-between mt-4">
                    <button type="reset" class="btn-outline-modern px-4">Limpiar</button>
                    <button type="submit" class="btn-primary-custom px-5">Guardar Proyecto</button>
                </div>
            </form>

            <?php if (!$is_iframe): ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
                </div>
            <?php endif; ?>
            
        </div>
    </div>

    <footer class="sticky-footer bg-white mt-4">
        <div class="container my-auto">
            <div class="copyright text-center my-auto">
                <span>&copy; <?php echo date('Y'); ?> ConlineWeb</span>
            </div>
        </div>
    </footer>
</div>

<a class="scroll-to-top rounded" href="#page-top">
    <i class="fas fa-angle-up"></i>
</a>

<script>
const CLIENTES_ACTIVOS_IDS = <?= json_encode(array_map('intval', array_column($clientesActivos, 'id')), JSON_UNESCAPED_UNICODE) ?>;
</script>

<?php if (!$is_iframe): ?>
<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="vendor/datatables/jquery.dataTables.min.js"></script>
<script src="vendor/datatables/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function(){
    // Inicializar DataTable
    var tablaProyectos = $('#tablaProyectos').DataTable({
        order: [[1, 'asc']],
        pageLength: 25,
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.5/i18n/es-MX.json' },
        columnDefs: [
            { orderable: false, targets: [5, 6, 11] },
            { searchable: false, targets: [6] }
        ]
    });

    // Variables de filtros
    let filtros = {
        busqueda: '',
        empresa: '',
        estado: 'todos',
        publicado: 'todos',
        privado: 'todos',
        tipo: 'todos'
    };

    function etiquetasActivas() {
        const tags = [];
        if (filtros.busqueda) tags.push('Buscar: “' + filtros.busqueda + '”');
        if (filtros.empresa) tags.push('Empresa: ' + $('#filtroEmpresa option:selected').text());
        if (filtros.estado !== 'todos') tags.push('Estado: ' + filtros.estado);
        if (filtros.publicado !== 'todos') tags.push('Publicado: ' + (filtros.publicado === 'si' ? 'Sí' : 'No'));
        if (filtros.privado !== 'todos') tags.push(filtros.privado === 'si' ? 'Privado' : 'Público');
        if (filtros.tipo !== 'todos') {
            const tipoTxt = $('.proy-tipo-tab[data-value="' + filtros.tipo + '"] span:not(.proy-tipo-tab__count)').first().text().trim()
                || ('Tipo ' + filtros.tipo);
            tags.push('Tipo: ' + tipoTxt);
        }
        const $box = $('#activeFilters');
        if (!tags.length) {
            $box.html('<span class="text-muted small">Sin filtros activos · orden por posición</span>');
            return;
        }
        $box.html(tags.map(t => '<span class="active-filter-tag">' + t + '</span>').join(''));
    }

    function aplicarFiltros() {
        $.fn.dataTable.ext.search = [];
        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            let row = tablaProyectos.row(dataIndex).node();
            let empresaRow = $(row).attr('data-empresa') || '';
            let estadoRow = $(row).attr('data-estado') || '';
            let publicadoRow = $(row).attr('data-publicado') || '';
            let privadoRow = $(row).attr('data-privado') || '';
            let tipoRow = $(row).attr('data-tipo') || '0';
            let textoFila = (
                (data[2] || '') + ' ' +
                (data[3] || '') + ' ' +
                (data[4] || '') + ' ' +
                (data[7] || '')
            ).toLowerCase();

            if (filtros.busqueda && textoFila.indexOf(filtros.busqueda) === -1) return false;
            if (filtros.empresa !== '' && empresaRow.toLowerCase().indexOf(filtros.empresa) === -1) return false;
            if (filtros.estado !== 'todos' && estadoRow !== filtros.estado) return false;
            if (filtros.publicado !== 'todos' && publicadoRow !== filtros.publicado) return false;
            if (filtros.privado !== 'todos' && privadoRow !== filtros.privado) return false;
            if (filtros.tipo !== 'todos' && String(tipoRow) !== String(filtros.tipo)) return false;
            return true;
        });
        tablaProyectos.draw();
        actualizarContador();
        etiquetasActivas();
    }

    function actualizarContador() {
        let total = tablaProyectos.rows({ filter: 'applied' }).count();
        $('#visibleCount').text(total);
    }

    function limpiarFiltros() {
        filtros = { busqueda: '', empresa: '', estado: 'todos', publicado: 'todos', privado: 'todos', tipo: 'todos' };
        $('#filtroBusqueda').val('');
        $('#filtroEmpresa').val('');
        $('.filter-pill').removeClass('active');
        $('.filter-pill[data-value="todos"]').addClass('active');
        $('.proy-tipo-tab').removeClass('active').attr('aria-selected', 'false');
        $('.proy-tipo-tab[data-value="todos"]').addClass('active').attr('aria-selected', 'true');
        aplicarFiltros();
    }

    // Busqueda en vivo
    let busquedaTimer = null;
    $('#filtroBusqueda').on('input', function() {
        clearTimeout(busquedaTimer);
        const val = $(this).val();
        busquedaTimer = setTimeout(function() {
            filtros.busqueda = (val || '').toLowerCase().trim();
            aplicarFiltros();
        }, 180);
    });

    // Evento filtro empresa
    $('#filtroEmpresa').on('change', function() {
        filtros.empresa = $(this).val().toLowerCase();
        aplicarFiltros();
    });

    // Filtros tipo pill (estado / publicado / privado)
    $('.filter-pill').on('click', function() {
        let tipo = $(this).data('filter');
        let valor = $(this).data('value');
        if (!tipo) return;
        $(`.filter-pill[data-filter="${tipo}"]`).removeClass('active');
        $(this).addClass('active');
        filtros[tipo] = valor;
        aplicarFiltros();
    });

    // Tabs por tipo de proyecto
    $(document).on('click', '.proy-tipo-tab', function() {
        const valor = String($(this).data('value'));
        $('.proy-tipo-tab').removeClass('active').attr('aria-selected', 'false');
        $(this).addClass('active').attr('aria-selected', 'true');
        filtros.tipo = valor;
        aplicarFiltros();
    });

    $('#btnLimpiarFiltros').on('click', limpiarFiltros);

    actualizarContador();
    etiquetasActivas();

    // ===================== GESTIÓN DE POSICIÓN =====================
    let posicionProyectoId = null;
    let posicionActual = null;

    // Abrir modal de posición
    $(document).on('click', '.btnPosicion', function() {
        const id = $(this).data('id');
        const actual = $(this).data('posicion-actual');
        posicionProyectoId = id;
        posicionActual = actual;
        $('#posicion_proyecto_id').val(id);
        $('#nueva_posicion').val(actual);
        
        // Cargar lista de posiciones ocupadas (excluyendo este proyecto)
        $.ajax({
            url: 'obtener_posiciones_ocupadas.php',
            type: 'GET',
            data: { id_proyecto: id },
            dataType: 'json',
            success: function(resp) {
                if (resp && resp.success && resp.posiciones) {
                    let html = '';
                    resp.posiciones.forEach(function(pos) {
                        let clase = (pos == actual) ? 'posicion-item actual' : 'posicion-item';
                        html += `<span class="${clase}">${pos}</span>`;
                    });
                    if (html === '') html = '<span class="text-muted">No hay otras posiciones ocupadas.</span>';
                    $('#listaPosicionesOcupadas').html(html);
                } else {
                    $('#listaPosicionesOcupadas').html('<span class="text-muted">Error al cargar posiciones.</span>');
                }
            },
            error: function() {
                $('#listaPosicionesOcupadas').html('<span class="text-muted">Error al cargar posiciones.</span>');
            }
        });
        
        $('#modalPosicion').modal('show');
    });

    // Guardar nueva posición
    $('#guardarPosicionBtn').on('click', function() {
        const nuevaPos = parseInt($('#nueva_posicion').val(), 10);
        if (isNaN(nuevaPos)) {
            Swal.fire('Error', 'Ingresa un número válido.', 'error');
            return;
        }
        if (nuevaPos < 0 || nuevaPos > 999) {
            Swal.fire('Error', 'La posición debe estar entre 0 y 999.', 'error');
            return;
        }
        // Verificar si ya está ocupada por otro proyecto
        const ocupadas = [];
        $('#listaPosicionesOcupadas .posicion-item').each(function() {
            if (!$(this).hasClass('actual')) {
                ocupadas.push(parseInt($(this).text(), 10));
            }
        });
        if (ocupadas.includes(nuevaPos)) {
            Swal.fire('Error', `La posición ${nuevaPos} ya está ocupada por otro proyecto. Elige otra.`, 'error');
            return;
        }
        
        $.ajax({
            url: 'cambiar_posicion_proyecto.php',
            type: 'POST',
            data: { id_proyecto: posicionProyectoId, nueva_posicion: nuevaPos },
            dataType: 'json',
            success: function(resp) {
                if (resp && resp.success) {
                    Swal.fire('Actualizado', 'La posición ha sido cambiada correctamente.', 'success')
                        .then(() => {
                            window.location.reload(); // Recargar para orden correcto
                        });
                } else {
                    Swal.fire('Error', resp.message || 'No se pudo cambiar la posición.', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Error de conexión.', 'error');
            }
        });
    });

    // ===================== OTROS TOGGLES (Activo, Publicado, Bloqueado) =====================
    // (Mantener igual que antes, pero actualizar también data-posicion si es necesario)
    $(document).on('click', '.btnTogglePublicado', function(){
        const btn = $(this);
        const id = btn.data('id');
        if (!id) return;
        btn.prop('disabled', true);
        $.ajax({
            url: 'toggle_visible_proyecto.php',
            type: 'POST',
            data: { id_proyecto: id },
            dataType: 'json'
        }).done(function(resp){
            if (resp && resp.success) {
                const esPublicado = parseInt(resp.mostrar, 10) === 1;
                btn.removeClass('btn-success-modern btn-outline-modern');
                btn.addClass(esPublicado ? 'btn-success-modern' : 'btn-outline-modern');
                btn.html('<i class="fas fa-' + (esPublicado ? 'eye' : 'eye-slash') + '"></i>');
                btn.attr('title', esPublicado ? 'Quitar de publicados' : 'Publicar');
                const fila = btn.closest('tr');
                const badgeCol = fila.find('#publicado-badge-' + id).closest('td');
                if (badgeCol.length) {
                    badgeCol.html('<span class="badge-modern ' + (esPublicado ? 'badge-success-modern' : 'badge-secondary-modern') + '" id="publicado-badge-' + id + '"><i class="fas fa-' + (esPublicado ? 'check-circle' : 'times-circle') + ' mr-1"></i>' + (esPublicado ? 'Publicado' : 'No publicado') + '</span>');
                }
                fila.attr('data-publicado', esPublicado ? 'si' : 'no');
                aplicarFiltros();
                Swal.fire('Actualizado', 'El estado de publicación ha sido cambiado.', 'success');
            } else {
                Swal.fire('Error', (resp && resp.message) ? resp.message : 'No se pudo cambiar el estado.', 'error');
            }
        }).fail(function(){
            Swal.fire('Error', 'No se pudo cambiar el estado.', 'error');
        }).always(function(){
            btn.prop('disabled', false);
        });
    });

    $(document).on('click', '.btnToggleActivo', function(){
        const btn = $(this);
        const id = btn.data('id');
        if (!id) return;
        btn.prop('disabled', true);
        $.ajax({
            url: 'toggle_activo_proyecto.php',
            type: 'POST',
            data: { id_proyecto: id },
            dataType: 'json'
        }).done(function(resp){
            if (resp && resp.success) {
                const esActivo = parseInt(resp.activo, 10) === 1;
                btn.removeClass('btn-success-modern btn-outline-modern');
                btn.addClass(esActivo ? 'btn-success-modern' : 'btn-outline-modern');
                btn.html('<i class="fas fa-' + (esActivo ? 'check-circle' : 'times-circle') + '"></i>');
                btn.attr('title', esActivo ? 'Desactivar' : 'Activar');
                const fila = btn.closest('tr');
                const badgeCol = fila.find('td').eq(8);
                badgeCol.html('<span class="badge-modern ' + (esActivo ? 'badge-success-modern' : 'badge-secondary-modern') + '"><i class="fas fa-' + (esActivo ? 'check-circle' : 'times-circle') + ' mr-1"></i>' + (esActivo ? 'Activo' : 'Inactivo') + '</span>');
                fila.attr('data-estado', esActivo ? 'activo' : 'inactivo');
                aplicarFiltros();
                Swal.fire('Actualizado', 'El estado de actividad ha sido cambiado.', 'success');
            } else {
                Swal.fire('Error', (resp && resp.message) ? resp.message : 'No se pudo cambiar el estado.', 'error');
            }
        }).fail(function(){
            Swal.fire('Error', 'No se pudo cambiar el estado.', 'error');
        }).always(function(){
            btn.prop('disabled', false);
        });
    });

    $(document).on('click', '.btnToggleBloqueado', function(){
        const btn = $(this);
        const id = btn.data('id');
        if (!id) return;
        btn.prop('disabled', true);
        $.ajax({
            url: 'toggle_bloqueado_proyecto.php',
            type: 'POST',
            data: { id_proyecto: id },
            dataType: 'json'
        }).done(function(resp){
            if (resp && resp.success) {
                const esBloqueado = parseInt(resp.bloqueado, 10) === 1;
                btn.removeClass('btn-danger-modern btn-outline-modern');
                if (esBloqueado) btn.addClass('btn-danger-modern');
                else btn.addClass('btn-outline-modern');
                btn.html('<i class="fas fa-' + (esBloqueado ? 'lock' : 'lock-open') + '"></i>');
                btn.attr('title', esBloqueado ? 'Hacer público' : 'Hacer privado');
                const fila = btn.closest('tr');
                const badgeCol = fila.find('#bloqueado-badge-' + id).closest('td');
                if (badgeCol.length) {
                    badgeCol.html('<span class="badge-modern ' + (esBloqueado ? 'badge-danger-modern' : 'badge-success-modern') + '" id="bloqueado-badge-' + id + '"><i class="fas fa-' + (esBloqueado ? 'lock' : 'lock-open') + ' mr-1"></i>' + (esBloqueado ? 'Privado' : 'Público') + '</span>');
                }
                fila.attr('data-privado', esBloqueado ? 'si' : 'no');
                aplicarFiltros();
                Swal.fire('Actualizado', 'El estado de privacidad ha sido cambiado.', 'success');
            } else {
                Swal.fire('Error', (resp && resp.message) ? resp.message : 'No se pudo cambiar el estado de bloqueo.', 'error');
            }
        }).fail(function(){
            Swal.fire('Error', 'No se pudo cambiar el estado de bloqueo.', 'error');
        }).always(function(){
            btn.prop('disabled', false);
        });
    });

    // Eliminar proyecto
    $(document).on('click', '.btnEliminarProyecto', function(){
        const btn = $(this);
        const id = btn.data('id');
        const nombre = btn.data('nombre') || ('#' + id);
        if (!id) return;

        Swal.fire({
            title: '¿Eliminar proyecto?',
            html: 'Se dará de baja permanentemente <strong>' + $('<div>').text(String(nombre)).html() + '</strong>. Esta acción no se puede deshacer.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then(function(result){
            if (!result.isConfirmed) return;
            btn.prop('disabled', true);
            $.ajax({
                url: 'eliminar_proyecto.php',
                type: 'POST',
                data: { id_proyecto: id },
                dataType: 'json'
            }).done(function(resp){
                if (resp && resp.success) {
                    Swal.fire({ icon: 'success', title: 'Eliminado', text: resp.message || 'Proyecto eliminado.' })
                        .then(function(){ window.location.reload(); });
                } else {
                    Swal.fire('Error', (resp && resp.message) ? resp.message : 'No se pudo eliminar el proyecto.', 'error');
                    btn.prop('disabled', false);
                }
            }).fail(function(){
                Swal.fire('Error', 'No se pudo eliminar el proyecto.', 'error');
                btn.prop('disabled', false);
            });
        });
    });

    // Actualizar tipo desde DataTable
    $(document).on('change', '.selectTipoProyecto', function() {
        const $sel = $(this);
        const id = $sel.data('id');
        const prev = String($sel.attr('data-prev'));
        const tipo = String($sel.val());
        if (!id || tipo === prev) return;
        $sel.prop('disabled', true);
        $.ajax({
            url: 'actualizar_tipo_proyecto.php',
            type: 'POST',
            dataType: 'json',
            data: { id_proyecto: id, tipo_proyecto: tipo }
        }).done(function(resp) {
            if (resp && resp.success) {
                $sel.attr('data-prev', tipo);
                $sel.closest('tr').attr('data-tipo', tipo);
                aplicarFiltros();
                Swal.fire({ icon: 'success', title: 'Actualizado', text: resp.message || 'Tipo actualizado.', timer: 1200, showConfirmButton: false });
            } else {
                $sel.val(prev);
                Swal.fire('Error', (resp && resp.message) ? resp.message : 'No se pudo actualizar el tipo.', 'error');
            }
        }).fail(function() {
            $sel.val(prev);
            Swal.fire('Error', 'No se pudo actualizar el tipo.', 'error');
        }).always(function() {
            $sel.prop('disabled', false);
        });
    });

    // Abrir modal nuevo proyecto
    $('#btnNuevoProyecto').on('click', function(){
        $('#modalProyectoLabel').text('Nuevo Proyecto');
        $('#id_proyecto').val('');
        $('#formProyecto')[0].reset();
        $('#formProyecto').removeClass('was-validated');
        $('#mostrar_chk').prop('checked', false);
        $('#posicion').val('0');
        $('#tipo_proyecto').val('0');
        $('#industria').val('');
        $('#ciudad').val('');
        $('#alias_publico').val('');
        $('#problema').val('');
        $('#solucion').val('');
        $('#resultado').val('');
        $('#caso_destacado').prop('checked', false);
        $('#eeatSuggestNotes').hide().empty();
        $('#modalProyecto').appendTo('body');
        $('.tags-container').show();
        $('#modalProyecto').modal('show');
    });

    function fillEeatOnlyEmpty(sel, value) {
        if (!value) return;
        const $el = $(sel);
        if (String($el.val() || '').trim() === '') {
            $el.val(value);
        }
    }

    $('#btnSugerirEeat').on('click', function(){
        const url = String($('#url_proyecto').val() || '').trim();
        const tipo = String($('#tipo_proyecto').val() || '0');
        if (!url) {
            Swal.fire('URL requerida', 'Ingresa primero la URL del proyecto.', 'info');
            return;
        }
        const $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Analizando…');
        $.ajax({
            url: 'sugerir_proyecto_eeat.php',
            type: 'POST',
            data: { url: url, tipo_proyecto: tipo },
            dataType: 'json'
        }).done(function(resp){
            if (!(resp && resp.success && resp.data && resp.data.sugerencias)) {
                Swal.fire('Sin sugerencias', (resp && resp.message) ? resp.message : 'No se pudo analizar la URL.', 'warning');
                return;
            }
            const s = resp.data.sugerencias;
            fillEeatOnlyEmpty('#alias_publico', s.alias_publico);
            fillEeatOnlyEmpty('#industria', s.industria);
            fillEeatOnlyEmpty('#ciudad', s.ciudad);
            fillEeatOnlyEmpty('#problema', s.problema);
            fillEeatOnlyEmpty('#solucion', s.solucion);
            // resultado no se auto-rellena
            const notes = Array.isArray(s.notas) ? s.notas : [];
            if (notes.length) {
                $('#eeatSuggestNotes').html('<strong>Notas:</strong> ' + notes.map(function(n){ return $('<div>').text(n).html(); }).join('<br>')).show();
            } else {
                $('#eeatSuggestNotes').hide().empty();
            }
            Swal.fire('Sugerencias listas', 'Se completaron solo campos vacíos. Revisa y edita antes de guardar. El resultado debes escribirlo tú.', 'success');
        }).fail(function(){
            Swal.fire('Error', 'No se pudo analizar la URL.', 'error');
        }).always(function(){
            $btn.prop('disabled', false).html('<i class="fas fa-magic mr-1"></i> Sugerir desde URL');
        });
    });

    // Editar proyecto
    $(document).on('click', '.btnEditar', function(){
        const id = $(this).data('id');
        $('#modalProyectoLabel').text('Editar Proyecto');
        $('#formProyecto').removeClass('was-validated');
        $('#id_proyecto').val(id);
        $.ajax({
            url: 'obtener_proyecto.php',
            type: 'GET',
            data: { id_proyecto: id },
            dataType: 'json',
            success: function(resp){
                if (resp && resp.success && resp.data) {
                    const p = resp.data;
                    const idCliente = parseInt(p.id_cliente, 10) || 0;
                    if (idCliente > 0 && CLIENTES_ACTIVOS_IDS.includes(idCliente)) {
                        $('#cliente').val(String(idCliente));
                    } else {
                        $('#cliente').val('');
                    }
                    $('#nombre_proyecto').val(p.nombre_proyecto);
                    $('#tipo_proyecto').val(String(p.tipo_proyecto != null ? p.tipo_proyecto : 0));
                    $('#descripcion').val(p.descripcion || '');
                    $('#descripcion_tecnica').val(p.descripcion_tecnica || '');
                    $('#url_proyecto').val(p.url || '');
                    $('#posicion').val(p.posicion || '0');
                    $('#mostrar_chk').prop('checked', parseInt(p.mostrar,10) === 1);
                    $('#industria').val(p.industria || '');
                    $('#ciudad').val(p.ciudad || '');
                    $('#alias_publico').val(p.alias_publico || '');
                    $('#problema').val(p.problema || '');
                    $('#solucion').val(p.solucion || '');
                    $('#resultado').val(p.resultado || '');
                    $('#caso_destacado').prop('checked', parseInt(p.caso_destacado, 10) === 1);
                    $('#eeatSuggestNotes').hide().empty();
                    $('.tag-check').prop('checked', false);
                    if (p.tags && Array.isArray(p.tags)) {
                        p.tags.forEach(function(t){
                            const cls = (t.tipo === 'categoria') ? '.categoria-check' : '.tecnologia-check';
                            const selector = cls + '[data-group="'+t.tag_group_id+'"][data-index="'+t.tag_index+'"]';
                            $(selector).prop('checked', true);
                        });
                    }
                    $('#modalProyecto').appendTo('body');
                    $('#modalProyecto').modal('show');
                } else {
                    Swal.fire('Error', (resp && resp.message) ? resp.message : 'No se pudo obtener el proyecto.', 'error');
                }
            },
            error: function(){
                Swal.fire('Error', 'No se pudo obtener el proyecto.', 'error');
            }
        });
    });

    // Enviar formulario proyecto
    $('#formProyecto').on('submit', function(e){
        e.preventDefault();
        const form = this;
        if (form.checkValidity() === false) {
            e.stopPropagation();
            $(form).addClass('was-validated');
            return;
        }
        $(form).find('input[name^="tag_"], input[name="tag_count"]').remove();
        let idx = 0;
        $('.tag-check:checked').each(function(){
            const group = $(this).attr('data-group');
            const index = $(this).attr('data-index');
            const tipo = $(this).hasClass('categoria-check') ? 'categoria' : 'tecnologia';
            $(form).append('<input type="hidden" name="tag_'+idx+'_group" value="'+group+'">');
            $(form).append('<input type="hidden" name="tag_'+idx+'_index" value="'+index+'">');
            $(form).append('<input type="hidden" name="tag_'+idx+'_tipo" value="'+tipo+'">');
            idx++;
        });
        $(form).append('<input type="hidden" name="tag_count" value="'+idx+'">');
        const datos = $(form).serialize();
        $.ajax({
            url: 'guardar_proyecto.php',
            type: 'POST',
            data: datos,
            dataType: 'json',
            success: function(resp) {
                $(form).find('input[name^="tag_"], input[name="tag_count"]').remove();
                if (resp && resp.success) {
                    Swal.fire({ icon: 'success', title: '¡Éxito!', text: resp.message || 'Proyecto guardado.' })
                        .then(function(){
                            $('#modalProyecto').modal('hide');
                            window.location.reload();
                        });
                } else {
                    Swal.fire('Error', (resp && resp.message) ? resp.message : 'No se pudo guardar el proyecto.', 'error');
                }
            },
            error: function(xhr){
                let msg = 'No se pudo guardar el proyecto.';
                if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                Swal.fire('Error', msg, 'error');
            }
        });
    });

    $('#modalProyecto').on('shown.bs.modal', function(){
        $('.tags-container').show();
    });
});
</script>
<?php require_once __DIR__ . '/includes/adm_proyecto_preview.php'; ?>
<?php else: ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function(){
    $('#formProyecto').on('submit', function(e){
        e.preventDefault();
        const form = this;
        if (form.checkValidity() === false) {
            e.stopPropagation();
            $(form).addClass('was-validated');
            return;
        }
        $(form).find('input[name^="tag_"], input[name="tag_count"]').remove();
        let idx = 0;
        $('.tag-check:checked').each(function(){
            const group = $(this).attr('data-group');
            const index = $(this).attr('data-index');
            const tipo = $(this).hasClass('categoria-check') ? 'categoria' : 'tecnologia';
            $(form).append('<input type="hidden" name="tag_'+idx+'_group" value="'+group+'">');
            $(form).append('<input type="hidden" name="tag_'+idx+'_index" value="'+index+'">');
            $(form).append('<input type="hidden" name="tag_'+idx+'_tipo" value="'+tipo+'">');
            idx++;
        });
        $(form).append('<input type="hidden" name="tag_count" value="'+idx+'">');
        const datos = $(form).serialize();
        $.ajax({
            url: 'guardar_proyecto.php',
            type: 'POST',
            data: datos,
            dataType: 'json',
            success: function(resp){
                $(form).find('input[name^="tag_"], input[name="tag_count"]').remove();
                if (resp && resp.success) {
                    Swal.fire({ icon:'success', title:'¡Éxito!', text: resp.message || 'Proyecto guardado.' })
                        .then(function(){
                            if (window.self !== window.top) {
                                window.parent.postMessage({ tipo: 'proyectoGuardado', project_id: resp.project_id }, '*');
                            } else {
                                window.location.href = resp.redirect || window.location.href;
                            }
                        });
                } else {
                    Swal.fire('Error', (resp && resp.message) ? resp.message : 'No se pudo guardar el proyecto.', 'error');
                }
            },
            error: function(xhr){
                let msg = 'No se pudo guardar el proyecto.';
                if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                Swal.fire('Error', msg, 'error');
            }
        });
    });
});
</script>
<?php endif; ?>

</body>
</html>