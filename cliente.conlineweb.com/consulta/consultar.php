<?php
require_once __DIR__ . '/../includes/cliente_session.php';
cliente_start_session();

if (!cliente_is_logged_in()) {
    header('Location: login.php');
    exit;
}

include 'configuracion.php';

function cq_post(string $key): string {
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function cq_escape(mysqli $conn, string $value): string {
    return mysqli_real_escape_string($conn, $value);
}

$nombrecliente = cq_post('nombrecliente');
$apellidocliente = cq_post('apellidocliente');
$calle = cq_post('calle');
$colonia = cq_post('colonia');
$delegacion = cq_post('delegacionmunicipio');
$telefono = cq_post('telefono');
$idpersona = cq_post('idpersona');

$query = '';
$resultados = [];
$mostrar_todos = isset($_GET['todos']) && (int) $_GET['todos'] === 1;

if ($mostrar_todos) {
    $query = 'SELECT * FROM registro ORDER BY id_registro DESC';
} else {
    $conditions = [];

    if ($idpersona !== '') {
        $conditions[] = "id_personal LIKE '%" . cq_escape($conn, $idpersona) . "%'";
    }
    if ($nombrecliente !== '') {
        $conditions[] = "nombre_cliente LIKE '%" . cq_escape($conn, $nombrecliente) . "%'";
    }
    if ($apellidocliente !== '') {
        $conditions[] = "apellido_cliente LIKE '%" . cq_escape($conn, $apellidocliente) . "%'";
    }
    if ($calle !== '') {
        $conditions[] = "calle LIKE '%" . cq_escape($conn, $calle) . "%'";
    }
    if ($colonia !== '') {
        $conditions[] = "colonia LIKE '%" . cq_escape($conn, $colonia) . "%'";
    }
    if ($delegacion !== '') {
        $conditions[] = "delegacion_municipio LIKE '%" . cq_escape($conn, $delegacion) . "%'";
    }
    if ($telefono !== '') {
        $conditions[] = "tel_contacto LIKE '%" . cq_escape($conn, $telefono) . "%'";
    }

    if (!empty($conditions)) {
        $query = 'SELECT * FROM registro WHERE ' . implode(' AND ', $conditions) . ' ORDER BY id_registro DESC';
    }
}

if ($query !== '') {
    $res = mysqli_query($conn, $query);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $resultados[] = $row;
        }
    }
}

$total_resultados = count($resultados);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require_once __DIR__ . '/../includes/cliente_head_meta.php'; ?>
    <title><?= htmlspecialchars(cliente_document_title('Consulta de registros'), ENT_QUOTES, 'UTF-8') ?></title>
    <?= cliente_favicon_markup() ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/consulta.css?v=20250714" rel="stylesheet">
</head>
<body class="cq-body">
<div class="cq-shell">
    <header class="cq-topbar">
        <div class="cq-topbar__brand">
            <i class="bi bi-search"></i>
            <div>
                <h1>Consulta de registros</h1>
                <p>Busca clientes por nombre, ubicación o teléfono</p>
            </div>
        </div>
        <nav class="cq-nav" aria-label="Navegación consulta">
            <a href="consultar.php" class="is-active">Consulta</a>
            <a href="index.php">Registro</a>
            <a href="carga.php">Carga</a>
            <a href="cerrarSesion.php">Cerrar sesión</a>
        </nav>
    </header>

    <section class="cq-card">
        <h2 class="cq-card__title">Filtros de búsqueda</h2>
        <p class="cq-card__subtitle">Completa uno o más campos para acotar los resultados.</p>

        <form name="consulta" id="consulta" method="post" action="consultar.php">
            <div class="cq-form-grid mb-3">
                <div class="cq-field">
                    <label for="idpersona">ID personal</label>
                    <select name="idpersona" id="idpersona" class="form-select">
                        <option value="" <?php echo $idpersona === '' ? 'selected' : ''; ?>>Cualquiera</option>
                        <?php for ($i = 1; $i <= 10; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo $idpersona === (string) $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="cq-field">
                    <label for="nombrecliente">Nombre del cliente</label>
                    <input type="text" class="form-control" id="nombrecliente" name="nombrecliente" value="<?php echo htmlspecialchars($nombrecliente, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ej. Juan">
                </div>
                <div class="cq-field">
                    <label for="apellidocliente">Apellido del cliente</label>
                    <input type="text" class="form-control" id="apellidocliente" name="apellidocliente" value="<?php echo htmlspecialchars($apellidocliente, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ej. Pérez">
                </div>
            </div>

            <div class="cq-form-grid cq-form-grid--2 mb-3">
                <div class="cq-field">
                    <label for="calle">Calle</label>
                    <input type="text" class="form-control" id="calle" name="calle" value="<?php echo htmlspecialchars($calle, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Buscar calle">
                </div>
                <div class="cq-field">
                    <label for="colonia">Colonia</label>
                    <input type="text" class="form-control" id="colonia" name="colonia" value="<?php echo htmlspecialchars($colonia, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Buscar colonia">
                </div>
            </div>

            <div class="cq-form-grid cq-form-grid--2">
                <div class="cq-field">
                    <label for="delegacionmunicipio">Delegación o municipio</label>
                    <input type="text" class="form-control" id="delegacionmunicipio" name="delegacionmunicipio" value="<?php echo htmlspecialchars($delegacion, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Buscar delegación o municipio">
                </div>
                <div class="cq-field">
                    <label for="telefono">Teléfono</label>
                    <input type="text" class="form-control" id="telefono" name="telefono" value="<?php echo htmlspecialchars($telefono, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Buscar teléfono">
                </div>
            </div>

            <div class="cq-actions">
                <button type="submit" class="cq-btn cq-btn--primary" id="btn_buscar">
                    <i class="bi bi-search"></i> Buscar
                </button>
                <a href="consultar.php?todos=1" class="cq-btn cq-btn--ghost">
                    <i class="bi bi-list-ul"></i> Ver todos
                </a>
            </div>
        </form>
    </section>

    <?php if ($query !== ''): ?>
    <section class="cq-card">
        <div class="cq-results-head">
            <h2>Resultados</h2>
            <span class="cq-badge-count"><?php echo $total_resultados; ?> registro<?php echo $total_resultados === 1 ? '' : 's'; ?></span>
        </div>

        <?php if ($total_resultados === 0): ?>
        <div class="cq-empty">
            <div><i class="bi bi-inbox"></i></div>
            <p class="mb-0">No se encontraron registros con los criterios indicados.</p>
        </div>
        <?php else: ?>
        <div class="cq-table-wrap">
            <table class="cq-table">
                <thead>
                    <tr>
                        <th>ID personal</th>
                        <th>Cliente</th>
                        <th>Ubicación</th>
                        <th>Teléfono</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($resultados as $nr):
                    $id_registro = (int) ($nr['id_registro'] ?? 0);
                    $nombre_completo = trim(($nr['nombre_cliente'] ?? '') . ' ' . ($nr['apellido_cliente'] ?? ''));
                    $ubicacion = trim(($nr['calle'] ?? '') . ', ' . ($nr['colonia'] ?? '') . ' — ' . ($nr['delegacion_municipio'] ?? ''));
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string) ($nr['id_personal'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><span class="cq-name"><?php echo htmlspecialchars($nombre_completo, ENT_QUOTES, 'UTF-8'); ?></span></td>
                        <td><span class="cq-location"><?php echo htmlspecialchars($ubicacion, ENT_QUOTES, 'UTF-8'); ?></span></td>
                        <td><?php echo htmlspecialchars((string) ($nr['tel_contacto'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <div class="cq-row-actions">
                                <button type="button" class="btn btn-outline-primary btn-sm btn_info" data-id1="<?php echo $id_registro; ?>" data-bs-toggle="modal" data-bs-target="#info_cliente">
                                    <i class="bi bi-eye"></i> Ver info
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm btn_edit" data-id1="<?php echo $id_registro; ?>" data-bs-toggle="modal" data-bs-target="#editar_cliente">
                                    <i class="bi bi-pencil"></i> Editar
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</div>

<div class="modal fade cq-modal" id="info_cliente" tabindex="-1" aria-labelledby="infoClienteLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="infoClienteLabel">Información de cliente</h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="modal_info"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade cq-modal" id="editar_cliente" tabindex="-1" aria-labelledby="editarClienteLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="editarClienteLabel">Editar cliente</h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="modal_edit"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).on('click', '.btn_info', function () {
    var id = $(this).data('id1');
    $.ajax({
        url: 'buscar.php',
        method: 'GET',
        data: { id: id },
        dataType: 'html',
        success: function (data) {
            $('#modal_info').html(data);
        }
    });
});

$(document).on('click', '.btn_edit', function () {
    var id = $(this).data('id1');
    $.ajax({
        url: 'modificar.php',
        method: 'GET',
        data: { id: id },
        dataType: 'html',
        success: function (data) {
            $('#modal_edit').html(data);
        }
    });
});
</script>
</body>
</html>
