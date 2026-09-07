<?php
/**
 * Credenciales Twilio centralizadas (WhatsApp).
 *
 * Prioridad:
 * 1) Variables de entorno TWILIO_ACCOUNT_SID / TWILIO_AUTH_TOKEN / TWILIO_FROM
 * 2) Archivo local includes/cw_twilio_secrets.local.php (no versionar)
 * 3) Valores por defecto del proyecto
 */

declare(strict_types=1);

/**
 * @return array{sid:string,token:string,from:string,template_sid:string}
 */
function cw_twilio_config(): array
{
    static $cached = null;
    if (is_array($cached)) {
        return $cached;
    }

    $local = [
        'sid' => '',
        'token' => '',
        'from' => '',
        'template_sid' => '',
    ];
    $localFile = __DIR__ . '/cw_twilio_secrets.local.php';
    if (is_file($localFile)) {
        $loaded = include $localFile;
        if (is_array($loaded)) {
            $local = array_merge($local, $loaded);
        }
    }

    // Cuenta donde vive el número WhatsApp +15557419621 y las plantillas HX…
    $defaultSid = 'ACe545cc9bfdfb41f417c8e1cc34062678';
    $defaultToken = '90ae3786fa0a76fdd946a0ac651d500f';
    $defaultFrom = 'whatsapp:+15557419621';
    $defaultTemplate = 'HXb4f3d05dccbda05a03321376294e1d43';

    $sid = trim((string) (getenv('TWILIO_ACCOUNT_SID') ?: ($local['sid'] ?? '') ?: $defaultSid));
    $token = trim((string) (getenv('TWILIO_AUTH_TOKEN') ?: ($local['token'] ?? '') ?: $defaultToken));
    $from = trim((string) (getenv('TWILIO_FROM') ?: ($local['from'] ?? '') ?: $defaultFrom));
    $template = trim((string) (getenv('TWILIO_TEMPLATE_SID') ?: ($local['template_sid'] ?? '') ?: $defaultTemplate));

    if ($from !== '' && !str_starts_with(strtolower($from), 'whatsapp:')) {
        $from = 'whatsapp:' . $from;
    }

    $cached = [
        'sid' => $sid,
        'token' => $token,
        'from' => $from,
        'template_sid' => $template,
    ];

    return $cached;
}

/** Mensaje claro cuando Twilio responde 401 Authenticate. */
function cw_twilio_auth_error_hint(string $twilioMessage = ''): string
{
    $base = 'Twilio rechazó las credenciales (Authenticate). '
        . 'En Twilio Console → Account → API keys & tokens, copia el Auth Token actual '
        . 'y pégalo en includes/cw_twilio_secrets.local.php o en la variable TWILIO_AUTH_TOKEN.';
    $twilioMessage = trim($twilioMessage);
    if ($twilioMessage === '' || strcasecmp($twilioMessage, 'Authenticate') === 0) {
        return $base;
    }

    return $base . ' Detalle: ' . $twilioMessage;
}
