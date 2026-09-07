<?php
/**
 * LOGIN portal cliente + SSO staff → adm.
 * Correo de alerta: síncrono ANTES del JSON (en cPanel el shutdown tras fastcgi suele no enviar SMTP).
 * Log BD: en shutdown (no bloquea la respuesta).
 */
declare(strict_types=1);

@ini_set('display_errors', '0');
@ini_set('html_errors', '0');
header('Content-Type: application/json; charset=utf-8');

$__cw_host = strtolower((string) preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
$isProduction = $__cw_host !== '' && preg_match('/(^|\.)conlineweb\.com$/', $__cw_host);

/**
 * Carga includes compartidos desde varias rutas posibles (local MAMP / cPanel).
 */
$cwRequireShared = static function (string $basename): bool {
    // Preferir includes del propio cliente (evita cargar una copia vieja en /home/.../includes/)
    $candidates = [
        __DIR__ . '/includes/' . $basename,
        '/home/conlineweb/cliente.conlineweb.com/includes/' . $basename,
        dirname(__DIR__) . '/includes/' . $basename,
        __DIR__ . '/../includes/' . $basename,
        '/home/conlineweb/includes/' . $basename,
    ];
    foreach ($candidates as $path) {
        if (is_file($path)) {
            require_once $path;
            if ($basename === 'cw_portal_login_alert.php') {
                error_log('cw login: alert cargado desde ' . $path);
            }

            return true;
        }
    }
    error_log('cw login: no se encontró ' . $basename);

    return false;
};

if (!function_exists('cw_login_run_after')) {
    /** Solo persistencia BD / SSO cookie con event_id. El correo ya se envió en sync. */
    function cw_login_run_after(): void
    {
        $ctx = $GLOBALS['cw_login_after'] ?? null;
        if (!is_array($ctx)) {
            return;
        }
        unset($GLOBALS['cw_login_after']);

        $candidates = [
            __DIR__ . '/includes/',
            dirname(__DIR__) . '/includes/',
            __DIR__ . '/../includes/',
            '/home/conlineweb/includes/',
            '/home/conlineweb/cliente.conlineweb.com/includes/',
            '/home/conlineweb/adm.conlineweb.com/includes/',
        ];
        $load = static function (string $file) use ($candidates): bool {
            foreach ($candidates as $base) {
                $path = $base . $file;
                if (is_file($path)) {
                    require_once $path;

                    return true;
                }
            }

            return false;
        };

        try {
            $load('cw_portal_security.php');
            $load('cw_portal_login_log.php');
            // Fallback correo solo si el sync no lo envió (p.ej. include falló antes)
            if (empty($ctx['mail_sent'])) {
                $load('cw_portal_login_alert.php');
            }
        } catch (Throwable $e) {
            error_log('cw_login_run_after load: ' . $e->getMessage());
        }

        $conn = $ctx['conn'] ?? null;
        $hasConn = ($conn instanceof mysqli);
        if ($hasConn) {
            try {
                if (!@$conn->ping()) {
                    $hasConn = false;
                }
            } catch (Throwable $e) {
                $hasConn = false;
            }
        }

        $kind = (string) ($ctx['kind'] ?? '');
        $nombre = (string) ($ctx['nombre'] ?? '');
        $ip = (string) ($ctx['ip'] ?? '');
        $ua = (string) ($ctx['ua'] ?? '');
        $env = (string) ($ctx['env'] ?? '');
        $mailSent = !empty($ctx['mail_sent']);

        try {
            if ($kind === 'success') {
                $eventId = (int) ($ctx['login_event_id'] ?? 0);

                /*
                 * El acceso del personal interno ya quedó registrado en
                 * caliente, porque su identificador tenía que ir dentro de la
                 * cookie SSO. Aquí solo falta completarle la geolocalización,
                 * que es la parte lenta y por eso se dejó para después de
                 * responder al navegador.
                 */
                if ($eventId > 0) {
                    if ($hasConn && function_exists('cw_portal_login_log_fill_geo')) {
                        cw_portal_login_log_fill_geo($conn, $eventId, $ip);
                    }
                } elseif ($hasConn && function_exists('cw_portal_login_log_record')) {
                    try {
                        $eventId = cw_portal_login_log_record($conn, [
                            'event_type' => 'success',
                            'uid' => (int) ($ctx['uid'] ?? 0),
                            'attempted_user' => $nombre,
                            'usuario' => (string) ($ctx['usuario'] ?? $nombre),
                            'tipo' => (int) ($ctx['tipo'] ?? 0),
                            'ip' => $ip,
                            'user_agent' => $ua,
                            'environment' => $env,
                            'session_token' => (string) ($ctx['session_id'] ?? ''),
                            'reason' => 'ok',
                            'with_geo' => true,
                        ]);
                        if ($eventId > 0 && session_status() === PHP_SESSION_ACTIVE) {
                            $_SESSION['cw_login_event_id'] = $eventId;
                        }
                        if ($eventId > 0 && !empty($ctx['sso_staff']) && function_exists('cliente_set_staff_sso_cookie')) {
                            $sso = [
                                'uid' => (int) ($ctx['uid'] ?? 0),
                                'tipo' => (int) ($ctx['tipo'] ?? 0),
                                'login' => true,
                                'timestamp' => time(),
                                'login_event_id' => $eventId,
                                'usuario' => (string) ($ctx['usuario'] ?? $nombre),
                                'alert_sent' => !empty($ctx['mail_sent']) ? 1 : 0,
                            ];
                            if (!empty($ctx['agente_id'])) {
                                $sso['agente_id'] = (int) $ctx['agente_id'];
                            }
                            cliente_set_staff_sso_cookie($sso);
                        }
                    } catch (Throwable $e) {
                        error_log('cw_login_run_after log success: ' . $e->getMessage());
                    }
                }
                if (!$mailSent && function_exists('cw_portal_notify_login')) {
                    cw_portal_notify_login([
                        'uid' => (int) ($ctx['uid'] ?? 0),
                        'usuario' => (string) ($ctx['usuario'] ?? $nombre),
                        'tipo' => (int) ($ctx['tipo'] ?? 0),
                        'ip' => $ip,
                        'user_agent' => $ua,
                        'agente_id' => $ctx['agente_id'] ?? null,
                        'environment' => $env,
                        'skip_geo' => true,
                    ]);
                }
            } elseif ($kind === 'blocked') {
                if ($hasConn && function_exists('cw_portal_login_log_record')) {
                    try {
                        cw_portal_login_log_record($conn, [
                            'event_type' => 'blocked',
                            'attempted_user' => $nombre,
                            'ip' => $ip,
                            'user_agent' => $ua,
                            'environment' => $env,
                            'reason' => (string) ($ctx['reason'] ?? 'rate_limit'),
                            'with_geo' => true,
                        ]);
                    } catch (Throwable $e) {
                        error_log('cw_login_run_after log blocked: ' . $e->getMessage());
                    }
                }
                if (!$mailSent && function_exists('cw_portal_notify_login_blocked')) {
                    cw_portal_notify_login_blocked([
                        'ip' => $ip,
                        'attempted_user' => $nombre,
                        'retry_after' => (int) ($ctx['retry'] ?? 0),
                        'user_agent' => $ua,
                        'skip_geo' => true,
                    ]);
                }
            } elseif ($kind === 'fail') {
                if ($hasConn && function_exists('cw_portal_login_log_record')) {
                    try {
                        cw_portal_login_log_record($conn, [
                            'event_type' => (string) ($ctx['event_type'] ?? 'fail'),
                            'uid' => isset($ctx['uid']) ? (int) $ctx['uid'] : null,
                            'attempted_user' => $nombre,
                            'usuario' => (string) ($ctx['usuario'] ?? $nombre),
                            'tipo' => array_key_exists('tipo', $ctx) ? $ctx['tipo'] : null,
                            'ip' => $ip,
                            'user_agent' => $ua,
                            'environment' => $env,
                            'reason' => (string) ($ctx['reason'] ?? 'fail'),
                            'with_geo' => false,
                        ]);
                    } catch (Throwable $e) {
                        error_log('cw_login_run_after log fail: ' . $e->getMessage());
                    }
                }
                if (!$mailSent && function_exists('cw_portal_notify_login_failed')) {
                    cw_portal_notify_login_failed([
                        'attempted_user' => $nombre,
                        'usuario' => (string) ($ctx['usuario'] ?? $nombre),
                        'uid' => $ctx['uid'] ?? null,
                        'tipo' => $ctx['tipo'] ?? null,
                        'ip' => $ip,
                        'user_agent' => $ua,
                        'reason' => (string) ($ctx['reason'] ?? ''),
                        'event_type' => (string) ($ctx['event_type'] ?? 'fail'),
                        'skip_geo' => true,
                    ]);
                }
            }
        } catch (Throwable $e) {
            error_log('cw_login_run_after: ' . $e->getMessage());
        }
    }
}

try {
    require_once __DIR__ . '/includes/cliente_session.php';
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error de sesión'], JSON_UNESCAPED_UNICODE);
    exit;
}

$hasSecurity = $cwRequireShared('cw_portal_security.php');
$hasAlert = $cwRequireShared('cw_portal_login_alert.php');

cliente_start_session();
if ($hasSecurity && function_exists('cw_portal_send_security_headers')) {
    cw_portal_send_security_headers(true);
}

if (!$isProduction) {
    $localAuth = __DIR__ . '/includes/cliente_local_auth.php';
    if (is_file($localAuth)) {
        require_once $localAuth;
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

include __DIR__ . '/conn.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => 'Servicio no disponible'], JSON_UNESCAPED_UNICODE);
    exit;
}

$nombre = trim((string) ($_POST['txusuario'] ?? ''));
$plainPass = (string) ($_POST['txpassword'] ?? '');
$captchaToken = trim((string) ($_POST['g-recaptcha-response'] ?? ''));
$ip = function_exists('cw_portal_client_ip') ? cw_portal_client_ip() : (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
$envLabel = $isProduction ? 'producción' : 'local';
$cwLower = static function (string $s): string {
    return function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s);
};

$scheduleAfter = static function (array $payload): void {
    $GLOBALS['cw_login_after'] = $payload;
    register_shutdown_function('cw_login_run_after');
};

/** Envío de alerta antes del JSON (crítico en cPanel). Devuelve true solo si SMTP/mail() OK. */
$notifyMailNow = static function (string $kind, array $payload) use ($hasAlert): bool {
    if (!$hasAlert) {
        error_log('cw login: alert module no cargado; correo diferido a shutdown');

        return false;
    }
    try {
        if ($kind === 'success' && function_exists('cw_portal_notify_login')) {
            return (bool) cw_portal_notify_login([
                'uid' => (int) ($payload['uid'] ?? 0),
                'usuario' => (string) ($payload['usuario'] ?? $payload['nombre'] ?? ''),
                'tipo' => (int) ($payload['tipo'] ?? 0),
                'ip' => (string) ($payload['ip'] ?? ''),
                'user_agent' => (string) ($payload['ua'] ?? ''),
                'agente_id' => $payload['agente_id'] ?? null,
                'environment' => (string) ($payload['env'] ?? ''),
                'skip_geo' => true,
            ]);
        }
        if ($kind === 'blocked' && function_exists('cw_portal_notify_login_blocked')) {
            return (bool) cw_portal_notify_login_blocked([
                'ip' => (string) ($payload['ip'] ?? ''),
                'attempted_user' => (string) ($payload['nombre'] ?? ''),
                'retry_after' => (int) ($payload['retry'] ?? 0),
                'user_agent' => (string) ($payload['ua'] ?? ''),
                'skip_geo' => true,
            ]);
        }
        if ($kind === 'fail' && function_exists('cw_portal_notify_login_failed')) {
            return (bool) cw_portal_notify_login_failed([
                'attempted_user' => (string) ($payload['nombre'] ?? ''),
                'usuario' => (string) ($payload['usuario'] ?? $payload['nombre'] ?? ''),
                'uid' => $payload['uid'] ?? null,
                'tipo' => $payload['tipo'] ?? null,
                'ip' => (string) ($payload['ip'] ?? ''),
                'user_agent' => (string) ($payload['ua'] ?? ''),
                'reason' => (string) ($payload['reason'] ?? ''),
                'event_type' => (string) ($payload['event_type'] ?? 'fail'),
                'skip_geo' => true,
            ]);
        }
    } catch (Throwable $e) {
        error_log('cw login notifyMailNow: ' . $e->getMessage());
    }

    return false;
};

$verifyPassword = static function (string $plain, string $stored) use ($hasSecurity): bool {
    if ($hasSecurity && function_exists('cw_portal_password_verify')) {
        return cw_portal_password_verify($plain, $stored);
    }
    $stored = trim($stored);
    if ($stored === '' || $plain === '') {
        return false;
    }
    if (strpos($stored, '$2y$') === 0 || strpos($stored, '$2a$') === 0) {
        return password_verify($plain, $stored);
    }
    if (preg_match('/^[a-f0-9]{32}$/i', $stored)) {
        return hash_equals(strtolower($stored), md5($plain));
    }

    return hash_equals($stored, $plain);
};

$needsRehash = static function (string $stored) use ($hasSecurity): bool {
    if ($hasSecurity && function_exists('cw_portal_password_needs_rehash')) {
        return cw_portal_password_needs_rehash($stored);
    }
    $stored = trim($stored);

    return $stored === '' || (bool) preg_match('/^[a-f0-9]{32}$/i', $stored);
};

$hashPassword = static function (string $plain) use ($hasSecurity): string {
    if ($hasSecurity && function_exists('cw_portal_password_hash')) {
        return cw_portal_password_hash($plain);
    }

    return password_hash($plain, PASSWORD_DEFAULT);
};

if ($hasSecurity && function_exists('cw_portal_rate_limit_hit')) {
    $rateIp = cw_portal_rate_limit_hit('login:ip:' . $ip, 20, 900);
    $rateUser = cw_portal_rate_limit_hit('login:user:' . $cwLower($nombre), 10, 900);
    if (!$rateIp['ok'] || !$rateUser['ok']) {
        $retry = max((int) $rateIp['retry_after'], (int) $rateUser['retry_after']);
        $after = [
            'kind' => 'blocked',
            'nombre' => $nombre,
            'ip' => $ip,
            'ua' => $ua,
            'env' => $envLabel,
            'retry' => $retry,
            'reason' => !$rateIp['ok'] ? 'rate_limit_ip' : 'rate_limit_user',
            'conn' => $conn,
            'mail_sent' => $notifyMailNow('blocked', [
                'nombre' => $nombre,
                'ip' => $ip,
                'ua' => $ua,
                'retry' => $retry,
            ]),
        ];
        $scheduleAfter($after);
        http_response_code(429);
        echo json_encode([
            'status' => 'error',
            'message' => 'Demasiados intentos. Espera unos minutos e inténtalo de nuevo.',
            'retry_after' => $retry,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if ($isProduction && $hasSecurity && function_exists('cw_portal_verify_recaptcha')) {
    $captchaUserKey = $cwLower($nombre);
    $captchaGrace = !empty($_SESSION['cw_login_captcha_ok'])
        && isset($_SESSION['cw_login_captcha_ok_at'], $_SESSION['cw_login_captcha_user'])
        && $_SESSION['cw_login_captcha_user'] === $captchaUserKey
        && (time() - (int) $_SESSION['cw_login_captcha_ok_at']) < 300;

    if (!$captchaGrace) {
        $captcha = cw_portal_verify_recaptcha($captchaToken);
        if (empty($captcha['ok'])) {
            $after = [
                'kind' => 'fail',
                'event_type' => 'captcha_fail',
                'reason' => 'recaptcha',
                'nombre' => $nombre,
                'ip' => $ip,
                'ua' => $ua,
                'env' => $envLabel,
                'conn' => $conn,
                'mail_sent' => $notifyMailNow('fail', [
                    'nombre' => $nombre,
                    'ip' => $ip,
                    'ua' => $ua,
                    'reason' => 'recaptcha',
                    'event_type' => 'captcha_fail',
                ]),
            ];
            $scheduleAfter($after);
            echo json_encode([
                'status' => 'error',
                'message' => 'Completa el captcha para continuar.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $_SESSION['cw_login_captcha_ok'] = true;
        $_SESSION['cw_login_captcha_ok_at'] = time();
        $_SESSION['cw_login_captcha_user'] = $captchaUserKey;
    }
}

$_SESSION['id'] = null;
$_SESSION['login'] = false;

if ($nombre === '' || $plainPass === '') {
    $after = [
        'kind' => 'fail',
        'event_type' => 'fail',
        'reason' => 'empty_credentials',
        'nombre' => $nombre,
        'ip' => $ip,
        'ua' => $ua,
        'env' => $envLabel,
        'conn' => $conn,
        'mail_sent' => $notifyMailNow('fail', [
            'nombre' => $nombre,
            'ip' => $ip,
            'ua' => $ua,
            'reason' => 'empty_credentials',
            'event_type' => 'fail',
        ]),
    ];
    $scheduleAfter($after);
    echo json_encode(['status' => 'error', 'message' => 'Usuario o contraseña incorrectos'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $conn->prepare('SELECT id, usuario, contrasena, id_tipo_usuario FROM login WHERE usuario = ? LIMIT 1');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error interno'], JSON_UNESCAPED_UNICODE);
    exit;
}
$stmt->bind_param('s', $nombre);
$stmt->execute();
$result = $stmt->get_result();
$res = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!is_array($res) || !$verifyPassword($plainPass, (string) ($res['contrasena'] ?? ''))) {
    $failUid = is_array($res) ? (int) ($res['id'] ?? 0) : null;
    $failUser = is_array($res) ? (string) ($res['usuario'] ?? $nombre) : $nombre;
    $failTipo = is_array($res) ? (int) ($res['id_tipo_usuario'] ?? 0) : null;
    $failReason = !is_array($res) ? 'user_not_found' : 'bad_password';
    $after = [
        'kind' => 'fail',
        'event_type' => 'fail',
        'reason' => $failReason,
        'uid' => $failUid,
        'usuario' => $failUser,
        'tipo' => $failTipo,
        'nombre' => $nombre,
        'ip' => $ip,
        'ua' => $ua,
        'env' => $envLabel,
        'conn' => $conn,
        'mail_sent' => $notifyMailNow('fail', [
            'nombre' => $nombre,
            'usuario' => $failUser,
            'uid' => $failUid,
            'tipo' => $failTipo,
            'ip' => $ip,
            'ua' => $ua,
            'reason' => $failReason,
            'event_type' => 'fail',
        ]),
    ];
    $scheduleAfter($after);
    echo json_encode(['status' => 'error', 'message' => 'Usuario o contraseña incorrectos'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stored = (string) ($res['contrasena'] ?? '');
if ($needsRehash($stored)) {
    $newHash = $hashPassword($plainPass);
    $upd = $conn->prepare('UPDATE login SET contrasena = ? WHERE id = ? LIMIT 1');
    if ($upd) {
        $uidUpd = (int) $res['id'];
        $upd->bind_param('si', $newHash, $uidUpd);
        $upd->execute();
        $upd->close();
    }
}

/*
 * GPS opcional en login cliente (ya no bloquea). La ubicación obligatoria
 * se pide una vez por dispositivo al entrar a adm.conlineweb.com.
 */
$cwTipoAcceso = (int) ($res['id_tipo_usuario'] ?? 0);
$cwGpsLat = null;
$cwGpsLng = null;
$cwGpsAcc = null;

if ($cwTipoAcceso >= 1 && $cwTipoAcceso <= 5) {
    $latRaw = trim((string) ($_POST['geo_lat'] ?? ''));
    $lngRaw = trim((string) ($_POST['geo_lng'] ?? ''));
    $accRaw = trim((string) ($_POST['geo_accuracy'] ?? ''));
    if ($latRaw !== '' && $lngRaw !== '' && is_numeric($latRaw) && is_numeric($lngRaw)) {
        $lat = (float) $latRaw;
        $lng = (float) $lngRaw;
        $valida = !function_exists('cw_portal_login_gps_valida') || cw_portal_login_gps_valida($lat, $lng);
        if ($valida) {
            $cwGpsLat = $lat;
            $cwGpsLng = $lng;
            $cwGpsAcc = $accRaw !== '' && is_numeric($accRaw) ? (int) round((float) $accRaw) : null;
        }
    }
}

if ($hasSecurity && function_exists('cw_portal_rate_limit_clear')) {
    cw_portal_rate_limit_clear('login:ip:' . $ip);
    cw_portal_rate_limit_clear('login:user:' . $cwLower($nombre));
}

session_regenerate_id(true);

$_SESSION['id'] = session_id();
$_SESSION['uid'] = (int) $res['id'];
$_SESSION['login'] = true;
$_SESSION['tipo'] = (int) $res['id_tipo_usuario'];
$_SESSION['last_activity'] = time();
$_SESSION['user_ip'] = $ip;
if ($hasSecurity && function_exists('cw_portal_csrf_token')) {
    cw_portal_csrf_token();
}

$tipo_usuario = (int) $res['id_tipo_usuario'];
$uid = (int) $res['id'];
$agente_id = null;

$agente_stmt = $conn->prepare('SELECT id FROM agentes WHERE Idusu = ? LIMIT 1');
if ($agente_stmt) {
    $agente_stmt->bind_param('i', $uid);
    $agente_stmt->execute();
    $agente_res = $agente_stmt->get_result();
    if ($agente_res && ($agente_row = $agente_res->fetch_assoc())) {
        $agente_id = (int) $agente_row['id'];
        $_SESSION['agente_id'] = $agente_id;
    }
    $agente_stmt->close();
}

$usuarioOk = (string) ($res['usuario'] ?? $nombre);
$mailSent = $notifyMailNow('success', [
    'uid' => $uid,
    'usuario' => $usuarioOk,
    'tipo' => $tipo_usuario,
    'nombre' => $nombre,
    'ip' => $ip,
    'ua' => $ua,
    'env' => $envLabel,
    'agente_id' => $agente_id,
]);

$localToken = '';

/*
 * Registro en caliente del acceso del personal interno.
 *
 * El registro general va diferido a cw_login_run_after(), que corre cuando la
 * respuesta ya se envió. Eso sirve para el portal de clientes, pero no para el
 * panel: adm identifica su sesión por el identificador de este acceso, y ese
 * dato tiene que viajar dentro de la cookie SSO que se escribe unas líneas más
 * abajo. Al diferirlo, la cookie salía sin él y el panel no podía saber cuál
 * era su propia sesión ni si se la habían cerrado desde Seguridad → Accesos.
 *
 * Se inserta sin geolocalización para no bloquear el login con una consulta a
 * Internet; cw_login_run_after la completa después sobre esta misma fila.
 */
$staffLoginEventId = 0;
if ($tipo_usuario >= 1 && $tipo_usuario <= 5
    && isset($conn) && $conn instanceof mysqli
    && function_exists('cw_portal_login_log_record')) {
    try {
        $staffLoginEventId = cw_portal_login_log_record($conn, [
            'event_type' => 'success',
            'uid' => $uid,
            'attempted_user' => $nombre,
            'usuario' => $usuarioOk,
            'tipo' => $tipo_usuario,
            'ip' => $ip,
            'user_agent' => $ua,
            'environment' => $envLabel,
            'session_token' => (string) session_id(),
            'reason' => 'ok',
            'with_geo' => false,
        ]);
        if ($staffLoginEventId > 0) {
            $_SESSION['cw_login_event_id'] = $staffLoginEventId;

            if ($cwGpsLat !== null && $cwGpsLng !== null
                && function_exists('cw_portal_login_log_set_gps')) {
                cw_portal_login_log_set_gps($conn, $staffLoginEventId, $cwGpsLat, $cwGpsLng, $cwGpsAcc);
            }
        }
    } catch (Throwable $e) {
        error_log('login staff event: ' . $e->getMessage());
    }
}

if ($isProduction) {
    if ($tipo_usuario >= 1 && $tipo_usuario <= 5 && function_exists('cliente_set_staff_sso_cookie')) {
        cliente_set_staff_sso_cookie([
            'uid' => $uid,
            'tipo' => $tipo_usuario,
            'login' => true,
            'timestamp' => time(),
            'agente_id' => $agente_id,
            'usuario' => $usuarioOk,
            'alert_sent' => $mailSent ? 1 : 0,
            /*
             * Identificador del acceso recién registrado. El panel y el portal
             * de clientes tienen sesiones distintas, así que este es el único
             * puente por el que adm sabe a qué fila de cw_portal_login_events
             * corresponde. Sin él no puede reconocer su propia sesión ni
             * detectar que se la han cerrado desde Seguridad → Accesos.
             */
            'login_event_id' => $staffLoginEventId,
        ]);
    }

    if ($tipo_usuario === 5) {
        $redirect = 'https://adm.conlineweb.com/leads';
    } elseif ($tipo_usuario === 3) {
        $redirect = $agente_id
            ? 'https://adm.conlineweb.com/solicitudes/tickets_desarrollador.php?id=' . $agente_id
            : 'https://adm.conlineweb.com/solicitudes/';
    } elseif ($tipo_usuario === 4) {
        $redirect = 'https://adm.conlineweb.com/tickets_externa/';
    } elseif ($tipo_usuario === 1) {
        $redirect = 'https://adm.conlineweb.com/';
    } elseif ($tipo_usuario === 2) {
        $redirect = 'https://adm.conlineweb.com/solicitudes/';
    } else {
        $redirect = 'index.php';
    }
} else {
    if ($tipo_usuario >= 1 && $tipo_usuario <= 5 && function_exists('cliente_local_create_token')) {
        $localToken = cliente_local_create_token($uid, $tipo_usuario, $agente_id);
        $redirect = cliente_local_login_redirect($tipo_usuario, $agente_id, $uid, $localToken);
    } else {
        $redirect = 'index.php';
    }
}

$data = [
    'status' => 'ok',
    'tipo' => $tipo_usuario,
    'uid' => $uid,
    'agente_id' => $agente_id,
    'redirect' => $redirect,
];
if ($localToken !== '') {
    $data['local_token'] = $localToken;
}

if (!$isProduction) {
    $data['mail_alert_ok'] = (bool) $mailSent;
    $data['mail_alert_loaded'] = (bool) $hasAlert;
}

$scheduleAfter([
    'kind' => 'success',
    'uid' => $uid,
    'usuario' => $usuarioOk,
    'tipo' => $tipo_usuario,
    'nombre' => $nombre,
    'ip' => $ip,
    'ua' => $ua,
    'env' => $envLabel,
    'agente_id' => $agente_id,
    'session_id' => (string) session_id(),
    'conn' => $conn,
    'sso_staff' => ($isProduction && $tipo_usuario >= 1 && $tipo_usuario <= 5),
    /* Si el acceso ya se registró en caliente, aquí solo se completa la geo. */
    'login_event_id' => $staffLoginEventId,
    'mail_sent' => $mailSent,
]);

unset($_SESSION['cw_login_captcha_ok'], $_SESSION['cw_login_captcha_ok_at'], $_SESSION['cw_login_captcha_user']);

echo json_encode($data, JSON_UNESCAPED_UNICODE);
if (function_exists('fastcgi_finish_request')) {
    @fastcgi_finish_request();
}
