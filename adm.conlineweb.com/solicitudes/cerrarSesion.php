<?php
// Definir constante para incluir solo funciones sin validación
define('AUTH_MIDDLEWARE_FUNCTIONS_ONLY', true);

// Incluir el middleware para usar la función destroySession
require_once __DIR__.'/../auth_middleware.php';

// Usar la función del middleware que limpia todo correctamente
destroySession();

// Redirigir al login centralizado
header("Location: https://cliente.conlineweb.com/ingreso.php?logout=1");
exit();
?>