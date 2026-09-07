<?php
/**
 * Datos de ejemplo para email_preview.php — solo preview, sin envío.
 */
require_once dirname(__DIR__, 2) . '/includes/cw_email_brand.php';
require_once __DIR__ . '/adm_email_template.php';
require_once __DIR__ . '/pago_email_template.php';
require_once __DIR__ . '/chat_email_template.php';

$clienteTemplate = dirname(__DIR__, 2) . '/cliente.conlineweb.com/includes/cliente_email_template.php';
if (is_file($clienteTemplate)) {
    require_once $clienteTemplate;
}

function email_preview_demo_cliente(): array
{
    return [
        'nombre' => 'María López',
        'empresa' => 'Empresa Demo SA',
        'correo' => 'maria@ejemplo.com',
        'telefono' => '477 123 4567',
        'dominio' => 'miempresa.com.mx',
    ];
}

function email_preview_demo_pedido(): array
{
    return [
        'cliente' => ['nombre' => 'Carlos Ruiz', 'email' => 'carlos@ejemplo.com'],
        'pedido' => [
            'numero_pedido' => 'PED-2026-1042',
            'total' => 4999.00,
            'moneda' => 'MXN',
            'productos' => [
                ['nombre' => 'Página web profesional', 'periodo' => 'Pago único', 'precio' => 4999, 'moneda' => 'MXN'],
            ],
        ],
    ];
}

function email_preview_adm_renovacion_dominio(): string
{
    $d = email_preview_demo_cliente();
    $cuerpo = "Estimado/a {$d['nombre']},<br><br>"
        . "Le recordamos que el dominio <strong>{$d['dominio']}</strong> tiene como fecha de renovación el <strong>30 Ago 2026</strong>.<br><br>"
        . "Por favor, considere renovarlo a tiempo para evitar interrupciones en su servicio.";
    $despedida = "Gracias por su atención.<br>Atentamente, <br><strong>Equipo de soporte técnico</strong>";

    return adm_email_message('Renovación de dominio', $cuerpo, $despedida);
}

function email_preview_adm_renovacion_hosting(): string
{
    $d = email_preview_demo_cliente();
    $cuerpo = "Estimado/a {$d['nombre']},<br><br>"
        . "Le recordamos que su plan de <strong>Hosting Pro</strong> para <strong>{$d['dominio']}</strong> vence el <strong>15 Sep 2026</strong>.<br><br>"
        . "Renueve a tiempo para mantener su sitio en línea sin interrupciones.";
    $despedida = "Atentamente,<br><strong>Equipo de soporte técnico</strong>";

    return adm_email_message('Renovación de hosting', $cuerpo, $despedida);
}

function email_preview_adm_confirmacion_hosting(): string
{
    $d = email_preview_demo_cliente();
    $cuerpo = "Estimado/a {$d['nombre']},<br><br>"
        . "Confirmamos la renovación de su servicio de hosting para <strong>{$d['dominio']}</strong>.<br>"
        . "Su servicio queda activo hasta el <strong>15 Sep 2027</strong>.";
    $despedida = "Gracias por confiar en ConlineWeb.<br><strong>Equipo de soporte</strong>";

    return adm_email_message('Confirmación de renovación', $cuerpo, $despedida, ['badge' => 'Hosting', 'badge_variant' => 'success']);
}

function email_preview_adm_accesos(): string
{
    $d = email_preview_demo_cliente();
    $cuerpo = "Estimado/a {$d['nombre']},<br><br>"
        . "Le compartimos sus accesos al <strong>Área Cliente ConlineWeb</strong>:<br><br>"
        . "<strong>Usuario:</strong> {$d['correo']}<br>"
        . "<strong>Portal:</strong> <a href=\"https://cliente.conlineweb.com\">cliente.conlineweb.com</a>";
    $despedida = "Atentamente,<br><strong>Equipo ConlineWeb</strong>";

    return adm_email_message('Accesos al portal de cliente', $cuerpo, $despedida, ['badge' => 'Accesos', 'badge_variant' => 'info']);
}

function email_preview_adm_confirmar_correo(): string
{
    if (!function_exists('cliente_email_verify_change')) {
        return cw_email_wrap(['title' => 'Preview no disponible', 'content' => cw_email_p('Falta cliente_email_template.php')]);
    }

    return cliente_email_verify_change(
        email_preview_demo_cliente()['nombre'],
        'https://adm.conlineweb.com/confirmar_correo.php?id=42'
    );
}

function email_preview_adm_ticket_asignacion(): string
{
    $d = email_preview_demo_cliente();

    return cw_email_wrap([
        'title' => 'Nueva tarea asignada',
        'badge' => 'Tickets #1042',
        'badge_variant' => 'warning',
        'content' => cw_email_p('Estimado/a <strong>Agente Demo</strong>,')
            . cw_email_p('Se te ha asignado una nueva tarea en el sistema de tickets de soporte:')
            . cw_email_kv([
                ['label' => 'Ticket', 'value' => '#1042'],
                ['label' => 'Asunto', 'value' => 'Cambio de DNS en producción'],
                ['label' => 'Cliente', 'value' => $d['nombre'] . ' / ' . $d['empresa']],
                ['label' => 'Asignación', 'value' => date('d/m/Y – H:i')],
            ])
            . cw_email_cta('https://adm.conlineweb.com/solicitudes/tickets_desarrollador.php?id=1', 'Abrir ticket', 'primary'),
        'signature_team' => 'Portal de Soporte ConlineWeb',
    ]);
}

function email_preview_adm_ticket_nota(): string
{
    return cw_email_wrap([
        'title' => 'Nuevo comentario en ticket',
        'badge' => 'Tickets #1042',
        'badge_variant' => 'info',
        'content' => cw_email_p('Hola,')
            . cw_email_p('Se ha añadido un nuevo comentario en el Ticket <strong>#1042</strong> – <em>Cambio de DNS</em>.')
            . cw_email_card(
                '<ul style="margin:0;padding-left:18px;">'
                . '<li><strong>Número:</strong> #1042</li>'
                . '<li><strong>Autor:</strong> Cliente Demo</li>'
                . '<li><strong>Fecha:</strong> ' . date('d/m/Y H:i') . '</li></ul>',
                'Detalles del ticket'
            )
            . cw_email_p('<strong>Comentario:</strong><br><span style="display:block;margin-top:8px;padding:12px;background:#f8fafc;border-left:4px solid #10b981;border-radius:8px;">Por favor actualicen los NS antes del viernes.</span>')
            . cw_email_cta('https://adm.conlineweb.com/solicitudes/tickets_desarrollador.php?id=1', 'Ver ticket #1042', 'primary'),
        'signature_team' => 'Sistema de Gestión de Tickets',
    ]);
}

function email_preview_adm_ticket_finalizado(): string
{
    $d = email_preview_demo_cliente();

    return cw_email_wrap([
        'title' => 'Ticket completado',
        'badge' => 'Finalizado #1042',
        'badge_variant' => 'success',
        'signature' => null,
        'content' => cw_email_p('Estimado equipo,')
            . cw_email_alert('El desarrollador <strong>Agente Demo</strong> ha marcado como <strong>finalizado</strong> el siguiente ticket.', 'success')
            . cw_email_kv([
                ['label' => 'ID Ticket', 'value' => '#1042'],
                ['label' => 'Título', 'value' => 'Cambio de DNS'],
                ['label' => 'Desarrollador', 'value' => 'Agente Demo'],
                ['label' => 'Empresa', 'value' => $d['empresa']],
                ['label' => 'Cliente', 'value' => $d['nombre']],
                ['label' => 'Finalización', 'value_html' => '<span style="color:#047857;font-weight:700;">' . date('d/m/Y H:i') . '</span>'],
            ])
            . cw_email_cta('https://adm.conlineweb.com/solicitudes/index.php', 'Ver panel de administración', 'primary'),
    ]);
}

function email_preview_adm_dns_alerta(): string
{
    $d = email_preview_demo_cliente();

    return adm_email_alert(
        'Cambio de DNS detectado',
        'Un cliente ha actualizado los servidores DNS de uno de sus dominios.',
        cw_email_alert('Verifica que el cambio sea autorizado y que la propagación DNS se realice correctamente.', 'warning')
            . cw_email_kv([
                ['label' => 'Dominio', 'value_html' => '<strong>' . cw_email_h($d['dominio']) . '</strong>'],
                ['label' => 'Cliente', 'value' => $d['nombre']],
                ['label' => 'Empresa', 'value' => $d['empresa']],
                ['label' => 'Correo', 'value' => $d['correo']],
                ['label' => 'Fecha', 'value' => date('d/m/Y H:i')],
                ['label' => 'Nuevos DNS', 'value_html' => 'NS1: ns1.conlineweb.com<br>NS2: ns2.conlineweb.com'],
            ]),
        ['badge' => 'DNS', 'alert_type' => 'warning']
    );
}

function email_preview_adm_epp_solicitud(): string
{
    $d = email_preview_demo_cliente();

    return cw_email_wrap([
        'title' => 'Solicitud de Código de Transferencia',
        'badge' => 'Alerta interna',
        'badge_variant' => 'warning',
        'signature' => null,
        'content' => cw_email_p('Se recibió una solicitud de <strong>código EPP</strong> para transferencia de dominio.')
            . cw_email_alert('El cliente requiere acción: proporciona el código EPP/AUTH del dominio.', 'warning')
            . cw_email_kv([
                ['label' => 'Dominio', 'value_html' => '<strong>' . cw_email_h($d['dominio']) . '</strong>'],
                ['label' => 'Cliente', 'value' => $d['nombre']],
                ['label' => 'Correo', 'value' => $d['correo']],
                ['label' => 'Teléfono', 'value' => $d['telefono']],
                ['label' => 'Fecha', 'value' => date('d/m/Y H:i')],
            ])
            . cw_email_cta('https://wa.me/524771181285', 'Responder por WhatsApp', 'whatsapp'),
    ]);
}

function email_preview_adm_hub_lead(): string
{
    require_once __DIR__ . '/cw_hub_notify.php';
    $body = cw_email_p('Hola <strong>María</strong>,')
        . cw_email_p('Recibimos tu solicitud desde el sitio web.')
        . cw_email_card('<p style="margin:0"><strong>Servicio:</strong> Página web</p>', 'Tu solicitud')
        . cw_email_cta('https://wa.me/524771181285', 'Continuar en WhatsApp', 'whatsapp');

    return cw_hub_email_template('Confirmación de tu solicitud', $body);
}

function email_preview_adm_hub_admin(): string
{
    $inner = cw_email_p('Se registró un contacto desde el <strong>modal WhatsApp</strong>.')
        . cw_email_kv([
            ['label' => 'Lead', 'value' => '#1042'],
            ['label' => 'Nombre', 'value' => 'María López'],
            ['label' => 'Servicio', 'value' => 'E-commerce'],
        ]);

    return cw_email_wrap([
        'title' => 'Nuevo lead web #1042',
        'content' => $inner,
        'badge' => 'CRM',
        'badge_variant' => 'warning',
        'signature' => null,
    ]);
}

function email_preview_cliente_ticket(): string
{
    return cw_email_wrap([
        'title' => '¡Ticket creado!',
        'badge' => 'Soporte #1042',
        'badge_variant' => 'success',
        'content' => cw_email_p('Hola <strong>María López</strong>,')
            . cw_email_alert('Tu solicitud fue registrada. Actualmente hay <strong>3 ticket(s) pendientes</strong> en la cola.', 'success')
            . cw_email_card(
                '<p style="margin:0 0 10px;"><span style="display:inline-block;padding:4px 10px;border-radius:999px;background:#fef3c7;color:#92400e;font-size:11px;font-weight:700;">Pendiente</span> '
                . '<span style="display:inline-block;padding:4px 10px;border-radius:999px;background:#fee2e2;color:#991b1b;font-size:11px;font-weight:700;margin-left:6px;">Prioridad: Alta</span></p>'
                . '<p style="margin:0 0 8px;"><strong style="color:#000147;">Título</strong><br>Cambio de DNS</p>'
                . '<p style="margin:0;"><strong style="color:#000147;">Descripción</strong><br><span style="display:block;margin-top:6px;padding:12px;background:#f8fafc;border-radius:8px;font-size:13px;">Necesito apuntar el dominio a los nuevos servidores.</span></p>',
                'Detalle del ticket #1042'
            )
            . cw_email_p('El procesamiento se realizará en un plazo máximo de <strong>4 días hábiles</strong>.')
            . cw_email_cta('https://wa.me/524771181285', 'Contactar por WhatsApp', 'whatsapp')
            . cw_email_alert('Si tu solicitud genera algún cargo adicional, primero te contactaremos para confirmar el monto.', 'warning'),
        'signature_team' => 'Equipo de CONLINEWEB',
    ]);
}

function email_preview_cliente_epp_recibido(): string
{
    $d = email_preview_demo_cliente();

    return cw_email_wrap([
        'title' => 'Tu solicitud fue recibida',
        'badge' => 'Área Cliente',
        'badge_variant' => 'success',
        'signature' => null,
        'content' => cw_email_p('Hola <strong>' . cw_email_h($d['nombre']) . '</strong>, recibimos tu solicitud de <strong>código EPP</strong> para el dominio:')
            . cw_email_kv([
                ['label' => 'Dominio', 'value_html' => '<strong>' . cw_email_h($d['dominio']) . '</strong>'],
                ['label' => 'Tipo', 'value' => 'Código de transferencia'],
                ['label' => 'Fecha', 'value' => date('d/m/Y H:i')],
            ])
            . cw_email_p('Te enviaremos el código EPP en un plazo de <strong>1 a 3 días hábiles</strong>.')
            . cw_email_cta('https://wa.me/524771181285', 'Contactar soporte', 'whatsapp'),
    ]);
}

function email_preview_cliente_verificar_correo(): string
{
    if (!function_exists('cliente_email_verify_change')) {
        return cw_email_wrap(['title' => 'Preview no disponible', 'content' => cw_email_p('Falta cliente_email_template.php')]);
    }

    return cliente_email_verify_change(email_preview_demo_cliente()['nombre'], 'https://adm.conlineweb.com/confirmar_correo.php?id=42');
}

function email_preview_cliente_password_reset(): string
{
    if (!function_exists('cliente_email_password_reset_link')) {
        return cw_email_wrap(['title' => 'Preview no disponible', 'content' => cw_email_p('Falta cliente_email_template.php')]);
    }

    return cliente_email_password_reset_link('https://cliente.conlineweb.com/reset_password.php?token=demo');
}

function email_preview_cliente_password_changed(): string
{
    if (!function_exists('cliente_email_password_changed')) {
        return cw_email_wrap(['title' => 'Preview no disponible', 'content' => cw_email_p('Falta cliente_email_template.php')]);
    }

    return cliente_email_password_changed();
}

function email_preview_web_bienvenida(): string
{
    $path = dirname(__DIR__, 2) . '/conlineweb.com/includes/email_templates.php';
    if (!is_file($path)) {
        return cw_email_wrap(['title' => 'Preview no disponible', 'content' => cw_email_p('No se encontró conlineweb.com/includes/email_templates.php')]);
    }
    require_once $path;

    return get_template_bienvenida(
        'Carlos Ruiz',
        'carlos@ejemplo.com',
        'TempPass2026!',
        [['nombre' => 'Página web profesional', 'periodo' => 'Pago único', 'precio' => 4999, 'moneda' => 'MXN']]
    );
}

function email_preview_web_pedido(): string
{
    $path = dirname(__DIR__, 2) . '/conlineweb.com/includes/email_templates.php';
    if (!is_file($path)) {
        return cw_email_wrap(['title' => 'Preview no disponible', 'content' => cw_email_p('No se encontró email_templates.php')]);
    }
    require_once $path;
    $demo = email_preview_demo_pedido();

    return get_template_confirmacion_pedido($demo['cliente'], $demo['pedido']);
}

function email_preview_web_admin_usuario(): string
{
    $path = dirname(__DIR__, 2) . '/conlineweb.com/includes/email_templates.php';
    if (!is_file($path)) {
        return cw_email_wrap(['title' => 'Preview no disponible', 'content' => cw_email_p('No se encontró email_templates.php')]);
    }
    require_once $path;

    return get_template_admin_nuevo_usuario('Carlos Ruiz', 'carlos@ejemplo.com', '477 123 4567', 'México', 'León, Gto.');
}

function email_preview_web_admin_pedido(): string
{
    $path = dirname(__DIR__, 2) . '/conlineweb.com/includes/email_templates.php';
    if (!is_file($path)) {
        return cw_email_wrap(['title' => 'Preview no disponible', 'content' => cw_email_p('No se encontró email_templates.php')]);
    }
    require_once $path;
    $demo = email_preview_demo_pedido();

    return get_template_admin_nuevo_pedido($demo['cliente'], $demo['pedido']);
}

function email_preview_adm_confirmacion_dominio(): string
{
    $d = email_preview_demo_cliente();
    $cuerpo = "Estimado/a {$d['nombre']},<br><br>"
        . "Confirmamos la renovación del dominio <strong>{$d['dominio']}</strong>.<br>"
        . "Vigencia hasta el <strong>30 Ago 2027</strong>.";
    $despedida = "Gracias por confiar en ConlineWeb.<br><strong>Equipo de soporte</strong>";

    return adm_email_message('Confirmación de dominio', $cuerpo, $despedida, ['badge' => 'Dominios', 'badge_variant' => 'success']);
}

function email_preview_adm_whatsapp_alerta(): string
{
    $cuerpo = "Se recibió una nueva solicitud vía <strong>WhatsApp</strong> con pago registrado.<br><br>"
        . "<strong>Cliente:</strong> María López<br>"
        . "<strong>Ticket:</strong> #1042<br>"
        . "<strong>Monto:</strong> $1,500.00 MXN";
    $despedida = "Revisa el panel de administración para dar seguimiento.<br><strong>Equipo ConlineWeb</strong>";

    return adm_email_message('Alerta WhatsApp — pago recibido', $cuerpo, $despedida, ['badge' => 'WhatsApp', 'badge_variant' => 'warning']);
}

function email_preview_adm_actualizar_cliente(): string
{
    $d = email_preview_demo_cliente();
    $cuerpo = "Estimado/a {$d['nombre']},<br><br>"
        . "Te informamos que los datos de tu cuenta en ConlineWeb fueron actualizados correctamente.<br><br>"
        . "Si no autorizaste este cambio, contacta a soporte de inmediato.";
    $despedida = "Atentamente,<br><strong>Equipo ConlineWeb</strong>";

    return adm_email_message('Actualización de datos de cliente', $cuerpo, $despedida, ['badge' => 'Clientes', 'badge_variant' => 'info']);
}

function email_preview_hostpro_pago(): string
{
    $path = dirname(__DIR__) . '/hostpro_email_helper.php';
    if (!is_file($path)) {
        return cw_email_wrap(['title' => 'Preview no disponible', 'content' => cw_email_p('No se encontró hostpro_email_helper.php')]);
    }
    require_once $path;

    return hostpro_template_pago_pendiente(
        'Cliente Demo',
        'Renovación Hosting: midominio.com',
        '1,500.00',
        'MXN',
        date('d/m/Y', strtotime('+7 days')),
        'https://checkout.stripe.com/example',
        'hosting'
    );
}

/**
 * Pantallas web y herramientas (no son correos HTML transaccionales).
 *
 * @return array<string, list<array{label:string,url:string,note?:string}>>
 */
function email_preview_extras(): array
{
    return [
        'adm' => [
            ['label' => 'Pantalla — Pago individual', 'url' => 'payment_success_preview.php?v=individual'],
            ['label' => 'Pantalla — Pago grupal / múltiple', 'url' => 'payment_success_preview.php?v=grupal'],
            ['label' => 'Pantalla — Pago WhatsApp + ticket', 'url' => 'payment_success_preview.php?v=whatsapp'],
            ['label' => 'Pantalla — Ya pagado (info)', 'url' => 'payment_success_preview.php?v=info'],
            ['label' => 'Pantalla — Error de pago', 'url' => 'payment_success_preview.php?v=error'],
            ['label' => 'Índice pantallas post-pago', 'url' => 'payment_success_preview.php'],
            ['label' => 'Test SMTP (tickets)', 'url' => 'solicitudes/test_smtp.php', 'note' => 'Envía correo real'],
        ],
        'cliente' => [
            ['label' => 'Portal cliente — Mis pagos', 'url' => 'https://cliente.conlineweb.com/pagos.php', 'note' => 'Producción'],
        ],
        'conlineweb' => [
            ['label' => 'Test SMTP tienda', 'url' => '../conlineweb.com/admin/test_smtp.php', 'note' => 'Envía correo real'],
        ],
        'hostpro' => [
            ['label' => 'Sitio HostPro', 'url' => 'https://hostpro.com.mx', 'note' => 'Marca independiente'],
        ],
    ];
}

/**
 * @return array<string, array{project:string,label:string,group?:string,sort?:int}>
 */
function email_preview_catalog(): array
{
    return [
        // ── adm.conlineweb.com ──────────────────────────────────────────────
        'adm_pago_pendiente' => ['project' => 'adm', 'group' => 'Pagos', 'label' => 'Pago pendiente — Hosting', 'sort' => 10],
        'adm_pago_pendiente_dominio' => ['project' => 'adm', 'group' => 'Pagos', 'label' => 'Pago pendiente — Dominio', 'sort' => 10.5],
        'adm_pago_confirmacion' => ['project' => 'adm', 'group' => 'Pagos', 'label' => 'Confirmación de pago recibido', 'sort' => 11],
        'adm_pago_multiple' => ['project' => 'adm', 'group' => 'Pagos', 'label' => 'Pago múltiple confirmado', 'sort' => 12],
        'adm_pago_vencido_hosting' => ['project' => 'adm', 'group' => 'Pagos', 'label' => 'Aviso vencido — hosting (eliminación 5 días)', 'sort' => 13],
        'adm_pago_vencido_dominio' => ['project' => 'adm', 'group' => 'Pagos', 'label' => 'Aviso vencido — dominio (riesgo de pérdida)', 'sort' => 14],
        'adm_renovacion_dominio' => ['project' => 'adm', 'group' => 'Renovaciones', 'label' => 'Recordatorio renovación dominio', 'sort' => 20],
        'adm_renovacion_hosting' => ['project' => 'adm', 'group' => 'Renovaciones', 'label' => 'Recordatorio renovación hosting', 'sort' => 21],
        'adm_confirmacion_hosting' => ['project' => 'adm', 'group' => 'Renovaciones', 'label' => 'Confirmación renovación hosting', 'sort' => 22],
        'adm_confirmacion_dominio' => ['project' => 'adm', 'group' => 'Renovaciones', 'label' => 'Confirmación renovación dominio', 'sort' => 23],
        'adm_accesos' => ['project' => 'adm', 'group' => 'Clientes', 'label' => 'Accesos al portal cliente', 'sort' => 30],
        'adm_confirmar_correo' => ['project' => 'adm', 'group' => 'Clientes', 'label' => 'Confirmar cambio de correo (link)', 'sort' => 31],
        'adm_actualizar_cliente' => ['project' => 'adm', 'group' => 'Clientes', 'label' => 'Datos de cliente actualizados', 'sort' => 32],
        'adm_ticket_asignacion' => ['project' => 'adm', 'group' => 'Tickets', 'label' => 'Asignación a agente', 'sort' => 40],
        'adm_ticket_nota' => ['project' => 'adm', 'group' => 'Tickets', 'label' => 'Nuevo comentario en ticket', 'sort' => 41],
        'adm_ticket_finalizado' => ['project' => 'adm', 'group' => 'Tickets', 'label' => 'Ticket finalizado (notifica jefe)', 'sort' => 42],
        'adm_dns_alerta' => ['project' => 'adm', 'group' => 'Alertas internas', 'label' => 'Cambio de DNS detectado', 'sort' => 50],
        'adm_epp_solicitud' => ['project' => 'adm', 'group' => 'Alertas internas', 'label' => 'Solicitud código EPP (admin)', 'sort' => 51],
        'adm_whatsapp_alerta' => ['project' => 'adm', 'group' => 'Alertas internas', 'label' => 'Alerta vía WhatsApp webhook', 'sort' => 52],
        'adm_hub_lead' => ['project' => 'adm', 'group' => 'Hub / CRM', 'label' => 'Confirmación de lead (al cliente)', 'sort' => 60],
        'adm_hub_admin' => ['project' => 'adm', 'group' => 'Hub / CRM', 'label' => 'Nuevo lead web (admin)', 'sort' => 61],
        'adm_chat' => ['project' => 'adm', 'group' => 'Comunicación', 'label' => 'Notificación de chat', 'sort' => 70],
        // ── cliente.conlineweb.com ──────────────────────────────────────────
        'cliente_ticket' => ['project' => 'cliente', 'group' => 'Tickets', 'label' => 'Ticket creado (confirmación)', 'sort' => 10],
        'cliente_epp_recibido' => ['project' => 'cliente', 'group' => 'Dominios', 'label' => 'Solicitud EPP recibida', 'sort' => 20],
        'cliente_verificar_correo' => ['project' => 'cliente', 'group' => 'Cuenta y seguridad', 'label' => 'Verificar cambio de correo', 'sort' => 30],
        'cliente_password_reset' => ['project' => 'cliente', 'group' => 'Cuenta y seguridad', 'label' => 'Restablecer contraseña', 'sort' => 31],
        'cliente_password_changed' => ['project' => 'cliente', 'group' => 'Cuenta y seguridad', 'label' => 'Contraseña actualizada', 'sort' => 32],
        // ── conlineweb.com ────────────────────────────────────────────────────
        'web_bienvenida' => ['project' => 'conlineweb', 'group' => 'Tienda — Cliente', 'label' => 'Bienvenida nueva cuenta', 'sort' => 10],
        'web_pedido' => ['project' => 'conlineweb', 'group' => 'Tienda — Cliente', 'label' => 'Confirmación de pedido', 'sort' => 11],
        'web_admin_usuario' => ['project' => 'conlineweb', 'group' => 'Tienda — Admin', 'label' => 'Alerta: nuevo usuario', 'sort' => 20],
        'web_admin_pedido' => ['project' => 'conlineweb', 'group' => 'Tienda — Admin', 'label' => 'Alerta: nuevo pedido', 'sort' => 21],
        // ── HostPro (marca aparte) ────────────────────────────────────────────
        'hostpro_pago_pendiente' => ['project' => 'hostpro', 'group' => 'Pagos HostPro', 'label' => 'Pago pendiente HostPro', 'sort' => 10],
    ];
}

function email_preview_render(string $v): string
{
    return match ($v) {
        'adm_pago_pendiente' => pago_email_pendiente(
            'Renovación Hosting cpanel.ejemplo.com',
            '2,500.00',
            'MXN',
            '30/08/2026',
            'https://checkout.stripe.com/example',
            'hosting',
            'cpanel.ejemplo.com',
            'ejemplo.com'
        ),
        'adm_pago_pendiente_dominio' => pago_email_pendiente(
            'Renovación dominio ejemplo.com',
            '450.00',
            'MXN',
            '30/08/2026',
            'https://checkout.stripe.com/example',
            'dominio',
            'ejemplo.com',
            'ejemplo.com'
        ),
        'adm_pago_confirmacion' => pago_email_confirmacion('María López', date('d/m/Y H:i'), cw_email_p('Concepto: Renovación anual hosting', 0)),
        'adm_pago_multiple' => pago_email_multiple(4850, 'MXN', 3, 'PG-DEMO-001', date('d/m/Y H:i'), pago_email_servicios_list([
            ['tipo' => 'Hosting', 'nombre' => 'conlineweb.com', 'monto' => 2500, 'nueva_fecha' => '2027-07-14'],
            ['tipo' => 'Dominio', 'nombre' => 'miempresa.mx', 'monto' => 850, 'nueva_fecha' => '2027-03-20'],
        ])),
        'adm_pago_vencido_hosting' => pago_email_vencido_hosting(
            'María López',
            'Hosting Pro miempresa',
            'miempresa.com.mx',
            'Hosting Pro',
            '2,500.00',
            'MXN',
            date('d/m/Y', strtotime('-3 days')),
            date('d/m/Y', strtotime('-3 days +5 days')),
            date('d/m/Y', strtotime('-3 days +6 days')),
            'https://checkout.stripe.com/example'
        ),
        'adm_pago_vencido_dominio' => pago_email_vencido_dominio(
            'María López',
            'miempresa.com.mx',
            '850.00',
            'MXN',
            date('d/m/Y', strtotime('-5 days')),
            'https://checkout.stripe.com/example'
        ),
        'adm_renovacion_dominio' => email_preview_adm_renovacion_dominio(),
        'adm_renovacion_hosting' => email_preview_adm_renovacion_hosting(),
        'adm_confirmacion_hosting' => email_preview_adm_confirmacion_hosting(),
        'adm_confirmacion_dominio' => email_preview_adm_confirmacion_dominio(),
        'adm_accesos' => email_preview_adm_accesos(),
        'adm_confirmar_correo' => email_preview_adm_confirmar_correo(),
        'adm_actualizar_cliente' => email_preview_adm_actualizar_cliente(),
        'adm_ticket_asignacion' => email_preview_adm_ticket_asignacion(),
        'adm_ticket_nota' => email_preview_adm_ticket_nota(),
        'adm_ticket_finalizado' => email_preview_adm_ticket_finalizado(),
        'adm_dns_alerta' => email_preview_adm_dns_alerta(),
        'adm_epp_solicitud' => email_preview_adm_epp_solicitud(),
        'adm_whatsapp_alerta' => email_preview_adm_whatsapp_alerta(),
        'adm_hub_lead' => email_preview_adm_hub_lead(),
        'adm_hub_admin' => email_preview_adm_hub_admin(),
        'adm_chat' => chat_email_render([
            'titulo' => 'Nuevo mensaje en el chat',
            'parrafos' => ['Hola,', 'Tienes un mensaje nuevo en el <strong>Centro de Atención</strong> de ConlineWeb.'],
            'cta_texto' => 'Abrir conversación',
            'cta_url' => 'https://cliente.conlineweb.com/',
            'alerta_tipo' => 'info',
            'alerta_texto' => 'Responde pronto para mantener una excelente experiencia al cliente.',
        ]),
        'cliente_ticket' => email_preview_cliente_ticket(),
        'cliente_epp_recibido' => email_preview_cliente_epp_recibido(),
        'cliente_verificar_correo' => email_preview_cliente_verificar_correo(),
        'cliente_password_reset' => email_preview_cliente_password_reset(),
        'cliente_password_changed' => email_preview_cliente_password_changed(),
        'web_bienvenida' => email_preview_web_bienvenida(),
        'web_pedido' => email_preview_web_pedido(),
        'web_admin_usuario' => email_preview_web_admin_usuario(),
        'web_admin_pedido' => email_preview_web_admin_pedido(),
        'hostpro_pago_pendiente' => email_preview_hostpro_pago(),
        default => '',
    };
}
