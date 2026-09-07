<?php
require_once 'auth_externa.php';

$ticket_id = (int)($_GET['id'] ?? 0);
$error = '';

if (!$ticket_id) {
    $error = 'ID de solicitud no válido.';
} else {
    // Obtener solicitud de Línea Italia
    $ticket_query = "
        SELECT s.*, p.nombre_proyecto, c.empresa
        FROM solicitudes s 
        LEFT JOIN proyectos p ON s.id_proyecto = p.id_proyecto
        LEFT JOIN clientes c ON s.id_cliente = c.id
        WHERE s.id = ? AND s.id_cliente = ?
    ";
    
    $stmt = $conn->prepare($ticket_query);
    $stmt->bind_param('ii', $ticket_id, $cliente_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($ticket = $result->fetch_assoc()) {
        // Procesar descripción JSON
        $descripcion_data = json_decode($ticket['descripcion'], true);
        if (is_array($descripcion_data)) {
            $descripcion_texto = $descripcion_data['text'] ?? $ticket['descripcion'];
            $imagenes = $descripcion_data['images'] ?? [];
            $archivos = $descripcion_data['files'] ?? [];
        } else {
            $descripcion_texto = $ticket['descripcion'];
            $imagenes = [];
            $archivos = [];
        }
    } else {
        $error = 'Solicitud no encontrada o no autorizada.';
    }
}

function formatFecha($fecha) {
    if (!$fecha || $fecha === '0000-00-00 00:00:00') return '-';
    return date('d/m/Y H:i', strtotime($fecha));
}

function getEstadoClass($estado) {
    switch($estado) {
        case 'Pendiente': return 'status-pending';
        case 'En Proceso': return 'status-progress';  
        case 'Finalizado': return 'status-completed';
        default: return 'status-pending';
    }
}

function getPrioridadClass($prioridad) {
    switch($prioridad) {
        case 'Baja': return 'priority-low';
        case 'Media': return 'priority-medium';
        case 'Alta': return 'priority-high';
        default: return 'priority-medium';
    }
}
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Solicitud #<?= $ticket_id ?> - Sistema Línea Italia</title>
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
            max-width: 900px;
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

        /* Error State */
        .error-card {
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--danger);
            box-shadow: var(--shadow);
            padding: 40px;
            text-align: center;
        }

        .error-icon {
            font-size: 3rem;
            color: var(--danger);
            margin-bottom: 20px;
        }

        /* Ticket Card */
        .ticket-card {
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 25px;
        }

        .ticket-header {
            padding: 25px 30px;
            border-bottom: 1px solid var(--border);
            background: rgba(37, 99, 235, 0.03);
        }

        .ticket-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 15px;
        }

        .ticket-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .meta-label {
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-secondary);
        }

        .meta-value {
            font-size: 0.95rem;
            color: var(--text-primary);
        }

        /* Status Badges */
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status-pending { background: rgba(217, 119, 6, 0.1); color: var(--warning); }
        .status-progress { background: rgba(2, 132, 199, 0.1); color: var(--info); }
        .status-completed { background: rgba(5, 150, 105, 0.1); color: var(--success); }

        /* Priority Badges */
        .priority-badge {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .priority-low { background: rgba(5, 150, 105, 0.1); color: var(--success); }
        .priority-medium { background: rgba(2, 132, 199, 0.1); color: var(--info); }
        .priority-high { background: rgba(217, 119, 6, 0.1); color: var(--warning); }

        /* Ticket Body */
        .ticket-body {
            padding: 30px;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .description {
            font-size: 1rem;
            line-height: 1.7;
            color: var(--text-primary);
            margin-bottom: 25px;
            white-space: pre-wrap;
        }

        /* Attachments */
        .attachments-section {
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid var(--border);
        }

        .attachment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .attachment-item {
            background: var(--light);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            transition: all 0.2s;
        }

        .attachment-item:hover {
            background: rgba(37, 99, 235, 0.05);
            border-color: var(--primary);
        }

        .attachment-icon {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .attachment-name {
            font-size: 0.9rem;
            color: var(--text-primary);
            font-weight: 500;
            word-break: break-all;
        }

        .attachment-link {
            text-decoration: none;
            color: inherit;
        }

        /* Image Grid */
        .image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .image-item {
            aspect-ratio: 1;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--border);
            cursor: pointer;
            transition: transform 0.2s;
        }

        .image-item:hover {
            transform: scale(1.05);
        }

        .image-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Action Buttons */
        .action-buttons {
            padding: 25px 30px;
            border-top: 1px solid var(--border);
            background: rgba(37, 99, 235, 0.02);
            display: flex;
            gap: 15px;
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
        }

        .btn-secondary {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--light);
            color: var(--text-primary);
        }

        /* Lightbox */
        .lightbox {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.9);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .lightbox.active {
            display: flex;
        }

        .lightbox-content {
            position: relative;
            max-width: 90vw;
            max-height: 90vh;
        }

        .lightbox img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .lightbox-close {
            position: absolute;
            top: -50px;
            right: 0;
            background: var(--danger);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.2rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 0 15px;
            }

            .header-content {
                flex-direction: column;
                text-align: center;
            }

            .ticket-meta {
                grid-template-columns: 1fr;
            }

            .ticket-body {
                padding: 20px;
            }

            .action-buttons {
                padding: 20px;
                flex-direction: column;
            }
        }

        /* Notes Section */
        .notes-section {
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 25px;
        }

        .notes-header {
            padding: 20px 25px;
            border-bottom: 1px solid var(--border);
            background: rgba(37, 99, 235, 0.03);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .notes-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }

        .btn-small {
            padding: 8px 16px;
            font-size: 0.85rem;
        }

        .notes-list {
            padding: 0;
        }

        .note-item {
            border-bottom: 1px solid var(--border);
            padding: 20px 25px;
            position: relative;
        }

        .note-item:last-child {
            border-bottom: none;
        }

        .note-item.note-respuesta {
            background: rgba(5, 150, 105, 0.02);
            border-left: 4px solid var(--success);
        }

        .note-item.note-interno {
            background: rgba(217, 119, 6, 0.02);
            border-left: 4px solid var(--warning);
        }

        .note-item.note-nota {
            background: rgba(37, 99, 235, 0.02);
            border-left: 4px solid var(--primary);
        }

        .note-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .note-author {
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .note-type {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .note-date {
            color: var(--text-secondary);
            font-size: 0.85rem;
        }

        .note-content {
            color: var(--text-primary);
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .note-attachments {
            background: rgba(100, 116, 139, 0.05);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid var(--border);
        }

        .note-attachments h5 {
            margin: 0 0 10px 0;
            color: var(--text-secondary);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .attachment-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .attachment-link {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 6px;
            text-decoration: none;
            color: var(--primary);
            font-size: 0.85rem;
            transition: all 0.2s;
        }

        .attachment-link:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-1px);
        }

        .no-notes {
            padding: 60px 20px;
            text-align: center;
            color: var(--text-secondary);
        }

        .no-notes-icon {
            font-size: 3rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .no-notes h4 {
            color: var(--text-primary);
            margin-bottom: 10px;
        }

        /* Animation */
        .ticket-card, .notes-section {
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
    <header class="header">
        <div class="container">
            <div class="header-content">
                <h1 class="header-title">
                    <i class="fas fa-ticket-alt"></i>
                    Solicitud #<?= $ticket_id ?>
                </h1>
                <div class="breadcrumb">
                    <a href="index.php"><i class="fas fa-home"></i> Inicio</a>
                    <i class="fas fa-chevron-right"></i>
                    <a href="mis_tickets.php">Mis Solicitudes</a>
                    <i class="fas fa-chevron-right"></i>
                    <span>Solicitud #<?= $ticket_id ?></span>
                </div>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <?php if ($error): ?>
                <div class="error-card">
                    <div class="error-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h2>Error</h2>
                    <p><?= htmlspecialchars($error) ?></p>
                    <br>
                    <a href="index.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i>
                        Volver al Inicio
                    </a>
                </div>
            <?php else: ?>
                <div class="ticket-card">
                    <div class="ticket-header">
                        <h2 class="ticket-title"><?= htmlspecialchars($ticket['titulo']) ?></h2>
                        
                        <div class="ticket-meta">
                            <div class="meta-item">
                                <div class="meta-label">Estado</div>
                                <div class="meta-value">
                                    <span class="status-badge <?= getEstadoClass($ticket['estado']) ?>">
                                        <i class="fas fa-<?= $ticket['estado'] === 'Pendiente' ? 'clock' : ($ticket['estado'] === 'En Proceso' ? 'cog' : 'check') ?>"></i>
                                        <?= $ticket['estado'] ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="meta-item">
                                <div class="meta-label">Prioridad</div>
                                <div class="meta-value">
                                    <span class="priority-badge <?= getPrioridadClass($ticket['prioridad']) ?>">
                                        <?= $ticket['prioridad'] ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="meta-item">
                                <div class="meta-label">Proyecto</div>
                                <div class="meta-value"><?= htmlspecialchars($ticket['nombre_proyecto'] ?: 'General') ?></div>
                            </div>
                            
                            <div class="meta-item">
                                <div class="meta-label">Fecha de Creación</div>
                                <div class="meta-value"><?= formatFecha($ticket['fecha_solicitud']) ?></div>
                            </div>
                            
                            <?php if ($ticket['fecha_lim'] && $ticket['fecha_lim'] !== '0000-00-00 00:00:00'): ?>
                            <div class="meta-item">
                                <div class="meta-label">Fecha Límite</div>
                                <div class="meta-value"><?= formatFecha($ticket['fecha_lim']) ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($ticket['fecha_termina'] && $ticket['fecha_termina'] !== '0000-00-00 00:00:00'): ?>
                            <div class="meta-item">
                                <div class="meta-label">Fecha de Finalización</div>
                                <div class="meta-value"><?= formatFecha($ticket['fecha_termina']) ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="ticket-body">
                        <h3 class="section-title">
                            <i class="fas fa-align-left"></i>
                            Descripción
                        </h3>
                        <div class="description"><?= nl2br(htmlspecialchars(str_replace(['\\r\\n', '\\n', '\\r'], "\n", $descripcion_texto))) ?></div>
                        
                        <?php if (!empty($imagenes) || !empty($archivos)): ?>
                            <div class="attachments-section">
                                <?php if (!empty($imagenes)): ?>
                                    <h3 class="section-title">
                                        <i class="fas fa-images"></i>
                                        Imágenes
                                    </h3>
                                    <div class="image-grid">
                                        <?php foreach ($imagenes as $imagen): ?>
                                            <div class="image-item" onclick="openLightbox('<?= htmlspecialchars($imagen) ?>')">
                                                <img src="<?= htmlspecialchars($imagen) ?>" alt="Imagen adjunta" onerror="this.style.display='none'">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($archivos)): ?>
                                    <h3 class="section-title">
                                        <i class="fas fa-paperclip"></i>
                                        Archivos Adjuntos
                                    </h3>
                                    <div class="attachment-grid">
                                        <?php foreach ($archivos as $archivo): ?>
                                            <a href="<?= htmlspecialchars($archivo) ?>" target="_blank" class="attachment-link">
                                                <div class="attachment-item">
                                                    <div class="attachment-icon">
                                                        <i class="fas fa-file"></i>
                                                    </div>
                                                    <div class="attachment-name"><?= htmlspecialchars(basename($archivo)) ?></div>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Sección de Notas -->
                    <div class="notes-section">
                        <div class="notes-header">
                            <h3 class="notes-title">
                                <i class="fas fa-comments"></i>
                                Notas y Comentarios
                            </h3>
                            <a href="agregar_nota.php?id=<?= $ticket_id ?>" class="btn btn-primary btn-small">
                                <i class="fas fa-plus"></i>
                                Agregar Nota
                            </a>
                        </div>

                        <?php
                        // Obtener notas de la solicitud
                        $notas_query = "
                            SELECT n.*, n.autor as creador_usuario
                            FROM solicitudes_notas n
                            WHERE n.solicitud_id = ?
                            ORDER BY n.fecha_creacion DESC
                        ";
                        
                        $notas_stmt = $conn->prepare($notas_query);
                        $notas_stmt->bind_param('i', $ticket_id);
                        $notas_stmt->execute();
                        $notas_result = $notas_stmt->get_result();
                        ?>

                        <?php if ($notas_result->num_rows > 0): ?>
                            <div class="notes-list">
                                <?php while ($nota = $notas_result->fetch_assoc()): ?>
                                    <div class="note-item note-general">
                                        <div class="note-header">
                                            <div class="note-author">
                                                <i class="fas fa-comment"></i>
                                                <?= htmlspecialchars($nota['creador_usuario'] ?: 'Usuario') ?>
                                                <span class="note-type">(Nota)</span>
                                            </div>
                                            <div class="note-date">
                                                <?= formatFecha($nota['fecha_creacion']) ?>
                                            </div>
                                        </div>
                                        <div class="note-content">
                                            <?php
                                            // Procesar la nota que puede tener JSON con texto e imágenes
                                            $nota_content = $nota['nota'];
                                            $nota_data = json_decode($nota_content, true);
                                            
                                            if (is_array($nota_data) && isset($nota_data['text'])) {
                                                echo nl2br(htmlspecialchars($nota_data['text']));
                                                
                                                // Mostrar imágenes si existen
                                                if (isset($nota_data['images']) && is_array($nota_data['images'])) {
                                                    echo '<div class="note-images" style="margin-top: 10px;">';
                                                    foreach ($nota_data['images'] as $image) {
                                                        $image_url = '../solicitudes/' . $image;
                                                        echo '<img src="' . htmlspecialchars($image_url) . '" style="max-width: 200px; margin: 5px; border-radius: 5px;" onclick="window.open(this.src)">';
                                                    }
                                                    echo '</div>';
                                                }
                                            } else {
                                                echo nl2br(htmlspecialchars($nota_content));
                                            }
                                            ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-notes">
                                <div class="no-notes-icon">
                                    <i class="fas fa-comments"></i>
                                </div>
                                <h4>No hay notas aún</h4>
                                <p>Sé el primero en agregar una nota o comentario a esta solicitud.</p>
                                <br>
                                <a href="agregar_nota.php?id=<?= $ticket_id ?>" class="btn btn-primary">
                                    <i class="fas fa-plus"></i>
                                    Agregar Primera Nota
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="action-buttons">
                        <a href="mis_tickets.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            Volver a Mis Solicitudes
                        </a>
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-home"></i>
                            Ir al Inicio
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Lightbox -->
    <div class="lightbox" id="lightbox">
        <div class="lightbox-content">
            <button class="lightbox-close" onclick="closeLightbox()">
                <i class="fas fa-times"></i>
            </button>
            <img id="lightboxImg" src="" alt="">
        </div>
    </div>

    <script>
        function openLightbox(src) {
            document.getElementById('lightboxImg').src = src;
            document.getElementById('lightbox').classList.add('active');
        }

        function closeLightbox() {
            document.getElementById('lightbox').classList.remove('active');
        }

        // Cerrar lightbox con ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLightbox();
            }
        });

        // Cerrar lightbox clickeando fuera de la imagen
        document.getElementById('lightbox').addEventListener('click', function(e) {
            if (e.target === this) {
                closeLightbox();
            }
        });
    </script>
</body>
</html>