<?php
/**
 * Rutas web de adjuntos de tickets (solo código PHP; no toca cPanel).
 * Compatible con páginas dentro de /solicitudes/ y en la raíz (tickets_public.php).
 */

if (!function_exists('cw_solicitudes_web_base')) {
    function cw_solicitudes_web_base(): string
    {
        $sn = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if (preg_match('#^(.*?/solicitudes)(?:/|$)#i', $sn, $m)) {
            return rtrim($m[1], '/') . '/';
        }
        $dir = rtrim(str_replace('\\', '/', dirname($sn)), '/');
        if ($dir === '' || $dir === '/' || $dir === '.') {
            return '/solicitudes/';
        }
        return $dir . '/solicitudes/';
    }
}

if (!function_exists('cw_ticket_media_encode_path')) {
    function cw_ticket_media_encode_path(string $path): string
    {
        $parts = explode('/', str_replace('\\', '/', $path));
        $out = [];
        foreach ($parts as $seg) {
            if ($seg === '') {
                $out[] = '';
                continue;
            }
            $out[] = rawurlencode(rawurldecode($seg));
        }
        return implode('/', $out);
    }
}

if (!function_exists('cw_ticket_media_url')) {
    /**
     * Convierte una ruta guardada en BD a URL web usable desde cualquier página del admin.
     */
    function cw_ticket_media_url($path): string
    {
        $path = trim(str_replace('\\', '/', (string) $path));
        if ($path === '') {
            return '';
        }
        if (preg_match('#^(https?:|data:|blob:)#i', $path)) {
            return $path;
        }

        $base = cw_solicitudes_web_base(); // /solicitudes/ o /proyecto/.../solicitudes/
        $clean = ltrim($path, '/');

        // Evitar duplicar "solicitudes/" si ya viene en la ruta
        if (preg_match('#(?:^|/)solicitudes/(uploads/.+)$#i', '/' . $clean, $m)) {
            $rel = $m[1];
        } elseif (strpos($clean, 'uploads/') === 0) {
            $rel = $clean;
        } else {
            $rel = 'uploads/solicitudes/' . $clean;
        }

        return cw_ticket_media_encode_path($base . $rel);
    }
}

if (!function_exists('cw_ticket_media_urls')) {
    function cw_ticket_media_urls($list): array
    {
        if (!is_array($list)) {
            return [];
        }
        $out = [];
        foreach ($list as $item) {
            if (is_array($item)) {
                $item = $item['ruta'] ?? $item['url'] ?? $item['path'] ?? $item['src'] ?? $item['href'] ?? '';
            }
            $u = cw_ticket_media_url($item);
            if ($u !== '' && !in_array($u, $out, true)) {
                $out[] = $u;
            }
        }
        return $out;
    }
}
