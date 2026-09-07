<?php
// Middleware de autenticación para Línea Italia (usuario tipo 4)

// Configurar dominio de cookies ANTES de session_start()
ini_set('session.cookie_domain', '.conlineweb.com');
ini_set('session.cookie_path', '/');
$__is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
ini_set('session.cookie_secure', $__is_https ? '1' : '0');
ini_set('session.cookie_httponly', '1');

session_start();

// Log de debugging para sesión
error_log("DEBUG auth_externa - Session ID: " . session_id());
error_log("DEBUG auth_externa - Session data: " . json_encode($_SESSION ?? []));
error_log("DEBUG auth_externa - Cookie data: " . json_encode($_COOKIE ?? []));

// Verificar sesión básica o intentar cargar desde cookies
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    // Intentar cargar desde cookie compartida
    if (isset($_COOKIE['user_session_data'])) {
        $cookieData = json_decode($_COOKIE['user_session_data'], true);
        error_log("Intentando cargar desde cookie: " . json_encode($cookieData));
        
        // Verificar que sea un tipo 4 (empresa) y que sea el usuario lineaItalia (ID 95)
        if ($cookieData && $cookieData['login'] === true && (int)$cookieData['tipo'] === 4 && (int)$cookieData['uid'] === 95) {
            // Restaurar sesión desde cookie
            $_SESSION['uid'] = (int)$cookieData['uid'];
            $_SESSION['login'] = true;
            $_SESSION['tipo'] = (int)$cookieData['tipo'];
            $_SESSION['last_activity'] = time();
            if (!empty($cookieData['agente_id'])) {
                $_SESSION['agente_id'] = (int)$cookieData['agente_id'];
            }
            error_log("Sesión restaurada desde cookie para usuario lineaItalia (ID 95, tipo 4)");
        } else {
            error_log("Cookie no válida para acceso a tickets_externa - UID: " . ($cookieData['uid'] ?? 'no definido') . ", Tipo: " . ($cookieData['tipo'] ?? 'no definido') . " - Redirigiendo al login");
            header('Location: https://cliente.conlineweb.com/ingreso.php?error=' . urlencode('Acceso restringido a Línea Italia'));
            exit;
        }
    } else {
        error_log("No hay sesión ni cookie válida - Redirigiendo al login");
        header('Location: https://cliente.conlineweb.com/ingreso.php?error=' . urlencode('Debe iniciar sesión para acceder'));
        exit;
    }
}

// Verificar que sea usuario tipo 4 (Línea Italia) y específicamente el usuario ID 95
if (!isset($_SESSION['tipo']) || (int)$_SESSION['tipo'] !== 4 || !isset($_SESSION['uid']) || (int)$_SESSION['uid'] !== 95) {
    // Log del intento de acceso no autorizado
    error_log("Acceso no autorizado a tickets_externa - Usuario tipo: " . ($_SESSION['tipo'] ?? 'no definido') . " UID: " . ($_SESSION['uid'] ?? 'no definido') . " - Solo Línea Italia (ID 95) puede acceder");
    header('Location: https://cliente.conlineweb.com/ingreso.php?error=' . urlencode('Acceso restringido a Línea Italia'));
    exit;
}

// Verificar timeout de sesión
$timeout = 3600; // 1 hora
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_destroy();
    error_log("Sesión expirada para tickets_externa - Redirigiendo al login");
    header('Location: https://cliente.conlineweb.com/ingreso.php');
    exit;
}

// Actualizar última actividad
$_SESSION['last_activity'] = time();

// Actualizar cookies compartidas para mantener sesión sincronizada
$secure = $__is_https;
setcookie(
    'user_session_data', 
    json_encode([
        'uid' => $_SESSION['uid'],
        'tipo' => $_SESSION['tipo'],
        'login' => true,
        'timestamp' => time(),
        'agente_id' => $_SESSION['agente_id'] ?? null
    ]), 
    time() + 3600, 
    '/', 
    '.conlineweb.com', 
    $secure, 
    true
);

// Variables para Línea Italia
$usuario_login_id = (int)$_SESSION['uid']; // ID 95 (lineaItalia)
$cliente_id = 90; // Línea Italia en tabla clientes
$usuario_tipo = 4;

// Incluir conexión a base de datos
require_once __DIR__ . '/../conn.php';

// Función para obtener datos del usuario y cliente
function obtener_datos_linea_italia() {
    global $conn, $usuario_login_id, $cliente_id;
    
    // Obtener datos del usuario login
    $stmt = $conn->prepare("SELECT usuario FROM login WHERE id = ? AND id_tipo_usuario = 4");
    $stmt->bind_param('i', $usuario_login_id);
    $stmt->execute();
    $login_result = $stmt->get_result();
    
    if (!$login_row = $login_result->fetch_assoc()) {
        return null;
    }
    
    // Obtener datos del cliente
    $stmt2 = $conn->prepare("SELECT empresa, nombre_contacto FROM clientes WHERE id = ?");
    $stmt2->bind_param('i', $cliente_id);
    $stmt2->execute();
    $cliente_result = $stmt2->get_result();
    
    if ($cliente_row = $cliente_result->fetch_assoc()) {
        return [
            'login_id' => $usuario_login_id,
            'cliente_id' => $cliente_id,
            'usuario' => $login_row['usuario'],
            'empresa' => $cliente_row['empresa'],
            'contacto' => $cliente_row['nombre_contacto'],
            'nombre_display' => $cliente_row['empresa']
        ];
    }
    
    return null;
}

// Obtener datos de Línea Italia
$linea_italia = obtener_datos_linea_italia();

if (!$linea_italia) {
    error_log("No se encontraron datos para Línea Italia - Usuario ID: $usuario_login_id");
    session_destroy();
    header('Location: https://cliente.conlineweb.com/ingreso.php');
    exit;
}

// Variables disponibles en el sistema
$usuario_actual = $linea_italia;

// Log de acceso exitoso
error_log("Acceso autorizado a sistema Línea Italia - Usuario: {$linea_italia['usuario']} - Empresa: {$linea_italia['empresa']}");
?>