<?php

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

$dbhost_hp = getenv('DB_HOST_HP') ?: ($_ENV['DB_HOST_HP'] ?? "cpanel.conlineweb.com");
$dbuser_hp = getenv('DB_USER_HP') ?: ($_ENV['DB_USER_HP'] ?? "conlineweb_hosting");
$dbpass_hp = getenv('DB_PASS_HP') ?: ($_ENV['DB_PASS_HP'] ?? "");
$dbname_hp = getenv('DB_NAME_HP') ?: ($_ENV['DB_NAME_HP'] ?? "conlineweb_hosting");

mysqli_report(MYSQLI_REPORT_OFF);
@$mysqli = mysqli_init();
if ($mysqli) {
    @$mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
    $ok = @$mysqli->real_connect($dbhost_hp, $dbuser_hp, $dbpass_hp, $dbname_hp);
    if ($ok) {
        $conn_hp = $mysqli;
        mysqli_set_charset($conn_hp, "utf8mb4");
    } else {
        $conn_hp = null;
        error_log('conn_hostingpro: ' . mysqli_connect_error());
        if (!(defined('CW_CONN_SOFT') && CW_CONN_SOFT)) {
            // Solo tumbar con die en páginas HTML; APIs JSON definen CW_CONN_SOFT
            if (!defined('CW_JSON_API') || !CW_JSON_API) {
                die("Sin conexión a HostingPro: " . mysqli_connect_error());
            }
        }
    }
} else {
    $conn_hp = null;
    if (!(defined('CW_CONN_SOFT') && CW_CONN_SOFT) && (!defined('CW_JSON_API') || !CW_JSON_API)) {
        die("Sin conexión a HostingPro: no se pudo iniciar mysqli");
    }
}
