<?php
// payment_success.php (COMPLETO)

// 1) Encabezados y zona horaria
header('Content-Type: text/html; charset=UTF-8');
date_default_timezone_set('America/Mexico_City');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// CORS (si lo necesitas, ajusta dominios/métodos según tu flujo)
header("Access-Control-Allow-Origin: https://cliente.conlineweb.com");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// 2) Requeridos del proyecto
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/conn_hostingpro.php';

$conn_cw = $conn; // ConlineWeb (no sobrescribir)

// Sistema del checkout (mismo param que procesar_pago / correos de alerta)
$sistema = 'conlineweb';
if (isset($_GET['sistema'])) {
    if ($_GET['sistema'] === 'hostingpro') {
        $sistema = 'hostingpro';
    } elseif ($_GET['sistema'] === 'planpro') {
        $sistema = 'planpro';
    }
}
$conn = ($sistema === 'hostingpro' || $sistema === 'planpro') ? $conn_hp : $conn_cw;

// Stripe SDK (usa el que tienes en tu repo)
require_once __DIR__ . '/stripe-php/init.php';

// Composer autoload (dotenv, etc.)
require_once __DIR__ . '/../vendor/autoload.php'; // sube una carpeta hasta /vendor
use Dotenv\Dotenv;

// Cargar variables de entorno (.env en la raíz del proyecto)
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// 3) Limpieza de buffers (opcional)
while (ob_get_level()) { ob_end_clean(); }
ob_start();

// 4) PHPMailer (una sola carga; evita fatal "SMTP already in use")
if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer', false)) {
    require_once __DIR__ . '/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';
}

// 5) Configuración de Stripe (misma llave que procesar_pago según sistema)
$stripeKey = $_ENV['STRIPE_SECRET_KEY'] ?? '';
if ($sistema === 'hostingpro') {
    $hostproKey = $_ENV['STRIPE_SECRET_KEY_HOSTPRO'] ?? ($_ENV['HOSTPRO_STRIPE_SECRET_KEY'] ?? '');
    if ($hostproKey !== '') {
        $stripeKey = $hostproKey;
    }
}
\Stripe\Stripe::setApiKey($stripeKey);

require_once __DIR__ . '/includes/pago_email_template.php';
require_once __DIR__ . '/includes/cw_nota_pago_pdf.php';
require_once __DIR__ . '/includes/cw_pago_renovacion.php';
require_once __DIR__ . '/includes/payment_success_view.php';

// 6) Helper para enviar correo (HTML listo + PDF opcional)
function sendEmail($to, $subject, $htmlBody, $attachmentPath = null, $attachmentName = null) {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $correoRemitente = "servicios@conlineweb.com";
    $nombreRemitente = "ConlineWeb";

    try {
        $mail->isSMTP();
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = "tls";
        $mail->Port = 587;
        $mail->Host = "smtp.gmail.com";
        $mail->Username = $correoRemitente;
        $mail->Password = "wcglkgcxfebsauqo";
        $mail->setFrom($correoRemitente, $nombreRemitente);
        if ($to) $mail->addAddress($to);

        $mail->CharSet = "UTF-8";
        $mail->Encoding = "base64";
        $mail->isHTML(true);
        $mail->Subject = $subject;

        if ($attachmentPath && is_file($attachmentPath)) {
            $mail->addAttachment($attachmentPath, $attachmentName ?: basename($attachmentPath));
        }

        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));
        return $mail->send();
    } catch (Exception $e) {
        error_log("Email Error: " . $e->getMessage());
        return false;
    }
}

function cw_enviar_confirmacion_pago_con_pdf(mysqli $conn, array $cliente, int $pagoId, string $metodoTxt = 'Tarjeta'): void
{
    if (empty($cliente['correo'])) {
        return;
    }
    $tempPdf = null;
    $pdf = cw_generar_nota_pago_pdf_por_id($conn, $pagoId, 1);
    if (!empty($pdf['ok']) && !empty($pdf['path'])) {
        $tempPdf = $pdf['path'];
    }

    $fechaHora = date('d/m/Y') . ' · ' . date('h:i a');
    $detalle = cw_email_p(
        '<strong style="color:#000147;">Método de pago:</strong> ' . htmlspecialchars($metodoTxt, ENT_QUOTES, 'UTF-8')
        . '<br><strong style="color:#000147;">Referencia:</strong> #' . (int) $pagoId,
        12
    );
    if ($tempPdf) {
        $detalle .= cw_email_alert(
            '<strong>Comprobante adjunto:</strong> Encontrarás tu comprobante de pago en PDF en este correo.',
            'success'
        );
    }
    $detalle .= cw_email_p('Si requieres factura fiscal, indícanos tus datos por WhatsApp al <strong>477 118 1285</strong>.', 0);

    $html = pago_email_confirmacion((string) ($cliente['nombre_contacto'] ?? 'Cliente'), $fechaHora, $detalle);
    sendEmail(
        $cliente['correo'],
        'Confirmación de pago recibido - ConlineWeb',
        $html,
        $tempPdf,
        $tempPdf ? ('comprobante_pago_' . $pagoId . '.pdf') : null
    );
    if ($tempPdf && is_file($tempPdf)) {
        @unlink($tempPdf);
    }
}

// 7) Lógica de procesamiento (pago individual y pago múltiple)
try {
    if (!isset($_GET['session_id'])) {
        throw new Exception("No se proporcionó session_id");
    }
    $session_id = $_GET['session_id'];

    // 7.1 Obtener la session de Stripe
    $session = \Stripe\Checkout\Session::retrieve($session_id);
    if ($session->payment_status !== 'paid') {
        throw new Exception("El pago no se completó correctamente");
    }

    /**
     * CASO A: PAGO DE SOLICITUD WHATSAPP (nuevo flujo)
     * Si existe solicitud_id en los parámetros GET, procesamos como solicitud de WhatsApp
     */
    if (isset($_GET['solicitud_id']) && !empty($_GET['solicitud_id'])) {
        $solicitud_id = (int)$_GET['solicitud_id'];
        
        error_log("[Payment Success WhatsApp] Procesando solicitud ID: " . $solicitud_id);
        
        // Verificar si la solicitud existe y no está pagada
        $stmt = $conn->prepare("SELECT id, pagado, stripe_payment_id, telefono FROM solicitud_whatsapp WHERE id = ?");
        $stmt->bind_param("i", $solicitud_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $solicitud = $result->fetch_assoc();
            $stmt->close();
            
            if ((int)$solicitud['pagado'] === 1) {
                payment_success_render([
                    'variant' => 'info',
                    'badge' => 'Pago registrado',
                    'title' => 'Tu pago ya fue registrado',
                    'subtitle' => 'Esta solicitud se procesó anteriormente. No es necesario volver a pagar.',
                    'footnote' => 'Puedes cerrar esta ventana con tranquilidad.',
                    'auto_close_seconds' => 5,
                    'confetti' => false,
                    'actions' => [
                        ['label' => 'Ir a ConlineWeb', 'url' => 'https://conlineweb.com', 'style' => 'ghost', 'icon' => 'fas fa-home'],
                    ],
                ]);
            }
            
            // Llamar a la API para confirmar el pago
            $apiUrl = "https://adm.conlineweb.com/api/confirmar_pago_solicitud.php";
            
            $postData = [
                'solicitud_id' => $solicitud_id,
                'stripe_payment_id' => $session_id
            ];

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

            error_log("[Payment Success WhatsApp] API Response Code: " . $httpCode);
            error_log("[Payment Success WhatsApp] API Response: " . $apiResponse);

            if ($httpCode == 200 || $httpCode == 201) {
                $responseData = json_decode($apiResponse, true);
                if (!empty($responseData['success']) && !empty($responseData['ticket_id'])) {
                    $ticket_id = $responseData['ticket_id'];

                    // Normalizar teléfono para archivo de sesión
                    $telefono_raw = $solicitud['telefono'] ?? '';
                    $digits = preg_replace('/\D+/', '', $telefono_raw);
                    if (preg_match('/^52\d{10}$/', $digits)) {
                        $digits = '521' . substr($digits, 2);
                    } elseif (strlen($digits) === 10) {
                        $digits = '521' . $digits;
                    }
                    $toE164 = '+' . $digits;

                    // Enviar notificación WhatsApp al cliente vía Twilio
                    $TWILIO_SID  = 'ACe545cc9bfdfb41f417c8e1cc34062678';
                    $TWILIO_TOK  = '6068c519f69979eefe00f63d61ad25a8';
                    $TWILIO_FROM = 'whatsapp:+15557419621';

                    $mensajeCliente  = "✅ *¡Tu pago ha sido confirmado y acreditado!*\n\n";
                    $mensajeCliente .= "📋 *Ticket:* #{$ticket_id}\n";
                    $mensajeCliente .= "⏱️ *Los días hábiles de trabajo comienzan a partir de hoy.*\n\n";
                    $mensajeCliente .= "Nuestro equipo ya comenzará a trabajar en tu solicitud. Te notificaremos cuando esté lista. 😊";

                    $chT = curl_init("https://api.twilio.com/2010-04-01/Accounts/{$TWILIO_SID}/Messages.json");
                    curl_setopt_array($chT, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST           => true,
                        CURLOPT_POSTFIELDS     => http_build_query([
                            'From' => $TWILIO_FROM,
                            'To'   => 'whatsapp:' . $toE164,
                            'Body' => $mensajeCliente,
                        ]),
                        CURLOPT_USERPWD        => $TWILIO_SID . ':' . $TWILIO_TOK,
                        CURLOPT_TIMEOUT        => 15,
                    ]);
                    curl_exec($chT);
                    $twilioCode = curl_getinfo($chT, CURLINFO_HTTP_CODE);
                    curl_close($chT);
                    error_log("[Payment Success WhatsApp] Twilio send - HTTP {$twilioCode}");

                    // Guardar mensaje en historial de conversación
                    $sessionsDir = __DIR__ . '/whatsapp/sessions';
                    $sessionFile = $sessionsDir . '/whatsapp_' . $digits . '.json';
                    $metaFile    = $sessionsDir . '/whatsapp_' . $digits . '_meta.json';

                    if (file_exists($sessionFile)) {
                        $msgs = json_decode((string)@file_get_contents($sessionFile), true) ?: [];
                        $msgs[] = [
                            'role'      => 'assistant',
                            'content'   => $mensajeCliente,
                            'timestamp' => time(),
                            'origen'    => 'stripe_confirmacion',
                        ];
                        @file_put_contents($sessionFile, json_encode($msgs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
                    }

                    // Limpiar flags de pago pendiente en meta
                    if (file_exists($metaFile)) {
                        $meta = json_decode((string)@file_get_contents($metaFile), true) ?: [];
                        unset($meta['pending_solicitud_id'], $meta['pending_payment_method']);
                        $meta['pago_acreditado_at'] = time();
                        $meta['ticket_id'] = $ticket_id;
                        @file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
                    }

                    payment_success_render([
                        'variant' => 'success',
                        'badge' => 'Pago confirmado',
                        'title' => '¡Pago exitoso!',
                        'subtitle' => 'Tu pago fue procesado correctamente. Nuestro equipo ya comenzará a trabajar en tu solicitud.',
                        'wide' => false,
                        'highlight' => [
                            'label' => 'Número de ticket',
                            'value' => '#' . $ticket_id,
                            'meta' => 'Guarda este número para dar seguimiento',
                        ],
                        'details' => [
                            ['label' => 'Estado', 'value' => 'Acreditado'],
                            ['label' => 'Fecha', 'value' => date('d/m/Y H:i')],
                        ],
                        'note' => 'Recibirás una confirmación por <strong>WhatsApp</strong> con los detalles de tu solicitud.',
                        'actions' => [
                            ['label' => 'Volver al sitio', 'url' => 'https://conlineweb.com', 'style' => 'primary', 'icon' => 'fas fa-arrow-right'],
                        ],
                        'footnote' => 'Los días hábiles de trabajo comienzan a partir de hoy.',
                    ]);
                }
            }
            
            // Si llegamos aquí, hubo un error
            throw new Exception("Error al procesar el pago con la API");
        }
        
        $stmt->close();
    }

    /**
     * CASO B: PAGO INDIVIDUAL DE HOSTING/DOMINIO (flujo existente)
     * Busca por session_id en la BD del sistema; si no aparece, prueba la otra (como el webhook).
     */
    $pago = null;
    $dbCandidates = [$conn];
    if (isset($conn_cw) && $conn_cw instanceof mysqli && $conn_cw !== $conn) {
        $dbCandidates[] = $conn_cw;
    }
    if (isset($conn_hp) && $conn_hp instanceof mysqli && $conn_hp !== $conn) {
        $dbCandidates[] = $conn_hp;
    }

    foreach ($dbCandidates as $dbTry) {
        $stmt = $dbTry->prepare("SELECT id, id_clie, id_servicio, tipo_servicio, estatus FROM pagos WHERE session_id = ?");
        if (!$stmt) {
            continue;
        }
        $stmt->bind_param("s", $session_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $pago = $result->fetch_assoc();
            $conn = $dbTry;
            $stmt->close();
            break;
        }
        $stmt->close();
    }

    if ($pago) {
        if ((int)$pago['estatus'] === 1) {
            payment_success_render([
                'variant' => 'info',
                'badge' => 'Ya pagado',
                'title' => 'Transacción registrada',
                'subtitle' => 'Este servicio ya se pagó anteriormente. No se realizó ningún cargo adicional.',
                'auto_close_seconds' => 5,
                'confetti' => false,
                'actions' => [
                    ['label' => 'Mis pagos', 'url' => 'https://cliente.conlineweb.com/pagos.php', 'style' => 'primary', 'icon' => 'fas fa-receipt'],
                ],
            ]);
        }

        // Transacción: marcar pagado + extender servicio si aplica
        $conn->begin_transaction();

        $update = $conn->prepare("UPDATE pagos SET forma_pago = 1, estatus = 1, fecha_pago = NOW() WHERE id = ?");
        $update->bind_param("i", $pago['id']);
        $update->execute();

        if ((int)$pago['tipo_servicio'] === 1 || (int)$pago['tipo_servicio'] === 2) {
            $ren = cw_renovar_servicio($conn, (int)$pago['tipo_servicio'], (int)$pago['id_servicio']);
            if (empty($ren['ok'])) {
                error_log('payment_success renovación falló pago=' . $pago['id'] . ' err=' . ($ren['error'] ?? ''));
            }
        }

        // Datos del cliente para email
        $clientStmt = $conn->prepare("SELECT correo, nombre_contacto FROM clientes WHERE id = ?");
        $clientStmt->bind_param("i", $pago['id_clie']);
        $clientStmt->execute();
        $cliente = $clientStmt->get_result()->fetch_assoc();
        $clientStmt->close();

        $conn->commit();

        // Correo (si hay) + PDF adjunto
        if (!empty($cliente['correo'])) {
            cw_enviar_confirmacion_pago_con_pdf($conn, $cliente, (int) $pago['id'], 'Tarjeta');
        }

        payment_success_render([
            'variant' => 'success',
            'badge' => 'Pago confirmado',
            'title' => '¡Gracias por tu pago' . (!empty($cliente['nombre_contacto']) ? ', ' . $cliente['nombre_contacto'] : '') . '!',
            'subtitle' => 'Tu transacción fue registrada y tu servicio quedó activo. Te enviamos un correo de confirmación.',
            'metrics' => [
                ['label' => 'Referencia', 'value' => '#' . $pago['id'], 'icon' => 'fas fa-hashtag'],
                ['label' => 'Método', 'value' => 'Tarjeta', 'icon' => 'fas fa-credit-card'],
                ['label' => 'Fecha', 'value' => date('d/m/Y'), 'icon' => 'fas fa-calendar-check'],
            ],
            'note' => 'Puedes descargar tu comprobante desde el <strong>portal de cliente</strong> en la sección Mis pagos.',
            'actions' => [
                ['label' => 'Ver mis pagos', 'url' => 'https://cliente.conlineweb.com/pagos.php', 'style' => 'primary', 'icon' => 'fas fa-receipt'],
                ['label' => 'Portal cliente', 'url' => 'https://cliente.conlineweb.com/', 'style' => 'ghost', 'icon' => 'fas fa-user'],
            ],
            'auto_close_seconds' => 8,
        ]);
    }

    /**
     * CASO B: PAGO MÚLTIPLE
     * No hay fila con este session_id → tomamos los IDs de session.metadata.ids
     */
    $idsCsv = $session->metadata->ids ?? '';
    $idList = array_filter(array_map('intval', explode(',', $idsCsv)));
    if (!$idList) {
        throw new Exception("No se encontraron IDs en metadata para pago múltiple.");
    }

    // Cargar pagos a actualizar y validar
    $placeholders = implode(',', array_fill(0, count($idList), '?'));
    $typesBind = str_repeat('i', count($idList));

    $sqlPagos = "SELECT id, id_clie, id_servicio, tipo_servicio, estatus
                 FROM pagos
                 WHERE id IN ($placeholders) AND Registro = 0";
    $stmt = $conn->prepare($sqlPagos);
    $stmt->bind_param($typesBind, ...$idList);
    $stmt->execute();
    $resPagos = $stmt->get_result();

    $rows = [];
    while ($row = $resPagos->fetch_assoc()) $rows[] = $row;
    $stmt->close();

    if (count($rows) !== count($idList)) {
        throw new Exception("Algunos pagos no existen o no están activos.");
    }

    // Validar que todos sean del mismo cliente
    $clientesUnicos = array_unique(array_map(fn($r) => (int)$r['id_clie'], $rows));
    if (count($clientesUnicos) > 1) {
        throw new Exception("Los pagos seleccionados pertenecen a distintos clientes.");
    }
    $idCliente = $clientesUnicos[0];

    // Evitar reproceso
    foreach ($rows as $r) {
        if ((int)$r['estatus'] === 1) {
            throw new Exception("Hay pagos ya aprobados en la selección.");
        }
    }

    // Transacción: marcar pagos + extender servicios
    $conn->begin_transaction();

    // Marcar todos como pagados
    $sqlUpdatePagos = "UPDATE pagos SET forma_pago = 1, estatus = 1, fecha_pago = NOW()
                       WHERE id IN ($placeholders) AND Registro = 0";
    $stmt = $conn->prepare($sqlUpdatePagos);
    $stmt->bind_param($typesBind, ...$idList);
    $stmt->execute();
    $stmt->close();

    // Extender servicios por cada pago (hosting/dom)
    foreach ($rows as $r) {
        $tipo = (int)$r['tipo_servicio'];
        $idServ = (int)$r['id_servicio'];
        if ($tipo === 1 || $tipo === 2) {
            $ren = cw_renovar_servicio($conn, $tipo, $idServ);
            if (empty($ren['ok'])) {
                error_log('payment_success múltiple renovación falló pago=' . $r['id'] . ' err=' . ($ren['error'] ?? ''));
            }
        }
    }

    // Datos del cliente para email
    $clientStmt = $conn->prepare("SELECT correo, nombre_contacto FROM clientes WHERE id = ?");
    $clientStmt->bind_param("i", $idCliente);
    $clientStmt->execute();
    $cliente = $clientStmt->get_result()->fetch_assoc();
    $clientStmt->close();

    $conn->commit();

    // Correo de confirmación (si hay correo). En pago múltiple adjuntamos el PDF del primer pago
    // y mencionamos la cantidad de servicios.
    if (!empty($cliente['correo'])) {
        $primerId = (int) $idList[0];
        $fechaHora = date('d/m/Y') . ' · ' . date('h:i a');
        $tempPdf = null;
        $pdf = cw_generar_nota_pago_pdf_por_id($conn, $primerId, 1);
        if (!empty($pdf['ok']) && !empty($pdf['path'])) {
            $tempPdf = $pdf['path'];
        }
        $detalle = cw_email_p(
            'Se acreditaron <strong>' . count($idList) . ' servicio(s)</strong>.'
            . '<br><strong style="color:#000147;">Método:</strong> Tarjeta'
            . ($tempPdf ? '<br><span style="color:#64748b;">Adjunto: comprobante de referencia #' . $primerId . '.</span>' : ''),
            12
        );
        $html = pago_email_confirmacion((string) ($cliente['nombre_contacto'] ?? 'Cliente'), $fechaHora, $detalle);
        sendEmail(
            $cliente['correo'],
            'Confirmación de pago recibido - ConlineWeb',
            $html,
            $tempPdf,
            $tempPdf ? ('comprobante_pago_' . $primerId . '.pdf') : null
        );
        if ($tempPdf && is_file($tempPdf)) {
            @unlink($tempPdf);
        }
    }

    payment_success_render([
        'variant' => 'success',
        'badge' => 'Pago múltiple',
        'title' => '¡Gracias por tu pago!',
        'subtitle' => 'Se registraron correctamente todos los servicios incluidos en esta transacción.',
        'highlight' => [
            'label' => 'Servicios pagados',
            'value' => (string) count($idList),
            'meta' => 'Confirmación enviada a tu correo',
        ],
        'metrics' => [
            ['label' => 'Fecha', 'value' => date('d/m/Y'), 'icon' => 'fas fa-calendar-check'],
            ['label' => 'Hora', 'value' => date('h:i a'), 'icon' => 'fas fa-clock'],
            ['label' => 'Método', 'value' => 'Tarjeta', 'icon' => 'fas fa-credit-card'],
        ],
        'actions' => [
            ['label' => 'Ver mis pagos', 'url' => 'https://cliente.conlineweb.com/pagos.php', 'style' => 'primary', 'icon' => 'fas fa-receipt'],
        ],
        'auto_close_seconds' => 8,
    ]);

} catch (\Stripe\Exception\ApiErrorException $e) {
    if (isset($conn) && $conn instanceof mysqli && $conn->connect_errno === 0) { $conn->rollback(); }
    error_log("Stripe Error: " . $e->getMessage());
    http_response_code(400);
    payment_success_render([
        'variant' => 'error',
        'page_title' => 'Error en el pago',
        'badge' => 'Error Stripe',
        'title' => 'No se pudo confirmar el pago',
        'subtitle' => 'Ocurrió un problema al validar la transacción con Stripe. Si el cargo apareció en tu tarjeta, contáctanos de inmediato.',
        'details' => [['label' => 'Detalle', 'value' => $e->getMessage()]],
        'confetti' => false,
        'actions' => [
            ['label' => 'Reintentar pago', 'url' => 'https://cliente.conlineweb.com/pagos.php', 'style' => 'primary', 'icon' => 'fas fa-redo'],
        ],
    ]);
} catch (Exception $e) {
    if (isset($conn) && $conn instanceof mysqli && $conn->connect_errno === 0) { $conn->rollback(); }
    error_log("General Error: " . $e->getMessage());
    http_response_code(500);
    payment_success_render([
        'variant' => 'error',
        'page_title' => 'Error en el pago',
        'badge' => 'Error',
        'title' => 'No se pudo procesar el pago',
        'subtitle' => 'La transacción no pudo completarse. Si necesitas ayuda, nuestro equipo de soporte puede revisarlo contigo.',
        'details' => [['label' => 'Detalle', 'value' => $e->getMessage()]],
        'confetti' => false,
        'actions' => [
            ['label' => 'Volver a pagos', 'url' => 'https://cliente.conlineweb.com/pagos.php', 'style' => 'primary', 'icon' => 'fas fa-arrow-left'],
        ],
    ]);
} finally {
    if (isset($conn) && $conn instanceof mysqli) { $conn->close(); }
}
