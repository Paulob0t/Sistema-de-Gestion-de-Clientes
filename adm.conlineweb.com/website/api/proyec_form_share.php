<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once dirname(__DIR__, 2) . '/auth_middleware.php';
    require_once dirname(__DIR__, 2) . '/conn.php';
    require_once dirname(__DIR__, 2) . '/includes/cw_hub_migrate.php';
    require_once dirname(__DIR__, 2) . '/includes/proyec_levantamiento_admin.php';

    cw_hub_migrate($conn);
    proyec_migrate($conn);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al iniciar el módulo: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$user = getAuthenticatedUser();
$usuarioId = (int) ($user['id'] ?? 0);

function proyec_share_json(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $leadId = (int) ($_GET['lead_id'] ?? 0);
        if ($leadId <= 0) {
            proyec_share_json(['success' => false, 'message' => 'Lead inválido'], 400);
        }

        $lead = proyec_admin_load_lead_summary($conn, $leadId);
        if ($lead === null) {
            proyec_share_json(['success' => false, 'message' => 'Lead no encontrado'], 404);
        }

        $active = proyec_get_active_access($conn, $leadId);
        $link = null;
        if ($active) {
            $link = [
                'url' => proyec_form_url_by_token((string) $active['token']),
                'token' => (string) $active['token'],
                'fecha_expiracion' => (string) $active['fecha_expiracion'],
                'acceso_id' => (int) $active['id'],
                'reused' => true,
            ];
        }

        $formUrl = $link['url'] ?? proyec_form_url_by_token('preview');
        $subject = proyec_admin_default_email_subject((string) $lead['contacto']);
        $bodyHtml = proyec_admin_default_email_body((string) $lead['contacto'], $formUrl);

        proyec_share_json([
            'success' => true,
            'lead' => $lead,
            'link' => $link,
            'email_defaults' => [
                'subject' => $subject,
                'body_html' => $bodyHtml,
            ],
            'whatsapp_message' => proyec_admin_whatsapp_message(
                (string) $lead['contacto'],
                $link['url'] ?? ''
            ),
        ]);
    } catch (Throwable $e) {
        proyec_share_json(['success' => false, 'message' => 'Error al cargar lead: ' . $e->getMessage()], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    proyec_share_json(['success' => false, 'message' => 'Método no permitido'], 405);
}

$raw = file_get_contents('php://input') ?: '';
$body = $_POST;
if (!is_array($body) || empty($body['action'])) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $body = $decoded;
    } elseif (!is_array($body)) {
        $body = [];
    }
}

$action = trim((string) ($body['action'] ?? ''));
$leadId = (int) ($body['lead_id'] ?? 0);

if ($leadId <= 0) {
    proyec_share_json(['success' => false, 'message' => 'Lead inválido'], 400);
}

$lead = proyec_admin_load_lead_summary($conn, $leadId);
if ($lead === null) {
    proyec_share_json(['success' => false, 'message' => 'Lead no encontrado'], 404);
}

if ($action === 'generate_link') {
    try {
        $regenerate = !empty($body['regenerate']);
        $result = proyec_create_form_access($conn, $leadId, $usuarioId > 0 ? $usuarioId : null, $regenerate);
        if (empty($result['ok'])) {
            proyec_share_json(['success' => false, 'message' => $result['error'] ?? 'No se pudo generar el enlace'], 500);
        }

        proyec_log_form_share(
            $conn,
            $leadId,
            'enlace',
            'ok',
            $usuarioId > 0 ? $usuarioId : null,
            (int) ($result['acceso_id'] ?? 0),
            !empty($result['reused']) ? 'Enlace activo reutilizado' : 'Enlace generado'
        );

        proyec_share_json([
            'success' => true,
            'link' => [
                'url' => (string) $result['url'],
                'token' => (string) $result['token'],
                'fecha_expiracion' => (string) $result['fecha_expiracion'],
                'acceso_id' => (int) $result['acceso_id'],
                'reused' => !empty($result['reused']),
            ],
            'whatsapp_message' => proyec_admin_whatsapp_message(
                (string) $lead['contacto'],
                (string) $result['url']
            ),
            'email_defaults' => [
                'subject' => proyec_admin_default_email_subject((string) $lead['contacto']),
                'body_html' => proyec_admin_default_email_body((string) $lead['contacto'], (string) $result['url']),
            ],
        ]);
    } catch (Throwable $e) {
        proyec_share_json(['success' => false, 'message' => 'Error al generar enlace: ' . $e->getMessage()], 500);
    }
}

if ($action === 'send_email') {
    $regenerate = !empty($body['regenerate']);
    $access = proyec_create_form_access($conn, $leadId, $usuarioId > 0 ? $usuarioId : null, $regenerate);
    if (empty($access['ok'])) {
        proyec_share_json(['success' => false, 'message' => $access['error'] ?? 'No se pudo generar el enlace'], 500);
    }

    $formUrl = (string) $access['url'];
    $subject = trim((string) ($body['subject'] ?? ''));
    $messageHtml = trim((string) ($body['message_html'] ?? ''));

    if ($subject === '') {
        $subject = proyec_admin_default_email_subject((string) $lead['contacto']);
    }
    if ($messageHtml === '') {
        $messageHtml = proyec_admin_default_email_body((string) $lead['contacto'], $formUrl);
    } elseif (!str_contains($messageHtml, $formUrl) && !str_contains($messageHtml, 'href=')) {
        proyec_admin_load_email_brand();
        $cta = function_exists('cw_email_cta')
            ? cw_email_cta($formUrl, 'Abrir formulario de requerimientos', 'primary')
            : '<p><a href="' . htmlspecialchars($formUrl, ENT_QUOTES, 'UTF-8') . '">Abrir formulario</a></p>';
        if (function_exists('cw_email_wrap')) {
            $messageHtml = cw_email_wrap([
                'title' => 'Levantamiento de requerimientos',
                'content' => $messageHtml . $cta,
            ]);
        } else {
            $messageHtml .= $cta;
        }
    }

    $send = proyec_admin_send_form_email(
        $conn,
        $leadId,
        (string) $lead['correo'],
        $subject,
        $messageHtml,
        $usuarioId > 0 ? $usuarioId : null,
        (int) ($access['acceso_id'] ?? 0)
    );

    if (empty($send['ok'])) {
        proyec_share_json(['success' => false, 'message' => $send['error'] ?? 'No se pudo enviar el correo'], 500);
    }

    proyec_share_json([
        'success' => true,
        'message' => 'Correo enviado correctamente',
        'link' => [
            'url' => $formUrl,
            'token' => (string) $access['token'],
            'fecha_expiracion' => (string) $access['fecha_expiracion'],
            'acceso_id' => (int) $access['acceso_id'],
        ],
    ]);
}

if ($action === 'log_share') {
    $canal = trim((string) ($body['canal'] ?? ''));
    $allowed = ['whatsapp', 'copiar', 'enlace'];
    if (!in_array($canal, $allowed, true)) {
        proyec_share_json(['success' => false, 'message' => 'Canal inválido'], 400);
    }

    $accesoId = (int) ($body['acceso_id'] ?? 0);
    $detalle = trim((string) ($body['detalle'] ?? ''));
    if ($detalle === '') {
        $detalle = $canal === 'copiar' ? 'Enlace copiado al portapapeles' : 'Compartido por WhatsApp';
    }

    proyec_log_form_share(
        $conn,
        $leadId,
        $canal,
        'ok',
        $usuarioId > 0 ? $usuarioId : null,
        $accesoId > 0 ? $accesoId : null,
        $detalle
    );

    proyec_share_json(['success' => true, 'message' => 'Registrado en bitácora']);
}

proyec_share_json(['success' => false, 'message' => 'Acción no reconocida'], 400);
