<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../conn.php';

// Leer el cuerpo de la petición
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// LOG: Datos recibidos
error_log("[crear_ticket_whatsapp] === INICIO DE SOLICITUD ===");
error_log("[crear_ticket_whatsapp] Datos RAW recibidos: " . $input);
error_log("[crear_ticket_whatsapp] Datos parseados: " . json_encode($data, JSON_UNESCAPED_UNICODE));

// Validar que se recibieron datos
if (!$data) {
    error_log("[crear_ticket_whatsapp] ERROR: Datos inválidos o no proporcionados");
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Datos inválidos o no proporcionados'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Obtener parámetros
$telefono = isset($data['telefono']) ? trim($data['telefono']) : '';
$cliente_id = isset($data['cliente_id']) ? (int) $data['cliente_id'] : 0;
$domain_name = isset($data['domain_name']) ? trim($data['domain_name']) : '';
$titulo = isset($data['titulo']) ? trim($data['titulo']) : '';
$descripcion = isset($data['descripcion']) ? trim($data['descripcion']) : '';
$fecha_limite = isset($data['fecha_limite']) ? trim($data['fecha_limite']) : '';
$prioridad = isset($data['prioridad']) ? trim($data['prioridad']) : 'media';

// LOG: Parámetros extraídos
error_log("[crear_ticket_whatsapp] Parámetros extraídos:");
error_log("[crear_ticket_whatsapp]   - telefono: " . $telefono);
error_log("[crear_ticket_whatsapp]   - cliente_id: " . $cliente_id);
error_log("[crear_ticket_whatsapp]   - domain_name: " . $domain_name);
error_log("[crear_ticket_whatsapp]   - titulo: " . $titulo);
error_log("[crear_ticket_whatsapp]   - prioridad: " . $prioridad);

// Validaciones
if (empty($titulo)) {
    error_log("[crear_ticket_whatsapp] ERROR: El título es obligatorio");
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'El título es obligatorio'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($descripcion)) {
    error_log("[crear_ticket_whatsapp] ERROR: La descripción es obligatoria");
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'La descripción es obligatoria'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Normalizar prioridad
$prioridad = strtolower($prioridad);
if (!in_array($prioridad, ['alta', 'media', 'baja'])) {
    $prioridad = 'media';
}

// Función auxiliar para extraer dominio limpio
function extractCleanDomain($input)
{
    if (empty($input)) {
        return '';
    }

    $input = trim($input);

    if (preg_match('#^https?://#i', $input)) {
        $parsed = parse_url($input);
        $host = $parsed['host'] ?? '';
    } else {
        $parts = explode('/', $input);
        $host = $parts[0];
    }

    $host = preg_replace('/^www\./i', '', $host);

    return strtolower($host);
}

/**
 * Función para extraer los últimos 10 dígitos de un teléfono (formato México)
 * Esto permite comparar números independientemente del formato:
 * +5214775579264 -> 4775579264
 * +524775579264 -> 4775579264
 * 4775579264 -> 4775579264
 * 8112120774 -> 8112120774
 */
function get_last_10_digits($phone) {
    $digits = preg_replace('/[^0-9]/', '', (string)$phone);
    if (strlen($digits) >= 10) {
        return substr($digits, -10);
    }
    return $digits;
}

try {
    $id_cliente = null;
    $nombre_cliente = null;

    // PRIORIDAD 1: Si ya viene cliente_id, usarlo directamente
    if ($cliente_id > 0) {
        error_log("[crear_ticket_whatsapp_v2] Usando cliente_id proporcionado: " . $cliente_id);
        $stmt = $conn->prepare("SELECT id, nombre_contacto, empresa FROM clientes WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $cliente_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $cliente = $result->fetch_assoc();
            $id_cliente = $cliente['id'];
            $nombre_cliente = $cliente['nombre_contacto'] ?: $cliente['empresa'];
            error_log("[crear_ticket_whatsapp_v2] ✓ Cliente encontrado por ID: " . $nombre_cliente);
        }
        $stmt->close();
    }

    // PRIORIDAD 2: Buscar por dominio
    if (!$id_cliente && !empty($domain_name)) {
        $clean_domain = extractCleanDomain($domain_name);
        error_log("[crear_ticket_whatsapp_v2] Buscando por dominio: " . $domain_name . " (limpio: " . $clean_domain . ")");

        if (!empty($clean_domain)) {
            // Buscar en tabla dominios
            $stmt = $conn->prepare("
                SELECT cliente_id 
                FROM dominios 
                WHERE url_dominio LIKE ? AND (eliminado = 0 OR eliminado IS NULL)
                LIMIT 1
            ");
            $search_pattern = "%{$clean_domain}%";
            $stmt->bind_param("s", $search_pattern);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $id_cliente = (int) $row['cliente_id'];
                error_log("[crear_ticket_whatsapp_v2] ✓ Cliente encontrado por dominio en tabla dominios: ID=" . $id_cliente);
            }
            $stmt->close();

            // Si no se encuentra en dominios, buscar en proyectos
            if (!$id_cliente) {
                $stmt = $conn->prepare("
                    SELECT id_cliente 
                    FROM proyectos 
                    WHERE dominio LIKE ? AND (eliminado = 0 OR eliminado IS NULL)
                    LIMIT 1
                ");
                $stmt->bind_param("s", $search_pattern);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $id_cliente = (int) $row['cliente_id'];
                    error_log("[crear_ticket_whatsapp_v2] ✓ Cliente encontrado por dominio en tabla proyectos: ID=" . $id_cliente);
                }
                $stmt->close();
            }

            // Obtener datos del cliente
            if ($id_cliente) {
                $stmt = $conn->prepare("SELECT id, nombre_contacto, empresa FROM clientes WHERE id = ? LIMIT 1");
                $stmt->bind_param("i", $id_cliente);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $cliente = $result->fetch_assoc();
                    $nombre_cliente = $cliente['nombre_contacto'] ?: $cliente['empresa'];
                }
                $stmt->close();
            }
        }
    }

    // PRIORIDAD 3: Buscar por teléfono
    if (!$id_cliente && !empty($telefono)) {
        $telefono_clean = preg_replace('/[^0-9]/', '', $telefono);
        $telefono_last10 = get_last_10_digits($telefono);
        error_log("[crear_ticket_whatsapp_v2] Buscando por teléfono: " . $telefono . " (últimos 10: " . $telefono_last10 . ")");
        
        // Buscar comparando los últimos 10 dígitos
        // Esto maneja: +5214775579264, +524775579264, 4775579264, 8112120774, etc.
        $stmt = $conn->prepare("
            SELECT id, nombre_contacto, empresa, telefono 
            FROM clientes 
            WHERE RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefono, '+', ''), ' ', ''), '-', ''), '(', ''), ')', ''), 10) = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $telefono_last10);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $cliente = $result->fetch_assoc();
            $id_cliente = $cliente['id'];
            $nombre_cliente = $cliente['nombre_contacto'] ?: $cliente['empresa'];
            error_log("[crear_ticket_whatsapp_v2] ✓ Cliente encontrado por teléfono: ID=" . $id_cliente . " (Tel BD: " . $cliente['telefono'] . ")");
        } else {
            error_log("[crear_ticket_whatsapp_v2] ✗ No se encontró cliente con últimos 10 dígitos: " . $telefono_last10);
        }
        $stmt->close();
    }

    // Si no se encontró el cliente
    if (!$id_cliente) {
        error_log("[crear_ticket_whatsapp_v2] ✗ NO se encontró cliente con los datos proporcionados");
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'No se encontró un cliente con los datos proporcionados',
            'debug' => [
                'telefono' => $telefono,
                'telefono_ultimos_10' => !empty($telefono) ? get_last_10_digits($telefono) : null,
                'domain_name' => $domain_name,
                'cliente_id' => $cliente_id,
                'nota' => 'Búsqueda por: 1) cliente_id, 2) dominio, 3) últimos 10 dígitos del teléfono'
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Validar y formatear fecha límite
    $fecha_lim_sql = null;
    if (!empty($fecha_limite)) {
        $fecha_timestamp = strtotime($fecha_limite);
        if ($fecha_timestamp !== false) {
            $fecha_lim_sql = date('Y-m-d H:i:s', $fecha_timestamp);
        }
    }

    // LOG: Datos finales antes de insertar
    error_log("[crear_ticket_whatsapp] === PREPARANDO INSERCIÓN ===");
    error_log("[crear_ticket_whatsapp] id_cliente: " . $id_cliente);
    error_log("[crear_ticket_whatsapp] nombre_cliente: " . $nombre_cliente);
    error_log("[crear_ticket_whatsapp] titulo: " . $titulo);
    error_log("[crear_ticket_whatsapp] descripcion: " . $descripcion);
    error_log("[crear_ticket_whatsapp] prioridad: " . $prioridad);
    error_log("[crear_ticket_whatsapp] fecha_lim_sql: " . ($fecha_lim_sql ?: 'NULL'));

    // Insertar ticket en la tabla solicitudes
    $stmt_insert = $conn->prepare("
        INSERT INTO solicitudes 
        (id_cliente, titulo, descripcion, prioridad, fecha_solicitud, fecha_lim, estado, nombreMSJ, id_proyecto) 
        VALUES (?, ?, ?, ?, NOW(), ?, 'pendiente', ?, NULL)
    ");

    $nombreMSJ = "WhatsApp Bot";
    $stmt_insert->bind_param(
        "isssss",
        $id_cliente,
        $titulo,
        $descripcion,
        $prioridad,
        $fecha_lim_sql,
        $nombreMSJ
    );

    if ($stmt_insert->execute()) {
        $ticket_id = $stmt_insert->insert_id;
        $stmt_insert->close();

        error_log("[crear_ticket_whatsapp] ✓ Ticket creado exitosamente: #" . $ticket_id);

        // Respuesta exitosa
        echo json_encode([
            'success' => true,
            'message' => 'Ticket creado exitosamente',
            'data' => [
                'ticket_id' => $ticket_id,
                'id_cliente' => $id_cliente,
                'nombre_cliente' => $nombre_cliente,
                'titulo' => $titulo,
                'descripcion' => $descripcion,
                'prioridad' => $prioridad,
                'fecha_limite' => $fecha_lim_sql,
                'estado' => 'pendiente'
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        error_log("[crear_ticket_whatsapp] ERROR SQL: " . $stmt_insert->error);
        throw new Exception("Error al insertar el ticket: " . $stmt_insert->error);
    }

} catch (Exception $e) {
    error_log("[crear_ticket_whatsapp] ERROR CATCH: " . $e->getMessage());
    error_log("[crear_ticket_whatsapp] Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al procesar la solicitud: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>