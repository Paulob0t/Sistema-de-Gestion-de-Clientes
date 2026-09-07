<?php
require_once __DIR__ . '/includes/cliente_session.php';
cliente_start_session();

if (!cliente_is_logged_in()) {
    header('Location: ingreso.php');
    exit;
}

include 'conn.php';

function dns_redirect(string $params): void {
    header('Location: dominios.php?' . $params);
    exit;
}

if (!isset($_POST['id'], $_POST['ns1'], $_POST['ns2'], $_POST['ns3'], $_POST['ns4'], $_POST['ns5'], $_POST['ns6'])) {
    dns_redirect('tab=registrados&dns_incomplete=1');
}

$id  = mysqli_real_escape_string($conn, $_POST['id']);
$ns1 = mysqli_real_escape_string($conn, $_POST['ns1']);
$ns2 = mysqli_real_escape_string($conn, $_POST['ns2']);
$ns3 = mysqli_real_escape_string($conn, $_POST['ns3']);
$ns4 = mysqli_real_escape_string($conn, $_POST['ns4']);
$ns5 = mysqli_real_escape_string($conn, $_POST['ns5']);
$ns6 = mysqli_real_escape_string($conn, $_POST['ns6']);
$uid = (int) ($_SESSION['uid'] ?? 0);
$id_int = (int) $id;

$chk = mysqli_query($conn,
    "SELECT id_dominio, url_dominio FROM dominios
     WHERE id_dominio = '$id' AND cliente_id = $uid
     AND eliminado = 0 AND estado_dominio = 1 AND registrado = 1 LIMIT 1"
);

if (!$chk || mysqli_num_rows($chk) === 0) {
    dns_redirect('tab=registrados&dns_warn=1');
}

$dom_row = mysqli_fetch_assoc($chk);
$dominio_url = $dom_row['url_dominio'] ?? '';

$query = "UPDATE dominios SET ns1='$ns1', ns2='$ns2', ns3='$ns3', ns4='$ns4', ns5='$ns5', ns6='$ns6' WHERE id_dominio='$id' AND cliente_id=$uid";
$result = mysqli_query($conn, $query);

if (!$result) {
    dns_redirect('tab=registrados&gestionar=' . $id_int . '&dns_error=1');
}

// ── Notificar al admin por correo ─────────────────────────────────
$cliente_nombre = '';
$cliente_empresa = '';
$cliente_correo_val = '';
if ($uid > 0) {
    $cr = mysqli_query($conn, "SELECT nombre_contacto, empresa, correo FROM clientes WHERE id = $uid LIMIT 1");
    if ($cr && $crow = mysqli_fetch_assoc($cr)) {
        $cliente_nombre     = $crow['nombre_contacto'];
        $cliente_empresa    = $crow['empresa'];
        $cliente_correo_val = $crow['correo'];
    }
}

$fecha_cambio = date('d/m/Y H:i');
$ns_list = "<strong>NS1:</strong> $ns1<br><strong>NS2:</strong> $ns2";
if (!empty($ns3)) $ns_list .= "<br><strong>NS3:</strong> $ns3";
if (!empty($ns4)) $ns_list .= "<br><strong>NS4:</strong> $ns4";
if (!empty($ns5)) $ns_list .= "<br><strong>NS5:</strong> $ns5";
if (!empty($ns6)) $ns_list .= "<br><strong>NS6:</strong> $ns6";

require_once dirname(__DIR__) . '/includes/cw_email_brand.php';

$admin_subject = "⚠️ Cambio de DNS – $dominio_url";
$admin_html = cw_email_wrap([
    'title' => 'Cambio de DNS detectado',
    'badge' => 'Alerta interna',
    'badge_variant' => 'warning',
    'signature' => null,
    'content' => cw_email_p('Un cliente ha actualizado los servidores DNS de uno de sus dominios.')
        . cw_email_alert('Verifica que el cambio sea autorizado y que la propagación DNS se realice correctamente.', 'warning')
        . cw_email_kv([
            ['label' => 'Dominio', 'value_html' => '<strong>' . cw_email_h($dominio_url) . '</strong>'],
            ['label' => 'Cliente', 'value' => $cliente_nombre],
            ['label' => 'Empresa', 'value' => $cliente_empresa],
            ['label' => 'Correo', 'value_html' => '<a href="mailto:' . cw_email_h($cliente_correo_val) . '" style="color:#000147;">' . cw_email_h($cliente_correo_val) . '</a>'],
            ['label' => 'Fecha y hora', 'value' => $fecha_cambio],
            ['label' => 'Nuevos DNS', 'value_html' => $ns_list],
        ]),
]);

try {
    require_once __DIR__ . '/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->SMTPAuth   = true;
    $mail->SMTPSecure = 'tls';
    $mail->Host       = 'smtp.gmail.com';
    $mail->Port       = 587;
    $mail->Username   = 'servicios@conlineweb.com';
    $mail->Password   = 'wcglkgcxfebsauqo';
    $mail->SMTPDebug  = 0;
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom('servicios@conlineweb.com', 'CONLINEWEB');
    $mail->addAddress('servicios@conlineweb.com');
    $mail->isHTML(true);
    $mail->Subject = $admin_subject;
    $mail->Body    = $admin_html;
    $mail->send();
} catch (\Throwable $e) {
    error_log('[DNS Update] Error al notificar admin: ' . $e->getMessage());
}

dns_redirect('tab=registrados&gestionar=' . $id_int . '&dns_ok=1');
