<?php
require_once __DIR__ . '/../auth_middleware.php';
require_once __DIR__ . '/db/conexion.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$download = isset($_GET['download']);

if ($id <= 0) {
    die('ID inválido');
}

$stmt = $conexion->prepare("SELECT folio, pdf_path FROM cotizaciones WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Cotización no encontrada');
}

$row = $result->fetch_assoc();
$pdfPath = $row['pdf_path'];

if (!$pdfPath || !file_exists($pdfPath)) {
    require_once __DIR__ . '/helpers_cotizacion.php';
    require_once __DIR__ . '/../includes/cotizacion_precios_service.php';

    $stmt2 = $conexion->prepare("SELECT * FROM cotizaciones WHERE id = ?");
    $stmt2->bind_param('i', $id);
    $stmt2->execute();
    $fullData = $stmt2->get_result()->fetch_assoc();

    if (!$fullData) {
        die('Error al regenerar PDF');
    }

    $items = json_decode($fullData['items'], true) ?? [];
    $solicitudesIds = json_decode($fullData['solicitudes_ids'], true) ?? [];
    $solicitudes = obtenerSolicitudesParaCotizacion($conexion, $solicitudesIds);

    $cotData = [
        'id' => $fullData['id'],
        'folio' => $fullData['folio'],
        'cliente_nombre' => $fullData['cliente_nombre'],
        'proyecto_nombre' => $fullData['proyecto_nombre'],
        'dias_validez' => 15,
        'descuento_porcentaje' => (float)$fullData['descuento_porcentaje'],
        'notas_adicionales' => '',
        'created_at' => $fullData['created_at'],
    ];

    $logoPath = __DIR__ . '/../images/c-online_completo.png';
    $pdfPath = generarPDFCotizacion($cotData, $items, $solicitudes, $logoPath);

    $upd = $conexion->prepare("UPDATE cotizaciones SET pdf_path = ? WHERE id = ?");
    $upd->bind_param('si', $pdfPath, $id);
    $upd->execute();
}

if (file_exists($pdfPath)) {
    $filename = 'cotizacion_' . preg_replace('/[^A-Za-z0-9\-]/', '_', $row['folio']) . '.pdf';
    header('Content-Type: application/pdf');
    if ($download) {
        header('Content-Disposition: attachment; filename="' . $filename . '"');
    } else {
        header('Content-Disposition: inline; filename="' . $filename . '"');
    }
    header('Content-Length: ' . filesize($pdfPath));
    header('Cache-Control: no-cache');
    readfile($pdfPath);
    exit;
}

die('PDF no encontrado');
