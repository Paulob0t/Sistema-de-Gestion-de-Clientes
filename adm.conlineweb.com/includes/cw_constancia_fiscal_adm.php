<?php
/**
 * Puente admin → módulo compartido de constancia fiscal.
 * Toda la lógica vive en cw_constancia_fiscal.php (idéntico en adm y cliente).
 */
require_once __DIR__ . '/cw_constancia_fiscal.php';

function cw_constancia_fiscal_adm_cliente_file_url(string $filename): string
{
    return cw_constancia_fiscal_public_url($filename);
}

function cw_constancia_fiscal_adm_public_url(string $filename): string
{
    return cw_constancia_fiscal_public_url($filename);
}

function cw_constancia_fiscal_adm_parse(?string $stored): ?array
{
    return cw_constancia_fiscal_parse($stored);
}

/**
 * Misma función de guardado que el portal cliente.
 * Escribe en adm/.../constancias_fiscales/ (carpeta sincronizada con cliente).
 */
function cw_constancia_fiscal_adm_save_upload(array $file, int $clientId = 0): array
{
    return cw_constancia_fiscal_save_upload($clientId, $file);
}
