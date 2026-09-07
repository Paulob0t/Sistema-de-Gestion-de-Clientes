<?php
/**
 * Propuestas de mejora UX sobre el portal cliente (cliente.conlineweb.com).
 * Misma filosofía que admin SEO: whitelist · fundamento · congruencia · approve → apply.
 */
declare(strict_types=1);

require_once __DIR__ . '/cw_seo_mexico_ai.php';
require_once __DIR__ . '/cw_site_ai_brain.php';

function cw_site_ai_cliente_root(): ?string
{
    $candidates = [
        dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'cliente.conlineweb.com',
        dirname(__DIR__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'cliente.conlineweb.com',
    ];
    foreach ($candidates as $path) {
        $real = realpath($path);
        if ($real !== false && is_dir($real) && is_file($real . DIRECTORY_SEPARATOR . 'index.php')) {
            return $real;
        }
    }
    return null;
}

/**
 * @return array<string, array<string,mixed>>
 */
function cw_site_ai_cliente_views_catalog(): array
{
    return [
        'shell' => [
            'label' => 'Shell / navegación',
            'purpose' => 'Layouts, menú y pie del portal: orientación del cliente entre secciones sin confundir ni romper sesión.',
            'congruent' => 'Labels de menú, títulos de layout, footer, claridad de navegación, microcopy de cabecera.',
            'files' => [
                'layouts/master.html',
                'layouts/sidebar.html',
                'layouts/vertical-navbar.html',
                'menu.php',
                'footer.php',
                'footer2.php',
            ],
            'cliente_url' => 'https://cliente.conlineweb.com/',
        ],
        'css' => [
            'label' => 'Estilos del portal',
            'purpose' => 'CSS propio del portal (no vendors): legibilidad, jerarquía visual y consistencia UI.',
            'congruent' => 'Ajustes de CSS del portal, espaciado, tipografía existente, sin reinventar el tema completo ni tocar vendor.',
            'files' => [
                'assets/css/',
            ],
            'cliente_url' => 'https://cliente.conlineweb.com/',
        ],
        'tickets' => [
            'label' => 'Tickets / soporte',
            'purpose' => 'Que el cliente abra y siga tickets con textos claros (estados, acciones, vacíos).',
            'congruent' => 'Labels, vacíos, botones, ayudas de tickets. No lógica de envío ni APIs internas.',
            'files' => [
                'tickets.php',
            ],
            'cliente_url' => 'https://cliente.conlineweb.com/tickets.php',
        ],
        'sitios' => [
            'label' => 'Mis sitios',
            'purpose' => 'Listar y entender sitios/servicios del cliente con copy claro.',
            'congruent' => 'Títulos, vacíos, CTAs de “Mis sitios”, sin tocar datos ni acciones destructivas.',
            'files' => [
                'mis-sitios.php',
            ],
            'cliente_url' => 'https://cliente.conlineweb.com/mis-sitios.php',
        ],
        'hosting' => [
            'label' => 'Hosting',
            'purpose' => 'Vista de hosting: qué tiene contratado y qué puede hacer, en lenguaje claro.',
            'congruent' => 'Microcopy de listados/estados de hosting, sin DNS ni procesos de cambio.',
            'files' => [
                'hosting.php',
            ],
            'cliente_url' => 'https://cliente.conlineweb.com/hosting.php',
        ],
        'dominios' => [
            'label' => 'Dominios',
            'purpose' => 'Vista de dominios: listado y orientación (no transferencia ni DNS).',
            'congruent' => 'Labels y ayudas de la lista de dominios. Prohibido tocar transferencia/DNS/pagos.',
            'files' => [
                'dominios.php',
            ],
            'cliente_url' => 'https://cliente.conlineweb.com/dominios.php',
        ],
        'productos' => [
            'label' => 'Productos / servicios',
            'purpose' => 'Catálogo o listado de productos/servicios visibles al cliente.',
            'congruent' => 'Copy de presentación de productos, sin checkout ni Stripe.',
            'files' => [
                'productos.php',
            ],
            'cliente_url' => 'https://cliente.conlineweb.com/productos.php',
        ],
        'home' => [
            'label' => 'Inicio portal',
            'purpose' => 'Primera pantalla tras entrar: orientación clara a las secciones útiles del cliente.',
            'congruent' => 'Títulos, CTAs y claridad del index (sin tocar login/auth).',
            'files' => [
                'index.php',
            ],
            'cliente_url' => 'https://cliente.conlineweb.com/',
        ],
        'email' => [
            'label' => 'Plantilla de correo (diseño)',
            'purpose' => 'Mejorar tipografía, colores, layout y marca visual de correos al cliente. Solo diseño.',
            'congruent' => 'Markup/estilo de cliente_email_template. Prohibido SMTP, reset password logic, tokens.',
            'files' => [
                'includes/cliente_email_template.php',
            ],
            'cliente_url' => 'https://cliente.conlineweb.com/',
        ],
    ];
}

/**
 * @return list<string>
 */
function cw_site_ai_cliente_allowed_prefixes(): array
{
    return [
        'layouts/',
        'assets/css/',
        'menu.php',
        'footer.php',
        'footer2.php',
        'index.php',
        'tickets.php',
        'mis-sitios.php',
        'hosting.php',
        'dominios.php',
        'productos.php',
        'includes/cliente_email_template.php',
    ];
}

function cw_site_ai_cliente_path_allowed(string $relPath): bool
{
    $rel = str_replace('\\', '/', ltrim($relPath, '/'));
    if ($rel === '' || str_contains($rel, '..')) {
        return false;
    }
    $deny = [
        'conn.php', '.env', 'secrets', 'sesion', 'iniciar', 'ingreso',
        'pagos', 'stripe', 'factura', 'constancia', 'upload', 'token',
        'dns', 'transferencia', 'procesar_pago', 'PHPMailer', 'vendor',
        'vendors/', 'cgi-bin/', 'actulizacion_clientes', 'actulizar_dns',
        'reset_password', 'reset_request', 'validate_session', 'cerrarSesion',
        'cliente.php', 'mail_test', 'password',
    ];
    // Permitir plantilla email aunque el path no tenga "password"; bloquear resets
    if (stripos($rel, 'password') !== false && stripos($rel, 'cliente_email_template') === false) {
        return false;
    }
    foreach ($deny as $d) {
        if ($d === 'password') {
            continue; // ya manejado arriba
        }
        if (stripos($rel, $d) !== false) {
            return false;
        }
    }
    foreach (cw_site_ai_cliente_allowed_prefixes() as $p) {
        if ($rel === rtrim($p, '/') || str_starts_with($rel, $p)) {
            return true;
        }
    }
    return false;
}

/**
 * @return array{ok:bool,view?:array<string,mixed>,error?:string}
 */
function cw_site_ai_cliente_resolve_view(string $viewKey): array
{
    $key = preg_replace('/[^a-z_]/', '', strtolower($viewKey)) ?? '';
    $catalog = cw_site_ai_cliente_views_catalog();
    if ($key === '' || !isset($catalog[$key])) {
        return ['ok' => false, 'error' => 'Vista no válida. Usa: ' . implode(', ', array_keys($catalog))];
    }
    $view = $catalog[$key];
    $view['key'] = $key;
    return ['ok' => true, 'view' => $view];
}

/**
 * @return list<array{path:string,preview:string}>
 */
function cw_site_ai_cliente_collect_previews(string $root, array $files, int $maxFiles = 6): array
{
    $out = [];
    foreach ($files as $rel) {
        $rel = str_replace('\\', '/', (string) $rel);
        if (!cw_site_ai_cliente_path_allowed($rel)) {
            continue;
        }
        $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (is_dir($abs)) {
            $css = glob($abs . DIRECTORY_SEPARATOR . '*.css') ?: [];
            foreach (array_slice($css, 0, 4) as $cssFile) {
                $relCss = $rel . basename((string) $cssFile);
                if (!cw_site_ai_cliente_path_allowed($relCss)) {
                    continue;
                }
                $raw = (string) file_get_contents((string) $cssFile);
                $out[] = ['path' => $relCss, 'preview' => mb_substr($raw, 0, 8000)];
                if (count($out) >= $maxFiles) {
                    return $out;
                }
            }
            continue;
        }
        if (!is_readable($abs)) {
            continue;
        }
        $out[] = [
            'path' => $rel,
            'preview' => mb_substr((string) file_get_contents($abs), 0, 9000),
        ];
        if (count($out) >= $maxFiles) {
            break;
        }
    }
    return $out;
}

/**
 * @return array{ok:bool,proposal_id?:int,after?:array,error?:string,message?:string}
 */
function cw_site_ai_cliente_propose_view(
    mysqli $conn,
    string $viewKey,
    string $instruction = '',
    int $userId = 0
): array {
    $resolved = cw_site_ai_cliente_resolve_view($viewKey);
    if (empty($resolved['ok'])) {
        return ['ok' => false, 'error' => (string) ($resolved['error'] ?? 'Vista inválida')];
    }
    /** @var array<string,mixed> $view */
    $view = $resolved['view'];
    $instruction = trim($instruction);
    if ($instruction !== '' && mb_strlen($instruction) < 8) {
        return ['ok' => false, 'error' => 'Instrucción demasiado corta (mín. 8) o déjala vacía'];
    }
    if (!cw_seo_mexico_ai_available()) {
        return ['ok' => false, 'error' => 'IA no disponible'];
    }
    $root = cw_site_ai_cliente_root();
    if ($root === null) {
        return ['ok' => false, 'error' => 'Raíz cliente.conlineweb.com no encontrada'];
    }

    $previews = cw_site_ai_cliente_collect_previews($root, (array) ($view['files'] ?? []));
    if ($previews === []) {
        return ['ok' => false, 'error' => 'No se pudo leer ningún archivo de la vista cliente'];
    }

    $brain = cw_site_ai_brain_context($conn, 12);
    require_once __DIR__ . '/cw_seo_mexico_ai_constitution.php';
    $allowed = implode(', ', cw_site_ai_cliente_allowed_prefixes());
    $previewBlocks = [];
    foreach ($previews as $p) {
        $previewBlocks[] = "### {$p['path']}\n```\n{$p['preview']}\n```";
    }

    $system = "Eres el comité senior (diseño + PM + programador) del PORTAL CLIENTE ConlineWeb (cliente.conlineweb.com).\n"
        . cw_seo_mexico_ai_master_brief() . "\n"
        . mb_substr(cw_seo_mexico_ai_committee_brief(), 0, 1200) . "\n"
        . "{$brain}\n"
        . "OBJETIVO: UNA mejora concreta, congruente con el propósito de la vista y FUNDAMENTADA.\n"
        . "REGLAS:\n"
        . "- Solo whitelist UX/diseño del portal cliente.\n"
        . "- NUNCA tocar auth, pagos, facturas, DNS, transferencias, uploads, secrets, conn, SMTP.\n"
        . "- Mantén el look actual del portal; evoluciona copy/claridad/tipografía/colores.\n"
        . "- Si vista email: SOLO diseño visual de la plantilla.\n"
        . "- search debe existir EXACTO en el preview.\n"
        . "- rationale ≥ 40 chars; congruence explica por qué encaja en la vista.\n"
        . "Responde SOLO JSON: path, search, replace, change_type, summary, rationale, congruence, view_key.\n"
        . "change_type: mejora|actualizacion|mantenimiento|ux|email_design\n"
        . "path bajo: {$allowed}\n"
        . "view_key exactamente: {$view['key']}";

    $user = "Vista: {$view['key']} — {$view['label']}\n"
        . "De qué va: {$view['purpose']}\n"
        . "Mejoras congruentes: {$view['congruent']}\n";
    if ($instruction !== '') {
        $user .= "Pedido humano: {$instruction}\n";
    } else {
        $user .= "Sin pedido: elige la mejora UX de mayor claridad para ESTA vista.\n";
    }
    $user .= "Archivos:\n" . implode("\n\n", $previewBlocks) . "\n";

    $chat = cw_seo_mexico_ai_chat([
        ['role' => 'system', 'content' => $system],
        ['role' => 'user', 'content' => $user],
    ], 0.35);
    if (empty($chat['ok'])) {
        return ['ok' => false, 'error' => (string) ($chat['error'] ?? 'IA falló')];
    }
    $after = json_decode((string) ($chat['content'] ?? ''), true);
    if (!is_array($after) || empty($after['path']) || empty($after['search']) || !isset($after['replace'])) {
        return ['ok' => false, 'error' => 'JSON de mejora cliente incompleto', 'raw' => $chat['content'] ?? ''];
    }

    $path = str_replace('\\', '/', ltrim((string) $after['path'], '/'));
    if (!cw_site_ai_cliente_path_allowed($path)) {
        return ['ok' => false, 'error' => 'Ruta fuera de whitelist cliente: ' . $path];
    }

    $search = (string) $after['search'];
    $replace = (string) $after['replace'];
    if ($search === '' || $search === $replace) {
        return ['ok' => false, 'error' => 'search/replace inválidos'];
    }
    $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    $beforeContent = is_readable($abs) ? (string) file_get_contents($abs) : '';
    if ($beforeContent === '' || !str_contains($beforeContent, $search)) {
        return ['ok' => false, 'error' => 'El texto a reemplazar no existe en el archivo cliente actual'];
    }

    $changeType = preg_replace('/[^a-z_]/', '', strtolower((string) ($after['change_type'] ?? 'mejora'))) ?: 'mejora';
    if ($changeType === 'improve' || $changeType === 'ux') {
        $changeType = 'mejora';
    }
    if ($changeType === 'update') {
        $changeType = 'actualizacion';
    }
    if (!in_array($changeType, ['mejora', 'actualizacion', 'mantenimiento', 'email_design'], true)) {
        $changeType = 'mejora';
    }

    $rationale = trim((string) ($after['rationale'] ?? ''));
    $congruence = trim((string) ($after['congruence'] ?? ''));
    $summary = trim((string) ($after['summary'] ?? $instruction));
    if ($summary === '') {
        $summary = 'Mejora UX en ' . (string) $view['label'];
    }
    if (mb_strlen($rationale) < 40) {
        $rationale = 'Mejora en portal cliente «' . $view['label'] . '»: '
            . mb_substr($summary, 0, 180) . '. Alineada a: ' . mb_substr((string) $view['purpose'], 0, 160);
    }
    if ($congruence === '') {
        $congruence = 'Refuerza el propósito de «' . $view['label'] . '»: '
            . mb_substr((string) $view['purpose'], 0, 180);
    }

    $clienteUrl = (string) ($view['cliente_url'] ?? 'https://cliente.conlineweb.com/');
    $afterNorm = [
        'portal' => 'cliente',
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
        'mode' => 'cliente_patch',
        'url' => $clienteUrl,
        'url_label' => 'Vista portal cliente',
        'url_kind' => 'page',
    ];
    $before = [
        'portal' => 'cliente',
        'view_key' => (string) $view['key'],
        'view_label' => (string) $view['label'],
        'path' => $path,
        'snippet' => $search,
        'purpose' => (string) $view['purpose'],
    ];

    $detailExtra = "PORTAL: cliente.conlineweb.com\n"
        . "VISTA: {$view['label']} ({$view['key']})\n"
        . "PROPÓSITO: {$view['purpose']}\n"
        . "CONGRUENCIA: {$congruence}\n"
        . "FUNDAMENTO: {$rationale}\n"
        . "TIPO: {$changeType}\n"
        . "ARCHIVO: {$path}\n"
        . "URL: {$clienteUrl}\n";

    $saved = cw_seo_mexico_ai_save_proposal(
        $conn,
        'cliente_patch',
        'Cliente · ' . $view['label'] . ' · ' . mb_substr($summary, 0, 60),
        $path,
        $clienteUrl,
        'Mejora portal cliente «' . $view['key'] . '»: ' . mb_substr($instruction !== '' ? $instruction : $summary, 0, 160),
        $before,
        $afterNorm,
        $userId
    );
    if (empty($saved['ok'])) {
        return $saved;
    }
    $pid = (int) ($saved['id'] ?? 0);
    if ($pid > 0) {
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
        'portal' => 'cliente',
        'message' => 'Propuesta portal cliente lista (portal cliente.conlineweb.com). Revísala en la cola antes de implementar.',
    ];
}

/**
 * @param list<string> $viewKeys
 * @return array{ok:bool,created:list<array>,errors:list<string>,message?:string}
 */
function cw_site_ai_cliente_propose_views_batch(
    mysqli $conn,
    array $viewKeys = [],
    string $instruction = '',
    int $userId = 0,
    int $max = 3
): array {
    $max = max(1, min(4, $max));
    $catalog = cw_site_ai_cliente_views_catalog();
    if ($viewKeys === []) {
        $viewKeys = ['shell', 'tickets', 'sitios'];
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
        $res = cw_site_ai_cliente_propose_view($conn, $vk, $instruction, $userId);
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
            ? ('Se crearon ' . count($created) . ' propuestas del portal cliente. Revísalas (portal cliente.conlineweb.com).')
            : ('No se pudo crear ninguna propuesta. ' . implode(' · ', $errors)),
    ];
}

/**
 * @param array<string,mixed> $after
 * @return array{ok:bool,applied:bool,path?:string,backup?:string,error?:string}
 */
function cw_site_ai_cliente_apply_patch(array $after, string $reason = ''): array
{
    $path = str_replace('\\', '/', ltrim((string) ($after['path'] ?? ''), '/'));
    $search = (string) ($after['search'] ?? '');
    $replace = (string) ($after['replace'] ?? '');
    if ($path === '' || $search === '') {
        return ['ok' => false, 'applied' => false, 'error' => 'Parche cliente incompleto'];
    }
    if (!cw_site_ai_cliente_path_allowed($path)) {
        return ['ok' => false, 'applied' => false, 'error' => 'Ruta cliente no permitida'];
    }
    $root = cw_site_ai_cliente_root();
    if ($root === null) {
        return ['ok' => false, 'applied' => false, 'error' => 'Raíz cliente no encontrada'];
    }
    $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    if (!is_file($abs) || !is_writable($abs)) {
        return ['ok' => false, 'applied' => false, 'error' => 'Archivo cliente no escribible'];
    }
    $src = (string) file_get_contents($abs);
    if (!str_contains($src, $search)) {
        return ['ok' => false, 'applied' => false, 'error' => 'Fragmento search no encontrado al aplicar'];
    }
    $next = str_replace($search, $replace, $src);
    $bakDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'autofix_backups';
    if (!is_dir($bakDir)) {
        @mkdir($bakDir, 0755, true);
    }
    $bak = $bakDir . DIRECTORY_SEPARATOR . date('Ymd_His') . '__cliente__' . preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $path) . '.bak';
    if (@file_put_contents($bak, $src) === false) {
        return ['ok' => false, 'applied' => false, 'error' => 'No se pudo crear backup'];
    }
    if (@file_put_contents($abs, $next) === false) {
        return ['ok' => false, 'applied' => false, 'error' => 'No se pudo escribir archivo cliente', 'backup' => $bak];
    }
    if (preg_match('/\.php$/i', $abs) && function_exists('cw_seo_mexico_autofix_php_lint')) {
        $lint = cw_seo_mexico_autofix_php_lint($abs);
        if (empty($lint['ok'])) {
            @file_put_contents($abs, $src);
            return [
                'ok' => false,
                'applied' => false,
                'error' => 'Lint PHP falló; se restauró. ' . (string) ($lint['output'] ?? ''),
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
