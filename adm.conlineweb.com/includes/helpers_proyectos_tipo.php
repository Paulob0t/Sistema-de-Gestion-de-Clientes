<?php
/**
 * Tipo de proyecto: 0 Página Web, 1 Desarrollo de Software, 2 Marketing Digital, 3 Otro.
 */

if (!function_exists('adm_proyecto_tipos_map')) {
    function adm_proyecto_tipos_map(): array
    {
        return [
            0 => 'Página Web',
            1 => 'Desarrollo de Software',
            2 => 'Marketing Digital',
            3 => 'Otro',
        ];
    }
}

if (!function_exists('adm_proyecto_tipo_label')) {
    function adm_proyecto_tipo_label($tipo): string
    {
        $map = adm_proyecto_tipos_map();
        $id = (int) $tipo;
        return $map[$id] ?? $map[0];
    }
}

if (!function_exists('adm_proyecto_tipo_normalize')) {
    function adm_proyecto_tipo_normalize($tipo): int
    {
        $id = filter_var($tipo, FILTER_VALIDATE_INT);
        if ($id === false || $id < 0 || $id > 3) {
            return 0;
        }
        return (int) $id;
    }
}

if (!function_exists('adm_proyectos_ensure_tipo_proyecto')) {
    /**
     * Crea la columna tipo_proyecto si no existe.
     */
    function adm_proyectos_ensure_tipo_proyecto(mysqli $conn): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            $chk = $conn->query("SHOW COLUMNS FROM proyectos LIKE 'tipo_proyecto'");
            if ($chk && $chk->num_rows > 0) {
                $ready = true;
                return true;
            }
            $ok = $conn->query(
                "ALTER TABLE proyectos
                 ADD COLUMN tipo_proyecto TINYINT UNSIGNED NOT NULL DEFAULT 0
                 COMMENT '0=Pagina Web,1=Desarrollo Software,2=Marketing Digital,3=Otro'
                 AFTER nombre_proyecto"
            );
            $ready = (bool) $ok;
            return $ready;
        } catch (Throwable $e) {
            $ready = false;
            return false;
        }
    }
}

if (!function_exists('adm_proyecto_tipo_options_html')) {
    function adm_proyecto_tipo_options_html($selected = 0, bool $includeEmpty = false): string
    {
        $selectedInt = filter_var($selected, FILTER_VALIDATE_INT);
        $html = '';
        if ($includeEmpty) {
            $emptySelected = ($selectedInt === false || $selected === '' || $selected === null) ? ' selected' : '';
            $html .= '<option value=""' . $emptySelected . '>Todos los tipos</option>';
        }
        foreach (adm_proyecto_tipos_map() as $id => $label) {
            $sel = ($selectedInt !== false && (int) $id === (int) $selectedInt) ? ' selected' : '';
            $html .= '<option value="' . (int) $id . '"' . $sel . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
        }
        return $html;
    }
}

if (!function_exists('adm_proyecto_tipo_select_cell')) {
    /**
     * Select editable para DataTable.
     */
    function adm_proyecto_tipo_select_cell(int $idProyecto, $tipoActual): string
    {
        $tipo = adm_proyecto_tipo_normalize($tipoActual);
        $label = adm_proyecto_tipo_label($tipo);
        $html = '<select class="selectTipoProyecto" data-id="' . (int) $idProyecto . '" data-prev="' . $tipo . '" title="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">';
        $html .= adm_proyecto_tipo_options_html($tipo, false);
        $html .= '</select>';
        return $html;
    }
}

if (!function_exists('adm_proyecto_tipo_styles')) {
    function adm_proyecto_tipo_styles(): void
    {
        static $printed = false;
        if ($printed) {
            return;
        }
        $printed = true;
        echo <<<'CSS'
<style>
.selectTipoProyecto,
#tipo_proyecto,
#tipo_proyecto_cliente,
#filtroTipoProyectoCliente,
#cliente,
#filtroEmpresa {
    width: 100%;
    height: auto !important;
    min-height: 38px;
    padding: 0.45rem 2.25rem 0.45rem 0.75rem !important;
    font-size: 0.8125rem !important;
    font-weight: 650 !important;
    line-height: 1.35 !important;
    border: 1.5px solid #e2e8f0 !important;
    border-radius: 10px !important;
    color: #0f172a !important;
    white-space: normal;
    text-overflow: clip;
    overflow: visible;
    box-sizing: border-box;
    -webkit-appearance: none !important;
    -moz-appearance: none !important;
    appearance: none !important;
    background-color: #fff !important;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%23475569' d='M1.4 0.6L6 5.2 10.6 0.6 12 2 6 8 0 2z'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: right 0.85rem center !important;
    background-size: 11px 7px !important;
}
.selectTipoProyecto,
#tipo_proyecto,
#tipo_proyecto_cliente,
#filtroTipoProyectoCliente {
    min-width: 230px !important;
    max-width: 280px;
}
#cliente,
#filtroEmpresa {
    min-width: 100% !important;
    max-width: 100%;
}
.selectTipoProyecto:focus,
#tipo_proyecto:focus,
#tipo_proyecto_cliente:focus,
#filtroTipoProyectoCliente:focus,
#cliente:focus,
#filtroEmpresa:focus {
    outline: none;
    border-color: #000147 !important;
    box-shadow: 0 0 0 3px rgba(0, 1, 71, 0.08);
}
.selectTipoProyecto option,
#tipo_proyecto option,
#tipo_proyecto_cliente option,
#filtroTipoProyectoCliente option,
#cliente option,
#filtroEmpresa option {
    white-space: normal;
    padding: 0.35rem 0.5rem;
    font-size: 0.8125rem;
    line-height: 1.4;
    background: #fff;
}
td .selectTipoProyecto {
    display: block;
    margin: 0 auto;
}
.filter-pill[data-filter="tipo"] {
    white-space: nowrap;
    max-width: none;
    overflow: visible;
    text-overflow: unset;
    padding: 6px 14px;
    font-size: 12px;
}
#filtroTipoProyectoCliente {
    max-width: 260px;
}
/* En el toolbar de detalle_cliente el ancho lo define .dc-section-head */
.dc-section-head #filtroTipoProyectoCliente {
    width: 220px !important;
    min-width: 220px !important;
    max-width: 220px !important;
}
@media (max-width: 767.98px) {
    .selectTipoProyecto,
    #tipo_proyecto,
    #tipo_proyecto_cliente,
    #filtroTipoProyectoCliente,
    #cliente,
    #filtroEmpresa {
        min-width: 100% !important;
        max-width: 100%;
    }
    .dc-section-head #filtroTipoProyectoCliente {
        width: 100% !important;
        min-width: 0 !important;
        max-width: 100% !important;
    }
}
</style>
CSS;
    }
}
