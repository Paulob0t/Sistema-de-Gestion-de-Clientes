<?php
/**
 * Constancia de situación fiscal — MÓDULO COMPARTIDO
 *
 * Usado por:
 *   - https://cliente.conlineweb.com/cliente.php  (actulizacion_clientes.php)
 *   - https://adm.conlineweb.com/detalle_cliente.php
 *
 * IMPORTANTE: Mantener idéntico en:
 *   cliente.conlineweb.com/includes/cw_constancia_fiscal.php
 *   adm.conlineweb.com/includes/cw_constancia_fiscal.php
 *
 * Carpeta local (sincronizada entre ambos):  {root}/constancias_fiscales/
 * URL de consulta (siempre):
 *   https://cliente.conlineweb.com/constancias_fiscales/{archivo}
 */

if (!defined('CW_CONSTANCIA_FISCAL_PUBLIC_BASE')) {
    define('CW_CONSTANCIA_FISCAL_PUBLIC_BASE', 'https://cliente.conlineweb.com/constancias_fiscales');
}

if (!function_exists('cw_constancia_fiscal_upload_dir')) {
    function cw_constancia_fiscal_upload_dir(): string
    {
        // Carpeta junto al portal que incluye este archivo (adm o cliente).
        // Ambas carpetas deben estar sincronizadas.
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'constancias_fiscales' . DIRECTORY_SEPARATOR;
    }
}

if (!function_exists('cw_constancia_fiscal_public_url')) {
    function cw_constancia_fiscal_public_url(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        return rtrim(CW_CONSTANCIA_FISCAL_PUBLIC_BASE, '/') . '/' . rawurlencode($filename);
    }
}

if (!function_exists('cw_constancia_fiscal_filename_from_stored')) {
    function cw_constancia_fiscal_filename_from_stored(?string $stored): ?string
    {
        if ($stored === null) {
            return null;
        }
        $stored = trim($stored);
        if ($stored === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $stored)) {
            $path = parse_url($stored, PHP_URL_PATH);
            $name = $path ? basename($path) : '';
        } else {
            $name = basename(str_replace('\\', '/', $stored));
        }
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '', $name ?? '') ?? '';
        return $name !== '' ? $name : null;
    }
}

if (!function_exists('cw_constancia_fiscal_allowed_extension')) {
    function cw_constancia_fiscal_allowed_extension(string $originalName): ?string
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $map = [
            'pdf' => 'pdf',
            'jpg' => 'jpg',
            'jpeg' => 'jpg',
            'png' => 'png',
            'webp' => 'webp',
        ];
        return $map[$ext] ?? null;
    }
}

if (!function_exists('cw_constancia_fiscal_parse')) {
    function cw_constancia_fiscal_parse(?string $stored): ?array
    {
        $filename = cw_constancia_fiscal_filename_from_stored($stored);
        if ($filename === null) {
            return null;
        }

        $localPath = cw_constancia_fiscal_upload_dir() . $filename;
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed, true)) {
            return null;
        }

        $type = in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) ? 'image' : 'pdf';
        $url = cw_constancia_fiscal_public_url($filename);

        return [
            'filename' => $filename,
            'url' => $url,
            'cliente_url' => $url,
            'local_path' => $localPath,
            // Si la sync aún no llegó, igual se consulta por URL canónica
            'exists' => is_file($localPath),
            'type' => $type,
            'ext' => $ext,
        ];
    }
}

if (!function_exists('cw_constancia_fiscal_save_upload')) {
    /**
     * Guarda en {root}/constancias_fiscales/ (sincronizada)
     * y devuelve el nombre + URL canónica en cliente.conlineweb.com
     */
    function cw_constancia_fiscal_save_upload(int $clientId, array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'No se pudo subir el archivo.'];
        }

        $ext = cw_constancia_fiscal_allowed_extension($file['name'] ?? '');
        if ($ext === null) {
            return ['ok' => false, 'error' => 'Formato no permitido. Usa PDF, JPG o PNG.'];
        }

        $maxBytes = 8 * 1024 * 1024;
        if (($file['size'] ?? 0) > $maxBytes) {
            return ['ok' => false, 'error' => 'El archivo supera el límite de 8 MB.'];
        }

        $uploadDir = cw_constancia_fiscal_upload_dir();
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            return ['ok' => false, 'error' => 'No se pudo crear la carpeta constancias_fiscales/.'];
        }
        if (!is_writable($uploadDir)) {
            return ['ok' => false, 'error' => 'La carpeta constancias_fiscales/ no tiene permisos de escritura.'];
        }

        $filename = 'constancia_' . max(0, $clientId) . '_' . time() . '.' . $ext;
        $destPath = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            return ['ok' => false, 'error' => 'Error al guardar el archivo en constancias_fiscales/.'];
        }
        @chmod($destPath, 0644);

        return [
            'ok' => true,
            'filename' => $filename,
            'url' => cw_constancia_fiscal_public_url($filename),
        ];
    }
}
