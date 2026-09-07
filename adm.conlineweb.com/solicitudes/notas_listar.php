<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../auth_middleware.php';
require_once __DIR__ . '/bootstrap_conexion.php';
require_once __DIR__ . '/helpers_media.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo '<div class="alert alert-info">No hay notas para mostrar</div>';
    exit;
}

// Verificar existencia de la tabla
$check = $conexion->query("SHOW TABLES LIKE 'solicitudes_notas'");
if (!$check || $check->num_rows === 0) {
    echo '<div class="alert alert-info">No hay notas para mostrar</div>';
    exit;
}

$sql = "SELECT * FROM solicitudes_notas WHERE solicitud_id = $id ORDER BY fecha_creacion DESC";
$result = $conexion->query($sql);

if ($result && $result->num_rows > 0) {
    while ($nota = $result->fetch_assoc()) {
        $notaData = json_decode($nota['nota'], true);
        $texto = isset($notaData['text']) ? htmlspecialchars($notaData['text']) : '';
        $imagenes = isset($notaData['images']) ? cw_ticket_media_urls($notaData['images']) : [];
        $autor = htmlspecialchars($nota['autor']);
        $fecha = date('d/m/Y H:i', strtotime($nota['fecha_creacion']));
        
    echo '<div class="nota-item" style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee;">';
    echo '<div style="font-weight: bold; color: #000147;">' . $autor . ' <span style="font-size: 0.8em; color: #6c757d;">(' . $fecha . ')</span></div>';
    // Dejar el texto plano y dejar que el front-end lo formatee (párrafos/listas)
    echo '<div class="nota-texto">' . $texto . '</div>';
        
        if (!empty($imagenes)) {
            echo '<div class="nota-images content-images">';
            foreach ($imagenes as $img) {
                $safe = htmlspecialchars($img, ENT_QUOTES, 'UTF-8');
                echo '<img src="' . $safe . '" class="content-image-thumb" data-full="' . $safe . '">';
            }
            echo '</div>';
        }
        
        echo '</div>';
    }
} else {
    echo '<div class="alert alert-info">No hay notas para mostrar</div>';
}
?>