/**
 * Layout por defecto de DataTables en consultas admin.
 * Solo afecta el DOM visual; búsqueda, orden y paginación siguen igual.
 */
(function ($) {
    'use strict';

    if (!$.fn.dataTable) {
        return;
    }

    $.extend(true, $.fn.dataTable.defaults, {
        dom:
            '<"adm-dt-toolbar row mx-0 align-items-end"' +
            '<"col-12 col-lg-6 px-0 adm-dt-length"l>' +
            '<"col-12 col-lg-6 px-0 adm-dt-search"f>>' +
            'rt' +
            '<"adm-dt-footer row mx-0 align-items-center"' +
            '<"col-12 col-md-5 px-0 adm-dt-info"i>' +
            '<"col-12 col-md-7 px-0 adm-dt-paginate"p>>',
    });
})(jQuery);
