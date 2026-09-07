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
$tab_activa = ($_GET['tab'] ?? 'registrados') === 'no_registrados' ? 'no_registrados' : 'registrados';

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

function dominio_es_gestionable(array $d): bool {
    return (int) ($d['estado_dominio'] ?? 0) === 1 && (int) ($d['registrado'] ?? 0) === 1;
}

function dominio_tipo_etiqueta(array $d): string {
    $tipo = strtolower(trim((string) ($d['dominio_tipo'] ?? '')));
    if ($tipo === 'nuevo' || $tipo === 'new') {
        return 'Registro nuevo';
    }
    if ($tipo === 'existente' || $tipo === 'existing') {
        return 'Dominio existente';
    }
    if ($tipo !== '') {
        return ucfirst($tipo);
    }
    return ((int) ($d['registrado'] ?? 0) === 1) ? 'Registro nuevo' : 'Dominio existente';
}

$dominios_registrados = [];
$dominios_no_registrados = [];

$query = mysqli_query($conn, "SELECT * FROM dominios WHERE cliente_id = $usrid AND eliminado = 0 ORDER BY fecha_pago DESC");
if ($query) {
    while ($row = mysqli_fetch_assoc($query)) {
        if ((int) ($row['registrado'] ?? 0) === 1) {
            $dominios_registrados[] = $row;
        } else {
            $dominios_no_registrados[] = $row;
        }
    }
}

$cnt_reg = count($dominios_registrados);
$cnt_noreg = count($dominios_no_registrados);
$total_dominios = $cnt_reg + $cnt_noreg;
$cnt_pendientes = 0;
foreach ($dominios_registrados as $d) {
    if ((int) ($d['estado_dominio'] ?? 0) !== 1) {
        $cnt_pendientes++;
    }
}
$dominios_modals_html = '';

/**
 * @return array{0: string, 1: string} card HTML, modal HTML
 */
function render_dominio_card_y_modales(array $d, bool $es_conlineweb): array
{
    $id = (int) ($d['id_dominio'] ?? 0);
    $url = (string) ($d['url_dominio'] ?? '');
    $dom_esc = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    $dom_search = htmlspecialchars(strtolower($url), ENT_QUOTES, 'UTF-8');
    $activo = (int) ($d['estado_dominio'] ?? 0) === 1;
    $gestionable = dominio_es_gestionable($d);
    $dias = cliente_dias_vencimiento($d['fecha_pago'] ?? '');
    $moneda = ((int) ($d['id_forma_pago'] ?? 0) === 1) ? 'MXN' : 'USD';
    $costo = htmlspecialchars((string) ($d['costo_dominio'] ?? ''), ENT_QUOTES, 'UTF-8');
    $card_class = 'card-domain svc-card--compact' . ($es_conlineweb && $activo ? '' : ' card-domain--muted');
    $inicial = strtoupper(substr(preg_replace('/^www\./i', '', $url) ?: 'D', 0, 1));

    ob_start();
    ?>
    <div class="<?= $card_class ?>" data-domain-search="<?= $dom_search ?>">
        <div class="gd-domain-header gd-domain-header--side">
            <div class="gd-domain-main">
                <div class="gd-domain-name">
                    <div class="gd-domain-icon<?= $es_conlineweb && $activo ? '' : ' gd-domain-icon--muted' ?>" aria-hidden="true"><?= htmlspecialchars($inicial, ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="gd-domain-text">
                        <?php if ($es_conlineweb): ?>
                            <a href="https://<?= $dom_esc ?>" target="_blank" rel="noopener" class="domain-title"><?= $dom_esc ?></a>
                        <?php else: ?>
                            <span class="domain-title domain-title--muted"><?= $dom_esc ?></span>
                        <?php endif; ?>
                        <div class="gd-badges">
                            <?php if ($es_conlineweb): ?>
                                <?php if ($activo): ?>
                                    <span class="domain-status status-active"><i class="bi bi-circle-fill" style="font-size:.5rem;"></i> Activo</span>
                                <?php else: ?>
                                    <a href="pagos.php" class="domain-status status-pending-payment status-pending-payment--link" title="Ir a pagos">
                                        <i class="bi bi-credit-card-fill"></i> Pendiente · Ir a pagos
                                    </a>
                                <?php endif; ?>
                                <span class="domain-status status-registered"><i class="bi bi-patch-check-fill"></i> ConlineWeb</span>
                            <?php else: ?>
                                <span class="domain-status status-other-provider"><i class="bi bi-arrow-left-right"></i> Otro proveedor</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($es_conlineweb && $gestionable): ?>
                            <p class="gd-can-do">Puedes cambiar DNS o pedir el código para transferirlo</p>
                        <?php elseif ($es_conlineweb && !$activo): ?>
                            <p class="gd-can-do gd-can-do--warn">Para gestionar DNS o transferencia, primero regulariza el pago</p>
                        <?php elseif (!$es_conlineweb): ?>
                            <p class="gd-can-do">Solo consulta · DNS y transferencia se hacen con tu otro proveedor</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php if ($es_conlineweb): ?>
            <div class="svc-meta svc-meta--side" title="Vencimiento y renovación">
                <?= cliente_render_dias_chip($dias) ?>
                <span class="svc-chip"><i class="bi bi-calendar-event"></i> <?= cliente_fecha_corta($d['fecha_pago'] ?? '') ?></span>
                <span class="svc-chip"><i class="bi bi-cash"></i> $<?= $costo ?> <?= $moneda ?>/año</span>
            </div>
            <?php endif; ?>
        </div>

        <div class="svc-actions-row<?= (!$es_conlineweb || !$gestionable) ? ' svc-actions-row--solo' : '' ?>">
            <button type="button" class="svc-btn svc-btn--info"
                data-bs-toggle="modal" data-bs-target="#infoModal_<?= $id ?>">
                <i class="bi bi-info-circle-fill"></i>
                <span>Ver detalle</span>
            </button>
            <?php if ($es_conlineweb && $gestionable): ?>
            <button type="button" class="svc-btn svc-btn--manage svc-btn--primary-action"
                data-bs-toggle="modal" data-bs-target="#manageModal_<?= $id ?>">
                <i class="bi bi-hdd-network"></i>
                <span>DNS y transferencia</span>
            </button>
            <?php elseif ($es_conlineweb && !$activo): ?>
            <a href="pagos.php" class="svc-btn svc-btn--pay">
                <i class="bi bi-credit-card-fill"></i>
                <span>Ir a pagos</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php
    $card_html = (string) ob_get_clean();

    ob_start();
    if ($es_conlineweb):
    ?>
    <div class="modal fade gd-modal" id="infoModal_<?= $id ?>" tabindex="-1" aria-labelledby="infoModalLabel_<?= $id ?>" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="infoModalLabel_<?= $id ?>">
                            <i class="bi bi-info-circle-fill"></i> Detalle del dominio
                        </h5>
                        <p class="gd-modal-sub"><?= $dom_esc ?></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="gd-info-list">
                        <div class="gd-info-row">
                            <span class="gd-info-row__label"><i class="bi bi-calendar-event"></i> Vence</span>
                            <strong class="gd-info-row__value"><?= fechaEnEspanol($d['fecha_pago'] ?? '') ?></strong>
                        </div>
                        <div class="gd-info-row">
                            <span class="gd-info-row__label"><i class="bi bi-cash"></i> Renovación anual</span>
                            <strong class="gd-info-row__value">$<?= $costo ?> <?= $moneda ?></strong>
                        </div>
                        <div class="gd-info-row">
                            <span class="gd-info-row__label"><i class="bi bi-calendar-plus"></i> Contratación</span>
                            <strong class="gd-info-row__value"><?= fechaEnEspanol($d['fecha_contratacion'] ?? '') ?></strong>
                        </div>
                        <div class="gd-info-row">
                            <span class="gd-info-row__label"><i class="bi bi-hash"></i> Referencia</span>
                            <strong class="gd-info-row__value">#<?= $id ?></strong>
                        </div>
                        <div class="gd-info-row">
                            <span class="gd-info-row__label"><i class="bi bi-bookmark-check-fill"></i> Tipo</span>
                            <strong class="gd-info-row__value"><?= htmlspecialchars(dominio_tipo_etiqueta($d), ENT_QUOTES, 'UTF-8') ?></strong>
                        </div>
                        <div class="gd-info-row">
                            <span class="gd-info-row__label"><i class="bi bi-shield-check"></i> Estado</span>
                            <strong class="gd-info-row__value"><?= $activo ? 'Activo' : 'Pendiente de pago' ?> · ConlineWeb</strong>
                        </div>
                    </div>
                    <div class="gd-dns-block gd-dns-block--modal">
                        <h6 class="gd-dns-block-title"><i class="bi bi-hdd-network"></i> Servidores DNS actuales</h6>
                        <div class="gd-dns-tags">
                            <?php
                            $hay_ns = false;
                            for ($i = 1; $i <= 6; $i++) {
                                if (!empty($d['ns' . $i])) {
                                    $hay_ns = true;
                                    echo '<span class="gd-dns-tag"><i class="bi bi-server"></i> ' . htmlspecialchars((string) $d['ns' . $i], ENT_QUOTES, 'UTF-8') . '</span>';
                                }
                            }
                            if (!$hay_ns) {
                                echo '<span class="gd-dns-empty">Aún no hay servidores DNS guardados</span>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="gd-modal-close" data-bs-dismiss="modal">Cerrar</button>
                    <?php if ($gestionable): ?>
                    <button type="button" class="svc-btn svc-btn--manage svc-btn--modal"
                        onclick="switchToManageModal(<?= $id ?>)">
                        <i class="bi bi-hdd-network"></i> DNS y transferencia
                    </button>
                    <?php elseif (!$activo): ?>
                    <a href="pagos.php" class="svc-btn svc-btn--pay svc-btn--modal">Ir a pagos</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($gestionable): ?>
    <div class="modal fade gd-modal" id="manageModal_<?= $id ?>" tabindex="-1" aria-labelledby="manageModalLabel_<?= $id ?>" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="manageModalLabel_<?= $id ?>">
                            <i class="bi bi-hdd-network"></i> DNS y transferencia
                        </h5>
                        <p class="gd-modal-sub"><?= $dom_esc ?></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="gd-manage-section">
                        <h4><i class="bi bi-grid-3x3-gap-fill"></i> 1. Cambiar servidores DNS</h4>
                        <p class="gd-section-desc">Indica a dónde apunta tu dominio. Los cambios pueden tardar hasta 72 horas en verse en todo internet.</p>
                        <form method="post" action="actulizar_dns.php">
                            <input type="hidden" name="id" value="<?= $id ?>">
                            <div class="row g-3">
                                <?php for ($i = 1; $i <= 6; $i++): ?>
                                <div class="col-md-6">
                                    <label class="gd-dns-label">Servidor DNS <?= $i ?><?= $i === 1 ? ' *' : '' ?></label>
                                    <input type="text" name="ns<?= $i ?>" class="gd-dns-input"
                                        placeholder="ns<?= $i ?>.ejemplo.com"
                                        value="<?= htmlspecialchars((string) ($d['ns' . $i] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        <?= $i === 1 ? 'required' : '' ?>>
                                </div>
                                <?php endfor; ?>
                            </div>
                            <button type="submit" class="gd-btn-dns"><i class="bi bi-save"></i> Guardar DNS</button>
                        </form>
                    </div>

                    <div class="gd-manage-section">
                        <h4><i class="bi bi-key-fill"></i> 2. Código para transferir (EPP)</h4>
                        <p class="gd-section-desc">
                            Solo si quieres mover este dominio a otro registrador. Te confirmamos por correo en 1–3 días hábiles.
                        </p>
                        <button type="button" class="gd-btn-transfer" data-transfer-btn="<?= $id ?>"
                            onclick="solicitarCodigo(<?= $id ?>, '<?= addslashes($url) ?>')">
                            <i class="bi bi-key-fill"></i> Solicitar código EPP
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="gd-modal-close" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="modal fade gd-modal" id="infoModal_<?= $id ?>" tabindex="-1" aria-labelledby="infoModalLabel_<?= $id ?>" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="infoModalLabel_<?= $id ?>">
                            <i class="bi bi-info-circle-fill"></i> Detalle del dominio
                        </h5>
                        <p class="gd-modal-sub"><?= $dom_esc ?></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="gd-info-list">
                        <div class="gd-info-row">
                            <span class="gd-info-row__label"><i class="bi bi-hash"></i> Referencia</span>
                            <strong class="gd-info-row__value">#<?= $id ?></strong>
                        </div>
                        <div class="gd-info-row">
                            <span class="gd-info-row__label"><i class="bi bi-bookmark-check-fill"></i> Tipo</span>
                            <strong class="gd-info-row__value"><?= htmlspecialchars(dominio_tipo_etiqueta($d), ENT_QUOTES, 'UTF-8') ?></strong>
                        </div>
                        <div class="gd-info-row">
                            <span class="gd-info-row__label"><i class="bi bi-shield"></i> Proveedor</span>
                            <strong class="gd-info-row__value">Otro proveedor (no ConlineWeb)</strong>
                        </div>
                    </div>
                    <p class="mi-details__note" style="margin-top:14px;">
                        Aquí no se pueden cambiar DNS ni pedir el código EPP. Eso lo hace el proveedor donde está registrado el dominio.
                        Si lo transfieres a ConlineWeb, aparecerá en la pestaña <strong>Con ConlineWeb</strong>.
                    </p>
                    <p class="gd-help-link">
                        <a href="tickets.php">¿Quieres transferirlo a ConlineWeb? Abre un ticket</a>
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="gd-modal-close" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    <?php
    endif;
    $modals_html = (string) ob_get_clean();

    return [$card_html, $modals_html];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#000147">
    <?php require_once __DIR__ . '/includes/cliente_head_meta.php'; $clientePageTitle = 'Mis dominios'; ?>
    <title><?= htmlspecialchars(cliente_document_title('Mis dominios'), ENT_QUOTES, 'UTF-8') ?></title>
    <?= cliente_favicon_markup() ?>
    <link rel="stylesheet" href="assets/css/cliente-dias-badge.css">
    <link rel="stylesheet" href="assets/css/cliente-mas-info.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/cliente-mas-info.css') ?>">
    <link rel="stylesheet" href="assets/css/cliente-servicios.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/cliente-servicios.css') ?>">
    <link rel="stylesheet" href="assets/css/cliente-dominios.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/cliente-dominios.css') ?>">
</head>
<body>
    <div id="app">
        <?php include 'menu.php'; ?>

        <div class="main-content container-fluid gd-page">
            <div class="header-section">
                <div class="header-content">
                    <div class="header-icon-container">
                        <i class="bi bi-globe2 header-icon"></i>
                    </div>
                    <div class="header-text">
                        <h1 class="header-title">Mis dominios</h1>
                        <p class="header-subtitle">Vencimiento, DNS y transferencia · <?= (int) $total_dominios ?> dominio<?= $total_dominios === 1 ? '' : 's' ?></p>
                    </div>
                </div>
            </div>

            <div class="gd-diff">
                <div class="gd-diff__item">
                    <strong>Esta página</strong>
                    <span>Dominio: cuándo vence, DNS y código para transferir</span>
                </div>
                <div class="gd-diff__item">
                    <strong>Mis sitios</strong>
                    <span>Entrar a WordPress, cPanel o visitar la web</span>
                </div>
            </div>

            <?php if ($cnt_pendientes > 0 && $tab_activa === 'registrados'): ?>
            <a href="pagos.php" class="gd-pay-strip">
                <i class="bi bi-exclamation-circle-fill"></i>
                <span><strong><?= (int) $cnt_pendientes ?></strong> dominio<?= $cnt_pendientes === 1 ? '' : 's' ?> con pago pendiente</span>
                <span class="gd-pay-strip__cta">Ir a pagos <i class="bi bi-arrow-right"></i></span>
            </a>
            <?php endif; ?>

            <div class="gd-toolbar">
                <div class="gd-tabs" role="tablist">
                    <a href="dominios.php?tab=registrados" class="gd-tab <?= $tab_activa === 'registrados' ? 'active' : '' ?>">
                        <i class="bi bi-check-circle-fill"></i>
                        <span class="gd-tab__label-full">Con ConlineWeb</span>
                        <span class="gd-tab__label-short">ConlineWeb</span>
                        <span class="gd-tab-count"><?= (int) $cnt_reg ?></span>
                    </a>
                    <a href="dominios.php?tab=no_registrados" class="gd-tab <?= $tab_activa === 'no_registrados' ? 'active' : '' ?>">
                        <i class="bi bi-arrow-left-right"></i>
                        <span class="gd-tab__label-full">Otro proveedor</span>
                        <span class="gd-tab__label-short">Otro</span>
                        <span class="gd-tab-count"><?= (int) $cnt_noreg ?></span>
                    </a>
                </div>
                <label class="gd-search">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input type="search" id="gdSearch" placeholder="Buscar dominio…" autocomplete="off" aria-label="Buscar dominio">
                </label>
            </div>

            <div class="gd-panel <?= $tab_activa === 'registrados' ? 'active' : '' ?>" id="panel-registrados">
                <div class="gd-hint">
                    <i class="bi bi-info-circle-fill"></i>
                    <span>Dominios <strong>registrados con nosotros</strong>. Si está <strong>Activo</strong>, puedes cambiar DNS o pedir el código EPP. Si está <strong>Pendiente</strong>, ve a Pagos.</span>
                </div>

                <?php if ($cnt_reg === 0): ?>
                <div class="gd-empty">
                    <div class="gd-empty-icon"><i class="bi bi-globe"></i></div>
                    <h5>No tienes dominios con ConlineWeb</h5>
                    <p class="text-muted">Cuando registremos o transfiramos un dominio a tu cuenta, aparecerá aquí.</p>
                    <div class="gd-empty-actions">
                        <a href="tickets.php" class="gd-empty-btn">Abrir ticket</a>
                        <a href="mis-sitios.php" class="gd-empty-btn gd-empty-btn--ghost">Ver mis sitios</a>
                    </div>
                </div>
                <?php else: ?>
                <div class="gd-list" data-gd-list>
                    <?php foreach ($dominios_registrados as $d):
                        [$card, $modals] = render_dominio_card_y_modales($d, true);
                        echo $card;
                        $dominios_modals_html .= $modals;
                    endforeach; ?>
                </div>
                <p class="gd-no-results" hidden>No hay dominios que coincidan con tu búsqueda.</p>
                <?php endif; ?>
            </div>

            <div class="gd-panel <?= $tab_activa === 'no_registrados' ? 'active' : '' ?>" id="panel-no-registrados">
                <div class="gd-hint gd-hint--warn">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>Dominios en <strong>otro proveedor</strong>. Aquí solo los ves; DNS y transferencia no se hacen desde ConlineWeb.</span>
                </div>

                <?php if ($cnt_noreg === 0): ?>
                <div class="gd-empty">
                    <div class="gd-empty-icon gd-empty-icon--success"><i class="bi bi-check2-all"></i></div>
                    <h5>Todos tus dominios están con ConlineWeb</h5>
                    <p class="text-muted mb-0">No hay dominios registrados en otro proveedor.</p>
                </div>
                <?php else: ?>
                <div class="gd-list" data-gd-list>
                    <?php foreach ($dominios_no_registrados as $d):
                        [$card, $modals] = render_dominio_card_y_modales($d, false);
                        echo $card;
                        $dominios_modals_html .= $modals;
                    endforeach; ?>
                </div>
                <p class="gd-no-results" hidden>No hay dominios que coincidan con tu búsqueda.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="dominios-modals-root"><?= $dominios_modals_html ?></div>

    <script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php include __DIR__ . '/includes/cliente_transferencia_js.php'; ?>
    <script>
        function cleanupModalArtifacts() {
            document.querySelectorAll('.modal-backdrop').forEach(function (el) { el.remove(); });
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('overflow');
            document.body.style.removeProperty('padding-right');
        }

        function openDomainModal(id, type) {
            var el = document.getElementById((type === 'manage' ? 'manageModal_' : 'infoModal_') + id);
            if (!el || typeof bootstrap === 'undefined') return;
            cleanupModalArtifacts();
            bootstrap.Modal.getOrCreateInstance(el, {
                backdrop: true,
                keyboard: true,
                focus: true
            }).show();
        }

        function openManageModal(id) {
            openDomainModal(id, 'manage');
        }

        function switchToManageModal(id) {
            var infoEl = document.getElementById('infoModal_' + id);
            if (infoEl && typeof bootstrap !== 'undefined') {
                var info = bootstrap.Modal.getInstance(infoEl);
                if (info) {
                    infoEl.addEventListener('hidden.bs.modal', function handler() {
                        infoEl.removeEventListener('hidden.bs.modal', handler);
                        cleanupModalArtifacts();
                        openManageModal(id);
                    });
                    info.hide();
                    return;
                }
            }
            openManageModal(id);
        }

        document.addEventListener('hidden.bs.modal', function () {
            if (!document.querySelector('.modal.show')) {
                cleanupModalArtifacts();
            }
        });

        (function initGdSearch() {
            var input = document.getElementById('gdSearch');
            if (!input) return;
            var activePanel = document.querySelector('.gd-panel.active');
            var list = activePanel ? activePanel.querySelector('[data-gd-list]') : null;
            var emptyMsg = activePanel ? activePanel.querySelector('.gd-no-results') : null;
            if (!list) {
                input.disabled = true;
                input.placeholder = 'Sin dominios para buscar';
                return;
            }
            input.addEventListener('input', function () {
                var q = (input.value || '').trim().toLowerCase();
                var visible = 0;
                list.querySelectorAll('.card-domain').forEach(function (card) {
                    var hay = (card.getAttribute('data-domain-search') || '').indexOf(q) !== -1;
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

        <?php if (!empty($_GET['gestionar'])): ?>
        document.addEventListener('DOMContentLoaded', function () {
            openManageModal(<?= (int) $_GET['gestionar'] ?>);
        });
        <?php endif; ?>

        <?php if (!empty($_GET['dns_ok'])): ?>
        Swal.fire({
            title: 'DNS guardados',
            icon: 'success',
            text: 'Los cambios pueden tardar hasta 72 horas en propagarse. Tu sitio estará disponible cuando termine ese proceso.',
            confirmButtonText: 'Entendido',
            confirmButtonColor: '#10b981',
            allowOutsideClick: false
        });
        <?php elseif (!empty($_GET['dns_error'])): ?>
        Swal.fire({
            title: 'No se pudieron guardar',
            icon: 'error',
            text: 'Hubo un error al actualizar los DNS. Intenta de nuevo o abre un ticket.',
            confirmButtonText: 'Aceptar',
            confirmButtonColor: '#dc2626',
            allowOutsideClick: false
        });
        <?php elseif (!empty($_GET['dns_warn'])): ?>
        Swal.fire({
            title: 'Dominio no disponible',
            icon: 'warning',
            text: 'Solo puedes cambiar DNS en dominios activos gestionados por ConlineWeb.',
            confirmButtonText: 'Aceptar',
            confirmButtonColor: '#000147',
            allowOutsideClick: false
        });
        <?php elseif (!empty($_GET['dns_incomplete'])): ?>
        Swal.fire({
            title: 'Faltan datos',
            icon: 'warning',
            text: 'Completa al menos el servidor DNS 1 para guardar.',
            confirmButtonText: 'Aceptar',
            confirmButtonColor: '#f59e0b',
            allowOutsideClick: false
        });
        <?php endif; ?>
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>
