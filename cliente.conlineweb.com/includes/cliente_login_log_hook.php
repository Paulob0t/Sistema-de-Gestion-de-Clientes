<?php
/**
 * En cada petición del portal cliente: actualiza última actividad y expulsa
 * si un administrador cerró la sesión desde Seguridad → Accesos.
 */
declare(strict_types=1);

if (!function_exists('cliente_login_log_on_request')) {
    function cliente_login_log_on_request(?mysqli $conn = null): void
    {
        if (!function_exists('cliente_is_logged_in') || !cliente_is_logged_in()) {
            return;
        }

        $eventId = (int) ($_SESSION['cw_login_event_id'] ?? 0);
        if ($eventId <= 0) {
            return;
        }

        if (!($conn instanceof mysqli)) {
            $connFile = dirname(__DIR__) . '/conn.php';
            if (!is_file($connFile)) {
                return;
            }
            try {
                if (!defined('CW_CONN_SOFT')) {
                    define('CW_CONN_SOFT', true);
                }
                include $connFile;
            } catch (Throwable $e) {
                return;
            }
        }

        if (!($conn instanceof mysqli)) {
            return;
        }

        foreach ([
            __DIR__ . '/cw_portal_login_log.php',
            dirname(__DIR__, 2) . '/includes/cw_portal_login_log.php',
            '/home/conlineweb/includes/cw_portal_login_log.php',
        ] as $logFile) {
            if (is_file($logFile)) {
                require_once $logFile;
                break;
            }
        }

        if (!function_exists('cw_portal_login_log_is_revoked')) {
            return;
        }

        try {
            if (cw_portal_login_log_is_revoked($conn, $eventId)) {
                cliente_login_log_force_logout('Un administrador cerró esta sesión.');
            }

            if (function_exists('cw_portal_login_log_touch')) {
                cw_portal_login_log_touch($conn, $eventId);
            }
        } catch (Throwable $e) {
            error_log('cliente_login_log_on_request: ' . $e->getMessage());
        }
    }
}

if (!function_exists('cliente_login_log_force_logout')) {
    function cliente_login_log_force_logout(string $message = ''): void
    {
        $tipo = (int) ($_SESSION['tipo'] ?? -1);
        $isProduction = function_exists('cliente_is_production_host')
            && cliente_is_production_host();

        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        if (function_exists('cliente_expire_host_cookie')) {
            cliente_expire_host_cookie(cliente_session_name());
        }

        if ($isProduction && $tipo >= 1 && $tipo <= 5 && function_exists('cliente_clear_staff_sso_cookie')) {
            cliente_clear_staff_sso_cookie();
        }

        $qs = $message !== '' ? '?error=' . rawurlencode($message) : '';
        header('Location: ingreso.php' . $qs);
        exit;
    }
}
