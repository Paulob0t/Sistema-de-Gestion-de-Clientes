<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: https://cliente.conlineweb.com");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

// Incluir autoload de Composer
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Cargar .env que está en tu proyecto
$dotenv = Dotenv::createImmutable(__DIR__ . '/..'); 
$dotenv->load();

// Configuración de logging
$logFile = 'stripe_payments.log';
$logMessage = function($message) use ($logFile) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
};

// Registrar inicio del proceso
$logMessage("Iniciando procesamiento de pago grupal");

try {
    // Incluir la librería de Stripe
    require_once('stripe-php/init.php');
    
    // Registrar que la librería se cargó
    $logMessage("Librería Stripe cargada");

    // Obtener datos JSON
    $jsonData = file_get_contents('php://input');
    $data = json_decode($jsonData, true);
    
    if (!$data) {
        throw new Exception("No se recibieron datos válidos");
    }

    // Validar que se recibió un array de pagos
    if (!isset($data['pagos']) || !is_array($data['pagos']) || empty($data['pagos'])) {
        throw new Exception("Se requiere un array de pagos válido");
    }

    // Validar que todos los pagos tengan los campos requeridos
    $pagos = $data['pagos'];
    foreach ($pagos as $index => $pago) {
        $requiredFields = ['id', 'monto', 'concepto'];
        foreach ($requiredFields as $field) {
            if (empty($pago[$field])) {
                throw new Exception("Falta el campo '$field' en el pago #" . ($index + 1));
            }
        }
    }

    // Validar correo y datos generales
    if (empty($data['correo'])) {
        throw new Exception("El correo del cliente es requerido");
    }

    // Registrar datos recibidos
    $logMessage("Pago grupal solicitado: " . count($pagos) . " servicios");
    $logMessage("Datos: " . json_encode($data));

    // Configurar Stripe
   // \Stripe\Stripe::setApiKey('sk_test_51JDaK3GHIyztsA2RQBa83FnkXiOPtY9o4OPbSbd0B9A2K847iXpp88bTvCuXekxNrcj4BiP2JzCmBqd1j5NTC2EL009H4CjAmo');

    \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

    // Incluir conexión a la base de datos
    require_once('conn.php');

    // Calcular total y crear line items
    $lineItems = [];
    $totalMonto = 0;
    $currency = null;
    $ids_pagos = [];
    
    foreach ($pagos as $pago) {
        $monto = floatval($pago['monto']);
        $pagoId = intval($pago['id']);
        $concepto = $pago['concepto'];
        $moneda = isset($pago['currency']) ? strtolower($pago['currency']) : 'mxn';
        
        // Validar que todos los pagos tengan la misma moneda
        if ($currency === null) {
            $currency = $moneda;
        } elseif ($currency !== $moneda) {
            throw new Exception("Todos los pagos deben tener la misma moneda");
        }
        
        $totalMonto += $monto;
        $ids_pagos[] = $pagoId;
        
        // Agregar item al checkout
        $lineItems[] = [
            'price_data' => [
                'currency' => $moneda,
                'product_data' => [
                    'name' => $concepto,
                ],
                'unit_amount' => round($monto * 100), // Convertir a centavos
            ],
            'quantity' => 1,
        ];
    }

    $logMessage("Total a pagar: $totalMonto $currency");

    // Crear ID único para este pago grupal
    $pago_grupal_id = uniqid("pago_grupal_");
    
    // Crear la sesión de Checkout en Stripe
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => $lineItems,
        'mode' => 'payment',
        'customer_email' => $data['correo'],
        'success_url' => 'https://adm.conlineweb.com/payment_success_grupal.php?session_id={CHECKOUT_SESSION_ID}&pago_grupal_id=' . $pago_grupal_id,
        'cancel_url' => 'https://adm.conlineweb.com/payment_cancel.php',
        'metadata' => [
            'pago_grupal_id' => $pago_grupal_id,
            'ids_pagos' => implode(',', $ids_pagos),
            'cantidad_servicios' => count($pagos)
        ]
    ]);

    $logMessage("Sesión de Stripe creada exitosamente. Session ID: " . $session->id);

    // Actualizar los registros de pago con el session_id y pago_grupal_id
    foreach ($ids_pagos as $pagoId) {
        $stmt = $conn->prepare("UPDATE pagos SET session_id = ?, pago_grupal_id = ? WHERE id = ?");
        $stmt->bind_param("ssi", $session->id, $pago_grupal_id, $pagoId);
        
        if (!$stmt->execute()) {
            $logMessage("ERROR: No se pudo guardar session_id para pago ID: $pagoId");
        }
    }
    
    $logMessage("Session IDs guardados para " . count($ids_pagos) . " pagos");

    // Enviar la URL de la sesión de pago a la respuesta
    echo json_encode([
        'success' => true,
        'session_url' => $session->url,
        'session_id' => $session->id,
        'pago_grupal_id' => $pago_grupal_id,
        'total' => $totalMonto,
        'currency' => $currency,
        'cantidad_servicios' => count($pagos)
    ]);

} catch (\Stripe\Exception\ApiErrorException $e) {
    $errorMsg = "Error al crear sesión de Stripe: " . $e->getMessage();
    $logMessage("ERROR STRIPE: $errorMsg");
    
    echo json_encode([
        'success' => false,
        'error' => $errorMsg,
        'error_type' => get_class($e)
    ]);
} catch (Exception $e) {
    $errorMsg = "Error: " . $e->getMessage();
    $logMessage("ERROR: $errorMsg");
    
    echo json_encode([
        'success' => false,
        'error' => $errorMsg
    ]);
}
?>
