<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
ini_set('display_errors', 0);

try {
    require_once "conn.php";
    require_once "conn_hostingpro.php";
    
    if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
        throw new Exception("ID no proporcionado");
    }
    
    $id = (int)$_POST['id'];
    $sistema = isset($_POST['sistema']) ? $_POST['sistema'] : 'conlineweb';
    $conn = ($sistema === 'hostingpro') ? $conn_hp : $conn;
    
    // Obtener información del pago
    $sql = "SELECT 
        p.*,
        c.nombre_contacto,
        c.telefono,
        CASE 
            WHEN p.tipo_servicio = '2' THEN d.url_dominio
            WHEN p.tipo_servicio = '1' THEN h.nom_host
            ELSE NULL
        END AS nombre_servicio,
        h.producto,
        h.costo_producto,
        d.costo_dominio
    FROM pagos p
    LEFT JOIN clientes c ON p.id_clie = c.id
    LEFT JOIN dominios d ON p.tipo_servicio = '2' AND p.id_servicio = d.id_dominio
    LEFT JOIN hosting h ON p.tipo_servicio = '1' AND p.id_servicio = h.id_orden
    WHERE p.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("No se encontró el pago");
    }
    
    $pago = $result->fetch_assoc();
    $stmt->close();
    
    // Verificar que tenga teléfono
    if (empty($pago['telefono'])) {
        throw new Exception("El cliente no tiene teléfono registrado");
    }
    
    // Generar link de pago de Stripe
    $paymentData = [
        'id' => $id,
        'nombre' => $pago['nombre_contacto'],
        'costo' => $pago['monto'],
        'moneda' => $pago['currency'],
        'concepto' => $pago['concepto'],
        'correo' => '' // No necesitamos correo para WhatsApp
    ];
    
    $ch = curl_init('https://adm.conlineweb.com/procesar_pago.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($paymentData),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $urlStripe = '';
    if ($response !== false && $httpCode === 200) {
        $paymentResult = json_decode($response, true);
        if (isset($paymentResult['success']) && $paymentResult['success']) {
            $urlStripe = $paymentResult['session_url'];
            $session_id = $paymentResult['session_id'];
            
            // Actualizar session_id en la base de datos
            $update_sql = "UPDATE pagos SET session_id = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("si", $session_id, $id);
            $update_stmt->execute();
            $update_stmt->close();
        }
    }
    
    // Determinar tipo de servicio
    $tipoServicio = '';
    if ($pago['tipo_servicio'] == 1) {
        $tipoServicio = 'Hosting';
    } elseif ($pago['tipo_servicio'] == 2) {
        $tipoServicio = 'Dominio';
    } else {
        $tipoServicio = 'Servicio';
    }
    
    // Formatear fecha límite
    $fechaLimite = 'No especificada';
    if (!empty($pago['fecha_limite_pago']) && $pago['fecha_limite_pago'] !== '0000-00-00') {
        $fechaLimite = date('d/m/Y', strtotime($pago['fecha_limite_pago']));
    }
    
    // Crear mensaje de WhatsApp (sin emojis problemáticos)
    $mensaje = "Hola *" . $pago['nombre_contacto'] . "*\n\n";
    $mensaje .= "Te recordamos que tienes un pago pendiente:\n\n";
    $mensaje .= "*DETALLES DEL PAGO*\n";
    $mensaje .= "----------------------------------------\n";
    $mensaje .= "• Servicio: " . $tipoServicio . "\n";
    
    if (!empty($pago['nombre_servicio'])) {
        $mensaje .= "• " . $tipoServicio . ": " . $pago['nombre_servicio'] . "\n";
    }
    
    $mensaje .= "• Concepto: " . $pago['concepto'] . "\n";
    $mensaje .= "• Monto: $" . number_format($pago['monto'], 2) . " " . $pago['currency'] . "\n";
    $mensaje .= "• Fecha limite: " . $fechaLimite . "\n";
    $mensaje .= "----------------------------------------\n\n";
    
    $mensaje .= "*COMO REALIZAR EL PAGO:*\n\n";
    $mensaje .= "Para efectuar tu pago, por favor ingresa a tu dashboard con tu usuario y contrasena:\n\n";
    $mensaje .= "https://adm.conlineweb.com\n\n";
    $mensaje .= "Una vez dentro, podras:\n";
    $mensaje .= "• Ver todos tus servicios y pagos pendientes\n";
    $mensaje .= "• Pagar con tarjeta de forma segura (Stripe)\n";
    $mensaje .= "• Generar recibos y comprobantes\n";
    $mensaje .= "• Consultar el historial de pagos\n\n";
    
    $mensaje .= "Si ya realizaste el pago, por favor omite este mensaje.\n\n";
    $mensaje .= "Gracias por tu atencion.\n\n";
    $mensaje .= "Atentamente,\n";
    $mensaje .= "El equipo de ConlineWeb";
    
    // Limpiar y formatear teléfono
    $telefono = preg_replace('/\D/', '', $pago['telefono']);
    if (!empty($telefono) && strlen($telefono) === 10 && substr($telefono, 0, 2) !== '52') {
        $telefono = '52' . $telefono;
    }
    
    echo json_encode([
        'success' => true,
        'telefono' => $telefono,
        'mensaje' => $mensaje,
        'cliente' => $pago['nombre_contacto']
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
