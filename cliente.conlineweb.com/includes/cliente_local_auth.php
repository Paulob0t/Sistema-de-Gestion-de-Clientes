<?php
/**
 * Utilidades de desarrollo local del portal cliente.
 * Se activa automáticamente fuera de *.conlineweb.com (sin archivos de configuración).
 */
declare(strict_types=1);

if (!function_exists('cliente_is_local_environment')) {
    function cliente_is_local_environment(): bool
    {
        return !cliente_is_production_host();
    }
}

if (!function_exists('cliente_local_bootstrap')) {
    function cliente_local_bootstrap(): void
    {
        static $done = false;
        if ($done || cliente_is_production_host()) {
            return;
        }
        $done = true;

        $override = __DIR__ . '/cliente_local.php';
        if (is_file($override)) {
            require_once $override;
        }
    }
}

if (!function_exists('cliente_local_token_secret')) {
    function cliente_local_token_secret(): string
    {
        cliente_local_bootstrap();

        if (defined('CLIENTE_LOCAL_TOKEN_SECRET') && (string) CLIENTE_LOCAL_TOKEN_SECRET !== '') {
            return (string) CLIENTE_LOCAL_TOKEN_SECRET;
        }

        return 'cw_conlineweb_local_dev_v1';
    }
}

if (!function_exists('cliente_local_request_authority')) {
    /** Host[:puerto] de la petición actual (p. ej. 127.0.0.1:8888). Solo para URLs locales. */
    function cliente_local_request_authority(): string
    {
        $authority = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($authority !== '') {
            return $authority;
        }

        return cliente_request_host() !== '' ? cliente_request_host() : 'localhost';
    }
}

if (!function_exists('cliente_local_peer_base_url')) {
    function cliente_local_peer_base_url(string $peerFolder): string
    {
        cliente_local_bootstrap();

        $scheme = cliente_is_https() ? 'https' : 'http';
        $host = cliente_request_host();
        $authority = cliente_local_request_authority();

        if ($peerFolder === 'adm.conlineweb.com' && defined('CLIENTE_LOCAL_ADM_URL') && (string) CLIENTE_LOCAL_ADM_URL !== '') {
            return rtrim((string) CLIENTE_LOCAL_ADM_URL, '/');
        }

        if (preg_match('/(^|\.)conlineweb\.local$/', $host)) {
            $sub = explode('.', $peerFolder)[0];
            return $scheme . '://' . $sub . '.conlineweb.local';
        }

        if (in_array($host, ['localhost', '127.0.0.1'], true)) {
            $project = basename(dirname(__DIR__, 2));
            // Conservar :8888 (u otro puerto MAMP); sin eso el redirect a adm va a :80.
            return $scheme . '://' . $authority . '/' . $project . '/' . $peerFolder;
        }

        return $scheme . '://' . $authority . '/' . $peerFolder;
    }
}

if (!function_exists('cliente_local_adm_url')) {
    function cliente_local_adm_url(string $path = ''): string
    {
        $base = cliente_local_peer_base_url('adm.conlineweb.com');
        $path = ltrim(str_replace('\\', '/', $path), '/');

        return $path === '' ? $base . '/' : $base . '/' . $path;
    }
}

if (!function_exists('cliente_local_create_token')) {
    function cliente_local_create_token(int $uid, int $tipo, ?int $agente_id = null): string
    {
        $secret = cliente_local_token_secret();
        $payload = [
            'uid' => $uid,
            'tipo' => $tipo,
            'exp' => time() + 120,
        ];

        if ($agente_id) {
            $payload['agente_id'] = $agente_id;
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $json, $secret);
        $payloadB64 = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');

        return $payloadB64 . '.' . $signature;
    }
}

if (!function_exists('cliente_local_append_token')) {
    function cliente_local_append_token(string $url, string $token): string
    {
        if ($token === '') {
            return $url;
        }

        $separator = (strpos($url, '?') === false) ? '?' : '&';

        return $url . $separator . 'cw_local_token=' . rawurlencode($token);
    }
}

if (!function_exists('cliente_local_login_redirect')) {
    function cliente_local_login_redirect(int $tipo, ?int $agente_id, int $uid, ?string $token = null): string
    {
        if ($tipo === 5) {
            $url = cliente_local_adm_url('leads');
        } elseif ($tipo === 3) {
            $url = $agente_id
                ? cliente_local_adm_url('solicitudes/tickets_desarrollador.php?id=' . $agente_id)
                : cliente_local_adm_url('solicitudes/');
        } elseif ($tipo === 4) {
            $url = cliente_local_adm_url('tickets_externa/');
        } elseif ($tipo === 1) {
            $url = cliente_local_adm_url('');
        } elseif ($tipo === 2) {
            $url = cliente_local_adm_url('solicitudes/');
        } else {
            return 'index.php';
        }

        if ($token === null || $token === '') {
            $token = cliente_local_create_token($uid, $tipo, $agente_id);
        }

        return cliente_local_append_token($url, $token);
    }
}

if (!function_exists('cliente_captcha_required')) {
    function cliente_captcha_required(): bool
    {
        return cliente_is_production_host();
    }
}
