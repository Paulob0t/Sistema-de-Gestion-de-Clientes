<?php
/**
 * Tracking público: apertura (pixel), clic y baja.
 * Sin autenticación — solo token opaco.
 */
declare(strict_types=1);

header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex');

require_once dirname(__DIR__) . '/conn.php';
require_once dirname(__DIR__) . '/includes/adm_paths.php';
require_once dirname(__DIR__) . '/includes/cw_mailing_service.php';

if (!($conn instanceof mysqli)) {
    http_response_code(503);
    exit;
}

cw_mailing_migrate($conn);

$event = strtolower(trim((string) ($_GET['e'] ?? '')));
$token = trim((string) ($_GET['t'] ?? ''));
$encodedUrl = trim((string) ($_GET['u'] ?? ''));

$result = cw_mailing_handle_track($conn, $event, $token, $encodedUrl);

if ($event === 'open' || !empty($result['pixel'])) {
    header('Content-Type: image/gif');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    // GIF 1x1 transparente
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}

$redirect = (string) ($result['redirect'] ?? 'https://conlineweb.com/');
if (!preg_match('#^https?://#i', $redirect)) {
    $redirect = 'https://conlineweb.com/';
}

header('Location: ' . $redirect, true, 302);
exit;
