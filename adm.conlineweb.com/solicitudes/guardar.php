<?php
// Configurar zona horaria
date_default_timezone_set('America/Mexico_City');

require_once __DIR__ . '/../auth_middleware.php';
require_once __DIR__ . '/bootstrap_conexion.php';
require_once __DIR__ . '/helpers_agentes.php'; // Funciones para múltiples agentes
require_once __DIR__ . '/helpers_clientes.php';

// Configurar zona horaria en MySQL también
$conexion->query("SET time_zone = '-06:00'");

$isAjax = (isset($_POST['ajax']) && $_POST['ajax'] == '1') || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
// Utilidad: generar nombre único conservando el nombre original saneado
function unique_filename($dir, $safeName) {
    $dir = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
    $info = pathinfo($safeName);
    $base = isset($info['filename']) ? $info['filename'] : $safeName;
    $ext  = isset($info['extension']) && $info['extension'] !== '' ? '.' . $info['extension'] : '';
    // Sin espacios: rompen src HTML si no se encodean
    $base = preg_replace('/[^a-zA-Z0-9._-]+/', '_', (string) $base);
    $base = trim($base, '._-');
    if ($base === '') {
        $base = 'archivo';
    }
    $candidate = $base . $ext;
    $i = 1;
    while (file_exists($dir . $candidate)) {
        $candidate = $base . '_' . $i . $ext;
        $i++;
        if ($i > 1000) {
            break;
        }
    }
    return $candidate;
}
// Carga perezosa del mailer de asignación (si existe)
@require_once __DIR__.'/enviar_correo_asignacion.php';

// Verificar si es una edición o nueva solicitud
$solicitud_id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
$isEdit = ($solicitud_id !== null && $solicitud_id > 0);

// Sanitizar entradas básicas
$titulo        = isset($_POST['titulo']) ? $conexion->real_escape_string(trim($_POST['titulo'])) : '';
// Texto crudo para JSON (el escape va sobre el JSON final, no sobre el texto interno)
$descripcion   = isset($_POST['descripcion']) ? trim((string) $_POST['descripcion']) : '';
$cliente_id    = isset($_POST['cliente_id']) && $_POST['cliente_id'] !== '' ? (int)$_POST['cliente_id'] : null;
$id_proyecto   = isset($_POST['id_proyecto']) && $_POST['id_proyecto'] !== '' ? (int)$_POST['id_proyecto'] : null;

if ($cliente_id !== null && $cliente_id > 0 && !solicitudes_cliente_es_activo($conexion, $cliente_id)) {
    $msg = 'El cliente seleccionado no está activo o no es válido.';
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $msg]);
        exit;
    }
    die($msg);
}

// NUEVO: Soportar múltiples agentes (viene como array agentes_asignados[])
if (isset($_POST['agentes_asignados']) && is_array($_POST['agentes_asignados'])) {
    // Convertir array [1,3,5] a string CSV "1,3,5"
    $usuario_asignado = agentesArrayToString($_POST['agentes_asignados']);
    $usuario_asignado = $usuario_asignado ? $conexion->real_escape_string($usuario_asignado) : '';
} elseif (isset($_POST['usuario_asignado'])) {
    // Backward compatibility: si viene usuario_asignado directo (de asignación inline)
    $usuario_asignado = $conexion->real_escape_string(trim($_POST['usuario_asignado']));
} else {
    $usuario_asignado = '';
}

$prioridad     = isset($_POST['prioridad']) ? $conexion->real_escape_string(trim($_POST['prioridad'])) : '';
$fecha_lim     = isset($_POST['fecha_lim']) && !empty($_POST['fecha_lim']) ? $conexion->real_escape_string($_POST['fecha_lim']) : null;

// Procesar campo repetir
$repetir = 0;
if (isset($_POST['repetir_activo']) && isset($_POST['repetir']) && $_POST['repetir'] !== '') {
    $repetir = (int)$_POST['repetir'];
    if (!in_array($repetir, [1, 2, 3])) {
        $repetir = 0;
    }
}

// Calcular fecha de repetición si se repite
$fechaRepeticion = '0000-00-00 00:00:00';
if ($repetir > 0) {
    $fechaCreacion = new DateTime('now', new DateTimeZone('America/Mexico_City'));
    
    switch ($repetir) {
        case 1: $fechaCreacion->add(new DateInterval('P1D')); break;
        case 2: $fechaCreacion->add(new DateInterval('P1W')); break;
        case 3: $fechaCreacion->add(new DateInterval('P1M')); break;
    }
    $fechaRepeticion = $fechaCreacion->format('Y-m-d H:i:s');
}

// Procesar imágenes de descripción y construir JSON de descripción
$descripcionData = [
    'text' => $descripcion,
    'images' => [],
    'files' => []
];

// Si es edición, obtener las imágenes existentes
if ($isEdit && isset($_POST['descripcion_existing_images'])) {
    $existingImages = json_decode($_POST['descripcion_existing_images'], true);
    if (is_array($existingImages)) {
        $descripcionData['images'] = $existingImages;
    }
}

// Si es edición, obtener los archivos existentes
if ($isEdit && isset($_POST['descripcion_existing_files'])) {
    $existingFiles = json_decode($_POST['descripcion_existing_files'], true);
    if (is_array($existingFiles)) {
        $descripcionData['files'] = $existingFiles;
    }
}

// Carpeta para guardar imágenes de solicitudes
$uploadDir = __DIR__ . '/uploads/solicitudes/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Para devolver info de debug en AJAX si es necesario
$debugFiles = [];

if (!empty($_FILES) && isset($_FILES['descripcion_images'])) {
    $files = $_FILES['descripcion_images'];
    for ($i = 0; $i < count($files['name']); $i++) {
        $error = $files['error'][$i];
        $tmpName = $files['tmp_name'][$i];
        $origName = $files['name'][$i];

        $debugEntry = ['index' => $i, 'name' => $origName, 'error' => $error];

        if ($error === UPLOAD_ERR_OK && is_uploaded_file($tmpName)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $tmpName);
            finfo_close($finfo);

            $debugEntry['mime'] = $mimeType;

            $ext = strtolower(pathinfo((string) $origName, PATHINFO_EXTENSION));
            $extIsImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
            $mimeIsImage = is_string($mimeType) && strpos($mimeType, 'image/') === 0;
            // Algunos navegadores mandan octet-stream en blobs del canvas/chat
            if ($mimeIsImage || ($extIsImage && in_array($mimeType, ['application/octet-stream', 'binary/octet-stream', ''], true))) {
                $safeName = preg_replace('/[^a-zA-Z0-9\._-]/', '_', (string) $origName);
                if ($safeName === '' || $safeName === '_') {
                    $safeName = 'chat_' . date('Ymd_His') . ($extIsImage ? ('.' . $ext) : '.jpg');
                }
                $fileName = unique_filename($uploadDir, $safeName);
                $filePath = $uploadDir . $fileName;

                if (move_uploaded_file($tmpName, $filePath)) {
                    $relativePath = 'uploads/solicitudes/' . $fileName;
                    $descripcionData['images'][] = $relativePath;
                    $debugEntry['saved'] = $relativePath;
                } else {
                    $debugEntry['saved_error'] = 'move_uploaded_file failed';
                }
            } else {
                $debugEntry['saved_error'] = 'not_an_image';
            }
        }

        $debugFiles[] = $debugEntry;
    }
}

// Procesar archivos no-imagen adjuntos en la descripción
if (!empty($_FILES) && isset($_FILES['descripcion_files'])) {
    $files = $_FILES['descripcion_files'];
    // Lista blanca de extensiones y tipos MIME comunes
    $allowedExtensions = [
        'pdf','doc','docx','xls','xlsx','csv','ppt','pptx','zip','rar','txt','rtf'
    ];
    $allowedMimes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/csv', 'application/csv', 'text/plain', 'application/rtf',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/zip', 'application/x-zip-compressed',
        'application/x-rar-compressed', 'application/octet-stream' // algunos navegadores mandan octet-stream
    ];
    $maxFileSize = 50 * 1024 * 1024; // 50 MB

    for ($i = 0; $i < count($files['name']); $i++) {
        $error = $files['error'][$i];
        $tmpName = $files['tmp_name'][$i];
        $origName = $files['name'][$i];

        $debugEntry = ['index' => $i, 'name' => $origName, 'error' => $error, 'type' => 'file'];

        if ($error === UPLOAD_ERR_OK && is_uploaded_file($tmpName)) {
            $size = isset($files['size'][$i]) ? (int)$files['size'][$i] : 0;
            if ($size > $maxFileSize) {
                $debugEntry['saved_error'] = 'file_too_large';
                $debugFiles[] = $debugEntry;
                continue;
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $tmpName);
            finfo_close($finfo);
            $debugEntry['mime'] = $mimeType;

            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

            // Validar por extensión y, si no es octet-stream, también por mime
            $extOk = in_array($ext, $allowedExtensions, true);
            $mimeOk = in_array($mimeType, $allowedMimes, true);
            if (!$extOk || (!$mimeOk && $mimeType !== 'application/octet-stream')) {
                $debugEntry['saved_error'] = 'invalid_type';
                $debugFiles[] = $debugEntry;
                continue;
            }

            // Evitar que se suban imágenes por este campo (se manejan aparte)
            if (strpos($mimeType, 'image/') === 0) {
                $debugEntry['saved_error'] = 'image_not_allowed_in_files';
                $debugFiles[] = $debugEntry;
                continue;
            }

            $safeName = preg_replace('/[^a-zA-Z0-9\._-]/', '_', $origName);
            // Conservar nombre original; si existe, agregar sufijo (1), (2), ...
            $fileName = unique_filename($uploadDir, $safeName);
            $filePath = $uploadDir . $fileName;

            if (move_uploaded_file($tmpName, $filePath)) {
                $relativePath = 'uploads/solicitudes/' . $fileName;
                $descripcionData['files'][] = $relativePath;
                $debugEntry['saved'] = $relativePath;
            } else {
                $debugEntry['saved_error'] = 'move_uploaded_file failed';
            }
        }

        $debugFiles[] = $debugEntry;
    }
}

// Convertir descripción a JSON
$descripcionJson = $conexion->real_escape_string(json_encode($descripcionData));

// Validar proyecto seleccionado y alinear cliente_id si aplica
if ($id_proyecto !== null) {
    $stmt = $conexion->prepare("SELECT id_cliente FROM proyectos WHERE id_proyecto = ?");
    if ($stmt) {
        $stmt->bind_param('i', $id_proyecto);
        $stmt->execute();
        $stmt->bind_result($proj_cliente_id);
        if ($stmt->fetch()) {
            // Si viene cliente en el form, validar que corresponda
            if ($cliente_id !== null && (int)$proj_cliente_id !== (int)$cliente_id) {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'ok' => false,
                        'error' => 'El proyecto seleccionado no corresponde al cliente elegido.'
                    ]);
                    exit;
                } else {
                    header("Location: index.php?error=1&message=" . urlencode('El proyecto seleccionado no corresponde al cliente elegido.'));
                    exit;
                }
            }
            // Si no viene cliente pero sí proyecto, asignar el cliente del proyecto
            if ($cliente_id === null) {
                $cliente_id = (int)$proj_cliente_id;
            }
        } else {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'ok' => false,
                    'error' => 'Proyecto no encontrado'
                ]);
                exit;
            } else {
                header("Location: index.php?error=1&message=" . urlencode('Proyecto no encontrado'));
                exit;
            }
        }
        $stmt->close();
    }
}

// Preparar la consulta SQL
if ($isEdit) {
    // Es una edición - hacer UPDATE
    $sql = "UPDATE solicitudes SET titulo = '$titulo', descripcion = '$descripcionJson', usuario_asignado = '$usuario_asignado', prioridad = '$prioridad', repetir = $repetir, fecha_repeticion = '$fechaRepeticion'";
    
    // Agregar id_cliente si existe la columna
    $colRes = $conexion->query("SHOW COLUMNS FROM solicitudes LIKE 'id_cliente'");
    if ($colRes && $colRes->num_rows > 0) {
        $clienteVal = $cliente_id !== null ? $cliente_id : 'NULL';
        $sql .= ", id_cliente = $clienteVal";
    }

    // Agregar fecha_lim si existe
    $colResFechaLim = $conexion->query("SHOW COLUMNS FROM solicitudes LIKE 'fecha_lim'");
    if ($colResFechaLim && $colResFechaLim->num_rows > 0) {
        $fechaLimVal = $fecha_lim !== null ? "'$fecha_lim'" : 'NULL';
        $sql .= ", fecha_lim = $fechaLimVal";
    }

    // Agregar id_proyecto si existe la columna
    $colResProyecto = $conexion->query("SHOW COLUMNS FROM solicitudes LIKE 'id_proyecto'");
    if ($colResProyecto && $colResProyecto->num_rows > 0) {
        $proyVal = $id_proyecto !== null ? $id_proyecto : 'NULL';
        $sql .= ", id_proyecto = $proyVal";
    }
    
    $sql .= " WHERE id = $solicitud_id";
    
} else {
    // Es nueva solicitud - hacer INSERT
    $sql = "INSERT INTO solicitudes (titulo, descripcion, usuario_asignado, prioridad, repetir, fecha_repeticion, repeticion_original_id";
    $values = "VALUES ('$titulo', '$descripcionJson', '$usuario_asignado', '$prioridad', $repetir, '$fechaRepeticion', NULL";

    // Agregar id_cliente si existe (nombre de columna en BD)
    $colRes = $conexion->query("SHOW COLUMNS FROM solicitudes LIKE 'id_cliente'");
    if ($colRes && $colRes->num_rows > 0) {
        $clienteVal = $cliente_id !== null ? $cliente_id : 'NULL';
        $sql .= ", id_cliente";
        $values .= ", $clienteVal";
    }

    // Agregar fecha_lim si existe
    $colResFechaLim = $conexion->query("SHOW COLUMNS FROM solicitudes LIKE 'fecha_lim'");
    if ($colResFechaLim && $colResFechaLim->num_rows > 0) {
        $fechaLimVal = $fecha_lim !== null ? "'$fecha_lim'" : 'NULL';
        $sql .= ", fecha_lim";
        $values .= ", $fechaLimVal";
    }

    // Agregar id_proyecto si existe la columna
    $colResProyecto = $conexion->query("SHOW COLUMNS FROM solicitudes LIKE 'id_proyecto'");
    if ($colResProyecto && $colResProyecto->num_rows > 0) {
        $proyVal = $id_proyecto !== null ? $id_proyecto : 'NULL';
        $sql .= ", id_proyecto";
        $values .= ", $proyVal";
    }

    $sql .= ") " . $values . ")";
}

if ($conexion->query($sql) === TRUE) {
    $resultId = $isEdit ? $solicitud_id : $conexion->insert_id;
    $emailEnviado = false;
    $emailDebug = null;
    $emailError = null;

    // Guardar nota inicial si viene (solo para nuevas solicitudes)
    if (!$isEdit && !empty($_POST['nota_inicial'])) {
        $notaIni = $conexion->real_escape_string(trim($_POST['nota_inicial']));
        $autorIni = isset($_POST['nota_autor_inicial']) ? $conexion->real_escape_string(trim($_POST['nota_autor_inicial'])) : 'admin';
        
        // Procesar imágenes de nota inicial si las hay
        $notaData = [
            'text' => $notaIni,
            'images' => []
        ];
        
        $notaJson = $conexion->real_escape_string(json_encode($notaData));
        
        // Verificar si existe la tabla de notas antes de insertar
        $chk = $conexion->query("SHOW TABLES LIKE 'solicitudes_notas'");
        if ($chk && $chk->num_rows > 0) {
            $conexion->query("INSERT INTO solicitudes_notas (solicitud_id, autor, nota, fecha_creacion) VALUES ($resultId, '$autorIni', '$notaJson', NOW())");
        }
    }

    // Enviar / Re-enviar correo de asignación si hay usuario asignado válido y la función está disponible
    // Ahora también se reenvía cuando se edita el ticket (el usuario lo solicitó)
    // SOPORTE PARA MÚLTIPLES AGENTES: enviar correo a cada agente asignado
    if (function_exists('enviar_correo_asignacion') && !empty($usuario_asignado)) {
        // Convertir string CSV "1,3,5" a array [1,3,5]
        $agentesIds = agentesStringToArray($usuario_asignado);
        
        if (!empty($agentesIds)) {
            $emailsEnviados = 0;
            $emailsErrors = [];
            
            foreach ($agentesIds as $agenteId) {
                try {
                    $okMail = enviar_correo_asignacion(
                        $conexion,
                        $resultId,
                        (string)$titulo,
                        $cliente_id !== null ? (int)$cliente_id : null,
                        (int)$agenteId
                    );
                    
                    if ($okMail) {
                        $emailsEnviados++;
                    } else {
                        if (isset($GLOBALS['ENVIAR_CORREO_ASIG_ERROR'])) {
                            $emailsErrors[] = "Agente $agenteId: " . $GLOBALS['ENVIAR_CORREO_ASIG_ERROR'];
                        }
                    }
                    
                    if (isset($GLOBALS['ENVIAR_CORREO_ASIG_DEBUG'])) {
                        $emailDebug = $GLOBALS['ENVIAR_CORREO_ASIG_DEBUG'];
                    }
                } catch (Throwable $t) {
                    $emailsErrors[] = "Agente $agenteId: " . $t->getMessage();
                }
            }
            
            $emailEnviado = $emailsEnviados > 0;
            if (!empty($emailsErrors)) {
                $emailError = implode('; ', $emailsErrors);
            }
        }
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'ok' => true,
            'id' => $resultId,
            'is_edit' => $isEdit,
            'email_enviado' => $emailEnviado,
            'email_debug' => $emailDebug,
            'email_error' => $emailError,
            'images_count' => count($descripcionData['images'] ?? []),
            'files_count' => count($descripcionData['files'] ?? []),
            'debug_files' => isset($debugFiles) ? $debugFiles : []
        ]);
    } else {
        header("Location: index.php?success=1");
    }
} else {
    error_log("Error en guardar.php: " . $conexion->error);
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'ok' => false,
            'error' => 'Error al guardar en la base de datos: ' . $conexion->error,
            'debug_files' => isset($debugFiles) ? $debugFiles : []
        ]);
    } else {
        header("Location: index.php?error=1&message=" . urlencode($conexion->error));
    }
}

$conexion->close();
exit;
?>