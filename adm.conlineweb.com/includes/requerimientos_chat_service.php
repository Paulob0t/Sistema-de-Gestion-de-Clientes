<?php

require_once __DIR__ . '/openai_config.php';

function requerimientos_chat_system_prompt(string $contextoProyecto = ''): string
{
    $base = <<<'PROMPT'
Eres un agente de soporte técnico de CONLINEWEB con nivel técnico medio. Conoces desarrollo web, CMS y mantenimiento, pero redactas para que cualquier miembro del equipo entienda qué hay que hacer sin ambigüedades.

Tu trabajo: transformar lo que el usuario escribe en un ticket de desarrollo listo para ejecutar, manteniéndote lo más fiel posible a su solicitud original.

PERFIL DE REDACCIÓN:
- Lenguaje claro, directo y profesional. Oraciones cortas.
- Usa términos técnicos solo cuando aporten precisión (ej. "formulario", "header", "responsive", "URL", "plugin").
- Evita jerga innecesaria, adornos y explicaciones teóricas que no ayuden a ejecutar el trabajo.
- Escribe como un agente experimentado que documenta bien un ticket para el desarrollador.
- NUNCA uses formato "Historia de usuario" (Como [rol] quiero [acción] para [beneficio]). Usa lenguaje directo de ticket técnico.

FIDELIDAD A LA SOLICITUD (CRÍTICO):
1. El ticket debe reflejar la intención exacta del usuario. No reinterpretes ni "mejores" lo que pidió.
2. Conserva textualmente o casi textualmente: nombres propios, URLs, textos exactos, colores, medidas, cantidades, ubicaciones en pantalla, comportamientos específicos y condiciones que el usuario mencionó.
3. No omitas ningún detalle relevante que el usuario haya proporcionado. Si mencionó 5 puntos, el documento debe cubrir los 5.
4. No agregues funcionalidades, supuestos, alcances extra, sugerencias de mejora ni requisitos que el usuario no haya pedido.
5. No sustituyas lo que dijo el usuario por una versión genérica. Si dijo "botón azul en el header", no lo conviertas en "mejorar la navegación".
6. Si el usuario da instrucciones claras y completas en un solo mensaje, estructúralas y marca listo_para_ticket en true sin hacer preguntas innecesarias.

PREGUNTAS DE ACLARACIÓN:
- Solo pregunta si falta información crítica para ejecutar el trabajo (¿qué página?, ¿qué texto exacto?, ¿en qué sección?).
- Máximo 2 preguntas por turno.
- Si la solicitud es suficientemente clara para un desarrollador, no preguntes: genera el ticket.

CONTEXTO DE CLIENTE/PROYECTO:
- Si existe, úsalo para alinear stack, tecnologías y alcance. No propongas rehacer lo ya definido salvo que lo pidan.
- Los requerimientos deben ser coherentes con la descripción técnica del proyecto.

ESTRUCTURA DEL DOCUMENTO:
- resumen_solicitud: párrafo breve que capture fielmente lo que el usuario pidió, en sus propios términos cuando sea posible.
- titulo_sugerido: resumen corto de la intención real del usuario (máx. 80 caracteres), usando su vocabulario.
- detalle_tecnico: descripción técnica accionable para quien ejecute el ticket (página/sección/módulo afectado, cambios concretos, comportamiento esperado, textos/URLs/colores si aplican). Redacta como ticket de soporte técnico, NO uses formato "Como [rol] quiero...".
- requerimientos_funcionales: cada RF accionable, con qué hacer, dónde y cómo (según lo indicado por el usuario).
- criterios_aceptacion: verificables, basados únicamente en lo que el usuario describió.

CAPTURAS DE PANTALLA / IMÁGENES ADJUNTAS:
- Si el usuario adjunta imágenes, analízalas para identificar textos visibles, botones, secciones, errores, colores, disposición y cualquier detalle relevante.
- Incorpora en el ticket los hallazgos visuales que complementen o aclaren la solicitud escrita.
- No inventes elementos que no aparezcan en las imágenes ni contradigas lo que el usuario escribió.
- Si solo hay imágenes sin texto, describe con precisión lo que se observa y genera el ticket a partir de ello.

RESPONDE SIEMPRE con un JSON válido (sin markdown) con esta estructura exacta:
{
  "mensaje_chat": "texto conversacional breve para el chat; confirma lo entendido o pregunta solo lo indispensable",
  "documento": {
    "resumen_solicitud": "Resumen fiel de lo que el usuario pidió",
    "detalle_tecnico": "Descripción técnica accionable: dónde, qué cambiar y comportamiento esperado",
    "requerimientos_funcionales": [
      {"id": "RF-01", "titulo": "Título corto", "descripcion": "Descripción clara y accionable, fiel al pedido original"}
    ],
    "criterios_aceptacion": [
      "Dado que... Cuando... Entonces..."
    ],
    "dudas_abiertas": ["pregunta pendiente solo si es crítica para ejecutar"],
    "prioridad_sugerida": "Alta|Media|Baja",
    "titulo_sugerido": "Título corto para el ticket"
  },
  "listo_para_ticket": false
}

Actualiza el documento en cada turno con lo acumulado. Los arrays pueden empezar vacíos.
PROMPT;

    if ($contextoProyecto !== '') {
        $base .= "\n\n--- CONTEXTO DE CLIENTE/PROYECTO (usar como referencia) ---\n" . $contextoProyecto;
    }

    return $base;
}

/**
 * Convierte mensajes del chat (texto + imágenes opcionales) al formato OpenAI.
 */
function requerimientos_chat_build_openai_messages(array $messages): array
{
    $out = [];

    foreach ($messages as $msg) {
        if (!is_array($msg)) {
            continue;
        }

        $role = $msg['role'] ?? '';
        $content = trim((string) ($msg['content'] ?? ''));
        $images = $msg['images'] ?? [];

        if ($role === 'assistant') {
            if ($content !== '') {
                $out[] = ['role' => 'assistant', 'content' => $content];
            }
            continue;
        }

        if ($role !== 'user') {
            continue;
        }

        $imageUrls = [];
        if (is_array($images)) {
            foreach ($images as $img) {
                $url = trim((string) $img);
                if ($url !== '' && preg_match('#^data:image/(jpeg|jpg|png|gif|webp);base64,#i', $url)) {
                    $imageUrls[] = $url;
                }
            }
            $imageUrls = array_slice($imageUrls, 0, 5);
        }

        if (!empty($imageUrls)) {
            $parts = [];
            $parts[] = [
                'type' => 'text',
                'text' => $content !== ''
                    ? $content
                    : 'El usuario adjuntó capturas de pantalla. Analízalas y genera el ticket según lo que se observa.',
            ];
            foreach ($imageUrls as $url) {
                $parts[] = [
                    'type' => 'image_url',
                    'image_url' => ['url' => $url, 'detail' => 'high'],
                ];
            }
            $out[] = ['role' => 'user', 'content' => $parts];
        } elseif ($content !== '') {
            $out[] = ['role' => 'user', 'content' => $content];
        }
    }

    return $out;
}

function requerimientos_chat_mock_response(array $messages): array
{
    $lastUser = '';
    foreach (array_reverse($messages) as $msg) {
        if (!is_array($msg) || ($msg['role'] ?? '') !== 'user') {
            continue;
        }
        $lastUser = trim((string) ($msg['content'] ?? ''));
        if ($lastUser !== '') {
            break;
        }
    }

    if ($lastUser === '') {
        $lastUser = 'Solicitud registrada desde el asistente (modo desarrollo local).';
    }

    $titulo = function_exists('mb_substr') ? mb_substr($lastUser, 0, 80) : substr($lastUser, 0, 80);

    return [
        'success' => true,
        'mensaje_chat' => '[Modo desarrollo local] Borrador generado sin OpenAI. Para respuestas reales del asistente, define OPENAI_API_KEY en includes/openai_config.local.php.',
        'documento' => [
            'resumen_solicitud' => $lastUser,
            'detalle_tecnico' => $lastUser,
            'requerimientos_funcionales' => [
                [
                    'id' => 'RF-01',
                    'titulo' => 'Solicitud principal',
                    'descripcion' => $lastUser,
                ],
            ],
            'criterios_aceptacion' => [
                'La solicitud queda documentada según lo indicado por el usuario.',
            ],
            'dudas_abiertas' => [],
            'prioridad_sugerida' => 'Media',
            'titulo_sugerido' => $titulo,
        ],
        'listo_para_ticket' => true,
    ];
}

function requerimientos_chat_call(array $messages, string $contextoProyecto = ''): array
{
    $apiKey = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '';
    if ($apiKey === '') {
        if (function_exists('openai_dev_mock_enabled') && openai_dev_mock_enabled()) {
            return requerimientos_chat_mock_response($messages);
        }

        return [
            'success' => false,
            'error' => openai_is_production_host()
                ? 'OPENAI_API_KEY no configurada en el servidor. Verifica includes/openai_config.secrets.php o la variable de entorno en cPanel.'
                : 'OPENAI_API_KEY no configurada. Crea includes/openai_config.local.php o define la variable de entorno.',
        ];
    }

    $openaiMessages = requerimientos_chat_build_openai_messages($messages);
    if (count($openaiMessages) === 0) {
        return ['success' => false, 'error' => 'No hay mensajes válidos para enviar al asistente'];
    }

    $payload = [
        'model' => 'gpt-4o-mini',
        'temperature' => 0.25,
        'response_format' => ['type' => 'json_object'],
        'messages' => array_merge(
            [['role' => 'system', 'content' => requerimientos_chat_system_prompt($contextoProyecto)]],
            $openaiMessages
        ),
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
        CURLOPT_TIMEOUT => 90,
    ]);

    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['success' => false, 'error' => 'Error de conexión con OpenAI: ' . $curlError];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return ['success' => false, 'error' => 'Respuesta inválida de OpenAI'];
    }

    if (isset($data['error'])) {
        $msg = is_array($data['error']) ? ($data['error']['message'] ?? 'Error OpenAI') : (string) $data['error'];
        return ['success' => false, 'error' => $msg];
    }

    $content = $data['choices'][0]['message']['content'] ?? '';
    $parsed = json_decode($content, true);
    if (!is_array($parsed)) {
        return [
            'success' => false,
            'error' => 'No se pudo interpretar la respuesta del asistente',
            'raw' => $content,
        ];
    }

    $documento = $parsed['documento'] ?? [];
    if (!is_array($documento)) {
        $documento = [];
    }

    return [
        'success' => true,
        'mensaje_chat' => (string) ($parsed['mensaje_chat'] ?? ''),
        'documento' => [
            'resumen_solicitud' => (string) ($documento['resumen_solicitud'] ?? ''),
            'detalle_tecnico' => (string) ($documento['detalle_tecnico'] ?? $documento['historia_usuario'] ?? ''),
            'requerimientos_funcionales' => is_array($documento['requerimientos_funcionales'] ?? null)
                ? $documento['requerimientos_funcionales'] : [],
            'criterios_aceptacion' => is_array($documento['criterios_aceptacion'] ?? null)
                ? $documento['criterios_aceptacion'] : [],
            'dudas_abiertas' => is_array($documento['dudas_abiertas'] ?? null)
                ? $documento['dudas_abiertas'] : [],
            'prioridad_sugerida' => in_array($documento['prioridad_sugerida'] ?? '', ['Alta', 'Media', 'Baja'], true)
                ? $documento['prioridad_sugerida'] : 'Media',
            'titulo_sugerido' => (string) ($documento['titulo_sugerido'] ?? ''),
        ],
        'listo_para_ticket' => !empty($parsed['listo_para_ticket']),
        'usage' => $data['usage'] ?? null,
        'http_code' => $httpCode,
    ];
}

function requerimientos_documento_a_descripcion(array $documento): string
{
    $partes = [];

    if (!empty($documento['resumen_solicitud'])) {
        $partes[] = "## Resumen de la solicitud\n" . $documento['resumen_solicitud'];
    }

    if (!empty($documento['detalle_tecnico'])) {
        $partes[] = "## Detalle técnico\n" . $documento['detalle_tecnico'];
    }

    $rfs = $documento['requerimientos_funcionales'] ?? [];
    if (!empty($rfs)) {
        $bloque = "## Requerimientos funcionales\n";
        foreach ($rfs as $rf) {
            if (!is_array($rf)) {
                continue;
            }
            $id = $rf['id'] ?? 'RF';
            $titulo = $rf['titulo'] ?? '';
            $desc = $rf['descripcion'] ?? '';
            $bloque .= "- **{$id}" . ($titulo ? " — {$titulo}" : '') . "**: {$desc}\n";
        }
        $partes[] = trim($bloque);
    }

    $cas = $documento['criterios_aceptacion'] ?? [];
    if (!empty($cas)) {
        $bloque = "## Criterios de aceptación\n";
        foreach ($cas as $ca) {
            $bloque .= "- {$ca}\n";
        }
        $partes[] = trim($bloque);
    }

    $dudas = $documento['dudas_abiertas'] ?? [];
    if (!empty($dudas)) {
        $bloque = "## Dudas abiertas\n";
        foreach ($dudas as $d) {
            $bloque .= "- {$d}\n";
        }
        $partes[] = trim($bloque);
    }

    return implode("\n\n", $partes);
}
