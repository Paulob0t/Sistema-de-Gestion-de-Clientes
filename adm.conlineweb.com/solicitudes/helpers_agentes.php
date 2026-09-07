<?php
/**
 * Funciones auxiliares para manejo de múltiples agentes asignados
 * Formato: CSV simple "1,3,5,7" almacenado en usuario_asignado
 */

// ============================================
// CONVERSIÓN ARRAY <-> STRING
// ============================================

/**
 * Convierte array de IDs a string CSV
 * @param array $agentesArray Ej: [1, 3, 5]
 * @return string Ej: "1,3,5"
 */
function agentesArrayToString($agentesArray) {
    if (!is_array($agentesArray)) {
        return '';
    }
    // Filtrar vacíos y convertir a enteros
    $agentesArray = array_filter($agentesArray, function($id) {
        return !empty($id) && is_numeric($id);
    });
    $agentesArray = array_map('intval', $agentesArray);
    return implode(',', $agentesArray);
}

/**
 * Convierte string CSV a array de IDs
 * @param string $agentesString Ej: "1,3,5"
 * @return array Ej: [1, 3, 5]
 */
function agentesStringToArray($agentesString) {
    if (empty($agentesString)) {
        return [];
    }
    $arr = explode(',', $agentesString);
    $arr = array_map('trim', $arr);
    $arr = array_filter($arr, function($id) {
        return is_numeric($id);
    });
    return array_map('intval', $arr);
}

// ============================================
// VISUALIZACIÓN HTML
// ============================================

/**
 * Genera badges HTML con nombres de agentes
 * @param mysqli $conexion
 * @param string $agentesString Ej: "1,3,5"
 * @return string HTML con badges
 */
function mostrarAgentesHTML($conexion, $agentesString) {
    if (empty($agentesString)) {
        return '<span style="color:#888;">Sin asignar</span>';
    }
    
    $ids = agentesStringToArray($agentesString);
    if (empty($ids)) {
        return '<span style="color:#888;">Sin asignar</span>';
    }
    
    // Escapar IDs para query segura
    $idsEscaped = array_map(function($id) use ($conexion) {
        return (int)$id;
    }, $ids);
    $idsString = implode(',', $idsEscaped);
    
    $query = "SELECT id, nombre FROM agentes WHERE id IN ($idsString) ORDER BY nombre";
    $result = $conexion->query($query);
    
    if (!$result) {
        return '<span style="color:#888;">Error al cargar agentes</span>';
    }
    
    if ($result->num_rows === 0) {
        return '<span style="color:#888;">Agente(s) no encontrado(s)</span>';
    }
    
    $html = '';
    $colores = ['#4361ee', '#3a0ca3', '#7209b7', '#f72585', '#06a77d', '#ffa600'];
    $colorIndex = 0;
    
    while ($row = $result->fetch_assoc()) {
        $color = $colores[$colorIndex % count($colores)];
        $nombre = htmlspecialchars($row['nombre']);
        $html .= '<span class="agente-badge" style="background-color: ' . $color . ';">' . $nombre . '</span> ';
        $colorIndex++;
    }
    
    return trim($html);
}

/**
 * Genera opciones <option> para select con agentes pre-seleccionados
 * @param mysqli $conexion
 * @param string $agentesSeleccionados Ej: "1,3" para marcar esos como selected
 * @return string HTML con <option> tags
 */
function generarOpcionesAgentes($conexion, $agentesSeleccionados = '') {
    $idsSeleccionados = agentesStringToArray($agentesSeleccionados);
    
    $query = "SELECT id, nombre FROM agentes WHERE idEmpresa IS NULL OR idEmpresa = '' ORDER BY nombre";
    $result = $conexion->query($query);
    
    if (!$result) {
        return '<option value="">Error al cargar agentes</option>';
    }
    
    $html = '';
    while ($row = $result->fetch_assoc()) {
        $id = (int)$row['id'];
        $nombre = htmlspecialchars($row['nombre']);
        $selected = in_array($id, $idsSeleccionados) ? ' selected' : '';
        $html .= '<option value="' . $id . '"' . $selected . '>' . $nombre . '</option>';
    }
    
    return $html;
}

// ============================================
// FILTRADO SQL
// ============================================

/**
 * Genera condición SQL para filtrar por agente usando FIND_IN_SET
 * @param int|string $agenteId
 * @return string Condición WHERE para usar en query
 */
function generarFiltroAgente($agenteId) {
    if (empty($agenteId)) {
        return '1=1'; // No filtrar
    }
    
    $agenteId = (int)$agenteId;
    
    // Busca el ID exacto o dentro de la lista CSV
    return "(usuario_asignado = '$agenteId' OR FIND_IN_SET('$agenteId', usuario_asignado) > 0)";
}

// ============================================
// VALIDACIÓN
// ============================================

/**
 * Valida que todos los IDs de agentes existan en la BD
 * @param mysqli $conexion
 * @param array $agentesArray
 * @return bool true si todos existen, false si alguno no existe
 */
function validarAgentesExisten($conexion, $agentesArray) {
    if (empty($agentesArray) || !is_array($agentesArray)) {
        return true; // Vacío es válido
    }
    
    $ids = array_map('intval', $agentesArray);
    $idsString = implode(',', $ids);
    
    $query = "SELECT COUNT(*) as total FROM agentes WHERE id IN ($idsString)";
    $result = $conexion->query($query);
    
    if (!$result) {
        return false;
    }
    
    $row = $result->fetch_assoc();
    return $row['total'] == count($ids);
}

/**
 * Obtiene nombres de agentes por sus IDs
 * @param mysqli $conexion
 * @param string $agentesString Ej: "1,3,5"
 * @return array Ej: ["Paulo Essau", "Carlos Paredes", "Gerardo Caudillo"]
 */
function obtenerNombresAgentes($conexion, $agentesString) {
    if (empty($agentesString)) {
        return [];
    }
    
    $ids = agentesStringToArray($agentesString);
    if (empty($ids)) {
        return [];
    }
    
    $idsEscaped = array_map('intval', $ids);
    $idsInString = implode(',', $idsEscaped);
    
    $query = "SELECT nombre FROM agentes WHERE id IN ($idsInString) ORDER BY nombre";
    $result = $conexion->query($query);
    
    if (!$result) {
        return [];
    }
    
    $nombres = [];
    while ($row = $result->fetch_assoc()) {
        $nombres[] = $row['nombre'];
    }
    
    return $nombres;
}

/**
 * Cuenta cuántos agentes están asignados
 * @param string $agentesString Ej: "1,3,5"
 * @return int Ej: 3
 */
function contarAgentes($agentesString) {
    $ids = agentesStringToArray($agentesString);
    return count($ids);
}

/**
 * Verifica si un agente específico está asignado
 * @param string $agentesString Ej: "1,3,5"
 * @param int $agenteId Ej: 3
 * @return bool
 */
function agenteEstaAsignado($agentesString, $agenteId) {
    $ids = agentesStringToArray($agentesString);
    return in_array((int)$agenteId, $ids);
}

/**
 * Agrega un agente a la lista sin duplicar
 * @param string $agentesString Ej: "1,3"
 * @param int $agenteId Ej: 5
 * @return string Ej: "1,3,5"
 */
function agregarAgente($agentesString, $agenteId) {
    $ids = agentesStringToArray($agentesString);
    $agenteId = (int)$agenteId;
    
    if (!in_array($agenteId, $ids)) {
        $ids[] = $agenteId;
    }
    
    return agentesArrayToString($ids);
}

/**
 * Remueve un agente de la lista
 * @param string $agentesString Ej: "1,3,5"
 * @param int $agenteId Ej: 3
 * @return string Ej: "1,5"
 */
function removerAgente($agentesString, $agenteId) {
    $ids = agentesStringToArray($agentesString);
    $agenteId = (int)$agenteId;
    
    $ids = array_filter($ids, function($id) use ($agenteId) {
        return $id !== $agenteId;
    });
    
    return agentesArrayToString($ids);
}
