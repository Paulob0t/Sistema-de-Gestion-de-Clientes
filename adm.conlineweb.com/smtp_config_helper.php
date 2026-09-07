<?php
/**
 * Helper para configuración SMTP multi-sistema
 * Devuelve credenciales según el sistema activo (conlineweb o hostingpro)
 */

/**
 * Obtener configuración SMTP según el sistema
 * @param string $sistema 'conlineweb' o 'hostingpro'
 * @return array Configuración SMTP completa
 */
function get_smtp_config($sistema = 'conlineweb') {
    $envPath = dirname(__DIR__) . '/.env';
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

    $host = getenv('SMTP_HOST') ?: ($_ENV['SMTP_HOST'] ?? 'smtp.gmail.com');
    $port = (int) (getenv('SMTP_PORT') ?: ($_ENV['SMTP_PORT'] ?? 587));
    $secure = getenv('SMTP_SECURE') ?: ($_ENV['SMTP_SECURE'] ?? 'tls');
    $user = getenv('SMTP_USER') ?: ($_ENV['SMTP_USER'] ?? 'servicios@conlineweb.com');
    $pass = getenv('SMTP_PASS') ?: ($_ENV['SMTP_PASS'] ?? '');
    $fromEmail = getenv('SMTP_FROM_EMAIL') ?: ($_ENV['SMTP_FROM_EMAIL'] ?? $user);

    return [
        'host' => $host,
        'port' => $port,
        'secure' => $secure,
        'auth' => true,
        'username' => $user,
        'password' => $pass,
        'from_email' => $fromEmail,
        'from_name' => ($sistema === 'hostingpro') ? 'HostPro - Soporte' : 'ConlineWeb'
    ];
}

/**
 * Configurar PHPMailer con credenciales según sistema
 * @param PHPMailer $mail Instancia de PHPMailer
 * @param string $sistema 'conlineweb' o 'hostingpro'
 * @param bool $debug Activar debug SMTP
 */
function configure_phpmailer_by_system($mail, $sistema = 'conlineweb', $debug = false) {
    $config = get_smtp_config($sistema);
    
    $mail->isSMTP();
    $mail->SMTPAuth = $config['auth'];
    $mail->SMTPSecure = $config['secure'];
    $mail->Port = $config['port'];
    $mail->Host = $config['host'];
    $mail->Username = $config['username'];
    $mail->Password = $config['password'];
    // Evita cuelgues eternos en local (MAMP/XAMPP) y producción.
    $mail->Timeout = 12;
    $mail->SMTPKeepAlive = false;
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ];
    
    if ($debug) {
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str, $level) {
            error_log("SMTP Debug [$level]: $str");
        };
    } else {
        $mail->SMTPDebug = 0;
    }
    
    $mail->setFrom($config['from_email'], $config['from_name']);
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    
    return $config;
}
