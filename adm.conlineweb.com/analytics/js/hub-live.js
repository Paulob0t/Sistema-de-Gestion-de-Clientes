(function () {
  'use strict';
  var API = (window.ADM_BASE || '') + '/analytics/api/live_stats.php';
  if (window.CW_ANALYTICS_WEB && window.CW_ANALYTICS_WEB !== 'all') {
    API += (API.indexOf('?') === -1 ? '?' : '&') + 'web=' + encodeURIComponent(window.CW_ANALYTICS_WEB);
  }
  var pollMs = 45000;
  var chartHourly = null;

  function el(id) { return document.getElementById(id); }

  function setText(id, val) {
    var n = el(id);
    if (n) n.textContent = val;
  }

  function renderLeads(leads) {
    var box = el('hubLiveLeads');
    if (!box) return;
    if (!leads || !leads.length) {
      box.innerHTML = '<p class="text-muted small mb-0">Sin leads hoy aún.</p>';
      return;
    }
    var html = '<ul class="list-unstyled mb-0 small">';
    leads.forEach(function (l) {
      html += '<li class="mb-2 pb-2 border-bottom"><a href="' + (window.ADM_BASE || '') + '/leads/detalle.php?id=' + l.id + '"><strong>' +
        escapeHtml(l.nombre) + '</strong></a><br><span class="text-muted">' +
        escapeHtml(l.servicio || '') + ' · ' + escapeHtml(l.fecha_registro) + '</span></li>';
    });
    html += '</ul>';
    box.innerHTML = html;
  }

  function renderReminders(data) {
    var box = el('hubReminders');
    if (!box || !data) return;
    var items = (data.pending || []).concat(data.upcoming || []);
    if (!items.length) {
      box.innerHTML = '<p class="text-muted small mb-0">No hay recordatorios próximos.</p>';
      return;
    }
    var html = '';
    items.slice(0, 8).forEach(function (r) {
      html += '<div class="small mb-2"><i class="fas fa-bell text-warning mr-1"></i> ' +
        '<a href="' + (window.ADM_BASE || '') + '/leads/detalle.php?id=' + r.lead_id + '">' + escapeHtml(r.lead_nombre || 'Lead') + '</a><br>' +
        '<span class="text-muted">' + escapeHtml(r.proxima_accion || '') + '</span></div>';
    });
    box.innerHTML = html;
  }

  function escapeHtml(s) {
    return String(s || '').replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }

  function updateHourlyChart(hourly) {
    if (!hourly || !window.Chart) return;
    var ctx = el('chartLiveHourly');
    if (!ctx) return;
    if (chartHourly) {
      chartHourly.data.labels = hourly.labels;
      chartHourly.data.datasets[0].data = hourly.values;
      chartHourly.update();
      return;
    }
    chartHourly = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: hourly.labels,
        datasets: [{ label: 'Hoy por hora', data: hourly.values, backgroundColor: 'rgba(28,200,138,.55)', borderColor: '#1cc88a', borderWidth: 1 }]
      },
      options: { responsive: true, scales: { y: { beginAtZero: true } }, legend: { display: false } }
    });
  }

  function refresh() {
    fetch(API, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.success || !res.data) return;
        var d = res.data;
        var t = d.today || {};
        setText('livePageviews', (t.pageviews || 0).toLocaleString('es-MX'));
        setText('liveUsers', (t.users || 0).toLocaleString('es-MX'));
        setText('liveLeads', (t.leadsCount || 0).toLocaleString('es-MX'));
        setText('liveActive', (d.active_sessions || 0).toLocaleString('es-MX'));
        setText('liveReminders', (d.pending_reminders || 0).toLocaleString('es-MX'));
        setText('liveUpdated', new Date(d.generated_at).toLocaleTimeString('es-MX', { timeZone: d.timezone || 'America/Mexico_City' }));
        updateHourlyChart(d.hourly);
        renderLeads(d.recent_leads);
      }).catch(function () {});

    fetch(API + '?action=reminders', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) { if (res.success) renderReminders(res); })
      .catch(function () {});
  }

  document.addEventListener('DOMContentLoaded', function () {
    refresh();
    setInterval(refresh, pollMs);
  });
})();
