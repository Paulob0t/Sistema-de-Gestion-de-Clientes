<?php
/**
 * Tablas del módulo Mailing (plantillas, envíos, eventos de tracking).
 */
declare(strict_types=1);

function cw_mailing_migrate(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $conn->query("CREATE TABLE IF NOT EXISTS cw_mailing_templates (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(80) NOT NULL DEFAULT '',
        title VARCHAR(180) NOT NULL,
        theme VARCHAR(80) NOT NULL DEFAULT 'general',
        subject VARCHAR(255) NOT NULL,
        preheader VARCHAR(255) NOT NULL DEFAULT '',
        body_html MEDIUMTEXT NOT NULL,
        image_url VARCHAR(500) NOT NULL DEFAULT '',
        cta_label VARCHAR(120) NOT NULL DEFAULT '',
        cta_url VARCHAR(500) NOT NULL DEFAULT '',
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        UNIQUE KEY uq_mailing_tpl_slug (slug),
        KEY idx_mailing_tpl_active (active),
        KEY idx_mailing_tpl_theme (theme)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS cw_mailing_sends (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        token CHAR(40) NOT NULL,
        template_id INT UNSIGNED NULL,
        template_title VARCHAR(180) NOT NULL DEFAULT '',
        audience ENUM('cliente','lead') NOT NULL,
        audience_id INT UNSIGNED NOT NULL DEFAULT 0,
        email VARCHAR(190) NOT NULL,
        name VARCHAR(180) NOT NULL DEFAULT '',
        company VARCHAR(180) NOT NULL DEFAULT '',
        subject VARCHAR(255) NOT NULL DEFAULT '',
        status ENUM('queued','sent','failed','unsubscribed') NOT NULL DEFAULT 'queued',
        error_message VARCHAR(500) NOT NULL DEFAULT '',
        opened_at DATETIME NULL,
        open_count INT UNSIGNED NOT NULL DEFAULT 0,
        click_count INT UNSIGNED NOT NULL DEFAULT 0,
        last_click_at DATETIME NULL,
        last_click_url VARCHAR(500) NOT NULL DEFAULT '',
        unsubscribed_at DATETIME NULL,
        sent_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_by INT UNSIGNED NULL,
        UNIQUE KEY uq_mailing_send_token (token),
        KEY idx_mailing_send_tpl (template_id),
        KEY idx_mailing_send_audience (audience, audience_id),
        KEY idx_mailing_send_email (email),
        KEY idx_mailing_send_status (status),
        KEY idx_mailing_send_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS cw_mailing_events (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        send_id BIGINT UNSIGNED NOT NULL,
        event_type ENUM('sent','open','click','unsub','fail') NOT NULL,
        url VARCHAR(500) NOT NULL DEFAULT '',
        ip VARCHAR(45) NOT NULL DEFAULT '',
        user_agent VARCHAR(255) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_mailing_ev_send (send_id),
        KEY idx_mailing_ev_type (event_type),
        KEY idx_mailing_ev_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS cw_mailing_jobs (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        audience ENUM('cliente','lead') NOT NULL,
        template_id_1 INT UNSIGNED NOT NULL,
        template_id_2 INT UNSIGNED NULL,
        mode ENUM('now','once','weekly') NOT NULL DEFAULT 'now',
        scheduled_at DATETIME NULL,
        weekdays VARCHAR(32) NOT NULL DEFAULT '',
        send_time TIME NULL,
        template2_delay_hours INT UNSIGNED NOT NULL DEFAULT 24,
        recipient_count INT UNSIGNED NOT NULL DEFAULT 0,
        queue_count INT UNSIGNED NOT NULL DEFAULT 0,
        status ENUM('pending','done','cancelled') NOT NULL DEFAULT 'pending',
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_mailing_job_status (status),
        KEY idx_mailing_job_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS cw_mailing_queue (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        job_id BIGINT UNSIGNED NOT NULL,
        template_id INT UNSIGNED NOT NULL,
        audience ENUM('cliente','lead') NOT NULL,
        audience_id INT UNSIGNED NOT NULL,
        email VARCHAR(190) NOT NULL,
        name VARCHAR(180) NOT NULL DEFAULT '',
        company VARCHAR(180) NOT NULL DEFAULT '',
        phone VARCHAR(60) NOT NULL DEFAULT '',
        send_at DATETIME NOT NULL,
        status ENUM('pending','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
        send_id BIGINT UNSIGNED NULL,
        error_message VARCHAR(500) NOT NULL DEFAULT '',
        processed_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_mailing_q_due (status, send_at),
        KEY idx_mailing_q_job (job_id),
        KEY idx_mailing_q_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    cw_mailing_seed_templates($conn);
}

function cw_mailing_seed_templates(mysqli $conn): void
{
    $res = $conn->query('SELECT COUNT(*) AS c FROM cw_mailing_templates');
    $row = $res ? $res->fetch_assoc() : null;
    if ((int) ($row['c'] ?? 0) > 0) {
        return;
    }

    $seeds = [
        [
            'slug' => 'promo-desarrollo-web',
            'title' => 'Desarrollo web profesional',
            'theme' => 'servicios',
            'subject' => '{nombre}, impulsamos la web de {empresa}',
            'preheader' => 'Sitios corporativos, e-commerce y software a medida',
            'body_html' => "<p>Hola <strong>{nombre}</strong>,</p>\n"
                . "<p>En ConlineWeb diseñamos y desarrollamos sitios web, tiendas en línea y sistemas a medida para empresas en México.</p>\n"
                . "<p>Si {empresa} busca presencia digital más clara, rápida y orientada a resultados, podemos ayudarte con una propuesta sin compromiso.</p>\n"
                . "<p>WhatsApp: 477 118 1285 · <a href=\"https://conlineweb.com/\">conlineweb.com</a></p>",
            'cta_label' => 'Ver servicios',
            'cta_url' => 'https://conlineweb.com/agencia-de-desarrollo-web-mx.php',
        ],
        [
            'slug' => 'promo-hosting',
            'title' => 'Hosting cPanel confiable',
            'theme' => 'hosting',
            'subject' => 'Hosting profesional para {empresa}',
            'preheader' => 'SSD, seguridad y soporte cuando lo necesitas',
            'body_html' => "<p>Hola <strong>{nombre}</strong>,</p>\n"
                . "<p>Te compartimos nuestra opción de hosting cPanel con almacenamiento SSD, respaldos y soporte cercano.</p>\n"
                . "<p>Ideal si {empresa} quiere estabilidad sin complicaciones técnicas.</p>",
            'cta_label' => 'Ver planes de hosting',
            'cta_url' => 'https://conlineweb.com/hosting-administrado/',
        ],
        [
            'slug' => 'seguimiento-lead',
            'title' => 'Seguimiento a tu solicitud',
            'theme' => 'leads',
            'subject' => 'Seguimos pendientes de tu solicitud, {nombre}',
            'preheader' => 'ConlineWeb · respuesta personalizada',
            'body_html' => "<p>Hola <strong>{nombre}</strong>,</p>\n"
                . "<p>Recibimos tu interés y queremos ayudarte a avanzar. Cuéntanos un poco más del objetivo de {empresa} y te orientamos con la mejor opción.</p>\n"
                . "<p>También puedes agendar una asesoría breve sin costo.</p>",
            'cta_label' => 'Agendar asesoría',
            'cta_url' => 'https://conlineweb.com/asesoria-web-gratuita.php',
        ],
    ];

    $stmt = $conn->prepare(
        'INSERT INTO cw_mailing_templates
        (slug, title, theme, subject, preheader, body_html, image_url, cta_label, cta_url, active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)'
    );
    if (!$stmt) {
        return;
    }
    foreach ($seeds as $s) {
        $img = '';
        $stmt->bind_param(
            'sssssssss',
            $s['slug'],
            $s['title'],
            $s['theme'],
            $s['subject'],
            $s['preheader'],
            $s['body_html'],
            $img,
            $s['cta_label'],
            $s['cta_url']
        );
        $stmt->execute();
    }
    $stmt->close();
}
