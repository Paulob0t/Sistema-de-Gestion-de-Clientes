<?php
session_start();
header('Content-Type: application/json'); // Establecemos el tipo de contenido como JSON
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;


require "PHPMailer/src/Exception.php";
require "PHPMailer/src/PHPMailer.php";
require "PHPMailer/src/SMTP.php";



if (isset($_SESSION['id']) != null && isset($_SESSION['login']) == true) {
  include 'conn.php';
  $usrid = $_SESSION['uid'];
        $messageSuccess ="Tus datos se actualizaron correctamente!";
        $quiere_actualizar = 0;
  if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Obtener el ID del cliente
    $id = $_POST['id'] ?? ($_GET['id'] ?? null);
    if ($id && is_numeric($id)) {
      // Consultar datos actuales del cliente
      $sql = "SELECT * FROM clientes WHERE id = ?";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("i", $id);
      $stmt->execute();
      $result = $stmt->get_result();
      $cliente = $result->fetch_assoc();
      $stmt->close();

      // Recoger datos del formulario con valores por defecto para evitar errores
      $nombre = $_POST['nombre'] ?? '';
      $telefono = $_POST['telefono'] ?? '';
      $correo = $_POST['correo'] ?? '';
      $empresa = $_POST['empresa_fact'] ?? '';
      $tel = $_POST['tel'] ?? '';
      // Facturación: usar campos *_fact si existen
      $rsocial = $_POST['rsocial_fact'] ?? ($_POST['rsocial'] ?? '');
      $rfc = $_POST['rfc_fact'] ?? ($_POST['rfc'] ?? '');
      $calle = $_POST['calle_fact'] ?? ($_POST['calle'] ?? '');
      $next = $_POST['next_fact'] ?? ($_POST['next'] ?? '');
      $nint = $_POST['nint_fact'] ?? ($_POST['nint'] ?? '');
      $col = $_POST['colonia_fact'] ?? ($_POST['col'] ?? '');
      $cp = $_POST['cp_fact'] ?? ($_POST['cp'] ?? '');
      $pais = $_POST['pais_fact'] ?? ($_POST['pais'] ?? '');
      $estado = $_POST['estado_fact'] ?? ($_POST['estado'] ?? '');
      $ciudad = $_POST['municipio'] ?? '';
      $contrasena = $_POST['inputPassword'] ?? '';
      $contrasena_segura = md5($contrasena);
      $facturacion = $_POST['facturacion'] ?? '';

      // Procesar archivo constancia_fiscal
      $constancia_fiscal_url = '';
      if (isset($_FILES['constancia_fiscal']) && $_FILES['constancia_fiscal']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/constancias_fiscales/';
        if (!is_dir($uploadDir)) {
          mkdir($uploadDir, 0777, true);
        }
        $fileTmpPath = $_FILES['constancia_fiscal']['tmp_name'];
        $fileName = 'constancia_' . $id . '_' . time() . '.' . pathinfo($_FILES['constancia_fiscal']['name'], PATHINFO_EXTENSION);
        $destPath = $uploadDir . $fileName;
        if (move_uploaded_file($fileTmpPath, $destPath)) {
          $constancia_fiscal_url = 'https://cliente.conlineweb.com/constancias_fiscales/' . $fileName;
        } else {
          error_log("[constancia_fiscal] Error al mover el archivo: " . print_r($_FILES['constancia_fiscal'], true) . ", destino: $destPath");
        }
      } elseif (isset($_FILES['constancia_fiscal'])) {
        error_log("[constancia_fiscal] Error en upload: " . $_FILES['constancia_fiscal']['error'] . ", detalles: " . print_r($_FILES['constancia_fiscal'], true));
      }

      // Preparar la consulta SQL (mejor usar sentencias preparadas)
             $fields = [
            "nombre_contacto = '$nombre'",
            "empresa = '$empresa'",
            "telefono = '$tel'",
            "especificacion = '---'",
            "rsocial = '$rsocial'",
            "rfc = '$rfc'",
            "calle = '$calle'",
            "next = '$next'",
            "nint = '$nint'",
            "col = '$col'",
            "cp = '$cp'",
            "pais = '$pais'",
            "estado = '$estado'",
            "ciudad = '$ciudad'",
            "display = '---'"
        ];
      if ($constancia_fiscal_url !== '') {
        $fields[] = "constancia_situacion_fiscal = '$constancia_fiscal_url'";
      }

      // Obtener correo actual del cliente antes de actualizar
      $correo_actual = $cliente['correo'] ?? '';
      //

      // Verificar si el correo va a cambiar
      if ($correo_actual !== $correo) {

        $quiere_actualizar = 1;
        $messageSuccess = "Hemos enviado un correo de verificación a tu nueva dirección. Por favor, revisa tu bandeja de entrada y haz clic en el enlace para confirmar el cambio. Una vez confirmado, este será tu nuevo usuario para iniciar sesión.";
        
        
            
          $sql_correo = "UPDATE clientes SET correo_pendiente_actualizar = ?, actualizar_correo = ? WHERE id = ?";
          $stmt_correo = $conn->prepare($sql_correo);
          $stmt_correo->bind_param("sii", $correo,$quiere_actualizar, $id);
          $stmt_correo->execute();
          $stmt_correo->close();
          
          
          
          
          
          
          

        require_once dirname(__DIR__) . '/includes/cliente_email_template.php';

        $asunto = "Cambio de credenciales - CONLINEWEB";
        $enlace = "https://adm.conlineweb.com/confirmar_correo.php?id=" . urlencode((string) $id);
        $mensaje = cliente_email_verify_change($nombre, $enlace);
        $correoRemitente = "info@conlineweb.com";
        $nombreRemitente = "Conlineweb";
        // Configurar PHPMailer
        $mail = new PHPMailer(true);
        $emailEnviado = false;

        try {
          $mail->isSMTP();
          $mail->SMTPAuth = true;
          $mail->SMTPSecure = "tls";
          $mail->Port = 587;
          $mail->Host = "smtp.gmail.com";
          $mail->Username = $correoRemitente;
          $mail->Password = "bwctvomkzretakmu";

          $mail->setFrom($correoRemitente, $nombreRemitente);
          $mail->addAddress($correo);

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

   

      }else{
           $fields[] = "correo = '$correo'";

      }

      // Actualizar el campo usuario en la tabla login con el nuevo correo
    //   $sql_login = "UPDATE login SET usuario = ? WHERE id = ?";
    //   $stmt_login = $conn->prepare($sql_login);
    //   $stmt_login->bind_param("si", $correo, $id);
    //   $stmt_login->execute();
    //   $stmt_login->close();
    
    
    $sql = "UPDATE clientes SET " . implode(', ', $fields) . " WHERE id = $id";
    if (mysqli_query($conn, $sql)) {
      echo json_encode([
        'success' => true,
        'message' => $messageSuccess,
        'redirect' => 'cliente.php',
        "actualizarcorreo" =>$quiere_actualizar,
        "correo"=>$correo
      ]);
    } else {
      error_log("[UPDATE clientes] Error: " . mysqli_error($conn) . ", SQL: $sql");
      echo json_encode([
        'success' => false,
        'message' => 'Tu actualización no se realizó: ' . mysqli_error($conn),
        'redirect' => 'cliente.php'
      ]);
    }

      mysqli_close($conn);
      exit; // Importante terminar la ejecución después de enviar la respuesta JSON
    } else {
      error_log("[ID de cliente no válido] ID recibido: " . print_r($id, true));
      echo json_encode([
        'success' => false,
        'message' => 'ID de cliente no válido',
        'redirect' => 'cliente.php'
      ]);
      exit;
    }
  }
} else {
  error_log("[No autorizado] Sesión: " . print_r($_SESSION, true));
  echo json_encode([
    'success' => false,
    'message' => 'No autorizado: sesión no válida',
    'redirect' => 'ingreso.php'
  ]);
  exit;
}
?>