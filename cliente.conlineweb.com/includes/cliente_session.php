<?php
/**
 * Configuración de sesión del portal cliente.
 * Cookie propia (CW_CLIENTE_SESS), solo del host — no comparte PHPSESSID con adm.
 */
declare(strict_types=1);

if (!function_exists('cliente_is_https')) {
    function cliente_is_https(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }

        return false;
    }
}

if (!function_exists('cliente_request_host')) {
    function cliente_request_host(): string
    {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        return (string) preg_replace('/:\d+$/', '', $host);
    }
}

if (!function_exists('cliente_is_production_host')) {
    function cliente_is_production_host(): bool
    {
        $host = cliente_request_host();
        if ($host === '') {
            return false;
        }

        return (bool) preg_match('/(^|\.)conlineweb\.com$/', $host);
    }
}

if (!function_exists('cliente_session_name')) {
    function cliente_session_name(): string
    {
        // Siempre nombre propio: no colisionar con PHPSESSID de adm ("sesión nueva").
        return 'CW_CLIENTE_SESS';
    }
}

if (!function_exists('cliente_login_is_true')) {
    function cliente_login_is_true($value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }
}

if (!function_exists('cliente_sync_session_identity')) {
    function cliente_sync_session_identity(): void
    {
        if (!cliente_login_is_true($_SESSION['login'] ?? false)) {
            return;
        }

        if (!empty($_SESSION['uid']) && empty($_SESSION['id'])) {
            $_SESSION['id'] = session_id();
        }
    }
}

if (!function_exists('cliente_is_logged_in')) {
    function cliente_is_logged_in(): bool
    {
        cliente_sync_session_identity();

        return !empty($_SESSION['uid'])
            && cliente_login_is_true($_SESSION['login'] ?? false);
    }
}

if (!function_exists('cliente_expire_host_cookie')) {
    function cliente_expire_host_cookie(string $name): void
    {
        $secure = cliente_is_https();
        $params = [
            'expires' => time() - 42000,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
        ];

        if (PHP_VERSION_ID >= 70300) {
            $params['samesite'] = 'Lax';
            setcookie($name, '', $params);
            return;
        }

        setcookie($name, '', time() - 42000, '/', '', $secure, true);
    }
}

if (!function_exists('cliente_configure_session')) {
    function cliente_configure_session(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $secure = cliente_is_https();
        $lifetime = 86400;
        $sessionName = cliente_session_name();

        if ($sessionName !== session_name()) {
            session_name($sessionName);
        }

        // Host-only: sin domain=.conlineweb.com (no toca la sesión del admin).
        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            ini_set('session.cookie_path', '/');
            ini_set('session.cookie_domain', '');
            ini_set('session.cookie_secure', $secure ? '1' : '0');
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_lifetime', (string) $lifetime);
            ini_set('session.gc_maxlifetime', (string) $lifetime);
        }
    }
}

if (!function_exists('cliente_start_session')) {
    function cliente_start_session(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            cliente_sync_session_identity();
            return;
        }

        cliente_configure_session();
        session_start();
        cliente_sync_session_identity();
        $shared = dirname(__DIR__, 2) . '/includes/cw_portal_security.php';
        if (is_file($shared)) {
            require_once $shared;
            if (function_exists('cw_portal_csrf_token') && cliente_is_logged_in()) {
                cw_portal_csrf_token();
            }
        }
    }
}

if (!function_exists('cliente_set_staff_sso_cookie')) {
    /**
     * Cookie SSO staff → adm (solo producción *.conlineweb.com).
     * Valor firmado HMAC (cw_portal_sso_encode).
     */
    function cliente_set_staff_sso_cookie(array $cookieData, int $lifetime = 86400): void
    {
        if (!cliente_is_production_host()) {
            return;
        }

        if (!function_exists('cw_portal_sso_encode')) {
            foreach ([
                __DIR__ . '/cw_portal_security.php',
                dirname(__DIR__, 2) . '/includes/cw_portal_security.php',
                '/home/conlineweb/includes/cw_portal_security.php',
            ] as $shared) {
                if (is_file($shared)) {
                    require_once $shared;
                    break;
                }
            }
        }

        // Limpiar nulls que rompen JSON/firma innecesariamente
        foreach ($cookieData as $k => $v) {
            if ($v === null) {
                unset($cookieData[$k]);
            }
        }
        if (!isset($cookieData['timestamp'])) {
            $cookieData['timestamp'] = time();
        }
        if (!isset($cookieData['login'])) {
            $cookieData['login'] = true;
        }

        $value = function_exists('cw_portal_sso_encode')
            ? cw_portal_sso_encode($cookieData)
            : (string) json_encode($cookieData, JSON_UNESCAPED_UNICODE);
        if ($value === '' || $value === 'false' || $value === 'null') {
            error_log('cliente_set_staff_sso_cookie: valor vacío, cookie no escrita');
            return;
        }

        $expires = time() + $lifetime;
        $secure = cliente_is_https();

        if (PHP_VERSION_ID >= 70300) {
            setcookie('user_session_data', $value, [
                'expires' => $expires,
                'path' => '/',
                'domain' => '.conlineweb.com',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            return;
        }

        setcookie('user_session_data', $value, $expires, '/', '.conlineweb.com', $secure, true);
    }
}

if (!function_exists('cliente_clear_staff_sso_cookie')) {
    function cliente_clear_staff_sso_cookie(): void
    {
        if (!cliente_is_production_host()) {
            return;
        }

        $secure = cliente_is_https();
        if (PHP_VERSION_ID >= 70300) {
            setcookie('user_session_data', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'domain' => '.conlineweb.com',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            return;
        }

        setcookie('user_session_data', '', time() - 3600, '/', '.conlineweb.com', $secure, true);
    }
}
