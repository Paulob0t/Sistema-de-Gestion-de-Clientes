<?php
require_once __DIR__ . '/auth_middleware.php';
header('Content-Type: application/json');

function sendJsonResponse($success, $message, $data = null) {
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    sendJsonResponse(false, "Método no permitido");
}

try {
    session_start();
    require "conn.php";

    if (!isset($conn)) {
        throw new Exception("No se pudo establecer la conexión a la base de datos");
    }
    
    // Obtener el ID del usuario que está registrando el cliente
    $uid = $_SESSION['uid'] ?? null;

    if ($conn->connect_error) {
        throw new Exception("Error de conexión: " . $conn->connect_error);
    }

    // Capturar y limpiar datos
    $nombre = trim($_POST["nombre"] ?? "");
    $apellido = trim($_POST["apellido"] ?? "");
    $telefono = trim($_POST["telefono"] ?? "");
    $correo = trim($_POST["email"] ?? "");
    $empresa = trim($_POST["empresa"] ?? "");
    $rsocial = trim($_POST["rsocial"] ?? "");
    $rfc = trim($_POST["rfc"] ?? "");
    $especificacion = trim($_POST["especificacion"] ?? "");
    $calle = trim($_POST["calle"] ?? "");
    $next = trim($_POST["next"] ?? "");
    $nint = trim($_POST["nint"] ?? "");
    $col = trim($_POST["col"] ?? "");
    $cp = trim($_POST["cp"] ?? "");
    $pais = trim($_POST["pais"] ?? "");
    $estado = trim($_POST["estado"] ?? "");
    $ciudad = trim($_POST["municipio"] ?? "");
    $contrasena = trim($_POST["contrasena"] ?? "");
    $facturacion = trim($_POST['facturacion'] ?? '');

    // ⚠️ Validaciones
    if (empty($nombre) || empty($apellido) || empty($correo) || empty($telefono) || empty($contrasena)) {
        sendJsonResponse(false, "Nombre, apellido, correo, teléfono y contraseña son obligatorios");
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        sendJsonResponse(false, "Correo electrónico no válido");
    }


    if (!empty($rfc) && !preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/i', $rfc)) {
        sendJsonResponse(false, "RFC no válido");
    }

    if (strlen($contrasena) < 6) {
        sendJsonResponse(false, "La contraseña debe tener al menos 6 caracteres");
    }

    if (!empty($cp) && !preg_match('/^\d{5}$/', $cp)) {
        sendJsonResponse(false, "Código postal no válido");
    }

    $contrasena_segura = md5($contrasena);

    // Archivo
    $nombre_archivo = '';
    if (isset($_FILES['constancia_fiscal'])) {
        if ($_FILES['constancia_fiscal']['error'] === UPLOAD_ERR_OK) {
            $archivo = $_FILES['constancia_fiscal'];
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

            $permitidas = ['pdf', 'jpg', 'jpeg', 'png'];
            if (!in_array($extension, $permitidas)) {
                sendJsonResponse(false, "Formato de archivo no permitido. Solo PDF, JPG, PNG");
            }

            if ($archivo['size'] > 5 * 1024 * 1024) {
                sendJsonResponse(false, "El archivo excede los 5MB permitidos");
            }

            $nombre_archivo = 'constancia_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
            $ruta_destino = "constancias_fiscales/" . $nombre_archivo;

            if (!is_dir("constancias_fiscales")) {
                mkdir("constancias_fiscales", 0777, true);
            }

            if (!move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
                sendJsonResponse(false, "Error al subir el archivo de constancia fiscal");
            }
        } else {
            $errores = [
                UPLOAD_ERR_INI_SIZE => "El archivo excede el tamaño permitido por el servidor",
                UPLOAD_ERR_FORM_SIZE => "El archivo excede el tamaño permitido por el formulario",
                UPLOAD_ERR_PARTIAL => "El archivo se subió parcialmente",
                UPLOAD_ERR_NO_FILE => "No se subió ningún archivo"
            ];
            $error_mensaje = $errores[$_FILES['constancia_fiscal']['error']] ?? "Error desconocido en la subida del archivo";
            sendJsonResponse(false, $error_mensaje);
        }
    }

    $nombre_contacto = "$nombre $apellido";

    // Iniciar transacción
    if (!$conn->begin_transaction()) {
        throw new Exception("Error al iniciar la transacción");
    }

    // Verificar correo existente
    $stmt = $conn->prepare("SELECT id FROM login WHERE usuario = ?");
    if (!$stmt) throw new Exception("Error al preparar consulta: " . $conn->error);

    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) throw new Exception("El correo electrónico ya está registrado");
    $stmt->close();

    // Insertar cliente
    $stmt = $conn->prepare("INSERT INTO clientes (
        nombre_contacto, empresa, correo, telefono, 
        especificacion, rsocial, rfc, calle, 
        next, nint, col, cp, pais, estado, ciudad, 
        constancia_situacion_fiscal, facturacion, usuario_registro
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if (!$stmt) throw new Exception("Error al preparar la inserción del cliente: " . $conn->error);

    $stmt->bind_param("sssssssssssssssssi", 
        $nombre_contacto, $empresa, $correo, $telefono,
        $especificacion, $rsocial, $rfc, $calle,
        $next, $nint, $col, $cp, $pais, $estado, $ciudad,
        $nombre_archivo, $facturacion, $uid
    );

    if (!$stmt->execute()) throw new Exception("Error al guardar cliente: " . $stmt->error);

    $cliente_id = $stmt->insert_id;
    $stmt->close();

    if (!$cliente_id) throw new Exception("Error al obtener el ID del cliente");

    // Insertar login
    $stmt = $conn->prepare("INSERT INTO login (id, usuario, contrasena, contrasena_normal) VALUES (?, ?, ?, ?)");
    if (!$stmt) throw new Exception("Error al preparar la inserción del login: " . $conn->error);

    $stmt->bind_param("isss", $cliente_id, $correo, $contrasena_segura, $contrasena);
    if (!$stmt->execute()) throw new Exception("Error al crear acceso: " . $stmt->error);
    $stmt->close();

    if (!$conn->commit()) throw new Exception("Error al confirmar la transacción");

    sendJsonResponse(true, "Cliente registrado correctamente", [
        'cliente_id' => $cliente_id,
        'nombre_contacto' => $nombre_contacto
    ]);

} catch (Exception $e) {
    if (isset($conn)) {
        try { $conn->rollback(); } catch (Exception $ex) {}
    }
    sendJsonResponse(false, $e->getMessage());
} finally {
    if (isset($conn)) {
        try { $conn->close(); } catch (Exception $ex) {}
    }
}
