<?php
/**
 * Middleware de Autenticación para el Panel de Administración
 * Ubicación: adm.conlineweb.com/auth_middleware.php
 * 
 * Debe incluirse en TODAS las páginas del panel admin con:
 * require_once __DIR__.'/auth_middleware.php';
 */

@ini_set('display_errors', '0');

// Polyfills PHP 7.4 (cPanel) — antes de cualquier include compartido
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle)
    {
        $haystack = (string) $haystack;
        $needle = (string) $needle;
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle)
    {
        $haystack = (string) $haystack;
        $needle = (string) $needle;
        if ($needle === '') {
            return true;
        }
        $len = strlen($needle);
        return $len <= strlen($haystack) && substr($haystack, -$len) === $needle;
    }
}
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle)
    {
        $haystack = (string) $haystack;
        $needle = (string) $needle;
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

require_once __DIR__ . '/includes/adm_session.php';
require_once __DIR__ . '/includes/adm_head_meta.php';

// Seguridad compartida: varias rutas (no tumbar el panel si falta el include padre)
$__cwPortalSecurityLoaded = false;
$__cwPortalSecurityCandidates = [
    __DIR__ . '/includes/cw_portal_security.php',
    dirname(__DIR__) . '/includes/cw_portal_security.php',
    '/home/conlineweb/includes/cw_portal_security.php',
];
foreach ($__cwPortalSecurityCandidates as $__cwSecPath) {
    if (is_file($__cwSecPath)) {
        try {
            require_once $__cwSecPath;
            $__cwPortalSecurityLoaded = function_exists('cw_portal_send_security_headers');
            if ($__cwPortalSecurityLoaded) {
                break;
            }
        } catch (Throwable $e) {
            error_log('auth_middleware: fallo cargando seguridad: ' . $e->getMessage());
        }
    }
}
unset($__cwSecPath, $__cwPortalSecurityCandidates);

if (!adm_is_production_host()) {
    $__admLocalAuth = __DIR__ . '/includes/adm_local_auth.php';
    if (is_file($__admLocalAuth)) {
        require_once $__admLocalAuth;
    }
    unset($__admLocalAuth);
}

// Panel privado: nunca indexar + cabeceras de seguridad
adm_send_noindex_headers();
$__cwAllowFrame = defined('CW_ALLOW_SAMEORIGIN_FRAME') && CW_ALLOW_SAMEORIGIN_FRAME;
if (function_exists('cw_portal_send_security_headers')) {
    cw_portal_send_security_headers(!$__cwAllowFrame);
} else {
    if (!headers_sent()) {
        header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet', true);
        header('X-Content-Type-Options: nosniff', true);
        header('X-Frame-Options: ' . ($__cwAllowFrame ? 'SAMEORIGIN' : 'DENY'), true);
    }
}
unset($__cwAllowFrame);
// =============================================
// CONFIGURACIÓN INICIAL DE SESIÓN COMPARTIDA
// =============================================
$__is_https = adm_is_https();
adm_start_session();

// =============================================
// CONSTANTES Y CONFIGURACIONES
// =============================================
$__scheme = $__is_https ? 'https' : 'http';
$__host = $_SERVER['HTTP_HOST'] ?? 'adm.conlineweb.com';

if (adm_is_production_host()) {
    define('LOGIN_URL', $__scheme . '://cliente.conlineweb.com/ingreso.php');
    define('ADMIN_HOME', $__scheme . '://' . $__host . '/');
    define('CLIENT_HOME', $__scheme . '://cliente.conlineweb.com/index.php');
} elseif (adm_is_local_environment()) {
    define('LOGIN_URL', adm_local_cliente_url('ingreso.php'));
    define('ADMIN_HOME', $__scheme . '://' . $__host . '/');
    define('CLIENT_HOME', adm_local_cliente_url('index.php'));
} else {
    define('LOGIN_URL', $__scheme . '://cliente.conlineweb.com/ingreso.php');
    define('ADMIN_HOME', $__scheme . '://' . $__host . '/');
    define('CLIENT_HOME', $__scheme . '://cliente.conlineweb.com/index.php');
}

define('SESSION_TIMEOUT', 86400); // 24 horas en segundos

// =============================================
// FUNCIONES PRINCIPALES DE AUTENTICACIÓN
// =============================================

/**
 * Verifica si hay una sesión de administrador válida
 */
function isAdminSessionValid() {
    $uid = (int) ($_SESSION['uid'] ?? 0);
    if ($uid <= 0 || !adm_login_value_is_true($_SESSION['login'] ?? null)) {
        return false;
    }

    // Normalizar tipos para evitar fallos por string/int del login central
    $_SESSION['uid'] = $uid;
    $_SESSION['login'] = true;

    // (Desactivado) No forzar cierre por inactividad dentro de las 24h. Solo expira por lifetime natural o logout.
    // if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) { return false; }
    // Validar tipo de usuario (admins=1, solicitudes=2, agentes=3, empresas=4)
    $tipo = (int) ($_SESSION['tipo'] ?? 0);
    if ($tipo < 1 || $tipo > 5) {
        return false;
    }
    $_SESSION['tipo'] = $tipo;

    return true;
}

/**
 * Intenta cargar sesión desde cookies compartidas
 */
function loadSessionFromCookies() {
    if (!adm_is_production_host()) {
        return false;
    }

    if (empty($_COOKIE['user_session_data'])) {
        return false;
    }

    $rawCookie = (string) $_COOKIE['user_session_data'];
    $sessionData = function_exists('cw_portal_sso_decode')
        ? cw_portal_sso_decode($rawCookie, true)
        : json_decode($rawCookie, true);
    if (!is_array($sessionData)) {
        // Compat: cookie escrita con urlencode manual en versiones anteriores
        $sessionData = json_decode(urldecode($rawCookie), true);
    }

    if (!is_array($sessionData)) {
        error_log('Cookie user_session_data inválida (firma/JSON)');
        adm_clear_shared_cookie('user_session_data');
        return false;
    }

    $uid = (int) ($sessionData['uid'] ?? 0);
    $loginOk = adm_login_value_is_true($sessionData['login'] ?? null);
    $tipo = (int) ($sessionData['tipo'] ?? 0);
    $now = time();
    $timestamp = (int) ($sessionData['timestamp'] ?? 0);

    if ($uid <= 0 || !$loginOk || $tipo < 1 || $tipo > 5) {
        error_log('Cookie user_session_data con uid/tipo/login inválidos');
        adm_clear_shared_cookie('user_session_data');
        return false;
    }

    if ($timestamp <= 0 || $timestamp > $now || ($now - $timestamp) > SESSION_TIMEOUT) {
        error_log('Cookie user_session_data expirada o timestamp inválido: ' . $timestamp);
        adm_clear_shared_cookie('user_session_data');
        return false;
    }

    $_SESSION['uid'] = $uid;
    $_SESSION['login'] = true;
    $_SESSION['tipo'] = $tipo;
    $_SESSION['id'] = session_id();
    $_SESSION['last_activity'] = $now;
    $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'] ?? null;

    if (!empty($sessionData['agente_id'])) {
        $_SESSION['agente_id'] = (int) $sessionData['agente_id'];
    } else {
        unset($_SESSION['agente_id']);
    }

    $loginEventId = (int) ($sessionData['login_event_id'] ?? 0);
    if ($loginEventId > 0) {
        $_SESSION['cw_login_event_id'] = $loginEventId;
    }

    $usuarioCookie = trim((string) ($sessionData['usuario'] ?? ''));
    if ($usuarioCookie !== '') {
        $_SESSION['usuario'] = $usuarioCookie;
    }

    // Si el correo no salió en iniciarSesion (o entró solo con cookie SSO), avisar al entrar al ADM
    $alertSent = !empty($sessionData['alert_sent']);
    if (!$alertSent) {
        $alertSent = adm_try_send_sso_login_alert([
            'uid' => $uid,
            'tipo' => $tipo,
            'usuario' => $usuarioCookie !== '' ? $usuarioCookie : ('UID #' . $uid),
            'agente_id' => !empty($_SESSION['agente_id']) ? (int) $_SESSION['agente_id'] : null,
        ]);
    }

    // Re-firmar cookie (legacy o para marcar alert_sent)
    if (function_exists('cw_portal_sso_encode')) {
        $needsRefresh = !str_starts_with($rawCookie, 'v1.') || empty($sessionData['alert_sent']);
        if ($needsRefresh) {
            $refresh = [
                'uid' => $uid,
                'tipo' => $tipo,
                'login' => true,
                'timestamp' => $timestamp > 0 ? $timestamp : $now,
                'alert_sent' => $alertSent ? 1 : 0,
            ];
            if ($usuarioCookie !== '') {
                $refresh['usuario'] = $usuarioCookie;
            }
            if (!empty($_SESSION['agente_id'])) {
                $refresh['agente_id'] = (int) $_SESSION['agente_id'];
            }
            if ($loginEventId > 0) {
                $refresh['login_event_id'] = $loginEventId;
            }
            adm_set_shared_cookie('user_session_data', cw_portal_sso_encode($refresh), $now + SESSION_TIMEOUT);
        }
    }

    error_log('Sesión restaurada exitosamente para usuario: ' . $uid . ', tipo: ' . $tipo);
    return true;
}

/**
 * Alerta de correo al hidratar sesión ADM desde cookie SSO (fallback si falló en login).
 *
 * @param array{uid:int,tipo:int,usuario?:string,agente_id?:int|null} $ctx
 */
function adm_try_send_sso_login_alert(array $ctx): bool
{
    static $done = false;
    if ($done) {
        return false;
    }
    $done = true;

    $candidates = [
        __DIR__ . '/includes/cw_portal_login_alert.php',
        dirname(__DIR__) . '/includes/cw_portal_login_alert.php',
        '/home/conlineweb/adm.conlineweb.com/includes/cw_portal_login_alert.php',
        '/home/conlineweb/cliente.conlineweb.com/includes/cw_portal_login_alert.php',
        '/home/conlineweb/includes/cw_portal_login_alert.php',
    ];
    foreach ($candidates as $path) {
        if (is_file($path)) {
            try {
                require_once $path;
            } catch (Throwable $e) {
                error_log('adm_try_send_sso_login_alert load: ' . $e->getMessage());
            }
            break;
        }
    }

    if (!function_exists('cw_portal_notify_login')) {
        error_log('adm_try_send_sso_login_alert: notify no disponible');

        return false;
    }

    try {
        if (function_exists('cw_portal_login_alert_queue_flush')) {
            cw_portal_login_alert_queue_flush(2);
        }
        $ip = function_exists('cw_portal_client_ip')
            ? cw_portal_client_ip()
            : (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        return (bool) cw_portal_notify_login([
            'uid' => (int) ($ctx['uid'] ?? 0),
            'usuario' => (string) ($ctx['usuario'] ?? ''),
            'tipo' => (int) ($ctx['tipo'] ?? 1),
            'ip' => $ip,
            'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'agente_id' => $ctx['agente_id'] ?? null,
            'environment' => 'producción',
            'skip_geo' => true,
        ]);
    } catch (Throwable $e) {
        error_log('adm_try_send_sso_login_alert: ' . $e->getMessage());

        return false;
    }
}

/**
 * Actualiza las cookies de sesión compartidas
 */
function updateSharedCookies() {
    if (!adm_is_production_host()) {
        return;
    }

    if (!empty($_SESSION['uid'])) {
        $expires = time() + SESSION_TIMEOUT;
        
        adm_set_shared_cookie('shared_session', session_id(), $expires);
        
        $cookieData = [
            'uid' => (int) $_SESSION['uid'],
            'tipo' => (int) ($_SESSION['tipo'] ?? 1),
            'login' => true,
            'timestamp' => time(),
            // Ya hay sesión ADM activa: no volver a alertar en cada request
            'alert_sent' => 1,
        ];
        if (!empty($_SESSION['usuario'])) {
            $cookieData['usuario'] = (string) $_SESSION['usuario'];
        }
        if (!empty($_SESSION['agente_id'])) {
            $cookieData['agente_id'] = (int) $_SESSION['agente_id'];
        }
        if (!empty($_SESSION['cw_login_event_id'])) {
            $cookieData['login_event_id'] = (int) $_SESSION['cw_login_event_id'];
        }

        $encoded = function_exists('cw_portal_sso_encode')
            ? cw_portal_sso_encode($cookieData)
            : (string) json_encode($cookieData, JSON_UNESCAPED_UNICODE);
        adm_set_shared_cookie('user_session_data', $encoded, $expires);
    }
}

/**
 * Destruye completamente la sesión
 */
function destroySession() {
    // Log para debugging
    error_log("Destruyendo sesión para usuario: " . ($_SESSION['uid'] ?? 'desconocido'));

    $eventId = (int) ($_SESSION['cw_login_event_id'] ?? 0);
    if ($eventId > 0) {
        try {
            if (!defined('CW_CONN_SOFT')) {
                define('CW_CONN_SOFT', true);
            }
            $connFile = __DIR__ . '/conn.php';
            if (is_file($connFile)) {
                include $connFile;
            }
            if (isset($conn) && $conn instanceof mysqli) {
                foreach ([
                    __DIR__ . '/includes/cw_portal_login_log.php',
                    dirname(__DIR__) . '/includes/cw_portal_login_log.php',
                    '/home/conlineweb/includes/cw_portal_login_log.php',
                ] as $logFile) {
                    if (is_file($logFile)) {
                        require_once $logFile;
                        break;
                    }
                }
                if (function_exists('cw_portal_login_log_end_event')) {
                    cw_portal_login_log_end_event($conn, $eventId);
                }
            }
        } catch (Throwable $e) {
            error_log('Login end event skipped: ' . $e->getMessage());
        }
    }
    
    // Limpiar variables de sesión
    $_SESSION = [];
    
    // Eliminar cookies de sesión estándar
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), 
            '', 
            time() - 42000,
            $params["path"], 
            $params["domain"],
            $params["secure"], 
            $params["httponly"]
        );
    }
    
    // Eliminar cookies personalizadas
    adm_clear_shared_cookie('user_session_data');
    adm_clear_shared_cookie('shared_session');
    adm_clear_shared_cookie('user_session_data'); // dominio host actual por compatibilidad
    setcookie('user_session_data', '', time() - 3600, '/', $_SERVER['HTTP_HOST'] ?? '', adm_is_https(), true);
    setcookie('shared_session', '', time() - 3600, '/', $_SERVER['HTTP_HOST'] ?? '', adm_is_https(), true);
    
    // Destruir sesión
    session_destroy();
    
    error_log("Sesión destruida completamente");
}

/**
 * Redirige al login con mensaje opcional
 */
function redirectToLogin($message = '') {
    $url = LOGIN_URL;
    $params = [];
    
    if (!empty($message)) {
        $params['error'] = $message;
    }
    
    // URL de retorno limpia (sin token local de un solo uso)
    $scheme = adm_is_https() ? 'https' : 'http';
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $parts = parse_url($uri);
    $path = is_array($parts) ? ($parts['path'] ?? '/') : '/';
    $query = [];
    if (is_array($parts) && !empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }
    unset($query['cw_local_token']);
    $current_url = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $path;
    if ($query !== []) {
        $current_url .= '?' . http_build_query($query);
    }
    $params['return_url'] = $current_url;
    
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    error_log("Redirigiendo al login: " . $url . " desde: " . $current_url);
    
    header("Location: $url");
    exit();
}

/**
 * ¿Inyectar cerebro flotante en esta respuesta HTML?
 */
function cw_adm_brain_should_inject(): bool
{
    if (defined('CW_BRAIN_WIDGET_DISABLE') && CW_BRAIN_WIDGET_DISABLE) {
        return false;
    }
    if (PHP_SAPI === 'cli') {
        return false;
    }
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $uri = str_replace('\\', '/', (string) ($_SERVER['REQUEST_URI'] ?? ''));
    $hay = $script . ' ' . $uri;
    if (preg_match('#/(api/|cron_|ajax_)#i', $hay)) {
        return false;
    }
    // Preview de correos (iframe): no inyectar FAB del cerebro
    if (preg_match('#/emails/render\.php#i', $hay)) {
        return false;
    }
    // Módulo de tickets/solicitudes: no molestar con el chat flotante
    if (preg_match('#/solicitudes(/|$)#i', $hay)) {
        return false;
    }
    if (preg_match('#(cerrarSesion|generar_txt|actualizar\.php|eliminar\.php|eliminar_masivo|api_|guardar_dominio|guardar_hosting|guardar_pago|guardar_cliente|guardar_proyecto)#i', $hay)) {
        return false;
    }
    foreach (headers_list() as $h) {
        if (stripos($h, 'Content-Type:') !== 0) {
            continue;
        }
        if (stripos($h, 'text/html') !== false) {
            return true;
        }
        if (stripos($h, 'application/json') !== false
            || stripos($h, 'text/plain') !== false
            || stripos($h, 'application/octet-stream') !== false
            || stripos($h, 'application/pdf') !== false
            || stripos($h, 'text/csv') !== false
        ) {
            return false;
        }
    }
    return true;
}

/**
 * Registra el cerebro al final de la página (sin ob_start: evita pantallas en blanco).
 */
function cw_adm_brain_register_shutdown(): void
{
    if (!empty($GLOBALS['cw_brain_shutdown_registered'])) {
        return;
    }
    $GLOBALS['cw_brain_shutdown_registered'] = true;
    register_shutdown_function(static function (): void {
        if (!empty($GLOBALS['cw_brain_widget_rendered'])) {
            return;
        }
        if (!cw_adm_brain_should_inject()) {
            return;
        }
        foreach (headers_list() as $h) {
            if (stripos($h, 'Content-Type:') !== 0) {
                continue;
            }
            if (stripos($h, 'application/json') !== false
                || stripos($h, 'text/plain') !== false
                || stripos($h, 'application/octet-stream') !== false
                || stripos($h, 'application/pdf') !== false
                || stripos($h, 'text/csv') !== false
            ) {
                return;
            }
        }
        $widgetFile = __DIR__ . '/includes/cw_brain_widget.php';
        if (!is_file($widgetFile)) {
            return;
        }
        try {
            require $widgetFile;
        } catch (Throwable $e) {
            error_log('cw_brain_widget: ' . $e->getMessage());
        }
    });
}

// =============================================
// VALIDACIÓN PRINCIPAL
// =============================================

// Solo en local (nunca en *.conlineweb.com) se omite el login
$admAuthBypass = false;
if (!adm_is_production_host() && file_exists(__DIR__ . '/includes/cw_hub_config.local.php')) {
    require_once __DIR__ . '/includes/cw_hub_config.local.php';
    $admAuthBypass = defined('ADM_DEV_LOCAL') && ADM_DEV_LOCAL;
}

if (!$admAuthBypass && !defined('AUTH_MIDDLEWARE_FUNCTIONS_ONLY')) {
    if (!adm_is_production_host() && adm_is_local_environment() && !empty($_GET['cw_local_token'])) {
        if (adm_local_consume_token((string) $_GET['cw_local_token'])) {
            header('Location: ' . adm_local_strip_token_from_request_uri());
            exit();
        }
        // Token inválido/expirado: limpiar URL y continuar (evitar bucle con return_url sucio)
        header('Location: ' . adm_local_strip_token_from_request_uri());
        exit();
    }

    // 1. Intentar validar sesión existente
    $sessionValid = isAdminSessionValid();

    // 2. En producción, intentar cargar desde cookies compartidas (.conlineweb.com)
    if (!$sessionValid) {
        $sessionValid = loadSessionFromCookies();
    }

    // 3. Si la sesión es válida, actualizar actividad
    if ($sessionValid) {
        // Mantener last_activity solo como referencia (no usado para cortar sesión ahora)
        if (empty($_SESSION['last_activity'])) {
            $_SESSION['last_activity'] = time();
        }
        updateSharedCookies(); // Refresca cookies para extender hasta 24h desde el primer login

        $loginEventId = (int) ($_SESSION['cw_login_event_id'] ?? 0);
        if ($loginEventId > 0) {
            try {
                if (!isset($conn) || !($conn instanceof mysqli)) {
                    if (!defined('CW_CONN_SOFT')) {
                        define('CW_CONN_SOFT', true);
                    }
                    include __DIR__ . '/conn.php';
                }
                if (isset($conn) && $conn instanceof mysqli) {
                    $logCandidates = [
                        __DIR__ . '/includes/cw_portal_login_log.php',
                        dirname(__DIR__) . '/includes/cw_portal_login_log.php',
                        '/home/conlineweb/includes/cw_portal_login_log.php',
                    ];
                    foreach ($logCandidates as $logFile) {
                        if (is_file($logFile)) {
                            require_once $logFile;
                            break;
                        }
                    }
                    /*
                     * Cierre de sesión ordenado desde Seguridad → Accesos.
                     *
                     * De la sesión solo se guarda un hash irreversible, así
                     * que no se puede borrar su fichero desde el panel. En su
                     * lugar queda marcada en la base de datos y es aquí, en la
                     * siguiente petición del dispositivo afectado, donde se
                     * hace efectiva. Va antes del "touch" para no revivir una
                     * sesión que ya está revocada.
                     */
                    if (function_exists('cw_portal_login_log_is_revoked')
                        && cw_portal_login_log_is_revoked($conn, $loginEventId)) {
                        destroySession();
                        redirectToLogin('Un administrador cerró esta sesión.');
                        exit;
                    }

                    if (function_exists('cw_portal_login_log_touch')) {
                        cw_portal_login_log_touch($conn, $loginEventId);
                    }
                }
            } catch (Throwable $e) {
                // no bloquear panel
            }
        }

        // =============================
        // VALIDACIÓN POR RUTA/PERMISO
        // =============================
        $currentPath = $_SERVER['SCRIPT_NAME'] ?? '';
        $userType = intval($_SESSION['tipo']);

        // Seguridad / accesos portal: solo admin (1) y solicitudes (2)
        if (preg_match('#/seguridad/#', $currentPath) && $userType !== 1 && $userType !== 2) {
            header('Location: /index.php?hub_error=acceso_denegado');
            exit();
        }
        
        // /solicitudes/index.php: admin (1) y módulo solicitudes (2)
        if (preg_match('#/solicitudes/index\.php$#', $currentPath)) {
            if ($userType !== 1 && $userType !== 2) {
                error_log("Acceso denegado a ".$currentPath." para usuario UID: ".$_SESSION['uid']." tipo: ".$_SESSION['tipo']);
                destroySession();
                redirectToLogin('Acceso denegado: no tiene permiso para el módulo de solicitudes.');
            }
        }
        
        // Restringir a empresas (tipo 4) solo a tickets_externa/ y archivos específicos
        if ($userType === 4) {
            $allowedPaths = [
                '/cerrarSesion.php',
                '/actualizar_contrasena.php'
            ];
            
            // Permitir acceso completo a tickets_externa/
            if (preg_match('#/tickets_externa/#', $currentPath)) {
                // Permitir todos los archivos en tickets_externa/
                $isAllowed = true;
            } else {
                $isAllowed = false;
                foreach ($allowedPaths as $allowedPath) {
                    if (preg_match('#'.preg_quote($allowedPath, '#').'$#', $currentPath)) {
                        $isAllowed = true;
                        break;
                    }
                }
            }
            
            if (!$isAllowed) {
                error_log("Empresa intentando acceder a ruta no permitida: ".$currentPath);
                header("Location: https://adm.conlineweb.com/tickets_externa/");
                exit();
            }
        }

        // Hub analítico + CRM + Web Site — permisos por rol
        if (preg_match('#/(analytics|leads|website)/#', $currentPath)) {
            if (!defined('AUTH_MIDDLEWARE_FUNCTIONS_ONLY')) {
                require_once __DIR__ . '/includes/cw_hub_permissions.php';
            }
            // Excepción: API del cerebro flotante (todas las vistas del panel)
            $isBrainApi = (bool) preg_match('#/analytics/api/seo_mexico_ai_prompt\.php$#', $currentPath);
            if (preg_match('#/analytics/#', $currentPath)
                && !$isBrainApi
                && !cw_hub_can('hub.analytics.view')
            ) {
                header('Location: /index.php?hub_error=analytics_denied');
                exit();
            }
            if (preg_match('#/website/#', $currentPath)
                && !cw_hub_can('hub.analytics.view')
                && !cw_hub_can('hub.crm.view')) {
                header('Location: /index.php?hub_error=website_denied');
                exit();
            }
            if (preg_match('#/leads/#', $currentPath) && !cw_hub_can('hub.crm.view')) {
                header('Location: /index.php?hub_error=crm_denied');
                exit();
            }
        }

        // Cerebro flotante: se registra al final de la respuesta (sin output buffering)
        cw_adm_brain_register_shutdown();

    }
    // 4. Si no es válida, redirigir al login (sin borrar cookies del login central)
    else {
        error_log("Intento de acceso no autorizado desde IP: ".$_SERVER['REMOTE_ADDR']);
        $_SESSION = [];
        redirectToLogin('Acceso no autorizado. Debe iniciar sesión con permisos válidos.');
    }
}

// Local bypass: también mostrar cerebro si hay sesión / entorno local activo
if ($admAuthBypass && !defined('AUTH_MIDDLEWARE_FUNCTIONS_ONLY')) {
    cw_adm_brain_register_shutdown();
}

// =============================================
// FUNCIONES ADICIONALES DISPONIBLES
// =============================================

/**
 * Obtiene información del usuario autenticado
 */
function getAuthenticatedUser() {
    return [
        'id' => $_SESSION['uid'] ?? null,
        'type' => $_SESSION['tipo'] ?? null,
        'ip' => $_SESSION['user_ip'] ?? null,
        'last_activity' => $_SESSION['last_activity'] ?? null
    ];
}

/**
 * Cierra la sesión y redirige al login
 */
function adminLogout() {
    destroySession();
    redirectToLogin('Sesión cerrada correctamente');
}

// =============================================
// PROTECCIÓN CONTRA ACCESO DIRECTO
// =============================================
if (basename($_SERVER['SCRIPT_FILENAME']) === 'auth_middleware.php') {
    header("HTTP/1.1 403 Forbidden");
    exit('Acceso directo no permitido');
}
