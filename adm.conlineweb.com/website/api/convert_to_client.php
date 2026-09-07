<?php
/**
 * Convierte un lead web en cliente del portal.
 * Inserta en `clientes` y luego en `login` con el mismo `id`.
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/auth_middleware.php';
require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_config.php';
require_once dirname(__DIR__, 2) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__, 2) . '/website/includes/leads_helpers.php';

cw_hub_migrate($conn);

function cw_web_convert_fail(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    cw_web_convert_fail('Método no permitido', 405);
}

$user = getAuthenticatedUser();
$uid = (int) ($user['id'] ?? $_SESSION['uid'] ?? 0);
$leadId = (int) ($_POST['lead_id'] ?? 0);

if ($leadId <= 0) {
    cw_web_convert_fail('Lead inválido');
}

$stmt = $conn->prepare(
    "SELECT id, nombre, apellido, correo, telefono, empresa, pipeline_estado, notas
     FROM leads
     WHERE id = ? AND origen_web = 1 AND eliminado = 0
     LIMIT 1"
);
if (!$stmt) {
    cw_web_convert_fail('Error al consultar el lead', 500);
}
$stmt->bind_param('i', $leadId);
$stmt->execute();
$lead = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$lead) {
    cw_web_convert_fail('Lead no encontrado');
}

$nombre = trim((string) ($lead['nombre'] ?? ''));
$apellido = trim((string) ($lead['apellido'] ?? ''));
$correo = strtolower(trim((string) ($lead['correo'] ?? '')));
$telefono = trim((string) ($lead['telefono'] ?? ''));
$empresa = trim((string) ($lead['empresa'] ?? ''));
$nombreContacto = trim($nombre . ($apellido !== '' ? ' ' . $apellido : ''));

if ($nombreContacto === '') {
    cw_web_convert_fail('El lead no tiene nombre para registrar el cliente');
}
if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    cw_web_convert_fail('El lead necesita un correo válido para crear el acceso');
}

// ¿Ya convertido por id_lead?
$chk = $conn->prepare('SELECT id FROM clientes WHERE id_lead = ? AND eliminado = 0 LIMIT 1');
if ($chk) {
    $chk->bind_param('i', $leadId);
    $chk->execute();
    $existLead = $chk->get_result()->fetch_assoc();
    $chk->close();
    if ($existLead) {
        cw_web_convert_fail('Este lead ya está vinculado al cliente #' . (int) $existLead['id']);
    }
}

// ¿Correo ya en clientes o login?
$chkMail = $conn->prepare('SELECT id FROM clientes WHERE correo = ? AND eliminado = 0 LIMIT 1');
if ($chkMail) {
    $chkMail->bind_param('s', $correo);
    $chkMail->execute();
    $existCli = $chkMail->get_result()->fetch_assoc();
    $chkMail->close();
    if ($existCli) {
        cw_web_convert_fail('Ya existe un cliente con ese correo (ID #' . (int) $existCli['id'] . ')');
    }
}

$chkLogin = $conn->prepare('SELECT id FROM login WHERE usuario = ? LIMIT 1');
if ($chkLogin) {
    $chkLogin->bind_param('s', $correo);
    $chkLogin->execute();
    $existLogin = $chkLogin->get_result()->fetch_assoc();
    $chkLogin->close();
    if ($existLogin) {
        cw_web_convert_fail('Ya existe un acceso (login) con ese correo');
    }
}

$plainPass = substr(bin2hex(random_bytes(8)), 0, 10);
$passMd5 = md5($plainPass);
$now = date('Y-m-d H:i:s');
$display = 'none';
$especificacion = 'Cliente desde lead web #' . $leadId;
$empty = '';
$facturacion = 0;
$actualizado = 0;
$actualizarCorreo = 0;
$eliminado = 0;
$correoPendiente = '';
$pais = 'México';

$conn->begin_transaction();

try {
    $insCli = $conn->prepare(
        'INSERT INTO clientes (
            nombre_contacto, empresa, correo, telefono,
            especificacion, rsocial, rfc, calle, next, nint, col, cp,
            pais, estado, ciudad, display, constancia_situacion_fiscal,
            facturacion, actualizado, correo_pendiente_actualizar, actualizar_correo,
            eliminado, id_lead, usuario_registro
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$insCli) {
        throw new RuntimeException('No se pudo preparar el alta de cliente: ' . $conn->error);
    }

    $insCli->bind_param(
        'sssssssssssssssssiisiiii',
        $nombreContacto,
        $empresa,
        $correo,
        $telefono,
        $especificacion,
        $empty,
        $empty,
        $empty,
        $empty,
        $empty,
        $empty,
        $empty,
        $pais,
        $empty,
        $empty,
        $display,
        $empty,
        $facturacion,
        $actualizado,
        $correoPendiente,
        $actualizarCorreo,
        $eliminado,
        $leadId,
        $uid
    );

    if (!$insCli->execute()) {
        throw new RuntimeException('Error al crear cliente: ' . $insCli->error);
    }
    $clienteId = (int) $insCli->insert_id;
    $insCli->close();

    if ($clienteId <= 0) {
        throw new RuntimeException('No se obtuvo el ID del cliente');
    }

    // Mismo ID en login (patrón del sistema)
    $hasTipo = false;
    $colTipo = $conn->query("SHOW COLUMNS FROM login LIKE 'id_tipo_usuario'");
    if ($colTipo && $colTipo->num_rows > 0) {
        $hasTipo = true;
    }

    if ($hasTipo) {
        $tipoCliente = 0;
        $insLogin = $conn->prepare(
            'INSERT INTO login (id, usuario, contrasena, contrasena_normal, id_tipo_usuario)
             VALUES (?, ?, ?, ?, ?)'
        );
        if (!$insLogin) {
            throw new RuntimeException('No se pudo preparar el acceso: ' . $conn->error);
        }
        $insLogin->bind_param('isssi', $clienteId, $correo, $passMd5, $plainPass, $tipoCliente);
    } else {
        $insLogin = $conn->prepare(
            'INSERT INTO login (id, usuario, contrasena, contrasena_normal) VALUES (?, ?, ?, ?)'
        );
        if (!$insLogin) {
            throw new RuntimeException('No se pudo preparar el acceso: ' . $conn->error);
        }
        $insLogin->bind_param('isss', $clienteId, $correo, $passMd5, $plainPass);
    }

    if (!$insLogin->execute()) {
        throw new RuntimeException('Error al crear acceso (login): ' . $insLogin->error);
    }
    $insLogin->close();

    // Actualizar lead: estatus pipeline + nota
    $notasArr = json_decode((string) ($lead['notas'] ?? '[]'), true);
    if (!is_array($notasArr)) {
        $notasArr = [];
    }
    $notasArr[] = [
        'nota' => 'Convertido a cliente #' . $clienteId . ' (portal). Acceso creado con correo ' . $correo . '.',
        'fecha' => $now,
        'usuario_id' => $uid,
        'tipo' => 'gestion',
    ];
    $notasJson = json_encode($notasArr, JSON_UNESCAPED_UNICODE);
    $pipeline = 'cierre';

    $updLead = $conn->prepare(
        'UPDATE leads
         SET pipeline_estado = ?, notas = ?, ultima_interaccion = ?
         WHERE id = ? AND origen_web = 1'
    );
    if ($updLead) {
        $updLead->bind_param('sssi', $pipeline, $notasJson, $now, $leadId);
        $updLead->execute();
        $updLead->close();
    }

    $act = $conn->prepare(
        "INSERT INTO cw_lead_actividades (lead_id, tipo, descripcion, usuario_id, created_at)
         VALUES (?, 'seguimiento', ?, ?, ?)"
    );
    if ($act) {
        $desc = 'Lead convertido a cliente #' . $clienteId . ' (login y clientes con el mismo ID).';
        $act->bind_param('isis', $leadId, $desc, $uid, $now);
        $act->execute();
        $act->close();
    }

    if (!$conn->commit()) {
        throw new RuntimeException('No se pudo confirmar la conversión');
    }
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
    }
    cw_web_convert_fail($e->getMessage(), 500);
}

echo json_encode([
    'success' => true,
    'message' => 'Cliente creado correctamente',
    'cliente_id' => $clienteId,
    'login_id' => $clienteId,
    'correo' => $correo,
    'usuario' => $correo,
    'contrasena' => $plainPass,
    'nombre_contacto' => $nombreContacto,
    'empresa' => $empresa,
    'detalle_url' => '/detalle_cliente.php?id=' . $clienteId,
], JSON_UNESCAPED_UNICODE);
