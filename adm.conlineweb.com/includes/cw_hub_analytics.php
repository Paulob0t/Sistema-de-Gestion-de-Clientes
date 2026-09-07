<?php
require_once __DIR__ . '/cw_hub_migrate.php';
require_once __DIR__ . '/cw_hub_config.php';
require_once __DIR__ . '/cw_hub_geo.php';

/** @return array<string,string> */
function cw_analytics_web_options(): array
{
    return [
        'all' => 'Todos',
        'mx' => 'MX · .com',
        'us' => 'US · /us',
        'cl' => 'CL · .cl',
    ];
}

function cw_analytics_normalize_web(?string $web): string
{
    $web = strtolower(trim((string) ($web ?? '')));
    return isset(cw_analytics_web_options()[$web]) ? $web : 'all';
}

/**
 * Filtro activo del sitio (Todos / MX / US / CL).
 * Pasar $web para fijarlo; sin argumento solo lee el valor actual.
 */
function cw_analytics_web_filter(?string $web = null): string
{
    static $current = 'all';
    if ($web !== null) {
        $current = cw_analytics_normalize_web($web);
    }

    return $current;
}

/**
 * Condición SQL por sitio web (MX / US / CL) sobre una columna URL.
 */
function cw_analytics_url_web_sql(string $column, string $web): string
{
    $web = cw_analytics_normalize_web($web);
    if ($web === 'all') {
        return '';
    }

    // /us como segmento de ruta (no /usuario). [.] evita problemas de escapes en MySQL REGEXP.
    $us = "(
        {$column} LIKE '%://conlineweb.com/us/%'
        OR {$column} LIKE '%://www.conlineweb.com/us/%'
        OR {$column} LIKE '%://conlineweb.com/us?%'
        OR {$column} LIKE '%://www.conlineweb.com/us?%'
        OR {$column} LIKE '%://conlineweb.com/us#%'
        OR {$column} LIKE '%://www.conlineweb.com/us#%'
        OR {$column} REGEXP '://(www[.])?conlineweb[.]com/us$'
    )";

    if ($web === 'cl') {
        return " AND (
            {$column} LIKE '%conlineweb.cl%'
            OR {$column} LIKE '%conlineweb.cl/%'
        )";
    }

    if ($web === 'us') {
        return " AND {$us}";
    }

    // MX: .com excluyendo /us y .cl
    return " AND (
        ({$column} LIKE '%://conlineweb.com%' OR {$column} LIKE '%://www.conlineweb.com%')
        AND {$column} NOT LIKE '%://conlineweb.cl%'
        AND {$column} NOT LIKE '%://www.conlineweb.cl%'
        AND NOT {$us}
    )";
}

/** Columna `web` hermana de una columna URL (p.url → p.web). */
function cw_analytics_web_column_from_url_column(string $urlColumn): string
{
    if (str_contains($urlColumn, '.')) {
        $alias = explode('.', $urlColumn, 2)[0];

        return $alias . '.web';
    }

    return 'web';
}

/**
 * Filtro SQL: URLs de producción (+ opcional MX/US/CL).
 * Usa columna `web` si existe valor; si no, infiere por URL.
 */
function cw_analytics_url_scope_sql(string $column): string
{
    $web = cw_analytics_web_filter();
    $useWebCol = !empty($GLOBALS['CW_HUB_ANALYTICS_WEB_COL']);
    $webCol = cw_analytics_web_column_from_url_column($column);

    if ($web !== 'all') {
        $urlPart = trim(cw_analytics_url_web_sql($column, $web));
        $urlInner = preg_replace('/^\s*AND\s+/i', '', $urlPart) ?: '0';
        if ($useWebCol) {
            return " AND (
                LOWER(TRIM(COALESCE({$webCol},''))) = '{$web}'
                OR (({$webCol} IS NULL OR {$webCol} = '') AND ({$urlInner}))
            )";
        }

        return " AND ({$urlInner})";
    }

    if (!CW_HUB_ANALYTICS_PRODUCTION_ONLY) {
        return '';
    }

    $parts = [];
    foreach (CW_HUB_TRACKING_HOSTS as $host) {
        $host = preg_replace('/[^a-z0-9.\-]/', '', strtolower($host));
        if ($host !== '') {
            $parts[] = "{$column} LIKE '%://{$host}%'";
            // Respaldo sin esquema (URLs raras)
            $parts[] = "{$column} LIKE '%{$host}%'";
        }
    }
    if ($useWebCol) {
        $parts[] = "LOWER(TRIM(COALESCE({$webCol},''))) IN ('mx','us','cl')";
    }

    return $parts ? ' AND (' . implode(' OR ', $parts) . ')' : '';
}

function cw_analytics_lead_scope_sql(): string
{
    $web = cw_analytics_web_filter();
    if ($web !== 'all') {
        $urlPart = trim(cw_analytics_url_web_sql('pagina_origen', $web));
        $urlInner = preg_replace('/^\s*AND\s+/i', '', $urlPart) ?: '0';

        return " AND (
            LOWER(TRIM(COALESCE(web,''))) = '{$web}'
            OR ((web IS NULL OR web = '') AND ({$urlInner}))
        )";
    }

    if (!CW_HUB_ANALYTICS_PRODUCTION_ONLY) {
        return '';
    }
    $urlScope = trim(cw_analytics_url_scope_sql('pagina_origen'));
    if ($urlScope === '') {
        return '';
    }
    $inner = preg_replace('/^\s*AND\s+/i', '', $urlScope);

    return " AND (pagina_origen IS NULL OR pagina_origen = '' OR {$inner})";
}

function cw_analytics_overview(mysqli $conn, string $from, string $to): array
{
    $pvScope = cw_analytics_url_scope_sql('url');
    $pv = $conn->prepare("SELECT COUNT(*) c FROM cw_analytics_pageviews WHERE viewed_at BETWEEN ? AND ?{$pvScope}");
    $pv->bind_param('ss', $from, $to);
    $pv->execute();
    $pageviews = (int) ($pv->get_result()->fetch_assoc()['c'] ?? 0);
    $pv->close();

    $sessScope = cw_analytics_url_scope_sql('landing_url');
    $sess = $conn->prepare("SELECT COUNT(*) c, COUNT(DISTINCT visitor_id) u FROM cw_analytics_sessions WHERE first_seen BETWEEN ? AND ?{$sessScope}");
    $sess->bind_param('ss', $from, $to);
    $sess->execute();
    $sr = $sess->get_result()->fetch_assoc();
    $sessions = (int) ($sr['c'] ?? 0);
    $users = (int) ($sr['u'] ?? 0);
    $sess->close();

    $avg = $conn->prepare("SELECT AVG(time_on_page) a FROM cw_analytics_pageviews WHERE viewed_at BETWEEN ? AND ? AND time_on_page > 0{$pvScope}");
    $avg->bind_param('ss', $from, $to);
    $avg->execute();
    $avgSec = round((float) ($avg->get_result()->fetch_assoc()['a'] ?? 0));
    $avg->close();

    $leadScope = cw_analytics_lead_scope_sql();
    $leads = $conn->prepare("SELECT COUNT(*) c FROM leads WHERE eliminado = 0 AND origen_web = 1 AND fecha_registro BETWEEN ? AND ?{$leadScope}");
    $leads->bind_param('ss', $from, $to);
    $leads->execute();
    $leadsCount = (int) ($leads->get_result()->fetch_assoc()['c'] ?? 0);
    $leads->close();

    $qual = $conn->prepare("SELECT COUNT(*) c FROM leads WHERE eliminado = 0 AND origen_web = 1 AND pipeline_estado IN ('calificado','seguimiento','propuesta','cerrado') AND fecha_registro BETWEEN ? AND ?{$leadScope}");
    $qual->bind_param('ss', $from, $to);
    $qual->execute();
    $qualified = (int) ($qual->get_result()->fetch_assoc()['c'] ?? 0);
    $qual->close();

    $closed = $conn->prepare("SELECT COUNT(*) c FROM leads WHERE eliminado = 0 AND origen_web = 1 AND pipeline_estado = 'cerrado' AND fecha_registro BETWEEN ? AND ?{$leadScope}");
    $closed->bind_param('ss', $from, $to);
    $closed->execute();
    $closedCount = (int) ($closed->get_result()->fetch_assoc()['c'] ?? 0);
    $closed->close();

    return compact('pageviews', 'sessions', 'users', 'avgSec', 'leadsCount', 'qualified', 'closedCount');
}

function cw_analytics_top_pages(mysqli $conn, string $from, string $to, int $limit = 15): array
{
    $pvScope = cw_analytics_url_scope_sql('p.url');
    $pv2Scope = cw_analytics_url_scope_sql('pv2.url');
    $sql = "SELECT p.path, MAX(p.title) AS title, COUNT(*) AS visitas,
            COUNT(DISTINCT p.session_id) AS usuarios,
            ROUND(AVG(p.time_on_page)) AS tiempo_prom,
            (SELECT COUNT(*) FROM cw_analytics_events e
             INNER JOIN cw_analytics_pageviews pv2 ON pv2.session_id = e.session_id
             WHERE e.event_type = 'conversion' AND pv2.path = p.path AND e.created_at BETWEEN ? AND ?{$pv2Scope}) AS conversiones
            FROM cw_analytics_pageviews p
            WHERE p.viewed_at BETWEEN ? AND ? AND p.path IS NOT NULL AND p.path != ''{$pvScope}
            GROUP BY p.path ORDER BY visitas DESC LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssssi', $from, $to, $from, $to, $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function cw_analytics_daily(mysqli $conn, string $from, string $to): array
{
    $pvScope = cw_analytics_url_scope_sql('url');
    $sql = "SELECT DATE(viewed_at) d, COUNT(*) v FROM cw_analytics_pageviews
            WHERE viewed_at BETWEEN ? AND ?{$pvScope} GROUP BY DATE(viewed_at) ORDER BY d";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function cw_analytics_leads_by_service(mysqli $conn, string $from, string $to): array
{
    $leadScope = cw_analytics_lead_scope_sql();
    $sql = "SELECT COALESCE(servicio,'otro') s, COUNT(*) c FROM leads
            WHERE eliminado = 0 AND origen_web = 1 AND fecha_registro BETWEEN ? AND ?{$leadScope}
            GROUP BY s ORDER BY c DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function cw_format_duration(int $sec): string
{
    if ($sec < 60) {
        return $sec . 's';
    }
    $m = floor($sec / 60);
    $s = $sec % 60;
    return $m . 'm ' . $s . 's';
}

function cw_pipeline_counts(mysqli $conn): array
{
    $out = [];
    foreach (array_keys(CW_HUB_PIPELINE) as $st) {
        $out[$st] = 0;
    }
    $r = $conn->query("SELECT pipeline_estado, COUNT(*) c FROM leads WHERE eliminado = 0 GROUP BY pipeline_estado");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $k = $row['pipeline_estado'] ?: 'nuevo';
            $out[$k] = (int) $row['c'];
        }
    }
    return $out;
}

/**
 * Funnel comercial: visitas → leads → cerrados por ruta.
 */
function cw_analytics_funnel(mysqli $conn, string $from, string $to, int $limit = 30): array
{
    $pages = cw_analytics_top_pages($conn, $from, $to, $limit);
    $leadCounts = [];

    $leadScope = cw_analytics_lead_scope_sql();
    $q = $conn->prepare("SELECT pagina_origen, pipeline_estado FROM leads
        WHERE eliminado = 0 AND origen_web = 1 AND fecha_registro BETWEEN ? AND ?
        AND pagina_origen IS NOT NULL AND pagina_origen != ''{$leadScope}");
    $q->bind_param('ss', $from, $to);
    $q->execute();
    $res = $q->get_result();
    while ($row = $res->fetch_assoc()) {
        $path = parse_url($row['pagina_origen'], PHP_URL_PATH);
        if (!$path) {
            $path = '/';
        }
        if (!isset($leadCounts[$path])) {
            $leadCounts[$path] = ['leads' => 0, 'cerrados' => 0, 'calificados' => 0];
        }
        $leadCounts[$path]['leads']++;
        $pe = $row['pipeline_estado'] ?? 'nuevo';
        if ($pe === 'cerrado') {
            $leadCounts[$path]['cerrados']++;
        }
        if (in_array($pe, ['calificado', 'seguimiento', 'propuesta', 'cerrado'], true)) {
            $leadCounts[$path]['calificados']++;
        }
    }
    $q->close();

    foreach ($pages as &$p) {
        $path = $p['path'];
        $lc = $leadCounts[$path] ?? ['leads' => 0, 'cerrados' => 0, 'calificados' => 0];
        $vis = (int) $p['visitas'];
        $p['leads'] = $lc['leads'];
        $p['calificados'] = $lc['calificados'];
        $p['cerrados'] = $lc['cerrados'];
        $p['tasa_lead'] = $vis > 0 ? round(($lc['leads'] / $vis) * 100, 2) : 0;
        $p['tasa_cierre'] = $lc['leads'] > 0 ? round(($lc['cerrados'] / $lc['leads']) * 100, 2) : 0;
    }
    unset($p);

    usort($pages, fn($a, $b) => ($b['leads'] <=> $a['leads']) ?: ($b['visitas'] <=> $a['visitas']));
    return $pages;
}

function cw_analytics_funnel_totals(array $funnel): array
{
    $t = ['visitas' => 0, 'leads' => 0, 'calificados' => 0, 'cerrados' => 0];
    foreach ($funnel as $row) {
        $t['visitas'] += (int) $row['visitas'];
        $t['leads'] += (int) $row['leads'];
        $t['calificados'] += (int) $row['calificados'];
        $t['cerrados'] += (int) $row['cerrados'];
    }
    return $t;
}

function cw_analytics_hourly_today(mysqli $conn): array
{
    [$start, $end] = cw_hub_period_dates('today');
    $pvScope = cw_analytics_url_scope_sql('url');
    $sql = "SELECT HOUR(viewed_at) h, COUNT(*) v FROM cw_analytics_pageviews
            WHERE viewed_at BETWEEN ? AND ?{$pvScope} GROUP BY HOUR(viewed_at) ORDER BY h";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $start, $end);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $out = array_fill(0, 24, 0);
    foreach ($rows as $r) {
        $out[(int) $r['h']] = (int) $r['v'];
    }
    $labels = [];
    $values = [];
    for ($i = 0; $i < 24; $i++) {
        $labels[] = sprintf('%02d:00', $i);
        $values[] = $out[$i];
    }
    return [
        'labels' => $labels,
        'values' => $values,
        'timezone' => CW_HUB_TIMEZONE,
    ];
}

function cw_analytics_active_sessions(mysqli $conn, int $minutes = 5): int
{
    $since = (new DateTimeImmutable('now', cw_hub_tz_mx()))
        ->modify("-{$minutes} minutes")
        ->format('Y-m-d H:i:s');
    $sessScope = cw_analytics_url_scope_sql('landing_url');
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT session_id) c FROM cw_analytics_sessions WHERE last_seen >= ?{$sessScope}");
    $stmt->bind_param('s', $since);
    $stmt->execute();
    $c = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    return $c;
}

function cw_analytics_recent_leads(mysqli $conn, int $limit = 8): array
{
    $leadScope = cw_analytics_lead_scope_sql();
    $sql = "SELECT id, nombre, servicio, pagina_origen, pipeline_estado, fecha_registro
            FROM leads WHERE eliminado = 0 AND origen_web = 1{$leadScope} ORDER BY fecha_registro DESC LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function cw_analytics_live_snapshot(mysqli $conn): array
{
    [$todayFrom, $todayTo] = cw_hub_period_dates('today');
    $overview = cw_analytics_overview($conn, $todayFrom, $todayTo);
    $hourly = cw_analytics_hourly_today($conn);

    $remCount = 0;
    $now = cw_hub_now();
    $rq = $conn->prepare('SELECT COUNT(*) c FROM cw_lead_actividades WHERE proxima_accion IS NOT NULL AND proxima_accion <= ? AND recordatorio_notificado = 0');
    if ($rq) {
        $rq->bind_param('s', $now);
        $rq->execute();
        $remCount = (int) ($rq->get_result()->fetch_assoc()['c'] ?? 0);
        $rq->close();
    }

    return [
        'generated_at' => cw_hub_now_iso(),
        'timezone' => CW_HUB_TIMEZONE,
        'active_sessions' => cw_analytics_active_sessions($conn, 5),
        'today' => $overview,
        'hourly' => $hourly,
        'recent_leads' => cw_analytics_recent_leads($conn, 6),
        'pending_reminders' => $remCount,
    ];
}

/** Métricas de visitas sin datos de CRM/leads. */
function cw_analytics_visits_overview(mysqli $conn, string $from, string $to): array
{
    $base = cw_analytics_overview($conn, $from, $to);

    return [
        'pageviews' => $base['pageviews'],
        'sessions' => $base['sessions'],
        'users' => $base['users'],
        'avgSec' => $base['avgSec'],
        'pagesPerSession' => cw_analytics_pages_per_session($conn, $from, $to),
    ];
}

function cw_analytics_pages_per_session(mysqli $conn, string $from, string $to): float
{
    $sessScope = cw_analytics_url_scope_sql('landing_url');
    $stmt = $conn->prepare("SELECT AVG(pages_count) a FROM cw_analytics_sessions
        WHERE first_seen BETWEEN ? AND ? AND pages_count > 0{$sessScope}");
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $avg = round((float) ($stmt->get_result()->fetch_assoc()['a'] ?? 0), 1);
    $stmt->close();

    return $avg;
}

function cw_analytics_daily_users(mysqli $conn, string $from, string $to): array
{
    $sessScope = cw_analytics_url_scope_sql('landing_url');
    $sql = "SELECT DATE(first_seen) d, COUNT(DISTINCT visitor_id) u FROM cw_analytics_sessions
            WHERE first_seen BETWEEN ? AND ?{$sessScope} GROUP BY DATE(first_seen) ORDER BY d";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

function cw_analytics_by_device(mysqli $conn, string $from, string $to, int $limit = 6): array
{
    return cw_analytics_group_sessions($conn, $from, $to, 'device_type', $limit);
}

/** Conteo de sesiones por tipo de dispositivo en el periodo. */
function cw_analytics_sessions_device_counts(mysqli $conn, string $from, string $to): array
{
    $sessScope = cw_analytics_url_scope_sql('landing_url');
    $sql = "SELECT
            SUM(CASE WHEN device_type = 'mobile' THEN 1 ELSE 0 END) AS mobile,
            SUM(CASE WHEN device_type = 'tablet' THEN 1 ELSE 0 END) AS tablet,
            SUM(CASE WHEN device_type = 'desktop' THEN 1 ELSE 0 END) AS desktop,
            SUM(CASE WHEN device_type IS NULL OR device_type = '' OR device_type NOT IN ('mobile','tablet','desktop') THEN 1 ELSE 0 END) AS other
        FROM cw_analytics_sessions
        WHERE first_seen BETWEEN ? AND ?{$sessScope}";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    $mobile = (int) ($row['mobile'] ?? 0);
    $tablet = (int) ($row['tablet'] ?? 0);
    $desktop = (int) ($row['desktop'] ?? 0);
    $other = (int) ($row['other'] ?? 0);

    return [
        'mobile' => $mobile,
        'tablet' => $tablet,
        'mobile_total' => $mobile + $tablet,
        'desktop' => $desktop,
        'pc' => $desktop,
        'other' => $other,
        'total' => $mobile + $tablet + $desktop + $other,
    ];
}

function cw_analytics_device_label(?string $deviceType): string
{
    return match (strtolower(trim((string) $deviceType))) {
        'mobile', 'tablet' => 'Celular',
        'desktop' => 'PC',
        default => '—',
    };
}

function cw_analytics_device_icon(?string $deviceType): string
{
    return match (strtolower(trim((string) $deviceType))) {
        'mobile', 'tablet' => 'phone',
        'desktop' => 'laptop',
        default => 'question-circle',
    };
}

function cw_analytics_device_screen_label(array $row): string
{
    $viewportW = (int) ($row['viewport_w'] ?? 0);
    $screenW = (int) ($row['screen_w'] ?? 0);
    if ($viewportW <= 0 && $screenW <= 0) {
        return '';
    }
    if ($viewportW > 0 && $screenW > 0 && $viewportW !== $screenW) {
        return $viewportW . '×' . $screenW . ' px';
    }
    $w = $viewportW > 0 ? $viewportW : $screenW;

    return $w . ' px';
}

function cw_analytics_count_devices_in_sessions(array $sessions): array
{
    $mobile = 0;
    $desktop = 0;
    $other = 0;
    foreach ($sessions as $s) {
        $type = strtolower(trim((string) ($s['device_type'] ?? '')));
        if ($type === 'mobile' || $type === 'tablet') {
            $mobile++;
        } elseif ($type === 'desktop') {
            $desktop++;
        } else {
            $other++;
        }
    }

    return [
        'mobile' => $mobile,
        'mobile_total' => $mobile,
        'desktop' => $desktop,
        'pc' => $desktop,
        'other' => $other,
        'total' => count($sessions),
    ];
}

function cw_analytics_by_browser(mysqli $conn, string $from, string $to, int $limit = 6): array
{
    return cw_analytics_group_sessions($conn, $from, $to, 'browser', $limit);
}

function cw_analytics_by_os(mysqli $conn, string $from, string $to, int $limit = 6): array
{
    return cw_analytics_group_sessions($conn, $from, $to, 'os', $limit);
}

function cw_analytics_by_utm(mysqli $conn, string $from, string $to, int $limit = 8): array
{
    return cw_analytics_group_sessions($conn, $from, $to, 'utm_source', $limit, '(directo)');
}

function cw_analytics_by_country(mysqli $conn, string $from, string $to, int $limit = 8): array
{
    $sessScope = cw_analytics_url_scope_sql('landing_url');
    $sql = "SELECT COALESCE(NULLIF(country, ''), 'Desconocido') AS label, COUNT(*) c
            FROM cw_analytics_sessions
            WHERE first_seen BETWEEN ? AND ?{$sessScope}
            GROUP BY label ORDER BY c DESC LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssi', $from, $to, $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

function cw_analytics_top_referrers(mysqli $conn, string $from, string $to, int $limit = 8): array
{
    $sessScope = cw_analytics_url_scope_sql('landing_url');
    $sql = "SELECT referrer FROM cw_analytics_sessions
            WHERE first_seen BETWEEN ? AND ?{$sessScope}";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $res = $stmt->get_result();
    $counts = [];
    while ($row = $res->fetch_assoc()) {
        $label = cw_analytics_referrer_label((string) ($row['referrer'] ?? ''));
        $counts[$label] = ($counts[$label] ?? 0) + 1;
    }
    $stmt->close();

    arsort($counts);
    $out = [];
    foreach (array_slice($counts, 0, $limit, true) as $label => $c) {
        $out[] = ['label' => $label, 'c' => $c];
    }

    return $out;
}

function cw_analytics_group_sessions(
    mysqli $conn,
    string $from,
    string $to,
    string $column,
    int $limit,
    string $emptyLabel = 'desconocido'
): array {
    $allowed = ['device_type', 'browser', 'os', 'utm_source'];
    if (!in_array($column, $allowed, true)) {
        return [];
    }

    $sessScope = cw_analytics_url_scope_sql('landing_url');
    $sql = "SELECT COALESCE(NULLIF({$column}, ''), ?) AS label, COUNT(*) c
            FROM cw_analytics_sessions
            WHERE first_seen BETWEEN ? AND ?{$sessScope}
            GROUP BY label ORDER BY c DESC LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssi', $emptyLabel, $from, $to, $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

function cw_analytics_referrer_label(string $ref): string
{
    $ref = trim($ref);
    if ($ref === '') {
        return 'Directo / sin referrer';
    }
    $host = parse_url($ref, PHP_URL_HOST);
    if ($host) {
        return strtolower($host);
    }

    return mb_substr($ref, 0, 80);
}

function cw_analytics_period_delta(mysqli $conn, string $from, string $to): array
{
    $fromUtc = new DateTimeImmutable($from, cw_hub_tz_mx());
    $toUtc = new DateTimeImmutable($to, cw_hub_tz_mx());
    $fromMx = $fromUtc;
    $toMx = $toUtc;
    $days = max(1, (int) $fromMx->diff($toMx)->days + 1);
    $prevFromMx = $fromMx->modify("-{$days} days")->setTime(0, 0, 0);
    $prevToMx = $fromMx->modify('-1 day')->setTime(23, 59, 59);
    $prevFrom = $prevFromMx->format('Y-m-d H:i:s');
    $prevTo = $prevToMx->format('Y-m-d H:i:s');

    $current = cw_analytics_visits_overview($conn, $from, $to);
    $previous = cw_analytics_visits_overview($conn, $prevFrom, $prevTo);

    $pct = static function (float $cur, float $prev): float {
        if ($prev <= 0) {
            return $cur > 0 ? 100.0 : 0.0;
        }

        return round((($cur - $prev) / $prev) * 100, 1);
    };

    return [
        'pageviews' => $pct((float) $current['pageviews'], (float) $previous['pageviews']),
        'users' => $pct((float) $current['users'], (float) $previous['users']),
        'sessions' => $pct((float) $current['sessions'], (float) $previous['sessions']),
        'avgSec' => $pct((float) $current['avgSec'], (float) $previous['avgSec']),
    ];
}

function cw_analytics_visits_live_snapshot(mysqli $conn): array
{
    [$todayFrom, $todayTo] = cw_hub_period_dates('today');

    return [
        'generated_at' => cw_hub_now_iso(),
        'timezone' => CW_HUB_TIMEZONE,
        'active_sessions' => cw_analytics_active_sessions($conn, 5),
        'today' => cw_analytics_visits_overview($conn, $todayFrom, $todayTo),
        'hourly' => cw_analytics_hourly_today($conn),
    ];
}

function cw_analytics_visits_period_options(): array
{
    return [
        'today' => 'Hoy',
        '7d' => '7 días',
        'week' => 'Semana actual',
        '30d' => '30 días',
        '90d' => '90 días',
        'month' => 'Mes',
        'quarter' => 'Trimestre',
        'year' => 'Año',
        'custom' => 'Personalizado',
    ];
}

function cw_analytics_visits_mode_label(): string
{
    $web = cw_analytics_web_filter();
    $webLabels = [
        'all' => 'Todos los sitios',
        'mx' => 'MX · conlineweb.com',
        'us' => 'US · /us',
        'cl' => 'CL · conlineweb.cl',
    ];
    $webLabel = $webLabels[$web] ?? $webLabels['all'];

    if (defined('ADM_DEV_LOCAL') && ADM_DEV_LOCAL) {
        return CW_HUB_ANALYTICS_PRODUCTION_ONLY
            ? 'Local · ' . $webLabel
            : 'Local · todas · ' . $webLabel;
    }

    return 'Producción · ' . $webLabel;
}

/**
 * Sesiones individuales con duración total y desglose por página.
 */
function cw_analytics_individual_sessions(mysqli $conn, string $from, string $to, int $limit = 50, int $offset = 0): array
{
    $sessScope = cw_analytics_url_scope_sql('s.landing_url');
    $limitSql = $limit > 0 ? ' LIMIT ? OFFSET ?' : '';
    $sqlFull = "SELECT s.session_id, s.visitor_id, s.first_seen, s.last_seen,
            TIMESTAMPDIFF(SECOND, s.first_seen, s.last_seen) AS session_seconds,
            s.pages_count, s.device_type, s.browser, s.os, s.screen_w, s.viewport_w, s.utm_source, s.referrer, s.landing_url, s.user_agent,
            s.country, s.region, s.geo_city, s.geo_address, s.geo_lat, s.geo_lng, s.geo_accuracy,
            s.client_timezone, s.geo_source,
            COALESCE(NULLIF(s.contact_nombre, ''), ld.nombre) AS contact_nombre,
            COALESCE(NULLIF(s.contact_correo, ''), ld.correo) AS contact_correo,
            COALESCE(NULLIF(s.contact_telefono, ''), ld.telefono) AS contact_telefono,
            COALESCE(s.lead_id, ld.id) AS lead_id,
            (SELECT COALESCE(SUM(p.time_on_page), 0) FROM cw_analytics_pageviews p WHERE p.session_id = s.session_id) AS total_time_on_pages,
            (SELECT COUNT(*) FROM cw_analytics_pageviews p2 WHERE p2.session_id = s.session_id) AS routes_global,
            (SELECT COUNT(DISTINCT p3.path) FROM cw_analytics_pageviews p3 WHERE p3.session_id = s.session_id) AS routes_unique,
            (SELECT MAX(v.viewed_at) FROM cw_analytics_admin_session_views v WHERE v.session_id = s.session_id) AS admin_last_viewed_at,
            (SELECT COUNT(*) FROM cw_analytics_pageviews p4
                WHERE p4.session_id = s.session_id
                AND p4.viewed_at > COALESCE((SELECT MAX(v2.viewed_at) FROM cw_analytics_admin_session_views v2 WHERE v2.session_id = s.session_id), '1970-01-01 00:00:00')
            ) AS nav_after_admin,
            (SELECT COUNT(*) FROM cw_analytics_events e WHERE e.session_id = s.session_id) AS events_count
            FROM cw_analytics_sessions s
            LEFT JOIN (
                SELECT l1.session_id, l1.id, l1.nombre, l1.correo, l1.telefono
                FROM leads l1
                INNER JOIN (
                    SELECT session_id, MAX(id) AS max_id
                    FROM leads
                    WHERE origen_web = 1 AND eliminado = 0 AND session_id != ''
                    GROUP BY session_id
                ) l2 ON l1.id = l2.max_id
            ) ld ON ld.session_id = s.session_id
            WHERE s.first_seen BETWEEN ? AND ?{$sessScope}
            ORDER BY s.first_seen DESC{$limitSql}";

    $rows = cw_analytics_query_sessions($conn, $sqlFull, $from, $to, $limit, $offset);
    if ($rows !== null) {
        return $rows;
    }

    $sqlBasic = "SELECT s.session_id, s.visitor_id, s.first_seen, s.last_seen,
            TIMESTAMPDIFF(SECOND, s.first_seen, s.last_seen) AS session_seconds,
            s.pages_count, s.device_type, s.browser, s.os, s.screen_w, s.viewport_w, s.utm_source, s.referrer, s.landing_url, s.user_agent,
            s.country, s.region, s.geo_city, s.geo_address, s.geo_lat, s.geo_lng, s.geo_accuracy,
            s.client_timezone, s.geo_source,
            NULL AS contact_nombre, NULL AS contact_correo, NULL AS contact_telefono, NULL AS lead_id,
            (SELECT COALESCE(SUM(p.time_on_page), 0) FROM cw_analytics_pageviews p WHERE p.session_id = s.session_id) AS total_time_on_pages,
            (SELECT COUNT(*) FROM cw_analytics_pageviews p2 WHERE p2.session_id = s.session_id) AS routes_global,
            (SELECT COUNT(DISTINCT p3.path) FROM cw_analytics_pageviews p3 WHERE p3.session_id = s.session_id) AS routes_unique,
            (SELECT MAX(v.viewed_at) FROM cw_analytics_admin_session_views v WHERE v.session_id = s.session_id) AS admin_last_viewed_at,
            (SELECT COUNT(*) FROM cw_analytics_pageviews p4
                WHERE p4.session_id = s.session_id
                AND p4.viewed_at > COALESCE((SELECT MAX(v2.viewed_at) FROM cw_analytics_admin_session_views v2 WHERE v2.session_id = s.session_id), '1970-01-01 00:00:00')
            ) AS nav_after_admin,
            (SELECT COUNT(*) FROM cw_analytics_events e WHERE e.session_id = s.session_id) AS events_count
            FROM cw_analytics_sessions s
            WHERE s.first_seen BETWEEN ? AND ?{$sessScope}
            ORDER BY s.first_seen DESC{$limitSql}";

    $rows = cw_analytics_query_sessions($conn, $sqlBasic, $from, $to, $limit, $offset);

    return $rows ?? [];
}

function cw_analytics_query_sessions(
    mysqli $conn,
    string $sql,
    string $from,
    string $to,
    int $limit,
    int $offset
): ?array {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log('[CW Hub] sessions prepare failed: ' . $conn->error);
        return null;
    }

    if ($limit > 0) {
        $stmt->bind_param('ssii', $from, $to, $limit, $offset);
    } else {
        $stmt->bind_param('ss', $from, $to);
    }

    if (!$stmt->execute()) {
        error_log('[CW Hub] sessions execute failed: ' . $stmt->error);
        $stmt->close();
        return null;
    }

    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

/** Límites permitidos para cargar sesiones en el panel. */
function cw_analytics_sessions_limit_allowed(int $limit): int
{
    $allowed = [100, 500, 2000, 3000, 8000, 0];
    return in_array($limit, $allowed, true) ? $limit : 100;
}

/** Registra que un admin consultó el recorrido de una sesión. */
function cw_analytics_record_admin_session_view(mysqli $conn, string $sessionId, int $adminUid = 0): bool
{
    $sessionId = trim($sessionId);
    if ($sessionId === '') {
        return false;
    }

    $now = cw_hub_now();
    $stmt = $conn->prepare('INSERT INTO cw_analytics_admin_session_views (session_id, admin_uid, viewed_at) VALUES (?, ?, ?)');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('sis', $sessionId, $adminUid, $now);
    $ok = $stmt->execute();
    $stmt->close();

    return (bool) $ok;
}

function cw_analytics_individual_sessions_count(mysqli $conn, string $from, string $to): int
{
    $sessScope = cw_analytics_url_scope_sql('landing_url');
    $stmt = $conn->prepare("SELECT COUNT(*) c FROM cw_analytics_sessions WHERE first_seen BETWEEN ? AND ?{$sessScope}");
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $c = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();

    return $c;
}

/**
 * Conteos de categoría para TODO el periodo (consulta liviana).
 * Evita que los KPIs dependan solo del lote cargado en la tabla.
 *
 * @return array{total:int,sessions:int,contact:int,crawler_ia:int,possible_spam:int}
 */
function cw_analytics_sessions_period_category_counts(mysqli $conn, string $from, string $to): array
{
    $counts = [
        'total' => 0,
        'sessions' => 0,
        'contact' => 0,
        'crawler_ia' => 0,
        'possible_spam' => 0,
    ];

    $sessScope = cw_analytics_url_scope_sql('s.landing_url');
    $sqlFull = "SELECT s.user_agent, s.pages_count,
            TIMESTAMPDIFF(SECOND, s.first_seen, s.last_seen) AS session_seconds,
            COALESCE(NULLIF(s.contact_nombre, ''), ld.nombre) AS contact_nombre,
            COALESCE(NULLIF(s.contact_correo, ''), ld.correo) AS contact_correo,
            COALESCE(NULLIF(s.contact_telefono, ''), ld.telefono) AS contact_telefono,
            COALESCE(s.lead_id, ld.id) AS lead_id
            FROM cw_analytics_sessions s
            LEFT JOIN (
                SELECT l1.session_id, l1.id, l1.nombre, l1.correo, l1.telefono
                FROM leads l1
                INNER JOIN (
                    SELECT session_id, MAX(id) AS max_id
                    FROM leads
                    WHERE origen_web = 1 AND eliminado = 0 AND session_id != ''
                    GROUP BY session_id
                ) l2 ON l1.id = l2.max_id
            ) ld ON ld.session_id = s.session_id
            WHERE s.first_seen BETWEEN ? AND ?{$sessScope}";

    $stmt = $conn->prepare($sqlFull);
    if (!$stmt) {
        $sqlBasic = "SELECT user_agent, pages_count,
                TIMESTAMPDIFF(SECOND, first_seen, last_seen) AS session_seconds,
                contact_nombre, contact_correo, contact_telefono, lead_id
                FROM cw_analytics_sessions
                WHERE first_seen BETWEEN ? AND ?" . cw_analytics_url_scope_sql('landing_url');
        $stmt = $conn->prepare($sqlBasic);
        if (!$stmt) {
            return $counts;
        }
    }

    $stmt->bind_param('ss', $from, $to);
    if (!$stmt->execute()) {
        $stmt->close();
        return $counts;
    }
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $counts['total']++;
        $cat = cw_analytics_session_category($row);
        if (!isset($counts[$cat])) {
            $cat = 'sessions';
        }
        $counts[$cat]++;
    }
    $stmt->close();

    return $counts;
}

/** Lista de sesiones para el panel (sin paginación UI, límite alto). */
function cw_analytics_sessions_list(mysqli $conn, string $from, string $to, int $limit = 500): array
{
    return cw_analytics_individual_sessions($conn, $from, $to, $limit, 0);
}

/** @return list<string> */
function cw_analytics_crawler_ia_patterns(): array
{
    return [
        'googlebot', 'bingbot', 'slurp', 'duckduckbot', 'baiduspider', 'yandexbot',
        'gptbot', 'chatgpt-user', 'chatgpt', 'claudebot', 'anthropic',
        'perplexitybot', 'perplexity', 'google-extended', 'bytespider', 'ccbot',
        'google-notebooklm', 'applebot', 'facebookexternalhit', 'meta-externalagent',
        'semrushbot', 'ahrefsbot', 'petalbot', 'amazonbot', 'rogerbot', 'dotbot',
        'serpstatbot', 'dataforseo', 'mj12bot', 'pingdom', 'headlesschrome',
    ];
}

function cw_analytics_row_has_contact(array $row): bool
{
    $nombre = trim((string) ($row['contact_nombre'] ?? ''));
    $correo = trim((string) ($row['contact_correo'] ?? ''));
    $telefono = trim((string) ($row['contact_telefono'] ?? ''));

    return $nombre !== '' || $correo !== '' || $telefono !== '' || (int) ($row['lead_id'] ?? 0) > 0;
}

function cw_analytics_is_crawler_ia_row(array $row): bool
{
    $ua = strtolower(trim((string) ($row['user_agent'] ?? '')));
    foreach (cw_analytics_crawler_ia_patterns() as $pattern) {
        if ($ua !== '' && str_contains($ua, $pattern)) {
            return true;
        }
    }
    if ($ua !== '' && preg_match('/compatible;\s*googlebot/i', $ua)) {
        return true;
    }

    if (cw_analytics_row_has_contact($row)) {
        return false;
    }

    $sec = max(0, (int) ($row['session_seconds'] ?? 0));
    $pages = max(0, (int) ($row['pages_count'] ?? 0));

    // Visitas anónimas instantáneas → ruido de crawler / ping.
    if ($sec <= 0) {
        return true;
    }
    if ($sec <= 3 && $pages <= 1) {
        return true;
    }

    return false;
}

function cw_analytics_is_spam_name(string $name): bool
{
    $name = trim($name);
    if ($name === '' || mb_strlen($name) < 10) {
        return false;
    }
    if (preg_match('/\s/u', $name)) {
        return false;
    }
    if (!preg_match('/^[A-Za-z0-9._-]+$/', $name)) {
        return false;
    }
    if (preg_match('/[a-z]/', $name) && preg_match('/[A-Z]/', $name) && !preg_match('/^[A-Z][a-z]+([A-Z][a-z]+)*$/', $name)) {
        return true;
    }

    return false;
}

function cw_analytics_is_possible_spam_row(array $row): bool
{
    if (cw_analytics_is_crawler_ia_row($row)) {
        return false;
    }
    if (!cw_analytics_row_has_contact($row)) {
        return false;
    }

    $nombre = trim((string) ($row['contact_nombre'] ?? ''));
    $correo = strtolower(trim((string) ($row['contact_correo'] ?? '')));
    $referrer = strtolower(trim((string) ($row['referrer'] ?? '')));
    $country = strtolower(trim((string) ($row['country'] ?? '')));
    $sec = max(0, (int) ($row['session_seconds'] ?? 0));

    if (cw_analytics_is_spam_name($nombre)) {
        return true;
    }
    if ($correo !== '' && preg_match('/@(369rolling|tempmail|mailinator|guerrillamail|yopmail|10minutemail)\./', $correo)) {
        return true;
    }
    if ($correo !== '' && preg_match('/@(web\.de|mail\.ru|163\.com)$/', $correo) && $referrer === '' && !str_contains($country, 'méxico') && !str_contains($country, 'mexico')) {
        return true;
    }
    if ($nombre !== '' && $sec <= 45 && $referrer === '' && !str_contains($country, 'méxico') && !str_contains($country, 'mexico')) {
        if (preg_match('/(austria|suecia|sweden|alemania|germany|rusia|russia|india|indonesia|vietnam|china)/', $country)) {
            return true;
        }
    }

    return false;
}

/** @return 'sessions'|'contact'|'crawler_ia'|'possible_spam' */
function cw_analytics_session_category(array $row): string
{
    if (cw_analytics_is_crawler_ia_row($row)) {
        return 'crawler_ia';
    }
    if (cw_analytics_is_possible_spam_row($row)) {
        return 'possible_spam';
    }
    if (cw_analytics_row_has_contact($row)) {
        return 'contact';
    }

    return 'sessions';
}

function cw_analytics_sessions_summary_counts(array $sessions): array
{
    $counts = [
        'sessions' => 0,
        'contact' => 0,
        'crawler_ia' => 0,
        'possible_spam' => 0,
    ];
    foreach ($sessions as $session) {
        $cat = (string) ($session['session_category'] ?? 'sessions');
        if (!isset($counts[$cat])) {
            $cat = 'sessions';
        }
        $counts[$cat]++;
    }

    return $counts;
}

function cw_analytics_sessions_payload(array $rows): array
{
    $out = [];
    foreach ($rows as $row) {
        $nombre = trim((string) ($row['contact_nombre'] ?? ''));
        $correo = trim((string) ($row['contact_correo'] ?? ''));
        $telefono = trim((string) ($row['contact_telefono'] ?? ''));
        $region = trim((string) ($row['region'] ?? ''));
        $country = trim((string) ($row['country'] ?? ''));
        $city = trim((string) ($row['geo_city'] ?? ''));
        $address = trim((string) ($row['geo_address'] ?? ''));
        $geoSource = trim((string) ($row['geo_source'] ?? ''));
        $geoLat = isset($row['geo_lat']) && $row['geo_lat'] !== null && $row['geo_lat'] !== ''
            ? (float) $row['geo_lat']
            : null;
        $geoLng = isset($row['geo_lng']) && $row['geo_lng'] !== null && $row['geo_lng'] !== ''
            ? (float) $row['geo_lng']
            : null;
        $geoAccuracy = isset($row['geo_accuracy']) && $row['geo_accuracy'] !== null && $row['geo_accuracy'] !== ''
            ? (int) $row['geo_accuracy']
            : null;
        $geoMapsUrl = ($geoLat !== null && $geoLng !== null)
            ? ('https://www.google.com/maps?q=' . rawurlencode((string) $geoLat . ',' . (string) $geoLng))
            : '';
        $referrer = (string) ($row['referrer'] ?? '');
        $landing = (string) ($row['landing_url'] ?? '');
        $userAgent = (string) ($row['user_agent'] ?? '');
        $sessionSec = max(0, (int) ($row['session_seconds'] ?? 0));
        $pageTime = max(0, (int) ($row['total_time_on_pages'] ?? 0));
        $hasContact = cw_analytics_row_has_contact($row);
        $isCrawlerIa = cw_analytics_is_crawler_ia_row($row);
        $isPossibleSpam = cw_analytics_is_possible_spam_row($row);
        $category = cw_analytics_session_category($row);

        $out[] = [
            'session_id' => (string) ($row['session_id'] ?? ''),
            'visitor_id' => (string) ($row['visitor_id'] ?? ''),
            'nombre' => $nombre,
            'nombre_display' => $nombre !== '' ? $nombre : '—',
            'telefono' => $telefono,
            'telefono_display' => $telefono !== '' ? $telefono : '—',
            'correo' => $correo,
            'correo_display' => $correo !== '' ? $correo : '—',
            'has_contact' => $hasContact,
            'lead_id' => (int) ($row['lead_id'] ?? 0),
            'user_agent' => $userAgent,
            'user_agent_short' => mb_substr($userAgent, 0, 72) . (mb_strlen($userAgent) > 72 ? '…' : ''),
            'is_crawler_ia' => $isCrawlerIa,
            'is_possible_spam' => $isPossibleSpam,
            'session_category' => $category,
            'country' => $country !== '' ? $country : '—',
            'region' => $region !== '' ? $region : '—',
            'geo_city' => $city !== '' ? $city : '—',
            'geo_address' => $address !== '' ? $address : '—',
            'geo_lat' => $geoLat,
            'geo_lng' => $geoLng,
            'geo_accuracy' => $geoAccuracy,
            'geo_maps_url' => $geoMapsUrl,
            'geo_source' => $geoSource !== '' ? $geoSource : '—',
            'geo' => cw_hub_geo_label_from_row($row),
            'geo_display' => cw_hub_geo_display_label($country, $region, $city, $geoSource, $address, $geoAccuracy),
            'device_type' => cw_hub_normalize_device_type((string) ($row['device_type'] ?? '')),
            'device' => cw_analytics_device_label($row['device_type'] ?? ''),
            'device_icon' => cw_analytics_device_icon($row['device_type'] ?? ''),
            'device_screen' => cw_analytics_device_screen_label($row),
            'browser' => (string) ($row['browser'] ?? '—'),
            'referrer' => $referrer,
            'referrer_label' => cw_analytics_referrer_label($referrer),
            'landing_url' => $landing,
            'landing_path' => cw_analytics_url_short_label($landing),
            'first_seen' => cw_hub_format_datetime((string) ($row['first_seen'] ?? '')),
            'first_seen_sort' => cw_hub_format_datetime((string) ($row['first_seen'] ?? ''), 'Y-m-d H:i:s'),
            'last_seen' => cw_hub_format_datetime((string) ($row['last_seen'] ?? '')),
            'session_duration' => $sessionSec,
            'session_duration_fmt' => cw_format_duration($sessionSec),
            'is_spam' => $isPossibleSpam,
            'total_page_time_fmt' => cw_format_duration($pageTime),
            'pages_count' => (int) ($row['pages_count'] ?? 0),
            'routes_global' => max(0, (int) ($row['routes_global'] ?? $row['pages_count'] ?? 0)),
            'routes_unique' => max(0, (int) ($row['routes_unique'] ?? 0)),
            'admin_last_viewed_at' => (string) ($row['admin_last_viewed_at'] ?? ''),
            'admin_last_viewed_fmt' => !empty($row['admin_last_viewed_at'])
                ? cw_hub_format_datetime((string) $row['admin_last_viewed_at'])
                : '',
            'nav_after_admin' => max(0, (int) ($row['nav_after_admin'] ?? 0)),
            'continued_after_admin' => !empty($row['admin_last_viewed_at']) && (int) ($row['nav_after_admin'] ?? 0) > 0,
            'events_count' => (int) ($row['events_count'] ?? 0),
        ];
    }

    return $out;
}

function cw_analytics_session_pageviews(mysqli $conn, string $sessionId): array
{
    $stmt = $conn->prepare('SELECT path, title, url, viewed_at, time_on_page, country, region
        FROM cw_analytics_pageviews WHERE session_id = ? ORDER BY viewed_at ASC');
    $stmt->bind_param('s', $sessionId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

function cw_analytics_session_events(mysqli $conn, string $sessionId): array
{
    $stmt = $conn->prepare('SELECT event_type, event_label, url, created_at
        FROM cw_analytics_events WHERE session_id = ? ORDER BY created_at ASC');
    $stmt->bind_param('s', $sessionId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

function va_render_session_page_breakdown(array $pageviews): string
{
    if (empty($pageviews)) {
        return '<p class="text-muted small mb-0">Sin páginas registradas en esta sesión.</p>';
    }

    $html = '<table class="va-table va-table-compact mb-0">';
    $html .= '<thead><tr><th>Página</th><th>Hora (CDMX)</th><th>Duración</th></tr></thead><tbody>';
    foreach ($pageviews as $pv) {
        $html .= '<tr>';
        $html .= '<td><code>' . htmlspecialchars((string) ($pv['path'] ?? '/')) . '</code>';
        if (!empty($pv['title'])) {
            $html .= '<br><small class="text-muted">' . htmlspecialchars(mb_substr((string) $pv['title'], 0, 70)) . '</small>';
        }
        $html .= '</td>';
        $html .= '<td>' . htmlspecialchars(cw_hub_format_datetime((string) ($pv['viewed_at'] ?? ''), 'H:i:s')) . '</td>';
        $html .= '<td>' . cw_format_duration((int) ($pv['time_on_page'] ?? 0)) . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';

    return $html;
}

/**
 * Visitas individuales de una página: usuario, geo, tiempo en página.
 */
function cw_analytics_page_users(mysqli $conn, string $path, string $from, string $to): array
{
    $path = trim($path);
    if ($path === '') {
        return [];
    }

    $pvScope = cw_analytics_url_scope_sql('p.url');
    $sql = "SELECT p.id, p.session_id, p.url, p.viewed_at, p.time_on_page,
            COALESCE(NULLIF(p.visitor_id, ''), s.visitor_id) AS visitor_id,
            COALESCE(NULLIF(p.country, ''), s.country) AS country,
            COALESCE(NULLIF(p.region, ''), s.region) AS region,
            COALESCE(NULLIF(p.client_timezone, ''), s.client_timezone) AS client_timezone,
            s.client_lang, s.geo_source, s.referrer,
            s.device_type, s.browser, s.os
            FROM cw_analytics_pageviews p
            INNER JOIN cw_analytics_sessions s ON s.session_id = p.session_id
            WHERE p.path = ? AND p.viewed_at BETWEEN ? AND ?{$pvScope}
            ORDER BY p.viewed_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sss', $path, $from, $to);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

function cw_analytics_page_users_payload(array $rows): array
{
    $out = [];
    foreach ($rows as $row) {
        $sec = max(0, (int) ($row['time_on_page'] ?? 0));
        $url = (string) ($row['url'] ?? '');
        $referrer = (string) ($row['referrer'] ?? '');
        $country = trim((string) ($row['country'] ?? ''));
        $region = trim((string) ($row['region'] ?? ''));
        $out[] = [
            'visitor_id' => (string) ($row['visitor_id'] ?? ''),
            'visitor_short' => mb_substr((string) ($row['visitor_id'] ?? ''), 0, 12) . '…',
            'session_id' => (string) ($row['session_id'] ?? ''),
            'session_short' => mb_substr((string) ($row['session_id'] ?? ''), 0, 10) . '…',
            'url' => $url,
            'url_short' => cw_analytics_url_short_label($url),
            'referrer' => $referrer,
            'referrer_label' => cw_analytics_referrer_label($referrer),
            'geo' => cw_hub_geo_label_from_row($row),
            'country' => $country !== '' ? $country : '—',
            'region' => $region !== '' ? $region : '—',
            'geo_source' => (string) ($row['geo_source'] ?? ''),
            'device' => (string) ($row['device_type'] ?? '—'),
            'browser' => (string) ($row['browser'] ?? '—'),
            'os' => (string) ($row['os'] ?? '—'),
            'viewed_at' => cw_hub_format_datetime((string) ($row['viewed_at'] ?? '')),
            'viewed_at_sort' => cw_hub_format_datetime((string) ($row['viewed_at'] ?? ''), 'Y-m-d H:i:s'),
            'time_on_page' => $sec,
            'time_on_page_fmt' => cw_format_duration($sec),
        ];
    }

    return $out;
}

function cw_analytics_url_short_label(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '—';
    }

    $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
    $query = (string) (parse_url($url, PHP_URL_QUERY) ?? '');
    $label = $path !== '' ? $path : $url;
    if ($query !== '') {
        $label .= '?' . mb_substr($query, 0, 40) . (mb_strlen($query) > 40 ? '…' : '');
    }

    return $label !== '' ? $label : mb_substr($url, 0, 80);
}

/**
 * Datos completos de una sesión para el recorrido del visitante.
 */
function cw_analytics_session_detail(mysqli $conn, string $sessionId): ?array
{
    $sessionId = trim($sessionId);
    if ($sessionId === '') {
        return null;
    }

    $stmt = $conn->prepare('SELECT s.*,
        TIMESTAMPDIFF(SECOND, s.first_seen, s.last_seen) AS session_seconds
        FROM cw_analytics_sessions s WHERE s.session_id = ? LIMIT 1');
    $stmt->bind_param('s', $sessionId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return null;
    }

    if (trim((string) ($row['contact_nombre'] ?? '')) === '') {
        $lead = cw_analytics_session_lead_fallback($conn, $sessionId);
        if ($lead) {
            $row['contact_nombre'] = $lead['nombre'] ?? '';
            $row['contact_correo'] = $lead['correo'] ?? '';
            $row['contact_telefono'] = $lead['telefono'] ?? '';
            $row['lead_id'] = $lead['id'] ?? null;
        }
    }

    return $row;
}

function cw_analytics_session_lead_fallback(mysqli $conn, string $sessionId): ?array
{
    $stmt = $conn->prepare('SELECT id, nombre, correo, telefono FROM leads
        WHERE session_id = ? AND origen_web = 1 AND eliminado = 0
        ORDER BY fecha_registro DESC LIMIT 1');
    $stmt->bind_param('s', $sessionId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function cw_analytics_session_journey(mysqli $conn, string $sessionId): array
{
    $pageviews = cw_analytics_session_pageviews($conn, $sessionId);
    $events = cw_analytics_session_events($conn, $sessionId);
    $steps = [];

    foreach ($pageviews as $pv) {
        $steps[] = [
            'kind' => 'pageview',
            'at' => (string) ($pv['viewed_at'] ?? ''),
            'at_fmt' => cw_hub_format_datetime((string) ($pv['viewed_at'] ?? '')),
            'at_sort' => cw_hub_format_datetime((string) ($pv['viewed_at'] ?? ''), 'Y-m-d H:i:s'),
            'path' => (string) ($pv['path'] ?? '/'),
            'url' => (string) ($pv['url'] ?? ''),
            'url_short' => cw_analytics_url_short_label((string) ($pv['url'] ?? '')),
            'title' => (string) ($pv['title'] ?? ''),
            'time_on_page' => max(0, (int) ($pv['time_on_page'] ?? 0)),
            'time_on_page_fmt' => cw_format_duration(max(0, (int) ($pv['time_on_page'] ?? 0))),
            'country' => (string) ($pv['country'] ?? ''),
            'region' => (string) ($pv['region'] ?? ''),
        ];
    }

    foreach ($events as $ev) {
        $steps[] = [
            'kind' => 'event',
            'at' => (string) ($ev['created_at'] ?? ''),
            'at_fmt' => cw_hub_format_datetime((string) ($ev['created_at'] ?? '')),
            'at_sort' => cw_hub_format_datetime((string) ($ev['created_at'] ?? ''), 'Y-m-d H:i:s'),
            'event_type' => (string) ($ev['event_type'] ?? ''),
            'event_label' => (string) ($ev['event_label'] ?? ''),
            'url' => (string) ($ev['url'] ?? ''),
            'url_short' => cw_analytics_url_short_label((string) ($ev['url'] ?? '')),
        ];
    }

    usort($steps, static function (array $a, array $b): int {
        return strcmp($a['at_sort'] ?? $a['at'], $b['at_sort'] ?? $b['at']);
    });

    $n = 1;
    foreach ($steps as &$step) {
        $step['step'] = $n++;
    }
    unset($step);

    return $steps;
}

function cw_analytics_session_trace_payload(mysqli $conn, string $sessionId): ?array
{
    $session = cw_analytics_session_detail($conn, $sessionId);
    if (!$session) {
        return null;
    }

    $journey = cw_analytics_session_journey($conn, $sessionId);
    $pageSteps = array_values(array_filter($journey, static fn (array $s): bool => ($s['kind'] ?? '') === 'pageview'));
    $totalPageTime = 0;
    foreach ($pageSteps as $ps) {
        $totalPageTime += (int) ($ps['time_on_page'] ?? 0);
    }

    $nombre = trim((string) ($session['contact_nombre'] ?? ''));
    $correo = trim((string) ($session['contact_correo'] ?? ''));
    $telefono = trim((string) ($session['contact_telefono'] ?? ''));

    return [
        'session_id' => (string) ($session['session_id'] ?? ''),
        'visitor_id' => (string) ($session['visitor_id'] ?? ''),
        'nombre' => $nombre !== '' ? $nombre : '—',
        'correo' => $correo !== '' ? $correo : '—',
        'telefono' => $telefono !== '' ? $telefono : '—',
        'lead_id' => (int) ($session['lead_id'] ?? 0),
        'has_contact' => $nombre !== '' || $correo !== '' || $telefono !== '',
        'first_seen' => cw_hub_format_datetime((string) ($session['first_seen'] ?? '')),
        'last_seen' => cw_hub_format_datetime((string) ($session['last_seen'] ?? '')),
        'session_duration' => max(0, (int) ($session['session_seconds'] ?? 0)),
        'session_duration_fmt' => cw_format_duration(max(0, (int) ($session['session_seconds'] ?? 0))),
        'total_page_time' => $totalPageTime,
        'total_page_time_fmt' => cw_format_duration($totalPageTime),
        'pages_count' => (int) ($session['pages_count'] ?? count($pageSteps)),
        'routes_global' => count($pageSteps),
        'routes_unique' => count(array_unique(array_map(static fn (array $s): string => (string) ($s['path'] ?? ''), $pageSteps))),
        'country' => trim((string) ($session['country'] ?? '')) ?: '—',
        'region' => trim((string) ($session['region'] ?? '')) ?: '—',
        'geo' => cw_hub_geo_label_from_row($session),
        'geo_source' => (string) ($session['geo_source'] ?? ''),
        'device' => (string) ($session['device_type'] ?? '—'),
        'browser' => (string) ($session['browser'] ?? '—'),
        'os' => (string) ($session['os'] ?? '—'),
        'referrer' => (string) ($session['referrer'] ?? ''),
        'referrer_label' => cw_analytics_referrer_label((string) ($session['referrer'] ?? '')),
        'landing_url' => (string) ($session['landing_url'] ?? ''),
        'landing_path' => cw_analytics_url_short_label((string) ($session['landing_url'] ?? '')),
        'utm_source' => (string) ($session['utm_source'] ?? ''),
        'journey' => $journey,
    ];
}

/**
 * Recorrido web de un lead: pasos antes y después del registro.
 */
function cw_analytics_lead_journey_payload(mysqli $conn, int $leadId): ?array
{
    if ($leadId <= 0) {
        return null;
    }

    $stmt = $conn->prepare('SELECT id, nombre, session_id, fecha_registro, pagina_origen, fuente
        FROM leads WHERE id = ? AND origen_web = 1 AND eliminado = 0 LIMIT 1');
    $stmt->bind_param('i', $leadId);
    $stmt->execute();
    $lead = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$lead) {
        return null;
    }

    $sessionId = trim((string) ($lead['session_id'] ?? ''));
    $registeredAt = (string) ($lead['fecha_registro'] ?? '');
    $registeredAtFmt = $registeredAt !== '' ? cw_hub_format_datetime($registeredAt) : '—';

    if ($sessionId === '') {
        return [
            'lead_id' => $leadId,
            'lead_nombre' => (string) ($lead['nombre'] ?? ''),
            'session_id' => '',
            'registered_at' => $registeredAt,
            'registered_at_fmt' => $registeredAtFmt,
            'pagina_origen' => (string) ($lead['pagina_origen'] ?? ''),
            'has_tracking' => false,
            'message' => 'Este lead no tiene sesión de tracking vinculada.',
            'summary' => [
                'before_count' => 0,
                'after_count' => 0,
                'later_sessions_count' => 0,
                'returned_after_register' => false,
            ],
            'before' => [],
            'registration' => null,
            'after' => [],
            'later_sessions' => [],
        ];
    }

    $session = cw_analytics_session_detail($conn, $sessionId);
    if (!$session) {
        return [
            'lead_id' => $leadId,
            'lead_nombre' => (string) ($lead['nombre'] ?? ''),
            'session_id' => $sessionId,
            'registered_at' => $registeredAt,
            'registered_at_fmt' => $registeredAtFmt,
            'pagina_origen' => (string) ($lead['pagina_origen'] ?? ''),
            'has_tracking' => false,
            'message' => 'No hay datos de sesión para este lead.',
            'summary' => [
                'before_count' => 0,
                'after_count' => 0,
                'later_sessions_count' => 0,
                'returned_after_register' => false,
            ],
            'before' => [],
            'registration' => null,
            'after' => [],
            'later_sessions' => [],
        ];
    }

    $journey = cw_analytics_session_journey($conn, $sessionId);
    $before = [];
    $after = [];
    $registration = null;

    foreach ($journey as $step) {
        $at = (string) ($step['at_sort'] ?? $step['at'] ?? '');
        $isConversion = ($step['kind'] ?? '') === 'event' && ($step['event_type'] ?? '') === 'conversion';

        if ($isConversion) {
            $registration = array_merge($step, [
                'is_registration' => true,
                'event_label' => 'Registro del lead #' . $leadId,
            ]);
            continue;
        }

        if ($registeredAt !== '' && $at !== '' && $at < $registeredAt) {
            $before[] = $step;
        } else {
            $after[] = $step;
        }
    }

    if (!$registration && $registeredAt !== '') {
        $registration = [
            'kind' => 'registration',
            'is_registration' => true,
            'at' => $registeredAt,
            'at_fmt' => $registeredAtFmt,
            'at_sort' => cw_hub_format_datetime($registeredAt, 'Y-m-d H:i:s'),
            'event_type' => 'registration',
            'event_label' => 'Registro del lead #' . $leadId,
            'url' => (string) ($lead['pagina_origen'] ?? ''),
            'url_short' => cw_analytics_url_short_label((string) ($lead['pagina_origen'] ?? '')),
            'path' => (string) (parse_url((string) ($lead['pagina_origen'] ?? ''), PHP_URL_PATH) ?? '/'),
        ];
    }

    $visitorId = trim((string) ($session['visitor_id'] ?? ''));
    $routesGlobal = 0;
    $routesUnique = 0;
    $routesNewAfter = 0;
    $routeStmt = $conn->prepare('SELECT COUNT(*) AS routes_global, COUNT(DISTINCT path) AS routes_unique
        FROM cw_analytics_pageviews WHERE session_id = ?');
    if ($routeStmt) {
        $routeStmt->bind_param('s', $sessionId);
        $routeStmt->execute();
        $routeRow = $routeStmt->get_result()->fetch_assoc();
        $routeStmt->close();
        $routesGlobal = max(0, (int) ($routeRow['routes_global'] ?? 0));
        $routesUnique = max(0, (int) ($routeRow['routes_unique'] ?? 0));
    }
    if ($registeredAt !== '') {
        $routeNewStmt = $conn->prepare('SELECT COUNT(DISTINCT path) AS routes_new
            FROM cw_analytics_pageviews WHERE session_id = ? AND viewed_at >= ?');
        if ($routeNewStmt) {
            $routeNewStmt->bind_param('ss', $sessionId, $registeredAt);
            $routeNewStmt->execute();
            $routesNewAfter = max(0, (int) ($routeNewStmt->get_result()->fetch_assoc()['routes_new'] ?? 0));
            $routeNewStmt->close();
        }
    }

    $laterSessions = [];
    if ($visitorId !== '' && $registeredAt !== '') {
        $laterStmt = $conn->prepare('SELECT session_id, first_seen, last_seen, pages_count, landing_url, country, region
            FROM cw_analytics_sessions
            WHERE visitor_id = ? AND session_id != ? AND first_seen > ?
            ORDER BY first_seen ASC
            LIMIT 15');
        $laterStmt->bind_param('sss', $visitorId, $sessionId, $registeredAt);
        $laterStmt->execute();
        $laterRows = $laterStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $laterStmt->close();

        foreach ($laterRows as $lr) {
            $laterSessions[] = [
                'session_id' => (string) ($lr['session_id'] ?? ''),
                'session_short' => mb_substr((string) ($lr['session_id'] ?? ''), 0, 10) . '…',
                'first_seen' => cw_hub_format_datetime((string) ($lr['first_seen'] ?? '')),
                'last_seen' => cw_hub_format_datetime((string) ($lr['last_seen'] ?? '')),
                'pages_count' => (int) ($lr['pages_count'] ?? 0),
                'landing_path' => cw_analytics_url_short_label((string) ($lr['landing_url'] ?? '')),
                'geo' => cw_hub_geo_label($lr['country'] ?? null, $lr['region'] ?? null),
            ];
        }
    }

    return [
        'lead_id' => $leadId,
        'lead_nombre' => (string) ($lead['nombre'] ?? ''),
        'session_id' => $sessionId,
        'session_short' => mb_substr($sessionId, 0, 12) . '…',
        'visitor_id' => $visitorId,
        'registered_at' => $registeredAt,
        'registered_at_fmt' => $registeredAtFmt,
        'pagina_origen' => (string) ($lead['pagina_origen'] ?? ''),
        'has_tracking' => true,
        'message' => '',
        'geo' => cw_hub_geo_label_from_row($session),
        'device' => (string) ($session['device_type'] ?? '—'),
        'referrer_label' => cw_analytics_referrer_label((string) ($session['referrer'] ?? '')),
        'landing_path' => cw_analytics_url_short_label((string) ($session['landing_url'] ?? '')),
        'summary' => [
            'before_count' => count($before),
            'after_count' => count($after),
            'later_sessions_count' => count($laterSessions),
            'returned_after_register' => count($after) > 0 || count($laterSessions) > 0,
            'total_steps' => count($before) + count($after) + ($registration ? 1 : 0),
            'routes_global' => $routesGlobal,
            'routes_unique' => $routesUnique,
            'routes_new_after' => $routesNewAfter,
        ],
        'before' => $before,
        'registration' => $registration,
        'after' => $after,
        'later_sessions' => $laterSessions,
    ];
}

function cw_analytics_top_pages_tier(array $pages, int $limit): array
{
    $slice = array_slice($pages, 0, $limit);
    $visitas = 0;
    $timeWeighted = 0;
    $topPath = '';
    $topVisits = 0;

    foreach ($slice as $i => $page) {
        $vis = (int) ($page['visitas'] ?? 0);
        $visitas += $vis;
        $timeWeighted += $vis * (int) ($page['tiempo_prom'] ?? 0);
        if ($i === 0) {
            $topPath = (string) ($page['path'] ?? '');
            $topVisits = $vis;
        }
    }

    return [
        'limit' => $limit,
        'count' => count($slice),
        'visitas' => $visitas,
        'avgSec' => $visitas > 0 ? (int) round($timeWeighted / $visitas) : 0,
        'topPath' => $topPath,
        'topVisits' => $topVisits,
        'pages' => $slice,
    ];
}

function va_render_top_pages_detail_list(array $pages, int $totalPv): string
{
    if (empty($pages)) {
        return '<div class="va-empty">Sin URLs en este ranking.</div>';
    }

    $html = '<div class="va-top-detail-table-wrap"><table class="va-top-detail-table">';
    $html .= '<thead><tr>';
    $html .= '<th>#</th><th>URL / ruta</th><th>Visitas</th><th>Tiempo prom. por vista</th><th>Participación</th><th></th>';
    $html .= '</tr></thead><tbody>';

    $rank = 1;
    foreach ($pages as $page) {
        $vis = (int) ($page['visitas'] ?? 0);
        $avgSec = (int) ($page['tiempo_prom'] ?? 0);
        $share = $totalPv > 0 ? round(($vis / $totalPv) * 100, 1) : 0;
        $html .= '<tr>';
        $html .= '<td><span class="va-top-url-rank">' . $rank . '</span></td>';
        $html .= '<td class="va-top-url-cell">';
        $html .= '<code>' . htmlspecialchars((string) ($page['path'] ?? '')) . '</code>';
        if (!empty($page['title'])) {
            $html .= '<small>' . htmlspecialchars(mb_substr((string) $page['title'], 0, 90)) . '</small>';
        }
        $html .= '</td>';
        $html .= '<td><strong>' . number_format($vis) . '</strong></td>';
        $html .= '<td>' . cw_format_duration($avgSec) . '</td>';
        $html .= '<td><div class="d-flex align-items-center gap-2">';
        $html .= '<div class="va-share-bar flex-grow-1"><span style="width:' . min(100, $share) . '%"></span></div>';
        $html .= '<small class="text-muted">' . $share . '%</small></div></td>';
        $path = htmlspecialchars((string) ($page['path'] ?? ''), ENT_QUOTES);
        $pageTitle = htmlspecialchars((string) ($page['title'] ?? $page['path'] ?? ''), ENT_QUOTES);
        $html .= '<td><button type="button" class="btn btn-sm btn-outline-primary va-page-detail-btn" data-path="' . $path . '" data-title="' . $pageTitle . '"><i class="bi bi-people"></i> Detalle</button></td>';
        $html .= '</tr>';
        $rank++;
    }

    $html .= '</tbody></table></div>';

    return $html;
}

function va_render_delta(float $pct): string
{
    if ($pct > 0) {
        return '<span class="va-delta up"><i class="bi bi-arrow-up-short"></i>' . htmlspecialchars((string) $pct) . '%</span>';
    }
    if ($pct < 0) {
        return '<span class="va-delta down"><i class="bi bi-arrow-down-short"></i>' . htmlspecialchars((string) abs($pct)) . '%</span>';
    }

    return '<span class="va-delta flat"><i class="bi bi-dash"></i>0%</span>';
}

function va_render_rank_list(array $rows, int $maxVal): string
{
    if (empty($rows)) {
        return '<p class="text-muted small mb-0">Sin datos en este periodo.</p>';
    }

    $html = '<ul class="va-rank-list">';
    foreach ($rows as $row) {
        $label = htmlspecialchars((string) ($row['label'] ?? ''));
        $count = (int) ($row['c'] ?? 0);
        $pct = $maxVal > 0 ? round(($count / $maxVal) * 100) : 0;
        $html .= '<li><span>' . $label . '</span><strong>' . number_format($count) . '</strong>';
        $html .= '<div class="bar-wrap"><span style="width:' . $pct . '%"></span></div></li>';
    }
    $html .= '</ul>';

    return $html;
}
