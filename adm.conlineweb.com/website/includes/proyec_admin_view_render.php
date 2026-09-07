<?php
declare(strict_types=1);

/**
 * Renderizado de solo lectura — levantamiento de requerimientos (admin).
 */

function proyec_admin_h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function proyec_admin_render_empty(string $text = 'Sin información'): string
{
    return '<p class="lw-proyec-empty">' . proyec_admin_h($text) . '</p>';
}

/** @param array<string, string> $labels */
function proyec_admin_render_kv_grid(array $items, array $labels): string
{
    $html = '<div class="lw-proyec-kv-grid">';
    $has = false;
    foreach ($labels as $key => $label) {
        $value = trim((string) ($items[$key] ?? ''));
        if ($value === '') {
            continue;
        }
        $has = true;
        $html .= '<div class="lw-proyec-kv"><span class="lw-proyec-kv__label">' . proyec_admin_h($label)
            . '</span><div class="lw-proyec-kv__value">' . nl2br(proyec_admin_h($value)) . '</div></div>';
    }
    $html .= '</div>';

    return $has ? $html : proyec_admin_render_empty();
}

/** @param list<array<string, mixed>> $items */
function proyec_admin_render_repeat_cards(array $items, array $fieldLabels): string
{
    if ($items === []) {
        return proyec_admin_render_empty();
    }
    $html = '<div class="lw-proyec-cards">';
    foreach ($items as $idx => $item) {
        if (!is_array($item)) {
            continue;
        }
        $html .= '<article class="lw-proyec-card"><header class="lw-proyec-card__head">Elemento ' . ($idx + 1) . '</header><div class="lw-proyec-kv-grid">';
        foreach ($fieldLabels as $key => $label) {
            $value = $item[$key] ?? '';
            if (is_array($value)) {
                if ($key === 'campos' && $value !== []) {
                    $value = implode(', ', array_map(static function ($c) {
                        if (!is_array($c)) {
                            return (string) $c;
                        }
                        $parts = array_filter([
                            $c['nombre'] ?? '',
                            $c['tipo'] ?? '',
                            !empty($c['requerido']) ? 'obligatorio' : '',
                        ]);
                        return implode(' · ', $parts);
                    }, $value));
                } elseif ($key === 'ejemplos' && $value !== []) {
                    $names = [];
                    foreach ($value as $ej) {
                        if (is_array($ej) && !empty($ej['nombre_archivo'])) {
                            $names[] = (string) $ej['nombre_archivo'];
                        }
                    }
                    $value = $names !== [] ? implode(', ', $names) : '';
                } else {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                }
            }
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            $html .= '<div class="lw-proyec-kv"><span class="lw-proyec-kv__label">' . proyec_admin_h($label)
                . '</span><div class="lw-proyec-kv__value">' . nl2br(proyec_admin_h($value)) . '</div></div>';
        }
        $html .= '</div></article>';
    }
    $html .= '</div>';

    return $html;
}

/** @param array<string, mixed> $project */
function proyec_admin_render_full_project(array $project, ?array $lead = null): string
{
    $datos = is_array($project['datos'] ?? null) ? $project['datos'] : [];
    $steps = proyec_admin_step_titles();
    $html = '';

    $descLabels = [
        'proyecto' => 'Describa el proyecto',
        'problema' => 'Problema que desea resolver',
        'objetivos' => 'Objetivos',
        'beneficios' => 'Beneficios esperados',
        'alcance' => 'Alcance general',
        'procesos_involucrados' => 'Procesos involucrados',
    ];

    $repeatMap = [
        3 => ['key' => 'procesos', 'fields' => ['nombre' => 'Nombre', 'descripcion' => 'Descripción', 'entradas' => 'Entradas', 'salidas' => 'Salidas', 'actores' => 'Actores']],
        4 => ['key' => 'modulos', 'fields' => ['nombre' => 'Nombre', 'descripcion' => 'Descripción', 'prioridad' => 'Prioridad']],
        5 => ['key' => 'funcionalidades', 'fields' => ['nombre' => 'Nombre', 'modulo' => 'Módulo', 'descripcion' => 'Descripción', 'prioridad' => 'Prioridad']],
        6 => ['key' => 'usuarios', 'fields' => ['nombre' => 'Perfil', 'descripcion' => 'Descripción', 'permisos' => 'Permisos', 'restricciones' => 'Restricciones']],
        7 => ['key' => 'formularios', 'fields' => ['nombre' => 'Formulario', 'descripcion' => 'Descripción', 'campos' => 'Campos']],
        8 => ['key' => 'reportes', 'fields' => ['nombre' => 'Reporte', 'descripcion' => 'Descripción', 'campos' => 'Campos', 'filtros' => 'Filtros', 'exportar' => 'Exportar', 'ejemplos' => 'Archivos de ejemplo']],
        9 => ['key' => 'dashboard', 'fields' => ['nombre' => 'KPI', 'descripcion' => 'Descripción', 'fuente' => 'Fuente', 'frecuencia' => 'Frecuencia']],
        10 => ['key' => 'automatizaciones', 'fields' => ['nombre' => 'Nombre', 'cuando' => 'Cuando', 'entonces' => 'Entonces', 'descripcion' => 'Descripción']],
    ];

    foreach ($steps as $num => $title) {
        $enabled = proyec_admin_is_step_optin($datos, (int) $num);
        $status = $num >= 3
            ? ($enabled ? '<span class="lw-proyec-pill lw-proyec-pill--on">Incluido</span>' : '<span class="lw-proyec-pill lw-proyec-pill--off">Omitido</span>')
            : '<span class="lw-proyec-pill lw-proyec-pill--on">Obligatorio</span>';

        $body = '';
        if ($num === 1) {
            $contacto = is_array($datos['contacto'] ?? null) ? $datos['contacto'] : [];
            $general = is_array($datos['general'] ?? null) ? $datos['general'] : [];
            $body .= '<h4>Contacto</h4>' . proyec_admin_render_kv_grid(array_merge($contacto, [
                'contacto' => $contacto['contacto'] ?? $lead['contacto'] ?? '',
                'correo' => $contacto['correo'] ?? $lead['correo'] ?? '',
                'telefono' => $contacto['telefono'] ?? $lead['telefono'] ?? '',
            ]), [
                'contacto' => 'Nombre del contacto',
                'correo' => 'Correo',
                'telefono' => 'Teléfono',
            ]);
            $body .= '<h4>Proyecto</h4>' . proyec_admin_render_kv_grid(array_merge($general, [
                'nombre_proyecto' => $project['nombre_proyecto'] ?? $general['nombre_proyecto'] ?? '',
                'giro' => $project['giro'] ?? $general['giro'] ?? '',
                'pagina_web' => $project['pagina_web'] ?? $general['pagina_web'] ?? '',
                'objetivo_proyecto' => $project['objetivo_proyecto'] ?? $general['objetivo_proyecto'] ?? '',
            ]), [
                'nombre_proyecto' => 'Nombre del proyecto',
                'giro' => 'Giro',
                'pagina_web' => 'Página web',
                'objetivo_proyecto' => 'Objetivo del proyecto',
            ]);
        } elseif ($num === 2) {
            $body = proyec_admin_render_kv_grid(is_array($datos['descripcion'] ?? null) ? $datos['descripcion'] : [], $descLabels);
        } elseif ($num === 11 && $enabled) {
            $ints = is_array($datos['integraciones'] ?? null) ? $datos['integraciones'] : [];
            $body = $ints !== []
                ? '<ul class="lw-proyec-list">' . implode('', array_map(static fn($i) => '<li>' . proyec_admin_h((string) $i) . '</li>', $ints)) . '</ul>'
                : proyec_admin_render_empty();
            $det = trim((string) ($datos['integraciones_detalle'] ?? ''));
            if ($det !== '') {
                $body .= '<h4>Otras integraciones</h4><div class="lw-proyec-kv__value">' . nl2br(proyec_admin_h($det)) . '</div>';
            }
        } elseif ($num === 12 && $enabled) {
            $body = proyec_admin_render_kv_grid(is_array($datos['diseno'] ?? null) ? $datos['diseno'] : [], [
                'manual_marca' => 'Manual de marca',
                'colores' => 'Colores',
                'tipografias' => 'Tipografías',
                'referencias' => 'Referencias',
                'wireframes' => 'Wireframes',
            ]);
        } elseif ($num === 13 && $enabled) {
            $docs = is_array($datos['documentos'] ?? null) ? $datos['documentos'] : [];
            $body = proyec_admin_render_repeat_cards($docs, [
                'titulo' => 'Título',
                'categoria' => 'Categoría',
                'descripcion' => 'Descripción',
                'nombre_archivo' => 'Archivo',
            ]);
        } elseif ($num === 14 && $enabled) {
            $obs = is_array($datos['observaciones'] ?? null) ? $datos['observaciones'] : [];
            $body = proyec_admin_render_kv_grid($obs, [
                'comentarios' => 'Comentarios finales',
                'riesgos' => 'Riesgos identificados',
                'requerimientos_futuros' => 'Requerimientos futuros',
                'notas_analista' => 'Notas para el analista',
            ]);
        } elseif (isset($repeatMap[$num])) {
            if ($enabled) {
                $cfg = $repeatMap[$num];
                $list = is_array($datos[$cfg['key']] ?? null) ? $datos[$cfg['key']] : [];
                $body = proyec_admin_render_repeat_cards($list, $cfg['fields']);
            } else {
                $body = proyec_admin_render_empty('Módulo omitido por el cliente');
            }
        } elseif ($num >= 3 && !$enabled) {
            $body = proyec_admin_render_empty('Módulo omitido por el cliente');
        }

        $html .= '<section class="lw-proyec-section" id="lw-proyec-step-' . $num . '">'
            . '<div class="lw-proyec-section__head"><h3><span class="lw-proyec-step-num">' . $num . '</span> '
            . proyec_admin_h($title) . '</h3>' . $status . '</div>'
            . '<div class="lw-proyec-section__body">' . $body . '</div></section>';
    }

    return $html;
}
