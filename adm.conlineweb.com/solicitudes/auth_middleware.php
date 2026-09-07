<?php
/**
 * Middleware de Autenticación - Versión simplificada para que funcione el TXT
 * Si tu sistema ya tiene un sistema de login, ajusta las sesiones según corresponda.
 */

// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * FUNCIÓN TEMPORAL PARA PRUEBAS: 
 * Como no sabemos cómo manejas la autenticación, por ahora desactivamos la verificación.
 * ¡RECUERDA ACTIVARLA CUANDO EL SISTEMA ESTÉ COMPLETO!
 */
function verificarAutenticacion() {
    // Para producción, descomenta las líneas de abajo y ajusta según tu lógica de login
    /*
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: login.php');
        exit;
    }
    */
    return true; // Permite el acceso temporalmente
}

// Ejecutar verificación
verificarAutenticacion();

// Fin del middleware
?>