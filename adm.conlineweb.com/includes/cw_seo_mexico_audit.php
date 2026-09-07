<?php
/**
 * Auditoría SEO México en vivo:
 * - Crawl HTTP completo (plazas + servicios geo)
 * - Findings → nuevas tareas en checklist
 * - Auto-correcciones seguras en el árbol local del sitio + re-check
 */
require_once __DIR__ . '/cw_seo_mexico_checklist.php';
require_once __DIR__ . '/cw_seo_mexico_autofix.php';
require_once __DIR__ . '/cw_seo_mexico_multisource.php';

function cw_seo_mexico_audit_base_url(): string
{
    return 'https://conlineweb.com';
}

/**
 * @return list<array{url:string,kind:string,city:string,label:string}>
 */
function cw_seo_mexico_audit_target_urls(): array
{
    $base = rtrim(cw_seo_mexico_audit_base_url(), '/');
    $out = [];
    $out[] = ['url' => $base . '/', 'kind' => 'home', 'city' => '', 'label' => 'Home'];
    $out[] = ['url' => $base . '/mexico/', 'kind' => 'mexico_hub', 'city' => '', 'label' => 'Hub México'];
    $out[] = ['url' => $base . '/contacto/', 'kind' => 'contacto', 'city' => '', 'label' => 'Contacto'];
    $out[] = ['url' => $base . '/sitemap.xml', 'kind' => 'sitemap', 'city' => '', 'label' => 'Sitemap'];
    $out[] = ['url' => $base . '/sitemap-index.xml', 'kind' => 'sitemap', 'city' => '', 'label' => 'Sitemap index'];
    $out[] = ['url' => $base . '/robots.txt', 'kind' => 'robots', 'city' => '', 'label' => 'Robots'];

    $services = [
        'desarrollo-web',
        'software-a-medida',
        'ecommerce',
        'inteligencia-artificial',
        'seo',
    ];

    foreach (cw_seo_mexico_priority_plazas() as $p) {
        $slug = (string) ($p['slug'] ?? '');
        $city = (string) ($p['city'] ?? $slug);
        if ($slug === '') {
            continue;
        }
        $hub = $base . '/mexico/ciudades/' . rawurlencode($slug) . '/';
        $out[] = ['url' => $hub, 'kind' => 'city_hub', 'city' => $city, 'label' => 'Hub ' . $city];
        $out[] = [
            'url' => $hub . 'servicios/',
            'kind' => 'city_services_hub',
            'city' => $city,
            'label' => 'Servicios ' . $city,
        ];
        foreach ($services as $svc) {
            $out[] = [
                'url' => $hub . 'servicios/' . rawurlencode($svc) . '/',
                'kind' => 'city_service',
                'city' => $city,
                'label' => $city . ' · ' . $svc,
            ];
        }
    }

    return $out;
}

/**
 * @return array{ok:bool,status:int,final_url:string,body:string,ms:int,error?:string}
 */
function cw_seo_mexico_audit_fetch(string $url, int $timeout = 18): array
{
    $t0 = microtime(true);
    if (!function_exists('curl_init')) {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'follow_location' => 1,
                'user_agent' => 'ConlineWeb-SEO-Audit/1.0',
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        $ms = (int) round((microtime(true) - $t0) * 1000);
        if ($body === false) {
            return ['ok' => false, 'status' => 0, 'final_url' => $url, 'body' => '', 'ms' => $ms, 'error' => 'fetch_failed'];
        }
        return ['ok' => true, 'status' => 200, 'final_url' => $url, 'body' => (string) $body, 'ms' => $ms];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_USERAGENT => 'ConlineWeb-SEO-Audit/1.0 (+https://conlineweb.com)',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_ENCODING => '',
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $final = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $err = curl_error($ch);
    curl_close($ch);
    $ms = (int) round((microtime(true) - $t0) * 1000);

    if ($body === false) {
        return ['ok' => false, 'status' => $status, 'final_url' => $final ?: $url, 'body' => '', 'ms' => $ms, 'error' => $err ?: 'curl_failed'];
    }

    return [
        'ok' => $status >= 200 && $status < 400,
        'status' => $status,
        'final_url' => $final ?: $url,
        'body' => (string) $body,
        'ms' => $ms,
    ];
}

/**
 * @return array<string,mixed>
 */
function cw_seo_mexico_audit_parse_html(string $html): array
{
    $title = '';
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
        $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    $desc = '';
    if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)["\']/i', $html, $m)
        || preg_match('/<meta[^>]+content=["\']([^"\']*)["\'][^>]+name=["\']description["\']/i', $html, $m)) {
        $desc = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    $canonical = '';
    if (preg_match('/<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']+)["\']/i', $html, $m)
        || preg_match('/<link[^>]+href=["\']([^"\']+)["\'][^>]+rel=["\']canonical["\']/i', $html, $m)) {
        $canonical = trim($m[1]);
    }
    $h1 = '';
    if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m)) {
        $h1 = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    $robots = '';
    if (preg_match('/<meta[^>]+name=["\']robots["\'][^>]+content=["\']([^"\']*)["\']/i', $html, $m)) {
        $robots = strtolower(trim($m[1]));
    }
    $hasSchema = (bool) preg_match('/application\/ld\+json/i', $html);
    $textLen = strlen(trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? ''));
    $hasOg = (bool) preg_match('/property=["\']og:title["\']/i', $html);
    $hasTwitter = (bool) preg_match('/name=["\']twitter:card["\']/i', $html);
    $hasWa = (bool) preg_match('/wa\.me|whatsapp/i', $html);
    $imgs = preg_match_all('/<img\b[^>]*>/i', $html, $imgTags) ? count($imgTags[0]) : 0;
    $imgsNoAlt = 0;
    if (!empty($imgTags[0])) {
        foreach ($imgTags[0] as $tag) {
            if (!preg_match('/\balt\s*=/i', $tag)) {
                $imgsNoAlt++;
            }
        }
    }

    return [
        'title' => $title,
        'description' => $desc,
        'canonical' => $canonical,
        'h1' => $h1,
        'robots' => $robots,
        'has_schema' => $hasSchema,
        'text_len' => $textLen,
        'has_og' => $hasOg,
        'has_twitter' => $hasTwitter,
        'has_whatsapp' => $hasWa,
        'images' => $imgs,
        'images_no_alt' => $imgsNoAlt,
    ];
}

/**
 * @return array{phone:string,email:string,street:string,name:string}
 */
function cw_seo_mexico_audit_nap_needles(): array
{
    $napPath = null;
    $root = cw_seo_mexico_site_root_path();
    if ($root) {
        $cand = $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'cw-nap.php';
        if (is_file($cand)) {
            $napPath = $cand;
        }
    }
    $phone = '477 118 1285';
    $email = 'info@conlineweb.com';
    $street = 'Ana María 104';
    $name = 'ConlineWeb';
    if ($napPath) {
        require_once $napPath;
        if (function_exists('cw_nap')) {
            $n = cw_nap();
            $phone = (string) ($n['phone_primary_display'] ?? $phone);
            $email = (string) ($n['email'] ?? $email);
            $street = (string) ($n['street'] ?? $street);
            $name = (string) ($n['name'] ?? $name);
        }
    }
    return compact('phone', 'email', 'street', 'name');
}

/**
 * @param array<string,mixed> $target
 * @param array<string,mixed> $fetch
 * @param array<string,mixed> $parsed
 * @param array{phone:string,email:string,street:string,name:string} $nap
 * @return list<array<string,mixed>>
 */
function cw_seo_mexico_audit_evaluate(array $target, array $fetch, array $parsed, array $nap): array
{
    $findings = [];
    $url = (string) $target['url'];
    $kind = (string) $target['kind'];
    $label = (string) $target['label'];
    $slugKey = preg_replace('/[^a-z0-9]+/', '_', strtolower($label)) ?: 'url';

    if ($kind === 'sitemap' || $kind === 'robots') {
        if (empty($fetch['ok']) || (int) $fetch['status'] !== 200) {
            $findings[] = [
                'finding_key' => ($kind === 'robots' ? 'robots_unreachable' : 'sitemap_unreachable_' . $slugKey),
                'check_type' => $kind === 'robots' ? 'robots_http' : 'sitemap_http',
                'severity' => 'critical',
                'url' => $url,
                'title' => ($kind === 'robots' ? 'robots.txt' : 'Sitemap') . ' no responde en vivo: ' . $label,
                'evidence' => 'HTTP ' . (int) ($fetch['status'] ?? 0),
                'correction' => 'Verificar que el archivo esté publicado en producción y accesible.',
                'auto_fixable' => false,
            ];
        }
        return $findings;
    }

    if (empty($fetch['ok']) || (int) $fetch['status'] >= 400 || (int) $fetch['status'] === 0) {
        $findings[] = [
            'finding_key' => 'http_' . $slugKey,
            'check_type' => 'http_status',
            'severity' => 'critical',
            'url' => $url,
            'title' => 'URL caída o error HTTP: ' . $label,
            'evidence' => 'HTTP ' . (int) ($fetch['status'] ?? 0) . ' · ' . ($fetch['error'] ?? ''),
            'correction' => 'Revisar routing/deploy de la URL y regenerar página geo si falta.',
            'auto_fixable' => in_array($kind, ['city_hub', 'city_service', 'city_services_hub'], true),
            'meta' => ['kind' => $kind, 'city' => $target['city'] ?? ''],
        ];
        return $findings;
    }

    $title = (string) ($parsed['title'] ?? '');
    $desc = (string) ($parsed['description'] ?? '');
    $h1 = (string) ($parsed['h1'] ?? '');
    $canonical = (string) ($parsed['canonical'] ?? '');
    $robots = (string) ($parsed['robots'] ?? '');

    $geoFixable = in_array($kind, ['city_hub', 'city_service', 'city_services_hub'], true);

    if ($title === '' || mb_strlen($title) < 15) {
        $findings[] = [
            'finding_key' => 'title_' . $slugKey,
            'check_type' => 'title',
            'severity' => 'high',
            'url' => $url,
            'title' => 'Title débil o ausente: ' . $label,
            'evidence' => $title !== '' ? ('Title: ' . $title) : 'Sin <title>',
            'correction' => 'Definir title SEO local (≥15 caracteres) en override/plantilla de la plaza.',
            'auto_fixable' => $geoFixable,
            'meta' => ['kind' => $kind],
        ];
    }
    if ($desc === '' || mb_strlen($desc) < 50) {
        $findings[] = [
            'finding_key' => 'meta_' . $slugKey,
            'check_type' => 'meta_description',
            'severity' => 'high',
            'url' => $url,
            'title' => 'Meta description débil: ' . $label,
            'evidence' => $desc !== '' ? ('Desc: ' . mb_substr($desc, 0, 120)) : 'Sin meta description',
            'correction' => 'Añadir meta description local de 50–160 caracteres.',
            'auto_fixable' => $geoFixable,
            'meta' => ['kind' => $kind],
        ];
    }
    if ($h1 === '') {
        $findings[] = [
            'finding_key' => 'h1_' . $slugKey,
            'check_type' => 'h1',
            'severity' => 'high',
            'url' => $url,
            'title' => 'Sin H1 visible: ' . $label,
            'evidence' => 'No se detectó <h1> en HTML',
            'correction' => 'Asegurar un único H1 con intención local en la plantilla.',
            'auto_fixable' => $geoFixable,
            'meta' => ['kind' => $kind],
        ];
    }
    if ($canonical === '') {
        $findings[] = [
            'finding_key' => 'canonical_' . $slugKey,
            'check_type' => 'canonical',
            'severity' => 'medium',
            'url' => $url,
            'title' => 'Sin canonical: ' . $label,
            'evidence' => 'Falta link rel=canonical',
            'correction' => 'Emitir canonical absoluto hacia la URL canónica México.',
            'auto_fixable' => false,
        ];
    }
    if ($robots !== '' && (str_contains($robots, 'noindex') || str_contains($robots, 'none'))) {
        $findings[] = [
            'finding_key' => 'robots_' . $slugKey,
            'check_type' => 'robots',
            'severity' => 'critical',
            'url' => $url,
            'title' => 'Página con noindex: ' . $label,
            'evidence' => 'robots=' . $robots,
            'correction' => 'Quitar noindex en páginas comerciales indexables.',
            'auto_fixable' => false,
        ];
    }
    if (empty($parsed['has_schema']) && in_array($kind, ['city_hub', 'city_service', 'home', 'contacto'], true)) {
        $findings[] = [
            'finding_key' => 'schema_' . $slugKey,
            'check_type' => 'schema',
            'severity' => 'medium',
            'url' => $url,
            'title' => 'Sin JSON-LD detectado: ' . $label,
            'evidence' => 'No hay application/ld+json',
            'correction' => 'Incluir schema LocalBusiness/Service/FAQ según plantilla.',
            'auto_fixable' => false,
        ];
    }
    if ((int) ($parsed['text_len'] ?? 0) < 400 && in_array($kind, ['city_hub', 'city_service'], true)) {
        $findings[] = [
            'finding_key' => 'thin_' . $slugKey,
            'check_type' => 'thin_content',
            'severity' => 'medium',
            'url' => $url,
            'title' => 'Contenido corto (thin): ' . $label,
            'evidence' => 'Texto ~' . (int) $parsed['text_len'] . ' chars',
            'correction' => 'Ampliar copy local / casos / FAQs en override de plaza.',
            'auto_fixable' => false,
        ];
    }

    if (empty($parsed['has_og']) && $kind !== 'sitemap') {
        $findings[] = [
            'finding_key' => 'og_' . $slugKey,
            'check_type' => 'og_tags',
            'severity' => 'medium',
            'url' => $url,
            'title' => 'Sin Open Graph: ' . $label,
            'evidence' => 'Falta og:title',
            'correction' => 'Emitir meta og:* en la plantilla (home-template / seo-meta).',
            'auto_fixable' => false,
        ];
    }
    if (empty($parsed['has_whatsapp']) && in_array($kind, ['city_hub', 'city_service', 'contacto', 'home'], true)) {
        $findings[] = [
            'finding_key' => 'wa_' . $slugKey,
            'check_type' => 'whatsapp_cta',
            'severity' => 'medium',
            'url' => $url,
            'title' => 'Sin CTA WhatsApp detectable: ' . $label,
            'evidence' => 'No hay wa.me / whatsapp en HTML',
            'correction' => 'Incluir botón/enlace WhatsApp (NAP) en plantilla o footer.',
            'auto_fixable' => false,
        ];
    }
    if ((int) ($parsed['images_no_alt'] ?? 0) >= 3) {
        $findings[] = [
            'finding_key' => 'alt_' . $slugKey,
            'check_type' => 'img_alt',
            'severity' => 'low',
            'url' => $url,
            'title' => 'Varias imágenes sin ALT: ' . $label,
            'evidence' => (int) $parsed['images_no_alt'] . ' <img> sin alt',
            'correction' => 'Añadir atributos alt descriptivos (accesibilidad + SEO).',
            'auto_fixable' => false,
        ];
    }

    // NAP en páginas clave
    if (in_array($kind, ['home', 'contacto', 'city_hub'], true)) {
        $hay = $fetch['body'] ?? '';
        $phoneOk = $nap['phone'] !== '' && (str_contains($hay, $nap['phone']) || str_contains($hay, preg_replace('/\D+/', '', $nap['phone']) ?? ''));
        $emailOk = $nap['email'] !== '' && stripos($hay, $nap['email']) !== false;
        $streetOk = $nap['street'] !== '' && stripos($hay, $nap['street']) !== false;
        if (!$phoneOk || !$emailOk || !$streetOk) {
            $miss = [];
            if (!$phoneOk) {
                $miss[] = 'teléfono';
            }
            if (!$emailOk) {
                $miss[] = 'email';
            }
            if (!$streetOk) {
                $miss[] = 'dirección';
            }
            $findings[] = [
                'finding_key' => 'nap_' . $slugKey,
                'check_type' => 'nap_live',
                'severity' => 'high',
                'url' => $url,
                'title' => 'NAP incompleto en vivo: ' . $label,
                'evidence' => 'Falta: ' . implode(', ', $miss),
                'correction' => 'Usar includes/cw-nap.php en footer/schema de la plantilla y redeploy.',
                'auto_fixable' => true,
                'meta' => ['missing' => $miss],
            ];
        }
    }

    return $findings;
}

function cw_seo_mexico_audit_ensure_city_stub(string $citySlug, string $serviceSlug = ''): array
{
    return cw_seo_mexico_autofix_city_stub($citySlug, $serviceSlug);
}

/**
 * @param array<string,mixed> $finding
 */
function cw_seo_mexico_audit_auto_fix(array $finding, ?mysqli $conn = null, int $userId = 0): array
{
    $type = (string) ($finding['check_type'] ?? '');
    $url = (string) ($finding['url'] ?? '');
    $fkey = (string) ($finding['finding_key'] ?? '');

    // Stubs geo: 404 HTTP o SEO vacío en landings ciudad (title/meta/h1)
    if (in_array($type, ['http_status', 'title', 'meta_description', 'h1'], true)
        && preg_match('#/mexico/ciudades/([a-z0-9\-]+)(?:/(servicios)(?:/([a-z0-9\-]+))?)?/?$#i', $url, $m)) {
        $city = strtolower($m[1]);
        if (!empty($m[3])) {
            $res = cw_seo_mexico_autofix_city_stub($city, strtolower($m[3]));
        } elseif (!empty($m[2]) && strtolower($m[2]) === 'servicios') {
            $res = cw_seo_mexico_autofix_city_stub($city, 'servicios');
        } else {
            $res = cw_seo_mexico_autofix_city_stub($city, '');
        }
        if ($conn) {
            cw_seo_mexico_autofix_log(
                $conn,
                'city_stub',
                (string) ($res['path'] ?? ''),
                !empty($res['ok']) && !empty($res['applied']),
                (string) ($res['error'] ?? ($res['reason'] ?? 'stub')),
                isset($res['backup']) ? (string) $res['backup'] : null,
                $userId,
                $fkey
            );
        }
        // Si el stub ya existía, igual reportamos OK para revalidar en vivo
        if (!empty($res['ok']) && empty($res['applied'])) {
            $res['note'] = 'Stub ya existía; revisa overrides/deploy si el title/meta sigue débil en vivo.';
            $res['applied'] = ($type === 'http_status') ? false : false;
        }
        return $res;
    }

    if (($type === 'ms_redirects' || $type === 'ms_override_vs_live') && $conn) {
        $fixed = 0;
        foreach (cw_seo_mexico_priority_plazas() as $p) {
            $slug = (string) ($p['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $res = cw_seo_mexico_autofix_city_stub($slug, '');
            if (!empty($res['ok']) && !empty($res['applied'])) {
                $fixed++;
                cw_seo_mexico_autofix_log(
                    $conn,
                    'city_stub',
                    (string) ($res['path'] ?? ''),
                    true,
                    'multisource short/hub stub',
                    isset($res['backup']) ? (string) $res['backup'] : null,
                    $userId,
                    $fkey
                );
            }
        }
        return [
            'ok' => $fixed > 0,
            'applied' => $fixed > 0,
            'note' => 'Stubs geo intentados: ' . $fixed,
        ];
    }

    if (($type === 'nap_live' || $type === 'ms_nap') && $conn) {
        $apply = cw_seo_mexico_checklist_apply_marca_consistente($conn, $userId);
        $ok = !empty($apply['ok']);
        cw_seo_mexico_autofix_log(
            $conn,
            'nap_verify',
            'includes/cw-nap.php',
            $ok,
            (string) ($apply['error'] ?? 'NAP verificado / checklist marca_consistente'),
            null,
            $userId,
            $fkey
        );
        return [
            'ok' => $ok,
            'applied' => !empty($apply['applied']),
            'note' => 'NAP: verificación de cableado local (mismo servidor).',
            'error' => $apply['error'] ?? null,
        ];
    }

    return ['ok' => false, 'applied' => false, 'error' => 'No auto-fixable'];
}

/**
 * Upsert finding + crear/actualizar tarea dinámica en checklist.
 *
 * @param array<string,mixed> $finding
 */
/**
 * @param array{defer_autofix?:bool} $opts
 */
function cw_seo_mexico_audit_upsert_finding(mysqli $conn, int $runId, array $finding, int $userId = 0, array $opts = []): array
{
    $fkey = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) ($finding['finding_key'] ?? ''))) ?? '';
    if ($fkey === '') {
        return ['ok' => false];
    }
    $checkType = substr((string) ($finding['check_type'] ?? 'general'), 0, 60);
    $severity = substr((string) ($finding['severity'] ?? 'medium'), 0, 16);
    $url = mb_substr((string) ($finding['url'] ?? ''), 0, 500);
    $title = mb_substr((string) ($finding['title'] ?? $fkey), 0, 255);
    $evidence = (string) ($finding['evidence'] ?? '');
    $correction = (string) ($finding['correction'] ?? '');
    require_once __DIR__ . '/cw_seo_mexico_external.php';
    $isExternal = cw_seo_mexico_external_is_check($checkType, $finding);
    // Externas nunca se auto-aplican en código
    $auto = (!$isExternal && !empty($finding['auto_fixable'])) ? 1 : 0;
    $meta = is_array($finding['meta'] ?? null) ? $finding['meta'] : [];
    if ($isExternal) {
        $meta['execution'] = 'external';
        $meta['kind'] = (string) ($meta['kind'] ?? 'external_task');
    }
    $metaJson = $meta !== [] ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null;

    // Cerrar duplicados open previos del mismo key
    $conn->query(
        "UPDATE cw_seo_mexico_audit_findings
         SET status = 'superseded', updated_at = NOW()
         WHERE finding_key = '" . $conn->real_escape_string($fkey) . "' AND status = 'open'"
    );

    $stmt = $conn->prepare(
        'INSERT INTO cw_seo_mexico_audit_findings
         (run_id, finding_key, check_type, severity, status, url, title, evidence, correction, auto_fixable, auto_applied, task_key, meta_json, created_at, updated_at)
         VALUES (?, ?, ?, ?, \'open\', ?, ?, ?, ?, ?, 0, NULL, ?, NOW(), NOW())'
    );
    if (!$stmt) {
        return ['ok' => false];
    }
    $stmt->bind_param(
        'isssssssis',
        $runId,
        $fkey,
        $checkType,
        $severity,
        $url,
        $title,
        $evidence,
        $correction,
        $auto,
        $metaJson
    );
    $stmt->execute();
    $findingId = (int) $stmt->insert_id;
    $stmt->close();

    // Tarea dinámica agrupada por check_type (no 1 tarea por URL → lista manejable)
    if ($isExternal) {
        $taskKey = 'external_' . preg_replace('/[^a-z0-9_]/', '', $checkType);
        $phase = cw_seo_mexico_external_phase_for($checkType);
        $taskTitle = 'SEO/GEO externo · ' . str_replace(['ext_', '_'], ['', ' '], $checkType);
        $sort = 500 + (int) (crc32($checkType) % 80);
        $srcNote = 'Generada por el análisis: tarea/recomendación externa (GSC, GBP, GEO, autoridad).';
        $userDesc = 'Acción fuera del sitio (Search Console, Maps, reseñas, citaciones, medición). '
            . 'No se corrige con AutoFix de código.';
    } else {
        $taskKey = 'audit_' . preg_replace('/[^a-z0-9_]/', '', $checkType);
        $phase = '06_auditoria_viva';
        $taskTitle = 'Auditoría en vivo · ' . str_replace('_', ' ', $checkType);
        $sort = 900 + (int) (crc32($checkType) % 80);
        $srcNote = 'Generada automáticamente por el auditor en vivo.';
        $userDesc = 'Hallazgos detectados en auditoría HTTP en vivo. Revisar URLs afectadas y aplicar corrección.';
    }
    $desc = $title . ' — ' . $evidence;

    $detail = [
        'source' => $isExternal ? 'external_seo_geo' : 'live_audit',
        'dynamic' => true,
        'execution' => $isExternal ? 'external' : 'code',
        'check_type' => $checkType,
        'user_description' => $userDesc,
        'user_correction' => $correction,
        'severity' => $severity,
        'urls' => [$url],
        'evidence' => [$evidence],
        'finding_keys' => [$fkey],
        'run_id' => $runId,
        'status' => 'parcial',
        'plan' => [
            'can_start_now' => true,
            'start' => date('Y-m-d'),
            'due' => date('Y-m-d', strtotime($isExternal ? '+21 days' : '+14 days')),
            'effort' => $auto ? 'bajo' : ($isExternal ? 'medio-alto' : 'medio'),
            'window' => $isExternal ? 'ops SEO/GEO externo' : 'auditoría continua',
            'note' => $srcNote,
        ],
    ];

    // Merge si la tarea ya existe
    $existing = cw_seo_mexico_checklist_task_detail($conn, $taskKey);
    if (is_array($existing) && is_array($existing['detail'] ?? null)) {
        $prev = $existing['detail'];
        $urls = array_values(array_unique(array_merge(
            is_array($prev['urls'] ?? null) ? $prev['urls'] : [],
            [$url]
        )));
        $ev = array_values(array_unique(array_merge(
            is_array($prev['evidence'] ?? null) ? $prev['evidence'] : [],
            [$evidence]
        )));
        $keys = array_values(array_unique(array_merge(
            is_array($prev['finding_keys'] ?? null) ? $prev['finding_keys'] : [],
            [$fkey]
        )));
        $detail['urls'] = array_slice($urls, 0, 80);
        $detail['evidence'] = array_slice($ev, 0, 80);
        $detail['finding_keys'] = array_slice($keys, 0, 80);
        if ($isExternal) {
            $detail['user_description'] = 'Tarea/recomendación externa «' . $checkType . '». Último: ' . $title;
            $detail['execution'] = 'external';
        } else {
            $detail['user_description'] = count($detail['urls']) . ' URL(s) con hallazgo «' . $checkType . '». Último: ' . $title;
        }
    }

    require_once __DIR__ . '/cw_seo_mexico_checklist_roadmap.php';
    $detail = cw_seo_mexico_roadmap_detail_from_finding($finding, $detail);
    $detailJson = json_encode($detail, JSON_UNESCAPED_UNICODE);
    $ins = $conn->prepare(
        'INSERT INTO cw_seo_mexico_checklist (task_key, phase, title, description, sort_order, done, notes, detail_json, updated_at)
         VALUES (?, ?, ?, ?, ?, 0, NULL, ?, NOW())
         ON DUPLICATE KEY UPDATE
            phase = VALUES(phase),
            title = VALUES(title),
            description = VALUES(description),
            sort_order = VALUES(sort_order),
            done = 0,
            done_at = NULL,
            detail_json = VALUES(detail_json),
            updated_at = NOW()'
    );
    $created = false;
    if ($ins) {
        $ins->bind_param('ssssis', $taskKey, $phase, $taskTitle, $desc, $sort, $detailJson);
        $ins->execute();
        $created = $ins->affected_rows > 0;
        $ins->close();
    }

    $conn->query(
        'UPDATE cw_seo_mexico_audit_findings SET task_key = \'' . $conn->real_escape_string($taskKey) . '\' WHERE id = ' . (int) $findingId
    );

    // En auditoría manual se difiere al botón «Ejecutar correcciones» (con reporte antes/después).
    $autoApplied = false;
    if ($auto && empty($opts['defer_autofix'])) {
        $fix = cw_seo_mexico_audit_auto_fix($finding, $conn, $userId);
        if (!empty($fix['applied'])) {
            $autoApplied = true;
            $conn->query('UPDATE cw_seo_mexico_audit_findings SET auto_applied = 1, status = \'fixed\', updated_at = NOW() WHERE id = ' . (int) $findingId);
            cw_seo_mexico_checklist_log_update(
                $conn,
                $taskKey,
                'AutoFix aplicado (mismo servidor)',
                $title . "\n" . ($fix['path'] ?? json_encode($fix, JSON_UNESCAPED_UNICODE)),
                $userId
            );
        }
    }

    cw_seo_mexico_checklist_log_update(
        $conn,
        $taskKey,
        'Hallazgo auditoría: ' . mb_substr($title, 0, 180),
        $url . "\n" . $evidence . "\nCorrección: " . $correction,
        $userId
    );

    return [
        'ok' => true,
        'finding_id' => $findingId,
        'task_key' => $taskKey,
        'task_created' => $created,
        'auto_applied' => $autoApplied,
    ];
}

/**
 * Cierra findings open por finding_key (p. ej. falso positivo ya corregido).
 *
 * @return int cantidad cerrada
 */
function cw_seo_mexico_audit_resolve_finding_key(mysqli $conn, string $findingKey, int $userId = 0, string $reason = ''): int
{
    $fkey = preg_replace('/[^a-z0-9_\-]/', '', strtolower($findingKey)) ?? '';
    if ($fkey === '') {
        return 0;
    }
    $res = $conn->query(
        "SELECT id, task_key, title, url FROM cw_seo_mexico_audit_findings
         WHERE finding_key = '" . $conn->real_escape_string($fkey) . "' AND status = 'open'
         LIMIT 20"
    );
    if (!$res) {
        return 0;
    }
    $closed = 0;
    while ($row = $res->fetch_assoc()) {
        $id = (int) ($row['id'] ?? 0);
        if ($id < 1) {
            continue;
        }
        $conn->query(
            "UPDATE cw_seo_mexico_audit_findings
             SET status = 'resolved', updated_at = NOW()
             WHERE id = {$id} AND status = 'open'"
        );
        if ($conn->affected_rows < 1) {
            continue;
        }
        $closed++;
        $tk = (string) ($row['task_key'] ?? '');
        if ($tk !== '') {
            cw_seo_mexico_checklist_log_update(
                $conn,
                $tk,
                'Hallazgo resuelto: ' . mb_substr((string) ($row['title'] ?? $fkey), 0, 160),
                ($reason !== '' ? $reason . "\n" : '') . 'finding #' . $id . ' · ' . (string) ($row['url'] ?? ''),
                $userId
            );
        }
    }
    $res->free();
    if ($closed > 0) {
        cw_seo_mexico_audit_reconcile_tasks($conn, $userId);
    }
    return $closed;
}

/**
 * Si el NAP ya está cableado en código, cierra el finding ms_nap_not_wired colgado.
 */
function cw_seo_mexico_audit_heal_nap_wired(mysqli $conn, int $userId = 0): int
{
    $chk = $conn->query(
        "SELECT id FROM cw_seo_mexico_audit_findings
         WHERE finding_key = 'ms_nap_not_wired' AND status = 'open' LIMIT 1"
    );
    if (!$chk || !$chk->fetch_assoc()) {
        if ($chk) {
            $chk->free();
        }
        return 0;
    }
    $chk->free();

    $root = cw_seo_mexico_site_root_path() ?? cw_seo_mexico_autofix_site_root();
    if ($root === null || !function_exists('cw_seo_mexico_multisource_nap_is_wired')) {
        return 0;
    }
    if (!cw_seo_mexico_multisource_nap_is_wired($root)) {
        return 0;
    }
    return cw_seo_mexico_audit_resolve_finding_key(
        $conn,
        'ms_nap_not_wired',
        $userId,
        'Verificado: footer, SEO meta, page-factory México y contacto usan cw-nap.php / cw_nap().'
    );
}

/**
 * Cierra hallazgos open de un prefijo de check_type que no reaparecieron en este run.
 * (Los del mismo finding_key ya pasan a superseded en upsert; esto limpia los que ya no aplican.)
 *
 * @return int cantidad cerrada
 */
function cw_seo_mexico_audit_close_stale_findings(mysqli $conn, int $runId, string $checkTypePrefix = 'ms_', int $userId = 0): int
{
    $prefix = preg_replace('/[^a-z0-9_]/', '', strtolower($checkTypePrefix)) ?? '';
    if ($prefix === '' || $runId < 1) {
        return 0;
    }
    $like = $conn->real_escape_string($prefix) . '%';
    $res = $conn->query(
        "SELECT id, finding_key, task_key, title, url
         FROM cw_seo_mexico_audit_findings
         WHERE status = 'open'
           AND check_type LIKE '{$like}'
           AND run_id <> " . (int) $runId . '
         ORDER BY id ASC
         LIMIT 200'
    );
    if (!$res) {
        return 0;
    }
    $closed = 0;
    while ($row = $res->fetch_assoc()) {
        $id = (int) ($row['id'] ?? 0);
        if ($id < 1) {
            continue;
        }
        $conn->query(
            "UPDATE cw_seo_mexico_audit_findings
             SET status = 'resolved', updated_at = NOW()
             WHERE id = {$id} AND status = 'open'"
        );
        if ($conn->affected_rows < 1) {
            continue;
        }
        $closed++;
        $tk = (string) ($row['task_key'] ?? '');
        if ($tk !== '') {
            cw_seo_mexico_checklist_log_update(
                $conn,
                $tk,
                'Hallazgo resuelto en reauditoría: ' . mb_substr((string) ($row['title'] ?? $row['finding_key'] ?? ''), 0, 160),
                'Ya no se reproduce · finding #' . $id . ' · ' . (string) ($row['url'] ?? ''),
                $userId
            );
        }
    }
    $res->free();
    return $closed;
}

/**
 * Marca tareas de auditoría como hechas si ya no hay findings open de ese tipo.
 */
function cw_seo_mexico_audit_reconcile_tasks(mysqli $conn, int $userId = 0): int
{
    $closed = 0;
    $res = $conn->query(
        "SELECT task_key FROM cw_seo_mexico_checklist WHERE task_key LIKE 'audit_%'"
    );
    if (!$res) {
        return 0;
    }
    while ($row = $res->fetch_assoc()) {
        $tk = (string) $row['task_key'];
        $chk = $conn->query(
            "SELECT COUNT(*) AS c FROM cw_seo_mexico_audit_findings
             WHERE task_key = '" . $conn->real_escape_string($tk) . "' AND status = 'open'"
        );
        $c = 0;
        if ($chk && ($r = $chk->fetch_assoc())) {
            $c = (int) $r['c'];
        }
        if ($c === 0) {
            $done = $conn->prepare(
                'UPDATE cw_seo_mexico_checklist
                 SET done = 1, done_at = NOW(), done_by = ?, updated_at = NOW(),
                     detail_json = JSON_SET(COALESCE(detail_json, \'{}\'), \'$.status\', \'completo\')
                 WHERE task_key = ? AND done = 0'
            );
            // JSON_SET may fail on old MySQL — fallback
            if ($done) {
                $done->bind_param('is', $userId, $tk);
                if (!$done->execute()) {
                    $done->close();
                    cw_seo_mexico_checklist_set_done($conn, $tk, true, $userId, 'Cerrado: sin hallazgos open en última auditoría');
                } else {
                    $closed += $done->affected_rows > 0 ? 1 : 0;
                    $done->close();
                }
            }
        }
    }
    $res->free();
    return $closed;
}

/**
 * Scores 0–100 por URL (SEO / técnico / GEO / conversión).
 *
 * @param array<string,mixed> $fetch
 * @param array<string,mixed> $parsed
 * @param list<array<string,mixed>> $findings
 * @param array<string,mixed> $target
 * @return array{seo:int,tech:int,geo:int,conv:int,overall:int}
 */
function cw_seo_mexico_audit_score_url(array $fetch, array $parsed, array $findings, array $target): array
{
    $seo = 100;
    $tech = 100;
    $geo = 100;
    $conv = 100;
    $kind = (string) ($target['kind'] ?? '');
    $body = (string) ($fetch['body'] ?? '');

    if ($kind === 'sitemap') {
        $ok = !empty($fetch['ok']) && (int) ($fetch['status'] ?? 0) === 200;
        $v = $ok ? 100 : 20;
        return ['seo' => $v, 'tech' => $v, 'geo' => $v, 'conv' => 100, 'overall' => $v];
    }

    if (empty($fetch['ok']) || (int) ($fetch['status'] ?? 0) >= 400) {
        return ['seo' => 15, 'tech' => 10, 'geo' => 20, 'conv' => 20, 'overall' => 15];
    }

    $titleLen = mb_strlen((string) ($parsed['title'] ?? ''));
    $descLen = mb_strlen((string) ($parsed['description'] ?? ''));
    if ($titleLen < 15) {
        $seo -= 25;
    } elseif ($titleLen > 65) {
        $seo -= 8;
    }
    if ($descLen < 50) {
        $seo -= 20;
    } elseif ($descLen > 170) {
        $seo -= 6;
    }
    if (trim((string) ($parsed['h1'] ?? '')) === '') {
        $seo -= 18;
    }
    if (trim((string) ($parsed['canonical'] ?? '')) === '') {
        $tech -= 15;
    }
    $robots = (string) ($parsed['robots'] ?? '');
    if ($robots !== '' && (str_contains($robots, 'noindex') || str_contains($robots, 'none'))) {
        $seo -= 40;
        $tech -= 25;
    }
    if (empty($parsed['has_schema'])) {
        $seo -= 12;
        $geo -= 15;
    }
    if ((int) ($parsed['text_len'] ?? 0) < 400 && in_array($kind, ['city_hub', 'city_service', 'city_services_hub'], true)) {
        $seo -= 10;
        $geo -= 10;
    }

    // GEO / local signals
    if (in_array($kind, ['city_hub', 'city_service', 'city_services_hub', 'contacto', 'home'], true)) {
        $nap = cw_seo_mexico_audit_nap_needles();
        if ($nap['phone'] !== '' && stripos($body, preg_replace('/\D+/', '', $nap['phone']) ?: $nap['phone']) === false
            && stripos($body, $nap['phone']) === false) {
            $geo -= 18;
        }
        if ($nap['email'] !== '' && stripos($body, $nap['email']) === false) {
            $geo -= 10;
        }
        $city = (string) ($target['city'] ?? '');
        if ($city !== '' && stripos($body, $city) === false) {
            $geo -= 12;
        }
    }

    // Conversión básica
    $hasWa = (bool) preg_match('/wa\.me|whatsapp/i', $body);
    $hasForm = (bool) preg_match('/<form[\s>]/i', $body);
    $hasCta = (bool) preg_match('/cotiz|contacto|solicitar|demo/i', $body);
    if (!$hasWa && in_array($kind, ['city_hub', 'city_service', 'contacto', 'home'], true)) {
        $conv -= 15;
    }
    if (!$hasForm && in_array($kind, ['contacto', 'home'], true)) {
        $conv -= 20;
    }
    if (!$hasCta) {
        $conv -= 10;
    }

    foreach ($findings as $f) {
        $sev = (string) ($f['severity'] ?? 'medium');
        $pen = $sev === 'critical' ? 12 : ($sev === 'high' ? 8 : ($sev === 'medium' ? 4 : 2));
        $type = (string) ($f['check_type'] ?? '');
        if (in_array($type, ['title', 'meta_description', 'h1', 'thin_content', 'robots'], true)) {
            $seo = max(0, $seo - (int) ($pen / 2));
        } elseif (in_array($type, ['canonical', 'http_status', 'sitemap_http'], true)) {
            $tech = max(0, $tech - $pen);
        } elseif (in_array($type, ['nap_live', 'schema'], true)) {
            $geo = max(0, $geo - $pen);
        }
    }

    $seo = (int) clamp_score($seo);
    $tech = (int) clamp_score($tech);
    $geo = (int) clamp_score($geo);
    $conv = (int) clamp_score($conv);
    $overall = (int) round(($seo + $tech + $geo + $conv) / 4);

    return compact('seo', 'tech', 'geo', 'conv', 'overall');
}

function clamp_score(int $v): int
{
    return $v < 0 ? 0 : ($v > 100 ? 100 : $v);
}

/**
 * @param array<string,mixed> $score
 * @param array<string,mixed> $target
 */
function cw_seo_mexico_audit_save_url_score(
    mysqli $conn,
    int $runId,
    array $target,
    array $score,
    int $httpStatus,
    int $findingsCount,
    array $meta = []
): void {
    $url = mb_substr((string) ($target['url'] ?? ''), 0, 500);
    $kind = mb_substr((string) ($target['kind'] ?? ''), 0, 40);
    $label = mb_substr((string) ($target['label'] ?? ''), 0, 160);
    $city = mb_substr((string) ($target['city'] ?? ''), 0, 120);
    $metaJson = $meta !== [] ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null;
    $stmt = $conn->prepare(
        'INSERT INTO cw_seo_mexico_audit_url_scores
         (run_id, url, kind, label, city, score_seo, score_tech, score_geo, score_conv, score_overall, http_status, findings_count, meta_json, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    if (!$stmt) {
        return;
    }
    $seo = (int) $score['seo'];
    $tech = (int) $score['tech'];
    $geo = (int) $score['geo'];
    $conv = (int) $score['conv'];
    $overall = (int) $score['overall'];
    $stmt->bind_param(
        'issssiiiiiiis',
        $runId,
        $url,
        $kind,
        $label,
        $city,
        $seo,
        $tech,
        $geo,
        $conv,
        $overall,
        $httpStatus,
        $findingsCount,
        $metaJson
    );
    $stmt->execute();
    $stmt->close();
}

/**
 * @param list<array{seo:int,tech:int,geo:int,conv:int,overall:int}> $scores
 */
function cw_seo_mexico_audit_save_site_health(
    mysqli $conn,
    int $runId,
    string $source,
    array $scores,
    int $findingsOpen,
    int $autoApplied
): array {
    $n = count($scores);
    if ($n === 0) {
        $health = [
            'score_avg' => 0, 'score_min' => 0,
            'score_seo_avg' => 0, 'score_tech_avg' => 0, 'score_geo_avg' => 0, 'score_conv_avg' => 0,
        ];
    } else {
        $sumO = $sumS = $sumT = $sumG = $sumC = 0;
        $min = 100;
        foreach ($scores as $s) {
            $sumO += (int) $s['overall'];
            $sumS += (int) $s['seo'];
            $sumT += (int) $s['tech'];
            $sumG += (int) $s['geo'];
            $sumC += (int) $s['conv'];
            $min = min($min, (int) $s['overall']);
        }
        $health = [
            'score_avg' => (int) round($sumO / $n),
            'score_min' => $min,
            'score_seo_avg' => (int) round($sumS / $n),
            'score_tech_avg' => (int) round($sumT / $n),
            'score_geo_avg' => (int) round($sumG / $n),
            'score_conv_avg' => (int) round($sumC / $n),
        ];
    }

    $src = preg_replace('/[^a-z0-9_\-]/', '', strtolower($source)) ?: 'manual';
    $stmt = $conn->prepare(
        'INSERT INTO cw_seo_mexico_audit_site_health
         (run_id, source, score_avg, score_min, score_seo_avg, score_tech_avg, score_geo_avg, score_conv_avg,
          urls_scanned, findings_open, auto_applied, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
            source = VALUES(source),
            score_avg = VALUES(score_avg),
            score_min = VALUES(score_min),
            score_seo_avg = VALUES(score_seo_avg),
            score_tech_avg = VALUES(score_tech_avg),
            score_geo_avg = VALUES(score_geo_avg),
            score_conv_avg = VALUES(score_conv_avg),
            urls_scanned = VALUES(urls_scanned),
            findings_open = VALUES(findings_open),
            auto_applied = VALUES(auto_applied),
            created_at = NOW()'
    );
    if ($stmt) {
        $stmt->bind_param(
            'isiiiiiiiii',
            $runId,
            $src,
            $health['score_avg'],
            $health['score_min'],
            $health['score_seo_avg'],
            $health['score_tech_avg'],
            $health['score_geo_avg'],
            $health['score_conv_avg'],
            $n,
            $findingsOpen,
            $autoApplied
        );
        $stmt->execute();
        $stmt->close();
    }

    return $health + ['urls_scanned' => $n, 'findings_open' => $findingsOpen, 'auto_applied' => $autoApplied, 'source' => $src];
}

/**
 * @return list<array<string,mixed>>
 */
function cw_seo_mexico_audit_health_history(mysqli $conn, int $limit = 14): array
{
    $limit = max(1, min(60, $limit));
    $rows = [];
    $res = $conn->query(
        "SELECT run_id, source, score_avg, score_min, score_seo_avg, score_tech_avg, score_geo_avg, score_conv_avg,
                urls_scanned, findings_open, auto_applied, created_at
         FROM cw_seo_mexico_audit_site_health
         ORDER BY id DESC LIMIT {$limit}"
    );
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        $res->free();
    }
    return array_reverse($rows);
}

/**
 * @return list<array<string,mixed>>
 */
function cw_seo_mexico_audit_worst_urls(mysqli $conn, ?int $runId = null, int $limit = 10): array
{
    $limit = max(1, min(50, $limit));
    if ($runId === null) {
        $last = cw_seo_mexico_audit_last_run($conn);
        $runId = $last ? (int) $last['id'] : 0;
    }
    if ($runId <= 0) {
        return [];
    }
    $rows = [];
    $res = $conn->query(
        'SELECT url, label, city, kind, score_overall, score_seo, score_tech, score_geo, score_conv, http_status, findings_count
         FROM cw_seo_mexico_audit_url_scores
         WHERE run_id = ' . (int) $runId . '
         ORDER BY score_overall ASC, findings_count DESC
         LIMIT ' . $limit
    );
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        $res->free();
    }
    return $rows;
}

/**
 * Ejecuta auditoría completa.
 *
 * @param array{time_budget?:int,source?:string} $opts
 * @return array<string,mixed>
 */
function cw_seo_mexico_audit_run(mysqli $conn, int $userId = 0, string $scope = 'mexico_priority', array $opts = []): array
{
    $uid = max(0, $userId);
    $source = (string) ($opts['source'] ?? 'manual');
    $isCli = PHP_SAPI === 'cli' || $source === 'cron';
    // cPanel cron CLI: presupuesto amplio; botón web: corto
    $budget = isset($opts['time_budget']) ? (int) $opts['time_budget'] : ($isCli ? 300 : 75);
    $budget = max(30, min(600, $budget));

    $stmt = $conn->prepare(
        'INSERT INTO cw_seo_mexico_audit_runs (status, scope, started_by, started_at)
         VALUES (\'running\', ?, ?, NOW())'
    );
    if (!$stmt) {
        return ['ok' => false, 'error' => 'No se pudo crear run'];
    }
    $stmt->bind_param('si', $scope, $uid);
    $stmt->execute();
    $runId = (int) $stmt->insert_id;
    $stmt->close();

    $targets = cw_seo_mexico_audit_target_urls();
    $nap = cw_seo_mexico_audit_nap_needles();
    $urlsOk = 0;
    $findingsOpen = 0;
    $tasksCreated = 0;
    $autoApplied = 0;
    $byType = [];
    $scoreList = [];

    $deadline = microtime(true) + $budget;
    $scanned = 0;

    foreach ($targets as $target) {
        if (microtime(true) > $deadline) {
            break;
        }
        $fetch = cw_seo_mexico_audit_fetch((string) $target['url']);
        $parsed = [];
        $kindT = (string) ($target['kind'] ?? '');
        if (!empty($fetch['body']) && !in_array($kindT, ['sitemap', 'robots'], true)) {
            $parsed = cw_seo_mexico_audit_parse_html((string) $fetch['body']);
        }
        if (!empty($fetch['ok'])) {
            $urlsOk++;
        }
        $deferFix = !empty($opts['defer_autofix']);
        $items = cw_seo_mexico_audit_evaluate($target, $fetch, $parsed, $nap);
        foreach ($items as $f) {
            $up = cw_seo_mexico_audit_upsert_finding($conn, $runId, $f, $uid, [
                'defer_autofix' => $deferFix,
            ]);
            if (!empty($up['ok'])) {
                $findingsOpen++;
                if (!empty($up['task_created'])) {
                    $tasksCreated++;
                }
                if (!empty($up['auto_applied'])) {
                    $autoApplied++;
                }
                $t = (string) ($f['check_type'] ?? 'other');
                $byType[$t] = ($byType[$t] ?? 0) + 1;
            }
        }

        $score = cw_seo_mexico_audit_score_url($fetch, $parsed, $items, $target);
        $scoreList[] = $score;
        cw_seo_mexico_audit_save_url_score(
            $conn,
            $runId,
            $target,
            $score,
            (int) ($fetch['status'] ?? 0),
            count($items),
            ['ms' => (int) ($fetch['ms'] ?? 0)]
        );

        $scanned++;
        usleep($isCli ? 50000 : 80000);
    }

    // Contraste multi-fuente (código ↔ vivo ↔ sitemap ↔ NAP ↔ redirects)
    $msDeep = $isCli || !empty($opts['multisource_deep']);
    $ms = ['findings' => 0, 'tasks_created' => 0, 'auto_applied' => 0, 'by_type' => []];
    if (microtime(true) < $deadline - 5) {
        $ms = cw_seo_mexico_multisource_apply_to_run($conn, $runId, $uid, [
            'deep' => $msDeep,
            'defer_autofix' => !empty($opts['defer_autofix']),
        ]);
        $findingsOpen += (int) ($ms['findings'] ?? 0);
        $tasksCreated += (int) ($ms['tasks_created'] ?? 0);
        $autoApplied += (int) ($ms['auto_applied'] ?? 0);
        foreach (($ms['by_type'] ?? []) as $t => $n) {
            $byType[$t] = ($byType[$t] ?? 0) + (int) $n;
        }
    }

    // Tareas y recomendaciones SEO/GEO externas (GSC, GBP, reseñas, citaciones…)
    $ext = ['findings' => 0, 'tasks_created' => 0, 'by_type' => []];
    if (microtime(true) < $deadline - 3) {
        require_once __DIR__ . '/cw_seo_mexico_external.php';
        $ext = cw_seo_mexico_external_apply_to_run($conn, $runId, $uid, [
            'with_ai' => !empty($opts['external_ai'])
                || ($isCli && empty($opts['skip_external_ai'])),
        ]);
        $findingsOpen += (int) ($ext['findings'] ?? 0);
        $tasksCreated += (int) ($ext['tasks_created'] ?? 0);
        foreach (($ext['by_type'] ?? []) as $t => $n) {
            $byType[$t] = ($byType[$t] ?? 0) + (int) $n;
        }
    }

    $closed = cw_seo_mexico_audit_reconcile_tasks($conn, $uid);
    $health = cw_seo_mexico_audit_save_site_health($conn, $runId, $source, $scoreList, $findingsOpen, $autoApplied);

    $summary = [
        'scanned' => $scanned,
        'targets' => count($targets),
        'by_type' => $byType,
        'tasks_closed' => $closed,
        'timed_out' => $scanned < count($targets),
        'source' => $source,
        'time_budget' => $budget,
        'health' => $health,
        'multisource' => $ms,
        'external_seo_geo' => $ext,
        'sources' => [
            'live_html', 'code', 'sitemap', 'robots', 'redirects', 'nap', 'overrides',
            'external_seo_geo',
        ],
    ];
    $summaryJson = json_encode($summary, JSON_UNESCAPED_UNICODE);
    $upd = $conn->prepare(
        'UPDATE cw_seo_mexico_audit_runs
         SET status = ?, urls_total = ?, urls_ok = ?, findings_open = ?, findings_fixed = ?,
             tasks_created = ?, auto_applied = ?, summary_json = ?, finished_at = NOW()
         WHERE id = ?'
    );
    $status = !empty($summary['timed_out']) ? 'partial' : 'done';
    $fixed = 0;
    if ($upd) {
        $upd->bind_param(
            'siiiiissi',
            $status,
            $scanned,
            $urlsOk,
            $findingsOpen,
            $fixed,
            $tasksCreated,
            $autoApplied,
            $summaryJson,
            $runId
        );
        $upd->execute();
        $upd->close();
    }

    cw_seo_mexico_checklist_log_update(
        $conn,
        'audit_live_engine',
        'Auditoría multi-fuente #' . $runId . ' (' . $status . ' · ' . $source . ')',
        "URLs: {$scanned}/" . count($targets) . " · OK: {$urlsOk} · Hallazgos: {$findingsOpen} · Multi-fuente: "
        . (int) ($ms['findings'] ?? 0) . ' · Externos SEO/GEO: ' . (int) ($ext['findings'] ?? 0)
        . " · Auto-fix: {$autoApplied} · Salud avg: " . (int) ($health['score_avg'] ?? 0),
        $uid
    );

    $engineDetail = json_encode([
        'source' => 'live_audit',
        'dynamic' => true,
        'user_description' => 'Motor autónomo de auditoría (cron cPanel + botón manual). Misión: estable, rápida, segura y líder en SEO.',
        'user_correction' => 'Configurar cron diario en cPanel. Revisar fase 6 y scores de salud.',
        'last_run_id' => $runId,
        'summary' => $summary,
        'plan' => [
            'can_start_now' => true,
            'start' => date('Y-m-d'),
            'due' => date('Y-m-d', strtotime('+7 days')),
            'effort' => 'bajo',
            'window' => 'diario (cron)',
            'note' => 'Corrida automática en cPanel productivo.',
        ],
    ], JSON_UNESCAPED_UNICODE);
    $engDone = $findingsOpen === 0 ? 1 : 0;
    $eng = $conn->prepare(
        'INSERT INTO cw_seo_mexico_checklist (task_key, phase, title, description, sort_order, done, detail_json, updated_at)
         VALUES (\'audit_live_engine\', \'06_auditoria_viva\', \'Motor de auditoría en vivo (México)\', ?, 890, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE description = VALUES(description), done = VALUES(done), detail_json = VALUES(detail_json), updated_at = NOW()'
    );
    if ($eng) {
        $engDesc = 'Última corrida #' . $runId . ' (' . $source . '): ' . $scanned . ' URLs, salud ' . (int) ($health['score_avg'] ?? 0) . '/100, ' . $findingsOpen . ' hallazgos.';
        $eng->bind_param('sis', $engDesc, $engDone, $engineDetail);
        $eng->execute();
        $eng->close();
    }

    return [
        'ok' => true,
        'run_id' => $runId,
        'status' => $status,
        'urls_total' => count($targets),
        'urls_scanned' => $scanned,
        'urls_ok' => $urlsOk,
        'findings_open' => $findingsOpen,
        'tasks_created' => $tasksCreated,
        'auto_applied' => $autoApplied,
        'tasks_closed' => $closed,
        'health' => $health,
        'summary' => $summary,
    ];
}

/**
 * @return array<string,mixed>|null
 */
function cw_seo_mexico_audit_last_run(mysqli $conn): ?array
{
    $res = $conn->query(
        'SELECT id, status, scope, urls_total, urls_ok, findings_open, findings_fixed, tasks_created, auto_applied, summary_json, started_at, finished_at
         FROM cw_seo_mexico_audit_runs ORDER BY id DESC LIMIT 1'
    );
    if (!$res || !($row = $res->fetch_assoc())) {
        return null;
    }
    $row['summary'] = null;
    if (!empty($row['summary_json'])) {
        $d = json_decode((string) $row['summary_json'], true);
        if (is_array($d)) {
            $row['summary'] = $d;
        }
    }
    return $row;
}

/**
 * @param 'all'|'code'|'external' $scope
 * @return list<array<string,mixed>>
 */
function cw_seo_mexico_audit_open_findings(mysqli $conn, int $limit = 40, string $scope = 'all'): array
{
    $limit = max(1, min(100, $limit));
    $scope = strtolower(trim($scope));
    $rows = [];
    $res = $conn->query(
        "SELECT id, finding_key, check_type, severity, url, title, evidence, correction, auto_fixable, auto_applied, task_key, meta_json, created_at, updated_at, status
         FROM cw_seo_mexico_audit_findings
         WHERE status = 'open'
         ORDER BY FIELD(severity,'critical','high','medium','low'), id DESC
         LIMIT 200"
    );
    if ($res) {
        require_once __DIR__ . '/cw_seo_mexico_external.php';
        while ($r = $res->fetch_assoc()) {
            $ct = (string) ($r['check_type'] ?? '');
            $meta = [];
            if (!empty($r['meta_json'])) {
                $decoded = json_decode((string) $r['meta_json'], true);
                if (is_array($decoded)) {
                    $meta = $decoded;
                }
            }
            $isExt = cw_seo_mexico_external_is_check($ct, ['execution' => $meta['execution'] ?? '', 'meta' => $meta]);
            if ($scope === 'external' && !$isExt) {
                continue;
            }
            if ($scope === 'code' && $isExt) {
                continue;
            }
            unset($r['meta_json']);
            $rows[] = $r;
            if (count($rows) >= $limit) {
                break;
            }
        }
        $res->free();
    }
    return $rows;
}

/**
 * Findings ya cerrados (fixed/resolved) para historial en DataTable.
 *
 * @return list<array<string,mixed>>
 */
function cw_seo_mexico_audit_closed_findings(mysqli $conn, int $limit = 40): array
{
    $limit = max(1, min(100, $limit));
    $rows = [];
    $res = $conn->query(
        "SELECT id, finding_key, check_type, severity, url, title, evidence, correction, auto_fixable, auto_applied, task_key, created_at, updated_at, status
         FROM cw_seo_mexico_audit_findings
         WHERE status IN ('fixed','resolved','acknowledged')
         ORDER BY updated_at DESC, id DESC
         LIMIT {$limit}"
    );
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        $res->free();
    }
    return $rows;
}
