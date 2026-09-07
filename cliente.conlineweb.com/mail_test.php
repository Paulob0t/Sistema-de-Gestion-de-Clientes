<?php
// Script de prueba para depurar envío SMTP con PHPMailer
require_once 'conn.php';
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

header('Content-Type: application/json; charset=utf-8');

$to = trim($_GET['to'] ?? '');
if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'Proporciona ?to=correo@dominio.com en la URL']);
    exit;
}

$out = ['ok' => null, 'debug' => [], 'db_check' => null, 'exception' => null];

// Comprobar si el email existe en la tabla clientes
$stmt = $conn->prepare('SELECT id, correo FROM clientes WHERE correo = ? LIMIT 1');
$stmt->bind_param('s', $to);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();
$out['db_check'] = $row ?: null;

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = 'tls';
    $mail->Host = 'smtp.gmail.com';
    $mail->Port = 587;
    $mail->Username = 'info@conlineweb.com';
    $mail->Password = 'bwctvomkzretakmu';

    // Capturar debug en array
    $debug_lines = [];
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = function($str, $level) use (&$debug_lines) {
        $debug_lines[] = "[$level] $str";
    };

    $mail->setFrom('info@conlineweb.com', 'Conlineweb Debug');
    $mail->addAddress($to);
    $mail->Subject = 'Prueba SMTP - Conlineweb';
    $mail->Body = '<p>Mensaje de prueba desde mail_test.php</p>';
    $mail->isHTML(true);

    $sent = $mail->send();
    $out['ok'] = true;
    $out['debug'] = $debug_lines;
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (PHPMailerException $e) {
    $out['ok'] = false;
    $out['exception'] = $e->getMessage();
    $out['debug'] = $debug_lines ?? [];
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

?>
