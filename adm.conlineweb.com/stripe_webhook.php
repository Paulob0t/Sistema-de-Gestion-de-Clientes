<?php
/**
 * Webhook Stripe (live) — ACK rápido 2xx y luego acredita pagos.
 *
 * URL Dashboard: https://adm.conlineweb.com/stripe_webhook.php
 * Secret: STRIPE_LIVE_WEBHOOK_SECRET / STRIPE_TEST_WEBHOOK_SECRET
 *         en ~/.stripe_credentials_cw (fuera del web root)
 *
 * Eventos que acreditan:
 * - checkout.session.completed
 * - checkout.session.async_payment_succeeded
 *
 * El resto se acusa recibido (200) para que Stripe no marque el endpoint como caído.
 */
declare(strict_types=1);

@ini_set('display_errors', '0');
@ini_set('zlib.output_compression', '0');
ignore_user_abort(true);
date_default_timezone_set('America/Mexico_City');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/stripe-php/init.php';
require_once __DIR__ . '/includes/cw_pago_renovacion.php';

$logFile = __DIR__ . '/stripe_webhook.log';
$log = static function (string $message) use ($logFile): void {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    @file_put_contents($logFile, $line, FILE_APPEND);
};

$respond = static function (int $code, array $body): void {
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
};

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
    $respond(200, ['ok' => true, 'endpoint' => 'stripe_webhook', 'ready' => true]);
    exit;
}

try {
    $payload = file_get_contents('php://input');
    if ($payload === false || $payload === '') {
        $respond(400, ['ok' => false, 'error' => 'empty_payload']);
        exit;
    }

    $sigHeader = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
    if ($sigHeader === '') {
        $respond(400, ['ok' => false, 'error' => 'missing_signature']);
        exit;
    }

    $secrets = cw_stripe_webhook_secrets();
    if ($secrets === []) {
        $log('ERROR: ningún STRIPE_*_WEBHOOK_SECRET en .stripe_credentials_cw');
        $respond(500, ['ok' => false, 'error' => 'webhook_secret_missing']);
        exit;
    }

    $event = null;
    $lastSigError = '';
    foreach ($secrets as $secret) {
        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $secret);
            break;
        } catch (\UnexpectedValueException $e) {
            $log('Payload inválido: ' . $e->getMessage());
            $respond(400, ['ok' => false, 'error' => 'invalid_payload']);
            exit;
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            $lastSigError = $e->getMessage();
        }
    }

    if ($event === null) {
        $log('Firma inválida con ' . count($secrets) . ' secretos: ' . $lastSigError);
        $respond(400, ['ok' => false, 'error' => 'invalid_signature']);
        exit;
    }

    $type = (string) $event->type;
    $eventId = (string) $event->id;
    $log('Evento recibido: ' . $type . ' id=' . $eventId);

    // Responder YA. Stripe da ~5s; el trabajo de BD va después.
    $respond(200, ['ok' => true, 'received' => true, 'id' => $eventId, 'type' => $type]);
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        if (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        }
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();
    }

    $ignoreTypes = [
        'checkout.session.async_payment_failed',
        'checkout.session.expired',
    ];
    if (in_array($type, $ignoreTypes, true)) {
        $session = $event->data->object;
        $log('Evento informativo ' . $type . ' session=' . ($session->id ?? ''));
        exit;
    }

    $processTypes = [
        'checkout.session.completed',
        'checkout.session.async_payment_succeeded',
    ];
    if (!in_array($type, $processTypes, true)) {
        exit;
    }

    /** @var \Stripe\Checkout\Session $session */
    $session = $event->data->object;
    $sessionId = (string) ($session->id ?? '');
    $paymentStatus = (string) ($session->payment_status ?? '');

    if ($sessionId === '') {
        $log('Session sin id event=' . $eventId);
        exit;
    }

    if ($type === 'checkout.session.completed' && $paymentStatus !== 'paid') {
        $log("Session {$sessionId} completed con payment_status={$paymentStatus}; se espera async_payment_succeeded");
        exit;
    }

    if ($paymentStatus !== 'paid' && $type !== 'checkout.session.async_payment_succeeded') {
        $log("Session {$sessionId} no paid ({$paymentStatus})");
        exit;
    }

    require_once __DIR__ . '/conn.php';
    $connections = [];
    if (isset($conn) && $conn instanceof mysqli) {
        $connections['conlineweb'] = $conn;
    }
    if (is_file(__DIR__ . '/conn_hostingpro.php')) {
        require_once __DIR__ . '/conn_hostingpro.php';
        if (isset($conn_hp) && $conn_hp instanceof mysqli) {
            $connections['hostingpro'] = $conn_hp;
        }
    }

    $foundDb = null;
    $pagos = [];
    foreach ($connections as $label => $db) {
        $pagos = cw_pagos_por_checkout_session($db, $sessionId, $session);
        if ($pagos !== []) {
            $foundDb = $label;
            $conn = $db;
            break;
        }
    }

    if ($pagos === [] || !($conn instanceof mysqli)) {
        $log("No hay filas en pagos para session_id={$sessionId} (otro producto Stripe o ya no aplica)");
        exit;
    }

    $log('DB=' . $foundDb . ' pagos=' . count($pagos) . ' session=' . $sessionId);

    $conn->begin_transaction();
    try {
        $result = cw_acreditar_pagos_checkout($conn, $pagos);
        if (!empty($result['errors'])) {
            $log('Errores renovación: ' . implode(' | ', $result['errors']));
        }
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }

    $log(sprintf(
        'OK session=%s newly_paid=%s already_paid=%s renewed=%d errors=%d',
        $sessionId,
        implode(',', $result['newly_paid']),
        implode(',', $result['already_paid']),
        count($result['renewed']),
        count($result['errors'])
    ));
} catch (Throwable $e) {
    $log('FATAL: ' . $e->getMessage());
    if (!headers_sent()) {
        $respond(500, ['ok' => false, 'error' => 'server_error']);
    }
}
