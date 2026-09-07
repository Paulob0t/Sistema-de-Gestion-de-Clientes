<?php
/**
 * Pago cancelado / rechazado en Stripe Checkout.
 * Es el cancel_url de procesar_pago, guardar_pago, procesar_pago_grupal
 * y api/generar_pago_stripe.
 */
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
date_default_timezone_set('America/Mexico_City');

require_once __DIR__ . '/includes/payment_success_view.php';

$sistema = isset($_GET['sistema']) ? (string) $_GET['sistema'] : 'conlineweb';
$solicitudId = isset($_GET['solicitud_id']) ? (int) $_GET['solicitud_id'] : 0;

$details = [
    ['label' => 'Estado', 'value' => 'No completado'],
    ['label' => 'Fecha', 'value' => date('d/m/Y H:i')],
];
if ($solicitudId > 0) {
    $details[] = ['label' => 'Solicitud', 'value' => '#' . $solicitudId];
}

$actions = [];
if ($solicitudId > 0) {
    $actions[] = ['label' => 'Reintentar por WhatsApp', 'url' => 'https://wa.me/524771181285', 'style' => 'primary', 'icon' => 'fab fa-whatsapp'];
    $actions[] = ['label' => 'Ir a ConlineWeb', 'url' => 'https://conlineweb.com', 'style' => 'ghost', 'icon' => 'fas fa-home'];
} else {
    $actions[] = ['label' => 'Reintentar pago', 'url' => 'https://cliente.conlineweb.com/pagos.php', 'style' => 'primary', 'icon' => 'fas fa-redo'];
    $actions[] = ['label' => 'Contactar soporte', 'url' => 'https://wa.me/524771181285', 'style' => 'accent', 'icon' => 'fab fa-whatsapp'];
}

payment_success_render([
    'variant' => 'warning',
    'page_title' => 'Pago no completado',
    'badge' => 'Pago cancelado',
    'title' => 'Tu pago no se completó',
    'subtitle' => 'Cancelaste el proceso o la transacción fue rechazada. <strong>No se realizó ningún cargo</strong> a tu tarjeta.',
    'details' => $details,
    'note' => 'Si crees que fue un error, verifica que tu tarjeta tenga fondos y que permita <strong>compras en línea</strong>, y vuelve a intentarlo.',
    'actions' => $actions,
    'footnote' => 'Tu servicio permanece sin cambios hasta que se registre un pago.',
    'confetti' => false,
]);
