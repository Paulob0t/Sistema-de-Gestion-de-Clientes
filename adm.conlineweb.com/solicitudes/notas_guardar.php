<?php
header('Content-Type: text/plain; charset=UTF-8');
require_once __DIR__ . '/../auth_middleware.php';
require_once __DIR__ . '/bootstrap_conexion.php';

$id = isset($_POST['solicitud_id']) ? (int)$_POST['solicitud_id'] : 0;
$autor = isset($_POST['autor']) ? trim($_POST['autor']) : '';
$nota = isset($_POST['nota']) ? trim($_POST['nota']) : '';
if ($id <= 0 || $autor === '' || ($nota === '' && empty($_FILES['nota_images']))) { 
    http_response_code(400); 
    echo 'BAD'; 
    exit; 
}

if (strlen($nota) > 5000) { 
    http_response_code(400); 
    echo 'TOO_LONG'; 
    exit; 
}

// Verificar existencia de la tabla
$check = $conexion->query("SHOW TABLES LIKE 'solicitudes_notas'");
if (!$check || $check->num_rows === 0) { 
    http_response_code(503); 
    echo 'NO_TABLE'; 
    exit; 
}

// Procesar imágenes de nota
$notaData = [
    'text' => $nota,
    'images' => []
];

if (!empty($_FILES['nota_images'])) {
    $uploadDir = __DIR__ . '/uploads/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    foreach ($_FILES['nota_images']['tmp_name'] as $index => $tmpName) {
        if ($_FILES['nota_images']['error'][$index] === UPLOAD_ERR_OK) {
            $fileName = uniqid() . '_' . basename($_FILES['nota_images']['name'][$index]);
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($tmpName, $filePath)) {
                $notaData['images'][] = 'uploads/' . $fileName;
            }
        }
    }
}

$autor = $conexion->real_escape_string($autor);
$notaJson = $conexion->real_escape_string(json_encode($notaData));

$sql = "INSERT INTO solicitudes_notas (solicitud_id, autor, nota, fecha_creacion) VALUES ($id, '$autor', '$notaJson', NOW())";
if ($conexion->query($sql)) { 
    // Intentar enviar correo de notificación (no bloquear la respuesta)
    @require_once __DIR__.'/enviar_correo_nota.php';
    if (function_exists('enviar_correo_nota')) {
        // Extraer texto plano e imágenes del payload para el correo
        $notaTexto = isset($notaData['text']) ? (string)$notaData['text'] : '';
        $imgs = isset($notaData['images']) && is_array($notaData['images']) ? $notaData['images'] : [];
        try { @enviar_correo_nota($conexion, (int)$id, (string)$autor, $notaTexto, $imgs); } catch (Throwable $t) { /* swallow */ }
    }
    echo 'OK'; 
} else { 
    http_response_code(500); 
    echo 'ERR'; 
}
?>