<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/../auth_middleware.php';
require_once __DIR__ . '/db/conexion.php';
require_once __DIR__ . '/helpers_cotizacion.php';
require_once __DIR__ . '/../includes/cotizacion_precios_service.php';

$input = json_decode(file_get_contents('php://input'), true);
$solicitudIds = $input['ids'] ?? $_POST['ids'] ?? [];

if (empty($solicitudIds) || !is_array($solicitudIds)) {
    echo json_encode(['success' => false, 'error' => 'No se recibieron IDs de solicitudes']);
    exit;
}

$solicitudIds = array_map('intval', $solicitudIds);
$solicitudIds = array_filter($solicitudIds, fn($id) => $id > 0);

if (empty($solicitudIds)) {
    echo json_encode(['success' => false, 'error' => 'IDs de solicitudes inválidos']);
    exit;
}

try {
    $solicitudes = obtenerSolicitudesParaCotizacion($conexion, $solicitudIds);

    if (empty($solicitudes)) {
        echo json_encode(['success' => false, 'error' => 'No se encontraron solicitudes']);
        exit;
    }

    $clienteNombre = '';
    $clienteId = null;
    $proyectoNombre = '';
    $proyectoId = null;

    foreach ($solicitudes as $s) {
        if (!empty($s['cliente_nombre']) || !empty($s['cliente_empresa'])) {
            $clienteNombre = $s['cliente_nombre'] ?: $s['cliente_empresa'];
            $clienteId = $s['cliente_id'];
        }
        if (!empty($s['proyecto_nombre'])) {
            $proyectoNombre = $s['proyecto_nombre'];
            $proyectoId = $s['proyecto_id'];
        }
    }

    $aiResult = cotizacion_analizar_solicitudes($solicitudes);

    if (!$aiResult['success']) {
        echo json_encode(['success' => false, 'error' => $aiResult['error']]);
        exit;
    }

    $items = $aiResult['items'];
    $notasAdicionales = $aiResult['notas_adicionales'] ?? '';
    $diasValidez = $aiResult['dias_validez'] ?? 15;

    $subtotal = 0;
    foreach ($items as &$item) {
        $item['total'] = round((float)($item['precio_unitario'] ?? 0) * (int)($item['cantidad'] ?? 1), 2);
        $subtotal += $item['total'];
    }
    unset($item);

    $descuentoPorcentaje = 30;
    $descuentoMonto = round($subtotal * ($descuentoPorcentaje / 100), 2);
    $total = round($subtotal - $descuentoMonto, 2);

    $folio = generarFolioCotizacion($conexion);

    $itemsJson = json_encode($items, JSON_UNESCAPED_UNICODE);
    $solicitudesIdsJson = json_encode($solicitudIds);
    $createdBy = (int)($_SESSION['uid'] ?? 0);
    $aiRaw = $aiResult['raw'] ?? '';

    $clienteIdVal = $clienteId ? (string)(int)$clienteId : '';
    $proyectoIdVal = $proyectoId ? (string)(int)$proyectoId : '';

    $stmt = $conexion->prepare("
        INSERT INTO cotizaciones (folio, id_cliente, cliente_nombre, id_proyecto, proyecto_nombre,
            solicitudes_ids, items, subtotal, descuento_porcentaje, descuento_monto, total,
            moneda, estatus, ai_response_raw, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'MXN', 'Activa', ?, ?, NOW())
    ");

    $stmt->bind_param(
        'sssssssdddddsi',
        $folio,
        $clienteIdVal,
        $clienteNombre,
        $proyectoIdVal,
        $proyectoNombre,
        $solicitudesIdsJson,
        $itemsJson,
        $subtotal,
        $descuentoPorcentaje,
        $descuentoMonto,
        $total,
        $aiRaw,
        $createdBy
    );

    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'error' => 'Error al guardar cotización: ' . $stmt->error]);
        exit;
    }

    $cotizacionId = $conexion->insert_id;

    $cotizacionData = [
        'id' => $cotizacionId,
        'folio' => $folio,
        'cliente_nombre' => $clienteNombre,
        'proyecto_nombre' => $proyectoNombre,
        'dias_validez' => $diasValidez,
        'descuento_porcentaje' => $descuentoPorcentaje,
        'notas_adicionales' => $notasAdicionales,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $logoPath = __DIR__ . '/../images/c-online_completo.png';
    $pdfPath = generarPDFCotizacion($cotizacionData, $items, $solicitudes, $logoPath);

    $pdfUpdate = $conexion->prepare("UPDATE cotizaciones SET pdf_path = ? WHERE id = ?");
    $pdfUpdate->bind_param('si', $pdfPath, $cotizacionId);
    $pdfUpdate->execute();

    echo json_encode([
        'success' => true,
        'cotizacion' => [
            'id' => $cotizacionId,
            'folio' => $folio,
            'cliente_nombre' => $clienteNombre,
            'proyecto_nombre' => $proyectoNombre,
            'fecha' => date('d/m/Y H:i'),
            'subtotal' => $subtotal,
            'descuento_porcentaje' => $descuentoPorcentaje,
            'descuento_monto' => $descuentoMonto,
            'total' => $total,
            'dias_validez' => $diasValidez,
            'notas_adicionales' => $notasAdicionales,
            'items' => $items,
            'pdf_path' => $pdfPath,
        ],
    ]);

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()]);
}
