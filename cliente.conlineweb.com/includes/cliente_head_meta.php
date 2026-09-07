<?php
declare(strict_types=1);

if (!function_exists('cliente_plain_title')) {
    function cliente_plain_title(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');

        return $text;
    }
}

if (!function_exists('cliente_document_title')) {
    function cliente_document_title(string $pageTitle): string
    {
        $pageTitle = cliente_plain_title($pageTitle);
        if ($pageTitle === '') {
            return 'Área Cliente | ConlineWeb';
        }
        if (stripos($pageTitle, 'conlineweb') !== false || stripos($pageTitle, 'área cliente') !== false) {
            return $pageTitle;
        }

        return $pageTitle . ' | Área Cliente ConlineWeb';
    }
}

if (!function_exists('cliente_resolve_page_title')) {
    function cliente_resolve_page_title(): string
    {
        if (!empty($GLOBALS['clientePageTitle'])) {
            return cliente_plain_title((string) $GLOBALS['clientePageTitle']);
        }

        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $base = basename($script);

        $pathTitles = [
            '/consulta/consultar.php' => 'Consulta de registros',
        ];

        foreach ($pathTitles as $needle => $title) {
            if (str_ends_with($script, $needle)) {
                return $title;
            }
        }

        $fileTitles = [
            'index.php' => 'Centro de ayuda',
            'dominios.php' => 'Mis dominios',
            'hosting.php' => 'Mi hosting',
            'pagos.php' => 'Mis pagos',
            'tickets.php' => 'Mis tickets',
            'cliente.php' => 'Mis datos',
            'mis-sitios.php' => 'Mis sitios web',
            'productos.php' => 'Mis productos',
            'facturas.php' => 'Mis facturas',
            'facturas2020.php' => 'Mis facturas',
            'ingreso.php' => 'Iniciar sesión',
            'reset_password_client.php' => 'Cambiar contraseña',
            'reset_password.php' => 'Restablecer contraseña',
            'reset_request.php' => 'Recuperar contraseña',
        ];

        return $fileTitles[$base] ?? 'Área Cliente';
    }
}

if (!function_exists('cliente_favicon_markup')) {
    /**
     * Favicon igual que conlineweb.com (isotipo con degradado azul → cyan).
     */
    function cliente_favicon_markup(): string
    {
        $root = dirname(__DIR__);
        $ico = 'images/c-online_isotipo.ico';
        $img = 'images/c-online_isotipo.jpg';
        $svg = 'assets/images/favicon.svg';
        $pngFallback = 'images/c-online-simbolo.png';

        if (!is_file($root . '/' . $ico)) {
            $ico = $pngFallback;
        }
        if (!is_file($root . '/' . $img)) {
            $img = is_file($root . '/' . $pngFallback) ? $pngFallback : $ico;
        }

        $out = '<link rel="icon" href="' . htmlspecialchars($ico, ENT_QUOTES, 'UTF-8') . '" type="image/x-icon">' . "\n    ";
        $out .= '<link rel="icon" href="' . htmlspecialchars($img, ENT_QUOTES, 'UTF-8') . '" type="image/jpeg" sizes="32x32">' . "\n    ";
        if (is_file($root . '/' . $svg)) {
            $out .= '<link rel="icon" type="image/svg+xml" href="' . htmlspecialchars($svg, ENT_QUOTES, 'UTF-8') . '">' . "\n    ";
        }
        $out .= '<link rel="apple-touch-icon" href="' . htmlspecialchars($img, ENT_QUOTES, 'UTF-8') . '" sizes="180x180">' . "\n    ";
        $out .= '<link rel="shortcut icon" href="' . htmlspecialchars($ico, ENT_QUOTES, 'UTF-8') . '" type="image/x-icon">';

        return $out;
    }
}

if (!function_exists('cliente_robots_meta_markup')) {
    function cliente_robots_meta_markup(): string
    {
        return '<meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex">' . "\n"
            . '    <meta name="googlebot" content="noindex, nofollow, noarchive, nosnippet">' . "\n"
            . '    <meta name="referrer" content="no-referrer">';
    }
}

if (!function_exists('cliente_boot_security_headers')) {
    function cliente_boot_security_headers(): void
    {
        $shared = dirname(__DIR__, 2) . '/includes/cw_portal_security.php';
        if (is_file($shared)) {
            require_once $shared;
            if (function_exists('cw_portal_send_security_headers')) {
                cw_portal_send_security_headers(true);
            }
        }
    }
}
