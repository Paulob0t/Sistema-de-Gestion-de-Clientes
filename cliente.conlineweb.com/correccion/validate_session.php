<?php
// Iniciar sesión
session_start();

// Función para validar sesión
function validateSession($session_id) {
    // Si no hay session_id, retornar inválido
    if (empty($session_id)) {
        return false;
    }
    
    // Verificar si la sesión actual coincide con la enviada
    if (session_id() !== $session_id) {
        return false;
    }
    
    // Verificar si hay datos de usuario en la sesión
    if (!isset($_SESSION['uid']) || !isset($_SESSION['loggin'])) {
        return false;
    }
    
    // Verificar si la sesión sigue siendo válida (no expirada)
    if (isset($_SESSION['last_activity'])) {
        $timeout = 3600; // 1 hora en segundos
        if (time() - $_SESSION['last_activity'] > $timeout) {
            // Limpiar sesión expirada
            session_destroy();
            return false;
        }
    }
    
    return true;
}

// Obtener session_id del POST o de las cookies
$session_id = $_POST['session_id'] ?? $_COOKIE['PHPSESSID'] ?? '';

// Validar la sesión
if (validateSession($session_id)) {
    // Actualizar última actividad
    $_SESSION['last_activity'] = time();
    
    // Respuesta simple
    echo 'valid';
} else {
    // Sesión inválida
    echo 'invalid';
}
?>