<?php
// Test simple de SMTP para verificar credenciales
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Localizar PHPMailer
$pmPaths = [
    __DIR__.'/PHPMailer/src',
    __DIR__.'/../PHPMailer/src',
    dirname(__DIR__).'/PHPMailer/src',
];
$pmFound = null;
foreach ($pmPaths as $p) {
    if ($p && file_exists($p.'/PHPMailer.php') && file_exists($p.'/SMTP.php') && file_exists($p.'/Exception.php')) {
        $pmFound = $p; break;
    }
}

if (!$pmFound) {
    die("PHPMailer no encontrado en las rutas esperadas");
}

require_once $pmFound.'/Exception.php';
require_once $pmFound.'/PHPMailer.php';
require_once $pmFound.'/SMTP.php';

echo "<h2>Test SMTP - ConlineWeb</h2>";

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = "starttls";
    $mail->Port = 587;
    $mail->Host = "smtp.gmail.com";
    $mail->Username = "servicios@conlineweb.com";
    $mail->Password = "wcglkgcxfebsauqo";
    
    // Configurar correo de prueba
    $mail->setFrom("servicios@conlineweb.com", "Test SMTP");
    $mail->addAddress("servicios@conlineweb.com", "Test");
    $mail->Subject = "Test SMTP - " . date('Y-m-d H:i:s');
    $mail->isHTML(true);
require_once dirname(__DIR__, 2) . '/includes/cw_email_brand.php';

    $mail->Body = cw_email_wrap([
        'title' => 'Test SMTP',
        'badge' => 'Test',
        'content' => cw_email_p('Este es un test de conectividad SMTP desde el sistema de tickets.')
            . cw_email_p('Fecha: <strong>' . date('Y-m-d H:i:s') . '</strong>', 0),
    ]);
    
    if ($mail->send()) {
        echo "<p style='color: green;'>✅ Correo enviado exitosamente</p>";
        echo "<p>Las credenciales SMTP están funcionando correctamente.</p>";
    } else {
        echo "<p style='color: red;'>❌ Error al enviar correo</p>";
        echo "<p>Error: " . $mail->ErrorInfo . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Excepción PHPMailer</p>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='javascript:history.back()'>← Volver</a></p>";
?>