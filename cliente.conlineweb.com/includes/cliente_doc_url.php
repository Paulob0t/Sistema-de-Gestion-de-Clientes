<?php
/**
 * URL del Centro de Documentación público (abre en nueva pestaña desde el portal).
 */
function cliente_doc_url(): string
{
    $host = strtolower($_SERVER['HTTP_HOST'] ?? 'localhost');
    if ($host === 'localhost' || str_starts_with($host, '127.0.0.1') || str_starts_with($host, 'localhost:')) {
        return 'http://' . $host . '/sistemasconlineweb/conlineweb.com/documentacion/';
    }
    return 'https://conlineweb.com/documentacion/';
}
