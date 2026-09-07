<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: https://cliente.conlineweb.com");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");






// Incluir autoload de Composer
require_once __DIR__ . '/../vendor/autoload.php'; // sube una carpeta hasta /home/usuario/vendor/

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
$logMessage("Iniciando procesamiento de pago");

// Sistema activo para seleccionar credenciales Stripe
$sistema = (isset($_POST['sistema']) && $_POST['sistema'] === 'hostingpro') ? 'hostingpro' : 'conlineweb';

function resolveStripeSecretKey($sistema, $logMessage) {
    // ConlineWeb: mantiene la llave actual
    if ($sistema === 'conlineweb') {
        return $_ENV['STRIPE_SECRET_KEY'] ?? '';
    }

    // HostingPro: prioridad a variable de entorno específica
    $hostproEnv = $_ENV['STRIPE_SECRET_KEY_HOSTPRO'] ?? ($_ENV['HOSTPRO_STRIPE_SECRET_KEY'] ?? '');
    if (!empty($hostproEnv)) {
        return $hostproEnv;
    }

    // Fallback: intentar múltiples rutas posibles
    $pathsToTry = [
        dirname(__DIR__) . '/stripe_credentials',
        dirname(__DIR__) . '/.stripe_credentials',
        dirname(dirname(__DIR__)) . '/stripe_credentials',
        dirname(dirname(__DIR__)) . '/.stripe_credentials',
    ];

    foreach ($pathsToTry as $credentialsFile) {
        $logMessage("Buscando credenciales en: " . $credentialsFile . " - existe: " . (file_exists($credentialsFile) ? 'SI' : 'NO'));
        if (file_exists($credentialsFile)) {
            $creds = parse_ini_file($credentialsFile);
            if ($creds !== false) {
                $isTest = filter_var($creds['STRIPE_TEST_MODE'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
                $key = $isTest
                    ? ($creds['STRIPE_TEST_SECRET_KEY'] ?? '')
                    : ($creds['STRIPE_LIVE_SECRET_KEY'] ?? '');
                if (!empty($key)) {
                    $logMessage("Credenciales encontradas en: " . $credentialsFile);
                    return $key;
                }
            }
        }
    }

    $logMessage("No se encontraron credenciales Stripe para HostingPro en ninguna ruta");
    return '';
}

try {
    // Incluir la librería de Stripe
    require_once('stripe-php/init.php');

    // Registrar que la librería se cargó
    $logMessage("Librería Stripe cargada");

    // Incluir conexión a la base de datos correcta según sistema
    require_once('conn.php');
    require_once('conn_hostingpro.php');
    $dbConn = ($sistema === 'hostingpro') ? $conn_hp : $conn;

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

    // Seleccionar clave Stripe según sistema
    $stripeSecretKey = resolveStripeSecretKey($sistema, $logMessage);
    if (empty($stripeSecretKey)) {
        throw new Exception('No hay clave Stripe configurada para el sistema seleccionado: ' . $sistema);
    }
    \Stripe\Stripe::setApiKey($stripeSecretKey);

    // Verificar si la columna session_id existe en la tabla pagos
    $result = $dbConn->query("SHOW COLUMNS FROM pagos LIKE 'session_id'");
    if ($result->num_rows == 0) {
        // La columna no existe, la creamos
        $dbConn->query("ALTER TABLE pagos ADD COLUMN session_id VARCHAR(255) NULL");
        $logMessage("Columna session_id creada en la tabla pagos");
    }

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
            'success_url' => 'https://adm.conlineweb.com/payment_success.php?session_id={CHECKOUT_SESSION_ID}&sistema=' . $sistema,
            'cancel_url' => 'https://adm.conlineweb.com/payment_cancel.php?sistema=' . $sistema,
        ]);

        $logMessage("Sesión de Stripe creada exitosamente. Session ID: " . $session->id);

        // Actualizar el registro de pago con el session_id
        $stmt = $dbConn->prepare("UPDATE pagos SET session_id = ? WHERE id = ?");
        $stmt->bind_param("si", $session->id, $_POST['id']);

        if ($stmt->execute()) {
            $logMessage("Session ID guardado en la base de datos para el pago ID: " . $_POST['id']);
        } else {
            $logMessage("ERROR: No se pudo guardar el session_id en la base de datos: " . $dbConn->error);
            throw new Exception("Error al guardar session_id en la base de datos");
        }
        
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