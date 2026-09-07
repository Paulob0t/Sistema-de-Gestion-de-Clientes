<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
include "conn.php";
include "conn_hostingpro.php";
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
$db = ($sistema === 'hostingpro' || $sistema === 'planpro') ? $conn_hp : $conn;

// Clientes activos (mismo criterio que solicitudes: tipo 0 + no eliminados)
$clientesActivos = solicitudes_clientes_activos($db);

// Hide menu if loaded inside an iframe
$esIframe = isset($_GET['iframe']) && $_GET['iframe'] == 1;
if (!$esIframe) {
    include "menu.php";
}

// Check if we are in edit mode
$modo_edicion = isset($_GET['edit']) && $_GET['edit'] == 1;
$hosting_data = null;

if ($modo_edicion && isset($_GET['id_hosting'])) {
    $id_orden = $_GET['id_hosting'];
    
    if ($sistema === 'planpro') {
        // Para Plan Pro: consultar servicios_web con mapeo de campos
        $sql = "SELECT 
                    id AS id_orden,
                    cliente_id,
                    dominio AS nom_host,
                    plan_nombre AS producto_nombre,
                    plan_categoria AS tipo_producto,
                    plan_precio AS costo_producto,
                    fecha_contratacion,
                    fecha_vencimiento AS fecha_pago,
                    periodicidad,
                    moneda AS currency,
                    id_forma_pago,
                    estado AS estado_producto,
                    eliminado,
                    usuario_cpanel AS usuario,
                    password_cpanel AS contrasena,
                    url_cpanel AS url_admin,
                    ns1, ns2
                FROM servicios_web 
                WHERE id = ?";
    } else {
        // Para ConlineWeb y HostingPro: consultar hosting normal
        $sql = "SELECT * FROM hosting WHERE id_orden = ?";
    }
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $id_orden);
    $stmt->execute();
    $result = $stmt->get_result();
    $hosting_data = $result->fetch_assoc();
    $stmt->close();
}

// Default client id from GET if present
$id_cliente_default = isset($_GET['id_cliente']) ? (int) $_GET['id_cliente'] : 0;
if ($id_cliente_default > 0 && !solicitudes_cliente_es_activo($db, $id_cliente_default)) {
    $id_cliente_default = 0;
}
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if ($esIframe): require_once __DIR__ . '/includes/adm_head_meta.php'; ?>
    <title><?= htmlspecialchars(adm_document_title($modo_edicion ? 'Editar hosting' : 'Registro de hosting'), ENT_QUOTES, 'UTF-8') ?></title>
    <?= adm_favicon_markup() ?>
    <?php endif; ?>
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="css/admin-platform.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="css/admin-consultas.css?v=20250714" rel="stylesheet">
    <link href="css/admin-registros.css?v=20250714b" rel="stylesheet">

    <script>
        $(document).ready(function() {
            // Log selected client data to console
            $('#cliente').on('change', function() {
                var selectedOption = $(this).find('option:selected');
                var clienteId = $(this).val();
                var nombreContacto = selectedOption.text();

                console.log('Datos del cliente seleccionado:');
                console.log('ID:', clienteId);
                console.log('Información completa:', nombreContacto);
            });

            // In edit mode, log initial client data
            if ($('input[name="modo_edicion"]').val() === '1') {
                var clienteInfo = $('input[type="text"][readonly]').val();
                console.log('Datos del cliente (modo edición):');
                console.log(clienteInfo);
            }
        });
    </script>
</head>
<body<?php echo $esIframe ? ' class="adm-form--iframe"' : ''; ?>>

<div id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <div class="container my-4 py-2 legacy-touch" style="max-width: 96% !important;">

            <div class="adm-form-header fade-in-up">
                <div class="adm-form-header__icon">
                    <i class="fas fa-server"></i>
                </div>
                <div class="adm-form-header__body">
                    <h1 class="page-header-title mb-1">
                        <?php echo $modo_edicion ? 'Edición de Hosting' : 'Registro de Hosting'; ?>
                    </h1>
                    <p class="page-header-subtitle mb-0">
                        <?php echo $modo_edicion ? 'Modifica los datos del hosting seleccionado' : 'Completa los datos para registrar un nuevo hosting'; ?>
                    </p>
                </div>
            </div>

            <!-- Form wrapper card -->
            <div class="form-card fade-in-up">

                <form id="formHosting" class="needs-validation" novalidate>

                    <!-- Hidden fields -->
                    <input type="hidden" name="modo_edicion" value="<?php echo $modo_edicion ? '1' : '0'; ?>">
                    <input type="hidden" name="sistema" value="<?php echo $sistema; ?>">
                    <?php if ($modo_edicion): ?>
                        <input type="hidden" name="id_orden" value="<?php echo htmlspecialchars($_GET['id_hosting']); ?>">
                    <?php endif; ?>

                    <!-- ===== SECTION 1: Client & Domain ===== -->
                    <div class="section-card">
                        <div class="section-title">
                            <i class="fas fa-user"></i>
                            Información Personal
                        </div>

                        <div class="row">
                            <!-- Client -->
                            <div class="col-md-6 mb-3">
                                <label for="cliente" class="form-label required-field">Cliente</label>

                                <?php if ($modo_edicion): ?>
                                    <?php
                                    // Fetch current client data for edit mode display
                                    $row_cliente = null;
                                    if ($hosting_data && isset($hosting_data['cliente_id'])) {
                                        $sql_cliente = "SELECT nombre_contacto, correo, facturacion FROM clientes WHERE id = ?";
                                        $stmt_cliente = $db->prepare($sql_cliente);
                                        $stmt_cliente->bind_param("i", $hosting_data['cliente_id']);
                                        $stmt_cliente->execute();
                                        $result_cliente = $stmt_cliente->get_result();
                                        if ($result_cliente->num_rows > 0) {
                                            $row_cliente = $result_cliente->fetch_assoc();
                                        }
                                        $stmt_cliente->close();
                                    }
                                    ?>
                                    <input type="hidden" name="cliente_id" value="<?php echo $hosting_data ? htmlspecialchars($hosting_data['cliente_id']) : ''; ?>">
                                    <input type="hidden" name="cliente" value="<?php echo $hosting_data ? htmlspecialchars($hosting_data['cliente_id']) : ''; ?>"
                                           data-facturacion="<?php echo $row_cliente ? htmlspecialchars($row_cliente['facturacion']) : ''; ?>">
                                    <input type="hidden" id="cliente_facturacion" value="<?php echo $row_cliente ? htmlspecialchars($row_cliente['facturacion']) : ''; ?>">

                                    <div id="cliente-display">
                                        <input type="text" class="form-control"
                                               value="<?php echo ($row_cliente && $hosting_data) ? htmlspecialchars($row_cliente['nombre_contacto'].' (ID: '.$hosting_data['cliente_id'].' - '.$row_cliente['correo'].')') : ''; ?>"
                                               readonly>
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" id="cambiar_cliente" name="cambiar_cliente">
                                            <label class="form-check-label" for="cambiar_cliente">Cambiar cliente</label>
                                        </div>
                                    </div>

                                    <!-- Client select hidden initially -->
                                    <div id="cliente-select-wrapper" style="display: none;">
                                        <select id="cliente" name="cliente_nuevo" class="custom-select form-control">
                                            <option value="" selected disabled>Selecciona un cliente</option>
                                            <?php foreach ($clientesActivos as $row):
                                                $selected = ($hosting_data && isset($hosting_data['cliente_id']) && (int) $hosting_data['cliente_id'] === (int) $row['id']) ? 'selected' : '';
                                                $label = htmlspecialchars(adm_registro_cliente_option_label($row), ENT_QUOTES, 'UTF-8');
                                                $fact = (int) ($row['facturacion'] ?? 0);
                                            ?>
                                                <option value="<?= (int) $row['id'] ?>" data-facturacion="<?= $fact ?>" <?= $selected ?>><?= $label ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php else: ?>
                                    <!-- Create mode — normal select with validation -->
                                    <select id="cliente" name="cliente" class="custom-select form-control">
                                        <option value="" selected disabled>Selecciona un cliente</option>
                                        <?php foreach ($clientesActivos as $row):
                                            $facturacion = (int) ($row['facturacion'] ?? 0);
                                            $selected = $id_cliente_default === (int) $row['id'] ? 'selected' : '';
                                            $label = htmlspecialchars(adm_registro_cliente_option_label($row), ENT_QUOTES, 'UTF-8');
                                        ?>
                                            <option value="<?= (int) $row['id'] ?>" <?= $selected ?> data-facturacion="<?= $facturacion ?>"><?= $label ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                                <div class="invalid-feedback">Por favor selecciona un cliente</div>
                            </div>

                            <!-- Domain -->
                            <div class="col-md-6 mb-3">
                                <label for="dominio" class="form-label required-field">Dominios</label>
                                <select id="dominio" name="dominio" class="custom-select form-control">
                                    <option value="" <?php echo !$modo_edicion ? 'selected disabled' : ''; ?>>Selecciona un dominio</option>
                                    <?php
                                    $sql = "SELECT id_dominio, url_dominio FROM dominios WHERE eliminado = 0 ORDER BY url_dominio;";
                                    $result = $db->query($sql);
                                    if ($result && $result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            $nombre = htmlspecialchars($row["url_dominio"]);
                                            $selected = ($modo_edicion && isset($hosting_data['dominio']) && $hosting_data['dominio'] == $nombre) ? 'selected' : '';
                                            echo "<option value=\"$nombre\" $selected>$nombre</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Host name -->
                            <div class="col-md-6 mb-3">
                                <label for="nom_host" class="form-label required-field">Nombre Host</label>
                                <div class="input-group">
                                    <span class="input-group-text">cpanel.</span>
                                    <input type="text" class="form-control" name="nom_host" id="nom_host"
                                           value="<?php echo ($modo_edicion && $hosting_data && isset($hosting_data['nom_host'])) ? str_replace('cpanel.', '', htmlspecialchars($hosting_data['nom_host'])) : ''; ?>"
                                           placeholder="ejemplo.com">
                                    <div class="invalid-feedback">Por favor ingresa el resto del dominio</div>
                                </div>
                            </div>

                            <!-- Admin user -->
                            <div class="col-md-3 mb-3">
                                <label for="usuario" class="form-label">Usuario</label>
                                <input type="text" class="form-control" name="usuario" id="usuario"
                                       value="<?php echo ($modo_edicion && $hosting_data && isset($hosting_data['usuario'])) ? htmlspecialchars($hosting_data['usuario']) : ''; ?>"
                                       placeholder="admin">
                                <div class="invalid-feedback">Por favor ingresa un usuario válido</div>
                            </div>

                            <!-- Password -->
                            <div class="col-md-3 mb-3">
                                <label for="contrasena" class="form-label">Contraseña</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="contrasena" id="contrasena"
                                           value="<?php echo ($modo_edicion && $hosting_data && isset($hosting_data['contrasena'])) ? htmlspecialchars($hosting_data['contrasena']) : ''; ?>"
                                           placeholder="hola$323232">
                                    <button class="btn toggle-password" type="button" data-target="contrasena">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">Por favor ingresa una contraseña válida</div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== SECTION 2: Plan & Pricing ===== -->
                    <div class="section-card">
                        <div class="section-title">
                            <i class="fas fa-box"></i>
                            Plan y Precio
                        </div>

                        <div class="row">
                            <!-- Service type -->
                            <div class="col-md-3 mb-3">
                                <label for="tipo_producto" class="form-label">Tipo Servicio</label>
                                <input type="text" class="form-control" name="tipo_producto" id="tipo_producto"
                                       value="<?php echo ($modo_edicion && $hosting_data && isset($hosting_data['tipo_producto'])) ? htmlspecialchars($hosting_data['tipo_producto']) : ''; ?>">
                                <div class="invalid-feedback">Por favor ingresa el tipo de servicio</div>
                            </div>

                            <!-- Product type -->
                            <div class="col-md-3 mb-3">
                                <label for="producto" class="form-label required-field">Tipo Producto</label>
                                <?php if ($sistema === 'planpro' && $modo_edicion && $hosting_data): ?>
                                    <!-- Plan Pro: mostrar nombre del producto como texto -->
                                    <input type="text" class="form-control" name="producto" id="producto" 
                                           value="<?php echo isset($hosting_data['producto_nombre']) ? htmlspecialchars($hosting_data['producto_nombre']) : ''; ?>" 
                                           readonly>
                                <?php else: ?>
                                    <!-- ConlineWeb/HostingPro: select normal de planes -->
                                    <select id="producto" name="producto" class="custom-select form-control">
                                        <option value="" selected disabled>Selecciona un Producto</option>
                                        <?php
                                        // Load plans from the "planes" table
                                        $sql_planes = "SELECT id, nombre, precio FROM planes ORDER BY id";
                                        $result_planes = $db->query($sql_planes);
                                        $plan_id_edicion = ($modo_edicion && $hosting_data && isset($hosting_data['producto'])) ? $hosting_data['producto'] : '';
                                        if ($result_planes->num_rows > 0) {
                                            while ($row = $result_planes->fetch_assoc()) {
                                                $selected = ($modo_edicion && $plan_id_edicion == $row['id']) ? 'selected' : '';
                                                echo '<option value="'.$row['id'].'" data-precio="'.$row['precio'].'" '.$selected.'>'.$row['nombre'].'</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                <?php endif; ?>
                            </div>

                            <!-- Calculated price (display only) -->
                            <div class="col-md-3 mb-3">
                                <label for="costo_hosting" class="form-label">Precio</label>
                                <select id="costo_hosting" class="custom-select form-control" disabled>
                                    <?php if ($modo_edicion && $hosting_data && isset($hosting_data['costo_producto'])): ?>
                                        <option value="<?php echo htmlspecialchars($hosting_data['costo_producto']); ?>" selected>
                                            $<?php echo htmlspecialchars($hosting_data['costo_producto']); ?>
                                            <?php echo (isset($hosting_data['id_forma_pago']) && $hosting_data['id_forma_pago'] == 1) ? 'MXN' : 'USD'; ?>
                                        </option>
                                    <?php else: ?>
                                        <option value="" selected disabled>Selecciona un plan</option>
                                    <?php endif; ?>
                                </select>
                                <input type="hidden" name="costo_hosting" id="costo_hosting_valor" value="<?php echo ($modo_edicion && $hosting_data && isset($hosting_data['costo_producto'])) ? htmlspecialchars($hosting_data['costo_producto']) : ''; ?>">
                                <input type="hidden" name="costo_producto" id="costo_producto" value="<?php echo ($modo_edicion && $hosting_data && isset($hosting_data['costo_producto'])) ? htmlspecialchars($hosting_data['costo_producto']) : ''; ?>">
                            </div>

                            <!-- Currency -->
                            <div class="col-md-3 mb-3">
                                <label for="id_forma_pago" class="form-label required-field">Moneda</label>
                                <select id="id_forma_pago" name="id_forma_pago" class="custom-select form-control">
                                    <option value="" selected disabled>Selecciona moneda</option>
                                    <option value="1" <?php echo ($modo_edicion && $hosting_data && isset($hosting_data['id_forma_pago']) && $hosting_data['id_forma_pago'] == 1) ? 'selected' : ''; ?>>MXN (Pesos Mexicanos)</option>
                                    <option value="2" <?php echo ($modo_edicion && $hosting_data && isset($hosting_data['id_forma_pago']) && $hosting_data['id_forma_pago'] == 2) ? 'selected' : ''; ?>>USD (Dólares Americanos)</option>
                                </select>
                                <div class="invalid-feedback">Por favor selecciona la moneda</div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== SECTION 3: Dates ===== -->
                    <div class="section-card">
                        <div class="section-title">
                            <i class="fas fa-calendar-check"></i>
                            Registro
                        </div>

                        <div class="row">
                            <!-- Hire date -->
                            <div class="col-md-3 mb-3">
                                <label for="fecha_contratacion" class="form-label required-field">Fecha de Contratación</label>
                                <input type="date" class="form-control" id="fecha_contratacion" name="fecha_contratacion"
                                       value="<?php echo ($modo_edicion && $hosting_data && isset($hosting_data['fecha_contratacion'])) ? htmlspecialchars($hosting_data['fecha_contratacion']) : ''; ?>">
                                <div class="invalid-feedback">Por favor selecciona una fecha válida</div>
                            </div>

                            <!-- Payment date -->
                            <div class="col-md-3 mb-3">
                                <label for="fecha_pago" class="form-label required-field">Fecha de Pago</label>
                                <input type="date" class="form-control" id="fecha_pago" name="fecha_pago"
                                       value="<?php echo ($modo_edicion && $hosting_data && isset($hosting_data['fecha_pago'])) ? htmlspecialchars($hosting_data['fecha_pago']) : ''; ?>">
                                <div class="invalid-feedback">Por favor selecciona una fecha válida</div>
                            </div>

                            <?php
                            $estadoActual = 1;
                            if ($modo_edicion && $hosting_data && array_key_exists('estado_producto', $hosting_data)) {
                                $rawEstado = $hosting_data['estado_producto'];
                                if (is_string($rawEstado)) {
                                    $estadoActual = (strtolower(trim($rawEstado)) === 'activo' || $rawEstado === '1') ? 1 : 0;
                                } else {
                                    $estadoActual = ((int) $rawEstado === 1) ? 1 : 0;
                                }
                            }
                            ?>
                            <div class="col-md-3 mb-3">
                                <label for="estado_producto" class="form-label required-field">Estado del servicio</label>
                                <select id="estado_producto" name="estado_producto" class="custom-select form-control" required>
                                    <option value="1" <?php echo $estadoActual === 1 ? 'selected' : ''; ?>>Activo</option>
                                    <option value="0" <?php echo $estadoActual === 0 ? 'selected' : ''; ?>>Inactivo</option>
                                </select>
                                <div class="invalid-feedback">Selecciona si el hosting está activo o inactivo</div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== SECTION 4: DNS Configuration ===== -->
                    <div class="section-card">
                        <div class="section-title">
                            <i class="fas fa-server"></i>
                            Configuración DNS
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="ns1" class="form-label">Nameserver 1 (NS1)</label>
                                <input type="text" class="form-control" name="ns1" id="ns1"
                                       value="<?php echo ($modo_edicion && $hosting_data) ? htmlspecialchars($hosting_data['ns1'] ?? '') : 'ns1.dns.com'; ?>"
                                       placeholder="ns1.dns.com">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="ns2" class="form-label">Nameserver 2 (NS2)</label>
                                <input type="text" class="form-control" name="ns2" id="ns2"
                                       value="<?php echo ($modo_edicion && $hosting_data) ? htmlspecialchars($hosting_data['ns2'] ?? '') : 'ns2.dns.com'; ?>"
                                       placeholder="ns2.dns.com">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="ns3" class="form-label">Nameserver 3 (NS3)</label>
                                <input type="text" class="form-control" name="ns3" id="ns3"
                                       value="<?php echo ($modo_edicion && $hosting_data) ? htmlspecialchars($hosting_data['ns3'] ?? '') : ''; ?>"
                                       placeholder="Opcional">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="ns4" class="form-label">Nameserver 4 (NS4)</label>
                                <input type="text" class="form-control" name="ns4" id="ns4"
                                       value="<?php echo ($modo_edicion && $hosting_data) ? htmlspecialchars($hosting_data['ns4'] ?? '') : ''; ?>"
                                       placeholder="Opcional">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="ns5" class="form-label">Nameserver 5 (NS5)</label>
                                <input type="text" class="form-control" name="ns5" id="ns5"
                                       value="<?php echo ($modo_edicion && $hosting_data) ? htmlspecialchars($hosting_data['ns5'] ?? '') : ''; ?>"
                                       placeholder="Opcional">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="ns6" class="form-label">Nameserver 6 (NS6)</label>
                                <input type="text" class="form-control" name="ns6" id="ns6"
                                       value="<?php echo ($modo_edicion && $hosting_data) ? htmlspecialchars($hosting_data['ns6'] ?? '') : ''; ?>"
                                       placeholder="Opcional">
                            </div>
                        </div>
                    </div>

                    <div class="form-actions-bar">
                        <button type="reset" class="btn-reset">
                            <i class="fas fa-undo"></i>Limpiar
                        </button>
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-save"></i>
                            <?php echo $modo_edicion ? 'Actualizar Hosting' : 'Guardar Hosting'; ?>
                        </button>
                    </div>

                </form>
            </div>
            <!-- End form card -->

        </div>
    </div>
    <!-- End Main Content -->

    <footer class="sticky-footer bg-white">
        <div class="container my-auto">
            <div class="copyright text-center my-auto">
                <span>Copyright &copy; Your Website <?php echo date("Y"); ?></span>
            </div>
        </div>
    </footer>
</div>

<!-- Scripts -->
<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/jquery-easing/jquery.easing.min.js"></script>
<script src="js/sb-admin-2.min.js"></script>
<script src="vendor/chart.js/Chart.min.js"></script>
<script src="js/demo/chart-area-demo.js"></script>
<script src="js/demo/chart-pie-demo.js"></script>

<script>
$(document).ready(function() {
    // Toggle password visibility
    $('.toggle-password').click(function() {
        const target = $(this).data('target');
        const passwordInput = $('#' + target);
        const icon = $(this).find('i');

        if (passwordInput.attr('type') === 'password') {
            passwordInput.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            passwordInput.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });

    // Load domains for a given client
    function cargarDominios(clienteId) {
        if (!clienteId) return;

        $('#dominio').html('<option value="" selected disabled>Cargando...</option>');

        $.ajax({
            url: 'dominios_obtener.php',
            type: 'GET',
            data: { cliente_id: clienteId, sistema: '<?php echo $sistema; ?>' },
            dataType: 'json',
            success: function(data) {
                $('#dominio').html('<option value="" selected disabled>Selecciona un dominio</option>');

                if (data.length > 0) {
                    $.each(data, function(index, dominio) {
                        $('#dominio').append($('<option>', {
                            value: dominio.url_dominio,
                            text: dominio.url_dominio
                        }));
                    });
                } else {
                    $('#dominio').html('<option value="" selected disabled>No hay dominios registrados</option>');
                }
            },
            error: function() {
                $('#dominio').html('<option value="" selected disabled>Error al cargar</option>');
            }
        });
    }

    // In create mode, reload domains when client changes
    <?php if (!$modo_edicion): ?>
        $('#cliente').change(function() {
            cargarDominios($(this).val());
        });

        // If a default client is set, load their domains immediately
        <?php if ($id_cliente_default): ?>
            cargarDominios(<?php echo $id_cliente_default; ?>);
        <?php endif; ?>
    <?php endif; ?>

    // Checkbox to change client in edit mode
    $('#cambiar_cliente').on('change', function() {
        if ($(this).is(':checked')) {
            $('#cliente-display input[type="text"]').hide();
            $('#cliente-select-wrapper').show();
            // Update select name so it is submitted with the form
            $('#cliente-select-wrapper select').attr('name', 'cliente');
            // Update price and domains when a different client is selected
            $('#cliente-select-wrapper select').on('change', function() {
                actualizarPrecio();
                // Also reload domains for the new client in edit mode
                cargarDominios($(this).val());
            });
            // Immediately recalculate with the currently selected client
            actualizarPrecio();
        } else {
            $('#cliente-display input[type="text"]').show();
            $('#cliente-select-wrapper').hide();
            // Restore original field name
            $('#cliente-select-wrapper select').attr('name', 'cliente_nuevo');
            // Restore price calculation with original client
            actualizarPrecio();
        }
    });

    const tipoCambio = 19.01; // MXN to USD exchange rate

    // Single function to update pricing based on plan, currency and billing status
    function actualizarPrecio() {
        const productoId = $('#producto').val();
        const productoOption = $('#producto option:selected');
        const precioBaseRaw = productoOption.data('precio');
        const moneda = $('#id_forma_pago').val();
        let requiereFacturacion;

        // Determine billing flag based on mode
        if ($('input[name="modo_edicion"]').val() === '1') {
            const cambiarCliente = $('#cambiar_cliente').is(':checked');

            if (cambiarCliente) {
                // Use billing from the newly selected client
                const clienteSeleccionado = $('#cliente-select-wrapper select option:selected');
                requiereFacturacion = clienteSeleccionado.data('facturacion') == 1;
            } else {
                // Use billing from the original client
                requiereFacturacion = $('#cliente_facturacion').val() == 1;
            }
        } else {
            // Create mode — get billing from the select
            const clienteSeleccionado = $('#cliente option:selected');
            requiereFacturacion = clienteSeleccionado.data('facturacion') == 1;
        }

        // Clear the price select
        $('#costo_hosting').empty();

        // No product selected
        if (!productoId || !precioBaseRaw) {
            const option = new Option('Selecciona un plan primero', '');
            option.disabled = true;
            option.selected = true;
            $('#costo_hosting').append(option);
            $('#costo_hosting').prop('disabled', true);
            $('#costo_producto').val('');
            $('#costo_hosting_valor').val('');
            return;
        }

        // No currency selected
        if (!moneda) {
            const option = new Option('Selecciona una moneda primero', '');
            option.disabled = true;
            option.selected = true;
            $('#costo_hosting').append(option);
            $('#costo_hosting').prop('disabled', true);
            $('#costo_producto').val('');
            $('#costo_hosting_valor').val('');
            return;
        }

        // Compute price
        let precioBase = parseFloat(precioBaseRaw);
        let monedaTexto = 'MXN';

        // Convert to USD if needed
        if (moneda === '2') {
            precioBase = (precioBase / tipoCambio).toFixed(2);
            monedaTexto = 'USD';
        }

        let precioFinal = parseFloat(precioBase);
        let textoMostrar = '';

        if (requiereFacturacion) {
            // Apply 16% VAT
            precioFinal = (precioBase * 1.16).toFixed(2);
            textoMostrar = `$${precioBase} + IVA (16%) = $${precioFinal} ${monedaTexto}`;
        } else {
            textoMostrar = `$${precioBase} ${monedaTexto} (Sin IVA)`;
        }

        // Update price select and hidden fields
        $('#costo_hosting').append(new Option(textoMostrar, precioFinal));
        $('#costo_hosting').prop('disabled', false);
        $('#costo_producto').val(precioFinal);
        $('#costo_hosting_valor').val(precioFinal);

        console.log('Precio calculado:', {
            productoId: productoId,
            moneda: monedaTexto,
            precioBase: precioBase,
            requiereFacturacion: requiereFacturacion,
            precioFinal: precioFinal,
            modoEdicion: $('input[name="modo_edicion"]').val() === '1'
        });
    }

    // Recalculate price on product, currency or client change
    $('#producto, #id_forma_pago, #cliente').on('change', function() {
        actualizarPrecio();
    });
     // Autocompletar nombre host al seleccionar dominio
        $('#dominio').on('change', function() {
            const dominioSeleccionado = $(this).val();
            if (dominioSeleccionado) {
                $('#nom_host').val(dominioSeleccionado);
            }
        });

    // In edit mode, initialize price after DOM is ready
    <?php if ($modo_edicion): ?>
        setTimeout(function() {
            // Get billing flag from the hidden client field
            const clienteHidden = $('input[name="cliente"]');
            const requiereFacturacion = clienteHidden.data('facturacion') == 1;

            console.log('Modo edición - Datos del cliente:', {
                id: clienteHidden.val(),
                requiereFacturacion: requiereFacturacion
            });

            // Force price update with current billing info
            actualizarPrecio();
        }, 100);
    <?php endif; ?>

    // Log client selection details
    $('#cliente').on('change', function() {
        var selectedOption = $(this).find('option:selected');
        var clienteId = $(this).val();
        var nombreContacto = selectedOption.text();
        var requiereFactura = selectedOption.data('facturacion') == 1;

        console.log('Cliente seleccionado:', {
            id: clienteId,
            nombre: nombreContacto,
            requiereFactura: requiereFactura
        });
    });

    // AJAX form submission
    $('#formHosting').on('submit', function(e) {
        e.preventDefault();
        const form = this;
        const formData = new FormData(form);

        if (!form.checkValidity()) {
            $(form).addClass('was-validated');
            return;
        }

        // Debug: log all fields being sent
        console.log('Datos del formulario a enviar:');
        for (let [key, value] of formData.entries()) {
            console.log(key, value);
        }

        $.ajax({
            url: 'guardar_hosting.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: response.message
                    }).then(() => {
                        // If inside iframe, notify parent to reload or close modal
                        if (window.self !== window.top) {
                            window.parent.postMessage({ tipo: 'hosting_actualizado' }, '*');
                        } else {
                            // Otherwise redirect if needed
                            if (response.redirect) {
                                window.location.href = response.redirect;
                            }
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });

                let mensaje = 'No se pudo completar la operación.';
                try {
                    const respuesta = JSON.parse(xhr.responseText);
                    if (respuesta && respuesta.mensaje) {
                        mensaje = respuesta.mensaje;
                    }
                } catch (e) {
                    // Response was not valid JSON
                }

                Swal.fire({
                    icon: 'error',
                    title: 'Error de conexión',
                    text: mensaje,
                    footer: `Código de error: ${xhr.status}`
                });
            }
        });
    });
});
</script>

</body>
</html>