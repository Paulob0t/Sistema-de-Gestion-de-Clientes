<?php
// Envío de correo de finalización de ticket al jefe/admin usando PHPMailer (SMTP)
// Uso: enviar_correo_finalizacion($conexion, $ticketId): bool

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Localizar PHPMailer en posibles rutas (prioriza carpeta raíz del proyecto)
$pmPaths = [
    __DIR__.'/PHPMailer/src',                // solicitudes/PHPMailer/src
    __DIR__.'/../PHPMailer/src',             // raiz/PHPMailer/src (más probable)
    dirname(__DIR__).'/PHPMailer/src',       // por si __DIR__/.. resuelve distinto
    (isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') : '').'/PHPMailer/src',
];
$pmFound = null;
foreach ($pmPaths as $p) {
    if ($p && file_exists($p.'/PHPMailer.php') && file_exists($p.'/SMTP.php') && file_exists($p.'/Exception.php')) {
        $pmFound = $p; break;
    }
}
if (!$pmFound) {
    $msg = "PHPMailer missing in searched paths: ".implode(' | ', $pmPaths);
    $GLOBALS['ENVIAR_CORREO_FIN_ERROR'] = $msg;
    $GLOBALS['ENVIAR_CORREO_FIN_DEBUG'] = ['pmPaths'=>$pmPaths];
    @file_put_contents(__DIR__.'/email_errors.log', date('c')." $msg\n", FILE_APPEND);
    if (!function_exists('enviar_correo_finalizacion')) {
        function enviar_correo_finalizacion(mysqli $conexion, int $ticketId): bool { return false; }
    }
    return;
}

require_once $pmFound.'/Exception.php';
require_once $pmFound.'/PHPMailer.php';
require_once $pmFound.'/SMTP.php';
$GLOBALS['PM_PATH_FIN'] = $pmFound;

if (!function_exists('enviar_correo_finalizacion')) {
    function enviar_correo_finalizacion(mysqli $conexion, int $ticketId): bool {
        @file_put_contents(__DIR__.'/email_debug.log', date('c')." [FUNC] Iniciando enviar_correo_finalizacion para ticket #$ticketId\n", FILE_APPEND);
        
        // Obtener información básica del ticket primero
        $stmt = $conexion->prepare("SELECT * FROM solicitudes WHERE id = ? LIMIT 1");
        
        if (!$stmt) {
            $GLOBALS['ENVIAR_CORREO_FIN_ERROR'] = "Error preparando consulta del ticket: " . $conexion->error;
            return false;
        }
        
        $stmt->bind_param('i', $ticketId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$result || !$result->num_rows) {
            $GLOBALS['ENVIAR_CORREO_FIN_ERROR'] = "No se encontró el ticket con ID $ticketId";
            $stmt->close();
            return false;
        }
        
        $ticket = $result->fetch_assoc();
        $stmt->close();
        
        // Obtener datos adicionales por separado
        $agente_nombre = 'No asignado';
        if (!empty($ticket['usuario_asignado'])) {
            $agenteStmt = $conexion->prepare("SELECT nombre FROM agentes WHERE id = ? LIMIT 1");
            if ($agenteStmt) {
                $agenteStmt->bind_param('i', $ticket['usuario_asignado']);
                $agenteStmt->execute();
                $agenteResult = $agenteStmt->get_result();
                if ($agenteResult && $agente = $agenteResult->fetch_assoc()) {
                    $agente_nombre = $agente['nombre'];
                }
                $agenteStmt->close();
            }
        }
        
        $empresa_nombre = 'No especificada';
        $cliente_nombre = 'No especificado';
        if (!empty($ticket['id_cliente'])) {
            $clienteStmt = $conexion->prepare("SELECT empresa, nombre_contacto FROM clientes WHERE id = ? LIMIT 1");
            if ($clienteStmt) {
                $clienteStmt->bind_param('i', $ticket['id_cliente']);
                $clienteStmt->execute();
                $clienteResult = $clienteStmt->get_result();
                if ($clienteResult && $cliente = $clienteResult->fetch_assoc()) {
                    $empresa_nombre = $cliente['empresa'] ?? 'No especificada';
                    $cliente_nombre = $cliente['nombre_contacto'] ?? 'No especificado';
                }
                $clienteStmt->close();
            }
        }
        
        $nombre_proyecto = 'No especificado';
        if (!empty($ticket['id_proyecto'])) {
            $proyectoStmt = $conexion->prepare("SELECT nombre_proyecto FROM proyectos WHERE id_proyecto = ? LIMIT 1");
            if ($proyectoStmt) {
                $proyectoStmt->bind_param('i', $ticket['id_proyecto']);
                $proyectoStmt->execute();
                $proyectoResult = $proyectoStmt->get_result();
                if ($proyectoResult && $proyecto = $proyectoResult->fetch_assoc()) {
                    $nombre_proyecto = $proyecto['nombre_proyecto'] ?? 'No especificado';
                }
                $proyectoStmt->close();
            }
        }
        
        // Lista de destinatarios para notificación de finalización
        $admins = [
            [
                'email' => 'info@conlineweb.com',
                'nombre' => 'Jefe - Servicios ConlineWeb'
            ],
            [
                'email' => 'proyectos@conlineweb.com',
                'nombre' => 'Proyectos ConlineWeb'
            ]
        ];
        
        if (empty($admins)) {
            $GLOBALS['ENVIAR_CORREO_FIN_ERROR'] = "No hay destinatarios válidos configurados";
            @file_put_contents(__DIR__.'/email_debug.log', date('c')." [FUNC] ERROR: No hay destinatarios válidos\n", FILE_APPEND);
            return false;
        }
        
        @file_put_contents(__DIR__.'/email_debug.log', date('c')." [FUNC] Destinatarios encontrados: " . count($admins) . "\n", FILE_APPEND);
        foreach ($admins as $i => $admin) {
            @file_put_contents(__DIR__.'/email_debug.log', date('c')." [FUNC] Destinatario #$i: {$admin['email']} ({$admin['nombre']})\n", FILE_APPEND);
        }
        
        // Preparar información del ticket
        $titulo = htmlspecialchars($ticket['titulo'] ?? 'Sin título', ENT_QUOTES, 'UTF-8');
        $desarrollador = htmlspecialchars($agente_nombre, ENT_QUOTES, 'UTF-8');
        $empresa = htmlspecialchars($empresa_nombre, ENT_QUOTES, 'UTF-8');
        $cliente = htmlspecialchars($cliente_nombre, ENT_QUOTES, 'UTF-8');
        $proyecto = htmlspecialchars($nombre_proyecto, ENT_QUOTES, 'UTF-8');
        $prioridad = htmlspecialchars($ticket['prioridad'] ?? 'Media', ENT_QUOTES, 'UTF-8');
        
        // Procesar descripción (puede ser JSON o texto simple)
        $descripcionTexto = '';
        if (!empty($ticket['descripcion'])) {
            $descripcionData = json_decode($ticket['descripcion'], true);
            if ($descripcionData && isset($descripcionData['text'])) {
                $descripcionTexto = $descripcionData['text'];
            } else {
                $descripcionTexto = $ticket['descripcion'];
            }
            // Limpiar y formatear descripción
            $descripcionTexto = preg_replace('/\\\r\\\n|\\\n|\\\r/', '<br>', $descripcionTexto);
            $descripcionTexto = htmlspecialchars($descripcionTexto, ENT_QUOTES, 'UTF-8');
            $descripcionTexto = str_replace('&lt;br&gt;', '<br>', $descripcionTexto);
        }
        if (empty($descripcionTexto)) {
            $descripcionTexto = '<em>Sin descripción</em>';
        }
        
        // Formatear fechas
        $fechaInicio = $ticket['fecha_solicitud'] ? date('d/m/Y H:i', strtotime($ticket['fecha_solicitud'])) : 'N/A';
        $fechaFinalizacion = $ticket['fecha_termina'] ? date('d/m/Y H:i', strtotime($ticket['fecha_termina'])) : date('d/m/Y H:i');
        
        // Construir enlace al panel de administración
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'adm.conlineweb.com';
        $basePath = '/solicitudes';
        if (!empty($_SERVER['REQUEST_URI'])) {
            $basePath = rtrim(dirname($_SERVER['REQUEST_URI']), '/\\');
            if ($basePath === '' || $basePath === '.') $basePath = '/solicitudes';
        }
        $enlace = "$scheme://$host$basePath/index.php";
        
        require_once dirname(__DIR__, 2) . '/includes/cw_email_brand.php';

        $subject = "✅ Ticket #$ticketId Finalizado - $desarrollador ha completado la tarea";
        $body = cw_email_wrap([
            'title' => 'Ticket completado',
            'badge' => 'Finalizado #' . (int) $ticketId,
            'badge_variant' => 'success',
            'signature' => null,
            'content' => cw_email_p('Estimado equipo,')
                . cw_email_alert('El desarrollador <strong>' . $desarrollador . '</strong> ha marcado como <strong>finalizado</strong> el siguiente ticket.', 'success')
                . cw_email_kv([
                    ['label' => 'ID Ticket', 'value' => '#' . (int) $ticketId],
                    ['label' => 'Título', 'value_html' => $titulo],
                    ['label' => 'Desarrollador', 'value_html' => $desarrollador],
                    ['label' => 'Empresa', 'value_html' => $empresa],
                    ['label' => 'Cliente', 'value_html' => $cliente],
                    ['label' => 'Proyecto', 'value_html' => $proyecto],
                    ['label' => 'Prioridad', 'value_html' => $prioridad],
                    ['label' => 'Inicio', 'value' => $fechaInicio],
                    ['label' => 'Finalización', 'value_html' => '<span style="color:#047857;font-weight:700;">' . cw_email_h($fechaFinalizacion) . '</span>'],
                ])
                . cw_email_card($descripcionTexto, 'Descripción')
                . cw_email_cta($enlace, 'Ver panel de administración', 'primary')
                . cw_email_p('<span style="font-size:13px;color:#64748b;">Este correo se genera automáticamente cuando un desarrollador finaliza un ticket.</span>', 0),
        ]);
        
        // Configuración SMTP (debe coincidir exactamente con la que funciona)
        $correoRemitente = "servicios@conlineweb.com";
        $nombreRemitente = "InfoConlineweb - Notificación de Ticket";
        $smtpHost = "smtp.gmail.com";
        $smtpPort = 587;
        $smtpSecure = "starttls";
        $smtpUser = "servicios@conlineweb.com"; // Debe coincidir con el remitente
        $smtpPass = "wcglkgcxfebsauqo"; // App Password debe ser para servicios@conlineweb.com
        
        @file_put_contents(__DIR__.'/email_debug.log', date('c')." [FUNC] Configuración SMTP: Host=$smtpHost, Port=$smtpPort, Secure=$smtpSecure, User=$smtpUser\n", FILE_APPEND);
        
        $enviosExitosos = 0;
        $errores = [];
        
        // Enviar correo a todos los administradores
        foreach ($admins as $admin) {
            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->SMTPAuth = true;
                $mail->SMTPSecure = $smtpSecure;
                $mail->Port = $smtpPort;
                $mail->Host = $smtpHost;
                $mail->Username = $smtpUser;
                $mail->Password = $smtpPass;
                
                // Configuración adicional para compatibilidad
                $mail->SMTPDebug = 0;
                $mail->Debugoutput = 'error_log';
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );
                $mail->Timeout = 30; // Timeout más largo
                $mail->SMTPKeepAlive = false;
                
                $mail->setFrom($correoRemitente, $nombreRemitente);
                $mail->addAddress($admin['email'], $admin['nombre']);
                $mail->Subject = $subject;
                $mail->isHTML(true);
                $mail->Body = $body;
                $mail->CharSet = 'UTF-8';
                $mail->Encoding = 'base64';
                
                if ($mail->send()) {
                    $enviosExitosos++;
                    @file_put_contents(__DIR__.'/email_finalizacion.log', date('c')." Correo de finalizacion ticket #$ticketId enviado a admin {$admin['email']} => OK\n", FILE_APPEND);
                } else {
                    $errores[] = "No se pudo enviar a {$admin['email']}";
                    @file_put_contents(__DIR__.'/email_errors.log', date('c')." Error enviando correo de finalizacion ticket #$ticketId a {$admin['email']}: No se pudo enviar\n", FILE_APPEND);
                }
                
            } catch (Exception $e) {
                $errores[] = "Error enviando a {$admin['email']}: " . $e->getMessage();
                @file_put_contents(__DIR__.'/email_errors.log', date('c')." PHPMailer error enviando finalizacion ticket #$ticketId a {$admin['email']}: ".$e->getMessage()."\n", FILE_APPEND);
            }
        }
        
        // Debug info
        $GLOBALS['ENVIAR_CORREO_FIN_DEBUG'] = [
            'pmPath' => ($GLOBALS['PM_PATH_FIN'] ?? null),
            'admins' => $admins,
            'enviosExitosos' => $enviosExitosos,
            'errores' => $errores,
            'enlace' => $enlace,
        ];
        
        // Considerar exitoso si se envió al menos a un administrador
        if ($enviosExitosos > 0) {
            @file_put_contents(__DIR__.'/email_finalizacion.log', date('c')." Notificacion de finalizacion ticket #$ticketId enviada exitosamente a $enviosExitosos administradores\n", FILE_APPEND);
            return true;
        } else {
            $GLOBALS['ENVIAR_CORREO_FIN_ERROR'] = "No se pudo enviar el correo a ningún administrador. Errores: " . implode(', ', $errores);
            return false;
        }
    }
}
?>