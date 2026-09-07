<?php
/**
 * Propuestas de mejora sobre CUALQUIER vista del admin (adm.conlineweb.com)
 * dentro de whitelist amplia + deny duro (auth/pagos/secrets).
 * Flujo: propone → Monitor → preview → aprueba → aplica.
 */
declare(strict_types=1);

require_once __DIR__ . '/cw_seo_mexico_ai.php';
require_once __DIR__ . '/cw_site_ai_brain.php';

/**
 * Raíz del admin (adm.conlineweb.com).
 */
function cw_site_ai_admin_root(): ?string
{
    $root = realpath(dirname(__DIR__));
    return ($root !== false && is_dir($root)) ? $root : null;
}

/**
 * Catálogo de módulos/vistas del panel admin (SEO + operación).
 *
 * @return array<string, array<string,mixed>>
 */
function cw_site_ai_admin_views_catalog(): array
{
    return [
        'monitor' => [
            'label' => 'Cola de propuestas IA',
            'purpose' => 'Revisar propuestas IA y aprobar/rechazar.',
            'congruent' => 'DataTable, Detalle, filtros, botones, badges.',
            'files' => ['analytics/seo_mexico_monitor.php', 'includes/cw_seo_mexico_monitor.php', 'analytics/css/seo-module.css'],
            'admin_url' => 'analytics/seo_mexico_monitor.php',
            'flexible' => true,
        ],
        'chat_ia' => [
            'label' => 'Chat / Colaboración IA',
            'purpose' => 'Canal de comunicación con la IA; propuestas a cola.',
            'congruent' => 'Modos, presets, composer, mensajes de estado.',
            'files' => ['analytics/seo_mexico_ai_prompt.php', 'includes/cw_seo_mexico_ai_prompt.php', 'analytics/css/seo-module.css'],
            'admin_url' => 'analytics/seo_mexico_ai_prompt.php',
            'flexible' => true,
        ],
        'checklist' => [
            'label' => 'Auditoría / Checklist',
            'purpose' => 'Cola de correcciones y tareas SEO.',
            'congruent' => 'DataTable, Detalle, AutoFix, estados.',
            'files' => ['analytics/seo_mexico_checklist.php', 'includes/cw_seo_mexico_checklist.php', 'analytics/css/seo-module.css'],
            'admin_url' => 'analytics/seo_mexico_checklist.php',
            'flexible' => true,
        ],
        'external' => [
            'label' => 'Tareas externas SEO',
            'purpose' => 'GSC, GBP, reseñas y ops humanas.',
            'congruent' => 'Copy operativo y checklist externo.',
            'files' => ['analytics/seo_mexico_external.php'],
            'admin_url' => 'analytics/seo_mexico_external.php',
            'flexible' => true,
        ],
        'analytics' => [
            'label' => 'Analytics / reportes',
            'purpose' => 'Dashboard analytics, funnel, pages, reportes, sesiones.',
            'congruent' => 'UX de reportes y navegación analytics.',
            'files' => ['analytics/index.php', 'analytics/funnel.php', 'analytics/pages.php', 'analytics/reportes.php', 'analytics/sessions.php'],
            'admin_url' => 'analytics/index.php',
            'flexible' => true,
        ],
        'plazas' => [
            'label' => 'Plazas GEO',
            'purpose' => 'Plazas locales México.',
            'congruent' => 'Labels y claridad operativa.',
            'files' => ['analytics/seo_mexico_plazas.php', 'analytics/css/seo-module.css'],
            'admin_url' => 'analytics/seo_mexico_plazas.php',
            'flexible' => true,
        ],
        'urls' => [
            'label' => 'URLs SEO',
            'purpose' => 'Listado/orientación de URLs del programa.',
            'congruent' => 'Filtros y copy de URLs.',
            'files' => ['analytics/seo_mexico_urls.php', 'analytics/css/seo-module.css'],
            'admin_url' => 'analytics/seo_mexico_urls.php',
            'flexible' => true,
        ],
        'shell' => [
            'label' => 'Shell admin (menú / home)',
            'purpose' => 'Inicio y navegación global del panel.',
            'congruent' => 'menu.php, index, headers, sidebar CSS.',
            'files' => ['menu.php', 'index.php', 'includes/adm_page_header.php', 'assets/css/admin-sidebar.css', 'css/admin-platform.css'],
            'admin_url' => 'index.php',
            'flexible' => true,
        ],
        'clientes' => [
            'label' => 'Clientes',
            'purpose' => 'Listado y formularios de clientes (UX/copy; no auth ni borrados).',
            'congruent' => 'Tablas, labels, vacíos, formularios de captura.',
            'files' => ['clientes.php', 'detalle_cliente.php', 'formulario_cliente.php', 'nuevo_cliente.php', 'css/admin-registros.css'],
            'admin_url' => 'clientes.php',
            'flexible' => true,
        ],
        'dominios' => [
            'label' => 'Dominios',
            'purpose' => 'Gestión visual de dominios en admin.',
            'congruent' => 'Listados, formularios, estados visibles.',
            'files' => ['dominios.php', 'formulario_dominio.php', 'crear_dominio.php'],
            'admin_url' => 'dominios.php',
            'flexible' => true,
        ],
        'hosting' => [
            'label' => 'Hosting',
            'purpose' => 'Gestión visual de hosting/planes.',
            'congruent' => 'Listados, formularios, planes UI.',
            'files' => ['hosting.php', 'formulario_hosting.php', 'hostpro_planes.php', 'planpro_planes.php', 'js/hostpro_admin.js'],
            'admin_url' => 'hosting.php',
            'flexible' => true,
        ],
        'pagos_ui' => [
            'label' => 'Pagos (solo UI)',
            'purpose' => 'Pantallas de listado/éxito de pagos: labels y claridad. Sin procesar/cobrar.',
            'congruent' => 'UX de pagos.php y pantallas success; no lógica Stripe/procesar.',
            'files' => ['pagos.php', 'payment_success.php', 'payment_success_grupal.php', 'payment_success_preview.php', 'assets/css/payment-success.css'],
            'admin_url' => 'pagos.php',
            'flexible' => true,
        ],
        'tickets' => [
            'label' => 'Tickets',
            'purpose' => 'Vistas de tickets internos/públicos.',
            'congruent' => 'Listados, notas, estados, CSS tickets.',
            'files' => ['tickets_vista.php', 'tickets_public.php', 'tickets_public_notas.php', 'css/tickets-vista.css'],
            'admin_url' => 'tickets_vista.php',
            'flexible' => true,
        ],
        'tickets_ext' => [
            'label' => 'Tickets externos',
            'purpose' => 'Portal tickets externa (UI).',
            'congruent' => 'index, listados, ver ticket, notas.',
            'files' => ['tickets_externa/index.php', 'tickets_externa/mis_tickets.php', 'tickets_externa/ver_ticket.php', 'tickets_externa/crear_ticket.php'],
            'admin_url' => 'tickets_externa/index.php',
            'flexible' => true,
        ],
        'leads' => [
            'label' => 'Leads / CRM visual',
            'purpose' => 'Inbox, kanban y detalle de leads (UX).',
            'congruent' => 'Kanban, inbox, detalle, notas UI.',
            'files' => ['leads/index.php', 'leads/inbox.php', 'leads/kanban.php', 'leads/detalle.php', 'leads/agregar_lead.php'],
            'admin_url' => 'leads/index.php',
            'flexible' => true,
        ],
        'solicitudes' => [
            'label' => 'Solicitudes / proyectos',
            'purpose' => 'Tablero de solicitudes y productividad (UX).',
            'congruent' => 'index, estados visibles, notas UI.',
            'files' => ['solicitudes/index.php', 'solicitudes/actualizar.php', 'solicitudes/guardar.php', 'solicitudes/notas_listar.php'],
            'admin_url' => 'solicitudes/index.php',
            'flexible' => true,
        ],
        'cotizaciones' => [
            'label' => 'Cotizaciones',
            'purpose' => 'Listado y helpers visuales de cotizaciones.',
            'congruent' => 'index y helpers de presentación.',
            'files' => ['cotizaciones/index.php', 'cotizaciones/helpers_cotizacion.php'],
            'admin_url' => 'cotizaciones/index.php',
            'flexible' => true,
        ],
        'chat_live' => [
            'label' => 'Chat en vivo',
            'purpose' => 'UI del chat/whatsapp operativo del admin.',
            'congruent' => 'index chat, estadísticas, CSS live.',
            'files' => ['chat/index.php', 'chat/estadisticas.php', 'assets/css/chat-menu-live.css', 'whatsapp_conversacion.php'],
            'admin_url' => 'chat/index.php',
            'flexible' => true,
        ],
        'briefings' => [
            'label' => 'Briefings / proyectos web',
            'purpose' => 'Briefings y formularios de proyectos.',
            'congruent' => 'ver_briefings, formularios, toggles UI.',
            'files' => ['ver_briefings.php', 'formulario_proyectos.php', 'website/leads.php', 'website/levantamiento-ver.php'],
            'admin_url' => 'ver_briefings.php',
            'flexible' => true,
        ],
        'email' => [
            'label' => 'Plantillas de correo (diseño)',
            'purpose' => 'Diseño tipografía/colores/layout de correos.',
            'congruent' => 'Solo markup/estilo; no SMTP ni envío.',
            'files' => [
                'includes/adm_email_template.php',
                'includes/chat_email_template.php',
                'includes/pago_email_template.php',
                'includes/email_preview_samples.php',
                'email_preview.php',
                'shared/includes/cw_email_brand.php',
            ],
            'admin_url' => 'email_preview.php',
            'flexible' => true,
        ],
        'admin_css' => [
            'label' => 'CSS / JS plataforma',
            'purpose' => 'Estilos y JS de UI del panel completo.',
            'congruent' => 'CSS/JS propios del admin (no vendor minificado crítico).',
            'files' => [
                'css/admin-platform.css',
                'css/admin-datatables.css',
                'css/admin-consultas.css',
                'css/admin-registros.css',
                'css/tickets-vista.css',
                'assets/css/admin-sidebar.css',
                'js/admin-datatables-defaults.js',
                'analytics/css/seo-module.css',
            ],
            'admin_url' => 'index.php',
            'flexible' => true,
        ],
        'panel' => [
            'label' => 'Cualquier vista del panel',
            'purpose' => 'Editar código de cualquier vista/archivo permitido del admin según la solicitud.',
            'congruent' => 'Cambio puntual en el archivo que indique la petición, dentro de whitelist segura.',
            'files' => ['menu.php', 'index.php', 'css/admin-platform.css'],
            'admin_url' => 'index.php',
            'flexible' => true,
        ],
    ];
}

/**
 * Prefijos/áreas permitidas (panel completo). La seguridad real está en el deny.
 *
 * @return list<string>
 */
function cw_site_ai_admin_allowed_prefixes(): array
{
    return [
        'analytics/',
        'chat/',
        'leads/',
        'solicitudes/',
        'cotizaciones/',
        'tickets_externa/',
        'website/',
        'ajax/',
        'css/',
        'assets/css/',
        'assets/js/',
        'js/',
        'scss/',
        'includes/',
        'shared/includes/',
        // Vistas raíz frecuentes
        'menu.php',
        'index.php',
        'clientes.php',
        'detalle_cliente.php',
        'formulario_cliente.php',
        'nuevo_cliente.php',
        'dominios.php',
        'formulario_dominio.php',
        'crear_dominio.php',
        'hosting.php',
        'formulario_hosting.php',
        'hostpro_planes.php',
        'hostpro_caracteristicas.php',
        'planpro_planes.php',
        'pagos.php',
        'payment_success.php',
        'payment_success_grupal.php',
        'payment_success_preview.php',
        'payment_cancel.php',
        'tickets_vista.php',
        'tickets_public.php',
        'tickets_public_notas.php',
        'ver_briefings.php',
        'formulario_proyectos.php',
        'tablas.php',
        'email_preview.php',
        'whatsapp_conversacion.php',
        'actividad_inactividad.php',
        'servicios_filtro.php',
        'link_view.php',
        'link_helper.php',
        'plan_helper.php',
    ];
}

/**
 * @return list<string>
 */
function cw_site_ai_admin_deny_needles(): array
{
    return [
        'conn.php', 'conn_', 'conn.', '.env', 'secrets', 'credential',
        'auth_middleware', 'adm_local_auth', 'adm_session',
        'password', 'contrasena', 'cerrarSesion',
        'stripe', 'stripe-php', 'procesar_pago', 'guardar_pago', 'aprobar_pago',
        'eliminar_pago', 'acreditar_pago', 'generar_pago', 'restaurar_pago',
        'get_pago_data', 'cambiar_estatus_pago', 'actualizar_estatus_pago',
        'PHPMailer', 'smtp_config',
        'vendor/', 'cgi-bin/', 'tcpdf/', 'uploads/', 'constancias_fiscales/',
        'eliminar_', 'bootstrap_conexion',
        'validar-token', 'validacion-cliente', 'validate_session',
        'cw_seo_mexico_ai_constitution', 'cw_site_ai_brain',
        'cw_site_ai_admin.php', 'cw_site_ai_cliente.php',
        // núcleo IA / permisos (no autootorgarse poder)
        '/cw_seo_mexico_ai.php', 'includes/cw_seo_mexico_ai.php',
        'cw_hub_permissions', 'cw_hub_config',
        'chat/config.php',
    ];
}

function cw_site_ai_admin_path_denied(string $rel): bool
{
    $base = basename($rel);
    // Excepciones de diseño/UI relacionadas con pagos
    $pagoUiAllow = [
        'pagos.php',
        'payment_success.php',
        'payment_success_grupal.php',
        'payment_success_preview.php',
        'payment_cancel.php',
        'pago_email_template.php',
        'payment-success.css',
    ];
    if (in_array($base, $pagoUiAllow, true)) {
        return false;
    }

    foreach (cw_site_ai_admin_deny_needles() as $d) {
        if (stripos($rel, $d) !== false) {
            return true;
        }
    }
    // APIs destructivas / envío masivo sensibles
    if (preg_match('#(^|/)(api_enviar_|enviar_correo|cron_enviar|mandar_correos)#i', $rel)) {
        return true;
    }
    return false;
}

/**
 * Resuelve ruta virtual shared/ → includes compartido del monorepo.
 */
function cw_site_ai_admin_resolve_abs(string $relPath): ?string
{
    $rel = str_replace('\\', '/', ltrim($relPath, '/'));
    if ($rel === 'shared/includes/cw_email_brand.php' || $rel === 'includes/cw_email_brand.php') {
        $abs = realpath(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'cw_email_brand.php');
        return ($abs !== false && is_file($abs)) ? $abs : null;
    }
    $root = cw_site_ai_admin_root();
    if ($root === null) {
        return null;
    }
    $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    return is_file($abs) ? $abs : (is_readable($abs) ? $abs : null);
}

function cw_site_ai_admin_path_allowed(string $relPath): bool
{
    $rel = str_replace('\\', '/', ltrim($relPath, '/'));
    if ($rel === '' || str_contains($rel, '..')) {
        return false;
    }
    if (!preg_match('/\.(php|css|js|scss|html)$/i', $rel)) {
        return false;
    }
    // Evitar minificados vendor masivos
    if (preg_match('/\.(min\.js|min\.css)$/i', $rel) && str_contains($rel, 'sb-admin')) {
        return false;
    }
    if (cw_site_ai_admin_path_denied($rel)) {
        return false;
    }
    foreach (cw_site_ai_admin_allowed_prefixes() as $p) {
        if ($rel === rtrim($p, '/') || str_starts_with($rel, $p) || $rel === $p) {
            return true;
        }
    }
    // Cualquier .php/.css/.js de un solo segmento en raíz (vista del panel) si no está denied
    if (preg_match('#^[^/]+\.(php|css|js)$#i', $rel)) {
        return true;
    }
    return false;
}

/**
 * @return array{ok:bool,view?:array<string,mixed>,error?:string}
 */
function cw_site_ai_admin_resolve_view(string $viewKey): array
{
    $key = preg_replace('/[^a-z_]/', '', strtolower($viewKey)) ?? '';
    $catalog = cw_site_ai_admin_views_catalog();
    if ($key === '' || !isset($catalog[$key])) {
        return ['ok' => false, 'error' => 'Vista no válida. Usa: ' . implode(', ', array_keys($catalog))];
    }
    $view = $catalog[$key];
    $view['key'] = $key;
    return ['ok' => true, 'view' => $view];
}

/**
 * Propone una mejora congruente y fundamentada para una vista admin SEO.
 *
 * @return array{ok:bool,proposal_id?:int,after?:array,error?:string,message?:string}
 */
function cw_site_ai_admin_propose_view(
    mysqli $conn,
    string $viewKey,
    string $instruction = '',
    int $userId = 0,
    array $extraFiles = []
): array {
    $resolved = cw_site_ai_admin_resolve_view($viewKey);
    if (empty($resolved['ok'])) {
        return ['ok' => false, 'error' => (string) ($resolved['error'] ?? 'Vista inválida')];
    }
    /** @var array<string,mixed> $view */
    $view = $resolved['view'];
    foreach ($extraFiles as $ef) {
        $ef = str_replace('\\', '/', ltrim((string) $ef, '/'));
        if ($ef !== '' && cw_site_ai_admin_path_allowed($ef)) {
            array_unshift($view['files'], $ef);
        }
    }
    $view['files'] = array_values(array_unique(array_map(
        static fn ($f) => str_replace('\\', '/', (string) $f),
        (array) ($view['files'] ?? [])
    )));
    $instruction = trim($instruction);
    if ($instruction !== '' && mb_strlen($instruction) < 8) {
        return ['ok' => false, 'error' => 'Instrucción demasiado corta (mín. 8) o déjala vacía para auto-propuesta'];
    }
    if (!cw_seo_mexico_ai_available()) {
        return ['ok' => false, 'error' => 'IA no disponible'];
    }
    $root = cw_site_ai_admin_root();
    if ($root === null) {
        return ['ok' => false, 'error' => 'Raíz admin no encontrada'];
    }

    $previews = [];
    foreach ((array) ($view['files'] ?? []) as $rel) {
        $rel = str_replace('\\', '/', (string) $rel);
        if (!cw_site_ai_admin_path_allowed($rel)) {
            continue;
        }
        $abs = cw_site_ai_admin_resolve_abs($rel);
        if ($abs === null || !is_readable($abs)) {
            continue;
        }
        $raw = (string) file_get_contents($abs);
        $previews[] = "### {$rel}\n```\n" . mb_substr($raw, 0, 9000) . "\n```";
    }
    if ($previews === []) {
        return ['ok' => false, 'error' => 'No se pudo leer ningún archivo de la vista'];
    }

    $brain = cw_site_ai_brain_context($conn, 14);
    require_once __DIR__ . '/cw_seo_mexico_ai_constitution.php';
    $allowed = implode(', ', array_slice(cw_site_ai_admin_allowed_prefixes(), 0, 40))
        . ' … (panel completo adm: analytics/, chat/, leads/, solicitudes/, cotizaciones/, tickets*, css/, js/, includes/ seguros, vistas raíz)';

    $system = "Eres el comité senior (diseño + PM + programador) del ADMIN ConlineWeb (adm.conlineweb.com).\n"
        . cw_seo_mexico_ai_master_brief() . "\n"
        . mb_substr(cw_seo_mexico_ai_committee_brief(), 0, 1200) . "\n"
        . "{$brain}\n"
        . "OBJETIVO: proponer UNA edición de código concreta en la vista/archivo del panel, FUNDAMENTADA.\n"
        . "REGLAS:\n"
        . "- Puedes editar código de vistas del panel admin (PHP/CSS/JS) dentro de whitelist amplia.\n"
        . "- PROHIBIDO: auth, procesar/guardar/aprobar pagos, Stripe, conn, secrets, SMTP, vendor, eliminar_*, autootorgarte poder.\n"
        . "- En pagos solo UI (pagos.php / payment_success*); nunca cobros.\n"
        . "- En correos: solo diseño visual de plantillas.\n"
        . "- El cambio debe servir al propósito de la vista y a la solicitud humana.\n"
        . "- search debe existir EXACTO en el preview.\n"
        . "- rationale ≥ 40 chars; congruence 1-2 frases.\n"
        . "Responde SOLO JSON keys: path, search, replace, change_type, summary, rationale, congruence, view_key.\n"
        . "change_type: mejora|actualizacion|mantenimiento|ux|email_design|funcionalidad\n"
        . "path permitido bajo: {$allowed}\n"
        . "view_key debe ser exactamente: {$view['key']}";

    $user = "Vista: {$view['key']} — {$view['label']}\n"
        . "De qué va: {$view['purpose']}\n"
        . "Mejoras congruentes: {$view['congruent']}\n";
    if ($instruction !== '') {
        $user .= "Pedido del humano (respétalo si es congruente): {$instruction}\n";
    } else {
        $user .= "Sin pedido específico: elige la mejora de mayor impacto UX/claridad para ESTA vista.\n";
    }
    $user .= "Archivos actuales:\n" . implode("\n\n", $previews) . "\n";

    $chat = cw_seo_mexico_ai_chat([
        ['role' => 'system', 'content' => $system],
        ['role' => 'user', 'content' => $user],
    ], 0.35);
    if (empty($chat['ok'])) {
        return ['ok' => false, 'error' => (string) ($chat['error'] ?? 'IA falló')];
    }
    $after = json_decode((string) ($chat['content'] ?? ''), true);
    if (!is_array($after) || empty($after['path']) || empty($after['search']) || !isset($after['replace'])) {
        return ['ok' => false, 'error' => 'JSON de mejora admin incompleto', 'raw' => $chat['content'] ?? ''];
    }

    $path = str_replace('\\', '/', ltrim((string) $after['path'], '/'));
    if (!cw_site_ai_admin_path_allowed($path)) {
        return ['ok' => false, 'error' => 'Ruta fuera de whitelist admin: ' . $path];
    }
    $flexible = !empty($view['flexible']) || ($view['key'] ?? '') === 'panel';
    $viewFiles = array_map(
        static fn ($f) => str_replace('\\', '/', (string) $f),
        (array) ($view['files'] ?? [])
    );
    if (!$flexible && $viewFiles !== [] && !in_array($path, $viewFiles, true)) {
        $sharedOk = str_starts_with($path, 'analytics/css/')
            || str_starts_with($path, 'css/')
            || str_starts_with($path, 'assets/css/')
            || str_starts_with($path, 'js/')
            || $path === 'includes/seo_module_nav.php'
            || $path === 'shared/includes/cw_email_brand.php';
        if (!$sharedOk) {
            return ['ok' => false, 'error' => 'El archivo no pertenece a la vista «' . $view['key'] . '»'];
        }
    }

    $search = (string) $after['search'];
    $replace = (string) $after['replace'];
    if ($search === '' || $search === $replace) {
        return ['ok' => false, 'error' => 'search/replace inválidos'];
    }
    $abs = cw_site_ai_admin_resolve_abs($path);
    $beforeContent = ($abs !== null && is_readable($abs)) ? (string) file_get_contents($abs) : '';
    if ($beforeContent === '' || !str_contains($beforeContent, $search)) {
        return ['ok' => false, 'error' => 'El texto a reemplazar no existe en el archivo admin actual'];
    }

    $changeType = preg_replace('/[^a-z_]/', '', strtolower((string) ($after['change_type'] ?? 'mejora'))) ?: 'mejora';
    if (!in_array($changeType, ['mejora', 'actualizacion', 'mantenimiento', 'ux', 'improve', 'update', 'email_design', 'funcionalidad'], true)) {
        $changeType = 'mejora';
    }
    if ($changeType === 'improve' || $changeType === 'ux') {
        $changeType = 'mejora';
    }
    if ($changeType === 'update' || $changeType === 'funcionalidad') {
        $changeType = 'actualizacion';
    }

    $rationale = trim((string) ($after['rationale'] ?? ''));
    $congruence = trim((string) ($after['congruence'] ?? ''));
    $summary = trim((string) ($after['summary'] ?? $instruction));
    if ($summary === '') {
        $summary = 'Mejora UX en ' . (string) $view['label'];
    }
    if (mb_strlen($rationale) < 40) {
        $rationale = 'Mejora en la vista «' . $view['label'] . '» (' . $view['key'] . '): '
            . mb_substr($summary, 0, 180) . '. Alineada a: ' . mb_substr((string) $view['purpose'], 0, 160);
    }
    if ($congruence === '') {
        $congruence = 'El cambio refuerza el propósito de «' . $view['label'] . '»: '
            . mb_substr((string) $view['purpose'], 0, 180);
    }

    $adminUrl = 'https://adm.conlineweb.com/' . ltrim((string) ($view['admin_url'] ?? 'analytics/seo_mexico_monitor.php'), '/');
    $afterNorm = [
        'portal' => 'admin',
        'view_key' => (string) $view['key'],
        'view_label' => (string) $view['label'],
        'view_purpose' => (string) $view['purpose'],
        'path' => $path,
        'search' => $search,
        'replace' => $replace,
        'change_type' => $changeType,
        'summary' => $summary,
        'rationale' => $rationale,
        'congruence' => $congruence,
        'mode' => 'admin_patch',
        'url' => $adminUrl,
        'url_label' => 'Vista admin afectada',
        'url_kind' => 'page',
    ];
    $before = [
        'portal' => 'admin',
        'view_key' => (string) $view['key'],
        'view_label' => (string) $view['label'],
        'path' => $path,
        'snippet' => $search,
        'purpose' => (string) $view['purpose'],
    ];

    $detailExtra = "VISTA: {$view['label']} ({$view['key']})\n"
        . "PROPÓSITO: {$view['purpose']}\n"
        . "CONGRUENCIA: {$congruence}\n"
        . "FUNDAMENTO: {$rationale}\n"
        . "TIPO: {$changeType}\n"
        . "ARCHIVO: {$path}\n"
        . "URL ADMIN: {$adminUrl}\n";

    $saved = cw_seo_mexico_ai_save_proposal(
        $conn,
        'admin_patch',
        'Admin · ' . $view['label'] . ' · ' . mb_substr($summary, 0, 60),
        $path,
        $adminUrl,
        'Mejora vista admin «' . $view['key'] . '»: ' . mb_substr($instruction !== '' ? $instruction : $summary, 0, 160),
        $before,
        $afterNorm,
        $userId
    );
    if (empty($saved['ok'])) {
        return $saved;
    }

    // Ampliar detail con congruencia (si save ya escribió detail, actualizar)
    $pid = (int) ($saved['id'] ?? 0);
    if ($pid > 0 && function_exists('cw_seo_mexico_ai_proposals_ensure_table')) {
        $stmt = $conn->prepare('UPDATE cw_seo_mexico_ai_proposals SET detail = CONCAT(IFNULL(detail,\'\'), ?) WHERE id = ?');
        if ($stmt) {
            $extra = "\n\n" . $detailExtra;
            $stmt->bind_param('si', $extra, $pid);
            $stmt->execute();
            $stmt->close();
        }
    }

    return [
        'ok' => true,
        'proposal_id' => $pid,
        'after' => $afterNorm,
        'content_type' => 'fix',
        'portal' => 'admin',
        'message' => 'Propuesta de mejora admin lista (portal adm.conlineweb.com). Revísala en la cola antes de implementar.',
    ];
}

/**
 * Genera propuestas para varias vistas (máx. 4), una por vista.
 *
 * @param list<string> $viewKeys
 * @return array{ok:bool,created:list<array>,errors:list<string>,message?:string}
 */
function cw_site_ai_admin_propose_views_batch(
    mysqli $conn,
    array $viewKeys = [],
    string $instruction = '',
    int $userId = 0,
    int $max = 3
): array {
    $max = max(1, min(4, $max));
    $catalog = cw_site_ai_admin_views_catalog();
    if ($viewKeys === []) {
        $viewKeys = ['monitor', 'checklist', 'shell'];
    }
    $viewKeys = array_values(array_unique(array_filter(array_map(
        static fn ($k) => preg_replace('/[^a-z_]/', '', strtolower((string) $k)) ?? '',
        $viewKeys
    ))));
    $viewKeys = array_values(array_filter($viewKeys, static fn ($k) => isset($catalog[$k])));
    $viewKeys = array_slice($viewKeys, 0, $max);

    $created = [];
    $errors = [];
    foreach ($viewKeys as $vk) {
        $res = cw_site_ai_admin_propose_view($conn, $vk, $instruction, $userId);
        if (!empty($res['ok']) && !empty($res['proposal_id'])) {
            $created[] = [
                'view' => $vk,
                'proposal_id' => (int) $res['proposal_id'],
                'summary' => (string) (($res['after']['summary'] ?? '') ?: ''),
            ];
        } else {
            $errors[] = $vk . ': ' . (string) ($res['error'] ?? 'falló');
        }
    }

    return [
        'ok' => $created !== [],
        'created' => $created,
        'errors' => $errors,
        'message' => $created !== []
            ? ('Se crearon ' . count($created) . ' propuestas de vistas admin. Revísalas en la cola (portal adm.conlineweb.com).')
            : ('No se pudo crear ninguna propuesta. ' . implode(' · ', $errors)),
    ];
}

/**
 * Desde Chat/Monitor: interpreta la instrucción, elige vista/archivo admin y crea propuesta en cola.
 *
 * @return array{ok:bool,proposal_id?:int,after?:array,error?:string,message?:string,view_key?:string,path?:string}
 */
function cw_site_ai_admin_propose_from_instruction(
    mysqli $conn,
    string $instruction,
    int $userId = 0
): array {
    $instruction = trim($instruction);
    if (mb_strlen($instruction) < 8) {
        return ['ok' => false, 'error' => 'Indica qué mejorar en el admin (mín. 8 caracteres)'];
    }
    $catalog = cw_site_ai_admin_views_catalog();
    $viewKey = '';
    $forcePath = '';

    // Heurística rápida por módulo
    $low = mb_strtolower($instruction);
    $hints = [
        'monitor' => ['monitor', 'cola de propuesta', 'aprobar propuesta'],
        'checklist' => ['checklist', 'auditor', 'autofix'],
        'chat_ia' => ['chat ia', 'colabora', 'prompt ia', 'crear propuesta en la cola'],
        'external' => ['tarea externa', 'gsc', 'gbp'],
        'analytics' => ['funnel', 'reporte', 'analytics', 'sesiones'],
        'plazas' => ['plaza', 'geo local'],
        'urls' => ['urls seo', 'índice seo', 'indice seo'],
        'shell' => ['menú admin', 'menu admin', 'sidebar', 'inicio panel'],
        'clientes' => ['cliente', 'detalle_cliente', 'nuevo_cliente'],
        'dominios' => ['dominio'],
        'hosting' => ['hosting', 'hostpro', 'planpro'],
        'pagos_ui' => ['pantalla de pago', 'payment_success', 'listado de pagos'],
        'tickets' => ['ticket'],
        'tickets_ext' => ['ticket externa', 'tickets_externa'],
        'leads' => ['lead', 'kanban', 'inbox'],
        'solicitudes' => ['solicitud', 'requerimiento'],
        'cotizaciones' => ['cotizaci'],
        'chat_live' => ['whatsapp', 'chat en vivo', 'chat/'],
        'briefings' => ['briefing', 'levantamiento', 'proyecto web'],
        'email' => ['plantilla de correo', 'email_template', 'email_preview'],
        'admin_css' => ['css admin', 'datatable', 'admin-platform'],
        'panel' => ['cualquier vista', 'todo el panel', 'adm.conlineweb'],
    ];
    foreach ($hints as $vk => $words) {
        foreach ($words as $w) {
            if (str_contains($low, $w)) {
                $viewKey = $vk;
                break 2;
            }
        }
    }

    // Ruta explícita en el texto (ej. leads/kanban.php)
    if (preg_match('#([a-z0-9_\-/]+\.(?:php|css|js))#i', $instruction, $m)) {
        $candPath = str_replace('\\', '/', ltrim($m[1], '/'));
        if (cw_site_ai_admin_path_allowed($candPath)) {
            $forcePath = $candPath;
        }
    }

    if (($viewKey === '' || $forcePath === '') && cw_seo_mexico_ai_available()) {
        $keys = implode(', ', array_keys($catalog));
        $chat = cw_seo_mexico_ai_chat([
            [
                'role' => 'system',
                'content' => 'Eres router del panel ADMIN ConlineWeb (todas las vistas permitidas). '
                    . 'Responde SOLO JSON {"view_key":"","path":"","why":""}. '
                    . 'view_key uno de: ' . $keys . '. '
                    . 'path = archivo relativo exacto a editar (ej. clientes.php, leads/kanban.php, css/admin-platform.css). '
                    . 'Si no estás seguro del path, view_key=panel y path vacío. '
                    . 'Nunca path de auth/pagos procesar/conn/secrets.',
            ],
            ['role' => 'user', 'content' => $instruction],
        ], 0.15);
        if (!empty($chat['ok'])) {
            $route = json_decode((string) ($chat['content'] ?? ''), true);
            $cand = preg_replace('/[^a-z_]/', '', strtolower((string) ($route['view_key'] ?? ''))) ?? '';
            if ($cand !== '' && isset($catalog[$cand])) {
                $viewKey = $cand;
            }
            $p = str_replace('\\', '/', ltrim((string) ($route['path'] ?? ''), '/'));
            if ($p !== '' && cw_site_ai_admin_path_allowed($p)) {
                $forcePath = $p;
            }
        }
    }
    if ($viewKey === '' || !isset($catalog[$viewKey])) {
        $viewKey = $forcePath !== '' ? 'panel' : 'monitor';
    }

    $extra = $forcePath !== '' ? [$forcePath] : [];
    $res = cw_site_ai_admin_propose_view($conn, $viewKey, $instruction, $userId, $extra);
    if (!empty($res['ok'])) {
        $res['view_key'] = $viewKey;
        if ($forcePath !== '') {
            $res['path'] = $forcePath;
        }
        $res['message'] = 'Propuesta admin («' . $viewKey . '»'
            . ($forcePath !== '' ? ' → ' . $forcePath : '')
            . ') en cola. Revísala en Monitor antes de implementar.';
    }
    return $res;
}

/**
 * Aplica parche admin_patch con backup.
 *
 * @param array<string,mixed> $after
 * @return array{ok:bool,applied:bool,path?:string,backup?:string,error?:string}
 */
function cw_site_ai_admin_apply_patch(array $after, string $reason = ''): array
{
    $path = str_replace('\\', '/', ltrim((string) ($after['path'] ?? ''), '/'));
    $search = (string) ($after['search'] ?? '');
    $replace = (string) ($after['replace'] ?? '');
    if ($path === '' || $search === '') {
        return ['ok' => false, 'applied' => false, 'error' => 'Parche admin incompleto'];
    }
    if (!cw_site_ai_admin_path_allowed($path)) {
        return ['ok' => false, 'applied' => false, 'error' => 'Ruta admin no permitida'];
    }
    $root = cw_site_ai_admin_root();
    if ($root === null) {
        return ['ok' => false, 'applied' => false, 'error' => 'Raíz admin no encontrada'];
    }
    $abs = cw_site_ai_admin_resolve_abs($path);
    if ($abs === null || !is_file($abs) || !is_writable($abs)) {
        return ['ok' => false, 'applied' => false, 'error' => 'Archivo admin no escribible'];
    }
    $src = (string) file_get_contents($abs);
    if (!str_contains($src, $search)) {
        return ['ok' => false, 'applied' => false, 'error' => 'Fragmento search no encontrado al aplicar'];
    }
    $next = str_replace($search, $replace, $src);
    $bakDir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'autofix_backups';
    if (!is_dir($bakDir)) {
        @mkdir($bakDir, 0755, true);
    }
    $bak = $bakDir . DIRECTORY_SEPARATOR . date('Ymd_His') . '__admin__' . preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $path) . '.bak';
    if (@file_put_contents($bak, $src) === false) {
        return ['ok' => false, 'applied' => false, 'error' => 'No se pudo crear backup'];
    }
    if (@file_put_contents($abs, $next) === false) {
        return ['ok' => false, 'applied' => false, 'error' => 'No se pudo escribir archivo admin', 'backup' => $bak];
    }
    if (preg_match('/\.php$/i', $abs) && function_exists('cw_seo_mexico_autofix_php_lint')) {
        $lint = cw_seo_mexico_autofix_php_lint($abs);
        if (empty($lint['ok'])) {
            @file_put_contents($abs, $src);
            return [
                'ok' => false,
                'applied' => false,
                'error' => 'Lint PHP falló; se restauró backup. ' . (string) ($lint['output'] ?? ''),
                'backup' => $bak,
            ];
        }
    }
    return [
        'ok' => true,
        'applied' => true,
        'path' => $path,
        'backup' => $bak,
        'reason' => $reason,
    ];
}
