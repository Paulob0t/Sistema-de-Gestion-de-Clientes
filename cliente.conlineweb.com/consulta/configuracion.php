<?php

$envPath = dirname(__DIR__, 2) . '/.env';
if (file_exists($envPath)) {
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
    cw_load_dotenv($envPath);
}

$dbhost = getenv('DB_HOST_LEGACY') ?: ($_ENV['DB_HOST_LEGACY'] ?? "localhost");
$dbuser = getenv('DB_USER_LEGACY') ?: ($_ENV['DB_USER_LEGACY'] ?? "conlinew_login");
$dbpass = getenv('DB_PASS_LEGACY') ?: ($_ENV['DB_PASS_LEGACY'] ?? "");
$dbname = getenv('DB_NAME_LEGACY') ?: ($_ENV['DB_NAME_LEGACY'] ?? "conlinew_prueba-clientes");

$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

if(!$conn){
    echo "alert('no hay conexion: '".$mysql_connect_error()."');";
}
?>