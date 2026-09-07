<?php
/**
 * Estrategia SEO: URLs cortas comerciales /mexico/{plaza}/servicios/{svc}/
 * Indexación por fases — cola automática de propuestas IA.
 */
declare(strict_types=1);

/** @return list<string> */
function cw_seo_mexico_short_urls_service_slugs(): array
{
    return [
        'desarrollo-web',
        'software-a-medida',
        'ecommerce',
        'seo',
        'inteligencia-artificial',
    ];
}

/**
 * @return array<string, array<string, mixed>>
 */
function cw_seo_mexico_short_urls_phases(): array
{
    return [
        'short_urls_fase1' => [
            'key' => 'short_urls_fase1',
            'label' => 'Fase 1 — Piloto',
            'sort' => 1,
            'url_target' => 1252,
            'url_increment' => 50,
            'scope' => '10 plazas prioritarias × 5 servicios',
            'geo_kind' => 'ciudad',
            'include_hub' => false,
            'plan' => [
                'can_start_now' => true,
                'start' => '2026-07-22',
                'due' => '2026-08-31',
                'effort' => '4–6 semanas',
                'window' => 'Jul–Ago 2026',
                'note' => 'Convertir 50 URLs cortas servicio×plaza en landings comerciales indexables (sin 301). '
                    . 'Plazas: CDMX, Guadalajara, Monterrey, León, Querétaro, Puebla, Mérida, Tijuana, Cancún, Toluca. '
                    . 'H1 comercial («Páginas web en Guadalajara»); canonical self; enlace cruzado al hub largo. '
                    . 'La IA encola propuestas en Monitor → Propuestas; aprobación humana obligatoria.',
            ],
            'requires_previous' => null,
            'gsc_gate' => false,
        ],
        'short_urls_fase2' => [
            'key' => 'short_urls_fase2',
            'label' => 'Fase 2 — Expansión ciudades',
            'sort' => 2,
            'url_target' => 1297,
            'url_increment' => 45,
            'scope' => '9 ciudades restantes × 5 servicios',
            'geo_kind' => 'ciudad',
            'include_hub' => false,
            'plan' => [
                'can_start_now' => false,
                'start' => '2026-09-01',
                'due' => '2026-10-15',
                'effort' => '8–12 semanas',
                'window' => 'Sep–Oct 2026',
                'note' => 'Tras revisar GSC 30 días post-Fase 1. Mismo modelo comercial para el resto de ciudades en locations.php. '
                    . 'No repetir H1 de la canónica larga. Medir canibalización antes de escalar.',
            ],
            'requires_previous' => 'short_urls_fase1',
            'gsc_gate' => true,
        ],
        'short_urls_fase3' => [
            'key' => 'short_urls_fase3',
            'label' => 'Fase 3 — Estados',
            'sort' => 3,
            'url_target' => 1457,
            'url_increment' => 160,
            'scope' => '32 estados × 5 servicios',
            'geo_kind' => 'estado',
            'include_hub' => false,
            'plan' => [
                'can_start_now' => false,
                'start' => '2026-10-16',
                'due' => '2026-12-15',
                'effort' => '12–16 semanas',
                'window' => 'Oct–Dic 2026',
                'note' => 'Intención estatal («Desarrollo web en Jalisco») distinta a ciudad. '
                    . 'Diferenciar homónimos (Guadalajara ciudad vs Jalisco estado; León vs Guanajuato).',
            ],
            'requires_previous' => 'short_urls_fase2',
            'gsc_gate' => true,
        ],
        'short_urls_fase4' => [
            'key' => 'short_urls_fase4',
            'label' => 'Fase 4 — Hubs cortos (opcional)',
            'sort' => 4,
            'url_target' => 1502,
            'url_increment' => 45,
            'scope' => '~45 hubs /mexico/{plaza}/',
            'geo_kind' => 'mixed',
            'include_hub' => true,
            'plan' => [
                'can_start_now' => false,
                'start' => '2026-12-16',
                'due' => '2027-01-31',
                'effort' => '4–6 semanas',
                'window' => 'Dic 2026 – Ene 2027',
                'note' => 'Opcional: solo si Fases 1–3 no muestran canibalización en GSC. '
                    . 'Landings generales de plaza; NO duplicar hub largo palabra por palabra.',
            ],
            'requires_previous' => 'short_urls_fase3',
            'gsc_gate' => true,
            'optional' => true,
        ],
    ];
}

/**
 * @return array<string, mixed>|null
 */
function cw_seo_mexico_short_urls_phase(string $phaseKey): ?array
{
    $phases = cw_seo_mexico_short_urls_phases();
    return $phases[$phaseKey] ?? null;
}

/**
 * Fase activa por calendario (primera pendiente cuya ventana incluye hoy).
 *
 * @return array<string, mixed>|null
 */
function cw_seo_mexico_short_urls_active_phase(?mysqli $conn = null): ?array
{
    $today = date('Y-m-d');
    $phases = cw_seo_mexico_short_urls_phases();
    uasort($phases, static fn($a, $b) => ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0));

    foreach ($phases as $phase) {
        $plan = is_array($phase['plan'] ?? null) ? $phase['plan'] : [];
        $start = (string) ($plan['start'] ?? '');
        $due = (string) ($plan['due'] ?? '');
        if ($start !== '' && $today < $start) {
            continue;
        }
        if ($due !== '' && $today > $due) {
            continue;
        }
        $key = (string) ($phase['key'] ?? '');
        if ($conn !== null && cw_seo_mexico_short_urls_phase_is_complete($conn, $key)) {
            continue;
        }
        if (!cw_seo_mexico_short_urls_phase_may_start($conn, $phase)) {
            continue;
        }
        return $phase;
    }

    return null;
}

/**
 * @param array<string, mixed> $phase
 */
function cw_seo_mexico_short_urls_phase_may_start(?mysqli $conn, array $phase): bool
{
    $plan = is_array($phase['plan'] ?? null) ? $phase['plan'] : [];
    $today = date('Y-m-d');
    $start = (string) ($plan['start'] ?? '');
    if ($start !== '' && $today < $start) {
        return false;
    }
    $prev = (string) ($phase['requires_previous'] ?? '');
    if ($prev !== '' && $conn !== null && !cw_seo_mexico_short_urls_phase_is_complete($conn, $prev)) {
        $prog = cw_seo_mexico_short_urls_phase_progress($conn, $prev);
        if (($prog['pct'] ?? 0) < 90) {
            return false;
        }
    }
    return true;
}

function cw_seo_mexico_short_urls_phase_is_complete(mysqli $conn, string $phaseKey): bool
{
    if (function_exists('cw_seo_mexico_checklist_sync')) {
        cw_seo_mexico_checklist_sync($conn);
    }
    $stmt = $conn->prepare('SELECT done FROM cw_seo_mexico_checklist WHERE task_key = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $phaseKey);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row !== null && (int) ($row['done'] ?? 0) === 1;
}

/**
 * @return list<array{slug:string,label:string,geo_kind:string,estado?:string,estado_slug?:string}>
 */
function cw_seo_mexico_short_urls_phase_plazas(array $phase): array
{
    $geoKind = (string) ($phase['geo_kind'] ?? 'ciudad');
    $phaseKey = (string) ($phase['key'] ?? '');
    $root = function_exists('cw_seo_mexico_autofix_site_root') ? cw_seo_mexico_autofix_site_root() : null;
    if ($root === null) {
        return [];
    }
    $locFile = $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'mexico' . DIRECTORY_SEPARATOR . 'locations.php';
    if (!is_readable($locFile)) {
        return [];
    }
    $data = require $locFile;
    $out = [];

    if ($geoKind === 'ciudad') {
        if (!function_exists('cw_seo_mexico_priority_plazas')) {
            require_once __DIR__ . '/cw_seo_mexico_checklist.php';
        }
        $prioritySlugs = array_map(static fn($p) => (string) ($p['slug'] ?? ''), cw_seo_mexico_priority_plazas());
        $prioritySlugs = array_values(array_filter($prioritySlugs));
        foreach ($data['ciudades'] ?? [] as $slug => $city) {
            $slug = (string) $slug;
            if ($phaseKey === 'short_urls_fase1' && !in_array($slug, $prioritySlugs, true)) {
                continue;
            }
            if ($phaseKey === 'short_urls_fase2' && in_array($slug, $prioritySlugs, true)) {
                continue;
            }
            $estadoSlug = (string) ($city['estado'] ?? '');
            $estadoName = (string) (($data['estados'][$estadoSlug]['name'] ?? '') ?: '');
            $out[] = [
                'slug' => $slug,
                'label' => (string) ($city['name'] ?? $slug),
                'geo_kind' => 'ciudad',
                'estado' => $estadoName,
                'estado_slug' => $estadoSlug,
            ];
        }
    } elseif ($geoKind === 'estado') {
        foreach ($data['estados'] ?? [] as $slug => $estado) {
            $out[] = [
                'slug' => (string) $slug,
                'label' => (string) ($estado['name'] ?? $slug),
                'geo_kind' => 'estado',
            ];
        }
    } elseif ($geoKind === 'mixed' && !empty($phase['include_hub'])) {
        $pf = $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'mexico' . DIRECTORY_SEPARATOR . 'page-factory.php';
        if (is_readable($pf)) {
            require_once $pf;
            if (function_exists('mx_short_geo_slugs')) {
                foreach (mx_short_geo_slugs() as $slug) {
                    $geo = function_exists('mx_resolve_geo_slug') ? mx_resolve_geo_slug($slug) : null;
                    $out[] = [
                        'slug' => (string) $slug,
                        'label' => (string) $slug,
                        'geo_kind' => (string) ($geo['kind'] ?? 'ciudad'),
                    ];
                }
            }
        }
    }

    return $out;
}

/**
 * @return list<array<string, mixed>>
 */
function cw_seo_mexico_short_urls_phase_targets(array $phase): array
{
    $plazas = cw_seo_mexico_short_urls_phase_plazas($phase);
    $services = cw_seo_mexico_short_urls_service_slugs();
    $targets = [];
    $includeHub = !empty($phase['include_hub']);

    foreach ($plazas as $plaza) {
        $slug = (string) ($plaza['slug'] ?? '');
        $geoKind = (string) ($plaza['geo_kind'] ?? 'ciudad');
        if ($slug === '') {
            continue;
        }
        if ($includeHub) {
            $targets[] = cw_seo_mexico_short_urls_build_target($phase, $plaza, '', $geoKind, true);
        } else {
            foreach ($services as $svc) {
                $targets[] = cw_seo_mexico_short_urls_build_target($phase, $plaza, $svc, $geoKind, false);
            }
        }
    }

    return $targets;
}

/**
 * @param array<string, mixed> $plaza
 * @return array<string, mixed>
 */
function cw_seo_mexico_short_urls_build_target(array $phase, array $plaza, string $serviceSlug, string $geoKind, bool $isHub): array
{
    $slug = (string) ($plaza['slug'] ?? '');
    $phaseKey = (string) ($phase['key'] ?? '');
    if ($isHub) {
        $shortPath = "mexico/{$slug}/";
        $longPath = $geoKind === 'estado'
            ? "mexico/estados/{$slug}/"
            : "mexico/ciudades/{$slug}/";
        $targetKey = "short_hub:{$phaseKey}:{$slug}";
    } else {
        $shortPath = "mexico/{$slug}/servicios/{$serviceSlug}/";
        $longPath = $geoKind === 'estado'
            ? "mexico/estados/{$slug}/servicios/{$serviceSlug}/"
            : "mexico/ciudades/{$slug}/servicios/{$serviceSlug}/";
        $targetKey = "short_svc:{$phaseKey}:{$slug}:{$serviceSlug}";
    }

    return [
        'phase_key' => $phaseKey,
        'target_key' => $targetKey,
        'plaza_slug' => $slug,
        'plaza_label' => (string) ($plaza['label'] ?? $slug),
        'geo_kind' => $geoKind,
        'service_slug' => $serviceSlug,
        'is_hub' => $isHub,
        'short_path' => $shortPath,
        'long_path' => $longPath,
        'short_url' => 'https://conlineweb.com/' . $shortPath,
        'long_url' => 'https://conlineweb.com/' . $longPath,
    ];
}

function cw_seo_mexico_short_urls_target_key(array $target): string
{
    return (string) ($target['target_key'] ?? '');
}

/**
 * @return array{total:int,done:int,pending:int,pct:int,done?:bool}
 */
function cw_seo_mexico_short_urls_phase_progress(mysqli $conn, string $phaseKey): array
{
    if (!function_exists('cw_seo_mexico_ai_proposals_ensure_table')) {
        require_once __DIR__ . '/cw_seo_mexico_ai.php';
    }
    $phase = cw_seo_mexico_short_urls_phase($phaseKey);
    if ($phase === null) {
        return ['total' => 0, 'done' => 0, 'pending' => 0, 'pct' => 0];
    }
    $targets = cw_seo_mexico_short_urls_phase_targets($phase);
    $total = count($targets);
    if ($total === 0) {
        return ['total' => 0, 'done' => 0, 'pending' => 0, 'pct' => 0];
    }

    cw_seo_mexico_ai_proposals_ensure_table($conn);
    $done = 0;
    foreach ($targets as $t) {
        $tk = cw_seo_mexico_short_urls_target_key($t);
        $stmt = $conn->prepare(
            "SELECT id FROM cw_seo_mexico_ai_proposals
             WHERE target_key = ? AND status = 'applied' LIMIT 1"
        );
        if (!$stmt) {
            continue;
        }
        $stmt->bind_param('s', $tk);
        $stmt->execute();
        $res = $stmt->get_result();
        $has = $res && $res->fetch_assoc();
        $stmt->close();
        if ($has) {
            $done++;
        }
    }
    $pct = (int) round(($done / $total) * 100);
    return [
        'total' => $total,
        'done' => $done,
        'pending' => $total - $done,
        'pct' => $pct,
        'done_flag' => cw_seo_mexico_short_urls_phase_is_complete($conn, $phaseKey),
    ];
}

/**
 * Reglas editoriales / SEO para propuestas IA.
 */
function cw_seo_mexico_short_urls_ai_brief(): string
{
    return <<<'TXT'
ESTRATEGIA URLs CORTAS COMERCIALES (servicio × plaza):
- Objetivo: indexar /mexico/{plaza}/servicios/{servicio}/ para keywords alto volumen «{servicio} + {ciudad/estado}».
- NO duplicar la canónica larga (/mexico/ciudades|estados/{slug}/servicios/{svc}/).
- Corta = landing comercial (cotizar, FAQ local, industria local). Larga = hub/directorio.
- H1 corta: «Páginas web en Guadalajara» / «SEO en Monterrey» (intención directa).
- H1 larga: más descriptiva + estado («Desarrollo web profesional en Guadalajara, Jalisco»).
- Canonical: self en cada URL (corta canonical propia, larga canonical propia).
- Enlace cruzado obligatorio entre corta ↔ larga.
- Quitar 301 en la corta al activar; añadir al sitemap.xml o sitemap-blog según corresponda.
- Schema Service + areaServed (ciudad/estado).
- Servicios: desarrollo-web, software-a-medida, ecommerce, seo, inteligencia-artificial.
- NO indexar las 315 de golpe; seguir fases del checklist (Fase 1 → 4).
- Medir GSC 30–60 días entre fases; pausar si hay canibalización.
TXT;
}

/**
 * Sincroniza progreso y marca fase complete al 100% aplicado.
 */
function cw_seo_mexico_short_urls_sync_checklist(mysqli $conn, int $userId = 0): void
{
    require_once __DIR__ . '/cw_seo_mexico_ai.php';
    if (!function_exists('cw_seo_mexico_checklist_set_done')) {
        require_once __DIR__ . '/cw_seo_mexico_checklist.php';
    }
    foreach (cw_seo_mexico_short_urls_phases() as $phase) {
        $key = (string) ($phase['key'] ?? '');
        if ($key === '') {
            continue;
        }
        $prog = cw_seo_mexico_short_urls_phase_progress($conn, $key);
        $detail = [
            'strategy' => 'short_urls_commercial',
            'phase' => $phase['label'] ?? $key,
            'sitemap_target' => (int) ($phase['url_target'] ?? 0),
            'increment' => (int) ($phase['url_increment'] ?? 0),
            'progress' => $prog,
            'plan' => $phase['plan'] ?? [],
        ];
        $json = json_encode($detail, JSON_UNESCAPED_UNICODE);
        $stmt = $conn->prepare(
            'UPDATE cw_seo_mexico_checklist SET detail_json = ?, updated_at = NOW() WHERE task_key = ?'
        );
        if ($stmt) {
            $stmt->bind_param('ss', $json, $key);
            $stmt->execute();
            $stmt->close();
        }
        if ($prog['total'] > 0 && $prog['done'] >= $prog['total'] && !cw_seo_mexico_short_urls_phase_is_complete($conn, $key)) {
            cw_seo_mexico_checklist_set_done($conn, $key, true, $userId, 'Fase completada: ' . $prog['done'] . '/' . $prog['total'] . ' URLs aplicadas.');
            cw_seo_mexico_checklist_log_update(
                $conn,
                $key,
                'Fase completada — ' . ($phase['label'] ?? $key),
                'Sitemap objetivo ~' . (int) ($phase['url_target'] ?? 0) . ' URLs. Todas las propuestas de la fase fueron aplicadas.',
                $userId
            );
        }
    }
}

/**
 * Encola propuestas IA para la fase activa (cron / autonomía).
 *
 * @return array{ok:bool,created:list<array<string,mixed>>,skipped?:string,phase?:string}
 */
function cw_seo_mexico_short_urls_autonomy_enqueue(mysqli $conn, int $userId = 0, int $maxBatch = 3): array
{
    require_once __DIR__ . '/cw_seo_mexico_ai.php';

    $active = cw_seo_mexico_short_urls_active_phase($conn);
    if ($active === null) {
        return ['ok' => true, 'created' => [], 'skipped' => 'Ninguna fase de URLs cortas en ventana activa.'];
    }
    if (!cw_seo_mexico_short_urls_phase_may_start($conn, $active)) {
        return ['ok' => true, 'created' => [], 'skipped' => 'Fase ' . ($active['label'] ?? '') . ' aún no puede iniciar (dependencia o GSC).'];
    }

    $phaseKey = (string) ($active['key'] ?? '');
    $targets = cw_seo_mexico_short_urls_phase_targets($active);
    $openKeys = cw_seo_mexico_ai_autonomy_open_target_keys($conn);
    $created = [];

    foreach ($targets as $target) {
        if (count($created) >= $maxBatch) {
            break;
        }
        $tk = cw_seo_mexico_short_urls_target_key($target);
        if ($tk === '' || in_array($tk, $openKeys, true)) {
            continue;
        }
        // Ya aplicada
        $stmt = $conn->prepare(
            "SELECT id FROM cw_seo_mexico_ai_proposals WHERE target_key = ? AND status = 'applied' LIMIT 1"
        );
        if ($stmt) {
            $stmt->bind_param('s', $tk);
            $stmt->execute();
            $res = $stmt->get_result();
            $applied = $res && $res->fetch_assoc();
            $stmt->close();
            if ($applied) {
                continue;
            }
        }
        // Pendiente/working
        $stmt = $conn->prepare(
            "SELECT id FROM cw_seo_mexico_ai_proposals WHERE target_key = ? AND status IN ('pending','working') LIMIT 1"
        );
        if ($stmt) {
            $stmt->bind_param('s', $tk);
            $stmt->execute();
            $res = $stmt->get_result();
            $pending = $res && $res->fetch_assoc();
            $stmt->close();
            if ($pending) {
                continue;
            }
        }

        $result = cw_seo_mexico_ai_propose_short_url_commercial($conn, $target, $userId);
        if (!empty($result['ok'])) {
            $created[] = $result;
        }
    }

    cw_seo_mexico_short_urls_sync_checklist($conn, $userId);

    return [
        'ok' => true,
        'created' => $created,
        'phase' => $phaseKey,
        'phase_label' => (string) ($active['label'] ?? $phaseKey),
    ];
}

/**
 * Resumen para UI checklist.
 *
 * @return array<string, mixed>
 */
function cw_seo_mexico_short_urls_strategy_summary(mysqli $conn): array
{
    $phases = cw_seo_mexico_short_urls_phases();
    $active = cw_seo_mexico_short_urls_active_phase($conn);
    $rows = [];
    foreach ($phases as $p) {
        $key = (string) ($p['key'] ?? '');
        $prog = cw_seo_mexico_short_urls_phase_progress($conn, $key);
        $plan = is_array($p['plan'] ?? null) ? $p['plan'] : [];
        $rows[] = [
            'key' => $key,
            'label' => (string) ($p['label'] ?? $key),
            'start' => (string) ($plan['start'] ?? ''),
            'due' => (string) ($plan['due'] ?? ''),
            'sitemap_target' => (int) ($p['url_target'] ?? 0),
            'increment' => (int) ($p['url_increment'] ?? 0),
            'progress' => $prog,
            'is_active' => $active !== null && ($active['key'] ?? '') === $key,
            'is_done' => cw_seo_mexico_short_urls_phase_is_complete($conn, $key),
            'optional' => !empty($p['optional']),
        ];
    }
    return [
        'baseline_sitemap' => 1202,
        'active_phase' => $active,
        'phases' => $rows,
        'rules_do' => [
            'Indexar cortas solo servicio×plaza con contenido comercial único',
            'Empezar con 10 plazas prioritarias (Fase 1)',
            'Apuntar a «{servicio} + {ciudad/estado}»',
            'Medir GSC antes de Fase 2',
        ],
        'rules_dont' => [
            'Meter las 315 de golpe con 301 o contenido idéntico',
            'Competir corta vs larga con el mismo H1',
            'Cambiar canonicals de URLs ya indexadas sin plan',
        ],
    ];
}

/**
 * Datos completos para el módulo UI en seo_mexico_checklist.php.
 *
 * @return array<string, mixed>
 */
function cw_seo_mexico_short_urls_module_data(mysqli $conn): array
{
    require_once __DIR__ . '/cw_seo_mexico_ai.php';
    cw_seo_mexico_ai_proposals_ensure_table($conn);

    $summary = cw_seo_mexico_short_urls_strategy_summary($conn);
    $services = cw_seo_mexico_short_urls_service_slugs();
    $serviceLabels = [
        'desarrollo-web' => 'Desarrollo web',
        'software-a-medida' => 'Software a medida',
        'ecommerce' => 'Tienda online',
        'seo' => 'SEO y posicionamiento',
        'inteligencia-artificial' => 'Inteligencia artificial',
    ];

    $statusByTarget = [];
    $res = $conn->query(
        "SELECT target_key, status, id, title, created_at
         FROM cw_seo_mexico_ai_proposals
         WHERE kind = 'short_url_commercial'
         ORDER BY id DESC"
    );
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $tk = (string) ($row['target_key'] ?? '');
            if ($tk !== '' && !isset($statusByTarget[$tk])) {
                $statusByTarget[$tk] = $row;
            }
        }
        $res->free();
    }

    $phasesOut = [];
    $allRows = [];
    $baseline = (int) ($summary['baseline_sitemap'] ?? 1202);
    $runningSitemap = $baseline;

    foreach (cw_seo_mexico_short_urls_phases() as $phase) {
        $key = (string) ($phase['key'] ?? '');
        $targets = cw_seo_mexico_short_urls_phase_targets($phase);
        $actualIncrement = count($targets);
        $runningSitemap += $actualIncrement;
        $prog = cw_seo_mexico_short_urls_phase_progress($conn, $key);
        $plan = is_array($phase['plan'] ?? null) ? $phase['plan'] : [];

        $urlRows = [];
        foreach ($targets as $t) {
            $tk = cw_seo_mexico_short_urls_target_key($t);
            $prop = $statusByTarget[$tk] ?? null;
            $st = 'pendiente';
            $stLabel = 'Pendiente de propuesta';
            if ($prop !== null) {
                $ps = (string) ($prop['status'] ?? '');
                if ($ps === 'applied') {
                    $st = 'aplicada';
                    $stLabel = 'Aplicada';
                } elseif (in_array($ps, ['pending', 'working'], true)) {
                    $st = 'propuesta';
                    $stLabel = 'En propuestas IA';
                } elseif ($ps === 'rejected') {
                    $st = 'rechazada';
                    $stLabel = 'Rechazada';
                }
            }
            $row = [
                'phase_key' => $key,
                'phase_label' => (string) ($phase['label'] ?? $key),
                'target_key' => $tk,
                'plaza_slug' => (string) ($t['plaza_slug'] ?? ''),
                'plaza_label' => (string) ($t['plaza_label'] ?? ''),
                'service_slug' => (string) ($t['service_slug'] ?? ''),
                'service_label' => !empty($t['is_hub'])
                    ? 'Hub general'
                    : ($serviceLabels[(string) ($t['service_slug'] ?? '')] ?? (string) ($t['service_slug'] ?? '')),
                'is_hub' => !empty($t['is_hub']),
                'short_url' => (string) ($t['short_url'] ?? ''),
                'long_url' => (string) ($t['long_url'] ?? ''),
                'status' => $st,
                'status_label' => $stLabel,
                'proposal_id' => (int) ($prop['id'] ?? 0),
            ];
            $urlRows[] = $row;
            $allRows[] = $row;
        }

        $phasesOut[] = [
            'key' => $key,
            'label' => (string) ($phase['label'] ?? $key),
            'scope' => (string) ($phase['scope'] ?? ''),
            'optional' => !empty($phase['optional']),
            'gsc_gate' => !empty($phase['gsc_gate']),
            'requires_previous' => (string) ($phase['requires_previous'] ?? ''),
            'plan' => $plan,
            'actual_url_count' => $actualIncrement,
            'sitemap_after' => $runningSitemap,
            'progress' => $prog,
            'is_active' => !empty($summary['active_phase']) && ($summary['active_phase']['key'] ?? '') === $key,
            'is_done' => cw_seo_mexico_short_urls_phase_is_complete($conn, $key),
            'urls' => $urlRows,
        ];
    }

    $pendingShortProps = 0;
    $r2 = $conn->query(
        "SELECT COUNT(*) AS c FROM cw_seo_mexico_ai_proposals
         WHERE kind = 'short_url_commercial' AND status IN ('pending','working')"
    );
    if ($r2) {
        $pendingShortProps = (int) (($r2->fetch_assoc()['c'] ?? 0));
        $r2->free();
    }

    return [
        'summary' => $summary,
        'services' => $services,
        'service_labels' => $serviceLabels,
        'phases' => $phasesOut,
        'all_urls' => $allRows,
        'total_urls' => count($allRows),
        'baseline_sitemap' => $baseline,
        'final_sitemap' => $runningSitemap,
        'pending_proposals' => $pendingShortProps,
        'purpose' => [
            'title' => 'Indexar URLs cortas comerciales para posicionar servicios por región',
            'lead' => 'Convertir /mexico/{plaza}/servicios/{servicio}/ en landings comerciales indexables '
                . 'orientadas a keywords de alto volumen («SEO Guadalajara», «páginas web Monterrey», etc.), '
                . 'sin duplicar el contenido del hub canónico largo.',
            'benefits' => [
                'Captar búsquedas locales con intención de compra directa',
                'URLs cortas alineadas a cómo busca la gente (no rutas largas /ciudades/…)',
                'Ampliar el sitemap de ~1 202 a ~1 502 URLs indexables de forma controlada',
                'Escalar por fases con medición GSC entre etapas',
            ],
        ],
        'execution' => [
            'cron' => 'analytics/cron_seo_mexico_ai_propose.php',
            'cron_schedule' => '2–3 veces al día (ej. 08:00, 14:00, 20:00)',
            'proposal_kind' => 'short_url_commercial',
            'per_run' => 'Hasta 2 propuestas URL corta + propuestas editoriales (blog/hub)',
            'approval' => 'Checklist → Propuestas → Aprobar (nunca publica sola)',
            'on_apply' => [
                'Guarda contenido en includes/mexico/short-url-commercial-overrides.php',
                'Registra progreso en tarea short_urls_faseN del checklist',
                'Pendiente técnico: quitar 301, añadir al sitemap e /indice/ al consolidar fase',
            ],
            'content_generated' => [
                'title', 'description', 'h1', 'hero_subtitle',
                'faq local (3–4)', 'primary_keyword', 'schema_area_served',
                'cross_link_label → hub largo', 'rationale SEO',
            ],
        ],
        'rules_do' => $summary['rules_do'] ?? [],
        'rules_dont' => $summary['rules_dont'] ?? [],
    ];
}
