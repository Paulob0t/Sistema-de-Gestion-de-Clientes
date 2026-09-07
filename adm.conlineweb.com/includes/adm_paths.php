<?php
/**
 * Rutas base del panel admin.
 * Corrige CSS/JS/API cuando el admin está en subcarpeta (analytics, leads, chat, emails…)
 * o en localhost (ej. /proyecto/adm.conlineweb.com/).
 */
function adm_base(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $dir = dirname($scriptName);
    if ($dir === '/' || $dir === '\\' || $dir === '.') {
        $dir = '';
    }

    $base = preg_replace('#/(analytics|leads|cotizaciones|solicitudes|ajax|website|chat|emails|mailing|seguridad)(/.*)?$#', '', $dir);
    if ($base === '/') {
        $base = '';
    }

    return $base;
}

function adm_url(string $path = ''): string
{
    $path = ltrim(str_replace('\\', '/', $path), '/');
    $base = adm_base();

    if ($path === '') {
        return $base === '' ? '/' : $base . '/';
    }

    return ($base === '' ? '' : $base) . '/' . $path;
}

function adm_href(string $path): string
{
    return htmlspecialchars(adm_url($path), ENT_QUOTES, 'UTF-8');
}
