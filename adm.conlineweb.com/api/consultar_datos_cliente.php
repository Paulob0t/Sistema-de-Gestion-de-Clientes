<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__.'/../conn.php';

/**
 * Extrae el dominio limpio de una URL o string
 * Ejemplos:
 * - https://efegepho.com.mx/wp-login.php -> efegepho.com.mx
 * - efegepho.com.mx -> efegepho.com.mx
 * - www.efegepho.com.mx -> efegepho.com.mx
 */
function extractCleanDomain($input) {
    if (empty($input)) {
        return '';
    }
    
    // Remover espacios
    $input = trim($input);
    
    // Si tiene protocolo, parsear URL
    if (preg_match('#^https?://#i', $input)) {
        $parsed = parse_url($input);
        $host = $parsed['host'] ?? '';
    } else {
        // Extraer solo la parte del dominio (remover path si existe)
        $parts = explode('/', $input);
        $host = $parts[0];
    }
    
    // Remover www.
    $host = preg_replace('/^www\./i', '', $host);
    
    return strtolower($host);
}

/**
 * Función para extraer los últimos 10 dígitos de un teléfono (formato México)
 */
function get_last_10_digits($phone) {
    $digits = preg_replace('/[^0-9]/', '', (string)$phone);
    if (strlen($digits) >= 10) {
        return substr($digits, -10);
    }
    return $digits;
}

function fetchClientById($conn, $clienteId)
{
    $clienteId = (int)$clienteId;
    if ($clienteId <= 0) {
        return null;
    }

    $stmt = $conn->prepare("SELECT id, nombre_contacto, correo FROM clientes WHERE id = ? LIMIT 1");
    if (!$stmt) {
        error_log("Error al preparar consulta de clientes por ID: " . $conn->error);
        return null;
    }
    $stmt->bind_param("i", $clienteId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = ($result && $result->num_rows > 0) ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row;
}

// Obtener parámetros de búsqueda
$client_name = isset($_GET['client_name']) ? trim($_GET['client_name']) : '';
$client_email = isset($_GET['client_email']) ? trim($_GET['client_email']) : '';
$client_phone = isset($_GET['client_phone']) ? trim($_GET['client_phone']) : '';
$domain_name = isset($_GET['domain_name']) ? trim($_GET['domain_name']) : '';
$project_name = isset($_GET['project_name']) ? trim($_GET['project_name']) : '';
$id_cliente = isset($_GET['id']) ? (int)$_GET['id'] : 0;

error_log("[consultar_datos_cliente] ========== INICIO ==========");
error_log("[consultar_datos_cliente] Parámetros recibidos: name=$client_name, email=$client_email, phone=$client_phone, domain=$domain_name, project=$project_name, id=$id_cliente");

// Validar que se proporcione al menos un parámetro
if (empty($client_name) && empty($client_email) && empty($client_phone) && empty($domain_name) && empty($project_name) && $id_cliente <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Debe proporcionar al menos uno de los siguientes parámetros: client_name, client_email, client_phone, domain_name, project_name o id'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Verificar conexión
    if (!$conn || $conn->connect_error) {
        error_log("[consultar_datos_cliente] Error de conexión: " . ($conn->connect_error ?? 'desconocido'));
        throw new Exception("Error de conexión a la base de datos: " . ($conn->connect_error ?? 'desconocido'));
    }
    
    error_log("[consultar_datos_cliente] Conexión a BD exitosa");
    
    $cliente_id = null;
    $cliente_data = null;
    
    // BUSCAR POR DOMINIO (prioridad alta)
    if (!empty($domain_name)) {
        error_log("[consultar_datos_cliente] Dominio recibido: " . $domain_name);
        $clean_domain = extractCleanDomain($domain_name);
        error_log("[consultar_datos_cliente] Buscando dominio limpio: " . $clean_domain);
        
        if (!empty($clean_domain)) {
            // Buscar dominio en la tabla dominios en el campo url_dominio (sin JOIN para evitar perder coincidencias)
            $stmt = $conn->prepare("
                SELECT cliente_id, url_dominio
                FROM dominios
                WHERE url_dominio LIKE ? AND (eliminado = 0 OR eliminado IS NULL)
                LIMIT 1
            ");
            
            if (!$stmt) {
                error_log("Error al preparar consulta de dominios: " . $conn->error);
                throw new Exception("Error al preparar consulta: " . $conn->error);
            }
            
            $search_pattern = "%{$clean_domain}%";
            error_log("Patrón de búsqueda: " . $search_pattern);
            $stmt->bind_param("s", $search_pattern);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                error_log("[consultar_datos_cliente] ✓ Dominio encontrado en tabla dominios");
                $row = $result->fetch_assoc();
                $cliente_id = (int)($row['cliente_id'] ?? 0);
                error_log("[consultar_datos_cliente] Cliente ID encontrado: " . $cliente_id);
                $cliente_data = fetchClientById($conn, $cliente_id);
            } else {
                error_log("[consultar_datos_cliente] ✗ Dominio NO encontrado en tabla dominios (patrón: " . $search_pattern . ")");
            }
            $stmt->close();
            
            // Si no se encuentra en dominios, buscar en proyectos
            if (!$cliente_id) {
                $stmt = $conn->prepare("
                    SELECT cliente_id, dominio
                    FROM proyectos
                    WHERE dominio LIKE ? AND (eliminado = 0 OR eliminado IS NULL)
                    LIMIT 1
                ");
                $stmt->bind_param("s", $search_pattern);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $cliente_id = (int)($row['cliente_id'] ?? 0);
                    $cliente_data = fetchClientById($conn, $cliente_id);
                }
                $stmt->close();
            }
        }
    }
    
    // BUSCAR POR NOMBRE DE PROYECTO
    if (!$cliente_id && !empty($project_name)) {
        $stmt = $conn->prepare("
            SELECT cliente_id, nombre_proyecto
            FROM proyectos
            WHERE nombre_proyecto LIKE ? AND (eliminado = 0 OR eliminado IS NULL)
            LIMIT 1
        ");
        $search_pattern = "%{$project_name}%";
        $stmt->bind_param("s", $search_pattern);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $cliente_id = (int)($row['cliente_id'] ?? 0);
            $cliente_data = fetchClientById($conn, $cliente_id);
        }
        $stmt->close();
    }
    
    // BUSCAR POR EMAIL
    if (!$cliente_id && !empty($client_email)) {
        $stmt = $conn->prepare("SELECT id, nombre_contacto, correo FROM clientes WHERE correo = ? LIMIT 1");
        $stmt->bind_param("s", $client_email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $cliente_id = $row['id'];
            $cliente_data = $row;
        }
        $stmt->close();
    }
    
    // BUSCAR POR TELÉFONO (comparando últimos 10 dígitos)
    if (!$cliente_id && !empty($client_phone)) {
        error_log("[consultar_datos_cliente] Iniciando búsqueda por teléfono: " . $client_phone);
        
        try {
            $telefono_last10 = get_last_10_digits($client_phone);
            error_log("[consultar_datos_cliente] Buscando por teléfono: " . $client_phone . " (últimos 10: " . $telefono_last10 . ")");
            
            $stmt = $conn->prepare("
                SELECT id, nombre_contacto, correo, telefono 
                FROM clientes 
                WHERE RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefono, '+', ''), ' ', ''), '-', ''), '(', ''), ')', ''), 10) = ?
                LIMIT 1
            ");
            
            if (!$stmt) {
                error_log("[consultar_datos_cliente] Error al preparar consulta de teléfono: " . $conn->error);
            } else {
                $stmt->bind_param("s", $telefono_last10);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $cliente_id = $row['id'];
                    $cliente_data = $row;
                    error_log("[consultar_datos_cliente] ✓ Cliente encontrado por teléfono: ID=" . $cliente_id . " (Tel BD: " . $row['telefono'] . ")");
                } else {
                    error_log("[consultar_datos_cliente] ✗ No se encontró cliente con últimos 10 dígitos: " . $telefono_last10);
                }
                $stmt->close();
            }
        } catch (Exception $ex) {
            error_log("[consultar_datos_cliente] Excepción en búsqueda por teléfono: " . $ex->getMessage());
        }
    }
    
    // BUSCAR POR NOMBRE
    if (!$cliente_id && !empty($client_name)) {
        $stmt = $conn->prepare("SELECT id, nombre_contacto, correo FROM clientes WHERE nombre_contacto LIKE ? LIMIT 1");
        $search_name = "%{$client_name}%";
        $stmt->bind_param("s", $search_name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $cliente_id = $row['id'];
            $cliente_data = $row;
        }
        $stmt->close();
    }
    
    // BUSCAR POR ID
    if (!$cliente_id && $id_cliente > 0) {
        $stmt = $conn->prepare("SELECT id, nombre_contacto, correo FROM clientes WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $id_cliente);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $cliente_id = $row['id'];
            $cliente_data = $row;
        }
        $stmt->close();
    }
    
    // Si no se encontró el cliente
    if (!$cliente_id || !$cliente_data) {
        error_log("[consultar_datos_cliente] ❌ Cliente NO encontrado. Parámetros: name=$client_name, email=$client_email, phone=$client_phone, domain=$domain_name, project=$project_name, id=$id_cliente");
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'No se encontró información del cliente o error en la consulta.',
            'debug' => [
                'client_name' => $client_name,
                'client_email' => $client_email,
                'client_phone' => $client_phone,
                'client_phone_last10' => !empty($client_phone) ? get_last_10_digits($client_phone) : null,
                'domain_name' => $domain_name,
                'domain_clean' => !empty($domain_name) ? extractCleanDomain($domain_name) : null,
                'project_name' => $project_name,
                'id' => $id_cliente
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Obtener todos los dominios del cliente
    $stmt_dominios = $conn->prepare("SELECT url_dominio FROM dominios WHERE cliente_id = ? AND eliminado = 0");
    $stmt_dominios->bind_param("i", $cliente_id);
    $stmt_dominios->execute();
    $result_dominios = $stmt_dominios->get_result();
    
    $dominios = [];
    while ($row = $result_dominios->fetch_assoc()) {
        $dominios[] = $row['url_dominio'];
    }
    $stmt_dominios->close();
    
    // Obtener proyectos del cliente
    $stmt_proyectos = $conn->prepare("SELECT id_proyecto, nombre_proyecto FROM proyectos WHERE id_cliente = ?");
    $stmt_proyectos->bind_param("i", $cliente_id);
    $stmt_proyectos->execute();
    $result_proyectos = $stmt_proyectos->get_result();
    
    $proyectos = [];
    while ($row = $result_proyectos->fetch_assoc()) {
        $proyectos[] = [
            'id' => $row['id_proyecto'],
            'nombre' => $row['nombre_proyecto']
        ];
    }
    $stmt_proyectos->close();
    
    // Preparar respuesta completa
    echo json_encode([
        'success' => true,
        'data' => [
            'cliente_id' => $cliente_data['id'],
            'nombre' => $cliente_data['nombre_contacto'],
            'email' => $cliente_data['correo'],
            'dominios' => $dominios,
            'proyectos' => $proyectos,
            'servicios_activos' => count($dominios) + count($proyectos)
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("[consultar_datos_cliente] ========== EXCEPCIÓN ==========");
    error_log("[consultar_datos_cliente] Excepción capturada: " . $e->getMessage());
    error_log("[consultar_datos_cliente] Archivo: " . $e->getFile() . " Línea: " . $e->getLine());
    error_log("[consultar_datos_cliente] Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'No se encontró información del cliente o error en la consulta.',
        'debug' => [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
