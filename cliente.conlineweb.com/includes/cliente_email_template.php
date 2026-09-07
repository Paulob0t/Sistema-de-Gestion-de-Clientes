<?php

require_once dirname(__DIR__, 2) . '/includes/cw_email_brand.php';

function cliente_email_logo_url(): string
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
function cliente_email_render(array $config): string
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
        $content .= cw_email_alert($config['alerta_texto'], $tipo === 'warning' ? 'warning' : 'info');
    }

    return cw_email_wrap([
        'title' => $config['titulo'] ?? 'ConlineWeb',
        'content' => $content,
        'footer' => $config['pie'] ?? 'Este mensaje fue enviado automáticamente. Por favor, no respondas a este correo.',
        'badge' => $config['badge'] ?? 'Área Cliente',
        'badge_variant' => 'neutral',
    ]);
}

function cliente_email_password_changed(): string
{
    return cliente_email_render([
        'titulo' => 'Tu contraseña fue actualizada',
        'badge' => 'Seguridad',
        'parrafos' => [
            'Hola,',
            'Te confirmamos que la contraseña de tu cuenta en el <strong>Área Cliente ConlineWeb</strong> se actualizó correctamente.',
            'Si tú realizaste este cambio, no necesitas hacer nada más.',
        ],
        'alerta_tipo' => 'warning',
        'alerta_texto' => '<strong>¿No fuiste tú?</strong> Contacta de inmediato a soporte para proteger tu cuenta.',
    ]);
}

function cliente_email_password_reset_link(string $resetLink): string
{
    return cliente_email_render([
        'titulo' => 'Restablecer tu contraseña',
        'badge' => 'Seguridad',
        'parrafos' => [
            'Hola,',
            'Recibimos una solicitud para restablecer la contraseña de tu cuenta.',
            'El enlace es válido por <strong>1 hora</strong>. Si no solicitaste este cambio, puedes ignorar este correo.',
        ],
        'cta_texto' => 'Restablecer contraseña',
        'cta_url' => $resetLink,
    ]);
}

/**
 * @param array{badge?:string,badge_variant?:string,signature?:string|null,footer?:string} $opts
 */
function cliente_email_message(string $titulo, string $cuerpo, string $despedida = '', array $opts = []): string
{
    return cw_email_message($titulo, $cuerpo, $despedida, array_merge([
        'badge' => 'Área Cliente',
        'badge_variant' => 'neutral',
    ], $opts));
}

function cliente_email_verify_change(string $nombre, string $enlace): string
{
    return cliente_email_render([
        'titulo' => 'Confirmar cambio de correo',
        'badge' => 'Verificación',
        'parrafos' => [
            'Estimado/a <strong>' . cw_email_h($nombre) . '</strong>,',
            'Recibimos una solicitud para cambiar tu dirección de correo electrónico en el portal de cliente.',
            'Si fuiste tú, confirma el cambio con el botón de abajo.',
        ],
        'cta_texto' => 'Confirmar cambio de correo',
        'cta_url' => $enlace,
        'alerta_tipo' => 'warning',
        'alerta_texto' => 'Si no solicitaste este cambio, ignora este correo y contacta a soporte de inmediato.',
    ]);
}
