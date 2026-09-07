<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../error.log');

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../auth_middleware.php';

// Intentar incluir conn.php pero continuar si falla
$conn = null;
try {
    require_once __DIR__ . '/../conn.php';
} catch (Exception $e) {
    // Si falla, conn estará undefined pero podemos continuar sin BD
    error_log('Error loading conn.php: ' . $e->getMessage());
    $conn = null;
}

// Ruta correcta: desde ajax/ ir al parent dir y luego a whatsapp/sessions
$sessionsDir = dirname(__DIR__) . '/whatsapp/sessions';

if (!is_dir($sessionsDir)) {
    error_log('Sessions directory not found: ' . $sessionsDir);
    echo json_encode([]);
    exit;
}

$conversaciones = [];
$files = glob($sessionsDir . '/whatsapp_*.json');

if ($files === false) {
    error_log('Error reading sessions directory: ' . $sessionsDir);
    echo json_encode([]);
    exit;
}

// Log para debug
error_log('Found ' . count($files) . ' files in sessions directory');

foreach ($files as $filepath) {
    $filename = basename($filepath);
    
    // Ignorar archivos _meta.json
    if (strpos($filename, '_meta.json') !== false) {
        error_log('Skipping meta file: ' . $filename);
        continue;
    }
    
    error_log('Processing file: ' . $filename);
    
    // Extraer el número de teléfono del nombre del archivo
    if (preg_match('/whatsapp_(\d+)\.json$/', $filename, $matches)) {
        $telefono = $matches[1];
        
        // Leer el archivo JSON
        $contenido = @file_get_contents($filepath);
        if ($contenido === false) {
            error_log('Failed to read file: ' . $filepath);
            continue;
        }
        
        $mensajes = json_decode($contenido, true);
        if (!is_array($mensajes)) {
            error_log('Invalid JSON in file: ' . $filepath . ' - Error: ' . json_last_error_msg());
            continue;
        }

        // Calcular último índice de mensaje entrante (cliente) y último mensaje visible
        $lastInboundIdx = -1;
        $ultimoMensaje = '';
        $ultimoRol = '';
        foreach ($mensajes as $i => $item) {
            if (!is_array($item)) continue;
            $role = isset($item['role']) ? (string)$item['role'] : '';
            $content = isset($item['content']) ? trim((string)$item['content']) : '';
            if ($content === '' || $role === 'system') continue;
            $ultimoMensaje = $content;
            $ultimoRol = $role;
            if ($role === 'user') {
                $lastInboundIdx = (int)$i;
            }
        }
        
        error_log('Successfully processed file for phone: ' . $telefono . ' with ' . count($mensajes) . ' messages');
        
        // Obtener información del archivo meta si existe
        $metaFile = $sessionsDir . '/whatsapp_' . $telefono . '_meta.json';
        $metaData = [];
        if (file_exists($metaFile)) {
            $metaContent = @file_get_contents($metaFile);
            if ($metaContent) {
                $metaData = json_decode($metaContent, true) ?: [];
            }
        }

        // Determinar si requiere atención del agente (bolita roja)
        $humanHandoff = !empty($metaData['human_handoff']);
        $agentActive = !empty($metaData['agent_active']);
        $adminLastReadInboundIdx = isset($metaData['admin_last_read_inbound_idx']) ? (int)$metaData['admin_last_read_inbound_idx'] : -1;
        $agentLastSentInboundIdx = isset($metaData['agent_last_sent_inbound_idx']) ? (int)$metaData['agent_last_sent_inbound_idx'] : -1;
        $attentionCutoffIdx = max($adminLastReadInboundIdx, $agentLastSentInboundIdx);
        $needsAgentAttention = (($humanHandoff || $agentActive) && ($lastInboundIdx > $attentionCutoffIdx));
        
        // Intentar obtener información del cliente desde la BD
        $nombreCliente = null;
        $email = null;
        $clienteId = null;
        
        // Primero intentar desde meta
        if (!empty($metaData['client_name'])) {
            $nombreCliente = $metaData['client_name'];
        }
        if (!empty($metaData['client_email'])) {
            $email = $metaData['client_email'];
        }
        if (!empty($metaData['cliente_id'])) {
            $clienteId = $metaData['cliente_id'];
        }
        
        // Si no hay info en meta, buscar en BD por teléfono (últimos 10 dígitos)
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
        
        // Obtener timestamp de última modificación
        $ultimaActualizacion = filemtime($filepath);
        
        // Contar mensajes
        $totalMensajes = count($mensajes);
        
        // Formatear teléfono para mostrar
        $telefonoFormateado = $telefono;
        if (strlen($telefono) === 13 && strpos($telefono, '521') === 0) {
            // Formato México: +52 1 (XXX) XXX-XXXX
            $telefonoFormateado = '+52 1 (' . substr($telefono, 3, 3) . ') ' . 
                                  substr($telefono, 6, 3) . '-' . substr($telefono, 9, 4);
        } elseif (strlen($telefono) === 12 && strpos($telefono, '52') === 0) {
            // Formato México sin 1: +52 (XXX) XXX-XXXX
            $telefonoFormateado = '+52 (' . substr($telefono, 2, 3) . ') ' . 
                                  substr($telefono, 5, 3) . '-' . substr($telefono, 8, 4);
        } elseif (strlen($telefono) === 10) {
            // Formato local México: (XXX) XXX-XXXX
            $telefonoFormateado = '(' . substr($telefono, 0, 3) . ') ' . 
                                  substr($telefono, 3, 3) . '-' . substr($telefono, 6, 4);
        } else {
            $telefonoFormateado = '+' . $telefono;
        }
        
        $conversaciones[] = [
            'telefono' => $telefonoFormateado,
            'telefono_raw' => $telefono,
            'nombre_cliente' => $nombreCliente,
            'email' => $email,
            'cliente_id' => $clienteId,
            'ultima_actualizacion' => $ultimaActualizacion,
            'total_mensajes' => $totalMensajes,
            'needs_agent_attention' => $needsAgentAttention,
            'comprobante_pendiente' => !empty($metaData['comprobante_pendiente']),
            'ultimo_mensaje' => $ultimoMensaje,
            'ultimo_rol' => $ultimoRol,
            'archivo' => $filename
        ];
    }
}

// Ordenar por última actualización (más reciente primero)
usort($conversaciones, function($a, $b) {
    return $b['ultima_actualizacion'] - $a['ultima_actualizacion'];
});

// Log final para debug
error_log('Returning ' . count($conversaciones) . ' conversations');

echo json_encode($conversaciones, JSON_UNESCAPED_UNICODE);
