<?php
session_start();
if (isset($_SESSION['id']) && isset($_SESSION['login']) && $_SESSION['login'] == true) {
    include 'conn.php';
    $usrid = $_SESSION['id'];

    include('menu.php');

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
            END AS fecha_vencimiento_servicio
        FROM pagos p
        LEFT JOIN clientes c ON p.id_clie = c.id
        LEFT JOIN dominios d ON p.tipo_servicio = '2' AND p.id_servicio = d.id_dominio
        LEFT JOIN hosting h ON p.tipo_servicio = '1' AND p.id_servicio = h.id_orden
        LEFT JOIN planes pl ON h.producto = pl.id
        WHERE p.id_clie = ?
        ORDER BY p.fecha_pago >= CURDATE() DESC, p.fecha_pago ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $usrid);
    $stmt->execute();
    $result = $stmt->get_result();
    $pagos = [];
    if ($result && $result->num_rows > 0) {
        $pagos = $result->fetch_all(MYSQLI_ASSOC);
    }

    $pagosPagados = array_filter($pagos, function ($p) {
        return intval($p['estatus']) === 1;
    });
    $pagosPendientes = array_filter($pagos, function ($p) {
        return intval($p['estatus']) !== 1;
    });

    // Función para mostrar tabla de pagos
    function mostrarTablaPagos($pagos, $mostrarTotal = false, $tabId = 'datatable')
    {
        ?>
        <div class="table-responsive p-3">
            <table class="table modern-table" id="<?php echo $tabId; ?>" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Servicio</th>
                        <th>Nombre Servicio</th>
                        <th>Fecha Pago</th>
                        <th>Forma Pago</th>
                        <th>Monto</th>
                        <th>Moneda</th>
                        <th>Estatus</th>
                        <th>Acción</th>
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
                            <td><?php echo htmlspecialchars($row["id"]); ?></td>
                            <td>
                                <?php
                                if ($row["tipo_servicio"] == 1) {
                                    echo "Hosting";
                                } elseif ($row["tipo_servicio"] == 2) {
                                    echo "Dominio";
                                } else {
                                    echo "Otro";
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                $nombreServicio = $row["nombre_servicio"] ?? 'N/A';
                                $nombrePlan = $row["nombre_plan"] ?? '';
                                echo htmlspecialchars($nombrePlan ? $nombreServicio . ' - ' . $nombrePlan : $nombreServicio);
                                ?>
                            </td>
                            <td>
                                <?php
                                echo (!empty($row["fecha_pago"]) && $row["fecha_pago"] !== "0000-00-00")
                                    ? date("d M Y", strtotime($row["fecha_pago"]))
                                    : "";
                                ?>
                            </td>
                            <td>
                                <?php
                                switch ($row["forma_pago"]) {
                                    case 1:
                                        echo "Tarjeta";
                                        break;
                                    case 2:
                                        echo "Transferencia";
                                        break;
                                    case 3:
                                        echo "Efectivo";
                                        break;
                                    default:
                                        echo "Pendiente";
                                        break;
                                }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($row["monto"]); ?></td>
                            <td><?php echo htmlspecialchars($row["currency"]); ?></td>
                            <td>
                                <span class="status-badge <?php echo $row["estatus"] == 1 ? 'status-approved' : 'status-pending'; ?>">
                                    <?php echo htmlspecialchars($row["estatus"] == 1 ? "Aprobado" : "Pendiente"); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-actions">
                                    <button class="btn-download-invoice" data-estatus="<?php echo $row['estatus']; ?>" onclick="generarReporte(<?php echo $row['id']; ?>)">
                                        <i class="fas fa-file-pdf"></i> Nota
                                    </button>
                                    <?php if (intval($row['estatus']) !== 1): ?>
                                        <button class="btn-pay-now" data-pago-id="<?php echo $row['id']; ?>">
                                            <i class="fas fa-dollar-sign"></i> Pagar
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
            <div class="total-pendientes p-3">
                <h5><i class="fas fa-exclamation-triangle"></i> Total de Pagos Pendientes:</h5>
                <?php foreach ($monedas as $moneda => $total): ?>
                    <div class="total-amount">
                        <?php echo number_format($total, 2); ?> <?php echo htmlspecialchars($moneda); ?>
                    </div>
                <?php endforeach; ?>
                <small>* Solo se incluyen los pagos con estatus pendiente</small>
            </div>
        <?php endif; ?>
        <?php
    }

    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Pagos</title>
    </head>

    <body>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <div class="page-header">
                    <h3><i class="fas fa-credit-card"></i> Pagos</h3>
                </div>

                <!-- Nav tabs -->
                <ul class="nav-tabs" id="pagosTab" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="pendientes-tab" href="#pendientes" role="tab"
                            aria-controls="pendientes" aria-selected="true">Pendientes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="pagados-tab" href="#pagados" role="tab"
                            aria-controls="pagados" aria-selected="false">Pagados</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="todos-tab" href="#todos" role="tab"
                            aria-controls="todos" aria-selected="false">Todos</a>
                    </li>
                </ul>
                
                <!-- Tab panes -->
                <div class="tab-content" id="pagosTabContent">
                    <div class="tab-pane active" id="pendientes" role="tabpanel" aria-labelledby="pendientes-tab">
                        <?php
                        if (!empty($pagosPendientes)) {
                            mostrarTablaPagos($pagosPendientes, true, 'datatable-pendientes');
                        } else {
                            echo '<div class="alert alert-info">No hay pagos pendientes.</div>';
                        }
                        ?>
                    </div>
                    <div class="tab-pane" id="pagados" role="tabpanel" aria-labelledby="pagados-tab">
                        <?php
                        if (!empty($pagosPagados)) {
                            mostrarTablaPagos($pagosPagados, false, 'datatable-pagados');
                        } else {
                            echo '<div class="alert alert-info">No hay pagos aprobados.</div>';
                        }
                        ?>
                    </div>
                    <div class="tab-pane" id="todos" role="tabpanel" aria-labelledby="todos-tab">
                        <?php
                        if (!empty($pagos)) {
                            mostrarTablaPagos($pagos, false, 'datatable-todos');
                        } else {
                            echo '<div class="alert alert-info">No hay pagos registrados.</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Nota de pago template -->
        <div id="nota-pago-template" style="display: none;">
            <div class="nota-pago" style="width: 100%; max-width: 800px; margin: 0 auto; padding: 30px; font-family: 'Nunito', Calibri, sans-serif; background-color: white; box-shadow: 0 0 20px rgba(0,0,0,0.1);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                    <div>
                        <img src="images/logo-conline.png" style="width: 150px; height: auto; margin-bottom: 15px;">
                        <div style="font-size: 14px; color: #555;">
                            <p style="margin: 3px 0;"><strong>ConlineWeb</strong></p>
                            <p style="margin: 3px 0;">Correo: info@conlineweb.com</p>
                            <p style="margin: 3px 0;">Tel.: 477 115 1263</p>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <h1 style="margin: 0; color: var(--primary-color); font-size: 28px;">NOTA DE PAGO</h1>
                        <div style="font-size: 14px; margin-top: 10px;">
                            <strong>Número:</strong> <span id="numero-pago" style="font-weight: bold;">id_pago</span>
                        </div>
                        <div style="font-size: 14px; margin-top: 5px;">
                            <strong>Fecha:</strong> <span id="fecha-emision"><?php echo date('d/m/Y'); ?></span>
                        </div>
                    </div>
                </div>

                <div style="border-top: 2px solid #eee; margin: 20px 0;"></div>

                <div style="margin-bottom: 20px;">
                    <h3 style="color: var(--primary-color); margin-bottom: 15px; font-size: 18px;">INFORMACIÓN DEL CLIENTE</h3>
                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                        <tr>
                            <td style="padding: 8px 0; width: 30%;"><strong>Nombre:</strong></td>
                            <td style="padding: 8px 0;" id="cliente-nombre">-</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0;"><strong>Correo:</strong></td>
                            <td style="padding: 8px 0;" id="cliente-correo">-</td>
                        </tr>
                    </table>
                </div>

                <div style="margin-bottom: 20px;">
                    <h3 style="color: var(--primary-color); margin-bottom: 15px; font-size: 18px;">DETALLE DEL PAGO</h3>
                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                        <thead>
                            <tr style="background-color: #f8f9fa;">
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Descripción</th>
                                <th style="padding: 12px; text-align: center; border-bottom: 2px solid #ddd;">Cantidad</th>
                                <th style="padding: 12px; text-align: right; border-bottom: 2px solid #ddd;">Precio Unitario</th>
                                <th style="padding: 12px; text-align: right; border-bottom: 2px solid #ddd;">Total</th>
                            </tr>
                        </thead>
                        <tbody id="detalle-servicio">
                            <!-- Will be filled by JavaScript -->
                        </tbody>
                    </table>
                </div>

                <div style="border-top: 2px solid #eee; margin: 20px 0;"></div>

                <div style="display: flex; justify-content: flex-end; margin-bottom: 30px;">
                    <div style="width: 300px;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="padding: 8px 0; text-align: right;"><strong>Subtotal:</strong></td>
                                <td style="padding: 8px 0; text-align: right; width: 120px;" id="subtotal">$0.00</td>
                            </tr>
                            <tr id="impuesto-row" style="display: none;">
                                <td style="padding: 8px 0; text-align: right;"><strong>IVA (16%):</strong></td>
                                <td style="padding: 8px 0; text-align: right;" id="impuesto">$0.00</td>
                            </tr>
                            <tr style="border-top: 1px solid #ddd;">
                                <td style="padding: 12px 0; text-align: right; font-weight: bold;">Total:</td>
                                <td style="padding: 12px 0; text-align: right; font-weight: bold;" id="total">$0.00</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div style="border-top: 2px solid #eee; margin: 20px 0;"></div>

                <div style="margin-bottom: 20px;">
                    <h3 style="color: var(--primary-color); margin-bottom: 15px; font-size: 18px;">INFORMACIÓN ADICIONAL</h3>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 8px 0; width: 30%;"><strong>Forma de Pago:</strong></td>
                            <td style="padding: 8px 0;" id="forma-pago">-</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0;"><strong>Fecha de Pago:</strong></td>
                            <td style="padding: 8px 0;" id="fecha-pago">-</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0;"><strong>Estatus:</strong></td>
                            <td style="padding: 8px 0;" id="estatus-pago">-</td>
                        </tr>
                    </table>
                </div>

                <div style="margin-top: 40px; padding: 15px; background-color: #f9f9f9; border-radius: 5px;">
                    <p style="margin: 0; font-size: 13px; color: #666; text-align: center;">
                        <strong>Confirmación:</strong> Este comprobante acredita el pago del servicio mencionado. 
                        El servicio permanecerá activo hasta el <span id="fecha-expiracion" style="font-weight: bold;">21 de junio de 2026</span>.
                    </p>
                </div>

                <div style="margin-top: 30px; text-align: center;">
                    <p style="font-size: 12px; color: #999; font-style: italic;">
                        Este documento es únicamente informativo y no constituye un comprobante fiscal.
                    </p>
                </div>
            </div>
        </div>

        <!-- Scripts -->
        

        <script>
            // Asegurarse de que los pagos están definidos
            const pagos = <?php echo isset($pagos) ? json_encode($pagos) : '[]'; ?>;
            
            document.addEventListener('DOMContentLoaded', function () {
                window.generarReporte = async function (pagoId) {
                    const pagos = <?php echo json_encode($pagos); ?>;
                    const pago = pagos.find(p => p.id == pagoId);

                    if (pago.estatus != 1) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Pago pendiente',
                            text: 'No se puede generar el reporte hasta que el pago esté aprobado.',
                            confirmButtonText: 'Aceptar'
                        });
                        return;
                    }

                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = document.getElementById('nota-pago-template').innerHTML;
                    tempDiv.style.position = 'absolute';
                    tempDiv.style.left = '-9999px';
                    document.body.appendChild(tempDiv);

                    try {
                        // Fill data
                        tempDiv.querySelector('#numero-pago').textContent = pagoId;
                        tempDiv.querySelector('#cliente-nombre').textContent = pago.cliente || 'N/A';
                        tempDiv.querySelector('#cliente-correo').textContent = pago.correo_cliente || 'N/A';

                        const tipoServicio = pago.tipo_servicio == 1 ? 'Hosting' : 
                                           pago.tipo_servicio == 2 ? 'Dominio' : 'Otro';
                        
                        const formaPago = pago.forma_pago == 1 ? 'Tarjeta de crédito/débito' : 
                                        pago.forma_pago == 2 ? 'Transferencia bancaria' : 
                                        pago.forma_pago == 3 ? 'Efectivo' : 'Pendiente de pago';
                        
                        const estatus = pago.estatus == 1 ? 'Aprobado' : 'Pendiente';

                        let fechaPago = '';
                        if (pago.fecha_pago && pago.fecha_pago !== "0000-00-00") {
                            const date = new Date(pago.fecha_pago);
                            fechaPago = date.toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' });
                        }

                        // Fill payment details table
                        tempDiv.querySelector('#detalle-servicio').innerHTML = `
                            <tr>
                                <td style="padding: 12px; border-bottom: 1px solid #eee; vertical-align: top;">
                                    <strong>${tipoServicio}:</strong> ${pago.nombre_servicio || 'N/A'} ${pago.nombre_plan ? '- ' + pago.nombre_plan : ''}
                                    <div style="font-size: 13px; color: #666; margin-top: 5px;">
                                        <strong>Concepto:</strong> ${pago.concepto || 'N/A'}
                                    </div>
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid #eee; text-align: center; vertical-align: top;">1</td>
                                <td style="padding: 12px; border-bottom: 1px solid #eee; text-align: right; vertical-align: top;">${pago.monto} ${pago.currency}</td>
                                <td style="padding: 12px; border-bottom: 1px solid #eee; text-align: right; vertical-align: top;">${pago.monto} ${pago.currency}</td>
                            </tr>`;

                        // Calculate totals
                        const subtotal = (pago.facturacion == 1) ? (parseFloat(pago.monto) / 1.16) : parseFloat(pago.monto);
                        let impuesto = 0;
                        let total = parseFloat(pago.monto);
                        
                        if (pago.facturacion == 1) {
                            impuesto = total - subtotal;
                            tempDiv.querySelector('#impuesto-row').style.display = '';
                        }

                        tempDiv.querySelector('#subtotal').textContent = formatCurrency(subtotal, pago.currency);
                        tempDiv.querySelector('#impuesto').textContent = formatCurrency(impuesto, pago.currency);
                        tempDiv.querySelector('#total').textContent = formatCurrency(total, pago.currency);

                        // Fill additional info
                        tempDiv.querySelector('#forma-pago').textContent = formaPago;
                        tempDiv.querySelector('#fecha-pago').textContent = fechaPago || 'No especificada';
                        tempDiv.querySelector('#estatus-pago').textContent = estatus;

                        // Vigencia = fecha de renovación del hosting/dominio
                        let vigenciaTxt = 'No especificada';
                        const rawVig = pago.fecha_vencimiento_servicio || pago.fecha_limite_pago || '';
                        if (rawVig && rawVig !== '0000-00-00') {
                            const parts = /^([0-9]{4})-([0-9]{2})-([0-9]{2})/.exec(rawVig);
                            const d = parts
                                ? new Date(parseInt(parts[1], 10), parseInt(parts[2], 10) - 1, parseInt(parts[3], 10))
                                : new Date(rawVig);
                            if (!isNaN(d.getTime())) {
                                vigenciaTxt = d.toLocaleDateString('es-MX', {
                                    day: 'numeric',
                                    month: 'long',
                                    year: 'numeric'
                                });
                            }
                        } else if (pago.fecha_pago && pago.fecha_pago !== '0000-00-00') {
                            const base = new Date(pago.fecha_pago);
                            if (!isNaN(base.getTime())) {
                                base.setFullYear(base.getFullYear() + 1);
                                base.setDate(base.getDate() - 1);
                                vigenciaTxt = base.toLocaleDateString('es-MX', {
                                    day: 'numeric',
                                    month: 'long',
                                    year: 'numeric'
                                });
                            }
                        }
                        tempDiv.querySelector('#fecha-expiracion').textContent = vigenciaTxt;

                        // Generate PDF
                        const { jsPDF } = window.jspdf;
                        const doc = new jsPDF({
                            orientation: 'portrait',
                            unit: 'mm',
                            format: 'a4'
                        });

                        const element = tempDiv.querySelector('.nota-pago');
                        const canvas = await html2canvas(element, {
                            scale: 2,
                            useCORS: true,
                            logging: false,
                            allowTaint: true,
                            letterRendering: true
                        });

                        const imgData = canvas.toDataURL('image/png');
                        const imgWidth = doc.internal.pageSize.getWidth() - 20;
                        const imgHeight = (canvas.height * imgWidth) / canvas.width;

                        doc.addImage(imgData, 'PNG', 10, 10, imgWidth, imgHeight);
                        doc.save(`nota_pago_${pagoId}.pdf`);

                    } catch (error) {
                        console.error('Error generating PDF:', error);
                        Swal.fire('Error', 'No se pudo generar el PDF.', 'error');
                    } finally {
                        document.body.removeChild(tempDiv);
                    }
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
                        pago.tipo_servicio == 2 ? 'Dominio' : 'Otro';

                    const formaPago = pago.forma_pago == 1 ? 'Tarjeta' :
                        pago.forma_pago == 2 ? 'Transferencia' :
                            pago.forma_pago == 3 ? 'Efectivo' : 'Pendiente';

                    const estatus = pago.estatus == 1 ? 'Aprobado' : 'Pendiente';

                    // Formatear fecha
                    let fechaPago = '';
                    if (pago.fecha_pago && pago.fecha_pago !== "0000-00-00") {
                        const date = new Date(pago.fecha_pago);
                        fechaPago = date.toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' });
                    }

                    // Contenido básico mientras cargamos link Stripe
                    const contenidoBase = `
                        <div style="font-family: 'Nunito', Calibri, sans-serif; text-align: left;">
                            <div style="margin-bottom: 20px;">
                                <h4 style="text-align: center; margin-bottom: 15px; font-family: 'Nunito', Calibri, sans-serif; color: var(--primary-color);">Detalle de Pago #${pago.id}</h4>
                                <div style="max-height: 300px; overflow-y: auto;">
                                    <table style="width: 100%; border-collapse: collapse; font-family: 'Nunito', Calibri, sans-serif;">
                                        <tr style="border-bottom: 1px solid #ddd;">
                                            <th style="padding: 8px; text-align: left; font-weight: bold; background-color: #f8f9fa;">Cliente:</th>
                                            <td style="padding: 8px;">${pago.cliente || 'N/A'}</td>
                                        </tr>
                                        <tr style="border-bottom: 1px solid #ddd;">
                                            <th style="padding: 8px; text-align: left; font-weight: bold; background-color: #f8f9fa;">Correo:</th>
                                            <td style="padding: 8px;">${pago.correo_cliente || 'N/A'}</td>
                                        </tr>
                                        <tr style="border-bottom: 1px solid #ddd;">
                                            <th style="padding: 8px; text-align: left; font-weight: bold; background-color: #f8f9fa;">Servicio:</th>
                                            <td style="padding: 8px;">${tipoServicio} - ${pago.nombre_servicio || 'N/A'} ${pago.nombre_plan ? '- ' + pago.nombre_plan : ''}</td>
                                        </tr>
                                        <tr style="border-bottom: 1px solid #ddd;">
                                            <th style="padding: 8px; text-align: left; font-weight: bold; background-color: #f8f9fa;">Concepto:</th>
                                            <td style="padding: 8px;">${pago.concepto || 'N/A'}</td>
                                        </tr>
                                        <tr style="border-bottom: 1px solid #ddd;">
                                            <th style="padding: 8px; text-align: left; font-weight: bold; background-color: #f8f9fa;">Fecha de Pago:</th>
                                            <td style="padding: 8px;">${fechaPago || 'No pagado aún'}</td>
                                        </tr>
                                        <tr style="border-bottom: 1px solid #ddd;">
                                            <th style="padding: 8px; text-align: left; font-weight: bold; background-color: #f8f9fa;">Forma de Pago:</th>
                                            <td style="padding: 8px;">${formaPago}</td>
                                        </tr>
                                        <tr style="border-bottom: 1px solid #ddd;">
                                            <th style="padding: 8px; text-align: left; font-weight: bold; background-color: #f8f9fa;">Monto:</th>
                                            <td style="padding: 8px;">${pago.monto || '0'} ${pago.currency || ''}</td>
                                        </tr>
                                        <tr style="border-bottom: 1px solid #ddd;">
                                            <th style="padding: 8px; text-align: left; font-weight: bold; background-color: #f8f9fa;">Estatus:</th>
                                            <td style="padding: 8px;">${estatus}</td>
                                        </tr>
                                    </table>
                                </div>
                                <div id="stripe-payment-container" style="text-align: center; margin: 20px 0; font-family: 'Nunito', Calibri, sans-serif;">
                                    <p>Cargando opción de pago...</p>
                                </div>
                                <p style="text-align: center; margin-top: 15px; font-family: 'Nunito', Calibri, sans-serif;">Gracias por su pago.</p>
                            </div>
                        </div>`;

                    // Mostrar SweetAlert2 con el contenido base
                    Swal.fire({
                        title: 'Detalle de Pago',
                        html: contenidoBase,
                        width: '80%',
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
                                        <div style="margin-top: 15px; text-align: left; padding: 15px; border: 1px solid #ddd; border-radius: var(--border-radius); background-color: #f8f9fa; font-family: 'Nunito', Calibri, sans-serif;">
                                            <h5 style="text-align: center; margin-bottom: 15px; font-family: 'Nunito', Calibri, sans-serif; color: var(--primary-color);"><strong>Opciones de pago</strong></h5>
                                            <div style="text-align: center; margin-bottom: 15px;">
                                                <a href="${urlStripe}" target="_blank" style="display: inline-block; padding: 10px 20px; font-size: 1.1rem; color: white; background-color: var(--secondary-color); text-decoration: none; border-radius: var(--border-radius); font-family: 'Nunito', Calibri, sans-serif; transition: var(--transition);">
                                                    <i class="fas fa-credit-card"></i> Pagar con Stripe
                                                </a>
                                            </div>
                                            <p style="text-align: center; margin-bottom: 15px; font-family: 'Nunito', Calibri, sans-serif;">Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
                                                <small><a href="${urlStripe}" target="_blank" style="word-break: break-all; font-family: 'Nunito', Calibri, sans-serif; font-size: 0.8rem; color: var(--primary-color);">${urlStripe}</a></small>
                                            </p>
                                            <h6 style="font-family: 'Nunito', Calibri, sans-serif; color: var(--primary-color);"><i class="fas fa-university"></i> Transferencia bancaria</h6>
                                            <p style="font-family: 'Nunito', Calibri, sans-serif;"><strong>Nombre del titular:</strong> Jose Antonio Martinez Karam<br>
                                            <strong>Banco:</strong> Santander<br>
                                            <strong>Número de cuenta:</strong> 60622161632<br>
                                            <strong>CLAVE interbancaria:</strong> 014225606221616325</p>
                                            
                                            <h6 style="font-family: 'Nunito', Calibri, sans-serif; color: var(--primary-color);"><i class="fas fa-check-circle"></i> Confirmación de Pago:</h6>
                                            <p style="font-family: 'Nunito', Calibri, sans-serif;">
                                                Una vez realizado el pago, por favor envía el comprobante vía <strong>WhatsApp</strong> al número 
                                                <strong>477 118 1285</strong> para confirmar la renovación y validar tu transacción.
                                            </p>
                                            <p style="margin-bottom: 0; font-family: 'Nunito', Calibri, sans-serif;"><em>Si ya realizaste el pago, por favor omite este mensaje. Quedamos atentos a cualquier duda o comentario.</em></p>
                                            <p style="margin-top: 15px; text-align: right; margin-bottom: 0; font-family: 'Nunito', Calibri, sans-serif;"><strong>Atentamente,<br>El equipo de ConlineWeb</strong></p>
                                        </div>`;
                                    document.getElementById('stripe-payment-container').innerHTML = botonStripe;
                                } else {
                                    document.getElementById('stripe-payment-container').innerHTML = '<p style="color: var(--danger-color); font-family: \'Nunito\', Calibri, sans-serif;"><i class="fas fa-exclamation-circle"></i> No se pudo generar el link de pago.</p>';
                                    Swal.fire('Error', 'No se pudo generar el link de pago: ' + response.error, 'error');
                                }
                            }).fail(function () {
                                document.getElementById('stripe-payment-container').innerHTML = '<p style="color: var(--danger-color); font-family: \'Nunito\', Calibri, sans-serif;"><i class="fas fa-exclamation-circle"></i> Error al comunicarse con el servidor.</p>';
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

                // Inicializar DataTables
                function initDataTables() {
                    $('.modern-table').each(function() {
                        if ($.fn.DataTable.isDataTable(this)) {
                            $(this).DataTable().destroy();
                        }
                        
                        $(this).DataTable({
                            language: {
                                lengthMenu: "Mostrar _MENU_ elementos por página",
                                info: "Mostrando _START_ a _END_ de _TOTAL_ elementos",
                                infoEmpty: "Mostrando 0 a 0 de 0 elementos",
                                infoFiltered: "(filtrado de _MAX_ elementos totales)",
                                search: "Buscar:",
                                searchPlaceholder: "Buscar...",
                                paginate: {
                                    next: "<i class='fas fa-chevron-right'></i>",
                                    previous: "<i class='fas fa-chevron-left'></i>"
                                },
                                emptyTable: "No hay datos disponibles en la tabla",
                                zeroRecords: "No se encontraron registros que coincidan"
                            },
                            paging: true,
                            lengthChange: true,
                            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
                            pageLength: 10,
                            searching: true,
                            ordering: true,
                            info: true,
                            autoWidth: false,
                            responsive: true,
                            dom: '<"top"<"row"<"col-md-6"l><"col-md-6"f>>>rt<"bottom"<"row"<"col-md-6"i><"col-md-6"p>>>',
                            pagingType: "simple_numbers",
                            columnDefs: [
                                {
                                    targets: -1,
                                    orderable: false,
                                    searchable: false
                                }
                            ],
                            order: [[0, 'desc']],
                            initComplete: function() {
                                // Personalizar el selector de elementos por página
                                $(".dataTables_length select").addClass("form-select form-select-sm");
                                $(".dataTables_filter input").addClass("form-control form-control-sm");
                                
                                // Agregar iconos y mejorar el texto
                                $(".dataTables_length label").html(function(i, html) {
                                    return html.replace("Mostrar", '<i class="fas fa-list"></i> Mostrar');
                                });
                                
                                $(".dataTables_filter label").html(function(i, html) {
                                    return html.replace("Buscar:", '<i class="fas fa-search"></i>');
                                });
                            }
                        });
                    });
                }
                
                // Inicializar DataTables al cargar la página
                initDataTables();
                
                // Reinicializar cuando se cambie de pestaña
                $('.nav-link').on('click', function(e) {
                    e.preventDefault();
                    
                    // Remover clase active de todos los enlaces
                    $('.nav-link').removeClass('active');
                    $('.tab-pane').removeClass('active');
                    
                    // Agregar clase active al enlace clickeado
                    $(this).addClass('active');
                    
                    // Mostrar el contenido correspondiente
                    const target = $(this).attr('href');
                    $(target).addClass('active');
                    
                    // Reinicializar DataTables después de un pequeño retraso
                    setTimeout(initDataTables, 100);
                });

                // Estilos personalizados para SweetAlert2
                const style = document.createElement('style');
                style.textContent = `
                    .swal-wide {
                        max-width: 800px !important;
                        font-family: 'Nunito', Calibri, sans-serif !important;
                    }
                    .swal-html-container {
                        font-family: 'Nunito', Calibri, sans-serif !important;
                    }
                    .swal-title {
                        color: var(--primary-color) !important;
                        font-family: 'Nunito', Calibri, sans-serif !important;
                    }
                `;
                document.head.appendChild(style);
            });
        </script>

    </body>
    <?php include('footer.php'); ?>

    </html>
    <?php
} else {
    header("Location: ingreso.php");
    exit();
}
?>