<?php
/**
 * Campos E-E-A-T de proyectos (industria, ciudad, caso, resultado).
 * Migración lazy + sugerencias ligeras desde URL.
 */

if (!function_exists('adm_proyectos_eeat_columns')) {
    /**
     * @return list<array{name:string,ddl:string}>
     */
    function adm_proyectos_eeat_columns(): array
    {
        return [
            [
                'name' => 'industria',
                'ddl' => "ADD COLUMN industria VARCHAR(120) NULL DEFAULT NULL COMMENT 'Industria o vertical del caso'",
            ],
            [
                'name' => 'ciudad',
                'ddl' => "ADD COLUMN ciudad VARCHAR(120) NULL DEFAULT NULL COMMENT 'Ciudad o region (opcional)'",
            ],
            [
                'name' => 'alias_publico',
                'ddl' => "ADD COLUMN alias_publico VARCHAR(150) NULL DEFAULT NULL COMMENT 'Nombre comercial o alias anonimizado'",
            ],
            [
                'name' => 'problema',
                'ddl' => "ADD COLUMN problema TEXT NULL COMMENT 'Situacion inicial / problema'",
            ],
            [
                'name' => 'solucion',
                'ddl' => "ADD COLUMN solucion TEXT NULL COMMENT 'Solucion entregada'",
            ],
            [
                'name' => 'resultado',
                'ddl' => "ADD COLUMN resultado TEXT NULL COMMENT 'Resultado / logro del proyecto'",
            ],
            [
                'name' => 'caso_destacado',
                'ddl' => "ADD COLUMN caso_destacado TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=mostrar bloque caso en portafolio'",
            ],
        ];
    }
}

if (!function_exists('adm_proyectos_ensure_eeat_columns')) {
    function adm_proyectos_ensure_eeat_columns(mysqli $conn): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            foreach (adm_proyectos_eeat_columns() as $col) {
                $name = $col['name'];
                $chk = $conn->query("SHOW COLUMNS FROM proyectos LIKE '" . $conn->real_escape_string($name) . "'");
                if ($chk && $chk->num_rows > 0) {
                    continue;
                }
                if (!$conn->query('ALTER TABLE proyectos ' . $col['ddl'])) {
                    $ready = false;
                    return false;
                }
            }
            $ready = true;
            return true;
        } catch (Throwable $e) {
            $ready = false;
            return false;
        }
    }
}

if (!function_exists('adm_proyectos_eeat_has_column')) {
    function adm_proyectos_eeat_has_column(mysqli $conn, string $name): bool
    {
        static $cache = [];
        if (array_key_exists($name, $cache)) {
            return $cache[$name];
        }
        try {
            $chk = $conn->query("SHOW COLUMNS FROM proyectos LIKE '" . $conn->real_escape_string($name) . "'");
            $cache[$name] = ($chk && $chk->num_rows > 0);
        } catch (Throwable $e) {
            $cache[$name] = false;
        }
        return $cache[$name];
    }
}

if (!function_exists('adm_proyecto_eeat_from_post')) {
    /**
     * @return array{
     *   industria:?string,
     *   ciudad:?string,
     *   alias_publico:?string,
     *   problema:?string,
     *   solucion:?string,
     *   resultado:?string,
     *   caso_destacado:int
     * }
     */
    function adm_proyecto_eeat_from_post(array $src): array
    {
        $trimOrNull = static function ($v, int $max = 0): ?string {
            $s = trim((string) ($v ?? ''));
            if ($s === '') {
                return null;
            }
            if ($max > 0 && mb_strlen($s) > $max) {
                $s = mb_substr($s, 0, $max);
            }
            return $s;
        };

        return [
            'industria' => $trimOrNull($src['industria'] ?? null, 120),
            'ciudad' => $trimOrNull($src['ciudad'] ?? null, 120),
            'alias_publico' => $trimOrNull($src['alias_publico'] ?? null, 150),
            'problema' => $trimOrNull($src['problema'] ?? null),
            'solucion' => $trimOrNull($src['solucion'] ?? null),
            'resultado' => $trimOrNull($src['resultado'] ?? null),
            'caso_destacado' => !empty($src['caso_destacado']) ? 1 : 0,
        ];
    }
}

if (!function_exists('adm_proyecto_sugerir_eeat_desde_url')) {
    /**
     * Sugerencias conservadoras desde URL + meta si es alcanzable.
     * No inventa resultados; solo pistas editables.
     *
     * @return array<string,mixed>
     */
    function adm_proyecto_sugerir_eeat_desde_url(string $url, int $tipoProyecto = 0): array
    {
        $out = [
            'ok' => false,
            'url' => $url,
            'host' => '',
            'sugerencias' => [
                'alias_publico' => null,
                'industria' => null,
                'ciudad' => null,
                'problema' => null,
                'solucion' => null,
                'resultado' => null,
                'notas' => [],
            ],
        ];

        $url = trim($url);
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            $out['sugerencias']['notas'][] = 'URL inválida o vacía.';
            return $out;
        }

        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $host = preg_replace('/^www\./', '', $host) ?: '';
        $out['host'] = $host;
        $path = strtolower((string) ($parts['path'] ?? ''));
        $haystack = $host . ' ' . $path;

        $alias = $host !== '' ? preg_replace('/\.(com|mx|cl|net|org|io|app|co)(\.[a-z]{2})?$/i', '', $host) : '';
        $alias = str_replace(['-', '_'], ' ', (string) $alias);
        $alias = trim(preg_replace('/\s+/', ' ', $alias) ?: '');
        if ($alias !== '') {
            $out['sugerencias']['alias_publico'] = mb_convert_case($alias, MB_CASE_TITLE, 'UTF-8');
        }

        $industriaMap = [
            'clinic' => 'Salud / clínica',
            'dental' => 'Salud / clínica dental',
            'medico' => 'Salud',
            'salud' => 'Salud',
            'hotel' => 'Turismo / hotel',
            'travel' => 'Turismo',
            'turismo' => 'Turismo',
            'school' => 'Educación',
            'edu' => 'Educación',
            'universidad' => 'Educación',
            'shop' => 'Retail / ecommerce',
            'store' => 'Retail / ecommerce',
            'tienda' => 'Retail / ecommerce',
            'market' => 'Retail / ecommerce',
            'restaurant' => 'Alimentos y bebidas',
            'cafe' => 'Alimentos y bebidas',
            'law' => 'Servicios profesionales',
            'abogad' => 'Servicios profesionales',
            'inmobil' => 'Bienes raíces',
            'realestate' => 'Bienes raíces',
            'construc' => 'Construcción',
            'gym' => 'Fitness',
            'fitness' => 'Fitness',
            'auto' => 'Automotriz',
            'taller' => 'Automotriz',
        ];
        foreach ($industriaMap as $needle => $label) {
            if (strpos($haystack, $needle) !== false) {
                $out['sugerencias']['industria'] = $label;
                break;
            }
        }

        $ciudadMap = [
            'leon' => 'León, Gto.',
            'gdl' => 'Guadalajara',
            'guadalajara' => 'Guadalajara',
            'cdmx' => 'CDMX',
            'mexico' => 'CDMX',
            'mty' => 'Monterrey',
            'monterrey' => 'Monterrey',
            'puebla' => 'Puebla',
            'queretaro' => 'Querétaro',
            'qro' => 'Querétaro',
            'cancun' => 'Cancún',
            'tijuana' => 'Tijuana',
        ];
        foreach ($ciudadMap as $needle => $label) {
            if (strpos($haystack, $needle) !== false) {
                $out['sugerencias']['ciudad'] = $label;
                break;
            }
        }

        $tipoSolucion = [
            0 => 'Sitio web corporativo orientado a captación y confianza.',
            1 => 'Software / sistema a medida para ordenar la operación.',
            2 => 'Estrategia digital (web + canales) para atraer y convertir prospectos.',
            3 => 'Solución digital a medida según el alcance del proyecto.',
        ];
        $out['sugerencias']['solucion'] = $tipoSolucion[$tipoProyecto] ?? $tipoSolucion[0];
        $out['sugerencias']['problema'] = 'El negocio necesitaba una presencia digital clara y un canal ordenado para atender prospectos u operación.';
        $out['sugerencias']['resultado'] = null;
        $out['sugerencias']['notas'][] = 'El resultado no se infiere de la URL: complétalo manualmente.';

        $metaTitle = '';
        $metaDesc = '';
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 4,
                'follow_location' => 1,
                'max_redirects' => 3,
                'user_agent' => 'ConlineWebAdminEEAT/1.0',
                'header' => "Accept: text/html\r\n",
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);
        $html = @file_get_contents($url, false, $ctx);
        if (is_string($html) && $html !== '') {
            if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
                $metaTitle = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
            if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
                $metaDesc = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            } elseif (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']description["\']/i', $html, $m)) {
                $metaDesc = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
            if ($metaTitle !== '' && empty($out['sugerencias']['alias_publico'])) {
                $short = preg_replace('/\s*[\|\-–—].*$/u', '', $metaTitle);
                $short = trim((string) $short);
                if ($short !== '') {
                    $out['sugerencias']['alias_publico'] = mb_substr($short, 0, 150);
                }
            }
            if ($metaDesc !== '') {
                $out['sugerencias']['notas'][] = 'Meta description detectada (referencia): ' . mb_substr($metaDesc, 0, 180);
            }
            $out['sugerencias']['notas'][] = 'Se leyó título/meta de la URL cuando fue posible.';
        } else {
            $out['sugerencias']['notas'][] = 'No se pudo leer la página; sugerencias solo por dominio/ruta y tipo.';
        }

        $out['ok'] = true;
        return $out;
    }
}
