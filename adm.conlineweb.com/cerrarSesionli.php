<?php
// Definir constante para incluir solo funciones sin validaci贸n
define('AUTH_MIDDLEWARE_FUNCTIONS_ONLY', true);

// Incluir el middleware para usar la funci贸n destroySession
require_once __DIR__.'/auth_middleware.php';

// Usar la funci贸n del middleware que limpia todo correctamente
destroySession();

// Redirigir al login centralizado
header("Location: https://cliente.conlineweb.com/ingresoli.php?logout=1");
exit();
?>