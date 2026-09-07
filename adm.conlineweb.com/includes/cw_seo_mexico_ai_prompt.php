<?php
/**
 * Módulo de prompts interactivos con IA dentro del entorno adm + sitio.
 * Chat con contexto del sistema; puede generar borradores que se guardan como propuestas.
 */
require_once __DIR__ . '/cw_seo_mexico_ai.php';
require_once __DIR__ . '/cw_seo_mexico_checklist.php';
require_once __DIR__ . '/cw_seo_mexico_autofix.php';

function cw_seo_mexico_ai_prompt_ensure_tables(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS cw_seo_mexico_ai_prompt_sessions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL DEFAULT 'Nueva conversación',
            mode VARCHAR(40) NOT NULL DEFAULT 'ask',
            created_by INT UNSIGNED DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_updated (updated_at),
            KEY idx_user (created_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $conn->query(
        "CREATE TABLE IF NOT EXISTS cw_seo_mexico_ai_prompt_messages (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            session_id BIGINT UNSIGNED NOT NULL,
            role VARCHAR(20) NOT NULL,
            content LONGTEXT NOT NULL,
            meta_json LONGTEXT,
            created_at DATETIME NOT NULL,
            KEY idx_session (session_id),
            KEY idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

/**
 * Contexto del entorno que se inyecta al system prompt.
 */
function cw_seo_mexico_ai_prompt_env_context(?mysqli $conn = null): string
{
    $st = function_exists('cw_seo_mexico_ai_status') ? cw_seo_mexico_ai_status() : [];
    $root = function_exists('cw_seo_mexico_autofix_site_root') ? cw_seo_mexico_autofix_site_root() : null;
    $plazas = [];
    foreach (cw_seo_mexico_priority_plazas() as $p) {
        $plazas[] = ($p['city'] ?? '') . ' (' . ($p['slug'] ?? '') . ')';
    }
    $napBits = '';
    if ($root) {
        $napFile = $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'cw-nap.php';
        if (is_file($napFile)) {
            require_once $napFile;
            if (function_exists('cw_nap')) {
                $n = cw_nap();
                $napBits = ($n['name'] ?? '') . ' · ' . ($n['address_full'] ?? '') . ' · '
                    . ($n['phone_primary_display'] ?? '') . ' · ' . ($n['email'] ?? '');
            }
        }
    }

    $dirCtx = '';
    $syncFile = __DIR__ . '/cw_seo_mexico_directory_sync.php';
    if (is_readable($syncFile)) {
        require_once $syncFile;
        if (function_exists('cw_seo_mexico_directory_ai_context')) {
            $dirCtx = cw_seo_mexico_directory_ai_context();
        }
    }

    $lines = [
        'Entorno: admin ConlineWeb (adm.conlineweb.com) + sitio público conlineweb.com en el mismo servidor (cPanel/XAMPP).',
        'Modelo IA: ' . cw_seo_mexico_ai_model() . ' · estado: ' . ($st['label'] ?? 'desconocido'),
        'Raíz sitio local: ' . ($root ?: 'no detectada'),
        'Sitio escribible: ' . (!empty($st['site_writable']) ? 'sí' : 'no'),
        'Plazas prioridad: ' . implode(', ', $plazas),
        'NAP canónico: ' . ($napBits !== '' ? $napBits : 'includes/cw-nap.php'),
        'Rol: eres el CEREBRO y el MÚSCULO del sitio. Objetivo primordial: MASTER SEO y GEO + mejor web del giro en México.',
        'Módulos: Colaboración IA, Monitor IA, checklist, AutoFix, autonomía, sitemaps/LLMs/índice.',
        'Regla dura: NUNCA inventes que ya escribiste archivos. Los cambios requieren propuesta aprobada e «Implementar».',
        'Puedes y DEBES: analizar páginas concretas, corregir textos, ampliar copy, cambiar información, '
        . 'proponer diseño con preview, mantenimiento de landings, hubs, blogs y mejoras admin seguras. '
        . 'El usuario formaliza con «Crear propuesta en la cola»; tú no digas que no puedes generar solicitudes.',
        'Marca: ConlineWeb. Español México. Sin emojis. Sin datos falsos de clientes o precios inventados.',
    ];
    require_once __DIR__ . '/cw_seo_mexico_ai_constitution.php';
    $lines[] = cw_seo_mexico_ai_master_brief();
    $lines[] = cw_seo_mexico_ai_primary_objectives();
    if ($dirCtx !== '') {
        $lines[] = $dirCtx;
    }
    if ($conn instanceof mysqli) {
        require_once __DIR__ . '/cw_site_ai_brain.php';
        $lines[] = cw_site_ai_brain_context($conn, 20);
    }
    return implode("\n", $lines);
}

/**
 * @return list<array{id:string,label:string,mode:string,prompt:string}>
 */
function cw_seo_mexico_ai_prompt_presets(): array
{
    return [
        [
            'id' => 'seo_priorities',
            'label' => 'Prioridades SEO México',
            'mode' => 'ask',
            'prompt' => 'Con el contexto del sistema ConlineWeb, dame las 5 prioridades SEO locales más importantes para las plazas actuales y qué conviene hacer esta semana en código vs tareas externas.',
        ],
        [
            'id' => 'hub_ideas',
            'label' => 'Ideas copy hub León',
            'mode' => 'draft_page',
            'prompt' => 'Propón mejora de title, meta description, h1 y hero_subtitle para el hub de León (slug leon). Responde JSON con keys: title, description, h1, hero_subtitle, rationale.',
        ],
        [
            'id' => 'blog_outline',
            'label' => 'Outline blog SEO local',
            'mode' => 'draft_blog',
            'prompt' => 'Genera un artículo de blog SEO local para PyMEs en México. Tema: cómo aparecer en Google Maps y búsqueda local. Responde JSON con slug, title, excerpt, category, keyword, html, rationale.',
        ],
        [
            'id' => 'explain_nap',
            'label' => 'Explicar NAP del sistema',
            'mode' => 'ask',
            'prompt' => 'Explica qué es el NAP en este proyecto, dónde vive (cw-nap.php) y qué archivos deben consumirlo. Sé concreto con rutas del repo.',
        ],
        [
            'id' => 'autofix_safe',
            'label' => 'Qué puede AutoFix',
            'mode' => 'ask',
            'prompt' => 'Resume qué correcciones puede aplicar AutoFix de forma segura en este sistema y qué queda siempre como revisión manual o propuesta IA.',
        ],
        [
            'id' => 'maintain_hero',
            'label' => 'Mantenimiento · copy hero',
            'mode' => 'maintain',
            'prompt' => 'Como cerebro del sitio, sugiere mejoras de copy e iconografía para la homepage o una landing clave, priorizando conversión PyME México. Lista cambios concretos y archivos probables.',
        ],
        [
            'id' => 'design_hero_preview',
            'label' => 'Diseño · preview hero',
            'mode' => 'design',
            'prompt' => 'Propón un rediseño del hero de una landing clave ConlineWeb (p.ej. León o home) alineado al design system dark cyber/tech. Incluye títulos, subtítulo, cards de servicios y CTAs. Debe generarse con preview visual antes de implementar.',
        ],
        [
            'id' => 'admin_monitor_ux',
            'label' => 'Admin · mejorar Monitor',
            'mode' => 'admin',
            'prompt' => 'Propón una mejora concreta de UX en la cola de propuestas del admin (botones, labels, claridad de Detalle/Aprobar). Debe quedar como propuesta en la cola, no aplicar sola.',
        ],
        [
            'id' => 'admin_validate_recent',
            'label' => 'Validar updates admin',
            'mode' => 'validate',
            'prompt' => 'Valida las últimas actualizaciones aplicadas en adm.conlineweb.com: comprueba si los cambios quedaron bien en los archivos y genera el informe en la cola de propuestas.',
        ],
        [
            'id' => 'review_software_page',
            'label' => 'Revisar Desarrollo de Software',
            'mode' => 'maintain',
            'prompt' => 'Revisa la página https://conlineweb.com/desarrollo-de-software/ (archivo desarrollo-de-software.php): analiza títulos, meta, hero, claridad del proceso y CTAs. Propón correcciones de texto y mejoras concretas listas para encolar.',
        ],
        [
            'id' => 'expand_empresas_copy',
            'label' => 'Ampliar copy Software Empresas',
            'mode' => 'maintain',
            'prompt' => 'En software-para-empresas.php, propone ampliar/clarificar el copy del hero y una sección de beneficios sin repetir clichés. Cambios concretos de texto para propuesta en cola.',
        ],
        [
            'id' => 'design_landing_align',
            'label' => 'Diseño · alinear landing',
            'mode' => 'design',
            'prompt' => 'Propón un diseño (con preview) para desarrollo-de-software.php alineado visualmente a software-para-empresas.php, con contenido propio del proceso de desarrollo.',
        ],
    ];
}

/**
 * @return list<string>
 */
function cw_seo_mexico_ai_prompt_allowed_modes(): array
{
    return ['ask', 'draft_page', 'draft_blog', 'ops', 'maintain', 'design', 'admin', 'validate', 'brain'];
}

function cw_seo_mexico_ai_prompt_mode_system(string $mode, ?mysqli $conn = null): string
{
    require_once __DIR__ . '/cw_seo_mexico_ai_constitution.php';
    $base = "Eres el cerebro y músculo operativo de ConlineWeb dentro del panel adm.\n"
        . cw_seo_mexico_ai_master_brief() . "\n"
        . mb_substr(cw_seo_mexico_ai_domain_mastery_brief(), 0, 1100) . "\n"
        . mb_substr(cw_seo_mexico_ai_committee_brief(), 0, 700) . "\n"
        . cw_seo_mexico_ai_seo_geo_playbook() . "\n"
        . "REGLA DE ORO: en cada respuesta y propuesta aplica SIEMPRE las mejores técnicas SEO y GEO "
        . "(intención, title/meta/H1, E-E-A-T, local México, schema, internos, mobile). "
        . "Nunca entregues copy o diseño genérico sin fundamento SEO/GEO.\n"
        . "Ayudas a desarrollo web, sistemas, SEO/GEO, contenidos, blogs, hubs, diseño, mantenimiento, "
        . "soporte, actualizaciones, mejoras del ADMIN/cliente y validaciones "
        . "con mejores prácticas para Google y la web moderna.\n"
        . "Contexto del entorno:\n" . cw_seo_mexico_ai_prompt_env_context($conn) . "\n";

    if ($mode === 'brain') {
        require_once __DIR__ . '/cw_site_ai_brain.php';
        $brainCtx = $conn ? cw_site_ai_brain_context($conn, 16) : '';
        return $base
            . "Modo: CEREBRO FLOTANTE (voz + chat del administrador).\n"
            . "Tono OBLIGATORIO: humano, amable, cercano y claro (español México). "
            . "Habla como un compañero experto que ayuda, sin sonar robótico ni con emojis.\n"
            . "Usa la base de conocimiento del sitio. Puedes atender CUALQUIER orden del ecosistema "
            . "(SEO/GEO, diseño, mantenimiento, blogs, hubs, admin, validación, externos GSC/GBP).\n"
            . "Si la orden requiere cambio en el sitio: explica el plan y di que pulse "
            . "«Ejecutar orden» (o que diga «ejecuta / crea propuesta») para formalizarla en Monitor. "
            . "Nunca digas que ya publicaste. Nunca digas que no puedes generar solicitudes.\n"
            . "Respuestas preferentemente breves y hablables (2–6 frases + bullets si hace falta).\n"
            . ($brainCtx !== '' ? "\n{$brainCtx}\n" : '');
    }

    return match ($mode) {
        'draft_page' => $base
            . "Modo: borrador de PÁGINA/hub. Responde SOLO JSON válido con keys: "
            . "title, description, h1, hero_subtitle, rationale, city_slug (si aplica).",
        'draft_blog' => $base
            . cw_seo_mexico_ai_blog_editorial_brief() . ' '
            . "Modo: borrador de BLOG. Responde SOLO JSON válido con keys: "
            . "slug, title, excerpt, category, keyword, html, rationale. "
            . "category DEBE ser una existente del blog: desarrollo-web, seo, ecommerce, software, inteligencia-artificial, marketing-digital. "
            . "html: fragmento con h2/h3/p/ul (sin html/body), 700–1200 palabras útiles y valor real al lector. "
            . "Enfocado en cluster de categoría + E-E-A-T; rationale con valor lector + SEO.",
        'ops' => $base
            . "Modo: operaciones. Responde en texto claro con pasos accionables. "
            . "Si sugieres cambios de código, indícalos como propuesta (no digas que ya se aplicaron).",
        'maintain' => $base
            . "Modo: mantenimiento del sitio (copy, iconos, secciones, CSS, textos SEO, ampliar/corregir info). "
            . "Si te dan extracto de un archivo, ANÁLIZALO y propone cambios concretos (qué buscar/reemplazar). "
            . "Prioriza el design system actual (dark cyber/tech, variables de base.css). "
            . "Al final: pide pulsar «Crear propuesta en la cola» para formalizar (tú SÍ puedes generar esa solicitud). "
            . "Nunca digas que ya modificaste archivos en producción. Nunca digas que no puedes crear propuestas.",
        'design' => $base
            . "Modo: DISEÑO UI con preview obligatorio. "
            . "Tienes el design system (colores, tipografía títulos/párrafos/cards, radios, botones). "
            . "Puedes reinventar layout solo si permanece alineado (reinvent_aligned). "
            . "Si piden revisar/rediseñar una página concreta, usa el extracto del archivo y describe el nuevo layout. "
            . "Indica «Crear propuesta en la cola» para generar la propuesta formal con maqueta. "
            . "Nunca digas que ya aplicaste el diseño. Nunca digas que no puedes generar solicitudes de diseño.",
        'admin' => $base
            . "Modo: ACTUALIZACIONES del panel ADMIN (adm.conlineweb.com). "
            . "Puedes planear mejoras de UX/funcionalidad en Monitor, Checklist, Chat, CSS, emails (solo diseño), plazas/URLs. "
            . "Whitelist segura: nunca auth, pagos, conn, secrets. "
            . "Explica el plan; para crear la propuesta formal usa «Crear propuesta en la cola». "
            . "Nunca digas que ya modificaste el admin en producción.",
        'validate' => $base
            . "Modo: VALIDACIÓN de actualizaciones ya aplicadas. "
            . "Explica qué comprobarás (fragmento en archivo, lint, portal). "
            . "Para generar el informe oficial en la cola usa «Crear propuesta en la cola». "
            . "No inventes resultados: la validación real la hace el sistema al ejecutar.",
        default => $base
            . "Modo: consulta / cerebro operativo. Responde en texto claro y accionable. "
            . "SI el usuario pide revisar una página, corregir textos, ampliar info, cambiar copy, "
            . "diseño, blog o hub: analiza el extracto si viene en contexto, da hallazgos concretos "
            . "y dile que pulse «Crear propuesta en la cola» (eso SÍ genera la solicitud formal). "
            . "PROHIBIDO responder «no puedo generar solicitudes/propuestas». "
            . "Solo rechaza pedidos ilegales, pagos/credenciales o fuera de ConlineWeb.",
    };
}

/**
 * Páginas/landings conocidas para detectar referencias en el chat.
 *
 * @return list<string>
 */
function cw_seo_mexico_ai_prompt_known_pages(): array
{
    return [
        'index.php',
        'desarrollo-de-software.php',
        'software-para-empresas.php',
        'software-para-empresas-leon.php',
        'paginas-web.php',
        'diseno-de-paginas-web.php',
        'tienda-online.php',
        'seo.php',
        'soluciones-inteligencia-artificial.php',
        'agencia-de-desarrollo-web.php',
        'agencia-de-desarrollo-web-mx.php',
        'soluciones-corporativas.php',
        'tu-web-gratis.php',
        'asesoria-web-gratuita.php',
        'contacto.php',
        'hosting-administrado.php',
        'demos-y-precios.php',
        'proyectos.php',
        'seo-leon.php',
        'paginas-web-leon-gto.php',
        'inteligencia-artificial-leon.php',
        'tienda-online-leon.php',
    ];
}

/**
 * Extrae rutas/URLs de página mencionadas en el texto del usuario.
 *
 * @return list<string> paths relativos candidatos
 */
function cw_seo_mexico_ai_prompt_extract_paths(string $text): array
{
    $paths = [];
    $low = mb_strtolower($text);

    if (preg_match_all('~https?://(?:www\.)?conlineweb\.com/([^\s\"\'<>?#]+)~iu', $text, $m)) {
        foreach ($m[1] as $p) {
            $p = rawurldecode((string) $p);
            $p = trim(str_replace('\\', '/', $p), '/');
            if ($p !== '') {
                $paths[] = $p;
            }
        }
    }
    if (preg_match_all('#\b([a-z0-9\-]+\.php)\b#i', $text, $m2)) {
        foreach ($m2[1] as $p) {
            $paths[] = strtolower((string) $p);
        }
    }
    foreach (cw_seo_mexico_ai_prompt_known_pages() as $page) {
        $slug = preg_replace('/\.php$/i', '', $page) ?? $page;
        if ($slug !== '' && (str_contains($low, $slug) || str_contains($low, str_replace('-', ' ', $slug)))) {
            $paths[] = $page;
        }
    }
    // Hubs México frecuentes
    if (preg_match_all('#mexico/(?:ciudades|estados|servicios)/[a-z0-9\-/]+#i', $low, $m3)) {
        foreach ($m3[0] as $p) {
            $paths[] = rtrim((string) $p, '/') . '/';
        }
    }

    $paths = array_values(array_unique(array_filter($paths)));
    return array_slice($paths, 0, 6);
}

/**
 * Carga extractos de archivos del sitio para que el chat analice páginas concretas.
 */
function cw_seo_mexico_ai_prompt_page_context(string $instruction): string
{
    require_once __DIR__ . '/cw_site_ai_maintain.php';
    $root = function_exists('cw_seo_mexico_autofix_site_root') ? cw_seo_mexico_autofix_site_root() : null;
    if ($root === null) {
        return '';
    }
    $paths = cw_seo_mexico_ai_prompt_extract_paths($instruction);
    if ($paths === []) {
        return '';
    }
    $chunks = [];
    foreach ($paths as $raw) {
        $resolved = cw_site_ai_maintain_resolve_path($raw);
        if ($resolved === null) {
            continue;
        }
        $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $resolved);
        if (!is_readable($abs) || !is_file($abs)) {
            continue;
        }
        $rawFile = (string) file_get_contents($abs);
        $body = $rawFile;
        if (preg_match('/<body[^>]*>(.*)<\/body>/is', $rawFile, $bm)) {
            $body = (string) $bm[1];
        }
        $excerpt = mb_substr(trim($body !== '' ? $body : $rawFile), 0, 9000);
        $chunks[] = "--- ARCHIVO: {$resolved} ---\n{$excerpt}\n--- FIN {$resolved} ---";
        if (count($chunks) >= 3) {
            break;
        }
    }
    if ($chunks === []) {
        return '';
    }
    return "Extractos reales del sitio para tu análisis (usa esto; no inventes el HTML):\n"
        . implode("\n\n", $chunks);
}

function cw_seo_mexico_ai_prompt_create_session(mysqli $conn, string $mode = 'ask', int $userId = 0, string $title = ''): array
{
    cw_seo_mexico_ai_prompt_ensure_tables($conn);
    $mode = preg_replace('/[^a-z_]/', '', strtolower($mode)) ?: 'ask';
    if (!in_array($mode, cw_seo_mexico_ai_prompt_allowed_modes(), true)) {
        $mode = 'ask';
    }
    $title = mb_substr(trim($title !== '' ? $title : 'Conversación ' . date('Y-m-d H:i')), 0, 255);
    $uid = max(0, $userId);
    $stmt = $conn->prepare(
        'INSERT INTO cw_seo_mexico_ai_prompt_sessions (title, mode, created_by, created_at, updated_at)
         VALUES (?, ?, ?, NOW(), NOW())'
    );
    if (!$stmt) {
        return ['ok' => false, 'error' => 'No se pudo crear sesión'];
    }
    $stmt->bind_param('ssi', $title, $mode, $uid);
    $stmt->execute();
    $id = (int) $stmt->insert_id;
    $stmt->close();
    return ['ok' => true, 'session_id' => $id, 'mode' => $mode, 'title' => $title];
}

/**
 * @return list<array<string,mixed>>
 */
function cw_seo_mexico_ai_prompt_list_sessions(mysqli $conn, int $limit = 30): array
{
    cw_seo_mexico_ai_prompt_ensure_tables($conn);
    $limit = max(1, min(80, $limit));
    $rows = [];
    $res = $conn->query(
        "SELECT id, title, mode, created_by, created_at, updated_at
         FROM cw_seo_mexico_ai_prompt_sessions
         ORDER BY updated_at DESC LIMIT {$limit}"
    );
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        $res->free();
    }
    return $rows;
}

function cw_seo_mexico_ai_prompt_get_session(mysqli $conn, int $sessionId): ?array
{
    cw_seo_mexico_ai_prompt_ensure_tables($conn);
    $stmt = $conn->prepare(
        'SELECT id, title, mode, created_by, created_at, updated_at
         FROM cw_seo_mexico_ai_prompt_sessions WHERE id = ? LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $sessionId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return is_array($row) ? $row : null;
}

/**
 * @return list<array<string,mixed>>
 */
function cw_seo_mexico_ai_prompt_messages(mysqli $conn, int $sessionId, int $limit = 80): array
{
    cw_seo_mexico_ai_prompt_ensure_tables($conn);
    $limit = max(1, min(120, $limit));
    $rows = [];
    $stmt = $conn->prepare(
        'SELECT id, role, content, meta_json, created_at
         FROM cw_seo_mexico_ai_prompt_messages
         WHERE session_id = ? ORDER BY id ASC LIMIT ' . $limit
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $sessionId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($r = $res->fetch_assoc())) {
        $r['meta'] = json_decode((string) ($r['meta_json'] ?? ''), true) ?: [];
        $rows[] = $r;
    }
    $stmt->close();
    return $rows;
}

function cw_seo_mexico_ai_prompt_add_message(
    mysqli $conn,
    int $sessionId,
    string $role,
    string $content,
    array $meta = []
): int {
    $role = in_array($role, ['user', 'assistant', 'system'], true) ? $role : 'user';
    $metaJ = $meta !== [] ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null;
    $stmt = $conn->prepare(
        'INSERT INTO cw_seo_mexico_ai_prompt_messages (session_id, role, content, meta_json, created_at)
         VALUES (?, ?, ?, ?, NOW())'
    );
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('isss', $sessionId, $role, $content, $metaJ);
    $stmt->execute();
    $id = (int) $stmt->insert_id;
    $stmt->close();
    $conn->query('UPDATE cw_seo_mexico_ai_prompt_sessions SET updated_at = NOW() WHERE id = ' . (int) $sessionId);
    return $id;
}

/**
 * Ejecuta un prompt del usuario en una sesión.
 *
 * @return array{ok:bool,session_id?:int,reply?:string,mode?:string,proposal_ready?:bool,error?:string}
 */
function cw_seo_mexico_ai_prompt_run(
    mysqli $conn,
    string $prompt,
    string $mode = 'ask',
    int $sessionId = 0,
    int $userId = 0
): array {
    $prompt = trim($prompt);
    if (mb_strlen($prompt) < 2) {
        return ['ok' => false, 'error' => 'Escribe un prompt'];
    }
    if (mb_strlen($prompt) > 8000) {
        return ['ok' => false, 'error' => 'Prompt demasiado largo (máx. 8000)'];
    }
    if (!cw_seo_mexico_ai_available()) {
        return ['ok' => false, 'error' => 'IA no activa / OpenAI no configurada'];
    }

    $mode = preg_replace('/[^a-z_]/', '', strtolower($mode)) ?: 'ask';
    if (!in_array($mode, cw_seo_mexico_ai_prompt_allowed_modes(), true)) {
        $mode = 'ask';
    }

    if ($sessionId < 1) {
        $created = cw_seo_mexico_ai_prompt_create_session(
            $conn,
            $mode,
            $userId,
            mb_substr($prompt, 0, 80)
        );
        if (empty($created['ok'])) {
            return $created;
        }
        $sessionId = (int) $created['session_id'];
    } else {
        $sess = cw_seo_mexico_ai_prompt_get_session($conn, $sessionId);
        if ($sess === null) {
            return ['ok' => false, 'error' => 'Sesión no encontrada'];
        }
        // Si cambia el modo, actualizar
        if (($sess['mode'] ?? '') !== $mode) {
            $conn->query(
                "UPDATE cw_seo_mexico_ai_prompt_sessions SET mode = '"
                . $conn->real_escape_string($mode) . "' WHERE id = " . (int) $sessionId
            );
        }
    }

    cw_seo_mexico_ai_prompt_add_message($conn, $sessionId, 'user', $prompt);

    $history = cw_seo_mexico_ai_prompt_messages($conn, $sessionId, 40);
    $systemContent = cw_seo_mexico_ai_prompt_mode_system($mode, $conn);
    $pageCtx = cw_seo_mexico_ai_prompt_page_context($prompt);
    if ($pageCtx !== '') {
        $systemContent .= "\n\n" . $pageCtx;
    }
    $messages = [
        ['role' => 'system', 'content' => $systemContent],
    ];
    foreach ($history as $m) {
        $role = (string) ($m['role'] ?? '');
        if ($role === 'system') {
            continue;
        }
        if ($role !== 'user' && $role !== 'assistant') {
            continue;
        }
        $messages[] = [
            'role' => $role,
            'content' => (string) ($m['content'] ?? ''),
        ];
    }

    $jsonMode = in_array($mode, ['draft_page', 'draft_blog'], true);
    $chat = cw_seo_mexico_ai_chat($messages, $jsonMode ? 0.5 : 0.55, $jsonMode);
    if (empty($chat['ok'])) {
        $err = (string) ($chat['error'] ?? 'IA falló');
        cw_seo_mexico_ai_prompt_add_message($conn, $sessionId, 'assistant', 'Error: ' . $err, ['error' => true]);
        return ['ok' => false, 'error' => $err, 'session_id' => $sessionId];
    }

    $reply = trim((string) ($chat['content'] ?? ''));
    $meta = ['mode' => $mode, 'json' => $jsonMode];
    $proposalReady = false;
    if ($jsonMode) {
        $decoded = json_decode($reply, true);
        if (is_array($decoded)) {
            $meta['parsed'] = true;
            $proposalReady = ($mode === 'draft_page' && !empty($decoded['title']) && !empty($decoded['h1']))
                || ($mode === 'draft_blog' && !empty($decoded['slug']) && !empty($decoded['html']));
            $meta['proposal_ready'] = $proposalReady;
        }
    }

    cw_seo_mexico_ai_prompt_add_message($conn, $sessionId, 'assistant', $reply, $meta);

    return [
        'ok' => true,
        'session_id' => $sessionId,
        'reply' => $reply,
        'mode' => $mode,
        'proposal_ready' => $proposalReady,
        'message' => $proposalReady
            ? 'Borrador listo. Puedes convertirlo en propuesta formal (sin implementar aún).'
            : 'Respuesta generada.',
    ];
}

/**
 * Desde el chat: interpreta la petición del usuario y crea propuesta formal (pending).
 * Nunca escribe al sitio; deja la propuesta en Monitor (+ alerta campanita/correo).
 *
 * @return array{ok:bool,proposal_id?:int,error?:string,content_type?:string,message?:string,action?:string}
 */
function cw_seo_mexico_ai_prompt_execute_request(
    mysqli $conn,
    int $sessionId,
    string $instruction = '',
    int $userId = 0
): array {
    $sess = cw_seo_mexico_ai_prompt_get_session($conn, $sessionId);
    if ($sess === null) {
        return ['ok' => false, 'error' => 'Sesión no encontrada'];
    }
    $mode = (string) ($sess['mode'] ?? 'ask');

    $msgs = cw_seo_mexico_ai_prompt_messages($conn, $sessionId, 40);
    $lastUser = '';
    for ($i = count($msgs) - 1; $i >= 0; $i--) {
        if (($msgs[$i]['role'] ?? '') === 'user') {
            $lastUser = trim((string) ($msgs[$i]['content'] ?? ''));
            break;
        }
    }
    $instruction = trim($instruction) !== '' ? trim($instruction) : $lastUser;
    if (mb_strlen($instruction) < 8) {
        return ['ok' => false, 'error' => 'Escribe o conversa primero la petición (mín. 8 caracteres)'];
    }

    // Atajos por modo
    if ($mode === 'draft_page' || $mode === 'draft_blog') {
        return cw_seo_mexico_ai_prompt_to_proposal($conn, $sessionId, $userId);
    }
    if ($mode === 'maintain') {
        require_once __DIR__ . '/cw_site_ai_maintain.php';
        $result = cw_site_ai_maintain_propose_patch($conn, $instruction, '', $userId);
        if (!empty($result['ok'])) {
            cw_seo_mexico_ai_prompt_add_message(
                $conn,
                $sessionId,
                'assistant',
                'Petición de mantenimiento convertida en propuesta #' . (int) ($result['proposal_id'] ?? 0)
                . '. Revísala en Monitor IA (vista previa → implementar). No se escribió nada aún.',
                ['proposal_id' => (int) ($result['proposal_id'] ?? 0), 'action' => 'propose_maintain']
            );
            $result['content_type'] = 'fix';
            $result['action'] = 'propose_maintain';
            $result['message'] = 'Propuesta de mantenimiento creada desde el chat. Apruébala en Monitor.';
        }
        return $result;
    }
    if ($mode === 'design') {
        require_once __DIR__ . '/cw_site_ai_design.php';
        $result = cw_site_ai_design_propose($conn, $instruction, '', '', $userId);
        if (!empty($result['ok'])) {
            $pid = (int) ($result['proposal_id'] ?? 0);
            cw_seo_mexico_ai_prompt_add_message(
                $conn,
                $sessionId,
                'assistant',
                'Propuesta de diseño #' . $pid . ' creada con PREVIEW obligatorio. '
                . 'Ábrela en Monitor IA, analiza la maqueta y solo entonces implementa si es viable. '
                . 'Nada se escribió en producción.',
                ['proposal_id' => $pid, 'action' => 'propose_design']
            );
            $result['content_type'] = 'design';
            $result['action'] = 'propose_design';
            $result['message'] = 'Propuesta de diseño con preview creada. Analízala en Monitor.';
        }
        return $result;
    }
    if ($mode === 'admin') {
        require_once __DIR__ . '/cw_site_ai_admin.php';
        $result = cw_site_ai_admin_propose_from_instruction($conn, $instruction, $userId);
        if (!empty($result['ok'])) {
            $pid = (int) ($result['proposal_id'] ?? 0);
            $vk = (string) ($result['view_key'] ?? '');
            cw_seo_mexico_ai_prompt_add_message(
                $conn,
                $sessionId,
                'assistant',
                'Actualización ADMIN convertida en propuesta #' . $pid
                . ($vk !== '' ? ' (vista «' . $vk . '»)' : '')
                . '. Revísala en Monitor IA (Detalle → implementar). Nada se escribió aún en adm.',
                ['proposal_id' => $pid, 'action' => 'propose_admin', 'view_key' => $vk]
            );
            $result['content_type'] = 'fix';
            $result['action'] = 'propose_admin';
            $result['message'] = 'Propuesta de actualización admin en la cola. Apruébala en Monitor.';
        }
        return $result;
    }
    if ($mode === 'validate') {
        $scope = 'admin';
        $low = mb_strtolower($instruction);
        if (str_contains($low, 'cliente')) {
            $scope = 'cliente';
        } elseif (str_contains($low, 'sitio') || (str_contains($low, 'conlineweb.com') && !str_contains($low, 'adm'))) {
            $scope = 'site';
        } elseif (str_contains($low, 'todo') || str_contains($low, 'todos') || str_contains($low, 'tres portales')) {
            $scope = 'all';
        }
        $result = cw_seo_mexico_ai_validate_recent_updates($conn, $scope, 8, $userId);
        if (!empty($result['ok'])) {
            $pid = (int) ($result['proposal_id'] ?? 0);
            $report = (string) ($result['report'] ?? $result['message'] ?? 'Validación lista');
            cw_seo_mexico_ai_prompt_add_message(
                $conn,
                $sessionId,
                'assistant',
                $report . ($pid > 0 ? "\n\nInforme también en cola Monitor como propuesta #" . $pid
                    . '. Aprobar = acusar recibo (no reescribe código).' : ''),
                [
                    'proposal_id' => $pid,
                    'action' => 'validate_admin',
                    'ok_count' => (int) ($result['ok_count'] ?? 0),
                    'fail_count' => (int) ($result['fail_count'] ?? 0),
                ]
            );
            $result['action'] = 'validate_admin';
            $result['content_type'] = 'fix';
        } else {
            cw_seo_mexico_ai_prompt_add_message(
                $conn,
                $sessionId,
                'assistant',
                (string) ($result['error'] ?? 'No se pudo validar'),
                ['action' => 'validate_admin']
            );
        }
        return $result;
    }

    // ask / ops / brain: clasificar intención con cerebro (+ heurística anti-bloqueo)
    require_once __DIR__ . '/cw_site_ai_brain.php';
    require_once __DIR__ . '/cw_site_ai_maintain.php';
    $brain = cw_site_ai_brain_context($conn, 12);
    $cats = implode(', ', array_keys(cw_seo_mexico_ai_blog_categories()));
    $plazas = [];
    foreach (cw_seo_mexico_priority_plazas() as $p) {
        $plazas[] = (string) ($p['slug'] ?? '');
    }
    $detectedPaths = cw_seo_mexico_ai_prompt_extract_paths($instruction);
    $resolvedPath = '';
    foreach ($detectedPaths as $dp) {
        $r = cw_site_ai_maintain_resolve_path($dp);
        if ($r !== null) {
            $resolvedPath = $r;
            break;
        }
    }
    $pageCtx = cw_seo_mexico_ai_prompt_page_context($instruction);

    require_once __DIR__ . '/cw_seo_mexico_ai_constitution.php';
    $system = "Eres el router de peticiones del cerebro ConlineWeb (MASTER SEO/GEO + admin).\n"
        . cw_seo_mexico_ai_master_brief() . "\n{$brain}\n"
        . 'Clasifica la petición y responde SOLO JSON: '
        . '{"action":"propose_design|propose_maintain|propose_admin|validate_admin|propose_blog|propose_hub|propose_blog_improve|none",'
        . '"topic":"","category":"","city_slug":"","slug":"","path":"","target_url":"","scope":"admin","instruction":"","why":""} '
        . "PREFERIR SIEMPRE una acción útil. action=none SOLO si es ilegal, fuera de ConlineWeb, o pide secretos/pagos/auth.\n"
        . "Reglas:\n"
        . "- propose_maintain: corregir/ampliar textos, SEO copy, info de una página, sección, CSS de página, revisar landing concreta.\n"
        . "- propose_design: rediseño/UI/layout/hero/cards/preview visual.\n"
        . "- propose_admin: mejorar panel adm (Monitor, Checklist, Chat, botones, CSS admin, emails diseño).\n"
        . "- validate_admin: validar si updates quedaron bien.\n"
        . "- propose_blog / propose_blog_improve / propose_hub: contenido editorial o hub ciudad.\n"
        . "- Si menciona una URL/página del sitio → propose_maintain o propose_design (nunca none).\n"
        . "- Revisar/analizar/mejorar/corregir/explayar/cambiar información → propose_maintain.\n"
        . 'scope: admin|site|cliente|all (validate_admin). category: ' . $cats . '. '
        . 'city_slug preferido: ' . implode(', ', $plazas) . ". "
        . 'path: archivo .php relativo si aplica'
        . ($resolvedPath !== '' ? (' (detectado: ' . $resolvedPath . ')') : '') . '. '
        . 'instruction = petición accionable limpia alineada a mejores técnicas SEO/GEO. '
        . 'why DEBE citar intención/keyword o señal GEO y el valor SEO/UX (no genérico).';

    $userRoute = "Petición del usuario:\n{$instruction}";
    if ($pageCtx !== '') {
        $userRoute .= "\n\n" . mb_substr($pageCtx, 0, 6000);
    }

    $chat = cw_seo_mexico_ai_chat([
        ['role' => 'system', 'content' => $system],
        ['role' => 'user', 'content' => $userRoute],
    ], 0.2);
    if (empty($chat['ok'])) {
        return ['ok' => false, 'error' => (string) ($chat['error'] ?? 'No se pudo interpretar la petición')];
    }
    $route = json_decode((string) ($chat['content'] ?? ''), true);
    if (!is_array($route)) {
        $route = [];
    }
    $action = preg_replace('/[^a-z_]/', '', strtolower((string) ($route['action'] ?? ''))) ?: '';
    $why = trim((string) ($route['why'] ?? ''));
    $lowIns = mb_strtolower($instruction);

    // Heurística: casi nunca bloquear. Si el router dice none o vacío → elegir acción.
    if ($action === '' || $action === 'none') {
        if (preg_match('/diseñ|disen|rediseñ|redisen|layout|hero|ui\b|preview|visual/i', $lowIns)) {
            $action = 'propose_design';
            $why = $why !== '' ? $why : 'Heurística: petición de diseño/UI → propuesta con preview.';
        } elseif (preg_match('/\b(admin|monitor|checklist|panel|bot[oó]n)\b/i', $lowIns)
            && !preg_match('/conlineweb\.com\/(?!adm)/i', $lowIns)) {
            $action = 'propose_admin';
            $why = $why !== '' ? $why : 'Heurística: mejora del panel admin.';
        } elseif (preg_match('/valid(ar|aci[oó]n)|verificar.*(update|actualiz)/i', $lowIns)) {
            $action = 'validate_admin';
            $why = $why !== '' ? $why : 'Heurística: validación de updates.';
        } elseif (preg_match('/mejor(ar|a).*(art[ií]culo|blog|post)|blog.*existente/i', $lowIns)) {
            $action = 'propose_blog_improve';
            $why = $why !== '' ? $why : 'Heurística: mejorar artículo de blog.';
        } elseif (preg_match('/\b(blog|art[ií]culo)\b/i', $lowIns) && !preg_match('/hub|ciudad/i', $lowIns)) {
            $action = 'propose_blog';
            $why = $why !== '' ? $why : 'Heurística: nuevo contenido de blog.';
        } elseif (preg_match('/hub|ciudad|plaza|m[eé]xico\/ciudades/i', $lowIns)) {
            $action = 'propose_hub';
            $why = $why !== '' ? $why : 'Heurística: textos de hub ciudad.';
        } else {
            // Default: mantenimiento/corrección de sitio (páginas, textos, info)
            $action = 'propose_maintain';
            $why = $why !== '' ? $why : 'Heurística: corrección/análisis/cambio de información en el sitio.';
        }
        $route['action'] = $action;
        $route['why'] = $why;
        if ($resolvedPath !== '' && trim((string) ($route['path'] ?? '')) === '') {
            $route['path'] = $resolvedPath;
        }
    }

    // Rellenar path/url si el router no los puso
    if ($resolvedPath !== '') {
        if (trim((string) ($route['path'] ?? '')) === '') {
            $route['path'] = $resolvedPath;
        }
        if (trim((string) ($route['target_url'] ?? '')) === '') {
            $slugUrl = preg_replace('/\.php$/i', '', $resolvedPath) ?? $resolvedPath;
            $route['target_url'] = 'https://conlineweb.com/' . ltrim((string) $slugUrl, '/') . '/';
        }
    }
    if (trim((string) ($route['instruction'] ?? '')) === '') {
        $route['instruction'] = $instruction;
    }

    $result = ['ok' => false, 'error' => 'Acción no soportada'];
    if ($action === 'propose_design') {
        require_once __DIR__ . '/cw_site_ai_design.php';
        $ins = trim((string) ($route['instruction'] ?? $instruction));
        $path = trim((string) ($route['path'] ?? $resolvedPath));
        $url = trim((string) ($route['target_url'] ?? ''));
        $result = cw_site_ai_design_propose($conn, $ins, $url, $path, $userId);
        $result['content_type'] = 'design';
    } elseif ($action === 'propose_maintain') {
        require_once __DIR__ . '/cw_site_ai_maintain.php';
        $ins = trim((string) ($route['instruction'] ?? $instruction));
        $path = trim((string) ($route['path'] ?? $resolvedPath));
        $result = cw_site_ai_maintain_propose_patch($conn, $ins, $path, $userId);
        $result['content_type'] = 'fix';
    } elseif ($action === 'propose_admin') {
        require_once __DIR__ . '/cw_site_ai_admin.php';
        $ins = trim((string) ($route['instruction'] ?? $instruction));
        $result = cw_site_ai_admin_propose_from_instruction($conn, $ins, $userId);
        $result['content_type'] = 'fix';
    } elseif ($action === 'validate_admin') {
        $scope = preg_replace('/[^a-z_]/', '', strtolower((string) ($route['scope'] ?? 'admin'))) ?: 'admin';
        if (!in_array($scope, ['admin', 'site', 'cliente', 'all'], true)) {
            $scope = 'admin';
        }
        $result = cw_seo_mexico_ai_validate_recent_updates($conn, $scope, 8, $userId);
        $result['content_type'] = 'fix';
    } elseif ($action === 'propose_blog') {
        $topic = trim((string) ($route['topic'] ?? $instruction));
        $cat = (string) ($route['category'] ?? 'seo');
        $result = cw_seo_mexico_ai_propose_blog($conn, $topic, $cat, $userId);
        $result['content_type'] = 'blog';
    } elseif ($action === 'propose_hub') {
        $slug = (string) ($route['city_slug'] ?? '');
        if ($slug === '') {
            foreach ($plazas as $ps) {
                if ($ps !== '' && str_contains($lowIns, $ps)) {
                    $slug = $ps;
                    break;
                }
            }
        }
        if ($slug === '') {
            $slug = 'leon';
        }
        $result = cw_seo_mexico_ai_propose_hub_text($conn, $slug, $userId);
        $result['content_type'] = 'page';
    } elseif ($action === 'propose_blog_improve') {
        $slug = (string) ($route['slug'] ?? '');
        if ($slug === '' && preg_match('#blog/articulo/([a-z0-9\-]+)#i', $instruction, $bm)) {
            $slug = (string) $bm[1];
        }
        $result = cw_seo_mexico_ai_propose_blog_improve($conn, $slug, $userId);
        $result['content_type'] = 'blog';
    }

    if (!empty($result['ok']) && !empty($result['proposal_id'])) {
        $pid = (int) $result['proposal_id'];
        if ($action === 'validate_admin') {
            $note = (string) ($result['report'] ?? ('Validación #' . $pid))
                . "\n\nInforme en cola Monitor #" . $pid . '. '
                . ($why !== '' ? $why . ' ' : '')
                . 'Aprobar = acusar recibo (no reescribe código).';
        } else {
            $note = 'Petición del chat ejecutada → propuesta #' . $pid
                . ' (' . $action . '). '
                . ($why !== '' ? $why . ' ' : '')
                . 'Revisa en Monitor IA y aprueba para implementar. Nada se escribió aún.';
        }
        cw_seo_mexico_ai_prompt_add_message($conn, $sessionId, 'assistant', $note, [
            'proposal_id' => $pid,
            'action' => $action,
        ]);
        $result['action'] = $action;
        $result['message'] = $action === 'validate_admin'
            ? 'Informe de validación en la cola. Revísalo en Monitor.'
            : 'Propuesta creada desde el chat. Apruébala en Monitor IA.';
        $result['session_id'] = $sessionId;
    } elseif (!empty($result['ok']) && $action === 'validate_admin' && empty($result['proposal_id'])) {
        $note = (string) ($result['report'] ?? $result['message'] ?? 'Validación lista');
        cw_seo_mexico_ai_prompt_add_message($conn, $sessionId, 'assistant', $note, ['action' => $action]);
        $result['action'] = $action;
        $result['session_id'] = $sessionId;
    }

    return $result;
}

/**
 * Convierte el último borrador JSON de la sesión en propuesta IA (pending).
 *
 * @return array{ok:bool,proposal_id?:int,error?:string,content_type?:string}
 */
function cw_seo_mexico_ai_prompt_to_proposal(mysqli $conn, int $sessionId, int $userId = 0): array
{
    $sess = cw_seo_mexico_ai_prompt_get_session($conn, $sessionId);
    if ($sess === null) {
        return ['ok' => false, 'error' => 'Sesión no encontrada'];
    }
    $mode = (string) ($sess['mode'] ?? 'ask');
    if (in_array($mode, ['maintain', 'design', 'admin', 'validate', 'ask', 'ops'], true)) {
        return cw_seo_mexico_ai_prompt_execute_request($conn, $sessionId, '', $userId);
    }
    if (!in_array($mode, ['draft_page', 'draft_blog'], true)) {
        // Desde consulta/ops: intentar rutear la petición
        return cw_seo_mexico_ai_prompt_execute_request($conn, $sessionId, '', $userId);
    }

    $msgs = cw_seo_mexico_ai_prompt_messages($conn, $sessionId, 80);
    $lastAssistant = null;
    for ($i = count($msgs) - 1; $i >= 0; $i--) {
        if (($msgs[$i]['role'] ?? '') === 'assistant') {
            $lastAssistant = $msgs[$i];
            break;
        }
    }
    if ($lastAssistant === null) {
        return ['ok' => false, 'error' => 'No hay respuesta de IA para convertir'];
    }
    $data = json_decode((string) ($lastAssistant['content'] ?? ''), true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'La última respuesta no es JSON de borrador válido'];
    }

    if ($mode === 'draft_page') {
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($data['city_slug'] ?? 'leon'))) ?: 'leon';
        $cityName = ucwords(str_replace('-', ' ', $slug));
        foreach (cw_seo_mexico_priority_plazas() as $p) {
            if (($p['slug'] ?? '') === $slug) {
                $cityName = (string) ($p['city'] ?? $cityName);
                break;
            }
        }
        $after = [
            'title' => trim((string) ($data['title'] ?? '')),
            'description' => trim((string) ($data['description'] ?? '')),
            'h1' => trim((string) ($data['h1'] ?? '')),
            'hero_subtitle' => trim((string) ($data['hero_subtitle'] ?? '')),
            'rationale' => trim((string) ($data['rationale'] ?? '')),
        ];
        if ($after['title'] === '' || $after['h1'] === '') {
            return ['ok' => false, 'error' => 'JSON de página incompleto (title/h1)'];
        }
        if (mb_strlen($after['rationale']) < 20) {
            $after['rationale'] = 'Propuesta de hub para ' . $cityName
                . ' generada desde Colaboración IA, orientada a SEO local ConlineWeb.';
        }
        $saved = cw_seo_mexico_ai_save_proposal(
            $conn,
            'hub_text',
            'Hub SEO · ' . $cityName . ' (desde prompt)',
            $slug,
            'https://conlineweb.com/mexico/ciudades/' . $slug . '/',
            'Colaboración IA · hub ' . $cityName,
            [],
            $after,
            $userId
        );
        if (empty($saved['ok'])) {
            return $saved;
        }
        return [
            'ok' => true,
            'proposal_id' => (int) $saved['id'],
            'content_type' => 'page',
            'message' => 'Propuesta de página creada. Revísala en Monitor IA antes de implementar.',
        ];
    }

    // draft_blog
    $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($data['slug'] ?? ''))) ?? '';
    if ($slug === '' || strlen($slug) < 6) {
        return ['ok' => false, 'error' => 'slug de blog inválido'];
    }
    $html = trim((string) ($data['html'] ?? ''));
    if ($html === '') {
        return ['ok' => false, 'error' => 'HTML de blog vacío'];
    }
    $after = [
        'slug' => $slug,
        'title' => trim((string) ($data['title'] ?? '')),
        'excerpt' => trim((string) ($data['excerpt'] ?? '')),
        'category' => preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($data['category'] ?? 'seo'))) ?: 'seo',
        'keyword' => trim((string) ($data['keyword'] ?? '')),
        'html' => $html,
        'rationale' => trim((string) ($data['rationale'] ?? '')),
        'date' => date('Y-m-d'),
        'read_minutes' => max(6, min(18, (int) round(str_word_count(strip_tags($html)) / 180))),
    ];
    if ($after['title'] === '') {
        return ['ok' => false, 'error' => 'Título de blog vacío'];
    }
    if (mb_strlen($after['rationale']) < 20) {
        $after['rationale'] = 'Artículo de blog propuesto desde Colaboración IA'
            . ($after['keyword'] !== '' ? ' (keyword: ' . $after['keyword'] . ')' : '')
            . ' para el cluster SEO de ConlineWeb.';
    }
    $saved = cw_seo_mexico_ai_save_proposal(
        $conn,
        'blog_post',
        'Blog · ' . $after['title'] . ' (desde prompt)',
        $slug,
        'https://conlineweb.com/blog/articulo/' . $slug . '/',
        'Colaboración IA · blog ' . mb_substr($after['title'], 0, 120),
        ['topic' => $after['keyword']],
        $after,
        $userId
    );
    if (empty($saved['ok'])) {
        return $saved;
    }
    return [
        'ok' => true,
        'proposal_id' => (int) $saved['id'],
        'content_type' => 'blog',
        'message' => 'Propuesta de blog creada. Revísala en Monitor IA antes de implementar.',
    ];
}
