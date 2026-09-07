<?php
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cw_hub_api_fail('Método no permitido', 405);
}
cw_hub_validate_request();

$data = cw_hub_json_input();

// reCAPTCHA: obligatorio en producción; omitido en localhost (igual que login del portal).
if (cw_hub_lead_captcha_required()) {
    $captchaToken = cw_hub_recaptcha_token_from_data($data);
    $captchaCheck = cw_hub_verify_recaptcha($captchaToken);
    if (empty($captchaCheck['ok'])) {
        cw_hub_api_fail($captchaCheck['error'] ?? 'Completa el captcha para continuar', 400);
    }
}

$nombre = cw_hub_s($data['nombre'] ?? '', 150);
$apellido = cw_hub_s($data['apellido'] ?? '', 150);
$correo = cw_hub_s($data['correo'] ?? '', 180);
$telefonoLocal = preg_replace('/\D/', '', (string) ($data['telefono'] ?? ''));
$telefonoCodigo = cw_hub_s($data['telefono_codigo'] ?? '+52', 8);
$telefonoPais = cw_hub_s($data['telefono_pais'] ?? 'mx', 4);
$whatsappRaw = cw_hub_s($data['whatsapp'] ?? '', 30);
$empresa = cw_hub_s($data['empresa'] ?? '', 150);
$servicio = cw_hub_s($data['servicio'] ?? 'otro', 100);
$proyecto = cw_hub_s($data['proyecto'] ?? $data['requerimiento'] ?? '', 5000);
$mensaje = cw_hub_s($data['mensaje'] ?? $data['mensaje_contexto'] ?? '', 2000);
$paginaOrigen = cw_hub_s($data['pagina_origen'] ?? $data['url'] ?? '', 2000);
$sessionId = cw_hub_s($data['session_id'] ?? '', 64);
$fuenteRaw = strtolower(cw_hub_s($data['fuente'] ?? 'website', 40));
$fuente = in_array($fuenteRaw, ['website', 'web_chat', 'whatsapp', 'chat'], true)
    ? ($fuenteRaw === 'chat' ? 'web_chat' : $fuenteRaw)
    : 'website';
if (($data['canal'] ?? '') === 'web_chat' && $fuente === 'website') {
    $fuente = 'web_chat';
}
$utmSource = cw_hub_s($data['utm_source'] ?? '', 120);
$utmMedium = cw_hub_s($data['utm_medium'] ?? '', 120);
$utmCampaign = cw_hub_s($data['utm_campaign'] ?? '', 120);

// Compat: si llega solo "nombre completo" sin apellido (chat legado), separar.
if ($apellido === '' && strpos($nombre, ' ') !== false) {
    $parts = preg_split('/\s+/', $nombre, 2);
    if (is_array($parts) && count($parts) === 2) {
        $nombre = cw_hub_s($parts[0], 150);
        $apellido = cw_hub_s($parts[1], 150);
    }
}

$isWebChatLead = ($fuente === 'web_chat')
    || !empty($data['crear_chat'])
    || (($data['canal'] ?? '') === 'web_chat');

if ($nombre === '') {
    cw_hub_api_fail('El nombre es obligatorio');
}

// Formulario WhatsApp: nombre + teléfono bastan para el lead. Correo/empresa/apellido son opcionales.
if (!$isWebChatLead) {
    if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        cw_hub_api_fail('El correo electrónico debe ser válido');
    }
    $consentRaw = strtolower(trim((string) ($data['consentimiento'] ?? $data['consentimiento_privacidad'] ?? '')));
    $consentOk = in_array($consentRaw, ['1', 'true', 'si', 'sí', 'yes', 'on'], true);
    if (!$consentOk) {
        cw_hub_api_fail('Debes aceptar la política de privacidad para continuar');
    }
} elseif ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    cw_hub_api_fail('Correo electrónico inválido');
}

$nombreCompleto = trim($nombre . ($apellido !== '' ? ' ' . $apellido : ''));

$paises = [
    'mx' => 'México',
    'us' => 'Estados Unidos',
    'ca' => 'Canadá',
    'es' => 'España',
    'ar' => 'Argentina',
    'br' => 'Brasil',
    'cl' => 'Chile',
    'co' => 'Colombia',
    'pe' => 'Perú',
    've' => 'Venezuela',
];

if ($telefonoLocal === '' && $whatsappRaw !== '') {
    $telefonoLocal = preg_replace('/\D/', '', $whatsappRaw);
    if (str_starts_with($telefonoCodigo, '+')) {
        $codigoDigitsTmp = preg_replace('/\D/', '', $telefonoCodigo);
        if ($codigoDigitsTmp !== '' && str_starts_with($telefonoLocal, $codigoDigitsTmp)) {
            $telefonoLocal = substr($telefonoLocal, strlen($codigoDigitsTmp));
        }
    }
}

$codigoDigits = preg_replace('/\D/', '', $telefonoCodigo);
if ($codigoDigits === '') {
    $codigoDigits = '52';
    $telefonoCodigo = '+52';
}

$phoneLen = strlen($telefonoLocal);
$isClPhone = ($telefonoPais === 'cl' || $codigoDigits === '56');
$isBrPhone = ($telefonoPais === 'br' || $codigoDigits === '55');
$isNineDigit = in_array($telefonoPais, ['es', 'pe'], true) || in_array($codigoDigits, ['34', '51'], true);

if ($isClPhone) {
    if ($phoneLen < 8 || $phoneLen > 9) {
        cw_hub_api_fail('Ingresa un teléfono válido de Chile (8 o 9 dígitos)');
    }
} elseif ($isBrPhone) {
    if ($phoneLen !== 11) {
        cw_hub_api_fail('Ingresa un teléfono válido de Brasil (11 dígitos)');
    }
} elseif ($isNineDigit) {
    if ($phoneLen !== 9) {
        cw_hub_api_fail('Ingresa un teléfono válido de 9 dígitos');
    }
} elseif ($phoneLen !== 10) {
    cw_hub_api_fail('Ingresa un teléfono válido de 10 dígitos');
}

$whatsapp = '+' . $codigoDigits . $telefonoLocal;
$pais = $paises[$telefonoPais] ?? 'México';

// Columna apellido (campos separados en BD)
$hasApellidoCol = false;
$colChk = $conn->query("SHOW COLUMNS FROM leads LIKE 'apellido'");
if ($colChk && $colChk->num_rows > 0) {
    $hasApellidoCol = true;
} else {
    $hasApellidoCol = (bool) @$conn->query("ALTER TABLE leads ADD COLUMN apellido VARCHAR(150) NULL DEFAULT NULL AFTER nombre");
}

$servicios = CW_HUB_SERVICIOS;
$servicio = strtolower(trim($servicio));
if ($servicio === '' || !isset($servicios[$servicio])) {
    $servicio = 'otro';
}
$servicioLabel = $servicios[$servicio];

$crearChatEarly = !empty($data['crear_chat']) || (($data['canal'] ?? '') === 'web_chat');
require_once dirname(__DIR__, 2) . '/website/includes/leads_helpers.php';
$sitioOrigen = cw_web_lead_resolve_web(
    (string) ($data['sitio'] ?? $data['web'] ?? ''),
    $paginaOrigen
);
if ($sitioOrigen === '') {
    $sitioOrigen = 'mx';
}
$sitioLabel = ['mx' => 'México (.com)', 'cl' => 'Chile (.cl)', 'us' => 'USA (/us)'][$sitioOrigen] ?? $sitioOrigen;
$notas = [[
    'nota' => ($crearChatEarly ? 'Lead captado desde chat web. Servicio: ' : 'Lead captado desde formulario modal web. Servicio: ')
        . $servicioLabel . ' · Sitio: ' . $sitioLabel,
    'fecha' => cw_hub_now(),
    'usuario_id' => 0,
]];
if (!$crearChatEarly) {
    $notas[] = [
        'nota' => 'Consentimiento: política de privacidad e información de servicios (mailing), con opción de baja.',
        'fecha' => cw_hub_now(),
        'usuario_id' => 0,
    ];
}
if ($mensaje !== '') {
    $notas[] = [
        'nota' => ($crearChatEarly ? 'Mensaje chat web: ' : 'Mensaje WhatsApp: ') . $mensaje,
        'fecha' => cw_hub_now(),
        'usuario_id' => 0,
    ];
}
if ($proyecto !== '') {
    $notas[] = [
        'nota' => 'Proyecto: ' . $proyecto,
        'fecha' => cw_hub_now(),
        'usuario_id' => 0,
    ];
}
$notasJson = json_encode($notas, JSON_UNESCAPED_UNICODE);

$estatus = 'Activo';
$pipeline = 'lead';
$origenWeb = 1;
$now = cw_hub_now();
$requerimiento = trim($servicioLabel . ($mensaje !== '' ? ' — ' . $mensaje : ($proyecto !== '' ? ' — ' . $proyecto : '')));

// Evitar duplicado reciente
$existing = null;
$isNewLead = false;
if ($correo !== '') {
    $dup = $conn->prepare("SELECT id FROM leads WHERE correo = ? AND origen_web = 1 AND fecha_registro > DATE_SUB(NOW(), INTERVAL 2 HOUR) AND eliminado = 0 LIMIT 1");
    $dup->bind_param('s', $correo);
    $dup->execute();
    $existing = $dup->get_result()->fetch_assoc();
    $dup->close();
} elseif ($sessionId !== '') {
    $dup = $conn->prepare("SELECT id FROM leads WHERE session_id = ? AND nombre = ? AND origen_web = 1 AND fecha_registro > DATE_SUB(NOW(), INTERVAL 2 HOUR) AND eliminado = 0 LIMIT 1");
    $dup->bind_param('ss', $sessionId, $nombre);
    $dup->execute();
    $existing = $dup->get_result()->fetch_assoc();
    $dup->close();
} elseif ($whatsapp !== '') {
    $dup = $conn->prepare("SELECT id FROM leads WHERE telefono = ? AND origen_web = 1 AND fecha_registro > DATE_SUB(NOW(), INTERVAL 2 HOUR) AND eliminado = 0 LIMIT 1");
    $dup->bind_param('s', $whatsapp);
    $dup->execute();
    $existing = $dup->get_result()->fetch_assoc();
    $dup->close();
}

if ($existing) {
    $leadId = (int) $existing['id'];
    $notesStmt = $conn->prepare('SELECT notas FROM leads WHERE id = ? AND origen_web = 1 LIMIT 1');
    $notesStmt->bind_param('i', $leadId);
    $notesStmt->execute();
    $notesRow = $notesStmt->get_result()->fetch_assoc();
    $notesStmt->close();

    $notasArr = json_decode($notesRow['notas'] ?? '[]', true);
    if (!is_array($notasArr)) {
        $notasArr = [];
    }
    $notasArr[] = [
        'nota' => ($crearChatEarly ? 'Nueva interacción desde chat web. Servicio: ' : 'Nueva interacción desde formulario web. Servicio: ') . $servicioLabel,
        'fecha' => $now,
        'usuario_id' => 0,
    ];
    if ($mensaje !== '') {
        $notasArr[] = [
            'nota' => ($crearChatEarly ? 'Mensaje chat web: ' : 'Mensaje WhatsApp: ') . $mensaje,
            'fecha' => $now,
            'usuario_id' => 0,
        ];
    }
    $leadNotasJson = json_encode($notasArr, JSON_UNESCAPED_UNICODE);

    if ($correo !== '') {
        if ($hasApellidoCol) {
            $upd = $conn->prepare('UPDATE leads SET ultima_interaccion = ?, telefono = ?, pais = ?, requerimiento = ?, pagina_origen = ?, session_id = ?, correo = ?, empresa = ?, nombre = ?, apellido = ?, notas = ?, web = ?, servicio = ? WHERE id = ? AND origen_web = 1');
            $upd->bind_param('sssssssssssssi', $now, $whatsapp, $pais, $requerimiento, $paginaOrigen, $sessionId, $correo, $empresa, $nombre, $apellido, $leadNotasJson, $sitioOrigen, $servicio, $leadId);
        } else {
            $upd = $conn->prepare('UPDATE leads SET ultima_interaccion = ?, telefono = ?, pais = ?, requerimiento = ?, pagina_origen = ?, session_id = ?, correo = ?, empresa = ?, nombre = ?, notas = ?, web = ?, servicio = ? WHERE id = ? AND origen_web = 1');
            $nombreStore = $nombreCompleto;
            $upd->bind_param('ssssssssssssi', $now, $whatsapp, $pais, $requerimiento, $paginaOrigen, $sessionId, $correo, $empresa, $nombreStore, $leadNotasJson, $sitioOrigen, $servicio, $leadId);
        }
    } else {
        if ($hasApellidoCol) {
            $upd = $conn->prepare('UPDATE leads SET ultima_interaccion = ?, telefono = ?, pais = ?, requerimiento = ?, pagina_origen = ?, session_id = ?, empresa = ?, nombre = ?, apellido = ?, notas = ?, web = ?, servicio = ? WHERE id = ? AND origen_web = 1');
            $upd->bind_param('ssssssssssssi', $now, $whatsapp, $pais, $requerimiento, $paginaOrigen, $sessionId, $empresa, $nombre, $apellido, $leadNotasJson, $sitioOrigen, $servicio, $leadId);
        } else {
            $upd = $conn->prepare('UPDATE leads SET ultima_interaccion = ?, telefono = ?, pais = ?, requerimiento = ?, pagina_origen = ?, session_id = ?, empresa = ?, nombre = ?, notas = ?, web = ?, servicio = ? WHERE id = ? AND origen_web = 1');
            $nombreStore = $nombreCompleto;
            $upd->bind_param('sssssssssssi', $now, $whatsapp, $pais, $requerimiento, $paginaOrigen, $sessionId, $empresa, $nombreStore, $leadNotasJson, $sitioOrigen, $servicio, $leadId);
        }
    }
    $upd->execute();
    $upd->close();

    $act = $conn->prepare("INSERT INTO cw_lead_actividades (lead_id, tipo, descripcion, created_at) VALUES (?, 'seguimiento', ?, ?)");
    $desc = 'Nueva interacción registrada en Leads Website.';
    $act->bind_param('iss', $leadId, $desc, $now);
    $act->execute();
    $act->close();
} else {
    if ($hasApellidoCol) {
        $ins = $conn->prepare("INSERT INTO leads
            (nombre, apellido, correo, telefono, requerimiento, empresa, pais, estatus, notas, usuario_registro,
             servicio, pagina_origen, fuente, utm_source, utm_medium, utm_campaign, session_id,
             pipeline_estado, ultima_interaccion, origen_web, web)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $ins->bind_param(
            'ssssssssssssssssssis',
            $nombre,
            $apellido,
            $correo,
            $whatsapp,
            $requerimiento,
            $empresa,
            $pais,
            $estatus,
            $notasJson,
            $servicio,
            $paginaOrigen,
            $fuente,
            $utmSource,
            $utmMedium,
            $utmCampaign,
            $sessionId,
            $pipeline,
            $now,
            $origenWeb,
            $sitioOrigen
        );
    } else {
        $nombreStore = $nombreCompleto;
        $ins = $conn->prepare("INSERT INTO leads
            (nombre, correo, telefono, requerimiento, empresa, pais, estatus, notas, usuario_registro,
             servicio, pagina_origen, fuente, utm_source, utm_medium, utm_campaign, session_id,
             pipeline_estado, ultima_interaccion, origen_web, web)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $ins->bind_param(
            'sssssssssssssssssis',
            $nombreStore,
            $correo,
            $whatsapp,
            $requerimiento,
            $empresa,
            $pais,
            $estatus,
            $notasJson,
            $servicio,
            $paginaOrigen,
            $fuente,
            $utmSource,
            $utmMedium,
            $utmCampaign,
            $sessionId,
            $pipeline,
            $now,
            $origenWeb,
            $sitioOrigen
        );
    }
    if (!$ins->execute()) {
        cw_hub_api_fail('No se pudo registrar el lead', 500);
    }
    $leadId = (int) $ins->insert_id;
    $ins->close();
    $isNewLead = true;

    $act = $conn->prepare("INSERT INTO cw_lead_actividades (lead_id, tipo, descripcion, created_at) VALUES (?, 'seguimiento', ?, ?)");
    $desc = 'Lead registrado en Leads Website desde formulario web.';
    $act->bind_param('iss', $leadId, $desc, $now);
    $act->execute();
    $act->close();
}

require_once dirname(__DIR__, 2) . '/includes/cw_hub_notify.php';

$crearChat = $crearChatEarly;
$conversationId = 0;
$conversationUuid = '';
if ($crearChat) {
    try {
        require_once dirname(__DIR__, 2) . '/includes/cw_chat_migrate.php';
        require_once dirname(__DIR__, 2) . '/includes/cw_chat_service.php';
        $existingUuid = cw_hub_s($data['conversation_uuid'] ?? '', 36);
        // Si ya hay chat de sesión, solo vincular al lead (sin nuevo saludo duplicado)
        $saludoBot = ($existingUuid !== '' || $sessionId !== '')
            ? ''
            : sprintf(
                '¡Hola %s! Gracias por escribirnos. Ya registramos tu interés en %s. '
                . 'Puedes contarme más de tu proyecto, pedir cotización o agendar una llamada. ¿En qué te ayudo?',
                explode(' ', $nombreCompleto)[0] ?: $nombre,
                $servicioLabel
            );
        $created = cw_chat_get_or_create_web_lead_conversation($conn, $leadId, $nombreCompleto, $saludoBot, [
            'servicio' => $servicio,
            'pagina_origen' => $paginaOrigen,
            'mensaje_inicial' => $mensaje,
            'session_id' => $sessionId,
            'conversation_uuid' => $existingUuid,
        ]);
        $conversationId = (int) ($created['conversation']['id'] ?? 0);
        $conversationUuid = (string) ($created['conversation']['uuid'] ?? '');
        if ($conversationId > 0 && !empty($created['created']) && $saludoBot === '' && $mensaje !== '') {
            cw_chat_add_message($conn, $conversationId, 'cliente', null, $mensaje, 'texto', [
                'origen' => 'web_chat_seed',
            ]);
        }
        if ($conversationId > 0 && empty($created['created'])) {
            cw_chat_add_message($conn, $conversationId, 'sistema', null, 'Lead #' . $leadId . ' vinculado: ' . $nombreCompleto, 'sistema', [
                'origen' => 'web_chat_link',
            ]);
        }
    } catch (Throwable $e) {
        error_log('lead.php web chat create failed for lead #' . $leadId . ': ' . $e->getMessage());
    }
}

$leadPayload = [
    'nombre' => $nombreCompleto,
    'apellido' => $apellido,
    'correo' => $correo,
    'telefono' => $whatsapp,
    'empresa' => $empresa,
    'servicio' => $servicio,
    'servicio_label' => $servicioLabel,
    'requerimiento' => $requerimiento,
    'pagina_origen' => $paginaOrigen,
    'pipeline_estado' => $pipeline,
    'fuente' => $fuente,
];

// Igual que XAMPP/producción: registrar → notificar → confirmar → responder.
$adminNotify = ['admin_ok' => false, 'admin_error' => '', 'admin_skipped' => false, 'n8n_ok' => false];
try {
    if (!function_exists('cw_hub_notify_web_lead_submission')) {
        require_once dirname(__DIR__, 2) . '/includes/cw_hub_notify.php';
    }
    $adminNotify = cw_hub_notify_web_lead_submission($conn, $leadId, $leadPayload, $isNewLead);
} catch (Throwable $e) {
    error_log('lead.php notify failed for lead #' . $leadId . ': ' . $e->getMessage());
    $adminNotify['admin_error'] = $e->getMessage();
}

if ($sessionId !== '') {
    try {
        $meta = json_encode(['lead_id' => $leadId, 'conversation_id' => $conversationId], JSON_UNESCAPED_UNICODE);
        $ev = $conn->prepare('INSERT INTO cw_analytics_events (session_id, event_type, event_label, url, meta, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        if ($ev) {
            $etype = 'conversion';
            $label = $crearChat ? 'lead_web_chat' : 'lead_whatsapp';
            $ev->bind_param('ssssss', $sessionId, $etype, $label, $paginaOrigen, $meta, $now);
            $ev->execute();
            $ev->close();
        }
        cw_hub_update_session_contact(
            $conn,
            $sessionId,
            $nombreCompleto !== '' ? $nombreCompleto : $nombre,
            $correo,
            $whatsapp,
            $leadId
        );
    } catch (Throwable $e) {
        error_log('lead.php analytics failed for lead #' . $leadId . ': ' . $e->getMessage());
    }
}

$waIntro = $mensaje !== '' ? $mensaje : ('Me interesa ' . $servicioLabel);
$waMsg = sprintf(
    "Hola, soy %s%s. %s\n\nServicio de interés: %s%s\nTel: %s\nPágina: %s%s",
    $nombreCompleto !== '' ? $nombreCompleto : $nombre,
    $empresa !== '' ? (' de ' . $empresa) : '',
    $waIntro,
    $servicioLabel,
    $empresa !== '' ? ("\nEmpresa: " . $empresa) : '',
    $whatsapp,
    $paginaOrigen,
    $correo !== '' ? ("\nCorreo: " . $correo) : ''
);
$whatsappUrl = 'https://wa.me/' . CW_HUB_WHATSAPP . '?text=' . rawurlencode($waMsg);

$clientEmail = ['ok' => false, 'error' => ''];
if ($correo !== '') {
    try {
        if (!function_exists('cw_hub_send_client_confirmation')) {
            require_once dirname(__DIR__, 2) . '/includes/cw_hub_notify.php';
        }
        $clientEmail = cw_hub_send_client_confirmation($conn, $leadId, $correo, [
            'nombre' => $nombreCompleto !== '' ? $nombreCompleto : $nombre,
            'correo' => $correo,
            'telefono' => $whatsapp,
            'servicio' => $servicio,
            'servicio_label' => $servicioLabel,
            'mensaje' => $mensaje,
            'requerimiento' => $requerimiento,
            'pagina_origen' => $paginaOrigen,
        ], $whatsappUrl);
    } catch (Throwable $e) {
        error_log('lead.php client email failed for lead #' . $leadId . ': ' . $e->getMessage());
        $clientEmail = ['ok' => false, 'error' => $e->getMessage()];
    }
}

$emailSent = !empty($clientEmail['ok']);
$emailError = (string) ($clientEmail['error'] ?? '');
if ($correo !== '' && !$emailSent && $emailError !== '') {
    error_log('lead.php confirmation email failed for lead #' . $leadId . ': ' . $emailError);
}

$inboxUrl = $conversationId > 0
    ? cw_hub_admin_chat_inbox_url($conversationId)
    : cw_hub_admin_inbox_url($leadId);

$confirmMsg = $emailSent
    ? 'Te enviamos un correo de confirmación a ' . $correo . '. Revisa tu bandeja (y spam). También puedes continuar por WhatsApp.'
    : ($correo !== ''
        ? 'Registramos tu solicitud. No pudimos enviar el correo de confirmación; un asesor te contactará pronto. También puedes escribirnos por WhatsApp.'
        : 'Registramos tu solicitud. Puedes continuar por WhatsApp.');

$response = [
    'success' => true,
    'lead_id' => $leadId,
    'conversation_id' => $conversationId,
    'conversation_uuid' => $conversationUuid,
    'crm' => [
        'module' => 'website',
        'origen_web' => 1,
        'fuente' => $fuente,
        'is_new' => $isNewLead,
        'leads_url' => cw_hub_website_leads_url($leadId),
        'inbox_url' => $inboxUrl,
    ],
    'whatsapp_url' => $whatsappUrl,
    'skip_whatsapp' => false,
    'email_sent' => $emailSent,
    'admin_alert_sent' => !empty($adminNotify['admin_ok']),
    'confirmation' => [
        'title' => '¡Solicitud registrada!',
        'message' => $confirmMsg,
    ],
];

if ($emailError !== '') {
    $response['email_error'] = $emailError;
}
if (!empty($adminNotify['admin_error'])) {
    $response['admin_alert_error'] = $adminNotify['admin_error'];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
cw_hub_api_exit();