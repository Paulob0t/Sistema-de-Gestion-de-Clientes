/**
 * Mailing UI — plantillas, audiencias, envío y tracking.
 * Usa fetch (el menú carga jQuery slim sin $.ajax).
 */
(function ($) {
  'use strict';

  var API = '';
  var UPLOAD = '';
  var state = {
    tab: 'plantillas',
    templates: [],
    clientes: [],
    leads: [],
    selected: { cliente: {}, lead: {} },
    stats: null,
    sends: [],
    queue: [],
    dtClientes: null,
    dtTrack: null
  };
  var aiDraft = null;
  var aiImageUrls = [];
  var tplDesignMeta = '';

  function splitDesignMeta(html) {
    var raw = String(html || '');
    /* Greedy: el JSON puede traer blocks anidados. */
    var m = raw.match(/^<!--cwml:\{[\s\S]*\}-->/);
    if (!m) return { meta: '', body: raw };
    return { meta: m[0], body: raw.slice(m[0].length) };
  }

  function joinDesignMeta(body) {
    var html = String(body || '');
    if (/^<!--cwml:\{/.test(html)) return html;
    return (tplDesignMeta || '') + html;
  }

  /** Extrae "Etiqueta | url" desde el bloque <!--cw-links--> del HTML. */
  function extractLinksText(html) {
    var raw = String(html || '');
    var block = raw.match(/<!--cw-links-->([\s\S]*?)<!--\/cw-links-->/);
    if (!block) return '';
    var chunk = block[1];
    var lines = [];
    var re = /href="(https?:\/\/[^"]+)"[\s\S]*?<strong[^>]*>([\s\S]*?)<\/strong>/gi;
    var m;
    while ((m = re.exec(chunk))) {
      var url = m[1];
      var title = String(m[2] || '').replace(/<[^>]+>/g, '').trim();
      if (url && title) lines.push(title + ' | ' + url);
    }
    if (!lines.length) {
      re = /href="(https?:\/\/[^"]+)"/gi;
      while ((m = re.exec(chunk))) {
        if (lines.indexOf(m[1]) === -1) lines.push(m[1]);
      }
    }
    return lines.join('\n');
  }

  function setAiDraftUi(on) {
    $('#btnAiPreview, #btnAiEditDraft, #btnAiSave, #btnAiDiscard').toggleClass('d-none', !on);
    $('#mlAiDraftBox').toggleClass('d-none', !on);
    if (on && aiDraft) {
      $('#mlAiDraftTitle').text(aiDraft.title || 'Borrador IA');
      $('#mlAiDraftSubject').text(aiDraft.subject || '');
    }
    syncGenerateBtn();
  }

  function fillTemplateFields(tpl) {
    tpl = tpl || {};
    $('#mlTplId').val(tpl.id || '');
    $('#mlTplTitle').val(tpl.title || '');
    $('#mlTplTheme').val(tpl.theme || 'general');
    $('#mlTplSubject').val(tpl.subject || '');
    $('#mlTplPreheader').val(tpl.preheader || '');
    var split = splitDesignMeta(tpl.body_html || '');
    tplDesignMeta = split.meta;
    $('#mlTplBody').val(split.body);
    $('#mlTplImage').val(tpl.image_url || '');
    $('#mlTplCtaLabel').val(tpl.cta_label || '');
    $('#mlTplCtaUrl').val(tpl.cta_url || '');
    if ($('#mlTplLinks').length) {
      $('#mlTplLinks').val(extractLinksText(tpl.body_html || '') || tpl.links_text || '');
    }
    $('#mlTplActive').prop('checked', tpl.id ? parseInt(tpl.active, 10) === 1 : true);
  }

  function uploadAiImages(files) {
    var list = Array.prototype.slice.call(files || [], 0, 3);
    if (!list.length) return Promise.resolve([]);
    var chain = Promise.resolve([]);
    list.forEach(function (file) {
      chain = chain.then(function (acc) {
        var fd = new FormData();
        fd.append('image', file);
        return fetch(UPLOAD, { method: 'POST', credentials: 'same-origin', body: fd })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            if (res && res.ok && res.url) acc.push(res.url);
            return acc;
          });
      });
    });
    return chain;
  }

  function renderAiThumbs(urls) {
    var $box = $('#mlAiThumbs');
    if (!urls.length) {
      $box.empty();
      return;
    }
    $box.html(urls.map(function (u) {
      return '<img src="' + esc(u) + '" alt="">';
    }).join(''));
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function api(action, data) {
    var payload = Object.assign({ action: action }, data || {});
    return fetch(API, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json; charset=utf-8',
        'Accept': 'application/json'
      },
      body: JSON.stringify(payload)
    }).then(function (r) {
      return r.text().then(function (txt) {
        try {
          return JSON.parse(txt);
        } catch (e) {
          throw new Error('Respuesta inválida del servidor (' + r.status + ')');
        }
      });
    });
  }

  function showModal(id) {
    var el = document.getElementById(id);
    if (!el) return;
    if ($ && $.fn && typeof $.fn.modal === 'function') {
      $(el).modal('show');
      return;
    }
    el.style.display = 'block';
    el.classList.add('show');
    el.removeAttribute('aria-hidden');
    el.setAttribute('aria-modal', 'true');
    document.body.classList.add('modal-open');
    if (!document.getElementById(id + '-backdrop')) {
      var bd = document.createElement('div');
      bd.className = 'modal-backdrop fade show';
      bd.id = id + '-backdrop';
      document.body.appendChild(bd);
    }
  }

  function hideModal(id) {
    var el = document.getElementById(id);
    if (!el) return;
    if ($ && $.fn && typeof $.fn.modal === 'function') {
      $(el).modal('hide');
      return;
    }
    el.style.display = 'none';
    el.classList.remove('show');
    el.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
    var bd = document.getElementById(id + '-backdrop');
    if (bd) bd.remove();
  }

  function selectedCount(audience) {
    return Object.keys(state.selected[audience] || {}).filter(function (k) {
      return state.selected[audience][k];
    }).length;
  }

  function selectedIds(audience) {
    return Object.keys(state.selected[audience] || {}).filter(function (k) {
      return state.selected[audience][k];
    }).map(function (k) { return parseInt(k, 10); });
  }

  function updateSendBar(audience) {
    var n = selectedCount(audience);
    var $bar = $('#mlSendBar');
    if (n > 0 && (state.tab === 'clientes' || state.tab === 'leads')) {
      $bar.addClass('is-on');
      $('#mlSendCount').text(n);
      $bar.attr('data-audience', audience);
    } else if (state.tab !== 'clientes' && state.tab !== 'leads') {
      $bar.removeClass('is-on');
    } else if (n === 0) {
      $bar.removeClass('is-on');
    }
  }

  function renderKpis(stats) {
    if (!stats) return;
    $('#kpiTemplates').text(stats.templates || 0);
    $('#kpiSent').text(stats.sent || 0);
    $('#kpiOpened').text(stats.opened || 0);
    $('#kpiClicked').text(stats.clicked || 0);
    $('#kpiOpenRate').text((stats.open_rate || 0) + '%');
    $('#kpiClickRate').text((stats.click_rate || 0) + '%');
  }

  function renderTemplates() {
    var $grid = $('#mlTplGrid');
    if (!state.templates.length) {
      $grid.html('<div class="ml-empty"><i class="bi bi-envelope-paper"></i>Aún no hay plantillas. Crea la primera.</div>');
      return;
    }
    var html = state.templates.map(function (t) {
      var active = parseInt(t.active, 10) === 1;
      return (
        '<article class="ml-tpl" data-id="' + t.id + '">' +
          '<div class="ml-tpl__theme">' + esc(t.theme || 'general') + (active ? '' : ' · inactiva') + '</div>' +
          '<h3 class="ml-tpl__title">' + esc(t.title) + '</h3>' +
          '<p class="ml-tpl__subject">' + esc(t.subject) + '</p>' +
          '<div class="ml-tpl__actions">' +
            '<button type="button" class="btn btn-sm btn-outline-primary ml-tpl-preview" data-id="' + t.id + '"><i class="bi bi-eye"></i> Preview</button>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary ml-tpl-improve" data-id="' + t.id + '"><i class="bi bi-stars"></i> Mejorar</button>' +
            '<button type="button" class="btn btn-sm btn-light ml-tpl-edit" data-id="' + t.id + '"><i class="bi bi-pencil"></i> Editar</button>' +
            '<button type="button" class="btn btn-sm btn-outline-danger ml-tpl-del" data-id="' + t.id + '"><i class="bi bi-trash"></i></button>' +
          '</div>' +
        '</article>'
      );
    }).join('');
    $grid.html(html);
    fillTemplateSelect();
  }

  function fillTemplateSelect() {
    var active = state.templates.filter(function (t) { return parseInt(t.active, 10) === 1; });
    state._tplOptionsHtml = active.map(function (t) {
      return '<option value="' + t.id + '">' + esc(t.title) + ' (' + esc(t.theme) + ')</option>';
    }).join('') || '<option value="">Sin plantillas activas</option>';
  }

  function tplOptionsHtml() {
    return state._tplOptionsHtml || '<option value="">Sin plantillas</option>';
  }

  function assignRowHtml(idx) {
    var name = 'mlMode_' + idx;
    return (
      '<div class="ml-assign-row" data-idx="' + idx + '">' +
        '<div class="ml-assign-row__head">' +
          '<strong>Plantilla #' + (idx + 1) + '</strong>' +
          (idx > 0 ? '<button type="button" class="btn btn-sm btn-outline-danger ml-assign-remove"><i class="bi bi-trash"></i></button>' : '') +
        '</div>' +
        '<div class="form-group mb-2">' +
          '<label>Plantilla</label>' +
          '<select class="form-control ml-assign-tpl">' + tplOptionsHtml() + '</select>' +
        '</div>' +
        '<div class="ml-assign-modes">' +
          '<label class="ml-assign-mode"><input type="radio" name="' + name + '" value="now" checked> Ahora</label>' +
          '<label class="ml-assign-mode"><input type="radio" name="' + name + '" value="once"> Fecha y hora</label>' +
          '<label class="ml-assign-mode"><input type="radio" name="' + name + '" value="weekly"> Días + hora</label>' +
        '</div>' +
        '<div class="ml-assign-once d-none">' +
          '<label>Fecha y hora (México)</label>' +
          '<input type="datetime-local" class="form-control ml-assign-datetime">' +
        '</div>' +
        '<div class="ml-assign-weekly d-none">' +
          '<div class="form-group mb-2">' +
            '<label>Días</label>' +
            '<div class="ml-weekdays">' +
              '<label><input type="checkbox" value="1"> Lun</label>' +
              '<label><input type="checkbox" value="2"> Mar</label>' +
              '<label><input type="checkbox" value="3"> Mié</label>' +
              '<label><input type="checkbox" value="4"> Jue</label>' +
              '<label><input type="checkbox" value="5"> Vie</label>' +
              '<label><input type="checkbox" value="6"> Sáb</label>' +
              '<label><input type="checkbox" value="7"> Dom</label>' +
            '</div>' +
          '</div>' +
          '<div class="form-group mb-0">' +
            '<label>Hora</label>' +
            '<input type="time" class="form-control ml-assign-time" value="10:00">' +
          '</div>' +
        '</div>' +
      '</div>'
    );
  }

  function resetAssignRows() {
    var $box = $('#mlAssignRows');
    $box.html(assignRowHtml(0));
    syncRowModeUi($box.find('.ml-assign-row').first());
  }

  function syncRowModeUi($row) {
    var mode = $row.find('input[type=radio]:checked').val() || 'now';
    $row.find('.ml-assign-once').toggleClass('d-none', mode !== 'once');
    $row.find('.ml-assign-weekly').toggleClass('d-none', mode !== 'weekly');
  }

  function collectAssignItems() {
    var items = [];
    var error = null;
    $('#mlAssignRows .ml-assign-row').each(function (i) {
      if (error) return;
      var $row = $(this);
      var tid = parseInt($row.find('.ml-assign-tpl').val(), 10) || 0;
      var mode = $row.find('input[type=radio]:checked').val() || 'now';
      if (!tid) {
        error = 'Selecciona plantilla en la fila #' + (i + 1);
        return;
      }
      var item = { template_id: tid, mode: mode, weekdays: [], scheduled_at: '', send_time: '' };
      if (mode === 'once') {
        item.scheduled_at = $row.find('.ml-assign-datetime').val() || '';
        if (!item.scheduled_at) {
          error = 'Fecha/hora faltante en fila #' + (i + 1);
          return;
        }
      }
      if (mode === 'weekly') {
        $row.find('.ml-weekdays input:checked').each(function () {
          item.weekdays.push(parseInt($(this).val(), 10));
        });
        item.send_time = $row.find('.ml-assign-time').val() || '';
        if (!item.weekdays.length) {
          error = 'Elige días en fila #' + (i + 1);
          return;
        }
        if (!item.send_time) {
          error = 'Indica hora en fila #' + (i + 1);
          return;
        }
      }
      items.push(item);
    });
    return { items: items, error: error };
  }

  function queueStatusLabel(st) {
    if (st === 'pending') return 'Pendiente';
    if (st === 'sent') return 'Enviado';
    if (st === 'failed') return 'Fallido';
    if (st === 'cancelled') return 'Cancelado';
    return st || '—';
  }

  function renderQueue(rows) {
    var html;
    var n = rows && rows.length ? rows.length : 0;
    $('.ml-queue-count').text(n === 1 ? '1 ítem' : n + ' ítems');
    if (!n) {
      html = '<tr><td colspan="5" class="ml-empty">Sin envíos en el cronograma</td></tr>';
    } else {
      html = rows.map(function (q) {
        var st = q.status;
        var badge = st === 'pending' ? 'ml-badge--warn' : (st === 'sent' ? 'ml-badge--ok' : (st === 'failed' ? 'ml-badge--fail' : 'ml-badge--muted'));
        return '<tr>' +
          '<td><span class="ml-id">#' + q.id + '</span></td>' +
          '<td>' + esc(q.template_title || ('#' + q.template_id)) + '</td>' +
          '<td>' + esc(q.name || '') + '<div class="small text-muted">' + esc(q.email) + '</div></td>' +
          '<td class="small text-nowrap">' + esc(q.send_at || '') + '</td>' +
          '<td><span class="ml-badge ' + badge + '">' + esc(queueStatusLabel(st)) + '</span></td>' +
          '</tr>';
      }).join('');
    }
    $('.ml-queue-body').html(html);
  }

  function destroyClientesDt() {
    if (state.dtClientes) {
      try { state.dtClientes.destroy(); } catch (e) { /* ignore */ }
      state.dtClientes = null;
    }
  }

  function initClientesDt() {
    var $table = $('#mlClientesTable');
    if (!$table.length || !$.fn.DataTable) return;
    destroyClientesDt();
    state.dtClientes = $table.DataTable({
      searching: true,
      pageLength: 25,
      order: [[1, 'desc']],
      dom: 'lrtip',
      columnDefs: [
        { orderable: false, searchable: false, targets: [0, 6] }
      ],
      language: {
        search: 'Buscar:',
        searchPlaceholder: 'Ingrese término...',
        lengthMenu: 'Mostrar _MENU_ registros',
        info: 'Mostrando _START_ a _END_ de _TOTAL_ clientes',
        infoEmpty: 'Sin clientes',
        infoFiltered: '(filtrado de _MAX_)',
        zeroRecords: 'Sin coincidencias',
        paginate: {
          first: 'Primero',
          last: 'Último',
          next: 'Siguiente',
          previous: 'Anterior'
        }
      }
    });
    var q = String($('#mlSearchClientes').val() || '');
    if (q) state.dtClientes.search(q).draw();
  }

  function renderAudience(audience) {
    var rows = audience === 'cliente' ? state.clientes : state.leads;
    var $tb = audience === 'cliente' ? $('#mlClientesBody') : $('#mlLeadsBody');
    if (audience === 'cliente') destroyClientesDt();
    if (!rows.length) {
      $tb.html('<tr><td colspan="' + (audience === 'cliente' ? '7' : '6') + '" class="ml-empty">Sin clientes para mostrar</td></tr>');
      return;
    }
    var html = rows.map(function (r) {
      var id = r.id;
      var checked = state.selected[audience][id] ? ' checked' : '';
      if (audience === 'cliente') {
        var mail = String(r.correo || '').trim();
        var canSend = mail.indexOf('@') !== -1;
        return '<tr>' +
          '<td><input type="checkbox" class="ml-row-check" data-audience="cliente" data-id="' + id + '"' + checked + (canSend ? '' : ' disabled title="Sin correo válido"') + '></td>' +
          '<td><span class="badge-id">#' + id + '</span></td>' +
          '<td>' + esc(r.empresa || '—') + '</td>' +
          '<td>' + esc(r.nombre_contacto || '—') + '</td>' +
          '<td>' + esc(mail || '—') + '</td>' +
          '<td>' + esc(r.telefono || '—') + '</td>' +
          '<td>' + (canSend
            ? '<button type="button" class="btn btn-sm btn-outline-primary ml-send-one" data-audience="cliente" data-id="' + id + '"><i class="bi bi-send"></i></button>'
            : '<span class="text-muted small">Sin correo</span>') +
          '</td>' +
          '</tr>';
      }
      var nombre = [r.nombre, r.apellido].filter(Boolean).join(' ') || '—';
      return '<tr>' +
        '<td><input type="checkbox" class="ml-row-check" data-audience="lead" data-id="' + id + '"' + checked + '></td>' +
        '<td>' + esc(nombre) + '</td>' +
        '<td>' + esc(r.empresa || '—') + '</td>' +
        '<td>' + esc(r.correo || '') + '</td>' +
        '<td><span class="ml-badge ml-badge--muted">' + esc(r.pipeline_estado || 'lead') + '</span></td>' +
        '<td><button type="button" class="btn btn-sm btn-outline-primary ml-send-one" data-audience="lead" data-id="' + id + '"><i class="bi bi-send"></i></button></td>' +
        '</tr>';
    }).join('');
    $tb.html(html);
    if (audience === 'cliente') initClientesDt();
  }

  function statusLabel(row) {
    var st = row.status;
    if (st === 'sent') {
      if (parseInt(row.click_count, 10) > 0) return 'Clic';
      if (parseInt(row.open_count, 10) > 0) return 'Abierto';
      return 'Enviado';
    }
    if (st === 'failed') return 'Fallido';
    if (st === 'unsubscribed') return 'Baja';
    return st || '—';
  }

  function statusBadge(row) {
    var label = statusLabel(row);
    var cls = 'ml-badge--muted';
    if (label === 'Clic' || label === 'Abierto') cls = 'ml-badge--ok';
    else if (label === 'Fallido') cls = 'ml-badge--fail';
    else if (label === 'Baja') cls = 'ml-badge--warn';
    return '<span class="ml-badge ' + cls + '">' + esc(label) + '</span>';
  }

  function destroyTrackDt() {
    if (state.dtTrack) {
      try { state.dtTrack.destroy(); } catch (e) { /* ignore */ }
      state.dtTrack = null;
    }
  }

  function initTrackDt() {
    var $table = $('#mlTrackTable');
    if (!$table.length || !$.fn.DataTable || !state.sends.length) return;
    destroyTrackDt();
    state.dtTrack = $table.DataTable({
      searching: true,
      pageLength: 25,
      lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Todos']],
      order: [[7, 'desc']],
      autoWidth: false,
      deferRender: true,
      columnDefs: [
        { targets: [0, 4, 5, 6, 7], className: 'text-nowrap' },
        { targets: [5, 6], className: 'text-center' }
      ],
      language: {
        search: '',
        searchPlaceholder: 'Buscar plantilla, correo o destinatario…',
        lengthMenu: 'Mostrar _MENU_',
        info: 'Mostrando _START_–_END_ de _TOTAL_ envíos',
        infoEmpty: 'Sin envíos',
        infoFiltered: '(filtrado de _MAX_)',
        zeroRecords: 'Sin coincidencias',
        emptyTable: 'Aún no hay envíos registrados',
        paginate: {
          first: 'Primero',
          last: 'Último',
          next: 'Siguiente',
          previous: 'Anterior'
        }
      }
    });
    var st = String($('#mlTrackStatus').val() || '');
    if (st) state.dtTrack.column(4).search(st).draw();
  }

  function renderTracking() {
    destroyTrackDt();
    var $tb = $('#mlTrackBody');
    if (!state.sends.length) {
      $tb.html('<tr><td colspan="8" class="ml-empty">Aún no hay envíos registrados</td></tr>');
      return;
    }
    var html = state.sends.map(function (s) {
      var opens = parseInt(s.open_count, 10) || 0;
      var clicks = parseInt(s.click_count, 10) || 0;
      var when = s.sent_at || s.created_at || '';
      var label = statusLabel(s);
      var clickHint = s.last_click_url
        ? '<div class="small text-muted text-truncate ml-click-url" title="' + esc(s.last_click_url) + '">' + esc(s.last_click_url) + '</div>'
        : '';
      return '<tr>' +
        '<td data-order="' + s.id + '"><span class="ml-id">#' + s.id + '</span></td>' +
        '<td>' + esc(s.template_title || '—') + '</td>' +
        '<td>' + esc(s.audience) + ' #' + s.audience_id + '<div class="small text-muted">' + esc(s.name || '') + '</div></td>' +
        '<td class="small">' + esc(s.email) + '</td>' +
        '<td data-search="' + esc(label) + '">' + statusBadge(s) + '</td>' +
        '<td data-order="' + opens + '"><span class="ml-metric' + (opens ? ' is-on' : '') + '">' + opens + '</span></td>' +
        '<td data-order="' + clicks + '"><span class="ml-metric' + (clicks ? ' is-on' : '') + '">' + clicks + '</span>' + clickHint + '</td>' +
        '<td class="small text-nowrap" data-order="' + esc(when) + '">' + esc(when) + '</td>' +
        '</tr>';
    }).join('');
    $tb.html(html);
    initTrackDt();
  }

  function loadTemplates() {
    return api('templates_list').then(function (res) {
      if (!res || !res.ok) throw new Error((res && res.error) || 'Error al listar plantillas');
      state.templates = res.templates || [];
      renderTemplates();
    });
  }

  function loadStats() {
    return api('stats').then(function (res) {
      if (!res || !res.ok) throw new Error((res && res.error) || 'Error stats');
      state.stats = res.stats;
      state.sends = res.sends || [];
      state.queue = res.queue || [];
      renderKpis(state.stats);
      renderTracking();
      renderQueue(state.queue);
    });
  }

  function loadClientes(q) {
    return api('audience_clientes', { q: q || '' }).then(function (res) {
      if (!res || !res.ok) throw new Error((res && res.error) || 'Error clientes');
      state.clientes = res.rows || [];
      renderAudience('cliente');
    });
  }

  function loadLeads(q) {
    return api('audience_leads', { q: q || '' }).then(function (res) {
      if (!res || !res.ok) throw new Error((res && res.error) || 'Error leads');
      state.leads = res.rows || [];
      renderAudience('lead');
    });
  }

  function openTemplateModal(tpl) {
    fillTemplateFields(tpl);
    $('#mlTplAiPrompt').val('');
    showModal('mlTplModal');
  }

  function currentTplPayload() {
    return {
      id: parseInt($('#mlTplId').val(), 10) || 0,
      title: $('#mlTplTitle').val() || '',
      theme: $('#mlTplTheme').val() || '',
      subject: $('#mlTplSubject').val() || '',
      preheader: $('#mlTplPreheader').val() || '',
      body_html: joinDesignMeta($('#mlTplBody').val() || ''),
      image_url: $('#mlTplImage').val() || '',
      cta_label: $('#mlTplCtaLabel').val() || '',
      cta_url: $('#mlTplCtaUrl').val() || '',
      links: String($('#mlTplLinks').val() || '').trim(),
      active: $('#mlTplActive').is(':checked') ? 1 : 0
    };
  }

  function syncGenerateBtn() {
    var hasDraft = !!(aiDraft && (aiDraft.body_html || aiDraft.subject));
    var $btn = $('#btnAiGenerate');
    if (!$btn.length) return;
    $btn.html(hasDraft
      ? '<i class="bi bi-arrow-repeat"></i> Aplicar cambios al borrador'
      : '<i class="bi bi-magic"></i> Generar con prompt');
  }

  function paintPreview(html, subject) {
    if (subject) $('#mlPreviewSubject').text(subject || '');
    var frame = document.getElementById('mlPreviewFrame');
    if (!frame) return;
    var docHtml = html || '<p style="padding:24px;font-family:sans-serif;color:#333;">Sin contenido</p>';
    var painted = false;

    function writeFrame() {
      if (painted) return;
      painted = true;
      try {
        frame.setAttribute('sandbox', 'allow-same-origin allow-popups allow-popups-to-escape-sandbox');
        frame.onload = function () {
          try {
            var doc = frame.contentDocument;
            if (!doc) return;
            var h = Math.max(
              (doc.documentElement && doc.documentElement.scrollHeight) || 0,
              (doc.body && doc.body.scrollHeight) || 0,
              520
            );
            frame.style.height = (h + 8) + 'px';
          } catch (e) {}
        };
        /* srcdoc = mismo HTML del envío, aislado del CSS del admin */
        frame.srcdoc = docHtml;
      } catch (err) {
        try {
          var doc = frame.contentDocument;
          if (doc) {
            doc.open();
            doc.write(docHtml);
            doc.close();
          }
        } catch (e2) {}
      }
    }

    var $modal = $('#mlPreviewModal');
    if ($modal.hasClass('show') || $modal.is(':visible')) {
      writeFrame();
      return;
    }
    showModal('mlPreviewModal');
    $modal.one('shown.bs.modal', writeFrame);
    setTimeout(writeFrame, 400);
  }

  function refreshPreviewIfOpen(html, subject) {
    if (subject) $('#mlPreviewSubject').text(subject);
    var $modal = $('#mlPreviewModal');
    if (!$modal.hasClass('show') && !$modal.is(':visible')) return;
    paintPreview(html, subject);
  }

  function applyAiDraftToForm(draft, keepId) {
    var id = keepId ? (draft.id || $('#mlTplId').val() || '') : '';
    fillTemplateFields(Object.assign({}, draft, { id: id, active: draft.active != null ? draft.active : 0 }));
    aiDraft = Object.assign({}, draft, { id: id });
    setAiDraftUi(true);
  }

  function askImprovePrompt(title) {
    return Swal.fire({
      title: title || 'Mejorar plantilla',
      input: 'textarea',
      inputLabel: 'Prompt',
      inputPlaceholder: 'Ej. Más corto y directo, asunto más clickeable, tono formal, destaca el hosting…',
      inputAttributes: { maxlength: 2500 },
      showCancelButton: true,
      confirmButtonText: 'Mejorar',
      cancelButtonText: 'Cancelar',
      inputValidator: function (v) {
        if (!String(v || '').trim()) return 'Escribe un prompt';
        return undefined;
      }
    });
  }

  function afterAiDraft(draft, msg) {
    applyAiDraftToForm(draft, !!draft.id);
    Swal.fire({
      icon: 'success',
      title: 'Borrador listo',
      html: msg || 'Revisa con <strong>Ver preview</strong>. Solo se guarda si lo confirmas.',
      showDenyButton: true,
      confirmButtonText: 'Ver preview',
      denyButtonText: 'Editar'
    }).then(function (r) {
      if (r.isConfirmed) $('#btnAiPreview').trigger('click');
      else if (r.isDenied) openTemplateModal(Object.assign({}, aiDraft, { id: draft.id || '' }));
    });
  }

  function doSend(audience, ids) {
    if (!ids.length) {
      Swal.fire('Destinatarios', 'Selecciona al menos uno', 'warning');
      return;
    }
    fillTemplateSelect();
    $('#mlAssignCount').text(ids.length);
    $('#mlAssignAudience').text(audience === 'cliente' ? 'Clientes' : 'Leads');
    $('#mlAssignModal').data('audience', audience);
    $('#mlAssignModal').data('ids', ids);
    resetAssignRows();
    showModal('mlAssignModal');
  }

  function setTab(tab) {
    state.tab = tab;
    $('.ml-tab').removeClass('is-active');
    $('.ml-tab[data-tab="' + tab + '"]').addClass('is-active');
    $('.ml-panel').removeClass('is-active');
    $('#panel-' + tab).addClass('is-active');
    if (tab === 'clientes' || tab === 'leads') {
      updateSendBar(tab === 'clientes' ? 'cliente' : 'lead');
    } else {
      $('#mlSendBar').removeClass('is-on');
    }
    if (tab === 'clientes' && state.dtClientes) {
      setTimeout(function () { state.dtClientes.columns.adjust(); }, 50);
    }
    if (window.history && window.history.replaceState) {
      try {
        var url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        window.history.replaceState({}, '', url.toString());
      } catch (e) { /* ignore */ }
    }
  }

  $(function () {
    var app = document.getElementById('mlApp');
    if (!app) return;
    API = app.getAttribute('data-api') || '';
    UPLOAD = app.getAttribute('data-upload') || '';

    var initial = app.getAttribute('data-tab') || 'plantillas';
    setTab(initial);

    Promise.all([
      loadTemplates().catch(function (e) { console.error(e); throw e; }),
      loadStats().catch(function (e) { console.error(e); }),
      loadClientes('').catch(function (e) { console.error(e); }),
      loadLeads('').catch(function (e) { console.error(e); })
    ]).catch(function (e) {
      console.error(e);
      if (window.Swal) {
        Swal.fire('Mailing', (e && e.message) || 'No se pudieron cargar las plantillas', 'error');
      }
    });

    $(document).on('click', '.ml-tab', function () {
      setTab($(this).data('tab'));
    });

    $(document).on('click', '#btnNewTpl, #btnNewTplPanel', function (e) {
      e.preventDefault();
      openTemplateModal(null);
      setTimeout(function () {
        var el = document.getElementById('mlTplAiPrompt');
        if (el) el.focus();
      }, 350);
    });

    $(document).on('click', '.ml-tpl-edit', function () {
      var id = parseInt($(this).data('id'), 10);
      var tpl = state.templates.find(function (t) { return parseInt(t.id, 10) === id; });
      if (tpl) openTemplateModal(tpl);
    });

    $(document).on('click', '.ml-tpl-del', function () {
      var id = parseInt($(this).data('id'), 10);
      Swal.fire({ title: '¿Eliminar plantilla?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Eliminar' })
        .then(function (r) {
          if (!r.isConfirmed) return;
          api('template_delete', { id: id }).then(function (res) {
            if (!res || !res.ok) {
              Swal.fire('Error', (res && res.error) || 'No se pudo eliminar', 'error');
              return;
            }
            loadTemplates();
            loadStats();
          });
        });
    });

    $(document).on('click', '.ml-tpl-preview', function () {
      var id = parseInt($(this).data('id'), 10);
      api('preview', { template_id: id }).then(function (res) {
        if (!res || !res.ok) {
          Swal.fire('Error', (res && res.error) || 'Sin preview', 'error');
          return;
        }
        paintPreview(res.html || '', res.subject || '');
      });
    });

    $(document).on('click', '#btnSaveTpl', function () {
      var payload = {
        id: parseInt($('#mlTplId').val(), 10) || 0,
        title: $('#mlTplTitle').val(),
        theme: $('#mlTplTheme').val(),
        subject: $('#mlTplSubject').val(),
        preheader: $('#mlTplPreheader').val(),
        body_html: joinDesignMeta($('#mlTplBody').val()),
        image_url: $('#mlTplImage').val(),
        cta_label: $('#mlTplCtaLabel').val(),
        cta_url: $('#mlTplCtaUrl').val(),
        links: String($('#mlTplLinks').val() || '').trim(),
        active: $('#mlTplActive').is(':checked') ? 1 : 0
      };
      // Si venimos de un borrador IA editado, sincronizar
      if (aiDraft && !payload.id) {
        aiDraft = Object.assign({}, aiDraft, payload);
      }
      api('template_save', payload).then(function (res) {
        if (!res || !res.ok) {
          Swal.fire('Error', (res && res.error) || 'No se pudo guardar', 'error');
          return;
        }
        hideModal('mlTplModal');
        if (aiDraft && !payload.id) {
          aiDraft = null;
          setAiDraftUi(false);
        }
        Swal.fire({ icon: 'success', title: 'Plantilla guardada', timer: 1400, showConfirmButton: false });
        loadTemplates();
        loadStats();
      }).catch(function (err) {
        Swal.fire('Error', (err && err.message) || 'No se pudo guardar', 'error');
      });
    });

    $(document).on('click', '[data-dismiss="modal"]', function () {
      var modal = $(this).closest('.modal').attr('id');
      if (modal) hideModal(modal);
    });

    $(document).on('change', '#mlTplUpload', function () {
      var file = this.files && this.files[0];
      if (!file) return;
      var fd = new FormData();
      fd.append('image', file);
      fetch(UPLOAD, { method: 'POST', credentials: 'same-origin', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (!res || !res.ok) {
            Swal.fire('Imagen', (res && res.error) || 'No se pudo subir', 'error');
            return;
          }
          $('#mlTplImage').val(res.url);
        })
        .catch(function () {
          Swal.fire('Imagen', 'Error de conexión al subir', 'error');
        });
    });

    $('#mlSearchClientes').on('input', function () {
      var q = String($(this).val() || '');
      if (state.dtClientes) state.dtClientes.search(q).draw();
    });

    $('#btnPrintClientes').on('click', function () {
      if (!state.dtClientes) return;
      var rows = state.dtClientes.rows({ search: 'applied' }).data();
      var body = '';
      for (var i = 0; i < rows.length; i++) {
        var r = rows[i];
        body += '<tr><td>' + r[1] + '</td><td>' + r[2] + '</td><td>' + r[3] + '</td><td>' + r[4] + '</td><td>' + r[5] + '</td></tr>';
      }
      var win = window.open('', 'mlPrintClientes');
      if (!win) return;
      win.document.write(
        '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Clientes</title>' +
        '<style>body{font-family:Arial,sans-serif;font-size:12px;padding:16px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:6px 8px;text-align:left}th{background:#0a0a0f;color:#fff}</style>' +
        '</head><body><h2>Clientes</h2><table><thead><tr><th>ID</th><th>Empresa</th><th>Contacto</th><th>Correo</th><th>Teléfono</th></tr></thead><tbody>' +
        body + '</tbody></table></body></html>'
      );
      win.document.close();
      win.focus();
      win.print();
    });
    $('#mlSearchLeads').on('input', function () {
      var q = $(this).val();
      clearTimeout(window.__mlLeadT);
      window.__mlLeadT = setTimeout(function () { loadLeads(q); }, 280);
    });

    $(document).on('change', '.ml-row-check', function () {
      var audience = $(this).data('audience');
      var id = parseInt($(this).data('id'), 10);
      state.selected[audience][id] = $(this).is(':checked');
      if (!state.selected[audience][id]) delete state.selected[audience][id];
      updateSendBar(audience);
    });

    $(document).on('change', '#mlCheckAllClientes', function () {
      var on = $(this).is(':checked');
      var $boxes = state.dtClientes
        ? $(state.dtClientes.rows({ search: 'applied', page: 'current' }).nodes()).find('.ml-row-check:not(:disabled)')
        : $('#mlClientesBody .ml-row-check:not(:disabled)');
      $boxes.each(function () {
        var id = parseInt($(this).data('id'), 10);
        $(this).prop('checked', on);
        if (on) state.selected.cliente[id] = true;
        else delete state.selected.cliente[id];
      });
      updateSendBar('cliente');
    });
    $('#mlCheckAllLeads').on('change', function () {
      var on = $(this).is(':checked');
      state.leads.forEach(function (r) {
        if (on) state.selected.lead[r.id] = true;
        else delete state.selected.lead[r.id];
      });
      renderAudience('lead');
      updateSendBar('lead');
    });

    $('#btnMlSend').on('click', function () {
      var audience = $('#mlSendBar').attr('data-audience') || 'cliente';
      doSend(audience, selectedIds(audience));
    });

    $(document).on('change', '#mlAssignRows input[type=radio]', function () {
      syncRowModeUi($(this).closest('.ml-assign-row'));
    });

    $(document).on('click', '#btnAssignAddRow', function () {
      var n = $('#mlAssignRows .ml-assign-row').length;
      if (n >= 10) {
        Swal.fire('Límite', 'Máximo 10 plantillas por lote', 'info');
        return;
      }
      $('#mlAssignRows').append(assignRowHtml(n));
      syncRowModeUi($('#mlAssignRows .ml-assign-row').last());
    });

    $(document).on('click', '.ml-assign-remove', function () {
      $(this).closest('.ml-assign-row').remove();
      $('#mlAssignRows .ml-assign-row').each(function (i) {
        $(this).attr('data-idx', i);
        $(this).find('.ml-assign-row__head strong').text('Plantilla #' + (i + 1));
      });
    });

    $(document).on('click', '#btnAssignConfirm', function () {
      var audience = $('#mlAssignModal').data('audience') || 'cliente';
      var ids = $('#mlAssignModal').data('ids') || [];
      var packed = collectAssignItems();
      if (packed.error) {
        Swal.fire('Revisa el formulario', packed.error, 'warning');
        return;
      }
      if (!packed.items.length) {
        Swal.fire('Plantillas', 'Agrega al menos una plantilla', 'warning');
        return;
      }
      Swal.fire({ title: 'Programando envíos…', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
      api('schedule_send', {
        audience: audience,
        recipients: ids.map(function (id) { return { id: id }; }),
        items: packed.items
      }).then(function (res) {
        if (!res || !res.ok) {
          Swal.fire('Error', (res && res.error) || 'No se pudo completar', 'error');
          return;
        }
        hideModal('mlAssignModal');
        state.selected[audience] = {};
        updateSendBar(audience);
        renderAudience(audience);
        loadStats();
        Swal.fire('Listo', res.message || 'OK', 'success');
      }).catch(function (err) {
        Swal.fire('Error', (err && err.message) || 'Fallo de conexión', 'error');
      });
    });

    $(document).on('click', '#btnProcessQueue', function () {
      Swal.fire({ title: 'Procesando cola…', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
      api('process_queue', { limit: 40 }).then(function (res) {
        loadStats();
        Swal.fire('Cola', 'Enviados: ' + (res.sent || 0) + ' · Fallidos: ' + (res.failed || 0), 'success');
      }).catch(function () {
        Swal.fire('Error', 'No se pudo procesar la cola', 'error');
      });
    });

    $(document).on('click', '.ml-send-one', function () {
      var audience = $(this).data('audience');
      var id = parseInt($(this).data('id'), 10);
      if (state.tab !== (audience === 'cliente' ? 'clientes' : 'leads')) {
        setTab(audience === 'cliente' ? 'clientes' : 'leads');
      }
      doSend(audience, [id]);
    });

    $(document).on('change', '#mlTrackStatus', function () {
      if (!state.dtTrack) return;
      var st = String($(this).val() || '');
      state.dtTrack.column(4).search(st).draw();
    });

    $('#btnRefreshTrack').on('click', function () {
      loadStats();
    });

    // —— Estado de conexión IA ——
    function paintAiStatus(res) {
      var $box = $('#mlAiConn');
      var connected = !!(res && res.connected);
      var pinged = !!(res && res.pinged);
      var configured = !!(res && res.configured);
      var available = connected || (!pinged && configured);
      var tone = 'off';
      if (connected) tone = 'on';
      else if (configured && !pinged) tone = 'warn';
      else if (res && res.tone === 'warn') tone = 'warn';

      $box.removeClass('is-on is-off is-warn');
      if (tone === 'on') $box.addClass('is-on');
      else if (tone === 'warn') $box.addClass('is-warn');
      else $box.addClass('is-off');

      $('#mlAiStatusBadge').text((res && res.label) || (available ? 'IA' : 'IA sin conexión'));
      $('#mlAiStatusDetail').text((res && res.detail) || '');
      $('#btnAiGenerate, #btnTplAiRun').prop('disabled', !available);
    }

    function refreshAiStatus(doPing) {
      $('#mlAiStatusBadge').text(doPing ? 'Probando conexión…' : 'Comprobando IA…');
      $('#mlAiStatusDetail').text(doPing ? 'Ping a OpenAI' : 'Leyendo configuración');
      return api('ai_status', { ping: doPing ? 1 : 0 }).then(function (res) {
        paintAiStatus(res || {});
        return res;
      }).catch(function () {
        paintAiStatus({
          available: false,
          connected: false,
          tone: 'off',
          label: 'Sin respuesta',
          detail: 'No se pudo consultar el estado de la IA'
        });
      });
    }

    refreshAiStatus(false).then(function (res) {
      // Si hay clave, valida conexión real en segundo plano
      if (res && res.configured) {
        refreshAiStatus(true);
      }
    });

    $(document).on('click', '#btnAiPing', function () {
      var $btn = $(this).prop('disabled', true);
      refreshAiStatus(true).finally(function () {
        $btn.prop('disabled', false);
      });
    });

    $(document).on('change', '#mlAiImages', function () {
      var files = this.files;
      if (!files || !files.length) {
        aiImageUrls = [];
        renderAiThumbs([]);
        return;
      }
      Swal.fire({ title: 'Subiendo imágenes…', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
      uploadAiImages(files).then(function (urls) {
        aiImageUrls = urls;
        renderAiThumbs(urls);
        Swal.close();
        if (!urls.length) {
          Swal.fire('Imágenes', 'No se pudieron subir', 'warning');
        }
      }).catch(function () {
        Swal.fire('Imágenes', 'Error al subir', 'error');
      });
    });

    function runAiFromPrompt(prompt) {
      var theme = String($('#mlAiTheme').val() || '').trim();
      var cta = String($('#mlAiCta').val() || '').trim();
      var links = String($('#mlAiLinks').val() || '').trim();
      var hasDraft = !!(aiDraft && (aiDraft.body_html || aiDraft.subject));
      var action = hasDraft ? 'ai_improve' : 'ai_generate';
      var payload = hasDraft
        ? { prompt: prompt, draft: aiDraft, image_urls: aiImageUrls, links: links }
        : { prompt: prompt, theme: theme, cta_url: cta, image_urls: aiImageUrls, links: links };

      Swal.fire({
        title: hasDraft ? 'Modificando este borrador…' : 'Generando con IA…',
        html: hasDraft ? 'Se actualiza la misma plantilla, no se crea otra.' : 'Puede tomar unos segundos',
        allowOutsideClick: false,
        didOpen: function () { Swal.showLoading(); }
      });

      return api(action, payload).then(function (res) {
        if (!res || !res.ok || !res.draft) {
          Swal.fire('IA', (res && res.error) || 'No se pudo completar', 'error');
          return;
        }
        var keepId = !!(hasDraft && aiDraft && aiDraft.id);
        var draft = Object.assign({}, res.draft);
        if (keepId) draft.id = aiDraft.id;
        /* Conservar imagen hero del borrador actual al mejorar con IA. */
        if (hasDraft && aiDraft && aiDraft.image_url) {
          draft.image_url = aiDraft.image_url;
        }
        afterAiDraft(
          draft,
          hasDraft
            ? 'Cambios aplicados <strong>sobre el mismo borrador</strong>. Revisa el preview.'
            : 'Usa <strong>Ver preview</strong> o <strong>Editar</strong>. Solo se guarda si pulsas <strong>Guardar plantilla</strong>.'
        );
        syncGenerateBtn();
        if (hasDraft) {
          api('preview', { draft: aiDraft }).then(function (prev) {
            if (prev && prev.ok) {
              refreshPreviewIfOpen(prev.html, prev.subject);
            }
          });
        }
      }).catch(function (err) {
        Swal.fire('IA', (err && err.message) || 'Error de conexión', 'error');
      });
    }

    $(document).on('click', '#btnAiGenerate', function () {
      var prompt = String($('#mlAiPrompt').val() || '').trim();
      if (!prompt) {
        Swal.fire('Prompt', aiDraft
          ? 'Escribe qué quieres añadir o cambiar en este borrador'
          : 'Escribe qué quieres que diga el correo', 'warning');
        return;
      }
      runAiFromPrompt(prompt);
    });

    $(document).on('click', '#btnTplAiRun', function () {
      var prompt = String($('#mlTplAiPrompt').val() || '').trim();
      if (!prompt) {
        Swal.fire('Prompt', 'Escribe el prompt para crear o mejorar', 'warning');
        return;
      }
      var cur = currentTplPayload();
      var hasContent = !!(cur.subject || cur.body_html);
      var action = hasContent ? 'ai_improve' : 'ai_generate';
      var payload = hasContent
        ? { prompt: prompt, draft: cur, template_id: cur.id || 0, image_urls: cur.image_url ? [cur.image_url] : [], links: cur.links || '' }
        : { prompt: prompt, theme: cur.theme, cta_url: cur.cta_url, image_urls: cur.image_url ? [cur.image_url] : aiImageUrls, links: cur.links || '' };
      Swal.fire({ title: hasContent ? 'Mejorando…' : 'Generando…', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
      api(action, payload).then(function (res) {
        if (!res || !res.ok || !res.draft) {
          Swal.fire('IA', (res && res.error) || 'No se pudo completar', 'error');
          return;
        }
        var draft = res.draft;
        if (hasContent && cur.id) draft.id = cur.id;
        if (hasContent && cur.image_url) draft.image_url = cur.image_url;
        fillTemplateFields(Object.assign({}, draft, {
          id: hasContent ? (cur.id || '') : '',
          active: hasContent ? cur.active : 0,
          image_url: (hasContent && cur.image_url) ? cur.image_url : (draft.image_url || '')
        }));
        aiDraft = Object.assign({}, draft, {
          id: hasContent ? cur.id : '',
          image_url: (hasContent && cur.image_url) ? cur.image_url : (draft.image_url || '')
        });
        setAiDraftUi(true);
        Swal.fire({
          icon: 'success',
          title: hasContent ? 'Plantilla mejorada' : 'Borrador generado',
          html: 'Revisa los campos. Guarda solo si te convence.',
          confirmButtonText: 'Ver preview'
        }).then(function (r) {
          if (r.isConfirmed) $('#btnAiPreview').trigger('click');
        });
      }).catch(function (err) {
        Swal.fire('IA', (err && err.message) || 'Error de conexión', 'error');
      });
    });

    $(document).on('click', '.ml-tpl-improve', function () {
      var id = parseInt($(this).data('id'), 10);
      var tpl = state.templates.find(function (t) { return parseInt(t.id, 10) === id; });
      if (!tpl) return;
      askImprovePrompt('Mejorar «' + (tpl.title || 'plantilla') + '»').then(function (r) {
        if (!r.isConfirmed) return;
        Swal.fire({ title: 'Mejorando con IA…', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
        return api('ai_improve', { template_id: id, prompt: String(r.value || '').trim() }).then(function (res) {
          if (!res || !res.ok || !res.draft) {
            Swal.fire('IA', (res && res.error) || 'No se pudo mejorar', 'error');
            return;
          }
          var draft = Object.assign({}, res.draft, { id: id, active: tpl.active });
          if (tpl.image_url) draft.image_url = tpl.image_url;
          aiDraft = draft;
          setAiDraftUi(true);
          fillTemplateFields(draft);
          Swal.fire({
            icon: 'success',
            title: 'Borrador mejorado',
            html: 'Puedes <strong>reemplazar</strong> esta plantilla o <strong>guardar una nueva</strong>.',
            showDenyButton: true,
            showCancelButton: true,
            confirmButtonText: 'Reemplazar esta',
            denyButtonText: 'Guardar como nueva',
            cancelButtonText: 'Editar / preview'
          }).then(function (choice) {
            if (choice.isConfirmed) {
              api('template_save', Object.assign({}, draft, { id: id })).then(function (saveRes) {
                if (!saveRes || !saveRes.ok) {
                  Swal.fire('Error', (saveRes && saveRes.error) || 'No se pudo guardar', 'error');
                  return;
                }
                aiDraft = null;
                setAiDraftUi(false);
                Swal.fire({ icon: 'success', title: 'Plantilla actualizada', timer: 1400, showConfirmButton: false });
                loadTemplates();
              });
            } else if (choice.isDenied) {
              api('template_save', Object.assign({}, draft, { id: 0, active: 1, title: (draft.title || 'Plantilla') + ' (mejorada)' })).then(function (saveRes) {
                if (!saveRes || !saveRes.ok) {
                  Swal.fire('Error', (saveRes && saveRes.error) || 'No se pudo guardar', 'error');
                  return;
                }
                aiDraft = null;
                setAiDraftUi(false);
                Swal.fire({ icon: 'success', title: 'Nueva plantilla guardada', timer: 1400, showConfirmButton: false });
                loadTemplates();
                loadStats();
              });
            } else if (choice.dismiss === Swal.DismissReason.cancel) {
              openTemplateModal(draft);
            }
          });
        });
      }).catch(function (err) {
        if (err) Swal.fire('IA', (err && err.message) || 'Error de conexión', 'error');
      });
    });

    $(document).on('click', '#btnAiExample', function () {
      var $btn = $(this).prop('disabled', true);
      api('ai_example', {}).then(function (res) {
        if (!res || !res.ok || !res.draft) {
          Swal.fire('Ejemplo', (res && res.error) || 'No se pudo armar el preview', 'error');
          return;
        }
        afterAiDraft(res.draft, 'Ejemplo de plantilla IA (captación León). No está guardada: puedes <strong>Ver preview</strong> o generar la tuya con un prompt.');
        /* El preview se abre con «Ver preview» → mismo HTML que el envío vía api preview. */
      }).catch(function () {
        Swal.fire('Ejemplo', 'No se pudo cargar el preview', 'error');
      }).finally(function () {
        $btn.prop('disabled', false);
      });
    });

    $(document).on('click', '.ml-preview-dev', function () {
      var mode = $(this).data('preview') === 'mobile' ? 'mobile' : 'desktop';
      $('.ml-preview-dev').removeClass('is-on');
      $(this).addClass('is-on');
      $('#mlPreviewStage').toggleClass('is-mobile', mode === 'mobile').toggleClass('is-desktop', mode === 'desktop');
      var frame = document.getElementById('mlPreviewFrame');
      if (frame && frame.srcdoc) {
        /* Reaplicar srcdoc para que el @media del correo recalcule (PC ↔ móvil) como en clientes reales. */
        var html = frame.srcdoc;
        frame.srcdoc = '';
        setTimeout(function () {
          frame.srcdoc = html;
        }, 30);
      }
    });

    $(document).on('click', '#btnAiPreview', function () {
      if (!aiDraft) return;
      api('preview', { draft: aiDraft }).then(function (res) {
        if (!res || !res.ok) {
          Swal.fire('Preview', (res && res.error) || 'Sin preview', 'error');
          return;
        }
        paintPreview(res.html || '', res.subject || '');
      });
    });

    $(document).on('click', '#btnAiSave', function () {
      if (!aiDraft) return;
      Swal.fire({
        title: '¿Guardar plantilla?',
        text: 'Se agregará al catálogo para usarla en envíos.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Guardar',
        cancelButtonText: 'Cancelar'
      }).then(function (r) {
        if (!r.isConfirmed) return;
        var payload = Object.assign({}, aiDraft, {
          id: 0,
          active: 1,
          title: $('#mlTplTitle').val() || aiDraft.title,
          theme: $('#mlTplTheme').val() || aiDraft.theme,
          subject: $('#mlTplSubject').val() || aiDraft.subject,
          preheader: $('#mlTplPreheader').val() || aiDraft.preheader,
          body_html: joinDesignMeta($('#mlTplBody').val() || '') || aiDraft.body_html,
          image_url: $('#mlTplImage').val() || aiDraft.image_url,
          cta_label: $('#mlTplCtaLabel').val() || aiDraft.cta_label,
          cta_url: $('#mlTplCtaUrl').val() || aiDraft.cta_url
        });
        api('template_save', payload).then(function (res) {
          if (!res || !res.ok) {
            Swal.fire('Error', (res && res.error) || 'No se pudo guardar', 'error');
            return;
          }
          aiDraft = null;
          setAiDraftUi(false);
          Swal.fire({ icon: 'success', title: 'Plantilla guardada', timer: 1500, showConfirmButton: false });
          loadTemplates();
          loadStats();
        });
      });
    });

    $(document).on('click', '#btnAiDiscard', function () {
      aiDraft = null;
      setAiDraftUi(false);
    });

    $(document).on('click', '#btnAiEditDraft', function () {
      if (!aiDraft) return;
      openTemplateModal(Object.assign({}, aiDraft, { id: '', active: 0 }));
    });
  });
})(window.jQuery);
