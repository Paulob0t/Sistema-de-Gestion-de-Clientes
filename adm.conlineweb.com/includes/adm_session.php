<?php
/**
 * Configuración de sesión PHP del panel admin.
 * Producción (*.conlineweb.com): cookies compartidas en .conlineweb.com (sin cambios).
 * Local: sesión aislada al host actual (sin compartir con otros subdominios).
 */
declare(strict_types=1);

if (!function_exists('adm_is_https')) {
    function adm_is_https(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_SSL'])
            && strtolower((string) $_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') {
            return true;
        }
        if (!empty($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
            return true;
        }

        return false;
    }
}

if (!function_exists('adm_request_host')) {
    function adm_request_host(): string
    {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        return (string) preg_replace('/:\d+$/', '', $host);
    }
}

if (!function_exists('adm_is_production_host')) {
    function adm_is_production_host(): bool
    {
        $host = adm_request_host();
        if ($host === '') {
            return false;
        }

        return (bool) preg_match('/(^|\.)conlineweb\.com$/', $host);
    }
}

if (!function_exists('adm_cookie_domain')) {
    function adm_cookie_domain(): string
    {
        return adm_is_production_host() ? '.conlineweb.com' : '';
    }
}

if (!function_exists('adm_is_localhost_subfolder')) {
    function adm_is_localhost_subfolder(): bool
    {
        return in_array(adm_request_host(), ['localhost', '127.0.0.1'], true);
    }
}

if (!function_exists('adm_session_name')) {
    function adm_session_name(): string
    {
        return adm_is_production_host() ? 'PHPSESSID' : 'CW_ADM_SESS';
    }
}

if (!function_exists('adm_login_value_is_true')) {
    function adm_login_value_is_true($value): bool
    {
        return !empty($value)
            && ($value === true || $value === 1 || $value === '1' || $value === 'true');
    }
}

if (!function_exists('adm_configure_session')) {
    function adm_configure_session(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $secure = adm_is_https();
        $lifetime = 86400;
        $sessionName = adm_session_name();

        if ($sessionName !== session_name()) {
            session_name($sessionName);
        }

        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            ini_set('session.cookie_path', '/');
            ini_set('session.cookie_secure', $secure ? '1' : '0');
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_lifetime', (string) $lifetime);
            ini_set('session.gc_maxlifetime', (string) $lifetime);
        }
    }
}

if (!function_exists('adm_start_session')) {
    function adm_start_session(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        adm_configure_session();
        session_start();

        if (adm_login_value_is_true($_SESSION['login'] ?? null) && !empty($_SESSION['uid']) && empty($_SESSION['id'])) {
            $_SESSION['id'] = session_id();
        }
    }
}

if (!function_exists('adm_set_shared_cookie')) {
    function adm_set_shared_cookie(string $name, string $value, int $expires): void
    {
        if (!adm_is_production_host()) {
            return;
        }

        $secure = adm_is_https();
        $domain = adm_cookie_domain();
        $options = [
            'expires' => $expires,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
        ];

        if (PHP_VERSION_ID >= 70300) {
            $options['samesite'] = 'Lax';
            if ($domain !== '') {
                $options['domain'] = $domain;
            }
            setcookie($name, $value, $options);
            return;
        }

        setcookie($name, $value, $expires, '/', $domain !== '' ? $domain : false, $secure, true);
    }
}

if (!function_exists('adm_clear_shared_cookie')) {
    function adm_clear_shared_cookie(string $name): void
    {
        if (adm_is_production_host()) {
            adm_set_shared_cookie($name, '', time() - 3600);
        }

        setcookie($name, '', time() - 3600, '/', adm_request_host(), adm_is_https(), true);
    }
}
