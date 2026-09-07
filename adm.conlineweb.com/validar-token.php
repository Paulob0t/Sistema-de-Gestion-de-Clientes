<?php
// Valida el token recibido por cookie

if (!isset($_COOKIE['auth_token'])) {
    http_response_code(401);
    echo 'ERROR';
    exit;
}

$token = $_COOKIE['auth_token'];
$tokenFile = __DIR__ . "/tokens/$token";

if (file_exists($tokenFile)) {
    // Leer ID del usuario para validar
    $userId = file_get_contents($tokenFile);
    if ($userId) {
        echo 'OK';
        exit;
    }
}

http_response_code(401);
echo 'ERROR';
