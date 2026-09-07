<?php
require_once __DIR__ . '/includes/cliente_session.php';
cliente_start_session();
if (cliente_is_logged_in()) {
    include 'conn.php';
    $usrid = (int) $_SESSION['uid'];

    $stats_query = mysqli_query($conn, "
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'Pendiente' THEN 1 ELSE 0 END) as pendientes,
            SUM(CASE WHEN estado = 'En Proceso' THEN 1 ELSE 0 END) as en_proceso,
            SUM(CASE WHEN estado = 'Finalizado' THEN 1 ELSE 0 END) as finalizados
        FROM solicitudes
        WHERE id_cliente = $usrid
    ");
    $stats = mysqli_fetch_assoc($stats_query) ?: [
        'total' => 0,
        'pendientes' => 0,
        'en_proceso' => 0,
        'finalizados' => 0,
    ];

    $tickets_activos = [];
    $tickets_terminados = [];
    $tickets_query = mysqli_query($conn, "
        SELECT s.*, p.nombre_proyecto
        FROM solicitudes s
        LEFT JOIN proyectos p ON s.id_proyecto = p.id_proyecto
        WHERE s.id_cliente = $usrid
        ORDER BY s.fecha_solicitud DESC
    ");
    if ($tickets_query) {
        while ($row = mysqli_fetch_assoc($tickets_query)) {
            if (($row['estado'] ?? '') === 'Finalizado') {
                $tickets_terminados[] = $row;
            } else {
                $tickets_activos[] = $row;
            }
        }
    }

    $proyectos = [];
    $proyectos_query = mysqli_query($conn, "
        SELECT id_proyecto, nombre_proyecto
        FROM proyectos
        WHERE id_cliente = $usrid
        ORDER BY nombre_proyecto
    ");
    if ($proyectos_query) {
        while ($p = mysqli_fetch_assoc($proyectos_query)) {
            $proyectos[] = $p;
        }
    }

    $tk_estado_class = static function (string $estado): string {
        switch ($estado) {
            case 'Pendiente':
                return 'status-badge status-pending';
            case 'En Proceso':
                return 'status-badge status-process';
            case 'Finalizado':
                return 'status-badge status-completed';
            default:
                return 'status-badge';
        }
    };

    $tk_prioridad_class = static function (string $prioridad): string {
        switch ($prioridad) {
            case 'Alta':
                return 'priority-badge priority-high';
            case 'Media':
                return 'priority-badge priority-medium';
            case 'Baja':
                return 'priority-badge priority-low';
            default:
                return 'priority-badge';
        }
    };

    $tk_render_desktop_rows = static function (array $tickets) use ($tk_estado_class, $tk_prioridad_class): void {
        foreach ($tickets as $ticket) {
            $fecha_creacion = date('d/m/Y H:i', strtotime($ticket['fecha_solicitud']));
            $fecha_limite = ($ticket['fecha_lim'] && $ticket['fecha_lim'] != '0000-00-00 00:00:00')
                ? date('d/m/Y H:i', strtotime($ticket['fecha_lim']))
                : 'Sin fecha';
            $estado_class = $tk_estado_class($ticket['estado'] ?? '');
            $prioridad_class = $tk_prioridad_class($ticket['prioridad'] ?? '');
            ?>
            <tr data-tk-estado="<?= htmlspecialchars($ticket['estado'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <td>
                    <span class="tk-ref">#<?= (int) $ticket['id'] ?></span>
                </td>
                <td>
                    <div class="tk-title-cell">
                        <span class="tk-title-cell__text"><?= htmlspecialchars($ticket['titulo']) ?></span>
                    </div>
                </td>
                <td>
                    <span class="tk-project-cell<?= empty($ticket['nombre_proyecto']) ? ' is-empty' : '' ?>">
                        <i class="bi bi-folder2"></i>
                        <?= !empty($ticket['nombre_proyecto']) ? htmlspecialchars($ticket['nombre_proyecto']) : 'Sin proyecto' ?>
                    </span>
                </td>
                <td><span class="<?= $estado_class ?>"><?= htmlspecialchars($ticket['estado']) ?></span></td>
                <td><span class="<?= $prioridad_class ?>"><?= htmlspecialchars($ticket['prioridad']) ?></span></td>
                <td>
                    <span class="tk-date-cell" data-order="<?= htmlspecialchars($ticket['fecha_solicitud']) ?>">
                        <?= $fecha_creacion ?>
                    </span>
                </td>
                <td>
                    <span class="tk-date-cell<?= $fecha_limite === 'Sin fecha' ? ' is-muted' : '' ?>">
                        <?= $fecha_limite ?>
                    </span>
                </td>
                <td>
                    <button type="button" class="tk-btn tk-btn--ghost tk-btn--sm"
                        onclick="verDetalleTicket(<?= (int) $ticket['id'] ?>)" title="Ver detalle">
                        <i class="bi bi-eye"></i> Ver detalle
                    </button>
                </td>
            </tr>
            <?php
        }
    };

    $tk_render_mobile_cards = static function (array $tickets) use ($tk_estado_class, $tk_prioridad_class): void {
        foreach ($tickets as $ticket) {
            $fecha_creacion = date('d/m/Y H:i', strtotime($ticket['fecha_solicitud']));
            $estado_class = $tk_estado_class($ticket['estado'] ?? '');
            $prioridad_class = $tk_prioridad_class($ticket['prioridad'] ?? '');
            $id = (int) $ticket['id'];
            ?>
            <div class="ticket-item-mobile" data-tk-estado="<?= htmlspecialchars($ticket['estado'] ?? '', ENT_QUOTES, 'UTF-8') ?>" onclick="verDetalleTicket(<?= $id ?>)">
                <div class="ticket-header-mobile">
                    <div class="ticket-id"><span class="tk-ref">#<?= $id ?></span></div>
                    <div class="ticket-date"><?= $fecha_creacion ?></div>
                </div>
                <div class="ticket-title-mobile"><?= htmlspecialchars($ticket['titulo']) ?></div>
                <div class="ticket-info-mobile">
                    <div class="ticket-project">
                        <i class="bi bi-folder me-1"></i>
                        <?= !empty($ticket['nombre_proyecto']) ? htmlspecialchars($ticket['nombre_proyecto']) : 'Sin proyecto' ?>
                    </div>
                    <div class="ticket-meta">
                        <span class="<?= $estado_class ?>"><?= htmlspecialchars($ticket['estado']) ?></span>
                        <span class="<?= $prioridad_class ?>"><?= htmlspecialchars($ticket['prioridad']) ?></span>
                    </div>
                </div>
                <div class="ticket-actions-mobile">
                    <button type="button" class="btn-action-view"
                        onclick="event.stopPropagation(); verDetalleTicket(<?= $id ?>)">
                        <i class="bi bi-eye"></i> Ver detalle
                    </button>
                </div>
            </div>
            <?php
        }
    };
    ?>

    <!DOCTYPE html>
    <html lang="es">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#000147">
        <?php require_once __DIR__ . '/includes/cliente_head_meta.php'; $clientePageTitle = 'Mis tickets'; ?>
        <title><?= htmlspecialchars(cliente_document_title('Mis tickets'), ENT_QUOTES, 'UTF-8') ?></title>
        <?= cliente_favicon_markup() ?>

        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="assets/css/bootstrap.css">
        <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
        <link rel="stylesheet" href="assets/css/app.css">
        <link rel="stylesheet" href="assets/css/tickets.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/tickets.css') ?>">

        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.0/dist/sweetalert2.min.css">
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.0/dist/sweetalert2.all.min.js"></script>
    </head>

    <body>
        <div id="app">
            <?php include 'menu.php'; ?>

            <div class="main-content tickets-page">
            <div class="header-section">
                <div class="header-content">
                    <div class="header-icon-container">
                        <i class="bi bi-inbox-fill header-icon"></i>
                    </div>
                    <div class="header-text">
                        <h1 class="header-title">Mis tickets</h1>
                        <p class="header-subtitle">Soporte: crea, sigue y comenta tus solicitudes · <?= (int) $stats['total'] ?> ticket<?= (int) $stats['total'] === 1 ? '' : 's' ?></p>
                    </div>
                </div>
            </div>

                <div class="tk-diff">
                    <div class="tk-diff__item">
                        <strong>Esta página</strong>
                        <span>Pedir ayuda al equipo y ver el avance de cada caso</span>
                    </div>
                    <div class="tk-diff__item">
                        <strong>Estados</strong>
                        <span><em>Pendiente</em> → <em>En proceso</em> → <em>Finalizado</em></span>
                    </div>
                    <div class="tk-diff__item">
                        <strong>Urgente</strong>
                        <span>Crea el ticket y avísanos por WhatsApp al 477 118 1285</span>
                    </div>
                </div>

                <div class="tk-list-toolbar">
                    <div class="tk-list-toolbar__text">
                        <p class="tk-list-toolbar__eyebrow">¿Necesitas algo?</p>
                        <h5>Abre un ticket</h5>
                        <small>Describe el problema, adjunta capturas si hace falta y te respondemos aquí</small>
                    </div>
                    <button type="button" class="tk-btn tk-btn--primary tk-btn--lg" onclick="abrirModalNuevoTicket()">
                        <i class="bi bi-plus-circle"></i> Crear ticket
                    </button>
                </div>

                <?php
                $abiertos = (int) $stats['pendientes'] + (int) $stats['en_proceso'];
                ?>
                <?php if ($abiertos > 0): ?>
                <div class="tk-pay-strip">
                    <i class="bi bi-info-circle-fill"></i>
                    <span>Tienes <strong><?= $abiertos ?></strong> ticket<?= $abiertos === 1 ? '' : 's' ?> abierto<?= $abiertos === 1 ? '' : 's' ?>
                        (<?= (int) $stats['pendientes'] ?> pendiente<?= (int) $stats['pendientes'] === 1 ? '' : 's' ?> · <?= (int) $stats['en_proceso'] ?> en proceso).
                        Pulsa <strong>Ver detalle</strong> para leer respuestas o agregar un comentario.</span>
                </div>
                <?php endif; ?>

                <div class="tk-summary">
                    <button type="button" class="tk-summary__card" data-tk-goto="activos" title="Ver tickets en curso">
                        <span class="tk-summary__label">Total</span>
                        <strong class="tk-summary__num"><?= (int) $stats['total'] ?></strong>
                        <span class="tk-summary__extra">Todos tus tickets</span>
                    </button>
                    <button type="button" class="tk-summary__card tk-summary__card--warn" data-tk-goto="activos" data-tk-filter="Pendiente">
                        <span class="tk-summary__label">Pendientes</span>
                        <strong class="tk-summary__num"><?= (int) $stats['pendientes'] ?></strong>
                        <span class="tk-summary__extra">Aún sin atender</span>
                    </button>
                    <button type="button" class="tk-summary__card tk-summary__card--info" data-tk-goto="activos" data-tk-filter="En Proceso">
                        <span class="tk-summary__label">En proceso</span>
                        <strong class="tk-summary__num"><?= (int) $stats['en_proceso'] ?></strong>
                        <span class="tk-summary__extra">El equipo ya trabaja</span>
                    </button>
                    <button type="button" class="tk-summary__card tk-summary__card--ok" data-tk-goto="terminados">
                        <span class="tk-summary__label">Finalizados</span>
                        <strong class="tk-summary__num"><?= (int) $stats['finalizados'] ?></strong>
                        <span class="tk-summary__extra">Casos cerrados</span>
                    </button>
                </div>

                <?php if ((int) $stats['total'] === 0): ?>
                    <div class="row">
                        <div class="col-12">
                            <div class="tk-card">
                                <div class="tk-empty">
                                    <div class="tk-empty__icon"><i class="bi bi-inbox"></i></div>
                                    <h5>Aún no tienes tickets</h5>
                                    <p>Si algo falla en tu sitio, hosting o dominio, crea un ticket y te ayudamos.</p>
                                    <button type="button" class="tk-btn tk-btn--primary mt-2" onclick="abrirModalNuevoTicket()">
                                        <i class="bi bi-plus-circle"></i> Crear mi primer ticket
                                    </button>
                                    <div class="tk-empty-actions">
                                        <a href="https://wa.me/524771181285" target="_blank" rel="noopener" class="tk-empty-link"><i class="bi bi-whatsapp"></i> WhatsApp</a>
                                        <a href="pagos.php" class="tk-empty-link">Ir a pagos</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php
                    $count_activos = count($tickets_activos);
                    $count_terminados = count($tickets_terminados);
                    $tab_default = $count_activos > 0 ? 'activos' : 'terminados';
                    ?>
                    <div class="tk-tabs" data-default-tab="<?= htmlspecialchars($tab_default) ?>">
                        <div class="tk-tabs__toolbar">
                            <ul class="nav nav-pills tk-tabs__nav" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button type="button"
                                        class="nav-link tk-tabs__btn<?= $tab_default === 'activos' ? ' active' : '' ?>"
                                        id="tk-tab-activos"
                                        data-bs-toggle="tab"
                                        data-bs-target="#tk-pane-activos"
                                        role="tab"
                                        aria-controls="tk-pane-activos"
                                        aria-selected="<?= $tab_default === 'activos' ? 'true' : 'false' ?>">
                                        <i class="bi bi-lightning-charge-fill"></i>
                                        <span>En curso</span>
                                        <span class="tk-tabs__badge"><?= $count_activos ?></span>
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button type="button"
                                        class="nav-link tk-tabs__btn tk-tabs__btn--done<?= $tab_default === 'terminados' ? ' active' : '' ?>"
                                        id="tk-tab-terminados"
                                        data-bs-toggle="tab"
                                        data-bs-target="#tk-pane-terminados"
                                        role="tab"
                                        aria-controls="tk-pane-terminados"
                                        aria-selected="<?= $tab_default === 'terminados' ? 'true' : 'false' ?>">
                                        <i class="bi bi-check2-circle"></i>
                                        <span>Terminados</span>
                                        <span class="tk-tabs__badge"><?= $count_terminados ?></span>
                                    </button>
                                </li>
                            </ul>
                            <label class="tk-search" for="tkSearchInput">
                                <span class="tk-search__icon" aria-hidden="true"><i class="bi bi-search"></i></span>
                                <input type="text" id="tkSearchInput" class="tk-search__input"
                                    placeholder="Buscar por título, # o proyecto…"
                                    autocomplete="off"
                                    spellcheck="false"
                                    aria-label="Buscar tickets">
                                <button type="button" class="tk-search__clear" id="tkSearchClear" hidden title="Limpiar búsqueda" aria-label="Limpiar búsqueda">
                                    <i class="bi bi-x"></i>
                                </button>
                            </label>
                        </div>

                        <div class="tab-content tk-tabs__content">
                            <div class="tab-pane fade<?= $tab_default === 'activos' ? ' show active' : '' ?>"
                                id="tk-pane-activos" role="tabpanel" aria-labelledby="tk-tab-activos" tabindex="0">
                                <p class="tk-tabs__hint"><i class="bi bi-info-circle-fill"></i> Tickets abiertos · <strong>Pendiente</strong> = en espera · <strong>En proceso</strong> = ya lo atendemos</p>
                                <?php if ($count_activos === 0): ?>
                                    <div class="tk-card">
                                        <div class="tk-empty tk-empty--compact">
                                            <h5>No tienes tickets en curso</h5>
                                            <p>Todo cerrado. Si necesitas algo, crea un ticket nuevo.</p>
                                            <button type="button" class="tk-btn tk-btn--primary mt-2" onclick="abrirModalNuevoTicket()">
                                                <i class="bi bi-plus-circle"></i> Crear ticket
                                            </button>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="d-none d-md-block">
                                        <div class="card tk-card">
                                            <div class="card-body">
                                                <div class="table-responsive">
                                                    <table class="table table-striped tk-tickets-table" style="width:100%">
                                                        <thead>
                                                            <tr>
                                                                <th>Referencia</th>
                                                                <th>Título</th>
                                                                <th>Proyecto</th>
                                                                <th>Estado</th>
                                                                <th>Prioridad</th>
                                                                <th>Creado</th>
                                                                <th>Fecha estimada</th>
                                                                <th>Acciones</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php $tk_render_desktop_rows($tickets_activos); ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-block d-md-none">
                                        <div class="tickets-list-mobile">
                                            <?php $tk_render_mobile_cards($tickets_activos); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="tab-pane fade<?= $tab_default === 'terminados' ? ' show active' : '' ?>"
                                id="tk-pane-terminados" role="tabpanel" aria-labelledby="tk-tab-terminados" tabindex="0">
                                <p class="tk-tabs__hint"><i class="bi bi-check2-circle"></i> Historial de casos ya cerrados · más recientes primero</p>
                                <?php if ($count_terminados === 0): ?>
                                    <div class="tk-card">
                                        <div class="tk-empty tk-empty--compact">
                                            <h5>Aún no hay tickets terminados</h5>
                                            <p>Cuando un caso se cierre, aparecerá aquí para consulta.</p>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="d-none d-md-block">
                                        <div class="card tk-card">
                                            <div class="card-body">
                                                <div class="table-responsive">
                                                    <table class="table table-striped tk-tickets-table tk-tickets-table--done" style="width:100%">
                                                        <thead>
                                                            <tr>
                                                                <th>Referencia</th>
                                                                <th>Título</th>
                                                                <th>Proyecto</th>
                                                                <th>Estado</th>
                                                                <th>Prioridad</th>
                                                                <th>Creado</th>
                                                                <th>Fecha estimada</th>
                                                                <th>Acciones</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php $tk_render_desktop_rows($tickets_terminados); ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-block d-md-none">
                                        <div class="tickets-list-mobile">
                                            <?php $tk_render_mobile_cards($tickets_terminados); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Modal crear ticket — wizard 3 pasos -->
        <div class="modal fade" id="nuevoTicketModal" tabindex="-1" aria-labelledby="nuevoTicketModalLabel">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="nuevoTicketModalLabel">
                            <i class="bi bi-plus-circle me-2"></i>Crear ticket de soporte
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <form id="formNuevoTicket" enctype="multipart/form-data" novalidate>
                        <div class="modal-body">
                            <nav class="tk-wizard-steps" aria-label="Pasos del ticket">
                                <button type="button" class="tk-wizard-step is-active" data-goto="1">
                                    <span class="tk-wizard-step__num">1</span>
                                    <span class="tk-wizard-step__label">Contexto</span>
                                </button>
                                <button type="button" class="tk-wizard-step" data-goto="2">
                                    <span class="tk-wizard-step__num">2</span>
                                    <span class="tk-wizard-step__label">Detalle</span>
                                </button>
                                <button type="button" class="tk-wizard-step" data-goto="3">
                                    <span class="tk-wizard-step__num">3</span>
                                    <span class="tk-wizard-step__label">Envío</span>
                                </button>
                            </nav>

                            <!-- Paso 1 -->
                            <div class="tk-wizard-pane is-active" data-step="1">
                                <p class="tk-wizard-tip">
                                    <i class="bi bi-info-circle"></i>
                                    Elige el tipo de caso y un título claro. Puedes vincular un proyecto si aplica.
                                </p>

                                <div class="mb-3">
                                    <label class="form-label">Tipo de caso *</label>
                                    <div class="tk-type-grid" role="radiogroup" aria-label="Tipo de caso">
                                        <label class="tk-type-card">
                                            <input type="radio" name="tipo_caso" value="problema" checked>
                                            <span class="tk-type-card__body">
                                                <i class="bi bi-bug"></i>
                                                <strong>Problema</strong>
                                                <small>Algo no funciona como debería</small>
                                            </span>
                                        </label>
                                        <label class="tk-type-card">
                                            <input type="radio" name="tipo_caso" value="cambio">
                                            <span class="tk-type-card__body">
                                                <i class="bi bi-pencil-square"></i>
                                                <strong>Cambio</strong>
                                                <small>Ajuste o mejora en tu sitio/sistema</small>
                                            </span>
                                        </label>
                                        <label class="tk-type-card">
                                            <input type="radio" name="tipo_caso" value="duda">
                                            <span class="tk-type-card__body">
                                                <i class="bi bi-question-circle"></i>
                                                <strong>Duda</strong>
                                                <small>Consulta o aclaración</small>
                                            </span>
                                        </label>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="tkTitulo">Título del ticket *</label>
                                    <input type="text" class="form-control" id="tkTitulo" name="titulo" required maxlength="180"
                                        placeholder="Ej. No puedo iniciar sesión en el panel">
                                    <div class="form-text">Mínimo 5 caracteres. Sé específico.</div>
                                </div>

                                <div class="mb-0">
                                    <label class="form-label" for="tkProyecto">Proyecto relacionado</label>
                                    <select class="form-select" id="tkProyecto" name="id_proyecto">
                                        <option value="">Sin proyecto (opcional)</option>
                                        <?php foreach ($proyectos as $proyecto): ?>
                                            <option value="<?= (int) $proyecto['id_proyecto'] ?>">
                                                <?= htmlspecialchars($proyecto['nombre_proyecto']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Paso 2 -->
                            <div class="tk-wizard-pane" data-step="2" hidden>
                                <p class="tk-wizard-tip">
                                    <i class="bi bi-info-circle"></i>
                                    Con estas respuestas armamos el mismo tipo de brief que usa el equipo en solicitudes.
                                </p>

                                <div class="mb-3">
                                    <label class="form-label" for="tkQuePasa">¿Qué está pasando? *</label>
                                    <textarea class="form-control" id="tkQuePasa" name="que_pasa" rows="3" required
                                        placeholder="Describe el problema, el cambio que pides o tu duda."></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="tkQueEspera">¿Qué debería pasar / resultado esperado? *</label>
                                    <textarea class="form-control" id="tkQueEspera" name="que_espera" rows="2" required
                                        placeholder="Ej. Poder entrar con mi correo y ver el dashboard."></textarea>
                                </div>

                                <div class="mb-0">
                                    <label class="form-label" for="tkDatosUtiles">Datos útiles (opcional)</label>
                                    <textarea class="form-control" id="tkDatosUtiles" name="datos_utiles" rows="3"
                                        placeholder="URL, pasos para reproducir, usuario de prueba, navegador, etc."></textarea>
                                </div>
                            </div>

                            <!-- Paso 3 -->
                            <div class="tk-wizard-pane" data-step="3" hidden>
                                <p class="tk-wizard-tip">
                                    <i class="bi bi-camera"></i>
                                    Una captura ayuda mucho. Luego elige la prioridad y envía.
                                </p>

                                <div class="mb-3">
                                    <label class="form-label">Archivos o capturas</label>
                                    <div class="file-upload-area" id="attachmentsSection">
                                        <div class="file-drop-zone" id="fileDropZone">
                                            <div class="file-drop-content">
                                                <i class="bi bi-cloud-upload fs-1 text-muted mb-2"></i>
                                                <h6 class="text-muted mb-1">Arrastra aquí o selecciona archivos</h6>
                                                <p class="text-muted small mb-2">
                                                    jpg, png, gif, pdf, doc, zip · máx. 10MB c/u
                                                </p>
                                                <button type="button" class="tk-btn tk-btn--ghost" id="selectFilesBtn">
                                                    <i class="bi bi-folder2-open"></i> Seleccionar
                                                </button>
                                            </div>
                                        </div>
                                        <input type="file" id="fileInput" name="archivos[]" multiple
                                            accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt,.zip,.rar" style="display:none">
                                        <div id="selectedFilesList" class="selected-files-container mt-3" style="display:none">
                                            <h6 class="mb-3"><i class="bi bi-files me-2"></i>Archivos seleccionados</h6>
                                            <div id="filesPreview"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-0">
                                    <label class="form-label" for="tkPrioridad">Prioridad *</label>
                                    <select class="form-select" id="tkPrioridad" name="prioridad" required>
                                        <option value="Baja">Baja</option>
                                        <option value="Media" selected>Media</option>
                                        <option value="Alta">Alta</option>
                                    </select>
                                </div>

                                <textarea name="descripcion" id="tkDescripcionHidden" hidden></textarea>
                            </div>
                        </div>
                        <div class="modal-footer tk-wizard-footer">
                            <button type="button" class="tk-btn tk-btn--ghost" data-bs-dismiss="modal">Cancelar</button>
                            <div class="tk-wizard-footer__nav">
                                <button type="button" class="tk-btn tk-btn--ghost" id="tkWizardPrev" hidden>
                                    <i class="bi bi-arrow-left"></i> Atrás
                                </button>
                                <button type="button" class="tk-btn tk-btn--primary" id="tkWizardNext">
                                    Siguiente <i class="bi bi-arrow-right"></i>
                                </button>
                                <button type="submit" class="tk-btn tk-btn--primary" id="tkWizardSubmit" hidden>
                                    <i class="bi bi-send-fill"></i> Enviar solicitud
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="detalleTicketModal" tabindex="-1">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-card-list me-2"></i>Detalle del ticket</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div id="contenidoDetalleTicket"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="tk-btn tk-btn--ghost" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

        <script>
            let tkWizardStep = 1;
            const TK_WIZARD_MAX = 3;
            const tipoLabels = {
                problema: 'Problema / error',
                cambio: 'Cambio o mejora',
                duda: 'Duda o consulta'
            };

            function abrirModalNuevoTicket() {
                resetTicketWizard();
                $('#nuevoTicketModal').modal('show');
            }

            function verDetalleTicket(ticketId) {
                $('#contenidoDetalleTicket').html(`
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="mt-2">Cargando detalles del ticket...</p>
                    </div>
                `);
                $('#detalleTicketModal').modal('show');
                $.ajax({
                    url: 'obtener_detalle_ticket.php',
                    method: 'GET',
                    data: { id: ticketId },
                    success: function (response) {
                        $('#contenidoDetalleTicket').html(response);
                        formatDescriptions();
                    },
                    error: function () {
                        $('#contenidoDetalleTicket').html(`
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                Error al cargar los detalles del ticket. Inténtalo nuevamente.
                            </div>
                        `);
                    }
                });
            }

            function formatDescriptions() {
                const descriptionElement = document.querySelector('.description-content');
                if (descriptionElement) {
                    descriptionElement.innerHTML = formatDescriptionText(descriptionElement.textContent);
                }
                document.querySelectorAll('.comment-content, .comment-text').forEach(comment => {
                    comment.innerHTML = formatDescriptionText(comment.textContent);
                });
            }

            function formatDescriptionText(text) {
                if (!text) return '';
                let formattedText = text.replace(/\n/g, '<br>');
                formattedText = formattedText.replace(/^- (.+)$/gm, '<li>$1</li>');
                formattedText = formattedText.replace(/^\* (.+)$/gm, '<li>$1</li>');
                formattedText = formattedText.replace(/^(\d+)\. (.+)$/gm, '<li>$2</li>');
                if (formattedText.includes('<li>')) {
                    formattedText = formattedText.replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>');
                }
                formattedText = formattedText.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
                return formattedText;
            }

            function getTipoCaso() {
                const el = document.querySelector('input[name="tipo_caso"]:checked');
                return el ? el.value : 'problema';
            }

            /** Misma estructura que requerimientos_documento_a_descripcion() en admin */
            function composeTicketDescripcion() {
                const tipo = getTipoCaso();
                const tipoLabel = tipoLabels[tipo] || 'Solicitud';
                const quePasa = ($('#tkQuePasa').val() || '').trim();
                const queEspera = ($('#tkQueEspera').val() || '').trim();
                const datos = ($('#tkDatosUtiles').val() || '').trim();
                const partes = [];

                partes.push('## Resumen de la solicitud\n' + quePasa);

                let detalle = 'Tipo de caso: ' + tipoLabel + '.';
                if (datos) {
                    detalle += '\n\n' + datos;
                }
                partes.push('## Detalle técnico\n' + detalle);

                partes.push(
                    '## Requerimientos funcionales\n' +
                    '- **RF-01 — ' + tipoLabel + '**: ' + quePasa
                );

                partes.push('## Criterios de aceptación\n- ' + queEspera);

                return partes.join('\n\n');
            }

            function suggestTitleIfEmpty() {
                const titulo = ($('#tkTitulo').val() || '').trim();
                if (titulo.length >= 5) return;
                const quePasa = ($('#tkQuePasa').val() || '').trim();
                if (!quePasa) return;
                const tipo = getTipoCaso();
                const prefix = { problema: 'Problema: ', cambio: 'Cambio: ', duda: 'Consulta: ' }[tipo] || '';
                let suggestion = prefix + quePasa.replace(/\s+/g, ' ');
                if (suggestion.length > 80) suggestion = suggestion.slice(0, 77) + '…';
                $('#tkTitulo').val(suggestion);
            }

            function showWizardStep(step) {
                tkWizardStep = Math.max(1, Math.min(TK_WIZARD_MAX, step));
                document.querySelectorAll('.tk-wizard-pane').forEach(pane => {
                    const n = parseInt(pane.getAttribute('data-step'), 10);
                    const active = n === tkWizardStep;
                    pane.classList.toggle('is-active', active);
                    pane.hidden = !active;
                });
                document.querySelectorAll('.tk-wizard-step').forEach(btn => {
                    const n = parseInt(btn.getAttribute('data-goto'), 10);
                    btn.classList.toggle('is-active', n === tkWizardStep);
                    btn.classList.toggle('is-done', n < tkWizardStep);
                });
                $('#tkWizardPrev').prop('hidden', tkWizardStep === 1);
                $('#tkWizardNext').prop('hidden', tkWizardStep === TK_WIZARD_MAX);
                $('#tkWizardSubmit').prop('hidden', tkWizardStep !== TK_WIZARD_MAX);
            }

            function clearFieldErrors() {
                $('#formNuevoTicket .is-invalid').removeClass('is-invalid');
                $('#formNuevoTicket .invalid-feedback-custom').remove();
            }

            function markInvalid($el, msg) {
                $el.addClass('is-invalid');
                $el.after('<div class="invalid-feedback invalid-feedback-custom d-block">' + msg + '</div>');
            }

            function validateWizardStep(step) {
                clearFieldErrors();
                let ok = true;

                if (step === 1) {
                    const $titulo = $('#tkTitulo');
                    if ($titulo.val().trim().length < 5) {
                        markInvalid($titulo, 'El título debe tener al menos 5 caracteres.');
                        ok = false;
                    }
                }

                if (step === 2) {
                    const $pasa = $('#tkQuePasa');
                    const $espera = $('#tkQueEspera');
                    if ($pasa.val().trim().length < 10) {
                        markInvalid($pasa, 'Cuéntanos un poco más (mín. 10 caracteres).');
                        ok = false;
                    }
                    if ($espera.val().trim().length < 5) {
                        markInvalid($espera, 'Indica el resultado esperado (mín. 5 caracteres).');
                        ok = false;
                    }
                }

                if (!ok) {
                    $('#formNuevoTicket .is-invalid:first').focus();
                }
                return ok;
            }

            function resetTicketWizard() {
                $('#formNuevoTicket')[0].reset();
                $('input[name="tipo_caso"][value="problema"]').prop('checked', true);
                $('#tkPrioridad').val('Media');
                $('#tkDescripcionHidden').val('');
                clearSelectedFiles();
                clearFieldErrors();
                showWizardStep(1);
            }

            $('#tkWizardNext').on('click', function () {
                if (!validateWizardStep(tkWizardStep)) return;
                if (tkWizardStep === 1) {
                    // ok
                }
                if (tkWizardStep === 2) {
                    suggestTitleIfEmpty();
                }
                showWizardStep(tkWizardStep + 1);
            });

            $('#tkWizardPrev').on('click', function () {
                showWizardStep(tkWizardStep - 1);
            });

            document.querySelectorAll('.tk-wizard-step').forEach(btn => {
                btn.addEventListener('click', function () {
                    const goto = parseInt(this.getAttribute('data-goto'), 10);
                    if (goto < tkWizardStep) {
                        showWizardStep(goto);
                        return;
                    }
                    for (let s = tkWizardStep; s < goto; s++) {
                        if (!validateWizardStep(s)) return;
                        if (s === 2) suggestTitleIfEmpty();
                    }
                    showWizardStep(goto);
                });
            });

            $('#nuevoTicketModal').on('hidden.bs.modal', function () {
                resetTicketWizard();
            });

            let selectedFiles = [];
            const maxFileSize = 10 * 1024 * 1024;
            const allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip', 'rar'];
            const fileDropZone = document.getElementById('fileDropZone');
            const fileInput = document.getElementById('fileInput');
            const selectFilesBtn = document.getElementById('selectFilesBtn');

            let openFileDialogLock = false;
            function openFileDialogOnce() {
                if (openFileDialogLock) return;
                openFileDialogLock = true;
                setTimeout(() => { openFileDialogLock = false; }, 700);
                fileInput.click();
            }

            selectFilesBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                openFileDialogOnce();
            });

            fileDropZone.addEventListener('click', function (e) {
                if (e.target && e.target.closest && e.target.closest('#selectFilesBtn')) return;
                e.preventDefault();
                e.stopPropagation();
                openFileDialogOnce();
            });

            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                fileDropZone.addEventListener(eventName, function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                }, false);
            });
            ['dragenter', 'dragover'].forEach(eventName => {
                fileDropZone.addEventListener(eventName, function () {
                    fileDropZone.classList.add('dragover');
                }, false);
            });
            ['dragleave', 'drop'].forEach(eventName => {
                fileDropZone.addEventListener(eventName, function () {
                    fileDropZone.classList.remove('dragover');
                }, false);
            });
            fileDropZone.addEventListener('drop', function (e) {
                handleFiles(e.dataTransfer.files);
            }, false);
            fileInput.addEventListener('change', function () {
                handleFiles(this.files);
            });

            function handleFiles(files) {
                [...files].forEach(addFile);
            }

            function addFile(file) {
                if (!validateFile(file)) return;
                if (selectedFiles.find(f => f.name === file.name && f.size === file.size)) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Archivo duplicado',
                        text: `El archivo "${file.name}" ya ha sido seleccionado.`
                    });
                    return;
                }
                selectedFiles.push(file);
                displaySelectedFiles();
                updateFileInput();
            }

            function validateFile(file) {
                if (file.size > maxFileSize) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Archivo muy grande',
                        text: `El archivo "${file.name}" es demasiado grande. El tamaño máximo es 10MB.`
                    });
                    return false;
                }
                const extension = file.name.split('.').pop().toLowerCase();
                if (!allowedExtensions.includes(extension)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Tipo de archivo no permitido',
                        text: `El archivo "${file.name}" no está permitido. Tipos: ${allowedExtensions.join(', ')}`
                    });
                    return false;
                }
                return true;
            }

            function displaySelectedFiles() {
                const container = document.getElementById('selectedFilesList');
                const preview = document.getElementById('filesPreview');
                if (selectedFiles.length === 0) {
                    container.style.display = 'none';
                    return;
                }
                container.style.display = 'block';
                preview.innerHTML = '';
                selectedFiles.forEach((file, index) => {
                    preview.appendChild(createFilePreview(file, index));
                });
            }

            function createFilePreview(file, index) {
                const div = document.createElement('div');
                div.className = 'file-preview-item';
                const extension = file.name.split('.').pop().toLowerCase();
                const fileType = getFileType(extension);
                const fileSize = formatFileSize(file.size);
                let iconContent = '';
                if (fileType === 'image' && file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        const img = div.querySelector('.file-icon img');
                        if (img) img.src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                    iconContent = '<div class="file-icon image"><img class="image-preview" alt="Vista previa"></div>';
                } else {
                    iconContent = `<div class="file-icon ${fileType}"><i class="bi ${getFileIcon(fileType)}"></i></div>`;
                }
                div.innerHTML = `
                    ${iconContent}
                    <div class="file-info">
                        <div class="file-name">${file.name}</div>
                        <div class="file-size">${fileSize}</div>
                    </div>
                    <button type="button" class="file-remove" onclick="removeFile(${index})" title="Eliminar archivo">
                        <i class="bi bi-x"></i>
                    </button>
                `;
                return div;
            }

            function getFileType(extension) {
                if (['jpg', 'jpeg', 'png', 'gif'].includes(extension)) return 'image';
                if (extension === 'pdf') return 'pdf';
                if (['doc', 'docx', 'txt'].includes(extension)) return 'document';
                if (['zip', 'rar'].includes(extension)) return 'archive';
                return 'other';
            }

            function getFileIcon(type) {
                switch (type) {
                    case 'image': return 'bi-image';
                    case 'pdf': return 'bi-file-earmark-pdf';
                    case 'document': return 'bi-file-earmark-text';
                    case 'archive': return 'bi-file-earmark-zip';
                    default: return 'bi-file-earmark';
                }
            }

            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }

            function removeFile(index) {
                selectedFiles.splice(index, 1);
                displaySelectedFiles();
                updateFileInput();
            }

            function clearSelectedFiles() {
                selectedFiles = [];
                displaySelectedFiles();
                updateFileInput();
            }

            function updateFileInput() {
                const dataTransfer = new DataTransfer();
                selectedFiles.forEach(file => dataTransfer.items.add(file));
                fileInput.files = dataTransfer.files;
            }

            $(document).on('submit', '#formAgregarComentario', function (e) {
                e.preventDefault();
                const formData = new FormData(this);
                const submitBtn = $(this).find('button[type="submit"]');
                const originalText = submitBtn.html();
                submitBtn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-2"></i>Enviando...');
                $.ajax({
                    url: 'agregar_comentario_ticket.php',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function (result) {
                        if (result && result.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Comentario agregado',
                                text: 'Tu comentario ha sido agregado exitosamente.',
                                timer: 2000,
                                showConfirmButton: false
                            });
                            verDetalleTicket(formData.get('ticket_id'));
                            $('#formAgregarComentario')[0].reset();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: (result && result.message) ? result.message : 'No se pudo agregar el comentario'
                            });
                        }
                    },
                    error: function () {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de conexión',
                            text: 'No se pudo enviar el comentario. Inténtalo nuevamente.'
                        });
                    },
                    complete: function () {
                        submitBtn.prop('disabled', false).html(originalText);
                    }
                });
            });

            $('#formNuevoTicket').on('submit', function (e) {
                e.preventDefault();
                if (!validateWizardStep(1)) {
                    showWizardStep(1);
                    return;
                }
                if (!validateWizardStep(2)) {
                    showWizardStep(2);
                    return;
                }
                suggestTitleIfEmpty();

                const titulo = $('#tkTitulo').val().trim();
                const descripcion = composeTicketDescripcion();
                $('#tkDescripcionHidden').val(descripcion);

                if (titulo.length < 5 || descripcion.length < 10) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Revisa los datos',
                        text: 'Falta completar título o detalle del ticket.'
                    });
                    return;
                }

                const fd = new FormData();
                fd.append('titulo', titulo);
                fd.append('prioridad', $('#tkPrioridad').val());
                fd.append('id_proyecto', $('#tkProyecto').val());
                fd.append('descripcion', descripcion);
                for (let i = 0; i < fileInput.files.length; i++) {
                    fd.append('archivos[]', fileInput.files[i]);
                }

                const submitBtn = $('#tkWizardSubmit');
                const originalBtnHtml = submitBtn.html();
                submitBtn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-2"></i>Enviando...');

                Swal.fire({
                    title: 'Enviando ticket...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                $.ajax({
                    url: 'procesar_nuevo_ticket.php',
                    method: 'POST',
                    data: fd,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function (result) {
                        if (result && result.success) {
                            const ticketId = result.ticket_id || result.id || '';
                            const pendingAhead = (typeof result.pending_ahead !== 'undefined')
                                ? parseInt(result.pending_ahead, 10) : 0;
                            let extra = '';
                            if (result.adjuntos && result.adjuntos.errores && result.adjuntos.errores.length) {
                                extra = '<p class="tk-swal-errors small mt-2">Algunos archivos no se subieron:<br>- '
                                    + result.adjuntos.errores.join('<br>- ') + '</p>';
                            }

                            const idLabel = ticketId ? ' #' + ticketId : '';
                            const queueNote = pendingAhead > 0
                                ? '<p class="mb-2">Hay <strong>' + pendingAhead + '</strong> ticket(s) en cola antes del tuyo. Plazo estimado de atención: hasta <strong>4 días</strong>.</p>'
                                : '<p class="mb-2">Tu solicitud quedó en cola. Plazo estimado de atención: hasta <strong>4 días</strong>.</p>';

                            Swal.fire({
                                icon: 'success',
                                title: 'Ticket creado' + idLabel,
                                html:
                                    '<p class="mb-2">Registramos tu solicitud. El equipo la revisará con el mismo formato de brief que usamos en solicitudes.</p>'
                                    + queueNote
                                    + '<p class="mb-2"><a href="https://wa.me/524771181285" target="_blank" rel="noopener noreferrer" class="tk-btn tk-btn--primary" style="display:inline-flex;text-decoration:none;"><i class="bi bi-whatsapp"></i> WhatsApp urgente</a></p>'
                                    + '<p class="small text-muted mb-0">Si hubiera un cargo adicional, te contactamos antes por WhatsApp o correo.</p>'
                                    + extra,
                                confirmButtonText: 'Entendido',
                                customClass: { popup: 'swal-wide' }
                            }).then(() => {
                                $('#nuevoTicketModal').modal('hide');
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error al crear el ticket',
                                text: (result && result.message) ? result.message : 'Ha ocurrido un error inesperado'
                            });
                        }
                    },
                    error: function (xhr) {
                        let msg = 'No se pudo enviar el ticket. Inténtalo nuevamente.';
                        if (xhr && xhr.responseText) {
                            msg += ' Detalle: ' + xhr.responseText.substring(0, 400);
                        }
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de conexión',
                            text: msg
                        });
                    },
                    complete: function () {
                        submitBtn.prop('disabled', false).html(originalBtnHtml);
                    }
                });
            });

            showWizardStep(1);

            // Buscador de tickets (filtra tabla desktop y cards móvil en ambas pestañas)
            (function initTicketSearch() {
                const input = document.getElementById('tkSearchInput');
                const clearBtn = document.getElementById('tkSearchClear');
                if (!input) return;

                function normalize(text) {
                    return (text || '').toString().toLowerCase()
                        .normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                }

                function ensureNoResults(pane, visible) {
                    let empty = pane.querySelector('.tk-search-empty');
                    if (visible) {
                        if (empty) empty.remove();
                        return;
                    }
                    if (!empty) {
                        empty = document.createElement('div');
                        empty.className = 'tk-card tk-search-empty';
                        empty.innerHTML = '<div class="tk-empty tk-empty--compact"><h5>Sin resultados</h5><p>Prueba con otro término o cambia de pestaña.</p></div>';
                        pane.appendChild(empty);
                    }
                }

                function filterPane(pane, q, estadoFilter) {
                    if (!pane) return;
                    const rows = pane.querySelectorAll('.tk-tickets-table tbody tr');
                    const cards = pane.querySelectorAll('.ticket-item-mobile');
                    let visible = 0;

                    function matchItem(el) {
                        const textOk = !q || normalize(el.textContent).includes(q);
                        const est = el.getAttribute('data-tk-estado') || '';
                        const estOk = !estadoFilter || est === estadoFilter;
                        return textOk && estOk;
                    }

                    rows.forEach(row => {
                        const match = matchItem(row);
                        row.style.display = match ? '' : 'none';
                        if (match) visible++;
                    });
                    cards.forEach(card => {
                        const match = matchItem(card);
                        card.style.display = match ? '' : 'none';
                        if (match) visible++;
                    });

                    const hasItems = rows.length > 0 || cards.length > 0;
                    if (hasItems) {
                        ensureNoResults(pane, visible > 0 || (!q && !estadoFilter));
                    }
                }

                function applySearch() {
                    const q = normalize(input.value.trim());
                    const estadoFilter = window.__tkEstadoFilter || '';
                    if (clearBtn) clearBtn.hidden = !input.value.trim() && !estadoFilter;
                    filterPane(document.getElementById('tk-pane-activos'), q, estadoFilter);
                    filterPane(document.getElementById('tk-pane-terminados'), q, '');
                }

                window.__tkApplyTicketFilters = applySearch;

                input.addEventListener('input', function () {
                    applySearch();
                });
                if (clearBtn) {
                    clearBtn.addEventListener('click', function () {
                        input.value = '';
                        window.__tkEstadoFilter = '';
                        document.querySelectorAll('.tk-summary__card.is-active').forEach(function (c) {
                            c.classList.remove('is-active');
                        });
                        applySearch();
                        input.focus();
                    });
                }
                document.querySelectorAll('[data-bs-toggle="tab"]').forEach(btn => {
                    btn.addEventListener('shown.bs.tab', applySearch);
                });
            })();

            (function initTkSummaryGoto() {
                document.querySelectorAll('[data-tk-goto]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        const goto = btn.getAttribute('data-tk-goto');
                        const filter = btn.getAttribute('data-tk-filter') || '';
                        window.__tkEstadoFilter = filter;

                        document.querySelectorAll('.tk-summary__card.is-active').forEach(function (c) {
                            c.classList.remove('is-active');
                        });
                        btn.classList.add('is-active');

                        const tabBtn = document.getElementById(goto === 'terminados' ? 'tk-tab-terminados' : 'tk-tab-activos');
                        if (tabBtn && typeof bootstrap !== 'undefined') {
                            bootstrap.Tab.getOrCreateInstance(tabBtn).show();
                        } else if (tabBtn) {
                            tabBtn.click();
                        }

                        if (typeof window.__tkApplyTicketFilters === 'function') {
                            window.__tkApplyTicketFilters();
                        }

                        const tabs = document.querySelector('.tk-tabs');
                        if (tabs) {
                            tabs.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    });
                });
            })();
        </script>
    </body>
    </html>
    <?php include('footer.php'); ?>
<?php
} else {
    header('Location: ingreso.php');
    exit();
}
