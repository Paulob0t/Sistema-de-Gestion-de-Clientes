<?php
// Activar visualización de errores para depuración (quitar en producción)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Incluir archivos necesarios
require_once __DIR__ . '/auth_middleware.php';

// Incluir conexión a la base de datos
$conexion_file = __DIR__ . '/db/conexion.php';
if (!file_exists($conexion_file)) {
    die('Error: Archivo de conexión no encontrado en: ' . $conexion_file);
}
include($conexion_file);

// Verificar conexión
if (!isset($conexion) || $conexion->connect_error) {
    die('Error de conexión a la base de datos');
}

// Verificar que se recibió un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('ID no válido');
}

$id = (int)$_GET['id'];

// Consulta simplificada para evitar errores de JOIN
$sql = "SELECT s.*, 
        a.nombre as agente_nombre
        FROM solicitudes s 
        LEFT JOIN agentes a ON s.usuario_asignado = a.id 
        WHERE s.id = ?";

$stmt = $conexion->prepare($sql);
if (!$stmt) {
    die('Error en la consulta: ' . $conexion->error);
}

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Solicitud no encontrada');
}

$solicitud = $result->fetch_assoc();

// Obtener cliente si existe la columna
$cliente_nombre = '';
$cliente_empresa = '';

if (isset($solicitud['id_cliente']) && $solicitud['id_cliente'] > 0) {
    $cli_sql = "SELECT nombre_contacto, empresa FROM clientes WHERE id = ?";
    $cli_stmt = $conexion->prepare($cli_sql);
    if ($cli_stmt) {
        $cli_stmt->bind_param("i", $solicitud['id_cliente']);
        $cli_stmt->execute();
        $cli_result = $cli_stmt->get_result();
        if ($cli_result->num_rows > 0) {
            $cliente = $cli_result->fetch_assoc();
            $cliente_nombre = $cliente['nombre_contacto'] ?? '';
            $cliente_empresa = $cliente['empresa'] ?? '';
        }
        $cli_stmt->close();
    }
}

// Obtener proyecto si existe la columna
$proyecto_nombre = '';
if (isset($solicitud['id_proyecto']) && $solicitud['id_proyecto'] > 0) {
    $proy_sql = "SELECT nombre_proyecto FROM proyectos WHERE id_proyecto = ?";
    $proy_stmt = $conexion->prepare($proy_sql);
    if ($proy_stmt) {
        $proy_stmt->bind_param("i", $solicitud['id_proyecto']);
        $proy_stmt->execute();
        $proy_result = $proy_stmt->get_result();
        if ($proy_result->num_rows > 0) {
            $proyecto = $proy_result->fetch_assoc();
            $proyecto_nombre = $proyecto['nombre_proyecto'] ?? '';
        }
        $proy_stmt->close();
    }
}

// Obtener notas
$notas = [];
$notas_sql = "SELECT * FROM solicitudes_notas WHERE solicitud_id = ? ORDER BY fecha_creacion DESC";
$notas_stmt = $conexion->prepare($notas_sql);
if ($notas_stmt) {
    $notas_stmt->bind_param("i", $id);
    $notas_stmt->execute();
    $notas_result = $notas_stmt->get_result();
    while ($nota = $notas_result->fetch_assoc()) {
        $notas[] = $nota;
    }
    $notas_stmt->close();
}

// Función para formatear fecha
function formatearFecha($fecha) {
    if (!$fecha || $fecha === '0000-00-00 00:00:00' || $fecha === '0000-00-00') {
        return 'No especificada';
    }
    $timestamp = strtotime($fecha);
    if ($timestamp === false) return 'Fecha inválida';
    return date('d/m/Y H:i', $timestamp);
}

// Función para obtener texto de frecuencia
function getTextoFrecuencia($repetir) {
    $repetir = (int)$repetir;
    if ($repetir == 1) return 'Diaria';
    if ($repetir == 2) return 'Semanal';
    if ($repetir == 3) return 'Mensual';
    return 'Única';
}

// Función para limpiar texto SIN eliminar saltos de línea
function limpiarTexto($texto) {
    if (!$texto) return '';
    $texto = strip_tags($texto);
    $texto = html_entity_decode($texto, ENT_QUOTES, 'UTF-8');
    // Convertir \r\n y \n a saltos de línea reales
    $texto = str_replace(['\r\n', '\n', '\r'], PHP_EOL, $texto);
    // Mantener los saltos de línea, solo eliminar espacios extra al inicio/fin
    return trim($texto);
}

// Procesar descripción - manejar correctamente los saltos de línea
$descripcionTexto = $solicitud['descripcion'] ?? '';
$descripcionData = json_decode($solicitud['descripcion'], true);
if (is_array($descripcionData) && isset($descripcionData['text'])) {
    $descripcionTexto = $descripcionData['text'];
}

// Decodificar escapes de la descripción si vienen como JSON string
$descripcionTexto = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function($match) {
    return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
}, $descripcionTexto);
$descripcionTexto = str_replace(['\r\n', '\n', '\r'], PHP_EOL, $descripcionTexto);

// Generar contenido
$contenido = "========================================\n";
$contenido .= "         TICKET DE SOLICITUD #{$id}\n";
$contenido .= "========================================\n\n";

$contenido .= "INFORMACIÓN GENERAL\n";
$contenido .= "-------------------\n";
$contenido .= "Título: " . limpiarTexto($solicitud['titulo'] ?? 'Sin título') . "\n";
$contenido .= "Estado: " . ($solicitud['estado'] ?? 'No especificado') . "\n";
$contenido .= "Prioridad: " . ($solicitud['prioridad'] ?? 'No especificada') . "\n";
$contenido .= "Frecuencia: " . getTextoFrecuencia($solicitud['repetir'] ?? 0) . "\n";
$contenido .= "Fecha de creación: " . formatearFecha($solicitud['fecha_solicitud'] ?? '') . "\n";

if (isset($solicitud['fecha_lim'])) {
    $contenido .= "Fecha límite: " . formatearFecha($solicitud['fecha_lim']) . "\n";
}

if (isset($solicitud['fecha_termina'])) {
    $contenido .= "Fecha de término: " . formatearFecha($solicitud['fecha_termina']) . "\n";
}

$contenido .= "\nDESCRIPCIÓN\n";
$contenido .= "-----------\n";
$descripcionProcesada = limpiarTexto($descripcionTexto) ?: 'Sin descripción';
// Usar directamente el texto con saltos de línea, sin wordwrap
$contenido .= $descripcionProcesada . "\n\n";

$contenido .= "ASIGNACIÓN\n";
$contenido .= "----------\n";
$contenido .= "Agente asignado: " . ($solicitud['agente_nombre'] ?? 'No asignado') . "\n\n";

if (!empty($cliente_nombre) || !empty($cliente_empresa)) {
    $contenido .= "CLIENTE\n";
    $contenido .= "-------\n";
    if (!empty($cliente_nombre)) $contenido .= "Nombre contacto: " . $cliente_nombre . "\n";
    if (!empty($cliente_empresa)) $contenido .= "Empresa: " . $cliente_empresa . "\n\n";
}

if (!empty($proyecto_nombre)) {
    $contenido .= "PROYECTO\n";
    $contenido .= "--------\n";
    $contenido .= "Nombre: " . $proyecto_nombre . "\n\n";
}

if (!empty($notas)) {
    $contenido .= "NOTAS\n";
    $contenido .= "-----\n";
    foreach ($notas as $index => $nota) {
        $contenido .= ($index + 1) . ". [" . formatearFecha($nota['fecha_creacion']) . "] ";
        $contenido .= ($nota['autor'] ?? 'Usuario') . ":\n";
        $textoNota = limpiarTexto($nota['nota'] ?? '');
        // Para las notas, también mantener saltos de línea
        $contenido .= "   " . str_replace("\n", "\n   ", $textoNota) . "\n\n";
    }
}

$contenido .= "========================================\n";
$contenido .= "Documento generado el " . date('d/m/Y H:i:s') . "\n";

// Configurar headers para UTF-8 con BOM opcional (mejor compatibilidad con Windows)
header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="ticket_' . $id . '_' . date('Ymd_His') . '.txt"');
header('Content-Length: ' . strlen($contenido));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Imprimir contenido con BOM para mejor soporte de acentos en Windows
echo "\xEF\xBB\xBF" . $contenido;

// Cerrar conexiones
$stmt->close();
$conexion->close();
exit;
?>