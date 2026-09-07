<?php

function cliente_dias_vencimiento($fecha): ?int {
    if (empty($fecha) || $fecha === '0000-00-00' || $fecha === '0001-01-01') {
        return null;
    }
    $ts = strtotime($fecha);
    if ($ts === false) {
        return null;
    }
    return (int) floor(($ts - strtotime('today')) / 86400);
}

function cliente_render_dias_badge(?int $dias): string {
    if ($dias === null) {
        return '';
    }

    if ($dias < 0) {
        $n = abs($dias);
        $class = 'days-badge--expired';
        $num = (string) $n;
        $numClass = '';
        $label = 'día' . ($n === 1 ? '' : 's') . ' vencido' . ($n === 1 ? '' : 's');
        $icon = 'bi-exclamation-triangle-fill';
    } elseif ($dias === 0) {
        $class = 'days-badge--today';
        $num = 'Hoy';
        $numClass = ' days-badge__num--text';
        $label = 'Vence hoy';
        $icon = 'bi-alarm-fill';
    } elseif ($dias <= 30) {
        $class = 'days-badge--warn';
        $num = (string) $dias;
        $numClass = '';
        $label = 'día' . ($dias === 1 ? '' : 's') . ' para vencer';
        $icon = 'bi-clock-fill';
    } else {
        $class = 'days-badge--ok';
        $num = (string) $dias;
        $numClass = '';
        $label = 'días restantes';
        $icon = 'bi-calendar-check-fill';
    }

    return '<div class="days-badge ' . $class . '">'
        . '<span class="days-badge__num' . $numClass . '">' . htmlspecialchars($num) . '</span>'
        . '<span class="days-badge__label"><i class="bi ' . $icon . '"></i>'
        . htmlspecialchars($label) . '</span>'
        . '</div>';
}

/** Chip ultra compacto para poner al lado del título del servicio */
function cliente_render_dias_chip(?int $dias): string {
    if ($dias === null) {
        return '';
    }

    if ($dias < 0) {
        $n = abs($dias);
        $class = 'svc-chip--danger';
        $text = $n . ' d venc.';
    } elseif ($dias === 0) {
        $class = 'svc-chip--warn';
        $text = 'Hoy';
    } elseif ($dias <= 30) {
        $class = 'svc-chip--warn';
        $text = $dias . ' d';
    } else {
        $class = 'svc-chip--ok';
        $text = $dias . ' d';
    }

    return '<span class="svc-chip ' . $class . '" title="Días respecto al vencimiento">'
        . htmlspecialchars($text)
        . '</span>';
}

function cliente_fecha_corta($fecha): string {
    if (empty($fecha) || $fecha === '0000-00-00' || $fecha === '0001-01-01') {
        return '—';
    }
    $meses = [
        1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr',
        5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'ago',
        9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic',
    ];
    $ts = strtotime($fecha);
    if ($ts === false) {
        return '—';
    }
    return date('j', $ts) . ' ' . $meses[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}
