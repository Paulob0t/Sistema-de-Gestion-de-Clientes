<?php
// Configuración inicial y manejo de errores
require_once('stripe-php/init.php');
header('Content-Type: text/html; charset=UTF-8');
date_default_timezone_set('America/Mexico_City');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/conn.php';

// Incluir autoload de Composer
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Cargar .env que está en tu proyecto
$dotenv = Dotenv::createImmutable(__DIR__ . '/..'); 
$dotenv->load();

if (!isset($conn) || !$conn instanceof mysqli) {
    throw new Exception("No se pudo establecer conexión con la base de datos.");
}

// Limpiar buffers de salida
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer', false)) {
    require_once __DIR__ . '/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';
}
require_once __DIR__ . '/includes/pago_email_template.php';
require_once __DIR__ . '/includes/payment_success_view.php';
require_once __DIR__ . '/includes/cw_pago_renovacion.php';

// Configuración de Stripe
//\Stripe\Stripe::setApiKey('sk_test_51JDaK3GHIyztsA2RQBa83FnkXiOPtY9o4OPbSbd0B9A2K847iXpp88bTvCuXekxNrcj4BiP2JzCmBqd1j5NTC2EL009H4CjAmo');

Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

// Obtener session_id y pago_grupal_id de la URL
$session_id = $_GET['session_id'] ?? null;
$pago_grupal_id = $_GET['pago_grupal_id'] ?? null;

if (!$session_id || !$pago_grupal_id) {
    payment_success_render([
        'variant' => 'error',
        'page_title' => 'Enlace inválido',
        'badge' => 'Datos incompletos',
        'title' => 'No se recibió información del pago',
        'subtitle' => 'El enlace de confirmación no es válido o ya expiró. Regresa al portal e intenta de nuevo.',
        'confetti' => false,
        'actions' => [
            ['label' => 'Ir a mis pagos', 'url' => 'https://cliente.conlineweb.com/pagos.php', 'style' => 'primary', 'icon' => 'fas fa-receipt'],
        ],
    ]);
}

try {
    // Obtener detalles de la sesión de Stripe
    $session = \Stripe\Checkout\Session::retrieve($session_id);
    
    if ($session->payment_status !== 'paid') {
        throw new Exception("El pago no ha sido completado");
    }

    // Obtener todos los pagos asociados a este pago grupal
    $stmt = $conn->prepare("SELECT * FROM pagos WHERE pago_grupal_id = ? AND session_id = ?");
    $stmt->bind_param("ss", $pago_grupal_id, $session_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("No se encontraron pagos asociados");
    }

    $pagos = $result->fetch_all(MYSQLI_ASSOC);
    $fecha_actual = date('Y-m-d');
    $hora_actual = date('H:i:s');
    
    // Array para almacenar información de los servicios actualizados
    $servicios_actualizados = [];
    $total_pagado = 0;
    $cliente_id = null;
    $correo_cliente = null;
    
    // Actualizar cada pago (idempotente: si ya estaba pagado, no renueva de nuevo)
    foreach ($pagos as $pago) {
        $pago_id = $pago['id'];
        $tipo_servicio = $pago['tipo_servicio'];
        $id_servicio = $pago['id_servicio'];
        $monto = floatval($pago['monto']);
        $total_pagado += $monto;
        
        if ($cliente_id === null) {
            $cliente_id = $pago['id_clie'];
        }

        if ((int) $pago['estatus'] === 1) {
            continue;
        }
        
        // Actualizar el estatus del pago
        $stmt_update = $conn->prepare("UPDATE pagos SET 
            estatus = 1, 
            fecha_pago = ?, 
            hora_pago = ?,
            forma_pago = 1
            WHERE id = ? AND estatus = 0");
        $stmt_update->bind_param("ssi", $fecha_actual, $hora_actual, $pago_id);
        $stmt_update->execute();
        if ($stmt_update->affected_rows < 1) {
            $stmt_update->close();
            continue;
        }
        $stmt_update->close();

        // Actualizar las fechas de los servicios
        if ($tipo_servicio == 1 || $tipo_servicio == 2) {
            $ren = cw_renovar_servicio($conn, (int) $tipo_servicio, (int) $id_servicio);
            if (!empty($ren['ok']) && !empty($ren['renewed'])) {
                $nombre_servicio = '';
                if ((int) $tipo_servicio === 1) {
                    $qh = $conn->prepare('SELECT nom_host FROM hosting WHERE id_orden = ? LIMIT 1');
                    $qh->bind_param('i', $id_servicio);
                    $qh->execute();
                    $nombre_servicio = (string) ($qh->get_result()->fetch_assoc()['nom_host'] ?? 'Hosting');
                    $qh->close();
                    $tipoLabel = 'Hosting';
                } else {
                    $qd = $conn->prepare('SELECT url_dominio FROM dominios WHERE id_dominio = ? LIMIT 1');
                    $qd->bind_param('i', $id_servicio);
                    $qd->execute();
                    $nombre_servicio = (string) ($qd->get_result()->fetch_assoc()['url_dominio'] ?? 'Dominio');
                    $qd->close();
                    $tipoLabel = 'Dominio';
                }
                $servicios_actualizados[] = [
                    'tipo' => $tipoLabel,
                    'nombre' => $nombre_servicio,
                    'nueva_fecha' => $ren['fecha_despues'],
                    'monto' => $monto
                ];
            }
        } else {
            // Servicio manual
            $servicios_actualizados[] = [
                'tipo' => 'Servicio',
                'nombre' => $pago['concepto'],
                'monto' => $monto
            ];
        }
    }

    $cliente = [];
    // Obtener información del cliente
    if ($cliente_id) {
        $stmt_cliente = $conn->prepare("SELECT nombre_contacto, correo FROM clientes WHERE id = ?");
        $stmt_cliente->bind_param("i", $cliente_id);
        $stmt_cliente->execute();
        $cliente_result = $stmt_cliente->get_result();
        
        if ($cliente_result->num_rows > 0) {
            $cliente = $cliente_result->fetch_assoc();
            $correo_cliente = $cliente['correo'];
        }
    }

    // Enviar correo de confirmación al cliente
    if ($correo_cliente) {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            $mail->isSMTP();
            $mail->SMTPAuth = true;
            $mail->SMTPSecure = "tls";
            $mail->Port = 587;
            $mail->Host = "smtp.gmail.com";
            $mail->Username = "servicios@conlineweb.com";
            $mail->Password = "wcglkgcxfebsauqo";
            $mail->setFrom("servicios@conlineweb.com", "Conlineweb");
            $mail->addAddress($correo_cliente);
            $mail->CharSet = "UTF-8";
            $mail->Encoding = "base64";
            $mail->isHTML(true);
            $mail->Subject = "Confirmación de pago múltiple - ConlineWeb";

            $lista_servicios = pago_email_servicios_list($servicios_actualizados);

            $htmlTemplate = pago_email_multiple(
                $total_pagado,
                $pagos[0]['currency'],
                count($servicios_actualizados),
                $pago_grupal_id,
                date('d/m/Y H:i:s'),
                $lista_servicios
            );

            $mail->Body = $htmlTemplate;
            $mail->send();
        } catch (Exception $e) {
            error_log("Error al enviar correo: " . $e->getMessage());
        }
    }

    $currency = strtoupper($pagos[0]['currency'] ?? 'MXN');
    $clienteNombre = $cliente['nombre_contacto'] ?? '';

    $servicesForView = [];
    foreach ($servicios_actualizados as $svc) {
        $servicesForView[] = [
            'tipo' => $svc['tipo'],
            'nombre' => $svc['nombre'],
            'monto' => $svc['monto'],
            'nueva_fecha' => $svc['nueva_fecha'] ?? null,
            'currency' => $currency,
        ];
    }

    payment_success_render([
        'variant' => 'success',
        'page_title' => 'Pago múltiple exitoso',
        'badge' => 'Pago múltiple confirmado',
        'title' => '¡Pago grupal exitoso!',
        'subtitle' => $clienteNombre
            ? 'Gracias, ' . $clienteNombre . '. Todos tus servicios quedaron renovados y activos.'
            : 'Todos tus servicios quedaron renovados y activos correctamente.',
        'wide' => true,
        'highlight' => [
            'label' => 'Total pagado',
            'value' => '$' . number_format($total_pagado, 2) . ' ' . $currency,
            'meta' => count($servicios_actualizados) . ' servicio(s) · ID ' . $pago_grupal_id,
        ],
        'metrics' => [
            ['label' => 'Servicios', 'value' => (string) count($servicios_actualizados), 'icon' => 'fas fa-layer-group'],
            ['label' => 'Transacción', 'value' => $pago_grupal_id, 'icon' => 'fas fa-fingerprint'],
            ['label' => 'Fecha', 'value' => date('d/m/Y H:i'), 'icon' => 'fas fa-calendar-check'],
        ],
        'services' => $servicesForView,
        'note' => 'Enviamos un <strong>correo de confirmación</strong> con el detalle de cada servicio renovado.',
        'actions' => [
            ['label' => 'Ver mis pagos', 'url' => 'https://cliente.conlineweb.com/pagos.php', 'style' => 'primary', 'icon' => 'fas fa-receipt'],
            ['label' => 'Portal cliente', 'url' => 'https://cliente.conlineweb.com/', 'style' => 'ghost', 'icon' => 'fas fa-user'],
        ],
        'footnote' => 'Conserva tu comprobante desde el portal en la pestaña Pagados.',
    ]);

} catch (Exception $e) {
    payment_success_render([
        'variant' => 'error',
        'page_title' => 'Error en el pago',
        'badge' => 'Pago no completado',
        'title' => 'Error al procesar el pago',
        'subtitle' => 'No pudimos confirmar tu pago múltiple. Si el cargo apareció en tu tarjeta, escríbenos por WhatsApp con tu referencia.',
        'details' => [['label' => 'Detalle', 'value' => $e->getMessage()]],
        'confetti' => false,
        'actions' => [
            ['label' => 'Volver a pagos', 'url' => 'https://cliente.conlineweb.com/pagos.php', 'style' => 'primary', 'icon' => 'fas fa-arrow-left'],
            ['label' => 'Contactar soporte', 'url' => 'https://wa.me/524771181285', 'style' => 'accent', 'icon' => 'fab fa-whatsapp'],
        ],
    ]);
}
