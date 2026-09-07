<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set("display_errors", 1);

require_once 'conn.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$cliente_id = $_POST['cliente_id'] ?? 0;

if (!$cliente_id) {
    echo json_encode(['success' => false, 'message' => 'ID de cliente no proporcionado']);
    exit;
}

// Obtener datos del cliente
$sql = "SELECT c.id, c.empresa, c.nombre_contacto, c.correo, c.telefono, l.usuario, l.contrasena_normal 
        FROM clientes c 
        LEFT JOIN login l ON l.usuario = c.correo 
        WHERE c.id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $cliente_id);
$stmt->execute();
$result = $stmt->get_result();
$cliente = $result->fetch_assoc();
$stmt->close();

if (!$cliente) {
    echo json_encode(['success' => false, 'message' => 'Cliente no encontrado']);
    exit;
}

// Obtener usuario y contraseña
$usuario = $cliente['correo'];
$contrasena_texto = '';

if (!$cliente['usuario']) {
    // Si no existe login, generar contraseña temporal y crear registro
    $contrasena_texto = substr(md5(uniqid(rand(), true)), 0, 10);
    $contrasena_hash = md5($contrasena_texto);
    
    // Crear registro en login
    $sql_insert = "INSERT INTO login (usuario, contrasena, contrasena_normal, nombre, activo) VALUES (?, ?, ?, ?, 1)";
    $stmt_insert = $conn->prepare($sql_insert);
    $stmt_insert->bind_param('ssss', $usuario, $contrasena_hash, $contrasena_texto, $cliente['nombre_contacto']);
    $stmt_insert->execute();
    $stmt_insert->close();
} else {
    // Si existe login, obtener la contraseña del campo contrasena_normal
    $contrasena_texto = $cliente['contrasena_normal'];
    
    // Si no existe contrasena_normal, generar una nueva y actualizar
    if (empty($contrasena_texto)) {
        $contrasena_texto = substr(md5(uniqid(rand(), true)), 0, 10);
        $contrasena_hash = md5($contrasena_texto);
        
        $sql_update = "UPDATE login SET contrasena = ?, contrasena_normal = ? WHERE usuario = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param('sss', $contrasena_hash, $contrasena_texto, $usuario);
        $stmt_update->execute();
        $stmt_update->close();
    }
}

// Enviar correo
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$mail = new PHPMailer(true);

try {
    $correoRemitente = 'servicios@conlineweb.com';
    $nombreRemitente = 'ConlineWeb';

    $mail->isSMTP();
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;
    $mail->Host = 'smtp.gmail.com';
    $mail->Username = $correoRemitente;
    $mail->Password = "wcglkgcxfebsauqo";

    $mail->SMTPDebug = 0;

    $mail->setFrom($correoRemitente, $nombreRemitente);
    $mail->addAddress($cliente['correo']);

    $mail->isHTML(true);
    $mail->Subject = 'Accesos al Portal de Cliente - ConlineWeb';

    $titulo = 'Accesos al Portal de Cliente - ConlineWeb';
    $cuerpo = "<p>Hola 👋, te compartimos tus accesos al portal de cliente de ConlineWeb:</p>
                
                <div style='background-color: #f8f9fa; padding: 15px; border-radius: 6px; margin: 15px 0;'>
                    <p><strong>🌐 Portal de Cliente:</strong> <a href='https://cliente.conlineweb.com/'>https://cliente.conlineweb.com/</a></p>
                    <p><strong>👤 Usuario:</strong> " . htmlspecialchars($usuario, ENT_QUOTES, 'UTF-8') . "</p>
                    <p><strong>🔑 Contraseña:</strong> " . htmlspecialchars($contrasena_texto, ENT_QUOTES, 'UTF-8') . "</p>
                </div>
                
                <p>Desde el portal podrás cambiar tu contraseña en cualquier momento desde la opción &quot;Restablecer Contraseña&quot;.</p>
                
                <p><strong>⚠️ IMPORTANTE:</strong> Para solicitar cambios o dar seguimiento a tu proyecto de página web y otros proyectos, te recomendamos usar siempre el portal de cliente como canal principal. Esto nos permite mejorar la atención y garantizar que todas tus solicitudes se gestionen de forma rápida y correcta.</p>
                
                <ol>
                    <li><strong>①</strong> Ingresa al portal con tu usuario y contraseña.<br>
                    👉 Si no tienes acceso o lo olvidaste, restablece tu contraseña usando el correo con el que creaste tu cuenta: " . htmlspecialchars($usuario, ENT_QUOTES, 'UTF-8') . "</li>
                    
                    <li><strong>②</strong> Ve al apartado &quot;Solicitud de Ticket&quot; y registra tu requerimiento, detallando los cambios y adjuntando referencias si es necesario.</li>
                    
                    <li><strong>③</strong> Da clic en &quot;Enviar&quot; para que podamos atender tu solicitud y puedas seguir el progreso en línea.</li>
                </ol>
                
                <p>💡 <strong>Recuerda:</strong> Cada solicitud debe registrarse por separado para que podamos gestionarla más rápido y de forma organizada.</p>
                
                <p style='margin-top: 30px;'>¡Gracias por tu confianza en ConlineWeb!</p>";
    $despedida = "Atentamente,<br>Equipo de Soporte Técnico";

    require_once __DIR__ . "/includes/adm_email_template.php";
    $mensaje = adm_email_message($titulo, $cuerpo, $despedida);

    $mail->Body = $mensaje;
    $mail->CharSet = 'UTF-8';

    $mail->send();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Accesos enviados correctamente por correo',
        'usuario' => $usuario,
        'contrasena' => $contrasena_texto,
        'telefono' => $cliente['telefono']
    ]);
    
} catch (Exception $e) {
    error_log('Error al enviar correo de accesos: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al enviar el correo: ' . $mail->ErrorInfo]);
}
?>
