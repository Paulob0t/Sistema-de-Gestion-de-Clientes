<?php

if (file_exists(__DIR__ . '/conn.local.php')) {
    require __DIR__ . '/conn.local.php';
    if (isset($conn) && $conn instanceof mysqli) {
        return;
    }
}

if (!function_exists('cw_load_dotenv')) {
    function cw_load_dotenv($path) {
        if (!file_exists($path)) return;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($key, $val) = explode('=', $line, 2);
                $key = trim($key);
                $val = trim(trim($val), '"\'');
                if (!array_key_exists($key, $_ENV)) {
                    $_ENV[$key] = $val;
                    putenv("$key=$val");
                }
            }
        }
    }
}
$envPath = dirname(__DIR__) . '/.env';
if (file_exists($envPath)) {
    cw_load_dotenv($envPath);
}

$dbhost = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? "cpanel.conlineweb.com");
$dbuser = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? "admin_clientes");
$dbpass = getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? "");
$dbname = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? "admin_clientes");

$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    if (defined('CW_CONN_SOFT') && CW_CONN_SOFT) {
        $conn = null;
        error_log('conn.php: soft fail — ' . mysqli_connect_error());
    } else {
        die('no hay conexion: ' . mysqli_connect_error());
    }
}

@$conn->query("SET time_zone = '-06:00'");

if (!isset($conn)) {
    error_log("conn.php: \$conn no está definido.");
} elseif ($conn->connect_error) {
    error_log("conn.php: Error de conexión - " . $conn->connect_error);
} else {
    error_log("conn.php: Conexión establecida correctamente.");
}


?>