<?php
// Configuraciиоn para enlaces seguros de vista de tickets.
// IMPORTANTE: Cambia este secreto por uno largo y privado. No lo compartas.
// Puedes generarlo con: php -r "echo bin2hex(random_bytes(32));"
if(!defined('LINK_SECRET')) {
    // 32 bytes aleatorios en hex (64 chars). Generado con random_bytes.
    define('LINK_SECRET', '3f9a0c8d7b42f6e1a4d915bc2e88f4a7d6130b5c9ef27aa0d4c6b1e2837fd950');
}
// Duraciиоn mивxima en segundos de un enlace (30 dикas por defecto)
if(!defined('LINK_MAX_AGE')) {
    define('LINK_MAX_AGE', 30*24*3600);
}
