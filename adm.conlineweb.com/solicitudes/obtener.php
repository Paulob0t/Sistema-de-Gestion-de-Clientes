<?php
// Forzar JSON y deshabilitar caché para evitar respuestas obsoletas en edición
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../auth_middleware.php';
require_once __DIR__ . '/bootstrap_conexion.php';
require_once __DIR__ . '/helpers_media.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID inválido']);
    exit;
}

$stmt = $conexion->prepare('SELECT * FROM solicitudes WHERE id = ? LIMIT 1');
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Error al consultar la solicitud']);
    exit;
}
$stmt->bind_param('i', $id);
$stmt->execute();
$q = $stmt->get_result();
if (!$q || $q->num_rows === 0) {
    $stmt->close();
    echo json_encode(['success' => false, 'error' => 'Solicitud no encontrada']);
    exit;
}
$row = $q->fetch_assoc();
$stmt->close();

// Decodificar descripción con tolerancia a variaciones (texto plano, JSON con barras invertidas o doble codificación)
$descText = '';
$descImages = [];
$descFiles = [];
if (isset($row['descripcion']) && $row['descripcion'] !== '') {
    $raw = (string) $row['descripcion'];
    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        // Intento 2: quitar barras escapadas comunes
        $try = json_decode(stripslashes($raw), true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($try)) {
            $decoded = $try;
        } elseif (is_string($decoded)) {
            // Intento 3: si la primera decodificación dio string con JSON dentro
            $inner = json_decode($decoded, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($inner)) {
                $decoded = $inner;
            }
        }
    }

    if (is_array($decoded)) {
        $descText = isset($decoded['text']) ? (string) $decoded['text'] : $raw;
        $rawImages = [];
        if (isset($decoded['images']) && is_array($decoded['images'])) {
            $rawImages = $decoded['images'];
        } elseif (isset($decoded['imagenes']) && is_array($decoded['imagenes'])) {
            $rawImages = $decoded['imagenes'];
        }
        $descImages = array_values(array_filter(array_map(static function ($x) {
            if (is_array($x)) {
                return trim((string) ($x['ruta'] ?? $x['url'] ?? $x['path'] ?? $x['src'] ?? $x['href'] ?? ''));
            }
            return trim((string) $x);
        }, $rawImages)));

        $rawFiles = [];
        if (isset($decoded['files']) && is_array($decoded['files'])) {
            $rawFiles = $decoded['files'];
        } elseif (isset($decoded['archivos']) && is_array($decoded['archivos'])) {
            $rawFiles = $decoded['archivos'];
        }
        $descFiles = array_values(array_filter(array_map(static function ($x) {
            if (is_array($x)) {
                return trim((string) ($x['ruta'] ?? $x['url'] ?? $x['path'] ?? $x['src'] ?? $x['href'] ?? ''));
            }
            return trim((string) $x);
        }, $rawFiles)));
    } else {
        // Texto plano legado
        $descText = $raw;
    }

    // Fallback: extraer imágenes embebidas en markdown del texto
    if ($descText !== '' && preg_match_all('/!\[[^\]]*\]\(([^)\s]+)\)/', $descText, $mm)) {
        foreach ($mm[1] as $u) {
            $u = trim((string) $u);
            if ($u !== '' && !in_array($u, $descImages, true)) {
                $descImages[] = $u;
            }
        }
    }
}

$idCliente = isset($row['id_cliente']) ? (int) $row['id_cliente'] : 0;
$idProyecto = isset($row['id_proyecto']) ? (int) $row['id_proyecto'] : 0;
$clienteNombre = '';
$nombreProyecto = '';
$urlProyecto = '';

if ($idCliente > 0) {
    $stCli = $conexion->prepare('SELECT empresa, nombre_contacto FROM clientes WHERE id = ? LIMIT 1');
    if ($stCli) {
        $stCli->bind_param('i', $idCliente);
        $stCli->execute();
        $resCli = $stCli->get_result();
        if ($cli = $resCli->fetch_assoc()) {
            $empresa = trim((string) ($cli['empresa'] ?? ''));
            $contacto = trim((string) ($cli['nombre_contacto'] ?? ''));
            $clienteNombre = $empresa !== '' ? $empresa : $contacto;
        }
        $stCli->close();
    }
}

if ($idProyecto > 0) {
    $stPro = $conexion->prepare('SELECT nombre_proyecto, url, id_cliente FROM proyectos WHERE id_proyecto = ? LIMIT 1');
    if ($stPro) {
        $stPro->bind_param('i', $idProyecto);
        $stPro->execute();
        $resPro = $stPro->get_result();
        if ($pro = $resPro->fetch_assoc()) {
            $nombreProyecto = trim((string) ($pro['nombre_proyecto'] ?? ''));
            $urlProyecto = trim((string) ($pro['url'] ?? ''));
            if ($clienteNombre === '' && (int) ($pro['id_cliente'] ?? 0) > 0) {
                $idCliFromPro = (int) $pro['id_cliente'];
                $stCli2 = $conexion->prepare('SELECT empresa, nombre_contacto FROM clientes WHERE id = ? LIMIT 1');
                if ($stCli2) {
                    $stCli2->bind_param('i', $idCliFromPro);
                    $stCli2->execute();
                    $resCli2 = $stCli2->get_result();
                    if ($cli2 = $resCli2->fetch_assoc()) {
                        $empresa2 = trim((string) ($cli2['empresa'] ?? ''));
                        $contacto2 = trim((string) ($cli2['nombre_contacto'] ?? ''));
                        $clienteNombre = $empresa2 !== '' ? $empresa2 : $contacto2;
                    }
                    $stCli2->close();
                }
            }
        }
        $stPro->close();
    }
}

$payload = [
    'id' => (int) $row['id'],
    'titulo' => (string) ($row['titulo'] ?? ''),
    'descripcion_text' => $descText,
    'descripcion_images' => cw_ticket_media_urls($descImages),
    'descripcion_files' => cw_ticket_media_urls($descFiles),
    'usuario_asignado' => $row['usuario_asignado'] !== null && $row['usuario_asignado'] !== ''
        ? (string) $row['usuario_asignado']
        : null,
    'prioridad' => (string) ($row['prioridad'] ?? 'Media'),
    'estado' => (string) ($row['estado'] ?? 'Pendiente'),
    'repetir' => isset($row['repetir']) ? (int) $row['repetir'] : 0,
    'fecha_lim' => isset($row['fecha_lim']) && $row['fecha_lim'] !== '' && $row['fecha_lim'] !== '0000-00-00'
        ? (string) $row['fecha_lim']
        : null,
    'id_cliente' => $idCliente > 0 ? $idCliente : null,
    'id_proyecto' => $idProyecto > 0 ? $idProyecto : null,
    'cliente_nombre' => $clienteNombre,
    'nombre_proyecto' => $nombreProyecto,
    'url_proyecto' => $urlProyecto,
];

echo json_encode(['success' => true, 'solicitud' => $payload], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
exit;
