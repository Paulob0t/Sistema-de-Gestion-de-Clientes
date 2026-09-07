<?php
require_once dirname(__DIR__) . '/conn.php';
require_once dirname(__DIR__) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__) . '/includes/cw_hub_permissions.php';
require_once dirname(__DIR__) . '/includes/cw_chat_migrate.php';

cw_hub_migrate($conn);
cw_chat_migrate($conn);
cw_hub_ensure_session();

$selectedId = (int) ($_GET['id'] ?? 0);
$leadIdParam = (int) ($_GET['lead_id'] ?? 0);
$tabParam = $_GET['tab'] ?? null;

// Deep-link desde alertas web: ?lead_id= → conversación canal web
if ($selectedId <= 0 && $leadIdParam > 0) {
    $find = $conn->prepare("SELECT id FROM cw_chat_conversaciones
        WHERE lead_id = ? AND canal = 'web'
        ORDER BY COALESCE(ultimo_mensaje_at, iniciada_at) DESC LIMIT 1");
    if ($find) {
        $find->bind_param('i', $leadIdParam);
        $find->execute();
        $found = $find->get_result()->fetch_assoc();
        $find->close();
        if ($found) {
            $selectedId = (int) $found['id'];
            if ($tabParam === null) {
                $tabParam = 'sesiones';
            }
        }
    }
}

if ($tabParam === 'chat') {
    $activeTab = 'chat';
} elseif ($tabParam === 'sesiones') {
    $activeTab = 'sesiones';
} elseif ($tabParam === 'leads') {
    $activeTab = 'leads';
} elseif ($selectedId > 0) {
    $activeTab = 'leads';
    $chk = $conn->prepare('SELECT id, canal FROM cw_chat_conversaciones WHERE id = ? LIMIT 1');
    if ($chk) {
        $chk->bind_param('i', $selectedId);
        $chk->execute();
        $chkRow = $chk->get_result()->fetch_assoc();
        $chk->close();
        if ($chkRow) {
            $activeTab = (($chkRow['canal'] ?? '') === 'web') ? 'sesiones' : 'chat';
        }
    }
} else {
    $activeTab = 'leads';
}

include dirname(__DIR__) . '/menu.php';
?>
<link href="<?= adm_href('leads/css/leads-inbox.css') ?>?v=11" rel="stylesheet">
<script src="<?= adm_href('vendor/jquery/jquery.min.js') ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="adm-page-shell crm-inbox-page">
<div class="container-fluid px-0">
  <div class="crm-inbox-shell">
    <aside class="crm-sidebar" aria-label="Listado de leads">
      <div class="crm-sidebar-head">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h1 class="h5 mb-0">Centro de conversaciones</h1>
          <div class="crm-head-links">
            <a href="<?= adm_href('chat/index.php') ?>" class="btn btn-sm btn-outline-secondary" title="Base de conocimiento"><i class="bi bi-robot"></i></a>
            <a href="<?= adm_href('chat/estadisticas.php') ?>" class="btn btn-sm btn-outline-secondary" title="Estadísticas"><i class="bi bi-graph-up"></i></a>
            <a href="<?= adm_href('leads/index.php') ?>" class="btn btn-sm btn-outline-secondary" title="Vista tabla"><i class="bi bi-table"></i></a>
            <a href="<?= adm_href('leads/kanban.php') ?>" class="btn btn-sm btn-outline-secondary" title="Kanban"><i class="bi bi-kanban"></i></a>
          </div>
        </div>
        <div class="crm-inbox-tabs mb-2">
          <button type="button" class="crm-inbox-tab <?= $activeTab === 'leads' ? 'active' : '' ?>" data-tab="leads">Leads CRM</button>
          <button type="button" class="crm-inbox-tab <?= $activeTab === 'sesiones' ? 'active' : '' ?>" data-tab="sesiones">
            Sesiones <span class="crm-tab-badge" id="crmSesionesTabBadge" hidden>0</span>
          </button>
          <button type="button" class="crm-inbox-tab <?= $activeTab === 'chat' ? 'active' : '' ?>" data-tab="chat">
            Chat en vivo <span class="crm-tab-badge" id="crmChatTabBadge" hidden>0</span>
          </button>
        </div>
        <input type="search" id="crmSearch" class="form-control form-control-sm" placeholder="Buscar…" autocomplete="off">
        <div class="crm-filter-chips mt-2" id="crmFilterLeads">
          <button type="button" class="crm-chip active" data-filter="">Todos</button>
          <button type="button" class="crm-chip" data-filter="por_revisar">Por revisar</button>
          <button type="button" class="crm-chip" data-filter="pendientes">Pendientes</button>
          <button type="button" class="crm-chip" data-filter="sin_seguimiento">Sin seguimiento</button>
        </div>
        <div class="crm-filter-chips mt-2" id="crmFilterChat" hidden>
          <button type="button" class="crm-chip active" data-filter="">Todos</button>
          <button type="button" class="crm-chip" data-filter="por_revisar">No leídas</button>
          <button type="button" class="crm-chip" data-filter="pendientes">Pendientes</button>
          <button type="button" class="crm-chip" data-filter="sin_respuesta">Sin respuesta</button>
          <button type="button" class="crm-chip" data-filter="atendidas">Atendidas</button>
          <button type="button" class="crm-chip" data-filter="bot">Chatbot</button>
          <button type="button" class="crm-chip" data-filter="cerradas">Cerradas</button>
        </div>
        <div class="crm-filter-chips mt-2" id="crmFilterSesiones" hidden>
          <button type="button" class="crm-chip active" data-filter="">Todas</button>
          <button type="button" class="crm-chip" data-filter="en_vivo">En vivo</button>
          <button type="button" class="crm-chip" data-filter="sin_registro">Sin registrar</button>
          <button type="button" class="crm-chip" data-filter="por_revisar">Por revisar</button>
          <button type="button" class="crm-chip" data-filter="humano">Atendiendo</button>
          <button type="button" class="crm-chip" data-filter="cerradas">Cerradas</button>
        </div>
        <div class="crm-stats mt-2" id="crmStats"></div>
        <div class="crm-bulk-bar mt-2" id="crmBulkBar" hidden>
          <label class="crm-bulk-check-all mb-0">
            <input type="checkbox" id="crmChatCheckAll" title="Seleccionar todos visibles">
            <span>Todos</span>
          </label>
          <button type="button" id="btnDeleteChats" class="btn btn-sm btn-outline-danger" disabled>
            <i class="bi bi-trash"></i> Eliminar <span id="btnDeleteChatsCount" class="d-none"></span>
          </button>
        </div>
      </div>
      <div class="crm-lead-list" id="crmLeadList" role="listbox" aria-label="Leads"></div>
    </aside>

    <main class="crm-main" id="crmMain">
      <div class="crm-empty" id="crmEmpty">
        <i class="bi bi-chat-left-text"></i>
        <h2 id="crmEmptyTitle">Selecciona un lead</h2>
        <p id="crmEmptyText">Elige un registro del panel izquierdo para ver la conversación, historial, notas y archivos.</p>
      </div>
      <div class="crm-conversation-panel" id="crmConversationPanel" hidden></div>
    </main>
  </div>
</div>
</div>

<script>
(function ($) {
  'use strict';

  var API = window.ADM_BASE + '/leads/api';
  var selectedId = <?= (int) $selectedId ?>;
  var currentTab = '<?= $activeTab ?>';
  var currentFilter = '';
  var searchTimer = null;
  var chatEs = null;
  var chatListEs = null;
  var chatLastId = 0;
  var chatVersion = 0;
  var chatTypingTimer = null;
  var chatListSince = new Date(Date.now() - 120000).toISOString().slice(0, 19).replace('T', ' ');
  var listLoadTimer = null;
  var listXhr = null;
  var listLoadSeq = 0;

  function showListLoading() {
    $('#crmLeadList').html(
      '<div class="crm-list-loading"><span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Cargando…</div>'
    );
  }

  function isLiveChatTab(tab) {
    return tab === 'chat' || tab === 'sesiones';
  }

  function resetFiltersForTab(tab) {
    currentFilter = '';
    $('#crmFilterLeads .crm-chip, #crmFilterChat .crm-chip, #crmFilterSesiones .crm-chip').removeClass('active');
    var filterId = tab === 'chat' ? 'Chat' : (tab === 'sesiones' ? 'Sesiones' : 'Leads');
    $('#crmFilter' + filterId + ' .crm-chip').first().addClass('active');
  }

  function applyTabUI(tab) {
    $('#crmFilterLeads').attr('hidden', tab !== 'leads');
    $('#crmFilterChat').attr('hidden', tab !== 'chat');
    $('#crmFilterSesiones').attr('hidden', tab !== 'sesiones');
    $('#crmBulkBar').attr('hidden', !isLiveChatTab(tab));
    if (!isLiveChatTab(tab)) {
      $('#crmChatCheckAll').prop('checked', false).prop('indeterminate', false);
      updateChatBulkUI();
    }
    if (tab === 'sesiones') {
      $('#crmEmptyTitle').text('Selecciona una sesión');
      $('#crmEmptyText').text('Elige una sesión web del panel izquierdo para ver el chat en vivo y escribirle al visitante, aunque aún no se haya registrado.');
      $('#crmSearch').attr('placeholder', 'Buscar sesión, página o ID…');
    } else if (tab === 'chat') {
      $('#crmEmptyTitle').text('Selecciona una conversación');
      $('#crmEmptyText').text('Elige un chat del panel izquierdo para ver mensajes y contexto del cliente.');
      $('#crmSearch').attr('placeholder', 'Buscar…');
    } else {
      $('#crmEmptyTitle').text('Selecciona un lead');
      $('#crmEmptyText').text('Elige un registro del panel izquierdo para ver la conversación, historial, notas y archivos.');
      $('#crmSearch').attr('placeholder', 'Buscar…');
    }
  }

  function setActiveTab(tab) {
    currentTab = tab;
    $('.crm-inbox-tab').removeClass('active');
    $('.crm-inbox-tab[data-tab="' + tab + '"]').addClass('active');
    applyTabUI(tab);
  }

  function syncFromUrl() {
    var params = new URLSearchParams(location.search);
    var rawTab = params.get('tab') || '';
    var tab = rawTab === 'chat' || rawTab === 'sesiones' ? rawTab : 'leads';
    setActiveTab(tab);
    return {
      tab: tab,
      id: parseInt(params.get('id'), 10) || 0
    };
  }

  function esc(s) {
    return String(s || '').replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }

  function escHtml(s) {
    return String(s || '').replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }

  function confirmDeleteLead(id, nombre, onSuccess) {
    var label = nombre ? ('«' + nombre + '» (ID ' + id + ')') : ('ID ' + id);
    Swal.fire({
      title: '¿Dar de baja este lead?',
      html: 'Se ocultará <strong>' + escHtml(label) + '</strong> de la bandeja.<br><span style="color:#64748b;font-size:0.9em;">No se elimina permanentemente de la base de datos.</span>',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#dc2626',
      cancelButtonColor: '#64748b',
      confirmButtonText: 'Sí, dar de baja',
      cancelButtonText: 'Cancelar'
    }).then(function (result) {
      if (!result.isConfirmed) return;
      $.ajax({
        url: API + '/inbox_delete.php',
        method: 'POST',
        data: { id: id },
        dataType: 'json'
      }).done(function (res) {
        if (res.success) {
          Swal.fire('Dado de baja', res.message || 'Lead ocultado correctamente', 'success');
          if (onSuccess) onSuccess();
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

  function getCheckedChatIds() {
    var ids = [];
    $('#crmLeadList .js-chat-check:checked').each(function () {
      var id = parseInt($(this).val(), 10);
      if (id > 0) ids.push(id);
    });
    return ids;
  }

  function syncChatCheckAll() {
    var $all = $('#crmChatCheckAll');
    var $boxes = $('#crmLeadList .js-chat-check');
    var total = $boxes.length;
    var checked = $boxes.filter(':checked').length;
    $all.prop('checked', total > 0 && checked === total);
    $all.prop('indeterminate', checked > 0 && checked < total);
  }

  function updateChatBulkUI() {
    var ids = getCheckedChatIds();
    var $btn = $('#btnDeleteChats');
    var $count = $('#btnDeleteChatsCount');
    $btn.prop('disabled', ids.length === 0);
    if (ids.length > 1) {
      $count.removeClass('d-none').text('(' + ids.length + ')');
    } else {
      $count.addClass('d-none').text('');
    }
    syncChatCheckAll();
  }

  function confirmDeleteChats(ids, onSuccess) {
    ids = (ids || []).map(function (id) { return parseInt(id, 10); }).filter(function (id) { return id > 0; });
    if (!ids.length) return;

    var title = ids.length === 1 ? '¿Eliminar este chat?' : ('¿Eliminar ' + ids.length + ' chats?');
    var html = ids.length === 1
      ? 'Se ocultará el chat <strong>#' + ids[0] + '</strong> de la bandeja.<br><span style="color:#64748b;font-size:0.9em;">No se borra permanentemente; deja de aparecer en Chat en vivo / Sesiones.</span>'
      : 'Se ocultarán <strong>' + ids.length + ' chats</strong> de la bandeja.<br><span style="color:#64748b;font-size:0.9em;">No se borran permanentemente.</span>';

    Swal.fire({
      title: title,
      html: html,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#dc2626',
      cancelButtonColor: '#64748b',
      confirmButtonText: ids.length === 1 ? 'Sí, eliminar' : ('Sí, eliminar (' + ids.length + ')'),
      cancelButtonText: 'Cancelar'
    }).then(function (result) {
      if (!result.isConfirmed) return;
      $.ajax({
        url: API + '/chat_delete.php',
        method: 'POST',
        data: { ids: ids },
        dataType: 'json'
      }).done(function (res) {
        if (res.success) {
          Swal.fire('Eliminado', res.message || 'Chat(s) ocultado(s)', 'success');
          if (onSuccess) onSuccess(ids);
        } else {
          Swal.fire('Error', res.message || 'No se pudo eliminar', 'error');
        }
      }).fail(function (xhr) {
        var msg = 'Error al eliminar';
        try {
          var j = JSON.parse(xhr.responseText);
          if (j.message) msg = j.message;
        } catch (err) { /* ignore */ }
        Swal.fire('Error', msg, 'error');
      });
    });
  }

  function afterChatsDeleted(ids) {
    ids = ids || [];
    if (ids.indexOf(selectedId) !== -1) {
      selectedId = 0;
      stopConversationStream();
      var tabForUrl = currentTab === 'chat' ? 'chat' : 'sesiones';
      history.replaceState({ tab: tabForUrl, id: 0 }, '', window.ADM_BASE + '/leads/inbox.php?tab=' + tabForUrl);
      $('#crmConversationPanel').attr('hidden', true).empty();
      $('#crmEmpty').removeAttr('hidden');
    }
    $('#crmChatCheckAll').prop('checked', false).prop('indeterminate', false);
    updateChatBulkUI();
    loadList();
  }

  function formatDate(s) {
    if (!s) return '—';
    var d = new Date(String(s).replace(' ', 'T'));
    if (isNaN(d.getTime())) return s;
    return d.toLocaleDateString('es-MX', { day: '2-digit', month: 'short' }) + ' · ' +
      d.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
  }

  function renderStats(stats) {
    stats = stats || {};
    if (isLiveChatTab(currentTab)) {
      var label = currentTab === 'sesiones' ? 'sesiones' : 'chats';
      $('#crmStats').html(
        '<span class="crm-stat"><strong>' + (stats.total || 0) + '</strong> ' + label + '</span>' +
        '<span class="crm-stat warn"><strong>' + (stats.por_revisar || 0) + '</strong> por revisar</span>' +
        '<span class="crm-stat"><strong>' + (stats.pendientes || 0) + '</strong> pendientes</span>' +
        '<span class="crm-stat"><strong>' + (stats.sin_responder || stats.sin_respuesta || 0) + '</strong> sin respuesta</span>' +
        '<span class="crm-stat"><strong>' + (stats.nuevas || 0) + '</strong> nuevas hoy</span>'
      );
      var badge = (stats.por_revisar || 0) + (stats.sin_responder || stats.sin_respuesta || 0);
      var $badge = currentTab === 'sesiones' ? $('#crmSesionesTabBadge') : $('#crmChatTabBadge');
      if (badge > 0) {
        $badge.text(badge > 9 ? '9+' : badge).removeAttr('hidden');
      } else {
        $badge.attr('hidden', true);
      }
      return;
    }
    $('#crmStats').html(
      '<span class="crm-stat"><strong>' + (stats.total || 0) + '</strong> leads</span>' +
      '<span class="crm-stat warn"><strong>' + (stats.por_revisar || 0) + '</strong> por revisar</span>' +
      '<span class="crm-stat"><strong>' + (stats.pendientes || 0) + '</strong> pendientes</span>'
    );
  }

  function renderChatItem(row) {
    var badges = '';
    if (row.por_revisar) badges += '<span class="crm-badge-count">1</span>';
    if (row.sin_respuesta) badges += '<span class="crm-flag crm-flag-warn" title="Sin respuesta">!</span>';
    if (row.sin_registro) badges += '<span class="crm-flag" title="Sin registrar">anon</span>';
    var active = selectedId === row.id ? ' is-active' : '';
    var unread = row.por_revisar ? ' is-unread' : '';
    var metaExtra = '';
    if (currentTab === 'sesiones') {
      if (row.session_short) metaExtra += ' · ses ' + esc(row.session_short);
      if (row.pagina_origen) {
        try {
          var pu = new URL(row.pagina_origen);
          metaExtra += ' · ' + esc(pu.pathname.replace(/^\//, '') || 'inicio');
        } catch (e) { /* ignore */ }
      }
    }
    return '<div class="crm-lead-row" data-id="' + row.id + '" data-kind="chat">' +
      '<label class="crm-lead-check" title="Seleccionar">' +
        '<input type="checkbox" class="js-chat-check" value="' + row.id + '" aria-label="Seleccionar chat ' + row.id + '">' +
      '</label>' +
      '<button type="button" class="crm-lead-item' + active + unread + '" data-id="' + row.id + '" data-kind="chat" role="option">' +
        '<div class="crm-lead-top">' +
          '<strong class="crm-lead-name">' + esc(row.nombre) + '</strong>' +
          '<span class="crm-status-pill ' + esc(row.estado_class) + '">' + esc(row.estado_label) + '</span>' +
        '</div>' +
        '<div class="crm-lead-meta">' + formatDate(row.ultimo_mensaje_at) + metaExtra + badges + '</div>' +
        '<div class="crm-lead-note">' + esc(row.ultimo_mensaje_preview || row.interes || 'Sin mensajes') + '</div>' +
      '</button>' +
    '</div>';
  }

  function renderLeadItem(row) {
    var flags = row.flags || {};
    var badges = '';
    if (row.badge_count > 0) {
      badges += '<span class="crm-badge-count">' + row.badge_count + '</span>';
    }
    if (flags.sin_seguimiento) {
      badges += '<span class="crm-flag crm-flag-warn" title="Sin seguimiento reciente">!</span>';
    }
    if (flags.estatus_reciente) {
      badges += '<span class="crm-flag crm-flag-status" title="Cambio de estatus reciente">↻</span>';
    }

    var active = selectedId === row.id ? ' is-active' : '';
    var unread = flags.por_revisar ? ' is-unread' : '';

    return '<button type="button" class="crm-lead-item' + active + unread + '" data-id="' + row.id + '" data-kind="lead" role="option">' +
      '<div class="crm-lead-top">' +
        '<strong class="crm-lead-name">' + esc(row.nombre) + '</strong>' +
        '<span class="crm-status-pill ' + esc(row.status_class) + '">' + esc(row.pipeline_label) + '</span>' +
      '</div>' +
      '<div class="crm-lead-meta">' + formatDate(row.ultima_movimiento) + badges + '</div>' +
      '<div class="crm-lead-note">' + esc(row.ultima_nota || 'Sin notas registradas') + '</div>' +
    '</button>';
  }

  function patchChatListRows(rows) {
    if (!rows || !rows.length) return;
    var $list = $('#crmLeadList');
    if ($list.find('.crm-list-empty, .crm-list-loading').length) $list.empty();
    rows.forEach(function (row) {
      if (!row.id) return;
      if (row.eliminado) {
        $list.find('.crm-lead-row[data-kind="chat"][data-id="' + row.id + '"], .crm-lead-item[data-kind="chat"][data-id="' + row.id + '"]').closest('.crm-lead-row').remove();
        $list.find('.crm-lead-item[data-kind="chat"][data-id="' + row.id + '"]').remove();
        return;
      }
      var $existing = $list.find('.crm-lead-row[data-kind="chat"][data-id="' + row.id + '"]');
      var wasChecked = $existing.find('.js-chat-check').prop('checked');
      $existing.remove();
      $list.find('.crm-lead-item[data-kind="chat"][data-id="' + row.id + '"]').not('.crm-lead-row .crm-lead-item').remove();
      $list.prepend(renderChatItem(row));
      if (wasChecked) {
        $list.find('.crm-lead-row[data-id="' + row.id + '"] .js-chat-check').prop('checked', true);
      }
    });
    updateChatBulkUI();
  }

  function markChatItemRead(id) {
    var $item = $('.crm-lead-item[data-kind="chat"][data-id="' + id + '"]');
    $item.removeClass('is-unread');
    $item.find('.crm-badge-count').remove();
  }

  function updateChatBadge(counts) {
    if (!counts) return;
    var badge = (counts.por_revisar || 0) + (counts.sin_responder || 0);
    if (badge > 0) $('#crmChatTabBadge').text(badge > 9 ? '9+' : badge).removeAttr('hidden');
    else $('#crmChatTabBadge').attr('hidden', true);
    if (window.cwChatMenuLive && typeof window.cwChatMenuLive.setCounts === 'function') {
      window.cwChatMenuLive.setCounts(counts);
    }
  }

  function loadList(options) {
    options = options || {};
    var seq = ++listLoadSeq;
    var tabAtRequest = currentTab;
    var q = ($('#crmSearch').val() || '').trim();
    var endpoint = isLiveChatTab(tabAtRequest) ? API + '/chat_inbox_list.php' : API + '/inbox_list.php';
    var data = { q: q, filtro: currentFilter, _: Date.now() };
    if (tabAtRequest === 'sesiones') {
      data.scope = 'web_sessions';
    }

    if (listXhr && listXhr.readyState !== 4) {
      listXhr.abort();
    }

    listXhr = $.ajax({
      url: endpoint,
      method: 'GET',
      dataType: 'json',
      cache: false,
      data: data
    })
      .done(function (res) {
        if (seq !== listLoadSeq || tabAtRequest !== currentTab) return;
        if (!res || !res.success) {
          $('#crmLeadList').html('<p class="crm-list-empty">No se pudo cargar la lista.</p>');
          return;
        }
        renderStats(res.stats);
        var rows = res.data || [];
        var html = '';
        if (isLiveChatTab(currentTab)) {
          html = rows.map(renderChatItem).join('');
          $('#crmLeadList').html(html || '<p class="crm-list-empty">' + (currentTab === 'sesiones' ? 'No hay sesiones web.' : 'No hay conversaciones de chat.') + '</p>');
          $('#crmChatCheckAll').prop('checked', false).prop('indeterminate', false);
          updateChatBulkUI();
        } else {
          html = rows.map(renderLeadItem).join('');
          $('#crmLeadList').html(html || '<p class="crm-list-empty">No hay leads con este filtro.</p>');
        }
        if (selectedId > 0) {
          var kindSel = isLiveChatTab(currentTab) ? '[data-kind="chat"]' : '[data-kind="lead"]';
          $('.crm-lead-item' + kindSel + '[data-id="' + selectedId + '"]').addClass('is-active');
        }
      })
      .fail(function (_xhr, status) {
        if (seq !== listLoadSeq || tabAtRequest !== currentTab || status === 'abort') return;
        $('#crmLeadList').html('<p class="crm-list-empty">No se pudo cargar la lista. Intenta de nuevo.</p>');
      })
      .always(function () {
        if (listXhr && listXhr.readyState === 4) listXhr = null;
      });
  }

  function stopConversationStream() {
    if (chatEs) { chatEs.close(); chatEs = null; }
  }

  function stopChatStreams() {
    stopConversationStream();
    if (chatListEs) { chatListEs.close(); chatListEs = null; }
  }

  function startChatListStream() {
    if (!isLiveChatTab(currentTab)) return;
    if (chatListEs) chatListEs.close();
    chatListEs = new EventSource(window.ADM_BASE + '/leads/api/chat_stream.php?list=1');
    chatListEs.addEventListener('counts', function (e) {
      try {
        var c = JSON.parse(e.data);
        updateChatBadge(c);
      } catch (err) { /* ignore */ }
    });
    chatListEs.addEventListener('list_delta', function (e) {
      try {
        var data = JSON.parse(e.data);
        if (!isLiveChatTab(currentTab)) return;
        if (data.counts) {
          renderStats(data.counts);
          updateChatBadge(data.counts);
        }
        if (data.rows && data.rows.length && currentFilter === '' && !$('#crmSearch').val().trim()) {
          var rows = data.rows;
          if (currentTab === 'sesiones') {
            rows = rows.filter(function (r) { return r.canal === 'web'; });
          }
          if (rows.length) patchChatListRows(rows);
        }
        if (data.rows && window.cwChatMenuLive && typeof window.cwChatMenuLive.notifyRows === 'function') {
          window.cwChatMenuLive.notifyRows(data.rows);
        }
      } catch (err) { /* ignore */ }
    });
    chatListEs.addEventListener('reconnect', function () {
      chatListEs.close();
      setTimeout(startChatListStream, 500);
    });
    chatListEs.onerror = function () {
      chatListEs.close();
      setTimeout(startChatListStream, 3000);
    };
  }

  function appendChatMessageDom(msg) {
    var $tl = $('#chMessagesTimeline');
    if (!$tl.length || $tl.find('[data-id="' + msg.id + '"]').length) return;
    // Si ya hay un bubble optimista del mismo texto, no duplicar
    if ((msg.remitente_tipo || '') === 'agente' && msg.contenido) {
      var $opt = $tl.find('.ch-msg--pending, .ch-msg--failed').filter(function () {
        return $(this).find('.ch-msg-body').text() === String(msg.contenido || '');
      }).first();
      if ($opt.length) {
        finalizePendingMessage($opt, msg);
        return;
      }
    }
    var icons = { cliente: 'bi-person-fill', bot: 'bi-robot', agente: 'bi-headset', sistema: 'bi-info-circle' };
    var tipo = msg.remitente_tipo || 'sistema';
    var html = '<article class="ch-msg ch-msg--' + tipo + ' ch-msg--in" data-id="' + msg.id + '">' +
      '<span class="ch-msg-avatar"><i class="bi ' + (icons[tipo] || 'bi-chat') + '"></i></span>' +
      '<div class="ch-msg-body-wrap"><header class="ch-msg-head"><strong>' + esc(msg.remitente_nombre || tipo) + '</strong><time>' + esc(msg.enviado_at || '') + '</time></header>' +
      '<div class="ch-msg-body">' + esc(msg.contenido || '').replace(/\n/g, '<br>') + '</div></div></article>';
    $tl.append(html);
    var el = $tl[0];
    if (el) el.scrollTop = el.scrollHeight;
  }

  function appendPendingAgentMessage(text, tempId) {
    var $tl = $('#chMessagesTimeline');
    if (!$tl.length) return null;
    var html = '<article class="ch-msg ch-msg--agente ch-msg--in ch-msg--pending" data-temp-id="' + esc(tempId) + '">' +
      '<span class="ch-msg-avatar"><i class="bi bi-headset"></i></span>' +
      '<div class="ch-msg-body-wrap">' +
      '<header class="ch-msg-head"><strong>Tú</strong><time class="ch-msg-status"><span class="ch-send-dots" aria-hidden="true"></span> Enviando…</time></header>' +
      '<div class="ch-msg-body">' + esc(text).replace(/\n/g, '<br>') + '</div>' +
      '</div></article>';
    $tl.append(html);
    var el = $tl[0];
    if (el) el.scrollTop = el.scrollHeight;
    return $tl.find('[data-temp-id="' + tempId + '"]');
  }

  function finalizePendingMessage($pending, msg) {
    if (!$pending || !$pending.length) {
      if (msg) appendChatMessageDom(msg);
      return;
    }
    if (msg && msg.id) {
      $pending.attr('data-id', msg.id).removeAttr('data-temp-id')
        .removeClass('ch-msg--pending ch-msg--failed')
        .addClass('ch-msg--sent');
      $pending.find('.ch-msg-status').html('<i class="bi bi-check2-all"></i> ' + esc(msg.enviado_at || 'Enviado'));
      if (msg.remitente_nombre) $pending.find('.ch-msg-head strong').text(msg.remitente_nombre);
      if (msg.id > chatLastId) chatLastId = msg.id;
      setTimeout(function () { $pending.removeClass('ch-msg--sent'); }, 900);
    } else {
      $pending.removeClass('ch-msg--pending').addClass('ch-msg--sent');
      $pending.find('.ch-msg-status').html('<i class="bi bi-check2"></i> Enviado');
      setTimeout(function () { $pending.removeClass('ch-msg--sent'); }, 900);
    }
  }

  function failPendingMessage($pending, onRetry) {
    if (!$pending || !$pending.length) return;
    $pending.removeClass('ch-msg--pending').addClass('ch-msg--failed');
    $pending.find('.ch-msg-status').html(
      '<button type="button" class="ch-msg-retry">No se envió · Reintentar</button>'
    );
    $pending.find('.ch-msg-retry').off('click').on('click', function () {
      if (typeof onRetry === 'function') onRetry();
    });
  }

  function setReplySending($form, sending) {
    var $btn = $form.find('button[type=submit]');
    var $ta = $form.find('[name=mensaje]');
    $form.toggleClass('is-sending', !!sending);
    $btn.prop('disabled', !!sending);
    $ta.prop('disabled', !!sending);
    if (sending) {
      $btn.data('html-prev', $btn.html());
      $btn.html('<span class="ch-btn-spinner" aria-hidden="true"></span> Enviando');
    } else {
      $btn.html($btn.data('html-prev') || '<i class="bi bi-send-fill"></i> Enviar');
    }
  }

  function startChatConversationStream(id) {
    stopConversationStream();
    if (!chatListEs && isLiveChatTab(currentTab)) startChatListStream();
    chatEs = new EventSource(window.ADM_BASE + '/leads/api/chat_stream.php?id=' + id + '&after_id=' + chatLastId + '&version=' + chatVersion);
    chatEs.addEventListener('update', function (e) {
      try {
        var data = JSON.parse(e.data);
        chatVersion = data.version || chatVersion;
        if (data.typing && data.typing.cliente) {
          $('#chTypingIndicator').addClass('is-visible').text('El cliente está escribiendo…');
        } else {
          $('#chTypingIndicator').removeClass('is-visible');
        }
        if (data.messages && data.messages.length) {
          data.messages.forEach(function (m) {
            appendChatMessageDom(m);
            if (m.id > chatLastId) chatLastId = m.id;
          });
          markChatItemRead(selectedId);
          if (data.rows) patchChatListRows(data.rows);
        }
        if (data.conversation_meta) {
          var $pill = $('#crmConversationPanel .crm-status-pill').first();
          if ($pill.length && data.conversation_meta.estado_label) {
            $pill.text(data.conversation_meta.estado_label);
          }
          if (data.conversation_meta.estado) {
            updateBotControlUI({
              estado: data.conversation_meta.estado,
              estado_label: data.conversation_meta.estado_label,
              estado_class: data.conversation_meta.estado === 'bot' ? 'ch-st-bot' : (data.conversation_meta.estado === 'humano' ? 'ch-st-humano' : 'ch-st-pendiente'),
              bot_activo: ['humano', 'cerrada'].indexOf(data.conversation_meta.estado) < 0
            });
          }
        }
        if (data.counts) updateChatBadge(data.counts);
      } catch (err) { /* ignore */ }
    });
    chatEs.addEventListener('reconnect', function () {
      chatEs.close();
      setTimeout(function () { startChatConversationStream(id); }, 400);
    });
    chatEs.onerror = function () {
      chatEs.close();
      setTimeout(function () { startChatConversationStream(id); }, 3000);
    };
  }

  function sendChatTyping(id, active) {
    $.post(API + '/chat_typing.php', { id: id, typing: active ? 1 : 0 });
  }

  function updateBotControlUI(estado) {
    var $panel = $('#crmConversationPanel');
    var botActivo = estado.bot_activo != null
      ? !!estado.bot_activo
      : ['humano', 'cerrada'].indexOf(estado.estado) < 0;
    var $ctrl = $panel.find('#chBotControl');
    var $pill = $panel.find('.crm-status-pill').first();
    if ($pill.length && estado.estado_label) {
      $pill.text(estado.estado_label).attr('class', 'crm-status-pill ' + (estado.estado_class || ''));
    }
    if (!$ctrl.length) return;
    $ctrl.attr('data-bot-active', botActivo ? '1' : '0');
    $('#chBotStatusText').text(botActivo
      ? 'Activo — responde automáticamente al cliente hasta que tomes el control.'
      : 'Pausado — solo un asesor humano puede responder al cliente.');
    var $actions = $ctrl.find('.ch-bot-control__actions');
    if (botActivo) {
      $actions.html('<button type="button" class="btn btn-sm btn-outline-primary js-chat-bot-disable" data-id="' + $ctrl.data('id') + '"><i class="bi bi-person-fill-check"></i> Tomar control</button>');
    } else {
      $actions.html('<button type="button" class="btn btn-sm btn-success js-chat-bot-enable" data-id="' + $ctrl.data('id') + '"><i class="bi bi-robot"></i> Activar chatbot</button>');
    }
    bindBotToggleHandlers();
  }

  function bindBotToggleHandlers() {
    var $panel = $('#crmConversationPanel');
    $panel.find('.js-chat-bot-enable').off('click').on('click', function () {
      var id = $(this).data('id');
      var $btn = $(this);
      $btn.prop('disabled', true);
      $.post(API + '/chat_bot_toggle.php', { id: id, action: 'enable' }, 'json')
        .done(function (res) {
          if (res.success) {
            if (res.estado) updateBotControlUI(res.estado);
            if (res.system_message) appendChatMessageDom(res.system_message);
            loadList();
          } else alert(res.message || 'No se pudo activar');
        })
        .fail(function () { alert('Error al activar chatbot'); })
        .always(function () { $btn.prop('disabled', false); });
    });

    $panel.find('.js-chat-bot-disable').off('click').on('click', function () {
      var id = $(this).data('id');
      var $btn = $(this);
      $btn.prop('disabled', true);
      $.post(API + '/chat_bot_toggle.php', { id: id, action: 'disable' }, 'json')
        .done(function (res) {
          if (res.success) {
            if (res.estado) updateBotControlUI(res.estado);
            if (res.system_message) appendChatMessageDom(res.system_message);
            loadList();
          } else alert(res.message || 'No se pudo desactivar');
        })
        .fail(function () { alert('Error al tomar control'); })
        .always(function () { $btn.prop('disabled', false); });
    });
  }

  function bindChatEvents() {
    var $panel = $('#crmConversationPanel');

    $panel.find('.js-chat-assign').off('click').on('click', function () {
      var id = $(this).data('id');
      $.post(API + '/chat_assign.php', { id: id }).done(function (res) {
        if (res.success) { loadChatConversation(id); loadList(); }
        else alert(res.message || 'Error');
      });
    });

    $panel.find('.js-chat-close').off('click').on('click', function () {
      var id = $(this).data('id');
      if (!confirm('¿Cerrar esta conversación?')) return;
      $.post(API + '/chat_close.php', { id: id }).done(function (res) {
        if (res.success) { loadChatConversation(id); loadList(); }
        else alert(res.message || 'Error');
      });
    });

    $panel.find('.js-chat-delete').off('click').on('click', function () {
      var id = parseInt($(this).data('id'), 10);
      confirmDeleteChats([id], afterChatsDeleted);
    });

    bindBotToggleHandlers();

    $panel.find('#chReplyForm').off('submit').on('submit', function (e) {
      e.preventDefault();
      var $form = $(this);
      if ($form.hasClass('is-sending')) return;

      var id = $form.find('[name=id]').val();
      var $ta = $form.find('[name=mensaje]');
      var texto = ($ta.val() || '').trim();
      if (!texto) return;

      var tempId = 'tmp-' + Date.now();
      var $pending = appendPendingAgentMessage(texto, tempId);

      $ta.val('');
      setReplySending($form, true);
      sendChatTyping(id, false);

      function doSend() {
        setReplySending($form, true);
        if ($pending && $pending.length) {
          $pending.removeClass('ch-msg--failed').addClass('ch-msg--pending');
          $pending.find('.ch-msg-status').html('<span class="ch-send-dots" aria-hidden="true"></span> Enviando…');
        }
        $.post(API + '/chat_reply.php', { id: id, mensaje: texto }, 'json')
          .done(function (res) {
            if (res.success) {
              finalizePendingMessage($pending, res.message);
              if (res.estado) updateBotControlUI(res.estado);
              markChatItemRead(parseInt(id, 10));
              setReplySending($form, false);
              $ta.focus();
            } else {
              failPendingMessage($pending, doSend);
              setReplySending($form, false);
              alert(res.message || res.text || 'No se pudo enviar');
            }
          })
          .fail(function () {
            failPendingMessage($pending, doSend);
            setReplySending($form, false);
            alert('Error al enviar');
          });
      }

      doSend();
    });

    $panel.find('[name=mensaje]').off('input').on('input', function () {
      var id = $panel.find('[name=id]').val();
      clearTimeout(chatTypingTimer);
      sendChatTyping(id, true);
      chatTypingTimer = setTimeout(function () { sendChatTyping(id, false); }, 1200);
    });

    $panel.find('#chLoadMoreBtn').off('click').on('click', function () {
      var convId = $(this).data('id');
      var $tl = $panel.find('#chMessagesTimeline');
      var firstId = parseInt($tl.find('.ch-msg').first().data('id'), 10);
      if (!firstId) return;
      var $btn = $(this);
      $btn.prop('disabled', true);
      $.getJSON(API + '/chat_messages.php', { id: convId, before_id: firstId, limit: 30 })
        .done(function (res) {
          if (!res.success || !res.messages || !res.messages.length) {
            $btn.closest('.ch-load-more-wrap').remove();
            return;
          }
          var html = '';
          res.messages.forEach(function (msg) {
            if ($tl.find('[data-id="' + msg.id + '"]').length) return;
            var icons = { cliente: 'bi-person-fill', bot: 'bi-robot', agente: 'bi-headset', sistema: 'bi-info-circle' };
            var tipo = msg.remitente_tipo || 'sistema';
            html += '<article class="ch-msg ch-msg--' + tipo + '" data-id="' + msg.id + '">' +
              '<span class="ch-msg-avatar"><i class="bi ' + (icons[tipo] || 'bi-chat') + '"></i></span>' +
              '<div class="ch-msg-body-wrap"><header class="ch-msg-head"><strong>' + esc(msg.remitente_nombre || tipo) + '</strong><time>' + esc(msg.enviado_at || '') + '</time></header>' +
              '<div class="ch-msg-body">' + esc(msg.contenido || '').replace(/\n/g, '<br>') + '</div></div></article>';
          });
          $tl.prepend(html);
          if (!res.has_more) $btn.closest('.ch-load-more-wrap').remove();
        })
        .always(function () { $btn.prop('disabled', false); });
    });

    var timeline = $panel.find('#chMessagesTimeline')[0];
    if (timeline) timeline.scrollTop = timeline.scrollHeight;
  }

  function loadChatConversation(id, pushHistory) {
    if (!isLiveChatTab(currentTab)) {
      setActiveTab('sesiones');
    }
    var tabForUrl = currentTab === 'chat' ? 'chat' : 'sesiones';
    selectedId = parseInt(id, 10);
    var url = window.ADM_BASE + '/leads/inbox.php?tab=' + tabForUrl + '&id=' + selectedId;
    if (pushHistory !== false) {
      history.pushState({ tab: tabForUrl, id: selectedId }, '', url);
    } else {
      history.replaceState({ tab: tabForUrl, id: selectedId }, '', url);
    }
    $('#crmEmpty').attr('hidden', true);
    $('#crmConversationPanel').removeAttr('hidden').html(
      '<div class="crm-loading"><span class="spinner-border spinner-border-sm"></span> Cargando chat…</div>'
    );
    $.getJSON(API + '/chat_conversation.php', { id: selectedId })
      .done(function (res) {
        if (!res.success) {
          $('#crmConversationPanel').html('<p class="text-danger p-4">' + esc(res.message || 'Error') + '</p>');
          return;
        }
        $('#crmConversationPanel').html(res.html);
        bindChatEvents();
        chatLastId = 0;
        $('#chMessagesTimeline .ch-msg').each(function () {
          var mid = parseInt($(this).data('id'), 10);
          if (mid > chatLastId) chatLastId = mid;
        });
        startChatConversationStream(selectedId);
        $('.crm-lead-item').removeClass('is-active');
        $('.crm-lead-item[data-kind="chat"][data-id="' + selectedId + '"]').addClass('is-active').removeClass('is-unread');
        markChatItemRead(selectedId);
        if (window.cwChatMenuLive && typeof window.cwChatMenuLive.dismissConv === 'function') {
          window.cwChatMenuLive.dismissConv(selectedId);
        }
      })
      .fail(function () {
        $('#crmConversationPanel').html('<p class="text-danger p-4">No se pudo cargar la conversación</p>');
      });
  }

  function switchTab(tab) {
    if (tab !== 'chat' && tab !== 'sesiones') tab = 'leads';

    if (tab === currentTab) {
      if (selectedId > 0) {
        selectedId = 0;
        stopConversationStream();
        $('#crmConversationPanel').attr('hidden', true).empty();
        $('#crmEmpty').removeAttr('hidden');
        history.replaceState({ tab: tab, id: 0 }, '', window.ADM_BASE + '/leads/inbox.php?tab=' + tab);
        if (isLiveChatTab(tab) && !chatListEs) startChatListStream();
        showListLoading();
        loadList();
        return;
      }
      showListLoading();
      loadList();
      return;
    }

    stopChatStreams();
    setActiveTab(tab);
    resetFiltersForTab(tab);
    selectedId = 0;
    $('#crmSearch').val('');
    $('#crmConversationPanel').attr('hidden', true).empty();
    $('#crmEmpty').removeAttr('hidden');
    history.replaceState({ tab: tab, id: 0 }, '', window.ADM_BASE + '/leads/inbox.php?tab=' + tab);
    showListLoading();

    if (isLiveChatTab(tab)) {
      startChatListStream();
      if (window.cwChatMenuLive && typeof window.cwChatMenuLive.pauseStream === 'function') {
        window.cwChatMenuLive.pauseStream();
      }
    } else if (window.cwChatMenuLive && typeof window.cwChatMenuLive.resumeStream === 'function') {
      window.cwChatMenuLive.resumeStream();
    }

    loadList();
  }

  function bindConversationEvents() {
    var $panel = $('#crmConversationPanel');

    $panel.find('.js-pipeline').off('change').on('change', function () {
      var id = $(this).data('id');
      var estado = $(this).val();
      var $sel = $(this);
      $.post(window.ADM_BASE + '/leads/api_pipeline.php', { id: id, pipeline_estado: estado })
        .done(function (res) {
          if (res.success) {
            loadConversation(id);
            loadList();
          } else {
            alert(res.message || 'No se pudo actualizar estatus');
            $sel.val($sel.data('prev') || 'nuevo');
          }
        })
        .fail(function () { alert('Error al actualizar estatus'); });
    }).each(function () {
      $(this).data('prev', $(this).val());
    });

    $panel.find('.js-delete-lead').off('click').on('click', function () {
      var id = parseInt($(this).data('id'), 10);
      var nombre = $(this).data('nombre') || '';
      confirmDeleteLead(id, nombre, function () {
        selectedId = 0;
        history.replaceState(null, '', window.ADM_BASE + '/leads/inbox.php');
        $('#crmConversationPanel').attr('hidden', true).empty();
        $('#crmEmpty').removeAttr('hidden');
        loadList();
      });
    });

    $panel.find('#crmFileInput').off('change').on('change', function () {
      var name = this.files && this.files[0] ? this.files[0].name : '';
      $('#crmFileLabel').text(name ? 'Archivo: ' + name : '');
    });

    $panel.find('#crmNoteForm').off('submit').on('submit', function (e) {
      e.preventDefault();
      var $form = $(this);
      var id = $form.find('[name=id]').val();
      var $btn = $form.find('button[type=submit]');
      var fileInput = $form.find('#crmFileInput')[0];

      $btn.prop('disabled', true);

      var noteReq = $.ajax({
        url: API + '/inbox_note.php',
        method: 'POST',
        data: $form.serialize(),
        dataType: 'json'
      });

      noteReq.done(function (res) {
        if (!res || !res.success) {
          alert((res && res.message) || 'No se pudo guardar la nota');
          return;
        }
        if (fileInput && fileInput.files && fileInput.files[0]) {
          var fd = new FormData();
          fd.append('id', id);
          fd.append('archivo', fileInput.files[0]);
          $.ajax({
            url: API + '/inbox_upload.php',
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false
          }).always(function () {
            loadConversation(id);
            loadList();
          });
        } else {
          loadConversation(id);
          loadList();
        }
        $form.find('[name=nota]').val('');
        $('#crmFileLabel').text('');
        if (fileInput) fileInput.value = '';
      }).fail(function (xhr) {
        var msg = 'Error al guardar nota';
        try {
          var j = JSON.parse(xhr.responseText);
          if (j.message) msg = j.message;
        } catch (err) { /* ignore */ }
        alert(msg);
      }).always(function () {
        $btn.prop('disabled', false);
      });
    });

    var timeline = $panel.find('#crmTimeline')[0];
    if (timeline) timeline.scrollTop = timeline.scrollHeight;
  }

  function loadConversation(id, pushHistory) {
    if (isLiveChatTab(currentTab)) {
      loadChatConversation(id, pushHistory);
      return;
    }
    setActiveTab('leads');
    selectedId = parseInt(id, 10);
    var url = window.ADM_BASE + '/leads/inbox.php?tab=leads&id=' + selectedId;
    if (pushHistory !== false) {
      history.pushState({ tab: 'leads', id: selectedId }, '', url);
    } else {
      history.replaceState({ tab: 'leads', id: selectedId }, '', url);
    }

    $('#crmEmpty').attr('hidden', true);
    $('#crmConversationPanel').removeAttr('hidden').html(
      '<div class="crm-loading"><span class="spinner-border spinner-border-sm"></span> Cargando conversación…</div>'
    );

    $.get(window.ADM_BASE + '/leads/detalle.php', { id: selectedId, partial: 1 })
      .done(function (html) {
        $('#crmConversationPanel').html(html);
        bindConversationEvents();
        $('.crm-lead-item').removeClass('is-active');
        $('.crm-lead-item[data-kind="lead"][data-id="' + selectedId + '"]').addClass('is-active').removeClass('is-unread');
      })
      .fail(function () {
        $('#crmConversationPanel').html('<p class="text-danger p-4">No se pudo cargar detalle.php?id=' + selectedId + '</p>');
      });
  }

  $('#crmLeadList').on('click', '.crm-lead-item', function () {
    var id = $(this).data('id');
    var kind = $(this).data('kind');
    if (kind === 'chat' || isLiveChatTab(currentTab)) loadChatConversation(id, true);
    else loadConversation(id, true);
  });

  $('#crmLeadList').on('click', '.crm-lead-check', function (e) {
    e.stopPropagation();
  });

  $('#crmLeadList').on('change', '.js-chat-check', function () {
    updateChatBulkUI();
  });

  $('#crmChatCheckAll').on('change', function () {
    var checked = $(this).prop('checked');
    $('#crmLeadList .js-chat-check').prop('checked', checked);
    updateChatBulkUI();
  });

  $('#btnDeleteChats').on('click', function () {
    var ids = getCheckedChatIds();
    if (!ids.length) return;
    confirmDeleteChats(ids, afterChatsDeleted);
  });

  $('#crmSearch').on('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadList, 250);
  });

  $('.crm-filter-chips').on('click', '.crm-chip', function () {
    var $parent = $(this).closest('.crm-filter-chips');
    $parent.find('.crm-chip').removeClass('active');
    $(this).addClass('active');
    currentFilter = $(this).data('filter') || '';
    loadList();
  });

  $('.crm-inbox-tabs').on('click', '.crm-inbox-tab', function (e) {
    e.preventDefault();
    switchTab($(this).attr('data-tab'));
  });

  if (isLiveChatTab(currentTab)) {
    applyTabUI(currentTab);
    startChatListStream();
    if (window.cwChatMenuLive && typeof window.cwChatMenuLive.pauseStream === 'function') {
      window.cwChatMenuLive.pauseStream();
    }
  } else {
    applyTabUI('leads');
  }

  history.replaceState({ tab: currentTab, id: selectedId }, '', location.href);

  loadList();
  if (selectedId > 0) {
    if (isLiveChatTab(currentTab)) loadChatConversation(selectedId, false);
    else loadConversation(selectedId, false);
  }

  window.addEventListener('popstate', function () {
    var state = syncFromUrl();
    var newTab = state.tab;
    selectedId = state.id;
    stopConversationStream();

    if (newTab !== currentTab) {
      stopChatStreams();
      resetFiltersForTab(newTab);
      $('#crmSearch').val('');
      showListLoading();
      if (isLiveChatTab(newTab)) {
        startChatListStream();
        if (window.cwChatMenuLive && typeof window.cwChatMenuLive.pauseStream === 'function') {
          window.cwChatMenuLive.pauseStream();
        }
      } else if (window.cwChatMenuLive && typeof window.cwChatMenuLive.resumeStream === 'function') {
        window.cwChatMenuLive.resumeStream();
      }
    }

    if (selectedId > 0) {
      if (isLiveChatTab(currentTab)) loadChatConversation(selectedId, false);
      else loadConversation(selectedId, false);
    } else {
      $('#crmConversationPanel').attr('hidden', true).empty();
      $('#crmEmpty').removeAttr('hidden');
      if (isLiveChatTab(currentTab)) {
        if (!chatListEs) startChatListStream();
        if (window.cwChatMenuLive && typeof window.cwChatMenuLive.pauseStream === 'function') {
          window.cwChatMenuLive.pauseStream();
        }
      }
      loadList();
    }
  });

  setInterval(function () {
    if (!isLiveChatTab(currentTab)) loadList();
  }, 60000);
})(jQuery);
</script>
</div><!-- menu container-fluid -->
</div><!-- menu content-wrapper -->
</body></html>
