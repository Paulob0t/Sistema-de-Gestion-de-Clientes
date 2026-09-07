<?php
$__cw_host = strtolower((string) preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
$__cw_is_production = $__cw_host !== '' && preg_match('/(^|\.)conlineweb\.com$/', $__cw_host);

require_once __DIR__ . '/includes/cliente_session.php';
cliente_start_session();

$tipo = (int) ($_SESSION['tipo'] ?? -1);
$eventId = (int) ($_SESSION['cw_login_event_id'] ?? 0);

if ($eventId > 0) {
    try {
        include __DIR__ . '/conn.php';
        if (isset($conn) && $conn instanceof mysqli) {
            require_once dirname(__DIR__) . '/includes/cw_portal_login_log.php';
            cw_portal_login_log_end_event($conn, $eventId);
        }
    } catch (Throwable $e) {
        error_log('Login end event skipped: ' . $e->getMessage());
    }
}

$_SESSION = [];
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// Solo limpia la cookie del portal (CW_CLIENTE_SESS). No toca PHPSESSID de adm.
cliente_expire_host_cookie(cliente_session_name());

// Si cerró un usuario staff desde el portal, sí limpia el SSO hacia adm.
// Clientes (tipo 0) no deben borrar user_session_data de un admin activo.
if ($__cw_is_production && $tipo >= 1 && $tipo <= 5) {
    cliente_clear_staff_sso_cookie();
}

header('Location: ingreso.php');
exit;
