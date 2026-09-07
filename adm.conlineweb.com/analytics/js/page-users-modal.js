/**
 * Modal de ingresos por página (detalle de visitas).
 * Usado en analytics/index.php y analytics/pages.php.
 */
(function () {
  function escapeHtml(s) {
    return String(s || '').replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }

  function escapeAttr(s) {
    return escapeHtml(s).replace(/`/g, '&#96;');
  }

  window.VAPageUsersModal = {
    init: function (options) {
      options = options || {};
      var period = options.period || '30d';
      var webFilter = options.web || window.CW_ANALYTICS_WEB || 'all';
      var modal = document.getElementById('pageUsersModal');
      var title = document.getElementById('pageUsersModalTitle');
      var sub = document.getElementById('pageUsersModalSub');
      var summary = document.getElementById('pageUsersSummary');
      var loading = document.getElementById('pageUsersLoading');
      var tableBody = document.getElementById('pageUsersTableBody');
      var pageUsersDt = null;
      var apiBase = (window.ADM_BASE || '') + '/analytics/api/page_users.php';

      function destroyTable() {
        if (typeof jQuery !== 'undefined' && jQuery.fn.DataTable && jQuery.fn.DataTable.isDataTable('#pageUsersTable')) {
          jQuery('#pageUsersTable').DataTable().destroy();
        }
        pageUsersDt = null;
        if (tableBody) tableBody.innerHTML = '';
      }

      function closeModal() {
        if (!modal) return;
        modal.setAttribute('hidden', 'hidden');
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('va-top-modal-open');
        destroyTable();
      }

      function openModal(path, pageTitle) {
        if (!modal || !path) return;
        destroyTable();
        if (title) title.textContent = 'Ingresos · ' + path;
        if (sub) sub.textContent = (pageTitle || path) + ' · visitas a esta página (hora CDMX del servidor)';
        if (summary) summary.innerHTML = '';
        if (loading) loading.hidden = false;
        modal.removeAttribute('hidden');
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('va-top-modal-open');

        var usersUrl = apiBase + '?path=' + encodeURIComponent(path) + '&period=' + encodeURIComponent(period);
        if (webFilter && webFilter !== 'all') {
          usersUrl += '&web=' + encodeURIComponent(webFilter);
        }
        fetch(usersUrl, { credentials: 'same-origin' })
          .then(function (r) {
            return r.text().then(function (text) {
              var data = { success: false };
              try {
                data = text ? JSON.parse(text) : data;
              } catch (e) {
                throw new Error('Respuesta inválida del servidor');
              }
              return data;
            });
          })
          .then(function (res) {
            if (loading) loading.hidden = true;
            if (!res.success) {
              if (summary) {
                summary.innerHTML = '<p class="text-danger small mb-0">' + escapeHtml(res.error || 'No se pudo cargar el detalle.') + '</p>';
              }
              return;
            }
            var s = res.summary || {};
            if (summary) {
              summary.innerHTML =
                '<div class="va-page-users-kpis">' +
                '<span><strong>' + (s.visits || 0).toLocaleString('es-MX') + '</strong> ingresos</span>' +
                '<span><strong>' + (s.unique_visitors || 0).toLocaleString('es-MX') + '</strong> usuarios únicos</span>' +
                '<span>Tiempo prom. <strong>' + (s.avg_time_fmt || '0s') + '</strong></span>' +
                '<span>Tiempo total <strong>' + (s.total_time_fmt || '0s') + '</strong></span>' +
                '</div>';
            }
            var rows = res.users || [];
            if (!tableBody) return;
            if (!rows.length) {
              tableBody.innerHTML = '<tr><td colspan="10" class="text-muted text-center">Sin visitas en este periodo.</td></tr>';
              return;
            }
            rows.forEach(function (u) {
              var tr = document.createElement('tr');
              var urlCell = u.url
                ? '<a href="' + escapeAttr(u.url) + '" class="va-cell-link va-cell-url" target="_blank" rel="noopener noreferrer" title="' + escapeAttr(u.url) + '">' + escapeHtml(u.url_short || u.url) + '</a>'
                : '<span class="text-muted">—</span>';
              var refCell = '<span class="va-cell-referrer" title="' + escapeAttr(u.referrer || u.referrer_label) + '">' + escapeHtml(u.referrer_label || '—') + '</span>';
              tr.innerHTML =
                '<td><code title="' + escapeAttr(u.visitor_id) + '">' + escapeHtml(u.visitor_short) + '</code></td>' +
                '<td>' + urlCell + '</td>' +
                '<td>' + refCell + '</td>' +
                '<td>' + escapeHtml(u.country) + '</td>' +
                '<td>' + escapeHtml(u.region) + '</td>' +
                '<td data-order="' + escapeAttr(u.viewed_at_sort) + '">' + escapeHtml(u.viewed_at) + '</td>' +
                '<td data-order="' + (u.time_on_page || 0) + '"><strong>' + escapeHtml(u.time_on_page_fmt) + '</strong></td>' +
                '<td>' + escapeHtml(u.device) + '</td>' +
                '<td>' + escapeHtml(u.browser) + ' <small class="text-muted">' + escapeHtml(u.os) + '</small></td>' +
                '<td><small class="text-muted" title="' + escapeAttr(u.session_id) + '">' + escapeHtml(u.session_short) + '</small></td>';
              tableBody.appendChild(tr);
            });
            if (typeof jQuery !== 'undefined' && jQuery.fn.DataTable) {
              try {
                pageUsersDt = jQuery('#pageUsersTable').DataTable({
                  scrollX: true,
                  order: [[5, 'desc']],
                  pageLength: 15,
                  language: window.VA_DT_LANG_ES || {}
                });
              } catch (e) {
                console.warn('DataTables detalle página:', e);
              }
            }
          })
          .catch(function (err) {
            if (loading) loading.hidden = true;
            if (summary) {
              summary.innerHTML = '<p class="text-danger small mb-0">' + escapeHtml(err && err.message ? err.message : 'Error de conexión.') + '</p>';
            }
          });
      }

      document.addEventListener('click', function (e) {
        var btn = e.target.closest('.va-page-detail-btn');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        openModal(btn.getAttribute('data-path') || '', btn.getAttribute('data-title') || '');
      });

      document.querySelectorAll('[data-close-page-modal]').forEach(function (el) {
        el.addEventListener('click', closeModal);
      });

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal && modal.classList.contains('is-open')) closeModal();
      });

      return { open: openModal, close: closeModal };
    }
  };
})();
