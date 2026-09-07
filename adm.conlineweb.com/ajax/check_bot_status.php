<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../error.log');

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../auth_middleware.php';

// El bot siempre está activo (el webhook responde siempre).
// Este endpoint existe para que el frontend pueda consultar el estado.
// Si en el futuro se quiere un flag real, leerlo de un archivo de configuración.
echo json_encode(['success' => true, 'status' => 'active']);
