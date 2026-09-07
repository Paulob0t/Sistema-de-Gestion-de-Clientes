<?php
header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set("display_errors", 1);
include "menu.php";
include "conn_hostingpro.php";

// Verificar que existe la conexión
if (!$conn_hp) {
    die('Error de conexión a la base de datos');
}

// Cargar características
$sql = "SELECT * FROM caracteristicas ORDER BY orden_global ASC";
$result = $conn_hp->query($sql);
$caracteristicas = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $caracteristicas[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/admin-platform.css" rel="stylesheet">
    
    <style>
        /* ===== VARIABLES GLOBALES (DEBEN ESTAR EN ROOT) ===== */
        :root {
            --primary-dark: #000147;
            --primary: #1a1f6b;
            --primary-light: #2d3388;
            --primary-soft: #eef0ff;
            --hostpro-cyan: #00e5ff;
            --hostpro-lime: #a8ff3e;
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
        
        /* ===== ESTILOS HOSTPRO CON MAXIMA ESPECIFICIDAD ===== */
        #hostpro-caracteristicas-wrapper,
        #hostpro-caracteristicas-wrapper * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif !important;
        }
        
        body {
            background: #f1f5f9 !important;
            font-family: 'Inter', sans-serif !important;
            color: #0f172a !important;
        }
        
        /* Cards Modernos */
        #hostpro-caracteristicas-wrapper .stat-card,
        #hostpro-caracteristicas-wrapper .caracteristica-card {
            background: white !important;
            border-radius: 24px !important;
            border: 1px solid var(--border) !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            padding: 1.25rem !important;
            position: relative;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05) !important;
            margin-bottom: 1rem !important;
        }
        
        #hostpro-caracteristicas-wrapper .caracteristica-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--hostpro-cyan), var(--hostpro-lime));
        }
        
        #hostpro-caracteristicas-wrapper .caracteristica-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px -12px rgba(0, 229, 255, 0.2) !important;
        }
        
        /* Badges */
        #hostpro-caracteristicas-wrapper .badge-status {
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.2px;
            display: inline-block;
        }
        
        #hostpro-caracteristicas-wrapper .badge-tipo {
            font-size: 0.85em !important;
            padding: 0.4em 0.8em !important;
            font-weight: 600 !important;
        }
        
        #hostpro-caracteristicas-wrapper .badge-id {
            background: var(--primary-soft);
            color: var(--primary-dark);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        /* Botones Modernos */
        #hostpro-caracteristicas-wrapper .btn-modern {
            border-radius: 12px !important;
            padding: 8px 14px !important;
            font-size: 13px !important;
            font-weight: 600 !important;
            transition: all 0.2s !important;
            border: none !important;
            letter-spacing: 0.2px !important;
        }
        
        #hostpro-caracteristicas-wrapper .btn-modern:hover {
            transform: translateY(-1px) !important;
        }
        
        #hostpro-caracteristicas-wrapper .btn-primary,
        #hostpro-caracteristicas-wrapper .btn.btn-primary {
            background: var(--primary-dark) !important;
            border-color: var(--primary-dark) !important;
            border-radius: 10px !important;
            font-weight: 600 !important;
        }
        
        #hostpro-caracteristicas-wrapper .btn-primary:hover,
        #hostpro-caracteristicas-wrapper .btn.btn-primary:hover {
            background: var(--primary) !important;
            border-color: var(--primary) !important;
            transform: translateY(-1px) !important;
        }
        
        #hostpro-caracteristicas-wrapper .btn-success,
        #hostpro-caracteristicas-wrapper .btn.btn-success {
            background: var(--success) !important;
            border-color: var(--success) !important;
            border-radius: 10px !important;
            font-weight: 600 !important;
        }
        
        #hostpro-caracteristicas-wrapper .btn-success:hover,
        #hostpro-caracteristicas-wrapper .btn.btn-success:hover {
            background: #059669 !important;
            transform: translateY(-1px) !important;
        }
        
        #hostpro-caracteristicas-wrapper .btn-danger,
        #hostpro-caracteristicas-wrapper .btn.btn-danger {
            background: var(--danger) !important;
            border-color: var(--danger) !important;
            border-radius: 10px !important;
            font-weight: 600 !important;
        }
        
        #hostpro-caracteristicas-wrapper .btn-danger:hover,
        #hostpro-caracteristicas-wrapper .btn.btn-danger:hover {
            background: #dc2626 !important;
            transform: translateY(-1px) !important;
        }
        
        #hostpro-caracteristicas-wrapper .btn-hostpro,
        #hostpro-caracteristicas-wrapper .btn.btn-hostpro {
            background: linear-gradient(135deg, var(--hostpro-cyan) 0%, #00b8cc 100%) !important;
            border: none !important;
            color: white !important;
            font-weight: 600 !important;
            border-radius: 10px !important;
            padding: 8px 16px !important;
        }
        
        #hostpro-caracteristicas-wrapper .btn-hostpro:hover,
        #hostpro-caracteristicas-wrapper .btn.btn-hostpro:hover {
            background: linear-gradient(135deg, #00b8cc 0%, #009eb3 100%) !important;
            color: white !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 4px 12px rgba(0, 229, 255, 0.3) !important;
        }
        
        /* Form */
        #hostpro-caracteristicas-wrapper .add-form {
            background: white !important;
            padding: 1.5rem !important;
            border-radius: 24px !important;
            margin-bottom: 2rem !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05) !important;
            border: 1px solid var(--border) !important;
        }
        
        /* Form Controls */
        #hostpro-caracteristicas-wrapper .form-control,
        #hostpro-caracteristicas-wrapper .form-select {
            border-radius: 10px !important;
            border: 1px solid var(--border) !important;
            padding: 10px 14px !important;
            font-size: 14px !important;
            transition: all 0.2s !important;
        }
        
        #hostpro-caracteristicas-wrapper .form-control:focus,
        #hostpro-caracteristicas-wrapper .form-select:focus {
            border-color: var(--primary) !important;
            box-shadow: 0 0 0 3px rgba(0, 1, 71, 0.1) !important;
        }
        
        /* Icons */
        #hostpro-caracteristicas-wrapper .caracteristica-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-soft);
            border-radius: 8px;
            font-size: 1.5rem;
        }
        
        /* Textos */
        #hostpro-caracteristicas-wrapper .fw-semibold {
            font-weight: 600 !important;
        }
        
        #hostpro-caracteristicas-wrapper .fw-medium {
            font-weight: 500 !important;
        }
        
        #hostpro-caracteristicas-wrapper .text-secondary-custom {
            color: var(--text-secondary) !important;
        }
        
        /* Iconos con espacio */
        #hostpro-caracteristicas-wrapper .btn i,
        #hostpro-caracteristicas-wrapper .badge i,
        #hostpro-caracteristicas-wrapper h1 i,
        #hostpro-caracteristicas-wrapper h2 i,
        #hostpro-caracteristicas-wrapper h3 i {
            margin-right: 8px !important;
        }
        
        /* Container */
        #hostpro-caracteristicas-wrapper #content-wrapper {
            padding: 20px !important;
        }
        
        #hostpro-caracteristicas-wrapper .container-xl {
            max-width: 96% !important;
        }
        
        /* Page Header */
        #hostpro-caracteristicas-wrapper .page-header h1 {
            color: var(--primary-dark) !important;
            font-weight: 700 !important;
            letter-spacing: -0.02em !important;
            margin-bottom: 0.5rem !important;
        }
        
        #hostpro-caracteristicas-wrapper .page-header h1 .bi-sliders {
            color: var(--hostpro-cyan) !important;
            filter: drop-shadow(0 0 8px rgba(0, 229, 255, 0.5)) !important;
        }
        
        /* Animaciones */
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
        
        #hostpro-caracteristicas-wrapper .fade-in-up {
            animation: fadeInUp 0.5s ease-out !important;
        }
        
        /* Scrollbar */
        #hostpro-caracteristicas-wrapper ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        #hostpro-caracteristicas-wrapper ::-webkit-scrollbar-track {
            background: var(--light);
            border-radius: 10px;
        }
        
        #hostpro-caracteristicas-wrapper ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }
        
        #hostpro-caracteristicas-wrapper ::-webkit-scrollbar-thumb:hover {
            background: var(--text-muted);
        }
    </style>
</head>
<body style="background: #f1f5f9 !important; font-family: 'Inter', sans-serif !important; color: #0f172a !important; margin: 0; padding: 0;">
<!-- HOSTPRO WRAPPER UNICO -->
<div id="hostpro-caracteristicas-wrapper">
<!-- Content Wrapper -->
<div id="content-wrapper" class="d-flex flex-column" style="min-height: 100vh; background: #f1f5f9 !important;">
    <!-- Main Content -->
    <div id="content" style="flex: 1; padding: 20px;">
        <div class="container-xl my-4 py-4 legacy-touch" style="max-width: 96% !important;">
            
            <!-- Header -->
            <div class="d-flex align-items-center justify-content-between mb-4 fade-in-up">
                <div class="page-header">
                    <h1 class="h2 mb-1" style="color: #000147 !important; font-weight: 700 !important;">
                        <i class="bi bi-sliders" style="color: #00e5ff !important;"></i>
                        Características HostPro
                    </h1>
                    <p class="text-secondary-custom mb-0" style="font-weight: 500; color: #475569 !important;">Catálogo de características para planes</p>
                </div>
                <a href="/hostpro_planes.php" class="btn btn-primary" style="background: #000147 !important; border-color: #000147 !important; border-radius: 10px !important; font-weight: 600 !important; padding: 8px 16px !important;">
                    <i class="bi bi-arrow-left"></i> Volver a Planes
                </a>
            </div>

            <!-- Formulario Agregar -->
            <div class="add-form fade-in-up" style="background: white !important; padding: 1.5rem !important; border-radius: 24px !important; margin-bottom: 2rem !important; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05) !important; border: 1px solid #e2e8f0 !important;">
                <h5 class="mb-3 fw-bold" style="color: #0f172a !important; font-weight: 700 !important;">
                    <i class="bi bi-plus-circle-fill text-success"></i>
                    Agregar Nueva Característica
                </h5>
                <div class="row align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-bold" style="font-weight: 600 !important; color: #0f172a !important;">Nombre *</label>
                        <input type="text" class="form-control" id="nuevaCaracNombre" placeholder="Ej: Espacio en disco" style="border-radius: 10px !important; border: 1px solid #e2e8f0 !important; padding: 10px 14px !important;">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold" style="font-weight: 600 !important; color: #0f172a !important;">Tipo de dato *</label>
                        <select class="form-select" id="nuevaCaracTipo" style="border-radius: 10px !important; border: 1px solid #e2e8f0 !important; padding: 10px 14px !important;">
                            <option value="texto">Texto</option>
                            <option value="numero">Número</option>
                            <option value="boolean">Sí/No</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold" style="font-weight: 600 !important; color: #0f172a !important;">Icono (emoji/símbolo)</label>
                        <input type="text" class="form-control" id="nuevaCaracIcono" placeholder="💾" style="border-radius: 10px !important; border: 1px solid #e2e8f0 !important; padding: 10px 14px !important;">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-hostpro w-100" onclick="crearCaracteristica()" style="background: linear-gradient(135deg, #00e5ff 0%, #00b8cc 100%) !important; border: none !important; color: white !important; font-weight: 600 !important; border-radius: 10px !important; padding: 8px 16px !important;">
                            <i class="bi bi-plus-circle"></i> Agregar
                        </button>
                    </div>
                </div>
            </div>

            <!-- Lista de Características -->
            <h5 class="mb-3 fw-bold" style="color: #0f172a !important; font-weight: 700 !important;">
                <i class="bi bi-list-ul"></i>
                Características Disponibles
            </h5>
            <div id="listaCaracteristicas" class="row">
                <?php if (empty($caracteristicas)): ?>
                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            No hay características registradas. Agrega la primera característica usando el formulario de arriba.
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($caracteristicas as $carac): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="caracteristica-card" style="background: white !important; border-radius: 24px !important; border: 1px solid #e2e8f0 !important; padding: 1.25rem !important; position: relative; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05) !important; margin-bottom: 1rem !important;">
                                <div style="content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #00e5ff, #a8ff3e);"></div>
                                <div class="d-flex justify-content-between align-items-start mb-2" style="margin-top: 8px;">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="caracteristica-icon" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; background: #eef0ff; border-radius: 8px; font-size: 1.5rem;">
                                            <?php echo $carac['icono'] ?: '📋'; ?>
                                        </div>
                                        <div>
                                            <h6 class="mb-1 fw-bold" style="font-weight: 600 !important; color: #0f172a !important;"><?php echo htmlspecialchars($carac['nombre']); ?></h6>
                                            <span class="badge badge-tipo bg-secondary" style="font-size: 0.85em !important; padding: 0.4em 0.8em !important; font-weight: 600 !important;">
                                                <?php echo htmlspecialchars($carac['tipo_dato']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <button class="btn btn-sm btn-outline-danger" 
                                            onclick="eliminarCaracteristica(<?php echo $carac['id']; ?>, '<?php echo htmlspecialchars($carac['nombre'], ENT_QUOTES); ?>')"
                                            style="background: #ef4444 !important; border-color: #ef4444 !important; color: white !important; border-radius: 8px !important; font-weight: 600 !important;">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</div>
<!-- END HOSTPRO WRAPPER -->

    <!-- CSS Override al final para máxima prioridad -->
    <link href="css/hostpro_override.css?v=<?php echo time(); ?>" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/hostpro_admin.js"></script>
    <script>
        // Comentado: Las características ya están cargadas desde PHP
        // Solo necesitamos cargar el catálogo para el modal de agregar en planes
        document.addEventListener('DOMContentLoaded', () => {
            // NO llamamos cargarCaracteristicas() aquí porque ya están renderizadas desde PHP
            // Solo cargamos el catálogo global para usarlo en otras funciones
            fetch('/api/hostpro_caracteristicas.php')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        caracteristicasCatalogo = data.data;
                    }
                });
        });
    </script>
</body>
</html>
