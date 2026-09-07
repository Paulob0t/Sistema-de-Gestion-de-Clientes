<?php
/**
 * Alertas IA → campanita (adm) + correo electrónico.
 * Se disparan al crear propuestas (contenido, mantenimiento, mejoras).
 */
declare(strict_types=1);

require_once __DIR__ . '/cw_hub_notify.php';
require_once __DIR__ . '/adm_paths.php';

function cw_site_ai_alerts_ensure_table(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS cw_site_ai_alerts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            proposal_id BIGINT UNSIGNED DEFAULT NULL,
            kind VARCHAR(40) NOT NULL DEFAULT '',
            title VARCHAR(255) NOT NULL DEFAULT '',
            body TEXT NOT NULL,
            url VARCHAR(500) NOT NULL DEFAULT '',
            email_sent TINYINT(1) NOT NULL DEFAULT 0,
            email_error VARCHAR(255) DEFAULT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_by INT UNSIGNED DEFAULT 0,
            created_at DATETIME NOT NULL,
            read_at DATETIME DEFAULT NULL,
            KEY idx_read_created (is_read, created_at),
            KEY idx_proposal (proposal_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function cw_site_ai_alerts_kind_label(string $kind): string
{
    return match ($kind) {
        'blog_post' => 'Blog nuevo',
        'blog_improve' => 'Mejora de blog',
        'hub_text' => 'Página / hub',
        'site_patch' => 'Mantenimiento web',
        'design_ui' => 'Diseño UI (preview)',
        'admin_patch' => 'Mejora vista admin',
        'admin_validate' => 'Validación de updates',
        'cliente_patch' => 'Mejora portal cliente',
        default => 'Propuesta IA',
    };
}

/**
 * @param array<string,mixed> $proposal Meta: id, kind, title, target_url, prompt_summary, after
 * @return array{ok:bool,alert_id?:int,email?:array,error?:string}
 */
function cw_site_ai_alerts_notify_proposal(mysqli $conn, array $proposal, int $userId = 0): array
{
    cw_site_ai_alerts_ensure_table($conn);

    $proposalId = (int) ($proposal['id'] ?? $proposal['proposal_id'] ?? 0);
    $kind = (string) ($proposal['kind'] ?? '');
    $title = trim((string) ($proposal['title'] ?? 'Nueva propuesta IA'));
    $targetUrl = trim((string) ($proposal['target_url'] ?? ''));
    $summary = trim((string) ($proposal['prompt_summary'] ?? ''));
    $after = is_array($proposal['after'] ?? null) ? $proposal['after'] : [];

    if ($summary === '' && !empty($after['summary'])) {
        $summary = trim((string) $after['summary']);
    }
    if ($summary === '' && !empty($after['rationale'])) {
        $summary = trim((string) $after['rationale']);
    }
    if ($summary === '' && !empty($proposal['detail'])) {
        $summary = trim((string) $proposal['detail']);
    }
    if ($summary === '') {
        $summary = 'La IA generó una propuesta pendiente de tu aprobación.';
    }
    $detail = trim((string) ($proposal['detail'] ?? $after['detail'] ?? ''));
    $rationale = trim((string) ($after['rationale'] ?? ''));

    $kindLabel = cw_site_ai_alerts_kind_label($kind);
    $monitorUrl = function_exists('adm_href')
        ? (string) adm_href('analytics/seo_mexico_monitor.php')
        : 'https://adm.conlineweb.com/analytics/seo_mexico_monitor.php';
    // Absolute monitor URL for email
    $monitorAbs = 'https://adm.conlineweb.com/analytics/seo_mexico_monitor.php';
    if ($proposalId > 0) {
        $monitorAbs .= '?highlight=' . $proposalId;
        $monitorUrl .= (str_contains($monitorUrl, '?') ? '&' : '?') . 'highlight=' . $proposalId;
    }

    $body = $kindLabel . ': ' . mb_substr($summary, 0, 400);
    if ($targetUrl !== '') {
        $urlKind = (string) ($after['url_kind'] ?? '');
        $urlPrefix = $urlKind === 'new_page' ? 'URL nueva' : 'URL';
        $body .= ' · ' . $urlPrefix . ': ' . $targetUrl;
    }

    $stmt = $conn->prepare(
        'INSERT INTO cw_site_ai_alerts
         (proposal_id, kind, title, body, url, email_sent, is_read, created_by, created_at)
         VALUES (?, ?, ?, ?, ?, 0, 0, ?, NOW())'
    );
    if (!$stmt) {
        return ['ok' => false, 'error' => 'No se pudo guardar alerta'];
    }
    $uid = max(0, $userId);
    $alertTitle = 'IA · ' . $kindLabel;
    $stmt->bind_param(
        'issssi',
        $proposalId,
        $kind,
        $alertTitle,
        $body,
        $monitorUrl,
        $uid
    );
    $stmt->execute();
    $alertId = (int) $stmt->insert_id;
    $stmt->close();

    // Correo
    $emailHtml = '<div style="font-family:\'Montserrat\',Arial,Helvetica,sans-serif;max-width:640px;margin:0 auto;color:#0f172a">'
        . '<h2 style="margin:0 0 8px;color:#000147;font-family:\'Montserrat\',Arial,Helvetica,sans-serif">ConlineWeb · Alerta IA</h2>'
        . '<p style="margin:0 0 16px;color:#475569;font-family:\'Montserrat\',Arial,Helvetica,sans-serif">El cerebro del sitio tiene una propuesta lista para tu revisión.</p>'
        . '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px;margin-bottom:16px;font-family:\'Montserrat\',Arial,Helvetica,sans-serif">'
        . '<p style="margin:0 0 6px"><strong>Tipo:</strong> ' . htmlspecialchars($kindLabel, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p style="margin:0 0 6px"><strong>Título:</strong> ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p style="margin:0 0 6px"><strong>Qué quiere hacer:</strong><br>'
        . htmlspecialchars(mb_substr($summary, 0, 500), ENT_QUOTES, 'UTF-8') . '</p>';
    if ($rationale !== '' && $rationale !== $summary) {
        $emailHtml .= '<p style="margin:8px 0 0"><strong>Fundamento:</strong><br>'
            . htmlspecialchars(mb_substr($rationale, 0, 500), ENT_QUOTES, 'UTF-8') . '</p>';
    } elseif ($detail !== '' && !str_starts_with($detail, $summary)) {
        $emailHtml .= '<p style="margin:8px 0 0"><strong>Detalle:</strong><br>'
            . nl2br(htmlspecialchars(mb_substr($detail, 0, 700), ENT_QUOTES, 'UTF-8')) . '</p>';
    }
    if ($targetUrl !== '') {
        $urlKind = (string) ($after['url_kind'] ?? '');
        $urlLabelMail = $urlKind === 'new_page'
            ? 'URL nueva'
            : (string) ($after['url_label'] ?? 'URL donde se aplica');
        $emailHtml .= '<p style="margin:8px 0 0"><strong>' . htmlspecialchars($urlLabelMail, ENT_QUOTES, 'UTF-8') . ':</strong> '
            . '<a href="' . htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8') . '</a></p>';
    }
    $emailHtml .= '</div>'
        . '<p style="margin:0 0 18px"><a href="' . htmlspecialchars($monitorAbs, ENT_QUOTES, 'UTF-8') . '" '
        . 'style="display:inline-block;background:#000147;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:600">'
        . 'Revisar en Monitor IA</a></p>'
        . '<p style="font-size:12px;color:#94a3b8;margin:0">Nada se publica solo. Debes aprobar en el Monitor.</p>'
        . '</div>';

    $email = cw_hub_send_alert_email(
        '[IA ConlineWeb] ' . $kindLabel . ': ' . mb_substr($title, 0, 80),
        $emailHtml
    );

    $emailOk = !empty($email['ok']) ? 1 : 0;
    $emailErr = !empty($email['error']) ? mb_substr((string) $email['error'], 0, 255) : null;
    if ($alertId > 0) {
        if ($emailErr !== null) {
            $u = $conn->prepare('UPDATE cw_site_ai_alerts SET email_sent = ?, email_error = ? WHERE id = ?');
            if ($u) {
                $u->bind_param('isi', $emailOk, $emailErr, $alertId);
                $u->execute();
                $u->close();
            }
        } else {
            $conn->query('UPDATE cw_site_ai_alerts SET email_sent = ' . (int) $emailOk . ' WHERE id = ' . (int) $alertId);
        }
    }

    return [
        'ok' => true,
        'alert_id' => $alertId,
        'email' => $email,
    ];
}

/**
 * @return list<array<string,mixed>>
 */
function cw_site_ai_alerts_list(mysqli $conn, int $limit = 25, bool $unreadOnly = false): array
{
    cw_site_ai_alerts_ensure_table($conn);
    $limit = max(1, min(50, $limit));
    $sql = 'SELECT id, proposal_id, kind, title, body, url, email_sent, is_read, created_at
            FROM cw_site_ai_alerts';
    if ($unreadOnly) {
        $sql .= ' WHERE is_read = 0';
    }
    $sql .= ' ORDER BY id DESC LIMIT ' . $limit;
    $out = [];
    $res = $conn->query($sql);
    while ($res && ($r = $res->fetch_assoc())) {
        $out[] = $r;
    }
    return $out;
}

function cw_site_ai_alerts_unread_count(mysqli $conn): int
{
    cw_site_ai_alerts_ensure_table($conn);
    $res = $conn->query('SELECT COUNT(*) AS c FROM cw_site_ai_alerts WHERE is_read = 0');
    if (!$res) {
        return 0;
    }
    $row = $res->fetch_assoc();
    return (int) ($row['c'] ?? 0);
}

function cw_site_ai_alerts_mark_read(mysqli $conn, int $id = 0, bool $all = false): array
{
    cw_site_ai_alerts_ensure_table($conn);
    if ($all) {
        $conn->query('UPDATE cw_site_ai_alerts SET is_read = 1, read_at = NOW() WHERE is_read = 0');
        return ['ok' => true, 'message' => 'Alertas marcadas como leídas'];
    }
    if ($id < 1) {
        return ['ok' => false, 'error' => 'ID inválido'];
    }
    $conn->query('UPDATE cw_site_ai_alerts SET is_read = 1, read_at = NOW() WHERE id = ' . (int) $id);
    return ['ok' => true];
}
