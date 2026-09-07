<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
include "conn.php";

// Asegurarse de que cualquier salida de PHP venga después del HTML básico y scripts necesarios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_start(); // Iniciar el buffer de salida
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta name="robots" content="noindex, nofollow">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body style="display: none">
   
<?php




// Obtener lista de estados
$sql_estados = "SELECT id_estado, estado FROM estados ORDER BY estado";
$result_estados = $conn->query($sql_estados);

// Verificar si se recibió un ID válido
if (isset($_GET["id"]) && is_numeric($_GET["id"])) {
    $id = $_GET["id"];

    // Consulta para obtener datos del cliente
    $sql = "SELECT * FROM clientes WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);

    // Consulta para obtener datos de dominios
    $sql3 = "SELECT * FROM dominios WHERE cliente_id = ?";
    $stmt3 = $conn->prepare($sql3);
    $stmt3->bind_param("i", $id);

    if ($stmt3->execute()) {
        $result3 = $stmt3->get_result();
        $dominios = $result3->fetch_all(MYSQLI_ASSOC);
        $stmt3->close();
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error en la consulta de dominio: " . addslashes($stmt3->error) . "',
                confirmButtonText: 'Aceptar'
            });
        </script>";
    }

    // Consulta para obtener datos de hosting
    $sql4 = "SELECT * FROM hosting WHERE cliente_id = ?";
    $stmt4 = $conn->prepare($sql4);
    $stmt4->bind_param("i", $id);

    if ($stmt4->execute()) {
        $result4 = $stmt4->get_result();
        $hostings = $result4->fetch_all(MYSQLI_ASSOC);
        $stmt4->close();
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error en la consulta de hosting: " . addslashes($stmt4->error) . "',
                confirmButtonText: 'Aceptar'
            });
        </script>";
    }

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $cliente = $result->fetch_assoc();
        $stmt->close();

        // Consulta para obtener datos de login
        $sql2 = "SELECT * FROM login WHERE id = ?";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("i", $id);

        if ($stmt2->execute()) {
            $result2 = $stmt2->get_result();
            $login = $result2->fetch_assoc();
            $stmt2->close();

            // Debug en consola
            echo "<script>
                console.log('Cliente:', " . json_encode($cliente) . ");
                console.log('Login:', " . json_encode($login) . ");
                console.log('Dominios:', " . json_encode($dominios) . ");
                console.log('Hostings:', " . json_encode($hostings) . ");
            </script>";

        } else {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error en la consulta de login: " . addslashes($stmt2->error) . "',
                    confirmButtonText: 'Aceptar'
                });
            </script>";
        }
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error en la consulta de cliente: " . addslashes($stmt->error) . "',
                confirmButtonText: 'Aceptar'
            });
        </script>";
    }
} else {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'ID no válido',
            confirmButtonText: 'Aceptar',
            willClose: () => {
                window.location.href = 'lista_clientes.php';
            }
        });
    </script>";
    exit();
}






?>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <title>Modificar Cliente</title>
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <style>
    
    .form-check-input:checked {
    background-color: #000147 !important;
    border-color: #000147!important;
}
.text-primary {
    color:#000147!important;
}
.btn-primary {
    background-color: #000147 !important;
    border-color: #000147 !important; /* Opcional: cambia también el borde */
}

        .mt-custom {
            margin-top: 20px;
        }
        .is-invalid {
    border-color: #dc3545 !important;
}

.invalid-feedback {
    color: #dc3545;
    font-size: 0.875em;
    margin-top: 0.25rem;
}
    * {
        font-family: 'Montserrat', sans-serif;
    }
    .red{
        color:red;
    }
    .form-label{
        font-weight:500 !important;
    }

    .titulo {
       font-size: 40px;
        font-weight: 600;
        font-family: 'Poppins', sans-serif !important;
        color: white;
        /*display: flex;*/
        /*align-items: center;*/
        /*justify-content: center;*/
        
    }
    
    .subsubtitulo {
        font-size: 30px;
        font-weight: 600;
        font-family: 'Poppins', sans-serif !important;
    }

    .instrucciones {
        color: #6c757d;
        font-size: 16px;
        margin-top: 0.5rem;
        margin-bottom: 1rem;
        font-weight: 200;

    }

    .banner-image {
        position: relative;
        /* Asegúrate de que los elementos hijos se posicionen bien */
        background-image: url(img/banner-form.png);
        background-size: cover;
        background-position: center;
        height: 400px;
    }

    .banner-image::after {
        content: "";
        /* Necesario para generar el pseudo-elemento */
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.25); /* Aumentado la opacidad del overlay */
        pointer-events: none;
        /* Permite que los clics pasen a la imagen de fondo */
    }

    /* Asegurarnos que el texto esté por encima del overlay */
.banner-image .titulo,
.banner-image .instrucciones {
    position: relative;
    z-index: 1; /* Esto hace que el texto aparezca por encima del overlay */
}

.banner-image .logo-li {
    position: relative;
    z-index: 1; /* Asegura que el logo aparezca por encima del overlay */
    filter: brightness(100%) contrast(100%); /* Mantiene los colores originales */
}

.titulo {
    color: #ffffff;  /* Aseguramos que el título sea blanco */
}

.instrucciones.ins-inicio {
    color: #ffffff !important; /* Forzamos el color blanco para las instrucciones */
}

    /*.form-control,*/
    /*.btn {*/
    /*    border-radius: 0 !important;*/
    /*}*/

    input:focus,
    textarea:focus,
    select:focus {
        outline: none !important;
        border-color: #7f7f7f !important;
        /* Esto hereda el color original del borde */
    }

    /*input:active {}*/



    /*.btn-primary {*/
        /* font-family: 'Poppins', sans-serif; */
    /*    border: 1px solid #7f7f7f;*/
    /*    outline: none;*/
    /*    background-color: white;*/
    /*    cursor: pointer;*/
    /*    color: #7f7f7f;*/

    /*    padding: 0.7vw;*/

    /*}*/

    /*.btn {*/
    /*    padding: 8px 25px !important*/
    /*}*/

    /*.btn-primary:hover {*/
    /*    background-color: #7f7f7f !important;*/
    /*    border: 1px solid #7f7f7f;*/
    /*}*/


    /*.btn-primary .active {*/
    /*    background-color: #7f7f7f !important;*/
    /*    color: white;*/
    /*    font-weight: 600;*/
    /*    text-align: center;*/
    /*}*/


    /*.label-checkbox {*/

    /*    width: 50%;*/
    /*    text-align: left;*/
    /*}*/




    @media (max-width: 576px) {
        .titulo {
       font-size: 30px;
       text-align:center;
      
        
    }
    
    .banner-image{
        padding:10px;
         background-image: url(img/2024-05-15.webp);
    }
    .ins-inicio{
         text-align:center;
    }
        .subtitulo {
            font-size: 24px;
        }

        .instrucciones {

            font-size: 14px;


        }

        .form-label {
            font-size: 14px !important;
        }
         .logo-li{
            width:250px !important;
            
        }
    }
    .logo-li{
            width:250px;
            
        }
        
        .enlace_politica{
             color: inherit; /* Quita el color azul */
    text-decoration: none; /* Quita la línea de abajo */
        }
    </style>
    <div class="w-100 banner-image">
        <div class="d-flex flex-column align-items-center justify-content-center h-100">
            <img class="logo-li" src="img/c-online_completo.png"><br><br>
            <h1 class="titulo">Confirma tu contacto</h1>
            <p class="instrucciones ins-inicio text-white text-center">
           Te notificaremos solo por los medios que tengas actualizados.
            </p>
         
        </div>
    </div>
    <div class="container mb-5">
        <!--<div class="d-flex justify-content-center mt-5 mb-3">-->
        <!--    <img src="images/logo.png" alt="Logo de mi empresa" style="width: 290px; height: auto;">-->
        <!--</div>-->
         <p class=" mb-5 instrucciones">
                <h2 style="color: #dc3545; margin-bottom: 15px;">¡Importante! Ayúdanos a mantenernos en contacto contigo!</h2>
        <p style="color: #333; font-size: 16px; line-height: 1.6; margin-bottom: 10px;">Estimado cliente:</p>
        <p style="color: #333; font-size: 16px; line-height: 1.6; margin-bottom: 10px;">Queremos asegurarnos de que siempre estés al tanto de las renovaciones de tus servicios, como hosting, dominios y otros productos contratados.</p>
        <p style="color: #333; font-size: 16px; line-height: 1.6; margin-bottom: 10px;">Estos serán nuestros medios principales para informarte sobre fechas de vencimiento, recordatorios de pago y actualizaciones importantes.
Mantener tus datos actualizados te ayudará a evitar interrupciones en tu servicio.

</p>
        <p style="color: #333; font-size: 16px; line-height: 1.6; margin-bottom: 10px;">Es un proceso muy rápido y solo te tomará un minuto.<br>¡Gracias por tu atención y preferencia!</p>
              
            </p>
            <hr>
        
        <div id="content-wrapper" class="d-flex flex-column mt-5">
            <div class="container-fluid">
                <div id="content">
                    <!-- Agregamos enctype="multipart/form-data" para permitir subida de archivos -->
                    <form class="row g-3 needs-validation" method="POST" novalidate enctype="multipart/form-data">
                        <fieldset class="col-12 border p-3 mb-4 rounded">
                            <legend class="float-none w-auto px-2 text-primary">Información Personal</legend>
<input type="hidden"  id="especificacion" name="especificacion"
                                        value=""
                                       >
                                       <input type="hidden"  id="id" name="id"
                                        value="<?php echo htmlspecialchars($id ?? ''); ?>"
                                       >
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="nombre" class="form-label">Nombre de Cliente*</label>
                                    <input type="text" class="form-control" id="nombre" name="nombre" required
                                        value="<?php echo htmlspecialchars($cliente["nombre_contacto"] ?? ''); ?>"
                                        placeholder="Nombre de Contacto">
                                    <div class="invalid-feedback">Por favor ingresa un nombre</div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="nombre" class="form-label">Nombre de Empresa (opcional)</label>
                                    <input type="text" class="form-control" id="empresa" name="empresa" required
                                        value="<?php echo htmlspecialchars($cliente["empresa"] ?? ''); ?>"
                                        placeholder="Escribe la empresa aqui">
                                    <div class="invalid-feedback">Por favor ingresa un nombre de empresa</div>
                                </div>
                                <div class="col-md-4 mb-3">
                            <label for="telefono" class="form-label">+Code &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Teléfono (10 dígitos)*</label>
                            <div class="input-group">
                                <select class="form-select" id="country_code" name="country_code" style="max-width: 110px;" required>
                                    <option value="" disabled selected>Código</option>
                                </select>
                                <input type="tel" class="form-control" id="telefono" name="telefono" required
                                    value="<?php echo htmlspecialchars($cliente["telefono"] ?? ''); ?>"
                                    placeholder="55 1234 5678" maxlength="10" pattern="[0-9]{10}" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0,10);">
                            </div>
                            <div class="invalid-feedback">Por favor ingresa un teléfono válido</div>
                        </div>
                              
                               
                            </div>
                            <div class="row">
                                  <div class="col-md-4 mb-3">
                                    <label for="email" class="form-label">Correo*</label>
                                        <span class="text-muted small d-block">
                                            (Asegúrese de que sea un correo vigente, activo y que revise con frecuencia).
                                        </span>
                                    <input type="email" class="form-control" id="email" name="email" required
                                        value="<?php echo htmlspecialchars($cliente["correo"] ?? ''); ?>"
                                        placeholder="juan@empresa.com">
                                    <div class="invalid-feedback">Por favor ingresa un correo válido</div>
                                </div>
                            </div>
                        </fieldset>
                        <span class="fw-bold">
                             ¿Necesitas factura?
                        </span>
                        <span>
                           
                            Si requieres facturación, es necesario que actives la casilla correspondiente, registres tus datos fiscales completos y cargues tu Constancia de Situación Fiscal vigente (no mayor a 3 meses).
                            En caso de que no cuentes con la constancia en este momento, podrás actualizarla posteriormente a tu registro desde tu portal de cliente, ya que no es obligatoria en esta etapa.
                            Este paso es indispensable para poder emitir tu factura correctamente.
                        </span>
                                <div class="col-md-12 mb-3 d-flex align-items-end">
                                    <div class="form-check">   
                                        <input class="form-check-input" type="checkbox" id="requiereFacturacion" name="requiereFacturacion">
                                        <label class="form-check-label" for="requiereFacturacion">
                                             Requiero facturación 
                                             <span style="font-size: 0.85em; color: #6c757d;">
                                                 (Este campo no es obligatorio para continuar con la actualización. Puede continuar sin marcarlo).
                                             </span>
                                         </label>

                                    </div>
                                </div>
                        <fieldset id="seccionEmpresarial" class="col-12 border p-3 mb-4 rounded" style="display: none;">
                            <legend class="float-none w-auto px-2 text-primary">Información Empresarial</legend>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="empresa" class="form-label">Empresa</label>
                                    <input type="text" class="form-control" id="empresa" name="empresa"
                                        value="<?php echo htmlspecialchars($cliente["empresa"] ?? ''); ?>"
                                        placeholder="Nombre de la empresa">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="rsocial" class="form-label">Razón Social</label>
                                    <input type="text" class="form-control" id="rsocial" name="rsocial"
                                        value="<?php echo htmlspecialchars($cliente["rsocial"] ?? ''); ?>"
                                        placeholder="Razón social completa">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="rfc" class="form-label">RFC</label>
                                    <input type="text" class="form-control" id="rfc" name="rfc"
                                        value="<?php echo htmlspecialchars($cliente["rfc"] ?? ''); ?>"
                                        placeholder="XAXX010101000">
                                </div>
                                
                                <div class="col-md-12 mb-3">
                                    <label for="constancia_fiscal" class="form-label">Constancia de Situación Fiscal</label>
                                    <input type="file" class="form-control" id="constancia_fiscal" name="constancia_fiscal" accept=".pdf,.jpg,.jpeg,.png">
                                    <?php if (!empty($cliente['constancia_situacion_fiscal'])): ?>
                                        <div class="form-text">
                                            Archivo actual: 
                                            <a href="constancias_fiscales/<?php echo htmlspecialchars($cliente['constancia_situacion_fiscal']); ?>" target="_blank">
                                                <?php echo htmlspecialchars($cliente['constancia_situacion_fiscal']); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>                        </fieldset>

                        <fieldset id="seccionDireccion" class="col-12 border p-3 mb-4 rounded" style="display: none;">
                            <legend class="float-none w-auto px-2 text-primary">Dirección</legend>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="calle" class="form-label">Calle</label>                                    <input type="text" class="form-control" id="calle" name="calle"
                                        value="<?php echo htmlspecialchars($cliente["calle"] ?? ''); ?>"
                                        placeholder="Av. Principal">
                                    <div class="invalid-feedback">Por favor ingresa la calle</div>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label for="next" class="form-label">N° Exterior</label>
                                    <input type="text" class="form-control" id="next" name="next"
                                        value="<?php echo htmlspecialchars($cliente["next"] ?? ''); ?>"
                                        placeholder="123">
                                    <div class="invalid-feedback">Por favor ingresa el número</div>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label for="nint" class="form-label">N° Interior</label>
                                    <input type="text" class="form-control" id="nint" name="nint"
                                        value="<?php echo htmlspecialchars($cliente["nint"] ?? ''); ?>" placeholder="A">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="colonia" class="form-label">Colonia</label>
                                    <input type="text" class="form-control" id="colonia" name="colonia"
                                        value="<?php echo htmlspecialchars($cliente["col"] ?? ''); ?>"
                                        placeholder="Centro">
                                    <div class="invalid-feedback">Por favor ingresa la colonia</div>
                                </div>
                                <div class="row align-items-end">
                                    <div class="col-md-2 mb-3">
                                        <label for="cp" class="form-label">Código Postal</label>
                                        <input type="text" class="form-control" id="cp" name="cp"
                                            value="<?php echo htmlspecialchars($cliente["cp"] ?? ''); ?>"
                                            placeholder="01000" pattern="[0-9]{5}">
                                        <div class="invalid-feedback">Código postal de 5 dígitos</div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="pais" class="form-label">País</label>
                                        <input type="text" class="form-control" name="pais" placeholder="País"
                                            value="<?php echo htmlspecialchars($cliente["pais"] ?? ''); ?>">
                                    </div>

                                    <div class="col-md-3 mb-3">
                                        <label for="estado" class="form-label">Estado</label>
                                        <select id="estado" name="estado" class="form-select">
                                            <option value="" selected disabled>Selecciona un estado</option>
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
                                        <div class="invalid-feedback">Selecciona un estado</div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="municipio" class="form-label">Ciudad</label>
                                        <select id="municipio" name="municipio" class="form-select" required>
                                            <option value="<?php echo htmlspecialchars($cliente['ciudad'] ?? ''); ?>"
                                                selected>
                                                <?php echo htmlspecialchars($cliente['ciudad'] ?? ''); ?>
                                            </option>
                                        </select>
                                        <div class="invalid-feedback">Selecciona una ciudad</div>
                                    </div>
                                </div>
                            </div>
                        </fieldset>
<div class="col-12 mb-3">
    <div class="form-check">
        <input class="form-check-input" type="checkbox" id="privacyPolicyCheck" name="privacyPolicyCheck" required>
        <label class="form-check-label" for="privacyPolicyCheck">
            He leído y acepto la <a href="https://conlineweb.com/politica-de-privacidad/" target="_blank" class="enlace_politica text-primary">Política de Privacidad</a>
        </label>
        <div class="invalid-feedback">
            Debes aceptar la política de privacidad para continuar
        </div>
    </div>
</div>
                        <div class="col-12 d-flex justify-content-between mt-4">
                            <button type="submit" class="btn btn-primary px-4" id="submitBtn">Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>    
<script>
        $(document).ready(function () {
            // Control de secciones de facturación
            function toggleSecciones() {
                const requiereFacturacion = $('#requiereFacturacion').prop('checked');
                $('#seccionEmpresarial, #seccionDireccion').toggle(requiereFacturacion);
                  
                // Obtener todos los campos de facturación
                const camposFacturacion = $('#seccionEmpresarial input, #seccionDireccion input, #seccionDireccion select');
                const camposBasicos = $('#nombre,#empresa, #email, #telefono');
                
                if (requiereFacturacion) {
                    // Si requiere facturación, todos los campos son obligatorios
                    camposFacturacion.prop('required', true);
                    camposBasicos.prop('required', true);
                    
                    // Reactivar la validación para los campos de facturación
                    camposFacturacion.each(function() {
                        $(this).addClass('form-control');
                        if ($(this).attr('id') !== 'nint' && $(this).attr('id') !== 'constancia_fiscal') { // Estos campos no son obligatorios
                            $(this).prop('required', true);
                        }
                    });
                } else {
                    // Si no requiere facturación, solo los campos básicos son obligatorios
                    camposFacturacion.prop('required', false);
                    camposBasicos.prop('required', true);
                    
                    // Limpiar los campos de facturación
                    camposFacturacion.val('');
                    camposFacturacion.removeClass('is-invalid');
                }
            }

            // Evento de cambio en el checkbox
            $('#requiereFacturacion').change(toggleSecciones);
            
            // Inicializar el estado al cargar la página
            $('#requiereFacturacion').prop('checked', false);
            $('#seccionEmpresarial, #seccionDireccion').hide();
            toggleSecciones();

            // Función para mostrar/ocultar contraseñas
            $(document).on('click', '.toggle-password', function () {
                const input = $(this).closest('.input-group').find('input');
                const icon = $(this).find('i');

                if (input.attr('type') === 'password') {
                    input.attr('type', 'text');
                    icon.removeClass('bi-eye').addClass('bi-eye-slash');
                } else {
                    input.attr('type', 'password');
                    icon.removeClass('bi-eye-slash').addClass('bi-eye');
                }
            });            // Confirmación para actualizar cliente
         $('form.needs-validation').on('submit', function (e) {
    e.preventDefault();

    // Si no requiere facturación, remover validación de campos empresariales
    const requiereFacturacion = $('#requiereFacturacion').prop('checked');
    if (!requiereFacturacion) {
        $('#seccionEmpresarial input, #seccionDireccion input, #seccionDireccion select').removeAttr('required');
    }

    // Concatenar código de país y teléfono SOLO para el envío, sin modificar el input
    const codigoPais = $('#country_code').val() || '';
    const telefono = $('#telefono').val() || '';
    let telefonoCompleto = telefono;
    if (codigoPais && telefono) {
        telefonoCompleto = codigoPais + telefono.replace(/^0+/, '');
    }

    // Validar formulario
    if (!this.checkValidity()) {
        e.stopPropagation();
        $(this).addClass('was-validated');
        return;
    }

    Swal.fire({
        title: '¿Confirmar actualización?',
        text: "Se generarán nuevas credenciales de acceso y se actualizarán los datos del cliente",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, actualizar',
        cancelButtonText: 'Cancelar'               
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Actualizando...',
                html: 'Por favor espere mientras se procesa la actualización',
                allowOutsideClick: false,
                allowEscapeKey: false,
                allowEnterKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                    const form = this;
                    const formData = new FormData(form);
                    
                    // Eliminar el campo de archivo si no se requiere facturación
                    if (!requiereFacturacion) {
                        formData.delete('constancia_fiscal');
                    }
                    
                    formData.append('facturacion', requiereFacturacion ? 1 : 0);
                    
                    // Aquí usamos telefonoCompleto en lugar de modificar el input
                    formData.set('telefono', telefonoCompleto); // Sobrescribimos el teléfono con el valor completo

                    for (const [key, value] of formData.entries()) {
                        console.log(`${key}: ${value}`);
                    }
                    
                    $.ajax({
                        url: 'actualizar_cliente.php',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                           
                            Swal.close();
                            if (response.success) {
                                if (response.mailSent) {
                                    // Éxito total
                                    Swal.fire({
                                        position: 'center',
                                        icon: 'success',
                                        title: '¡Actualización Exitosa!',
                                        html: `
                                            <div class='text-left'>
                                                <p>Tus datos se actualizaron correctamente.</p>
                                                <p>En breve recibirás un correo electrónico con tus nuevos datos de acceso. Ingresa a tu cuenta para continuar con el proceso.</p>
                                                <div class='mt-3'>
                                                    <strong>Nuevas credenciales:</strong><br>
                                                    Usuario: <strong>${response.usuario}</strong><br>
                                                    Contraseña: <strong>${response.contrasena}</strong>
                                                </div>
                                            </div>`,
                                        showConfirmButton: true,
                                        confirmButtonText: 'Aceptar',
                                        confirmButtonColor: '#28a745'
                                    }).then(() => {
                                           window.location.href = "cambiar_contrasena.php?id=<?php echo urlencode($id); ?>";
                                    });
                                } else {
                                    // Actualización exitosa pero fallo en el correo
                                    Swal.fire({
                                        position: 'center',
                                        icon: 'warning',
                                        title: 'Actualización Parcial',
                                        html: `
                                            <div class='text-left'>
                                                <p>✅ Cliente actualizado correctamente</p>
                                                <p>❌ Error al enviar el correo</p>
                                                <div class='mt-3'>
                                                    <strong>Nuevas credenciales:</strong><br>
                                                    Usuario: <strong>${response.usuario}</strong><br>
                                                    Contraseña: <strong>${response.contrasena}</strong>
                                                </div>
                                            </div>`,
                                        showConfirmButton: true,
                                        confirmButtonText: 'Aceptar',
                                        confirmButtonColor: '#ffc107'
                                    }).then(() => {
                                        window.location.href = "cambiar_contrasena.php?id=<?php echo urlencode($id); ?>";

                                    });
                                }
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
                            console.log(xhr)
                            console.log(status)
                            console.log(error)

                            Swal.close();
                            let errorMessage = 'Hubo un error al procesar la solicitudd.';
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
        }
    });
});

            // Manejar el checkbox de política de privacidad
            $('#privacyPolicy').change(function() {
                $('#submitBtn').prop('disabled', !this.checked);
            });

            // Selectores de estado/municipio
            $('#estado').change(function () {
                const estados_id_estado = $(this).val();
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

            // Inicializar municipio si hay estado seleccionado
            const estadoSeleccionado = $('#estado_fact').val();
if (estadoSeleccionado) {
    $.ajax({
        url: 'obtener_municipios.php',
        type: 'POST',
        data: { estados_id_estado: estadoSeleccionado },
        success: function(data) {
            const ciudadActual = '<?php echo addslashes($mostrar["ciudad"] ?? ""); ?>';
            $('#municipio').html(data);
            
            // Buscar la opción que coincida con el nombre de la ciudad
            if (ciudadActual) {
                let encontrado = false;
                $('#municipio option').each(function() {
                    if ($(this).text().trim() === ciudadActual.trim()) {
                        $(this).prop('selected', true);
                        encontrado = true;
                        return false; // Salir del bucle
                    }
                });
                
                // Si no se encontró la ciudad, agregarla como opción seleccionada
                if (!encontrado && ciudadActual.trim() !== '') {
                    $('#municipio').prepend(
                        $('<option>', {
                            value: ciudadActual,
                            text: ciudadActual,
                            selected: true
                        })
                    );
                }
            }
        }
    });
}

            // Cargar códigos de país en el select
            fetch('countries_codes.json?' + new Date().getTime(), {
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    },
    cache: 'no-store'
})
.then(response => {
    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }
    return response.json();
})
.then(data => {
    console.log('Countries data loaded:', data);
    const selectElement = document.getElementById('country_code');
    selectElement.innerHTML = '<option value="" disabled selected>Select your country code</option>';
    selectElement.style.webkitAppearance = 'none';
    selectElement.style.borderRadius = '0';
    if (data && data.countries) {
        data.countries.sort((a, b) => a.name.localeCompare(b.name))
                     .forEach(country => {
                         const option = new Option(`${country.code} (${country.name})`, country.code);
                         selectElement.add(option);
                     });
    } else {
        console.error('Invalid countries data structure');
        // loadFallbackCountries(); // Si tienes función de respaldo, descomenta esto
    }
})
.catch(error => {
    console.error('Error loading countries:', error);
    // loadFallbackCountries(); // Si tienes función de respaldo, descomenta esto
});

           
        });
    </script>
<script>
        // Mostrar el body una vez que todo esté cargado
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.display = 'block';
        });
    </script>
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_end_flush(); // Liberar el buffer de salida
}
?>
</body>
</html>