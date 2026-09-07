/**
 * Brief de ticket para desarrolladores.
 * Orden: contexto → trabajo → tareas → validación → bloqueos.
 */
(function (global) {
  'use strict';

  var ORDER_CORE = ['resumen', 'detalle', 'rf', 'ca', 'dudas'];
  var LABELS = {
    titulo: { n: '', title: 'Título', sub: '', kind: 'titulo' },
    resumen: { n: '1', title: 'Contexto', sub: 'Qué pide el cliente', kind: 'resumen' },
    detalle: { n: '2', title: 'Implementación', sub: 'Qué hay que hacer', kind: 'detalle' },
    rf: { n: '3', title: 'Tareas', sub: 'Requerimientos funcionales', kind: 'rf' },
    ca: { n: '4', title: 'Validación', sub: 'Criterios de aceptación', kind: 'ca' },
    dudas: { n: '!', title: 'Bloqueos', sub: 'Dudas abiertas', kind: 'dudas' },
    prio: { n: '', title: 'Prioridad', sub: '', kind: 'prio' },
    plain: { n: '', title: 'Descripción', sub: '', kind: 'plain' }
  };

  var modalState = {
    id: 0,
    titulo: '',
    desc: '',
    meta: {},
    images: [],
    files: []
  };

  function decodeVisibleEscapes(s) {
    if (typeof s !== 'string') return s;
    return s.replace(/\\r\\n/g, '\n').replace(/\\n/g, '\n').replace(/\\r/g, '\n');
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function solicitudesBase() {
    // Preferir el directorio real de la página (mismo folder que uploads/).
    // SOLICITUDES_BASE a veces queda en "/" cuando SCRIPT_NAME es "/solicitudes/".
    try {
      var path = String((global.location && global.location.pathname) || '');
      if (/\/[^/]+\.php$/i.test(path)) {
        return path.replace(/\/[^/]+\.php$/i, '/');
      }
      if (path.slice(-1) === '/') return path || './';
      var idx = path.lastIndexOf('/');
      if (idx >= 0) return path.slice(0, idx + 1);
    } catch (e) { /* ignore */ }

    if (global.SOLICITUDES_BASE) {
      var configured = String(global.SOLICITUDES_BASE).replace(/\/?$/, '/');
      if (configured && configured !== '/') return configured;
    }
    return './';
  }

  function encodeMediaPath(path) {
    return String(path || '')
      .split('/')
      .map(function (seg) {
        if (!seg) return '';
        try {
          return encodeURIComponent(decodeURIComponent(seg));
        } catch (e) {
          return encodeURIComponent(seg);
        }
      })
      .join('/');
  }

  function rawMediaPath(item) {
    if (item == null) return '';
    if (typeof item === 'string') return item.trim();
    if (typeof item === 'object') {
      return String(item.ruta || item.url || item.path || item.src || item.href || '').trim();
    }
    return '';
  }

  function resolveTicketMediaUrl(item) {
    var raw = rawMediaPath(item);
    if (!raw) return '';
    if (/^(data:|blob:)/i.test(raw)) return raw;
    if (/^https?:\/\//i.test(raw)) {
      try {
        var u = new URL(raw);
        u.pathname = encodeMediaPath(u.pathname);
        return u.toString();
      } catch (e) {
        return raw;
      }
    }
    // Absoluta ya normalizada por el servidor (/solicitudes/uploads/...)
    if (raw.charAt(0) === '/') return encodeMediaPath(raw);
    // Preferir base absoluta del módulo solicitudes
    var base = '';
    if (global.SOLICITUDES_BASE) {
      base = String(global.SOLICITUDES_BASE).replace(/\/?$/, '/');
      if (base === '/') base = solicitudesBase();
    } else {
      base = solicitudesBase();
    }
    var clean = raw.replace(/^\.\//, '').replace(/^\/+/, '');
    if (clean.indexOf('uploads/') === 0) return base + encodeMediaPath(clean);
    return base + encodeMediaPath('uploads/solicitudes/' + clean);
  }

  function mediaUrlCandidates(item) {
    var out = [];
    function push(u) {
      if (u && out.indexOf(u) === -1) out.push(u);
    }

    var raw = rawMediaPath(item);
    if (!raw) return out;
    if (/^(data:|blob:)/i.test(raw)) {
      push(raw);
      return out;
    }
    if (/^https?:\/\//i.test(raw)) {
      push(resolveTicketMediaUrl(raw));
      return out;
    }

    var base = solicitudesBase();
    if (global.SOLICITUDES_BASE) {
      var configured = String(global.SOLICITUDES_BASE).replace(/\/?$/, '/');
      if (configured && configured !== '/') base = configured;
    }
    var clean = raw.replace(/^\.\//, '').replace(/^\/+/, '');

    // Absoluta recibida del API
    if (raw.charAt(0) === '/') {
      push(encodeMediaPath(raw));
      if (raw.indexOf('/uploads/') !== -1 && raw.indexOf('/solicitudes/') === -1) {
        push(encodeMediaPath(raw.replace('/uploads/', '/solicitudes/uploads/')));
      }
    }

    // Base + uploads/solicitudes|uploads
    push(base + encodeMediaPath(clean));
    if (clean.indexOf('uploads/solicitudes/') === 0) {
      push(base + encodeMediaPath(clean));
      push(base + encodeMediaPath(clean.replace(/^uploads\/solicitudes\//, 'uploads/')));
      push(encodeMediaPath(clean));
      push(encodeMediaPath(clean.replace(/^uploads\/solicitudes\//, 'uploads/')));
    } else if (clean.indexOf('uploads/') === 0) {
      push(base + encodeMediaPath(clean));
      push(base + encodeMediaPath(clean.replace(/^uploads\//, 'uploads/solicitudes/')));
      push(encodeMediaPath(clean));
      push(encodeMediaPath('uploads/solicitudes/' + clean.replace(/^uploads\//, '')));
    } else {
      push(base + encodeMediaPath('uploads/solicitudes/' + clean));
      push(base + encodeMediaPath('uploads/' + clean));
      push(encodeMediaPath('uploads/solicitudes/' + clean));
      push(encodeMediaPath('uploads/' + clean));
    }

    return out;
  }

  function extractMarkdownImages(text) {
    var out = [];
    String(text || '').replace(/!\[[^\]]*\]\(([^)\s]+)\)/g, function (_m, url) {
      url = String(url || '').trim();
      if (url && out.indexOf(url) === -1) out.push(url);
      return _m;
    });
    return out;
  }

  function normalizeTicketMediaList(list) {
    if (!Array.isArray(list)) return [];
    var out = [];
    list.forEach(function (item) {
      var url = resolveTicketMediaUrl(item);
      if (url && out.indexOf(url) === -1) out.push(url);
    });
    return out;
  }

  function normalizeTicketMediaEntries(list) {
    if (!Array.isArray(list)) return [];
    var out = [];
    var seen = {};
    list.forEach(function (item) {
      var cands = mediaUrlCandidates(item);
      if (!cands.length) return;
      var key = cands[0];
      if (seen[key]) return;
      seen[key] = 1;
      out.push({ url: cands[0], candidates: cands });
    });
    return out;
  }

  function normalizeHref(url) {
    var href = String(url || '').trim();
    if (!href) return '';
    if (!/^(https?:|mailto:|tel:)/i.test(href)) href = 'https://' + href.replace(/^\/\//, '');
    return href;
  }

  function formatInline(text) {
    var esc = escapeHtml(text || '');
    esc = esc.replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>');
    esc = esc.replace(/`([^`]+)`/g, '<code>$1</code>');
    esc = esc.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    return esc;
  }

  function flushParagraphs(lines) {
    var html = '';
    var inList = false;
    var para = [];

    function flushPara() {
      if (para.length) {
        html += '<p>' + para.map(formatInline).join('<br>') + '</p>';
        para = [];
      }
    }

    lines.forEach(function (rawLine) {
      var line = String(rawLine || '').trim();
      if (!line) {
        flushPara();
        if (inList) {
          html += '</ul>';
          inList = false;
        }
        return;
      }
      if (/^[-*•]\s+/.test(line)) {
        flushPara();
        if (!inList) {
          html += '<ul>';
          inList = true;
        }
        html += '<li>' + formatInline(line.replace(/^[-*•]\s+/, '')) + '</li>';
      } else {
        if (inList) {
          html += '</ul>';
          inList = false;
        }
        para.push(line);
      }
    });
    flushPara();
    if (inList) html += '</ul>';
    return html || '<p class="tk-muted">Sin contenido</p>';
  }

  function parseRfItems(body) {
    var items = [];
    String(body || '').split('\n').forEach(function (raw) {
      var line = raw.trim();
      if (!line) return;
      var mBold = line.match(/^[-*•]\s+\*\*([^*]+)\*\*\s*:\s*(.*)$/);
      if (mBold) {
        var head = mBold[1].trim();
        var desc = (mBold[2] || '').trim();
        var split = head.split(/\s*[—\-]\s*/);
        if (split.length >= 2) {
          items.push({ id: split[0].trim(), titulo: split.slice(1).join(' — ').trim(), descripcion: desc });
        } else {
          items.push({ id: head, titulo: '', descripcion: desc });
        }
        return;
      }
      var mPlain = line.match(/^[-*•]\s+(.+)$/);
      if (mPlain) items.push({ id: '', titulo: '', descripcion: mPlain[1] });
    });
    return items;
  }

  function countListItems(body) {
    var n = 0;
    String(body || '').split('\n').forEach(function (raw) {
      if (String(raw || '').trim()) n += 1;
    });
    return n;
  }

  function rfStorageKey(ticketId) {
    return 'tk-rf-checks-' + String(ticketId || 0);
  }

  function loadRfChecks(ticketId) {
    try {
      return JSON.parse(global.localStorage.getItem(rfStorageKey(ticketId)) || '{}') || {};
    } catch (e) {
      return {};
    }
  }

  function saveRfChecks(ticketId, map) {
    try {
      global.localStorage.setItem(rfStorageKey(ticketId), JSON.stringify(map || {}));
    } catch (e) { /* ignore */ }
  }

  function renderRf(body, ticketId) {
    var items = parseRfItems(body);
    if (!items.length) return flushParagraphs(String(body || '').split('\n'));
    var checks = loadRfChecks(ticketId);
    var done = 0;
    var html = '<div class="tk-rf-list" data-tk-rf-list="1">';
    items.forEach(function (rf, i) {
      var key = String(i);
      var checked = !!checks[key];
      if (checked) done += 1;
      html +=
        '<label class="tk-rf tk-rf--check' + (checked ? ' is-done' : '') + '">' +
          '<input type="checkbox" class="tk-rf-check" data-rf-idx="' + key + '"' + (checked ? ' checked' : '') + '>' +
          '<span class="tk-rf-num" aria-hidden="true">' + (checked ? '✓' : (i + 1)) + '</span>' +
          '<div class="tk-rf-content">' +
            '<div class="tk-rf-label">' + escapeHtml(rf.id || ('RF-' + String(i + 1).padStart(2, '0'))) + '</div>' +
            (rf.titulo ? '<strong>' + escapeHtml(rf.titulo) + '</strong>' : '') +
            (rf.descripcion ? '<p>' + formatInline(rf.descripcion) + '</p>' : '') +
          '</div>' +
        '</label>';
    });
    html += '</div>';
    html =
      '<div class="tk-rf-progress" data-tk-rf-progress="1">' +
        '<span data-tk-rf-count>' + done + '/' + items.length + '</span> completadas' +
      '</div>' +
      html;
    return html;
  }

  function renderList(body, kind) {
    var items = [];
    String(body || '').split('\n').forEach(function (raw) {
      var line = raw.trim();
      if (!line) return;
      items.push(/^[-*•]\s+/.test(line) ? line.replace(/^[-*•]\s+/, '') : line);
    });
    if (!items.length) return '';
    var html = '<ul class="tk-list' + (kind === 'warn' ? ' tk-list--warn' : '') + '">';
    items.forEach(function (it) {
      html += '<li><span class="tk-list-text">' + formatInline(it) + '</span></li>';
    });
    html += '</ul>';
    return html;
  }

  function norm(t) {
    return String(t || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .trim();
  }

  function detectKey(title) {
    var key = norm(title);
    if (key.indexOf('resumen') !== -1) return 'resumen';
    if (key.indexOf('detalle') !== -1 || key.indexOf('tecnico') !== -1) return 'detalle';
    if (key.indexOf('requerimiento') !== -1) return 'rf';
    if (key.indexOf('criterio') !== -1 || key.indexOf('aceptacion') !== -1) return 'ca';
    if (key.indexOf('duda') !== -1) return 'dudas';
    if (key.indexOf('prioridad') !== -1) return 'prio';
    if (key.indexOf('titulo') !== -1) return 'titulo';
    return 'plain';
  }

  function bodyForKey(key, body, ticketId) {
    if (key === 'rf') return renderRf(body, ticketId);
    if (key === 'ca') return renderList(body);
    if (key === 'dudas') return renderList(body, 'warn');
    if (key === 'prio') {
      var p = String(body || '').trim();
      var cls = 'tk-pill';
      var pn = norm(p);
      if (pn.indexOf('alta') !== -1) cls += ' tk-pill--alta';
      else if (pn.indexOf('baja') !== -1) cls += ' tk-pill--baja';
      else cls += ' tk-pill--media';
      return '<span class="' + cls + '">' + formatInline(p) + '</span>';
    }
    if (key === 'titulo') return '<p class="tk-title">' + formatInline(String(body || '').trim()) + '</p>';
    return flushParagraphs(String(body || '').split('\n'));
  }

  function sectionHtml(key, body, metaExtra, ticketId) {
    var meta = LABELS[key] || LABELS.plain;
    var step = meta.n
      ? '<span class="tk-step' + (key === 'dudas' ? ' tk-step--warn' : '') + '">' + escapeHtml(meta.n) + '</span>'
      : '<span class="tk-step tk-step--empty" aria-hidden="true"></span>';
    var sub = meta.sub ? '<span class="tk-sec-sub">' + escapeHtml(meta.sub) + '</span>' : '';
    var badge = metaExtra ? '<span class="tk-sec-badge" data-tk-sec-badge="' + key + '">' + escapeHtml(metaExtra) + '</span>' : '';
    return (
      '<section class="tk-sec tk-sec--' + meta.kind + '" id="tk-sec-' + key + '">' +
        '<header class="tk-sec-head">' +
          step +
          '<div class="tk-sec-head-text">' +
            '<h4>' + escapeHtml(meta.title) + badge + '</h4>' +
            sub +
          '</div>' +
        '</header>' +
        '<div class="tk-sec-body">' + bodyForKey(key, body, ticketId) + '</div>' +
      '</section>'
    );
  }

  function sectionHtmlEmpty(key) {
    var meta = LABELS[key] || LABELS.plain;
    var step = meta.n
      ? '<span class="tk-step' + (key === 'dudas' ? ' tk-step--warn' : '') + '">' + escapeHtml(meta.n) + '</span>'
      : '<span class="tk-step tk-step--empty" aria-hidden="true"></span>';
    var sub = meta.sub ? '<span class="tk-sec-sub">' + escapeHtml(meta.sub) + '</span>' : '';
    return (
      '<section class="tk-sec tk-sec--empty tk-sec--' + meta.kind + '" id="tk-sec-' + key + '">' +
        '<header class="tk-sec-head">' +
          step +
          '<div class="tk-sec-head-text">' +
            '<h4>' + escapeHtml(meta.title) + '<span class="tk-sec-badge">Sin definir</span></h4>' +
            sub +
          '</div>' +
        '</header>' +
        '<div class="tk-sec-body"><p class="tk-muted">Esta sección no está en el brief. Conviene completarla para el desarrollo.</p></div>' +
      '</section>'
    );
  }

  function renderNav(present) {
    var items = [
      { key: 'resumen', label: 'Contexto' },
      { key: 'detalle', label: 'Implementar' },
      { key: 'rf', label: 'Tareas' },
      { key: 'ca', label: 'Validar' },
      { key: 'dudas', label: 'Bloqueos' }
    ];
    var html = '<nav class="tk-nav" aria-label="Secciones del ticket">';
    items.forEach(function (it) {
      var on = !!present[it.key];
      html +=
        '<button type="button" class="tk-nav-item' + (on ? ' is-on' : ' is-off') + '" data-tk-nav="' + it.key + '">' +
          '<i class="tk-nav-dot"></i>' + escapeHtml(it.label) +
        '</button>';
    });
    html += '</nav>';
    return html;
  }

  function parseSections(text) {
    var map = {};
    if (!/^##\s+/m.test(text)) {
      map.plain = text;
      return map;
    }
    var parts = text.split(/^##\s+/m);
    var preamble = (parts[0] || '').trim();
    if (preamble) map.plain = preamble;
    for (var i = 1; i < parts.length; i++) {
      var block = parts[i] || '';
      var nl = block.indexOf('\n');
      var title = (nl === -1 ? block : block.slice(0, nl)).trim();
      var body = (nl === -1 ? '' : block.slice(nl + 1)).trim();
      if (!title && !body) continue;
      var key = detectKey(title);
      if (map[key] && key === 'plain') map[key] += '\n\n' + body;
      else if (!map[key]) map[key] = body;
      else map[key] += '\n' + body;
    }
    return map;
  }

  function rfExtraLabel(body, ticketId) {
    var items = parseRfItems(body);
    if (!items.length) {
      var c = countListItems(body);
      return c ? String(c) : '';
    }
    var checks = loadRfChecks(ticketId);
    var done = 0;
    items.forEach(function (_rf, i) {
      if (checks[String(i)]) done += 1;
    });
    return done + '/' + items.length;
  }

  function renderTicketDescDocument(raw, ticketId) {
    var text = decodeVisibleEscapes(raw || '').trim().replace(/\n{3,}/g, '\n\n');
    if (!text) {
      return '<div class="tk-doc"><p class="tk-muted tk-empty">Sin descripción en este ticket</p></div>';
    }

    var map = parseSections(text);
    var isStructured = ORDER_CORE.some(function (k) { return !!map[k]; });
    var html = '<div class="tk-doc">';
    html += renderNav(map);

    if (map.titulo) html += sectionHtml('titulo', map.titulo, '', ticketId);

    ORDER_CORE.forEach(function (key) {
      if (map[key]) {
        var extra = '';
        if (key === 'rf') extra = rfExtraLabel(map[key], ticketId);
        else if (key === 'ca' || key === 'dudas') {
          var c = countListItems(map[key]);
          if (c) extra = String(c);
        }
        html += sectionHtml(key, map[key], extra, ticketId);
      } else if (isStructured) {
        html += sectionHtmlEmpty(key);
      }
    });

    if (map.prio) html += sectionHtml('prio', map.prio, '', ticketId);
    if (map.plain) html += sectionHtml('plain', map.plain, '', ticketId);

    html += '</div>';
    return html;
  }

  function prioClass(prio) {
    var pn = norm(prio);
    if (pn.indexOf('alta') !== -1) return 'tk-pill--alta';
    if (pn.indexOf('baja') !== -1) return 'tk-pill--baja';
    return 'tk-pill--media';
  }

  function formatFechaLim(raw) {
    var s = String(raw || '').trim();
    if (!s || s.indexOf('0000') === 0) return '';
    var m = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (m) return m[3] + '/' + m[2] + '/' + m[1];
    return s;
  }

  /** Cabecera operativa sticky + meta de proyecto. */
  function renderTicketMeta(meta) {
    meta = meta || {};
    var id = meta.id ? String(meta.id) : '';
    var titulo = String(meta.titulo || '').trim();
    var cliente = String(meta.cliente || '').trim();
    var proyecto = String(meta.proyecto || '').trim();
    var url = String(meta.url || '').trim();
    var prioridad = String(meta.prioridad || '').trim() || 'Media';
    var estado = String(meta.estado || '').trim() || '—';
    var fecha = formatFechaLim(meta.fecha_lim);
    var href = normalizeHref(url);

    function valOrDash(v) {
      return v
        ? '<span class="tk-doc-meta-value">' + escapeHtml(v) + '</span>'
        : '<span class="tk-doc-meta-value tk-muted">—</span>';
    }

    var sticky =
      '<div class="tk-doc-sticky">' +
        '<div class="tk-doc-sticky-main">' +
          (id ? '<span class="tk-doc-id">#' + escapeHtml(id) + '</span>' : '') +
          '<span class="tk-doc-sticky-title">' + escapeHtml(titulo || 'Brief del ticket') + '</span>' +
        '</div>' +
        '<div class="tk-doc-sticky-side">' +
          '<span class="tk-pill ' + prioClass(prioridad) + '">' + escapeHtml(prioridad) + '</span>' +
          (href
            ? '<a class="tk-btn-open-url" href="' + escapeHtml(href) + '" target="_blank" rel="noopener noreferrer">' +
                '<i class="fas fa-external-link-alt" aria-hidden="true"></i> Abrir URL' +
              '</a>'
            : '<span class="tk-btn-open-url is-disabled" title="Sin URL de proyecto">Sin URL</span>') +
        '</div>' +
      '</div>';

    var chips =
      '<div class="tk-doc-chips">' +
        '<span class="tk-chip"><b>Estado</b> ' + escapeHtml(estado) + '</span>' +
        '<span class="tk-chip"><b>Límite</b> ' + escapeHtml(fecha || '—') + '</span>' +
      '</div>';

    var urlHtml = href
      ? '<a class="tk-doc-meta-value tk-doc-meta-link" href="' + escapeHtml(href) + '" target="_blank" rel="noopener noreferrer">' +
          escapeHtml(url) +
        '</a>'
      : '<span class="tk-doc-meta-value tk-muted">—</span>';

    var grid =
      '<div class="tk-doc-meta" aria-label="Datos del proyecto">' +
        '<div class="tk-doc-meta-item"><span class="tk-doc-meta-label">Cliente</span>' + valOrDash(cliente) + '</div>' +
        '<div class="tk-doc-meta-item"><span class="tk-doc-meta-label">Proyecto</span>' + valOrDash(proyecto) + '</div>' +
        '<div class="tk-doc-meta-item"><span class="tk-doc-meta-label">URL</span>' + urlHtml + '</div>' +
      '</div>';

    return sticky + chips + grid;
  }

  function renderAttachmentsTop(images, files, onImageClick) {
    // Slot vacío: las imágenes se montan por DOM (evita romper src con espacios en el nombre).
    var html = '';
    if ((images && images.length) || (files && files.length)) {
      html += '<div class="tk-doc-attach-top" id="tkDescAttachTop"></div>';
    }
    return html;
  }

  function mountAttachmentsTop(root, images, files, onImageClick) {
    var slot = root ? root.querySelector('#tkDescAttachTop') : null;
    if (!slot) return;
    slot.innerHTML = '';

    if (images && images.length) {
      var label = document.createElement('div');
      label.className = 'tk-doc-attachments-label';
      label.textContent = 'Referencias visuales';
      slot.appendChild(label);

      var gallery = document.createElement('div');
      gallery.className = 'content-images tk-doc-gallery';
      images.forEach(function (url, index) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'tk-gallery-btn';
        btn.title = 'Ampliar imagen ' + (index + 1);
        btn.setAttribute('data-tk-img', String(index));

        var el = document.createElement('img');
        el.src = url;
        el.alt = 'Adjunto ' + (index + 1);
        el.className = 'content-image-thumb';
        el.loading = 'eager';
        el.decoding = 'async';
        el.onerror = function () {
          el.classList.add('is-broken');
          el.alt = 'No se pudo cargar';
        };
        btn.appendChild(el);
        btn.addEventListener('click', function () {
          if (typeof onImageClick === 'function') onImageClick(images, index);
        });
        gallery.appendChild(btn);
      });
      slot.appendChild(gallery);
    }

    if (files && files.length) {
      var labF = document.createElement('div');
      labF.className = 'tk-doc-attachments-label';
      labF.textContent = 'Archivos';
      slot.appendChild(labF);

      var list = document.createElement('div');
      list.className = 'content-files';
      files.forEach(function (url) {
        var d = document.createElement('div');
        d.className = 'content-file-item';
        var ic = document.createElement('i');
        ic.className = 'fas fa-paperclip';
        ic.setAttribute('aria-hidden', 'true');
        var a = document.createElement('a');
        a.href = url;
        a.target = '_blank';
        a.rel = 'noopener noreferrer';
        a.textContent = (url.split('/').pop() || url.split('\\').pop() || 'archivo');
        try {
          a.textContent = decodeURIComponent(a.textContent);
        } catch (e) { /* keep encoded */ }
        d.appendChild(ic);
        d.appendChild(a);
        list.appendChild(d);
      });
      slot.appendChild(list);
    }
  }

  function updateFooterActions(meta) {
    meta = meta || {};
    var btnUrl = document.getElementById('btnCopyUrl');
    var btnNotas = document.getElementById('btnOpenNotasDesc');
    var btnProc = document.getElementById('btnMarcarProceso');
    var hasUrl = !!String(meta.url || '').trim();
    var id = parseInt(meta.id || 0, 10);
    var estado = String(meta.estado || '');

    if (btnUrl) {
      btnUrl.disabled = !hasUrl;
      btnUrl.title = hasUrl ? 'Copiar URL del proyecto' : 'Sin URL de proyecto';
    }
    if (btnNotas) {
      btnNotas.disabled = !(id > 0);
    }
    if (btnProc) {
      var already = estado === 'En Proceso' || estado === 'Finalizado';
      btnProc.disabled = !(id > 0) || already;
      btnProc.textContent = '';
      var icon = document.createElement('i');
      icon.className = 'fas fa-play';
      icon.setAttribute('aria-hidden', 'true');
      btnProc.appendChild(icon);
      btnProc.appendChild(document.createTextNode(already ? (estado === 'Finalizado' ? ' Finalizado' : ' Ya en proceso') : ' En Proceso'));
    }
  }

  function toast(msg, icon) {
    if (global.Swal && Swal.fire) {
      Swal.fire({
        toast: true,
        position: 'top-end',
        icon: icon || 'success',
        title: msg,
        timer: 1800,
        showConfirmButton: false
      });
      return;
    }
    try { console.log(msg); } catch (e) { /* ignore */ }
  }

  function copyText(text) {
    text = String(text || '');
    if (!text) return Promise.reject(new Error('Vacío'));
    if (global.navigator && navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(text);
    }
    return new Promise(function (resolve, reject) {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.setAttribute('readonly', '');
      ta.style.position = 'fixed';
      ta.style.left = '-9999px';
      document.body.appendChild(ta);
      ta.select();
      try {
        var ok = document.execCommand('copy');
        document.body.removeChild(ta);
        if (ok) resolve();
        else reject(new Error('No se pudo copiar'));
      } catch (err) {
        document.body.removeChild(ta);
        reject(err);
      }
    });
  }

  function getTicketBriefText() {
    var m = modalState.meta || {};
    var lines = [];
    if (m.id) lines.push('Ticket #' + m.id);
    if (m.titulo) lines.push(m.titulo);
    if (m.cliente) lines.push('Cliente: ' + m.cliente);
    if (m.proyecto) lines.push('Proyecto: ' + m.proyecto);
    if (m.url) lines.push('URL: ' + m.url);
    if (m.prioridad) lines.push('Prioridad: ' + m.prioridad);
    if (m.estado) lines.push('Estado: ' + m.estado);
    if (m.fecha_lim) lines.push('Límite: ' + formatFechaLim(m.fecha_lim));
    lines.push('');
    lines.push(decodeVisibleEscapes(modalState.desc || '').trim());
    return lines.join('\n').trim();
  }

  function originalBriefBody() {
    return decodeVisibleEscapes(modalState.desc || '').trim();
  }

  function buildAiPromptText() {
    var m = modalState.meta || {};
    var brief = originalBriefBody();
    var lines = [];

    lines.push('ROL');
    lines.push('Eres un desarrollador senior. Debes implementar exactamente este ticket, sin ampliar ni reducir el alcance.');
    lines.push('');
    lines.push('CONTEXTO DEL TICKET');
    if (m.id) lines.push('Ticket: #' + m.id);
    if (m.titulo) lines.push('Título: ' + m.titulo);
    if (m.cliente) lines.push('Cliente: ' + m.cliente);
    if (m.proyecto) lines.push('Proyecto: ' + m.proyecto);
    if (m.url) lines.push('URL: ' + m.url);
    if (m.prioridad) lines.push('Prioridad: ' + m.prioridad);
    if (m.estado) lines.push('Estado: ' + m.estado);
    if (m.fecha_lim) lines.push('Fecha límite: ' + formatFechaLim(m.fecha_lim));
    lines.push('');
    lines.push('INSTRUCCIONES');
    lines.push('- Conserva la estructura original del brief (secciones ##).');
    lines.push('- Implementa solo lo descrito en Resumen, Detalle técnico, Requerimientos funcionales y Criterios de aceptación.');
    lines.push('- No inventes requerimientos, pantallas, textos ni archivos que no estén en el brief.');
    lines.push('- Si hay Dudas abiertas, no asumas: indícalas y espera criterio o documenta la decisión mínima.');
    lines.push('- Entrega el trabajo listo para revisión contra los criterios de aceptación.');
    lines.push('');
    lines.push('===== BRIEF DEL TICKET (ESTRUCTURA ORIGINAL — NO ALTERAR) =====');
    lines.push('');
    lines.push(brief || '(Sin descripción)');
    lines.push('');
    lines.push('===== FIN DEL BRIEF =====');

    var imgs = Array.isArray(modalState.images) ? modalState.images : [];
    var files = Array.isArray(modalState.files) ? modalState.files : [];
    if (imgs.length || files.length) {
      lines.push('');
      lines.push('REFERENCIAS ADJUNTAS');
      if (imgs.length) {
        lines.push('Imágenes (' + imgs.length + '):');
        imgs.forEach(function (u, i) {
          lines.push((i + 1) + '. ' + u);
        });
      }
      if (files.length) {
        lines.push('Archivos (' + files.length + '):');
        files.forEach(function (u, i) {
          lines.push((i + 1) + '. ' + u);
        });
      }
    }

    lines.push('');
    lines.push('FORMATO DE RESPUESTA');
    lines.push('1) Qué vas a cambiar (según RF).');
    lines.push('2) Cómo lo implementarás.');
    lines.push('3) Cómo se valida (criterios de aceptación).');
    lines.push('4) Bloqueos o dudas que impidan avanzar.');

    return lines.join('\n').trim() + '\n';
  }

  function downloadTextFile(filename, text) {
    var blob = new Blob(['\uFEFF' + String(text || '')], { type: 'text/plain;charset=utf-8' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = filename || 'prompt.txt';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(function () { URL.revokeObjectURL(url); }, 800);
  }

  function ensurePromptPanel() {
    var body = document.querySelector('#modalVerDesc .modal-body');
    if (!body) return null;
    var panel = document.getElementById('tkPromptPanel');
    if (panel) return panel;
    panel = document.createElement('div');
    panel.id = 'tkPromptPanel';
    panel.className = 'tk-prompt-panel';
    panel.hidden = true;
    panel.innerHTML =
      '<div class="tk-prompt-head">' +
        '<div class="tk-prompt-head-copy">' +
          '<strong>Prompt para IA</strong>' +
          '<span>Misma estructura del brief, modelada para implementación.</span>' +
        '</div>' +
        '<div class="tk-prompt-head-actions">' +
          '<button type="button" class="btn tk-btn-ghost" id="btnCopyPromptTxt"><i class="fas fa-copy" aria-hidden="true"></i> Copiar</button>' +
          '<button type="button" class="btn tk-btn-ghost" id="btnDownloadPrompt"><i class="fas fa-file-download" aria-hidden="true"></i> Descargar prompt</button>' +
        '</div>' +
      '</div>' +
      '<textarea id="tkPromptText" class="tk-prompt-text" readonly rows="14" spellcheck="false"></textarea>';
    body.appendChild(panel);

    var btnCopy = panel.querySelector('#btnCopyPromptTxt');
    var btnDl = panel.querySelector('#btnDownloadPrompt');
    if (btnCopy) {
      btnCopy.addEventListener('click', function () {
        copyText(buildAiPromptText())
          .then(function () { toast('Prompt copiado'); })
          .catch(function () { toast('No se pudo copiar', 'error'); });
      });
    }
    if (btnDl) {
      btnDl.addEventListener('click', function () {
        downloadPromptFile();
      });
    }
    return panel;
  }

  function promptFilename() {
    var id = modalState.id || 0;
    var slug = String((modalState.meta && modalState.meta.titulo) || 'ticket')
      .toLowerCase()
      .replace(/[^a-z0-9]+/gi, '_')
      .replace(/^_+|_+$/g, '')
      .slice(0, 40);
    return 'prompt_ticket_' + (id || 'na') + (slug ? '_' + slug : '') + '.txt';
  }

  function downloadPromptFile() {
    downloadTextFile(promptFilename(), buildAiPromptText());
    toast('Prompt descargado');
  }

  function showPromptInModal() {
    var prompt = buildAiPromptText();
    var panel = ensurePromptPanel();
    var ta = document.getElementById('tkPromptText');
    if (ta) ta.value = prompt;
    if (panel) panel.hidden = false;
  }

  function bindDocInteractions(root, ticketId, images, onImageClick) {
    if (!root) return;
    var scrollParent = root.closest ? root.closest('.modal-body') : null;

    root.querySelectorAll('[data-tk-nav]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var key = btn.getAttribute('data-tk-nav');
        var target = document.getElementById('tk-sec-' + key);
        if (!target) return;
        if (scrollParent) {
          var targetRect = target.getBoundingClientRect();
          var parentRect = scrollParent.getBoundingClientRect();
          var top = scrollParent.scrollTop + (targetRect.top - parentRect.top) - 10;
          scrollParent.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
        } else {
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
    });

    root.querySelectorAll('.tk-rf-check').forEach(function (input) {
      input.addEventListener('change', function () {
        var map = loadRfChecks(ticketId);
        var idx = input.getAttribute('data-rf-idx');
        if (input.checked) map[idx] = 1;
        else delete map[idx];
        saveRfChecks(ticketId, map);

        var label = input.closest('.tk-rf');
        if (label) {
          label.classList.toggle('is-done', input.checked);
          var num = label.querySelector('.tk-rf-num');
          if (num) num.textContent = input.checked ? '✓' : String(parseInt(idx, 10) + 1);
        }

        var list = root.querySelector('[data-tk-rf-list]');
        var total = list ? list.querySelectorAll('.tk-rf-check').length : 0;
        var done = list ? list.querySelectorAll('.tk-rf-check:checked').length : 0;
        var countEl = root.querySelector('[data-tk-rf-count]');
        if (countEl) countEl.textContent = done + '/' + total;
        var badge = root.querySelector('[data-tk-sec-badge="rf"]');
        if (badge) badge.textContent = done + '/' + total;
      });
    });
  }

  function updateModalChrome(meta) {
    meta = meta || {};
    var titleEl = document.querySelector('#modalVerDesc .modal-title');
    var subEl = document.querySelector('#modalVerDesc .tk-doc-modal-sub');
    if (titleEl) {
      titleEl.textContent = meta.id ? ('Ticket #' + meta.id) : 'Brief del ticket';
    }
    if (subEl) {
      subEl.textContent = meta.titulo || 'Orden recomendado para desarrollo';
    }
  }

  function fillTicketDescModal(opts) {
    opts = opts || {};
    var metaEl = document.getElementById('tkDescMeta');
    var descEl = document.getElementById('contenidoDescripcion');
    var imgEl = document.getElementById('contenidoImagenes');
    var fileEl = document.getElementById('contenidoArchivos');
    var labImg = document.getElementById('labelImagenesDesc');
    var labFil = document.getElementById('labelArchivosDesc');
    var desc = opts.desc || '';

    // Combinar adjuntos JSON + imágenes embebidas en markdown del texto
    var rawImages = Array.isArray(opts.images) ? opts.images.slice() : [];
    extractMarkdownImages(desc).forEach(function (u) {
      if (rawImages.indexOf(u) === -1) rawImages.push(u);
    });

    var imageEntries = normalizeTicketMediaEntries(rawImages);
    var fileEntries = normalizeTicketMediaEntries(opts.files || []);
    var images = imageEntries.map(function (e) { return e.url; });
    var files = fileEntries.map(function (e) { return e.url; });
    var meta = opts.meta || {};

    modalState = {
      id: parseInt(meta.id || 0, 10) || 0,
      titulo: meta.titulo || '',
      desc: desc,
      meta: meta,
      images: images,
      files: files
    };

    updateModalChrome(meta);
    updateFooterActions(meta);

    // Imágenes PRIMERO: si el brief falla, las referencias igual se muestran
    if (imgEl) {
      imgEl.innerHTML = '';
      imgEl.hidden = false;
      imgEl.removeAttribute('hidden');
      if (imageEntries.length) {
        if (labImg) {
          labImg.hidden = false;
          labImg.removeAttribute('hidden');
        }
        imageEntries.forEach(function (entry, index) {
          var wrap = document.createElement('button');
          wrap.type = 'button';
          wrap.className = 'tk-gallery-btn';
          wrap.title = 'Ampliar imagen ' + (index + 1);

          var el = document.createElement('img');
          el.className = 'content-image-thumb';
          el.alt = 'Adjunto ' + (index + 1);
          el.loading = 'eager';
          el.decoding = 'async';

          var tryIdx = 0;
          var cands = entry.candidates || [entry.url];
          el.src = cands[0];
          el.onerror = function () {
            tryIdx += 1;
            if (tryIdx < cands.length) {
              el.src = cands[tryIdx];
              return;
            }
            el.classList.add('is-broken');
            el.alt = 'No se pudo cargar';
            wrap.title = 'No se pudo cargar: ' + (cands[0] || '');
          };
          wrap.appendChild(el);
          wrap.addEventListener('click', function () {
            if (el.classList.contains('is-broken')) {
              try { global.open(cands[0], '_blank'); } catch (e) { /* ignore */ }
              return;
            }
            if (typeof opts.onImageClick === 'function') {
              opts.onImageClick(images, index);
            }
          });
          imgEl.appendChild(wrap);
        });
      } else if (labImg) {
        labImg.hidden = true;
      }
    }

    if (fileEl) {
      fileEl.innerHTML = '';
      if (fileEntries.length) {
        if (labFil) {
          labFil.hidden = false;
          labFil.removeAttribute('hidden');
        }
        fileEntries.forEach(function (entry) {
          var url = entry.url;
          var d = document.createElement('div');
          d.className = 'content-file-item';
          var ic = document.createElement('i');
          ic.className = 'fas fa-paperclip';
          ic.setAttribute('aria-hidden', 'true');
          var a = document.createElement('a');
          a.href = url;
          a.target = '_blank';
          a.rel = 'noopener noreferrer';
          var name = url.split('/').pop() || url.split('\\').pop() || 'archivo';
          try { name = decodeURIComponent(name); } catch (e) { /* keep */ }
          a.textContent = name;
          d.appendChild(ic);
          d.appendChild(a);
          fileEl.appendChild(d);
        });
      } else if (labFil) {
        labFil.hidden = true;
      }
    }

    if (metaEl) {
      metaEl.innerHTML = renderTicketMeta(meta);
    }

    try {
      if (descEl) {
        descEl.innerHTML = renderTicketDescDocument(desc, modalState.id);
        bindDocInteractions(descEl, modalState.id, images, opts.onImageClick);
      } else if (metaEl) {
        metaEl.insertAdjacentHTML('afterend', renderTicketDescDocument(desc, modalState.id));
      }
    } catch (err) {
      try { console.error('ticket brief render', err); } catch (e) { /* ignore */ }
      if (descEl) {
        descEl.innerHTML = '<div class="tk-doc"><p class="tk-muted">No se pudo renderizar el brief.</p><pre style="white-space:pre-wrap;font-size:12px">' +
          escapeHtml(desc || '') + '</pre></div>';
      }
    }

    showPromptInModal();

    return { images: images, files: files, meta: meta };
  }

  function bindTicketDescModalChrome() {
    if (bindTicketDescModalChrome._done) return;
    bindTicketDescModalChrome._done = true;

    var btnBrief = document.getElementById('btnCopyBrief');
    var btnUrl = document.getElementById('btnCopyUrl');
    var btnNotas = document.getElementById('btnOpenNotasDesc');
    var btnProc = document.getElementById('btnMarcarProceso');

    if (btnBrief) {
      btnBrief.addEventListener('click', function () {
        copyText(getTicketBriefText())
          .then(function () { toast('Brief copiado'); })
          .catch(function () { toast('No se pudo copiar', 'error'); });
      });
    }

    if (btnUrl) {
      btnUrl.addEventListener('click', function () {
        var url = String((modalState.meta && modalState.meta.url) || '').trim();
        if (!url) return;
        copyText(normalizeHref(url) || url)
          .then(function () { toast('URL copiada'); })
          .catch(function () { toast('No se pudo copiar', 'error'); });
      });
    }

    if (btnNotas) {
      btnNotas.addEventListener('click', function () {
        var id = modalState.id;
        if (!(id > 0)) return;
        var modal = document.getElementById('modalVerDesc');
        if (modal) modal.classList.remove('active');
        if (typeof global.openNotasModal === 'function') {
          global.openNotasModal(id);
        }
      });
    }

    if (btnProc) {
      btnProc.addEventListener('click', async function () {
        var id = modalState.id;
        if (!(id > 0) || btnProc.disabled) return;
        btnProc.disabled = true;
        try {
          var fd = new FormData();
          fd.append('id', String(id));
          fd.append('estado', 'En Proceso');
          fd.append('ajax', '1');
          var r = await fetch('actualizar.php', { method: 'POST', body: fd, credentials: 'same-origin' });
          var d = await r.json();
          if (!d || d.ok === false) throw new Error((d && d.error) || 'Error');
          modalState.meta.estado = 'En Proceso';
          updateFooterActions(modalState.meta);
          var chips = document.querySelector('#contenidoDescripcion .tk-doc-chips');
          if (chips) {
            chips.innerHTML =
              '<span class="tk-chip"><b>Estado</b> En Proceso</span>' +
              '<span class="tk-chip"><b>Límite</b> ' +
              escapeHtml(formatFechaLim(modalState.meta.fecha_lim) || '—') +
              '</span>';
          }
          // Sync row select if present
          document.querySelectorAll('select.estado-select').forEach(function (sel) {
            var form = sel.closest('form');
            var hid = form && form.querySelector('input[name="id"]');
            if (hid && String(hid.value) === String(id)) {
              sel.value = 'En Proceso';
              if (typeof global.applyEstadoSelectClass === 'function') {
                global.applyEstadoSelectClass(sel, 'En Proceso');
              } else {
                sel.classList.remove('estado-pendiente', 'estado-proceso', 'estado-finalizado');
                sel.classList.add('estado-proceso');
              }
            }
          });
          toast('Marcado En Proceso');
        } catch (err) {
          console.error(err);
          btnProc.disabled = false;
          toast('No se pudo actualizar', 'error');
        }
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindTicketDescModalChrome);
  } else {
    bindTicketDescModalChrome();
  }

  global.renderTicketDescDocument = renderTicketDescDocument;
  global.renderTicketMeta = renderTicketMeta;
  global.fillTicketDescModal = fillTicketDescModal;
  global.bindTicketDescModalChrome = bindTicketDescModalChrome;
  global.resolveTicketMediaUrl = resolveTicketMediaUrl;
  global.normalizeTicketMediaList = normalizeTicketMediaList;
  global.decodeVisibleEscapes = global.decodeVisibleEscapes || decodeVisibleEscapes;
  global.getTicketBriefText = getTicketBriefText;
  global.buildAiPromptText = buildAiPromptText;
})(window);
