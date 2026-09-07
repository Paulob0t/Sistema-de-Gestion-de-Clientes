/**
 * Modal de cruce leads / calificados / cerrados (Plazas y URLs).
 */
(function (window, document) {
  'use strict';

  var cfg = {
    apiUrl: '',
    period: '90d',
    from: null,
    to: null,
    plaza: null,
    categoria: null
  };
  var bound = false;
  var lastOpenKey = '';
  var lastOpenAt = 0;

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }

  function ensureModal() {
    var el = document.getElementById('seoLeadsModal');
    if (el) return el;

    el = document.createElement('div');
    el.id = 'seoLeadsModal';
    el.className = 'seo-leads-modal';
    el.setAttribute('aria-hidden', 'true');
    el.innerHTML =
      '<div class="seo-leads-modal-backdrop" data-seo-leads-close="1"></div>' +
      '<div class="seo-leads-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="seoLeadsModalTitle">' +
      '  <div class="seo-leads-modal-head">' +
      '    <div><h3 id="seoLeadsModalTitle">Leads</h3><p class="seo-leads-modal-sub" id="seoLeadsModalSub"></p></div>' +
      '    <button type="button" class="seo-leads-modal-x" data-seo-leads-close="1" aria-label="Cerrar">&times;</button>' +
      '  </div>' +
      '  <div class="seo-leads-modal-body">' +
      '    <div id="seoLeadsModalLoading" class="seo-leads-modal-loading" hidden>Cargando registros…</div>' +
      '    <div id="seoLeadsModalError" class="seo-leads-modal-error" hidden></div>' +
      '    <div class="seo-leads-table-wrap"><table class="table table-sm va-table w-100">' +
      '      <thead><tr>' +
      '        <th>Fecha</th><th>Nombre</th><th>Teléfono</th><th>Correo</th>' +
      '        <th>Servicio</th><th>Estatus</th><th>Origen</th><th></th>' +
      '      </tr></thead><tbody id="seoLeadsModalBody"></tbody>' +
      '    </table></div>' +
      '  </div>' +
      '</div>';
    (document.body || document.documentElement).appendChild(el);
    return el;
  }

  function setShown(el, shown) {
    if (!el) return;
    if (shown) {
      el.classList.add('is-open');
      el.setAttribute('aria-hidden', 'false');
      el.removeAttribute('hidden');
      el.style.setProperty('display', 'flex', 'important');
      el.style.setProperty('position', 'fixed', 'important');
      el.style.setProperty('inset', '0', 'important');
      el.style.setProperty('z-index', '2147483000', 'important');
      el.style.setProperty('align-items', 'center', 'important');
      el.style.setProperty('justify-content', 'center', 'important');
      el.style.setProperty('padding', '1rem', 'important');
      el.style.setProperty('background', 'rgba(15,23,42,.55)', 'important');
      document.documentElement.classList.add('seo-leads-modal-open');
      document.body.classList.add('seo-leads-modal-open');
    } else {
      el.classList.remove('is-open');
      el.setAttribute('aria-hidden', 'true');
      el.setAttribute('hidden', 'hidden');
      el.style.setProperty('display', 'none', 'important');
      document.documentElement.classList.remove('seo-leads-modal-open');
      document.body.classList.remove('seo-leads-modal-open');
    }
  }

  function closeModal() {
    setShown(document.getElementById('seoLeadsModal'), false);
  }

  function openModal() {
    var el = ensureModal();
    setShown(el, true);
    return el;
  }

  function renderRows(leads) {
    var body = document.getElementById('seoLeadsModalBody');
    if (!body) return;
    if (!leads.length) {
      body.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Sin registros en este cruce para el periodo.</td></tr>';
      return;
    }
    body.innerHTML = leads.map(function (l) {
      var origen = l.path_label && l.path_label !== '—'
        ? '<strong>' + esc(l.path_label) + '</strong><br><code>' + esc(l.path) + '</code>'
        : '<code>' + esc(l.path || '—') + '</code>';
      var link = l.detalle_url
        ? '<a class="btn btn-sm btn-outline-primary" href="' + esc(l.detalle_url) + '" target="_blank" rel="noopener">Ver</a>'
        : '';
      return '<tr>' +
        '<td>' + esc(l.fecha_registro) + '</td>' +
        '<td><strong>' + esc(l.nombre) + '</strong></td>' +
        '<td>' + esc(l.telefono) + '</td>' +
        '<td>' + esc(l.correo) + '</td>' +
        '<td>' + esc(l.servicio) + '</td>' +
        '<td><span class="seo-lead-status">' + esc(l.pipeline_label) + '</span></td>' +
        '<td class="seo-lead-origen">' + origen + '</td>' +
        '<td>' + link + '</td>' +
        '</tr>';
    }).join('');
  }

  function buildUrl(btn) {
    var qs = '?period=' + encodeURIComponent(cfg.period || '90d');
    qs += '&status=' + encodeURIComponent(btn.getAttribute('data-status') || 'leads');
    qs += '&scope=' + encodeURIComponent(btn.getAttribute('data-scope') || 'site');
    var plaza = btn.getAttribute('data-plaza') || cfg.plaza || '';
    var path = btn.getAttribute('data-path') || '';
    var categoria = btn.getAttribute('data-categoria') || cfg.categoria || '';
    if (plaza) qs += '&plaza=' + encodeURIComponent(plaza);
    if (path) qs += '&path=' + encodeURIComponent(path);
    if (categoria) qs += '&categoria=' + encodeURIComponent(categoria);
    if (cfg.period === 'custom' && cfg.from && cfg.to) {
      qs += '&from=' + encodeURIComponent(cfg.from) + '&to=' + encodeURIComponent(cfg.to);
    }
    return (cfg.apiUrl || '') + qs;
  }

  function loadLeads(btn) {
    if (!btn || !btn.getAttribute) {
      console.error('SeoLeadsModal.open: botón inválido', btn);
      return false;
    }
    // Evita doble disparo (listener captura + onclick inline)
    var openKey = [
      btn.getAttribute('data-status') || '',
      btn.getAttribute('data-scope') || '',
      btn.getAttribute('data-plaza') || '',
      btn.getAttribute('data-path') || ''
    ].join('|');
    var now = Date.now();
    if (openKey === lastOpenKey && (now - lastOpenAt) < 500) {
      return false;
    }
    lastOpenKey = openKey;
    lastOpenAt = now;

    openModal();
    var title = document.getElementById('seoLeadsModalTitle');
    var sub = document.getElementById('seoLeadsModalSub');
    var loading = document.getElementById('seoLeadsModalLoading');
    var err = document.getElementById('seoLeadsModalError');
    var body = document.getElementById('seoLeadsModalBody');

    if (title) title.textContent = 'Cargando…';
    if (sub) sub.textContent = '';
    if (err) { err.hidden = true; err.textContent = ''; }
    if (body) body.innerHTML = '';
    if (loading) loading.hidden = false;

    if (!cfg.apiUrl) {
      if (loading) loading.hidden = true;
      if (err) {
        err.hidden = false;
        err.textContent = 'No hay URL de API configurada (SeoLeadsModal.init).';
      }
      return false;
    }

    var url = buildUrl(btn);
    fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) {
        return r.text().then(function (text) {
          var data = null;
          try { data = text ? JSON.parse(text) : null; } catch (e) {
            throw new Error('Respuesta inválida del servidor');
          }
          if (!r.ok) {
            throw new Error((data && (data.error || (data.data && data.data.error))) || ('HTTP ' + r.status));
          }
          return data;
        });
      })
      .then(function (j) {
        if (loading) loading.hidden = true;
        if (!j || !j.success || !j.data) {
          if (err) {
            err.hidden = false;
            err.textContent = (j && j.error) || (j && j.data && j.data.error) || 'No se pudieron cargar los registros.';
          }
          return;
        }
        var d = j.data;
        if (title) title.textContent = d.title || 'Leads';
        if (sub) {
          sub.textContent = (d.count || 0) + ' registro(s) · ' +
            String((d.period && d.period.from) || '').slice(0, 10) + ' → ' +
            String((d.period && d.period.to) || '').slice(0, 10);
        }
        renderRows(d.leads || []);
      })
      .catch(function (e) {
        if (loading) loading.hidden = true;
        if (err) {
          err.hidden = false;
          err.textContent = (e && e.message) ? e.message : 'Error de conexión al cargar leads.';
        }
      });
    return false;
  }

  function findLeadBtn(node) {
    while (node && node !== document && node !== document.documentElement) {
      if (node.nodeType === 1 && node.getAttribute && node.getAttribute('data-seo-leads') != null) {
        return node;
      }
      node = node.parentNode;
    }
    return null;
  }

  function findClose(node) {
    while (node && node !== document && node !== document.documentElement) {
      if (node.nodeType === 1 && node.getAttribute && node.getAttribute('data-seo-leads-close') != null) {
        return node;
      }
      node = node.parentNode;
    }
    return null;
  }

  function onDocClick(e) {
    if (findClose(e.target)) {
      e.preventDefault();
      e.stopPropagation();
      closeModal();
      return;
    }
    var btn = findLeadBtn(e.target);
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    loadLeads(btn);
  }

  function bind() {
    if (bound) return;
    bound = true;
    document.addEventListener('click', onDocClick, true);
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeModal();
    });
  }

  window.SeoLeadsModal = {
    init: function (options) {
      cfg = Object.assign({}, cfg, options || {});
      ensureModal();
      bind();
    },
    open: loadLeads,
    close: closeModal
  };

  bind();
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      ensureModal();
      bind();
    });
  } else {
    ensureModal();
  }
})(window, document);
