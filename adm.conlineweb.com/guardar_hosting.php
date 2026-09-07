<?php
require_once __DIR__ . '/auth_middleware.php';
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

// Incluir conexión
include "conn.php";
include "conn_hostingpro.php";
require_once __DIR__ . '/includes/helpers_clientes.php';

$sistema = 'conlineweb';
if (isset($_POST['sistema'])) {
    if ($_POST['sistema'] === 'hostingpro') {
        $sistema = 'hostingpro';
    } elseif ($_POST['sistema'] === 'planpro') {
        $sistema = 'planpro';
    }
}
$db = ($sistema === 'hostingpro' || $sistema === 'planpro') ? $conn_hp : $conn;

try {
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    // Verificar si es edición o creación
    $modo_edicion = isset($_POST['modo_edicion']) && $_POST['modo_edicion'] == '1';
    
    // Validar campos obligatorios
    if ($modo_edicion) {
        // En modo edición, verificar que al menos tenga cliente (original o nuevo)
        if (empty($_POST['cliente']) && empty($_POST['cliente_id'])) {
            throw new Exception('Se requiere un cliente válido');
        }
    } else {
        $required_fields = ['cliente'];
    }

    // Obtner datos del formulario
    // Manejar cliente_id dependiendo del modo
    if ($modo_edicion) {
        // En modo edición, verificar si se está cambiando el cliente
        if (isset($_POST['cliente']) && !empty($_POST['cliente'])) {
            // Se está cambiando el cliente (checkbox marcado)
            $cliente_id = (int)$_POST['cliente'];
        } else {
            // No se está cambiando el cliente (usar el original)
            $cliente_id = (int)$_POST['cliente_id'];
        }
    } else {
        // En modo normal, usar el campo cliente
        $cliente_id = (int)$_POST['cliente'];
    }
    
    // Validar que el cliente_id sea válido
    if (!$cliente_id || $cliente_id <= 0) {
        throw new Exception('ID de cliente inválido');
    }

    // En alta o al cambiar cliente: solo clientes activos (mismo criterio que solicitudes)
    $cambiandoCliente = !$modo_edicion || (!empty($_POST['cliente']) && (int) $_POST['cliente'] > 0);
    if ($cambiandoCliente && !solicitudes_cliente_es_activo($db, (int) $cliente_id)) {
        throw new Exception('El cliente seleccionado no está activo o no es válido');
    }
    
$dominio = $_POST['dominio'] ?? '';
$producto = isset($_POST['producto']) ? (int)$_POST['producto'] : 0;
$id_forma_pago = isset($_POST['id_forma_pago']) ? (int)$_POST['id_forma_pago'] : 0;    
$nom_host = 'cpanel.' . str_replace('cpanel.', '', $_POST['nom_host']);
    $usuario = $_POST['usuario'];
    $contrasena = $_POST['contrasena'];
    $contrasena_segura =($contrasena); 
    $tipo_producto = $_POST['tipo_producto'];
    $costo_producto = (float)$_POST['costo_producto'];
    $fecha_contratacion = isset($_POST['fecha_contratacion']) ? trim($_POST['fecha_contratacion']) : '';
    $fecha_pago = isset($_POST['fecha_pago']) ? trim($_POST['fecha_pago']) : '';

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_contratacion)) {
        throw new Exception('Formato de fecha de contratación inválido');
    }

    $fecha_pago_valida = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_pago) && $fecha_pago !== '0000-00-00';

    // En edición, si fecha_pago viene vacía/incorrecta, conservar la fecha guardada.
    if (!$fecha_pago_valida && $modo_edicion) {
        $id_orden_tmp = isset($_POST['id_orden']) ? (int)$_POST['id_orden'] : 0;

        if ($id_orden_tmp > 0) {
            if ($sistema === 'planpro') {
                $sql_fecha_actual = "SELECT fecha_vencimiento AS fecha_pago FROM servicios_web WHERE id = ? LIMIT 1";
            } else {
                $sql_fecha_actual = "SELECT fecha_pago FROM hosting WHERE id_orden = ? LIMIT 1";
            }

            $stmt_fecha_actual = $db->prepare($sql_fecha_actual);
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

    // Fallback final: si no hay fecha de pago válida, usar +1 año desde fecha_contratacion.
    if (!$fecha_pago_valida) {
        $fecha_pago = date('Y-m-d', strtotime($fecha_contratacion . ' +1 year'));
    }

        $ns1= $_POST['ns1'];
        $ns2= $_POST['ns2'];
        $ns3= $_POST['ns3'];
        $ns4= $_POST['ns4'];
        $ns5= $_POST['ns5'];
        $ns6= $_POST['ns6'];

    $url_pago = '';
    $url_acceso = '';
    $dns = '';
    $estado_producto = isset($_POST['estado_producto']) ? (int) $_POST['estado_producto'] : 1;
    $estado_producto = $estado_producto === 1 ? 1 : 0;
    $estado_planpro = $estado_producto === 1 ? 'activo' : 'inactivo';
    
    if ($modo_edicion) {
        // Modo edición - Actualizar registro
        $id_orden = (int)$_POST['id_orden'];
        
        if ($sistema === 'planpro') {
            // Plan Pro: Actualizar servicios_web
            $sql = "UPDATE servicios_web SET 
                    dominio = ?,
                    usuario_cpanel = ?,
                    password_cpanel = ?,
                    plan_categoria = ?,
                    plan_nombre = ?,
                    plan_precio = ?,
                    id_forma_pago = ?,
                    fecha_contratacion = ?,
                    fecha_vencimiento = ?,
                    cliente_id = ?,
                    ns1 = ?,
                    ns2 = ?,
                    estado = ?
                    WHERE id = ?";
            
            $stmt = $db->prepare($sql);
            $stmt->bind_param(
                "sssssdisissssi",
                $nom_host,
                $usuario,
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
                $estado_planpro,
                $id_orden
            );
        } else {
            // ConlineWeb/HostingPro: Actualizar hosting
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
                    estado_producto = ?
                    WHERE id_orden = ?";
            
            $stmt = $db->prepare($sql);
            $stmt->bind_param(
                "ssssssidisssssssssii",
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
                $estado_producto,
                $id_orden
            );
        }
        
        $action = 'actualizado';
        $redirect = 'detalle_cliente.php?id=' . $cliente_id . '&sistema=' . $sistema;
    } else {
        // Modo creación - Insertar nuevo registro      
          $sql = "INSERT INTO hosting (
                dominio, nom_host, usuario,contrasena_normal, contrasena, tipo_producto, producto,
                costo_producto, id_forma_pago, dns, url_pago, url_acceso,
                ns1, ns2, ns3, ns4, ns5, ns6, fecha_contratacion, fecha_pago,
                estado_producto, cliente_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $db->prepare($sql);
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
        $redirect = 'formulario_hosting.php?sistema=' . $sistema;
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
    if (isset($conn_hp)) $conn_hp->close();
}
?>