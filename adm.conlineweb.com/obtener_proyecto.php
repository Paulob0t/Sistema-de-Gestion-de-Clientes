<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/includes/helpers_proyectos_tipo.php';
require_once __DIR__ . '/includes/helpers_proyectos_eeat.php';

function respond($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

try {
  if (!isset($_GET['id_proyecto'])) {
    throw new Exception('Falta id_proyecto');
  }
  $id = filter_var($_GET['id_proyecto'], FILTER_VALIDATE_INT);
  if ($id === false) throw new Exception('id_proyecto inválido');

  adm_proyectos_ensure_tipo_proyecto($conn);
  adm_proyectos_ensure_eeat_columns($conn);

  $hasDescripcionTecnica = false;
  $colCheck = $conn->query("SHOW COLUMNS FROM proyectos LIKE 'descripcion_tecnica'");
  if ($colCheck && $colCheck->num_rows > 0) {
    $hasDescripcionTecnica = true;
  }

  $sql = $hasDescripcionTecnica
    ? 'SELECT id_proyecto, id_cliente, nombre_proyecto, tipo_proyecto, descripcion, descripcion_tecnica, fecha_creacion, url, mostrar, posicion, industria, ciudad, alias_publico, problema, solucion, resultado, caso_destacado FROM proyectos WHERE id_proyecto = ?'
    : 'SELECT id_proyecto, id_cliente, nombre_proyecto, tipo_proyecto, descripcion, fecha_creacion, url, mostrar, posicion, industria, ciudad, alias_publico, problema, solucion, resultado, caso_destacado FROM proyectos WHERE id_proyecto = ?';

  $stmt = $conn->prepare($sql);
  if (!$stmt) throw new Exception('Error al preparar consulta');
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();
  $stmt->close();

  if (!$row) throw new Exception('Proyecto no encontrado');

  if (!$hasDescripcionTecnica) {
    $row['descripcion_tecnica'] = null;
  }
  $row['tipo_proyecto'] = adm_proyecto_tipo_normalize($row['tipo_proyecto'] ?? 0);
  $row['tipo_proyecto_label'] = adm_proyecto_tipo_label($row['tipo_proyecto']);
  $row['caso_destacado'] = (int) ($row['caso_destacado'] ?? 0);

  // Obtener tags asociados (si existe la tabla)
  $tags = [];
  $res2 = $conn->query('SELECT tag_group_id, tag_index, tag_text, tipo FROM proyectos_tags WHERE id_proyecto = '.(int)$row['id_proyecto']);
  if ($res2) {
    while ($t = $res2->fetch_assoc()) {
      $tags[] = [
        'tag_group_id' => (int)$t['tag_group_id'],
        'tag_index' => (int)$t['tag_index'],
        'tag_text' => $t['tag_text'],
        'tipo' => $t['tipo']
      ];
    }
  }

  $row['tags'] = $tags;

  respond([ 'success' => true, 'data' => $row ]);
} catch (Exception $e) {
  respond([ 'success' => false, 'message' => $e->getMessage() ]);
}
