<?php
// Configurar headers antes que cualquier salida
header('Content-Type: application/json; charset=utf-8');

// DESACTIVAR display_errors para evitar que interfiera con JSON
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0); // En producción, desactiva todos los errores

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// Limpiar cualquier output previo
if (ob_get_level()) {
    ob_clean();
}
ob_start();

// Función para enviar respuesta JSON y terminar
function sendJsonResponse($data) {
    ob_clean(); // Limpiar buffer
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Función para manejar errores
function handleError($message, $code = 0) {
    sendJsonResponse([
        'success' => false,
        'message' => $message,
        'error_code' => $code
    ]);
}

// Configuración del remitente
$correoRemitente = "servicios@conlineweb.com";
$nombreRemitente = "ConlineWeb";

try {
    // Verificar si existe el archivo de conexión
    if (!file_exists('conn.php')) {
        throw new Exception('Archivo de conexión no encontrado');
    }
    
    include 'conn.php';
    include 'conn_hostingpro.php';
    $sistema = (isset($_POST['sistema']) && $_POST['sistema'] === 'hostingpro') ? 'hostingpro' : 'conlineweb';
    $conn = ($sistema === 'hostingpro') ? $conn_hp : $conn;
    
    // Verificar conexión a BD
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception('Error de conexión a base de datos');
    }

    // Verificar si PHPMailer existe
    $phpmailer_files = [
        "PHPMailer/src/Exception.php",
        "PHPMailer/src/PHPMailer.php", 
        "PHPMailer/src/SMTP.php"
    ];
    
    foreach ($phpmailer_files as $file) {
        if (!file_exists($file)) {
            throw new Exception("Archivo PHPMailer no encontrado: $file");
        }
        require_once $file;
    }

    function enviarCorreo($correo_destino, $asunto, $titulo, $cuerpo, $despedida) {
        global $correoRemitente, $nombreRemitente;
        
        if (!filter_var($correo_destino, FILTER_VALIDATE_EMAIL)) {
            return "Dirección de correo electrónico no válida";
        }
        
       require_once __DIR__ . "/includes/adm_email_template.php";
    $mensaje = adm_email_message($titulo, $cuerpo, $despedida);
        
        try {
            $mail = new PHPMailer(true);
            
            // Configuración SMTP
            $mail->isSMTP();
            $mail->SMTPAuth = true;
            $mail->SMTPSecure = "tls";
            $mail->Port = 587;
            $mail->Host = "smtp.gmail.com";
            $mail->Username = $correoRemitente;
            $mail->Password = "wcglkgcxfebsauqo";
            

            $mail->setFrom($correoRemitente, $nombreRemitente);
            $mail->addAddress($correo_destino);

            $mail->Subject = $asunto;
            $mail->isHTML(true);
            $mail->Body = $mensaje;
            $mail->CharSet = "UTF-8";
            $mail->Encoding = "base64";

            if ($mail->send()) {
                return true;
            } else {
                return "Error al enviar el correo: " . $mail->ErrorInfo;
            }
            
        } catch (PHPMailerException $e) {
            error_log("Error PHPMailer: " . $e->getMessage());
            return "Excepción de PHPMailer: " . $e->getMessage();
        }
    }

    // Validar ID
    if (!isset($_REQUEST["id"])) {
        throw new Exception("ID no proporcionado");
    }
    
    $id = (int)$_REQUEST["id"];
    
    // Verificar si el pago existe
    $checkStmt = $conn->prepare(
        "SELECT id, id_servicio, id_clie FROM pagos WHERE id = ? AND tipo_servicio = 1 LIMIT 1"
    );
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows === 0) {
        throw new Exception("No se encontró el pago con ID: " . $id);
    }

    $pagoData = $checkResult->fetch_assoc();
    $checkStmt->close();

    // Obtener datos del hosting y cliente
    $stmt = $conn->prepare("
    SELECT 
        h.nom_host AS hosting,
        pl.nombre AS plan,
        h.dominio AS dominio,
        h.costo_producto AS costo,
        h.fecha_pago AS fecha_renovacion,
        h.fecha_contratacion AS periodo,
        c.nombre_contacto,
        TRIM(c.correo) AS correo,
        p.currency
    FROM 
        hosting h
    JOIN 
        clientes c ON h.cliente_id = c.id
    JOIN 
        pagos p ON p.id_servicio = h.id_orden
    JOIN 
        planes pl ON h.producto = pl.id
    WHERE 
        p.id = ? AND
        p.tipo_servicio = 1
    LIMIT 1
");

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception(
            "No se encontraron datos de hosting/cliente para el pago ID: " . $id
        );
    }

    $row = $result->fetch_assoc();
    $stmt->close();

    // Preparar datos para el correo
    $nombre = $row["nombre_contacto"];
    $correo_destino = $row["correo"];
    $hosting = $row["hosting"];
    $plan = $row["plan"];
    $dominio = $row["dominio"];
    $fecha_renovacion = $row["fecha_renovacion"];
    $periodo = $row["periodo"];
    $costo = $row["costo"];
    $moneda = $row["currency"];

    // Procesar pago con Stripe
    $paymentData = [
        'id' => $id,
        'nombre' => $nombre,
        'costo' => $costo,
        'moneda' => $moneda,
        'concepto' => "Renovación de hosting $hosting",
        'correo' => $correo_destino,
        'sistema' => ((isset($_POST['sistema']) && $_POST['sistema'] === 'hostingpro') ? 'hostingpro' : 'conlineweb')
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

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception("Error al conectar con Stripe: $error");
    }
    
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Error HTTP al procesar pago: $httpCode");
    }
    
    $paymentResult = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Error al procesar respuesta de pago: " . json_last_error_msg());
    }
    
    if (!isset($paymentResult['success']) || !$paymentResult['success']) {
        $error = $paymentResult['error'] ?? 'Error desconocido en procesamiento de pago';
        throw new Exception("Error en Stripe: $error");
    }
    
    if (!isset($paymentResult['session_url'])) {
        throw new Exception("URL de pago no recibida de Stripe");
    }
    
    $urlStripe = $paymentResult['session_url'];
    $session_id = $paymentResult['session_id'];
    
    // Actualizar session_id en la base de datos
    $update_sql = "UPDATE pagos SET session_id = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $session_id, $id);
    
    if (!$update_stmt->execute()) {
        error_log("Error al actualizar session_id: " . $update_stmt->error);
    }
    $update_stmt->close();

    // Preparar y enviar email
    $asunto = "Recordatorio de Pago Pendiente de Servicio de Alojamiento (Hosting) - $hosting";
    $titulo = "Recordatorio de Pago Pendiente de Servicio de Alojamiento (Hosting) - $hosting";

    $cuerpo = "
<p>🔔 Estimado cliente, <strong>".htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8')."</strong>,</p>
<p>Te recordamos que tienes un pago pendiente de la renovación de tu hosting <strong>".htmlspecialchars($hosting, ENT_QUOTES, 'UTF-8')."</strong> asociado al dominio <strong>".htmlspecialchars($dominio, ENT_QUOTES, 'UTF-8')."</strong>, con vencimiento el <strong>".htmlspecialchars($fecha_renovacion, ENT_QUOTES, 'UTF-8')."</strong>. Te pedimos atenderlo a tiempo para mantener el sitio y los correos activos.</p>

<div style='margin:20px 0;'>
  <p>🔍 <strong>Detalles del Pago Pendiente</strong></p>
  <ul>
    <li><strong>Servicio:</strong> Renovar Hosting ".htmlspecialchars($hosting, ENT_QUOTES, 'UTF-8')."</li>
    <li><strong>Plan:</strong> ".htmlspecialchars($plan, ENT_QUOTES, 'UTF-8')."</li>
    <li><strong>Dominio asociado:</strong> ".htmlspecialchars($dominio, ENT_QUOTES, 'UTF-8')."</li>
    <li><strong>Fecha límite sugerida:</strong> ".htmlspecialchars($fecha_renovacion, ENT_QUOTES, 'UTF-8')."</li>
    <li><strong>Total a pagar:</strong> ".htmlspecialchars($costo.' '.$moneda, ENT_QUOTES, 'UTF-8')."</li>
  </ul>
</div>

<p><strong>Formas de Pago:</strong></p>

<p>✅ Pago con tarjeta (Stripe)</p>
<p>Puedes completar tu pago de forma rápida y segura a través de nuestra pasarela de pagos.</p>
<div style='text-align: center; margin: 30px 0;'>
    <a href='$urlStripe' style='background-color: #6772E5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold; display: inline-block;'>
           Realizar Pago
        </a>
</div>

<p>Si el boton anterior no funciona, copia y pega el siguiente enlace en tu navegador:<br>
    <small>$urlStripe</small></p>

<p><strong>🏦 Transferencia bancaria</strong></p>
<ul>
    <li><strong>Nombre del titular:</strong> Jose Antonio Martinez Karam</li>
    <li><strong>Banco:</strong> Santander</li>
    <li><strong>Número de cuenta:</strong> 60622161632</li>
    <li><strong>CLAVE interbancaria:</strong> 014225606221616325</li>
</ul>

<p><strong>📲 Confirmación de Pago:</strong></p>
<p>Una vez realizado el pago, por favor envia el comprobante via WhatsApp al numero 
    477 118 1285 para confirmar la renovacion y validar tu transaccion.</p>
    
<p>Si ya realizaste el pago, por favor omite este mensaje.
Quedamos atentos a cualquier duda o comentario.</p>
";
    $despedida = "Atentamente,<br>El equipo de ConlineWeb";

    $resultadoEmail = enviarCorreo($correo_destino, $asunto, $titulo, $cuerpo, $despedida);
    
    // Respuesta exitosa
    $respuesta = [
        'success' => true,
        'email_status' => ($resultadoEmail === true) ? 'Enviado' : $resultadoEmail,
        'payment_link' => $urlStripe,
        'session_id' => $session_id,
        'details' => [
            'hosting' => $hosting,
            'dominio' => $dominio,
            'cliente' => $nombre,
            'monto' => $costo,
            'moneda' => $moneda,
            'fecha_renovacion' => $fecha_renovacion
        ]
    ];

    sendJsonResponse($respuesta);

} catch (Exception $e) {
    handleError($e->getMessage(), $e->getCode());
} catch (Error $e) {
    handleError('Error fatal del servidor: ' . $e->getMessage(), 500);
} finally {
    // Cerrar conexiones si existen
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>
