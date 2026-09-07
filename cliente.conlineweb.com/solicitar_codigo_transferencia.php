<?php
/**
 * Endpoint AJAX: solicitud de código de autorización/EPP para transferencia de dominio.
 * Notifica por correo al admin y al cliente.
 */
declare(strict_types=1);

// Capturar cualquier salida inesperada para no romper el JSON
ob_start();

require_once __DIR__ . '/includes/cliente_session.php';
cliente_start_session();

header('Content-Type: application/json; charset=utf-8');

if (!cliente_is_logged_in()) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

require_once __DIR__ . '/conn.php';

$dominio_id = (int) ($_POST['dominio_id'] ?? 0);
if ($dominio_id <= 0) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dominio inválido']);
    exit;
}

$uid = (int) $_SESSION['uid'];

// ── Verificar que el dominio pertenezca al cliente ───────────────────────────
$stmt = $conn->prepare(
    "SELECT d.id_dominio, d.url_dominio, c.nombre_contacto, c.empresa, c.correo, c.telefono
     FROM dominios d
     JOIN clientes c ON c.id = d.cliente_id
     WHERE d.id_dominio = ? AND d.cliente_id = ? AND d.eliminado = 0
       AND d.estado_dominio = 1 AND d.registrado = 1 LIMIT 1"
);
if (!$stmt) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    exit;
}
$stmt->bind_param('ii', $dominio_id, $uid);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    ob_end_clean();
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Este dominio no está activo y registrado. No se puede solicitar transferencia.']);
    exit;
}

// ── Variables de datos ───────────────────────────────────────────────────────
$dominio         = (string) $row['url_dominio'];
$cliente_nombre  = (string) $row['nombre_contacto'];
$cliente_empresa = (string) $row['empresa'];
$cliente_correo  = (string) $row['correo'];
$cliente_tel     = (string) $row['telefono'];
$fecha           = date('d/m/Y H:i');

$envPath = dirname(__DIR__) . '/.env';
if (file_exists($envPath)) {
    if (!function_exists('cw_load_dotenv')) {
        function cw_load_dotenv($path) {
            if (!file_exists($path)) return;
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || strpos($line, '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($key, $val) = explode('=', $line, 2);
                    $key = trim($key);
                    $val = trim(trim($val), '"\'');
                    if (!array_key_exists($key, $_ENV)) {
                        $_ENV[$key] = $val;
                        putenv("$key=$val");
                    }
                }
            }
        }
    }
    cw_load_dotenv($envPath);
}

// ── Configuración (variables para poder usarlas en heredocs) ─────────────────
$admin_email      = getenv('SMTP_FROM_EMAIL') ?: ($_ENV['SMTP_FROM_EMAIL'] ?? 'servicios@conlineweb.com');
$admin_nombre     = getenv('SMTP_FROM_NAME') ?: ($_ENV['SMTP_FROM_NAME'] ?? 'CONLINEWEB');
$admin_whatsapp   = getenv('CW_HUB_WHATSAPP') ?: ($_ENV['CW_HUB_WHATSAPP'] ?? '5214771181285');
$smtp_host        = getenv('SMTP_HOST') ?: ($_ENV['SMTP_HOST'] ?? 'smtp.gmail.com');
$smtp_port        = (int)(getenv('SMTP_PORT') ?: ($_ENV['SMTP_PORT'] ?? 587));
$smtp_user        = getenv('SMTP_USER') ?: ($_ENV['SMTP_USER'] ?? 'servicios@conlineweb.com');
$smtp_pass        = getenv('SMTP_PASS') ?: ($_ENV['SMTP_PASS'] ?? '');
$wa_admin_link    = 'https://wa.me/' . $admin_whatsapp . '?text=' . rawurlencode("Solicitud código EPP dominio $dominio");
$wa_cliente_link  = 'https://wa.me/' . $admin_whatsapp . '?text=' . rawurlencode("Hola, solicité el código EPP para el dominio $dominio y quiero hacer seguimiento.");

// ── Helper: enviar correo ────────────────────────────────────────────────────
$phpmailer_loaded = false;
$mail_fn = function (string $to, string $subject, string $body) use (
    $smtp_host, $smtp_port, $smtp_user, $smtp_pass, $admin_nombre, &$phpmailer_loaded
): bool {
    if (!$phpmailer_loaded) {
        require_once __DIR__ . '/PHPMailer/src/Exception.php';
        require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
        require_once __DIR__ . '/PHPMailer/src/SMTP.php';
        $phpmailer_loaded = true;
    }
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->SMTPAuth   = true;
        $mail->SMTPSecure = 'tls';
        $mail->Host       = $smtp_host;
        $mail->Port       = $smtp_port;
        $mail->Username   = $smtp_user;
        $mail->Password   = $smtp_pass;
        $mail->SMTPDebug  = 0;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom($smtp_user, $admin_nombre);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->send();
        return true;
    } catch (\Throwable $e) {
        error_log('[TransferDomain] SMTP error: ' . $e->getMessage());
        return false;
    }
};

// ── Plantilla unificada ConlineWeb ───────────────────────────────────────────
require_once dirname(__DIR__) . '/includes/cw_email_brand.php';

function cw_transfer_email(string $title, string $content, string $badge = 'Dominios', string $badgeVariant = 'neutral'): string
{
    return cw_email_wrap([
        'title' => $title,
        'content' => $content,
        'badge' => $badge,
        'badge_variant' => $badgeVariant,
        'signature' => null,
    ]);
}

// ── Correo al ADMIN ──────────────────────────────────────────────────────────
$admin_content =
    cw_email_p('Se ha recibido una nueva solicitud de <strong>código de autorización (EPP)</strong> para la transferencia de un dominio.')
    . cw_email_alert('El cliente requiere acción: proporciona el código EPP/AUTH del dominio lo antes posible.', 'warning')
    . cw_email_kv([
        ['label' => 'Dominio', 'value_html' => '<strong>' . cw_email_h($dominio) . '</strong>'],
        ['label' => 'Cliente', 'value' => $cliente_nombre],
        ['label' => 'Empresa', 'value' => $cliente_empresa],
        ['label' => 'Correo', 'value_html' => '<a href="mailto:' . cw_email_h($cliente_correo) . '">' . cw_email_h($cliente_correo) . '</a>'],
        ['label' => 'Teléfono', 'value' => $cliente_tel],
        ['label' => 'Fecha solicitud', 'value' => $fecha],
    ])
    . cw_email_cta($wa_admin_link, 'Responder por WhatsApp', 'whatsapp')
    . cw_email_p('<span style="font-size:12px;color:#64748b;">O responde directamente al correo del cliente.</span>', 0);

$admin_html = cw_transfer_email(
    'Solicitud de Código de Transferencia de Dominio',
    $admin_content,
    'Alerta interna',
    'warning'
);

// ── Correo al CLIENTE ────────────────────────────────────────────────────────
$cliente_content =
    cw_email_p('Hola <strong>' . cw_email_h($cliente_nombre) . '</strong>, hemos recibido correctamente tu solicitud de <strong>código de autorización (EPP)</strong> para transferir el dominio:')
    . cw_email_kv([
        ['label' => 'Dominio', 'value_html' => '<strong>' . cw_email_h($dominio) . '</strong>'],
        ['label' => 'Tipo de solicitud', 'value' => 'Código de transferencia'],
        ['label' => 'Fecha', 'value' => $fecha],
    ])
    . cw_email_p('Nuestro equipo te enviará el <strong>código EPP / Auth-Info</strong> al correo '
        . '<a href="mailto:' . cw_email_h($cliente_correo) . '" style="color:#059669;">' . cw_email_h($cliente_correo) . '</a>'
        . ' en un plazo de <strong>1 a 3 días hábiles</strong>.')
    . cw_email_p('Si necesitas soporte inmediato puedes escribirnos por WhatsApp:')
    . cw_email_cta($wa_cliente_link, 'Contactar soporte por WhatsApp', 'whatsapp');

$cliente_html = cw_transfer_email(
    'Tu solicitud fue recibida',
    $cliente_content,
    'Área Cliente',
    'success'
);

// ── Enviar correos ───────────────────────────────────────────────────────────
$ok_admin   = $mail_fn($admin_email, "\xF0\x9F\x94\x91 Solicitud de código EPP – $dominio", $admin_html);
$ok_cliente = filter_var($cliente_correo, FILTER_VALIDATE_EMAIL)
    ? $mail_fn($cliente_correo, "\xE2\x9C\x85 Solicitud recibida – Código de transferencia de $dominio", $cliente_html)
    : false;

// ── Registrar en BD ──────────────────────────────────────────────────────────
$conn->query(
    "CREATE TABLE IF NOT EXISTS solicitudes_transferencia (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        id_dominio    INT NOT NULL,
        id_cliente    INT NOT NULL,
        dominio       VARCHAR(255) NOT NULL,
        cliente_email VARCHAR(255),
        estado        ENUM('pendiente','enviado','completado') DEFAULT 'pendiente',
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
$ins = $conn->prepare(
    "INSERT INTO solicitudes_transferencia (id_dominio, id_cliente, dominio, cliente_email) VALUES (?, ?, ?, ?)"
);
if ($ins) {
    $ins->bind_param('iiss', $dominio_id, $uid, $dominio, $cliente_correo);
    $ins->execute();
    $ins->close();
}

// ── Respuesta JSON (descartar cualquier output previo) ───────────────────────
ob_end_clean();
echo json_encode([
    'success'       => true,
    'email_admin'   => $ok_admin,
    'email_cliente' => $ok_cliente,
    'wa_link'       => $wa_cliente_link,
    'dominio'       => $dominio,
], JSON_UNESCAPED_UNICODE);
