<?php
require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_config.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_analytics.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_permissions.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (!$conn || $conn->connect_error) {
        throw new RuntimeException('Sin conexión a la base de datos');
    }

    cw_hub_migrate($conn);

    if (!cw_hub_can('hub.analytics.view')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Sin permiso']);
        exit;
    }

    $period = $_GET['period'] ?? '30d';
    $from = $_GET['from'] ?? null;
    $to = $_GET['to'] ?? null;
    cw_analytics_web_filter($_GET['web'] ?? 'all');
    $limit = cw_analytics_sessions_limit_allowed((int) ($_GET['limit'] ?? 100));
    [$dateFrom, $dateTo] = cw_hub_period_dates($period, $from, $to);

    // "Todos" / lotes grandes: evitar timeout o memoria corta
    if ($limit === 0 || $limit >= 2000) {
        @ini_set('memory_limit', '512M');
        @set_time_limit(120);
    }

    $periodCounts = cw_analytics_sessions_period_category_counts($conn, $dateFrom, $dateTo);
    $totalInPeriod = (int) ($periodCounts['total'] ?? 0);
    if ($totalInPeriod <= 0) {
        $totalInPeriod = cw_analytics_individual_sessions_count($conn, $dateFrom, $dateTo);
    }

    $rows = cw_analytics_sessions_list($conn, $dateFrom, $dateTo, $limit);
    $sessions = cw_analytics_sessions_payload($rows);
    $loadedCounts = cw_analytics_sessions_summary_counts($sessions);

    $totalLoaded = count($sessions);

    $devicesPeriod = cw_analytics_sessions_device_counts($conn, $dateFrom, $dateTo);
    $devicesLoaded = cw_analytics_count_devices_in_sessions($sessions);

    $flags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }

    echo json_encode([
        'success' => true,
        'period' => $period,
        'from' => $dateFrom,
        'to' => $dateTo,
        'timezone' => CW_HUB_TIMEZONE,
        'limit' => $limit,
        'summary' => [
            // KPIs / pestañas = periodo completo
            'total' => $totalLoaded,
            'total_in_period' => $totalInPeriod,
            'sessions' => (int) ($periodCounts['sessions'] ?? 0),
            'with_contact' => (int) ($periodCounts['contact'] ?? 0),
            'with_contact_real' => (int) ($periodCounts['contact'] ?? 0),
            'crawler_ia' => (int) ($periodCounts['crawler_ia'] ?? 0),
            'possible_spam' => (int) ($periodCounts['possible_spam'] ?? 0),
            'spam_contact' => (int) ($periodCounts['possible_spam'] ?? 0),
            'total_active' => (int) (($periodCounts['sessions'] ?? 0) + ($periodCounts['contact'] ?? 0)),
            'loaded_all' => $limit === 0 || $totalLoaded >= $totalInPeriod,
            // Conteos del lote mostrado en tabla (para aviso)
            'loaded_sessions' => (int) ($loadedCounts['sessions'] ?? 0),
            'loaded_contact' => (int) ($loadedCounts['contact'] ?? 0),
            'loaded_crawler_ia' => (int) ($loadedCounts['crawler_ia'] ?? 0),
            'loaded_possible_spam' => (int) ($loadedCounts['possible_spam'] ?? 0),
            'devices_period' => $devicesPeriod,
            'devices_loaded' => $devicesLoaded,
        ],
        'sessions' => $sessions,
    ], $flags);
} catch (Throwable $e) {
    error_log('[sessions_list.php] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al cargar sesiones: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
