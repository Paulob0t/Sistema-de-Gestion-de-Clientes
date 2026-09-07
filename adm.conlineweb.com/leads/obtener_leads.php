<?php
require_once __DIR__ . '/../auth_middleware.php';
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);
include __DIR__ . '/../conn.php';

$uid = $_SESSION['uid'] ?? null;
$tipo = intval($_SESSION['tipo'] ?? 0);

// Auto-migración: crear columnas whatsapp si no existen
$chkCol = $conn->query("SHOW COLUMNS FROM leads LIKE 'whatsapp_enviado'");
if ($chkCol && $chkCol->num_rows === 0) {
    $conn->query("ALTER TABLE leads ADD COLUMN whatsapp_enviado TINYINT(1) NOT NULL DEFAULT 0");
    $conn->query("ALTER TABLE leads ADD COLUMN whatsapp_enviado_fecha DATETIME NULL DEFAULT NULL");
}

try {
    $data = [];
    
    // ========== 1. OBTENER LEADS MANUALES ==========
    if ($tipo === 5) {
        $query = "SELECT id, nombre, correo, telefono, requerimiento, empresa, pais, estatus, notas, fecha_registro, usuario_registro,
                         COALESCE(whatsapp_enviado, 0) AS whatsapp_enviado, whatsapp_enviado_fecha
                  FROM leads 
                  WHERE eliminado = 0 AND usuario_registro = ?
                  ORDER BY fecha_registro DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $query = "SELECT id, nombre, correo, telefono, requerimiento, empresa, pais, estatus, notas, fecha_registro, usuario_registro,
                         COALESCE(whatsapp_enviado, 0) AS whatsapp_enviado, whatsapp_enviado_fecha
                  FROM leads 
                  WHERE eliminado = 0
                  ORDER BY fecha_registro DESC";
        $result = $conn->query($query);
    }
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $notas = json_decode($row['notas'], true) ?: [];
            $ultima_nota = '';
            $total_notas = count($notas);
            
            if ($total_notas > 0) {
                $ultima_nota = $notas[$total_notas - 1]['nota'];
            }
            
            $data[] = [
                'id' => $row['id'],
                'id_real' => $row['id'],
                'origen' => 'Manual',
                'nombre' => htmlspecialchars($row['nombre'] ?? '', ENT_QUOTES, 'UTF-8'),
                'correo' => htmlspecialchars($row['correo'] ?? '', ENT_QUOTES, 'UTF-8'),
                'telefono' => htmlspecialchars($row['telefono'] ?? '', ENT_QUOTES, 'UTF-8'),
                'requerimiento' => htmlspecialchars($row['requerimiento'] ?? '', ENT_QUOTES, 'UTF-8'),
                'empresa' => htmlspecialchars($row['empresa'] ?? '', ENT_QUOTES, 'UTF-8'),
                'pais' => htmlspecialchars($row['pais'] ?? 'México', ENT_QUOTES, 'UTF-8'),
                'estatus' => $row['estatus'],
                'ultima_nota' => htmlspecialchars($ultima_nota, ENT_QUOTES, 'UTF-8'),
                'total_notas' => $total_notas,
                'fecha_registro' => $row['fecha_registro'],
                'notas_json' => $row['notas'],
                'whatsapp_enviado' => (int)($row['whatsapp_enviado'] ?? 0),
                'whatsapp_enviado_fecha' => $row['whatsapp_enviado_fecha'] ?? null
            ];
        }
    }
    
    // ========== 2. OBTENER LEADS DEL EXCEL (todas las tablas registradas) ==========
    $queryTablas = "SELECT nombre FROM tablas_leads ORDER BY nombre";
    $resultTablas = $conn->query($queryTablas);
    
    if ($resultTablas && $resultTablas->num_rows > 0) {
        while ($rowTabla = $resultTablas->fetch_assoc()) {
            $tableName = $rowTabla['nombre'];
            
            // Verificar que la tabla exista
            $checkTable = $conn->query("SHOW TABLES LIKE '$tableName'");
            if ($checkTable->num_rows == 0) {
                continue;
            }
            
            // Obtener columnas de la tabla
            $columnsResult = $conn->query("SHOW COLUMNS FROM `$tableName`");
            $columns = [];
            while ($col = $columnsResult->fetch_assoc()) {
                $columns[] = $col['Field'];
            }
            
            // Mapear columnas del Excel a campos esperados
            $queryExcel = "SELECT * FROM `$tableName` ORDER BY fecha_importacion DESC";
            $resultExcel = $conn->query($queryExcel);
            
            if ($resultExcel && $resultExcel->num_rows > 0) {
                while ($row = $resultExcel->fetch_assoc()) {
                    // Mapear campos del Excel a formato estándar
                    $nombre = $row['nombre_completo'] ?? $row['full_name'] ?? '';
                    $correo = $row['email'] ?? '';
                    $telefono = $row['n_mero_de_tel_fono'] ?? $row['numero_de_telefono'] ?? $row['telefono'] ?? '';
                    $empresa = $row['empresa'] ?? $row['company_name'] ?? '';
                    $pais = $row['pais'] ?? $row['country'] ?? '';
                    $estatus = $row['lead_status'] ?? $row['estatus'] ?? 'Activo';
                    $fecha = $row['created_time'] ?? $row['fecha_importacion'] ?? date('Y-m-d H:i:s');
                    
                    // Requerimiento puede venir de varios campos
                    $requerimiento = '';
                    if (isset($row['form_name'])) {
                        $requerimiento .= 'Formulario: ' . $row['form_name'] . ' ';
                    }
                    if (isset($row['campaign_name'])) {
                        $requerimiento .= 'Campaña: ' . $row['campaign_name'];
                    }
                    
                    $data[] = [
                        'id' => $row['id'] . '.1',
                        'id_real' => $row['id'],
                        'origen' => 'Excel',
                        'tabla_origen' => $tableName,
                        'nombre' => htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'),
                        'correo' => htmlspecialchars($correo, ENT_QUOTES, 'UTF-8'),
                        'telefono' => htmlspecialchars($telefono, ENT_QUOTES, 'UTF-8'),
                        'requerimiento' => htmlspecialchars($requerimiento, ENT_QUOTES, 'UTF-8'),
                        'empresa' => htmlspecialchars($empresa, ENT_QUOTES, 'UTF-8'),
                        'pais' => htmlspecialchars($pais, ENT_QUOTES, 'UTF-8'),
                        'estatus' => $estatus,
                        'ultima_nota' => '',
                        'total_notas' => 0,
                        'fecha_registro' => $fecha,
                        'notas_json' => '[]'
                    ];
                }
            }
        }
    }
    
    // Ordenar todos los datos por fecha
    usort($data, function($a, $b) {
        return strtotime($b['fecha_registro']) - strtotime($a['fecha_registro']);
    });
    
    echo json_encode(['data' => $data]);
    
} catch (Exception $e) {
    echo json_encode(['data' => [], 'error' => $e->getMessage()]);
}

$conn->close();
?>
