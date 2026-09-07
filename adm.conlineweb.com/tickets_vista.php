<?php
require_once __DIR__.'/auth_middleware.php';
$admPageTitle = 'Vista de Tickets';
include_once 'menu.php';
include_once 'conn.php';
require_once __DIR__ . '/solicitudes/helpers_agentes.php';

function tickets_query_rows(mysqli $conn, string $sql): array
{
    $rows = [];
    $res = $conn->query($sql);
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $res->free();
    }

    return $rows;
}

function tickets_table_has_column(mysqli $conn, string $column): bool
{
    static $cache = [];
    if (!array_key_exists($column, $cache)) {
        $safe = $conn->real_escape_string($column);
        $check = $conn->query("SHOW COLUMNS FROM solicitudes LIKE '{$safe}'");
        $cache[$column] = $check instanceof mysqli_result && $check->num_rows > 0;
        if ($check instanceof mysqli_result) {
            $check->free();
        }
    }

    return $cache[$column];
}

function tickets_cliente_label(array $c): string
{
    $txt = '[' . (int) ($c['id'] ?? 0) . '] ';
    if (!empty($c['nombre_contacto'])) {
        $txt .= $c['nombre_contacto'] . ' - ';
    }
    $txt .= (string) ($c['empresa'] ?? '');

    return trim($txt);
}

function tickets_agentes_cell_html(mysqli $conn, string $agentesString): string
{
    if (trim($agentesString) === '') {
        return '<span class="tv-agent-empty">Sin asignar</span>';
    }

    $nombres = obtenerNombresAgentes($conn, $agentesString);
    if ($nombres === []) {
        return '<span class="tv-agent-empty">Sin asignar</span>';
    }

    $html = '<div class="tv-agentes">';
    foreach ($nombres as $nombre) {
        $html .= '<span class="tv-agent-pill">' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . '</span>';
    }
    $html .= '</div>';

    return $html;
}

// Parámetros GET
$idCliente = isset($_GET['id_cliente']) ? (int)$_GET['id_cliente'] : 0;
$idProyecto = isset($_GET['id_proyecto']) ? (int)$_GET['id_proyecto'] : 0;
$nombreMSJ = isset($_GET['nombreMSJ']) ? trim((string)$_GET['nombreMSJ']) : '';
$debugDesc  = isset($_GET['debug_desc']); // modo depuración de descripciones
$soloVista = isset($_GET['vista']); // modo vista de cliente

$hasNombreMsjCol = tickets_table_has_column($conn, 'nombreMSJ');
$hasUsuarioAsignadoCol = tickets_table_has_column($conn, 'usuario_asignado');

// Cargar clientes activos (login tipo 0, no eliminados)
$clientes = tickets_query_rows(
    $conn,
    "SELECT c.id, c.empresa, c.nombre_contacto
     FROM clientes c
     INNER JOIN login l ON l.id = c.id
     WHERE l.id_tipo_usuario = 0
       AND c.eliminado = 0
     ORDER BY c.id ASC"
);
if ($idCliente > 0) {
    $clientePermitido = false;
    foreach ($clientes as $c) {
        if ((int) $c['id'] === $idCliente) {
            $clientePermitido = true;
            break;
        }
    }
    if (!$clientePermitido) {
        $idCliente = 0;
        $idProyecto = 0;
        $nombreMSJ = '';
    }
}
$proyectosCliente = [];
if ($idCliente > 0) {
    $proyectosCliente = tickets_query_rows(
        $conn,
        'SELECT id_proyecto, nombre_proyecto FROM proyectos WHERE id_cliente = ' . (int) $idCliente . ' ORDER BY nombre_proyecto ASC'
    );
}
$proyectosPorClienteJs = [];
foreach (tickets_query_rows($conn, 'SELECT id_proyecto, nombre_proyecto, id_cliente FROM proyectos ORDER BY nombre_proyecto ASC') as $proyRow) {
    $cidProy = (int) ($proyRow['id_cliente'] ?? 0);
    if ($cidProy <= 0) {
        continue;
    }
    if (!isset($proyectosPorClienteJs[$cidProy])) {
        $proyectosPorClienteJs[$cidProy] = [];
    }
    $proyectosPorClienteJs[$cidProy][] = [
        'id_proyecto' => (int) $proyRow['id_proyecto'],
        'nombre_proyecto' => (string) $proyRow['nombre_proyecto'],
    ];
}
$nombresMSJ = [];
if ($hasNombreMsjCol) {
    $nombresMSJ = array_column(
        tickets_query_rows($conn, "SELECT DISTINCT nombreMSJ FROM solicitudes WHERE nombreMSJ IS NOT NULL AND nombreMSJ <> '' ORDER BY nombreMSJ ASC"),
        'nombreMSJ'
    );
}

// Construir WHERE dinámico
$where = 'WHERE 1=1';
if ($idCliente > 0) {
    $where .= " AND s.id_cliente = $idCliente";
}
if ($idProyecto > 0) {
    $where .= " AND s.id_proyecto = $idProyecto";
}
if ($nombreMSJ !== '' && $hasNombreMsjCol) {
    $safeNombre = $conn->real_escape_string($nombreMSJ);
    $where .= " AND s.nombreMSJ LIKE '%$safeNombre%'";
}

$sqlTickets = "SELECT s.*, c.empresa AS empresa_nombre, c.nombre_contacto AS contacto_nombre, p.nombre_proyecto FROM solicitudes s ";
$sqlTickets .= "LEFT JOIN clientes c ON s.id_cliente=c.id ";
$sqlTickets .= "LEFT JOIN proyectos p ON s.id_proyecto=p.id_proyecto $where ORDER BY s.fecha_solicitud DESC";
$ticketsRows = tickets_query_rows($conn, $sqlTickets);
$ticketTotal = count($ticketsRows);

function format_es_datetime_local($datetime, $withTime = false) {
    if (!$datetime || $datetime === '0000-00-00 00:00:00') return '-';
    $timestamp = strtotime($datetime);
    if ($timestamp === false) return $datetime;
    if (class_exists('IntlDateFormatter')) {
        $pattern = $withTime ? "dd MMM yyyy HH:mm" : "dd MMM yyyy";
        $fmt = new IntlDateFormatter('es_ES', IntlDateFormatter::SHORT, $withTime ? IntlDateFormatter::SHORT : IntlDateFormatter::NONE);
        $fmt->setPattern($pattern);
        $result = $fmt->format($timestamp);
        if ($result !== false) return mb_strtolower($result);
    }
    return date($withTime ? 'd/m/Y H:i' : 'd/m/Y', $timestamp);
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
<link href="css/admin-consultas.css?v=20250714" rel="stylesheet">
<link href="css/admin-datatables.css?v=20250714c" rel="stylesheet">
<link href="css/tickets-vista.css?v=3" rel="stylesheet">
</head>
<body>
<div id="content-wrapper" class="d-flex flex-column">
<div id="content">
<div class="container-fluid tv-page legacy-touch px-4 py-4">

<div class="tv-hero mb-4 fade-in-up">
    <div>
        <h1 class="tv-hero__title">
            <i class="fas fa-ticket-alt me-2"></i>Vista de Tickets
            <?php if($soloVista): ?><span class="tv-view-badge"><i class="fas fa-eye"></i> Vista cliente</span><?php endif; ?>
            <?php if($debugDesc): ?><span class="tv-view-badge" style="background:#d63384">Debug</span><?php endif; ?>
        </h1>
        <p class="tv-hero__sub">Consulta y filtra solicitudes por cliente, proyecto y nombre MSJ.</p>
    </div>
    <button type="button" class="tv-btn tv-btn--primary" id="refreshBtn"><i class="fas fa-sync-alt"></i> Actualizar</button>
</div>

    <?php if(!$soloVista): ?>
    <div class="tv-panel tv-panel--filters mb-4 fade-in-up">
        <form id="filtrosForm" onsubmit="return false;">
            <div class="tv-filters">
                <div class="tv-field">
                    <label for="selectCliente"><i class="fas fa-building"></i> Cliente / Empresa</label>
                    <select id="selectCliente" name="id_cliente">
                        <option value="0">-- Todos --</option>
                        <?php foreach ($clientes as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= $idCliente === (int) $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars(tickets_cliente_label($c), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="tv-field">
                    <label for="selectProyecto"><i class="fas fa-project-diagram"></i> Proyecto</label>
                    <select id="selectProyecto" name="id_proyecto" <?= $idCliente === 0 ? 'disabled' : '' ?>>
                        <option value="0">-- Todos --</option>
                        <?php foreach ($proyectosCliente as $p): ?>
                            <option value="<?= (int) $p['id_proyecto'] ?>" <?= $idProyecto === (int) $p['id_proyecto'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $p['nombre_proyecto'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($hasNombreMsjCol): ?>
                <div class="tv-field" id="nombreMSJGroup" style="<?= ($idCliente > 0 && $nombreMSJ !== '') ? '' : 'display:none;' ?>">
                    <label for="selectNombreMSJ"><i class="fas fa-tag"></i> Nombre MSJ</label>
                    <select id="selectNombreMSJ" name="nombreMSJ">
                        <option value="">-- Todos --</option>
                        <?php foreach ($nombresMSJ as $nm): ?>
                            <option value="<?= htmlspecialchars($nm, ENT_QUOTES) ?>" <?= ($nombreMSJ !== '' && $nombreMSJ === $nm) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($nm) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="tv-field">
                    <label>&nbsp;</label>
                    <div class="tv-actions">
                        <button type="button" class="tv-btn tv-btn--primary" id="aplicarBtn"><i class="fas fa-filter"></i> Aplicar</button>
                        <button type="button" class="tv-btn tv-btn--ghost" id="limpiarBtn"><i class="fas fa-undo-alt"></i> Limpiar</button>
                    </div>
                </div>
            </div>
        </form>
        <div class="tv-link-bar">
            <strong><i class="fas fa-link"></i> Link actual</strong>
            <span id="linkText"></span>
            <button type="button" class="tv-copy-btn" id="copyLink"><i class="fas fa-copy"></i> Copiar</button>
            <button type="button" class="tv-copy-btn" id="secureLinkBtn"><i class="fas fa-lock"></i> Link seguro</button>
        </div>
    </div>
    <?php endif; ?>

    <div class="tv-panel tv-panel--table fade-in-up">
        <div class="tv-panel__head">
            <div>
                <h2 class="tv-panel__title"><i class="fas fa-list me-2"></i>Listado de tickets</h2>
                <div class="tv-panel__meta">Resultados según los filtros aplicados</div>
            </div>
            <span class="tv-count-badge"><?= (int) $ticketTotal ?> ticket<?= $ticketTotal === 1 ? '' : 's' ?></span>
        </div>
        <div class="tv-table-wrap table-responsive">
            <table id="tablaTickets" class="table table-hover w-100">
                <thead>
                 <tr>
                    <th>ID</th>
                    <th>Título</th>
                    <th>Contacto</th>
                    <th>Empresa</th>
                    <th>Proyecto</th>
                    <th>Agente</th>
                    <th>Descripción</th>
                    <th>Estado</th>
                    <th>Prioridad</th>
                    <th>Inicio</th>
                    <th>Fecha Límite</th>
                    <th>Completado</th>
                    <th>Acciones</th>
                 </tr>
                </thead>
                <tbody>
                <?php foreach ($ticketsRows as $t):
                        $descData = json_decode($t['descripcion'], true);
                        $descText = isset($descData['text']) ? $descData['text'] : $t['descripcion'];
                        $imgs = isset($descData['images']) ? $descData['images'] : [];
                        $files = isset($descData['files']) ? $descData['files'] : [];
                        $imgsJson = htmlspecialchars(json_encode($imgs), ENT_QUOTES);
                        $filesJson = htmlspecialchars(json_encode($files), ENT_QUOTES);
                        $estadoBadgeClass = $t['estado'] === 'Pendiente' ? 'tv-badge--pendiente' : ($t['estado'] === 'En Proceso' ? 'tv-badge--proceso' : 'tv-badge--finalizado');
                        $prioClass = $t['prioridad'] === 'Alta' ? 'tv-badge--alta' : ($t['prioridad'] === 'Media' ? 'tv-badge--media' : 'tv-badge--baja');
                        $agenteHtml = $hasUsuarioAsignadoCol
                            ? tickets_agentes_cell_html($conn, (string) ($t['usuario_asignado'] ?? ''))
                            : '<span class="tv-agent-empty">-</span>';
                        $descB64 = base64_encode((string) $descText);

                        echo '<tr data-idcliente="'.(int)$t['id_cliente'].'" data-idproyecto="'.(int)$t['id_proyecto'].'" data-nombremsj="'.htmlspecialchars($t['nombreMSJ'] ?? '', ENT_QUOTES).'">';
                        echo '<td class="tv-cell-id">#'.(int)$t['id'].'</td>';
                        echo '<td class="tv-cell-title">'.htmlspecialchars($t['titulo']).'</td>';
                        echo '<td>'.htmlspecialchars($t['contacto_nombre']??'-').'</td>';
                        echo '<td>'.htmlspecialchars($t['empresa_nombre']??'-').'</td>';
                        echo '<td>'.htmlspecialchars($t['nombre_proyecto']??'-').'</td>';
                        echo '<td class="tv-cell-agent">'.$agenteHtml.'</td>';
                        echo '<td><div class="tv-desc-short">'.htmlspecialchars(mb_strimwidth(strip_tags(preg_replace('/<\\/?strong>/i','',$descText)),0,60,'...')).'</div></td>';
                        echo '<td><span class="tv-badge '.$estadoBadgeClass.'">'.htmlspecialchars($t['estado']).'</span></td>';
                        echo '<td><span class="tv-badge '.$prioClass.'">'.htmlspecialchars($t['prioridad']).'</span></td>';
                        echo '<td>'.htmlspecialchars(format_es_datetime_local($t['fecha_solicitud'], true)).'</td>';
                        echo '<td>'.htmlspecialchars(format_es_datetime_local($t['fecha_lim'], true)).'</td>';
                        echo '<td>'.htmlspecialchars(format_es_datetime_local($t['fecha_termina'], true)).'</td>';
                        echo '<td><button type="button" class="tv-btn-view ver-desc" data-desc="'.htmlspecialchars($descText,ENT_QUOTES).'" data-desc64="'.$descB64.'" data-images="'.$imgsJson.'" data-files="'.$filesJson.'" title="Ver descripción completa"><i class="fas fa-eye"></i> Ver</button></td>';
                        echo '</tr>';
                endforeach;
                ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const baseUrl = window.location.origin + window.location.pathname;
const DEBUG_DESC = <?php echo $debugDesc ? 'true' : 'false'; ?>;
const PROYECTOS_POR_CLIENTE = <?= json_encode($proyectosPorClienteJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const selectCliente = document.getElementById('selectCliente');
const selectProyecto = document.getElementById('selectProyecto');
const selectNombreMSJ = document.getElementById('selectNombreMSJ');
const linkText = document.getElementById('linkText');
const aplicarBtn = document.getElementById('aplicarBtn');
const limpiarBtn = document.getElementById('limpiarBtn');
const copyLink = document.getElementById('copyLink');
const secureLinkBtn = document.getElementById('secureLinkBtn');
let dtTickets = null;
let currentClient = <?= (int) $idCliente ?>;
let currentProject = <?= (int) $idProyecto ?>;
let currentNombreMSJ = <?= json_encode($nombreMSJ, JSON_UNESCAPED_UNICODE) ?>;
let lastLoadedClientId = currentClient > 0 ? currentClient : null;

function getClienteIdFromInput() {
    return selectCliente ? (parseInt(selectCliente.value, 10) || 0) : 0;
}

function setProyectoOptions(proyectos, selectedId) {
    if (!selectProyecto) {
        return;
    }
    selectProyecto.innerHTML = '<option value="0">-- Todos --</option>';
    if (!Array.isArray(proyectos) || !proyectos.length) {
        const opt = document.createElement('option');
        opt.value = '0';
        opt.textContent = '(Sin proyectos)';
        selectProyecto.appendChild(opt);
        selectProyecto.value = '0';
        currentProject = 0;
        return;
    }
    proyectos.forEach(p => {
        const opt = document.createElement('option');
        opt.value = String(p.id_proyecto);
        opt.textContent = p.nombre_proyecto;
        selectProyecto.appendChild(opt);
    });
    const wanted = selectedId || 0;
    if (wanted > 0 && selectProyecto.querySelector('option[value="' + wanted + '"]')) {
        selectProyecto.value = String(wanted);
        currentProject = wanted;
    } else {
        selectProyecto.value = '0';
        currentProject = 0;
    }
}

function loadProyectosForCliente(cid, selectedId) {
    if (!selectProyecto) {
        return;
    }
    selectProyecto.disabled = false;
    selectProyecto.removeAttribute('disabled');
    const list = PROYECTOS_POR_CLIENTE[cid] || PROYECTOS_POR_CLIENTE[String(cid)] || [];
    setProyectoOptions(list, selectedId);
}

function onClienteChange() {
    const cid = getClienteIdFromInput();
    const clientChanged = lastLoadedClientId !== null && lastLoadedClientId !== cid;
    currentClient = cid;

    if (cid === 0) {
        lastLoadedClientId = null;
        currentProject = 0;
        currentNombreMSJ = '';
        if (selectNombreMSJ) {
            selectNombreMSJ.value = '';
        }
        if (selectProyecto) {
            selectProyecto.innerHTML = '<option value="0">-- Todos --</option>';
            selectProyecto.value = '0';
            selectProyecto.disabled = true;
        }
        updateNombreMSJOptions();
        applyFrontendFilter();
        buildLink();
        return;
    }

    if (clientChanged) {
        currentProject = 0;
        currentNombreMSJ = '';
        if (selectNombreMSJ) {
            selectNombreMSJ.value = '';
        }
    }

    lastLoadedClientId = cid;
    loadProyectosForCliente(cid, clientChanged ? 0 : currentProject);
    updateNombreMSJOptions();
    applyFrontendFilter();
    buildLink();
}

function navigateWithCurrentFilters() {
    const params = new URLSearchParams();
    const cid = getClienteIdFromInput();
    const pid = parseInt(selectProyecto?.value, 10) || 0;
    const n = (selectNombreMSJ && selectNombreMSJ.value) ? selectNombreMSJ.value.trim() : '';
    if (cid > 0) params.set('id_cliente', String(cid));
    if (pid > 0) params.set('id_proyecto', String(pid));
    if (n) params.set('nombreMSJ', n);
    const url = params.toString() ? (baseUrl + '?' + params.toString()) : baseUrl;
    window.location.href = url;
}

// Plugin de búsqueda personalizada
$.fn.dataTable.ext.search.push(function(settings, data, dataIndex){
    if(!dtTickets || settings.nTable.id !== 'tablaTickets') return true;
    const tr = settings.aoData[dataIndex].nTr;
    const idCli = parseInt(tr.getAttribute('data-idcliente')) || 0;
    const idPro = parseInt(tr.getAttribute('data-idproyecto')) || 0;
    const nmsj = (tr.getAttribute('data-nombremsj')||'').toLowerCase();
    if(currentClient > 0 && idCli !== currentClient) return false;
    if(currentProject > 0 && idPro !== currentProject) return false;
    if(currentNombreMSJ && !nmsj.includes(currentNombreMSJ.toLowerCase())) return false;
    return true;
});

function applyFrontendFilter(){
    if(!dtTickets) return;
    dtTickets.draw();
}

function updateNombreMSJOptions() {
    if (!selectNombreMSJ) return;
    selectNombreMSJ.innerHTML = '<option value="">-- Todos --</option>';
    if (currentClient === 0) {
        const nombreMSJGroup = document.getElementById('nombreMSJGroup');
        if (nombreMSJGroup) nombreMSJGroup.style.display = 'none';
        return;
    }
    const visibleRows = document.querySelectorAll('#tablaTickets tbody tr');
    const nombresMSJSet = new Set();
    visibleRows.forEach(row => {
        const rowCliente = parseInt(row.getAttribute('data-idcliente')) || 0;
        const rowProyecto = parseInt(row.getAttribute('data-idproyecto')) || 0;
        const rowNombreMSJ = (row.getAttribute('data-nombremsj') || '').trim();
        let matches = true;
        if (currentClient > 0 && rowCliente !== currentClient) matches = false;
        if (currentProject > 0 && rowProyecto !== currentProject) matches = false;
        if (matches && rowNombreMSJ) nombresMSJSet.add(rowNombreMSJ);
    });
    const nombresMSJArray = Array.from(nombresMSJSet).sort();
    const nombreMSJGroup = document.getElementById('nombreMSJGroup');
    if (nombresMSJArray.length === 0) {
        if (nombreMSJGroup) nombreMSJGroup.style.display = 'none';
        return;
    }
    if (nombreMSJGroup) nombreMSJGroup.style.display = 'flex';
    nombresMSJArray.forEach(nombre => {
        const option = document.createElement('option');
        option.value = nombre;
        option.textContent = nombre;
        selectNombreMSJ.appendChild(option);
    });
    if (currentNombreMSJ && nombresMSJArray.includes(currentNombreMSJ)) {
        selectNombreMSJ.value = currentNombreMSJ;
    }
}

function buildLink() {
    if(!linkText) return;
    const c = getClienteIdFromInput();
    const p = parseInt(selectProyecto?.value)||0;
    const n = (selectNombreMSJ && selectNombreMSJ.value) ? selectNombreMSJ.value.trim() : '';
    const params = new URLSearchParams();
    if (c>0) params.set('id_cliente', c);
    if (p>0) params.set('id_proyecto', p);
    if (n) params.set('nombreMSJ', n);
    const full = params.toString() ? baseUrl + '?' + params.toString() : baseUrl;
    linkText.textContent = full;
}

async function buildSecureLink(){
    const c = getClienteIdFromInput();
    const p = parseInt(selectProyecto?.value, 10) || 0;
    const n = (selectNombreMSJ && selectNombreMSJ.value) ? selectNombreMSJ.value.trim() : '';
    if (c === 0) { Swal.fire('Aviso', 'Selecciona un cliente primero', 'info'); return; }
    try {
        const form = new FormData();
        form.append('id_cliente', c);
        if (p > 0) form.append('id_proyecto', p);
        if (n) form.append('nombreMSJ', n);
        const r = await fetch('tickets_vista_link_token.php', { method:'POST', body:form });
        const data = await r.json();
        if (!data.success) { Swal.fire('Error', data.message || 'Error generando token', 'error'); return; }
        const url = window.location.origin + window.location.pathname.replace(/tickets_vista\.php$/,'link_view.php') + '?token=' + encodeURIComponent(data.token);
        navigator.clipboard.writeText(url);
        Swal.fire('¡Listo!', 'Link seguro copiado al portapapeles', 'success');
    } catch (err) { console.error(err); Swal.fire('Error', 'Fallo al generar link', 'error'); }
}

if (selectCliente) {
    selectCliente.addEventListener('change', onClienteChange);
}

if (selectProyecto) {
    selectProyecto.addEventListener('change', () => {
        currentProject = parseInt(selectProyecto.value, 10) || 0;
        currentNombreMSJ = '';
        if (selectNombreMSJ) {
            selectNombreMSJ.value = '';
        }
        updateNombreMSJOptions();
        applyFrontendFilter();
        buildLink();
    });
}

if (selectNombreMSJ) {
    selectNombreMSJ.addEventListener('change', () => {
        currentNombreMSJ = selectNombreMSJ.value.trim();
        applyFrontendFilter();
        buildLink();
    });
}

if(copyLink){
    copyLink.addEventListener('click', () => {
        const txt = linkText.textContent.trim();
        if (!txt) return;
        navigator.clipboard.writeText(txt);
        Swal.fire('Copiado', 'Link copiado al portapapeles', 'success');
    });
}
if (secureLinkBtn) secureLinkBtn.addEventListener('click', buildSecureLink);
if (aplicarBtn) aplicarBtn.addEventListener('click', navigateWithCurrentFilters);
if (limpiarBtn) limpiarBtn.addEventListener('click', () => {
    if (selectCliente) selectCliente.value = '0';
    if (selectProyecto) {
        selectProyecto.innerHTML = '<option value="0">-- Todos --</option>';
        selectProyecto.disabled = true;
        selectProyecto.value = '0';
    }
    if (selectNombreMSJ) selectNombreMSJ.value = '';
    currentClient = 0;
    currentProject = 0;
    currentNombreMSJ = '';
    lastLoadedClientId = null;
    updateNombreMSJOptions();
    applyFrontendFilter();
    buildLink();
    window.location.href = baseUrl;
});

function decodeVisibleEscapes(str){ return (str||'').replace(/\\r\\n/g,'\n').replace(/\\n/g,'\n').replace(/\\r/g,'\n'); }
function escapeHtml(str){ return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }
function formatDescriptionText(raw){
    const text = decodeVisibleEscapes(raw||'').trim();
    const collapsed = text.replace(/\n{3,}/g,'\n\n');
    const lines = collapsed.split('\n');
    let html=''; let inList=false; let para=[];
    function flushPara(){ if(para.length){ const c = para.join('<br>'); html += `<p>${c}</p>`; para=[]; } }
    for(const rawLine of lines){
        const line = rawLine.trim();
        if(!line){ flushPara(); if(inList){ html+='</ul>'; inList=false;} continue; }
        if(/^[-*•]\s+/.test(line)){ flushPara(); if(!inList){ html+='<ul>'; inList=true;} const body=line.replace(/^[-*•]\s+/,''); const m=body.match(/^([^:]{2,}):\s*(.*)$/); if(m){ html+=`<li><strong>${escapeHtml(m[1])}:</strong> ${escapeHtml(m[2])}</li>`; } else { html+=`<li>${escapeHtml(body)}</li>`; } }
        else { if(inList){ html+='</ul>'; inList=false;} const m=line.match(/^([^:]{2,}):\s*(.*)$/); if(m){ para.push(`<strong>${escapeHtml(m[1])}:</strong> ${escapeHtml(m[2])}`); } else { para.push(escapeHtml(line)); } }
    }
    flushPara(); if(inList) html+='</ul>';
    return html || '<p><em>Sin descripción</em></p>';
}

// Modal para descripción
let DESC_MODAL = null, DESC_IMG_GRID=null, DESC_FILES=null;
let LIGHTBOX=null, LB_IMG=null, LB_COUNTER=null, LB_DOWNLOAD=null, LB_PREV=null, LB_NEXT=null, LB_CLOSE=null;
let lightboxList=[]; let lightboxIndex=0;
let currentThumbResolvedList=[];

function ensureDescModal(){
    if(DESC_MODAL) return;
    const html = `<div class="modal-overlay-desc" id="descModalOverlay"><div class="modal-desc"><div class="modal-desc-header"><h3 class="modal-desc-title"><i class="fas fa-align-left"></i> Descripción Completa</h3><button class="modal-desc-close" id="descModalClose">&times;</button></div><div class="modal-desc-body"><div class="descripcion-completa" id="descModalText"></div><div class="desc-images-grid" id="descModalImages"></div><div class="desc-files" id="descModalFiles"></div></div><div class="modal-desc-footer"><button class="btn-close-desc" id="descModalBtnCerrar">Cerrar</button></div></div></div><div class="lightbox-layer" id="descLightbox"><div class="lightbox-stage"><button class="lightbox-nav-btn lightbox-prev" id="lbPrev">◀</button><img id="lbImg" src="" alt="Imagen" /><button class="lightbox-nav-btn lightbox-next" id="lbNext">▶</button><button class="lightbox-close-btn" id="lbClose">&times;</button><a class="lightbox-download" id="lbDownload" href="#" download><i class='fas fa-download'></i> Descargar</a></div><div class="lightbox-counter" id="lbCounter"></div></div>`;
    document.body.insertAdjacentHTML('beforeend', html);
    DESC_MODAL = document.getElementById('descModalOverlay');
    DESC_IMG_GRID = document.getElementById('descModalImages');
    DESC_FILES = document.getElementById('descModalFiles');
    LIGHTBOX=document.getElementById('descLightbox');
    LB_IMG=document.getElementById('lbImg');
    LB_COUNTER=document.getElementById('lbCounter');
    LB_DOWNLOAD=document.getElementById('lbDownload');
    LB_PREV=document.getElementById('lbPrev');
    LB_NEXT=document.getElementById('lbNext');
    LB_CLOSE=document.getElementById('lbClose');
    document.getElementById('descModalClose').onclick=closeDescModal;
    document.getElementById('descModalBtnCerrar').onclick=closeDescModal;
    DESC_MODAL.addEventListener('click', e=>{ if(e.target===DESC_MODAL) closeDescModal(); });
    LB_CLOSE.onclick=closeLightbox;
    LIGHTBOX.addEventListener('click', e=>{ if(e.target===LIGHTBOX) closeLightbox(); });
    LB_PREV.onclick=()=>navigateLightbox(-1);
    LB_NEXT.onclick=()=>navigateLightbox(1);
    document.addEventListener('keydown', e=>{
        if(!DESC_MODAL?.classList.contains('active')) return;
        if(e.key==='Escape') closeDescModal();
    });
}
function openDescModal(desc, images, files){
    ensureDescModal();
    document.getElementById('descModalText').innerHTML = formatDescriptionText(desc||'');
    DESC_IMG_GRID.innerHTML='';
    if(Array.isArray(images) && images.length){
        images.forEach((raw,i)=>{
            const div=document.createElement('div');
            div.className='desc-thumb';
            div.title=raw.split('/').pop();
            const img=document.createElement('img');
            img.loading='lazy';
            img.src=raw;
            img.onerror=function(){
                if(!this.dataset.alt){
                    this.dataset.alt=1;
                    if(this.src.includes('/uploads/solicitudes/')) this.src=this.src.replace('/uploads/solicitudes/','/solicitudes/uploads/solicitudes/');
                    else if(this.src.includes('/solicitudes/uploads/solicitudes/')) this.src=this.src.replace('/solicitudes/uploads/solicitudes/','/uploads/solicitudes/');
                }
            };
            img.onload=function(){ buildResolvedList(); };
            div.appendChild(img);
            const fn=document.createElement('span'); fn.className='thumb-filename'; fn.textContent=(raw.split('/').pop()||'').substring(0,12); div.appendChild(fn);
            div.addEventListener('click',()=>openLightbox(i));
            DESC_IMG_GRID.appendChild(div);
        });
        buildResolvedList();
    }
    DESC_FILES.innerHTML='';
    if(Array.isArray(files) && files.length){
        const title=document.createElement('div'); title.style.fontSize='.7rem'; title.style.fontWeight='600'; title.style.margin='4px 0 8px'; title.innerHTML='<i class="fas fa-paperclip"></i> ARCHIVOS ADJUNTOS:'; DESC_FILES.appendChild(title);
        files.forEach(f=>{ const item=document.createElement('div'); item.className='desc-files-item'; item.innerHTML=`<i class="fas fa-file"></i> <a href="${f}" target="_blank" rel="noopener">${(f.split('/').pop())}</a>`; DESC_FILES.appendChild(item); });
    }
    DESC_MODAL.classList.add('active');
    document.body.style.overflow='hidden';
}
function closeDescModal(){ if(DESC_MODAL){ DESC_MODAL.classList.remove('active'); document.body.style.overflow='auto'; } }
function buildResolvedList(){ currentThumbResolvedList = Array.from(DESC_IMG_GRID.querySelectorAll('img')).map(im=>im.currentSrc || im.src); }
function openLightbox(idx){
    ensureDescModal();
    if(!currentThumbResolvedList.length) buildResolvedList();
    lightboxList = currentThumbResolvedList.slice();
    lightboxIndex=idx;
    renderLightbox();
    LIGHTBOX.classList.add('active');
    document.addEventListener('keydown', lbKey);
}
function closeLightbox(){ if(LIGHTBOX){ LIGHTBOX.classList.remove('active'); document.removeEventListener('keydown', lbKey);} }
function lbKey(e){ if(e.key==='Escape') return closeLightbox(); if(e.key==='ArrowLeft') return navigateLightbox(-1); if(e.key==='ArrowRight') return navigateLightbox(1); }
function navigateLightbox(d){ lightboxIndex+=d; if(lightboxIndex<0) lightboxIndex=lightboxList.length-1; if(lightboxIndex>=lightboxList.length) lightboxIndex=0; renderLightbox(); }
function renderLightbox(){
    const rawUrl=lightboxList[lightboxIndex];
    LB_IMG.style.opacity='0';
    LB_IMG.onload=function(){ LB_IMG.style.opacity='1'; LB_DOWNLOAD.href=LB_IMG.currentSrc || LB_IMG.src; LB_DOWNLOAD.download=( (LB_IMG.currentSrc||LB_IMG.src).split('/').pop()||'' ); };
    LB_IMG.onerror=function(){
        if(!this.dataset.alt){
            this.dataset.alt=1;
            if(this.src.includes('/uploads/solicitudes/')) this.src=this.src.replace('/uploads/solicitudes/','/solicitudes/uploads/solicitudes/');
            else if(this.src.includes('/solicitudes/uploads/solicitudes/')) this.src=this.src.replace('/solicitudes/uploads/solicitudes/','/uploads/solicitudes/');
        } else {
            this.style.display='none';
            if(!document.getElementById('lbErrorMsg')){
                const msg=document.createElement('div');
                msg.id='lbErrorMsg';
                msg.style.color='#eee';
                msg.style.fontSize='.8rem';
                msg.style.marginTop='10px';
                msg.textContent='Imagen no disponible';
                LB_IMG.parentElement.appendChild(msg);
            }
        }
    };
    LB_IMG.style.display='block';
    const errNode=document.getElementById('lbErrorMsg'); if(errNode) errNode.remove();
    LB_IMG.src=rawUrl;
    LB_COUNTER.textContent=`${lightboxIndex+1} / ${lightboxList.length}`;
    LB_DOWNLOAD.href=rawUrl;
    LB_DOWNLOAD.download=(rawUrl.split('/').pop()||'');
    LB_PREV.style.display= lightboxList.length>1? 'flex':'none';
    LB_NEXT.style.display= lightboxList.length>1? 'flex':'none';
}

$(document).on('click', '.ver-desc', function(e){
    e.preventDefault();
    const btn = this;
    let desc = btn.getAttribute('data-desc') || '';
    if(!desc){
        const b64 = btn.getAttribute('data-desc64');
        if(b64){
            try { desc = decodeURIComponent(escape(atob(b64))); } catch(err){ try { desc = atob(b64); } catch(_e){} }
        }
    }
    if(!desc){
        const cell = btn.closest('td');
        if(cell){ desc = cell.textContent.trim(); }
    }
    let imgs = [], files=[];
    try { imgs = JSON.parse(btn.getAttribute('data-images')||'[]'); } catch(e){}
    try { files = JSON.parse(btn.getAttribute('data-files')||'[]'); } catch(e){}
    openDescModal(desc, imgs, files);
});

$(document).ready(function(){
    dtTickets = $('#tablaTickets').DataTable({
        scrollX: true,
        dom:'Blfrtip',
        buttons:[{ extend:'excelHtml5', text:'<i class="fas fa-file-excel"></i> Exportar a Excel', className:'btnExport' }],
        lengthMenu:[[10,25,50,100,-1],[10,25,50,100,'Todos']],
        pageLength:25,
        language:{ url:'//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
        order:[[0,'desc']]
    });
    currentClient = <?= (int) $idCliente ?> || 0;
    currentProject = <?= (int) $idProyecto ?> || 0;
    currentNombreMSJ = <?= json_encode($nombreMSJ, JSON_UNESCAPED_UNICODE) ?> || '';
    if (currentClient > 0 && selectProyecto) {
        selectProyecto.disabled = false;
        selectProyecto.removeAttribute('disabled');
        if (selectProyecto.options.length <= 1) {
            loadProyectosForCliente(currentClient, currentProject);
        }
    }
    updateNombreMSJOptions();
    if (currentClient > 0 || currentProject > 0 || currentNombreMSJ) {
        dtTickets.draw();
    }
    buildLink();
    if (<?= (int) $idCliente ?> > 0 && <?= $hasNombreMsjCol ? 'true' : 'false' ?>) {
        updateNombreMSJOptions();
    }
    
    $('#refreshBtn').on('click', function(){ location.reload(); });

    $(window).on('resize.admTicketsDt', function () {
        if (dtTickets) dtTickets.columns.adjust();
    });
});
</script>
</body>
</html>