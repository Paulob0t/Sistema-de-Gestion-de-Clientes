<?php
declare(strict_types=1);

/**
 * Migración tablas proyec_* — levantamiento de requerimientos.
 * Copia admin (autocontenida, no depende de conlineweb.com/).
 */
function proyec_migrate(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $conn->query("CREATE TABLE IF NOT EXISTS proyec_proyectos (
        project_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        lead_id INT NOT NULL,
        nombre_proyecto VARCHAR(255) DEFAULT NULL,
        giro VARCHAR(150) DEFAULT NULL,
        pagina_web VARCHAR(500) DEFAULT NULL,
        objetivo_proyecto TEXT DEFAULT NULL,
        estado ENUM('borrador','enviado','revision','cerrado') NOT NULL DEFAULT 'borrador',
        paso_actual TINYINT UNSIGNED NOT NULL DEFAULT 1,
        progreso_pct TINYINT UNSIGNED NOT NULL DEFAULT 0,
        datos JSON NOT NULL,
        fecha_creacion DATETIME NOT NULL,
        fecha_actualizacion DATETIME NOT NULL,
        fecha_envio DATETIME DEFAULT NULL,
        KEY idx_lead (lead_id),
        KEY idx_estado (estado),
        KEY idx_actualizado (fecha_actualizacion)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS proyec_historial (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        project_id INT UNSIGNED NOT NULL,
        lead_id INT NOT NULL,
        paso TINYINT UNSIGNED NOT NULL DEFAULT 0,
        accion VARCHAR(40) NOT NULL DEFAULT 'autosave',
        resumen VARCHAR(255) DEFAULT NULL,
        created_at DATETIME NOT NULL,
        KEY idx_project (project_id),
        KEY idx_lead (lead_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS proyec_form_acceso (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        lead_id INT NOT NULL,
        token VARCHAR(64) NOT NULL,
        estado ENUM('activo','expirado','revocado') NOT NULL DEFAULT 'activo',
        fecha_creacion DATETIME NOT NULL,
        fecha_expiracion DATETIME NOT NULL,
        usuario_id INT DEFAULT NULL,
        ultimo_uso DATETIME DEFAULT NULL,
        UNIQUE KEY uq_token (token),
        KEY idx_lead (lead_id),
        KEY idx_estado (estado),
        KEY idx_expira (fecha_expiracion)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS proyec_form_bitacora (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        lead_id INT NOT NULL,
        acceso_id INT UNSIGNED DEFAULT NULL,
        usuario_id INT DEFAULT NULL,
        canal ENUM('email','enlace','whatsapp','copiar') NOT NULL,
        estado ENUM('ok','error') NOT NULL DEFAULT 'ok',
        detalle VARCHAR(500) DEFAULT NULL,
        created_at DATETIME NOT NULL,
        KEY idx_lead (lead_id),
        KEY idx_canal (canal),
        KEY idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS proyec_admin_visto (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        lead_id INT NOT NULL,
        usuario_id INT NOT NULL DEFAULT 0,
        visto_en DATETIME NOT NULL,
        UNIQUE KEY uq_lead_usuario (lead_id, usuario_id),
        KEY idx_usuario (usuario_id),
        KEY idx_visto (visto_en)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
