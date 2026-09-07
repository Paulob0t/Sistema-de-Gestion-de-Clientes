<?php
/**
 * Prueba local del router de cuenta del chatbot.
 * Uso: php chat/test_bot_account.php [cliente_id] ["pregunta"]
 */
require_once dirname(__DIR__) . '/conn.php';
require_once dirname(__DIR__) . '/includes/cw_chat_migrate.php';
require_once dirname(__DIR__) . '/includes/cw_chatbot_service.php';

cw_chat_migrate($conn);

$clienteId = (int) ($argv[1] ?? 0);
$pregunta = $argv[2] ?? 'cuando vence el hosting';

if ($clienteId <= 0) {
    $r = $conn->query('SELECT h.cliente_id, COUNT(*) c FROM hosting h WHERE h.eliminado=0 GROUP BY h.cliente_id ORDER BY c DESC LIMIT 1');
    if ($r && $row = $r->fetch_assoc()) {
        $clienteId = (int) $row['cliente_id'];
    }
}

$chk = $conn->prepare('SELECT id, nombre_contacto FROM clientes WHERE id = ? LIMIT 1');
$chk->bind_param('i', $clienteId);
$chk->execute();
$chkRow = $chk->get_result()->fetch_assoc();
echo "direct clientes query: " . ($chkRow ? json_encode($chkRow) : 'NULL') . "\n";
echo "Pregunta: {$pregunta}\n\n";

$ctx = cw_chat_client_context($conn, $clienteId);
echo "Hosting rows: " . count($ctx['hosting']) . "\n";
echo "Dominios rows: " . count($ctx['dominios']) . "\n";
echo "cliente encontrado: " . (!empty($ctx['cliente']) ? 'YES (' . ($ctx['cliente']['nombre_contacto'] ?? '') . ')' : 'NO') . "\n";
if (!empty($ctx['hosting'][0])) {
    $h = $ctx['hosting'][0];
    echo "Sample hosting: {$h['dominio']} fecha_pago={$h['fecha_pago']} dias=" . ($h['dias_restantes'] ?? '?') . "\n";
}
if (!empty($ctx['dominios'][0])) {
    $d = $ctx['dominios'][0];
    echo "Sample dominio: {$d['dominio']} registrado=" . ($d['registrado'] ?? '?') . " fecha_pago={$d['fecha_pago']} dias=" . ($d['dias_restantes'] ?? '?') . "\n";
}
echo "\n";

$m = mb_strtolower(trim($pregunta));
echo "should_handle: " . (cw_chatbot_live_should_handle($m, [], false) ? 'YES' : 'NO') . "\n";
$topic = cw_chatbot_live_detect_topic($m, [], false);
echo "detect_topic: " . ($topic ?? 'NULL') . "\n";

if ($topic === 'dominios') {
    $direct = cw_chatbot_live_build_dominios_vence($ctx, null);
    echo "direct build matched: " . (!empty($direct['matched']) ? 'YES' : 'NO') . "\n";
    echo "direct respuesta: " . substr($direct['respuesta'] ?? '', 0, 120) . "\n";
}

$live = cw_chatbot_try_live_response($conn, $clienteId, $m, [], false);
echo "live matched: " . (!empty($live['matched']) ? 'YES' : 'NO') . "\n";
echo "intencion: " . ($live['intencion'] ?? '-') . "\n";
echo "origen: " . ($live['origen'] ?? '-') . "\n";
echo "--- RESPUESTA ---\n";
echo ($live['respuesta'] ?? '(sin respuesta)') . "\n";
