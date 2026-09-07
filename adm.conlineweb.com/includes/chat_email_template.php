<?php

require_once dirname(__DIR__, 2) . '/includes/cw_email_brand.php';

function chat_email_logo_url(): string
{
    return cw_email_logo_url();
}

/**
 * @param array{
 *   titulo: string,
 *   parrafos?: string[],
 *   cta_texto?: string,
 *   cta_url?: string,
 *   alerta_tipo?: 'info'|'warning'|'success'|'danger',
 *   alerta_texto?: string,
 *   pie?: string,
 *   badge?: string
 * } $config
 */
function chat_email_render(array $config): string
{
    $content = '';
    foreach ($config['parrafos'] ?? [] as $p) {
        $content .= cw_email_p($p);
    }

    if (!empty($config['cta_texto']) && !empty($config['cta_url'])) {
        $content .= cw_email_cta($config['cta_url'], $config['cta_texto'], 'primary');
    }

    if (!empty($config['alerta_texto'])) {
        $tipo = $config['alerta_tipo'] ?? 'info';
        if ($tipo === 'warning') {
            $tipo = 'warning';
        } elseif ($tipo === 'success') {
            $tipo = 'success';
        } elseif ($tipo === 'danger') {
            $tipo = 'danger';
        } else {
            $tipo = 'info';
        }
        $content .= cw_email_alert($config['alerta_texto'], $tipo);
    }

    return cw_email_wrap([
        'title' => $config['titulo'] ?? 'ConlineWeb Chat',
        'content' => $content,
        'footer' => $config['pie'] ?? 'Notificación automática del Centro de Atención ConlineWeb.',
        'badge' => $config['badge'] ?? 'Chat en vivo',
        'badge_variant' => 'neutral',
        'signature_team' => 'Centro de Atención ConlineWeb',
    ]);
}
