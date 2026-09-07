<?php
require_once __DIR__ . '/auth_middleware.php';
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', 1);
require "PHPMailer/src/Exception.php";
require "PHPMailer/src/PHPMailer.php";
require "PHPMailer/src/SMTP.php";
require_once __DIR__ . '/includes/cw_nota_pago_pdf.php';
require_once __DIR__ . '/includes/pago_email_template.php';

function sendEmailWithAttachment($to, $subject, $htmlBody, $attachmentPath = null, $attachmentName = null) {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $correoRemitente = "servicios@conlineweb.com";
    $nombreRemitente = "ConlineWeb";

    try {
        $mail->isSMTP();
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = "tls";
        $mail->Port = 587;
        $mail->Host = "smtp.gmail.com";
        $mail->Username = $correoRemitente;
        $mail->Password = "wcglkgcxfebsauqo";
        $mail->setFrom($correoRemitente, $nombreRemitente);
        $mail->addAddress($to);

        $mail->CharSet = "UTF-8";
        $mail->Encoding = "base64";
        $mail->isHTML(true);
        $mail->Subject = $subject;

        if ($attachmentPath && file_exists($attachmentPath)) {
            $mail->addAttachment(
                $attachmentPath,
                $attachmentName ?: ('Nota_de_pago_' . basename($attachmentPath))
            );
        }

        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));
        return $mail->send();
    } catch (Exception $e) {
        error_log("Email Error: " . $e->getMessage());
        return false;
    }
}

function generarPDFServidor($pagoData, $clienteData, $forma_pago, $tipo_servicio, $id_servicio, $conn) {
    $pago = is_array($pagoData) ? $pagoData : [];
    $pago['forma_pago'] = (int) $forma_pago;
    $pago['tipo_servicio'] = (int) $tipo_servicio;
    $pago['id_servicio'] = (int) $id_servicio;
    if (empty($pago['fecha_pago']) || $pago['fecha_pago'] === '0000-00-00') {
        $pago['fecha_pago'] = date('Y-m-d');
    }
    $cliente = is_array($clienteData) ? $clienteData : [];
    return cw_generar_nota_pago_pdf($pago, $cliente, $conn);
}

// aprobar_pago.php
header('Content-Type: application/json');

try {
    include "conn.php";
    include "conn_hostingpro.php";

    $sistema = (isset($_POST['sistema']) && $_POST['sistema'] === 'hostingpro') ? 'hostingpro' : 'conlineweb';
    $conn = ($sistema === 'hostingpro') ? $conn_hp : $conn;
} catch (Exception $e) {
    error_log("Error incluyendo conn.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos']);
    exit;
}

// Verificar que la conexión a la base de datos existe
if (!isset($conn) || !$conn) {
    error_log("Error: Conexión a la base de datos no disponible");
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos']);
    exit;
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

$log_file = 'pago_errors.log';
date_default_timezone_set('America/Mexico_City');

function log_error($message, $log_file) {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] ERROR: $message\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

try {
// Verificar que la petición sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $error_msg = 'Método no permitido';
    log_error($error_msg, $log_file);
    echo json_encode(['success' => false, 'message' => $error_msg]);
    exit;
}

// Verificar que se reciban los datos necesarios
if (!isset($_POST['id']) || !isset($_POST['forma_pago']) || !isset($_POST['tipo_servicio']) || !isset($_POST['id_servicio'])) {
    $error_msg = 'Datos incompletos';
    log_error($error_msg, $log_file);
    echo json_encode(['success' => false, 'message' => $error_msg]);
    exit;
}

$id = intval($_POST['id']);
$forma_pago = intval($_POST['forma_pago']);
$tipo_servicio = intval($_POST['tipo_servicio']);
$id_servicio = intval($_POST['id_servicio']);
$pago_data = isset($_POST['pago_data']) ? json_decode($_POST['pago_data'], true) : null;

// Log para debugging
log_error("Datos recibidos - ID: $id, forma_pago: $forma_pago, pago_data: " . json_encode($pago_data), $log_file);

// Validar forma de pago (tarjeta=1, transferencia=2, efectivo=3)
if ($forma_pago !== 1 && $forma_pago !== 2 && $forma_pago !== 3) {
    $error_msg = 'Forma de pago inválida: ' . $forma_pago;
    log_error($error_msg, $log_file);
    echo json_encode(['success' => false, 'message' => 'Forma de pago inválida']);
    exit;
}

// Verificar que el pago existe y está pendiente
$check_sql = "SELECT id_clie FROM pagos WHERE id = ? AND estatus = 0";
$check_stmt = $conn->prepare($check_sql);

if (!$check_stmt) {
    $error_msg = 'Error en la consulta: ' . $conn->error;
    log_error($error_msg, $log_file);
    echo json_encode(['success' => false, 'message' => $error_msg]);
    exit;
}

$check_stmt->bind_param("i", $id);
$check_stmt->execute();
$result = $check_stmt->get_result();
$pago_check = $result->fetch_assoc();
$check_stmt->close();

if (!$pago_check) {
    $error_msg = 'Pago no encontrado o ya está aprobado. ID: ' . $id;
    log_error($error_msg, $log_file);
    echo json_encode(['success' => false, 'message' => 'Pago no encontrado o ya está aprobado']);
    exit;
}

// Obtener datos del cliente
$clientStmt = $conn->prepare("SELECT correo, nombre_contacto FROM clientes WHERE id = ?");
$clientStmt->bind_param("i", $pago_check['id_clie']);
$clientStmt->execute();
$cliente = $clientStmt->get_result()->fetch_assoc();
$clientStmt->close();

if (!$cliente || empty($cliente['correo'])) {
    log_error('No se encontró correo del cliente o está vacío para ID: '.$pago_check['id_clie'], $log_file);
}

// Actualizar el pago
$update_sql = "UPDATE pagos SET estatus = 1, forma_pago = ?, fecha_pago = CURDATE(), hora_pago = NOW() WHERE id = ?";
$update_stmt = $conn->prepare($update_sql);

if (!$update_stmt) {
    $error_msg = 'Error en la preparación de la consulta: ' . $conn->error;
    log_error($error_msg, $log_file);
    echo json_encode(['success' => false, 'message' => $error_msg]);
    exit;
}

$update_stmt->bind_param("ii", $forma_pago, $id);

if ($update_stmt->execute()) {
    if ($update_stmt->affected_rows > 0) {
        $success = true;
        $message = 'Pago aprobado correctamente';
        
        // Actualizar fechas según tipo de servicio
        if ($tipo_servicio == 1) {
            // Hosting
            $query = "SELECT fecha_pago FROM hosting WHERE id_orden = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $id_servicio);
            $stmt->execute();
            $stmt->bind_result($fecha);
            $stmt->fetch();
            $stmt->close();

            if ($fecha) {
                $nuevaFecha = date('Y-m-d', strtotime('+1 year -1 day', strtotime($fecha)));
                $update = $conn->prepare("UPDATE hosting SET fecha_pago = ?, estado_producto = 1 WHERE id_orden = ?");
                $update->bind_param("si", $nuevaFecha, $id_servicio);
                $update->execute();
                $update->close();
            } else {
                $success = false;
                $message = "No se encontró la fecha de pago para el servicio de hosting";
                log_error($message." ID: $id_servicio", $log_file);
            }
        } else if ($tipo_servicio == 2) {
            // Dominios
            $query = "SELECT fecha_pago FROM dominios WHERE id_dominio = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $id_servicio);
            $stmt->execute();
            $stmt->bind_result($fecha);
            $stmt->fetch();
            $stmt->close();

            if ($fecha) {
                $nuevaFecha = date('Y-m-d', strtotime('+1 year -1 day', strtotime($fecha)));
                $update = $conn->prepare("UPDATE dominios SET fecha_pago = ?, estado_dominio = 1 WHERE id_dominio = ?");
                $update->bind_param("si", $nuevaFecha, $id_servicio);
                $update->execute();
                $update->close();
            } else {
                $success = false;
                $message = "No se encontró la fecha de pago para el dominio";
                log_error($message." ID: $id_servicio", $log_file);
            }
        }

        // Enviar email solo si tenemos datos del cliente
        if ($success && $cliente && !empty($cliente['correo'])) {
            $tempPdfPath = null;
            $pdfResult = cw_generar_nota_pago_pdf_por_id($conn, $id, $forma_pago);
            if (!empty($pdfResult['ok']) && !empty($pdfResult['path']) && is_file($pdfResult['path'])) {
                $tempPdfPath = $pdfResult['path'];
                log_error("PDF generado correctamente: " . $tempPdfPath, $log_file);
            } else {
                $pdfErr = $pdfResult['error'] ?? 'desconocido';
                log_error("Error al generar PDF para pago ID: {$id}. Motivo: {$pdfErr}", $log_file);
                // Fallback con datos POST (si vienen)
                if ($pago_data && is_array($pago_data)) {
                    $tempPdfPath = generarPDFServidor($pago_data, $cliente, $forma_pago, $tipo_servicio, $id_servicio, $conn) ?: null;
                }
            }

            $metodoTxt = $forma_pago === 1
                ? 'Tarjeta'
                : ($forma_pago === 2 ? 'Transferencia bancaria' : 'Efectivo');
            $fechaHora = date('d/m/Y') . ' · ' . date('h:i a');
            $detalleExtra = cw_email_p(
                '<strong style="color:#000147;">Método de pago:</strong> ' . htmlspecialchars($metodoTxt, ENT_QUOTES, 'UTF-8')
                . '<br><strong style="color:#000147;">Referencia:</strong> #' . (int) $id,
                12
            );
            if ($tempPdfPath) {
                $detalleExtra .= cw_email_alert(
                    '<strong>Comprobante adjunto:</strong> Encontrarás tu nota de pago en PDF en este correo. Guárdala para tus registros.',
                    'success'
                );
            } else {
                $detalleExtra .= cw_email_p(
                    'Si necesitas el comprobante en PDF, escríbenos por WhatsApp al <strong>477 118 1285</strong>.',
                    12
                );
            }
            $detalleExtra .= cw_email_p(
                'Si requieres <strong>factura fiscal</strong>, indícanos tus datos fiscales por WhatsApp o correo.',
                0
            );

            $htmlBody = pago_email_confirmacion(
                (string) ($cliente['nombre_contacto'] ?? 'Cliente'),
                $fechaHora,
                $detalleExtra
            );

            $emailSent = sendEmailWithAttachment(
                $cliente['correo'],
                'Confirmación de pago recibido - ConlineWeb',
                $htmlBody,
                $tempPdfPath,
                $tempPdfPath ? ('comprobante_pago_' . $id . '.pdf') : null
            );

            if ($tempPdfPath && is_file($tempPdfPath)) {
                @unlink($tempPdfPath);
            }

            if (!$emailSent) {
                log_error("Falló el envío de email a {$cliente['correo']}", $log_file);
            }
        }

        echo json_encode([
            'success' => $success,
            'message' => $message,
            'id' => $id,
            'forma_pago' => $forma_pago
        ]);
    } else {
        $error_msg = 'No se pudo actualizar el registro. ID: ' . $id;
        log_error($error_msg, $log_file);
        echo json_encode(['success' => false, 'message' => 'No se pudo actualizar el registro']);
    }
} else {
    $error_msg = 'Error al ejecutar la consulta: ' . $update_stmt->error;
    log_error($error_msg, $log_file);
    echo json_encode(['success' => false, 'message' => 'Error al actualizar el pago']);
}

$update_stmt->close();
$conn->close();

} catch (Exception $e) {
    error_log("Error fatal en aprobar_pago.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor: ' . $e->getMessage()]);
}