<?php
require_once __DIR__ . '/bootstrap.php';

try {

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cw_hub_api_fail('Método no permitido', 405);
}
cw_hub_validate_request();

$data = cw_hub_json_input();
$type = cw_hub_s($data['type'] ?? 'pageview', 30);

$sessionId = cw_hub_s($data['session_id'] ?? '', 64);
$visitorId = cw_hub_s($data['visitor_id'] ?? '', 64);
if ($sessionId === '' || $visitorId === '') {
    cw_hub_api_fail('session_id y visitor_id requeridos');
}

$now = cw_hub_now();
$ua = cw_hub_s($data['user_agent'] ?? '', 512);
if ($ua === '') {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
}
$clientDeviceHint = cw_hub_s($data['device_type'] ?? '', 20);
if (!in_array($clientDeviceHint, ['mobile', 'tablet', 'desktop'], true)) {
    $clientDeviceHint = '';
}
$dev = cw_hub_parse_device($ua, $clientDeviceHint !== '' ? $clientDeviceHint : null, [
    'touch_points' => (int) ($data['touch_points'] ?? 0),
    'screen_w' => (int) ($data['screen_w'] ?? 0),
    'viewport_w' => (int) ($data['viewport_w'] ?? 0),
    'ua_data_mobile' => $data['ua_data_mobile'] ?? null,
    'pointer_coarse' => $data['pointer_coarse'] ?? null,
    'has_fine_hover' => $data['has_fine_hover'] ?? null,
]);
$clientIp = cw_hub_client_ip();
$ipHash = hash('sha256', $clientIp . CW_HUB_API_KEY);
$geo = cw_hub_geo_from_request($data, $clientIp);

if ($type === 'pageview') {
    $url = cw_hub_s($data['url'] ?? '', 2000);
    $path = cw_hub_s($data['path'] ?? parse_url($url, PHP_URL_PATH) ?? '/', 500);
    $title = cw_hub_s($data['title'] ?? '', 500);
    $timeOn = max(0, (int) ($data['time_on_page'] ?? 0));
    $utmSource = cw_hub_s($data['utm_source'] ?? '', 120);
    $utmMedium = cw_hub_s($data['utm_medium'] ?? '', 120);
    $utmCampaign = cw_hub_s($data['utm_campaign'] ?? '', 120);
    $referrer = cw_hub_s($data['referrer'] ?? '', 2000);
    $screenW = max(0, min(9999, (int) ($data['screen_w'] ?? 0)));
    $viewportW = max(0, min(9999, (int) ($data['viewport_w'] ?? 0)));
    $webSite = cw_hub_resolve_web_site(
        (string) ($data['sitio'] ?? $data['web'] ?? ''),
        $url,
        $path
    );
    if ($webSite === '') {
        $webSite = 'mx';
    }

    $chk = $conn->prepare('SELECT id, pages_count, country, region, geo_city, geo_source FROM cw_analytics_sessions WHERE session_id = ? LIMIT 1');
    $chk->bind_param('s', $sessionId);
    $chk->execute();
    $sess = $chk->get_result()->fetch_assoc();
    $chk->close();

    if ($sess) {
        $pages = (int) $sess['pages_count'] + 1;
        $upd = $conn->prepare('UPDATE cw_analytics_sessions SET last_seen = ?, pages_count = ?, user_agent = ?, device_type = ?, browser = ?, os = ?, screen_w = ?, viewport_w = ? WHERE session_id = ?');
        if ($upd) {
            $upd->bind_param('sissssiis', $now, $pages, $ua, $dev['device'], $dev['browser'], $dev['os'], $screenW, $viewportW, $sessionId);
            $upd->execute();
            $upd->close();
        } else {
            $updBasic = $conn->prepare('UPDATE cw_analytics_sessions SET last_seen = ?, pages_count = ?, user_agent = ?, device_type = ?, browser = ?, os = ? WHERE session_id = ?');
            if ($updBasic) {
                $updBasic->bind_param('sisssss', $now, $pages, $ua, $dev['device'], $dev['browser'], $dev['os'], $sessionId);
                $updBasic->execute();
                $updBasic->close();
            }
        }

        $existingSource = trim((string) ($sess['geo_source'] ?? ''));
        $existingCountry = trim((string) ($sess['country'] ?? ''));
        $existingRegion = trim((string) ($sess['region'] ?? ''));
        $existingCity = trim((string) ($sess['geo_city'] ?? ''));
        $newCountry = trim((string) ($geo['country'] ?? ''));
        $newRegion = trim((string) ($geo['region'] ?? ''));
        $newCity = trim((string) ($geo['geo_city'] ?? ''));
        $newSource = trim((string) ($geo['geo_source'] ?? ''));
        if ($existingSource === 'gps') {
            $shouldUpdateGeo = false;
        } elseif ($newSource === 'gps') {
            $shouldUpdateGeo = true;
        } else {
            $shouldUpdateGeo = $existingCountry === ''
                || $existingCountry === 'Local'
                || $existingCountry === 'Desconocido'
                || ($newSource === 'ip' && ($newCountry !== '' || $newRegion !== '' || $newCity !== ''))
                || ($newRegion !== '' && $newRegion !== $existingRegion)
                || ($newCity !== '' && $newCity !== $existingCity);
        }
        if ($shouldUpdateGeo) {
            cw_hub_update_session_geo($conn, $sessionId, $geo);
        }
        if ($webSite !== '') {
            $updWeb = $conn->prepare("UPDATE cw_analytics_sessions SET web = COALESCE(NULLIF(web, ''), ?) WHERE session_id = ?");
            if ($updWeb) {
                $updWeb->bind_param('ss', $webSite, $sessionId);
                $updWeb->execute();
                $updWeb->close();
            }
        }
    } else {
        $pages = 1;
        $ok = cw_hub_insert_session($conn, [
            'session_id' => $sessionId,
            'visitor_id' => $visitorId,
            'first_seen' => $now,
            'last_seen' => $now,
            'ip_hash' => $ipHash,
            'user_agent' => $ua,
            'device_type' => $dev['device'],
            'browser' => $dev['browser'],
            'os' => $dev['os'],
            'screen_w' => $screenW > 0 ? $screenW : null,
            'viewport_w' => $viewportW > 0 ? $viewportW : null,
            'country' => cw_hub_str_or_empty($geo['country'] ?? ''),
            'region' => cw_hub_str_or_empty($geo['region'] ?? ''),
            'geo_city' => cw_hub_str_or_empty($geo['geo_city'] ?? ''),
            'client_timezone' => cw_hub_str_or_empty($geo['client_timezone'] ?? ''),
            'client_lang' => cw_hub_str_or_empty($geo['client_lang'] ?? ''),
            'geo_source' => cw_hub_str_or_empty($geo['geo_source'] ?? ''),
            'utm_source' => $utmSource,
            'utm_medium' => $utmMedium,
            'utm_campaign' => $utmCampaign,
            'referrer' => $referrer,
            'landing_url' => $url,
            'pages_count' => $pages,
            'web' => $webSite,
        ]);
        if (!$ok) {
            cw_hub_api_fail('No se pudo registrar la sesión', 500);
        }
    }

    $pvOk = cw_hub_insert_pageview($conn, [
        'session_id' => $sessionId,
        'visitor_id' => $visitorId,
        'url' => $url,
        'path' => $path,
        'title' => $title,
        'viewed_at' => $now,
        'time_on_page' => $timeOn,
        'country' => cw_hub_str_or_empty($geo['country'] ?? ''),
        'region' => cw_hub_str_or_empty($geo['region'] ?? ''),
        'geo_city' => cw_hub_str_or_empty($geo['geo_city'] ?? ''),
        'client_timezone' => cw_hub_str_or_empty($geo['client_timezone'] ?? ''),
        'web' => $webSite,
    ]);
    if (!$pvOk) {
        cw_hub_api_fail('No se pudo registrar el pageview', 500);
    }

    echo json_encode([
        'success' => true,
        'recorded' => 'pageview',
        'stored_at' => $now,
        'geo' => [
            'country' => $geo['country'] ?? '',
            'region' => $geo['region'] ?? '',
            'city' => $geo['geo_city'] ?? '',
            'source' => $geo['geo_source'] ?? '',
            'display' => cw_hub_geo_display_label(
                $geo['country'] ?? '',
                $geo['region'] ?? '',
                $geo['geo_city'] ?? '',
                $geo['geo_source'] ?? '',
                $geo['geo_address'] ?? '',
                isset($geo['geo_accuracy']) ? (int) $geo['geo_accuracy'] : null
            ),
            'lat' => $geo['geo_lat'] ?? null,
            'lng' => $geo['geo_lng'] ?? null,
            'accuracy' => $geo['geo_accuracy'] ?? null,
            'address' => $geo['geo_address'] ?? '',
        ],
    ], JSON_UNESCAPED_UNICODE);
    cw_hub_api_exit();
}

if ($type === 'geo') {
    $geo = cw_hub_geo_from_request($data, $clientIp);
    if (($geo['geo_source'] ?? '') !== 'gps') {
        cw_hub_api_fail('Coordenadas GPS inválidas');
    }

    $chk = $conn->prepare('SELECT id FROM cw_analytics_sessions WHERE session_id = ? LIMIT 1');
    $chk->bind_param('s', $sessionId);
    $chk->execute();
    $sess = $chk->get_result()->fetch_assoc();
    $chk->close();

    if ($sess) {
        cw_hub_update_session_geo($conn, $sessionId, $geo);
        $upd = $conn->prepare('UPDATE cw_analytics_sessions SET last_seen = ? WHERE session_id = ?');
        $upd->bind_param('ss', $now, $sessionId);
        $upd->execute();
        $upd->close();
    }

    echo json_encode([
        'success' => true,
        'recorded' => 'geo',
        'stored_at' => $now,
        'geo' => [
            'country' => $geo['country'] ?? '',
            'region' => $geo['region'] ?? '',
            'city' => $geo['geo_city'] ?? '',
            'address' => $geo['geo_address'] ?? '',
            'source' => $geo['geo_source'] ?? '',
            'lat' => $geo['geo_lat'] ?? null,
            'lng' => $geo['geo_lng'] ?? null,
            'accuracy' => $geo['geo_accuracy'] ?? null,
            'display' => cw_hub_geo_display_label(
                $geo['country'] ?? '',
                $geo['region'] ?? '',
                $geo['geo_city'] ?? '',
                $geo['geo_source'] ?? '',
                $geo['geo_address'] ?? '',
                isset($geo['geo_accuracy']) ? (int) $geo['geo_accuracy'] : null
            ),
        ],
    ], JSON_UNESCAPED_UNICODE);
    cw_hub_api_exit();
}

if ($type === 'event') {
    $eventType = cw_hub_s($data['event_type'] ?? 'click', 50);
    $label = cw_hub_s($data['event_label'] ?? '', 200);
    $url = cw_hub_s($data['url'] ?? '', 2000);
    $meta = isset($data['meta']) ? json_encode($data['meta'], JSON_UNESCAPED_UNICODE) : null;

    $ev = $conn->prepare('INSERT INTO cw_analytics_events (session_id, event_type, event_label, url, meta, created_at) VALUES (?, ?, ?, ?, ?, ?)');
    $ev->bind_param('ssssss', $sessionId, $eventType, $label, $url, $meta, $now);
    $ev->execute();
    $ev->close();

    if ($eventType === 'whatsapp_click') {
        $upd = $conn->prepare('UPDATE cw_analytics_sessions SET last_seen = ? WHERE session_id = ?');
        $upd->bind_param('ss', $now, $sessionId);
        $upd->execute();
        $upd->close();
    }

    echo json_encode(['success' => true, 'recorded' => 'event', 'stored_at' => $now]);
    cw_hub_api_exit();
}

if ($type === 'heartbeat') {
    $timeOn = max(0, (int) ($data['time_on_page'] ?? 0));
    $path = cw_hub_s($data['path'] ?? '', 500);
    if ($timeOn > 0 && $path !== '') {
        $sel = $conn->prepare('SELECT id FROM cw_analytics_pageviews WHERE session_id = ? AND path = ? ORDER BY viewed_at DESC LIMIT 1');
        if ($sel) {
            $sel->bind_param('ss', $sessionId, $path);
            $sel->execute();
            $pvRow = $sel->get_result()->fetch_assoc();
            $sel->close();
            if ($pvRow) {
                $pvId = (int) $pvRow['id'];
                $hb = $conn->prepare('UPDATE cw_analytics_pageviews SET time_on_page = GREATEST(time_on_page, ?) WHERE id = ?');
                if ($hb) {
                    $hb->bind_param('ii', $timeOn, $pvId);
                    $hb->execute();
                    $hb->close();
                }
            }
        }
    }
    $upd = $conn->prepare('UPDATE cw_analytics_sessions SET last_seen = ? WHERE session_id = ?');
    $upd->bind_param('ss', $now, $sessionId);
    $upd->execute();
    $upd->close();
    echo json_encode(['success' => true, 'recorded' => 'heartbeat', 'stored_at' => $now]);
    cw_hub_api_exit();
}

cw_hub_api_fail('Tipo de tracking desconocido');

} catch (CwHubApiRespond $e) {
    throw $e;
} catch (Throwable $e) {
    error_log('[CW Hub track] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    cw_hub_api_fail('Error interno al registrar visita', 500);
}
