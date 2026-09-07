<?php
/**
 * Códigos de verificación de cuenta (envío por correo).
 * Almacenamiento JSON local — sin cambios de esquema BD.
 */
declare(strict_types=1);

if (!function_exists('cw_client_verification_storage_path')) {
    function cw_client_verification_storage_path(): string
    {
        $dir = dirname(__DIR__) . '/storage';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return $dir . '/client_verification_codes.json';
    }
}

if (!function_exists('cw_client_verification_key')) {
    function cw_client_verification_key(int $clienteId, string $sistema = 'conlineweb'): string
    {
        $sistema = preg_replace('/[^a-z0-9_\-]/i', '', strtolower($sistema)) ?: 'conlineweb';

        return $sistema . ':' . max(0, $clienteId);
    }
}

if (!function_exists('cw_client_verification_load_all')) {
    /**
     * @return array<string, array{current:?string, updated_at:?string, history:list<array>}>
     */
    function cw_client_verification_load_all(): array
    {
        $path = cw_client_verification_storage_path();
        if (!is_file($path)) {
            return [];
        }
        $raw = @file_get_contents($path);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $key => $row) {
            $key = trim((string) $key);
            if ($key === '' || !is_array($row)) {
                continue;
            }
            $history = [];
            foreach (($row['history'] ?? []) as $item) {
                if (!is_array($item) || empty($item['code'])) {
                    continue;
                }
                $history[] = [
                    'code' => (string) $item['code'],
                    'sent_at' => (string) ($item['sent_at'] ?? ''),
                    'by' => isset($item['by']) ? (int) $item['by'] : null,
                    'correo' => (string) ($item['correo'] ?? ''),
                ];
            }
            $out[$key] = [
                'current' => isset($row['current']) && $row['current'] !== '' ? (string) $row['current'] : null,
                'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : null,
                'history' => $history,
            ];
        }

        return $out;
    }
}

if (!function_exists('cw_client_verification_save_all')) {
    function cw_client_verification_save_all(array $data): bool
    {
        $path = cw_client_verification_storage_path();
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        return file_put_contents($path, $json . "\n", LOCK_EX) !== false;
    }
}

if (!function_exists('cw_client_verification_get')) {
    /**
     * @return array{current:?string, updated_at:?string, history:list<array>}
     */
    function cw_client_verification_get(int $clienteId, string $sistema = 'conlineweb'): array
    {
        $all = cw_client_verification_load_all();
        $key = cw_client_verification_key($clienteId, $sistema);
        if (isset($all[$key]) && is_array($all[$key])) {
            return $all[$key];
        }

        return ['current' => null, 'updated_at' => null, 'history' => []];
    }
}

if (!function_exists('cw_client_verification_generate_code')) {
    function cw_client_verification_generate_code(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('cw_client_verification_register_send')) {
    /**
     * Genera código nuevo, lo pone como actual y lo agrega al historial.
     *
     * @return array{ok:bool, error?:string, code?:string, entry?:array, data?:array}
     */
    function cw_client_verification_register_send(
        int $clienteId,
        string $sistema,
        string $correo,
        ?int $byUid = null
    ): array {
        if ($clienteId <= 0) {
            return ['ok' => false, 'error' => 'Cliente inválido'];
        }

        $code = cw_client_verification_generate_code();
        $all = cw_client_verification_load_all();
        $key = cw_client_verification_key($clienteId, $sistema);
        $existing = $all[$key] ?? ['current' => null, 'updated_at' => null, 'history' => []];
        $history = is_array($existing['history'] ?? null) ? $existing['history'] : [];

        $entry = [
            'code' => $code,
            'sent_at' => date('c'),
            'by' => $byUid,
            'correo' => $correo,
        ];

        array_unshift($history, $entry);
        $history = array_slice($history, 0, 10);

        $all[$key] = [
            'current' => $code,
            'updated_at' => $entry['sent_at'],
            'history' => $history,
        ];

        if (!cw_client_verification_save_all($all)) {
            return ['ok' => false, 'error' => 'No se pudo guardar el código'];
        }

        return [
            'ok' => true,
            'code' => $code,
            'entry' => $entry,
            'data' => $all[$key],
        ];
    }
}
