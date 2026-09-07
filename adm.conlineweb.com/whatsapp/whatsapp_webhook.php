<?php
header("Content-Type: text/xml");

// Keep PHP running even if Twilio closes the webhook connection
// (tool-call flows can take >15s — Twilio's webhook timeout)
ignore_user_abort(true);
set_time_limit(120);

// Twilio credentials (used by twilio_rest_send for reliable outbound delivery)
define('TWILIO_ACCOUNT_SID', getenv('TWILIO_ACCOUNT_SID') ?: 'ACe545cc9bfdfb41f417c8e1cc34062678');
define('TWILIO_AUTH_TOKEN',  getenv('TWILIO_AUTH_TOKEN')  ?: '6068c519f69979eefe00f63d61ad25a8');
define('TWILIO_WHATSAPP_FROM', 'whatsapp:+15557419621');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require "../PHPMailer/src/Exception.php";
require "../PHPMailer/src/PHPMailer.php";
require "../PHPMailer/src/SMTP.php";

require_once __DIR__ . '/../includes/openai_config.php';
$OPENAI_API_KEY = OPENAI_API_KEY;

// Función para enviar correos con PHPMailer
function enviarCorreo($correo_destino, $asunto, $titulo, $cuerpo, $despedida) {
    require_once dirname(__DIR__) . "/includes/adm_email_template.php";
    $mensaje = adm_email_message($titulo, $cuerpo, $despedida);

    $mail = new PHPMailer(true);
    $result = ['success' => false];

    try {
        // Configuración del servidor SMTP
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'servicios@conlineweb.com';
        $mail->Password = 'wcglkgcxfebsauqo';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Configuración del correo
        $mail->setFrom('servicios@conlineweb.com', 'Conlineweb');
        $mail->addAddress($correo_destino);
        $mail->Subject = $asunto;
        $mail->isHTML(true);
        $mail->Body = $mensaje;
        $mail->CharSet = 'UTF-8';

        // Enviar correo
        $mail->send();
        $result['success'] = true;
        $result['message'] = 'Correo enviado correctamente';
        
    } catch (Exception $e) {
        $result['error'] = "Error al enviar el correo: " . $e->getMessage();
        $result['debug'] = $mail->ErrorInfo;
    }

    return $result;
}

// Obtenemos el teléfono real de quien escribe por WhatsApp
$from_whatsapp_number_raw = preg_replace('/[^0-9]/', '', $_POST['From'] ?? '');

// Normaliza números de WhatsApp a E.164 (en MX fuerza +521 cuando aplique)
function normalize_whatsapp_phone_e164($digits)
{
    $digits = preg_replace('/\D+/', '', (string) $digits);
    if ($digits === '')
        return '';

    // MX: 52 + 10 dígitos -> +521 + 10 dígitos
    if (strpos($digits, '52') === 0 && strlen($digits) === 12) {
        return '+521' . substr($digits, 2);
    }
    // MX ya correcto: 521 + 10 dígitos
    if (strpos($digits, '521') === 0 && strlen($digits) === 13) {
        return '+' . $digits;
    }
    // MX local 10 dígitos
    if (strlen($digits) === 10) {
        return '+521' . $digits;
    }
    // US/CA típico
    if (strlen($digits) === 11 && strpos($digits, '1') === 0) {
        return '+' . $digits;
    }

    // Fallback: anteponer +
    return '+' . $digits;
}

function e164_to_digits($e164)
{
    return preg_replace('/\D+/', '', (string) $e164);
}

// Twilio WhatsApp: max 1600 chars per <Message>.
// Splits text on paragraph/newline boundaries and outputs multiple <Message> tags.
function twiml_send_message($text, $maxLen = 1580) {
    $text = trim($text);
    if (mb_strlen($text) <= $maxLen) {
        echo "<Message>" . htmlspecialchars($text, ENT_XML1, 'UTF-8') . "</Message>";
        return;
    }
    // Split on double-newline (paragraphs), fallback to single newline
    $paragraphs = preg_split('/\n{2,}/', $text);
    $chunk = '';
    foreach ($paragraphs as $para) {
        $para = trim($para);
        if ($para === '') continue;
        $candidate = $chunk === '' ? $para : $chunk . "\n\n" . $para;
        if (mb_strlen($candidate) <= $maxLen) {
            $chunk = $candidate;
        } else {
            if ($chunk !== '') {
                echo "<Message>" . htmlspecialchars($chunk, ENT_XML1, 'UTF-8') . "</Message>";
            }
            // If single paragraph exceeds limit, split by line
            if (mb_strlen($para) > $maxLen) {
                $lines = explode("\n", $para);
                $chunk = '';
                foreach ($lines as $line) {
                    $cand2 = $chunk === '' ? $line : $chunk . "\n" . $line;
                    if (mb_strlen($cand2) <= $maxLen) {
                        $chunk = $cand2;
                    } else {
                        if ($chunk !== '') {
                            echo "<Message>" . htmlspecialchars($chunk, ENT_XML1, 'UTF-8') . "</Message>";
                        }
                        $chunk = $line;
                    }
                }
            } else {
                $chunk = $para;
            }
        }
    }
    if ($chunk !== '') {
        echo "<Message>" . htmlspecialchars($chunk, ENT_XML1, 'UTF-8') . "</Message>";
    }
}

/**
 * Send a WhatsApp message via Twilio REST API.
 * Reliable for long-running webhooks where Twilio may have already closed the TwiML connection.
 * Splits messages longer than $maxLen chars automatically.
 */
function twilio_rest_send(string $toE164, string $text, int $maxLen = 1580): bool {
    $text = markdownToWhatsApp(trim($text));
    $to   = 'whatsapp:+' . ltrim(preg_replace('/\D+/', '', $toE164), '+');

    // Build chunks (same logic as twiml_send_message)
    $chunks = [];
    if (mb_strlen($text) <= $maxLen) {
        $chunks[] = $text;
    } else {
        $paragraphs = preg_split('/\n{2,}/', $text);
        $chunk = '';
        foreach ($paragraphs as $para) {
            $para = trim($para);
            if ($para === '') continue;
            $candidate = $chunk === '' ? $para : $chunk . "\n\n" . $para;
            if (mb_strlen($candidate) <= $maxLen) {
                $chunk = $candidate;
            } else {
                if ($chunk !== '') { $chunks[] = $chunk; }
                if (mb_strlen($para) > $maxLen) {
                    $lines = explode("\n", $para);
                    $chunk = '';
                    foreach ($lines as $line) {
                        $cand2 = $chunk === '' ? $line : $chunk . "\n" . $line;
                        if (mb_strlen($cand2) <= $maxLen) {
                            $chunk = $cand2;
                        } else {
                            if ($chunk !== '') { $chunks[] = $chunk; }
                            $chunk = $line;
                        }
                    }
                } else {
                    $chunk = $para;
                }
            }
        }
        if ($chunk !== '') { $chunks[] = $chunk; }
    }

    $url   = 'https://api.twilio.com/2010-04-01/Accounts/' . TWILIO_ACCOUNT_SID . '/Messages.json';
    $allOk = true;
    foreach ($chunks as $body) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_USERPWD        => TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN,
            CURLOPT_POSTFIELDS     => http_build_query([
                'From' => TWILIO_WHATSAPP_FROM,
                'To'   => $to,
                'Body' => $body,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode < 200 || $httpCode >= 300) {
            error_log('[WhatsApp Webhook] ❌ twilio_rest_send failed. HTTP: ' . $httpCode . ' | ' . substr((string)$resp, 0, 200));
            $allOk = false;
        } else {
            error_log('[WhatsApp Webhook] ✅ twilio_rest_send OK. HTTP: ' . $httpCode);
        }
    }
    return $allOk;
}

$from_whatsapp_e164 = normalize_whatsapp_phone_e164($from_whatsapp_number_raw);
$from_whatsapp_number = e164_to_digits($from_whatsapp_e164);
$rawUserMessage = trim($_POST['Body'] ?? '');
$userMessage = $rawUserMessage;

$sessionDir = __DIR__ . '/sessions';
if (!is_dir($sessionDir)) {
    mkdir($sessionDir, 0777, true);
}

$sessionFile = "{$sessionDir}/whatsapp_{$from_whatsapp_number}.json";
$legacyDigits = $from_whatsapp_number_raw;
$legacySessionFile = "{$sessionDir}/whatsapp_{$legacyDigits}.json";
$loadedFromLegacySession = false;

// 1. CARGAR HISTORIAL
$messages = [];
if (file_exists($sessionFile)) {
    $messages = json_decode(file_get_contents($sessionFile), true) ?: [];
} elseif ($legacyDigits !== '' && $legacyDigits !== $from_whatsapp_number && file_exists($legacySessionFile)) {
    $messages = json_decode(file_get_contents($legacySessionFile), true) ?: [];
    $loadedFromLegacySession = true;
}

// Validar y limpiar mensajes incompletos (tool_calls sin respuesta)
if (!empty($messages)) {
    $cleanedMessages = [];
    $expectingToolResponse = false;
    $expectedToolCallIds = [];
    
    foreach ($messages as $msg) {
        // Si esperamos tool responses y no es uno, algo salió mal
        if ($expectingToolResponse && $msg['role'] !== 'tool') {
            error_log("[WhatsApp Webhook] ⚠️ Limpiando historial: se encontró tool_calls sin respuesta. Removiendo mensajes incompletos.");
            // Remover el último mensaje (el assistant con tool_calls)
            array_pop($cleanedMessages);
            $expectingToolResponse = false;
            $expectedToolCallIds = [];
        }
        
        // Si es assistant con tool_calls, marcar que esperamos tool responses
        if ($msg['role'] === 'assistant' && !empty($msg['tool_calls'])) {
            $expectingToolResponse = true;
            $expectedToolCallIds = array_map(function($tc) { return $tc['id']; }, $msg['tool_calls']);
            $cleanedMessages[] = $msg;
            continue;
        }
        
        // Si es tool response, verificar que corresponda a los IDs esperados
        if ($msg['role'] === 'tool') {
            if ($expectingToolResponse && !empty($msg['tool_call_id'])) {
                $key = array_search($msg['tool_call_id'], $expectedToolCallIds);
                if ($key !== false) {
                    unset($expectedToolCallIds[$key]);
                }
            }
            $cleanedMessages[] = $msg;
            
            // Si ya recibimos todas las tool responses esperadas
            if (empty($expectedToolCallIds)) {
                $expectingToolResponse = false;
            }
            continue;
        }
        
        // Mensaje normal
        $cleanedMessages[] = $msg;
    }
    
    // Si terminamos el loop esperando tool responses, remover el último assistant
    if ($expectingToolResponse && !empty($expectedToolCallIds)) {
        error_log("[WhatsApp Webhook] ⚠️ Limpiando historial: tool_calls incompletos al final del historial. IDs faltantes: " . implode(', ', $expectedToolCallIds));
        // Buscar y remover el último assistant con tool_calls
        for ($i = count($cleanedMessages) - 1; $i >= 0; $i--) {
            if ($cleanedMessages[$i]['role'] === 'assistant' && !empty($cleanedMessages[$i]['tool_calls'])) {
                array_splice($cleanedMessages, $i, 1);
                break;
            }
        }
    }
    
    $messages = $cleanedMessages;
}

// --- VERIFICAR SI UN AGENTE HUMANO ESTÁ ACTIVO ---
$sessionMetaFile = "{$sessionDir}/whatsapp_{$from_whatsapp_number}_meta.json";
$agentActive = false;
$agentLastInteraction = 0;

if (file_exists($sessionMetaFile)) {
    $metaRaw = @file_get_contents($sessionMetaFile);
    $metaData = json_decode($metaRaw, true) ?: [];
    
    if (isset($metaData['agent_active']) && $metaData['agent_active'] === true) {
        $agentLastInteraction = $metaData['agent_last_interaction'] ?? 0;
        $timeSinceLastInteraction = time() - $agentLastInteraction;
        
        // Si el agente interactuó hace menos de 30 minutos, considerarlo activo
        if ($timeSinceLastInteraction < 1800) { // 30 minutos
            $agentActive = true;
            error_log("[WhatsApp Webhook] 👤 Agente humano activo. Bot NO responderá. Último contacto hace " . round($timeSinceLastInteraction / 60) . " minutos.");
        } else {
            // Si pasaron más de 30 minutos, desactivar el flag
            $metaData['agent_active'] = false;
            @file_put_contents($sessionMetaFile, json_encode($metaData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
            error_log("[WhatsApp Webhook] ⏰ Sesión de agente expirada. Bot retomará conversación.");
        }
    }
}

// Si un agente está activo, solo guardar el mensaje del usuario y salir
if ($agentActive) {
    $systemPrompt = file_exists('./prompt_efege.txt') ? file_get_contents('./prompt_efege.txt') : "Eres Alexa de Efege...";
    if (empty($messages) || $messages[0]['role'] !== 'system') {
        array_unshift($messages, ["role" => "system", "content" => $systemPrompt]);
    }
    $messages[] = ["role" => "user", "content" => $rawUserMessage];
    
    // Guardar el mensaje del usuario
    file_put_contents($sessionFile, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Responder con TwiML vacío (no enviar ningún mensaje)
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
    echo "<Response></Response>";
    exit;
}

// --- HANDOFF A AGENTE HUMANO ---
$handoffRegex = '/\b(hablar\s+con\s+agente|agente\s+humano|quiero\s+un\s+agente|pasame\s+con\s+un\s+agente|pasame\s+con\s+alguien)\b/i';
if ($rawUserMessage !== '' && preg_match($handoffRegex, $rawUserMessage)) {
    $sessionMetaFile = __DIR__ . "/sessions/whatsapp_" . preg_replace('/\D+/', '', $from_whatsapp_number) . "_meta.json";
    $meta = [];
    if (file_exists($sessionMetaFile)) {
        $mraw = @file_get_contents($sessionMetaFile);
        $meta = json_decode($mraw, true) ?: [];
    }
    $meta['human_handoff'] = true;
    $meta['human_handoff_requested_at'] = date('c');
    @file_put_contents($sessionMetaFile, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);

    $webhookUrl = "https://adm.conlineweb.com/api/request_human_whatsapp.php";
    $payload = [
        'payload' => [
            'client_phone' => $from_whatsapp_e164,
            'reason' => 'client_requested_agent'
        ]
    ];
    $ch = curl_init($webhookUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5
    ]);
    curl_exec($ch);
    curl_close($ch);

    $systemPrompt = file_exists('./prompt.txt') ? file_get_contents('./prompt.txt') : "Eres Alex de CONLINEWEB...";
    if (empty($messages) || $messages[0]['role'] !== 'system') {
        array_unshift($messages, ["role" => "system", "content" => $systemPrompt]);
    }
    $messages[] = ["role" => "user", "content" => $rawUserMessage];
    $responseText = "Claro, te conecto con un agente. En un momento te escribimos 😊";
    $messages[] = ["role" => "assistant", "content" => $responseText];

    $tmp = $sessionFile . '.tmp';
    $fh = @fopen($tmp, 'wb');
    if ($fh) {
        if (flock($fh, LOCK_EX)) {
            fwrite($fh, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            fflush($fh);
            flock($fh, LOCK_UN);
        }
        fclose($fh);
        @rename($tmp, $sessionFile);
    } else {
        file_put_contents($sessionFile, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
    echo "<Response>";
    twiml_send_message($responseText);
    echo "</Response>";
    exit;
}

// --- HANDOFF AUTOMÁTICO: SOLICITUDES FUERA DE CATÁLOGO ---
// Regla: si el cliente solicita algo que NO es parte del catálogo (o no coincide con servicios digitales), NO cotizar.
// Se transfiere inmediatamente a un agente humano y se notifica al equipo.
// Heurística conservadora: detectar frases tipo "quiero/necesito/busco X" y validar que X contenga palabras del catálogo.
$catalogKeywords = [
    // Desarrollo web / presencia
    'web', 'sitio', 'página', 'pagina', 'landing', 'one page', 'corporativo', 'rediseño', 'redisen', 'ux', 'ui',
    // Ecommerce
    'tienda', 'e-commerce', 'ecommerce', 'carrito', 'productos', 'pasarela', 'mercado pago', 'stripe',
    // Soporte/mantenimiento
    'mantenimiento', 'soporte', 'corrección', 'correccion', 'error', 'bugs', 'optimización', 'optimizacion',
    'formulario', 'contacto', 'sección', 'seccion', 'cambiar imágenes', 'cambiar imagenes', 'texto',
    // Hosting/dominios/correo
    'hosting', 'dominio', 'ssl', 'migración', 'migracion', 'correo', 'email',
    // SEO/analytics/marketing (si aplica en tu catálogo)
    'seo', 'analytics', 'google analytics', 'pixel', 'meta', 'facebook', 'my business',
    // Branding/diseño
    'logo', 'diseño', 'diseno', 'banner'
];

$requestedThing = '';
$looksLikeNewRequest = false;
if ($rawUserMessage !== '') {
    $looksLikeNewRequest = (bool) preg_match('/\b(quiero|necesito|busco|requiero|mejor\s+quiero|ahora\s+quiero)\b/i', $rawUserMessage);
    if (preg_match('/\b(quiero|necesito|busco|requiero)\s+(una?|un)\s+([^\n\r\.,;\!\?]{3,60})/iu', $rawUserMessage, $m)) {
        $requestedThing = trim($m[3]);
    }
}

// Si hay intención de solicitud pero NO detectamos palabras del catálogo => fuera de catálogo
$isCatalogMatch = false;
$hayTexto = mb_strtolower((string) $rawUserMessage, 'UTF-8');
foreach ($catalogKeywords as $kw) {
    if ($kw === '') continue;
    $kwLower = mb_strtolower($kw, 'UTF-8');
    if (mb_strpos($hayTexto, $kwLower) !== false) {
        $isCatalogMatch = true;
        break;
    }
}

// Lista extra de disparadores claros (productos físicos / hardware / vending, etc.)
$hardOutOfCatalogRegex = '/\b(vending\s*box|vending\s*machine|maquina\s*expendedora|máquina\s*expendedora|expendedora|kiosko|kiosk|gabinete\s*vending|caja\s*vending|máquina|maquina|equipo\s+físico|equipo\s+fisico|hardware|gabinete|locker|dispensador)\b/i';
$hardOutOfCatalog = ($rawUserMessage !== '' && preg_match($hardOutOfCatalogRegex, $rawUserMessage));

if ($rawUserMessage !== '' && ($hardOutOfCatalog || ($looksLikeNewRequest && !$isCatalogMatch))) {
    $sessionMetaFile = __DIR__ . "/sessions/whatsapp_" . preg_replace('/\D+/', '', $from_whatsapp_number) . "_meta.json";
    $meta = [];
    if (file_exists($sessionMetaFile)) {
        $mraw = @file_get_contents($sessionMetaFile);
        $meta = json_decode($mraw, true) ?: [];
    }

    // Si no tenemos nombre/email en meta, intentar obtenerlos por teléfono antes de pedirlos.
    if ((empty($meta['client_name']) || empty($meta['client_email'])) && !empty($from_whatsapp_e164)) {
        $lookupUrl = "https://adm.conlineweb.com/api/consultar_datos_cliente.php?client_phone=" . urlencode($from_whatsapp_e164);
        $lookupCh = curl_init($lookupUrl);
        curl_setopt_array($lookupCh, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $lookupResp = curl_exec($lookupCh);
        $lookupCode = curl_getinfo($lookupCh, CURLINFO_HTTP_CODE);
        curl_close($lookupCh);

        if ($lookupCode === 200 && $lookupResp) {
            $lookupData = json_decode($lookupResp, true);
            if (!empty($lookupData['success']) && !empty($lookupData['data'])) {
                if (!empty($lookupData['data']['cliente_id']) && empty($meta['cliente_id'])) {
                    $meta['cliente_id'] = $lookupData['data']['cliente_id'];
                }
                if (!empty($lookupData['data']['nombre']) && empty($meta['client_name'])) {
                    $meta['client_name'] = $lookupData['data']['nombre'];
                }
                if (!empty($lookupData['data']['email']) && empty($meta['client_email'])) {
                    $meta['client_email'] = $lookupData['data']['email'];
                }
                @file_put_contents($sessionMetaFile, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
            }
        }
    }

    // Marcar handoff y pausar bot (simula agente activo por 30 min)
    $meta['human_handoff'] = true;
    $meta['human_handoff_requested_at'] = date('c');
    $meta['human_handoff_reason'] = 'special_out_of_catalog';
    $meta['agent_active'] = true;
    $meta['agent_last_interaction'] = time();
    @file_put_contents($sessionMetaFile, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);

    // Notificar al sistema interno (si existe) que se requiere agente
    $webhookUrl = "https://adm.conlineweb.com/api/request_human_whatsapp.php";
    $payload = [
        'payload' => [
            'client_phone' => $from_whatsapp_e164,
            'reason' => 'special_out_of_catalog'
        ]
    ];
    $ch = curl_init($webhookUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5
    ]);
    curl_exec($ch);
    curl_close($ch);

    // Notificar al equipo por correo con contexto mínimo
    $clientName = !empty($meta['client_name']) ? $meta['client_name'] : 'No especificado';
    $clientEmail = !empty($meta['client_email']) ? $meta['client_email'] : 'No especificado';
    $clientPhone = $from_whatsapp_e164;
    $tipoProyecto = 'Solicitud fuera de catálogo';
    $descripcionSolicitud = $rawUserMessage;

    $emailSubject = "🚨 Solicitud fuera de catálogo (WhatsApp)";
    $emailTitulo = "Solicitud de Evaluación Especial";
    $emailCuerpo = "
        <div style='background-color: #f8f9fa; padding: 15px; border-left: 4px solid #ff6b6b; margin-bottom: 20px;'>
            <h3 style='color: #ff6b6b; margin-top: 0;'>🚨 Solicitud Fuera de Catálogo</h3>
            <p style='margin: 0;'>El bot detectó una solicitud que requiere levantamiento de requerimientos (NO cotizar automáticamente).</p>
        </div>

        <div style='margin-bottom: 25px;'>
            <h3 style='color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 8px;'>👤 Datos del Cliente</h3>
            <table style='width: 100%; margin-top: 10px;'>
                <tr><td style='padding: 8px 0; font-weight: bold; width: 30%;'>Nombre:</td><td style='padding: 8px 0;'>" . htmlspecialchars($clientName) . "</td></tr>
                <tr><td style='padding: 8px 0; font-weight: bold;'>Email:</td><td style='padding: 8px 0;'><a href='mailto:" . htmlspecialchars($clientEmail) . "'>" . htmlspecialchars($clientEmail) . "</a></td></tr>
                <tr><td style='padding: 8px 0; font-weight: bold;'>Teléfono:</td><td style='padding: 8px 0;'><a href='https://wa.me/" . preg_replace('/[^0-9]/', '', $clientPhone) . "'>" . htmlspecialchars($clientPhone) . "</a></td></tr>
            </table>
        </div>

        <div style='margin-bottom: 25px;'>
            <h3 style='color: #333; border-bottom: 2px solid #2196F3; padding-bottom: 8px;'>🎯 Tipo</h3>
            <p style='background-color: #e3f2fd; padding: 12px; border-radius: 5px; font-weight: bold;'>" . htmlspecialchars($tipoProyecto) . "</p>
        </div>

        <div style='margin-bottom: 25px;'>
            <h3 style='color: #333; border-bottom: 2px solid #FF9800; padding-bottom: 8px;'>📝 Mensaje del Cliente</h3>
            <p style='background-color: #fff3e0; padding: 12px; border-radius: 5px; line-height: 1.6;'>" . nl2br(htmlspecialchars($descripcionSolicitud)) . "</p>
        </div>

        <div style='background-color: #fff8e1; padding: 15px; border-radius: 5px; border-left: 4px solid #FFC107;'>
            <p style='margin: 0; font-weight: bold; color: #F57C00;'>⚠️ ACCIÓN REQUERIDA:</p>
            <p style='margin: 10px 0 0 0;'>Contactar al cliente para levantamiento de requerimientos y cotización especial.</p>
        </div>
    ";
    $emailDespedida = "<strong>Seguimiento requerido.</strong>";
    enviarCorreo('servicios@conlineweb.com', $emailSubject, $emailTitulo, $emailCuerpo, $emailDespedida);

    // Respuesta al cliente: transferir + solicitar datos mínimos (sin cotizar)
    $systemPrompt = file_exists('./prompt.txt') ? file_get_contents('./prompt.txt') : "Eres Alex de CONLINEWEB...";
    if (empty($messages) || $messages[0]['role'] !== 'system') {
        array_unshift($messages, ["role" => "system", "content" => $systemPrompt]);
    }
    $messages[] = ["role" => "user", "content" => $rawUserMessage];

    $label = $requestedThing !== '' ? $requestedThing : 'tu solicitud';
    $needsIdentity = empty($meta['client_name']) || empty($meta['client_email']);
    $responseText = "Perfecto, entiendo tu solicitud de {$label}.\n\nEste tipo de proyecto requiere una evaluación personalizada para darte el mejor presupuesto y solución adaptada a tus necesidades específicas.\n\nTe voy a transferir con un agente especializado que evaluará tu solicitud y te contactará a la brevedad para analizar los detalles y preparar una propuesta personalizada.\n\n¿Te parece bien? 😊\n\nPara que el agente pueda avanzar, por favor escríbeme estos datos:\n1) ¿Qué es exactamente lo que necesitas (alcance general)?\n2) ¿Es algo físico, online, o ambos?\n3) ¿Qué volumen/cantidad/capacidad estimas (y si hay medidas aproximadas)?";
    if ($needsIdentity) {
        $responseText .= "\n\nY para registrarte correctamente en el sistema: \n👤 ¿Cuál es tu nombre completo?\n📧 ¿Cuál es tu correo electrónico?";
    }
    $messages[] = ["role" => "assistant", "content" => $responseText];

    $tmp = $sessionFile . '.tmp';
    $fh = @fopen($tmp, 'wb');
    if ($fh) {
        if (flock($fh, LOCK_EX)) {
            fwrite($fh, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            fflush($fh);
            flock($fh, LOCK_UN);
        }
        fclose($fh);
        @rename($tmp, $sessionFile);
    } else {
        file_put_contents($sessionFile, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
    echo "<Response>";
    twiml_send_message($responseText);
    echo "</Response>";
    exit;
}

// --- CONTAR MENSAJES DEL HISTORIAL PRIMERO ---
$yaSaludó = false;
$mensajesUsuario = 0;
$mensajesAsistente = 0;

foreach ($messages as $m) {
    if ($m['role'] === 'assistant' && !empty($m['content'])) {
        $mensajesAsistente++;
        if (stripos($m['content'], 'Hola') !== false || stripos($m['content'], '👋') !== false) {
            $yaSaludó = true;
        }
    }
    if ($m['role'] === 'user') {
        $mensajesUsuario++;
    }
}

// --- BÚSQUEDA AUTOMÁTICA DEL CLIENTE POR TELÉFONO (PRIMERA VEZ) ---
$clienteEncontrado = false;
$datosClienteAPI = null;

// Solo buscar si es el primer mensaje del usuario en esta sesión
if ($mensajesUsuario === 0) {
    error_log("[WhatsApp Webhook] 🔍 Primer mensaje detectado. Buscando cliente automáticamente...");
    
    $apiUrl = "https://adm.conlineweb.com/api/consultar_datos_cliente.php?client_phone=" . urlencode($from_whatsapp_e164);
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $datosClienteAPI = json_decode($response, true);
        if (!empty($datosClienteAPI['success']) && !empty($datosClienteAPI['data'])) {
            $clienteEncontrado = true;
            
            // Cargar meta si no existe
            $sessionMetaFile = __DIR__ . "/sessions/whatsapp_" . preg_replace('/\D+/', '', $from_whatsapp_number) . "_meta.json";
            $meta = [];
            if (file_exists($sessionMetaFile)) {
                $mraw = @file_get_contents($sessionMetaFile);
                $meta = json_decode($mraw, true) ?: [];
            }
            
            // Guardar datos del cliente en meta
            if (!empty($datosClienteAPI['data']['cliente_id'])) {
                $meta['cliente_id'] = $datosClienteAPI['data']['cliente_id'];
            }
            if (!empty($datosClienteAPI['data']['nombre'])) {
                $meta['client_name'] = $datosClienteAPI['data']['nombre'];
            }
            if (!empty($datosClienteAPI['data']['email'])) {
                $meta['client_email'] = $datosClienteAPI['data']['email'];
            }
            
            file_put_contents($sessionMetaFile, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
            error_log("[WhatsApp Webhook] ✓ Cliente encontrado automáticamente: ID=" . $datosClienteAPI['data']['cliente_id'] . ", Nombre=" . ($datosClienteAPI['data']['nombre'] ?? 'N/A'));
        }
    } else {
        error_log("[WhatsApp Webhook] ℹ Cliente NO encontrado por teléfono {$from_whatsapp_e164}. Es cliente nuevo.");
    }
}

// --- VERIFICACIÓN DE CLIENTE VIP (TABLA CONTACTOS) ---
$esClienteVIP = false;
$datosVIP = null;

// Verificar en la tabla contactos si es un cliente VIP
require_once __DIR__ . '/../conn.php';

try {
    // Limpiar el número para la búsqueda (solo dígitos)
    $phoneDigits = preg_replace('/\D+/', '', $from_whatsapp_e164);
    
    // Buscar con diferentes formatos posibles
    $stmt = $conn->prepare("
        SELECT id, empresa, nombre, telefono 
        FROM contactos 
        WHERE REPLACE(REPLACE(REPLACE(telefono, '+', ''), '-', ''), ' ', '') LIKE ?
           OR REPLACE(REPLACE(REPLACE(telefono, '+', ''), '-', ''), ' ', '') LIKE ?
           OR REPLACE(REPLACE(REPLACE(telefono, '+', ''), '-', ''), ' ', '') LIKE ?
    ");
    
    // Probar con últimos 10 dígitos
    $last10Digits = substr($phoneDigits, -10);
    $pattern1 = '%' . $last10Digits;
    $pattern2 = $last10Digits . '%';
    $pattern3 = '%' . $last10Digits . '%';
    
    $stmt->bind_param("sss", $pattern1, $pattern2, $pattern3);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $esClienteVIP = true;
        $datosVIP = $result->fetch_assoc();
        error_log("[WhatsApp Webhook] 🔥 CLIENTE VIP DETECTADO: " . $datosVIP['nombre'] . " - " . $datosVIP['empresa'] . " (Tel: " . $datosVIP['telefono'] . ")");
    } else {
        error_log("[WhatsApp Webhook] ℹ Número no encontrado en tabla contactos VIP");
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("[WhatsApp Webhook] ⚠️ Error al verificar cliente VIP: " . $e->getMessage());
}

// --- ASEGURAR QUE EL SYSTEM PROMPT ESTÉ AL INICIO ---
$systemPrompt = file_exists('./prompt.txt') ? file_get_contents('./prompt.txt') : "Eres Alex de CONLINEWEB...";

if (empty($messages) || $messages[0]['role'] !== 'system') {
    array_unshift($messages, ["role" => "system", "content" => $systemPrompt]);
}

// --- INYECTAR ALERTA DE CLIENTE VIP SI APLICA ---
if ($esClienteVIP && !empty($datosVIP)) {
    $vipAlert = "🔥🔥🔥 ⚠️ CLIENTE VIP DETECTADO ⚠️ 🔥🔥🔥\n\n";
    $vipAlert .= "Nombre: " . $datosVIP['nombre'] . "\n";
    $vipAlert .= "Empresa: " . $datosVIP['empresa'] . "\n";
    $vipAlert .= "Teléfono: " . $datosVIP['telefono'] . "\n\n";
    $vipAlert .= "🚨 PROTOCOLO VIP ACTIVADO:\n";
    $vipAlert .= "- NO COBRAR - Servicios cubiertos por contrato\n";
    $vipAlert .= "- NO mencionar precios ni cotizaciones\n";
    $vipAlert .= "- NO preguntar método de pago\n";
    $vipAlert .= "- Crear ticket directo sin cobro (sin pago)\n";
    $vipAlert .= "- USAR herramienta: crear_ticket_servicio (NO usar registrar_solicitud_prepago)\n";
    $vipAlert .= "- Confirmar registro inmediatamente\n\n";
    $vipAlert .= "Este cliente tiene un acuerdo especial. NUNCA cobrar sin importar la solicitud.";
    
    $messages[] = ["role" => "system", "content" => $vipAlert];
    error_log("[WhatsApp Webhook] 🔥 Alerta VIP inyectada en contexto de IA");
}

// Inyectar información del cliente si se encontró
if ($clienteEncontrado && !empty($datosClienteAPI['data'])) {
    $clienteInfo = "INFORMACIÓN DEL CLIENTE (búsqueda automática):\n";
    $clienteInfo .= "- ID: " . ($datosClienteAPI['data']['cliente_id'] ?? 'N/A') . "\n";
    $clienteInfo .= "- Nombre: " . ($datosClienteAPI['data']['nombre'] ?? 'N/A') . "\n";
    $clienteInfo .= "- Email: " . ($datosClienteAPI['data']['email'] ?? 'N/A') . "\n";
    $clienteInfo .= "- Teléfono: " . $from_whatsapp_e164 . "\n";
    if (!empty($datosClienteAPI['data']['dominios'])) {
        $clienteInfo .= "- Dominios: " . implode(', ', $datosClienteAPI['data']['dominios']) . "\n";
    }
    if (!empty($datosClienteAPI['data']['proyectos'])) {
        $proyectosNombres = array_map(function($p) { return $p['nombre']; }, $datosClienteAPI['data']['proyectos']);
        $clienteInfo .= "- Proyectos: " . implode(', ', $proyectosNombres) . "\n";
    }
    $clienteInfo .= "\n⚠️ Este es un CLIENTE EXISTENTE. DEBES saludarlo usando su nombre: \"Hola " . ($datosClienteAPI['data']['nombre'] ?? '') . " 👋\". No necesitas pedir estos datos de nuevo.";
    
    $messages[] = ["role" => "system", "content" => $clienteInfo];
} else if ($mensajesUsuario === 0) {
    // Solo inyectar "CLIENTE NUEVO" si NO es VIP (evitar mensajes contradictorios)
    if (empty($esClienteVIP)) {
        $messages[] = ["role" => "system", "content" => "ALERTA: Este número de teléfono ({$from_whatsapp_e164}) NO está registrado en la base de datos. Es un CLIENTE NUEVO. Necesitas obtener su nombre completo y email para poder registrarlo."];
    }
}

// 2. DETECTAR SI YA SE PRESENTÓ Y CONTEXTO DE CONVERSACIÓN (YA CONTADO ARRIBA)

// 3. INYECTAR REGLA ANTI-REPETICIÓN (SOLO UNA VEZ EN TODA LA CONVERSACIÓN)
$esConversacionActiva = ($mensajesUsuario > 1 && $mensajesAsistente > 0);

// Verificar si ya se inyectó la regla anti-repetición anteriormente
$reglaYaInyectada = false;
foreach ($messages as $m) {
    if ($m['role'] === 'system' && strpos($m['content'], 'REGLA CRÍTICA') !== false) {
        $reglaYaInyectada = true;
        break;
    }
}

if ($esConversacionActiva && $yaSaludó && !$reglaYaInyectada) {
    $messages[] = [
        "role" => "system",
        "content" => "REGLA CRÍTICA: Ya saludaste y te presentaste previamente. NO vuelvas a saludar. El usuario está continuando la conversación. Responde directamente a su pregunta o solicitud actual de manera natural y útil."
    ];
}

// --- GESTIÓN DE MENSAJES VACÍOS O "?" ---
// Capturar media SIEMPRE (independientemente de si Body está vacío)
$numMedia   = (int)($_POST['NumMedia'] ?? 0);
$mediaUrl0  = !empty($_POST['MediaUrl0'])  ? $_POST['MediaUrl0']  : null;
$mediaType0 = !empty($_POST['MediaContentType0']) ? $_POST['MediaContentType0'] : null;

if ($userMessage === '?' || empty($userMessage)) {
    if ($numMedia > 0) {
        if ($mediaType0 && strpos($mediaType0, 'image') !== false) {
            $userMessage = "[El usuario envió una imagen como comprobante de pago]";
        } else {
            $userMessage = "[El usuario envió un archivo adjunto como comprobante de pago]";
        }
    } else {
        $userMessage = "[El usuario parece confundido o envió un mensaje vacío. No repitas tu saludo, intenta retomar la conversación donde quedó]";
    }
}

// Construir entrada del mensaje guardando la URL del medio si existe
$userMsgEntry = ["role" => "user", "content" => $userMessage];
if ($numMedia > 0 && $mediaUrl0) {
    $userMsgEntry['media_url']  = $mediaUrl0;
    $userMsgEntry['media_type'] = $mediaType0 ?? 'image/jpeg';
}
$messages[] = $userMsgEntry;

// Extraer entidades básicas
function extract_client_data($text)
{
    $data = [];

    // Email
    if (preg_match('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $text, $m)) {
        $data['client_email'] = $m[0];
    }

    // Nombre
    if (preg_match('/(?:mi nombre es|soy|me llamo)\s+([A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)+)/i', $text, $n)) {
        $data['client_name'] = trim($n[1]);
    }

    // Dominio - Mejorado para capturar URLs completas y dominios simples
    // Primero quitar emails del texto para que no se confundan con dominios
    $textSinEmail = preg_replace('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/', '', $text);
    // Buscar URLs completas primero (https://dominio.com/path)
    if (preg_match('#https?://([a-z0-9.-]+\.[a-z]{2,})(?:/[^\s]*)?#i', $textSinEmail, $d)) {
        $data['domain_name'] = strtolower($d[1]);
    }
    // Si no hay URL completa, buscar dominio simple (dominio.com.mx o dominio.com)
    elseif (preg_match('/\b([a-z0-9-]+\.[a-z0-9-]+\.[a-z]{2,})\b/i', $textSinEmail, $d)) {
        // Dominio con subdominio como ejemplo.com.mx
        $data['domain_name'] = strtolower($d[1]);
    } elseif (preg_match('/\b([a-z0-9-]+\.[a-z]{2,})\b/i', $textSinEmail, $d)) {
        // Dominio simple como ejemplo.com
        $data['domain_name'] = strtolower($d[1]);
    }

    // Nombre de proyecto - Detectar cuando el usuario menciona explícitamente el nombre del proyecto
    if (preg_match('/(?:proyecto|proyecto es|proyecto de|nombre del proyecto|se llama|llamado)\s+["\']?([A-Za-z0-9áéíóúñÁÉÍÓÚÑ\s-]+?)["\']?(?:\s|$)/i', $text, $p)) {
        $data['project_name'] = trim($p[1]);
    }

    return $data;
}

$entities = extract_client_data($userMessage);

// Cargamos o inicializamos meta
$sessionMetaFile = __DIR__ . "/sessions/whatsapp_" . preg_replace('/\D+/', '', $from_whatsapp_number) . "_meta.json";
$legacySessionMetaFile = __DIR__ . "/sessions/whatsapp_" . preg_replace('/\D+/', '', $legacyDigits) . "_meta.json";
$loadedFromLegacyMeta = false;
$meta = [];

if (file_exists($sessionMetaFile)) {
    $mraw = @file_get_contents($sessionMetaFile);
    $meta = json_decode($mraw, true) ?: [];
} elseif ($legacyDigits !== '' && $legacyDigits !== $from_whatsapp_number && file_exists($legacySessionMetaFile)) {
    $mraw = @file_get_contents($legacySessionMetaFile);
    $meta = json_decode($mraw, true) ?: [];
    $loadedFromLegacyMeta = true;
}

// --- BÚSQUEDA AUTOMÁTICA POR EMAIL SI SE PROPORCIONÓ ---
// Si el cliente es nuevo (no encontrado por teléfono) y proporcionó un email,
// buscar automáticamente si ese email corresponde a un cliente existente
if (!empty($entities['client_email']) && empty($meta['cliente_id'])) {
    error_log("[WhatsApp Webhook] 📧 Email detectado en mensaje: " . $entities['client_email'] . ". Buscando cliente por email...");
    
    $apiUrl = "https://adm.conlineweb.com/api/consultar_datos_cliente.php?client_email=" . urlencode($entities['client_email']);
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $datosClienteEmail = json_decode($response, true);
        if (!empty($datosClienteEmail['success']) && !empty($datosClienteEmail['data'])) {
            // ¡Cliente encontrado por email!
            error_log("[WhatsApp Webhook] ✓ Cliente existente encontrado por email: ID=" . $datosClienteEmail['data']['cliente_id']);
            
            // Guardar datos del cliente en meta
            if (!empty($datosClienteEmail['data']['cliente_id'])) {
                $meta['cliente_id'] = $datosClienteEmail['data']['cliente_id'];
            }
            if (!empty($datosClienteEmail['data']['nombre'])) {
                $meta['client_name'] = $datosClienteEmail['data']['nombre'];
            }
            if (!empty($datosClienteEmail['data']['email'])) {
                $meta['client_email'] = $datosClienteEmail['data']['email'];
            }
            
            file_put_contents($sessionMetaFile, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
            
            // Inyectar información en el contexto
            $clienteInfoEmail = "✅ CLIENTE ENCONTRADO POR EMAIL:\n";
            $clienteInfoEmail .= "El email proporcionado ({$entities['client_email']}) corresponde a un cliente existente:\n";
            $clienteInfoEmail .= "- ID: " . ($datosClienteEmail['data']['cliente_id'] ?? 'N/A') . "\n";
            $clienteInfoEmail .= "- Nombre: " . ($datosClienteEmail['data']['nombre'] ?? 'N/A') . "\n";
            $clienteInfoEmail .= "- Email: " . ($datosClienteEmail['data']['email'] ?? 'N/A') . "\n";
            $clienteInfoEmail .= "- Teléfono registrado: " . ($datosClienteEmail['data']['telefono'] ?? 'N/A') . "\n";
            
            if (!empty($datosClienteEmail['data']['dominios'])) {
                $clienteInfoEmail .= "- Dominios: " . implode(', ', $datosClienteEmail['data']['dominios']) . "\n";
            }
            if (!empty($datosClienteEmail['data']['proyectos'])) {
                $proyectosNombres = array_map(function($p) { return $p['nombre']; }, $datosClienteEmail['data']['proyectos']);
                $clienteInfoEmail .= "- Proyectos: " . implode(', ', $proyectosNombres) . "\n";
            }
            
            $clienteInfoEmail .= "\n⚠️ IMPORTANTE: Este cliente YA EXISTE en el sistema. NO uses registrar_cliente_nuevo. Salúdalo por su nombre y continúa con su solicitud.";
            
            $messages[] = ["role" => "system", "content" => $clienteInfoEmail];
            
            error_log("[WhatsApp Webhook] ✓ Contexto de cliente existente inyectado por búsqueda de email");
        } else {
            error_log("[WhatsApp Webhook] ℹ Email no encontrado en base de datos. Proceder con registro de cliente nuevo.");
        }
    }
}

$metaChanged = false;

if (empty($meta['client_phone']) && !empty($from_whatsapp_e164)) {
    $meta['client_phone'] = $from_whatsapp_e164;
    $metaChanged = true;
}

foreach (['client_name', 'client_email', 'domain_name', 'project_name'] as $k) {
    if (!empty($entities[$k]) && (empty($meta[$k]) || $meta[$k] != $entities[$k])) {
        $meta[$k] = $entities[$k];
        $metaChanged = true;
    }
}

if ($metaChanged) {
    file_put_contents($sessionMetaFile, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    $known = [];
    $knownDetails = [];    if (!empty($meta['client_name'])) {
        $known[] = "client_name: " . $meta['client_name'];
        $knownDetails[] = "su nombre completo";
    }
    if (!empty($meta['client_email'])) {
        $known[] = "client_email: " . $meta['client_email'];
        $knownDetails[] = "su correo electrónico (" . $meta['client_email'] . ")";
    }
    if (!empty($meta['client_phone'])) {
        $known[] = "client_phone: " . $meta['client_phone'];
        $knownDetails[] = "su teléfono";
    }
    if (!empty($meta['domain_name'])) {
        $known[] = "domain_name: " . $meta['domain_name'];
        $knownDetails[] = "su dominio";
    }
    if (!empty($meta['project_name'])) {
        $known[] = "project_name: " . $meta['project_name'];
        $knownDetails[] = "su proyecto";
    }
    if (!empty($known)) {
        $detailsText = implode(', ', $knownDetails);
        $messages[] = ["role" => "system", "content" => "⚠️ DATOS YA OBTENIDOS: El cliente ya te proporcionó " . $detailsText . ". NO vuelvas a pedir estos datos. Usa la información que ya tienes: " . implode('; ', $known)];
    }
}

// ---------------------------------------------------------------
// DETECCIÓN DE COMPROBANTE DE TRANSFERENCIA
// Si hay una solicitud pendiente con método transferencia y el
// cliente dice "ya pagué", "ya transferí" o manda una imagen,
// marcamos comprobante_pendiente en meta para que el admin lo acredite.
// ---------------------------------------------------------------
// Detectar comprobante: dispara con imagen O palabras clave de pago,
// independientemente de si pending_solicitud_id está seteado.
$isMediaMessage = $numMedia > 0;
$comprobanteKeywords = '/\b(comprobante|ya pague|ya pagué|ya transferi|ya transferí|realice la transferencia|realicé la transferencia|hice la transferencia|hice el pago|efectue|efectué|deposite|deposité|aqui esta|aquí está|te mando|adjunto)\b/ui';
if ($isMediaMessage || preg_match($comprobanteKeywords, $rawUserMessage)) {
    if (empty($meta['comprobante_pendiente'])) {
        $meta['comprobante_pendiente'] = true;
        $meta['comprobante_at'] = time();
        if ($mediaUrl0) {
            $meta['comprobante_media_url']  = $mediaUrl0;
            $meta['comprobante_media_type'] = $mediaType0 ?? 'image/jpeg';
        }
        file_put_contents($sessionMetaFile, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        error_log("[WhatsApp Webhook] 🧾 Comprobante detectado" . (!empty($meta['pending_solicitud_id']) ? " para solicitud #{$meta['pending_solicitud_id']}" : ' (sin solicitud_id)') . ". Marcado como pendiente de acreditación.");
    }
}

// Si está en handoff, no responder automáticamente
if (!empty($meta['human_handoff'])) {
    $tmp = $sessionFile . '.tmp';
    $fh = @fopen($tmp, 'wb');
    if ($fh) {
        if (flock($fh, LOCK_EX)) {
            fwrite($fh, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            fflush($fh);
            flock($fh, LOCK_UN);
        }
        fclose($fh);
        @rename($tmp, $sessionFile);
    } else {
        @file_put_contents($sessionFile, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
    echo "<Response></Response>";
    exit;
}

// =======================
// 2. DEFINICIÓN DE TOOLS PARA CONLINEWEB
// =======================
$tools = [
    [
        "type" => "function",
        "function" => [
                "name" => "registrar_cliente_nuevo",
                "description" => "Registra un cliente nuevo en el sistema cuando NO se encontró su número de teléfono en la búsqueda automática. Usar después de obtener nombre y email del cliente.",
                "parameters" => [
                        "type" => "object",
                        "properties" => [
                                "client_name" => ["type" => "string", "description" => "Nombre completo del cliente"],
                                "client_email" => ["type" => "string", "description" => "Correo electrónico del cliente"],
                                "client_phone" => ["type" => "string", "description" => "Número de teléfono del cliente en formato E.164"]
                        ],
                        "required" => ["client_name", "client_email", "client_phone"]
                ]
        ]
    ],
    [
        "type" => "function",
        "function" => [
                "name" => "consultar_datos_cliente",
                "description" => "Busca información adicional del cliente en la base de datos usando nombre, email, dominio o nombre de proyecto. Usar cuando necesites información adicional que no se obtuvo en la búsqueda automática inicial.",
                "parameters" => [
                        "type" => "object",
                        "properties" => [
                                "client_name" => ["type" => "string", "description" => "Nombre del cliente"],
                                "client_email" => ["type" => "string", "description" => "Email del cliente"],
                                "domain_name" => ["type" => "string", "description" => "Nombre de dominio o URL completa"],
                                "project_name" => ["type" => "string", "description" => "Nombre del proyecto"]
                            ]
                    ]
            ]
    ],
    [
        "type" => "function",
        "function" => [
                "name" => "consultar_tickets_pendientes",
                "description" => "Consulta los tickets pendientes para calcular la carga de trabajo actual y estimar fechas de entrega realistas. Ejecutar antes de proporcionar una fecha estimada al cliente.",
                "parameters" => [
                        "type" => "object",
                        "properties" => (object) []
                    ]
            ]
    ],
    // NOTA: generar_enlace_pago_stripe se ejecuta automáticamente cuando registrar_solicitud_prepago tiene metodo_pago='tarjeta'
    // No debe estar disponible como herramienta independiente para evitar errores de solicitud_id faltante
    /*
    [
        "type" => "function",
        "function" => [
                "name" => "generar_enlace_pago_stripe",
                "description" => "Genera un enlace de pago seguro con Stripe cuando el cliente selecciona tarjeta como método de pago. Este enlace debe compartirse con el cliente para que complete el pago.",
                "parameters" => [
                        "type" => "object",
                        "properties" => [
                                "solicitud_id" => ["type" => "number", "description" => "ID de la solicitud pre-pago registrada"],
                                "monto" => ["type" => "number", "description" => "Cantidad en MXN"],
                                "descripcion" => ["type" => "string", "description" => "Descripción breve del servicio"],
                                "client_email" => ["type" => "string", "description" => "Email del cliente (opcional)"]
                            ],
                        "required" => ["solicitud_id", "monto", "descripcion"]
                    ]
            ]
    ],
    */
    [
        "type" => "function",
        "function" => [
                "name" => "registrar_solicitud_prepago",
                "description" => "Registra una solicitud ANTES de crear el ticket. Usar cuando el cliente acepta el precio y se define el método de pago. El ticket real se crea después de confirmar el pago.",
                "parameters" => [
                        "type" => "object",
                        "properties" => [
                                "cliente_id" => ["type" => "number", "description" => "ID del cliente si está disponible"],
                                "dominio" => ["type" => "string", "description" => "Nombre del dominio relacionado"],
                                "proyecto" => ["type" => "string", "description" => "Nombre del proyecto relacionado"],
                                "titulo" => ["type" => "string", "description" => "Resumen claro de la solicitud (máximo 80 caracteres)"],
                                "descripcion" => ["type" => "string", "description" => "Descripción detallada de la solicitud"],
                                "precio_acordado" => ["type" => "number", "description" => "Monto acordado en MXN"],
                                "fecha_limite" => ["type" => "string", "description" => "Fecha límite estimada en formato YYYY-MM-DD"],
                                "metodo_pago" => ["type" => "string", "enum" => ["transferencia", "tarjeta"], "description" => "Método de pago elegido por el cliente"],
                                "prioridad" => ["type" => "string", "enum" => ["alta", "media", "baja"], "description" => "Nivel de prioridad"]
                            ],
                        "required" => ["titulo", "descripcion", "precio_acordado", "metodo_pago"]
                    ]
            ]
    ],
                [
                "type" => "function",
                "function" => [
                    "name" => "crear_ticket_servicio",
                    "description" => "Crea un ticket DIRECTO (sin pago). USAR para CLIENTES VIP o cuando el sistema indique que NO se debe cobrar.",
                    "parameters" => [
                        "type" => "object",
                        "properties" => [
                            "cliente_id" => ["type" => "number", "description" => "ID del cliente si está disponible"],
                            "telefono" => ["type" => "string", "description" => "Teléfono del cliente (E.164 si se conoce)"],
                            "domain_name" => ["type" => "string", "description" => "Dominio/URL relacionado (opcional)"],
                            "titulo" => ["type" => "string", "description" => "Resumen claro de la solicitud"],
                            "descripcion" => ["type" => "string", "description" => "Descripción detallada de la solicitud"],
                            "fecha_limite" => ["type" => "string", "description" => "Fecha límite estimada (YYYY-MM-DD)"],
                                            "prioridad" => ["type" => "string", "enum" => ["alta", "media", "baja"], "description" => "Nivel de prioridad" ]
                                        ],
                                    "required" => ["titulo", "descripcion"]
                                ]
                        ]
                ],
    [
        "type" => "function",
        "function" => [
                "name" => "verificar_estado_pago",
                "description" => "Verifica en la base de datos si un pago fue realmente procesado y confirmado. USAR SIEMPRE cuando el usuario dice 'ya pagué' o 'realicé el pago'. NO crear tickets sin verificar primero con esta función.",
                "parameters" => [
                        "type" => "object",
                        "properties" => [
                                "solicitud_id" => ["type" => "number", "description" => "ID de la solicitud a verificar"]
                            ],
                        "required" => ["solicitud_id"]
                    ]
            ]
    ],
    [
        "type" => "function",
        "function" => [
                "name" => "confirmar_pago_solicitud",
                "description" => "Confirma el pago de una solicitud y crea el ticket en el sistema. ⚠️ CRÍTICO: SOLO llamar después de verificar_estado_pago y confirmar que pagado=true. NUNCA llamar solo porque el usuario diga que pagó.",
                "parameters" => [
                        "type" => "object",
                        "properties" => [
                                "solicitud_id" => ["type" => "number", "description" => "ID de la solicitud pre-pago"],
                                "stripe_payment_id" => ["type" => "string", "description" => "ID de pago de Stripe (si aplica)"]
                    ],
                        "required" => ["solicitud_id"]
                    ]
            ]
    ],
    [
        "type" => "function",
        "function" => [
                "name" => "ticket_no_creado",
                "description" => "Registra cuando la conversación termina sin crear un ticket. Ejecutar silenciosamente cuando: el cliente no continuó, pidió pensarlo, no confirmó pago, abandonó la conversación, o dijo que el precio es muy alto.",
                "parameters" => [
                        "type" => "object",
                        "properties" => [
                                "client_phone" => ["type" => "string", "description" => "Teléfono del cliente"],
                                "reason" => ["type" => "string", "description" => "Motivo por el que no se creó el ticket"]
                            ]
                    ]
            ]
    ],
    [
        "type" => "function",
        "function" => [
                "name" => "consultar_catalogo",
                "description" => "Consulta el catálogo oficial de servicios y precios de CONLINEWEB. SIEMPRE usar esta herramienta antes de cotizar cualquier servicio. Nunca inventes precios; usa exclusivamente los precios que devuelva esta función.",
                "parameters" => [
                        "type" => "object",
                        "properties" => [
                                "q" => ["type" => "string", "description" => "Búsqueda libre por palabra clave (ej: 'landing', 'tienda', 'hosting', 'mantenimiento')"],
                                "categoria" => ["type" => "string", "description" => "Filtrar por categoría exacta: 'Webs Informativas', 'E-commerce', 'Mantenimiento', 'Servicios Pequeños', 'Dominios y Hosting', 'SEO y Marketing'"],
                                "servicio" => ["type" => "integer", "description" => "Número exacto del servicio si se conoce"],
                                "moneda" => ["type" => "string", "enum" => ["mxn", "usd", "clp"], "description" => "Moneda para el precio destacado (default: mxn)"]
                        ]
                ]
        ]
    ],
    [
        "type" => "function",
        "function" => [
                "name" => "solicitar_evaluacion_especial",
                "description" => "Solicita evaluación de un agente humano para proyectos fuera de catálogo (sistemas personalizados, apps móviles, integraciones complejas, etc.). Envía notificación automática al equipo vía email.",
                "parameters" => [
                        "type" => "object",
                        "properties" => [
                                "client_name" => ["type" => "string", "description" => "Nombre del cliente"],
                                "client_email" => ["type" => "string", "description" => "Email del cliente"],
                                "client_phone" => ["type" => "string", "description" => "Teléfono del cliente"],
                                "tipo_proyecto" => ["type" => "string", "description" => "Tipo de proyecto solicitado (ej: sistema ERP, app móvil, integración API)"],
                                "descripcion_solicitud" => ["type" => "string", "description" => "Descripción completa de lo que el cliente necesita"],
                                "detalles_adicionales" => ["type" => "string", "description" => "Cualquier detalle adicional relevante mencionado por el cliente"]
                            ],
                        "required" => ["client_phone", "tipo_proyecto", "descripcion_solicitud"]
                    ]
            ]
    ]
];

// --- BLOQUEO DE PAGOS PARA CLIENTES VIP ---
// Requisito: para VIP se debe saltar TODA la parte de pagos/Stripe.
// Por eso, NO exponemos herramientas de pago a la IA cuando esClienteVIP = true.
if (!empty($esClienteVIP)) {
    $tools = array_values(array_filter($tools, function($t) {
        $name = $t['function']['name'] ?? '';
        return !in_array($name, [
            'registrar_solicitud_prepago',
            'verificar_estado_pago',
            'confirmar_pago_solicitud'
        ], true);
    }));
    error_log('[WhatsApp Webhook] 🔥 VIP: herramientas de pago deshabilitadas (se crea ticket directo)');
}

// Convierte Markdown a formato WhatsApp
function markdownToWhatsApp($text)
{
    // Encabezados ### ## # → *Texto*
    $text = preg_replace('/^#{1,3}\s+(.+)$/m', '*$1*', $text);

    // **negrita** → *negrita* (WhatsApp bold)
    $text = preg_replace('/\*\*(.+?)\*\*/', '*$1*', $text);

    // __negrita__ → *negrita*
    $text = preg_replace('/__(.+?)__/', '*$1*', $text);

    // _cursiva_ → _cursiva_ (ya es compatible, no cambiar)
    // *cursiva* de MD puede confundirse con bold de WA — dejar como está

    // Bloques de código ```...``` → quitar los backticks
    $text = preg_replace('/```[\w]*\n?/', '', $text);
    $text = preg_replace('/```/', '', $text);

    // `código inline` → quitar backticks
    $text = preg_replace('/`(.+?)`/', '$1', $text);

    return $text;
}

// Función auxiliar para llamar a OpenAI
function callOpenAI($messages, $tools, $apiKey)
{
    $payload = [
        "model" => "gpt-4o-mini",
        "messages" => $messages,
        "tools" => $tools,
        "tool_choice" => "auto",
        "temperature" => 0.7
    ];

    $ch = curl_init("https://api.openai.com/v1/chat/completions");
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer {$apiKey}"
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

// =======================
// 3. PRIMERA LLAMADA A OPENAI
// =======================
$data = callOpenAI($messages, $tools, $OPENAI_API_KEY);

// Validar si hay error en la respuesta de OpenAI
if (isset($data['error'])) {
    error_log("OpenAI Error: " . json_encode($data['error']));
    $responseText = 'Disculpa, estoy teniendo problemas técnicos. ¿Podrías intentar de nuevo en un momento? 😊';
    $messages[] = ["role" => "assistant", "content" => $responseText];

    // Guardar y responder
    $tmp = $sessionFile . '.tmp';
    $fh = @fopen($tmp, 'wb');
    if ($fh) {
        if (flock($fh, LOCK_EX)) {
            fwrite($fh, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            fflush($fh);
            flock($fh, LOCK_UN);
        }
        fclose($fh);
        @rename($tmp, $sessionFile);
    }

    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
    echo "<Response>";
    twiml_send_message($responseText);
    echo "</Response>";
    exit;
}

$assistantMessage = $data['choices'][0]['message'] ?? [];

// Log si el contenido está vacío para debugging
if (empty($assistantMessage['content']) && empty($assistantMessage['tool_calls'])) {
    error_log("OpenAI returned empty response. Full data: " . json_encode($data));
}

$responseText = '';
$skipFinalOpenAI = false;
$farewellMessage = '';
$pendingContextMessages = []; // Almacenar mensajes de contexto para agregar después de tool responses
$vipTicketCallProcessed = false;

// =======================
// 4. PROCESAR TOOL CALLS
// =======================
if (!empty($assistantMessage['tool_calls'])) {

    $messages[] = $assistantMessage;

    foreach ($assistantMessage['tool_calls'] as $toolCall) {
        $functionName = $toolCall['function']['name'];
        $functionArgs = json_decode($toolCall['function']['arguments'], true);
        $toolCallId = $toolCall['id'];

        $toolOutput = "Error desconocido.";

        // --- BLOQUEO DEFENSIVO: VIP NO USA PAGOS ---
        if (!empty($esClienteVIP) && in_array($functionName, ['registrar_solicitud_prepago', 'verificar_estado_pago', 'confirmar_pago_solicitud'], true)) {
            $toolOutput = json_encode([
                'success' => false,
                'error' => 'Cliente VIP: los flujos de pago/Stripe están deshabilitados. Debes crear ticket directo con crear_ticket_servicio.'
            ], JSON_UNESCAPED_UNICODE);
            $messages[] = ["role" => "tool", "tool_call_id" => $toolCallId, "content" => $toolOutput];
            continue;
        }

        // --- A) REGISTRAR CLIENTE NUEVO ---
        if ($functionName === 'registrar_cliente_nuevo') {
            $apiUrl = "https://adm.conlineweb.com/api/registrar_cliente_nuevo.php";

            // Asegurar que el teléfono esté en formato E.164
            if (empty($functionArgs['client_phone'])) {
                $functionArgs['client_phone'] = $from_whatsapp_e164;
            }

            error_log("[WhatsApp Webhook] ========== REGISTRAR CLIENTE NUEVO ==========");
            error_log("[WhatsApp Webhook] Datos: " . json_encode($functionArgs, JSON_UNESCAPED_UNICODE));

            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($functionArgs),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5
            ]);
            $apiResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            error_log("[WhatsApp Webhook] Respuesta API: " . $apiResponse);

            if ($apiResponse) {
                $toolOutput = $apiResponse;
                $responseData = json_decode($apiResponse, true);
                
                // Guardar cliente_id en meta
                if (!empty($responseData['success']) && !empty($responseData['data']['cliente_id'])) {
                    $meta['cliente_id'] = $responseData['data']['cliente_id'];
                    $meta['client_name'] = $functionArgs['client_name'];
                    $meta['client_email'] = $functionArgs['client_email'];
                    file_put_contents($sessionMetaFile, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
                    error_log("[WhatsApp Webhook] ✓ Cliente registrado con ID: " . $responseData['data']['cliente_id']);
                }
            } else {
                $toolOutput = json_encode(['success' => false, 'error' => 'No se pudo conectar con la API']);
            }

            $messages[] = ["role" => "tool", "tool_call_id" => $toolCallId, "content" => $toolOutput];
        }
        // --- B) CONSULTAR DATOS CLIENTE ---
        else if ($functionName === 'consultar_datos_cliente') {
            $apiUrl = "https://adm.conlineweb.com/api/consultar_datos_cliente.php";

            // Agregar el teléfono del cliente si está disponible
            if (!empty($from_whatsapp_e164) && empty($functionArgs['client_phone'])) {
                $functionArgs['client_phone'] = $from_whatsapp_e164;
            }

            error_log("[WhatsApp Webhook] ========== CONSULTAR DATOS CLIENTE ==========");
            error_log("[WhatsApp Webhook] Teléfono WhatsApp: " . $from_whatsapp_e164);
            error_log("[WhatsApp Webhook] Argumentos función: " . json_encode($functionArgs, JSON_UNESCAPED_UNICODE));

            $queryParams = http_build_query(array_filter($functionArgs));
            $fullUrl = $apiUrl . '?' . $queryParams;
            error_log("[WhatsApp Webhook] URL completa: " . $fullUrl);

            $ch = curl_init($fullUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5
            ]);
            $apiResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            error_log("[WhatsApp Webhook] Código HTTP: " . $httpCode);
            error_log("[WhatsApp Webhook] Respuesta API: " . substr($apiResponse, 0, 500));
            if (!empty($curlError)) {
                error_log("[WhatsApp Webhook] Error CURL: " . $curlError);
            }

            if ($httpCode === 200 && $apiResponse) {
                $toolOutput = $apiResponse;

                // Extraer y almacenar automáticamente los datos del cliente
                $responseData = json_decode($apiResponse, true);
                if (!empty($responseData['success']) && !empty($responseData['data'])) {
                    $clientData = $responseData['data'];

                    // Actualizar metadata con información del cliente
                    $metaUpdated = false;
                    if (!empty($clientData['cliente_id']) && empty($meta['cliente_id'])) {
                        $meta['cliente_id'] = $clientData['cliente_id'];
                        $metaUpdated = true;
                        error_log("[WhatsApp Webhook] ✓ Cliente ID guardado en meta: " . $clientData['cliente_id']);
                    }
                    if (!empty($clientData['nombre']) && empty($meta['client_name'])) {
                        $meta['client_name'] = $clientData['nombre'];
                        $metaUpdated = true;
                        error_log("[WhatsApp Webhook] ✓ Nombre guardado en meta: " . $clientData['nombre']);
                    }
                    if (!empty($clientData['email']) && empty($meta['client_email'])) {
                        $meta['client_email'] = $clientData['email'];
                        $metaUpdated = true;
                        error_log("[WhatsApp Webhook] ✓ Email guardado en meta: " . $clientData['email']);
                    }

                    // Guardar metadata actualizada
                    if ($metaUpdated) {
                        file_put_contents($sessionMetaFile, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);

                        // Preparar contexto silencioso para agregar DESPUÉS de las tool responses
                        $contextInfo = [];
                        if (!empty($clientData['nombre']))
                            $contextInfo[] = "Cliente: {$clientData['nombre']}";
                        if (!empty($clientData['email']))
                            $contextInfo[] = "Email: {$clientData['email']}";
                        if (!empty($clientData['dominios']))
                            $contextInfo[] = "Dominios: " . implode(', ', $clientData['dominios']);
                        if (!empty($clientData['proyectos'])) {
                            $proyectosNombres = array_map(function ($p) {
                                return $p['nombre']; }, $clientData['proyectos']);
                            $contextInfo[] = "Proyectos: " . implode(', ', $proyectosNombres);
                        }

                        if (!empty($contextInfo)) {
                            // Guardar para agregar después de completar todas las tool responses
                            $pendingContextMessages[] = [
                                "role" => "system",
                                "content" => "[CONTEXTO INTERNO - No mencionar al cliente] Se encontró información del cliente: " . implode('; ', $contextInfo) . ". Procede normalmente con la conversación."
                            ];
                            error_log("[WhatsApp Webhook] ✓ Contexto preparado (se agregará después de tool responses): " . implode('; ', $contextInfo));
                        }
                    }
                } else {
                    error_log("[WhatsApp Webhook] ✗ No se encontraron datos del cliente en la respuesta");
                }
            } else {
                error_log("[WhatsApp Webhook] ✗ Error en consulta: HTTP " . $httpCode);
                $toolOutput = json_encode(["success" => false, "message" => "No se encontró información del cliente o error en la consulta."]);
            }

            $messages[] = ["role" => "tool", "tool_call_id" => $toolCallId, "content" => $toolOutput];

            // --- C) CONSULTAR TICKETS PENDIENTES ---
        } elseif ($functionName === 'consultar_tickets_pendientes') {
            $apiUrl = "https://adm.conlineweb.com/api/consultar_tickets_pendientes.php";

            error_log("[WhatsApp Webhook] ========== CONSULTAR TICKETS PENDIENTES ==========");
            error_log("[WhatsApp Webhook] URL API: " . $apiUrl);

            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5
            ]);
            $apiResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            error_log("[WhatsApp Webhook] Código HTTP: " . $httpCode);
            error_log("[WhatsApp Webhook] Respuesta API: " . substr($apiResponse ?: 'VACÍA', 0, 500));
            if (!empty($curlError)) {
                error_log("[WhatsApp Webhook] Error cURL: " . $curlError);
            }

            if ($httpCode === 200 && $apiResponse) {
                $toolOutput = $apiResponse;
                error_log("[WhatsApp Webhook] ✓ Tickets pendientes obtenidos exitosamente");
            } else {
                error_log("[WhatsApp Webhook] ✗ ERROR al obtener tickets pendientes - HTTP {$httpCode}");
                $toolOutput = json_encode(["success" => false, "tickets_pendientes" => [], "error" => "No se pudieron obtener los tickets pendientes"]);
            }

            $messages[] = ["role" => "tool", "tool_call_id" => $toolCallId, "content" => $toolOutput];

            // --- C) GENERAR ENLACE PAGO STRIPE ---
        } elseif ($functionName === 'generar_enlace_pago_stripe') {
            $apiUrl = "https://adm.conlineweb.com/api/generar_pago_stripe.php";

            $postData = [
                'solicitud_id' => $functionArgs['solicitud_id'],
                'monto' => $functionArgs['monto'],
                'descripcion' => $functionArgs['descripcion']
            ];
            if (!empty($functionArgs['client_email'])) {
                $postData['client_email'] = $functionArgs['client_email'];
            }

            error_log("[WhatsApp Webhook] ========== GENERAR ENLACE PAGO STRIPE ==========");
            error_log("[WhatsApp Webhook] Datos enviados: " . json_encode($postData, JSON_UNESCAPED_UNICODE));

            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($postData),
                CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10
            ]);

            $apiResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            error_log("[WhatsApp Webhook] Código HTTP: " . $httpCode);
            error_log("[WhatsApp Webhook] Respuesta API: " . substr($apiResponse ?: 'VACÍA', 0, 500));

            if ($httpCode == 200 || $httpCode == 201) {
                $toolOutput = $apiResponse;
                
                // Actualizar meta con solicitud_id si se generó exitosamente
                $responseData = json_decode($apiResponse, true);
                if (!empty($responseData['success']) && !empty($responseData['payment_url'])) {
                    $meta['pending_solicitud_id'] = $functionArgs['solicitud_id'];
                    $meta['pending_payment_method'] = 'tarjeta';
                    file_put_contents($sessionMetaFile, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
                    error_log("[WhatsApp Webhook] ✓ Enlace de pago generado y metadata actualizada");
                }
            } else {
                $toolOutput = json_encode(["success" => false, "message" => "No se pudo generar el enlace de pago. Código: $httpCode"]);
                error_log("[WhatsApp Webhook] ✗ Error al generar enlace de pago");
            }

            $messages[] = ["role" => "tool", "tool_call_id" => $toolCallId, "content" => $toolOutput];

            // --- D) REGISTRAR SOLICITUD PRE-PAGO ---
        } elseif ($functionName === 'registrar_solicitud_prepago') {
            $apiUrl = "https://adm.conlineweb.com/api/registrar_solicitud_prepago.php";

            // Preparar datos desde functionArgs y meta
            $postData = [
                'titulo' => $functionArgs['titulo'],
                'descripcion' => $functionArgs['descripcion'],
                'precio_acordado' => $functionArgs['precio_acordado'],
                'metodo_pago' => $functionArgs['metodo_pago'],
                'prioridad' => $functionArgs['prioridad'] ?? 'media',
                'telefono' => $from_whatsapp_e164 // Agregar teléfono del cliente
            ];

            // Detectar si la solicitud parece ser un "sistema" o proyecto especial (ERP, app, integración, plataforma, etc.)
            $combinedText = strtolower(($postData['titulo'] ?? '') . ' ' . ($postData['descripcion'] ?? '') . ' ' . strtolower($functionArgs['proyecto'] ?? ''));
            $keywords = ['sistema','plataforma','app','aplicación','aplicativo','erp','crm','integración','integracion','software','sistema nuevo','sistema personalizado','aplicacion','mobile','móvil'];
            $isSpecial = false;
            foreach ($keywords as $kw) {
                if (strpos($combinedText, $kw) !== false) {
                    $isSpecial = true;
                    break;
                }
            }

            if ($isSpecial) {
                error_log("[WhatsApp Webhook] 🔎 Solicitud detectada como PROYECTO ESPECIAL (sistema/plataforma). Enviando a evaluación especializada en lugar de registrar pre-pago.");

                // Preparar datos para notificar al equipo
                $clientName = $meta['client_name'] ?? ($functionArgs['client_name'] ?? 'No especificado');
                $clientEmail = $meta['client_email'] ?? ($functionArgs['client_email'] ?? 'No especificado');
                $clientPhone = $postData['telefono'];
                $tipoProyecto = $postData['titulo'] ?? 'Proyecto especial';
                $descripcionSolicitud = $postData['descripcion'] ?? ($functionArgs['descripcion'] ?? '');
                $detallesAdicionales = $functionArgs['proyecto'] ?? '';

                // Construir y enviar correo al equipo usando la misma plantilla que solicitar_evaluacion_especial
                $emailSubject = "🚨 Solicitud Especial de WhatsApp - " . htmlspecialchars($tipoProyecto);
                $emailTitulo = "Solicitud de Evaluación Especial";
                $emailCuerpo = "
                <div style='background-color: #f8f9fa; padding: 15px; border-left: 4px solid #ff6b6b; margin-bottom: 20px;'>
                    <h3 style='color: #ff6b6b; margin-top: 0;'>🚨 Nueva Solicitud Especial</h3>
                    <p style='margin: 0;'>Se ha recibido una solicitud especial que requiere evaluación personalizada.</p>
                </div>
                
                <div style='margin-bottom: 25px;'>
                    <h3 style='color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 8px;'>👤 Datos del Cliente</h3>
                    <table style='width: 100%; margin-top: 10px;'>
                        <tr><td style='padding: 8px 0; font-weight: bold; width: 30%;'>Nombre:</td><td style='padding: 8px 0;'>" . htmlspecialchars($clientName) . "</td></tr>
                        <tr><td style='padding: 8px 0; font-weight: bold;'>Email:</td><td style='padding: 8px 0;'><a href='mailto:" . htmlspecialchars($clientEmail) . "'>" . htmlspecialchars($clientEmail) . "</a></td></tr>
                        <tr><td style='padding: 8px 0; font-weight: bold;'>Teléfono:</td><td style='padding: 8px 0;'><a href='https://wa.me/" . preg_replace('/[^0-9]/', '', $clientPhone) . "'>" . htmlspecialchars($clientPhone) . "</a></td></tr>
                    </table>
                </div>
                
                <div style='margin-bottom: 25px;'>
                    <h3 style='color: #333; border-bottom: 2px solid #2196F3; padding-bottom: 8px;'>🎯 Tipo de Proyecto</h3>
                    <p style='background-color: #e3f2fd; padding: 12px; border-radius: 5px; font-weight: bold;'>" . htmlspecialchars($tipoProyecto) . "</p>
                </div>
                
                <div style='margin-bottom: 25px;'>
                    <h3 style='color: #333; border-bottom: 2px solid #FF9800; padding-bottom: 8px;'>📝 Descripción de la Solicitud</h3>
                    <p style='background-color: #fff3e0; padding: 12px; border-radius: 5px; line-height: 1.6;'>" . nl2br(htmlspecialchars($descripcionSolicitud)) . "</p>
                </div>
                
                <div style='margin-bottom: 25px;'>
                    <h3 style='color: #333; border-bottom: 2px solid #9C27B0; padding-bottom: 8px;'>ℹ️ Detalles Adicionales</h3>
                    <p style='background-color: #f3e5f5; padding: 12px; border-radius: 5px; line-height: 1.6;'>" . nl2br(htmlspecialchars($detallesAdicionales)) . "</p>
                </div>
                
                <div style='background-color: #fff8e1; padding: 15px; border-radius: 5px; border-left: 4px solid #FFC107;'><p style='margin: 0; font-weight: bold; color: #F57C00;'>⚠️ ACCIÓN REQUERIDA:</p><p style='margin: 10px 0 0 0;'>Contactar al cliente para evaluar y preparar propuesta personalizada.</p></div>
                
                <hr style='border: none; border-top: 1px solid #eee; margin: 25px 0;'><p style='color: #777; font-size: 13px;'><strong>Fecha de solicitud:</strong> " . date('d/m/Y H:i:s') . "<br><strong>Canal:</strong> WhatsApp<br><strong>Sistema:</strong> Bot Automatizado CONLINEWEB</p>";
                $emailDespedida = "<strong>Por favor, dar seguimiento a esta solicitud lo antes posible.</strong>";

                $mailResult = enviarCorreo('servicios@conlineweb.com', $emailSubject, $emailTitulo, $emailCuerpo, $emailDespedida);

                if (!empty($mailResult['success'])) {
                    $toolOutput = json_encode([
                        'success' => true,
                        'message' => 'Solicitud especial enviada al equipo para evaluación',
                        'email_sent_to' => 'servicios@conlineweb.com'
                    ], JSON_UNESCAPED_UNICODE);
                    error_log("[WhatsApp Webhook] ✓ Solicitud especial enviada por correo a servicios@conlineweb.com");
                } else {
                    $toolOutput = json_encode([
                        'success' => false,
                        'error' => 'No se pudo notificar al equipo sobre la solicitud especial',
                        'details' => $mailResult
                    ], JSON_UNESCAPED_UNICODE);
                    error_log("[WhatsApp Webhook] ✗ Error al notificar solicitud especial: " . json_encode($mailResult));
                }

                // Añadir mensaje de sistema obligatorio que el asistente debe enviar al cliente (plantilla del prompt)
                $pendingContextMessages[] = [
                    "role" => "system",
                    "content" => "Perfecto, entiendo tu solicitud de {$tipoProyecto}. Este tipo de proyecto requiere una evaluación personalizada para darte el mejor presupuesto y solución adaptada a tus necesidades específicas.\n\nTe voy a transferir con un agente especializado que evaluará tu solicitud y te contactará a la brevedad para analizar los detalles y preparar una propuesta personalizada.\n\n¿Te parece bien? 😊"
                ];

                // Agregar la respuesta de herramienta y saltar registro de pre-pago
                $messages[] = ["role" => "tool", "tool_call_id" => $toolCallId, "content" => $toolOutput];
                continue;

            }

            // Agregar cliente_id si está disponible
            if (!empty($functionArgs['cliente_id'])) {
                $postData['cliente_id'] = $functionArgs['cliente_id'];
            } elseif (!empty($meta['cliente_id'])) {
                $postData['cliente_id'] = $meta['cliente_id'];
            }

            // Agregar dominio si está disponible
            if (!empty($functionArgs['dominio'])) {
                $postData['dominio'] = $functionArgs['dominio'];
            } elseif (!empty($meta['domain_name'])) {
                $postData['dominio'] = $meta['domain_name'];
            }

            // Agregar proyecto si está disponible
            if (!empty($functionArgs['proyecto'])) {
                $postData['proyecto'] = $functionArgs['proyecto'];
            } elseif (!empty($meta['project_name'])) {
                $postData['proyecto'] = $meta['project_name'];
            }

            // Agregar fecha límite si está disponible
            if (!empty($functionArgs['fecha_limite'])) {
                $postData['fecha_limite'] = $functionArgs['fecha_limite'];
            }

            error_log("[WhatsApp Webhook] ========== REGISTRAR SOLICITUD PRE-PAGO ==========");
            error_log("[WhatsApp Webhook] Datos enviados: " . json_encode($postData, JSON_UNESCAPED_UNICODE));

            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($postData),
                CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10
            ]);

            $apiResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            error_log("[WhatsApp Webhook] Código HTTP: " . $httpCode);
            error_log("[WhatsApp Webhook] Respuesta API: " . ($apiResponse ?: 'VACÍA'));
            if ($curlError) {
                error_log("[WhatsApp Webhook] Error cURL: " . $curlError);
            }

            if ($httpCode == 200 || $httpCode == 201) {
                $toolOutput = $apiResponse;
                
                // Guardar solicitud_id en meta para referencia futura
                $responseData = json_decode($apiResponse, true);
                if (!empty($responseData['success']) && !empty($responseData['solicitud_id'])) {
                    $meta['pending_solicitud_id'] = $responseData['solicitud_id'];
                    $meta['pending_payment_method'] = $postData['metodo_pago'];
                    file_put_contents($sessionMetaFile, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
                    error_log("[WhatsApp Webhook] ✓ Solicitud pre-pago registrada. ID: " . $responseData['solicitud_id']);
                    
                    // Si el método de pago es tarjeta, generar automáticamente el enlace de Stripe
                    if ($postData['metodo_pago'] === 'tarjeta') {
                        error_log("[WhatsApp Webhook] 🔄 Método tarjeta detectado. Generando enlace de pago automáticamente...");
                        
                        $stripeApiUrl = "https://adm.conlineweb.com/api/generar_pago_stripe.php";
                        $stripePostData = [
                            'solicitud_id' => $responseData['solicitud_id'],
                            'monto' => $postData['precio_acordado'],
                            'descripcion' => $postData['titulo']
                        ];
                        
                        // Agregar email si está disponible
                        if (!empty($meta['client_email'])) {
                            $stripePostData['client_email'] = $meta['client_email'];
                        }
                        
                        error_log("[WhatsApp Webhook] Datos para Stripe: " . json_encode($stripePostData, JSON_UNESCAPED_UNICODE));
                        
                        $stripeCh = curl_init($stripeApiUrl);
                        curl_setopt_array($stripeCh, [
                            CURLOPT_POST => true,
                            CURLOPT_POSTFIELDS => json_encode($stripePostData),
                            CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_TIMEOUT => 10
                        ]);
                        
                        $stripeResponse = curl_exec($stripeCh);
                        $stripeHttpCode = curl_getinfo($stripeCh, CURLINFO_HTTP_CODE);
                        curl_close($stripeCh);
                        
                        error_log("[WhatsApp Webhook] Stripe HTTP Code: " . $stripeHttpCode);
                        error_log("[WhatsApp Webhook] Stripe Response: " . ($stripeResponse ?: 'VACÍA'));
                        
                        if ($stripeHttpCode == 200 || $stripeHttpCode == 201) {
                            $stripeData = json_decode($stripeResponse, true);
                            if (!empty($stripeData['success']) && !empty($stripeData['payment_url'])) {
                                error_log("[WhatsApp Webhook] ✓ Enlace de pago generado: " . $stripeData['payment_url']);
                                
                                // Modificar toolOutput para incluir el enlace de pago
                                $responseData['payment_url'] = $stripeData['payment_url'];
                                $responseData['stripe_session_id'] = $stripeData['stripe_session_id'];
                                $toolOutput = json_encode($responseData, JSON_UNESCAPED_UNICODE);
                                
                                // Agregar mensaje de sistema para que GPT envíe el enlace
                                $pendingContextMessages[] = [
                                    "role" => "system",
                                    "content" => "[INSTRUCCIÓN URGENTE] Se generó el enlace de pago: {$stripeData['payment_url']}. DEBES enviar este enlace al cliente INMEDIATAMENTE con un mensaje amigable indicando que puede proceder con el pago."
                                ];
                            } else {
                                error_log("[WhatsApp Webhook] ✗ Error al generar enlace de pago Stripe");
                            }
                        } else {
                            error_log("[WhatsApp Webhook] ✗ Error HTTP al llamar API Stripe: " . $stripeHttpCode);
                        }
                    }
                }
            } else {
                $toolOutput = json_encode(["success" => false, "message" => "No se pudo registrar la solicitud. Código: $httpCode"]);
                error_log("[WhatsApp Webhook] ✗ Error al registrar solicitud pre-pago");
            }

            $messages[] = ["role" => "tool", "tool_call_id" => $toolCallId, "content" => $toolOutput];

            // --- E) VERIFICAR ESTADO PAGO ---
        } elseif ($functionName === 'verificar_estado_pago') {
            $apiUrl = "https://adm.conlineweb.com/api/verificar_estado_pago.php";

            $postData = [
                'solicitud_id' => $functionArgs['solicitud_id']
            ];

            error_log("[WhatsApp Webhook] ========== VERIFICAR ESTADO PAGO ==========");
            error_log("[WhatsApp Webhook] Datos enviados: " . json_encode($postData, JSON_UNESCAPED_UNICODE));

            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($postData),
                CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10
            ]);

            $apiResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            error_log("[WhatsApp Webhook] Código HTTP: " . $httpCode);
            error_log("[WhatsApp Webhook] Respuesta API: " . ($apiResponse ?: 'VACÍA'));
            if ($curlError) {
                error_log("[WhatsApp Webhook] Error cURL: " . $curlError);
            }

            if ($httpCode == 200 || $httpCode == 201) {
                $toolOutput = $apiResponse;
                
                // Agregar instrucción al modelo sobre cómo proceder
                $verifyData = json_decode($apiResponse, true);
                if (!empty($verifyData['success'])) {
                    if ($verifyData['pago_confirmado']) {
                        error_log("[WhatsApp Webhook] ✓ Pago CONFIRMADO en BD - Ticket ya existe: " . ($verifyData['ticket_id'] ?: 'pendiente'));
                        
                        if (!empty($verifyData['ticket_id'])) {
                            $pendingContextMessages[] = [
                                "role" => "system",
                                "content" => "[VERIFICACIÓN EXITOSA] El pago YA fue confirmado anteriormente y el ticket #{$verifyData['ticket_id']} ya existe. Informa al cliente que su ticket ya está activo y en proceso."
                            ];
                        } else {
                            $pendingContextMessages[] = [
                                "role" => "system",
                                "content" => "[VERIFICACIÓN EXITOSA] El pago fue confirmado pero no se creó ticket. Puedes proceder a llamar confirmar_pago_solicitud para crear el ticket ahora."
                            ];
                        }
                    } else {
                        error_log("[WhatsApp Webhook] ✗ Pago NO CONFIRMADO en BD");
                        
                        if ($verifyData['metodo_pago'] === 'tarjeta') {
                            $pendingContextMessages[] = [
                                "role" => "system",
                                "content" => "[PAGO NO DETECTADO] El sistema NO ha recibido confirmación del pago con tarjeta. El cliente debe usar el enlace de pago proporcionado. NO crear ticket hasta que el pago sea confirmado automáticamente por Stripe."
                            ];
                        } else {
                            $pendingContextMessages[] = [
                                "role" => "system",
                                "content" => "[PAGO NO DETECTADO] El sistema NO ha recibido confirmación de la transferencia. Informa al cliente que debe enviar su comprobante de pago para que el equipo valide y confirme manualmente. NO crear ticket hasta que se confirme el pago."
                            ];
                        }
                    }
                }
            } else {
                $toolOutput = json_encode(["success" => false, "message" => "No se pudo verificar el estado del pago. Código: $httpCode"]);
                error_log("[WhatsApp Webhook] ✗ Error al verificar estado de pago");
            }

            $messages[] = ["role" => "tool", "tool_call_id" => $toolCallId, "content" => $toolOutput];

            // --- F) CONFIRMAR PAGO SOLICITUD ---
        } elseif ($functionName === 'confirmar_pago_solicitud') {
            $apiUrl = "https://adm.conlineweb.com/api/confirmar_pago_solicitud.php";

            $postData = [
                'solicitud_id' => $functionArgs['solicitud_id']
            ];

            if (!empty($functionArgs['stripe_payment_id'])) {
                $postData['stripe_payment_id'] = $functionArgs['stripe_payment_id'];
            }

            error_log("[WhatsApp Webhook] ========== CONFIRMAR PAGO SOLICITUD ==========");
            error_log("[WhatsApp Webhook] Datos enviados: " . json_encode($postData, JSON_UNESCAPED_UNICODE));

            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($postData),
                CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10
            ]);

            $apiResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            error_log("[WhatsApp Webhook] Código HTTP: " . $httpCode);
            error_log("[WhatsApp Webhook] Respuesta API: " . ($apiResponse ?: 'VACÍA'));
            if ($curlError) {
                error_log("[WhatsApp Webhook] Error cURL: " . $curlError);
            }

            if ($httpCode == 200 || $httpCode == 201) {
                $toolOutput = $apiResponse;
                
                // Limpiar metadata de pago pendiente
                if (isset($meta['pending_solicitud_id'])) {
                    unset($meta['pending_solicitud_id']);
                    unset($meta['pending_payment_method']);
                    file_put_contents($sessionMetaFile, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
                    error_log("[WhatsApp Webhook] ✓ Pago confirmado y ticket creado. Metadata limpiada.");
                }
            } else {
                $toolOutput = json_encode(["success" => false, "message" => "No se pudo confirmar el pago. Código: $httpCode"]);
                error_log("[WhatsApp Webhook] ✗ Error al confirmar pago");
            }

            $messages[] = ["role" => "tool", "tool_call_id" => $toolCallId, "content" => $toolOutput];

            // --- G) CREAR TICKET DIRECTO (VIP / SIN PAGO) ---
        } elseif ($functionName === 'crear_ticket_servicio') {
            // Evitar duplicados en VIP si el modelo dispara múltiples tool_calls en el mismo mensaje
            if (!empty($esClienteVIP) && $vipTicketCallProcessed) {
                $toolOutput = json_encode([
                    'success' => false,
                    'error' => 'Cliente VIP: ya se procesó una creación de ticket en este mensaje. Evita duplicar la solicitud.'
                ], JSON_UNESCAPED_UNICODE);
                $messages[] = ["role" => "tool", "tool_call_id" => $toolCallId, "content" => $toolOutput];
                continue;
            }

            // API v2 para crear ticket por teléfono/dominio/cliente_id
            $apiUrl = "https://adm.conlineweb.com/api/crear_ticket_whatsapp.php";

            // Construir payload con fallback a meta y teléfono actual
            $postData = [
                'telefono' => !empty($functionArgs['telefono']) ? $functionArgs['telefono'] : $from_whatsapp_e164,
                'titulo' => $functionArgs['titulo'] ?? '',
                'descripcion' => $functionArgs['descripcion'] ?? '',
                'prioridad' => $functionArgs['prioridad'] ?? 'media'
            ];

            // Añadir en la descripción el nombre y teléfono del solicitante
            $requesterName = '';
            if (!empty($datosVIP['nombre'])) {
                $requesterName = $datosVIP['nombre'];
            } elseif (!empty($meta['client_name'])) {
                $requesterName = $meta['client_name'];
            }
            $phoneRequester = $postData['telefono'] ?? $from_whatsapp_e164;
            $requesterDisplay = trim(($requesterName ? $requesterName . ' ' : '') . '(' . $phoneRequester . ')');
            $postData['descripcion'] = trim((string)$postData['descripcion']);
            if (!empty($postData['descripcion'])) {
                $postData['descripcion'] .= "\n\nSolicitado por: " . $requesterDisplay;
            } else {
                $postData['descripcion'] = "Solicitado por: " . $requesterDisplay;
            }

            error_log("[WhatsApp Webhook] Descripción enviada (con requester): " . $postData['descripcion']);

            // ENFOQUE MEJORADO: calcular fecha_limite según COMPLEJIDAD del ticket y carga actual
            // 1) Si ya viene fecha_limite explícita, usarla
            if (!empty($functionArgs['fecha_limite'])) {
                $postData['fecha_limite'] = $functionArgs['fecha_limite'];
                $tiempoEstimado = 'Ver fecha proporcionada por el cliente';
            } else {
                // Inferir complejidad a partir de título/descripcion
                $titleDesc = trim(($postData['titulo'] ?? '') . ' ' . ($postData['descripcion'] ?? ''));
                $complexity = 'media'; // por defecto

                // Simple
                if (preg_match('/\b(imagen|imagenes|foto|fotos|titulo|texto|cambiar imagen|cambio de imagen|ajuste|ajustes|minor|pequeñ[ao])\b/i', $titleDesc)) {
                    $complexity = 'simple';
                }
                // Complejo
                if (preg_match('/\b(tienda|e(-)?commerce|ecommerce|carrito|checkout|pasarela|sistema|plataforma|sitio completo|rediseñ|re-diseñ|sitio nuevo|integraci[oó]n|api|dashboard)\b/i', $titleDesc)) {
                    $complexity = 'complejo';
                }
                // Medio si menciona optimización o formularios
                if (preg_match('/\b(optimiza|seo|formulario|correcci[oó]n|error|reparaci[oó]n)\b/i', $titleDesc) && $complexity !== 'complejo') {
                    $complexity = 'media';
                }

                // Rangos base por complejidad
                $range = [3, 5];
                $tiempoEstimado = '2-3 días hábiles';
                if ($complexity === 'simple') {
                    $range = [2, 3];
                    $tiempoEstimado = '2-3 días hábiles';
                } elseif ($complexity === 'media') {
                    $range = [5, 7];
                    $tiempoEstimado = '5-7 días hábiles';
                } elseif ($complexity === 'complejo') {
                    $range = [10, 15];
                    $tiempoEstimado = '10-15 días hábiles';
                }

                // Ajuste por prioridad
                $prioTmp = strtolower((string)($postData['prioridad'] ?? 'media'));
                if ($prioTmp === 'alta') {
                    // reducir 1 día en el límite inferior si es posible
                    $range[0] = max(1, $range[0] - 1);
                } elseif ($prioTmp === 'baja') {
                    // aumentar 1 día en el límite superior
                    $range[1] = $range[1] + 1;
                }

                // Consultar tickets pendientes para ajustar carga
                $extraDays = 0;
                $pendingApi = 'https://adm.conlineweb.com/api/consultar_tickets_pendientes.php';
                $ch2 = curl_init($pendingApi);
                curl_setopt_array($ch2, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => '{}',
                    CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 6,
                    CURLOPT_SSL_VERIFYPEER => false
                ]);
                $pendingResp = curl_exec($ch2);
                $pendingHttp = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                $curlErr = curl_error($ch2);
                curl_close($ch2);

                if (($pendingHttp == 200 || $pendingHttp == 201) && !empty($pendingResp)) {
                    $parsedPending = json_decode($pendingResp, true);
                    $countPending = 0;
                    if (!empty($parsedPending['success']) && !empty($parsedPending['tickets'])) {
                        foreach ($parsedPending['tickets'] as $t) {
                            $status = strtolower($t['estado'] ?? '');
                            if (in_array($status, ['pendiente', 'en proceso', 'en_proceso', 'en-proceso'])) {
                                $countPending++;
                            }
                        }
                    }

                    // Si hay más de 5 tickets pendientes, añadir 2 días; si >10, añadir 4 días
                    if ($countPending > 10) {
                        $extraDays = 4;
                    } elseif ($countPending > 5) {
                        $extraDays = 2;
                    }

                    error_log("[WhatsApp Webhook] Info carga: tickets pendientes = " . $countPending . ", extraDays = " . $extraDays);
                } else {
                    error_log("[WhatsApp Webhook] No se pudo obtener tickets pendientes: HTTP=" . $pendingHttp . " err=" . $curlErr . " resp=" . ($pendingResp ?: 'EMPTY'));
                }

                // Calcular días finales como el punto medio del rango + extraDays
                $mid = (int) round(($range[0] + $range[1]) / 2);
                $finalDays = max(1, $mid + $extraDays);

                $postData['fecha_limite'] = date('Y-m-d', strtotime('+' . $finalDays . ' days'));
                // Ajustar texto de tiempo estimado para incluir carga extra si aplica
                if ($extraDays > 0) {
                    $tiempoEstimado .= " (carga actual: +{$extraDays} días)";
                }

                error_log("[WhatsApp Webhook] Fecha límite calculada: " . $postData['fecha_limite'] . " (complejidad: " . $complexity . ", rango: " . implode('-', $range) . ", finalDays: " . $finalDays . ")");
            }

            if (!empty($functionArgs['cliente_id'])) {
                $postData['cliente_id'] = $functionArgs['cliente_id'];
            } elseif (!empty($meta['cliente_id'])) {
                $postData['cliente_id'] = $meta['cliente_id'];
            }

            if (!empty($functionArgs['domain_name'])) {
                $postData['domain_name'] = $functionArgs['domain_name'];
            } elseif (!empty($meta['domain_name'])) {
                $postData['domain_name'] = $meta['domain_name'];
            }

            error_log("[WhatsApp Webhook] ========== CREAR TICKET DIRECTO (VIP) ==========");
            error_log("[WhatsApp Webhook] URL API: " . $apiUrl);
            error_log("[WhatsApp Webhook] Datos enviados: " . json_encode($postData, JSON_UNESCAPED_UNICODE));

            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($postData),
                CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false
            ]);

            $apiResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            error_log("[WhatsApp Webhook] Código HTTP: " . $httpCode);
            error_log("[WhatsApp Webhook] Respuesta API: " . ($apiResponse ?: 'VACÍA'));
            if ($curlError) {
                error_log("[WhatsApp Webhook] Error cURL: " . $curlError);
            }

            if ($httpCode == 200 || $httpCode == 201) {
                $toolOutput = $apiResponse;
                if (!empty($esClienteVIP)) {
                    $vipTicketCallProcessed = true;
                }

                // Para VIP: responder con ticket_id real (sin pasar por segunda llamada a OpenAI)
                if (!empty($esClienteVIP)) {
                    $parsed = json_decode($apiResponse, true);
                    $ticketId = $parsed['data']['ticket_id'] ?? $parsed['ticket_id'] ?? null;

                    $clientName = '';
                    if (!empty($datosVIP['nombre'])) {
                        $clientName = $datosVIP['nombre'];
                    } elseif (!empty($meta['client_name'])) {
                        $clientName = $meta['client_name'];
                    }

                    $saludoNombre = $clientName ? (", " . $clientName) : '';

                    $prioridad = strtolower($postData['prioridad'] ?? 'media');
                    $tiempoEstimado = '2-3 días hábiles';
                    if ($prioridad === 'alta') {
                        $tiempoEstimado = '1-2 días hábiles';
                    } elseif ($prioridad === 'baja') {
                        $tiempoEstimado = '3-5 días hábiles';
                    }

                    $tituloResumen = trim((string)($postData['titulo'] ?? ''));
                    if ($tituloResumen === '') {
                        $tituloResumen = 'tu solicitud';
                    }

                    if (!empty($ticketId)) {
                        $phoneRequester = $postData['telefono'] ?? $from_whatsapp_e164;
                        $requesterName = $postData['cliente_id'] ? ($clientName ?? '') : ($meta['client_name'] ?? '');
                        $requesterDisplay = trim(($requesterName ? $requesterName . ' ' : '') . '(' . $phoneRequester . ')');
                        $farewellMessage = "Perfecto{$saludoNombre}, entendido.\n\n✅ He registrado tu solicitud de {$tituloResumen}.\n\nNuestro equipo se pondrá en contacto contigo para coordinar los detalles.\n\n📋 Número de ticket: #{$ticketId}\n📞 Solicitado por: {$requesterDisplay}\n⏱️ Tiempo estimado: {$tiempoEstimado}\n\n¿Hay algo más en lo que pueda ayudarte?";
                    } else {
                        // Si por alguna razón no viene ticket_id, mostrar el error para depurar
                        $phoneRequester = $postData['telefono'] ?? $from_whatsapp_e164;
                        $requesterName = $postData['cliente_id'] ? ($clientName ?? '') : ($meta['client_name'] ?? '');
                        $requesterDisplay = trim(($requesterName ? $requesterName . ' ' : '') . '(' . $phoneRequester . ')');
                        $farewellMessage = "Perfecto{$saludoNombre}, entendido.\n\n✅ He registrado tu solicitud de {$tituloResumen}.\n\nNuestro equipo se pondrá en contacto contigo para coordinar los detalles.\n\n📞 Solicitado por: {$requesterDisplay}";
                        error_log('[WhatsApp Webhook] ⚠️ VIP: respuesta sin ticket_id. API response: ' . ($apiResponse ?: 'VACÍA'));
                    }

                    $messages[] = ["role" => "assistant", "content" => $farewellMessage];
                    $skipFinalOpenAI = true;
                }
            } else {
                // Incluir respuesta del API si existe para que el modelo vea el error real
                $toolOutput = json_encode([
                    "success" => false,
                    "message" => "No se pudo crear el ticket directo. Código: $httpCode",
                    "api_response" => $apiResponse
                ], JSON_UNESCAPED_UNICODE);

                // Para VIP: NO permitir que el modelo confirme ticket si falló el insert
                if (!empty($esClienteVIP)) {
                    $clientName = '';
                    if (!empty($datosVIP['nombre'])) {
                        $clientName = $datosVIP['nombre'];
                    } elseif (!empty($meta['client_name'])) {
                        $clientName = $meta['client_name'];
                    }
                    $saludoNombre = $clientName ? (", " . $clientName) : '';
                    $phoneRequester = $postData['telefono'] ?? $from_whatsapp_e164;

                    $requesterName = $postData['cliente_id'] ? ($clientName ?? '') : ($meta['client_name'] ?? '');
                    $requesterDisplay = trim(($requesterName ? $requesterName . ' ' : '') . '(' . $phoneRequester . ')');
                    $farewellMessage = "Perfecto{$saludoNombre}.\n\nTuve un problema técnico al registrar el ticket en el sistema (no se generó folio).\n\nNuestro equipo lo revisará para registrarlo manualmente y confirmarte el número de ticket.\n\n📞 Solicitado por: {$requesterDisplay}\n\n¿Me confirmas si el dominio es lineaitalia.com?";
                    $messages[] = ["role" => "assistant", "content" => $farewellMessage];
                    $skipFinalOpenAI = true;
                }
            }

            $messages[] = ["role" => "tool", "tool_call_id" => $toolCallId, "content" => $toolOutput];

            // --- H) CREAR TICKET SIMPLE (DEPRECATED - Usar registrar_solicitud_prepago) ---
        } elseif ($functionName === 'crear_ticket_simple') {
            // Usar nueva API v2 que acepta múltiples formas de identificación
            $apiUrl = "https://adm.conlineweb.com/api/crear_ticket_whatsapp.php";

            $postData = [
                'telefono' => $from_whatsapp_e164,
                'titulo' => $functionArgs['titulo'],
                'descripcion' => $functionArgs['descripcion'],
                'prioridad' => $functionArgs['prioridad'] ?? 'media'
            ];

            // Añadir nombre y teléfono del solicitante en la descripción
            $requesterName = !empty($meta['client_name']) ? $meta['client_name'] : '';
            $phoneRequester = $from_whatsapp_e164;
            $requesterDisplay = trim(($requesterName ? $requesterName . ' ' : '') . '(' . $phoneRequester . ')');
            $postData['descripcion'] = trim((string)$postData['descripcion']);
            if (!empty($postData['descripcion'])) {
                $postData['descripcion'] .= "\n\nSolicitado por: " . $requesterDisplay;
            } else {
                $postData['descripcion'] = "Solicitado por: " . $requesterDisplay;
            }

            // Si ya tenemos cliente_id de consultar_datos_cliente, pasarlo
            if (!empty($meta['cliente_id'])) {
                $postData['cliente_id'] = $meta['cliente_id'];
            }

            // Si tenemos domain_name en meta, pasarlo también
            if (!empty($meta['domain_name'])) {
                $postData['domain_name'] = $meta['domain_name'];
            }

            if (!empty($functionArgs['fecha_limite'])) {
                $postData['fecha_limite'] = $functionArgs['fecha_limite'];
            }

            // LOG: Datos que se enviarán a la API
            error_log("[WhatsApp Webhook] === CREAR_TICKET_SIMPLE ===");
            error_log("[WhatsApp Webhook] URL API: " . $apiUrl);
            error_log("[WhatsApp Webhook] Datos enviados: " . json_encode($postData, JSON_UNESCAPED_UNICODE));
            error_log("[WhatsApp Webhook] Meta disponible: " . json_encode($meta, JSON_UNESCAPED_UNICODE));

            // LOG: Datos que se enviarán a la API
            error_log("[WhatsApp Webhook] === CREAR_TICKET_SIMPLE ===");
            error_log("[WhatsApp Webhook] URL API: " . $apiUrl);
            error_log("[WhatsApp Webhook] Datos enviados: " . json_encode($postData, JSON_UNESCAPED_UNICODE));
            error_log("[WhatsApp Webhook] Meta disponible: " . json_encode($meta, JSON_UNESCAPED_UNICODE));

            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($postData),
                CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10
            ]);

            $apiResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            // LOG: Respuesta de la API
            error_log("[WhatsApp Webhook] Código HTTP: " . $httpCode);
            error_log("[WhatsApp Webhook] Respuesta API: " . ($apiResponse ?: 'VACÍA'));
            if ($curlError) {
                error_log("[WhatsApp Webhook] Error cURL: " . $curlError);
            }

            if ($httpCode == 200 || $httpCode == 201) {
                $toolOutput = $apiResponse;
                error_log("[WhatsApp Webhook] ✓ Ticket creado exitosamente");
            } else {
                $toolOutput = json_encode(["success" => false, "message" => "No se pudo crear el ticket. Código: $httpCode", "response" => $apiResponse]);
                error_log("[WhatsApp Webhook] ✗ Error al crear ticket. Código: $httpCode");
            }

            $messages[] = ["role" => "tool", "tool_call_id" => $toolCallId, "content" => $toolOutput];

            // --- I) TICKET NO CREADO ---
        } elseif ($functionName === 'ticket_no_creado') {
            $apiUrl = "https://adm.conlineweb.com/api/marcar_no_creado.php";

            if (empty($functionArgs['client_phone'])) {
                $functionArgs['client_phone'] = $from_whatsapp_e164;
            } else {
                $functionArgs['client_phone'] = normalize_whatsapp_phone_e164($functionArgs['client_phone']);
            }

            $payload = [
                'payload' => [
                    'client_phone' => $functionArgs['client_phone']
                ]
            ];
            if (!empty($functionArgs['reason'])) {
                $payload['payload']['reason'] = $functionArgs['reason'];
            }

            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 8
            ]);
            $apiResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode == 200 || $httpCode == 201) {
                $toolOutput = $apiResponse;
            } else {
                $toolOutput = json_encode(["success" => false, "message" => "No se pudo marcar como no creado. Código: $httpCode"]);
            }

            $messages[] = ["role" => "tool", "tool_call_id" => $toolCallId, "content" => $toolOutput];

            $farewellMessage = "Gracias por tu tiempo y por considerar a CONLINEWEB. Si decides continuar o tienes alguna otra consulta, aquí estaremos para ayudarte. ¡Que tengas un excelente día! 😊";
            $messages[] = ["role" => "assistant", "content" => $farewellMessage];
            $skipFinalOpenAI = true;

            // --- J) SOLICITAR EVALUACIÓN ESPECIAL ---
        } elseif ($functionName === 'consultar_catalogo') {
            $queryParams = [];
            if (!empty($functionArgs['q']))         $queryParams['q']        = $functionArgs['q'];
            if (!empty($functionArgs['categoria'])) $queryParams['categoria'] = $functionArgs['categoria'];
            if (!empty($functionArgs['servicio']))   $queryParams['servicio']  = $functionArgs['servicio'];
            if (!empty($functionArgs['moneda']))     $queryParams['moneda']    = $functionArgs['moneda'];

            $normalizeCatalogCategory = function ($value) {
                if ($value === null) {
                    return null;
                }

                $value = trim((string) $value);
                if ($value === '') {
                    return null;
                }

                $valueLower = mb_strtolower($value, 'UTF-8');

                if (preg_match('/webs?\s+informativas?|p[aá]ginas?\s+web|web\s*site|sitios?\s+web|sitio\s+web\s+corporativo|landing|one\s*page|corporativ|institucional|informativo/', $valueLower)) {
                    return 'Webs Informativas';
                }
                if (preg_match('/e-?commerce|tienda|carrito|venta\s+en\s+l[ií]nea|compras\s+en\s+l[ií]nea|shop/', $valueLower)) {
                    return 'E-commerce';
                }
                if (preg_match('/mantenimiento|soporte|actualiz|correcci[oó]n|error|bug/', $valueLower)) {
                    return 'Mantenimiento';
                }
                if (preg_match('/dominio|hosting|ssl|correo|mail|email|migraci[oó]n/', $valueLower)) {
                    return 'Dominios y Hosting';
                }
                if (preg_match('/seo|marketing|google|analytics|pixel|meta|facebook|posicionamiento/', $valueLower)) {
                    return 'SEO y Marketing';
                }
                if (preg_match('/servicios?\s+peque[nñ]os?|logo|banner|formulario|texto|imagen|bot[oó]n|enlace|ajuste/', $valueLower)) {
                    return 'Servicios Pequeños';
                }

                return $value;
            };

            $getFallbackQueriesForCategory = function ($category) {
                switch ($category) {
                    case 'Webs Informativas':
                        return ['pagina web', 'sitio web', 'sitio web corporativo'];
                    case 'E-commerce':
                        return ['tienda en linea', 'ecommerce', 'carrito de compras'];
                    case 'Mantenimiento':
                        return ['mantenimiento web', 'soporte web', 'correccion de errores'];
                    case 'Dominios y Hosting':
                        return ['hosting', 'dominio', 'ssl'];
                    case 'SEO y Marketing':
                        return ['seo', 'marketing digital', 'google'];
                    case 'Servicios Pequeños':
                        return ['cambio de texto', 'cambio de imagen', 'formulario'];
                    default:
                        return [];
                }
            };

            $fetchCatalog = function ($params, $logLabel) {
                $apiUrl = "https://adm.conlineweb.com/api/catalogo_servicios.php"
                        . (!empty($params) ? '?' . http_build_query($params) : '');

                error_log("[WhatsApp Webhook] {$logLabel} URL: " . $apiUrl);

                $ch = curl_init($apiUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 8,
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);
                $response = curl_exec($ch);
                $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                error_log("[WhatsApp Webhook] {$logLabel} HTTP: " . $code . " | Respuesta: " . substr($response ?: 'VACÍA', 0, 300));

                return [$apiUrl, $response, $code];
            };

            if (!empty($queryParams['categoria'])) {
                $queryParams['categoria'] = $normalizeCatalogCategory($queryParams['categoria']);
            }

            error_log("[WhatsApp Webhook] ========== CONSULTAR CATÁLOGO ==========");

            [$apiUrl, $apiResponse, $httpCode] = $fetchCatalog($queryParams, 'Catálogo inicial');

            if ($httpCode === 200 && $apiResponse) {
                $parsedCat = json_decode($apiResponse, true);
                $total = (int) ($parsedCat['total'] ?? 0);

                if (!empty($parsedCat['success']) && $total === 0) {
                    if (!empty($queryParams['q']) && empty($queryParams['categoria'])) {
                        $categoryFallback = $normalizeCatalogCategory($queryParams['q']);

                        if ($categoryFallback && $categoryFallback !== $queryParams['q']) {
                            $fallbackParams = ['categoria' => $categoryFallback];
                            if (!empty($queryParams['moneda'])) {
                                $fallbackParams['moneda'] = $queryParams['moneda'];
                            }

                            error_log("[WhatsApp Webhook] 🔄 Catálogo: 0 resultados con q='{$queryParams['q']}'. Reintentando con categoria='{$categoryFallback}'");
                            [, $fallbackResponse, $fallbackCode] = $fetchCatalog($fallbackParams, 'Catálogo fallback categoria');

                            if ($fallbackCode === 200 && $fallbackResponse) {
                                $apiResponse = $fallbackResponse;
                                $httpCode = $fallbackCode;
                            }
                        }
                    } elseif (!empty($queryParams['categoria'])) {
                        $categoryFallbackQueries = $getFallbackQueriesForCategory($queryParams['categoria']);

                        foreach ($categoryFallbackQueries as $fallbackQuery) {
                            $fallbackParams = ['q' => $fallbackQuery];
                            if (!empty($queryParams['moneda'])) {
                                $fallbackParams['moneda'] = $queryParams['moneda'];
                            }

                            error_log("[WhatsApp Webhook] 🔄 Catálogo: 0 resultados con categoria='{$queryParams['categoria']}'. Reintentando con q='{$fallbackQuery}'");
                            [, $fallbackResponse, $fallbackCode] = $fetchCatalog($fallbackParams, 'Catálogo fallback q');

                            if ($fallbackCode !== 200 || !$fallbackResponse) {
                                continue;
                            }

                            $fallbackParsed = json_decode($fallbackResponse, true);
                            if (!empty($fallbackParsed['success']) && (int) ($fallbackParsed['total'] ?? 0) > 0) {
                                $apiResponse = $fallbackResponse;
                                $httpCode = $fallbackCode;
                                error_log("[WhatsApp Webhook] ✓ Fallback catálogo exitoso con q='{$fallbackQuery}'");
                                break;
                            }
                        }
                    }
                }
            }

            $toolOutput = ($httpCode === 200 && $apiResponse)
                ? $apiResponse
                : json_encode(['success' => false, 'error' => 'No se pudo obtener el catálogo. HTTP ' . $httpCode]);

            $messages[] = ["role" => "tool", "tool_call_id" => $toolCallId, "content" => $toolOutput];

        } elseif ($functionName === 'solicitar_evaluacion_especial') {            error_log("[WhatsApp Webhook] === SOLICITAR EVALUACIÓN ESPECIAL ===");
            error_log("[WhatsApp Webhook] Argumentos: " . json_encode($functionArgs, JSON_UNESCAPED_UNICODE));

            // Preparar datos del cliente
            $clientName = $functionArgs['client_name'] ?? 'No especificado';
            $clientEmail = $functionArgs['client_email'] ?? 'No especificado';
            $clientPhone = $functionArgs['client_phone'] ?? $from_whatsapp_e164;
            $tipoProyecto = $functionArgs['tipo_proyecto'] ?? 'No especificado';
            $descripcionSolicitud = $functionArgs['descripcion_solicitud'] ?? '';
            $detallesAdicionales = $functionArgs['detalles_adicionales'] ?? 'Ninguno';

            // Construir el contenido del correo HTML
            $emailSubject = "🚨 Solicitud Especial de WhatsApp - " . htmlspecialchars($tipoProyecto);
            $emailTitulo = "Solicitud de Evaluación Especial";
            
            $emailCuerpo = "
                <div style='background-color: #f8f9fa; padding: 15px; border-left: 4px solid #ff6b6b; margin-bottom: 20px;'>
                    <h3 style='color: #ff6b6b; margin-top: 0;'>🚨 Nueva Solicitud Especial</h3>
                    <p style='margin: 0;'>Se ha recibido una solicitud especial que requiere evaluación personalizada.</p>
                </div>
                
                <div style='margin-bottom: 25px;'>
                    <h3 style='color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 8px;'>👤 Datos del Cliente</h3>
                    <table style='width: 100%; margin-top: 10px;'>
                        <tr><td style='padding: 8px 0; font-weight: bold; width: 30%;'>Nombre:</td><td style='padding: 8px 0;'>" . htmlspecialchars($clientName) . "</td></tr>
                        <tr><td style='padding: 8px 0; font-weight: bold;'>Email:</td><td style='padding: 8px 0;'><a href='mailto:" . htmlspecialchars($clientEmail) . "'>" . htmlspecialchars($clientEmail) . "</a></td></tr>
                        <tr><td style='padding: 8px 0; font-weight: bold;'>Teléfono:</td><td style='padding: 8px 0;'><a href='https://wa.me/" . preg_replace('/[^0-9]/', '', $clientPhone) . "'>" . htmlspecialchars($clientPhone) . "</a></td></tr>
                    </table>
                </div>
                
                <div style='margin-bottom: 25px;'>
                    <h3 style='color: #333; border-bottom: 2px solid #2196F3; padding-bottom: 8px;'>🎯 Tipo de Proyecto</h3>
                    <p style='background-color: #e3f2fd; padding: 12px; border-radius: 5px; font-weight: bold;'>" . htmlspecialchars($tipoProyecto) . "</p>
                </div>
                
                <div style='margin-bottom: 25px;'>
                    <h3 style='color: #333; border-bottom: 2px solid #FF9800; padding-bottom: 8px;'>📝 Descripción de la Solicitud</h3>
                    <p style='background-color: #fff3e0; padding: 12px; border-radius: 5px; line-height: 1.6;'>" . nl2br(htmlspecialchars($descripcionSolicitud)) . "</p>
                </div>
                
                <div style='margin-bottom: 25px;'>
                    <h3 style='color: #333; border-bottom: 2px solid #9C27B0; padding-bottom: 8px;'>ℹ️ Detalles Adicionales</h3>
                    <p style='background-color: #f3e5f5; padding: 12px; border-radius: 5px; line-height: 1.6;'>" . nl2br(htmlspecialchars($detallesAdicionales)) . "</p>
                </div>
                
                <div style='background-color: #fff8e1; padding: 15px; border-radius: 5px; border-left: 4px solid #FFC107;'>
                    <p style='margin: 0; font-weight: bold; color: #F57C00;'>⚠️ ACCIÓN REQUERIDA:</p>
                    <p style='margin: 10px 0 0 0;'>Contactar al cliente para evaluar y preparar propuesta personalizada.</p>
                </div>
                
                <hr style='border: none; border-top: 1px solid #eee; margin: 25px 0;'>
                
                <p style='color: #777; font-size: 13px;'>
                    <strong>Fecha de solicitud:</strong> " . date('d/m/Y H:i:s') . "<br>
                    <strong>Canal:</strong> WhatsApp<br>
                    <strong>Sistema:</strong> Bot Automatizado CONLINEWEB
                </p>
            ";
            
            $emailDespedida = "<strong>Por favor, dar seguimiento a esta solicitud lo antes posible.</strong>";

            // Enviar correo usando PHPMailer
            $mailResult = enviarCorreo('servicios@conlineweb.com', $emailSubject, $emailTitulo, $emailCuerpo, $emailDespedida);
            $mailSent = $mailResult['success'];

            if ($mailSent) {
                $toolOutput = json_encode([
                    'success' => true,
                    'message' => 'Solicitud de evaluación especial enviada correctamente al equipo',
                    'email_sent_to' => 'servicios@conlineweb.com'
                ], JSON_UNESCAPED_UNICODE);
                error_log("[WhatsApp Webhook] ✓ Email de solicitud especial enviado a servicios@conlineweb.com");
            } else {
                $errorDetails = isset($mailResult['error']) ? $mailResult['error'] : 'Error desconocido';
                $toolOutput = json_encode([
                    'success' => false,
                    'error' => 'No se pudo enviar el correo de notificación',
                    'message' => 'El sistema registró la solicitud pero hubo un problema al notificar al equipo',
                    'details' => $errorDetails
                ], JSON_UNESCAPED_UNICODE);
                error_log("[WhatsApp Webhook] ✗ Error al enviar email de solicitud especial: " . $errorDetails);
            }

            $messages[] = ["role" => "tool", "tool_call_id" => $toolCallId, "content" => $toolOutput];
        }
    }
    if (!empty($pendingContextMessages)) {
        foreach ($pendingContextMessages as $contextMsg) {
            $messages[] = $contextMsg;
            error_log("[WhatsApp Webhook] ✓ Contexto agregado a mensajes después de tool responses");
        }
    }

    // 
    // =======================
    // 5. LLAMADAS A OPENAI (con soporte de tool calls encadenados)
    // =======================
    if ($skipFinalOpenAI && !empty($farewellMessage)) {
        $responseText = $farewellMessage;
    } else {
        // Mapa de herramientas → endpoint API para tool calls encadenados en rondas posteriores
        $toolApiMap = [
            'registrar_cliente_nuevo'      => ['url' => 'https://adm.conlineweb.com/api/registrar_cliente_nuevo.php',       'method' => 'post'],
            'consultar_datos_cliente'      => ['url' => 'https://adm.conlineweb.com/api/consultar_datos_cliente.php',       'method' => 'get'],
            'consultar_tickets_pendientes' => ['url' => 'https://adm.conlineweb.com/api/consultar_tickets_pendientes.php',  'method' => 'get'],
            'registrar_solicitud_prepago'  => ['url' => 'https://adm.conlineweb.com/api/registrar_solicitud_prepago.php',   'method' => 'post'],
            'verificar_estado_pago'        => ['url' => 'https://adm.conlineweb.com/api/verificar_estado_pago.php',         'method' => 'get'],
            'confirmar_pago_solicitud'     => ['url' => 'https://adm.conlineweb.com/api/confirmar_pago_solicitud.php',      'method' => 'post'],
            'crear_ticket_servicio'        => ['url' => 'https://adm.conlineweb.com/api/crear_ticket_whatsapp.php',         'method' => 'post'],
            'ticket_no_creado'             => ['url' => 'https://adm.conlineweb.com/api/marcar_no_creado.php',              'method' => 'post'],
            'consultar_catalogo'           => ['url' => 'https://adm.conlineweb.com/api/catalogo_servicios.php',            'method' => 'get'],
        ];

        $maxIteraciones = 4;
        $iteracion = 0;

        while ($iteracion < $maxIteraciones) {
            $iteracion++;
            $finalResponse = callOpenAI($messages, $tools, $OPENAI_API_KEY);

            error_log("[WhatsApp Webhook] Llamada OpenAI #" . (1 + $iteracion) . ": " . json_encode($finalResponse));

            if (isset($finalResponse['error'])) {
                error_log("[WhatsApp Webhook] OpenAI Error en llamada #" . (1 + $iteracion) . ": " . json_encode($finalResponse['error']));
                $responseText = 'Disculpa, estoy teniendo problemas técnicos. ¿Podrías intentar de nuevo en un momento? 😊';
                break;
            }

            $finalMessage = $finalResponse['choices'][0]['message'] ?? [];
            $responseText = $finalMessage['content'] ?? '';

            // Si no hay tool calls encadenados, tenemos la respuesta de texto → salir del loop
            if (empty($finalMessage['tool_calls'])) {
                if (empty(trim($responseText))) {
                    error_log("[WhatsApp Webhook] Respuesta vacía en llamada #" . (1 + $iteracion) . ": " . json_encode($finalResponse));
                    $responseText = 'Disculpa, hubo un problema al procesar tu mensaje. ¿Puedes intentar de nuevo? 😊';
                }
                break;
            }

            // Hay tool calls encadenados → procesarlos y continuar el loop
            $messages[] = $finalMessage;

            foreach ($finalMessage['tool_calls'] as $chainedToolCall) {
                $chainedFuncName = $chainedToolCall['function']['name'];
                $chainedFuncArgs = json_decode($chainedToolCall['function']['arguments'], true) ?: [];
                $chainedCallId   = $chainedToolCall['id'];

                error_log("[WhatsApp Webhook] Tool call encadenado #{$iteracion}: {$chainedFuncName}");

                // Bloqueo VIP: no procesar herramientas de pago para clientes VIP
                if (!empty($esClienteVIP) && in_array($chainedFuncName, ['registrar_solicitud_prepago', 'verificar_estado_pago', 'confirmar_pago_solicitud'], true)) {
                    $chainedOutput = json_encode(['success' => false, 'error' => 'Cliente VIP: flujos de pago deshabilitados. Usa crear_ticket_servicio.'], JSON_UNESCAPED_UNICODE);
                    $messages[] = ["role" => "tool", "tool_call_id" => $chainedCallId, "content" => $chainedOutput];
                    continue;
                }

                if (isset($toolApiMap[$chainedFuncName])) {
                    $apiInfo     = $toolApiMap[$chainedFuncName];
                    $apiEndpoint = $apiInfo['url'];

                    // Asegurar teléfono si aplica
                    if (!empty($from_whatsapp_e164) && array_key_exists('client_phone', $chainedFuncArgs) && empty($chainedFuncArgs['client_phone'])) {
                        $chainedFuncArgs['client_phone'] = $from_whatsapp_e164;
                    }

                    $ch = curl_init();
                    if ($apiInfo['method'] === 'get') {
                        $qs = http_build_query($chainedFuncArgs);
                        curl_setopt($ch, CURLOPT_URL, $apiEndpoint . ($qs ? '?' . $qs : ''));
                    } else {
                        curl_setopt($ch, CURLOPT_URL, $apiEndpoint);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($chainedFuncArgs));
                    }
                    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8]);
                    $chainedApiResponse = curl_exec($ch);
                    curl_close($ch);

                    $chainedOutput = $chainedApiResponse ?: json_encode(['success' => false, 'error' => 'No se pudo conectar con la API'], JSON_UNESCAPED_UNICODE);

                    // Persistir cliente_id en meta si se registró un cliente nuevo
                    if ($chainedFuncName === 'registrar_cliente_nuevo') {
                        $chainedData = json_decode($chainedOutput, true);
                        if (!empty($chainedData['success']) && !empty($chainedData['data']['cliente_id'])) {
                            $meta['cliente_id']   = $chainedData['data']['cliente_id'];
                            $meta['client_name']  = $chainedFuncArgs['client_name'] ?? '';
                            $meta['client_email'] = $chainedFuncArgs['client_email'] ?? '';
                            file_put_contents($sessionMetaFile, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
                            error_log("[WhatsApp Webhook] ✓ Cliente encadenado registrado con ID: " . $chainedData['data']['cliente_id']);
                        }
                    }
                } else {
                    // Herramienta sin endpoint mapeado (solicitar_evaluacion_especial, etc.) → respuesta genérica de éxito
                    $chainedOutput = json_encode(['success' => true, 'message' => 'Procesado'], JSON_UNESCAPED_UNICODE);
                }

                $messages[] = ["role" => "tool", "tool_call_id" => $chainedCallId, "content" => $chainedOutput];
            }
            // Continuar el loop para obtener la respuesta de texto final
        }

        $messages[] = ["role" => "assistant", "content" => $responseText];
    }

} else {
    // Respuesta normal sin herramientas
    $responseText = $assistantMessage['content'] ?? '';

    // Validar que la respuesta no esté vacía
    if (empty(trim($responseText))) {
        // Generar respuesta apropiada según el contexto
        if ($mensajesAsistente === 0) {
            // Primera respuesta
            $responseText = '¡Hola! 👋 Soy Alex de CONLINEWEB. ¿En qué puedo ayudarte hoy?';
        } elseif (preg_match('/\b(que puedes hacer|qué puedes hacer|ayuda|servicios|info)\b/i', $userMessage)) {
            // Usuario pregunta qué puede hacer
            $responseText = "Puedo ayudarte con:\n\n✓ Desarrollo y diseño web\n\n✓ Hosting y dominios\n\n✓ Corrección de errores en sitios web\n\n✓ Optimización y mejoras\n\n✓ Soporte técnico\n\n¿En cuál de estos servicios estás interesado?";
        } else {
            // Limpiar mensajes del sistema excesivos y reconstruir contexto
            $messagesClean = [];
            $systemPromptAdded = false;
            $reglaAntiRepeticionAdded = false;

            foreach ($messages as $msg) {
                // Mantener solo el primer system prompt
                if ($msg['role'] === 'system') {
                    if (strpos($msg['content'], 'IDENTIDAD Y OBJETIVO') !== false || strpos($msg['content'], 'Eres Alex') !== false) {
                        if (!$systemPromptAdded) {
                            $messagesClean[] = $msg;
                            $systemPromptAdded = true;
                        }
                        continue;
                    }
                    // Mantener solo una regla anti-repetición
                    if (strpos($msg['content'], 'REGLA CRÍTICA') !== false) {
                        if (!$reglaAntiRepeticionAdded) {
                            $messagesClean[] = $msg;
                            $reglaAntiRepeticionAdded = true;
                        }
                        continue;
                    }
                    // Saltar mensajes de sistema de "Datos ya obtenidos" antiguos
                    if (strpos($msg['content'], 'Datos ya obtenidos') !== false) {
                        continue;
                    }
                }
                $messagesClean[] = $msg;
            }

            // Limitar el historial a los últimos 10 intercambios para reducir tokens
            $userAssistantMessages = [];
            foreach ($messagesClean as $msg) {
                if ($msg['role'] === 'user' || $msg['role'] === 'assistant') {
                    $userAssistantMessages[] = $msg;
                }
            }

            // Si hay más de 20 mensajes (10 intercambios), recortar
            if (count($userAssistantMessages) > 20) {
                $systemMessages = [];
                foreach ($messagesClean as $msg) {
                    if ($msg['role'] === 'system') {
                        $systemMessages[] = $msg;
                    }
                }

                $recentMessages = array_slice($userAssistantMessages, -20);
                $messagesClean = array_merge($systemMessages, $recentMessages);
            }

            // Agregar un contexto muy directo para forzar respuesta
            $lastUserMsg = $userMessage;
            $messagesClean[] = [
                "role" => "system",
                "content" => "INSTRUCCIÓN DIRECTA: El usuario dijo: \"$lastUserMsg\". Debes responder con una pregunta específica para entender mejor su solicitud. Por ejemplo, si mencionan imágenes, pregunta cuáles imágenes y dónde están en el sitio. Si mencionan soporte, pregunta qué tipo de problema tienen. NUNCA respondas con mensajes genéricos."
            ];

            $retryResponse = callOpenAI($messagesClean, $tools, $OPENAI_API_KEY);
            $responseText = $retryResponse['choices'][0]['message']['content'] ?? '';

            // Si aún está vacío, generar respuesta contextual manualmente
            if (empty(trim($responseText))) {
                error_log("Both API calls returned empty. User message: $userMessage");

                // Generar respuesta basada en palabras clave
                if (preg_match('/\b(imagen|imágenes|foto|fotos|banner)\b/i', $userMessage)) {
                    $responseText = "Perfecto, entiendo que necesitas actualizar imágenes. ¿Podrías indicarme:\n\n1. ¿Cuáles imágenes necesitas cambiar?\n2. ¿En qué sección de tu sitio están?\n3. ¿Ya tienes las nuevas imágenes listas?";
                } elseif (preg_match('/\b(texto|contenido|información|datos)\b/i', $userMessage)) {
                    $responseText = "Entendido, necesitas actualizar contenido. ¿Podrías especificar qué texto o sección necesitas modificar?";
                } elseif (preg_match('/\b(error|problema|fallo|no funciona)\b/i', $userMessage)) {
                    $responseText = "Claro, veo que tienes un problema técnico. ¿Podrías describirme qué es lo que no está funcionando correctamente?";
                } elseif (preg_match('/\b(agregar|añadir|nuevo|nueva)\b/i', $userMessage)) {
                    $responseText = "Perfecto, ¿qué te gustaría agregar a tu sitio web?";
                } else {
                    $responseText = "Claro, entiendo que necesitas $userMessage. ¿Podrías darme más detalles para poder ayudarte mejor? Por ejemplo, ¿en qué parte de tu sitio o qué específicamente necesitas?";
                }
            }
        }
    }

    $messages[] = ["role" => "assistant", "content" => $responseText];
}

// =======================
// 6. GUARDAR Y RESPONDER
// =======================
$tmp = $sessionFile . '.tmp';
$fh = @fopen($tmp, 'wb');
if ($fh) {
    if (flock($fh, LOCK_EX)) {
        fwrite($fh, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fflush($fh);
        flock($fh, LOCK_UN);
    }
    fclose($fh);
    @rename($tmp, $sessionFile);
} else {
    file_put_contents($sessionFile, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

if ($loadedFromLegacySession && $legacyDigits !== '' && $legacyDigits !== $from_whatsapp_number && file_exists($legacySessionFile)) {
    @unlink($legacySessionFile);
}
if ($loadedFromLegacyMeta && $legacyDigits !== '' && $legacyDigits !== $from_whatsapp_number && file_exists($legacySessionMetaFile)) {
    @unlink($legacySessionMetaFile);
}

// Deliver the bot reply via Twilio REST API.
// This is reliable regardless of whether Twilio's 15-second webhook timeout
// has already expired (which happens on tool-call flows with 2+ OpenAI calls).
twilio_rest_send($from_whatsapp_e164, $responseText);

// Return empty TwiML to acknowledge the webhook (Twilio may or may not read this).
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
echo "<Response></Response>";
?>