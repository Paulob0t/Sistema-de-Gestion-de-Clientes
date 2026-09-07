<?php
/**
 * Geolocalización por dispositivo para acceso al panel ADM.
 * Cookie firmada en .conlineweb.com — no vuelve a pedir en el mismo dispositivo.
 */
declare(strict_types=1);

if (!function_exists('cw_adm_device_geo_cookie_name')) {
    function cw_adm_device_geo_cookie_name(): string
    {
        return 'cw_adm_device_geo';
    }
}

if (!function_exists('cw_adm_device_geo_ttl')) {
    function cw_adm_device_geo_ttl(): int
    {
        return 90 * 86400;
    }
}

if (!function_exists('cw_adm_device_geo_encode')) {
    function cw_adm_device_geo_encode(array $payload): string
    {
        if (function_exists('cw_portal_sso_encode')) {
            return cw_portal_sso_encode($payload);
        }

        return 'legacy.' . base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE));
    }
}

if (!function_exists('cw_adm_device_geo_decode')) {
    function cw_adm_device_geo_decode(string $raw): ?array
    {
        if ($raw === '') {
            return null;
        }
        if (function_exists('cw_portal_sso_decode')) {
            $data = cw_portal_sso_decode($raw, true);
            return is_array($data) ? $data : null;
        }
        if (str_starts_with($raw, 'legacy.')) {
            $json = base64_decode(substr($raw, 7), true);
            $data = is_string($json) ? json_decode($json, true) : null;

            return is_array($data) ? $data : null;
        }

        return null;
    }
}

if (!function_exists('cw_adm_device_geo_normalize_device_id')) {
    function cw_adm_device_geo_normalize_device_id(string $deviceId): string
    {
        $deviceId = strtolower(trim($deviceId));
        if (strlen($deviceId) > 64) {
            $deviceId = substr($deviceId, 0, 64);
        }

        return preg_match('/^[a-f0-9\-]{16,64}$/', $deviceId) ? $deviceId : '';
    }
}

if (!function_exists('cw_adm_device_geo_read')) {
    /**
     * @return array{uid:int,device_id:string,lat:float,lng:float,accuracy:?int,ts:int}|null
     */
    function cw_adm_device_geo_read(int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }
        $raw = (string) ($_COOKIE[cw_adm_device_geo_cookie_name()] ?? '');
        if ($raw === '') {
            return null;
        }
        $data = cw_adm_device_geo_decode($raw);
        if (!is_array($data)) {
            return null;
        }
        $cookieUid = (int) ($data['uid'] ?? 0);
        $deviceId = cw_adm_device_geo_normalize_device_id((string) ($data['device_id'] ?? ''));
        $lat = (float) ($data['lat'] ?? 0);
        $lng = (float) ($data['lng'] ?? 0);
        $ts = (int) ($data['ts'] ?? 0);
        $now = time();

        if ($cookieUid !== $uid || $deviceId === '' || $ts <= 0 || ($now - $ts) > cw_adm_device_geo_ttl()) {
            return null;
        }
        if (function_exists('cw_portal_login_gps_valida') && !cw_portal_login_gps_valida($lat, $lng)) {
            return null;
        }

        $acc = isset($data['accuracy']) && $data['accuracy'] !== '' && is_numeric($data['accuracy'])
            ? (int) round((float) $data['accuracy'])
            : null;

        return [
            'uid' => $cookieUid,
            'device_id' => $deviceId,
            'lat' => $lat,
            'lng' => $lng,
            'accuracy' => $acc,
            'ts' => $ts,
        ];
    }
}

if (!function_exists('cw_adm_device_geo_requires_prompt')) {
    function cw_adm_device_geo_requires_prompt(int $uid): bool
    {
        return cw_adm_device_geo_read($uid) === null;
    }
}

if (!function_exists('cw_adm_device_geo_save')) {
    /**
     * @return bool
     */
    function cw_adm_device_geo_save(int $uid, string $deviceId, float $lat, float $lng, ?int $accuracy = null): bool
    {
        if ($uid <= 0) {
            return false;
        }
        $deviceId = cw_adm_device_geo_normalize_device_id($deviceId);
        if ($deviceId === '') {
            return false;
        }
        if (function_exists('cw_portal_login_gps_valida') && !cw_portal_login_gps_valida($lat, $lng)) {
            return false;
        }

        $payload = [
            'uid' => $uid,
            'device_id' => $deviceId,
            'lat' => $lat,
            'lng' => $lng,
            'accuracy' => $accuracy,
            'ts' => time(),
        ];
        $encoded = cw_adm_device_geo_encode($payload);
        $expires = time() + cw_adm_device_geo_ttl();

        if (function_exists('adm_set_shared_cookie')) {
            adm_set_shared_cookie(cw_adm_device_geo_cookie_name(), $encoded, $expires);

            return true;
        }
        if (function_exists('cliente_set_staff_sso_cookie')) {
            // fallback local sin adm_session
            setcookie(cw_adm_device_geo_cookie_name(), $encoded, [
                'expires' => $expires,
                'path' => '/',
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);

            return true;
        }

        return false;
    }
}

if (!function_exists('cw_adm_device_geo_attach_to_login_event')) {
    function cw_adm_device_geo_attach_to_login_event(mysqli $conn, int $loginEventId, float $lat, float $lng, ?int $accuracy = null): void
    {
        if ($loginEventId <= 0 || !function_exists('cw_portal_login_log_set_gps')) {
            return;
        }
        try {
            cw_portal_login_log_set_gps($conn, $loginEventId, $lat, $lng, $accuracy);
        } catch (Throwable $e) {
            error_log('cw_adm_device_geo_attach_to_login_event: ' . $e->getMessage());
        }
    }
}

if (!function_exists('cw_adm_device_geo_gate_exempt_path')) {
    function cw_adm_device_geo_gate_exempt_path(string $scriptName): bool
    {
        if ($scriptName === '') {
            return false;
        }

        return (bool) preg_match(
            '#/(geo_consent\.php|api/geo_device\.php|cerrarSesion\.php)(?:$|\?)#i',
            $scriptName
        );
    }
}
