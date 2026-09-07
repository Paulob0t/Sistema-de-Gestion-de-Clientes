<?php
/**
 * Plantillas de mensajes / comunicados para clientes (WhatsApp, etc.).
 * Almacenamiento en JSON local — no toca cPanel ni esquema de BD.
 * Textos sin emojis: WhatsApp Desktop (PC) suele romperlos vía wa.me.
 */
declare(strict_types=1);

if (!function_exists('cw_client_messages_storage_path')) {
    function cw_client_messages_storage_path(): string
    {
        $dir = dirname(__DIR__) . '/storage';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return $dir . '/client_messages.json';
    }
}

if (!function_exists('cw_client_messages_defaults')) {
    function cw_client_messages_defaults(): array
    {
        return [
            'settings' => [
                'google_review_url' => 'https://g.page/r/CQ-1-K_N5AAEEBM/review',
            ],
            'templates' => [
                [
                    'id' => 'review_google',
                    'title' => 'Solicitar reseña Google Business',
                    'category' => 'reseñas',
                    'body' => "Hola *{nombre}*,\n\n"
                        . "Espero que tú y el equipo de *{empresa}* se encuentren muy bien.\n\n"
                        . "En ConlineWeb valoramos mucho la confianza que depositaron en nosotros. "
                        . "Si su experiencia ha sido positiva, nos ayudaría mucho que dejaran una reseña en Google: "
                        . "motiva al equipo y ayuda a más negocios a encontrarnos.\n\n"
                        . "Pueden hacerlo en este enlace (toma menos de un minuto):\n"
                        . "{link_resenas}\n\n"
                        . "Mil gracias de antemano. Su opinión marca la diferencia.\n\n"
                        . "Atentamente,\n"
                        . "Equipo ConlineWeb",
                    'updated_at' => null,
                ],
                [
                    'id' => 'seguimiento_general',
                    'title' => 'Seguimiento / ¿cómo van?',
                    'category' => 'comunicados',
                    'body' => "Hola *{nombre}*,\n\n"
                        . "Te escribimos desde ConlineWeb para saludarte y saber cómo va todo con *{empresa}*.\n\n"
                        . "Si necesitas apoyo con tu sitio web, hosting, dominio, tienda en línea o un nuevo proyecto, "
                        . "estamos para ayudarte.\n\n"
                        . "También puedes revisar tus servicios en el portal de cliente:\n"
                        . "https://cliente.conlineweb.com/\n\n"
                        . "Quedamos atentos a tu respuesta.\n\n"
                        . "Equipo ConlineWeb",
                    'updated_at' => null,
                ],
                [
                    'id' => 'aviso_renovacion',
                    'title' => 'Recordatorio de renovación',
                    'category' => 'comunicados',
                    'body' => "Hola *{nombre}*,\n\n"
                        . "Te recordamos revisar en tu portal de cliente los servicios de *{empresa}* "
                        . "por si hay alguna renovación próxima de hosting o dominio.\n\n"
                        . "Portal: https://cliente.conlineweb.com/\n\n"
                        . "Si prefieres pagar por transferencia:\n"
                        . "----------------------------------------\n"
                        . "Titular: Jose Antonio Martinez Karam\n"
                        . "Banco: Santander\n"
                        . "Cuenta: 60622161632\n"
                        . "CLABE: 014225606221616325\n"
                        . "----------------------------------------\n"
                        . "Después de transferir, envía tu comprobante por WhatsApp al *477 118 1285*.\n\n"
                        . "Si tienes dudas, responde este mensaje y te orientamos con gusto.\n\n"
                        . "Equipo ConlineWeb",
                    'updated_at' => null,
                ],
                [
                    'id' => 'pagos_pendientes',
                    'title' => 'Recordatorio de pagos pendientes',
                    'category' => 'cobranza',
                    'body' => "Hola *{nombre}*,\n\n"
                        . "Te escribimos de ConlineWeb para recordarte que *{empresa}* tiene uno o más "
                        . "pagos pendientes de revisar en el portal de cliente.\n\n"
                        . "Portal: https://cliente.conlineweb.com/\n\n"
                        . "*Datos para transferencia*\n"
                        . "Titular: Jose Antonio Martinez Karam\n"
                        . "Banco: Santander\n"
                        . "Cuenta: 60622161632\n"
                        . "CLABE: 014225606221616325\n"
                        . "Referencia: {empresa}\n\n"
                        . "Al pagar, envía tu comprobante por WhatsApp al *477 118 1285* para acreditarlo.\n\n"
                        . "Si ya realizaste el pago, omite este mensaje o avísanos para actualizarlo.\n\n"
                        . "Gracias por tu atención.\n"
                        . "Equipo ConlineWeb",
                    'updated_at' => null,
                ],
                [
                    'id' => 'bienvenida_portal',
                    'title' => 'Bienvenida / accesos al portal',
                    'category' => 'onboarding',
                    'body' => "Hola *{nombre}*,\n\n"
                        . "Bienvenido(a) a ConlineWeb. Desde tu portal de cliente puedes consultar servicios, "
                        . "pagos y dar seguimiento a tus proyectos de *{empresa}*.\n\n"
                        . "Portal: https://cliente.conlineweb.com/\n"
                        . "Usuario (correo): {correo}\n\n"
                        . "Si aún no tienes contraseña o no puedes entrar, responde este mensaje y te apoyamos.\n\n"
                        . "Equipo ConlineWeb",
                    'updated_at' => null,
                ],
                [
                    'id' => 'agradecimiento',
                    'title' => 'Agradecimiento por su preferencia',
                    'category' => 'comunicados',
                    'body' => "Hola *{nombre}*,\n\n"
                        . "Queremos agradecer a *{empresa}* por confiar en ConlineWeb.\n\n"
                        . "Seguimos disponibles para apoyarte con mejoras, soporte o nuevos proyectos digitales.\n\n"
                        . "Portal de cliente: https://cliente.conlineweb.com/\n\n"
                        . "Un saludo,\n"
                        . "Equipo ConlineWeb",
                    'updated_at' => null,
                ],
                [
                    'id' => 'promo_socio_tecnologico',
                    'title' => 'Publicidad · Socio tecnológico',
                    'category' => 'publicidad',
                    'body' => "Hola *{nombre}*,\n\n"
                        . "Tu empresa merece más que una página web.\n"
                        . "En ConlineWeb impulsamos el crecimiento de *{empresa}* con tecnología.\n\n"
                        . "*Qué hacemos*\n"
                        . "- Desarrollo de software\n"
                        . "- Páginas web\n"
                        . "- Inteligencia artificial\n"
                        . "- Automatización\n"
                        . "- SEO + GEO\n\n"
                        . "Resultados medibles. Soluciones a la medida.\n\n"
                        . "Si quieres explorarlo, agenda una asesoría gratuita y te orientamos sin compromiso.\n"
                        . "WhatsApp: 477 118 1285\n"
                        . "https://conlineweb.com/\n\n"
                        . "Equipo ConlineWeb",
                    'updated_at' => null,
                ],
                [
                    'id' => 'promo_inteligencia_artificial',
                    'title' => 'Publicidad · Inteligencia Artificial',
                    'category' => 'publicidad',
                    'body' => "Hola *{nombre}*,\n\n"
                        . "Automatiza *{empresa}* con Inteligencia Artificial.\n\n"
                        . "Reduce costos, mejora la atención y automatiza procesos con soluciones de IA diseñadas para tu negocio.\n\n"
                        . "*Podemos implementar*\n"
                        . "- Agentes de IA\n"
                        . "- Chatbots\n"
                        . "- Automatización de procesos\n"
                        . "- Atención por WhatsApp\n"
                        . "- Integraciones con tus sistemas\n\n"
                        . "Si te interesa, solicita una demo y te mostramos casos aplicables a tu operación.\n"
                        . "WhatsApp: 477 118 1285\n\n"
                        . "Equipo ConlineWeb",
                    'updated_at' => null,
                ],
                [
                    'id' => 'promo_paginas_web',
                    'title' => 'Publicidad · Páginas web que venden',
                    'category' => 'publicidad',
                    'body' => "Hola *{nombre}*,\n\n"
                        . "Convierte visitantes en clientes.\n\n"
                        . "Creamos sitios web rápidos, modernos y optimizados para aparecer en Google y generar más ventas para *{empresa}*.\n\n"
                        . "*Incluye*\n"
                        . "- SEO\n"
                        . "- GEO (presencia local)\n"
                        . "- Diseño profesional\n"
                        . "- Hosting\n"
                        . "- SSL y correos corporativos\n\n"
                        . "Si quieres renovar o crear tu sitio, agenda una asesoría gratuita.\n"
                        . "WhatsApp: 477 118 1285\n"
                        . "https://conlineweb.com/\n\n"
                        . "Equipo ConlineWeb",
                    'updated_at' => null,
                ],
                [
                    'id' => 'promo_software_medida',
                    'title' => 'Publicidad · Software a la medida',
                    'category' => 'publicidad',
                    'body' => "Hola *{nombre}*,\n\n"
                        . "Software diseñado para hacer crecer *{empresa}*.\n\n"
                        . "Desarrollamos sistemas a la medida para optimizar cada área de tu negocio:\n"
                        . "- CRM\n"
                        . "- ERP\n"
                        . "- Inventarios\n"
                        . "- Ventas\n"
                        . "- Producción\n"
                        . "- Reportes\n\n"
                        . "Tecnología que impulsa el crecimiento de tu empresa.\n\n"
                        . "¿Platicamos de un sistema a tu medida? Agenda una asesoría gratuita.\n"
                        . "WhatsApp: 477 118 1285\n\n"
                        . "Equipo ConlineWeb",
                    'updated_at' => null,
                ],
                [
                    'id' => 'promo_integraciones',
                    'title' => 'Publicidad · Integraciones y operación',
                    'category' => 'publicidad',
                    'body' => "Hola *{nombre}*,\n\n"
                        . "Conectamos toda la operación de *{empresa}*.\n\n"
                        . "Integramos tus sistemas con:\n"
                        . "- WhatsApp Business\n"
                        . "- APIs\n"
                        . "- CRMs y ERPs\n"
                        . "- Pasarelas de pago\n"
                        . "- Facturación\n"
                        . "- Inteligencia Artificial\n\n"
                        . "Menos trabajo manual. Más control y velocidad.\n\n"
                        . "Si quieres ver cómo aplicarlo en tu empresa, agenda una asesoría gratuita.\n"
                        . "WhatsApp: 477 118 1285\n\n"
                        . "Equipo ConlineWeb",
                    'updated_at' => null,
                ],
                [
                    'id' => 'promo_asesoria_gratuita',
                    'title' => 'Publicidad · Asesoría gratuita',
                    'category' => 'publicidad',
                    'body' => "Hola *{nombre}*,\n\n"
                        . "*Tecnología que impulsa el crecimiento de tu empresa.*\n\n"
                        . "En ConlineWeb ayudamos a *{empresa}* con desarrollo de software, páginas web, "
                        . "automatización, inteligencia artificial y posicionamiento digital.\n\n"
                        . "Agenda una asesoría gratuita y revisamos juntos la mejor ruta para tu negocio.\n"
                        . "WhatsApp: 477 118 1285\n"
                        . "https://conlineweb.com/\n\n"
                        . "Equipo ConlineWeb",
                    'updated_at' => null,
                ],
                [
                    'id' => 'noticia_interes_digital',
                    'title' => 'Noticia / tip de interés digital',
                    'category' => 'noticias',
                    'body' => "Hola *{nombre}*,\n\n"
                        . "Te compartimos un tip útil para *{empresa}*:\n\n"
                        . "Hoy, una presencia digital sólida no termina en tener sitio web. "
                        . "Lo que más impacta es combinar velocidad del sitio, presencia en Google (SEO/GEO), "
                        . "atención automatizada y datos para decidir mejor.\n\n"
                        . "Si quieres, te ayudamos a revisar en una llamada corta qué oportunidad tiene más retorno para tu negocio.\n"
                        . "WhatsApp: 477 118 1285\n\n"
                        . "Equipo ConlineWeb",
                    'updated_at' => null,
                ],
            ],
            'google_reviews' => [],
        ];
    }
}

if (!function_exists('cw_client_messages_normalize')) {
    function cw_client_messages_normalize(array $data): array
    {
        $defaults = cw_client_messages_defaults();
        $settings = array_merge($defaults['settings'], is_array($data['settings'] ?? null) ? $data['settings'] : []);
        $templates = [];
        $seen = [];

        foreach (($data['templates'] ?? []) as $tpl) {
            if (!is_array($tpl)) {
                continue;
            }
            $id = trim((string) ($tpl['id'] ?? ''));
            $title = trim((string) ($tpl['title'] ?? ''));
            $body = (string) ($tpl['body'] ?? '');
            if ($id === '' || $title === '' || $body === '') {
                continue;
            }
            $templates[] = [
                'id' => $id,
                'title' => $title,
                'category' => trim((string) ($tpl['category'] ?? 'general')) ?: 'general',
                'body' => $body,
                'updated_at' => $tpl['updated_at'] ?? null,
            ];
            $seen[$id] = true;
        }

        // Añadir plantillas nuevas del sistema si aún no existen
        foreach ($defaults['templates'] as $def) {
            $id = (string) ($def['id'] ?? '');
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $templates[] = $def;
            $seen[$id] = true;
        }

        if ($templates === []) {
            $templates = $defaults['templates'];
        }

        return [
            'settings' => [
                'google_review_url' => trim((string) ($settings['google_review_url'] ?? '')),
            ],
            'templates' => $templates,
            'google_reviews' => cw_client_messages_normalize_reviews(
                is_array($data['google_reviews'] ?? null) ? $data['google_reviews'] : []
            ),
        ];
    }
}

if (!function_exists('cw_client_messages_normalize_reviews')) {
    /**
     * @param array<string, mixed> $reviews
     * @return array<string, array{done:bool, at:?string, by:?int, note:string}>
     */
    function cw_client_messages_normalize_reviews(array $reviews): array
    {
        $out = [];
        foreach ($reviews as $key => $row) {
            $key = trim((string) $key);
            if ($key === '' || !is_array($row)) {
                continue;
            }
            $done = !empty($row['done']);
            if (!$done) {
                continue;
            }
            $out[$key] = [
                'done' => true,
                'at' => isset($row['at']) ? (string) $row['at'] : null,
                'by' => isset($row['by']) ? (int) $row['by'] : null,
                'note' => trim((string) ($row['note'] ?? '')),
            ];
        }

        return $out;
    }
}

if (!function_exists('cw_client_google_review_key')) {
    function cw_client_google_review_key(int $clienteId, string $sistema = 'conlineweb'): string
    {
        $sistema = preg_replace('/[^a-z0-9_\-]/i', '', strtolower($sistema)) ?: 'conlineweb';

        return $sistema . ':' . max(0, $clienteId);
    }
}

if (!function_exists('cw_client_google_review_done')) {
    function cw_client_google_review_done(int $clienteId, string $sistema = 'conlineweb', ?array $data = null): bool
    {
        $data = $data ?? cw_client_messages_load();
        $key = cw_client_google_review_key($clienteId, $sistema);
        $row = $data['google_reviews'][$key] ?? null;

        return is_array($row) && !empty($row['done']);
    }
}

if (!function_exists('cw_client_google_review_set')) {
    /**
     * @return array{ok:bool, error?:string, data?:array, entry?:?array, key?:string}
     */
    function cw_client_google_review_set(int $clienteId, string $sistema, bool $done, ?int $byUid = null, string $note = ''): array
    {
        if ($clienteId <= 0) {
            return ['ok' => false, 'error' => 'Cliente inválido'];
        }

        $data = cw_client_messages_load();
        if (!isset($data['google_reviews']) || !is_array($data['google_reviews'])) {
            $data['google_reviews'] = [];
        }

        $key = cw_client_google_review_key($clienteId, $sistema);
        if ($done) {
            $data['google_reviews'][$key] = [
                'done' => true,
                'at' => date('c'),
                'by' => $byUid,
                'note' => trim($note),
            ];
            $entry = $data['google_reviews'][$key];
        } else {
            unset($data['google_reviews'][$key]);
            $entry = null;
        }

        if (!cw_client_messages_save($data)) {
            return ['ok' => false, 'error' => 'No se pudo guardar el checklist de reseñas'];
        }

        return ['ok' => true, 'data' => $data, 'entry' => $entry, 'key' => $key];
    }
}

if (!function_exists('cw_client_messages_load')) {
    function cw_client_messages_load(): array
    {
        $path = cw_client_messages_storage_path();
        if (!is_file($path)) {
            $data = cw_client_messages_defaults();
            cw_client_messages_save($data);
            return $data;
        }

        $raw = @file_get_contents($path);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            return cw_client_messages_defaults();
        }

        return cw_client_messages_normalize($decoded);
    }
}

if (!function_exists('cw_client_messages_save')) {
    function cw_client_messages_save(array $data): bool
    {
        $path = cw_client_messages_storage_path();
        $normalized = cw_client_messages_normalize($data);
        $json = json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        return file_put_contents($path, $json . "\n", LOCK_EX) !== false;
    }
}

if (!function_exists('cw_client_messages_upsert_template')) {
    function cw_client_messages_upsert_template(array $tpl): array
    {
        $data = cw_client_messages_load();
        $id = trim((string) ($tpl['id'] ?? ''));
        if ($id === '') {
            $id = 'msg_' . substr(bin2hex(random_bytes(6)), 0, 12);
        }

        $entry = [
            'id' => $id,
            'title' => trim((string) ($tpl['title'] ?? 'Sin título')),
            'category' => trim((string) ($tpl['category'] ?? 'general')) ?: 'general',
            'body' => (string) ($tpl['body'] ?? ''),
            'updated_at' => date('c'),
        ];

        $found = false;
        foreach ($data['templates'] as $i => $existing) {
            if (($existing['id'] ?? '') === $id) {
                $data['templates'][$i] = $entry;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $data['templates'][] = $entry;
        }

        if (!cw_client_messages_save($data)) {
            return ['ok' => false, 'error' => 'No se pudo guardar el archivo de plantillas'];
        }

        return ['ok' => true, 'template' => $entry, 'data' => $data];
    }
}

if (!function_exists('cw_client_messages_delete_template')) {
    function cw_client_messages_delete_template(string $id): array
    {
        $id = trim($id);
        if ($id === '') {
            return ['ok' => false, 'error' => 'ID vacío'];
        }

        $data = cw_client_messages_load();
        $before = count($data['templates']);
        $data['templates'] = array_values(array_filter(
            $data['templates'],
            static fn(array $t): bool => ($t['id'] ?? '') !== $id
        ));

        if (count($data['templates']) === $before) {
            return ['ok' => false, 'error' => 'Plantilla no encontrada'];
        }

        if ($data['templates'] === []) {
            $data['templates'] = cw_client_messages_defaults()['templates'];
        }

        if (!cw_client_messages_save($data)) {
            return ['ok' => false, 'error' => 'No se pudo guardar'];
        }

        return ['ok' => true, 'data' => $data];
    }
}

if (!function_exists('cw_client_messages_render')) {
    function cw_client_messages_render(string $body, array $vars): string
    {
        $map = [
            '{nombre}' => (string) ($vars['nombre'] ?? ''),
            '{empresa}' => (string) ($vars['empresa'] ?? ''),
            '{correo}' => (string) ($vars['correo'] ?? ''),
            '{telefono}' => (string) ($vars['telefono'] ?? ''),
            '{link_resenas}' => (string) ($vars['link_resenas'] ?? ''),
            '{link_reseñas}' => (string) ($vars['link_resenas'] ?? ''),
        ];

        return strtr($body, $map);
    }
}
