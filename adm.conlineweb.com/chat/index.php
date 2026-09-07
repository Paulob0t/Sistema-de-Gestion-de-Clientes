<?php
require_once dirname(__DIR__) . '/auth_middleware.php';
require_once dirname(__DIR__) . '/conn.php';
require_once dirname(__DIR__) . '/includes/cw_hub_permissions.php';
require_once dirname(__DIR__) . '/includes/cw_chat_migrate.php';
require_once dirname(__DIR__) . '/includes/cw_chat_service.php';

cw_hub_require('hub.crm.view');
cw_chat_migrate($conn);

$q = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT k.*, c.nombre AS categoria_nombre FROM cw_chat_conocimiento k
        LEFT JOIN cw_chat_categorias c ON k.categoria_id = c.id WHERE 1=1';
$params = [];
$types = '';
if ($q !== '') {
    $sql .= ' AND (k.titulo LIKE ? OR k.pregunta LIKE ? OR k.palabras_clave LIKE ? OR k.intencion LIKE ?)';
    $like = '%' . $q . '%';
    $types = 'ssss';
    $params = [$like, $like, $like, $like];
}
$sql .= ' ORDER BY k.prioridad DESC, k.veces_usada DESC, k.id DESC LIMIT 500';
$stmt = $conn->prepare($sql);
$items = [];
if ($stmt) {
    if ($types) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();
}

$cats = [];
$cr = $conn->query('SELECT id, nombre FROM cw_chat_categorias WHERE activo = 1 ORDER BY orden, nombre');
if ($cr) {
    while ($row = $cr->fetch_assoc()) {
        $cats[] = $row;
    }
}

$unanswered = [];
$ur = $conn->query('SELECT id, mensaje, intent_detectado, veces, created_at, conversacion_id
    FROM cw_chat_preguntas_sin_respuesta WHERE resuelta = 0 ORDER BY veces DESC, COALESCE(updated_at, created_at) DESC LIMIT 50');
if ($ur) {
    while ($row = $ur->fetch_assoc()) {
        $unanswered[] = $row;
    }
}

$stats = [
    'total' => count($items),
    'activas' => 0,
    'categorias' => count($cats),
    'sin_respuesta' => count($unanswered),
];
foreach ($items as $it) {
    if ((int) ($it['activo'] ?? 0) === 1) {
        $stats['activas']++;
    }
}

include dirname(__DIR__) . '/menu.php';

$admPageTitle = 'Base de conocimiento del chatbot';
$admPageSubtitle = 'Respuestas automáticas, categorías y palabras clave del asistente virtual';
$admPageIcon = 'bi bi-journal-text';
$admPageActions = '<a href="' . adm_href('chat/estadisticas.php') . '" class="btn btn-outline-secondary btn-sm"><i class="bi bi-graph-up"></i> Estadísticas</a>'
    . '<a href="' . adm_href('chat/config.php') . '" class="btn btn-outline-secondary btn-sm"><i class="bi bi-gear"></i> Configuración</a>'
    . '<a href="' . adm_href('leads/inbox.php?tab=chat') . '" class="btn btn-primary btn-sm"><i class="bi bi-inbox"></i> Bandeja chat</a>';
?>
<div class="adm-page-shell chat-admin-page">
  <div class="container-fluid px-0">

    <?php include dirname(__DIR__) . '/includes/adm_page_header.php'; ?>

    <div class="ch-stats-row">
      <div class="ch-stat-card"><i class="bi bi-journal-text"></i><div><strong><?= (int) $stats['total'] ?></strong><span>Respuestas totales</span></div></div>
      <div class="ch-stat-card"><i class="bi bi-check-circle"></i><div><strong><?= (int) $stats['activas'] ?></strong><span>Activas</span></div></div>
      <div class="ch-stat-card"><i class="bi bi-folder"></i><div><strong><?= (int) $stats['categorias'] ?></strong><span>Categorías</span></div></div>
      <div class="ch-stat-card"><i class="bi bi-question-circle"></i><div><strong><?= (int) $stats['sin_respuesta'] ?></strong><span>Sin respuesta</span></div></div>
    </div>

    <?php if ($unanswered): ?>
    <div class="card ch-card">
      <div class="card-body">
        <h2 class="h6 mb-2">Preguntas sin respuesta <span class="badge badge-warning text-dark"><?= count($unanswered) ?></span></h2>
        <p class="text-muted small mb-3">Consultas que el chatbot no pudo resolver. Úsalas para crear nuevas entradas en la base de conocimiento.</p>
        <div class="table-responsive">
          <table class="table table-sm ch-table mb-0">
            <thead>
              <tr>
                <th>Pregunta del cliente</th>
                <th>Intención</th>
                <th>Veces</th>
                <th>Fecha</th>
                <th class="text-right">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($unanswered as $uq): ?>
              <tr data-unanswered-id="<?= (int) $uq['id'] ?>">
                <td><?= htmlspecialchars(mb_substr($uq['mensaje'], 0, 120)) ?><?= mb_strlen($uq['mensaje']) > 120 ? '…' : '' ?></td>
                <td><code><?= htmlspecialchars($uq['intent_detectado'] ?? '—') ?></code></td>
                <td><?= (int) $uq['veces'] ?></td>
                <td><small class="text-muted"><?= htmlspecialchars($uq['created_at']) ?></small></td>
                <td class="text-right text-nowrap">
                  <button type="button" class="btn btn-sm btn-outline-success js-use-unanswered"
                    data-text="<?= htmlspecialchars($uq['mensaje'], ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-plus-circle"></i> Crear</button>
                  <button type="button" class="btn btn-sm btn-outline-secondary js-resolve-unanswered" data-id="<?= (int) $uq['id'] ?>">Resuelta</button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="card ch-card">
      <div class="card-body">
        <div class="ch-toolbar">
          <form class="ch-search-form" method="get" action="<?= adm_href('chat/index.php') ?>">
            <input type="search" name="q" class="form-control" placeholder="Buscar por título, pregunta, palabras clave…" value="<?= htmlspecialchars($q) ?>">
            <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i> Buscar</button>
            <?php if ($q !== ''): ?>
            <a href="<?= adm_href('chat/index.php') ?>" class="btn btn-light">Limpiar</a>
            <?php endif; ?>
          </form>
          <div class="ch-toolbar__actions">
            <button type="button" class="btn btn-outline-secondary" id="btnImportarFaqs" title="Carga el lote inicial de FAQs (solo agrega las que faltan)"><i class="bi bi-cloud-download"></i> Importar FAQs</button>
            <button type="button" class="btn btn-success" id="btnNuevaRespuesta"><i class="bi bi-plus-lg"></i> Nueva respuesta</button>
          </div>
        </div>

        <?php if ($q !== ''): ?>
        <p class="text-muted small mb-3">Mostrando resultados para: <strong><?= htmlspecialchars($q) ?></strong> (<?= count($items) ?>)</p>
        <?php endif; ?>

        <?php if ($items): ?>
        <div class="table-responsive">
          <table class="table table-hover ch-table mb-0">
            <thead>
              <tr>
                <th>Título / Pregunta</th>
                <th>Categoría</th>
                <th>Intención</th>
                <th>Usos</th>
                <th>Estado</th>
                <th class="text-right">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $item):
                $preview = mb_substr((string) ($item['pregunta'] ?? ''), 0, 90);
                $itemB64 = base64_encode(json_encode($item, JSON_UNESCAPED_UNICODE));
              ?>
              <tr data-knowledge-id="<?= (int) $item['id'] ?>">
                <td class="ch-title-cell">
                  <strong><?= htmlspecialchars($item['titulo']) ?></strong>
                  <small class="text-muted"><?= htmlspecialchars($preview) ?><?= mb_strlen((string) ($item['pregunta'] ?? '')) > 90 ? '…' : '' ?></small>
                </td>
                <td><?= htmlspecialchars($item['categoria_nombre'] ?? '—') ?></td>
                <td><code><?= htmlspecialchars($item['intencion'] ?? '—') ?></code></td>
                <td><?= (int) $item['veces_usada'] ?></td>
                <td>
                  <?php if ((int) $item['activo']): ?>
                  <span class="badge badge-success">Activa</span>
                  <?php else: ?>
                  <span class="badge badge-secondary">Inactiva</span>
                  <?php endif; ?>
                </td>
                <td class="text-right ch-actions-cell">
                  <button type="button" class="btn btn-sm btn-outline-primary js-edit-knowledge"
                    data-item="<?= htmlspecialchars($itemB64, ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-pencil"></i> Editar</button>
                  <button type="button" class="btn btn-sm btn-outline-danger js-delete-knowledge"
                    data-id="<?= (int) $item['id'] ?>"
                    data-title="<?= htmlspecialchars($item['titulo'], ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-trash"></i></button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
        <div class="ch-empty">
          <i class="bi bi-robot"></i>
          <p class="mb-2"><strong>No hay respuestas<?= $q !== '' ? ' para esta búsqueda' : '' ?>.</strong></p>
          <p class="mb-3 small">Agrega la primera entrada para que el chatbot pueda responder automáticamente.</p>
          <button type="button" class="btn btn-primary js-empty-new"><i class="bi bi-plus-lg"></i> Crear primera respuesta</button>
        </div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<div class="modal fade" id="modalConocimiento" tabindex="-1" role="dialog" aria-labelledby="modalConocimientoTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
    <div class="modal-content">
      <form id="formConocimiento">
        <div class="modal-header">
          <h5 class="modal-title" id="modalConocimientoTitle">Respuesta automática</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="kId" value="0">
          <div class="form-row">
            <div class="form-group col-md-8">
              <label for="kTitulo">Título</label>
              <input class="form-control" name="titulo" id="kTitulo" required maxlength="255">
            </div>
            <div class="form-group col-md-4">
              <label for="kCategoria">Categoría</label>
              <select class="form-control" name="categoria_id" id="kCategoria">
                <option value="">Sin categoría</option>
                <?php foreach ($cats as $cat): ?>
                <option value="<?= (int) $cat['id'] ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label for="kPregunta">Pregunta / disparador</label>
            <textarea class="form-control" name="pregunta" id="kPregunta" rows="2" required placeholder="Ej. ¿Cómo renuevo mi hosting?"></textarea>
          </div>
          <div class="form-group">
            <label for="kRespuesta">Respuesta del bot</label>
            <textarea class="form-control" name="respuesta" id="kRespuesta" rows="5" required placeholder="Texto que verá el cliente cuando el chatbot detecte esta consulta…"></textarea>
          </div>
          <div class="form-row">
            <div class="form-group col-md-6">
              <label for="kKeywords">Palabras clave (separadas por coma)</label>
              <input class="form-control" name="palabras_clave" id="kKeywords" placeholder="hosting, renovar, pago">
            </div>
            <div class="form-group col-md-3">
              <label for="kIntencion">Intención</label>
              <input class="form-control" name="intencion" id="kIntencion" placeholder="hosting">
            </div>
            <div class="form-group col-md-3">
              <label for="kPrioridad">Prioridad</label>
              <input type="number" class="form-control" name="prioridad" id="kPrioridad" value="10" min="0" max="100">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-4">
              <label for="kActivo">Estado</label>
              <select class="form-control" name="activo" id="kActivo">
                <option value="1">Activa</option>
                <option value="0">Inactiva</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary" id="btnGuardarConocimiento"><i class="bi bi-check-lg"></i> Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div id="chToast" class="ch-toast" role="status" aria-live="polite"></div>

<script>
(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  ready(function () {
    var $ = window.jQuery;
    if (!$) {
      console.error('jQuery no disponible');
      return;
    }

  var modal = document.getElementById('modalConocimiento');
  var form = document.getElementById('formConocimiento');
  var toast = document.getElementById('chToast');
  var saveUrl = <?= json_encode(adm_href('chat/api/guardar_conocimiento.php'), JSON_UNESCAPED_SLASHES) ?>;
  var deleteUrl = <?= json_encode(adm_href('chat/api/eliminar_conocimiento.php'), JSON_UNESCAPED_SLASHES) ?>;
  var importUrl = <?= json_encode(adm_href('chat/api/importar_faqs.php'), JSON_UNESCAPED_SLASHES) ?>;
  var resolveUrl = <?= json_encode(adm_href('chat/api/resolver_pregunta.php'), JSON_UNESCAPED_SLASHES) ?>;

  if (modal && modal.parentElement !== document.body) {
    document.body.appendChild(modal);
  }

  function showToast(msg, ok) {
    if (!toast) return;
    toast.textContent = msg;
    toast.className = 'ch-toast is-visible ' + (ok ? 'ch-toast--ok' : 'ch-toast--err');
    clearTimeout(showToast._t);
    showToast._t = setTimeout(function () {
      toast.classList.remove('is-visible');
    }, 3200);
  }

  function showModal() {
    $('#modalConocimiento').modal('show');
  }

  function hideModal() {
    $('#modalConocimiento').modal('hide');
  }

  function parseItem(raw) {
    if (!raw) return {};
    try {
      return JSON.parse(atob(raw));
    } catch (e) {
      showToast('No se pudo leer el registro para editar.', false);
      return {};
    }
  }

  function fillForm(item) {
    $('#kId').val(item.id || 0);
    $('#kTitulo').val(item.titulo || '');
    $('#kCategoria').val(item.categoria_id || '');
    $('#kPregunta').val(item.pregunta || '');
    $('#kRespuesta').val(item.respuesta || '');
    $('#kKeywords').val(item.palabras_clave || '');
    $('#kIntencion').val(item.intencion || '');
    $('#kPrioridad').val(item.prioridad != null ? item.prioridad : 10);
    $('#kActivo').val(String(item.activo != null ? item.activo : 1));
    $('#modalConocimientoTitle').text((item.id && item.id > 0) ? 'Editar respuesta' : 'Nueva respuesta');
  }

  function openNewForm() {
    fillForm({ activo: 1, prioridad: 10 });
    showModal();
  }

  $('#btnNuevaRespuesta, .js-empty-new').on('click', openNewForm);

  $(document).on('click', '.js-edit-knowledge', function () {
    var item = parseItem(this.getAttribute('data-item'));
    if (!item || !item.id) return;
    fillForm(item);
    showModal();
  });

  $(document).on('click', '.js-use-unanswered', function () {
    var text = this.getAttribute('data-text') || '';
    fillForm({
      pregunta: text,
      titulo: text.length > 60 ? text.substring(0, 60) + '…' : text,
      respuesta: '',
      activo: 1,
      prioridad: 15
    });
    showModal();
  });

  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var $btn = $('#btnGuardarConocimiento');
      $btn.prop('disabled', true);
      var fd = new FormData(form);
      fetch(saveUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) {
          if (!r.ok) throw new Error('HTTP ' + r.status);
          return r.json();
        })
        .then(function (res) {
          if (res.success) {
            hideModal();
            showToast('Respuesta guardada correctamente', true);
            setTimeout(function () { location.reload(); }, 700);
          } else {
            showToast(res.message || 'Error al guardar', false);
          }
        })
        .catch(function () {
          showToast('Error de conexión al guardar', false);
        })
        .finally(function () {
          $btn.prop('disabled', false);
        });
    });
  }

  $(document).on('click', '.js-delete-knowledge', function () {
    var id = this.getAttribute('data-id');
    var title = this.getAttribute('data-title') || 'esta respuesta';
    if (!id || !window.confirm('¿Desactivar «' + title + '»?\n\nEl chatbot dejará de usarla. Puedes reactivarla editándola.')) {
      return;
    }
    var $row = $('[data-knowledge-id="' + id + '"]');
    var fd = new FormData();
    fd.append('id', id);
    fetch(deleteUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res.success) {
          $row.fadeOut(200, function () { $(this).remove(); });
          showToast(res.message || 'Respuesta desactivada', true);
        } else {
          showToast(res.message || 'No se pudo eliminar', false);
        }
      })
      .catch(function () {
        showToast('Error de conexión al eliminar', false);
      });
  });

  $('#btnImportarFaqs').on('click', function () {
    var $btn = $(this);
    if (!window.confirm('¿Importar el lote inicial de FAQs?\n\nSolo se agregarán entradas que aún no existan (por intención).')) {
      return;
    }
    $btn.prop('disabled', true);
    fetch(importUrl, { method: 'POST', credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res.success) {
          showToast(res.message + (res.total_activas ? ' Total activas: ' + res.total_activas : ''), true);
          if (res.inserted > 0) {
            setTimeout(function () { location.reload(); }, 900);
          }
        } else {
          showToast(res.message || 'Error al importar', false);
        }
      })
      .catch(function () {
        showToast('Error de conexión al importar', false);
      })
      .finally(function () {
        $btn.prop('disabled', false);
      });
  });

  $(document).on('click', '.js-resolve-unanswered', function () {
    var id = this.getAttribute('data-id');
    var $row = $('[data-unanswered-id="' + id + '"]');
    var fd = new FormData();
    fd.append('id', id);
    fetch(resolveUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res.success) {
          $row.remove();
          showToast('Pregunta marcada como resuelta', true);
        } else {
          showToast('No se pudo marcar como resuelta', false);
        }
      })
      .catch(function () {
        showToast('Error de conexión', false);
      });
  });

  });
})();
</script>
</div><!-- menu container-fluid -->
</div><!-- menu content-wrapper -->
</body></html>
