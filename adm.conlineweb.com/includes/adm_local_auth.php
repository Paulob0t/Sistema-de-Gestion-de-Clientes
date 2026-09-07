<?php
/**
 * Utilidades de desarrollo local del panel admin.
 * Se activa automáticamente fuera de *.conlineweb.com (sin archivos de configuración).
 */
declare(strict_types=1);

if (!function_exists('adm_is_local_environment')) {
    function adm_is_local_environment(): bool
    {
        return !adm_is_production_host();
    }
}

if (!function_exists('adm_local_bootstrap')) {
    function adm_local_bootstrap(): void
    {
        static $done = false;
        if ($done || adm_is_production_host()) {
            return;
        }
        $done = true;

        $override = __DIR__ . '/adm_local.php';
        if (is_file($override)) {
            require_once $override;
        }
    }
}

if (!function_exists('adm_local_token_secret')) {
    function adm_local_token_secret(): string
    {
        adm_local_bootstrap();

        if (defined('ADM_LOCAL_TOKEN_SECRET') && (string) ADM_LOCAL_TOKEN_SECRET !== '') {
            return (string) ADM_LOCAL_TOKEN_SECRET;
        }

        return 'cw_conlineweb_local_dev_v1';
    }
}

if (!function_exists('adm_local_request_authority')) {
    /** Host[:puerto] de la petición actual (p. ej. 127.0.0.1:8888). Solo para URLs locales. */
    function adm_local_request_authority(): string
    {
        $authority = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($authority !== '') {
            return $authority;
        }

        return adm_request_host() !== '' ? adm_request_host() : 'localhost';
    }
}

if (!function_exists('adm_local_peer_base_url')) {
    function adm_local_peer_base_url(string $peerFolder): string
    {
        adm_local_bootstrap();

        $scheme = adm_is_https() ? 'https' : 'http';
        $host = adm_request_host();
        $authority = adm_local_request_authority();

        if ($peerFolder === 'cliente.conlineweb.com' && defined('ADM_LOCAL_CLIENTE_URL') && (string) ADM_LOCAL_CLIENTE_URL !== '') {
            return rtrim((string) ADM_LOCAL_CLIENTE_URL, '/');
        }

        if (preg_match('/(^|\.)conlineweb\.local$/', $host)) {
            $sub = explode('.', $peerFolder)[0];
            return $scheme . '://' . $sub . '.conlineweb.local';
        }

        if (in_array($host, ['localhost', '127.0.0.1'], true)) {
            $project = basename(dirname(__DIR__, 2));
            // Conservar :8888 (u otro puerto MAMP); sin eso el login salta a :80 y pierde la sesión.
            return $scheme . '://' . $authority . '/' . $project . '/' . $peerFolder;
        }

        return $scheme . '://' . $authority . '/' . $peerFolder;
    }
}

if (!function_exists('adm_local_cliente_url')) {
    function adm_local_cliente_url(string $path = ''): string
    {
        $base = adm_local_peer_base_url('cliente.conlineweb.com');
        $path = ltrim(str_replace('\\', '/', $path), '/');

        return $path === '' ? $base . '/' : $base . '/' . $path;
    }
}

if (!function_exists('adm_local_consume_token')) {
    function adm_local_consume_token(string $token): bool
    {
        if (!adm_is_local_environment()) {
            return false;
        }

        $secret = adm_local_token_secret();
        if ($token === '') {
            return false;
        }

        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return false;
        }

        [$payloadB64, $signature] = $parts;
        $b64 = strtr($payloadB64, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }
        $json = base64_decode($b64, true);
        if ($json === false) {
            return false;
        }

        $expected = hash_hmac('sha256', $json, $secret);
        if (!hash_equals($expected, $signature)) {
            return false;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return false;
        }

        $uid = (int) ($data['uid'] ?? 0);
        $tipo = (int) ($data['tipo'] ?? 0);
        $exp = (int) ($data['exp'] ?? 0);
        $now = time();

        if ($uid <= 0 || $tipo < 1 || $tipo > 5 || $exp <= $now) {
            return false;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $_SESSION['uid'] = $uid;
        $_SESSION['login'] = true;
        $_SESSION['tipo'] = $tipo;
        $_SESSION['id'] = session_id();
        $_SESSION['last_activity'] = $now;
        $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'] ?? null;

        if (!empty($data['agente_id'])) {
            $_SESSION['agente_id'] = (int) $data['agente_id'];
        } else {
            unset($_SESSION['agente_id']);
        }

        // Local: misma alerta que SSO en producción al entrar al ADM
        if (function_exists('adm_try_send_sso_login_alert')) {
            adm_try_send_sso_login_alert([
                'uid' => $uid,
                'tipo' => $tipo,
                'usuario' => 'UID #' . $uid,
                'agente_id' => !empty($_SESSION['agente_id']) ? (int) $_SESSION['agente_id'] : null,
            ]);
        } else {
            // auth_middleware define la función; si el token se consume antes, cargar alerta directo
            foreach ([
                __DIR__ . '/cw_portal_login_alert.php',
                dirname(__DIR__, 2) . '/includes/cw_portal_login_alert.php',
            ] as $alertPath) {
                if (is_file($alertPath)) {
                    require_once $alertPath;
                    break;
                }
            }
            if (function_exists('cw_portal_notify_login')) {
                cw_portal_notify_login([
                    'uid' => $uid,
                    'usuario' => 'UID #' . $uid,
                    'tipo' => $tipo,
                    'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                    'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
                    'agente_id' => $_SESSION['agente_id'] ?? null,
                    'environment' => 'local',
                    'skip_geo' => true,
                ]);
            }
        }

        return true;
    }
}

if (!function_exists('adm_local_strip_token_from_request_uri')) {
    function adm_local_strip_token_from_request_uri(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $parts = parse_url($uri);
        if (!is_array($parts)) {
            return $uri;
        }

        $path = $parts['path'] ?? '/';
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        unset($query['cw_local_token']);

        $scheme = adm_is_https() ? 'https' : 'http';
        $url = $scheme . '://' . adm_local_request_authority() . $path;

        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }
}
