(function () {
  'use strict';

  var API = (window.ADM_BASE || '') + '/analytics/api/live_stats.php?action=visits';
  if (window.CW_ANALYTICS_WEB && window.CW_ANALYTICS_WEB !== 'all') {
    API += '&web=' + encodeURIComponent(window.CW_ANALYTICS_WEB);
  }
  var pollMs = 45000;
  var chartHourly = null;

  function el(id) {
    return document.getElementById(id);
  }

  function setText(id, val) {
    var node = el(id);
    if (node) {
      node.textContent = val;
    }
  }

  function formatDuration(sec) {
    sec = Math.max(0, parseInt(sec, 10) || 0);
    if (sec < 60) {
      return sec + 's';
    }
    var m = Math.floor(sec / 60);
    var s = sec % 60;
    return m + 'm ' + s + 's';
  }

  function updateHourlyChart(hourly) {
    if (!hourly || !window.Chart) {
      return;
    }
    var ctx = el('chartLiveHourly');
    if (!ctx) {
      return;
    }
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
        datasets: [{
          label: 'Visitas por hora',
          data: hourly.values,
          backgroundColor: 'rgba(16,185,129,.55)',
          borderColor: '#10b981',
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        scales: { y: { beginAtZero: true } },
        legend: { display: false }
      }
    });
  }

  function refresh() {
    fetch(API, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.success || !res.data) {
          return;
        }
        var d = res.data;
        var t = d.today || {};
        setText('livePageviews', (t.pageviews || 0).toLocaleString('es-MX'));
        setText('liveUsers', (t.users || 0).toLocaleString('es-MX'));
        setText('liveActive', (d.active_sessions || 0).toLocaleString('es-MX'));
        setText('liveAvgTime', formatDuration(t.avgSec || 0));
        var tz = d.timezone || 'America/Mexico_City';
        setText('liveUpdated', new Date(d.generated_at).toLocaleTimeString('es-MX', { timeZone: tz }));
        updateHourlyChart(d.hourly);
      })
      .catch(function () {});
  }

  document.addEventListener('DOMContentLoaded', function () {
    refresh();
    setInterval(refresh, pollMs);
  });
})();
