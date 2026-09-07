<?php
/**
 * Autonomía IA SEO México.
 * Piensa temas y genera propuestas iniciales (blog / página hub / mejora).
 * NUNCA aplica al sitio: solo deja filas pending para aprobación manual.
 */
declare(strict_types=1);

require_once __DIR__ . '/cw_seo_mexico_ai.php';

function cw_seo_mexico_ai_runtime_ensure_table(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS cw_seo_mexico_ai_runtime (
            k VARCHAR(64) NOT NULL PRIMARY KEY,
            v TEXT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function cw_seo_mexico_ai_runtime_get(mysqli $conn, string $key, string $default = ''): string
{
    cw_seo_mexico_ai_runtime_ensure_table($conn);
    $stmt = $conn->prepare('SELECT v FROM cw_seo_mexico_ai_runtime WHERE k = ? LIMIT 1');
    if (!$stmt) {
        return $default;
    }
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!$row) {
        return $default;
    }
    return (string) ($row['v'] ?? $default);
}

function cw_seo_mexico_ai_runtime_set(mysqli $conn, string $key, string $value): void
{
    cw_seo_mexico_ai_runtime_ensure_table($conn);
    $stmt = $conn->prepare(
        'INSERT INTO cw_seo_mexico_ai_runtime (k, v, updated_at) VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE v = VALUES(v), updated_at = NOW()'
    );
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('ss', $key, $value);
    $stmt->execute();
    $stmt->close();
}

/** Pausa generación automática de propuestas (cron / autonomía). */
function cw_seo_mexico_ai_propose_is_paused(mysqli $conn): bool
{
    $pauseFile = dirname(__DIR__) . '/storage/seo_mexico_ai_propose.pause';
    if (is_file($pauseFile)) {
        return true;
    }
    return cw_seo_mexico_ai_runtime_get($conn, 'ai_propose_paused', '0') === '1';
}

/** Pausa solo blogs automáticos (plan rehab activo). Hubs/URLs/diseño sí pueden proponerse. */
function cw_seo_mexico_ai_blog_propose_is_paused(mysqli $conn): bool
{
    if (cw_seo_mexico_ai_propose_is_paused($conn)) {
        return true;
    }
    return cw_seo_mexico_ai_runtime_get($conn, 'ai_blog_propose_paused', '0') === '1';
}

function cw_seo_mexico_ai_autonomy_pending_count(mysqli $conn): int
{
    cw_seo_mexico_ai_proposals_ensure_table($conn);
    $res = $conn->query(
        "SELECT COUNT(*) AS c FROM cw_seo_mexico_ai_proposals
         WHERE status IN ('pending','working')"
    );
    if (!$res) {
        return 0;
    }
    $row = $res->fetch_assoc();
    return (int) ($row['c'] ?? 0);
}

/**
 * @return list<string>
 */
function cw_seo_mexico_ai_autonomy_open_target_keys(mysqli $conn): array
{
    cw_seo_mexico_ai_proposals_ensure_table($conn);
    $keys = [];
    $res = $conn->query(
        "SELECT target_key, kind, title FROM cw_seo_mexico_ai_proposals
         WHERE status IN ('pending','working') ORDER BY id DESC LIMIT 80"
    );
    if (!$res) {
        return $keys;
    }
    while ($r = $res->fetch_assoc()) {
        $k = trim((string) ($r['target_key'] ?? ''));
        if ($k !== '') {
            $keys[] = $k;
        }
        $title = mb_strtolower(trim((string) ($r['title'] ?? '')));
        if ($title !== '') {
            $keys[] = 'title:' . $title;
        }
    }
    return $keys;
}

/**
 * Contexto para que la IA decida qué proponer.
 *
 * @return array<string,mixed>
 */
function cw_seo_mexico_ai_autonomy_context(mysqli $conn): array
{
    $cats = cw_seo_mexico_ai_blog_categories();
    $posts = cw_seo_mexico_ai_blog_existing_posts(60);
    $plazas = cw_seo_mexico_priority_plazas();
    $openKeys = cw_seo_mexico_ai_autonomy_open_target_keys($conn);

    $hubWeak = [];
    $root = cw_seo_mexico_site_root_path() ?? cw_seo_mexico_autofix_site_root();
    $hubFile = $root
        ? $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'mexico' . DIRECTORY_SEPARATOR . 'city-hub-overrides.php'
        : null;
    if ($hubFile && is_file($hubFile)) {
        require_once $hubFile;
    }
    foreach ($plazas as $p) {
        $slug = (string) ($p['slug'] ?? '');
        if ($slug === '' || in_array($slug, $openKeys, true)) {
            continue;
        }
        $ov = function_exists('mx_city_hub_override') ? (mx_city_hub_override($slug) ?? []) : [];
        $title = trim((string) ($ov['title'] ?? ''));
        $desc = trim((string) ($ov['description'] ?? ''));
        $h1 = trim((string) ($ov['h1'] ?? ''));
        $score = 0;
        if ($title === '') {
            $score += 3;
        } elseif (mb_strlen($title) < 40 || mb_strlen($title) > 70) {
            $score += 1;
        }
        if ($desc === '' || mb_strlen($desc) < 120) {
            $score += 2;
        }
        if ($h1 === '') {
            $score += 2;
        }
        if ($score > 0) {
            $hubWeak[] = [
                'slug' => $slug,
                'city' => (string) ($p['city'] ?? $slug),
                'motivo' => (string) ($p['motivo'] ?? ''),
                'weak_score' => $score,
                'has_override' => $ov !== [],
            ];
        }
    }
    usort($hubWeak, static fn($a, $b) => ($b['weak_score'] <=> $a['weak_score']));

    $postsByCat = [];
    foreach ($posts as $post) {
        $c = (string) ($post['category'] ?? 'seo');
        $postsByCat[$c] = ($postsByCat[$c] ?? 0) + 1;
    }

    return [
        'pending_open' => cw_seo_mexico_ai_autonomy_pending_count($conn),
        'open_keys' => $openKeys,
        'categories' => array_values($cats),
        'posts_count_by_category' => $postsByCat,
        'existing_posts' => array_map(static fn($p) => [
            'slug' => $p['slug'],
            'title' => $p['title'],
            'category' => $p['category'],
            'excerpt' => mb_substr((string) ($p['excerpt'] ?? ''), 0, 120),
        ], array_slice($posts, 0, 40)),
        'hubs_weak' => array_slice($hubWeak, 0, 10),
        'plazas' => array_map(static fn($p) => [
            'slug' => $p['slug'] ?? '',
            'city' => $p['city'] ?? '',
            'motivo' => $p['motivo'] ?? '',
        ], $plazas),
        'services' => [
            'desarrollo-web',
            'software',
            'ecommerce',
            'seo',
            'inteligencia-artificial',
            'marketing-digital',
        ],
        'directory' => 'https://conlineweb.com/indice/',
        'rule' => 'Solo proponer; nunca publicar. Aprobación humana obligatoria.',
    ];
}

/**
 * Planifica acciones (sin escribir al sitio).
 *
 * @param array{max_blogs?:int,max_hubs?:int,max_improves?:int} $opts
 * @return array{ok:bool,actions?:list<array<string,mixed>>,error?:string,raw?:string}
 */
function cw_seo_mexico_ai_autonomy_plan(mysqli $conn, array $opts = []): array
{
    if (!cw_seo_mexico_ai_available()) {
        return ['ok' => false, 'error' => 'IA no disponible (OpenAI)'];
    }

    $maxBlogs = max(0, min(4, (int) ($opts['max_blogs'] ?? 2)));
    $maxHubs = max(0, min(3, (int) ($opts['max_hubs'] ?? 1)));
    $maxImproves = max(0, min(3, (int) ($opts['max_improves'] ?? 1)));
    $ctx = cw_seo_mexico_ai_autonomy_context($conn);

    require_once __DIR__ . '/cw_site_ai_brain.php';
    require_once __DIR__ . '/cw_site_ai_blog_rehab.php';
    // Antes de planear: el cerebro sincroniza/habilita la tanda rehab
    cw_site_ai_blog_rehab_brain_sync($conn, 0, false);
    $brain = cw_site_ai_brain_context($conn, 16);

    require_once __DIR__ . '/cw_seo_mexico_ai_constitution.php';
    $system = 'Eres el cerebro autónomo de ConlineWeb (agencia digital México) y MASTER SEO/GEO. '
        . cw_seo_mexico_ai_master_brief() . "\n"
        . cw_seo_mexico_ai_blog_editorial_brief() . "\n"
        . cw_site_ai_blog_rehab_brief() . "\n"
        . "{$brain}\n"
        . 'Debes decidir QUÉ contenido proponer ahora para ejecutar la estrategia SEO/GEO de forma óptima. '
        . 'Responde SOLO JSON válido: '
        . '{"actions":[{"kind":"blog_post|hub_text|blog_improve","topic":"...",'
        . '"category":"slug-categoria","city_slug":"...","slug":"...","why":"..."}]} '
        . 'Reglas: '
        . '1) kind blog_post: topic (8+ chars) + category de la lista real; temas que un lector PyME quiera leer completos. '
        . '2) kind hub_text: city_slug de plazas prioritarias (preferir hubs_weak). '
        . '3) kind blog_improve: slug de existing_posts SOLO si NO es rehab-queue/template del plan por tandas (eso va en cola Rehab). '
        . '4) No repetir open_keys ni títulos ya pendientes. '
        . '5) Temas con valor práctico real para PyME México (no relleno SEO); servicios ConlineWeb; sin inventar verticales. '
        . '6) Balancea categorías con pocos posts. '
        . '7) Máximos: blog_post≤' . $maxBlogs . ', hub_text≤' . $maxHubs
        . ', blog_improve≤' . $maxImproves . '. '
        . '8) Si no hay nada útil, actions=[] . '
        . '9) Nunca publiques: solo planeas propuestas. '
        . '10) Prioriza lo que acerque a posicionamiento óptimo SEO/GEO y a ser la mejor web del giro en México. '
        . '11) Cada why debe fundamentar valor al lector Y impacto SEO/GEO (intención/keyword/cluster). '
        . '12) Si hay fase activa short_urls_fase* en checklist, prioriza kind short_url_commercial vía cola dedicada (no duplicar en actions). '
        . '13) Rehab blog lo gestiona el cerebro en cola separada; NO metas templates del plan rehab en actions de autonomía.';

    $user = "Contexto actual:\n" . json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    require_once __DIR__ . '/cw_seo_mexico_short_urls_strategy.php';
    $activeShort = cw_seo_mexico_short_urls_active_phase($conn);
    if ($activeShort !== null) {
        $user .= "\n\nFASE ACTIVA URLs CORTAS: " . json_encode([
            'key' => $activeShort['key'] ?? '',
            'label' => $activeShort['label'] ?? '',
            'plan' => $activeShort['plan'] ?? [],
        ], JSON_UNESCAPED_UNICODE);
        $user .= "\n" . cw_seo_mexico_short_urls_ai_brief();
    }

    $chat = cw_seo_mexico_ai_chat([
        ['role' => 'system', 'content' => $system],
        ['role' => 'user', 'content' => $user],
    ], 0.5);
    if (empty($chat['ok'])) {
        return ['ok' => false, 'error' => (string) ($chat['error'] ?? 'Plan IA falló')];
    }

    $parsed = json_decode((string) ($chat['content'] ?? ''), true);
    if (!is_array($parsed) || !isset($parsed['actions']) || !is_array($parsed['actions'])) {
        return ['ok' => false, 'error' => 'Plan JSON inválido', 'raw' => (string) ($chat['content'] ?? '')];
    }

    $allowedCats = array_keys(cw_seo_mexico_ai_blog_categories());
    $plazaSlugs = [];
    foreach (cw_seo_mexico_priority_plazas() as $p) {
        $plazaSlugs[(string) ($p['slug'] ?? '')] = true;
    }
    $postSlugs = [];
    foreach (cw_seo_mexico_ai_blog_existing_posts(80) as $p) {
        $postSlugs[(string) ($p['slug'] ?? '')] = true;
    }
    $open = array_flip($ctx['open_keys']);

    $actions = [];
    $nBlog = $nHub = $nImp = 0;
    foreach ($parsed['actions'] as $a) {
        if (!is_array($a)) {
            continue;
        }
        $kind = preg_replace('/[^a-z_]/', '', strtolower((string) ($a['kind'] ?? ''))) ?? '';
        if ($kind === 'blog_post') {
            if ($nBlog >= $maxBlogs) {
                continue;
            }
            $topic = trim((string) ($a['topic'] ?? ''));
            $cat = cw_seo_mexico_ai_blog_normalize_category((string) ($a['category'] ?? 'seo'));
            if (mb_strlen($topic) < 8 || !in_array($cat, $allowedCats, true)) {
                continue;
            }
            $titleKey = 'title:' . mb_strtolower($topic);
            if (isset($open[$titleKey])) {
                continue;
            }
            $actions[] = [
                'kind' => 'blog_post',
                'topic' => $topic,
                'category' => $cat,
                'why' => trim((string) ($a['why'] ?? '')),
            ];
            $nBlog++;
        } elseif ($kind === 'hub_text') {
            if ($nHub >= $maxHubs) {
                continue;
            }
            $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($a['city_slug'] ?? $a['slug'] ?? ''))) ?? '';
            if ($slug === '' || empty($plazaSlugs[$slug]) || isset($open[$slug])) {
                continue;
            }
            $actions[] = [
                'kind' => 'hub_text',
                'city_slug' => $slug,
                'why' => trim((string) ($a['why'] ?? '')),
            ];
            $nHub++;
            $open[$slug] = true;
        } elseif ($kind === 'blog_improve') {
            if ($nImp >= $maxImproves) {
                continue;
            }
            $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($a['slug'] ?? ''))) ?? '';
            if ($slug === '' || empty($postSlugs[$slug]) || isset($open[$slug])) {
                continue;
            }
            $actions[] = [
                'kind' => 'blog_improve',
                'slug' => $slug,
                'why' => trim((string) ($a['why'] ?? '')),
            ];
            $nImp++;
            $open[$slug] = true;
        }
    }

    if ($actions === []) {
        $actions = cw_seo_mexico_ai_autonomy_fallback_plan($ctx, $maxBlogs, $maxHubs, $maxImproves);
    }

    return ['ok' => true, 'actions' => $actions, 'context_pending' => (int) $ctx['pending_open']];
}

/**
 * Plan mínimo sin depender del JSON de IA (fallback).
 *
 * @param array<string,mixed> $ctx
 * @return list<array<string,mixed>>
 */
function cw_seo_mexico_ai_autonomy_fallback_plan(array $ctx, int $maxBlogs, int $maxHubs, int $maxImproves): array
{
    $actions = [];
    $open = array_flip($ctx['open_keys'] ?? []);
    $byCat = is_array($ctx['posts_count_by_category'] ?? null) ? $ctx['posts_count_by_category'] : [];
    $cats = is_array($ctx['categories'] ?? null) ? $ctx['categories'] : [];

    // Priorizar categorías con menos posts
    usort($cats, static function ($a, $b) use ($byCat) {
        $ca = (int) ($byCat[$a['slug'] ?? ''] ?? 0);
        $cb = (int) ($byCat[$b['slug'] ?? ''] ?? 0);
        return $ca <=> $cb;
    });

    $topicSeeds = [
        'desarrollo-web' => 'Sitio web profesional para PyME en México: estructura, conversión y WhatsApp',
        'seo' => 'SEO local en México: cómo aparecer cuando te buscan por ciudad y servicio',
        'ecommerce' => 'Tienda en línea para negocio mexicano: catálogo, pagos y envíos sin fricción',
        'software' => 'Software a medida vs plantillas: cuándo conviene a una PyME mexicana',
        'inteligencia-artificial' => 'IA práctica para atención y ventas en negocios de México',
        'marketing-digital' => 'Embudo digital simple para PyME: web, contenido y captura de leads',
    ];

    foreach ($cats as $c) {
        if (count(array_filter($actions, static fn($x) => ($x['kind'] ?? '') === 'blog_post')) >= $maxBlogs) {
            break;
        }
        $slug = (string) ($c['slug'] ?? '');
        $topic = $topicSeeds[$slug] ?? ('Guía práctica ConlineWeb: ' . ($c['name'] ?? $slug) . ' para empresas en México');
        $titleKey = 'title:' . mb_strtolower($topic);
        if (isset($open[$titleKey])) {
            continue;
        }
        $actions[] = [
            'kind' => 'blog_post',
            'topic' => $topic,
            'category' => $slug,
            'why' => 'Fallback: reforzar categoría con menos cobertura',
        ];
    }

    foreach (($ctx['hubs_weak'] ?? []) as $h) {
        if (count(array_filter($actions, static fn($x) => ($x['kind'] ?? '') === 'hub_text')) >= $maxHubs) {
            break;
        }
        $slug = (string) ($h['slug'] ?? '');
        if ($slug === '' || isset($open[$slug])) {
            continue;
        }
        $actions[] = [
            'kind' => 'hub_text',
            'city_slug' => $slug,
            'why' => 'Fallback: hub débil score ' . (int) ($h['weak_score'] ?? 0),
        ];
    }

    $posts = is_array($ctx['existing_posts'] ?? null) ? $ctx['existing_posts'] : [];
    // Mejorar posts más antiguos del listado (últimos del array suelen ser viejos según orden del factory)
    $postsRev = array_reverse($posts);
    foreach ($postsRev as $p) {
        if (count(array_filter($actions, static fn($x) => ($x['kind'] ?? '') === 'blog_improve')) >= $maxImproves) {
            break;
        }
        $slug = (string) ($p['slug'] ?? '');
        if ($slug === '' || isset($open[$slug])) {
            continue;
        }
        $actions[] = [
            'kind' => 'blog_improve',
            'slug' => $slug,
            'why' => 'Fallback: refrescar artículo existente',
        ];
    }

    return $actions;
}

/**
 * Ejecuta el plan: genera propuestas completas (pending). No aplica.
 *
 * @param array{max_blogs?:int,max_hubs?:int,max_improves?:int,max_pending?:int,max_actions?:int} $opts
 * @return array{ok:bool,planned?:int,created?:list<array<string,mixed>>,skipped?:string,errors?:list<string>,message?:string}
 */
function cw_seo_mexico_ai_autonomy_run(mysqli $conn, int $userId = 0, array $opts = []): array
{
    if (cw_seo_mexico_ai_propose_is_paused($conn)) {
        // Aun en pausa total: el cerebro sigue pudiendo sincronizar rehab vía seed/cron dedicado
        return [
            'ok' => true,
            'skipped' => 'Generación de propuestas pausada (flag total). '
                . 'Rehab blog se opera con cron_blog_rehab_enable / Actualizar conocimiento base.',
            'created' => [],
            'planned' => 0,
        ];
    }

    // Plan rehab activo → cero blogs nuevos/improve automáticos
    $blogPaused = cw_seo_mexico_ai_blog_propose_is_paused($conn);
    if ($blogPaused) {
        $opts['max_blogs'] = 0;
        $opts['max_improves'] = 0;
    }

    $maxPending = max(1, min(40, (int) ($opts['max_pending'] ?? 12)));

    // Prioridad estrategia URLs cortas comerciales (fase activa) — no bloqueada por cola editorial
    require_once __DIR__ . '/cw_seo_mexico_short_urls_strategy.php';
    $shortBatch = max(0, min(4, (int) ($opts['max_short_urls'] ?? 2)));
    $shortCreated = [];
    $enqueue = ['phase_label' => ''];
    if ($shortBatch > 0) {
        $enqueue = cw_seo_mexico_short_urls_autonomy_enqueue($conn, $userId, $shortBatch);
        if (!empty($enqueue['created']) && is_array($enqueue['created'])) {
            $shortCreated = $enqueue['created'];
        }
    }

    $open = cw_seo_mexico_ai_autonomy_pending_count($conn);
    if ($open >= $maxPending) {
        if ($shortCreated !== []) {
            return [
                'ok' => true,
                'planned' => count($shortCreated),
                'created' => array_map(static fn($c) => [
                    'proposal_id' => (int) ($c['proposal_id'] ?? 0),
                    'kind' => 'short_url_commercial',
                    'url' => (string) ($c['url'] ?? ''),
                    'why' => 'Estrategia URLs cortas · ' . (string) ($enqueue['phase_label'] ?? ''),
                ], $shortCreated),
                'message' => 'IA generó ' . count($shortCreated) . ' propuesta(s) URL corta. Cola editorial llena — revisa Propuestas.',
            ];
        }
        return [
            'ok' => true,
            'planned' => 0,
            'created' => [],
            'skipped' => 'Ya hay ' . $open . ' propuestas abiertas (tope ' . $maxPending . '). Aprueba o rechaza antes de generar más.',
            'message' => 'Cola llena: espera aprobación manual.',
        ];
    }

    $remainingSlots = $maxPending - cw_seo_mexico_ai_autonomy_pending_count($conn);
    if ($remainingSlots < 1 && $shortCreated === []) {
        return [
            'ok' => true,
            'planned' => 0,
            'created' => [],
            'skipped' => 'Cola llena tras URLs cortas.',
            'message' => 'Espera aprobación manual.',
        ];
    }

    $maxActions = max(0, min(6, (int) ($opts['max_actions'] ?? 4)));
    if ($shortCreated !== []) {
        $maxActions = max(0, $maxActions - count($shortCreated));
    }

    $plan = ['ok' => true, 'actions' => []];
    if ($maxActions > 0) {
        $plan = cw_seo_mexico_ai_autonomy_plan($conn, $opts);
        if (empty($plan['ok'])) {
            if ($shortCreated !== []) {
                return [
                    'ok' => true,
                    'planned' => count($shortCreated),
                    'created' => array_map(static fn($c) => [
                        'proposal_id' => (int) ($c['proposal_id'] ?? 0),
                        'kind' => 'short_url_commercial',
                        'url' => (string) ($c['url'] ?? ''),
                        'why' => 'Estrategia URLs cortas · ' . (string) ($enqueue['phase_label'] ?? ''),
                    ], $shortCreated),
                    'message' => 'IA generó ' . count($shortCreated) . ' propuesta(s) URL corta. Plan editorial omitido.',
                ];
            }
            return ['ok' => false, 'error' => (string) ($plan['error'] ?? 'No se pudo planear'), 'raw' => $plan['raw'] ?? null];
        }
    }

    $actions = is_array($plan['actions'] ?? null) ? $plan['actions'] : [];
    $actions = array_slice($actions, 0, max(0, $maxActions));

    $created = [];
    $errors = [];

    foreach ($actions as $a) {
        if (cw_seo_mexico_ai_autonomy_pending_count($conn) >= $maxPending) {
            $errors[] = 'Tope de pendientes alcanzado a media ejecución';
            break;
        }
        $kind = (string) ($a['kind'] ?? '');
        if ($blogPaused && in_array($kind, ['blog_post', 'blog_improve'], true)) {
            continue; // plan rehab cubre el blog hasta ~2027
        }
        $result = ['ok' => false, 'error' => 'kind desconocido'];

        if ($kind === 'blog_post') {
            $result = cw_seo_mexico_ai_propose_blog(
                $conn,
                (string) ($a['topic'] ?? ''),
                (string) ($a['category'] ?? 'seo'),
                $userId
            );
        } elseif ($kind === 'hub_text') {
            $result = cw_seo_mexico_ai_propose_hub_text(
                $conn,
                (string) ($a['city_slug'] ?? ''),
                $userId
            );
        } elseif ($kind === 'blog_improve') {
            $result = cw_seo_mexico_ai_propose_blog_improve(
                $conn,
                (string) ($a['slug'] ?? ''),
                $userId
            );
        }

        if (!empty($result['ok']) && !empty($result['proposal_id'])) {
            $pid = (int) $result['proposal_id'];
            // Marcar origen autonomía en prompt_summary
            $why = trim((string) ($a['why'] ?? 'Autonomía IA'));
            $stmt = $conn->prepare(
                'UPDATE cw_seo_mexico_ai_proposals
                 SET prompt_summary = CONCAT(\'Autonomía IA · \', LEFT(?, 120), \' · \', prompt_summary)
                 WHERE id = ?'
            );
            if ($stmt) {
                $stmt->bind_param('si', $why, $pid);
                $stmt->execute();
                $stmt->close();
            }
            $created[] = [
                'proposal_id' => $pid,
                'kind' => $kind,
                'url' => (string) ($result['url'] ?? ''),
                'why' => $why,
            ];
        } else {
            $errors[] = $kind . ': ' . (string) ($result['error'] ?? 'falló');
        }
    }

    $n = count($created);
    $totalCreated = count($shortCreated) + $n;
    $allCreated = array_merge(
        array_map(static fn($c) => [
            'proposal_id' => (int) ($c['proposal_id'] ?? 0),
            'kind' => 'short_url_commercial',
            'url' => (string) ($c['url'] ?? ''),
            'why' => 'Estrategia URLs cortas · fase activa',
        ], $shortCreated),
        $created
    );
    return [
        'ok' => $totalCreated > 0 || $errors === [],
        'planned' => count($actions) + count($shortCreated),
        'created' => $allCreated,
        'errors' => $errors,
        'message' => $totalCreated > 0
            ? ("IA generó {$totalCreated} propuesta(s). Revisa y aprueba en Monitor — no se publicó nada.")
            : ('Sin propuestas nuevas. ' . ($errors[0] ?? 'Nada que proponer ahora.')),
    ];
}
