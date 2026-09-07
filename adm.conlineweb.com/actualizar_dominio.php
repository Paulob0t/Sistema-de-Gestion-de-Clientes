<?php
// 🔧 Configuración estricta de errores
error_reporting(0); // Desactivar errores en pantalla
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log'); // Crear log de errores

// 🔐 Función para enviar respuesta JSON limpia
function sendJsonResponse($data) {
    // Limpiar cualquier salida previa
    if (ob_get_length()) ob_clean();
    
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// 🔍 Función para logging personalizado
function logError($message, $data = null) {
    $logMessage = date('Y-m-d H:i:s') . " - " . $message;
    if ($data !== null) {
        $logMessage .= " - Data: " . print_r($data, true);
    }
    error_log($logMessage . "\n", 3, __DIR__ . '/dominio_debug.log');
}

// 🛡️ Manejo de errores fatal
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        logError("Fatal Error", $error);
        sendJsonResponse([
            'success' => false,
            'message' => 'Error interno del servidor. Revisa los logs.'
        ]);
    }
});

ob_start(); // Iniciar buffer

try {
    // 🔗 Incluir conexión con manejo de errores
    if (!file_exists('conn.php')) {
        throw new Exception('Archivo de conexión no encontrado');
    }
    
    include 'conn.php';
    include 'conn_hostingpro.php';
    require_once __DIR__ . '/includes/helpers_clientes.php';
    
    // Detectar sistema desde POST
    $sistema = 'conlineweb';
    if (isset($_POST['sistema'])) {
        if ($_POST['sistema'] === 'hostingpro') {
            $sistema = 'hostingpro';
        } elseif ($_POST['sistema'] === 'planpro') {
            $sistema = 'planpro';
        }
    }
    $conn = ($sistema === 'hostingpro' || $sistema === 'planpro') ? $conn_hp : $conn;
    
    // Verificar conexión
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception('Error de conexión a la base de datos: ' . ($conn->connect_error ?? 'Conexión no establecida'));
    }
    
    logError("Inicio de proceso - Método: " . $_SERVER["REQUEST_METHOD"]);
    
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        throw new Exception('Método no permitido. Solo se acepta POST.');
    }
    
    // 🔍 Log de datos recibidos
    logError("Datos POST recibidos", $_POST);
    file_put_contents('debug_post.log', print_r($_POST, true));
    
    // Verificar si es modo edición
    $modo_edicion = isset($_POST['modo_edicion']) && $_POST['modo_edicion'] == '1';
    $id_dominio = $modo_edicion ? $_POST['id_dominio'] : null;
    
    // 📋 Validar campos obligatorios
    $required_fields = ['cliente', 'proveedor', 'url_dominio', 'url_admin', 'costo_dominio', 'id_forma_pago'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if (!isset($_POST['registrado'])) {
        $missing_fields[] = 'registrado';
    }
    if (!isset($_POST['estado_dominio'])) {
        $missing_fields[] = 'estado_dominio';
    }
    
    if (empty($_POST['usuario'])) {
        $missing_fields[] = 'usuario';
    }
    
    if (!empty($missing_fields)) {
        logError("Campos faltantes", $missing_fields);
        throw new Exception('Faltan campos obligatorios: ' . implode(', ', $missing_fields));
    }
    
    // 🧹 Sanitizar y validar datos
    $cliente_id = filter_var($_POST['cliente'], FILTER_VALIDATE_INT);
    if ($cliente_id === false) {
        throw new Exception('ID de cliente inválido');
    }
    if (!solicitudes_cliente_es_activo($conn, (int) $cliente_id)) {
        throw new Exception('El cliente seleccionado no está activo o no es válido');
    }
    
    $costo_dominio = filter_var($_POST['costo_dominio'], FILTER_VALIDATE_FLOAT);
    if ($costo_dominio === false) {
        throw new Exception('Costo de dominio inválido');
    }
    
    $id_forma_pago = filter_var($_POST['id_forma_pago'], FILTER_VALIDATE_INT);
    if ($id_forma_pago === false) {
        throw new Exception('Forma de pago inválida');
    }
    
    $registrado = filter_var($_POST['registrado'], FILTER_VALIDATE_INT);
    if ($registrado === false || !in_array($registrado, [0, 1], true)) {
        throw new Exception('Gestión inválida (registrado: 1=ConlineWeb, 0=externo)');
    }

    $estado_dominio = filter_var($_POST['estado_dominio'], FILTER_VALIDATE_INT);
    if ($estado_dominio === false || !in_array($estado_dominio, [0, 1], true)) {
        throw new Exception('Estado del dominio inválido (1=Activo, 0=Inactivo)');
    }
    
   

    // Asignar variables con sanitización
    $proveedor = trim($_POST['proveedor']);
    $url_dominio = $_POST['url_dominio'];
    $url_admin = $_POST['url_admin'];

    
   
    
    $fecha_contratacion = $_POST['fecha_contratacion'];
    // Validar formato de fecha YYYY-MM-DD
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_contratacion)) {
        throw new Exception('Formato de fecha de contratación inválido');
    }
    
    $fecha_contratacion_dt = new DateTime($fecha_contratacion);
    $fecha_pago_dt = clone $fecha_contratacion_dt;
    $fecha_pago_dt->modify('+1 year');
    $fecha_pago = $fecha_pago_dt->format('Y-m-d');

    // Variables opcionales
    $usuario_admin = !empty($_POST['usuario_admin']) ? trim($_POST['usuario_admin']) : null;
    $contrasena_admin = !empty($_POST['contrasena_admin']) ? trim($_POST['contrasena_admin']) : null;
    $contrasena_segura = ($contrasena_admin);
    $url_cpanel = !empty($_POST['url_cpanel']) ? filter_var(trim($_POST['url_cpanel']), FILTER_VALIDATE_URL) : null;
    $ns1 = !empty($_POST['ns1']) ? trim($_POST['ns1']) : null;
    $ns2 = !empty($_POST['ns2']) ? trim($_POST['ns2']) : null;
    $ns3 = !empty($_POST['ns3']) ? trim($_POST['ns3']) : null;
    $ns4 = !empty($_POST['ns4']) ? trim($_POST['ns4']) : null;
    $ns5 = !empty($_POST['ns5']) ? trim($_POST['ns5']) : null;
    $ns6 = !empty($_POST['ns6']) ? trim($_POST['ns6']) : null;
    
    // 🔍 Log de datos procesados
    logError("Datos procesados correctamente", [
        'modo_edicion' => $modo_edicion,
        'id_dominio' => $id_dominio,
        'cliente_id' => $cliente_id,
        'proveedor' => $proveedor,
        'costo' => $costo_dominio,
        'registrado' => $registrado,
        'estado_dominio' => $estado_dominio,
    ]);
    
    if ($modo_edicion) {
        // 💾 Modo EDICIÓN - Actualizar dominio existente
        $sql = "UPDATE dominios SET 
                cliente_id = ?,
                proveedor = ?,
                url_dominio = ?,
                url_admin = ?,
                usuario = ?,
                contrasena = ?,
                contrasena_normal = ?,
                url_cpanel = ?,
                ns1 = ?,
                ns2 = ?,
                ns3 = ?,
                ns4 = ?,
                ns5 = ?,
                ns6 = ?,
                costo_dominio = ?,
                id_forma_pago = ?,
                fecha_contratacion = ?,
                fecha_pago = ?,
                estado_dominio = ?,
                registrado = ?
                WHERE id_dominio = ?";
        
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Error al preparar consulta de actualización: ' . $conn->error);
        }
        
        $bind_result = $stmt->bind_param(
            "isssssssssssssdissiii",
            $cliente_id, $proveedor, $url_dominio, $url_admin, $usuario_admin,$contrasena_admin,
            $contrasena_admin, $url_cpanel, $ns1, $ns2, $ns3, $ns4, $ns5, $ns6,
            $costo_dominio, $id_forma_pago, $fecha_contratacion, $fecha_pago, 
            $estado_dominio, $registrado, $id_dominio
        );
        
        $action = 'actualizado';
    } else {
        // 💾 Modo CREACIÓN - Insertar nuevo dominio
        $sql = "INSERT INTO dominios (
        cliente_id, proveedor, url_dominio, url_admin, usuario, 
        contrasena, contrasena_normal, url_cpanel, ns1, ns2, ns3, ns4, ns5, ns6, 
        costo_dominio, id_forma_pago, fecha_contratacion, fecha_pago, estado_dominio, registrado
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"; // 20 signos de interrogación
        
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Error al preparar consulta de inserción: ' . $conn->error);
        }
        
        $bind_result = $stmt->bind_param(
    "isssssssssssssdissii", // 20 caracteres de tipo
    $cliente_id,            // i (entero)
    $proveedor,             // s (string)
    $url_dominio,           // s
    $url_admin,             // s
    $usuario_admin,         // s
    $contrasena_admin,     // s
    $contrasena_admin,      // s
    $url_cpanel,            // s
    $ns1,                   // s
    $ns2,                   // s
    $ns3,                   // s
    $ns4,                   // s
    $ns5,                   // s
    $ns6,                   // s
    $costo_dominio,         // d (double/float)
    $id_forma_pago,         // i
    $fecha_contratacion,    // s (aunque es fecha, se trata como string)
    $fecha_pago,            // s
    $estado_dominio,        // i
    $registrado             // i
);
        
        $action = 'registrado';
    }
    
    if (!$bind_result) {
        throw new Exception('Error al vincular parámetros: ' . $stmt->error);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Error al ejecutar consulta: ' . $stmt->error);
    }
    
    if ($modo_edicion) {
        $affected_rows = $stmt->affected_rows;
        logError("Dominio actualizado exitosamente", [
            'id_dominio' => $id_dominio,
            'affected_rows' => $affected_rows
        ]);
    } else {
        $insert_id = $conn->insert_id;
        logError("Dominio registrado exitosamente", ['insert_id' => $insert_id]);
    }
    
    $stmt->close();
    
    // ✅ Respuesta exitosa
    sendJsonResponse([
        'success' => true,
        'message' => 'El dominio se ha '.$action.' correctamente.',
        'action' => $action,
        'domain_id' => $modo_edicion ? $id_dominio : $insert_id,
        'redirect' => $modo_edicion ? 'detalle_cliente.php?id='.$cliente_id : 'formulario_dominio.php'
    ]);
    
} catch (Exception $e) {
    logError("Error capturado: " . $e->getMessage());
    
    // 🚨 Respuesta de error
    sendJsonResponse([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => $modo_edicion ? 'DOMAIN_UPDATE_ERROR' : 'DOMAIN_REGISTRATION_ERROR'
    ]);
} finally {
    // 🧹 Limpiar recursos
    if (ob_get_length()) ob_end_clean();
    if (isset($conn)) $conn->close();
}

// Si solo llega 'usuario', úsalo como 'usuario_admin'
if (empty($_POST['usuario_admin']) && !empty($_POST['usuario'])) {
    $_POST['usuario_admin'] = $_POST['usuario'];
}
$usuario_admin = !empty($_POST['usuario_admin']) ? trim($_POST['usuario_admin']) : null;

file_put_contents('debug_post.log', print_r([
    'usuario' => $_POST['usuario'] ?? null,
    'usuario_admin' => $_POST['usuario_admin'] ?? null,
    'usuario_admin_var' => $usuario_admin
], true), FILE_APPEND);
?>
