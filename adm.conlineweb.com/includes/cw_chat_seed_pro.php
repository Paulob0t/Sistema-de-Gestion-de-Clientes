<?php
/**
 * Lote profesional de FAQs — importación idempotente por intención.
 * Formato: [categoria_slug, intencion, titulo, pregunta, palabras_clave, respuesta]
 */

/** @return int Entradas insertadas */
function cw_chat_seed_pro(mysqli $conn): int
{
    $now = date('Y-m-d H:i:s');
    $catIds = [];
    $cr = $conn->query('SELECT id, slug FROM cw_chat_categorias');
    if ($cr) {
        while ($row = $cr->fetch_assoc()) {
            $catIds[$row['slug']] = (int) $row['id'];
        }
    }

    $faqs = [
        ['general', 'servicios_disponibles', 'Servicios de ConlineWeb',
            '¿Qué servicios ofrecen? ¿A qué se dedican?',
            'servicios, ofrecen, que hacen, empresa, conlineweb',
            'En ConlineWeb ofrecemos hosting, dominios, correo corporativo, desarrollo web, WordPress, SEO, marketing digital y soporte técnico. Todo se administra desde tu panel de cliente en cliente.conlineweb.com.'],

        ['general', 'horario_atencion', 'Horarios de atención',
            '¿Cuál es su horario de atención? ¿A qué hora abren?',
            'horario, horarios, atencion, atienden, hora, abierto, disponibles',
            'Nuestro horario de atención humana es de lunes a viernes de 9:00 a 18:00 (hora Ciudad de México). Fuera de ese horario puedes escribirnos y un asesor te contactará el siguiente día hábil.'],

        ['general', 'contacto_general', 'Formas de contacto',
            '¿Cómo los contacto? ¿Tienen teléfono o correo?',
            'contacto, telefono, correo, email, comunicarme, escribirles',
            'Puedes contactarnos por este chat, por correo a servicios@conlineweb.com o por WhatsApp al +52 1 81 2009 4766. También puedes abrir un ticket desde tu panel de cliente.'],

        ['general', 'info_comercial', 'Información comercial',
            'Quiero información comercial o una cotización',
            'comercial, cotizacion, cotización, presupuesto, información, info',
            'Con gusto te orientamos. Cuéntanos qué necesitas (sitio web, hosting, marketing, etc.) y un asesor te contactará con opciones y cotización. También puedes escribir a servicios@conlineweb.com.'],

        ['general', 'proceso_contratacion', 'Cómo contratar un servicio',
            '¿Cómo contrato un servicio? ¿Cuál es el proceso?',
            'contratar, contratación, proceso, empezar, iniciar, comprar',
            'Para contratar: 1) Escríbenos qué servicio necesitas. 2) Recibirás opciones y cotización. 3) Al confirmar, te indicamos el pago y activamos tu servicio. Puedes dar el primer paso desde este chat o por servicios@conlineweb.com.'],

        ['general', 'estado_proyecto', 'Estado de mi proyecto',
            '¿En qué va mi proyecto? ¿Cuál es el avance?',
            'estado, avance, proyecto, progreso, desarrollo, sitio',
            'Para consultar el avance de tu proyecto, abre un ticket en tu panel indicando el nombre del proyecto o dominio. Un asesor o tu ejecutivo te compartirá el estatus actualizado.'],

        ['hosting', 'hosting_planes', 'Planes de hosting',
            '¿Qué planes de hosting tienen? Quiero contratar hosting',
            'planes hosting, contratar hosting, hosting, alojamiento, paquetes, opciones',
            "Ofrecemos planes de hosting adaptados a distintas necesidades:\n\n1. Plan Básico — ideal para sitios pequeños o landing pages.\n2. Plan Profesional — para sitios con más tráfico y correos corporativos.\n3. Plan Empresarial — mayor espacio, recursos y soporte prioritario.\n\nLos precios y detalles actualizados te los comparte un asesor según tus necesidades. ¿Te gustaría que te conecte con uno?"],

        ['hosting', 'hosting_plan_basico', 'Plan Básico de hosting',
            '¿Cuánto cuesta el plan básico? Precio del primer plan',
            'plan basico, básico, primer plan, precio basico, costo basico, opcion 1',
            'El Plan Básico está pensado para sitios pequeños o páginas de presentación. El precio depende de la promoción vigente y del periodo de contratación. Un asesor puede darte el costo exacto hoy mismo — ¿quieres que te conecte?'],

        ['hosting', 'hosting_plan_profesional', 'Plan Profesional de hosting',
            '¿Cuánto cuesta el segundo plan? Precio plan profesional',
            'plan profesional, segundo plan, precio profesional, costo plan 2, opcion 2',
            'El Plan Profesional incluye más espacio, correos y recursos para sitios con mayor tráfico. El precio varía según contratación anual o mensual. Te puedo conectar con un asesor para cotización exacta sin compromiso.'],

        ['hosting', 'hosting_plan_empresarial', 'Plan Empresarial de hosting',
            '¿Cuánto cuesta el plan empresarial? Tercer plan de hosting',
            'plan empresarial, tercer plan, plan 3, precio empresarial, opcion 3',
            'El Plan Empresarial ofrece mayor capacidad, rendimiento y soporte prioritario. Es ideal para tiendas en línea o sitios con alto tráfico. Solicita cotización personalizada con un asesor para conocer el precio actual.'],

        ['hosting', 'soporte_tecnico_hosting', 'Soporte técnico de hosting',
            'Tengo un problema técnico con mi hosting',
            'soporte tecnico, problema hosting, falla servidor, no funciona hosting, ayuda tecnica',
            'Para soporte técnico de hosting, abre un ticket en tu panel con la mayor descripción posible (dominio, error, capturas). Si es urgente, escríbenos por WhatsApp al +52 1 81 2009 4766.'],

        ['dominios', 'dominios_precios', 'Precios de dominios',
            '¿Cuánto cuesta un dominio? Precio de dominios',
            'precio dominio, costo dominio, cuanto cuesta dominio, registrar dominio',
            'El costo de un dominio depende de la extensión (.com, .mx, .net, etc.) y del periodo de registro. Consulta disponibilidad y precio actual en la sección «Dominios» de tu panel o solicita cotización a servicios@conlineweb.com.'],

        ['dominios', 'renovacion_dominio', 'Renovar dominio',
            '¿Cómo renuevo mi dominio?',
            'renovar dominio, renovación dominio, vence dominio, pago dominio',
            'Renueva tu dominio desde «Mis pagos» en el panel antes de la fecha de vencimiento. Si ya venció, contáctanos de inmediato para evaluar recuperación.'],

        ['pagos', 'formas_pago', 'Formas de pago',
            '¿Qué formas de pago aceptan? ¿Cómo puedo pagar?',
            'formas de pago, como pagar, metodos pago, tarjeta, transferencia, pagar',
            'Aceptamos pago con tarjeta de crédito y débito mediante Stripe desde tu panel. Si necesitas transferencia u otra forma de pago, escríbenos a servicios@conlineweb.com indicando tu servicio.'],

        ['pagos', 'facturacion_detalle', 'Facturación y CFDI',
            'Necesito factura o CFDI',
            'factura, facturación, cfdi, fiscal, rfc, comprobante fiscal, facturar',
            'Para solicitar factura fiscal (CFDI), abre un ticket con tus datos fiscales (RFC, razón social, régimen, uso de CFDI y correo). El comprobante de pago simple está en «Mis pagos» después de pagar.'],

        ['pagos', 'renovacion_servicios', 'Renovar mis servicios',
            '¿Cómo renuevo mis servicios? ¿Cuándo debo pagar?',
            'renovar, renovación, vencimiento, pagar renovacion, servicios vencen',
            'Ve a «Mis pagos» en tu panel para ver servicios próximos a vencer. Te recomendamos pagar al menos 5 días antes del vencimiento para evitar interrupciones. Puedes pagar varios servicios juntos con pago múltiple.'],

        ['pagos', 'adeudo_pendiente', 'Tengo un adeudo o pago pendiente',
            'Tengo un adeudo, pago pendiente o servicio suspendido',
            'adeudo, pendiente, debo, suspendido, moroso, vencido, pagar adeudo',
            'Revisa «Mis pagos» para ver cobros pendientes. Al liquidar, el servicio se reactiva en breve. Si necesitas facilidades de pago, escríbenos a servicios@conlineweb.com.'],

        ['tickets', 'soporte_general', 'Soporte técnico general',
            'Necesito soporte técnico',
            'soporte, soporte tecnico, ayuda tecnica, asistencia, problema tecnico',
            'Para soporte técnico, crea un ticket en tu panel con el detalle del problema. Incluye dominio, capturas y pasos que ya intentaste. Respondemos en horario laboral y priorizamos casos urgentes.'],

        ['tickets', 'tiempo_respuesta_ticket', 'Tiempo de respuesta de tickets',
            '¿En cuánto responden un ticket?',
            'tiempo respuesta, cuando responden, tardan ticket, SLA',
            'Los tickets se atienden en horario laboral (lun–vie 9:00–18:00 CDMX). El tiempo de primera respuesta depende de la prioridad y complejidad. Casos urgentes: también WhatsApp +52 1 81 2009 4766.'],

        ['wordpress', 'desarrollo_web_cotizar', 'Cotizar desarrollo web',
            'Quiero cotizar un sitio web o landing page',
            'cotizar sitio, desarrollo web, pagina web, landing, diseño web, crear sitio',
            'Cuéntanos qué tipo de sitio necesitas (corporativo, tienda, landing, etc.), funciones deseadas y plazo. Un asesor te enviará propuesta y cotización. También puedes escribir a servicios@conlineweb.com.'],

        ['general', 'mantenimiento_web', 'Mantenimiento de sitios web',
            '¿Ofrecen mantenimiento para mi sitio?',
            'mantenimiento, actualizar sitio, mantenimiento web, soporte mensual',
            'Sí, ofrecemos planes de mantenimiento que incluyen actualizaciones, respaldos y revisión técnica. Solicita detalles por ticket o por este chat y te indicamos opciones.'],

        ['correo-ssl', 'correo_soporte', 'Problemas con correo',
            'Mi correo no funciona o no puedo enviar emails',
            'correo no funciona, email error, no envia correo, smtp error, outlook',
            'Verifica usuario, contraseña y configuración IMAP/SMTP en tu panel de hosting. Si el problema continúa, abre un ticket con el dominio afectado y el mensaje de error exacto.'],

        ['general', 'procesos_internos', 'Procesos y tiempos de activación',
            '¿Cuánto tarda en activarse un servicio?',
            'activacion, activar, cuanto tarda, tiempo activacion, cuando queda listo',
            'La activación de hosting y dominios suele ser en minutos u horas tras confirmar el pago. Desarrollo web y proyectos a medida tienen tiempos según alcance; tu asesor te dará fecha estimada al confirmar el proyecto.'],

        ['marketing', 'marketing_servicios', 'Servicios de marketing digital',
            '¿Qué incluye el servicio de marketing?',
            'marketing, publicidad, redes sociales, ads, campañas, promocion',
            'Ofrecemos estrategia digital, gestión de redes, campañas publicitarias y posicionamiento. Cuéntanos tu objetivo (ventas, leads, branding) y te proponemos el plan adecuado.'],

        ['general', 'escalar_asesor', 'Hablar con un asesor',
            'Quiero hablar con un asesor humano',
            'asesor, humano, persona, agente, hablar con alguien, operador',
            'Con gusto te conecto con un asesor. Puedes seguir escribiendo aquí y un miembro del equipo te responderá lo antes posible. También puedes escribir a servicios@conlineweb.com o WhatsApp +52 1 81 2009 4766.'],

        ['correo-ssl', 'crear_correo_cpanel', 'Crear correo electrónico en cPanel',
            '¿Cómo creo un correo electrónico desde cPanel?',
            'crear correo, correo cpanel, email accounts, cuenta correo, nuevo correo, buzon',
            "Para crear un correo en cPanel:\n\n1. Entra a cPanel desde **Hosting** o **Mis sitios web** en tu panel.\n2. Busca la sección **Email** → **Email Accounts** (Cuentas de correo).\n3. Pulsa **Create** / **Crear**.\n4. Escribe la dirección (ej. ventas@tudominio.com), contraseña y espacio.\n5. Guarda y usa los datos IMAP/SMTP que cPanel te muestra para Outlook o tu celular.\n\nSi no ves cPanel, revisa la sección **Hosting** para tus datos de acceso."],

        ['correo-ssl', 'configurar_correo_outlook', 'Configurar correo en Outlook o celular',
            '¿Cómo configuro mi correo en Outlook o en el celular?',
            'configurar correo, outlook, thunderbird, imap smtp, celular correo, android mail',
            "Los datos de configuración están en cPanel → **Email Accounts** → **Connect Devices** (o **Configure Mail Client**).\n\nGeneralmente:\n• **IMAP:** mail.tudominio.com puerto 993 (SSL)\n• **SMTP:** mail.tudominio.com puerto 465 o 587 (SSL)\n• **Usuario:** tu correo completo\n• **Contraseña:** la que definiste al crear la cuenta\n\nSi tienes problemas, abre un ticket con captura del error."],

        ['dominios', 'gestionar_dns_cpanel', 'Gestionar registros DNS en cPanel',
            '¿Cómo gestiono los registros DNS desde cPanel?',
            'registros dns, zone editor, cpanel dns, editar dns, registros a cname mx, zonas dns',
            "Para editar DNS en cPanel:\n\n1. Accede a cPanel desde tu panel (**Hosting** o **Mis sitios web**).\n2. Busca **Domains** → **Zone Editor** (Editor de zonas).\n3. Selecciona tu dominio y elige el tipo de registro: **A**, **CNAME**, **MX**, **TXT**, etc.\n4. Agrega o edita el registro con el valor que necesites.\n5. Los cambios pueden tardar hasta **24 horas** en propagarse.\n\nTambién puedes gestionar DNS desde **Dominios** en el panel del cliente."],

        ['dominios', 'registro_mx_correo', 'Registro MX para correo',
            '¿Cómo configuro el registro MX para mi correo?',
            'registro mx, mx correo, apuntar correo, mail exchange, recibir correos',
            "El registro **MX** indica dónde llegan los correos de tu dominio.\n\nEn cPanel → **Zone Editor**:\n1. Agrega o edita el registro **MX**.\n2. Prioridad habitual: **0** (o la que indique tu proveedor).\n3. Destino: el servidor de correo (en hosting ConlineWeb suele ser mail.tudominio.com).\n\nSi usas correo de ConlineWeb, normalmente cPanel lo configura al crear las cuentas. Si migras correo, abre un ticket para validar los registros."],

        ['hosting', 'acceso_cpanel_panel', 'Acceder a cPanel desde el panel',
            '¿Cómo entro a cPanel desde mi panel de cliente?',
            'entrar cpanel, acceso cpanel, abrir cpanel, login cpanel, panel control hosting',
            "Tienes dos formas:\n\n1. **Hosting** → selecciona tu servicio → botón de acceso a cPanel.\n2. **Mis sitios web** → acceso directo con auto-login (si tu plan lo incluye).\n\nTambién puedes usar la URL https://cpanel.tudominio.com:2083 con el usuario y contraseña que aparecen en **Hosting**. Si no los tienes, abre un ticket de soporte."],

        ['hosting', 'administrador_archivos_cpanel', 'Administrador de archivos en cPanel',
            '¿Cómo uso el administrador de archivos de cPanel?',
            'file manager, administrador archivos, subir archivos cpanel, public_html, editar archivos',
            "En cPanel → **Files** → **File Manager** (Administrador de archivos):\n\n1. Abre la carpeta **public_html** (ahí va tu sitio web público).\n2. Usa **Upload** para subir archivos o carpetas.\n3. Clic derecho en un archivo para editar, renombrar o cambiar permisos.\n4. Para instalar WordPress u otros CMS, sube los archivos a public_html o usa instaladores como Softaculous si están disponibles.\n\nAlternativa: FTP con los datos de la sección **Hosting**."],
    ];

    $inserted = 0;
    foreach ($faqs as $faq) {
        if (cw_chat_seed_pro_insert($conn, $catIds, $faq, $now)) {
            $inserted++;
        }
    }

    return $inserted;
}

/**
 * @param array<string,int> $catIds
 * @param array<int,string> $faq
 */
function cw_chat_seed_pro_insert(mysqli $conn, array $catIds, array $faq, string $now): bool
{
    $intent = $faq[1];
    $chk = $conn->prepare('SELECT id FROM cw_chat_conocimiento WHERE intencion = ? LIMIT 1');
    if (!$chk) {
        return false;
    }
    $chk->bind_param('s', $intent);
    $chk->execute();
    $exists = $chk->get_result()->fetch_assoc();
    $chk->close();
    if ($exists) {
        return false;
    }

    $catId = $catIds[$faq[0]] ?? null;
    $titulo = $faq[2];
    $pregunta = $faq[3];
    $keywords = $faq[4];
    $respuesta = $faq[5];

    if ($catId !== null && $catId > 0) {
        $stmt = $conn->prepare('INSERT INTO cw_chat_conocimiento
            (categoria_id, titulo, pregunta, respuesta, palabras_clave, intencion, tipo_cliente, activo, prioridad, created_at)
            VALUES (?,?,?,?,?,?,"todos",1,12,?)');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('issssss', $catId, $titulo, $pregunta, $respuesta, $keywords, $intent, $now);
    } else {
        $stmt = $conn->prepare('INSERT INTO cw_chat_conocimiento
            (categoria_id, titulo, pregunta, respuesta, palabras_clave, intencion, tipo_cliente, activo, prioridad, created_at)
            VALUES (NULL,?,?,?,?,?,"todos",1,12,?)');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ssssss', $titulo, $pregunta, $respuesta, $keywords, $intent, $now);
    }
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();
    return $ok;
}
