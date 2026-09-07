<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/includes/cliente_session.php';
    cliente_start_session();
}
if (cliente_is_logged_in()) {
    include 'conn.php';
    require_once __DIR__ . '/includes/cliente_login_log_hook.php';
    cliente_login_log_on_request(isset($conn) && $conn instanceof mysqli ? $conn : null);
    require_once __DIR__ . '/includes/cliente_head_meta.php';
    cliente_boot_security_headers();
    $usrid = $_SESSION['uid'];

    // NEW: Query to count pending payments from pagos table
    $query_pagos = mysqli_query($conn, "SELECT COUNT(*) as total_pendientes FROM pagos WHERE id_clie = $usrid AND estatus != 1 AND Registro = 0");
    $resultado = mysqli_fetch_assoc($query_pagos);
    $total_pendientes = $resultado['total_pendientes'];
    ?>

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#000147">
        <?= cliente_robots_meta_markup() ?>
        <title><?= htmlspecialchars(cliente_document_title(cliente_resolve_page_title()), ENT_QUOTES, 'UTF-8') ?></title>
        <?= cliente_favicon_markup() ?>

        <!-- Fuentes y estilos -->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap"
            rel="stylesheet">

        <!-- Hojas de estilo locales -->
        <link rel="stylesheet" href="assets/css/bootstrap.css">
        <link rel="stylesheet" href="assets/vendors/iconly/bold.css">
        <link rel="stylesheet" href="assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
        <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
        <link rel="stylesheet" href="assets/css/app.css">
        <link rel="stylesheet" href="assets/css/cliente-menu.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/cliente-menu.css') ?>">

        <!-- Scripts -->
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>

    <style>
        :root {
            --primary-color: #000147;
            --secondary-color: #10b981;
            --dark-color: #1e293b;
            --light-color: #f8fafc;
            --gray-light: #e2e8f0;
            --info-color: #17a2b8;
            --warning-color: #ffc107;
            --warning-dark-color: #856404;
        }

        /* Cabecera con icono */
        .header-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, #1a1a6e 100%);
            padding: 0.85rem 1.15rem;
            margin: 0 0 0.85rem 0;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            display: flex;
            align-items: center;
            gap: 0.9rem;
        }

        .header-icon-container {
            background: rgba(255, 255, 255, 0.1);
            padding: 0.55rem;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .header-icon {
            color: var(--secondary-color);
            font-size: 1.75rem;
        }

        .header-text {
            flex: 1;
        }

        .header-title {
            color: white;
            font-weight: 600;
            font-size: 1.4rem;
            margin-bottom: 0.15rem;
            line-height: 1.2;
        }

        .header-subtitle {
            color: rgba(255, 255, 255, 0.85);
            font-weight: 400;
            font-size: 0.88rem;
            margin: 0;
            line-height: 1.3;
        }

        /* Alertas informativas */
        .info-alert {
            background-color: #f8f9fa;
            border-left: 4px solid var(--info-color);
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-radius: 0 8px 8px 0;
        }

        .alert-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1.25rem;
        }

        .alert-item:last-child {
            margin-bottom: 0;
        }

        .alert-icon {
            margin-right: 1rem;
            font-size: 1.5rem;
            color: var(--info-color);
            flex-shrink: 0;
            margin-top: 0.15rem;
        }

        .alert-content {
            flex: 1;
        }

        .alert-title {
            display: block;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
        }

        .alert-text {
            color: var(--dark-color);
            margin: 0;
            line-height: 1.5;
            font-size: 0.95rem;
        }

        /* Estilo para alerta de advertencia */
        .alert-item.warning .alert-icon {
            color: var(--warning-color);
        }

        .alert-item.warning .alert-title {
            color: var(--warning-dark-color);
        }

        /* Formulario DNS */
        .dns-form-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            border: 1px solid var(--gray-light);
            padding: 1.75rem;
            width: 100%;
        }

        .dns-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--gray-light);
        }

        .dns-input-group {
            margin-bottom: 1.5rem;
        }

        .dns-label {
            display: block;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .dns-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-light);
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .dns-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 1, 71, 0.1);
            outline: none;
        }

        /* Botones */
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 0.75rem 1.75rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
        }

        .btn-primary:hover {
            background-color: #1a1a6e;
            transform: translateY(-1px);
        }

        .btn-primary i {
            margin-right: 0.5rem;
        }

        /* Estado sin dominios */
        .no-domains {
            text-align: center;
            padding: 3rem 0;
            background: white;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--gray-light);
        }

        .no-domains i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 1rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-section {
                padding: 0.75rem 1rem;
                margin: 0 0 0.75rem 0;
            }

            .header-title {
                font-size: 1.5rem;
            }

            .header-subtitle {
                font-size: 0.95rem;
            }

            .dns-form-container {
                padding: 1.25rem;
            }

            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .dns-title {
                font-size: 1.3rem;
            }

            .info-alert {
                padding: 1.25rem;
            }

            .alert-icon {
                font-size: 1.25rem;
                margin-right: 0.75rem;
            }

            .alert-title {
                font-size: 0.95rem;
            }

            .alert-text {
                font-size: 0.9rem;
            }
        }
    </style>

    <!-- Sidebar -->
    <div class="cw-nav-backdrop" id="cwNavBackdrop" hidden aria-hidden="true"></div>

    <div id="sidebar" class="active">
        <div class="sidebar-wrapper active">
            <div class="sidebar-header">
                <div class="logo">
                    <a href="index.php">
                        <img src="images/logo-conline.png" alt="Logo ConlineWeb">
                    </a>
                </div>
                <a href="#" class="sidebar-hide d-xl-none" id="cwNavClose" aria-label="Cerrar menú">
                    <i class="bi bi-x"></i>
                </a>
            </div>

            <?php $current_page = basename($_SERVER['PHP_SELF']); ?>

            <a href="cliente.php" class="client-card<?php echo $current_page === 'cliente.php' || $current_page === 'reset_password_client.php' ? ' client-card--active' : ''; ?>">
                <?php
                $query = mysqli_query($conn, "SELECT nombre_contacto, empresa FROM clientes WHERE id=" . (int) $usrid);
                if ($query && $cliente = mysqli_fetch_assoc($query)):
                ?>
                <div class="client-card__avatar"><i class="bi bi-person-circle"></i></div>
                <div class="client-card__info">
                    <span class="client-card__label">Mi cuenta</span>
                    <span class="client-card__name"><?php echo htmlspecialchars($cliente['nombre_contacto']); ?></span>
                    <?php if (trim((string) ($cliente['empresa'] ?? '')) !== ''): ?>
                    <span class="client-card__company"><?php echo htmlspecialchars($cliente['empresa']); ?></span>
                    <?php endif; ?>
                </div>
                <i class="bi bi-chevron-right client-card__arrow"></i>
                <?php endif; ?>
            </a>

            <?php if ($total_pendientes > 0): ?>
            <a href="pagos.php" class="sidebar-alert">
                <span class="sidebar-alert__icon"><i class="bi bi-exclamation-circle-fill"></i></span>
                <span class="sidebar-alert__text">
                    <strong><?php echo (int) $total_pendientes; ?> pago<?php echo $total_pendientes > 1 ? 's' : ''; ?> pendiente<?php echo $total_pendientes > 1 ? 's' : ''; ?></strong>
                    <small>Ir a pagar</small>
                </span>
                <i class="bi bi-chevron-right"></i>
            </a>
            <?php endif; ?>

            <!-- Menú principal -->
            <div class="sidebar-menu">
                <ul class="menu menu--simple">
                    <?php
                    $menu_sections = [
                        [
                            'title' => 'Principal',
                            'items' => [
                                ['page' => 'index.php', 'href' => 'index.php', 'icon' => 'bi-house-door-fill', 'label' => 'Inicio'],
                                ['page' => 'mis-sitios.php', 'href' => 'mis-sitios.php', 'icon' => 'bi-hdd-stack-fill', 'label' => 'Mis sitios'],
                            ],
                        ],
                        [
                            'title' => 'Servicios',
                            'items' => [
                                ['page' => 'dominios.php', 'href' => 'dominios.php', 'icon' => 'bi-globe2', 'label' => 'Dominios', 'active_pages' => ['dominios.php', 'dns.php', 'transferencia_dominio.php']],
                                ['page' => 'hosting.php', 'href' => 'hosting.php', 'icon' => 'bi-hdd-rack-fill', 'label' => 'Hosting'],
                            ],
                        ],
                        [
                            'title' => 'Cuenta',
                            'items' => [
                                ['page' => 'pagos.php', 'href' => 'pagos.php', 'icon' => 'bi-receipt', 'label' => 'Pagos', 'badge' => $total_pendientes > 0 ? $total_pendientes : null],
                                ['page' => 'tickets.php', 'href' => 'tickets.php', 'icon' => 'bi-inbox-fill', 'label' => 'Soporte'],
                                ['page' => 'reset_password_client.php', 'href' => 'reset_password_client.php', 'icon' => 'bi-shield-lock-fill', 'label' => 'Cambiar contraseña'],
                                ['page' => 'chat', 'href' => "javascript:if(window.cwChatWidget){cwChatWidget.open();}else{void(0);}", 'icon' => 'bi-chat-dots-fill', 'label' => 'Chat en vivo', 'external' => true],
                            ],
                        ],
                    ];

                    foreach ($menu_sections as $section):
                    ?>
                    <li class="menu-section">
                        <div class="menu-section__head">
                            <span class="menu-section__title"><?php echo htmlspecialchars($section['title']); ?></span>
                        </div>
                        <ul class="menu-section__items">
                            <?php foreach ($section['items'] as $item):
                                $active_pages = $item['active_pages'] ?? [$item['page']];
                                $is_active = in_array($current_page, $active_pages, true);
                            ?>
                            <li class="sidebar-item<?php echo $is_active ? ' active' : ''; ?>">
                                <a href="<?php echo $item['href']; ?>" class="sidebar-link<?php echo !empty($item['external']) ? ' sidebar-link--external' : ''; ?>">
                                    <span class="sidebar-link__icon"><i class="bi <?php echo $item['icon']; ?>"></i></span>
                                    <span class="sidebar-link__text"><?php echo htmlspecialchars($item['label']); ?></span>
                                    <?php if (!empty($item['badge'])): ?>
                                    <span class="menu-pill"><?php echo (int) $item['badge']; ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="sidebar-footer">
                <a href="cerrarSesion.php" class="sidebar-logout">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Cerrar sesión</span>
                </a>
            </div>
        </div>
    </div>

    <div id="main">
        <header class="mb-3">
            <div class="cw-topbar" role="banner">
                <button type="button" class="cw-topbar__menu" id="cwNavOpen" aria-label="Abrir menú">
                    <i class="bi bi-list"></i>
                </button>
                <a href="index.php" class="cw-topbar__brand">
                    <strong>ConlineWeb</strong>
                    <span>Área cliente</span>
                </a>
                <?php if ($total_pendientes > 0): ?>
                <a href="pagos.php" class="cw-topbar__pay" title="Pagos pendientes">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <?= (int) $total_pendientes ?>
                </a>
                <?php endif; ?>
            </div>
        </header>

        <?php
        /*
         * Barra de navegación inferior (solo móvil, la oculta el CSS a partir
         * de 1200px). Cuatro destinos frecuentes más el acceso al menú
         * completo: así Dominios, Hosting y el resto siguen a un toque y
         * ninguna sección queda inalcanzable desde aquí.
         */
        $cw_tabbar = [
            ['page' => 'index.php', 'href' => 'index.php', 'icon' => 'bi-house-door-fill', 'label' => 'Inicio'],
            ['page' => 'mis-sitios.php', 'href' => 'mis-sitios.php', 'icon' => 'bi-hdd-stack-fill', 'label' => 'Sitios'],
            ['page' => 'pagos.php', 'href' => 'pagos.php', 'icon' => 'bi-receipt', 'label' => 'Pagos', 'badge' => $total_pendientes > 0 ? $total_pendientes : null],
            ['page' => 'tickets.php', 'href' => 'tickets.php', 'icon' => 'bi-inbox-fill', 'label' => 'Soporte'],
        ];
        /* Las secciones que no tienen pestaña propia resaltan "Menú". */
        $cw_tabbar_pages = array_column($cw_tabbar, 'page');
        ?>
        <nav class="cw-tabbar" aria-label="Navegación principal">
            <?php foreach ($cw_tabbar as $tab):
                $tab_active = $current_page === $tab['page'];
            ?>
            <a href="<?php echo $tab['href']; ?>" class="cw-tabbar__item<?php echo $tab_active ? ' is-active' : ''; ?>"<?php echo $tab_active ? ' aria-current="page"' : ''; ?>>
                <span class="cw-tabbar__icon">
                    <i class="bi <?php echo $tab['icon']; ?>"></i>
                    <?php if (!empty($tab['badge'])): ?>
                    <span class="cw-tabbar__badge"><?php echo (int) $tab['badge']; ?></span>
                    <?php endif; ?>
                </span>
                <span class="cw-tabbar__label"><?php echo htmlspecialchars($tab['label']); ?></span>
            </a>
            <?php endforeach; ?>
            <button type="button" class="cw-tabbar__item cw-tabbar__item--menu<?php echo in_array($current_page, $cw_tabbar_pages, true) ? '' : ' is-active'; ?>" id="cwTabbarMenu" aria-label="Abrir menú completo">
                <span class="cw-tabbar__icon"><i class="bi bi-grid"></i></span>
                <span class="cw-tabbar__label">Menú</span>
            </button>
        </nav>
        <style>
            :root {
                --sidebar-width: 268px;
                --header-height: 70px;
                --primary-color: #000147;
                --info-color: #324f9a;
                --secondary-color: #10b981;
                --dark-color: #1e293b;
                --light-color: #f8fafc;
                --gray-light: #e2e8f0;
            }

            body {
                font-family: 'Montserrat', sans-serif;
                overflow-x: hidden;
                background-color: #f8f9fa;
            }

            #app {
                display: flex;
                min-height: 100vh;
            }

            #sidebar {
                width: var(--sidebar-width);
                background: #ffffff;
                border-right: 1px solid #e2e8f0;
                box-shadow: 4px 0 24px rgba(0, 1, 71, 0.04);
                transition: all 0.3s;
                position: fixed;
                height: 100vh;
                z-index: 1000;
                overflow: hidden;
            }

            #sidebar .sidebar-wrapper {
                position: relative !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                height: 100vh !important;
                display: flex !important;
                flex-direction: column !important;
                overflow: hidden !important;
                padding: 20px !important;
                box-sizing: border-box !important;
            }

            .sidebar-header {
                display: flex;
                align-items: center;
                justify-content: center;
                position: relative;
                padding: 0 0 10px;
                margin-bottom: 10px;
                border-bottom: 1px solid #f1f5f9;
                flex-shrink: 0;
            }

            .sidebar-header .logo {
                display: flex;
                justify-content: center;
                align-items: center;
                width: 100%;
            }

            .sidebar-header .logo a {
                display: inline-flex;
                justify-content: center;
            }

            .sidebar-header .logo img {
                max-width: 148px;
                height: auto;
                display: block;
                margin: 0 auto;
            }

            .sidebar-hide {
                position: absolute;
                right: 0;
                top: 50%;
                transform: translateY(-50%);
                width: 34px;
                height: 34px;
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #64748b;
                text-decoration: none;
                background: #f8fafc;
            }

            .sidebar-hide:hover {
                background: #f1f5f9;
                color: #000147;
            }

            #main {
                flex: 1;
                margin-left: var(--sidebar-width);
                transition: all 0.3s;
                min-height: 100vh;
                display: flex;
                flex-direction: column;
            }

            #main > header.mb-3 {
                padding: 4px 16px 0;
                margin-bottom: 0 !important;
            }

            .page-content {
                flex: 1;
                padding: 6px 16px 16px;
                background-color: #f8f9fa;
            }

            #app > .main-content,
            #app > .main-content.container-fluid,
            #main .main-content,
            #main .main-content.container-fluid {
                padding: 6px 16px 16px;
                box-sizing: border-box;
            }

            /* Menú lateral */
            .sidebar-menu {
                flex: 1;
                overflow-y: auto;
                overflow-x: hidden;
                padding: 8px 2px 10px 4px;
                -webkit-overflow-scrolling: touch;
            }

            /* Anular padding lateral legacy de app.css */
            #sidebar .sidebar-wrapper .menu,
            #sidebar .sidebar-wrapper .menu.menu--simple {
                margin-top: 0 !important;
                padding: 0 !important;
                font-weight: inherit !important;
                text-align: left !important;
            }

            .menu--simple {
                list-style: none;
                margin: 0;
                padding: 0;
                width: 100%;
            }

            .menu-section {
                list-style: none;
                margin-bottom: 10px;
                padding: 0;
                text-align: left;
            }

            .menu-section:last-child {
                margin-bottom: 0;
            }

            .menu-section__head {
                margin: 0 0 3px;
                padding: 3px 8px 5px 10px;
                background: transparent;
                border: none;
                border-bottom: 1px solid #e2e8f0;
                border-radius: 0;
                text-align: left;
            }

            .menu-section__title {
                display: block;
                font-size: 0.68rem;
                font-weight: 700;
                color: #64748b;
                line-height: 1.15;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                text-align: left;
            }

            .menu-section__desc {
                display: block;
                margin-top: 1px;
                font-size: 0.64rem;
                font-weight: 400;
                color: #94a3b8;
                line-height: 1.2;
                text-align: left;
            }

            .menu-section__items {
                list-style: none;
                margin: 0;
                padding: 0 0 0 2px;
                display: flex;
                flex-direction: column;
                gap: 3px;
                align-items: stretch;
            }

            .menu-section__items .sidebar-item {
                list-style: none;
                margin: 0 !important;
                padding: 0 !important;
            }

            /* Anular estilos legacy de app.css */
            #sidebar .sidebar-wrapper .menu .sidebar-link,
            #sidebar .sidebar-wrapper .menu .sidebar-item .sidebar-link {
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
                padding: 6px 10px 6px 8px !important;
                justify-content: flex-start !important;
                text-align: left !important;
                margin: 0 !important;
                border-radius: 9px !important;
                color: #1e293b !important;
                font-size: 0.8rem !important;
                font-weight: 500 !important;
                text-decoration: none !important;
                transition: all 0.18s ease;
                border: 1px solid transparent;
            }

            #sidebar .sidebar-wrapper .menu .sidebar-link span,
            #sidebar .sidebar-wrapper .menu .sidebar-link .sidebar-link__text {
                margin-left: 0 !important;
            }

            .sidebar-link__icon {
                width: 28px;
                height: 28px;
                border-radius: 7px;
                background: #f8fafc;
                border: 1px solid #eef2f7;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
                transition: all 0.18s ease;
            }

            .sidebar-link__icon i {
                font-size: 0.88rem;
                color: #64748b;
            }

            .sidebar-link__text {
                flex: 1;
                min-width: 0;
                line-height: 1.3;
                color: #1e293b;
                text-align: left !important;
                white-space: normal !important;
                overflow: visible !important;
                text-overflow: unset !important;
                word-break: break-word;
                overflow-wrap: anywhere;
            }

            .sidebar-link:hover {
                background: #f1f5f9 !important;
                color: #000147 !important;
            }

            .sidebar-link:hover .sidebar-link__text {
                color: #000147 !important;
            }

            .sidebar-link:hover .sidebar-link__icon {
                background: #eff6ff;
                border-color: #dbeafe;
            }

            .sidebar-link:hover .sidebar-link__icon i {
                color: var(--primary-color);
            }

            .sidebar-item.active .sidebar-link {
                background: linear-gradient(135deg, #000147 0%, #1a1a6e 100%) !important;
                color: #ffffff !important;
                border: none !important;
                font-weight: 600 !important;
                box-shadow: 0 3px 10px rgba(0, 1, 71, 0.22) !important;
            }

            .sidebar-item.active .sidebar-link .sidebar-link__text {
                color: #ffffff !important;
            }

            .sidebar-item.active .sidebar-link__icon {
                background: rgba(255, 255, 255, 0.14);
                border-color: rgba(255, 255, 255, 0.22);
            }

            .sidebar-item.active .sidebar-link__icon i {
                color: #34d399 !important;
            }

            .sidebar-item.active .menu-pill {
                background: #fbbf24;
                color: #000147;
            }

            .menu-pill {
                flex-shrink: 0;
                background: #f59e0b;
                color: #fff;
                font-size: 0.62rem;
                font-weight: 700;
                min-width: 18px;
                height: 18px;
                padding: 0 5px;
                border-radius: 20px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            .client-card {
                display: flex;
                align-items: center;
                gap: 8px;
                margin: 0 0 10px;
                padding: 8px;
                background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
                border: 1px solid #e2e8f0;
                border-radius: 10px;
                text-decoration: none !important;
                transition: all 0.2s ease;
                flex-shrink: 0;
            }

            .client-card:hover {
                border-color: #cbd5e1;
                box-shadow: 0 4px 12px rgba(0, 1, 71, 0.06);
                transform: translateY(-1px);
            }

            .client-card--active {
                border-color: #93c5fd;
                background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            }

            .client-card__avatar {
                width: 34px;
                height: 34px;
                border-radius: 50%;
                background: #fff;
                border: 2px solid #e2e8f0;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }

            .client-card__avatar i {
                font-size: 1.25rem;
                color: var(--primary-color);
            }

            .client-card__info {
                display: flex;
                flex-direction: column;
                min-width: 0;
                line-height: 1.3;
                flex: 1;
            }

            .client-card__label {
                font-size: 0.68rem;
                color: #94a3b8;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.04em;
            }

            .client-card__name {
                font-size: 0.82rem;
                font-weight: 700;
                color: var(--dark-color);
                word-break: break-word;
            }

            .client-card__company {
                font-size: 0.7rem;
                color: #64748b;
                margin-top: 2px;
                word-break: break-word;
            }

            .client-card__arrow {
                color: #94a3b8;
                font-size: 0.85rem;
                flex-shrink: 0;
            }

            .sidebar-alert {
                display: flex;
                align-items: center;
                gap: 8px;
                margin: 0 0 22px;
                padding: 7px 8px;
                background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
                border: 1px solid #fdba74;
                border-radius: 10px;
                text-decoration: none !important;
                color: #9a3412;
                flex-shrink: 0;
                transition: all 0.2s ease;
            }

            .sidebar-alert:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(245, 158, 11, 0.15);
            }

            .sidebar-alert__icon {
                width: 32px;
                height: 32px;
                border-radius: 8px;
                background: #fff;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #ea580c;
                flex-shrink: 0;
            }

            .sidebar-alert__text {
                flex: 1;
                min-width: 0;
                display: flex;
                flex-direction: column;
                line-height: 1.25;
            }

            .sidebar-alert__text strong {
                font-size: 0.82rem;
            }

            .sidebar-alert__text small {
                font-size: 0.72rem;
                opacity: 0.85;
            }

            .sidebar-footer {
                padding: 10px 0 0;
                border-top: 1px solid #e2e8f0;
                background: transparent;
                flex-shrink: 0;
                margin-top: 10px;
            }

            .sidebar-logout {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                width: 100%;
                padding: 9px 10px;
                border-radius: 8px;
                border: 1px solid #fecaca;
                background: #fff;
                color: #dc2626;
                font-size: 0.8rem;
                font-weight: 600;
                text-decoration: none !important;
                transition: all 0.2s ease;
            }

            .sidebar-logout:hover {
                background: #fef2f2;
                border-color: #fca5a5;
                color: #b91c1c;
            }

            .client-info-container {
                width: auto;
                margin: 15px 12px;
                padding: 16px;
                background: #f6f6f6;
                border-radius: 8px;
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
                box-sizing: border-box;
            }

            .client-info-item {
                margin-bottom: 10px;
            }

            .client-info-item:last-child {
                margin-bottom: 0;
            }

            .client-info-label {
                display: block;
                font-size: 0.8rem;
                color: #666;
                margin-bottom: 2px;
            }

            .client-info-value {
                display: block;
                font-size: 0.95rem;
                color: var(--dark-color);
                font-weight: 500;
                width: 100%;
                max-width: 100%;
                line-height: 1.4;
                word-break: break-word;
                overflow-wrap: anywhere;
                white-space: normal;
            }

            .client-info-value--email {
                font-size: 0.85rem;
            }

            /* Botón de menú móvil moderno */
            .mobile-menu-btn {
                display: none !important;
                align-items: center;
                justify-content: center;
                gap: 12px;
                padding: 14px 20px;
                background: linear-gradient(135deg, var(--primary-color) 0%, #1a1a6e 100%);
                color: white;
                border-radius: 12px;
                text-decoration: none;
                margin-bottom: 20px;
                font-weight: 600;
                transition: all 0.3s ease;
                box-shadow: 0 4px 15px rgba(0, 1, 71, 0.2);
                border: none;
                width: 140px;
                height: 50px;
            }

            .mobile-menu-btn:hover {
                transform: translateY(-3px);
                box-shadow: 0 8px 25px rgba(0, 1, 71, 0.3);
                background: linear-gradient(135deg, #1a1a6e 0%, #000147 100%);
            }

            /* Estilo para estado activo en tickets - legacy cleanup handled by .sidebar-item.active */

            /* Cabecera con icono */
            .header-section {
                background: linear-gradient(135deg, var(--primary-color) 0%, #1a1a6e 100%);
                padding: 0.85rem 1.15rem;
                margin: 0 0 0.85rem 0;
                border-radius: 12px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            }

            .header-content {
                display: flex;
                align-items: center;
                gap: 0.9rem;
            }

            .header-icon-container {
                background: rgba(255, 255, 255, 0.1);
                padding: 0.55rem;
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .header-icon {
                color: var(--secondary-color);
                font-size: 1.75rem;
            }

            .header-text {
                flex: 1;
            }

            .header-title {
                color: white;
                font-weight: 600;
                font-size: 1.4rem;
                margin-bottom: 0.15rem;
                line-height: 1.2;
            }

            .header-subtitle {
                color: rgba(255, 255, 255, 0.85);
                font-weight: 400;
                font-size: 0.88rem;
                margin: 0;
                line-height: 1.3;
            }

            /* Tarjetas de dominio */
            .card-domain {
                background: white;
                border-radius: 10px;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
                margin-bottom: 1.5rem;
                border: 1px solid var(--gray-light);
                transition: transform 0.2s ease, box-shadow 0.2s ease;
                padding: 1.5rem;
            }

            .card-domain:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            }

            .domain-title {
                font-size: 1.1rem;
                font-weight: 500;
                color: var(--dark-color);
                text-decoration: none;
            }

            .domain-title:hover {
                color: var(--primary-color);
            }

            .domain-status {
                font-size: 0.75rem;
                font-weight: 600;
                padding: 0.25rem 0.75rem;
                border-radius: 1rem;
            }

            .status-active {
                background-color: #dcfce7;
                color: #166534;
            }

            .status-inactive {
                background-color: #fee2e2;
                color: #991b1b;
            }

            /* Botones */
            .boton_admin {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0.6rem 1rem;
                font-weight: 500;
                font-size: 0.875rem;
                color: var(--dark-color);
                background-color: white;
                border-radius: 8px;
                border: 1px solid var(--gray-light);
                transition: all 0.2s ease;
                width: 100%;
                text-decoration: none !important;
                cursor: pointer;
            }

            .boton_admin:hover {
                background-color: #f1f5f9;
                color: var(--primary-color);
                border-color: #cbd5e1;
            }

            .boton_admin i {
                margin-right: 0.5rem;
                font-size: 1rem;
            }

            /* Sección informativa */
            .info-section {
                background-color: white;
                border-radius: 10px;
                padding: 1.5rem;
                margin-top: 2rem;
                border: 1px solid var(--gray-light);
            }

            .info-title {
                font-size: 1.25rem;
                font-weight: 600;
                color: var(--dark-color);
                margin-bottom: 1rem;
                display: flex;
                align-items: center;
            }

            .info-title i {
                margin-right: 0.5rem;
                color: var(--primary-color);
            }

            .info-content p {
                margin-bottom: 1rem;
                line-height: 1.6;
            }

            .info-content a {
                color: var(--primary-color);
                text-decoration: none;
                font-weight: 500;
            }

            .info-content a:hover {
                text-decoration: underline;
            }

            /* Alertas */
            .alert {
                padding: 10px;
                margin: 5px 0;
                border-radius: 3px;
                background-color: Green;
                color: white;
                text-align: left;
                font-size: 13px;
            }

            .alertpago {
                padding: 10px;
                margin: 5px 0;
                border-radius: 3px;
                background-color: #ffd200;
                color: #000000;
                text-align: left;
                font-size: 13px;
            }

            .alertservicio {
                padding: 10px;
                margin: 5px 0;
                border-radius: 3px;
                background-color: #F87902;
                color: white;
                text-align: left;
                font-size: 13px;
            }

            /* Alerta de pagos pendientes (contenido principal) */
            .payment-alert-modern {
                background: #fffbeb;
                border: 1px solid #fcd34d;
                border-left: 4px solid #f59e0b;
                border-radius: 12px;
                box-shadow: 0 2px 10px rgba(245, 158, 11, 0.1);
                margin: 0 0 18px 0;
                overflow: hidden;
            }

            .payment-alert-content {
                display: flex;
                align-items: center;
                padding: 14px 16px;
                gap: 14px;
            }

            .payment-alert-icon {
                background: #fef3c7;
                border-radius: 10px;
                width: 42px;
                height: 42px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }

            .payment-alert-icon i {
                color: #d97706;
                font-size: 1.2rem;
            }

            .payment-alert-text {
                flex: 1;
                min-width: 0;
            }

            .payment-alert-title {
                font-size: 0.95rem;
                font-weight: 700;
                color: #92400e;
                margin-bottom: 4px;
                line-height: 1.3;
            }

            .payment-alert-subtitle {
                font-size: 0.84rem;
                color: #b45309;
                font-weight: 500;
                line-height: 1.45;
            }

            .payment-alert-count {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 22px;
                height: 22px;
                padding: 0 7px;
                margin-right: 4px;
                background: #f59e0b;
                color: #fff;
                font-size: 0.78rem;
                font-weight: 700;
                border-radius: 20px;
                vertical-align: middle;
            }

            .payment-alert-actions {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-shrink: 0;
            }

            .payment-btn-action {
                background: linear-gradient(135deg, #000147 0%, #1a1a6e 100%);
                color: #fff;
                padding: 10px 16px;
                border-radius: 8px;
                text-decoration: none;
                font-weight: 600;
                font-size: 0.85rem;
                display: inline-flex;
                align-items: center;
                gap: 7px;
                transition: all 0.2s ease;
                border: none;
                white-space: nowrap;
            }

            .payment-btn-action:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 14px rgba(0, 1, 71, 0.22);
                color: #fff;
                text-decoration: none;
            }

            .payment-btn-close {
                background: #fff;
                border: 1px solid #d97706;
                border-radius: 8px;
                width: 34px;
                height: 34px;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                transition: all 0.2s ease;
                color: #92400e;
                flex-shrink: 0;
                padding: 0;
            }

            .payment-btn-close:hover {
                background: #fef3c7;
                border-color: #b45309;
                color: #7c2d12;
            }

            .payment-btn-close i {
                font-size: 1.1rem;
                color: inherit;
                line-height: 1;
            }

            /* Animaciones */
            @keyframes slideInDown {
                from {
                    transform: translateY(-100%);
                    opacity: 0;
                }

                to {
                    transform: translateY(0);
                    opacity: 1;
                }
            }

            @keyframes pulse {

                0%,
                100% {
                    transform: scale(1);
                }

                50% {
                    transform: scale(1.1);
                }
            }

            .cerrarbtn {
                margin-left: 15px;
                color: white;
                float: right;
                font-size: 15px;
                line-height: 20px;
                cursor: pointer;
                transition: 0.3s;
            }

            .cerrartext {
                margin-left: 13px;
                color: white;
                float: center;
                font-size: 13px;
                line-height: 20px;
                cursor: pointer;
                transition: 0.3s;
            }

            .cerrarbtn:hover {
                color: black;
            }

            /* Responsive — el drawer móvil lo controla cliente-menu.css */
            @media (max-width: 1199.98px) {
                .mobile-menu-btn {
                    display: none !important;
                }

                #main {
                    margin-left: 0;
                }

                #main > header.mb-3 {
                    padding: 4px 4px 0;
                }

                /* Contenedor casi a sangre: el respiro lo da el padding del bloque. */
                #app > .main-content,
                #app > .main-content.container-fluid,
                #main .main-content,
                #main .main-content.container-fluid {
                    padding: 4px 4px 14px !important;
                }

                .header-section {
                    padding: 0.75rem 0.7rem;
                    margin: 0 0 0.75rem 0;
                }

                .header-content {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 1rem;
                }

                .header-icon-container {
                    padding: 0.8rem;
                }

                .header-icon {
                    font-size: 2rem;
                }

                .header-title {
                    font-size: 1.5rem;
                }

                .header-subtitle {
                    font-size: 0.95rem;
                }

                .card-domain {
                    padding: 1rem 0.7rem;
                }

                .client-card {
                    margin: 0 0 10px;
                    padding: 8px;
                }
            }

            @media (max-width: 576px) {
                .header-section {
                    padding: 0.7rem 0.85rem;
                }

                .header-title {
                    font-size: 1.3rem;
                }

                .info-section {
                    padding: 1rem;
                }

                .payment-alert-modern {
                    margin: 0 0 14px 0;
                    border-radius: 10px;
                }

                .payment-alert-content {
                    padding: 12px 14px;
                }

                .payment-alert-icon {
                    width: 38px;
                    height: 38px;
                }

                .payment-alert-title {
                    font-size: 0.9rem;
                }

                .payment-alert-subtitle {
                    font-size: 0.8rem;
                }

                .payment-btn-action {
                    padding: 10px 12px;
                    font-size: 0.82rem;
                }

                .payment-btn-close {
                    width: 38px;
                    height: 38px;
                }
            }
        </style>

        <!-- Navegación móvil: drawer (mismo sidebar) -->
        <script>
            (function initCwNavDrawer() {
                const sidebar = document.getElementById('sidebar');
                const backdrop = document.getElementById('cwNavBackdrop');
                const openBtn = document.getElementById('cwNavOpen');
                const closeBtn = document.getElementById('cwNavClose');
                if (!sidebar) return;

                function openNav() {
                    sidebar.classList.add('is-open');
                    if (backdrop) {
                        backdrop.hidden = false;
                        requestAnimationFrame(function () {
                            backdrop.classList.add('is-visible');
                        });
                        backdrop.setAttribute('aria-hidden', 'false');
                    }
                    document.body.classList.add('cw-nav-open');
                }

                function closeNav() {
                    sidebar.classList.remove('is-open');
                    if (backdrop) {
                        backdrop.classList.remove('is-visible');
                        backdrop.setAttribute('aria-hidden', 'true');
                        setTimeout(function () {
                            if (!sidebar.classList.contains('is-open')) {
                                backdrop.hidden = true;
                            }
                        }, 250);
                    }
                    document.body.classList.remove('cw-nav-open');
                }

                function toggleNav() {
                    if (sidebar.classList.contains('is-open')) closeNav();
                    else openNav();
                }

                openBtn?.addEventListener('click', function (e) {
                    e.preventDefault();
                    openNav();
                });
                closeBtn?.addEventListener('click', function (e) {
                    e.preventDefault();
                    closeNav();
                });

                /* "Menú" de la barra inferior: alterna, para que un segundo
                   toque en la misma pestaña cierre el cajón. */
                document.getElementById('cwTabbarMenu')?.addEventListener('click', function (e) {
                    e.preventDefault();
                    toggleNav();
                });
                backdrop?.addEventListener('click', closeNav);

                sidebar.querySelectorAll('.sidebar-link:not(.sidebar-link--external), .client-card, .sidebar-alert, .sidebar-logout').forEach(function (link) {
                    link.addEventListener('click', function () {
                        if (window.innerWidth < 1200) closeNav();
                    });
                });

                sidebar.querySelectorAll('.sidebar-link--external').forEach(function (link) {
                    link.addEventListener('click', function () {
                        if (window.innerWidth < 1200) closeNav();
                    });
                });

                window.addEventListener('resize', function () {
                    if (window.innerWidth >= 1200) closeNav();
                });

                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') closeNav();
                });

                // Compat: páginas viejas que usan .burger-btn
                document.querySelector('.burger-btn')?.addEventListener('click', function (e) {
                    e.preventDefault();
                    toggleNav();
                });
            })();
        </script>

        <!-- Scripts -->
        <script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
        <script src="assets/js/bootstrap.bundle.min.js"></script>
        <script src="assets/js/main.js"></script>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

        <div class="page-content">
            <?php
} else {
    header("Location: ingreso.php");
}
?>