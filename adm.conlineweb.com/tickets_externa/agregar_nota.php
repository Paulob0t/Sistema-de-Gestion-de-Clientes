<?php
require_once 'auth_externa.php';

// Verificar que las variables críticas están definidas
if (!isset($conn)) {
    die('Error: Conexión a base de datos no disponible');
}

if (!isset($usuario_login_id)) {
    die('Error: Usuario no autenticado correctamente');
}

if (!isset($cliente_id)) {
    die('Error: Cliente no identificado');
}

// Verificar que se recibió ID de solicitud
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$solicitud_id = (int)$_GET['id'];

// Verificar que la solicitud pertenece a Línea Italia
$verificar = $conn->prepare("SELECT id FROM solicitudes WHERE id = ? AND id_cliente = ?");
$verificar->bind_param('ii', $solicitud_id, $cliente_id);
$verificar->execute();
if (!$verificar->get_result()->fetch_assoc()) {
    header('Location: index.php');
    exit;
}

$success = false;
$error = '';

// Log de debugging para diagnosticar problemas
error_log("DEBUG agregar_nota.php - Solicitud ID: $solicitud_id, Usuario: $usuario_login_id, Cliente: $cliente_id");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("DEBUG agregar_nota.php - POST recibido: " . json_encode($_POST));
    $nota = trim($_POST['nota'] ?? '');
    
    if (empty($nota)) {
        $error = 'La nota no puede estar vacía.';
    } elseif (strlen($nota) < 5) {
        $error = 'La nota debe tener al menos 5 caracteres.';
    } else {
        // Procesar la nota directamente sin archivos por ahora
        try {
                // Insertar nota
                $archivos_json = !empty($archivos) ? json_encode($archivos) : null;
                
                // Log para debugging
                error_log("Insertando nota - Solicitud: $solicitud_id, Usuario: lineaitalia");
                
                // Usar la tabla correcta solicitudes_notas con el autor identificado
                $autor = "lineaitalia"; // Identificador para Línea Italia
                
                // Formatear la nota en el mismo formato JSON que usa el admin
                $nota_json = json_encode([
                    "text" => $nota,
                    "images" => []
                ]);
                
                $stmt = $conn->prepare("INSERT INTO solicitudes_notas (solicitud_id, autor, nota) VALUES (?, ?, ?)");
                
                if (!$stmt) {
                    throw new Exception("Error en prepare: " . $conn->error);
                }
                
                $stmt->bind_param('iss', $solicitud_id, $autor, $nota_json);
                
                if ($stmt->execute()) {
                    $success = true;
                    error_log("Nota agregada exitosamente - ID solicitud: $solicitud_id");
                    // Redirigir de vuelta a la solicitud
                    header("Location: ver_ticket.php?id=$solicitud_id&nota_agregada=1");
                    exit;
                } else {
                    throw new Exception("Error en execute: " . $stmt->error);
                }
                
            } catch (Exception $e) {
                error_log("Error al insertar nota: " . $e->getMessage());
                $error = 'Error al agregar la nota: ' . $e->getMessage();
            }
    }
}
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Agregar Nota - Sistema de Tickets</title>
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
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

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

        .form-textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-help {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 5px;
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

        .alert-error {
            background: rgba(220, 38, 38, 0.1);
            color: var(--danger);
            border: 1px solid rgba(220, 38, 38, 0.2);
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
            font-size: 2rem;
            color: var(--text-secondary);
            margin-bottom: 10px;
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

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .form-body {
                padding: 20px;
            }

            .form-actions {
                padding: 20px;
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-card">
            <div class="form-header">
                <h2 class="form-title">
                    <i class="fas fa-comment-medical"></i>
                    Agregar Nota a Solicitud #<?= $solicitud_id ?>
                </h2>
            </div>

            <form method="POST">
                <div class="form-body">
                    <?php if ($error): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>



                    <div class="form-group">
                        <label for="nota" class="form-label required">Contenido de la Nota</label>
                        <textarea 
                            id="nota" 
                            name="nota" 
                            class="form-textarea" 
                            placeholder="Escribe tu nota o comentario..."
                            required
                        ><?= htmlspecialchars($_POST['nota'] ?? '') ?></textarea>
                        <div class="form-help">Mínimo 5 caracteres. Sé claro y específico.</div>
                    </div>


                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Agregar Nota
                    </button>
                    <a href="ver_ticket.php?id=<?= $solicitud_id ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>