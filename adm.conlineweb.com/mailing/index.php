<?php
/**
 * Módulo Mailing — plantillas, envío a clientes/leads y tracking.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/auth_middleware.php';
require_once dirname(__DIR__) . '/includes/adm_paths.php';
require_once dirname(__DIR__) . '/includes/cw_mailing_service.php';
require_once dirname(__DIR__) . '/conn.php';

if (!($conn instanceof mysqli)) {
    http_response_code(503);
    exit('Sin conexión a base de datos');
}

cw_mailing_migrate($conn);

$tab = trim((string) ($_GET['tab'] ?? 'plantillas'));
$allowedTabs = ['plantillas', 'clientes', 'leads', 'tracking'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'plantillas';
}

$stats = cw_mailing_stats($conn);

$admPageTitle = 'Mailing';
$admPageSubtitle = 'Plantillas, campañas a clientes/leads y tracking de aperturas y clics';
$admPageIcon = 'bi bi-mailbox';
$admHeaderVariant = 'compact';
$admBreadcrumbs = [
    ['label' => 'Inicio', 'href' => adm_href('index.php')],
    ['label' => 'Mailing'],
];
$admPageActions = '<button type="button" class="btn btn-primary btn-sm" id="btnNewTpl"><i class="bi bi-plus-lg"></i> Nueva plantilla</button>';

include dirname(__DIR__) . '/menu.php';
?>
<link rel="stylesheet" href="<?= adm_href('vendor/datatables/dataTables.bootstrap4.min.css') ?>">
<link rel="stylesheet" href="<?= adm_href('css/admin-datatables.css') ?>?v=20250716a">
<link rel="stylesheet" href="<?= adm_href('mailing/mailing.css') ?>?v=25">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="adm-page-shell ml-page" id="mlApp"
     data-api="<?= adm_href('mailing/api.php') ?>"
     data-upload="<?= adm_href('mailing/upload.php') ?>"
     data-tab="<?= htmlspecialchars($tab, ENT_QUOTES, 'UTF-8') ?>">
<div class="container-fluid px-0">
<?php include dirname(__DIR__) . '/includes/adm_page_header.php'; ?>

<section class="ml-kpis" aria-label="Resumen mailing">
  <article class="ml-kpi"><div class="ml-kpi__label">Plantillas</div><div class="ml-kpi__num" id="kpiTemplates"><?= (int) $stats['templates'] ?></div></article>
  <article class="ml-kpi"><div class="ml-kpi__label">Enviados</div><div class="ml-kpi__num" id="kpiSent"><?= (int) $stats['sent'] ?></div></article>
  <article class="ml-kpi"><div class="ml-kpi__label">Abiertos</div><div class="ml-kpi__num" id="kpiOpened"><?= (int) $stats['opened'] ?></div></article>
  <article class="ml-kpi"><div class="ml-kpi__label">Con clic</div><div class="ml-kpi__num" id="kpiClicked"><?= (int) $stats['clicked'] ?></div></article>
  <article class="ml-kpi"><div class="ml-kpi__label">Open rate</div><div class="ml-kpi__num" id="kpiOpenRate"><?= htmlspecialchars((string) $stats['open_rate'], ENT_QUOTES, 'UTF-8') ?>%</div></article>
  <article class="ml-kpi"><div class="ml-kpi__label">CTR</div><div class="ml-kpi__num" id="kpiClickRate"><?= htmlspecialchars((string) $stats['click_rate'], ENT_QUOTES, 'UTF-8') ?>%</div></article>
</section>

<nav class="ml-tabs" role="tablist" aria-label="Secciones mailing">
  <button type="button" class="ml-tab<?= $tab === 'plantillas' ? ' is-active' : '' ?>" data-tab="plantillas"><i class="bi bi-layout-text-window-reverse"></i> Plantillas</button>
  <button type="button" class="ml-tab<?= $tab === 'clientes' ? ' is-active' : '' ?>" data-tab="clientes"><i class="bi bi-people"></i> Clientes</button>
  <button type="button" class="ml-tab<?= $tab === 'leads' ? ' is-active' : '' ?>" data-tab="leads"><i class="bi bi-person-lines-fill"></i> Leads</button>
  <button type="button" class="ml-tab<?= $tab === 'tracking' ? ' is-active' : '' ?>" data-tab="tracking"><i class="bi bi-graph-up-arrow"></i> Tracking</button>
</nav>

<!-- Plantillas -->
<section class="ml-panel<?= $tab === 'plantillas' ? ' is-active' : '' ?>" id="panel-plantillas">
  <div class="ml-toolbar">
    <p class="ml-help mb-0">Crea o mejora con un prompt. Variables: <code>{nombre}</code> <code>{empresa}</code> <code>{correo}</code> <code>{telefono}</code></p>
    <button type="button" class="btn btn-primary btn-sm" id="btnNewTplPanel"><i class="bi bi-plus-lg"></i> Nueva plantilla</button>
  </div>

  <div class="ml-ai-card" id="mlAiCard">
    <div class="ml-ai-card__head">
      <div>
        <h3 class="ml-ai-card__title"><i class="bi bi-stars"></i> Crear plantilla con un prompt</h3>
        <p class="ml-help mb-0">El prompt manda el diseño y el copy. La IA arma un correo corporativo ConlineWeb (México); tú decides si guardarlo.</p>
      </div>
      <div class="ml-ai-conn" id="mlAiConn">
        <span class="ml-ai-conn__dot" id="mlAiConnDot" aria-hidden="true"></span>
        <div class="ml-ai-conn__text">
          <strong id="mlAiStatusBadge">Comprobando IA…</strong>
          <small id="mlAiStatusDetail">Validando configuración y conexión</small>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnAiPing" title="Probar conexión con OpenAI">
          <i class="bi bi-wifi"></i> Probar
        </button>
      </div>
    </div>
    <div class="form-group">
      <label for="mlAiPrompt">Prompt</label>
      <textarea class="form-control" id="mlAiPrompt" rows="4" placeholder="Ej. Correo de captación con precios de página web y tienda, 3 pasos y CTA WhatsApp. Si ya hay borrador: «pon los precios oficiales» o «añade Google y WhatsApp»."></textarea>
    </div>
    <div class="form-row">
      <div class="form-group col-md-4">
        <label for="mlAiTheme">Tema (opcional)</label>
        <input type="text" class="form-control" id="mlAiTheme" maxlength="80" placeholder="Hosting, SEO, Tendencias…">
      </div>
      <div class="form-group col-md-8">
        <label for="mlAiCta">CTA principal / URL (opcional)</label>
        <input type="url" class="form-control" id="mlAiCta" placeholder="https://conlineweb.com/… o WhatsApp">
      </div>
    </div>
    <div class="form-group">
      <label for="mlAiLinks">Links adicionales (varios, según corresponda)</label>
      <textarea class="form-control" id="mlAiLinks" rows="3" placeholder="Una por línea: Etiqueta | https://url&#10;WhatsApp | https://api.whatsapp.com/send?phone=5214771181285&amp;text=Hola&#10;Demos | https://conlineweb.com/demos-y-precios/&#10;Google | https://www.google.com/search?q=ConlineWeb"></textarea>
      <small class="ml-help">Se insertan como bloque de enlaces clickeables en el correo. Formato: <code>Etiqueta | https://…</code></small>
    </div>
    <div class="form-group">
      <label for="mlAiImages">Imágenes (hasta 3)</label>
      <input type="file" class="form-control-file" id="mlAiImages" accept="image/jpeg,image/png,image/gif,image/webp" multiple>
      <div class="ml-ai-thumbs" id="mlAiThumbs"></div>
    </div>
    <div class="ml-ai-actions">
      <button type="button" class="btn btn-primary" id="btnAiGenerate"><i class="bi bi-magic"></i> Generar con prompt</button>
      <button type="button" class="btn btn-outline-dark" id="btnAiExample"><i class="bi bi-stars"></i> Ver ejemplo</button>
      <button type="button" class="btn btn-outline-secondary d-none" id="btnAiPreview"><i class="bi bi-eye"></i> Ver preview</button>
      <button type="button" class="btn btn-outline-primary d-none" id="btnAiEditDraft"><i class="bi bi-pencil"></i> Editar borrador</button>
      <button type="button" class="btn btn-success d-none" id="btnAiSave"><i class="bi bi-save"></i> Guardar plantilla</button>
      <button type="button" class="btn btn-light d-none" id="btnAiDiscard">Descartar borrador</button>
    </div>
    <div class="ml-ai-draft d-none" id="mlAiDraftBox">
      <div class="ml-ai-draft__meta">
        <strong id="mlAiDraftTitle">—</strong>
        <span class="ml-help" id="mlAiDraftSubject"></span>
      </div>
      <p class="ml-help mb-0">Borrador listo. Revísalo y guárdalo solo si te convence.</p>
    </div>
  </div>

  <div class="ml-tpl-grid" id="mlTplGrid">
    <div class="ml-empty"><i class="bi bi-hourglass-split"></i>Cargando plantillas…</div>
  </div>
</section>

<!-- Clientes -->
<section class="ml-panel<?= $tab === 'clientes' ? ' is-active' : '' ?>" id="panel-clientes">
  <div class="ml-toolbar">
    <input type="search" class="form-control" id="mlSearchClientes" placeholder="Buscar cliente, empresa o correo…">
    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnPrintClientes"><i class="bi bi-printer"></i> Imprimir</button>
    <span class="ml-help">Mismos clientes activos que en Clientes · selecciona y envía con la barra inferior</span>
  </div>
  <div class="ml-card">
    <div class="table-responsive">
      <table class="table ml-table mb-0" id="mlClientesTable" width="100%">
        <thead>
          <tr>
            <th style="width:36px"><input type="checkbox" id="mlCheckAllClientes" title="Seleccionar visibles"></th>
            <th>ID</th>
            <th>Empresa</th>
            <th>Contacto</th>
            <th>Correo</th>
            <th>Teléfono</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="mlClientesBody">
          <tr><td colspan="7" class="ml-empty">Cargando…</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</section>

<!-- Leads -->
<section class="ml-panel<?= $tab === 'leads' ? ' is-active' : '' ?>" id="panel-leads">
  <div class="ml-toolbar">
    <input type="search" class="form-control" id="mlSearchLeads" placeholder="Buscar lead, empresa o correo…">
    <span class="ml-help">Solo leads website con correo válido</span>
  </div>
  <div class="ml-card">
    <div class="table-responsive">
      <table class="table ml-table mb-0">
        <thead>
          <tr>
            <th style="width:36px"><input type="checkbox" id="mlCheckAllLeads" title="Seleccionar visibles"></th>
            <th>Nombre</th>
            <th>Empresa</th>
            <th>Correo</th>
            <th>Pipeline</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="mlLeadsBody">
          <tr><td colspan="6" class="ml-empty">Cargando…</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</section>

<!-- Tracking -->
<section class="ml-panel<?= $tab === 'tracking' ? ' is-active' : '' ?>" id="panel-tracking">
  <div class="ml-toolbar">
    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRefreshTrack"><i class="bi bi-arrow-clockwise"></i> Actualizar</button>
    <button type="button" class="btn btn-sm btn-primary" id="btnProcessQueue"><i class="bi bi-play-fill"></i> Procesar cola pendiente</button>
    <span class="ml-help">Aperturas · clics · cronograma (hora México)</span>
  </div>

  <div class="ml-queue-block" data-queue-pos="top">
    <div class="ml-section-head">
      <h4 class="ml-section-title mb-0">Cronograma</h4>
      <span class="ml-queue-count">0</span>
    </div>
    <div class="ml-card mb-4">
      <div class="table-responsive">
        <table class="table ml-table mb-0">
          <thead>
            <tr>
              <th>ID</th>
              <th>Plantilla</th>
              <th>Destinatario</th>
              <th>Programado</th>
              <th>Estado</th>
            </tr>
          </thead>
          <tbody class="ml-queue-body">
            <tr><td colspan="5" class="ml-empty">Cargando…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="ml-section-head">
    <h4 class="ml-section-title mb-0">Historial de envíos</h4>
    <select id="mlTrackStatus" class="form-control form-control-sm ml-track-filter" aria-label="Filtrar por estado">
      <option value="">Todos los estados</option>
      <option value="Enviado">Enviado</option>
      <option value="Abierto">Abierto</option>
      <option value="Clic">Clic</option>
      <option value="Fallido">Fallido</option>
      <option value="Baja">Baja</option>
    </select>
  </div>
  <div class="ml-card ml-track-card mb-4">
      <table class="table ml-table ml-track-table mb-0" id="mlTrackTable">
        <thead>
          <tr>
            <th>ID</th>
            <th>Plantilla</th>
            <th>Destinatario</th>
            <th>Correo</th>
            <th>Estado</th>
            <th>Aperturas</th>
            <th>Clics</th>
            <th>Fecha</th>
          </tr>
        </thead>
        <tbody id="mlTrackBody">
          <tr><td colspan="8" class="ml-empty">Cargando…</td></tr>
        </tbody>
      </table>
  </div>

  <div class="ml-queue-block" data-queue-pos="bottom">
    <div class="ml-section-head">
      <h4 class="ml-section-title mb-0">Cronograma</h4>
      <span class="ml-queue-count">0</span>
    </div>
    <div class="ml-card">
      <div class="table-responsive">
        <table class="table ml-table mb-0">
          <thead>
            <tr>
              <th>ID</th>
              <th>Plantilla</th>
              <th>Destinatario</th>
              <th>Programado</th>
              <th>Estado</th>
            </tr>
          </thead>
          <tbody class="ml-queue-body">
            <tr><td colspan="5" class="ml-empty">Cargando…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</section>

<div class="ml-sendbar" id="mlSendBar" data-audience="cliente">
  <div class="ml-sendbar__count"><span id="mlSendCount">0</span> seleccionados</div>
  <button type="button" class="btn btn-light btn-sm" id="btnMlSend"><i class="bi bi-send-fill"></i> Asignar plantilla(s) y enviar</button>
</div>

</div>
</div>

<!-- Modal envío / programación multi-plantilla -->
<div class="modal fade ml-modal" id="mlAssignModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0">Asignar plantillas y horarios</h5>
          <small class="text-muted">Destinatarios: <span id="mlAssignCount">0</span> · <span id="mlAssignAudience">—</span></small>
        </div>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <p class="ml-help">Agrega una o más plantillas. Cada una tiene su propio momento de envío (ahora, fecha/hora o días+hora).</p>
        <div id="mlAssignRows" class="ml-assign-rows"></div>
        <button type="button" class="btn btn-sm btn-outline-primary" id="btnAssignAddRow"><i class="bi bi-plus-lg"></i> Agregar plantilla</button>
        <p class="ml-help mt-3 mb-0">Máximo 10 plantillas. Zona horaria: México. La cola se procesa con cron o en Tracking.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="btnAssignConfirm"><i class="bi bi-check2"></i> Confirmar envíos</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal plantilla -->
<div class="modal fade ml-modal" id="mlTplModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Plantilla de mailing</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="mlTplId">
        <div class="ml-ai-promptbox" id="mlTplAiBox">
          <label for="mlTplAiPrompt">Prompt de IA</label>
          <textarea class="form-control" id="mlTplAiPrompt" rows="3" placeholder="Al crear: describe el correo. Al editar: di cómo mejorarlo (más corto, más formal, destaca el CTA…)."></textarea>
          <div class="ml-ai-actions mt-2">
            <button type="button" class="btn btn-sm btn-primary" id="btnTplAiRun"><i class="bi bi-stars"></i> Aplicar prompt</button>
            <small class="ml-help mb-0">Si la plantilla está vacía, la crea. Si ya tiene contenido, la mejora. No se guarda sola.</small>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-8">
            <label for="mlTplTitle">Título interno</label>
            <input type="text" class="form-control" id="mlTplTitle" maxlength="180" placeholder="Ej. Promo hosting marzo">
          </div>
          <div class="form-group col-md-4">
            <label for="mlTplTheme">Tema</label>
            <input type="text" class="form-control" id="mlTplTheme" maxlength="80" placeholder="hosting, servicios, leads…">
          </div>
        </div>
        <div class="form-group">
          <label for="mlTplSubject">Asunto</label>
          <input type="text" class="form-control" id="mlTplSubject" maxlength="255" placeholder="Asunto del correo">
        </div>
        <div class="form-group">
          <label for="mlTplPreheader">Preheader</label>
          <input type="text" class="form-control" id="mlTplPreheader" maxlength="255" placeholder="Texto corto en bandeja de entrada">
        </div>
        <div class="form-group">
          <label for="mlTplBody">Cuerpo HTML</label>
          <textarea class="form-control" id="mlTplBody" rows="8" placeholder="<p>Hola {nombre}…</p>"></textarea>
          <small class="ml-help">Puedes usar HTML básico. Variables: {nombre} {empresa} {correo} {telefono}</small>
        </div>
        <div class="form-row">
          <div class="form-group col-md-8">
            <label for="mlTplImage">URL imagen / banner</label>
            <input type="url" class="form-control" id="mlTplImage" placeholder="https://… o sube un archivo">
          </div>
          <div class="form-group col-md-4">
            <label for="mlTplUpload">Subir imagen</label>
            <input type="file" class="form-control-file" id="mlTplUpload" accept="image/jpeg,image/png,image/gif,image/webp">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-5">
            <label for="mlTplCtaLabel">Texto botón CTA</label>
            <input type="text" class="form-control" id="mlTplCtaLabel" maxlength="120" placeholder="Ver más">
          </div>
          <div class="form-group col-md-7">
            <label for="mlTplCtaUrl">URL botón CTA</label>
            <input type="url" class="form-control" id="mlTplCtaUrl" placeholder="https://conlineweb.com/…">
          </div>
        </div>
        <div class="form-group">
          <label for="mlTplLinks">Links adicionales en el cuerpo</label>
          <textarea class="form-control" id="mlTplLinks" rows="3" placeholder="Etiqueta | https://url (una por línea)"></textarea>
          <small class="ml-help">Al guardar se añaden/actualizan como bloque de enlaces clickeables. Formato: <code>WhatsApp | https://…</code></small>
        </div>
        <div class="custom-control custom-switch">
          <input type="checkbox" class="custom-control-input" id="mlTplActive" checked>
          <label class="custom-control-label" for="mlTplActive">Plantilla activa (disponible para envío)</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="btnSaveTpl"><i class="bi bi-check2"></i> Guardar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal preview -->
<div class="modal fade ml-modal" id="mlPreviewModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0">Vista previa</h5>
          <small class="text-muted" id="mlPreviewSubject"></small>
          <div class="ml-help mb-0 mt-1">Tal cual se envía el correo (mismo HTML).</div>
        </div>
        <div class="ml-preview-switch" role="group" aria-label="Dispositivo">
          <button type="button" class="ml-preview-dev is-on" data-preview="desktop"><i class="bi bi-laptop"></i> PC</button>
          <button type="button" class="ml-preview-dev" data-preview="mobile"><i class="bi bi-phone"></i> Móvil</button>
        </div>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="ml-preview-stage is-desktop" id="mlPreviewStage">
          <iframe id="mlPreviewFrame" class="ml-preview-frame" title="Vista previa del correo"></iframe>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="<?= adm_href('vendor/datatables/jquery.dataTables.min.js') ?>"></script>
<script src="<?= adm_href('vendor/datatables/dataTables.bootstrap4.min.js') ?>"></script>
<script src="<?= adm_href('mailing/mailing.js') ?>?v=25"></script>
</div>
</div>
</body>
</html>
