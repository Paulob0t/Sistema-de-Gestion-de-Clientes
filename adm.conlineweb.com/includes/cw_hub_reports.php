<?php
/**
 * Generación de reportes PDF y Excel del Hub.
 */
require_once __DIR__ . '/cw_hub_analytics.php';

function cw_hub_report_pdf_overview(mysqli $conn, string $from, string $to, string $periodLabel): void
{
    require_once dirname(__DIR__) . '/tcpdf/tcpdf.php';

    $overview = cw_analytics_overview($conn, $from, $to);
    $funnel = cw_analytics_funnel($conn, $from, $to, 15);
    $byService = cw_analytics_leads_by_service($conn, $from, $to);

    $pdf = new TCPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);
    $pdf->SetCreator('ConlineWeb Hub');
    $pdf->SetTitle('Reporte Analítico Web');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 9);

    $navy = [0, 1, 71];
    $pdf->SetFillColor($navy[0], $navy[1], $navy[2]);
    $pdf->Rect(0, 0, 216, 28, 'F');
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->SetXY(15, 8);
    $pdf->Cell(0, 10, 'Reporte Analítico — ConlineWeb', 0, 1);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetX(15);
    $pdf->Cell(0, 6, 'Periodo: ' . $periodLabel . ' | Generado: ' . date('d/m/Y H:i'), 0, 1);

    $pdf->SetTextColor(30, 41, 59);
    $pdf->Ln(8);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, 'Resumen ejecutivo', 0, 1);

    $pdf->SetFont('helvetica', '', 9);
    $metrics = [
        'Páginas vistas' => number_format($overview['pageviews']),
        'Usuarios únicos' => number_format($overview['users']),
        'Sesiones' => number_format($overview['sessions']),
        'Duración prom.' => cw_format_duration($overview['avgSec']),
        'Leads web' => number_format($overview['leadsCount']),
        'Calificados' => number_format($overview['qualified']),
        'Cerrados' => number_format($overview['closedCount']),
    ];
    foreach ($metrics as $label => $val) {
        $pdf->Cell(55, 6, $label . ':', 0, 0);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(0, 6, $val, 0, 1);
        $pdf->SetFont('helvetica', '', 9);
    }

    if (!empty($byService)) {
        $pdf->Ln(4);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 8, 'Leads por servicio', 0, 1);
        $pdf->SetFont('helvetica', '', 9);
        foreach ($byService as $s) {
            $label = CW_HUB_SERVICIOS[$s['s']] ?? $s['s'];
            $pdf->Cell(80, 6, $label, 0, 0);
            $pdf->Cell(0, 6, (string) $s['c'], 0, 1);
        }
    }

    $pdf->Ln(4);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, 'Funnel por página (top 15)', 0, 1);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetFillColor(241, 245, 249);
    $pdf->Cell(55, 7, 'Página', 1, 0, 'L', true);
    $pdf->Cell(22, 7, 'Visitas', 1, 0, 'C', true);
    $pdf->Cell(22, 7, 'Leads', 1, 0, 'C', true);
    $pdf->Cell(22, 7, 'Cerrados', 1, 0, 'C', true);
    $pdf->Cell(22, 7, '% Lead', 1, 0, 'C', true);
    $pdf->Cell(22, 7, '% Cierre', 1, 1, 'C', true);
    $pdf->SetFont('helvetica', '', 7);
    foreach ($funnel as $row) {
        $path = mb_substr($row['path'], 0, 38);
        $pdf->Cell(55, 6, $path, 1, 0);
        $pdf->Cell(22, 6, (string) $row['visitas'], 1, 0, 'C');
        $pdf->Cell(22, 6, (string) $row['leads'], 1, 0, 'C');
        $pdf->Cell(22, 6, (string) $row['cerrados'], 1, 0, 'C');
        $pdf->Cell(22, 6, $row['tasa_lead'] . '%', 1, 0, 'C');
        $pdf->Cell(22, 6, $row['tasa_cierre'] . '%', 1, 1, 'C');
    }

    $pdf->Output('reporte_analitico_' . date('Y-m-d') . '.pdf', 'D');
    exit;
}

function cw_hub_report_excel_table(string $filename, array $headers, array $rows): void
{
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";
    echo '<html><head><meta charset="UTF-8"></head><body><table border="1">';
    echo '<tr>';
    foreach ($headers as $h) {
        echo '<th>' . htmlspecialchars($h) . '</th>';
    }
    echo '</tr>';
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td>' . htmlspecialchars((string) $cell) . '</td>';
        }
        echo '</tr>';
    }
    echo '</table></body></html>';
    exit;
}

function cw_hub_report_export_funnel_excel(mysqli $conn, string $from, string $to): void
{
    $funnel = cw_analytics_funnel($conn, $from, $to, 500);
    $rows = [];
    foreach ($funnel as $p) {
        $rows[] = [
            $p['path'],
            $p['title'] ?? '',
            $p['visitas'],
            $p['usuarios'],
            $p['leads'],
            $p['calificados'],
            $p['cerrados'],
            $p['tasa_lead'] . '%',
            $p['tasa_cierre'] . '%',
        ];
    }
    cw_hub_report_excel_table(
        'funnel_paginas_' . date('Y-m-d') . '.xls',
        ['Ruta', 'Título', 'Visitas', 'Usuarios', 'Leads', 'Calificados', 'Cerrados', 'Tasa lead', 'Tasa cierre'],
        $rows
    );
}
