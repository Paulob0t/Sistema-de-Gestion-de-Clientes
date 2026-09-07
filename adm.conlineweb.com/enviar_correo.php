<?php
ob_start();
header('Content-Type: application/json');
include "conn.php";
error_reporting(E_ALL);
ini_set('display_errors', 1); // Mostrar errores en pantalla para depuración

// Verificar si PHPMailer existe
$phpmailerFiles = [
    './PHPMailer/src/Exception.php',
    './PHPMailer/src/PHPMailer.php',
    './PHPMailer/src/SMTP.php'
];

foreach ($phpmailerFiles as $file) {
    if (!file_exists($file)) {
        echo json_encode(["success" => false, "message" => "Archivo PHPMailer no encontrado: $file"]);
        exit();
    }
}

require "./PHPMailer/src/Exception.php";
require "./PHPMailer/src/PHPMailer.php";
require "./PHPMailer/src/SMTP.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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
    $sqlDominio = "SELECT * FROM dominios WHERE cliente_id = ?";
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
    $asunto = "Recordatorio de renovación de dominio: $url_dominio";
    $titulo = "Renovación de dominio";
    $cuerpo = "Estimado/a $nombre_cliente,<br><br>
    Le recordamos que el dominio <strong>$url_dominio</strong> tiene como fecha de renovación el <strong>$fecha_pago</strong>.<br><br>
    Por favor, considere renovarlo a tiempo para evitar interrupciones en su servicio.";
    $despedida = "Gracias por su atención.<br>Atentamente, <br><strong>Equipo de soporte técnico</strong>";

    // Configuración de PHPMailer con el estilo del código que funciona
    $mail = new PHPMailer(true);
    require_once __DIR__ . "/includes/adm_email_template.php";
    $mensaje = adm_email_message($titulo, $cuerpo, $despedida);

    // Configuración SMTP (igual que en tu código funcional)
    $correoRemitente = "servicios@conlineweb.com";
    $nombreRemitente = "InfoConlineweb";
    
    try {
        $mail->isSMTP();
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = "starttls";
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
        echo json_encode(["success" => true, "message" => "✅ Correo enviado a $correo_destino"]);
    } catch (Exception $e) {
        echo json_encode(["success" => false, "message" => "❌ Error al enviar el correo: " . $mail->ErrorInfo]);
    }

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "❌ Error en el proceso: " . $e->getMessage()]);
}