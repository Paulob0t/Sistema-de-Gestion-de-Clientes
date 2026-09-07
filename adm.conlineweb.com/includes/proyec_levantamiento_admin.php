<?php
declare(strict_types=1);

/**
 * Helpers admin — compartir formulario de levantamiento con leads.
 */
require_once __DIR__ . '/proyec_levantamiento_core.php';
require_once __DIR__ . '/cw_hub_permissions.php';

if (!defined('CW_PROYEC_PUBLIC_BASE')) {
    if (file_exists(__DIR__ . '/adm_local_auth.php')) {
        require_once __DIR__ . '/adm_local_auth.php';
    }
    if (function_exists('adm_is_local_environment') && function_exists('adm_local_peer_base_url') && adm_is_local_environment()) {
        define('CW_PROYEC_PUBLIC_BASE', adm_local_peer_base_url('conlineweb.com'));
    } else {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if (preg_match('/localhost|127\.0\.0\.1/i', $host)) {
            $project = basename(dirname(__DIR__, 2));
            define('CW_PROYEC_PUBLIC_BASE', 'http://' . $host . '/' . $project . '/conlineweb.com');
        } else {
            define('CW_PROYEC_PUBLIC_BASE', 'https://conlineweb.com');
        }
    }
}

/** @return array<string, mixed>|null */
function proyec_admin_load_lead_summary(mysqli $conn, int $leadId): ?array
{
    $stmt = $conn->prepare(
        'SELECT id, nombre, correo, telefono, empresa, responsable_id
         FROM leads WHERE id = ? AND eliminado = 0 LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $leadId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }

    return [
        'id' => (int) $row['id'],
        'empresa' => (string) ($row['empresa'] ?? ''),
        'contacto' => (string) ($row['nombre'] ?? ''),
        'correo' => (string) ($row['correo'] ?? ''),
        'telefono' => (string) ($row['telefono'] ?? ''),
        'ejecutivo' => cw_hub_responsable_nombre($conn, (int) ($row['responsable_id'] ?? 0)),
    ];
}

function proyec_admin_load_email_brand(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;
    foreach ([
        dirname(__DIR__, 3) . '/includes/cw_email_brand.php',
        dirname(__DIR__, 2) . '/includes/cw_email_brand.php',
    ] as $brandPath) {
        if (is_file($brandPath)) {
            require_once $brandPath;
            return;
        }
    }
}

function proyec_admin_default_email_subject(string $contacto): string
{
    $name = trim($contacto) !== '' ? trim($contacto) : 'cliente';
    return 'Formulario de levantamiento de requerimientos — ' . $name;
}

function proyec_admin_default_email_body(string $contacto, string $formUrl): string
{
    $greet = trim($contacto) !== '' ? trim($contacto) : 'estimado cliente';
    proyec_admin_load_email_brand();

    $inner = '<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#334155;">'
        . 'Hola <strong>' . htmlspecialchars($greet, ENT_QUOTES, 'UTF-8') . '</strong>,</p>'
        . '<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#334155;">'
        . 'Le compartimos el formulario de levantamiento de requerimientos de su proyecto. '
        . 'La información que nos proporcione nos permitirá elaborar una propuesta técnica y económica acorde a sus necesidades.</p>'
        . '<p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#334155;">'
        . 'Puede acceder desde el siguiente botón:</p>';

    if (function_exists('cw_email_cta')) {
        $inner .= cw_email_cta($formUrl, 'Abrir formulario de requerimientos', 'primary');
    } else {
        $inner .= '<p style="text-align:center;margin:24px 0;">'
            . '<a href="' . htmlspecialchars($formUrl, ENT_QUOTES, 'UTF-8') . '" '
            . 'style="display:inline-block;padding:14px 28px;background:#1e3a8a;color:#fff;text-decoration:none;border-radius:8px;font-weight:700;">'
            . 'Abrir formulario de requerimientos</a></p>';
    }

    $inner .= '<p style="margin:20px 0 0;font-size:14px;line-height:1.6;color:#64748b;">'
        . 'Si tiene alguna duda durante el llenado, con gusto le apoyaremos.</p>';

    if (function_exists('cw_email_wrap')) {
        return cw_email_wrap([
            'title' => 'Levantamiento de requerimientos',
            'content' => $inner,
        ]);
    }

    return '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">'
        . '<h2 style="color:#1e3a8a;">Levantamiento de requerimientos</h2>'
        . $inner . '</div>';
}

function proyec_admin_whatsapp_message(string $contacto, string $formUrl): string
{
    $name = trim($contacto) !== '' ? trim($contacto) : 'estimado cliente';
    return "Hola {$name}.\n\n"
        . "Le compartimos el formulario de levantamiento de requerimientos de su proyecto.\n\n"
        . "La información que nos proporcione nos permitirá elaborar una propuesta técnica y económica acorde a sus necesidades.\n\n"
        . "Puede acceder desde el siguiente enlace:\n\n"
        . $formUrl . "\n\n"
        . 'Si tiene alguna duda durante el llenado, con gusto le apoyaremos.';
}

/** @return array{ok:bool, error?:string} */
function proyec_admin_send_form_email(
    mysqli $conn,
    int $leadId,
    string $to,
    string $subject,
    string $htmlBody,
    ?int $usuarioId = null,
    ?int $accesoId = null
): array {
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Correo del lead inválido'];
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
        $mail->addAddress($to);
        $mail->Subject = $subject !== '' ? $subject : 'Formulario de levantamiento de requerimientos';
        $mail->isHTML(true);
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], ["\n", "\n", "\n", "\n\n"], $htmlBody));
        $mail->send();

        proyec_log_form_share($conn, $leadId, 'email', 'ok', $usuarioId, $accesoId, 'Enviado a ' . $to);

        return ['ok' => true];
    } catch (Throwable $e) {
        $detail = $e->getMessage();
        if (isset($mail) && !empty($mail->ErrorInfo)) {
            $detail = $mail->ErrorInfo;
        }
        proyec_log_form_share($conn, $leadId, 'email', 'error', $usuarioId, $accesoId, $detail);
        error_log('proyec_admin_send_form_email: ' . $detail);

        return ['ok' => false, 'error' => $detail];
    }
}

/** @return array<int, string> */
function proyec_admin_step_titles(): array
{
    return [
        1 => 'Información general',
        2 => 'Descripción general',
        3 => 'Procesos del negocio',
        4 => 'Módulos',
        5 => 'Funcionalidades',
        6 => 'Usuarios',
        7 => 'Formularios',
        8 => 'Reportes',
        9 => 'Dashboard',
        10 => 'Automatizaciones',
        11 => 'Integraciones',
        12 => 'Diseño',
        13 => 'Documentos',
        14 => 'Observaciones finales',
    ];
}

/** @return array<string, mixed>|null */
function proyec_admin_get_project_by_lead(mysqli $conn, int $leadId): ?array
{
    if ($leadId <= 0) {
        return null;
    }
    $stmt = $conn->prepare(
        "SELECT project_id, lead_id, nombre_proyecto, giro, pagina_web, objetivo_proyecto,
                estado, paso_actual, progreso_pct, datos, fecha_creacion, fecha_actualizacion, fecha_envio
         FROM proyec_proyectos
         WHERE lead_id = ?
         ORDER BY fecha_actualizacion DESC, project_id DESC
         LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $leadId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }
    $row['project_id'] = (int) $row['project_id'];
    $row['lead_id'] = (int) $row['lead_id'];
    $row['paso_actual'] = (int) ($row['paso_actual'] ?? 1);
    $row['progreso_pct'] = (int) ($row['progreso_pct'] ?? 0);
    $row['datos'] = json_decode((string) ($row['datos'] ?? '{}'), true) ?: [];

    return $row;
}

/** @param array<string, mixed> $project */
function proyec_admin_project_has_content(array $project): bool
{
    if ((int) ($project['progreso_pct'] ?? 0) > 0) {
        return true;
    }
    foreach (['nombre_proyecto', 'objetivo_proyecto', 'giro', 'pagina_web'] as $field) {
        if (trim((string) ($project[$field] ?? '')) !== '') {
            return true;
        }
    }
    $datos = is_array($project['datos'] ?? null) ? $project['datos'] : [];
    foreach (['contacto', 'general', 'descripcion'] as $section) {
        if (!empty($datos[$section]) && is_array($datos[$section])) {
            foreach ($datos[$section] as $value) {
                if (trim((string) $value) !== '') {
                    return true;
                }
            }
        }
    }
    foreach (['procesos', 'modulos', 'funcionalidades', 'usuarios', 'formularios', 'reportes', 'dashboard', 'automatizaciones', 'documentos'] as $listKey) {
        if (!empty($datos[$listKey]) && is_array($datos[$listKey]) && count($datos[$listKey]) > 0) {
            return true;
        }
    }

    return false;
}

function proyec_admin_mark_project_seen(mysqli $conn, int $leadId, int $usuarioId): void
{
    if ($leadId <= 0) {
        return;
    }
    $now = date('Y-m-d H:i:s');
    $uid = max(0, $usuarioId);
    $stmt = $conn->prepare(
        'INSERT INTO proyec_admin_visto (lead_id, usuario_id, visto_en)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE visto_en = VALUES(visto_en)'
    );
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('iis', $leadId, $uid, $now);
    $stmt->execute();
    $stmt->close();
}

function proyec_admin_count_unseen_changes(mysqli $conn, int $leadId, int $usuarioId): int
{
    if ($leadId <= 0) {
        return 0;
    }
    $uid = max(0, $usuarioId);
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM proyec_historial h
         INNER JOIN proyec_proyectos p ON p.project_id = h.project_id
         LEFT JOIN proyec_admin_visto v ON v.lead_id = h.lead_id AND v.usuario_id = ?
         WHERE h.lead_id = ?
           AND h.accion = 'autosave'
           AND h.created_at > COALESCE(v.visto_en, '1970-01-01 00:00:00')"
    );
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('ii', $uid, $leadId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($row['total'] ?? 0);
}

/**
 * @param int[] $leadIds
 * @return array<int, array{tiene_formulario:bool, cambios_pendientes:int, progreso_pct:int, paso_actual:int, fecha_actualizacion:?string}>
 */
function proyec_admin_leads_form_flags_bulk(mysqli $conn, array $leadIds, int $usuarioId): array
{
    $leadIds = array_values(array_unique(array_filter(array_map('intval', $leadIds), static fn(int $id): bool => $id > 0)));
    $out = [];
    foreach ($leadIds as $id) {
        $out[$id] = [
            'tiene_formulario' => false,
            'cambios_pendientes' => 0,
            'progreso_pct' => 0,
            'paso_actual' => 1,
            'fecha_actualizacion' => null,
        ];
    }
    if ($leadIds === []) {
        return $out;
    }

    $placeholders = implode(',', array_fill(0, count($leadIds), '?'));
    $types = str_repeat('i', count($leadIds));
    $sql = "SELECT lead_id, project_id, nombre_proyecto, giro, pagina_web, objetivo_proyecto,
                   paso_actual, progreso_pct, datos, fecha_actualizacion
            FROM proyec_proyectos
            WHERE lead_id IN ({$placeholders})
            ORDER BY fecha_actualizacion DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $out;
    }
    $stmt->bind_param($types, ...$leadIds);
    $stmt->execute();
    $result = $stmt->get_result();
    $projects = [];
    while ($row = $result->fetch_assoc()) {
        $lid = (int) $row['lead_id'];
        if (!isset($projects[$lid])) {
            $row['datos'] = json_decode((string) ($row['datos'] ?? '{}'), true) ?: [];
            $projects[$lid] = $row;
        }
    }
    $stmt->close();

    $uid = max(0, $usuarioId);
    $sqlUnseen = "SELECT h.lead_id, COUNT(*) AS total
                  FROM proyec_historial h
                  LEFT JOIN proyec_admin_visto v ON v.lead_id = h.lead_id AND v.usuario_id = ?
                  WHERE h.lead_id IN ({$placeholders})
                    AND h.accion = 'autosave'
                    AND h.created_at > COALESCE(v.visto_en, '1970-01-01 00:00:00')
                  GROUP BY h.lead_id";
    $stmtUnseen = $conn->prepare($sqlUnseen);
    $unseenMap = [];
    if ($stmtUnseen) {
        $bindTypes = 'i' . $types;
        $bindParams = array_merge([$uid], $leadIds);
        $stmtUnseen->bind_param($bindTypes, ...$bindParams);
        $stmtUnseen->execute();
        $resUnseen = $stmtUnseen->get_result();
        while ($u = $resUnseen->fetch_assoc()) {
            $unseenMap[(int) $u['lead_id']] = (int) $u['total'];
        }
        $stmtUnseen->close();
    }

    foreach ($projects as $lid => $project) {
        $out[$lid] = [
            'tiene_formulario' => proyec_admin_project_has_content($project),
            'cambios_pendientes' => $unseenMap[$lid] ?? 0,
            'progreso_pct' => (int) ($project['progreso_pct'] ?? 0),
            'paso_actual' => (int) ($project['paso_actual'] ?? 1),
            'fecha_actualizacion' => (string) ($project['fecha_actualizacion'] ?? ''),
        ];
    }

    return $out;
}

/** @return list<array<string, mixed>> */
function proyec_admin_recent_historial(mysqli $conn, int $leadId, int $limit = 12): array
{
    if ($leadId <= 0) {
        return [];
    }
    $limit = max(1, min(50, $limit));
    $stmt = $conn->prepare(
        "SELECT id, paso, accion, resumen, created_at
         FROM proyec_historial
         WHERE lead_id = ?
         ORDER BY id DESC
         LIMIT {$limit}"
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $leadId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return is_array($rows) ? $rows : [];
}

function proyec_admin_view_page_url(int $leadId): string
{
    if (!function_exists('adm_url')) {
        $pathsFile = dirname(__DIR__) . '/adm_paths.php';
        if (is_file($pathsFile)) {
            require_once $pathsFile;
        }
    }
    if (function_exists('adm_url')) {
        return adm_url('website/levantamiento-ver.php?lead_id=' . $leadId);
    }

    return '/website/levantamiento-ver.php?lead_id=' . $leadId;
}

/** @param array<string, mixed> $datos */
function proyec_admin_is_step_optin(array $datos, int $step): bool
{
    if ($step < 3) {
        return true;
    }
    $optin = is_array($datos['_meta']['optin'] ?? null) ? $datos['_meta']['optin'] : [];

    return !empty($optin[$step]) || !empty($optin[(string) $step]);
}

/** @param array<string, mixed> $datos */
function proyec_admin_section_has_text(array $datos, string $key): bool
{
    if (!isset($datos[$key]) || !is_array($datos[$key])) {
        return false;
    }
    foreach ($datos[$key] as $value) {
        if (trim((string) $value) !== '') {
            return true;
        }
    }

    return false;
}

/**
 * @param array<string, mixed> $project
 * @return list<array<string, mixed>>
 */
function proyec_admin_build_sections_overview(array $project): array
{
    $datos = is_array($project['datos'] ?? null) ? $project['datos'] : [];
    $steps = proyec_admin_step_titles();
    $sections = [];

    foreach ($steps as $num => $title) {
        $num = (int) $num;
        $enabled = proyec_admin_is_step_optin($datos, $num);
        $status = 'empty';
        $preview = '';
        $itemsCount = 0;

        if ($num >= 3 && !$enabled) {
            $status = 'skipped';
            $preview = 'Omitido por el cliente';
        } elseif ($num === 1) {
            $filled = 0;
            $checks = [
                (string) ($project['nombre_proyecto'] ?? ''),
                (string) ($project['objetivo_proyecto'] ?? ''),
                (string) ($datos['contacto']['contacto'] ?? ''),
                (string) ($datos['contacto']['correo'] ?? ''),
            ];
            foreach ($checks as $c) {
                if (trim($c) !== '') {
                    $filled++;
                }
            }
            $preview = proyec_admin_text_preview((string) ($project['nombre_proyecto'] ?? 'Sin nombre'), 120);
            if (trim((string) ($project['objetivo_proyecto'] ?? '')) !== '') {
                $preview .= ' — ' . proyec_admin_text_preview((string) $project['objetivo_proyecto'], 100);
            }
            $status = $filled >= 3 ? 'filled' : ($filled > 0 ? 'partial' : 'empty');
        } elseif ($num === 2) {
            $preview = proyec_admin_text_preview((string) ($datos['descripcion']['proyecto'] ?? ''), 160);
            if ($preview === '') {
                $preview = proyec_admin_text_preview((string) ($datos['descripcion']['problema'] ?? ''), 160);
            }
            $status = proyec_admin_section_has_text($datos, 'descripcion')
                ? ($preview !== '' ? 'filled' : 'partial')
                : 'empty';
        } elseif ($num === 11) {
            $ints = is_array($datos['integraciones'] ?? null) ? $datos['integraciones'] : [];
            $itemsCount = count($ints);
            $preview = $ints !== [] ? implode(', ', array_slice(array_map('strval', $ints), 0, 4)) : '';
            if (count($ints) > 4) {
                $preview .= '…';
            }
            $det = trim((string) ($datos['integraciones_detalle'] ?? ''));
            if ($det !== '' && $preview === '') {
                $preview = proyec_admin_text_preview($det, 160);
            }
            $status = ($itemsCount > 0 || $det !== '') ? 'filled' : 'empty';
        } elseif ($num === 12) {
            $status = proyec_admin_section_has_text($datos, 'diseno') ? 'filled' : 'empty';
            $preview = proyec_admin_text_preview((string) ($datos['diseno']['referencias'] ?? ''), 160);
        } elseif ($num === 13) {
            $docs = is_array($datos['documentos'] ?? null) ? $datos['documentos'] : [];
            $itemsCount = count($docs);
            if ($itemsCount > 0) {
                $names = [];
                foreach ($docs as $doc) {
                    if (is_array($doc) && trim((string) ($doc['titulo'] ?? '')) !== '') {
                        $names[] = (string) $doc['titulo'];
                    }
                }
                $preview = $names !== [] ? implode(', ', array_slice($names, 0, 3)) : $itemsCount . ' documento(s)';
                if (count($names) > 3) {
                    $preview .= '…';
                }
            }
            $status = $itemsCount > 0 ? 'filled' : 'empty';
        } elseif ($num === 14) {
            $preview = proyec_admin_text_preview((string) ($datos['observaciones']['comentarios'] ?? ''), 160);
            $status = proyec_admin_section_has_text($datos, 'observaciones') ? 'filled' : 'empty';
        } else {
            $map = [
                3 => 'procesos',
                4 => 'modulos',
                5 => 'funcionalidades',
                6 => 'usuarios',
                7 => 'formularios',
                8 => 'reportes',
                9 => 'dashboard',
                10 => 'automatizaciones',
            ];
            $listKey = $map[$num] ?? '';
            $list = ($listKey !== '' && is_array($datos[$listKey] ?? null)) ? $datos[$listKey] : [];
            $itemsCount = count($list);
            if ($itemsCount > 0) {
                $first = $list[0];
                if (is_array($first)) {
                    $preview = proyec_admin_text_preview((string) ($first['nombre'] ?? ''), 120);
                }
                if ($itemsCount > 1) {
                    $preview .= ($preview !== '' ? ' · ' : '') . '+' . ($itemsCount - 1) . ' más';
                }
            }
            $status = $itemsCount > 0 ? 'filled' : 'empty';
        }

        $sections[] = [
            'step' => $num,
            'title' => $title,
            'status' => $status,
            'preview' => $preview,
            'items_count' => $itemsCount,
        ];
    }

    return $sections;
}

/**
 * @param array<string, mixed> $project
 * @return array<string, mixed>
 */
function proyec_admin_build_modal_payload(array $project, ?array $lead, mysqli $conn, int $leadId): array
{
    $datos = is_array($project['datos'] ?? null) ? $project['datos'] : [];
    $summary = proyec_admin_build_project_summary($project, $lead);
    $sections = proyec_admin_build_sections_overview($project);

    $filledSections = 0;
    $skippedSections = 0;
    foreach ($sections as $sec) {
        if (($sec['status'] ?? '') === 'filled' || ($sec['status'] ?? '') === 'partial') {
            $filledSections++;
        }
        if (($sec['status'] ?? '') === 'skipped') {
            $skippedSections++;
        }
    }

    $ints = is_array($datos['integraciones'] ?? null) ? $datos['integraciones'] : [];
    $docs = is_array($datos['documentos'] ?? null) ? $datos['documentos'] : [];

    $autosaveCount = 0;
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total FROM proyec_historial WHERE lead_id = ? AND accion = 'autosave'"
    );
    if ($stmt) {
        $stmt->bind_param('i', $leadId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $autosaveCount = (int) ($row['total'] ?? 0);
        $stmt->close();
    }

    $active = proyec_get_active_access($conn, $leadId);
    $formClientUrl = $active ? proyec_form_url_by_token((string) $active['token']) : '';

    return [
        'summary' => array_merge($summary, [
            'problema' => proyec_admin_text_preview((string) ($datos['descripcion']['problema'] ?? ''), 320),
            'alcance' => proyec_admin_text_preview((string) ($datos['descripcion']['alcance'] ?? ''), 320),
            'integraciones' => $ints,
            'documentos_count' => count($docs),
            'empresa' => (string) ($lead['empresa'] ?? ''),
            'ejecutivo' => (string) ($lead['ejecutivo'] ?? ''),
        ]),
        'sections' => $sections,
        'stats' => [
            'secciones_con_info' => $filledSections,
            'secciones_omitidas' => $skippedSections,
            'total_secciones' => count($sections),
            'guardados_en_vivo' => $autosaveCount,
            'documentos' => count($docs),
            'integraciones' => count($ints),
        ],
        'form_client_url' => $formClientUrl,
    ];
}

/**
 * @param array<string, mixed> $project
 * @return array<string, mixed>
 */
function proyec_admin_build_project_summary(array $project, ?array $lead = null): array
{
    $datos = is_array($project['datos'] ?? null) ? $project['datos'] : [];
    $steps = proyec_admin_step_titles();
    $optin = is_array($datos['_meta']['optin'] ?? null) ? $datos['_meta']['optin'] : [];

    return [
        'project_id' => (int) ($project['project_id'] ?? 0),
        'nombre_proyecto' => (string) ($project['nombre_proyecto'] ?? ''),
        'giro' => (string) ($project['giro'] ?? ''),
        'pagina_web' => (string) ($project['pagina_web'] ?? ''),
        'objetivo_proyecto' => (string) ($project['objetivo_proyecto'] ?? ''),
        'estado' => (string) ($project['estado'] ?? 'borrador'),
        'paso_actual' => (int) ($project['paso_actual'] ?? 1),
        'paso_titulo' => $steps[(int) ($project['paso_actual'] ?? 1)] ?? 'Paso ' . ($project['paso_actual'] ?? 1),
        'progreso_pct' => (int) ($project['progreso_pct'] ?? 0),
        'fecha_creacion' => proyec_admin_format_datetime((string) ($project['fecha_creacion'] ?? '')),
        'fecha_actualizacion' => proyec_admin_format_datetime((string) ($project['fecha_actualizacion'] ?? '')),
        'contacto' => [
            'contacto' => (string) ($datos['contacto']['contacto'] ?? $lead['contacto'] ?? ''),
            'correo' => (string) ($datos['contacto']['correo'] ?? $lead['correo'] ?? ''),
            'telefono' => (string) ($datos['contacto']['telefono'] ?? $lead['telefono'] ?? ''),
        ],
        'descripcion_corta' => proyec_admin_text_preview((string) ($datos['descripcion']['proyecto'] ?? ''), 280),
        'modulos_activos' => proyec_admin_count_optin_enabled($optin),
        'modulos_total' => 12,
    ];
}

/** @param array<int|string, mixed> $optin */
function proyec_admin_count_optin_enabled(array $optin): int
{
    $count = 0;
    for ($s = 3; $s <= 14; $s++) {
        if (!empty($optin[$s]) || !empty($optin[(string) $s])) {
            $count++;
        }
    }

    return $count;
}

function proyec_admin_text_preview(string $text, int $max = 200): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    if ($text === '') {
        return '';
    }
    if (mb_strlen($text) <= $max) {
        return $text;
    }

    return mb_substr($text, 0, $max - 1) . '…';
}

function proyec_admin_format_datetime(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '—';
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return $value;
    }

    return date('d/m/Y H:i', $ts);
}
