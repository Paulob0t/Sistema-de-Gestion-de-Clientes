<?php
/**
 * Bootstrap API pública web ↔ adm
 * CORS se envía ANTES de la BD para que el preflight (OPTIONS) no falle.
 */
require_once dirname(__DIR__, 2) . '/includes/cw_hub_config.php';

function cw_hub_origin_allowed(string $origin): bool
{
    if ($origin === '') {
        return false;
    }
    if (preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?(/|$)#i', $origin)) {
        return true;
    }
    foreach (CW_HUB_ALLOWED_ORIGINS as $allowed) {
        $allowed = rtrim((string) $allowed, '/');
        if ($origin === $allowed || str_starts_with($origin, $allowed . '/')) {
            return true;
        }
    }
    return false;
}

function cw_hub_send_cors_headers(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin && cw_hub_origin_allowed($origin)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
    }
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-CW-Hub-Key');
    header('Access-Control-Max-Age: 86400');
}

/** Respuesta controlada para proxy same-server (include) sin matar el proceso padre. */
class CwHubApiRespond extends Exception
{
    public int $httpCode;
    public string $json;

    public function __construct(string $json, int $httpCode = 200)
    {
        parent::__construct('cw_hub_api_respond');
        $this->json = $json;
        $this->httpCode = $httpCode;
    }
}

function cw_hub_api_exit(int $code = 0): void
{
    if (!empty($GLOBALS['CW_HUB_PROXY_MODE'])) {
        $buf = ob_get_contents();
        if (is_string($buf)) {
            @ob_end_clean();
        } else {
            $buf = '';
        }
        $http = $code > 0 ? $code : (int) (http_response_code() ?: 200);
        throw new CwHubApiRespond($buf, $http > 0 ? $http : 200);
    }
    exit;
}

cw_hub_send_cors_headers();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    cw_hub_api_exit();
}

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_geo.php';
require_once dirname(__DIR__, 2) . '/conn.php';

cw_hub_migrate($conn);

function cw_hub_api_fail(string $msg, int $code = 400): void
{
    cw_hub_send_cors_headers();
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    cw_hub_api_exit($code);
}

function cw_hub_validate_request(): void
{
    $key = $_SERVER['HTTP_X_CW_HUB_KEY'] ?? ($_POST['key'] ?? '');
    if (!hash_equals(CW_HUB_API_KEY, (string) $key)) {
        cw_hub_api_fail('API key inválida', 403);
    }

    // Proxy server-side de sitios públicos independientes (.com / .cl /us)
    $proxySite = strtolower(trim((string) ($_SERVER['HTTP_X_CW_PROXY_SITE'] ?? '')));
    if (in_array($proxySite, ['mx', 'cl', 'us'], true)) {
        return;
    }

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $ok = cw_hub_origin_allowed($origin);

    if (!$ok && $referer !== '') {
        foreach (CW_HUB_ALLOWED_ORIGINS as $allowed) {
            if (str_starts_with($referer, rtrim((string) $allowed, '/'))) {
                $ok = true;
                break;
            }
        }
    }

    if (!$ok && php_sapi_name() !== 'cli') {
        if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
            cw_hub_api_fail('Origen no autorizado', 403);
        }
    }
}

function cw_hub_json_input(): array
{
    if (!empty($GLOBALS['CW_HUB_PROXY_BODY']) && is_string($GLOBALS['CW_HUB_PROXY_BODY'])) {
        $data = json_decode($GLOBALS['CW_HUB_PROXY_BODY'], true);
        if (is_array($data)) {
            return $data;
        }
    }
    $raw = file_get_contents('php://input');
    if ($raw) {
        $data = json_decode($raw, true);
        if (is_array($data)) {
            return $data;
        }
    }
    return $_POST;
}

function cw_hub_s(string $v, int $max = 500): string
{
    return mb_substr(trim(strip_tags((string) $v)), 0, $max);
}

/**
 * ¿Exigir reCAPTCHA en el formulario de leads/WhatsApp?
 * Local (localhost/127.0.0.1) → no. Dominios *.conlineweb.com / *.conlineweb.cl → sí.
 */
function cw_hub_lead_captcha_required(): bool
{
    $candidates = [
        (string) ($_SERVER['HTTP_ORIGIN'] ?? ''),
        (string) ($_SERVER['HTTP_REFERER'] ?? ''),
    ];
    foreach ($candidates as $url) {
        if ($url === '') {
            continue;
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            continue;
        }
        $host = strtolower((string) preg_replace('/:\d+$/', '', $host));
        if (preg_match('/^(localhost|127\.0\.0\.1)$/', $host)) {
            return false;
        }
        if (preg_match('/(^|\.)conlineweb\.(com|cl)$/', $host) && !preg_match('/^adm\./', $host)) {
            return true;
        }
    }

    // Llamadas directas a la API en producción: exigir captcha (anti-spam).
    return function_exists('cw_hub_is_production_host') && cw_hub_is_production_host();
}

/**
 * Secret(s) de reCAPTCHA v2 — deben corresponder a la site key del front.
 *
 * @return list<string>
 */
function cw_hub_recaptcha_secrets(): array
{
    $secrets = [];
    if (defined('CW_HUB_RECAPTCHA_SECRET') && is_string(CW_HUB_RECAPTCHA_SECRET) && CW_HUB_RECAPTCHA_SECRET !== '') {
        $secrets[] = CW_HUB_RECAPTCHA_SECRET;
    }
    // Chile (conlineweb.cl) — site key 6Lc29IYtAAAAAM1twW3jpS74ksE0yKuWebZv-PEj
    $secrets[] = '6Lc29IYtAAAAAHZ-XnrlLHbFCfucpZYkqSXkTcW4';
    // Par principal MX / portal (site key 6LfPDTQmAAAAALvmYsR12ZcGgcvRmK3eLTcKfj9l)
    $secrets[] = '6LfPDTQmAAAAAJXDg4UTYJbGonWOzsOYQ-QR5jP_';
    // Par legacy usado en login del portal
    $secrets[] = '6LccysAdAAAAABx9GvXHFtpORAORoXdjRiI_6gdQ';

    return array_values(array_unique($secrets));
}

/**
 * Token crudo: no usar strip_tags/mb_substr (pueden corromper la respuesta de Google).
 */
function cw_hub_recaptcha_token_from_data(array $data): string
{
    $raw = $data['g-recaptcha-response'] ?? $data['recaptcha'] ?? $data['captcha'] ?? '';
    if (!is_string($raw)) {
        return '';
    }
    $token = trim($raw);
    // Evitar HTML/espacios raros; longitud típica v2 ~500–2000
    if ($token === '' || strlen($token) < 20 || strlen($token) > 4000) {
        return '';
    }
    if (preg_match('/[\s<>]/', $token)) {
        return '';
    }
    return $token;
}

/**
 * @return array{ok:bool,error?:string,codes?:list<string>}
 */
function cw_hub_verify_recaptcha_with_secret(string $secret, string $token): array
{
    $payload = http_build_query([
        'secret' => $secret,
        'response' => $token,
        // No enviamos remoteip: detrás de proxy/CDN suele invalidar tokens válidos
    ]);

    $raw = false;
    if (function_exists('curl_init')) {
        $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 10,
            ],
        ]);
        $raw = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $ctx);
    }

    if ($raw === false || $raw === '') {
        return ['ok' => false, 'error' => 'No se pudo validar el captcha. Intenta de nuevo.', 'codes' => ['network-error']];
    }

    $arr = json_decode((string) $raw, true);
    if (!is_array($arr)) {
        return ['ok' => false, 'error' => 'No se pudo validar el captcha. Intenta de nuevo.', 'codes' => ['bad-json']];
    }

    $codes = [];
    if (!empty($arr['error-codes']) && is_array($arr['error-codes'])) {
        foreach ($arr['error-codes'] as $code) {
            if (is_string($code) && $code !== '') {
                $codes[] = $code;
            }
        }
    }

    if (!empty($arr['success'])) {
        return ['ok' => true, 'codes' => $codes];
    }

    return ['ok' => false, 'error' => 'Captcha inválido o expirado. Márcalo de nuevo.', 'codes' => $codes];
}

/**
 * @return array{ok:bool,error?:string}
 */
function cw_hub_verify_recaptcha(string $token): array
{
    $token = trim($token);
    if ($token === '') {
        return ['ok' => false, 'error' => 'Completa el captcha para continuar'];
    }

    $last = ['ok' => false, 'error' => 'Captcha inválido o expirado. Márcalo de nuevo.', 'codes' => []];
    foreach (cw_hub_recaptcha_secrets() as $secret) {
        $last = cw_hub_verify_recaptcha_with_secret($secret, $token);
        if (!empty($last['ok'])) {
            return ['ok' => true];
        }
        $codes = $last['codes'] ?? [];
        // Si el secreto no corresponde, probar el siguiente; si el token expiró, no seguir
        if (in_array('timeout-or-duplicate', $codes, true) || in_array('invalid-input-response', $codes, true)) {
            break;
        }
    }

    $codes = $last['codes'] ?? [];
    error_log('[CW Hub] reCAPTCHA fail codes=' . implode(',', $codes) . ' token_len=' . strlen($token));

    if (in_array('timeout-or-duplicate', $codes, true)) {
        return ['ok' => false, 'error' => 'El captcha expiró. Márcalo de nuevo e intenta otra vez.'];
    }
    if (in_array('invalid-input-secret', $codes, true) || in_array('missing-input-secret', $codes, true)) {
        return ['ok' => false, 'error' => 'Error de configuración del captcha. Contacta a soporte.'];
    }

    return ['ok' => false, 'error' => $last['error'] ?? 'Captcha inválido o expirado. Márcalo de nuevo.'];
}
