<?php
require_once __DIR__ . '/auth_middleware.php';
require_once __DIR__ . '/includes/cw_hub_permissions.php';

$uid = $_SESSION['uid'] ?? null;
$tipo = intval($_SESSION['tipo'] ?? 0);
$hubAnalytics = cw_hub_can('hub.analytics.view');
$hubCrm = cw_hub_can('hub.crm.view');

require_once __DIR__ . '/includes/adm_paths.php';
require_once __DIR__ . '/includes/adm_head_meta.php';
include_once 'conn.php';

$current_page = basename($_SERVER['PHP_SELF']);
$current_path = $_SERVER['PHP_SELF'];

$mod_registros    = in_array($current_page, ['formulario_cliente.php','formulario_dominio.php','formulario_hosting.php','formulario_proyectos.php']);
$mod_soporte      = in_array($current_page, ['tickets_vista.php'])
    || strpos($current_path, '/solicitudes/') !== false;
$mod_consultas    = in_array($current_page, ['clientes.php','dominios.php','hosting.php','pagos.php']);
$mod_chat_inbox   = ($current_page === 'inbox.php' && strpos($current_path, 'leads') !== false && (($_GET['tab'] ?? '') === 'chat'));
$mod_chat_admin   = (strpos($current_path, 'chat/') !== false || strpos($current_path, 'chat\\') !== false)
    && in_array($current_page, ['index.php', 'config.php', 'estadisticas.php'], true);
$mod_comunicacion = in_array($current_page, ['whatsapp_conversacion.php','kanban.php','detalle.php','inbox.php']) || ($current_page === 'index.php' && strpos($current_path, 'leads') !== false) || $mod_chat_inbox || $mod_chat_admin;
$mod_analytics    = ($current_page === 'index.php' && strpos($current_path, 'analytics') !== false) || in_array($current_page, ['pages.php','reportes.php','funnel.php','sessions.php','hub_migrate.php','deploy_check.php','seo_deploy_check.php','seo_mexico_checklist.php','seo_mexico_external.php','seo_mexico_monitor.php','seo_mexico_ai_prompt.php','seo_mexico_plazas.php','seo_mexico_urls.php','seo_sitemap_directory.php','seo_mexico_geo_status.php']);
$mod_website      = in_array($current_page, ['ver_briefings.php', 'leads.php', 'templates.php'])
    || strpos($current_path, '/website/') !== false
    || strpos($current_path, '/emails/') !== false
    || strpos($current_path, '/mailing/') !== false;
$mod_gestion      = (in_array($current_page, ['inbox.php', 'index.php', 'kanban.php', 'detalle.php']) && strpos($current_path, 'leads') !== false && $tipo === 5) || ($mod_chat_inbox && $tipo === 5);
$mod_consultas5   = in_array($current_page, ['clientes.php']) && $tipo === 5;
$mod_hostpro      = in_array($current_page, ['hostpro_planes.php','hostpro_caracteristicas.php']);
$mod_planpro      = in_array($current_page, ['planpro_planes.php']);
$mod_cotizaciones = in_array($current_page, ['index.php']) && strpos($current_path, 'cotizaciones') !== false;
$mod_seguridad    = (strpos($current_path, '/seguridad/') !== false) || in_array($current_page, ['accesos.php'], true);
$canSeguridad     = ($tipo === 1 || $tipo === 2);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <?= adm_robots_meta_markup() ?>
    <meta name="description" content="Panel privado ConlineWeb — acceso restringido">
    <title><?= htmlspecialchars(adm_document_title(adm_resolve_page_title()), ENT_QUOTES, 'UTF-8') ?></title>
    <?= adm_favicon_markup() ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="<?= adm_href('vendor/fontawesome-free/css/all.min.css') ?>" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <link href="<?= adm_href('css/sb-admin-2.min.css') ?>" rel="stylesheet">
    <link href="<?= adm_href('css/admin-platform.css') ?>?v=2" rel="stylesheet">
    <link href="<?= adm_href('assets/css/admin-sidebar.css') ?>?v=2" rel="stylesheet">
    <?php if ($hubCrm || $mod_analytics): ?><link href="<?= adm_href('assets/css/chat-menu-live.css') ?>?v=7" rel="stylesheet"><?php endif; ?>
    <?php if ($mod_chat_admin): ?><link href="<?= adm_href('chat/css/chat-admin.css') ?>?v=4" rel="stylesheet"><?php endif; ?>
    <script>window.ADM_BASE = <?= json_encode(adm_base(), JSON_UNESCAPED_SLASHES) ?>;</script>
</head>

<body id="page-top">
<button class="sidebar-mobile-toggle" onclick="toggleSidebar()" aria-label="Mostrar/ocultar menú">
    <i class="bi bi-list"></i>
</button>
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>
<div id="wrapper">

    <nav class="menu-web-sidebar">

        <div class="sidebar-brand-wrap">
            <span class="sidebar-brand-eyebrow">Panel de gestión</span>
            <a href="<?= adm_href('index.php') ?>">
                <img src="<?= adm_href('images/c-online_completo.png') ?>" alt="ConlineWeb">
            </a>
            <div class="sidebar-user-info">
                <div class="sidebar-footer-avatar">
                    <?php echo strtoupper(substr($_SESSION['nombre'] ?? 'U', 0, 1)); ?>
                </div>
                <div class="sidebar-footer-text">
                    <strong><?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?></strong>
                    <small>En línea</small>
                </div>
            </div>
        </div>

        <div class="sidebar-scroll-area">
            <div class="sidebar-nav-label">Módulos</div>

            <?php if ($tipo === 5): ?>

                <div class="sidebar-module <?php echo $mod_gestion ? 'open' : ''; ?>" data-module="gestion">
                    <div class="sidebar-module-header" onclick="toggleModule('gestion')" role="button" aria-expanded="<?php echo $mod_gestion ? 'true' : 'false'; ?>" aria-controls="module-body-gestion">
                        <span class="mod-icon mod-gestion"><i class="bi bi-people-fill"></i></span>
                        <span class="mod-label">Gestión</span>
                        <?php if ($hubCrm): ?><span class="cw-module-chat-badge" data-chat-module-badge hidden>0</span><?php endif; ?>
                        <i class="bi bi-chevron-down mod-arrow"></i>
                    </div>
                    <div class="sidebar-module-body" id="module-body-gestion">
                        <ul class="sidebar-nav-list">
                            <li class="nav-item <?php echo ($current_page === 'inbox.php' && !$mod_chat_inbox) ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('leads/inbox.php') ?>">
                                    <i class="bi bi-inbox-fill ni"></i>
                                    <span class="nl">Bandeja CRM</span>
                                </a>
                            </li>
                            <?php if ($hubCrm): ?>
                            <li class="nav-item <?php echo $mod_chat_inbox ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('leads/inbox.php?tab=chat') ?>">
                                    <i class="bi bi-chat-left-text-fill ni"></i>
                                    <span class="nl">Chat en vivo</span>
                                    <span class="cw-menu-chat-badge" data-chat-menu-badge hidden>0</span>
                                </a>
                            </li>
                            <?php endif; ?>
                            <li class="nav-item <?php echo ($current_page === 'index.php' && strpos($current_path, 'leads') !== false) ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('leads/index.php') ?>">
                                    <i class="bi bi-table ni"></i>
                                    <span class="nl">Lista de Leads</span>
                                </a>
                            </li>
                            <li class="nav-item <?php echo ($current_page === 'kanban.php') ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('leads/kanban.php') ?>">
                                    <i class="bi bi-kanban ni"></i>
                                    <span class="nl">Pipeline Kanban</span>
                                </a>
                            </li>
                            <?php if ($hubAnalytics): ?>
                            <li class="nav-item <?php echo ($current_page === 'index.php' && strpos($current_path, 'analytics') !== false) ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('analytics/index.php') ?>">
                                    <i class="bi bi-graph-up ni"></i>
                                    <span class="nl">Dashboard Analítico</span>
                                </a>
                            </li>
                            <li class="nav-item <?php echo ($current_page === 'seo_mexico_geo_status.php') ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('analytics/seo_mexico_geo_status.php') ?>">
                                    <i class="bi bi-list-check ni"></i>
                                    <span class="nl">SEO · Estado GEO</span>
                                </a>
                            </li>
                            <li class="nav-item <?php echo ($current_page === 'seo_mexico_checklist.php') ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('analytics/seo_mexico_checklist.php') ?>">
                                    <i class="bi bi-code-slash ni"></i>
                                    <span class="nl">SEO · Auditoría código</span>
                                </a>
                            </li>
                            <li class="nav-item <?php echo ($current_page === 'seo_mexico_external.php') ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('analytics/seo_mexico_external.php') ?>">
                                    <i class="bi bi-box-arrow-up-right ni"></i>
                                    <span class="nl">SEO · Tareas externas</span>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>

                <hr class="sidebar-sep">

                <div class="sidebar-module <?php echo $mod_consultas5 ? 'open' : ''; ?>" data-module="consultas5">
                    <div class="sidebar-module-header" onclick="toggleModule('consultas5')" role="button" aria-expanded="<?php echo $mod_consultas5 ? 'true' : 'false'; ?>" aria-controls="module-body-consultas5">
                        <span class="mod-icon mod-consultas"><i class="bi bi-search"></i></span>
                        <span class="mod-label">Consultas</span>
                        <i class="bi bi-chevron-down mod-arrow"></i>
                    </div>
                    <div class="sidebar-module-body" id="module-body-consultas5">
                        <ul class="sidebar-nav-list">
                            <li class="nav-item <?php echo ($current_page === 'clientes.php') ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('clientes.php') ?>">
                                    <i class="bi bi-building ni"></i>
                                    <span class="nl">Consulta de Clientes</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

            <?php else: ?>

                <div class="sidebar-module <?php echo $mod_registros ? 'open' : ''; ?>" data-module="registros">
                    <div class="sidebar-module-header" onclick="toggleModule('registros')" role="button" aria-expanded="<?php echo $mod_registros ? 'true' : 'false'; ?>" aria-controls="module-body-registros">
                        <span class="mod-icon mod-registros"><i class="bi bi-pencil-square"></i></span>
                        <span class="mod-label">Registros</span>
                        <i class="bi bi-chevron-down mod-arrow"></i>
                    </div>
                    <div class="sidebar-module-body" id="module-body-registros">
                        <ul class="sidebar-nav-list">
                            <li class="nav-item <?php echo ($current_page === 'formulario_cliente.php') ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('formulario_cliente.php') ?>">
                                    <i class="bi bi-person-plus-fill ni"></i>
                                    <span class="nl">Registro de Cliente</span>
                                </a>
                            </li>
                            <li class="nav-item <?php echo ($current_page === 'formulario_dominio.php') ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('formulario_dominio.php') ?>">
                                    <i class="bi bi-globe2 ni"></i>
                                    <span class="nl">Registro de Dominio</span>
                                </a>
                            </li>
                            <li class="nav-item <?php echo ($current_page === 'formulario_hosting.php') ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('formulario_hosting.php') ?>">
                                    <i class="bi bi-cloud-arrow-up-fill ni"></i>
                                    <span class="nl">Registro de Hosting</span>
                                </a>
                            </li>
                            <li class="nav-item <?php echo ($current_page === 'formulario_proyectos.php') ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('formulario_proyectos.php') ?>">
                                    <i class="bi bi-kanban ni"></i>
                                    <span class="nl">Formulario Proyectos</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <hr class="sidebar-sep">

                <div class="sidebar-module <?php echo $mod_consultas ? 'open' : ''; ?>" data-module="consultas">
                    <div class="sidebar-module-header" onclick="toggleModule('consultas')" role="button" aria-expanded="<?php echo $mod_consultas ? 'true' : 'false'; ?>" aria-controls="module-body-consultas">
                        <span class="mod-icon mod-consultas"><i class="bi bi-search"></i></span>
                        <span class="mod-label">Consultas</span>
                        <i class="bi bi-chevron-down mod-arrow"></i>
                    </div>
                    <div class="sidebar-module-body" id="module-body-consultas">
                        <ul class="sidebar-nav-list">
                            <li class="nav-item <?php echo ($current_page === 'clientes.php') ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('clientes.php') ?>">
                                    <i class="bi bi-building ni"></i>
                                    <span class="nl">Consulta de Clientes</span>
                                </a>
                            </li>
                            <li class="nav-item <?php echo ($current_page === 'dominios.php') ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('dominios.php') ?>">
                                    <i class="bi bi-globe ni"></i>
                                    <span class="nl">Consulta de Dominios</span>
                                </a>
                            </li>
                            <li class="nav-item <?php echo ($current_page === 'hosting.php') ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('hosting.php') ?>">
                                    <i class="bi bi-hdd-stack-fill ni"></i>
                                    <span class="nl">Consulta de Hosting</span>
                                </a>
                            </li>
                            <li class="nav-item <?php echo ($current_page === 'pagos.php') ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('pagos.php') ?>">
                                    <i class="bi bi-credit-card-2-front-fill ni"></i>
                                    <span class="nl">Consulta de Pagos</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <hr class="sidebar-sep">

                <div class="sidebar-module <?php echo $mod_comunicacion ? 'open' : ''; ?>" data-module="comunicacion">
                    <div class="sidebar-module-header" onclick="toggleModule('comunicacion')" role="button" aria-expanded="<?php echo $mod_comunicacion ? 'true' : 'false'; ?>" aria-controls="module-body-comunicacion">
                        <span class="mod-icon mod-comunicacion"><i class="bi bi-chat-dots-fill"></i></span>
                        <span class="mod-label">Comunicación</span>
                        <?php if ($hubCrm): ?><span class="cw-module-chat-badge" data-chat-module-badge hidden>0</span><?php endif; ?>
                        <i class="bi bi-chevron-down mod-arrow"></i>
                    </div>
                    <div class="sidebar-module-body" id="module-body-comunicacion">
                        <ul class="sidebar-nav-list">
                            <?php if ($hubCrm): ?>
                            <li class="nav-item <?php echo ($current_page === 'inbox.php' && !$mod_chat_inbox) ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('leads/inbox.php') ?>">
                                    <i class="bi bi-inbox-fill ni"></i>
                                    <span class="nl">Bandeja CRM</span>
                                </a>
                            </li>
                            <li class="nav-item <?php echo $mod_chat_inbox ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('leads/inbox.php?tab=chat') ?>">
                                    <i class="bi bi-chat-left-text-fill ni"></i>
                                    <span class="nl">Chat en vivo</span>
                                    <span class="cw-menu-chat-badge" data-chat-menu-badge hidden>0</span>
                                </a>
                            </li>
                            <li class="nav-item <?php echo ($current_page === 'index.php' && strpos($current_path, 'leads') !== false) ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('leads/index.php') ?>">
                                    <i class="bi bi-table ni"></i>
                                    <span class="nl">Lista de Leads</span>
                                </a>
                            </li>
                            <li class="nav-item <?php echo ($current_page === 'kanban.php') ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('leads/kanban.php') ?>">
                                    <i class="bi bi-kanban ni"></i>
                                    <span class="nl">Pipeline Kanban</span>
                                </a>
                            </li>
                            <li class="nav-item <?php echo $mod_chat_admin ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('chat/index.php') ?>">
                                    <i class="bi bi-robot ni"></i>
                                    <span class="nl">Chatbot / FAQ</span>
                                </a>
                            </li>
                            <?php endif; ?>
                            <li class="nav-item <?php echo ($current_page === 'whatsapp_conversacion.php') ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('whatsapp_conversacion.php') ?>">
                                    <i class="bi bi-whatsapp ni"></i>
                                    <span class="nl">Conversaciones WhatsApp</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <hr class="sidebar-sep">

                <div class="sidebar-module <?php echo $mod_website || $mod_analytics ? 'open' : ''; ?>" data-module="website">
                    <div class="sidebar-module-header" onclick="toggleModule('website')" role="button" aria-expanded="<?php echo $mod_website || $mod_analytics ? 'true' : 'false'; ?>" aria-controls="module-body-website">
                        <span class="mod-icon mod-website"><i class="bi bi-window-stack"></i></span>
                        <span class="mod-label">Web Site</span>
                        <i class="bi bi-chevron-down mod-arrow"></i>
                    </div>
                    <div class="sidebar-module-body" id="module-body-website">
                        <ul class="sidebar-nav-list">
                            <?php if ($hubAnalytics): ?>
                            <li class="nav-item <?php echo ($current_page === 'index.php' && strpos($current_path, 'analytics') !== false) ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('analytics/index.php') ?>">
                                    <i class="bi bi-graph-up ni"></i>
                                    <span class="nl">Dashboard Analítico</span>
                                </a>
                            </li>
                            <li class="nav-item <?php echo ($current_page === 'funnel.php') ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('analytics/funnel.php') ?>">
                                    <i class="bi bi-funnel ni"></i>
                                    <span class="nl">Funnel comercial</span>
                                </a>
                            </li>
                            <li class="nav-item <?php echo ($current_page === 'pages.php') ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('analytics/pages.php') ?>">
                                    <i class="bi bi-bar-chart ni"></i>
                                    <span class="nl">Análisis por página</span>
                                </a>
                            </li>
                            <li class="nav-item <?php echo ($current_page === 'sessions.php') ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('analytics/sessions.php') ?>">
                                    <i class="bi bi-diagram-3 ni"></i>
                                    <span class="nl">Sesiones</span>
                                </a>
                            </li>
                            <li class="nav-item <?php echo ($current_page === 'reportes.php') ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('analytics/reportes.php') ?>">
                                    <i class="bi bi-file-earmark-spreadsheet ni"></i>
                                    <span class="nl">Exportar reportes</span>
                                </a>
                            </li>
                            <li class="nav-item <?php echo ($current_page === 'seo_mexico_geo_status.php') ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('analytics/seo_mexico_geo_status.php') ?>">
                                    <i class="bi bi-list-check ni"></i>
                                    <span class="nl">SEO · Estado GEO</span>
                                </a>
                            </li>
                            <li class="nav-item <?php echo ($current_page === 'seo_mexico_checklist.php') ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('analytics/seo_mexico_checklist.php') ?>">
                                    <i class="bi bi-code-slash ni"></i>
                                    <span class="nl">SEO · Auditoría código</span>
                                </a>
                            </li>
                            <li class="nav-item <?php echo ($current_page === 'seo_mexico_external.php') ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('analytics/seo_mexico_external.php') ?>">
                                    <i class="bi bi-box-arrow-up-right ni"></i>
                                    <span class="nl">SEO · Tareas externas</span>
                                </a>
                            </li>
                            <li class="nav-item <?php echo ($current_page === 'seo_mexico_monitor.php') ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('analytics/seo_mexico_monitor.php') ?>">
                                    <i class="bi bi-activity ni"></i>
                                    <span class="nl">SEO · Cola propuestas</span>
                                </a>
                            </li>
                            <li class="nav-item <?php echo ($current_page === 'seo_mexico_ai_prompt.php') ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('analytics/seo_mexico_ai_prompt.php') ?>">
                                    <i class="bi bi-chat-dots ni"></i>
                                    <span class="nl">SEO · Chat IA</span>
                                </a>
                            </li>
                            <li class="nav-item <?php echo ($current_page === 'seo_mexico_plazas.php') ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('analytics/seo_mexico_plazas.php') ?>">
                                    <i class="bi bi-geo-alt ni"></i>
                                    <span class="nl">SEO · Plazas</span>
                                </a>
                            </li>
                            <li class="nav-item <?php echo ($current_page === 'seo_mexico_urls.php') ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('analytics/seo_mexico_urls.php') ?>">
                                    <i class="bi bi-link-45deg ni"></i>
                                    <span class="nl">SEO · URLs</span>
                                </a>
                            </li>
                            <li class="nav-item <?php echo ($current_page === 'seo_sitemap_directory.php') ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('analytics/seo_sitemap_directory.php') ?>">
                                    <i class="bi bi-folder2-open ni"></i>
                                    <span class="nl">SEO · Directorio sitemap</span>
                                </a>
                            </li>
                            <?php endif; ?>
                            <li class="nav-item <?php echo ($current_page === 'leads.php' && strpos($current_path, 'website') !== false) ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('website/leads.php') ?>">
                                    <i class="bi bi-person-lines-fill ni"></i>
                                    <span class="nl">Leads Website</span>
                                </a>
                            </li>
                            <li class="nav-item <?php echo (strpos($current_path, '/mailing/') !== false) ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('mailing/') ?>">
                                    <i class="bi bi-mailbox ni"></i>
                                    <span class="nl">Mailing</span>
                                </a>
                            </li>
                            <li class="nav-item <?php echo ($current_page === 'templates.php' && strpos($current_path, 'emails') !== false) ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('emails/templates.php') ?>">
                                    <i class="bi bi-envelope-paper ni"></i>
                                    <span class="nl">Plantillas de correo</span>
                                </a>
                            </li>
                            <li class="nav-item <?php echo ($current_page === 'ver_briefings.php') ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('ver_briefings.php') ?>">
                                    <i class="bi bi-layout-text-window-reverse ni"></i>
                                    <span class="nl">Web Sites Gratis</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <hr class="sidebar-sep">

                <div class="sidebar-module <?php echo $mod_hostpro ? 'open' : ''; ?>" data-module="hostpro">
                    <div class="sidebar-module-header" onclick="toggleModule('hostpro')" role="button" aria-expanded="<?php echo $mod_hostpro ? 'true' : 'false'; ?>" aria-controls="module-body-hostpro">
                        <span class="mod-icon mod-hostpro"><i class="bi bi-lightning-charge-fill"></i></span>
                        <span class="mod-label">HostPro</span>
                        <i class="bi bi-chevron-down mod-arrow"></i>
                    </div>
                    <div class="sidebar-module-body" id="module-body-hostpro">
                        <ul class="sidebar-nav-list">
                            <li class="nav-item <?php echo ($current_page === 'hostpro_planes.php') ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('hostpro_planes.php') ?>">
                                    <i class="bi bi-box-seam-fill ni"></i>
                                    <span class="nl">Gestión de Planes</span>
                                </a>
                            </li>
                            <li class="nav-item <?php echo ($current_page === 'hostpro_caracteristicas.php') ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('hostpro_caracteristicas.php') ?>">
                                    <i class="bi bi-sliders ni"></i>
                                    <span class="nl">Características</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <hr class="sidebar-sep">

                <div class="sidebar-module <?php echo $mod_planpro ? 'open' : ''; ?>" data-module="planpro">
                    <div class="sidebar-module-header" onclick="toggleModule('planpro')" role="button" aria-expanded="<?php echo $mod_planpro ? 'true' : 'false'; ?>" aria-controls="module-body-planpro">
                        <span class="mod-icon mod-planpro"><i class="bi bi-globe2"></i></span>
                        <span class="mod-label">Plan Pro Web</span>
                        <i class="bi bi-chevron-down mod-arrow"></i>
                    </div>
                    <div class="sidebar-module-body" id="module-body-planpro">
                        <ul class="sidebar-nav-list">
                            <li class="nav-item <?php echo ($current_page === 'planpro_planes.php') ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('planpro_planes.php') ?>">
                                    <i class="bi bi-box-seam-fill ni"></i>
                                    <span class="nl">Gestión de Planes</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <hr class="sidebar-sep">

                <div class="sidebar-module <?php echo $mod_cotizaciones ? 'open' : ''; ?>" data-module="cotizaciones">
                    <div class="sidebar-module-header" onclick="toggleModule('cotizaciones')" role="button" aria-expanded="<?php echo $mod_cotizaciones ? 'true' : 'false'; ?>" aria-controls="module-body-cotizaciones">
                        <span class="mod-icon mod-cotizaciones"><i class="bi bi-file-earmark-text-fill"></i></span>
                        <span class="mod-label">Cotizaciones</span>
                        <i class="bi bi-chevron-down mod-arrow"></i>
                    </div>
                    <div class="sidebar-module-body" id="module-body-cotizaciones">
                        <ul class="sidebar-nav-list">
                            <li class="nav-item <?php echo ($current_page === 'index.php' && strpos($current_path, 'cotizaciones') !== false) ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('cotizaciones/index.php') ?>">
                                    <i class="bi bi-robot ni"></i>
                                    <span class="nl">Cotizaciones IA</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <hr class="sidebar-sep">

                <?php if ($canSeguridad): ?>
                <div class="sidebar-module <?php echo $mod_seguridad ? 'open' : ''; ?>" data-module="seguridad">
                    <div class="sidebar-module-header" onclick="toggleModule('seguridad')" role="button" aria-expanded="<?php echo $mod_seguridad ? 'true' : 'false'; ?>" aria-controls="module-body-seguridad">
                        <span class="mod-icon mod-soporte"><i class="bi bi-shield-lock-fill"></i></span>
                        <span class="mod-label">Seguridad</span>
                        <i class="bi bi-chevron-down mod-arrow"></i>
                    </div>
                    <div class="sidebar-module-body" id="module-body-seguridad">
                        <ul class="sidebar-nav-list">
                            <li class="nav-item <?php echo ($current_page === 'accesos.php') ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('seguridad/accesos.php') ?>">
                                    <i class="bi bi-person-bounding-box ni"></i>
                                    <span class="nl">Accesos del portal</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <hr class="sidebar-sep">
                <?php endif; ?>

                <div class="sidebar-module <?php echo $mod_soporte ? 'open' : ''; ?>" data-module="soporte">
                    <div class="sidebar-module-header" onclick="toggleModule('soporte')" role="button" aria-expanded="<?php echo $mod_soporte ? 'true' : 'false'; ?>" aria-controls="module-body-soporte">
                        <span class="mod-icon mod-soporte"><i class="bi bi-headset"></i></span>
                        <span class="mod-label">Soporte</span>
                        <i class="bi bi-chevron-down mod-arrow"></i>
                    </div>
                    <div class="sidebar-module-body" id="module-body-soporte">
                        <ul class="sidebar-nav-list">
                            <li class="nav-item <?php echo ($current_page === 'tickets_vista.php') ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('tickets_vista.php') ?>">
                                    <i class="bi bi-ticket-perforated-fill ni"></i>
                                    <span class="nl">Filtro Tickets</span>
                                </a>
                            </li>
                            <li class="nav-item <?php echo (strpos($current_path, '/solicitudes/') !== false) ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?= adm_href('solicitudes/') ?>" target="_blank" rel="noopener">
                                    <i class="bi bi-clipboard-check ni"></i>
                                    <span class="nl">Solicitudes</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

            <?php endif; ?>

        </div>

        <div class="sidebar-footer">
            <span class="sidebar-footer-brand">Conlineweb Gestión</span>
            <a href="<?= adm_href('cerrarSesion.php') ?>" class="sidebar-logout-btn" title="Cerrar sesión">
                <i class="bi bi-box-arrow-right"></i>
                Cerrar sesión
            </a>
        </div>

    </nav>

    <div id="content-wrapper">
        <div class="container-fluid">

    <script>
        function toggleModule(name) {
            var el = document.querySelector('[data-module="' + name + '"]');
            if (!el) return;
            var isOpen = el.classList.toggle('open');
            localStorage.setItem('sidebar-module-' + name, isOpen ? 'open' : 'closed');
            var header = el.querySelector('.sidebar-module-header');
            if (header) header.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            if (isOpen) {
                requestAnimationFrame(function () {
                    el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                });
            }
        }

        function toggleSidebar() {
            document.querySelector('.menu-web-sidebar')?.classList.toggle('active');
            document.querySelector('.sidebar-overlay')?.classList.toggle('active');
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.sidebar-module').forEach(function(mod) {
                var name = mod.getAttribute('data-module');
                if (!name) return;
                var saved = localStorage.getItem('sidebar-module-' + name);
                if (saved) {
                    mod.classList.toggle('open', saved === 'open');
                }
                var header = mod.querySelector('.sidebar-module-header');
                if (header) header.setAttribute('aria-expanded', mod.classList.contains('open'));
            });

            document.querySelectorAll('.sidebar-nav-list .nav-link').forEach(function(link) {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 767) toggleSidebar();
                });
            });
        });
    </script>
<?php
// Cerebro flotante (también lo registra auth_middleware; el guard evita duplicado)
if (empty($GLOBALS['cw_brain_widget_rendered'])) {
    require_once __DIR__ . '/includes/cw_brain_widget.php';
}
?>
<?php if ($hubCrm): ?>
    <script src="<?= adm_href('assets/js/chat-menu-live.js') ?>?v=7" defer></script>
<?php endif; ?>
<?php if ($mod_analytics || $hubCrm): ?>
    <script src="<?= adm_href('assets/js/seo-ai-notify.js') ?>?v=1" defer></script>
<?php endif; ?>
<?php if (!empty($_GET['hub_error'])): ?>
<div class="adm-flash-wrap"><div class="alert alert-warning alert-dismissible fade show mb-0" role="alert">
    No tienes permiso para acceder a ese módulo.
    <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
</div></div>
<?php endif; ?>