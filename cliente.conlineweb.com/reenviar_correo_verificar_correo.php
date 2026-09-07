<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
 require_once "conn.php";
// Configurar headers antes que cualquier salida
header("Content-Type: application/json; charset=utf-8");

// Limpiar buffer de salida
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Incluir PHPMailer
require "PHPMailer/src/Exception.php";
require "PHPMailer/src/PHPMailer.php";
require "PHPMailer/src/SMTP.php";

// Configuración del remitente
$correoRemitente = "info@conlineweb.com";
$nombreRemitente = "Conlineweb";

$id = (int)$_POST['id'];


 $sql = "SELECT * FROM clientes WHERE id = ?";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("i", $id);
      $stmt->execute();
      $result = $stmt->get_result();
      $cliente = $result->fetch_assoc();
      $stmt->close();
        $nombre = $cliente['nombre_contacto'];
        $correo = $cliente['correo_pendiente_actualizar'];
    $messageSuccess = "Hemos enviado un correo de verificación a tu nueva dirección. Por favor, revisa tu bandeja de entrada y haz clic en el enlace para confirmar el cambio. Una vez confirmado, este será tu nuevo usuario para iniciar sesión.";
    
    
    
    
require_once __DIR__ . '/includes/cliente_email_template.php';

        $correoRemitente = 'servicios@conlineweb.com';
        $asunto = "Cambio de credenciales - CONLINEWEB";
        $enlace = "https://adm.conlineweb.com/confirmar_correo.php?id=" . urlencode((string) $id);
        $mensaje = cliente_email_verify_change($nombre, $enlace);
        $correo_destino = $correo;
        $nombreRemitente = "Conlineweb";
        // Configurar PHPMailer
        $mail = new PHPMailer(true);
        $emailEnviado = false;

        try {
         $mail->isSMTP();
        $mail->SMTPDebug = 0; // Cambiado a 0 para producción
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        $mail->Host = "smtp.gmail.com";
        $mail->Username = $correoRemitente;
        $mail->Password = "wcglkgcxfebsauqo"; 

        // Configuración del correo
        $mail->setFrom($correoRemitente, $nombreRemitente);
        $mail->addAddress($correo_destino); 
        $mail->Subject = $asunto;
        $mail->isHTML(true);
        $mail->Body = $mensaje;
        $mail->CharSet = 'UTF-8';

          $emailEnviado = $mail->send();



        } catch (Exception $e) {
          $errorCorreo = $e->getMessage();
          error_log("Error al enviar correo: " . $errorCorreo);
          $emailEnviado = false;
        }




// Responder con JSON según el resultado del envío del correo
if ($emailEnviado) {
    echo json_encode([
        "success" => true,
        "message" => $messageSuccess
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => isset($errorCorreo) ? $errorCorreo : "No se pudo enviar el correo."
    ]);
}
exit;
?>
