<?php
/**
 * IA SEO México — propone textos (hubs) y artículos de blog con OpenAI.
 * Nunca escribe solo: requiere aprobación explícita (apply).
 */
require_once __DIR__ . '/openai_config.php';
require_once __DIR__ . '/cw_seo_mexico_checklist.php';
require_once __DIR__ . '/cw_seo_mexico_autofix.php';

function cw_seo_mexico_ai_model(): string
{
    return 'gpt-4o-mini';
}

function cw_seo_mexico_ai_available(): bool
{
    return function_exists('openai_key_configured') && openai_key_configured();
}

/**
 * Diagnóstico de configuración IA (sin exponer la clave completa).
 *
 * @return array{
 *   active:bool,
 *   configured:bool,
 *   label:string,
 *   detail:string,
 *   model:string,
 *   key_source:string,
 *   key_suffix:string,
 *   key_format_ok:bool,
 *   curl_ok:bool,
 *   host:string,
 *   production_host:bool,
 *   mock:bool,
 *   site_writable:bool
 * }
 */
function cw_seo_mexico_ai_status(): array
{
    $configured = cw_seo_mexico_ai_available();
    $key = (defined('OPENAI_API_KEY') && is_string(OPENAI_API_KEY)) ? trim(OPENAI_API_KEY) : '';
    $suffix = $key !== '' ? substr($key, -4) : '';
    $formatOk = $key !== '' && (bool) preg_match('/^sk-[A-Za-z0-9_\-]{10,}$/', $key);

    $source = 'none';
    if ($key !== '') {
        $envKey = function_exists('openai_resolve_env_key') ? openai_resolve_env_key() : '';
        $secretsFile = __DIR__ . '/openai_config.secrets.php';
        $localFile = __DIR__ . '/openai_config.local.php';
        $default = defined('CW_OPENAI_API_KEY_DEFAULT') ? (string) CW_OPENAI_API_KEY_DEFAULT : '';
        if ($envKey !== '' && hash_equals($envKey, $key)) {
            $source = 'env';
        } elseif (is_file($secretsFile) && $key !== $default) {
            $source = 'secrets';
        } elseif ($default !== '' && hash_equals($default, $key)) {
            $source = 'default';
        } elseif (is_file($localFile)) {
            $source = 'local';
        } else {
            $source = 'configured';
        }
    }

    $curlOk = function_exists('curl_init');
    $mock = function_exists('openai_dev_mock_enabled') && openai_dev_mock_enabled();
    $prod = function_exists('openai_is_production_host') && openai_is_production_host();
    $host = function_exists('openai_request_host') ? openai_request_host() : (string) ($_SERVER['HTTP_HOST'] ?? '');
    $siteRoot = cw_seo_mexico_autofix_site_root();
    $siteWritable = $siteRoot !== null && is_dir($siteRoot) && is_writable($siteRoot);

    $active = $configured && $formatOk && $curlOk && !$mock;
    $tone = 'off'; // on | warn | off
    if ($active) {
        $label = 'IA activa';
        $detail = 'Clave OpenAI cargada · modelo ' . cw_seo_mexico_ai_model() . ' · origen ' . $source;
        $tone = 'on';
    } elseif ($mock) {
        $label = 'IA en mock local';
        $detail = 'Modo desarrollo sin clave real (OPENAI_DEV_MOCK). No genera contenido real.';
        $tone = 'warn';
    } elseif (!$configured) {
        $label = 'IA inactiva';
        $detail = 'Falta OPENAI_API_KEY (env, openai_config.secrets.php o local).';
    } elseif (!$formatOk) {
        $label = 'IA mal configurada';
        $detail = 'La clave no tiene formato OpenAI válido (debe empezar con sk-).';
        $tone = 'warn';
    } elseif (!$curlOk) {
        $label = 'IA no usable';
        $detail = 'PHP cURL no está disponible; no se puede llamar a OpenAI.';
    } else {
        $label = 'IA inactiva';
        $detail = 'Configuración incompleta.';
    }

    return [
        'active' => $active,
        'configured' => $configured,
        'tone' => $tone,
        'label' => $label,
        'detail' => $detail,
        'model' => cw_seo_mexico_ai_model(),
        'key_source' => $source,
        'key_suffix' => $suffix,
        'key_format_ok' => $formatOk,
        'curl_ok' => $curlOk,
        'host' => $host,
        'production_host' => $prod,
        'mock' => $mock,
        'site_writable' => $siteWritable,
    ];
}

/**
 * Valida la clave con un ping ligero a OpenAI (models list).
 *
 * @return array{ok:bool,reachable:bool,active:bool,error?:string,http_code?:int,latency_ms?:int}
 */
function cw_seo_mexico_ai_validate_connection(): array
{
    $status = cw_seo_mexico_ai_status();
    if (empty($status['configured'])) {
        return [
            'ok' => false,
            'reachable' => false,
            'active' => false,
            'error' => 'No hay clave OpenAI configurada.',
            'status' => $status,
        ];
    }
    if (empty($status['curl_ok'])) {
        return [
            'ok' => false,
            'reachable' => false,
            'active' => false,
            'error' => 'Extensión cURL no disponible.',
            'status' => $status,
        ];
    }

    $t0 = microtime(true);
    $ch = curl_init('https://api.openai.com/v1/models?limit=1');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . OPENAI_API_KEY,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_TIMEOUT => 25,
    ]);
    $result = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $ms = (int) round((microtime(true) - $t0) * 1000);

    if ($result === false) {
        return [
            'ok' => false,
            'reachable' => false,
            'active' => false,
            'error' => 'No se pudo conectar: ' . $err,
            'latency_ms' => $ms,
            'status' => $status,
        ];
    }

    if ($code === 401 || $code === 403) {
        $status['active'] = false;
        $status['tone'] = 'warn';
        $status['label'] = 'IA clave inválida';
        $status['detail'] = 'OpenAI rechazó la clave (HTTP ' . $code . ').';
        return [
            'ok' => false,
            'reachable' => true,
            'active' => false,
            'error' => 'Clave rechazada por OpenAI (HTTP ' . $code . '). Revisa o renueva la API key.',
            'http_code' => $code,
            'latency_ms' => $ms,
            'status' => $status,
        ];
    }
    if ($code >= 400) {
        $status['active'] = false;
        $status['tone'] = 'warn';
        $status['label'] = 'IA sin respuesta OK';
        $status['detail'] = 'OpenAI respondió HTTP ' . $code;
        return [
            'ok' => false,
            'reachable' => true,
            'active' => false,
            'error' => 'OpenAI respondió HTTP ' . $code,
            'http_code' => $code,
            'latency_ms' => $ms,
            'status' => $status,
        ];
    }

    $status['active'] = true;
    $status['tone'] = 'on';
    $status['label'] = 'IA activa y verificada';
    $status['detail'] = 'Conexión OK con OpenAI · ' . $ms . ' ms · modelo ' . $status['model'];

    return [
        'ok' => true,
        'reachable' => true,
        'active' => true,
        'http_code' => $code,
        'latency_ms' => $ms,
        'message' => 'OpenAI responde correctamente.',
        'status' => $status,
    ];
}

/**
 * @param list<array{role:string,content:string}> $messages
 * @return array{ok:bool,content?:string,error?:string,raw?:mixed}
 */
function cw_seo_mexico_ai_chat(array $messages, float $temperature = 0.55, bool $jsonMode = true): array
{
    if (!cw_seo_mexico_ai_available()) {
        return ['ok' => false, 'error' => 'OpenAI no configurada (OPENAI_API_KEY).'];
    }
    $apiKey = OPENAI_API_KEY;
    $payload = [
        'model' => cw_seo_mexico_ai_model(),
        'messages' => $messages,
        'temperature' => $temperature,
    ];
    if ($jsonMode) {
        $payload['response_format'] = ['type' => 'json_object'];
    }
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => 90,
    ]);
    $result = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($result === false) {
        return ['ok' => false, 'error' => 'cURL: ' . $err];
    }
    $data = json_decode((string) $result, true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'Respuesta OpenAI inválida', 'raw' => $result];
    }
    if (!empty($data['error'])) {
        return ['ok' => false, 'error' => (string) ($data['error']['message'] ?? 'Error OpenAI'), 'raw' => $data];
    }
    if ($code >= 400) {
        return ['ok' => false, 'error' => 'HTTP ' . $code, 'raw' => $data];
    }
    $content = (string) ($data['choices'][0]['message']['content'] ?? '');
    if ($content === '') {
        return ['ok' => false, 'error' => 'OpenAI sin contenido', 'raw' => $data];
    }
    return ['ok' => true, 'content' => $content, 'raw' => $data];
}

function cw_seo_mexico_ai_proposals_ensure_table(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS cw_seo_mexico_ai_proposals (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            kind VARCHAR(40) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            title VARCHAR(255) NOT NULL DEFAULT '',
            target_key VARCHAR(120) NOT NULL DEFAULT '',
            target_url VARCHAR(500) NOT NULL DEFAULT '',
            prompt_summary VARCHAR(500) NOT NULL DEFAULT '',
            detail MEDIUMTEXT,
            before_json LONGTEXT,
            after_json LONGTEXT,
            executed_before_json LONGTEXT,
            executed_after_json LONGTEXT,
            apply_log TEXT,
            created_by INT UNSIGNED DEFAULT 0,
            applied_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL,
            applied_at DATETIME DEFAULT NULL,
            KEY idx_status (status),
            KEY idx_kind (kind),
            KEY idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    // Migración suave si la tabla ya existía
    $cols = [];
    $res = $conn->query('SHOW COLUMNS FROM cw_seo_mexico_ai_proposals');
    while ($res && ($c = $res->fetch_assoc())) {
        $cols[(string) ($c['Field'] ?? '')] = true;
    }
    if (empty($cols['detail'])) {
        $conn->query('ALTER TABLE cw_seo_mexico_ai_proposals ADD COLUMN detail MEDIUMTEXT NULL AFTER prompt_summary');
    }
    if (empty($cols['executed_before_json'])) {
        $conn->query('ALTER TABLE cw_seo_mexico_ai_proposals ADD COLUMN executed_before_json LONGTEXT NULL AFTER after_json');
    }
    if (empty($cols['executed_after_json'])) {
        $conn->query('ALTER TABLE cw_seo_mexico_ai_proposals ADD COLUMN executed_after_json LONGTEXT NULL AFTER executed_before_json');
    }
}

/**
 * Resuelve la URL pública canónica de una propuesta (donde se aplica o la URL nueva).
 *
 * @param array<string,mixed> $after
 * @return array{url:string,url_kind:string,url_label:string}
 */
function cw_seo_mexico_ai_resolve_public_url(string $kind, string $targetKey, string $targetUrl, array $after = []): array
{
    $base = 'https://conlineweb.com';
    $fromAfter = trim((string) ($after['url'] ?? $after['target_url'] ?? $after['page_url'] ?? ''));
    $given = trim($targetUrl !== '' ? $targetUrl : $fromAfter);
    $key = trim($targetKey);
    $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($after['slug'] ?? $key))) ?? '';
    $path = str_replace('\\', '/', ltrim((string) ($after['path'] ?? $key), '/'));

    $url = '';
    $urlKind = 'page'; // page|new_page|file
    $urlLabel = 'URL donde se aplica';

    if ($kind === 'blog_post') {
        $url = $slug !== '' ? $base . '/blog/articulo/' . $slug . '/' : $given;
        $urlKind = 'new_page';
        $urlLabel = 'URL nueva (artículo)';
    } elseif ($kind === 'blog_improve') {
        $url = $slug !== '' ? $base . '/blog/articulo/' . $slug . '/' : $given;
        $urlKind = 'page';
        $urlLabel = 'URL del artículo (mejora)';
    } elseif ($kind === 'hub_text') {
        $city = preg_replace('/[^a-z0-9\-]/', '', strtolower($key)) ?? '';
        $url = $city !== '' ? $base . '/mexico/ciudades/' . $city . '/' : $given;
        $urlKind = 'page';
        $urlLabel = 'URL del hub (cambio)';
    } elseif ($kind === 'short_url_commercial') {
        $url = $given !== '' ? $given : $base . '/';
        $urlKind = 'page';
        $urlLabel = 'URL corta comercial (indexar)';
    } elseif ($kind === 'design_ui') {
        $url = $given !== '' ? $given : $base . '/';
        $urlKind = 'page';
        $urlLabel = 'URL de página (preview de diseño)';
    } elseif ($kind === 'audit_fix') {
        $url = $given !== '' ? $given : $base . '/';
        $urlKind = 'page';
        $urlLabel = 'URL del hallazgo (corrección)';
    } elseif ($kind === 'external_task' || $kind === 'external_rec') {
        $url = $given !== '' ? $given : $base . '/';
        $urlKind = 'page';
        $urlLabel = 'Referencia (tarea SEO/GEO externa)';
    } elseif ($kind === 'admin_patch' || str_starts_with($kind, 'admin_')) {
        $admBase = 'https://adm.conlineweb.com';
        $viewUrl = trim((string) ($after['url'] ?? $given));
        if ($viewUrl !== '' && preg_match('#^https?://#i', $viewUrl)) {
            $url = $viewUrl;
        } else {
            $relAdmin = (string) ($after['admin_url'] ?? $path);
            $url = $admBase . '/' . ltrim(str_replace('\\', '/', $relAdmin), '/');
        }
        $urlKind = 'page';
        $urlLabel = 'Vista admin afectada';
    } elseif ($kind === 'cliente_patch' || str_starts_with($kind, 'cliente_')) {
        $cliBase = 'https://cliente.conlineweb.com';
        $viewUrl = trim((string) ($after['url'] ?? $given));
        if ($viewUrl !== '' && preg_match('#^https?://#i', $viewUrl)) {
            $url = $viewUrl;
        } else {
            $url = $cliBase . '/';
        }
        $urlKind = 'page';
        $urlLabel = 'Vista portal cliente';
    } elseif ($kind === 'site_patch') {
        // Intentar mapear archivo → página pública
        if (preg_match('#mexico/ciudades/([a-z0-9\-]+)#', $path, $m)
            || preg_match('#ai-hub-patches/([a-z0-9\-]+)\.json$#', $path, $m)) {
            $url = $base . '/mexico/ciudades/' . $m[1] . '/';
            $urlLabel = 'URL de página afectada';
        } elseif (preg_match('#blog/content/([a-z0-9\-]+)\.php$#', $path, $m)) {
            $url = $base . '/blog/articulo/' . $m[1] . '/';
            $urlLabel = 'URL de página afectada';
        } elseif (preg_match('#^blog/categoria/([a-z0-9\-]+)#', $path, $m)) {
            $url = $base . '/blog/categoria/' . $m[1] . '/';
            $urlLabel = 'URL de página afectada';
        } elseif ($path !== '' && preg_match('#\.(php|html?)$#i', $path) && !str_starts_with($path, 'includes/')) {
            $pub = preg_replace('#/index\.php$#i', '/', $path) ?? $path;
            $pub = preg_replace('#\.php$#i', '/', $pub) ?? $pub;
            $url = $base . '/' . ltrim((string) $pub, '/');
            if (!str_ends_with($url, '/')) {
                $url .= '/';
            }
            $urlLabel = 'URL de página afectada';
        } elseif (in_array($path, ['includes/layout-nav.php', 'includes/footer.php', 'includes/cw-nap.php'], true)) {
            $url = $base . '/';
            $urlLabel = 'URL afectada (sitio global)';
        } else {
            $url = $given !== '' ? $given : ($path !== '' ? $base . '/' . $path : '');
            $urlKind = 'file';
            $urlLabel = 'URL / archivo afectado';
        }
    } else {
        $url = $given;
        $urlLabel = 'URL destino';
    }

    if ($url === '' && $given !== '') {
        $url = $given;
    }
    // Normalizar https absolutas
    if ($url !== '' && !preg_match('#^https?://#i', $url)) {
        $url = $base . '/' . ltrim($url, '/');
    }

    return [
        'url' => $url,
        'url_kind' => $urlKind,
        'url_label' => $urlLabel,
    ];
}

/**
 * Construye el detalle/fundamento obligatorio de una propuesta.
 *
 * @param array<string,mixed> $before
 * @param array<string,mixed> $after
 */
function cw_seo_mexico_ai_build_proposal_detail(
    string $kind,
    string $title,
    string $promptSummary,
    array $before,
    array $after,
    string $targetUrl = ''
): string {
    $rationale = trim((string) ($after['rationale'] ?? $after['why'] ?? ''));
    $summary = trim((string) ($after['summary'] ?? $promptSummary));
    $urlMeta = cw_seo_mexico_ai_resolve_public_url(
        $kind,
        (string) ($after['slug'] ?? $after['path'] ?? ''),
        $targetUrl !== '' ? $targetUrl : (string) ($after['url'] ?? ''),
        $after
    );
    $url = (string) ($urlMeta['url'] ?? '');
    $urlLabel = (string) ($urlMeta['url_label'] ?? 'URL destino');

    $lines = [];
    $lines[] = 'FUNDAMENTO';
    if ($rationale !== '') {
        $lines[] = $rationale;
    } elseif ($summary !== '') {
        $lines[] = $summary;
    } else {
        $lines[] = 'Mejora alineada a objetivos SEO/GEO y calidad del sitio ConlineWeb.';
    }
    $lines[] = '';
    $lines[] = 'URL';
    if ($url !== '') {
        $lines[] = $urlLabel . ': ' . $url;
        if (($urlMeta['url_kind'] ?? '') === 'new_page') {
            $lines[] = 'Tipo: URL nueva que se añadirá al sitio e índice.';
        } else {
            $lines[] = 'Tipo: URL existente donde se aplicará el cambio.';
        }
    } else {
        $lines[] = 'Pendiente de URL (debe completarse antes de aplicar).';
    }
    $lines[] = '';
    $lines[] = 'QUÉ CAMBIA';
    $lines[] = 'Tipo: ' . $kind . ' · ' . $title;
    if (!empty($after['path'])) {
        $lines[] = 'Archivo: ' . (string) $after['path'];
    }
    if (!empty($after['slug'])) {
        $lines[] = 'Slug: ' . (string) $after['slug'];
    }
    if (!empty($after['category'])) {
        $lines[] = 'Categoría: ' . (string) $after['category'];
    }
    if (!empty($after['change_type'])) {
        $lines[] = 'Cambio: ' . (string) $after['change_type'];
    }
    $lines[] = '';
    $lines[] = 'ANTES (propuesta)';
    $lines[] = cw_seo_mexico_monitor_summarize_safe($before, 500);
    $lines[] = '';
    $lines[] = 'DESPUÉS (propuesta)';
    $lines[] = cw_seo_mexico_monitor_summarize_safe($after, 500);
    $lines[] = '';
    $lines[] = 'Nota: la implementación guarda además el antes/después real ejecutado y confirma la URL aplicada.';
    return trim(implode("\n", $lines));
}

/**
 * Summarize sin depender del monitor (evita require circular).
 *
 * @param mixed $data
 */
function cw_seo_mexico_monitor_summarize_safe($data, int $max = 280): string
{
    if (function_exists('cw_seo_mexico_monitor_summarize')) {
        return cw_seo_mexico_monitor_summarize($data, $max);
    }
    if (is_string($data)) {
        $t = trim($data);
        return mb_strlen($t) > $max ? mb_substr($t, 0, $max) . '…' : $t;
    }
    if (!is_array($data) || $data === []) {
        return '— (sin estado previo / contenido nuevo)';
    }
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    $json = is_string($json) ? $json : '—';
    return mb_strlen($json) > $max ? mb_substr($json, 0, $max) . '…' : $json;
}

/**
 * @param array<string,mixed> $before
 * @param array<string,mixed> $after
 */
function cw_seo_mexico_ai_save_proposal(
    mysqli $conn,
    string $kind,
    string $title,
    string $targetKey,
    string $targetUrl,
    string $promptSummary,
    array $before,
    array $after,
    int $userId = 0
): array {
    cw_seo_mexico_ai_proposals_ensure_table($conn);

    // Exigir fundamento explícito (no reutilizar el campo detail construido)
    $rationale = trim((string) ($after['rationale'] ?? $after['why'] ?? ''));
    if ($rationale === '') {
        $rationale = trim($promptSummary);
    }
    if (mb_strlen($rationale) < 20) {
        return [
            'ok' => false,
            'error' => 'Toda propuesta debe ir fundamentada (rationale ≥ 20 caracteres).',
        ];
    }
    $after['rationale'] = $rationale;

    // URL obligatoria en la misma propuesta (donde se aplica o URL nueva)
    $urlMeta = cw_seo_mexico_ai_resolve_public_url($kind, $targetKey, $targetUrl, $after);
    $targetUrl = trim((string) ($urlMeta['url'] ?? ''));
    if ($targetUrl === '') {
        return [
            'ok' => false,
            'error' => 'Toda propuesta debe incluir la URL donde se aplica el cambio o la URL nueva.',
        ];
    }
    $after['url'] = $targetUrl;
    $after['url_kind'] = (string) ($urlMeta['url_kind'] ?? 'page');
    $after['url_label'] = (string) ($urlMeta['url_label'] ?? 'URL destino');

    $detail = cw_seo_mexico_ai_build_proposal_detail($kind, $title, $promptSummary, $before, $after, $targetUrl);
    $after['detail'] = $detail;

    $beforeJ = json_encode($before, JSON_UNESCAPED_UNICODE);
    $afterJ = json_encode($after, JSON_UNESCAPED_UNICODE);
    $stmt = $conn->prepare(
        'INSERT INTO cw_seo_mexico_ai_proposals
         (kind, status, title, target_key, target_url, prompt_summary, detail, before_json, after_json, created_by, created_at)
         VALUES (?, \'pending\', ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    if (!$stmt) {
        return ['ok' => false, 'error' => 'No se pudo guardar propuesta'];
    }
    $uid = max(0, $userId);
    $stmt->bind_param(
        'ssssssssi',
        $kind,
        $title,
        $targetKey,
        $targetUrl,
        $promptSummary,
        $detail,
        $beforeJ,
        $afterJ,
        $uid
    );
    $stmt->execute();
    $id = (int) $stmt->insert_id;
    $stmt->close();

    // Campanita + correo: avisar que hay propuesta nueva
    if ($id > 0) {
        require_once __DIR__ . '/cw_site_ai_alerts.php';
        cw_site_ai_alerts_notify_proposal($conn, [
            'id' => $id,
            'kind' => $kind,
            'title' => $title,
            'target_url' => $targetUrl,
            'prompt_summary' => $promptSummary !== '' ? $promptSummary : mb_substr($rationale, 0, 400),
            'detail' => $detail,
            'after' => $after,
        ], $userId);
    }

    return [
        'ok' => true,
        'id' => $id,
        'detail' => $detail,
        'url' => $targetUrl,
        'url_label' => (string) ($after['url_label'] ?? 'URL destino'),
        'url_kind' => (string) ($after['url_kind'] ?? 'page'),
    ];
}

/**
 * @return list<array<string,mixed>>
 */
function cw_seo_mexico_ai_list_proposals(mysqli $conn, string $status = 'pending', int $limit = 30): array
{
    cw_seo_mexico_ai_proposals_ensure_table($conn);
    $limit = max(1, min(80, $limit));
    $status = preg_replace('/[^a-z_]/', '', strtolower($status)) ?? 'pending';
    $rows = [];
    $stmt = $conn->prepare(
        'SELECT id, kind, status, title, target_key, target_url, prompt_summary, detail,
                before_json, after_json, executed_before_json, executed_after_json,
                apply_log, created_at, applied_at
         FROM cw_seo_mexico_ai_proposals WHERE status = ? ORDER BY id DESC LIMIT ' . $limit
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('s', $status);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($r = $res->fetch_assoc())) {
        $r['before'] = json_decode((string) ($r['before_json'] ?? ''), true) ?: [];
        $r['after'] = json_decode((string) ($r['after_json'] ?? ''), true) ?: [];
        $r['executed_before'] = json_decode((string) ($r['executed_before_json'] ?? ''), true) ?: [];
        $r['executed_after'] = json_decode((string) ($r['executed_after_json'] ?? ''), true) ?: [];
        $r['detail'] = (string) ($r['detail'] ?? '');
        $rows[] = $r;
    }
    $stmt->close();
    return $rows;
}

function cw_seo_mexico_ai_get_proposal(mysqli $conn, int $id): ?array
{
    cw_seo_mexico_ai_proposals_ensure_table($conn);
    $stmt = $conn->prepare(
        'SELECT * FROM cw_seo_mexico_ai_proposals WHERE id = ? LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!is_array($row)) {
        return null;
    }
    $row['before'] = json_decode((string) ($row['before_json'] ?? ''), true) ?: [];
    $row['after'] = json_decode((string) ($row['after_json'] ?? ''), true) ?: [];
    $row['executed_before'] = json_decode((string) ($row['executed_before_json'] ?? ''), true) ?: [];
    $row['executed_after'] = json_decode((string) ($row['executed_after_json'] ?? ''), true) ?: [];
    $row['detail'] = (string) ($row['detail'] ?? '');
    return $row;
}

/**
 * Snapshot real del estado en disco antes/después de aplicar.
 *
 * @param array<string,mixed> $after
 * @return array<string,mixed>
 */
function cw_seo_mexico_ai_execution_snapshot(string $kind, array $row, array $after, bool $afterApply = false): array
{
    $root = function_exists('cw_seo_mexico_autofix_site_root') ? cw_seo_mexico_autofix_site_root() : null;
    $urlMeta = cw_seo_mexico_ai_resolve_public_url(
        $kind,
        (string) ($row['target_key'] ?? ''),
        (string) ($row['target_url'] ?? ''),
        $after
    );
    $snap = [
        'kind' => $kind,
        'captured_at' => date('c'),
        'phase' => $afterApply ? 'after_apply' : 'before_apply',
        'target_key' => (string) ($row['target_key'] ?? ''),
        'url' => (string) ($urlMeta['url'] ?? $row['target_url'] ?? ''),
        'url_label' => (string) ($urlMeta['url_label'] ?? 'URL destino'),
        'url_kind' => (string) ($urlMeta['url_kind'] ?? 'page'),
        'target_url' => (string) ($urlMeta['url'] ?? $row['target_url'] ?? ''),
    ];

    if ($kind === 'audit_fix') {
        $snap['finding_id'] = (int) ($after['finding_id'] ?? 0);
        $snap['finding_key'] = (string) ($after['finding_key'] ?? '');
        $snap['check_type'] = (string) ($after['check_type'] ?? '');
        $snap['correction'] = (string) ($after['correction'] ?? '');
        $snap['title'] = (string) ($after['title'] ?? $row['title'] ?? '');
        if ($afterApply && is_array($after['fix_report'] ?? null)) {
            $snap['fix_report'] = $after['fix_report'];
        }
        return $snap;
    }

    if ($kind === 'hub_text') {
        $slug = (string) ($row['target_key'] ?? '');
        $patchRel = 'includes/mexico/ai-hub-patches/' . $slug . '.json';
        $abs = $root ? $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $patchRel) : null;
        $current = ($abs && is_readable($abs)) ? (json_decode((string) file_get_contents($abs), true) ?: []) : [];
        $snap['path'] = $patchRel;
        $snap['fields'] = [
            'title' => (string) ($current['title'] ?? ($afterApply ? ($after['title'] ?? '') : '')),
            'description' => (string) ($current['description'] ?? ''),
            'h1' => (string) ($current['h1'] ?? ''),
            'hero_subtitle' => (string) ($current['hero_subtitle'] ?? ''),
        ];
        if ($afterApply) {
            $snap['fields'] = [
                'title' => (string) ($after['title'] ?? ''),
                'description' => (string) ($after['description'] ?? ''),
                'h1' => (string) ($after['h1'] ?? ''),
                'hero_subtitle' => (string) ($after['hero_subtitle'] ?? ''),
            ];
        }
        return $snap;
    }

    if ($kind === 'blog_post' || $kind === 'blog_improve') {
        $slug = (string) ($after['slug'] ?? $row['target_key'] ?? '');
        $rel = 'includes/blog/content/' . $slug . '.php';
        $abs = $root ? $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel) : null;
        $html = '';
        if ($abs && is_readable($abs)) {
            ob_start();
            include $abs;
            $html = trim((string) ob_get_clean());
        }
        $snap['path'] = $rel;
        $snap['title'] = (string) ($after['title'] ?? '');
        $snap['html_excerpt'] = mb_substr(strip_tags($afterApply ? (string) ($after['html'] ?? $html) : $html), 0, 800);
        $snap['html_length'] = strlen($afterApply ? (string) ($after['html'] ?? $html) : $html);
        return $snap;
    }

    if ($kind === 'site_patch' || $kind === 'admin_patch' || $kind === 'cliente_patch') {
        $path = (string) ($after['path'] ?? $row['target_key'] ?? '');
        $snapRoot = $root;
        $portal = 'site';
        if ($kind === 'admin_patch') {
            $portal = 'admin';
            if (!function_exists('cw_site_ai_admin_root')) {
                require_once __DIR__ . '/cw_site_ai_admin.php';
            }
            $snapRoot = cw_site_ai_admin_root();
        } elseif ($kind === 'cliente_patch') {
            $portal = 'cliente';
            if (!function_exists('cw_site_ai_cliente_root')) {
                require_once __DIR__ . '/cw_site_ai_cliente.php';
            }
            $snapRoot = cw_site_ai_cliente_root();
        }
        $abs = null;
        if ($kind === 'admin_patch' && function_exists('cw_site_ai_admin_resolve_abs')) {
            $abs = cw_site_ai_admin_resolve_abs($path);
        } elseif ($snapRoot) {
            $abs = $snapRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        }
        $exists = $abs && is_file($abs);
        $snap['path'] = $path;
        $snap['portal'] = $portal;
        $snap['view_key'] = (string) ($after['view_key'] ?? '');
        $snap['search'] = (string) ($after['search'] ?? '');
        $snap['replace'] = (string) ($after['replace'] ?? '');
        if ($exists) {
            $src = (string) file_get_contents($abs);
            $needle = $afterApply ? (string) ($after['replace'] ?? '') : (string) ($after['search'] ?? '');
            $snap['fragment_present'] = $needle !== '' && str_contains($src, $needle);
            $snap['file_bytes'] = strlen($src);
        } else {
            $snap['fragment_present'] = false;
            $snap['file_bytes'] = 0;
        }
        return $snap;
    }

    if ($kind === 'design_ui') {
        $patches = is_array($after['patches'] ?? null) ? $after['patches'] : [];
        $snap['reinvention_level'] = (string) ($after['reinvention_level'] ?? '');
        $snap['scope'] = (string) ($after['scope'] ?? '');
        $snap['patches_count'] = count($patches);
        $snap['preview_chars'] = strlen((string) ($after['preview_html'] ?? ''));
        // No usar "summary" aquí: el resumen del Monitor lo prioriza y oculta el estado real de parches
        $snap['design_summary'] = (string) ($after['summary'] ?? '');
        $patchSnaps = [];
        if (!function_exists('cw_site_ai_maintain_resolve_path')) {
            require_once __DIR__ . '/cw_site_ai_maintain.php';
        }
        if (!function_exists('cw_site_ai_design_patch_is_visual')) {
            require_once __DIR__ . '/cw_site_ai_design.php';
        }
        $siteRoot = $root;
        $visualN = 0;
        $metaN = 0;
        foreach ($patches as $i => $p) {
            if (!is_array($p)) {
                continue;
            }
            $pathRaw = (string) ($p['path'] ?? '');
            $resolved = cw_site_ai_maintain_resolve_path($pathRaw) ?? $pathRaw;
            $absP = $siteRoot ? $siteRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $resolved) : null;
            $exists = $absP && is_file($absP);
            $src = ($exists) ? (string) file_get_contents($absP) : '';
            $needle = $afterApply ? (string) ($p['replace'] ?? '') : (string) ($p['search'] ?? '');
            $isVisual = cw_site_ai_design_patch_is_visual($p);
            $isMeta = cw_site_ai_design_patch_is_meta_only($p);
            if ($isVisual) {
                $visualN++;
            }
            if ($isMeta) {
                $metaN++;
            }
            $patchSnaps[] = [
                'index' => (int) $i,
                'path' => $resolved,
                'path_raw' => $pathRaw,
                'kind' => $isVisual ? 'visual' : ($isMeta ? 'meta_seo' : 'other'),
                'file_exists' => $exists,
                'fragment_present' => $needle !== '' && $exists && str_contains($src, $needle),
                'file_bytes' => strlen($src),
            ];
        }
        $snap['patches'] = $patchSnaps;
        $snap['visual_patches'] = $visualN;
        $snap['meta_patches'] = $metaN;
        $snap['all_fragments_ok'] = $patchSnaps !== [] && !in_array(false, array_column($patchSnaps, 'fragment_present'), true);
        $snap['status'] = $afterApply
            ? ('aplicado visual=' . $visualN . ' meta=' . $metaN
                . ($visualN < 1 ? ' · AVISO: sin parches visuales (la UI puede verse igual)' : ''))
            : ('pendiente visual=' . $visualN . ' meta=' . $metaN);
        return $snap;
    }

    $snap['proposal_before'] = is_array($row['before'] ?? null) ? $row['before'] : [];
    $snap['proposal_after'] = $after;
    return $snap;
}

/**
 * Propone mejora de textos SEO de un hub de ciudad (title, meta, h1, hero).
 *
 * @return array{ok:bool,proposal_id?:int,before?:array,after?:array,error?:string}
 */
function cw_seo_mexico_ai_propose_hub_text(mysqli $conn, string $citySlug, int $userId = 0): array
{
    $citySlug = preg_replace('/[^a-z0-9\-]/', '', strtolower($citySlug)) ?? '';
    if ($citySlug === '') {
        return ['ok' => false, 'error' => 'slug de ciudad inválido'];
    }

    $root = cw_seo_mexico_site_root_path();
    $hubFile = $root
        ? $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'mexico' . DIRECTORY_SEPARATOR . 'city-hub-overrides.php'
        : null;
    if ($hubFile && is_file($hubFile)) {
        require_once $hubFile;
    }
    $current = function_exists('mx_city_hub_override') ? (mx_city_hub_override($citySlug) ?? []) : [];
    $cityName = '';
    foreach (cw_seo_mexico_priority_plazas() as $p) {
        if (($p['slug'] ?? '') === $citySlug) {
            $cityName = (string) ($p['city'] ?? $citySlug);
            break;
        }
    }
    if ($cityName === '') {
        $cityName = ucwords(str_replace('-', ' ', $citySlug));
    }

    $before = [
        'title' => (string) ($current['title'] ?? ''),
        'description' => (string) ($current['description'] ?? ''),
        'h1' => (string) ($current['h1'] ?? ''),
        'hero_subtitle' => (string) ($current['hero_subtitle'] ?? ''),
    ];

    require_once __DIR__ . '/cw_seo_mexico_ai_constitution.php';
    $system = 'Eres MASTER SEO/GEO y copywriter local de ConlineWeb (agencia digital México). '
        . cw_seo_mexico_ai_master_brief() . ' '
        . 'Responde SOLO JSON válido con keys: title, description, h1, hero_subtitle, rationale. '
        . 'title 50–65 chars, description 140–160 chars, h1 claro servicio+ciudad, hero_subtitle 1–2 frases. '
        . 'Aplica mejores prácticas Google (intención local, claridad, E-E-A-T). '
        . 'Español México, tono profesional comercial, sin inventar casos falsos ni precios. '
        . 'No uses emojis. Marca: ConlineWeb. En rationale explica el impacto SEO/GEO.';

    $user = "Mejora SEO del hub de ciudad.\nCiudad: {$cityName}\nSlug: {$citySlug}\nURL: https://conlineweb.com/mexico/ciudades/{$citySlug}/\n"
        . "Textos actuales:\n" . json_encode($before, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    $chat = cw_seo_mexico_ai_chat([
        ['role' => 'system', 'content' => $system],
        ['role' => 'user', 'content' => $user],
    ], 0.45);
    if (empty($chat['ok'])) {
        return ['ok' => false, 'error' => (string) ($chat['error'] ?? 'IA falló')];
    }
    $after = json_decode((string) $chat['content'], true);
    if (!is_array($after) || empty($after['title']) || empty($after['h1'])) {
        return ['ok' => false, 'error' => 'JSON de IA incompleto', 'raw' => $chat['content'] ?? ''];
    }
    $after = [
        'title' => trim((string) ($after['title'] ?? '')),
        'description' => trim((string) ($after['description'] ?? '')),
        'h1' => trim((string) ($after['h1'] ?? '')),
        'hero_subtitle' => trim((string) ($after['hero_subtitle'] ?? '')),
        'rationale' => trim((string) ($after['rationale'] ?? '')),
    ];
    if (mb_strlen($after['rationale']) < 20) {
        $after['rationale'] = 'Actualizar title, meta, H1 y hero del hub de ' . $cityName
            . ' para mejorar relevancia local SEO/GEO de los servicios ConlineWeb.';
    }

    $saved = cw_seo_mexico_ai_save_proposal(
        $conn,
        'hub_text',
        'Hub SEO · ' . $cityName,
        $citySlug,
        'https://conlineweb.com/mexico/ciudades/' . $citySlug . '/',
        'Mejora title/meta/h1/hero · ' . $cityName,
        $before,
        $after,
        $userId
    );
    if (empty($saved['ok'])) {
        return $saved;
    }
    return [
        'ok' => true,
        'proposal_id' => (int) $saved['id'],
        'before' => $before,
        'after' => $after,
        'url' => 'https://conlineweb.com/mexico/ciudades/' . $citySlug . '/',
    ];
}

/**
 * Propone landing comercial indexable en URL corta servicio×plaza (estrategia por fases).
 *
 * @param array<string, mixed> $target
 * @return array{ok:bool,proposal_id?:int,url?:string,target_key?:string,error?:string}
 */
function cw_seo_mexico_ai_propose_short_url_commercial(mysqli $conn, array $target, int $userId = 0): array
{
    require_once __DIR__ . '/cw_seo_mexico_short_urls_strategy.php';

    $targetKey = (string) ($target['target_key'] ?? '');
    $plazaSlug = (string) ($target['plaza_slug'] ?? '');
    $plazaLabel = (string) ($target['plaza_label'] ?? $plazaSlug);
    $serviceSlug = (string) ($target['service_slug'] ?? '');
    $geoKind = (string) ($target['geo_kind'] ?? 'ciudad');
    $isHub = !empty($target['is_hub']);
    $shortUrl = (string) ($target['short_url'] ?? '');
    $longUrl = (string) ($target['long_url'] ?? '');
    $phaseKey = (string) ($target['phase_key'] ?? '');

    if ($targetKey === '' || $plazaSlug === '' || $shortUrl === '') {
        return ['ok' => false, 'error' => 'Target URL corta incompleto'];
    }
    if (!$isHub && $serviceSlug === '') {
        return ['ok' => false, 'error' => 'Servicio requerido'];
    }

    $root = cw_seo_mexico_site_root_path() ?? cw_seo_mexico_autofix_site_root();
    $profile = [];
    if (!$isHub && $root) {
        $profilesFile = $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'mexico' . DIRECTORY_SEPARATOR . 'service-profiles.php';
        if (is_readable($profilesFile)) {
            $profiles = require $profilesFile;
            $profile = is_array($profiles[$serviceSlug] ?? null) ? $profiles[$serviceSlug] : [];
        }
    }
    $svcName = (string) ($profile['name'] ?? ($isHub ? 'Servicios digitales' : $serviceSlug));
    $keywords = implode(', ', $profile['primary_keywords'] ?? []);

    $before = [
        'mode' => '301_redirect',
        'short_url' => $shortUrl,
        'long_url' => $longUrl,
        'indexable' => false,
        'in_sitemap' => false,
    ];

    require_once __DIR__ . '/cw_seo_mexico_ai_constitution.php';
    $system = 'Eres MASTER SEO/GEO y copywriter comercial de ConlineWeb (México). '
        . cw_seo_mexico_ai_master_brief() . "\n"
        . cw_seo_mexico_short_urls_ai_brief() . "\n"
        . 'Responde SOLO JSON válido con keys: '
        . 'title, description, h1, hero_subtitle, faq (array de {q,a}), cross_link_label, rationale, '
        . 'primary_keyword, schema_area_served. '
        . 'title 48–60 chars con keyword+plaza; description 140–158; h1 comercial directo («{servicio} en {plaza}»); '
        . 'hero_subtitle 1–2 frases orientadas a cotizar; faq 3–4 preguntas locales; '
        . 'cross_link_label texto para enlace al hub largo; rationale ≥40 chars SEO/GEO. '
        . 'Español México, sin inventar casos ni precios.';

    $user = "Fase checklist: {$phaseKey}\n"
        . "Plaza: {$plazaLabel} ({$plazaSlug}, {$geoKind})\n"
        . "Servicio: {$svcName} ({$serviceSlug})\n"
        . "Keywords: {$keywords}\n"
        . "URL corta (indexar): {$shortUrl}\n"
        . "URL larga hub (enlace cruzado): {$longUrl}\n"
        . "Estado actual: 301 → larga. Objetivo: landing comercial única indexable.\n";

    $chat = cw_seo_mexico_ai_chat([
        ['role' => 'system', 'content' => $system],
        ['role' => 'user', 'content' => $user],
    ], 0.42);
    if (empty($chat['ok'])) {
        return ['ok' => false, 'error' => (string) ($chat['error'] ?? 'IA falló')];
    }
    $after = json_decode((string) ($chat['content'] ?? ''), true);
    if (!is_array($after) || empty($after['title']) || empty($after['h1'])) {
        return ['ok' => false, 'error' => 'JSON IA incompleto', 'raw' => $chat['content'] ?? ''];
    }

    $after = array_merge($before, [
        'mode' => 'commercial_short',
        'indexable' => true,
        'in_sitemap' => true,
        'canonical' => $shortUrl,
        'long_url' => $longUrl,
        'phase_key' => $phaseKey,
        'plaza_slug' => $plazaSlug,
        'plaza_label' => $plazaLabel,
        'geo_kind' => $geoKind,
        'service_slug' => $serviceSlug,
        'is_hub' => $isHub,
        'title' => trim((string) ($after['title'] ?? '')),
        'description' => trim((string) ($after['description'] ?? '')),
        'h1' => trim((string) ($after['h1'] ?? '')),
        'hero_subtitle' => trim((string) ($after['hero_subtitle'] ?? '')),
        'faq' => is_array($after['faq'] ?? null) ? $after['faq'] : [],
        'cross_link_label' => trim((string) ($after['cross_link_label'] ?? 'Ver hub completo de la plaza')),
        'primary_keyword' => trim((string) ($after['primary_keyword'] ?? '')),
        'schema_area_served' => trim((string) ($after['schema_area_served'] ?? $plazaLabel)),
        'rationale' => trim((string) ($after['rationale'] ?? '')),
        'technical_steps' => [
            'page-factory: modo commercial_short (quitar 301)',
            'includes/mexico/short-url-commercial-overrides.php',
            'sync-sitemap-mexico.php: include_short_service_urls fase activa',
            'site-index-data.php: sección landings comerciales',
            'cannibalization.php: cortas indexables con contenido único',
        ],
    ]);
    if (mb_strlen($after['rationale']) < 20) {
        $after['rationale'] = 'Landing comercial corta para «' . $svcName . ' en ' . $plazaLabel
            . '» — keyword local alto volumen, sin duplicar el hub largo.';
    }

    $title = ($isHub ? 'Hub corto' : $svcName) . ' · ' . $plazaLabel . ' (URL corta)';
    $saved = cw_seo_mexico_ai_save_proposal(
        $conn,
        'short_url_commercial',
        $title,
        $targetKey,
        $shortUrl,
        'URL corta comercial · ' . $plazaLabel . ' · ' . ($isHub ? 'hub' : $serviceSlug) . ' · ' . $phaseKey,
        $before,
        $after,
        $userId
    );
    if (empty($saved['ok'])) {
        return $saved;
    }
    return [
        'ok' => true,
        'proposal_id' => (int) $saved['id'],
        'url' => $shortUrl,
        'target_key' => $targetKey,
        'kind' => 'short_url_commercial',
    ];
}

/**
 * Categorías reales del blog en conlineweb.com (única fuente permitida).
 *
 * @return array<string,array{slug:string,name:string,description?:string}>
 */
function cw_seo_mexico_ai_blog_categories(): array
{
    static $cats = null;
    if (is_array($cats)) {
        return $cats;
    }
    $cats = [];
    $root = cw_seo_mexico_site_root_path() ?? cw_seo_mexico_autofix_site_root();
    if ($root === null) {
        return $cats;
    }
    $file = $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'blog' . DIRECTORY_SEPARATOR . 'categories.php';
    if (!is_readable($file)) {
        return $cats;
    }
    $loaded = include $file;
    if (!is_array($loaded)) {
        return $cats;
    }
    foreach ($loaded as $slug => $meta) {
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) $slug)) ?? '';
        if ($slug === '' || !is_array($meta)) {
            continue;
        }
        $cats[$slug] = [
            'slug' => $slug,
            'name' => (string) ($meta['name'] ?? $slug),
            'description' => (string) ($meta['description'] ?? ''),
        ];
    }
    return $cats;
}

function cw_seo_mexico_ai_blog_normalize_category(string $category): string
{
    $cats = cw_seo_mexico_ai_blog_categories();
    $category = preg_replace('/[^a-z0-9\-]/', '', strtolower($category)) ?? '';
    if ($category !== '' && isset($cats[$category])) {
        return $category;
    }
    // aliases UI antiguas → catálogo real
    $aliases = [
        'ia' => 'inteligencia-artificial',
        'desarrollo' => 'desarrollo-web',
        'marketing' => 'marketing-digital',
        'tienda' => 'ecommerce',
    ];
    if (isset($aliases[$category]) && isset($cats[$aliases[$category]])) {
        return $aliases[$category];
    }
    return isset($cats['seo']) ? 'seo' : (array_key_first($cats) ?: 'seo');
}

/**
 * Posts existentes del blog (para mejorar contenido).
 *
 * @return list<array{slug:string,title:string,category:string,excerpt:string,url:string}>
 */
function cw_seo_mexico_ai_blog_existing_posts(int $limit = 40): array
{
    $root = cw_seo_mexico_site_root_path() ?? cw_seo_mexico_autofix_site_root();
    if ($root === null) {
        return [];
    }
    $factory = $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'blog' . DIRECTORY_SEPARATOR . 'page-factory.php';
    if (!is_readable($factory)) {
        return [];
    }
    require_once $factory;
    if (!function_exists('blog_posts')) {
        return [];
    }
    $out = [];
    foreach (blog_posts() as $p) {
        $slug = (string) ($p['slug'] ?? '');
        if ($slug === '') {
            continue;
        }
        $out[] = [
            'slug' => $slug,
            'title' => (string) ($p['title'] ?? $slug),
            'category' => cw_seo_mexico_ai_blog_normalize_category((string) ($p['category'] ?? 'seo')),
            'excerpt' => (string) ($p['excerpt'] ?? ''),
            'url' => 'https://conlineweb.com/blog/articulo/' . $slug . '/',
        ];
        if (count($out) >= $limit) {
            break;
        }
    }
    return $out;
}

function cw_seo_mexico_ai_blog_load_content_html(string $slug): string
{
    $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug)) ?? '';
    if ($slug === '') {
        return '';
    }
    $root = cw_seo_mexico_site_root_path() ?? cw_seo_mexico_autofix_site_root();
    if ($root === null) {
        return '';
    }
    $file = $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'blog'
        . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . $slug . '.php';
    if (!is_readable($file)) {
        return '';
    }
    ob_start();
    include $file;
    return trim((string) ob_get_clean());
}

/**
 * Propone un artículo de blog nuevo (HTML + meta) en categorías ya existentes.
 *
 * @return array{ok:bool,proposal_id?:int,after?:array,error?:string}
 */
function cw_seo_mexico_ai_propose_blog(mysqli $conn, string $topic, string $category = 'seo', int $userId = 0): array
{
    $topic = trim($topic);
    $autoTopic = ($topic === '' || mb_strlen($topic) < 8);
    $cats = cw_seo_mexico_ai_blog_categories();
    if ($cats === []) {
        return ['ok' => false, 'error' => 'No se encontraron categorías del blog en el sitio'];
    }
    $category = cw_seo_mexico_ai_blog_normalize_category($category);
    $catName = $cats[$category]['name'] ?? $category;
    $catDesc = $cats[$category]['description'] ?? '';
    $allowed = implode(', ', array_keys($cats));

    $existingInCat = [];
    foreach (cw_seo_mexico_ai_blog_existing_posts(80) as $p) {
        if (($p['category'] ?? '') === $category) {
            $existingInCat[] = (string) ($p['title'] ?? '');
        }
    }
    $existingList = $existingInCat !== []
        ? implode("\n- ", array_slice($existingInCat, 0, 24))
        : '(ninguno registrado en esta categoría)';

    require_once __DIR__ . '/cw_seo_mexico_ai_constitution.php';
    $system = 'Eres MASTER SEO/GEO y redactor editorial senior del blog ConlineWeb (México). '
        . cw_seo_mexico_ai_master_brief() . ' '
        . cw_seo_mexico_ai_blog_editorial_brief() . ' '
        . cw_seo_mexico_ai_seo_geo_playbook() . ' '
        . 'Responde SOLO JSON con keys: slug, title, excerpt, category, keyword, html, rationale, placement_kind'
        . ($autoTopic ? ', topic' : '') . '. '
        . 'placement_kind debe ser siempre "blog_post". '
        . 'category DEBE ser exactamente una de: ' . $allowed . '. '
        . 'Colocación fija: URL https://conlineweb.com/blog/articulo/{slug}/ · se indexa en https://conlineweb.com/indice/ (sección Blog). '
        . 'slug: kebab-case sin acentos. html: fragmento HTML con h2/h3/p/ul/li (sin html/body), 700–1200 palabras útiles. '
        . 'Incluye sección FAQ con h3+p. Enlaza internamente con rutas relativas tipo mexico/ o blog/categoria/ cuando aporte. '
        . 'Español México, E-E-A-T, sin inventar casos ni estadísticas falsas. Sin emojis. '
        . 'rationale: explica valor para el lector Y estrategia SEO/GEO (intención, keyword, cluster). '
        . 'El artículo debe fortalecer el cluster de la categoría elegida y ser digno de lectura completa.';

    if ($autoTopic) {
        $user = "Escribe un artículo NUEVO para el blog.\n"
            . "Elige tú un tema/título original con alto potencial SEO/GEO para la categoría obligatoria: {$category} ({$catName}).\n"
            . "Descripción categoría: {$catDesc}\n"
            . "Artículos ya publicados en esta categoría (NO repetir ni canibalizar):\n- {$existingList}\n"
            . "Incluye en el JSON el campo topic con la frase del tema elegido (8+ caracteres).\n"
            . "Audiencia: dueños de PyME y gerentes en México.\n"
            . "Prioridad: contenido que el lector quiera terminar (valor práctico) + SEO para tráfico orgánico.\n"
            . "CTA suave a cotizar con ConlineWeb por WhatsApp solo al final, sin spam.\n"
            . "No uses otra categoría fuera de la lista permitida.\n"
            . "Al publicar se actualizará automáticamente el directorio /indice/ y sitemap-blog.xml.";
    } else {
        $user = "Escribe un artículo NUEVO para el blog.\nTema: {$topic}\n"
            . "Categoría obligatoria: {$category} ({$catName})\nDescripción categoría: {$catDesc}\n"
            . "Artículos ya publicados en esta categoría (evitar duplicar):\n- {$existingList}\n"
            . "Audiencia: dueños de PyME y gerentes en México.\n"
            . "Prioridad: contenido que el lector quiera terminar (valor práctico) + SEO para tráfico orgánico.\n"
            . "CTA suave a cotizar con ConlineWeb por WhatsApp solo al final, sin spam.\n"
            . "No uses otra categoría fuera de la lista permitida.\n"
            . "Al publicar se actualizará automáticamente el directorio /indice/ y sitemap-blog.xml.";
    }

    $chat = cw_seo_mexico_ai_chat([
        ['role' => 'system', 'content' => $system],
        ['role' => 'user', 'content' => $user],
    ], 0.55);
    if (empty($chat['ok'])) {
        return ['ok' => false, 'error' => (string) ($chat['error'] ?? 'IA falló')];
    }
    $after = json_decode((string) $chat['content'], true);
    if (!is_array($after) || empty($after['slug']) || empty($after['title']) || empty($after['html'])) {
        return ['ok' => false, 'error' => 'JSON de blog incompleto'];
    }
    $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) $after['slug'])) ?? '';
    if ($slug === '' || strlen($slug) < 6) {
        return ['ok' => false, 'error' => 'slug inválido generado por IA'];
    }
    $resolvedTopic = $autoTopic
        ? trim((string) ($after['topic'] ?? $after['title'] ?? ''))
        : $topic;
    if (mb_strlen($resolvedTopic) < 8) {
        $resolvedTopic = trim((string) ($after['title'] ?? ''));
    }
    $after = [
        'slug' => $slug,
        'title' => trim((string) $after['title']),
        'excerpt' => trim((string) ($after['excerpt'] ?? '')),
        'category' => $category,
        'keyword' => trim((string) ($after['keyword'] ?? $resolvedTopic)),
        'html' => trim((string) $after['html']),
        'rationale' => trim((string) ($after['rationale'] ?? '')),
        'topic' => $resolvedTopic,
        'date' => date('Y-m-d'),
        'read_minutes' => max(6, min(18, (int) round(str_word_count(strip_tags((string) $after['html'])) / 180))),
        'mode' => 'new',
    ];
    if (mb_strlen($after['rationale']) < 20) {
        $after['rationale'] = 'Artículo nuevo en categoría «' . $catName . '» sobre «' . mb_substr($resolvedTopic, 0, 120)
            . '» para reforzar cluster SEO del blog ConlineWeb.';
    }

    $saved = cw_seo_mexico_ai_save_proposal(
        $conn,
        'blog_post',
        'Blog nuevo · ' . $catName . ' · ' . $after['title'],
        $slug,
        'https://conlineweb.com/blog/articulo/' . $slug . '/',
        'Nuevo en categoría ' . $category . ': ' . mb_substr($resolvedTopic, 0, 160),
        ['topic' => $resolvedTopic, 'category' => $category, 'auto_topic' => $autoTopic],
        $after,
        $userId
    );
    if (empty($saved['ok'])) {
        return $saved;
    }
    return [
        'ok' => true,
        'proposal_id' => (int) $saved['id'],
        'after' => $after,
        'url' => 'https://conlineweb.com/blog/articulo/' . $slug . '/',
        'content_type' => 'blog',
        'mode' => 'new',
    ];
}

/**
 * Propone mejora de un artículo de blog ya existente (misma categoría / slug).
 *
 * @return array{ok:bool,proposal_id?:int,before?:array,after?:array,error?:string}
 */
function cw_seo_mexico_ai_propose_blog_improve(mysqli $conn, string $slug, int $userId = 0): array
{
    $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug)) ?? '';
    if ($slug === '') {
        return ['ok' => false, 'error' => 'slug inválido'];
    }
    $posts = cw_seo_mexico_ai_blog_existing_posts(80);
    $meta = null;
    foreach ($posts as $p) {
        if (($p['slug'] ?? '') === $slug) {
            $meta = $p;
            break;
        }
    }
    if ($meta === null) {
        return ['ok' => false, 'error' => 'Artículo no encontrado en el catálogo del blog'];
    }
    $category = cw_seo_mexico_ai_blog_normalize_category((string) ($meta['category'] ?? 'seo'));
    $cats = cw_seo_mexico_ai_blog_categories();
    $catName = $cats[$category]['name'] ?? $category;
    $currentHtml = cw_seo_mexico_ai_blog_load_content_html($slug);
    if ($currentHtml === '') {
        $currentHtml = '<p>' . htmlspecialchars((string) ($meta['excerpt'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>';
    }

    $before = [
        'slug' => $slug,
        'title' => (string) ($meta['title'] ?? ''),
        'excerpt' => (string) ($meta['excerpt'] ?? ''),
        'category' => $category,
        'html' => $currentHtml,
    ];

    require_once __DIR__ . '/cw_seo_mexico_ai_constitution.php';
    $system = 'Eres MASTER SEO/GEO y editor senior del blog ConlineWeb. '
        . cw_seo_mexico_ai_master_brief() . ' '
        . cw_seo_mexico_ai_blog_editorial_brief() . ' '
        . 'Mejora el artículo EXISTENTE sin cambiar de categoría ni inventar otra. '
        . 'Responde SOLO JSON con keys: title, excerpt, keyword, html, rationale. '
        . 'Mantén el mismo slug implícito (no lo cambies). category fija: ' . $category . '. '
        . 'html: fragmento mejorado (h2/h3/p/ul/li), más útil para el lector, actualizado a 2026, E-E-A-T, sin datos falsos. Sin emojis. '
        . 'En rationale indica qué valor aporta al lector y qué mejora SEO/GEO.';

    $user = "Mejora este artículo de la categoría {$category} ({$catName}).\n"
        . "Slug (NO cambiar): {$slug}\nURL: https://conlineweb.com/blog/articulo/{$slug}/\n"
        . "Título actual: {$before['title']}\nExcerpt actual: {$before['excerpt']}\n"
        . "HTML actual:\n" . mb_substr($currentHtml, 0, 6000);

    $chat = cw_seo_mexico_ai_chat([
        ['role' => 'system', 'content' => $system],
        ['role' => 'user', 'content' => $user],
    ], 0.4);
    if (empty($chat['ok'])) {
        return ['ok' => false, 'error' => (string) ($chat['error'] ?? 'IA falló')];
    }
    $parsed = json_decode((string) $chat['content'], true);
    if (!is_array($parsed) || empty($parsed['title']) || empty($parsed['html'])) {
        return ['ok' => false, 'error' => 'JSON de mejora incompleto'];
    }
    $after = [
        'slug' => $slug,
        'title' => trim((string) $parsed['title']),
        'excerpt' => trim((string) ($parsed['excerpt'] ?? $before['excerpt'])),
        'category' => $category,
        'keyword' => trim((string) ($parsed['keyword'] ?? $before['title'])),
        'html' => trim((string) $parsed['html']),
        'rationale' => trim((string) ($parsed['rationale'] ?? '')),
        'date' => date('Y-m-d'),
        'read_minutes' => max(6, min(18, (int) round(str_word_count(strip_tags((string) $parsed['html'])) / 180))),
        'mode' => 'improve',
    ];
    if (mb_strlen($after['rationale']) < 20) {
        $after['rationale'] = 'Mejora continua del artículo «' . mb_substr((string) $before['title'], 0, 80)
            . '» (categoría ' . $catName . ') para calidad, claridad y SEO del blog ConlineWeb.';
    }

    $saved = cw_seo_mexico_ai_save_proposal(
        $conn,
        'blog_improve',
        'Mejora blog · ' . $catName . ' · ' . $after['title'],
        $slug,
        'https://conlineweb.com/blog/articulo/' . $slug . '/',
        'Mejora continua categoría ' . $category,
        $before,
        $after,
        $userId
    );
    if (empty($saved['ok'])) {
        return $saved;
    }
    return [
        'ok' => true,
        'proposal_id' => (int) $saved['id'],
        'before' => [
            'title' => $before['title'],
            'excerpt' => $before['excerpt'],
            'category' => $category,
            'html_preview' => mb_substr(strip_tags($currentHtml), 0, 400),
        ],
        'after' => $after,
        'url' => 'https://conlineweb.com/blog/articulo/' . $slug . '/',
        'content_type' => 'blog',
        'mode' => 'improve',
    ];
}

/**
 * Valida actualizaciones ya aplicadas (fragmento en archivo, lint) y deja informe en cola + chat.
 *
 * @return array{ok:bool,proposal_id?:int,checks?:list<array>,ok_count?:int,fail_count?:int,message?:string,error?:string,report?:string}
 */
function cw_seo_mexico_ai_validate_recent_updates(
    mysqli $conn,
    string $scope = 'admin',
    int $limit = 8,
    int $userId = 0
): array {
    cw_seo_mexico_ai_proposals_ensure_table($conn);
    $scope = preg_replace('/[^a-z_]/', '', strtolower($scope)) ?: 'admin';
    $limit = max(1, min(20, $limit));

    $kinds = match ($scope) {
        'site' => ['site_patch', 'design_ui', 'hub_text', 'short_url_commercial', 'blog_post', 'blog_improve'],
        'cliente' => ['cliente_patch'],
        'all' => ['admin_patch', 'cliente_patch', 'site_patch', 'design_ui', 'hub_text', 'short_url_commercial', 'blog_post', 'blog_improve'],
        default => ['admin_patch'],
    };
    $in = "'" . implode("','", array_map(static fn ($k) => $conn->real_escape_string($k), $kinds)) . "'";
    $sql = "SELECT id, kind, title, status, target_key, target_url, after_json, before_json, applied_at, detail
            FROM cw_seo_mexico_ai_proposals
            WHERE status = 'applied' AND kind IN ({$in})
            ORDER BY applied_at DESC, id DESC
            LIMIT {$limit}";
    $res = $conn->query($sql);
    $checks = [];
    $okCount = 0;
    $failCount = 0;

    while ($res && ($row = $res->fetch_assoc())) {
        $kind = (string) ($row['kind'] ?? '');
        $after = json_decode((string) ($row['after_json'] ?? '{}'), true);
        if (!is_array($after)) {
            $after = [];
        }
        $row['after'] = $after;
        $snap = cw_seo_mexico_ai_execution_snapshot($kind, $row, $after, true);
        $ok = true;
        $notes = [];

        if (in_array($kind, ['admin_patch', 'cliente_patch', 'site_patch'], true)) {
            $present = !empty($snap['fragment_present']);
            $ok = $present;
            $notes[] = $present
                ? 'Fragmento replace presente en archivo'
                : 'Fragmento replace NO encontrado (posible drift o rollback)';
            $path = (string) ($snap['path'] ?? $after['path'] ?? '');
            if ($ok && $path !== '' && preg_match('/\.php$/i', $path) && function_exists('cw_seo_mexico_autofix_php_lint')) {
                $abs = null;
                if ($kind === 'admin_patch' && function_exists('cw_site_ai_admin_resolve_abs')) {
                    require_once __DIR__ . '/cw_site_ai_admin.php';
                    $abs = cw_site_ai_admin_resolve_abs($path);
                } elseif ($kind === 'cliente_patch' && function_exists('cw_site_ai_cliente_root')) {
                    require_once __DIR__ . '/cw_site_ai_cliente.php';
                    $root = cw_site_ai_cliente_root();
                    $abs = $root ? $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path) : null;
                } else {
                    $root = cw_seo_mexico_autofix_site_root();
                    $abs = $root ? $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path) : null;
                }
                if ($abs && is_file($abs)) {
                    $lint = cw_seo_mexico_autofix_php_lint($abs);
                    if (empty($lint['ok'])) {
                        $ok = false;
                        $notes[] = 'Lint PHP falló';
                    } else {
                        $notes[] = 'Lint PHP OK';
                    }
                }
            }
        } elseif ($kind === 'design_ui') {
            $ok = ((int) ($snap['patches_count'] ?? 0) >= 0) && ((int) ($snap['preview_chars'] ?? 0) > 0);
            $notes[] = $ok ? 'Diseño con preview registrado' : 'Diseño incompleto en snapshot';
        } else {
            $ok = ((int) ($snap['html_length'] ?? 0) > 0) || !empty($snap['fields']);
            $notes[] = $ok ? 'Contenido presente tras apply' : 'No se pudo verificar contenido';
        }

        if ($ok) {
            $okCount++;
        } else {
            $failCount++;
        }
        $checks[] = [
            'proposal_id' => (int) ($row['id'] ?? 0),
            'kind' => $kind,
            'title' => (string) ($row['title'] ?? ''),
            'path' => (string) ($snap['path'] ?? $after['path'] ?? ''),
            'url' => (string) ($snap['url'] ?? $row['target_url'] ?? ''),
            'ok' => $ok,
            'notes' => $notes,
            'applied_at' => (string) ($row['applied_at'] ?? ''),
            'fragment_present' => !empty($snap['fragment_present']),
        ];
    }

    if ($checks === []) {
        return [
            'ok' => false,
            'error' => 'No hay actualizaciones aplicadas recientes en el alcance «' . $scope . '» para validar.',
            'checks' => [],
            'ok_count' => 0,
            'fail_count' => 0,
        ];
    }

    $lines = [
        'VALIDACIÓN DE ACTUALIZACIONES (' . strtoupper($scope) . ')',
        'Revisadas: ' . count($checks) . ' · OK: ' . $okCount . ' · Fallidas: ' . $failCount,
        '',
    ];
    foreach ($checks as $c) {
        $lines[] = ($c['ok'] ? '[OK]' : '[FALLA]')
            . ' #' . $c['proposal_id'] . ' · ' . $c['title']
            . ($c['path'] !== '' ? ' · ' . $c['path'] : '')
            . ' — ' . implode('; ', $c['notes']);
    }
    $report = implode("\n", $lines);

    $afterNorm = [
        'portal' => $scope === 'cliente' ? 'cliente' : ($scope === 'site' ? 'site' : 'admin'),
        'mode' => 'admin_validate',
        'change_type' => 'validacion',
        'scope' => $scope,
        'checks' => $checks,
        'ok_count' => $okCount,
        'fail_count' => $failCount,
        'summary' => 'Validación ' . $scope . ': ' . $okCount . ' OK / ' . $failCount . ' fallas',
        'rationale' => 'Informe de funcionamiento tras implementar. Revisar en Monitor; aprobar = acusar recibo (no reescribe código).',
        'url' => 'https://adm.conlineweb.com/analytics/seo_mexico_monitor.php',
        'url_label' => 'Cola de propuestas (informe)',
        'acknowledge_only' => true,
    ];
    $before = [
        'scope' => $scope,
        'checked' => count($checks),
        'note' => 'Estado previo a la validación en chat',
    ];

    $kindSave = $scope === 'admin' || $scope === 'all' ? 'admin_validate' : 'admin_validate';
    $saved = cw_seo_mexico_ai_save_proposal(
        $conn,
        $kindSave,
        'Validación · ' . $scope . ' · ' . $okCount . ' OK / ' . $failCount . ' fallas',
        'validate/' . $scope,
        (string) $afterNorm['url'],
        mb_substr($report, 0, 500),
        $before,
        $afterNorm,
        $userId
    );
    if (empty($saved['ok'])) {
        return [
            'ok' => true,
            'checks' => $checks,
            'ok_count' => $okCount,
            'fail_count' => $failCount,
            'report' => $report,
            'message' => 'Validación lista (sin poder guardar en cola). ' . ($saved['error'] ?? ''),
        ];
    }
    $pid = (int) ($saved['id'] ?? 0);
    if ($pid > 0) {
        $stmt = $conn->prepare('UPDATE cw_seo_mexico_ai_proposals SET detail = CONCAT(IFNULL(detail,\'\'), ?) WHERE id = ?');
        if ($stmt) {
            $extra = "\n\n" . $report;
            $stmt->bind_param('si', $extra, $pid);
            $stmt->execute();
            $stmt->close();
        }
    }

    return [
        'ok' => true,
        'proposal_id' => $pid,
        'checks' => $checks,
        'ok_count' => $okCount,
        'fail_count' => $failCount,
        'report' => $report,
        'message' => 'Informe de validación #' . $pid . ' en la cola. OK=' . $okCount . ' · Fallas=' . $failCount
            . '. Revisa Detalle en Monitor; aprobar solo acusa recibo.',
    ];
}

/**
 * Aplica una propuesta aprobada al código del sitio (mismo servidor).
 *
 * @return array{ok:bool,applied:bool,files?:list<string>,error?:string,before?:array,after?:array,url?:string}
 */
function cw_seo_mexico_ai_apply_proposal(mysqli $conn, int $proposalId, int $userId = 0): array
{
    $row = cw_seo_mexico_ai_get_proposal($conn, $proposalId);
    if ($row === null) {
        return ['ok' => false, 'applied' => false, 'error' => 'Propuesta no encontrada'];
    }
    $st = (string) ($row['status'] ?? '');
    if ($st !== 'pending' && $st !== 'working') {
        return ['ok' => false, 'applied' => false, 'error' => 'La propuesta no está pendiente (status=' . $st . ')'];
    }
    $kind = (string) ($row['kind'] ?? '');
    $after = is_array($row['after'] ?? null) ? $row['after'] : [];
    $before = is_array($row['before'] ?? null) ? $row['before'] : [];
    $files = [];

    // Snapshot real ANTES de ejecutar
    $executedBefore = cw_seo_mexico_ai_execution_snapshot($kind, $row, $after, false);

    // Informe de validación: solo acusar recibo (sin tocar archivos)
    if ($kind === 'admin_validate') {
        $url = (string) ($after['url'] ?? $row['target_url'] ?? 'https://adm.conlineweb.com/analytics/seo_mexico_monitor.php');
        $executedAfter = array_merge($executedBefore, [
            'acknowledged' => true,
            'ok_count' => (int) ($after['ok_count'] ?? 0),
            'fail_count' => (int) ($after['fail_count'] ?? 0),
            'phase' => 'after_apply',
        ]);
        $detail = trim((string) ($row['detail'] ?? ''));
        $detail .= "\n\nACUSE DE VALIDACIÓN " . date('Y-m-d H:i:s')
            . "\nOK: " . (int) ($after['ok_count'] ?? 0)
            . ' · Fallas: ' . (int) ($after['fail_count'] ?? 0);
        $log = 'Validación acusada · sin cambios de archivos';
        $after['url'] = $url;
        $afterJ = json_encode($after, JSON_UNESCAPED_UNICODE);
        $execBeforeJ = json_encode($executedBefore, JSON_UNESCAPED_UNICODE);
        $execAfterJ = json_encode($executedAfter, JSON_UNESCAPED_UNICODE);
        $uid = max(0, $userId);
        $stmt = $conn->prepare(
            'UPDATE cw_seo_mexico_ai_proposals
             SET status = \'applied\', target_url = ?, apply_log = ?, detail = ?, after_json = ?,
                 executed_before_json = ?, executed_after_json = ?,
                 applied_by = ?, applied_at = NOW()
             WHERE id = ?'
        );
        if ($stmt) {
            $stmt->bind_param(
                'ssssssii',
                $url,
                $log,
                $detail,
                $afterJ,
                $execBeforeJ,
                $execAfterJ,
                $uid,
                $proposalId
            );
            $stmt->execute();
            $stmt->close();
        }
        return [
            'ok' => true,
            'applied' => true,
            'files' => [],
            'before' => $before,
            'after' => $after,
            'executed_before' => $executedBefore,
            'executed_after' => $executedAfter,
            'url' => $url,
            'url_label' => 'Informe de validación',
            'message' => 'Validación marcada como revisada (sin cambios de código).',
        ];
    }

    if ($kind === 'hub_text') {
        $slug = (string) ($row['target_key'] ?? '');
        $patch = [
            'title' => (string) ($after['title'] ?? ''),
            'description' => (string) ($after['description'] ?? ''),
            'h1' => (string) ($after['h1'] ?? ''),
            'hero_subtitle' => (string) ($after['hero_subtitle'] ?? ''),
            'ai_applied_at' => date('c'),
        ];
        if ($patch['title'] === '' || $patch['h1'] === '') {
            return ['ok' => false, 'applied' => false, 'error' => 'Propuesta hub incompleta'];
        }
        $rel = 'includes/mexico/ai-hub-patches/' . $slug . '.json';
        $write = cw_seo_mexico_autofix_write(
            $rel,
            json_encode($patch, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n",
            'IA hub_text apply #' . $proposalId
        );
        if (empty($write['ok']) || empty($write['applied'])) {
            return ['ok' => false, 'applied' => false, 'error' => (string) ($write['error'] ?? 'No se pudo escribir patch')];
        }
        $files[] = $rel;
        cw_seo_mexico_autofix_log($conn, 'ai_hub_text', $rel, true, 'proposal #' . $proposalId, $write['backup'] ?? null, $userId, null);
    } elseif ($kind === 'short_url_commercial') {
        $plazaSlug = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($after['plaza_slug'] ?? ''))) ?? '';
        $serviceSlug = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($after['service_slug'] ?? ''))) ?? '';
        $geoKind = (string) ($after['geo_kind'] ?? 'ciudad');
        $isHub = !empty($after['is_hub']);
        if ($plazaSlug === '') {
            return ['ok' => false, 'applied' => false, 'error' => 'Propuesta URL corta sin plaza'];
        }
        $overrideKey = $isHub ? $plazaSlug . ':hub' : $plazaSlug . ':' . $serviceSlug;
        $patch = [
            'phase_key' => (string) ($after['phase_key'] ?? ''),
            'plaza_slug' => $plazaSlug,
            'geo_kind' => $geoKind,
            'service_slug' => $isHub ? '' : $serviceSlug,
            'is_hub' => $isHub,
            'short_url' => (string) ($after['canonical'] ?? $row['target_url'] ?? ''),
            'long_url' => (string) ($after['long_url'] ?? ''),
            'title' => (string) ($after['title'] ?? ''),
            'description' => (string) ($after['description'] ?? ''),
            'h1' => (string) ($after['h1'] ?? ''),
            'hero_subtitle' => (string) ($after['hero_subtitle'] ?? ''),
            'faq' => is_array($after['faq'] ?? null) ? $after['faq'] : [],
            'cross_link_label' => (string) ($after['cross_link_label'] ?? ''),
            'primary_keyword' => (string) ($after['primary_keyword'] ?? ''),
            'schema_area_served' => (string) ($after['schema_area_served'] ?? ''),
            'indexable' => true,
            'ai_applied_at' => date('c'),
        ];
        if ($patch['title'] === '' || $patch['h1'] === '') {
            return ['ok' => false, 'applied' => false, 'error' => 'Propuesta URL corta incompleta'];
        }
        $rel = 'includes/mexico/short-url-commercial-overrides.php';
        $root = cw_seo_mexico_autofix_site_root();
        $abs = $root ? $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel) : null;
        $existing = [];
        if ($abs && is_file($abs)) {
            $loaded = include $abs;
            if (is_array($loaded)) {
                $existing = $loaded;
            }
        }
        $existing[$overrideKey] = $patch;
        $php = "<?php\n/** Generado por IA — landings comerciales URL corta. */\nreturn "
            . var_export($existing, true) . ";\n";
        $write = cw_seo_mexico_autofix_write($rel, $php, 'IA short_url_commercial #' . $proposalId);
        if (empty($write['ok']) || empty($write['applied'])) {
            return ['ok' => false, 'applied' => false, 'error' => (string) ($write['error'] ?? 'No se pudo escribir overrides')];
        }
        $files[] = $rel;
        cw_seo_mexico_autofix_log($conn, 'ai_short_url', $rel, true, 'proposal #' . $proposalId, $write['backup'] ?? null, $userId, null);
        if (function_exists('cw_seo_mexico_short_urls_sync_checklist')) {
            require_once __DIR__ . '/cw_seo_mexico_short_urls_strategy.php';
            cw_seo_mexico_short_urls_sync_checklist($conn, $userId);
        }
    } elseif ($kind === 'blog_post' || $kind === 'blog_improve') {
        $afterArr = $after;
        $targetKeyRaw = (string) ($row['target_key'] ?? '');
        $isRehabManual = str_starts_with($targetKeyRaw, 'rehab:')
            || !empty($afterArr['manual_ai'])
            || !empty($afterArr['brain_owned'])
            || (($before['source'] ?? '') === 'rehab-queue');

        // Rehab blog (cerebro): IA manual en Cursor — NO reescribir archivos del sitio al “aplicar”.
        // Solo cierra la tarea en cola + aprendizaje; el contenido ya lo rehabilitó el humano.
        if ($isRehabManual && $kind === 'blog_improve') {
            $slug = (string) ($afterArr['slug'] ?? '');
            if ($slug === '' && str_starts_with($targetKeyRaw, 'rehab:')) {
                $slug = substr($targetKeyRaw, strlen('rehab:'));
            }
            $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug)) ?? '';
            if ($slug === '') {
                return ['ok' => false, 'applied' => false, 'error' => 'Rehab sin slug válido'];
            }
            $url = (string) ($afterArr['url'] ?? $row['target_url'] ?? ('https://conlineweb.com/blog/articulo/' . $slug . '/'));
            $executedAfter = array_merge($executedBefore, [
                'rehab_closed' => true,
                'manual_ai' => true,
                'slug' => $slug,
                'note' => 'Cierre de tanda rehab sin parche automático (diseño/funcionalidad del sitio intactos).',
            ]);
            $log = 'Rehab blog cerrada por cerebro · slug=' . $slug
                . ' · sin cambios de archivos (calidad/sitemap: actualizar manualmente tras reescritura)';
            $detail = trim((string) ($row['detail'] ?? ''));
            $detail .= "\n\nCIERRE REHAB " . date('Y-m-d H:i:s')
                . "\nSin escribir content/stub (protege diseño y funcionalidad)."
                . "\nSiguiente: quality=standard|gold + regenerar sitemap-blog si el artículo ya fue rehabilitado en código.";
            $afterArr['url'] = $url;
            $afterArr['rehab_closed_at'] = date('c');
            $afterJ = json_encode($afterArr, JSON_UNESCAPED_UNICODE);
            $execBeforeJ = json_encode($executedBefore, JSON_UNESCAPED_UNICODE);
            $execAfterJ = json_encode($executedAfter, JSON_UNESCAPED_UNICODE);
            $uid = max(0, $userId);
            $stmt = $conn->prepare(
                'UPDATE cw_seo_mexico_ai_proposals
                 SET status = \'applied\', target_url = ?, apply_log = ?, detail = ?, after_json = ?,
                     executed_before_json = ?, executed_after_json = ?,
                     applied_by = ?, applied_at = NOW()
                 WHERE id = ?'
            );
            if ($stmt) {
                $stmt->bind_param(
                    'ssssssii',
                    $url,
                    $log,
                    $detail,
                    $afterJ,
                    $execBeforeJ,
                    $execAfterJ,
                    $uid,
                    $proposalId
                );
                $stmt->execute();
                $stmt->close();
            }
            require_once __DIR__ . '/cw_site_ai_brain.php';
            cw_site_ai_brain_learn_from_applied($conn, array_merge($row, [
                'after' => $afterArr,
                'id' => $proposalId,
                'target_key' => 'rehab:' . $slug,
                'target_url' => $url,
            ]), $userId);
            return [
                'ok' => true,
                'applied' => true,
                'files' => [],
                'before' => $before,
                'after' => $afterArr,
                'executed_before' => $executedBefore,
                'executed_after' => $executedAfter,
                'url' => $url,
                'url_label' => 'Artículo rehab (cierre manual)',
                'message' => 'Rehab marcada como hecha sin modificar archivos del sitio. '
                    . 'Si ya reescribiste el artículo, pasa quality a standard/gold y regenera sitemap-blog.',
            ];
        }

        $slug = (string) ($after['slug'] ?? $row['target_key'] ?? '');
        // Quitar prefijo rehab: si llegara por error a apply automático
        if (str_starts_with($slug, 'rehab:')) {
            $slug = substr($slug, strlen('rehab:'));
        }
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug)) ?? '';
        if ($slug === '') {
            return ['ok' => false, 'applied' => false, 'error' => 'slug blog inválido'];
        }
        // Mejora: forzar slug original de la propuesta (sin prefijo rehab:)
        if ($kind === 'blog_improve') {
            $origKey = (string) ($row['target_key'] ?? '');
            if (str_starts_with($origKey, 'rehab:')) {
                $origKey = substr($origKey, strlen('rehab:'));
            }
            $orig = preg_replace('/[^a-z0-9\-]/', '', strtolower($origKey)) ?? '';
            if ($orig !== '') {
                $slug = $orig;
            }
        }
        $html = (string) ($after['html'] ?? '');
        if ($html === '') {
            return ['ok' => false, 'applied' => false, 'error' => 'HTML vacío'];
        }
        $category = cw_seo_mexico_ai_blog_normalize_category((string) ($after['category'] ?? 'seo'));
        // Sanitizar tags peligrosos / PHP embebido
        $html = preg_replace('#<(script|iframe|object|embed)[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace('/\son\w+\s*=\s*("|\')[^"\']*\1/i', '', $html) ?? $html;
        $html = str_replace(['<?php', '<?=', '<?'], ['&lt;?php', '&lt;?=', '&lt;?'], $html);

        $contentRel = 'includes/blog/content/' . $slug . '.php';
        $contentWrite = cw_seo_mexico_autofix_write($contentRel, $html . "\n", 'IA ' . $kind . ' #' . $proposalId);
        if (empty($contentWrite['ok']) || empty($contentWrite['applied'])) {
            return ['ok' => false, 'applied' => false, 'error' => (string) ($contentWrite['error'] ?? 'No se pudo escribir content')];
        }
        $files[] = $contentRel;

        // Stub solo si es artículo nuevo o aún no existe
        $stubRel = 'blog/articulo/' . $slug . '/index.php';
        $root = cw_seo_mexico_autofix_site_root();
        $stubAbs = $root
            ? $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $stubRel)
            : null;
        if ($kind === 'blog_post' || ($stubAbs && !is_file($stubAbs))) {
            $stubPhp = "<?php\nrequire_once __DIR__ . '/../../../includes/blog/page-factory.php';\n"
                . "\$page = blog_page_post(" . var_export($slug, true) . ");\n"
                . "if (\$page === null) {\n"
                . "    http_response_code(404);\n"
                . "    \$notFound = dirname(__DIR__, 3) . '/404.php';\n"
                . "    if (is_file(\$notFound)) {\n"
                . "        require \$notFound;\n"
                . "    }\n"
                . "    exit;\n"
                . "}\n"
                . "blog_render(\$page);\n";
            $stubWrite = cw_seo_mexico_autofix_write($stubRel, $stubPhp, 'IA blog stub #' . $proposalId);
            if (empty($stubWrite['ok'])) {
                return ['ok' => false, 'applied' => false, 'error' => (string) ($stubWrite['error'] ?? 'No se pudo escribir stub'), 'files' => $files];
            }
            // Garantizar que el stub exista en disco (evita 404 por apply incompleto)
            if ($stubAbs && !is_file($stubAbs)) {
                return ['ok' => false, 'applied' => false, 'error' => 'Stub de blog no quedó creado: ' . $stubRel, 'files' => $files];
            }
            if (!empty($stubWrite['applied'])) {
                $files[] = $stubRel;
            }
        }

        $meta = [
            'slug' => $slug,
            'title' => (string) ($after['title'] ?? ''),
            'keyword' => (string) ($after['keyword'] ?? ''),
            'category' => $category,
            'excerpt' => (string) ($after['excerpt'] ?? ''),
            'date' => (string) ($after['date'] ?? date('Y-m-d')),
            'read_minutes' => (int) ($after['read_minutes'] ?? 10),
            'type' => 'informativo',
            'builder' => $kind === 'blog_improve' ? 'ai-seo-improve' : 'ai-seo',
            'image' => 'assets/images/backgrounds/2024-05-15.webp',
        ];
        $manifestRel = 'includes/blog/posts/manifest.ai.php';
        $manifestWrite = cw_seo_mexico_ai_upsert_manifest_ai($meta);
        if (empty($manifestWrite['ok'])) {
            return ['ok' => false, 'applied' => false, 'error' => (string) ($manifestWrite['error'] ?? 'manifest.ai'), 'files' => $files];
        }
        $files[] = $manifestRel;
        cw_seo_mexico_autofix_log(
            $conn,
            $kind === 'blog_improve' ? 'ai_blog_improve' : 'ai_blog_post',
            $contentRel,
            true,
            'proposal #' . $proposalId . ' · ' . $slug,
            $contentWrite['backup'] ?? null,
            $userId,
            null
        );
    } elseif ($kind === 'site_patch') {
        require_once __DIR__ . '/cw_site_ai_maintain.php';
        $patch = cw_site_ai_maintain_apply_patch($after, 'IA site_patch #' . $proposalId);
        if (empty($patch['ok']) || empty($patch['applied'])) {
            return ['ok' => false, 'applied' => false, 'error' => (string) ($patch['error'] ?? 'No se pudo aplicar mantenimiento')];
        }
        $files[] = (string) ($patch['path'] ?? $after['path'] ?? '');
        cw_seo_mexico_autofix_log(
            $conn,
            'ai_site_patch',
            (string) ($patch['path'] ?? ''),
            true,
            'proposal #' . $proposalId,
            $patch['backup'] ?? null,
            $userId,
            null
        );
    } elseif ($kind === 'admin_patch') {
        require_once __DIR__ . '/cw_site_ai_admin.php';
        $patch = cw_site_ai_admin_apply_patch($after, 'IA admin_patch #' . $proposalId);
        if (empty($patch['ok']) || empty($patch['applied'])) {
            return ['ok' => false, 'applied' => false, 'error' => (string) ($patch['error'] ?? 'No se pudo aplicar mejora admin')];
        }
        $files[] = (string) ($patch['path'] ?? $after['path'] ?? '');
        if (function_exists('cw_seo_mexico_autofix_log')) {
            cw_seo_mexico_autofix_log(
                $conn,
                'ai_admin_patch',
                (string) ($patch['path'] ?? ''),
                true,
                'proposal #' . $proposalId . ' · vista ' . (string) ($after['view_key'] ?? ''),
                $patch['backup'] ?? null,
                $userId,
                null
            );
        }
    } elseif ($kind === 'cliente_patch') {
        require_once __DIR__ . '/cw_site_ai_cliente.php';
        $patch = cw_site_ai_cliente_apply_patch($after, 'IA cliente_patch #' . $proposalId);
        if (empty($patch['ok']) || empty($patch['applied'])) {
            return ['ok' => false, 'applied' => false, 'error' => (string) ($patch['error'] ?? 'No se pudo aplicar mejora cliente')];
        }
        $files[] = (string) ($patch['path'] ?? $after['path'] ?? '');
        if (function_exists('cw_seo_mexico_autofix_log')) {
            cw_seo_mexico_autofix_log(
                $conn,
                'ai_cliente_patch',
                (string) ($patch['path'] ?? ''),
                true,
                'proposal #' . $proposalId . ' · vista ' . (string) ($after['view_key'] ?? ''),
                $patch['backup'] ?? null,
                $userId,
                null
            );
        }
    } elseif ($kind === 'design_ui') {
        require_once __DIR__ . '/cw_site_ai_design.php';
        // Exigir que venga de preview (el API ya pide previewed=true)
        if (empty($after['preview_html']) && empty($after['preview_document'])) {
            return ['ok' => false, 'applied' => false, 'error' => 'Propuesta de diseño sin preview; no se puede implementar'];
        }
        $designApply = cw_site_ai_design_apply($after, 'IA design_ui #' . $proposalId);
        if (empty($designApply['ok']) || empty($designApply['applied'])) {
            return ['ok' => false, 'applied' => false, 'error' => (string) ($designApply['error'] ?? 'No se pudo aplicar diseño')];
        }
        foreach (($designApply['files'] ?? []) as $f) {
            $f = (string) $f;
            if ($f !== '') {
                $files[] = $f;
            }
        }
        cw_seo_mexico_autofix_log(
            $conn,
            'ai_design_ui',
            (string) ($after['path'] ?? $row['target_key'] ?? 'design'),
            true,
            'proposal #' . $proposalId . ' · patches=' . (int) ($designApply['patches_applied'] ?? 0)
            . ' · level=' . (string) ($after['reinvention_level'] ?? ''),
            null,
            $userId,
            null
        );
    } elseif ($kind === 'audit_fix') {
        require_once __DIR__ . '/cw_seo_mexico_autofix.php';
        $findingId = (int) ($after['finding_id'] ?? 0);
        if ($findingId < 1 && preg_match('/^finding:(\d+)$/', (string) ($row['target_key'] ?? ''), $fm)) {
            $findingId = (int) $fm[1];
        }
        if ($findingId < 1) {
            return ['ok' => false, 'applied' => false, 'error' => 'Propuesta audit_fix sin finding_id'];
        }
        $fixOne = cw_seo_mexico_autofix_fix_one_by_id($conn, $findingId, $userId);
        if (empty($fixOne['ok'])) {
            return [
                'ok' => false,
                'applied' => false,
                'error' => (string) ($fixOne['error'] ?? 'No se pudo aplicar la corrección del hallazgo'),
            ];
        }
        $item = is_array($fixOne['item'] ?? null) ? $fixOne['item'] : [];
        $relPath = (string) ($item['rel_path'] ?? '');
        if ($relPath !== '') {
            $files[] = $relPath;
        }
        $after['fix_report'] = $item;
        $after['applied_note'] = (string) ($fixOne['message'] ?? '');
        if (empty($fixOne['applied']) && empty($item['improved'])) {
            // Marcar propuesta aplicada aunque no hubiera escritura (ya corregido / no aplica)
            // pero informar en el log
        }
    } elseif ($kind === 'external_task' || $kind === 'external_rec') {
        // No escribe código: confirma la tarea externa y cierra el hallazgo asociado
        require_once __DIR__ . '/cw_seo_mexico_checklist.php';
        $findingId = (int) ($after['finding_id'] ?? 0);
        if ($findingId < 1 && preg_match('/^ext_finding:(\d+)$/', (string) ($row['target_key'] ?? ''), $fm)) {
            $findingId = (int) $fm[1];
        }
        $taskKey = (string) ($after['task_key'] ?? '');
        $note = 'Tarea SEO/GEO externa aceptada. Ejecutar fuera del sitio: '
            . mb_substr((string) ($after['correction'] ?? $after['summary'] ?? ''), 0, 500);
        if ($findingId > 0) {
            $conn->query(
                "UPDATE cw_seo_mexico_audit_findings
                 SET status = 'acknowledged', updated_at = NOW()
                 WHERE id = " . (int) $findingId . " AND status = 'open'"
            );
        }
        if ($taskKey !== '') {
            cw_seo_mexico_checklist_log_update(
                $conn,
                $taskKey,
                'Propuesta externa aprobada (ops fuera del código)',
                $note,
                $userId
            );
        }
        $after['applied_note'] = $note;
        $after['external_ack'] = true;
    } else {
        return ['ok' => false, 'applied' => false, 'error' => 'Tipo de propuesta no soportado: ' . $kind];
    }

    // Cerebro: aprender de lo aplicado
    require_once __DIR__ . '/cw_site_ai_brain.php';
    cw_site_ai_brain_learn_from_applied($conn, array_merge($row, [
        'id' => $proposalId,
        'kind' => $kind,
        'after' => $after,
    ]), $userId);

    // Actualizar /indice/ + sitemaps + llms
    require_once __DIR__ . '/cw_seo_mexico_directory_sync.php';
    $syncMeta = array_merge($after, [
        'slug' => (string) ($after['slug'] ?? $row['target_key'] ?? ''),
        'title' => (string) ($after['title'] ?? $row['title'] ?? ''),
        'category' => (string) ($after['category'] ?? ''),
        'city_slug' => (string) ($row['target_key'] ?? ''),
    ]);
    $dirSync = cw_seo_mexico_directory_sync_after_publish($kind, $syncMeta, $userId, $conn);
    if (!empty($dirSync['extra']['applied'])) {
        $files[] = 'includes/content-directory-extra.php';
    }
    foreach (($dirSync['discovery']['files'] ?? []) as $df) {
        $df = (string) $df;
        if ($df !== '' && !in_array($df, $files, true)) {
            $files[] = $df;
        }
    }

    // Snapshot real DESPUÉS de ejecutar
    $executedAfter = cw_seo_mexico_ai_execution_snapshot($kind, $row, $after, true);

    $urlMeta = cw_seo_mexico_ai_resolve_public_url(
        $kind,
        (string) ($row['target_key'] ?? ''),
        (string) ($row['target_url'] ?? ''),
        $after
    );
    $appliedUrl = (string) ($urlMeta['url'] ?? $row['target_url'] ?? '');
    $urlLabel = (string) ($urlMeta['url_label'] ?? 'URL aplicada');
    // Persistir URL en after_json también
    $after['url'] = $appliedUrl;
    $after['url_kind'] = (string) ($urlMeta['url_kind'] ?? 'page');
    $after['url_label'] = $urlLabel;
    $afterJ = json_encode($after, JSON_UNESCAPED_UNICODE);

    $detail = trim((string) ($row['detail'] ?? ''));
    if ($detail === '') {
        $detail = cw_seo_mexico_ai_build_proposal_detail(
            $kind,
            (string) ($row['title'] ?? ''),
            (string) ($row['prompt_summary'] ?? ''),
            $before,
            $after,
            $appliedUrl
        );
    }
    $detail .= "\n\nEJECUCIÓN " . date('Y-m-d H:i:s')
        . "\n" . $urlLabel . ': ' . $appliedUrl
        . "\nAntes real: " . cw_seo_mexico_monitor_summarize_safe($executedBefore, 400)
        . "\nDespués real: " . cw_seo_mexico_monitor_summarize_safe($executedAfter, 400);

    $log = 'URL: ' . $appliedUrl
        . ' · Archivos: ' . implode(', ', $files)
        . ' · Índice: ' . (string) ($dirSync['indice_url'] ?? 'https://conlineweb.com/indice/')
        . ' · Discovery: sitemaps + llms'
        . ' · Antes/después de ejecución guardados';
    $uid = max(0, $userId);
    $execBeforeJ = json_encode($executedBefore, JSON_UNESCAPED_UNICODE);
    $execAfterJ = json_encode($executedAfter, JSON_UNESCAPED_UNICODE);
    $stmt = $conn->prepare(
        'UPDATE cw_seo_mexico_ai_proposals
         SET status = \'applied\', target_url = ?, apply_log = ?, detail = ?, after_json = ?,
             executed_before_json = ?, executed_after_json = ?,
             applied_by = ?, applied_at = NOW()
         WHERE id = ?'
    );
    if ($stmt) {
        $stmt->bind_param(
            'ssssssii',
            $appliedUrl,
            $log,
            $detail,
            $afterJ,
            $execBeforeJ,
            $execAfterJ,
            $uid,
            $proposalId
        );
        $stmt->execute();
        $stmt->close();
    }

    return [
        'ok' => true,
        'applied' => true,
        'files' => $files,
        'before' => $before,
        'after' => $after,
        'executed_before' => $executedBefore,
        'executed_after' => $executedAfter,
        'detail' => $detail,
        'url' => $appliedUrl,
        'url_label' => $urlLabel,
        'indice_url' => (string) ($dirSync['indice_url'] ?? 'https://conlineweb.com/indice/'),
        'directory_sync' => $dirSync,
        'message' => 'Aplicado · URL ' . $appliedUrl . ' · ' . $log,
    ];
}

/**
 * @param array<string,mixed> $meta
 * @return array{ok:bool,applied?:bool,error?:string}
 */
function cw_seo_mexico_ai_upsert_manifest_ai(array $meta): array
{
    $root = cw_seo_mexico_autofix_site_root();
    if ($root === null) {
        return ['ok' => false, 'error' => 'Sitio no encontrado'];
    }
    $rel = 'includes/blog/posts/manifest.ai.php';
    $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $list = [];
    if (is_file($abs)) {
        $loaded = include $abs;
        if (is_array($loaded)) {
            $list = $loaded;
        }
    }
    $bySlug = [];
    foreach ($list as $p) {
        if (!empty($p['slug'])) {
            $bySlug[(string) $p['slug']] = $p;
        }
    }
    $bySlug[(string) $meta['slug']] = $meta;
    $export = var_export(array_values($bySlug), true);
    $php = "<?php\n/** Artículos generados/aprobados por IA SEO México */\nreturn " . $export . ";\n";
    return cw_seo_mexico_autofix_write($rel, $php, 'IA manifest.ai upsert');
}

/**
 * Chat dentro de una propuesta: el humano pide correcciones y la IA actualiza el borrador.
 * No publica al sitio; solo modifica after_json / título / URL de la solicitud pendiente.
 *
 * @return array{ok:bool,message?:string,error?:string,reply?:string,proposal_id?:int}
 */
function cw_seo_mexico_ai_refine_proposal_chat(
    mysqli $conn,
    int $proposalId,
    string $userMessage,
    int $userId = 0
): array {
    $proposalId = max(0, $proposalId);
    $userMessage = trim($userMessage);
    if ($proposalId < 1) {
        return ['ok' => false, 'error' => 'proposal_id inválido'];
    }
    if (mb_strlen($userMessage) < 3) {
        return ['ok' => false, 'error' => 'Escribe el mensaje del chat (mín. 3 caracteres)'];
    }
    if (mb_strlen($userMessage) > 6000) {
        return ['ok' => false, 'error' => 'Mensaje demasiado largo (máx. 6000)'];
    }
    if (!cw_seo_mexico_ai_available()) {
        return ['ok' => false, 'error' => 'IA no disponible / OpenAI no configurada'];
    }

    $row = cw_seo_mexico_ai_get_proposal($conn, $proposalId);
    if ($row === null) {
        return ['ok' => false, 'error' => 'Propuesta no encontrada'];
    }
    $st = (string) ($row['status'] ?? '');
    if (!in_array($st, ['pending', 'working'], true)) {
        return ['ok' => false, 'error' => 'Solo se puede chatear sobre propuestas pendientes o en revisión'];
    }

    require_once __DIR__ . '/cw_seo_mexico_ai_constitution.php';
    require_once __DIR__ . '/cw_site_ai_brain.php';

    $kind = (string) ($row['kind'] ?? '');
    $after = is_array($row['after'] ?? null) ? $row['after'] : [];
    $before = is_array($row['before'] ?? null) ? $row['before'] : [];
    $chatLog = is_array($after['refine_chat'] ?? null) ? $after['refine_chat'] : [];

    // Compactar after para el prompt (evitar tokens enormes)
    $afterForAi = $after;
    unset($afterForAi['refine_chat'], $afterForAi['clarifications'], $afterForAi['human_edits'], $afterForAi['detail']);
    if (!empty($afterForAi['html']) && mb_strlen((string) $afterForAi['html']) > 12000) {
        $afterForAi['html'] = mb_substr((string) $afterForAi['html'], 0, 12000) . "\n<!-- …truncado… -->";
    }
    if (!empty($afterForAi['preview_html']) && mb_strlen((string) $afterForAi['preview_html']) > 8000) {
        $afterForAi['preview_html'] = mb_substr((string) $afterForAi['preview_html'], 0, 8000) . '…';
    }
    if (!empty($afterForAi['preview_document'])) {
        unset($afterForAi['preview_document']);
    }

    $historyBits = [];
    foreach (array_slice($chatLog, -8) as $m) {
        if (!is_array($m)) {
            continue;
        }
        $role = (string) ($m['role'] ?? '');
        $txt = trim((string) ($m['content'] ?? ''));
        if ($txt === '' || !in_array($role, ['user', 'assistant'], true)) {
            continue;
        }
        $historyBits[] = strtoupper($role) . ': ' . mb_substr($txt, 0, 1200);
    }

    $brain = function_exists('cw_site_ai_brain_context') ? cw_site_ai_brain_context($conn, 12) : '';
    $system = 'Eres el cerebro MASTER de ConlineWeb (web, sistemas, SEO/GEO, diseño, contenido, desarrollo, '
        . "mantenimiento, soporte y actualizaciones).\n"
        . cw_seo_mexico_ai_master_brief() . "\n"
        . mb_substr(cw_seo_mexico_ai_domain_mastery_brief(), 0, 1400) . "\n"
        . mb_substr(cw_seo_mexico_ai_seo_geo_playbook(), 0, 1400) . "\n"
        . $brain . "\n"
        . "Tarea: el humano chatea SOBRE UNA PROPUESTA YA CREADA. Debes aplicar sus correcciones al BORRADOR "
        . "(after), no al sitio en vivo.\n"
        . "Responde SOLO JSON:\n"
        . '{"reply":"mensaje corto al humano","title":"","target_url":"","after_patch":{},"changed_fields":[]}'
        . "\n- reply: qué corregiste (español México, sin emojis).\n"
        . "- title/target_url: solo si deben cambiar; si no, string vacío.\n"
        . "- after_patch: SOLO campos a actualizar del after (parcial). Incluye rationale actualizado si cambia el sentido.\n"
        . "- Para design_ui puedes devolver patches (array) y/o preview_html.\n"
        . "- Para blog: html/title/excerpt/keyword/slug.\n"
        . "- Para hub_text: title/description/h1/hero_subtitle.\n"
        . "- Para site_patch/admin_patch/cliente_patch: path/search/replace/summary.\n"
        . "- Mantén mejores técnicas SEO/GEO y calidad de desarrollo. No inventes clientes/precios.\n"
        . '- kind actual: ' . $kind;

    $user = "Propuesta #{$proposalId}\n"
        . 'kind: ' . $kind . "\n"
        . 'title actual: ' . (string) ($row['title'] ?? '') . "\n"
        . 'URL actual: ' . (string) ($row['target_url'] ?? '') . "\n"
        . "AFTER actual (JSON):\n" . json_encode($afterForAi, JSON_UNESCAPED_UNICODE) . "\n";
    if ($historyBits !== []) {
        $user .= "Historial reciente del chat de esta propuesta:\n" . implode("\n", $historyBits) . "\n";
    }
    $user .= "Mensaje del humano (aplica la corrección al borrador):\n{$userMessage}";

    $chat = cw_seo_mexico_ai_chat([
        ['role' => 'system', 'content' => $system],
        ['role' => 'user', 'content' => $user],
    ], 0.35);
    if (empty($chat['ok'])) {
        return ['ok' => false, 'error' => (string) ($chat['error'] ?? 'IA falló al refinar')];
    }
    $parsed = json_decode((string) ($chat['content'] ?? ''), true);
    if (!is_array($parsed)) {
        return ['ok' => false, 'error' => 'La IA no devolvió JSON válido para el chat de propuesta'];
    }

    $reply = trim((string) ($parsed['reply'] ?? 'Listo: actualicé el borrador de la propuesta.'));
    $afterPatch = is_array($parsed['after_patch'] ?? null) ? $parsed['after_patch'] : [];
    // Si mandaron campos sueltos al root, mezclar
    foreach (['title', 'description', 'h1', 'hero_subtitle', 'html', 'excerpt', 'keyword', 'slug',
        'path', 'search', 'replace', 'summary', 'rationale', 'correction', 'preview_html', 'patches',
    ] as $k) {
        if (array_key_exists($k, $parsed) && !array_key_exists($k, $afterPatch)) {
            $afterPatch[$k] = $parsed[$k];
        }
    }

    $newTitle = trim((string) ($parsed['title'] ?? ''));
    $newUrl = trim((string) ($parsed['target_url'] ?? ''));

    // Registrar mensaje usuario en chat
    $chatLog[] = [
        'role' => 'user',
        'content' => $userMessage,
        'at' => date('Y-m-d H:i:s'),
        'by' => max(0, $userId),
    ];

    if ($afterPatch !== [] || $newTitle !== '' || $newUrl !== '') {
        $patch = [
            'title' => $newTitle !== '' ? $newTitle : (string) ($row['title'] ?? ''),
            'target_url' => $newUrl !== '' ? $newUrl : (string) ($row['target_url'] ?? ''),
            'after' => $afterPatch,
        ];
        // Preservar historial de chat al hacer merge: update_proposal_draft no toca refine_chat si no viene
        // Así que primero mergeamos patch, luego reinyectamos chat + assistant reply
        $upd = cw_seo_mexico_ai_update_proposal_draft($conn, $proposalId, $patch, $userId);
        if (empty($upd['ok'])) {
            return [
                'ok' => false,
                'error' => (string) ($upd['error'] ?? 'No se pudo aplicar la corrección al borrador'),
                'reply' => $reply,
            ];
        }
    }

    // Recargar y guardar hilo de chat + reply
    $row2 = cw_seo_mexico_ai_get_proposal($conn, $proposalId);
    if ($row2 === null) {
        return ['ok' => false, 'error' => 'Propuesta perdida tras actualizar'];
    }
    $after2 = is_array($row2['after'] ?? null) ? $row2['after'] : [];
    $chatLog = is_array($after2['refine_chat'] ?? null) ? $after2['refine_chat'] : $chatLog;
    // si update reemplazó after sin chat, reconstruir
    $hasUser = false;
    foreach ($chatLog as $m) {
        if (($m['role'] ?? '') === 'user' && ($m['content'] ?? '') === $userMessage) {
            $hasUser = true;
            break;
        }
    }
    if (!$hasUser) {
        $chatLog[] = [
            'role' => 'user',
            'content' => $userMessage,
            'at' => date('Y-m-d H:i:s'),
            'by' => max(0, $userId),
        ];
    }
    $chatLog[] = [
        'role' => 'assistant',
        'content' => $reply,
        'at' => date('Y-m-d H:i:s'),
        'by' => 0,
    ];
    if (count($chatLog) > 40) {
        $chatLog = array_slice($chatLog, -40);
    }
    $after2['refine_chat'] = $chatLog;
    $detail = trim((string) ($row2['detail'] ?? ''));
    $detail .= "\n\nCHAT PROPUESTA " . date('Y-m-d H:i:s') . "\nHumano: "
        . mb_substr($userMessage, 0, 500) . "\nIA: " . mb_substr($reply, 0, 500);
    $afterJ = json_encode($after2, JSON_UNESCAPED_UNICODE);
    $stmt = $conn->prepare(
        'UPDATE cw_seo_mexico_ai_proposals SET after_json = ?, detail = ?,
         status = IF(status = \'pending\', \'working\', status) WHERE id = ?'
    );
    if ($stmt) {
        $stmt->bind_param('ssi', $afterJ, $detail, $proposalId);
        $stmt->execute();
        $stmt->close();
    }

    return [
        'ok' => true,
        'proposal_id' => $proposalId,
        'reply' => $reply,
        'message' => 'Corrección aplicada al borrador de la propuesta (aún no publicada).',
        'changed_fields' => is_array($parsed['changed_fields'] ?? null) ? $parsed['changed_fields'] : [],
    ];
}

/**
 * Añade una aclaración humana a una propuesta (antes de implementar).
 * Aplica a todos los kinds pendientes/en revisión.
 *
 * @return array{ok:bool,message?:string,error?:string,clarifications?:list<array>}
 */
function cw_seo_mexico_ai_add_proposal_clarification(
    mysqli $conn,
    int $proposalId,
    string $note,
    int $userId = 0
): array {
    $proposalId = max(0, $proposalId);
    $note = trim($note);
    if ($proposalId < 1) {
        return ['ok' => false, 'error' => 'proposal_id inválido'];
    }
    if (mb_strlen($note) < 3) {
        return ['ok' => false, 'error' => 'Escribe una aclaración (mín. 3 caracteres)'];
    }
    if (mb_strlen($note) > 4000) {
        return ['ok' => false, 'error' => 'Aclaración demasiado larga (máx. 4000)'];
    }
    $row = cw_seo_mexico_ai_get_proposal($conn, $proposalId);
    if ($row === null) {
        return ['ok' => false, 'error' => 'Propuesta no encontrada'];
    }
    $st = (string) ($row['status'] ?? '');
    if (!in_array($st, ['pending', 'working'], true)) {
        return ['ok' => false, 'error' => 'Solo se pueden aclarar propuestas pendientes o en revisión'];
    }
    $after = is_array($row['after'] ?? null) ? $row['after'] : [];
    $list = is_array($after['clarifications'] ?? null) ? $after['clarifications'] : [];
    $entry = [
        'at' => date('Y-m-d H:i:s'),
        'by' => max(0, $userId),
        'text' => $note,
    ];
    $list[] = $entry;
    if (count($list) > 40) {
        $list = array_slice($list, -40);
    }
    $after['clarifications'] = $list;
    $detail = trim((string) ($row['detail'] ?? ''));
    $detail .= "\n\nACLARACIÓN " . $entry['at'] . " (usuario #" . $entry['by'] . ")\n" . $note;
    $afterJ = json_encode($after, JSON_UNESCAPED_UNICODE);
    $stmt = $conn->prepare(
        'UPDATE cw_seo_mexico_ai_proposals
         SET after_json = ?, detail = ?, status = IF(status = \'pending\', \'working\', status)
         WHERE id = ? AND status IN (\'pending\',\'working\')'
    );
    if (!$stmt) {
        return ['ok' => false, 'error' => 'No se pudo guardar la aclaración'];
    }
    $stmt->bind_param('ssi', $afterJ, $detail, $proposalId);
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();
    if (!$ok) {
        return ['ok' => false, 'error' => 'No se actualizó la propuesta (ya no está en estado editable)'];
    }
    return [
        'ok' => true,
        'message' => 'Aclaración guardada en la propuesta.',
        'clarifications' => $list,
    ];
}

/**
 * Edita el borrador de una propuesta antes de implementar (todos los kinds).
 *
 * @param array<string,mixed> $patch keys: title?, target_url?, after? (parcial)
 * @return array{ok:bool,message?:string,error?:string}
 */
function cw_seo_mexico_ai_update_proposal_draft(
    mysqli $conn,
    int $proposalId,
    array $patch,
    int $userId = 0
): array {
    $proposalId = max(0, $proposalId);
    if ($proposalId < 1) {
        return ['ok' => false, 'error' => 'proposal_id inválido'];
    }
    $row = cw_seo_mexico_ai_get_proposal($conn, $proposalId);
    if ($row === null) {
        return ['ok' => false, 'error' => 'Propuesta no encontrada'];
    }
    $st = (string) ($row['status'] ?? '');
    if (!in_array($st, ['pending', 'working'], true)) {
        return ['ok' => false, 'error' => 'Solo se pueden editar propuestas pendientes o en revisión'];
    }

    $kind = (string) ($row['kind'] ?? '');
    $after = is_array($row['after'] ?? null) ? $row['after'] : [];
    $before = is_array($row['before'] ?? null) ? $row['before'] : [];
    $title = trim((string) ($patch['title'] ?? $row['title'] ?? ''));
    $targetUrl = trim((string) ($patch['target_url'] ?? $row['target_url'] ?? ''));
    $afterPatch = is_array($patch['after'] ?? null) ? $patch['after'] : [];

    // Campos editables comunes + por tipo
    $allowed = [
        'rationale', 'summary', 'why', 'correction', 'evidence', 'title', 'description',
        'h1', 'hero_subtitle', 'excerpt', 'keyword', 'html', 'slug', 'category',
        'path', 'search', 'replace', 'change_type', 'preview_html', 'preview_css',
        'scope', 'reinvention_level', 'view_key', 'view_label', 'congruence',
        'view_purpose', 'instruction', 'channel',
    ];
    $changed = [];
    foreach ($allowed as $key) {
        if (!array_key_exists($key, $afterPatch)) {
            continue;
        }
        $val = $afterPatch[$key];
        if (is_array($val) || is_object($val)) {
            continue;
        }
        $str = (string) $val;
        if (mb_strlen($str) > 200000) {
            return ['ok' => false, 'error' => "Campo «{$key}» demasiado largo"];
        }
        $prev = (string) ($after[$key] ?? '');
        if ($str !== $prev) {
            $after[$key] = $str;
            $changed[] = $key;
        }
    }

    // Parches de diseño (lista)
    if (array_key_exists('patches', $afterPatch)) {
        $patchesRaw = $afterPatch['patches'];
        if (is_string($patchesRaw)) {
            $decoded = json_decode($patchesRaw, true);
            $patchesRaw = is_array($decoded) ? $decoded : null;
        }
        if (!is_array($patchesRaw)) {
            return ['ok' => false, 'error' => 'patches debe ser un JSON array [{path,search,replace}]'];
        }
        $cleanPatches = [];
        foreach ($patchesRaw as $pt) {
            if (!is_array($pt)) {
                continue;
            }
            $pPath = str_replace('\\', '/', ltrim((string) ($pt['path'] ?? ''), '/'));
            $pSearch = (string) ($pt['search'] ?? '');
            $pReplace = (string) ($pt['replace'] ?? '');
            if ($pPath === '' || $pSearch === '') {
                continue;
            }
            $cleanPatches[] = [
                'path' => $pPath,
                'search' => $pSearch,
                'replace' => $pReplace,
            ];
            if (count($cleanPatches) >= 8) {
                break;
            }
        }
        $after['patches'] = $cleanPatches;
        $changed[] = 'patches';
    }

    if ($title === '') {
        return ['ok' => false, 'error' => 'El título no puede quedar vacío'];
    }
    if ($targetUrl !== '' && !preg_match('#^https?://#i', $targetUrl)) {
        $targetUrl = 'https://conlineweb.com/' . ltrim($targetUrl, '/');
    }
    if ($targetUrl !== '') {
        $after['url'] = $targetUrl;
    }

    // Sync title fields for hub/blog
    if (isset($after['title']) && $after['title'] === '' && $title !== '') {
        $after['title'] = $title;
    }
    if ($kind === 'hub_text' || $kind === 'short_url_commercial' || $kind === 'blog_post' || $kind === 'blog_improve') {
        if (!empty($afterPatch['title'])) {
            $title = trim((string) $afterPatch['title']);
        }
    }

    $rationale = trim((string) ($after['rationale'] ?? $after['why'] ?? ''));
    if (mb_strlen($rationale) < 20) {
        return ['ok' => false, 'error' => 'El fundamento (rationale) debe tener al menos 20 caracteres'];
    }
    $after['rationale'] = $rationale;

    $edits = is_array($after['human_edits'] ?? null) ? $after['human_edits'] : [];
    $edits[] = [
        'at' => date('Y-m-d H:i:s'),
        'by' => max(0, $userId),
        'fields' => array_values(array_unique($changed)),
        'note' => 'Edición humana previa a implementar',
    ];
    if (count($edits) > 30) {
        $edits = array_slice($edits, -30);
    }
    $after['human_edits'] = $edits;

    $detail = cw_seo_mexico_ai_build_proposal_detail(
        $kind,
        $title,
        (string) ($row['prompt_summary'] ?? ''),
        $before,
        $after,
        $targetUrl
    );
    $detail .= "\n\nEDICIÓN HUMANA " . date('Y-m-d H:i:s')
        . ' · campos: ' . ($changed !== [] ? implode(', ', array_unique($changed)) : 'metadatos');
    $after['detail'] = $detail;

    $afterJ = json_encode($after, JSON_UNESCAPED_UNICODE);
    $stmt = $conn->prepare(
        'UPDATE cw_seo_mexico_ai_proposals
         SET title = ?, target_url = ?, after_json = ?, detail = ?,
             status = IF(status = \'pending\', \'working\', status)
         WHERE id = ? AND status IN (\'pending\',\'working\')'
    );
    if (!$stmt) {
        return ['ok' => false, 'error' => 'No se pudo guardar la edición'];
    }
    $stmt->bind_param('ssssi', $title, $targetUrl, $afterJ, $detail, $proposalId);
    $stmt->execute();
    $stmt->close();

    return [
        'ok' => true,
        'message' => 'Propuesta actualizada. Revisa el detalle y luego aprueba e implementa.',
        'changed' => array_values(array_unique($changed)),
    ];
}

/**
 * Rechaza una propuesta pendiente/en revisión.
 *
 * @return array{ok:bool,message?:string,error?:string}
 */
function cw_seo_mexico_ai_reject_proposal(mysqli $conn, int $proposalId): array
{
    $proposalId = max(0, $proposalId);
    if ($proposalId < 1) {
        return ['ok' => false, 'error' => 'proposal_id inválido'];
    }
    $row = cw_seo_mexico_ai_get_proposal($conn, $proposalId);
    if ($row === null) {
        return ['ok' => false, 'error' => 'No encontrada'];
    }
    $st = (string) ($row['status'] ?? '');
    if (!in_array($st, ['pending', 'working'], true)) {
        return ['ok' => false, 'error' => 'La propuesta no está pendiente (status=' . $st . ')'];
    }
    $conn->query(
        'UPDATE cw_seo_mexico_ai_proposals SET status = \'rejected\'
         WHERE id = ' . $proposalId . " AND status IN ('pending','working')"
    );
    return ['ok' => true, 'message' => 'Propuesta rechazada'];
}

/**
 * Encola un hallazgo auto-corregible como propuesta (sin escribir aún).
 *
 * @return array{ok:bool,proposal_id?:int,skipped?:bool,error?:string,message?:string}
 */
function cw_seo_mexico_ai_propose_audit_fix(mysqli $conn, int $findingId, int $userId = 0): array
{
    $findingId = max(0, $findingId);
    if ($findingId < 1) {
        return ['ok' => false, 'error' => 'finding_id inválido'];
    }
    require_once __DIR__ . '/cw_seo_mexico_autofix.php';
    cw_seo_mexico_ai_proposals_ensure_table($conn);

    $targetKey = 'finding:' . $findingId;
    $dup = $conn->prepare(
        "SELECT id FROM cw_seo_mexico_ai_proposals
         WHERE kind = 'audit_fix' AND target_key = ? AND status IN ('pending','working')
         LIMIT 1"
    );
    if ($dup) {
        $dup->bind_param('s', $targetKey);
        $dup->execute();
        $res = $dup->get_result();
        $exist = $res ? $res->fetch_assoc() : null;
        $dup->close();
        if (is_array($exist) && (int) ($exist['id'] ?? 0) > 0) {
            return [
                'ok' => true,
                'skipped' => true,
                'proposal_id' => (int) $exist['id'],
                'message' => 'Ya hay una propuesta pendiente para este hallazgo.',
            ];
        }
    }

    $stmt = $conn->prepare(
        'SELECT id, finding_key, check_type, severity, status, url, title, evidence, correction,
                auto_fixable, auto_applied, task_key, meta_json
         FROM cw_seo_mexico_audit_findings WHERE id = ? LIMIT 1'
    );
    if (!$stmt) {
        return ['ok' => false, 'error' => 'No se pudo consultar el hallazgo'];
    }
    $stmt->bind_param('i', $findingId);
    $stmt->execute();
    $res = $stmt->get_result();
    $finding = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!is_array($finding)) {
        return ['ok' => false, 'error' => 'Hallazgo no encontrado'];
    }
    if (empty($finding['auto_fixable'])) {
        return ['ok' => false, 'error' => 'Este hallazgo no es auto-corregible; requiere revisión manual o propuesta IA de contenido.'];
    }
    if ((string) ($finding['status'] ?? '') !== 'open' || !empty($finding['auto_applied'])) {
        return ['ok' => false, 'error' => 'El hallazgo ya no está abierto o ya fue corregido.'];
    }

    $url = trim((string) ($finding['url'] ?? ''));
    $title = trim((string) ($finding['title'] ?? 'Corrección de auditoría'));
    $correction = trim((string) ($finding['correction'] ?? ''));
    $evidence = trim((string) ($finding['evidence'] ?? ''));
    $fkey = (string) ($finding['finding_key'] ?? '');
    $checkType = (string) ($finding['check_type'] ?? '');
    $severity = (string) ($finding['severity'] ?? 'medium');

    $beforeSnap = $url !== '' ? cw_seo_mexico_autofix_snapshot_url($url) : [
        'ok' => false,
        'summary' => 'Sin URL para snapshot',
        'status' => 0,
    ];

    $rationale = 'Corrección segura detectada por la auditoría SEO México'
        . ($severity !== '' ? ' (severidad ' . $severity . ')' : '')
        . '. La IA/AutoFix aplicará el remediador whitelist solo si apruebas esta propuesta.';
    if ($correction !== '') {
        $rationale .= ' Plan: ' . mb_substr($correction, 0, 280);
    }

    $after = [
        'mode' => 'audit_fix',
        'finding_id' => $findingId,
        'finding_key' => $fkey,
        'check_type' => $checkType,
        'severity' => $severity,
        'task_key' => (string) ($finding['task_key'] ?? ''),
        'title' => $title,
        'correction' => $correction,
        'evidence' => $evidence,
        'url' => $url,
        'rationale' => $rationale,
        'summary' => 'Encolar corrección de hallazgo #' . $findingId . ' · ' . $title,
        'change_type' => 'autofix_seguro',
    ];

    $saved = cw_seo_mexico_ai_save_proposal(
        $conn,
        'audit_fix',
        'Corrección · ' . mb_substr($title, 0, 120),
        $targetKey,
        $url,
        'Checklist: hallazgo auto-corregible encolado para aprobación humana.',
        [
            'finding' => [
                'id' => $findingId,
                'key' => $fkey,
                'status' => (string) ($finding['status'] ?? 'open'),
                'evidence' => mb_substr($evidence, 0, 500),
            ],
            'live' => $beforeSnap,
        ],
        $after,
        $userId
    );
    if (empty($saved['ok'])) {
        return ['ok' => false, 'error' => (string) ($saved['error'] ?? 'No se pudo encolar la propuesta')];
    }

    return [
        'ok' => true,
        'proposal_id' => (int) ($saved['id'] ?? 0),
        'message' => 'Corrección encolada como propuesta #' . (int) ($saved['id'] ?? 0) . '. Aprueba o rechaza en la cola.',
        'url' => $url,
    ];
}

/**
 * Encola todos los hallazgos abiertos auto-corregibles como propuestas.
 *
 * @return array{ok:bool,queued:int,skipped:int,failed:int,proposal_ids:list<int>,error?:string,message?:string}
 */
function cw_seo_mexico_ai_enqueue_open_audit_fixes(mysqli $conn, int $userId = 0, int $limit = 40): array
{
    require_once __DIR__ . '/cw_seo_mexico_autofix.php';
    $open = cw_seo_mexico_autofix_open_fixable($conn, max(1, min(80, $limit)));
    $queued = 0;
    $skipped = 0;
    $failed = 0;
    $ids = [];
    foreach ($open as $f) {
        $fid = (int) ($f['id'] ?? 0);
        if ($fid < 1) {
            continue;
        }
        $one = cw_seo_mexico_ai_propose_audit_fix($conn, $fid, $userId);
        if (!empty($one['ok']) && !empty($one['skipped'])) {
            $skipped++;
            if (!empty($one['proposal_id'])) {
                $ids[] = (int) $one['proposal_id'];
            }
            continue;
        }
        if (!empty($one['ok']) && !empty($one['proposal_id'])) {
            $queued++;
            $ids[] = (int) $one['proposal_id'];
            continue;
        }
        $failed++;
    }

    return [
        'ok' => true,
        'queued' => $queued,
        'skipped' => $skipped,
        'failed' => $failed,
        'proposal_ids' => $ids,
        'message' => 'Propuestas de corrección: ' . $queued . ' nuevas · '
            . $skipped . ' ya en cola · ' . $failed . ' fallidas.',
    ];
}

/**
 * Mapa finding_id → proposal_id para hallazgos con propuesta pendiente.
 *
 * @return array<int,int>
 */
function cw_seo_mexico_ai_pending_audit_fix_map(mysqli $conn): array
{
    cw_seo_mexico_ai_proposals_ensure_table($conn);
    $map = [];
    $res = $conn->query(
        "SELECT id, target_key FROM cw_seo_mexico_ai_proposals
         WHERE kind = 'audit_fix' AND status IN ('pending','working')
         ORDER BY id DESC LIMIT 200"
    );
    if (!$res) {
        return $map;
    }
    while ($row = $res->fetch_assoc()) {
        if (!preg_match('/^finding:(\d+)$/', (string) ($row['target_key'] ?? ''), $m)) {
            continue;
        }
        $fid = (int) $m[1];
        if ($fid > 0 && !isset($map[$fid])) {
            $map[$fid] = (int) ($row['id'] ?? 0);
        }
    }
    return $map;
}

/**
 * Encola un hallazgo externo (ext_*) como propuesta de tarea SEO/GEO.
 *
 * @return array{ok:bool,proposal_id?:int,skipped?:bool,error?:string,message?:string}
 */
function cw_seo_mexico_ai_propose_external_task(mysqli $conn, int $findingId, int $userId = 0): array
{
    $findingId = max(0, $findingId);
    if ($findingId < 1) {
        return ['ok' => false, 'error' => 'finding_id inválido'];
    }
    require_once __DIR__ . '/cw_seo_mexico_external.php';
    cw_seo_mexico_ai_proposals_ensure_table($conn);

    $targetKey = 'ext_finding:' . $findingId;
    $dup = $conn->prepare(
        "SELECT id FROM cw_seo_mexico_ai_proposals
         WHERE kind IN ('external_task','external_rec') AND target_key = ?
           AND status IN ('pending','working')
         LIMIT 1"
    );
    if ($dup) {
        $dup->bind_param('s', $targetKey);
        $dup->execute();
        $res = $dup->get_result();
        $exist = $res ? $res->fetch_assoc() : null;
        $dup->close();
        if (is_array($exist) && (int) ($exist['id'] ?? 0) > 0) {
            return [
                'ok' => true,
                'skipped' => true,
                'proposal_id' => (int) $exist['id'],
                'message' => 'Ya hay una propuesta externa pendiente para este hallazgo.',
            ];
        }
    }

    $stmt = $conn->prepare(
        'SELECT id, finding_key, check_type, severity, status, url, title, evidence, correction,
                auto_fixable, task_key, meta_json
         FROM cw_seo_mexico_audit_findings WHERE id = ? LIMIT 1'
    );
    if (!$stmt) {
        return ['ok' => false, 'error' => 'No se pudo consultar el hallazgo'];
    }
    $stmt->bind_param('i', $findingId);
    $stmt->execute();
    $res = $stmt->get_result();
    $finding = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!is_array($finding)) {
        return ['ok' => false, 'error' => 'Hallazgo no encontrado'];
    }

    $checkType = (string) ($finding['check_type'] ?? '');
    $meta = [];
    if (!empty($finding['meta_json'])) {
        $decoded = json_decode((string) $finding['meta_json'], true);
        if (is_array($decoded)) {
            $meta = $decoded;
        }
    }
    if (!cw_seo_mexico_external_is_check($checkType, ['execution' => $meta['execution'] ?? '', 'meta' => $meta])) {
        return ['ok' => false, 'error' => 'Este hallazgo no es una tarea SEO/GEO externa'];
    }
    if ((string) ($finding['status'] ?? '') !== 'open') {
        return ['ok' => false, 'error' => 'El hallazgo externo ya no está abierto'];
    }

    $url = trim((string) ($finding['url'] ?? 'https://conlineweb.com/'));
    $title = trim((string) ($finding['title'] ?? 'Tarea SEO/GEO externa'));
    $correction = trim((string) ($finding['correction'] ?? ''));
    $evidence = trim((string) ($finding['evidence'] ?? ''));
    $severity = (string) ($finding['severity'] ?? 'medium');
    $isRec = ($checkType === 'ext_rec') || (($meta['kind'] ?? '') === 'external_rec');
    $kind = $isRec ? 'external_rec' : 'external_task';

    $rationale = 'Tarea/recomendación SEO y GEO fuera del código (GSC, Maps, reseñas, citaciones, medición). '
        . 'Severidad ' . $severity . '. Al aprobar se registra como aceptada; la ejecución es operativa externa.';
    if ($correction !== '') {
        $rationale .= ' Plan: ' . mb_substr($correction, 0, 320);
    }

    $after = [
        'mode' => $kind,
        'execution' => 'external',
        'finding_id' => $findingId,
        'finding_key' => (string) ($finding['finding_key'] ?? ''),
        'check_type' => $checkType,
        'severity' => $severity,
        'channel' => (string) ($meta['channel'] ?? 'seo_geo'),
        'task_key' => (string) ($finding['task_key'] ?? ''),
        'title' => $title,
        'correction' => $correction,
        'evidence' => $evidence,
        'url' => $url,
        'rationale' => $rationale,
        'summary' => 'SEO/GEO externo · ' . $title,
        'change_type' => 'external_ops',
    ];

    $saved = cw_seo_mexico_ai_save_proposal(
        $conn,
        $kind,
        'Externo · ' . mb_substr($title, 0, 120),
        $targetKey,
        $url,
        'Análisis: tarea/recomendación SEO/GEO externa para aprobación humana.',
        [
            'finding' => [
                'id' => $findingId,
                'key' => (string) ($finding['finding_key'] ?? ''),
                'status' => 'open',
                'evidence' => mb_substr($evidence, 0, 500),
            ],
        ],
        $after,
        $userId
    );
    if (empty($saved['ok'])) {
        return ['ok' => false, 'error' => (string) ($saved['error'] ?? 'No se pudo encolar la tarea externa')];
    }

    return [
        'ok' => true,
        'proposal_id' => (int) ($saved['id'] ?? 0),
        'message' => 'Tarea SEO/GEO externa encolada como propuesta #' . (int) ($saved['id'] ?? 0) . '.',
        'url' => $url,
    ];
}

/**
 * Encola hallazgos externos abiertos (check_type ext_*) como propuestas.
 *
 * @return array{ok:bool,queued:int,skipped:int,failed:int,proposal_ids:list<int>,message:string}
 */
function cw_seo_mexico_ai_enqueue_open_external_tasks(mysqli $conn, int $userId = 0, int $limit = 20): array
{
    require_once __DIR__ . '/cw_seo_mexico_external.php';
    cw_seo_mexico_ai_proposals_ensure_table($conn);
    $limit = max(1, min(40, $limit));

    $res = $conn->query(
        "SELECT id, check_type, meta_json FROM cw_seo_mexico_audit_findings
         WHERE status = 'open'
           AND (check_type LIKE 'ext_%' OR check_type LIKE 'external_%'
                OR meta_json LIKE '%\"execution\":\"external\"%')
         ORDER BY FIELD(severity,'critical','high','medium','low'), id DESC
         LIMIT " . (int) $limit
    );

    $queued = 0;
    $skipped = 0;
    $failed = 0;
    $ids = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $fid = (int) ($row['id'] ?? 0);
            if ($fid < 1) {
                continue;
            }
            $one = cw_seo_mexico_ai_propose_external_task($conn, $fid, $userId);
            if (!empty($one['ok']) && !empty($one['skipped'])) {
                $skipped++;
                if (!empty($one['proposal_id'])) {
                    $ids[] = (int) $one['proposal_id'];
                }
                continue;
            }
            if (!empty($one['ok']) && !empty($one['proposal_id'])) {
                $queued++;
                $ids[] = (int) $one['proposal_id'];
                continue;
            }
            $failed++;
        }
        $res->free();
    }

    return [
        'ok' => true,
        'queued' => $queued,
        'skipped' => $skipped,
        'failed' => $failed,
        'proposal_ids' => $ids,
        'message' => 'Tareas SEO/GEO externas: ' . $queued . ' nuevas · '
            . $skipped . ' ya en cola · ' . $failed . ' fallidas.',
    ];
}
