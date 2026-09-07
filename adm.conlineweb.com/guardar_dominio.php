<?php
/**
 * Guardar / actualizar dominio (JSON puro).
 */
if (!defined('CW_JSON_API')) {
    define('CW_JSON_API', true);
}
if (!defined('CW_CONN_SOFT')) {
    define('CW_CONN_SOFT', true);
}
if (!defined('CW_BRAIN_WIDGET_DISABLE')) {
    define('CW_BRAIN_WIDGET_DISABLE', true);
}

require_once __DIR__ . '/auth_middleware.php';

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error.log');

if (!function_exists('cw_dominio_json')) {
    function cw_dominio_json(array $data): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('X-Content-Type-Options: nosniff');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('cw_dominio_log')) {
    function cw_dominio_log(string $message, $data = null): void
    {
        $line = date('Y-m-d H:i:s') . ' - ' . $message;
        if ($data !== null) {
            $line .= ' - ' . print_r($data, true);
        }
        @error_log($line . "\n", 3, __DIR__ . '/dominio_debug.log');
    }
}

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if (!$error || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    cw_dominio_log('Fatal', $error);
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor: ' . ($error['message'] ?? 'fatal'),
    ], JSON_UNESCAPED_UNICODE);
});

ob_start();
$modo_edicion = false;

try {
    include __DIR__ . '/conn.php';
    require_once __DIR__ . '/includes/helpers_clientes.php';

    $sistema = (isset($_POST['sistema']) && $_POST['sistema'] === 'hostingpro') ? 'hostingpro' : 'conlineweb';

    // Solo conectar HostingPro si el formulario lo pide (evita die/timeouts)
    if ($sistema === 'hostingpro') {
        include __DIR__ . '/conn_hostingpro.php';
        $db = $conn_hp ?? null;
    } else {
        $db = $conn ?? null;
    }

    if (!($db instanceof mysqli)) {
        throw new Exception(
            $sistema === 'hostingpro'
                ? 'Sin conexión a HostingPro.'
                : 'Sin conexión a la base de datos.'
        );
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new Exception('Método no permitido.');
    }

    cw_dominio_log('POST', $_POST);

    $modo_edicion = isset($_POST['modo_edicion']) && (string) $_POST['modo_edicion'] === '1';
    $id_dominio = $modo_edicion ? (int) ($_POST['id_dominio'] ?? 0) : 0;
    if ($modo_edicion && $id_dominio <= 0) {
        throw new Exception('ID de dominio inválido.');
    }

    if ($modo_edicion) {
        if (empty($_POST['cliente']) && empty($_POST['cliente_id'])) {
            throw new Exception('Se requiere un cliente válido.');
        }
    } else {
        foreach (['cliente', 'proveedor', 'url_dominio', 'costo_dominio', 'id_forma_pago', 'fecha_contratacion'] as $field) {
            if (!isset($_POST[$field]) || trim((string) $_POST[$field]) === '') {
                throw new Exception('Falta el campo obligatorio: ' . $field);
            }
        }
    }

    if (!isset($_POST['registrado']) || $_POST['registrado'] === '') {
        throw new Exception('Selecciona la gestión del dominio (ConlineWeb / externo).');
    }
    if (!isset($_POST['estado_dominio']) || $_POST['estado_dominio'] === '') {
        throw new Exception('Selecciona el estado del dominio (Activo / Inactivo).');
    }

    if ($modo_edicion) {
        if (isset($_POST['cliente']) && trim((string) $_POST['cliente']) !== '') {
            $cliente_id = filter_var($_POST['cliente'], FILTER_VALIDATE_INT);
        } else {
            $cliente_id = filter_var($_POST['cliente_id'] ?? null, FILTER_VALIDATE_INT);
        }
    } else {
        $cliente_id = filter_var($_POST['cliente'], FILTER_VALIDATE_INT);
    }
    if ($cliente_id === false || (int) $cliente_id <= 0) {
        throw new Exception('ID de cliente inválido.');
    }
    $cliente_id = (int) $cliente_id;

    $clienteOriginal = $modo_edicion ? (int) ($_POST['cliente_id'] ?? 0) : 0;
    $cambiandoCliente = !$modo_edicion || ($clienteOriginal > 0 && $cliente_id !== $clienteOriginal);
    if ($cambiandoCliente && function_exists('solicitudes_cliente_es_activo') && !solicitudes_cliente_es_activo($db, $cliente_id)) {
        throw new Exception('El cliente seleccionado no está activo o no es válido.');
    }

    $costo_dominio = filter_var($_POST['costo_dominio'] ?? 0, FILTER_VALIDATE_FLOAT);
    if ($costo_dominio === false) {
        throw new Exception('Costo de dominio inválido.');
    }

    $id_forma_pago = filter_var($_POST['id_forma_pago'], FILTER_VALIDATE_INT);
    if ($id_forma_pago === false) {
        throw new Exception('Moneda / forma de pago inválida.');
    }

    $registrado = filter_var($_POST['registrado'], FILTER_VALIDATE_INT);
    if ($registrado === false || !in_array($registrado, [0, 1], true)) {
        throw new Exception('Gestión inválida.');
    }

    $estado_dominio = filter_var($_POST['estado_dominio'], FILTER_VALIDATE_INT);
    if ($estado_dominio === false || !in_array($estado_dominio, [0, 1], true)) {
        throw new Exception('Estado inválido.');
    }

    $proveedor = trim((string) ($_POST['proveedor'] ?? ''));
    $url_dominio = trim((string) ($_POST['url_dominio'] ?? ''));
    $url_dominio = preg_replace('#^https?://#i', '', $url_dominio);
    $url_dominio = rtrim((string) $url_dominio, '/');
    if ($url_dominio === '') {
        throw new Exception('El dominio es obligatorio.');
    }

    $url_admin = trim((string) ($_POST['url_admin'] ?? ''));
    if ($url_admin === '' && !empty($_POST['url_admin_clean'])) {
        $clean = preg_replace('#^https?://#i', '', trim((string) $_POST['url_admin_clean']));
        $clean = preg_replace('#/wp-login\.php$#i', '', (string) $clean);
        $url_admin = 'https://' . rtrim((string) $clean, '/') . '/wp-login.php';
    }
    if ($url_admin === '') {
        // En edición, no bloquear si falta; en alta sí
        if (!$modo_edicion) {
            throw new Exception('La URL de administrador es obligatoria.');
        }
        $url_admin = 'https://' . $url_dominio . '/wp-login.php';
    }

    $fecha_contratacion = (string) ($_POST['fecha_contratacion'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_contratacion)) {
        throw new Exception('Fecha de contratación inválida.');
    }

    if (!empty($_POST['fecha_pago']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_POST['fecha_pago'])) {
        $fecha_pago = (string) $_POST['fecha_pago'];
    } else {
        $dt = new DateTime($fecha_contratacion);
        $dt->modify('+1 year');
        $fecha_pago = $dt->format('Y-m-d');
    }

    $usuario_admin = trim((string) ($_POST['usuario_admin'] ?? ''));
    $contrasena_admin = (string) ($_POST['contrasena_admin'] ?? '');
    $url_cpanel = trim((string) ($_POST['url_cpanel'] ?? ''));
    if ($url_cpanel === '' && !empty($_POST['url_cpanel_clean'])) {
        $cleanCp = preg_replace('#^https?://#i', '', trim((string) $_POST['url_cpanel_clean']));
        $cleanCp = preg_replace('#^cpanel\.#i', '', (string) $cleanCp);
        $cleanCp = preg_replace('#:2083/?$#', '', (string) $cleanCp);
        $url_cpanel = 'https://cpanel.' . rtrim((string) $cleanCp, '/') . ':2083/';
    }
    if ($url_cpanel === '') {
        $url_cpanel = 'https://cpanel.' . $url_dominio . ':2083/';
    }

    $ns1 = trim((string) ($_POST['ns1'] ?? ''));
    $ns2 = trim((string) ($_POST['ns2'] ?? ''));
    $ns3 = trim((string) ($_POST['ns3'] ?? ''));
    $ns4 = trim((string) ($_POST['ns4'] ?? ''));
    $ns5 = trim((string) ($_POST['ns5'] ?? ''));
    $ns6 = trim((string) ($_POST['ns6'] ?? ''));

    if ($modo_edicion) {
        $sql = 'UPDATE dominios SET
            cliente_id=?, proveedor=?, url_dominio=?, url_admin=?, usuario=?,
            contrasena=?, contrasena_normal=?, url_cpanel=?,
            ns1=?, ns2=?, ns3=?, ns4=?, ns5=?, ns6=?,
            costo_dominio=?, id_forma_pago=?, fecha_contratacion=?, fecha_pago=?,
            estado_dominio=?, registrado=?
            WHERE id_dominio=?';
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new Exception('No se pudo preparar UPDATE: ' . $db->error);
        }
        if (!$stmt->bind_param(
            'isssssssssssssdissiii',
            $cliente_id,
            $proveedor,
            $url_dominio,
            $url_admin,
            $usuario_admin,
            $contrasena_admin,
            $contrasena_admin,
            $url_cpanel,
            $ns1,
            $ns2,
            $ns3,
            $ns4,
            $ns5,
            $ns6,
            $costo_dominio,
            $id_forma_pago,
            $fecha_contratacion,
            $fecha_pago,
            $estado_dominio,
            $registrado,
            $id_dominio
        )) {
            throw new Exception('bind UPDATE: ' . $stmt->error);
        }
        $action = 'actualizado';
    } else {
        $sql = 'INSERT INTO dominios (
            cliente_id, proveedor, url_dominio, url_admin, usuario,
            contrasena, contrasena_normal, url_cpanel,
            ns1, ns2, ns3, ns4, ns5, ns6,
            costo_dominio, id_forma_pago, fecha_contratacion, fecha_pago,
            estado_dominio, registrado
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new Exception('No se pudo preparar INSERT: ' . $db->error);
        }
        if (!$stmt->bind_param(
            'isssssssssssssdissii',
            $cliente_id,
            $proveedor,
            $url_dominio,
            $url_admin,
            $usuario_admin,
            $contrasena_admin,
            $contrasena_admin,
            $url_cpanel,
            $ns1,
            $ns2,
            $ns3,
            $ns4,
            $ns5,
            $ns6,
            $costo_dominio,
            $id_forma_pago,
            $fecha_contratacion,
            $fecha_pago,
            $estado_dominio,
            $registrado
        )) {
            throw new Exception('bind INSERT: ' . $stmt->error);
        }
        $action = 'registrado';
    }

    if (!$stmt->execute()) {
        throw new Exception('Error SQL: ' . $stmt->error);
    }

    $domain_id = $modo_edicion ? $id_dominio : (int) $db->insert_id;
    $stmt->close();
    cw_dominio_log('OK ' . $action, ['id' => $domain_id]);

    cw_dominio_json([
        'success' => true,
        'message' => 'El dominio se ha ' . $action . ' correctamente.',
        'action' => $action,
        'domain_id' => $domain_id,
        'redirect' => $modo_edicion
            ? ('detalle_cliente.php?id=' . $cliente_id . '&sistema=' . $sistema)
            : ('formulario_dominio.php?sistema=' . $sistema),
    ]);
} catch (Throwable $e) {
    cw_dominio_log('ERR: ' . $e->getMessage());
    cw_dominio_json([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => $modo_edicion ? 'DOMAIN_UPDATE_ERROR' : 'DOMAIN_REGISTRATION_ERROR',
    ]);
}
