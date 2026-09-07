<?php
require_once 'auth_externa.php';

$success = false;
$error = '';

// Obtener proyectos de Línea Italia
$proyectos_query = "SELECT id_proyecto, nombre_proyecto FROM proyectos WHERE id_cliente = ? ORDER BY nombre_proyecto";
$proyectos_stmt = $conn->prepare($proyectos_query);
$proyectos_stmt->bind_param('i', $cliente_id);
$proyectos_stmt->execute();
$proyectos_result = $proyectos_stmt->get_result();



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $prioridad = $_POST['prioridad'] ?? 'Media';
    $id_proyecto = !empty($_POST['id_proyecto']) ? (int)$_POST['id_proyecto'] : NULL;
    $fecha_limite = !empty($_POST['fecha_limite']) ? $_POST['fecha_limite'] : NULL;
    
    // Validaciones
    if (empty($titulo)) {
        $error = 'El título es requerido.';
    } elseif (strlen($titulo) < 5) {
        $error = 'El título debe tener al menos 5 caracteres.';
    } elseif (empty($descripcion)) {
        $error = 'La descripción es requerida.';
    } elseif (strlen($descripcion) < 10) {
        $error = 'La descripción debe tener al menos 10 caracteres.';
    } elseif (!empty($fecha_limite) && strtotime($fecha_limite) <= time()) {
        $error = 'La fecha y hora límite debe ser posterior a la fecha y hora actual.';
    } else {
        // Procesar archivos adjuntos (simulado como JSON en descripción)
        $descripcion_json = [
            'text' => $descripcion,
            'images' => [],
            'files' => []
        ];
        
        if (!empty($_FILES['archivos']['name'][0])) {
            $upload_dir = '../solicitudes/uploads/solicitudes/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            for ($i = 0; $i < count($_FILES['archivos']['name']); $i++) {
                if ($_FILES['archivos']['error'][$i] === UPLOAD_ERR_OK) {
                    $filename = uniqid() . '_' . $_FILES['archivos']['name'][$i];
                    $filepath = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES['archivos']['tmp_name'][$i], $filepath)) {
                        $descripcion_json['files'][] = 'uploads/solicitudes/' . $filename;
                    }
                }
            }
        }
        
        // Insertar solicitud usando tabla existente
        $descripcion_final = json_encode($descripcion_json);
        
        // Configurar valores por defecto para campos requeridos
        $fecha_lim_final = $fecha_limite ?: '0000-00-00 00:00:00';
        $fecha_termina = '0000-00-00 00:00:00'; // Valor por defecto
        $repetir = 0; // No se repite por defecto
        $fecha_repeticion = '0000-00-00 00:00:00'; // Valor por defecto
        
        // Debug
        error_log("Datos para insertar - Cliente: $cliente_id, Proyecto: $id_proyecto, Usuario: $usuario_login_id, Fecha límite: $fecha_lim_final");
        
        // Primero intentar con la consulta básica que funciona en los datos existentes
        $stmt = $conn->prepare("INSERT INTO solicitudes (id_cliente, id_proyecto, titulo, descripcion, prioridad, estado, fecha_lim, fecha_termina, repetir, fecha_repeticion) VALUES (?, ?, ?, ?, ?, 'Pendiente', ?, ?, ?, ?)");
        $stmt->bind_param('iisssssis', $cliente_id, $id_proyecto, $titulo, $descripcion_final, $prioridad, $fecha_lim_final, $fecha_termina, $repetir, $fecha_repeticion);
        
        if ($stmt === false) {
            $error = 'Error en la preparación de la consulta: ' . $conn->error;
            error_log("Error prepare statement: " . $conn->error);
        } elseif ($stmt->execute()) {
            $success = true;
            $ticket_id = $conn->insert_id;
            
            // Log de la solicitud creada
            error_log("Solicitud creada - ID: $ticket_id, Cliente: $cliente_id, Usuario: " . ($usuario_actual['usuario'] ?? 'N/A'));
        } else {
            $error = 'Error al crear la solicitud: ' . $stmt->error;
            error_log("Error al crear solicitud - Error: " . $stmt->error . " - SQL Error: " . $conn->error);
        }
    }
}
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Crear Nueva Solicitud - Sistema de Solicitudes</title>
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
            max-width: 800px;
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

        /* Form Card */
        .form-card {
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .form-header {
            padding: 25px 30px;
            border-bottom: 1px solid var(--border);
            background: rgba(37, 99, 235, 0.03);
        }

        .form-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .form-body {
            padding: 30px;
        }

        /* Alerts */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(5, 150, 105, 0.1);
            color: var(--success);
            border: 1px solid rgba(5, 150, 105, 0.2);
        }

        .alert-error {
            background: rgba(220, 38, 38, 0.1);
            color: var(--danger);
            border: 1px solid rgba(220, 38, 38, 0.2);
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        .form-label.required::after {
            content: ' *';
            color: var(--danger);
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: inherit;
            background: var(--bg-card);
            color: var(--text-primary);
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        /* Estilos específicos para input datetime-local */
        input[type="datetime-local"] {
            position: relative;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23666' stroke-width='2'%3e%3crect x='3' y='4' width='18' height='18' rx='2' ry='2'/%3e%3cline x1='16' y1='2' x2='16' y2='6'/%3e%3cline x1='8' y1='2' x2='8' y2='6'/%3e%3cline x1='3' y1='10' x2='21' y2='10'/%3e%3ccircle cx='12' cy='17' r='1'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 18px;
            padding-right: 45px;
        }
        
        input[type="datetime-local"]::-webkit-calendar-picker-indicator {
            opacity: 0;
            position: absolute;
            right: 0;
            width: 50px;
            height: 100%;
            cursor: pointer;
        }
        
        /* Estilos adicionales para mejorar la apariencia */
        input[type="datetime-local"]:focus {
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%232563eb' stroke-width='2'%3e%3crect x='3' y='4' width='18' height='18' rx='2' ry='2'/%3e%3cline x1='16' y1='2' x2='16' y2='6'/%3e%3cline x1='8' y1='2' x2='8' y2='6'/%3e%3cline x1='3' y1='10' x2='21' y2='10'/%3e%3ccircle cx='12' cy='17' r='1'/%3e%3c/svg%3e");
        }

        .form-textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-help {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 5px;
        }

        /* File Upload */
        .file-upload {
            border: 2px dashed var(--border);
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            background: rgba(37, 99, 235, 0.02);
            transition: border-color 0.2s, background-color 0.2s;
            cursor: pointer;
        }

        .file-upload:hover {
            border-color: var(--primary);
            background: rgba(37, 99, 235, 0.05);
        }

        .file-upload-icon {
            font-size: 2.5rem;
            color: var(--text-secondary);
            margin-bottom: 15px;
        }

        .file-upload-text {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .file-upload-hint {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .file-input {
            display: none;
        }

        .selected-files {
            margin-top: 15px;
            padding: 15px;
            background: rgba(5, 150, 105, 0.05);
            border-radius: 6px;
            border: 1px solid rgba(5, 150, 105, 0.2);
        }

        .selected-files h4 {
            color: var(--success);
            font-size: 0.9rem;
            margin-bottom: 10px;
        }

        .file-list {
            list-style: none;
        }

        .file-list li {
            padding: 5px 0;
            font-size: 0.85rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Form Row */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        /* Buttons */
        .form-actions {
            padding: 25px 30px;
            border-top: 1px solid var(--border);
            background: rgba(37, 99, 235, 0.02);
            display: flex;
            gap: 15px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
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
            transform: translateY(-1px);
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

        /* Success State */
        .success-card {
            text-align: center;
            padding: 50px 30px;
        }

        .success-icon {
            font-size: 4rem;
            color: var(--success);
            margin-bottom: 25px;
        }

        .success-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 15px;
        }

        .success-message {
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin-bottom: 30px;
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

            .form-body {
                padding: 20px;
            }

            .form-actions {
                padding: 20px;
                flex-direction: column;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }

        /* Animation */
        .form-card {
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
                    <i class="fas fa-plus-circle"></i>
                    Crear Nueva Solicitud
                </h1>
                <div class="breadcrumb">
                    <a href="index.php"><i class="fas fa-home"></i> Inicio</a>
                    <i class="fas fa-chevron-right"></i>
                    <span>Crear Solicitud</span>
                </div>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <div class="form-card">
                <?php if ($success): ?>
                    <div class="success-card">
                        <div class="success-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h2 class="success-title">¡Solicitud Creada Exitosamente!</h2>
                        <p class="success-message">
                            Su solicitud #<?= $ticket_id ?> ha sido creada y nuestro equipo la revisará pronto.
                        </p>
                        <div style="display: flex; gap: 15px; justify-content: center;">
                            <a href="ver_ticket.php?id=<?= $ticket_id ?>" class="btn btn-primary">
                                <i class="fas fa-eye"></i>
                                Ver Solicitud
                            </a>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i>
                                Volver al Inicio
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="form-header">
                        <h2 class="form-title">
                            <i class="fas fa-edit"></i>
                            Información de la Solicitud
                        </h2>
                    </div>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-body">
                            <?php if ($error): ?>
                                <div class="alert alert-error">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <?= htmlspecialchars($error) ?>
                                </div>
                            <?php endif; ?>

                            <div class="form-group">
                                <label for="titulo" class="form-label required">Título de la Solicitud</label>
                                <input 
                                    type="text" 
                                    id="titulo" 
                                    name="titulo" 
                                    class="form-input" 
                                    placeholder="Describe brevemente el problema o solicitud"
                                    value="<?= htmlspecialchars($_POST['titulo'] ?? '') ?>"
                                    required
                                    maxlength="255"
                                >
                                <div class="form-help">Mínimo 5 caracteres. Sea específico y claro.</div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="prioridad" class="form-label required">Prioridad</label>
                                    <select id="prioridad" name="prioridad" class="form-select" required>
                                        <option value="Baja" <?= ($_POST['prioridad'] ?? '') === 'Baja' ? 'selected' : '' ?>>Baja</option>
                                        <option value="Media" <?= ($_POST['prioridad'] ?? 'Media') === 'Media' ? 'selected' : '' ?>>Media</option>
                                        <option value="Alta" <?= ($_POST['prioridad'] ?? '') === 'Alta' ? 'selected' : '' ?>>Alta</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="fecha_limite" class="form-label">Fecha y Hora Límite</label>
                                    <input 
                                        type="datetime-local" 
                                        id="fecha_limite" 
                                        name="fecha_limite" 
                                        class="form-input" 
                                        value="<?= htmlspecialchars($_POST['fecha_limite'] ?? '') ?>"
                                        min="<?= date('Y-m-d\TH:i') ?>"
                                    >
                                    <div class="form-help">Fecha y hora límite deseada para resolver la solicitud (opcional)</div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="id_proyecto" class="form-label">Proyecto</label>
                                <select id="id_proyecto" name="id_proyecto" class="form-select">
                                    <option value="">Seleccionar proyecto (opcional)</option>
                                    <?php while ($proyecto = $proyectos_result->fetch_assoc()): ?>
                                        <option value="<?= $proyecto['id_proyecto'] ?>" <?= ($_POST['id_proyecto'] ?? '') == $proyecto['id_proyecto'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($proyecto['nombre_proyecto']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="descripcion" class="form-label required">Descripción Detallada</label>
                                <textarea 
                                    id="descripcion" 
                                    name="descripcion" 
                                    class="form-textarea" 
                                    placeholder="Describa el problema o solicitud con el mayor detalle posible..."
                                    required
                                ><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
                                <div class="form-help">
                                    Incluya pasos para reproducir el problema, mensajes de error, capturas de pantalla si es necesario.
                                    Mínimo 10 caracteres.
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Archivos Adjuntos (Opcional)</label>
                                <div class="file-upload" onclick="document.getElementById('archivos').click()">
                                    <div class="file-upload-icon">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                    </div>
                                    <div class="file-upload-text">Haz clic para seleccionar archivos</div>
                                    <div class="file-upload-hint">Máximo 5 archivos. Formatos: PDF, DOC, DOCX, JPG, PNG, GIF (max 10MB cada uno)</div>
                                </div>
                                <input 
                                    type="file" 
                                    id="archivos" 
                                    name="archivos[]" 
                                    class="file-input" 
                                    multiple 
                                    accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif"
                                    onchange="mostrarArchivos(this)"
                                >
                                <div id="archivos-seleccionados" class="selected-files" style="display: none;">
                                    <h4><i class="fas fa-paperclip"></i> Archivos Seleccionados:</h4>
                                    <ul id="lista-archivos" class="file-list"></ul>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i>
                                Crear Solicitud
                            </button>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i>
                                Cancelar
                            </a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        function mostrarArchivos(input) {
            const contenedor = document.getElementById('archivos-seleccionados');
            const lista = document.getElementById('lista-archivos');
            
            if (input.files.length > 0) {
                lista.innerHTML = '';
                
                Array.from(input.files).forEach(file => {
                    const li = document.createElement('li');
                    li.innerHTML = `
                        <i class="fas fa-file"></i>
                        <span>${file.name}</span>
                        <span style="color: var(--text-secondary); font-size: 0.8rem;">(${formatFileSize(file.size)})</span>
                    `;
                    lista.appendChild(li);
                });
                
                contenedor.style.display = 'block';
            } else {
                contenedor.style.display = 'none';
            }
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Sugerir fecha límite basada en prioridad
        document.getElementById('prioridad').addEventListener('change', function() {
            const fechaLimiteInput = document.getElementById('fecha_limite');
            
            // Solo sugerir si no hay fecha ya seleccionada
            if (fechaLimiteInput.value === '') {
                const ahora = new Date();
                let diasASumar = 7; // Por defecto una semana
                let horaASumar = 17; // Por defecto 5:00 PM (hora de cierre de oficina)
                
                switch(this.value) {
                    case 'Crítica':
                        diasASumar = 0; // Mismo día
                        horaASumar = ahora.getHours() + 4; // 4 horas desde ahora
                        break;
                    case 'Alta':
                        diasASumar = 1; // 1 día
                        horaASumar = 17; // 5:00 PM del día siguiente
                        break;
                    case 'Media':
                        diasASumar = 7; // 1 semana
                        horaASumar = 17; // 5:00 PM
                        break;
                    case 'Baja':
                        diasASumar = 14; // 2 semanas
                        horaASumar = 17; // 5:00 PM
                        break;
                }
                
                const fechaSugerida = new Date(ahora.getTime() + (diasASumar * 24 * 60 * 60 * 1000));
                
                // Para prioridad crítica, agregar horas en lugar de cambiar el día
                if (this.value === 'Crítica') {
                    fechaSugerida.setTime(ahora.getTime() + (4 * 60 * 60 * 1000)); // 4 horas desde ahora
                } else {
                    fechaSugerida.setHours(horaASumar, 0, 0, 0); // Establecer la hora específica
                }
                
                // Formatear para datetime-local (YYYY-MM-DDTHH:MM)
                const year = fechaSugerida.getFullYear();
                const month = String(fechaSugerida.getMonth() + 1).padStart(2, '0');
                const day = String(fechaSugerida.getDate()).padStart(2, '0');
                const hours = String(fechaSugerida.getHours()).padStart(2, '0');
                const minutes = String(fechaSugerida.getMinutes()).padStart(2, '0');
                
                const fechaFormateada = `${year}-${month}-${day}T${hours}:${minutes}`;
                
                // Sugerir la fecha con una pequeña animación
                fechaLimiteInput.style.border = '2px solid var(--warning)';
                fechaLimiteInput.style.backgroundColor = 'rgba(217, 119, 6, 0.05)';
                fechaLimiteInput.value = fechaFormateada;
                
                // Mostrar tooltip informativo
                const tooltip = document.createElement('div');
                tooltip.style.cssText = `
                    position: absolute;
                    background: var(--warning);
                    color: white;
                    padding: 8px 12px;
                    border-radius: 6px;
                    font-size: 0.8rem;
                    font-weight: 600;
                    z-index: 1000;
                    transform: translateY(-100%);
                    margin-top: -10px;
                `;
                tooltip.textContent = `Fecha sugerida para prioridad ${this.value}`;
                fechaLimiteInput.parentNode.style.position = 'relative';
                fechaLimiteInput.parentNode.appendChild(tooltip);
                
                setTimeout(() => {
                    fechaLimiteInput.style.border = '2px solid var(--border)';
                    fechaLimiteInput.style.backgroundColor = 'var(--bg-card)';
                    if (tooltip.parentNode) {
                        tooltip.parentNode.removeChild(tooltip);
                    }
                }, 2500);
            }
        });

        // Validación del formulario
        document.querySelector('form').addEventListener('submit', function(e) {
            const titulo = document.getElementById('titulo').value.trim();
            const descripcion = document.getElementById('descripcion').value.trim();
            const fechaLimite = document.getElementById('fecha_limite').value;
            
            if (titulo.length < 5) {
                e.preventDefault();
                alert('El título debe tener al menos 5 caracteres.');
                return;
            }
            
            if (descripcion.length < 10) {
                e.preventDefault();
                alert('La descripción debe tener al menos 10 caracteres.');
                return;
            }
            
            // Validar fecha y hora límite
            if (fechaLimite) {
                const fechaSeleccionada = new Date(fechaLimite);
                const ahora = new Date();
                
                if (fechaSeleccionada <= ahora) {
                    e.preventDefault();
                    alert('La fecha y hora límite debe ser posterior a la fecha y hora actual.');
                    return;
                }
                
                // Validar que no sea más de 1 año en el futuro
                const unAnoEnElFuturo = new Date();
                unAnoEnElFuturo.setFullYear(unAnoEnElFuturo.getFullYear() + 1);
                
                if (fechaSeleccionada > unAnoEnElFuturo) {
                    e.preventDefault();
                    alert('La fecha límite no puede ser mayor a un año en el futuro.');
                    return;
                }
            }
            
            // Validar archivos
            const archivos = document.getElementById('archivos').files;
            if (archivos.length > 5) {
                e.preventDefault();
                alert('Máximo 5 archivos permitidos.');
                return;
            }
            
            for (let i = 0; i < archivos.length; i++) {
                if (archivos[i].size > 10 * 1024 * 1024) { // 10MB
                    e.preventDefault();
                    alert(`El archivo "${archivos[i].name}" es demasiado grande. Máximo 10MB por archivo.`);
                    return;
                }
            }
        });
    </script>
</body>
</html>