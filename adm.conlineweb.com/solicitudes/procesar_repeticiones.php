<?php
/**
 * Script para procesar repeticiones de solicitudes
 * Este archivo debe ejecutarse diariamente a las 10:00 AM via cron
 * 
 * Comando cron sugerido:
 * 0 10 * * * /usr/bin/php /ruta/a/este/archivo/procesar_repeticiones.php
 */

// Incluir conexión a la base de datos
require_once __DIR__ . '/db/conexion.php';

// Carga perezosa del mailer de asignación (si existe) - similar a guardar.php
@require_once __DIR__ . '/enviar_correo_asignacion.php';

// Configurar zona horaria
date_default_timezone_set('America/Mexico_City');

// Determinar si se ejecuta desde navegador o CLI
$esCLI = php_sapi_name() === 'cli';
$generarHTML = !$esCLI; // Generar HTML solo si se accede desde navegador

// Array para almacenar el reporte
$reporte = [
    'inicio' => date('Y-m-d H:i:s'),
    'mensajes' => [],
    'procesadas' => [],
    'errores' => [],
    'estadisticas' => [
        'total_encontradas' => 0,
        'exitosas' => 0,
        'fallidas' => 0
    ]
];

// Función para escribir log
function escribir_log($mensaje, $tipo = 'info') {
    global $reporte, $esCLI;
    
    $fecha = date('Y-m-d H:i:s');
    $log = "[$fecha] $mensaje\n";
    error_log($log, 3, __DIR__ . '/repeticiones.log');
    
    if($esCLI) {
        echo $log;
    }
    
    // Agregar al reporte
    $reporte['mensajes'][] = [
        'fecha' => $fecha,
        'tipo' => $tipo,
        'mensaje' => $mensaje
    ];
}

escribir_log("=== INICIO PROCESAMIENTO REPETICIONES ===");

// 1. Intentar obtener un lock para evitar ejecuciones simultáneas
$lockName = 'cron_procesar_repeticiones';
$lockRes = $conexion->query("SELECT GET_LOCK('$lockName', 1) lock_obtenido");
if(!$lockRes){
    escribir_log("No se pudo solicitar GET_LOCK (consulta falló) – abortando", 'error');
    if($generarHTML) mostrar_reporte_html();
    exit(1);
}
$lockRow = $lockRes->fetch_assoc();
if((int)$lockRow['lock_obtenido'] !== 1){
    escribir_log("Otro proceso ya está ejecutándose (no se obtuvo el lock) – saliendo", 'warning');
    if($generarHTML) mostrar_reporte_html();
    exit(0);
}
escribir_log("Lock '$lockName' obtenido", 'success');

try {
    $fechaHoy = date('Y-m-d');
    $horaActual = date('H:i:s');
    escribir_log("Procesando fecha: $fechaHoy a las $horaActual");
    
    // Buscar solicitudes que necesiten repetirse hoy o antes
    $sql = "SELECT * FROM solicitudes 
        WHERE repetir > 0 
        AND fecha_repeticion IS NOT NULL 
        AND fecha_repeticion != '0000-00-00 00:00:00'
        AND DATE(fecha_repeticion) <= '$fechaHoy'
        AND (fecha_ultima_repeticion IS NULL OR fecha_ultima_repeticion < '$fechaHoy')
        AND repeticion_original_id IS NULL
        ORDER BY id FOR UPDATE";
    
    escribir_log("Ejecutando consulta para buscar solicitudes pendientes de repetir...");
    
    $resultado = $conexion->query($sql);
    
    if (!$resultado) {
        throw new Exception("Error en consulta: " . $conexion->error);
    }
    
    $totalEncontradas = $resultado->num_rows;
    $reporte['estadisticas']['total_encontradas'] = $totalEncontradas;
    escribir_log("Solicitudes encontradas que necesitan repetirse: $totalEncontradas");
    
    $procesadas = 0;
    $errores = 0;
    
    while ($solicitud = $resultado->fetch_assoc()) {
        $fechaRepeticionProgramada = $solicitud['fecha_repeticion'];
        $ultimaGenerada = $solicitud['fecha_ultima_repeticion'] ?? 'Nunca';
        
        escribir_log("Procesando solicitud ID: {$solicitud['id']} - Título: {$solicitud['titulo']}");
        
        try {
            // Iniciar transacción
            $conexion->begin_transaction();
            
            // 1. Crear la nueva solicitud (repetición)
            $repeticion_original_id = (int)$solicitud['id'];
            $idCliente = isset($solicitud['id_cliente']) && $solicitud['id_cliente'] !== '' ? (int)$solicitud['id_cliente'] : null;
            $titulo     = $solicitud['titulo'];
            $descripcion= $solicitud['descripcion'];
            $usuarioAsignado = $solicitud['usuario_asignado'];
            // Mantener prioridad como string (igual que guardar.php)
            $prioridad  = isset($solicitud['prioridad']) ? (string)$solicitud['prioridad'] : '';
            // Detectar si la columna id_proyecto existe en la tabla
            $hasProyecto = false;
            $id_proyecto = null;
            $colResProyecto = $conexion->query("SHOW COLUMNS FROM solicitudes LIKE 'id_proyecto'");
            if ($colResProyecto && $colResProyecto->num_rows > 0) {
                $hasProyecto = true;
                $id_proyecto = array_key_exists('id_proyecto', $solicitud) && $solicitud['id_proyecto'] !== '' ? (int)$solicitud['id_proyecto'] : null;
            }
            // Detectar si la columna fecha_lim existe
            $hasFechaLimCol = false;
            $colResFechaLim = $conexion->query("SHOW COLUMNS FROM solicitudes LIKE 'fecha_lim'");
            if ($colResFechaLim && $colResFechaLim->num_rows > 0) {
                $hasFechaLimCol = true;
            }
            $fechaLim   = isset($solicitud['fecha_lim']) ? $solicitud['fecha_lim'] : null;

            // Calcular nueva fecha_lim: si la solicitud original tenía fecha_lim, la nueva
            // se calculará a partir de NOW() sumando el intervalo según el tipo de repetición
            // (1=Diaria, 2=Semanal, 3=Mensual). Solo se aplica si la columna fecha_lim existe.
            $nuevaFechaLim = null;
            $repetir_tipo = isset($solicitud['repetir']) ? (int)$solicitud['repetir'] : 0;
            if ($hasFechaLimCol && $fechaLim && $fechaLim !== '0000-00-00 00:00:00') {
                try {
                    $fechaBase = new DateTime('now', new DateTimeZone('America/Mexico_City'));
                    switch ($repetir_tipo) {
                        case 1:
                            $fechaBase->add(new DateInterval('P1D'));
                            break;
                        case 2:
                            $fechaBase->add(new DateInterval('P1W'));
                            break;
                        case 3:
                            $fechaBase->add(new DateInterval('P1M'));
                            break;
                        default:
                            // por defecto sumar un día
                            $fechaBase->add(new DateInterval('P1D'));
                            break;
                    }
                    $nuevaFechaLim = $fechaBase->format('Y-m-d H:i:s');
                } catch (Exception $ie) { /* ignore */ }
            }

            // Preparar valores para insertar la nueva solicitud repetida.
            // La columna `fecha_repeticion` en la tabla no permite NULL en este esquema,
            // así que usamos el sentinel '0000-00-00 00:00:00' para indicar "sin fecha".
            $fechaRepeticionNuevaRegistro = '0000-00-00 00:00:00';
            $repetir_val = 0; // la copia creada no debe seguir repitiendo

            // Construir columnas a insertar (añadimos id_proyecto si existe en la fila original)
            $insertCampos = 'titulo, descripcion, id_cliente, usuario_asignado, prioridad, repetir, fecha_repeticion, repeticion_original_id';
            if ($hasProyecto) {
                $insertCampos .= ', id_proyecto';
            }
            if ($nuevaFechaLim && $hasFechaLimCol) {
                $insertCampos .= ', fecha_lim';
            }
            $insertCampos .= ', estado, fecha_solicitud';

            // Construir placeholders (?) según columnas
            $placeholders = '?, ?, ?, ?, ?, ?, ?, ?'; // base: 8 campos
            if ($hasProyecto) $placeholders .= ', ?';
            if ($nuevaFechaLim && $hasFechaLimCol) $placeholders .= ', ?';
            $placeholders .= ", '" . 'Pendiente' . "', NOW()";

            $sqlInsert = "INSERT INTO solicitudes ($insertCampos) VALUES ($placeholders)";
            $stmtInsert = $conexion->prepare($sqlInsert);
            if (!$stmtInsert) {
                throw new Exception('Prepare insert falló: ' . $conexion->error);
            }

            // Bind params in this exact order:
            // titulo (s), descripcion (s), id_cliente (i), usuario_asignado (s), prioridad (i), repetir (i),
            // fecha_repeticion (s), repeticion_original_id (i) [, fecha_lim (s)]
            // Preparar tipos y parámetros dinámicamente. Mantener prioridad como string (como en guardar.php).
            $types = 'ssissisi'; // titulo(s), descripcion(s), id_cliente(i), usuario_asignado(s), prioridad(s), repetir(i), fecha_repeticion(s), repeticion_original_id(i)
            $params = [
                $titulo,
                $descripcion,
                $idCliente,
                $usuarioAsignado,
                $prioridad,
                $repetir_val,
                $fechaRepeticionNuevaRegistro,
                $repeticion_original_id
            ];

            if ($hasProyecto) {
                $types .= 'i';
                $params[] = $id_proyecto;
            }
            if ($nuevaFechaLim && $hasFechaLimCol) {
                $types .= 's';
                $params[] = $nuevaFechaLim;
            }

            // bind_param requires parameters to be passed by reference
            $bindNames = [];
            $bindNames[] = & $types;
            for ($i = 0; $i < count($params); $i++) {
                $bindNames[] = & $params[$i];
            }
            if (!call_user_func_array([$stmtInsert, 'bind_param'], $bindNames)) {
                throw new Exception('Bind params falló: ' . $stmtInsert->error);
            }

            if (!$stmtInsert->execute()) {
                throw new Exception('Error execute insert: ' . $stmtInsert->error);
            }
            $nuevoId = $stmtInsert->insert_id;
            $stmtInsert->close();
            escribir_log("Nueva solicitud creada con ID: $nuevoId", 'success');
            
            // 2. Calcular la próxima fecha de repetición
            $repetir = (int)$solicitud['repetir'];
            $baseFecha = null;
            try {
                $baseFecha = new DateTime($solicitud['fecha_repeticion']);
            } catch(Exception $eBF){
                $baseFecha = new DateTime();
            }
            
            $tipoRepeticion = '';
            switch ($repetir) {
                case 1: 
                    $baseFecha->add(new DateInterval('P1D'));
                    $tipoRepeticion = 'Diaria';
                    break;
                case 2: 
                    $baseFecha->add(new DateInterval('P1W'));
                    $tipoRepeticion = 'Semanal';
                    break;
                case 3: 
                    $baseFecha->add(new DateInterval('P1M'));
                    $tipoRepeticion = 'Mensual';
                    break;
                default: 
                    $baseFecha->add(new DateInterval('P1D'));
                    $tipoRepeticion = 'Diaria';
                    break;
            }
            $nuevaFechaRepeticion = $baseFecha->format('Y-m-d H:i:s');
            
            // 3. Actualizar la solicitud original
            $stmtUpd = $conexion->prepare("UPDATE solicitudes SET fecha_repeticion = ?, fecha_ultima_repeticion = ? WHERE id = ?");
            if(!$stmtUpd){ throw new Exception('Prepare update falló: '. $conexion->error); }
            $stmtUpd->bind_param('ssi', $nuevaFechaRepeticion, $fechaHoy, $solicitud['id']);
            if(!$stmtUpd->execute()){
                throw new Exception('Error execute update: '. $stmtUpd->error);
            }
            $stmtUpd->close();
            
            escribir_log("Solicitud original actualizada. Próxima repetición: $nuevaFechaRepeticion");
            
            // 4. Copiar notas
            $stmtNotasSel = $conexion->prepare("SELECT autor, nota FROM solicitudes_notas WHERE solicitud_id = ?");
            if($stmtNotasSel){
                $stmtNotasSel->bind_param('i', $solicitud['id']);
                if($stmtNotasSel->execute()){
                    $resNotas = $stmtNotasSel->get_result();
                    if($resNotas && $resNotas->num_rows > 0){
                        $stmtNotaIns = $conexion->prepare("INSERT INTO solicitudes_notas (solicitud_id, autor, nota, fecha_creacion) VALUES (?,?,?,NOW())");
                        if($stmtNotaIns){
                            while($nota = $resNotas->fetch_assoc()){
                                $notaAutor = $nota['autor'];
                                $notaTexto = 'COPIA: '. $nota['nota'];
                                $stmtNotaIns->bind_param('iss', $nuevoId, $notaAutor, $notaTexto);
                                $stmtNotaIns->execute();
                            }
                            $stmtNotaIns->close();
                            escribir_log("Notas copiadas a la nueva solicitud");
                        }
                    }
                }
                $stmtNotasSel->close();
            }
            
            // Confirmar transacción
            $conexion->commit();

            // Enviar correo de asignación si está disponible la función (separa post-commit para evitar fallos en la transacción)
            $emailEnviado = false;
            $emailDebug = null;
            $emailError = null;
            if (function_exists('enviar_correo_asignacion')) {
                try {
                    $agenteIdInt = is_numeric($usuarioAsignado) ? (int)$usuarioAsignado : 0;
                    if ($agenteIdInt > 0) {
                        $okMail = enviar_correo_asignacion(
                            $conexion,
                            $nuevoId,
                            (string)$titulo,
                            $idCliente !== null ? (int)$idCliente : null,
                            $agenteIdInt
                        );
                        $emailEnviado = (bool)$okMail;
                    }
                } catch (Throwable $t) {
                    $emailError = $t->getMessage();
                } catch (Exception $e) {
                    $emailError = $e->getMessage();
                }
            }

            // Preparar y agregar entrada única al reporte (incluye info de email)
            $entry = [
                'id_original' => $solicitud['id'],
                'id_nueva' => $nuevoId,
                'titulo' => $solicitud['titulo'],
                'tipo_repeticion' => $tipoRepeticion,
                'fecha_programada' => $fechaRepeticionProgramada,
                'proxima_repeticion' => $nuevaFechaRepeticion,
                'email_enviado' => $emailEnviado,
                'email_debug' => isset($GLOBALS['ENVIAR_CORREO_ASIG_DEBUG']) ? $GLOBALS['ENVIAR_CORREO_ASIG_DEBUG'] : null,
                'email_error' => $emailError
            ];

            $reporte['procesadas'][] = $entry;
            $procesadas++;
            
            escribir_log("✓ Solicitud procesada exitosamente", 'success');
            
        } catch (Exception $e) {
            $conexion->rollback();
            $errores++;
            $errorMsg = "Error procesando solicitud ID {$solicitud['id']}: " . $e->getMessage();
            escribir_log("✗ " . $errorMsg, 'error');
            
            // Agregar al reporte de errores
            $reporte['errores'][] = [
                'id' => $solicitud['id'],
                'titulo' => $solicitud['titulo'],
                'error' => $e->getMessage()
            ];
        }
    }
    
    $reporte['estadisticas']['exitosas'] = $procesadas;
    $reporte['estadisticas']['fallidas'] = $errores;
    
    escribir_log("=== RESUMEN ===");
    escribir_log("Solicitudes procesadas exitosamente: $procesadas", 'success');
    escribir_log("Errores: $errores", $errores > 0 ? 'error' : 'info');
    
    if ($totalEncontradas == 0) {
        escribir_log("No se encontraron solicitudes para procesar", 'info');
    }
    
    escribir_log("=== FIN PROCESAMIENTO REPETICIONES ===");
    
} catch (Exception $e) {
    escribir_log("ERROR CRÍTICO: " . $e->getMessage(), 'error');
    if($generarHTML) mostrar_reporte_html();
    exit(1);
} finally {
    try { 
        $conexion->query("DO RELEASE_LOCK('$lockName')"); 
        escribir_log("Lock '$lockName' liberado"); 
    } catch(Exception $e) { }
    if (isset($conexion)) { $conexion->close(); }
}

// Mostrar reporte si es desde navegador
if($generarHTML) {
    mostrar_reporte_html();
}

exit(0);

// ====== FUNCIÓN PARA GENERAR REPORTE HTML ======
function mostrar_reporte_html() {
    global $reporte;
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Reporte de Repeticiones - <?php echo date('d/m/Y H:i:s'); ?></title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                padding: 20px;
            }
            
            .container {
                max-width: 1200px;
                margin: 0 auto;
            }
            
            .header {
                background: white;
                border-radius: 15px;
                padding: 30px;
                margin-bottom: 25px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            }
            
            .header h1 {
                color: #333;
                margin-bottom: 10px;
                display: flex;
                align-items: center;
                gap: 15px;
            }
            
            .header-info {
                display: flex;
                justify-content: space-between;
                margin-top: 15px;
                color: #666;
                font-size: 14px;
            }
            
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                margin-bottom: 25px;
            }
            
            .stat-card {
                background: white;
                border-radius: 12px;
                padding: 25px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                text-align: center;
            }
            
            .stat-icon {
                font-size: 40px;
                margin-bottom: 10px;
            }
            
            .stat-value {
                font-size: 48px;
                font-weight: bold;
                margin-bottom: 5px;
            }
            
            .stat-value.success { color: #28a745; }
            .stat-value.error { color: #dc3545; }
            .stat-value.info { color: #17a2b8; }
            
            .stat-label {
                color: #666;
                font-size: 14px;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            
            .section {
                background: white;
                border-radius: 12px;
                padding: 25px;
                margin-bottom: 20px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            }
            
            .section-title {
                font-size: 20px;
                font-weight: bold;
                color: #333;
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .solicitud-item {
                background: #f8f9fa;
                border-left: 4px solid #28a745;
                border-radius: 8px;
                padding: 15px;
                margin-bottom: 15px;
            }
            
            .solicitud-item.error {
                border-left-color: #dc3545;
            }
            
            .solicitud-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 10px;
            }
            
            .solicitud-titulo {
                font-weight: bold;
                color: #333;
                font-size: 16px;
            }
            
            .solicitud-badge {
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: bold;
                color: white;
            }
            
            .badge-diaria { background: #28a745; }
            .badge-semanal { background: #17a2b8; }
            .badge-mensual { background: #6f42c1; }
            
            .solicitud-details {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 10px;
                margin-top: 10px;
                font-size: 13px;
                color: #666;
            }
            
            .detail-item {
                display: flex;
                align-items: center;
                gap: 5px;
            }
            
            .detail-label {
                font-weight: 600;
                color: #333;
            }
            
            .empty-state {
                text-align: center;
                padding: 40px;
                color: #999;
            }
            
            .empty-state-icon {
                font-size: 60px;
                margin-bottom: 15px;
            }
            
            .log-section {
                background: #1e1e1e;
                border-radius: 12px;
                padding: 20px;
                margin-bottom: 20px;
                color: #fff;
                font-family: 'Courier New', monospace;
                font-size: 12px;
                max-height: 400px;
                overflow-y: auto;
            }
            
            .log-line {
                padding: 5px;
                border-bottom: 1px solid #333;
            }
            
            .log-line:last-child {
                border-bottom: none;
            }
            
            .log-info { color: #17a2b8; }
            .log-success { color: #28a745; }
            .log-warning { color: #ffc107; }
            .log-error { color: #dc3545; }
            
            .refresh-btn {
                background: #667eea;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 8px;
                font-size: 14px;
                font-weight: bold;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }
            
            .refresh-btn:hover {
                background: #5568d3;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>
                    🔄 Reporte de Procesamiento de Repeticiones
                    <button class="refresh-btn" onclick="location.reload()">↻ Actualizar</button>
                </h1>
                <div class="header-info">
                    <span>⏰ Ejecutado: <?php echo $reporte['inicio']; ?></span>
                    <span>🖥️ Modo: Navegador Web</span>
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">🔍</div>
                    <div class="stat-value info"><?php echo $reporte['estadisticas']['total_encontradas']; ?></div>
                    <div class="stat-label">Encontradas</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-value success"><?php echo $reporte['estadisticas']['exitosas']; ?></div>
                    <div class="stat-label">Exitosas</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">❌</div>
                    <div class="stat-value error"><?php echo $reporte['estadisticas']['fallidas']; ?></div>
                    <div class="stat-label">Fallidas</div>
                </div>
            </div>
            
            <?php if(!empty($reporte['procesadas'])): ?>
            <div class="section">
                <div class="section-title">✅ Solicitudes Repetidas Exitosamente</div>
                <?php foreach($reporte['procesadas'] as $item): ?>
                <div class="solicitud-item">
                    <div class="solicitud-header">
                        <div class="solicitud-titulo"><?php echo htmlspecialchars($item['titulo']); ?></div>
                        <span class="solicitud-badge badge-<?php echo strtolower($item['tipo_repeticion']); ?>">
                            <?php echo $item['tipo_repeticion']; ?>
                        </span>
                    </div>
                    <div class="solicitud-details">
                        <div class="detail-item">
                            <span class="detail-label">ID Original:</span>
                            <span>#<?php echo $item['id_original']; ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">ID Nueva:</span>
                            <span>#<?php echo $item['id_nueva']; ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Programada:</span>
                            <span><?php echo date('d/m/Y H:i', strtotime($item['fecha_programada'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Próxima:</span>
                            <span><?php echo date('d/m/Y H:i', strtotime($item['proxima_repeticion'])); ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if(!empty($reporte['errores'])): ?>
            <div class="section">
                <div class="section-title">❌ Errores Encontrados</div>
                <?php foreach($reporte['errores'] as $item): ?>
                <div class="solicitud-item error">
                    <div class="solicitud-header">
                        <div class="solicitud-titulo"><?php echo htmlspecialchars($item['titulo']); ?></div>
                        <span>ID: #<?php echo $item['id']; ?></span>
                    </div>
                    <div style="color: #dc3545; margin-top: 10px; font-size: 13px;">
                        <strong>Error:</strong> <?php echo htmlspecialchars($item['error']); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if(empty($reporte['procesadas']) && empty($reporte['errores'])): ?>
            <div class="section">
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h3>No se procesaron solicitudes</h3>
                    <p>No se encontraron solicitudes pendientes de repetir en este momento</p>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="section">
                <div class="section-title">📋 Log de Ejecución</div>
                <div class="log-section">
                    <?php foreach($reporte['mensajes'] as $msg): ?>
                    <div class="log-line log-<?php echo $msg['tipo']; ?>">
                        [<?php echo $msg['fecha']; ?>] <?php echo htmlspecialchars($msg['mensaje']); ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>