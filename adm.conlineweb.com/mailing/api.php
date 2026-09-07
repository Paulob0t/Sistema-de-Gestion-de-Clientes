<?php
/**
 * API JSON del módulo Mailing.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

define('AUTH_MIDDLEWARE_FUNCTIONS_ONLY', true);
require_once dirname(__DIR__) . '/auth_middleware.php';
require_once dirname(__DIR__) . '/includes/adm_paths.php';
require_once dirname(__DIR__) . '/includes/cw_mailing_service.php';
require_once dirname(__DIR__) . '/includes/cw_mailing_ai.php';
require_once dirname(__DIR__) . '/includes/cw_mailing_schedule.php';
require_once dirname(__DIR__) . '/conn.php';

adm_start_session();

if (!isAdminSessionValid() && !loadSessionFromCookies()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autorizado'], JSON_UNESCAPED_UNICODE);
    exit;
}

$tipo = (int) ($_SESSION['tipo'] ?? 0);
if ($tipo < 1 || $tipo > 5) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sin permiso'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!($conn instanceof mysqli)) {
    echo json_encode(['ok' => false, 'error' => 'Sin conexión'], JSON_UNESCAPED_UNICODE);
    exit;
}

cw_mailing_migrate($conn);

$input = $_POST;
$raw = file_get_contents('php://input');
if (is_string($raw) && $raw !== '' && str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = array_merge($input, $decoded);
    }
}

$action = trim((string) ($input['action'] ?? $_GET['action'] ?? ''));
$uid = isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : null;

try {
    switch ($action) {
        case 'templates_list':
            echo json_encode([
                'ok' => true,
                'templates' => cw_mailing_list_templates($conn, false),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;

        case 'template_get':
            $id = (int) ($input['id'] ?? 0);
            $tpl = cw_mailing_get_template($conn, $id);
            if (!$tpl) {
                echo json_encode(['ok' => false, 'error' => 'Plantilla no encontrada'], JSON_UNESCAPED_UNICODE);
                break;
            }
            echo json_encode(['ok' => true, 'template' => $tpl], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;

        case 'template_save':
            $id = (int) ($input['id'] ?? 0);
            $linksRaw = $input['links'] ?? ($input['extra_links'] ?? '');
            if ((is_string($linksRaw) && trim($linksRaw) !== '') || (is_array($linksRaw) && $linksRaw !== [])) {
                require_once dirname(__DIR__) . '/includes/cw_mailing_ai.php';
                $items = is_string($linksRaw)
                    ? cw_mailing_ai_parse_links_input($linksRaw)
                    : $linksRaw;
                if ($items !== []) {
                    $body = (string) ($input['body_html'] ?? '');
                    $body = (string) preg_replace('#<!--cw-links-->.*?<!--/cw-links-->#s', '', $body);
                    $input['body_html'] = (string) (cw_mailing_ai_ensure_links_block(['body_html' => $body], $items)['body_html'] ?? $body);
                }
            }
            $res = cw_mailing_save_template($conn, $input, $id);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        case 'template_delete':
            $id = (int) ($input['id'] ?? 0);
            echo json_encode(cw_mailing_delete_template($conn, $id), JSON_UNESCAPED_UNICODE);
            break;

        case 'preview':
            $id = (int) ($input['template_id'] ?? $input['id'] ?? 0);
            $tpl = null;
            if ($id > 0) {
                $tpl = cw_mailing_get_template($conn, $id);
            } elseif (!empty($input['draft']) && is_array($input['draft'])) {
                $tpl = $input['draft'];
            }
            if (!$tpl) {
                echo json_encode(['ok' => false, 'error' => 'Plantilla no encontrada'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $recipient = [
                'name' => (string) ($input['name'] ?? 'Cliente Demo'),
                'company' => (string) ($input['company'] ?? 'Empresa Demo'),
                'email' => (string) ($input['email'] ?? 'demo@conlineweb.com'),
                'phone' => (string) ($input['phone'] ?? ''),
            ];
            $html = cw_mailing_build_html($tpl, $recipient, 'preview', false);
            $subject = cw_mailing_apply_vars((string) ($tpl['subject'] ?? ''), cw_mailing_vars($recipient));
            echo json_encode([
                'ok' => true,
                'subject' => $subject,
                'html' => $html,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;

        case 'ai_example':
            require_once dirname(__DIR__) . '/includes/cw_mailing_ai.php';
            $draft = cw_mailing_ai_example_draft();
            $recipient = [
                'name' => 'Ana Torres',
                'company' => 'Grupo Norte',
                'email' => 'ana@empresademo.mx',
                'phone' => '4773400954',
            ];
            $html = cw_mailing_build_html($draft, $recipient, 'preview', false);
            $subject = cw_mailing_apply_vars((string) ($draft['subject'] ?? ''), cw_mailing_vars($recipient));
            echo json_encode([
                'ok' => true,
                'draft' => $draft,
                'subject' => $subject,
                'html' => $html,
                'example' => true,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;

        case 'ai_generate':
            $theme = trim((string) ($input['theme'] ?? ''));
            $prompt = trim((string) ($input['prompt'] ?? $input['brief'] ?? ''));
            $ctaHint = trim((string) ($input['cta_url'] ?? ''));
            $images = $input['image_urls'] ?? [];
            if (!is_array($images)) {
                $images = [];
            }
            $images = array_values(array_filter(array_map('strval', $images)));
            $gen = cw_mailing_ai_generate($theme, $prompt, $ctaHint, $images, $input['links'] ?? ($input['extra_links'] ?? ''));
            echo json_encode($gen, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;

        case 'ai_improve':
            $prompt = trim((string) ($input['prompt'] ?? ''));
            $images = $input['image_urls'] ?? [];
            if (!is_array($images)) {
                $images = [];
            }
            $images = array_values(array_filter(array_map('strval', $images)));
            $links = $input['links'] ?? ($input['extra_links'] ?? '');
            $current = [];
            $tid = (int) ($input['template_id'] ?? $input['id'] ?? 0);
            if ($tid > 0) {
                $row = cw_mailing_get_template($conn, $tid);
                if (!$row) {
                    echo json_encode(['ok' => false, 'error' => 'Plantilla no encontrada'], JSON_UNESCAPED_UNICODE);
                    break;
                }
                $current = $row;
            } elseif (!empty($input['draft']) && is_array($input['draft'])) {
                $current = $input['draft'];
            } else {
                $current = [
                    'title' => (string) ($input['title'] ?? ''),
                    'theme' => (string) ($input['theme'] ?? ''),
                    'subject' => (string) ($input['subject'] ?? ''),
                    'preheader' => (string) ($input['preheader'] ?? ''),
                    'body_html' => (string) ($input['body_html'] ?? ''),
                    'image_url' => (string) ($input['image_url'] ?? ''),
                    'cta_label' => (string) ($input['cta_label'] ?? ''),
                    'cta_url' => (string) ($input['cta_url'] ?? ''),
                    'active' => (int) ($input['active'] ?? 0),
                ];
            }
            $imp = cw_mailing_ai_improve($current, $prompt, $images, $links);
            if (!empty($imp['ok']) && !empty($imp['draft']) && $tid > 0) {
                $imp['draft']['id'] = $tid;
            }
            echo json_encode($imp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;

        case 'ai_status':
            require_once dirname(__DIR__) . '/includes/cw_mailing_ai.php';
            $ping = !empty($input['ping']) || !empty($_GET['ping']);
            $base = function_exists('cw_seo_mexico_ai_status')
                ? cw_seo_mexico_ai_status()
                : ['active' => false, 'configured' => false, 'label' => 'IA inactiva', 'detail' => 'Sin estado', 'tone' => 'off', 'model' => ''];

            $env = !empty($base['production_host']) ? 'producción' : 'local';
            $model = (string) ($base['model'] ?? '');
            $connected = false;
            $latency = null;
            $error = null;

            if (!empty($base['mock'])) {
                $base['label'] = 'IA en mock';
                $base['detail'] = 'Entorno ' . $env . ' · no llama a OpenAI';
                $base['tone'] = 'warn';
            } elseif (empty($base['configured'])) {
                $base['label'] = 'Sin clave OpenAI';
                $base['detail'] = 'Entorno ' . $env . ' · falta OPENAI_API_KEY';
                $base['tone'] = 'off';
            } elseif (!$ping) {
                $base['label'] = 'Clave lista · sin verificar';
                $base['detail'] = 'Entorno ' . $env . ' · aún no se hizo ping a api.openai.com';
                $base['tone'] = 'warn';
            } elseif (function_exists('cw_seo_mexico_ai_validate_connection')) {
                $val = cw_seo_mexico_ai_validate_connection();
                $connected = !empty($val['ok']) && !empty($val['reachable']);
                $latency = isset($val['latency_ms']) ? (int) $val['latency_ms'] : null;
                $error = $val['error'] ?? null;
                if ($connected) {
                    $base['label'] = 'OpenAI en línea';
                    $base['detail'] = 'Ping real OK · ' . $env . ' · ' . $model
                        . ($latency !== null ? ' · ' . $latency . ' ms' : '');
                    $base['tone'] = 'on';
                } else {
                    $base['label'] = 'OpenAI no responde';
                    $base['detail'] = (string) ($error ?: 'Falló el ping') . ' · entorno ' . $env;
                    $base['tone'] = 'off';
                    $base['active'] = false;
                }
            }

            echo json_encode([
                'ok' => true,
                'available' => $connected || (!$ping && !empty($base['configured']) && empty($base['mock'])),
                'configured' => !empty($base['configured']),
                'connected' => $connected,
                'pinged' => (bool) $ping,
                'local' => $env === 'local',
                'environment' => $env,
                'tone' => (string) ($base['tone'] ?? 'off'),
                'label' => (string) ($base['label'] ?? 'IA'),
                'detail' => (string) ($base['detail'] ?? ''),
                'model' => $model,
                'latency_ms' => $latency,
                'error' => $error,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;

        case 'audience_clientes':
            $q = trim((string) ($input['q'] ?? ''));
            echo json_encode([
                'ok' => true,
                'rows' => cw_mailing_list_clientes($conn, $q, 0, (int) ($uid ?? 0), $tipo),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;

        case 'audience_leads':
            $q = trim((string) ($input['q'] ?? ''));
            echo json_encode([
                'ok' => true,
                'rows' => cw_mailing_list_leads($conn, $q),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;

        case 'stats':
            echo json_encode([
                'ok' => true,
                'stats' => cw_mailing_stats($conn),
                'sends' => cw_mailing_recent_sends($conn, 80),
                'queue' => cw_mailing_list_queue($conn, 40),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;

        case 'schedule_send':
            $audience = (string) ($input['audience'] ?? '');
            $recipients = $input['recipients'] ?? [];
            $ids = [];
            if (is_array($recipients)) {
                foreach ($recipients as $rec) {
                    $ids[] = (int) (is_array($rec) ? ($rec['id'] ?? 0) : $rec);
                }
            }
            // Nuevo: items[] = plantilla + horario propio
            if (!empty($input['items']) && is_array($input['items'])) {
                $res = cw_mailing_schedule_multi($conn, $audience, $ids, $input['items'], $uid);
                echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                break;
            }
            // Compat antigua
            $tpl1 = (int) ($input['template_id'] ?? $input['template_id_1'] ?? 0);
            $tpl2 = (int) ($input['template_id_2'] ?? 0);
            $mode = (string) ($input['mode'] ?? 'now');
            $scheduledAt = isset($input['scheduled_at']) ? (string) $input['scheduled_at'] : null;
            $weekdays = $input['weekdays'] ?? [];
            if (!is_array($weekdays)) {
                $weekdays = [];
            }
            $sendTime = isset($input['send_time']) ? (string) $input['send_time'] : null;
            $delay = (int) ($input['template2_delay_hours'] ?? 24);
            $res = cw_mailing_schedule_campaign(
                $conn,
                $audience,
                $ids,
                $tpl1,
                $tpl2,
                $mode,
                $scheduledAt,
                $weekdays,
                $sendTime,
                $delay,
                $uid
            );
            echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;

        case 'process_queue':
            $limit = (int) ($input['limit'] ?? 40);
            $proc = cw_mailing_process_queue($conn, $limit);
            echo json_encode($proc, JSON_UNESCAPED_UNICODE);
            break;

        case 'queue_list':
            echo json_encode([
                'ok' => true,
                'queue' => cw_mailing_list_queue($conn, 60),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;

        case 'send':
            // Compat: redirige a schedule inmediato con 1 plantilla
            $templateId = (int) ($input['template_id'] ?? 0);
            $audience = (string) ($input['audience'] ?? '');
            $recipients = $input['recipients'] ?? [];
            $ids = [];
            if (is_array($recipients)) {
                foreach ($recipients as $rec) {
                    $ids[] = (int) (is_array($rec) ? ($rec['id'] ?? 0) : $rec);
                }
            }
            $res = cw_mailing_schedule_campaign(
                $conn,
                $audience,
                $ids,
                $templateId,
                0,
                'now',
                null,
                [],
                null,
                0,
                $uid
            );
            echo json_encode([
                'ok' => !empty($res['ok']),
                'sent' => (int) ($res['sent_now'] ?? 0),
                'failed' => (int) ($res['failed_now'] ?? 0),
                'message' => $res['message'] ?? ($res['error'] ?? ''),
                'error' => $res['error'] ?? null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Acción no válida'], JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    error_log('mailing/api: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error interno'], JSON_UNESCAPED_UNICODE);
}
