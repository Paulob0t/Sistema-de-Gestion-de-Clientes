<?php
ob_start();
header('Content-Type: application/json');
include "conn.php";


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require "./PHPMailer/src/Exception.php";
require "./PHPMailer/src/PHPMailer.php";
require "./PHPMailer/src/SMTP.php";



// Obtener el ID del cliente desde GET
$idCliente = isset($_GET["id"]) && $_GET["id"] !== '' ? intval($_GET["id"]) : null;

if (!$idCliente) {
    echo json_encode(["success" => false, "message" => "ID de cliente no proporcionado"]);
    exit();
}

// Obtener datos del cliente
try {
    $sqlCliente = "SELECT * FROM clientes WHERE id = ?";
    $stmt = $conn->prepare($sqlCliente);
    $stmt->bind_param("i", $idCliente);
    $stmt->execute();
    $resultCliente = $stmt->get_result();
    $cliente = $resultCliente->fetch_assoc();

    if (!$cliente) {
        echo json_encode(["success" => false, "message" => "Cliente no encontrado"]);
        exit();
    }

    // Obtener dominio asociado
    $sqlDominio = "SELECT * FROM hosting WHERE cliente_id = ?";
    $stmt = $conn->prepare($sqlDominio);
    $stmt->bind_param("i", $idCliente);
    $stmt->execute();
    $resultDominio = $stmt->get_result();
    $dominio = $resultDominio->fetch_assoc();

    if (!$dominio) {
        echo json_encode(["success" => false, "message" => "Dominio no encontrado para el cliente"]);
        exit();
    }

    // Preparar datos
    $correo_destino = $cliente["correo"] ?? '';
    if (empty($correo_destino)) {
        echo json_encode(["success" => false, "message" => "No hay dirección de correo para el cliente"]);
        exit();
    }

    $nombre_cliente = $cliente["nombre_contacto"] ?? 'Cliente';
    $url_dominio = $dominio["url_dominio"] ?? 'dominio no especificado';
    $fecha_pago = (!empty($dominio["fecha_pago"]) && $dominio["fecha_pago"] !== '0000-00-00') 
        ? date("d M Y", strtotime($dominio["fecha_pago"])) 
        : "sin fecha registrada";
        
        
        
        
    // Contenido del correo
    $asunto = "Recordatorio de renovacion de dominio: $url_dominio";
    $titulo = "Renovación de dominio";
// Aquí defines el botón justo antes de construir el cuerpo:
$boton_pago = "<p style='text-align:center; margin: 15px 0;'>
  <a href='$urlStripe' style='display: inline-block; background-color: #27ae60; color: #fff; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold;'>
    Pagar renovación ahora
  </a>
</p>";

// Construyes el cuerpo incluyendo el botón, sin imprimirlo después:
$cuerpo = "Estimado/a $nombre_cliente,<br><br>
Le recordamos que el dominio <strong>$url_dominio</strong> tiene como fecha de renovación el <strong>$fecha_pago</strong>.<br><br>
Por favor, considere renovarlo a tiempo para evitar interrupciones en su servicio.<br><br>



<h4>💳 Opciones de pago:</h4>
<p><strong>Transferencia bancaria</strong></p>
<ul>
  <li><strong>Nombre del titular:</strong> Jose Antonio Martinez Karam</li>
  <li><strong>Número de cuenta:</strong> 60622161632 (Santander)</li>
  <li><strong>CLABE:</strong> 014225606221616325 (Santander)</li>
  $boton_pago
</ul>
<hr>
<p>Una vez realizado el pago, por favor envía el comprobante vía WhatsApp al número <strong>477 118 1285</strong> para confirmar la renovación.</p>
<p>Si tienes alguna pregunta o necesitas asistencia, no dudes en contactarnos. Estaremos encantados de ayudarte.</p>";
    $despedida = "Gracias por su atención.<br>Atentamente, <br><strong>Equipo de soporte técnico</strong>";
    
    
    
    // Preparar datos del cliente y dominio
$correo_destino = $cliente["correo"] ?? '';
if (empty($correo_destino)) {
    echo json_encode(["success" => false, "message" => "No hay dirección de correo para el cliente"]);
    exit();
}

$monedasMap = [
  1 => 'mxn',
  2 => 'usd'
];


$nombre_cliente = $cliente["nombre_contacto"] ?? 'Cliente';
$url_dominio = $dominio["url_dominio"] ?? 'dominio no especificado';
$fecha_pago = (!empty($dominio["fecha_pago"]) && $dominio["fecha_pago"] !== '0000-00-00') 
    ? date("d M Y", strtotime($dominio["fecha_pago"])) 
    : "sin fecha registrada";
$costoDominio = isset($dominio["costo_dominio"]) ? $dominio["costo_dominio"] : 1200;
$idFormaPago = isset($dominio["id_forma_pago"]) ? intval($dominio["id_forma_pago"]) : 1;
$codigoMoneda = $monedasMap[$idFormaPago] ?? 'mxn';  // default a 'mxn' si no existe





// ✅ Preparar datos para la solicitud de pago (usa los nombres requeridos)
$data = [
    "id" => $idCliente,
    "nombre" => $nombre_cliente,
    "correo" => $correo_destino,
    "moneda" => $codigoMoneda,
    "costo" => $costoDominio,
    "concepto" => "Renovación de dominio: $url_dominio"
];

$ch = curl_init('https://adm.conlineweb.com/procesar_pago.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
curl_close($ch);

$payment_result = json_decode($response, true);

if (!isset($payment_result['success']) || !$payment_result['success']) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al crear la sesión de pago con Stripe',
        'error' => $payment_result['error'] ?? 'Error desconocido',
        'raw_response' => $response
    ]);
    exit;
}

// ✅ Ahora que ya existe, puedes usar $urlStripe
$urlStripe = $payment_result['session_url'];
$boton_pago = "<p style='text-align:center; margin: 15px 0;'>
  <a href='$urlStripe' style='display: inline-block; background-color: #27ae60; color: #fff; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold;'>
    Pagar renovación ahora - $ $costoDominio $codigoMoneda
  </a>
</p>";






    // Configuración de PHPMailer con el estilo del código que funciona
    $mail = new PHPMailer(true);
    require_once __DIR__ . "/includes/adm_email_template.php";
    $mensaje = adm_email_message($titulo, $cuerpo, $despedida);
    
    
     // Encabezados para enviar el correo como HTML
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8" . "\r\n";

    // Configuración SMTP (igual que en tu código funcional)
    $mail_enviado = false;
    $correoRemitente = "servicios@conlineweb.com";
    $nombreRemitente = "InfoConlineweb";
    
    try {
        $mail->isSMTP();
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = 'starttls'; // Cambiado de 'starttls' a 'tls'
        $mail->Port = 587;
        $mail->Host = "smtp.gmail.com";
        $mail->Username = $correoRemitente;
        $mail->Password = "wcglkgcxfebsauqo";

        $mail->setFrom($correoRemitente, $nombreRemitente);
        $mail->addAddress($correo_destino, $nombre_cliente);

        $mail->Subject = $asunto;
        $mail->isHTML(true);
        $mail->Body = $mensaje;

        $mail->send();
        ob_end_clean();
        echo json_encode(["success" => true, "message" => "✅ Correo enviado a $correo_destino"]);
        exit();
    } catch (Exception $e) {
        ob_end_clean();
        echo json_encode(["success" => false, "message" => "❌ Error al enviar el correo: " . $mail->ErrorInfo]);
        exit();

    }

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "❌ Error en el proceso: " . $e->getMessage()]);
}