<?php
/**
 * Configuración del Hub Analítico + CRM (adm ↔ conlineweb.com)
 */
if (!function_exists('cw_hub_is_production_host')) {
    function cw_hub_is_production_host(): bool
    {
        $host = strtolower((string) preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            return false;
        }

        return (bool) preg_match('/(^|\.)conlineweb\.com$/', $host);
    }
}

if (!cw_hub_is_production_host() && file_exists(__DIR__ . '/cw_hub_config.local.php')) {
    require_once __DIR__ . '/cw_hub_config.local.php';
}

/** Zona horaria del tráfico web — Ciudad de México (CDMX). */
if (!defined('CW_HUB_TIMEZONE')) {
    define('CW_HUB_TIMEZONE', 'America/Mexico_City');
}
/** BD guarda hora CDMX directamente (lo que ves en MySQL = hora México). */
if (!defined('CW_HUB_DB_TZ')) {
    define('CW_HUB_DB_TZ', '-06:00');
}
if (!defined('CW_HUB_MX_OFFSET')) {
    define('CW_HUB_MX_OFFSET', '-06:00');
}
if (!defined('CW_HUB_TIMEZONE_SET')) {
    date_default_timezone_set(CW_HUB_TIMEZONE);
    define('CW_HUB_TIMEZONE_SET', true);
}

function cw_hub_tz_mx(): DateTimeZone
{
    static $tz = null;
    return $tz ??= new DateTimeZone(CW_HUB_TIMEZONE);
}

function cw_hub_tz_utc(): DateTimeZone
{
    static $tz = null;
    return $tz ??= new DateTimeZone('UTC');
}

/** Timestamp actual en hora CDMX del servidor para guardar en BD (no hora del navegador). */
function cw_hub_now(): string
{
    return (new DateTimeImmutable('now', cw_hub_tz_mx()))->format('Y-m-d H:i:s');
}

/**
 * Hora de la visita: toma la hora local del navegador (client_tz) y la guarda en CDMX.
 */
function cw_hub_resolve_viewed_at(array $data): string
{
    $raw = trim((string) ($data['viewed_at'] ?? $data['client_now'] ?? ''));
    $tzId = trim((string) ($data['client_tz'] ?? ''));

    if ($raw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}$/', $raw)) {
        $raw = str_replace('T', ' ', $raw);
        if ($tzId !== '') {
            try {
                return (new DateTimeImmutable($raw, new DateTimeZone($tzId)))
                    ->setTimezone(cw_hub_tz_mx())
                    ->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                // continúa con offset o fallback
            }
        }

        return $raw;
    }

    if ($raw !== '') {
        try {
            $dt = new DateTimeImmutable($raw);
            if ($tzId !== '') {
                return $dt->setTimezone(new DateTimeZone($tzId))
                    ->setTimezone(cw_hub_tz_mx())
                    ->format('Y-m-d H:i:s');
            }

            return $dt->setTimezone(cw_hub_tz_mx())->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            // fallback abajo
        }
    }

    $offsetMin = isset($data['client_utc_offset']) ? (int) $data['client_utc_offset'] : null;
    if ($offsetMin !== null) {
        try {
            $utc = new DateTimeImmutable('now', cw_hub_tz_utc());
            $local = $utc->modify(sprintf('%+d minutes', -$offsetMin));

            return $local->setTimezone(cw_hub_tz_mx())->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            // fallback abajo
        }
    }

    return cw_hub_now();
}

/** ISO con offset CDMX para mostrar en vivo en el dashboard. */
function cw_hub_now_iso(): string
{
    return (new DateTimeImmutable('now', cw_hub_tz_mx()))->format('c');
}

/** Formatea datetime almacenado (ya en CDMX). */
function cw_hub_format_datetime(string $dt, string $format = 'd/m/Y H:i'): string
{
    if ($dt === '' || $dt === '0000-00-00 00:00:00') {
        return '—';
    }
    try {
        return (new DateTimeImmutable($dt, cw_hub_tz_mx()))->format($format);
    } catch (Exception $e) {
        return $dt;
    }
}

/** Convierte límite CDMX → UTC para consultas SQL. */
function cw_hub_mx_to_utc(string $mxDt): string
{
    try {
        return (new DateTimeImmutable($mxDt, cw_hub_tz_mx()))
            ->setTimezone(cw_hub_tz_utc())
            ->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return $mxDt;
    }
}

/** Expresión SQL: columna UTC → hora CDMX. */
function cw_hub_sql_to_mx(string $column): string
{
    return "CONVERT_TZ({$column}, '" . CW_HUB_DB_TZ . "', '" . CW_HUB_MX_OFFSET . "')";
}

function cw_hub_db_timezone(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    @$conn->query("SET time_zone = '" . CW_HUB_MX_OFFSET . "'");
}

/**
 * Rango del periodo en hora CDMX (coincide con lo guardado en BD).
 */
function cw_hub_period_dates(string $period, ?string $from = null, ?string $to = null): array
{
    $mx = cw_hub_tz_mx();
    $now = new DateTimeImmutable('now', $mx);

    switch ($period) {
        case 'today':
            $start = $now->setTime(0, 0, 0);
            $end = $now->setTime(23, 59, 59);
            break;
        case '7d':
            $start = $now->modify('-6 days')->setTime(0, 0, 0);
            $end = $now->setTime(23, 59, 59);
            break;
        case 'week':
            $start = $now->modify('monday this week')->setTime(0, 0, 0);
            $end = $now->setTime(23, 59, 59);
            break;
        case '30d':
            $start = $now->modify('-29 days')->setTime(0, 0, 0);
            $end = $now->setTime(23, 59, 59);
            break;
        case '90d':
            $start = $now->modify('-89 days')->setTime(0, 0, 0);
            $end = $now->setTime(23, 59, 59);
            break;
        case 'month':
            $start = $now->modify('first day of this month')->setTime(0, 0, 0);
            $end = $now->modify('last day of this month')->setTime(23, 59, 59);
            break;
        case 'quarter':
            $m = (int) $now->format('n');
            $qStart = (int) (floor(($m - 1) / 3) * 3 + 1);
            $start = $now->setDate((int) $now->format('Y'), $qStart, 1)->setTime(0, 0, 0);
            $qEndMonth = $qStart + 2;
            $end = $now->setDate((int) $now->format('Y'), $qEndMonth, 1)
                ->modify('last day of this month')
                ->setTime(23, 59, 59);
            break;
        case 'year':
            $start = $now->setDate((int) $now->format('Y'), 1, 1)->setTime(0, 0, 0);
            $end = $now->setDate((int) $now->format('Y'), 12, 31)->setTime(23, 59, 59);
            break;
        case 'custom':
            if ($from && $to) {
                $start = new DateTimeImmutable($from . ' 00:00:00', $mx);
                $end = new DateTimeImmutable($to . ' 23:59:59', $mx);
                break;
            }
            // fallthrough
        default:
            $start = $now->modify('-29 days')->setTime(0, 0, 0);
            $end = $now->setTime(23, 59, 59);
    }

    return [
        $start->format('Y-m-d H:i:s'),
        $end->format('Y-m-d H:i:s'),
    ];
}

/** Rango del periodo en CDMX (solo para etiquetas en UI). */
function cw_hub_period_dates_mx(string $period, ?string $from = null, ?string $to = null): array
{
    return cw_hub_period_dates($period, $from, $to);
}

if (!defined('CW_HUB_API_KEY')) {
    define('CW_HUB_API_KEY', getenv('CW_HUB_API_KEY') ?: ($_ENV['CW_HUB_API_KEY'] ?? 'cwhub_k8m2p9x4v7n1q5w3r6t0y2z8'));
}

if (!defined('CW_HUB_WHATSAPP')) {
    define('CW_HUB_WHATSAPP', getenv('CW_HUB_WHATSAPP') ?: ($_ENV['CW_HUB_WHATSAPP'] ?? '5214771181285'));
}

/** reCAPTCHA v2 — misma site key que conlineweb.com / portal cliente */
if (!defined('CW_HUB_RECAPTCHA_SITE_KEY')) {
    define('CW_HUB_RECAPTCHA_SITE_KEY', getenv('CW_HUB_RECAPTCHA_SITE_KEY') ?: ($_ENV['CW_HUB_RECAPTCHA_SITE_KEY'] ?? '6LfPDTQmAAAAALvmYsR12ZcGgcvRmK3eLTcKfj9l'));
}
if (!defined('CW_HUB_RECAPTCHA_SECRET')) {
    // Par de la site key anterior (prioridad en verificación del API)
    define('CW_HUB_RECAPTCHA_SECRET', getenv('CW_HUB_RECAPTCHA_SECRET') ?: ($_ENV['CW_HUB_RECAPTCHA_SECRET'] ?? '6LfPDTQmAAAAAJXDg4UTYJbGonWOzsOYQ-QR5jP_'));
}

if (!defined('CW_HUB_ALLOWED_ORIGINS')) {
    define('CW_HUB_ALLOWED_ORIGINS', [
        'https://conlineweb.com',
        'https://www.conlineweb.com',
        'https://conlineweb.cl',
        'https://www.conlineweb.cl',
        // Respaldo si el proxy enviara http por error de detección SSL
        'http://conlineweb.cl',
        'http://www.conlineweb.cl',
        'http://localhost',
        'https://localhost',
        'http://127.0.0.1',
        'https://127.0.0.1',
        'http://localhost/proyecto/conlineweb.com',
        'https://localhost/proyecto/conlineweb.com',
        'http://localhost/proyecto/conlineweb.cl',
        'https://localhost/proyecto/conlineweb.cl',
        'http://localhost/sistema/conlineweb.com',
        'https://localhost/sistema/conlineweb.com',
        'http://localhost/sistema/conlineweb.cl',
        'https://localhost/sistema/conlineweb.cl',
    ]);
}

if (!defined('CW_HUB_SERVICIOS')) {
    define('CW_HUB_SERVICIOS', [
        'desarrollo_web'          => 'Desarrollo web',
        'pagina_web'              => 'Páginas web',
        'seo'                     => 'SEO',
        'seo_local'               => 'SEO Local',
        'geo'                     => 'Posicionamiento GEO',
        'inteligencia_artificial' => 'Inteligencia Artificial',
        'automatizacion'          => 'Automatización',
        'ecommerce'               => 'E-commerce',
        'software'                => 'Desarrollo de software',
        'hosting'                 => 'Hosting',
        'otro'                    => 'Soluciones digitales',
    ]);
}

if (!defined('CW_HUB_PIPELINE')) {
    define('CW_HUB_PIPELINE', [
        'nuevo'       => 'Nuevo',
        'lead'        => 'Lead',
        'calificado'  => 'Calificado',
        'seguimiento' => 'Seguimiento',
        'propuesta'   => 'Propuesta enviada',
        'cierre'      => 'Cierre',
        'cerrado'     => 'Cerrado',
        'perdido'     => 'Perdido',
    ]);
}

/** Pipeline simplificado para leads del sitio web (modal WhatsApp). */
if (!defined('CW_HUB_WEBSITE_PIPELINE')) {
    define('CW_HUB_WEBSITE_PIPELINE', [
        'lead'       => 'Lead',
        'calificado' => 'Calificado',
        'cierre'     => 'Cierre',
    ]);
}

if (!defined('CW_HUB_N8N_WEBHOOK')) {
    define('CW_HUB_N8N_WEBHOOK', '');
}

if (!defined('CW_HUB_AUTO_WA_WELCOME')) {
    define('CW_HUB_AUTO_WA_WELCOME', false);
}

if (!defined('CW_HUB_ALERT_EMAIL')) {
    define('CW_HUB_ALERT_EMAIL', 'servicios@conlineweb.com');
}

/** Solo contar visitas del sitio público en producción (excluye localhost/pruebas). */
if (!defined('CW_HUB_ANALYTICS_PRODUCTION_ONLY')) {
    define('CW_HUB_ANALYTICS_PRODUCTION_ONLY', true);
}

if (!defined('CW_HUB_TRACKING_HOSTS')) {
    define('CW_HUB_TRACKING_HOSTS', [
        'conlineweb.com',
        'www.conlineweb.com',
        'conlineweb.cl',
        'www.conlineweb.cl',
    ]);
}

if (!defined('CW_HUB_CRON_SECRET')) {
    define('CW_HUB_CRON_SECRET', 'cw_hub_cron_' . substr(CW_HUB_API_KEY, -8));
}

if (!defined('CW_HUB_TWILIO_TEMPLATE_SID')) {
    define('CW_HUB_TWILIO_TEMPLATE_SID', 'HXb4f3d05dccbda05a03321376294e1d43');
}
