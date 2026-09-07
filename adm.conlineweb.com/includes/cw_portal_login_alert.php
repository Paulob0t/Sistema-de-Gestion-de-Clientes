<?php
/**
 * Alerta por correo al iniciar sesión (adm / cliente).
 * No bloquea el login si el correo o la geo fallan.
 */
declare(strict_types=1);

if (!function_exists('cw_portal_login_alert_secrets')) {
    /** @return array<string,mixed> */
    function cw_portal_login_alert_secrets(): array
    {
        static $cached = null;
        if (is_array($cached)) {
            return $cached;
        }
        $candidates = [
            __DIR__ . '/cw_portal_secrets.local.php',
            dirname(__DIR__) . '/includes/cw_portal_secrets.local.php',
            dirname(__DIR__) . '/cw_portal_secrets.local.php',
            '/home/conlineweb/includes/cw_portal_secrets.local.php',
            '/home/conlineweb/cliente.conlineweb.com/includes/cw_portal_secrets.local.php',
            '/home/conlineweb/adm.conlineweb.com/includes/cw_portal_secrets.local.php',
        ];
        foreach ($candidates as $local) {
            if (!is_file($local)) {
                continue;
            }
            $data = include $local;
            if (is_array($data)) {
                $cached = $data;

                return $cached;
            }
        }
        $cached = [];

        return $cached;
    }
}

if (!function_exists('cw_portal_login_alert_enabled')) {
    function cw_portal_login_alert_enabled(): bool
    {
        $flag = getenv('CW_LOGIN_ALERT_ENABLED');
        if ($flag === false || $flag === '') {
            $secrets = cw_portal_login_alert_secrets();
            if (array_key_exists('login_alert_enabled', $secrets)) {
                $flag = (string) $secrets['login_alert_enabled'];
            } else {
                return true;
            }
        }

        return !in_array(strtolower(trim((string) $flag)), ['0', 'false', 'off', 'no'], true);
    }
}

if (!function_exists('cw_portal_login_alert_recipients')) {
    /** @return list<string> */
    function cw_portal_login_alert_recipients(): array
    {
        $raw = trim((string) (getenv('CW_LOGIN_ALERT_TO') ?: ''));
        if ($raw === '') {
            $secrets = cw_portal_login_alert_secrets();
            if (!empty($secrets['login_alert_to'])) {
                $raw = (string) $secrets['login_alert_to'];
            }
        }
        if ($raw === '') {
            $raw = 'servicios@conlineweb.com';
        }
        $out = [];
        foreach (preg_split('/[\s,;]+/', $raw) ?: [] as $email) {
            $email = trim((string) $email);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $out[] = $email;
            }
        }

        return $out !== [] ? array_values(array_unique($out)) : ['servicios@conlineweb.com'];
    }
}

if (!function_exists('cw_portal_login_alert_tipo_label')) {
    function cw_portal_login_alert_tipo_label(int $tipo): string
    {
        switch ($tipo) {
            case 0:
                return 'Cliente';
            case 1:
                return 'Administrador';
            case 2:
                return 'Solicitudes / soporte';
            case 3:
                return 'Agente / desarrollador';
            case 4:
                return 'Empresa externa';
            case 5:
                return 'CRM / Leads';
            default:
                return 'Tipo ' . $tipo;
        }
    }
}

if (!function_exists('cw_portal_login_alert_portal_for_tipo')) {
    /**
     * @return array{portal:string,portal_url:string,entry:string}
     */
    function cw_portal_login_alert_portal_for_tipo(int $tipo): array
    {
        if ($tipo >= 1 && $tipo <= 5) {
            return [
                'portal' => 'Panel Admin (adm.conlineweb.com)',
                'portal_url' => 'https://adm.conlineweb.com/',
                'entry' => 'Login en cliente.conlineweb.com → acceso a adm',
            ];
        }

        return [
            'portal' => 'Área Cliente (cliente.conlineweb.com)',
            'portal_url' => 'https://cliente.conlineweb.com/',
            'entry' => 'Login en cliente.conlineweb.com',
        ];
    }
}

if (!function_exists('cw_portal_login_alert_geo')) {
    /**
     * Ubicación aproximada por IP (ip-api.com, gratis). Timeout corto.
     *
     * @return array{label:string,country:string,region:string,city:string,isp:string}
     */
    function cw_portal_login_alert_geo(string $ip): array
    {
        $empty = ['label' => 'No disponible', 'country' => '', 'region' => '', 'city' => '', 'isp' => ''];
        if ($ip === '' || $ip === '0.0.0.0' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return $empty;
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return ['label' => 'Red local / privada', 'country' => '', 'region' => '', 'city' => '', 'isp' => ''];
        }

        $url = 'http://ip-api.com/json/' . rawurlencode($ip)
            . '?fields=status,message,country,regionName,city,isp,query&lang=es';
        $raw = null;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 2,
                CURLOPT_CONNECTTIMEOUT => 2,
            ]);
            $raw = curl_exec($ch);
            curl_close($ch);
        }
        if (!is_string($raw) || $raw === '') {
            return $empty;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
            return $empty;
        }
        $city = trim((string) ($data['city'] ?? ''));
        $region = trim((string) ($data['regionName'] ?? ''));
        $country = trim((string) ($data['country'] ?? ''));
        $isp = trim((string) ($data['isp'] ?? ''));
        $parts = array_filter([$city, $region, $country], static function ($p) {
            return $p !== '';
        });
        $label = $parts !== [] ? implode(', ', $parts) : 'Ubicación desconocida';

        return [
            'label' => $label,
            'country' => $country,
            'region' => $region,
            'city' => $city,
            'isp' => $isp,
        ];
    }
}

if (!function_exists('cw_portal_login_alert_file_log')) {
    /** Log local legible en cPanel (cliente/.../logs/login_alerts.log). */
    function cw_portal_login_alert_file_log(string $message): void
    {
        $dirs = [
            // Cuando el include vive en cliente.../includes → logs junto al sitio
            dirname(__DIR__) . '/logs',
            __DIR__ . '/../logs',
            // cPanel absolutos
            '/home/conlineweb/cliente.conlineweb.com/logs',
            // shared sistema/includes → sistema/cliente.../logs
            dirname(__DIR__) . '/cliente.conlineweb.com/logs',
            __DIR__ . '/logs',
            sys_get_temp_dir() . '/cw_login_alerts_log',
        ];
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
        error_log('cw_login_alert: ' . $message);
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            if (is_dir($dir) && is_writable($dir)) {
                @file_put_contents($dir . '/login_alerts.log', $line, FILE_APPEND | LOCK_EX);
                break;
            }
        }
    }
}

if (!function_exists('cw_portal_login_alert_debounce_file')) {
    function cw_portal_login_alert_debounce_file(int $uid, string $ip, string $portalKey): string
    {
        $dir = sys_get_temp_dir() . '/cw_login_alerts';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        return $dir . '/' . hash('sha256', $uid . '|' . $ip . '|' . $portalKey) . '.ts';
    }
}

if (!function_exists('cw_portal_login_alert_should_send')) {
    /** Solo consulta: no marca enviado (marcar tras SMTP OK). */
    function cw_portal_login_alert_should_send(int $uid, string $ip, string $portalKey, int $windowSec = 600): bool
    {
        $file = cw_portal_login_alert_debounce_file($uid, $ip, $portalKey);
        $now = time();
        if (is_file($file)) {
            $prev = (int) @file_get_contents($file);
            if ($prev > 0 && ($now - $prev) < $windowSec) {
                cw_portal_login_alert_file_log("debounce SKIP uid={$uid} ip={$ip} key={$portalKey} wait=" . ($windowSec - ($now - $prev)) . 's');

                return false;
            }
        }

        return true;
    }
}

if (!function_exists('cw_portal_login_alert_mark_sent')) {
    function cw_portal_login_alert_mark_sent(int $uid, string $ip, string $portalKey): void
    {
        $file = cw_portal_login_alert_debounce_file($uid, $ip, $portalKey);
        @file_put_contents($file, (string) time(), LOCK_EX);
    }
}

if (!function_exists('cw_portal_login_alert_smtp')) {
    /** @return array{host:string,port:int,user:string,pass:string,from:string,from_name:string} */
    function cw_portal_login_alert_smtp(): array
    {
        $secrets = cw_portal_login_alert_secrets();
        $user = trim((string) (getenv('SMTP_USERNAME') ?: getenv('CW_SMTP_USER') ?: ($secrets['smtp_user'] ?? '') ?: 'servicios@conlineweb.com'));
        $pass = trim((string) (getenv('SMTP_PASSWORD') ?: getenv('CW_SMTP_PASS') ?: ($secrets['smtp_pass'] ?? '') ?: 'wcglkgcxfebsauqo'));
        $from = trim((string) (getenv('SMTP_FROM_EMAIL') ?: ($secrets['smtp_from'] ?? '') ?: $user));

        return [
            'host' => trim((string) (getenv('SMTP_HOST') ?: ($secrets['smtp_host'] ?? '') ?: 'smtp.gmail.com')),
            'port' => (int) (getenv('SMTP_PORT') ?: ($secrets['smtp_port'] ?? 0) ?: 587),
            'user' => $user,
            'pass' => $pass,
            'from' => $from,
            'from_name' => trim((string) (getenv('SMTP_FROM_NAME') ?: ($secrets['smtp_from_name'] ?? '') ?: 'ConlineWeb Seguridad')),
        ];
    }
}

if (!function_exists('cw_portal_login_alert_load_phpmailer')) {
    function cw_portal_login_alert_load_phpmailer(): bool
    {
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer', false)) {
            return true;
        }
        // __DIR__ puede ser sistema/includes, cliente/.../includes o adm/.../includes
        $candidates = [
            __DIR__ . '/../PHPMailer/src',
            __DIR__ . '/../../PHPMailer/src',
            __DIR__ . '/../../cliente.conlineweb.com/PHPMailer/src',
            __DIR__ . '/../../adm.conlineweb.com/PHPMailer/src',
            dirname(__DIR__) . '/PHPMailer/src',
            dirname(__DIR__) . '/cliente.conlineweb.com/PHPMailer/src',
            dirname(__DIR__) . '/adm.conlineweb.com/PHPMailer/src',
            '/home/conlineweb/cliente.conlineweb.com/PHPMailer/src',
            '/home/conlineweb/adm.conlineweb.com/PHPMailer/src',
            '/home/conlineweb/public_html/cliente.conlineweb.com/PHPMailer/src',
            '/home/conlineweb/public_html/PHPMailer/src',
        ];
        foreach ($candidates as $base) {
            if (is_file($base . '/PHPMailer.php')) {
                require_once $base . '/Exception.php';
                require_once $base . '/PHPMailer.php';
                require_once $base . '/SMTP.php';

                return class_exists('PHPMailer\\PHPMailer\\PHPMailer', false);
            }
        }
        error_log('cw_portal_login_alert_load_phpmailer: no se encontró PHPMailer en rutas conocidas');

        return false;
    }
}

if (!function_exists('cw_portal_login_alert_row')) {
    function cw_portal_login_alert_row(string $label, string $value): string
    {
        return '<tr>'
            . '<td style="padding:10px 12px;border-bottom:1px solid #e8ecf3;color:#5b6475;font-size:13px;width:34%;vertical-align:top;">'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td style="padding:10px 12px;border-bottom:1px solid #e8ecf3;color:#0f172a;font-size:14px;font-weight:600;word-break:break-word;">'
            . $value . '</td></tr>';
    }
}

if (!function_exists('cw_portal_login_alert_build_html')) {
    /**
     * @param array{title:string,eyebrow:string,accent:string,rows:list<array{0:string,1:string}>,note:string,cta_url?:string,cta_label?:string} $opts
     */
    function cw_portal_login_alert_build_html(array $opts): string
    {
        $accent = $opts['accent'] ?? '#000147';
        $rowsHtml = '';
        foreach ($opts['rows'] as $row) {
            $rowsHtml .= cw_portal_login_alert_row($row[0], $row[1]);
        }
        $cta = '';
        if (!empty($opts['cta_url'])) {
            $cta = '<p style="margin:22px 0 0;text-align:center;">'
                . '<a href="' . htmlspecialchars((string) $opts['cta_url'], ENT_QUOTES, 'UTF-8') . '" '
                . 'style="display:inline-block;background:' . htmlspecialchars($accent, ENT_QUOTES, 'UTF-8')
                . ';color:#fff;text-decoration:none;font-weight:700;font-size:14px;padding:12px 22px;border-radius:10px;">'
                . htmlspecialchars((string) ($opts['cta_label'] ?? 'Ver en el panel'), ENT_QUOTES, 'UTF-8')
                . '</a></p>';
        }

        return '<div style="margin:0;padding:0;background:#eef1f7;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef1f7;padding:24px 12px;">'
            . '<tr><td align="center">'
            . '<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #d9e0ec;">'
            . '<tr><td style="background:' . htmlspecialchars($accent, ENT_QUOTES, 'UTF-8') . ';padding:22px 24px;">'
            . '<div style="font-family:Arial,Helvetica,sans-serif;color:#aeb6d4;font-size:12px;letter-spacing:.08em;text-transform:uppercase;font-weight:700;">'
            . htmlspecialchars((string) $opts['eyebrow'], ENT_QUOTES, 'UTF-8') . '</div>'
            . '<div style="font-family:Arial,Helvetica,sans-serif;color:#ffffff;font-size:22px;font-weight:800;margin-top:6px;line-height:1.25;">'
            . htmlspecialchars((string) $opts['title'], ENT_QUOTES, 'UTF-8') . '</div>'
            . '</td></tr>'
            . '<tr><td style="padding:8px 12px 4px;font-family:Arial,Helvetica,sans-serif;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">'
            . $rowsHtml
            . '</table>'
            . $cta
            . '<p style="margin:18px 12px 20px;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.5;color:#6b7280;">'
            . htmlspecialchars((string) $opts['note'], ENT_QUOTES, 'UTF-8')
            . '</p>'
            . '</td></tr>'
            . '<tr><td style="background:#f7f9fc;padding:14px 24px;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#8a93a6;text-align:center;">'
            . 'ConlineWeb · Alerta de seguridad del portal'
            . '</td></tr>'
            . '</table></td></tr></table></div>';
    }
}

if (!function_exists('cw_portal_login_alert_queue_dir')) {
    function cw_portal_login_alert_queue_dir(): string
    {
        $dirs = [
            dirname(__DIR__) . '/logs/login_alert_queue',
            __DIR__ . '/../logs/login_alert_queue',
            '/home/conlineweb/cliente.conlineweb.com/logs/login_alert_queue',
            sys_get_temp_dir() . '/cw_login_alert_queue',
        ];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            if (is_dir($dir) && is_writable($dir)) {
                return $dir;
            }
        }

        return sys_get_temp_dir();
    }
}

if (!function_exists('cw_portal_login_alert_queue_push')) {
    /** Guarda correo fallido para reintento (siguiente login / flush). */
    function cw_portal_login_alert_queue_push(string $subject, string $html, string $alt): void
    {
        $dir = cw_portal_login_alert_queue_dir();
        $file = $dir . '/q_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.json';
        $payload = json_encode([
            'subject' => $subject,
            'html' => $html,
            'alt' => $alt,
            'created' => time(),
        ], JSON_UNESCAPED_UNICODE);
        if ($payload !== false) {
            @file_put_contents($file, $payload, LOCK_EX);
            cw_portal_login_alert_file_log('queued FAIL mail → ' . $file);
        }
    }
}

if (!function_exists('cw_portal_login_alert_queue_flush')) {
    /** Reintenta hasta N correos en cola (no bloquea mucho el login). */
    function cw_portal_login_alert_queue_flush(int $max = 2): int
    {
        $dir = cw_portal_login_alert_queue_dir();
        $files = glob($dir . '/q_*.json') ?: [];
        sort($files);
        $sent = 0;
        foreach (array_slice($files, 0, $max) as $file) {
            $raw = @file_get_contents($file);
            $data = is_string($raw) ? json_decode($raw, true) : null;
            if (!is_array($data) || empty($data['subject']) || empty($data['html'])) {
                @unlink($file);
                continue;
            }
            // Evitar bucles: enviar sin volver a encolar
            $ok = cw_portal_login_alert_send_mail_direct(
                (string) $data['subject'],
                (string) $data['html'],
                (string) ($data['alt'] ?? ''),
                false
            );
            if ($ok) {
                @unlink($file);
                $sent++;
                cw_portal_login_alert_file_log('queue FLUSH OK ' . basename($file));
            } else {
                // Si lleva >24h, descartar
                $created = (int) ($data['created'] ?? 0);
                if ($created > 0 && (time() - $created) > 86400) {
                    @unlink($file);
                    cw_portal_login_alert_file_log('queue DROP stale ' . basename($file));
                }
                break;
            }
        }

        return $sent;
    }
}

if (!function_exists('cw_portal_login_alert_send_mail_direct')) {
    function cw_portal_login_alert_send_mail_direct(string $subject, string $html, string $alt, bool $enqueueOnFail = true): bool
    {
        $recipients = cw_portal_login_alert_recipients();
        $toList = implode(',', $recipients);
        cw_portal_login_alert_file_log('send_mail start → ' . $toList . ' | ' . $subject);

        if (!cw_portal_login_alert_load_phpmailer()) {
            cw_portal_login_alert_file_log('PHPMailer no disponible, intentando mail()');
        } else {
            $smtp = cw_portal_login_alert_smtp();
            $attempts = [
                ['secure' => 'tls', 'port' => (int) ($smtp['port'] ?: 587)],
                ['secure' => 'ssl', 'port' => 465],
            ];
            if ((int) $smtp['port'] === 465) {
                $attempts = [
                    ['secure' => 'ssl', 'port' => 465],
                    ['secure' => 'tls', 'port' => 587],
                ];
            }

            foreach ($attempts as $attempt) {
                try {
                    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host = $smtp['host'];
                    $mail->SMTPAuth = true;
                    $mail->Username = $smtp['user'];
                    $mail->Password = $smtp['pass'];
                    $mail->SMTPSecure = $attempt['secure'];
                    $mail->Port = $attempt['port'];
                    $mail->Timeout = 8;
                    $mail->SMTPKeepAlive = false;
                    $mail->CharSet = 'UTF-8';
                    $mail->Encoding = 'base64';
                    $mail->SMTPOptions = [
                        'ssl' => [
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true,
                        ],
                    ];
                    $mail->setFrom($smtp['from'], $smtp['from_name']);
                    foreach ($recipients as $to) {
                        $mail->addAddress($to);
                    }
                    $mail->isHTML(true);
                    $mail->Subject = $subject;
                    $mail->Body = $html;
                    $mail->AltBody = $alt;
                    $mail->send();
                    cw_portal_login_alert_file_log(
                        'OK SMTP ' . $attempt['secure'] . ':' . $attempt['port']
                        . ' user=' . $smtp['user']
                        . ' → ' . $toList . ' | ' . $subject
                    );

                    return true;
                } catch (Throwable $e) {
                    cw_portal_login_alert_file_log(
                        'SMTP FAIL ' . $attempt['secure'] . ':' . $attempt['port'] . ': ' . $e->getMessage()
                    );
                }
            }
        }

        $toHeader = implode(', ', $recipients);
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ConlineWeb Seguridad <servicios@conlineweb.com>',
            'Reply-To: servicios@conlineweb.com',
            'X-Mailer: CW-Portal-Login-Alert',
        ];
        $ok = @mail($toHeader, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, implode("\r\n", $headers));
        cw_portal_login_alert_file_log('fallback mail() ' . ($ok ? 'OK' : 'FAIL') . ' → ' . $toHeader . ' | ' . $subject);

        if (!$ok && $enqueueOnFail) {
            cw_portal_login_alert_queue_push($subject, $html, $alt);
        }

        return (bool) $ok;
    }
}

if (!function_exists('cw_portal_login_alert_send_mail')) {
    function cw_portal_login_alert_send_mail(string $subject, string $html, string $alt): bool
    {
        // Primero vaciar cola vieja (máx 1) para no acumular
        try {
            cw_portal_login_alert_queue_flush(1);
        } catch (Throwable $e) {
            // ignore
        }

        return cw_portal_login_alert_send_mail_direct($subject, $html, $alt, true);
    }
}

if (!function_exists('cw_portal_notify_login')) {
    /**
     * @param array{
     *   uid:int,
     *   usuario?:string,
     *   tipo:int,
     *   ip?:string,
     *   user_agent?:string,
     *   agente_id?:int|null,
     *   environment?:string,
     *   geo?:array{label?:string,isp?:string}|null,
     *   skip_geo?:bool,
     *   force?:bool
     * } $ctx
     */
    function cw_portal_notify_login(array $ctx): bool
    {
        try {
            if (!cw_portal_login_alert_enabled()) {
                cw_portal_login_alert_file_log('notify_login SKIP: disabled');
                return false;
            }

            $uid = (int) ($ctx['uid'] ?? 0);
            $tipo = (int) ($ctx['tipo'] ?? -1);
            $usuario = trim((string) ($ctx['usuario'] ?? ''));
            $ip = trim((string) ($ctx['ip'] ?? ''));
            if ($ip === '' && function_exists('cw_portal_client_ip')) {
                $ip = cw_portal_client_ip();
            }
            $ua = trim((string) ($ctx['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')));
            $env = trim((string) ($ctx['environment'] ?? ''));
            if ($env === '') {
                $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
                $env = preg_match('/(^|\.)conlineweb\.com$/', $host) ? 'producción' : 'local';
            }

            $portalMeta = cw_portal_login_alert_portal_for_tipo($tipo);
            $portalKey = ($tipo >= 1 && $tipo <= 5) ? 'adm' : 'cliente';
            $sessionType = ($portalKey === 'adm' ? 'ADM' : 'Cliente') . ' · ' . cw_portal_login_alert_tipo_label($tipo);

            if ($uid <= 0) {
                cw_portal_login_alert_file_log('notify_login SKIP: uid inválido');
                return false;
            }
            if (empty($ctx['force']) && !cw_portal_login_alert_should_send($uid, $ip, $portalKey)) {
                return false;
            }

            // Geo opcional: en cPanel suele fallar/timeout; no bloquear el correo
            $skipGeo = !empty($ctx['skip_geo']);
            $geo = is_array($ctx['geo'] ?? null)
                ? $ctx['geo']
                : ($skipGeo ? ['label' => '—', 'isp' => ''] : cw_portal_login_alert_geo($ip));
            $when = (new DateTimeImmutable('now', new DateTimeZone('America/Mexico_City')))->format('d/m/Y H:i:s T');
            $tipoLabel = cw_portal_login_alert_tipo_label($tipo);
            $usuarioShow = $usuario !== '' ? $usuario : ('UID #' . $uid);
            $uaShort = function_exists('mb_substr') ? mb_substr($ua, 0, 240) : substr($ua, 0, 240);
            $h = static function ($s) {
                return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
            };

            $subject = '[Login] ' . $usuarioShow . ' → ' . ($portalKey === 'adm' ? 'ADM' : 'CLIENTE');
            $panelUrl = 'https://adm.conlineweb.com/seguridad/accesos.php?filter=success&q=' . rawurlencode($usuarioShow);

            $html = cw_portal_login_alert_build_html([
                'eyebrow' => 'ConlineWeb Seguridad',
                'title' => 'Nuevo inicio de sesión',
                'accent' => '#000147',
                'rows' => [
                    ['Usuario', $h($usuarioShow)],
                    ['UID', (string) $uid],
                    ['Tipo de sesión', $h($sessionType)],
                    ['Rol', $h($tipoLabel . ' (' . $tipo . ')')],
                    ['Portal', $h($portalMeta['portal'])],
                    ['Entrada', $h($portalMeta['entry'])],
                    ['Fecha (CDMX)', $h($when)],
                    ['IP', $h($ip !== '' ? $ip : '—')],
                    ['Ubicación aprox.', $h((string) ($geo['label'] ?? 'No disponible'))],
                    ['ISP', $h(((string) ($geo['isp'] ?? '')) !== '' ? (string) $geo['isp'] : '—')],
                    ['Entorno', $h($env)],
                    ['Navegador', $h($uaShort)],
                ],
                'note' => 'Si no reconoces este acceso, cambia la contraseña del usuario y revisa el historial en el panel.',
                'cta_url' => $panelUrl,
                'cta_label' => 'Ver historial de accesos',
            ]);

            cw_portal_login_alert_file_log("notify_login intentando uid={$uid} user={$usuarioShow} ip={$ip}");

            $ok = cw_portal_login_alert_send_mail(
                $subject,
                $html,
                "Login: {$usuarioShow} (UID {$uid}) | Portal: {$portalMeta['portal']} | IP: {$ip} | Ubicación: " . ($geo['label'] ?? '') . " | {$when}"
            );
            if ($ok) {
                cw_portal_login_alert_mark_sent($uid, $ip, $portalKey);
            }

            return $ok;
        } catch (Throwable $e) {
            cw_portal_login_alert_file_log('notify_login EX: ' . $e->getMessage());
            error_log('cw_portal_notify_login: ' . $e->getMessage());

            return false;
        }
    }
}

if (!function_exists('cw_portal_notify_login_blocked')) {
    /**
     * Aviso puntual de IP bloqueada por rate limit / spam (debounce por IP).
     *
     * @param array{ip?:string,attempted_user?:string,retry_after?:int,user_agent?:string} $ctx
     */
    function cw_portal_notify_login_blocked(array $ctx): bool
    {
        try {
            if (!cw_portal_login_alert_enabled()) {
                return false;
            }
            $ip = trim((string) ($ctx['ip'] ?? ''));
            if ($ip === '' && function_exists('cw_portal_client_ip')) {
                $ip = cw_portal_client_ip();
            }
            if ($ip === '') {
                return false;
            }
            if (empty($ctx['force']) && !cw_portal_login_alert_should_send(0, $ip, 'blocked', 1800)) {
                return false;
            }

            $attempted = trim((string) ($ctx['attempted_user'] ?? ''));
            $ua = trim((string) ($ctx['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')));
            $geo = is_array($ctx['geo'] ?? null) ? $ctx['geo'] : cw_portal_login_alert_geo($ip);
            $when = (new DateTimeImmutable('now', new DateTimeZone('America/Mexico_City')))->format('d/m/Y H:i:s T');
            $retry = (int) ($ctx['retry_after'] ?? 0);
            $uaShort = function_exists('mb_substr') ? mb_substr($ua, 0, 240) : substr($ua, 0, 240);
            $h = static function ($s) {
                return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
            };
            $skipGeo = !empty($ctx['skip_geo']);
            if ($skipGeo) {
                $geo = ['label' => '—', 'isp' => ''];
            }

            cw_portal_login_alert_file_log("notify_blocked intentando ip={$ip}");
            $html = cw_portal_login_alert_build_html([
                'eyebrow' => 'ConlineWeb Seguridad',
                'title' => 'Posible spam / IP bloqueada',
                'accent' => '#7f1d1d',
                'rows' => [
                    ['IP', $h($ip)],
                    ['Usuario intentado', $h($attempted !== '' ? $attempted : '—')],
                    ['Reintento en', $h($retry > 0 ? $retry . ' s' : 'unos minutos')],
                    ['Fecha (CDMX)', $h($when)],
                    ['Ubicación aprox.', $h((string) ($geo['label'] ?? 'No disponible'))],
                    ['ISP', $h(((string) ($geo['isp'] ?? '')) !== '' ? (string) $geo['isp'] : '—')],
                    ['Navegador', $h($uaShort)],
                ],
                'note' => 'Se bloqueó temporalmente por demasiados intentos de login. Revisa el historial de fallos en el panel.',
                'cta_url' => 'https://adm.conlineweb.com/seguridad/accesos.php?filter=blocked&q=' . rawurlencode($ip),
                'cta_label' => 'Ver bloqueos',
            ]);

            $sent = cw_portal_login_alert_send_mail(
                '[Login bloqueado] IP ' . $ip,
                $html,
                "Login bloqueado | IP: {$ip} | Usuario: {$attempted} | {$when}"
            );
            if ($sent) {
                cw_portal_login_alert_mark_sent(0, $ip, 'blocked');
            }

            return $sent;
        } catch (Throwable $e) {
            cw_portal_login_alert_file_log('notify_blocked EX: ' . $e->getMessage());
            error_log('cw_portal_notify_login_blocked: ' . $e->getMessage());

            return false;
        }
    }
}

if (!function_exists('cw_portal_login_alert_fail_reason_label')) {
    function cw_portal_login_alert_fail_reason_label(string $reason): string
    {
        switch ($reason) {
            case 'bad_password':
                return 'Contraseña incorrecta';
            case 'user_not_found':
                return 'Usuario no existe';
            case 'empty_credentials':
                return 'Usuario o contraseña vacíos';
            case 'recaptcha':
                return 'Captcha inválido / incompleto';
            default:
                return $reason !== '' ? $reason : 'Intento fallido';
        }
    }
}

if (!function_exists('cw_portal_notify_login_failed')) {
    /**
     * Alerta de login fallido (debounce por IP + usuario intentado).
     *
     * @param array{
     *   attempted_user?:string,
     *   usuario?:string,
     *   uid?:int|null,
     *   tipo?:int|null,
     *   ip?:string,
     *   user_agent?:string,
     *   reason?:string,
     *   event_type?:string
     * } $ctx
     */
    function cw_portal_notify_login_failed(array $ctx): bool
    {
        try {
            if (!cw_portal_login_alert_enabled()) {
                return false;
            }

            $ip = trim((string) ($ctx['ip'] ?? ''));
            if ($ip === '' && function_exists('cw_portal_client_ip')) {
                $ip = cw_portal_client_ip();
            }
            $attempted = trim((string) ($ctx['attempted_user'] ?? $ctx['usuario'] ?? ''));
            $debounceUser = function_exists('mb_strtolower')
                ? mb_strtolower($attempted !== '' ? $attempted : '_empty')
                : strtolower($attempted !== '' ? $attempted : '_empty');
            $debounceKey = 'fail:' . $debounceUser;
            if ($ip === '') {
                return false;
            }
            if (empty($ctx['force']) && !cw_portal_login_alert_should_send(0, $ip, $debounceKey, 600)) {
                return false;
            }

            $skipGeo = !empty($ctx['skip_geo']);
            $geo = is_array($ctx['geo'] ?? null)
                ? $ctx['geo']
                : ($skipGeo ? ['label' => '—', 'isp' => ''] : cw_portal_login_alert_geo($ip));
            $when = (new DateTimeImmutable('now', new DateTimeZone('America/Mexico_City')))->format('d/m/Y H:i:s T');
            $ua = trim((string) ($ctx['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')));
            $uaShort = function_exists('mb_substr') ? mb_substr($ua, 0, 240) : substr($ua, 0, 240);
            $reason = trim((string) ($ctx['reason'] ?? ''));
            $eventType = trim((string) ($ctx['event_type'] ?? 'fail'));
            $uid = isset($ctx['uid']) ? (int) $ctx['uid'] : 0;
            $tipo = array_key_exists('tipo', $ctx) && $ctx['tipo'] !== null ? (int) $ctx['tipo'] : null;
            $usuarioShow = trim((string) ($ctx['usuario'] ?? $attempted));
            if ($usuarioShow === '') {
                $usuarioShow = '—';
            }
            $h = static function ($s) {
                return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
            };
            $motivo = cw_portal_login_alert_fail_reason_label($reason !== '' ? $reason : $eventType);
            $rol = $tipo !== null ? cw_portal_login_alert_tipo_label($tipo) : 'Sin identificar';
            $portalKey = ($tipo !== null && $tipo >= 1 && $tipo <= 5) ? 'adm' : 'cliente';
            $sessionType = ($portalKey === 'adm' ? 'ADM' : 'Cliente') . ' · ' . $rol;
            $q = $attempted !== '' ? $attempted : $ip;

            cw_portal_login_alert_file_log("notify_failed intentando user={$usuarioShow} ip={$ip}");
            $html = cw_portal_login_alert_build_html([
                'eyebrow' => 'ConlineWeb Seguridad',
                'title' => 'Intento de login fallido',
                'accent' => '#9a3412',
                'rows' => [
                    ['Usuario intentado', $h($usuarioShow)],
                    ['UID', $uid > 0 ? (string) $uid : '—'],
                    ['Tipo de sesión', $h($sessionType)],
                    ['Rol', $h($tipo !== null ? $rol . ' (' . $tipo . ')' : '—')],
                    ['Motivo', $h($motivo)],
                    ['Fecha (CDMX)', $h($when)],
                    ['IP', $h($ip)],
                    ['Ubicación aprox.', $h((string) ($geo['label'] ?? 'No disponible'))],
                    ['ISP', $h(((string) ($geo['isp'] ?? '')) !== '' ? (string) $geo['isp'] : '—')],
                    ['Navegador', $h($uaShort)],
                ],
                'note' => 'No se concedió acceso. Si se repite, puede ser un ataque de fuerza bruta; revisa el historial y considera bloquear la IP.',
                'cta_url' => 'https://adm.conlineweb.com/seguridad/accesos.php?filter=fail&q=' . rawurlencode($q),
                'cta_label' => 'Ver fallos en el panel',
            ]);

            $sent = cw_portal_login_alert_send_mail(
                '[Login fallido] ' . $usuarioShow . ' · ' . $ip,
                $html,
                "Login fallido | Usuario: {$usuarioShow} | Motivo: {$motivo} | IP: {$ip} | {$when}"
            );
            if ($sent) {
                cw_portal_login_alert_mark_sent(0, $ip, $debounceKey);
            }

            return $sent;
        } catch (Throwable $e) {
            cw_portal_login_alert_file_log('notify_failed EX: ' . $e->getMessage());
            error_log('cw_portal_notify_login_failed: ' . $e->getMessage());

            return false;
        }
    }
}
