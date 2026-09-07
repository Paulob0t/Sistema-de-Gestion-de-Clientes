<?php
// generar_pago.php
error_reporting(E_ALL);
ini_set("display_errors", 1);
include "conn.php";
include "conn_hostingpro.php";

// Determinar si se debe procesar un sistema específico o ambos
$sistema_especifico = isset($_REQUEST['sistema']) ? $_REQUEST['sistema'] : 'todos';

// Verificar si es una solicitud AJAX
$es_ajax = isset($_POST['ajax']) && $_POST['ajax'] == 1;

// Si es AJAX, no mostrar el menú
if (!$es_ajax) {
    include "menu.php";
}

// Iniciar sesión para session_id()
$session_id = '';

// Obtener fecha actual
$fecha_actual = new DateTime();

/**
 * Función para calcular la próxima fecha de pago según la frecuencia
 * @param string $fecha_base Fecha base desde la cual calcular
 * @param int $frecuencia 0=manual/único, 1=semanal, 2=mensual, 3=anual, 4=personalizado
 * @param DateTime $fecha_actual Fecha actual para comparar
 * @param int $intervalo_dias Solo para frecuencia 4
 * @return array ['fecha_proxima' => DateTime, 'dias_faltantes' => int]
 */
function calcularProximaFechaPago($fecha_base, $frecuencia, $fecha_actual, $intervalo_dias = 0) {
    $fecha_base_obj = new DateTime($fecha_base);
    
    // Si la frecuencia es manual/único (0), solo verificar la fecha original
    if ($frecuencia == 0) {
        $interval = $fecha_actual->diff($fecha_base_obj);
        $dias_faltantes = $fecha_base_obj > $fecha_actual ? $interval->days : -$interval->days;
        return [
            'fecha_proxima' => $fecha_base_obj,
            'dias_faltantes' => $dias_faltantes,
            'debe_generar' => $dias_faltantes <= 30 && $dias_faltantes >= -7
        ];
    }
    
    // Para pagos recurrentes, calcular la próxima fecha
    $fecha_proxima = clone $fecha_base_obj;
    
    // Avanzar hasta llegar a una fecha futura
    while ($fecha_proxima < $fecha_actual) {
        switch ($frecuencia) {
            case 1: // Semanal
                $fecha_proxima->modify('+7 days');
                break;
            case 2: // Mensual
                $fecha_proxima->modify('+1 month');
                break;
            case 3: // Anual
                $fecha_proxima->modify('+1 year');
                break;
            case 4: // Personalizado
                $dias = max(1, (int) $intervalo_dias);
                $fecha_proxima->modify('+' . $dias . ' days');
                break;
            default:
                // Frecuencia desconocida: no avanzar en bucle infinito
                break 2;
        }
    }
    
    // Calcular días faltantes
    $interval = $fecha_actual->diff($fecha_proxima);
    $dias_faltantes = $fecha_proxima > $fecha_actual ? $interval->days : -$interval->days;
    
    // Determinar si debe generarse el pago (30 días antes o hasta 7 días después de vencido)
    $debe_generar = $dias_faltantes <= 30 && $dias_faltantes >= -7;
    
    return [
        'fecha_proxima' => $fecha_proxima,
        'dias_faltantes' => $dias_faltantes,
        'debe_generar' => $debe_generar
    ];
}

// Array para almacenar resultados
$resultados = [
    'success' => true,
    'dominios' => ['generados' => 0, 'errores' => 0],
    'hosting' => ['generados' => 0, 'errores' => 0],
    'total_generados' => 0,
    'mensajes' => []
];

// Sistemas a procesar
$sistemas_a_procesar = [];
if ($sistema_especifico === 'todos') {
    $sistemas_a_procesar = [
        ['nombre' => 'conlineweb', 'conn' => $conn],
        ['nombre' => 'hostingpro', 'conn' => $conn_hp]
    ];
} elseif ($sistema_especifico === 'hostingpro') {
    $sistemas_a_procesar = [['nombre' => 'hostingpro', 'conn' => $conn_hp]];
} else {
    $sistemas_a_procesar = [['nombre' => 'conlineweb', 'conn' => $conn]];
}

// Procesar cada sistema
foreach ($sistemas_a_procesar as $sistema_info) {
    $sistema = $sistema_info['nombre'];
    $conn_actual = $sistema_info['conn'];
    
    $resultados['mensajes'][] = "🚀 ========== Procesando sistema: " . strtoupper($sistema) . " ==========";

//////////////////////////////////////////
// PAGOS PARA DOMINIOS
//////////////////////////////////////////

// Mejorado: Excluir clientes eliminados y verificar existencia de pagos
// Incluir frecuencia_pago para generar pagos automáticos
$sql_dominios = "SELECT d.*, c.eliminado as cliente_eliminado,
                 COALESCE(d.frecuencia_pago, 3) as frecuencia_pago
                 FROM dominios d
                 LEFT JOIN clientes c ON d.cliente_id = c.id
                 WHERE d.eliminado = 0 
                 AND d.registrado = 1 
                 AND (c.eliminado = 0 OR c.eliminado IS NULL)";
$result_dominios = $conn_actual->query($sql_dominios);

if ($result_dominios && $result_dominios->num_rows > 0) {
    while ($row = $result_dominios->fetch_assoc()) {
        $id_clie = $row['cliente_id'];
        $id_dominio = $row['id_dominio'];
        $costo_dominio = $row['costo_dominio'];
        $id_forma_pago = $row['id_forma_pago'];
        $fecha_pago_dominio = $row['fecha_pago'];
        $frecuencia = intval($row['frecuencia_pago']); // 0=manual, 1=semanal, 2=mensual, 3=anual
        
        // Verificar si la fecha es válida
        if ($fecha_pago_dominio == '0000-00-00' || empty($fecha_pago_dominio)) {
            $resultados['mensajes'][] = "⚠️ Dominio ID $id_dominio no tiene fecha de pago configurada";
            continue;
        }
        
        // Calcular próxima fecha de pago según frecuencia
        $calculo = calcularProximaFechaPago($fecha_pago_dominio, $frecuencia, $fecha_actual);
        $fecha_proxima = $calculo['fecha_proxima'];
        $dias_faltantes = $calculo['dias_faltantes'];
        $debe_generar = $calculo['debe_generar'];
        
        $currency = ($id_forma_pago == 2) ? 'USD' : 'MXN';
        
        // Nombres de frecuencias para mensajes
        $nombres_frecuencia = ['Manual', 'Semanal', 'Mensual', 'Anual'];
        $nombre_freq = $nombres_frecuencia[$frecuencia] ?? 'Desconocida';

        // Verificar si debe generarse el pago (30 días antes o hasta 7 días después)
        if ($debe_generar) {
            $concepto = "Renovación de dominio: " . ($row['url_dominio'] ?? "ID $id_dominio");

            // Verificar si ya hay un pago (pendiente, aprobado o eliminado) para este dominio en este periodo
            // Usar la fecha próxima calculada en lugar de la fecha base
            $fecha_limite_str = $fecha_proxima->format('Y-m-d');
            $sql_check = "SELECT id, estatus, Registro
                          FROM pagos
                          WHERE id_servicio = ?
                          AND tipo_servicio = 2
                          AND Registro = 0
                          AND fecha_limite_pago = ?";
            $stmt_check = $conn_actual->prepare($sql_check);
            $check_result = false;

            if ($stmt_check) {
                $stmt_check->bind_param("is", $id_dominio, $fecha_limite_str);
                $stmt_check->execute();
                $check_result = $stmt_check->get_result();
            } else {
                $resultados['mensajes'][] = "❌ Error al preparar verificación de dominio ID $id_dominio: " . $conn_actual->error;
                $resultados['dominios']['errores']++;
                continue;
            }

            $generar_pago = true;

            if ($check_result && $check_result->num_rows > 0) {
                $pago_existente = $check_result->fetch_assoc();
                $generar_pago = false;
                
                if ($pago_existente['estatus'] == 1) {
                    $resultados['mensajes'][] = "ℹ️ Ya existe un pago aprobado para dominio ID $id_dominio (Fecha: $fecha_limite_str, Frecuencia: $nombre_freq)";
                } else {
                    $resultados['mensajes'][] = "ℹ️ Ya existe un pago pendiente para dominio ID $id_dominio (Frecuencia: $nombre_freq)";
                }
            }

            if ($generar_pago) {
                // Insertar pago
                $id_pago = uniqid("pago_");
                $tipo_servicio = 2; // 2 para dominios
                
                // Determinar si es pago recurrente
                $pago_recurrente = ($frecuencia > 0) ? 1 : 0;
                
                // INSERT IGNORE previene duplicados usando el índice UNIQUE
                $sql_insert = "INSERT IGNORE INTO pagos (
                    id_clie, fecha, hora, fecha_pago, hora_pago, monto, currency,
                    concepto, forma_pago, estatus, id_pago, session_id, id_cuenta, 
                    tipo_servicio, id_servicio, fecha_limite_pago, Registro, manual, 
                    frecuencia_pago, pago_recurrente, fecha_inicio_recurrencia, 
                    ultimo_pago_generado, sistema
                ) VALUES (
                    ?, CURDATE(), CURTIME(), '0000-00-00', '00:00:00',
                    ?, ?, ?, 0, 0,
                    ?, ?, 1, ?,
                    ?, ?, 0, 0,
                    ?, ?, ?,
                    CURDATE(), ?
                )";

                $stmt_insert = $conn_actual->prepare($sql_insert);

                if ($stmt_insert) {
                    $stmt_insert->bind_param(
                        "idsssssiissss",
                        $id_clie,
                        $costo_dominio,
                        $currency,
                        $concepto,
                        $id_pago,
                        $session_id,
                        $tipo_servicio,
                        $id_dominio,
                        $fecha_limite_str,
                        $frecuencia,
                        $pago_recurrente,
                        $fecha_pago_dominio,
                        $sistema
                    );

                    if ($stmt_insert->execute()) {
                        if ($stmt_insert->affected_rows > 0) {
                            $resultados['dominios']['generados']++;
                            $resultados['total_generados']++;
                            $resultados['mensajes'][] = "✅ Pago generado para dominio: " . ($row['url_dominio'] ?? "ID $id_dominio") . " (Frecuencia: $nombre_freq, Días: $dias_faltantes, Fecha límite: $fecha_limite_str)";
                        } else {
                            $resultados['mensajes'][] = "ℹ️ Pago de dominio ID $id_dominio no insertado (IGNORADO por índice único o ya existente)";
                        }
                    } else {
                        $resultados['dominios']['errores']++;
                        $resultados['mensajes'][] = "❌ Error al registrar pago de dominio ID $id_dominio: " . $stmt_insert->error;
                    }

                    $stmt_insert->close();
                } else {
                    $resultados['dominios']['errores']++;
                    $resultados['mensajes'][] = "❌ Error al preparar inserción de dominio ID $id_dominio: " . $conn_actual->error;
                }
            }

            $stmt_check->close();
        } else {
            // DEBUG: Dominio fuera del rango
            $fecha_prox_str = $fecha_proxima->format('Y-m-d');
            $resultados['mensajes'][] = "⏭️ Dominio ID $id_dominio omitido - Frecuencia: $nombre_freq - Próxima fecha: $fecha_prox_str - Días faltantes: $dias_faltantes (fuera de rango)";
        }
    }
}

//////////////////////////////////////////
// PAGOS PARA HOSTING
//////////////////////////////////////////

// Mejorado: Excluir clientes eliminados y verificar existencia de pagos
// Para HostPro: usar precio_renovacion de planes_admin
if ($sistema === 'hostingpro') {
    $sql_hosting = "SELECT h.id_orden, h.cliente_id, h.nom_host, h.producto, h.producto_extra_id, 
                           h.costo_producto, h.id_forma_pago, h.fecha_pago, h.eliminado,
                           COALESCE(h.frecuencia_pago, 2) as frecuencia_pago,
                           c.eliminado as cliente_eliminado,
                           p.precio_renovacion, p.precio_renovacion_usd
                    FROM hosting h
                    LEFT JOIN clientes c ON h.cliente_id = c.id
                    LEFT JOIN planes_admin p ON h.producto = p.id
                    WHERE h.eliminado = 0 
                    AND (c.eliminado = 0 OR c.eliminado IS NULL)";
} else {
    // ConlineWeb usa costo_producto directamente (NO tiene producto_extra_id)
    $sql_hosting = "SELECT h.id_orden, h.cliente_id, h.nom_host, h.producto,
                           h.costo_producto, h.id_forma_pago, h.fecha_pago, h.eliminado,
                           COALESCE(h.frecuencia_pago, 2) as frecuencia_pago,
                           c.eliminado as cliente_eliminado
                    FROM hosting h
                    LEFT JOIN clientes c ON h.cliente_id = c.id
                    WHERE h.eliminado = 0 
                    AND (c.eliminado = 0 OR c.eliminado IS NULL)";
}
$result_hosting = $conn_actual->query($sql_hosting);

// DEBUG: Agregar información del sistema y cantidad de hostings
$num_hostings = $result_hosting ? $result_hosting->num_rows : 0;
$resultados['mensajes'][] = "ℹ️ Sistema: $sistema - Hostings encontrados: $num_hostings";

if ($result_hosting && $result_hosting->num_rows > 0) {
    while ($row = $result_hosting->fetch_assoc()) {
        $id_clie = $row['cliente_id'];
        $id_orden = $row['id_orden'];
        $id_forma_pago = $row['id_forma_pago'];
        $fecha_pago_hosting = $row['fecha_pago'];
        $frecuencia = intval($row['frecuencia_pago']); // 0=manual, 1=semanal, 2=mensual, 3=anual
        
        // Para HostPro: usar precio_renovacion según la moneda
        // Para ConlineWeb: usar costo_producto
        if ($sistema === 'hostingpro') {
            $costo_producto = ($id_forma_pago == 2) ? $row['precio_renovacion_usd'] : $row['precio_renovacion'];
            
            // Validar que el precio de renovación exista y sea mayor a 0
            if (empty($costo_producto) || $costo_producto <= 0) {
                $resultados['mensajes'][] = "⚠️ Hosting ID $id_orden no tiene precio_renovacion configurado en planes_admin (Plan ID: {$row['producto']})";
                continue;
            }
        } else {
            $costo_producto = $row['costo_producto'];
        }
        
        // Verificar si la fecha es válida
        if ($fecha_pago_hosting == '0000-00-00' || empty($fecha_pago_hosting)) {
            $resultados['mensajes'][] = "⚠️ Hosting ID $id_orden no tiene fecha de pago configurada";
            continue;
        }
        
        // Calcular próxima fecha de pago según frecuencia
        $calculo = calcularProximaFechaPago($fecha_pago_hosting, $frecuencia, $fecha_actual);
        $fecha_proxima = $calculo['fecha_proxima'];
        $dias_faltantes = $calculo['dias_faltantes'];
        $debe_generar = $calculo['debe_generar'];
        
        $currency = ($id_forma_pago == 2) ? 'USD' : 'MXN';
        
        // Nombres de frecuencias para mensajes
        $nombres_frecuencia = ['Manual', 'Semanal', 'Mensual', 'Anual'];
        $nombre_freq = $nombres_frecuencia[$frecuencia] ?? 'Desconocida';

        // Verificar si debe generarse el pago
        if ($debe_generar) {
            $concepto = "Renovación de hosting: " . ($row['nom_host'] ?? "ID $id_orden");
            
            // DEBUG: Agregar información del hosting procesado
            $fecha_prox_str = $fecha_proxima->format('Y-m-d');
            $resultados['mensajes'][] = "🔍 Procesando Hosting ID $id_orden - Frecuencia: $nombre_freq - Días faltantes: $dias_faltantes - Monto: $costo_producto $currency - Fecha límite: $fecha_prox_str";

            // Verificar si ya hay un pago para este hosting en este periodo
            $sql_check = "SELECT id, estatus, Registro
                          FROM pagos
                          WHERE id_servicio = ?
                          AND tipo_servicio = 1
                          AND Registro = 0
                          AND fecha_limite_pago = ?";
            $stmt_check = $conn_actual->prepare($sql_check);
            $check_result = false;

            if ($stmt_check) {
                $stmt_check->bind_param("is", $id_orden, $fecha_prox_str);
                $stmt_check->execute();
                $check_result = $stmt_check->get_result();
            } else {
                $resultados['mensajes'][] = "❌ Error al preparar verificación de hosting ID $id_orden: " . $conn_actual->error;
                $resultados['hosting']['errores']++;
                continue;
            }

            $generar_pago = true;

            if ($check_result && $check_result->num_rows > 0) {
                $pago_existente = $check_result->fetch_assoc();
                $generar_pago = false;
                
                if ($pago_existente['estatus'] == 1) {
                    $resultados['mensajes'][] = "ℹ️ Ya existe un pago aprobado para hosting ID $id_orden (Fecha: $fecha_prox_str, Frecuencia: $nombre_freq)";
                } else {
                    $resultados['mensajes'][] = "ℹ️ Ya existe un pago pendiente para hosting ID $id_orden (Frecuencia: $nombre_freq)";
                }
            }

            if ($generar_pago) {
                // Insertar pago
                $id_pago = uniqid("pago_");
                $tipo_servicio = 1; // 1 para hosting
                
                // Determinar si es pago recurrente
                $pago_recurrente = ($frecuencia > 0) ? 1 : 0;
                
                // INSERT IGNORE previene duplicados usando el índice UNIQUE
                $sql_insert = "INSERT IGNORE INTO pagos (
                    id_clie, fecha, hora, fecha_pago, hora_pago, monto, currency,
                    concepto, forma_pago, estatus, id_pago, session_id, id_cuenta, 
                    tipo_servicio, id_servicio, fecha_limite_pago, Registro, manual,
                    frecuencia_pago, pago_recurrente, fecha_inicio_recurrencia,
                    ultimo_pago_generado, sistema
                ) VALUES (
                    ?, CURDATE(), CURTIME(), '0000-00-00', '00:00:00',
                    ?, ?, ?, 0, 0,
                    ?, ?, 1, ?,
                    ?, ?, 0, 0,
                    ?, ?, ?,
                    CURDATE(), ?
                )";

                $stmt_insert = $conn_actual->prepare($sql_insert);

                if ($stmt_insert) {
                    $stmt_insert->bind_param(
                        "idsssssiissss",
                        $id_clie,
                        $costo_producto,
                        $currency,
                        $concepto,
                        $id_pago,
                        $session_id,
                        $tipo_servicio,
                        $id_orden,
                        $fecha_prox_str,
                        $frecuencia,
                        $pago_recurrente,
                        $fecha_pago_hosting,
                        $sistema
                    );

                    if ($stmt_insert->execute()) {
                        if ($stmt_insert->affected_rows > 0) {
                            $resultados['hosting']['generados']++;
                            $resultados['total_generados']++;
                            $resultados['mensajes'][] = "✅ Pago generado para hosting: " . ($row['nom_host'] ?? "ID $id_orden") . " (Frecuencia: $nombre_freq, Días: $dias_faltantes, Fecha límite: $fecha_prox_str)";
                        } else {
                            $resultados['mensajes'][] = "ℹ️ Pago de hosting ID $id_orden no insertado (IGNORADO por índice único o ya existente)";
                        }
                    } else {
                        $resultados['hosting']['errores']++;
                        $resultados['mensajes'][] = "❌ Error al registrar pago de hosting ID $id_orden: " . $stmt_insert->error;
                    }

                    $stmt_insert->close();
                } else {
                    $resultados['hosting']['errores']++;
                    $resultados['mensajes'][] = "❌ Error al preparar inserción de hosting ID $id_orden: " . $conn_actual->error;
                }
            }

            $stmt_check->close();
        } else {
            // DEBUG: Hosting fuera del rango de 30 días
            $fecha_prox_str = $fecha_proxima->format('Y-m-d');
            $resultados['mensajes'][] = "⏭️ Hosting ID $id_orden omitido - Frecuencia: $nombre_freq - Próxima fecha: $fecha_prox_str - Días faltantes: $dias_faltantes (fuera de rango)";
        }
    }
}

//////////////////////////////////////////
// PAGOS MANUALES RECURRENTES (detalle_cliente)
// Series indefinidas: crea el siguiente al entrar en ventana de vencimiento
//////////////////////////////////////////
require_once __DIR__ . '/includes/cw_pago_recurrencia.php';
$manualRec = cw_pago_generar_siguientes_recurrentes_manuales($conn_actual, $sistema, $fecha_actual);
if (!isset($resultados['manual_recurrente'])) {
    $resultados['manual_recurrente'] = ['generados' => 0];
}
$resultados['manual_recurrente']['generados'] += (int) $manualRec['generados'];
$resultados['total_generados'] += (int) $manualRec['generados'];
foreach ($manualRec['mensajes'] as $msg) {
    $resultados['mensajes'][] = $msg;
}

} // Fin del foreach de sistemas

// Cerrar conexiones
@$conn->close();
@$conn_hp->close();

// Si es AJAX, devolver JSON
if ($es_ajax) {
    header('Content-Type: application/json');
    echo json_encode($resultados);
    exit;
}

// Si no es AJAX, mostrar la vista HTML
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generar Pagos Automáticos</title>
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="css/admin-platform.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f1f5f9;
        }
        .card-modern {
            background: white;
            border-radius: 24px;
            border: none;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .card-header-modern {
            background: #000147;
            color: white;
            padding: 20px 24px;
            font-weight: 600;
        }
        .btn-modern {
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-modern:hover {
            transform: translateY(-2px);
        }
        .log-container {
            background: #1e1e2f;
            color: #a5f3c3;
            font-family: 'Courier New', monospace;
            padding: 20px;
            border-radius: 16px;
            max-height: 500px;
            overflow-y: auto;
            font-size: 13px;
        }
        .log-line {
            padding: 4px 0;
            border-bottom: 1px solid #2d2d3f;
        }
        .resultado-card {
            background: #f8f9fc;
            border-radius: 16px;
            padding: 20px;
        }
        .numero-grande {
            font-size: 32px;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <?php include "menu.php"; ?>
    
    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            <div class="container-xl my-4 py-4 legacy-touch" style="max-width: 96% !important;">
                <div class="card-modern">
                    <div class="card-header-modern">
                        <h4 class="mb-0"><i class="fas fa-sync-alt me-2"></i> Generar Pagos Automáticos</h4>
                    </div>
                    <div class="card-body p-4">
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Este proceso generará automáticamente pagos para dominios y hosting que tengan fecha de vencimiento en los próximos 30 días.
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <button class="btn btn-primary btn-modern" id="btnGenerarPagos">
                                    <i class="fas fa-play me-2"></i> Generar Pagos Ahora
                                </button>
                                <a href="pagos.php" class="btn btn-secondary btn-modern ms-2">
                                    <i class="fas fa-arrow-left me-2"></i> Volver a Pagos
                                </a>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <h5 class="mb-3"><i class="fas fa-terminal me-2"></i> Log de Procesamiento</h5>
                                <div class="log-container" id="logContainer">
                                    <div class="log-line">⏳ Esperando inicio del proceso...</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-4" id="resultadosContainer" style="display: none;">
                            <div class="col-md-6">
                                <div class="resultado-card text-center">
                                    <i class="fas fa-globe fa-2x text-primary mb-2"></i>
                                    <h5>Dominios</h5>
                                    <hr>
                                    <p><strong>Generados:</strong> <span id="dominiosGenerados" class="text-success fw-bold">0</span></p>
                                    <p><strong>Errores:</strong> <span id="dominiosErrores" class="text-danger fw-bold">0</span></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="resultado-card text-center">
                                    <i class="fas fa-server fa-2x text-primary mb-2"></i>
                                    <h5>Hosting</h5>
                                    <hr>
                                    <p><strong>Generados:</strong> <span id="hostingGenerados" class="text-success fw-bold">0</span></p>
                                    <p><strong>Errores:</strong> <span id="hostingErrores" class="text-danger fw-bold">0</span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="js/sb-admin-2.min.js"></script>
    
    <script>
        function agregarLog(mensaje, tipo = 'info') {
            const logContainer = document.getElementById('logContainer');
            const icono = tipo === 'success' ? '✅' : (tipo === 'error' ? '❌' : (tipo === 'warning' ? '⚠️' : 'ℹ️'));
            const logLine = document.createElement('div');
            logLine.className = 'log-line';
            logLine.innerHTML = `${icono} ${mensaje}`;
            logContainer.appendChild(logLine);
            logContainer.scrollTop = logContainer.scrollHeight;
        }
        
        function generarPagos() {
            const btn = $('#btnGenerarPagos');
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> Generando pagos...');
            
            $('#logContainer').empty();
            agregarLog('Iniciando proceso de generación de pagos...', 'info');
            agregarLog('--------------------------------------------------', 'info');
            
            $.ajax({
                url: 'generar_pago.php',
                type: 'POST',
                data: {
                    ajax: 1
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Mostrar mensajes del log
                        if (response.mensajes && response.mensajes.length > 0) {
                            response.mensajes.forEach(mensaje => {
                                if (mensaje.includes('✅')) {
                                    agregarLog(mensaje, 'success');
                                } else if (mensaje.includes('❌')) {
                                    agregarLog(mensaje, 'error');
                                } else if (mensaje.includes('⚠️')) {
                                    agregarLog(mensaje, 'warning');
                                } else {
                                    agregarLog(mensaje, 'info');
                                }
                            });
                        }
                        
                        agregarLog('--------------------------------------------------', 'info');
                        agregarLog(`📊 RESUMEN: ${response.total_generados} pagos generados exitosamente`, 'success');
                        agregarLog(`   - Dominios: ${response.dominios.generados} generados, ${response.dominios.errores} errores`, 'info');
                        agregarLog(`   - Hosting: ${response.hosting.generados} generados, ${response.hosting.errores} errores`, 'info');
                        
                        // Actualizar estadísticas
                        $('#dominiosGenerados').text(response.dominios.generados);
                        $('#dominiosErrores').text(response.dominios.errores);
                        $('#hostingGenerados').text(response.hosting.generados);
                        $('#hostingErrores').text(response.hosting.errores);
                        $('#resultadosContainer').show();
                        
                        Swal.fire({
                            icon: 'success',
                            title: 'Proceso completado',
                            html: `Se generaron <strong>${response.total_generados}</strong> pagos correctamente.<br>
                                   Dominios: ${response.dominios.generados} generados, ${response.dominios.errores} errores<br>
                                   Hosting: ${response.hosting.generados} generados, ${response.hosting.errores} errores`,
                            timer: 3000,
                            showConfirmButton: true
                        });
                        
                        // Preguntar si quiere ir a la página de pagos
                        setTimeout(() => {
                            Swal.fire({
                                title: '¿Ir a la lista de pagos?',
                                text: '¿Deseas ver los pagos generados?',
                                icon: 'question',
                                showCancelButton: true,
                                confirmButtonText: 'Sí, ir a pagos',
                                cancelButtonText: 'Quedarme aquí'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.location.href = 'pagos.php';
                                }
                            });
                        }, 500);
                        
                    } else {
                        agregarLog(`❌ Error: ${response.message || 'Error desconocido'}`, 'error');
                        Swal.fire('Error', response.message || 'Error al generar pagos', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error AJAX:', error);
                    agregarLog(`❌ Error de conexión: ${error}`, 'error');
                    Swal.fire('Error', 'Error de conexión al servidor', 'error');
                },
                complete: function() {
                    btn.prop('disabled', false).html('<i class="fas fa-play me-2"></i> Generar Pagos Ahora');
                }
            });
        }
        
        $(document).ready(function() {
            $('#btnGenerarPagos').on('click', generarPagos);
        });
    </script>
</body>
</html>