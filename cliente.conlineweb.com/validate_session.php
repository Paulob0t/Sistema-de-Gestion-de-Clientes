<?php
require_once __DIR__ . '/includes/cliente_session.php';
cliente_start_session();

// Funci?n para validar sesi?n
function validateSession($session_id) {
    // Si no hay session_id, retornar inv?lido
    if (empty($session_id)) {
        return false;
    }
    
    // Verificar si la sesi?n actual coincide con la enviada
    if (session_id() !== $session_id) {
        return false;
    }
    
    // Verificar si hay datos de usuario en la sesi?n
    if (!isset($_SESSION['uid']) || empty($_SESSION['login'])) {
        return false;
    }
    
    // Verificar si la sesi?n sigue siendo v?lida (no expirada)
    if (isset($_SESSION['last_activity'])) {
        $timeout = 3600; // 1 hora en segundos
        if (time() - $_SESSION['last_activity'] > $timeout) {
            // Limpiar sesi?n expirada
            session_destroy();
            return false;
        }
    }
    
    return true;
}

// Obtener session_id del POST o de las cookies
$sessionCookie = cliente_session_name();
$session_id = $_POST['session_id'] ?? $_COOKIE[$sessionCookie] ?? '';

// Validar la sesi?n
if (validateSession($session_id)) {
    // Actualizar ?ltima actividad
    $_SESSION['last_activity'] = time();
    
    // Respuesta simple
    echo 'valid';
} else {
    // Sesi?n inv?lida
    echo 'invalid';
}
?>