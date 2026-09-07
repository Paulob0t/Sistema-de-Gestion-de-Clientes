<?php
/**
 * Tickets del cliente (tabla solicitudes): listar, crear y alerta de confirmación.
 * Misma lógica/alerta que el alta desde Mis Tickets del portal.
 */

/**
 * @return array{pendientes:int,en_proceso:int,finalizados:int,abiertos:int,total:int}
 */
function cw_cliente_tickets_stats(mysqli $conn, int $clienteId): array
{
    $out = ['pendientes' => 0, 'en_proceso' => 0, 'finalizados' => 0, 'abiertos' => 0, 'total' => 0];
    if ($clienteId <= 0) {
        return $out;
    }
    $stmt = $conn->prepare("SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN LOWER(estado) = 'pendiente' THEN 1 ELSE 0 END) AS pendientes,
        SUM(CASE WHEN LOWER(REPLACE(estado,' ','')) IN ('enproceso','en_proceso') THEN 1 ELSE 0 END) AS en_proceso,
        SUM(CASE WHEN LOWER(estado) = 'finalizado' THEN 1 ELSE 0 END) AS finalizados,
        SUM(CASE WHEN LOWER(estado) != 'finalizado' THEN 1 ELSE 0 END) AS abiertos
        FROM solicitudes WHERE id_cliente = ?");
    if (!$stmt) {
        return $out;
    }
    $stmt->bind_param('i', $clienteId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    foreach ($out as $k => $_) {
        $out[$k] = (int) ($row[$k] ?? 0);
    }
    return $out;
}

/**
 * @return list<array<string,mixed>>
 */
function cw_cliente_tickets_list(mysqli $conn, int $clienteId, string $filtro = 'abiertos', int $limit = 15): array
{
    if ($clienteId <= 0) {
        return [];
    }
    $limit = max(1, min(50, $limit));
    $where = 'id_cliente = ?';
    if ($filtro === 'pendientes') {
        $where .= " AND LOWER(estado) = 'pendiente'";
    } elseif ($filtro === 'abiertos') {
        $where .= " AND LOWER(estado) != 'finalizado'";
    } elseif ($filtro === 'finalizados') {
        $where .= " AND LOWER(estado) = 'finalizado'";
    }

    $sql = "SELECT id, titulo, estado, prioridad, fecha_solicitud, fecha_lim
        FROM solicitudes WHERE {$where}
        ORDER BY fecha_solicitud DESC LIMIT {$limit}";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $clienteId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

/**
 * Texto de confirmación (equivalente al SweetAlert de tickets.php).
 */
function cw_cliente_tickets_confirm_message(int $ticketId, int $pendingAhead): string
{
    $wa = 'https://wa.me/524771181285';
    return "✅ **¡Ticket creado!** (#{$ticketId})\n\n"
        . "Tu solicitud quedó registrada. Actualmente hay **{$pendingAhead} tickets pendientes** antes de iniciar el trámite.\n"
        . "Plazo estimado: **4 días**.\n\n"
        . "Si es urgente o tienes dudas: WhatsApp {$wa} (+52 477 118 1285).\n"
        . "Te enviamos el resumen por correo. Seguimiento en **Mis Tickets**.";
}

/**
 * Crea un ticket para el cliente (mismo INSERT / alerta que el portal).
 *
 * @param array{id_proyecto?:?int,images?:array,files?:array,origen?:string,nombre_msj?:string} $opts
 * @return array{ok:bool,ticket_id?:int,pending_ahead?:int,message?:string,email_sent?:bool}
 */
function cw_cliente_ticket_create(
    mysqli $conn,
    int $clienteId,
    string $titulo,
    string $descripcionTexto,
    string $prioridad = 'Media',
    array $opts = []
): array {
    $titulo = trim($titulo);
    $descripcionTexto = trim($descripcionTexto);
    $prioridad = trim($prioridad);

    if ($clienteId <= 0) {
        return ['ok' => false, 'message' => 'Cliente no válido.'];
    }
    if (mb_strlen($titulo) < 5) {
        return ['ok' => false, 'message' => 'El título debe tener al menos 5 caracteres.'];
    }
    if (mb_strlen($descripcionTexto) < 10) {
        return ['ok' => false, 'message' => 'La descripción debe tener al menos 10 caracteres.'];
    }

    $prioMap = [
        'alta' => 'Alta', 'high' => 'Alta', 'urgente' => 'Alta',
        'media' => 'Media', 'normal' => 'Media', 'medium' => 'Media',
        'baja' => 'Baja', 'low' => 'Baja',
    ];
    $prioKey = mb_strtolower($prioridad);
    if (isset($prioMap[$prioKey])) {
        $prioridad = $prioMap[$prioKey];
    }
    if (!in_array($prioridad, ['Alta', 'Media', 'Baja'], true)) {
        return ['ok' => false, 'message' => 'Prioridad inválida. Usa Alta, Media o Baja.'];
    }

    $idProyecto = isset($opts['id_proyecto']) ? (int) $opts['id_proyecto'] : 0;
    if ($idProyecto > 0) {
        $chk = $conn->prepare('SELECT id_proyecto FROM proyectos WHERE id_proyecto = ? AND id_cliente = ? LIMIT 1');
        if ($chk) {
            $chk->bind_param('ii', $idProyecto, $clienteId);
            $chk->execute();
            if (!$chk->get_result()->fetch_assoc()) {
                $chk->close();
                return ['ok' => false, 'message' => 'El proyecto no existe o no te pertenece.'];
            }
            $chk->close();
        }
    } else {
        $idProyecto = 0;
    }

    $images = $opts['images'] ?? [];
    $files = $opts['files'] ?? [];
    $descripcionJson = json_encode([
        'text' => $descripcionTexto,
        'images' => is_array($images) ? $images : [],
        'files' => is_array($files) ? $files : [],
    ], JSON_UNESCAPED_UNICODE);

    $fechaActual = date('Y-m-d H:i:s');
    $dupLimit = date('Y-m-d H:i:s', time() - 30);
    $dup = $conn->prepare('SELECT id FROM solicitudes WHERE id_cliente = ? AND titulo = ? AND descripcion = ? AND fecha_solicitud >= ? LIMIT 1');
    if ($dup) {
        $dup->bind_param('isss', $clienteId, $titulo, $descripcionJson, $dupLimit);
        $dup->execute();
        if ($dup->get_result()->fetch_assoc()) {
            $dup->close();
            return ['ok' => false, 'message' => 'Parece que ya enviaste este ticket recientemente. Espera unos segundos.'];
        }
        $dup->close();
    }

    $nombreMsj = (string) ($opts['nombre_msj'] ?? ($opts['origen'] ?? 'Chat Portal'));
    $hasNombreMsj = false;
    $colCheck = @$conn->query("SHOW COLUMNS FROM solicitudes LIKE 'nombreMSJ'");
    if ($colCheck && $colCheck->num_rows > 0) {
        $hasNombreMsj = true;
    }

    $tituloEsc = $conn->real_escape_string($titulo);
    $descEsc = $conn->real_escape_string($descripcionJson);
    $prioEsc = $conn->real_escape_string($prioridad);
    $fechaEsc = $conn->real_escape_string($fechaActual);
    $projSql = $idProyecto > 0 ? (string) (int) $idProyecto : 'NULL';
    $msjSql = $hasNombreMsj ? (", '" . $conn->real_escape_string($nombreMsj) . "'") : '';
    $msjCol = $hasNombreMsj ? ', nombreMSJ' : '';
    $query = "INSERT INTO solicitudes (
        id_cliente, id_proyecto, titulo, descripcion, fecha_solicitud, estado, prioridad,
        fecha_lim, fecha_termina, repetir, fecha_repeticion{$msjCol}
    ) VALUES (
        " . (int) $clienteId . ", {$projSql}, '{$tituloEsc}', '{$descEsc}', '{$fechaEsc}', 'Pendiente', '{$prioEsc}',
        '0000-00-00 00:00:00', '0000-00-00 00:00:00', 0, '0000-00-00 00:00:00'{$msjSql}
    )";

    if (!$conn->query($query)) {
        return ['ok' => false, 'message' => 'Error al crear el ticket: ' . $conn->error];
    }
    $ticketId = (int) $conn->insert_id;

    if ($ticketId <= 0) {
        return ['ok' => false, 'message' => 'No se obtuvo el ID del ticket.'];
    }

    $pendingAhead = 0;
    $pq = $conn->prepare("SELECT COUNT(*) AS cnt FROM solicitudes WHERE LOWER(estado) = 'pendiente' AND fecha_solicitud < ?");
    if ($pq) {
        $pq->bind_param('s', $fechaActual);
        $pq->execute();
        $pendingAhead = (int) (($pq->get_result()->fetch_assoc()['cnt'] ?? 0));
        $pq->close();
    }

    $emailSent = cw_cliente_ticket_send_created_email(
        $conn,
        $clienteId,
        $ticketId,
        $titulo,
        $descripcionTexto,
        $prioridad,
        $pendingAhead
    );

    return [
        'ok' => true,
        'ticket_id' => $ticketId,
        'pending_ahead' => $pendingAhead,
        'email_sent' => $emailSent,
        'message' => cw_cliente_tickets_confirm_message($ticketId, $pendingAhead),
    ];
}

function cw_cliente_ticket_phpmailer_bootstrap(): bool
{
    $candidates = [
        dirname(__DIR__) . '/PHPMailer/src',
        dirname(__DIR__, 2) . '/cliente.conlineweb.com/PHPMailer/src',
        dirname(__DIR__, 2) . '/adm.conlineweb.com/PHPMailer/src',
    ];
    foreach ($candidates as $dir) {
        if (is_file($dir . '/PHPMailer.php')) {
            require_once $dir . '/Exception.php';
            require_once $dir . '/PHPMailer.php';
            require_once $dir . '/SMTP.php';
            return true;
        }
    }
    return false;
}

/**
 * Misma alerta por correo que procesar_nuevo_ticket.php del portal.
 */
function cw_cliente_ticket_send_created_email(
    mysqli $conn,
    int $clienteId,
    int $ticketId,
    string $titulo,
    string $descripcionTexto,
    string $prioridad,
    int $pendingAhead
): bool {
    try {
        $clienteEmail = null;
        $clienteNombre = '';
        $clienteEmpresa = '';
        $cli = $conn->prepare('SELECT correo, nombre_contacto, empresa FROM clientes WHERE id = ? LIMIT 1');
        if ($cli) {
            $cli->bind_param('i', $clienteId);
            $cli->execute();
            if ($row = $cli->get_result()->fetch_assoc()) {
                $clienteEmail = $row['correo'] ?? null;
                $clienteNombre = (string) ($row['nombre_contacto'] ?? '');
                $clienteEmpresa = (string) ($row['empresa'] ?? '');
            }
            $cli->close();
        }
        if (!$clienteEmail || !filter_var($clienteEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        if (!cw_cliente_ticket_phpmailer_bootstrap()) {
            error_log('cw_cliente_ticket_send_created_email: PHPMailer no encontrado');
            return false;
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $correoRemitente = 'servicios@conlineweb.com';
        $mail->isSMTP();
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        $mail->Host = 'smtp.gmail.com';
        $mail->Username = $correoRemitente;
        $mail->Password = 'wcglkgcxfebsauqo';
        $mail->SMTPDebug = 0;
        $mail->setFrom($correoRemitente, 'CONLINEWEB');
        $mail->addAddress($clienteEmail);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = "¡Ticket creado! - #{$ticketId}";

        require_once dirname(__DIR__, 2) . '/includes/cw_email_brand.php';

        $dias = 4;
        $nombre = htmlspecialchars($clienteNombre !== '' ? $clienteNombre : $clienteEmpresa, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $tituloH = htmlspecialchars($titulo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $prioH = htmlspecialchars($prioridad, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $descH = nl2br(htmlspecialchars($descripcionTexto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

        $content = cw_email_p('Hola <strong>' . $nombre . '</strong>,')
            . cw_email_alert(
                'Tu solicitud fue registrada. Actualmente hay <strong>' . (int) $pendingAhead . ' ticket(s) pendientes</strong> por delante en la cola de atención.',
                'success'
            )
            . cw_email_card(
                '<p style="margin:0 0 10px;"><span style="display:inline-block;padding:4px 10px;border-radius:999px;background:#fef3c7;color:#92400e;font-size:11px;font-weight:700;">Pendiente</span> '
                . '<span style="display:inline-block;padding:4px 10px;border-radius:999px;background:#fee2e2;color:#991b1b;font-size:11px;font-weight:700;margin-left:6px;">Prioridad: ' . $prioH . '</span></p>'
                . '<p style="margin:0 0 8px;"><strong style="color:#000147;">Título</strong><br>' . $tituloH . '</p>'
                . '<p style="margin:0;"><strong style="color:#000147;">Descripción</strong><br><span style="display:block;margin-top:6px;padding:12px;background:#f8fafc;border-radius:8px;font-size:13px;">' . $descH . '</span></p>',
                'Detalle del ticket #' . (int) $ticketId
            )
            . cw_email_p('El procesamiento se realizará en un plazo máximo de <strong>' . (int) $dias . ' días hábiles</strong>.')
            . cw_email_cta('https://wa.me/524771181285', 'Contactar por WhatsApp', 'whatsapp')
            . cw_email_alert('Si tu solicitud genera algún cargo adicional, primero te contactaremos para confirmar el monto.', 'warning');

        $mail->Body = cw_email_wrap([
            'title' => '¡Ticket creado!',
            'content' => $content,
            'badge' => 'Soporte #' . (int) $ticketId,
            'badge_variant' => 'success',
            'signature_team' => 'Equipo de CONLINEWEB',
        ]);

        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('cw_cliente_ticket_send_created_email: ' . $e->getMessage());
        return false;
    }
}
