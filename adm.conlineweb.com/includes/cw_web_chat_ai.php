<?php
/**
 * Asesor comercial web ConlineWeb — usa KB del sitio (páginas de servicio + guías).
 * IA cuando hay clave; si falla, el caller usa FAQ (cw_web_chat_reply).
 */

require_once __DIR__ . '/openai_config.php';
require_once __DIR__ . '/cw_seo_mexico_ai.php';

/**
 * Catálogo de páginas de servicio del sitio.
 * @return list<string>
 */
function cw_web_chat_ai_service_pages(string $servicio): array
{
    $map = [
        'desarrollo_web' => [
            'desarrollo-de-software.php',
            'paginas-web.php',
            'agencia-de-desarrollo-web.php',
            'agencia-de-desarrollo-web-mx.php',
            'proceso-de-trabajo.php',
            'demos-y-precios.php',
        ],
        'pagina_web' => [
            'paginas-web.php',
            'diseno-de-paginas-web.php',
            'agencia-de-desarrollo-web.php',
            'proceso-de-trabajo.php',
            'demos-y-precios.php',
            'asesoria-web-gratuita.php',
        ],
        'seo' => [
            'seo.php',
            'seo-leon.php',
            'includes/blog/content/seo-local-mexico-guia-completa.php',
            'includes/blog/content/cuanto-cuesta-pagina-web-profesional.php',
        ],
        'seo_local' => [
            'seo.php',
            'seo-leon.php',
            'includes/blog/content/seo-local-mexico-guia-completa.php',
        ],
        'geo' => [
            'seo.php',
            'seo-leon.php',
            'includes/blog/content/seo-local-mexico-guia-completa.php',
        ],
        'inteligencia_artificial' => [
            'soluciones-inteligencia-artificial.php',
            'inteligencia-artificial-leon.php',
            'includes/blog/content/beneficios-inteligencia-artificial-negocio.php',
            'includes/blog/content/chatbots-whatsapp-leads-24-7.php',
            'includes/blog/content/ia-generativa-negocios-mexico-geo.php',
        ],
        'automatizacion' => [
            'soluciones-inteligencia-artificial.php',
            'software-para-empresas.php',
            'includes/blog/content/integrar-crm-sitio-web.php',
        ],
        'ecommerce' => [
            'tienda-online.php',
            'tienda-online-leon.php',
            'includes/blog/content/tienda-online-mexico-pasos-lanzamiento.php',
            'includes/blog/content/como-mejorar-la-conversion-de-tu-ecommerce-en-mexico.php',
            'demos-y-precios.php',
        ],
        'software' => [
            'desarrollo-de-software.php',
            'software-para-empresas.php',
            'software-para-empresas-leon.php',
            'proceso-de-trabajo.php',
            'includes/blog/content/software-a-medida-beneficios-pymes-mexico.php',
            'includes/blog/content/como-elegir-software-a-medida-pyme-mexico.php',
            'includes/blog/content/software-a-medida-vs-saas.php',
            'demos-y-precios.php',
        ],
        'hosting' => [
            'hosting-administrado.php',
            'includes/blog/content/elegir-hosting-web-empresa.php',
            'includes/blog/content/https-ssl-importancia-sitio-web.php',
            'politica-soporte-sla.php',
        ],
        'otro' => [
            'index.php',
            'soluciones-corporativas.php',
            'proceso-de-trabajo.php',
            'demos-y-precios.php',
            'contacto.php',
            'asesoria-web-gratuita.php',
        ],
    ];
    return $map[$servicio] ?? $map['otro'];
}

/**
 * Guías / artículos según la duda del visitante (base de conocimiento).
 * @return list<string>
 */
function cw_web_chat_ai_topic_guides(string $mensaje): array
{
    $m = mb_strtolower($mensaje);
    $out = [];

    $rules = [
        'precio|costo|cuanto|cotiz|presupuesto|inversi' => [
            'demos-y-precios.php',
            'includes/blog/content/cuanto-cuesta-pagina-web-profesional.php',
            'politica-facturacion-pagos.php',
        ],
        'plazo|tiempo|entrega|proceso|c[oó]mo trabajan' => [
            'proceso-de-trabajo.php',
            'includes/blog/content/brief-de-proyecto-web-guia.php',
            'politica-garantia-servicios-digitales.php',
        ],
        'tienda|ecommerce|comercio' => [
            'tienda-online.php',
            'includes/blog/content/tienda-online-mexico-pasos-lanzamiento.php',
            'includes/blog/content/como-mejorar-la-conversion-de-tu-ecommerce-en-mexico.php',
        ],
        'seo|posicion|google|geo' => [
            'seo.php',
            'includes/blog/content/seo-local-mexico-guia-completa.php',
            'includes/blog/content/como-optimizar-seo-ecommerce.php',
        ],
        'software|sistema|erp|crm|app' => [
            'desarrollo-de-software.php',
            'software-para-empresas.php',
            'includes/blog/content/software-a-medida-beneficios-pymes-mexico.php',
            'includes/blog/content/como-elegir-software-a-medida-pyme-mexico.php',
        ],
        'ia|inteligencia|chatbot|automatiz|whatsapp' => [
            'soluciones-inteligencia-artificial.php',
            'includes/blog/content/beneficios-inteligencia-artificial-negocio.php',
            'includes/blog/content/chatbots-whatsapp-leads-24-7.php',
        ],
        'p[aá]gina|sitio|landing|dise[nñ]o|web' => [
            'paginas-web.php',
            'diseno-de-paginas-web.php',
            'includes/blog/content/que-debe-incluir-pagina-web-empresa.php',
            'includes/blog/content/landing-page-alta-conversion-mexico.php',
            'includes/blog/content/rediseno-web-cuando-conviene.php',
        ],
        'hosting|servidor|ssl|dominio' => [
            'hosting-administrado.php',
            'includes/blog/content/elegir-hosting-web-empresa.php',
            'includes/blog/content/https-ssl-importancia-sitio-web.php',
        ],
        'soporte|garant[ií]a|mantenimiento|sla' => [
            'politica-garantia-servicios-digitales.php',
            'politica-soporte-sla.php',
            'includes/blog/content/mantenimiento-web-que-debe-incluir.php',
        ],
        'privacidad|t[eé]rminos|legal|datos' => [
            'politica-privacidad.php',
            'terminos-condiciones.php',
            'centro-politicas.php',
        ],
        'leads|conversi[oó]n|ventas|formulario' => [
            'includes/blog/content/formularios-web-captacion-leads.php',
            'includes/blog/content/diseno-ux-conversion-sitios-web.php',
            'includes/blog/content/landing-page-alta-conversion-mexico.php',
        ],
        'agencia|contratar|elegir' => [
            'agencia-de-desarrollo-web.php',
            'includes/blog/content/como-elegir-agencia-desarrollo-web-mexico.php',
            'proceso-de-trabajo.php',
        ],
    ];

    foreach ($rules as $pattern => $pages) {
        if (preg_match('/(' . $pattern . ')/u', $m)) {
            foreach ($pages as $p) {
                $out[] = $p;
            }
        }
    }
    return array_values(array_unique($out));
}

/**
 * Semilla de rutas a cargar desde el sitio real.
 */
function cw_web_chat_ai_knowledge_seed(string $pagina, string $servicio, string $interes, string $mensaje): string
{
    $bits = [];
    if ($pagina !== '') {
        $bits[] = $pagina;
    }
    if ($interes !== '') {
        $bits[] = $interes;
    }
    if ($mensaje !== '') {
        $bits[] = $mensaje;
    }
    foreach (cw_web_chat_ai_service_pages($servicio) as $p) {
        $bits[] = $p;
    }
    foreach (cw_web_chat_ai_topic_guides($mensaje) as $g) {
        $bits[] = $g;
    }
    $bits[] = 'proceso-de-trabajo.php';
    $bits[] = 'demos-y-precios.php';
    $bits[] = 'politica-garantia-servicios-digitales.php';
    $bits[] = 'soluciones-corporativas.php';
    return implode(' ', array_unique($bits));
}

/**
 * Carga extractos limpios (texto) de páginas/guías del sitio.
 */
function cw_web_chat_ai_load_site_knowledge(string $seed, int $maxFiles = 5, int $maxCharsEach = 4500): string
{
    require_once __DIR__ . '/cw_site_ai_maintain.php';
    require_once __DIR__ . '/cw_seo_mexico_ai_prompt.php';

    $root = function_exists('cw_seo_mexico_autofix_site_root') ? cw_seo_mexico_autofix_site_root() : null;
    if ($root === null) {
        return '';
    }

    $paths = [];
    if (function_exists('cw_seo_mexico_ai_prompt_extract_paths')) {
        $paths = cw_seo_mexico_ai_prompt_extract_paths($seed);
    }
    // También tomar .php / includes/... explícitos del seed
    if (preg_match_all('#\b([a-z0-9_\-/]+\.php)\b#i', $seed, $m)) {
        foreach ($m[1] as $p) {
            $paths[] = str_replace('\\', '/', (string) $p);
        }
    }
    $paths = array_values(array_unique($paths));
    if ($paths === []) {
        return '';
    }

    $chunks = [];
    foreach ($paths as $raw) {
        if (count($chunks) >= $maxFiles) {
            break;
        }
        $resolved = function_exists('cw_site_ai_maintain_resolve_path')
            ? cw_site_ai_maintain_resolve_path($raw)
            : null;
        if ($resolved === null) {
            // Intento directo bajo root
            $try = ltrim(str_replace('\\', '/', $raw), '/');
            if (str_contains($try, '..')) {
                continue;
            }
            $absTry = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $try);
            if (!is_readable($absTry) || !is_file($absTry)) {
                continue;
            }
            $resolved = $try;
        }
        $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $resolved);
        if (!is_readable($abs) || !is_file($abs)) {
            continue;
        }
        $rawFile = (string) file_get_contents($abs);
        $body = $rawFile;
        if (preg_match('/<body[^>]*>(.*)<\/body>/is', $rawFile, $bm)) {
            $body = (string) $bm[1];
        }
        $body = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $body) ?? $body;
        $body = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $body) ?? $body;
        $text = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', trim((string) $text)) ?? '';
        if (mb_strlen($text) < 80) {
            continue;
        }
        $excerpt = mb_substr($text, 0, $maxCharsEach);
        $chunks[] = "--- FUENTE: {$resolved} ---\n{$excerpt}\n--- FIN ---";
    }

    if ($chunks === []) {
        return '';
    }
    return "BASE DE CONOCIMIENTO DEL SITIO CONLINEWEB (páginas de servicio, proceso, guías y políticas reales).\n"
        . "Úsala para resolver dudas con precisión. No inventes precios fijos, plazos cerrados ni clientes.\n\n"
        . implode("\n\n", $chunks);
}

/**
 * Prompt de sistema: asesor comercial profesional.
 */
function cw_web_chat_ai_system_prompt(
    string $servicio,
    string $interes,
    string $pagina,
    bool $citaConfirmada,
    bool $adviceMode,
    string $nombre = '',
    string $telefono = '',
    bool $registered = false,
    bool $businessHoursOpen = true,
    string $businessHoursNote = '',
    bool $registrationPending = false,
    string $registrationStep = '',
    string $locale = 'es'
): string {
    $isEn = strtolower($locale) === 'en';
    $flags = [];
    if ($citaConfirmada) {
        $flags[] = $isEn
            ? 'The visitor ALREADY confirmed a contact time. Do not ask to schedule again unless they ask to CHANGE or RESCHEDULE; they may give a new day/time in chat.'
            : 'El visitante YA confirmó fecha de contacto. No vuelvas a pedir agendar salvo que pida CAMBIAR o REAGENDAR; puede indicar nuevo día y hora en el chat.';
    }
    if ($registered) {
        $flags[] = $isEn
            ? 'Visitor ALREADY registered as a lead. Full advisory on ConlineWeb services. They can use WhatsApp or schedule a call (day and time).'
            : 'Visitante YA registrado en leads. Asesoría completa sobre servicios ConlineWeb. Puede usar WhatsApp o agendar llamada (día y hora).';
        if ($adviceMode) {
            $flags[] = $isEn
                ? 'Active advisory mode. Do NOT ask for name or phone again.'
                : 'Modo asesoría activa. NO pidas nombre ni teléfono otra vez.';
        }
    } elseif ($registrationPending) {
        $step = $registrationStep !== '' ? $registrationStep : 'nombre';
        $flags[] = $isEn
            ? 'REGISTRATION PENDING (commercial priority). The chat is collecting details before enabling WhatsApp and advisor calls.'
            : 'REGISTRO PENDIENTE (prioridad comercial). El chat está recopilando datos antes de habilitar WhatsApp y llamada con asesor.';
        $flags[] = $isEn
            ? 'ALWAYS answer what they just wrote (price, timeline, question, odd comment, or ambiguous reply) with 1–2 useful sentences about ConlineWeb services (' . $servicio . ' / ' . $interes . ').'
            : 'Responde SIEMPRE a lo que acaba de escribir (precio, plazo, duda, comentario raro o respuesta ambigua) con 1-2 oraciones útiles sobre servicios ConlineWeb (' . $servicio . ' / ' . $interes . ').';
        $flags[] = $isEn
            ? 'Do NOT invent that they already registered. Do NOT give long advisory or hard closes yet.'
            : 'NO inventes que ya registró. NO des asesoría larga ni cierres comerciales todavía.';
        $flags[] = $isEn
            ? 'Do NOT repeat in your text the data the chat will ask next (the frontend resumes); only answer the question briefly.'
            : 'NO repitas en tu texto el dato que el chat pedirá después (el frontend lo retoma); solo responde la duda con brevedad.';
        if ($step === 'nombre') {
            $flags[] = $isEn ? 'The visitor NAME is still missing.' : 'Falta el NOMBRE del visitante.';
        } elseif ($step === 'correo') {
            $flags[] = $isEn ? 'EMAIL is still missing (optional; they may skip).' : 'Falta CORREO (opcional; puede omitir).';
        } elseif ($step === 'telefono') {
            $flags[] = $isEn ? 'PHONE (10 digits) is still missing (required to register).' : 'Falta TELÉFONO a 10 dígitos (obligatorio para registrar).';
        }
    } else {
        $flags[] = $isEn
            ? 'Answer briefly about ConlineWeb services.'
            : 'Responde brevemente sobre servicios ConlineWeb.';
    }
    if ($registered && $nombre !== '') {
        $flags[] = ($isEn ? 'Visitor name (use naturally): ' : 'Nombre del visitante (úsalo con naturalidad): ') . $nombre . '.';
    }
    if ($registered && $telefono !== '') {
        $flags[] = $isEn
            ? 'Phone is already registered; do not ask again.'
            : 'Ya tiene teléfono registrado; no lo vuelvas a solicitar.';
    }
    $flags[] = $isEn
        ? 'ConlineWeb business hours: Monday–Friday 8:00 a.m.–8:00 p.m., Saturday 9:00 a.m.–4:00 p.m., closed Sunday (Mexico City time).'
        : 'Horario de atención ConlineWeb: lunes a viernes 8:00–20:00, sábados 9:00–16:00, domingos cerrados (hora Ciudad de México).';
    if (!$businessHoursOpen) {
        $flags[] = $isEn
            ? 'We are CURRENTLY OUTSIDE business hours. If they want immediate help, mention WhatsApp; an advisor will follow up in the next business window.'
            : 'AHORA estamos FUERA de horario. Si pide atención inmediata, indica WhatsApp; un asesor contactará en el próximo horario hábil.';
    } else {
        $flags[] = $isEn
            ? ('We are CURRENTLY WITHIN business hours' . ($registered ? '; you may offer advisor coordination.' : '.'))
            : ('Ahora estamos DENTRO de horario hábil' . ($registered ? '; puede ofrecer coordinación con asesor.' : '.'));
    }
    if ($businessHoursNote !== '') {
        $flags[] = ($isEn ? 'Hours reference: ' : 'Referencia horario: ') . $businessHoursNote;
    }

    if ($isEn) {
        $prompt = <<<'PROMPT'
You are the senior sales advisor for ConlineWeb on a live website chat (US English audience).

ROLE:
- Sell professionally: websites, custom software, online stores, SEO/GEO, hosting, AI and automation.
- Rely ONLY on ConlineWeb site knowledge (service pages, process, demos/pricing, guides and policies) when available.
- Sound like an expert commercial advisor: clear, confident, courteous — not robotic and not slangy.

GOLDEN RULE — COHERENCE:
1) Always answer WHAT the person JUST wrote.
2) If they ask about price → talk about investment and scope factors (never invent fixed figures).
3) If they ask about timelines → talk about staged process and dependencies.
4) If they share their case → acknowledge it and connect the right service.
5) Never invent that they already accepted a call, proposal, or phone — but if context shows they already registered, treat them as an existing lead and do not restart intake.

HOW TO RESOLVE QUESTIONS:
- Clarify with useful, concrete information (benefits, typical scope, how you work, what is generally included).
- Use site knowledge; if an exact fact is missing (fixed price, hard date), say so transparently and offer an advisor to confirm for their case.
- Differentiate services when helpful without overwhelming.
- Do not paste long site blocks; synthesize like an advisor on a sales call.

TONE:
- US English. Default to polite “you”; mirror casual tone if the visitor is casual.
- REQUIRED BREVITY: 1–2 short sentences (max ~280 characters). Straight to the point.
- No corporate filler, no emojis unless the visitor used them first.
- No heavy markdown or lists.

COMMERCIAL GOAL (no hard pressure):
1) Answer the concrete question with authority in few words, tied to ConlineWeb services.
2) If registration is pending: brief guidance and let the chat flow ask for name/email/phone.
3) If ALREADY registered: full advisory; may invite WhatsApp or give/change day and time for a call.
4) Never invent prices, exact deadlines, clients, or fake results.
5) If the message is ambiguous (“sure”, “ok”, emoji, random topic): answer naturally and return to the service context without sounding robotic.

CLOSE:
- Optional: ONE short question tied to their message (only if useful).
- Do not say “reply yes, no, or later”.
PROMPT;
    } else {
        $prompt = <<<'PROMPT'
Eres el asesor comercial senior de ConlineWeb (México), en un chat en vivo del sitio web.

ROL:
- Vendes con profesionalismo: páginas web, software a medida, tiendas en línea, SEO/GEO, hosting, IA y automatización.
- Dominas la oferta de ConlineWeb usando SOLO la base de conocimiento del sitio (páginas de servicio, proceso de trabajo, demos/precios, guías y políticas) cuando esté disponible.
- Suenas a ejecutivo comercial experto: claro, seguro, cortés, sin slang excesivo ni tono de robot.

REGLA DE ORO — COHERENCIA:
1) Responde siempre a LO QUE ACABA DE ESCRIBIR la persona.
2) Si pregunta precio → habla de inversión y factores de alcance (sin inventar cifras cerradas).
3) Si pregunta plazos → habla de proceso por etapas y dependencias.
4) Si cuenta su caso → retoma su caso y conecta el servicio adecuado.
5) No inventes que ya aceptó llamada, propuesta o teléfono — pero si el contexto indica que YA registró datos, trátalo como lead existente y no reinicies el flujo.

CÓMO RESOLVER DUDAS (muy importante):
- Primero aclara la duda con información útil y concreta (beneficios, alcance típico, cómo trabajan, qué incluye a nivel general).
- Usa la base de conocimiento del sitio; si un dato exacto no está (precio fijo, fecha cerrada), dilo con transparencia y ofrece que un asesor lo confirme con su caso.
- Diferencia servicios cuando convenga (ej. web vs software vs tienda vs SEO vs IA) sin abrumar.
- No copies bloques largos del sitio; sintetiza como lo haría un asesor en una llamada comercial.

TONO PROFESIONAL:
- Español de México, trato de usted por defecto; si el visitante usa tú, responde de tú.
- BREVEDAD OBLIGATORIA: 1 a 2 oraciones cortas (máximo ~280 caracteres). Directo al punto.
- Sin relleno corporativo (“estimado cliente”, “nos especializamos…”, párrafos largos).
- Cero emojis salvo que el visitante los use primero.
- Sin markdown pesado ni listas.

OBJETIVO COMERCIAL (sin presión agresiva):
1) Responder la duda concreta con autoridad en pocas palabras, conectada a servicios ConlineWeb.
2) Si el registro está pendiente: orientar breve y dejar que el flujo del chat pida nombre/correo/teléfono.
3) Si YA está registrado: asesoría completa; puede invitar a WhatsApp o indicar/cambiar día y hora para llamada.
4) Nunca inventar precios, plazos exactos, clientes ni resultados falsos.
5) Si el mensaje es ambiguo («claro», «ok», emoji, tema random): responde con naturalidad y vuelve al contexto del servicio sin sonar robótico.

CIERRE:
- Opcional: UNA pregunta corta ligada a su mensaje (solo si aporta).
- No digas “responda sí, no o después”.
PROMPT;
    }

    $prompt .= ($isEn ? "\n\nVisit context — detected service: {$servicio}; interest: {$interes}; page: {$pagina}.\n"
        : "\n\nContexto de esta visita — servicio detectado: {$servicio}; interés: {$interes}; página: {$pagina}.\n");
    if ($flags !== []) {
        $prompt .= implode(' ', $flags) . "\n";
    }
    return $prompt;
}

/**
 * Intenta respuesta con IA. Null = usar fallback de reglas.
 *
 * @param list<array{role:string,content:string}> $history
 * @return array{reply:string,intent:string,source:string}|null
 */
function cw_web_chat_ai_try(
    mysqli $conn,
    string $mensaje,
    string $servicio,
    string $pagina,
    string $interes = '',
    bool $citaConfirmada = false,
    bool $adviceMode = false,
    array $history = [],
    string $nombre = '',
    string $telefono = '',
    bool $registered = false,
    bool $businessHoursOpen = true,
    string $businessHoursNote = '',
    bool $registrationPending = false,
    string $registrationStep = '',
    string $locale = 'es'
): ?array {
    if (!cw_seo_mexico_ai_available()) {
        return null;
    }

    try {
        require_once __DIR__ . '/cw_site_ai_brain.php';

        $brain = '';
        if (function_exists('cw_site_ai_brain_context')) {
            $brain = cw_site_ai_brain_context($conn, 16);
        }

        $seed = cw_web_chat_ai_knowledge_seed($pagina, $servicio, $interes, $mensaje);
        $pageCtx = cw_web_chat_ai_load_site_knowledge($seed, 5, 4500);

        // Fallback al extractor genérico si el loader dedicado no trajo nada
        if ($pageCtx === '' && function_exists('cw_seo_mexico_ai_prompt_page_context')) {
            require_once __DIR__ . '/cw_seo_mexico_ai_prompt.php';
            $pageCtx = (string) cw_seo_mexico_ai_prompt_page_context($seed);
        }

        $system = cw_web_chat_ai_system_prompt(
            $servicio,
            $interes,
            $pagina,
            $citaConfirmada,
            $adviceMode,
            $nombre,
            $telefono,
            $registered,
            $businessHoursOpen,
            $businessHoursNote,
            $registrationPending,
            $registrationStep,
            $locale
        );
        if ($brain !== '') {
            $system .= "\n\n=== CEREBRO INTERNO CONLINEWEB ===\n" . mb_substr($brain, 0, 5000) . "\n";
        }
        if ($pageCtx !== '') {
            $system .= "\n\n" . mb_substr($pageCtx, 0, 16000) . "\n";
        }

        $messages = [
            ['role' => 'system', 'content' => $system],
        ];
        $hist = array_slice($history, -10);
        foreach ($hist as $h) {
            $role = ($h['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $content = trim((string) ($h['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            $messages[] = ['role' => $role, 'content' => mb_substr($content, 0, 1400)];
        }
        $messages[] = ['role' => 'user', 'content' => mb_substr($mensaje, 0, 2000)];

        // Temperatura media: profesional y coherente, no creativo inventivo
        $ai = cw_seo_mexico_ai_chat($messages, 0.45, false);
        if (empty($ai['ok'])) {
            error_log('cw_web_chat_ai_try fail: ' . (string) ($ai['error'] ?? 'unknown'));
            return null;
        }
        $text = trim((string) ($ai['content'] ?? ''));
        if ($text !== '' && ($text[0] === '{' || $text[0] === '[')) {
            $decoded = json_decode($text, true);
            if (is_array($decoded)) {
                $text = trim((string) ($decoded['reply'] ?? $decoded['message'] ?? $decoded['content'] ?? $text));
            }
        }
        if ($text === '' || mb_strlen($text) < 8) {
            return null;
        }

        return [
            'reply' => mb_substr($text, 0, 380),
            'intent' => 'ai_sales',
            'source' => 'ai',
        ];
    } catch (Throwable $e) {
        error_log('cw_web_chat_ai_try exception: ' . $e->getMessage());
        return null;
    }
}
