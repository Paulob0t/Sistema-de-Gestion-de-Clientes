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
        /* ===== ESTILOS ESTANDARIZADOS DEL ADMIN + HOSTPRO ===== */
        
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        
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
        
        body {
            background: #f1f5f9 !important;
            font-family: 'Inter', sans-serif !important;
            color: var(--text-primary) !important;
        }
        
        /* Cards Modernos */
        .stat-card, .plan-card {
            background: white !important;
            border-radius: 24px !important;
            border: 1px solid var(--border) !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05) !important;
            margin-bottom: 15px !important;
        }
        
        .plan-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--hostpro-cyan), var(--hostpro-lime));
        }
        
        .plan-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px -12px rgba(0, 229, 255, 0.2);
        }
        
        /* Badges */
        .badge-status {
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.2px;
            display: inline-block;
        }
        
        .badge-active {
            background: #dcfce7;
            color: #15803d;
        }
        
        .badge-inactive {
            background: #fee2e2;
            color: #b91c1c;
        }
        
        .badge-tipo {
            font-size: 0.75em;
            padding: 0.35em 0.65em;
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
        
        /* Botones Modernos */
        .btn-modern {
            border-radius: 12px;
            padding: 8px 14px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s;
            border: none;
            letter-spacing: 0.2px;
        }
        
        .btn-modern:hover {
            transform: translateY(-1px);
        }
        
        .btn-primary {
            background: var(--primary-dark) !important;
            border-color: var(--primary-dark) !important;
            border-radius: 10px !important;
            font-weight: 600 !important;
        }
        
        .btn-primary:hover {
            background: var(--primary) !important;
            border-color: var(--primary) !important;
            transform: translateY(-1px) !important;
        }
        
        .btn-success {
            background: var(--success) !important;
            border-color: var(--success) !important;
            border-radius: 10px !important;
            font-weight: 600 !important;
        }
        
        .btn-success:hover {
            background: #059669 !important;
            transform: translateY(-1px) !important;
        }
        
        .btn-danger {
            background: var(--danger) !important;
            border-color: var(--danger) !important;
            border-radius: 10px !important;
            font-weight: 600 !important;
        }
        
        .btn-danger:hover {
            background: #dc2626 !important;
            transform: translateY(-1px) !important;
        }
        
        .btn-hostpro {
            background: linear-gradient(135deg, var(--hostpro-cyan) 0%, #00b8cc 100%) !important;
            border: none !important;
            color: white !important;
            font-weight: 600 !important;
            border-radius: 10px !important;
            padding: 8px 16px !important;
        }
        
        .btn-hostpro:hover {
            background: linear-gradient(135deg, #00b8cc 0%, #009eb3 100%) !important;
            color: white !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 4px 12px rgba(0, 229, 255, 0.3) !important;
        }
        
        /* Modal */
        .modal-header {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%) !important;
            color: white !important;
            border-bottom: 3px solid var(--hostpro-cyan) !important;
            border-radius: 0 !important;
        }
        
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        
        .modal-content {
            border-radius: 24px;
            border: none;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        /* Filter Section */
        .filter-section {
            background: white !important;
            padding: 1.5rem !important;
            border-radius: 24px !important;
            margin-bottom: 1.5rem !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05) !important;
            border: 1px solid var(--border) !important;
        }
        
        /* Form Controls */
        .form-control, .form-select {
            border-radius: 10px !important;
            border: 1px solid var(--border) !important;
            padding: 10px 14px !important;
            font-size: 14px !important;
            transition: all 0.2s !important;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 1, 71, 0.1);
        }
        
        /* Caracteristicas */
        .caracteristica-item {
            padding: 12px;
            border: 1px solid var(--border);
            margin-bottom: 8px;
            border-radius: 10px;
            background: white;
            transition: all 0.2s;
        }
        
        .caracteristica-item:hover {
            border-color: var(--primary-soft);
            background: #fafbff;
        }
        
        /* Textos */
        .fw-semibold {
            font-weight: 600;
        }
        
        .fw-medium {
            font-weight: 500;
        }
        
        .text-secondary-custom {
            color: var(--text-secondary);
        }
        
        /* Iconos con espacio */
        .btn i, 
        .badge i,
        h1 i, h2 i, h3 i {
            margin-right: 8px;
        }
        
        /* Container */
        #content-wrapper {
            padding: 20px !important;
        }
        
        .container-xl {
            max-width: 96% !important;
        }
        
        /* Page Header */
        .page-header h1 {
            color: var(--primary-dark) !important;
            font-weight: 700 !important;
            letter-spacing: -0.02em !important;
            margin-bottom: 0.5rem !important;
        }
        
        .page-header h1 .bi-lightning-charge-fill {
            color: var(--hostpro-cyan);
            filter: drop-shadow(0 0 8px rgba(0, 229, 255, 0.5));
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
        
        .fade-in-up {
            animation: fadeInUp 0.5s ease-out;
        }
        
        /* Scrollbar */
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
        
        /* ===== WRAPPER HOSTPRO CON MAXIMA ESPECIFICIDAD ===== */
        #hostpro-planes-wrapper * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif !important;
        }
        
        #hostpro-planes-wrapper body,
        body#hostpro-planes-wrapper {
            background: #f1f5f9 !important;
        }
        
        #hostpro-planes-wrapper .plan-card,
        #hostpro-planes-wrapper .filter-section,
        #hostpro-planes-wrapper .add-form {
            background: white !important;
            border-radius: 24px !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05) !important;
            border: 1px solid #e2e8f0 !important;
        }
        
        #hostpro-planes-wrapper .btn-primary,
        #hostpro-planes-wrapper .btn.btn-primary,
        #hostpro-planes-wrapper button.btn-primary {
            background: #000147 !important;
            border-color: #000147 !important;
            border-radius: 10px !important;
        }
        
        #hostpro-planes-wrapper .btn-hostpro,
        #hostpro-planes-wrapper .btn.btn-hostpro,
        #hostpro-planes-wrapper button.btn-hostpro {
            background: linear-gradient(135deg, #00e5ff 0%, #00b8cc 100%) !important;
            border: none !important;
            color: white !important;
            border-radius: 10px !important;
        }
        
        #hostpro-planes-wrapper .form-control,
        #hostpro-planes-wrapper .form-select {
            border-radius: 10px !important;
            border: 1px solid #e2e8f0 !important;
            padding: 10px 14px !important;
        }
        
        #hostpro-planes-wrapper #content-wrapper {
            padding: 20px !important;
        }
        
        #hostpro-planes-wrapper .container-xl {
            max-width: 96% !important;
        }
    </style>
</head>
<body>
<!-- HOSTPRO WRAPPER UNICO -->
<div id="hostpro-planes-wrapper">
<!-- Content Wrapper -->
<div id="content-wrapper" class="d-flex flex-column">
    <!-- Main Content -->
    <div id="content">
        <div class="container-xl my-4 py-4 legacy-touch" style="max-width: 96% !important;">
            
            <!-- Header -->
            <div class="d-flex align-items-center justify-content-between mb-4 fade-in-up">
                <div class="page-header">
                    <h1 class="h2 mb-1">
                        <i class="bi bi-lightning-charge-fill"></i>
                        Planes HostPro
                    </h1>
                    <p class="text-secondary-custom mb-0" style="font-weight: 500;">Gestión de planes de hosting y VPS</p>
                </div>
                <div>
                    <button class="btn btn-hostpro" onclick="abrirModalPlan()">
                        <i class="bi bi-plus-circle-fill"></i> Nuevo Plan
                    </button>
                    <button class="btn btn-primary ms-2" onclick="abrirModalCaracteristicas()">
                        <i class="bi bi-sliders"></i> Características
                    </button>
                </div>
            </div>

            <!-- Filtros -->
            <div class="filter-section fade-in-up">
                <div class="row align-items-center">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Filtrar por tipo:</label>
                        <select class="form-select" id="filtroTipo" onchange="cargarPlanes()">
                            <option value="">Todos los tipos</option>
                            <option value="hosting">Hosting</option>
                            <option value="vps">VPS</option>
                        </select>
                    </div>
                    <div class="col-md-9 text-end">
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i> 
                            Los planes se muestran en el orden configurado
                        </small>
                    </div>
                </div>
            </div>

            <!-- Lista de planes -->
            <div id="listaPlanes" class="row"></div>
        </div>
    </div>

    <!-- Modal Crear/Editar Plan -->
    <div class="modal fade" id="modalPlan" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-box-seam-fill"></i> Gestión de Plan
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formPlan">
                        <input type="hidden" id="planId">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Nombre del Plan *</label>
                                <input type="text" class="form-control" id="planNombre" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Tipo *</label>
                                <select class="form-select" id="planTipo" required>
                                    <option value="hosting">Hosting</option>
                                    <option value="vps">VPS</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Icono</label>
                                <input type="text" class="form-control" id="planIcono" placeholder="🌐">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Precio MXN *</label>
                                <input type="number" step="0.01" class="form-control" id="planPrecio" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Precio USD</label>
                                <input type="number" step="0.01" class="form-control" id="planPrecioUSD">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Renovación MXN</label>
                                <input type="number" step="0.01" class="form-control" id="planPrecioRenovacion">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Renovación USD</label>
                                <input type="number" step="0.01" class="form-control" id="planPrecioRenovacionUSD">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Orden de visualización</label>
                                <input type="number" class="form-control" id="planOrden" value="0">
                                <small class="text-muted">Los planes se ordenan de menor a mayor número</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Descripción</label>
                            <textarea class="form-control" id="planDescripcion" rows="2"></textarea>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="planDestacado">
                                    <label class="form-check-label fw-bold" for="planDestacado">
                                        ⭐ Plan destacado
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="planActivo" checked>
                                    <label class="form-check-label fw-bold" for="planActivo">
                                        ✅ Activo
                                    </label>
                                </div>
                            </div>
                        </div>

                        <hr>
                        <h6 class="fw-bold">
                            <i class="bi bi-list-check"></i> Características del Plan
                        </h6>
                        <button type="button" class="btn btn-sm btn-success mb-2" onclick="agregarCaracteristica()">
                            <i class="bi bi-plus-circle"></i> Agregar Característica
                        </button>
                        <div id="listaCaracteristicasPlan"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarPlan()">
                        <i class="bi bi-save"></i> Guardar Plan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Gestión de Características -->
    <div class="modal fade" id="modalCaracteristicas" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-sliders"></i> Catálogo de Características
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-4">
                            <input type="text" class="form-control form-control-sm" id="nuevaCaracNombre" placeholder="Nombre">
                        </div>
                        <div class="col-3">
                            <select class="form-select form-select-sm" id="nuevaCaracTipo">
                                <option value="texto">Texto</option>
                                <option value="numero">Número</option>
                                <option value="boolean">Sí/No</option>
                            </select>
                        </div>
                        <div class="col-3">
                            <input type="text" class="form-control form-control-sm" id="nuevaCaracIcono" placeholder="Icono">
                        </div>
                        <div class="col-2">
                            <button class="btn btn-sm btn-success w-100" onclick="crearCaracteristica()">
                                <i class="bi bi-plus-circle"></i>
                            </button>
                        </div>
                    </div>
                    <div id="listaCaracteristicas"></div>
                </div>
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
</body>
</html>
