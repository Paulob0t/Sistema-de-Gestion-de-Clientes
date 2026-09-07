<?php
/**
 * Respuestas FAQ del chat web: 40+ escenarios, coherentes con lo escrito,
 * siempre empujando a registro / asesor (sin inventar precios fijos).
 */
function cw_web_chat_reply(
    string $mensaje,
    string $servicio,
    string $pagina,
    string $interes = '',
    bool $citaConfirmada = false,
    bool $adviceMode = false,
    string $nombre = '',
    bool $registered = false,
    bool $businessHoursOpen = true,
    string $businessHoursNote = '',
    bool $registrationPending = false,
    string $registrationStep = '',
    string $locale = 'es'
): array {
    $isEn = strtolower($locale) === 'en';
    $m = mb_strtolower(trim($mensaje));
    $svcLabels = [
        'desarrollo_web' => 'desarrollo web y páginas a medida',
        'pagina_web' => 'diseño y desarrollo de páginas web',
        'seo' => 'SEO y posicionamiento orgánico',
        'seo_local' => 'SEO local / GEO en México',
        'geo' => 'posicionamiento GEO local',
        'inteligencia_artificial' => 'soluciones de inteligencia artificial',
        'automatizacion' => 'automatización de procesos',
        'ecommerce' => 'tiendas en línea y e-commerce',
        'software' => 'desarrollo de software a medida',
        'hosting' => 'hosting y soporte técnico',
        'otro' => 'servicios digitales ConlineWeb',
    ];
    $blurbs = [
        'desarrollo_web' => 'Armamos sitios a medida: claros, rápidos y pensados para que conviertan.',
        'pagina_web' => 'Hacemos páginas profesionales, responsive y listas para atraer clientes.',
        'seo' => 'Trabajamos SEO práctico: que te encuentren por lo que tu cliente realmente busca.',
        'seo_local' => 'Con SEO local / GEO te ayudamos a aparecer en tu ciudad y zona de servicio.',
        'geo' => 'El GEO conecta tu negocio con búsquedas locales reales.',
        'inteligencia_artificial' => 'Integramos IA útil de verdad: asistentes y automatizaciones que le ahorran tiempo al equipo.',
        'automatizacion' => 'Automatizamos lo repetitivo para que la operación fluya mejor.',
        'ecommerce' => 'Montamos tiendas en línea con catálogo, pagos y seguimiento de ventas.',
        'software' => 'Desarrollamos software a la medida de tu proceso, con soporte y evolución.',
        'hosting' => 'Cuidamos hosting y soporte para que tu sitio esté estable.',
        'otro' => 'Te acompañamos en proyectos digitales: web, sistemas, SEO e IA.',
    ];
    if ($isEn) {
        $svcLabels = [
            'desarrollo_web' => 'custom web development',
            'pagina_web' => 'website design and development',
            'seo' => 'SEO and organic visibility',
            'seo_local' => 'local SEO / GEO',
            'geo' => 'local GEO visibility',
            'inteligencia_artificial' => 'artificial intelligence solutions',
            'automatizacion' => 'process automation',
            'ecommerce' => 'online stores and e-commerce',
            'software' => 'custom software development',
            'hosting' => 'hosting and technical support',
            'otro' => 'ConlineWeb digital services',
        ];
        $blurbs = [
            'desarrollo_web' => 'We build custom sites that are clear, fast, and conversion-focused.',
            'pagina_web' => 'We create professional, responsive websites ready to attract customers.',
            'seo' => 'We run practical SEO so people find you for what they actually search.',
            'seo_local' => 'With local SEO / GEO we help you appear in your city and service area.',
            'geo' => 'GEO connects your business with real local searches.',
            'inteligencia_artificial' => 'We integrate useful AI: assistants and automations that save your team time.',
            'automatizacion' => 'We automate repetitive work so operations run smoother.',
            'ecommerce' => 'We build online stores with catalog, payments, and sales tracking.',
            'software' => 'We build software tailored to your process, with support and ongoing evolution.',
            'hosting' => 'We handle hosting and support so your site stays stable.',
            'otro' => 'We support digital projects: web, systems, SEO, and AI.',
        ];
    }
    $label = $svcLabels[$servicio] ?? $svcLabels['otro'];
    $focus = $interes !== '' ? $interes : $label;
    $blurb = $blurbs[$servicio] ?? $blurbs['otro'];
    $postAgenda = $citaConfirmada || $adviceMode || $registered;
    $nameLead = $nombre !== '' ? explode(' ', trim($nombre))[0] : '';
    $namePrefix = $nameLead !== '' ? $nameLead . ', ' : '';
    $hoursText = $isEn
        ? 'Monday–Friday 8:00 a.m.–8:00 p.m., Saturday 9:00 a.m.–4:00 p.m., closed Sunday (Mexico City time).'
        : 'Lunes a viernes de 8:00 a 20:00, sábados de 9:00 a 16:00, domingos cerrados (hora Ciudad de México).';
    $hoursNow = $businessHoursOpen
        ? ($isEn
            ? 'We are within business hours (' . $hoursText . ').'
            : 'Estamos en horario hábil (' . $hoursText . ').')
        : ($isEn
            ? 'We are currently outside business hours (' . $hoursText . '). For immediate help you can use WhatsApp; an advisor will follow up in the next business window.'
            : 'Ahora estamos fuera de horario (' . $hoursText . '). Para atención inmediata puede escribir por WhatsApp; un asesor le contactará en el próximo horario hábil.');

    $snip = trim(preg_replace('/\s+/u', ' ', $mensaje));
    if (mb_strlen($snip) > 70) {
        $snip = rtrim(mb_substr($snip, 0, 67)) . '…';
    }
    $ack = '';
    $ctaAsesor = $registered
        ? ($namePrefix !== '' ? $namePrefix : '') . ($isEn ? 'What else would you like to know?' : '¿Qué más le gustaría saber?')
        : ($isEn ? 'What else would you like to know?' : '¿Qué más le gustaría saber?');
    $ctaReg = $registered
        ? ($isEn ? 'Prefer WhatsApp or schedule a call? Tell me a day and time.' : '¿Prefiere WhatsApp o agendar llamada? Indíqueme día y hora.')
        : ($isEn ? 'What else would you like to know about ' . $focus . '?' : '¿Qué otra duda tiene sobre ' . $focus . '?');
    $regSuffix = '';
    if ($registrationPending && !$registered) {
        if ($isEn) {
            $regSuffix = match ($registrationStep) {
                'correo' => ' Can I get an email, or say “no email”?',
                'telefono' => ' What is your 10-digit phone number?',
                default => ' What is your name to continue?',
            };
        } else {
            $regSuffix = match ($registrationStep) {
                'correo' => ' ¿Me deja un correo o prefiere «no tengo»?',
                'telefono' => ' ¿Me indica su teléfono a 10 dígitos?',
                default => ' ¿Me indica su nombre para continuar?',
            };
        }
    }

    $scenarios = [
        ['precio|costo|cuanto|cuánto|cotiz|presupuesto|inversi[oó]n|cuota|tarifa|price|cost|quote|budget|investment|pricing|how much',
            $isEn ? "{$ack}For {$focus}, investment depends on scope; there is no fixed rate. {$ctaReg}" : "{$ack}En {$focus} la inversión depende del alcance; no hay tarifa fija. {$ctaReg}", 'precio'],
        ['plazo|tiempo|dura|entrega|semana|mes|cu[aá]ndo\s+(queda|est[aá]|entregan)|timeline|how long|delivery|deadline|weeks?|months?',
            $isEn ? "{$ack}Timelines depend on scope; we work in stages. {$ctaReg}" : "{$ack}Los tiempos dependen del alcance; avanzamos por etapas. {$ctaReg}", 'plazo'],
        ['incluye|proceso|c[oó]mo\s+trabajan|pasos|metodolog|flujo\s+de\s+trabajo|detalle|informaci[oó]n|how\s+do\s+you\s+work|process|steps|methodology|details?',
            $isEn ? "{$ack}We define scope and deliver in stages. {$ctaAsesor}" : "{$ack}Definimos alcance y entregamos por etapas. {$ctaAsesor}", 'proceso'],
        ['ejemplo|caso|portafolio|portfolio|clientes|referenc|demo|muestra|examples?|case\s+stud',
            $isEn ? "{$ack}Happy to share similar cases for {$focus}. An advisor can show real options for your industry. {$ctaReg}" : "{$ack}Con gusto le orientamos con casos similares a {$focus}. Un asesor le muestra opciones reales según su giro. {$ctaReg}", 'ejemplo'],
        ['soporte|mantenimiento|garant[ií]a|postventa|despu[eé]s\s+de\s+entreg|support|maintenance|warranty',
            $isEn ? "{$ack}We include guidance and support according to the plan. {$blurb} {$ctaAsesor}" : "{$ack}Incluimos acompañamiento y soporte según el plan. {$blurb} {$ctaAsesor}", 'soporte'],
        ['tienda|e-?commerce|comercio\s+electr[oó]nico|cat[aá]logo|pagos?\s+en\s+l[ií]nea|mercadopago|stripe|online\s+store|shopify',
            $isEn ? "{$ack}We build online stores with catalog, payments, and sales tracking. {$ctaReg}" : "{$ack}Armamos tiendas en línea con catálogo, pagos y seguimiento de ventas. {$ctaReg}", 'ecommerce'],
        ['\bseo\b|posicion|google|aparecer\s+en|b[uú]squeda|keywords?',
            "{$ack}Trabajamos SEO práctico para que le encuentren por lo que su cliente busca. {$ctaReg}", 'seo'],
        ['\bgeo\b|seo\s+local|mi\s+ciudad|mapa|google\s+maps|negocio\s+local',
            "{$ack}Con SEO local / GEO ayudamos a aparecer en su ciudad y zona de servicio. {$ctaReg}", 'geo'],
        ['\bia\b|inteligencia\s+artificial|chatbot|asistente\s+virtual|openai|automatiz|artificial\s+intelligence|automation',
            $isEn ? "{$ack}We integrate useful AI: assistants and automations that save your team time. {$ctaReg}" : "{$ack}Integramos IA útil: asistentes y automatizaciones que ahorran tiempo al equipo. {$ctaReg}", 'ia'],
        ['software|sistema|erp|crm|app|aplicaci[oó]n|plataforma\s+interna|custom\s+app',
            $isEn ? "{$ack}We build software tailored to your process, with support and ongoing evolution. {$ctaReg}" : "{$ack}Desarrollamos software a la medida de su proceso, con soporte y evolución. {$ctaReg}", 'software'],
        ['p[aá]gina\s+web|sitio\s+web|landing|redise[nñ]o|wordpress|sitio\s+nuevo|website|web\s+design|web\s+site',
            $isEn ? "{$ack}We design and build clear, fast sites focused on conversion. {$ctaReg}" : "{$ack}Diseñamos y desarrollamos sitios claros, rápidos y pensados para convertir. {$ctaReg}", 'web'],
        ['hosting|servidor|dominio|ssl|correo\s+corporativo|migraci[oó]n\s+de\s+sitio|server|domain|migration',
            $isEn ? "{$ack}We handle hosting, stability, and technical support so your site stays backed up. {$ctaReg}" : "{$ack}Cuidamos hosting, estabilidad y soporte técnico para que su sitio esté respaldado. {$ctaReg}", 'hosting'],
        ['urgente|lo\s+antes|r[aá]pido|ya\s+mismo|esta\s+semana|necesito\s+ya',
            "{$ack}Entiendo la urgencia. Ajustamos alcance para una primera entrega útil y luego evolucionamos. {$ctaReg}", 'urgencia'],
        ['mi\s+empresa|mi\s+negocio|pyme|negocio\s+familiar|tengo\s+un\s+negocio',
            "{$ack}Perfecto, lo aterrizamos al tamaño y operación de su negocio. {$blurb} {$ctaAsesor}", 'empresa'],
        ['leads?|ventas|conversi[oó]n|clientes\s+nuevos|formularios?|captar',
            "{$ack}Enfocamos la solución para captar y dar seguimiento a prospectos de forma clara. {$ctaReg}", 'leads'],
        ['whats?\s*app|wasap|wsp|atenci[oó]n\s+al\s+cliente|mensajer[ií]a',
            $registered
                ? ($isEn ? "{$ack}Use the WhatsApp button or tell me a day and time for a call." : "{$ack}Use el botón de WhatsApp o indíqueme día y hora para llamada.")
                : ($isEn ? "{$ack}I first register your request here; then we enable WhatsApp." : "{$ack}Primero registro su solicitud aquí; después habilitamos WhatsApp."), 'whatsapp'],
        ['llamada|agendar|cita|reuni[oó]n|opci[oó]n\s*1|videollamada|call|schedule|meeting|appointment',
            $registered
                ? ($isEn ? "{$ack}What day and time works for the call?" : "{$ack}¿Qué día y hora le conviene para la llamada?")
                : ($isEn ? "{$ack}Gladly. I first register your request (name and phone)." : "{$ack}Con gusto. Primero registro su solicitud (nombre y teléfono)."), 'llamada'],
        ['propuesta|me\s+contacten|que\s+me\s+contacte|opci[oó]n\s*2|escr[ií]banme',
            $registered
                ? "{$ack}¿Qué día y hora prefiere que le contactemos?"
                : "{$ack}Primero registro su solicitud aquí en el chat.", 'propuesta'],
        ['redise[nñ]|moderniz|actualizar\s+p[aá]gina|renovar\s+sitio|est[aá]\s+viejo',
            "{$ack}Modernizamos sitios conservando lo útil y mejorando conversión. {$ctaReg}", 'rediseno'],
        ['m[oó]vil|celular|responsive|se\s+ve\s+mal\s+en\s+el\s+cel',
            "{$ack}Todo se entrega responsive y usable en celular. {$ctaAsesor}", 'movil'],
        ['factur|cfdi|cobros?|pasarela|pago\s+en\s+l[ií]nea',
            "{$ack}Integramos cobros según su operación. {$ctaReg}", 'pagos'],
        ['horario|atienden|disponible|a\s+qu[eé]\s+hora|fin\s+de\s+semana|hours|available|weekend|business\s+hours',
            ($registered
                ? $hoursNow . ($isEn ? ' How else can I help?' : ' ¿En qué más le oriento?')
                : $hoursNow . ' ' . $ctaAsesor), 'horario'],
        ['pol[ií]tica|privacidad|t[eé]rminos|aviso\s+de\s+privacidad',
            'Claro: Privacidad y Términos están en el pie del sitio. Sus datos solo se usan para dar seguimiento. ¿Seguimos con ' . $focus . '?', 'legal'],
        ['^(hola|buenas|buen\s*d[ií]a|saludos|qu[eé]\s+tal|hey|hi|hello)\b',
            $postAgenda
                ? ($isEn
                    ? ('Hi' . ($nameLead !== '' ? ', ' . $nameLead : '') . '! What about ' . $focus . ' can I help with?')
                    : ('¡Qué tal' . ($nameLead !== '' ? ', ' . $nameLead : '') . '! ¿Sobre qué de ' . $focus . ' le ayudo?'))
                : ($isEn
                    ? "Hi! Happy to help with {$focus}. What would you like to know first?"
                    : "¡Qué tal! Con gusto le oriento sobre {$focus}. ¿Qué le interesa saber primero?"), 'saludo'],
        ['gracias|adi[oó]s|bye|nos\s+vemos|hasta\s+luego',
            '¡Gracias a usted! Cuando quiera retomamos y dejamos su solicitud registrada. Que tenga excelente día.', 'cierre'],
        ['servicios?|ofrecen|qu[eé]\s+hacen|en\s+qu[eé]\s+ayudan|cat[aá]logo\s+de\s+servicios|services|what\s+do\s+you\s+(do|offer)',
            $isEn ? "{$ack}We do websites, software, e-commerce, SEO/GEO, and AI. For your case the focus is {$focus}. {$blurb} {$ctaAsesor}" : "{$ack}Hacemos páginas web, software, e-commerce, SEO/GEO e IA. En su caso el foco es {$focus}. {$blurb} {$ctaAsesor}", 'servicios'],
        ['capacitaci[oó]n|entrenar|ense[nñ]an|tutorial|capacitar\s+al\s+equipo',
            "{$ack}Al entregar dejamos el uso claro y acompañamiento según el plan. {$ctaAsesor}", 'capacitacion'],
        ['integraci[oó]n|api|conectar\s+con|zapier|webhook|terceros',
            "{$ack}Sí contemplamos integraciones con herramientas externas según su operación. {$ctaReg}", 'integracion'],
        ['reporte|dashboard|indicadores|m[eé]tricas|estad[ií]sticas',
            "{$ack}Podemos incluir paneles y reportes para que vea resultados con claridad. {$ctaReg}", 'reportes'],
        ['logo|identidad|branding|dise[nñ]o\s+gr[aá]fico|colores\s+de\s+marca',
            "{$ack}Si ya tiene marca la respetamos; si necesita apoyo visual, lo revisamos en el alcance. {$ctaAsesor}", 'marca'],
        ['estafa|confiab|son\s+reales|fide|seguro\s+contratar',
            "{$ack}Somos ConlineWeb; el seguimiento queda registrado y un asesor le confirma alcance con claridad. {$ctaReg}", 'confianza'],
        ['quiero\s+empezar|quiero\s+contratar|vamos\s+adelante|me\s+interesa\s+contratar|arrancamos',
            $registered
                ? ($postAgenda
                    ? "{$ack}Excelente. ¿Le coordino llamada con un asesor o prefiere propuesta por escrito?"
                    : "{$ack}Excelente. ¿Agendamos llamada? Indíqueme día y hora.")
                : ($adviceMode
                    ? "{$ack}Excelente. Cuando quiera dar seguimiento, dígame y registro su solicitud. {$ctaReg}"
                    : "{$ack}Excelente. Para dejarle la solicitud registrada, ¿me comparte su teléfono a 10 dígitos?"), 'contratar'],
    ];

    if ($postAgenda && preg_match('/(llamada|agendar|cita|propuesta|opci[oó]n\s*[12]|me contacten|call|schedule|proposal)/u', $m)) {
        return [
            'reply' => $isEn
                ? "That is already noted. About {$focus}: {$blurb} What would you like to clarify first: scope, timeline, or investment?"
                : "Eso ya quedó anotado. Sobre {$focus}: {$blurb} ¿Qué le gustaría aclarar primero: alcance, tiempos o inversión?",
            'suggest_register' => false,
            'intent' => 'advice',
        ];
    }

    foreach ($scenarios as $row) {
        [$pattern, $answer, $intent] = $row;
        if (preg_match('/(' . $pattern . ')/u', $m)) {
            if ($regSuffix !== '' && !$registered) {
                $answer = rtrim($answer, '?') . '.' . $regSuffix;
            }
            return [
                'reply' => $answer,
                'suggest_register' => false,
                'intent' => $intent,
            ];
        }
    }

    if (preg_match('/^(s[ií]|sip|dale|sale|claro|ok|okay|de acuerdo|por supuesto|sure|yes|yeah|yep)\b/u', $m)) {
        return [
            'reply' => $postAgenda
                ? ($registered
                    ? ($namePrefix !== '' ? $namePrefix : '') . ($isEn ? "Perfect. What would you like to clarify about {$focus}?" : "Perfecto. ¿Qué le gustaría aclarar de {$focus}?")
                    : ($isEn ? "Perfect. What would you like to clarify about {$focus}?" : "Perfecto. ¿Qué le gustaría aclarar de {$focus}?"))
                : ($isEn
                    ? 'Perfect. To register your follow-up, what is your 10-digit phone number?'
                    : 'Perfecto. Para registrar su seguimiento, ¿me comparte su teléfono a 10 dígitos?'),
            'suggest_register' => false,
            'intent' => 'accept',
        ];
    }
    if (preg_match('/^(no|nop|nel|no gracias|mejor no|ahorita no|ahora no|no thanks|not now)\b/u', $m)) {
        return [
            'reply' => $isEn
                ? "No problem. We can continue here. What would you like to know about {$focus}?"
                : "Sin problema. Seguimos por aquí. ¿Qué le gustaría saber de {$focus}?",
            'suggest_register' => false,
            'intent' => 'decline',
        ];
    }
    if (preg_match('/^(despues|después|tal vez|talvez|luego|mas tarde|más tarde|lo pienso|later|maybe|not sure)\b/u', $m)) {
        return [
            'reply' => $isEn
                ? "Sure, whenever you are ready. Meanwhile, any questions about {$focus}?"
                : "Claro, cuando usted diga. Mientras, ¿qué duda tiene sobre {$focus}?",
            'suggest_register' => false,
            'intent' => 'defer',
        ];
    }

    return [
        'reply' => $registered
            ? ($isEn ? "{$ack}About {$focus}: what would you like to clarify?" : "{$ack}Sobre {$focus}: ¿qué le gustaría aclarar?")
            : ($isEn ? "{$ack}About {$focus}: what would you like to know?" : "{$ack}Sobre {$focus}: ¿qué le interesa saber?"),
        'suggest_register' => false,
        'intent' => 'general',
    ];
}
