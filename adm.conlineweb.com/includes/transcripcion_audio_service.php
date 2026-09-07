<?php

require_once __DIR__ . '/openai_config.php';

function transcribir_audio_openai(string $filePath, string $originalName = 'audio.webm'): array
{
    $apiKey = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '';
    if ($apiKey === '') {
        if (function_exists('openai_dev_mock_enabled') && openai_dev_mock_enabled()) {
            return [
                'success' => false,
                'error' => 'Transcripción de audio no disponible en modo desarrollo local. Define OPENAI_API_KEY en includes/openai_config.local.php.',
            ];
        }

        return ['success' => false, 'error' => 'OPENAI_API_KEY no configurada para transcribir audio.'];
    }

    if (!is_file($filePath) || filesize($filePath) === 0) {
        return ['success' => false, 'error' => 'Archivo de audio vacío o inválido.'];
    }

    $mime = mime_content_type($filePath) ?: 'audio/webm';
    $cfile = new CURLFile($filePath, $mime, $originalName);

    $post = [
        'file' => $cfile,
        'model' => 'whisper-1',
        'language' => 'es',
        'response_format' => 'json',
    ];

    $ch = curl_init('https://api.openai.com/v1/audio/transcriptions');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
    ]);

    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['success' => false, 'error' => 'Error de conexión: ' . $curlError];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return ['success' => false, 'error' => 'Respuesta inválida del servicio de transcripción'];
    }

    if ($httpCode >= 400 || isset($data['error'])) {
        $msg = is_array($data['error'] ?? null)
            ? ($data['error']['message'] ?? 'Error al transcribir')
            : (string) ($data['error'] ?? 'Error al transcribir');
        return ['success' => false, 'error' => $msg, 'http_code' => $httpCode];
    }

    $texto = trim((string) ($data['text'] ?? ''));
    if ($texto === '') {
        return ['success' => false, 'error' => 'No se detectó texto en el audio. Intenta hablar más cerca del micrófono.'];
    }

    return [
        'success' => true,
        'texto' => $texto,
    ];
}
