<?php
/**
 * Geolocalización híbrida: IP (servidor) + señales del navegador (timezone, idioma).
 */
/** Resuelve mx|us|cl desde payload / URL / path (tracking + leads). */
function cw_hub_resolve_web_site(?string $sitio = '', ?string $url = '', ?string $path = ''): string
{
    $sitio = strtolower(trim((string) ($sitio ?? '')));
    if (in_array($sitio, ['mx', 'cl', 'us'], true)) {
        return $sitio;
    }

    $url = strtolower(trim((string) ($url ?? '')));
    $path = strtolower(trim((string) ($path ?? '')));
    if ($path === '' && $url !== '') {
        $parsed = parse_url($url, PHP_URL_PATH);
        $path = is_string($parsed) ? strtolower($parsed) : '';
    }

    if (str_contains($url, 'conlineweb.cl')) {
        return 'cl';
    }

    if (
        preg_match('~://(?:www\.)?conlineweb\.com/us(?:/|$|[?\#])~', $url)
        || preg_match('~(?:^|/)us(?:/|$|[?\#])~', $path)
    ) {
        return 'us';
    }

    if (str_contains($url, 'conlineweb.com') || $path !== '') {
        return 'mx';
    }

    return '';
}

function cw_hub_is_private_ip(string $ip): bool
{
    if ($ip === '' || $ip === '::1') {
        return true;
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return true;
    }

    return false;
}

/** IP real del visitante (proxy, Cloudflare, etc.). */
function cw_hub_client_ip(): string
{
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR',
    ];
    foreach ($headers as $header) {
        if (empty($_SERVER[$header])) {
            continue;
        }
        $candidate = trim(explode(',', (string) $_SERVER[$header])[0]);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }

    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

function cw_hub_geo_clean(string $value, int $max = 80): string
{
    return mb_substr(trim(strip_tags($value)), 0, $max);
}

function cw_hub_geo_http_get(string $url, int $timeout = 5): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: ConlineWeb-Geo/1.1',
            ],
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw !== false && $code >= 200 && $code < 300) {
            return $raw;
        }
    }

    $ctx = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\nUser-Agent: ConlineWeb-Geo/1.1\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $raw = @file_get_contents($url, false, $ctx);

    return ($raw !== false && $raw !== '') ? $raw : null;
}

function cw_hub_geo_empty_ip_result(bool $isLocal = false): array
{
    return [
        'country' => null,
        'region' => null,
        'city' => null,
        'is_local' => $isLocal,
    ];
}

function cw_hub_geo_from_ipwho(string $ip): ?array
{
    $raw = cw_hub_geo_http_get('https://ipwho.is/' . rawurlencode($ip) . '?lang=es');
    if (!$raw) {
        return null;
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['success'])) {
        return null;
    }

    $country = cw_hub_geo_clean((string) ($data['country'] ?? ''));
    $region = cw_hub_geo_clean((string) ($data['region'] ?? ''));
    $city = cw_hub_geo_clean((string) ($data['city'] ?? ''), 60);

    if ($country === '' && $region === '' && $city === '') {
        return null;
    }

    return [
        'country' => $country !== '' ? $country : null,
        'region' => $region !== '' ? $region : null,
        'city' => $city !== '' ? $city : null,
        'is_local' => false,
    ];
}

function cw_hub_geo_from_ipapi_co(string $ip): ?array
{
    $raw = cw_hub_geo_http_get('https://ipapi.co/' . rawurlencode($ip) . '/json/');
    if (!$raw) {
        return null;
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || !empty($data['error'])) {
        return null;
    }

    $country = cw_hub_geo_clean((string) ($data['country_name'] ?? ''));
    $region = cw_hub_geo_clean((string) ($data['region'] ?? ''));
    $city = cw_hub_geo_clean((string) ($data['city'] ?? ''), 60);

    if ($country === '' && $region === '' && $city === '') {
        return null;
    }

    return [
        'country' => $country !== '' ? $country : null,
        'region' => $region !== '' ? $region : null,
        'city' => $city !== '' ? $city : null,
        'is_local' => false,
    ];
}

function cw_hub_geo_from_ip_api_com(string $ip): ?array
{
    $url = 'http://ip-api.com/json/' . urlencode($ip) . '?fields=status,country,regionName,city&lang=es';
    $raw = cw_hub_geo_http_get($url);
    if (!$raw) {
        return null;
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
        return null;
    }

    $country = cw_hub_geo_clean((string) ($data['country'] ?? ''));
    $region = cw_hub_geo_clean((string) ($data['regionName'] ?? ''));
    $city = cw_hub_geo_clean((string) ($data['city'] ?? ''), 60);

    if ($country === '' && $region === '' && $city === '') {
        return null;
    }

    return [
        'country' => $country !== '' ? $country : null,
        'region' => $region !== '' ? $region : null,
        'city' => $city !== '' ? $city : null,
        'is_local' => false,
    ];
}

function cw_hub_geo_score(array $geo): int
{
    $score = 0;
    if (!empty($geo['city'])) {
        $score += 4;
    }
    if (!empty($geo['region'])) {
        $score += 2;
    }
    if (!empty($geo['country'])) {
        $score += 1;
    }

    return $score;
}

function cw_hub_geo_merge_ip_results(array $base, array $extra): array
{
    foreach (['country', 'region', 'city'] as $field) {
        if (empty($base[$field]) && !empty($extra[$field])) {
            $base[$field] = $extra[$field];
        }
    }

    $base['is_local'] = false;

    return $base;
}

function cw_hub_resolve_geo_ip(string $ip): array
{
    if (cw_hub_is_private_ip($ip)) {
        return cw_hub_geo_empty_ip_result(true);
    }

    $best = cw_hub_geo_empty_ip_result(false);
    $bestScore = 0;

    foreach ([
        'cw_hub_geo_from_ip_api_com',
        'cw_hub_geo_from_ipwho',
        'cw_hub_geo_from_ipapi_co',
    ] as $resolver) {
        $result = $resolver($ip);
        if (!is_array($result)) {
            continue;
        }

        $score = cw_hub_geo_score($result);
        if ($score <= 0) {
            continue;
        }

        if ($score > $bestScore) {
            $best = $result;
            $bestScore = $score;
            continue;
        }

        if ($score === $bestScore) {
            $best = cw_hub_geo_merge_ip_results($best, $result);
        }
    }

    if ($bestScore > 0) {
        $best['is_local'] = false;

        return $best;
    }

    error_log('[CW Hub] geo IP lookup failed for ' . $ip);

    return cw_hub_geo_empty_ip_result(false);
}

/** Mapa IANA timezone → país (señal pasiva del navegador). */
function cw_hub_tz_country_map(): array
{
    return [
        'America/Mexico_City' => 'México',
        'America/Cancun' => 'México',
        'America/Mazatlan' => 'México',
        'America/Tijuana' => 'México',
        'America/Monterrey' => 'México',
        'America/Chihuahua' => 'México',
        'America/Hermosillo' => 'México',
        'America/Merida' => 'México',
        'America/New_York' => 'Estados Unidos',
        'America/Chicago' => 'Estados Unidos',
        'America/Denver' => 'Estados Unidos',
        'America/Los_Angeles' => 'Estados Unidos',
        'America/Phoenix' => 'Estados Unidos',
        'America/Toronto' => 'Canadá',
        'America/Vancouver' => 'Canadá',
        'Europe/Madrid' => 'España',
        'America/Argentina/Buenos_Aires' => 'Argentina',
        'America/Sao_Paulo' => 'Brasil',
        'America/Santiago' => 'Chile',
        'America/Bogota' => 'Colombia',
        'America/Lima' => 'Perú',
        'America/Caracas' => 'Venezuela',
    ];
}

function cw_hub_tz_region_label(string $tz): string
{
    $map = [
        'America/Mexico_City' => 'CDMX',
        'America/Cancun' => 'Quintana Roo',
        'America/Mazatlan' => 'Pacífico MX',
        'America/Tijuana' => 'Baja California',
        'America/New_York' => 'Este EE.UU.',
        'America/Chicago' => 'Centro EE.UU.',
        'America/Denver' => 'Montaña EE.UU.',
        'America/Los_Angeles' => 'Pacífico EE.UU.',
        'Europe/Madrid' => 'España',
    ];

    return $map[$tz] ?? str_replace('_', ' ', preg_replace('#^America/|^Europe/#', '', $tz));
}

function cw_hub_lang_country_hint(string $lang): ?string
{
    $lang = strtolower(trim($lang));
    $map = [
        'es-mx' => 'México',
        'es-us' => 'Estados Unidos',
        'en-us' => 'Estados Unidos',
        'en-ca' => 'Canadá',
        'es-es' => 'España',
        'es-ar' => 'Argentina',
        'pt-br' => 'Brasil',
        'es-cl' => 'Chile',
        'es-co' => 'Colombia',
        'es-pe' => 'Perú',
        'es-ve' => 'Venezuela',
    ];

    if (isset($map[$lang])) {
        return $map[$lang];
    }

    $base = explode('-', $lang)[0] ?? '';
    if ($base === 'es') {
        return 'Español';
    }

    return null;
}

/** ISO 3166-1 alpha-2 → nombre de país (señal del idioma/región del navegador). */
function cw_hub_country_from_code(string $code): ?string
{
    $code = strtolower(trim($code));
    if ($code === '') {
        return null;
    }

    $map = [
        'mx' => 'México',
        'us' => 'Estados Unidos',
        'ca' => 'Canadá',
        'es' => 'España',
        'ar' => 'Argentina',
        'br' => 'Brasil',
        'cl' => 'Chile',
        'co' => 'Colombia',
        'pe' => 'Perú',
        've' => 'Venezuela',
        'gt' => 'Guatemala',
        'cr' => 'Costa Rica',
        'pa' => 'Panamá',
        'ec' => 'Ecuador',
        'uy' => 'Uruguay',
        'py' => 'Paraguay',
        'bo' => 'Bolivia',
        'hn' => 'Honduras',
        'sv' => 'El Salvador',
        'ni' => 'Nicaragua',
        'do' => 'República Dominicana',
        'pr' => 'Puerto Rico',
        'gb' => 'Reino Unido',
        'de' => 'Alemania',
        'fr' => 'Francia',
        'it' => 'Italia',
    ];

    return $map[$code] ?? null;
}

function cw_hub_resolve_geo_browser(string $clientTz, string $clientLang): array
{
    $clientTz = cw_hub_geo_clean($clientTz, 64);
    $clientLang = cw_hub_geo_clean($clientLang, 16);

    $country = null;
    $region = null;

    if ($clientTz !== '') {
        $tzMap = cw_hub_tz_country_map();
        $country = $tzMap[$clientTz] ?? null;
        if ($country) {
            $region = cw_hub_tz_region_label($clientTz);
        } elseif (str_contains($clientTz, '/')) {
            $region = cw_hub_tz_region_label($clientTz);
        }
    }

    if (!$country && $clientLang !== '') {
        $langCountry = cw_hub_lang_country_hint($clientLang);
        if ($langCountry && $langCountry !== 'Español') {
            $country = $langCountry;
        } elseif ($langCountry === 'Español' && !$region) {
            $region = 'Idioma español';
        }
    }

    return [
        'country' => $country,
        'region' => $region,
        'client_timezone' => $clientTz !== '' ? $clientTz : null,
        'client_lang' => $clientLang !== '' ? $clientLang : null,
    ];
}

/**
 * Resuelve origen geográfico combinando IP + navegador.
 */
function cw_hub_resolve_visit_geo(string $ip, string $clientTz = '', string $clientLang = ''): array
{
    $ipGeo = cw_hub_resolve_geo_ip($ip);
    $browserGeo = cw_hub_resolve_geo_browser($clientTz, $clientLang);

    $country = $ipGeo['country'];
    $region = $ipGeo['region'];
    $source = 'ip';

    if (!empty($ipGeo['is_local']) || $country === null || $country === '') {
        if ($browserGeo['country']) {
            $country = $browserGeo['country'];
            $region = $browserGeo['region'] ?? $region;
            $source = 'browser';
        } else {
            $country = 'Local';
            $region = $browserGeo['region'] ?? 'Desarrollo';
            $source = 'local';
        }
    } elseif ($browserGeo['country'] && $browserGeo['country'] !== $country) {
        $source = 'hybrid';
        if ($region && $browserGeo['region']) {
            $region .= ' · TZ: ' . $browserGeo['region'];
        }
    } elseif (!$region && $browserGeo['region']) {
        $region = $browserGeo['region'];
        $source = 'hybrid';
    }

    return [
        'country' => $country,
        'region' => $region,
        'client_timezone' => $browserGeo['client_timezone'],
        'client_lang' => $browserGeo['client_lang'],
        'geo_source' => $source,
    ];
}

function cw_hub_geo_label(?string $country, ?string $region, ?string $geoSource = null, ?string $city = null): string
{
    return cw_hub_geo_display_label($country, $region, $city, $geoSource);
}

function cw_hub_geo_display_label(
    ?string $country,
    ?string $region,
    ?string $city = null,
    ?string $geoSource = null,
    ?string $geoAddress = null,
    ?int $accuracy = null
): string
{
    $country = trim((string) $country);
    $region = trim((string) $region);
    $city = trim((string) $city);
    $geoAddress = trim((string) $geoAddress);

    if ($geoSource === 'gps') {
        $chunks = [];
        if ($geoAddress !== '') {
            $chunks[] = $geoAddress;
        }
        foreach ([$city, $region, $country] as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }
            $already = false;
            foreach ($chunks as $existing) {
                if (stripos($existing, $part) !== false) {
                    $already = true;
                    break;
                }
            }
            if (!$already && !in_array($part, $chunks, true)) {
                $chunks[] = $part;
            }
        }
        if ($chunks !== []) {
            $label = implode(', ', $chunks);
            if ($accuracy !== null && $accuracy > 0) {
                $label .= ' (±' . $accuracy . ' m)';
            }

            return $label;
        }
    }

    if ($city === '' && str_contains($region, '·')) {
        $parts = array_map('trim', explode('·', $region, 2));
        if (count($parts) === 2) {
            $region = $parts[0];
            $city = $parts[1];
        }
    }

    $chunks = [];
    foreach ([$city, $region, $country] as $part) {
        $part = trim((string) $part);
        if ($part === '') {
            continue;
        }
        if (!in_array($part, $chunks, true)) {
            $chunks[] = $part;
        }
    }

    if ($chunks === []) {
        return 'Desconocido';
    }

    $label = implode(', ', $chunks);

    if ($geoSource === 'browser') {
        $label .= ' (aprox. navegador)';
    } elseif ($geoSource === 'local') {
        $label .= ' (local)';
    }

    return $label;
}

function cw_hub_geo_label_from_row(array $row): string
{
    $accuracy = isset($row['geo_accuracy']) && $row['geo_accuracy'] !== null && $row['geo_accuracy'] !== ''
        ? (int) $row['geo_accuracy']
        : null;

    return cw_hub_geo_display_label(
        $row['country'] ?? null,
        $row['region'] ?? null,
        $row['geo_city'] ?? null,
        $row['geo_source'] ?? null,
        $row['geo_address'] ?? null,
        $accuracy
    );
}

/**
 * Valida coordenadas GPS enviadas por el navegador.
 */
function cw_hub_parse_gps_coords(array $data): ?array
{
    if (!isset($data['geo_lat'], $data['geo_lng'])) {
        return null;
    }

    $lat = (float) $data['geo_lat'];
    $lng = (float) $data['geo_lng'];
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        return null;
    }
    if (abs($lat) < 0.000001 && abs($lng) < 0.000001) {
        return null;
    }

    return [
        'lat' => $lat,
        'lng' => $lng,
        'accuracy' => max(0, min(99999, (int) ($data['geo_accuracy'] ?? 0))),
    ];
}

/**
 * Reverse geocoding vía OpenStreetMap Nominatim (dirección aproximada).
 */
function cw_hub_reverse_geocode_gps(float $lat, float $lng): array
{
    $url = sprintf(
        'https://nominatim.openstreetmap.org/reverse?lat=%s&lon=%s&format=json&addressdetails=1&accept-language=es',
        rawurlencode((string) $lat),
        rawurlencode((string) $lng)
    );
    $raw = cw_hub_geo_http_get($url, 6);
    if ($raw === null) {
        return [];
    }

    $json = json_decode($raw, true);
    if (!is_array($json)) {
        return [];
    }

    $addr = is_array($json['address'] ?? null) ? $json['address'] : [];
    $road = trim((string) ($addr['road'] ?? $addr['pedestrian'] ?? $addr['footway'] ?? ''));
    $house = trim((string) ($addr['house_number'] ?? ''));
    $suburb = trim((string) ($addr['suburb'] ?? $addr['neighbourhood'] ?? $addr['quarter'] ?? $addr['residential'] ?? ''));
    $city = trim((string) ($addr['city'] ?? $addr['town'] ?? $addr['village'] ?? $addr['municipality'] ?? $addr['county'] ?? ''));
    $state = trim((string) ($addr['state'] ?? $addr['region'] ?? ''));
    $country = trim((string) ($addr['country'] ?? ''));

    $streetParts = [];
    if ($road !== '') {
        $streetParts[] = $house !== '' ? ($road . ' ' . $house) : $road;
    }
    if ($suburb !== '') {
        $streetParts[] = $suburb;
    }
    $geoAddress = implode(', ', array_filter($streetParts));
    if ($geoAddress === '') {
        $geoAddress = trim((string) ($json['display_name'] ?? ''));
    }

    return [
        'geo_address' => cw_hub_geo_clean($geoAddress, 255),
        'geo_city' => cw_hub_geo_clean($city, 80),
        'region' => cw_hub_geo_clean($state, 120),
        'country' => cw_hub_geo_clean($country, 80),
    ];
}

function cw_hub_geo_from_gps_coords(float $lat, float $lng, int $accuracy): array
{
    $rev = cw_hub_reverse_geocode_gps($lat, $lng);

    return [
        'country' => $rev['country'] ?? '',
        'region' => $rev['region'] ?? '',
        'geo_city' => $rev['geo_city'] ?? '',
        'geo_address' => $rev['geo_address'] ?? '',
        'geo_lat' => round($lat, 7),
        'geo_lng' => round($lng, 7),
        'geo_accuracy' => $accuracy,
        'geo_source' => 'gps',
        'client_timezone' => null,
        'client_lang' => null,
    ];
}

/**
 * Resuelve geo del visitante: IP pública (ciudad/región real) + navegador en local.
 */
function cw_hub_geo_from_request(array $data, string $clientIp): array
{
    $gps = cw_hub_parse_gps_coords($data);
    if ($gps !== null) {
        return cw_hub_geo_from_gps_coords($gps['lat'], $gps['lng'], $gps['accuracy']);
    }

    $clientTz = cw_hub_geo_clean((string) ($data['client_tz'] ?? ''), 64);
    $clientLang = cw_hub_geo_clean((string) ($data['client_lang'] ?? ''), 16);
    $clientCountry = cw_hub_geo_clean((string) ($data['country'] ?? ''), 80);
    $clientRegion = cw_hub_geo_clean((string) ($data['region'] ?? ''), 80);
    $clientCountryCode = strtolower(cw_hub_geo_clean((string) ($data['client_country_code'] ?? ''), 8));

    if ($clientCountry === '' && $clientCountryCode !== '') {
        $mapped = cw_hub_country_from_code($clientCountryCode);
        if ($mapped) {
            $clientCountry = $mapped;
        }
    }

    $browserGeo = cw_hub_resolve_geo_browser($clientTz, $clientLang);
    $ipGeo = cw_hub_resolve_geo_ip($clientIp);

    $country = '';
    $region = '';
    $city = '';
    $source = 'ip';

    if (!empty($ipGeo['is_local'])) {
        $country = $clientCountry !== '' ? $clientCountry : ($browserGeo['country'] ?? '');
        $region = $clientRegion !== '' ? $clientRegion : ($browserGeo['region'] ?? '');
        $city = '';
        $source = 'browser';
        if ($country === '') {
            $country = 'Local';
            $region = $region !== '' ? $region : 'Desarrollo';
            $source = 'local';
        }
    } elseif (($ipGeo['country'] ?? null) || ($ipGeo['region'] ?? null) || ($ipGeo['city'] ?? null)) {
        $country = (string) ($ipGeo['country'] ?? '');
        $region = (string) ($ipGeo['region'] ?? '');
        $city = (string) ($ipGeo['city'] ?? '');
        $source = 'ip';
    } else {
        $country = $clientCountry !== '' ? $clientCountry : (string) ($browserGeo['country'] ?? '');
        $region = $clientRegion !== '' ? $clientRegion : (string) ($browserGeo['region'] ?? '');
        $city = '';
        $source = $country !== '' ? 'browser' : 'unknown';
        if ($country === '' && $region === '') {
            $country = 'Desconocido';
            $source = 'unknown';
        }
    }

    return [
        'country' => $country,
        'region' => $region,
        'geo_city' => $city,
        'client_timezone' => $clientTz !== '' ? $clientTz : ($browserGeo['client_timezone'] ?? null),
        'client_lang' => $clientLang !== '' ? $clientLang : ($browserGeo['client_lang'] ?? null),
        'geo_source' => $source,
    ];
}

function cw_hub_str_or_empty(?string $v): string
{
    return $v === null ? '' : (string) $v;
}

/** Inserta sesión nueva con geo; fallback si faltan columnas nuevas. */
function cw_hub_insert_session(
    mysqli $conn,
    array $fields
): bool {
    $sqlFull = 'INSERT INTO cw_analytics_sessions
        (session_id, visitor_id, first_seen, last_seen, ip_hash, user_agent, device_type, browser, os,
         screen_w, viewport_w, country, region, geo_city, client_timezone, client_lang, geo_source,
         utm_source, utm_medium, utm_campaign, referrer, landing_url, pages_count, web)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $stmt = $conn->prepare($sqlFull);
    if ($stmt) {
        $screenW = max(0, (int) ($fields['screen_w'] ?? 0));
        $viewportW = max(0, (int) ($fields['viewport_w'] ?? 0));
        $pagesCount = max(0, (int) ($fields['pages_count'] ?? 0));
        $web = cw_hub_str_or_empty($fields['web'] ?? '');
        // 9s + 2i + 11s + 1i + 1s = 24 (session…os, screen/viewport, geo/utm/urls, pages, web)
        try {
            $stmt->bind_param(
                'sssssssssiisssssssssssis',
                $fields['session_id'],
                $fields['visitor_id'],
                $fields['first_seen'],
                $fields['last_seen'],
                $fields['ip_hash'],
                $fields['user_agent'],
                $fields['device_type'],
                $fields['browser'],
                $fields['os'],
                $screenW,
                $viewportW,
                $fields['country'],
                $fields['region'],
                $fields['geo_city'],
                $fields['client_timezone'],
                $fields['client_lang'],
                $fields['geo_source'],
                $fields['utm_source'],
                $fields['utm_medium'],
                $fields['utm_campaign'],
                $fields['referrer'],
                $fields['landing_url'],
                $pagesCount,
                $web
            );
            $ok = $stmt->execute();
            $stmt->close();
            if ($ok) {
                return true;
            }
        } catch (Throwable $e) {
            @$stmt->close();
            error_log('[CW Hub] session insert bind/exec: ' . $e->getMessage());
        }
    }

    $sqlFullNoWeb = 'INSERT INTO cw_analytics_sessions
        (session_id, visitor_id, first_seen, last_seen, ip_hash, user_agent, device_type, browser, os,
         screen_w, viewport_w, country, region, geo_city, client_timezone, client_lang, geo_source,
         utm_source, utm_medium, utm_campaign, referrer, landing_url, pages_count)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $stmtNw = $conn->prepare($sqlFullNoWeb);
    if ($stmtNw) {
        $screenW = max(0, (int) ($fields['screen_w'] ?? 0));
        $viewportW = max(0, (int) ($fields['viewport_w'] ?? 0));
        $stmtNw->bind_param(
            'sssssssssiisssssssssssi',
            $fields['session_id'],
            $fields['visitor_id'],
            $fields['first_seen'],
            $fields['last_seen'],
            $fields['ip_hash'],
            $fields['user_agent'],
            $fields['device_type'],
            $fields['browser'],
            $fields['os'],
            $screenW,
            $viewportW,
            $fields['country'],
            $fields['region'],
            $fields['geo_city'],
            $fields['client_timezone'],
            $fields['client_lang'],
            $fields['geo_source'],
            $fields['utm_source'],
            $fields['utm_medium'],
            $fields['utm_campaign'],
            $fields['referrer'],
            $fields['landing_url'],
            $fields['pages_count']
        );
        $okNw = $stmtNw->execute();
        $stmtNw->close();
        if ($okNw) {
            return true;
        }
    }

    $sqlBasic = 'INSERT INTO cw_analytics_sessions
        (session_id, visitor_id, first_seen, last_seen, ip_hash, user_agent, device_type, browser, os,
         country, region, utm_source, utm_medium, utm_campaign, referrer, landing_url, pages_count)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $stmt2 = $conn->prepare($sqlBasic);
    if (!$stmt2) {
        return false;
    }
    $stmt2->bind_param(
        'ssssssssssssssssi',
        $fields['session_id'],
        $fields['visitor_id'],
        $fields['first_seen'],
        $fields['last_seen'],
        $fields['ip_hash'],
        $fields['user_agent'],
        $fields['device_type'],
        $fields['browser'],
        $fields['os'],
        $fields['country'],
        $fields['region'],
        $fields['utm_source'],
        $fields['utm_medium'],
        $fields['utm_campaign'],
        $fields['referrer'],
        $fields['landing_url'],
        $fields['pages_count']
    );
    $ok2 = $stmt2->execute();
    $stmt2->close();

    return (bool) $ok2;
}

function cw_hub_update_session_geo(mysqli $conn, string $sessionId, array $geo): void
{
    if ($sessionId === '') {
        return;
    }
    $country = cw_hub_str_or_empty($geo['country'] ?? '');
    $region = cw_hub_str_or_empty($geo['region'] ?? '');
    $city = cw_hub_str_or_empty($geo['geo_city'] ?? ($geo['city'] ?? ''));
    $address = cw_hub_str_or_empty($geo['geo_address'] ?? '');
    $lat = isset($geo['geo_lat']) ? (float) $geo['geo_lat'] : null;
    $lng = isset($geo['geo_lng']) ? (float) $geo['geo_lng'] : null;
    $accuracy = isset($geo['geo_accuracy']) ? max(0, (int) $geo['geo_accuracy']) : null;
    if ($country === '' && $region === '' && $city === '' && $address === '' && $lat === null) {
        return;
    }
    $tz = cw_hub_str_or_empty($geo['client_timezone'] ?? '');
    $lang = cw_hub_str_or_empty($geo['client_lang'] ?? '');
    $source = cw_hub_str_or_empty($geo['geo_source'] ?? '');
    $isGps = $source === 'gps' || isset($geo['geo_lat']);

    if ($isGps) {
        $stmt = $conn->prepare('UPDATE cw_analytics_sessions
            SET country = ?, region = ?, geo_city = ?, geo_address = ?, geo_lat = ?, geo_lng = ?, geo_accuracy = ?,
                client_timezone = ?, client_lang = ?, geo_source = ?
            WHERE session_id = ?');
        if ($stmt) {
            $latVal = $lat ?? 0.0;
            $lngVal = $lng ?? 0.0;
            $accVal = $accuracy ?? 0;
            $stmt->bind_param(
                'ssssddissss',
                $country,
                $region,
                $city,
                $address,
                $latVal,
                $lngVal,
                $accVal,
                $tz,
                $lang,
                $source,
                $sessionId
            );
            $stmt->execute();
            $stmt->close();
        }

        return;
    }

    $stmt = $conn->prepare('UPDATE cw_analytics_sessions
        SET country = ?, region = ?, geo_city = ?, client_timezone = ?, client_lang = ?, geo_source = ?
        WHERE session_id = ?');
    if ($stmt) {
        $stmt->bind_param('sssssss', $country, $region, $city, $tz, $lang, $source, $sessionId);
        $stmt->execute();
        $stmt->close();
        return;
    }

    $stmt3 = $conn->prepare('UPDATE cw_analytics_sessions SET country = ?, region = ? WHERE session_id = ?');
    if ($stmt3) {
        $stmt3->bind_param('sss', $country, $region, $sessionId);
        $stmt3->execute();
        $stmt3->close();
    }
}

/** Vincula datos de contacto del lead a la sesión de analytics. */
function cw_hub_update_session_contact(
    mysqli $conn,
    string $sessionId,
    string $nombre,
    string $correo,
    string $telefono,
    int $leadId = 0
): void {
    if ($sessionId === '') {
        return;
    }

    $stmt = $conn->prepare('UPDATE cw_analytics_sessions
        SET contact_nombre = ?, contact_correo = ?, contact_telefono = ?, lead_id = ?
        WHERE session_id = ?');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('sssis', $nombre, $correo, $telefono, $leadId, $sessionId);
    $stmt->execute();
    $stmt->close();
}

function cw_hub_update_pageview_geo(mysqli $conn, int $pageviewId, array $fields): void
{
    if ($pageviewId <= 0) {
        return;
    }
    $stmt = $conn->prepare('UPDATE cw_analytics_pageviews
        SET visitor_id = ?, country = ?, region = ?, geo_city = ?, client_timezone = ?
        WHERE id = ?');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param(
        'sssssi',
        $fields['visitor_id'],
        $fields['country'],
        $fields['region'],
        $fields['geo_city'],
        $fields['client_timezone'],
        $pageviewId
    );
    $stmt->execute();
    $stmt->close();
}

/** Inserta pageview con geo y hora CDMX; actualiza geo si el INSERT básico fue el único posible. */
function cw_hub_insert_pageview(mysqli $conn, array $fields): bool
{
    $country = cw_hub_str_or_empty($fields['country'] ?? '');
    $region = cw_hub_str_or_empty($fields['region'] ?? '');
    $city = cw_hub_str_or_empty($fields['geo_city'] ?? ($fields['city'] ?? ''));
    $tz = cw_hub_str_or_empty($fields['client_timezone'] ?? '');

    $sqlFull = 'INSERT INTO cw_analytics_pageviews
        (session_id, visitor_id, url, path, title, viewed_at, time_on_page, country, region, geo_city, client_timezone, web)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $stmt = $conn->prepare($sqlFull);
    if ($stmt) {
        $web = cw_hub_str_or_empty($fields['web'] ?? '');
        $stmt->bind_param(
            'ssssssisssss',
            $fields['session_id'],
            $fields['visitor_id'],
            $fields['url'],
            $fields['path'],
            $fields['title'],
            $fields['viewed_at'],
            $fields['time_on_page'],
            $country,
            $region,
            $city,
            $tz,
            $web
        );
        $ok = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();
        if ($ok) {
            return true;
        }
        error_log('[CW Hub] pageview insert full failed: ' . $err);
    }

    $sqlFullNoWeb = 'INSERT INTO cw_analytics_pageviews
        (session_id, visitor_id, url, path, title, viewed_at, time_on_page, country, region, geo_city, client_timezone)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $stmtNw = $conn->prepare($sqlFullNoWeb);
    if ($stmtNw) {
        $stmtNw->bind_param(
            'ssssssissss',
            $fields['session_id'],
            $fields['visitor_id'],
            $fields['url'],
            $fields['path'],
            $fields['title'],
            $fields['viewed_at'],
            $fields['time_on_page'],
            $country,
            $region,
            $city,
            $tz
        );
        $okNw = $stmtNw->execute();
        $stmtNw->close();
        if ($okNw) {
            return true;
        }
    }

    $sqlBasic = 'INSERT INTO cw_analytics_pageviews
        (session_id, url, path, title, viewed_at, time_on_page)
        VALUES (?, ?, ?, ?, ?, ?)';
    $stmt2 = $conn->prepare($sqlBasic);
    if (!$stmt2) {
        error_log('[CW Hub] pageview insert prepare failed: ' . $conn->error);
        return false;
    }
    $stmt2->bind_param(
        'sssssi',
        $fields['session_id'],
        $fields['url'],
        $fields['path'],
        $fields['title'],
        $fields['viewed_at'],
        $fields['time_on_page']
    );
    $ok2 = $stmt2->execute();
    if (!$ok2) {
        error_log('[CW Hub] pageview insert basic failed: ' . $stmt2->error);
        $stmt2->close();
        return false;
    }
    $insertId = (int) $conn->insert_id;
    $stmt2->close();

    if ($insertId > 0 && ($country !== '' || $region !== '' || $city !== '')) {
        cw_hub_update_pageview_geo($conn, $insertId, [
            'visitor_id' => cw_hub_str_or_empty($fields['visitor_id'] ?? ''),
            'country' => $country,
            'region' => $region,
            'geo_city' => $city,
            'client_timezone' => $tz,
        ]);
    }

    return true;
}
