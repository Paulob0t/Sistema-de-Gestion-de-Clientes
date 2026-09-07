<?php
// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Registrar errores
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/hosting_error.log');

// Función para enviar respuesta JSON
function sendJsonResponse($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Incluir conexiones
include "conn.php";
include "conn_hostingpro.php";
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

try {
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    // Verificar si es edición o creación
    $modo_edicion = isset($_POST['modo_edicion']) && $_POST['modo_edicion'] == '1';
    

    // Obtener datos del formulario
    $cliente_id = (int)$_POST['cliente'];
    if ($cliente_id <= 0 || !solicitudes_cliente_es_activo($conn, $cliente_id)) {
        throw new Exception('El cliente seleccionado no está activo o no es válido');
    }
    $dominio= $_POST['dominio'];
    $nom_host = 'cpanel.' . str_replace('cpanel.', '', $_POST['nom_host']);
    $usuario = $_POST['usuario'];
    $contrasena = $_POST['contrasena'];
    $contrasena_segura =($contrasena); 
    $tipo_producto = $_POST['tipo_producto'];
    $producto =(int)$_POST['producto'] ??0;
    $costo_producto = (float)$_POST['costo_producto'];
    $id_forma_pago = (int)$_POST['id_forma_pago'];
    $fecha_contratacion = $_POST['fecha_contratacion'];
    $fecha_pago = isset($_POST['fecha_pago']) ? trim($_POST['fecha_pago']) : '';

        $ns1= $_POST['ns1'];
        $ns2= $_POST['ns2'];
        $ns3= $_POST['ns3'];
        $ns4= $_POST['ns4'];
        $ns5= $_POST['ns5'];
        $ns6= $_POST['ns6'];

    // Validar fecha
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_contratacion)) {
        throw new Exception('Formato de fecha de contratación inválido');
    }
    
    // Normalizar fecha de pago:
    // 1) Si viene válida del formulario, usarla.
    // 2) Si viene vacía en edición, conservar la fecha actual del hosting.
    // 3) Si no hay fecha previa, usar +1 año desde fecha_contratacion.
    $fecha_pago_valida = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_pago) && $fecha_pago !== '0000-00-00';

    if (!$fecha_pago_valida && $modo_edicion) {
        $id_orden_tmp = isset($_POST['id_orden']) ? (int)$_POST['id_orden'] : 0;
        if ($id_orden_tmp > 0) {
            $sql_fecha_actual = "SELECT fecha_pago FROM hosting WHERE id_orden = ? LIMIT 1";
            $stmt_fecha_actual = $conn->prepare($sql_fecha_actual);
            if ($stmt_fecha_actual) {
                $stmt_fecha_actual->bind_param("i", $id_orden_tmp);
                $stmt_fecha_actual->execute();
                $res_fecha_actual = $stmt_fecha_actual->get_result();
                if ($res_fecha_actual && $res_fecha_actual->num_rows > 0) {
                    $row_fecha_actual = $res_fecha_actual->fetch_assoc();
                    $fecha_guardada = $row_fecha_actual['fecha_pago'] ?? '';
                    if (!empty($fecha_guardada) && $fecha_guardada !== '0000-00-00') {
                        $fecha_pago = $fecha_guardada;
                        $fecha_pago_valida = true;
                    }
                }
                $stmt_fecha_actual->close();
            }
        }
    }

    if (!$fecha_pago_valida) {
        $fecha_pago = date('Y-m-d', strtotime($fecha_contratacion . ' +1 year'));
    }
    
  
    
    // Campos opcionales con valores por defecto
    $url_pago = '';
    $url_acceso = '';
    $dns = '';
    $estado_producto = 1;
    $IVA = 0; // O ajusta según tu lógica
    $eliminado = 0;
    
    if ($modo_edicion) {
        // Modo edición - Actualizar registro
        $id_orden = (int)$_POST['id_orden'];
        
        $sql = "UPDATE hosting SET 
                dominio = ?,
                nom_host = ?,
                usuario = ?,
                contrasena_normal = ?,
                contrasena = ?,
                tipo_producto = ?,
                producto = ?,
                costo_producto = ?,
                id_forma_pago = ?,
                fecha_contratacion = ?,
                fecha_pago = ?,
                cliente_id = ?,
                ns1 = ?,
                ns2 = ?,
                ns3 = ?,
                ns4 = ?,
                ns5 = ?,
                ns6 = ?,
                dns = ?,
                url_pago = ?,
                url_acceso = ?,
                estado_producto = ?,
                IVA = ?,
                eliminado = ?
            WHERE id_orden = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ssssssidissssssssssssiiii",
            $dominio,
            $nom_host,
            $usuario,
            $contrasena,
            $contrasena,
            $tipo_producto,
            $producto,
            $costo_producto,
            $id_forma_pago,
            $fecha_contratacion,
            $fecha_pago,
            $cliente_id,
            $ns1,
            $ns2,
            $ns3,
            $ns4,
            $ns5,
            $ns6,
            $dns,
            $url_pago,
            $url_acceso,
            $estado_producto,
            $IVA,
            $eliminado,
            $id_orden
        );
        
        $action = 'actualizado';
        $redirect = 'detalle_cliente.php?id=' . $cliente_id;
    } else {
        // Modo creación - Insertar nuevo registro      
          $sql = "INSERT INTO hosting (
                dominio, nom_host, usuario,contrasena_normal, contrasena, tipo_producto, producto,
                costo_producto, id_forma_pago, dns, url_pago, url_acceso,
                ns1, ns2, ns3, ns4, ns5, ns6, fecha_contratacion, fecha_pago,
                estado_producto, cliente_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssisdissssssssssii",
            $dominio,
            $nom_host,
            $usuario,
            $contrasena,
            $contrasena,
            $tipo_producto,
            $producto,
            $costo_producto,
            $id_forma_pago,
            $dns,
            $url_pago,
            $url_acceso,
            $ns1,
            $ns2,
            $ns3,
            $ns4,
            $ns5,
            $ns6,
            $fecha_contratacion,
            $fecha_pago,
            $estado_producto,
            $cliente_id
        );
        
        $action = 'creado';
        $redirect = 'formulario_hosting.php';
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Error al guardar en la base de datos: ' . $stmt->error);
    }
    
    // Respuesta exitosa
    sendJsonResponse([
        'success' => true,
        'message' => 'Hosting ' . $action . ' correctamente',
        'redirect' => $redirect
    ]);
    
} catch (Exception $e) {
    // Respuesta de error
    sendJsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
}
?>
