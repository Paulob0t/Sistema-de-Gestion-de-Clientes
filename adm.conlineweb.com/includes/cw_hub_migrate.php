<?php
/**
 * Auto-migración tablas Hub Analítico + CRM
 */
function cw_hub_migrate(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    cw_hub_db_timezone($conn);

    $conn->query("CREATE TABLE IF NOT EXISTS cw_analytics_sessions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(64) NOT NULL,
        visitor_id VARCHAR(64) NOT NULL,
        first_seen DATETIME NOT NULL,
        last_seen DATETIME NOT NULL,
        ip_hash VARCHAR(64) DEFAULT NULL,
        user_agent TEXT,
        device_type VARCHAR(20) DEFAULT NULL,
        browser VARCHAR(60) DEFAULT NULL,
        os VARCHAR(60) DEFAULT NULL,
        country VARCHAR(80) DEFAULT NULL,
        region VARCHAR(80) DEFAULT NULL,
        utm_source VARCHAR(120) DEFAULT NULL,
        utm_medium VARCHAR(120) DEFAULT NULL,
        utm_campaign VARCHAR(120) DEFAULT NULL,
        referrer TEXT,
        landing_url TEXT,
        pages_count INT UNSIGNED NOT NULL DEFAULT 0,
        client_timezone VARCHAR(64) DEFAULT NULL,
        client_lang VARCHAR(16) DEFAULT NULL,
        geo_source VARCHAR(20) DEFAULT NULL,
        UNIQUE KEY uk_session (session_id),
        KEY idx_visitor (visitor_id),
        KEY idx_last_seen (last_seen)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS cw_analytics_pageviews (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(64) NOT NULL,
        visitor_id VARCHAR(64) DEFAULT NULL,
        url TEXT NOT NULL,
        path VARCHAR(500) DEFAULT NULL,
        title VARCHAR(500) DEFAULT NULL,
        viewed_at DATETIME NOT NULL,
        time_on_page INT UNSIGNED NOT NULL DEFAULT 0,
        country VARCHAR(80) DEFAULT NULL,
        region VARCHAR(80) DEFAULT NULL,
        client_timezone VARCHAR(64) DEFAULT NULL,
        KEY idx_session (session_id),
        KEY idx_visitor (visitor_id),
        KEY idx_path (path(191)),
        KEY idx_viewed (viewed_at),
        KEY idx_country (country(32))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS cw_analytics_events (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(64) NOT NULL,
        event_type VARCHAR(50) NOT NULL,
        event_label VARCHAR(200) DEFAULT NULL,
        url TEXT,
        meta JSON DEFAULT NULL,
        created_at DATETIME NOT NULL,
        KEY idx_type (event_type),
        KEY idx_created (created_at),
        KEY idx_session (session_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS cw_lead_actividades (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        lead_id INT NOT NULL,
        tipo ENUM('nota','llamada','mensaje','email','seguimiento','recordatorio') NOT NULL DEFAULT 'nota',
        descripcion TEXT NOT NULL,
        proxima_accion DATETIME DEFAULT NULL,
        usuario_id INT DEFAULT NULL,
        created_at DATETIME NOT NULL,
        KEY idx_lead (lead_id),
        KEY idx_proxima (proxima_accion)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $leadCols = [
        'servicio'           => "VARCHAR(100) DEFAULT NULL",
        'pagina_origen'      => "TEXT DEFAULT NULL",
        'fuente'             => "VARCHAR(120) DEFAULT NULL",
        'utm_source'         => "VARCHAR(120) DEFAULT NULL",
        'utm_medium'         => "VARCHAR(120) DEFAULT NULL",
        'utm_campaign'       => "VARCHAR(120) DEFAULT NULL",
        'session_id'         => "VARCHAR(64) DEFAULT NULL",
        'pipeline_estado'    => "VARCHAR(30) NOT NULL DEFAULT 'nuevo'",
        'responsable_id'     => "INT DEFAULT NULL",
        'ultima_interaccion' => "DATETIME DEFAULT NULL",
        'origen_web'         => "TINYINT(1) NOT NULL DEFAULT 0",
        'apellido'           => "VARCHAR(150) DEFAULT NULL",
        'eliminado'          => "INT NOT NULL DEFAULT 0",
        'web'                => "VARCHAR(8) DEFAULT NULL",
    ];

    $webColJustAdded = false;
    foreach ($leadCols as $col => $def) {
        $chk = $conn->query("SHOW COLUMNS FROM leads LIKE '$col'");
        if ($chk && $chk->num_rows === 0) {
            $conn->query("ALTER TABLE leads ADD COLUMN $col $def");
            if ($col === 'web') {
                $webColJustAdded = true;
            }
        }
    }

    // Backfill una vez al crear la columna web
    if ($webColJustAdded) {
        @$conn->query("UPDATE leads SET web = 'cl' WHERE (web IS NULL OR web = '') AND origen_web = 1 AND pagina_origen LIKE '%conlineweb.cl%'");
        @$conn->query("UPDATE leads SET web = 'us' WHERE (web IS NULL OR web = '') AND origen_web = 1 AND (pagina_origen LIKE '%/us/%' OR pagina_origen LIKE '%conlineweb.com/us%' OR pagina_origen LIKE '%/us?%' OR pagina_origen LIKE '%/us')");
        @$conn->query("UPDATE leads SET web = 'mx' WHERE (web IS NULL OR web = '') AND origen_web = 1 AND pagina_origen LIKE '%conlineweb.com%' AND pagina_origen NOT LIKE '%conlineweb.cl%' AND pagina_origen NOT LIKE '%/us/%' AND pagina_origen NOT LIKE '%conlineweb.com/us%'");
    }

    $elimCol = $conn->query("SHOW COLUMNS FROM leads LIKE 'eliminado'");
    if ($elimCol && ($elimInfo = $elimCol->fetch_assoc())) {
        if ($elimInfo['Default'] === null) {
            $conn->query('ALTER TABLE leads MODIFY COLUMN eliminado INT NOT NULL DEFAULT 0');
        }
    }

    $chkR = $conn->query("SHOW COLUMNS FROM cw_lead_actividades LIKE 'recordatorio_notificado'");
    if ($chkR && $chkR->num_rows === 0) {
        $conn->query('ALTER TABLE cw_lead_actividades ADD COLUMN recordatorio_notificado TINYINT(1) NOT NULL DEFAULT 0');
    }

    $conn->query("CREATE TABLE IF NOT EXISTS cw_lead_adjuntos (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        lead_id INT NOT NULL,
        nombre_original VARCHAR(255) NOT NULL,
        nombre_archivo VARCHAR(255) NOT NULL,
        mime_type VARCHAR(120) DEFAULT NULL,
        tamano INT UNSIGNED NOT NULL DEFAULT 0,
        usuario_id INT DEFAULT NULL,
        created_at DATETIME NOT NULL,
        KEY idx_lead (lead_id),
        KEY idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $chkInbox = $conn->query("SHOW COLUMNS FROM leads LIKE 'inbox_leido_at'");
    if ($chkInbox && $chkInbox->num_rows === 0) {
        $conn->query('ALTER TABLE leads ADD COLUMN inbox_leido_at DATETIME DEFAULT NULL');
    }

    $deviceCols = [
        'screen_w'   => "SMALLINT UNSIGNED DEFAULT NULL",
        'viewport_w' => "SMALLINT UNSIGNED DEFAULT NULL",
        'web'        => "VARCHAR(8) DEFAULT NULL",
    ];
    foreach ($deviceCols as $col => $def) {
        $chkDev = $conn->query("SHOW COLUMNS FROM cw_analytics_sessions LIKE '$col'");
        if ($chkDev && $chkDev->num_rows === 0) {
            $conn->query("ALTER TABLE cw_analytics_sessions ADD COLUMN $col $def");
            if ($col === 'web') {
                @$conn->query("UPDATE cw_analytics_sessions SET web = 'cl' WHERE (web IS NULL OR web = '') AND landing_url LIKE '%conlineweb.cl%'");
                @$conn->query("UPDATE cw_analytics_sessions SET web = 'us' WHERE (web IS NULL OR web = '') AND (landing_url LIKE '%conlineweb.com/us/%' OR landing_url LIKE '%conlineweb.com/us' OR landing_url LIKE '%conlineweb.com/us?%' OR landing_url LIKE '%www.conlineweb.com/us/%' OR landing_url LIKE '%www.conlineweb.com/us' OR landing_url LIKE '%www.conlineweb.com/us?%')");
                @$conn->query("UPDATE cw_analytics_sessions SET web = 'mx' WHERE (web IS NULL OR web = '') AND (landing_url LIKE '%://conlineweb.com%' OR landing_url LIKE '%://www.conlineweb.com%') AND landing_url NOT LIKE '%conlineweb.cl%' AND landing_url NOT LIKE '%/us/%' AND landing_url NOT LIKE '%/us' AND landing_url NOT LIKE '%/us?%'");
            }
        }
    }

    $pvWeb = $conn->query("SHOW COLUMNS FROM cw_analytics_pageviews LIKE 'web'");
    if ($pvWeb && $pvWeb->num_rows === 0) {
        $conn->query("ALTER TABLE cw_analytics_pageviews ADD COLUMN web VARCHAR(8) DEFAULT NULL");
        @$conn->query("UPDATE cw_analytics_pageviews SET web = 'cl' WHERE (web IS NULL OR web = '') AND (url LIKE '%conlineweb.cl%' OR path LIKE '%conlineweb.cl%')");
        @$conn->query("UPDATE cw_analytics_pageviews SET web = 'us' WHERE (web IS NULL OR web = '') AND (url LIKE '%/us/%' OR url LIKE '%/us' OR url LIKE '%/us?%' OR path LIKE '/us/%' OR path = '/us' OR path LIKE '%/us/%')");
        @$conn->query("UPDATE cw_analytics_pageviews SET web = 'mx' WHERE (web IS NULL OR web = '') AND (url LIKE '%://conlineweb.com%' OR url LIKE '%://www.conlineweb.com%') AND url NOT LIKE '%conlineweb.cl%' AND path NOT LIKE '/us%'");
    }

    $hasSessWeb = $conn->query("SHOW COLUMNS FROM cw_analytics_sessions LIKE 'web'");
    $hasPvWeb = $conn->query("SHOW COLUMNS FROM cw_analytics_pageviews LIKE 'web'");
    $GLOBALS['CW_HUB_ANALYTICS_WEB_COL'] = ($hasSessWeb && $hasSessWeb->num_rows > 0)
        || ($hasPvWeb && $hasPvWeb->num_rows > 0);

    $geoCols = [
        'client_timezone' => "VARCHAR(64) DEFAULT NULL",
        'client_lang'     => "VARCHAR(16) DEFAULT NULL",
        'geo_source'      => "VARCHAR(20) DEFAULT NULL",
        'geo_city'        => "VARCHAR(80) DEFAULT NULL",
        'geo_lat'         => "DECIMAL(10,7) DEFAULT NULL",
        'geo_lng'         => "DECIMAL(10,7) DEFAULT NULL",
        'geo_accuracy'    => "SMALLINT UNSIGNED DEFAULT NULL",
        'geo_address'     => "VARCHAR(255) DEFAULT NULL",
    ];
    foreach ($geoCols as $col => $def) {
        $chkGeo = $conn->query("SHOW COLUMNS FROM cw_analytics_sessions LIKE '$col'");
        if ($chkGeo && $chkGeo->num_rows === 0) {
            $conn->query("ALTER TABLE cw_analytics_sessions ADD COLUMN $col $def");
        }
    }

    $chkRegion = $conn->query("SHOW COLUMNS FROM cw_analytics_sessions LIKE 'region'");
    if ($chkRegion && $chkRegion->num_rows > 0) {
        $conn->query("ALTER TABLE cw_analytics_sessions MODIFY COLUMN region VARCHAR(120) DEFAULT NULL");
    }

    $pvCols = [
        'visitor_id'      => "VARCHAR(64) DEFAULT NULL",
        'country'         => "VARCHAR(80) DEFAULT NULL",
        'region'          => "VARCHAR(120) DEFAULT NULL",
        'geo_city'        => "VARCHAR(80) DEFAULT NULL",
        'client_timezone' => "VARCHAR(64) DEFAULT NULL",
    ];
    foreach ($pvCols as $col => $def) {
        $chkPv = $conn->query("SHOW COLUMNS FROM cw_analytics_pageviews LIKE '$col'");
        if ($chkPv && $chkPv->num_rows === 0) {
            $conn->query("ALTER TABLE cw_analytics_pageviews ADD COLUMN $col $def");
        }
    }

    $contactCols = [
        'contact_nombre'   => "VARCHAR(150) DEFAULT NULL",
        'contact_correo'   => "VARCHAR(180) DEFAULT NULL",
        'contact_telefono' => "VARCHAR(30) DEFAULT NULL",
        'lead_id'          => "INT UNSIGNED DEFAULT NULL",
    ];
    foreach ($contactCols as $col => $def) {
        $chkCt = $conn->query("SHOW COLUMNS FROM cw_analytics_sessions LIKE '$col'");
        if ($chkCt && $chkCt->num_rows === 0) {
            $conn->query("ALTER TABLE cw_analytics_sessions ADD COLUMN $col $def");
        }
    }

    $conn->query("CREATE TABLE IF NOT EXISTS cw_analytics_admin_session_views (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(64) NOT NULL,
        admin_uid INT UNSIGNED NOT NULL DEFAULT 0,
        viewed_at DATETIME NOT NULL,
        KEY idx_session (session_id),
        KEY idx_viewed (viewed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS cw_seo_mexico_checklist (
        task_key VARCHAR(80) NOT NULL,
        phase VARCHAR(40) NOT NULL DEFAULT 'general',
        title VARCHAR(255) NOT NULL,
        description TEXT,
        sort_order INT NOT NULL DEFAULT 0,
        done TINYINT(1) NOT NULL DEFAULT 0,
        done_at DATETIME DEFAULT NULL,
        done_by INT UNSIGNED DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        detail_json LONGTEXT DEFAULT NULL,
        updated_at DATETIME DEFAULT NULL,
        PRIMARY KEY (task_key),
        KEY idx_phase (phase),
        KEY idx_done (done),
        KEY idx_sort (sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $chkNotes = $conn->query("SHOW COLUMNS FROM cw_seo_mexico_checklist LIKE 'notes'");
    if ($chkNotes && $chkNotes->num_rows > 0) {
        $conn->query('ALTER TABLE cw_seo_mexico_checklist MODIFY COLUMN notes TEXT DEFAULT NULL');
    }
    $chkDetail = $conn->query("SHOW COLUMNS FROM cw_seo_mexico_checklist LIKE 'detail_json'");
    if ($chkDetail && $chkDetail->num_rows === 0) {
        $conn->query('ALTER TABLE cw_seo_mexico_checklist ADD COLUMN detail_json LONGTEXT DEFAULT NULL AFTER notes');
    }

    $conn->query("CREATE TABLE IF NOT EXISTS cw_seo_mexico_checklist_updates (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        task_key VARCHAR(80) NOT NULL,
        summary VARCHAR(255) NOT NULL,
        detail TEXT,
        created_by INT UNSIGNED DEFAULT NULL,
        created_at DATETIME NOT NULL,
        KEY idx_task (task_key),
        KEY idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Auditoría en vivo → findings → tareas dinámicas / auto-correcciones
    $conn->query("CREATE TABLE IF NOT EXISTS cw_seo_mexico_audit_runs (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        status VARCHAR(24) NOT NULL DEFAULT 'running',
        scope VARCHAR(40) NOT NULL DEFAULT 'mexico_priority',
        urls_total INT NOT NULL DEFAULT 0,
        urls_ok INT NOT NULL DEFAULT 0,
        findings_open INT NOT NULL DEFAULT 0,
        findings_fixed INT NOT NULL DEFAULT 0,
        tasks_created INT NOT NULL DEFAULT 0,
        auto_applied INT NOT NULL DEFAULT 0,
        summary_json LONGTEXT DEFAULT NULL,
        started_by INT UNSIGNED DEFAULT NULL,
        started_at DATETIME NOT NULL,
        finished_at DATETIME DEFAULT NULL,
        KEY idx_status (status),
        KEY idx_started (started_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS cw_seo_mexico_audit_findings (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        run_id BIGINT UNSIGNED NOT NULL,
        finding_key VARCHAR(120) NOT NULL,
        check_type VARCHAR(60) NOT NULL,
        severity VARCHAR(16) NOT NULL DEFAULT 'medium',
        status VARCHAR(24) NOT NULL DEFAULT 'open',
        url VARCHAR(500) NOT NULL DEFAULT '',
        title VARCHAR(255) NOT NULL,
        evidence TEXT,
        correction TEXT,
        auto_fixable TINYINT(1) NOT NULL DEFAULT 0,
        auto_applied TINYINT(1) NOT NULL DEFAULT 0,
        task_key VARCHAR(80) DEFAULT NULL,
        meta_json LONGTEXT DEFAULT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        KEY idx_run (run_id),
        KEY idx_finding_key (finding_key),
        KEY idx_type (check_type),
        KEY idx_status (status),
        KEY idx_task (task_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Scores por URL + salud del sitio (historial para cPanel/cron)
    $conn->query("CREATE TABLE IF NOT EXISTS cw_seo_mexico_audit_url_scores (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        run_id BIGINT UNSIGNED NOT NULL,
        url VARCHAR(500) NOT NULL,
        kind VARCHAR(40) NOT NULL DEFAULT '',
        label VARCHAR(160) NOT NULL DEFAULT '',
        city VARCHAR(120) NOT NULL DEFAULT '',
        score_seo TINYINT UNSIGNED NOT NULL DEFAULT 0,
        score_tech TINYINT UNSIGNED NOT NULL DEFAULT 0,
        score_geo TINYINT UNSIGNED NOT NULL DEFAULT 0,
        score_conv TINYINT UNSIGNED NOT NULL DEFAULT 0,
        score_overall TINYINT UNSIGNED NOT NULL DEFAULT 0,
        http_status SMALLINT NOT NULL DEFAULT 0,
        findings_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        meta_json LONGTEXT DEFAULT NULL,
        created_at DATETIME NOT NULL,
        KEY idx_run (run_id),
        KEY idx_url (url(191)),
        KEY idx_overall (score_overall),
        KEY idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS cw_seo_mexico_audit_site_health (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        run_id BIGINT UNSIGNED NOT NULL,
        source VARCHAR(24) NOT NULL DEFAULT 'manual',
        score_avg TINYINT UNSIGNED NOT NULL DEFAULT 0,
        score_min TINYINT UNSIGNED NOT NULL DEFAULT 0,
        score_seo_avg TINYINT UNSIGNED NOT NULL DEFAULT 0,
        score_tech_avg TINYINT UNSIGNED NOT NULL DEFAULT 0,
        score_geo_avg TINYINT UNSIGNED NOT NULL DEFAULT 0,
        score_conv_avg TINYINT UNSIGNED NOT NULL DEFAULT 0,
        urls_scanned INT NOT NULL DEFAULT 0,
        findings_open INT NOT NULL DEFAULT 0,
        auto_applied INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        UNIQUE KEY uq_run (run_id),
        KEY idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS cw_seo_mexico_autofix_log (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        action VARCHAR(40) NOT NULL,
        rel_path VARCHAR(500) NOT NULL DEFAULT '',
        ok TINYINT(1) NOT NULL DEFAULT 0,
        detail TEXT,
        backup_path VARCHAR(500) DEFAULT NULL,
        finding_key VARCHAR(120) DEFAULT NULL,
        created_by INT UNSIGNED DEFAULT NULL,
        created_at DATETIME NOT NULL,
        KEY idx_created (created_at),
        KEY idx_ok (ok),
        KEY idx_finding (finding_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS cw_seo_mexico_ai_proposals (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // Migración suave si la tabla ya existía sin las columnas nuevas
    $propCols = [];
    $propRes = $conn->query('SHOW COLUMNS FROM cw_seo_mexico_ai_proposals');
    while ($propRes && ($c = $propRes->fetch_assoc())) {
        $propCols[(string) ($c['Field'] ?? '')] = true;
    }
    if (empty($propCols['detail'])) {
        $conn->query('ALTER TABLE cw_seo_mexico_ai_proposals ADD COLUMN detail MEDIUMTEXT NULL AFTER prompt_summary');
    }
    if (empty($propCols['executed_before_json'])) {
        $conn->query('ALTER TABLE cw_seo_mexico_ai_proposals ADD COLUMN executed_before_json LONGTEXT NULL AFTER after_json');
    }
    if (empty($propCols['executed_after_json'])) {
        $conn->query('ALTER TABLE cw_seo_mexico_ai_proposals ADD COLUMN executed_after_json LONGTEXT NULL AFTER executed_before_json');
    }

    $conn->query("CREATE TABLE IF NOT EXISTS cw_seo_mexico_ai_prompt_sessions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL DEFAULT 'Nueva conversación',
        mode VARCHAR(40) NOT NULL DEFAULT 'ask',
        created_by INT UNSIGNED DEFAULT 0,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        KEY idx_updated (updated_at),
        KEY idx_user (created_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS cw_seo_mexico_ai_prompt_messages (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        session_id BIGINT UNSIGNED NOT NULL,
        role VARCHAR(20) NOT NULL,
        content LONGTEXT NOT NULL,
        meta_json LONGTEXT,
        created_at DATETIME NOT NULL,
        KEY idx_session (session_id),
        KEY idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function cw_hub_normalize_device_type(string $type): string
{
    $type = strtolower(trim($type));

    return ($type === 'mobile' || $type === 'tablet') ? 'mobile' : 'desktop';
}

/**
 * Resuelve PC vs celular con señales del navegador + User-Agent real.
 */
function cw_hub_resolve_device_type(string $ua, ?string $clientHint = null, array $signals = []): string
{
    if ($clientHint !== null && in_array($clientHint, ['mobile', 'tablet', 'desktop'], true)) {
        return cw_hub_normalize_device_type($clientHint);
    }

    if (array_key_exists('ua_data_mobile', $signals) && $signals['ua_data_mobile'] !== null && $signals['ua_data_mobile'] !== '') {
        $uaDataMobile = filter_var($signals['ua_data_mobile'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($uaDataMobile === true) {
            return 'mobile';
        }
        if ($uaDataMobile === false) {
            return 'desktop';
        }
    }

    $uaLower = strtolower($ua);
    if (preg_match('/iphone|ipod|android.*mobile|windows phone|iemobile|blackberry|opera mini|webos/', $uaLower)) {
        return 'mobile';
    }
    if (preg_match('/ipad|tablet|playbook|silk|kindle/', $uaLower)) {
        return 'mobile';
    }
    if (preg_match('/android/', $uaLower)) {
        return 'mobile';
    }
    if (preg_match('/macintosh/', $uaLower) && (int) ($signals['touch_points'] ?? 0) > 1) {
        return 'mobile';
    }

    $pointerCoarse = filter_var($signals['pointer_coarse'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $hasFineHover = filter_var($signals['has_fine_hover'] ?? false, FILTER_VALIDATE_BOOLEAN);
    if ($pointerCoarse && !$hasFineHover) {
        return 'mobile';
    }

    $screenW = (int) ($signals['screen_w'] ?? 0);
    $viewportW = (int) ($signals['viewport_w'] ?? 0);
    $width = min($screenW > 0 ? $screenW : $viewportW, $viewportW > 0 ? $viewportW : $screenW);
    $touchPoints = (int) ($signals['touch_points'] ?? 0);
    if ($touchPoints > 0 && $width > 0 && $width <= 820) {
        return 'mobile';
    }

    return 'desktop';
}

function cw_hub_parse_device(string $ua, ?string $clientHint = null, array $signals = []): array
{
    $device = cw_hub_resolve_device_type($ua, $clientHint, $signals);
    $uaLower = strtolower($ua);

    $browser = 'other';
    foreach (['edg' => 'Edge', 'chrome' => 'Chrome', 'firefox' => 'Firefox', 'safari' => 'Safari', 'opr' => 'Opera'] as $k => $v) {
        if (str_contains($uaLower, $k)) {
            $browser = $v;
            break;
        }
    }

    $os = 'other';
    foreach (['windows' => 'Windows', 'mac os' => 'macOS', 'android' => 'Android', 'iphone' => 'iOS', 'ipad' => 'iPadOS', 'linux' => 'Linux'] as $k => $v) {
        if (str_contains($uaLower, $k)) {
            $os = $v;
            break;
        }
    }

    return ['device' => $device, 'browser' => $browser, 'os' => $os];
}
