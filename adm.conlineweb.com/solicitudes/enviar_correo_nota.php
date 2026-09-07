<?php
// Envío de correo cuando se agrega una nota/comentario a un ticket usando PHPMailer (SMTP)
// Uso: enviar_correo_nota($conexion, $ticketId, $autor, $notaTexto, $imagenesOpcionalesArray): bool

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Detectar PHPMailer en rutas comunes (igual patrón que enviar_correo_asignacion.php)
$pmPathsNota = [
    __DIR__.'/PHPMailer/src',
    __DIR__.'/../PHPMailer/src',
    dirname(__DIR__).'/PHPMailer/src',
    (isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') : '').'/PHPMailer/src',
];
$pmFoundNota = null;
foreach ($pmPathsNota as $p) {
    if ($p && file_exists($p.'/PHPMailer.php') && file_exists($p.'/SMTP.php') && file_exists($p.'/Exception.php')) {
        $pmFoundNota = $p; break;
    }
}
if (!$pmFoundNota) {
    $msg = "PHPMailer missing for notas in searched paths: ".implode(' | ', $pmPathsNota);
    $GLOBALS['ENVIAR_CORREO_NOTA_ERROR'] = $msg;
    @file_put_contents(__DIR__.'/email.log', date('c')." $msg\n", FILE_APPEND);
    if (!function_exists('enviar_correo_nota')) {
        function enviar_correo_nota(mysqli $conexion, int $ticketId, string $autor, string $notaTexto, array $imagenes = []): bool { return false; }
    }
    return;
}

require_once $pmFoundNota.'/Exception.php';
require_once $pmFoundNota.'/PHPMailer.php';
require_once $pmFoundNota.'/SMTP.php';

if (!function_exists('enviar_correo_nota')) {
    function enviar_correo_nota(mysqli $conexion, int $ticketId, string $autor, string $notaTexto, array $imagenes = []): bool {
        // Zona horaria coherente con el proyecto
        if (function_exists('date_default_timezone_set')) {
            @date_default_timezone_set('America/Mexico_City');
        }

        // 1) Obtener datos del ticket (agente asignado, título y cliente opcional)
        $ticketId = (int)$ticketId;
        $q = $conexion->query("SELECT * FROM solicitudes WHERE id = $ticketId LIMIT 1");
        if (!$q || !$q->num_rows) {
            $GLOBALS['ENVIAR_CORREO_NOTA_ERROR'] = "Ticket #$ticketId no encontrado";
            return false;
        }
        $sol = $q->fetch_assoc();
        $titulo = isset($sol['titulo']) ? (string)$sol['titulo'] : '';
        $agenteId = isset($sol['usuario_asignado']) ? (int)$sol['usuario_asignado'] : 0;

        // 2) Resolver email del agente (preferir agentes.correo; fallback login.usuario)
        $emailDestino = '';
        $nombreDestino = '';
        if ($agenteId > 0) {
            $qa = $conexion->query("SELECT a.nombre, a.correo AS correo_agente, l.usuario AS email_login FROM agentes a LEFT JOIN login l ON a.Idusu = l.id WHERE a.id = ".$agenteId." LIMIT 1");
            if ($qa && $qa->num_rows) {
                $ar = $qa->fetch_assoc();
                $emailDestino = trim((string)($ar['correo_agente'] ?? ''));
                if ($emailDestino === '') { $emailDestino = trim((string)($ar['email_login'] ?? '')); }
                $nombreDestino = trim((string)($ar['nombre'] ?? ''));
            }
        }
        if (!$emailDestino || strpos($emailDestino, '@') === false) {
            $GLOBALS['ENVIAR_CORREO_NOTA_ERROR'] = "Sin correo válido para agente asignado del ticket #$ticketId (agenteId=$agenteId)";
            @file_put_contents(__DIR__.'/email.log', date('c')." NO-DEST correo nota ticket #$ticketId\n", FILE_APPEND);
            return false;
        }

        // 3) Construir enlace directo (igual estilo que correo de asignación)
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'adm.conlineweb.com';
        $basePath = '/solicitudes';
        if (!empty($_SERVER['REQUEST_URI'])) {
            $basePath = rtrim(dirname($_SERVER['REQUEST_URI']), '/\\');
            if ($basePath === '' || $basePath === '.') $basePath = '/solicitudes';
        }
        // Para desarrollador, el panel usa ?id=<agenteId>; se resalta el ticket por ID allí.
        $enlace = "$scheme://$host$basePath/tickets_desarrollador.php?id=$agenteId";

        // 4) Construir asunto y cuerpo con el template solicitado
        $subject = '🔔 Nuevo comentario en el Ticket #'.$ticketId;
        $fecha = date('d/m/Y H:i');

        // Escapar datos para HTML
        $autorEsc = htmlspecialchars($autor, ENT_QUOTES, 'UTF-8');
        $notaEsc = nl2br(htmlspecialchars($notaTexto, ENT_QUOTES, 'UTF-8'));
        $tituloEsc = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');
        $enlaceEsc = htmlspecialchars($enlace, ENT_QUOTES, 'UTF-8');

        if (!empty($imagenes)) {
            $total = count($imagenes);
            $notaImagenesTxt = cw_email_p('La nota incluye <strong>' . (int) $total . '</strong> imagen' . ($total > 1 ? 'es' : '') . '.');
        }

        require_once dirname(__DIR__, 2) . '/includes/cw_email_brand.php';

        $inner = cw_email_p('Hola,')
            . cw_email_p('Se ha añadido un nuevo comentario en el Ticket <strong>#' . (int) $ticketId . '</strong>'
                . ($tituloEsc !== '' ? ' – <em>' . $tituloEsc . '</em>' : '') . '.')
            . cw_email_card(
                '<ul style="margin:0;padding-left:18px;">'
                . '<li><strong>Número:</strong> #' . (int) $ticketId . '</li>'
                . '<li><strong>Autor del comentario:</strong> ' . $autorEsc . '</li>'
                . '<li><strong>Fecha y hora:</strong> ' . cw_email_h($fecha) . '</li>'
                . '</ul>',
                'Detalles del ticket'
            )
            . cw_email_p('<strong>Comentario:</strong><br><span style="display:block;margin-top:8px;padding:12px;background:#f8fafc;border-left:4px solid #10b981;border-radius:8px;">' . $notaEsc . '</span>')
            . ($notaImagenesTxt !== '' ? $notaImagenesTxt : '')
            . cw_email_p('Puedes revisar el ticket completo en el sistema:')
            . cw_email_cta($enlace, 'Ver ticket #' . (int) $ticketId, 'primary');

        $body = cw_email_wrap([
            'title' => 'Nuevo comentario en ticket',
            'content' => $inner,
            'badge' => 'Tickets #' . (int) $ticketId,
            'badge_variant' => 'info',
            'signature_team' => 'Sistema de Gestión de Tickets',
        ]);

        // 5) Config SMTP (igual que otros envíos)
        $correoRemitente = "servicios@conlineweb.com";
        $nombreRemitente = "InfoConlineweb";
        $smtpHost = "smtp.gmail.com";
        $smtpPort = 587;
        $smtpSecure = "tls"; // starttls
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
            $mail->addAddress($emailDestino, $nombreDestino);
            // CC al administrador solicitado
            $mail->addCC('info@conlineweb.com', 'Administrador');
            // Opcional: BCC para auditoría interna
            // $mail->addBCC($correoRemitente);

            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->Body = $body;

            $ok = $mail->send();
            @file_put_contents(__DIR__.'/email.log', date('c')." PHPMailer nota ticket #$ticketId a agente $agenteId <{$emailDestino}> => ".($ok?'OK':'FAIL')."\n", FILE_APPEND);
            return (bool)$ok;
        } catch (Exception $e) {
            $GLOBALS['ENVIAR_CORREO_NOTA_ERROR'] = $e->getMessage();
            @file_put_contents(__DIR__.'/email.log', date('c')." PHPMailer nota error: ".$e->getMessage()."\n", FILE_APPEND);
            return false;
        }
    }
}
?>
