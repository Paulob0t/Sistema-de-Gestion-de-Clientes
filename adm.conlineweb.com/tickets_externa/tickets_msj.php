<?php
$cliente_id = 0; // Línea Italia en tabla clientes
if (isset($_GET['emp']))
$cliente_id = 87; // Línea Italia en tabla clientes
// Debug: Mostrar todos los parámetros GET recibidos
    // if (!empty($_GET)) {
    //     error_log("Filtros recibidos: " . print_r($_GET, true));
    // }
require_once 'auth_externa.php';

// Incluir conexión a base de datos
require_once __DIR__ . '/../conn.php';
// Parámetros de filtrado y paginación
$usuario_login_id = (int)$_GET['uid']; // ID 95 (lineaItalia)

$filtro_estado = $_GET['estado'] ?? '';
$filtro_prioridad = $_GET['prioridad'] ?? '';
$filtro_categoria = $_GET['categoria'] ?? '';
$busqueda = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Debug: Verificar que cliente_id existe
if (!isset($cliente_id) || empty($cliente_id)) {
    error_log("ERROR: cliente_id no está definido");
    die("Error: No se pudo identificar el cliente");
}

// Debug: Verificar conexión a la base de datos
if (!isset($conn) || $conn->connect_error) {
    error_log("ERROR: Problema con la conexión a la base de datos");
    die("Error de conexión a la base de datos");
}

// Construir WHERE clause para solicitudes
$where_conditions = ["s.id_cliente = ?"];
$params = [$cliente_id];
$types = "i"; // tipo integer para id_cliente

if (!empty($filtro_estado)) {
    $where_conditions[] = "s.estado = ?";
    $params[] = $filtro_estado;
    $types .= "s";
}

if (!empty($filtro_prioridad)) {
    $where_conditions[] = "s.prioridad = ?";
    $params[] = $filtro_prioridad;
    $types .= "s";
}

if (!empty($filtro_categoria)) {
    $where_conditions[] = "p.nombre_proyecto = ?";
    $params[] = $filtro_categoria;
    $types .= "s";
}

if (!empty($busqueda)) {
    $where_conditions[] = "(s.titulo LIKE ? OR s.descripcion LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $types .= "ss";
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Debug: Mostrar la consulta SQL y parámetros
error_log("WHERE clause: " . $where_clause);
error_log("Parámetros: " . print_r($params, true));
error_log("Tipos: " . $types);

// Contar total de solicitudes
$count_sql = "SELECT COUNT(*) as total FROM solicitudes s LEFT JOIN proyectos p ON s.id_proyecto = p.id_proyecto $where_clause";
$count_stmt = $conn->prepare($count_sql);
if ($count_stmt === false) {
    die('Error en la preparación de la consulta: ' . $conn->error);
}
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
if ($count_result === false) {
    die('Error en la ejecución de la consulta: ' . $count_stmt->error);
}
$total_tickets = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_tickets / $per_page);

// Obtener solicitudes
$tickets_sql = "
    SELECT s.*, p.nombre_proyecto,
           0 as respuestas_count,
           s.fecha_termina as ultima_actividad
    FROM solicitudes s 
    LEFT JOIN proyectos p ON s.id_proyecto = p.id_proyecto
    $where_clause 
    ORDER BY s.fecha_solicitud DESC 
    LIMIT ? OFFSET ?
";

$tickets_stmt = $conn->prepare($tickets_sql);
if ($tickets_stmt === false) {
    die('Error en la preparación de la consulta de tickets: ' . $conn->error);
}
$params_with_limit = array_merge($params, [$per_page, $offset]);
$types_with_limit = $types . 'ii'; // agregar tipos para LIMIT y OFFSET
$tickets_stmt->bind_param($types_with_limit, ...$params_with_limit);
$tickets_stmt->execute();
$tickets_result = $tickets_stmt->get_result();
if ($tickets_result === false) {
    die('Error en la ejecución de la consulta de tickets: ' . $tickets_stmt->error);
}

// Funciones helper
function formatFecha($fecha) {
    if (!$fecha) return '-';
    return date('d/m/Y H:i', strtotime($fecha));
}

function getEstadoClass($estado) {
    switch($estado) {
        case 'Pendiente': return 'status-open';
        case 'En Proceso': return 'status-progress';
        case 'Finalizado': return 'status-resolved';
        default: return 'status-open';
    }
}

function getEstadoIcon($estado) {
    switch($estado) {
        case 'Pendiente': return 'fas fa-clock';
        case 'En Proceso': return 'fas fa-cog fa-spin';
        case 'Finalizado': return 'fas fa-check';
        default: return 'fas fa-clock';
    }
}

function getPrioridadClass($prioridad) {
    switch($prioridad) {
        case 'Baja': return 'priority-low';
        case 'Media': return 'priority-medium';
        case 'Alta': return 'priority-high';
        case 'Crítica': return 'priority-critical';
        default: return 'priority-medium';
    }
}

// Generar URL con parámetros
function buildUrl($params = []) {
    $current = $_GET;
    $merged = array_merge($current, $params);
    return '?' . http_build_query(array_filter($merged));
}
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Mis Tickets - Sistema de Tickets</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #64748b;
            --success: #059669;
            --warning: #d97706;
            --danger: #dc2626;
            --info: #0284c7;
            --light: #f8fafc;
            --dark: #0f172a;
            --border: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --bg-card: #ffffff;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--light);
            color: var(--text-primary);
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header */
        .header {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            padding: 20px 0;
            box-shadow: var(--shadow);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* Main Content */
        .main-content {
            padding: 30px 0;
        }

        /* Filters */
        .filters-card {
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            padding: 25px;
            margin-bottom: 25px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-label {
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 6px;
            color: var(--text-primary);
        }

        .filter-input, .filter-select {
            padding: 10px 12px;
            border: 2px solid var(--border);
            border-radius: 6px;
            font-size: 0.9rem;
            background: var(--bg-card);
            color: var(--text-primary);
            transition: border-color 0.2s;
        }

        .filter-input:focus, .filter-select:focus {
            outline: none;
            border-color: var(--primary);
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-family: inherit;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }

        /* Results Info */
        .results-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        /* Tickets Table */
        .tickets-section {
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .section-header {
            padding: 20px 25px;
            border-bottom: 1px solid var(--border);
            background: rgba(37, 99, 235, 0.03);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-container {
            overflow-x: auto;
        }

        .tickets-table {
            width: 100%;
            border-collapse: collapse;
        }

        .tickets-table th {
            background: rgba(37, 99, 235, 0.05);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border);
        }

        .tickets-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
        }

        .tickets-table tr:hover {
            background: rgba(37, 99, 235, 0.02);
        }

        /* Status Badges */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status-open { background: rgba(217, 119, 6, 0.1); color: var(--warning); }
        .status-progress { background: rgba(2, 132, 199, 0.1); color: var(--info); }
        .status-waiting { background: rgba(100, 116, 139, 0.1); color: var(--secondary); }
        .status-resolved { background: rgba(5, 150, 105, 0.1); color: var(--success); }
        .status-closed { background: rgba(100, 116, 139, 0.1); color: var(--secondary); }

        /* Priority Badges */
        .priority-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .priority-low { background: rgba(5, 150, 105, 0.1); color: var(--success); }
        .priority-medium { background: rgba(2, 132, 199, 0.1); color: var(--info); }
        .priority-high { background: rgba(217, 119, 6, 0.1); color: var(--warning); }
        .priority-critical { background: rgba(220, 38, 38, 0.1); color: var(--danger); }

        .ticket-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .ticket-description {
            color: var(--text-secondary);
            font-size: 0.85rem;
            max-width: 350px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .response-count {
            background: var(--primary);
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .ticket-id {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: var(--primary);
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            padding: 25px;
            border-top: 1px solid var(--border);
            background: rgba(37, 99, 235, 0.02);
        }

        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            text-decoration: none;
            color: var(--text-primary);
            font-weight: 500;
            transition: all 0.2s;
        }

        .pagination a:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .pagination .current {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .pagination .disabled {
            color: var(--text-secondary);
            opacity: 0.5;
            pointer-events: none;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .filters-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .filters-grid > div:last-child {
                text-align: center;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 15px;
            }

            .header-content {
                flex-direction: column;
                text-align: center;
            }

            .results-info {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }

            .tickets-table {
                font-size: 0.8rem;
            }

            .tickets-table th,
            .tickets-table td {
                padding: 10px 8px;
            }

            .pagination {
                flex-wrap: wrap;
            }
        }

        /* Animation */
        .filters-card, .tickets-section {
            animation: fadeInUp 0.6s ease;
        }

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
    </style>
</head>
<body>
    <script>
        // Debug: Mostrar parámetros actuales en la consola
        console.log('Parámetros actuales:', {
            estado: '<?= htmlspecialchars($filtro_estado) ?>',
            prioridad: '<?= htmlspecialchars($filtro_prioridad) ?>',
            categoria: '<?= htmlspecialchars($filtro_categoria) ?>',
            busqueda: '<?= htmlspecialchars($busqueda) ?>',
            total_tickets: <?= $total_tickets ?>,
            cliente_id: <?= $cliente_id ?? 0 ?>
        });

        // Auto-submit del formulario cuando cambian los selects
        document.addEventListener('DOMContentLoaded', function() {
            const selects = document.querySelectorAll('.filter-select');
            const searchInput = document.querySelector('input[name="q"]');
            
            selects.forEach(select => {
                select.addEventListener('change', function() {
                    console.log('Filtro cambiado:', this.name, '=', this.value);
                    // Auto-submit cuando cambie un select
                    this.closest('form').submit();
                });
            });
            
            // También para el campo de búsqueda con Enter
            if (searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        this.closest('form').submit();
                    }
                });
            }
        });
    </script>
    <header class="header">
        <div class="container">
            <div class="header-content">
                <h1 class="header-title">
                    <i class="fas fa-list"></i>
                    Mis Tickets
                </h1>
                <div class="breadcrumb">
                    <a href="index.php"><i class="fas fa-home"></i> Inicio</a>
                    <i class="fas fa-chevron-right"></i>
                    <span>Mis Tickets</span>
                </div>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <!-- Filtros -->
            <div class="filters-card">
                <form method="GET" action="mis_tickets.php">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label class="filter-label">Buscar</label>
                            <input 
                                type="text" 
                                name="q" 
                                class="filter-input" 
                                placeholder="Buscar en títulos y descripciones..."
                                value="<?= htmlspecialchars($busqueda) ?>"
                            >
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">Estado</label>
                            <select name="estado" class="filter-select">
                                <option value="">Todos</option>
                                <option value="Pendiente" <?= $filtro_estado === 'Pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                <option value="En Proceso" <?= $filtro_estado === 'En Proceso' ? 'selected' : '' ?>>En Proceso</option>
                                <option value="Finalizado" <?= $filtro_estado === 'Finalizado' ? 'selected' : '' ?>>Finalizado</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">Prioridad</label>
                            <select name="prioridad" class="filter-select">
                                <option value="">Todas</option>
                                <option value="Crítica" <?= $filtro_prioridad === 'Crítica' ? 'selected' : '' ?>>Crítica</option>
                                <option value="Alta" <?= $filtro_prioridad === 'Alta' ? 'selected' : '' ?>>Alta</option>
                                <option value="Media" <?= $filtro_prioridad === 'Media' ? 'selected' : '' ?>>Media</option>
                                <option value="Baja" <?= $filtro_prioridad === 'Baja' ? 'selected' : '' ?>>Baja</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">Proyecto</label>
                            <select name="categoria" class="filter-select">
                                <option value="">Todos</option>
                                <?php
                                // Obtener proyectos para filtro usando prepared statement
                                $proyectos_sql = "SELECT DISTINCT nombre_proyecto FROM proyectos WHERE id_cliente = ? ORDER BY nombre_proyecto";
                                $proyectos_stmt = $conn->prepare($proyectos_sql);
                                if ($proyectos_stmt) {
                                    $proyectos_stmt->bind_param("i", $cliente_id);
                                    $proyectos_stmt->execute();
                                    $proyectos_result = $proyectos_stmt->get_result();
                                    while ($proyecto_filtro = $proyectos_result->fetch_assoc()):
                                ?>
                                        <option value="<?= htmlspecialchars($proyecto_filtro['nombre_proyecto']) ?>" <?= $filtro_categoria === $proyecto_filtro['nombre_proyecto'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($proyecto_filtro['nombre_proyecto']) ?>
                                        </option>
                                    <?php endwhile;
                                    $proyectos_stmt->close();
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                                Filtrar
                            </button>
                            <a href="mis_tickets.php" class="btn btn-outline">
                                <i class="fas fa-times"></i>
                                Limpiar
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Filtros activos -->
            <?php
            $filtros_activos = [];
            if (!empty($filtro_estado)) $filtros_activos[] = "Estado: " . $filtro_estado;
            if (!empty($filtro_prioridad)) $filtros_activos[] = "Prioridad: " . $filtro_prioridad;
            if (!empty($filtro_categoria)) $filtros_activos[] = "Proyecto: " . $filtro_categoria;
            if (!empty($busqueda)) $filtros_activos[] = "Búsqueda: \"" . $busqueda . "\"";
            
            if (!empty($filtros_activos)): ?>
                <div style="background: #e3f2fd; border: 1px solid #2196f3; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <span style="font-weight: 600; color: #1565c0;">
                            <i class="fas fa-filter"></i> Filtros activos:
                        </span>
                        <?php foreach ($filtros_activos as $filtro): ?>
                            <span style="background: #2196f3; color: white; padding: 4px 8px; border-radius: 12px; font-size: 0.8rem;">
                                <?= htmlspecialchars($filtro) ?>
                            </span>
                        <?php endforeach; ?>
                        <a href="mis_tickets.php" style="color: #1565c0; text-decoration: none; font-weight: 600;">
                            <i class="fas fa-times-circle"></i> Limpiar todos
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Información de resultados -->
            <div class="results-info">
                <div>
                    Mostrando <?= $tickets_result->num_rows ?> de <?= $total_tickets ?> solicitudes
                    <?php if ($busqueda): ?>
                        para "<?= htmlspecialchars($busqueda) ?>"
                    <?php endif; ?>
                </div>
                <div>
                    <a href="crear_ticket.php" class="btn btn-outline">
                        <i class="fas fa-plus"></i>
                        Nueva Solicitud
                    </a>
                </div>
            </div>

            <!-- Tickets -->
            <div class="tickets-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-tickets-alt"></i>
                        Lista de Tickets
                    </h2>
                </div>

                <?php if ($tickets_result->num_rows > 0): ?>
                    <div class="table-container">
                        <table class="tickets-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Título y Descripción</th>
                                    <th>Estado</th>
                                    <th>Prioridad</th>
                                    <th>Proyecto</th>
                                    <th>Respuestas</th>
                                    <th>Creado</th>
                                    <th>Última Actividad</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($ticket = $tickets_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <span class="ticket-id">#<?= $ticket['id'] ?></span>
                                        </td>
                                        <td>
                                            <div class="ticket-title"><?= htmlspecialchars($ticket['titulo']) ?></div>
                                            <div class="ticket-description"><?= htmlspecialchars(strip_tags($ticket['descripcion'])) ?></div>
                                        </td>
                                        <td>
                                            <span class="status-badge <?= getEstadoClass($ticket['estado']) ?>">
                                                <i class="<?= getEstadoIcon($ticket['estado']) ?>"></i>
                                                <?= $ticket['estado'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="priority-badge <?= getPrioridadClass($ticket['prioridad']) ?>">
                                                <?= $ticket['prioridad'] ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($ticket['nombre_proyecto'] ?: 'General') ?></td>
                                        <td>
                                            <span class="response-count"><?= $ticket['respuestas_count'] ?></span>
                                        </td>
                                        <td><?= formatFecha($ticket['fecha_solicitud']) ?></td>
                                        <td>
                                            <?= formatFecha($ticket['fecha_termina'] ?: $ticket['fecha_solicitud']) ?>
                                        </td>
                                        <td>
                                            <a href="ver_ticket.php?id=<?= $ticket['id'] ?>" class="btn btn-primary" style="padding: 6px 12px; font-size: 0.8rem;">
                                                <i class="fas fa-eye"></i>
                                                Ver
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="<?= buildUrl(['page' => $page - 1]) ?>">
                                    <i class="fas fa-chevron-left"></i>
                                    Anterior
                                </a>
                            <?php else: ?>
                                <span class="disabled">
                                    <i class="fas fa-chevron-left"></i>
                                    Anterior
                                </span>
                            <?php endif; ?>

                            <?php 
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            
                            if ($start > 1): ?>
                                <a href="<?= buildUrl(['page' => 1]) ?>">1</a>
                                <?php if ($start > 2): ?>
                                    <span>...</span>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $start; $i <= $end; $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="current"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="<?= buildUrl(['page' => $i]) ?>"><?= $i ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($end < $total_pages): ?>
                                <?php if ($end < $total_pages - 1): ?>
                                    <span>...</span>
                                <?php endif; ?>
                                <a href="<?= buildUrl(['page' => $total_pages]) ?>"><?= $total_pages ?></a>
                            <?php endif; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="<?= buildUrl(['page' => $page + 1]) ?>">
                                    Siguiente
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php else: ?>
                                <span class="disabled">
                                    Siguiente
                                    <i class="fas fa-chevron-right"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <h3>No se encontraron tickets</h3>
                        <p>
                            <?php if ($busqueda || $filtro_estado || $filtro_prioridad || $filtro_categoria): ?>
                                Intenta ajustar los filtros o crear un nuevo ticket.
                            <?php else: ?>
                                Crea tu primer ticket para comenzar.
                            <?php endif; ?>
                        </p>
                        <br>
                        <a href="crear_ticket.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Crear Ticket
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>