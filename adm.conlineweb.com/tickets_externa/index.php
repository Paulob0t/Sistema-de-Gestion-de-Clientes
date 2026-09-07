<?php
require_once 'auth_externa.php';

// Obtener estadísticas usando tablas existentes
$stats = [];

// Total de solicitudes de Línea Italia
$result = $conn->query("SELECT COUNT(*) as total FROM solicitudes WHERE id_cliente = $cliente_id");
$stats['total'] = $result->fetch_assoc()['total'];

// Solicitudes por estado
$result = $conn->query("SELECT COUNT(*) as count FROM solicitudes WHERE id_cliente = $cliente_id AND estado = 'Pendiente'");
$stats['pendiente'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM solicitudes WHERE id_cliente = $cliente_id AND estado = 'En Proceso'");
$stats['en_proceso'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM solicitudes WHERE id_cliente = $cliente_id AND estado = 'Finalizado'");
$stats['finalizado'] = $result->fetch_assoc()['count'];

// Solicitudes validadas (nuevo estatus 'Validado correcto' vinculado con el flag validado)
$result = $conn->query("SELECT COUNT(*) as count FROM solicitudes WHERE id_cliente = $cliente_id AND validado = 1");
$stats['validado'] = $result->fetch_assoc()['count'];

$tickets_query = "
    SELECT s.*, p.nombre_proyecto,
           COALESCE(n.respuestas_count, 0) as respuestas_count,
           s.fecha_termina as ultima_actividad
    FROM solicitudes s 
    LEFT JOIN proyectos p ON s.id_proyecto = p.id_proyecto
    LEFT JOIN (
        SELECT solicitud_id, COUNT(*) as respuestas_count 
        FROM solicitudes_notas 
        GROUP BY solicitud_id
    ) n ON s.id = n.solicitud_id
    WHERE s.id_cliente = $cliente_id 
    ORDER BY s.fecha_solicitud DESC
";
$tickets_result = $conn->query($tickets_query);

function formatFecha($fecha) {
    if (!$fecha) return '-';
    return date('d/m/Y H:i', strtotime($fecha));
}

function getEstadoClass($estado) {
    switch($estado) {
        case 'Pendiente': return 'status-open';
        case 'En Proceso': return 'status-progress';  
        case 'Finalizado': return 'status-finalizado';
        case 'Validado correcto': return 'status-validated';
        default: return 'status-open';
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
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Sistema de Solicitudes - <?= htmlspecialchars($usuario_actual['nombre_display']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
            
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--light);
            color: var(--text-primary);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
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

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .logout-btn {
            background: var(--danger);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: background-color 0.2s;
        }

        .logout-btn:hover {
            background: #b91c1c;
        }

        /* Main Content */
        .main-content {
            padding: 30px 0;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--bg-card);
            padding: 20px;
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            text-align: center;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin: 0 auto 15px;
            color: white;
        }

        .stat-icon.total { background: var(--primary); }
        .stat-icon.open { background: var(--warning); }
        .stat-icon.progress { background: var(--info); }
        .stat-icon.resolved { background: var(--success); }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.85rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* Action Buttons */
        .action-bar {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
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

        /* Tickets Table */
        .tickets-section {
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            /* Permitir que las sombras de los botones no se corten en el borde */
            overflow: visible;
        }

        .section-header {
            padding: 20px 25px;
            border-bottom: 1px solid var(--border);
            background: rgba(37, 99, 235, 0.03);
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

        /* Evitar que el botón final quede pegado/cortado por el borde derecho */
        .tickets-table td:last-child {
            padding-right: 28px;
        }

        .tickets-table tr:hover {
            background: rgba(37, 99, 235, 0.02);
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap; /* evitar que el texto se divida en 2 líneas */
            line-height: 1.2;
            vertical-align: middle;
        }
        .status-open { background: rgba(217, 119, 6, 0.1); color: var(--warning); }
        .status-progress { background: rgba(2, 132, 199, 0.1); color: var(--info); }
        .status-waiting { background: rgba(100, 116, 139, 0.1); color: var(--secondary); }
        .status-finalizado {
            background: linear-gradient(180deg, rgba(37,160,100,0.12), rgba(37,160,100,0.08));
            color: #0f5132;
            border: 1px solid rgba(37,160,100,0.12);
            box-shadow: 0 6px 12px rgba(37,160,100,0.06);
        }
        .status-validated {
            background: linear-gradient(180deg, rgba(16,185,129,0.12), rgba(6,160,121,0.08));
            color: #065f46;
            border: 1px solid rgba(6,160,121,0.12);
            box-shadow: 0 6px 12px rgba(6,160,121,0.06);
        }
        /* Small validated badge to sit above Finalizado (matches screenshot) */
        .status-stack { display: inline-flex; flex-direction: column; gap: 6px; align-items: flex-start; }
        .status-validated-small {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            color: #065f46;
            background: #ecfdf5; /* very light green */
            border: 1px solid rgba(6,160,121,0.16);
            box-shadow: 0 4px 10px rgba(6,160,121,0.06);
            white-space: nowrap;
        }
        .status-validated-small i { color: #059669; font-size: 0.9rem; }
        /* Compact style for the FINALIZADO pill when stacked */
        .status-finalizado-compact {
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 0.78rem;
            text-transform: uppercase;
            box-shadow: 0 6px 12px rgba(37,160,100,0.06);
        }
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
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .action-bar {
                flex-direction: column;
            }

            .btn {
                text-align: center;
                justify-content: center;
            }

            .tickets-table {
                font-size: 0.8rem;
            }

            .tickets-table th,
            .tickets-table td {
                padding: 10px 8px;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Animation */
        .stat-card, .tickets-section {
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

        .ticket-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .ticket-description {
            color: var(--text-secondary);
            font-size: 0.85rem;
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
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
        /* Botones de validación más vistosos */
        .btn-validate {
            background: linear-gradient(180deg,#10b981,#059669);
            color: #fff;
            border: none;
            padding: 8px 12px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            box-shadow: 0 6px 12px rgba(5,150,105,0.12);
            cursor: pointer;
            position: relative;
            z-index: 3;
        }
        .btn-validate i { color: rgba(255,255,255,0.95); }
        .btn-validate:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(5,150,105,0.14); }

        .btn-unvalidate {
            background: #fff;
            color: #dc2626;
            border: 1px solid rgba(220,38,38,0.12);
            padding: 8px 12px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            cursor: pointer;
            box-shadow: 0 4px 8px rgba(0,0,0,0.04);
            position: relative;
            z-index: 3;
            margin-left: 8px;
        }
        .btn-unvalidate i { color: #dc2626; }
        .btn-unvalidate:hover { background: #fff6f6; transform: translateY(-1px); }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="header-content">
                <h1 class="header-title">
                    <i class="fas fa-clipboard-list"></i>
                    Sistema de Solicitudes
                </h1>
                <div class="user-info">
                    <span><i class="fas fa-user"></i> <?= htmlspecialchars($usuario_actual['nombre_display']) ?></span>
                    <a href="../cerrarSesionli.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon total">
                        <i class="fas fa-tickets-alt"></i>
                    </div>
                    <div class="stat-number"><?= $stats['total'] ?></div>
                    <div class="stat-label">Total de Solicitudes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon open">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-number"><?= $stats['pendiente'] ?></div>
                    <div class="stat-label">Pendientes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon progress">
                        <i class="fas fa-cog"></i>
                    </div>
                    <div class="stat-number"><?= $stats['en_proceso'] ?></div>
                    <div class="stat-label">En Proceso</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon resolved">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-number"><?= $stats['finalizado'] ?></div>
                    <div class="stat-label">Finalizados</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon resolved">
                        <i class="fas fa-award"></i>
                    </div>
                    <div class="stat-number"><?= $stats['validado'] ?></div>
                    <div class="stat-label">Validado correcto</div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-bar">
                <a href="crear_ticket.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Nueva Solicitud
                </a>
                <a href="mis_tickets.php" class="btn btn-outline">
                    <i class="fas fa-list"></i>
                    Ver Todas Mis Solicitudes
                </a>
            </div>

            <!-- Recent Solicitudes -->
            <div class="tickets-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-history"></i>
                        Solicitudes Recientes
                    </h2>
                </div>

                <?php if ($tickets_result->num_rows > 0): ?>
                    <div class="table-container">
                        <table class="tickets-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Título</th>
                                    <th>Estado</th>
                                    <th>Prioridad</th>
                                    <th>Proyecto</th>
                                    <th>Respuestas</th>
                                    <th>Última Actualización</th>
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
                                            <div class="ticket-description">
                                                <?php
                                                // Procesar descripción que puede estar en formato JSON
                                                $descripcion = $ticket['descripcion'];
                                                $descripcion_data = json_decode($descripcion, true);
                                                
                                                // Debug: mostrar qué está recibiendo (desactivado)
                                                // echo "<!--DEBUG: " . htmlspecialchars($descripcion) . "-->";
                                                
                                                if (is_array($descripcion_data)) {
                                                    // Es JSON válido
                                                    if (isset($descripcion_data['text'])) {
                                                        $texto = $descripcion_data['text'];
                                                    } else {
                                                        // JSON sin campo text, buscar otros campos
                                                        $texto = $descripcion_data[0] ?? 'Descripción no disponible';
                                                    }
                                                } else {
                                                    // No es JSON válido o es texto plano
                                                    // Verificar si empieza con {"text": para casos mal formateados
                                                    if (strpos($descripcion, '{"text":"') === 0) {
                                                        // Intentar extraer manualmente el texto
                                                        preg_match('/"text":"([^"]*)"/', $descripcion, $matches);
                                                        $texto = isset($matches[1]) ? $matches[1] : $descripcion;
                                                    } else {
                                                        $texto = $descripcion;
                                                    }
                                                }
                                                
                                                // Procesar saltos de línea literales y limpiar
                                                $texto_procesado = str_replace(['\\r\\n', '\\n', '\\r'], ' ', $texto);
                                                $texto_limpio = strip_tags($texto_procesado);
                                                $texto_corto = mb_substr($texto_limpio, 0, 100);
                                                if (mb_strlen($texto_limpio) > 100) {
                                                    $texto_corto .= '...';
                                                }
                                                echo htmlspecialchars($texto_corto);
                                                ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($ticket['validado']) && $ticket['validado'] == 1 && isset($ticket['estado']) && $ticket['estado'] === 'Finalizado'): ?>
                                                <div class="status-stack">
                                                    <div class="status-validated-small"><i class="fas fa-check-circle"></i> Validado</div>
                                                    <div class="status-badge status-finalizado status-finalizado-compact">FINALIZADO</div>
                                                </div>
                                            <?php elseif (!empty($ticket['validado']) && $ticket['validado'] == 1): ?>
                                                <!-- Validado but not Finalizado: show validated badge alone -->
                                                <span class="status-badge <?= getEstadoClass('Validado correcto') ?>">Validado correcto</span>
                                            <?php else: ?>
                                                <span class="status-badge <?= getEstadoClass($ticket['estado']) ?>">
                                                    <?= $ticket['estado'] ?>
                                                </span>
                                            <?php endif; ?>
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
                                        <td>
                                            <?= formatFecha($ticket['fecha_termina'] ?: $ticket['fecha_solicitud']) ?>
                                        </td>
                                        <td>
                                            <a href="ver_ticket.php?id=<?= $ticket['id'] ?>" class="btn btn-primary" style="padding: 6px 12px; font-size: 0.8rem;">
                                                <i class="fas fa-eye"></i>
                                                Ver
                                            </a>
                                            <!-- Nuevo: botón general Confirmar Solicitud (solo para Finalizados) -->
                                            <?php if (isset($ticket['estado']) && $ticket['estado'] === 'Finalizado' && empty($ticket['validado'])): ?>
                                                <button onclick="confirmarSolicitud(<?= $ticket['id'] ?>, <?= (int)$ticket['validado'] ?>)" class="btn-validate" title="Confirmar solicitud">
                                                    <i class="fas fa-check-double"></i>
                                                    Confirmar
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-ticket-alt"></i>
                        </div>
                        <h3>No tienes solicitudes aún</h3>
                        <p>Crea tu primera solicitud para comenzar a recibir soporte de nuestro equipo.</p>
                        <br>
                        <a href="crear_ticket.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Crear Primera Solicitud
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    <script>
        // Nuevo flujo: Confirmar Solicitud -> elegir Validar o Regresar a Pendiente con comentario
        async function confirmarSolicitud(ticketId, currentValidado) {
            const { value: action } = await Swal.fire({
                title: 'Confirmar Solicitud',
                text: 'Elige una acción para esta solicitud:',
                icon: 'question',
                showDenyButton: true,
                showCancelButton: true,
                confirmButtonText: 'Validar como Correcta',
                denyButtonText: 'Regresar a Pendiente',
                cancelButtonText: 'Cancelar'
            });

            // Si canceló o cerró
            if (!action && action !== false) return; // Swal returns undefined on cancel

            // Si elige Validar
            if (action === true) {
                // Llamada para validar
                await doToggleValidado(ticketId, 1);
                return;
            }

            // Si elige Regresar a Pendiente -> pedir comentario
            if (action === false) {
                const { value: comentario } = await Swal.fire({
                    title: 'Motivo de devolución',
                    input: 'textarea',
                    inputPlaceholder: 'Escribe una breve justificación...',
                    inputAttributes: { 'aria-label': 'Justificación' },
                    showCancelButton: true,
                    confirmButtonText: 'Enviar y regresar a Pendiente',
                    cancelButtonText: 'Cancelar',
                    preConfirm: (val) => {
                        if (!val || !val.trim()) {
                            Swal.showValidationMessage('Escribe una justificación');
                        }
                        return val;
                    }
                });

                if (!comentario) return; // cancelado

                // Guardar nota usando endpoint existente de notas
                try {
                    const fd = new FormData();
                    fd.append('solicitud_id', ticketId);
                    fd.append('autor', 'validacion');
                    fd.append('nota', comentario.trim());

                    // notas_guardar.php está en la carpeta solicitudes
                    const res = await fetch('../solicitudes/notas_guardar.php', { method: 'POST', body: fd });
                    const text = await res.text();
                    if (text.trim() !== 'OK') {
                        throw new Error('No se pudo guardar la justificación');
                    }
                } catch (e) {
                    Swal.fire('Error', 'No se pudo guardar la justificación: ' + (e.message || ''), 'error');
                    return;
                }

                // Luego marcar como no validado (pendiente)
                await doToggleValidado(ticketId, 0);
                return;
            }
        }

        // Helper para llamar toggle_validado.php y mostrar feedback
        async function doToggleValidado(ticketId, newValue) {
            try {
                const body = 'id=' + encodeURIComponent(ticketId) + '&validado=' + encodeURIComponent(newValue);
                const res = await fetch('toggle_validado.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body
                });
                const data = await res.json();
                if (!data.success) throw new Error(data.error || 'Error desconocido');
                Swal.fire({ title: 'Hecho', text: 'Acción realizada correctamente', icon: 'success', timer: 1200, showConfirmButton: false }).then(() => location.reload());
            } catch (e) {
                Swal.fire('Error', e.message || 'Error al actualizar el estado', 'error');
            }
        }
        function toggleValidado(ticketId, newValue) {
            const title = newValue == 1 ? 'Marcar como Validado correcto?' : 'Quitar validación?';
            const text = newValue == 1 ? 'Esto marcará el ticket como validado.' : 'Esto quitará la validación; el ticket volverá al flujo correspondiente.';

            // Usar SweetAlert2 si está disponible
            const ask = () => Swal.fire({
                title: title,
                text: text,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: newValue == 1 ? 'Sí, validar' : 'Sí, desvalidar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true
            });

            const doRequest = () => {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'toggle_validado.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 200) {
                            try {
                                var res = JSON.parse(xhr.responseText);
                                if (res.success) {
                                    Swal.fire({
                                        title: 'Hecho',
                                        text: 'El estado se actualizó correctamente.',
                                        icon: 'success',
                                        timer: 1200,
                                        showConfirmButton: false
                                    }).then(function(){ location.reload(); });
                                } else {
                                    Swal.fire('Error', res.error || 'No se pudo actualizar', 'error');
                                }
                            } catch (e) {
                                Swal.fire('Error', 'Respuesta inesperada del servidor', 'error');
                            }
                        } else {
                            Swal.fire('Error', 'Error en la solicitud al servidor: ' + xhr.status, 'error');
                        }
                    }
                };
                xhr.send('id=' + encodeURIComponent(ticketId) + '&validado=' + encodeURIComponent(newValue));
            };

            if (window.Swal) {
                ask().then((result) => {
                    if (result.isConfirmed) doRequest();
                });
            } else {
                // Fallback simple
                if (confirm(title + "\n" + text)) doRequest();
            }
        }
    </script>
</body>
</html>