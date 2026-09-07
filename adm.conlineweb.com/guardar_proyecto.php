<?php
require_once __DIR__ . '/auth_middleware.php';
// Configuración de errores y logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');

function sendJson($data) {
    if (ob_get_length()) { ob_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    require_once __DIR__ . '/conn.php';
    require_once __DIR__ . '/includes/helpers_clientes.php';
    require_once __DIR__ . '/includes/helpers_proyectos_tipo.php';
    require_once __DIR__ . '/includes/helpers_proyectos_eeat.php';
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception('Error de conexión a la base de datos');
    }
    if (!adm_proyectos_ensure_tipo_proyecto($conn)) {
        throw new Exception('No se pudo preparar el campo tipo_proyecto');
    }
    if (!adm_proyectos_ensure_eeat_columns($conn)) {
        throw new Exception('No se pudieron preparar los campos de caso / E-E-A-T');
    }

    $id_proyecto = isset($_POST['id_proyecto']) && $_POST['id_proyecto'] !== '' ? filter_var($_POST['id_proyecto'], FILTER_VALIDATE_INT) : null;
    $cliente_id = isset($_POST['cliente']) ? filter_var($_POST['cliente'], FILTER_VALIDATE_INT) : false;
    $nombre = isset($_POST['nombre_proyecto']) ? trim($_POST['nombre_proyecto']) : '';
    $tipo_proyecto = isset($_POST['tipo_proyecto']) ? adm_proyecto_tipo_normalize($_POST['tipo_proyecto']) : 0;
    $descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : null;
    $descripcion_tecnica = isset($_POST['descripcion_tecnica']) ? trim($_POST['descripcion_tecnica']) : null;
    if ($descripcion === '') {
        $descripcion = null;
    }
    if ($descripcion_tecnica === '') {
        $descripcion_tecnica = null;
    }
    $url_proyecto = isset($_POST['url_proyecto']) && $_POST['url_proyecto'] !== '' ? trim($_POST['url_proyecto']) : null;
    $mostrar = isset($_POST['mostrar']) ? 1 : 0;
    $posicion = isset($_POST['posicion']) ? intval($_POST['posicion']) : 0;
    $eeat = adm_proyecto_eeat_from_post($_POST);

    $tags = [];
    $tag_count = isset($_POST['tag_count']) ? intval($_POST['tag_count']) : 0;
    for ($i = 0; $i < $tag_count; $i++) {
        $g = isset($_POST['tag_'.$i.'_group']) ? intval($_POST['tag_'.$i.'_group']) : null;
        $ix = isset($_POST['tag_'.$i.'_index']) ? intval($_POST['tag_'.$i.'_index']) : null;
        $tipo = isset($_POST['tag_'.$i.'_tipo']) ? $_POST['tag_'.$i.'_tipo'] : null;
        if ($g !== null && $ix !== null && ($tipo === 'categoria' || $tipo === 'tecnologia')) {
            $tags[] = ['group' => $g, 'index' => $ix, 'tipo' => $tipo];
        }
    }

    if ($cliente_id === false || $cliente_id <= 0) {
        throw new Exception('Cliente inválido');
    }
    if (!solicitudes_cliente_es_activo($conn, (int) $cliente_id)) {
        throw new Exception('El cliente seleccionado no está activo o no es válido');
    }
    if ($nombre === '') {
        throw new Exception('El nombre del proyecto es obligatorio');
    }
    if (mb_strlen($nombre) > 150) {
        throw new Exception('El nombre del proyecto excede 150 caracteres');
    }

    // Validar posición única (si es diferente de 0)
    if ($posicion > 0) {
        if ($id_proyecto) {
            $stmt = $conn->prepare('SELECT id_proyecto FROM proyectos WHERE posicion = ? AND id_proyecto != ?');
            if (!$stmt) { throw new Exception('Error al validar posición'); }
            $stmt->bind_param('ii', $posicion, $id_proyecto);
        } else {
            $stmt = $conn->prepare('SELECT id_proyecto FROM proyectos WHERE posicion = ?');
            if (!$stmt) { throw new Exception('Error al validar posición'); }
            $stmt->bind_param('i', $posicion);
        }
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->close();
            throw new Exception('La posición ' . $posicion . ' ya está asignada a otro proyecto. Por favor, elige un número diferente.');
        }
        $stmt->close();
    }

    $hasDescripcionTecnica = false;
    $colCheck = $conn->query("SHOW COLUMNS FROM proyectos LIKE 'descripcion_tecnica'");
    if ($colCheck && $colCheck->num_rows > 0) {
        $hasDescripcionTecnica = true;
    }

    $guardarTagsProyecto = function (int $proyectoId, array $tagsLista) use ($conn): void {
        $del = $conn->prepare('DELETE FROM proyectos_tags WHERE id_proyecto = ?');
        if (!$del) {
            throw new Exception('Error al preparar limpieza de tags');
        }
        $del->bind_param('i', $proyectoId);
        $del->execute();
        $del->close();

        if (empty($tagsLista)) {
            return;
        }

        $ins = $conn->prepare('INSERT INTO proyectos_tags (id_proyecto, tag_group_id, tag_index, tag_text, tipo) VALUES (?, ?, ?, ?, ?)');
        if (!$ins) {
            throw new Exception('Error al preparar inserción de tags');
        }

        foreach ($tagsLista as $t) {
            $grp = (int) $t['group'];
            $ix = (int) $t['index'];
            $tipo = $t['tipo'];
            $tag_text = '';
            $r2 = $conn->query('SELECT tags FROM tags WHERE id = ' . $grp . ' LIMIT 1');
            if ($r2 && $rowt = $r2->fetch_assoc()) {
                $arr = json_decode($rowt['tags'], true);
                if (isset($arr[$ix])) {
                    $tag_text = (string) $arr[$ix];
                }
            }
            $ins->bind_param('iiiss', $proyectoId, $grp, $ix, $tag_text, $tipo);
            if (!$ins->execute()) {
                $ins->close();
                throw new Exception('No se pudo guardar un tag del proyecto');
            }
        }
        $ins->close();
    };

    $industria = $eeat['industria'];
    $ciudad = $eeat['ciudad'];
    $alias_publico = $eeat['alias_publico'];
    $problema = $eeat['problema'];
    $solucion = $eeat['solucion'];
    $resultado = $eeat['resultado'];
    $caso_destacado = (int) $eeat['caso_destacado'];

    if ($id_proyecto) {
        if ($hasDescripcionTecnica) {
            $stmt = $conn->prepare(
                'UPDATE proyectos SET id_cliente = ?, nombre_proyecto = ?, tipo_proyecto = ?, descripcion = ?, descripcion_tecnica = ?, url = ?, mostrar = ?, posicion = ?,
                 industria = ?, ciudad = ?, alias_publico = ?, problema = ?, solucion = ?, resultado = ?, caso_destacado = ?
                 WHERE id_proyecto = ?'
            );
            if (!$stmt) { throw new Exception('Error al preparar actualización'); }
            $stmt->bind_param(
                'isisssiissssssii',
                $cliente_id,
                $nombre,
                $tipo_proyecto,
                $descripcion,
                $descripcion_tecnica,
                $url_proyecto,
                $mostrar,
                $posicion,
                $industria,
                $ciudad,
                $alias_publico,
                $problema,
                $solucion,
                $resultado,
                $caso_destacado,
                $id_proyecto
            );
        } else {
            $stmt = $conn->prepare(
                'UPDATE proyectos SET id_cliente = ?, nombre_proyecto = ?, tipo_proyecto = ?, descripcion = ?, url = ?, mostrar = ?, posicion = ?,
                 industria = ?, ciudad = ?, alias_publico = ?, problema = ?, solucion = ?, resultado = ?, caso_destacado = ?
                 WHERE id_proyecto = ?'
            );
            if (!$stmt) { throw new Exception('Error al preparar actualización'); }
            $stmt->bind_param(
                'isissiissssssii',
                $cliente_id,
                $nombre,
                $tipo_proyecto,
                $descripcion,
                $url_proyecto,
                $mostrar,
                $posicion,
                $industria,
                $ciudad,
                $alias_publico,
                $problema,
                $solucion,
                $resultado,
                $caso_destacado,
                $id_proyecto
            );
        }
        if (!$stmt->execute()) {
            throw new Exception('No se pudo actualizar el proyecto');
        }
        $stmt->close();

        $guardarTagsProyecto((int) $id_proyecto, $tags);

        sendJson([
            'success' => true,
            'message' => 'Proyecto actualizado correctamente.',
            'project_id' => $id_proyecto,
            'tipo_proyecto' => $tipo_proyecto,
            'redirect' => 'detalle_cliente.php?id=' . $cliente_id
        ]);
    } else {
        if ($hasDescripcionTecnica) {
            $stmt = $conn->prepare(
                'INSERT INTO proyectos (id_cliente, nombre_proyecto, tipo_proyecto, descripcion, descripcion_tecnica, url, mostrar, posicion, industria, ciudad, alias_publico, problema, solucion, resultado, caso_destacado)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            if (!$stmt) { throw new Exception('Error al preparar inserción'); }
            $stmt->bind_param(
                'isisssiissssssi',
                $cliente_id,
                $nombre,
                $tipo_proyecto,
                $descripcion,
                $descripcion_tecnica,
                $url_proyecto,
                $mostrar,
                $posicion,
                $industria,
                $ciudad,
                $alias_publico,
                $problema,
                $solucion,
                $resultado,
                $caso_destacado
            );
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO proyectos (id_cliente, nombre_proyecto, tipo_proyecto, descripcion, url, mostrar, posicion, industria, ciudad, alias_publico, problema, solucion, resultado, caso_destacado)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            if (!$stmt) { throw new Exception('Error al preparar inserción'); }
            $stmt->bind_param(
                'isissiissssssi',
                $cliente_id,
                $nombre,
                $tipo_proyecto,
                $descripcion,
                $url_proyecto,
                $mostrar,
                $posicion,
                $industria,
                $ciudad,
                $alias_publico,
                $problema,
                $solucion,
                $resultado,
                $caso_destacado
            );
        }
        if (!$stmt->execute()) {
            throw new Exception('No se pudo guardar el proyecto');
        }
        $nuevo_id = (int) $stmt->insert_id;
        $stmt->close();

        $guardarTagsProyecto($nuevo_id, $tags);

        sendJson([
            'success' => true,
            'message' => 'Proyecto guardado correctamente.',
            'project_id' => $nuevo_id,
            'tipo_proyecto' => $tipo_proyecto,
            'redirect' => 'detalle_cliente.php?id=' . $cliente_id
        ]);
    }
} catch (Exception $e) {
    sendJson([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
