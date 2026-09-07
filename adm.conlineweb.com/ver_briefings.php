<?php
header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set("display_errors", 1);
include "menu.php";
include "conn.php";

$sql    = "SELECT * FROM briefings ORDER BY fecha_registro DESC";
$result = $conn->query($sql);

$briefings        = [];
$briefings_hoy    = 0;
$briefings_semana = 0;

$hoy       = date('Y-m-d');
$hace7dias = date('Y-m-d', strtotime('-7 days'));

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $briefings[] = $row;
        $fecha = date('Y-m-d', strtotime($row['fecha_registro']));
        if ($fecha === $hoy)      $briefings_hoy++;
        if ($fecha >= $hace7dias) $briefings_semana++;
    }
}
$total_briefings = count($briefings);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="css/admin-platform.css" rel="stylesheet">
    <link href="vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
    <link href="css/admin-datatables.css?v=20250715" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        /* (Mismos estilos previos, se mantienen) */
        * { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        :root {
            --primary-dark: #000147;
            --primary: #1a1f6b;
            --primary-light: #2d3388;
            --primary-soft: #eef0ff;
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
        body { background: #f1f5f9; font-family: 'Inter', sans-serif; color: var(--text-primary); }
        .stat-card {
            background: white; border-radius: 24px; border: none;
            transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
            cursor: pointer; position: relative; overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .stat-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
            background: linear-gradient(90deg, var(--primary-dark), var(--primary-light));
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px -12px rgba(0,1,71,0.15); }
        .stat-icon { width: 52px; height: 52px; background: var(--primary-soft); border-radius: 18px; display: flex; align-items: center; justify-content: center; color: var(--primary-dark); }
        .stat-number { font-size: 34px; font-weight: 700; color: var(--dark); line-height: 1.2; letter-spacing: -0.02em; }
        .stat-label  { font-size: 13px; font-weight: 500; color: var(--text-secondary); letter-spacing: 0.2px; }
        .modern-table { border-radius: 24px; overflow: hidden; border: 1px solid var(--border); background: white; }
        .modern-table thead th { background: #fafbff; color: var(--text-primary); font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.4px; padding: 18px 16px; border-bottom: 1px solid var(--border); }
        .modern-table tbody tr { transition: all 0.2s ease; border-bottom: 1px solid var(--border); }
        .modern-table tbody tr:hover { background: #fefefe; box-shadow: inset 0 0 0 1px var(--primary-soft); }
        .modern-table tbody td { padding: 14px 16px; vertical-align: middle; font-size: 14px; font-weight: 500; color: var(--text-primary); border-bottom: 1px solid var(--border); }
        .badge-id     { background: var(--primary-soft); color: var(--primary-dark); padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        .badge-status { padding: 5px 12px; border-radius: 30px; font-size: 11px; font-weight: 600; display: inline-block; }
        .badge-yes    { background: #dcfce7; color: #15803d; }
        .badge-no     { background: #fee2e2; color: #b91c1c; }
        .badge-info   { background: #dbeafe; color: #1d4ed8; }
        .badge-estatus {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 12px; border-radius: 30px; font-size: 11px; font-weight: 600;
            cursor: pointer; border: none; transition: all 0.2s ease;
            white-space: nowrap;
        }
        .badge-estatus.pendiente { background: #fef3c7; color: #92400e; }
        .badge-estatus.pendiente:hover { background: #fde68a; transform: scale(1.05); }
        .badge-estatus.completado { background: #dcfce7; color: #15803d; }
        .badge-estatus.completado:hover { background: #bbf7d0; transform: scale(1.05); }
        .badge-estatus .dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
        .badge-estatus.pendiente  .dot { background: #f59e0b; }
        .badge-estatus.completado .dot { background: #10b981; }
        .btn-primary-custom  { background: var(--primary-dark); color: white; border-radius: 10px; padding: 6px 14px; font-size: 12px; font-weight: 500; transition: all 0.2s; border: none; cursor: pointer; }
        .btn-primary-custom:hover { background: var(--primary); transform: translateY(-1px); }
        .btn-success-modern  { background: var(--success); color: white; border-radius: 10px; padding: 6px 14px; font-size: 12px; font-weight: 500; transition: all 0.2s; border: none; cursor: pointer; }
        .btn-success-modern:hover { background: #059669; transform: translateY(-1px); }
        .btn-danger-modern   { background: var(--danger); color: white; border-radius: 10px; padding: 6px 14px; font-size: 12px; font-weight: 500; transition: all 0.2s; border: none; cursor: pointer; }
        .btn-danger-modern:hover  { background: #dc2626; transform: translateY(-1px); }
        .btn-whatsapp-modern { background: #25D366; color: white; border-radius: 10px; padding: 6px 14px; font-size: 12px; font-weight: 500; transition: all 0.2s; border: none; cursor: pointer; }
        .btn-whatsapp-modern:hover { background: #128C7E; transform: translateY(-1px); }
        .btn-edit-modern { background: #f59e0b; color: white; border-radius: 10px; padding: 6px 14px; font-size: 12px; font-weight: 500; transition: all 0.2s; border: none; cursor: pointer; }
        .btn-edit-modern:hover { background: #d97706; transform: translateY(-1px); }
        .btn-group-actions { display: flex; gap: 6px; flex-wrap: wrap; }
        .text-secondary-custom { color: var(--text-secondary); }
        .ruta-generada {
            font-size: 11px; color: var(--primary-dark); word-break: break-all;
            background: var(--primary-soft); padding: 6px 10px; border-radius: 8px;
            display: inline-block; text-decoration: none;
        }
        .ruta-generada:hover { text-decoration: underline; color: var(--primary); }
        .modal-briefing .modal-content { border-radius: 20px; border: none; box-shadow: 0 25px 60px rgba(0,0,0,0.2); }
        .modal-briefing .modal-header  { background: linear-gradient(135deg, var(--primary-dark), var(--primary-light)); color: white; border-radius: 20px 20px 0 0; padding: 1.5rem 2rem; }
        .modal-briefing .modal-header .btn-close { filter: invert(1); }
        .modal-briefing .modal-body   { padding: 2rem; max-height: 75vh; overflow-y: auto; }
        .modal-briefing .modal-footer { padding: 1rem 2rem; border-top: 1px solid var(--border); }
        .detail-section { margin-bottom: 1.5rem; }
        .detail-section-title {
            font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px;
            color: var(--primary-dark); padding-bottom: 8px;
            border-bottom: 2px solid var(--primary-soft); margin-bottom: 12px;
            display: flex; align-items: center; gap: 6px;
        }
        .detail-row   { display: flex; gap: 8px; margin-bottom: 8px; font-size: 14px; }
        .detail-label { min-width: 170px; font-weight: 600; color: var(--text-secondary); flex-shrink: 0; }
        .detail-value { color: var(--text-primary); word-break: break-word; }
        .img-gallery { display: flex; flex-wrap: wrap; gap: 14px; margin-top: 10px; }
        .img-gallery-card {
            display: flex; flex-direction: column; align-items: center;
            gap: 6px; max-width: 150px;
        }
        .img-gallery-item {
            width: 130px; height: 130px; border-radius: 10px; object-fit: cover;
            border: 2px solid var(--border); cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            display: block;
        }
        .img-gallery-item:hover { transform: scale(1.05); box-shadow: 0 6px 16px rgba(0,1,71,0.15); }
        .img-url-label {
            font-size: 10px; color: var(--primary-dark); word-break: break-all;
            text-align: center; line-height: 1.4; text-decoration: none;
            opacity: 0.8; transition: opacity 0.2s;
        }
        .img-url-label:hover { opacity: 1; text-decoration: underline; color: var(--primary); }
        .img-not-found { font-size: 11px; color: var(--danger); text-align: center; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in-up { animation: fadeInUp 0.5s ease-out; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: var(--light); border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        @media print {
            body * { visibility: hidden; }
            #printArea, #printArea * { visibility: visible; }
            #printArea { position: absolute; left: 0; top: 0; width: 100%; padding: 20px; }
            .no-print  { display: none !important; }
            .img-gallery-item { width: 120px; height: 120px; }
            .img-gallery-card { break-inside: avoid; }
            .img-url-label { font-size: 9px !important; color: #000 !important; }
        }

        /* Estilos adicionales para el modal de edición */
        #modalEditar .modal-dialog { max-width: 95%; width: 1200px; }
        .edit-section { margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border); }
        .edit-section:last-child { border-bottom: none; }
        .edit-section-title {
            font-size: 1.1rem; font-weight: 700; color: var(--primary-dark);
            margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;
        }
        .edit-grid { display: grid; grid-template-columns: repeat(2,1fr); gap: 1rem; }
        .edit-grid .full-width { grid-column: 1 / -1; }
        .edit-form-group { margin-bottom: 0.8rem; }
        .edit-form-group label { font-weight: 600; font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 0.2rem; display: block; }
        .edit-form-group input, .edit-form-group select, .edit-form-group textarea {
            width: 100%; padding: 0.5rem 0.8rem; border: 1px solid var(--border); border-radius: 8px;
            font-size: 0.9rem; background: white;
        }
        .edit-form-group textarea {
            white-space: pre-wrap;
            tab-size: 4;
            resize: vertical;
            min-height: 70px;
        }
        .edit-options-grid {
            display: flex; flex-wrap: wrap; gap: 0.4rem; margin-top: 0.3rem;
        }
        .edit-option-btn {
            background: #f1f5f9; border: 1px solid var(--border); padding: 0.3rem 1rem;
            border-radius: 30px; font-size: 0.8rem; cursor: pointer; transition: all 0.1s;
        }
        .edit-option-btn.selected {
            background: var(--primary-dark); color: white; border-color: var(--primary-dark);
        }
        .edit-existing-images {
            display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px;
        }
        .edit-img-item {
            position: relative; width: 80px; height: 80px; border-radius: 8px; overflow: hidden;
            border: 1px solid var(--border);
        }
        .edit-img-item img { width: 100%; height: 100%; object-fit: cover; }
        .edit-img-delete {
            position: absolute; top: 2px; right: 2px; background: rgba(239,68,68,0.9);
            color: white; border: none; border-radius: 50%; width: 20px; height: 20px;
            font-size: 12px; display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: all 0.1s;
        }
        .edit-img-delete:hover { background: #dc2626; }
        .edit-file-upload-area {
            border: 2px dashed var(--border); border-radius: 12px; padding: 1.5rem;
            text-align: center; cursor: pointer; background: #fafbff; display: block;
            margin-bottom: 0;
        }
        .edit-file-upload-area.disabled {
            opacity: 0.55; pointer-events: none; background: #f1f5f9;
        }
        .edit-file-preview {
            display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px;
        }
        .edit-file-preview-item {
            width: 70px; height: 70px; border-radius: 8px; object-fit: cover; border: 1px solid var(--border);
        }
        .edit-file-preview-wrap { position: relative; width: 80px; height: 80px; }
        .edit-file-preview-wrap .edit-img-delete { z-index: 2; }
        .edit-section-desc {
            font-size: 0.85rem; color: var(--text-muted); margin: -0.4rem 0 1rem;
            padding-left: 0.2rem; line-height: 1.45;
        }
        .edit-field-hint {
            font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem; line-height: 1.35;
        }
        .edit-form-group label.required::after { content: ' *'; color: var(--danger); }
        .edit-phone-input-group {
            display: flex; gap: 0.5rem; align-items: stretch;
        }
        .edit-phone-input-group select {
            width: auto; min-width: 110px; flex-shrink: 0;
        }
        .edit-phone-input-group input { flex: 1; }
        .edit-contact-box {
            background: var(--primary-soft); border-radius: 12px; padding: 1.2rem;
            border: 1px solid #dde3ff;
        }
        #modalEditar .modal-body { max-height: 82vh; }
    </style>
</head>
<body>
<div id="content-wrapper" class="d-flex flex-column">
<div id="content">
<div class="container-xl my-4 py-4 legacy-touch" style="max-width: 96% !important;">

    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4 fade-in-up">
        <div>
            <h1 class="h2 mb-1" style="color: var(--primary-dark); font-weight: 700; letter-spacing: -0.02em;">
                <i class="fas fa-file-alt me-2"></i>Briefings Recibidos
            </h1>
            <p class="text-secondary-custom mb-0" style="font-weight: 500;">Solicitudes de desarrollo web enviadas por clientes</p>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="row mb-4 fade-in-up">
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="stat-card p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-number"><?php echo $total_briefings; ?></div>
                        <div class="stat-label mt-1">Total Briefings</div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-file-alt fa-lg"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="stat-card p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-number"><?php echo $briefings_hoy; ?></div>
                        <div class="stat-label mt-1">Recibidos Hoy</div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-calendar-day fa-lg"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="stat-card p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-number"><?php echo $briefings_semana; ?></div>
                        <div class="stat-label mt-1">Últimos 7 días</div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-chart-line fa-lg"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="modern-table fade-in-up">
        <?php if ($total_briefings > 0): ?>
        <table class="table" id="dataTableBriefings" width="100%" cellspacing="0">
            <thead>
                <tr>
                    <th><i class="fas fa-hashtag me-1"></i>ID</th>
                    <th><i class="fas fa-building me-1"></i>Empresa</th>
                    <th><i class="fas fa-briefcase me-1"></i>Giro</th>
                    <th><i class="fas fa-user me-1"></i>Contacto</th>
                    <th><i class="fas fa-envelope me-1"></i>Correo</th>
                    <th><i class="fas fa-phone me-1"></i>Teléfono</th>
                    <th><i class="fas fa-images me-1"></i>Imágenes</th>
                    <th><i class="fas fa-circle-check me-1"></i>Estatus</th>
                    <th><i class="fas fa-calendar me-1"></i>Fecha</th>
                    <th><i class="fas fa-cog me-1"></i>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($briefings as $b):
                    $imagenes  = [];
                    if (!empty($b['imagenes'])) {
                        $decoded = json_decode($b['imagenes'], true);
                        if (is_array($decoded)) {
                            if (isset($decoded['files']) && is_array($decoded['files'])) {
                                $imagenes = $decoded['files'];
                            } else {
                                foreach ($decoded as $item) {
                                    if (is_string($item) && $item !== '' && !str_starts_with($item, 'data:')) {
                                        $imagenes[] = $item;
                                    }
                                }
                            }
                        }
                    }
                    $num_imgs   = count($imagenes);
                    $fecha_fmt  = !empty($b['fecha_registro']) ? date('d/m/Y H:i', strtotime($b['fecha_registro'])) : '-';
                    $estatus    = isset($b['estatus']) ? (int)$b['estatus'] : 0;
                ?>
                <tr id="row-<?php echo $b['id']; ?>">
                    <td><span class="badge-id">#<?php echo $b['id']; ?></span></td>
                    <td><strong><?php echo htmlspecialchars($b['company_name']); ?></strong></td>
                    <td class="text-secondary-custom"><?php echo htmlspecialchars($b['business_type']); ?></td>
                    <td><?php echo htmlspecialchars($b['contact_name']); ?></td>
                    <td>
                        <?php if (!empty($b['contact_email'])): ?>
                        <a href="mailto:<?php echo htmlspecialchars($b['contact_email']); ?>" style="color:var(--primary-dark);text-decoration:none;">
                            <i class="fas fa-envelope me-1" style="font-size:11px;"></i><?php echo htmlspecialchars($b['contact_email']); ?>
                        </a>
                        <?php else: ?><span class="text-secondary-custom">-</span><?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($b['contact_phone'])): ?>
                        <a href="tel:<?php echo htmlspecialchars($b['contact_phone']); ?>" style="color:var(--primary-dark);text-decoration:none;">
                            <?php echo htmlspecialchars($b['contact_phone']); ?>
                        </a>
                        <?php else: ?><span class="text-secondary-custom">-</span><?php endif; ?>
                    </td>
                    <td>
                        <?php if ($num_imgs > 0): ?>
                        <span class="badge-status badge-yes">
                            <i class="fas fa-images me-1"></i><?php echo $num_imgs; ?> archivo<?php echo $num_imgs > 1 ? 's' : ''; ?>
                        </span>
                        <?php else: ?>
                        <span class="badge-status badge-no">Sin imágenes</span>
                        <?php endif; ?>
                    </td>
                    <td id="estatus-cell-<?php echo $b['id']; ?>">
                        <?php if ($estatus === 1): ?>
                        <button class="badge-estatus completado" onclick="cambiarEstatus(<?php echo $b['id']; ?>, 1)" title="Clic para marcar como Pendiente">
                            <span class="dot"></span> Completado
                        </button>
                        <?php else: ?>
                        <button class="badge-estatus pendiente" onclick="cambiarEstatus(<?php echo $b['id']; ?>, 0)" title="Clic para marcar como Completado">
                            <span class="dot"></span> Pendiente
                        </button>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;font-size:13px;"><?php echo $fecha_fmt; ?></td>
                    <td>
                        <div class="adm-actions">
                            <button type="button" onclick="verDetalle(<?php echo $b['id']; ?>)" class="adm-act adm-act--view">
                                <i class="fas fa-eye"></i>Ver
                            </button>
                            <button type="button" onclick="editarBriefing(<?php echo $b['id']; ?>)" class="adm-act adm-act--warn">
                                <i class="fas fa-edit"></i>Editar
                            </button>
                            <button type="button" onclick="descargarTXT(<?php echo $b['id']; ?>)" class="adm-act adm-act--info">
                                <i class="fas fa-file-alt"></i>TXT
                            </button>
                            <button type="button" onclick="enviarWhatsApp(<?php echo $b['id']; ?>)" class="adm-act adm-act--success" title="Enviar sitio al cliente">
                                <i class="fab fa-whatsapp"></i>WhatsApp
                            </button>
                            <button type="button" onclick="eliminarBriefing(<?php echo $b['id']; ?>)" class="adm-act adm-act--danger">
                                <i class="fas fa-trash"></i>Eliminar
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="text-center py-5">
            <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
            <p class="text-secondary-custom">No se han recibido briefings aún.</p>
        </div>
        <?php endif; ?>
    </div>
</div>
</div>
</div>

<!-- ═══════════════ MODAL DETALLE ═══════════════ -->
<div class="modal fade modal-briefing" id="modalDetalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0 fw-bold"><i class="fas fa-file-alt me-2"></i>Detalle del Briefing</h5>
                    <small id="modalSubtitle" style="opacity:0.8;font-size:13px;"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="printArea"></div>
            <div class="modal-footer no-print">
                <button onclick="imprimirDetalle()" class="btn btn-sm"
                        style="background:var(--primary-dark);color:white;border-radius:10px;font-weight:600;">
                    <i class="fas fa-print me-1"></i>Imprimir
                </button>
                <button id="btnDescargarTXTModal" class="btn btn-sm btn-success" style="border-radius:10px;font-weight:600;">
                    <i class="fas fa-file-alt me-1"></i>Descargar TXT
                </button>
                <button id="btnWhatsAppModal" class="btn btn-sm" style="background:#25D366;color:white;border:none;border-radius:10px;font-weight:600;">
                    <i class="fab fa-whatsapp me-1"></i>Enviar por WhatsApp
                </button>
                <button type="button" class="btn btn-sm btn-secondary" style="border-radius:10px;" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════ MODAL EDITAR ═══════════════ -->
<div class="modal fade modal-briefing" id="modalEditar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                <div>
                    <h5 class="modal-title mb-0 fw-bold"><i class="fas fa-edit me-2"></i>Editar Briefing</h5>
                    <small id="editModalSubtitle" style="opacity:0.9;font-size:13px;"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1);"></button>
            </div>
            <div class="modal-body" id="editModalBody">
                <!-- El formulario se cargará dinámicamente con JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" id="btnGuardarEdicion" style="background: #f59e0b; border: none; color: white; font-weight: 600;">
                    <i class="fas fa-save me-1"></i>Guardar Cambios
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════ MODAL IMAGEN AMPLIADA ═══════════════ -->
<div class="modal fade" id="modalImagen" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="background:transparent;border:none;box-shadow:none;">
            <img id="imgAmpliada" src="" alt="Imagen" style="width:100%;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,0.6);">
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="vendor/datatables/jquery.dataTables.min.js"></script>
<script src="vendor/datatables/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// ── Datos PHP → JS ──────────────────────────────────────────────────────────
var briefingsData = <?php echo json_encode($briefings, JSON_UNESCAPED_UNICODE); ?>;
var IMG_BASE_URL = 'https://www.conlineweb.com/uploads/briefing_imagenes/';

// Opciones para selects múltiples (coinciden con el formulario de registro)
const GOALS_OPTIONS    = ["Ventas directas", "Generar clientes potenciales (leads)", "Posicionamiento SEO", "Mostrar portafolio", "Información institucional", "Otro"];
const ACTIONS_OPTIONS  = ["Contactarte", "Agendar cita", "Enviar mensaje", "Comprar producto", "Solicitar cotización", "Descargar contenido", "Suscribirse", "Llamar"];
const LOGO_OPTIONS     = ["Sí, tengo", "No, necesito ayuda"];
const BRANDING_OPTIONS = ["Sí", "No"];
const UPLOAD_OPTIONS   = ["Sí, deseo cargar imágenes", "No, no tengo imágenes todavía"];
const FEATURES_OPTIONS = ["Formulario de contacto", "Botón de WhatsApp flotante", "Catálogo o listado de productos", "Galería de imágenes/videos", "Blog / Noticias", "Integración con redes sociales", "Ubicación en mapa", "Carrito de compras", "Pasarela de pagos", "Chat en vivo", "Área de clientes", "Calendario de citas", "Newsletter / Suscripciones"];
const DOMAIN_OPTIONS   = ["Sí", "No"];
const HOSTING_OPTIONS  = ["Sí", "No"];
const SOCIAL_OPTIONS   = ["Facebook", "Instagram", "LinkedIn", "TikTok", "X / Twitter", "YouTube"];

// ── DataTable ────────────────────────────────────────────────────────────────
$(document).ready(function() {
    if ($('#dataTableBriefings').length) {
        $('#dataTableBriefings').DataTable({
            scrollX: true,
            searching: true,
            language: {
                search: "Buscar:",
                searchPlaceholder: "Empresa, contacto, correo...",
                lengthMenu: "Mostrar _MENU_ registros",
                info: "Mostrando _START_ a _END_ de _TOTAL_ briefings",
                paginate: { first: "Primero", last: "Último", next: "Siguiente", previous: "Anterior" },
                zeroRecords: "No se encontraron resultados",
                emptyTable: "No hay briefings registrados"
            },
            pageLength: 25,
            order: [[0, 'desc']],
            columnDefs: [{ orderable: false, targets: [6, 7, 9] }]
        });
        $(window).on('resize.admBriefingsDt', function () {
            if ($.fn.DataTable.isDataTable('#dataTableBriefings')) {
                $('#dataTableBriefings').DataTable().columns.adjust();
            }
        });
    }
});

// ── Helpers ──────────────────────────────────────────────────────────────────
function getBriefing(id) {
    return briefingsData.find(function(b) { return parseInt(b.id) === parseInt(id); });
}

function nv(val, fallback) {
    fallback = fallback || '-';
    if (!val) return fallback;
    var s = String(val).trim();
    return s !== '' ? s : fallback;
}

function fmtFecha(str) {
    if (!str) return '-';
    var d = new Date(str.replace(' ', 'T'));
    return d.toLocaleDateString('es-MX', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
}

function badge(val) {
    if (!val || String(val).trim() === '') return '<span class="badge-status badge-no">No especificado</span>';
    var v = String(val).toLowerCase();
    if (v === 'sí' || v === 'si' || v === 'sí, tengo')
        return '<span class="badge-status badge-yes">' + val + '</span>';
    if (v === 'no' || v === 'no, necesito ayuda')
        return '<span class="badge-status badge-no">' + val + '</span>';
    return '<span class="badge-status badge-info">' + val + '</span>';
}

function row(label, value) {
    return '<div class="detail-row">'
         + '<span class="detail-label">' + label + ':</span>'
         + '<span class="detail-value">' + value + '</span>'
         + '</div>';
}

function slugify(text) {
    var from = 'áàäâãéèëêíìïîóòöôõúùüûñçÁÀÄÂÃÉÈËÊÍÌÏÎÓÒÖÔÕÚÙÜÛÑÇ';
    var to   = 'aaaaaeeeeiiiioooooouuuuncaaaaaeeeeiiiioooooouuuunc';
    for (var i = 0; i < from.length; i++) {
        text = text.replace(new RegExp(from.charAt(i), 'g'), to.charAt(i));
    }
    return text.toLowerCase().replace(/[^a-z0-9\s]/g, '').trim().replace(/\s+/g, '_').substring(0, 40);
}

function generarRuta(b) {
    var slug = slugify(b.company_name || 'empresa');
    return 'https://websites.c-onlineweb.net/' + slug + '_' + b.id;
}

function parseImagenesArchivos(b) {
    var list = [];
    try {
        var raw = b.imagenes ? JSON.parse(b.imagenes) : [];
        if (Array.isArray(raw)) {
            raw.forEach(function(item) {
                if (typeof item === 'string' && item !== '' && item.indexOf('data:') !== 0) {
                    list.push(item);
                }
            });
        } else if (raw && Array.isArray(raw.files)) {
            raw.files.forEach(function(file) {
                if (typeof file === 'string') list.push(file);
            });
        }
    } catch (e) {}
    return list;
}

// ── Cambiar estatus ─────────────────────────────────────────────────────────
function cambiarEstatus(id, estatusActual) {
    var nuevoEstatus = estatusActual === 1 ? 0 : 1;
    var textoNuevo   = nuevoEstatus === 1 ? 'Completado' : 'Pendiente';
    var icono        = nuevoEstatus === 1 ? 'success' : 'warning';

    Swal.fire({
        title: '¿Cambiar estatus?',
        html: 'El sitio será marcado como <strong>' + textoNuevo + '</strong>.',
        icon: icono,
        showCancelButton: true,
        confirmButtonColor: nuevoEstatus === 1 ? '#10b981' : '#f59e0b',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Sí, cambiar',
        cancelButtonText: 'Cancelar'
    }).then(function(result) {
        if (!result.isConfirmed) return;

        $.ajax({
            url: 'actualizar_estatus_briefing.php',
            type: 'POST',
            data: { id: id, estatus: nuevoEstatus },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    var cell = document.getElementById('estatus-cell-' + id);
                    if (cell) {
                        if (nuevoEstatus === 1) {
                            cell.innerHTML = '<button class="badge-estatus completado" onclick="cambiarEstatus(' + id + ', 1)" title="Clic para marcar como Pendiente"><span class="dot"></span> Completado</button>';
                        } else {
                            cell.innerHTML = '<button class="badge-estatus pendiente" onclick="cambiarEstatus(' + id + ', 0)" title="Clic para marcar como Completado"><span class="dot"></span> Pendiente</button>';
                        }
                    }
                    var b = getBriefing(id);
                    if (b) b.estatus = nuevoEstatus;
                    Swal.fire({ title: 'Estatus actualizado', text: 'El briefing ahora está marcado como ' + textoNuevo + '.', icon: 'success', timer: 1600, showConfirmButton: false });
                } else {
                    Swal.fire('Error', response.message || 'No se pudo actualizar el estatus.', 'error');
                }
            },
            error: function() { Swal.fire('Error', 'Error de conexión.', 'error'); }
        });
    });
}

// ── Detalle ─────────────────────────────────────────────────────────────────
function buildDetalleHTML(b) {
    var imagenes = parseImagenesArchivos(b);
    var html = '';
    var ruta = generarRuta(b);
    var estatus = parseInt(b.estatus) || 0;
    html += '<div class="detail-section">';
    html += '<div class="detail-section-title"><i class="fas fa-globe"></i> Ruta del Sitio Web Generada</div>';
    html += '<div class="detail-row"><span class="detail-label">URL del sitio:</span><span class="detail-value"><a href="' + ruta + '" target="_blank" class="ruta-generada"><i class="fas fa-link me-1"></i>' + ruta + '</a></span></div>';
    html += '<div class="detail-row"><span class="detail-label">Estatus del sitio:</span><span class="detail-value">' + (estatus === 1 ? '<span class="badge-status badge-yes"><i class="fas fa-circle-check me-1"></i>Completado</span>' : '<span class="badge-status" style="background:#fef3c7;color:#92400e;"><i class="fas fa-clock me-1"></i>Pendiente</span>') + '</span></div>';
    html += '</div>';
    html += '<div class="detail-section"><div class="detail-section-title"><i class="fas fa-user-circle"></i> Datos de Contacto</div>';
    html += row('Nombre', nv(b.contact_name));
    html += row('Correo', nv(b.contact_email));
    html += row('Teléfono', nv(b.contact_phone));
    html += row('Puesto', nv(b.contact_position));
    html += row('Fecha de envío', fmtFecha(b.fecha_registro));
    html += '</div>';
    html += '<div class="detail-section"><div class="detail-section-title"><i class="fas fa-building"></i> Datos del Negocio</div>';
    html += row('Empresa', nv(b.company_name));
    html += row('Giro', nv(b.business_type));
    html += row('Ubicación', nv(b.location));
    html += row('Experiencia', nv(b.years_experience));
    html += row('Diferenciador', nv(b.differentiator));
    html += '</div>';
    html += '<div class="detail-section"><div class="detail-section-title"><i class="fas fa-boxes"></i> Productos y Servicios</div>';
    html += row('¿Qué ofrece?', nv(b.products_services));
    html += row('Servicios estrella', nv(b.main_services));
    html += '</div>';
    html += '<div class="detail-section"><div class="detail-section-title"><i class="fas fa-bullseye"></i> Objetivos del Sitio</div>';
    html += row('Metas del negocio', nv(b.goals));
    html += row('Acción del visitante', nv(b.actions));
    html += '</div>';
    html += '<div class="detail-section"><div class="detail-section-title"><i class="fas fa-palette"></i> Material e Identidad</div>';
    html += row('Logotipo', badge(b.logo));
    html += row('Identidad visual', badge(b.branding));
    html += row('Colores de marca', nv(b.brand_colors));
    html += row('Desea cargar imágenes', badge(b.upload_images));
    html += '</div>';
    if (imagenes.length > 0) {
        html += '<div class="detail-section"><div class="detail-section-title"><i class="fas fa-images"></i> Imágenes Cargadas (' + imagenes.length + ')</div>';
        html += '<div class="img-gallery">';
        imagenes.forEach(function(img) {
            var urlCompleta = IMG_BASE_URL + img;
            html += '<div class="img-gallery-card">';
            html += '<img src="' + urlCompleta + '" alt="' + escapeHtml(img) + '" class="img-gallery-item" onclick="ampliarImagen(\'' + urlCompleta.replace(/'/g, "\\'") + '\')" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'block\'">';
            html += '<span class="img-not-found" style="display:none;"><i class="fas fa-exclamation-triangle me-1"></i>No disponible</span>';
            html += '<a href="' + urlCompleta + '" target="_blank" class="img-url-label"><i class="fas fa-link me-1" style="font-size:9px;"></i>' + escapeHtml(img) + '</a>';
            html += '</div>';
        });
        html += '</div></div>';
    }
    html += '<div class="detail-section"><div class="detail-section-title"><i class="fas fa-cogs"></i> Funcionalidades</div>';
    html += row('Requeridas', nv(b.features));
    html += row('Otras', nv(b.other_features));
    html += '</div>';
    html += '<div class="detail-section"><div class="detail-section-title"><i class="fas fa-server"></i> Dominio y Hosting</div>';
    html += row('¿Tiene dominio?', badge(b.domain));
    html += row('Nombre de dominio', nv(b.domain_name));
    html += row('¿Tiene hosting?', badge(b.hosting));
    html += row('Proveedor hosting', nv(b.hosting_provider));
    html += '</div>';
    html += '<div class="detail-section"><div class="detail-section-title"><i class="fas fa-hashtag"></i> Redes Sociales</div>';
    html += row('Redes activas', nv(b.social));
    html += row('Enlaces', nv(b.social_links));
    html += '</div>';
    return html;
}

function verDetalle(id) {
    var b = getBriefing(id);
    if (!b) { Swal.fire('Error', 'Briefing no encontrado', 'error'); return; }
    currentBriefingId = id;
    document.getElementById('modalSubtitle').textContent = b.company_name + ' — ' + fmtFecha(b.fecha_registro);
    document.getElementById('printArea').innerHTML = buildDetalleHTML(b);
    document.getElementById('btnDescargarTXTModal').onclick = function() { descargarTXT(id); };
    document.getElementById('btnWhatsAppModal').onclick = function() { enviarWhatsApp(id); };
    var modal = new bootstrap.Modal(document.getElementById('modalDetalle'));
    modal.show();
}

function ampliarImagen(src) {
    document.getElementById('imgAmpliada').src = src;
    var modal = new bootstrap.Modal(document.getElementById('modalImagen'));
    modal.show();
}

function imprimirDetalle() { window.print(); }

// ── WhatsApp y TXT ─────────────────────────────────────────────────────────
function enviarWhatsApp(id) {
    var b = getBriefing(id);
    if (!b) { Swal.fire('Error', 'Briefing no encontrado', 'error'); return; }
    var ruta  = generarRuta(b);
    var phone = (b.contact_phone || '').replace(/\D/g, '');
    if (!phone) { Swal.fire('Sin teléfono', 'Este briefing no tiene número de teléfono registrado.', 'warning'); return; }
    if (!phone.startsWith('52')) phone = '52' + phone;
    var mensaje = '¡Tu sitio web ya está listo! 🎉\n\nCon la información que nos compartiste, hemos creado tu página para que puedas comenzar a promocionar tu negocio.\n\nAquí puedes verla:\n👉 ' + ruta + '\n\nTe recomendamos revisarla con calma. Si deseas agregar información, cambiar imágenes o hacer algún ajuste, con gusto podemos ayudarte.\n\n¡Ahora ya tienes presencia en internet para empezar a atraer más clientes! 🚀';
    window.open('https://wa.me/' + phone + '?text=' + encodeURIComponent(mensaje), '_blank');
}

function descargarTXT(id) {
    var b = getBriefing(id);
    if (!b) return;
    var imagenes = parseImagenesArchivos(b);
    var sep  = '='.repeat(60);
    var sep2 = '-'.repeat(40);
    var txt = sep + '\n';
    txt += 'Desarrolla una página web completa, moderna, funcional y extensa con MÍNIMO 5 SECCIONES (para que no se vea corta). Las secciones obligatorias son: INICIO, ACERCA DE, SERVICIOS, PORTAFOLIO o PROYECTOS, y CONTACTO. Puedes agregar más secciones como TESTIMONIOS o FAQ si lo deseas. REQUISITO OBLIGATORIO: El formulario de contacto debe ir en la sección CONTACTO, junto con un mapa (Google Maps o similar embebido). El formulario (Nombre, Email, Teléfono, Mensaje) debe enviar los datos por WhatsApp. Al hacer clic en enviar, se abre WhatsApp con el mensaje pre-llenado incluyendo toda la información del cliente. ADEMÁS: Botón de WhatsApp flotante visible en toda la página. INSTRUCCIONES PRECISAS: 1) Debe ser COMPLETAMENTE RESPONSIVE y muy bien estructurado, funcionando perfectamente en móviles (375px), tablets (768px) y desktop (1024px+). Usar mobile first, flexbox, grid, unidades relativas (rem, %), media queries bien definidas. 2) Estructura HTML semántica impecable: <header>, <nav>, <main>, <section>, <article>, <footer>. Cada sección debe tener un ID único para navegación por anclas. 3) Header con menú de navegación (links ancla a todas las secciones) que sea responsive con menú hamburguesa en móvil. 4) Sección INICIO: hero muy detallado con título llamativo, descripción extensa y atractiva (mínimo 3 líneas), botón CTA que lleve a la sección contacto, fondo con gradiente o imagen sutil. 5) Sección ACERCA DE: muy detallada y extensa, con información completa de la empresa o persona, incluyendo historia (2 párrafos), misión, visión, valores, y datos clave. Usar iconos modernos de FontAwesome. 6) Sección SERVICIOS: mínimo 4 tarjetas de servicios (para que sea más completo) cada una con ícono, título, descripción detallada (párrafo completo de 2-3 líneas), lista de beneficios, efecto hover. 7) Sección PORTAFOLIO o PROYECTOS: mostrar mínimo 3 proyectos realizados con imagen (placeholder con fondo de color o emoji), título, descripción, y etiquetas de tecnologías. Diseño en grid. 8) Sección CONTACTO: DOS columnas (en desktop) - columna izquierda con formulario, columna derecha con mapa embebido + información de contacto adicional (email, teléfono, dirección, horarios, redes sociales). En móvil: columnas apiladas. 9) SECCIÓN ADICIONAL (opcional pero recomendada): TESTIMONIOS de clientes (mínimo 3 tarjetas con foto de perfil, nombre, cargo, testimonio) o FAQ con preguntas frecuentes (mínimo 3 preguntas). Esto hará la página más completa. 10) Footer con año actual dinámico, enlaces a redes sociales, créditos, enlaces rápidos a todas las secciones, newsletter opcional. 11) SEO completo: meta charset, viewport, meta description (150-160 caracteres), meta keywords, Open Graph (og:title, og:description, og:image, og:type), Twitter Cards, JSON-LD para organización, título optimizado. 12) Diseño moderno y profesional: tipografía Inter o Poppins, gradientes suaves, bordes redondeados (24-32px), sombras elegantes, espaciado generoso, paleta de colores armoniosa (ej: azul/blanco/gris o verde/blanco). 13) Iconos FontAwesome (versión 6 gratis) en toda la página: menú, servicios, contacto, proyectos, testimonios, redes sociales. 14) Validaciones JavaScript en formulario: campos no vacíos, email válido (regex completo), teléfono mínimo 8 dígitos máximo 15. Mostrar errores debajo de cada campo con diseño moderno (texto rojo suave, ícono de error), sin usar alert(). 15) Mensaje WhatsApp con formato profesional: "📩 *NUEVO CONTACTO WEB*%0A%0A👤 *Nombre:* X%0A📧 *Email:* X%0A📞 *Teléfono:* X%0A💬 *Mensaje:* X%0A%0A📅 Enviado desde formulario web". Usar encodeURIComponent. Número WhatsApp configurable al inicio del JS (const whatsappNumber = "573000000000";). 16) Botón flotante WhatsApp: fixed bottom 20px right 20px, background #25D366, border-radius 50px, padding 12px 18px, ícono tamaño 28px, efecto pulso o latido, tooltip al hover "¡Escríbenos por WhatsApp!". 17) Animaciones suaves: scroll suave entre secciones (scroll-behavior: smooth), fade-in al hacer scroll (opacity 0 -> 1 con transition), hover en tarjetas con escala ligera. 18) Mapa responsive: iframe de Google Maps con width 100%, height 250px en móvil, 300px en desktop, border-radius 20px, sin bordes. Ubicación de ejemplo: centro de la ciudad o una dirección ficticia realista. 19) Las secciones deben estar MUY DETALLADAS con información real y coherente (NO usar Lorem ipsum). Generar contenido profesional y extenso sobre una agencia digital, empresa de tecnología, consultoría o negocio moderno. Cada sección debe tener contenido sustancial (mínimo 3-4 líneas por bloque descriptivo). 20) El código debe estar muy bien estructurado, indentado, comentado por secciones (/* SECCION INICIO */, /* SECCION SERVICIOS */, etc.), sin errores, listo para copiar, pegar y ejecutar directamente en un archivo .html. 21) La página debe verse completa y no corta, con suficiente contenido que ocupe altura considerable y ofrezca una experiencia profesional al usuario. Antes de terminar, usa la información adicional que te voy a enviar.';
    txt += sep + '\n\n';
    txt += '[ DATOS DE CONTACTO ]\n' + sep2 + '\n';
    txt += 'Nombre:             ' + nv(b.contact_name)         + '\n';
    txt += 'Correo:             ' + nv(b.contact_email)        + '\n';
    txt += 'Teléfono:           ' + nv(b.contact_phone)        + '\n';
    txt += 'Puesto:             ' + nv(b.contact_position)     + '\n';
    txt += 'Fecha de envío:     ' + fmtFecha(b.fecha_registro) + '\n\n';
    txt += '[ DATOS DEL NEGOCIO ]\n' + sep2 + '\n';
    txt += 'Empresa:            ' + nv(b.company_name)     + '\n';
    txt += 'Giro:               ' + nv(b.business_type)    + '\n';
    txt += 'Ubicación:          ' + nv(b.location)         + '\n';
    txt += 'Experiencia:        ' + nv(b.years_experience) + '\n';
    txt += 'Diferenciador:      ' + nv(b.differentiator)   + '\n\n';
    txt += '[ PRODUCTOS / SERVICIOS ]\n' + sep2 + '\n';
    txt += 'Ofrece:             ' + nv(b.products_services) + '\n';
    txt += 'Servicios estrella: ' + nv(b.main_services)     + '\n\n';
    txt += '[ OBJETIVOS DEL SITIO ]\n' + sep2 + '\n';
    txt += 'Metas:              ' + nv(b.goals)   + '\n';
    txt += 'Acción visitante:   ' + nv(b.actions) + '\n\n';
    txt += '[ MATERIAL E IDENTIDAD ]\n' + sep2 + '\n';
    txt += 'Logotipo:           ' + nv(b.logo)          + '\n';
    txt += 'Identidad visual:   ' + nv(b.branding)      + '\n';
    txt += 'Colores de marca:   ' + nv(b.brand_colors)  + '\n';
    txt += 'Cargar imágenes:    ' + nv(b.upload_images) + '\n';
    if (imagenes.length > 0) {
        txt += 'URL del sitio:      ' + generarRuta(b) + '\n';
        txt += '\nImágenes cargadas (' + imagenes.length + '):\n';
        imagenes.forEach(function(img, i) { txt += '  ' + (i + 1) + '. ' + IMG_BASE_URL + img + '\n'; });
    }
    txt += '\n[ FUNCIONALIDADES ]\n' + sep2 + '\n';
    txt += 'Requeridas:         ' + nv(b.features)      + '\n';
    txt += 'Otras:              ' + nv(b.other_features) + '\n\n';
    txt += '[ DOMINIO Y HOSTING ]\n' + sep2 + '\n';
    txt += 'Tiene dominio:      ' + nv(b.domain)           + '\n';
    txt += 'Nombre dominio:     ' + nv(b.domain_name)      + '\n';
    txt += 'Tiene hosting:      ' + nv(b.hosting)          + '\n';
    txt += 'Proveedor hosting:  ' + nv(b.hosting_provider) + '\n\n';
    txt += '[ REDES SOCIALES ]\n' + sep2 + '\n';
    txt += 'Redes activas:      ' + nv(b.social)      + '\n';
    txt += 'Enlaces:            ' + nv(b.social_links) + '\n\n';
    txt += sep + '\n  Generado el ' + new Date().toLocaleString('es-MX') + '\n' + sep + '\n';
    var nombre = (b.company_name || 'briefing').replace(/[^a-zA-Z0-9]/g, '_').toLowerCase().substring(0, 30);
    var blob = new Blob([txt], { type: 'text/plain;charset=utf-8' });
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'briefing_' + nombre + '_' + b.id + '.txt';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(link.href);
    Swal.fire({ title: 'Descargado', text: 'El archivo TXT ha sido generado.', icon: 'success', timer: 1800, showConfirmButton: false });
}

// ── Eliminar ─────────────────────────────────────────────────────────────────
function eliminarBriefing(id) {
    Swal.fire({
        title: '¿Eliminar briefing?', text: 'Esta acción no se puede deshacer.',
        icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', cancelButtonColor: '#64748b',
        confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar'
    }).then(function(result) {
        if (result.isConfirmed) {
            $.ajax({
                url: 'eliminar_briefing.php', type: 'POST', data: { id: id }, dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Eliminado', 'El briefing ha sido eliminado.', 'success').then(function() { location.reload(); });
                    } else {
                        Swal.fire('Error', response.message || 'No se pudo eliminar.', 'error');
                    }
                },
                error: function() { Swal.fire('Error', 'Error de conexión.', 'error'); }
            });
        }
    });
}

// ─────────────────────────────────────────────────────────────────────────────
//  NUEVA FUNCIONALIDAD: EDICIÓN DE BRIEFING
// ─────────────────────────────────────────────────────────────────────────────
let currentEditId = null;
let existingImages = []; // Almacena las imágenes actuales (nombres de archivo)
let deletedImages = [];  // Nombres de archivo a eliminar
let newFilesToUpload = []; // Objetos File nuevos

function escapeHtml(text) {
    if (!text) return '';
    return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function enableTextareaTabs(container) {
    if (!container) return;
    container.querySelectorAll('textarea').forEach(function(ta) {
        if (ta.dataset.tabInsert === '1') return;
        ta.dataset.tabInsert = '1';
        ta.addEventListener('keydown', function(e) {
            if (e.key !== 'Tab' || e.ctrlKey || e.altKey || e.metaKey) return;
            e.preventDefault();
            var start = this.selectionStart;
            var end = this.selectionEnd;
            if (e.shiftKey) {
                var before = this.value.slice(0, start);
                var after = this.value.slice(end);
                if (before.endsWith('\t')) {
                    this.value = before.slice(0, -1) + after;
                    this.selectionStart = this.selectionEnd = start - 1;
                } else {
                    var lineStart = before.lastIndexOf('\n') + 1;
                    if (before.slice(lineStart).startsWith('\t')) {
                        this.value = before.slice(0, lineStart) + before.slice(lineStart + 1) + after;
                        this.selectionStart = this.selectionEnd = start - 1;
                    }
                }
                return;
            }
            this.value = this.value.slice(0, start) + '\t' + this.value.slice(end);
            this.selectionStart = this.selectionEnd = start + 1;
        });
    });
}

function parseContactPhone(phone) {
    var codes = ['+593', '+57', '+56', '+55', '+54', '+52', '+51', '+34', '+1'];
    var raw = String(phone || '').trim();
    var code = '+52';
    var number = raw;
    if (raw.startsWith('+')) {
        codes.sort(function(a, b) { return b.length - a.length; });
        for (var i = 0; i < codes.length; i++) {
            if (raw.startsWith(codes[i])) {
                code = codes[i];
                number = raw.slice(codes[i].length).replace(/\D/g, '');
                break;
            }
        }
    } else {
        number = raw.replace(/\D/g, '');
    }
    if (number.length > 10) number = number.slice(-10);
    return { code: code, number: number };
}

function editInput(label, name, value, opts) {
    opts = opts || {};
    var req = opts.required ? ' class="required"' : '';
    var ph = opts.placeholder ? ' placeholder="' + escapeHtml(opts.placeholder) + '"' : '';
    var hint = opts.hint ? '<div class="edit-field-hint">' + opts.hint + '</div>' : '';
    var type = opts.type || 'text';
    return '<div class="edit-form-group' + (opts.full ? ' full-width' : '') + '">'
        + '<label' + req + '>' + label + '</label>'
        + '<input type="' + type + '" name="' + name + '" value="' + escapeHtml(value || '') + '"' + ph + (opts.required ? ' required' : '') + '>'
        + hint + '</div>';
}

function editTextarea(label, name, value, opts) {
    opts = opts || {};
    var req = opts.required ? ' required class="required"' : '';
    var hint = opts.hint ? '<div class="edit-field-hint">' + opts.hint + '</div>' : '';
    return '<div class="edit-form-group full-width">'
        + '<label' + req + '>' + label + '</label>'
        + '<textarea name="' + name + '" rows="' + (opts.rows || 2) + '"' + (opts.required ? ' required' : '') + '>'
        + escapeHtml(value || '') + '</textarea>' + hint + '</div>';
}

function editSection(title, icon, desc, content) {
    return '<div class="edit-section">'
        + '<div class="edit-section-title"><i class="' + icon + '"></i> ' + title + '</div>'
        + (desc ? '<p class="edit-section-desc">' + desc + '</p>' : '')
        + content + '</div>';
}

function optionMatchesSelected(opt, selectedValue) {
    if (!selectedValue) return false;
    var s = String(selectedValue).trim().toLowerCase();
    var o = String(opt).trim().toLowerCase();
    return s === o || s.indexOf(o) === 0 || o.indexOf(s) === 0;
}

function splitMultiValues(str) {
    if (!str) return [];
    return String(str).split(',').map(function(s) { return s.trim(); }).filter(Boolean);
}

function renderMultiSelect(fieldName, label, options, selectedValuesStr, required) {
    var selected = splitMultiValues(selectedValuesStr);
    var html = '<div class="edit-form-group full-width">';
    html += '<label' + (required ? ' class="required"' : '') + '>' + label + '</label>';
    html += '<div class="edit-options-grid" data-field="' + fieldName + '">';
    options.forEach(function(opt) {
        var isSelected = selected.some(function(s) { return optionMatchesSelected(opt, s); }) ? 'selected' : '';
        html += '<span class="edit-option-btn ' + isSelected + '" data-value="' + escapeHtml(opt) + '" onclick="toggleEditOption(this)">' + escapeHtml(opt) + '</span>';
    });
    html += '</div>';
    html += '<input type="hidden" name="' + fieldName + '" id="edit_' + fieldName + '" value="' + escapeHtml(selectedValuesStr || '') + '">';
    html += '</div>';
    return html;
}

function renderSingleSelect(fieldName, label, options, selectedValue) {
    var html = '<div class="edit-form-group">';
    html += '<label>' + label + '</label>';
    html += '<div class="edit-options-grid" data-field="' + fieldName + '">';
    options.forEach(function(opt) {
        var isSelected = optionMatchesSelected(opt, selectedValue) ? 'selected' : '';
        html += '<span class="edit-option-btn ' + isSelected + '" data-value="' + escapeHtml(opt) + '" onclick="selectSingleEditOption(this)">' + escapeHtml(opt) + '</span>';
    });
    html += '</div>';
    html += '<input type="hidden" name="' + fieldName + '" id="edit_' + fieldName + '" value="' + escapeHtml(selectedValue || '') + '">';
    html += '</div>';
    return html;
}

function editarBriefing(id) {
    var b = getBriefing(id);
    if (!b) { Swal.fire('Error', 'Briefing no encontrado', 'error'); return; }
    currentEditId = id;

    existingImages = [];
    deletedImages = [];
    newFilesToUpload = [];
    existingImages = parseImagenesArchivos(b);

    document.getElementById('editModalSubtitle').textContent = 'Editando: ' + b.company_name + ' (ID: ' + id + ')';

    var phone = parseContactPhone(b.contact_phone);
    var html = '<form id="formEditarBriefing" enctype="multipart/form-data">';
    html += '<input type="hidden" name="id" value="' + id + '">';

    // ── Datos del negocio (igual que tu-web-gratis) ──
    html += editSection('Datos de tu negocio', 'fas fa-building',
        'Cuéntanos quién eres — esto será la base de la página web.',
        '<div class="edit-grid">'
        + editInput('Nombre de tu empresa o negocio', 'company_name', b.company_name, { required: true, placeholder: 'Ej: Ferretería El Clavo, Taquería Don Pepe...', hint: 'El nombre que aparecerá en el header y título del sitio' })
        + editInput('¿A qué se dedica tu negocio?', 'business_type', b.business_type, { required: true, placeholder: 'Ej: Venta de ropa, Consultora, Restaurante...' })
        + editInput('Zonas de operación / dirección', 'location', b.location, { placeholder: 'Ej: León, Guanajuato, CDMX, todo México...', hint: 'Zonas donde operas y dirección completa si deseas mostrarla' })
        + editInput('¿Cuánto tiempo llevas en el mercado?', 'years_experience', b.years_experience, { placeholder: 'Ej: 5 años, Recién inicio, +10 años' })
        + editInput('¿Por qué elegirte a ti y no a la competencia?', 'differentiator', b.differentiator, { required: true, full: true, placeholder: 'Ej: Envío gratis, atención 24/7, garantía de por vida...' })
        + '</div>');

    // ── Productos y servicios ──
    html += editSection('Tus productos y servicios', 'fas fa-boxes',
        'Corazón del contenido del sitio web.',
        editTextarea('¿Qué vendes o qué servicio ofreces?', 'products_services', b.products_services, { required: true, rows: 2 })
        + editTextarea('¿Cuáles son tus servicios o productos estrella?', 'main_services', b.main_services, { rows: 2 }));

    // ── Objetivos ──
    html += editSection('¿Para qué quieres tu sitio web?', 'fas fa-bullseye',
        'Diseñamos cada sección para convertir visitas en clientes.',
        renderMultiSelect('goals', '¿Qué resultado esperas obtener con tu página web?', GOALS_OPTIONS, b.goals, true)
        + renderMultiSelect('actions', '¿Qué quieres que haga el visitante cuando llegue a tu sitio?', ACTIONS_OPTIONS, b.actions, true));

    // ── Material e identidad ──
    var materialHtml = '<div class="edit-grid">'
        + renderSingleSelect('logo', '¿Ya tienes logotipo?', LOGO_OPTIONS, b.logo)
        + '<div class="edit-form-group">'
        + renderSingleSelect('branding', '¿Tienes colores o imagen de marca?', BRANDING_OPTIONS, b.branding)
        + editInput('Colores de marca', 'brand_colors', b.brand_colors, { placeholder: 'Ej: azul marino #003366, naranja #FF9900' })
        + '</div></div>'
        + renderSingleSelect('upload_images', '¿Deseas cargar imágenes?', UPLOAD_OPTIONS, b.upload_images);

    materialHtml += '<div class="edit-form-group full-width" style="margin-top:1rem;">';
    materialHtml += '<label>Imágenes actuales</label>';
    materialHtml += '<div class="edit-existing-images" id="existingImagesContainer">';
    if (existingImages.length > 0) {
        existingImages.forEach(function(imgName) {
            materialHtml += '<div class="edit-img-item" data-img="' + escapeHtml(imgName) + '">';
            materialHtml += '<img src="' + IMG_BASE_URL + imgName + '" alt="' + escapeHtml(imgName) + '">';
            materialHtml += '<button type="button" class="edit-img-delete" onclick="marcarEliminarImagen(this, \'' + String(imgName).replace(/\\/g, '\\\\').replace(/'/g, "\\'") + '\')"><i class="fas fa-times"></i></button>';
            materialHtml += '</div>';
        });
    } else {
        materialHtml += '<p class="text-muted mb-0">No hay imágenes cargadas.</p>';
    }
    materialHtml += '</div>';

    materialHtml += '<label class="mt-3 d-block">Carga el logo y todas las imágenes que desees</label>';
    materialHtml += '<label class="edit-file-upload-area" id="editFileUploadArea" for="editFileInput">';
    materialHtml += '<i class="fas fa-cloud-upload-alt fa-2x mb-2"></i><br>';
    materialHtml += '<span>Haz clic aquí para seleccionar imágenes</span><br>';
    materialHtml += '<small class="text-muted">PNG, JPG, JPEG, GIF, WEBP — Puedes agregar varias veces</small>';
    materialHtml += '</label>';
    materialHtml += '<input type="file" id="editFileInput" name="new_images[]" multiple accept="image/png,image/jpeg,image/gif,image/webp" style="display:none;">';
    materialHtml += '<div class="edit-file-preview" id="editFilePreview"></div>';
    materialHtml += '<div class="edit-field-hint">Puedes hacer clic de nuevo en el área de arriba para agregar más imágenes. Usa la <strong>X</strong> para quitar.</div>';
    materialHtml += '</div>';

    html += editSection('Material e identidad de tu marca', 'fas fa-palette',
        '¿Qué tan listo está tu material? No te preocupes si no tienes todo.',
        materialHtml);

    // ── Funcionalidades ──
    html += editSection('¿Qué herramientas necesita tu sitio web?', 'fas fa-cogs',
        'Selecciona las funciones que harán que tu página trabaje por ti.',
        renderMultiSelect('features', 'Selecciona todo lo que aplique a tu negocio', FEATURES_OPTIONS, b.features, false)
        + editTextarea('¿Algo más que necesites y no veas en la lista?', 'other_features', b.other_features, { rows: 2 }));

    // ── Dominio y hosting ──
    html += editSection('Dominio y alojamiento web', 'fas fa-server',
        'Tu dominio es tu dirección en internet (ej: tunegocio.com).',
        '<div class="edit-grid">'
        + '<div class="edit-form-group">' + renderSingleSelect('domain', '¿Ya tienes un dominio registrado?', DOMAIN_OPTIONS, b.domain)
        + editInput('Nombre de dominio', 'domain_name', b.domain_name, { placeholder: 'Ej: tunegocio.com' }) + '</div>'
        + '<div class="edit-form-group">' + renderSingleSelect('hosting', '¿Tienes un servicio de hosting activo?', HOSTING_OPTIONS, b.hosting)
        + editInput('Proveedor de hosting', 'hosting_provider', b.hosting_provider, { placeholder: 'Ej: GoDaddy, Hostinger, cPanel...' }) + '</div>'
        + '</div>');

    // ── Redes sociales ──
    html += editSection('Tus redes sociales', 'fas fa-hashtag',
        'Conectaremos tu página con tus canales donde ya tienes audiencia.',
        renderMultiSelect('social', '¿En qué redes tienes presencia activa?', SOCIAL_OPTIONS, b.social, false)
        + editTextarea('Comparte los enlaces de tus perfiles', 'social_links', b.social_links, { rows: 2 }));

    // ── Contacto (al final, igual que insertar) ──
    var contactHtml = '<div class="edit-contact-box"><div class="edit-grid">';
    contactHtml += editInput('Nombre completo', 'contact_name', b.contact_name, { required: true, placeholder: 'Ej: Juan Pérez Martínez' });
    contactHtml += editInput('Correo electrónico', 'contact_email', b.contact_email, { required: true, type: 'email', placeholder: 'Ej: contacto@tunegocio.com' });
    contactHtml += '<div class="edit-form-group"><label class="required">Teléfono / WhatsApp (10 dígitos)</label>';
    contactHtml += '<div class="edit-phone-input-group">';
    contactHtml += '<select id="edit_phone_country_code" class="form-select form-select-sm">';
    var countryCodes = [
        ['+52', '🇲🇽 +52'], ['+1', '🇺🇸 +1'], ['+57', '🇨🇴 +57'], ['+56', '🇨🇱 +56'],
        ['+51', '🇵🇪 +51'], ['+54', '🇦🇷 +54'], ['+593', '🇪🇨 +593'], ['+34', '🇪🇸 +34'], ['+55', '🇧🇷 +55']
    ];
    countryCodes.forEach(function(c) {
        contactHtml += '<option value="' + c[0] + '"' + (c[0] === phone.code ? ' selected' : '') + '>' + c[1] + '</option>';
    });
    contactHtml += '</select>';
    contactHtml += '<input type="tel" id="edit_phone_number" maxlength="10" pattern="[0-9]{10}" value="' + escapeHtml(phone.number) + '" placeholder="Ej: 4771234567">';
    contactHtml += '</div>';
    contactHtml += '<input type="hidden" name="contact_phone" id="edit_contact_phone" value="' + escapeHtml(b.contact_phone || '') + '">';
    contactHtml += '<div class="edit-field-hint">Exactamente 10 dígitos, sin espacios ni guiones.</div></div>';
    contactHtml += editInput('Puesto / Cargo', 'contact_position', b.contact_position, { placeholder: 'Ej: Dueño, Gerente, Marketing...' });
    contactHtml += '</div></div>';

    html += editSection('¿Con quién nos comunicamos?', 'fas fa-user-check',
        'Datos de la persona de contacto.',
        contactHtml);

    html += '</form>';

    document.getElementById('editModalSubtitle').textContent = 'Editando: ' + b.company_name + ' (ID: ' + id + ')';
    document.getElementById('editModalBody').innerHTML = html;

    setTimeout(function() {
        initEditFileUpload();
        enableTextareaTabs(document.getElementById('formEditarBriefing'));
        var phoneCountry = document.getElementById('edit_phone_country_code');
        var phoneNumber = document.getElementById('edit_phone_number');
        function syncEditPhone() {
            var num = (phoneNumber.value || '').replace(/\D/g, '').slice(0, 10);
            phoneNumber.value = num;
            document.getElementById('edit_contact_phone').value = num ? (phoneCountry.value + num) : '';
        }
        phoneCountry.addEventListener('change', syncEditPhone);
        phoneNumber.addEventListener('input', syncEditPhone);
        syncEditPhone();
    }, 50);

    var modal = new bootstrap.Modal(document.getElementById('modalEditar'));
    modal.show();
}

function toggleEditOption(el) {
    el.classList.toggle('selected');
    var container = el.closest('.edit-options-grid');
    var field = container.dataset.field;
    var selected = [];
    container.querySelectorAll('.edit-option-btn.selected').forEach(function(btn) { selected.push(btn.dataset.value); });
    document.getElementById('edit_' + field).value = selected.join(', ');
}

function toggleEditUploadArea(value) {
    var hidden = document.getElementById('edit_upload_images');
    if (hidden && value) hidden.value = value;
}

function syncEditFilesInput() {
    var input = document.getElementById('editFileInput');
    if (!input || typeof DataTransfer === 'undefined') return;
    var dt = new DataTransfer();
    newFilesToUpload.forEach(function(file) { dt.items.add(file); });
    input.files = dt.files;
}

function markEditUploadYes() {
    var hidden = document.getElementById('edit_upload_images');
    if (hidden) hidden.value = 'Sí, deseo cargar imágenes';
    var grid = document.querySelector('.edit-options-grid[data-field="upload_images"]');
    if (grid) {
        grid.querySelectorAll('.edit-option-btn').forEach(function(btn) {
            btn.classList.toggle('selected', btn.dataset.value === 'Sí, deseo cargar imágenes');
        });
    }
}

function initEditFileUpload() {
    var input = document.getElementById('editFileInput');
    if (!input) return;
    input.addEventListener('change', function(event) {
        addEditFiles(event.target.files);
        event.target.value = '';
    });
}

function addEditFiles(fileList) {
    if (!fileList || !fileList.length) return;
    var maxSize = 5 * 1024 * 1024;
    var existingKeys = new Set(newFilesToUpload.map(function(f) {
        return f.name + '|' + f.size + '|' + f.lastModified;
    }));
    Array.from(fileList).forEach(function(file) {
        if (!file.type.startsWith('image/')) return;
        if (file.size > maxSize) return;
        var key = file.name + '|' + file.size + '|' + file.lastModified;
        if (existingKeys.has(key)) return;
        existingKeys.add(key);
        newFilesToUpload.push(file);
    });
    if (newFilesToUpload.length) markEditUploadYes();
    syncEditFilesInput();
    renderNewFilesPreview();
}

function selectSingleEditOption(el) {
    var container = el.closest('.edit-options-grid');
    container.querySelectorAll('.edit-option-btn').forEach(function(btn) { btn.classList.remove('selected'); });
    el.classList.add('selected');
    var field = container.dataset.field;
    document.getElementById('edit_' + field).value = el.dataset.value;
    if (field === 'upload_images') {
        toggleEditUploadArea(el.dataset.value);
    }
}

function marcarEliminarImagen(btn, imgName) {
    let item = btn.closest('.edit-img-item');
    item.style.opacity = '0.4';
    btn.disabled = true;
    deletedImages.push(imgName);
}

function renderNewFilesPreview() {
    var preview = document.getElementById('editFilePreview');
    if (!preview) return;
    preview.innerHTML = '';
    newFilesToUpload.forEach(function(file) {
        var wrap = document.createElement('div');
        wrap.className = 'edit-file-preview-wrap';
        var img = document.createElement('img');
        img.className = 'edit-file-preview-item';
        img.alt = file.name;
        var reader = new FileReader();
        reader.onload = function(e) { img.src = e.target.result; };
        reader.readAsDataURL(file);
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'edit-img-delete';
        btn.innerHTML = '<i class="fas fa-times"></i>';
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var idx = newFilesToUpload.indexOf(file);
            if (idx !== -1) newFilesToUpload.splice(idx, 1);
            syncEditFilesInput();
            renderNewFilesPreview();
        });
        wrap.appendChild(img);
        wrap.appendChild(btn);
        preview.appendChild(wrap);
    });
}

// Guardar edición
document.getElementById('btnGuardarEdicion').addEventListener('click', function() {
    if (!currentEditId) return;
    
    let form = document.getElementById('formEditarBriefing');
    let formData = new FormData(form);
    formData.delete('new_images[]');
    formData.delete('new_images');

    // Agregar listas de imágenes
    formData.append('deleted_images', JSON.stringify(deletedImages));
    formData.append('existing_images', JSON.stringify(existingImages.filter(img => !deletedImages.includes(img))));
    
    newFilesToUpload.forEach(file => {
        formData.append('new_images[]', file);
    });
    
    // Validación básica
    let requiredFields = ['contact_name', 'contact_email', 'contact_phone', 'company_name', 'business_type', 'differentiator', 'products_services'];
    for (let f of requiredFields) {
        if (!formData.get(f)) {
            Swal.fire('Error', 'Por favor completa los campos obligatorios.', 'warning');
            return;
        }
    }
    if (!formData.get('goals') || !formData.get('actions')) {
        Swal.fire('Error', 'Debes seleccionar al menos un objetivo y una acción.', 'warning');
        return;
    }
    var phoneVal = formData.get('contact_phone') || '';
    if (!/^\+\d{11,15}$/.test(phoneVal.replace(/\s/g, ''))) {
        Swal.fire('Error', 'Ingresa un teléfono válido de 10 dígitos con lada.', 'warning');
        return;
    }
    
    Swal.fire({
        title: 'Guardando cambios...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });
    
    $.ajax({
        url: 'actualizar_briefing.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            Swal.close();
            if (response.success) {
                Swal.fire('¡Actualizado!', 'El briefing se ha actualizado correctamente.', 'success').then(() => {
                    location.reload();
                });
            } else {
                Swal.fire('Error', response.message || 'No se pudo actualizar.', 'error');
            }
        },
        error: function() {
            Swal.close();
            Swal.fire('Error', 'Error de conexión con el servidor.', 'error');
        }
    });
});

</script>
</body>
</html>