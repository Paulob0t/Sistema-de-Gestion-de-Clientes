// ==================== VARIABLES GLOBALES ====================
const API_PLANES = '/api/hostpro_planes.php';
const API_CARACTERISTICAS = '/api/hostpro_caracteristicas.php';
let caracteristicasCatalogo = [];
let planEditando = null;
let caracteristicasPlanTemp = [];

// ==================== CARGAR AL INICIO ====================
document.addEventListener('DOMContentLoaded', () => {
    cargarPlanes();
    cargarCaracteristicas();
});

// ==================== GESTIÓN DE PLANES ====================
async function cargarPlanes() {
    const tipo = document.getElementById('filtroTipo').value;
    const url = tipo ? `${API_PLANES}?tipo=${tipo}` : API_PLANES;
    
    try {
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success) {
            mostrarPlanes(data.data);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al cargar planes');
    }
}

function mostrarPlanes(planes) {
    const container = document.getElementById('listaPlanes');
    
    if (planes.length === 0) {
        container.innerHTML = '<div class="col-12"><p class="text-center text-muted">No hay planes registrados</p></div>';
        return;
    }
    
    container.innerHTML = planes.map(plan => `
        <div class="col-md-6 col-lg-4">
            <div class="card plan-card ${plan.activo == 0 ? 'opacity-50' : ''}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5 class="card-title mb-0">
                            ${plan.icono || ''} ${plan.nombre}
                            ${plan.destacado == 1 ? '<span class="badge bg-warning text-dark ms-2">⭐</span>' : ''}
                        </h5>
                        <span class="badge bg-${plan.tipo === 'vps' ? 'primary' : 'info'}">${plan.tipo.toUpperCase()}</span>
                    </div>
                    
                    <p class="text-muted small">${plan.descripcion || ''}</p>
                    
                    <div class="h4 text-success mb-3">
                        $${parseFloat(plan.precio).toLocaleString('es-MX', {minimumFractionDigits: 2})} MXN
                        ${plan.precio_usd > 0 ? `<br><small class="text-muted">$${parseFloat(plan.precio_usd).toFixed(2)} USD</small>` : ''}
                        ${plan.precio_renovacion > 0 ? `<br><small class="text-warning">Renovación: $${parseFloat(plan.precio_renovacion).toLocaleString('es-MX', {minimumFractionDigits: 2})} MXN</small>` : ''}
                    </div>
                    
                    <div class="mb-3">
                        ${plan.caracteristicas.map(c => `
                            <div class="caracteristica-item bg-light">
                                <small>${c.icono || ''} <strong>${c.nombre}:</strong> 
                                ${c.tipo_dato === 'boolean' ? (c.valor === 'true' || c.valor === '1' ? '✅ Sí' : '❌ No') : c.valor}
                                </small>
                            </div>
                        `).join('')}
                    </div>
                    
                    <div class="btn-group w-100">
                        <button class="btn btn-sm btn-outline-primary" onclick='editarPlan(${JSON.stringify(plan)})'>✏️ Editar</button>
                        <button class="btn btn-sm btn-outline-danger" onclick="eliminarPlan(${plan.id}, '${plan.nombre}')">🗑️</button>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

function abrirModalPlan(plan = null) {
    planEditando = plan;
    
    // Copiar características asegurando que tengan caracteristica_id
    if (plan && plan.caracteristicas) {
        caracteristicasPlanTemp = plan.caracteristicas.map(c => ({
            caracteristica_id: c.caracteristica_id || c.id,
            nombre: c.nombre,
            tipo_dato: c.tipo_dato,
            icono: c.icono,
            valor: c.valor
        }));
    } else {
        caracteristicasPlanTemp = [];
    }
    
    // Limpiar formulario
    document.getElementById('formPlan').reset();
    document.getElementById('planId').value = plan ? plan.id : '';
    
    if (plan) {
        document.getElementById('planNombre').value = plan.nombre;
        document.getElementById('planTipo').value = plan.tipo;
        document.getElementById('planPrecio').value = plan.precio;
        document.getElementById('planPrecioUSD').value = plan.precio_usd || 0;
        document.getElementById('planPrecioRenovacion').value = plan.precio_renovacion || 0;
        document.getElementById('planPrecioRenovacionUSD').value = plan.precio_renovacion_usd || 0;
        document.getElementById('planDescripcion').value = plan.descripcion || '';
        document.getElementById('planIcono').value = plan.icono || '';
        document.getElementById('planOrden').value = plan.orden;
        document.getElementById('planDestacado').checked = plan.destacado == 1;
        document.getElementById('planActivo').checked = plan.activo == 1;
    }
    
    renderizarCaracteristicasPlan();
    new bootstrap.Modal(document.getElementById('modalPlan')).show();
}

function editarPlan(plan) {
    abrirModalPlan(plan);
}

async function guardarPlan() {
    const id = document.getElementById('planId').value;
    const method = id ? 'PUT' : 'POST';
    const url = id ? `${API_PLANES}?id=${id}` : API_PLANES;
    
    // CAPTURAR valores actuales de los inputs ANTES de enviar
    capturarValoresCaracteristicas();
    
    const data = {
        nombre: document.getElementById('planNombre').value,
        tipo: document.getElementById('planTipo').value,
        precio: parseFloat(document.getElementById('planPrecio').value),
        precio_usd: parseFloat(document.getElementById('planPrecioUSD').value) || 0,
        precio_renovacion: parseFloat(document.getElementById('planPrecioRenovacion').value) || 0,
        precio_renovacion_usd: parseFloat(document.getElementById('planPrecioRenovacionUSD').value) || 0,
        descripcion: document.getElementById('planDescripcion').value,
        icono: document.getElementById('planIcono').value,
        orden: parseInt(document.getElementById('planOrden').value),
        destacado: document.getElementById('planDestacado').checked ? 1 : 0,
        activo: document.getElementById('planActivo').checked ? 1 : 0,
        caracteristicas: caracteristicasPlanTemp.map((c, idx) => ({
            id: c.caracteristica_id,
            valor: c.valor,
            orden: idx
        }))
    };
    
    console.log('📤 Enviando al servidor:', data);
    
    try {
        const response = await fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        console.log('📥 Respuesta del servidor:', result);
        
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalPlan')).hide();
            cargarPlanes();
            alert('✅ Plan guardado correctamente');
        } else {
            console.error('❌ Error del servidor:', result);
            alert('❌ Error: ' + (result.error || 'Error desconocido'));
        }
    } catch (error) {
        console.error('💥 Error de red/parsing:', error);
        alert('Error al guardar plan: ' + error.message);
    }
}

async function eliminarPlan(id, nombre) {
    if (!confirm(`¿Eliminar el plan "${nombre}"?`)) return;
    
    try {
        const response = await fetch(`${API_PLANES}?id=${id}`, { method: 'DELETE' });
        const result = await response.json();
        
        if (result.success) {
            cargarPlanes();
            alert('✅ Plan eliminado');
        } else {
            alert('❌ Error: ' + result.error);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al eliminar');
    }
}

// ==================== CARACTERÍSTICAS DEL PLAN ====================
function agregarCaracteristica() {
    if (caracteristicasCatalogo.length === 0) {
        alert('Primero debes crear características en el catálogo');
        return;
    }
    
    caracteristicasPlanTemp.push({
        caracteristica_id: caracteristicasCatalogo[0].id,
        nombre: caracteristicasCatalogo[0].nombre,
        tipo_dato: caracteristicasCatalogo[0].tipo_dato,
        icono: caracteristicasCatalogo[0].icono,
        valor: ''
    });
    
    renderizarCaracteristicasPlan();
}

function renderizarCaracteristicasPlan() {
    const container = document.getElementById('listaCaracteristicasPlan');
    
    if (caracteristicasPlanTemp.length === 0) {
        container.innerHTML = '<p class="text-muted small">Sin características</p>';
        return;
    }
    
    container.innerHTML = caracteristicasPlanTemp.map((c, idx) => `
        <div class="row mb-2 align-items-center">
            <div class="col-5">
                <select class="form-select form-select-sm" onchange="cambiarCaracteristica(${idx}, this.value)">
                    ${caracteristicasCatalogo.map(cat => 
                        `<option value="${cat.id}" ${cat.id == c.caracteristica_id ? 'selected' : ''}>
                            ${cat.icono || ''} ${cat.nombre}
                        </option>`
                    ).join('')}
                </select>
            </div>
            <div class="col-5">
                ${renderizarInputValor(c, idx)}
            </div>
            <div class="col-2">
                <button type="button" class="btn btn-sm btn-danger" onclick="eliminarCaracteristicaPlan(${idx})">🗑️</button>
            </div>
        </div>
    `).join('');
}

function renderizarInputValor(caracteristica, idx) {
    if (caracteristica.tipo_dato === 'boolean') {
        return `
            <select class="form-select form-select-sm" onchange="actualizarValorCaracteristica(${idx}, this.value)">
                <option value="true" ${caracteristica.valor === 'true' || caracteristica.valor === '1' ? 'selected' : ''}>✅ Sí</option>
                <option value="false" ${caracteristica.valor === 'false' || caracteristica.valor === '0' ? 'selected' : ''}>❌ No</option>
            </select>
        `;
    } else if (caracteristica.tipo_dato === 'numero') {
        return `<input type="number" class="form-control form-control-sm" value="${caracteristica.valor || ''}" 
                onchange="actualizarValorCaracteristica(${idx}, this.value)">`;
    } else {
        return `<input type="text" class="form-control form-control-sm" value="${caracteristica.valor || ''}" 
                onchange="actualizarValorCaracteristica(${idx}, this.value)" placeholder="Ej: 5 GB">`;
    }
}

function cambiarCaracteristica(idx, caracId) {
    const carac = caracteristicasCatalogo.find(c => c.id == caracId);
    caracteristicasPlanTemp[idx] = {
        caracteristica_id: carac.id,
        nombre: carac.nombre,
        tipo_dato: carac.tipo_dato,
        icono: carac.icono,
        valor: caracteristicasPlanTemp[idx].valor || ''
    };
    renderizarCaracteristicasPlan();
}

function actualizarValorCaracteristica(idx, valor) {
    caracteristicasPlanTemp[idx].valor = valor;
}

function eliminarCaracteristicaPlan(idx) {
    caracteristicasPlanTemp.splice(idx, 1);
    renderizarCaracteristicasPlan();
}

function capturarValoresCaracteristicas() {
    // Capturar valores actuales del DOM antes de guardar
    const container = document.getElementById('listaCaracteristicasPlan');
    const rows = container.querySelectorAll('.row.mb-2');
    
    rows.forEach((row, idx) => {
        if (caracteristicasPlanTemp[idx]) {
            // Buscar el input/select de valor en esta fila
            const valorInput = row.querySelector('.col-5:nth-child(2) input, .col-5:nth-child(2) select');
            if (valorInput) {
                caracteristicasPlanTemp[idx].valor = valorInput.value;
            }
            
            // También actualizar el caracteristica_id del select
            const caracSelect = row.querySelector('.col-5:first-child select');
            if (caracSelect) {
                const caracId = parseInt(caracSelect.value);
                const carac = caracteristicasCatalogo.find(c => c.id == caracId);
                if (carac) {
                    caracteristicasPlanTemp[idx].caracteristica_id = carac.id;
                    caracteristicasPlanTemp[idx].nombre = carac.nombre;
                    caracteristicasPlanTemp[idx].tipo_dato = carac.tipo_dato;
                    caracteristicasPlanTemp[idx].icono = carac.icono;
                }
            }
        }
    });
}

// ==================== CATÁLOGO DE CARACTERÍSTICAS ====================
async function cargarCaracteristicas() {
    try {
        const response = await fetch(API_CARACTERISTICAS);
        const data = await response.json();
        
        if (data.success) {
            caracteristicasCatalogo = data.data;
            mostrarCaracteristicas();
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

function abrirModalCaracteristicas() {
    mostrarCaracteristicas();
    new bootstrap.Modal(document.getElementById('modalCaracteristicas')).show();
}

function mostrarCaracteristicas() {
    const container = document.getElementById('listaCaracteristicas');
    
    if (caracteristicasCatalogo.length === 0) {
        container.innerHTML = `
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    No hay características registradas. Agrega la primera característica usando el formulario de arriba.
                </div>
            </div>
        `;
        return;
    }
    
    container.innerHTML = caracteristicasCatalogo.map(c => `
        <div class="col-md-6 col-lg-4">
            <div class="caracteristica-card" style="background: white !important; border-radius: 24px !important; border: 1px solid #e2e8f0 !important; padding: 1.25rem !important; position: relative; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05) !important; margin-bottom: 1rem !important;">
                <div style="content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #00e5ff, #a8ff3e);"></div>
                <div class="d-flex justify-content-between align-items-start mb-2" style="margin-top: 8px;">
                    <div class="d-flex align-items-center gap-3">
                        <div class="caracteristica-icon" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; background: #eef0ff; border-radius: 8px; font-size: 1.5rem;">
                            ${c.icono || '📋'}
                        </div>
                        <div>
                            <h6 class="mb-1 fw-bold" style="font-weight: 600 !important; color: #0f172a !important;">${c.nombre}</h6>
                            <span class="badge badge-tipo bg-secondary" style="font-size: 0.85em !important; padding: 0.4em 0.8em !important; font-weight: 600 !important;">
                                ${c.tipo_dato}
                            </span>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-outline-danger" 
                            onclick="eliminarCaracteristica(${c.id}, '${c.nombre.replace(/'/g, "\\'")}')"
                            style="background: #ef4444 !important; border-color: #ef4444 !important; color: white !important; border-radius: 8px !important; font-weight: 600 !important;">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `).join('');
}

async function crearCaracteristica() {
    const nombre = document.getElementById('nuevaCaracNombre').value.trim();
    const tipo = document.getElementById('nuevaCaracTipo').value;
    const icono = document.getElementById('nuevaCaracIcono').value.trim();
    
    if (!nombre) {
        alert('Ingresa el nombre');
        return;
    }
    
    try {
        const response = await fetch(API_CARACTERISTICAS, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                nombre: nombre,
                tipo_dato: tipo,
                icono: icono,
                orden_global: caracteristicasCatalogo.length
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            document.getElementById('nuevaCaracNombre').value = '';
            document.getElementById('nuevaCaracIcono').value = '';
            cargarCaracteristicas();
        } else {
            alert('Error: ' + result.error);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al crear característica');
    }
}

async function eliminarCaracteristica(id, nombre) {
    if (!confirm(`¿Eliminar "${nombre}"?`)) return;
    
    try {
        const response = await fetch(`${API_CARACTERISTICAS}?id=${id}`, { method: 'DELETE' });
        const result = await response.json();
        
        if (result.success) {
            cargarCaracteristicas();
        } else {
            alert('Error: ' + result.error);
        }
    } catch (error) {
        console.error('Error:', error);
    }
}
