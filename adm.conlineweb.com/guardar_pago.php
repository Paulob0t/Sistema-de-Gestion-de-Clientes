<?php
/**
 * Guarda pago(s) desde detalle_cliente (único o serie recurrente).
 * Compatible local (MAMP) y cPanel: no depende de Composer/Dotenv.
 * Stripe y correo: si fallan, los pagos ya creados se conservan y se reporta en JSON.
 */
require_once __DIR__ . '/auth_middleware.php';

if (!defined('CW_JSON_API')) {
    define('CW_JSON_API', true);
}
if (!defined('CW_CONN_SOFT')) {
    define('CW_CONN_SOFT', true);
}

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
@ini_set('default_socket_timeout', '8');
ob_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

error_log('========================================');
error_log('INICIO DE guardar_pago.php');
error_log('========================================');

/**
 * @param array<string, mixed> $payload
 */
function cw_guardar_pago_json(array $payload, int $httpCode = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function resolveStripeSecretKey(string $sistema): string
{
    if ($sistema === 'conlineweb') {
        $env = (string) ($_ENV['STRIPE_SECRET_KEY'] ?? getenv('STRIPE_SECRET_KEY') ?: '');
        if ($env !== '') {
            return $env;
        }
    } else {
        $hostproEnv = (string) (
            $_ENV['STRIPE_SECRET_KEY_HOSTPRO']
            ?? $_ENV['HOSTPRO_STRIPE_SECRET_KEY']
            ?? getenv('STRIPE_SECRET_KEY_HOSTPRO')
            ?: ''
        );
        if ($hostproEnv !== '') {
            return $hostproEnv;
        }
    }

    $pathsToTry = [
        __DIR__ . '/stripe_credentials',
        __DIR__ . '/.stripe_credentials',
        dirname(__DIR__) . '/stripe_credentials',
        dirname(__DIR__) . '/.stripe_credentials',
    ];

    foreach ($pathsToTry as $credentialsFile) {
        if (!is_file($credentialsFile)) {
            continue;
        }
        $creds = @parse_ini_file($credentialsFile);
        if ($creds === false) {
            continue;
        }
        $isTest = filter_var($creds['STRIPE_TEST_MODE'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
        if ($sistema === 'hostingpro') {
            $key = $isTest
                ? ($creds['STRIPE_TEST_SECRET_KEY_HOSTPRO'] ?? $creds['STRIPE_TEST_SECRET_KEY'] ?? '')
                : ($creds['STRIPE_LIVE_SECRET_KEY_HOSTPRO'] ?? $creds['STRIPE_LIVE_SECRET_KEY'] ?? '');
        } else {
            $key = $isTest
                ? ($creds['STRIPE_TEST_SECRET_KEY'] ?? '')
                : ($creds['STRIPE_LIVE_SECRET_KEY'] ?? '');
        }
        if (!empty($key)) {
            return (string) $key;
        }
    }

    return '';
}

/**
 * @return array{success:bool, message?:string, error?:string}
 */
function enviarCorreoPagoDirecto(
    string $correo_destino,
    string $asunto,
    $monto,
    string $moneda,
    string $concepto,
    string $fecha_limite,
    string $session_url,
    string $sistema,
    string $nombre_cliente
): array {
    $monto_formateado = number_format((float) $monto, 2);

    try {
        if ($sistema === 'hostingpro' && function_exists('hostpro_template_pago_pendiente')) {
            $fecha_vencimiento = date('d/m/Y', strtotime($fecha_limite));
            $mensaje = hostpro_template_pago_pendiente(
                $nombre_cliente,
                $concepto,
                $monto_formateado,
                $moneda,
                $fecha_vencimiento,
                $session_url,
                'pago'
            );
            $mail = new PHPMailer(true);
            configure_phpmailer_by_system($mail, 'hostingpro');
            $mail->Timeout = 8;
            $mail->addAddress($correo_destino, $nombre_cliente);
            $mail->Subject = $asunto;
            $mail->isHTML(true);
            $mail->Body = $mensaje;
            $mail->send();
            return ['success' => true, 'message' => 'Correo enviado correctamente'];
        }

        require_once __DIR__ . '/includes/pago_email_template.php';
        $fecha_vencimiento = date('d/m/Y', strtotime($fecha_limite));
        $mensaje = pago_email_pendiente($concepto, $monto_formateado, $moneda, $fecha_vencimiento, $session_url);

        $mail = new PHPMailer(true);
        configure_phpmailer_by_system($mail, 'conlineweb');
        $mail->Timeout = 8;
        $mail->addAddress($correo_destino, $nombre_cliente);
        $mail->Subject = $asunto;
        $mail->isHTML(true);
        $mail->Body = $mensaje;
        $mail->CharSet = 'UTF-8';
        if (!$mail->send()) {
            return ['success' => false, 'error' => 'El correo no se pudo enviar'];
        }
        return ['success' => true, 'message' => 'Correo enviado correctamente'];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

try {
    // Solo la BD del sistema activo (evita colgarse 30s en HostingPro si no hace falta)
    $sistema = (isset($_POST['sistema']) && $_POST['sistema'] === 'hostingpro') ? 'hostingpro' : 'conlineweb';

    if ($sistema === 'hostingpro') {
        include __DIR__ . '/conn_hostingpro.php';
        if (!isset($conn_hp) || !($conn_hp instanceof mysqli)) {
            throw new RuntimeException('Sin conexión a HostingPro');
        }
        $conn = $conn_hp;
    } else {
        include __DIR__ . '/conn.php';
        if (!isset($conn) || !($conn instanceof mysqli)) {
            throw new RuntimeException('Sin conexión a ConlineWeb');
        }
    }

    require __DIR__ . '/PHPMailer/src/Exception.php';
    require __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require __DIR__ . '/PHPMailer/src/SMTP.php';
    include __DIR__ . '/smtp_config_helper.php';
    if (is_file(__DIR__ . '/hostpro_email_helper.php')) {
        include __DIR__ . '/hostpro_email_helper.php';
    }

    // Stripe SDK local (sin Composer)
    if (!is_file(__DIR__ . '/stripe-php/init.php')) {
        throw new RuntimeException('Falta stripe-php/init.php');
    }
    require_once __DIR__ . '/stripe-php/init.php';

    // Cargar .env solo si existe (opcional)
    foreach ([__DIR__, dirname(__DIR__)] as $envRoot) {
        $envFile = $envRoot . '/.env';
        if (!is_file($envFile)) {
            continue;
        }
        $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }
            [$k, $v] = array_map('trim', explode('=', $line, 2));
            $v = trim($v, " \t\"'");
            if ($k !== '' && !isset($_ENV[$k])) {
                $_ENV[$k] = $v;
                putenv($k . '=' . $v);
            }
        }
        break;
    }

    error_log('🔧 Sistema activo: ' . $sistema);

    if (!isset($_POST['cliente_id'], $_POST['monto'], $_POST['currency'], $_POST['concepto'])) {
        cw_guardar_pago_json(['success' => false, 'message' => 'Faltan datos del formulario'], 400);
    }

    $cliente_id = (int) $_POST['cliente_id'];
    $monto = (float) $_POST['monto'];
    $currency = (string) $_POST['currency'];
    $concepto = (string) $_POST['concepto'];
    $fecha_limite = (!empty($_POST['fecha_limite_pago']))
        ? (string) $_POST['fecha_limite_pago']
        : date('Y-m-d', strtotime('+3 days'));

    $frecuencia_pago = isset($_POST['frecuencia_pago']) ? (int) $_POST['frecuencia_pago'] : 0;
    $intervalo_dias = isset($_POST['intervalo_dias']) ? (int) $_POST['intervalo_dias'] : 0;
    $num_repeticiones_raw = $_POST['num_repeticiones'] ?? null;
    $num_repeticiones = ($num_repeticiones_raw === null || $num_repeticiones_raw === '')
        ? null
        : max(0, (int) $num_repeticiones_raw);

    // Tope práctico en local/cPanel para no saturar ni timeout
    if ($num_repeticiones !== null && $num_repeticiones > 36) {
        $num_repeticiones = 36;
    }

    error_log("📋 Frecuencia: $frecuencia_pago · intervalo: $intervalo_dias · reps: " . ($num_repeticiones === null ? 'indef' : $num_repeticiones));

    require_once __DIR__ . '/includes/cw_pago_recurrencia.php';
    cw_pago_ensure_recurrencia_columns($conn);

    $serie = cw_pago_crear_serie_manual(
        $conn,
        $cliente_id,
        $monto,
        $currency,
        $concepto,
        $fecha_limite,
        $sistema,
        $frecuencia_pago,
        $intervalo_dias,
        $num_repeticiones
    );

    if (empty($serie['ok']) || empty($serie['primer_id'])) {
        cw_guardar_pago_json([
            'success' => false,
            'message' => $serie['mensaje'] ?? 'Error al guardar el pago / serie recurrente',
            'db_error' => $conn->error,
        ], 500);
    }

    $pago_id = (int) $serie['primer_id'];
    $serie_ids = $serie['ids'] ?? [$pago_id];
    error_log('🆔 Serie OK: ' . count($serie_ids) . ' pago(s), primero=' . $pago_id);

    $stmt_cliente = $conn->prepare('SELECT nombre_contacto, correo FROM clientes WHERE id = ?');
    if (!$stmt_cliente) {
        cw_guardar_pago_json(['success' => false, 'message' => 'Error al consultar cliente', 'pago_id' => $pago_id], 500);
    }
    $stmt_cliente->bind_param('i', $cliente_id);
    $stmt_cliente->execute();
    $cliente = $stmt_cliente->get_result()->fetch_assoc();
    $stmt_cliente->close();

    if (!$cliente) {
        cw_guardar_pago_json(['success' => false, 'message' => 'Cliente no encontrado', 'pago_id' => $pago_id], 404);
    }

    $sessionId = '';
    $sessionUrl = '';
    $stripeWarning = '';
    $resultado_correo = ['success' => false, 'error' => 'Correo no intentado'];

    $stripeSecretKey = resolveStripeSecretKey($sistema);
    if ($stripeSecretKey === '') {
        $stripeWarning = 'Pagos guardados. Falta clave Stripe en este entorno (local/cPanel); genera el link con “Enviar correo” después.';
        error_log('⚠️ Sin clave Stripe para ' . $sistema);
    } else {
        try {
            \Stripe\Stripe::setApiKey($stripeSecretKey);
            // Evitar colgar FastCGI 30s en local
            if (class_exists('\Stripe\HttpClient\CurlClient')) {
                $curl = new \Stripe\HttpClient\CurlClient([CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 5]);
                \Stripe\ApiRequestor::setHttpClient($curl);
            }

            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => $currency,
                        'product_data' => ['name' => $concepto],
                        'unit_amount' => (int) round($monto * 100),
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'customer_email' => $cliente['correo'],
                'success_url' => 'https://adm.conlineweb.com/payment_success.php?session_id={CHECKOUT_SESSION_ID}&sistema=' . $sistema,
                'cancel_url' => 'https://adm.conlineweb.com/payment_cancel.php?sistema=' . $sistema,
            ]);
            $sessionId = (string) $session->id;
            $sessionUrl = (string) $session->url;

            $stmt_update = $conn->prepare('UPDATE pagos SET session_id = ? WHERE id = ?');
            if ($stmt_update) {
                $stmt_update->bind_param('si', $sessionId, $pago_id);
                $stmt_update->execute();
                $stmt_update->close();
            }

            $resultado_correo = enviarCorreoPagoDirecto(
                (string) $cliente['correo'],
                'Pago Pendiente - ' . $concepto,
                $monto,
                $currency,
                $concepto,
                $fecha_limite,
                $sessionUrl,
                $sistema,
                (string) $cliente['nombre_contacto']
            );
        } catch (Throwable $e) {
            $stripeWarning = 'Pagos guardados, pero Stripe/correo falló: ' . $e->getMessage();
            error_log('⚠️ Stripe/correo: ' . $e->getMessage());
        }
    }

    $msgSerie = count($serie_ids) > 1
        ? (' Se generaron ' . count($serie_ids) . ' pagos en la serie.')
        : (!empty($serie['indefinido'])
            ? ' Recurrente indefinido: el siguiente se crea con generar_pago al acercarse el vencimiento.'
            : '');

    $okMail = !empty($resultado_correo['success']);
    $message = 'Pago(s) guardados correctamente.' . $msgSerie;
    if ($stripeWarning !== '') {
        $message .= ' ' . $stripeWarning;
    } elseif ($okMail) {
        $message .= ' Correo enviado del primer pago.';
    } else {
        $message .= ' Correo no enviado: ' . ($resultado_correo['error'] ?? 'desconocido');
    }

    cw_guardar_pago_json([
        'success' => true,
        'message' => $message,
        'pago_id' => $pago_id,
        'serie_ids' => $serie_ids,
        'serie_total' => count($serie_ids),
        'session_id' => $sessionId,
        'session_url' => $sessionUrl,
        'correo_status' => $resultado_correo,
        'stripe_warning' => $stripeWarning,
    ]);
} catch (Throwable $e) {
    error_log('ERROR guardar_pago: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    cw_guardar_pago_json([
        'success' => false,
        'message' => 'Error al guardar el pago: ' . $e->getMessage(),
    ], 500);
}
