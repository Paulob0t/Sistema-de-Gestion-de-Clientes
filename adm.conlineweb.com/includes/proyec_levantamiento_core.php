<?php
declare(strict_types=1);

/**
 * Core proyec — compartir formulario (admin, autocontenido).
 */
require_once __DIR__ . '/proyec_migrate.php';

if (!defined('CW_HUB_API_KEY')) {
    require_once __DIR__ . '/cw_hub_config.php';
}

if (!defined('PROYEC_FORM_LINK_DAYS')) {
    define('PROYEC_FORM_LINK_DAYS', 60);
}

function proyec_legacy_access_token(int $leadId): string
{
    $secret = defined('CW_HUB_API_KEY') ? CW_HUB_API_KEY : 'proyec_local_dev';
    return substr(hash_hmac('sha256', 'proyec_lead_' . $leadId, $secret), 0, 32);
}

function proyec_verify_legacy_access(int $leadId, string $token): bool
{
    if ($leadId <= 0 || $token === '') {
        return false;
    }
    return hash_equals(proyec_legacy_access_token($leadId), $token);
}

function proyec_public_site_base(): string
{
    if (defined('CW_PROYEC_PUBLIC_BASE') && CW_PROYEC_PUBLIC_BASE !== '') {
        return rtrim((string) CW_PROYEC_PUBLIC_BASE, '/');
    }
    return 'https://conlineweb.com';
}

function proyec_generate_secure_token(): string
{
    return bin2hex(random_bytes(24));
}

/** @return array<string, mixed>|null */
function proyec_get_active_access(mysqli $conn, int $leadId): ?array
{
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare(
        "SELECT id, lead_id, token, estado, fecha_creacion, fecha_expiracion, usuario_id, ultimo_uso
         FROM proyec_form_acceso
         WHERE lead_id = ? AND estado = 'activo' AND fecha_expiracion > ?
         ORDER BY id DESC LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('is', $leadId, $now);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/** @return array<string, mixed>|null */
function proyec_resolve_access(mysqli $conn, string $token): ?array
{
    $token = trim($token);
    if ($token === '' || strlen($token) < 16) {
        return null;
    }
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare(
        "SELECT id, lead_id, token, estado, fecha_creacion, fecha_expiracion, usuario_id, ultimo_uso
         FROM proyec_form_acceso
         WHERE token = ? AND estado = 'activo' AND fecha_expiracion > ?
         LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('ss', $token, $now);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }
    $touch = $conn->prepare('UPDATE proyec_form_acceso SET ultimo_uso = ? WHERE id = ? LIMIT 1');
    if ($touch) {
        $touch->bind_param('si', $now, $row['id']);
        $touch->execute();
        $touch->close();
    }
    return $row;
}

function proyec_form_url_by_token(string $token): string
{
    $base = proyec_public_site_base() . '/levantamiento-requerimientos.php';
    return $base . '?t=' . rawurlencode($token);
}

/** @return array{ok:bool, token?:string, url?:string, reused?:bool, acceso_id?:int, fecha_expiracion?:string, error?:string} */
function proyec_create_form_access(
    mysqli $conn,
    int $leadId,
    ?int $usuarioId = null,
    bool $regenerate = false
): array {
    if ($leadId <= 0) {
        return ['ok' => false, 'error' => 'Lead inválido'];
    }

    if (!$regenerate) {
        $active = proyec_get_active_access($conn, $leadId);
        if ($active) {
            return [
                'ok' => true,
                'token' => (string) $active['token'],
                'url' => proyec_form_url_by_token((string) $active['token']),
                'reused' => true,
                'acceso_id' => (int) $active['id'],
                'fecha_expiracion' => (string) $active['fecha_expiracion'],
            ];
        }
    } else {
        $rev = $conn->prepare(
            "UPDATE proyec_form_acceso SET estado = 'revocado' WHERE lead_id = ? AND estado = 'activo'"
        );
        if ($rev) {
            $rev->bind_param('i', $leadId);
            $rev->execute();
            $rev->close();
        }
    }

    $token = proyec_generate_secure_token();
    $now = date('Y-m-d H:i:s');
    $expires = date('Y-m-d H:i:s', strtotime('+' . (int) PROYEC_FORM_LINK_DAYS . ' days'));
    $uid = ($usuarioId && $usuarioId > 0) ? $usuarioId : 0;

    $ins = $conn->prepare(
        "INSERT INTO proyec_form_acceso
            (lead_id, token, estado, fecha_creacion, fecha_expiracion, usuario_id)
         VALUES (?, ?, 'activo', ?, ?, ?)"
    );
    if (!$ins) {
        return ['ok' => false, 'error' => 'No se pudo crear el acceso: ' . $conn->error];
    }
    $ins->bind_param('isssi', $leadId, $token, $now, $expires, $uid);
    if (!$ins->execute()) {
        $dbErr = $ins->error;
        $ins->close();
        return ['ok' => false, 'error' => 'Error al guardar el enlace: ' . $dbErr];
    }
    $accesoId = (int) $ins->insert_id;
    $ins->close();

    return [
        'ok' => true,
        'token' => $token,
        'url' => proyec_form_url_by_token($token),
        'reused' => false,
        'acceso_id' => $accesoId,
        'fecha_expiracion' => $expires,
    ];
}

function proyec_log_form_share(
    mysqli $conn,
    int $leadId,
    string $canal,
    string $estado = 'ok',
    ?int $usuarioId = null,
    ?int $accesoId = null,
    string $detalle = ''
): void {
    $allowed = ['email', 'enlace', 'whatsapp', 'copiar'];
    if (!in_array($canal, $allowed, true)) {
        return;
    }
    $estado = $estado === 'error' ? 'error' : 'ok';
    $now = date('Y-m-d H:i:s');
    $accesoId = ($accesoId && $accesoId > 0) ? $accesoId : 0;
    $usuarioId = ($usuarioId && $usuarioId > 0) ? $usuarioId : 0;
    $stmt = $conn->prepare(
        'INSERT INTO proyec_form_bitacora
            (lead_id, acceso_id, usuario_id, canal, estado, detalle, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('iiissss', $leadId, $accesoId, $usuarioId, $canal, $estado, $detalle, $now);
    $stmt->execute();
    $stmt->close();
}
