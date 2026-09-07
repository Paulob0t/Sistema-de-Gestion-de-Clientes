<?php
/**
 * Notificaciones Hub: n8n webhooks, email, eventos de leads.
 */
require_once __DIR__ . '/cw_hub_config.php';

/**
 * Carga el sistema de marca de correos (ruta compartida del monorepo).
 */
function cw_hub_require_email_brand(): void
{
    if (function_exists('cw_email_wrap')) {
        return;
    }
    $candidates = [
        dirname(__DIR__, 2) . '/includes/cw_email_brand.php',
        dirname(__DIR__, 2) . '/shared/includes/cw_email_brand.php',
        __DIR__ . '/cw_email_brand.php',
    ];
    foreach ($candidates as $file) {
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
    // Fallback local (monorepo sin shared/includes)
    $fallback = __DIR__ . '/cw_email_brand_fallback.php';
    if (is_file($fallback)) {
        require_once $fallback;
        return;
    }
    throw new RuntimeException('cw_email_brand.php no encontrado');
}

function cw_hub_n8n_dispatch(string $event, array $payload): bool
{
    $url = CW_HUB_N8N_WEBHOOK;
    if ($url === '') {
        return false;
    }

    $body = json_encode([
        'event' => $event,
        'source' => 'conlineweb_hub',
        'timestamp' => date('c'),
        'data' => $payload,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-CW-Hub-Key: ' . CW_HUB_API_KEY,
        ],
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 8,
    ]);
    curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $http >= 200 && $http < 300;
}

function cw_hub_send_alert_email(string $subject, string $htmlBody, $to = null): array
{
    $recipients = cw_hub_alert_recipients($to);
    if ($recipients === []) {
        return ['ok' => false, 'error' => 'Sin destinatarios de alerta configurados'];
    }

    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        require_once dirname(__DIR__) . '/PHPMailer/src/Exception.php';
        require_once dirname(__DIR__) . '/PHPMailer/src/PHPMailer.php';
        require_once dirname(__DIR__) . '/PHPMailer/src/SMTP.php';
    }
    require_once dirname(__DIR__) . '/smtp_config_helper.php';

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        configure_phpmailer_by_system($mail, 'conlineweb');
        $mail->Timeout = 15;
        $mail->SMTPKeepAlive = false;
        foreach ($recipients as $addr) {
            $mail->addAddress($addr);
        }
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));
        $mail->send();

        return ['ok' => true, 'error' => '', 'recipients' => $recipients];
    } catch (Throwable $e) {
        $detail = $e->getMessage();
        if (isset($mail) && !empty($mail->ErrorInfo)) {
            $detail = $mail->ErrorInfo;
        }
        error_log('cw_hub_send_alert_email: ' . $detail);

        return ['ok' => false, 'error' => $detail, 'recipients' => $recipients];
    }
}

/** @return list<string> */
function cw_hub_alert_recipients($to = null): array
{
    $list = [];
    if (is_string($to) && trim($to) !== '') {
        $list[] = trim($to);
    } elseif (is_array($to)) {
        foreach ($to as $addr) {
            $addr = trim((string) $addr);
            if ($addr !== '' && filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                $list[] = $addr;
            }
        }
    }

    if ($list === []) {
        if (defined('CW_HUB_ALERT_EMAILS') && is_array(CW_HUB_ALERT_EMAILS)) {
            foreach (CW_HUB_ALERT_EMAILS as $addr) {
                $addr = trim((string) $addr);
                if ($addr !== '' && filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                    $list[] = $addr;
                }
            }
        }
        $default = defined('CW_HUB_ALERT_EMAIL') ? trim((string) CW_HUB_ALERT_EMAIL) : '';
        if ($default !== '' && filter_var($default, FILTER_VALIDATE_EMAIL)) {
            $list[] = $default;
        }
    }

    return array_values(array_unique($list));
}

function cw_hub_local_adm_origin(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8888';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));

    if (preg_match('#^(.*?/adm\.conlineweb\.com)#', $script, $m)) {
        return $scheme . '://' . $host . $m[1];
    }
    if (preg_match('#^(.*?)/(?:conlineweb\.com|cliente\.conlineweb\.com)/#', $script, $m)) {
        return $scheme . '://' . $host . $m[1] . '/adm.conlineweb.com';
    }

    // Preferencia local actual (MAMP): /sistema/
    return $scheme . '://' . $host . '/sistema/adm.conlineweb.com';
}

function cw_hub_admin_inbox_url(int $leadId): string
{
    if (defined('ADM_DEV_LOCAL') && ADM_DEV_LOCAL) {
        return cw_hub_local_adm_origin() . '/leads/inbox.php?lead_id=' . $leadId . '&tab=chat';
    }

    return 'https://adm.conlineweb.com/leads/inbox.php?lead_id=' . $leadId . '&tab=chat';
}

function cw_hub_admin_chat_inbox_url(int $conversationId): string
{
    if (defined('ADM_DEV_LOCAL') && ADM_DEV_LOCAL) {
        return cw_hub_local_adm_origin() . '/leads/inbox.php?id=' . $conversationId . '&tab=sesiones';
    }

    return 'https://adm.conlineweb.com/leads/inbox.php?id=' . $conversationId . '&tab=sesiones';
}

/**
 * Alerta: alguien ingresó al chat web (para ver la conversación en vivo).
 * @param array<string,mixed> $ctx
 * @return array{ok:bool,error:string,skipped:bool}
 */
function cw_hub_notify_web_chat_started(mysqli $conn, int $conversationId, array $ctx = []): array
{
    if ($conversationId <= 0) {
        return ['ok' => false, 'error' => 'Conversación inválida', 'skipped' => true];
    }

    $conv = null;
    if (function_exists('cw_chat_get_conversation')) {
        $conv = cw_chat_get_conversation($conn, $conversationId);
    }
    if (!$conv) {
        return ['ok' => false, 'error' => 'Conversación no encontrada', 'skipped' => true];
    }

    $meta = [];
    if (!empty($conv['metadata'])) {
        $decoded = json_decode((string) $conv['metadata'], true);
        if (is_array($decoded)) {
            $meta = $decoded;
        }
    }
    // Evitar spam: una alerta de ingreso por conversación
    if (!empty($meta['ingreso_alert_sent_at'])) {
        return ['ok' => true, 'error' => '', 'skipped' => true];
    }

    cw_hub_require_email_brand();

    $pagina = trim((string) ($ctx['pagina_origen'] ?? $meta['pagina_origen'] ?? ''));
    $interes = trim((string) ($ctx['interes'] ?? $meta['interes'] ?? $meta['mensaje_inicial'] ?? ''));
    $servicio = trim((string) ($ctx['servicio'] ?? $meta['servicio'] ?? ''));
    $sessionId = trim((string) ($ctx['session_id'] ?? $meta['session_id'] ?? ''));
    $inboxUrl = cw_hub_admin_chat_inbox_url($conversationId);

    $box = '<p style="margin:0 0 8px;"><strong>Sesión chat #</strong>' . (int) $conversationId . '</p>'
        . '<p style="margin:0 0 8px;"><strong>Estado:</strong> Visitante en el sitio ahora</p>'
        . '<p style="margin:0 0 8px;"><strong>Canal:</strong> Sitio web (sesión en vivo)</p>';
    if ($sessionId !== '') {
        $box .= '<p style="margin:0 0 8px;"><strong>ID sesión:</strong> ' . cw_email_h(mb_substr($sessionId, 0, 40)) . '</p>';
    }
    if ($servicio !== '') {
        $box .= '<p style="margin:0 0 8px;"><strong>Servicio:</strong> ' . cw_email_h($servicio) . '</p>';
    }
    if ($interes !== '') {
        $box .= '<p style="margin:0 0 8px;"><strong>Interés:</strong> ' . cw_email_h($interes) . '</p>';
    }
    if ($pagina !== '') {
        $box .= '<p style="margin:0;"><strong>Página:</strong> <a href="' . cw_email_h($pagina) . '" style="color:#000147;">' . cw_email_h($pagina) . '</a></p>';
    }

    $body = cw_email_p('Un visitante <strong>está en el sitio web ahora</strong> y abrió el chat. '
            . 'Puedes atenderlo en vivo o responderle por su sesión desde el CRM, aunque aún no se haya registrado.')
        . cw_email_card($box, 'Sesión del visitante')
        . cw_email_cta($inboxUrl, 'Atender sesión en vivo', 'primary');

    $html = cw_hub_email_template('Visitante en el sitio ahora · sesión #' . $conversationId, $body, 'Alerta chat web ConlineWeb');
    $subject = 'Visitante en el sitio ahora — atender sesión #' . $conversationId;
    $result = cw_hub_send_alert_email($subject, $html);

    $meta['ingreso_alert_sent_at'] = date('Y-m-d H:i:s');
    $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE);
    $upd = $conn->prepare('UPDATE cw_chat_conversaciones SET metadata = ? WHERE id = ?');
    if ($upd) {
        $upd->bind_param('si', $metaJson, $conversationId);
        $upd->execute();
        $upd->close();
    }

    return [
        'ok' => !empty($result['ok']),
        'error' => (string) ($result['error'] ?? ''),
        'skipped' => false,
    ];
}

function cw_hub_website_leads_url(int $leadId): string
{
    if (defined('ADM_DEV_LOCAL') && ADM_DEV_LOCAL) {
        return cw_hub_local_adm_origin() . '/website/leads.php';
    }

    return 'https://adm.conlineweb.com/website/leads.php';
}

function cw_hub_admin_alert_sent_recently(mysqli $conn, int $leadId, int $minutes = 30): bool
{
    $since = date('Y-m-d H:i:s', strtotime('-' . max(1, $minutes) . ' minutes'));
    $like = 'Alerta admin:%';
    $stmt = $conn->prepare(
        "SELECT id FROM cw_lead_actividades
         WHERE lead_id = ? AND descripcion LIKE ? AND created_at >= ?
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('iss', $leadId, $like, $since);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return !empty($row);
}

/** @return array{ok:bool,error:string,skipped:bool} */
function cw_hub_send_admin_lead_alert(int $leadId, array $lead, bool $isNewLead): array
{
    $servicios = CW_HUB_SERVICIOS;
    $svcLabel = $servicios[$lead['servicio'] ?? ''] ?? ($lead['servicio_label'] ?? ($lead['servicio'] ?? ''));
    $nombre = trim((string) ($lead['nombre'] ?? ''));
    $apellido = trim((string) ($lead['apellido'] ?? ''));
    $correo = trim((string) ($lead['correo'] ?? ''));
    $telefono = trim((string) ($lead['telefono'] ?? $lead['whatsapp'] ?? ''));
    $empresa = trim((string) ($lead['empresa'] ?? ''));
    $pagina = trim((string) ($lead['pagina_origen'] ?? ''));
    $requerimiento = trim((string) ($lead['requerimiento'] ?? ''));
    $adminUrl = cw_hub_admin_inbox_url($leadId);
    $tipo = $isNewLead ? 'Nuevo lead web' : 'Nueva interacción web';
    $nombreCompleto = trim($nombre . ($apellido !== '' ? ' ' . $apellido : ''));

    cw_hub_require_email_brand();

    $boxInner = '<p style="margin:0 0 8px;"><strong>Lead #</strong>' . (int) $leadId . '</p>'
        . '<p style="margin:0 0 8px;"><strong>Nombre:</strong> ' . cw_email_h($nombreCompleto !== '' ? $nombreCompleto : $nombre) . '</p>'
        . '<p style="margin:0 0 8px;"><strong>Empresa:</strong> ' . cw_email_h($empresa !== '' ? $empresa : '—') . '</p>'
        . '<p style="margin:0 0 8px;"><strong>Servicio:</strong> ' . cw_email_h($svcLabel) . '</p>'
        . '<p style="margin:0 0 8px;"><strong>WhatsApp:</strong> ' . cw_email_h($telefono) . '</p>'
        . '<p style="margin:0 0 8px;"><strong>Correo:</strong> ' . cw_email_h($correo !== '' ? $correo : '—') . '</p>';
    if ($requerimiento !== '') {
        $boxInner .= '<p style="margin:0 0 8px;"><strong>Requerimiento:</strong> ' . nl2br(cw_email_h($requerimiento)) . '</p>';
    }
    if ($pagina !== '') {
        $boxInner .= '<p style="margin:0;"><strong>Página origen:</strong> <a href="' . cw_email_h($pagina) . '" style="color:#000147;">' . cw_email_h($pagina) . '</a></p>';
    }

    $body = cw_email_p('Se registró un contacto desde el <strong>modal WhatsApp</strong> del sitio.')
        . cw_email_card($boxInner, 'Detalle del lead')
        . cw_email_cta($adminUrl, 'Abrir bandeja CRM', 'primary')
        . cw_email_cta(cw_hub_website_leads_url($leadId), 'Ver en Leads Website', 'accent');

    $html = cw_hub_email_template($tipo . ' #' . $leadId, $body, 'Alerta automática ConlineWeb Hub');
    $subject = ($isNewLead ? 'Nuevo lead web' : 'Interacción web') . ' #' . $leadId . ' — ' . ($nombreCompleto !== '' ? $nombreCompleto : $nombre);

    return cw_hub_send_alert_email($subject, $html);
}

/**
 * Notifica al equipo (email + n8n) por cada envío del formulario web.
 *
 * @return array{admin_ok:bool,admin_error:string,admin_skipped:bool,n8n_ok:bool}
 */
function cw_hub_notify_web_lead_submission(mysqli $conn, int $leadId, array $lead, bool $isNewLead): array
{
    $servicios = CW_HUB_SERVICIOS;
    $svcLabel = $servicios[$lead['servicio'] ?? ''] ?? ($lead['servicio'] ?? '');

    $payload = [
        'lead_id' => $leadId,
        'nombre' => $lead['nombre'] ?? '',
        'apellido' => $lead['apellido'] ?? '',
        'correo' => $lead['correo'] ?? '',
        'whatsapp' => $lead['telefono'] ?? $lead['whatsapp'] ?? '',
        'empresa' => $lead['empresa'] ?? '',
        'servicio' => $lead['servicio'] ?? '',
        'servicio_label' => $svcLabel,
        'proyecto' => $lead['requerimiento'] ?? '',
        'pagina_origen' => $lead['pagina_origen'] ?? '',
        'pipeline_estado' => $lead['pipeline_estado'] ?? 'lead',
        'fuente' => $lead['fuente'] ?? 'website',
        'is_new' => $isNewLead,
        'admin_url' => cw_hub_admin_inbox_url($leadId),
    ];

    $n8nOk = cw_hub_n8n_dispatch($isNewLead ? 'lead.created' : 'lead.resubmitted', $payload);

    $adminResult = cw_hub_send_admin_lead_alert($leadId, $lead, $isNewLead);
    $adminOk = !empty($adminResult['ok']);
    $adminError = (string) ($adminResult['error'] ?? '');

    if ($adminOk) {
        $now = date('Y-m-d H:i:s');
        $recipients = implode(', ', $adminResult['recipients'] ?? cw_hub_alert_recipients());
        $desc = 'Alerta admin: correo enviado a ' . $recipients;
        $act = $conn->prepare("INSERT INTO cw_lead_actividades (lead_id, tipo, descripcion, created_at) VALUES (?, 'seguimiento', ?, ?)");
        $act->bind_param('iss', $leadId, $desc, $now);
        $act->execute();
        $act->close();
    } elseif ($adminError !== '') {
        error_log('cw_hub_notify_web_lead_submission admin alert failed for lead #' . $leadId . ': ' . $adminError);
    }

    if ($isNewLead && CW_HUB_AUTO_WA_WELCOME) {
        require_once __DIR__ . '/cw_hub_whatsapp.php';
        cw_hub_send_welcome_template($payload['whatsapp'], $payload['nombre'], $leadId, $conn);
    }

    return [
        'admin_ok' => $adminOk,
        'admin_error' => $adminError,
        'admin_skipped' => false,
        'n8n_ok' => $n8nOk,
    ];
}

/**
 * Correo de confirmación al cliente + registro en notas del lead.
 *
 * @return array{ok:bool,error:string}
 */
function cw_hub_send_client_confirmation(mysqli $conn, int $leadId, string $correo, array $lead, string $whatsappUrl): array
{
    if ($correo === '') {
        return ['ok' => false, 'error' => 'Sin correo'];
    }

    $emailResult = cw_hub_send_lead_confirmation_email($correo, array_merge($lead, [
        'whatsapp_url' => $whatsappUrl,
    ]));

    if (empty($emailResult['ok'])) {
        return $emailResult;
    }

    $notesStmt = $conn->prepare('SELECT notas FROM leads WHERE id = ? AND origen_web = 1 LIMIT 1');
    if (!$notesStmt) {
        return $emailResult;
    }
    $notesStmt->bind_param('i', $leadId);
    $notesStmt->execute();
    $row = $notesStmt->get_result()->fetch_assoc();
    $notesStmt->close();

    $notasArr = json_decode($row['notas'] ?? '[]', true);
    if (!is_array($notasArr)) {
        $notasArr = [];
    }
    $notasArr[] = [
        'nota' => 'Correo de confirmación enviado al cliente: ' . $correo,
        'fecha' => date('Y-m-d H:i:s'),
        'usuario_id' => 0,
    ];
    $notasJson = json_encode($notasArr, JSON_UNESCAPED_UNICODE);
    $noteUpd = $conn->prepare('UPDATE leads SET notas = ? WHERE id = ? AND origen_web = 1');
    $noteUpd->bind_param('si', $notasJson, $leadId);
    $noteUpd->execute();
    $noteUpd->close();

    return $emailResult;
}

function cw_hub_on_new_lead(mysqli $conn, int $leadId, array $lead): void
{
    cw_hub_notify_web_lead_submission($conn, $leadId, $lead, true);
}

function cw_hub_email_template(string $title, string $bodyHtml, string $signature = ''): string
{
    cw_hub_require_email_brand();

    if ($signature !== '') {
        $bodyHtml .= cw_email_p('<span style="font-size:13px;color:#94a3b8;">' . cw_email_h($signature) . '</span>', 0);
    }

    return cw_email_wrap([
        'title' => $title,
        'content' => $bodyHtml,
        'badge' => 'ConlineWeb Hub',
        'badge_variant' => 'neutral',
        'signature_team' => 'Equipo ConlineWeb',
    ]);
}

function cw_hub_str_len(string $text): int
{
    return function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
}

function cw_hub_str_sub(string $text, int $start, ?int $length = null): string
{
    if (function_exists('mb_substr')) {
        return $length === null ? mb_substr($text, $start) : mb_substr($text, $start, $length);
    }

    return $length === null ? substr($text, $start) : substr($text, $start, $length);
}

function cw_hub_lead_has_confirmation_note(?string $notasJson): bool
{
    $notas = json_decode($notasJson ?? '[]', true);
    if (!is_array($notas)) {
        return false;
    }
    foreach ($notas as $nota) {
        $text = (string) ($nota['nota'] ?? '');
        if (str_starts_with($text, 'Correo de confirmación enviado')) {
            return true;
        }
    }

    return false;
}

/** @return array{ok:bool,error:string} */
function cw_hub_send_lead_confirmation_email(string $to, array $lead): array
{
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Correo inválido'];
    }

    $nombre = trim((string) ($lead['nombre'] ?? 'Cliente'));
    $servicios = CW_HUB_SERVICIOS;
    $servicioLabel = $servicios[$lead['servicio'] ?? ''] ?? ($lead['servicio_label'] ?? 'nuestros servicios');
    $telefono = trim((string) ($lead['telefono'] ?? $lead['whatsapp'] ?? ''));
    $mensaje = trim((string) ($lead['mensaje'] ?? $lead['requerimiento'] ?? ''));
    $pagina = trim((string) ($lead['pagina_origen'] ?? ''));
    $waUrl = trim((string) ($lead['whatsapp_url'] ?? ''));

    cw_hub_require_email_brand();

    $boxInner = '<p style="margin:0 0 8px;"><strong>Servicio de interés:</strong> ' . cw_email_h($servicioLabel) . '</p>';
    if ($telefono !== '') {
        $boxInner .= '<p style="margin:0 0 8px;"><strong>Teléfono:</strong> ' . cw_email_h($telefono) . '</p>';
    }
    if ($mensaje !== '') {
        $shortMsg = cw_hub_str_len($mensaje) > 220 ? cw_hub_str_sub($mensaje, 0, 217) . '…' : $mensaje;
        $boxInner .= '<p style="margin:0 0 8px;"><strong>Tu mensaje:</strong> ' . nl2br(cw_email_h($shortMsg)) . '</p>';
    }
    if ($pagina !== '') {
        $boxInner .= '<p style="margin:0;"><strong>Página:</strong> <a href="' . cw_email_h($pagina) . '" style="color:#000147;font-weight:600;">' . cw_email_h($pagina) . '</a></p>';
    }

    $body = cw_email_p('Hola <strong>' . cw_email_h($nombre) . '</strong>,')
        . cw_email_p('Recibimos tu solicitud desde nuestro sitio web. Este correo confirma que tus datos quedaron registrados correctamente.')
        . cw_email_card($boxInner, 'Resumen de tu solicitud')
        . cw_email_p('En un momento te conectaremos por WhatsApp para continuar la conversación con nuestro equipo.');
    if ($waUrl !== '') {
        $body .= cw_email_cta($waUrl, 'Continuar en WhatsApp', 'whatsapp');
    }
    $body .= cw_email_p('Si no iniciaste esta solicitud, puedes ignorar este correo.', 0);

    $html = cw_hub_email_template('Confirmación de tu solicitud', $body);
    $subject = 'Confirmación — recibimos tu solicitud en ConlineWeb';

    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        require_once dirname(__DIR__) . '/PHPMailer/src/Exception.php';
        require_once dirname(__DIR__) . '/PHPMailer/src/PHPMailer.php';
        require_once dirname(__DIR__) . '/PHPMailer/src/SMTP.php';
    }
    require_once dirname(__DIR__) . '/smtp_config_helper.php';

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        configure_phpmailer_by_system($mail, 'conlineweb');
        $mail->Timeout = 15;
        $mail->SMTPKeepAlive = false;
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $html;
        $mail->AltBody = "Hola {$nombre},\n\nRecibimos tu solicitud sobre {$servicioLabel}. Te contactaremos pronto por WhatsApp.\n\nConlineWeb — https://conlineweb.com";
        $mail->send();

        return ['ok' => true, 'error' => ''];
    } catch (Throwable $e) {
        $detail = $e->getMessage();
        if (isset($mail) && !empty($mail->ErrorInfo)) {
            $detail = $mail->ErrorInfo;
        }
        error_log('cw_hub_send_lead_confirmation_email: ' . $detail);

        return ['ok' => false, 'error' => $detail];
    }
}

function cw_hub_notify_reminder(mysqli $conn, array $actividad, array $lead): void
{
    $leadId = (int) $lead['id'];
    $payload = [
        'actividad_id' => (int) $actividad['id'],
        'lead_id' => $leadId,
        'lead_nombre' => $lead['nombre'],
        'tipo' => $actividad['tipo'],
        'descripcion' => $actividad['descripcion'],
        'proxima_accion' => $actividad['proxima_accion'],
        'whatsapp' => $lead['telefono'] ?? '',
        'admin_url' => 'https://adm.conlineweb.com/leads/detalle.php?id=' . $leadId,
    ];

    cw_hub_n8n_dispatch('lead.reminder', $payload);

    $html = '<h2>Recordatorio comercial</h2>'
        . '<p>Lead: <strong>' . htmlspecialchars($lead['nombre']) . '</strong> (#' . $leadId . ')</p>'
        . '<p>' . htmlspecialchars($actividad['descripcion']) . '</p>'
        . '<p>Programado: ' . htmlspecialchars($actividad['proxima_accion']) . '</p>'
        . '<p><a href="' . htmlspecialchars($payload['admin_url']) . '">Abrir ficha</a></p>';
    cw_hub_send_alert_email('Recordatorio lead #' . $leadId, $html);
}

function cw_hub_pending_reminders(mysqli $conn, int $limit = 10): array
{
    $now = date('Y-m-d H:i:s');
    $sql = "SELECT a.*, l.nombre AS lead_nombre, l.telefono, l.pipeline_estado
            FROM cw_lead_actividades a
            INNER JOIN leads l ON l.id = a.lead_id AND l.eliminado = 0
            WHERE a.proxima_accion IS NOT NULL AND a.proxima_accion <= ?
            AND a.recordatorio_notificado = 0
            ORDER BY a.proxima_accion ASC LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $now, $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function cw_hub_upcoming_reminders(mysqli $conn, int $hours = 48, int $limit = 15): array
{
    $from = date('Y-m-d H:i:s');
    $to = date('Y-m-d H:i:s', strtotime("+{$hours} hours"));
    $sql = "SELECT a.*, l.nombre AS lead_nombre, l.telefono
            FROM cw_lead_actividades a
            INNER JOIN leads l ON l.id = a.lead_id AND l.eliminado = 0
            WHERE a.proxima_accion IS NOT NULL AND a.proxima_accion BETWEEN ? AND ?
            ORDER BY a.proxima_accion ASC LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssi', $from, $to, $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}
