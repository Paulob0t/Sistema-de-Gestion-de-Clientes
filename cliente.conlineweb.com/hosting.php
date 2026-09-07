<?php
require_once __DIR__ . '/includes/cliente_session.php';
cliente_start_session();
if (!cliente_is_logged_in()) {
    header('Location: ingreso.php');
    exit;
}

include 'conn.php';
require_once __DIR__ . '/includes/cliente_dias_vencimiento.php';
$usrid = (int) $_SESSION['uid'];
$tab_raw = $_GET['tab'] ?? 'activos';
$tab_activa = ($tab_raw === 'pendiente_pago' || $tab_raw === 'inactivos') ? 'pendiente_pago' : 'activos';

function fechaEnEspanol($fecha) {
    if (empty($fecha) || $fecha === '0000-00-00') {
        return '—';
    }
    $meses = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
    ];
    $ts = strtotime($fecha);
    if ($ts === false) {
        return '—';
    }
    return date('j', $ts) . ' de ' . $meses[(int) date('n', $ts)] . ' del ' . date('Y', $ts);
}

$hostings_activos = [];
$hostings_pendiente = [];

$query = mysqli_query($conn, "SELECT * FROM hosting WHERE cliente_id = $usrid AND eliminado = 0 ORDER BY fecha_pago DESC");
if ($query) {
    while ($row = mysqli_fetch_assoc($query)) {
        if ((int) ($row['estado_producto'] ?? 0) === 1) {
            $hostings_activos[] = $row;
        } else {
            $hostings_pendiente[] = $row;
        }
    }
}

$cnt_activos = count($hostings_activos);
$cnt_pendiente = count($hostings_pendiente);
$total_hosting = $cnt_activos + $cnt_pendiente;

function hosting_plan_nombre(mysqli $conn, array $hosting): string {
    $plan_id = $hosting['producto'] ?? '';
    $plan_nombre = trim((string) ($hosting['producto'] ?? ''));
    if ($plan_id !== '') {
        $plan_query = mysqli_query($conn, "SELECT nombre FROM planes WHERE id = '" . mysqli_real_escape_string($conn, (string) $plan_id) . "' LIMIT 1");
        if ($plan_query && $plan_row = mysqli_fetch_assoc($plan_query)) {
            $plan_nombre = (string) $plan_row['nombre'];
        }
    }
    return $plan_nombre !== '' ? $plan_nombre : 'Plan de hosting';
}

function hosting_resolver_panel_url(array $h): string {
    $url = trim((string) ($h['url_acceso'] ?? ''));
    if ($url !== '') {
        return rtrim($url, '/');
    }

    $nom_host = trim((string) ($h['nom_host'] ?? ''));
    if ($nom_host !== '') {
        if (preg_match('/^https?:\/\//i', $nom_host)) {
            return rtrim($nom_host, '/');
        }
        $host = $nom_host;
        if (strpos($host, ':') === false) {
            $host .= (stripos($host, 'whm') !== false) ? ':2086' : ':2083';
        }
        return 'https://' . $host;
    }

    $dominio = trim((string) ($h['dominio'] ?? ''));
    if ($dominio !== '') {
        return 'https://cpanel.' . $dominio . ':2083';
    }

    return '';
}

function hosting_resolver_password(array $h): string {
    $pass = trim((string) ($h['contrasena'] ?? ''));
    if ($pass !== '' && $pass !== '---') {
        return $pass;
    }
    $pass_normal = trim((string) ($h['contrasena_normal'] ?? ''));
    return ($pass_normal !== '' && $pass_normal !== '---') ? $pass_normal : '';
}

function hosting_estado_texto(bool $activo, ?int $dias): array {
    if (!$activo) {
        return ['class' => 'status-pending-payment', 'icon' => 'bi-credit-card-fill', 'text' => 'Pendiente de pago', 'link' => true];
    }
    if ($dias !== null && $dias < 0) {
        return ['class' => 'status-expired', 'icon' => 'bi-exclamation-triangle-fill', 'text' => 'Vencido', 'link' => true];
    }
    if ($dias !== null && $dias <= 30) {
        return ['class' => 'status-warning', 'icon' => 'bi-clock-fill', 'text' => 'Por vencer', 'link' => false];
    }
    return ['class' => 'status-active', 'icon' => 'bi-lightning-fill', 'text' => 'Activo', 'link' => false];
}

function hosting_dias_texto(?int $dias): string {
    if ($dias === null) {
        return 'Sin fecha';
    }
    if ($dias < 0) {
        $n = abs($dias);
        return $n . ' día' . ($n === 1 ? '' : 's') . ' vencido' . ($n === 1 ? '' : 's');
    }
    if ($dias === 0) {
        return 'Vence hoy';
    }
    return $dias . ' día' . ($dias === 1 ? '' : 's') . ' restantes';
}

function render_hosting_card(mysqli $conn, array $h, bool $muted): string {
    $id_orden   = (int) ($h['id_orden'] ?? 0);
    $activo     = (int) ($h['estado_producto'] ?? 0) === 1;
    $plan       = hosting_plan_nombre($conn, $h);
    $tipo       = trim((string) ($h['tipo_producto'] ?? ''));
    $dominio    = trim((string) ($h['dominio'] ?? ''));
    $nom_host   = trim((string) ($h['nom_host'] ?? ''));
    $url_acceso = hosting_resolver_panel_url($h);
    $usuario    = trim((string) ($h['usuario'] ?? ''));
    $contrasena = hosting_resolver_password($h);
    $costo      = htmlspecialchars((string) ($h['costo_producto'] ?? '0'), ENT_QUOTES, 'UTF-8');
    $moneda     = ((int) ($h['id_forma_pago'] ?? 1) === 1) ? 'MXN' : 'USD';
    $is_whm     = strpos($url_acceso, ':2086') !== false || stripos($nom_host, 'whm') !== false;
    $panel_label = $is_whm ? 'WHM' : 'cPanel';
    $has_panel  = $url_acceso !== '' && $usuario !== '' && $usuario !== '---' && $contrasena !== '';
    $dias       = cliente_dias_vencimiento($h['fecha_pago'] ?? '');
    $estado     = hosting_estado_texto($activo, $dias);
    $card_class = 'ho-card' . ($muted ? ' ho-card--muted' : '');
    $search_blob = strtolower(trim($plan . ' ' . $dominio . ' ' . $nom_host . ' ' . $tipo));
    $dns_items  = [];
    for ($i = 1; $i <= 6; $i++) {
        $ns = trim((string) ($h['ns' . $i] ?? ''));
        if ($ns !== '') {
            $dns_items[] = $ns;
        }
    }

    ob_start();
    ?>
    <div class="<?= $card_class ?>"
         data-hosting-id="<?= $id_orden ?>"
         data-hosting-search="<?= htmlspecialchars($search_blob, ENT_QUOTES, 'UTF-8') ?>"
         data-panel-url="<?= htmlspecialchars($url_acceso, ENT_QUOTES, 'UTF-8') ?>"
         data-panel-user="<?= htmlspecialchars($usuario, ENT_QUOTES, 'UTF-8') ?>"
         data-panel-pass="<?= htmlspecialchars($contrasena, ENT_QUOTES, 'UTF-8') ?>"
         data-panel-type="<?= $is_whm ? 'whm' : 'cpanel' ?>">

        <div class="ho-module ho-module--header">
            <div class="ho-top-main">
                <div class="ho-icon<?= $muted ? ' ho-icon--muted' : '' ?>"><i class="bi bi-hdd-rack-fill"></i></div>
                <div class="ho-top-text">
                    <div class="ho-top-title-row">
                        <h3 class="ho-plan"><?= htmlspecialchars($plan, ENT_QUOTES, 'UTF-8') ?></h3>
                        <?php if (!empty($estado['link'])): ?>
                        <a href="pagos.php" class="ho-status <?= htmlspecialchars($estado['class'], ENT_QUOTES, 'UTF-8') ?> ho-status--link" title="Ir a pagos">
                            <i class="bi <?= htmlspecialchars($estado['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                            <?= htmlspecialchars($estado['text'], ENT_QUOTES, 'UTF-8') ?> · Ir a pagos
                        </a>
                        <?php else: ?>
                        <span class="ho-status <?= htmlspecialchars($estado['class'], ENT_QUOTES, 'UTF-8') ?>">
                            <i class="bi <?= htmlspecialchars($estado['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                            <?= htmlspecialchars($estado['text'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <p class="ho-sub">
                        <?php if ($dominio !== ''): ?>
                            <i class="bi bi-globe2"></i> <?= htmlspecialchars($dominio, ENT_QUOTES, 'UTF-8') ?>
                        <?php elseif ($tipo !== ''): ?>
                            <?= htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8') ?>
                        <?php else: ?>
                            Servicio de hosting
                        <?php endif; ?>
                    </p>
                    <?php if ($activo && $has_panel): ?>
                        <p class="ho-can-do">Puedes entrar al panel <?= $panel_label ?><?= $dominio !== '' ? ' o visitar tu sitio' : '' ?></p>
                    <?php elseif ($activo && !$has_panel): ?>
                        <p class="ho-can-do ho-can-do--muted">Plan activo · el acceso al panel aún no está configurado</p>
                    <?php else: ?>
                        <p class="ho-can-do ho-can-do--warn">Para reactivar el servicio, regulariza el pago</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="ho-module ho-module--summary">
            <div class="ho-summary">
                <div class="ho-summary-item">
                    <span class="ho-summary-label">Vencimiento</span>
                    <strong><?= cliente_fecha_corta($h['fecha_pago'] ?? '') ?></strong>
                    <small><?= htmlspecialchars(hosting_dias_texto($dias), ENT_QUOTES, 'UTF-8') ?></small>
                </div>
                <div class="ho-summary-item">
                    <span class="ho-summary-label">Costo</span>
                    <strong>$<?= $costo ?> <?= $moneda ?></strong>
                    <small>por año</small>
                </div>
                <div class="ho-summary-item">
                    <span class="ho-summary-label">Panel</span>
                    <strong><?= $panel_label ?></strong>
                    <small><?= $has_panel ? 'Acceso listo' : 'Sin credenciales' ?></small>
                </div>
                <?php if ($nom_host !== ''): ?>
                <div class="ho-summary-item ho-summary-item--server">
                    <span class="ho-summary-label">Servidor</span>
                    <strong title="<?= htmlspecialchars($nom_host, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($nom_host, ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="ho-module ho-module--actions">
            <div class="ho-actions-row">
                <?php if (!$activo): ?>
                <a href="pagos.php" class="ho-btn ho-btn--warn">
                    <i class="bi bi-credit-card-fill"></i> Ir a pagos
                </a>
                <?php endif; ?>

                <button type="button" class="ho-btn <?= $activo ? 'ho-btn--primary' : 'ho-btn--ghost' ?>"
                    onclick="loginHostingPanel('<?= $id_orden ?>')"
                    <?= $has_panel ? '' : 'disabled aria-disabled="true"' ?>>
                    <i class="bi bi-terminal-fill"></i> Entrar a <?= $panel_label ?>
                </button>

                <?php if ($dominio !== ''): ?>
                <button type="button" class="ho-btn ho-btn--ghost"
                    onclick="window.open('https://<?= htmlspecialchars($dominio, ENT_QUOTES, 'UTF-8') ?>', '_blank')">
                    <i class="bi bi-box-arrow-up-right"></i> Visitar sitio
                </button>
                <?php endif; ?>

                <?php if ($activo): ?>
                <a href="pagos.php" class="ho-btn ho-btn--ghost">
                    <i class="bi bi-receipt"></i> Ver pagos
                </a>
                <?php endif; ?>
            </div>

            <?php if (!$has_panel): ?>
            <p class="ho-creds-note">
                <i class="bi bi-info-circle"></i>
                Acceso <?= $panel_label ?> no configurado.
                <a href="tickets.php">Abrir ticket de soporte</a>
            </p>
            <?php endif; ?>
        </div>

        <div class="ho-module ho-module--extra">
            <details class="mi-details">
                <summary class="mi-details__summary">
                    <span class="mi-details__summary-left"><i class="bi bi-info-circle-fill"></i> Ver detalle</span>
                    <i class="bi bi-chevron-down mi-details__chevron"></i>
                </summary>
                <div class="mi-details__body">
                    <div class="mi-details__grid">
                        <div class="mi-card">
                            <div class="mi-card__icon"><i class="bi bi-calendar-plus"></i></div>
                            <div class="mi-card__content">
                                <span class="mi-card__label">Contratación</span>
                                <strong class="mi-card__value"><?= fechaEnEspanol($h['fecha_contratacion'] ?? '') ?></strong>
                            </div>
                        </div>
                        <div class="mi-card">
                            <div class="mi-card__icon"><i class="bi bi-hash"></i></div>
                            <div class="mi-card__content">
                                <span class="mi-card__label">Orden</span>
                                <strong class="mi-card__value">#<?= $id_orden ?></strong>
                            </div>
                        </div>
                        <?php if ($tipo !== ''): ?>
                        <div class="mi-card">
                            <div class="mi-card__icon"><i class="bi bi-layers-fill"></i></div>
                            <div class="mi-card__content">
                                <span class="mi-card__label">Tipo</span>
                                <strong class="mi-card__value"><?= htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8') ?></strong>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($dns_items)): ?>
                    <div class="mi-dns">
                        <div class="mi-dns__head"><i class="bi bi-hdd-network"></i> Servidores DNS del plan</div>
                        <div class="mi-dns__tags">
                            <?php foreach ($dns_items as $ns): ?>
                            <span class="mi-dns__tag"><i class="bi bi-server"></i> <?= htmlspecialchars($ns, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endforeach; ?>
                        </div>
                        <p class="ho-dns-hint">Para cambiar DNS del dominio usa <a href="dominios.php">Mis dominios</a>.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </details>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#000147">
    <?php require_once __DIR__ . '/includes/cliente_head_meta.php'; $clientePageTitle = 'Mi hosting'; ?>
    <title><?= htmlspecialchars(cliente_document_title('Mi hosting'), ENT_QUOTES, 'UTF-8') ?></title>
    <?= cliente_favicon_markup() ?>
    <link rel="stylesheet" href="assets/css/cliente-dias-badge.css">
    <link rel="stylesheet" href="assets/css/cliente-mas-info.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/cliente-mas-info.css') ?>">
    <link rel="stylesheet" href="assets/css/cliente-servicios.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/cliente-servicios.css') ?>">
    <link rel="stylesheet" href="assets/css/cliente-hosting.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/cliente-hosting.css') ?>">
</head>
<body>
    <div id="app">
        <?php include 'menu.php'; ?>

        <div class="main-content container-fluid ho-page">
            <div class="header-section">
                <div class="header-content">
                    <div class="header-icon-container">
                        <i class="bi bi-hdd-rack-fill header-icon"></i>
                    </div>
                    <div class="header-text">
                        <h1 class="header-title">Mi hosting</h1>
                        <p class="header-subtitle">Planes, vencimiento y acceso a cPanel/WHM · <?= (int) $total_hosting ?> plan<?= $total_hosting === 1 ? '' : 'es' ?></p>
                    </div>
                </div>
            </div>

            <div class="ho-diff">
                <div class="ho-diff__item">
                    <strong>Esta página</strong>
                    <span>Plan de hosting: vencimiento, costo y entrar a cPanel/WHM</span>
                </div>
                <div class="ho-diff__item">
                    <strong>Mis sitios</strong>
                    <span>WordPress, cPanel y visitar cada sitio web</span>
                </div>
                <div class="ho-diff__item">
                    <strong>Mis dominios</strong>
                    <span>DNS del dominio y código de transferencia (EPP)</span>
                </div>
            </div>

            <?php if ($cnt_pendiente > 0 && $tab_activa === 'activos'): ?>
            <a href="hosting.php?tab=pendiente_pago" class="ho-pay-strip">
                <i class="bi bi-exclamation-circle-fill"></i>
                <span><strong><?= (int) $cnt_pendiente ?></strong> plan<?= $cnt_pendiente === 1 ? '' : 'es' ?> con pago pendiente</span>
                <span class="ho-pay-strip__cta">Ver pendientes <i class="bi bi-arrow-right"></i></span>
            </a>
            <?php elseif ($cnt_pendiente > 0 && $tab_activa === 'pendiente_pago'): ?>
            <a href="pagos.php" class="ho-pay-strip">
                <i class="bi bi-credit-card-fill"></i>
                <span>Renueva desde Pagos para reactivar el servicio</span>
                <span class="ho-pay-strip__cta">Ir a pagos <i class="bi bi-arrow-right"></i></span>
            </a>
            <?php endif; ?>

            <div class="ho-toolbar">
                <div class="ho-tabs" role="tablist">
                    <a href="hosting.php?tab=activos" class="ho-tab <?= $tab_activa === 'activos' ? 'active' : '' ?>">
                        <i class="bi bi-check-circle-fill"></i>
                        <span class="ho-tab__label-full">Activos</span>
                        <span class="ho-tab__label-short">Activos</span>
                        <span class="ho-tab-count"><?= (int) $cnt_activos ?></span>
                    </a>
                    <a href="hosting.php?tab=pendiente_pago" class="ho-tab <?= $tab_activa === 'pendiente_pago' ? 'active' : '' ?>">
                        <i class="bi bi-credit-card-fill"></i>
                        <span class="ho-tab__label-full">Pendiente de pago</span>
                        <span class="ho-tab__label-short">Pendientes</span>
                        <span class="ho-tab-count"><?= (int) $cnt_pendiente ?></span>
                    </a>
                </div>
                <label class="ho-search">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input type="search" id="hoSearch" placeholder="Buscar plan o dominio…" autocomplete="off" aria-label="Buscar plan o dominio">
                </label>
            </div>

            <div class="ho-panel <?= $tab_activa === 'activos' ? 'active' : '' ?>" id="panel-activos">
                <div class="ho-hint">
                    <i class="bi bi-info-circle-fill"></i>
                    <span>Planes <strong>al corriente</strong>. Entra a cPanel/WHM o visita tu sitio. Si falta el acceso al panel, abre un ticket.</span>
                </div>
                <?php if ($cnt_activos === 0): ?>
                <div class="ho-empty">
                    <div class="ho-empty-icon"><i class="bi bi-hdd-rack"></i></div>
                    <h5>No tienes planes activos</h5>
                    <p class="text-muted">Cuando tu hosting esté activo, aparecerá aquí.</p>
                    <div class="ho-empty-actions">
                        <?php if ($cnt_pendiente > 0): ?>
                        <a href="hosting.php?tab=pendiente_pago" class="ho-empty-btn">Ver pendientes</a>
                        <a href="pagos.php" class="ho-empty-btn ho-empty-btn--ghost">Ir a pagos</a>
                        <?php else: ?>
                        <a href="tickets.php" class="ho-empty-btn">Abrir ticket</a>
                        <a href="mis-sitios.php" class="ho-empty-btn ho-empty-btn--ghost">Ver mis sitios</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="ho-list" data-ho-list>
                    <?php foreach ($hostings_activos as $h):
                        echo render_hosting_card($conn, $h, false);
                    endforeach; ?>
                </div>
                <p class="ho-no-results" hidden>No hay planes que coincidan con tu búsqueda.</p>
                <?php endif; ?>
            </div>

            <div class="ho-panel <?= $tab_activa === 'pendiente_pago' ? 'active' : '' ?>" id="panel-pendiente">
                <div class="ho-hint ho-hint--warn">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>Estos planes necesitan <strong>pago</strong>. Renueva en Pagos para reactivar el servicio.</span>
                </div>
                <?php if ($cnt_pendiente === 0): ?>
                <div class="ho-empty">
                    <div class="ho-empty-icon ho-empty-icon--success"><i class="bi bi-check2-all"></i></div>
                    <h5>Todos tus planes están al día</h5>
                    <p class="text-muted mb-0">No hay hosting pendiente de pago.</p>
                </div>
                <?php else: ?>
                <div class="ho-list" data-ho-list>
                    <?php foreach ($hostings_pendiente as $h):
                        echo render_hosting_card($conn, $h, true);
                    endforeach; ?>
                </div>
                <p class="ho-no-results" hidden>No hay planes que coincidan con tu búsqueda.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
    function loginHostingPanel(id) {
        const card = document.querySelector('.ho-card[data-hosting-id="' + id + '"]');
        if (!card) { Swal.fire('Error', 'No se encontró la configuración del hosting', 'error'); return; }
        const panelUrl = card.dataset.panelUrl;
        const panelUser = card.dataset.panelUser;
        const panelPass = card.dataset.panelPass;
        if (!panelUrl || !panelUser || !panelPass) {
            Swal.fire({
                title: 'Acceso no configurado',
                html: 'Faltan credenciales del panel.<br><a href="tickets.php">Abrir ticket de soporte</a>',
                icon: 'warning',
                confirmButtonColor: '#000147'
            });
            return;
        }

        Swal.fire({ title: 'Abriendo panel…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = panelUrl.replace(/\/+$/, '') + '/login';
        form.target = '_blank';
        form.style.display = 'none';

        [{name:'user',value:panelUser},{name:'pass',value:panelPass},{name:'login',value:'1'}].forEach(function(f) {
            const i = document.createElement('input');
            i.type = 'hidden';
            i.name = f.name;
            i.value = f.value;
            form.appendChild(i);
        });

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
        setTimeout(function () { Swal.close(); }, 800);
    }

    (function initHoSearch() {
        const input = document.getElementById('hoSearch');
        if (!input) return;
        const activePanel = document.querySelector('.ho-panel.active');
        const list = activePanel ? activePanel.querySelector('[data-ho-list]') : null;
        const emptyMsg = activePanel ? activePanel.querySelector('.ho-no-results') : null;
        if (!list) {
            input.disabled = true;
            input.placeholder = 'Sin planes para buscar';
            return;
        }
        input.addEventListener('input', function () {
            const q = (input.value || '').trim().toLowerCase();
            let visible = 0;
            list.querySelectorAll('.ho-card').forEach(function (card) {
                const hay = (card.getAttribute('data-hosting-search') || '').indexOf(q) !== -1;
                card.hidden = q !== '' && !hay;
                if (!card.hidden) visible++;
            });
            if (emptyMsg) emptyMsg.hidden = visible > 0 || q === '';
        });
    })();

    document.querySelector('.burger-btn')?.addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('sidebar')?.classList.toggle('active');
        document.getElementById('main')?.classList.toggle('active');
    });
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>
