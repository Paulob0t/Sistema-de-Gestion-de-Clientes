<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../error.log');

header('Content-Type: application/json');

require_once __DIR__ . '/../auth_middleware.php';

// Intentar incluir conn.php pero continuar si falla
$conn = null;
try {
    require_once __DIR__ . '/../conn.php';
} catch (Exception $e) {
    error_log('Error loading conn.php: ' . $e->getMessage());
    $conn = null;
}

$archivo = $_GET['archivo'] ?? '';

if (empty($archivo)) {
    echo json_encode([
        'success' => false,
        'error' => 'Archivo no especificado'
    ]);
    exit;
}

// Validar que el archivo tenga el formato correcto
if (!preg_match('/^whatsapp_\d+\.json$/', $archivo)) {
    echo json_encode([
        'success' => false,
        'error' => 'Nombre de archivo inválido'
    ]);
    exit;
}

// Ruta correcta: desde ajax/ ir al parent dir y luego a whatsapp/sessions
$sessionsDir = dirname(__DIR__) . '/whatsapp/sessions';
$filepath = $sessionsDir . '/' . $archivo;

// Verificar que el archivo existe
if (!file_exists($filepath)) {
    echo json_encode([
        'success' => false,
        'error' => 'Archivo de conversación no encontrado'
    ]);
    exit;
}

// Leer el archivo
$contenido = @file_get_contents($filepath);
if ($contenido === false) {
    echo json_encode([
        'success' => false,
        'error' => 'Error al leer el archivo'
    ]);
    exit;
}

$mensajes = json_decode($contenido, true);
if (!is_array($mensajes)) {
    echo json_encode([
        'success' => false,
        'error' => 'Formato de archivo inválido'
    ]);
    exit;
}

// Calcular último índice de mensaje entrante (cliente) para marcar como leído
$lastInboundIdx = -1;
foreach ($mensajes as $i => $item) {
    if (!is_array($item)) continue;
    $role = isset($item['role']) ? (string)$item['role'] : '';
    $content = isset($item['content']) ? trim((string)$item['content']) : '';
    if ($content === '' || $role === 'system') continue;
    if ($role === 'user') {
        $lastInboundIdx = (int)$i;
    }
}

// Extraer el número de teléfono
preg_match('/whatsapp_(\d+)\.json$/', $archivo, $matches);
$telefono = $matches[1] ?? 'Desconocido';

// Obtener información del archivo meta
$metaFile = $sessionsDir . '/whatsapp_' . $telefono . '_meta.json';
$metaData = [];
if (file_exists($metaFile)) {
    $metaContent = @file_get_contents($metaFile);
    if ($metaContent) {
        $metaData = json_decode($metaContent, true) ?: [];
    }
}

// Marcar como leído al abrir la conversación (para quitar la bolita roja)
try {
    $metaData['admin_last_read_inbound_idx'] = $lastInboundIdx;
    $metaData['admin_last_read_at'] = gmdate('c');
    @file_put_contents($metaFile, json_encode($metaData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
} catch (Throwable $e) {
    // Silencioso: no romper la carga del modal si falla el guardado
}

// Información del cliente
$nombreCliente = null;
$email = null;
$clienteId = null;

// Primero desde meta
if (!empty($metaData['client_name'])) {
    $nombreCliente = $metaData['client_name'];
}
if (!empty($metaData['client_email'])) {
    $email = $metaData['client_email'];
}
if (!empty($metaData['cliente_id'])) {
    $clienteId = $metaData['cliente_id'];
}

// Si no hay info en meta, buscar en BD
if (isset($conn) && $conn && !$nombreCliente && !$clienteId && strlen($telefono) >= 10) {
    try {
        $telefono_last10 = substr($telefono, -10);
        
        $stmt = $conn->prepare("
            SELECT id, nombre_contacto, empresa, correo 
            FROM clientes 
            WHERE RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefono, '+', ''), ' ', ''), '-', ''), '(', ''), ')', ''), 10) = ?
            LIMIT 1
        ");
        
        if ($stmt) {
            $stmt->bind_param("s", $telefono_last10);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $cliente = $result->fetch_assoc();
                $nombreCliente = $cliente['nombre_contacto'] ?: $cliente['empresa'];
                $email = $cliente['correo'];
                $clienteId = $cliente['id'];
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log('Error querying database: ' . $e->getMessage());
    }
}

// Formatear teléfono
$telefonoFormateado = $telefono;
if (strlen($telefono) === 13 && strpos($telefono, '521') === 0) {
    $telefonoFormateado = '+52 1 (' . substr($telefono, 3, 3) . ') ' . 
                          substr($telefono, 6, 3) . '-' . substr($telefono, 9, 4);
} elseif (strlen($telefono) === 12 && strpos($telefono, '52') === 0) {
    $telefonoFormateado = '+52 (' . substr($telefono, 2, 3) . ') ' . 
                          substr($telefono, 5, 3) . '-' . substr($telefono, 8, 4);
} elseif (strlen($telefono) === 10) {
    $telefonoFormateado = '(' . substr($telefono, 0, 3) . ') ' . 
                          substr($telefono, 3, 3) . '-' . substr($telefono, 6, 4);
} else {
    $telefonoFormateado = '+' . $telefono;
}

// Procesar mensajes para hacer más legible el contenido del sistema
$mensajesProcesados = [];
foreach ($mensajes as $msg) {
    // Limitar longitud de mensajes del sistema muy largos
    if (isset($msg['role']) && $msg['role'] === 'system' && isset($msg['content'])) {
        if (strlen($msg['content']) > 2000) {
            $msg['content'] = substr($msg['content'], 0, 2000) . "\n\n... [Mensaje truncado por longitud]";
        }
    }
    
    $mensajesProcesados[] = $msg;
}

echo json_encode([
    'success' => true,
    'data' => [
        'archivo' => $archivo,
        'telefono' => $telefonoFormateado,
        'telefono_raw' => $telefono,
        'nombre_cliente' => $nombreCliente ?: 'Cliente sin nombre',
        'email' => $email,
        'cliente_id' => $clienteId,
        'mensajes' => $mensajesProcesados,
        'meta' => $metaData,
        'total_mensajes' => count($mensajesProcesados)
    ]
], JSON_UNESCAPED_UNICODE);
