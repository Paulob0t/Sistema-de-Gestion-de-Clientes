<?php

require_once __DIR__ . '/openai_config.php';

function cotizacion_precios_system_prompt(): string
{
    return <<<'PROMPT'
Eres un cotizador experto en desarrollo de software para CONLINEWEB, una agencia de desarrollo con sede en México.

Tu tarea es analizar solicitudes/tickets de desarrollo y calcular su costo en pesos mexicanos (MXN) basándote en precios de mercado actuales para desarrollo de software en México (2025-2026).

Para cada solicitud debes determinar:
1. Una descripción profesional del servicio (para incluir en la cotización)
2. La categoría del servicio (Desarrollo Web, Desarrollo Móvil, Integración API, Consultoría, Diseño UI/UX, Mantenimiento, Soporte Técnico, Base de Datos, DevOps, Otro)
3. El precio unitario de mercado en MXN (sin descuento)
4. La cantidad (por defecto 1, a menos que sea un servicio recurrente)
5. El tipo de unidad (hora, pieza, proyecto, mensualidad)

REGLAS IMPORTANTES:
- Los precios deben reflejar el mercado MEXICANO de desarrollo de software
- No uses precios de USA o Europa
- Precios de referencia (por hora):
  * Desarrollo Web (Frontend/Backend): $600-$1,200 MXN/hora
  * Desarrollo Móvil: $700-$1,400 MXN/hora
  * Integración de API: $500-$1,000 MXN/hora
  * Diseño UI/UX: $400-$900 MXN/hora
  * Consultoría técnica: $800-$1,500 MXN/hora
  * Base de datos/DevOps: $600-$1,200 MXN/hora
  * Soporte/Mantenimiento: $400-$800 MXN/hora
- Para proyectos completos, calcula el precio total basado en las horas estimadas
- Sé consistente: tickets similares deben tener precios similares
- Si un ticket tiene descripción muy breve o genérica, usa el precio mínimo de la categoría
- No inventes descuentos — el descuento comercial se aplica después

RESPONDE SIEMPRE con un JSON válido (sin markdown) con esta estructura exacta:
{
  "items": [
    {
      "id_solicitud": 123,
      "titulo": "Título de la solicitud",
      "descripcion_servicio": "Descripción profesional del servicio para la cotización",
      "categoria": "Desarrollo Web",
      "unidad": "proyecto",
      "cantidad": 1,
      "precio_unitario": 15000.00,
      "total": 15000.00
    }
  ],
  "notas_adicionales": "Notas relevantes sobre la cotización",
  "dias_validez": 15
}

IMPORTANTE: Los totales por item deben ser precio_unitario * cantidad. No apliques descuento aquí.
PROMPT;
}

function cotizacion_analizar_solicitudes(array $solicitudes): array
{
    $apiKey = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '';
    if ($apiKey === '') {
        if (function_exists('openai_dev_mock_enabled') && openai_dev_mock_enabled()) {
            return [
                'success' => false,
                'error' => 'Cotización con IA no disponible en modo desarrollo local. Define OPENAI_API_KEY en includes/openai_config.local.php.',
            ];
        }

        return [
            'success' => false,
            'error' => 'OPENAI_API_KEY no configurada.',
        ];
    }

    $solicitudesTexto = "";
    foreach ($solicitudes as $s) {
        $desc = $s['descripcion_text'] ?? $s['descripcion'] ?? '';
        if (is_array($desc)) {
            $desc = $desc['text'] ?? json_encode($desc);
        }
        $solicitudesTexto .= "--- SOLICITUD #{$s['id']} ---\n";
        $solicitudesTexto .= "Título: {$s['titulo']}\n";
        $solicitudesTexto .= "Descripción: {$desc}\n";
        $solicitudesTexto .= "Prioridad: {$s['prioridad']}\n\n";
    }

    $messages = [
        ['role' => 'system', 'content' => cotizacion_precios_system_prompt()],
        ['role' => 'user', 'content' => "Analiza las siguientes solicitudes de desarrollo y genera una cotización profesional con precios de mercado en México:\n\n" . $solicitudesTexto],
    ];

    $payload = [
        'model' => 'gpt-4o-mini',
        'temperature' => 0.3,
        'response_format' => ['type' => 'json_object'],
        'messages' => $messages,
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
        CURLOPT_TIMEOUT => 120,
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

    $items = $parsed['items'] ?? [];
    if (!is_array($items) || empty($items)) {
        return [
            'success' => false,
            'error' => 'La IA no generó items de cotización',
            'raw' => $content,
        ];
    }

    foreach ($items as &$item) {
        $item['total'] = round((float)($item['precio_unitario'] ?? 0) * (int)($item['cantidad'] ?? 1), 2);
        $item['precio_unitario'] = round((float)($item['precio_unitario'] ?? 0), 2);
    }
    unset($item);

    return [
        'success' => true,
        'items' => $items,
        'notas_adicionales' => $parsed['notas_adicionales'] ?? '',
        'dias_validez' => (int)($parsed['dias_validez'] ?? 15),
        'usage' => $data['usage'] ?? null,
        'http_code' => $httpCode,
        'raw' => $content,
    ];
}
