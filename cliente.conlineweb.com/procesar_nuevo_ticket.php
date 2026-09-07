<?php
require_once __DIR__ . '/includes/cliente_session.php';
cliente_start_session();
header('Content-Type: application/json');

// Habilitar logging y display de errores temporalmente para depuración del 500
// (remover o desactivar en producción una vez resuelto)
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error_procesar_nuevo_ticket.log');

// Verificar que el usuario esté logueado
// Aceptamos tanto la sesión con clave 'id' como con 'uid' (varios scripts usan una u otra)
if (!cliente_is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

include 'conn.php';
require_once __DIR__ . '/config_uploads.php';

$usrid = (int) $_SESSION['uid'];

try {
    // Validar que se recibieron los datos requeridos
    if(!isset($_POST['titulo']) || !isset($_POST['descripcion']) || !isset($_POST['prioridad'])) {
        echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos']);
        exit();
    }

    // Limpiar y validar los datos
    $titulo = mysqli_real_escape_string($conn, trim($_POST['titulo']));
    $descripcion = mysqli_real_escape_string($conn, trim($_POST['descripcion']));
    $prioridad = mysqli_real_escape_string($conn, $_POST['prioridad']);
    $id_proyecto = !empty($_POST['id_proyecto']) ? (int)$_POST['id_proyecto'] : null;

    // Validar título
    if(empty($titulo) || strlen($titulo) < 5) {
        echo json_encode(['success' => false, 'message' => 'El título debe tener al menos 5 caracteres']);
        exit();
    }

    // Validar descripción
    if(empty($descripcion) || strlen($descripcion) < 10) {
        echo json_encode(['success' => false, 'message' => 'La descripción debe tener al menos 10 caracteres']);
        exit();
    }

    // Validar prioridad
    if(!in_array($prioridad, ['Alta', 'Media', 'Baja'])) {
        echo json_encode(['success' => false, 'message' => 'Prioridad inválida']);
        exit();
    }

    // Validar que el proyecto pertenezca al cliente (si se especifica)
    if($id_proyecto) {
        $proyecto_check = mysqli_query($conn, "
            SELECT id_proyecto 
            FROM proyectos 
            WHERE id_proyecto = $id_proyecto AND id_cliente = $usrid
        ");
        
        if(mysqli_num_rows($proyecto_check) == 0) {
            echo json_encode(['success' => false, 'message' => 'El proyecto especificado no existe o no te pertenece']);
            exit();
        }
    }

    // Manejar archivos adjuntos
    $archivos_subidos = [];
    $imagenes_subidas = [];
    $errores_subida = [];
    
    if(isset($_FILES['archivos']) && !empty($_FILES['archivos']['name'][0])) {
        $upload_dir_abs  = TICKETS_UPLOAD_FS;
        $upload_base_url = TICKETS_UPLOAD_URL; // Siempre URL absoluta pública confiable

        if(!is_dir($upload_dir_abs)) {
            if(!@mkdir($upload_dir_abs, 0755, true)) {
                $errores_subida[] = 'No se pudo crear el directorio de subidas.';
                @file_put_contents(TICKETS_UPLOAD_LOG, date('Y-m-d H:i:s')." | FATAL | No se pudo crear directorio: $upload_dir_abs\n", FILE_APPEND);
            }
        }
        if(is_dir($upload_dir_abs) && !is_writable($upload_dir_abs)) {
            $errores_subida[] = 'Directorio de subidas sin permisos de escritura.';
            @file_put_contents(TICKETS_UPLOAD_LOG, date('Y-m-d H:i:s')." | FATAL | Sin permisos escritura: $upload_dir_abs\n", FILE_APPEND);
        }
        
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip', 'rar'];
        $max_file_size = 10 * 1024 * 1024; // 10MB
        
        for($i = 0; $i < count($_FILES['archivos']['name']); $i++) {
            $error_code = $_FILES['archivos']['error'][$i];
            $file_name = $_FILES['archivos']['name'][$i];

            if($error_code !== UPLOAD_ERR_OK) {
                if($error_code !== UPLOAD_ERR_NO_FILE) {
                    $errores_subida[] = 'Error con "' . $file_name . '": código ' . $error_code;
                    @file_put_contents(TICKETS_UPLOAD_LOG, date('Y-m-d H:i:s')." | ERR | $file_name codigo=$error_code\n", FILE_APPEND);
                }
                continue;
            }

            $file_tmp = $_FILES['archivos']['tmp_name'][$i];
            $file_size = $_FILES['archivos']['size'][$i];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            // Validar extensión
            if(!in_array($file_ext, $allowed_extensions)) {
                $errores_subida[] = 'Extensión no permitida: ' . $file_name;
                @file_put_contents(TICKETS_UPLOAD_LOG, date('Y-m-d H:i:s')." | EXT | $file_name $file_ext no permitida\n", FILE_APPEND);
                continue;
            }

            // Validar tamaño
            if($file_size > $max_file_size) {
                $errores_subida[] = 'Archivo muy grande (>10MB): ' . $file_name;
                @file_put_contents(TICKETS_UPLOAD_LOG, date('Y-m-d H:i:s')." | SIZE | $file_name $file_size bytes excede\n", FILE_APPEND);
                continue;
            }

            // Generar nombre único
            if(!$upload_dir_abs) {
                continue; // No hay carpeta válida
            }

            $unique_name = date('YmdHis') . '_' . $usrid . '_' . substr(sha1(uniqid('', true)), 0, 10) . '.' . $file_ext;
            $file_path_abs = $upload_dir_abs . $unique_name;      // Ruta física final
            $file_path_rel = $upload_base_url . $unique_name;     // URL pública absoluta

            // Mover archivo (usar ruta absoluta)
            if(is_uploaded_file($file_tmp) && move_uploaded_file($file_tmp, $file_path_abs)) {
                $archivo_info = [
                    'nombre_original' => $file_name,
                    'nombre_archivo' => $unique_name,
                    'ruta' => $file_path_rel,
                    'tamaño' => $file_size,
                    'tipo' => $_FILES['archivos']['type'][$i],
                    'extension' => $file_ext,
                    'origen' => ($upload_base_url)
                ];

                // Logging
                @file_put_contents(TICKETS_UPLOAD_LOG, date('Y-m-d H:i:s') . " | OK | $file_name -> $file_path_abs (json: $file_path_rel)\n", FILE_APPEND);

                // Separar imágenes de otros archivos
                if(in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $imagenes_subidas[] = $archivo_info;
                } else {
                    $archivos_subidos[] = $archivo_info;
                }
            } else {
                $errores_subida[] = 'No se pudo mover el archivo: ' . $file_name;
                @file_put_contents(TICKETS_UPLOAD_LOG, date('Y-m-d H:i:s') . " | FAIL | $file_name -> $file_path_abs tmp=$file_tmp"."\n", FILE_APPEND);
            }
        }
    }

    // Formatear la descripción como JSON (para mantener compatibilidad con el sistema existente)
    $descripcion_json = json_encode([
        'text' => $descripcion,
        'images' => $imagenes_subidas,
        'files' => $archivos_subidos
    ], JSON_UNESCAPED_UNICODE);

    // Prevención básica contra envíos duplicados rápidos
    // Si ya existe un ticket con el mismo título y descripción para este usuario en los últimos 30 segundos, rechazamos.
    $time_window_seconds = 30;
    $time_limit = date('Y-m-d H:i:s', time() - $time_window_seconds);
    $titulo_esc = mysqli_real_escape_string($conn, $titulo);
    $descripcion_search = mysqli_real_escape_string($conn, $descripcion_json);
    $dup_check_sql = "SELECT id FROM solicitudes WHERE id_cliente = $usrid AND titulo = '$titulo_esc' AND descripcion = '$descripcion_search' AND fecha_solicitud >= '$time_limit' LIMIT 1";
    $dup_res = mysqli_query($conn, $dup_check_sql);
    if($dup_res && mysqli_num_rows($dup_res) > 0) {
        @file_put_contents(TICKETS_UPLOAD_LOG, date('Y-m-d H:i:s') . " | DUP | Ticket duplicado detectado para usuario $usrid titulo='$titulo_esc'\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Parece que ya enviaste este ticket recientemente. Espera unos segundos antes de intentar nuevamente.']);
        exit();
    }

    // Insertar el nuevo ticket
    $fecha_actual = date('Y-m-d H:i:s');
    
    $query = "INSERT INTO solicitudes (
        id_cliente, 
        id_proyecto, 
        titulo, 
        descripcion, 
        fecha_solicitud, 
        estado, 
        prioridad, 
        fecha_lim, 
        fecha_termina, 
        repetir, 
        fecha_repeticion
    ) VALUES (
        $usrid, 
        " . ($id_proyecto ? $id_proyecto : 'NULL') . ", 
        '$titulo', 
        '$descripcion_json', 
        '$fecha_actual', 
        'Pendiente', 
        '$prioridad', 
        '0000-00-00 00:00:00', 
        '0000-00-00 00:00:00', 
        0, 
        '0000-00-00 00:00:00'
    )";

    if(mysqli_query($conn, $query)) {
        $ticket_id = mysqli_insert_id($conn);
        
        // Calcular cuántas solicitudes pendientes existen antes de esta nueva (backlog antes de este ticket)
        $pending_ahead = 0;
        $pending_query = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM solicitudes WHERE estado = 'Pendiente' AND fecha_solicitud < '$fecha_actual'");
        if($pending_query) {
            $r = mysqli_fetch_assoc($pending_query);
            $pending_ahead = isset($r['cnt']) ? (int)$r['cnt'] : 0;
        }

        // Intentar enviar resumen por correo al cliente (no bloquear la creación del ticket si falla)
        try {
            require_once dirname(__DIR__) . '/adm.conlineweb.com/includes/cw_cliente_tickets_service.php';
            cw_cliente_ticket_send_created_email(
                $conn,
                $usrid,
                $ticket_id,
                $titulo,
                $descripcion,
                $prioridad,
                $pending_ahead
            );
        } catch (Exception $e) {
            error_log('Error al intentar enviar resumen por correo: ' . $e->getMessage());
        }

        echo json_encode([
            'success' => true,
            'message' => 'Ticket creado exitosamente',
            'ticket_id' => $ticket_id,
            'pending_ahead' => $pending_ahead,
            'adjuntos' => [
                'imagenes' => count($imagenes_subidas),
                'archivos' => count($archivos_subidos),
                'errores' => $errores_subida
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al crear el ticket: ' . mysqli_error($conn)]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
?>
