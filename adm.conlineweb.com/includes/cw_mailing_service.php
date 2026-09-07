<?php
/**
 * Servicio Mailing: plantillas, render, envío SMTP y tracking.
 */
declare(strict_types=1);

require_once __DIR__ . '/cw_mailing_migrate.php';
require_once __DIR__ . '/cw_email_brand.php';

function cw_mailing_public_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'adm.conlineweb.com');
    $base = function_exists('adm_url') ? rtrim(adm_url(''), '/') : '';
    if ($base === '' || $base === '/') {
        return $scheme . '://' . $host;
    }
    if (str_starts_with($base, 'http://') || str_starts_with($base, 'https://')) {
        return rtrim($base, '/');
    }

    return $scheme . '://' . $host . $base;
}

function cw_mailing_track_url(string $token, string $event, string $targetUrl = ''): string
{
    $q = [
        'e' => $event,
        't' => $token,
    ];
    if ($targetUrl !== '') {
        $q['u'] = rtrim(strtr(base64_encode($targetUrl), '+/', '-_'), '=');
    }

    return cw_mailing_public_base_url() . '/mailing/track.php?' . http_build_query($q);
}

function cw_mailing_uploads_dir(): string
{
    $dir = dirname(__DIR__) . '/storage/mailing/uploads';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    return $dir;
}

function cw_mailing_media_url(string $filename): string
{
    $filename = basename($filename);

    return cw_mailing_public_base_url() . '/mailing/media.php?f=' . rawurlencode($filename);
}

/** @return array<string,string> */
function cw_mailing_vars(array $recipient): array
{
    $nombre = trim((string) ($recipient['name'] ?? 'Cliente'));
    if ($nombre === '') {
        $nombre = 'Cliente';
    }
    $empresa = trim((string) ($recipient['company'] ?? ''));
    if ($empresa === '') {
        $empresa = 'tu empresa';
    }
    $correo = trim((string) ($recipient['email'] ?? ''));
    $telefono = trim((string) ($recipient['phone'] ?? ''));

    return [
        '{nombre}' => $nombre,
        '{empresa}' => $empresa,
        '{correo}' => $correo,
        '{telefono}' => $telefono,
        '{name}' => $nombre,
        '{company}' => $empresa,
        '{email}' => $correo,
    ];
}

function cw_mailing_apply_vars(string $text, array $vars): string
{
    return strtr($text, $vars);
}

/**
 * @param array<string,mixed> $tpl
 * @param array<string,mixed> $recipient
 */
function cw_mailing_parse_design_meta(string $body): array
{
    $meta = [
        'layout' => 'corporativo',
        'kicker' => '',
        'headline' => '',
        'lead' => '',
        'cta_variant' => 'primary',
        'cta_hint' => '',
        'blocks' => [],
        'body' => $body,
    ];
    /* Greedy hasta --> para soportar blocks anidados en el JSON. */
    if (preg_match('/^<!--cwml:(\{.*\})-->/s', $body, $m)) {
        $decoded = json_decode($m[1], true);
        if (is_array($decoded)) {
            $meta['layout'] = (string) ($decoded['layout'] ?? 'corporativo');
            $meta['kicker'] = (string) ($decoded['kicker'] ?? '');
            $meta['headline'] = (string) ($decoded['headline'] ?? '');
            $meta['lead'] = (string) ($decoded['lead'] ?? '');
            $meta['cta_variant'] = (string) ($decoded['cta_variant'] ?? 'primary');
            $meta['cta_hint'] = (string) ($decoded['cta_hint'] ?? '');
            if (!empty($decoded['blocks']) && is_array($decoded['blocks'])) {
                $meta['blocks'] = array_values($decoded['blocks']);
            }
        }
        $meta['body'] = trim(substr($body, strlen($m[0])));
    }

    return $meta;
}

/** Quita el comentario <!--cwml:...--> del inicio (soporta JSON con blocks). */
function cw_mailing_strip_design_meta_comment(string $html): string
{
    $stripped = preg_replace('/^<!--cwml:\{.*\}-->/s', '', $html, 1);

    return is_string($stripped) ? ltrim($stripped) : $html;
}

/**
 * CTA compacto 50/50: texto referencial | botón pequeño.
 */
function cw_mailing_cta_pair(string $text, string $url, string $label, string $variant = 'primary'): string
{
    $text = trim($text);
    $label = trim($label);
    $url = trim($url);
    if ($url === '' || $label === '') {
        return '';
    }
    if ($text === '') {
        $text = '¿Seguimos? Te proponemos el siguiente paso.';
    }
    $bg = '#000147';
    $color = '#ffffff';
    $border = '#000147';
    if ($variant === 'whatsapp') {
        $bg = '#128c7e';
        $border = '#128c7e';
    }

    $bg = $variant === 'whatsapp' ? '#128c7e' : '#000147';
    $border = $bg;

    return '<table class="cw-cta-pair" role="presentation" width="100%" cellpadding="0" cellspacing="0" data-cw-cta="1" style="width:100%;margin:4px 0 0;border-collapse:collapse;">'
        . '<tr><td style="padding:0 0 12px;font-size:14px;line-height:1.5;color:#111111;text-align:center;font-family:' . cw_mailing_font_stack() . ';">'
        . cw_email_h($text) . '</td></tr>'
        . '<tr><td align="center" style="padding:0;">'
        . '<a href="' . cw_email_h($url) . '" style="display:block;width:100%;box-sizing:border-box;padding:16px 22px;border-radius:12px;background:' . $bg . ';color:#ffffff;'
        . 'font-family:' . cw_mailing_font_stack() . ';font-size:15px;font-weight:700;line-height:1.25;text-align:center;text-decoration:none;border:1px solid ' . $border . ';">'
        . cw_email_h($label) . '</a></td></tr></table>';
}

function cw_mailing_style_body_html(string $body): string
{
    /* Si ya trae cards AI / estilos, no reescribir. */
    if (str_contains($body, 'border-radius:12px') || str_contains($body, 'cw-body-panel')) {
        return $body;
    }

    if (!str_contains($body, 'style=')) {
        $body = preg_replace(
            '#<p>(.*?)</p>#is',
            '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:0 0 14px;border-collapse:separate;border-spacing:0;background:#f5f6f8;border:1px solid #d0d4db;border-radius:12px;"><tr><td style="padding:18px;"><p style="margin:0;font-size:15px;line-height:1.65;color:#111111;">$1</p></td></tr></table>',
            $body
        ) ?? $body;
        $body = preg_replace(
            '#<h3>(.*?)</h3>#is',
            '<h3 style="margin:8px 0 10px;font-size:16px;line-height:1.35;color:#000147;font-weight:800;padding-bottom:10px;border-bottom:1px solid #e5e7eb;">$1</h3>',
            $body
        ) ?? $body;
    } else {
        /* Párrafos sueltos con style: envolver en card si aún no están en contenedor. */
        $body = preg_replace_callback(
            '#<p\s+style="([^"]*)">(.*?)</p>#is',
            static function (array $m): string {
                $style = $m[1];
                $inner = $m[2];
                if (str_contains($style, 'border-radius') || str_contains($style, 'padding:18px')) {
                    return $m[0];
                }

                return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:0 0 14px;border-collapse:separate;border-spacing:0;background:#f5f6f8;border:1px solid #d0d4db;border-radius:12px;"><tr><td style="padding:18px;"><p style="margin:0;font-size:15px;line-height:1.65;color:#111111;">'
                    . $inner . '</p></td></tr></table>';
            },
            $body
        ) ?? $body;
    }

    return $body;
}

function cw_mailing_font_stack(): string
{
    return "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Helvetica,Arial,sans-serif";
}

function cw_mailing_normalize_colors(string $html): string
{
    if ($html === '') {
        return $html;
    }

    $replaced = preg_replace_callback(
        '/style=(["\'])(.*?)\1/is',
        static function (array $m): string {
            $q = $m[1];
            $s = $m[2];
            $isSolidBtn = (bool) preg_match('/background(?:-color)?\s*:\s*(#000147|#111111|#000000|#128c7e|#25d366|#25D366)/i', $s);
            $isLightBg = (bool) preg_match('/background(?:-color)?\s*:\s*(#fff\b|#ffffff|#f1f1f1|#f7f7f7|#f8fafc|#fafafa|#eeeeee|#e6e6e6)/i', $s);

            $pairs = [
                '#0a0a0f' => '#f7f7f7',
                '#0a0a12' => '#f7f7f7',
                '#0b0b5c' => '#000147',
                '#1f1f2a' => '#e6e6e6',
                '#00f3ff' => '#000147',
                '#b967ff' => '#000147',
                '#00ff9d' => '#000147',
                '#f8fafc' => '#ffffff',
                '#f1f5f9' => '#ffffff',
                '#e2e8f0' => '#e6e6e6',
                '#0f172a' => '#000147',
                '#334155' => '#111111',
                '#475569' => '#111111',
                '#64748b' => '#333333',
                '#6b6b6b' => '#333333',
                '#555555' => '#333333',
                '#333333' => '#333333',
                '#94a3b8' => '#333333',
            ];
            $s = str_ireplace(array_keys($pairs), array_values($pairs), $s);

            if (!$isSolidBtn) {
                $s = preg_replace('/(color\s*:\s*)#ffffff\b/i', '$1#1a1a1a', $s) ?? $s;
                $s = preg_replace('/(color\s*:\s*)#e2e8f0\b/i', '$1#1a1a1a', $s) ?? $s;
                $s = preg_replace('/(color\s*:\s*)#f1f1f1\b/i', '$1#1a1a1a', $s) ?? $s;
                $s = preg_replace('/(color\s*:\s*)#f7f7f7\b/i', '$1#1a1a1a', $s) ?? $s;
                $s = preg_replace('/(color\s*:\s*)#eeeeee\b/i', '$1#1a1a1a', $s) ?? $s;
                $s = preg_replace('/(color\s*:\s*)#cbd5e1\b/i', '$1#333333', $s) ?? $s;
            }
            /* Fondo claro: títulos y textos nunca en blanco ni gris muy claro. */
            if ($isLightBg) {
                $s = preg_replace('/(color\s*:\s*)#ffffff\b/i', '$1#000147', $s) ?? $s;
                $s = preg_replace('/(color\s*:\s*)#f8fafc\b/i', '$1#000147', $s) ?? $s;
                $s = preg_replace('/(color\s*:\s*)#e2e8f0\b/i', '$1#333333', $s) ?? $s;
                $s = preg_replace('/(color\s*:\s*)#94a3b8\b/i', '$1#333333', $s) ?? $s;
            }

            return 'style=' . $q . $s . $q;
        },
        $html
    );

    return is_string($replaced) ? $replaced : $html;
}

function cw_mailing_wrap_html(array $opts): string
{
    $title = (string) ($opts['title'] ?? 'ConlineWeb');
    $kicker = (string) ($opts['kicker'] ?? 'ConlineWeb');
    $subtitle = trim((string) ($opts['subtitle'] ?? ''));
    $content = cw_mailing_normalize_colors((string) ($opts['content'] ?? ''));
    $bannerCta = cw_mailing_normalize_colors(trim((string) ($opts['banner_cta'] ?? '')));
    $footerCta = cw_mailing_normalize_colors(trim((string) ($opts['footer_cta'] ?? '')));
    $hero = trim((string) ($opts['hero'] ?? ''));
    $footer = (string) ($opts['footer'] ?? 'ConlineWeb · León, Guanajuato · WhatsApp 477 118 1285');
    $logo = function_exists('cw_email_logo_white_url')
        ? cw_email_logo_white_url()
        : 'https://conlineweb.com/assets/images/logo/c-online-Logo-blanco.png';
    $subHtml = $subtitle !== ''
        ? '<p style="margin:0 0 20px;font-size:16px;line-height:1.6;color:#111111;font-family:' . cw_mailing_font_stack() . ';">' . cw_email_h($subtitle) . '</p>'
        : '';

    $font = cw_mailing_font_stack();
    $css = '<style type="text/css">'
        . 'html,body,table,td,p,a,h1,h2,h3,div,span{font-family:' . $font . ' !important;}'
        . 'html,body{margin:0!important;padding:0!important;width:100%!important;-webkit-text-size-adjust:100%;}'
        . 'img{max-width:100%!important;height:auto!important;border:0;}'
        . '.cw-body h1,.cw-body h2,.cw-body h3{color:#000147!important;margin:0 0 12px!important;line-height:1.35!important;}'
        . '.cw-body p,.cw-body li{color:#111111;}'
        . '.cw-body-panel{background:#ffffff!important;}'
        // Columnas horizontales en desktop; apilar solo en móvil (&lt;480px).
        // Preview PC (~600px) queda por encima del corte y muestra 50/50.
        . '.cw-col-2{width:50%!important;max-width:50%!important;vertical-align:top!important;box-sizing:border-box!important;}'
        . '@media only screen and (max-width:480px){'
        . '.cw-outer{padding:12px 0!important;}'
        . '.cw-card{width:100%!important;max-width:100%!important;border-radius:12px!important;}'
        . '.cw-pad{padding-left:16px!important;padding-right:16px!important;}'
        . '.cw-h1{font-size:20px!important;line-height:1.32!important;}'
        . '.cw-logo{width:150px!important;height:auto!important;}'
        . '.cw-col,.cw-col-2,.cw-stack{display:block!important;width:100%!important;max-width:100%!important;box-sizing:border-box!important;padding-left:0!important;padding-right:0!important;padding-bottom:10px!important;}'
        . '.cw-cta-pair .cw-stack,.cw-cta-pair .cw-col,.cw-cta-pair .cw-col-2{text-align:left!important;}'
        . '.cw-cta-pair a{display:inline-block!important;width:auto!important;}'
        . '}'
        . '</style>';

    return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="x-apple-disable-message-reformatting">'
        . '<title>' . cw_email_h($title) . '</title>' . $css . '</head>'
        . '<body style="margin:0;padding:0;background:#f1f1f1;font-family:' . $font . ';width:100%;">'
        . '<table class="cw-outer" role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f1f1;padding:28px 12px;width:100%;">'
        . '<tr><td align="center" style="width:100%;">'
        . '<table class="cw-card" role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;max-width:560px;background:#ffffff;border-radius:18px;overflow:hidden;border:1px solid #e6e6e6;">'
        . '<tr><td class="cw-pad" align="center" style="padding:28px 24px;background:#000147;text-align:center;font-family:' . $font . ';">'
        . '<img class="cw-logo" src="' . cw_email_h($logo) . '" alt="ConlineWeb" width="188" style="display:block;border:0;width:188px;height:auto;margin:0 auto;">'
        . '</td></tr>'
        . '<tr><td class="cw-pad cw-body" style="padding:28px 28px 8px;background:#ffffff;color:#111111;font-family:' . $font . ';">'
        . ($kicker !== ''
            ? '<div style="margin:0 0 10px;font-size:11px;line-height:1.3;font-weight:700;color:#000147;letter-spacing:.12em;text-transform:uppercase;font-family:' . $font . ';">'
              . cw_email_h($kicker) . '</div>'
            : '')
        . '<h1 class="cw-h1" style="margin:0 0 12px;font-size:26px;line-height:1.28;color:#000147;font-weight:700;font-family:' . $font . ';">' . cw_email_h($title) . '</h1>'
        . $subHtml
        . ($hero !== ''
            ? '<div style="margin:0 0 8px;border-radius:14px;overflow:hidden;">'
              . '<img src="' . cw_email_h($hero) . '" alt="" width="504" style="display:block;width:100%;height:auto;border:0;border-radius:14px;">'
              . '</div>'
            : '')
        . '</td></tr>'
        . '<tr><td class="cw-pad cw-body cw-body-panel" style="padding:16px 28px 8px;background:#ffffff;color:#111111;font-family:' . $font . ';">'
        . $content
        . '</td></tr>'
        . ($bannerCta !== ''
            ? '<tr><td class="cw-pad cw-body-panel" style="padding:0 28px 16px;background:#ffffff;">' . $bannerCta . '</td></tr>'
            : '')
        . ($footerCta !== ''
            ? '<tr><td class="cw-pad cw-body-panel" style="padding:4px 28px 28px;background:#ffffff;">' . $footerCta . '</td></tr>'
            : '<tr><td style="height:16px;background:#ffffff;font-size:0;line-height:0;">&nbsp;</td></tr>')
        . '<tr><td class="cw-pad" align="center" style="padding:22px 24px 24px;background:#000147;text-align:center;font-family:' . $font . ';">'
        . '<img class="cw-logo" src="' . cw_email_h($logo) . '" alt="ConlineWeb" width="120" style="display:block;border:0;width:120px;height:auto;margin:0 auto 12px;">'
        . '<p style="margin:0 0 8px;font-size:12px;line-height:1.55;color:#ffffff;font-family:' . $font . ';">' . cw_email_h($footer) . '</p>'
        . '<p style="margin:0;font-size:12px;line-height:1.5;font-family:' . $font . ';">'
        . '<a href="https://conlineweb.com/" style="color:#ffffff;font-weight:700;text-decoration:none;font-family:' . $font . ';">conlineweb.com</a>'
        . '</p>'
        . '<!--CW_UNSUB-->'
        . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

function cw_mailing_build_html(array $tpl, array $recipient, string $token, bool $withTracking = true): string
{
    $vars = cw_mailing_vars($recipient);
    $parsed = cw_mailing_parse_design_meta((string) ($tpl['body_html'] ?? ''));
    $title = cw_mailing_apply_vars(
        $parsed['headline'] !== '' ? $parsed['headline'] : (string) ($tpl['title'] ?? 'ConlineWeb'),
        $vars
    );
    $kicker = cw_mailing_apply_vars(
        $parsed['kicker'] !== '' ? $parsed['kicker'] : (string) ($tpl['theme'] ?? 'ConlineWeb'),
        $vars
    );

    /*
     * Si el borrador trae blocks en el meta, se re-maqueta SIEMPRE con el renderer actual.
     * Así los cambios de diseño (líneas, cards, WhatsApp/Google) se ven en preview/envío
     * sin tener que volver a llamar a la IA.
     */
    $bodySource = (string) ($parsed['body'] ?? '');
    $blocks = is_array($parsed['blocks'] ?? null) ? $parsed['blocks'] : [];
    if ($blocks !== []) {
        $aiFile = dirname(__DIR__) . '/includes/cw_mailing_ai.php';
        if (!function_exists('cw_mailing_ai_compose_blocks') && is_file($aiFile)) {
            require_once $aiFile;
        }
        if (function_exists('cw_mailing_ai_compose_blocks') && function_exists('cw_mailing_ai_lock_stable_blocks')) {
            $fresh = cw_mailing_ai_compose_blocks(
                cw_mailing_ai_lock_stable_blocks($blocks),
                $tpl
            );
            if (trim($fresh) !== '') {
                $bodySource = $fresh;
            }
        }
    }

    $body = cw_mailing_apply_vars($bodySource, $vars);
    $body = cw_mailing_style_body_html($body);
    $ctaLabel = trim(cw_mailing_apply_vars((string) ($tpl['cta_label'] ?? ''), $vars));
    $ctaUrl = trim(cw_mailing_apply_vars((string) ($tpl['cta_url'] ?? ''), $vars));
    $imageUrl = trim((string) ($tpl['image_url'] ?? ''));
    $preheader = trim(cw_mailing_apply_vars((string) ($tpl['preheader'] ?? ''), $vars));
    $ctaVariant = $parsed['cta_variant'] === 'whatsapp' ? 'whatsapp' : 'primary';
    $ctaHint = trim(cw_mailing_apply_vars((string) ($parsed['cta_hint'] ?? $tpl['cta_hint'] ?? ''), $vars));
    if ($ctaHint === '') {
        $ctaHint = '¿Seguimos? Te proponemos el siguiente paso.';
    }

    $content = '';
    if ($preheader !== '') {
        $content .= '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">'
            . cw_email_h($preheader) . '</div>';
    }
    $content .= $body;

    $bannerCta = '';
    if (preg_match_all('#<!--cw-banner-cta-->(.*?)<!--/cw-banner-cta-->#s', $content, $bannerHits)) {
        $bannerCta = implode('', $bannerHits[1]);
        $content = (string) preg_replace('#<!--cw-banner-cta-->.*?<!--/cw-banner-cta-->#s', '', $content);
    }

    $footerCta = '';
    if ($ctaLabel !== '' && $ctaUrl !== '') {
        $footerCta = cw_mailing_cta_pair($ctaHint, $ctaUrl, $ctaLabel, $ctaVariant);
    }

    $subtitle = cw_mailing_apply_vars((string) ($parsed['lead'] ?? ''), $vars);
    $html = cw_mailing_wrap_html([
        'title' => $title,
        'kicker' => $kicker,
        'subtitle' => $subtitle,
        'banner_cta' => $bannerCta,
        'footer_cta' => $footerCta,
        'content' => $content,
        'hero' => $imageUrl,
        'footer' => 'León, Guanajuato · WhatsApp 477 118 1285',
    ]);

    if ($withTracking && $token !== '' && $token !== 'preview') {
        $html = cw_mailing_inject_tracking($html, $token);
    } else {
        /* Preview: misma pieza visual que el envío (baja + hueco del pixel), sin reescribir links. */
        $html = cw_mailing_inject_preview_parity($html);
    }

    return $html;
}

/**
 * Misma estructura visual del pie de baja que en el envío real (para que preview ≡ enviado).
 */
function cw_mailing_inject_preview_parity(string $html): string
{
    $pixel = '<img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0;">';
    $unsubHtml = '<p style="margin:10px 0 0;font-size:11px;line-height:1.5;color:#f2f2f2;">'
        . 'Si prefieres no recibir este tipo de mensajes, '
        . '<a href="#" style="color:#ffffff;text-decoration:underline;" onclick="return false;">cancela la suscripción</a>.'
        . '</p>' . $pixel;

    if (str_contains($html, '<!--CW_UNSUB-->')) {
        return str_replace('<!--CW_UNSUB-->', $unsubHtml, $html);
    }
    if (stripos($html, '</body>') !== false) {
        return (string) str_ireplace('</body>', $unsubHtml . '</body>', $html);
    }

    return $html . $unsubHtml;
}

function cw_mailing_inject_tracking(string $html, string $token): string
{
    $html = preg_replace_callback(
        '/<a\s+([^>]*?)href=(["\'])(https?:\/\/[^"\']+)\2([^>]*)>/i',
        static function (array $m) use ($token): string {
            $url = html_entity_decode($m[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            // No reescribir el propio track / unsub
            if (str_contains($url, '/mailing/track.php')) {
                return $m[0];
            }
            $tracked = cw_mailing_track_url($token, 'click', $url);

            return '<a ' . $m[1] . 'href=' . $m[2] . cw_email_h($tracked) . $m[2] . $m[4] . '>';
        },
        $html
    ) ?? $html;

    $openPixel = '<img src="' . cw_email_h(cw_mailing_track_url($token, 'open'))
        . '" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0;">';
    $unsub = cw_mailing_track_url($token, 'unsub');
    $unsubHtml = '<p style="margin:10px 0 0;font-size:11px;line-height:1.5;color:#f2f2f2;">'
        . 'Si prefieres no recibir este tipo de mensajes, '
        . '<a href="' . cw_email_h($unsub) . '" style="color:#ffffff;text-decoration:underline;">cancela la suscripción</a>.'
        . '</p>' . $openPixel;

    if (str_contains($html, '<!--CW_UNSUB-->')) {
        $html = str_replace('<!--CW_UNSUB-->', $unsubHtml, $html);
    } elseif (stripos($html, '</body>') !== false) {
        $html = str_ireplace('</body>', $unsubHtml . '</body>', $html);
    } else {
        $html .= $unsubHtml;
    }

    return $html;
}

function cw_mailing_new_token(): string
{
    return bin2hex(random_bytes(20));
}

/** @return array<string,mixed>|null */
function cw_mailing_get_template(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare('SELECT * FROM cw_mailing_templates WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    return $row ?: null;
}

/** @return list<array<string,mixed>> */
function cw_mailing_list_templates(mysqli $conn, bool $onlyActive = false): array
{
    $sql = 'SELECT * FROM cw_mailing_templates';
    if ($onlyActive) {
        $sql .= ' WHERE active = 1';
    }
    $sql .= ' ORDER BY updated_at DESC, id DESC';
    $res = $conn->query($sql);
    if (!$res) {
        return [];
    }
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $out[] = $row;
    }

    return $out;
}

/**
 * @param array<string,mixed> $data
 * @return array{ok:bool,id?:int,error?:string}
 */
function cw_mailing_make_slug(string $title): string
{
    $slug = trim($title);
    if (function_exists('iconv')) {
        $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug);
        if (is_string($trans) && $trans !== '') {
            $slug = $trans;
        }
    }
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? 'plantilla');
    $slug = trim($slug, '-');
    if ($slug === '') {
        $slug = 'tpl-' . time();
    }
    if (strlen($slug) > 70) {
        $slug = rtrim(substr($slug, 0, 70), '-');
    }

    return $slug;
}

function cw_mailing_unique_slug(mysqli $conn, string $slug, int $ignoreId = 0): string
{
    $slug = cw_mailing_make_slug($slug);
    $base = $slug;
    for ($n = 1; $n <= 30; $n++) {
        $sql = 'SELECT id FROM cw_mailing_templates WHERE slug = ? LIMIT 1';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return $slug . '-' . ($ignoreId > 0 ? $ignoreId : time());
        }
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $foundId = $row ? (int) $row['id'] : 0;
        if ($foundId === 0 || $foundId === $ignoreId) {
            return $slug;
        }
        $slug = $base . '-' . ($n + 1);
        if (strlen($slug) > 80) {
            $slug = substr($base, 0, 70) . '-' . ($n + 1);
        }
    }

    return $base . '-' . ($ignoreId > 0 ? $ignoreId : time());
}

function cw_mailing_save_template(mysqli $conn, array $data, int $id = 0): array
{
    $title = trim((string) ($data['title'] ?? ''));
    $theme = trim((string) ($data['theme'] ?? 'general'));
    if (function_exists('cw_mailing_ai_humanize_label')) {
        $title = cw_mailing_ai_humanize_label($title, $theme);
    } elseif (preg_match('/[_]/', $title) || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)+$/', $title)) {
        $title = trim(str_replace(['_', '-'], ' ', $title));
        $title = mb_convert_case($title, MB_CASE_TITLE, 'UTF-8');
    }
    if (function_exists('cw_mailing_ai_humanize_theme')) {
        $theme = cw_mailing_ai_humanize_theme($theme);
    }
    $subject = trim((string) ($data['subject'] ?? ''));
    if (function_exists('cw_mailing_ai_humanize_subject')) {
        $subject = cw_mailing_ai_humanize_subject($subject, $title);
    }
    $preheader = trim((string) ($data['preheader'] ?? ''));
    $body = (string) ($data['body_html'] ?? '');
    $image = trim((string) ($data['image_url'] ?? ''));
    $ctaLabel = trim((string) ($data['cta_label'] ?? ''));
    $ctaUrl = trim((string) ($data['cta_url'] ?? ''));
    $active = !empty($data['active']) ? 1 : 0;
    $slug = trim((string) ($data['slug'] ?? ''));

    if ($title === '' || $subject === '' || trim(strip_tags($body)) === '') {
        return ['ok' => false, 'error' => 'Título, asunto y cuerpo son obligatorios'];
    }
    if ($theme === '') {
        $theme = 'general';
    }
    if ($id > 0) {
        $existing = cw_mailing_get_template($conn, $id);
        if (!$existing) {
            return ['ok' => false, 'error' => 'Plantilla no encontrada'];
        }
        if ($slug === '') {
            $slug = (string) ($existing['slug'] ?? '');
        }
    }
    if ($slug === '') {
        $slug = cw_mailing_make_slug($title);
    }
    $slug = cw_mailing_unique_slug($conn, $slug, $id);

    try {
    if ($id > 0) {
        $stmt = $conn->prepare(
            'UPDATE cw_mailing_templates SET slug=?, title=?, theme=?, subject=?, preheader=?, body_html=?,
             image_url=?, cta_label=?, cta_url=?, active=?, updated_at=NOW() WHERE id=?'
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'No se pudo preparar la actualización'];
        }
        $stmt->bind_param(
            'sssssssssii',
            $slug,
            $title,
            $theme,
            $subject,
            $preheader,
            $body,
            $image,
            $ctaLabel,
            $ctaUrl,
            $active,
            $id
        );
        $ok = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();
        if (!$ok) {
            return ['ok' => false, 'error' => $err !== '' ? $err : 'Error al actualizar'];
        }

        return ['ok' => true, 'id' => $id];
    }

    $stmt = $conn->prepare(
        'INSERT INTO cw_mailing_templates
        (slug, title, theme, subject, preheader, body_html, image_url, cta_label, cta_url, active, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    if (!$stmt) {
        return ['ok' => false, 'error' => 'No se pudo preparar el alta'];
    }
    $stmt->bind_param(
        'sssssssssi',
        $slug,
        $title,
        $theme,
        $subject,
        $preheader,
        $body,
        $image,
        $ctaLabel,
        $ctaUrl,
        $active
    );
    $ok = $stmt->execute();
    $newId = (int) $stmt->insert_id;
    $err = $stmt->error;
    $stmt->close();
    if (!$ok) {
        return ['ok' => false, 'error' => $err !== '' ? $err : 'Error al guardar'];
    }

    return ['ok' => true, 'id' => $newId];
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        if (stripos($msg, 'Duplicate') !== false || stripos($msg, 'uq_mailing_tpl_slug') !== false) {
            return ['ok' => false, 'error' => 'Ese nombre de plantilla ya existe. Cambia el título o guarda como nueva.'];
        }

        return ['ok' => false, 'error' => 'No se pudo guardar la plantilla'];
    }
}

/**
 * @return array{ok:bool,error?:string}
 */
function cw_mailing_delete_template(mysqli $conn, int $id): array
{
    if ($id <= 0) {
        return ['ok' => false, 'error' => 'ID inválido'];
    }
    $stmt = $conn->prepare('DELETE FROM cw_mailing_templates WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return ['ok' => false, 'error' => 'No se pudo eliminar'];
    }
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'Error al eliminar'];
}

/**
 * @return array{ok:bool,send_id?:int,token?:string,error?:string}
 */
function cw_mailing_send_one(
    mysqli $conn,
    array $tpl,
    array $recipient,
    string $audience,
    int $audienceId,
    ?int $createdBy = null
): array {
    $email = trim((string) ($recipient['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Correo inválido'];
    }

    // Respetar baja
    $chk = $conn->prepare(
        "SELECT id FROM cw_mailing_sends WHERE email = ? AND status = 'unsubscribed' LIMIT 1"
    );
    if ($chk) {
        $chk->bind_param('s', $email);
        $chk->execute();
        $un = $chk->get_result()->fetch_assoc();
        $chk->close();
        if ($un) {
            return ['ok' => false, 'error' => 'Este correo está dado de baja'];
        }
    }

    $token = cw_mailing_new_token();
    $vars = cw_mailing_vars($recipient);
    $subject = cw_mailing_apply_vars((string) ($tpl['subject'] ?? 'ConlineWeb'), $vars);
    $name = (string) ($recipient['name'] ?? '');
    $company = (string) ($recipient['company'] ?? '');
    $tplId = (int) ($tpl['id'] ?? 0);
    $tplTitle = (string) ($tpl['title'] ?? '');
    $status = 'queued';

    $stmt = $conn->prepare(
        'INSERT INTO cw_mailing_sends
        (token, template_id, template_title, audience, audience_id, email, name, company, subject, status, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        return ['ok' => false, 'error' => 'No se pudo registrar el envío'];
    }
    $createdByVal = $createdBy !== null ? (int) $createdBy : 0;
    $stmt->bind_param(
        'sississsssi',
        $token,
        $tplId,
        $tplTitle,
        $audience,
        $audienceId,
        $email,
        $name,
        $company,
        $subject,
        $status,
        $createdByVal
    );
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();

        return ['ok' => false, 'error' => $err !== '' ? $err : 'Error al registrar envío'];
    }
    $sendId = (int) $stmt->insert_id;
    $stmt->close();

    $html = cw_mailing_build_html($tpl, $recipient, $token, true);
    $alt = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    require_once dirname(__DIR__) . '/PHPMailer/src/Exception.php';
    require_once dirname(__DIR__) . '/PHPMailer/src/PHPMailer.php';
    require_once dirname(__DIR__) . '/PHPMailer/src/SMTP.php';
    require_once dirname(__DIR__) . '/smtp_config_helper.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        configure_phpmailer_by_system($mail, 'conlineweb', false);
        $mail->addAddress($email, $name !== '' ? $name : $email);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = $alt !== '' ? $alt : $subject;
        $mail->send();

        $upd = $conn->prepare("UPDATE cw_mailing_sends SET status='sent', sent_at=NOW() WHERE id=?");
        if ($upd) {
            $upd->bind_param('i', $sendId);
            $upd->execute();
            $upd->close();
        }
        cw_mailing_log_event($conn, $sendId, 'sent');

        return ['ok' => true, 'send_id' => $sendId, 'token' => $token];
    } catch (Throwable $e) {
        $msg = mb_substr($e->getMessage(), 0, 480);
        $upd = $conn->prepare("UPDATE cw_mailing_sends SET status='failed', error_message=? WHERE id=?");
        if ($upd) {
            $upd->bind_param('si', $msg, $sendId);
            $upd->execute();
            $upd->close();
        }
        cw_mailing_log_event($conn, $sendId, 'fail', '', $msg);
        error_log('cw_mailing_send_one: ' . $e->getMessage());

        return ['ok' => false, 'error' => 'No se pudo enviar el correo', 'send_id' => $sendId];
    }
}

function cw_mailing_log_event(
    mysqli $conn,
    int $sendId,
    string $type,
    string $url = '',
    string $note = ''
): void {
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $ua = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250);
    if ($note !== '' && $url === '') {
        $url = mb_substr($note, 0, 480);
    }
    $stmt = $conn->prepare(
        'INSERT INTO cw_mailing_events (send_id, event_type, url, ip, user_agent) VALUES (?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('issss', $sendId, $type, $url, $ip, $ua);
    $stmt->execute();
    $stmt->close();
}

/** @return array{ok:bool,redirect?:string,pixel?:bool} */
function cw_mailing_handle_track(mysqli $conn, string $event, string $token, string $encodedUrl = ''): array
{
    $token = preg_replace('/[^a-f0-9]/i', '', $token) ?? '';
    if (strlen($token) < 20) {
        return ['ok' => false];
    }

    $stmt = $conn->prepare('SELECT * FROM cw_mailing_sends WHERE token = ? LIMIT 1');
    if (!$stmt) {
        return ['ok' => false];
    }
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $send = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$send) {
        return ['ok' => false];
    }
    $sendId = (int) $send['id'];

    if ($event === 'open') {
        $upd = $conn->prepare(
            'UPDATE cw_mailing_sends SET open_count = open_count + 1,
             opened_at = IFNULL(opened_at, NOW()) WHERE id = ?'
        );
        if ($upd) {
            $upd->bind_param('i', $sendId);
            $upd->execute();
            $upd->close();
        }
        cw_mailing_log_event($conn, $sendId, 'open');

        return ['ok' => true, 'pixel' => true];
    }

    if ($event === 'click') {
        $url = '';
        if ($encodedUrl !== '') {
            $pad = strlen($encodedUrl) % 4;
            if ($pad > 0) {
                $encodedUrl .= str_repeat('=', 4 - $pad);
            }
            $decoded = base64_decode(strtr($encodedUrl, '-_', '+/'), true);
            if (is_string($decoded) && preg_match('#^https?://#i', $decoded)) {
                $url = $decoded;
            }
        }
        if ($url === '') {
            $url = 'https://conlineweb.com/';
        }
        $upd = $conn->prepare(
            'UPDATE cw_mailing_sends SET click_count = click_count + 1,
             last_click_at = NOW(), last_click_url = ? WHERE id = ?'
        );
        if ($upd) {
            $short = mb_substr($url, 0, 480);
            $upd->bind_param('si', $short, $sendId);
            $upd->execute();
            $upd->close();
        }
        cw_mailing_log_event($conn, $sendId, 'click', mb_substr($url, 0, 480));

        return ['ok' => true, 'redirect' => $url];
    }

    if ($event === 'unsub') {
        $upd = $conn->prepare(
            "UPDATE cw_mailing_sends SET status='unsubscribed', unsubscribed_at=NOW() WHERE id=?"
        );
        if ($upd) {
            $upd->bind_param('i', $sendId);
            $upd->execute();
            $upd->close();
        }
        cw_mailing_log_event($conn, $sendId, 'unsub');

        return ['ok' => true, 'redirect' => 'https://conlineweb.com/?mailing=unsubscribed'];
    }

    return ['ok' => false];
}

/** @return array<string,mixed> */
function cw_mailing_stats(mysqli $conn): array
{
    $stats = [
        'templates' => 0,
        'sent' => 0,
        'failed' => 0,
        'opened' => 0,
        'clicked' => 0,
        'unsubscribed' => 0,
        'open_rate' => 0.0,
        'click_rate' => 0.0,
    ];
    $r = $conn->query('SELECT COUNT(*) c FROM cw_mailing_templates WHERE active=1');
    $stats['templates'] = (int) (($r && ($row = $r->fetch_assoc())) ? $row['c'] : 0);

    $r = $conn->query("SELECT
        SUM(status='sent') sent,
        SUM(status='failed') failed,
        SUM(open_count > 0) opened,
        SUM(click_count > 0) clicked,
        SUM(status='unsubscribed') unsubscribed
        FROM cw_mailing_sends");
    if ($r && ($row = $r->fetch_assoc())) {
        $stats['sent'] = (int) ($row['sent'] ?? 0);
        $stats['failed'] = (int) ($row['failed'] ?? 0);
        $stats['opened'] = (int) ($row['opened'] ?? 0);
        $stats['clicked'] = (int) ($row['clicked'] ?? 0);
        $stats['unsubscribed'] = (int) ($row['unsubscribed'] ?? 0);
    }
    if ($stats['sent'] > 0) {
        $stats['open_rate'] = round(($stats['opened'] / $stats['sent']) * 100, 1);
        $stats['click_rate'] = round(($stats['clicked'] / $stats['sent']) * 100, 1);
    }

    return $stats;
}

/** @return list<array<string,mixed>> */
function cw_mailing_recent_sends(mysqli $conn, int $limit = 80): array
{
    $limit = max(1, min(200, $limit));
    $sql = "SELECT id, template_title, audience, audience_id, email, name, company, subject, status,
            open_count, click_count, opened_at, last_click_at, last_click_url, sent_at, created_at, error_message
            FROM cw_mailing_sends ORDER BY id DESC LIMIT {$limit}";
    $res = $conn->query($sql);
    if (!$res) {
        return [];
    }
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $out[] = $row;
    }

    return $out;
}

/**
 * Misma consulta de activos que clientes.php (sistema conlineweb):
 * JOIN login tipo 0, ventas solo los suyos, sin eliminados ni transferidos.
 *
 * @return list<array<string,mixed>>
 */
function cw_mailing_list_clientes(mysqli $conn, string $q = '', int $limit = 0, int $uid = 0, int $tipo = 0): array
{
    $tieneTransferido = false;
    $col = $conn->query("SHOW COLUMNS FROM clientes LIKE 'transferido'");
    if ($col && $col->num_rows > 0) {
        $tieneTransferido = true;
    }

    $select = $tieneTransferido
        ? 'c.id, c.empresa, c.nombre_contacto, c.correo, c.telefono, c.actualizado, c.eliminado, c.transferido'
        : 'c.id, c.empresa, c.nombre_contacto, c.correo, c.telefono, c.actualizado, c.eliminado';

    $sql = "SELECT {$select}
            FROM clientes c
            INNER JOIN login l ON l.id = c.id
            WHERE l.id_tipo_usuario = 0";
    $params = [];
    $types = '';
    if ($tipo === 5 && $uid > 0) {
        $sql .= ' AND c.usuario_registro = ?';
        $params[] = $uid;
        $types .= 'i';
    }
    if ($q !== '') {
        $sql .= ' AND (c.empresa LIKE ? OR c.nombre_contacto LIKE ? OR c.correo LIKE ?)';
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $types .= 'sss';
    }
    $sql .= ' ORDER BY c.id DESC';

    $res = null;
    $stmt = null;
    if ($params !== []) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $conn->query($sql);
    }
    if (!$res) {
        if ($stmt) {
            $stmt->close();
        }

        return [];
    }

    $out = [];
    while ($row = $res->fetch_assoc()) {
        $esTransferido = $tieneTransferido && isset($row['transferido']) && (int) $row['transferido'] === 1;
        if ($esTransferido) {
            continue;
        }
        if (isset($row['eliminado']) && (int) $row['eliminado'] === 1) {
            continue;
        }
        $out[] = $row;
        if ($limit > 0 && count($out) >= $limit) {
            break;
        }
    }
    if ($stmt) {
        $stmt->close();
    }

    return $out;
}

/** @return list<array<string,mixed>> */
function cw_mailing_list_leads(mysqli $conn, string $q = '', int $limit = 400): array
{
    $limit = max(1, min(800, $limit));
    $sql = "SELECT id, nombre, apellido, empresa, correo, telefono, pipeline_estado, servicio
            FROM leads
            WHERE IFNULL(eliminado,0)=0
              AND IFNULL(origen_web,0)=1
              AND correo IS NOT NULL AND TRIM(correo) <> ''
              AND correo LIKE '%@%'";
    $params = [];
    $types = '';
    if ($q !== '') {
        $sql .= ' AND (nombre LIKE ? OR apellido LIKE ? OR empresa LIKE ? OR correo LIKE ?)';
        $like = '%' . $q . '%';
        $params = [$like, $like, $like, $like];
        $types = 'ssss';
    }
    $sql .= " ORDER BY fecha_registro DESC LIMIT {$limit}";
    if ($params) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $out = [];
        while ($row = $res->fetch_assoc()) {
            $out[] = $row;
        }
        $stmt->close();

        return $out;
    }
    $res = $conn->query($sql);
    if (!$res) {
        return [];
    }
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $out[] = $row;
    }

    return $out;
}
