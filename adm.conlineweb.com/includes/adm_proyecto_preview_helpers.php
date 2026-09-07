<?php
/**
 * Helpers de markup para vista previa de proyectos en admin DataTables.
 */

if (!function_exists('adm_proyecto_preview_host')) {
    function adm_proyecto_preview_host(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return $url;
        }
        return preg_replace('/^www\./i', '', $host) ?: $host;
    }
}

if (!function_exists('adm_proyecto_preview_cell')) {
    function adm_proyecto_preview_cell(string $url, string $nombre = ''): string
    {
        $url = trim($url);
        if ($url === '') {
            return '<div class="adm-dt-preview"><div class="adm-dt-preview__ph"><span class="adm-dt-preview__empty">Sin URL</span></div></div>';
        }
        $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $safeName = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
        $host = htmlspecialchars(adm_proyecto_preview_host($url), ENT_QUOTES, 'UTF-8');
        return
            '<div class="adm-dt-preview" data-url="' . $safeUrl . '">' .
                '<div class="adm-dt-preview__ph">' .
                    '<i class="fas fa-globe adm-dt-preview__icon" aria-hidden="true"></i>' .
                    '<div class="adm-dt-preview__host" title="' . $safeUrl . '">' . $host . '</div>' .
                    '<div class="adm-dt-preview__actions">' .
                        '<a class="adm-dt-preview__btn adm-dt-preview__btn--web" href="' . $safeUrl . '" target="_blank" rel="noopener">Ver web</a>' .
                        '<button type="button" class="adm-dt-preview__btn adm-dt-preview__load" data-preview-url="' . $safeUrl . '">Ver aquí</button>' .
                        '<button type="button" class="adm-dt-preview__btn btnVistaPreviaProyecto" data-url="' . $safeUrl . '" data-nombre="' . $safeName . '" title="Ampliar"><i class="fas fa-expand"></i></button>' .
                    '</div>' .
                '</div>' .
                '<div class="adm-dt-preview__scaled" hidden></div>' .
            '</div>';
    }
}
