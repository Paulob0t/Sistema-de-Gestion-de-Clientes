<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Habilitar logging de errores
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../conn.php';

// Cargar Composer autoload
try {
    require_once __DIR__ . '/../../vendor/autoload.php';
    error_log("[generar_pago_stripe] ✓ Autoload cargado correctamente");
} catch (Exception $e) {
    error_log("[generar_pago_stripe] ERROR al cargar autoload: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al cargar dependencias: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Cargar librería de Stripe
try {
    require_once __DIR__ . '/../stripe-php/init.php';
    error_log("[generar_pago_stripe] ✓ Librería Stripe cargada");
} catch (Exception $e) {
    error_log("[generar_pago_stripe] ERROR al cargar Stripe: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al cargar librería Stripe: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Ahora sí podemos importar Dotenv
use Dotenv\Dotenv;

// Cargar .env
try {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->load();
    error_log("[generar_pago_stripe] ✓ Variables de entorno cargadas");
} catch (Exception $e) {
    error_log("[generar_pago_stripe] ERROR al cargar .env: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al cargar configuración: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Configurar Stripe con la clave secreta del .env
try {
    if (empty($_ENV['STRIPE_SECRET_KEY'])) {
        throw new Exception("STRIPE_SECRET_KEY no está configurada en .env");
    }
    \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
    error_log("[generar_pago_stripe] ✓ Stripe configurado correctamente");
} catch (Exception $e) {
    error_log("[generar_pago_stripe] ERROR al configurar Stripe: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al configurar Stripe: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Leer el cuerpo de la petición
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// LOG: Datos recibidos
error_log("[generar_pago_stripe] === INICIO DE SOLICITUD ===");
error_log("[generar_pago_stripe] Datos RAW recibidos: " . $input);
error_log("[generar_pago_stripe] Datos parseados: " . json_encode($data, JSON_UNESCAPED_UNICODE));

// Validar que se recibieron datos
if (!$data) {
    error_log("[generar_pago_stripe] ERROR: Datos inválidos o no proporcionados");
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Datos inválidos o no proporcionados'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Obtener parámetros
$solicitud_id = isset($data['solicitud_id']) ? (int) $data['solicitud_id'] : 0;
$monto = isset($data['monto']) ? (float) $data['monto'] : 0;
$descripcion = isset($data['descripcion']) ? trim($data['descripcion']) : '';
$client_email = isset($data['client_email']) ? trim($data['client_email']) : null;

// LOG: Parámetros extraídos
error_log("[generar_pago_stripe] Parámetros extraídos:");
error_log("[generar_pago_stripe]   - solicitud_id: " . $solicitud_id);
error_log("[generar_pago_stripe]   - monto: " . $monto);
error_log("[generar_pago_stripe]   - descripcion: " . $descripcion);
error_log("[generar_pago_stripe]   - client_email: " . ($client_email ?: 'NULL'));

// Validaciones
if ($solicitud_id <= 0) {
    error_log("[generar_pago_stripe] ERROR: ID de solicitud inválido");
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'ID de solicitud inválido'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($monto <= 0) {
    error_log("[generar_pago_stripe] ERROR: Monto inválido");
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'El monto debe ser mayor a cero'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($descripcion)) {
    error_log("[generar_pago_stripe] ERROR: La descripción es obligatoria");
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'La descripción es obligatoria'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Verificar que la solicitud existe y es válida
    $stmt = $conn->prepare("
        SELECT id, metodo_pago, pagado, stripe_payment_url 
        FROM solicitud_whatsapp 
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        error_log("[generar_pago_stripe] ERROR SQL prepare: " . $conn->error);
        throw new Exception("Error al preparar consulta: " . $conn->error);
    }

    $stmt->bind_param("i", $solicitud_id);

    if (!$stmt->execute()) {
        error_log("[generar_pago_stripe] ERROR SQL execute: " . $stmt->error);
        $stmt->close();
        throw new Exception("Error al ejecutar consulta: " . $stmt->error);
    }

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        error_log("[generar_pago_stripe] ERROR: Solicitud no encontrada: " . $solicitud_id);
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Solicitud no encontrada'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $solicitud = $result->fetch_assoc();
    $stmt->close();

    // Verificar que el método de pago sea tarjeta
    if ($solicitud['metodo_pago'] !== 'tarjeta') {
        error_log("[generar_pago_stripe] ERROR: Método de pago no es tarjeta: " . $solicitud['metodo_pago']);
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'El método de pago de esta solicitud no es tarjeta'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Si ya existe un enlace de pago, devolverlo
    if (!empty($solicitud['stripe_payment_url'])) {
        error_log("[generar_pago_stripe] ℹ️ Ya existe enlace de pago para esta solicitud");
        echo json_encode([
            'success' => true,
            'payment_url' => $solicitud['stripe_payment_url'],
            'message' => 'Enlace de pago ya existente'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Crear Checkout Session para generar el enlace de pago
    error_log("[generar_pago_stripe] Creando Checkout Session...");
    error_log("[generar_pago_stripe] Monto: $" . $monto . " MXN (" . ((int) ($monto * 100)) . " centavos)");
  
    try {
        $checkoutSession = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => 'mxn',
                        'product_data' => [
                            'name' => $descripcion,
                        ],
                        'unit_amount' => (int)($monto * 100),
                        // Monto fijo de 11 MXN para pruebas
                    ],
                    'quantity' => 1,
                ]
            ],
            'mode' => 'payment',
            'success_url' => 'https://adm.conlineweb.com/payment_success.php?session_id={CHECKOUT_SESSION_ID}&solicitud_id=' . $solicitud_id,
            'cancel_url' => 'https://adm.conlineweb.com/payment_cancel.php?solicitud_id=' . $solicitud_id,
            'metadata' => [
                'solicitud_id' => $solicitud_id,
            ],
            'customer_email' => $client_email ?: null,
        ]);
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log("[generar_pago_stripe] ERROR al crear Checkout Session: " . $e->getMessage());
        error_log("[generar_pago_stripe] Stripe Error Type: " . get_class($e));
        error_log("[generar_pago_stripe] Stripe Error Code: " . $e->getStripeCode());
        throw $e;
    }

    $payment_url = $checkoutSession->url;
    $stripe_payment_id = $checkoutSession->id;

    error_log("[generar_pago_stripe] ✓ Checkout Session creada: " . $stripe_payment_id);
    error_log("[generar_pago_stripe] ✓ URL de pago: " . $payment_url);

    // Actualizar la solicitud con el enlace de pago
    $stmt_update = $conn->prepare("
        UPDATE solicitud_whatsapp 
        SET stripe_payment_url = ?,
            stripe_payment_id = ?
        WHERE id = ?
    ");
    $stmt_update->bind_param("ssi", $payment_url, $stripe_payment_id, $solicitud_id);

    if (!$stmt_update->execute()) {
        error_log("[generar_pago_stripe] ERROR al actualizar solicitud: " . $stmt_update->error);
        throw new Exception("Error al actualizar la solicitud con el enlace de pago: " . $stmt_update->error);
    }
    $stmt_update->close();

    error_log("[generar_pago_stripe] ✓ Solicitud actualizada con enlace de pago");

    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'payment_url' => $payment_url,
        'stripe_session_id' => $stripe_payment_id,
        'solicitud_id' => $solicitud_id,
        'monto' => $monto,
        'message' => 'Enlace de pago generado exitosamente'
    ], JSON_UNESCAPED_UNICODE);

} catch (\Stripe\Exception\ApiErrorException $e) {
    error_log("[generar_pago_stripe] ERROR STRIPE: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al generar el enlace de pago con Stripe: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log("[generar_pago_stripe] ERROR CATCH: " . $e->getMessage());
    error_log("[generar_pago_stripe] Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al procesar la solicitud: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>