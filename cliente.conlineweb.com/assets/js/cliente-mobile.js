/**
 * cliente-mobile.js — Soporte móvil transversal del portal de clientes.
 *
 * Envuelve las tablas que no tienen contenedor con scroll en un .cw-tablewrap.
 * Se hace desde JS y no en el PHP porque las páginas heredadas (facturas.php,
 * facturas2020.php, productos.php) tienen marcado desbalanceado: para cuando
 * el navegador construye el DOM ya lo ha normalizado, así que envolver aquí
 * es fiable, mientras que editar el HTML a mano no lo sería.
 *
 * El estilo de .cw-tablewrap vive en cliente-mobile.css y solo aplica por
 * debajo de 1199.98px, por lo que en escritorio este div es inerte.
 */
(function () {
    'use strict';

    /* Contenedores que ya resuelven el scroll por su cuenta. */
    var YA_ENVUELTA = [
        '.cw-tablewrap',
        '.table-responsive',
        '.pg-datatable-wrap',
        '.dataTables_wrapper',
        '.dataTables_scrollBody'
    ].join(',');

    function envolverTablas(raiz) {
        var tablas = (raiz || document).querySelectorAll('table');

        Array.prototype.forEach.call(tablas, function (tabla) {
            if (tabla.closest(YA_ENVUELTA)) return;

            var wrap = document.createElement('div');
            wrap.className = 'cw-tablewrap';
            tabla.parentNode.insertBefore(wrap, tabla);
            wrap.appendChild(tabla);
        });
    }

    /* Marca las que realmente desbordan para mostrar el degradado lateral. */
    function marcarDesbordes() {
        var wraps = document.querySelectorAll('.cw-tablewrap');

        Array.prototype.forEach.call(wraps, function (wrap) {
            var desborda = wrap.scrollWidth > wrap.clientWidth + 1;
            wrap.classList.toggle('is-scrollable', desborda);
        });
    }

    function init() {
        envolverTablas(document);
        marcarDesbordes();

        var pendiente;
        window.addEventListener('resize', function () {
            clearTimeout(pendiente);
            pendiente = setTimeout(marcarDesbordes, 150);
        });

        /* Las tablas dentro de acordeones se miden a 0 mientras están
           ocultas; al desplegarlas hay que recalcular. */
        document.addEventListener('click', function () {
            setTimeout(marcarDesbordes, 60);
        }, true);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
