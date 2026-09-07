<?php
/**
 * Generación de plantillas de mailing con OpenAI (texto + imágenes).
 * No guarda sola: el admin decide guardar después del preview.
 *
 * Importante: OpenAI no puede descargar bien adm.conlineweb.com (timeouts).
 * Las imágenes locales se envían como data URI (base64), no como URL remota.
 */
declare(strict_types=1);

require_once __DIR__ . '/openai_config.php';
require_once __DIR__ . '/cw_seo_mexico_ai.php';
require_once __DIR__ . '/cw_mailing_service.php';

/**
 * Convierte URLs de media adm / archivos locales a data URI para visión.
 *
 * @param list<string> $imageUrls
 * @return array{vision:list<string>,public:list<string>}
 */
function cw_mailing_ai_prepare_images(array $imageUrls): array
{
    $vision = [];
    $public = [];

    foreach (array_slice($imageUrls, 0, 3) as $raw) {
        $u = trim((string) $raw);
        if ($u === '') {
            continue;
        }

        if (str_starts_with($u, 'data:image/')) {
            $vision[] = $u;
            continue;
        }

        $filename = '';
        if (preg_match('#/mailing/media\.php\?f=([a-zA-Z0-9._-]+)#', $u, $m)) {
            $filename = $m[1];
        } elseif (preg_match('#^https?://#i', $u) && str_contains($u, 'media.php') && preg_match('/[?&]f=([a-zA-Z0-9._-]+)/', $u, $m2)) {
            $filename = $m2[1];
        } elseif (preg_match('/^ml_[a-zA-Z0-9._-]+$/', $u)) {
            $filename = $u;
        }

        if ($filename !== '') {
            $path = cw_mailing_uploads_dir() . '/' . basename($filename);
            $publicUrl = cw_mailing_media_url(basename($filename));
            $public[] = $publicUrl;
            $dataUri = cw_mailing_ai_file_to_data_uri($path);
            if ($dataUri !== null) {
                $vision[] = $dataUri;
            }
            continue;
        }

        // URL externa (no adm): OpenAI sí puede intentar bajarla
        if (preg_match('#^https?://#i', $u) && !preg_match('#adm\.conlineweb\.com#i', $u)) {
            $vision[] = $u;
            $public[] = $u;
        }
    }

    return [
        'vision' => array_values(array_unique($vision)),
        'public' => array_values(array_unique($public)),
    ];
}

function cw_mailing_ai_file_to_data_uri(string $path): ?string
{
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }
    $size = filesize($path);
    if ($size === false || $size < 1 || $size > 4 * 1024 * 1024) {
        return null;
    }

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mimeMap = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];
    if (!isset($mimeMap[$ext])) {
        return null;
    }

    $dataUri = cw_mailing_ai_compress_to_data_uri($path);
    if ($dataUri !== null) {
        return $dataUri;
    }

    $bin = file_get_contents($path);
    if ($bin === false || $bin === '') {
        return null;
    }

    return 'data:' . $mimeMap[$ext] . ';base64,' . base64_encode($bin);
}

function cw_mailing_ai_compress_to_data_uri(string $path): ?string
{
    if (!function_exists('imagecreatefromstring')) {
        return null;
    }
    $bin = @file_get_contents($path);
    if ($bin === false || $bin === '') {
        return null;
    }
    $img = @imagecreatefromstring($bin);
    if ($img === false) {
        return null;
    }

    $w = imagesx($img);
    $h = imagesy($img);
    $max = 1280;
    if ($w > $max || $h > $max) {
        $scale = min($max / max(1, $w), $max / max(1, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        if ($dst === false) {
            imagedestroy($img);
            return null;
        }
        imagealphablending($dst, true);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);
        $img = $dst;
    }

    ob_start();
    imagejpeg($img, null, 78);
    $jpeg = ob_get_clean();
    imagedestroy($img);
    if (!is_string($jpeg) || $jpeg === '') {
        return null;
    }

    return 'data:image/jpeg;base64,' . base64_encode($jpeg);
}

/**
 * Parsea texto de links: una por línea "Etiqueta | https://..." o solo URL.
 *
 * @return list<array{title:string,text:string,url:string}>
 */
function cw_mailing_ai_parse_links_input(string $raw): array
{
    $raw = trim($raw);
    if ($raw === '') {
        return [];
    }
    $out = [];
    foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
        $line = trim((string) $line);
        if ($line === '') {
            continue;
        }
        $title = '';
        $url = '';
        if (str_contains($line, '|')) {
            [$title, $url] = array_map('trim', explode('|', $line, 2));
        } elseif (preg_match('#^(https?://\S+)\s+(.+)$#u', $line, $m)) {
            $url = $m[1];
            $title = $m[2];
        } elseif (preg_match('#^(.+?)\s+(https?://\S+)$#u', $line, $m)) {
            $title = $m[1];
            $url = $m[2];
        } else {
            $url = $line;
        }
        if (!preg_match('#^https?://#i', $url)) {
            continue;
        }
        if ($title === '') {
            $host = parse_url($url, PHP_URL_HOST);
            $title = is_string($host) && $host !== '' ? $host : 'Enlace';
        }
        $out[] = ['title' => $title, 'text' => '', 'url' => $url, 'price' => ''];
        if (count($out) >= 6) {
            break;
        }
    }

    return $out;
}

/**
 * Inyecta block links en el HTML del draft si el usuario aportó URLs.
 *
 * @param array<string,mixed> $draft
 * @param list<array<string,string>> $links
 * @return array<string,mixed>
 */
function cw_mailing_ai_ensure_links_block(array $draft, array $links): array
{
    $links = array_values(array_filter($links, static function ($row) {
        return is_array($row) && preg_match('#^https?://#i', (string) ($row['url'] ?? ''));
    }));
    if ($links === []) {
        return $draft;
    }
    $html = (string) ($draft['body_html'] ?? '');
    // Evitar duplicar si ya hay varios href en una card de enlaces marcada.
    if (str_contains($html, '<!--cw-links-->')) {
        return $draft;
    }
    $chunk = '<!--cw-links-->' . cw_mailing_ai_block_links($links) . '<!--/cw-links-->';
    $body = function_exists('cw_mailing_strip_design_meta_comment')
        ? cw_mailing_strip_design_meta_comment($html)
        : (preg_replace('/^<!--cwml:\{.*\}-->/s', '', $html, 1) ?? $html);
    $meta = ($body !== $html) ? (string) substr($html, 0, strlen($html) - strlen($body)) : '';
    // Insertar antes del último CTA si existe, si no al final del body.
    if (preg_match('/<div style="margin:10px 0 14px;">/i', $body)) {
        $body = preg_replace('/<div style="margin:10px 0 14px;">/i', $chunk . '<div style="margin:10px 0 14px;">', $body, 1) ?? ($body . $chunk);
    } else {
        $body .= $chunk;
    }
    $draft['body_html'] = $meta . $body;

    return $draft;
}

/**
 * @param list<string> $imageUrls
 * @param list<array<string,string>>|string $extraLinks
 * @return array{ok:bool,error?:string,draft?:array<string,mixed>}
 */
function cw_mailing_ai_generate(string $theme, string $brief, string $ctaHint = '', array $imageUrls = [], $extraLinks = []): array
{
    if (!cw_seo_mexico_ai_available()) {
        return ['ok' => false, 'error' => 'OpenAI no configurada'];
    }

    $theme = trim($theme);
    $brief = trim($brief);
    if ($brief === '') {
        return ['ok' => false, 'error' => 'Escribe un prompt para generar la plantilla'];
    }
    if ($theme === '') {
        $theme = 'General';
    }
    if (mb_strlen($brief) > 4000) {
        $brief = mb_substr($brief, 0, 4000);
    }

    $linkItems = is_string($extraLinks)
        ? cw_mailing_ai_parse_links_input($extraLinks)
        : (is_array($extraLinks) ? $extraLinks : []);

    $prepared = cw_mailing_ai_prepare_images($imageUrls);
    $visionUrls = $prepared['vision'];
    $publicUrls = $prepared['public'];

    $system = cw_mailing_ai_system_prompt('generate');

    $userText = "BRIEF / PROMPT (OBEDECE TODO lo que pide; si pide precios, ponlos):\n{$brief}\n\n"
        . "Tema / categoría (opcional): {$theme}\n"
        . cw_mailing_ai_price_catalog_prompt()
        . "\nMarca visual: ConlineWeb — corporativo México, fondo #f1f1f1, tarjeta blanca, navy #000147. Sin negro ni neón.\n";
    if (cw_mailing_ai_prompt_wants_prices($brief)) {
        $userText .= "\nOBLIGATORIO EN ESTA PIEZA: incluye un block type=pricing con 2 o 3 planes (title + price + text). "
            . "Usa montos del catálogo o los que el usuario escribió. El correo DEBE mostrar cifras en MXN.\n";
    }
    if (trim($ctaHint) !== '') {
        $userText .= "\nCTA principal (footer): " . trim($ctaHint) . "\n";
    }
    if ($linkItems !== []) {
        $userText .= "\nLINKS ADICIONALES OBLIGATORIOS (inclúyelos en block type=links y/o como url en channels/pricing):\n";
        foreach ($linkItems as $i => $row) {
            $userText .= ($i + 1) . ') ' . ($row['title'] ?? 'Link') . ' → ' . ($row['url'] ?? '') . "\n";
        }
    }
    if ($visionUrls !== []) {
        $userText .= "\nHay " . count($visionUrls) . " imagen(es) adjuntas embebidas para que las veas.\n";
    }
    if ($publicUrls !== []) {
        $userText .= "\nURLs públicas permitidas para image_url (elige una):\n";
        foreach ($publicUrls as $i => $url) {
            $userText .= ($i + 1) . ') ' . $url . "\n";
        }
    } else {
        $userText .= "\nSin URL pública de imagen; image_url vacío.\n";
    }

    $userContent = [
        ['type' => 'text', 'text' => $userText],
    ];
    foreach ($visionUrls as $url) {
        $userContent[] = [
            'type' => 'image_url',
            'image_url' => [
                'url' => $url,
                'detail' => 'low',
            ],
        ];
    }

    $messages = [
        ['role' => 'system', 'content' => $system],
        ['role' => 'user', 'content' => $userContent],
    ];

    $chat = cw_mailing_ai_chat_multimodal($messages, 0.72);
    if (empty($chat['ok'])) {
        return ['ok' => false, 'error' => (string) ($chat['error'] ?? 'Error al generar')];
    }

    $data = cw_mailing_ai_decode_json((string) ($chat['content'] ?? ''));
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'La IA no devolvió JSON válido', 'raw' => (string) ($chat['content'] ?? '')];
    }

    $draft = cw_mailing_ai_normalize_draft($data, [
        'title' => $theme,
        'theme' => $theme,
        'cta_url' => $ctaHint,
    ], $publicUrls);
    if (cw_mailing_ai_prompt_wants_prices($brief)) {
        $draft = cw_mailing_ai_ensure_pricing_block($draft, $data, $brief);
    }
    if ($linkItems !== []) {
        $draft = cw_mailing_ai_ensure_links_block($draft, $linkItems);
    }
    $draft['active'] = 0;

    if ($draft['subject'] === '' || $draft['body_html'] === '') {
        return ['ok' => false, 'error' => 'Borrador incompleto (falta asunto o cuerpo)'];
    }

    return ['ok' => true, 'draft' => $draft];
}

/**
 * Reescribe una plantilla existente según un prompt. No guarda sola.
 *
 * @param array<string,mixed> $current
 * @param list<string> $imageUrls
 * @param list<array<string,string>>|string $extraLinks
 * @return array{ok:bool,error?:string,draft?:array<string,mixed>}
 */
function cw_mailing_ai_improve(array $current, string $prompt, array $imageUrls = [], $extraLinks = []): array
{
    if (!cw_seo_mexico_ai_available()) {
        return ['ok' => false, 'error' => 'OpenAI no configurada'];
    }

    $prompt = trim($prompt);
    if ($prompt === '') {
        return ['ok' => false, 'error' => 'Escribe qué quieres mejorar'];
    }
    if (mb_strlen($prompt) > 2500) {
        $prompt = mb_substr($prompt, 0, 2500);
    }

    $linkItems = is_string($extraLinks)
        ? cw_mailing_ai_parse_links_input($extraLinks)
        : (is_array($extraLinks) ? $extraLinks : []);

    $title = trim((string) ($current['title'] ?? ''));
    $theme = trim((string) ($current['theme'] ?? 'General'));
    $subject = trim((string) ($current['subject'] ?? ''));
    $preheader = trim((string) ($current['preheader'] ?? ''));
    $body = trim((string) ($current['body_html'] ?? ''));
    $ctaLabel = trim((string) ($current['cta_label'] ?? ''));
    $ctaUrl = trim((string) ($current['cta_url'] ?? ''));
    $imageUrl = trim((string) ($current['image_url'] ?? ''));

    if ($subject === '' && $body === '') {
        return cw_mailing_ai_generate(
            $theme !== '' ? $theme : 'General',
            $prompt,
            $ctaUrl,
            $imageUrls !== [] ? $imageUrls : ($imageUrl !== '' ? [$imageUrl] : []),
            $linkItems
        );
    }

    $meta = function_exists('cw_mailing_parse_design_meta')
        ? cw_mailing_parse_design_meta($body)
        : ['kicker' => '', 'headline' => '', 'lead' => '', 'layout' => 'corporativo', 'body' => $body];
    $plain = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($meta['body'] ?? $body))) ?? '');
    if (mb_strlen($plain) > 700) {
        $plain = mb_substr($plain, 0, 700) . '…';
    }

    $extraImages = $imageUrls;
    if ($imageUrl !== '') {
        array_unshift($extraImages, $imageUrl);
    }
    $prepared = cw_mailing_ai_prepare_images($extraImages);
    $visionUrls = $prepared['vision'];
    $publicUrls = $prepared['public'];

    $beforeFp = cw_mailing_ai_draft_fingerprint([
        'subject' => $subject,
        'headline' => (string) ($meta['headline'] ?? ''),
        'body_html' => $body,
    ]);

    $userCore = "INSTRUCCIÓN DE CAMBIO (aplícala SOBRE la plantilla actual; edición quirúrgica, no rediseño total):\n{$prompt}\n\n"
        . cw_mailing_ai_price_catalog_prompt() . "\n"
        . "ESTADO ACTUAL:\n"
        . "- title: {$title}\n"
        . "- theme: {$theme}\n"
        . "- subject: {$subject}\n"
        . "- preheader: {$preheader}\n"
        . "- layout: " . ($meta['layout'] ?? 'corporativo') . "\n"
        . "- kicker: " . ($meta['kicker'] ?? '') . "\n"
        . "- headline: " . ($meta['headline'] ?? '') . "\n"
        . "- lead: " . ($meta['lead'] ?? '') . "\n"
        . "- cta_label: {$ctaLabel}\n"
        . "- cta_url: {$ctaUrl}\n"
        . "- image_url: {$imageUrl}\n"
        . "- extracto del cuerpo: {$plain}\n\n"
        . "REGLAS DE ESTABILIDAD (nivel agencia B2B — clientes premium):\n"
        . "- Aplica SOLO lo que pide el prompt. No reescribas módulos que no mencionó.\n"
        . "- PROHIBIDO vaciar o cambiar image_url si ya existe (conserva la URL exacta).\n"
        . "- PROHIBIDO cambiar textos de channels WhatsApp/Google (van fijos en el sistema).\n"
        . "- Conserva cta_label y cta_url salvo que el prompt pida otro CTA.\n"
        . "- Mantén tono corporativo sobrio. Sin emojis ni clickbait.\n"
        . "- Devuelve JSON nuevo con los cambios pedidos incorporados.\n";
    if (cw_mailing_ai_prompt_wants_prices($prompt)) {
        $userCore .= "OBLIGATORIO PRECIOS: añade o reemplaza un block type=pricing con precios visibles (title, price, text). "
            . "Si el usuario escribió montos, usa esos. Si no, usa el catálogo oficial. El HTML debe contener \$ y MXN.\n\n";
    }
    if ($linkItems !== []) {
        $userCore .= "LINKS A INCLUIR (block type=links y/o url en channels/pricing):\n";
        foreach ($linkItems as $i => $row) {
            $userCore .= ($i + 1) . ') ' . ($row['title'] ?? 'Link') . ' → ' . ($row['url'] ?? '') . "\n";
        }
        $userCore .= "\n";
    }

    if ($imageUrl !== '') {
        $userCore .= "\nIMAGEN HERO (OBLIGATORIO): image_url debe ser EXACTAMENTE esta URL, no la vacíes ni inventes otra:\n{$imageUrl}\n";
    } elseif ($publicUrls !== []) {
        $userCore .= "\nURLs públicas permitidas para image_url:\n";
        foreach ($publicUrls as $i => $url) {
            $userCore .= ($i + 1) . ') ' . $url . "\n";
        }
    }

    $userCore .= "\nBLOQUE channels (si lo usas): textos FIJOS, no los reescribas:\n"
        . "- WhatsApp → \"Contáctanos para resolver tus dudas.\"\n"
        . "- Google → \"Infórmate más sobre nuestros servicios.\"\n"
        . "Solo puedes cambiar urls; títulos WhatsApp/Google; textos exactos de arriba.\n";

    $fallback = [
        'title' => $title !== '' ? $title : 'Plantilla',
        'theme' => $theme,
        'subject' => $subject,
        'preheader' => $preheader,
        'cta_label' => $ctaLabel,
        'cta_url' => $ctaUrl,
        'image_url' => $imageUrl,
    ];

    $draft = null;
    $lastError = '';
    for ($attempt = 1; $attempt <= 2; $attempt++) {
        $userText = $userCore;
        if ($attempt === 2) {
            $userText .= "\nREINTENTO: aplica el cambio del prompt con más claridad. Conserva image_url y los textos fijos de WhatsApp/Google.\n";
        }

        $userContent = [['type' => 'text', 'text' => $userText]];
        foreach ($visionUrls as $url) {
            $userContent[] = [
                'type' => 'image_url',
                'image_url' => ['url' => $url, 'detail' => 'low'],
            ];
        }

        $chat = cw_mailing_ai_chat_multimodal([
            ['role' => 'system', 'content' => cw_mailing_ai_system_prompt('improve')],
            ['role' => 'user', 'content' => $userContent],
        ], $attempt === 1 ? 0.78 : 0.92);
        if (empty($chat['ok'])) {
            $lastError = (string) ($chat['error'] ?? 'Error al mejorar');
            continue;
        }

        $data = cw_mailing_ai_decode_json((string) ($chat['content'] ?? ''));
        if (!is_array($data)) {
            $lastError = 'La IA no devolvió JSON válido';
            continue;
        }

        $candidate = cw_mailing_ai_normalize_draft($data, $fallback, $publicUrls, false);
        if (cw_mailing_ai_prompt_wants_prices($prompt)) {
            $candidate = cw_mailing_ai_ensure_pricing_block($candidate, $data, $prompt);
        }
        if ($linkItems !== []) {
            $candidate = cw_mailing_ai_ensure_links_block($candidate, $linkItems);
        }
        /* Al mejorar: nunca perder imagen hero ni CTA guardados. */
        if ($imageUrl !== '') {
            $candidate['image_url'] = $imageUrl;
        } elseif (($candidate['image_url'] ?? '') === '' && $publicUrls !== []) {
            $candidate['image_url'] = $publicUrls[0];
        }
        if ($ctaLabel !== '' && !cw_mailing_ai_prompt_wants_cta_change($prompt)) {
            $candidate['cta_label'] = $ctaLabel;
        }
        if ($ctaUrl !== '' && !cw_mailing_ai_prompt_wants_cta_change($prompt)) {
            $candidate['cta_url'] = $ctaUrl;
        }
        if (($candidate['body_html'] ?? '') === '' || ($candidate['subject'] ?? '') === '') {
            $lastError = 'Borrador incompleto al mejorar';
            continue;
        }

        $afterFp = cw_mailing_ai_draft_fingerprint($candidate);
        if ($afterFp === $beforeFp && $attempt === 1) {
            $lastError = 'La IA no aplicó cambios';
            continue;
        }

        $draft = $candidate;
        break;
    }

    if ($draft === null) {
        return ['ok' => false, 'error' => $lastError !== '' ? $lastError : 'No se pudo modificar la plantilla'];
    }

    if ($imageUrl !== '') {
        $draft['image_url'] = $imageUrl;
    }
    if ($ctaLabel !== '' && !cw_mailing_ai_prompt_wants_cta_change($prompt)) {
        $draft['cta_label'] = $ctaLabel;
    }
    if ($ctaUrl !== '' && !cw_mailing_ai_prompt_wants_cta_change($prompt)) {
        $draft['cta_url'] = $ctaUrl;
    }
    $draft['active'] = isset($current['active']) ? (int) $current['active'] : 0;
    if (!empty($current['id'])) {
        $draft['id'] = (int) $current['id'];
    }

    return ['ok' => true, 'draft' => $draft, 'revised' => true];
}

/** @return array<string,mixed>|null */
function cw_mailing_ai_decode_json(string $raw): ?array
{
    $raw = trim($raw);
    if (str_starts_with($raw, '```')) {
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw) ?? $raw;
        $raw = preg_replace('/\s*```$/', '', $raw) ?? $raw;
    }
    $data = json_decode($raw, true);

    return is_array($data) ? $data : null;
}

/** @param array<string,mixed> $draft */
function cw_mailing_ai_draft_fingerprint(array $draft): string
{
    $body = (string) ($draft['body_html'] ?? '');
    $body = preg_replace('/^<!--cwml:\{.*\}-->/s', '', $body) ?? $body;
    $plain = strtolower(trim((string) (preg_replace('/\s+/', ' ', strip_tags($body)) ?? '')));

    return md5(
        strtolower(trim((string) ($draft['subject'] ?? ''))) . '|'
        . strtolower(trim((string) ($draft['headline'] ?? ''))) . '|'
        . $plain
    );
}

function cw_mailing_ai_system_prompt(string $mode): string
{
    $role = $mode === 'improve'
        ? 'Editas SOBRE la misma plantilla con precisión quirúrgica. El prompt son CAMBIOS puntuales. Conserva imagen hero, CTA y textos fijos de WhatsApp/Google. No rediseñes todo el correo ni reescribas módulos que no pidieron.'
        : 'Diseñas un correo de alto impacto según el PROMPT. El prompt es el brief creativo y comercial.';

    return <<<SYS
Eres director de arte + copywriter senior de ConlineWeb (León, Gto. / México). Nivel agencia premium B2B.
{$role}
Diseñas emails corporativos impecables: fondo claro #f1f1f1, tarjeta blanca, acento navy #000147. Sin fondos negros ni neón. Misma familia que la marca (páginas web, software, e-commerce, SEO, IA).
Tono: profesional, sobrio y claro. Titular fuerte, precios visibles si se piden, CTA nítido. El correo debe verse institucional, no invasivo.
Sin emojis. Sin clickbait falso. Sin inventar clientes, premios ni descuentos que nadie pidió.
Alineación perfecta: módulos en columnas 50/50 simétricas; icono + texto siempre alineados; padding uniforme.

REGLA #1 — OBEDECE EL PROMPT:
Si piden precios, paquetes o tarifas: OBLIGATORIO un block "pricing" con cifras en MXN.
Si el usuario escribe montos, usa ESOS. Si no, usa el CATÁLOGO OFICIAL (abajo en el mensaje de usuario).
Si piden WhatsApp, Google, 3 pasos, urgencia, comparativa: inclúyelo. No lo suavices ni lo omitas.

Devuelve SOLO JSON (sin markdown):
{
  "title": "Nombre humano de la plantilla",
  "theme": "1-2 palabras (Web, SEO, Hosting, E-commerce, IA, Seguimiento)",
  "subject": "Asunto clickeable; puedes usar {nombre} o {empresa}",
  "preheader": "Apoyo en bandeja, máx 90 caracteres",
  "layout": "corporativo|captacion|ecommerce|seguimiento|propuesta",
  "kicker": "Etiqueta corta (ej. Captación en México)",
  "headline": "Titular potente, 6-12 palabras",
  "lead": "Una frase que enmarca el problema o la promesa",
  "blocks": [
    {"type": "p", "text": "Saludo a {nombre} y contexto."},
    {"type": "steps", "items": [{"title":"Diagnóstico","text":"..."},{"title":"Propuesta","text":"..."},{"title":"Arranque","text":"..."}]},
    {"type": "split", "items": [{"title":"Sitio","text":"..."},{"title":"SEO local","text":"..."}]},
    {"type": "channels", "items": [{"title":"WhatsApp","text":"Contáctanos para resolver tus dudas.","url":"https://api.whatsapp.com/send?phone=5214771181285&text=Hola"},{"title":"Google","text":"Infórmate más sobre nuestros servicios.","url":"https://www.google.com/search?q=ConlineWeb"}]},
    {"type": "links", "items": [{"title":"Demos y precios","url":"https://conlineweb.com/demos-y-precios/","text":"Revisa paquetes"},{"title":"Sitio","url":"https://conlineweb.com/","text":"Conoce ConlineWeb"}]},
    {"type": "pricing", "items": [{"title":"Página web","price":"Desde $4,600 MXN","text":"Sitio corporativo","url":"https://conlineweb.com/demos-y-precios/"},{"title":"Tienda","price":"Desde $8,000 MXN","text":"Catálogo y checkout","url":"https://conlineweb.com/demos-y-precios/"}]},
    {"type": "cta", "label":"Hablar por WhatsApp","url":"https://api.whatsapp.com/send?phone=5214771181285&text=Hola","variant":"whatsapp","hint":"Te respondemos el mismo día.","position":"after_pricing"},
    {"type": "cta_row", "items":[{"label":"Agendar asesoría","url":"https://api.whatsapp.com/send?phone=5214771181285&text=Asesoría","variant":"whatsapp","hint":"Un mensaje y agendamos."},{"label":"Ver servicios","url":"https://conlineweb.com/","variant":"primary","hint":"Revisa paquetes y precios."}]},
    {"type": "offer", "title":"Página web corporativa","price":"Desde $4,600 MXN","text":"Inversión única · cotización formal por escrito"},
    {"type": "highlight", "text": "Frase de cierre."}
  ],
  "image_url": "URL HTTP pública de la lista o vacío",
  "cta_label": "Texto corto del botón, 2-5 palabras",
  "cta_url": "https://...",
  "cta_variant": "primary|whatsapp",
  "cta_hint": "Frase referencial al lado del botón (máx 12 palabras)"
}

TIPOS DE BLOCK (usa 2 a 5; plantilla compacta, no alargues ni ensanches):
- p: párrafo. El primero saluda con {nombre} si aporta.
- cta / cta_row: SOLO si el prompt pide más botones o varios CTA. Si no lo pide, no los uses.
- steps: 3 soft cards; badge 01–03 a la izquierda del título.
- split: 2 soft cards 50/50; icono o número al lado del título.
- channels: WhatsApp + Google. TEXTOS FIJOS (no inventes otros): WhatsApp = "Contáctanos para resolver tus dudas." · Google = "Infórmate más sobre nuestros servicios." El sistema pone iconos oficiales.
- image_url: si la plantilla ya tiene imagen, CONSERVA esa URL exacta. No la borres al mejorar.
- points: soft cards; número al lado del título; url opcional.
- highlight: máximo uno. Insight o cierre (card con borde izquierdo navy).
- pricing: 2 columnas; icono + url opcional en cada plan. OBLIGATORIO si piden precios.
- links: varios enlaces (title + url + text corto). Úsalo si el prompt o el usuario dan más de una URL.
- offer: una franja grande con un precio héroe (title, price, text).
- stat: 2–3 cajas. Si piden precios, el title puede ser el monto. Si no, valor cualitativo. No inventes % de clientes.

LAYOUT:
- corporativo: presencia y confianza.
- captacion: WhatsApp + Google + siguiente paso.
- ecommerce: tienda, checkout, pagos México.
- seguimiento: ya escribieron.
- propuesta: invitación a asesoría.

REGLAS:
- headline distinto del subject. kicker en español, corto.
- Compacto: pocos bloques, copy breve. No rellenes por rellenar.
- CTA guardado de la plantilla (cta_label + cta_url + cta_hint): el sistema lo pone SIEMPRE al final, antes del footer. No lo dupliques en blocks.
- Cada botón extra va 50/50: texto referencial a la izquierda, botón pequeño a la derecha. Siempre incluye hint.
- Botones extra (cta / cta_row) SOLO si el prompt pide más botones o un lugar concreto. Colócalos en el orden del body donde se pidan. Si piden “debajo del banner”, usa position:"banner".
- Varios CTA SOLO si el prompt lo pide explícitamente (más botones, WhatsApp y web, 2 o 3 llamados).
- Si el prompt pide WhatsApp en el CTA único: cta_variant=whatsapp y URL https://api.whatsapp.com/send?phone=5214771181285&text=...
- URLs válidas: https://conlineweb.com/paginas-web/ , /tienda-online/ , /seo/ , /software-para-empresas/ , /soluciones-inteligencia-artificial/ , /mexico/ciudades/leon/ , https://conlineweb.com/demos-y-precios/
- title humano. PROHIBIDO snake_case.
- Conserva {nombre} {empresa} {correo} {telefono}.
- No devuelvas HTML ni CSS. El sistema maqueta a partir de blocks.
SYS;
}

/**
 * @param array<string,mixed> $data
 * @param array<string,mixed> $fallback
 * @param list<string> $publicUrls
 * @return array<string,mixed>
 */
function cw_mailing_ai_normalize_draft(array $data, array $fallback, array $publicUrls, bool $keepBodyFallback = true): array
{
    $titleFb = (string) ($fallback['title'] ?? 'Plantilla');
    $themeFb = (string) ($fallback['theme'] ?? 'General');
    $draft = [
        'title' => cw_mailing_ai_humanize_label((string) ($data['title'] ?? $titleFb), $titleFb),
        'theme' => cw_mailing_ai_humanize_theme((string) ($data['theme'] ?? $themeFb)),
        'subject' => trim((string) ($data['subject'] ?? ($fallback['subject'] ?? ''))),
        'preheader' => trim((string) ($data['preheader'] ?? ($fallback['preheader'] ?? ''))),
        'layout' => cw_mailing_ai_normalize_layout((string) ($data['layout'] ?? '')),
        'kicker' => trim((string) ($data['kicker'] ?? '')),
        'headline' => trim((string) ($data['headline'] ?? '')),
        'lead' => trim((string) ($data['lead'] ?? '')),
        'image_url' => trim((string) ($data['image_url'] ?? ($fallback['image_url'] ?? ''))),
        'cta_label' => trim((string) ($data['cta_label'] ?? ($fallback['cta_label'] ?? ''))),
        'cta_url' => trim((string) ($data['cta_url'] ?? ($fallback['cta_url'] ?? ''))),
        'cta_variant' => strtolower(trim((string) ($data['cta_variant'] ?? 'primary'))) === 'whatsapp' ? 'whatsapp' : 'primary',
        'cta_hint' => trim((string) ($data['cta_hint'] ?? ($fallback['cta_hint'] ?? ''))),
        'active' => 0,
    ];

    $draft['subject'] = cw_mailing_ai_humanize_subject($draft['subject'], $draft['title']);
    if ($draft['headline'] === '') {
        $draft['headline'] = $draft['title'];
    }
    if ($draft['kicker'] === '') {
        $draft['kicker'] = $draft['theme'];
    }
    if (mb_strlen($draft['kicker']) > 42) {
        $draft['kicker'] = mb_substr($draft['kicker'], 0, 42);
    }
    if (mb_strlen($draft['preheader']) > 120) {
        $draft['preheader'] = rtrim(mb_substr($draft['preheader'], 0, 117)) . '…';
    }

    if ($draft['image_url'] === '' || str_starts_with($draft['image_url'], 'data:')) {
        $draft['image_url'] = $publicUrls[0] ?? (string) ($fallback['image_url'] ?? '');
    } elseif ($publicUrls !== [] && !in_array($draft['image_url'], $publicUrls, true)) {
        $orig = (string) ($fallback['image_url'] ?? '');
        $draft['image_url'] = in_array($orig, $publicUrls, true) || $orig === $draft['image_url']
            ? ($orig !== '' ? $orig : $publicUrls[0])
            : $publicUrls[0];
    }

    if ($draft['cta_label'] === '') {
        $draft['cta_label'] = 'Solicitar asesoría';
    }
    if ($draft['cta_url'] === '') {
        $draft['cta_url'] = 'https://conlineweb.com/';
    }
    if ($draft['cta_variant'] !== 'whatsapp' && str_contains($draft['cta_url'], 'whatsapp')) {
        $draft['cta_variant'] = 'whatsapp';
    }
    if ($draft['cta_hint'] === '') {
        $draft['cta_hint'] = $draft['cta_variant'] === 'whatsapp'
            ? 'Escríbenos y te respondemos el mismo día.'
            : '¿Seguimos? Te proponemos el siguiente paso.';
    }

    $blocks = $data['blocks'] ?? null;
    if (!is_array($blocks) || $blocks === []) {
        /* Conservar blocks del borrador previo (mejora IA sin reenviar blocks). */
        $prevMeta = function_exists('cw_mailing_parse_design_meta')
            ? cw_mailing_parse_design_meta((string) ($fallback['body_html'] ?? ''))
            : ['blocks' => []];
        $blocks = is_array($prevMeta['blocks'] ?? null) ? $prevMeta['blocks'] : [];
    }
    if (!empty($data['ctas']) && is_array($data['ctas'])) {
        foreach ($data['ctas'] as $extra) {
            if (is_array($extra)) {
                $blocks[] = array_merge(['type' => 'cta'], $extra);
            }
        }
    }
    $blocks = cw_mailing_ai_lock_stable_blocks($blocks);
    $composed = cw_mailing_ai_compose_blocks($blocks, $draft);
    if ($composed !== '') {
        $draft['body_html'] = $composed;
    } else {
        $rawBody = trim((string) ($data['body_html'] ?? ''));
        if ($rawBody === '' && $keepBodyFallback) {
            $rawBody = trim((string) ($fallback['body_html'] ?? ''));
        }
        $draft['body_html'] = cw_mailing_ai_sanitize_body($rawBody);
    }

    $meta = [
        'layout' => $draft['layout'],
        'kicker' => $draft['kicker'],
        'headline' => $draft['headline'],
        'lead' => $draft['lead'],
        'cta_variant' => $draft['cta_variant'],
        'cta_hint' => $draft['cta_hint'],
        'blocks' => $blocks,
    ];
    $draft['body_html'] = '<!--cwml:' . json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '-->'
        . $draft['body_html'];

    return $draft;
}

/**
 * Fija textos/títulos de módulos estables (channels WA/Google) para que la IA no los cambie.
 *
 * @param list<mixed> $blocks
 * @return list<mixed>
 */
function cw_mailing_ai_lock_stable_blocks(array $blocks): array
{
    $out = [];
    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }
        $type = strtolower(trim((string) ($block['type'] ?? '')));
        if ($type === 'channels' && isset($block['items']) && is_array($block['items'])) {
            $locked = [];
            foreach ($block['items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $title = (string) ($item['title'] ?? '');
                $canon = cw_mailing_ai_channel_canonical($title);
                if ($canon !== null) {
                    $item['title'] = $canon['title'];
                    $item['text'] = $canon['text'];
                    if (trim((string) ($item['url'] ?? '')) === '') {
                        $item['url'] = cw_mailing_ai_default_url_for_title($canon['title']);
                    }
                }
                $locked[] = $item;
            }
            /* Si faltó WA o Google, completar el par canónico. */
            $haveWa = false;
            $haveGo = false;
            foreach ($locked as $it) {
                $c = cw_mailing_ai_channel_canonical((string) ($it['title'] ?? ''));
                if ($c && $c['title'] === 'WhatsApp') {
                    $haveWa = true;
                }
                if ($c && $c['title'] === 'Google') {
                    $haveGo = true;
                }
            }
            if (!$haveWa) {
                array_unshift($locked, [
                    'title' => 'WhatsApp',
                    'text' => 'Contáctanos para resolver tus dudas.',
                    'url' => cw_mailing_ai_default_wa_url('Hola, vi el correo'),
                ]);
            }
            if (!$haveGo) {
                $locked[] = [
                    'title' => 'Google',
                    'text' => 'Infórmate más sobre nuestros servicios.',
                    'url' => 'https://www.google.com/search?q=ConlineWeb',
                ];
            }
            $block['items'] = array_slice($locked, 0, 2);
        }
        $out[] = $block;
    }

    return $out;
}

function cw_mailing_ai_normalize_layout(string $layout): string
{
    $layout = strtolower(trim($layout));
    $ok = ['corporativo', 'captacion', 'ecommerce', 'seguimiento', 'propuesta'];

    return in_array($layout, $ok, true) ? $layout : 'corporativo';
}

/**
 * @param list<mixed> $blocks
 * @param array<string,mixed> $draft
 */
function cw_mailing_ai_compose_blocks(array $blocks, array $draft): string
{
    $html = '';
    $count = 0;
    foreach ($blocks as $block) {
        if ($count >= 9 || !is_array($block)) {
            continue;
        }
        $type = strtolower(trim((string) ($block['type'] ?? 'p')));
        $items = is_array($block['items'] ?? null) ? $block['items'] : [];
        $chunk = '';

        if ($type === 'cta' || $type === 'button') {
            $chunk = cw_mailing_ai_block_cta($block, $draft);
            $pos = strtolower(trim((string) ($block['position'] ?? $block['place'] ?? '')));
            if ($chunk !== '' && in_array($pos, ['banner', 'top', 'after_banner', 'debajo_banner', 'header'], true)) {
                $chunk = '<!--cw-banner-cta-->' . $chunk . '<!--/cw-banner-cta-->';
            }
        } elseif ($type === 'cta_row' || $type === 'ctas' || $type === 'buttons') {
            $chunk = cw_mailing_ai_block_cta_row($items, $draft);
        } elseif ($type === 'links' || $type === 'link_list' || $type === 'recursos') {
            $chunk = cw_mailing_ai_block_links($items);
        } elseif ($type === 'pricing' || $type === 'prices' || $type === 'planes') {
            $chunk = cw_mailing_ai_block_pricing($items);
        } elseif ($type === 'offer' || $type === 'price') {
            $chunk = cw_mailing_ai_block_offer($block);
        } elseif ($type === 'steps') {
            $chunk = cw_mailing_ai_block_steps($items);
        } elseif ($type === 'split' || $type === 'two_col') {
            $chunk = cw_mailing_ai_block_split($items);
        } elseif ($type === 'channels') {
            $chunk = cw_mailing_ai_block_channels($items);
        } elseif ($type === 'stat' || $type === 'stats') {
            $chunk = cw_mailing_ai_block_stats($items);
        } elseif ($type === 'points' || $type === 'cards') {
            $chunk = cw_mailing_ai_block_points($items);
        } elseif ($type === 'highlight' || $type === 'quote') {
            $tx = trim((string) ($block['text'] ?? ''));
            if ($tx !== '') {
                $chunk = cw_mailing_ai_highlight_block($tx);
            }
        } elseif ($type === 'h3' || $type === 'h2') {
            $tx = trim((string) ($block['text'] ?? ''));
            if ($tx !== '') {
                $chunk = cw_mailing_ai_section_heading($tx);
            }
        } else {
            $tx = trim((string) ($block['text'] ?? ''));
            if ($tx !== '') {
                $chunk = cw_mailing_ai_text_block($tx);
            }
        }

        if ($chunk === '') {
            continue;
        }
        $html .= cw_mailing_ai_block_shell($chunk);
        $count++;
    }

    return trim($html);
}

/** @param list<mixed> $items */
function cw_mailing_ai_clean_items(array $items, int $max = 4): array
{
    $out = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $pt = trim((string) ($item['title'] ?? ''));
        $tx = trim((string) ($item['text'] ?? $item['note'] ?? ''));
        $price = trim((string) ($item['price'] ?? $item['monto'] ?? ''));
        if ($price === '' && preg_match('/\$\s*[\d.,]+\s*(MXN)?/i', $pt)) {
            $price = $pt;
            $pt = trim((string) ($item['name'] ?? $item['plan'] ?? 'Plan'));
        }
        if ($pt === '' && $tx === '' && $price === '') {
            continue;
        }
        $url = trim((string) ($item['url'] ?? $item['link'] ?? $item['href'] ?? ''));
        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            $url = '';
        }
        $out[] = ['title' => $pt, 'text' => $tx, 'price' => $price, 'url' => $url];
        if (count($out) >= $max) {
            break;
        }
    }

    return $out;
}

function cw_mailing_ai_prompt_wants_cta_change(string $prompt): bool
{
    $p = mb_strtolower($prompt, 'UTF-8');

    return (bool) preg_match('/\b(cta|bot[oó]n|whatsapp|llamad[oa]|agendar|cambiar\s+el\s+bot[oó]n|otro\s+cta)\b/u', $p);
}

function cw_mailing_ai_prompt_wants_prices(string $prompt): bool
{
    return (bool) preg_match('/precio|precios|tarifa|tarifas|costo|costos|cu[aá]nto (cuesta|sale)|paquete|planes|mxn|\$\s*\d/iu', $prompt);
}

function cw_mailing_ai_price_catalog(): array
{
    return [
        ['title' => 'Página web', 'price' => 'Desde $4,600 MXN', 'text' => 'Sitio corporativo responsive'],
        ['title' => 'SEO y GEO', 'price' => 'Desde $3,500 MXN/mes', 'text' => 'Mejora tu visibilidad en Google'],
        ['title' => 'Inteligencia Artificial', 'price' => 'Desde $6,000 MXN', 'text' => 'Chatbot WhatsApp entrenado'],
        ['title' => 'Tienda en línea', 'price' => 'Desde $8,000 MXN', 'text' => 'Catálogo, carrito y pagos'],
        ['title' => 'Software a medida', 'price' => 'Desde $9,500 MXN', 'text' => 'Plataforma en la nube'],
    ];
}

function cw_mailing_ai_price_catalog_prompt(): string
{
    $lines = "CATÁLOGO OFICIAL CONLINEWEB (úsalo si piden precios y no dan montos):\n";
    foreach (cw_mailing_ai_price_catalog() as $row) {
        $lines .= '- ' . $row['title'] . ': ' . $row['price'] . ' · ' . $row['text'] . "\n";
    }
    $lines .= "Hosting / dominio de referencia: planes PRO desde $1,200 MXN (1er año). "
        . "Siempre aclara que son montos de referencia y la cotización formal va por escrito.\n";

    return $lines;
}

/**
 * Extrae montos que el usuario escribió en el prompt (ej. $4,600 o 8000 mxn).
 *
 * @return list<string>
 */
function cw_mailing_ai_prices_from_prompt(string $prompt): array
{
    if (!preg_match_all('/\$\s*[\d]{1,3}(?:[.,]\d{3})*(?:\s*MXN)?|\b\d{3,6}\s*MXN\b/iu', $prompt, $m)) {
        return [];
    }

    return array_values(array_unique(array_map(static function ($s) {
        $s = trim((string) $s);
        if (!str_contains(strtoupper($s), 'MXN') && str_starts_with($s, '$')) {
            $s .= ' MXN';
        }

        return $s;
    }, $m[0])));
}

/**
 * @param array<string,mixed> $draft
 * @param array<string,mixed> $data
 * @return array<string,mixed>
 */
function cw_mailing_ai_ensure_pricing_block(array $draft, array $data, string $prompt): array
{
    $html = (string) ($draft['body_html'] ?? '');
    if (preg_match('/\$\s*[\d]|MXN/i', $html)) {
        return $draft;
    }

    $items = [];
    $userPrices = cw_mailing_ai_prices_from_prompt($prompt);
    if ($userPrices !== []) {
        foreach ($userPrices as $i => $price) {
            $items[] = [
                'title' => 'Opción ' . ($i + 1),
                'price' => $price,
                'text' => 'Según lo indicado en tu brief',
            ];
        }
    } else {
        $catalog = cw_mailing_ai_price_catalog();
        $hay = strtolower((string) ($draft['theme'] ?? '') . ' ' . $prompt);
        $pick = [];
        foreach ($catalog as $row) {
            $name = strtolower($row['title']);
            $first = explode(' ', $name)[0] ?? '';
            if (($first !== '' && str_contains($hay, $first)) || str_contains($hay, $name)) {
                $pick[] = $row;
            }
        }
        $items = $pick !== [] ? array_slice($pick, 0, 2) : array_slice($catalog, 0, 2);
        if (count($items) < 2) {
            foreach ($catalog as $row) {
                $dup = false;
                foreach ($items as $have) {
                    if ($have['title'] === $row['title']) {
                        $dup = true;
                        break;
                    }
                }
                if ($dup) {
                    continue;
                }
                $items[] = $row;
                if (count($items) >= 2) {
                    break;
                }
            }
        }
    }

    $chunk = cw_mailing_ai_block_pricing($items);
    if ($chunk === '') {
        return $draft;
    }

    $body = function_exists('cw_mailing_strip_design_meta_comment')
        ? cw_mailing_strip_design_meta_comment($html)
        : (preg_replace('/^<!--cwml:\{.*\}-->/s', '', $html, 1) ?? $html);
    $meta = ($body !== ltrim($html) && $body !== $html)
        ? (string) substr($html, 0, max(0, strlen($html) - strlen($body)))
        : '';
    if ($meta === '' && preg_match('/^<!--cwml:\{.*\}-->/s', $html, $mm)) {
        $meta = $mm[0];
        $body = ltrim(substr($html, strlen($mm[0])));
    }
    $draft['body_html'] = $meta . $chunk . $body;

    return $draft;
}

function cw_mailing_ai_ff(): string
{
    return function_exists('cw_mailing_font_stack')
        ? cw_mailing_font_stack()
        : "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Helvetica,Arial,sans-serif";
}

/**
 * Icono 36×36 (PNG o iniciales). Solo el contenido interno de la celda.
 */
function cw_mailing_ai_badge(string $mark, string $bg = '#000147', string $imgUrl = ''): string
{
    $ff = cw_mailing_ai_ff();
    $imgUrl = trim($imgUrl);
    if ($imgUrl !== '' && preg_match('#^https?://#i', $imgUrl)) {
        return '<img src="' . htmlspecialchars($imgUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" alt="" width="36" height="36" style="display:block;width:36px;height:36px;border:0;border-radius:8px;">';
    }
    $mark = trim($mark);
    if ($mark === '') {
        $mark = '•';
    }
    $fs = mb_strlen($mark) > 2 ? '10' : '13';

    return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="36" height="36" style="width:36px;height:36px;border-collapse:collapse;"><tr>'
        . '<td align="center" valign="middle" width="36" height="36" bgcolor="' . $bg . '" style="width:36px;height:36px;background:' . $bg . ';border-radius:8px;color:#ffffff;font-size:' . $fs . 'px;font-weight:800;line-height:36px;text-align:center;font-family:' . $ff . ';">'
        . htmlspecialchars($mark, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</td></tr></table>';
}

/** URL pública de iconos de canal (PNG para clientes de correo). */
function cw_mailing_ai_channel_icon_url(string $kind): string
{
    $kind = strtolower(trim($kind));
    $base = 'https://conlineweb.com/assets/images/social/';
    if ($kind === 'whatsapp' || $kind === 'wa') {
        return $base . 'mailing-whatsapp.png';
    }
    if ($kind === 'google' || $kind === 'g') {
        return $base . 'mailing-google.png';
    }
    if ($kind === 'ia' || $kind === 'ai') {
        return $base . 'mailing-ia.png';
    }
    if ($kind === 'store' || $kind === 'shop' || $kind === 'ecommerce' || $kind === 'tienda') {
        return $base . 'mailing-store.png';
    }

    return '';
}

/**
 * Textos canónicos del bloque channels (no deben cambiar con la IA).
 *
 * @return array{title:string,text:string}|null
 */
function cw_mailing_ai_channel_canonical(string $title): ?array
{
    $t = mb_strtolower(trim($title), 'UTF-8');
    if (str_contains($t, 'whatsapp') || str_contains($t, 'whats') || $t === 'wa') {
        return [
            'title' => 'WhatsApp',
            'text' => 'Contáctanos para resolver tus dudas.',
        ];
    }
    if (str_contains($t, 'google') || str_contains($t, 'maps') || str_contains($t, 'gmb') || $t === 'g') {
        return [
            'title' => 'Google',
            'text' => 'Infórmate más sobre nuestros servicios.',
        ];
    }

    return null;
}

/**
 * Icono/inicial según el título (WhatsApp, Google, Web, SEO…).
 *
 * @return array{mark:string,bg:string,img:string}
 */
function cw_mailing_ai_icon_for_title(string $title): array
{
    $t = mb_strtolower(trim($title), 'UTF-8');
    if ($t === '') {
        return ['mark' => 'CW', 'bg' => '#000147', 'img' => ''];
    }
    if (str_contains($t, 'whatsapp') || str_contains($t, 'whats') || $t === 'wa') {
        return ['mark' => 'WA', 'bg' => '#25D366', 'img' => cw_mailing_ai_channel_icon_url('whatsapp')];
    }
    if (str_contains($t, 'google') || str_contains($t, 'maps') || str_contains($t, 'gmb')) {
        return ['mark' => 'G', 'bg' => '#4285F4', 'img' => cw_mailing_ai_channel_icon_url('google')];
    }
    if (str_contains($t, 'seo') || str_contains($t, 'geo')) {
        return ['mark' => 'SEO', 'bg' => '#000147', 'img' => ''];
    }
    if (str_contains($t, 'tienda') || str_contains($t, 'e-com') || str_contains($t, 'ecommerce') || str_contains($t, 'commerce') || str_contains($t, 'en línea') || str_contains($t, 'en linea')) {
        return ['mark' => 'SHOP', 'bg' => '#000147', 'img' => cw_mailing_ai_channel_icon_url('store')];
    }
    if (str_contains($t, 'inteligencia') || preg_match('/\bia\b|\bai\b|chatbot|automatiz/u', $t)) {
        return ['mark' => 'IA', 'bg' => '#000147', 'img' => cw_mailing_ai_channel_icon_url('ia')];
    }
    if (str_contains($t, 'software') || str_contains($t, 'sistema') || str_contains($t, 'crm')) {
        return ['mark' => 'SW', 'bg' => '#000147', 'img' => ''];
    }
    if (str_contains($t, 'hosting') || str_contains($t, 'dominio')) {
        return ['mark' => 'HOST', 'bg' => '#000147', 'img' => ''];
    }
    if (str_contains($t, 'web') || str_contains($t, 'página') || str_contains($t, 'pagina') || str_contains($t, 'sitio')) {
        return ['mark' => 'WEB', 'bg' => '#000147', 'img' => ''];
    }
    if (str_contains($t, 'form')) {
        return ['mark' => 'FORM', 'bg' => '#000147', 'img' => ''];
    }

    // Iniciales del título (máx 3).
    $parts = preg_split('/\s+/u', $title) ?: [];
    $mark = '';
    foreach ($parts as $p) {
        $p = trim((string) $p);
        if ($p === '') {
            continue;
        }
        $mark .= mb_strtoupper(mb_substr($p, 0, 1, 'UTF-8'), 'UTF-8');
        if (mb_strlen($mark) >= 3) {
            break;
        }
    }
    if ($mark === '') {
        $mark = 'CW';
    }

    return ['mark' => $mark, 'bg' => '#000147', 'img' => ''];
}

/**
 * Icono JUSTO al lado del título; el texto debajo alineado con el título (no con el icono).
 * Misma geometría en WhatsApp, Google, precios, steps, etc.
 */
function cw_mailing_ai_icon_title_block(
    string $title,
    string $text,
    string $mark,
    string $bg = '#000147',
    string $imgUrl = '',
    string $url = '',
    int $titleSize = 15,
    int $textSize = 13
): string {
    $ff = cw_mailing_ai_ff();
    $title = trim($title);
    $text = trim($text);
    $icon = cw_mailing_ai_badge($mark, $bg, $imgUrl);
    if ($url !== '' && preg_match('#^https?://#i', $url)) {
        $icon = '<a href="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" target="_blank" style="text-decoration:none;border:0;line-height:0;">'
            . $icon . '</a>';
    }

    $titleHtml = $title !== ''
        ? '<strong style="margin:0;padding:0;font-size:' . $titleSize . 'px;line-height:36px;color:#000147;font-weight:700;font-family:' . $ff . ';">'
          . cw_mailing_ai_inline($title) . '</strong>'
        : '';
    if ($url !== '' && preg_match('#^https?://#i', $url) && $titleHtml !== '') {
        $titleHtml = '<a href="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" target="_blank" style="text-decoration:none;color:#000147;">'
            . $titleHtml . '</a>';
    }

    $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;table-layout:fixed;font-family:' . $ff . ';">';

    /* Fila 1: icono | título (misma altura 36px → alineación exacta) */
    $html .= '<tr>'
        . '<td width="36" valign="middle" style="width:36px;height:36px;padding:0;vertical-align:middle;line-height:0;">'
        . $icon
        . '</td>'
        . '<td width="10" style="width:10px;padding:0;font-size:0;line-height:0;">&nbsp;</td>'
        . '<td valign="middle" style="padding:0;vertical-align:middle;">'
        . $titleHtml
        . '</td>'
        . '</tr>';

    /* Fila 2: hueco bajo el icono | texto alineado con el título */
    if ($text !== '') {
        $textHtml = '<span style="display:block;margin:0;padding:0;font-size:' . $textSize . 'px;line-height:1.5;color:#333333;font-family:' . $ff . ';">'
            . cw_mailing_ai_inline($text) . '</span>';
        if ($url !== '' && preg_match('#^https?://#i', $url)) {
            $textHtml = '<a href="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" target="_blank" style="text-decoration:none;color:#333333;">'
                . $textHtml . '</a>';
        }
        $html .= '<tr>'
            . '<td width="36" style="width:36px;padding:0;font-size:0;line-height:0;">&nbsp;</td>'
            . '<td width="10" style="width:10px;padding:0;font-size:0;line-height:0;">&nbsp;</td>'
            . '<td valign="top" style="padding:8px 0 0;vertical-align:top;">'
            . $textHtml
            . '</td>'
            . '</tr>';
    }

    $html .= '</table>';

    return $html;
}

/**
 * Solo fila icono + título (sin descripción).
 */
function cw_mailing_ai_title_row(string $title, string $mark, string $bg = '#000147', int $size = 15, string $url = '', string $imgUrl = ''): string
{
    return cw_mailing_ai_icon_title_block($title, '', $mark, $bg, $imgUrl, $url, $size, 13);
}

/**
 * Icono + título + texto (misma geometría en todos los módulos).
 */
function cw_mailing_ai_card_inner(string $title, string $text, string $mark, string $bg = '#000147', int $titleSize = 15, int $textSize = 13, string $url = '', string $imgUrl = ''): string
{
    return cw_mailing_ai_icon_title_block($title, $text, $mark, $bg, $imgUrl, $url, $titleSize, $textSize);
}

/** URL por defecto según el título (WhatsApp, Google, demos…). */
function cw_mailing_ai_default_url_for_title(string $title): string
{
    $t = mb_strtolower(trim($title), 'UTF-8');
    if ($t === '') {
        return '';
    }
    if (str_contains($t, 'whatsapp') || str_contains($t, 'whats') || $t === 'wa') {
        return cw_mailing_ai_default_wa_url();
    }
    if (str_contains($t, 'google') || str_contains($t, 'maps') || str_contains($t, 'gmb')) {
        return 'https://www.google.com/search?q=ConlineWeb+Le%C3%B3n';
    }
    if (str_contains($t, 'demo') || str_contains($t, 'precio') || str_contains($t, 'paquete')) {
        return 'https://conlineweb.com/demos-y-precios/';
    }
    if (str_contains($t, 'seo') || str_contains($t, 'geo')) {
        return 'https://conlineweb.com/';
    }
    if (str_contains($t, 'web') || str_contains($t, 'página') || str_contains($t, 'pagina') || str_contains($t, 'sitio') || str_contains($t, 'tienda') || str_contains($t, 'software')) {
        return 'https://conlineweb.com/demos-y-precios/';
    }

    return '';
}

/**
 * Espacio uniforme entre bloques del cuerpo.
 */
function cw_mailing_ai_block_shell(string $inner): string
{
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;margin:0 0 14px;">'
        . '<tr><td style="padding:0;">' . $inner . '</td></tr></table>';
}

/**
 * Párrafo dentro de contenedor (misma familia visual que WhatsApp/Google).
 */
function cw_mailing_ai_text_block(string $text): string
{
    $ff = cw_mailing_ai_ff();
    $inner = '<p style="margin:0;padding:0;font-size:15px;line-height:1.65;color:#111111;font-family:' . $ff . ';">'
        . cw_mailing_ai_inline($text) . '</p>';

    return cw_mailing_ai_soft_card($inner, '18px 18px');
}

/**
 * Título de sección con línea divisoria debajo.
 */
function cw_mailing_ai_section_heading(string $text): string
{
    $ff = cw_mailing_ai_ff();

    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;font-family:' . $ff . ';">'
        . '<tr><td style="padding:4px 2px 0;">'
        . '<h3 style="margin:0 0 10px;font-size:16px;line-height:1.3;color:#000147;font-weight:800;font-family:' . $ff . ';">'
        . cw_mailing_ai_inline($text) . '</h3>'
        . '</td></tr>'
        . '<tr><td style="padding:0 0 2px;border-bottom:1px solid #e5e7eb;font-size:0;line-height:0;height:1px;">&nbsp;</td></tr>'
        . '</table>';
}

/**
 * Destacado / cita en contenedor con acento.
 */
function cw_mailing_ai_highlight_block(string $text): string
{
    $ff = cw_mailing_ai_ff();
    $inner = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;">'
        . '<tr>'
        . '<td width="4" style="width:4px;background:#000147;border-radius:4px;font-size:0;line-height:0;">&nbsp;</td>'
        . '<td style="padding:0 0 0 14px;font-size:15px;line-height:1.65;color:#111111;font-family:' . $ff . ';">'
        . cw_mailing_ai_inline($text)
        . '</td></tr></table>';

    return cw_mailing_ai_soft_card($inner, '18px 18px');
}

/**
 * Línea divisoria visible (gris fuerte) entre ítems apilados.
 */
function cw_mailing_ai_item_divider(): string
{
    return '<tr><td style="padding:0;font-size:0;line-height:0;height:2px;background:#555555;border:0;">'
        . '<div style="height:2px;line-height:2px;font-size:0;background:#555555;border:0;">&nbsp;</div>'
        . '</td></tr>';
}

/**
 * Varios ítems (WhatsApp, Google, pasos…) en UN solo card,
 * separados por línea gris fuerte inferior entre cada uno.
 *
 * @param list<string> $partsHtml
 */
function cw_mailing_ai_divided_stack(array $partsHtml): string
{
    $parts = [];
    foreach ($partsHtml as $part) {
        $part = trim((string) $part);
        if ($part !== '') {
            $parts[] = $part;
        }
    }
    if ($parts === []) {
        return '';
    }
    if (count($parts) === 1) {
        return cw_mailing_ai_soft_card($parts[0], '16px 18px');
    }

    $rows = '';
    $last = count($parts) - 1;
    foreach ($parts as $i => $part) {
        $rows .= '<tr><td style="padding:16px 0;">' . $part . '</td></tr>';
        if ($i < $last) {
            $rows .= cw_mailing_ai_item_divider();
        }
    }

    return cw_mailing_ai_soft_card(
        '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;">'
        . $rows
        . '</table>',
        '4px 18px'
    );
}

/**
 * Fila de módulo sin card propio (para meter en divided_stack).
 */
function cw_mailing_ai_sym_row(string $title, string $text, string $url = '', string $mark = 'CW', string $bg = '#000147', string $imgUrl = ''): string
{
    return cw_mailing_ai_icon_title_block($title, $text, $mark, $bg, $imgUrl, $url, 15, 13);
}

/**
 * Card: fondo gris muy suave sobre cuerpo blanco + borde, para que resalte.
 */
function cw_mailing_ai_soft_card(string $inner, string $pad = '18px 16px'): string
{
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f5f6f8" style="width:100%;border-collapse:separate;border-spacing:0;background:#f5f6f8;border:1px solid #d0d4db;border-radius:12px;">'
        . '<tr><td valign="top" style="padding:' . $pad . ';vertical-align:top;">'
        . $inner
        . '</td></tr></table>';
}

/**
 * Fila simétrica 50/50. El gutter va en padding interno (no columna extra)
 * para que width:50% + 50% no desborde el 100%.
 */
function cw_mailing_ai_two_col(string $leftHtml, string $rightHtml, string $margin = '0'): string
{
    $ff = cw_mailing_ai_ff();

    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:' . $margin . ';width:100%;border-collapse:collapse;table-layout:fixed;font-family:' . $ff . ';">'
        . '<tr>'
        . '<td class="cw-col cw-col-2 cw-stack" width="50%" valign="top" style="width:50%;vertical-align:top;padding:0 6px 0 0;box-sizing:border-box;">'
        . $leftHtml
        . '</td>'
        . '<td class="cw-col cw-col-2 cw-stack" width="50%" valign="top" style="width:50%;vertical-align:top;padding:0 0 0 6px;box-sizing:border-box;">'
        . $rightHtml
        . '</td>'
        . '</tr></table>';
}

/**
 * Fila simétrica de hasta 3 columnas iguales.
 *
 * @param list<string> $cellsHtml
 */
function cw_mailing_ai_three_col(array $cellsHtml, string $margin = '0'): string
{
    $cellsHtml = array_values(array_filter($cellsHtml, static fn($h) => trim((string) $h) !== ''));
    $n = count($cellsHtml);
    if ($n === 0) {
        return '';
    }
    if ($n === 1) {
        return '<div style="margin:' . $margin . ';">' . $cellsHtml[0] . '</div>';
    }
    if ($n === 2) {
        return cw_mailing_ai_two_col($cellsHtml[0], $cellsHtml[1], $margin);
    }
    $ff = cw_mailing_ai_ff();
    $pads = ['0 4px 0 0', '0 4px', '0 0 0 4px'];
    $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:' . $margin . ';width:100%;border-collapse:collapse;table-layout:fixed;font-family:' . $ff . ';"><tr>';
    foreach (array_slice($cellsHtml, 0, 3) as $i => $cell) {
        $html .= '<td class="cw-col cw-stack" width="33%" valign="top" style="width:33.33%;vertical-align:top;padding:' . $pads[$i] . ';box-sizing:border-box;">'
            . $cell
            . '</td>';
    }
    $html .= '</tr></table>';

    return $html;
}

/**
 * Card de módulo: icono al lado del título + texto debajo (WhatsApp, Google, precios…).
 */
function cw_mailing_ai_sym_module(string $title, string $text, string $url = '', string $mark = 'CW', string $bg = '#000147', string $imgUrl = ''): string
{
    return cw_mailing_ai_soft_card(
        cw_mailing_ai_icon_title_block($title, $text, $mark, $bg, $imgUrl, $url, 15, 13),
        '16px 16px'
    );
}

/** @param list<mixed> $items */
function cw_mailing_ai_block_pricing(array $items): string
{
    $items = cw_mailing_ai_clean_items($items, 3);
    if ($items === []) {
        return '';
    }
    $ff = cw_mailing_ai_ff();

    $card = static function (array $item): string {
        $price = $item['price'] !== '' ? $item['price'] : '';
        $title = $item['title'] !== '' ? $item['title'] : 'Plan';
        $desc = trim($price . ($item['text'] !== '' ? ($price !== '' ? ' · ' : '') . $item['text'] : ''));
        $icon = cw_mailing_ai_icon_for_title($title);
        $url = trim((string) ($item['url'] ?? ''));
        if ($url === '') {
            $url = cw_mailing_ai_default_url_for_title($title);
        }

        return cw_mailing_ai_sym_module(
            $title,
            $desc,
            $url,
            $icon['mark'],
            $icon['bg'],
            (string) ($icon['img'] ?? '')
        );
    };

    $cards = [];
    foreach ($items as $item) {
        $cards[] = $card($item);
    }
    $html = cw_mailing_ai_three_col($cards, '0');
    $html .= '<p style="margin:10px 0 0;font-size:12px;line-height:1.45;color:#333333;text-align:center;font-family:' . $ff . ';">Montos de referencia · cotización formal por escrito</p>';

    return $html;
}

/** @param array<string,mixed> $block */
function cw_mailing_ai_block_offer(array $block): string
{
    $title = trim((string) ($block['title'] ?? ''));
    $price = trim((string) ($block['price'] ?? ''));
    $text = trim((string) ($block['text'] ?? ''));
    if ($price === '' && $title === '') {
        return '';
    }

    $icon = cw_mailing_ai_icon_for_title($title !== '' ? $title : 'Oferta');

    return cw_mailing_ai_soft_card(
        ($title !== '' ? '<div style="margin:0 0 12px;">' . cw_mailing_ai_title_row($title, $icon['mark'], $icon['bg'], 13, '', (string) ($icon['img'] ?? '')) . '</div>' : '')
        . ($price !== '' ? '<strong style="display:block;margin:0 0 10px;font-size:34px;line-height:1.1;color:#000147;letter-spacing:-0.04em;">' . cw_mailing_ai_inline($price) . '</strong>' : '')
        . ($text !== '' ? '<span style="display:block;font-size:15px;line-height:1.5;color:#333333;">' . cw_mailing_ai_inline($text) . '</span>' : ''),
        '22px 20px'
    );
}

function cw_mailing_ai_default_wa_url(string $text = 'Hola, me interesa una asesoría ConlineWeb.'): string
{
    return 'https://api.whatsapp.com/send?phone=5214771181285&text=' . rawurlencode($text);
}

function cw_mailing_ai_normalize_cta_variant(string $variant, string $url = ''): string
{
    $variant = strtolower(trim($variant));
    if ($variant === 'whatsapp' || str_contains($url, 'whatsapp') || str_contains($url, 'wa.me')) {
        return 'whatsapp';
    }
    if ($variant === 'ghost' || $variant === 'secondary') {
        return 'ghost';
    }

    return 'primary';
}

/**
 * @param array<string,mixed> $block
 * @param array<string,mixed> $draft
 */
function cw_mailing_ai_block_cta(array $block, array $draft = []): string
{
    $label = trim((string) ($block['label'] ?? $block['cta_label'] ?? $block['text'] ?? ''));
    $url = trim((string) ($block['url'] ?? $block['cta_url'] ?? $block['href'] ?? ''));
    $hint = trim((string) ($block['hint'] ?? $block['text'] ?? ''));
    if ($hint === $label) {
        $hint = '';
    }
    if ($label === '') {
        $label = trim((string) ($draft['cta_label'] ?? 'Solicitar asesoría'));
    }
    if ($url === '') {
        $url = trim((string) ($draft['cta_url'] ?? ''));
    }
    if ($url === '') {
        $url = cw_mailing_ai_default_wa_url();
    }
    $variant = cw_mailing_ai_normalize_cta_variant((string) ($block['variant'] ?? ''), $url);

    return cw_mailing_ai_cta_button($url, $label, $variant, $hint);
}

/**
 * @param list<mixed> $items
 * @param array<string,mixed> $draft
 */
function cw_mailing_ai_block_cta_row(array $items, array $draft = []): string
{
    $btns = [];
    foreach (array_slice($items, 0, 2) as $item) {
        if (!is_array($item)) {
            continue;
        }
        $label = trim((string) ($item['label'] ?? $item['title'] ?? $item['text'] ?? ''));
        $url = trim((string) ($item['url'] ?? $item['cta_url'] ?? ''));
        if ($label === '' || $url === '') {
            continue;
        }
        $hint = trim((string) ($item['hint'] ?? $item['text'] ?? ''));
        if ($hint === $label) {
            $hint = '';
        }
        $btns[] = [
            'label' => $label,
            'url' => $url,
            'variant' => cw_mailing_ai_normalize_cta_variant((string) ($item['variant'] ?? ''), $url),
            'hint' => $hint,
        ];
    }
    if (count($btns) === 1) {
        return cw_mailing_ai_cta_button($btns[0]['url'], $btns[0]['label'], $btns[0]['variant'], $btns[0]['hint']);
    }
    if ($btns === []) {
        $wa = cw_mailing_ai_default_wa_url();
        $web = trim((string) ($draft['cta_url'] ?? 'https://conlineweb.com/'));
        if (str_contains($web, 'whatsapp')) {
            $web = 'https://conlineweb.com/demos-y-precios/';
        }
        $btns = [
            ['label' => 'WhatsApp', 'url' => $wa, 'variant' => 'whatsapp', 'hint' => 'Escríbenos y te respondemos el mismo día.'],
            ['label' => 'Ver servicios', 'url' => $web, 'variant' => 'primary', 'hint' => 'Revisa paquetes y el siguiente paso.'],
        ];
    }

    $pair = array_slice($btns, 0, 2);
    if (count($pair) === 2) {
        $left = cw_mailing_ai_cta_anchor($pair[0]['url'], $pair[0]['label'], $pair[0]['variant']);
        $right = cw_mailing_ai_cta_anchor($pair[1]['url'], $pair[1]['label'], $pair[1]['variant']);
        $hintL = trim((string) ($pair[0]['hint'] ?? ''));
        $hintR = trim((string) ($pair[1]['hint'] ?? ''));
        $ff = cw_mailing_ai_ff();
        $wrap = static function (string $btn, string $hint) use ($ff): string {
            $h = $hint !== ''
                ? '<div style="margin:0 0 8px;font-size:12px;line-height:1.4;color:#555555;text-align:center;font-family:' . $ff . ';">'
                  . htmlspecialchars($hint, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>'
                : '';

            return $h . $btn;
        };

        return '<div style="margin:10px 0 18px;">'
            . cw_mailing_ai_two_col($wrap($left, $hintL), $wrap($right, $hintR), '0 0 10px')
            . '</div>';
    }

    $html = '';
    foreach ($pair as $btn) {
        $html .= cw_mailing_ai_cta_button($btn['url'], $btn['label'], $btn['variant'], $btn['hint'] ?? '');
    }

    return $html;
}

function cw_mailing_ai_cta_button(string $url, string $label, string $variant = 'primary', string $hint = ''): string
{
    if ($hint === '') {
        $hint = $variant === 'whatsapp'
            ? 'Escríbenos y te respondemos el mismo día.'
            : '¿Seguimos? Te proponemos el siguiente paso.';
    }

    return '<div style="margin:10px 0 14px;">'
        . cw_mailing_cta_pair($hint, $url, $label, $variant === 'ghost' ? 'primary' : $variant)
        . '</div>';
}

function cw_mailing_ai_cta_anchor(string $url, string $label, string $variant = 'primary'): string
{
    $bg = '#000147';
    $color = '#ffffff';
    $border = '1px solid #000147';
    if ($variant === 'whatsapp') {
        $bg = '#128c7e';
        $border = '1px solid #128c7e';
    } elseif ($variant === 'ghost') {
        $bg = '#000147';
        $color = '#ffffff';
        $border = '1px solid #000147';
    }

    return '<a href="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" '
        . 'style="display:block;width:100%;box-sizing:border-box;padding:16px 20px;border-radius:12px;background:' . $bg . ';color:' . $color . ';'
        . 'font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,\'Helvetica Neue\',Helvetica,Arial,sans-serif;'
        . 'font-weight:700;font-size:15px;text-align:center;text-decoration:none;border:' . $border . ';">'
        . cw_mailing_ai_inline($label) . '</a>';
}

/**
 * Si la IA no puso suficientes botones, inserta 2 en puntos calientes.
 *
 * @param list<mixed> $blocks
 * @param array<string,mixed> $draft
 * @return list<mixed>
 */
function cw_mailing_ai_place_strategic_ctas(array $blocks, array $draft): array
{
    $ctaTypes = ['cta', 'button', 'cta_row', 'ctas', 'buttons'];
    $count = 0;
    foreach ($blocks as $block) {
        if (is_array($block) && in_array(strtolower((string) ($block['type'] ?? '')), $ctaTypes, true)) {
            $count++;
        }
    }
    if ($count >= 2) {
        return $blocks;
    }

    $wa = cw_mailing_ai_default_wa_url('Hola, vi el correo de ConlineWeb y quiero información.');
    $web = trim((string) ($draft['cta_url'] ?? 'https://conlineweb.com/'));
    if ($web === '' || str_contains($web, 'whatsapp')) {
        $web = 'https://conlineweb.com/demos-y-precios/';
    }

    $mid = [
        'type' => 'cta',
        'label' => 'Hablar por WhatsApp',
        'url' => $wa,
        'variant' => 'whatsapp',
        'hint' => 'Respuesta en horario hábil',
    ];
    $close = [
        'type' => 'cta_row',
        'items' => [
            ['label' => (string) ($draft['cta_label'] ?: 'Agendar asesoría'), 'url' => $wa, 'variant' => 'whatsapp'],
            ['label' => 'Ver demos y precios', 'url' => $web, 'variant' => 'primary'],
        ],
    ];

    $out = [];
    $insertedMid = $count >= 1;
    $afterHot = false;
    foreach ($blocks as $i => $block) {
        $out[] = $block;
        $type = is_array($block) ? strtolower((string) ($block['type'] ?? '')) : '';
        if (!$insertedMid && ($type === 'p' || $i === 0)) {
            $out[] = $mid;
            $insertedMid = true;
        }
        if (in_array($type, ['pricing', 'prices', 'planes', 'steps', 'offer'], true)) {
            $afterHot = true;
        }
    }
    if (!$insertedMid) {
        array_unshift($out, $mid);
    }
    if ($count < 2) {
        $out[] = $close;
    } elseif ($afterHot) {
        $out[] = $close;
    }

    return $out;
}

/** @param list<mixed> $items */
function cw_mailing_ai_block_steps(array $items): string
{
    $items = cw_mailing_ai_clean_items($items, 3);
    if ($items === []) {
        return '';
    }
    $rows = [];
    $i = 0;
    foreach ($items as $item) {
        $i++;
        $num = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        $url = trim((string) ($item['url'] ?? ''));
        $rows[] = cw_mailing_ai_sym_row(
            $item['title'] !== '' ? $item['title'] : ('Paso ' . $num),
            $item['text'],
            $url,
            $num,
            '#000147',
            ''
        );
    }

    return cw_mailing_ai_divided_stack($rows);
}

/** @param list<mixed> $items */
function cw_mailing_ai_block_split(array $items): string
{
    $items = cw_mailing_ai_clean_items($items, 2);
    if (count($items) < 2) {
        return cw_mailing_ai_block_points($items);
    }
    $rows = [];
    foreach ($items as $i => $item) {
        $icon = cw_mailing_ai_icon_for_title($item['title']);
        $known = in_array($icon['mark'], ['WA', 'G', 'SEO', 'SHOP', 'IA', 'SW', 'HOST', 'WEB', 'FORM'], true);
        $mark = $known ? $icon['mark'] : str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
        $url = trim((string) ($item['url'] ?? ''));
        if ($url === '') {
            $url = cw_mailing_ai_default_url_for_title($item['title']);
        }
        $rows[] = cw_mailing_ai_sym_row(
            $item['title'],
            $item['text'],
            $url,
            $mark,
            $icon['bg'],
            (string) ($icon['img'] ?? '')
        );
    }

    return cw_mailing_ai_divided_stack($rows);
}

/** @param list<mixed> $items */
function cw_mailing_ai_block_channels(array $items): string
{
    $items = cw_mailing_ai_clean_items($items, 3);
    if ($items === []) {
        return '';
    }

    $rows = [];
    foreach ($items as $item) {
        $canon = cw_mailing_ai_channel_canonical((string) ($item['title'] ?? ''));
        $title = $canon['title'] ?? (string) ($item['title'] ?? '');
        $text = $canon['text'] ?? (string) ($item['text'] ?? '');
        if ($title === '') {
            $title = 'Canal';
        }
        $url = trim((string) ($item['url'] ?? ''));
        if ($url === '') {
            $url = cw_mailing_ai_default_url_for_title($title);
        }
        $icon = cw_mailing_ai_icon_for_title($title);
        $rows[] = cw_mailing_ai_sym_row(
            $title,
            $text,
            $url,
            $icon['mark'],
            $icon['bg'],
            (string) ($icon['img'] ?? '')
        );
    }

    return cw_mailing_ai_divided_stack($rows);
}

/**
 * Lista de varios links (etiqueta + URL) según corresponda.
 *
 * @param list<mixed> $items
 */
function cw_mailing_ai_block_links(array $items): string
{
    $items = cw_mailing_ai_clean_items($items, 6);
    if ($items === []) {
        return '';
    }
    $ff = cw_mailing_ai_ff();
    $built = [];
    $i = 0;
    foreach ($items as $item) {
        $url = trim((string) ($item['url'] ?? ''));
        if ($url === '') {
            $url = cw_mailing_ai_default_url_for_title($item['title'] !== '' ? $item['title'] : $item['text']);
        }
        if ($url === '') {
            continue;
        }
        $i++;
        $title = $item['title'] !== '' ? $item['title'] : ('Enlace ' . $i);
        $icon = cw_mailing_ai_icon_for_title($title);
        $linkText = $item['text'] !== ''
            ? $item['text']
            : 'Abrir enlace';
        $built[] = cw_mailing_ai_icon_title_block(
            $title,
            $linkText,
            $icon['mark'],
            $icon['bg'],
            (string) ($icon['img'] ?? ''),
            $url,
            14,
            13
        );
    }
    if ($built === []) {
        return '';
    }

    $stack = cw_mailing_ai_divided_stack($built);
    $label = '<div style="margin:0 0 12px;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#555555;font-family:' . $ff . ';">Enlaces</div>';

    /* Etiqueta arriba + mismo stack con líneas (reusa el card interno). */
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;">'
        . '<tr><td style="padding:0 0 8px;">' . $label . '</td></tr>'
        . '<tr><td style="padding:0;">' . $stack . '</td></tr>'
        . '</table>';
}

/** @param list<mixed> $items */
function cw_mailing_ai_block_stats(array $items): string
{
    $items = cw_mailing_ai_clean_items($items, 3);
    if ($items === []) {
        return '';
    }
    $ff = cw_mailing_ai_ff();
    $mk = static function (array $item) use ($ff): string {
        $inner = ($item['title'] !== '' ? '<strong style="display:block;margin:0 0 8px;font-size:24px;line-height:1.15;color:#000147;text-align:center;font-family:' . $ff . ';">' . cw_mailing_ai_inline($item['title']) . '</strong>' : '')
            . ($item['text'] !== '' ? '<span style="display:block;font-size:12px;line-height:1.4;min-height:34px;color:#333333;text-align:center;font-family:' . $ff . ';">' . cw_mailing_ai_inline($item['text']) . '</span>' : '<span style="display:block;min-height:34px;">&nbsp;</span>');

        return cw_mailing_ai_soft_card($inner, '22px 12px');
    };
    $cards = [];
    foreach ($items as $item) {
        $cards[] = $mk($item);
    }

    return cw_mailing_ai_three_col($cards, '0');
}

/** @param list<mixed> $items */
function cw_mailing_ai_block_points(array $items): string
{
    $items = cw_mailing_ai_clean_items($items, 4);
    if ($items === []) {
        return '';
    }
    $rows = [];
    $i = 0;
    foreach ($items as $item) {
        $i++;
        $num = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        $icon = cw_mailing_ai_icon_for_title($item['title']);
        $useIcon = ($icon['mark'] === 'WA' || $icon['mark'] === 'G' || $icon['mark'] === 'IA' || $icon['mark'] === 'SHOP');
        $rows[] = cw_mailing_ai_sym_row(
            $item['title'] !== '' ? $item['title'] : ('Punto ' . $num),
            $item['text'],
            trim((string) ($item['url'] ?? '')),
            $useIcon ? $icon['mark'] : $num,
            $useIcon ? $icon['bg'] : '#000147',
            $useIcon ? (string) ($icon['img'] ?? '') : ''
        );
    }

    return cw_mailing_ai_divided_stack($rows);
}

/** Borrador de muestra (no llama a OpenAI) para el botón Ver ejemplo. */
function cw_mailing_ai_example_draft(): array
{
    $data = [
        'title' => 'Captación corporativa León',
        'theme' => 'Web',
        'subject' => '{empresa}: del sitio al contacto comercial',
        'preheader' => 'WhatsApp, Google y un siguiente paso claro para tu empresa en León.',
        'layout' => 'captacion',
        'kicker' => 'Captación en México',
        'headline' => 'Un sitio que abre conversación, no solo visita',
        'lead' => 'Diseñamos la ruta digital para que {empresa} reciba contactos listos para hablar.',
        'blocks' => [
            [
                'type' => 'p',
                'text' => 'Hola {nombre}, en ConlineWeb construimos presencia digital para empresas que ya venden y necesitan que el sitio trabaje como un ejecutivo: claro, local y con un canal de respuesta.',
            ],
            [
                'type' => 'steps',
                'items' => [
                    ['title' => 'Diagnóstico', 'text' => 'Revisamos sitio, Google Business y cómo llega hoy el contacto.'],
                    ['title' => 'Ruta de conversión', 'text' => 'WhatsApp, formulario y seguimiento en un solo flujo.'],
                    ['title' => 'Arranque', 'text' => 'Propuesta de alcance, tiempos y responsable. Sin letra chica.'],
                ],
            ],
            [
                'type' => 'split',
                'items' => [
                    ['title' => 'Sitio corporativo', 'text' => 'Página que explica la oferta y empuja a una llamada o WhatsApp.'],
                    ['title' => 'SEO local León', 'text' => 'Textos y estructura para aparecer cuando buscan el servicio en tu ciudad.'],
                ],
            ],
            [
                'type' => 'channels',
                'items' => [
                    ['title' => 'WhatsApp', 'text' => 'Contáctanos para resolver tus dudas.', 'url' => 'https://api.whatsapp.com/send?phone=5214771181285&text=' . rawurlencode('Hola, vi el correo')],
                    ['title' => 'Google', 'text' => 'Infórmate más sobre nuestros servicios.', 'url' => 'https://www.google.com/search?q=ConlineWeb+Le%C3%B3n'],
                ],
            ],
            [
                'type' => 'links',
                'items' => [
                    ['title' => 'Demos y precios', 'url' => 'https://conlineweb.com/demos-y-precios/', 'text' => 'Revisa paquetes y referencias'],
                    ['title' => 'Sitio ConlineWeb', 'url' => 'https://conlineweb.com/', 'text' => 'Conoce la agencia'],
                ],
            ],
            [
                'type' => 'pricing',
                'items' => [
                    ['title' => 'Página web', 'price' => 'Desde $4,600 MXN', 'text' => 'Sitio corporativo responsive', 'url' => 'https://conlineweb.com/demos-y-precios/'],
                    ['title' => 'SEO y GEO', 'price' => 'Desde $3,500 MXN/mes', 'text' => 'Mejora tu visibilidad en Google', 'url' => 'https://conlineweb.com/'],
                ],
            ],
            [
                'type' => 'highlight',
                'text' => 'El siguiente paso es una asesoría de 20 minutos: te decimos qué conviene primero, sitio, tienda o captación local.',
            ],
        ],
        'cta_label' => 'Agendar asesoría',
        'cta_url' => 'https://api.whatsapp.com/send?phone=5214771181285&text=' . rawurlencode('Hola, quiero una asesoría de captación para mi empresa.'),
        'cta_variant' => 'whatsapp',
        'cta_hint' => '20 minutos. Te decimos qué conviene primero.',
        'image_url' => '',
    ];

    return cw_mailing_ai_normalize_draft($data, [
        'title' => 'Captación corporativa León',
        'theme' => 'Web',
    ], []);
}

function cw_mailing_ai_inline(string $text): string
{
    $text = strip_tags($text, '<strong><b><em><i>');
    $text = preg_replace('/\{(nombre|empresa|correo|telefono)\}/', '%%VAR_$1%%', $text) ?? $text;
    $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $text = strtr($text, [
        '%%VAR_nombre%%' => '{nombre}',
        '%%VAR_empresa%%' => '{empresa}',
        '%%VAR_correo%%' => '{correo}',
        '%%VAR_telefono%%' => '{telefono}',
        '&lt;strong&gt;' => '<strong>',
        '&lt;/strong&gt;' => '</strong>',
        '&lt;b&gt;' => '<strong>',
        '&lt;/b&gt;' => '</strong>',
        '&lt;em&gt;' => '<em>',
        '&lt;/em&gt;' => '</em>',
        '&lt;i&gt;' => '<em>',
        '&lt;/i&gt;' => '</em>',
    ]);

    return $text;
}

/**
 * Convierte títulos tipo slug a etiqueta legible en español.
 */
function cw_mailing_ai_humanize_label(string $title, string $fallback = ''): string
{
    $title = trim($title);
    if ($title === '') {
        $title = trim($fallback);
    }
    if ($title === '') {
        return 'Plantilla de mailing';
    }

    $looksSlug = (bool) preg_match('/[_]/', $title)
        || (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)+$/u', $title)
        || (bool) preg_match('/^[a-z0-9]+(?:_[a-z0-9]+)+$/u', $title);

    if ($looksSlug) {
        $title = str_replace(['_', '-'], ' ', $title);
        $title = preg_replace('/\s+/u', ' ', $title) ?? $title;
        $title = trim($title);
        $title = mb_convert_case($title, MB_CASE_TITLE, 'UTF-8');
        // Ajustes frecuentes en español
        $title = str_replace(
            [' De ', ' Del ', ' La ', ' Las ', ' Los ', ' Y ', ' En ', ' Para ', ' Web ', ' Desarrollo '],
            [' de ', ' del ', ' la ', ' las ', ' los ', ' y ', ' en ', ' para ', ' web ', ' desarrollo '],
            $title
        );
        // "Tendencias Desarrollo" → "Tendencias de desarrollo"
        $title = preg_replace('/\bTendencias desarrollo\b/ui', 'Tendencias de desarrollo', $title) ?? $title;
        $title = preg_replace('/\bGuia\b/u', 'Guía', $title) ?? $title;
        $title = preg_replace('/\bSeo\b/u', 'SEO', $title) ?? $title;
        // Capitalizar primera letra
        $title = mb_strtoupper(mb_substr($title, 0, 1, 'UTF-8'), 'UTF-8')
            . mb_substr($title, 1, null, 'UTF-8');
    }

    // Limitar longitud de catálogo
    if (mb_strlen($title) > 90) {
        $title = rtrim(mb_substr($title, 0, 87)) . '…';
    }

    return $title;
}

function cw_mailing_ai_humanize_theme(string $theme): string
{
    $theme = trim($theme);
    if ($theme === '') {
        return 'General';
    }
    if (preg_match('/[_-]/', $theme) || preg_match('/^[a-z0-9]+(?:[_-][a-z0-9]+)+$/u', $theme)) {
        $theme = cw_mailing_ai_humanize_label($theme, 'General');
    }
    // Tomar primeras 1-3 palabras
    $parts = preg_split('/\s+/u', $theme) ?: [];
    $theme = implode(' ', array_slice($parts, 0, 3));
    if (mb_strlen($theme) > 40) {
        $theme = mb_substr($theme, 0, 40);
    }

    return $theme !== '' ? $theme : 'General';
}

function cw_mailing_ai_humanize_subject(string $subject, string $titleFallback): string
{
    $subject = trim($subject);
    if ($subject === '') {
        return $titleFallback !== '' ? $titleFallback . ' — ConlineWeb' : 'Novedades de ConlineWeb';
    }
    if (preg_match('/[_]/', $subject) || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)+$/u', $subject)) {
        $subject = cw_mailing_ai_humanize_label($subject, $titleFallback);
    }

    return $subject;
}

/**
 * @param list<array{role:string,content:mixed}> $messages
 * @return array{ok:bool,content?:string,error?:string}
 */
function cw_mailing_ai_chat_multimodal(array $messages, float $temperature = 0.55): array
{
    if (!cw_seo_mexico_ai_available()) {
        return ['ok' => false, 'error' => 'OpenAI no configurada (OPENAI_API_KEY).'];
    }
    $apiKey = OPENAI_API_KEY;
    $model = function_exists('cw_seo_mexico_ai_model') ? cw_seo_mexico_ai_model() : 'gpt-4o-mini';

    $payload = [
        'model' => $model,
        'messages' => $messages,
        'temperature' => $temperature,
        'response_format' => ['type' => 'json_object'],
    ];

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
        CURLOPT_TIMEOUT => 120,
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
        return ['ok' => false, 'error' => 'Respuesta OpenAI inválida'];
    }
    if (!empty($data['error'])) {
        return ['ok' => false, 'error' => (string) ($data['error']['message'] ?? 'Error OpenAI')];
    }
    if ($code >= 400) {
        $msg = (string) ($data['error']['message'] ?? 'error');
        return ['ok' => false, 'error' => 'HTTP ' . $code . ': ' . $msg];
    }
    $content = (string) ($data['choices'][0]['message']['content'] ?? '');
    if ($content === '') {
        return ['ok' => false, 'error' => 'OpenAI sin contenido'];
    }

    return ['ok' => true, 'content' => $content];
}

function cw_mailing_ai_sanitize_body(string $html): string
{
    $html = preg_replace('#<(script|iframe|object|embed|form)[^>]*>.*?</\1>#is', '', $html) ?? $html;
    $html = preg_replace('#\son\w+\s*=\s*([\'"]).*?\1#i', '', $html) ?? $html;
    $allowed = '<p><br><strong><b><em><i><ul><ol><li><a><span><h2><h3><h4><table><tr><td><div>';
    $clean = strip_tags($html, $allowed);

    return trim($clean);
}
