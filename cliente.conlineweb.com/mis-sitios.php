<?php
require_once __DIR__ . '/includes/cliente_session.php';
cliente_start_session();
if (!cliente_is_logged_in()) {
    header('Location: ingreso.php');
    exit;
}

include 'conn.php';
$usrid = (int) $_SESSION['uid'];
$tab_raw = $_GET['tab'] ?? 'activos';
$tab_activa = ($tab_raw === 'no_registrados' || $tab_raw === 'otro_proveedor') ? 'otro_proveedor' : 'activos';

$sitios_conlineweb = [];
$sitios_otro_proveedor = [];

$res_dominios = mysqli_query($conn, "SELECT id_dominio, url_dominio, usuario, contrasena, url_cpanel, estado_dominio, registrado FROM dominios WHERE cliente_id = $usrid AND eliminado = 0 ORDER BY id_dominio DESC");
if ($res_dominios) {
    while ($row = mysqli_fetch_assoc($res_dominios)) {
        if (empty($row['url_dominio'])) {
            continue;
        }
        if ((int) ($row['registrado'] ?? 0) === 1) {
            $sitios_conlineweb[] = $row;
        } else {
            $sitios_otro_proveedor[] = $row;
        }
    }
}

$cnt_conlineweb = count($sitios_conlineweb);
$cnt_otro_proveedor = count($sitios_otro_proveedor);
$total_sitios = $cnt_conlineweb + $cnt_otro_proveedor;
$cnt_pendientes_pago = 0;
foreach ($sitios_conlineweb as $s) {
    if ((int) ($s['estado_dominio'] ?? 0) !== 1) {
        $cnt_pendientes_pago++;
    }
}

function render_sitio_card(array $mostrar, bool $muted): string
{
    $id_dominio  = (int) ($mostrar['id_dominio'] ?? 0);
    $url_dominio = (string) ($mostrar['url_dominio'] ?? '');
    $panel_url   = (string) ($mostrar['url_cpanel'] ?? '');
    $usuario     = (string) ($mostrar['usuario'] ?? '');
    $contrasena  = (string) ($mostrar['contrasena'] ?? '');
    $estado      = (int) ($mostrar['estado_dominio'] ?? 0);
    $registrado  = (int) ($mostrar['registrado'] ?? 0) === 1;
    $is_whm      = strpos($panel_url, ':2086') !== false;
    $has_creds   = $panel_url !== '' && $usuario !== '' && $contrasena !== '';
    $dom_esc     = htmlspecialchars($url_dominio, ENT_QUOTES, 'UTF-8');
    $dom_search  = htmlspecialchars(strtolower($url_dominio), ENT_QUOTES, 'UTF-8');
    $card_class  = 'card-domain svc-card--compact' . ($muted ? ' card-domain--muted' : '');
    $icon_class  = 'ms-site-icon' . ($muted ? ' ms-site-icon--muted' : '');
    $inicial     = strtoupper(substr(preg_replace('/^www\./i', '', $url_dominio) ?: 'S', 0, 1));

    ob_start();
    ?>
    <div class="<?php echo $card_class; ?>"
         data-domain-id="<?php echo $id_dominio; ?>"
         data-domain="<?php echo $dom_esc; ?>"
         data-domain-search="<?php echo $dom_search; ?>"
         data-panel-url="<?php echo htmlspecialchars($panel_url, ENT_QUOTES, 'UTF-8'); ?>"
         data-panel-user="<?php echo htmlspecialchars($usuario, ENT_QUOTES, 'UTF-8'); ?>"
         data-panel-pass="<?php echo htmlspecialchars($contrasena, ENT_QUOTES, 'UTF-8'); ?>"
         data-panel-type="<?php echo $is_whm ? 'whm' : 'cpanel'; ?>">

        <div class="ms-site-header">
            <div class="ms-site-name">
                <div class="<?php echo $icon_class; ?>" aria-hidden="true"><?php echo htmlspecialchars($inicial, ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="ms-site-name__text">
                    <a href="https://<?php echo $dom_esc; ?>" target="_blank" rel="noopener" class="domain-title"><?php echo $dom_esc; ?></a>
                    <span class="ms-site-sub">https://<?php echo $dom_esc; ?></span>
                </div>
            </div>
            <div class="ms-badges">
                <?php if ($registrado): ?>
                    <?php if ($estado === 1): ?>
                        <span class="domain-status status-active"><i class="bi bi-lightning-fill"></i> Activo</span>
                    <?php else: ?>
                        <a href="pagos.php" class="domain-status status-pending-payment status-pending-payment--link" title="Revisar pagos">
                            <i class="bi bi-credit-card-fill"></i> Pendiente · Ir a pagos
                        </a>
                    <?php endif; ?>
                    <span class="domain-status status-registered"><i class="bi bi-patch-check-fill"></i> ConlineWeb</span>
                <?php else: ?>
                    <span class="domain-status status-other-provider"><i class="bi bi-arrow-left-right"></i> Otro proveedor</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="ms-actions ms-actions--compact">
            <button type="button" class="ms-action-btn ms-action-btn--visit ms-action-btn--primary" onclick="window.open('https://<?php echo $dom_esc; ?>', '_blank')">
                <i class="bi bi-box-arrow-up-right"></i>
                <span>Visitar sitio</span>
            </button>
            <button type="button" class="ms-action-btn ms-action-btn--wp" onclick="loginWordPress('<?php echo $id_dominio; ?>')" <?php echo $has_creds ? '' : 'disabled aria-disabled="true"'; ?>>
                <i class="bi bi-layout-text-window"></i>
                <span>WordPress</span>
            </button>
            <button type="button" class="ms-action-btn ms-action-btn--panel" onclick="loginPanel('<?php echo $id_dominio; ?>')" <?php echo $has_creds ? '' : 'disabled aria-disabled="true"'; ?>>
                <i class="bi bi-terminal-fill"></i>
                <span><?php echo $is_whm ? 'WHM' : 'cPanel'; ?></span>
            </button>
            <details class="ms-more">
                <summary class="ms-more__summary">Más opciones</summary>
                <div class="ms-more__menu">
                    <button type="button" class="ms-action-btn ms-action-btn--platform" onclick="window.open('https://<?php echo $dom_esc; ?>/plataforma', '_blank')">
                        <i class="bi bi-layers-fill"></i>
                        <span>Plataforma</span>
                    </button>
                </div>
            </details>
        </div>

        <?php if (!$has_creds): ?>
        <p class="ms-creds-note">
            <i class="bi bi-info-circle"></i>
            Acceso WordPress / panel no configurado.
            <a href="tickets.php">Abrir ticket de soporte</a>
        </p>
        <?php endif; ?>
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
    <?php require_once __DIR__ . '/includes/cliente_head_meta.php'; $clientePageTitle = 'Mis sitios web'; ?>
    <title><?= htmlspecialchars(cliente_document_title('Mis sitios web'), ENT_QUOTES, 'UTF-8') ?></title>
    <?= cliente_favicon_markup() ?>
    <link rel="stylesheet" href="assets/css/cliente-mis-sitios.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/cliente-mis-sitios.css') ?>">
</head>
<body>
    <div id="app">
        <?php include 'menu.php'; ?>

        <div class="main-content container-fluid ms-page">
            <div class="header-section">
                <div class="header-content">
                    <div class="header-icon-container">
                        <i class="bi bi-hdd-stack-fill header-icon"></i>
                    </div>
                    <div class="header-text">
                        <h1 class="header-title">Mis Sitios Web</h1>
                        <p class="header-subtitle">Entra a WordPress, cPanel o visita tus sitios en un clic · <?= (int) $total_sitios ?> sitio<?= $total_sitios === 1 ? '' : 's' ?></p>
                    </div>
                </div>
            </div>

            <?php if ($cnt_pendientes_pago > 0 && $tab_activa === 'activos'): ?>
            <a href="pagos.php" class="ms-pay-strip">
                <i class="bi bi-exclamation-circle-fill"></i>
                <span><strong><?= (int) $cnt_pendientes_pago ?></strong> sitio<?= $cnt_pendientes_pago === 1 ? '' : 's' ?> con pago pendiente</span>
                <span class="ms-pay-strip__cta">Ir a pagos <i class="bi bi-arrow-right"></i></span>
            </a>
            <?php endif; ?>

            <div class="ms-toolbar">
                <div class="ms-tabs" role="tablist">
                    <a href="mis-sitios.php?tab=activos" class="ms-tab <?= $tab_activa === 'activos' ? 'active' : '' ?>">
                        <i class="bi bi-check-circle-fill"></i>
                        <span class="ms-tab__label-full">Gestionados por ConlineWeb</span>
                        <span class="ms-tab__label-short">ConlineWeb</span>
                        <span class="ms-tab-count"><?= (int) $cnt_conlineweb ?></span>
                    </a>
                    <a href="mis-sitios.php?tab=otro_proveedor" class="ms-tab <?= $tab_activa === 'otro_proveedor' ? 'active' : '' ?>">
                        <i class="bi bi-arrow-left-right"></i>
                        <span class="ms-tab__label-full">Otro proveedor</span>
                        <span class="ms-tab__label-short">Otro proveedor</span>
                        <span class="ms-tab-count"><?= (int) $cnt_otro_proveedor ?></span>
                    </a>
                </div>
                <label class="ms-search">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input type="search" id="msSearch" placeholder="Buscar dominio…" autocomplete="off" aria-label="Buscar dominio">
                </label>
            </div>

            <div class="ms-panel <?= $tab_activa === 'activos' ? 'active' : '' ?>" id="panel-activos">
                <div class="ms-hint">
                    <i class="bi bi-info-circle-fill"></i>
                    <span>Sitios <strong>gestionados por ConlineWeb</strong>. Si hay adeudo verás <strong>Pendiente</strong> y puedes ir a pagos.</span>
                </div>

                <?php if ($cnt_conlineweb === 0): ?>
                <div class="ms-empty">
                    <div class="ms-empty-icon"><i class="bi bi-hdd-stack"></i></div>
                    <h5>No tienes sitios gestionados por ConlineWeb</h5>
                    <p class="text-muted">Cuando tengamos tu hosting/dominio activo, aparecerá aquí.</p>
                    <div class="ms-empty-actions">
                        <a href="tickets.php" class="ms-empty-btn">Abrir ticket</a>
                        <a href="dominios.php" class="ms-empty-btn ms-empty-btn--ghost">Ver dominios</a>
                    </div>
                </div>
                <?php else: ?>
                <div class="ms-list" data-ms-list>
                    <?php foreach ($sitios_conlineweb as $mostrar):
                        echo render_sitio_card($mostrar, (int) ($mostrar['estado_dominio'] ?? 0) !== 1);
                    endforeach; ?>
                </div>
                <p class="ms-no-results" id="msNoResultsActivos" hidden>No hay dominios que coincidan con tu búsqueda.</p>
                <?php endif; ?>
            </div>

            <div class="ms-panel <?= $tab_activa === 'otro_proveedor' ? 'active' : '' ?>" id="panel-otro-proveedor">
                <div class="ms-hint ms-hint--warn">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>Dominios <strong>registrados con otro proveedor</strong>. El hosting puede estar activo, pero la gestión del dominio no es con ConlineWeb.</span>
                </div>

                <?php if ($cnt_otro_proveedor === 0): ?>
                <div class="ms-empty">
                    <div class="ms-empty-icon ms-empty-icon--success"><i class="bi bi-check2-all"></i></div>
                    <h5>Sin sitios con otro proveedor</h5>
                    <p class="text-muted mb-0">Todos tus sitios están gestionados por ConlineWeb.</p>
                </div>
                <?php else: ?>
                <div class="ms-list" data-ms-list>
                    <?php foreach ($sitios_otro_proveedor as $mostrar):
                        echo render_sitio_card($mostrar, true);
                    endforeach; ?>
                </div>
                <p class="ms-no-results" id="msNoResultsOtro" hidden>No hay dominios que coincidan con tu búsqueda.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
    function loginWordPress(id) {
        const domainCard = document.querySelector('.card-domain[data-domain-id="' + id + '"]');
        if (!domainCard) { Swal.fire('Error', 'No se encontró la configuración del dominio', 'error'); return; }
        const domain = domainCard.dataset.domain;
        const panelUser = domainCard.dataset.panelUser;
        const panelPass = domainCard.dataset.panelPass;
        if (!domain || !panelUser || !panelPass) { Swal.fire('Error', 'Credenciales incompletas', 'error'); return; }
        Swal.fire({ title: 'Conectando a WordPress...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'https://' + domain + '/wp-login.php';
        form.target = '_blank';
        form.style.display = 'none';
        [{name:'log',value:panelUser},{name:'pwd',value:panelPass},{name:'rememberme',value:'forever'},{name:'wp-submit',value:'Acceder'}].forEach(function(f) {
            const input = document.createElement('input');
            input.type = f.name === 'pwd' ? 'password' : 'hidden';
            input.name = f.name; input.value = f.value;
            form.appendChild(input);
        });
        document.body.appendChild(form);
        form.submit();
        setTimeout(function() { Swal.close(); }, 1000);
    }

    function loginPanel(id) {
        const domainCard = document.querySelector('.card-domain[data-domain-id="' + id + '"]');
        if (!domainCard) { Swal.fire('Error', 'No se encontró la configuración del dominio', 'error'); return; }
        const panelUrl = domainCard.dataset.panelUrl;
        const panelUser = domainCard.dataset.panelUser;
        const panelPass = domainCard.dataset.panelPass;
        const panelType = domainCard.dataset.panelType;
        if (!panelUrl || !panelUser || !panelPass) { Swal.fire('Error', 'Credenciales incompletas', 'error'); return; }
        const validPorts = ['2083', '2086', '2087', '2095', '2096'];
        const portMatch = panelUrl.match(/:(\d+)/);
        const port = portMatch ? portMatch[1] : (panelUrl.indexOf('2086') !== -1 ? '2086' : '2083');
        if (!panelUrl.match(/^https?:\/\/.+(:\d+)?\/?$/i) || validPorts.indexOf(port) === -1) {
            Swal.fire({ title: 'URL inválida', html: 'La URL del panel debe usar puertos: <strong>' + validPorts.join(', ') + '</strong>', icon: 'error' });
            return;
        }
        Swal.fire({ title: 'Conectando a ' + panelType.toUpperCase() + '...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = panelUrl.replace(/\/+$/, '') + '/login/';
        form.target = '_blank';
        form.style.display = 'none';
        const fields = [{name:'user',value:panelUser},{name:'pass',value:panelPass},{name:'goto_uri',value:'/'}];
        if (panelType === 'whm') { fields.push({name:'goto',value:'1'},{name:'force_ssl',value:'1'}); }
        else { fields.push({name:'cpsession',value:'automatic'},{name:'login',value:'1'}); }
        fields.forEach(function(f) { const i = document.createElement('input'); i.type='hidden'; i.name=f.name; i.value=f.value; form.appendChild(i); });
        document.body.appendChild(form);
        form.submit();
        setTimeout(function() { Swal.close(); }, 1000);
    }

    (function initMsSearch() {
        const input = document.getElementById('msSearch');
        if (!input) return;
        const activePanel = document.querySelector('.ms-panel.active');
        const list = activePanel ? activePanel.querySelector('[data-ms-list]') : null;
        const emptyMsg = activePanel ? activePanel.querySelector('.ms-no-results') : null;
        if (!list) {
            input.disabled = true;
            input.placeholder = 'Sin sitios para buscar';
            return;
        }
        input.addEventListener('input', function () {
            const q = (input.value || '').trim().toLowerCase();
            let visible = 0;
            list.querySelectorAll('.card-domain').forEach(function (card) {
                const hay = (card.getAttribute('data-domain-search') || '').indexOf(q) !== -1;
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
