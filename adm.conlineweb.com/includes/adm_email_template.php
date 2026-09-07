<?php

require_once dirname(__DIR__, 2) . '/includes/cw_email_brand.php';

/**
 * @param array{badge?:string,badge_variant?:string,signature?:string|null,signature_team?:string,footer?:string} $opts
 */
function adm_email_message(string $titulo, string $cuerpo, string $despedida = '', array $opts = []): string
{
    return cw_email_message($titulo, $cuerpo, $despedida, array_merge([
        'badge' => 'Admin ConlineWeb',
        'badge_variant' => 'neutral',
    ], $opts));
}

/**
 * Alerta interna al equipo (DNS, tickets admin, webhooks, etc.).
 *
 * @param array{badge?:string,badge_variant?:string,alert_type?:string,alert_html?:string} $opts
 */
function adm_email_alert(string $titulo, string $intro, string $detailHtml, array $opts = []): string
{
    $content = cw_email_p($intro);
    if (!empty($opts['alert_html'])) {
        $content .= cw_email_alert($opts['alert_html'], $opts['alert_type'] ?? 'warning');
    }
    $content .= $detailHtml;

    return cw_email_wrap([
        'title' => $titulo,
        'content' => $content,
        'badge' => $opts['badge'] ?? 'Alerta interna',
        'badge_variant' => $opts['badge_variant'] ?? 'warning',
        'signature' => null,
    ]);
}
