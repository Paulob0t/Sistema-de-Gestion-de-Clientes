<?php
/**
 * Override opcional de la clave OpenAI en producción.
 * Si no existe, se usa openai_config.defaults.php automáticamente.
 */
if (!defined('OPENAI_API_KEY')) {
    require_once __DIR__ . '/openai_config.defaults.php';
    define('OPENAI_API_KEY', CW_OPENAI_API_KEY_DEFAULT);
}
