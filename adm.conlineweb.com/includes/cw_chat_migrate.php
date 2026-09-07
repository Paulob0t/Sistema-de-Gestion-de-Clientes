<?php
/**
 * Auto-migración tablas Chat Inteligente (Dashboard + CRM)
 */
function cw_chat_ensure_soft_delete_columns(mysqli $conn): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $ok = false;
    if (function_exists('mysqli_report')) {
        mysqli_report(MYSQLI_REPORT_OFF);
    }

    try {
        $tableCheck = $conn->query("SHOW TABLES LIKE 'cw_chat_conversaciones'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            return;
        }

        $cols = [
            'eliminado' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'eliminado_at' => 'DATETIME DEFAULT NULL',
        ];
        foreach ($cols as $col => $def) {
            $chk = $conn->query("SHOW COLUMNS FROM cw_chat_conversaciones LIKE '" . $conn->real_escape_string($col) . "'");
            if ($chk && $chk->num_rows === 0) {
                $conn->query("ALTER TABLE cw_chat_conversaciones ADD COLUMN `$col` $def");
            }
        }

        $idxElim = $conn->query("SHOW INDEX FROM cw_chat_conversaciones WHERE Key_name = 'idx_eliminado'");
        if ($idxElim && $idxElim->num_rows === 0) {
            $conn->query('ALTER TABLE cw_chat_conversaciones ADD INDEX idx_eliminado (eliminado)');
        }

        $verify = $conn->query("SHOW COLUMNS FROM cw_chat_conversaciones LIKE 'eliminado'");
        $ok = $verify && $verify->num_rows > 0;
    } finally {
        if (function_exists('mysqli_report')) {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        }
        if ($ok) {
            $ensured = true;
        }
    }
}

function cw_chat_migrate(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }

    // Garantiza columnas de baja aunque schema_version ya esté al día.
    cw_chat_ensure_soft_delete_columns($conn);

    $schemaVersion = 12;
    $tableCheck = @$conn->query("SHOW TABLES LIKE 'cw_chat_config'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $verRes = @$conn->query("SELECT valor FROM cw_chat_config WHERE clave = 'schema_version' LIMIT 1");
        if ($verRes && ($verRow = $verRes->fetch_assoc()) && (int) ($verRow['valor'] ?? 0) >= $schemaVersion) {
            $done = true;
            return;
        }
    }

    if (function_exists('cw_hub_db_timezone')) {
        cw_hub_db_timezone($conn);
    }

    $conn->query("CREATE TABLE IF NOT EXISTS cw_chat_conversaciones (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        uuid VARCHAR(36) NOT NULL,
        cliente_id INT DEFAULT NULL,
        lead_id INT DEFAULT NULL,
        canal ENUM('dashboard','web','whatsapp') NOT NULL DEFAULT 'dashboard',
        estado ENUM('activa','bot','pendiente_humano','humano','cerrada') NOT NULL DEFAULT 'bot',
        prioridad ENUM('baja','media','alta','urgente') NOT NULL DEFAULT 'media',
        responsable_id INT DEFAULT NULL,
        etiquetas JSON DEFAULT NULL,
        motivo_escalamiento TEXT DEFAULT NULL,
        titulo VARCHAR(255) DEFAULT NULL,
        ultimo_mensaje_preview VARCHAR(500) DEFAULT NULL,
        ultimo_mensaje_at DATETIME DEFAULT NULL,
        ultimo_mensaje_remitente ENUM('cliente','bot','agente','sistema') DEFAULT NULL,
        sin_respuesta_desde DATETIME DEFAULT NULL,
        leido_admin_at DATETIME DEFAULT NULL,
        iniciada_at DATETIME NOT NULL,
        cerrada_at DATETIME DEFAULT NULL,
        satisfaccion TINYINT UNSIGNED DEFAULT NULL,
        metadata JSON DEFAULT NULL,
        UNIQUE KEY uk_uuid (uuid),
        KEY idx_cliente (cliente_id),
        KEY idx_lead (lead_id),
        KEY idx_estado (estado),
        KEY idx_ultimo (ultimo_mensaje_at),
        KEY idx_responsable (responsable_id),
        KEY idx_sin_respuesta (sin_respuesta_desde)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS cw_chat_mensajes (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        conversacion_id INT UNSIGNED NOT NULL,
        remitente_tipo ENUM('cliente','bot','agente','sistema') NOT NULL,
        remitente_id INT DEFAULT NULL,
        contenido TEXT NOT NULL,
        tipo ENUM('texto','sistema','archivo') NOT NULL DEFAULT 'texto',
        metadata JSON DEFAULT NULL,
        leido_at DATETIME DEFAULT NULL,
        enviado_at DATETIME NOT NULL,
        KEY idx_conv (conversacion_id),
        KEY idx_enviado (enviado_at),
        KEY idx_remitente (remitente_tipo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS cw_chat_categorias (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(120) NOT NULL,
        slug VARCHAR(120) NOT NULL,
        descripcion TEXT DEFAULT NULL,
        icono VARCHAR(60) DEFAULT 'bi-chat-dots',
        orden INT NOT NULL DEFAULT 0,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_slug (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS cw_chat_conocimiento (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        categoria_id INT UNSIGNED DEFAULT NULL,
        titulo VARCHAR(255) NOT NULL,
        pregunta TEXT NOT NULL,
        respuesta TEXT NOT NULL,
        palabras_clave TEXT DEFAULT NULL,
        intencion VARCHAR(120) DEFAULT NULL,
        tipo_cliente ENUM('todos','nuevo','activo','moroso') NOT NULL DEFAULT 'todos',
        servicio VARCHAR(80) DEFAULT NULL,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        prioridad INT NOT NULL DEFAULT 0,
        veces_usada INT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        updated_at DATETIME DEFAULT NULL,
        KEY idx_categoria (categoria_id),
        KEY idx_activo (activo),
        KEY idx_intencion (intencion),
        KEY idx_prioridad (prioridad)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS cw_chat_flujos (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(120) NOT NULL,
        trigger_keywords TEXT NOT NULL,
        pasos JSON NOT NULL,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        orden INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        updated_at DATETIME DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS cw_chat_preguntas_sin_respuesta (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        conversacion_id INT UNSIGNED DEFAULT NULL,
        mensaje TEXT NOT NULL,
        intent_detectado VARCHAR(120) DEFAULT NULL,
        veces INT UNSIGNED NOT NULL DEFAULT 1,
        resuelta TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        updated_at DATETIME DEFAULT NULL,
        KEY idx_resuelta (resuelta),
        KEY idx_conv (conversacion_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS cw_chat_config (
        clave VARCHAR(80) NOT NULL PRIMARY KEY,
        valor TEXT NOT NULL,
        updated_at DATETIME DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS cw_chat_asignaciones (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        conversacion_id INT UNSIGNED NOT NULL,
        agente_id INT NOT NULL,
        asignado_at DATETIME NOT NULL,
        notas TEXT DEFAULT NULL,
        KEY idx_conv (conversacion_id),
        KEY idx_agente (agente_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS cw_chat_agentes_estado (
        agente_id INT NOT NULL PRIMARY KEY,
        estado ENUM('online','offline','away') NOT NULL DEFAULT 'offline',
        ultima_conexion DATETIME DEFAULT NULL,
        mensaje_estado VARCHAR(255) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS cw_chat_estadisticas_diarias (
        fecha DATE NOT NULL PRIMARY KEY,
        conversaciones_nuevas INT UNSIGNED NOT NULL DEFAULT 0,
        conversaciones_activas INT UNSIGNED NOT NULL DEFAULT 0,
        conversaciones_cerradas INT UNSIGNED NOT NULL DEFAULT 0,
        conversaciones_escaladas INT UNSIGNED NOT NULL DEFAULT 0,
        mensajes_cliente INT UNSIGNED NOT NULL DEFAULT 0,
        mensajes_bot INT UNSIGNED NOT NULL DEFAULT 0,
        mensajes_agente INT UNSIGNED NOT NULL DEFAULT 0,
        tiempo_respuesta_promedio INT UNSIGNED DEFAULT NULL,
        preguntas_sin_respuesta INT UNSIGNED NOT NULL DEFAULT 0,
        updated_at DATETIME DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $rtCols = [
        'realtime_version'   => 'INT UNSIGNED NOT NULL DEFAULT 0',
        'cliente_typing_at'  => 'DATETIME DEFAULT NULL',
        'agente_typing_at'   => 'DATETIME DEFAULT NULL',
        'cliente_online_at'  => 'DATETIME DEFAULT NULL',
        'leido_cliente_at'   => 'DATETIME DEFAULT NULL',
        'eliminado'          => 'TINYINT(1) NOT NULL DEFAULT 0',
        'eliminado_at'       => 'DATETIME DEFAULT NULL',
    ];
    foreach ($rtCols as $col => $def) {
        $chk = $conn->query("SHOW COLUMNS FROM cw_chat_conversaciones LIKE '$col'");
        if ($chk && $chk->num_rows === 0) {
            $conn->query("ALTER TABLE cw_chat_conversaciones ADD COLUMN $col $def");
        }
    }

    $idxElim = $conn->query("SHOW INDEX FROM cw_chat_conversaciones WHERE Key_name = 'idx_eliminado'");
    if ($idxElim && $idxElim->num_rows === 0) {
        $conn->query('ALTER TABLE cw_chat_conversaciones ADD INDEX idx_eliminado (eliminado)');
    }

    $conn->query("CREATE TABLE IF NOT EXISTS cw_chat_alertas_pendientes (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        conversacion_id INT UNSIGNED NOT NULL,
        ultimo_mensaje_id BIGINT UNSIGNED NOT NULL,
        programada_at DATETIME NOT NULL,
        estado ENUM('pendiente','enviada','cancelada') NOT NULL DEFAULT 'pendiente',
        cancel_motivo VARCHAR(40) DEFAULT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME DEFAULT NULL,
        enviada_at DATETIME DEFAULT NULL,
        KEY idx_conv_estado (conversacion_id, estado),
        KEY idx_programada (programada_at, estado)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $idxMsg = $conn->query("SHOW INDEX FROM cw_chat_mensajes WHERE Key_name = 'idx_conv_id'");
    if (!$idxMsg || $idxMsg->num_rows === 0) {
        $conn->query('ALTER TABLE cw_chat_mensajes ADD INDEX idx_conv_id (conversacion_id, id)');
    }

    $idxEstUlt = $conn->query("SHOW INDEX FROM cw_chat_conversaciones WHERE Key_name = 'idx_estado_ultimo'");
    if (!$idxEstUlt || $idxEstUlt->num_rows === 0) {
        $conn->query('ALTER TABLE cw_chat_conversaciones ADD INDEX idx_estado_ultimo (estado, ultimo_mensaje_at)');
    }

    cw_chat_seed_defaults($conn);
    cw_chat_seed_extended($conn);
    if (!function_exists('cw_chat_seed_pro')) {
        require_once __DIR__ . '/cw_chat_seed_pro.php';
    }
    cw_chat_seed_pro($conn);
    if (!function_exists('cw_chat_seed_cortesia_docs')) {
        require_once __DIR__ . '/cw_chat_seed_cortesia_docs.php';
    }
    cw_chat_seed_cortesia_docs($conn);
    cw_chat_seed_cortesia_config($conn);

    $conn->query("UPDATE cw_chat_config SET valor = '5'
        WHERE clave = 'alerta_sin_respuesta_minutos' AND valor IN ('30','')");

    $sv = (string) $schemaVersion;
    $svStmt = $conn->prepare('INSERT INTO cw_chat_config (clave, valor, updated_at) VALUES ("schema_version", ?, NOW())
        ON DUPLICATE KEY UPDATE valor = VALUES(valor), updated_at = NOW()');
    if ($svStmt) {
        $svStmt->bind_param('s', $sv);
        $svStmt->execute();
        $svStmt->close();
    }

    $done = true;
}

function cw_chat_seed_defaults(mysqli $conn): void
{
    $now = date('Y-m-d H:i:s');

    $defaults = [
        'saludo_inicial' => "¡Hola! Soy el **asistente de ConlineWeb**.\n\nEstoy aquí para ayudarte con hosting, dominios, pagos, tickets y tu panel. ¿En qué te puedo apoyar hoy?",
        'mensaje_espera' => 'Un momento, estoy revisando tu solicitud…',
        'mensaje_fuera_horario' => 'Nuestro horario de atención humana es de lunes a viernes de 9:00 a 18:00 (hora CDMX). Con gusto te ayudo ahora con lo que pueda, o deja tu mensaje y un asesor te contactará.',
        'mensaje_escalamiento' => 'Voy a avisar a un asesor para que revise tu caso. Mientras tanto sigo aquí contigo: puedes seguir preguntándome sobre hosting, dominios, pagos o tu cuenta.',
        'mensaje_whatsapp' => 'Si tienes dudas, escribe por WhatsApp.',
        'whatsapp_numero' => '524771181285',
        'horario_inicio' => '09:00',
        'horario_fin' => '18:00',
        'dias_laborales' => '1,2,3,4,5',
        'alerta_sin_respuesta_minutos' => '5',
        'alerta_email_activa' => '1',
        'bot_nombre' => 'Asistente ConlineWeb',
        'email_soporte' => 'servicios@conlineweb.com',
        'mensaje_aclaracion' => 'Con gusto te ayudo. ¿Te refieres a «{titulo}»? Si es así, cuéntame un poco más y lo resolvemos.',
        'mensaje_sin_respuesta' => "No pude resolver eso con certeza desde aquí.\n\nPuedes conversar con un agente. ¿Te gustaría conversar con uno o no?",
    ];

    foreach ($defaults as $clave => $valor) {
        $stmt = $conn->prepare('INSERT IGNORE INTO cw_chat_config (clave, valor, updated_at) VALUES (?, ?, ?)');
        if ($stmt) {
            $stmt->bind_param('sss', $clave, $valor, $now);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Reparar configs vacías que impedirían que el bot responda
    $claveFix = 'mensaje_sin_respuesta';
    $valorFix = $defaults[$claveFix];
    $fix = $conn->prepare('UPDATE cw_chat_config SET valor = ?, updated_at = ? WHERE clave = ? AND (valor IS NULL OR valor = "")');
    if ($fix) {
        $fix->bind_param('sss', $valorFix, $now, $claveFix);
        $fix->execute();
        $fix->close();
        $GLOBALS['cw_chat_config_cache'] = null;
    }

    $chk = $conn->query('SELECT COUNT(*) c FROM cw_chat_categorias');
    $count = $chk ? (int) ($chk->fetch_assoc()['c'] ?? 0) : 0;
    if ($count > 0) {
        return;
    }

    $categorias = [
        ['General', 'general', 'Preguntas generales del panel', 'bi-house', 1],
        ['Hosting', 'hosting', 'Planes, espacio, renovaciones', 'bi-hdd-stack', 2],
        ['Dominios', 'dominios', 'Registro, DNS, transferencias', 'bi-globe2', 3],
        ['Pagos', 'pagos', 'Facturación y métodos de pago', 'bi-credit-card', 4],
        ['Tickets', 'tickets', 'Soporte y solicitudes', 'bi-ticket-detailed', 5],
        ['Correo y SSL', 'correo-ssl', 'Correos corporativos y certificados', 'bi-envelope-check', 6],
        ['WordPress y Web', 'wordpress', 'Sitios web y CMS', 'bi-wordpress', 7],
    ];

    $catIds = [];
    foreach ($categorias as $cat) {
        $stmt = $conn->prepare('INSERT INTO cw_chat_categorias (nombre, slug, descripcion, icono, orden, activo, created_at) VALUES (?,?,?,?,?,1,?)');
        if ($stmt) {
            $stmt->bind_param('ssssis', $cat[0], $cat[1], $cat[2], $cat[3], $cat[4], $now);
            $stmt->execute();
            $catIds[$cat[1]] = (int) $conn->insert_id;
            $stmt->close();
        }
    }

    $faqs = [
        ['general', 'panel_cliente', '¿Qué es el panel del cliente?', 'panel cliente dashboard portal', 'El panel del cliente de ConlineWeb es tu centro de control para administrar hosting, dominios, pagos, tickets y sitios web. Desde aquí puedes consultar servicios, realizar pagos y abrir solicitudes de soporte.'],
        ['general', 'acceso_panel', '¿Cómo accedo al panel?', 'acceso ingresar login contraseña', "Para entrar al **Portal de Clientes** ve a **cliente.conlineweb.com** con tu usuario y contraseña.\n\nSi olvidaste la contraseña, usa «Recuperar / Restablecer contraseña» en la pantalla de ingreso. ¿Quieres que te guíe paso a paso?"],
        ['hosting', 'que_es_hosting', '¿Qué es el hosting?', 'hosting alojamiento servidor espacio', 'El hosting es el espacio en nuestros servidores donde vive tu sitio web, correos y archivos. En el panel puedes ver tu plan, espacio usado, fecha de renovación y datos de acceso.'],
        ['hosting', 'renovar_hosting', '¿Cómo renuevo mi hosting?', 'renovar renovación vencimiento hosting', 'Ve a la sección «Mis pagos» en el panel. Allí verás los pagos pendientes de hosting. Selecciona el servicio y completa el pago en línea con tarjeta.'],
        ['hosting', 'cpanel_ftp', '¿Cómo accedo a cPanel o FTP?', 'cpanel ftp acceso archivos servidor', 'En la sección «Hosting» de tu panel encontrarás los datos de acceso a cPanel y FTP. Si necesitas restablecer contraseñas, abre un ticket de soporte.'],
        ['dominios', 'ver_dominios', '¿Dónde veo mis dominios?', 'dominios lista mis dominios', 'En el menú lateral selecciona «Dominios». Verás todos tus dominios registrados, fechas de vencimiento y opciones de DNS.'],
        ['dominios', 'dns_dominio', '¿Cómo configuro el DNS?', 'dns registros nameserver apuntar', 'Entra a «Dominios», selecciona tu dominio y usa la sección DNS para editar registros A, CNAME, MX, etc. Los cambios pueden tardar hasta 24 horas en propagarse.'],
        ['dominios', 'transferir_dominio', '¿Cómo transfiero un dominio?', 'transferencia codigo auth epp', 'En «Dominios» puedes solicitar el código de transferencia. Con ese código podrás mover tu dominio a otro proveedor o traerlo a ConlineWeb.'],
        ['pagos', 'ver_pagos', '¿Dónde veo mis pagos?', 'pagos facturas pendientes pagado', 'La sección «Mis pagos» muestra pagos pendientes, historial y comprobantes. Puedes pagar individualmente o usar pago múltiple.'],
        ['pagos', 'metodos_pago', '¿Qué métodos de pago aceptan?', 'pago tarjeta stripe transferencia', 'Aceptamos pago con tarjeta de crédito/débito mediante Stripe desde el panel. Si necesitas otra forma de pago, escríbenos a servicios@conlineweb.com.'],
        ['pagos', 'factura', '¿Cómo obtengo mi factura?', 'factura comprobante fiscal cfdi', 'Después de pagar, el comprobante está disponible en «Mis pagos». Para factura fiscal (CFDI), abre un ticket indicando tus datos fiscales.'],
        ['tickets', 'crear_ticket', '¿Cómo creo un ticket de soporte?', 'ticket soporte solicitud ayuda', 'Ve a «Tickets» y pulsa «Crear ticket». Describe tu problema con el mayor detalle posible y, si aplica, adjunta capturas. Recibirás seguimiento por correo.'],
        ['tickets', 'estado_ticket', '¿Cómo consulto el estado de mi ticket?', 'estado ticket seguimiento avance', 'En «Tickets» verás el listado con estado (Pendiente, En Proceso, Finalizado). Haz clic en «Ver» para ver historial y comentarios.'],
        ['correo-ssl', 'correo_corporativo', '¿Cómo configuro mi correo corporativo?', 'correo email outlook thunderbird imap smtp', 'Los datos IMAP/SMTP están en tu sección de hosting o en la documentación enviada al contratar. Si tienes dudas de configuración, abre un ticket.'],
        ['correo-ssl', 'ssl_certificado', '¿Mi sitio tiene SSL?', 'ssl https certificado seguridad candado', 'La mayoría de nuestros planes incluyen SSL. Si ves advertencias de seguridad en tu sitio, abre un ticket para que revisemos la instalación del certificado.'],
        ['wordpress', 'sitio_wordpress', '¿Cómo administro mi WordPress?', 'wordpress wp admin panel sitio web', 'Accede a tudominio.com/wp-admin con tu usuario WordPress. Para cambios técnicos en servidor, plugins o temas, puedes solicitarlo vía ticket.'],
        ['general', 'horario_soporte', '¿Cuál es el horario de soporte?', 'horario atención horas soporte', 'Atención humana: lunes a viernes 9:00–18:00 (CDMX). Fuera de horario el asistente virtual puede ayudarte y un asesor te contactará al siguiente día hábil.'],
        ['general', 'whatsapp_soporte', '¿Tienen WhatsApp de soporte?', 'whatsapp contacto telefono', 'Sí. WhatsApp de soporte: **+52 477 118 1285**. Lo ideal es registrar tu ticket en **Mis Tickets** para seguimiento; usa WhatsApp si tienes dudas. También puedes escribir «quiero un asesor» en este chat.'],
    ];

    foreach ($faqs as $faq) {
        $catId = $catIds[$faq[0]] ?? null;
        $stmt = $conn->prepare('INSERT INTO cw_chat_conocimiento
            (categoria_id, titulo, pregunta, respuesta, palabras_clave, intencion, tipo_cliente, activo, prioridad, created_at)
            VALUES (?,?,?,?,?,?,"todos",1,10,?)');
        if ($stmt) {
            $stmt->bind_param('issssss', $catId, $faq[2], $faq[2], $faq[4], $faq[3], $faq[1], $now);
            $stmt->execute();
            $stmt->close();
        }
    }
}

function cw_chat_seed_extended(mysqli $conn): void
{
    $now = date('Y-m-d H:i:s');
    $catIds = [];
    $cr = $conn->query('SELECT id, slug FROM cw_chat_categorias');
    if ($cr) {
        while ($row = $cr->fetch_assoc()) {
            $catIds[$row['slug']] = (int) $row['id'];
        }
    }

    $extra = [
        ['general', 'mis_sitios', '¿Dónde veo mis sitios web?', 'mis sitios web index sitios', 'En el menú lateral abre «Mis sitios web» (index.php). Ahí verás tus dominios con acceso a WordPress, cPanel y WHM según tu plan.'],
        ['general', 'mi_cuenta', '¿Cómo actualizo mis datos?', 'mi cuenta datos perfil empresa', 'Ve a «Mi cuenta» en el menú lateral para consultar y actualizar tu información de contacto y empresa.'],
        ['general', 'recuperar_password', 'Olvidé mi contraseña', 'recuperar contraseña olvide password reset', "Sin problema. En **cliente.conlineweb.com**, en la pantalla de ingreso, usa «Recuperar / Restablecer contraseña».\n\nTe llegará un enlace al correo registrado en tu cuenta. Si no lo ves, revisa spam o espera unos minutos.\n\n¿Pudiste iniciar el restablecimiento o necesitas que te oriente con otra cosa?"],
        ['hosting', 'espacio_disco', '¿Cuánto espacio tengo?', 'espacio disco almacenamiento lleno', 'En «Hosting» puedes ver el plan contratado y el espacio disponible. Si necesitas ampliar tu plan, abre un ticket o contáctanos.'],
        ['hosting', 'vencimiento_hosting', '¿Cuándo vence mi hosting?', 'vence vencimiento fecha hosting', 'La fecha de renovación aparece en «Hosting» y en «Mis pagos» si hay un cobro pendiente. Te recomendamos pagar antes del vencimiento.'],
        ['dominios', 'vencimiento_dominio', '¿Cuándo vence mi dominio?', 'vence dominio renovar dominio', 'En «Dominios» verás la fecha de vencimiento de cada dominio. Renueva a tiempo para evitar perder el dominio.'],
        ['pagos', 'pago_multiple', '¿Puedo pagar varios servicios juntos?', 'pago multiple varios servicios', 'Sí. En «Mis pagos» activa la opción de pago múltiple, selecciona los servicios pendientes y paga en una sola transacción.'],
        ['pagos', 'comprobante_pago', '¿Dónde descargo mi comprobante?', 'comprobante recibo descargar pagado', 'En «Mis pagos», pestaña de pagados, usa el botón de comprobante en cada registro pagado.'],
        ['tickets', 'adjuntar_ticket', '¿Puedo adjuntar archivos al ticket?', 'adjuntar archivo captura imagen ticket', 'Sí. Al crear un ticket activa «Adjuntar archivos» y sube capturas o documentos que ayuden a resolver tu caso.'],
        ['tickets', 'urgente_ticket', 'Mi caso es urgente', 'urgente prioridad alta ticket', 'Al crear el ticket selecciona prioridad Alta y describe la urgencia. También puedes escribir por WhatsApp o a servicios@conlineweb.com.'],
        ['correo-ssl', 'correo_no_llega', 'No recibo correos', 'correo no llega recibo email', 'Verifica la configuración IMAP/SMTP y que el buzón no esté lleno. Si el problema persiste, abre un ticket con el dominio afectado.'],
        ['correo-ssl', 'activar_ssl', '¿Cómo activo el SSL?', 'activar ssl https certificado instalar', 'En la mayoría de planes el SSL se instala automáticamente. Si tu sitio no muestra el candado, abre un ticket y lo revisamos.'],
        ['wordpress', 'error_wordpress', 'Mi WordPress no carga', 'wordpress error pantalla blanca no carga', 'Prueba acceder a /wp-admin. Si ves error, abre un ticket indicando la URL exacta y si cambiaste algo recientemente (plugin, tema).'],
        ['wordpress', 'actualizar_wp', '¿Actualizan WordPress por mí?', 'actualizar wordpress plugins mantenimiento', 'Ofrecemos mantenimiento y actualizaciones bajo solicitud. Abre un ticket describiendo qué necesitas actualizar.'],
        ['general', 'seo_servicio', '¿Ofrecen SEO?', 'seo posicionamiento google buscadores', 'Sí, ConlineWeb ofrece servicios de SEO y marketing digital. Solicita información por ticket o escribe a servicios@conlineweb.com.'],
        ['general', 'desarrollo_web', '¿Hacen desarrollo web a medida?', 'desarrollo web diseño pagina landing', 'Sí. Cuéntanos tu proyecto en un ticket o por este chat y un asesor te contactará con opciones y cotización.'],
        ['general', 'marketing_digital', '¿Qué servicios de marketing ofrecen?', 'marketing digital redes ads publicidad', 'Ofrecemos estrategia digital, campañas y presencia web. Describe tu necesidad y te orientamos con el servicio adecuado.'],
        ['general', 'mantenimiento', '¿Tienen planes de mantenimiento?', 'mantenimiento soporte mensual plan', 'Sí, contamos con planes de mantenimiento para sitios y servicios. Consulta opciones vía ticket o con un asesor.'],
        ['hosting', 'subir_archivos', '¿Cómo subo archivos a mi sitio?', 'subir archivos ftp filezilla public_html', 'Usa FTP o el administrador de archivos de cPanel. Los datos de acceso están en la sección «Hosting» de tu panel.'],
        ['dominios', 'apuntar_dominio', '¿Cómo apunto mi dominio?', 'apuntar dominio nameserver dns servidor', 'Configura los nameservers o registros DNS en «Dominios». Si tu sitio está en ConlineWeb, podemos indicarte los valores correctos vía ticket.'],
        ['pagos', 'contacto_facturacion', 'Contacto de facturación', 'facturacion cobros servicios@ email', 'Para temas de facturación escribe a servicios@conlineweb.com indicando tu nombre de cliente y el servicio relacionado.'],
    ];

    foreach ($extra as $faq) {
        $intent = $faq[1];
        $chk = $conn->prepare('SELECT id FROM cw_chat_conocimiento WHERE intencion = ? LIMIT 1');
        if (!$chk) {
            continue;
        }
        $chk->bind_param('s', $intent);
        $chk->execute();
        $exists = $chk->get_result()->fetch_assoc();
        $chk->close();
        if ($exists) {
            continue;
        }
        $catId = $catIds[$faq[0]] ?? null;
        $stmt = $conn->prepare('INSERT INTO cw_chat_conocimiento
            (categoria_id, titulo, pregunta, respuesta, palabras_clave, intencion, tipo_cliente, activo, prioridad, created_at)
            VALUES (?,?,?,?,?,?,"todos",1,8,?)');
        if ($stmt) {
            $stmt->bind_param('issssss', $catId, $faq[2], $faq[2], $faq[4], $faq[3], $intent, $now);
            $stmt->execute();
            $stmt->close();
        }
    }
}
