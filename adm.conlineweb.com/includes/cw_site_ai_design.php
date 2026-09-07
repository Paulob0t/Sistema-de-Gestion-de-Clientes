<?php
/**
 * Propuestas de diseño UI con preview obligatorio (antes de implementar).
 * Usa el design system ConlineWeb (dark cyber/tech) y puede reinventar
 * solo si permanece alineado a tokens/patrones actuales.
 */
declare(strict_types=1);

require_once __DIR__ . '/cw_seo_mexico_ai.php';
require_once __DIR__ . '/cw_site_ai_brain.php';
require_once __DIR__ . '/cw_site_ai_maintain.php';
require_once __DIR__ . '/cw_seo_mexico_ai_constitution.php';

/**
 * Contexto completo del design system para la IA.
 */
function cw_site_ai_design_system_context(): string
{
    return <<<'TXT'
=== DESIGN SYSTEM CONLINEWEB (fuente de verdad) ===
Archivos: assets/css/base.css · assets/css/cw-hub.css · assets/css/footer.css
Look: dark cyber / tech. NO inventar tema claro genérico, cream/serif, ni purple-on-white ajeno.

COLORES (CSS vars)
- --deep-space: #0a0a0f → fondo principal body
- --stardust: rgba(255,255,255,0.9) → texto principal
- --cw-text-muted: rgba(255,255,255,0.72) → párrafos / secundario
- --cw-text-subtle: rgba(255,255,255,0.55) → meta / hints
- --neon-blue: #00f3ff → acento primario, CTAs, focus, glows
- --matrix-green: #00ff9d → acento éxito / highlights
- --electric-purple / --neon-purple: #b967ff → acento secundario
- --cyber-pink: #ff2a6d → acento alerta / énfasis
- --nav-showcase-border: rgba(0,243,255,0.45)
- --nav-showcase-border-hover: rgba(0,243,255,0.75)
- --nav-showcase-ring: rgba(255,255,255,0.06)
- --cw-focus-ring: 0 0 0 3px rgba(0,243,255,0.4)
- Gradientes permitidos: neon-blue ↔ electric-purple / matrix-green sutiles sobre deep-space

TIPOGRAFÍA
- Cuerpo / UI: 'Inter', sans-serif
- Display menú especial: 'Poiret One' (solo títulos menú/neon-title; no abusarlo)
- --cw-text-xs … --cw-text-lg → body/meta
- --cw-title-hero → H1 hero
- --cw-title-section → H2 de sección
- --cw-title-card → títulos de cards
- --cw-text-card → texto dentro de cards
- Párrafos: color var(--cw-text-muted), line-height ~1.55–1.7
- Títulos: color var(--stardust); acentos con neon-blue en spans/underline sutil

LAYOUT / SHELL
- --cw-shell-wide/max/hero: 1540px
- --cw-shell-pad-x: 5%
- Contenedor típico: max-width: var(--cw-shell-max); margin: 0 auto; padding: 0 var(--cw-shell-pad-x)
- Mobile-first; overflow-x hidden en body

ESPACIADO / RADIOS
- --cw-space-1…7 (0.25rem → 3rem)
- --cw-radius-sm 12px · --cw-radius 16px · --cw-radius-lg 20px · --cw-radius-pill 999px
- Transiciones: --transition-fast 0.3s ease · --transition-slow 0.5s cubic-bezier

BOTONES
- Alturas: --cw-btn-h-sm 44px · md 48px · lg 56px
- Font: --cw-btn-fs-sm 0.8rem · md 0.95rem; weight 600–700
- Primario: borde/glow neon-blue, fondo deep-space o gradiente sutil, hover brillo
- Clases de referencia: .free-web-btn, .service-zone-btn, .navbar-menu-btn, .hero-showcase-cta

CARDS / SUPERFICIES
- Fondo: rgba(255,255,255,0.03–0.06) o deep-space elevado
- Borde: 1px solid rgba(0,243,255,0.18–0.35) o nav-showcase-ring
- Radius: var(--cw-radius) o --cw-radius-lg
- Shadow suave oscura; hover: borde más neón + lift leve
- Título card: var(--cw-title-card); texto: var(--cw-text-card)

HERO
- H1: var(--cw-title-hero); subtítulo: var(--cw-text-lg) + muted
- Una composición clara: marca/servicio + 1 headline + 1 apoyo + CTA group
- Evitar clutter (stats strips, pills excesivas) en primer viewport

ICONOS
- Preferir clases existentes (Bootstrap Icons / FA ya usadas en el sitio)
- Color icono: neon-blue o stardust; tamaño coherente con botones

REGLAS DE PROPUESTA DE DISEÑO
1) SIEMPRE entregar preview_html (maqueta visual) ANTES de implementar.
2) Puede reinventar layout/composición solo si usa tokens y look vigentes (reinvent_aligned).
3) Prohibido hardcodear hex nuevos si existe variable; si hace falta, justificar en rationale.
4) preview_html = fragmento de página (section/main) con clases + style inline SOLO con var(--…).
5) patches opcionales [{path,search,replace}] para aplicar tras aprobación; search debe ser exacto.
6) Cada propuesta incluye URL de página afectada o URL nueva.
7) No tocar credenciales, pagos, tracking ni BD.
=== FIN DESIGN SYSTEM ===
TXT;
}

/**
 * Tokens resumidos (para after_json / cerebro).
 *
 * @return array<string,mixed>
 */
function cw_site_ai_design_tokens_pack(): array
{
    return [
        'colors' => [
            'deep-space' => '#0a0a0f',
            'stardust' => 'rgba(255,255,255,0.9)',
            'text-muted' => 'rgba(255,255,255,0.72)',
            'text-subtle' => 'rgba(255,255,255,0.55)',
            'neon-blue' => '#00f3ff',
            'matrix-green' => '#00ff9d',
            'electric-purple' => '#b967ff',
            'cyber-pink' => '#ff2a6d',
        ],
        'fonts' => [
            'body' => 'Inter, sans-serif',
            'display_menu' => 'Poiret One, sans-serif',
        ],
        'type_scale' => [
            'text-xs' => 'var(--cw-text-xs)',
            'text-sm' => 'var(--cw-text-sm)',
            'text-base' => 'var(--cw-text-base)',
            'text-lg' => 'var(--cw-text-lg)',
            'title-hero' => 'var(--cw-title-hero)',
            'title-section' => 'var(--cw-title-section)',
            'title-card' => 'var(--cw-title-card)',
            'text-card' => 'var(--cw-text-card)',
        ],
        'shell' => [
            'max' => '1540px',
            'pad-x' => '5%',
        ],
        'radius' => ['sm' => '12px', 'md' => '16px', 'lg' => '20px', 'pill' => '999px'],
        'css_files' => [
            'assets/css/base.css',
            'assets/css/cw-hub.css',
            'assets/css/footer.css',
        ],
    ];
}

/**
 * Envuelve el fragmento preview en HTML autónomo con design system.
 */
function cw_site_ai_design_wrap_preview_document(
    string $fragmentHtml,
    string $extraCss = '',
    string $title = 'Preview diseño ConlineWeb'
): string {
    $tokens = <<<'CSS'
:root{
  --deep-space:#0a0a0f;--neon-blue:#00f3ff;--matrix-green:#00ff9d;--stardust:rgba(255,255,255,.9);
  --electric-purple:#b967ff;--neon-purple:var(--electric-purple);--cyber-pink:#ff2a6d;
  --cw-text-muted:rgba(255,255,255,.72);--cw-text-subtle:rgba(255,255,255,.55);
  --transition-slow:.5s cubic-bezier(.25,.46,.45,.94);--transition-fast:.3s ease;
  --nav-showcase-border:rgba(0,243,255,.45);--nav-showcase-border-hover:rgba(0,243,255,.75);
  --nav-showcase-ring:rgba(255,255,255,.06);
  --cw-text-xs:clamp(.78rem,.85vw,.88rem);--cw-text-sm:clamp(.88rem,.95vw,1rem);
  --cw-text-base:clamp(1rem,1.05vw,1.12rem);--cw-text-lg:clamp(1.15rem,1.25vw,1.35rem);
  --cw-title-section:clamp(2rem,3.2vw,2.85rem);--cw-title-hero:clamp(2.4rem,4.5vw,3.75rem);
  --cw-title-card:clamp(1.05rem,1.4vw,1.25rem);--cw-text-card:clamp(.88rem,1.1vw,1rem);
  --cw-shell-wide:1540px;--cw-shell-max:1540px;--cw-shell-hero:1540px;--cw-shell-pad-x:5%;
  --cw-space-1:.25rem;--cw-space-2:.5rem;--cw-space-3:.75rem;--cw-space-4:1rem;
  --cw-space-5:1.5rem;--cw-space-6:2rem;--cw-space-7:3rem;
  --cw-radius-sm:12px;--cw-radius:16px;--cw-radius-lg:20px;--cw-radius-pill:999px;
  --cw-btn-h-sm:44px;--cw-btn-h-md:48px;--cw-btn-h-lg:56px;
  --cw-btn-fs-sm:.8rem;--cw-btn-fs-md:.95rem;
  --cw-focus-ring:0 0 0 3px rgba(0,243,255,.4);--blur-amount:10px;
}
*{box-sizing:border-box}
body{margin:0;font-family:Inter,sans-serif;background:var(--deep-space);color:var(--stardust);min-height:100vh}
.cw-design-preview-banner{position:sticky;top:0;z-index:50;padding:.55rem 1rem;background:#111827;border-bottom:1px solid rgba(0,243,255,.35);font:600 .78rem/1.3 Inter,sans-serif;color:#e2e8f0}
.cw-design-preview-banner strong{color:var(--neon-blue)}
.cw-design-preview-shell{max-width:var(--cw-shell-max);margin:0 auto;padding:var(--cw-space-6) var(--cw-shell-pad-x)}
.cw-preview-card{background:rgba(255,255,255,.04);border:1px solid rgba(0,243,255,.22);border-radius:var(--cw-radius);padding:var(--cw-space-5)}
.cw-preview-btn{display:inline-flex;align-items:center;justify-content:center;min-height:var(--cw-btn-h-md);padding:0 1.25rem;border-radius:var(--cw-radius-pill);border:1px solid var(--nav-showcase-border);background:rgba(0,243,255,.08);color:var(--stardust);font-size:var(--cw-btn-fs-md);font-weight:700;text-decoration:none;transition:var(--transition-fast)}
.cw-preview-btn:hover{border-color:var(--nav-showcase-border-hover);box-shadow:0 0 18px rgba(0,243,255,.25)}
h1{font-size:var(--cw-title-hero);line-height:1.15;margin:0 0 var(--cw-space-4)}
h2{font-size:var(--cw-title-section);line-height:1.2;margin:0 0 var(--cw-space-3)}
h3{font-size:var(--cw-title-card);margin:0 0 var(--cw-space-2)}
p{font-size:var(--cw-text-base);color:var(--cw-text-muted);line-height:1.65;margin:0 0 var(--cw-space-3)}
CSS;
    $extraCss = trim($extraCss);
    $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    return '<!DOCTYPE html><html lang="es-MX"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . $titleEsc . '</title>'
        . '<link rel="preconnect" href="https://fonts.googleapis.com">'
        . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
        . '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poiret+One&display=swap" rel="stylesheet">'
        . '<link rel="stylesheet" href="https://conlineweb.com/assets/css/base.css">'
        . '<style>' . $tokens . "\n" . $extraCss . '</style>'
        . '</head><body>'
        . '<div class="cw-design-preview-banner">PREVIEW de diseño · <strong>no está en producción</strong> · usa design system ConlineWeb</div>'
        . '<div class="cw-design-preview-shell">' . $fragmentHtml . '</div>'
        . '</body></html>';
}

/**
 * Extrae el <body> (o un tramo útil) para que los parches apunten a UI, no solo al <head>.
 */
function cw_site_ai_design_extract_body_preview(string $html, int $max = 11000): string
{
    if (preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $m)) {
        return mb_substr(trim($m[1]), 0, $max);
    }
    return mb_substr($html, 0, $max);
}

/**
 * ¿El parche solo toca title/meta/keywords (SEO head) sin cambiar UI?
 */
function cw_site_ai_design_patch_is_meta_only(array $patch): bool
{
    $path = strtolower((string) ($patch['path'] ?? ''));
    $blob = (string) ($patch['search'] ?? '') . "\n" . (string) ($patch['replace'] ?? '');
    if ($path !== '' && str_ends_with($path, '.css')) {
        return false;
    }
    $touchesMeta = (bool) preg_match('/<\s*(title|meta)\b/i', $blob);
    $touchesUi = (bool) preg_match(
        '/<\s*(section|div|h1|h2|h3|header|main|footer|nav|a\b|button|p\b|ul\b|li\b)|class\s*=|hero-|feature|cta-|badge|top-content|container--/i',
        $blob
    );
    return $touchesMeta && !$touchesUi;
}

/**
 * ¿El parche cambia estructura/visual (body HTML o CSS)?
 */
function cw_site_ai_design_patch_is_visual(array $patch): bool
{
    $path = strtolower((string) ($patch['path'] ?? ''));
    if ($path !== '' && str_ends_with($path, '.css')) {
        return true;
    }
    return !cw_site_ai_design_patch_is_meta_only($patch)
        && (bool) preg_match(
            '/<\s*(section|div|h1|h2|h3|header|main|footer|a\b|button)|class\s*=|hero-|feature|cta-|\{[^}]{6,}\}/i',
            (string) ($patch['search'] ?? '') . "\n" . (string) ($patch['replace'] ?? '')
        );
}

/**
 * Propone diseño con preview obligatorio.
 *
 * @return array{ok:bool,proposal_id?:int,after?:array,error?:string,message?:string}
 */
function cw_site_ai_design_propose(
    mysqli $conn,
    string $instruction,
    string $targetUrl = '',
    string $relPath = '',
    int $userId = 0
): array {
    $instruction = trim($instruction);
    if (mb_strlen($instruction) < 12) {
        return ['ok' => false, 'error' => 'Describe el cambio de diseño con más detalle (mín. 12 caracteres)'];
    }
    if (!cw_seo_mexico_ai_available()) {
        return ['ok' => false, 'error' => 'IA no disponible'];
    }

    $brain = cw_site_ai_brain_context($conn, 16);
    $designCtx = cw_site_ai_design_system_context();
    $relPath = str_replace('\\', '/', ltrim(trim($relPath), '/'));
    // Heurística: si la instrucción nombra una landing conocida, fijar path
    if ($relPath === '') {
        $low = mb_strtolower($instruction);
        $landingHints = [
            'desarrollo-de-software' => 'desarrollo-de-software.php',
            'software para empresas' => 'software-para-empresas.php',
            'software-para-empresas' => 'software-para-empresas.php',
            'paginas web' => 'paginas-web.php',
            'páginas web' => 'paginas-web.php',
            'tienda online' => 'tienda-online.php',
            'diseño de paginas' => 'diseno-de-paginas-web.php',
            'diseno de paginas' => 'diseno-de-paginas-web.php',
        ];
        foreach ($landingHints as $needle => $file) {
            if (str_contains($low, $needle)) {
                $relPath = $file;
                break;
            }
        }
    }
    $resolvedPath = $relPath !== '' ? cw_site_ai_maintain_resolve_path($relPath) : null;
    if ($resolvedPath !== null) {
        $relPath = $resolvedPath;
    }
    $filePreview = '';
    $bodyPreview = '';
    $cssPath = '';
    $cssPreview = '';
    $root = cw_seo_mexico_autofix_site_root();
    if ($relPath !== '' && $root && cw_site_ai_maintain_path_allowed($relPath)) {
        $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
        if (is_readable($abs) && is_file($abs)) {
            $rawFile = (string) file_get_contents($abs);
            $filePreview = mb_substr($rawFile, 0, 4000);
            $bodyPreview = cw_site_ai_design_extract_body_preview($rawFile, 11000);
            $baseName = preg_replace('/\.php$/i', '', basename($relPath)) ?? '';
            if ($baseName !== '') {
                $cssCand = 'assets/css/pages/' . $baseName . '.css';
                if (cw_site_ai_maintain_path_allowed($cssCand)) {
                    $cssAbs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $cssCand);
                    if (is_readable($cssAbs)) {
                        $cssPath = $cssCand;
                        $cssPreview = mb_substr((string) file_get_contents($cssAbs), 0, 6000);
                    }
                }
            }
        }
    }
    // Referencia de estructura: software-para-empresas.php (body)
    $refPreview = '';
    $lowInstr = mb_strtolower($instruction);
    $wantsVisual = (bool) preg_match(
        '/diseñ|disen|rediseñ|redisen|layout|hero|estructura|similar|visual|ui|ux|aline/i',
        $lowInstr
    );
    if ($root && (str_contains($lowInstr, 'software para empresas')
        || str_contains($lowInstr, 'software-para-empresas')
        || $wantsVisual)) {
        $ref = cw_site_ai_maintain_resolve_path('software-para-empresas.php');
        if ($ref) {
            $refAbs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $ref);
            if (is_readable($refAbs)) {
                $refPreview = cw_site_ai_design_extract_body_preview((string) file_get_contents($refAbs), 9000);
            }
        }
    }

    require_once __DIR__ . '/cw_seo_mexico_ai_constitution.php';
    $system = 'Eres el diseñador UI de ConlineWeb (comité senior: diseño + PM + dev) y MASTER SEO/GEO.\n'
        . cw_seo_mexico_ai_master_brief() . "\n"
        . mb_substr(cw_seo_mexico_ai_domain_mastery_brief(), 0, 900) . "\n"
        . mb_substr(cw_seo_mexico_ai_committee_brief(), 0, 800) . "\n"
        . mb_substr(cw_seo_mexico_ai_seo_geo_playbook(), 0, 1600) . "\n"
        . "{$brain}\n{$designCtx}\n"
        . "Diseño al servicio del SEO/GEO: H1 con intención, hero claro, CTAs, jerarquía scaneable, "
        . "copy México/PyME. rationale DEBE citar keyword/intención o señal GEO.\n"
        . "Debes proponer un diseño VIABLE con PREVIEW obligatorio y PARCHES QUE CAMBIEN LA UI.\n"
        . "IMPORTANTE rutas: landings = ARCHIVOS .php (desarrollo-de-software.php), NO carpetas. "
        . "CSS de página suele estar en assets/css/pages/<nombre>.css.\n"
        . "PROHIBIDO entregar solo cambios de <title>/<meta description>/<meta keywords>. "
        . "Eso NO es un rediseño: el sitio se ve igual. "
        . "Si la petición es de diseño/estructura/similar a otra página, "
        . "AL MENOS 1 parche DEBE modificar HTML del <body> (hero, section, h1, features, CTA) "
        . "y/o el CSS de la página.\n"
        . "Responde SOLO JSON con keys:\n"
        . "target_url, scope (section|page), reinvention_level (preserve|evolve|reinvent_aligned),\n"
        . "summary, rationale, preview_html, preview_css, patches, tokens_used.\n"
        . "preview_html: maqueta visual (section/main) con var(--…) del design system.\n"
        . "patches: 1–4 items {path,search,replace}. search EXACTO del código actual. "
        . "Prioriza body/hero/features/CTA; meta SEO solo como complemento opcional.\n"
        . "reinvent_aligned solo si el look sigue dark cyber/tech + tokens.\n"
        . 'Sin emojis. Español México.';

    $user = "Instrucción de diseño:\n{$instruction}\n";
    if ($targetUrl !== '') {
        $user .= "URL objetivo: {$targetUrl}\n";
    }
    if ($relPath !== '') {
        $user .= "Archivo PHP a editar: {$relPath}\n";
    }
    if ($cssPath !== '') {
        $user .= "CSS de página disponible: {$cssPath}\n";
    }
    if ($bodyPreview !== '') {
        $user .= "BODY actual (prioridad para parches visuales) — copia search EXACTO de aquí:\n```\n{$bodyPreview}\n```\n";
    } elseif ($filePreview !== '') {
        $user .= "Código actual (recortado):\n```\n{$filePreview}\n```\n";
    } else {
        $user .= "AVISO: no se pudo leer el archivo objetivo.\n";
    }
    if ($cssPreview !== '') {
        $user .= "CSS actual ({$cssPath}) opcional para parches visuales:\n```\n{$cssPreview}\n```\n";
    }
    if ($refPreview !== '') {
        $user .= "Referencia BODY (software-para-empresas.php) para alinear estructura:\n```\n{$refPreview}\n```\n";
    }
    $user .= "OBLIGATORIO: al menos un parche de hero/section/features/CTA o CSS. No solo meta tags.\n";
    $user .= "Entrega preview_html + patches visuales aplicables.\n";

    // Timeout más alto: HTML de preview
    $chat = cw_seo_mexico_ai_chat([
        ['role' => 'system', 'content' => $system],
        ['role' => 'user', 'content' => $user],
    ], 0.45);
    if (empty($chat['ok'])) {
        return ['ok' => false, 'error' => (string) ($chat['error'] ?? 'IA falló')];
    }
    $parsed = json_decode((string) ($chat['content'] ?? ''), true);
    if (!is_array($parsed)) {
        return ['ok' => false, 'error' => 'JSON de diseño inválido', 'raw' => $chat['content'] ?? ''];
    }

    $previewHtml = trim((string) ($parsed['preview_html'] ?? ''));
    if ($previewHtml === '' || mb_strlen(strip_tags($previewHtml)) < 40) {
        return ['ok' => false, 'error' => 'La propuesta de diseño debe incluir preview_html usable (≥ 40 caracteres de texto)'];
    }
    // Sanidad básica: no scripts
    if (preg_match('/<script\b/i', $previewHtml) || preg_match('/\bon\w+\s*=/i', $previewHtml)) {
        return ['ok' => false, 'error' => 'preview_html no puede incluir scripts ni handlers JS'];
    }

    $previewCss = trim((string) ($parsed['preview_css'] ?? ''));
    if (preg_match('/@import|expression\s*\(|javascript:/i', $previewCss)) {
        $previewCss = '';
    }

    $level = preg_replace('/[^a-z_]/', '', strtolower((string) ($parsed['reinvention_level'] ?? 'evolve'))) ?: 'evolve';
    if (!in_array($level, ['preserve', 'evolve', 'reinvent_aligned'], true)) {
        $level = 'evolve';
    }
    $scope = preg_replace('/[^a-z]/', '', strtolower((string) ($parsed['scope'] ?? 'section'))) ?: 'section';
    if (!in_array($scope, ['section', 'page'], true)) {
        $scope = 'section';
    }

    $patches = [];
    $patchErrors = [];
    if (is_array($parsed['patches'] ?? null)) {
        foreach ($parsed['patches'] as $p) {
            if (!is_array($p)) {
                continue;
            }
            $path = str_replace('\\', '/', ltrim((string) ($p['path'] ?? $relPath), '/'));
            $search = (string) ($p['search'] ?? '');
            $replace = (string) ($p['replace'] ?? '');
            if ($path === '' || $search === '') {
                $patchErrors[] = 'Parche incompleto (path/search vacíos)';
                continue;
            }
            $resolved = cw_site_ai_maintain_resolve_path($path);
            if ($resolved === null && $relPath !== '') {
                $resolved = cw_site_ai_maintain_resolve_path($relPath);
            }
            if ($resolved === null) {
                $patchErrors[] = 'Archivo inexistente o no permitido: ' . $path
                    . ' (en este sitio las landings son .php, ej. desarrollo-de-software.php)';
                continue;
            }
            if (!cw_site_ai_maintain_path_allowed($resolved)) {
                $patchErrors[] = 'Ruta fuera de whitelist: ' . $resolved;
                continue;
            }
            $absPatch = $root
                ? $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $resolved)
                : null;
            if ($absPatch === null || !is_readable($absPatch)) {
                $patchErrors[] = 'No se puede leer: ' . $resolved;
                continue;
            }
            $srcPatch = (string) file_get_contents($absPatch);
            if (!str_contains($srcPatch, $search)) {
                $patchErrors[] = 'search no existe en ' . $resolved . ' (debe ser fragmento exacto del archivo)';
                continue;
            }
            if ($search === $replace) {
                $patchErrors[] = 'search y replace son idénticos en ' . $resolved;
                continue;
            }
            $patches[] = [
                'path' => $resolved,
                'search' => $search,
                'replace' => $replace,
            ];
            if (count($patches) >= 5) {
                break;
            }
        }
    }

    // Si pedían actualizar una página real y no quedó ningún parche válido → error claro
    if ($patches === [] && ($bodyPreview !== '' || $filePreview !== '')) {
        $detail = $patchErrors !== []
            ? implode(' · ', array_slice($patchErrors, 0, 4))
            : 'La IA no devolvió parches aplicables.';
        return [
            'ok' => false,
            'error' => 'No se pudo crear una propuesta ejecutable para «'
                . ($relPath !== '' ? $relPath : 'la página') . '». ' . $detail
                . ' Vuelve a proponer indicando el archivo .php real.',
            'patch_errors' => $patchErrors,
        ];
    }

    // Rechazar “diseño” que solo cambia title/meta (la página se ve igual)
    $visualPatches = array_values(array_filter($patches, static fn ($p) => cw_site_ai_design_patch_is_visual($p)));
    $metaOnlyCount = count(array_filter($patches, static fn ($p) => cw_site_ai_design_patch_is_meta_only($p)));
    $needsVisual = $wantsVisual || in_array($level, ['evolve', 'reinvent_aligned'], true) || $scope === 'page';
    if ($needsVisual && $patches !== [] && $visualPatches === []) {
        return [
            'ok' => false,
            'error' => 'La IA solo propuso cambios de title/meta (' . $metaOnlyCount
                . ' parches SEO). Eso no actualiza el diseño visual. '
                . 'Vuelve a generar pidiendo cambios en hero/section/features/CTA o en '
                . ($cssPath !== '' ? $cssPath : 'el CSS de la página') . '.',
            'patch_errors' => array_merge($patchErrors, ['solo_meta_seo']),
        ];
    }
    // Si hay mezcla, priorizar visuales + máximo 1 meta opcional
    if ($visualPatches !== [] && count($patches) > count($visualPatches)) {
        $kept = $visualPatches;
        foreach ($patches as $p) {
            if (cw_site_ai_design_patch_is_meta_only($p) && count($kept) < 4) {
                $kept[] = $p;
                break;
            }
        }
        $patches = $kept;
    }

    $pageUrl = trim((string) ($parsed['target_url'] ?? $targetUrl));
    if ($pageUrl === '' && $relPath !== '') {
        $slugUrl = preg_replace('/\.php$/i', '', $relPath) ?? $relPath;
        $pageUrl = 'https://conlineweb.com/' . ltrim($slugUrl, '/') . '/';
        // Preferir URL sin slash final si el sitio sirve el .php directo
        if (!is_dir(($root ?? '') . DIRECTORY_SEPARATOR . $slugUrl)) {
            $pageUrl = 'https://conlineweb.com/' . ltrim($relPath, '/');
        }
    }
    if ($pageUrl === '') {
        $pageUrl = 'https://conlineweb.com/';
    }

    $summary = trim((string) ($parsed['summary'] ?? $instruction));
    $rationale = trim((string) ($parsed['rationale'] ?? ''));
    if (mb_strlen($rationale) < 20) {
        $rationale = 'Propuesta de diseño alineada al design system ConlineWeb (dark cyber/tech) '
            . 'con preview obligatorio para evaluar viabilidad antes de implementar. '
            . 'Nivel: ' . $level . '.';
    }

    $doc = cw_site_ai_design_wrap_preview_document(
        $previewHtml,
        $previewCss,
        'Preview · ' . mb_substr($summary, 0, 80)
    );

    $after = [
        'mode' => 'design_ui',
        'scope' => $scope,
        'reinvention_level' => $level,
        'summary' => $summary,
        'rationale' => $rationale,
        'preview_html' => $previewHtml,
        'preview_css' => $previewCss,
        'preview_document' => $doc,
        'patches' => $patches,
        'tokens_used' => is_array($parsed['tokens_used'] ?? null)
            ? $parsed['tokens_used']
            : array_keys(cw_site_ai_design_tokens_pack()['colors']),
        'design_tokens' => cw_site_ai_design_tokens_pack(),
        'path' => $relPath !== '' ? $relPath : (string) (($patches[0]['path'] ?? '')),
        'url' => $pageUrl,
        'url_kind' => 'page',
        'url_label' => 'URL de página (preview de diseño)',
        'change_type' => 'design',
        'requires_design_preview' => true,
    ];

    $before = [
        'instruction' => $instruction,
        'path' => $relPath,
        'note' => 'Estado actual del sitio; el preview muestra la propuesta visual nueva sin aplicarla.',
        'design_system' => 'assets/css/base.css',
    ];

    $targetKey = $relPath !== '' ? $relPath : ('design:' . substr(sha1($summary . $pageUrl), 0, 12));
    $saved = cw_seo_mexico_ai_save_proposal(
        $conn,
        'design_ui',
        'Diseño · ' . mb_substr($summary, 0, 80),
        $targetKey,
        $pageUrl,
        'Diseño con preview · ' . mb_substr($instruction, 0, 140),
        $before,
        $after,
        $userId
    );
    if (empty($saved['ok'])) {
        return $saved;
    }

    return [
        'ok' => true,
        'proposal_id' => (int) $saved['id'],
        'after' => $after,
        'url' => $pageUrl,
        'content_type' => 'design',
        'message' => 'Propuesta de diseño lista con preview. Analízala en Monitor antes de implementar.',
    ];
}

/**
 * Aplica parches de una propuesta design_ui (tras aprobar preview).
 *
 * @param array<string,mixed> $after
 * @return array{ok:bool,applied:bool,files?:list<string>,error?:string,patches_applied?:int}
 */
function cw_site_ai_design_apply(array $after, string $reason = ''): array
{
    $patches = is_array($after['patches'] ?? null) ? $after['patches'] : [];
    if ($patches === []) {
        // Dirección visual sin archivos: se puede acusar, pero no cambia el sitio
        return [
            'ok' => true,
            'applied' => true,
            'files' => [],
            'patches_applied' => 0,
            'message' => 'Diseño aprobado sin parches de archivo (solo dirección visual). '
                . 'El sitio NO cambió. Si querías editar desarrollo-de-software.php, genera otra propuesta con parches válidos.',
        ];
    }

    $files = [];
    $n = 0;
    $results = [];
    foreach ($patches as $idx => $p) {
        if (!is_array($p)) {
            continue;
        }
        $pathIn = (string) ($p['path'] ?? '');
        $resolved = cw_site_ai_maintain_resolve_path($pathIn);
        if ($resolved !== null) {
            $p['path'] = $resolved;
        }
        $res = cw_site_ai_maintain_apply_patch($p, $reason !== '' ? $reason : 'design_ui');
        $results[] = [
            'index' => (int) $idx,
            'path' => (string) ($p['path'] ?? $pathIn),
            'ok' => !empty($res['ok']) && !empty($res['applied']),
            'error' => (string) ($res['error'] ?? ''),
        ];
        if (empty($res['ok']) || empty($res['applied'])) {
            $hint = (string) ($res['error'] ?? 'error');
            if ($pathIn !== '' && !str_contains($pathIn, '.php') && str_contains($pathIn, '/')) {
                $hint .= ' · En ConlineWeb la landing es archivo .php, no carpeta.';
            }
            return [
                'ok' => false,
                'applied' => false,
                'error' => 'Falló parche #' . ((int) $idx + 1) . ' en «' . $pathIn . '»: ' . $hint,
                'files' => $files,
                'patches_applied' => $n,
                'patch_results' => $results,
            ];
        }
        $files[] = (string) ($res['path'] ?? $p['path'] ?? '');
        $n++;
    }

    return [
        'ok' => true,
        'applied' => true,
        'files' => $files,
        'patches_applied' => $n,
        'patch_results' => $results,
    ];
}
