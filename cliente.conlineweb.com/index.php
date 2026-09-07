<?php
require_once __DIR__ . '/includes/cliente_session.php';
require_once __DIR__ . '/includes/cliente_doc_url.php';
cliente_start_session();
if (!cliente_is_logged_in()) {
    header('Location: ingreso.php');
    exit;
}

include 'conn.php';
$usrid = (int) $_SESSION['uid'];
$doc_url = cliente_doc_url();

// Datos del cliente (saludo)
$cliente_nombre = '';
$cliente_empresa = '';
$qCli = mysqli_query($conn, 'SELECT nombre_contacto, empresa FROM clientes WHERE id = ' . $usrid . ' LIMIT 1');
if ($qCli && $rowCli = mysqli_fetch_assoc($qCli)) {
    $cliente_nombre = trim((string) ($rowCli['nombre_contacto'] ?? ''));
    $cliente_empresa = trim((string) ($rowCli['empresa'] ?? ''));
}
$saludo_nombre = $cliente_nombre !== '' ? $cliente_nombre : 'cliente';

// Sitios con URL
$total_sitios = 0;
$res_count = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM dominios
     WHERE cliente_id = {$usrid}
       AND eliminado = 0
       AND url_dominio IS NOT NULL
       AND url_dominio != ''"
);
if ($res_count && $row_count = mysqli_fetch_assoc($res_count)) {
    $total_sitios = (int) ($row_count['total'] ?? 0);
}

// Pagos pendientes (misma lógica que el menú)
$total_pendientes = 0;
$qPagos = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total_pendientes FROM pagos
     WHERE id_clie = {$usrid} AND estatus != 1 AND Registro = 0"
);
if ($qPagos && $rowPagos = mysqli_fetch_assoc($qPagos)) {
    $total_pendientes = (int) ($rowPagos['total_pendientes'] ?? 0);
}

// Tickets abiertos (no finalizados)
$total_tickets_abiertos = 0;
$qTk = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM solicitudes
     WHERE id_cliente = {$usrid}
       AND (estado IS NULL OR estado <> 'Finalizado')"
);
if ($qTk && $rowTk = mysqli_fetch_assoc($qTk)) {
    $total_tickets_abiertos = (int) ($rowTk['total'] ?? 0);
}

$hora = (int) (new DateTime('now', new DateTimeZone('America/Mexico_City')))->format('G');
if ($hora >= 5 && $hora < 12) {
    $saludo = 'Buenos días';
} elseif ($hora >= 12 && $hora < 19) {
    $saludo = 'Buenas tardes';
} else {
    // 19:00–04:59
    $saludo = 'Buenas noches';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#000147">
    <?php require_once __DIR__ . '/includes/cliente_head_meta.php'; $clientePageTitle = 'Inicio'; ?>
    <title><?= htmlspecialchars(cliente_document_title('Inicio'), ENT_QUOTES, 'UTF-8') ?></title>
    <?= cliente_favicon_markup() ?>
    <link rel="stylesheet" href="assets/css/cliente-inicio.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/cliente-inicio.css') ?>">
</head>
<body>
    <div id="app">
        <?php include 'menu.php'; ?>

        <div class="main-content container-fluid ch-home">
            <header class="header-section ch-hero">
                <div class="header-content">
                    <div class="header-icon-container" aria-hidden="true">
                        <i class="bi bi-house-door-fill header-icon"></i>
                    </div>
                    <div class="header-text">
                        <h1 class="header-title"><?= htmlspecialchars($saludo) ?>, <?= htmlspecialchars($saludo_nombre) ?></h1>
                        <p class="header-subtitle">
                            <?php if ($cliente_empresa !== ''): ?>
                                <?= htmlspecialchars($cliente_empresa) ?> · resumen de tu cuenta
                            <?php else: ?>
                                Resumen de tu cuenta en el Portal de Clientes
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </header>

            <?php if ($total_pendientes > 0): ?>
            <a href="pagos.php" class="ch-alert ch-alert--urgent">
                <span class="ch-alert__icon"><i class="bi bi-exclamation-triangle-fill"></i></span>
                <span class="ch-alert__body">
                    <strong><?= (int) $total_pendientes ?> pago<?= $total_pendientes === 1 ? '' : 's' ?> pendiente<?= $total_pendientes === 1 ? '' : 's' ?></strong>
                    <span>Liquídalo<?= $total_pendientes === 1 ? '' : 's' ?> para mantener tus servicios activos sin interrupciones.</span>
                </span>
                <span class="ch-alert__cta">Pagar ahora <i class="bi bi-arrow-right"></i></span>
            </a>
            <?php else: ?>
            <div class="ch-alert ch-alert--ok">
                <span class="ch-alert__icon"><i class="bi bi-check-circle-fill"></i></span>
                <span class="ch-alert__body">
                    <strong>Tu cuenta está al corriente</strong>
                    <span>No tienes pagos pendientes. Te avisaremos antes de cada renovación.</span>
                </span>
            </div>
            <?php endif; ?>

            <section class="ch-kpi" aria-label="Resumen de cuenta">
                <a href="mis-sitios.php" class="ch-kpi__item">
                    <span class="ch-kpi__value"><?= (int) $total_sitios ?></span>
                    <span class="ch-kpi__label">Sitio<?= $total_sitios === 1 ? '' : 's' ?> web</span>
                </a>
                <a href="pagos.php" class="ch-kpi__item<?= $total_pendientes > 0 ? ' ch-kpi__item--warn' : ' ch-kpi__item--ok' ?>">
                    <span class="ch-kpi__value"><?= (int) $total_pendientes ?></span>
                    <span class="ch-kpi__label">Pago<?= $total_pendientes === 1 ? '' : 's' ?> pendiente<?= $total_pendientes === 1 ? '' : 's' ?></span>
                </a>
                <a href="tickets.php" class="ch-kpi__item">
                    <span class="ch-kpi__value"><?= (int) $total_tickets_abiertos ?></span>
                    <span class="ch-kpi__label">Ticket<?= $total_tickets_abiertos === 1 ? '' : 's' ?> abierto<?= $total_tickets_abiertos === 1 ? '' : 's' ?></span>
                </a>
            </section>

            <h2 class="ch-section-title">Accesos rápidos</h2>
            <div class="ch-quick-grid">
                <a href="pagos.php" class="ch-quick-card<?= $total_pendientes > 0 ? ' ch-quick-card--priority' : '' ?>">
                    <div class="ch-quick-icon"><i class="bi bi-credit-card-fill"></i></div>
                    <h3 class="ch-quick-title">Pagos</h3>
                    <p class="ch-quick-desc">
                        <?php if ($total_pendientes > 0): ?>
                            Tienes <?= (int) $total_pendientes ?> por liquidar. Historial y renovaciones.
                        <?php else: ?>
                            Historial de pagos, facturas y renovaciones.
                        <?php endif; ?>
                    </p>
                    <?php if ($total_pendientes > 0): ?>
                    <span class="ch-quick-badge"><?= (int) $total_pendientes ?> pendiente<?= $total_pendientes === 1 ? '' : 's' ?></span>
                    <?php endif; ?>
                    <span class="ch-quick-arrow">Abrir <i class="bi bi-arrow-right"></i></span>
                </a>

                <a href="mis-sitios.php" class="ch-quick-card">
                    <div class="ch-quick-icon"><i class="bi bi-hdd-stack-fill"></i></div>
                    <h3 class="ch-quick-title">Mis Sitios Web</h3>
                    <p class="ch-quick-desc">WordPress, cPanel/WHM y visita a tus sitios publicados.</p>
                    <span class="ch-quick-count"><?= (int) $total_sitios ?> sitio<?= $total_sitios === 1 ? '' : 's' ?></span>
                    <span class="ch-quick-arrow">Abrir <i class="bi bi-arrow-right"></i></span>
                </a>

                <a href="dominios.php" class="ch-quick-card">
                    <div class="ch-quick-icon"><i class="bi bi-globe2"></i></div>
                    <h3 class="ch-quick-title">Gestión de Dominios</h3>
                    <p class="ch-quick-desc">DNS, transferencias, vencimientos y renovación.</p>
                    <span class="ch-quick-arrow">Abrir <i class="bi bi-arrow-right"></i></span>
                </a>

                <a href="hosting.php" class="ch-quick-card">
                    <div class="ch-quick-icon"><i class="bi bi-hdd-rack-fill"></i></div>
                    <h3 class="ch-quick-title">Hosting</h3>
                    <p class="ch-quick-desc">Consulta tu plan, vencimiento y acceso al panel.</p>
                    <span class="ch-quick-arrow">Abrir <i class="bi bi-arrow-right"></i></span>
                </a>

                <a href="tickets.php" class="ch-quick-card">
                    <div class="ch-quick-icon"><i class="bi bi-headset"></i></div>
                    <h3 class="ch-quick-title">Soporte</h3>
                    <p class="ch-quick-desc">Crea y da seguimiento a tus tickets técnicos.</p>
                    <?php if ($total_tickets_abiertos > 0): ?>
                    <span class="ch-quick-count"><?= (int) $total_tickets_abiertos ?> abierto<?= $total_tickets_abiertos === 1 ? '' : 's' ?></span>
                    <?php endif; ?>
                    <span class="ch-quick-arrow">Mis tickets <i class="bi bi-arrow-right"></i></span>
                </a>

                <a href="cliente.php" class="ch-quick-card">
                    <div class="ch-quick-icon"><i class="bi bi-person-badge"></i></div>
                    <h3 class="ch-quick-title">Mi cuenta</h3>
                    <p class="ch-quick-desc">Datos de contacto, empresa y preferencias de tu perfil.</p>
                    <span class="ch-quick-arrow">Abrir <i class="bi bi-arrow-right"></i></span>
                </a>
            </div>

            <aside class="ch-help" aria-label="Ayuda">
                <div class="ch-help__text">
                    <h2 class="ch-help__title">¿Necesitas ayuda?</h2>
                    <p class="ch-help__desc">Consulta las guías del portal o abre un ticket. Te atendemos a la brevedad.</p>
                </div>
                <div class="ch-help__actions">
                    <a href="<?= htmlspecialchars($doc_url) ?>" class="ch-help__btn ch-help__btn--primary" target="_blank" rel="noopener noreferrer">
                        <i class="bi bi-book"></i> Documentación
                    </a>
                    <a href="tickets.php" class="ch-help__btn ch-help__btn--ghost">
                        <i class="bi bi-headset"></i> Crear ticket
                    </a>
                </div>
            </aside>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="assets/js/main.js"></script>
    <?php include 'footer.php'; ?>
</body>
</html>
