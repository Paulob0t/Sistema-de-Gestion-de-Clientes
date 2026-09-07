<?php
// Script para verificar y actualizar el estado de productos basado en fechas de pago
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir conexión a la base de datos
require_once('conn.php');

// Configurar zona horaria
date_default_timezone_set('America/Mexico_City');

// Función para registrar actividad
function logActivity($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logFile = 'actividad_inactividad.log';
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    echo "[$timestamp] $message<br>\n";
}

logActivity("=== INICIANDO VERIFICACIÓN DE ESTADOS DE PRODUCTOS ===");

try {
    // Obtener fecha actual
    $fechaActual = date('Y-m-d');
    logActivity("Fecha actual: $fechaActual");

    // ===== VERIFICAR HOSTING =====
    logActivity("--- Verificando productos de HOSTING ---");
    
    // Buscar hosting con fecha de pago vencida y estado activo
    $sqlHosting = "SELECT id_orden, nom_host, fecha_pago, estado_producto 
                   FROM hosting 
                   WHERE fecha_pago <= ? AND estado_producto = 1";
    
    $stmtHosting = $conn->prepare($sqlHosting);
    $stmtHosting->bind_param("s", $fechaActual);
    $stmtHosting->execute();
    $resultHosting = $stmtHosting->get_result();
    
    $hostingVencidos = [];
    while ($row = $resultHosting->fetch_assoc()) {
        $hostingVencidos[] = $row;
    }
    
    logActivity("Hosting vencidos encontrados: " . count($hostingVencidos));
    
    // Actualizar hosting vencidos a inactivo
    if (count($hostingVencidos) > 0) {
        $updateHosting = $conn->prepare("UPDATE hosting SET estado_producto = 0 WHERE id_orden = ?");
        
        foreach ($hostingVencidos as $hosting) {
            $updateHosting->bind_param("i", $hosting['id_orden']);
            if ($updateHosting->execute()) {
                logActivity("✅ Hosting desactivado: ID {$hosting['id_orden']} - {$hosting['nom_host']} (Fecha pago: {$hosting['fecha_pago']})");
            } else {
                logActivity("❌ Error al desactivar hosting ID {$hosting['id_orden']}: " . $conn->error);
            }
        }
    }
    
    // ===== VERIFICAR DOMINIOS =====
    logActivity("--- Verificando DOMINIOS ---");
    
    // Buscar dominios con fecha de pago vencida y estado activo
    $sqlDominios = "SELECT id_dominio, url_dominio, fecha_pago, estado_dominio 
                    FROM dominios 
                    WHERE fecha_pago <= ? AND estado_dominio = 1";
    
    $stmtDominios = $conn->prepare($sqlDominios);
    $stmtDominios->bind_param("s", $fechaActual);
    $stmtDominios->execute();
    $resultDominios = $stmtDominios->get_result();
    
    $dominiosVencidos = [];
    while ($row = $resultDominios->fetch_assoc()) {
        $dominiosVencidos[] = $row;
    }
    
    logActivity("Dominios vencidos encontrados: " . count($dominiosVencidos));
    
    // Actualizar dominios vencidos a inactivo
    if (count($dominiosVencidos) > 0) {
        $updateDominios = $conn->prepare("UPDATE dominios SET estado_dominio = 0 WHERE id_dominio = ?");
        
        foreach ($dominiosVencidos as $dominio) {
            $updateDominios->bind_param("i", $dominio['id_dominio']);
            if ($updateDominios->execute()) {
                logActivity("✅ Dominio desactivado: ID {$dominio['id_dominio']} - {$dominio['url_dominio']} (Fecha pago: {$dominio['fecha_pago']})");
            } else {
                logActivity("❌ Error al desactivar dominio ID {$dominio['id_dominio']}: " . $conn->error);
            }
        }
    }
    
    // ===== RESUMEN =====
    logActivity("--- RESUMEN ---");
    logActivity("Total hosting desactivados: " . count($hostingVencidos));
    logActivity("Total dominios desactivados: " . count($dominiosVencidos));
    logActivity("Total productos desactivados: " . (count($hostingVencidos) + count($dominiosVencidos)));
    
    // ===== MOSTRAR PRODUCTOS ACTIVOS =====
    logActivity("--- PRODUCTOS ACTIVOS ---");
    
    // Hosting activos
    $hostingActivos = $conn->query("SELECT COUNT(*) as total FROM hosting WHERE estado_producto = 1")->fetch_assoc();
    logActivity("Hosting activos: " . $hostingActivos['total']);
    
    // Dominios activos
    $dominiosActivos = $conn->query("SELECT COUNT(*) as total FROM dominios WHERE estado_dominio = 1")->fetch_assoc();
    logActivity("Dominios activos: " . $dominiosActivos['total']);
    
    // ===== PRÓXIMOS VENCIMIENTOS =====
    logActivity("--- PRÓXIMOS VENCIMIENTOS (30 días) ---");
    
    $fechaLimite = date('Y-m-d', strtotime('+30 days'));
    
    // Hosting próximos a vencer
    $sqlProximosHosting = "SELECT id_orden, nom_host, fecha_pago 
                           FROM hosting 
                           WHERE fecha_pago > ? AND fecha_pago <= ? AND estado_producto = 1
                           ORDER BY fecha_pago ASC";
    
    $stmtProximosHosting = $conn->prepare($sqlProximosHosting);
    $stmtProximosHosting->bind_param("ss", $fechaActual, $fechaLimite);
    $stmtProximosHosting->execute();
    $resultProximosHosting = $stmtProximosHosting->get_result();
    
    $proximosHosting = [];
    while ($row = $resultProximosHosting->fetch_assoc()) {
        $proximosHosting[] = $row;
    }
    
    logActivity("Hosting próximos a vencer (30 días): " . count($proximosHosting));
    foreach ($proximosHosting as $hosting) {
        logActivity("⚠️  Hosting: {$hosting['nom_host']} - Vence: {$hosting['fecha_pago']}");
    }
    
    // Dominios próximos a vencer
    $sqlProximosDominios = "SELECT id_dominio, url_dominio, fecha_pago 
                            FROM dominios 
                            WHERE fecha_pago > ? AND fecha_pago <= ? AND estado_dominio = 1
                            ORDER BY fecha_pago ASC";
    
    $stmtProximosDominios = $conn->prepare($sqlProximosDominios);
    $stmtProximosDominios->bind_param("ss", $fechaActual, $fechaLimite);
    $stmtProximosDominios->execute();
    $resultProximosDominios = $stmtProximosDominios->get_result();
    
    $proximosDominios = [];
    while ($row = $resultProximosDominios->fetch_assoc()) {
        $proximosDominios[] = $row;
    }
    
    logActivity("Dominios próximos a vencer (30 días): " . count($proximosDominios));
    foreach ($proximosDominios as $dominio) {
        logActivity("⚠️  Dominio: {$dominio['url_dominio']} - Vence: {$dominio['fecha_pago']}");
    }
    
    logActivity("=== VERIFICACIÓN COMPLETADA ===");
    
} catch (Exception $e) {
    logActivity("❌ ERROR: " . $e->getMessage());
    error_log("Error en actividad_inactividad.php: " . $e->getMessage());
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Estados de Productos</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
        }
        .success {
            color: #28a745;
        }
        .error {
            color: #dc3545;
        }
        .warning {
            color: #ffc107;
        }
        .info {
            color: #17a2b8;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 10px 5px;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
        }
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Verificación de Estados de Productos</h1>
        <p><strong>Fecha de ejecución:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
        
        <div style="margin-top: 30px;">
            <h3>Acciones disponibles:</h3>
            <a href="actividad_inactividad.php" class="btn btn-primary">🔄 Ejecutar verificación nuevamente</a>
            <a href="pagos.php" class="btn btn-secondary">📋 Ver pagos</a>
        </div>
        
        <div style="margin-top: 30px;">
            <h3>Información:</h3>
            <p>Este script verifica automáticamente:</p>
            <ul>
                <li>Productos de hosting con fecha de pago vencida</li>
                <li>Dominios con fecha de pago vencida</li>
                <li>Actualiza el estado_producto a 0 (inactivo) para productos vencidos</li>
                <li>Muestra productos próximos a vencer en 30 días</li>
            </ul>
        </div>
        
        <div style="margin-top: 30px;">
            <h3>Logs:</h3>
            <p>Para ver los logs detallados, consulta el archivo: <code>actividad_inactividad.log</code></p>
        </div>
    </div>
</body>
</html>
