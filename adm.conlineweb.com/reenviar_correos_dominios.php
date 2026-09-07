<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

header("Content-Type: application/json; charset=utf-8");
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

require "PHPMailer/src/Exception.php";
require "PHPMailer/src/PHPMailer.php";
require "PHPMailer/src/SMTP.php";

$correoRemitente = "servicios@conlineweb.com";
$nombreRemitente = "ConlineWeb";

try {
    require_once "conn.php";
    if (!$conn) {
        throw new Exception("Error de conexión a la base de datos");
    }

    if (!isset($_REQUEST["id"]) || !is_numeric($_REQUEST["id"])) {
        throw new Exception("ID no proporcionado o inválido");
    }
    $id = (int) $_REQUEST["id"];

    $checkStmt = $conn->prepare("SELECT id, id_servicio, id_clie FROM pagos WHERE id = ? AND tipo_servicio = 2 LIMIT 1");
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows === 0) {
        throw new Exception("No se encontró el pago con ID: " . $id);
    }

    $pagoData = $checkResult->fetch_assoc();
    $checkStmt->close();

    $stmt = $conn->prepare("
        SELECT 
            d.url_dominio AS dominio,
            d.costo_dominio,
            d.fecha_pago AS fecha_renovacion,
            c.nombre_contacto,
            c.correo,
            p.currency
        FROM 
            dominios d
        JOIN 
            clientes c ON d.cliente_id = c.id
        JOIN 
            pagos p ON p.id_servicio = d.id_dominio
        WHERE 
            p.id = ? AND
            p.tipo_servicio = 2
        LIMIT 1
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("No se encontraron datos de dominio/cliente para el pago ID: " . $id);
    }

    $row = $result->fetch_assoc();
    $stmt->close();

    $nombre = $row["nombre_contacto"];
    $correo_destino = $row["correo"];
    $dominio = $row["dominio"];
    $fecha_renovacion = $row["fecha_renovacion"];
    $costo = $row["costo_dominio"];
    $moneda = $row["currency"];

    $paymentData = [
        'id' => $id,
        'nombre' => $nombre,
        'costo' => $costo,
        'moneda' => $moneda,
        'concepto' => "Renovación de dominio $dominio",
        'correo' => $correo_destino
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

    if ($response === false || $httpCode !== 200) {
        throw new Exception("Error al conectar con Stripe");
    }

    $paymentResult = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($paymentResult['success']) || !$paymentResult['success']) {
        throw new Exception("Error en Stripe");
    }

    $urlStripe = $paymentResult['session_url'];
    $session_id = $paymentResult['session_id'];

    $update_sql = "UPDATE pagos SET session_id = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $session_id, $id);
    $update_stmt->execute();
    $update_stmt->close();

    $asunto = "Recordatorio de Pago Pendiente - $dominio";
    $titulo = "Recordatorio de Pago Pendiente - $dominio";

    $cuerpo = "
    <p>🔔 Estimado cliente, <strong>" . htmlspecialchars($nombre, ENT_QUOTES, "UTF-8") . "</strong>,</p>
    <p>Te recordamos que tienes un pago pendiente de la renovación del dominio <strong>" . htmlspecialchars($dominio, ENT_QUOTES, "UTF-8") . "</strong>, con vencimiento el <strong>" . htmlspecialchars($fecha_renovacion, ENT_QUOTES, "UTF-8") . "</strong>. Realízalo a tiempo para conservar el registro y evitar que quede disponible para terceros.</p>

    <div style='margin:20px 0;'>
      <p>🔍 <strong>Detalles del Pago Pendiente</strong></p>
      <ul>
        <li><strong>Servicio:</strong> Renovar Dominio " . htmlspecialchars($dominio, ENT_QUOTES, "UTF-8") . "</li>
        <li><strong>Fecha límite sugerida:</strong> " . htmlspecialchars($fecha_renovacion, ENT_QUOTES, "UTF-8") . "</li>
        <li><strong>Total a pagar:</strong> " . htmlspecialchars($costo . " " . $moneda, ENT_QUOTES, "UTF-8") . "</li>
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

    <p>Si el botón anterior no funciona, copia y pega el siguiente enlace en tu navegador:<br>
        <small>$urlStripe</small></p>

    <p><strong>🏦 Transferencia bancaria</strong></p>
    <ul>
        <li><strong>Nombre del titular:</strong> Jose Antonio Martinez Karam</li>
        <li><strong>Banco:</strong> Santander</li>
        <li><strong>Número de cuenta:</strong> 60622161632</li>
        <li><strong>CLAVE interbancaria:</strong> 014225606221616325</li>
    </ul>

    <p><strong>📲 Confirmación de Pago:</strong></p>
    <p>Una vez realizado el pago, por favor envía el comprobante vía WhatsApp al número 477 118 1285 para confirmar la renovación y validar tu transacción.</p>

    <p>Si ya realizaste el pago, por favor omite este mensaje. Quedamos atentos a cualquier duda o comentario.</p>
    ";

    $despedida = "Atentamente,<br>El equipo de ConlineWeb";

    require_once __DIR__ . "/includes/adm_email_template.php";
    $mensaje = adm_email_message($titulo, $cuerpo, $despedida);

    $mail = new PHPMailer(true);
    $emailEnviado = false;

    try {
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
        $emailEnviado = $mail->send();
    } catch (Exception $e) {
        $errorCorreo = $e->getMessage();
        error_log("Error al enviar correo: " . $errorCorreo);
        $emailEnviado = false;
    }

    ob_clean();
    echo json_encode([
        "success" => $emailEnviado,
        "message" => $emailEnviado ? "Correo enviado correctamente" : "Error al enviar el correo",
        "data" => [
            "pago_id" => $id,
            "dominio" => $dominio,
            "cliente" => $nombre,
            "email" => $correo_destino,
            "payment_link" => $urlStripe,
            "email_sent" => $emailEnviado,
            "error" => $emailEnviado ? null : $errorCorreo,
        ],
    ]);
} catch (Exception $e) {
    ob_clean();
    error_log("Error en reenviar_correos_dominios.php: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage(),
        "error" => $e->getTraceAsString(),
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
