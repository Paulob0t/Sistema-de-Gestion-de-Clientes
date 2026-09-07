<?php
/**
 * Servicio principal del Chat Inteligente
 */
require_once __DIR__ . '/cw_chat_migrate.php';

function cw_chat_now(): string
{
    return date('Y-m-d H:i:s');
}

function cw_chat_uuid(): string
{
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff)
    );
}

function cw_chat_config_get(mysqli $conn, string $clave, string $default = ''): string
{
    if (!isset($GLOBALS['cw_chat_config_cache']) || $GLOBALS['cw_chat_config_cache'] === null) {
        $GLOBALS['cw_chat_config_cache'] = [];
        $r = $conn->query('SELECT clave, valor FROM cw_chat_config');
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $GLOBALS['cw_chat_config_cache'][$row['clave']] = (string) $row['valor'];
            }
        }
    }
    return $GLOBALS['cw_chat_config_cache'][$clave] ?? $default;
}

function cw_chat_config_set(mysqli $conn, string $clave, string $valor): void
{
    $now = cw_chat_now();
    $stmt = $conn->prepare('INSERT INTO cw_chat_config (clave, valor, updated_at) VALUES (?,?,?)
        ON DUPLICATE KEY UPDATE valor = VALUES(valor), updated_at = VALUES(updated_at)');
    if ($stmt) {
        $stmt->bind_param('sss', $clave, $valor, $now);
        $stmt->execute();
        $stmt->close();
    }
    $GLOBALS['cw_chat_config_cache'] = null;
}

function cw_chat_estado_label(string $estado): string
{
    $map = [
        'activa' => 'Activa',
        'bot' => 'Chatbot',
        'pendiente_humano' => 'Pendiente humano',
        'humano' => 'Con asesor',
        'cerrada' => 'Cerrada',
    ];
    return $map[$estado] ?? ucfirst($estado);
}

function cw_chat_estado_class(string $estado): string
{
    $map = [
        'activa' => 'ch-st-activa',
        'bot' => 'ch-st-bot',
        'pendiente_humano' => 'ch-st-pendiente',
        'humano' => 'ch-st-humano',
        'cerrada' => 'ch-st-cerrada',
    ];
    return $map[$estado] ?? 'ch-st-activa';
}

function cw_chat_prioridad_label(string $prioridad): string
{
    return ucfirst($prioridad);
}

/** @return array<string,mixed>|null */
function cw_chat_get_conversation(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare('SELECT * FROM cw_chat_conversaciones WHERE id = ? AND eliminado = 0 LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/** @return array<string,mixed>|null */
function cw_chat_get_conversation_by_uuid(mysqli $conn, string $uuid): ?array
{
    $stmt = $conn->prepare('SELECT * FROM cw_chat_conversaciones WHERE uuid = ? AND eliminado = 0 LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $uuid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Obtiene conversación activa del cliente o crea una nueva.
 * @return array{conversation:array<string,mixed>,created:bool}
 */
function cw_chat_get_or_create_conversation(mysqli $conn, int $clienteId, ?string $uuid = null): array
{
    cw_chat_migrate($conn);
    $now = cw_chat_now();

    if ($uuid) {
        $existing = cw_chat_get_conversation_by_uuid($conn, $uuid);
        if ($existing && (int) ($existing['cliente_id'] ?? 0) === $clienteId) {
            return ['conversation' => $existing, 'created' => false];
        }
    }

    $stmt = $conn->prepare("SELECT * FROM cw_chat_conversaciones
        WHERE cliente_id = ? AND estado != 'cerrada'
        ORDER BY ultimo_mensaje_at DESC, iniciada_at DESC LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $clienteId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            return ['conversation' => $row, 'created' => false];
        }
    }

    $newUuid = cw_chat_uuid();
    $saludo = cw_chat_config_get($conn, 'saludo_inicial');
    $titulo = 'Chat dashboard #' . substr($newUuid, 0, 8);

    $stmt = $conn->prepare("INSERT INTO cw_chat_conversaciones
        (uuid, cliente_id, canal, estado, prioridad, titulo, ultimo_mensaje_preview, ultimo_mensaje_at,
         ultimo_mensaje_remitente, sin_respuesta_desde, iniciada_at)
        VALUES (?,?,'dashboard','bot','media',?,?,?,'bot',NULL,?)");
    if (!$stmt) {
        throw new RuntimeException('No se pudo crear la conversación');
    }
    $preview = mb_substr($saludo, 0, 200);
    $stmt->bind_param('sissss', $newUuid, $clienteId, $titulo, $preview, $now, $now);
    $stmt->execute();
    $convId = (int) $conn->insert_id;
    $stmt->close();

    cw_chat_add_message($conn, $convId, 'bot', null, $saludo, 'texto', ['tipo' => 'saludo']);

    $conv = cw_chat_get_conversation($conn, $convId);
    return ['conversation' => $conv ?: [], 'created' => true];
}

/**
 * Busca conversación web abierta por session_id (metadata JSON).
 * @return array<string,mixed>|null
 */
function cw_chat_find_web_by_session(mysqli $conn, string $sessionId): ?array
{
    $sessionId = trim($sessionId);
    if ($sessionId === '') {
        return null;
    }
    $stmt = $conn->prepare("SELECT * FROM cw_chat_conversaciones
        WHERE canal = 'web' AND estado != 'cerrada'
          AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.session_id')) = ?
          AND eliminado = 0
        ORDER BY COALESCE(ultimo_mensaje_at, iniciada_at) DESC
        LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $sessionId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Conversación web por sesión de visitante (antes o después de registrar lead).
 * @param array<string,mixed> $meta
 * @return array{conversation:array<string,mixed>,created:bool}
 */
function cw_chat_get_or_create_web_session_conversation(
    mysqli $conn,
    string $sessionId,
    array $meta = []
): array {
    cw_chat_migrate($conn);
    $now = cw_chat_now();
    $sessionId = trim($sessionId);
    if ($sessionId === '') {
        $sessionId = 'anon_' . substr(cw_chat_uuid(), 0, 12);
    }

    $existing = cw_chat_find_web_by_session($conn, $sessionId);
    if ($existing) {
        return ['conversation' => $existing, 'created' => false];
    }

    $uuidParam = trim((string) ($meta['conversation_uuid'] ?? ''));
    if ($uuidParam !== '') {
        $byUuid = cw_chat_get_conversation_by_uuid($conn, $uuidParam);
        if ($byUuid && (string) ($byUuid['canal'] ?? '') === 'web') {
            return ['conversation' => $byUuid, 'created' => false];
        }
    }

    $newUuid = cw_chat_uuid();
    $titulo = 'Web · Visitante';
    $metaFull = array_merge(['origen' => 'web_chat', 'session_id' => $sessionId], $meta);
    unset($metaFull['conversation_uuid']);
    $metaJson = json_encode($metaFull, JSON_UNESCAPED_UNICODE);
    $preview = 'Visitante ingresó al chat web';

    $stmt = $conn->prepare("INSERT INTO cw_chat_conversaciones
        (uuid, cliente_id, lead_id, canal, estado, prioridad, titulo, ultimo_mensaje_preview, ultimo_mensaje_at,
         ultimo_mensaje_remitente, sin_respuesta_desde, iniciada_at, metadata)
        VALUES (?, NULL, NULL, 'web', 'bot', 'media', ?, ?, ?, 'sistema', NULL, ?, ?)");
    if (!$stmt) {
        throw new RuntimeException('No se pudo crear la conversación web');
    }
    $stmt->bind_param('ssssss', $newUuid, $titulo, $preview, $now, $now, $metaJson);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('No se pudo guardar la conversación web');
    }
    $convId = (int) $conn->insert_id;
    $stmt->close();

    cw_chat_add_message($conn, $convId, 'sistema', null, 'Visitante ingresó al chat del sitio web.', 'sistema', [
        'tipo' => 'ingreso',
        'origen' => 'web_chat',
        'session_id' => $sessionId,
    ]);

    $conv = cw_chat_get_conversation($conn, $convId);
    return ['conversation' => $conv ?: [], 'created' => true];
}

/**
 * Vincula conversación web existente a un lead (al completar registro).
 */
function cw_chat_link_web_conversation_to_lead(
    mysqli $conn,
    string $uuid,
    int $leadId,
    string $nombre,
    array $extraMeta = []
): ?array {
    $conv = cw_chat_get_conversation_by_uuid($conn, $uuid);
    if (!$conv || (string) ($conv['canal'] ?? '') !== 'web') {
        return null;
    }
    $meta = [];
    if (!empty($conv['metadata'])) {
        $decoded = json_decode((string) $conv['metadata'], true);
        if (is_array($decoded)) {
            $meta = $decoded;
        }
    }
    $meta = array_merge($meta, $extraMeta, ['lead_id' => $leadId, 'linked_at' => cw_chat_now()]);
    $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE);
    $safeName = trim($nombre) !== '' ? trim($nombre) : 'Visitante web';
    $titulo = 'Web · ' . mb_substr($safeName, 0, 80);
    $id = (int) $conv['id'];
    $stmt = $conn->prepare('UPDATE cw_chat_conversaciones SET lead_id = ?, titulo = ?, metadata = ? WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('issi', $leadId, $titulo, $metaJson, $id);
        $stmt->execute();
        $stmt->close();
    }
    return cw_chat_get_conversation($conn, $id);
}

/**
 * Conversación canal web ligada a un lead (sitio público).
 * @param array<string,mixed> $meta
 * @return array{conversation:array<string,mixed>,created:bool}
 */
function cw_chat_get_or_create_web_lead_conversation(
    mysqli $conn,
    int $leadId,
    string $nombre,
    string $saludo,
    array $meta = []
): array {
    cw_chat_migrate($conn);
    $now = cw_chat_now();

    $uuidHint = trim((string) ($meta['conversation_uuid'] ?? ''));
    if ($uuidHint !== '') {
        $linked = cw_chat_link_web_conversation_to_lead($conn, $uuidHint, $leadId, $nombre, $meta);
        if ($linked) {
            return ['conversation' => $linked, 'created' => false];
        }
    }

    $sessionId = trim((string) ($meta['session_id'] ?? ''));
    if ($sessionId !== '') {
        $bySession = cw_chat_find_web_by_session($conn, $sessionId);
        if ($bySession) {
            $linked = cw_chat_link_web_conversation_to_lead(
                $conn,
                (string) $bySession['uuid'],
                $leadId,
                $nombre,
                $meta
            );
            if ($linked) {
                return ['conversation' => $linked, 'created' => false];
            }
        }
    }

    if ($leadId > 0) {
        $stmt = $conn->prepare("SELECT * FROM cw_chat_conversaciones
            WHERE lead_id = ? AND canal = 'web' AND estado != 'cerrada'
            ORDER BY ultimo_mensaje_at DESC, iniciada_at DESC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $leadId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                return ['conversation' => $row, 'created' => false];
            }
        }
    }

    $newUuid = cw_chat_uuid();
    $safeName = trim($nombre) !== '' ? trim($nombre) : 'Visitante web';
    $titulo = 'Web · ' . mb_substr($safeName, 0, 80);
    $metaJson = json_encode(array_merge(['origen' => 'web_chat'], $meta), JSON_UNESCAPED_UNICODE);
    $preview = mb_substr($saludo !== '' ? $saludo : 'Lead web registrado', 0, 200);
    $leadBind = $leadId > 0 ? $leadId : null;

    if ($leadBind === null) {
        $stmt = $conn->prepare("INSERT INTO cw_chat_conversaciones
            (uuid, cliente_id, lead_id, canal, estado, prioridad, titulo, ultimo_mensaje_preview, ultimo_mensaje_at,
             ultimo_mensaje_remitente, sin_respuesta_desde, iniciada_at, metadata)
            VALUES (?, NULL, NULL, 'web', 'bot', 'media', ?, ?, ?, 'bot', NULL, ?, ?)");
        if (!$stmt) {
            throw new RuntimeException('No se pudo crear la conversación web');
        }
        $stmt->bind_param('ssssss', $newUuid, $titulo, $preview, $now, $now, $metaJson);
    } else {
        $stmt = $conn->prepare("INSERT INTO cw_chat_conversaciones
            (uuid, cliente_id, lead_id, canal, estado, prioridad, titulo, ultimo_mensaje_preview, ultimo_mensaje_at,
             ultimo_mensaje_remitente, sin_respuesta_desde, iniciada_at, metadata)
            VALUES (?, NULL, ?, 'web', 'bot', 'media', ?, ?, ?, 'bot', NULL, ?, ?)");
        if (!$stmt) {
            throw new RuntimeException('No se pudo crear la conversación web');
        }
        $stmt->bind_param('sisssss', $newUuid, $leadBind, $titulo, $preview, $now, $now, $metaJson);
    }
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('No se pudo guardar la conversación web');
    }
    $convId = (int) $conn->insert_id;
    $stmt->close();

    if ($saludo !== '') {
        cw_chat_add_message($conn, $convId, 'bot', null, $saludo, 'texto', ['tipo' => 'saludo', 'origen' => 'web_chat']);
    }

    $conv = cw_chat_get_conversation($conn, $convId);
    return ['conversation' => $conv ?: [], 'created' => true];
}

function cw_chat_add_message(
    mysqli $conn,
    int $conversacionId,
    string $remitenteTipo,
    ?int $remitenteId,
    string $contenido,
    string $tipo = 'texto',
    ?array $metadata = null
): int {
    $now = cw_chat_now();
    $metaJson = $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null;
    // mysqli no admite null en bind 'i'; usar 0 y convertir a NULL en SQL.
    $remitenteIdVal = $remitenteId !== null && $remitenteId > 0 ? (int) $remitenteId : 0;

    $stmt = $conn->prepare('INSERT INTO cw_chat_mensajes
        (conversacion_id, remitente_tipo, remitente_id, contenido, tipo, metadata, enviado_at)
        VALUES (?,?,NULLIF(?,0),?,?,?,?)');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('isissss', $conversacionId, $remitenteTipo, $remitenteIdVal, $contenido, $tipo, $metaJson, $now);
    if (!$stmt->execute()) {
        $stmt->close();
        return 0;
    }
    $msgId = (int) $conn->insert_id;
    $stmt->close();

    if ($msgId <= 0) {
        return 0;
    }

    $preview = mb_substr(trim(preg_replace('/\s+/', ' ', $contenido)), 0, 200);

    $upd = $conn->prepare('UPDATE cw_chat_conversaciones SET
        ultimo_mensaje_preview = ?,
        ultimo_mensaje_at = ?,
        ultimo_mensaje_remitente = ?,
        sin_respuesta_desde = CASE
            WHEN ? = "cliente" THEN ?
            WHEN ? IN ("bot","agente","sistema") THEN NULL
            ELSE sin_respuesta_desde END,
        leido_admin_at = CASE WHEN ? IN ("bot","agente","sistema") THEN NOW() ELSE leido_admin_at END
        WHERE id = ?');
    if ($upd) {
        $upd->bind_param('sssssssi', $preview, $now, $remitenteTipo, $remitenteTipo, $now, $remitenteTipo, $remitenteTipo, $conversacionId);
        $upd->execute();
        $upd->close();
    }

    if (!function_exists('cw_chat_bump_realtime')) {
        require_once __DIR__ . '/cw_chat_realtime.php';
    }
    cw_chat_bump_realtime($conn, $conversacionId);

    return $msgId;
}

/** @return array<int,array<string,mixed>> */
function cw_chat_get_messages_recent(mysqli $conn, int $conversacionId, int $limit = 40): array
{
    $stmt = $conn->prepare('SELECT m.*, COALESCE(a.Nombre, l.usuario) AS remitente_nombre
        FROM cw_chat_mensajes m
        LEFT JOIN agentes a ON m.remitente_tipo = "agente" AND m.remitente_id = a.Idusu
        LEFT JOIN login l ON m.remitente_tipo = "agente" AND m.remitente_id = l.id
        WHERE m.conversacion_id = ?
        ORDER BY m.id DESC LIMIT ?');
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('ii', $conversacionId, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = cw_chat_format_message_row($row);
    }
    $stmt->close();
    return array_reverse($rows);
}

/** @return array<int,array<string,mixed>> */
function cw_chat_get_messages(mysqli $conn, int $conversacionId, ?int $afterId = null, int $limit = 100): array
{
    $sql = 'SELECT m.*, COALESCE(a.Nombre, l.usuario) AS remitente_nombre
        FROM cw_chat_mensajes m
        LEFT JOIN agentes a ON m.remitente_tipo = "agente" AND m.remitente_id = a.Idusu
        LEFT JOIN login l ON m.remitente_tipo = "agente" AND m.remitente_id = l.id
        WHERE m.conversacion_id = ?';
    $params = [$conversacionId];
    $types = 'i';

    if ($afterId) {
        $sql .= ' AND m.id > ?';
        $params[] = $afterId;
        $types .= 'i';
    }

    $sql .= ' ORDER BY m.id ASC LIMIT ?';
    $params[] = $limit;
    $types .= 'i';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $out[] = cw_chat_format_message_row($row);
    }
    $stmt->close();
    return $out;
}

/** @param array<string,mixed> $row */
function cw_chat_format_message_row(array $row): array
{
    $meta = [];
    if (!empty($row['metadata'])) {
        $decoded = json_decode((string) $row['metadata'], true);
        if (is_array($decoded)) {
            $meta = $decoded;
        }
    }

    return [
        'id' => (int) $row['id'],
        'conversacion_id' => (int) $row['conversacion_id'],
        'remitente_tipo' => $row['remitente_tipo'],
        'remitente_id' => $row['remitente_id'] ? (int) $row['remitente_id'] : null,
        'remitente_nombre' => $row['remitente_nombre'] ?? cw_chat_remitente_label($row['remitente_tipo']),
        'contenido' => $row['contenido'],
        'tipo' => $row['tipo'],
        'metadata' => $meta,
        'enviado_at' => $row['enviado_at'],
        'leido_at' => $row['leido_at'],
        'delivery' => !empty($row['leido_at']) ? 'read' : 'delivered',
    ];
}

function cw_chat_remitente_label(string $tipo): string
{
    $map = [
        'cliente' => 'Tú',
        'bot' => 'Asistente',
        'agente' => 'Asesor',
        'sistema' => 'Sistema',
    ];
    return $map[$tipo] ?? ucfirst($tipo);
}

function cw_chat_escalate(mysqli $conn, int $conversacionId, string $motivo): void
{
    $now = cw_chat_now();
    $msg = cw_chat_config_get($conn, 'mensaje_escalamiento');
    $waMsg = cw_chat_config_get($conn, 'mensaje_whatsapp');
    $waNum = cw_chat_config_get($conn, 'whatsapp_numero', '524771181285');
    $waDigits = preg_replace('/\D/', '', (string) $waNum);
    if (strlen($waDigits) === 10) {
        $waDigits = '52' . $waDigits;
    }
    if ($waDigits === '') {
        $waDigits = '524771181285';
    }

    $stmt = $conn->prepare("UPDATE cw_chat_conversaciones SET
        estado = 'pendiente_humano', motivo_escalamiento = ?, prioridad = 'alta' WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('si', $motivo, $conversacionId);
        $stmt->execute();
        $stmt->close();
    }

    cw_chat_add_message($conn, $conversacionId, 'sistema', null, $msg, 'sistema', ['escalamiento' => true]);
    cw_chat_add_message($conn, $conversacionId, 'sistema', null,
        $waMsg . ' https://wa.me/' . $waDigits,
        'sistema',
        [
            'whatsapp' => true,
            'whatsapp_url' => 'https://wa.me/' . $waDigits,
            'acciones' => [
                ['tipo' => 'link', 'label' => 'Registrar ticket', 'url' => 'tickets.php', 'icon' => 'bi-inbox-fill', 'primary' => true],
                ['tipo' => 'whatsapp', 'label' => 'WhatsApp', 'url' => 'https://wa.me/' . $waDigits, 'icon' => 'bi-whatsapp', 'primary' => false],
                ['tipo' => 'send', 'label' => 'Hablar con agente', 'text' => 'quiero hablar con un agente', 'icon' => 'bi-headset', 'primary' => false],
            ],
        ]
    );

    if (!function_exists('cw_chat_alert_schedule')) {
        require_once __DIR__ . '/cw_chat_alerts.php';
    }
    $lastMsg = $conn->query('SELECT id FROM cw_chat_mensajes WHERE conversacion_id = ' . (int) $conversacionId . ' ORDER BY id DESC LIMIT 1');
    if ($lastMsg && ($row = $lastMsg->fetch_assoc())) {
        cw_chat_alert_schedule($conn, $conversacionId, (int) $row['id']);
    }
}

function cw_chat_bot_activo(string $estado): bool
{
    // Activo hasta que un asesor tome el control (humano) o se cierre.
    // pendiente_humano = aún puede responder el bot (solo aviso interno).
    return !in_array($estado, ['humano', 'cerrada'], true);
}

/**
 * Evita insertar el mismo mensaje del cliente dos veces (doble clic / reintento / carrera).
 * @return array{id:int,duplicado:bool}|null  null = no hay duplicado reciente
 */
function cw_chat_find_recent_cliente_duplicate(
    mysqli $conn,
    int $conversacionId,
    int $clienteId,
    string $mensaje,
    string $clientToken = '',
    int $withinSeconds = 30
): ?array {
    $withinSeconds = max(3, min(120, $withinSeconds));

    if ($clientToken !== '') {
        $stmt = $conn->prepare("SELECT id FROM cw_chat_mensajes
            WHERE conversacion_id = ? AND remitente_tipo = 'cliente'
              AND metadata LIKE ?
            ORDER BY id DESC LIMIT 1");
        if ($stmt) {
            $like = '%"client_token":"' . $clientToken . '"%';
            $stmt->bind_param('is', $conversacionId, $like);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                return ['id' => (int) $row['id'], 'duplicado' => true];
            }
        }
    }

    $stmt = $conn->prepare("SELECT id, contenido, enviado_at FROM cw_chat_mensajes
        WHERE conversacion_id = ? AND remitente_tipo = 'cliente' AND remitente_id = ?
        ORDER BY id DESC LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('ii', $conversacionId, $clienteId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }
    if (trim((string) ($row['contenido'] ?? '')) !== $mensaje) {
        return null;
    }
    $ts = strtotime((string) ($row['enviado_at'] ?? ''));
    if ($ts === false || (time() - $ts) > $withinSeconds) {
        return null;
    }
    return ['id' => (int) $row['id'], 'duplicado' => true];
}

/** Condición SQL: conversación pendiente de revisión admin (incl. solicita agente). */
function cw_chat_sql_por_revisar(string $alias = 'c'): string
{
    return "{$alias}.estado != 'cerrada'
        AND (
            {$alias}.leido_admin_at IS NULL
            OR ({$alias}.ultimo_mensaje_at > {$alias}.leido_admin_at AND {$alias}.ultimo_mensaje_remitente = 'cliente')
            OR (
                {$alias}.prioridad = 'alta'
                AND {$alias}.motivo_escalamiento IS NOT NULL
                AND TRIM({$alias}.motivo_escalamiento) != ''
                AND {$alias}.estado IN ('bot','activa','pendiente_humano')
            )
        )";
}

/** @param array<string,mixed> $row */
function cw_chat_row_solicita_agente(array $row): bool
{
    return ($row['prioridad'] ?? '') === 'alta'
        && trim((string) ($row['motivo_escalamiento'] ?? '')) !== ''
        && in_array($row['estado'] ?? '', ['bot', 'activa', 'pendiente_humano'], true);
}

/**
 * Sube prioridad sin pausar el chatbot (avisar a staff).
 * Mantiene la conversación en «por revisar» aunque el bot ya haya respondido.
 * @return bool true si es la primera vez que se marca (útil para enviar correo una sola vez)
 */
function cw_chat_flag_needs_human(mysqli $conn, int $conversacionId, string $motivo = ''): bool
{
    $first = false;
    $conv = cw_chat_get_conversation($conn, $conversacionId);
    if ($conv && !cw_chat_row_solicita_agente($conv)) {
        $first = true;
    }

    $now = cw_chat_now();
    $stmt = $conn->prepare("UPDATE cw_chat_conversaciones SET
        prioridad = 'alta',
        leido_admin_at = NULL,
        sin_respuesta_desde = COALESCE(sin_respuesta_desde, ?)
        WHERE id = ? AND estado IN ('bot','activa','pendiente_humano')");
    if ($stmt) {
        $stmt->bind_param('si', $now, $conversacionId);
        $stmt->execute();
        $stmt->close();
    }
    if ($motivo !== '') {
        $m = mb_substr('Cliente solicitó asesor: ' . $motivo, 0, 250);
        $u = $conn->prepare('UPDATE cw_chat_conversaciones SET motivo_escalamiento = ? WHERE id = ? AND (motivo_escalamiento IS NULL OR motivo_escalamiento = "")');
        if ($u) {
            $u->bind_param('si', $m, $conversacionId);
            $u->execute();
            $u->close();
        }
    }

    if (!function_exists('cw_chat_bump_realtime')) {
        require_once __DIR__ . '/cw_chat_realtime.php';
    }
    cw_chat_bump_realtime($conn, $conversacionId);

    return $first;
}

/**
 * Activa o desactiva el chatbot en una conversación.
 * @return array{estado:string,estado_label:string,bot_activo:bool}
 */
function cw_chat_set_bot_enabled(mysqli $conn, int $conversacionId, bool $enabled, ?int $agenteId = null): array
{
    $conv = cw_chat_get_conversation($conn, $conversacionId);
    if (!$conv || ($conv['estado'] ?? '') === 'cerrada') {
        throw new RuntimeException('Conversación no disponible');
    }

    if ($enabled) {
        $stmt = $conn->prepare("UPDATE cw_chat_conversaciones SET
            estado = 'bot', motivo_escalamiento = NULL, prioridad = 'media' WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $conversacionId);
            $stmt->execute();
            $stmt->close();
        }

        $botName = cw_chat_config_get($conn, 'bot_nombre', 'Asistente ConlineWeb');
        cw_chat_add_message($conn, $conversacionId, 'sistema', $agenteId,
            'El ' . $botName . ' está atendiendo esta conversación. Puedes seguir escribiendo y recibirás respuestas automáticas hasta que un asesor humano tome el control.',
            'sistema', ['bot_habilitado' => true]);
        if (!function_exists('cw_chatbot_clear_context')) {
            require_once __DIR__ . '/cw_chatbot_service.php';
        }
        cw_chatbot_clear_context($conn, $conversacionId);
    } else {
        if ($agenteId && $agenteId > 0) {
            cw_chat_assign_agent($conn, $conversacionId, $agenteId, 'Asesor tomó control — chatbot desactivado');
        } else {
            $stmt = $conn->prepare("UPDATE cw_chat_conversaciones SET estado = 'humano' WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $conversacionId);
                $stmt->execute();
                $stmt->close();
            }
        }

        cw_chat_add_message($conn, $conversacionId, 'sistema', $agenteId,
            'Un asesor humano ha tomado el control de esta conversación. El asistente automático está pausado.',
            'sistema', ['bot_desactivado' => true]);

        if (!function_exists('cw_chat_alert_cancel')) {
            require_once __DIR__ . '/cw_chat_alerts.php';
        }
        cw_chat_alert_cancel($conn, $conversacionId, 'control_humano');
    }

    if (!function_exists('cw_chat_bump_realtime')) {
        require_once __DIR__ . '/cw_chat_realtime.php';
    }
    cw_chat_bump_realtime($conn, $conversacionId);

    $updated = cw_chat_get_conversation($conn, $conversacionId);
    $estado = $updated['estado'] ?? 'humano';

    return [
        'estado' => $estado,
        'estado_label' => cw_chat_estado_label($estado),
        'estado_class' => cw_chat_estado_class($estado),
        'bot_activo' => cw_chat_bot_activo($estado),
    ];
}

function cw_chat_assign_agent(mysqli $conn, int $conversacionId, int $agenteId, ?string $notas = null): void
{
    $now = cw_chat_now();
    $stmt = $conn->prepare("UPDATE cw_chat_conversaciones SET estado = 'humano', responsable_id = ?, prioridad = 'media' WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('ii', $agenteId, $conversacionId);
        $stmt->execute();
        $stmt->close();
    }

    $ins = $conn->prepare('INSERT INTO cw_chat_asignaciones (conversacion_id, agente_id, asignado_at, notas) VALUES (?,?,?,?)');
    if ($ins) {
        $ins->bind_param('iiss', $conversacionId, $agenteId, $now, $notas);
        $ins->execute();
        $ins->close();
    }

    if (function_exists('cw_chat_notify_assigned')) {
        cw_chat_notify_assigned($conn, $conversacionId, $agenteId);
    }

    if (!function_exists('cw_chat_alert_cancel')) {
        require_once __DIR__ . '/cw_chat_alerts.php';
    }
    cw_chat_alert_cancel($conn, $conversacionId, 'asignada');
}

function cw_chat_close(mysqli $conn, int $conversacionId, ?int $agenteId = null): void
{
    $now = cw_chat_now();
    $stmt = $conn->prepare("UPDATE cw_chat_conversaciones SET estado = 'cerrada', cerrada_at = ? WHERE id = ? AND eliminado = 0");
    if ($stmt) {
        $stmt->bind_param('si', $now, $conversacionId);
        $stmt->execute();
        $stmt->close();
    }

    cw_chat_add_message($conn, $conversacionId, 'sistema', $agenteId,
        'Esta conversación ha sido cerrada. Si necesitas más ayuda, inicia un nuevo chat desde el panel.',
        'sistema', ['cerrada' => true]);

    if (function_exists('cw_chat_notify_closed')) {
        cw_chat_notify_closed($conn, $conversacionId);
    }

    if (!function_exists('cw_chat_alert_cancel')) {
        require_once __DIR__ . '/cw_chat_alerts.php';
    }
    cw_chat_alert_cancel($conn, $conversacionId, 'cerrada');
}

/**
 * Baja lógica de conversaciones de chat (se ocultan de la bandeja).
 *
 * @param list<int> $ids
 * @return array{ok:int,failed:int}
 */
function cw_chat_soft_delete(mysqli $conn, array $ids, ?int $agenteId = null): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function ($id) {
        return $id > 0;
    })));
    $ok = 0;
    $failed = 0;
    if ($ids === []) {
        return ['ok' => 0, 'failed' => 0];
    }

    $now = cw_chat_now();
    $sel = $conn->prepare('SELECT id FROM cw_chat_conversaciones WHERE id = ? AND eliminado = 0 LIMIT 1');
    $upd = $conn->prepare('UPDATE cw_chat_conversaciones
        SET eliminado = 1, eliminado_at = ?, estado = \'cerrada\', cerrada_at = COALESCE(cerrada_at, ?)
        WHERE id = ? AND eliminado = 0');

    if (!$sel || !$upd) {
        return ['ok' => 0, 'failed' => count($ids)];
    }

    if (!function_exists('cw_chat_alert_cancel')) {
        require_once __DIR__ . '/cw_chat_alerts.php';
    }

    foreach ($ids as $id) {
        $sel->bind_param('i', $id);
        $sel->execute();
        $row = $sel->get_result()->fetch_assoc();
        if (!$row) {
            $failed++;
            continue;
        }

        $upd->bind_param('ssi', $now, $now, $id);
        if (!$upd->execute() || $upd->affected_rows < 1) {
            $failed++;
            continue;
        }

        cw_chat_add_message(
            $conn,
            $id,
            'sistema',
            $agenteId,
            'Conversación dada de baja desde el CRM. Ya no aparece en la bandeja.',
            'sistema',
            ['eliminada' => true]
        );
        cw_chat_alert_cancel($conn, $id, 'eliminada');
        $ok++;
    }

    $sel->close();
    $upd->close();

    return ['ok' => $ok, 'failed' => $failed];
}

function cw_chat_mark_admin_read(mysqli $conn, int $conversacionId): void
{
    $now = cw_chat_now();
    $changed = false;

    $stmt = $conn->prepare("UPDATE cw_chat_mensajes SET leido_at = ?
        WHERE conversacion_id = ? AND remitente_tipo = 'cliente' AND leido_at IS NULL");
    if ($stmt) {
        $stmt->bind_param('si', $now, $conversacionId);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $changed = true;
        }
        $stmt->close();
    }

    $upd = $conn->prepare('UPDATE cw_chat_conversaciones SET leido_admin_at = ?
        WHERE id = ? AND (leido_admin_at IS NULL OR ultimo_mensaje_at > leido_admin_at)');
    if ($upd) {
        $upd->bind_param('si', $now, $conversacionId);
        $upd->execute();
        if ($upd->affected_rows > 0) {
            $changed = true;
        }
        $upd->close();
    }

    if (!$changed) {
        return;
    }

    if (!function_exists('cw_chat_bump_realtime')) {
        require_once __DIR__ . '/cw_chat_realtime.php';
    }
    cw_chat_bump_realtime($conn, $conversacionId);

    if (!function_exists('cw_chat_alert_cancel')) {
        require_once __DIR__ . '/cw_chat_alerts.php';
    }
    cw_chat_alert_cancel($conn, $conversacionId, 'vista_agente');
}

function cw_chat_log_unanswered(mysqli $conn, int $conversacionId, string $mensaje, ?string $intent = null): void
{
    $now = cw_chat_now();
    $stmt = $conn->prepare('SELECT id, veces FROM cw_chat_preguntas_sin_respuesta
        WHERE mensaje = ? AND resuelta = 0 LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $mensaje);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $id = (int) $row['id'];
            $veces = (int) $row['veces'] + 1;
            $upd = $conn->prepare('UPDATE cw_chat_preguntas_sin_respuesta SET veces = ?, updated_at = ? WHERE id = ?');
            if ($upd) {
                $upd->bind_param('isi', $veces, $now, $id);
                $upd->execute();
                $upd->close();
            }
            return;
        }
    }

    $ins = $conn->prepare('INSERT INTO cw_chat_preguntas_sin_respuesta
        (conversacion_id, mensaje, intent_detectado, veces, created_at) VALUES (?,?,?,1,?)');
    if ($ins) {
        $ins->bind_param('isss', $conversacionId, $mensaje, $intent, $now);
        $ins->execute();
        $ins->close();
    }
}

function cw_chat_is_business_hours(mysqli $conn): bool
{
    $inicio = cw_chat_config_get($conn, 'horario_inicio', '09:00');
    $fin = cw_chat_config_get($conn, 'horario_fin', '18:00');
    $dias = array_map('intval', explode(',', cw_chat_config_get($conn, 'dias_laborales', '1,2,3,4,5')));

    $tz = new DateTimeZone('America/Mexico_City');
    $now = new DateTime('now', $tz);
    $dow = (int) $now->format('N');
    if (!in_array($dow, $dias, true)) {
        return false;
    }

    $hora = $now->format('H:i');
    return $hora >= $inicio && $hora <= $fin;
}

/** @return array<int,array<string,mixed>> */
function cw_chat_list_admin(mysqli $conn, string $q = '', string $filtro = '', string $scope = 'all'): array
{
    $scope = $scope === 'web_sessions' ? 'web_sessions' : 'all';
    $sql = 'SELECT c.*,
                   COALESCE(cl.nombre_contacto, l.nombre) AS nombre_contacto,
                   COALESCE(cl.empresa, l.empresa) AS empresa,
                   COALESCE(cl.correo, l.correo) AS correo,
                   COALESCE(cl.telefono, l.telefono) AS telefono,
                   a.Nombre AS responsable_nombre
            FROM cw_chat_conversaciones c
            LEFT JOIN clientes cl ON c.cliente_id = cl.id
            LEFT JOIN leads l ON c.lead_id = l.id
            LEFT JOIN agentes a ON c.responsable_id = a.Idusu
            WHERE c.eliminado = 0';
    $params = [];
    $types = '';

    if ($scope === 'web_sessions') {
        $sql .= " AND c.canal = 'web'";
    }

    if ($q !== '') {
        $sql .= ' AND (COALESCE(cl.nombre_contacto, l.nombre) LIKE ?
            OR COALESCE(cl.empresa, l.empresa) LIKE ?
            OR COALESCE(cl.correo, l.correo) LIKE ?
            OR c.titulo LIKE ?
            OR CAST(c.id AS CHAR) = ?
            OR CAST(c.lead_id AS CHAR) = ?
            OR c.metadata LIKE ?)';
        $like = '%' . $q . '%';
        $types .= 'sssssss';
        $params = array_merge($params, [$like, $like, $like, $like, $q, $q, $like]);
    }

    if ($filtro === 'pendientes') {
        $sql .= " AND c.estado IN ('pendiente_humano','humano') AND c.sin_respuesta_desde IS NOT NULL";
    } elseif ($filtro === 'bot') {
        $sql .= " AND c.estado = 'bot'";
    } elseif ($filtro === 'cerradas') {
        $sql .= " AND c.estado = 'cerrada'";
    } elseif ($filtro === 'sin_respuesta') {
        $sql .= ' AND c.sin_respuesta_desde IS NOT NULL AND c.estado != "cerrada"';
    } elseif ($filtro === 'atendidas') {
        $sql .= " AND c.estado IN ('humano','cerrada') AND c.sin_respuesta_desde IS NULL";
    } elseif ($filtro === 'humano') {
        $sql .= " AND c.estado = 'humano'";
    } elseif ($filtro === 'por_revisar') {
        $sql .= ' AND ' . cw_chat_sql_por_revisar('c');
    } elseif ($filtro === 'en_vivo' && $scope === 'web_sessions') {
        // Activas en las últimas 2 horas
        $sql .= " AND c.estado != 'cerrada' AND COALESCE(c.ultimo_mensaje_at, c.iniciada_at) >= DATE_SUB(NOW(), INTERVAL 2 HOUR)";
    } elseif ($filtro === 'sin_registro' && $scope === 'web_sessions') {
        $sql .= ' AND (c.lead_id IS NULL OR c.lead_id = 0) AND (c.cliente_id IS NULL OR c.cliente_id = 0)';
    }

    $sql .= ' ORDER BY COALESCE(c.ultimo_mensaje_at, c.iniciada_at) DESC LIMIT 150';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    $minSinResp = (int) cw_chat_config_get($conn, 'alerta_sin_respuesta_minutos', '5');
    while ($row = $res->fetch_assoc()) {
        $out[] = cw_chat_format_conversation_row($conn, $row, $minSinResp);
    }
    $stmt->close();
    return $out;
}

/** @param array<string,mixed> $row */
function cw_chat_format_conversation_row(mysqli $conn, array $row, ?int $minSinResp = null): array
{
    if ($minSinResp === null) {
        $minSinResp = (int) cw_chat_config_get($conn, 'alerta_sin_respuesta_minutos', '5');
    }
    $sinRespuesta = false;
    if (!empty($row['sin_respuesta_desde']) && ($row['estado'] ?? '') !== 'cerrada') {
        $diff = time() - strtotime((string) $row['sin_respuesta_desde']);
        $sinRespuesta = $diff >= ($minSinResp * 60);
    }

    $solicitaAgente = cw_chat_row_solicita_agente($row);
    $porRevisar = empty($row['leido_admin_at'])
        || (!empty($row['ultimo_mensaje_at']) && !empty($row['leido_admin_at'])
            && strtotime((string) $row['ultimo_mensaje_at']) > strtotime((string) $row['leido_admin_at'])
            && ($row['ultimo_mensaje_remitente'] ?? '') === 'cliente')
        || $solicitaAgente;

    $meta = [];
    if (!empty($row['metadata'])) {
        $decoded = json_decode((string) $row['metadata'], true);
        if (is_array($decoded)) {
            $meta = $decoded;
        }
    }
    $sessionId = trim((string) ($meta['session_id'] ?? ''));
    $sessionShort = $sessionId !== '' ? mb_substr($sessionId, -8) : '';
    $paginaOrigen = trim((string) ($meta['pagina_origen'] ?? ''));
    $interes = trim((string) ($meta['interes'] ?? $meta['mensaje_inicial'] ?? ''));
    $servicio = trim((string) ($meta['servicio'] ?? ''));

    $nombre = trim((string) ($row['nombre_contacto'] ?? ''));
    $canal = (string) ($row['canal'] ?? '');
    $leadId = !empty($row['lead_id']) ? (int) $row['lead_id'] : null;
    $clienteId = !empty($row['cliente_id']) ? (int) $row['cliente_id'] : null;

    if ($nombre === '') {
        if ($canal === 'web') {
            $nombre = $leadId
                ? ('Lead #' . $leadId)
                : ('Visitante en vivo' . ($sessionShort !== '' ? (' · ' . $sessionShort) : ''));
        } else {
            $nombre = $clienteId ? ('Cliente #' . $clienteId) : ('Chat #' . (int) ($row['id'] ?? 0));
        }
    }

    return [
        'id' => (int) $row['id'],
        'uuid' => $row['uuid'],
        'cliente_id' => $clienteId,
        'lead_id' => $leadId,
        'nombre' => $nombre,
        'empresa' => $row['empresa'] ?? '',
        'correo' => $row['correo'] ?? '',
        'telefono' => $row['telefono'] ?? '',
        'estado' => $row['estado'],
        'estado_label' => cw_chat_estado_label($row['estado']),
        'estado_class' => cw_chat_estado_class($row['estado']),
        'prioridad' => $row['prioridad'],
        'prioridad_label' => cw_chat_prioridad_label($row['prioridad']),
        'responsable_id' => $row['responsable_id'] ? (int) $row['responsable_id'] : null,
        'responsable_nombre' => $row['responsable_nombre'] ?? 'Sin asignar',
        'ultimo_mensaje_preview' => $row['ultimo_mensaje_preview'] ?? '',
        'ultimo_mensaje_at' => $row['ultimo_mensaje_at'] ?? $row['iniciada_at'],
        'sin_respuesta' => $sinRespuesta,
        'por_revisar' => $porRevisar,
        'solicita_agente' => $solicitaAgente,
        'iniciada_at' => $row['iniciada_at'],
        'canal' => $canal,
        'session_id' => $sessionId,
        'session_short' => $sessionShort,
        'pagina_origen' => $paginaOrigen,
        'interes' => $interes,
        'servicio' => $servicio,
        'sin_registro' => ($canal === 'web' && !$leadId && !$clienteId),
        'titulo' => (string) ($row['titulo'] ?? ''),
    ];
}

/** @return array{total:int,pendientes:int,sin_respuesta:int,bot:int} */
function cw_chat_stats_list(mysqli $conn, array $rows): array
{
    $stats = ['total' => 0, 'pendientes' => 0, 'sin_respuesta' => 0, 'bot' => 0, 'por_revisar' => 0];
    foreach ($rows as $row) {
        $stats['total']++;
        if (in_array($row['estado'], ['pendiente_humano', 'humano'], true)) {
            $stats['pendientes']++;
        }
        if (!empty($row['sin_respuesta'])) {
            $stats['sin_respuesta']++;
        }
        if ($row['estado'] === 'bot') {
            $stats['bot']++;
        }
        if (!empty($row['por_revisar'])) {
            $stats['por_revisar']++;
        }
    }
    return $stats;
}
