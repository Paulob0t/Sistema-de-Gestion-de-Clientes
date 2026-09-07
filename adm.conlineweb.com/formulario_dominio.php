<?php
if (!defined('CW_CONN_SOFT')) {
    define('CW_CONN_SOFT', true);
}
if (!empty($_GET['iframe']) && !defined('CW_ALLOW_SAMEORIGIN_FRAME')) {
    define('CW_ALLOW_SAMEORIGIN_FRAME', true);
}
if (empty($_GET['iframe'])) {
    include 'menu.php';
} else {
    require_once __DIR__ . '/auth_middleware.php';
}
include 'conn.php';
// HostingPro solo si el sistema lo pide (evita tumbar el formulario)
$sistema_prefetch = $_GET['sistema'] ?? 'conlineweb';
if ($sistema_prefetch === 'hostingpro' || $sistema_prefetch === 'planpro') {
    include 'conn_hostingpro.php';
}
require_once __DIR__ . '/includes/helpers_clientes.php';

// Detectar sistema: conlineweb, hostingpro o planpro
$sistema = 'conlineweb';
if (isset($_GET['sistema'])) {
    if ($_GET['sistema'] === 'hostingpro') {
        $sistema = 'hostingpro';
    } elseif ($_GET['sistema'] === 'planpro') {
        $sistema = 'planpro';
    }
}
$db = ($sistema === 'hostingpro' || $sistema === 'planpro') ? ($conn_hp ?? null) : ($conn ?? null);
if (!($db instanceof mysqli)) {
    http_response_code(503);
    echo 'Sin conexión a la base de datos del sistema seleccionado.';
    exit;
}

// Clientes activos (mismo criterio que solicitudes: tipo 0 + no eliminados)
$clientesActivos = solicitudes_clientes_activos($db);

// Check if we are in edit mode
$modo_edicion = isset($_GET['edit']) && $_GET['edit'] == 1;
$dominio_data = null;

if ($modo_edicion && isset($_GET['id_dominio'])) {
    $id_dominio = $_GET['id_dominio'];
    $sql = "SELECT d.*, c.nombre_contacto as nombre_cliente, c.facturacion 
            FROM dominios d 
            LEFT JOIN clientes c ON d.cliente_id = c.id 
            WHERE d.id_dominio = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $id_dominio);
    $stmt->execute();
    $result = $stmt->get_result();
    $dominio_data = $result->fetch_assoc();
    $stmt->close();
}

// Default client id from GET if present
$id_cliente_default = isset($_GET['id_cliente']) ? (int) $_GET['id_cliente'] : 0;
if ($id_cliente_default > 0 && !solicitudes_cliente_es_activo($db, $id_cliente_default)) {
    $id_cliente_default = 0;
}

// Skip validation if novalid=1
$no_valid = isset($_GET['novalid']) && $_GET['novalid'] == 1;
$esIframe = !empty($_GET['iframe']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if ($esIframe): require_once __DIR__ . '/includes/adm_head_meta.php'; ?>
    <title><?= htmlspecialchars(adm_document_title($modo_edicion ? 'Editar dominio' : 'Registro de dominio'), ENT_QUOTES, 'UTF-8') ?></title>
    <?= adm_favicon_markup() ?>
    <?php endif; ?>
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="css/admin-platform.css" rel="stylesheet">
    <link href="vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="css/admin-consultas.css?v=20250714" rel="stylesheet">
    <link href="css/admin-registros.css?v=20250714b" rel="stylesheet">
</head>
<body<?php echo $esIframe ? ' class="adm-form--iframe"' : ''; ?>>

<div id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <div class="container my-4 py-2 legacy-touch" style="max-width: 96% !important;">

            <div class="adm-form-header fade-in-up">
                <div class="adm-form-header__icon">
                    <i class="fas fa-globe"></i>
                </div>
                <div class="adm-form-header__body">
                    <h1 class="page-header-title mb-1">
                        <?php echo $modo_edicion ? 'Edición de Dominio' : 'Registro de Dominio'; ?>
                    </h1>
                    <p class="page-header-subtitle mb-0">
                        <?php echo $modo_edicion ? 'Modifica los datos del dominio seleccionado' : 'Completa los datos para registrar un nuevo dominio'; ?>
                    </p>
                </div>
            </div>

            <!-- Form wrapper card -->
            <div class="form-card fade-in-up">

                <form id="formDominio" class="needs-validation" novalidate>

                    <!-- Hidden fields -->
                    <input type="hidden" name="modo_edicion" value="<?php echo $modo_edicion ? '1' : '0'; ?>">
                    <input type="hidden" name="sistema" value="<?php echo $sistema; ?>">
                    <?php if ($modo_edicion): ?>
                        <input type="hidden" name="id_dominio" value="<?php echo htmlspecialchars($_GET['id_dominio']); ?>">
                    <?php endif; ?>

                    <!-- ===== SECTION 1: Personal Information ===== -->
                    <div class="section-card">
                        <div class="section-title">
                            <i class="fas fa-user"></i>
                            Información Personal
                        </div>

                        <div class="row">
                            <!-- Client -->
                            <div class="col-md-3 mb-3">
                                <label for="cliente" class="form-label<?php if (!$modo_edicion) echo ' required-field'; ?>">Cliente</label>

                                <?php if ($modo_edicion): ?>
                                    <input type="hidden" name="cliente_id" value="<?php echo htmlspecialchars($dominio_data['cliente_id']); ?>">
                                    <input type="hidden" name="cliente" value="<?php echo htmlspecialchars($dominio_data['cliente_id']); ?>">
                                    <input type="hidden" id="cliente_facturacion" value="<?php echo htmlspecialchars($dominio_data['facturacion']); ?>">

                                    <div id="cliente-display">
                                        <p class="form-control-plaintext">
                                            <?php echo htmlspecialchars($dominio_data['nombre_cliente'] ?? 'Cliente ID: ' . $dominio_data['cliente_id']); ?>
                                        </p>
                                        <div class="form-check mt-1">
                                            <input class="form-check-input" type="checkbox" id="cambiar_cliente" name="cambiar_cliente">
                                            <label class="form-check-label" for="cambiar_cliente">Cambiar cliente</label>
                                        </div>
                                    </div>

                                    <div id="cliente-select-wrapper" style="display: none;">
                                        <select id="cliente" name="cliente_nuevo" class="custom-select form-control">
                                            <option value="" selected disabled>Selecciona un cliente</option>
                                            <?php foreach ($clientesActivos as $row):
                                                $selected = ((int) ($dominio_data['cliente_id'] ?? 0) === (int) $row['id']) ? 'selected' : '';
                                                $label = htmlspecialchars(adm_registro_cliente_option_label($row), ENT_QUOTES, 'UTF-8');
                                                $fact = (int) ($row['facturacion'] ?? 0);
                                            ?>
                                                <option value="<?= (int) $row['id'] ?>" data-facturacion="<?= $fact ?>" <?= $selected ?>><?= $label ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php else: ?>
                                    <select id="cliente" name="cliente" class="custom-select form-control" required>
                                        <option value="" selected disabled>Selecciona un cliente</option>
                                        <?php foreach ($clientesActivos as $row):
                                            $selected = ($id_cliente_default === (int) $row['id']) ? 'selected' : '';
                                            $label = htmlspecialchars(adm_registro_cliente_option_label($row), ENT_QUOTES, 'UTF-8');
                                            $fact = (int) ($row['facturacion'] ?? 0);
                                        ?>
                                            <option value="<?= (int) $row['id'] ?>" data-facturacion="<?= $fact ?>" <?= $selected ?>><?= $label ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Por favor selecciona un cliente</div>
                                <?php endif; ?>
                            </div>

                            <!-- Provider -->
                            <div class="col-md-3 mb-3">
                                <label for="proveedor" class="form-label<?php if (!$modo_edicion) echo ' required-field'; ?>">Proveedor</label>
                                <input type="text" class="form-control" name="proveedor" <?php if (!$modo_edicion) echo 'required'; ?>
                                       value="<?php echo $modo_edicion ? htmlspecialchars($dominio_data['proveedor']) : ''; ?>"
                                       placeholder="Nombre del proveedor">
                                <?php if (!$modo_edicion): ?>
                                    <div class="invalid-feedback">Por favor ingresa el proveedor</div>
                                <?php endif; ?>
                            </div>

                            <!-- Domain URL -->
                            <div class="col-md-3 mb-3">
                                <label for="url_dominio" class="form-label<?php if (!$modo_edicion) echo ' required-field'; ?>">Dominio</label>
                                <div class="input-group">
                                    <span class="input-group-text" style="border-radius:10px 0 0 10px;">https://</span>
                                    <input type="text" class="form-control" id="url_dominio" name="url_dominio"
                                           value="<?php echo $modo_edicion ? htmlspecialchars($dominio_data['url_dominio']) : ''; ?>"
                                           placeholder="empresa.com" style="border-radius:0 10px 10px 0;">
                                </div>
                                <?php if (!$modo_edicion): ?>
                                    <div class="invalid-feedback">Por favor ingresa el dominio</div>
                                <?php endif; ?>
                            </div>

                            <!-- Admin URL -->
                            <div class="col-md-3 mb-3">
                                <label for="url_admin" class="form-label<?php if (!$modo_edicion) echo ' required-field'; ?>">URL Administrador</label>
                                <div class="input-group">
                                    <span class="input-group-text">https://</span>
                                    <input type="text" class="form-control" id="url_admin" name="url_admin_clean"
                                           value="<?php echo $modo_edicion ? htmlspecialchars(preg_replace('#^https?://#i', '', preg_replace('#/wp-login\.php$#i', '', $dominio_data['url_admin']))) : ''; ?>"
                                           placeholder="empresa.com">
                                    <span class="input-group-text">/wp-login.php</span>
                                </div>
                                <input type="hidden" id="url_admin_final" name="url_admin" value="<?php echo $modo_edicion ? htmlspecialchars($dominio_data['url_admin']) : ''; ?>">
                                <?php if (!$modo_edicion): ?>
                                    <div class="invalid-feedback">Por favor ingresa una URL válida</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- ===== SECTION 2: Access Credentials ===== -->
                    <div class="section-card">
                        <div class="section-title">
                            <i class="fas fa-key"></i>
                            Credenciales de Acceso
                        </div>

                        <div class="row">
                            <!-- Admin user -->
                            <div class="col-md-4 mb-3">
                                <label for="usuario_admin" class="form-label<?php if (!$modo_edicion) echo ' required-field'; ?>">Usuario Admin</label>
                                <input type="text" class="form-control" name="usuario_admin" <?php if (!$modo_edicion) echo 'required'; ?>
                                       value="<?php echo $modo_edicion ? htmlspecialchars($dominio_data['usuario']) : ''; ?>"
                                       placeholder="Nombre de usuario">
                                <?php if (!$modo_edicion): ?>
                                    <div class="invalid-feedback">Por favor ingresa el usuario admin</div>
                                <?php endif; ?>
                            </div>

                            <!-- Admin password -->
                            <div class="col-md-4 mb-3">
                                <label for="contrasena_admin" class="form-label<?php if (!$modo_edicion) echo ' required-field'; ?>">Contraseña Admin</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="contrasena_admin" <?php if (!$modo_edicion) echo 'required'; ?>
                                           value="<?php echo $modo_edicion ? htmlspecialchars($dominio_data['contrasena']) : ''; ?>"
                                           placeholder="Contraseña" id="contrasena_admin">
                                    <button class="btn password-toggle" type="button" data-target="contrasena_admin">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <?php if (!$modo_edicion): ?>
                                    <div class="invalid-feedback">Por favor ingresa la contraseña admin</div>
                                <?php endif; ?>
                            </div>

                            <!-- cPanel URL -->
                            <div class="col-md-4 mb-3">
                                <label for="url_cpanel" class="form-label<?php if (!$modo_edicion) echo ' required-field'; ?>">URL cPanel</label>
                                <div class="input-group">
                                    <span class="input-group-text">https://cpanel.</span>
                                    <input type="text" class="form-control" id="url_cpanel" name="url_cpanel_clean"
                                           value="<?php echo $modo_edicion ? htmlspecialchars(preg_replace('#^https?://cpanel\.#i', '', preg_replace('#:2083/$#', '', $dominio_data['url_cpanel']))) : ''; ?>"
                                           placeholder="empresa.com">
                                    <span class="input-group-text">:2083/</span>
                                </div>
                                <input type="hidden" id="url_cpanel_final" name="url_cpanel" value="<?php echo $modo_edicion ? htmlspecialchars($dominio_data['url_cpanel']) : ''; ?>">
                                <?php if (!$modo_edicion): ?>
                                    <div class="invalid-feedback">Por favor ingresa una URL válida</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- ===== SECTION 3: DNS Configuration ===== -->
                    <div class="section-card">
                        <div class="section-title">
                            <i class="fas fa-server"></i>
                            Configuración DNS
                        </div>

                        <div class="row">
                            <?php
                            // Render NS1–NS6 fields in a loop to avoid repetition
                            for ($i = 1; $i <= 6; $i++):
                                $nsVal = $modo_edicion ? htmlspecialchars($dominio_data["ns$i"]) : '';
                            ?>
                            <div class="col-md-4 mb-3">
                                <label for="ns<?php echo $i; ?>" class="form-label">Nameserver <?php echo $i; ?> (NS<?php echo $i; ?>)</label>
                                <input type="text" class="form-control" name="ns<?php echo $i; ?>"
                                       value="<?php echo $nsVal; ?>"
                                       placeholder="ns<?php echo $i; ?>.dominio.com">
                            </div>
                            <?php endfor; ?>
                        </div>

                        <div class="row align-items-end">
                            <!-- Base cost -->
                            <div class="col-md-3 mb-3">
                                <label for="costo_dominio" class="form-label<?php if (!$modo_edicion) echo ' required-field'; ?>">Costo Base</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="costo_dominio" name="costo_dominio" step="0.01"
                                           value="<?php echo $modo_edicion ? htmlspecialchars($dominio_data['costo_dominio']) : ''; ?>"
                                           placeholder="0.00" <?php if (!$modo_edicion) echo 'required'; ?>>
                                </div>
                                <?php if (!$modo_edicion): ?>
                                    <div class="invalid-feedback">Por favor ingresa un costo válido</div>
                                <?php endif; ?>
                                <div id="iva-info" class="iva-info" style="display: none;">
                                    <i class="fas fa-receipt" style="margin-right:5px;"></i>
                                    <strong>Costo con IVA (16%):</strong> $<span id="costo-con-iva">0.00</span>
                                </div>
                            </div>

                            <!-- Currency -->
                            <div class="col-md-3 mb-3">
                                <label for="id_forma_pago" class="form-label<?php if (!$modo_edicion) echo ' required-field'; ?>">Moneda</label>
                                <select id="id_forma_pago" name="id_forma_pago" class="custom-select form-control" <?php if (!$modo_edicion) echo 'required'; ?>>
                                    <option value="" selected disabled>Selecciona moneda</option>
                                    <option value="1" <?php echo ($modo_edicion && $dominio_data['id_forma_pago'] == 1) ? 'selected' : ''; ?>>MXN (Pesos Mexicanos)</option>
                                    <option value="2" <?php echo ($modo_edicion && $dominio_data['id_forma_pago'] == 2) ? 'selected' : ''; ?>>USD (Dólares Americanos)</option>
                                </select>
                                <div class="invalid-feedback">Por favor selecciona la moneda</div>
                            </div>
                        </div>

                        <!-- Hidden final cost field -->
                        <input type="hidden" id="costo_final" name="costo_final" value="">
                    </div>

                    <!-- ===== SECTION 4: Gestión y fechas ===== -->
                    <div class="section-card">
                        <div class="section-title">
                            <i class="fas fa-calendar-check"></i>
                            Gestión y estado
                        </div>

                        <div class="row">
                            <?php
                            $val_registrado = $modo_edicion ? (int) ($dominio_data['registrado'] ?? 0) : null;
                            $val_estado = $modo_edicion ? (int) ($dominio_data['estado_dominio'] ?? 1) : 1;
                            ?>
                            <div class="col-md-3 mb-3">
                                <label for="registrado" class="form-label required-field">Gestión del dominio</label>
                                <select id="registrado" name="registrado" class="custom-select form-control" required>
                                    <option value="" disabled <?php echo $val_registrado === null ? 'selected' : ''; ?>>Selecciona una opción</option>
                                    <option value="1" <?php echo $val_registrado === 1 ? 'selected' : ''; ?>>Gestionado por ConlineWeb</option>
                                    <option value="0" <?php echo $val_registrado === 0 ? 'selected' : ''; ?>>Proveedor externo</option>
                                </select>
                                <small class="text-muted d-block mt-1">1 = ConlineWeb · 0 = externo</small>
                                <div class="invalid-feedback">Indica si lo gestiona ConlineWeb o un proveedor externo</div>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label for="estado_dominio" class="form-label required-field">Estado del dominio</label>
                                <select id="estado_dominio" name="estado_dominio" class="custom-select form-control" required>
                                    <option value="1" <?php echo $val_estado === 1 ? 'selected' : ''; ?>>Activo</option>
                                    <option value="0" <?php echo $val_estado === 0 ? 'selected' : ''; ?>>Inactivo</option>
                                </select>
                                <small class="text-muted d-block mt-1">1 = Activo · 0 = Inactivo</small>
                                <div class="invalid-feedback">Selecciona si el dominio está activo o inactivo</div>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label for="fecha_contratacion" class="form-label<?php if (!$modo_edicion) echo ' required-field'; ?>">Fecha de Contratación</label>
                                <input type="date" class="form-control" id="fecha_contratacion" name="fecha_contratacion"
                                       value="<?php echo $modo_edicion ? htmlspecialchars($dominio_data['fecha_contratacion']) : ''; ?>"
                                       <?php if (!$modo_edicion) echo 'required'; ?>>
                                <div class="invalid-feedback">Por favor selecciona una fecha válida</div>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label for="fecha_pago" class="form-label<?php if (!$modo_edicion) echo ' required-field'; ?>">Fecha de Pago</label>
                                <input type="date" class="form-control" id="fecha_pago" name="fecha_pago"
                                       value="<?php echo $modo_edicion ? htmlspecialchars($dominio_data['fecha_pago']) : ''; ?>"
                                       <?php if (!$modo_edicion) echo 'required'; ?>>
                                <div class="invalid-feedback">Por favor selecciona una fecha válida</div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions-bar">
                        <button type="reset" class="btn-reset">
                            <i class="fas fa-undo"></i>Limpiar
                        </button>
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-save"></i>
                            <?php echo $modo_edicion ? 'Actualizar Dominio' : 'Guardar Dominio'; ?>
                        </button>
                    </div>

                </form>

            </div>
            <!-- End form card -->

        </div>
    </div>
    <!-- End Main Content -->

    <!-- Footer -->
    <footer class="sticky-footer bg-white">
        <div class="container my-auto">
            <div class="copyright text-center my-auto">
                <span>Copyright &copy; Your Website <?php echo date('Y'); ?></span>
            </div>
        </div>
    </footer>
</div>

<!-- Scroll to Top -->
<a class="scroll-to-top rounded" href="#page-top">
    <i class="fas fa-angle-up"></i>
</a>

<!-- Scripts -->
<script src="vendor/jquery/jquery.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    // Exchange rate and currency tracking
    var ultimaMoneda = $('#id_forma_pago').val();
    // IVA rate
    var ivaPorcentaje = 0.16;

    function actualizarCostoFinal() {
        var costo = parseFloat($('#costo_dominio').val()) || 0;
        var clienteOption, facturacion;

        // Check if we are in edit mode and the checkbox is checked
        var modoEdicion = $('input[name="modo_edicion"]').val() == '1';
        var cambiarCliente = $('#cambiar_cliente').is(':checked');

        if (modoEdicion && !cambiarCliente) {
            // Use billing from original client
            facturacion = $('#cliente_facturacion').val();
        } else {
            // Use billing from selected client
            clienteOption = $('#cliente option:selected');
            facturacion = clienteOption.data('facturacion');
        }

        var tieneIVA = facturacion == 1 || facturacion == '1';
        var costoFinal = costo;
        if (tieneIVA) {
            costoFinal = (costo * (1 + ivaPorcentaje)).toFixed(2);
            $('#iva-info').show();
            $('#costo-con-iva').text(costoFinal);
        } else {
            $('#iva-info').hide();
        }
        $('#costo_final').val(costoFinal);
    }

    // Recalculate IVA when client or cost changes
    $('#cliente').on('change', actualizarCostoFinal);
    $('#costo_dominio').on('input', actualizarCostoFinal);
    // Calculate on load if client/cost already set
    actualizarCostoFinal();

    // Checkbox to change client in edit mode
    $('#cambiar_cliente').on('change', function() {
        if ($(this).is(':checked')) {
            $('#cliente-display p').hide();
            $('#cliente-select-wrapper').show();
            // Update select name so it's submitted in the form
            $('#cliente-select-wrapper select').attr('name', 'cliente');
            // Update IVA when a different client is selected
            $('#cliente-select-wrapper select').on('change', actualizarCostoFinal);
            // Immediately update calculation with selected client
            actualizarCostoFinal();
        } else {
            $('#cliente-display p').show();
            $('#cliente-select-wrapper').hide();
            // Restore original name
            $('#cliente-select-wrapper select').attr('name', 'cliente_nuevo');
            // Restore IVA calculation with original client
            actualizarCostoFinal();
        }
    });

    // Show/hide exchange rate and apply conversion
    $('#id_forma_pago').on('change', function() {
        var moneda = $(this).val();
        var simbolo = (moneda == '2') ? 'US$' : '$';
        var placeholder = (moneda == '2') ? '0.00 USD' : '0.00';
        var costo = parseFloat($('#costo_dominio').val());
        var tasaCambio = parseFloat($('#tasa_cambio').val()) || 20;
        if (moneda == '2') {
            $('#tasa-cambio-group').show();
        } else {
            $('#tasa-cambio-group').hide();
        }
        if (!isNaN(costo) && costo > 0 && ultimaMoneda && ultimaMoneda !== moneda) {
            if (moneda == '2' && ultimaMoneda == '1') {
                costo = (costo / tasaCambio).toFixed(2);
            } else if (moneda == '1' && ultimaMoneda == '2') {
                costo = (costo * tasaCambio).toFixed(2);
            }
            $('#costo_dominio').val(costo);
            actualizarCostoFinal();
        }
        $("#costo_dominio").prev('.input-group-text').text(simbolo);
        $("#costo_dominio").attr('placeholder', placeholder);
        ultimaMoneda = moneda;
    });

    // If exchange rate changes and currency is USD, recalculate
    $('#tasa_cambio').on('input', function() {
        var moneda = $('#id_forma_pago').val();
        if (moneda == '2') {
            var costo = parseFloat($('#costo_dominio').val());
            var tasaCambio = parseFloat($(this).val()) || 20;
            if (!isNaN(costo) && costo > 0) {
                var costoMXN = (costo * tasaCambio).toFixed(2);
                $('#costo_dominio').val((costoMXN / tasaCambio).toFixed(2));
                actualizarCostoFinal();
            }
        }
    });

    // AJAX submit — concatena los hidden fields ANTES de serializar
    $('#formDominio').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);

        // 1. Llenar hidden fields con prefijos/sufijos
        let dom = $('#url_dominio').val().trim();
        let adm = $('#url_admin').val().trim();
        let cpn = $('#url_cpanel').val().trim();
        let modoEdicion = $('input[name="modo_edicion"]').val() == '1';

        // 2. Validación manual en modo registro
        if (!modoEdicion) {
            let errores = [];
            if (!dom) errores.push('Dominio es requerido');
            if (!adm) errores.push('URL Administrador es requerida');
            if (!cpn) errores.push('URL cPanel es requerida');
            if (errores.length) {
                Swal.fire({ icon: 'error', title: 'Campos requeridos', html: errores.join('<br>') });
                return false;
            }
        }

        // Dominio se guarda sin prefijo — solo el nombre (ej. empresa.com)
        if (adm) {
            $('#url_admin_final').val('https://' + adm.replace(/^https?:\/\//i, '').replace(/\/wp-login\.php$/i, '') + '/wp-login.php');
        }
        if (cpn) {
            var cpnClean = cpn.replace(/^https?:\/\//i, '').replace(/^cpanel\./i, '').replace(/:2083\/?$/i, '');
            $('#url_cpanel_final').val('https://cpanel.' + cpnClean + ':2083/');
        }

        // 3. Serializar y enviar (parseo tolerante a HTML extra del servidor)
        var formData = form.serialize();
        $.ajax({
            url: 'guardar_dominio.php',
            type: 'POST',
            data: formData,
            dataType: 'text',
            success: function(raw) {
                var response = null;
                try {
                    var text = (raw || '').toString();
                    var start = text.indexOf('{');
                    var end = text.lastIndexOf('}');
                    if (start >= 0 && end > start) {
                        response = JSON.parse(text.substring(start, end + 1));
                    }
                } catch (parseErr) {
                    response = null;
                }
                if (!response) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        html: 'El servidor no devolvió JSON válido.<br><small>Sube <code>guardar_dominio.php</code> actualizado a cPanel y recarga sin caché.</small>'
                    });
                    return;
                }
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: response.message
                    }).then(function() {
                        if (window.self !== window.top) {
                            window.parent.postMessage({ tipo: 'dominioActualizado' }, '*');
                        } else if (response.redirect) {
                            window.location.href = response.redirect;
                        } else {
                            form[0].reset();
                        }
                    });
                } else {
                    Swal.fire('Error', response.message || 'Ocurrió un error al guardar el dominio.', 'error');
                }
            },
            error: function(xhr) {
                var msg = 'Ocurrió un error al guardar el dominio.';
                var text = (xhr.responseText || '').toString();
                try {
                    var start = text.indexOf('{');
                    var end = text.lastIndexOf('}');
                    if (start >= 0 && end > start) {
                        var parsed = JSON.parse(text.substring(start, end + 1));
                        if (parsed && parsed.message) msg = parsed.message;
                    } else if (text) {
                        msg = text.replace(/<[^>]+>/g, ' ').trim().substring(0, 200);
                    }
                } catch (e) {}
                Swal.fire('Error', msg, 'error');
            }
        });
    });

    // Toggle password visibility
    $(document).on('click', '.password-toggle', function() {
        var targetId = $(this).data('target');
        var input = $('#' + targetId);
        var icon = $(this).find('i');
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            input.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });

    // ===== Dominio / URL helpers =====
    function cleanDomain(val) {
        return val
            .replace(/^https?:\/\//i, '')
            .replace(/^cpanel\./i, '')
            .replace(/^www\./i, '')
            .replace(/\/wp-login\.php$/i, '')
            .replace(/:2083\/$/i, '')
            .replace(/\/+$/, '');
    }

    function hasProtocol(val) {
        return /https?|www\./i.test(val);
    }

    function showFormatError() {
        Swal.fire({
            icon: 'error',
            title: 'Formato incorrecto',
            text: 'No incluyas https://, cpanel. o www. \u2014 el sistema los agrega autom\u00e1ticamente.',
            timer: 2500,
            showConfirmButton: false
        });
    }

    // Efecto domin\u00f3: Dominio \u2192 Admin y cPanel
    $('#url_dominio').on('input', function() {
        let val = $(this).val();
        if (hasProtocol(val)) {
            showFormatError();
            $(this).val('');
            $('#url_admin').val('');
            $('#url_cpanel').val('');
            return;
        }
        $('#url_admin').val(val);
        $('#url_cpanel').val(val);
    });

    // Validaci\u00f3n individual en Admin y cPanel
    $('#url_admin, #url_cpanel').on('input', function() {
        let val = $(this).val();
        if (hasProtocol(val)) {
            showFormatError();
            $(this).val('');
        }
    });

});
</script>

</body>
</html>