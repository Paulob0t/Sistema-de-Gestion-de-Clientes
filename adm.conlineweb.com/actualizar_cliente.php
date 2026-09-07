<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

// Configurar headers al inicio
header('Content-Type: application/json; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Iniciar buffer de salida limpio
ob_clean();
ob_start();

include "conn.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require "PHPMailer/src/Exception.php";
require "PHPMailer/src/PHPMailer.php";
require "PHPMailer/src/SMTP.php";

// Crear un archivo de log con más detalles
$logFile = fopen("update_log_".date('Y-m-d').".txt", "a");

function writeLog($message) {
    global $logFile;
    if ($logFile) {
        fwrite($logFile, date('Y-m-d H:i:s') . " - " . $message . "\n");
        fflush($logFile);
    }
}

// Función para registrar todos los datos relevantes
function logRequestData() {
    writeLog("=== INICIO DE SOLICITUD ===");
    writeLog("Método: " . $_SERVER['REQUEST_METHOD']);
    writeLog("POST data: " . print_r($_POST, true));
    writeLog("FILES data: " . print_r($_FILES, true));
    writeLog("Headers: " . print_r(getallheaders(), true));
}

try {
    logRequestData();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido. Se esperaba POST, se recibió: ' . $_SERVER['REQUEST_METHOD']);
    }
    
    // Validar datos esenciales
    $requiredFields = ['id', 'nombre', 'email', 'telefono'];
    $missingFields = [];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        throw new Exception('Faltan campos obligatorios: ' . implode(', ', $missingFields));
    }
    
    // Recoger datos del formulario con validación
    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    if ($id === false) {
        throw new Exception('ID de cliente no válido');
    }
    
    $nombre = trim($_POST['nombre']);
    $telefono = trim($_POST['telefono']);
    $correo = $_POST['email'];
    // Exception('Correo electrónico no válido');
    
    
    // Datos opcionales
    $empresa = $_POST['empresa'] ?? '';
    $rsocial = $_POST['rsocial'] ?? '';
    $rfc = $_POST['rfc'] ?? '';
    $especificacion = $_POST['especificacion'] ?? '';
    $calle = $_POST['calle'] ?? '';
    $next = $_POST['next'] ?? '';
    $nint = $_POST['nint'] ?? '';
    $colonia = $_POST['colonia'] ?? '';
    $cp = $_POST['cp'] ?? '';
    $pais = $_POST['pais'] ?? '';
    $estado = $_POST['estado'] ?? '';
    $ciudad = $_POST['municipio'] ?? '';
    $display = "";
    $facturacion = isset($_POST['facturacion']) ? 1 : 0;
    
    writeLog("Datos procesados:");
    writeLog("ID: $id, Nombre: $nombre, Correo: $correo, Teléfono: $telefono");
    writeLog("Facturación requerida: " . ($facturacion ? 'Sí' : 'No'));
    
    // Manejo del archivo → https://cliente.conlineweb.com/constancias_fiscales/
    $nombre_archivo = '';
    if ($facturacion && isset($_FILES['constancia_fiscal']) && ($_FILES['constancia_fiscal']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        require_once __DIR__ . '/includes/cw_constancia_fiscal_adm.php';
        $file = $_FILES['constancia_fiscal'];
        writeLog("Detalles del archivo: " . print_r($file, true));
        $uploadResult = cw_constancia_fiscal_adm_save_upload($file, (int) $id);
        if (!$uploadResult['ok']) {
            throw new Exception($uploadResult['error'] ?? 'Error al subir la constancia');
        }
        $nombre_archivo = $uploadResult['filename'];
        writeLog("Archivo guardado en cliente: $nombre_archivo");
    }
    
    // Generar credenciales
    writeLog("Generando nuevas credenciales...");
    $contrasena = bin2hex(random_bytes(8)); // Contraseña más segura
    $contrasena_segura = md5($contrasena);
    
    // Iniciar transacción para asegurar la integridad de los datos
    writeLog("Iniciando transacción en la base de datos...");
    $conn->begin_transaction();
    
    try {
        // Actualizar tabla clientes
        $query = "UPDATE clientes SET 
            nombre_contacto = ?, 
            telefono = ?,
            correo = ?,
            empresa = ?,
            rsocial = ?,
            rfc = ?,
            especificacion = ?,
            calle = ?,
            next = ?,
            nint = ?,
            col = ?,
            cp = ?,
            pais = ?,
            estado = ?,
            ciudad = ?,
            constancia_situacion_fiscal = ?,
            display = ?,
            facturacion = ?,
            actualizado = ?
            
            WHERE id = ?";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Error preparando consulta de actualización: ' . $conn->error);
        }
        $actualizado = 1;
        $stmt->bind_param("sssssssssssssssssiii", 
            $nombre, $telefono, $correo, $empresa, $rsocial, $rfc, 
            $especificacion, $calle, $next, $nint, $colonia, $cp, 
            $pais, $estado, $ciudad, $nombre_archivo, $display, $facturacion,$actualizado, $id);
        
        if (!$stmt->execute()) {
            throw new Exception('Error ejecutando actualización de cliente: ' . $stmt->error);
        }
        $stmt->close();
        
        // Actualizar tabla login
        $query_login = "UPDATE login SET 
            usuario = ?,
            contrasena = ?,
            contrasena_normal = ?,
            fecha_actualizacion = NOW()
            WHERE id = ?";
        
        $stmt_login = $conn->prepare($query_login);
        if (!$stmt_login) {
            throw new Exception('Error preparando consulta de login: ' . $conn->error);
        }
        
        $stmt_login->bind_param("sssi", $correo, $contrasena_segura, $contrasena, $id);
        
        if (!$stmt_login->execute()) {
            throw new Exception('Error ejecutando actualización de login: ' . $stmt_login->error);
        }
        $stmt_login->close();
        
        // Confirmar transacción
        $conn->commit();
        writeLog("Transacción completada con éxito");
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
    // Enviar correo electrónico
    writeLog("Preparando envío de correo a $correo...");
    $mailResult = enviarCorreo(
        $correo,
        "Actualización exitosa de tu cuenta – Datos de acceso",
        "Actualización exitosa de tu cuenta",
        "<p>Hemos actualizado tus datos correctamente. Aquí están tus nuevas credenciales:</p>
         <ul>
            <li><strong>Usuario:</strong> $correo</li>
            <li><strong>Contraseña temporal:</strong> $contrasena</li>
         </ul>
         <a href='https://cliente.conlineweb.com'>Inicia sesión aquí</a>
         <p>Te recomendamos cambiar tu contraseña después de iniciar sesión.</p>
         <a href='https://adm.conlineweb.com/cambiar_contrasena.php?id=$id'>Actualiza tu contraseña aquí</a>",
        "Atentamente,<br>El equipo de Conlineweb"
    );
    
    // Preparar respuesta
    $response = [
        'success' => true,
        'mailSent' => $mailResult['success'],
        'message' => $mailResult['success'] ? 
            'Cliente actualizado y correo enviado correctamente' : 
            'Cliente actualizado pero hubo un problema al enviar el correo',
        'usuario' => $correo,
        'contrasena' => $contrasena,
        'debug' => $mailResult
    ];
    
    writeLog("Proceso completado con éxito");
    
} catch (Exception $e) {
    writeLog("ERROR: " . $e->getMessage());
    writeLog("TRACE: " . $e->getTraceAsString());
    
    $response = [
        'success' => false,
        'message' => 'Error al procesar la solicitud',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'debug' => [
            'post_data' => $_POST,
            'file_data' => $_FILES
        ]
    ];
    
    http_response_code(500);
}

// Limpiar buffer y enviar respuesta
ob_clean();
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// Cerrar conexión y archivo de log
if (isset($conn) && $conn) {
    $conn->close();
}
if ($logFile) {
    fclose($logFile);
}

exit();
?>

<?php
function enviarCorreo($correo_destino, $asunto, $titulo, $cuerpo, $despedida) {
    require_once __DIR__ . "/includes/adm_email_template.php";
    $mensaje = adm_email_message($titulo, $cuerpo, $despedida);

    $mail = new PHPMailer(true);
    $result = ['success' => false];

    try {
        // Configuración del servidor SMTP
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'servicios@conlineweb.com';
        $mail->Password = 'wcglkgcxfebsauqo';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->SMTPDebug = 2; // Habilita salida de depuración detallada

        // Configuración del correo
        $mail->setFrom('servicios@conlineweb.com', 'Conlineweb');
        $mail->addAddress($correo_destino);
        $mail->Subject = $asunto;
        $mail->isHTML(true);
        $mail->Body = $mensaje;
        $mail->CharSet = 'UTF-8';

        // Enviar correo
        $mail->send();
        $result['success'] = true;
        $result['message'] = 'Correo enviado correctamente';
        
    } catch (Exception $e) {
        $result['error'] = "Error al enviar el correo: " . $e->getMessage();
        $result['debug'] = $mail->ErrorInfo;
    }

    return $result;
}
?>