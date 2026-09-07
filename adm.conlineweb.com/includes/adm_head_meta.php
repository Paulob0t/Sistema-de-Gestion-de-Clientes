<?php
declare(strict_types=1);

if (!function_exists('adm_plain_title')) {
    function adm_plain_title(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');

        return $text;
    }
}

if (!function_exists('adm_document_title')) {
    function adm_document_title(string $pageTitle): string
    {
        $pageTitle = adm_plain_title($pageTitle);
        if ($pageTitle === '') {
            return 'ConlineWeb Admin';
        }
        if (stripos($pageTitle, 'conlineweb') !== false) {
            return $pageTitle;
        }

        return $pageTitle . ' | ConlineWeb Admin';
    }
}

if (!function_exists('adm_resolve_page_title')) {
    function adm_resolve_page_title(): string
    {
        if (!empty($GLOBALS['admDocumentTitle'])) {
            return adm_plain_title((string) $GLOBALS['admDocumentTitle']);
        }
        if (!empty($GLOBALS['admPageTitle'])) {
            return adm_plain_title((string) $GLOBALS['admPageTitle']);
        }
        if (!empty($GLOBALS['websiteHeroTitle'])) {
            return adm_plain_title((string) $GLOBALS['websiteHeroTitle']);
        }

        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $base = basename($script);

        $pathTitles = [
            '/website/leads.php' => 'Leads Website',
            '/website/levantamiento-ver.php' => 'Levantamiento de requerimientos',
            '/analytics/index.php' => 'Analytics · Resumen',
            '/analytics/pages.php' => 'Analytics · Páginas',
            '/analytics/funnel.php' => 'Analytics · Embudo',
            '/analytics/sessions.php' => 'Analytics · Sesiones',
            '/analytics/reportes.php' => 'Analytics · Reportes',
            '/analytics/hub_migrate.php' => 'Migración CW Hub',
            '/leads/inbox.php' => 'Bandeja CRM',
            '/leads/index.php' => 'Leads CRM',
            '/leads/kanban.php' => 'Pipeline Kanban',
            '/chat/index.php' => 'Base de conocimiento',
            '/chat/config.php' => 'Configuración del chatbot',
            '/chat/estadisticas.php' => 'Estadísticas del chat',
            '/cotizaciones/index.php' => 'Cotizaciones',
            '/seguridad/accesos.php' => 'Accesos del portal',
        ];

        foreach ($pathTitles as $needle => $title) {
            if (str_ends_with($script, $needle)) {
                return $title;
            }
        }

        $fileTitles = [
            'index.php' => 'Dashboard de Pagos',
            'clientes.php' => 'Clientes',
            'dominios.php' => 'Dominios',
            'hosting.php' => 'Hosting',
            'pagos.php' => 'Pagos',
            'ver_briefings.php' => 'Briefings',
            'tickets_vista.php' => 'Tickets',
            'whatsapp_conversacion.php' => 'WhatsApp',
            'detalle_cliente.php' => 'Detalle del cliente',
            'formulario_cliente.php' => 'Registro de cliente',
            'formulario_dominio.php' => 'Registro de dominio',
            'formulario_hosting.php' => 'Registro de hosting',
            'formulario_proyectos.php' => 'Registro de proyecto',
            'hostpro_planes.php' => 'HostPro · Planes',
            'hostpro_caracteristicas.php' => 'HostPro · Características',
            'planpro_planes.php' => 'PlanPro · Planes',
            'email_preview.php' => 'Vista previa de correos',
        ];

        return $fileTitles[$base] ?? 'Panel Admin';
    }
}

if (!function_exists('adm_favicon_markup')) {
    function adm_favicon_markup(): string
    {
        if (!function_exists('adm_href')) {
            require_once __DIR__ . '/adm_paths.php';
        }

        $icon = adm_href('images/favicon-16x16.png');

        return '<link rel="icon" type="image/png" sizes="16x16" href="' . $icon . '">' . "\n"
            . '    <link rel="shortcut icon" href="' . $icon . '" type="image/png">';
    }
}

/**
 * Cabeceras HTTP: el panel admin no debe indexarse ni archivarse.
 */
if (!function_exists('adm_send_noindex_headers')) {
    function adm_send_noindex_headers(): void
    {
        if (headers_sent()) {
            return;
        }
        header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex', true);
        header('Referrer-Policy: no-referrer', false);
    }
}

/**
 * Meta tags HTML para páginas del panel.
 */
if (!function_exists('adm_robots_meta_markup')) {
    function adm_robots_meta_markup(): string
    {
        return '<meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex">' . "\n"
            . '    <meta name="googlebot" content="noindex, nofollow, noarchive, nosnippet, noimageindex">' . "\n"
            . '    <meta name="bingbot" content="noindex, nofollow">' . "\n"
            . '    <meta name="google" content="notranslate">';
    }
}
