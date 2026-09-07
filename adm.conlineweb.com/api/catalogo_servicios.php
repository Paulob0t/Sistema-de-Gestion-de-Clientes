<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../conn.php';

function normalizar_categoria_catalogo($valor) {
    if ($valor === null) {
        return null;
    }

    $valor = trim((string) $valor);
    if ($valor === '') {
        return null;
    }

    $valorLower = mb_strtolower($valor, 'UTF-8');

    $mapa = [
        'Webs Informativas' => '/webs?\s+informativas?|p[aá]ginas?\s+web|web\s*site|sitios?\s+web|sitio\s+web\s+corporativo|blog|landing|one\s*page|corporativ|institucional|informativo/',
        'E-commerce' => '/e-?commerce|tienda|carrito|venta\s+en\s+l[ií]nea|compras\s+en\s+l[ií]nea|shop/',
        'Mantenimiento' => '/mantenimiento|soporte|actualiz|correcci[oó]n|error|bug/',
        'Dominios y Hosting' => '/dominio|hosting|ssl|correo|mail|email|migraci[oó]n/',
        'SEO y Marketing' => '/seo|marketing|google|analytics|pixel|meta|facebook|posicionamiento/',
        'Servicios Pequeños' => '/servicios?\s+peque[nñ]os?|logo|banner|formulario|texto|imagen|bot[oó]n|enlace|ajuste/'
    ];

    foreach ($mapa as $categoriaCanonica => $patron) {
        if (preg_match($patron, $valorLower)) {
            return $categoriaCanonica;
        }
    }

    return $valor;
}

// --- Parámetros opcionales de filtro ---
$categoria  = isset($_GET['categoria'])  ? trim($_GET['categoria'])  : null;
$busqueda   = isset($_GET['q'])          ? trim($_GET['q'])           : null;
$moneda     = isset($_GET['moneda'])     ? strtolower(trim($_GET['moneda'])) : 'mxn';
$servicio_numero = isset($_GET['servicio']) ? (int)$_GET['servicio'] : null;

// Columna de precio según moneda solicitada
$columnas_precio = ['mxn' => 'precio_mxn', 'usd' => 'precio_usd', 'clp' => 'precio_clp'];
$col_precio = $columnas_precio[$moneda] ?? 'precio_mxn';

// --- Construir consulta ---
$where  = [];
$params = [];
$types  = '';

if ($categoria !== null && $categoria !== '') {
    $categoriaNormalizada = normalizar_categoria_catalogo($categoria) ?? $categoria;
    $where[]  = "categoria LIKE ?";
    $params[] = $categoriaNormalizada . '%';
    $types   .= 's';
}

if ($servicio_numero !== null && $servicio_numero > 0) {
    $where[]  = "servicio_numero = ?";
    $params[] = $servicio_numero;
    $types   .= 'i';
}

if ($busqueda !== null && $busqueda !== '') {
    $where[]  = "(titulo LIKE ? OR descripcion_breve LIKE ? OR incluye LIKE ?)";
    $like     = '%' . $busqueda . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types   .= 'sss';
}

$sql = "SELECT
            id,
            servicio_numero,
            titulo,
            descripcion_breve,
            incluye,
            precio_mxn,
            precio_usd,
            precio_clp,
            categoria,
            created_at
        FROM catalogo";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY categoria ASC, servicio_numero ASC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error al preparar la consulta.']);
    exit;
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$servicios = [];
while ($row = $result->fetch_assoc()) {
    // Precio según moneda solicitada
    $precio = (float) $row[$col_precio];
    $categoriaCanonica = normalizar_categoria_catalogo($row['categoria']) ?? $row['categoria'];

    // Formatear incluye como array de ítems (líneas que empiecen con ✅)
    $incluye_raw   = $row['incluye'];
    $incluye_lineas = array_values(array_filter(
        array_map('trim', explode("\n", $incluye_raw)),
        function($l) { return $l !== ''; }
    ));

    $servicios[] = [
        'id'               => (int) $row['id'],
        'servicio_numero'  => (int) $row['servicio_numero'],
        'titulo'           => $row['titulo'],
        'descripcion_breve'=> $row['descripcion_breve'],
        'incluye'          => $incluye_lineas,
        'precio'           => $precio,
        'moneda'           => strtoupper($moneda),
        'precio_mxn'       => (float) $row['precio_mxn'],
        'precio_usd'       => (float) $row['precio_usd'],
        'precio_clp'       => (float) $row['precio_clp'],
        'categoria'        => $categoriaCanonica,
        'categoria_raw'    => $row['categoria'],
    ];
}

$stmt->close();

// --- Agrupar por categoría ---
$por_categoria = [];
foreach ($servicios as $s) {
    $por_categoria[$s['categoria']][] = $s;
}

// --- Obtener lista de categorías disponibles ---
$res_cats = $conn->query("SELECT DISTINCT categoria FROM catalogo ORDER BY categoria ASC");
$categorias_disponibles = [];
$categorias_disponibles_raw = [];
if ($res_cats) {
    while ($c = $res_cats->fetch_assoc()) {
        $categorias_disponibles_raw[] = $c['categoria'];

        $categoriaCanonica = normalizar_categoria_catalogo($c['categoria']) ?? $c['categoria'];
        if (!in_array($categoriaCanonica, $categorias_disponibles, true)) {
            $categorias_disponibles[] = $categoriaCanonica;
        }
    }
}

echo json_encode([
    'success'               => true,
    'total'                 => count($servicios),
    'moneda_usada'          => strtoupper($moneda),
    'categorias_disponibles'=> $categorias_disponibles,
    'categorias_disponibles_raw' => $categorias_disponibles_raw,
    'servicios'             => $servicios,
    'por_categoria'         => $por_categoria,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
