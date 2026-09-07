<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'menu.php';
include 'conn.php';
include 'obtener_municipios.php';

// Get states
$sql_estados = "SELECT id_estado, estado FROM estados ORDER BY estado";
$result_estados = $conn->query($sql_estados);

$sql = "SELECT id, empresa, `nombre_contacto`,correo FROM clientes";
$result = $conn->query($sql);
?>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="css/admin-platform.css" rel="stylesheet">
    <link href="css/admin-consultas.css?v=20250714" rel="stylesheet">
    <link href="css/admin-registros.css?v=20250714b" rel="stylesheet">
    <style>
        .facturacion-toggle {
            display: flex;
            align-items: center;
            gap: 14px;
            width: 100%;
            min-height: 46px;
            margin: 0;
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #f8fafc;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
            user-select: none;
        }
        .facturacion-toggle:hover {
            border-color: #c7d2fe;
            background: #f5f7ff;
        }
        .facturacion-toggle.is-on {
            border-color: #a5b4fc;
            background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
        }
        .facturacion-toggle__switch {
            position: relative;
            width: 44px;
            height: 26px;
            flex-shrink: 0;
            border-radius: 999px;
            background: #cbd5e1;
            transition: background 0.2s;
        }
        .facturacion-toggle.is-on .facturacion-toggle__switch {
            background: #000147;
        }
        .facturacion-toggle__switch::after {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #fff;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.2);
            transition: transform 0.2s;
        }
        .facturacion-toggle.is-on .facturacion-toggle__switch::after {
            transform: translateX(18px);
        }
        .facturacion-toggle__copy {
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 0;
        }
        .facturacion-toggle__title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
        }
        .facturacion-toggle__title i {
            color: #000147;
        }
        .facturacion-toggle__hint {
            font-size: 0.75rem;
            font-weight: 500;
            color: #64748b;
            line-height: 1.3;
        }
        .facturacion-toggle input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
            width: 0;
            height: 0;
        }
        .facturacion-field-label {
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 6px;
            display: block;
        }
    </style>
</head>

<div id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <div class="container my-4 py-2 legacy-touch" style="max-width: 96% !important;">

            <div class="adm-form-header fade-in-up">
                <div class="adm-form-header__icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="adm-form-header__body">
                    <h1 class="page-header-title mb-1">Registro de Cliente</h1>
                    <p class="page-header-subtitle mb-0">Completa los datos para registrar un nuevo cliente</p>
                </div>
            </div>

            <!-- Form wrapper card -->
            <div class="form-card fade-in-up">

                <form class="needs-validation" method="POST" novalidate enctype="multipart/form-data">

                    <!-- ===== SECTION 1: Personal Information ===== -->
                    <div class="section-card">
                        <div class="section-title">
                            <i class="fas fa-user"></i>
                            Información Personal
                        </div>

                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="nombre" class="form-label required-field">Nombre</label>
                                <input type="text" class="form-control" id="nombre" name="nombre"
                                    value="<?php echo htmlspecialchars($cliente['nombre'] ?? ''); ?>"
                                    placeholder="Juan">
                                <div class="invalid-feedback">Por favor ingresa un nombre</div>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label for="apellidos" class="form-label required-field">Apellido</label>
                                <input type="text" class="form-control" id="apellido" name="apellido"
                                    value="<?php echo htmlspecialchars($cliente['apellido'] ?? ''); ?>"
                                    placeholder="Pérez López">
                                <div class="invalid-feedback">Por favor ingresa los apellidos</div>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label for="telefono" class="form-label required-field">Teléfono</label>
                                <input type="tel" class="form-control" id="telefono" name="telefono"
                                    value="<?php echo htmlspecialchars($cliente["telefono"] ?? ''); ?>"
                                    placeholder="55 1234 5678">
                                <div class="invalid-feedback">Por favor ingresa un teléfono válido</div>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label for="email" class="form-label required-field">Correo</label>
                                <input type="email" class="form-control" id="email" name="email"
                                    value="<?php echo htmlspecialchars($cliente["correo"] ?? ''); ?>"
                                    placeholder="juan@empresa.com">
                                <div class="invalid-feedback">Por favor ingresa un correo válido</div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="empresa" class="form-label">Empresa</label>
                                <input type="text" class="form-control" id="empresa" name="empresa"
                                    value="<?php echo htmlspecialchars($cliente["empresa"] ?? ''); ?>"
                                    placeholder="Nombre de la empresa">
                            </div>

                            <div class="col-md-6 mb-3">
                                <span class="facturacion-field-label">Facturación</span>
                                <label class="facturacion-toggle" id="facturacionToggle">
                                    <input class="form-check-input" type="checkbox" id="requiereFacturacion"
                                        name="requiereFacturacion">
                                    <span class="facturacion-toggle__switch" aria-hidden="true"></span>
                                    <span class="facturacion-toggle__copy">
                                        <span class="facturacion-toggle__title">
                                            <i class="fas fa-file-invoice"></i>
                                            Requiere facturación
                                        </span>
                                        <span class="facturacion-toggle__hint">Activa IVA y datos fiscales del cliente</span>
                                    </span>
                                </label>
                                <input type="hidden" name="facturacion" id="facturacion" value="0">
                            </div>
                        </div>
                    </div>

                    <!-- ===== SECTION 2: Datos fiscales (hidden by default) ===== -->
                    <div id="seccionEmpresarial" class="section-card" style="display: none;">
                        <div class="section-title">
                            <i class="fas fa-file-invoice-dollar"></i>
                            Datos fiscales
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="rsocial" class="form-label required-field">Razón Social</label>
                                <input type="text" class="form-control" id="rsocial" name="rsocial"
                                    value="<?php echo htmlspecialchars($cliente["rsocial"] ?? ''); ?>"
                                    placeholder="Razón social completa">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="rfc" class="form-label required-field">RFC</label>
                                <input type="text" class="form-control" id="rfc" name="rfc"
                                    value="<?php echo htmlspecialchars($cliente["rfc"] ?? ''); ?>"
                                    placeholder="XAXX010101000">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="especificacion" class="form-label">Especificación</label>
                                <input type="text" id="especificacion" name="especificacion" class="form-control" value="">
                            </div>

                            <div class="col-md-12 mb-3">
                                <label for="constancia_fiscal" class="form-label">Constancia de Situación Fiscal</label>
                                <input type="file" class="form-control" id="constancia_fiscal" name="constancia_fiscal"
                                    accept=".pdf,.jpg,.jpeg,.png">
                                <?php if (!empty($cliente['constancia_situacion_fiscal'])): ?>
                                    <div class="form-text mt-2">
                                        <i class="fas fa-paperclip" style="margin-right:4px;"></i>
                                        Archivo actual:
                                        <a href="uploads/<?php echo htmlspecialchars($cliente['constancia_situacion_fiscal']); ?>"
                                            target="_blank" class="file-current-link">
                                            <?php echo htmlspecialchars($cliente['constancia_situacion_fiscal']); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- ===== SECTION 3: Address (hidden by default) ===== -->
                    <div id="seccionDireccion" class="section-card" style="display: none;">
                        <div class="section-title">
                            <i class="fas fa-map-marker-alt"></i>
                            Dirección
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="calle" class="form-label required-field">Calle</label>
                                <input type="text" class="form-control" id="calle" name="calle"
                                    value="<?php echo htmlspecialchars($cliente["calle"] ?? ''); ?>"
                                    placeholder="Av. Principal">
                                <div class="invalid-feedback">Por favor ingresa la calle</div>
                            </div>

                            <div class="col-md-2 mb-3">
                                <label for="next" class="form-label required-field">N° Exterior</label>
                                <input type="text" class="form-control" id="next" name="next"
                                    value="<?php echo htmlspecialchars($cliente["next"] ?? ''); ?>"
                                    placeholder="123">
                                <div class="invalid-feedback">Por favor ingresa el número</div>
                            </div>

                            <div class="col-md-2 mb-3">
                                <label for="nint" class="form-label">N° Interior</label>
                                <input type="text" class="form-control" id="nint" name="nint"
                                    value="<?php echo htmlspecialchars($cliente["nint"] ?? ''); ?>"
                                    placeholder="A">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="colonia" class="form-label required-field">Colonia</label>
                                <input type="text" class="form-control" id="colonia" name="colonia"
                                    value="<?php echo htmlspecialchars($cliente["col"] ?? ''); ?>"
                                    placeholder="Centro">
                                <div class="invalid-feedback">Por favor ingresa la colonia</div>
                            </div>

                            <div class="col-md-2 mb-3">
                                <label for="cp" class="form-label required-field">Código Postal</label>
                                <input type="text" class="form-control" id="cp" name="cp"
                                    value="<?php echo htmlspecialchars($cliente["cp"] ?? ''); ?>"
                                    placeholder="01000" pattern="[0-9]{5}">
                                <div class="invalid-feedback">Código postal de 5 dígitos</div>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label for="pais" class="form-label required-field">País</label>
                                <input type="text" class="form-control" name="pais" placeholder="País" id="pais"
                                    value="<?php echo htmlspecialchars($cliente["pais"] ?? ''); ?>">
                            </div>

                            <div class="col-md-3 mb-3">
                                <label for="estado" class="form-label">Estado</label>
                                <select id="estado" name="estado" class="custom-select form-control">
                                    <option value="">Selecciona un estado</option>
                                    <?php
                                    $result_estados->data_seek(0);
                                    while ($row_estado = $result_estados->fetch_assoc()):
                                        $selected = ($row_estado['id_estado'] == $cliente['estado']) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo $row_estado['id_estado']; ?>" <?php echo $selected; ?>>
                                            <?php echo $row_estado['estado']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="municipio" class="form-label">Ciudad</label>
                                <select id="municipio" name="municipio" class="custom-select form-control">
                                    <option value="<?php echo htmlspecialchars($cliente['ciudad'] ?? ''); ?>" selected>
                                        <?php echo htmlspecialchars($cliente['ciudad'] ?? ''); ?>
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- ===== SECTION 4: Security ===== -->
                    <div class="section-card">
                        <div class="section-title">
                            <i class="fas fa-lock"></i>
                            Seguridad
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="contrasena" class="form-label required-field">Contraseña</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="contrasena" id="contrasena"
                                        placeholder="Mínimo 8 caracteres" minlength="8">
                                    <button class="btn toggle-password" type="button" data-target="contrasena">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">La contraseña debe contener al menos 8 caracteres</div>
                                <div class="invalid-feedback">La contraseña debe tener mínimo 8 caracteres</div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="confirmPassword" class="form-label required-field">Confirmar Contraseña*</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirmPassword"
                                        placeholder="Repite tu contraseña">
                                    <button class="btn toggle-password" type="button" data-target="confirmPassword">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">Las contraseñas no coinciden</div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions-bar justify-content-end">
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-save"></i>Guardar Cliente
                        </button>
                    </div>

                </form>
            </div>
            <!-- End form card -->

        </div>
    </div>
    <!-- End Content Wrapper -->

    <!-- Scroll to Top -->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Logout Modal (unchanged) -->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Ready to Leave?</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">Select "Logout" below if you are ready to end your current session.</div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
                    <a class="btn btn-primary" href="login.html">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap core JavaScript -->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="js/sb-admin-2.min.js"></script>
    <script src="vendor/chart.js/Chart.min.js"></script>
    <script src="js/demo/chart-area-demo.js"></script>
    <script src="js/demo/chart-pie-demo.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    $(document).ready(function () {
        // Function to show/hide sections based on billing requirement
        function toggleSecciones() {
            const requiereFacturacion = $('#requiereFacturacion').prop('checked');
            $('#facturacion').val(requiereFacturacion ? '1' : '0');
            $('#seccionEmpresarial, #seccionDireccion').toggle(requiereFacturacion);
            $('#facturacionToggle').toggleClass('is-on', requiereFacturacion);

            if (requiereFacturacion) {
                $('#rsocial, #rfc, #calle, #next, #colonia, #cp, #pais').prop('required', true);
                $('#nint, #constancia_fiscal, #especificacion, #empresa').prop('required', false);
            } else {
                $('#seccionEmpresarial input, #seccionDireccion input, #seccionDireccion select').prop('required', false);
                $('#seccionEmpresarial input, #seccionDireccion input, #seccionDireccion select').val('');
                $('#seccionEmpresarial input, #seccionDireccion input, #seccionDireccion select').removeClass('is-invalid');
            }
        }

        // Checkbox change event
        $('#requiereFacturacion').change(toggleSecciones);

        // Initialize state on page load
        toggleSecciones();

        // State change — load municipalities
        $('#estado').change(function () {
            var estados_id_estado = $(this).val();
            if (estados_id_estado) {
                $.ajax({
                    url: 'obtener_municipios.php',
                    type: 'POST',
                    data: { estados_id_estado: estados_id_estado },
                    success: function (data) {
                        $('#municipio').html(data);
                    }
                });
            } else {
                $('#municipio').html('<option value="" selected disabled>Selecciona un municipio</option>');
            }
        });

        // Toggle password visibility
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function () {
                const targetId = this.getAttribute('data-target');
                const passwordInput = document.getElementById(targetId);
                const icon = this.querySelector('i');

                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    icon.classList.remove('bi-eye');
                    icon.classList.add('bi-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                }
            });
        });

        // Password confirmation validation
        $('#confirmPassword').on('input', function() {
            const password = $('#contrasena').val();
            const confirmPassword = $(this).val();
            
            if (password !== confirmPassword) {
                this.setCustomValidity("Las contraseñas no coinciden");
            } else {
                this.setCustomValidity("");
            }
        });

        // Form submission
        $('form.needs-validation').on('submit', function (e) {
            e.preventDefault();

            // Validate passwords match
            if ($('#contrasena').val() !== $('#confirmPassword').val()) {
                $('#confirmPassword')[0].setCustomValidity("Las contraseñas no coinciden");
                $('#confirmPassword')[0].reportValidity();
                return;
            } else {
                $('#confirmPassword')[0].setCustomValidity("");
            }

            const requiereFacturacion = $('#requiereFacturacion').prop('checked');
            
            // Validate form
            if (!this.checkValidity()) {
                e.stopPropagation();
                $(this).addClass('was-validated');
                return;
            }

            Swal.fire({
                title: 'Guardando...',
                html: 'Por favor espere mientras se procesa la petición',
                allowOutsideClick: false,
                allowEscapeKey: false,
                allowEnterKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                    
                    // Prepare form data
                    const form = this;
                    const formData = new FormData();
                    
                    // Add basic fields manually (empresa siempre va, fuera de facturación)
                    const camposBasicos = ['nombre', 'apellido', 'telefono', 'email', 'contrasena', 'empresa'];
                    camposBasicos.forEach(campo => {
                        formData.append(campo, $(`#${campo}`).val());
                    });
                    
                    // If billing required, add fiscal + address fields
                    if(requiereFacturacion) {
                        const camposFacturacion = ['rsocial', 'rfc', 'especificacion', 
                                                 'calle', 'next', 'nint', 'colonia', 'cp', 
                                                 'pais', 'estado', 'municipio'];
                        camposFacturacion.forEach(campo => {
                            formData.append(campo, $(`#${campo}`).val());
                        });
                        
                        // Add file only if one was selected
                        const archivo = $('#constancia_fiscal')[0].files[0];
                        if(archivo && archivo.size > 0) {
                            formData.append('constancia_fiscal', archivo);
                        }
                    }
                    
                    formData.append('facturacion', requiereFacturacion ? 1 : 0);
                    
                    // Send via AJAX
                    $.ajax({
                        url: 'guardar_cliente.php',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            Swal.close();
                            if (response.success) {
                                Swal.fire({
                                    position: 'center',
                                    icon: 'success',
                                    title: 'Cliente Guardado',
                                    html: `<div class='text-left'>
                                             <p>✅ Cliente agregado correctamente</p>
                                          </div>`,
                                    showConfirmButton: true,
                                    confirmButtonText: 'Aceptar',
                                    confirmButtonColor: '#ffc107'
                                }).then(() => {
                                    // Reset the form
                                    $('form.needs-validation')[0].reset();
                                    
                                    // Hide billing sections
                                    $('#seccionEmpresarial, #seccionDireccion').hide();
                                    
                                    // Uncheck billing checkbox
                                    $('#requiereFacturacion').prop('checked', false);
                                    $('#facturacion').val('0');
                                    $('#facturacionToggle').removeClass('is-on');
                                    
                                    // Remove validation classes
                                    $('form.needs-validation').removeClass('was-validated');
                                    
                                    // Reset municipality select
                                    $('#municipio').html('<option value="" selected disabled>Selecciona un municipio</option>');
                                    
                                    // Reset state select
                                    $('#estado').val('');
                                    
                                    // Focus first field
                                    $('#nombre').focus();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: response.message || 'Hubo un error al procesar la solicitud.',
                                    confirmButtonText: 'Entendido',
                                    confirmButtonColor: '#dc3545'
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            Swal.close();
                            let errorMessage = 'Hubo un error al procesar la solicitud.';
                            try {
                                const response = JSON.parse(xhr.responseText);
                                if (response.message) {
                                    errorMessage = response.message;
                                }
                            } catch (e) {}
                            
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: errorMessage,
                                confirmButtonText: 'Entendido',
                                confirmButtonColor: '#dc3545'
                            });
                        }
                    });
                }
            });
        });
    });
    </script>

</body>
</html>