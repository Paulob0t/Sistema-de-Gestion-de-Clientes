<?php


// ============================================================
// CONFIGURACIÓN
// ============================================================

/** Token secreto para llamadas HTTP. Cámbialo por uno seguro. */
define('CRON_SECRET', 'cron_leads_wa_2025_secret');

/** ContentSID de la plantilla aprobada en Twilio (mismo que enviar_mensaje_plantilla.php) */
define('TEMPLATE_CONTENT_SID', 'HXb4f3d05dccbda05a03321376294e1d43');

/** ¿La plantilla usa variables {{1}}, {{2}}, etc.? */
define('TEMPLATE_TIENE_VARIABLES', false);

/** Máximo de leads a procesar por ejecución (evita superar rate limits de Twilio) */
define('MAX_LEADS_POR_CICLO', 20);

/** Segundos de espera entre envíos (evita saturar la API de Twilio) */
define('DELAY_ENTRE_ENVIOS', 1);

/**
 * MODO PRUEBA
 * - Cuando es true, solo se procesa el teléfono indicado en TEST_PHONE.
 * - No se actualiza whatsapp_enviado en la BD (puedes repetir la prueba).
 * - Cambia a false para producción.
 */
define('TEST_MODE',  true);
define('TEST_PHONE', '+524775579264');

/** Ruta al directorio de sesiones de WhatsApp */
define('SESSIONS_DIR', __DIR__ . '/../whatsapp/sessions');

// ============================================================
// SEGURIDAD: Solo CLI o token correcto
// ============================================================
$isCli = (php_sapi_name() === 'cli');
$tokenOk = isset($_GET['token']) && is_string($_GET['token']) && hash_equals(CRON_SECRET, $_GET['token']);

if (!$isCli && !$tokenOk) {
    http_response_code(403);
    exit("Acceso no autorizado\n");
}

// ============================================================
// INICIALIZACIÓN
// ============================================================
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Mostrar salida en el navegador cuando se llama por HTTP
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

$logFile = __DIR__ . '/cron_wa_plantilla.log';

function cron_log(string $msg): void
{
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    // Mostrar siempre: tanto en CLI como en navegador
    echo $line;
    if (php_sapi_name() !== 'cli') {
        ob_flush();
        flush();
    }
}

require_once __DIR__ . '/../conn.php';

if (!$conn || $conn->connect_error) {
    cron_log('ERROR: No se pudo conectar a la base de datos.');
    exit(1);
}

$conn->set_charset('utf8mb4');

// ============================================================
// AUTO-MIGRACIÓN: Añadir columnas si no existen
// ============================================================
$checkCol = $conn->query("SHOW COLUMNS FROM leads LIKE 'whatsapp_enviado'");
if ($checkCol && $checkCol->num_rows === 0) {
    cron_log('Añadiendo columnas whatsapp_enviado y whatsapp_enviado_fecha a la tabla leads...');
    $conn->query("ALTER TABLE leads ADD COLUMN whatsapp_enviado TINYINT(1) NOT NULL DEFAULT 0");
    $conn->query("ALTER TABLE leads ADD COLUMN whatsapp_enviado_fecha DATETIME NULL DEFAULT NULL");
    if ($conn->error) {
        cron_log('ERROR al hacer ALTER TABLE: ' . $conn->error);
        exit(1);
    }
    cron_log('Columnas agregadas correctamente.');
}

// ============================================================
// CREDENCIALES TWILIO
// ============================================================
require_once dirname(__DIR__) . '/includes/cw_twilio_config.php';
$__tw = cw_twilio_config();
$TWILIO_ACCOUNT_SID = $__tw['sid'];
$TWILIO_AUTH_TOKEN  = $__tw['token'];
$TWILIO_FROM        = $__tw['from'];
if (!defined('TEMPLATE_CONTENT_SID')) {
    define('TEMPLATE_CONTENT_SID', $__tw['template_sid']);
}

// ============================================================
// FUNCIONES AUXILIARES
// ============================================================

/**
 * Normaliza un número de teléfono al formato E.164 para WhatsApp México.
 * Devuelve false si el número es inválido.
 */
function normalizar_telefono(string $telefono)
{
    $startsWithPlus = strlen($telefono) > 0 && $telefono[0] === '+';
    $digits = preg_replace('/\D+/', '', $telefono);

    if ($digits === '' || strlen($digits) < 7) {
        return false;
    }

    // 10 dígitos sin código de país → México
    if (!$startsWithPlus && strlen($digits) === 10) {
        $digits = '521' . $digits;
    }
    // 52 + 10 dígitos → falta el 1 de WhatsApp MX
    elseif (preg_match('/^52\d{10}$/', $digits)) {
        $digits = '521' . substr($digits, 2);
    }
    // 521 + 10 dígitos → formato correcto WhatsApp MX
    elseif (preg_match('/^521\d{10}$/', $digits)) {
        // ya correcto
    }
    // Otro formato MX con dígitos de más
    elseif (preg_match('/^52\d{11,}$/', $digits)) {
        $digits = '521' . substr($digits, -10);
    }
    // Cualquier otro código de país → confiar en el número

    if (strlen($digits) < 7 || strlen($digits) > 15) {
        return false;
    }

    return [
        'e164'   => '+' . $digits,
        'digits' => $digits,
    ];
}

/**
 * Envía la plantilla aprobada de Twilio a un número en formato E.164.
 * Devuelve ['ok' => true] o ['ok' => false, 'error' => '...']
 */
function enviar_plantilla_twilio(
    string $sid,
    string $token,
    string $from,
    string $toE164,
    string $nombreCliente = ''
): array {
    $postFields = [
        'From'       => $from,
        'To'         => 'whatsapp:' . $toE164,
        'ContentSid' => TEMPLATE_CONTENT_SID,
    ];

    if (TEMPLATE_TIENE_VARIABLES) {
        $variables = ['1' => $nombreCliente !== '' ? $nombreCliente : 'Cliente'];
        $postFields['ContentVariables'] = json_encode($variables, JSON_UNESCAPED_UNICODE);
    }

    $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($postFields),
        CURLOPT_USERPWD        => $sid . ':' . $token,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $resp    = curl_exec($ch);
    $http    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr !== '') {
        return ['ok' => false, 'error' => 'cURL: ' . $curlErr];
    }

    // Loguear siempre la respuesta completa de Twilio para diagnóstico
    error_log("[cron_leads] Twilio HTTP {$http} → " . $resp);
    cron_log("  [Twilio] HTTP {$http} | Respuesta: " . $resp);

    if ($http < 200 || $http >= 300) {
        $detail = '';
        $decoded = json_decode((string)$resp, true);
        if (is_array($decoded) && isset($decoded['message'])) {
            $detail = $decoded['message'];
        }
        return ['ok' => false, 'error' => "HTTP {$http}" . ($detail !== '' ? ': ' . $detail : '')];
    }

    return ['ok' => true];
}

/**
 * Registra el mensaje de plantilla en el archivo de sesión JSON de WhatsApp,
 * para que la conversación aparezca en whatsapp_conversacion.php.
 */
function registrar_en_sesion(string $digits, string $nombre): void
{
    $sessionsDir = SESSIONS_DIR;
    if (!is_dir($sessionsDir)) {
        @mkdir($sessionsDir, 0755, true);
    }

    $sessionFile = $sessionsDir . '/whatsapp_' . $digits . '.json';
    $metaFile    = $sessionsDir . '/whatsapp_' . $digits . '_meta.json';

    // Cargar historial existente o iniciar vacío
    $messages = [];
    if (is_file($sessionFile)) {
        $raw = @file_get_contents($sessionFile);
        $messages = json_decode((string)$raw, true);
        if (!is_array($messages)) {
            $messages = [];
        }
    }

    // Agregar el mensaje de plantilla enviado
    $messages[] = [
        'role'      => 'assistant',
        'content'   => '👋 [Plantilla de saludo enviada]',
        'timestamp' => time(),
        'template'  => true,
        'origen'    => 'cron_leads',
    ];

    @file_put_contents(
        $sessionFile,
        json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );

    // Actualizar meta
    $meta = [];
    if (is_file($metaFile)) {
        $raw = @file_get_contents($metaFile);
        $meta = json_decode((string)$raw, true);
        if (!is_array($meta)) {
            $meta = [];
        }
    }

    $meta['ultima_actualizacion']   = time();
    $meta['client_name']            = $nombre;
    // El bot puede responder cuando el lead conteste (no marcamos agent_active)
    if (!isset($meta['agent_active'])) {
        $meta['agent_active'] = false;
    }

    @file_put_contents(
        $metaFile,
        json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

// ============================================================
// PROCESO PRINCIPAL
// ============================================================
cron_log('=== Inicio del cron de plantillas WhatsApp para leads ===');

if (TEST_MODE) {
    cron_log('⚠️  MODO PRUEBA ACTIVO — solo se procesará: ' . TEST_PHONE);
    // Buscar el lead por teléfono (acepta con o sin el +52 / 521 delante)
    $testDigits = preg_replace('/\D+/', '', TEST_PHONE);
    $stmt = $conn->prepare(
        "SELECT id, nombre, telefono
         FROM leads
         WHERE eliminado = 0
           AND telefono IS NOT NULL
           AND telefono <> ''
           AND REPLACE(REPLACE(REPLACE(REPLACE(telefono,' ',''),'-',''),'(',''),')','') LIKE ?
         LIMIT 1"
    );
    if (!$stmt) {
        cron_log('ERROR al preparar la consulta de prueba: ' . $conn->error);
        exit(1);
    }
    $likePhone = '%' . substr($testDigits, -10); // últimos 10 dígitos
    $stmt->bind_param('s', $likePhone);
    $stmt->execute();
} else {
    $stmt = $conn->prepare(
        "SELECT id, nombre, telefono
         FROM leads
         WHERE eliminado = 0
           AND telefono IS NOT NULL
           AND telefono <> ''
           AND (whatsapp_enviado IS NULL OR whatsapp_enviado = 0)
         ORDER BY fecha_registro ASC
         LIMIT ?"
    );
    if (!$stmt) {
        cron_log('ERROR al preparar la consulta: ' . $conn->error);
        exit(1);
    }
    $limit = MAX_LEADS_POR_CICLO;
    $stmt->bind_param('i', $limit);
    $stmt->execute();
}
$result = $stmt->get_result();

$total      = $result->num_rows;
$enviados              = 0;
$fallidos              = 0;
$omitidos_conversacion = 0;  // ya habían iniciado conversación ellos
$omitidos_telefono     = 0;  // teléfono inválido

cron_log("Leads pendientes encontrados: {$total}");

while ($lead = $result->fetch_assoc()) {
    $id     = (int)$lead['id'];
    $nombre = (string)$lead['nombre'];
    $tel    = (string)$lead['telefono'];

    $normalizado = normalizar_telefono($tel);

    if ($normalizado === false) {
        cron_log("Lead #{$id} ({$nombre}): ⚠️  OMITIDO — teléfono inválido '{$tel}'. Corrija el número en la BD para que pueda enviarse.");
        $omitidos_telefono++;
        continue;
    }

    $e164   = $normalizado['e164'];
    $digits = $normalizado['digits'];

    // -------------------------------------------------------
    // Verificar si ya existe una conversación activa para este número.
    // Si el lead ya escribió primero, el bot ya está respondiendo;
    // no tiene sentido enviar la plantilla de saludo encima.
    // -------------------------------------------------------
    $sessionFile = SESSIONS_DIR . '/whatsapp_' . $digits . '.json';
    if (is_file($sessionFile)) {
        $rawSession = @file_get_contents($sessionFile);
        $existingMessages = json_decode((string)$rawSession, true);
        if (is_array($existingMessages)) {
            $hasUserMessages = false;
            foreach ($existingMessages as $msg) {
                if (isset($msg['role']) && $msg['role'] === 'user') {
                    $hasUserMessages = true;
                    break;
                }
            }
            if ($hasUserMessages) {
                cron_log("Lead #{$id} ({$nombre}): ⏭️  OMITIDO — ya tiene conversación activa en {$e164} (el lead escribió primero; el bot ya está atendiendo). Se marca como enviado.");
                // Marcar como enviado para no volver a procesar en cada ciclo
                $upd = $conn->prepare("UPDATE leads SET whatsapp_enviado = 1, whatsapp_enviado_fecha = NOW() WHERE id = ?");
                if ($upd) {
                    $upd->bind_param('i', $id);
                    $upd->execute();
                    $upd->close();
                }
                $omitidos_conversacion++;
                continue;
            }
        }
    }

    cron_log("Lead #{$id} ({$nombre}): enviando plantilla a {$e164}...");

    $envioResult = enviar_plantilla_twilio(
        $TWILIO_ACCOUNT_SID,
        $TWILIO_AUTH_TOKEN,
        $TWILIO_FROM,
        $e164,
        $nombre
    );

    if (!$envioResult['ok']) {
        cron_log("Lead #{$id} ({$nombre}): ❌ ERROR Twilio al enviar a {$e164} — " . $envioResult['error']);
        $fallidos++;
        // Esperamos igual para no abusar de la API
        if (DELAY_ENTRE_ENVIOS > 0) {
            sleep(DELAY_ENTRE_ENVIOS);
        }
        continue;
    }

    // Registro en los archivos de sesión (visible en whatsapp_conversacion.php)
    registrar_en_sesion($digits, $nombre);

    // Marcar lead como enviado en la BD (solo en producción)
    if (!TEST_MODE) {
        $upd = $conn->prepare(
            "UPDATE leads SET whatsapp_enviado = 1, whatsapp_enviado_fecha = NOW() WHERE id = ?"
        );
        if ($upd) {
            $upd->bind_param('i', $id);
            $upd->execute();
            $upd->close();
        }
        cron_log("Lead #{$id} ({$nombre}): ✓ Plantilla enviada y registrada.");
    } else {
        cron_log("Lead #{$id} ({$nombre}): ✓ [TEST] Plantilla enviada (BD NO actualizada).");
    }
    $enviados++;

    if (DELAY_ENTRE_ENVIOS > 0) {
        sleep(DELAY_ENTRE_ENVIOS);
    }
}

$stmt->close();

cron_log('=== Resumen final =================================');
cron_log("  Total procesados :  {$total}");
cron_log("  ✓ Enviados        :  {$enviados}  ← plantilla enviada correctamente");
cron_log("  ⏭️ Omitidos (conv.) :  {$omitidos_conversacion}  ← el lead ya había escrito primero; el bot ya los atiende");
cron_log("  ⚠️  Omitidos (tel.)  :  {$omitidos_telefono}  ← teléfono inválido o en formato no reconocido; corregir en BD");
cron_log("  ❌ Fallidos (API)   :  {$fallidos}  ← error al llamar a Twilio; revisar credenciales o límites");
cron_log('==================================================');
exit(0);
