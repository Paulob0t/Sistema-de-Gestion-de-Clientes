<?php
require_once dirname(__DIR__) . '/auth_middleware.php';
require_once dirname(__DIR__) . '/conn.php';
require_once dirname(__DIR__) . '/includes/cw_hub_config.php';
require_once dirname(__DIR__) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__) . '/includes/cw_hub_analytics.php';
require_once dirname(__DIR__) . '/includes/cw_hub_permissions.php';

cw_hub_migrate($conn);
cw_hub_require('hub.analytics.view');

require_once dirname(__DIR__) . '/includes/adm_paths.php';

$period = $_GET['period'] ?? '30d';
$from = $_GET['from'] ?? null;
$to = $_GET['to'] ?? null;
$websiteWebFilter = cw_analytics_normalize_web($_GET['web'] ?? 'all');
cw_analytics_web_filter($websiteWebFilter);
[$dateFrom, $dateTo] = cw_hub_period_dates($period, $from, $to);

$activeTab = 'sessions';

include dirname(__DIR__) . '/menu.php';
?>
<div class="adm-page-shell">
<div class="container-fluid px-0">
<?php include __DIR__ . '/includes/visits_shell.php'; ?>

<p class="text-muted small mb-3" id="sessionsPeriodMeta">
  Periodo: <strong id="sessionsPeriodLabel"><?= htmlspecialchars($period) ?></strong>
  · <span id="sessionsPeriodRange"><?= htmlspecialchars(substr($dateFrom, 0, 16) . ' → ' . substr($dateTo, 0, 16)) ?></span>
  · Total en periodo: <strong id="sessionsPeriodTotal">—</strong>
</p>

<section class="va-kpi-grid va-kpi-grid-4" aria-label="Resumen de sesiones">
  <article class="va-kpi">
    <div class="va-kpi-head"><span class="title">Sesiones (periodo)</span><span class="icon"><i class="bi bi-diagram-3"></i></span></div>
    <div class="num" id="kpiSessionsHuman">—</div>
    <div class="va-kpi-sub">Humanas sin contacto</div>
  </article>
  <article class="va-kpi">
    <div class="va-kpi-head"><span class="title">Con contacto</span><span class="icon"><i class="bi bi-person-check"></i></span></div>
    <div class="num" id="kpiSessionsContact">—</div>
    <div class="va-kpi-sub">En el periodo completo</div>
  </article>
  <article class="va-kpi va-kpi-crawler">
    <div class="va-kpi-head"><span class="title">Crawler / IA</span><span class="icon"><i class="bi bi-robot"></i></span></div>
    <div class="num" id="kpiSessionsCrawler">—</div>
    <div class="va-kpi-sub">En el periodo completo</div>
  </article>
  <article class="va-kpi va-kpi-spam">
    <div class="va-kpi-head"><span class="title">Posible spam</span><span class="icon"><i class="bi bi-exclamation-triangle"></i></span></div>
    <div class="num" id="kpiSessionsSpam">—</div>
    <div class="va-kpi-sub">En el periodo completo</div>
  </article>
</section>

<article class="va-panel">
  <div class="va-panel-head">
    <h2>Sesiones de visitantes</h2>
    <p class="text-muted small mb-0">Visitas humanas, contactos reales, crawlers de IA/SEO y envíos sospechosos al formulario. Hora CDMX.</p>
  </div>
  <div class="va-page-users-tabs" id="sessionsTabs">
    <button type="button" class="va-page-users-tab is-active" data-sessions-tab="sessions">
      Sesiones <span class="va-tab-count" id="sessionsTabCountSessions">0</span>
    </button>
    <button type="button" class="va-page-users-tab" data-sessions-tab="contact">
      Con contacto <span class="va-tab-count" id="sessionsTabCountContact">0</span>
    </button>
    <button type="button" class="va-page-users-tab" data-sessions-tab="crawler_ia" title="Googlebot, ClaudeBot, NotebookLM y visitas automáticas sin contacto">
      <i class="bi bi-robot" aria-hidden="true"></i>
      Crawler / IA <span class="va-tab-count" id="sessionsTabCountCrawler">0</span>
    </button>
    <button type="button" class="va-page-users-tab" data-sessions-tab="possible_spam" title="Formularios con nombre, correo o patrón sospechoso">
      <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
      Posible spam <span class="va-tab-count" id="sessionsTabCountSpam">0</span>
    </button>
    <div class="va-sessions-limit-wrap">
      <label for="sessionsLimit" class="mb-0">Mostrar</label>
      <select id="sessionsLimit" class="va-select va-select-sm" aria-label="Cantidad de sesiones a cargar">
        <option value="100" selected>100</option>
        <option value="500">500</option>
        <option value="2000">2,000</option>
        <option value="3000">3,000</option>
        <option value="8000">8,000</option>
        <option value="0">Todos</option>
      </select>
    </div>
  </div>
  <div class="va-sessions-alerts">
    <div id="sessionsTabHint" class="va-sessions-tab-hint" hidden>
      <i class="bi bi-info-circle" aria-hidden="true"></i>
      <span id="sessionsTabHintText"></span>
    </div>
    <div id="sessionsError" class="va-sessions-error" hidden></div>
    <div id="sessionsLimitNotice" class="va-sessions-limit-notice" hidden></div>
  </div>
  <div id="sessionsLoading" class="va-page-users-loading" hidden>
    <i class="bi bi-arrow-repeat spin"></i> Cargando sesiones…
  </div>
  <div class="modern-table va-sessions-dt">
    <table class="table va-table va-sessions-table" id="sessionsTable" style="width:100%">
      <thead>
        <tr>
          <th>ID sesión</th>
          <th>Nombre</th>
          <th>Teléfono</th>
          <th>Correo</th>
          <th>Ubicación</th>
          <th>Dispositivo</th>
          <th class="va-col-ua">User-Agent</th>
          <th>Referrer</th>
          <th>Página de entrada</th>
          <th>Inicio (CDMX)</th>
          <th>Duración</th>
          <th>URLs</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="sessionsTableBody"></tbody>
    </table>
  </div>
</article>

<div class="va-top-modal" id="sessionTraceModal" hidden aria-hidden="true">
  <div class="va-top-modal-backdrop" data-close-trace-modal></div>
  <div class="va-top-modal-dialog va-session-trace-dialog" role="dialog" aria-modal="true" aria-labelledby="sessionTraceModalTitle">
    <div class="va-top-detail-head">
      <div>
        <h3 id="sessionTraceModalTitle">Recorrido del visitante</h3>
        <p class="va-top-modal-sub" id="sessionTraceModalSub">Trazado completo de la sesión.</p>
      </div>
      <button type="button" class="va-top-detail-close" data-close-trace-modal aria-label="Cerrar">&times;</button>
    </div>
    <div class="va-session-trace-summary" id="sessionTraceSummary"></div>
    <div class="va-top-modal-body va-session-trace-body">
      <div class="va-page-users-loading" id="sessionTraceLoading" hidden>
        <i class="bi bi-arrow-repeat spin"></i> Cargando recorrido…
      </div>
      <div class="va-table-wrap">
        <table class="va-table va-session-trace-table" id="sessionTraceTable" style="width:100%">
          <thead>
            <tr>
              <th>#</th>
              <th>Tipo</th>
              <th>Página / Evento</th>
              <th>Hora (CDMX)</th>
              <th>Tiempo</th>
              <th>Región</th>
            </tr>
          </thead>
          <tbody id="sessionTraceTableBody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

</div><!-- .va-shell -->
</div><!-- .container-fluid px-0 -->
</div><!-- .adm-page-shell -->
</div><!-- menu container-fluid -->
</div><!-- menu content-wrapper -->

<link href="<?= adm_href('vendor/datatables/dataTables.bootstrap4.min.css') ?>" rel="stylesheet">
<script src="<?= adm_href('analytics/js/datatables-es.js') ?>"></script>
<script src="<?= adm_href('vendor/datatables/jquery.dataTables.min.js') ?>"></script>
<script src="<?= adm_href('vendor/datatables/dataTables.bootstrap4.min.js') ?>"></script>
<script>
(function () {
  var period = <?= json_encode($period) ?>;
  var dateFromParam = <?= json_encode($from) ?>;
  var dateToParam = <?= json_encode($to) ?>;
  var webFilter = <?= json_encode($websiteWebFilter ?? 'all') ?>;
  var apiBase = (window.ADM_BASE || '') + '/analytics/api/sessions_list.php';
  var traceApiBase = (window.ADM_BASE || '') + '/analytics/api/session_trace.php';
  var adminViewApi = (window.ADM_BASE || '') + '/analytics/api/session_admin_view.php';
  var tableBody = document.getElementById('sessionsTableBody');
  var loading = document.getElementById('sessionsLoading');
  var sessionsDt = null;
  var allRows = [];
  var activeTab = 'sessions';
  var STORAGE_LIMIT = 'cw_sessions_limit';
  var currentLimit = 100;
  var sessionsError = document.getElementById('sessionsError');
  var sessionsLimitNotice = document.getElementById('sessionsLimitNotice');
  var sessionsLimit = document.getElementById('sessionsLimit');
  var traceModal = document.getElementById('sessionTraceModal');
  var traceTitle = document.getElementById('sessionTraceModalTitle');
  var traceSub = document.getElementById('sessionTraceModalSub');
  var traceSummary = document.getElementById('sessionTraceSummary');
  var traceLoading = document.getElementById('sessionTraceLoading');
  var traceTableBody = document.getElementById('sessionTraceTableBody');
  var sessionTraceDt = null;

  function escapeHtml(s) {
    return String(s || '').replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }

  function escapeAttr(s) {
    return escapeHtml(s).replace(/`/g, '&#96;');
  }

  function contactCell(value, display) {
    if (!value) {
      return '<span class="va-contact-empty">—</span>';
    }
    return '<span class="va-contact-filled">' + escapeHtml(display || value) + '</span>';
  }

  function destroySessionsTable() {
    if (typeof jQuery !== 'undefined' && jQuery.fn.DataTable && jQuery.fn.DataTable.isDataTable('#sessionsTable')) {
      jQuery('#sessionsTable').DataTable().destroy();
    }
    sessionsDt = null;
    if (tableBody) tableBody.innerHTML = '';
  }

  function showError(msg) {
    if (sessionsError) {
      sessionsError.hidden = false;
      sessionsError.textContent = msg;
    }
  }

  function readStoredLimit() {
    try {
      var raw = localStorage.getItem(STORAGE_LIMIT);
      if (!raw) return null;
      var n = parseInt(raw, 10);
      if ([100, 500, 2000, 3000, 8000, 0].indexOf(n) !== -1) {
        return n;
      }
    } catch (e) { /* noop */ }
    return null;
  }

  function saveStoredLimit(limit) {
    try {
      localStorage.setItem(STORAGE_LIMIT, String(limit));
    } catch (e) { /* noop */ }
  }

  function applyLimitSelect(limit) {
    if (!sessionsLimit) return;
    var val = String(limit);
    var found = false;
    Array.prototype.forEach.call(sessionsLimit.options, function (opt) {
      if (opt.value === val) {
        found = true;
      }
    });
    if (found) {
      sessionsLimit.value = val;
    }
  }

  function updateLimitNotice(summary) {
    if (!sessionsLimitNotice) return;
    summary = summary || {};
    var loaded = summary.total || 0;
    var total = summary.total_in_period || 0;
    sessionsLimitNotice.hidden = false;
    if (summary.loaded_all || (total > 0 && loaded >= total)) {
      sessionsLimitNotice.innerHTML =
        'Cargadas <strong>' + loaded + '</strong> de <strong>' + total + '</strong> sesiones del periodo. ' +
        'La tabla pagina de 25 en 25 (usa la paginación abajo). Las pestañas filtran ese lote cargado.';
      return;
    }
    sessionsLimitNotice.innerHTML =
      'Cargadas las <strong>' + loaded + '</strong> sesiones más recientes de <strong>' + total + '</strong> del periodo. ' +
      'Los KPIs de arriba cuentan el periodo completo. Elige <strong>Todos</strong> en Mostrar para cargar el resto.';
  }

  function updatePeriodMeta(res) {
    var labelEl = document.getElementById('sessionsPeriodLabel');
    var rangeEl = document.getElementById('sessionsPeriodRange');
    var totalEl = document.getElementById('sessionsPeriodTotal');
    if (labelEl) labelEl.textContent = res.period || period || '—';
    if (rangeEl && res.from && res.to) {
      rangeEl.textContent = String(res.from).slice(0, 16) + ' → ' + String(res.to).slice(0, 16);
    }
    if (totalEl && res.summary) {
      totalEl.textContent = String(res.summary.total_in_period || 0);
    }
  }

  function clearError() {
    if (sessionsError) {
      sessionsError.hidden = true;
      sessionsError.textContent = '';
    }
  }

  function parseLimitValue(raw) {
    var limit = parseInt(raw, 10);
    // 0 = Todos (no usar || 100: 0 es falsy en JS)
    if (!isFinite(limit) || limit < 0) {
      return 100;
    }
    if ([0, 100, 500, 2000, 3000, 8000].indexOf(limit) === -1) {
      return 100;
    }
    return limit;
  }

  function loadSessions(limit) {
    currentLimit = parseLimitValue(limit);
    clearError();
    if (loading) loading.hidden = false;
    if (tableBody) tableBody.innerHTML = '';
    if (sessionsLimitNotice) {
      sessionsLimitNotice.hidden = false;
      sessionsLimitNotice.textContent = currentLimit === 0
        ? 'Cargando todas las sesiones del periodo… puede tardar unos segundos.'
        : 'Cargando ' + currentLimit + ' sesiones…';
    }

    var url = apiBase + '?period=' + encodeURIComponent(period) + '&limit=' + encodeURIComponent(currentLimit);
    if (webFilter && webFilter !== 'all') {
      url += '&web=' + encodeURIComponent(webFilter);
    }
    if (period === 'custom' && dateFromParam && dateToParam) {
      url += '&from=' + encodeURIComponent(dateFromParam) + '&to=' + encodeURIComponent(dateToParam);
    }
    fetch(url, { credentials: 'same-origin' })
      .then(function (r) {
        return r.text().then(function (text) {
          var data = { success: false };
          try {
            data = text ? JSON.parse(text) : data;
          } catch (e) {
            var preview = String(text || '').replace(/\s+/g, ' ').slice(0, 180);
            throw new Error('Respuesta inválida del servidor' + (preview ? ': ' + preview : ''));
          }
          if (!r.ok && data.error) {
            throw new Error(data.error);
          }
          return data;
        });
      })
      .then(function (res) {
        if (loading) loading.hidden = true;
        if (!res.success) {
          showError(res.error || 'No se pudieron cargar las sesiones.');
          return;
        }
        allRows = res.sessions || [];
        var summary = res.summary || {};
        updatePeriodMeta(res);
        var tabSessions = document.getElementById('sessionsTabCountSessions');
        var tabContact = document.getElementById('sessionsTabCountContact');
        var tabCrawler = document.getElementById('sessionsTabCountCrawler');
        var tabSpam = document.getElementById('sessionsTabCountSpam');
        if (tabSessions) tabSessions.textContent = String(summary.sessions ?? 0);
        if (tabContact) tabContact.textContent = String(summary.with_contact_real ?? summary.with_contact ?? 0);
        if (tabCrawler) tabCrawler.textContent = String(summary.crawler_ia ?? 0);
        if (tabSpam) tabSpam.textContent = String(summary.possible_spam ?? summary.spam_contact ?? 0);
        var kpiHuman = document.getElementById('kpiSessionsHuman');
        var kpiContact = document.getElementById('kpiSessionsContact');
        var kpiCrawler = document.getElementById('kpiSessionsCrawler');
        var kpiSpam = document.getElementById('kpiSessionsSpam');
        if (kpiHuman) kpiHuman.textContent = String(summary.sessions ?? 0);
        if (kpiContact) kpiContact.textContent = String(summary.with_contact_real ?? summary.with_contact ?? 0);
        if (kpiCrawler) kpiCrawler.textContent = String(summary.crawler_ia ?? 0);
        if (kpiSpam) kpiSpam.textContent = String(summary.possible_spam ?? summary.spam_contact ?? 0);
        updateLimitNotice(summary);
        setTab(activeTab);
      })
      .catch(function (err) {
        if (loading) loading.hidden = true;
        showError(err && err.message ? err.message : 'Error al cargar sesiones.');
        if (tableBody) {
          tableBody.innerHTML = '<tr><td colspan="13" class="text-danger text-center">Error al cargar sesiones.</td></tr>';
        }
      });
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

  function traceActionCell(s) {
    var continued = !!(s.continued_after_admin && (s.nav_after_admin || 0) > 0);
    var badge = continued
      ? '<span class="va-after-admin-badge" title="Siguió navegando después de consulta admin' +
        (s.admin_last_viewed_fmt ? ' (' + s.admin_last_viewed_fmt + ')' : '') + '">' +
        (s.nav_after_admin || 0) + '</span>'
      : '';
    var btnClass = 'adm-act va-trace-btn' + (continued ? ' adm-act--success' : ' adm-act--view');
    return '<div class="adm-actions va-trace-wrap">' + badge +
      '<button type="button" class="' + btnClass + '" data-session-id="' + escapeAttr(s.session_id) + '">' +
      '<i class="fas fa-route"></i>Recorrido</button></div>';
  }

  function markSessionViewedByAdmin(sessionId) {
    allRows.forEach(function (row) {
      if (row.session_id === sessionId) {
        row.admin_last_viewed_fmt = 'Ahora';
        row.nav_after_admin = 0;
        row.continued_after_admin = false;
      }
    });
    setTab(activeTab);
  }

  function recordAdminSessionView(sessionId) {
    fetch(adminViewApi, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ session_id: sessionId })
    }).then(function () {
      markSessionViewedByAdmin(sessionId);
    }).catch(function () { /* noop */ });
  }

    function geoCell(s) {
    var label = s.geo_display || s.geo || s.region || '—';
    var source = (s.geo_source || '').toLowerCase();
    var badge = '';
    if (source === 'gps') {
      badge = '<span class="va-geo-badge va-geo-badge-gps" title="Ubicación GPS exacta del navegador">GPS</span>';
    } else if (source === 'ip') {
      badge = '<span class="va-geo-badge va-geo-badge-ip" title="Ubicación detectada por IP">IP</span>';
    } else if (source === 'browser') {
      badge = '<span class="va-geo-badge va-geo-badge-browser" title="Aproximación por navegador">Navegador</span>';
    } else if (source === 'local') {
      badge = '<span class="va-geo-badge va-geo-badge-local" title="Entorno local / desarrollo">Local</span>';
    }
    var mapsLink = '';
    if (s.geo_maps_url) {
      mapsLink = ' <a href="' + escapeAttr(s.geo_maps_url) + '" class="va-geo-map-link" target="_blank" rel="noopener noreferrer" title="Ver en mapa"><i class="bi bi-geo-alt-fill"></i></a>';
    }
    return '<span class="va-cell-geo-wrap" title="' + escapeAttr(label) + '">' +
      '<span class="va-cell-geo">' + escapeHtml(label) + '</span>' + badge + mapsLink + '</span>';
  }

  function sessionIdCell(sessionId) {
    if (!sessionId) {
      return '<span class="text-muted">—</span>';
    }
    var shortId = sessionId.length > 14 ? sessionId.slice(0, 14) + '…' : sessionId;
    return '<code class="va-session-id" title="' + escapeAttr(sessionId) + '">' + escapeHtml(shortId) + '</code>';
  }

    function deviceCell(s) {
    var title = s.device || '—';
    if (s.browser && s.browser !== '—') {
      title += ' · ' + s.browser;
    }
    if (s.device_screen) {
      title += ' · ' + s.device_screen;
    }
    return '<span class="va-device-pill va-device-' + escapeAttr(s.device_type || 'other') + '" title="' + escapeAttr(title) + '">' +
      '<i class="bi bi-' + escapeAttr(s.device_icon || 'question-circle') + '"></i> ' + escapeHtml(s.device || '—') + '</span>';
  }

  function durationCell(s) {
    var spam = isSpamSession(s);
    var crawler = isCrawlerSession(s);
    return '<span class="va-duration' + (spam ? ' is-spam' : '') + (crawler ? ' is-crawler' : '') + '"' +
      (spam ? ' title="Contacto sospechoso"' : (crawler ? ' title="Crawler / IA"' : '')) + '>' +
      '<strong>' + escapeHtml(s.session_duration_fmt || '0s') + '</strong>' +
      (spam ? '<small>spam</small>' : (crawler ? '<small>bot</small>' : '')) +
      '</span>';
  }

  var UA_BOT_LABELS = [
    ['Googlebot', 'googlebot'],
    ['Bingbot', 'bingbot'],
    ['ClaudeBot', 'claudebot'],
    ['GPTBot', 'gptbot'],
    ['ChatGPT', 'chatgpt-user'],
    ['Perplexity', 'perplexitybot'],
    ['NotebookLM', 'google-notebooklm'],
    ['Applebot', 'applebot'],
    ['Semrush', 'semrushbot'],
    ['Ahrefs', 'ahrefsbot'],
    ['Facebook', 'facebookexternalhit'],
    ['Amazonbot', 'amazonbot'],
    ['Headless Chrome', 'headlesschrome'],
    ['Pingdom', 'pingdom']
  ];

  function userAgentBotLabel(ua) {
    var lower = String(ua || '').toLowerCase();
    if (!lower) return '';
    for (var i = 0; i < UA_BOT_LABELS.length; i++) {
      if (lower.indexOf(UA_BOT_LABELS[i][1]) !== -1) {
        return UA_BOT_LABELS[i][0];
      }
    }
    return '';
  }

  function userAgentCell(s) {
    var ua = String(s.user_agent || '');
    if (!ua) {
      return '<span class="va-ua-cell va-ua-cell-empty" title="Sin user-agent registrado">' +
        '<span class="va-ua-bot-name">Ping / sin UA</span>' +
        '<small class="va-ua-hint">Entrada instantánea sin identificarse</small></span>';
    }
    var bot = userAgentBotLabel(ua);
    var short = s.user_agent_short || (ua.length > 96 ? ua.slice(0, 96) + '…' : ua);
    if (bot) {
      return '<span class="va-ua-cell" title="' + escapeAttr(ua) + '">' +
        '<span class="va-ua-bot-name"><i class="bi bi-robot" aria-hidden="true"></i> ' + escapeHtml(bot) + '</span>' +
        '<code class="va-ua-detail">' + escapeHtml(short) + '</code></span>';
    }
    return '<span class="va-ua-cell" title="' + escapeAttr(ua) + '">' +
      '<span class="va-ua-bot-name">Navegador / bot desconocido</span>' +
      '<code class="va-ua-detail">' + escapeHtml(short) + '</code></span>';
  }

  function renderSessionsTable(rows) {
    destroySessionsTable();
    if (!rows.length) {
      var emptyMsg = 'Sin sesiones en este lote para la pestaña seleccionada.';
      if (activeTab === 'possible_spam') emptyMsg = 'No hay contactos sospechosos en este lote.';
      else if (activeTab === 'crawler_ia') emptyMsg = 'No hay crawlers / IA en este lote.';
      else if (activeTab === 'contact') emptyMsg = 'No hay contactos reales en este lote.';
      else if (activeTab === 'sessions') emptyMsg = 'No hay visitas humanas sin contacto en este lote. Sube el límite “Mostrar” o cambia de pestaña.';
      tableBody.innerHTML = '<tr><td colspan="13" class="text-muted text-center">' + emptyMsg + '</td></tr>';
      return;
    }
    rows.forEach(function (s) {
      var tr = document.createElement('tr');
      if (isSpamSession(s)) {
        tr.classList.add('va-session-spam');
      } else if (isCrawlerSession(s)) {
        tr.classList.add('va-session-crawler');
      }
      var landingCell = s.landing_url
        ? '<a href="' + escapeAttr(s.landing_url) + '" class="va-cell-link va-cell-url" target="_blank" rel="noopener noreferrer" title="' + escapeAttr(s.landing_url) + '">' + escapeHtml(s.landing_path) + '</a>'
        : '<span class="text-muted">—</span>';
      tr.innerHTML =
        '<td class="va-col-session-id">' + sessionIdCell(s.session_id) + categoryBadge(s) + '</td>' +
        '<td class="va-col-contact">' + contactCell(s.nombre, s.nombre_display) + '</td>' +
        '<td class="va-col-contact">' + contactCell(s.telefono, s.telefono_display) + '</td>' +
        '<td class="va-col-contact">' + contactCell(s.correo, s.correo_display) + '</td>' +
        '<td>' + geoCell(s) + '</td>' +
        '<td>' + deviceCell(s) + '</td>' +
        '<td class="va-col-ua">' + userAgentCell(s) + '</td>' +
        '<td><span class="va-cell-referrer" title="' + escapeAttr(s.referrer) + '">' + escapeHtml(s.referrer_label) + '</span></td>' +
        '<td>' + landingCell + '</td>' +
        '<td data-order="' + escapeAttr(s.first_seen_sort) + '">' + escapeHtml(s.first_seen) + '</td>' +
        '<td data-order="' + (s.session_duration || 0) + '">' + durationCell(s) + '</td>' +
        '<td data-order="' + (s.routes_global || s.pages_count || 0) + '">' + routeBadges(s.routes_global || s.pages_count, s.routes_unique) + '</td>' +
        '<td data-order="' + (s.nav_after_admin || 0) + '">' + traceActionCell(s) + '</td>';
      tableBody.appendChild(tr);
    });
    tableBody.querySelectorAll('.va-trace-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        openTraceModal(btn.getAttribute('data-session-id') || '');
      });
    });
    if (typeof jQuery !== 'undefined' && jQuery.fn.DataTable) {
      try {
        sessionsDt = jQuery('#sessionsTable').DataTable({
          scrollX: true,
          order: [[9, 'desc']],
          pageLength: 25,
          lengthMenu: [[25, 50, 100, 250, -1], [25, 50, 100, 250, 'Todas en tabla']],
          columnDefs: [
            { targets: [0], className: 'va-col-session-id' },
            { targets: [1, 2, 3], className: 'va-col-contact' },
            { targets: [6], className: 'va-col-ua' }
          ],
          language: window.VA_DT_LANG_ES || {}
        });
      } catch (e) {
        console.warn('DataTables sesiones:', e);
      }
    }
  }

  function sessionDurationSec(s) {
    if (!s) return 0;
    var n = Number(s.session_duration);
    if (isFinite(n)) {
      return n > 0 ? n : 0;
    }
    var fmt = String(s.session_duration_fmt || '').trim().toLowerCase();
    if (fmt === '0s' || fmt === '0' || fmt === '') {
      return 0;
    }
    return 1;
  }

  function sessionCategory(s) {
    if (!s) return 'sessions';
    return s.session_category || 'sessions';
  }

  function isCrawlerSession(s) {
    return sessionCategory(s) === 'crawler_ia';
  }

  function isSpamSession(s) {
    if (!s) return false;
    if (s.is_possible_spam === true || s.is_possible_spam === 1 || s.is_possible_spam === '1') return true;
    if (s.is_spam === true || s.is_spam === 1 || s.is_spam === '1') return true;
    return sessionCategory(s) === 'possible_spam';
  }

  function hasContact(s) {
    if (!s) return false;
    if (s.has_contact) return true;
    return !!(s.nombre || s.telefono || s.correo);
  }

  function categoryBadge(s) {
    var cat = sessionCategory(s);
    if (cat === 'crawler_ia') {
      return '<span class="va-session-badge va-session-badge-crawler" title="Crawler o bot de IA/SEO">Crawler / IA</span>';
    }
    if (cat === 'possible_spam') {
      return '<span class="va-session-badge va-session-badge-spam" title="Contacto sospechoso">Posible spam</span>';
    }
    if (cat === 'contact') {
      return '<span class="va-session-badge va-session-badge-contact" title="Contacto registrado">Contacto</span>';
    }
    return '';
  }

  function setTab(tab) {
    activeTab = tab;
    document.querySelectorAll('[data-sessions-tab]').forEach(function (btn) {
      btn.classList.toggle('is-active', btn.getAttribute('data-sessions-tab') === tab);
    });
    var hint = document.getElementById('sessionsTabHint');
    var hintText = document.getElementById('sessionsTabHintText');
    if (hint && hintText) {
      var hints = {
        sessions: 'Visitas humanas sin formulario de contacto.',
        contact: 'Leads reales con nombre, teléfono o correo registrado.',
        crawler_ia: 'Googlebot, ClaudeBot, NotebookLM y pings automáticos sin contacto. Revisa la columna User-Agent para identificar cada bot.',
        possible_spam: 'Envíos con nombre aleatorio, correo sospechoso o patrón de bot comercial.'
      };
      hintText.textContent = hints[tab] || '';
      hint.hidden = !hints[tab];
    }
    var filtered = allRows.filter(function (s) {
      return sessionCategory(s) === tab;
    });
    renderSessionsTable(filtered);
  }

  function closeTraceModal() {
    if (!traceModal) return;
    traceModal.setAttribute('hidden', 'hidden');
    traceModal.classList.remove('is-open');
    traceModal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('va-top-modal-open');
    if (sessionTraceDt) {
      sessionTraceDt.destroy();
      sessionTraceDt = null;
    }
    if (traceTableBody) traceTableBody.innerHTML = '';
  }

  function openTraceModal(sessionId) {
    if (!traceModal || !sessionId) return;
    recordAdminSessionView(sessionId);
    if (traceTitle) traceTitle.textContent = 'Recorrido del visitante';
    if (traceSub) traceSub.textContent = 'Sesión ' + sessionId;
    if (traceSummary) traceSummary.innerHTML = '';
    if (traceLoading) traceLoading.hidden = false;
    traceModal.removeAttribute('hidden');
    traceModal.classList.add('is-open');
    traceModal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('va-top-modal-open');

    fetch(traceApiBase + '?session_id=' + encodeURIComponent(sessionId), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (traceLoading) traceLoading.hidden = true;
        if (!res.success || !res.trace) {
          if (traceSummary) traceSummary.innerHTML = '<p class="text-danger small mb-0">No se pudo cargar el recorrido.</p>';
          return;
        }
        var t = res.trace;
        if (traceTitle) traceTitle.textContent = t.has_contact ? ('Recorrido · ' + t.nombre) : 'Recorrido del visitante';
        if (traceSub) traceSub.textContent = t.geo + ' · ' + t.device;
        if (traceSummary) {
          traceSummary.innerHTML =
            '<div class="va-session-trace-kpis">' +
            '<span>' + routeBadges(t.routes_global || t.pages_count, t.routes_unique) + '</span>' +
            '<span><strong>Sesión:</strong> <code>' + escapeHtml(t.session_id || sessionId) + '</code></span>' +
            '<span><strong>Nombre:</strong> ' + escapeHtml(t.nombre) + '</span>' +
            '<span><strong>Tel:</strong> ' + escapeHtml(t.telefono) + '</span>' +
            '<span><strong>Correo:</strong> ' + escapeHtml(t.correo) + '</span>' +
            '<span><strong>Referrer:</strong> ' + escapeHtml(t.referrer_label) + '</span>' +
            '<span><strong>Entrada:</strong> ' + escapeHtml(t.landing_path) + '</span>' +
            '<span><strong>Inicio:</strong> ' + escapeHtml(t.first_seen) + '</span>' +
            '<span><strong>Duración:</strong> ' + escapeHtml(t.session_duration_fmt) + '</span>' +
            '</div>';
        }
        var steps = t.journey || [];
        if (!traceTableBody) return;
        if (!steps.length) {
          traceTableBody.innerHTML = '<tr><td colspan="6" class="text-muted text-center">Sin pasos registrados.</td></tr>';
          return;
        }
        steps.forEach(function (step) {
          var tr = document.createElement('tr');
          var isPage = step.kind === 'pageview';
          var typeBadge = isPage
            ? '<span class="badge badge-light">Página</span>'
            : '<span class="badge badge-info">' + escapeHtml(step.event_type || 'evento') + '</span>';
          var pageCell = isPage
            ? '<a href="' + escapeAttr(step.url) + '" class="va-cell-link" target="_blank" rel="noopener noreferrer"><code>' + escapeHtml(step.path || '/') + '</code></a>' +
              (step.title ? '<br><small class="text-muted">' + escapeHtml(step.title) + '</small>' : '')
            : escapeHtml(step.event_label || '—');
          tr.innerHTML =
            '<td>' + (step.step || '') + '</td>' +
            '<td>' + typeBadge + '</td>' +
            '<td>' + pageCell + '</td>' +
            '<td data-order="' + escapeAttr(step.at_sort || step.at) + '">' + escapeHtml(step.at_fmt) + '</td>' +
            '<td data-order="' + (isPage ? step.time_on_page : 0) + '">' + (isPage ? '<strong>' + escapeHtml(step.time_on_page_fmt) + '</strong>' : '—') + '</td>' +
            '<td>' + escapeHtml(isPage ? (step.region || '—') : '—') + '</td>';
          traceTableBody.appendChild(tr);
        });
      })
      .catch(function () {
        if (traceLoading) traceLoading.hidden = true;
        if (traceSummary) traceSummary.innerHTML = '<p class="text-danger small mb-0">Error de conexión.</p>';
      });
  }

  var storedLimit = readStoredLimit();
  if (storedLimit !== null) {
    currentLimit = storedLimit;
    applyLimitSelect(storedLimit);
  }

  loadSessions(currentLimit);

  if (sessionsLimit) {
    sessionsLimit.addEventListener('change', function () {
      var limit = parseLimitValue(sessionsLimit.value);
      saveStoredLimit(limit);
      loadSessions(limit);
    });
  }

  document.querySelectorAll('[data-sessions-tab]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      setTab(btn.getAttribute('data-sessions-tab') || 'sessions');
    });
  });

  document.querySelectorAll('[data-close-trace-modal]').forEach(function (el) {
    el.addEventListener('click', closeTraceModal);
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && traceModal && traceModal.classList.contains('is-open')) closeTraceModal();
  });
})();
</script>
<script src="<?= adm_href('analytics/js/visits-live.js') ?>"></script>
</body></html>
