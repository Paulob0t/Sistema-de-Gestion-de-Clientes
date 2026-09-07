<?php
/**
 * Markup del modal de leads (Plazas / URLs).
 * Incluir antes de seo-leads-modal.js.
 */
?>
<div id="seoLeadsModal" class="seo-leads-modal" hidden aria-hidden="true">
  <div class="seo-leads-modal-backdrop" data-seo-leads-close="1"></div>
  <div class="seo-leads-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="seoLeadsModalTitle">
    <div class="seo-leads-modal-head">
      <div>
        <h3 id="seoLeadsModalTitle">Leads</h3>
        <p class="seo-leads-modal-sub" id="seoLeadsModalSub"></p>
      </div>
      <button type="button" class="seo-leads-modal-x" data-seo-leads-close="1" aria-label="Cerrar">&times;</button>
    </div>
    <div class="seo-leads-modal-body">
      <div id="seoLeadsModalLoading" class="seo-leads-modal-loading" hidden>Cargando registros…</div>
      <div id="seoLeadsModalError" class="seo-leads-modal-error" hidden></div>
      <div class="seo-leads-table-wrap">
        <table class="table table-sm va-table w-100">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Nombre</th>
              <th>Teléfono</th>
              <th>Correo</th>
              <th>Servicio</th>
              <th>Estatus</th>
              <th>Origen</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="seoLeadsModalBody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>
