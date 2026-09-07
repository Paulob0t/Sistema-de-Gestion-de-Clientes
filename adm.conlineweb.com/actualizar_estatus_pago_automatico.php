<?php
/**
 * Script automático para cambiar el estatus de pago de dominios
 * Cambia el estatus a 0 (No pagado) cuando falta un mes o menos para la fecha de vencimiento
 * 
 * Este script debe ejecutarse periódicamente (por ejemplo, mediante un cron job diario)
 * Ejemplo de configuración en crontab (ejecutar diariamente a las 00:00):
 * 0 0 * * * /usr/bin/php /ruta/al/archivo/actualizar_estatus_pago_automatico.php
 */

error_reporting(E_ALL);
ini_set("display_errors", 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/estatus_pago_automatico.log');

// Incluir archivo de conexión
include "conn.php";

// Función para escribir en log
function escribirLog($mensaje) {
    $log_dir = __DIR__ . '/logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    $log_file = $log_dir . '/estatus_pago_automatico.log';
    $fecha = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[{$fecha}] {$mensaje}\n", FILE_APPEND);
}

try {
    escribirLog("===== INICIO DE PROCESO AUTOMÁTICO =====");
    
    // Calcular la fecha límite (un mes desde hoy)
    $fecha_limite = date('Y-m-d', strtotime('+1 month'));
    $fecha_actual = date('Y-m-d');
    
    escribirLog("Fecha actual: {$fecha_actual}");
    escribirLog("Fecha límite (1 mes): {$fecha_limite}");
    
    // Buscar dominios que:
    // 1. Estén marcados como pagados (estatus_pago = 1)
    // 2. Su fecha de pago (vencimiento) sea menor o igual a la fecha límite
    // 3. No estén eliminados (eliminado = 0)
    $sql = "SELECT id_dominio, url_dominio, fecha_pago, cliente_id, estatus_pago
            FROM dominios
            WHERE estatus_pago = 1 
            AND fecha_pago <= ? 
            AND fecha_pago >= ?
            AND eliminado = 0";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Error al preparar consulta de selección: " . $conn->error);
    }
    
    $stmt->bind_param("ss", $fecha_limite, $fecha_actual);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $dominios_a_actualizar = [];
    while ($row = $result->fetch_assoc()) {
        $dominios_a_actualizar[] = $row;
    }
    
    $stmt->close();
    
    $total_encontrados = count($dominios_a_actualizar);
    escribirLog("Dominios encontrados que requieren actualización: {$total_encontrados}");
    
    if ($total_encontrados > 0) {
        // Actualizar el estatus de pago a 0 (No pagado)
        $sql_update = "UPDATE dominios SET estatus_pago = 0 WHERE id_dominio = ?";
        $stmt_update = $conn->prepare($sql_update);
        
        if (!$stmt_update) {
            throw new Exception("Error al preparar consulta de actualización: " . $conn->error);
        }
        
        $actualizados = 0;
        $errores = 0;
        
        foreach ($dominios_a_actualizar as $dominio) {
            escribirLog("Procesando dominio ID: {$dominio['id_dominio']} - URL: {$dominio['url_dominio']} - Vencimiento: {$dominio['fecha_pago']}");
            
            $stmt_update->bind_param("i", $dominio['id_dominio']);
            
            if ($stmt_update->execute()) {
                $actualizados++;
                escribirLog("  ✓ Dominio ID {$dominio['id_dominio']} actualizado correctamente a 'No pagado'");
            } else {
                $errores++;
                escribirLog("  ✗ Error al actualizar dominio ID {$dominio['id_dominio']}: " . $stmt_update->error);
            }
        }
        
        $stmt_update->close();
        
        escribirLog("Resumen: {$actualizados} dominios actualizados exitosamente, {$errores} errores");
        
        // Si se ejecuta desde el navegador, mostrar resultado
        if (php_sapi_name() !== 'cli') {
            echo json_encode([
                'success' => true,
                'total_encontrados' => $total_encontrados,
                'actualizados' => $actualizados,
                'errores' => $errores,
                'dominios' => $dominios_a_actualizar
            ]);
        }
    } else {
        escribirLog("No se encontraron dominios que requieran actualización");
        
        // Si se ejecuta desde el navegador, mostrar resultado
        if (php_sapi_name() !== 'cli') {
            echo json_encode([
                'success' => true,
                'message' => 'No se encontraron dominios que requieran actualización',
                'total_encontrados' => 0
            ]);
        }
    }
    
    escribirLog("===== FIN DE PROCESO AUTOMÁTICO =====\n");
    
} catch (Exception $e) {
    $mensaje_error = "ERROR CRÍTICO: " . $e->getMessage();
    escribirLog($mensaje_error);
    
    // Si se ejecuta desde el navegador, mostrar error
    if (php_sapi_name() !== 'cli') {
        echo json_encode([
            'success' => false,
            'message' => $mensaje_error
        ]);
    }
}

$conn->close();
?>
