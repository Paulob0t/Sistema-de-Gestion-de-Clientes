<?php
/**
 * Vista previa de proyecto (iframe escalado) para DataTables admin.
 * Incluir una vez antes de </body> en páginas que usen .btnVistaPreviaProyecto
 * o .adm-dt-preview.
 */
if (!empty($GLOBALS['adm_proyecto_preview_loaded'])) {
    return;
}
$GLOBALS['adm_proyecto_preview_loaded'] = true;
?>
<style>
.adm-dt-preview {
    width: 220px;
    max-width: 100%;
    aspect-ratio: 16 / 9;
    position: relative;
    overflow: hidden;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
}
.adm-dt-preview__ph {
    position: absolute;
    inset: 0;
    z-index: 2;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    padding: 0.55rem;
    text-align: center;
}
.adm-dt-preview__ph[hidden] { display: none !important; }
.adm-dt-preview__icon { font-size: 1.25rem; color: #38bdf8; opacity: 0.45; }
.adm-dt-preview__host {
    font-size: 0.65rem;
    color: #94a3b8;
    max-width: 95%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.adm-dt-preview__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
    justify-content: center;
}
.adm-dt-preview__btn {
    border: 1px solid rgba(56, 189, 248, 0.4);
    background: rgba(56, 189, 248, 0.12);
    color: #7dd3fc;
    border-radius: 999px;
    padding: 0.28rem 0.55rem;
    font-size: 0.62rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}
.adm-dt-preview__btn:hover { background: rgba(56, 189, 248, 0.22); color: #e0f2fe; text-decoration: none; }
.adm-dt-preview__btn--web {
    border-color: rgba(167, 139, 250, 0.45);
    background: rgba(167, 139, 250, 0.12);
    color: #c4b5fd;
}
.adm-dt-preview__btn--web:hover { background: rgba(167, 139, 250, 0.22); color: #ede9fe; }
.adm-dt-preview__btn:disabled { opacity: 0.7; cursor: wait; }
.adm-dt-preview__empty {
    font-size: 0.68rem;
    color: #94a3b8;
    font-weight: 600;
}
.adm-dt-preview__scaled {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    z-index: 1;
}
.adm-dt-preview__scaled[hidden] { display: none !important; }
.adm-dt-preview__scaled iframe {
    border: 0;
    pointer-events: none;
    display: block;
    opacity: 0;
    transition: opacity 0.25s ease;
}
.adm-dt-preview__loading {
    position: absolute;
    inset: 0;
    z-index: 4;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    background: rgba(15, 23, 42, 0.88);
    color: #38bdf8;
    font-size: 0.65rem;
    font-weight: 600;
}
.adm-dt-preview__err {
    margin: 0;
    font-size: 0.6rem;
    color: #fca5a5;
    line-height: 1.35;
    max-width: 11rem;
}
.adm-dt-preview__err a { color: #7dd3fc; font-weight: 700; }

#modalVistaPreviaProyecto .modal-dialog { max-width: 920px; }
#modalVistaPreviaProyecto .modal-body { padding: 0; background: #0f172a; }
#modalVistaPreviaProyecto .adm-preview-modal-frame {
    width: 100%;
    aspect-ratio: 16 / 9;
    position: relative;
    overflow: hidden;
    background: #0f172a;
}
#modalVistaPreviaProyecto .adm-preview-modal-frame iframe {
    border: 0;
    display: block;
    opacity: 0;
    transition: opacity 0.25s ease;
    pointer-events: auto;
}
#modalVistaPreviaProyecto .adm-preview-modal-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    background: #fff;
    border-top: 1px solid #e2e8f0;
}
#modalVistaPreviaProyecto .adm-preview-modal-url {
    font-size: 0.8rem;
    color: #64748b;
    word-break: break-all;
    margin: 0;
    flex: 1 1 220px;
}
</style>

<div class="modal fade" id="modalVistaPreviaProyecto" tabindex="-1" role="dialog" aria-labelledby="modalVistaPreviaProyectoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalVistaPreviaProyectoLabel">Vista previa del proyecto</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="adm-preview-modal-frame" id="admPreviewModalFrame">
                    <div class="adm-dt-preview__loading" id="admPreviewModalLoading">
                        <i class="fas fa-spinner fa-spin"></i>
                        <span>Cargando vista previa…</span>
                    </div>
                    <div class="adm-dt-preview__scaled" id="admPreviewModalScaled" hidden></div>
                </div>
                <div class="adm-preview-modal-bar">
                    <p class="adm-preview-modal-url" id="admPreviewModalUrl"></p>
                    <a href="#" target="_blank" rel="noopener" class="btn-primary-custom" id="admPreviewModalOpen" style="padding:0.45rem 0.9rem;font-size:0.8rem;">
                        <i class="fas fa-external-link-alt"></i> Abrir web
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function ($) {
    if (window.AdmProyectoPreview) return;

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function previewHost(url) {
        try {
            return new URL(url, window.location.origin).hostname.replace(/^www\./, '');
        } catch (e) {
            return url || '';
        }
    }

    function scaleIframe(iframe, container) {
        if (!iframe || !container) return;
        const containerWidth = container.offsetWidth || 1;
        const containerHeight = container.offsetHeight || 1;
        const iframeWidth = 1200;
        const iframeHeight = 675;
        const scale = Math.min(containerWidth / iframeWidth, containerHeight / iframeHeight);
        iframe.style.width = iframeWidth + 'px';
        iframe.style.height = iframeHeight + 'px';
        iframe.style.transform = 'scale(' + scale + ')';
        iframe.style.transformOrigin = 'top left';
    }

    function loadInlinePreview(box, url, triggerBtn) {
        if (!box || !url || box.dataset.previewLoading === '1' || box.dataset.previewLoaded === '1') return;
        const placeholder = box.querySelector('.adm-dt-preview__ph');
        const scaled = box.querySelector('.adm-dt-preview__scaled');
        if (!scaled) return;

        box.dataset.previewLoading = '1';
        if (triggerBtn) triggerBtn.disabled = true;

        let overlay = box.querySelector('.adm-dt-preview__loading');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'adm-dt-preview__loading';
            overlay.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Cargando…</span>';
            box.appendChild(overlay);
        }

        scaled.hidden = false;
        scaled.innerHTML = '<iframe src="' + escapeHtml(url) + '" loading="eager" sandbox="allow-same-origin allow-scripts allow-forms" scrolling="no" title="Vista previa"></iframe>';
        const iframe = scaled.querySelector('iframe');
        if (!iframe) {
            box.dataset.previewLoading = '0';
            if (triggerBtn) triggerBtn.disabled = false;
            return;
        }

        let settled = false;
        const settle = function (ok) {
            if (settled) return;
            settled = true;
            clearTimeout(timer);
            box.dataset.previewLoading = '0';
            if (overlay) overlay.remove();
            if (triggerBtn) triggerBtn.disabled = false;
            if (ok) {
                box.dataset.previewLoaded = '1';
                if (placeholder) placeholder.hidden = true;
                iframe.style.opacity = '1';
                requestAnimationFrame(function () {
                    scaleIframe(iframe, box);
                    requestAnimationFrame(function () { scaleIframe(iframe, box); });
                });
            } else if (placeholder) {
                scaled.hidden = true;
                scaled.innerHTML = '';
                const err = document.createElement('p');
                err.className = 'adm-dt-preview__err';
                err.innerHTML = 'No se pudo cargar. <a href="' + escapeHtml(url) + '" target="_blank" rel="noopener">Ver web</a>';
                placeholder.appendChild(err);
            }
        };

        iframe.addEventListener('load', function () { settle(true); }, { once: true });
        iframe.addEventListener('error', function () { settle(false); }, { once: true });
        const timer = setTimeout(function () { settle(true); }, 6000);
    }

    function openModalPreview(url, nombre) {
        const $modal = $('#modalVistaPreviaProyecto');
        const frame = document.getElementById('admPreviewModalFrame');
        const scaled = document.getElementById('admPreviewModalScaled');
        const loading = document.getElementById('admPreviewModalLoading');
        const urlEl = document.getElementById('admPreviewModalUrl');
        const openBtn = document.getElementById('admPreviewModalOpen');
        if (!$modal.length || !frame || !scaled) return;

        $('#modalVistaPreviaProyectoLabel').text(nombre || 'Vista previa del proyecto');
        if (urlEl) urlEl.textContent = url;
        if (openBtn) openBtn.href = url;
        if (loading) loading.style.display = 'flex';
        scaled.hidden = false;
        scaled.innerHTML = '<iframe src="' + escapeHtml(url) + '" loading="eager" sandbox="allow-same-origin allow-scripts allow-forms allow-popups" title="Vista previa"></iframe>';
        const iframe = scaled.querySelector('iframe');

        $modal.appendTo('body').modal('show');

        const finish = function () {
            if (loading) loading.style.display = 'none';
            if (!iframe) return;
            iframe.style.opacity = '1';
            requestAnimationFrame(function () {
                scaleIframe(iframe, frame);
                requestAnimationFrame(function () { scaleIframe(iframe, frame); });
            });
        };
        if (iframe) {
            iframe.addEventListener('load', finish, { once: true });
            setTimeout(finish, 6000);
        } else if (loading) {
            loading.style.display = 'none';
        }
    }

    $(document).on('click', '.adm-dt-preview__load', function (e) {
        e.preventDefault();
        const btn = this;
        const box = btn.closest('.adm-dt-preview');
        const url = btn.getAttribute('data-preview-url') || (box && box.getAttribute('data-url'));
        if (box && url) loadInlinePreview(box, url, btn);
    });

    $(document).on('click', '.btnVistaPreviaProyecto', function (e) {
        e.preventDefault();
        const url = ($(this).data('url') || '').toString().trim();
        const nombre = ($(this).data('nombre') || '').toString().trim();
        if (!url) {
            if (window.Swal) {
                Swal.fire('Sin URL', 'Este proyecto no tiene URL para vista previa.', 'info');
            }
            return;
        }
        openModalPreview(url, nombre);
    });

    $('#modalVistaPreviaProyecto').on('hidden.bs.modal', function () {
        const scaled = document.getElementById('admPreviewModalScaled');
        const loading = document.getElementById('admPreviewModalLoading');
        if (scaled) {
            scaled.hidden = true;
            scaled.innerHTML = '';
        }
        if (loading) loading.style.display = 'flex';
    });

    $(window).on('resize', function () {
        document.querySelectorAll('.adm-dt-preview__scaled iframe[src]').forEach(function (iframe) {
            const box = iframe.closest('.adm-dt-preview');
            if (box) scaleIframe(iframe, box);
        });
        const modalIframe = document.querySelector('#admPreviewModalScaled iframe[src]');
        const frame = document.getElementById('admPreviewModalFrame');
        if (modalIframe && frame && $('#modalVistaPreviaProyecto').hasClass('show')) {
            scaleIframe(modalIframe, frame);
        }
    });

    window.AdmProyectoPreview = {
        host: previewHost,
        openModal: openModalPreview,
        markup: function (url, nombre) {
            url = (url || '').toString().trim();
            if (!url) {
                return '<div class="adm-dt-preview"><div class="adm-dt-preview__ph"><span class="adm-dt-preview__empty">Sin URL</span></div></div>';
            }
            const host = escapeHtml(previewHost(url));
            const safeUrl = escapeHtml(url);
            const safeName = escapeHtml(nombre || '');
            return (
                '<div class="adm-dt-preview" data-url="' + safeUrl + '">' +
                    '<div class="adm-dt-preview__ph">' +
                        '<i class="fas fa-globe adm-dt-preview__icon"></i>' +
                        '<div class="adm-dt-preview__host" title="' + safeUrl + '">' + host + '</div>' +
                        '<div class="adm-dt-preview__actions">' +
                            '<a class="adm-dt-preview__btn adm-dt-preview__btn--web" href="' + safeUrl + '" target="_blank" rel="noopener">Ver web</a>' +
                            '<button type="button" class="adm-dt-preview__btn adm-dt-preview__load" data-preview-url="' + safeUrl + '">Ver aquí</button>' +
                            '<button type="button" class="adm-dt-preview__btn btnVistaPreviaProyecto" data-url="' + safeUrl + '" data-nombre="' + safeName + '" title="Ampliar"><i class="fas fa-expand"></i></button>' +
                        '</div>' +
                    '</div>' +
                    '<div class="adm-dt-preview__scaled" hidden></div>' +
                '</div>'
            );
        }
    };
})(jQuery);
</script>
