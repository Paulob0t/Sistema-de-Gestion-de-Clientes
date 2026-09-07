<?php
/**
 * Cerebro del sitio ConlineWeb — base de conocimiento persistente para la IA.
 * Objetivo: SEO + contenido + mantenimiento continuo para liderar el giro en México.
 * La IA propone; el humano aprueba; al aplicar, el cerebro aprende.
 */
declare(strict_types=1);

require_once __DIR__ . '/cw_seo_mexico_ai.php';

function cw_site_ai_brain_ensure_tables(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS cw_site_ai_knowledge (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            kkey VARCHAR(120) NOT NULL,
            category VARCHAR(60) NOT NULL DEFAULT 'general',
            title VARCHAR(255) NOT NULL DEFAULT '',
            content MEDIUMTEXT NOT NULL,
            source VARCHAR(80) NOT NULL DEFAULT 'seed',
            weight TINYINT UNSIGNED NOT NULL DEFAULT 50,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_by INT UNSIGNED DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_kkey (kkey),
            KEY idx_cat (category),
            KEY idx_active_weight (active, weight)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $conn->query(
        "CREATE TABLE IF NOT EXISTS cw_site_ai_knowledge_log (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            kkey VARCHAR(120) NOT NULL DEFAULT '',
            event VARCHAR(60) NOT NULL DEFAULT 'learn',
            detail TEXT,
            proposal_id BIGINT UNSIGNED DEFAULT NULL,
            created_by INT UNSIGNED DEFAULT 0,
            created_at DATETIME NOT NULL,
            KEY idx_created (created_at),
            KEY idx_proposal (proposal_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

/**
 * @return array{ok:bool,id?:int,error?:string}
 */
function cw_site_ai_brain_upsert(
    mysqli $conn,
    string $key,
    string $category,
    string $title,
    string $content,
    string $source = 'manual',
    int $weight = 50,
    int $userId = 0
): array {
    cw_site_ai_brain_ensure_tables($conn);
    $key = preg_replace('/[^a-z0-9_\-\.]/', '', strtolower(trim($key))) ?? '';
    if ($key === '' || trim($content) === '') {
        return ['ok' => false, 'error' => 'key/content requeridos'];
    }
    $category = preg_replace('/[^a-z0-9_\-]/', '', strtolower($category)) ?: 'general';
    $title = mb_substr(trim($title), 0, 255);
    $content = trim($content);
    $source = mb_substr(preg_replace('/[^a-z0-9_\-]/', '', strtolower($source)) ?: 'manual', 0, 80);
    $weight = max(1, min(100, $weight));
    $uid = max(0, $userId);

    $stmt = $conn->prepare(
        'INSERT INTO cw_site_ai_knowledge
         (kkey, category, title, content, source, weight, active, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, 1, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
           category = VALUES(category),
           title = VALUES(title),
           content = VALUES(content),
           source = VALUES(source),
           weight = GREATEST(weight, VALUES(weight)),
           active = 1,
           updated_at = NOW()'
    );
    if (!$stmt) {
        return ['ok' => false, 'error' => 'No se pudo preparar upsert'];
    }
    $stmt->bind_param('sssssii', $key, $category, $title, $content, $source, $weight, $uid);
    $stmt->execute();
    $id = (int) $stmt->insert_id;
    $stmt->close();
    return ['ok' => true, 'id' => $id];
}

function cw_site_ai_brain_log(
    mysqli $conn,
    string $key,
    string $event,
    string $detail = '',
    ?int $proposalId = null,
    int $userId = 0
): void {
    cw_site_ai_brain_ensure_tables($conn);
    $stmt = $conn->prepare(
        'INSERT INTO cw_site_ai_knowledge_log (kkey, event, detail, proposal_id, created_by, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())'
    );
    if (!$stmt) {
        return;
    }
    $pid = $proposalId ?? 0;
    $uid = max(0, $userId);
    $stmt->bind_param('sssii', $key, $event, $detail, $pid, $uid);
    $stmt->execute();
    $stmt->close();
}

/**
 * Semilla inicial del cerebro (idempotente).
 *
 * @return array{ok:bool,seeded:int,message:string}
 */
function cw_site_ai_brain_seed(mysqli $conn, int $userId = 0): array
{
    cw_site_ai_brain_ensure_tables($conn);
    require_once __DIR__ . '/cw_seo_mexico_ai_constitution.php';
    $facts = [];

    $facts[] = [
        'mission',
        'strategy',
        'Misión del cerebro IA',
        cw_seo_mexico_ai_mission()
        . ' ConlineWeb debe convertirse en la referencia digital de su giro en todo México: '
        . 'agencia de desarrollo web, software a medida, e-commerce, SEO, IA empresarial y marketing digital. '
        . 'La IA es cerebro (conocimiento + decisión) y músculo (propuestas de contenido, SEO/GEO y mantenimiento del sitio). '
        . 'Nunca publica sola: siempre genera propuesta y espera aprobación humana.',
        'seed',
        100,
    ];

    $facts[] = [
        'seo_geo_master',
        'strategy',
        'Objetivo primordial · Master SEO y GEO',
        cw_seo_mexico_ai_primary_objectives(),
        'seed',
        100,
    ];

    $facts[] = [
        'continuous_learning',
        'strategy',
        'Aprendizaje continuo',
        'La IA debe aprender de forma constante: cada propuesta aprobada, cada rechazo, cada auditoría '
        . 'y cada resultado de posicionamiento refuerzan el cerebro. El fin es ejecutar cada vez mejor '
        . 'la estrategia SEO/GEO hasta un posicionamiento óptimo, sostenible y alineado a mejores prácticas '
        . 'de Google y de la web moderna (navegadores, rendimiento, accesibilidad, mobile-first).',
        'seed',
        98,
    ];

    $facts[] = [
        'brand_voice',
        'brand',
        'Voz de marca',
        'Español México, tono profesional comercial, claro y orientado a conversión PyME/empresa. '
        . 'Sin emojis. Sin inventar casos, clientes, precios ni estadísticas. Marca: ConlineWeb. Base operativa: León, Guanajuato; cobertura nacional.',
        'seed',
        95,
    ];

    require_once __DIR__ . '/cw_site_ai_design.php';
    $facts[] = [
        'design_system',
        'design',
        'Design system vigente + preview obligatorio',
        cw_site_ai_design_system_context()
        . "\nFlujo diseño: proponer → PREVIEW visual obligatorio → análisis humano → implementar parches si procede. "
        . 'Puede reinventar layout solo en modo reinvent_aligned (mismas tokens/look).',
        'seed',
        100,
    ];

    $facts[] = [
        'services_core',
        'catalog',
        'Servicios reales',
        'Servicios permitidos (no inventar otros): desarrollo web / páginas web, diseño web, software a medida, '
        . 'sistemas empresariales/CRM, tienda en línea/e-commerce, SEO y marketing digital, automatización, '
        . 'inteligencia artificial para negocios. Cobertura México por estado y ciudad.',
        'seed',
        95,
    ];

    $cats = function_exists('cw_seo_mexico_ai_blog_categories') ? cw_seo_mexico_ai_blog_categories() : [];
    if ($cats !== []) {
        $facts[] = [
            'blog_categories',
            'catalog',
            'Categorías blog',
            'Solo estas categorías: ' . implode(', ', array_keys($cats)) . '. Todo artículo nuevo debe fortalecer un cluster existente.',
            'seed',
            90,
        ];
    }

    $plazas = [];
    foreach (cw_seo_mexico_priority_plazas() as $p) {
        $plazas[] = ($p['city'] ?? '') . ' [' . ($p['slug'] ?? '') . '] — ' . ($p['motivo'] ?? '');
    }
    if ($plazas !== []) {
        $facts[] = [
            'geo_priority',
            'geo',
            'Plazas prioritarias',
            implode("\n", $plazas),
            'seed',
            90,
        ];
    }

    require_once __DIR__ . '/cw_seo_mexico_short_urls_strategy.php';
    $facts[] = [
        'short_urls_commercial',
        'strategy',
        'URLs cortas comerciales — 4 fases sitemap',
        cw_seo_mexico_short_urls_ai_brief()
            . "\nCalendario: Fase1 23-jul–31-ago-2026 (+50) · Fase2 1-sep–15-oct (+45) · "
            . 'Fase3 16-oct–15-dic (+160) · Fase4 16-dic–31-ene-2027 (+45 opcional). '
            . 'Kind propuesta: short_url_commercial. Cron encola; humano aprueba en Monitor.',
        'seed',
        96,
    ];

    require_once __DIR__ . '/cw_site_ai_blog_rehab.php';
    $facts[] = [
        'blog_rehab_plan',
        'strategy',
        'Rehab blog · plan por tandas (cerebro)',
        cw_site_ai_blog_rehab_brief(),
        'seed',
        100,
    ];
    $facts[] = [
        'blog_queues_separation',
        'ops',
        'Dos colas en Monitor',
        '1) Propuestas automáticas: cron/chat/hubs/URLs cortas. '
        . '2) Rehab blog: plan template→gold por tandas operado por el cerebro. Nunca mezclar.',
        'seed',
        100,
    ];

    $root = function_exists('cw_seo_mexico_autofix_site_root') ? cw_seo_mexico_autofix_site_root() : null;
    if ($root) {
        $napFile = $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'cw-nap.php';
        if (is_file($napFile)) {
            require_once $napFile;
            if (function_exists('cw_nap')) {
                $n = cw_nap();
                $facts[] = [
                    'nap_canonical',
                    'nap',
                    'NAP canónico',
                    'Fuente: includes/cw-nap.php · '
                    . ($n['name'] ?? 'ConlineWeb') . ' · '
                    . ($n['address_full'] ?? '') . ' · '
                    . ($n['phone_primary_display'] ?? '') . ' · '
                    . ($n['email'] ?? '') . ' · '
                    . ($n['whatsapp'] ?? ''),
                    'seed',
                    100,
                ];
            }
        }
    }

    $facts[] = [
        'discovery',
        'ops',
        'Discovery obligatorio',
        'Tras publicar contenido: actualizar https://conlineweb.com/indice/, sitemap.xml, sitemap-blog.xml, '
        . 'sitemap-index.xml, llms.txt y llms.json. Colocación según content-placement.php.',
        'seed',
        85,
    ];

    $facts[] = [
        'approval_rule',
        'ops',
        'Regla de aprobación',
        'La IA puede pensar, proponer, corregir y preparar mantenimiento (textos, secciones, SEO). '
        . 'Implementar en producción solo tras vista previa y aprobación humana en Monitor IA.',
        'seed',
        100,
    ];

    $facts[] = [
        'maintain_scope',
        'ops',
        'Alcance mantenimiento',
        'Puede proponer cambios de copy, meta, iconos (clases/bi/fa), secciones, CSS y diseño de plantillas de correo '
        . 'en rutas whitelist de los 3 portales. Objetivo visual: estandarizar design system web + marca de correo. '
        . 'No tocar credenciales, pagos, auth, DNS ni BD de clientes.',
        'seed',
        90,
    ];

    $facts[] = [
        'committee_role',
        'strategy',
        'Comité senior · diseño + PM + programación',
        cw_seo_mexico_ai_committee_brief(),
        'seed',
        100,
    ];

    $facts[] = [
        'multi_portal_scope',
        'ops',
        'Alcance multi-portal (whitelist segura)',
        'Portales: conlineweb.com (sitio: landings, includes UX, assets/css, mexico/, blog/, email_templates), '
        . 'adm.conlineweb.com (módulo SEO analytics, CSS admin seguro, plantillas email diseño), '
        . 'cliente.conlineweb.com (layouts, menú/footer, CSS, vistas UX, plantilla email diseño). '
        . 'Prohibido: conn, secrets, auth, pagos/Stripe, facturas, DNS, uploads, vendor, PHPMailer, '
        . 'y modificar constitución/whitelist/cerebro para autootorgarse poder.',
        'seed',
        100,
    ];

    $facts[] = [
        'email_design_scope',
        'design',
        'Correos · solo diseño visual',
        'Plantillas permitidas (diseño): conlineweb.com/includes/email_templates.php; '
        . 'adm: adm_email_template.php, chat_email_template.php, pago_email_template.php (solo markup/estilo); '
        . 'cliente: includes/cliente_email_template.php; compartido: shared/includes/cw_email_brand.php. '
        . 'Preservar tipografía/colores de marca de correo (Montserrat + navy). '
        . 'No cambiar SMTP, envío, tokens, reset password ni lógica de negocio.',
        'seed',
        95,
    ];

    $facts[] = [
        'monitor_ops_buttons',
        'ops',
        'Monitor · botones operativos',
        'En analytics/seo_mexico_monitor.php: '
        . '«Actualizar conocimiento base» ejecuta brain_seed (refuerza misión, design system, multi-portal, emails). '
        . '«Actualizar sitemaps e índice» ejecuta sync_directory (sitemap.xml, sitemap-blog, sitemap-index, llms). '
        . 'Ambos deben mostrar estado busy → OK/error junto a los botones. '
        . 'Landings del sitio son archivos .php (ej. desarrollo-de-software.php), no carpetas.',
        'seed',
        92,
    ];

    $facts[] = [
        'design_must_change_ui',
        'design',
        'Diseño · parches deben cambiar UI',
        'Propuestas design_ui NO pueden ser solo title/meta. '
        . 'Deben incluir al menos un parche de HTML body (hero, section, h1, features, CTA) '
        . 'y/o CSS en assets/css/pages/*.css. Si solo cambia SEO head, la página se ve igual y se rechaza.',
        'seed',
        100,
    ];

    $facts[] = [
        'always_seo_geo_best',
        'seo',
        'SIEMPRE mejores técnicas SEO y GEO',
        'Toda propuesta (chat, diseño, mantenimiento, blog, hub) debe aplicar el playbook SEO/GEO: '
        . 'intención de búsqueda, title/meta/H1, E-E-A-T, GEO local México/NAP, schema, enlaces internos, '
        . 'mobile-first. Prohibido copy o diseño genérico sin fundamento SEO/GEO.',
        'seed',
        100,
    ];

    $facts[] = [
        'domain_mastery_web_systems',
        'strategy',
        'MASTER web + sistemas ConlineWeb',
        cw_seo_mexico_ai_domain_mastery_brief()
        . ' En el chat de una propuesta del Monitor: aplicar correcciones al borrador (after), nunca al sitio vivo.',
        'seed',
        100,
    ];

    $n = 0;
    foreach ($facts as $f) {
        $r = cw_site_ai_brain_upsert($conn, $f[0], $f[1], $f[2], $f[3], $f[4], $f[5], $userId);
        if (!empty($r['ok'])) {
            $n++;
        }
    }

    require_once __DIR__ . '/cw_site_ai_blog_rehab.php';
    $rehabSync = cw_site_ai_blog_rehab_brain_sync($conn, $userId, false);

    cw_site_ai_brain_log(
        $conn,
        'mission',
        'seed',
        'Semilla cerebro: ' . $n . ' hechos · ' . (string) ($rehabSync['message'] ?? ''),
        null,
        $userId
    );
    return [
        'ok' => true,
        'seeded' => $n,
        'message' => "Cerebro sembrado ({$n} hechos base). " . (string) ($rehabSync['message'] ?? ''),
        'rehab' => $rehabSync,
    ];
}

/**
 * @return list<array<string,mixed>>
 */
function cw_site_ai_brain_list(mysqli $conn, int $limit = 40, ?string $category = null): array
{
    cw_site_ai_brain_ensure_tables($conn);
    $limit = max(1, min(100, $limit));
    $sql = 'SELECT id, kkey, category, title, content, source, weight, updated_at
            FROM cw_site_ai_knowledge WHERE active = 1';
    if ($category !== null && $category !== '') {
        $cat = $conn->real_escape_string(preg_replace('/[^a-z0-9_\-]/', '', strtolower($category)) ?? '');
        $sql .= " AND category = '{$cat}'";
    }
    $sql .= ' ORDER BY weight DESC, updated_at DESC LIMIT ' . $limit;
    $res = $conn->query($sql);
    $out = [];
    while ($res && ($r = $res->fetch_assoc())) {
        $out[] = $r;
    }
    return $out;
}

/**
 * Texto compacto para system prompts.
 */
/**
 * Asegura que los objetivos SEO/GEO primordiales estén en el cerebro (también en DBs ya sembradas).
 */
function cw_site_ai_brain_ensure_primary_objectives(mysqli $conn, int $userId = 0): void
{
    require_once __DIR__ . '/cw_seo_mexico_ai_constitution.php';
    cw_site_ai_brain_upsert(
        $conn,
        'seo_geo_master',
        'strategy',
        'Objetivo primordial · Master SEO y GEO',
        cw_seo_mexico_ai_primary_objectives(),
        'seed',
        100,
        $userId
    );
    cw_site_ai_brain_upsert(
        $conn,
        'continuous_learning',
        'strategy',
        'Aprendizaje continuo',
        'La IA debe aprender de forma constante: cada propuesta aprobada, cada rechazo, cada auditoría '
        . 'y cada resultado de posicionamiento refuerzan el cerebro. El fin es ejecutar cada vez mejor '
        . 'la estrategia SEO/GEO hasta un posicionamiento óptimo, sostenible y alineado a mejores prácticas '
        . 'de Google y de la web moderna (navegadores, rendimiento, accesibilidad, mobile-first).',
        'seed',
        98,
        $userId
    );
    cw_site_ai_brain_upsert(
        $conn,
        'mission',
        'strategy',
        'Misión del cerebro IA',
        cw_seo_mexico_ai_mission()
        . ' ConlineWeb debe convertirse en la referencia digital de su giro en todo México. '
        . 'La IA es cerebro + músculo. Nunca publica sola: propuesta → aprobación humana.',
        'seed',
        100,
        $userId
    );
    require_once __DIR__ . '/cw_site_ai_design.php';
    cw_site_ai_brain_upsert(
        $conn,
        'design_system',
        'design',
        'Design system vigente + preview obligatorio',
        cw_site_ai_design_system_context()
        . "\nFlujo diseño: proponer → PREVIEW visual obligatorio → análisis humano → implementar. "
        . 'Reinvention solo si reinvent_aligned al look dark cyber/tech.',
        'seed',
        100,
        $userId
    );
    cw_site_ai_brain_upsert(
        $conn,
        'committee_role',
        'strategy',
        'Comité senior · diseño + PM + programación',
        cw_seo_mexico_ai_committee_brief(),
        'seed',
        100,
        $userId
    );
    cw_site_ai_brain_upsert(
        $conn,
        'multi_portal_scope',
        'ops',
        'Alcance multi-portal (whitelist segura)',
        'Portales: conlineweb.com, adm.conlineweb.com (TODAS las vistas del panel: clientes, dominios, hosting, '
        . 'tickets, leads, solicitudes, cotizaciones, chat, analytics, SEO, CSS/JS), cliente.conlineweb.com. '
        . 'Edición de código PHP/CSS/JS según solicitud en whitelist. '
        . 'Prohibido: conn, secrets, auth, procesar/guardar pagos Stripe, DNS, uploads, vendor, SMTP. '
        . 'Correos: solo diseño. No autootorgarse poder cambiando reglas.',
        'seed',
        100,
        $userId
    );
    cw_site_ai_brain_upsert(
        $conn,
        'email_design_scope',
        'design',
        'Correos · solo diseño visual',
        'Diseño tipografía/colores/layout en email_templates y cw_email_brand. '
        . 'No SMTP, tokens, envío ni lógica de pagos/auth.',
        'seed',
        95,
        $userId
    );
    cw_site_ai_brain_upsert(
        $conn,
        'monitor_ops_buttons',
        'ops',
        'Monitor · botones operativos',
        'Monitor: «Actualizar conocimiento base» = brain_seed; '
        . '«Actualizar sitemaps e índice» = sync_directory con feedback OK/error. '
        . 'Landings = archivos .php (desarrollo-de-software.php), no carpetas.',
        'seed',
        92,
        $userId
    );
    cw_site_ai_brain_upsert(
        $conn,
        'design_must_change_ui',
        'design',
        'Diseño · parches deben cambiar UI',
        'Propuestas design_ui NO pueden ser solo title/meta. '
        . 'Deben incluir al menos un parche de HTML body (hero, section, h1, features, CTA) '
        . 'y/o CSS en assets/css/pages/*.css. Si solo cambia SEO head, la página se ve igual y se rechaza.',
        'seed',
        100,
        $userId
    );
    cw_site_ai_brain_upsert(
        $conn,
        'always_seo_geo_best',
        'seo',
        'SIEMPRE mejores técnicas SEO y GEO',
        cw_seo_mexico_ai_seo_geo_playbook()
        . "\nObligatorio en chat, diseño, mantenimiento, blog y hub. Sin copy/diseño genérico.",
        'seed',
        100,
        $userId
    );
    cw_site_ai_brain_upsert(
        $conn,
        'domain_mastery_web_systems',
        'strategy',
        'MASTER web + sistemas ConlineWeb',
        cw_seo_mexico_ai_domain_mastery_brief()
        . ' Chat en Monitor: corrige el borrador de la propuesta; publicar solo tras aprobación.',
        'seed',
        100,
        $userId
    );

    require_once __DIR__ . '/cw_site_ai_blog_rehab.php';
    cw_site_ai_blog_rehab_brain_sync($conn, $userId, false);
}

function cw_site_ai_brain_context(mysqli $conn, int $limit = 24): string
{
    cw_site_ai_brain_ensure_tables($conn);
    require_once __DIR__ . '/cw_seo_mexico_ai_constitution.php';
    // Auto-seed si vacío
    $cntRes = $conn->query('SELECT COUNT(*) AS c FROM cw_site_ai_knowledge WHERE active = 1');
    $cnt = 0;
    if ($cntRes && ($row = $cntRes->fetch_assoc())) {
        $cnt = (int) ($row['c'] ?? 0);
    }
    if ($cnt === 0) {
        cw_site_ai_brain_seed($conn, 0);
    } else {
        // Actualizar objetivos primordiales aunque el cerebro ya existiera
        cw_site_ai_brain_ensure_primary_objectives($conn, 0);
    }

    // Priorizar hechos rehab en el contexto
    $prefer = ['blog_rehab_today', 'blog_rehab_plan', 'blog_queues_separation', 'seo_geo_master', 'mission'];
    $items = [];
    $seen = [];
    foreach ($prefer as $k) {
        $stmt = $conn->prepare(
            'SELECT kkey, category, title, content, weight FROM cw_site_ai_knowledge
             WHERE active = 1 AND kkey = ? LIMIT 1'
        );
        if (!$stmt) {
            continue;
        }
        $stmt->bind_param('s', $k);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && ($row = $res->fetch_assoc())) {
            $items[] = $row;
            $seen[$k] = true;
        }
        $stmt->close();
    }
    foreach (cw_site_ai_brain_list($conn, $limit) as $it) {
        $k = (string) ($it['kkey'] ?? '');
        if ($k !== '' && isset($seen[$k])) {
            continue;
        }
        $items[] = $it;
        if (count($items) >= $limit) {
            break;
        }
    }

    if ($items === []) {
        return 'Cerebro: sin hechos todavía.';
    }
    require_once __DIR__ . '/cw_site_ai_blog_rehab.php';
    $lines = [
        '=== CEREBRO CONLINEWEB (conocimiento operativo) ===',
        cw_seo_mexico_ai_master_brief(),
        cw_site_ai_blog_rehab_brief(),
        'Rol: cerebro + músculo + comité senior (diseño/PM/dev) en 3 portales. '
        . 'MASTER web/sistemas: SEO/GEO, desarrollo, mantenimiento, soporte, contenidos y diseño.',
        mb_substr(cw_seo_mexico_ai_committee_brief(), 0, 900),
        'Hechos activos:',
    ];
    foreach ($items as $it) {
        $lines[] = '- [' . ($it['category'] ?? '') . '/' . ($it['kkey'] ?? '') . '] '
            . ($it['title'] ?? '') . ': '
            . mb_substr((string) ($it['content'] ?? ''), 0, 420);
    }
    $lines[] = '=== FIN CEREBRO ===';
    return implode("\n", $lines);
}

/**
 * Aprende de una propuesta aplicada (refuerza conocimiento).
 *
 * @param array<string,mixed> $proposalRow
 */
function cw_site_ai_brain_learn_from_applied(
    mysqli $conn,
    array $proposalRow,
    int $userId = 0
): void {
    $kind = (string) ($proposalRow['kind'] ?? '');
    $title = (string) ($proposalRow['title'] ?? '');
    $target = (string) ($proposalRow['target_key'] ?? '');
    $url = (string) ($proposalRow['target_url'] ?? '');
    $after = is_array($proposalRow['after'] ?? null) ? $proposalRow['after'] : [];
    $pid = (int) ($proposalRow['id'] ?? 0);

    $summary = 'Aplicado kind=' . $kind . ' · ' . $title;
    if ($url !== '') {
        $summary .= ' · URL ' . $url;
    }
    if (!empty($after['rationale'])) {
        $summary .= ' · ' . mb_substr((string) $after['rationale'], 0, 280);
    }

    $key = 'applied_' . $kind . '_' . ($target !== '' ? $target : ('p' . $pid));
    $key = substr(preg_replace('/[^a-z0-9_\-]/', '', strtolower($key)) ?? 'applied', 0, 120);

    cw_site_ai_brain_upsert(
        $conn,
        $key,
        'applied',
        'Aprendizaje SEO/GEO · ' . mb_substr($title, 0, 80),
        $summary . ' · Refuerza mejores prácticas SEO/GEO y el objetivo de posicionamiento óptimo de ConlineWeb.',
        'apply',
        55,
        $userId
    );
    cw_site_ai_brain_log($conn, $key, 'learn_apply', $summary, $pid > 0 ? $pid : null, $userId);

    // Contador de ritmo
    $today = date('Y-m-d');
    cw_site_ai_brain_upsert(
        $conn,
        'rhythm_' . $today,
        'metrics',
        'Ritmo del día ' . $today,
        'Se aplicó al menos una mejora IA el ' . $today . ' (kind=' . $kind . '). '
        . 'Mantener cadencia diaria de propuestas útiles orientadas a master SEO/GEO y posicionamiento óptimo.',
        'apply',
        40,
        $userId
    );

    // Refuerzo del objetivo primordial (aprendizaje continuo)
    require_once __DIR__ . '/cw_seo_mexico_ai_constitution.php';
    cw_site_ai_brain_ensure_primary_objectives($conn, $userId);

    // Si fue rehab, marcar done en el plan JSON y refrescar conocimiento del cerebro
    if (str_starts_with($target, 'rehab:') || !empty($after['rehab_id']) || !empty($after['brain_owned'])) {
        $slug = (string) ($after['slug'] ?? preg_replace('/^rehab:/', '', $target));
        require_once __DIR__ . '/cw_site_ai_blog_rehab.php';
        if ($slug !== '' && cw_site_ai_blog_rehab_load_helpers() && function_exists('blog_rehab_queue_mark_done')) {
            blog_rehab_queue_mark_done($slug, 'done');
        }
        cw_site_ai_blog_rehab_brain_sync($conn, $userId, false);
        cw_site_ai_brain_upsert(
            $conn,
            'rehab_done_' . substr(preg_replace('/[^a-z0-9_\-]/', '', strtolower($slug)) ?? 'x', 0, 40),
            'applied',
            'Rehab completado · ' . mb_substr($slug, 0, 60),
            'Artículo rehabilitado vía cerebro. Siguiente: quality standard/gold + regenerar sitemap-blog. '
            . $summary,
            'apply',
            70,
            $userId
        );
    }
}

/**
 * @return array{ok:bool,facts:int,recent_learnings:int,message:string}
 */
function cw_site_ai_brain_status(mysqli $conn): array
{
    cw_site_ai_brain_ensure_tables($conn);
    $facts = 0;
    $r = $conn->query('SELECT COUNT(*) AS c FROM cw_site_ai_knowledge WHERE active = 1');
    if ($r && ($row = $r->fetch_assoc())) {
        $facts = (int) ($row['c'] ?? 0);
    }
    if ($facts === 0) {
        cw_site_ai_brain_seed($conn, 0);
        $r = $conn->query('SELECT COUNT(*) AS c FROM cw_site_ai_knowledge WHERE active = 1');
        if ($r && ($row = $r->fetch_assoc())) {
            $facts = (int) ($row['c'] ?? 0);
        }
    }
    $learnings = 0;
    $r2 = $conn->query(
        "SELECT COUNT(*) AS c FROM cw_site_ai_knowledge WHERE active = 1 AND source = 'apply'"
    );
    if ($r2 && ($row = $r2->fetch_assoc())) {
        $learnings = (int) ($row['c'] ?? 0);
    }
    return [
        'ok' => true,
        'facts' => $facts,
        'recent_learnings' => $learnings,
        'message' => "Cerebro activo · {$facts} hechos · {$learnings} aprendizajes de implementaciones",
    ];
}
