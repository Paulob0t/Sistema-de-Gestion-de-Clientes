<?php
// Envío de correo de asignación al guardar un ticket usando PHPMailer (SMTP)
// Uso: enviar_correo_asignacion($conexion, $ticketId, $titulo, $cliente_id|null, $agenteId): bool

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
    $GLOBALS['ENVIAR_CORREO_ASIG_ERROR'] = $msg;
    $GLOBALS['ENVIAR_CORREO_ASIG_DEBUG'] = ['pmPaths'=>$pmPaths];
    @file_put_contents(__DIR__.'/email.log', date('c')." $msg\n", FILE_APPEND);
    if (!function_exists('enviar_correo_asignacion')) {
        function enviar_correo_asignacion(mysqli $conexion, int $ticketId, string $titulo, ?int $cliente_id, int $agenteId): bool { return false; }
    }
    return;
}

require_once $pmFound.'/Exception.php';
require_once $pmFound.'/PHPMailer.php';
require_once $pmFound.'/SMTP.php';
$GLOBALS['PM_PATH_ASIG'] = $pmFound;

if (!function_exists('enviar_correo_asignacion')) {
    function enviar_correo_asignacion(mysqli $conexion, int $ticketId, string $titulo, ?int $cliente_id, int $agenteId): bool {
        // Obtener agente (email/nombre) - preferir agentes.correo; fallback login.usuario
    $q = $conexion->query("SELECT a.id, a.Idusu, a.nombre, a.correo AS correo_agente, l.usuario as email_login FROM agentes a LEFT JOIN login l ON a.Idusu = l.id WHERE a.id = ".$agenteId." LIMIT 1");
    if (!$q || !$q->num_rows) { $GLOBALS['ENVIAR_CORREO_ASIG_ERROR'] = "No se encontró el agente con id $agenteId"; return false; }
        $row = $q->fetch_assoc();
        $emailAgente = trim((string)($row['correo_agente'] ?? ''));
        if ($emailAgente === '') { $emailAgente = trim((string)($row['email_login'] ?? '')); }
        $nombreAgente = trim((string)$row['nombre']);
    if (!$emailAgente || strpos($emailAgente, '@') === false) { $GLOBALS['ENVIAR_CORREO_ASIG_ERROR'] = "El agente $agenteId no tiene un correo válido ('".$emailAgente."')"; return false; }

        // Cliente (nombre/empresa)
        $clienteTexto = 'N/A';
        if (!empty($cliente_id)) {
            $qc = $conexion->query("SELECT nombre_contacto, empresa FROM clientes WHERE id = ".(int)$cliente_id);
            if ($qc && $c = $qc->fetch_assoc()) {
                $nc = trim((string)$c['nombre_contacto']);
                $em = trim((string)$c['empresa']);
                $clienteTexto = $nc . ($em !== '' ? " / $em" : '');
            }
        }

        $fechaAsignacion = date('d/m/Y – H:i');
        // Construir enlace directo
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'adm.conlineweb.com';
        $basePath = '/solicitudes';
        if (!empty($_SERVER['REQUEST_URI'])) {
            $basePath = rtrim(dirname($_SERVER['REQUEST_URI']), '/\\');
            if ($basePath === '' || $basePath === '.') $basePath = '/solicitudes';
        }
        $enlace = "$scheme://$host$basePath/tickets_desarrollador.php?id=$agenteId";

        // Template solicitado
        $subject = 'Alerta de Asignación de Tarea – Portal de Soporte';
        require_once dirname(__DIR__, 2) . '/includes/cw_email_brand.php';

        $inner = cw_email_p('Estimado/a <strong>' . cw_email_h($nombreAgente) . '</strong>,')
            . cw_email_p('Se te ha asignado una nueva tarea en el sistema de tickets de soporte:')
            . cw_email_kv([
                ['label' => 'Ticket', 'value' => '#' . $ticketId],
                ['label' => 'Asunto', 'value' => $titulo],
                ['label' => 'Cliente', 'value' => $clienteTexto],
                ['label' => 'Asignación', 'value' => $fechaAsignacion],
            ])
            . cw_email_p('Ingresa al portal para revisar los detalles y dar seguimiento oportuno.')
            . cw_email_cta($enlace, 'Ver ticket asignado', 'primary')
            . cw_email_alert('Dar atención oportuna a las tareas asignadas mantiene la calidad del servicio.', 'info');

        $body = cw_email_wrap([
            'title' => 'Nueva tarea asignada',
            'content' => $inner,
            'badge' => 'Portal Soporte',
            'badge_variant' => 'warning',
            'signature_team' => 'Equipo de Soporte Técnico',
        ]);

        // Configuración SMTP (igual a enviar_correo.php)
        $correoRemitente = "servicios@conlineweb.com";
        $nombreRemitente = "InfoConlineweb";
        $smtpHost = "smtp.gmail.com";
        $smtpPort = 587;
    $smtpSecure = "tls"; // alineado con script funcional
        $smtpUser = $correoRemitente;
        $smtpPass = "wcglkgcxfebsauqo"; // App Password

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->SMTPAuth = true;
            $mail->SMTPSecure = $smtpSecure;
            $mail->Port = $smtpPort;
            $mail->Host = $smtpHost;
            $mail->Username = $smtpUser;
            $mail->Password = $smtpPass;

            $mail->setFrom($correoRemitente, $nombreRemitente);
            $mail->addAddress($emailAgente, $nombreAgente);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $body;
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';

            // Debug info
            $GLOBALS['ENVIAR_CORREO_ASIG_DEBUG'] = [
                'pmPath' => ($GLOBALS['PM_PATH_ASIG'] ?? null),
                'emailAgente' => $emailAgente,
                'nombreAgente' => $nombreAgente,
                'host' => $host,
                'smtpSecure' => $smtpSecure,
                'smtpPort' => $smtpPort,
                'enlace' => $enlace,
            ];

            $ok = $mail->send();
            @file_put_contents(__DIR__.'/email.log', date('c')." PHPMailer asignacion ticket #$ticketId a agente $agenteId <$emailAgente> => ".($ok?'OK':'FAIL')."\n", FILE_APPEND);
            return (bool)$ok;
        } catch (Exception $e) {
            $GLOBALS['ENVIAR_CORREO_ASIG_ERROR'] = $e->getMessage();
            @file_put_contents(__DIR__.'/email.log', date('c')." PHPMailer error: ".$e->getMessage()."\n", FILE_APPEND);
            return false;
        }
    }
}
?>