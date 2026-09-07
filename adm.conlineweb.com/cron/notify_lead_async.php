<?php
/**
 * Notificación asíncrona de lead (local/MAMP sin fastcgi_finish_request).
 * Uso: php notify_lead_async.php <lead_id>
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$leadId = isset($argv[1]) ? (int) $argv[1] : 0;
if ($leadId <= 0) {
    fwrite(STDERR, "lead_id requerido\n");
    exit(1);
}

require_once dirname(__DIR__) . '/conn.php';
require_once dirname(__DIR__) . '/includes/cw_hub_notify.php';

$stmt = $conn->prepare('SELECT id, nombre, apellido, correo, telefono, empresa, servicio, requerimiento, pagina_origen, pipeline_estado, fuente FROM leads WHERE id = ? AND origen_web = 1 LIMIT 1');
if (!$stmt) {
    fwrite(STDERR, "prepare failed\n");
    exit(1);
}
$stmt->bind_param('i', $leadId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    fwrite(STDERR, "lead no encontrado\n");
    exit(1);
}

$nombre = trim((string) (($row['nombre'] ?? '') . ' ' . ($row['apellido'] ?? '')));
$lead = [
    'nombre' => $nombre !== '' ? $nombre : (string) ($row['nombre'] ?? ''),
    'apellido' => (string) ($row['apellido'] ?? ''),
    'correo' => (string) ($row['correo'] ?? ''),
    'telefono' => (string) ($row['telefono'] ?? ''),
    'empresa' => (string) ($row['empresa'] ?? ''),
    'servicio' => (string) ($row['servicio'] ?? ''),
    'requerimiento' => (string) ($row['requerimiento'] ?? ''),
    'pagina_origen' => (string) ($row['pagina_origen'] ?? ''),
    'pipeline_estado' => (string) ($row['pipeline_estado'] ?? 'lead'),
    'fuente' => (string) ($row['fuente'] ?? 'website'),
];

try {
    cw_hub_notify_web_lead_submission($conn, $leadId, $lead, true);
} catch (Throwable $e) {
    fwrite(STDERR, 'notify: ' . $e->getMessage() . "\n");
}

$correo = trim($lead['correo']);
if ($correo !== '' && function_exists('cw_hub_send_client_confirmation')) {
    try {
        $wa = 'https://wa.me/' . (defined('CW_HUB_WHATSAPP') ? CW_HUB_WHATSAPP : '5214771181285');
        cw_hub_send_client_confirmation($conn, $leadId, $correo, $lead, $wa);
    } catch (Throwable $e) {
        fwrite(STDERR, 'client mail: ' . $e->getMessage() . "\n");
    }
}

echo "ok lead #{$leadId}\n";
