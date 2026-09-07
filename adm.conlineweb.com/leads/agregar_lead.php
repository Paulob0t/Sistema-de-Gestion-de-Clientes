<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../auth_middleware.php';
include __DIR__ . '/../conn.php';

$usuario_id = $_SESSION['uid'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $accion = isset($_POST['accion']) ? $_POST['accion'] : 'agregar';
        
        // Validar campos requeridos
        $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
        $correo = isset($_POST['correo']) ? trim($_POST['correo']) : '';
        $telefono = isset($_POST['telefono']) ? trim($_POST['telefono']) : '';
        $requerimiento = isset($_POST['requerimiento']) ? trim($_POST['requerimiento']) : '';
        $empresa = isset($_POST['empresa']) ? trim($_POST['empresa']) : '';
        $pais = isset($_POST['pais']) ? trim($_POST['pais']) : 'México';
        $estatus = isset($_POST['estatus']) ? $_POST['estatus'] : 'Activo';
        $nota = isset($_POST['nota']) ? trim($_POST['nota']) : '';
        
        if (empty($nombre) || empty($correo)) {
            echo json_encode(['success' => false, 'message' => 'Nombre y correo son obligatorios']);
            exit;
        }
        
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Correo electrónico inválido']);
            exit;
        }
        
        if ($accion === 'agregar') {
            // Verificar si el correo ya existe
            $stmt = $conn->prepare("SELECT id FROM leads WHERE correo = ?");
            $stmt->bind_param("s", $correo);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'El correo electrónico ya está registrado']);
                exit;
            }
            
            // Crear array de notas inicial
            $notas = [];
            if (!empty($nota)) {
                $notas[] = [
                    'nota' => $nota,
                    'fecha' => date('Y-m-d H:i:s'),
                    'usuario_id' => $usuario_id
                ];
            }
            $notas_json = json_encode($notas, JSON_UNESCAPED_UNICODE);
            
            // Insertar nuevo lead
            $stmt = $conn->prepare("INSERT INTO leads (nombre, correo, telefono, requerimiento, empresa, pais, estatus, notas, usuario_registro) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssi", $nombre, $correo, $telefono, $requerimiento, $empresa, $pais, $estatus, $notas_json, $usuario_id);
            
            if ($stmt->execute()) {
                $lead_id = $stmt->insert_id;
                
                // Obtener los datos del lead recién insertado para devolverlos
                $stmt = $conn->prepare("SELECT id, nombre, correo, telefono, requerimiento, empresa, pais, estatus, notas, fecha_registro, COALESCE(whatsapp_enviado, 0) AS whatsapp_enviado, whatsapp_enviado_fecha FROM leads WHERE id = ?");
                $stmt->bind_param("i", $lead_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $lead_data = $result->fetch_assoc();
                
                // Procesar las notas para la respuesta
                $notas_array = json_decode($lead_data['notas'], true) ?: [];
                $lead_data['total_notas'] = count($notas_array);
                $lead_data['ultima_nota'] = !empty($notas_array) ? end($notas_array)['nota'] : null;
                $lead_data['notas_json'] = $lead_data['notas'];
                $lead_data['id_real'] = $lead_data['id'];
                $lead_data['origen'] = 'Manual';
                $lead_data['whatsapp_enviado'] = (int)($lead_data['whatsapp_enviado'] ?? 0);
                $lead_data['whatsapp_enviado_fecha'] = $lead_data['whatsapp_enviado_fecha'] ?? null;
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Lead agregado exitosamente',
                    'data' => $lead_data
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al agregar el lead: ' . $stmt->error]);
            }
            
        } elseif ($accion === 'editar') {
            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
            
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID inválido']);
                exit;
            }
            
            // Obtener notas actuales
            $stmt = $conn->prepare("SELECT notas FROM leads WHERE id = ? AND eliminado = 0");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                echo json_encode(['success' => false, 'message' => 'Lead no encontrado']);
                exit;
            }
            
            $row = $result->fetch_assoc();
            $notas = json_decode($row['notas'], true) ?: [];
            
            // Agregar nueva nota si existe
            if (!empty($nota)) {
                $notas[] = [
                    'nota' => $nota,
                    'fecha' => date('Y-m-d H:i:s'),
                    'usuario_id' => $usuario_id
                ];
            }
            $notas_json = json_encode($notas, JSON_UNESCAPED_UNICODE);
            
            // Obtener el estatus anterior antes de actualizar
            $stmt_check = $conn->prepare("SELECT estatus FROM leads WHERE id = ? AND eliminado = 0");
            $stmt_check->bind_param("i", $id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            $estatus_anterior = $result_check->fetch_assoc()['estatus'];
            
            // Actualizar lead
            $stmt = $conn->prepare("UPDATE leads SET nombre = ?, correo = ?, telefono = ?, requerimiento = ?, empresa = ?, pais = ?, estatus = ?, notas = ? WHERE id = ? AND eliminado = 0");
            $stmt->bind_param("ssssssssi", $nombre, $correo, $telefono, $requerimiento, $empresa, $pais, $estatus, $notas_json, $id);
            
            if ($stmt->execute()) {
                $mensaje = 'Lead actualizado exitosamente';
                
                // Si cambió a estatus "Cliente", insertar en la tabla clientes
                if ($estatus === 'Cliente' && $estatus_anterior !== 'Cliente') {
                    // Verificar si ya existe un cliente con este id_lead
                    $stmt_verificar = $conn->prepare("SELECT id FROM clientes WHERE id_lead = ?");
                    $stmt_verificar->bind_param("i", $id);
                    $stmt_verificar->execute();
                    $result_verificar = $stmt_verificar->get_result();
                    
                    if ($result_verificar->num_rows === 0) {
                        // Insertar en tabla clientes con datos básicos
                        $especificacion = $requerimiento; // Usar requerimiento como especificación inicial
                        $display = 'none';
                        $facturacion = 0;
                        $actualizado = 0;
                        $actualizar_correo = 0;
                        $eliminado = 0;
                        $constancia = '';
                        $correo_pendiente = '';
                        
                        // Campos vacíos que se llenarán después
                        $empresa_cliente = $empresa; // Usar la empresa del lead si existe
                        $rsocial = '';
                        $rfc = '';
                        $calle = '';
                        $next = '';
                        $nint = '';
                        $col = '';
                        $cp = '';
                        $pais_cliente = $pais; // Usar el país del lead
                        $estado = '';
                        $ciudad = '';
                        
                        $stmt_cliente = $conn->prepare("INSERT INTO clientes (nombre_contacto, empresa, correo, telefono, especificacion, rsocial, rfc, calle, next, nint, col, cp, pais, estado, ciudad, display, constancia_situacion_fiscal, facturacion, actualizado, correo_pendiente_actualizar, actualizar_correo, eliminado, id_lead, usuario_registro) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        
                        $stmt_cliente->bind_param("sssssssssssssssssiisiiii", 
                            $nombre, $empresa_cliente, $correo, $telefono, $especificacion, 
                            $rsocial, $rfc, $calle, $next, $nint, $col, $cp, 
                            $pais_cliente, $estado, $ciudad, $display, $constancia, 
                            $facturacion, $actualizado, $correo_pendiente, 
                            $actualizar_correo, $eliminado, $id, $usuario_id
                        );
                        
                        if ($stmt_cliente->execute()) {
                            $cliente_id = $stmt_cliente->insert_id;
                            
                            // Crear acceso al sistema con contraseña por defecto
                            $contrasena_defecto = '12345678';
                            $contrasena_segura = md5($contrasena_defecto);
                            
                            $stmt_login = $conn->prepare("INSERT INTO login (id, usuario, contrasena, contrasena_normal) VALUES (?, ?, ?, ?)");
                            $stmt_login->bind_param("isss", $cliente_id, $correo, $contrasena_segura, $contrasena_defecto);
                            
                            if ($stmt_login->execute()) {
                                $mensaje .= '. Cliente creado automáticamente en el sistema con acceso generado (contraseña: 12345678)';
                            } else {
                                $mensaje .= '. Cliente creado pero no se pudo generar el acceso automáticamente';
                            }
                            $stmt_login->close();
                        } else {
                            $mensaje .= '. Advertencia: No se pudo crear el cliente automáticamente';
                        }
                    } else {
                        $mensaje .= '. El cliente ya existe en el sistema';
                    }
                }
                
                echo json_encode(['success' => true, 'message' => $mensaje]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al actualizar el lead: ' . $stmt->error]);
            }
            
        } else {
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        }
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>
