<?php
require_once __DIR__.'/../auth_middleware.php';
require_once __DIR__ . '/bootstrap_conexion.php';

header('Content-Type: application/json');

try {
    // Obtener parámetros de filtro
    $period = $_GET['period'] ?? 'week';
    $metricType = $_GET['metricType'] ?? 'all';
    $startDate = $_GET['startDate'] ?? '';
    $endDate = $_GET['endDate'] ?? '';
    
    // Filtros de la tabla principal
    $estadoFilter = $_GET['estadoFilter'] ?? '';
    $prioridadFilter = $_GET['prioridadFilter'] ?? '';
    $agenteFilter = $_GET['agenteFilter'] ?? '';
    $clienteFilter = $_GET['clienteFilter'] ?? '';
    $proyectoFilter = $_GET['proyectoFilter'] ?? '';
    
    // Calcular fechas según el período
    $dateRange = calculateDateRange($period, $startDate, $endDate);
    
    // Construir consultas con filtros
    $whereConditions = buildWhereConditions($estadoFilter, $prioridadFilter, $agenteFilter, $clienteFilter, $proyectoFilter);
    $dateCondition = " AND s.fecha_solicitud BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'";
    
    // Obtener métricas generales
    $metrics = getGeneralMetrics($conexion, $whereConditions, $dateCondition);
    
    // Obtener datos por agente
    $agents = getAgentMetrics($conexion, $whereConditions, $dateCondition);
    
    // Preparar datos para gráficos
    $chartData = prepareChartData($agents);
    
    echo json_encode([
        'success' => true,
        'metrics' => $metrics,
        'agents' => $agents,
        'chartData' => $chartData
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function calculateDateRange($period, $startDate, $endDate) {
    if ($period === 'custom' && $startDate && $endDate) {
        return [
            'start' => $startDate . ' 00:00:00',
            'end' => $endDate . ' 23:59:59'
        ];
    }
    
    $today = date('Y-m-d');
    
    switch ($period) {
        case 'today':
            return [
                'start' => $today . ' 00:00:00',
                'end' => $today . ' 23:59:59'
            ];
        case 'week':
            return [
                'start' => date('Y-m-d', strtotime('monday this week')) . ' 00:00:00',
                'end' => date('Y-m-d', strtotime('sunday this week')) . ' 23:59:59'
            ];
        case 'month':
            return [
                'start' => date('Y-m-01') . ' 00:00:00',
                'end' => date('Y-m-t') . ' 23:59:59'
            ];
        case 'quarter':
            $quarter = ceil(date('n') / 3);
            $startMonth = ($quarter - 1) * 3 + 1;
            $endMonth = $quarter * 3;
            return [
                'start' => date("Y-{$startMonth}-01") . ' 00:00:00',
                'end' => date("Y-{$endMonth}-t") . ' 23:59:59'
            ];
        case 'year':
            return [
                'start' => date('Y-01-01') . ' 00:00:00',
                'end' => date('Y-12-31') . ' 23:59:59'
            ];
        default:
            return [
                'start' => date('Y-m-d', strtotime('-30 days')) . ' 00:00:00',
                'end' => $today . ' 23:59:59'
            ];
    }
}

function buildWhereConditions($estado, $prioridad, $agente, $cliente, $proyecto) {
    $conditions = [];
    
    if (!empty($estado)) {
        $conditions[] = "s.estado = '" . mysqli_real_escape_string($GLOBALS['conexion'], $estado) . "'";
    }
    
    if (!empty($prioridad)) {
        $conditions[] = "s.prioridad = '" . mysqli_real_escape_string($GLOBALS['conexion'], $prioridad) . "'";
    }
    
    if (!empty($agente)) {
        $conditions[] = "s.usuario_asignado = " . (int)$agente;
    }
    
    if (!empty($cliente)) {
        $conditions[] = "s.id_cliente = " . (int)$cliente;
    }
    
    if (!empty($proyecto)) {
        $conditions[] = "s.id_proyecto = " . (int)$proyecto;
    }
    
    return empty($conditions) ? '1=1' : implode(' AND ', $conditions);
}

function getGeneralMetrics($conexion, $whereConditions, $dateCondition) {
    $sql = "SELECT 
        COUNT(*) as totalTickets,
        SUM(CASE WHEN estado = 'Finalizado' THEN 1 ELSE 0 END) as completedTickets,
        SUM(CASE WHEN estado = 'Pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN estado = 'En Proceso' THEN 1 ELSE 0 END) as enProceso
    FROM solicitudes s 
    WHERE $whereConditions $dateCondition";
    
    $result = $conexion->query($sql);
    $data = $result->fetch_assoc();
    
    // Calcular tasa de finalización
    $completionRate = $data['totalTickets'] > 0 ? 
        round(($data['completedTickets'] / $data['totalTickets']) * 100, 2) : 0;
    
    // Calcular tiempo promedio de finalización (simplificado)
    $avgTimeSql = "SELECT AVG(TIMESTAMPDIFF(HOUR, fecha_solicitud, fecha_termina)) as avgTime 
                   FROM solicitudes 
                   WHERE estado = 'Finalizado' AND $whereConditions $dateCondition";
    $avgResult = $conexion->query($avgTimeSql);
    $avgData = $avgResult->fetch_assoc();
    $avgCompletionTime = round($avgData['avgTime'] ?? 0, 1);
    
    return [
        'totalTickets' => $data['totalTickets'] ?? 0,
        'completedTickets' => $data['completedTickets'] ?? 0,
        'pendientes' => $data['pendientes'] ?? 0,
        'enProceso' => $data['enProceso'] ?? 0,
        'completionRate' => $completionRate,
        'avgCompletionTime' => $avgCompletionTime
    ];
}

function getAgentMetrics($conexion, $whereConditions, $dateCondition) {
    $sql = "SELECT 
        a.id,
        a.nombre,
        COUNT(s.id) as totalAsignadas,
        SUM(CASE WHEN s.estado = 'En Proceso' THEN 1 ELSE 0 END) as enProceso,
        SUM(CASE WHEN s.estado = 'Finalizado' THEN 1 ELSE 0 END) as finalizadas,
        SUM(CASE WHEN s.estado = 'Pendiente' AND s.fecha_lim < NOW() THEN 1 ELSE 0 END) as pendientesVencidas
    FROM agentes a
    LEFT JOIN solicitudes s ON a.id = s.usuario_asignado AND $whereConditions $dateCondition
    GROUP BY a.id, a.nombre
    ORDER BY a.nombre";
    
    $result = $conexion->query($sql);
    $agents = [];
    
    while ($row = $result->fetch_assoc()) {
        // Calcular tasa de finalización por agente
        $completionRate = $row['totalAsignadas'] > 0 ? 
            round(($row['finalizadas'] / $row['totalAsignadas']) * 100, 2) : 0;
        
        // Calcular tiempo promedio por agente
        $timeSql = "SELECT AVG(TIMESTAMPDIFF(HOUR, fecha_solicitud, fecha_termina)) as avgTime 
                    FROM solicitudes 
                    WHERE usuario_asignado = {$row['id']} AND estado = 'Finalizado' $dateCondition";
        $timeResult = $conexion->query($timeSql);
        $timeData = $timeResult->fetch_assoc();
        $avgTime = round($timeData['avgTime'] ?? 0, 1);
        
        $agents[] = [
            'id' => $row['id'],
            'nombre' => $row['nombre'],
            'totalAsignadas' => $row['totalAsignadas'],
            'enProceso' => $row['enProceso'],
            'finalizadas' => $row['finalizadas'],
            'pendientesVencidas' => $row['pendientesVencidas'],
            'tasaFinalizacion' => $completionRate,
            'tiempoPromedio' => $avgTime
        ];
    }
    
    return $agents;
}

function prepareChartData($agents) {
    $agentNames = [];
    $completions = [];
    $avgTimes = [];
    
    // Datos para gráficos de prioridad (simplificado)
    $priorityHigh = 0;
    $priorityMedium = 0;
    $priorityLow = 0;
    
    foreach ($agents as $agent) {
        $agentNames[] = $agent['nombre'];
        $completions[] = $agent['finalizadas'];
        $avgTimes[] = $agent['tiempoPromedio'];
        
        // En una implementación real, estos valores vendrían de la base de datos
        $priorityHigh += $agent['finalizadas'] * 0.4; // Simulación
        $priorityMedium += $agent['finalizadas'] * 0.35;
        $priorityLow += $agent['finalizadas'] * 0.25;
    }
    
    return [
        'agentNames' => $agentNames,
        'completions' => $completions,
        'avgTimes' => $avgTimes,
        'priorityHigh' => $priorityHigh,
        'priorityMedium' => $priorityMedium,
        'priorityLow' => $priorityLow
    ];
}
?>