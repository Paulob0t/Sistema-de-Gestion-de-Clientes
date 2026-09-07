<?php
header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set("display_errors", 1);
include "menu.php";
include_once "conn_hostingpro.php";

// Verificar que existe la conexión (planes de ConlineWeb están en la misma BD que hostpro)
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
        /* ===== ESTILOS ESTANDARIZADOS DEL ADMIN + PLANPRO ===== */
        
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        
        :root {
            --primary-dark: #000147;
            --primary: #1a1f6b;
            --primary-light: #2d3388;
            --primary-soft: #eef0ff;
            --planpro-green: #a8ff3e;
            --planpro-blue: #00e5ff;
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
            background: linear-gradient(90deg, var(--planpro-blue), var(--planpro-green));
        }
        
        .plan-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px -12px rgba(168, 255, 62, 0.3);
        }
        
        .plan-card-destacado {
            border: 2px solid var(--planpro-green) !important;
            box-shadow: 0 8px 24px rgba(168, 255, 62, 0.2) !important;
        }
        
        .plan-card-destacado::before {
            height: 6px;
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
            background: #fef2f2;
            color: #dc2626;
        }
        
        .badge-destacado {
            background: linear-gradient(135deg, #a8ff3e 0%, #00e5ff 100%);
            color: #000147;
            font-weight: 700;
            padding: 8px 16px;
        }
        
        /* Botones */
        .btn-planpro {
            background: linear-gradient(135deg, var(--planpro-green), var(--planpro-blue)) !important;
            color: var(--primary-dark) !important;
            border: none !important;
            padding: 12px 24px !important;
            border-radius: 12px !important;
            font-weight: 600 !important;
            transition: all 0.3s !important;
            box-shadow: 0 4px 12px rgba(168, 255, 62, 0.25) !important;
        }
        
        .btn-planpro:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(168, 255, 62, 0.4) !important;
        }
        
        .btn-primary {
            background: var(--primary-dark) !important;
            border: none !important;
            padding: 12px 24px !important;
            border-radius: 12px !important;
            font-weight: 600 !important;
        }
        
        .btn-danger-soft {
            background: #fee2e2 !important;
            color: #dc2626 !important;
            border: none !important;
            padding: 8px 16px !important;
            border-radius: 10px !important;
            font-weight: 500 !important;
        }
        
        .btn-warning-soft {
            background: #fef3c7 !important;
            color: #d97706 !important;
            border: none !important;
            padding: 8px 16px !important;
            border-radius: 10px !important;
            font-weight: 500 !important;
        }
        
        .btn-success-soft {
            background: #dcfce7 !important;
            color: #15803d !important;
            border: none !important;
            padding: 8px 16px !important;
            border-radius: 10px !important;
            font-weight: 500 !important;
        }
        
        .btn-sm {
            padding: 6px 12px !important;
            font-size: 13px !important;
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
            border-color: var(--planpro-green);
            box-shadow: 0 0 0 3px rgba(168, 255, 62, 0.15);
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
        
        .page-header h1 .bi-globe2 {
            color: var(--planpro-green);
            filter: drop-shadow(0 0 8px rgba(168, 255, 62, 0.5));
        }
        
        /* Plan Card Content */
        .plan-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .plan-name {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 0.5rem;
            line-height: 1.2;
        }
        
        .plan-category {
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .plan-price {
            font-size: 2.25rem;
            font-weight: 800;
            color: var(--primary-dark);
            margin-bottom: 0.25rem;
        }
        
        .plan-price-original {
            font-size: 1rem;
            text-decoration: line-through;
            color: var(--text-muted);
            margin-left: 0.5rem;
        }
        
        .plan-periodicidad {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }
        
        .plan-ahorro {
            display: inline-block;
            background: #dcfce7;
            color: #15803d;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .plan-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        /* Características */
        .caracteristicas-list {
            list-style: none;
            padding: 0;
            margin: 1rem 0;
        }
        
        .caracteristicas-list li {
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border);
            font-size: 0.875rem;
        }
        
        .caracteristicas-list li:last-child {
            border-bottom: none;
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
    </style>
</head>
<body>

<!-- Content Wrapper -->
<div id="content-wrapper" class="d-flex flex-column">
    <!-- Main Content -->
    <div id="content">
        <div class="container-xl my-4 py-4 legacy-touch" style="max-width: 96% !important;">
            
            <!-- Header -->
            <div class="d-flex align-items-center justify-content-between mb-4 fade-in-up">
                <div class="page-header">
                    <h1 class="h2 mb-1">
                        <i class="bi bi-globe2"></i>
                        Planes ConlineWeb
                    </h1>
                    <p class="text-secondary-custom mb-0" style="font-weight: 500;">Gestión de planes de desarrollo web</p>
                </div>
                <div>
                    <button class="btn btn-planpro" onclick="abrirModalPlan()">
                        <i class="bi bi-plus-circle-fill"></i> Nuevo Plan
                    </button>
                </div>
            </div>

            <!-- Filtros -->
            <div class="filter-section fade-in-up">
                <div class="row align-items-center">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Filtrar por categoría:</label>
                        <select class="form-select" id="filtroCategoria" onchange="cargarPlanes()">
                            <option value="">Todas las categorías</option>
                            <option value="plan_basico">Plan Básico</option>
                            <option value="plan_pro">Plan PRO</option>
                            <option value="plan_premium">Plan Premium</option>
                            <option value="ecommerce">E-commerce</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Estado:</label>
                        <select class="form-select" id="filtroActivo" onchange="cargarPlanes()">
                            <option value="">Todos</option>
                            <option value="1" selected>Activos</option>
                            <option value="0">Inactivos</option>
                        </select>
                    </div>
                    <div class="col-md-6 text-end">
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i> 
                            Los planes se muestran tal como aparecen en conlineweb.com/plan_pro.php
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
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Nombre del Plan *</label>
                                <input type="text" class="form-control" id="planNombre" required>
                                <small class="text-muted">Ej: Plan Básico, Plan Premium, etc.</small>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Categoría *</label>
                                <select class="form-select" id="planCategoria" required>
                                    <option value="">Seleccionar...</option>
                                    <option value="plan_basico">Plan Básico</option>
                                    <option value="plan_pro">Plan PRO</option>
                                    <option value="plan_premium">Plan Premium</option>
                                    <option value="ecommerce">E-commerce</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Periodicidad *</label>
                                <select class="form-select" id="planPeriodicidad" required>
                                    <option value="">Seleccionar...</option>
                                    <option value="mensual">Mensual</option>
                                    <option value="trimestral">Trimestral</option>
                                    <option value="semestral">Semestral</option>
                                    <option value="anual">Anual</option>
                                    <option value="unico">Pago Único</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Precio Original (MXN) *</label>
                                <input type="number" step="0.01" class="form-control" id="planPrecioOriginal" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Precio Actual (MXN) *</label>
                                <input type="number" step="0.01" class="form-control" id="planPrecioActual" required>
                                <small class="text-muted">Precio con descuento</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Ahorro (%)</label>
                                <input type="number" step="0.01" class="form-control" id="planAhorro" value="0">
                                <small class="text-muted">Ej: 20 para 20%</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Descripción Corta</label>
                            <textarea class="form-control" id="planDescripcion" rows="2" placeholder="Describe brevemente el plan..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Características</label>
                            <textarea class="form-control" id="planCaracteristicas" rows="8" placeholder="Diseño responsive
Hosting gratis 1 año
Correos corporativos
Soporte 24/7"></textarea>
                            <small class="text-muted">Escribe una característica por línea</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Orden de visualización</label>
                            <input type="number" class="form-control" id="planOrden" value="0">
                            <small class="text-muted">Los planes se ordenan de menor a mayor número</small>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="planPopular">
                                    <label class="form-check-label fw-bold" for="planPopular">
                                        ⭐ Popular
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="planOferta">
                                    <label class="form-check-label fw-bold" for="planOferta">
                                        🔥 Oferta
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="planActivo" checked>
                                    <label class="form-check-label fw-bold" for="planActivo">
                                        ✅ Activo
                                    </label>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-planpro" onclick="guardarPlan()">
                        <i class="bi bi-save"></i> Guardar Plan
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let modalPlan = null;

document.addEventListener('DOMContentLoaded', function() {
    modalPlan = new bootstrap.Modal(document.getElementById('modalPlan'));
    cargarPlanes();
});

// ==================== CARGAR PLANES ====================
function cargarPlanes() {
    const categoria = document.getElementById('filtroCategoria').value;
    const activo = document.getElementById('filtroActivo').value;
    
    let url = '/api/planpro_planes.php?';
    if (categoria) url += `categoria=${categoria}&`;
    if (activo !== '') url += `activo=${activo}`;
    
    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderizarPlanes(data.data);
            } else {
                Swal.fire('Error', data.error || 'Error al cargar planes', 'error');
            }
        })
        .catch(err => {
            console.error('Error:', err);
            Swal.fire('Error', 'Error de conexión', 'error');
        });
}

// ==================== RENDERIZAR PLANES ====================
function renderizarPlanes(planes) {
    const container = document.getElementById('listaPlanes');
    
    if (planes.length === 0) {
        container.innerHTML = `
            <div class="col-12 text-center py-5">
                <i class="bi bi-inbox" style="font-size: 3rem; color: var(--text-muted);"></i>
                <p class="text-muted mt-3">No hay planes para mostrar</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = planes.map(plan => {
        const caracteristicas = plan.caracteristicas ? JSON.parse(plan.caracteristicas) : [];
        const popularBadge = plan.es_popular == 1 ? '<span class="badge-status badge-active"><i class="bi bi-star-fill"></i> Popular</span>' : '';
        const ofertaBadge = plan.tiene_oferta == 1 ? '<span style="background: #fef3c7; color: #d97706; padding: 6px 14px; border-radius: 30px; font-size: 12px; font-weight: 600;"><i class="bi bi-fire"></i> Oferta</span>' : '';
        const estadoBadge = plan.activo == 1 ? '<span class="badge-status badge-active">Activo</span>' : '<span class="badge-status badge-inactive">Inactivo</span>';
        
        return `
            <div class="col-md-6 col-lg-4 mb-4 fade-in-up">
                <div class="plan-card ${plan.es_popular == 1 ? 'plan-card-destacado' : ''}">
                    <div class="p-4">
                        <!-- Badges superiores -->
                        <div class="d-flex justify-content-end mb-3" style="gap: 0.5rem;">
                            ${popularBadge}
                            ${ofertaBadge}
                        </div>
                        
                        <!-- Título del plan GRANDE -->
                        <div class="plan-name text-center" style="font-size: 1.75rem; margin-bottom: 0.5rem; font-weight: 800;">${plan.nombre_plan}</div>
                        <div class="plan-category text-center" style="text-transform: uppercase; font-size: 0.75rem; letter-spacing: 1px; margin-bottom: 1.5rem;">${plan.categoria || 'Sin categoría'}</div>
                        
                        <!-- Precio centrado -->
                        <div class="text-center mb-2">
                            <div class="plan-price" style="display: inline-block;">$${parseFloat(plan.precio_actual).toFixed(2)}</div>
                            ${plan.precio_original > plan.precio_actual ? `<div class="plan-price-original" style="display: inline-block;">$${parseFloat(plan.precio_original).toFixed(2)}</div>` : ''}
                        </div>
                        
                        <div class="plan-periodicidad text-center">${plan.periodicidad || 'Pago único'}</div>
                        
                        ${plan.ahorro > 0 ? `<div class="text-center mb-3"><span class="plan-ahorro"><i class="bi bi-tag-fill"></i> Ahorra ${plan.ahorro}%</span></div>` : ''}
                        
                        ${plan.descripcion_corta ? `<p class="text-secondary-custom text-center mb-3" style="font-size: 0.875rem;">${plan.descripcion_corta}</p>` : ''}
                        
                        ${caracteristicas.length > 0 ? `
                            <ul class="caracteristicas-list">
                                ${caracteristicas.slice(0, 3).map(c => `<li><i class="bi bi-check-circle-fill" style="color: var(--success);"></i> ${c}</li>`).join('')}
                                ${caracteristicas.length > 3 ? `<li class="text-muted"><i>+${caracteristicas.length - 3} más...</i></li>` : ''}
                            </ul>
                        ` : ''}
                        
                        <hr style="margin: 1.5rem 0; border-color: var(--border);">
                        
                        <!-- Estado -->
                        <div class="text-center mb-3">
                            ${estadoBadge}
                        </div>
                        
                        <div class="plan-actions mt-2">
                            <button class="btn btn-warning-soft btn-sm" onclick="editarPlan(${plan.id_plan})">
                                <i class="bi bi-pencil-fill"></i> Editar
                            </button>
                            <button class="btn ${plan.activo == 1 ? 'btn-danger-soft' : 'btn-success-soft'} btn-sm" onclick="toggleEstado(${plan.id_plan}, ${plan.activo})">
                                <i class="bi bi-${plan.activo == 1 ? 'x-circle' : 'check-circle'}"></i> ${plan.activo == 1 ? 'Desactivar' : 'Activar'}
                            </button>
                            <button class="btn btn-danger-soft btn-sm" onclick="eliminarPlan(${plan.id_plan})">
                                <i class="bi bi-trash-fill"></i> Eliminar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

// ==================== ABRIR MODAL PLAN ====================
function abrirModalPlan(planId = null) {
    document.getElementById('formPlan').reset();
    document.getElementById('planId').value = '';
    document.getElementById('planActivo').checked = true;
    
    if (planId) {
        // Cargar datos del plan
        fetch(`/api/planpro_planes.php?id=${planId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.data) {
                    const plan = data.data;
                    document.getElementById('planId').value = plan.id_plan;
                    document.getElementById('planNombre').value = plan.nombre_plan;
                    document.getElementById('planCategoria').value = plan.categoria || '';
                    document.getElementById('planPeriodicidad').value = plan.periodicidad || '';
                    document.getElementById('planPrecioOriginal').value = plan.precio_original;
                    document.getElementById('planPrecioActual').value = plan.precio_actual;
                    document.getElementById('planAhorro').value = plan.ahorro || 0;
                    document.getElementById('planDescripcion').value = plan.descripcion_corta || '';
                    
                    // Convertir JSON a líneas simples
                    let caracteristicasTexto = '';
                    try {
                        const caracArray = JSON.parse(plan.caracteristicas || '[]');
                        caracteristicasTexto = caracArray.join('\n');
                    } catch(e) {
                        caracteristicasTexto = plan.caracteristicas || '';
                    }
                    document.getElementById('planCaracteristicas').value = caracteristicasTexto;
                    
                    document.getElementById('planOrden').value = plan.orden || 0;
                    document.getElementById('planPopular').checked = plan.es_popular == 1;
                    document.getElementById('planOferta').checked = plan.tiene_oferta == 1;
                    document.getElementById('planActivo').checked = plan.activo == 1;
                }
            });
    }
    
    modalPlan.show();
}

// ==================== GUARDAR PLAN ====================
function guardarPlan() {
    const form = document.getElementById('formPlan');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const planId = document.getElementById('planId').value;
    
    // Convertir líneas simples a JSON array
    const caracteristicasTexto = document.getElementById('planCaracteristicas').value;
    const caracteristicasArray = caracteristicasTexto.split('\n').filter(line => line.trim() !== '');
    const caracteristicasJSON = JSON.stringify(caracteristicasArray);
    
    const data = {
        nombre_plan: document.getElementById('planNombre').value,
        categoria: document.getElementById('planCategoria').value,
        periodicidad: document.getElementById('planPeriodicidad').value,
        precio_original: parseFloat(document.getElementById('planPrecioOriginal').value),
        precio_actual: parseFloat(document.getElementById('planPrecioActual').value),
        ahorro: parseFloat(document.getElementById('planAhorro').value) || 0,
        descripcion_corta: document.getElementById('planDescripcion').value,
        caracteristicas: caracteristicasJSON,
        orden: parseInt(document.getElementById('planOrden').value) || 0,
        es_popular: document.getElementById('planPopular').checked ? 1 : 0,
        tiene_oferta: document.getElementById('planOferta').checked ? 1 : 0,
        activo: document.getElementById('planActivo').checked ? 1 : 0
    };
    
    const method = planId ? 'PUT' : 'POST';
    const url = planId ? `/api/planpro_planes.php?id=${planId}` : '/api/planpro_planes.php';
    
    fetch(url, {
        method: method,
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(response => {
        if (response.success) {
            Swal.fire('Éxito', planId ? 'Plan actualizado correctamente' : 'Plan creado correctamente', 'success');
            modalPlan.hide();
            cargarPlanes();
        } else {
            Swal.fire('Error', response.error || 'Error al guardar', 'error');
        }
    })
    .catch(err => {
        console.error('Error:', err);
        Swal.fire('Error', 'Error de conexión', 'error');
    });
}

// ==================== EDITAR PLAN ====================
function editarPlan(planId) {
    abrirModalPlan(planId);
}

// ==================== TOGGLE ESTADO ====================
function toggleEstado(planId, estadoActual) {
    const nuevoEstado = estadoActual == 1 ? 0 : 1;
    
    fetch(`/api/planpro_planes.php?id=${planId}`, {
        method: 'PUT',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ activo: nuevoEstado })
    })
    .then(res => res.json())
    .then(response => {
        if (response.success) {
            Swal.fire('Éxito', `Plan ${nuevoEstado == 1 ? 'activado' : 'desactivado'} correctamente`, 'success');
            cargarPlanes();
        } else {
            Swal.fire('Error', response.error || 'Error al cambiar estado', 'error');
        }
    });
}

// ==================== ELIMINAR PLAN ====================
function eliminarPlan(planId) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/api/planpro_planes.php?id=${planId}`, {
                method: 'DELETE'
            })
            .then(res => res.json())
            .then(response => {
                if (response.success) {
                    Swal.fire('Eliminado', 'Plan eliminado correctamente', 'success');
                    cargarPlanes();
                } else {
                    Swal.fire('Error', response.error || 'Error al eliminar', 'error');
                }
            });
        }
    });
}
</script>

</body>
</html>
