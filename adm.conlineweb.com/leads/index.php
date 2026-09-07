<?php
require_once dirname(__DIR__) . '/auth_middleware.php';
require_once dirname(__DIR__) . '/conn.php';
require_once dirname(__DIR__) . '/includes/cw_hub_permissions.php';
cw_hub_require('hub.crm.view');

$uid = $_SESSION['uid'] ?? null;
$tipo = intval($_SESSION['tipo'] ?? 0);
include dirname(__DIR__) . '/menu.php';

$admPageTitle = 'Gestión de Leads';
$admPageSubtitle = 'Lista, seguimiento y acciones rápidas del CRM';
$admPageIcon = 'bi bi-person-lines-fill';
$admPageActions = '<a href="inbox.php" class="btn btn-primary mr-2"><i class="bi bi-inbox-fill"></i> Bandeja CRM</a>'
    . '<a href="kanban.php" class="btn btn-outline-primary mr-2"><i class="fas fa-columns"></i> Kanban</a>'
    . '<button class="btn btn-primary" id="btnNuevoLead"><i class="fas fa-plus"></i> Nuevo Lead</button>';
?>
<link href="/vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">

<div class="adm-page-shell">
<div class="container-fluid px-0">

                <?php include dirname(__DIR__) . '/includes/adm_page_header.php'; ?>
                <div class="card shadow mb-4 adm-content-card">
                    <div class="card-header py-3">
                        <h6 class="adm-section-title">Lista de Leads</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Origen</th>
                                        <th>Nombre</th>
                                        <th>Correo</th>
                                        <th>Teléfono</th>
                                        <th>Empresa</th>
                                        <th>País</th>
                                        <th>Requerimiento</th>
                                        <th>Estatus</th>
                                        <th>WA</th>
                                        <th>Última Nota</th>
                                        <th>Fecha Registro</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

    <!-- Modales -->
    <div class="modal fade" id="modalLead" tabindex="-1" aria-labelledby="modalLeadLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalLeadLabel">Nuevo Lead</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="formLead">
                    <div class="modal-body">
                        <input type="hidden" id="leadId" name="id">
                        <input type="hidden" id="accion" name="accion" value="agregar">

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="nombre">Nombre <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nombre" name="nombre" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="correo">Correo Electrónico <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="correo" name="correo" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="telefono">Teléfono</label>
                                <input type="text" class="form-control" id="telefono" name="telefono">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="empresa">Empresa</label>
                                <input type="text" class="form-control" id="empresa" name="empresa" placeholder="Opcional">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="pais">País</label>
                                <select class="form-control" id="pais" name="pais">
                                    <option value="México">México</option>
                                    <option value="Estados Unidos">Estados Unidos</option>
                                    <option value="Canadá">Canadá</option>
                                    <option value="España">España</option>
                                    <option value="Brasil">Brasil</option>
                                    <option value="Argentina">Argentina</option>
                                    <option value="Chile">Chile</option>
                                    <option value="Colombia">Colombia</option>
                                    <option value="Perú">Perú</option>
                                    <option value="Panamá">Panamá</option>
                                    <option value="Costa Rica">Costa Rica</option>
                                    <option value="Uruguay">Uruguay</option>
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="estatus">Estatus</label>
                                <select class="form-control" id="estatus" name="estatus">
                                    <option value="Activo">Activo</option>
                                    <option value="Atendido">Atendido</option>
                                    <option value="Con cotización">Con cotización</option>
                                    <option value="Sin cotización">Sin cotización</option>
                                    <option value="No responde">No responde</option>
                                    <option value="Sesión de llamada">Sesión de llamada</option>
                                    <option value="Cliente">Cliente</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="requerimiento">Requerimiento</label>
                            <textarea class="form-control" id="requerimiento" name="requerimiento" rows="3"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="nota">Nota</label>
                            <textarea class="form-control" id="nota" name="nota" rows="2"
                                placeholder="Agregar una nota (opcional)"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="btnGuardarLead">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Ver Notas -->
    <div class="modal fade" id="modalNotas" tabindex="-1" aria-labelledby="modalNotasLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalNotasLabel">Historial de Notas</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="contenidoNotas">
                        <!-- Las notas se cargarán aquí dinámicamente -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Agregar Nueva Nota -->
    <div class="modal fade" id="modalAgregarNota" tabindex="-1" aria-labelledby="modalAgregarNotaLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAgregarNotaLabel">Agregar Nueva Nota</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="formAgregarNota">
                    <div class="modal-body">
                        <input type="hidden" id="notaLeadId" name="id">
                        
                        <div class="form-group">
                            <label>Lead:</label>
                            <p class="form-control-plaintext font-weight-bold" id="notaLeadNombre"></p>
                        </div>

                        <div class="form-group">
                            <label for="nuevaNota">Nueva Nota <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="nuevaNota" name="nota" rows="4" required 
                                placeholder="Escribe aquí la nueva nota..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Nota</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div><!-- .container-fluid px-0 -->
</div><!-- .adm-page-shell -->
</div><!-- menu container-fluid -->
</div><!-- menu content-wrapper -->

    <script src="/vendor/jquery/jquery.min.js"></script>
    <script src="/vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="/vendor/datatables/jquery.dataTables.min.js"></script>
    <script src="/vendor/datatables/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function () {
            // Inicializar DataTable
            var table = $('#dataTable').DataTable({
                scrollX: true,
                "ajax": {
                    "url": "obtener_leads.php",
                    "dataSrc": "data"
                },
                "columns": [
                    { "data": "id" },
                    {
                        "data": "origen",
                        "render": function (data, type, row) {
                            if (data === 'Manual') {
                                return '<span class="badge badge-info"><i class="fas fa-user-edit"></i> Manual</span>';
                            } else {
                                return '<span class="badge badge-success"><i class="fas fa-file-excel"></i> Excel</span>';
                            }
                        }
                    },
                    { "data": "nombre" },
                    { "data": "correo" },
                    { "data": "telefono" },
                    { 
                        "data": "empresa",
                        "render": function (data, type, row) {
                            return data || '<em class="text-muted">No especificado</em>';
                        }
                    },
                    { 
                        "data": "pais",
                        "render": function (data, type, row) {
                            return data || 'México';
                        }
                    },
                    {
                        "data": "requerimiento",
                        "render": function (data, type, row) {
                            if (data && data.length > 50) {
                                return data.substring(0, 50) + '...';
                            }
                            return data || '';
                        }
                    },
                    {
                        "data": "estatus",
                        "render": function (data, type, row) {
                            var badgeClass = '';
                            switch (data) {
                                case 'Activo':
                                    badgeClass = 'badge-primary';
                                    break;
                                case 'Atendido':
                                    badgeClass = 'badge-info';
                                    break;
                                case 'Con cotización':
                                    badgeClass = 'badge-warning';
                                    break;
                                case 'Sin cotización':
                                    badgeClass = 'badge-secondary';
                                    break;
                                case 'No responde':
                                    badgeClass = 'badge-danger';
                                    break;
                                case 'Sesión de llamada':
                                    badgeClass = 'badge-success';
                                    break;
                                case 'Cliente':
                                    badgeClass = 'badge-dark';
                                    break;
                                default:
                                    badgeClass = 'badge-light';
                            }
                            return '<span class="badge ' + badgeClass + ' badge-estatus">' + data + '</span>';
                        }
                    },
                    {
                        "data": "whatsapp_enviado",
                        "render": function (data, type, row) {
                            if (data == 1) {
                                var fecha = row.whatsapp_enviado_fecha
                                    ? new Date(row.whatsapp_enviado_fecha).toLocaleDateString('es-MX')
                                    : '';
                                return '<span class="badge badge-success badge-estatus" title="Enviado el ' + fecha + '">' +
                                    '<i class="fab fa-whatsapp"></i> Enviado</span>';
                            }
                            return '<span class="badge badge-light badge-estatus" title="Aún no enviado">' +
                                '<i class="fab fa-whatsapp"></i> Pendiente</span>';
                        }
                    },
                    {
                        "data": null,
                        "render": function (data, type, row) {
                            var notaHtml = '<div class="nota-preview">';
                            if (row.ultima_nota) {
                                notaHtml += row.ultima_nota;
                            } else {
                                notaHtml += '<em class="text-muted">Sin notas</em>';
                            }
                            notaHtml += '</div>';

                            if (row.total_notas > 0) {
                                notaHtml += '<button class="btn btn-sm btn-link p-0 mt-1 btnVerNotas" data-id="' + row.id + '" data-notas=\'' + JSON.stringify(row.notas_json) + '\'>';
                                notaHtml += '<i class="fas fa-eye"></i> Ver todas (' + row.total_notas + ')';
                                notaHtml += '</button>';
                            }

                            return notaHtml;
                        }
                    },
                    {
                        "data": "fecha_registro",
                        "render": function (data, type, row) {
                            var fecha = new Date(data);
                            return fecha.toLocaleDateString('es-MX') + ' ' + fecha.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
                        }
                    },
                    {
                        "data": null,
                        "render": function (data, type, row) {
                            var whatsappBtn = '';
                            var mensajeInicialBtn = '';
                            if (row.telefono) {
                                // Limpiar el número de teléfono (remover espacios, guiones, paréntesis)
                                var cleanPhone = row.telefono.replace(/[^0-9+]/g, '');
                                whatsappBtn = '<a href="https://wa.me/' + cleanPhone + '" target="_blank" class="btn btn-sm btn-success" title="Abrir WhatsApp">' +
                                    '<i class="fab fa-whatsapp"></i></a> ';
                                mensajeInicialBtn = '<button class="btn btn-sm btn-outline-success btnMensajeInicial" ' +
                                    'data-telefono="' + row.telefono + '" data-nombre="' + row.nombre + '" ' +
                                    'title="Enviar mensaje inicial de WhatsApp">' +
                                    '<i class="fab fa-whatsapp"></i> Saludo</button> ';
                            }
                            
                            var actionButtons = '';
                            
                            // Solo permitir agregar nota, editar y eliminar en leads manuales
                            if (row.origen === 'Manual') {
                                actionButtons = '<button class="btn btn-sm btn-primary btnAgregarNota" data-id="' + row.id_real + '" data-nombre="' + row.nombre + '" title="Agregar Nota">' +
                                    '<i class="fas fa-sticky-note"></i></button> ' +
                                    '<button class="btn btn-sm btn-info btnEditarLead" data-id="' + row.id_real + '" title="Editar">' +
                                    '<i class="fas fa-edit"></i></button> ' +
                                    '<button class="btn btn-sm btn-danger btnEliminarLead" data-id="' + row.id_real + '" title="Eliminar">' +
                                    '<i class="fas fa-trash"></i></button>';
                            } else {
                                // Para leads de Excel, solo mostrar un indicador
                                actionButtons = '<span class="badge badge-secondary" title="Lead de Excel - Solo lectura">' +
                                    '<i class="fas fa-lock"></i> Solo lectura</span>';
                            }
                            
                            return '<a href="inbox.php?id=' + row.id_real + '" class="btn btn-sm btn-outline-primary" title="Abrir bandeja CRM">' +
                                '<i class="fas fa-id-card"></i></a> ' +
                                mensajeInicialBtn + whatsappBtn + actionButtons;
                        }
                    }
                ],
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json"
                },
                "order": [[0, "desc"]]
            });

            $(window).on('resize.admLeadsDt', function () {
                if (table) table.columns.adjust();
            });

            // Abrir modal para nuevo lead
            $('#btnNuevoLead').click(function () {
                $('#formLead')[0].reset();
                $('#leadId').val('');
                $('#accion').val('agregar');
                $('#modalLeadLabel').text('Nuevo Lead');
                $('#estatus').val('Activo');
                $('#estatus').prop('disabled', true); // Deshabilitar el selector de estatus para nuevos leads
                $('#modalLead').modal('show');
            });

            // Editar lead
            $(document).on('click', '.btnEditarLead', function () {
                var id = $(this).data('id');

                $.ajax({
                    url: 'obtener_lead.php',
                    type: 'GET',
                    data: { id: id },
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            $('#leadId').val(response.data.id);
                            $('#accion').val('editar');
                            $('#nombre').val(response.data.nombre);
                            $('#correo').val(response.data.correo);
                            $('#telefono').val(response.data.telefono);
                            $('#empresa').val(response.data.empresa);
                            $('#pais').val(response.data.pais || 'México');
                            $('#requerimiento').val(response.data.requerimiento);
                            $('#estatus').val(response.data.estatus);
                            $('#estatus').prop('disabled', false); // Habilitar el selector de estatus para edición
                            $('#nota').val('');
                            $('#modalLeadLabel').text('Editar Lead');
                            $('#modalLead').modal('show');
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },
                    error: function () {
                        Swal.fire('Error', 'Error al cargar los datos del lead', 'error');
                    }
                });
            });

            // Guardar lead (agregar o editar)
            $('#formLead').submit(function (e) {
                e.preventDefault();

                // Habilitar temporalmente el campo de estatus para que se envíe en el formulario
                $('#estatus').prop('disabled', false);
                var formData = $(this).serialize();

                $.ajax({
                    url: 'agregar_lead.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            Swal.fire('Éxito', response.message, 'success');
                            $('#modalLead').modal('hide');

                            // Si es un nuevo lead, agregarlo a la tabla sin recargar
                            if ($('#accion').val() === 'agregar' && response.data) {
                                var lead = response.data;

                                // Agregar la nueva fila al DataTable usando el mismo formato de objeto que usa ajax
                                var rowNode = table.row.add(lead).draw(false).node();

                                // Resaltar la nueva fila brevemente
                                $(rowNode).css('background-color', '#d4edda');
                                setTimeout(function () {
                                    $(rowNode).css('background-color', '');
                                }, 2000);

                            } else {
                                // Si es edición, recargar la tabla
                                table.ajax.reload();
                            }
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },
                    error: function () {
                        Swal.fire('Error', 'Error al guardar el lead', 'error');
                    }
                });
            });

            // Eliminar lead
            $(document).on('click', '.btnEliminarLead', function () {
                var id = $(this).data('id');

                Swal.fire({
                    title: '¿Estás seguro?',
                    text: "Esta acción no se puede deshacer",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'eliminar_lead.php',
                            type: 'POST',
                            data: { id: id },
                            dataType: 'json',
                            success: function (response) {
                                if (response.success) {
                                    Swal.fire('Eliminado', response.message, 'success');
                                    table.ajax.reload();
                                } else {
                                    Swal.fire('Error', response.message, 'error');
                                }
                            },
                            error: function () {
                                Swal.fire('Error', 'Error al eliminar el lead', 'error');
                            }
                        });
                    }
                });
            });

            // Ver todas las notas
            $(document).on('click', '.btnVerNotas', function () {
                var notas = JSON.parse($(this).data('notas'));
                var notasArray = JSON.parse(notas);

                var html = '';
                if (notasArray && notasArray.length > 0) {
                    notasArray.forEach(function (nota, index) {
                        var fecha = new Date(nota.fecha);
                        html += '<div class="nota-item">';
                        html += '<div class="nota-fecha"><strong>Nota #' + (index + 1) + '</strong> - ' +
                            fecha.toLocaleDateString('es-MX') + ' ' +
                            fecha.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' }) + '</div>';
                        html += '<div class="mt-2">' + nota.nota + '</div>';
                        html += '</div>';
                    });
                } else {
                    html = '<p class="text-muted">No hay notas registradas.</p>';
                }

                $('#contenidoNotas').html(html);
                $('#modalNotas').modal('show');
            });

            // Abrir modal para agregar nueva nota
            $(document).on('click', '.btnAgregarNota', function () {
                var id = $(this).data('id');
                var nombre = $(this).data('nombre');
                
                $('#notaLeadId').val(id);
                $('#notaLeadNombre').text(nombre);
                $('#nuevaNota').val('');
                $('#modalAgregarNota').modal('show');
            });

            // Enviar mensaje inicial (plantilla WhatsApp)
            $(document).on('click', '.btnMensajeInicial', function () {
                var telefono = $(this).data('telefono');
                var nombre   = $(this).data('nombre');
                var $btn     = $(this);

                Swal.fire({
                    title: '¿Enviar mensaje inicial?',
                    html: 'Se enviará la plantilla de saludo de WhatsApp a <strong>' + nombre + '</strong> (' + telefono + ').',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#25d366',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '<i class="fab fa-whatsapp"></i> Enviar',
                    cancelButtonText: 'Cancelar'
                }).then(function (result) {
                    if (result.isConfirmed) {
                        var originalHtml = $btn.html();
                        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

                        $.ajax({
                            url: '../ajax/enviar_mensaje_plantilla.php',
                            type: 'POST',
                            data: { telefono: telefono, nombre_cliente: nombre },
                            dataType: 'json',
                            success: function (response) {
                                if (response.success) {
                                    Swal.fire('¡Enviado!', 'Mensaje inicial enviado correctamente a ' + nombre + '.', 'success');
                                } else {
                                    Swal.fire('Error', response.error || 'No se pudo enviar el mensaje.', 'error');
                                }
                            },
                            error: function (xhr) {
                                var msg = 'Error de conexión con el servidor';
                                try {
                                    var r = JSON.parse(xhr.responseText);
                                    if (r.error) msg = r.error;
                                } catch(e) {}
                                Swal.fire('Error', msg, 'error');
                            },
                            complete: function () {
                                $btn.prop('disabled', false).html(originalHtml);
                            }
                        });
                    }
                });
            });

            // Guardar nueva nota
            $('#formAgregarNota').submit(function (e) {
                e.preventDefault();
                var formData = $(this).serialize();

                $.ajax({
                    url: 'agregar_nota.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            Swal.fire('Éxito', 'Nota agregada correctamente', 'success');
                            $('#modalAgregarNota').modal('hide');
                            table.ajax.reload();
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },
                    error: function () {
                        Swal.fire('Error', 'Error al agregar la nota', 'error');
                    }
                });
            });
        });
    </script>

</body>

</html>