<?php
session_start();
if (isset($_SESSION['id']) != null && isset($_SESSION['login']) == true) {
    include 'conn.php';
     include 'menu.php';
    $usrid = $_SESSION['uid'];
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Mis datos - &Aacute;rea cliente</title>
        <!-- jQuery -->
       
    </head>

    <body>

        <div id="layoutSidenav_content">
            <main>
                <div class="container-fluid">
                    <div style="width: 100%; background-color: #000147; margin: 0px; auto; padding: 10px; padding-top: 20px; border-radius: 0px">
                        <h3><span style="color: #ffffff;"><i class="bi bi-person-circle"></i> Mi cuenta</h3>
                    </div><br>

                    <div style="width: 100%; background-color: #ffffff; margin: 0px; auto; padding: 10px; padding-top: 10px; border-radius: 5px;">
                        <?php
                        $query = mysqli_query($conn, "SELECT * from clientes WHERE id=" . $usrid);
                        while ($mostrar = mysqli_fetch_array($query)) {
                            if (isset($mostrar['actualizar_correo']) && $mostrar['actualizar_correo'] == 1) {
                                echo '<div id="barra-verificar-correo" style="width:100%;background:#ffecb3;color:#333;padding:15px 20px;display:flex;align-items:center;justify-content:space-between;border-radius:6px 6px 0 0;margin-bottom:15px;box-shadow:0 2px 6px #0001;font-size:16px;">
                                <div><b>¡Tienes un correo pendiente de verificar!</b> <span style="color:#000147;font-weight:bold;">' . htmlspecialchars($mostrar['correo_pendiente_actualizar']) . '</span></div>
                                <button id="btn-verificar-correo" data-id="' . $mostrar['id'] . '" style="background:#000147;color:#fff;border:none;padding:8px 18px;border-radius:4px;font-weight:bold;cursor:pointer;">Reenviar  correo</button>
                            </div>';
                            }
                            ?>

                            <form id="datosForm" method="post" enctype="multipart/form-data">
                                <font size="5" style="font-family:calibri; font-weight: 900;"><span
                                        style="color: #000147;">Datos de facturaci&oacute;n</span></font>

                                <?php date_default_timezone_set('Mexico/General');
                                $hoy = date("Y-m-d");
                                $hora = date('H:i'); ?>
                                <br><br>
                                <font size="4" style="font-family:calibri; font-weight: 700;"><span style="color: #000147;">

                                        <div class="row">
                                            <div class="login">
                                                <label for="">id</label>
                                                <input type="text" id="id" name="id" required=""
                                                    value="<?php echo $mostrar['id']; ?>" autocomplete="nope" autofocus>
                                            </div>

                                            <div class="login">
                                                <label for="">id</label>
                                                <input type="text" id="display" name="display" required="" value="none"
                                                    autocomplete="nope" autofocus>
                                            </div>

                                            <div class="col-25">
                                                <label for="nombre">Nombre</label>
                                                <input type="text" id="nombre" name="nombre" required
                                                    value="<?php echo $mostrar['nombre_contacto']; ?>" autocomplete="nope"
                                                    autofocus>
                                            </div>

                                           

                                            <div class="col-25">
                                                <label for="correo">Correo </label>
                                                <input type="text" id="correo" name="correo" required
                                                    value="<?php echo $mostrar['correo']; ?>">
                                            </div>

                                            <div class="col-25">
                                                <label for="tel">Teléfono movil </label>
                                                <input type="text" id="tel" name="tel" required
                                                    value="<?php echo $mostrar['telefono']; ?>">
                                            </div>
                                        </div>

                                        <!-- Checkbox para facturación -->
                                        <div class="row">
                                            <div class="col-25">
                                                <div class="form-check">   
<input class="form-check-input" type="checkbox" id="requiereFacturacion" name="requiereFacturacion" <?php echo (!empty($mostrar['rfc']) ? 'checked' : ''); ?>>
                                                    <label class="form-check-label" for="requiereFacturacion">
                                                        Requiero facturación
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Sección de Información Empresarial -->
                                        <fieldset id="seccionEmpresarial" class="border p-3 mb-4 rounded" style="<?php echo (empty($mostrar['rfc']) ? 'display: none;' : ''); ?>">
                                            <legend class="float-none w-auto px-2 text-primary">Información Empresarial</legend>
                                            <div class="row">
                                                <div class="col-25">
                                                    <label for="empresa_fact">Empresa</label>
                                                    <input type="text" class="form-control" id="empresa_fact" name="empresa_fact" value="<?php echo $mostrar['empresa']; ?>">
                                                </div>
                                                <div class="col-25">
                                                    <label for="rsocial_fact">Razón Social</label>
                                                    <input type="text" class="form-control" id="rsocial_fact" name="rsocial_fact" value="<?php echo $mostrar['rsocial']; ?>">
                                                </div>
                                                <div class="col-25">
                                                    <label for="rfc_fact">RFC</label>
                                                    <input type="text" class="form-control" id="rfc_fact" name="rfc_fact" value="<?php echo $mostrar['rfc']; ?>">
                                                </div>
                                               <div class="col-75">
    <label for="constancia_fiscal">Constancia de Situación Fiscal</label>
    <input type="file" class="form-control" id="constancia_fiscal" name="constancia_fiscal" accept=".pdf,.jpg,.jpeg,.png">

    <?php
    $archivo = $mostrar['constancia_situacion_fiscal'] ?? null;

    if (!empty($archivo)) {
        $url_clientes = 'https://cliente.conlineweb.com/constancias_fiscales/' . urlencode($archivo);
        $url_adm = 'https://adm.conlineweb.com/constancias_fiscales/' . urlencode($archivo);

        // Intenta obtener los headers desde la URL de clientes
        $headers = @get_headers($url_clientes);
        if ($headers && strpos($headers[0], '200') !== false) {
            $url_final = $url_clientes;
        } else {
            // Si no existe en clientes, usar adm
            $url_final = $url_adm;
        }
    ?>
        <div class="form-text mt-2">
            Archivo actual:
            <a href="<?php echo htmlspecialchars($url_final); ?>" target="_blank">
                <?php echo htmlspecialchars($archivo); ?>
            </a>
        </div>
    <?php } ?>
</div>

                                            </div>
                                        </fieldset>
                                        
                                        <!-- Sección de Dirección -->
                                        <fieldset id="seccionDireccion" class="border p-3 mb-4 rounded" style="<?php echo (empty($mostrar['rfc']) ? 'display: none;' : ''); ?>">
                                            <legend class="float-none w-auto px-2 text-primary">Dirección</legend>
                                            <div class="row">
                                                <div class="col-25">
                                                    <label for="calle_fact">Calle</label>
                                                    <input type="text" class="form-control" id="calle_fact" name="calle_fact" value="<?php echo $mostrar['calle']; ?>">
                                                </div>
                                                <div class="col-25">
                                                    <label for="next_fact">N° Exterior</label>
                                                    <input type="text" class="form-control" id="next_fact" name="next_fact" value="<?php echo $mostrar['next']; ?>">
                                                </div>
                                                <div class="col-25">
                                                    <label for="nint_fact">N° Interior</label>
                                                    <input type="text" class="form-control" id="nint_fact" name="nint_fact" value="<?php echo $mostrar['nint']; ?>">
                                                </div>
                                                <div class="col-25">
                                                    <label for="colonia_fact">Colonia</label>
                                                    <input type="text" class="form-control" id="colonia_fact" name="colonia_fact" value="<?php echo $mostrar['col']; ?>">
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-25">
                                                    <label for="cp_fact">Código Postal</label>
                                                    <input type="text" class="form-control" id="cp_fact" name="cp_fact" value="<?php echo $mostrar['cp']; ?>">
                                                </div>
                                                <div class="col-25">
                                                    <label for="pais_fact">País</label>
                                                    <input type="text" class="form-control" id="pais_fact" name="pais_fact" value="<?php echo $mostrar['pais']; ?>">
                                                </div>
                                                <div class="col-25">
                                                    <!-- Mostrar estado actual -->
                                                    
                                                    <label for="estado_fact">Estado</label>
                                                    <select class="form-control" id="estado_fact" name="estado_fact">
                                                        <option value="">Selecciona un estado</option>
                                                        <?php
                                                        $estados = mysqli_query($conn, "SELECT id_estado, estado FROM estados ORDER BY estado");
                                                        while ($row = mysqli_fetch_assoc($estados)) {
                                                            $selected = ($mostrar['estado'] == $row['estado'] || $mostrar['estado'] == $row['id_estado']) ? 'selected' : '';
                                                            echo '<option value="' . htmlspecialchars($row['id_estado']) . '" ' . $selected . '>' . htmlspecialchars($row['estado']) . '</option>';
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                                <div class="col-25">
                                                    <!-- Mostrar ciudad actual -->
                                                    
                                                    <label for="ciudad_fact">Ciudad</label>
                                                    <select id="municipio" name="municipio" class="form-control" required>
                                                        <option value="" selected disabled>Selecciona una ciudad</option>
                                                    </select>
                                                    <div class="invalid-feedback">Selecciona una ciudad</div>
                                                    <input type="hidden" id="ciudad_actual_id" value="<?php echo htmlspecialchars($mostrar['ciudad_id'] ?? $mostrar['ciudad'] ?? ''); ?>">
                                                </div>
                                            </div>
                                        </fieldset>

                                        <div class="row">
                                            <div class="col-25">
                                                <button type="submit" class="button" id="reg_btn">Actualizar mis datos</button>
                                            </div>

                                            <div class="col-25">
                                                <a
                                                    href="javascript:window.open('https://c-onlineweb.com/politica-de-privacidad/','','width= 800,height=600');void(null)">
                                                    <font size="2" style="font-family:calibri; font-weight: 900;">
                                                        <p style="text-align:right;"><br><span style="color: #000147;">Política
                                                                de privacidad</small></p>
                                                    </font>
                                                </a>
                                            </div>
                                        </div>
                            </form>

                            <hr class="solid">
                        </div>
                    <?php } ?>

                    <script>
                        $(document).ready(function () {
                          function toggleSecciones() {
    const requiereFacturacion = $('#requiereFacturacion').prop('checked');
    $('#seccionEmpresarial, #seccionDireccion').toggle(requiereFacturacion);
    
    // Seleccionar todos los inputs excepto el file
    const camposFacturacion = $('#seccionEmpresarial input:not([type="file"]), #seccionDireccion input');
    
    if (requiereFacturacion) {
        camposFacturacion.prop('required', true);
    } else {
        camposFacturacion.prop('required', false);
        camposFacturacion.val('');
        camposFacturacion.removeClass('is-invalid');
    }
}

// Corregir el typo en el selector (facturacion vs facturación)
$('#requiereFacturacion').change(toggleSecciones);

// Inicializar el estado al cargar la página
toggleSecciones();
                            $('#datosForm').on('submit', function (e) {
                                e.preventDefault();
                                // Mostrar loading con SweetAlert
                                Swal.fire({
                                    title: 'Cargando...',
                                    allowOutsideClick: false,
                                    didOpen: () => {
                                        Swal.showLoading();
                                    }
                                });
                                
                                // Si no requiere facturación, remover validación de campos empresariales
                                const requiereFacturacion = $('#requiereFacturacion').prop('checked');
                                if (!requiereFacturacion) {
                                    $('#seccionEmpresarial input, #seccionDireccion input, #seccionDireccion select').removeAttr('required');
                                }
                                    $('#constancia_fiscal').removeAttr('required');

                                const formData = new FormData(this);
                                formData.append('facturacion', requiereFacturacion ? 1 : 0);
                                
                                 if (!requiereFacturacion) {
                                    formData.delete('constancia_fiscal');
                                }
                                
                                
                                 for (const [key, value] of formData.entries()) {
                        console.log(`${key}: ${value}`);
                    }
                                $.ajax({
                                    type: 'POST',
                                    url: 'actulizacion_clientes.php',
                                    data: formData,
                                    processData: false,
                                    contentType: false,
                                    dataType: 'json', // Esperamos una respuesta JSON
                                    success: function (response) {
                                        Swal.close(); // Quitar loading
                                        if (response.success) {
                                            Swal.fire({
                                                icon: 'success',
                                                title: '¡Éxito!',
                                                text: response.message || 'Datos actualizados correctamente',
                                                confirmButtonColor: '#000147'
                                            }).then(() => {
                                                // Recargar la página
                                                location.reload();
                                            });
                                        } else {
                                            Swal.fire({
                                                icon: 'error',
                                                title: 'Error',
                                                text: response.message || 'Ocurrió un error al actualizar los datos',
                                                confirmButtonColor: '#000147'
                                            });
                                        }
                                    },
                                    error: function (xhr, status, error) {
                                        Swal.close(); // Quitar loading
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'Error de conexión',
                                            text: 'No se pudo conectar con el servidor: ' + error,
                                            confirmButtonColor: '#000147'
                                        });
                                    }
                                });
                            });

                            $(document).on('click', '#btn-verificar-correo', function () {
                                var idCliente = $(this).data('id');
                                Swal.fire({
                                    title: 'Reenviando correo...',
                                    allowOutsideClick: false,
                                    didOpen: () => { Swal.showLoading(); }
                                });
                                console.log(idCliente)
                                $.ajax({
                                    url: 'reenviar_correo_verificar_correo.php',
                                    type: 'POST',
                                    data: { id: idCliente },
                                    dataType: 'json',
                                    success: function (response) {
                                        Swal.close();
                                        if (response.success) {
                                            Swal.fire({
                                                icon: 'success',
                                                title: '¡Correo reenviado!',
                                                text: response.message || 'El correo de verificación fue reenviado exitosamente.',
                                                confirmButtonColor: '#000147'
                                            });
                                        } else {
                                            Swal.fire({
                                                icon: 'error',
                                                title: 'Error',
                                                text: response.message || 'No se pudo reenviar el correo de verificación.',
                                                confirmButtonColor: '#000147'
                                            });
                                        }
                                    },
                                    error: function (xhr, status, error) {
                                        Swal.close();
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'Error de conexión',
                                            text: 'No se pudo conectar con el servidor: ' + error,
                                            confirmButtonColor: '#000147'
                                        });
                                    }
                                });
                            });


                            // Cargar municipios según estado seleccionado (como en guardar vacante prebusqueda)
$('#estado_fact').change(function () {
    const estadoId = $(this).val();
    if (estadoId) {
        $.ajax({
            url: 'obtener_municipios.php',
            type: 'POST',
            data: { estados_id_estado: estadoId },
            success: function (data) {
                $('#municipio').html(data);
            }
        });
    } else {
        $('#municipio').html('<option value="" selected disabled>Selecciona una ciudad</option>');
    }
});
// Inicializar municipio si ya hay estado seleccionado
const estadoSeleccionado = $('#estado_fact').val();
const ciudadActualId = $('#ciudad_actual_id').val();
if (estadoSeleccionado) {
    $.ajax({
        url: 'obtener_municipios.php',
        type: 'POST',
        data: { estados_id_estado: estadoSeleccionado },
        success: function (data) {
            $('#municipio').html(data);
            if (ciudadActualId) {
                $('#municipio').val(ciudadActualId);
            }
        }
    });
}
                        });
                    </script>
                </div>
            </main>
        </div>

        <script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
        <script src="assets/js/bootstrap.bundle.min.js"></script>
        <script src="assets/js/main.js"></script>

        <?php include('footer.php'); ?>
    </body>

    </html>
    <?php
} else {
    header("Location: ingreso.php");
}
?>