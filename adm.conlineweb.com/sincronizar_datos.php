<?php
// --------------------------
// Mostrar errores para depuración
// --------------------------
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --------------------------
// 1️⃣ Cargar autoload de Google API
// --------------------------
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    die("❌ autoload.php no encontrado en $autoloadPath");
}
require $autoloadPath;

// --------------------------
// 2️⃣ Conectar a Google Sheets
// --------------------------
$client = new \Google_Client();
$client->setApplicationName('bd excel');
$client->setScopes([\Google_Service_Sheets::SPREADSHEETS_READONLY]);
$client->setAuthConfig(__DIR__ . '/../keys/service_key.json');

$service = new \Google_Service_Sheets($client);

$spreadsheetId = '1f8MMTyb2HLAX9R5f7wNUlcZSf_NTW9pq7vA__bjzIPI';

// Obtener información del spreadsheet y listar todas las hojas disponibles
try {
    $spreadsheet = $service->spreadsheets->get($spreadsheetId);
    $sheets = $spreadsheet->getSheets();
    
    echo "<div style='background: #fff3cd; padding: 15px; margin: 20px; border-left: 4px solid #ffc107; border-radius: 5px;'>";
    echo "<strong>📋 Hojas disponibles en el spreadsheet:</strong><br>";
    
    $sheetNames = [];
    foreach ($sheets as $sheet) {
        $sheetTitle = $sheet->getProperties()->getTitle();

        // Ignorar la hoja llamada 'prueba' (case-insensitive)
        if (strcasecmp(trim($sheetTitle), 'prueba') === 0) {
            echo "- " . htmlspecialchars($sheetTitle) . " (ignorada)<br>";
            continue;
        }

        $sheetNames[] = $sheetTitle;
        echo "- " . htmlspecialchars($sheetTitle) . "<br>";
    }
    echo "</div>";
    
    // Si quieres procesar solo hojas específicas, descomenta y ajusta esta línea:
    // $sheetNames = ['FORM META SETTO', 'FORM SETTO ORIGINAL'];
    
} catch (\Exception $e) {
    die("Error al acceder al Sheet: " . $e->getMessage());
}

// --------------------------
// 3️⃣ Conectar a MySQL
// --------------------------
include 'conn.php';

// Establecer charset y collation para evitar conflictos
$conn->set_charset("utf8mb4");
$conn->query("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci");

// Crear tabla de control para guardar nombres de tablas generadas
$sqlCreateControl = "CREATE TABLE IF NOT EXISTS `tablas_leads` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL UNIQUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

if (!$conn->query($sqlCreateControl)) {
    die("❌ Error creando tabla de control 'tablas_leads': " . $conn->error);
}

// --------------------------
// FUNCIÓN AUXILIAR: Convertir DD/MM/YYYY a ISO 8601
// --------------------------
function convertToISO8601($dateStr) {
    // Si está vacío, devolver vacío
    if (empty(trim($dateStr))) {
        return '';
    }
    
    // Intentar parsear el formato DD/MM/YYYY
    $parts = explode('/', $dateStr);
    
    if (count($parts) === 3) {
        $day = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
        $month = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
        $year = $parts[2];
        
        // Validar que sea una fecha válida
        if (checkdate((int)$month, (int)$day, (int)$year)) {
            // Formato ISO 8601 con hora 9 AM y zona horaria -05:00 (CDT/EST)
            return "$year-$month-{$day}T09:00:00-05:00";
        }
    }
    
    // Si no se pudo convertir, devolver el valor original
    return $dateStr;
}

// --------------------------
// 4️⃣ Procesar cada hoja
// --------------------------
$allResults = []; // Almacenar resultados de cada hoja

foreach ($sheetNames as $sheetName) {
    echo "<h2>Procesando hoja: $sheetName</h2>";

    // Saltar si el nombre de la hoja es 'prueba' por seguridad (no debería ocurrir
    // porque ya la filtramos más arriba, pero lo añadimos como medida defensiva).
    if (strcasecmp(trim($sheetName), 'pruebas') === 0) {
        echo "<p>⚠️ Hoja 'prueba' encontrada — ignorada.</p>";
        continue;
    }

    // Obtener datos de la hoja
    try {
        // Escapar el nombre de la hoja con comillas simples para evitar errores de parsing
        $range = "'" . str_replace("'", "''", $sheetName) . "'!A1:ZZ1000";
        $response = $service->spreadsheets_values->get($spreadsheetId, $range);
        $values = $response->getValues();

        if (empty($values)) {
            echo "<p>⚠️ No hay datos en la hoja '$sheetName'</p>";
            continue;
        }
    } catch (\Exception $e) {
        echo "<p>❌ Error al acceder a la hoja '$sheetName': " . $e->getMessage() . "</p>";
        continue;
    }

    // Buscar la primera fila no vacía para usarla como encabezados
    $headerRowIndex = 0;
    $headers = [];

    foreach ($values as $index => $row) {
        // Verificar si la fila tiene al menos una celda no vacía
        $hasContent = false;
        foreach ($row as $cell) {
            if (!empty(trim($cell ?? ''))) {
                $hasContent = true;
                break;
            }
        }

        if ($hasContent) {
            $headers = $row;
            $headerRowIndex = $index;
            break;
        }
    }

    // Si no se encontraron encabezados válidos
    if (empty($headers)) {
        echo "<p>⚠️ No se encontraron encabezados válidos en la hoja '$sheetName'</p>";
        continue;
    }

    echo "<p>ℹ️ Encabezados encontrados en la fila " . ($headerRowIndex + 1) . "</p>";

    // Limpiar el nombre de la hoja para usarlo como nombre de tabla
    $tableName = preg_replace('/[^a-zA-Z0-9_]/', '_', str_replace(' ', '_', $sheetName));
    $tableName = strtolower($tableName); // Convertir a minúsculas

    // Verificar si la tabla existe
    $tableExists = $conn->query("SHOW TABLES LIKE '$tableName'")->num_rows > 0;
    
    // Si la tabla existe, verificar si tiene el índice único antiguo
    if ($tableExists) {
        // Verificar índice antiguo de email
        $checkIndex = $conn->query("SHOW INDEX FROM `$tableName` WHERE Key_name = 'idx_email' AND Non_unique = 0");
        if ($checkIndex && $checkIndex->num_rows > 0) {
            $conn->query("ALTER TABLE `$tableName` DROP INDEX `idx_email`");
            echo "<p>ℹ️ Índice único antiguo de email eliminado de '$tableName'</p>";
        }
        // Verificar índice antiguo de teléfono
        $checkIndex = $conn->query("SHOW INDEX FROM `$tableName` WHERE Key_name = 'idx_telefono' AND Non_unique = 0");
        if ($checkIndex && $checkIndex->num_rows > 0) {
            $conn->query("ALTER TABLE `$tableName` DROP INDEX `idx_telefono`");
            echo "<p>ℹ️ Índice único antiguo de teléfono eliminado de '$tableName'</p>";
        }
    }

    // Limpiar encabezados para MySQL y crear columnas
    $columnsSQL = [];
    $cleanHeaders = [];
    $columnIndexMap = []; // Mapeo de columna limpia a índice original
    $hasIdColumn = false;
    $idColumnIndex = -1;
    $hasPhoneColumn = false;
    $phoneColumnIndex = -1;
    $hasEmailColumn = false;
    $emailColumnIndex = -1;
    $hasCreatedTimeColumn = false;
    $createdTimeColumnIndex = -1;

    foreach ($headers as $index => $h) {
        // Limpiar el nombre de la columna
        $col = trim($h ?? '');

        // Si la columna está vacía, saltarla
        if (empty($col)) {
            continue;
        }

        $col = preg_replace('/[^a-zA-Z0-9_]/', '_', str_replace(' ', '_', $col));
        $col = strtolower($col);

        // Si después de limpiar está vacío o solo contiene guiones bajos, saltarla
        if (empty($col) || preg_match('/^_+$/', $col)) {
            continue;
        }

        // Si la columna es 'id', renombrarla a 'id_excel'
        if ($col === 'id') {
            $col = 'id_excel';
            $hasIdColumn = true;
            $idColumnIndex = $index;
        }

        // Detectar si existe columna número_de_teléfono
        if ($col === 'n_mero_de_tel_fono' || $col === 'numero_de_telefono' || $col === 'telefono' || $col === 'phone') {
            $hasPhoneColumn = true;
            $phoneColumnIndex = $index;
            $col = 'n_mero_de_tel_fono'; // Normalizar el nombre
        }

        // Detectar si existe columna email
        if ($col === 'email' || $col === 'correo' || $col === 'e_mail') {
            $hasEmailColumn = true;
            $emailColumnIndex = $index;
            $col = 'email'; // Normalizar el nombre
        }

        // Detectar si existe columna created_time
        if ($col === 'created_time') {
            $hasCreatedTimeColumn = true;
            $createdTimeColumnIndex = $index;
        }

        $cleanHeaders[] = $col;
        $columnsSQL[] = "`$col` VARCHAR(255)";
        $columnIndexMap[] = $index; // Guardar el índice original
    }

    // Validar que haya al menos una columna válida
    if (empty($columnsSQL)) {
        echo "<p>⚠️ No hay columnas válidas en la hoja '$sheetName'</p>";
        continue;
    }

    $columnsSQLString = implode(",", $columnsSQL);

    // Crear tabla si no existe (con el nombre de la hoja)
    // Siempre crear un AUTO_INCREMENT para evitar problemas con duplicados
    $sqlCreate = "CREATE TABLE IF NOT EXISTS `$tableName` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        $columnsSQLString,
        fecha_importacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ";
    
    // Si existe columna número_de_teléfono, crear índice (no único para permitir teléfonos vacíos duplicados)
    if ($hasPhoneColumn) {
        $sqlCreate .= ",
        KEY `idx_telefono` (`n_mero_de_tel_fono`)";
    }

    $sqlCreate .= "
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    if (!$conn->query($sqlCreate)) {
        // Si falla porque la tabla ya existe, continuar normalmente
        if ($conn->errno != 1050) { // 1050 = Table already exists
            echo "<p>❌ Error creando tabla '$tableName': " . $conn->error . "</p>";
            continue;
        } else {
            echo "<p>ℹ️ Tabla '$tableName' ya existe, se agregarán nuevos registros</p>";
            $tableExists = true; // Actualizar porque la tabla ya existe
        }
    } else {
        // La tabla se creó exitosamente ahora
        $tableExists = true;
        echo "<p>✅ Tabla '$tableName' creada exitosamente</p>";
    }

    // Registrar el nombre de la tabla en tablas_leads
    $stmtControl = $conn->prepare("INSERT IGNORE INTO `tablas_leads` (nombre) VALUES (?)");
    $stmtControl->bind_param('s', $tableName);
    $stmtControl->execute();
    $stmtControl->close();

    // Insertar datos en la tabla
    $columnsNames = implode(',', array_map(function ($col) {
        return "`$col`";
    }, $cleanHeaders));

    $placeholders = implode(',', array_fill(0, count($cleanHeaders), '?'));
    $sqlInsert = "INSERT INTO `$tableName` ($columnsNames) VALUES ($placeholders)";

    $stmt = $conn->prepare($sqlInsert);
    if (!$stmt) {
        echo "<p>❌ Error en prepare para tabla '$tableName': " . $conn->error . "</p>";
        continue;
    }

    $insertedRows = 0;
    $skippedRows = 0;
    $duplicatePhones = 0;
    $testPhones = 0; // Contador para teléfonos de prueba
    $datesConverted = 0; // Contador de fechas convertidas

    foreach ($values as $index => $row) {
        // Saltar todas las filas hasta e incluyendo la fila de encabezados
        if ($index <= $headerRowIndex)
            continue;

        // Verificar si la fila es un título repetido (comparar con el encabezado original)
        $isHeaderRow = true;
        for ($i = 0; $i < count($headers); $i++) {
            $cellValue = isset($row[$i]) ? trim($row[$i]) : '';
            $headerValue = trim($headers[$i]);

            // Si al menos una celda no coincide con el encabezado, no es fila de título
            if (strcasecmp($cellValue, $headerValue) !== 0) {
                $isHeaderRow = false;
                break;
            }
        }

        // Si es una fila de título repetido, saltarla
        if ($isHeaderRow) {
            $skippedRows++;
            continue;
        }

        // Verificar si la fila está completamente vacía
        $isEmpty = true;
        foreach ($row as $cell) {
            if (!empty(trim($cell ?? ''))) {
                $isEmpty = false;
                break;
            }
        }

        // Si la fila está vacía, saltarla
        if ($isEmpty) {
            $skippedRows++;
            continue;
        }

        // ========== PASO 1: OBTENER VALORES DE TELÉFONO Y EMAIL ==========
        $phoneValue = '';
        $emailValue = '';
        
        if ($hasPhoneColumn && $phoneColumnIndex >= 0) {
            $phoneValue = isset($row[$phoneColumnIndex]) ? trim($row[$phoneColumnIndex]) : '';
        }
        if ($hasEmailColumn && $emailColumnIndex >= 0) {
            $emailValue = isset($row[$emailColumnIndex]) ? trim($row[$emailColumnIndex]) : '';
        }

        // ========== PASO 2: VERIFICAR TELÉFONOS DE PRUEBA ==========
        // Solo verificar si HAY columna de teléfono Y tiene valor
        if ($hasPhoneColumn && !empty($phoneValue) && preg_match('/^0+$/', $phoneValue)) {
            $testPhones++;
            continue; // Saltar registros con teléfonos de prueba (solo ceros)
        }

        // ========== PASO 3: VERIFICACIÓN DE DUPLICADOS GLOBAL ==========
        $isDuplicate = false;

        // 3.1 - Verificar en la tabla actual si existe teléfono duplicado
        if ($tableExists && !empty($phoneValue) && $hasPhoneColumn) {
            $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM `$tableName` WHERE `n_mero_de_tel_fono` = ?");
            if ($checkStmt) {
                $checkStmt->bind_param('s', $phoneValue);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                $count = $result->fetch_assoc()['count'];
                $checkStmt->close();
                
                if ($count > 0) {
                    $duplicatePhones++;
                    continue; // Ya existe en esta tabla, saltar inserción
                }
            }
        }

        // 3.2 - Verificar por email en la tabla actual (solo si tiene email Y teléfono vacío)
        if ($tableExists && !empty($emailValue) && $hasEmailColumn && empty($phoneValue)) {
            $checkEmailStmt = $conn->prepare("SELECT COUNT(*) as count FROM `$tableName` WHERE `email` = ?");
            if ($checkEmailStmt) {
                $checkEmailStmt->bind_param('s', $emailValue);
                $checkEmailStmt->execute();
                $resultEmail = $checkEmailStmt->get_result();
                $countEmail = $resultEmail->fetch_assoc()['count'];
                $checkEmailStmt->close();
                
                if ($countEmail > 0) {
                    $duplicatePhones++; // Reutilizamos el contador
                    continue; // Ya existe en esta tabla, saltar inserción
                }
            }
        }
        
        // 3.3 - Si NO tiene teléfono ni email, verificar por todos los campos clave
        if ($tableExists && empty($phoneValue) && empty($emailValue)) {
            // Construir verificación por múltiples campos para evitar duplicados sin teléfono/email
            $whereConditions = [];
            $checkParams = [];
            $checkTypes = '';
            
            foreach ($columnIndexMap as $mapIndex => $origIndex) {
                $columnName = $cleanHeaders[$mapIndex];
                $cellValue = isset($row[$origIndex]) ? trim($row[$origIndex]) : '';
                
                // Solo agregar columnas con valor
                if (!empty($cellValue)) {
                    $whereConditions[] = "`$columnName` = ?";
                    $checkParams[] = $cellValue;
                    $checkTypes .= 's';
                }
            }
            
            // Si hay al menos 2 campos para comparar, verificar duplicado
            if (count($whereConditions) >= 2) {
                $whereSQL = implode(' AND ', $whereConditions);
                $checkAllStmt = $conn->prepare("SELECT COUNT(*) as count FROM `$tableName` WHERE $whereSQL");
                if ($checkAllStmt) {
                    $checkAllStmt->bind_param($checkTypes, ...$checkParams);
                    $checkAllStmt->execute();
                    $resultAll = $checkAllStmt->get_result();
                    $countAll = $resultAll->fetch_assoc()['count'];
                    $checkAllStmt->close();
                    
                    if ($countAll > 0) {
                        $duplicatePhones++; // Reutilizamos el contador
                        continue; // Ya existe registro idéntico, saltar inserción
                    }
                }
            }
        }

        // Completar celdas vacías y reemplazar null por ''
        // Solo tomar los valores de las columnas válidas usando el mapeo
        $validRow = [];
        foreach ($columnIndexMap as $mapIndex => $origIndex) {
            $cellValue = isset($row[$origIndex]) ? $row[$origIndex] : '';
            
            // Si es la hoja "manual" y es la columna created_time, convertir la fecha
            if ($tableName === 'manual' && $hasCreatedTimeColumn && $origIndex === $createdTimeColumnIndex) {
                $convertedDate = convertToISO8601($cellValue);
                if ($convertedDate !== $cellValue && !empty($cellValue)) {
                    $datesConverted++;
                }
                $validRow[] = $convertedDate;
            } else {
                $validRow[] = $cellValue;
            }
        }

        $validRow = array_map(function ($v) {
            // Convertir a string y limpiar caracteres problemáticos
            $value = $v ?? '';
            return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }, $validRow);

        $types = str_repeat('s', count($validRow));
        $stmt->bind_param($types, ...$validRow);

        if ($stmt->execute()) {
            $insertedRows++;
        } else {
            echo "⚠️ Error insertando fila $index en '$tableName': " . $stmt->error . "<br>";
        }
    }

    $stmt->close();

    // Guardar resultados de esta hoja
    $allResults[] = [
        'sheetName' => $sheetName,
        'tableName' => $tableName,
        'headers' => $headers,
        'values' => $values,
        'headerRowIndex' => $headerRowIndex,
        'insertedRows' => $insertedRows,
        'duplicatePhones' => $duplicatePhones,
        'testPhones' => $testPhones,
        'skippedRows' => $skippedRows,
        'datesConverted' => $datesConverted,
        'totalRows' => count($values) - $headerRowIndex - 1 // Total desde después del encabezado
    ];
}

// --------------------------
// 5️⃣ Mostrar datos en tabla HTML
// --------------------------
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importación de Leads - Múltiples Hojas</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }

        .container {
            max-width: 100%;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
        }

        h2 {
            color: #4CAF50;
            margin-top: 30px;
            margin-bottom: 15px;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
        }

        .info {
            background: #e8f5e9;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #4caf50;
        }

        .summary {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #2196F3;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 14px;
        }

        th {
            background-color: #4CAF50;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: bold;
            position: sticky;
            top: 0;
        }

        td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }

        tr:hover {
            background-color: #f5f5f5;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .table-wrapper {
            overflow-x: auto;
            max-height: 600px;
            overflow-y: auto;
        }

        .sheet-section {
            margin-bottom: 40px;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            background: #fafafa;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>📊 Importación de Leads desde Google Sheets</h1>

        <div class="summary">
            <strong>📋 Resumen General</strong><br>
            <strong>Total de hojas procesadas:</strong> <?php echo count($allResults); ?><br>
            <?php
            $totalInserted = 0;
            $totalDuplicates = 0;
            $totalSkipped = 0;
            $totalDatesConverted = 0;
            foreach ($allResults as $result) {
                $totalInserted += $result['insertedRows'];
                $totalDuplicates += $result['duplicatePhones'];
                $totalSkipped += $result['skippedRows'];
                $totalDatesConverted += isset($result['datesConverted']) ? $result['datesConverted'] : 0;
            }
            ?>
            <strong>Total registros insertados:</strong> <?php echo $totalInserted; ?><br>
            <strong>Total teléfonos duplicados:</strong> <?php echo $totalDuplicates; ?><br>
            <strong>Total filas omitidas:</strong> <?php echo $totalSkipped; ?><br>
            <?php if ($totalDatesConverted > 0): ?>
            <strong>Total fechas convertidas a ISO 8601:</strong> <?php echo $totalDatesConverted; ?>
            <?php endif; ?>
        </div>

        <?php foreach ($allResults as $result): ?>
            <div class="sheet-section">
                <h2>📄 Hoja: <?php echo htmlspecialchars($result['sheetName']); ?></h2>

                <div class="info">
                    <strong>✅ Importación exitosa</strong><br>
                    <strong>Tabla MySQL:</strong> <?php echo htmlspecialchars($result['tableName']); ?><br>
                    <strong>Registros insertados:</strong> <?php echo $result['insertedRows']; ?><br>
                    <strong>Teléfonos duplicados:</strong> <?php echo $result['duplicatePhones']; ?> (omitidos)<br>
                    <strong>Filas omitidas:</strong> <?php echo $result['skippedRows']; ?> (títulos repetidos o vacías)<br>
                    <?php if (isset($result['datesConverted']) && $result['datesConverted'] > 0): ?>
                    <strong>Fechas convertidas a ISO 8601:</strong> <?php echo $result['datesConverted']; ?><br>
                    <?php endif; ?>
                    <strong>Total filas procesadas:</strong> <?php echo $result['totalRows']; ?>
                </div>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <?php foreach ($result['headers'] as $header): ?>
                                    <th><?php echo htmlspecialchars($header); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($result['values'] as $index => $row): ?>
                                <?php
                                // Saltar todas las filas hasta e incluyendo la fila de encabezados
                                if ($index <= $result['headerRowIndex'])
                                    continue;

                                // Verificar si es fila de título repetido
                                $isHeaderRow = true;
                                for ($i = 0; $i < count($result['headers']); $i++) {
                                    $cellValue = isset($row[$i]) ? trim($row[$i]) : '';
                                    $headerValue = trim($result['headers'][$i]);
                                    if (strcasecmp($cellValue, $headerValue) !== 0) {
                                        $isHeaderRow = false;
                                        break;
                                    }
                                }
                                if ($isHeaderRow)
                                    continue; // Saltar títulos repetidos
                        
                                // Verificar si la fila está vacía
                                $isEmpty = true;
                                foreach ($row as $cell) {
                                    if (!empty(trim($cell ?? ''))) {
                                        $isEmpty = false;
                                        break;
                                    }
                                }
                                if ($isEmpty)
                                    continue; // Saltar filas vacías
                                ?>
                                <tr>
                                    <?php
                                    // Completar celdas vacías para la vista
                                    $row = array_pad($row, count($result['headers']), '');
                                    foreach ($row as $cell):
                                        ?>
                                        <td><?php echo htmlspecialchars($cell ?? ''); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</body>

</html>
<?php
$conn->close();
?>