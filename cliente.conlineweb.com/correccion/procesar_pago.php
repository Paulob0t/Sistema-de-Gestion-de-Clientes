<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Configuración de logging
$logFile = 'stripe_payments.log';
$logMessage = function($message) use ($logFile) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
};

// Registrar inicio del proceso
$logMessage("Iniciando procesamiento de pago");

try {
    // Incluir la librería de Stripe
    require_once('stripe-php/init.php');

    // Registrar que la librería se cargó
    $logMessage("Librería Stripe cargada");

    // Validar datos de entrada obligatorios
    $requiredFields = ['id', 'nombre', 'moneda', 'costo', 'concepto', 'correo'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        $errorMsg = "Campos requeridos faltantes: " . implode(', ', $missingFields);
        $logMessage("ERROR: $errorMsg");
        
        echo json_encode([
            'success' => false,
            'error' => $errorMsg,
            'missing_fields' => $missingFields
        ]);
        exit;
    }

    // Validar que el costo sea un número válido
    if (!is_numeric($_POST['costo']) || $_POST['costo'] <= 0) {
        $errorMsg = "El costo debe ser un número positivo";
        $logMessage("ERROR: $errorMsg. Valor recibido: " . $_POST['costo']);
        
        echo json_encode([
            'success' => false,
            'error' => $errorMsg,
            'received_value' => $_POST['costo']
        ]);
        exit;
    }

    // Registrar datos recibidos (sin información sensible)
    $logMessage("Datos recibidos: " . json_encode([
        'id' => $_POST['id'],
        'nombre' => $_POST['nombre'],
        'moneda' => $_POST['moneda'],
        'costo' => $_POST['costo'],
        'concepto' => $_POST['concepto'],
        'correo' => $_POST['correo']
    ]));

    // Establecer tu clave secreta de Stripe desde variable de entorno
    $stripeSecret = getenv('STRIPE_SECRET_KEY') ?: ($_ENV['STRIPE_SECRET_KEY'] ?? '');
    \Stripe\Stripe::setApiKey($stripeSecret);

    // Convertir monto a centavos
    $monto_centavos = round($_POST['costo'] * 100);
    $logMessage("Monto en centavos: $monto_centavos");

    // Crear la sesión de Checkout en Stripe
    try {
        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => $_POST['moneda'],
                        'product_data' => [
                            'name' => $_POST['concepto'],
                        ],
                        'unit_amount' => $monto_centavos,
                    ],
                    'quantity' => 1,
                ],
            ],
            'mode' => 'payment',
            'customer_email' => $_POST['correo'],
            'success_url' => 'https://adm.conlineweb.com/payment_success.php?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => 'https://adm.conlineweb.com/payment_cancel.php',
        ]);

        
        // Enviar la URL de la sesión de pago a la respuesta
        echo json_encode([
            'success' => true,
            'session_url' => $session->url,
            'session_id' => $session->id,
        ]);
        
    } catch (\Stripe\Exception\ApiErrorException $e) {
        $errorMsg = "Error al crear sesión de Stripe: " . $e->getMessage();
        $logMessage("ERROR: $errorMsg");
        
        echo json_encode([
            'success' => false,
            'error' => $errorMsg,
            'error_type' => get_class($e),
            'stripe_error' => $e->getJsonBody()['error'] ?? null
        ]);
    }

} catch (Exception $e) {
    $errorMsg = "Error inesperado: " . $e->getMessage();
    $logMessage("ERROR GRAVE: $errorMsg");
    
    echo json_encode([
        'success' => false,
        'error' => $errorMsg,
        'error_type' => get_class($e),
        'trace' => $e->getTraceAsString()
    ]);
}
?>