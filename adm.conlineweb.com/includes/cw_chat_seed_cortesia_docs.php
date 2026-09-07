<?php
/**
 * Cortesía (saludos/despedidas) + FAQs basadas en la documentación del portal.
 * Idempotente por intención.
 */

/** @return int Entradas insertadas */
function cw_chat_seed_cortesia_docs(mysqli $conn): int
{
    @$conn->set_charset('utf8mb4');
    $now = date('Y-m-d H:i:s');
    $catIds = [];
    $cr = $conn->query('SELECT id, slug FROM cw_chat_categorias');
    if ($cr) {
        while ($row = $cr->fetch_assoc()) {
            $catIds[$row['slug']] = (int) $row['id'];
        }
    }

    if (!function_exists('cw_chat_seed_pro_insert')) {
        require_once __DIR__ . '/cw_chat_seed_pro.php';
    }

    $faqs = [
        // —— Cortesía ——
        ['general', 'saludo_hola', 'Saludo al cliente',
            'Hola, buenos días, buenas tardes, buen día, hi, hello',
            'hola, buenas, buenos dias, buenos días, buenas tardes, buenas noches, buen dia, hi, hello, hey, saludo, que tal, qué tal',
            "¡Hola! Soy el **asistente de ConlineWeb**.\n\nPuedo ayudarte con:\n• **Hosting** y **cPanel**\n• **Dominios** y **DNS**\n• **Pagos** y renovaciones\n• **Tickets** de soporte\n• Tu **cuenta** en el panel\n\nEn cualquier momento puedes pedir **hablar con un agente**.\n\n¿Qué necesitas hoy?"],

        ['general', 'despedida_gracias', 'Despedida y agradecimiento',
            'Gracias, adiós, hasta luego, bye, nos vemos, ya es todo',
            'gracias, muchas gracias, thank you, adios, adiós, hasta luego, bye, nos vemos, excelente, perfecto gracias, listo gracias, ya es todo, nada mas, nada más, no ocupo nada mas',
            "¡Con mucho gusto!\n\nSi más adelante necesitas algo sobre hosting, dominios, pagos o soporte, aquí estaré. También puedes pedir un agente cuando quieras. Que tengas un excelente día."],

        ['general', 'confirmacion_ok', 'Confirmación breve del cliente',
            'Ok, vale, claro, perfecto, entendido, de acuerdo',
            'ok, okay, vale, claro, perfecto, entendido, de acuerdo, listo, genial, excelente, muy bien, esta bien, está bien',
            "¡Perfecto! Si surge otra duda sobre tu cuenta, hosting, dominios o pagos, escríbeme cuando quieras. Estoy para ayudarte."],

        ['general', 'ayuda_general', 'Quiero ayuda / no sé por dónde empezar',
            'Necesito ayuda, ayúdame, no sé qué hacer, me puedes ayudar',
            'ayuda, ayudame, ayúdame, no se, no sé, auxiliarme, orientacion, orientación, por donde empiezo',
            "Claro, con gusto te ayudo. Para atenderte mejor, dime cuál de estos temas es:\n\n1. **Hosting** (acceso, cPanel, sitio caído)\n2. **Dominios** (DNS, renovación, transferencia)\n3. **Pagos** (pendientes, renovar, factura)\n4. **Tickets** (abrir o dar seguimiento a soporte)\n5. **Correo / SSL**\n\nTambién puedes preguntarme por tus servicios en vivo, por ejemplo: «¿cuándo vence mi dominio?»."],

        // —— Documentación portal ——
        ['general', 'doc_acceso_portal', 'Acceso al portal y contraseña',
            'No puedo entrar al portal, olvidé mi contraseña, sesión expirada',
            'acceso portal, no puedo entrar, olvide contraseña, olvidé contraseña, recuperar contraseña, sesion expirada, sesión expirada, login, ingreso',
            "Para entrar al **Portal de Clientes** ve a **cliente.conlineweb.com**.\n\n• Si **olvidaste tu contraseña**, usa «Recuperar / Restablecer contraseña» en la pantalla de ingreso.\n• Si la **sesión expiró**, vuelve a iniciar sesión. Si el bucle continúa, prueba borrar cookies del sitio o usa otro navegador.\n• Navegadores recomendados: Chrome, Firefox, Edge o Safari actualizados, con JavaScript y cookies activos.\n\nSi el reCAPTCHA no carga, desactiva bloqueadores de anuncios o prueba otra red.\n\n¿Quieres que te guíe paso a paso?"],

        ['tickets', 'doc_crear_ticket', 'Crear ticket de soporte (documentación)',
            'Cómo abro un ticket, crear solicitud de soporte',
            'crear ticket, abrir ticket, nuevo ticket, como abro un ticket, cómo abro un ticket, solicitud soporte, mis tickets',
            "Puedes abrir un ticket de dos formas:\n\n**Desde este chat:** escribe «crear ticket» y te guío (asunto → descripción → prioridad). Queda registrado en tu cuenta y te llega la misma confirmación por correo.\n\n**Desde el panel:**\n1. Abre **Mis Tickets**.\n2. Pulsa **Crear Nuevo Ticket**.\n3. Completa asunto, categoría, descripción y prioridad.\n4. Adjunta capturas si ayuda.\n5. Envía: queda en estado **Pendiente**.\n\nIncluye dominio o servicio afectado y pasos para reproducir el problema.\n\n¿Quieres que lo generemos ahora desde el chat?"],

        ['tickets', 'doc_estados_ticket', 'Estados de un ticket',
            'Qué significa pendiente, en proceso o finalizado en mi ticket',
            'estado ticket, pendiente, en proceso, finalizado, seguimiento ticket',
            "En **Mis Tickets** verás el estatus de cada solicitud:\n\n• **Pendiente** — recibida, en espera de atención.\n• **En proceso** — un asesor ya está trabajando en ella.\n• **Finalizado** — resuelta o cerrada.\n\nEntra a cada ticket con **Ver** para leer el historial y comentarios. Si necesitas más detalle, responde dentro del mismo ticket."],

        ['tickets', 'doc_prioridad_ticket', 'Prioridad de tickets',
            'Qué prioridad pongo en el ticket, urgente',
            'prioridad ticket, urgente, prioridad alta, prioridad normal',
            "Al crear el ticket indica la urgencia según el impacto:\n\n• **Alta** — sitio caído, correo empresarial sin funcionar, dominio expirado en producción.\n• **Normal** — consultas, mejoras o configuraciones no críticas.\n\nIncluye hora de inicio e impacto si es alta. El equipo puede reclasificar según el caso real."],

        ['hosting', 'doc_acceso_cpanel', 'Acceso a cPanel desde el portal',
            'Cómo entro a cPanel, no abre cPanel',
            'cpanel, whm, acceso hosting, panel control, auto login cpanel',
            "Puedes abrir **cPanel** desde el portal sin volver a escribir usuario y contraseña:\n\n1. En **Mis sitios web** o **Hosting**, usa el botón **cPanel** (o WHM si aplica).\n2. Se abre una pestaña con acceso autenticado.\n\nSi el botón está deshabilitado, faltan credenciales en tu servicio. Si no abre, permite ventanas emergentes o vuelve a iniciar sesión en el portal.\n\n¿Tu problema es que no ves el botón o que cPanel no carga?"],

        ['hosting', 'doc_correo_hosting', 'Correos en hosting / cPanel',
            'Cómo creo un correo corporativo en cPanel',
            'crear correo, email accounts, correo corporativo, cuenta de correo, imap smtp',
            "Los correos se administran en cPanel → **Cuentas de correo electrónico**:\n\n1. Entra a cPanel desde el portal.\n2. Abre **Email Accounts** / Cuentas de correo.\n3. **Crear**, define dirección, contraseña y cuota.\n4. Configura Outlook u otro cliente con IMAP/SMTP (usa «Connect Devices» en cPanel).\n\nCada buzón consume espacio de tu plan. Si no llegan correos, revisa MX y SPF.\n\n¿Quieres que te pase también los puertos típicos IMAP/SMTP?"],

        ['hosting', 'doc_ssl', 'Certificado SSL',
            'Mi sitio no tiene candado, HTTPS, SSL',
            'ssl, https, certificado, candado, insecure, no seguro',
            "La mayoría de planes incluyen **SSL**. Si el navegador marca el sitio como no seguro:\n\n1. Confirma que entras con **https://**.\n2. En cPanel revisa **SSL/TLS Status** o Let's Encrypt.\n3. Si hay contenido mixto (http en páginas https), corrige recursos en el sitio.\n\nSi no se emite el certificado, abre un **ticket** con tu dominio y te ayudamos a instalarlo."],

        ['hosting', 'doc_sitio_caido', 'Sitio caído o error 500',
            'Mi página no carga, error 500, sitio caído',
            'sitio caido, sitio caído, error 500, no carga, pagina abajo, down',
            "Si el sitio no carga o ves error 500:\n\n1. Revisa en **Pagos** que el hosting esté al corriente y activo.\n2. Consulta el **error_log** en cPanel o Administrador de archivos.\n3. Si usas WordPress, prueba desactivar plugins (renombra la carpeta `plugins`).\n4. Si el fallo empezó tras un cambio, valora restaurar un backup.\n5. Abre un **ticket** con el mensaje de error y la hora del incidente.\n\n¿Es un sitio WordPress o HTML/otro?"],

        ['dominios', 'doc_dns', 'Administrar DNS',
            'Cómo cambio DNS, registros A CNAME MX',
            'dns, registros dns, zone editor, apuntar dominio, a cname mx',
            "Si el dominio está **registrado con ConlineWeb** y activo:\n\n1. Ve a **Dominios** en el portal.\n2. Edita registros **A, CNAME, MX, TXT**, etc.\n3. Los cambios pueden tardar hasta **24–48 h** en propagarse.\n\nTambién puedes usar cPanel → **Zone Editor**.\n\nSi el dominio está con **otro proveedor**, DNS y renovación se gestionan allá; aquí no podemos renovarlo.\n\n¿El dominio lo tienes registrado con nosotros?"],

        ['dominios', 'doc_nameservers', 'Nameservers / DNS del hosting',
            'Qué nameservers uso, ns1 ns2',
            'nameserver, nameservers, ns1, ns2, dns hosting, apuntar nameserver',
            "Para que tu dominio use el hosting de ConlineWeb, en el registrador debes poner los **nameservers** que te indicamos al activar el servicio (o los que aparecen en tu bienvenida / ticket).\n\nDespués de cambiarlos, espera propagación (hasta 24–48 h). Mientras tanto el sitio puede verse intermitente.\n\nSi no tienes los NS a la mano, ábreme un ticket o dime tu dominio y te oriento con lo que veamos en tu cuenta."],

        ['dominios', 'doc_epp', 'Código EPP / transferencia',
            'Necesito código de transferencia EPP auth',
            'epp, auth code, codigo transferencia, transferir dominio, auth-info',
            "Para transferir un dominio **registrado y activo** con ConlineWeb:\n\n1. Ve a **Dominios** → pestaña Registrados.\n2. Solicita el **código EPP / Auth-Info**.\n3. El código se envía al correo registrado o se muestra según el flujo.\n\nRequisitos: dominio desbloqueado y correo del contacto administrativo accesible. Dominios con otro proveedor no gestionan EPP desde nuestro panel."],

        ['pagos', 'doc_facturacion', 'Factura y pagos (documentación)',
            'Dónde veo mi factura, comprobante de pago',
            'factura, cfdi, comprobante, mis pagos, historial pagos',
            "En **Mis pagos** del portal ves pendientes, historial y comprobantes después de pagar con tarjeta (Stripe).\n\nPara **factura fiscal (CFDI)** abre un ticket con RFC, razón social, régimen, uso de CFDI y correo de recepción.\n\nTe recomiendo renovar al menos **5 días antes** del vencimiento para evitar cortes.\n\n¿Quieres que revise si tienes pagos pendientes en tu cuenta?"],

        ['general', 'doc_documentacion', 'Dónde está la documentación del portal',
            'Hay un manual, documentación, guía del panel',
            'documentacion, documentación, manual, guia, guía, ayuda portal, tutorial',
            "Sí. En **conlineweb.com/documentacion** encontrarás guías del portal: acceso, hosting, dominios, tickets, pagos y más.\n\nTambién puedo resumirte aquí el paso a paso. ¿Sobre qué tema quieres la guía: hosting, dominios, tickets o pagos?"],
    ];

    $inserted = 0;
    foreach ($faqs as $faq) {
        // Quitar emojis para evitar fallos de collation utf8mb3 en algunos entornos.
        $faq[5] = preg_replace('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', '', $faq[5]);
        $faq[5] = trim(preg_replace('/[ \t]+\n/', "\n", $faq[5]));
        try {
            if (cw_chat_seed_pro_insert($conn, $catIds, $faq, $now)) {
                $inserted++;
            } else {
                // Actualizar si ya existe (mejora de tono / docs)
                if (in_array($faq[1], ['saludo_hola', 'despedida_gracias', 'confirmacion_ok', 'ayuda_general'], true)
                    || str_starts_with($faq[1], 'doc_')) {
                    $upd = $conn->prepare('UPDATE cw_chat_conocimiento SET respuesta = ?, palabras_clave = ?, pregunta = ?, titulo = ?, activo = 1, prioridad = 20 WHERE intencion = ?');
                    if ($upd) {
                        $upd->bind_param('sssss', $faq[5], $faq[4], $faq[3], $faq[2], $faq[1]);
                        $upd->execute();
                        $upd->close();
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('cw_chat_seed_cortesia_docs: ' . $faq[1] . ' ' . $e->getMessage());
        }
    }

    // Suavizar FAQs base que suelen sonar cortantes.
    $warmFixes = [
        'recuperar_password' => "Sin problema. En **cliente.conlineweb.com**, en la pantalla de ingreso, usa «Recuperar / Restablecer contraseña».\n\nTe llegará un enlace al correo registrado en tu cuenta. Si no lo ves, revisa spam o espera unos minutos.\n\n¿Pudiste iniciar el restablecimiento o necesitas que te oriente con otra cosa?",
        'acceso_panel' => "Para entrar al **Portal de Clientes** ve a **cliente.conlineweb.com** con tu usuario y contraseña.\n\nSi olvidaste la contraseña, usa «Recuperar / Restablecer contraseña» en la pantalla de ingreso. ¿Quieres que te guíe paso a paso?",
    ];
    foreach ($warmFixes as $intent => $respuesta) {
        $upd = $conn->prepare('UPDATE cw_chat_conocimiento SET respuesta = ?, activo = 1 WHERE intencion = ?');
        if ($upd) {
            $upd->bind_param('ss', $respuesta, $intent);
            $upd->execute();
            $upd->close();
        }
    }

    return $inserted;
}

/** Mensajes de sistema más cálidos (fuerza actualización en producción/local). */
function cw_chat_seed_cortesia_config(mysqli $conn): void
{
    $now = date('Y-m-d H:i:s');
    $msgs = [
        'saludo_inicial' => "¡Hola! Soy el **asistente de ConlineWeb**.\n\nPuedo ayudarte con hosting/cPanel, dominios/DNS, pagos, tickets y tu cuenta en el panel.\n\nEn cualquier momento puedes pedir hablar con un agente. ¿En qué te apoyo hoy?",
        'mensaje_aclaracion' => '¿Te refieres a «{titulo}»? Dime un poco más y lo resolvemos. También puedes pedir un agente cuando quieras.',
        'mensaje_sin_respuesta' => "No pude resolver eso con certeza desde aquí.\n\nPuedo ayudarte con hosting/cPanel, dominios/DNS, pagos, tickets y tu cuenta.\n\nPuedes conversar con un agente. ¿Te gustaría conversar con uno o no?",
        'mensaje_espera' => 'Un momento, estoy revisando tu solicitud…',
        'mensaje_whatsapp' => 'Si tienes dudas, escribe por WhatsApp.',
        'whatsapp_numero' => '524771181285',
    ];

    foreach ($msgs as $clave => $valor) {
        $stmt = $conn->prepare('INSERT INTO cw_chat_config (clave, valor, updated_at) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE valor = VALUES(valor), updated_at = VALUES(updated_at)');
        if ($stmt) {
            $stmt->bind_param('sss', $clave, $valor, $now);
            $stmt->execute();
            $stmt->close();
        }
    }
    $GLOBALS['cw_chat_config_cache'] = null;
}
