<?php
/**
 * Preview del diseño de confirmación de pago (solo desarrollo / diseño).
 * URL: /payment_success_preview.php?v=individual|grupal|whatsapp|info|error|index
 */
require_once __DIR__ . '/includes/payment_success_view.php';

$allowed = ['index', 'individual', 'grupal', 'whatsapp', 'info', 'error'];
$v = isset($_GET['v']) ? (string) $_GET['v'] : 'index';

if (!in_array($v, $allowed, true)) {
    $v = 'index';
}

if ($v === 'index') {
    ?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview — Pantallas de pago</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; font-family: Montserrat, sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; padding: 40px 20px; }
        .wrap { max-width: 720px; margin: 0 auto; }
        h1 { font-size: 1.6rem; margin: 0 0 8px; color: #fff; }
        p.lead { color: #94a3b8; margin: 0 0 28px; line-height: 1.6; }
        .grid { display: grid; gap: 12px; }
        a.card {
            display: flex; align-items: center; justify-content: space-between; gap: 16px;
            padding: 18px 20px; background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.1);
            border-radius: 14px; color: #fff; text-decoration: none; transition: .2s;
        }
        a.card:hover { background: rgba(255,255,255,.1); border-color: #10b981; transform: translateY(-2px); }
        a.card strong { font-size: 1rem; }
        a.card span { font-size: .85rem; color: #94a3b8; }
        .tag { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; padding: 4px 10px; border-radius: 999px; background: rgba(16,185,129,.2); color: #6ee7b7; }
        .tag--info { background: rgba(59,130,246,.2); color: #93c5fd; }
        .tag--err { background: rgba(239,68,68,.2); color: #fca5a5; }
        code { background: rgba(0,0,0,.3); padding: 2px 8px; border-radius: 6px; font-size: .8rem; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Preview — Confirmación de pago</h1>
        <p class="lead">Elige una variante para ver el diseño completo. Datos de ejemplo, sin Stripe ni base de datos.</p>
        <div class="grid">
            <a class="card" href="?v=individual">
                <div><strong>Pago individual</strong><br><span>Hosting / dominio — un servicio</span></div>
                <span class="tag">success</span>
            </a>
            <a class="card" href="?v=grupal">
                <div><strong>Pago grupal / múltiple</strong><br><span>Lista de servicios + total</span></div>
                <span class="tag">success</span>
            </a>
            <a class="card" href="?v=whatsapp">
                <div><strong>Pago WhatsApp + ticket</strong><br><span>Solicitud con número de ticket</span></div>
                <span class="tag">success</span>
            </a>
            <a class="card" href="?v=info">
                <div><strong>Ya pagado</strong><br><span>Transacción duplicada o ya registrada</span></div>
                <span class="tag tag--info">info</span>
            </a>
            <a class="card" href="?v=error">
                <div><strong>Error de pago</strong><br><span>Fallo Stripe o procesamiento</span></div>
                <span class="tag tag--err">error</span>
            </a>
        </div>
        <p style="margin-top:28px;font-size:.82rem;color:#64748b;">
            Local XAMPP: <code>http://localhost/sistemasconlineweb/adm.conlineweb.com/payment_success_preview.php</code><br>
            Producción: <code>https://adm.conlineweb.com/payment_success_preview.php</code>
        </p>
    </div>
</body>
</html><?php
    exit;
}

switch ($v) {
    case 'individual':
        payment_success_render([
            'variant' => 'success',
            'badge' => 'Pago confirmado',
            'title' => '¡Gracias por tu pago, José Antonio!',
            'subtitle' => 'Tu transacción fue registrada y tu servicio quedó activo. Te enviamos un correo de confirmación.',
            'metrics' => [
                ['label' => 'Referencia', 'value' => '#96', 'icon' => 'fas fa-hashtag'],
                ['label' => 'Método', 'value' => 'Tarjeta', 'icon' => 'fas fa-credit-card'],
                ['label' => 'Fecha', 'value' => date('d/m/Y'), 'icon' => 'fas fa-calendar-check'],
            ],
            'note' => 'Puedes descargar tu comprobante desde el <strong>portal de cliente</strong> en la sección Mis pagos.',
            'actions' => [
                ['label' => 'Ver mis pagos', 'url' => 'https://cliente.conlineweb.com/pagos.php', 'style' => 'primary', 'icon' => 'fas fa-receipt'],
                ['label' => 'Portal cliente', 'url' => 'https://cliente.conlineweb.com/', 'style' => 'ghost', 'icon' => 'fas fa-user'],
            ],
            'footnote' => 'Vista previa — payment_success.php (individual)',
        ]);
        break;

    case 'grupal':
        payment_success_render([
            'variant' => 'success',
            'page_title' => 'Pago múltiple exitoso',
            'badge' => 'Pago múltiple confirmado',
            'title' => '¡Pago grupal exitoso!',
            'subtitle' => 'Gracias, José Antonio. Todos tus servicios quedaron renovados y activos.',
            'wide' => true,
            'highlight' => [
                'label' => 'Total pagado',
                'value' => '$4,850.00 MXN',
                'meta' => '3 servicio(s) · ID PG-20260714-A1B2',
            ],
            'metrics' => [
                ['label' => 'Servicios', 'value' => '3', 'icon' => 'fas fa-layer-group'],
                ['label' => 'Transacción', 'value' => 'PG-20260714-A1B2', 'icon' => 'fas fa-fingerprint'],
                ['label' => 'Fecha', 'value' => date('d/m/Y H:i'), 'icon' => 'fas fa-calendar-check'],
            ],
            'services' => [
                ['tipo' => 'Hosting', 'nombre' => 'conlineweb.com', 'monto' => 2500, 'nueva_fecha' => '2027-07-14', 'currency' => 'MXN'],
                ['tipo' => 'Dominio', 'nombre' => 'miempresa.mx', 'monto' => 850, 'nueva_fecha' => '2027-03-20', 'currency' => 'MXN'],
                ['tipo' => 'Servicio', 'nombre' => 'Mantenimiento web mensual', 'monto' => 1500, 'currency' => 'MXN'],
            ],
            'note' => 'Enviamos un <strong>correo de confirmación</strong> con el detalle de cada servicio renovado.',
            'actions' => [
                ['label' => 'Ver mis pagos', 'url' => 'https://cliente.conlineweb.com/pagos.php', 'style' => 'primary', 'icon' => 'fas fa-receipt'],
                ['label' => 'Portal cliente', 'url' => 'https://cliente.conlineweb.com/', 'style' => 'ghost', 'icon' => 'fas fa-user'],
            ],
            'footnote' => 'Vista previa — payment_success_grupal.php',
        ]);
        break;

    case 'whatsapp':
        payment_success_render([
            'variant' => 'success',
            'badge' => 'Pago confirmado',
            'title' => '¡Pago exitoso!',
            'subtitle' => 'Tu pago fue procesado correctamente. Nuestro equipo ya comenzará a trabajar en tu solicitud.',
            'highlight' => [
                'label' => 'Número de ticket',
                'value' => '#1042',
                'meta' => 'Guarda este número para dar seguimiento',
            ],
            'details' => [
                ['label' => 'Estado', 'value' => 'Acreditado'],
                ['label' => 'Fecha', 'value' => date('d/m/Y H:i')],
            ],
            'note' => 'Recibirás una confirmación por <strong>WhatsApp</strong> con los detalles de tu solicitud.',
            'actions' => [
                ['label' => 'Volver al sitio', 'url' => 'https://conlineweb.com', 'style' => 'primary', 'icon' => 'fas fa-arrow-right'],
            ],
            'footnote' => 'Los días hábiles de trabajo comienzan a partir de hoy. · Vista previa WhatsApp',
        ]);
        break;

    case 'info':
        payment_success_render([
            'variant' => 'info',
            'badge' => 'Ya pagado',
            'title' => 'Transacción registrada',
            'subtitle' => 'Este servicio ya se pagó anteriormente. No se realizó ningún cargo adicional.',
            'confetti' => false,
            'actions' => [
                ['label' => 'Mis pagos', 'url' => 'https://cliente.conlineweb.com/pagos.php', 'style' => 'primary', 'icon' => 'fas fa-receipt'],
            ],
            'footnote' => 'Vista previa — pago duplicado / ya registrado',
        ]);
        break;

    case 'error':
        payment_success_render([
            'variant' => 'error',
            'page_title' => 'Error en el pago',
            'badge' => 'Pago no completado',
            'title' => 'Error al procesar el pago',
            'subtitle' => 'No pudimos confirmar tu pago. Si el cargo apareció en tu tarjeta, contáctanos de inmediato.',
            'details' => [['label' => 'Detalle', 'value' => 'Ejemplo: No se encontraron pagos asociados a esta sesión.']],
            'confetti' => false,
            'actions' => [
                ['label' => 'Volver a pagos', 'url' => 'https://cliente.conlineweb.com/pagos.php', 'style' => 'primary', 'icon' => 'fas fa-arrow-left'],
                ['label' => 'Contactar soporte', 'url' => 'https://wa.me/524771181285', 'style' => 'accent', 'icon' => 'fab fa-whatsapp'],
            ],
            'footnote' => 'Vista previa — pantalla de error',
        ]);
        break;
}
