<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

// Detectar sistema: conlineweb, hostingpro o planpro
$sistema = 'conlineweb';
if (isset($_REQUEST['sistema'])) {
    if ($_REQUEST['sistema'] === 'hostingpro') {
        $sistema = 'hostingpro';
    } elseif ($_REQUEST['sistema'] === 'planpro') {
        $sistema = 'planpro';
    }
}


// Procesar el formulario cuando se envía (ANTES DE CUALQUIER INCLUDE O HTML)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Limpiar el buffer de salida para evitar espacios o warnings antes del JSON
    if (ob_get_length()) ob_end_clean();
    header('Content-Type: application/json'); // Forzar header SIEMPRE

    // Recoger datos del formulario
    $nombre = $_POST['nombre'] ?? '';
    $telefono = $_POST['telefono'] ?? '';
    $correo = $_POST['email'] ?? '';
    $empresa = $_POST['empresa'] ?? '';
    $rsocial = $_POST['rsocial'] ?? '';
    $rfc = $_POST['rfc'] ?? '';
    $especificacion = $_POST['especificacion'] ?? '';
    $calle = $_POST['calle'] ?? '';
    $next = $_POST['next'] ?? '';
    $nint = $_POST['nint'] ?? '';
    $colonia = $_POST['colonia'] ?? '';
    $cp = $_POST['cp'] ?? '';
    $pais = $_POST['pais'] ?? '';
    $estado = $_POST['estado'] ?? '';
    $ciudad = $_POST['municipio'] ?? '';
    $contrasena = $_POST['inputPassword'] ?? '';
    $contrasena_segura = md5($contrasena);
    $facturacion = $_POST['facturacion'] ?? '';

    // Manejo de Constancia de Situación Fiscal → cliente.conlineweb.com/constancias_fiscales/
    require_once __DIR__ . '/includes/cw_constancia_fiscal_adm.php';
    $nombre_archivo = '';
    $idUpload = (int) ($_GET["id"] ?? $_POST["id"] ?? 0);
    if (isset($_FILES['constancia_fiscal']) && ($_FILES['constancia_fiscal']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $uploadResult = cw_constancia_fiscal_adm_save_upload($_FILES['constancia_fiscal'], $idUpload);
        if (!$uploadResult['ok']) {
            echo json_encode(['success' => false, 'message' => $uploadResult['error']]);
            exit();
        }
        $nombre_archivo = $uploadResult['filename'];
    } else {
        // Si no se sube archivo, conservar el actual si existe
        include_once "conn.php";
        include_once "conn_hostingpro.php";
        $db = ($sistema === 'hostingpro' || $sistema === 'planpro') ? $conn_hp : $conn;
        $id = $_GET["id"] ?? $_POST["id"] ?? null;

        if ($id) {
            $sql = "SELECT constancia_situacion_fiscal FROM clientes WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $nombre_archivo = cw_constancia_fiscal_filename_from_stored($row['constancia_situacion_fiscal'] ?? '') ?? '';
            $stmt->close();
        }
    }

    // Actualizar datos del cliente
    include_once "conn.php";
    include_once "conn_hostingpro.php";
    $db = ($sistema === 'hostingpro' || $sistema === 'planpro') ? $conn_hp : $conn;
    $id = $_GET["id"] ?? $_POST["id"] ?? null;

    // Solo permitir actualizar clientes (login tipo 0)
    $esClienteTipo0 = false;
    if ($id !== null && $id !== '') {
        $stmtTipo = $db->prepare("SELECT l.id FROM login l INNER JOIN clientes c ON c.id = l.id WHERE l.id = ? AND l.id_tipo_usuario = 0 LIMIT 1");
        if ($stmtTipo) {
            $idInt = (int) $id;
            $stmtTipo->bind_param("i", $idInt);
            $stmtTipo->execute();
            $esClienteTipo0 = (bool) $stmtTipo->get_result()->fetch_assoc();
            $stmtTipo->close();
        }
    }
    if (!$esClienteTipo0) {
        echo json_encode(['success' => false, 'message' => 'Solo se pueden editar usuarios tipo cliente (0).']);
        exit();
    }

    $sql_update = "UPDATE clientes SET 
        nombre_contacto = ?,
        telefono = ?,
        correo = ?,
        empresa = ?,
        rsocial = ?,
        rfc = ?,
        especificacion = ?,
        calle = ?,
        next = ?,
        nint = ?,
        col = ?,
        cp = ?,
        pais = ?,
        estado = ?,
        ciudad = ?,
        constancia_situacion_fiscal = ?,
        facturacion = ?
        WHERE id = ?";
    $stmt_update = $db->prepare($sql_update);
    $stmt_update->bind_param(
        "ssssssssssssssssii",
        $nombre,
        $telefono,
        $correo,
        $empresa,
        $rsocial,
        $rfc,
        $especificacion,
        $calle,
        $next,
        $nint,
        $colonia,
        $cp,
        $pais,
        $estado,
        $ciudad,
        $nombre_archivo,
        $facturacion,
        $id
    );

    // --- CORRECCIÓN: Actualizar SIEMPRE el usuario/correo en login, y la contraseña solo si se proporciona ---
    $login_ok = true;
    if (!empty($contrasena)) {
        $sql_update_login = "UPDATE login SET 
            contrasena = ?,
            contrasena_normal = ?,
            usuario = ?
            WHERE id = ? AND id_tipo_usuario = 0";
        $stmt_update_login = $db->prepare($sql_update_login);
        $stmt_update_login->bind_param("sssi", $contrasena_segura, $contrasena, $correo, $id);
        $login_ok = $stmt_update_login->execute();
        $login_error = $stmt_update_login->error;
        $stmt_update_login->close();
    } else {
        $sql_update_login = "UPDATE login SET usuario = ? WHERE id = ? AND id_tipo_usuario = 0";
        $stmt_update_login = $db->prepare($sql_update_login);
        $stmt_update_login->bind_param("si", $correo, $id);
        $login_ok = $stmt_update_login->execute();
        $login_error = $stmt_update_login->error;
        $stmt_update_login->close();
    }

    // Si hubo error en login, mostrar error y salir
    if (!$login_ok) {
        $errorMsg = 'Error al actualizar usuario/correo o contraseña: ' . addslashes($login_error);
        echo json_encode(['success' => false, 'message' => $errorMsg]);
        exit();
    }

    // Ejecutar actualización de cliente
    if ($stmt_update->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Cliente actualizado correctamente',
            'redirect' => 'detalle_cliente.php?id=' . $id . '&sistema=' . $sistema
        ]);
        exit();
    } else {
        $errorMsg = 'Error al actualizar cliente: ' . addslashes($stmt_update->error);
        echo json_encode(['success' => false, 'message' => $errorMsg]);
        exit();
    }
}

include "menu.php";
require_once __DIR__ . '/includes/cw_constancia_fiscal_adm.php';
require_once __DIR__ . '/includes/adm_proyecto_preview_helpers.php';
require_once __DIR__ . '/includes/helpers_proyectos_tipo.php';
include "conn.php";
include "conn_hostingpro.php";
$db = ($sistema === 'hostingpro' || $sistema === 'planpro') ? $conn_hp : $conn;

// Obtener lista de estados
$sql_estados = "SELECT id_estado, estado FROM estados ORDER BY estado";
$result_estados = $db->query($sql_estados);

// Verificar si se recibió un ID válido
$cliente_transferido = false;
if (isset($_GET["id"]) && is_numeric($_GET["id"])) {
    $id = $_GET["id"];

    // Consulta cliente solo si es usuario tipo 0 (cliente)
    $sql = "SELECT c.*
            FROM clientes c
            INNER JOIN login l ON l.id = c.id
            WHERE c.id = ? AND l.id_tipo_usuario = 0
            LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $id);

    // Consulta para obtener datos de dominios (solo activos; eliminado lógico = 0)
    $sql3 = "SELECT * FROM dominios WHERE cliente_id = ? AND IFNULL(eliminado, 0) = 0";
    $stmt3 = $db->prepare($sql3);
    $stmt3->bind_param("i", $id);

    if ($stmt3->execute()) {
        $result3 = $stmt3->get_result();
        $dominios = $result3->fetch_all(MYSQLI_ASSOC);
        $stmt3->close();
    } else {
        $dominios = [];
    }

    // Consulta para obtener datos de hosting (solo activos; eliminado lógico = 0)
    $sql4 = "SELECT * FROM hosting WHERE cliente_id = ? AND IFNULL(eliminado, 0) = 0";
    $stmt4 = $db->prepare($sql4);
    $stmt4->bind_param("i", $id);

    if ($stmt4->execute()) {
        $result4 = $stmt4->get_result();
        $hostings = $result4->fetch_all(MYSQLI_ASSOC);
        $stmt4->close();
    } else {
        $hostings = [];
    }

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $cliente = $result->fetch_assoc();
        $stmt->close();

        if (!$cliente) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Acceso denegado',
                    text: 'Este registro no es un cliente (tipo 0) o no existe.',
                    confirmButtonText: 'Aceptar',
                    willClose: () => { window.location.href = 'clientes.php'; }
                });
            </script>";
            exit();
        }

        $cliente_transferido = $sistema === 'conlineweb'
            && isset($cliente['transferido'])
            && (int) $cliente['transferido'] === 1;

        $transferencia_log = null;
        if ($cliente_transferido) {
            $stmtLog = $conn->prepare(
                "SELECT l.*, COALESCE(u.usuario, CONCAT('Usuario #', l.usuario_id)) AS responsable
                 FROM clientes_transferencia_log l
                 LEFT JOIN login u ON u.id = l.usuario_id
                 WHERE l.cliente_id = ?
                 LIMIT 1"
            );
            if ($stmtLog) {
                $stmtLog->bind_param('i', $id);
                $stmtLog->execute();
                $transferencia_log = $stmtLog->get_result()->fetch_assoc();
                $stmtLog->close();
            }
        }

        // Login del cliente (solo tipo 0)
        $sql2 = "SELECT * FROM login WHERE id = ? AND id_tipo_usuario = 0 LIMIT 1";
        $stmt2 = $db->prepare($sql2);
        $stmt2->bind_param("i", $id);

        if ($stmt2->execute()) {
            $result2 = $stmt2->get_result();
            $login = $result2->fetch_assoc() ?: [];
            $stmt2->close();
        } else {
            $login = [];
        }
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error en la consulta de cliente',
                confirmButtonText: 'Aceptar'
            });
        </script>";
    }
} else {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'ID no válido',
            confirmButtonText: 'Aceptar',
            willClose: () => {
                window.location.href = 'lista_clientes.php';
            }
        });
    </script>";
    exit();
}

// Proyectos del cliente (tabla proyectos en ConlineWeb)
$proyectos = [];
$tags_by_group = [];
$has_descripcion_tecnica = false;
if ($sistema === 'conlineweb' && isset($id) && is_numeric($id)) {
    $id_proy = (int) $id;
    adm_proyectos_ensure_tipo_proyecto($conn);
    try {
        $chkDescTec = $conn->query("SHOW COLUMNS FROM proyectos LIKE 'descripcion_tecnica'");
        if ($chkDescTec && $chkDescTec->num_rows > 0) {
            $has_descripcion_tecnica = true;
        }
    } catch (Throwable $e) {
    }

    $campo_desc_tecnica = $has_descripcion_tecnica ? 'descripcion_tecnica' : 'NULL AS descripcion_tecnica';
    $sql_proy = "SELECT id_proyecto, id_cliente, nombre_proyecto, tipo_proyecto, url, descripcion, {$campo_desc_tecnica},
                        fecha_creacion, mostrar, posicion, activo, bloqueado
                 FROM proyectos
                 WHERE id_cliente = ?
                 ORDER BY posicion ASC, id_proyecto DESC";
    $stmt_proy = $conn->prepare($sql_proy);
    if ($stmt_proy) {
        $stmt_proy->bind_param('i', $id_proy);
        if ($stmt_proy->execute()) {
            $proyectos = $stmt_proy->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
        }
        $stmt_proy->close();
    }

    $result_tags = $conn->query("SELECT t.id, t.id_titulo, t.subtitulo, t.tags FROM tags t ORDER BY t.id_titulo, t.id");
    if ($result_tags && $result_tags->num_rows > 0) {
        while ($r = $result_tags->fetch_assoc()) {
            $group_id = (int) $r['id_titulo'];
            $items = json_decode($r['tags'], true);
            if (!is_array($items)) {
                $items = [];
            }
            $tags_by_group[$group_id][] = [
                'id' => (int) $r['id'],
                'subtitulo' => $r['subtitulo'],
                'items' => $items,
            ];
        }
    }
}

// Consulta para pagos
$sql = "SELECT 
            p.*,
            c.nombre_contacto AS cliente,
            c.correo AS correo_cliente,
            CASE 
                WHEN p.tipo_servicio = '2' THEN d.url_dominio
                WHEN p.tipo_servicio = '1' THEN CONCAT('Producto ', h.tipo_producto)
                ELSE p.concepto
            END AS nombre_servicio,
            CASE
                WHEN p.tipo_servicio = '1' THEN pl.nombre
                ELSE NULL
            END AS nombre_plan,
            CASE
                WHEN p.tipo_servicio = '2' THEN d.fecha_pago
                WHEN p.tipo_servicio = '1' THEN h.fecha_pago
                WHEN p.tipo_servicio = '0' THEN p.fecha_limite_pago
                ELSE p.fecha_limite_pago
            END AS fecha_limite_pago,
            CASE
                WHEN p.tipo_servicio = '2' THEN d.fecha_pago
                WHEN p.tipo_servicio = '1' THEN h.fecha_pago
                WHEN p.tipo_servicio = '0' THEN p.fecha_limite_pago
                ELSE NULL
            END AS fecha_vencimiento_servicio
        FROM pagos p
        LEFT JOIN clientes c ON p.id_clie = c.id
        LEFT JOIN dominios d ON p.tipo_servicio = '2' AND p.id_servicio = d.id_dominio
        LEFT JOIN hosting h ON p.tipo_servicio = '1' AND p.id_servicio = h.id_orden
        LEFT JOIN planes pl ON h.producto = pl.id
        WHERE p.id_clie = ? AND p.Registro = 0
        ORDER BY p.fecha_pago >= CURDATE() DESC, p.fecha_pago ASC";

$stmt = $db->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$pagosall = [];
if ($result && $result->num_rows > 0) {
    $pagosall = $result->fetch_all(MYSQLI_ASSOC);
}

// Consulta para pagos eliminados
$sql_eliminados = "SELECT 
            p.*,
            c.nombre_contacto AS cliente,
            c.correo AS correo_cliente,
            CASE 
                WHEN p.tipo_servicio = '2' THEN d.url_dominio
                WHEN p.tipo_servicio = '1' THEN CONCAT('Producto ', h.tipo_producto)
                ELSE p.concepto
            END AS nombre_servicio,
            CASE
                WHEN p.tipo_servicio = '1' THEN pl.nombre
                ELSE NULL
            END AS nombre_plan,
            CASE
                WHEN p.tipo_servicio = '2' THEN d.fecha_pago
                WHEN p.tipo_servicio = '1' THEN h.fecha_pago
                WHEN p.tipo_servicio = '0' THEN p.fecha_limite_pago
                ELSE p.fecha_limite_pago
            END AS fecha_limite_pago,
            CASE
                WHEN p.tipo_servicio = '2' THEN d.fecha_pago
                WHEN p.tipo_servicio = '1' THEN h.fecha_pago
                WHEN p.tipo_servicio = '0' THEN p.fecha_limite_pago
                ELSE NULL
            END AS fecha_vencimiento_servicio
        FROM pagos p
        LEFT JOIN clientes c ON p.id_clie = c.id
        LEFT JOIN dominios d ON p.tipo_servicio = '2' AND p.id_servicio = d.id_dominio
        LEFT JOIN hosting h ON p.tipo_servicio = '1' AND p.id_servicio = h.id_orden
        LEFT JOIN planes pl ON h.producto = pl.id
        WHERE p.id_clie = ? AND p.Registro = 1
        ORDER BY p.fecha DESC, p.hora DESC";

$stmt_eliminados = $db->prepare($sql_eliminados);
$stmt_eliminados->bind_param("i", $id);
$stmt_eliminados->execute();
$result_eliminados = $stmt_eliminados->get_result();
$pagos_eliminados = [];
if ($result_eliminados && $result_eliminados->num_rows > 0) {
    $pagos_eliminados = $result_eliminados->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="css/admin-platform.css" rel="stylesheet">
    <link href="vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php if ($sistema === 'conlineweb') { adm_proyecto_tipo_styles(); } ?>
    
    <style>
        /* ===== ESTILOS ESTANDARIZADOS CON HOSTING Y DOMINIOS ===== */
        
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        
        :root {
            --primary-dark: #000147;
            --primary: #1a1f6b;
            --primary-light: #2d3388;
            --primary-soft: #eef0ff;
            --secondary: #64748b;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --dark: #0f172a;
            --light: #f8fafc;
            --border: #e2e8f0;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #64748b;
        }
        
        body {
            background: #f1f5f9;
            font-family: 'Inter', sans-serif;
            color: var(--text-primary);
        }
        
        /* Header */
        .dc-page-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
        }
        .dc-page-head__title {
            margin: 0 0 0.35rem;
            color: var(--primary-dark);
            font-weight: 800;
            font-size: clamp(1.45rem, 2vw, 1.85rem);
            letter-spacing: -0.02em;
            display: flex;
            align-items: center;
            gap: 0.55rem;
        }
        .dc-page-head__title i { color: var(--primary-dark); }
        .dc-page-head__sub {
            margin: 0;
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.92rem;
        }
        .dc-client-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            margin-top: 0.75rem;
            padding: 0.45rem 0.85rem;
            border-radius: 999px;
            background: #fff;
            border: 1px solid var(--border);
            box-shadow: 0 1px 2px rgba(15,23,42,0.04);
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        .dc-client-chip__avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--primary-dark);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
        }
        .dc-client-chip__meta {
            color: var(--text-muted);
            font-weight: 500;
            font-size: 0.75rem;
        }
        .dc-head-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .dc-btn-back {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 10px 18px;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--primary-dark);
            background: #fff;
            border: 1px solid var(--border);
            text-decoration: none;
            transition: background 0.18s ease, border-color 0.18s ease, color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
        }
        .dc-btn-back:hover {
            color: var(--primary-dark);
            text-decoration: none;
            background: #f8fafc;
            border-color: rgba(0, 1, 71, 0.22);
            box-shadow: 0 4px 14px rgba(0, 1, 71, 0.08);
            transform: translateY(-1px);
        }
        .dc-btn-back:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 1, 71, 0.12);
        }
        .dc-btn-back i {
            font-size: 0.82rem;
            opacity: 0.9;
        }
        .dc-form-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 1.5rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--border);
        }
        .dc-btn-transfer {
            background: var(--warning);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 10px 20px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.15s, filter 0.15s;
        }
        .dc-btn-transfer:hover {
            filter: brightness(0.95);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(245, 158, 11, 0.28);
        }
        .dc-badge-transfer {
            background: #f59e0b;
            color: #fff;
            padding: 10px 16px;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .constancia-preview {
            margin-top: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: #f8fafc;
            overflow: hidden;
        }
        .constancia-preview__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.65rem 0.85rem;
            background: #fff;
            border-bottom: 1px solid var(--border);
            font-size: 0.82rem;
        }
        .constancia-preview__body {
            min-height: 320px;
            background: #fff;
        }
        .constancia-preview__body iframe,
        .constancia-preview__body object {
            width: 100%;
            height: 360px;
            border: 0;
            background: #fff;
            display: block;
        }
        .constancia-preview__body img {
            max-width: 100%;
            max-height: 360px;
            display: block;
            margin: 0 auto;
            padding: 0.5rem;
        }
        .constancia-preview__empty {
            margin: 0;
            padding: 0.75rem;
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        /* Tabs */
        .nav-tabs-modern {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            border: 1px solid var(--border);
            background: #fff;
            border-radius: 16px;
            padding: 0.4rem;
            margin-bottom: 1.15rem;
            box-shadow: 0 1px 2px rgba(15,23,42,0.04);
            list-style: none;
        }
        .nav-tabs-modern .nav-item { margin: 0; }
        .nav-tabs-modern .nav-link {
            border: none !important;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.82rem;
            padding: 0.7rem 1rem;
            position: relative;
            background: transparent;
            border-radius: 12px;
            transition: all 0.18s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            white-space: nowrap;
            text-decoration: none !important;
        }
        .nav-tabs-modern .nav-link i {
            margin-right: 0 !important;
            font-size: 0.9rem;
            opacity: 0.85;
        }
        .nav-tabs-modern .nav-link.active {
            color: #fff !important;
            background: var(--primary-dark) !important;
            box-shadow: 0 6px 16px rgba(0, 1, 71, 0.22);
        }
        .nav-tabs-modern .nav-link.active::after { display: none; }
        .nav-tabs-modern .nav-link:hover:not(.active) {
            color: var(--primary-dark);
            background: var(--primary-soft);
            border-color: transparent;
        }
        .dc-tab-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            border-radius: 999px;
            background: #e2e8f0;
            color: #334155;
            font-size: 0.68rem;
            font-weight: 700;
            line-height: 1;
        }
        .nav-tabs-modern .nav-link.active .dc-tab-count {
            background: rgba(255,255,255,0.2);
            color: #fff;
        }

        /* Contenedor de contenido */
        .tab-content-container {
            background: white;
            border-radius: 20px;
            padding: 1.35rem 1.4rem 1.5rem;
            border: 1px solid var(--border);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            min-height: 320px;
        }
        .tab-pane.fade {
            transition: opacity 0.2s ease;
        }

        .dc-section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            flex-wrap: wrap;
            padding: 0.85rem 1.1rem;
            margin: 0;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(180deg, #f8fafc 0%, #fff 100%);
        }
        .dc-section-head h4 {
            margin: 0;
            padding: 0;
            border: none;
            background: transparent;
            font-weight: 750;
            font-size: 1rem;
            color: var(--primary-dark);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .dc-section-head h4 i { margin-right: 0 !important; }
        .dc-section-head__hint {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 500;
        }
        .dc-section-head__actions {
            display: inline-flex;
            align-items: flex-end;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-left: auto;
        }
        .dc-section-head__actions .btn-primary-custom {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 38px;
            padding: 0 1rem;
            font-size: 0.8125rem;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .dc-section-head__actions .btn-primary-custom i {
            margin-right: 0.4rem;
        }
        .dc-filter-field {
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
            min-width: 0;
        }
        .dc-filter-field label {
            margin: 0;
            font-size: 0.7rem;
            font-weight: 650;
            color: var(--text-muted);
            letter-spacing: 0.02em;
            text-transform: uppercase;
            line-height: 1;
        }
        .dc-section-head #filtroTipoProyectoCliente {
            width: 220px !important;
            min-width: 220px !important;
            max-width: 220px !important;
            height: 38px !important;
            min-height: 38px !important;
            margin: 0;
            flex-shrink: 0;
        }
        .dc-section-head__meta {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        @media (max-width: 767.98px) {
            .dc-section-head__actions {
                width: 100%;
                margin-left: 0;
            }
            .dc-filter-field {
                flex: 1 1 auto;
            }
            .dc-section-head #filtroTipoProyectoCliente {
                width: 100% !important;
                min-width: 0 !important;
                max-width: 100% !important;
            }
            .dc-section-head__actions .btn-primary-custom {
                flex: 0 0 auto;
            }
        }
        #modalProyectoCliente .tags-container {
            max-height: 220px;
            overflow-y: auto;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 0.75rem 0.9rem;
            background: #f8fafc;
        }
        #modalProyectoCliente .form-check-inline {
            margin-right: 0.85rem;
            margin-bottom: 0.35rem;
        }
        .dc-proy-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            max-width: 280px;
        }
        .dc-proy-actions .btnVistaPreviaProyecto,
        .dc-proy-actions .btn-outline-modern,
        .dc-proy-actions .btnToggleActivo,
        .dc-proy-actions .btnTogglePublicado,
        .dc-proy-actions .btnToggleBloqueado,
        .dc-proy-actions .btnEliminarProyecto,
        .dc-proy-actions .btn-success-modern,
        .dc-proy-actions .btn-danger-modern {
            min-width: 34px;
            padding: 6px 9px;
            border-radius: 10px;
            font-size: 12px;
            line-height: 1;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s;
        }
        .dc-proy-actions .btnVistaPreviaProyecto:disabled,
        .btnVistaPreviaProyecto:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }
        .dc-proy-actions .btn-outline-modern:hover {
            background: var(--primary-soft);
            border-color: var(--primary-light);
            color: var(--primary-dark);
        }
        .dc-proy-actions .btn-success-modern {
            background: #10b981;
            border-color: #10b981;
            color: #fff;
        }
        .dc-proy-actions .btn-danger-modern,
        .dc-proy-actions .btnEliminarProyecto {
            background: #dc2626;
            border-color: #dc2626;
            color: #fff;
        }
        .dc-proy-actions .btn-danger-modern:hover,
        .dc-proy-actions .btnEliminarProyecto:hover {
            background: #b91c1c;
            border-color: #b91c1c;
            color: #fff;
        }
        .posiciones-lista {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 0.75rem 0.9rem;
        }
        .posicion-item {
            display: inline-block;
            margin: 0.2rem 0.3rem 0.2rem 0;
            padding: 0.25rem 0.55rem;
            border-radius: 999px;
            background: #e2e8f0;
            color: #334155;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .posicion-item.actual {
            background: var(--primary-soft);
            color: var(--primary-dark);
        }

        /* Fieldset moderno */
        .modern-fieldset {
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1.15rem 1.25rem 1.25rem;
            margin-bottom: 1.15rem;
            background: #fff;
        }
        
        .modern-fieldset legend {
            float: none;
            width: auto;
            padding: 0 12px;
            color: var(--primary-dark);
            font-weight: 750;
            font-size: 0.92rem;
            margin-bottom: 0;
        }
        
        .modern-fieldset legend i {
            margin-right: 8px;
        }
        
        /* Formulario moderno - IGUAL QUE HOSTING */
        .form-control-modern {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 10px 14px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            width: 100%;
        }
        
        .form-control-modern:focus {
            border-color: var(--primary-dark);
            box-shadow: 0 0 0 3px rgba(0, 1, 71, 0.08);
            outline: none;
        }
        
        .form-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 6px;
            display: block;
        }
        
        .required-field::after {
            content: " *";
            color: var(--danger);
        }
        
        /* Botones modernos - IGUAL QUE HOSTING */
        .btn-primary-custom {
            background: var(--primary-dark);
            color: white;
            border-radius: 12px;
            padding: 10px 28px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s;
            border: none;
        }
        
        .btn-primary-custom i {
            margin-right: 10px;
        }
        
        .btn-primary-custom:hover {
            background: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(0, 1, 71, 0.18);
        }
        
        .btn-primary-custom:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 1, 71, 0.15);
        }
        /* Tablas modernas */
        .modern-table {
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid var(--border);
            background: white;
            box-shadow: 0 1px 2px rgba(15,23,42,0.03);
        }
        .modern-table > .modern-fieldset {
            margin: 1rem;
            border-radius: 14px;
        }
        .modern-table h4 {
            padding: 16px 20px;
            margin: 0;
            border-bottom: 1px solid var(--border);
            background: #fafbff;
            font-weight: 700;
            font-size: 18px;
            color: var(--primary-dark);
        }
        .modern-table h4 i { margin-right: 10px; }
        .modern-table .table-scroll {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .modern-table table {
            width: 100%;
            border-collapse: collapse;
            min-width: 720px;
        }
        .modern-table thead th {
            background: #f8fafc;
            color: var(--text-muted);
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        .modern-table thead th i {
            margin-right: 6px;
            opacity: 0.7;
        }
        .modern-table tbody tr {
            transition: background 0.15s ease;
        }
        .modern-table tbody tr:hover {
            background: #f8faff;
        }
        .modern-table tbody td {
            padding: 13px 14px;
            vertical-align: middle;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-primary);
            border-bottom: 1px solid #f1f5f9;
        }
        .modern-table tbody tr:last-child td {
            border-bottom: none;
        }
        .dc-empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2.5rem 1.5rem;
            color: var(--text-muted);
            gap: 0.4rem;
        }
        .dc-empty i {
            font-size: 1.75rem;
            color: #94a3b8;
            margin-bottom: 0.35rem;
        }
        .dc-empty strong {
            color: var(--text-primary);
            font-size: 0.95rem;
        }
        .dc-empty p {
            margin: 0;
            font-size: 0.85rem;
            max-width: 360px;
        }
        .dc-pass-cell {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 8px;
            border-radius: 8px;
            background: #f8fafc;
            border: 1px solid var(--border);
            min-width: 110px;
        }
        .dc-pass-cell input {
            border: none;
            background: transparent;
            width: 72px;
            outline: none;
            padding: 0;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-secondary);
        }
        .dc-pass-cell .toggle-password {
            border: none;
            background: transparent;
            cursor: pointer;
            color: var(--primary-dark);
            padding: 0;
            line-height: 1;
        }
        
        /* Badges - IGUAL QUE HOSTING */
        .badge-status {
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .badge-success {
            background: #dcfce7;
            color: #15803d;
        }
        
        .badge-danger {
            background: #fee2e2;
            color: #b91c1c;
        }
        
        .badge-warning {
            background: #ffedd5;
            color: #b45309;
        }
        
        .badge-info {
            background: #eef2ff;
            color: #1e40af;
        }
        .badge-secondary {
            background: #f1f5f9;
            color: #475569;
        }
        
        /* Botones de acción pequeños - IGUAL QUE HOSTING */
        .btn-icon-sm {
            border-radius: 10px;
            padding: 5px 12px;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s;
            border: none;
            margin: 0 3px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-icon-sm i {
            font-size: 12px;
        }
        
        .btn-icon-sm:hover {
            transform: translateY(-1px);
        }
        
        .btn-edit {
            background: var(--info);
            color: white;
        }
        
        .btn-edit:hover {
            background: #2563eb;
        }
        
        .btn-toggle {
            background: var(--warning);
            color: white;
        }
        
        .btn-toggle:hover {
            background: #d97706;
        }
        
        .btn-restore {
            background: var(--success);
            color: white;
        }
        
        .btn-restore:hover {
            background: #059669;
        }

        .btn-danger-sm {
            background: #dc2626;
            color: white;
        }

        .btn-danger-sm:hover {
            background: #b91c1c;
            color: white;
        }
        
        /* Input group - IGUAL QUE HOSTING */
        .input-group-modern {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .input-group-modern input {
            flex: 1;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 13px;
        }
        
        .input-group-modern button {
            background: var(--primary-soft);
            border: none;
            border-radius: 10px;
            padding: 8px 12px;
            cursor: pointer;
        }
        
        /* Alertas - IGUAL QUE HOSTING */
        .alert-modern {
            border-radius: 16px;
            border: none;
            padding: 16px 20px;
            margin-bottom: 20px;
        }
        
        .alert-info {
            background: #eef2ff;
            color: #1e40af;
        }
        
        .alert-warning {
            background: #ffedd5;
            color: #b45309;
        }
        
        .alert-info i, .alert-warning i {
            margin-right: 10px;
        }
        
        /* Checkbox / switch de facturación */
        .form-check-modern {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 0;
        }
        
        .form-check-modern input {
            width: 18px;
            height: 18px;
            cursor: pointer;
            margin: 0;
        }
        
        .form-check-modern label {
            margin: 0;
            font-weight: 500;
            cursor: pointer;
        }
        
        .form-check-modern label i {
            margin-right: 8px;
        }

        .facturacion-toggle {
            display: flex;
            align-items: center;
            gap: 14px;
            width: 100%;
            min-height: 46px;
            margin: 0;
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: #f8fafc;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
            user-select: none;
        }
        .facturacion-toggle:hover {
            border-color: #c7d2fe;
            background: #f5f7ff;
        }
        .facturacion-toggle.is-on {
            border-color: #a5b4fc;
            background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
        }
        .facturacion-toggle__switch {
            position: relative;
            width: 44px;
            height: 26px;
            flex-shrink: 0;
            border-radius: 999px;
            background: #cbd5e1;
            transition: background 0.2s;
        }
        .facturacion-toggle.is-on .facturacion-toggle__switch {
            background: var(--primary-dark);
        }
        .facturacion-toggle__switch::after {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #fff;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.2);
            transition: transform 0.2s;
        }
        .facturacion-toggle.is-on .facturacion-toggle__switch::after {
            transform: translateX(18px);
        }
        .facturacion-toggle__copy {
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 0;
        }
        .facturacion-toggle__title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.2;
        }
        .facturacion-toggle__title i {
            color: var(--primary-dark);
            margin-right: 0 !important;
        }
        .facturacion-toggle__hint {
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--text-muted);
            line-height: 1.3;
        }
        .facturacion-toggle input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
            width: 0;
            height: 0;
        }
        .facturacion-field-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 6px;
            display: block;
        }
        
        /* Botones de grupo */
        .btn-group-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        /* Enlaces */
        .text-link {
            color: var(--primary-dark);
            text-decoration: none;
            transition: color 0.2s;
        }
        
        .text-link i {
            margin-right: 6px;
        }
        
        .text-link:hover {
            color: var(--primary);
            text-decoration: underline;
        }
        
        /* Espaciado para iconos en general */
        .btn i, 
        .nav-link i, 
        .btn-icon-sm i,
        .form-check-modern label i,
        .alert-modern i,
        .text-link i,
        .modern-fieldset legend i,
        .modern-table h4 i,
        .page-header h1 i {
            margin-right: 8px;
        }
        
        /* Animaciones */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in-up {
            animation: fadeInUp 0.5s ease-out;
        }
        
        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--light);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--text-muted);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .nav-tabs-modern {
                flex-wrap: nowrap;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            .nav-tabs-modern .nav-link {
                padding: 0.65rem 0.85rem;
                font-size: 0.75rem;
            }
            .modern-table {
                overflow: hidden;
            }
            .btn-group-actions {
                flex-direction: column;
                gap: 5px;
            }
            .tab-content-container {
                padding: 1rem;
            }
        }
    </style>
</head>

<body>
<div id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <div class="container-xl my-4 py-4 legacy-touch" style="max-width: 96% !important;">
            
            <?php
            $dc_nombre = trim((string) ($cliente['nombre_contacto'] ?? ''));
            $dc_empresa = trim((string) ($cliente['empresa'] ?? ''));
            $dc_correo = trim((string) ($cliente['correo'] ?? ''));
            $dc_inicial = strtoupper(substr($dc_nombre !== '' ? $dc_nombre : 'C', 0, 1));
            $dc_count_dominios = is_array($dominios ?? null) ? count($dominios) : 0;
            $dc_count_hostings = is_array($hostings ?? null) ? count($hostings) : 0;
            $dc_count_proyectos = is_array($proyectos ?? null) ? count($proyectos) : 0;
            $dc_count_pagos = is_array($pagosall ?? null) ? count($pagosall) : 0;
            $dc_count_eliminados = is_array($pagos_eliminados ?? null) ? count($pagos_eliminados) : 0;
            ?>
            <!-- Header -->
            <div class="dc-page-head fade-in-up">
                <div>
                    <h1 class="dc-page-head__title">
                        <i class="fas fa-user-edit"></i>Detalle del Cliente
                    </h1>
                    <p class="dc-page-head__sub">Información, servicios y pagos en un solo lugar</p>
                    <div class="dc-client-chip">
                        <span class="dc-client-chip__avatar"><?php echo htmlspecialchars($dc_inicial); ?></span>
                        <span>
                            <?php echo htmlspecialchars($dc_nombre !== '' ? $dc_nombre : ('Cliente #' . (int) $id)); ?>
                            <?php if ($dc_empresa !== ''): ?>
                                <span class="dc-client-chip__meta"> · <?php echo htmlspecialchars($dc_empresa); ?></span>
                            <?php endif; ?>
                            <?php if ($dc_correo !== ''): ?>
                                <div class="dc-client-chip__meta"><?php echo htmlspecialchars($dc_correo); ?></div>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                <div class="dc-head-actions">
                <a href="clientes.php?sistema=<?php echo htmlspecialchars($sistema, ENT_QUOTES, 'UTF-8'); ?>" class="dc-btn-back">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i> Volver a clientes
                </a>
                <?php if ($sistema === 'conlineweb' && !empty($cliente_transferido)): ?>
                    <span class="dc-badge-transfer">
                        <i class="fas fa-exchange-alt"></i> Transferido a Hosting Pro
                        <?php if (!empty($cliente['fecha_transferencia'])): ?>
                        · <?php echo htmlspecialchars($cliente['fecha_transferencia']); ?>
                        <?php endif; ?>
                    </span>
                <?php elseif ($sistema === 'conlineweb'): ?>
                    <button type="button" class="dc-btn-transfer" onclick="transferirCliente(<?php echo (int) $id; ?>)">
                        <i class="fas fa-exchange-alt"></i> Transferir a HostingPro
                    </button>
                <?php endif; ?>
                </div>
            </div>

            <!-- Tabs -->
            <ul class="nav nav-tabs-modern" id="myTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="info-tab" data-toggle="tab" href="#info" role="tab">
                        <i class="fas fa-user-circle"></i>Información
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="details-tab" data-toggle="tab" href="#details" role="tab">
                        <i class="fas fa-globe"></i>Dominios
                        <span class="dc-tab-count"><?php echo (int) $dc_count_dominios; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="other-tab" data-toggle="tab" href="#other" role="tab">
                        <i class="fas fa-server"></i>Hostings
                        <span class="dc-tab-count"><?php echo (int) $dc_count_hostings; ?></span>
                    </a>
                </li>
                <?php if ($sistema === 'conlineweb'): ?>
                <li class="nav-item">
                    <a class="nav-link" id="proyectos-tab" data-toggle="tab" href="#proyectos" role="tab">
                        <i class="fas fa-project-diagram"></i>Proyectos
                        <span class="dc-tab-count"><?php echo (int) $dc_count_proyectos; ?></span>
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link" id="payments-tab" data-toggle="tab" href="#payments" role="tab">
                        <i class="fas fa-credit-card"></i>Pagos
                        <span class="dc-tab-count"><?php echo (int) $dc_count_pagos; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="eliminados-tab" data-toggle="tab" href="#eliminados" role="tab">
                        <i class="fas fa-trash-alt"></i>Eliminados
                        <span class="dc-tab-count"><?php echo (int) $dc_count_eliminados; ?></span>
                    </a>
                </li>
            </ul>

            <!-- Contenido de las pestañas -->
            <div class="tab-content-container tab-content" id="myTabContent">
                <!-- Pestaña 1: Información Principal -->
                <div class="tab-pane fade show active" id="info" role="tabpanel">
                    <?php if ($cliente_transferido && $transferencia_log): ?>
                    <div class="alert mb-4" style="background:linear-gradient(135deg,#fffbeb 0%,#fef3c7 100%);border:1px solid #f59e0b;border-radius:12px;padding:16px;">
                        <strong style="color:#92400e;"><i class="fas fa-clipboard-check"></i> Registro de transferencia a Hosting Pro</strong>
                        <div class="row mt-3" style="font-size:14px;color:#78350f;">
                            <div class="col-md-3"><strong>Responsable:</strong><br><?php echo htmlspecialchars($transferencia_log['responsable']); ?></div>
                            <div class="col-md-3"><strong>Fecha:</strong><br><?php echo htmlspecialchars($transferencia_log['fecha_transferencia']); ?></div>
                            <div class="col-md-2"><strong>Dominios:</strong><br><?php echo (int) $transferencia_log['dominios_transferidos']; ?></div>
                            <div class="col-md-2"><strong>Hostings:</strong><br><?php echo (int) $transferencia_log['hostings_transferidos']; ?></div>
                            <div class="col-md-2"><strong>Pagos:</strong><br><?php echo (int) $transferencia_log['pagos_transferidos']; ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <form class="needs-validation" method="POST" novalidate>
                        <!-- Información Personal -->
                        <fieldset class="modern-fieldset">
                            <legend><i class="fas fa-user"></i>Información Personal</legend>
                            <div class="row">
                                <div class="mb-3 col-md-4">
                                    <label for="nombre" class="form-label required-field">Nombre Contacto</label>
                                    <input type="text" class="form-control-modern" id="nombre" name="nombre" required
                                        value="<?php echo htmlspecialchars($cliente['nombre_contacto'] ?? ''); ?>"
                                        placeholder="Juan Pérez">
                                </div>
                                <div class="mb-3 col-md-4">
                                    <label for="telefono" class="form-label required-field">Teléfono</label>
                                    <input type="tel" class="form-control-modern" id="telefono" name="telefono" required
                                        value="<?php echo htmlspecialchars($cliente["telefono"] ?? ''); ?>"
                                        placeholder="55 1234 5678">
                                </div>
                                <div class="mb-3 col-md-4">
                                    <label for="email" class="form-label required-field">Correo Electrónico</label>
                                    <input type="email" class="form-control-modern" id="email" name="email" required
                                        value="<?php echo htmlspecialchars($cliente["correo"] ?? ''); ?>"
                                        placeholder="juan@empresa.com">
                                </div>
                                <div class="mb-3 col-md-6">
                                    <label for="empresa" class="form-label">Empresa</label>
                                    <input type="text" class="form-control-modern" id="empresa" name="empresa"
                                        value="<?php echo htmlspecialchars($cliente["empresa"] ?? ''); ?>"
                                        placeholder="Nombre de la empresa">
                                </div>
                                <div class="mb-3 col-md-6">
                                    <span class="facturacion-field-label">Facturación</span>
                                    <label class="facturacion-toggle <?php echo (isset($cliente["facturacion"]) && $cliente["facturacion"] == 1) ? 'is-on' : ''; ?>" id="facturacionToggle">
                                        <input class="form-check-input" type="checkbox" id="requiereFacturacion"
                                            name="requiereFacturacion" <?php echo (isset($cliente["facturacion"]) && $cliente["facturacion"] == 1) ? 'checked' : ''; ?>>
                                        <span class="facturacion-toggle__switch" aria-hidden="true"></span>
                                        <span class="facturacion-toggle__copy">
                                            <span class="facturacion-toggle__title">
                                                <i class="fas fa-file-invoice"></i>
                                                Requiere facturación
                                            </span>
                                            <span class="facturacion-toggle__hint">Activa IVA y datos fiscales del cliente</span>
                                        </span>
                                    </label>
                                    <input type="hidden" name="facturacion" id="facturacion" value="<?php echo (isset($cliente["facturacion"]) && $cliente["facturacion"] == 1) ? '1' : '0'; ?>">
                                </div>
                            </div>
                        </fieldset>

                        <?php
                        // Módulo compartido (misma lógica que cliente.php)
                        $constanciaInfo = cw_constancia_fiscal_parse($cliente['constancia_situacion_fiscal'] ?? null);
                        if ($constanciaInfo):
                            $constanciaFile = $constanciaInfo['filename'];
                            $constanciaUrl = $constanciaInfo['url']; // https://cliente.conlineweb.com/constancias_fiscales/...
                        ?>
                        <div class="constancia-preview mb-3" id="constanciaPreviewCurrent">
                            <div class="constancia-preview__head">
                                <span><i class="fas fa-file-pdf text-danger me-1"></i><?php echo htmlspecialchars($constanciaFile); ?></span>
                                <a href="<?php echo htmlspecialchars($constanciaUrl); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                                    Abrir constancia
                                </a>
                            </div>
                            <div class="constancia-preview__body">
                                <?php if (($constanciaInfo['type'] ?? '') === 'image'): ?>
                                    <img src="<?php echo htmlspecialchars($constanciaUrl); ?>" alt="Constancia fiscal">
                                <?php else: ?>
                                    <iframe src="<?php echo htmlspecialchars($constanciaUrl); ?>" title="Constancia de situación fiscal"></iframe>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Datos fiscales (solo si requiere facturación) -->
                        <fieldset id="seccionEmpresarial" class="modern-fieldset" style="display: none;">
                            <legend><i class="fas fa-file-invoice-dollar"></i>Datos fiscales</legend>
                            <div class="row">
                                <div class="mb-3 col-md-4">
                                    <label for="rsocial" class="form-label">Razón Social</label>
                                    <input type="text" class="form-control-modern" id="rsocial" name="rsocial"
                                        value="<?php echo htmlspecialchars($cliente["rsocial"] ?? ''); ?>"
                                        placeholder="Razón social completa">
                                </div>
                                <div class="mb-3 col-md-4">
                                    <label for="rfc" class="form-label">RFC</label>
                                    <input type="text" class="form-control-modern" id="rfc" name="rfc"
                                        value="<?php echo htmlspecialchars($cliente["rfc"] ?? ''); ?>"
                                        placeholder="XAXX010101000">
                                </div>
                                <div class="mb-3 col-md-4">
                                    <label for="especificacion" class="form-label">Especificación</label>
                                    <input type="text" id="especificacion" name="especificacion" class="form-control-modern" 
                                        value="<?php echo htmlspecialchars($cliente["especificacion"] ?? ''); ?>">
                                </div>
                                <div class="mb-3 col-md-12">
                                    <label for="constancia_fiscal" class="form-label">Constancia de Situación Fiscal</label>
                                    <input type="file" class="form-control-modern" id="constancia_fiscal" name="constancia_fiscal" accept=".pdf,.jpg,.jpeg,.png">
                                    <small class="text-muted d-block mt-1">Los archivos se guardan y consultan en cliente.conlineweb.com/constancias_fiscales/</small>
                                </div>
                            </div>
                        </fieldset>

                        <!-- Dirección (oculto por defecto) -->
                        <fieldset id="seccionDireccion" class="modern-fieldset" style="display: none;">
                            <legend><i class="fas fa-map-marker-alt"></i>Dirección</legend>
                            <div class="row">
                                <div class="mb-3 col-md-4">
                                    <label for="calle" class="form-label">Calle</label>
                                    <input type="text" class="form-control-modern" id="calle" name="calle" 
                                        value="<?php echo htmlspecialchars($cliente["calle"] ?? ''); ?>" placeholder="Av. Principal">
                                </div>
                                <div class="mb-3 col-md-2">
                                    <label for="next" class="form-label">N° Exterior</label>
                                    <input type="text" class="form-control-modern" id="next" name="next"
                                        value="<?php echo htmlspecialchars($cliente["next"] ?? ''); ?>" placeholder="123">
                                </div>
                                <div class="mb-3 col-md-2">
                                    <label for="nint" class="form-label">N° Interior</label>
                                    <input type="text" class="form-control-modern" id="nint" name="nint"
                                        value="<?php echo htmlspecialchars($cliente["nint"] ?? ''); ?>" placeholder="A">
                                </div>
                                <div class="mb-3 col-md-4">
                                    <label for="colonia" class="form-label">Colonia</label>
                                    <input type="text" class="form-control-modern" id="colonia" name="colonia"
                                        value="<?php echo htmlspecialchars($cliente["col"] ?? ''); ?>" placeholder="Centro">
                                </div>
                                <div class="mb-3 col-md-2">
                                    <label for="cp" class="form-label">Código Postal</label>
                                    <input type="text" class="form-control-modern" id="cp" name="cp"
                                        value="<?php echo htmlspecialchars($cliente["cp"] ?? ''); ?>" placeholder="01000">
                                </div>
                                <div class="mb-3 col-md-3">
                                    <label for="pais" class="form-label">País</label>
                                    <input type="text" class="form-control-modern" name="pais" placeholder="País"
                                        value="<?php echo htmlspecialchars($cliente["pais"] ?? ''); ?>">
                                </div>
                                <div class="mb-3 col-md-3">
                                    <label for="estado" class="form-label">Estado</label>
                                    <select id="estado" name="estado" class="form-control-modern">
                                        <option value="" selected disabled>Selecciona un estado</option>
                                        <?php
                                        $result_estados->data_seek(0);
                                        while ($row_estado = $result_estados->fetch_assoc()):
                                            $selected = ($row_estado['id_estado'] == $cliente['estado']) ? 'selected' : '';
                                        ?>
                                            <option value="<?php echo $row_estado['id_estado']; ?>" <?php echo $selected; ?>>
                                                <?php echo $row_estado['estado']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="mb-3 col-md-4">
                                    <label for="municipio" class="form-label">Ciudad/Municipio</label>
                                    <select id="municipio" name="municipio" class="form-control-modern">
                                        <option value="<?php echo htmlspecialchars($cliente['ciudad'] ?? ''); ?>" selected>
                                            <?php echo htmlspecialchars($cliente['ciudad'] ?? 'Selecciona un estado primero'); ?>
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </fieldset>

                        <!-- Seguridad -->
                        <fieldset class="modern-fieldset">
                            <legend><i class="fas fa-lock"></i>Seguridad</legend>
                            <div class="row">
                                <div class="mb-3 col-md-6">
                                    <label for="inputPassword" class="form-label">Contraseña</label>
                                    <div class="input-group-modern">
                                        <input type="password" id="inputPassword" name="inputPassword" 
                                            value="<?php echo htmlspecialchars($login["contrasena_normal"] ?? ''); ?>" placeholder="Mínimo 8 caracteres">
                                        <button type="button" class="toggle-password">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <small class="text-secondary-custom">Dejar en blanco para mantener la contraseña actual</small>
                                </div>
                                <div class="mb-3 col-md-6">
                                    <label for="confirmPassword" class="form-label">Confirmar Contraseña</label>
                                    <div class="input-group-modern">
                                        <input type="password" id="confirmPassword" 
                                            value="<?php echo htmlspecialchars($login["contrasena_normal"] ?? ''); ?>" placeholder="Repite tu contraseña">
                                        <button type="button" class="toggle-password">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </fieldset>

                        <div class="dc-form-actions">
                            <button type="submit" class="btn-primary-custom">
                                <i class="fas fa-save"></i>Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Pestaña 2: Dominios -->
                <div class="tab-pane fade" id="details" role="tabpanel">
                    <div class="modern-table">
                        <div class="dc-section-head">
                            <h4><i class="fas fa-globe"></i>Dominios del cliente</h4>
                            <span class="dc-section-head__hint"><?php echo (int) $dc_count_dominios; ?> registro(s)</span>
                        </div>
                        <?php if (!empty($dominios)): ?>
                            <div class="table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-building"></i>Proveedor</th>
                                        <th><i class="fas fa-link"></i>Dominio</th>
                                        <th><i class="fas fa-handshake"></i>Gestión</th>
                                        <th><i class="fas fa-user"></i>Usuario</th>
                                        <th><i class="fas fa-key"></i>Contraseña</th>
                                        <th><i class="fas fa-calendar"></i>Fecha Contratación</th>
                                        <th><i class="fas fa-calendar-check"></i>Fecha Pago</th>
                                        <th><i class="fas fa-dollar-sign"></i>Costo</th>
                                        <th><i class="fas fa-toggle-on"></i>Estado</th>
                                        <th><i class="fas fa-cog"></i>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dominios as $dominio):
                                        $es_gestionado_cw = isset($dominio['registrado']) && (int) $dominio['registrado'] === 1;
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($dominio['proveedor']); ?></td>
                                            <td>
                                                <?php
                                                $dom_url_raw = trim((string) ($dominio['url_dominio'] ?? ''));
                                                $dom_href = $dom_url_raw;
                                                if ($dom_href !== '' && !preg_match('#^https?://#i', $dom_href)) {
                                                    $dom_href = 'https://' . ltrim($dom_href, '/');
                                                }
                                                ?>
                                                <?php if ($dom_href !== ''): ?>
                                                    <a href="<?php echo htmlspecialchars($dom_href, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="text-link">
                                                        <i class="fas fa-external-link-alt"></i><?php echo htmlspecialchars($dom_url_raw); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($es_gestionado_cw): ?>
                                                    <span class="badge-status badge-success" title="Dominio gestionado / registrado por ConlineWeb">
                                                        <i class="fas fa-check-circle"></i> Gestionado por ConlineWeb
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge-status badge-warning" title="Dominio registrado con un proveedor externo">
                                                        <i class="fas fa-external-link-alt"></i> Registrado con proveedor externo
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($dominio['usuario']); ?></td>
                                            <td>
                                                <div class="dc-pass-cell">
                                                    <input type="password" value="<?php echo htmlspecialchars($dominio['contrasena_normal']); ?>" readonly>
                                                    <button type="button" class="toggle-password" title="Mostrar/ocultar"><i class="bi bi-eye"></i></button>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($dominio['fecha_contratacion']); ?></td>
                                            <td><?php echo htmlspecialchars($dominio['fecha_pago']); ?></td>
                                            <td class="fw-semibold">$<?php echo number_format($dominio['costo_dominio'], 2); ?></td>
                                            <td>
                                                <span class="badge-status <?php echo ($dominio['estado_dominio'] == 1) ? 'badge-success' : 'badge-danger'; ?>">
                                                    <?php echo ($dominio['estado_dominio'] == 1) ? 'Activo' : 'Inactivo'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group-actions">
                                                    <button class="btn-icon-sm btn-toggle" 
                                                        onclick="toggleEstadoDominio(<?php echo $dominio['id_dominio']; ?>, <?php echo $dominio['estado_dominio']; ?>)"
                                                        title="<?php echo ($dominio['estado_dominio'] == 1) ? 'Desactivar' : 'Activar'; ?>">
                                                        <i class="bi bi-<?php echo ($dominio['estado_dominio'] == 1) ? 'x-circle' : 'check-circle'; ?>"></i>
                                                        <?php echo ($dominio['estado_dominio'] == 1) ? 'Desactivar' : 'Activar'; ?>
                                                    </button>
                                                    <a href="formulario_dominio.php?edit=1&id_dominio=<?php echo $dominio['id_dominio']; ?>&id_cliente=<?php echo $dominio['cliente_id']; ?>&sistema=<?php echo $sistema; ?>"
                                                        class="btn-icon-sm btn-edit" title="Editar">
                                                        <i class="bi bi-pencil"></i>Editar
                                                    </a>
                                                    <button type="button" class="btn-icon-sm btn-danger-sm"
                                                        onclick="eliminarDominioPermanente(<?php echo (int) $dominio['id_dominio']; ?>, <?php echo (int) $dominio['cliente_id']; ?>, '<?php echo htmlspecialchars(addslashes($dominio['url_dominio'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>')"
                                                        title="Eliminar dominio">
                                                        <i class="bi bi-trash"></i>Eliminar
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            </div>
                        <?php else: ?>
                            <div class="dc-empty">
                                <i class="fas fa-globe"></i>
                                <strong>Sin dominios</strong>
                                <p>Este cliente aún no tiene dominios registrados.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pestaña 3: Hostings -->
                <div class="tab-pane fade" id="other" role="tabpanel">
                    <div class="modern-table">
                        <div class="dc-section-head">
                            <h4><i class="fas fa-server"></i>Hostings del cliente</h4>
                            <span class="dc-section-head__hint"><?php echo (int) $dc_count_hostings; ?> registro(s)</span>
                        </div>
                        <?php if (!empty($hostings)): ?>
                            <div class="table-scroll">
                            <table class="table" width="100%">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-link"></i>Dominio</th>
                                        <th><i class="fas fa-tag"></i>Nombre Hosting</th>
                                        <th><i class="fas fa-box"></i>Tipo Producto</th>
                                        <th><i class="fas fa-user"></i>Usuario</th>
                                        <th><i class="fas fa-key"></i>Contraseña</th>
                                        <th><i class="fas fa-dollar-sign"></i>Costo</th>
                                        <th><i class="fas fa-calendar"></i>Fecha Contratación</th>
                                        <th><i class="fas fa-calendar-check"></i>Fecha Pago</th>
                                        <th><i class="fas fa-toggle-on"></i>Estado</th>
                                        <th><i class="fas fa-cog"></i>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($hostings as $hosting): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($hosting['dominio']); ?></td>
                                            <td><?php echo htmlspecialchars($hosting['nom_host']); ?></td>
                                            <td><?php echo htmlspecialchars($hosting['tipo_producto']); ?></td>
                                            <td><?php echo htmlspecialchars($hosting['usuario']); ?></td>
                                            <td>
                                                <div class="dc-pass-cell">
                                                    <input type="password" value="<?php echo htmlspecialchars($hosting['contrasena_normal']); ?>" readonly>
                                                    <button type="button" class="toggle-password" title="Mostrar/ocultar"><i class="bi bi-eye"></i></button>
                                                </div>
                                            </td>
                                            <td class="fw-semibold">$<?php echo number_format($hosting['costo_producto'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($hosting['fecha_contratacion']); ?></td>
                                            <td><?php echo htmlspecialchars($hosting['fecha_pago']); ?></td>
                                            <td>
                                                <span class="badge-status <?php echo ($hosting['estado_producto'] == 1) ? 'badge-success' : 'badge-danger'; ?>">
                                                    <?php echo ($hosting['estado_producto'] == 1) ? 'Activo' : 'Inactivo'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group-actions">
                                                    <button class="btn-icon-sm btn-toggle" 
                                                        onclick="toggleEstadoHosting(<?php echo $hosting['id_orden']; ?>, <?php echo $hosting['estado_producto']; ?>)"
                                                        title="<?php echo ($hosting['estado_producto'] == 1) ? 'Desactivar' : 'Activar'; ?>">
                                                        <i class="bi bi-<?php echo ($hosting['estado_producto'] == 1) ? 'x-circle' : 'check-circle'; ?>"></i>
                                                        <?php echo ($hosting['estado_producto'] == 1) ? 'Desactivar' : 'Activar'; ?>
                                                    </button>
                                                    <a href="formulario_hosting.php?edit=1&id_hosting=<?php echo $hosting['id_orden']; ?>&id_cliente=<?php echo $hosting['cliente_id']; ?>&sistema=<?php echo $sistema; ?>"
                                                        class="btn-icon-sm btn-edit" title="Editar">
                                                        <i class="bi bi-pencil"></i>Editar
                                                    </a>
                                                    <button type="button" class="btn-icon-sm btn-danger-sm"
                                                        onclick="eliminarHostingPermanente(<?php echo (int) $hosting['id_orden']; ?>, <?php echo (int) $hosting['cliente_id']; ?>, '<?php echo htmlspecialchars(addslashes($hosting['nom_host'] ?? $hosting['dominio'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>')"
                                                        title="Eliminar hosting">
                                                        <i class="bi bi-trash"></i>Eliminar
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            </div>
                        <?php else: ?>
                            <div class="dc-empty">
                                <i class="fas fa-server"></i>
                                <strong>Sin hostings</strong>
                                <p>Este cliente aún no tiene hostings registrados.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($sistema === 'conlineweb'): ?>
                <!-- Pestaña: Proyectos -->
                <div class="tab-pane fade" id="proyectos" role="tabpanel">
                    <div class="modern-table">
                        <div class="dc-section-head">
                            <div class="dc-section-head__meta">
                                <h4><i class="fas fa-project-diagram"></i>Proyectos del cliente</h4>
                                <span class="dc-section-head__hint"><?php echo (int) $dc_count_proyectos; ?> registro(s)</span>
                            </div>
                            <div class="dc-section-head__actions">
                                <div class="dc-filter-field">
                                    <label for="filtroTipoProyectoCliente">Tipo</label>
                                    <select id="filtroTipoProyectoCliente" class="form-control-modern">
                                        <?php echo adm_proyecto_tipo_options_html('', true); ?>
                                    </select>
                                </div>
                                <button type="button" class="btn-primary-custom" id="btnNuevoProyectoCliente">
                                    <i class="fas fa-plus"></i> Nuevo proyecto
                                </button>
                            </div>
                        </div>
                        <?php if (!empty($proyectos)): ?>
                            <div class="table-scroll">
                            <table class="table" width="100%" id="tablaProyectosCliente">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-hashtag"></i> ID</th>
                                        <th><i class="fas fa-sort-numeric-down"></i> Posición</th>
                                        <th><i class="fas fa-project-diagram"></i> Proyecto</th>
                                        <th><i class="fas fa-layer-group"></i> Tipo</th>
                                        <th><i class="fas fa-desktop"></i> Vista previa</th>
                                        <th><i class="fas fa-link"></i> URL</th>
                                        <th><i class="fas fa-power-off"></i> Estado</th>
                                        <th><i class="fas fa-globe"></i> Publicado</th>
                                        <th><i class="fas fa-lock"></i> Privado</th>
                                        <th><i class="fas fa-calendar"></i> Creación</th>
                                        <th><i class="fas fa-cog"></i> Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($proyectos as $proy): ?>
                                        <?php
                                        $is_activo = ((int) ($proy['activo'] ?? 1) === 1);
                                        $is_publicado = ((int) ($proy['mostrar'] ?? 0) === 1);
                                        $is_bloqueado = ((int) ($proy['bloqueado'] ?? 0) === 1);
                                        $url_raw = trim((string) ($proy['url'] ?? ''));
                                        $tipo_proy = adm_proyecto_tipo_normalize($proy['tipo_proyecto'] ?? 0);
                                        ?>
                                        <tr data-tipo="<?php echo (int) $tipo_proy; ?>">
                                            <td class="fw-semibold">#<?php echo (int) $proy['id_proyecto']; ?></td>
                                            <td><?php echo !empty($proy['posicion']) ? (int) $proy['posicion'] : '—'; ?></td>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($proy['nombre_proyecto'] ?? ''); ?></td>
                                            <td><?php echo adm_proyecto_tipo_select_cell((int) $proy['id_proyecto'], $tipo_proy); ?></td>
                                            <td><?php echo adm_proyecto_preview_cell($url_raw, (string) ($proy['nombre_proyecto'] ?? '')); ?></td>
                                            <td style="min-width:240px; max-width:300px;">
                                                <?php if ($url_raw !== ''): ?>
                                                    <a href="<?php echo htmlspecialchars($url_raw); ?>" target="_blank" rel="noopener" title="<?php echo htmlspecialchars($url_raw); ?>">
                                                        <?php echo htmlspecialchars($url_raw); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge-status <?php echo $is_activo ? 'badge-success' : 'badge-danger'; ?>">
                                                    <?php echo $is_activo ? 'Activo' : 'Inactivo'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge-status <?php echo $is_publicado ? 'badge-success' : 'badge-danger'; ?>">
                                                    <?php echo $is_publicado ? 'Publicado' : 'No publicado'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge-status <?php echo $is_bloqueado ? 'badge-danger' : 'badge-success'; ?>">
                                                    <?php echo $is_bloqueado ? 'Privado' : 'Público'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $fc = $proy['fecha_creacion'] ?? '';
                                                echo $fc !== '' ? htmlspecialchars(date('d/m/Y', strtotime($fc))) : '—';
                                                ?>
                                            </td>
                                            <td>
                                                <div class="btn-group-actions dc-proy-actions">
                                                    <button type="button" class="btn-outline-modern btnVistaPreviaProyecto"
                                                        data-url="<?php echo htmlspecialchars($url_raw, ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-nombre="<?php echo htmlspecialchars($proy['nombre_proyecto'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                        title="Vista previa"
                                                        <?php echo $url_raw === '' ? 'disabled' : ''; ?>>
                                                        <i class="fas fa-desktop"></i>
                                                    </button>
                                                    <button type="button" class="btn-outline-modern btnEditarProyectoCliente"
                                                        data-id="<?php echo (int) $proy['id_proyecto']; ?>"
                                                        title="Editar proyecto">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn-outline-modern btnPosicion"
                                                        data-id="<?php echo (int) $proy['id_proyecto']; ?>"
                                                        data-posicion-actual="<?php echo (int) ($proy['posicion'] ?? 0); ?>"
                                                        title="Cambiar posición">
                                                        <i class="fas fa-sort-numeric-down"></i>
                                                    </button>
                                                    <button type="button" class="btnToggleActivo <?php echo $is_activo ? 'btn-success-modern' : 'btn-outline-modern'; ?>"
                                                        data-id="<?php echo (int) $proy['id_proyecto']; ?>"
                                                        title="<?php echo $is_activo ? 'Desactivar' : 'Activar'; ?>">
                                                        <i class="fas fa-<?php echo $is_activo ? 'check-circle' : 'times-circle'; ?>"></i>
                                                    </button>
                                                    <button type="button" class="btnTogglePublicado <?php echo $is_publicado ? 'btn-success-modern' : 'btn-outline-modern'; ?>"
                                                        data-id="<?php echo (int) $proy['id_proyecto']; ?>"
                                                        title="<?php echo $is_publicado ? 'Quitar de publicados' : 'Publicar'; ?>">
                                                        <i class="fas fa-<?php echo $is_publicado ? 'eye' : 'eye-slash'; ?>"></i>
                                                    </button>
                                                    <button type="button" class="btnToggleBloqueado <?php echo $is_bloqueado ? 'btn-danger-modern' : 'btn-outline-modern'; ?>"
                                                        data-id="<?php echo (int) $proy['id_proyecto']; ?>"
                                                        title="<?php echo $is_bloqueado ? 'Hacer público' : 'Hacer privado'; ?>">
                                                        <i class="fas fa-<?php echo $is_bloqueado ? 'lock' : 'lock-open'; ?>"></i>
                                                    </button>
                                                    <button type="button" class="btn-danger-modern btnEliminarProyecto"
                                                        data-id="<?php echo (int) $proy['id_proyecto']; ?>"
                                                        data-nombre="<?php echo htmlspecialchars($proy['nombre_proyecto'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                        title="Eliminar proyecto">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            </div>
                        <?php else: ?>
                            <div class="dc-empty">
                                <i class="fas fa-project-diagram"></i>
                                <strong>Sin proyectos</strong>
                                <p>Este cliente aún no tiene proyectos registrados. Usa «Nuevo proyecto» para dar de alta el primero.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Pestaña 4: Pagos -->
                <div class="tab-pane fade" id="payments" role="tabpanel">
                    <?php
                    $sql_cliente = "SELECT c.*
                                    FROM clientes c
                                    INNER JOIN login l ON l.id = c.id
                                    WHERE c.id = ? AND l.id_tipo_usuario = 0
                                    LIMIT 1";
                    $stmt_cliente = $db->prepare($sql_cliente);
                    $stmt_cliente->bind_param("i", $id);
                    $stmt_cliente->execute();
                    $result_cliente = $stmt_cliente->get_result();
                    $cliente_info = $result_cliente->fetch_assoc();
                    $stmt_cliente->close();

                    $sql_pagos = "SELECT * FROM pagos WHERE id_clie = ? AND Registro = 0 ORDER BY fecha_pago DESC";
                    $stmt_pagos = $db->prepare($sql_pagos);
                    $stmt_pagos->bind_param("i", $id);
                    $stmt_pagos->execute();
                    $result_pagos = $stmt_pagos->get_result();
                    $pagos = $result_pagos->fetch_all(MYSQLI_ASSOC);
                    $stmt_pagos->close();
                    ?>
                    
                    <div class="modern-table">
                        <div class="dc-section-head">
                            <h4><i class="fas fa-credit-card"></i>Historial de pagos</h4>
                            <span class="dc-section-head__hint"><?php echo (int) $dc_count_pagos; ?> activo(s)</span>
                        </div>
                        
                        <!-- Formulario para agregar pago -->
                        <div class="modern-fieldset mb-4">
                            <legend><i class="fas fa-plus-circle"></i>Agregar nuevo pago</legend>
                            <form id="pagoForm">
                                <input type="hidden" name="cliente_id" value="<?php echo $_GET['id']; ?>">
                                <input type="hidden" name="cliente_facturacion" value="<?php echo isset($cliente_info['facturacion']) ? $cliente_info['facturacion'] : ''; ?>">
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <label for="monto" class="form-label required-field">Monto</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control-modern" id="monto" name="monto" step="0.01" min="0.01" required>
                                            <span class="input-group-text" style="background: var(--primary-soft);">$</span>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="currency" class="form-label required-field">Moneda</label>
                                        <select class="form-control-modern" id="currency" name="currency" required>
                                            <option value="MXN">MXN - Pesos Mexicanos</option>
                                            <option value="USD">USD - Dólares Americanos</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="concepto" class="form-label required-field">Concepto</label>
                                        <input type="text" class="form-control-modern" id="concepto" name="concepto" required placeholder="Ej: Renovación de hosting, Registro de dominio...">
                                    </div>
                                </div>
                                
                                <!-- Configuración de Fecha y Recurrencia -->
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="fecha_limite_pago" class="form-label required-field">
                                            <i class="fas fa-calendar-alt"></i> Fecha Límite de Pago
                                        </label>
                                        <input type="date" class="form-control-modern" id="fecha_limite_pago" name="fecha_limite_pago" 
                                               value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required>
                                        <small class="text-muted">Fecha en que vence el pago</small>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="frecuencia_pago" class="form-label">
                                            <i class="fas fa-sync-alt"></i> Frecuencia de Pago
                                        </label>
                                        <select class="form-control-modern" id="frecuencia_pago" name="frecuencia_pago">
                                            <option value="0" selected>Manual/Único (no se repite)</option>
                                            <option value="1">Semanal (cada 7 días)</option>
                                            <option value="2">Mensual (cada mes)</option>
                                            <option value="3">Anual (cada año)</option>
                                            <option value="4">Personalizado (cada N días)</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3" id="intervalo-container" style="display: none;">
                                        <label for="intervalo_dias" class="form-label">
                                            <i class="fas fa-hourglass-half"></i> Cada cuántos días
                                        </label>
                                        <input type="number" class="form-control-modern" id="intervalo_dias" name="intervalo_dias"
                                               min="1" max="3650" value="15" placeholder="Ej: 15">
                                        <small class="text-muted">Ej: 15 = quincenal, 45 = cada mes y medio</small>
                                    </div>

                                    <div class="col-md-4 mb-3" id="repeticiones-container" style="display: none;">
                                        <label for="num_repeticiones" class="form-label">
                                            <i class="fas fa-redo"></i> Número de Repeticiones
                                        </label>
                                        <input type="number" class="form-control-modern" id="num_repeticiones" name="num_repeticiones" 
                                               min="1" max="120" value="12" placeholder="Ej: 12">
                                        <small class="text-muted">Cuántos pagos crear ahora (vacío = indefinido: solo el primero; el resto al vencer)</small>
                                    </div>
                                </div>
                                
                                <!-- Preview de recurrencia -->
                                <div id="preview-recurrencia" class="alert-modern alert-info mb-3" style="display: none;">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Vista previa:</strong> <span id="preview-text"></span>
                                </div>
                                
                                <div class="d-flex justify-content-end mt-3">
                                    <button type="submit" class="btn-primary-custom">
                                        <i class="fas fa-credit-card"></i>Procesar Pago
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <?php if (!empty($pagos)): ?>
                            <div class="table-scroll">
                            <table class="table" width="100%">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-calendar"></i>Fecha</th>
                                        <th><i class="fas fa-calendar-check"></i>Fecha Pago</th>
                                        <th><i class="fas fa-tag"></i>Concepto</th>
                                        <th><i class="fas fa-dollar-sign"></i>Subtotal</th>
                                        <th><i class="fas fa-percent"></i>IVA (16%)</th>
                                        <th><i class="fas fa-money-bill"></i>Total</th>
                                        <th><i class="fas fa-credit-card"></i>Forma Pago</th>
                                        <th><i class="fas fa-chart-line"></i>Estatus</th>
                                        <th><i class="fas fa-sync-alt"></i>Frecuencia</th>
                                        <th><i class="fas fa-barcode"></i>ID Transacción</th>
                                        <th><i class="fas fa-hourglass-end"></i>Fecha Límite</th>
                                        <th><i class="fas fa-cog"></i>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pagos as $pago):
                                        $estatus_class = ($pago['estatus'] == 0) ? 'badge-warning' : 'badge-success';
                                        $estatus_text = ($pago['estatus'] == 1) ? 'Aprobado' : 'Pendiente';
                                        
                                        $fecha_limite_raw = $pago['fecha_limite_pago'] ?? '';
                                        $fecha_limite_valida = !empty($fecha_limite_raw) && $fecha_limite_raw !== '0000-00-00' && strtotime($fecha_limite_raw) !== false;
                                        $fecha_limite = $fecha_limite_valida ? date('d/m/Y', strtotime($fecha_limite_raw)) : 'N/A';

                                        // Formatear fecha de pago (cuando el cliente realmente pagó)
                                        $fecha_pago_raw = $pago['fecha_pago'] ?? '';
                                        if (!empty($fecha_pago_raw) && $fecha_pago_raw != '0000-00-00' && strtotime($fecha_pago_raw) !== false) {
                                            $fecha_pago = date('d/m/Y', strtotime($fecha_pago_raw));
                                        } else {
                                            if ($fecha_limite_valida) {
                                                $fecha_pago = '<span class="text-muted" style="font-style: italic;">Pendiente</span><br><small class="text-secondary-custom">Vence: ' . $fecha_limite . '</small>';
                                            } else {
                                                $fecha_pago = '<span class="text-muted" style="font-style: italic;">Pendiente</span>';
                                            }
                                        }

                                        $fecha = !empty($pago['fecha']) ? date('d/m/Y', strtotime($pago['fecha'])) : 'N/A';
                                        $formas_pago = [0 => 'Pendiente', 1 => 'Tarjeta', 2 => 'Transferencia', 3 => 'Efectivo'];
                                        $forma_pago = $formas_pago[$pago['forma_pago']] ?? 'Desconocido';
                                        
                                        // Frecuencia de pago
                                        $frecuencias = [
                                            0 => ['texto' => 'Único', 'badge' => 'badge-secondary', 'icon' => 'fa-stop-circle'],
                                            1 => ['texto' => 'Semanal', 'badge' => 'badge-info', 'icon' => 'fa-calendar-week'],
                                            2 => ['texto' => 'Mensual', 'badge' => 'badge-primary', 'icon' => 'fa-calendar-alt'],
                                            3 => ['texto' => 'Anual', 'badge' => 'badge-success', 'icon' => 'fa-calendar-check'],
                                            4 => ['texto' => 'Personalizado', 'badge' => 'badge-warning', 'icon' => 'fa-sliders-h']
                                        ];
                                        $frecuencia_pago = isset($pago['frecuencia_pago']) ? intval($pago['frecuencia_pago']) : 0;
                                        $frecuencia_info = $frecuencias[$frecuencia_pago] ?? $frecuencias[0];
                                        $intervalo_pago = isset($pago['intervalo_dias']) ? intval($pago['intervalo_dias']) : 0;
                                        if ($frecuencia_pago === 4 && $intervalo_pago > 0) {
                                            $frecuencia_info['texto'] = 'Cada ' . $intervalo_pago . ' días';
                                        }
                                        $monto_total = floatval($pago['monto']);
                                        $subtotal = $monto_total;
                                        $iva = 0;
                                        if (isset($cliente_info['facturacion']) && $cliente_info['facturacion'] == 1) {
                                            $subtotal = $monto_total / 1.16;
                                            $iva = $monto_total - $subtotal;
                                        }
                                    ?>
                                        <tr>
                                            <td><?php echo $fecha; ?></td>
                                            <td><?php echo $fecha_pago; ?></td>
                                            <td><?php echo htmlspecialchars($pago['concepto']); ?></td>
                                            <td><?php echo $pago['currency'] . ' ' . number_format($subtotal, 2); ?></td>
                                            <td><?php echo $pago['currency'] . ' ' . number_format($iva, 2); ?></td>
                                            <td class="fw-semibold"><?php echo $pago['currency'] . ' ' . number_format($monto_total, 2); ?></td>
                                            <td><?php echo $forma_pago; ?></td>
                                            <td><span class="badge-status <?php echo $estatus_class; ?>"><?php echo $estatus_text; ?></span></td>
                                            <td>
                                                <span class="badge-status <?php echo $frecuencia_info['badge']; ?>" style="font-size: 0.85em;">
                                                    <i class="fas <?php echo $frecuencia_info['icon']; ?>"></i> <?php echo $frecuencia_info['texto']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($pago['id_pago']); ?></td>
                                            <td><?php echo $fecha_limite; ?></td>
                                            <td>
                                                <?php if ($pago["estatus"] == 0): ?>
                                                    <div class="btn-group-actions">
                                                        <button class="btn-icon-sm btn-edit enviar-correo-btn"
                                                            data-id="<?php echo $pago["id"]; ?>"
                                                            data-manual="<?php echo $pago["manual"]; ?>"
                                                            data-tiposervicio="<?php echo $pago["tipo_servicio"]; ?>">
                                                            <i class="fas fa-paper-plane"></i>Enviar
                                                        </button>
                                                        <button class="btn-icon-sm btn-toggle btn-aprobarpago" 
                                                            data-id="<?php echo $pago["id"]; ?>" 
                                                            data-idservicio="<?php echo $pago['id_servicio']; ?>" 
                                                            data-tiposervicio="<?php echo $pago['tipo_servicio']; ?>">
                                                            <i class="fas fa-check-circle"></i>Aprobar
                                                        </button>
                                                        <button type="button" class="btn-icon-sm btn-danger-sm"
                                                            onclick="eliminarPagoCliente(<?php echo (int) $pago['id']; ?>)"
                                                            title="Eliminar pago">
                                                            <i class="fas fa-trash"></i>Eliminar
                                                        </button>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="btn-group-actions">
                                                        <button type="button" class="btn-icon-sm btn-danger-sm"
                                                            onclick="eliminarPagoCliente(<?php echo (int) $pago['id']; ?>)"
                                                            title="Eliminar pago">
                                                            <i class="fas fa-trash"></i>Eliminar
                                                        </button>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            </div>
                        <?php else: ?>
                            <div class="dc-empty">
                                <i class="fas fa-credit-card"></i>
                                <strong>Sin pagos</strong>
                                <p>Este cliente aún no tiene registros de pago activos.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pestaña 5: Pagos Eliminados -->
                <div class="tab-pane fade" id="eliminados" role="tabpanel">
                    <div class="modern-table">
                        <div class="dc-section-head">
                            <h4><i class="fas fa-trash-alt"></i>Pagos eliminados</h4>
                            <span class="dc-section-head__hint"><?php echo (int) $dc_count_eliminados; ?> registro(s)</span>
                        </div>
                        <?php if (!empty($pagos_eliminados)): ?>
                            <div class="alert-modern alert-warning mb-0" style="margin:12px 16px 0;border-radius:12px;">
                                <i class="fas fa-exclamation-triangle"></i>Pagos marcados como eliminados. Puedes restaurarlos desde aquí.
                            </div>
                            <div class="table-scroll">
                            <table class="table" width="100%">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-hashtag"></i>ID</th>
                                        <th><i class="fas fa-tag"></i>Tipo</th>
                                        <th><i class="fas fa-server"></i>Servicio</th>
                                        <th><i class="fas fa-file-text"></i>Concepto</th>
                                        <th><i class="fas fa-dollar-sign"></i>Monto</th>
                                        <th><i class="fas fa-calendar"></i>Fecha Creación</th>
                                        <th><i class="fas fa-calendar-check"></i>Fecha Pago</th>
                                        <th><i class="fas fa-cog"></i>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pagos_eliminados as $pago): ?>
                                        <tr style="background-color: #fffbeb;">
                                            <td><?php echo $pago['id']; ?></td>
                                            <td>
                                                <?php 
                                                if ($pago['tipo_servicio'] == 1) {
                                                    echo '<span class="badge-status badge-info">Hosting</span>';
                                                } elseif ($pago['tipo_servicio'] == 2) {
                                                    echo '<span class="badge-status badge-success">Dominio</span>';
                                                } elseif ($pago['manual'] == 1) {
                                                    echo '<span class="badge-status badge-warning">Servicio</span>';
                                                } else {
                                                    echo '<span class="badge-status badge-secondary">Otro</span>';
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($pago['nombre_servicio'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($pago['concepto']); ?></td>
                                            <td class="fw-semibold">$<?php echo number_format($pago['monto'], 2); ?></td>
                                            <td><?php echo !empty($pago['fecha']) ? date('d/m/Y', strtotime($pago['fecha'])) : '-'; ?></td>
                                            <td><?php echo !empty($pago['fecha_pago']) && $pago['fecha_pago'] !== '0000-00-00' ? date('d/m/Y', strtotime($pago['fecha_pago'])) : 'Sin pagar'; ?></td>
                                            <td>
                                                <button class="btn-icon-sm btn-restore btn-restaurar-pago" data-id="<?php echo $pago['id']; ?>" title="Restaurar pago">
                                                    <i class="fas fa-undo"></i>Restaurar
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            </div>
                        <?php else: ?>
                            <div class="dc-empty">
                                <i class="fas fa-trash-alt"></i>
                                <strong>Sin eliminados</strong>
                                <p>No hay pagos eliminados para este cliente.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($sistema === 'conlineweb'): ?>
<!-- Modal: Cambiar posición del proyecto -->
<div class="modal fade" id="modalPosicion" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-sort-numeric-down mr-2"></i>Cambiar posición del proyecto</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="posicion_proyecto_id" value="">
                <div class="mb-3">
                    <label for="nueva_posicion" class="form-label fw-semibold">Nueva posición (número entero)</label>
                    <input type="number" class="form-control-modern" id="nueva_posicion" min="0" max="999" step="1">
                    <small class="text-muted">Los proyectos se ordenan de menor a mayor posición.</small>
                </div>
                <div class="posiciones-lista">
                    <strong><i class="fas fa-chart-line"></i> Posiciones ocupadas actualmente:</strong>
                    <div id="listaPosicionesOcupadas" class="mt-2"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn-primary-custom" id="guardarPosicionBtn">Guardar posición</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Alta / edición de proyecto del cliente -->
<div class="modal fade" id="modalProyectoCliente" tabindex="-1" role="dialog" aria-labelledby="modalProyectoClienteLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalProyectoClienteLabel">Nuevo Proyecto</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="formProyectoCliente" class="needs-validation" novalidate>
                    <input type="hidden" id="id_proyecto_cliente" name="id_proyecto" value="">
                    <input type="hidden" id="cliente_proyecto" name="cliente" value="<?php echo (int) $id; ?>">

                    <fieldset class="modern-fieldset mb-3">
                        <legend><i class="fas fa-project-diagram"></i>Información del Proyecto</legend>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Cliente</label>
                                <input type="text" class="form-control-modern" value="<?php echo htmlspecialchars(trim(($dc_nombre !== '' ? $dc_nombre : ('Cliente #' . (int) $id)) . ($dc_empresa !== '' ? ' · ' . $dc_empresa : '')), ENT_QUOTES, 'UTF-8'); ?>" readonly>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="nombre_proyecto_cliente" class="form-label required-field">Nombre del Proyecto</label>
                                <input type="text" class="form-control-modern" id="nombre_proyecto_cliente" name="nombre_proyecto" maxlength="150" placeholder="Ej. Nuevo Sistema Web" required>
                                <div class="invalid-feedback">Ingresa el nombre del proyecto</div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="tipo_proyecto_cliente" class="form-label required-field">Tipo de proyecto</label>
                                <select id="tipo_proyecto_cliente" name="tipo_proyecto" class="form-control-modern" required>
                                    <?php echo adm_proyecto_tipo_options_html(0, false); ?>
                                </select>
                                <div class="invalid-feedback">Selecciona el tipo de proyecto</div>
                            </div>

                            <div class="col-12 mb-3">
                                <label for="descripcion_proyecto_cliente" class="form-label">Descripción</label>
                                <textarea class="form-control-modern" id="descripcion_proyecto_cliente" name="descripcion" rows="3" placeholder="Descripción general del proyecto (opcional)"></textarea>
                            </div>

                            <?php if ($has_descripcion_tecnica): ?>
                            <div class="col-12 mb-3">
                                <label for="descripcion_tecnica_cliente" class="form-label">Descripción técnica</label>
                                <textarea class="form-control-modern" id="descripcion_tecnica_cliente" name="descripcion_tecnica" rows="4" placeholder="Stack, arquitectura, integraciones, APIs, hosting, accesos, notas para desarrollo..."></textarea>
                            </div>
                            <?php endif; ?>

                            <div class="col-md-9 mb-3">
                                <label for="url_proyecto_cliente" class="form-label">URL del Proyecto (opcional)</label>
                                <input type="url" class="form-control-modern" id="url_proyecto_cliente" name="url_proyecto" placeholder="https://ejemplo.com/proyecto" maxlength="255">
                                <small class="form-text text-muted">Si el proyecto ya está en línea, ingresa la URL completa.</small>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label for="posicion_proyecto_cliente" class="form-label"><i class="fas fa-sort-numeric-down mr-1"></i>Posición en Web</label>
                                <input type="number" class="form-control-modern" id="posicion_proyecto_cliente" name="posicion" min="0" max="999" placeholder="0" value="0">
                                <small class="form-text text-muted">Menor = primero</small>
                            </div>

                            <div class="col-12 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" id="mostrar_chk_cliente" name="mostrar">
                                    <label class="form-check-label" for="mostrar_chk_cliente">
                                        Mostrar en la pantalla de proyectos
                                    </label>
                                </div>
                            </div>

                            <div class="col-12 mb-3">
                                <label class="form-label fw-semibold">Categorías (elige una o varias)</label>
                                <div class="tags-container" id="tags_categorias_cliente">
                                    <?php
                                    if (!empty($tags_by_group[1])) {
                                        foreach ($tags_by_group[1] as $grp) {
                                            echo '<div class="mb-3"><strong style="color:var(--primary-dark);">' . htmlspecialchars($grp['subtitulo']) . '</strong><div class="d-flex flex-wrap mt-2">';
                                            foreach ($grp['items'] as $idx => $text) {
                                                $checkboxId = 'dc_cat_' . $grp['id'] . '_' . $idx;
                                                echo '<div class="form-check form-check-inline">';
                                                echo '<input class="form-check-input tag-check-dc categoria-check" type="checkbox" id="' . $checkboxId . '" data-group="' . $grp['id'] . '" data-index="' . $idx . '" value="' . htmlspecialchars($text, ENT_QUOTES) . '">';
                                                echo '<label class="form-check-label" for="' . $checkboxId . '">' . htmlspecialchars($text) . '</label>';
                                                echo '</div>';
                                            }
                                            echo '</div></div>';
                                        }
                                    } else {
                                        echo '<div class="text-muted">No hay categorías definidas.</div>';
                                    }
                                    ?>
                                </div>
                            </div>

                            <div class="col-12 mb-3">
                                <label class="form-label fw-semibold">Tecnologías (elige una o varias)</label>
                                <div class="tags-container" id="tags_tecnologias_cliente">
                                    <?php
                                    $otherGroups = $tags_by_group;
                                    unset($otherGroups[1]);
                                    if (!empty($otherGroups)) {
                                        foreach ($otherGroups as $groups) {
                                            foreach ($groups as $grp) {
                                                echo '<div class="mb-3"><strong style="color:var(--primary-dark);">' . htmlspecialchars($grp['subtitulo']) . '</strong><div class="d-flex flex-wrap mt-2">';
                                                foreach ($grp['items'] as $idx => $text) {
                                                    $checkboxId = 'dc_tec_' . $grp['id'] . '_' . $idx;
                                                    echo '<div class="form-check form-check-inline">';
                                                    echo '<input class="form-check-input tag-check-dc tecnologia-check" type="checkbox" id="' . $checkboxId . '" data-group="' . $grp['id'] . '" data-index="' . $idx . '" value="' . htmlspecialchars($text, ENT_QUOTES) . '">';
                                                    echo '<label class="form-check-label" for="' . $checkboxId . '">' . htmlspecialchars($text) . '</label>';
                                                    echo '</div>';
                                                }
                                                echo '</div></div>';
                                            }
                                        }
                                    } else {
                                        echo '<div class="text-muted">No hay tecnologías definidas.</div>';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <div class="d-flex justify-content-between mt-2">
                        <button type="button" class="btn btn-light px-4" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn-primary-custom px-5">Guardar Proyecto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Nota de pago template -->
<div id="nota-pago-template" style="display: none;">
    <!-- contenido del template igual que antes -->
</div>

<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="vendor/jquery-easing/jquery.easing.min.js"></script>
<script src="js/sb-admin-2.min.js"></script>
<script src="vendor/datatables/jquery.dataTables.min.js"></script>
<script src="vendor/datatables/dataTables.bootstrap4.min.js"></script>

<script>
$(document).ready(function() {
    const sistemaActivo = '<?php echo $sistema; ?>';
    // Funcionalidad de mostrar/ocultar contraseñas
    $(document).on('click', '.toggle-password', function() {
        const input = $(this).closest('.input-group-modern').find('input');
        const icon = $(this).find('i');
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('bi-eye').addClass('bi-eye-slash');
        } else {
            input.attr('type', 'password');
            icon.removeClass('bi-eye-slash').addClass('bi-eye');
        }
    });

    // Funcionalidad de checkbox de facturación
    function toggleSecciones() {
        const requiereFacturacion = $('#requiereFacturacion').prop('checked');
        $('#facturacion').val(requiereFacturacion ? '1' : '0');
        $('#seccionEmpresarial, #seccionDireccion').toggle(requiereFacturacion);
        $('#facturacionToggle').toggleClass('is-on', requiereFacturacion);
    }
    
    $('#requiereFacturacion').change(toggleSecciones);
    toggleSecciones();
    
    // Siempre quitar required de constancia_fiscal
    $("#constancia_fiscal").prop('required', false);

    // Confirmación para actualizar cliente
    $('form.needs-validation').not('#formProyectoCliente').on('submit', function(e) {
        e.preventDefault();
        const form = this;
        
        const password = $('#inputPassword').val();
        const confirmPassword = $('#confirmPassword').val();
        if (password && password !== confirmPassword) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Las contraseñas no coinciden' });
            return false;
        }
        
        Swal.fire({
            title: '¿Confirmar actualización?',
            text: "¿Estás seguro de actualizar los datos del cliente?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#000147',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, actualizar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'Actualizando...', html: 'Por favor espera', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
                
                const formData = new FormData(form);
                $.ajax({
                    url: form.action,
                    type: form.method,
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (typeof response === 'string') {
                            try { response = JSON.parse(response); } catch(e) { response = {}; }
                        }
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: '¡Éxito!', text: response.message || 'Cliente actualizado correctamente' })
                                .then(() => { window.location.href = response.redirect || window.location.href; });
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: response.message || 'Ocurrió un error al actualizar.' });
                        }
                    },
                    error: function() {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Ocurrió un error al actualizar' });
                    }
                });
            }
        });
    });

    // Selectores de estado/municipio
    $('#estado').change(function() {
        const estados_id_estado = $(this).val();
        if (estados_id_estado) {
            $.ajax({
                url: 'obtener_municipios.php',
                type: 'POST',
                data: { estados_id_estado: estados_id_estado },
                success: function(data) { $('#municipio').html(data); }
            });
        } else {
            $('#municipio').html('<option value="" selected disabled>Selecciona un municipio</option>');
        }
    });
    
    const estadoSeleccionado = $('#estado').val();
    if (estadoSeleccionado) {
        $.ajax({
            url: 'obtener_municipios.php',
            type: 'POST',
            data: { estados_id_estado: estadoSeleccionado },
            success: function(data) {
                const ciudadActual = '<?php echo $cliente["ciudad"] ?? ""; ?>';
                $('#municipio').html(data);
                if (ciudadActual) $('#municipio').val(ciudadActual);
            }
        });
    }

    // Funcionalidad de Frecuencia de Pago y Preview
    function actualizarPreviewRecurrencia() {
        const frecuencia = parseInt($('#frecuencia_pago').val(), 10) || 0;
        const numRepeticiones = ($('#num_repeticiones').val() || '').toString().trim();
        const fechaLimite = $('#fecha_limite_pago').val();
        const intervaloDias = parseInt($('#intervalo_dias').val(), 10) || 0;

        if (frecuencia === 4) {
            $('#intervalo-container').slideDown();
        } else {
            $('#intervalo-container').slideUp();
        }
        
        if (frecuencia > 0 && fechaLimite) {
            if (frecuencia === 4 && intervaloDias < 1) {
                $('#preview-text').text('Indica cada cuántos días para la frecuencia personalizada.');
                $('#preview-recurrencia').slideDown();
                $('#repeticiones-container').slideDown();
                return;
            }

            const frecuenciasTxt = {
                1: 'cada semana',
                2: 'cada mes',
                3: 'cada año',
                4: 'cada ' + intervaloDias + ' día(s)'
            };
            
            const repeticionesTexto = numRepeticiones
                ? (parseInt(numRepeticiones, 10) + ' pagos (se crean todos ahora en el historial)')
                : 'de forma indefinida (solo se crea el primero; el siguiente al acercarse el vencimiento)';
            const fechaObj = new Date(fechaLimite + 'T00:00:00');
            const fechaFormateada = fechaObj.toLocaleDateString('es-MX', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            
            const previewText = `Se programará ${frecuenciasTxt[frecuencia]}, ${repeticionesTexto}. Primer vencimiento: ${fechaFormateada}. El correo/Stripe se envía solo del primer pago.`;
            
            $('#preview-text').text(previewText);
            $('#preview-recurrencia').slideDown();
            $('#repeticiones-container').slideDown();
        } else {
            $('#preview-recurrencia').slideUp();
            $('#repeticiones-container').slideUp();
            $('#intervalo-container').slideUp();
        }
    }
    
    $('#frecuencia_pago, #num_repeticiones, #fecha_limite_pago, #intervalo_dias').on('change input', actualizarPreviewRecurrencia);
    
    // Formulario de pagos
    $('#pagoForm').submit(function(e) {
        e.preventDefault();

        const frecuencia = parseInt($('#frecuencia_pago').val(), 10) || 0;
        const intervalo = parseInt($('#intervalo_dias').val(), 10) || 0;
        if (frecuencia === 4 && intervalo < 1) {
            Swal.fire('Falta dato', 'Para frecuencia personalizada indica cada cuántos días.', 'warning');
            return;
        }

        Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
        
        const formData = {
            cliente_id: $('input[name="cliente_id"]').val(),
            monto: $('#monto').val(),
            currency: $('#currency').val(),
            concepto: $('#concepto').val(),
            fecha_limite_pago: $('#fecha_limite_pago').val(),
            frecuencia_pago: $('#frecuencia_pago').val(),
            intervalo_dias: frecuencia === 4 ? intervalo : 0,
            num_repeticiones: ($('#num_repeticiones').val() || '').toString().trim() || null,
            cliente_facturacion: $('input[name="cliente_facturacion"]').val(),
            sistema: sistemaActivo
        };
        
        if (formData.cliente_facturacion == 1) {
            formData.monto = parseFloat(formData.monto) * 1.16;
        }
        
        $.ajax({
            url: 'guardar_pago.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                Swal.close();
                
                if (typeof response === 'string') {
                    try { 
                        response = JSON.parse(response); 
                    } catch(e) { 
                        console.error('Error parsing response:', e);
                        Swal.fire('Error', 'Respuesta inválida del servidor', 'error');
                        return;
                    }
                }
                
                if (response.success) {
                    Swal.fire({ 
                        icon: 'success', 
                        title: '¡Pago agregado!', 
                        text: response.message || 'El pago se agregó y el correo fue enviado correctamente.',
                        confirmButtonColor: '#000147'
                    }).then(() => location.reload());
                } else {
                    Swal.fire({ 
                        icon: 'error', 
                        title: 'Error', 
                        html: response.message || 'No se pudo guardar el pago.<br><small>' + (response.error || '') + '</small>',
                        confirmButtonColor: '#d33'
                    });
                }
            },
            error: function(xhr, status, error) { 
                Swal.close(); 
                console.error('AJAX Error:', xhr.responseText);
                let detail = error;
                const raw = (xhr.responseText || '').trim();
                if (raw) {
                    try {
                        const parsed = JSON.parse(raw);
                        detail = parsed.message || parsed.error || raw.substring(0, 300);
                    } catch (e) {
                        detail = 'El servidor no devolvió JSON. Respuesta: ' + raw.substring(0, 280).replace(/</g, '&lt;');
                    }
                }
                Swal.fire({
                    icon: 'error',
                    title: 'No se pudo guardar el pago',
                    html: detail,
                    confirmButtonColor: '#d33'
                });
            }
        });
    });

    // Enviar correo
    $(document).on('click', '.enviar-correo-btn', function() {
        const pagoId = $(this).data('id');
        const manual = $(this).data('manual');
        const tiposervicio = $(this).data('tiposervicio');
        
        Swal.fire({
            title: '¿Enviar correo?',
            text: 'Se enviará 1 correo. ¿Deseas continuar?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#000147',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, enviar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                let url = '';
                if (manual == 1) url = 'reenviar_correo_pago_pendienteManual.php';
                else if (tiposervicio == 1) url = 'reenviar_correos_hosting.php';
                else url = 'reenviar_correos_dominios.php';
                
                $.ajax({
                    url: url,
                    type: 'POST',
                    dataType: 'json',
                    data: { id: pagoId, ...(manual == 1 && { pago_id: pagoId, resend: 1 }) },
                    success: function(response) {
                        if (response.success) Swal.fire('Enviado', response.message, 'success');
                        else Swal.fire('Error', response.message, 'error');
                    },
                    error: function() { Swal.fire('Error', 'Error al conectar con el servidor', 'error'); }
                });
            }
        });
    });

    // Aprobar pago
    $(document).on('click', '.btn-aprobarpago', function() {
        const pagoId = $(this).data('id');
        const idservicio = $(this).data('idservicio');
        const tiposervicio = $(this).data('tiposervicio');
        
        Swal.fire({
            title: 'Aprobar Pago',
            text: '¿Cómo fue realizado el pago?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#000147',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Transferencia',
            cancelButtonText: 'Efectivo',
            showDenyButton: true,
            denyButtonText: 'Cancelar',
            denyButtonColor: '#6c757d'
        }).then((result) => {
            if (result.isConfirmed) procesarAprobacion(pagoId, 2, idservicio, tiposervicio);
            else if (result.dismiss === Swal.DismissReason.cancel) procesarAprobacion(pagoId, 3, idservicio, tiposervicio);
        });
    });
    
    function procesarAprobacion(idPago, formaPago, id_servicio, tipo_servicio) {
        Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
        
        const allpagos = <?php echo isset($pagosall) ? json_encode($pagosall) : '[]'; ?>;
        const pago = allpagos.find(p => p.id == idPago);
        
        if (!pago) {
            Swal.fire('Error', 'Pago no encontrado', 'error');
            return;
        }
        
        $.ajax({
            url: 'aprobar_pago.php',
            type: 'POST',
            data: {
                id: idPago,
                forma_pago: formaPago,
                id_servicio: id_servicio,
                tipo_servicio: tipo_servicio,
                pago_data: JSON.stringify(pago)
            },
            success: function(result) {
                try {
                    const response = typeof result === 'string' ? JSON.parse(result) : result;
                    if (response.success) {
                        Swal.fire({ icon: 'success', title: '¡Éxito!', text: 'El pago ha sido aprobado correctamente' })
                            .then(() => location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: response.message || 'Error al aprobar el pago' });
                    }
                } catch(e) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Error procesando la respuesta del servidor' });
                }
            },
            error: function() { Swal.fire('Error', 'Error de conexión', 'error'); }
        });
    }

    // Restaurar pago
    $(document).on('click', '.btn-restaurar-pago', function() {
        const pagoId = $(this).data('id');
        
        Swal.fire({
            title: '¿Restaurar pago?',
            text: 'El pago volverá a estar visible en la lista principal',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#000147',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, restaurar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'Restaurando...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
                $.ajax({
                    url: 'restaurar_pago.php',
                    type: 'POST',
                    data: { id: pagoId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: '¡Restaurado!', text: 'El pago ha sido restaurado correctamente' })
                                .then(() => location.reload());
                        } else {
                            Swal.fire('Error', response.message || 'No se pudo restaurar el pago', 'error');
                        }
                    },
                    error: function() { Swal.fire('Error', 'Error al restaurar el pago', 'error'); }
                });
            }
        });
    });

    // Toggle estado dominio
    window.toggleEstadoDominio = function(idDominio, estadoActual) {
        const nuevoEstado = estadoActual == 1 ? 0 : 1;
        const accion = nuevoEstado == 1 ? 'Activar' : 'Desactivar';
        
        Swal.fire({
            title: `¿${accion} dominio?`,
            text: `¿Estás seguro de ${accion.toLowerCase()} este dominio?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: nuevoEstado == 1 ? '#28a745' : '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, ' + accion.toLowerCase(),
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'actualizar_estado_dominio.php',
                    type: 'POST',
                    data: { id_dominio: idDominio, estado: nuevoEstado },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: '¡Éxito!', text: response.message }).then(() => location.reload());
                        } else {
                            Swal.fire('Error', response.message || 'No se pudo actualizar el estado', 'error');
                        }
                    },
                    error: function() { Swal.fire('Error', 'Error al actualizar el estado', 'error'); }
                });
            }
        });
    };

    window.eliminarDominioPermanente = function(idDominio, clienteId, etiqueta) {
        const nombre = etiqueta || ('#' + idDominio);
        Swal.fire({
            title: '¿Eliminar dominio?',
            html: 'Se eliminará <strong>permanentemente</strong> de la base de datos:<br><strong>' + $('<div>').text(String(nombre)).html() + '</strong><br><br>Esta acción no se puede deshacer.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then(function(result) {
            if (!result.isConfirmed) return;
            Swal.fire({ title: 'Eliminando...', allowOutsideClick: false, didOpen: function() { Swal.showLoading(); } });
            $.ajax({
                url: 'eliminar_dominio.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    id: idDominio,
                    cliente_id: clienteId,
                    permanente: 1,
                    sistema: sistemaActivo
                },
                success: function(response) {
                    if (response && response.success) {
                        Swal.fire({ icon: 'success', title: 'Eliminado', text: response.message || 'Dominio eliminado.' })
                            .then(function() { location.reload(); });
                    } else {
                        Swal.fire('Error', (response && response.message) ? response.message : 'No se pudo eliminar el dominio.', 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Error al conectar con el servidor', 'error');
                }
            });
        });
    };

    window.eliminarHostingPermanente = function(idHosting, clienteId, etiqueta) {
        const nombre = etiqueta || ('#' + idHosting);
        Swal.fire({
            title: '¿Eliminar hosting?',
            html: 'Se eliminará <strong>permanentemente</strong> de la base de datos:<br><strong>' + $('<div>').text(String(nombre)).html() + '</strong><br><br>Esta acción no se puede deshacer.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then(function(result) {
            if (!result.isConfirmed) return;
            Swal.fire({ title: 'Eliminando...', allowOutsideClick: false, didOpen: function() { Swal.showLoading(); } });
            $.ajax({
                url: 'eliminar_hosting.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    id: idHosting,
                    cliente_id: clienteId,
                    permanente: 1,
                    sistema: sistemaActivo
                },
                success: function(response) {
                    if (response && response.success) {
                        Swal.fire({ icon: 'success', title: 'Eliminado', text: response.message || 'Hosting eliminado.' })
                            .then(function() { location.reload(); });
                    } else {
                        Swal.fire('Error', (response && response.message) ? response.message : 'No se pudo eliminar el hosting.', 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Error al conectar con el servidor', 'error');
                }
            });
        });
    };

    window.eliminarPagoCliente = function(idPago) {
        Swal.fire({
            title: '¿Eliminar este pago?',
            text: 'Será marcado como eliminado y podrás restaurarlo desde la pestaña Eliminados.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then(function(result) {
            if (!result.isConfirmed) return;
            Swal.fire({ title: 'Eliminando...', allowOutsideClick: false, didOpen: function() { Swal.showLoading(); } });
            $.ajax({
                url: 'eliminar_pago.php',
                type: 'POST',
                dataType: 'json',
                data: { id: idPago, sistema: sistemaActivo },
                success: function(response) {
                    if (response && response.success) {
                        Swal.fire({ icon: 'success', title: 'Eliminado', text: response.message || 'Pago eliminado.' })
                            .then(function() { location.reload(); });
                    } else {
                        Swal.fire('Error', (response && response.message) ? response.message : 'No se pudo eliminar el pago.', 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Error al conectar con el servidor', 'error');
                }
            });
        });
    };
    
    // Transferir cliente a HostingPro
    window.transferirCliente = function(id) {
        Swal.fire({
            title: '¿Transferir cliente?',
            html: `Se transferirá el cliente <strong>#${id}</strong> de <strong>ConlineWeb</strong> a <strong>HostingPro</strong>.<br><br>
                   Se incluirán: login, datos del cliente, hostings, dominios e historial de pagos.<br>
                   El ID del cliente se conservará en ambos sistemas.<br>
                   <span class="text-warning"><i class="fas fa-exclamation-triangle"></i> Esta acción no es reversible.</span>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, transferir',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'Transfiriendo...', html: 'Por favor espera', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
                $.ajax({
                    url: 'transferir_cliente.php',
                    type: 'POST',
                    data: { id: id },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: '¡Transferido!', text: response.message }).then(() => location.reload());
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: response.message });
                        }
                    },
                    error: function() {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Error al conectar con el servidor' });
                    }
                });
            }
        });
    };

    $('#myTab a[data-toggle="tab"]').on('shown.bs.tab', function () {
        window.dispatchEvent(new Event('resize'));
    });

    <?php if ($sistema === 'conlineweb'): ?>
    // Activar pestaña Proyectos tras guardar o por hash
    if (sessionStorage.getItem('dc_open_tab') === 'proyectos' || window.location.hash === '#proyectos') {
        sessionStorage.removeItem('dc_open_tab');
        $('#proyectos-tab').tab('show');
    }

    function dcReloadProyectosTab() {
        sessionStorage.setItem('dc_open_tab', 'proyectos');
        window.location.reload();
    }

    function resetFormProyectoCliente() {
        const form = document.getElementById('formProyectoCliente');
        if (!form) return;
        form.reset();
        $(form).removeClass('was-validated');
        $('#id_proyecto_cliente').val('');
        $('#cliente_proyecto').val('<?php echo (int) $id; ?>');
        $('#posicion_proyecto_cliente').val('0');
        $('#tipo_proyecto_cliente').val('0');
        $('#mostrar_chk_cliente').prop('checked', false);
        $('.tag-check-dc').prop('checked', false);
        $('#modalProyectoClienteLabel').text('Nuevo Proyecto');
    }

    $('#filtroTipoProyectoCliente').on('change', function() {
        const tipo = String($(this).val());
        $('#tablaProyectosCliente tbody tr').each(function() {
            const rowTipo = String($(this).attr('data-tipo') || '0');
            $(this).toggle(tipo === '' || rowTipo === tipo);
        });
    });

    $(document).on('change', '.selectTipoProyecto', function() {
        const $sel = $(this);
        const idProy = $sel.data('id');
        const prev = String($sel.attr('data-prev'));
        const tipo = String($sel.val());
        if (!idProy || tipo === prev) return;
        $sel.prop('disabled', true);
        $.ajax({
            url: 'actualizar_tipo_proyecto.php',
            type: 'POST',
            dataType: 'json',
            data: { id_proyecto: idProy, tipo_proyecto: tipo }
        }).done(function(resp) {
            if (resp && resp.success) {
                $sel.attr('data-prev', tipo);
                $sel.closest('tr').attr('data-tipo', tipo);
                Swal.fire({ icon: 'success', title: 'Actualizado', text: resp.message || 'Tipo actualizado.', timer: 1200, showConfirmButton: false });
            } else {
                $sel.val(prev);
                Swal.fire('Error', (resp && resp.message) ? resp.message : 'No se pudo actualizar el tipo.', 'error');
            }
        }).fail(function() {
            $sel.val(prev);
            Swal.fire('Error', 'No se pudo actualizar el tipo.', 'error');
        }).always(function() {
            $sel.prop('disabled', false);
        });
    });

    $('#btnNuevoProyectoCliente').on('click', function() {
        resetFormProyectoCliente();
        $('#modalProyectoCliente').appendTo('body').modal('show');
    });

    // Posición
    let posicionProyectoId = null;
    $(document).on('click', '.btnPosicion', function() {
        const idProy = $(this).data('id');
        const actual = $(this).data('posicion-actual');
        posicionProyectoId = idProy;
        $('#posicion_proyecto_id').val(idProy);
        $('#nueva_posicion').val(actual);
        $.ajax({
            url: 'obtener_posiciones_ocupadas.php',
            type: 'GET',
            data: { id_proyecto: idProy },
            dataType: 'json',
            success: function(resp) {
                if (resp && resp.success && resp.posiciones) {
                    let html = '';
                    resp.posiciones.forEach(function(pos) {
                        const clase = (parseInt(pos, 10) === parseInt(actual, 10)) ? 'posicion-item actual' : 'posicion-item';
                        html += '<span class="' + clase + '">' + pos + '</span>';
                    });
                    if (html === '') html = '<span class="text-muted">No hay otras posiciones ocupadas.</span>';
                    $('#listaPosicionesOcupadas').html(html);
                } else {
                    $('#listaPosicionesOcupadas').html('<span class="text-muted">Error al cargar posiciones.</span>');
                }
            },
            error: function() {
                $('#listaPosicionesOcupadas').html('<span class="text-muted">Error al cargar posiciones.</span>');
            }
        });
        $('#modalPosicion').appendTo('body').modal('show');
    });

    $('#guardarPosicionBtn').on('click', function() {
        const nuevaPos = parseInt($('#nueva_posicion').val(), 10);
        if (isNaN(nuevaPos)) {
            Swal.fire('Error', 'Ingresa un número válido.', 'error');
            return;
        }
        if (nuevaPos < 0 || nuevaPos > 999) {
            Swal.fire('Error', 'La posición debe estar entre 0 y 999.', 'error');
            return;
        }
        const ocupadas = [];
        $('#listaPosicionesOcupadas .posicion-item').each(function() {
            if (!$(this).hasClass('actual')) {
                ocupadas.push(parseInt($(this).text(), 10));
            }
        });
        if (ocupadas.includes(nuevaPos)) {
            Swal.fire('Error', 'La posición ' + nuevaPos + ' ya está ocupada por otro proyecto. Elige otra.', 'error');
            return;
        }
        $.ajax({
            url: 'cambiar_posicion_proyecto.php',
            type: 'POST',
            data: { id_proyecto: posicionProyectoId, nueva_posicion: nuevaPos },
            dataType: 'json',
            success: function(resp) {
                if (resp && resp.success) {
                    Swal.fire('Actualizado', 'La posición ha sido cambiada correctamente.', 'success')
                        .then(function() { dcReloadProyectosTab(); });
                } else {
                    Swal.fire('Error', (resp && resp.message) ? resp.message : 'No se pudo cambiar la posición.', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Error de conexión.', 'error');
            }
        });
    });

    $(document).on('click', '.btnTogglePublicado', function() {
        const btn = $(this);
        const idProy = btn.data('id');
        if (!idProy) return;
        btn.prop('disabled', true);
        $.ajax({
            url: 'toggle_visible_proyecto.php',
            type: 'POST',
            data: { id_proyecto: idProy },
            dataType: 'json'
        }).done(function(resp) {
            if (resp && resp.success) {
                Swal.fire('Actualizado', 'El estado de publicación ha sido cambiado.', 'success')
                    .then(function() { dcReloadProyectosTab(); });
            } else {
                Swal.fire('Error', (resp && resp.message) ? resp.message : 'No se pudo cambiar el estado.', 'error');
                btn.prop('disabled', false);
            }
        }).fail(function() {
            Swal.fire('Error', 'No se pudo cambiar el estado.', 'error');
            btn.prop('disabled', false);
        });
    });

    $(document).on('click', '.btnToggleActivo', function() {
        const btn = $(this);
        const idProy = btn.data('id');
        if (!idProy) return;
        btn.prop('disabled', true);
        $.ajax({
            url: 'toggle_activo_proyecto.php',
            type: 'POST',
            data: { id_proyecto: idProy },
            dataType: 'json'
        }).done(function(resp) {
            if (resp && resp.success) {
                Swal.fire('Actualizado', 'El estado de actividad ha sido cambiado.', 'success')
                    .then(function() { dcReloadProyectosTab(); });
            } else {
                Swal.fire('Error', (resp && resp.message) ? resp.message : 'No se pudo cambiar el estado.', 'error');
                btn.prop('disabled', false);
            }
        }).fail(function() {
            Swal.fire('Error', 'No se pudo cambiar el estado.', 'error');
            btn.prop('disabled', false);
        });
    });

    $(document).on('click', '.btnToggleBloqueado', function() {
        const btn = $(this);
        const idProy = btn.data('id');
        if (!idProy) return;
        btn.prop('disabled', true);
        $.ajax({
            url: 'toggle_bloqueado_proyecto.php',
            type: 'POST',
            data: { id_proyecto: idProy },
            dataType: 'json'
        }).done(function(resp) {
            if (resp && resp.success) {
                Swal.fire('Actualizado', 'El estado de privacidad ha sido cambiado.', 'success')
                    .then(function() { dcReloadProyectosTab(); });
            } else {
                Swal.fire('Error', (resp && resp.message) ? resp.message : 'No se pudo cambiar el estado de bloqueo.', 'error');
                btn.prop('disabled', false);
            }
        }).fail(function() {
            Swal.fire('Error', 'No se pudo cambiar el estado de bloqueo.', 'error');
            btn.prop('disabled', false);
        });
    });

    $(document).on('click', '.btnEliminarProyecto', function() {
        const btn = $(this);
        const idProy = btn.data('id');
        const nombre = btn.data('nombre') || ('#' + idProy);
        if (!idProy) return;
        Swal.fire({
            title: '¿Eliminar proyecto?',
            html: 'Se dará de baja permanentemente <strong>' + $('<div>').text(String(nombre)).html() + '</strong>. Esta acción no se puede deshacer.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then(function(result) {
            if (!result.isConfirmed) return;
            btn.prop('disabled', true);
            $.ajax({
                url: 'eliminar_proyecto.php',
                type: 'POST',
                data: { id_proyecto: idProy },
                dataType: 'json'
            }).done(function(resp) {
                if (resp && resp.success) {
                    Swal.fire({ icon: 'success', title: 'Eliminado', text: resp.message || 'Proyecto eliminado.' })
                        .then(function() { dcReloadProyectosTab(); });
                } else {
                    Swal.fire('Error', (resp && resp.message) ? resp.message : 'No se pudo eliminar el proyecto.', 'error');
                    btn.prop('disabled', false);
                }
            }).fail(function() {
                Swal.fire('Error', 'No se pudo eliminar el proyecto.', 'error');
                btn.prop('disabled', false);
            });
        });
    });

    $(document).on('click', '.btnEditarProyectoCliente', function() {
        const idProyecto = $(this).data('id');
        resetFormProyectoCliente();
        $('#modalProyectoClienteLabel').text('Editar Proyecto');
        $('#id_proyecto_cliente').val(idProyecto);
        $.ajax({
            url: 'obtener_proyecto.php',
            type: 'GET',
            data: { id_proyecto: idProyecto },
            dataType: 'json',
            success: function(resp) {
                if (resp && resp.success && resp.data) {
                    const p = resp.data;
                    if (parseInt(p.id_cliente, 10) !== <?php echo (int) $id; ?>) {
                        Swal.fire('Error', 'Este proyecto no pertenece al cliente actual.', 'error');
                        return;
                    }
                    $('#nombre_proyecto_cliente').val(p.nombre_proyecto || '');
                    $('#tipo_proyecto_cliente').val(String(p.tipo_proyecto != null ? p.tipo_proyecto : 0));
                    $('#descripcion_proyecto_cliente').val(p.descripcion || '');
                    $('#descripcion_tecnica_cliente').val(p.descripcion_tecnica || '');
                    $('#url_proyecto_cliente').val(p.url || '');
                    $('#posicion_proyecto_cliente').val(p.posicion || '0');
                    $('#mostrar_chk_cliente').prop('checked', parseInt(p.mostrar, 10) === 1);
                    $('.tag-check-dc').prop('checked', false);
                    if (p.tags && Array.isArray(p.tags)) {
                        p.tags.forEach(function(t) {
                            const cls = (t.tipo === 'categoria') ? '.categoria-check' : '.tecnologia-check';
                            const selector = '.tag-check-dc' + cls + '[data-group="' + t.tag_group_id + '"][data-index="' + t.tag_index + '"]';
                            $(selector).prop('checked', true);
                        });
                    }
                    $('#modalProyectoCliente').appendTo('body').modal('show');
                } else {
                    Swal.fire('Error', (resp && resp.message) ? resp.message : 'No se pudo obtener el proyecto.', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'No se pudo obtener el proyecto.', 'error');
            }
        });
    });

    $('#formProyectoCliente').on('submit', function(e) {
        e.preventDefault();
        const form = this;
        if (form.checkValidity() === false) {
            e.stopPropagation();
            $(form).addClass('was-validated');
            return;
        }
        $(form).find('input[name^="tag_"], input[name="tag_count"]').remove();
        let idx = 0;
        $('.tag-check-dc:checked').each(function() {
            const group = $(this).attr('data-group');
            const index = $(this).attr('data-index');
            const tipo = $(this).hasClass('categoria-check') ? 'categoria' : 'tecnologia';
            $(form).append('<input type="hidden" name="tag_' + idx + '_group" value="' + group + '">');
            $(form).append('<input type="hidden" name="tag_' + idx + '_index" value="' + index + '">');
            $(form).append('<input type="hidden" name="tag_' + idx + '_tipo" value="' + tipo + '">');
            idx++;
        });
        $(form).append('<input type="hidden" name="tag_count" value="' + idx + '">');
        const datos = $(form).serialize();
        $.ajax({
            url: 'guardar_proyecto.php',
            type: 'POST',
            data: datos,
            dataType: 'json',
            success: function(resp) {
                $(form).find('input[name^="tag_"], input[name="tag_count"]').remove();
                if (resp && resp.success) {
                    Swal.fire({ icon: 'success', title: '¡Éxito!', text: resp.message || 'Proyecto guardado.' })
                        .then(function() {
                            dcReloadProyectosTab();
                        });
                } else {
                    Swal.fire('Error', (resp && resp.message) ? resp.message : 'No se pudo guardar el proyecto.', 'error');
                }
            },
            error: function(xhr) {
                let msg = 'No se pudo guardar el proyecto.';
                if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                Swal.fire('Error', msg, 'error');
            }
        });
    });
    <?php endif; ?>

    // Toggle estado hosting
    window.toggleEstadoHosting = function(idHosting, estadoActual) {
        const nuevoEstado = estadoActual == 1 ? 0 : 1;
        const accion = nuevoEstado == 1 ? 'Activar' : 'Desactivar';
        
        Swal.fire({
            title: `¿${accion} hosting?`,
            text: `¿Estás seguro de ${accion.toLowerCase()} este servicio de hosting?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: nuevoEstado == 1 ? '#28a745' : '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, ' + accion.toLowerCase(),
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'actualizar_estado_hosting.php',
                    type: 'POST',
                    data: { id_hosting: idHosting, estado: nuevoEstado },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: '¡Éxito!', text: response.message }).then(() => location.reload());
                        } else {
                            Swal.fire('Error', response.message || 'No se pudo actualizar el estado', 'error');
                        }
                    },
                    error: function() { Swal.fire('Error', 'Error al actualizar el estado', 'error'); }
                });
            }
        });
    };
});
</script>
<?php if ($sistema === 'conlineweb'): ?>
<?php require_once __DIR__ . '/includes/adm_proyecto_preview.php'; ?>
<?php endif; ?>
</body>
</html>