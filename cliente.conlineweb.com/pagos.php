<?php
require_once __DIR__ . '/includes/cliente_session.php';
cliente_start_session();
if (cliente_is_logged_in()) {
    include 'conn.php';
    $clientePageTitle = 'Mis pagos';
    $usrid = $_SESSION['uid'];
include('menu.php');
    

    // Obtener el correo del cliente y guardarlo en sesión si no existe
    if (!isset($_SESSION['correo']) || empty($_SESSION['correo'])) {
        $stmt_correo = $conn->prepare("SELECT correo FROM clientes WHERE id = ?");
        $stmt_correo->bind_param("i", $usrid);
        $stmt_correo->execute();
        $result_correo = $stmt_correo->get_result();
        if ($result_correo && $result_correo->num_rows > 0) {
            $row_correo = $result_correo->fetch_assoc();
            $_SESSION['correo'] = $row_correo['correo'];
        }
    }

    $sql = "SELECT 
            p.*,
            c.nombre_contacto AS cliente,
            c.correo AS correo_cliente,
            CASE 
                WHEN p.tipo_servicio = '2' THEN d.url_dominio
                WHEN p.tipo_servicio = '1' THEN CONCAT('Producto ', h.tipo_producto)
                ELSE p.concepto
            END AS nombre_servicio,
            CASE
                WHEN p.tipo_servicio = '1' THEN pl.nombre
                ELSE NULL
            END AS nombre_plan,
            CASE
                WHEN p.tipo_servicio = '2' THEN d.fecha_pago
                WHEN p.tipo_servicio = '1' THEN h.fecha_pago
                WHEN p.tipo_servicio = '0' THEN p.fecha_limite_pago
                ELSE p.fecha_limite_pago
            END AS fecha_limite_pago,
            CASE
                WHEN p.tipo_servicio = '2' THEN d.fecha_pago
                WHEN p.tipo_servicio = '1' THEN h.fecha_pago
                WHEN p.tipo_servicio = '0' THEN p.fecha_limite_pago
                ELSE NULL
            END AS fecha_vencimiento_servicio
        FROM pagos p
        LEFT JOIN clientes c ON p.id_clie = c.id
        LEFT JOIN dominios d ON p.tipo_servicio = '2' AND p.id_servicio = d.id_dominio
        LEFT JOIN hosting h ON p.tipo_servicio = '1' AND p.id_servicio = h.id_orden
        LEFT JOIN planes pl ON h.producto = pl.id
        WHERE p.id_clie = ? AND p.Registro = 0
        ORDER BY p.fecha_pago >= CURDATE() DESC, p.fecha_pago ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $usrid);
    $stmt->execute();
    $result = $stmt->get_result();
    $pagos = [];
    if ($result && $result->num_rows > 0) {
        $pagos = $result->fetch_all(MYSQLI_ASSOC);
    }

    $pagosPagados = array_values(array_filter($pagos, function ($p) {
        return intval($p['estatus']) === 1;
    }));
    $pagosPendientes = array_values(array_filter($pagos, function ($p) {
        return intval($p['estatus']) !== 1;
    }));

    $cnt_pendientes = count($pagosPendientes);
    $cnt_pagados = count($pagosPagados);
    $cnt_todos = count($pagos);
    $totales_pendientes = [];
    foreach ($pagosPendientes as $pPend) {
        $cur = (string) ($pPend['currency'] ?? 'MXN');
        if ($cur === '') {
            $cur = 'MXN';
        }
        if (!isset($totales_pendientes[$cur])) {
            $totales_pendientes[$cur] = 0.0;
        }
        $totales_pendientes[$cur] += floatval($pPend['monto'] ?? 0);
    }

    // Función para mostrar tabla de pagos
    function mostrarTablaPagos($pagos, $mostrarTotal = false, $tabId = 'datatable', $esPendientes = false)
    {
        
        
        ?>
        <div class="pg-datatable-wrap">
            <table class="table pagos-datatable" id="<?php echo $tabId; ?>" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <?php if ($esPendientes): ?>
                        <th style="width: 40px;">
                            <input type="checkbox" id="select-all-<?php echo $tabId; ?>" onchange="toggleSelectAllCliente(this.checked)">
                        </th>
                        <?php endif; ?>
                        <th>Referencia</th>
                        <th>Tipo</th>
                        <th class="pg-desc-col">Descripción</th>
                        <?php if ($esPendientes): ?>
                        <th>Fecha de vencimiento</th>
                        <?php endif; ?>
                        <?php if (!$esPendientes): ?>
                        <th>Fecha de pago</th>
                        <?php endif; ?>
                        <?php if (!$esPendientes): ?>
                        <th>Método de pago</th>
                        <?php endif; ?>
                        <th>Monto</th>
                        <th>Moneda</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $totalPendiente = 0;
                    $monedas = [];
                    
                    foreach ($pagos as $row): 
                        if ($mostrarTotal && intval($row['estatus']) !== 1) {
                            $monto = floatval($row["monto"]);
                            $moneda = $row["currency"] ?? 'MXN';
                            
                            if (!isset($monedas[$moneda])) {
                                $monedas[$moneda] = 0;
                            }
                            $monedas[$moneda] += $monto;
                        }
                    ?>
                        <tr>
                            <?php if ($esPendientes && intval($row['estatus']) !== 1): ?>
                            <td>
                                <input type="checkbox" class="pago-checkbox-cliente" 
                                       data-id="<?php echo $row['id']; ?>"
                                       data-monto="<?php echo $row['monto']; ?>"
                                       data-currency="<?php echo $row['currency']; ?>"
                                       data-concepto="<?php echo htmlspecialchars($row['concepto'] ?? ''); ?>"
                                       onchange="actualizarSeleccionCliente()">
                            </td>
                            <?php endif; ?>
                            <td data-order="<?php echo (int) $row['id']; ?>">
                                <span class="pg-ref">#<?php echo htmlspecialchars($row["id"]); ?></span>
                            </td>
                            <td>
                                <?php
                                $tipoLabel = 'Otro';
                                $tipoClass = 'pg-tipo--otro';
                                if ($row["tipo_servicio"] == 1) {
                                    $tipoLabel = 'Hosting';
                                    $tipoClass = 'pg-tipo--hosting';
                                } elseif ($row["tipo_servicio"] == 2) {
                                    $tipoLabel = 'Dominio';
                                    $tipoClass = 'pg-tipo--dominio';
                                } elseif ($row["tipo_servicio"] == 0) {
                                    $tipoLabel = 'Servicio';
                                    $tipoClass = 'pg-tipo--servicio';
                                }
                                ?>
                                <span class="pg-tipo <?php echo $tipoClass; ?>"><?php echo $tipoLabel; ?></span>
                            </td>
                            <td class="pg-desc-cell">
                                <?php
                                $nombreServicio = $row["nombre_servicio"] ?? 'N/A';
                                $nombrePlan = $row["nombre_plan"] ?? '';
                                echo htmlspecialchars($nombrePlan ? $nombreServicio . ' - ' . $nombrePlan : $nombreServicio);
                                ?>
                            </td>
                            <?php if ($esPendientes): ?>
                            <td data-order="<?php
                                if ($row["tipo_servicio"] == 1 || $row["tipo_servicio"] == 2) {
                                    $fv = $row["fecha_vencimiento_servicio"] ?? '';
                                    echo (!empty($fv) && $fv !== '0000-00-00') ? $fv : '9999-12-31';
                                } else {
                                    $fl = $row["fecha_limite_pago"] ?? '';
                                    echo (!empty($fl) && $fl !== '0000-00-00') ? $fl : '9999-12-31';
                                }
                            ?>">
                                <?php
                                if ($row["tipo_servicio"] == 1 || $row["tipo_servicio"] == 2) {
                                    $fechaVencimiento = $row["fecha_vencimiento_servicio"] ?? '';
                                    if (!empty($fechaVencimiento) && $fechaVencimiento !== "0000-00-00") {
                                        echo date("d M Y", strtotime($fechaVencimiento));
                                    } else {
                                        echo '<span class="pg-muted">N/A</span>';
                                    }
                                } else {
                                    $fechaLimite = $row["fecha_limite_pago"] ?? '';
                                    if (!empty($fechaLimite) && $fechaLimite !== "0000-00-00") {
                                        echo date("d M Y", strtotime($fechaLimite));
                                    } else {
                                        echo '<span class="pg-muted">N/A</span>';
                                    }
                                }
                                ?>
                            </td>
                            <?php endif; ?>
                            <?php if (!$esPendientes): ?>
                            <td data-order="<?php
                                $fp = $row["fecha_pago"] ?? '';
                                echo (!empty($fp) && $fp !== '0000-00-00') ? $fp : '0000-00-00';
                            ?>">
                                <?php
                                echo (!empty($row["fecha_pago"]) && $row["fecha_pago"] !== "0000-00-00")
                                    ? date("d M Y", strtotime($row["fecha_pago"]))
                                    : '<span class="pg-muted">Pendiente</span>';
                                ?>
                            </td>
                            <?php endif; ?>
                            <?php if (!$esPendientes): ?>
                            <td>
                                <?php
                                $metodoLabel = 'Pendiente';
                                switch ($row["forma_pago"]) {
                                    case 1: $metodoLabel = 'Tarjeta'; break;
                                    case 2: $metodoLabel = 'Transferencia'; break;
                                    case 3: $metodoLabel = 'Efectivo'; break;
                                }
                                echo htmlspecialchars($metodoLabel);
                                ?>
                            </td>
                            <?php endif; ?>
                            <td data-order="<?php echo floatval($row['monto']); ?>">
                                <strong class="pg-monto"><?php echo number_format(floatval($row["monto"]), 2); ?></strong>
                            </td>
                            <td><span class="pg-moneda"><?php echo htmlspecialchars($row["currency"]); ?></span></td>
                            <td>
                                <span class="status-badge <?php echo $row["estatus"] == 1 ? 'status-approved' : 'status-pending'; ?>">
                                    <?php echo htmlspecialchars($row["estatus"] == 1 ? "Pagado" : "Pendiente"); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-actions">
                                    <?php if (intval($row['estatus']) === 1): ?>
                                    <button class="btn-download-invoice" data-estatus="<?php echo $row['estatus']; ?>" onclick="generarReporte(<?php echo $row['id']; ?>)">
                                        <i class="fas fa-file-pdf"></i> Descargar comprobante
                                    </button>
                                    <?php else: ?>
                                        <button class="btn-pay-now" data-pago-id="<?php echo $row['id']; ?>">
                                            <i class="fas fa-credit-card"></i> Pagar ahora
                                        </button>
                                        <button class="btn-download-invoice btn-download-invoice--secondary" data-estatus="<?php echo $row['estatus']; ?>" onclick="generarReporte(<?php echo $row['id']; ?>)" title="Vista previa / nota">
                                            <i class="fas fa-file-alt"></i> Ver detalle
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($mostrarTotal && !empty($monedas)): ?>
            <div class="total-pendientes">
                <h5><i class="fas fa-exclamation-triangle"></i> Total por pagar ahora</h5>
                <?php foreach ($monedas as $moneda => $total): ?>
                    <div class="total-amount">
                        $<?php echo number_format($total, 2); ?> <?php echo htmlspecialchars($moneda); ?>
                    </div>
                <?php endforeach; ?>
                <small>Suma de todos los pendientes de esta lista. Usa <strong>Pagar ahora</strong> o selecciona varios para pago múltiple.</small>
            </div>
        <?php endif; ?>
        <?php
    }

    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#000147">
        <?php require_once __DIR__ . '/includes/cliente_head_meta.php'; ?>
        <title><?= htmlspecialchars(cliente_document_title('Mis pagos'), ENT_QUOTES, 'UTF-8') ?></title>
        <?= cliente_favicon_markup() ?>
        <!-- Fuentes y estilos -->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="assets/css/bootstrap.css">
        <link rel="stylesheet" href="assets/vendors/iconly/bold.css">
        <link rel="stylesheet" href="assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
        <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
        <link rel="stylesheet" href="assets/css/app.css">
        <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
        <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
        <link rel="stylesheet" href="assets/css/pagos.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/pagos.css') ?>">
    </head>

    <body>
        <div id="app">

                <div class="main-content container-fluid pagos-page">
                    <div class="header-section">
                        <div class="header-content">
                            <div class="header-icon-container">
                                <i class="bi bi-receipt header-icon"></i>
                            </div>
                            <div class="header-text">
                                <h1 class="header-title">Mis pagos</h1>
                                <p class="header-subtitle">Paga lo pendiente y descarga comprobantes · <?= (int) $cnt_todos ?> movimiento<?= $cnt_todos === 1 ? '' : 's' ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="pg-diff">
                        <div class="pg-diff__item">
                            <strong>Esta página</strong>
                            <span>Pagar servicios, ver historial y descargar comprobantes</span>
                        </div>
                        <div class="pg-diff__item">
                            <strong>Hosting / Dominios</strong>
                            <span>Ver vencimientos y gestionar el servicio (panel, DNS)</span>
                        </div>
                        <div class="pg-diff__item">
                            <strong>Cómo pagar</strong>
                            <span>Tarjeta en línea o transferencia · luego envía comprobante por WhatsApp</span>
                        </div>
                    </div>

                    <div class="pg-summary">
                        <a href="#pendientes" class="pg-summary__card pg-summary__card--warn" data-pg-goto="pendientes">
                            <span class="pg-summary__label">Por pagar</span>
                            <strong class="pg-summary__num"><?= (int) $cnt_pendientes ?></strong>
                            <span class="pg-summary__extra">
                                <?php if ($cnt_pendientes === 0): ?>
                                    Todo al día
                                <?php else: ?>
                                    <?php
                                    $parts = [];
                                    foreach ($totales_pendientes as $mon => $tot) {
                                        $parts[] = '$' . number_format($tot, 2) . ' ' . htmlspecialchars($mon, ENT_QUOTES, 'UTF-8');
                                    }
                                    echo implode(' · ', $parts);
                                    ?>
                                <?php endif; ?>
                            </span>
                        </a>
                        <a href="#pagados" class="pg-summary__card pg-summary__card--ok" data-pg-goto="pagados">
                            <span class="pg-summary__label">Pagados</span>
                            <strong class="pg-summary__num"><?= (int) $cnt_pagados ?></strong>
                            <span class="pg-summary__extra">Con comprobante disponible</span>
                        </a>
                        <a href="#todos" class="pg-summary__card" data-pg-goto="todos">
                            <span class="pg-summary__label">Historial</span>
                            <strong class="pg-summary__num"><?= (int) $cnt_todos ?></strong>
                            <span class="pg-summary__extra">Todos los movimientos</span>
                        </a>
                    </div>

                    <?php if ($cnt_pendientes > 0): ?>
                    <div class="pg-pay-strip">
                        <i class="bi bi-info-circle-fill"></i>
                        <span>Tienes <strong><?= (int) $cnt_pendientes ?></strong> pago<?= $cnt_pendientes === 1 ? '' : 's' ?> pendiente<?= $cnt_pendientes === 1 ? '' : 's' ?>. Pulsa <strong>Pagar ahora</strong> o marca varios para un solo pago.</span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="content-card">
                        <ul class="nav-tabs" id="pagosTab" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="pendientes-tab" href="#pendientes" role="tab"
                                    aria-controls="pendientes" aria-selected="true">
                                    <i class="bi bi-clock-history"></i>
                                    <span class="pg-tab__full">Pendientes</span>
                                    <span class="pg-tab__short">Pend.</span>
                                    <span class="pg-tab-count"><?= (int) $cnt_pendientes ?></span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="pagados-tab" href="#pagados" role="tab"
                                    aria-controls="pagados" aria-selected="false">
                                    <i class="bi bi-check-circle-fill"></i>
                                    <span class="pg-tab__full">Pagados</span>
                                    <span class="pg-tab__short">Pagados</span>
                                    <span class="pg-tab-count"><?= (int) $cnt_pagados ?></span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="todos-tab" href="#todos" role="tab"
                                    aria-controls="todos" aria-selected="false">
                                    <i class="bi bi-list-ul"></i>
                                    <span class="pg-tab__full">Historial</span>
                                    <span class="pg-tab__short">Todos</span>
                                    <span class="pg-tab-count"><?= (int) $cnt_todos ?></span>
                                </a>
                            </li>
                        </ul>
                        
                        <div class="tab-content" id="pagosTabContent">
                            <div class="tab-pane active" id="pendientes" role="tabpanel" aria-labelledby="pendientes-tab">
                                <?php if (!empty($pagosPendientes)): ?>
                                <div class="pg-hint">
                                    <i class="bi bi-lightbulb-fill"></i>
                                    <span><strong>1.</strong> Pulsa <em>Pagar ahora</em> en una fila &nbsp;·&nbsp; <strong>2.</strong> O marca varias casillas y usa <em>Pago múltiple</em> (misma moneda).</span>
                                </div>
                                <?php endif; ?>

                                <div class="alert alert-info" id="pagos-cliente-selection-bar">
                                    <div class="pg-sel-info">
                                        <i class="fas fa-check-circle"></i>
                                        <span>
                                            <strong><span id="pagos-cliente-seleccionados">0</span> seleccionado(s)</strong>
                                            <span id="total-cliente-seleccionado" class="ms-2"></span>
                                        </span>
                                    </div>
                                    <div class="pg-sel-actions">
                                        <button type="button" class="pg-btn pg-btn--primary" onclick="procesarPagoGrupalCliente()">
                                            <i class="fas fa-credit-card"></i> Pagar seleccionados
                                        </button>
                                        <button type="button" class="pg-btn pg-btn--ghost" onclick="limpiarSeleccionCliente()">
                                            <i class="fas fa-times"></i> Limpiar
                                        </button>
                                    </div>
                                </div>
                                
                                <?php
                                if (!empty($pagosPendientes)) {
                                    mostrarTablaPagos($pagosPendientes, true, 'datatable-pendientes', true);
                                } else {
                                    echo '<div class="pg-empty"><div class="pg-empty__icon pg-empty__icon--ok"><i class="bi bi-check2-circle"></i></div><h5>Todo al día</h5><p>No tienes pagos pendientes.</p><div class="pg-empty-actions"><a href="hosting.php" class="pg-empty-btn pg-empty-btn--ghost">Ver hosting</a><a href="dominios.php" class="pg-empty-btn pg-empty-btn--ghost">Ver dominios</a></div></div>';
                                }
                                ?>
                            </div>
                            <div class="tab-pane" id="pagados" role="tabpanel" aria-labelledby="pagados-tab">
                                <?php if (!empty($pagosPagados)): ?>
                                <div class="pg-hint pg-hint--ok">
                                    <i class="bi bi-info-circle-fill"></i>
                                    <span>Aquí están tus pagos confirmados. Usa <strong>Descargar comprobante</strong> para el PDF.</span>
                                </div>
                                <?php endif; ?>
                                <?php
                                if (!empty($pagosPagados)) {
                                    mostrarTablaPagos($pagosPagados, false, 'datatable-pagados', false);
                                } else {
                                    echo '<div class="pg-empty"><div class="pg-empty__icon"><i class="bi bi-receipt"></i></div><h5>Sin pagos completados</h5><p>Cuando pagues, el comprobante aparecerá aquí.</p></div>';
                                }
                                ?>
                            </div>
                            <div class="tab-pane" id="todos" role="tabpanel" aria-labelledby="todos-tab">
                                <?php
                                if (!empty($pagos)) {
                                    mostrarTablaPagos($pagos, false, 'datatable-todos', false);
                                } else {
                                    echo '<div class="pg-empty"><div class="pg-empty__icon"><i class="bi bi-inbox"></i></div><h5>Sin movimientos</h5><p>No hay pagos registrados en tu cuenta.</p><div class="pg-empty-actions"><a href="tickets.php" class="pg-empty-btn">Abrir ticket</a></div></div>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
     

       <!-- Plantilla comprobante PDF -->
<div id="nota-pago-template">
    <div class="nota-pago">
        <div class="np-header">
            <div class="np-brand">
                <img src="images/logo-conline.png" alt="ConlineWeb">
                <p><strong>ConlineWeb</strong></p>
                <p>info@conlineweb.com</p>
                <p>+52 477 118 1285</p>
            </div>
            <div class="np-doc-title">
                <h1>COMPROBANTE DE PAGO</h1>
                <div class="np-meta">
                    <div><strong>Referencia:</strong> <span id="numero-pago"></span></div>
                    <div><strong>Fecha de emisión:</strong> <span id="fecha-emision"></span></div>
                    <div><strong>Fecha de pago:</strong> <span id="fecha-pago-encabezado">-</span></div>
                </div>
            </div>
        </div>

        <div class="np-section">
            <h3 class="np-section-title">Información del cliente</h3>
            <table class="np-info-table">
                <tr>
                    <td>Nombre</td>
                    <td id="cliente-nombre">-</td>
                </tr>
                <tr>
                    <td>Correo</td>
                    <td id="cliente-correo">-</td>
                </tr>
            </table>
        </div>

        <div class="np-section">
            <h3 class="np-section-title">Detalle del pago</h3>
            <table class="np-detail-table">
                <thead>
                    <tr>
                        <th>Descripción</th>
                        <th>Cant.</th>
                        <th>Precio unitario</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody id="detalle-servicio"></tbody>
            </table>
        </div>

        <div class="np-totals-wrap">
            <div class="np-totals">
                <table>
                    <tr>
                        <td>Subtotal</td>
                        <td id="subtotal">$0.00</td>
                    </tr>
                    <tr id="impuesto-row" style="display: none;">
                        <td>IVA (16%)</td>
                        <td id="impuesto">$0.00</td>
                    </tr>
                    <tr class="np-total-row">
                        <td>Total</td>
                        <td id="total">$0.00</td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="np-section">
            <h3 class="np-section-title">Información adicional</h3>
            <table class="np-info-table">
                <tr>
                    <td>Método de pago</td>
                    <td id="forma-pago">-</td>
                </tr>
                <tr>
                    <td>Fecha de pago</td>
                    <td id="fecha-pago">-</td>
                </tr>
                <tr>
                    <td>Estado</td>
                    <td id="estatus-pago">-</td>
                </tr>
            </table>
        </div>

        <div class="np-footer-note">
            <strong>Confirmación:</strong> Este comprobante acredita el pago del servicio mencionado.
            Vigencia del servicio hasta: <span id="fecha-expiracion"></span>.
        </div>

        <p class="np-legal">Documento informativo. No constituye comprobante fiscal.</p>
    </div>
</div>

        <!-- Scripts -->
        <script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
        <script src="assets/js/bootstrap.bundle.min.js"></script>
        <script src="assets/js/main.js"></script>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
        <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
        <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

                <script>
                        // Asegurarse de que los pagos están definidos
                        const pagos = <?php echo isset($pagos) ? json_encode($pagos) : '[]'; ?>;
            
                        document.addEventListener('DOMContentLoaded', function () {
                            // Helpers de fecha para evitar desfases por zona horaria con strings YYYY-MM-DD
                            function parseDateLocal(dateStr) {
                                if (!dateStr) return null;
                                // Si viene solo como YYYY-MM-DD, crear Date local para evitar tratarlo como UTC
                                const m = /^([0-9]{4})-([0-9]{2})-([0-9]{2})/.exec(dateStr);
                                if (m) {
                                    const y = parseInt(m[1], 10);
                                    const mo = parseInt(m[2], 10) - 1;
                                    const d = parseInt(m[3], 10);
                                    return new Date(y, mo, d);
                                }
                                // Fallback: dejar que el navegador lo parsee
                                const d = new Date(dateStr);
                                return isNaN(d.getTime()) ? null : d;
                            }

                            function formatDateMX(date) {
                                if (!date) return '';
                                return date.toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' });
                            }

              window.generarReporte = function (pagoId) {
    const pago = pagos.find(p => p.id == pagoId);

    if (!pago) {
        Swal.fire('Error', 'No se encontró el pago solicitado', 'error');
        return;
    }

    if (pago.estatus != 1) {
        Swal.fire({
            icon: 'warning',
            title: 'Pago pendiente',
            text: 'El comprobante estará disponible cuando el pago se marque como pagado.',
            confirmButtonText: 'Aceptar'
        });
        return;
    }

    // Mismo PDF que adm (generador TCPDF compartido)
    Swal.fire({ title: 'Generando PDF...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    const a = document.createElement('a');
    a.href = 'generar_nota_pago_pdf.php?id=' + encodeURIComponent(pagoId);
    a.download = '';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(function () {
        Swal.close();
        Swal.fire('PDF listo', 'El comprobante se está descargando.', 'success');
    }, 700);
};

                // Helper function to format currency
                function formatCurrency(amount, currency) {
                    return new Intl.NumberFormat('es-MX', {
                        style: 'currency',
                        currency: currency || 'MXN',
                        minimumFractionDigits: 2
                    }).format(amount);
                }

                // Función para mostrar el modal de pago con SweetAlert2
                function mostrarModalPago(pagoId) {
                    // Buscar el pago correspondiente
                    const pago = pagos.find(p => p.id == pagoId);

                    if (!pago) {
                        Swal.fire('Error', 'No se encontró el pago solicitado', 'error');
                        return;
                    }

                    // Determinar textos según valores
                    const tipoServicio = pago.tipo_servicio == 1 ? 'Hosting' :
                        pago.tipo_servicio == 2 ? 'Dominio' : 
                        pago.tipo_servicio == 0 ? 'Servicio' : 'Otro';

                    const formaPago = pago.forma_pago == 1 ? 'Tarjeta' :
                        pago.forma_pago == 2 ? 'Transferencia' :
                            pago.forma_pago == 3 ? 'Efectivo' : 'Pendiente';

                    const estatus = pago.estatus == 1 ? 'Pagado' : 'Pendiente';

                    // Formatear fecha
                    let fechaPago = '';
                    if (pago.fecha_pago && pago.fecha_pago !== "0000-00-00") {
                        const date = parseDateLocal(pago.fecha_pago);
                        fechaPago = formatDateMX(date);
                    }

                    // Fecha vence/límite
                    let fechaVence = 'No especificada';
                    if (pago.tipo_servicio == 1 || pago.tipo_servicio == 2) {
                        if (pago.fecha_vencimiento_servicio) {
                            const d = parseDateLocal(pago.fecha_vencimiento_servicio);
                            fechaVence = d ? formatDateMX(d) : 'No especificada';
                        }
                    } else {
                        if (pago.fecha_limite_pago) {
                            const d = parseDateLocal(pago.fecha_limite_pago);
                            fechaVence = d ? formatDateMX(d) : 'No especificada';
                        }
                    }

                    // Contenido básico mientras cargamos link Stripe
                    const contenidoBase = `
                        <div class="pago-modal">
                            <h4 class="pago-modal__title">Resumen del pago #${pago.id}</h4>
                            <table class="pago-modal__table">
                                <tr><th>Cliente</th><td>${pago.cliente || 'N/A'}</td></tr>
                                <tr><th>Correo</th><td>${pago.correo_cliente || 'N/A'}</td></tr>
                                <tr><th>Servicio</th><td>${tipoServicio} - ${pago.nombre_servicio || 'N/A'} ${pago.nombre_plan ? '- ' + pago.nombre_plan : ''}</td></tr>
                                <tr><th>Concepto</th><td>${pago.concepto || 'N/A'}</td></tr>
                                <tr><th>Fecha de pago</th><td>${fechaPago || 'Aún no pagado'}</td></tr>
                                <tr><th>Fecha de vencimiento</th><td>${fechaVence}</td></tr>
                                <tr><th>Método de pago</th><td>${formaPago}</td></tr>
                                <tr><th>Monto</th><td>${pago.monto || '0'} ${pago.currency || ''}</td></tr>
                                <tr><th>Estado</th><td>${estatus}</td></tr>
                            </table>
                            <div id="stripe-payment-container" class="pago-modal__loading">
                                <i class="fas fa-spinner"></i> Cargando opciones de pago...
                            </div>
                        </div>`;

                    // Mostrar SweetAlert2 con el contenido base
                    Swal.fire({
                        title: 'Opciones de pago',
                        html: contenidoBase,
                        width: '90%',
                        maxWidth: '800px',
                        showConfirmButton: false,
                        showCancelButton: true,
                        cancelButtonText: 'Cerrar',
                        cancelButtonColor: '#6c757d',
                        allowOutsideClick: false,
                        customClass: {
                            popup: 'swal-wide',
                            htmlContainer: 'swal-html-container'
                        },
                        didOpen: function() {
                            // Hacer petición AJAX para obtener URL de Stripe
                            $.ajax({
                                url: 'https://adm.conlineweb.com/procesar_pago.php',
                                method: 'POST',
                                data: {
                                    id: pago.id,
                                    nombre: pago.cliente || '',
                                    moneda: pago.currency ? pago.currency.toLowerCase() : 'mxn',
                                    costo: pago.monto || 0,
                                    concepto: pago.concepto || pago.nombre_servicio || 'Pago',
                                    correo: pago.correo_cliente || ''
                                },
                                dataType: 'json'
                            }).done(function (response) {
                                if (response.success) {
                                    const urlStripe = response.session_url;
                                    const botonStripe = `
                                        <div class="pago-modal__pay-box">
                                            <h5 class="pago-modal__pay-title">Elige cómo pagar</h5>
                                            <div style="text-align:center;margin-bottom:14px;">
                                                <a href="${urlStripe}" target="_blank" class="pago-modal__pay-btn">
                                                    <i class="fas fa-credit-card"></i> Pagar en línea
                                                </a>
                                            </div>
                                            <p style="text-align:center;font-size:.85rem;color:#64748b;margin-bottom:10px;">Si el botón no funciona, usa este enlace:</p>
                                            <div class="pago-modal__link-box"><a href="${urlStripe}" target="_blank">${urlStripe}</a></div>
                                            <h6 class="pago-modal__section-title"><i class="fas fa-university"></i> Transferencia bancaria</h6>
                                            <p style="font-size:.88rem;line-height:1.6;color:#475569;">
                                                <strong>Titular:</strong> Jose Antonio Martinez Karam<br>
                                                <strong>Banco:</strong> Santander<br>
                                                <strong>Cuenta:</strong> 60622161632<br>
                                                <strong>CLABE:</strong> 014225606221616325
                                            </p>
                                            <h6 class="pago-modal__section-title"><i class="fas fa-check-circle"></i> Confirmar tu pago</h6>
                                            <p style="font-size:.88rem;line-height:1.6;color:#475569;">
                                                Después de pagar, envía tu comprobante por <strong>WhatsApp</strong> al <strong>477 118 1285</strong>.
                                            </p>
                                            <p style="font-size:.82rem;color:#94a3b8;margin:12px 0 0;"><em>Si ya pagaste, puedes ignorar este mensaje.</em></p>
                                        </div>`;
                                    document.getElementById('stripe-payment-container').innerHTML = botonStripe;
                                } else {
                                    document.getElementById('stripe-payment-container').innerHTML = '<p style="color: var(--danger-color); font-family: \'Montserrat\', sans-serif;"><i class="fas fa-exclamation-circle"></i> No se pudo generar el link de pago.</p>';
                                    Swal.fire('Error', 'No se pudo generar el link de pago: ' + response.error, 'error');
                                }
                            }).fail(function () {
                                document.getElementById('stripe-payment-container').innerHTML = '<p style="color: var(--danger-color); font-family: \'Montserrat\', sans-serif;"><i class="fas fa-exclamation-circle"></i> Error al comunicarse con el servidor.</p>';
                                Swal.fire('Error', 'Error de comunicación con el servidor.', 'error');
                            });
                        }
                    });
                }

                // Escuchar el click en el botón de pagar
                $(document).on('click', '.btn-pay-now', function () {
                    const pagoId = $(this).data('pago-id');
                    mostrarModalPago(pagoId);
                });

                // DataTables — una instancia por pestaña, sin destruir al cambiar tab
                const pgDataTables = {};

                function pgTableConfig($table) {
                    const tableId = $table.attr('id') || '';
                    const isPendientes = tableId === 'datatable-pendientes';
                    const isPagados = tableId === 'datatable-pagados';
                    const refCol = isPendientes ? 1 : 0;
                    const descCol = isPendientes ? 3 : 2;
                    const dateCol = isPendientes ? 4 : 3;
                    const montoCol = isPendientes ? 5 : 5;
                    const estadoCol = isPendientes ? 7 : 7;

                    const columnDefs = [
                        { targets: -1, orderable: false, searchable: false, responsivePriority: 1 },
                        { targets: refCol, responsivePriority: 1 },
                        { targets: descCol, responsivePriority: 2 },
                        { targets: montoCol, responsivePriority: 1 },
                        { targets: estadoCol, responsivePriority: 2 },
                        { targets: isPendientes ? 0 : -99, orderable: false, searchable: false, responsivePriority: 5 }
                    ].filter(function (def) { return def.targets !== -99; });

                    let order = [[refCol, 'desc']];
                    if (isPendientes) {
                        order = [[dateCol, 'asc']];
                    } else if (isPagados) {
                        order = [[dateCol, 'desc']];
                    }

                    return {
                        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
                        paging: true,
                        lengthChange: true,
                        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'Todos']],
                        pageLength: 10,
                        searching: true,
                        ordering: true,
                        info: true,
                        autoWidth: false,
                        /*
                         * Antes se usaba el plugin Responsive, que en móvil ocultaba
                         * columnas tras un desplegable: por eso no había nada que
                         * desplazar. Con scrollX la tabla conserva sus 9 columnas y
                         * el desplazamiento horizontal ocurre dentro del cuerpo,
                         * dejando fijos el buscador y la paginación.
                         */
                        responsive: false,
                        scrollX: true,
                        dom: '<"pg-dt-toolbar"<"pg-dt-toolbar__left"l><"pg-dt-toolbar__right"f>>rt<"pg-dt-footer"<"pg-dt-footer__info"i><"pg-dt-footer__pages"p>>',
                        pagingType: 'simple_numbers',
                        columnDefs: columnDefs,
                        order: order,
                        initComplete: function () {
                            const $wrapper = $table.closest('.dataTables_wrapper');
                            $wrapper.find('.dataTables_length select').addClass('form-select form-select-sm');
                            $wrapper.find('.dataTables_filter input')
                                .addClass('form-control form-control-sm')
                                .attr('placeholder', 'Buscar referencia, servicio, monto…');
                        }
                    };
                }

                function initPgDataTable(tableId) {
                    const $table = $('#' + tableId);
                    if (!$table.length) {
                        return null;
                    }
                    if ($.fn.DataTable.isDataTable($table[0])) {
                        /* Sin el plugin Responsive basta con recalcular anchos;
                           invocar .responsive aquí lanzaba un TypeError que
                           abortaba el resto del manejador de pestañas. */
                        $table.DataTable().columns.adjust();
                        return pgDataTables[tableId];
                    }
                    pgDataTables[tableId] = $table.DataTable(pgTableConfig($table));
                    return pgDataTables[tableId];
                }

                function initVisiblePgTab() {
                    const $activePane = $('.tab-pane.active');
                    const tableId = $activePane.find('.pagos-datatable').attr('id');
                    if (tableId) {
                        initPgDataTable(tableId);
                    }
                }

                initVisiblePgTab();

                $('.nav-link').on('click', function (e) {
                    e.preventDefault();

                    $('.nav-link').removeClass('active');
                    $('.tab-pane').removeClass('active');

                    $(this).addClass('active');
                    const target = $(this).attr('href');
                    $(target).addClass('active');

                    setTimeout(function () {
                        const tableId = $(target).find('.pagos-datatable').attr('id');
                        if (tableId) {
                            initPgDataTable(tableId);
                        }
                    }, 50);
                });

                $('[data-pg-goto]').on('click', function (e) {
                    e.preventDefault();
                    const id = $(this).attr('data-pg-goto');
                    const $tab = $('#' + id + '-tab');
                    if ($tab.length) {
                        $tab.trigger('click');
                        const $tabs = $('#pagosTab');
                        if ($tabs.length) {
                            $('html, body').animate({ scrollTop: $tabs.offset().top - 24 }, 200);
                        }
                    }
                });

                // Estilos SweetAlert y selección — definidos en assets/css/pagos.css

                // ============================================
                // FUNCIONES PARA PAGOS GRUPALES DEL CLIENTE
                // ============================================
                
                let pagosSeleccionadosCliente = [];

                window.toggleSelectAllCliente = function(checked) {
                    const checkboxes = document.querySelectorAll('.pago-checkbox-cliente');
                    checkboxes.forEach(cb => {
                        if (!cb.disabled) {
                            cb.checked = checked;
                        }
                    });
                    actualizarSeleccionCliente();
                }

                window.limpiarSeleccionCliente = function() {
                    document.querySelectorAll('.pago-checkbox-cliente').forEach(cb => cb.checked = false);
                    document.querySelectorAll('[id^="select-all-"]').forEach(cb => cb.checked = false);
                    actualizarSeleccionCliente();
                }

                window.actualizarSeleccionCliente = function() {
                    const checkboxes = document.querySelectorAll('.pago-checkbox-cliente:checked');
                    const count = checkboxes.length;
                    const bar = document.getElementById('pagos-cliente-selection-bar');
                    
                    pagosSeleccionadosCliente = [];
                    
                    if (count > 0) {
                        bar.style.display = 'flex';
                        document.getElementById('pagos-cliente-seleccionados').textContent = count;
                        
                        // Calcular totales
                        const totales = {};
                        
                        checkboxes.forEach(cb => {
                            const pago = {
                                id: cb.dataset.id,
                                monto: parseFloat(cb.dataset.monto),
                                currency: cb.dataset.currency,
                                concepto: cb.dataset.concepto
                            };
                            
                            pagosSeleccionadosCliente.push(pago);
                            
                            if (!totales[pago.currency]) {
                                totales[pago.currency] = 0;
                            }
                            totales[pago.currency] += pago.monto;
                        });
                        
                        // Mostrar total
                        let textoTotal = 'Total: ';
                        Object.keys(totales).forEach((currency, index) => {
                            if (index > 0) textoTotal += ' + ';
                            textoTotal += `<strong>$${totales[currency].toFixed(2)} ${currency}</strong>`;
                        });
                        document.getElementById('total-cliente-seleccionado').innerHTML = textoTotal;
                        
                        // Validar misma moneda
                        if (Object.keys(totales).length > 1) {
                            bar.classList.add('alert-warning');
                            bar.classList.remove('alert-info');
                            document.getElementById('total-cliente-seleccionado').innerHTML += 
                                '<br><small class="text-danger"><i class="fas fa-exclamation-triangle"></i> Solo puedes pagar servicios con la misma moneda</small>';
                        } else {
                            bar.classList.remove('alert-warning');
                            bar.classList.add('alert-info');
                        }
                    } else {
                        bar.style.display = 'none';
                        pagosSeleccionadosCliente = [];
                    }
                }

                window.procesarPagoGrupalCliente = function() {
                    if (pagosSeleccionadosCliente.length === 0) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Sin selección',
                            text: 'Por favor selecciona al menos un pago'
                        });
                        return;
                    }
                    
                    // Validar misma moneda
                    const monedas = [...new Set(pagosSeleccionadosCliente.map(p => p.currency))];
                    if (monedas.length > 1) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Todos los pagos deben tener la misma moneda'
                        });
                        return;
                    }
                    
                    const totalMonto = pagosSeleccionadosCliente.reduce((sum, p) => sum + p.monto, 0);
                    const currency = pagosSeleccionadosCliente[0].currency;
                    
                    // Crear lista de servicios para mostrar
                    let listaServicios = '<ul class="pago-modal__confirm-list">';
                    pagosSeleccionadosCliente.forEach(p => {
                        listaServicios += `<li><strong>${p.concepto}</strong> — $${p.monto.toFixed(2)} ${currency}</li>`;
                    });
                    listaServicios += '</ul>';

                    Swal.fire({
                        title: 'Confirmar pago múltiple',
                        html: `
                            <div class="pago-modal__confirm">
                                <p><strong>Servicios seleccionados:</strong> ${pagosSeleccionadosCliente.length}</p>
                                ${listaServicios}
                                <hr style="border:none;border-top:1px solid #e2e8f0;margin:16px 0;">
                                <p style="text-align:center;margin:0;"><strong>Total a pagar</strong></p>
                                <p class="pago-modal__confirm-total">$${totalMonto.toFixed(2)} ${currency}</p>
                                <p style="font-size:.88rem;color:#64748b;text-align:center;margin-top:8px;">Se realizará un solo cargo por todos los servicios seleccionados.</p>
                            </div>
                        `,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#000147',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: '<i class="fas fa-credit-card"></i> Continuar al pago',
                        cancelButtonText: 'Cancelar',
                        customClass: {
                            popup: 'swal-wide'
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            enviarPagoGrupalCliente();
                        }
                    });
                }

                window.enviarPagoGrupalCliente = function() {
                    Swal.fire({
                        title: 'Procesando...',
                        text: 'Preparando tu pago',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Obtener correo del cliente de la sesión
                    const correoCliente = '<?php echo isset($_SESSION['correo']) ? $_SESSION['correo'] : ''; ?>';
                    
                    fetch('https://adm.conlineweb.com/procesar_pago_grupal.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            pagos: pagosSeleccionadosCliente,
                            correo: correoCliente
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Redirigir a Stripe
                            window.location.href = data.session_url;
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.error || 'Error al procesar el pago'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Error de conexión con el servidor'
                        });
                    });
                }

                // Inicializar
                actualizarSeleccionCliente();
            });
        </script>

        <?php include 'footer.php'; ?>
    </body>
    </html>
    <?php
} else {
    header("Location: ingreso.php");
    exit();
}
?>