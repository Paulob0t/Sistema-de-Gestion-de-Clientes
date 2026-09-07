<?php
/**
 * Configuración OpenAI — listo para cPanel y desarrollo local.
 *
 * Orden de clave:
 *   secrets.php → variable de entorno → clave por defecto del proyecto
 * Local: openai_config.local.php solo si aún no hay clave (mock opcional).
 *
 * La IA del chat web queda activa siempre que haya clave; si no hay conexión
 * al proveedor, el API usa fallback por reglas.
 */
require_once __DIR__ . '/openai_config.defaults.php';

if (!function_exists('openai_request_host')) {
    function openai_request_host(): string
    {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        return (string) preg_replace('/:\d+$/', '', $host);
    }
}

if (!function_exists('openai_is_production_host')) {
    function openai_is_production_host(): bool
    {
        $host = openai_request_host();
        if ($host === '') {
            return false;
        }

        return (bool) preg_match('/(^|\.)conlineweb\.com$/', $host);
    }
}

if (!function_exists('openai_resolve_env_key')) {
    function openai_resolve_env_key(): string
    {
        $sources = [
            getenv('OPENAI_API_KEY'),
            $_ENV['OPENAI_API_KEY'] ?? null,
            $_SERVER['OPENAI_API_KEY'] ?? null,
            getenv('REDIRECT_OPENAI_API_KEY'),
            $_ENV['REDIRECT_OPENAI_API_KEY'] ?? null,
            $_SERVER['REDIRECT_OPENAI_API_KEY'] ?? null,
        ];

        foreach ($sources as $value) {
            if (is_string($value)) {
                $value = trim($value);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }
}

// secrets.php en cualquier entorno (local + producción)
$secretsFile = __DIR__ . '/openai_config.secrets.php';
if (is_file($secretsFile)) {
    require $secretsFile;
}

if (!defined('OPENAI_API_KEY')) {
    $envKey = openai_resolve_env_key();
    if ($envKey !== '') {
        define('OPENAI_API_KEY', $envKey);
    }
}

// Local: permitir override/mock solo si aún no hay clave
if (!defined('OPENAI_API_KEY') && !openai_is_production_host()) {
    $localFile = __DIR__ . '/openai_config.local.php';
    if (is_file($localFile)) {
        require $localFile;
    }
}

// Clave del proyecto: IA activa por defecto (local y producción)
if (!defined('OPENAI_API_KEY') && defined('CW_OPENAI_API_KEY_DEFAULT') && CW_OPENAI_API_KEY_DEFAULT !== '') {
    define('OPENAI_API_KEY', CW_OPENAI_API_KEY_DEFAULT);
}

if (!defined('OPENAI_API_KEY')) {
    define('OPENAI_API_KEY', '');
}

if (!defined('OPENAI_DEV_MOCK')) {
    define('OPENAI_DEV_MOCK', false);
}

if (!function_exists('openai_dev_mock_enabled')) {
    function openai_dev_mock_enabled(): bool
    {
        return !openai_is_production_host()
            && OPENAI_API_KEY === ''
            && defined('OPENAI_DEV_MOCK')
            && OPENAI_DEV_MOCK;
    }
}

if (!function_exists('openai_key_configured')) {
    function openai_key_configured(): bool
    {
        return defined('OPENAI_API_KEY') && is_string(OPENAI_API_KEY) && trim(OPENAI_API_KEY) !== '';
    }
}
