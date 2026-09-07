<?php

function generarFolioCotizacion($db): string
{
    $year = date('Y');
    $prefix = "COT-{$year}-";
    $result = $db->query("SELECT COUNT(*) AS total FROM cotizaciones WHERE folio LIKE '{$prefix}%'");
    $row = $result->fetch_assoc();
    $next = ((int)($row['total'] ?? 0)) + 1;
    return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
}

function formatearMoneda($cantidad)
{
    return '$' . number_format($cantidad, 2, '.', ',') . ' MXN';
}

function obtenerSolicitudesParaCotizacion($conexion, array $ids)
{
    if (empty($ids)) return [];

    $escaped = array_map(function($id) use ($conexion) {
        return (int)$id;
    }, $ids);
    $idsStr = implode(',', $escaped);

    $hasIdCliente = $conexion->query("SHOW COLUMNS FROM solicitudes LIKE 'id_cliente'")->num_rows > 0;
    $hasIdProyecto = $conexion->query("SHOW COLUMNS FROM solicitudes LIKE 'id_proyecto'")->num_rows > 0;

    $selectCliente = $hasIdCliente ? ", c.empresa AS cliente_empresa, c.nombre_contacto AS cliente_nombre" : ", NULL AS cliente_empresa, NULL AS cliente_nombre";
    $joinCliente = $hasIdCliente ? " LEFT JOIN clientes c ON s.id_cliente = c.id " : "";
    $selectProyecto = $hasIdProyecto ? ", p.nombre_proyecto" : ", NULL AS nombre_proyecto";
    $joinProyecto = $hasIdProyecto ? " LEFT JOIN proyectos p ON s.id_proyecto = p.id_proyecto " : "";

    $sql = "SELECT s.* {$selectCliente} {$selectProyecto} FROM solicitudes s {$joinCliente} {$joinProyecto} WHERE s.id IN ({$idsStr}) ORDER BY s.id ASC";
    $q = $conexion->query($sql);

    $solicitudes = [];
    while ($row = $q->fetch_assoc()) {
        $descText = '';
        $decoded = json_decode($row['descripcion'] ?? '', true);
        if (is_array($decoded) && isset($decoded['text'])) {
            $descText = $decoded['text'];
        } else {
            $descText = $row['descripcion'] ?? '';
        }

        $solicitudes[] = [
            'id' => (int)$row['id'],
            'titulo' => $row['titulo'] ?? '',
            'descripcion_text' => $descText,
            'prioridad' => $row['prioridad'] ?? 'Media',
            'cliente_id' => $hasIdCliente && isset($row['id_cliente']) ? (int)$row['id_cliente'] : null,
            'cliente_empresa' => $row['cliente_empresa'] ?? '',
            'cliente_nombre' => $row['cliente_nombre'] ?? '',
            'proyecto_id' => $hasIdProyecto && isset($row['id_proyecto']) ? (int)$row['id_proyecto'] : null,
            'proyecto_nombre' => $row['nombre_proyecto'] ?? '',
        ];
    }

    return $solicitudes;
}

function generarPDFCotizacion($cotizacion, $items, $solicitudes, $logoPath = '')
{
    require_once __DIR__ . '/../tcpdf/tcpdf.php';

    $pdf = new TCPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);

    $pdf->SetCreator('ConlineWeb');
    $pdf->SetAuthor('ConlineWeb');
    $pdf->SetTitle('Cotización ' . $cotizacion['folio']);
    $pdf->SetSubject('Cotización de Servicios');

    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    $pdf->SetMargins(20, 20, 20);
    $pdf->SetAutoPageBreak(true, 30);

    $pdf->AddPage();

    $pdf->SetFont('helvetica', '', 9);

    $navy = [0, 1, 71];
    $gold = [255, 193, 7];
    $gray = [100, 116, 139];
    $dark = [30, 41, 59];
    $light = [248, 250, 252];

    $pdf->SetFillColor($navy[0], $navy[1], $navy[2]);
    $pdf->Rect(0, 0, 216, 45, 'F');

    if ($logoPath && file_exists($logoPath)) {
        $pdf->Image($logoPath, 20, 8, 45, 0, 'PNG');
    }

    $pdf->SetY(8);
    $pdf->SetX(75);
    $pdf->SetFont('helvetica', 'B', 22);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 10, 'COTIZACION', 0, 1, 'R');

    $pdf->SetX(75);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor($gold[0], $gold[1], $gold[2]);
    $pdf->Cell(0, 6, 'Servicios de Desarrollo de Software', 0, 1, 'R');

    $pdf->SetY(50);

    $pdf->SetTextColor($dark[0], $dark[1], $dark[2]);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 7, 'DATOS DE LA COTIZACION', 0, 1, 'L');

    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor($gray[0], $gray[1], $gray[2]);

    $infoX = 20;
    $infoY = $pdf->GetY();

    $pdf->SetXY($infoX, $infoY);
    $pdf->SetTextColor($dark[0], $dark[1], $dark[2]);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(30, 5, 'Folio:', 0, 0);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor($gray[0], $gray[1], $gray[2]);
    $pdf->Cell(60, 5, $cotizacion['folio'], 0, 0);

    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetTextColor($dark[0], $dark[1], $dark[2]);
    $pdf->Cell(20, 5, 'Fecha:', 0, 0);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor($gray[0], $gray[1], $gray[2]);
    $pdf->Cell(0, 5, date('d/m/Y', strtotime($cotizacion['created_at'])), 0, 1);

    $pdf->SetX($infoX);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetTextColor($dark[0], $dark[1], $dark[2]);
    $pdf->Cell(30, 5, 'Cliente:', 0, 0);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor($gray[0], $gray[1], $gray[2]);
    $pdf->Cell(0, 5, $cotizacion['cliente_nombre'], 0, 1);

    if (!empty($cotizacion['proyecto_nombre'])) {
        $pdf->SetX($infoX);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetTextColor($dark[0], $dark[1], $dark[2]);
        $pdf->Cell(30, 5, 'Proyecto:', 0, 0);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor($gray[0], $gray[1], $gray[2]);
        $pdf->Cell(0, 5, $cotizacion['proyecto_nombre'], 0, 1);
    }

    $pdf->SetX($infoX);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetTextColor($dark[0], $dark[1], $dark[2]);
    $pdf->Cell(30, 5, 'Validez:', 0, 0);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor($gray[0], $gray[1], $gray[2]);
    $pdf->Cell(0, 5, $cotizacion['dias_validez'] . ' dias', 0, 1);

    $pdf->Ln(6);

    $pdf->SetFillColor($navy[0], $navy[1], $navy[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 8);

    $colW = [10, 70, 12, 28, 28, 28];
    $headers = ['#', 'Descripcion del Servicio', 'Ud.', 'Precio Unit.', 'Cant.', 'Total'];

    $tableX = 20;
    $pdf->SetX($tableX);
    foreach ($headers as $i => $h) {
        $pdf->Cell($colW[$i], 8, $h, 1, 0, 'C', true);
    }
    $pdf->Ln();

    $pdf->SetTextColor($dark[0], $dark[1], $dark[2]);
    $pdf->SetFont('helvetica', '', 8);

    $fill = false;
    $subtotal = 0;

    foreach ($items as $idx => $item) {
        $totalItem = (float)($item['total'] ?? 0);
        $subtotal += $totalItem;

        $precioU = (float)($item['precio_unitario'] ?? 0);
        $cant = (int)($item['cantidad'] ?? 1);
        $descServicio = $item['descripcion_servicio'] ?? $item['titulo'] ?? '';

        $numLines = ceil($pdf->getStringWidth($descServicio) / ($colW[1] - 2)) + 1;
        if ($numLines < 1) $numLines = 1;
        $rowH = max(7, $numLines * 5);

        if ($pdf->GetY() + $rowH > 260) {
            $pdf->AddPage();
            $pdf->SetFillColor($navy[0], $navy[1], $navy[2]);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetX($tableX);
            foreach ($headers as $i => $h) {
                $pdf->Cell($colW[$i], 8, $h, 1, 0, 'C', true);
            }
            $pdf->Ln();
            $pdf->SetTextColor($dark[0], $dark[1], $dark[2]);
            $pdf->SetFont('helvetica', '', 8);
        }

        $xStart = $pdf->GetX();
        $yStart = $pdf->GetY();

        if ($fill) {
            $pdf->SetFillColor($light[0], $light[1], $light[2]);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }

        $pdf->SetX($tableX);
        $pdf->Cell($colW[0], $rowH, ($idx + 1), 'LR', 0, 'C', $fill);
        $pdf->Cell($colW[1], $rowH, $descServicio, 'LR', 0, 'L', $fill);
        $pdf->Cell($colW[2], $rowH, $item['unidad'] ?? 'pza', 'LR', 0, 'C', $fill);
        $pdf->Cell($colW[3], $rowH, '$' . number_format($precioU, 2), 'LR', 0, 'R', $fill);
        $pdf->Cell($colW[4], $rowH, $cant, 'LR', 0, 'C', $fill);
        $pdf->Cell($colW[5], $rowH, '$' . number_format($totalItem, 2), 'LR', 0, 'R', $fill);
        $pdf->Ln();
        $pdf->SetX($tableX);
        $pdf->Cell(array_sum($colW), 0.5, '', 'T', 1);

        $fill = !$fill;
    }

    $pdf->Ln(4);

    $descuentoPorc = (float)($cotizacion['descuento_porcentaje'] ?? 30);
    $descuentoMonto = round($subtotal * ($descuentoPorc / 100), 2);
    $total = round($subtotal - $descuentoMonto, 2);
    $iva = round($total * 0.16, 2);
    $totalConIva = round($total + $iva, 2);

    $totalsX = 120;
    $labelW = 45;
    $valueW = 35;

    $pdf->SetX($totalsX);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor($gray[0], $gray[1], $gray[2]);
    $pdf->Cell($labelW, 6, 'Subtotal:', 0, 0, 'R');
    $pdf->SetTextColor($dark[0], $dark[1], $dark[2]);
    $pdf->Cell($valueW, 6, '$' . number_format($subtotal, 2), 0, 1, 'R');

    $pdf->SetX($totalsX);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor($gray[0], $gray[1], $gray[2]);
    $pdf->Cell($labelW, 6, 'Descuento (' . number_format($descuentoPorc, 0) . '%):', 0, 0, 'R');
    $pdf->SetTextColor(220, 38, 38);
    $pdf->Cell($valueW, 6, '-$' . number_format($descuentoMonto, 2), 0, 1, 'R');

    $pdf->SetX($totalsX);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor($gray[0], $gray[1], $gray[2]);
    $pdf->Cell($labelW, 6, 'Subtotal c/desc.:', 0, 0, 'R');
    $pdf->SetTextColor($dark[0], $dark[1], $dark[2]);
    $pdf->Cell($valueW, 6, '$' . number_format($total, 2), 0, 1, 'R');

    $pdf->SetX($totalsX);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor($gray[0], $gray[1], $gray[2]);
    $pdf->Cell($labelW, 6, 'IVA (16%):', 0, 0, 'R');
    $pdf->SetTextColor($dark[0], $dark[1], $dark[2]);
    $pdf->Cell($valueW, 6, '$' . number_format($iva, 2), 0, 1, 'R');

    $pdf->SetX($totalsX);
    $pdf->SetDrawColor($gold[0], $gold[1], $gold[2]);
    $pdf->SetLineWidth(0.5);
    $pdf->Cell($labelW, 0.5, '', 'T', 0);
    $pdf->Cell($valueW, 0.5, '', 'T', 1);

    $pdf->SetX($totalsX);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColor($navy[0], $navy[1], $navy[2]);
    $pdf->Cell($labelW, 8, 'TOTAL:', 0, 0, 'R');
    $pdf->Cell($valueW, 8, '$' . number_format($totalConIva, 2), 0, 1, 'R');

    $pdf->SetX($totalsX);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetTextColor($gray[0], $gray[1], $gray[2]);
    $pdf->Cell($labelW, 5, 'Total sin IVA:', 0, 0, 'R');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell($valueW, 5, '$' . number_format($total, 2), 0, 1, 'R');

    $pdf->Ln(6);

    if (!empty($cotizacion['notas_adicionales'])) {
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetTextColor($navy[0], $navy[1], $navy[2]);
        $pdf->Cell(0, 6, 'NOTAS:', 0, 1);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor($gray[0], $gray[1], $gray[2]);
        $pdf->MultiCell(0, 5, $cotizacion['notas_adicionales'], 0, 'L');
        $pdf->Ln(3);
    }

    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetTextColor($navy[0], $navy[1], $navy[2]);
    $pdf->Cell(0, 6, 'TERMINOS Y CONDICIONES:', 0, 1);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor($gray[0], $gray[1], $gray[2]);
    $terms = "1. Forma de pago: 50% anticipo, 50% contra entrega.\n";
    $terms .= "2. Los precios incluyen IVA.\n";
    $terms .= "3. Validez de la cotizacion: {$cotizacion['dias_validez']} dias.\n";
    $terms .= "4. Tiempo de entrega sujeto a la complejidad de cada servicio.\n";
    $terms .= "5. Cualquier cambio en el alcance debera ser autorizado por ambas partes.\n";
    $terms .= "6. ConlineWeb - Desarrollo de Software Profesional.";
    $pdf->MultiCell(0, 5, $terms, 0, 'L');

    $pdf->Ln(4);

    $pdf->SetFillColor($navy[0], $navy[1], $navy[2]);
    $pdf->Rect(0, -20, 216, 25, 'F');

    $pdf->SetY(-18);
    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetTextColor($gold[0], $gold[1], $gold[2]);
    $pdf->Cell(0, 4, 'ConlineWeb - Desarrollo de Software Profesional', 0, 1, 'C');
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 4, 'contacto@conlineweb.com | www.conlineweb.com', 0, 1, 'C');

    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = 'cotizacion_' . preg_replace('/[^A-Za-z0-9\-]/', '_', $cotizacion['folio']) . '.pdf';
    $filepath = $uploadDir . $filename;
    $pdf->Output($filepath, 'F');

    return $filepath;
}
