<?php require_once __DIR__ . '/../auth_middleware.php'; ?>
<?php require_once __DIR__ . '/db/conexion.php'; ?>
<?php require_once __DIR__ . '/helpers_cotizacion.php'; ?>
<?php
$preselectedIds = isset($_GET['ids']) ? array_map('intval', explode(',', $_GET['ids'])) : [];
$preselectedIds = array_filter($preselectedIds, fn($id) => $id > 0);
?>
<?php include __DIR__ . '/../menu.php'; ?>

<style>
.card{
  background:#fff;border-radius:16px;
  box-shadow:0 1px 3px rgba(0,0,0,0.03),0 4px 20px rgba(0,0,0,0.04);
  border:1px solid #e9ecef;padding:22px;margin-bottom:20px;
}
.card h2{font-size:1.1rem;color:#000147;font-weight:700;margin-bottom:14px;display:flex;align-items:center;gap:8px}
.btn-primary{
  background:linear-gradient(135deg,#000147,#1a1a8a);
  color:#fff;border:none;padding:10px 22px;border-radius:12px;
  cursor:pointer;font-weight:600;font-size:0.9rem;
  display:inline-flex;align-items:center;gap:8px;
  transition:all .25s cubic-bezier(.4,0,.2,1);
  box-shadow:0 4px 14px rgba(0,1,71,0.2);
  white-space:nowrap;
}
.btn-primary:hover{transform:translateY(-3px);box-shadow:0 8px 25px rgba(0,1,71,0.3)}
.btn-primary:disabled{opacity:.6;cursor:not-allowed;transform:none}
.btn-success{
  background:linear-gradient(135deg,#10b981,#059669);
  color:#fff;border:none;padding:10px 22px;border-radius:12px;
  cursor:pointer;font-weight:600;font-size:0.9rem;
  display:inline-flex;align-items:center;gap:8px;
  transition:all .25s;
  box-shadow:0 4px 14px rgba(16,185,129,0.3);
}
.btn-success:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(16,185,129,0.4)}
.btn-secondary{
  background:rgba(67,97,238,0.08);color:#4361ee;
  border:1px solid rgba(67,97,238,0.15);
  padding:8px 16px;border-radius:10px;cursor:pointer;
  font-weight:600;font-size:0.85rem;
  transition:all .2s;
}
.btn-secondary:hover{background:rgba(67,97,238,0.15);transform:translateY(-1px)}
.btn-danger{
  background:linear-gradient(135deg,#f72585,#b5179e);color:#fff;border:none;
  padding:8px 16px;border-radius:10px;cursor:pointer;
  font-weight:600;font-size:0.85rem;transition:all .2s;
  display:inline-flex;align-items:center;gap:6px;
}
.btn-danger:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(247,37,133,0.3)}
.btn-sm{padding:6px 12px;font-size:0.8rem}
.flex{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.flex-between{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
.mt-2{margin-top:12px}
.mb-2{margin-bottom:12px}
.text-center{text-align:center}
.text-right{text-align:right}
.text-muted{color:#64748b;font-size:0.85rem}
.font-bold{font-weight:700}
.text-success{color:#10b981}
.text-danger{color:#f72585}

.filters{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px}
.filter-select{
  padding:8px 12px;border-radius:10px;border:1px solid #e2e8f0;
  flex:1;min-width:160px;font-size:0.82rem;
  background:#f8fafc;color:#374151;
}
.filter-select:focus{outline:none;border-color:#4361ee;background:#fff;box-shadow:0 0 0 3px rgba(67,97,238,0.08)}

.table-responsive{width:100%;overflow-x:auto;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.04)}
th{
  font-size:0.72rem;letter-spacing:0.5px;text-transform:uppercase;
  padding:10px 10px;background:#000147;color:#fff;font-weight:700;
  position:sticky;top:0;
}
td{padding:10px 10px;font-size:0.85rem;border-bottom:1px solid #f1f3f5}
tr:hover{background:rgba(67,97,238,0.04)}
.badge{
  padding:4px 10px;border-radius:20px;font-size:0.75rem;font-weight:600;
  display:inline-block;text-align:center;min-width:65px;
}
.badge-activa{background:#ecfdf5;color:#065f46;border:1px solid rgba(16,185,129,0.15)}
.badge-cancelada{background:#fef2f2;color:#991b1b;border:1px solid rgba(239,68,68,0.15)}
.action-btn{background:none;border:none;cursor:pointer;width:30px;height:30px;border-radius:8px;transition:all .15s;font-size:0.85rem;display:inline-flex;align-items:center;justify-content:center;margin:0 1px}
.action-btn:hover{background:#f1f3f8}
.btn-view:hover{background:rgba(67,97,238,0.12)!important;color:#4361ee}
.btn-pdf:hover{background:rgba(239,68,68,0.12)!important;color:#dc2626}

.modal-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.45);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;z-index:1000;padding:15px;opacity:0;visibility:hidden;transition:all .3s}
.modal-overlay.active{opacity:1;visibility:visible}
.modal{background:#fff;border-radius:16px;width:100%;max-width:95%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.15);transform:translateY(30px);transition:transform .4s cubic-bezier(.22,.68,0,1)}
.modal-overlay.active .modal{transform:translateY(0)}
.modal-xl{max-width:1200px}
.modal-header{padding:16px 20px;border-bottom:1px solid #f1f3f5;display:flex;justify-content:space-between;align-items:center}
.modal-title{font-size:1.2rem;color:#000147;font-weight:700}
.modal-close{background:none;border:none;font-size:1.5rem;cursor:pointer;color:#94a3b8;transition:color .2s}
.modal-close:hover{color:#1e293b}
.modal-body{padding:20px}
.modal-footer{padding:14px 20px;border-top:1px solid #f1f3f5;display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap}
.form-group{margin-bottom:14px}
.form-label{display:block;margin-bottom:5px;font-weight:600;color:#1e293b;font-size:0.85rem}
.form-control{width:100%;padding:9px 12px;border:1px solid #e2e8f0;border-radius:10px;font-size:0.85rem;transition:border-color .2s}
.form-control:focus{outline:none;border-color:#4361ee;box-shadow:0 0 0 3px rgba(67,97,238,0.12)}

.quote-preview{
  background:#fff;border-radius:16px;border:2px solid #e9ecef;overflow:hidden;
}
.quote-header{
  background:linear-gradient(135deg,#000147,#04055c);padding:30px 30px 20px;
  color:#fff;
}
.quote-header h2{font-size:1.6rem;font-weight:800;margin:0;color:#fff}
.quote-header .folio{font-size:0.9rem;color:rgba(255,255,255,0.7)}
.quote-client-info{padding:16px 30px;background:#f8fafc;border-bottom:1px solid #e9ecef}
.quote-client-info .row{display:flex;gap:20px;flex-wrap:wrap}
.quote-client-info .field{flex:1;min-width:180px}
.quote-client-info .label{font-size:0.75rem;text-transform:uppercase;color:#64748b;font-weight:600;letter-spacing:0.3px}
.quote-client-info .value{font-size:0.95rem;color:#1e293b;font-weight:500;margin-top:2px}
.quote-table{width:100%;border-collapse:collapse;margin:0}
.quote-table th{background:#f1f5f9;color:#000147;font-size:0.75rem;padding:10px 12px;text-align:left;border-bottom:2px solid #e2e8f0}
.quote-table td{padding:10px 12px;border-bottom:1px solid #f1f3f5;font-size:0.85rem}
.quote-table tr:nth-child(even){background:#f8fafc}
.quote-totals{padding:16px 30px;background:#f8fafc;border-top:2px solid #e2e8f0;margin-top:0}
.quote-totals .row{display:flex;justify-content:flex-end;gap:30px}
.quote-totals .totals-table{width:auto;min-width:300px;margin-left:auto}
.quote-totals .totals-table td{padding:4px 8px;border:none;font-size:0.9rem}
.quote-totals .totals-table .grand-total td{font-size:1.3rem;font-weight:800;color:#000147;border-top:2px solid #000147;padding-top:8px}
.quote-totals .discount{color:#f72585;font-weight:600}
.quote-notas{padding:16px 30px;font-size:0.85rem;color:#64748b;border-top:1px solid #e9ecef}
.quote-loader{text-align:center;padding:60px 20px}
.quote-loader .spinner{width:48px;height:48px;border:4px solid #e2e8f0;border-top-color:#000147;border-radius:50%;animation:spin .8s linear infinite;margin:0 auto 16px}
@keyframes spin{to{transform:rotate(360deg)}}
.quote-loader p{color:#64748b;font-size:0.95rem}
</style>

<div class="adm-page-shell">
<div class="container-fluid px-0">
<?php
$admPageTitle = 'Cotizaciones Inteligentes';
$admPageSubtitle = 'Generación automática con IA y historial de propuestas';
$admPageIcon = 'fas fa-file-invoice';
$admPageActions = '<span class="adm-page-meta"><i class="fas fa-robot"></i> Generación automática con IA</span>'
    . '<button class="btn-primary" id="btnNuevaCotizacion"><i class="fas fa-plus"></i> Generar Cotización IA</button>';
include __DIR__ . '/../includes/adm_page_header.php';
?>

<div class="card" id="listaCotizacionesCard">
  <div class="flex-between mb-2">
    <h2><i class="fas fa-history"></i> Cotizaciones Generadas</h2>
    <div class="flex">
      <select class="filter-select" id="filterEstatus" style="flex:none;min-width:140px">
        <option value="Activa">Activas</option>
        <option value="Todas">Todas</option>
        <option value="Cancelada">Canceladas</option>
      </select>
      <button class="btn-secondary btn-sm" onclick="cargarListaCotizaciones()"><i class="fas fa-sync"></i></button>
    </div>
  </div>
  <div class="table-responsive">
    <table class="display" style="width:100%">
      <thead>
        <tr>
          <th>Folio</th>
          <th>Cliente</th>
          <th>Proyecto</th>
          <th>Subtotal</th>
          <th>Dto.</th>
          <th>Total</th>
          <th>Estatus</th>
          <th>Fecha</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody id="tbodyCotizaciones">
        <tr><td colspan="9" class="text-center text-muted">Cargando...</td></tr>
      </tbody>
    </table>
  </div>
</div>
</div>

<div class="modal-overlay" id="modalGenerar">
  <div class="modal modal-xl">
    <div class="modal-header">
      <span class="modal-title"><i class="fas fa-robot"></i> Generar Cotizaci&oacute;n con IA</span>
      <button type="button" class="modal-close" onclick="cerrarModal('modalGenerar')">&times;</button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label class="form-label">Seleccionar Solicitudes (Tickets)</label>
        <select class="form-control" id="selectSolicitudes" multiple style="min-height:150px">
          <?php
          $solRes = $conexion->query("
            SELECT s.id, s.titulo, c.empresa AS cliente
            FROM solicitudes s
            LEFT JOIN clientes c ON s.id_cliente = c.id
            WHERE s.estado != 'Finalizado'
            ORDER BY s.id DESC
          ");
          while ($s = $solRes->fetch_assoc()) {
            $sel = in_array((int)$s['id'], $preselectedIds) ? ' selected' : '';
            echo '<option value="' . (int)$s['id'] . '"' . $sel . '>'
               . '#' . $s['id'] . ' - ' . htmlspecialchars($s['titulo'], ENT_QUOTES)
               . (!empty($s['cliente']) ? ' (' . htmlspecialchars($s['cliente'], ENT_QUOTES) . ')' : '')
               . '</option>';
          }
          ?>
        </select>
        <div class="text-muted mt-2" style="font-size:0.8rem">
          <i class="fas fa-info-circle"></i> Mant&eacute;n presionado Ctrl/Cmd para seleccionar m&uacute;ltiples tickets.
        </div>
      </div>
      <div id="quoteResult"></div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn-secondary" onclick="cerrarModal('modalGenerar')"><i class="fas fa-times"></i> Cancelar</button>
      <button type="button" class="btn-success" id="btnGenerarCotizacion" onclick="generarCotizacion()">
        <i class="fas fa-magic"></i> Generar Cotizaci&oacute;n IA
      </button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modalVerCotizacion">
  <div class="modal modal-xl">
    <div class="modal-header">
      <span class="modal-title" id="verTitulo"><i class="fas fa-file-invoice"></i> Cotizaci&oacute;n</span>
      <button type="button" class="modal-close" onclick="cerrarModal('modalVerCotizacion')">&times;</button>
    </div>
    <div class="modal-body" id="verBody">
    </div>
    <div class="modal-footer">
      <button type="button" class="btn-secondary" onclick="cerrarModal('modalVerCotizacion')"><i class="fas fa-times"></i> Cerrar</button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const preselectedIds = <?php echo json_encode(array_values($preselectedIds)); ?>;

document.addEventListener('DOMContentLoaded', function() {
    cargarListaCotizaciones();

    document.getElementById('btnNuevaCotizacion').addEventListener('click', function() {
        abrirModalGenerar();
    });

    document.getElementById('filterEstatus').addEventListener('change', function() {
        cargarListaCotizaciones();
    });

    if (preselectedIds.length > 0) {
        abrirModalGenerar();
    }
});

function abrirModalGenerar() {
    document.getElementById('quoteResult').innerHTML = '';
    document.getElementById('modalGenerar').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function cerrarModal(id) {
    document.getElementById(id).classList.remove('active');
    document.body.style.overflow = 'auto';
}

async function generarCotizacion() {
    const select = document.getElementById('selectSolicitudes');
    const selected = Array.from(select.selectedOptions).map(o => parseInt(o.value));
    const btn = document.getElementById('btnGenerarCotizacion');

    if (selected.length === 0) {
        Swal.fire({icon: 'warning', title: 'Selecciona tickets', text: 'Debes seleccionar al menos una solicitud.'});
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analizando con IA...';

    document.getElementById('quoteResult').innerHTML = `
        <div class="quote-loader">
            <div class="spinner"></div>
            <p><strong>Analizando solicitudes con inteligencia artificial...</strong></p>
            <p class="text-muted">La IA est&aacute; calculando precios de mercado en M&eacute;xico para cada ticket.</p>
        </div>
    `;

    try {
        const res = await fetch('api_generar.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ids: selected})
        });
        const data = await res.json();

        if (!data.success) {
            document.getElementById('quoteResult').innerHTML = `
                <div style="padding:16px;background:#fef2f2;border:1px solid #fecaca;border-radius:12px;color:#991b1b">
                    <i class="fas fa-exclamation-triangle"></i> ${data.error || 'Error al generar cotización'}
                </div>`;
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-magic"></i> Generar Cotización IA';
            return;
        }

        mostrarVistaPrevia(data.cotizacion);
        await cargarListaCotizaciones();

        Swal.fire({
            icon: 'success',
            title: 'Cotizaci&oacute;n generada',
            text: 'Folio: ' + data.cotizacion.folio,
            timer: 2500,
            showConfirmButton: false
        });

    } catch (e) {
        document.getElementById('quoteResult').innerHTML = `
            <div style="padding:16px;background:#fef2f2;border:1px solid #fecaca;border-radius:12px;color:#991b1b">
                <i class="fas fa-exclamation-triangle"></i> Error de conexi&oacute;n: ${e.message}
            </div>`;
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-magic"></i> Generar Cotización IA';
}

function mostrarVistaPrevia(cot) {
    let itemsHtml = '';
    cot.items.forEach((item, idx) => {
        itemsHtml += `<tr>
            <td>${idx + 1}</td>
            <td>${item.descripcion_servicio || item.titulo || ''}</td>
            <td class="text-center">${item.cantidad || 1}</td>
            <td class="text-center">${item.unidad || 'pza'}</td>
            <td class="text-right">$${Number(item.precio_unitario).toLocaleString('es-MX', {minimumFractionDigits:2})}</td>
            <td class="text-right font-bold">$${Number(item.total).toLocaleString('es-MX', {minimumFractionDigits:2})}</td>
        </tr>`;
    });

    const subtotal = Number(cot.subtotal);
    const descMonto = Number(cot.descuento_monto);
    const descPorc = Number(cot.descuento_porcentaje);
    const total = Number(cot.total);
    const iva = Math.round(total * 0.16 * 100) / 100;
    const totalConIva = total + iva;

    const html = `
        <div class="quote-preview">
            <div class="quote-header">
                <div class="flex-between">
                    <div>
                        <h2>COTIZACI&Oacute;N</h2>
                        <div class="folio">Folio: <strong>${cot.folio}</strong></div>
                    </div>
                    <div style="color:rgba(255,255,255,0.7);font-size:0.85rem;text-align:right">
                        <div>${cot.fecha || ''}</div>
                    </div>
                </div>
            </div>
            <div class="quote-client-info">
                <div class="row">
                    <div class="field">
                        <div class="label">Cliente</div>
                        <div class="value">${cot.cliente_nombre || 'No especificado'}</div>
                    </div>
                    <div class="field">
                        <div class="label">Proyecto</div>
                        <div class="value">${cot.proyecto_nombre || 'No especificado'}</div>
                    </div>
                    <div class="field">
                        <div class="label">Validez</div>
                        <div class="value">${cot.dias_validez || 15} d&iacute;as</div>
                    </div>
                </div>
            </div>
            <table class="quote-table">
                <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Descripci&oacute;n del Servicio</th>
                        <th style="width:60px" class="text-center">Cant.</th>
                        <th style="width:60px" class="text-center">Ud.</th>
                        <th style="width:120px" class="text-right">Precio Unit.</th>
                        <th style="width:120px" class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>${itemsHtml}</tbody>
            </table>
            <div class="quote-totals">
                <div class="row">
                    <table class="totals-table">
                        <tr><td>Subtotal:</td><td class="text-right">$${subtotal.toLocaleString('es-MX', {minimumFractionDigits:2})}</td></tr>
                        <tr class="discount"><td>Descuento comercial (${descPorc}%):</td><td class="text-right">-$${descMonto.toLocaleString('es-MX', {minimumFractionDigits:2})}</td></tr>
                        <tr><td>Subtotal con descuento:</td><td class="text-right">$${total.toLocaleString('es-MX', {minimumFractionDigits:2})}</td></tr>
                        <tr><td>IVA (16%):</td><td class="text-right">$${iva.toLocaleString('es-MX', {minimumFractionDigits:2})}</td></tr>
                        <tr class="grand-total"><td><strong>TOTAL:</strong></td><td class="text-right"><strong>$${totalConIva.toLocaleString('es-MX', {minimumFractionDigits:2})}</strong></td></tr>
                    </table>
                </div>
            </div>
            ${cot.notas_adicionales ? `<div class="quote-notas"><strong>Notas:</strong> ${cot.notas_adicionales}</div>` : ''}
            <div style="padding:16px 30px;border-top:1px solid #e9ecef" class="flex flex-between">
                <div class="text-muted" style="font-size:0.8rem">
                    <i class="fas fa-check-circle text-success"></i> Cotizaci&oacute;n generada por IA con precios de mercado en M&eacute;xico
                </div>
                <div class="flex">
                    <button class="btn-primary btn-sm" onclick="descargarPDF(${cot.id})">
                        <i class="fas fa-file-pdf"></i> Ver PDF
                    </button>
                    <button class="btn-secondary btn-sm" onclick="window.open('pdf_descargar.php?id=${cot.id}&download=1', '_blank')">
                        <i class="fas fa-download"></i> Descargar
                    </button>
                </div>
            </div>
        </div>
    `;

    document.getElementById('quoteResult').innerHTML = html;
}

function descargarPDF(id) {
    window.open('pdf_descargar.php?id=' + id, '_blank');
}

async function cargarListaCotizaciones() {
    const estatus = document.getElementById('filterEstatus').value;

    try {
        const res = await fetch('api_listar.php?estatus=' + encodeURIComponent(estatus));
        const data = await res.json();

        const tbody = document.getElementById('tbodyCotizaciones');

        if (!data.success || !data.cotizaciones.length) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No hay cotizaciones generadas</td></tr>';
            return;
        }

        tbody.innerHTML = data.cotizaciones.map(c => {
            const badgeClass = c.estatus === 'Activa' ? 'badge-activa' : 'badge-cancelada';
            return `<tr>
                <td><strong>${c.folio}</strong></td>
                <td>${c.cliente_nombre}</td>
                <td>${c.proyecto_nombre}</td>
                <td class="text-right">$${Number(c.subtotal).toLocaleString('es-MX', {minimumFractionDigits:2})}</td>
                <td class="text-right text-danger">${c.descuento_porcentaje}%</td>
                <td class="text-right font-bold">$${Number(c.total).toLocaleString('es-MX', {minimumFractionDigits:2})}</td>
                <td><span class="badge ${badgeClass}">${c.estatus}</span></td>
                <td>${c.created_at}</td>
                <td>
                    <button class="action-btn btn-view" title="Ver cotizaci&oacute;n" onclick="verCotizacion(${c.id})"><i class="fas fa-eye"></i></button>
                    <button class="action-btn btn-pdf" title="Descargar PDF" onclick="descargarPDF(${c.id})"><i class="fas fa-file-pdf"></i></button>
                </td>
            </tr>`;
        }).join('');

    } catch (e) {
        document.getElementById('tbodyCotizaciones').innerHTML =
            '<tr><td colspan="9" class="text-center text-muted">Error al cargar: ' + e.message + '</td></tr>';
    }
}

async function verCotizacion(id) {
    try {
        const res = await fetch('api_obtener.php?id=' + id);
        const data = await res.json();
        if (!data.success) {
            Swal.fire({icon: 'error', title: 'Error', text: data.error});
            return;
        }
        mostrarVistaPrevia(data.cotizacion);
        document.getElementById('verTitulo').innerHTML = '<i class="fas fa-file-invoice"></i> Cotizaci&oacute;n ' + data.cotizacion.folio;
        document.getElementById('modalVerCotizacion').classList.add('active');
        document.body.style.overflow = 'hidden';
    } catch (e) {
        Swal.fire({icon: 'error', title: 'Error', text: e.message});
    }
}

[document.getElementById('modalGenerar'), document.getElementById('modalVerCotizacion')].forEach(el => {
    if (el) el.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
            document.body.style.overflow = 'auto';
        }
    });
});
</script>

</div><!-- .container-fluid px-0 -->
</div><!-- .adm-page-shell -->
</div><!-- menu container-fluid -->
</div><!-- menu content-wrapper -->
</body>
</html>
