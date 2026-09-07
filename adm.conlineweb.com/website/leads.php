<?php
require_once dirname(__DIR__) . '/conn.php';
require_once dirname(__DIR__) . '/includes/cw_hub_config.php';
require_once dirname(__DIR__) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__) . '/includes/cw_hub_analytics.php';
require_once dirname(__DIR__) . '/includes/adm_paths.php';
require_once dirname(__DIR__) . '/website/includes/leads_helpers.php';

cw_hub_migrate($conn);

$pipelineOpts = CW_HUB_WEBSITE_PIPELINE;
$servicios = CW_HUB_SERVICIOS;
$filters = cw_web_lead_filter_params();
$period = $filters['period'];
$from = $filters['from'];
$to = $filters['to'];
$dateFrom = $filters['dateFrom'];
$dateTo = $filters['dateTo'];
$statusFilter = $filters['status'];
$origenFilter = $filters['origen'];
$origenOpts = cw_web_lead_origen_options();

$leadStats = cw_web_lead_stats($conn, $dateFrom, $dateTo, $statusFilter, $origenFilter);

$admPageTitle = 'Leads Website';

include dirname(__DIR__) . '/menu.php';
?>
<link href="<?= adm_href('vendor/datatables/dataTables.bootstrap4.min.css') ?>" rel="stylesheet">

<div class="adm-page-shell">
<div class="container-fluid px-0">
<?php
$websiteTab = 'leads';
$websiteHeroTitle = 'Leads Website';
$websiteHeroSub = 'Contactos del modal WhatsApp · nombre, apellido, empresa, servicio y seguimiento';
$websiteShowPeriod = true;
$websiteStatusFilter = $statusFilter;
$websiteOrigenFilter = $origenFilter;
require dirname(__DIR__) . '/includes/website_module_shell.php';
?>

<form method="get" class="lw-filters" id="lwFilters" aria-label="Filtros de leads">
  <input type="hidden" name="period" value="<?= htmlspecialchars($period) ?>">
  <?php if ($period === 'custom' && $from && $to): ?>
  <input type="hidden" name="from" value="<?= htmlspecialchars($from) ?>">
  <input type="hidden" name="to" value="<?= htmlspecialchars($to) ?>">
  <?php endif; ?>
  <div class="lw-filters-inner">
    <label for="filterStatus" class="lw-filters-label"><i class="bi bi-funnel"></i> Estatus</label>
    <select name="status" id="filterStatus" class="lw-filter-select">
      <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>Todos los estatus</option>
      <?php foreach ($pipelineOpts as $key => $label): ?>
      <option value="<?= htmlspecialchars($key) ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
      <?php endforeach; ?>
    </select>
    <label for="filterOrigen" class="lw-filters-label ml-md-2"><i class="bi bi-signpost-split"></i> Origen</label>
    <select name="origen" id="filterOrigen" class="lw-filter-select">
      <option value="" <?= $origenFilter === '' ? 'selected' : '' ?>>Todos los orígenes</option>
      <?php foreach ($origenOpts as $key => $label): ?>
      <option value="<?= htmlspecialchars($key) ?>" <?= $origenFilter === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
      <?php endforeach; ?>
    </select>
    <?php
    $lwBaseQs = 'period=' . urlencode($period)
        . ($period === 'custom' && $from && $to ? '&from=' . urlencode($from) . '&to=' . urlencode($to) : '');
    ?>
    <?php if ($statusFilter !== '' || $origenFilter !== ''): ?>
    <a href="<?= adm_href('website/leads.php') ?>?<?= $lwBaseQs ?>" class="lw-filter-clear">Limpiar filtros</a>
    <?php endif; ?>
    <span class="lw-filters-hint ml-auto d-none d-md-inline">Periodo, estatus y origen aplican a KPIs y tabla</span>
  </div>
</form>

<section class="row lw-kpi-grid mx-0" aria-label="Resumen de leads">
  <div class="col-3 px-2 mb-3 mb-md-0">
    <article class="lw-kpi lw-kpi-total h-100">
      <div class="label">Total registros</div>
      <div class="num"><?= number_format($leadStats['total']) ?></div>
    </article>
  </div>
  <div class="col-3 px-2 mb-3 mb-md-0">
    <article class="lw-kpi lw-kpi-lead h-100">
      <div class="label">Lead</div>
      <div class="num"><?= number_format($leadStats['lead']) ?></div>
    </article>
  </div>
  <div class="col-3 px-2 mb-3 mb-md-0">
    <article class="lw-kpi lw-kpi-calificado h-100">
      <div class="label">Calificado</div>
      <div class="num"><?= number_format($leadStats['calificado']) ?></div>
    </article>
  </div>
  <div class="col-3 px-2">
    <article class="lw-kpi lw-kpi-cierre h-100">
      <div class="label">Cierre</div>
      <div class="num"><?= number_format($leadStats['cierre']) ?></div>
    </article>
  </div>
</section>

<article class="va-panel">
  <div class="va-panel-head">
    <h2><i class="bi bi-table mr-1"></i> Registros del sitio web</h2>
    <div class="va-panel-actions d-flex align-items-center flex-wrap gap-2">
      <button type="button" id="btnNuevoLead" class="btn btn-sm btn-primary">
        <i class="bi bi-person-plus"></i> Registro manual
      </button>
      <a href="#" id="btnOpenInbox" class="btn btn-sm btn-outline-primary lw-btn-inbox-main disabled" title="Selecciona un lead en la tabla" aria-disabled="true">
        <i class="bi bi-inbox-fill"></i> Bandeja CRM
      </a>
      <button type="button" id="btnDeleteLead" class="btn btn-sm btn-outline-danger lw-btn-delete-main disabled" title="Marca uno o más leads para dar de baja" disabled>
        <i class="bi bi-trash"></i> Dar de baja <span id="btnDeleteLeadCount" class="d-none"></span>
      </button>
      <span class="text-muted small d-none d-md-inline">Lead → Calificado → Cierre</span>
    </div>
  </div>
  <div class="va-panel-body va-table-wrap p-0">
    <table class="va-table w-100" id="webLeadsTable">
      <thead>
        <tr>
          <th class="lw-col-check text-center" style="width:36px;">
            <input type="checkbox" id="lwCheckAll" class="lw-check-all" title="Seleccionar todos de esta página" aria-label="Seleccionar todos">
          </th>
          <th>ID</th>
          <th>Nombre</th>
          <th>Apellido</th>
          <th>Empresa</th>
          <th>Teléfono</th>
          <th>Correo</th>
          <th>Servicio</th>
          <th>Origen</th>
          <th>Web</th>
          <th>Página origen</th>
          <th>Fecha</th>
          <th>Estatus</th>
          <th>Última nota</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</article>

</div><!-- .va-shell -->
</div><!-- .container-fluid -->
</div><!-- .adm-page-shell -->

<!-- Registro manual -->
<div class="modal fade lw-modal" id="modalNuevoLead" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-person-plus mr-1"></i> Registro manual</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span>&times;</span></button>
      </div>
      <form id="formNuevoLead" novalidate>
        <div class="modal-body">
          <p class="text-muted small mb-3">Ningún campo es obligatorio. El lead se marcará como <strong>Registro manual</strong>.</p>
          <div class="form-row">
            <div class="form-group col-md-6">
              <label for="nuevoNombre">Nombre</label>
              <input type="text" class="form-control" id="nuevoNombre" name="nombre" maxlength="150" autocomplete="given-name">
            </div>
            <div class="form-group col-md-6">
              <label for="nuevoApellido">Apellido</label>
              <input type="text" class="form-control" id="nuevoApellido" name="apellido" maxlength="150" autocomplete="family-name">
            </div>
          </div>
          <div class="form-group">
            <label for="nuevoEmpresa">Empresa</label>
            <input type="text" class="form-control" id="nuevoEmpresa" name="empresa" maxlength="150" autocomplete="organization" placeholder="Nombre de la empresa">
          </div>
          <div class="form-row">
            <div class="form-group col-md-6">
              <label for="nuevoTelefono">Teléfono</label>
              <input type="tel" class="form-control" id="nuevoTelefono" name="telefono" maxlength="24" inputmode="tel" autocomplete="tel" placeholder="+52 477 340 0954">
              <small class="form-text text-muted">Pega el número como venga: +52, 52 o solo 10 dígitos.</small>
            </div>
            <div class="form-group col-md-6">
              <label for="nuevoCorreo">Correo</label>
              <input type="email" class="form-control" id="nuevoCorreo" name="correo" maxlength="180" autocomplete="email">
            </div>
          </div>
          <div class="form-group">
            <label for="nuevoServicio">Servicio de interés</label>
            <select class="form-control" id="nuevoServicio" name="servicio">
              <option value="">— Sin especificar —</option>
              <?php foreach ($servicios as $key => $label): ?>
              <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group mb-0">
            <label for="nuevoNota">Nota / requerimiento</label>
            <textarea class="form-control" id="nuevoNota" name="nota" rows="3" maxlength="2000" placeholder="Comentario inicial del lead…"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary" id="btnGuardarNuevoLead"><i class="bi bi-check-lg"></i> Guardar lead</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Detalle -->
<div class="modal fade lw-modal" id="modalDetalle" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-person-badge mr-1"></i> Detalle del lead</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span>&times;</span></button>
      </div>
      <div class="modal-body" id="detalleBody"></div>
    </div>
  </div>
</div>

<!-- Notas -->
<div class="modal fade lw-modal" id="modalNotas" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0"><i class="bi bi-chat-left-text mr-1"></i> Notas de seguimiento</h5>
          <p class="lw-modal-lead-name mb-0"><span id="notasLeadNombre"></span> · ID <span id="notasLeadIdLabel"></span></p>
        </div>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="lw-notas-section">
          <h6>Historial</h6>
          <div id="notasHistorial" class="lw-notas-scroll"></div>
        </div>
        <form id="formNota" class="lw-notas-form border-top pt-3">
          <input type="hidden" id="notaLeadId" name="id">
          <label for="nuevaNota" class="font-weight-bold small text-uppercase text-muted">Nueva nota</label>
          <textarea id="nuevaNota" name="nota" class="form-control mt-1" rows="2" required maxlength="2000" placeholder="Comentario de gestión…"></textarea>
          <div class="d-flex align-items-center justify-content-between mt-2">
            <small class="text-muted">Se guarda en el historial de este lead.</small>
            <button type="submit" class="btn btn-primary btn-sm" id="btnGuardarNota"><i class="bi bi-plus-lg"></i> Guardar nota</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Enviar formulario de requerimientos -->
<div class="modal fade lw-modal" id="modalFormShare" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0"><i class="bi bi-ui-checks mr-1"></i> Enviar formulario de requerimientos</h5>
          <p class="lw-modal-lead-name mb-0">Lead ID <span id="formShareLeadIdLabel"></span></p>
        </div>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="formShareLeadId" value="">
        <input type="hidden" id="formShareAccesoId" value="">

        <section class="lw-form-share-info" aria-label="Información del cliente">
          <h6 class="lw-form-share-section-title">Información del cliente</h6>
          <div class="lw-form-share-grid">
            <div><span class="lw-form-share-label">Empresa</span><strong id="formShareEmpresa">—</strong></div>
            <div><span class="lw-form-share-label">Contacto</span><strong id="formShareContacto">—</strong></div>
            <div><span class="lw-form-share-label">Correo</span><strong id="formShareCorreo">—</strong></div>
            <div><span class="lw-form-share-label">Teléfono</span><strong id="formShareTelefono">—</strong></div>
            <div class="lw-form-share-grid--full"><span class="lw-form-share-label">Ejecutivo asignado</span><strong id="formShareEjecutivo">—</strong></div>
          </div>
        </section>

        <div class="lw-form-share-linkbar mt-3" id="formShareLinkBar" hidden>
          <label class="lw-form-share-label mb-1">Enlace generado</label>
          <div class="input-group input-group-sm">
            <input type="text" class="form-control" id="formShareUrlBar" readonly>
            <div class="input-group-append">
              <button type="button" class="btn btn-outline-secondary" id="btnFormShareCopyBar" title="Copiar enlace"><i class="bi bi-clipboard"></i></button>
            </div>
          </div>
          <small class="text-muted" id="formShareExpiryBar"></small>
        </div>

        <section class="lw-form-share-modes mt-4" aria-label="Modo de envío">
          <h6 class="lw-form-share-section-title">Seleccione cómo desea compartir el formulario</h6>
          <div class="lw-form-share-mode-btns" role="radiogroup" aria-label="Modo de compartir">
            <label class="lw-form-share-mode">
              <input type="radio" name="formShareMode" value="email" checked>
              <span><i class="bi bi-envelope"></i> Enviar por correo electrónico</span>
            </label>
            <label class="lw-form-share-mode">
              <input type="radio" name="formShareMode" value="link">
              <span><i class="bi bi-link-45deg"></i> Compartir mediante enlace</span>
            </label>
          </div>
          <div class="lw-form-share-reuse mt-2" id="formShareReuseRow" hidden>
            <label class="mb-0 small">
              <input type="checkbox" id="formShareRegenerate"> Regenerar enlace (invalida el enlace activo actual)
            </label>
          </div>
        </section>

        <div id="formSharePanelEmail" class="lw-form-share-panel mt-3">
          <div class="form-group">
            <label for="formShareSubject">Asunto del correo</label>
            <input type="text" class="form-control" id="formShareSubject" maxlength="200">
          </div>
          <div class="form-group">
            <label for="formShareMessage">Mensaje (opcional)</label>
            <textarea class="form-control" id="formShareMessage" rows="4" placeholder="Personalice el mensaje del correo…"></textarea>
            <small class="text-muted">Si deja el mensaje vacío, se usará la plantilla predeterminada con botón de acceso.</small>
          </div>
          <div class="lw-form-share-preview-wrap">
            <div class="lw-form-share-preview-head">Vista previa del correo</div>
            <div class="lw-form-share-preview" id="formShareEmailPreview"></div>
          </div>
        </div>

        <div id="formSharePanelLink" class="lw-form-share-panel mt-3" hidden>
          <div class="form-group mb-2">
            <label>Enlace del formulario</label>
            <div class="input-group">
              <input type="text" class="form-control" id="formShareUrl" readonly placeholder="Genere un enlace para compartir">
              <div class="input-group-append">
                <button type="button" class="btn btn-outline-secondary" id="btnFormShareCopy" disabled title="Copiar enlace"><i class="bi bi-clipboard"></i></button>
              </div>
            </div>
            <small class="text-muted" id="formShareExpiry"></small>
          </div>
          <div class="alert alert-light border small mb-0" id="formShareWaPreview"></div>
        </div>

        <div id="formShareStatus" class="lw-form-share-status mt-3" hidden></div>
      </div>
      <div class="modal-footer lw-form-share-footer">
        <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-outline-primary" id="btnFormShareGenerate" hidden><i class="bi bi-link-45deg"></i> Generar enlace</button>
        <button type="button" class="btn btn-outline-secondary" id="btnFormShareCopyFooter" hidden disabled><i class="bi bi-clipboard"></i> Copiar enlace</button>
        <button type="button" class="btn btn-success" id="btnFormShareWhatsApp" hidden disabled><i class="bi bi-whatsapp"></i> Compartir por WhatsApp</button>
        <button type="button" class="btn btn-primary" id="btnFormShareSendEmail"><i class="bi bi-send"></i> Enviar correo</button>
      </div>
    </div>
  </div>
</div>

<!-- Consultar levantamiento -->
<div class="modal fade lw-modal" id="modalProyecView" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0"><i class="bi bi-journal-text mr-1"></i> Levantamiento de requerimientos</h5>
          <p class="lw-modal-lead-name mb-0"><span id="proyecViewLeadNombre"></span> · ID <span id="proyecViewLeadIdLabel"></span></p>
        </div>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span>&times;</span></button>
      </div>
      <div class="modal-body" id="proyecViewBody">
        <div class="text-center text-muted py-4" id="proyecViewLoading">
          <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
          <span class="ml-2">Cargando levantamiento…</span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-dismiss="modal">Cerrar</button>
        <a href="#" class="btn btn-primary" id="btnProyecViewClient" target="_blank" rel="noopener" hidden>
          <i class="bi bi-box-arrow-up-right"></i> Abrir formulario del cliente
        </a>
      </div>
    </div>
  </div>
</div>
<script src="<?= adm_href('analytics/js/datatables-es.js') ?>"></script>
<script src="<?= adm_href('vendor/jquery/jquery.min.js') ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= adm_href('vendor/datatables/jquery.dataTables.min.js') ?>"></script>
<script src="<?= adm_href('vendor/datatables/dataTables.bootstrap4.min.js') ?>"></script>
<script>
(function ($) {
  'use strict';

  var API = window.ADM_BASE + '/website/api';
  var pipelineOpts = <?= json_encode($pipelineOpts, JSON_UNESCAPED_UNICODE) ?>;
  var listFilters = {
    period: <?= json_encode($period) ?>,
    from: <?= json_encode($from) ?>,
    to: <?= json_encode($to) ?>,
    status: <?= json_encode($statusFilter) ?>,
    origen: <?= json_encode($origenFilter) ?>
  };
  var table;
  var rowsCache = {};
  var selectedLeadId = null;
  var INBOX_URL = window.ADM_BASE + '/leads/inbox.php';
  var formShareState = {
    leadId: null,
    link: null,
    whatsappMessage: '',
    emailDefaults: null,
    loading: false
  };
  var proyecViewLeadId = null;

  function statusClass(estado) {
    if (estado === 'calificado') return 'lw-st-calificado';
    if (estado === 'cierre' || estado === 'cerrado') return 'lw-st-cierre';
    return 'lw-st-lead';
  }

  function statusSelect(row) {
    var estado = row.pipeline_estado || 'lead';
    var html = '<select class="lw-status-select js-status ' + statusClass(estado) + '" data-id="' + row.id + '">';
    Object.keys(pipelineOpts).forEach(function (key) {
      html += '<option value="' + key + '"' + (estado === key ? ' selected' : '') + '>' + pipelineOpts[key] + '</option>';
    });
    html += '</select>';
    return html;
  }

  function statusBadge(label, estado) {
    return '<span class="lw-status-badge ' + statusClass(estado) + '">' + escapeHtml(label) + '</span>';
  }

  function origenBadge(row) {
    var label = row.origen_label || 'Web site';
    var cls = row.origen_class || 'lw-origen-web';
    return '<span class="lw-origen-pill ' + cls + '">' + escapeHtml(label) + '</span>';
  }

  function webBadge(row) {
    var label = row.web_label || '—';
    var cls = row.web_class || 'lw-web-unknown';
    if (!row.web) {
      return '<span class="lw-origen-pill lw-web-unknown">' + escapeHtml(label) + '</span>';
    }
    return '<span class="lw-origen-pill ' + cls + '" title="Sitio web de registro">' + escapeHtml(label) + '</span>';
  }

  function escapeHtml(str) {
    return String(str || '').replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }

  function formatTelefono(row) {
    var tel = row.telefono || '';
    if (!hasValidWaPhone(row)) {
      return '<span class="text-muted">—</span>';
    }
    return '<a href="' + buildWhatsAppUrl(row) + '" target="_blank" rel="noopener" class="lw-phone-link">' + escapeHtml(tel) + '</a>';
  }

  function waPhoneDigits(tel) {
    return String(tel || '').replace(/\D/g, '');
  }

  /** Acepta +52 477 340 0954, 52 4773400954, 477 340 0954, etc. → 10 dígitos locales. */
  function normalizeMxPhone(raw) {
    var digits = String(raw || '').replace(/\D/g, '');
    if (!digits) return '';
    if (digits.length >= 13 && digits.indexOf('521') === 0) {
      digits = digits.slice(3);
    } else if (digits.length >= 12 && digits.indexOf('52') === 0) {
      digits = digits.slice(2);
    } else if (digits.length === 11 && digits.charAt(0) === '1') {
      digits = digits.slice(1);
    }
    if (digits.length > 10) {
      digits = digits.slice(-10);
    }
    return digits;
  }

  function formatMxPhoneLocal(digits) {
    digits = String(digits || '').replace(/\D/g, '').slice(0, 10);
    if (digits.length <= 3) return digits;
    if (digits.length <= 6) return digits.slice(0, 3) + ' ' + digits.slice(3);
    return digits.slice(0, 3) + ' ' + digits.slice(3, 6) + ' ' + digits.slice(6);
  }

  function hasValidWaPhone(row) {
    var tel = row.telefono || '';
    if (!tel || tel === 'WhatsApp web') return false;
    return waPhoneDigits(tel).length >= 10;
  }

  function buildWhatsAppUrl(row) {
    var phone = waPhoneDigits(row.telefono);
    var nombre = String(row.nombre_completo || row.nombre || '').trim() || 'cliente';
    var servicio = String(row.servicio || 'nuestros servicios').trim();
    var empresa = String(row.empresa || '').trim();
    var msg = 'Hola ' + nombre + (empresa ? (' (' + empresa + ')') : '') + ', te contactamos de ConlineWeb. Vi tu solicitud sobre ' + servicio + '. ¿En qué podemos ayudarte?';
    return 'https://wa.me/' + phone + '?text=' + encodeURIComponent(msg);
  }

  function whatsAppActionBtn(row) {
    if (!hasValidWaPhone(row)) {
      return '<button type="button" class="btn btn-sm btn-light lw-btn-wa mr-1" disabled title="Sin teléfono válido"><i class="bi bi-whatsapp"></i></button>';
    }
    return '<a href="' + buildWhatsAppUrl(row) + '" target="_blank" rel="noopener" class="btn btn-sm btn-success lw-btn-wa mr-1" title="Enviar WhatsApp"><i class="bi bi-whatsapp"></i></a>';
  }

  function inboxActionBtn(row) {
    return '<a href="' + INBOX_URL + '?id=' + row.id + '" class="btn btn-sm btn-outline-dark lw-btn-inbox mr-1" title="Abrir en Bandeja CRM"><i class="bi bi-inbox-fill"></i></a>';
  }

  function convertClientBtn(row) {
    if (row.es_cliente && row.cliente_id) {
      return '<a href="' + window.ADM_BASE + '/detalle_cliente.php?id=' + row.cliente_id + '" class="btn btn-sm btn-outline-success lw-btn-cliente mr-1" title="Ya es cliente #' + row.cliente_id + '"><i class="bi bi-person-check-fill"></i></a>';
    }
    var disabled = !(row.correo && String(row.correo).indexOf('@') > 0);
    if (disabled) {
      return '<button type="button" class="btn btn-sm btn-light lw-btn-cliente mr-1" disabled title="Sin correo válido"><i class="bi bi-person-plus"></i></button>';
    }
    return '<button type="button" class="btn btn-sm btn-warning lw-btn-cliente js-to-client mr-1" data-id="' + row.id + '" title="Pasar a cliente (crear acceso portal)"><i class="bi bi-person-plus-fill"></i></button>';
  }

  function convertLeadToClient(id, onDone) {
    var row = getRowById(id);
    if (!row) {
      Swal.fire('Error', 'No se encontró el lead', 'error');
      return;
    }
    if (row.es_cliente) {
      Swal.fire('Info', 'Este lead ya está vinculado a un cliente.', 'info');
      return;
    }
    var nombre = row.nombre_completo || row.nombre || ('Lead #' + id);
    Swal.fire({
      title: '¿Pasar a cliente?',
      html: 'Se creará el registro en <strong>clientes</strong> y <strong>login</strong> con el mismo ID.<br>' +
        'Correo / usuario: <code>' + escapeHtml(row.correo || '') + '</code><br>' +
        'Contacto: <strong>' + escapeHtml(nombre) + '</strong>' +
        (row.empresa ? (' · ' + escapeHtml(row.empresa)) : '') +
        '<br><small class="text-muted">Se generará una contraseña aleatoria (MD5 en sistema).</small>',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Sí, crear cliente',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#000147'
    }).then(function (result) {
      if (!result.isConfirmed) return;
      Swal.fire({ title: 'Creando cliente…', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
      $.ajax({
        url: API + '/convert_to_client.php',
        method: 'POST',
        dataType: 'json',
        data: { lead_id: id }
      }).done(function (res) {
        if (!res || !res.success) {
          Swal.fire('Error', (res && res.message) || 'No se pudo convertir', 'error');
          return;
        }
        Swal.fire({
          icon: 'success',
          title: 'Cliente creado',
          html: 'ID cliente / login: <strong>#' + res.cliente_id + '</strong><br>' +
            'Usuario: <code>' + escapeHtml(res.usuario || res.correo || '') + '</code><br>' +
            'Contraseña: <code id="cwCliPass">' + escapeHtml(res.contrasena || '') + '</code>' +
            '<br><small class="text-muted">Guarda la contraseña; también quedó en contrasena_normal.</small>',
          showCancelButton: true,
          confirmButtonText: 'Ver cliente',
          cancelButtonText: 'Cerrar'
        }).then(function (r) {
          if (r.isConfirmed && res.cliente_id) {
            window.location.href = window.ADM_BASE + '/detalle_cliente.php?id=' + res.cliente_id;
            return;
          }
          if (typeof onDone === 'function') onDone(res);
        });
      }).fail(function (xhr) {
        var msg = 'Error al crear el cliente';
        try {
          var j = JSON.parse(xhr.responseText);
          if (j.message) msg = j.message;
        } catch (err) { /* ignore */ }
        Swal.fire('Error', msg, 'error');
      });
    });
  }

  function getCheckedLeadIds() {
    var ids = [];
    $('#webLeadsTable tbody .js-lead-check:checked').each(function () {
      var id = parseInt($(this).val(), 10);
      if (id > 0) ids.push(id);
    });
    return ids;
  }

  function syncCheckAllState() {
    var $all = $('#lwCheckAll');
    var $boxes = $('#webLeadsTable tbody .js-lead-check');
    var total = $boxes.length;
    var checked = $boxes.filter(':checked').length;
    $all.prop('checked', total > 0 && checked === total);
    $all.prop('indeterminate', checked > 0 && checked < total);
  }

  function updateInboxButton() {
    var $btn = $('#btnOpenInbox');
    var $del = $('#btnDeleteLead');
    var $count = $('#btnDeleteLeadCount');
    var checkedIds = getCheckedLeadIds();
    var canDelete = checkedIds.length > 0 || !!selectedLeadId;

    if (selectedLeadId) {
      $btn.removeClass('disabled').attr('href', INBOX_URL + '?id=' + selectedLeadId).attr('aria-disabled', 'false');
    } else {
      $btn.addClass('disabled').attr('href', '#').attr('aria-disabled', 'true');
    }

    if (canDelete) {
      $del.prop('disabled', false).removeClass('disabled');
    } else {
      $del.prop('disabled', true).addClass('disabled');
    }

    if (checkedIds.length > 1) {
      $count.removeClass('d-none').text('(' + checkedIds.length + ')');
      $del.attr('title', 'Dar de baja ' + checkedIds.length + ' leads seleccionados');
    } else if (checkedIds.length === 1) {
      $count.addClass('d-none').text('');
      $del.attr('title', 'Dar de baja el lead marcado');
    } else {
      $count.addClass('d-none').text('');
      $del.attr('title', selectedLeadId ? 'Dar de baja el lead seleccionado' : 'Marca uno o más leads para dar de baja');
    }
  }

  function deleteLeads(ids, onDone) {
    ids = (ids || []).map(function (id) { return parseInt(id, 10); }).filter(function (id) { return id > 0; });
    if (!ids.length) return;

    var title = ids.length === 1 ? '¿Dar de baja este lead?' : ('¿Dar de baja ' + ids.length + ' leads?');
    var html = ids.length === 1
      ? (function () {
          var row = getRowById(ids[0]);
          var nombre = row ? (row.nombre_completo || row.nombre || '') : '';
          var label = nombre ? ('«' + nombre + '» (ID ' + ids[0] + ')') : ('ID ' + ids[0]);
          return 'Se ocultará <strong>' + escapeHtml(label) + '</strong> de los listados.<br><span style="color:#64748b;font-size:0.9em;">No se elimina permanentemente de la base de datos.</span>';
        })()
      : 'Se ocultarán <strong>' + ids.length + ' registros</strong> de los listados.<br><span style="color:#64748b;font-size:0.9em;">No se eliminan permanentemente de la base de datos.</span>';

    Swal.fire({
      title: title,
      html: html,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#dc2626',
      cancelButtonColor: '#64748b',
      confirmButtonText: ids.length === 1 ? 'Sí, dar de baja' : ('Sí, dar de baja (' + ids.length + ')'),
      cancelButtonText: 'Cancelar'
    }).then(function (result) {
      if (!result.isConfirmed) return;
      $.ajax({
        url: API + '/delete.php',
        method: 'POST',
        data: { ids: ids },
        dataType: 'json'
      }).done(function (res) {
        if (res.success) {
          ids.forEach(function (id) {
            if (rowsCache[id]) delete rowsCache[id];
          });
          Swal.fire('Dado de baja', res.message || 'Lead(s) ocultado(s) correctamente', 'success');
          if (onDone) onDone();
          else table.ajax.reload(null, false);
        } else {
          Swal.fire('Error', res.message || 'No se pudo dar de baja', 'error');
        }
      }).fail(function (xhr) {
        var msg = 'Error al dar de baja';
        try {
          var j = JSON.parse(xhr.responseText);
          if (j.message) msg = j.message;
        } catch (err) { /* ignore */ }
        Swal.fire('Error', msg, 'error');
      });
    });
  }

  function deleteLead(id, nombre, onDone) {
    deleteLeads([id], onDone);
  }

  function selectLeadRow($tr, row) {
    if (!row || !row.id) return;
    $('#webLeadsTable tbody tr').removeClass('lw-row-selected');
    $tr.addClass('lw-row-selected');
    selectedLeadId = parseInt(row.id, 10);
    updateInboxButton();
  }

  function renderDetalle(row) {
    var fields = [
      ['Nombre', row.nombre || '—'],
      ['Apellido', row.apellido || '—'],
      ['Empresa', row.empresa || '—'],
      ['Correo', row.correo || '—'],
      ['Teléfono', row.telefono || '—'],
      ['Servicio de interés', row.servicio || '—'],
      ['Requerimiento', row.requerimiento || '—'],
      ['Cliente portal', row.es_cliente && row.cliente_id
        ? ('<a href="' + window.ADM_BASE + '/detalle_cliente.php?id=' + row.cliente_id + '">Cliente #' + row.cliente_id + '</a>')
        : '<span class="text-muted">Aún no convertido</span>'],
      ['Página origen', row.pagina_origen || '—'],
      ['Origen', origenBadge(row)],
      ['Web', webBadge(row)],
      ['Estatus', statusBadge(row.pipeline_label, row.pipeline_estado)],
      ['Registro', row.fecha_registro],
      ['Última interacción', row.ultima_interaccion || '—'],
      ['Sesión tracking', row.session_id ? ('<code title="' + escapeAttr(row.session_id) + '">' + escapeHtml(row.session_id.slice(0, 16)) + '…</code>') : '<span class="text-muted">Sin vincular</span>']
    ];
    var html = '<dl class="lw-detail-grid">';
    fields.forEach(function (f) {
      html += '<dt>' + escapeHtml(f[0]) + '</dt><dd>';
      if (f[0] === 'Estatus' || f[0] === 'Origen' || f[0] === 'Web' || f[0] === 'Sesión tracking' || f[0] === 'Cliente portal') {
        html += f[1];
      } else {
        html += escapeHtml(f[1]);
      }
      html += '</dd>';
    });
    html += '</dl>';
    html += '<div class="lw-journey-wrap" id="lwJourneyWrap">';
    html += '<div class="lw-journey-head"><h6><i class="bi bi-signpost-split mr-1"></i> Recorrido en la web</h6>';
    html += '<span class="lw-journey-loading" id="lwJourneyLoading"><i class="bi bi-arrow-repeat spin"></i> Cargando…</span></div>';
    html += '<div id="lwJourneyContent"></div></div>';
    return html;
  }

  function escapeAttr(str) {
    return escapeHtml(str).replace(/`/g, '&#96;');
  }

  function renderJourneyStep(step, phaseClass) {
    var isPage = step.kind === 'pageview';
    var isReg = step.is_registration || step.kind === 'registration';
    var typeBadge = isReg
      ? '<span class="badge badge-success">Registro</span>'
      : (isPage ? '<span class="badge badge-light">Página</span>' : '<span class="badge badge-info">' + escapeHtml(step.event_type || 'evento') + '</span>');
    var pageCell = isPage
      ? '<a href="' + escapeAttr(step.url) + '" class="lw-journey-link" target="_blank" rel="noopener noreferrer"><code>' + escapeHtml(step.path || '/') + '</code></a>' +
        (step.title ? '<br><small class="text-muted">' + escapeHtml(step.title) + '</small>' : '')
      : escapeHtml(step.event_label || step.url_short || '—');
    return '<tr class="' + (phaseClass || '') + (isReg ? ' lw-journey-reg-row' : '') + '">' +
      '<td>' + (step.step || '—') + '</td>' +
      '<td>' + typeBadge + '</td>' +
      '<td>' + pageCell + '</td>' +
      '<td>' + escapeHtml(step.at_fmt || '—') + '</td>' +
      '<td>' + (isPage ? '<strong>' + escapeHtml(step.time_on_page_fmt || '0s') + '</strong>' : '—') + '</td>' +
      '<td>' + escapeHtml(isPage ? (step.region || '—') : '—') + '</td>' +
      '</tr>';
  }

  function renderJourneyTable(steps, phaseClass) {
    if (!steps || !steps.length) {
      return '<p class="text-muted small mb-0">Sin pasos en esta fase.</p>';
    }
    var html = '<div class="table-responsive"><table class="table table-sm lw-journey-table mb-0"><thead><tr>' +
      '<th>#</th><th>Tipo</th><th>Página / evento</th><th>Hora (CDMX)</th><th>Tiempo</th><th>Región</th></tr></thead><tbody>';
    steps.forEach(function (step) {
      html += renderJourneyStep(step, phaseClass);
    });
    html += '</tbody></table></div>';
    return html;
  }

  function routeBadges(globalCount, uniqueCount, newCount) {
    var globalNum = globalCount || 0;
    var newNum = (typeof newCount === 'number' && newCount >= 0) ? newCount : (uniqueCount || 0);
    var newTitle = (typeof newCount === 'number' && newCount >= 0)
      ? 'Rutas nuevas después del registro'
      : 'Rutas únicas distintas';
    return '<div class="va-route-badges">' +
      '<span class="va-route-badge va-route-badge-global" title="Total ingresos a páginas (global)">' +
      '<i class="bi bi-globe-americas"></i><em>' + globalNum + '</em><small>Global</small></span>' +
      '<span class="va-route-badge va-route-badge-new" title="' + newTitle + '">' +
      '<i class="bi bi-signpost-split"></i><em>' + newNum + '</em><small>Nuevas</small></span>' +
      '</div>';
  }

  function renderJourneyPanel(data) {
    if (!data || !data.has_tracking) {
      return '<div class="alert alert-light border mb-0">' + escapeHtml(data.message || 'Sin recorrido disponible.') + '</div>';
    }

    var s = data.summary || {};
    var returned = s.returned_after_register;
    var html = '<div class="lw-journey-summary lw-journey-summary-top">';
    html += '<div class="lw-journey-route-badges">' + routeBadges(s.routes_global, s.routes_unique, s.routes_new_after) + '</div>';
    html += '<span><strong>' + (s.before_count || 0) + '</strong> antes</span>';
    html += '<span><strong>' + (s.after_count || 0) + '</strong> después (misma sesión)</span>';
    html += '<span><strong>' + (s.later_sessions_count || 0) + '</strong> visitas posteriores</span>';
    html += '<span class="lw-journey-return ' + (returned ? 'is-yes' : 'is-no') + '">' +
      (returned ? '<i class="bi bi-check-circle"></i> Siguió navegando' : '<i class="bi bi-x-circle"></i> No volvió a entrar') +
      '</span></div>';

    html += '<div class="lw-journey-meta text-muted small mb-3">';
    html += escapeHtml(data.geo || '—') + ' · ' + escapeHtml(data.device || '—') + ' · Entrada: ' + escapeHtml(data.landing_path || '—');
    html += ' · Referrer: ' + escapeHtml(data.referrer_label || '—');
    html += '</div>';

    html += '<div class="lw-journey-phase"><h6 class="lw-journey-phase-title"><i class="bi bi-arrow-left-circle"></i> Antes del registro</h6>';
    html += renderJourneyTable(data.before, 'lw-phase-before');
    html += '</div>';

    if (data.registration) {
      html += '<div class="lw-journey-phase lw-journey-phase-reg"><h6 class="lw-journey-phase-title"><i class="bi bi-person-check-fill"></i> Momento del registro · ' + escapeHtml(data.registered_at_fmt || '') + '</h6>';
      html += '<div class="table-responsive"><table class="table table-sm lw-journey-table mb-0"><thead><tr>' +
        '<th></th><th>Tipo</th><th>Página / evento</th><th>Hora (CDMX)</th><th></th><th></th></tr></thead><tbody>';
      html += renderJourneyStep(data.registration, 'lw-phase-registration');
      html += '</tbody></table></div></div>';
    }

    html += '<div class="lw-journey-phase"><h6 class="lw-journey-phase-title"><i class="bi bi-arrow-right-circle"></i> Después del registro (misma sesión)</h6>';
    html += renderJourneyTable(data.after, 'lw-phase-after');
    html += '</div>';

    if (data.later_sessions && data.later_sessions.length) {
      html += '<div class="lw-journey-phase lw-journey-phase-later"><h6 class="lw-journey-phase-title"><i class="bi bi-arrow-repeat"></i> Visitas posteriores (otras sesiones)</h6>';
      html += '<div class="table-responsive"><table class="table table-sm lw-journey-table mb-0"><thead><tr>' +
        '<th>Sesión</th><th>Primera visita</th><th>Última actividad</th><th>Páginas</th><th>Entrada</th><th>Geo</th></tr></thead><tbody>';
      data.later_sessions.forEach(function (ls) {
        html += '<tr><td><code title="' + escapeAttr(ls.session_id) + '">' + escapeHtml(ls.session_short) + '</code></td>' +
          '<td>' + escapeHtml(ls.first_seen) + '</td>' +
          '<td>' + escapeHtml(ls.last_seen) + '</td>' +
          '<td>' + (ls.pages_count || 0) + '</td>' +
          '<td><code>' + escapeHtml(ls.landing_path) + '</code></td>' +
          '<td>' + escapeHtml(ls.geo) + '</td></tr>';
      });
      html += '</tbody></table></div></div>';
    }

    return html;
  }

  function loadLeadJourney(leadId) {
    var $loading = $('#lwJourneyLoading');
    var $content = $('#lwJourneyContent');
    if (!$content.length) return;

    $loading.show();
    $content.html('');

    $.getJSON(API + '/lead_journey.php', { id: leadId })
      .done(function (res) {
        $loading.hide();
        if (!res.success || !res.journey) {
          $content.html('<div class="alert alert-warning mb-0">No se pudo cargar el recorrido.</div>');
          return;
        }
        if (res.journey.session_id) {
          fetch((window.ADM_BASE || '') + '/analytics/api/session_admin_view.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ session_id: res.journey.session_id })
          }).catch(function () { /* noop */ });
        }
        $content.html(renderJourneyPanel(res.journey));
      })
      .fail(function () {
        $loading.hide();
        $content.html('<div class="alert alert-danger mb-0">Error al cargar el recorrido.</div>');
      });
  }

  function parseNotas(raw) {
    if (Array.isArray(raw)) return raw;
    if (!raw) return [];
    try {
      var parsed = typeof raw === 'string' ? JSON.parse(raw) : raw;
      return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
      return [];
    }
  }

  function renderNotas(notasJson) {
    var notas = parseNotas(notasJson);
    if (!notas.length) {
      return '<p class="text-muted mb-0">Sin notas registradas.</p>';
    }
    var html = '';
    notas.slice().reverse().forEach(function (n, i) {
      var isSystem = /^(Lead captado desde|Mensaje WhatsApp:|Registro manual)/.test(n.nota || '');
      html += '<div class="lw-note-item' + (isSystem ? ' is-system' : '') + '">';
      html += '<div class="meta">#' + (notas.length - i);
      if (n.tipo === 'gestion' || (!isSystem && n.usuario_id > 0)) html += ' · Gestión';
      else if (isSystem) html += ' · Automática';
      html += ' · ' + escapeHtml(n.fecha || '') + '</div>';
      html += '<div>' + escapeHtml(n.nota || '') + '</div></div>';
    });
    return html;
  }

  function cacheRow(row) {
    if (row && row.id) rowsCache[row.id] = row;
  }

  function getRowById(id) {
    id = parseInt(id, 10);
    if (!id) return null;
    if (rowsCache[id]) return rowsCache[id];
    if (!table) return null;
    var found = null;
    table.rows().every(function () {
      var d = this.data();
      if (d && parseInt(d.id, 10) === id) {
        found = d;
        cacheRow(d);
        return false;
      }
    });
    return found;
  }

  function openNotasModal(row) {
    if (!row) {
      alert('No se pudo cargar el lead.');
      return;
    }
    $('#notaLeadId').val(row.id);
    $('#notasLeadNombre').text(row.nombre_completo || row.nombre || 'Lead');
    $('#notasLeadIdLabel').text(row.id);
    $('#notasHistorial').html(renderNotas(row.notas_json));
    $('#nuevaNota').val('');
    $('#modalNotas').modal('show');
    setTimeout(function () { $('#nuevaNota').focus(); }, 350);
  }

  function openDetalleModal(row) {
    if (!row) {
      alert('No se pudo cargar el lead.');
      return;
    }
    $('#detalleBody').html(renderDetalle(row));
    $('#modalDetalle').modal('show');
    loadLeadJourney(row.id);
  }

  function getFormShareMode() {
    return $('input[name="formShareMode"]:checked').val() || 'email';
  }

  function setFormShareStatus(msg, type) {
    var $el = $('#formShareStatus');
    if (!msg) {
      $el.attr('hidden', true).removeClass('alert-success alert-danger alert-info').text('');
      return;
    }
    $el.removeAttr('hidden')
      .removeClass('alert-success alert-danger alert-info')
      .addClass('alert alert-' + (type || 'info') + ' py-2 mb-0')
      .text(msg);
  }

  function formShareAjaxPost(payload) {
    return $.ajax({
      url: API + '/proyec_form_share.php',
      method: 'POST',
      data: payload,
      dataType: 'json'
    }).fail(function (xhr, textStatus) {
      if (textStatus === 'parsererror' && xhr && xhr.responseText) {
        xhr._parserDetail = xhr.responseText.slice(0, 280);
      }
    });
  }

  function formShareAjaxError(xhr, fallback) {
    var msg = fallback || 'Error en la solicitud';
    if (!xhr) return msg;
    try {
      if (xhr.responseJSON && xhr.responseJSON.message) {
        return xhr.responseJSON.message;
      }
      if (xhr.message) {
        return xhr.message;
      }
      if (xhr.responseText) {
        if (xhr.responseText.charAt(0) === '{') {
          var j = JSON.parse(xhr.responseText);
          if (j.message) return j.message;
        }
        if (xhr.responseText.indexOf('<!DOCTYPE') !== -1 || xhr.responseText.indexOf('<html') !== -1) {
          return 'Sesión expirada o error del servidor. Recargue la página e inicie sesión de nuevo.';
        }
      }
    } catch (err) { /* ignore */ }
    if (xhr.status === 404) return 'API no encontrada (proyec_form_share.php)';
    if (xhr.status === 500) return msg + ' (error 500)';
    if (xhr._parserDetail) return 'Respuesta inválida del servidor. ' + xhr._parserDetail;
    return msg;
  }

  function syncFormShareLinkFields() {
    var url = (formShareState.link && formShareState.link.url) ? formShareState.link.url : '';
    var expiry = (formShareState.link && formShareState.link.fecha_expiracion)
      ? 'Vigente hasta ' + formShareState.link.fecha_expiracion
      : '';
    $('#formShareUrl, #formShareUrlBar').val(url);
    $('#formShareExpiry, #formShareExpiryBar').text(expiry);
    if (url) {
      $('#formShareLinkBar').removeAttr('hidden');
      $('#formShareReuseRow').removeAttr('hidden');
    } else {
      $('#formShareLinkBar').attr('hidden', true);
    }
  }

  function updateFormSharePanels() {
    var mode = getFormShareMode();
    var hasLink = !!(formShareState.link && formShareState.link.url);
    $('#formSharePanelEmail').prop('hidden', mode !== 'email');
    $('#formSharePanelLink').prop('hidden', mode !== 'link');
    $('#btnFormShareSendEmail').toggle(mode === 'email');
    $('#btnFormShareGenerate').toggle(mode === 'link');
    $('#btnFormShareCopyFooter, #btnFormShareCopy, #btnFormShareCopyBar').toggle(mode === 'link' || hasLink);
    $('#btnFormShareWhatsApp').toggle(mode === 'link');
    $('#btnFormShareCopyFooter, #btnFormShareCopy, #btnFormShareCopyBar, #btnFormShareWhatsApp').prop('disabled', !hasLink);
    syncFormShareLinkFields();
    if (mode === 'email') {
      refreshFormShareEmailPreview();
    }
  }

  function refreshFormShareEmailPreview() {
    var url = (formShareState.link && formShareState.link.url) ? formShareState.link.url : '';
    var html = (formShareState.emailDefaults && formShareState.emailDefaults.body_html)
      ? formShareState.emailDefaults.body_html
      : '<p class="text-muted mb-0">Generando enlace…</p>';
    if (url && html.indexOf('?t=preview') !== -1) {
      html = html.replace(/\?t=preview/g, '?t=' + encodeURIComponent(formShareState.link.token || ''));
    }
    $('#formShareEmailPreview').html(html);
  }

  function applyFormShareLinkResponse(res) {
    formShareState.link = res.link || null;
    formShareState.whatsappMessage = res.whatsapp_message || '';
    if (res.email_defaults) {
      formShareState.emailDefaults = res.email_defaults;
      if (!$('#formShareSubject').val()) {
        $('#formShareSubject').val(res.email_defaults.subject || '');
      }
    }
    if (formShareState.link && formShareState.link.acceso_id) {
      $('#formShareAccesoId').val(formShareState.link.acceso_id);
    }
    $('#formShareWaPreview').text(formShareState.whatsappMessage || '');
    syncFormShareLinkFields();
    updateFormSharePanels();
  }

  function ensureFormShareLink(regenerate) {
    var leadId = formShareState.leadId;
    if (!leadId) {
      return $.Deferred().reject({ responseJSON: { message: 'Lead no seleccionado' } }).promise();
    }

    var $pendingBtns = $('#btnFormShareGenerate, #btnFormShareSendEmail');
    $pendingBtns.prop('disabled', true);

    return formShareAjaxPost({
      action: 'generate_link',
      lead_id: leadId,
      regenerate: regenerate ? 1 : 0
    }).then(function (res) {
      if (!res.success) {
        return $.Deferred().reject({ responseJSON: res, responseText: JSON.stringify(res) }).promise();
      }
      applyFormShareLinkResponse(res);
      return res;
    }).always(function () {
      $pendingBtns.prop('disabled', false);
    });
  }

  function applyFormShareData(data) {
    var lead = data.lead || {};
    formShareState.link = data.link || null;
    formShareState.whatsappMessage = data.whatsapp_message || '';
    formShareState.emailDefaults = data.email_defaults || null;

    $('#formShareEmpresa').text(lead.empresa || '—');
    $('#formShareContacto').text(lead.contacto || '—');
    $('#formShareCorreo').text(lead.correo || '—');
    $('#formShareTelefono').text(lead.telefono || '—');
    $('#formShareEjecutivo').text(lead.ejecutivo || '—');

    if (formShareState.emailDefaults) {
      $('#formShareSubject').val(formShareState.emailDefaults.subject || '');
    }
    $('#formShareMessage').val('');

    if (formShareState.link && formShareState.link.url) {
      $('#formShareAccesoId').val(formShareState.link.acceso_id || '');
    } else {
      $('#formShareAccesoId').val('');
      $('#formShareReuseRow').attr('hidden', true);
      $('#formShareRegenerate').prop('checked', false);
    }

    $('#formShareWaPreview').text(formShareState.whatsappMessage || '');
    syncFormShareLinkFields();
    updateFormSharePanels();
  }

  function loadFormShareData(leadId) {
    formShareState.loading = true;
    setFormShareStatus('Cargando información…', 'info');
    return $.getJSON(API + '/proyec_form_share.php', { lead_id: leadId })
      .then(function (res) {
        if (!res.success) {
          setFormShareStatus(res.message || 'No se pudo cargar el lead', 'danger');
          return $.Deferred().reject({ responseJSON: res }).promise();
        }
        applyFormShareData(res);
        setFormShareStatus('');
        return res;
      })
      .fail(function (xhr) {
        var msg = formShareAjaxError(xhr, 'Error al cargar datos del formulario');
        setFormShareStatus(msg, 'danger');
      })
      .always(function () {
        formShareState.loading = false;
      });
  }

  function bootstrapFormShareLink() {
    if (!formShareState.leadId) return;
    if (formShareState.link && formShareState.link.url) {
      updateFormSharePanels();
      return;
    }
    setFormShareStatus('Generando enlace…', 'info');
    ensureFormShareLink($('#formShareRegenerate').is(':checked'))
      .done(function () {
        setFormShareStatus('Enlace listo para compartir', 'success');
      })
      .fail(function (xhr) {
        setFormShareStatus(formShareAjaxError(xhr, 'No se pudo generar el enlace'), 'danger');
      });
  }

  function fillFormShareLeadFromRow(row) {
    $('#formShareEmpresa').text(row.empresa || '—');
    $('#formShareContacto').text(row.nombre_completo || row.nombre || row.contacto || '—');
    $('#formShareCorreo').text(row.correo || '—');
    $('#formShareTelefono').text(row.telefono || '—');
    $('#formShareEjecutivo').text(row.ejecutivo || '—');
  }

  function openFormShareModal(row) {
    if (!row || !row.id) {
      alert('No se pudo cargar el lead.');
      return;
    }
    formShareState.leadId = row.id;
    formShareState.link = null;
    formShareState.whatsappMessage = '';
    formShareState.emailDefaults = null;
    $('#formShareLeadId').val(row.id);
    $('#formShareLeadIdLabel').text(row.id);
    fillFormShareLeadFromRow(row);
    $('input[name="formShareMode"][value="email"]').prop('checked', true);
    $('#formShareRegenerate').prop('checked', false);
    $('#formShareUrl, #formShareUrlBar').val('');
    $('#formShareExpiry, #formShareExpiryBar').text('');
    $('#formShareLinkBar').attr('hidden', true);
    $('#formShareWaPreview').text('');
    $('#formShareEmailPreview').html('');
    setFormShareStatus('');
    updateFormSharePanels();
    $('#modalFormShare').modal('show');

    loadFormShareData(row.id).done(function () {
      bootstrapFormShareLink();
    });
  }

  function copyFormShareLink() {
    var url = $('#formShareUrlBar').val() || $('#formShareUrl').val() || (formShareState.link && formShareState.link.url) || '';
    if (!url) {
      setFormShareStatus('Primero genere el enlace', 'danger');
      return;
    }

    function onCopied() {
      setFormShareStatus('Enlace copiado al portapapeles', 'success');
      $.ajax({
        url: API + '/proyec_form_share.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
          action: 'log_share',
          lead_id: formShareState.leadId,
          canal: 'copiar',
          acceso_id: parseInt($('#formShareAccesoId').val(), 10) || 0
        })
      });
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(url).then(onCopied).catch(function () {
        $('#formShareUrl').trigger('select');
        document.execCommand('copy');
        onCopied();
      });
    } else {
      $('#formShareUrl').trigger('select');
      document.execCommand('copy');
      onCopied();
    }
  }

  function shareFormWhatsApp() {
    var msg = formShareState.whatsappMessage || $('#formShareWaPreview').text();
    if (!msg) return;
    var url = 'https://wa.me/?text=' + encodeURIComponent(msg);
    window.open(url, '_blank', 'noopener');
    $.ajax({
      url: API + '/proyec_form_share.php',
      method: 'POST',
      contentType: 'application/json',
      data: JSON.stringify({
        action: 'log_share',
        lead_id: formShareState.leadId,
        canal: 'whatsapp',
        acceso_id: parseInt($('#formShareAccesoId').val(), 10) || 0
      })
    });
  }

  function sendFormShareEmail() {
    var leadId = formShareState.leadId;
    if (!leadId) return;

    var $btn = $('#btnFormShareSendEmail');
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm mr-1"></span> Enviando…');

    ensureFormShareLink($('#formShareRegenerate').is(':checked'))
      .then(function () {
        return formShareAjaxPost({
          action: 'send_email',
          lead_id: leadId,
          subject: $('#formShareSubject').val(),
          message_html: $('#formShareMessage').val(),
          regenerate: $('#formShareRegenerate').is(':checked') ? 1 : 0
        });
      })
      .done(function (res) {
        if (!res.success) {
          Swal.fire('Error', res.message || 'No se pudo enviar el correo', 'error');
          return;
        }
        if (res.link) {
          applyFormShareLinkResponse(res);
        }
        Swal.fire('Correo enviado', res.message || 'El formulario fue enviado al cliente', 'success');
        $('#modalFormShare').modal('hide');
      })
      .fail(function (xhr) {
        Swal.fire('Error', formShareAjaxError(xhr, 'No se pudo enviar el correo'), 'error');
      })
      .always(function () {
        $btn.prop('disabled', false).html('<i class="bi bi-send"></i> Enviar correo');
      });
  }

  function proyecViewBtn(row) {
    if (!row.proyec_tiene_formulario) {
      return '<button type="button" class="btn btn-sm btn-outline-secondary lw-btn-proyec-view" disabled title="Sin levantamiento"><i class="bi bi-journal-text"></i></button>';
    }
    var cambios = parseInt(row.proyec_cambios, 10) || 0;
    var badge = cambios > 0
      ? '<span class="lw-proyec-badge">' + (cambios > 9 ? '9+' : String(cambios)) + '</span>'
      : '';
    var prog = parseInt(row.proyec_progreso, 10) || 0;
    return '<button type="button" class="btn btn-sm btn-outline-success lw-btn-proyec-view js-proyec-view mr-1" data-id="' + row.id + '" title="Consultar levantamiento (' + prog + '%)">' +
      '<i class="bi bi-journal-text"></i>' + badge + '</button>';
  }

  function clearProyecBadge(leadId) {
    var row = getRowById(leadId);
    if (row) {
      row.proyec_cambios = 0;
      cacheRow(row);
      $('#webLeadsTable').find('.js-proyec-view[data-id="' + leadId + '"] .lw-proyec-badge').remove();
    }
  }

  function proyecSectionStatusLabel(status) {
    if (status === 'filled') return { text: 'Con información', cls: 'is-filled' };
    if (status === 'partial') return { text: 'Parcial', cls: 'is-partial' };
    if (status === 'skipped') return { text: 'Omitido', cls: 'is-skipped' };
    return { text: 'Sin datos', cls: 'is-empty' };
  }

  function renderProyecViewModal(res) {
    var $body = $('#proyecViewBody');
    var $btnClient = $('#btnProyecViewClient');

    function hideFooterLinks() {
      $btnClient.prop('hidden', true);
    }

    if (!res || !res.success) {
      $body.html('<div class="alert alert-danger mb-0">' + escapeHtml((res && res.message) || 'No se pudo cargar el levantamiento') + '</div>');
      hideFooterLinks();
      return;
    }
    if (!res.has_project) {
      $body.html('<div class="alert alert-info mb-0">' + escapeHtml(res.message || 'Aún no hay información en el formulario.') + '</div>');
      hideFooterLinks();
      return;
    }

    var s = res.summary || {};
    var stats = res.stats || {};
    var cambios = parseInt(res.cambios_pendientes, 10) || 0;
    var prog = parseInt(s.progreso_pct, 10) || 0;

    var alertHtml = cambios > 0
      ? '<div class="alert alert-warning lw-proyec-view-alert mb-3"><i class="bi bi-bell-fill"></i> '
        + '<strong>' + cambios + ' actualización' + (cambios === 1 ? '' : 'es') + ' nueva' + (cambios === 1 ? '' : 's') + '</strong>'
        + ' desde su última revisión. El cliente siguió capturando información en el formulario.</div>'
      : '';

    var progressHtml = '<div class="lw-proyec-modal-progress">'
      + '<div class="lw-proyec-modal-progress__head"><span>Avance del levantamiento</span><strong>' + prog + '%</strong></div>'
      + '<div class="progress" style="height:8px;border-radius:999px;"><div class="progress-bar bg-success" style="width:' + prog + '%"></div></div>'
      + '<small class="text-muted">Último paso visitado: ' + escapeHtml(s.paso_titulo || '—') + ' · '
      + (stats.guardados_en_vivo || 0) + ' guardados en vivo</small></div>';

    var metaHtml = '<div class="lw-proyec-modal-leadbar">'
      + '<div><span class="lw-form-share-label">Empresa</span><strong>' + escapeHtml(s.empresa || '—') + '</strong></div>'
      + '<div><span class="lw-form-share-label">Ejecutivo</span><strong>' + escapeHtml(s.ejecutivo || '—') + '</strong></div>'
      + '<div><span class="lw-form-share-label">Estado</span><strong>' + escapeHtml((s.estado || 'borrador').charAt(0).toUpperCase() + (s.estado || 'borrador').slice(1)) + '</strong></div>'
      + '<div><span class="lw-form-share-label">Proyecto #</span><strong>' + escapeHtml(String(s.project_id || '—')) + '</strong></div>'
      + '</div>';

    var contactHtml = '<section class="lw-proyec-modal-block"><h6>Contacto y proyecto</h6><div class="lw-proyec-modal-grid">'
      + '<div><span class="lw-form-share-label">Contacto</span><strong>' + escapeHtml((s.contacto && s.contacto.contacto) || '—') + '</strong></div>'
      + '<div><span class="lw-form-share-label">Correo</span><strong>' + escapeHtml((s.contacto && s.contacto.correo) || '—') + '</strong></div>'
      + '<div><span class="lw-form-share-label">Teléfono</span><strong>' + escapeHtml((s.contacto && s.contacto.telefono) || '—') + '</strong></div>'
      + '<div><span class="lw-form-share-label">Giro</span><strong>' + escapeHtml(s.giro || '—') + '</strong></div>'
      + '<div><span class="lw-form-share-label">Página web</span><strong>' + escapeHtml(s.pagina_web || '—') + '</strong></div>'
      + '<div><span class="lw-form-share-label">Última actualización</span><strong>' + escapeHtml(s.fecha_actualizacion || '—') + '</strong></div>'
      + '<div class="lw-proyec-modal-grid--full"><span class="lw-form-share-label">Nombre del proyecto</span><p class="lw-proyec-modal-emphasis">' + escapeHtml(s.nombre_proyecto || '—') + '</p></div>'
      + '<div class="lw-proyec-modal-grid--full"><span class="lw-form-share-label">Objetivo del proyecto</span><p>' + escapeHtml(s.objetivo_proyecto || '—') + '</p></div>'
      + (s.descripcion_corta ? '<div class="lw-proyec-modal-grid--full"><span class="lw-form-share-label">Descripción del proyecto</span><p>' + escapeHtml(s.descripcion_corta) + '</p></div>' : '')
      + (s.problema ? '<div class="lw-proyec-modal-grid--full"><span class="lw-form-share-label">Problema a resolver</span><p>' + escapeHtml(s.problema) + '</p></div>' : '')
      + (s.alcance ? '<div class="lw-proyec-modal-grid--full"><span class="lw-form-share-label">Alcance general</span><p>' + escapeHtml(s.alcance) + '</p></div>' : '')
      + '</div></section>';

    var countsHtml = '<div class="lw-proyec-modal-counts">'
      + '<span><i class="bi bi-check2-square"></i> ' + (stats.secciones_con_info || 0) + ' secciones con info</span>'
      + '<span><i class="bi bi-skip-forward"></i> ' + (stats.secciones_omitidas || 0) + ' omitidas</span>'
      + '<span><i class="bi bi-plug"></i> ' + (stats.integraciones || 0) + ' integraciones</span>'
      + '<span><i class="bi bi-paperclip"></i> ' + (stats.documentos || 0) + ' documentos</span>'
      + '</div>';

    var sectionsHtml = '<section class="lw-proyec-modal-block"><h6>Resumen por sección</h6><div class="lw-proyec-sections-list">';
    (res.sections || []).forEach(function (sec) {
      var st = proyecSectionStatusLabel(sec.status);
      sectionsHtml += '<div class="lw-proyec-section-row ' + st.cls + '">'
        + '<div class="lw-proyec-section-row__main">'
        + '<span class="lw-proyec-section-row__num">' + sec.step + '</span>'
        + '<div><strong>' + escapeHtml(sec.title || '') + '</strong>'
        + (sec.preview ? '<p>' + escapeHtml(sec.preview) + '</p>' : '<p class="text-muted mb-0">Sin contenido capturado</p>')
        + '</div></div>'
        + '<span class="lw-proyec-section-row__badge">' + st.text
        + (sec.items_count > 0 ? ' · ' + sec.items_count : '') + '</span></div>';
    });
    sectionsHtml += '</div></section>';

    var intsHtml = '';
    if (s.integraciones && s.integraciones.length) {
      intsHtml = '<section class="lw-proyec-modal-block"><h6>Integraciones marcadas</h6><div class="lw-proyec-tags">'
        + s.integraciones.map(function (i) { return '<span class="lw-proyec-tag">' + escapeHtml(i) + '</span>'; }).join('')
        + '</div></section>';
    }

    var histHtml = '';
    if (res.historial && res.historial.length) {
      histHtml = '<section class="lw-proyec-modal-block"><h6>Actividad reciente</h6><div class="table-responsive">'
        + '<table class="table table-sm lw-proyec-hist-table mb-0"><thead><tr><th>Fecha</th><th>Paso</th><th>Detalle</th></tr></thead><tbody>';
      res.historial.slice(0, 8).forEach(function (h) {
        histHtml += '<tr><td>' + escapeHtml(h.created_at || '') + '</td><td>' + escapeHtml(h.paso_titulo || '') + '</td><td>' + escapeHtml(h.resumen || '') + '</td></tr>';
      });
      histHtml += '</tbody></table></div></section>';
    }

    $body.html(alertHtml + progressHtml + metaHtml + contactHtml + countsHtml + sectionsHtml + intsHtml + histHtml
      + (res.form_client_url ? '' : '<p class="text-muted small mt-2 mb-0"><i class="bi bi-info-circle"></i> '
        + 'No hay enlace activo del formulario. Use el botón <strong>compartir formulario</strong> (icono azul) para generar uno y enviarlo al cliente.</p>'));

    if (res.form_client_url) {
      $btnClient.attr('href', res.form_client_url).prop('hidden', false);
    } else {
      hideFooterLinks();
    }
  }

  function openProyecViewModal(row) {
    if (!row || !row.id) return;
    proyecViewLeadId = row.id;
    $('#proyecViewLeadNombre').text(row.nombre_completo || row.nombre || 'Lead');
    $('#proyecViewLeadIdLabel').text(row.id);
    $('#proyecViewBody').html(
      '<div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm text-primary" role="status"></div>'
      + '<span class="ml-2">Cargando levantamiento…</span></div>'
    );
    $('#btnProyecViewClient').prop('hidden', true);
    $('#modalProyecView').modal('show');

    $.getJSON(API + '/proyec_form_view.php', { lead_id: row.id, mark_seen: 1 })
      .done(function (res) {
        renderProyecViewModal(res);
        if (res && res.success && res.has_project) {
          clearProyecBadge(row.id);
        }
      })
      .fail(function (xhr) {
        renderProyecViewModal({ success: false, message: formShareAjaxError(xhr, 'No se pudo cargar el levantamiento') });
      });
  }

  function notePreviewCell(row) {
    var preview = row.ultima_nota || '';
    var full = row.ultima_nota_full || preview;
    var count = row.total_notas || 0;
    var id = row.id;
    if (!preview) {
      return '<button type="button" class="btn btn-link btn-sm p-0 js-notas text-muted" data-id="' + id + '">Sin notas · abrir</button>';
    }
    return '<button type="button" class="btn btn-link btn-sm p-0 js-notas lw-note-preview-btn" data-id="' + id + '" title="' + escapeHtml(full) + '">' +
      escapeHtml(preview) + ' <span class="badge badge-light">' + count + '</span></button>';
  }

  $(function () {
    if (!$.fn.DataTable) {
      console.error('DataTables no disponible');
      return;
    }
    if (!$.fn.modal) {
      console.error('Bootstrap modal no disponible');
    }

    table = $('#webLeadsTable').DataTable({
      ajax: {
        url: API + '/list.php',
        data: function (d) {
          d.period = listFilters.period;
          if (listFilters.from) d.from = listFilters.from;
          if (listFilters.to) d.to = listFilters.to;
          d.status = listFilters.status;
          d.origen = listFilters.origen;
        },
        dataSrc: function (json) {
          (json.data || []).forEach(cacheRow);
          return json.data || [];
        }
      },
      order: [[1, 'desc']],
      pageLength: 25,
      autoWidth: false,
      columns: [
        {
          data: 'id',
          orderable: false,
          searchable: false,
          className: 'lw-col-check text-center',
          width: '36px',
          render: function (id) {
            return '<input type="checkbox" class="js-lead-check lw-row-check" value="' + id + '" aria-label="Seleccionar lead ' + id + '">';
          }
        },
        { data: 'id', width: '50px' },
        { data: 'nombre', render: function (d) { return d ? escapeHtml(d) : '<span class="text-muted">—</span>'; } },
        { data: 'apellido', render: function (d) { return d ? escapeHtml(d) : '<span class="text-muted">—</span>'; } },
        { data: 'empresa', render: function (d) { return d ? escapeHtml(d) : '<span class="text-muted">—</span>'; } },
        { data: 'telefono', width: '130px', render: function (_d, _t, row) { return formatTelefono(row); } },
        { data: 'correo', render: function (d) { return d || '<span class="text-muted">—</span>'; } },
        { data: 'servicio' },
        { data: null, orderable: false, width: '120px', render: function (_d, _t, row) { return origenBadge(row); } },
        { data: null, orderable: true, width: '64px', className: 'text-center', render: function (_d, _t, row) { return webBadge(row); } },
        { data: 'pagina_origen', render: function (d) {
          if (!d) return '—';
          var short = d.length > 40 ? d.slice(0, 40) + '…' : d;
          return '<span title="' + escapeHtml(d) + '">' + escapeHtml(short) + '</span>';
        }},
        { data: 'fecha_registro', width: '130px' },
        { data: null, orderable: false, width: '120px', render: function (_d, _t, row) { return statusSelect(row); } },
        { data: null, orderable: false, width: '140px', className: 'lw-col-nota', render: function (_d, _t, row) { return notePreviewCell(row); } },
        { data: null, orderable: false, searchable: false, width: '300px', className: 'lw-col-actions', render: function (_d, _t, row) {
          return inboxActionBtn(row) +
            convertClientBtn(row) +
            whatsAppActionBtn(row) +
            proyecViewBtn(row) +
            '<button type="button" class="btn btn-sm btn-outline-info lw-btn-form-share js-form-share mr-1" data-id="' + row.id + '" title="Enviar formulario de requerimientos"><i class="bi bi-ui-checks"></i></button>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary lw-btn-recorrido js-recorrido mr-1" data-id="' + row.id + '" title="Recorrido web"' + (row.has_session ? '' : ' disabled') + '><i class="bi bi-signpost-split"></i></button>' +
            '<button type="button" class="btn btn-sm btn-outline-primary lw-btn-notas js-notas mr-1" data-id="' + row.id + '" title="Notas"><i class="bi bi-chat-left-text"></i></button>' +
            '<button type="button" class="btn btn-sm btn-primary lw-btn-detalle js-detalle mr-1" data-id="' + row.id + '" title="Detalle"><i class="bi bi-eye"></i></button>' +
            '<button type="button" class="btn btn-sm btn-outline-danger lw-btn-delete js-delete" data-id="' + row.id + '" title="Dar de baja"><i class="bi bi-trash"></i></button>';
        }}
      ],
      language: window.VA_DT_LANG_ES || {
        url: 'https://cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json'
      },
      drawCallback: function () {
        selectedLeadId = null;
        $('#lwCheckAll').prop('checked', false).prop('indeterminate', false);
        updateInboxButton();
      }
    });

    $('#webLeadsTable tbody').on('click', 'tr', function (e) {
      if ($(e.target).closest('button, a, select, input, textarea, label').length) return;
      var row = table.row(this).data();
      selectLeadRow($(this), row);
    });

    $('#webLeadsTable tbody').on('dblclick', 'tr', function (e) {
      if ($(e.target).closest('button, a, select, input, textarea, label').length) return;
      var row = table.row(this).data();
      if (row && row.id) {
        window.location.href = INBOX_URL + '?id=' + row.id;
      }
    });

    $('#btnOpenInbox').on('click', function (e) {
      if (!selectedLeadId || $(this).hasClass('disabled')) {
        e.preventDefault();
        return;
      }
    });

    $('#btnDeleteLead').on('click', function () {
      var ids = getCheckedLeadIds();
      if (!ids.length && selectedLeadId) ids = [selectedLeadId];
      if (!ids.length) return;
      deleteLeads(ids, function () {
        selectedLeadId = null;
        $('#lwCheckAll').prop('checked', false).prop('indeterminate', false);
        updateInboxButton();
        table.ajax.reload(null, false);
      });
    });

    $('#lwCheckAll').on('change', function () {
      var checked = $(this).prop('checked');
      $('#webLeadsTable tbody .js-lead-check').prop('checked', checked);
      updateInboxButton();
    });

    $('#webLeadsTable').on('change', '.js-lead-check', function () {
      syncCheckAllState();
      updateInboxButton();
    });

    $('#webLeadsTable').on('click', '.js-delete', function (e) {
      e.preventDefault();
      e.stopPropagation();
      var id = parseInt($(this).attr('data-id'), 10);
      deleteLeads([id], function () {
        table.ajax.reload(null, false);
      });
    });

    $('#webLeadsTable').on('click', '.js-to-client', function (e) {
      e.preventDefault();
      e.stopPropagation();
      var id = parseInt($(this).attr('data-id'), 10);
      convertLeadToClient(id, function () {
        table.ajax.reload(null, false);
      });
    });

    $('#filterStatus, #filterOrigen').on('change', function () {
      $('#lwFilters').submit();
    });

    $('#webLeadsTable').on('click', '.js-detalle, .js-recorrido', function (e) {
      e.preventDefault();
      e.stopPropagation();
      openDetalleModal(getRowById($(this).attr('data-id')));
    });

    $('#webLeadsTable').on('click', '.js-notas', function (e) {
      e.preventDefault();
      e.stopPropagation();
      openNotasModal(getRowById($(this).attr('data-id')));
    });

    $('#webLeadsTable').on('click', '.js-form-share', function (e) {
      e.preventDefault();
      e.stopPropagation();
      openFormShareModal(getRowById($(this).attr('data-id')));
    });

    $('#webLeadsTable').on('click', '.js-proyec-view', function (e) {
      e.preventDefault();
      e.stopPropagation();
      openProyecViewModal(getRowById($(this).attr('data-id')));
    });

    $('input[name="formShareMode"]').on('change', function () {
      updateFormSharePanels();
      if (formShareState.leadId && !(formShareState.link && formShareState.link.url)) {
        bootstrapFormShareLink();
      }
    });

    $('#btnFormShareGenerate').on('click', function () {
      var $btn = $(this);
      $btn.prop('disabled', true);
      ensureFormShareLink($('#formShareRegenerate').is(':checked'))
        .done(function () {
          setFormShareStatus('Enlace listo para compartir', 'success');
        })
        .fail(function (xhr) {
          setFormShareStatus(formShareAjaxError(xhr, 'No se pudo generar el enlace'), 'danger');
        })
        .always(function () {
          $btn.prop('disabled', false);
        });
    });

    $('#btnFormShareCopy, #btnFormShareCopyFooter, #btnFormShareCopyBar').on('click', copyFormShareLink);
    $('#btnFormShareWhatsApp').on('click', shareFormWhatsApp);
    $('#btnFormShareSendEmail').on('click', sendFormShareEmail);

    $('#formShareSubject, #formShareMessage').on('input', function () {
      if (getFormShareMode() === 'email') {
        refreshFormShareEmailPreview();
      }
    });

    $('#webLeadsTable').on('change', '.js-status', function () {
      var id = $(this).attr('data-id');
      var estado = $(this).val();
      var $sel = $(this);
      $sel.removeClass('lw-st-lead lw-st-calificado lw-st-cierre lw-st-nuevo lw-st-cerrado').addClass(statusClass(estado));
      $.post(API + '/update_status.php', { id: id, pipeline_estado: estado })
        .done(function (res) {
          if (!res.success) {
            alert(res.message || 'No se pudo actualizar el estatus');
            table.ajax.reload(null, false);
          }
        })
        .fail(function () {
          alert('Error al actualizar estatus');
          table.ajax.reload(null, false);
        });
    });

    $('#formNota').on('submit', function (e) {
      e.preventDefault();
      var id = $('#notaLeadId').val();
      var nota = $('#nuevaNota').val().trim();
      if (!nota) return;

      var $btn = $('#btnGuardarNota');
      $btn.prop('disabled', true).text('Guardando…');

      $.ajax({
        url: API + '/add_note.php',
        method: 'POST',
        data: { id: id, nota: nota },
        dataType: 'json'
      }).done(function (res) {
        if (res.success) {
          $('#notasHistorial').html(renderNotas(res.notas_json));
          $('#nuevaNota').val('');
          if (rowsCache[id]) {
            rowsCache[id].notas_json = res.notas_json;
            rowsCache[id].ultima_nota = res.ultima_nota;
            rowsCache[id].ultima_nota_full = res.ultima_nota_full;
            rowsCache[id].total_notas = res.total_notas;
          }
          table.ajax.reload(null, false);
        } else {
          alert(res.message || 'No se pudo guardar la nota');
        }
      }).fail(function (xhr) {
        var msg = 'Error al guardar la nota';
        try {
          var j = JSON.parse(xhr.responseText);
          if (j.message) msg = j.message;
        } catch (err) { /* ignore */ }
        alert(msg);
      }).always(function () {
        $btn.prop('disabled', false).html('<i class="bi bi-plus-lg"></i> Guardar nota');
      });
    });

    $('#btnNuevoLead').on('click', function () {
      $('#formNuevoLead')[0].reset();
      $('#modalNuevoLead').modal('show');
      setTimeout(function () { $('#nuevoNombre').focus(); }, 350);
    });

    function applyNuevoTelefono(el, raw) {
      el.value = formatMxPhoneLocal(normalizeMxPhone(raw));
    }

    $('#nuevoTelefono').on('paste', function (e) {
      var clip = (e.originalEvent || e).clipboardData;
      var text = clip ? clip.getData('text') : '';
      if (!text) return;
      e.preventDefault();
      applyNuevoTelefono(this, text);
    });

    $('#nuevoTelefono').on('input blur', function () {
      applyNuevoTelefono(this, this.value);
    });

    $('#formNuevoLead').on('submit', function (e) {
      e.preventDefault();
      var tel = normalizeMxPhone($('#nuevoTelefono').val());
      $('#nuevoTelefono').val(tel);

      var $btn = $('#btnGuardarNuevoLead');
      $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm mr-1"></span> Guardando…');

      $.ajax({
        url: API + '/create.php',
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json'
      }).done(function (res) {
        if (res.success) {
          $('#modalNuevoLead').modal('hide');
          Swal.fire('Lead registrado', res.message || 'Registro manual guardado correctamente', 'success');
          if (table) table.ajax.reload(null, false);
        } else {
          Swal.fire('Error', res.message || 'No se pudo registrar el lead', 'error');
        }
      }).fail(function (xhr) {
        var msg = 'Error al registrar el lead';
        try {
          var j = JSON.parse(xhr.responseText);
          if (j.message) msg = j.message;
        } catch (err) { /* ignore */ }
        Swal.fire('Error', msg, 'error');
      }).always(function () {
        $btn.prop('disabled', false).html('<i class="bi bi-check-lg"></i> Guardar lead');
      });
    });
  });
})(jQuery);
</script>
</div><!-- menu container-fluid -->
</div><!-- menu content-wrapper -->
</body></html>
