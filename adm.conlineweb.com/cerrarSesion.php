<?php
define('AUTH_MIDDLEWARE_FUNCTIONS_ONLY', true);

require_once __DIR__ . '/auth_middleware.php';

destroySession();

$logoutUrl = LOGIN_URL;
$separator = (strpos($logoutUrl, '?') !== false) ? '&' : '?';
header('Location: ' . $logoutUrl . $separator . 'logout=1');
exit();
