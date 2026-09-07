<?php
/**
 * Seguridad compartida: adm + cliente (SSO, passwords, captcha, rate limit, CSRF).
 * Ruta: /sistema/includes/cw_portal_security.php
 * Compatible PHP 7.4+ (cPanel).
 */
declare(strict_types=1);

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }
        $len = strlen($needle);

        return $len <= strlen($haystack) && substr($haystack, -$len) === $needle;
    }
}
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('cw_portal_security_secret')) {
    /**
     * Secreto para firmar SSO / CSRF. Preferir env CW_PORTAL_SSO_SECRET.
     * El fallback NO usa __DIR__ (debe ser idéntico en adm y cliente).
     */
    function cw_portal_security_secret(): string
    {
        static $secret = null;
        if (is_string($secret)) {
            return $secret;
        }

        $fromEnv = trim((string) (getenv('CW_PORTAL_SSO_SECRET') ?: ''));
        if ($fromEnv !== '') {
            $secret = $fromEnv;

            return $secret;
        }

        $secretFiles = [
            dirname(__DIR__) . '/includes/cw_portal_secrets.local.php',
            dirname(__DIR__) . '/cw_portal_secrets.local.php',
            __DIR__ . '/cw_portal_secrets.local.php',
            '/home/conlineweb/includes/cw_portal_secrets.local.php',
            '/home/conlineweb/cw_portal_secrets.local.php',
        ];
        // Si este archivo vive en adm/.../includes o cliente/.../includes, subir 2 niveles
        $secretFiles[] = dirname(__DIR__, 2) . '/includes/cw_portal_secrets.local.php';
        $secretFiles[] = dirname(__DIR__, 2) . '/cw_portal_secrets.local.php';

        foreach ($secretFiles as $local) {
            if (!is_file($local)) {
                continue;
            }
            $data = include $local;
            if (is_array($data) && !empty($data['sso_secret'])) {
                $secret = (string) $data['sso_secret'];

                return $secret;
            }
        }

        // Fallback estable compartido adm + cliente (no depende de la carpeta del include)
        $secret = hash('sha256', 'conlineweb-portal-sso-v1|shared');

        return $secret;
    }
}

if (!function_exists('cw_portal_b64url_encode')) {
    function cw_portal_b64url_encode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}

if (!function_exists('cw_portal_b64url_decode')) {
    /**
     * @return string|false
     */
    function cw_portal_b64url_decode(string $encoded)
    {
        $remainder = strlen($encoded) % 4;
        if ($remainder > 0) {
            $encoded .= str_repeat('=', 4 - $remainder);
        }
        $raw = base64_decode(strtr($encoded, '-_', '+/'), true);

        return $raw === false ? false : $raw;
    }
}

if (!function_exists('cw_portal_sso_encode')) {
    /** @param array<string,mixed> $payload */
    function cw_portal_sso_encode(array $payload): string
    {
        if (!isset($payload['timestamp'])) {
            $payload['timestamp'] = time();
        }
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return '';
        }
        $body = cw_portal_b64url_encode($json);
        $sig = cw_portal_b64url_encode(hash_hmac('sha256', $body, cw_portal_security_secret(), true));

        return 'v1.' . $body . '.' . $sig;
    }
}

if (!function_exists('cw_portal_sso_secret_candidates')) {
    /** @return list<string> */
    function cw_portal_sso_secret_candidates(): array
    {
        $out = [cw_portal_security_secret()];
        // Secretos legacy (cuando el fallback usaba __DIR__ distinto en adm vs cliente)
        $legacyDirs = [
            '/home/conlineweb/includes',
            '/home/conlineweb/cliente.conlineweb.com/includes',
            '/home/conlineweb/adm.conlineweb.com/includes',
            __DIR__,
        ];
        foreach ($legacyDirs as $dir) {
            $out[] = hash('sha256', 'conlineweb-portal-sso-v1|' . $dir);
        }

        return array_values(array_unique($out));
    }
}

if (!function_exists('cw_portal_sso_decode')) {
    /**
     * Decodifica cookie SSO firmada. Acepta legacy JSON sin firma (gracia).
     *
     * @return array<string,mixed>|null
     */
    function cw_portal_sso_decode(string $raw, bool $allowLegacy = true): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (str_starts_with($raw, 'v1.')) {
            $parts = explode('.', $raw, 3);
            if (count($parts) !== 3) {
                return null;
            }
            $body = $parts[1];
            $sig = $parts[2];
            $ok = false;
            foreach (cw_portal_sso_secret_candidates() as $sec) {
                $expected = cw_portal_b64url_encode(hash_hmac('sha256', $body, $sec, true));
                if (hash_equals($expected, $sig)) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) {
                return null;
            }
            $json = cw_portal_b64url_decode($body);
            if ($json === false) {
                return null;
            }
            $data = json_decode($json, true);

            return is_array($data) ? $data : null;
        }

        if (!$allowLegacy) {
            return null;
        }

        // Legacy: JSON plano (migración).
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $data = json_decode(urldecode($raw), true);
        }

        return is_array($data) ? $data : null;
    }
}

if (!function_exists('cw_portal_password_hash')) {
    function cw_portal_password_hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_DEFAULT);
    }
}

if (!function_exists('cw_portal_password_verify')) {
    /**
     * Soporta bcrypt/argon y legacy MD5 (32 hex).
     */
    function cw_portal_password_verify(string $plain, string $stored): bool
    {
        $stored = trim($stored);
        if ($stored === '' || $plain === '') {
            return false;
        }

        if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$')
            || str_starts_with($stored, '$argon2i$') || str_starts_with($stored, '$argon2id$')) {
            return password_verify($plain, $stored);
        }

        // Legacy MD5
        if (preg_match('/^[a-f0-9]{32}$/i', $stored)) {
            return hash_equals(strtolower($stored), md5($plain));
        }

        return hash_equals($stored, $plain);
    }
}

if (!function_exists('cw_portal_password_needs_rehash')) {
    function cw_portal_password_needs_rehash(string $stored): bool
    {
        $stored = trim($stored);
        if ($stored === '') {
            return true;
        }
        if (preg_match('/^[a-f0-9]{32}$/i', $stored)) {
            return true;
        }
        if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$')
            || str_starts_with($stored, '$argon2i$') || str_starts_with($stored, '$argon2id$')) {
            return password_needs_rehash($stored, PASSWORD_DEFAULT);
        }

        return true;
    }
}

if (!function_exists('cw_portal_client_ip')) {
    function cw_portal_client_ip(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
            $cand = trim($parts[0]);
            if (filter_var($cand, FILTER_VALIDATE_IP)) {
                $ip = $cand;
            }
        }

        return $ip;
    }
}

if (!function_exists('cw_portal_rate_limit_dir')) {
    function cw_portal_rate_limit_dir(): string
    {
        $dir = sys_get_temp_dir() . '/cw_portal_rate';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        return $dir;
    }
}

if (!function_exists('cw_portal_rate_limit_hit')) {
    /**
     * @return array{ok:bool,remaining:int,retry_after:int}
     */
    function cw_portal_rate_limit_hit(string $bucket, int $maxAttempts = 8, int $windowSec = 900): array
    {
        $key = hash('sha256', $bucket);
        $file = cw_portal_rate_limit_dir() . '/' . $key . '.json';
        $now = time();
        $data = ['start' => $now, 'count' => 0];

        if (is_file($file)) {
            $raw = @file_get_contents($file);
            $parsed = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($parsed) && isset($parsed['start'], $parsed['count'])) {
                $data = $parsed;
            }
        }

        if (($now - (int) $data['start']) > $windowSec) {
            $data = ['start' => $now, 'count' => 0];
        }

        $data['count'] = (int) $data['count'] + 1;
        @file_put_contents($file, json_encode($data), LOCK_EX);

        $ok = $data['count'] <= $maxAttempts;
        $retry = max(0, $windowSec - ($now - (int) $data['start']));

        return [
            'ok' => $ok,
            'remaining' => max(0, $maxAttempts - (int) $data['count']),
            'retry_after' => $ok ? 0 : $retry,
        ];
    }
}

if (!function_exists('cw_portal_rate_limit_clear')) {
    function cw_portal_rate_limit_clear(string $bucket): void
    {
        $file = cw_portal_rate_limit_dir() . '/' . hash('sha256', $bucket) . '.json';
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

if (!function_exists('cw_portal_verify_recaptcha')) {
    /**
     * @return array{ok:bool,error?:string}
     */
    function cw_portal_verify_recaptcha(string $token, string $secret = ''): array
    {
        $token = trim($token);
        if ($token === '') {
            return ['ok' => false, 'error' => 'Captcha vacío'];
        }

        if ($secret === '') {
            $secret = trim((string) (getenv('CW_RECAPTCHA_SECRET') ?: ''));
        }
        if ($secret === '') {
            // Par site key 6LfPDTQm… (portal / hub MX)
            $secret = '6LfPDTQmAAAAAJXDg4UTYJbGonWOzsOYQ-QR5jP_';
        }

        $payload = http_build_query([
            'secret' => $secret,
            'response' => $token,
            'remoteip' => cw_portal_client_ip(),
        ]);

        $raw = null;
        if (function_exists('curl_init')) {
            $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_TIMEOUT => 8,
            ]);
            $raw = curl_exec($ch);
            curl_close($ch);
        }
        if (!is_string($raw) || $raw === '') {
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                    'content' => $payload,
                    'timeout' => 8,
                ],
            ]);
            $raw = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $ctx);
        }

        if (!is_string($raw) || $raw === '') {
            return ['ok' => false, 'error' => 'No se pudo validar captcha'];
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['success'])) {
            return ['ok' => false, 'error' => 'Captcha inválido'];
        }

        return ['ok' => true];
    }
}

if (!function_exists('cw_portal_csrf_token')) {
    function cw_portal_csrf_token(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Caller debe haber iniciado sesión; generar efímero si no.
            return cw_portal_b64url_encode(random_bytes(32));
        }
        if (empty($_SESSION['_cw_csrf']) || !is_string($_SESSION['_cw_csrf'])) {
            $_SESSION['_cw_csrf'] = cw_portal_b64url_encode(random_bytes(32));
        }

        return (string) $_SESSION['_cw_csrf'];
    }
}

if (!function_exists('cw_portal_csrf_field')) {
    function cw_portal_csrf_field(): string
    {
        $t = htmlspecialchars(cw_portal_csrf_token(), ENT_QUOTES, 'UTF-8');

        return '<input type="hidden" name="_cw_csrf" value="' . $t . '">';
    }
}

if (!function_exists('cw_portal_csrf_validate')) {
    function cw_portal_csrf_validate(?string $token = null): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            return false;
        }
        $expected = (string) ($_SESSION['_cw_csrf'] ?? '');
        if ($expected === '') {
            return false;
        }
        if ($token === null) {
            $token = (string) ($_POST['_cw_csrf'] ?? $_SERVER['HTTP_X_CW_CSRF'] ?? '');
        }

        return is_string($token) && $token !== '' && hash_equals($expected, $token);
    }
}

if (!function_exists('cw_portal_send_security_headers')) {
    function cw_portal_send_security_headers(bool $frameDeny = true): void
    {
        if (headers_sent()) {
            return;
        }
        header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex', true);
        header('X-Content-Type-Options: nosniff', true);
        header('Referrer-Policy: no-referrer', false);
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()', false);
        if ($frameDeny) {
            header('X-Frame-Options: DENY', true);
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
        if ($https) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains', false);
        }
    }
}
