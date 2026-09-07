<?php
require_once dirname(__DIR__) . '/auth_middleware.php';
require_once dirname(__DIR__) . '/conn.php';
require_once dirname(__DIR__) . '/includes/cw_hub_config.php';
require_once dirname(__DIR__) . '/includes/cw_hub_migrate.php';
require_once dirname(__DIR__) . '/includes/cw_hub_permissions.php';

cw_hub_migrate($conn);
cw_hub_require('hub.crm.kanban');

$columns = ['nuevo', 'calificado', 'seguimiento', 'propuesta', 'cerrado'];
$leadsByCol = array_fill_keys($columns, []);

$q = $conn->query("SELECT id, nombre, correo, telefono, servicio, pagina_origen, pipeline_estado, ultima_interaccion, fecha_registro
    FROM leads WHERE eliminado = 0 ORDER BY fecha_registro DESC LIMIT 200");
while ($row = $q->fetch_assoc()) {
    $st = $row['pipeline_estado'] ?: 'nuevo';
    if ($st === 'perdido') continue;
    if (!isset($leadsByCol[$st])) $st = 'nuevo';
    $leadsByCol[$st][] = $row;
}

include dirname(__DIR__) . '/menu.php';

$admPageTitle = 'Pipeline CRM — Kanban';
$admPageSubtitle = 'Arrastra tarjetas entre etapas del embudo comercial';
$admPageIcon = 'fas fa-columns';
$admPageActions = '<a href="' . adm_href('leads/inbox.php') . '" class="btn btn-sm btn-primary mr-1">Bandeja</a>'
    . '<a href="' . adm_href('leads/index.php') . '" class="btn btn-sm btn-outline-primary mr-1">Tabla</a>'
    . '<a href="/analytics/index.php" class="btn btn-sm btn-outline-secondary">Analítica</a>';
?>
<div class="adm-page-shell">
<div class="container-fluid px-0">
<?php include dirname(__DIR__) . '/includes/adm_page_header.php'; ?>
<div class="kb-wrap">
<?php foreach ($columns as $col):
  $label = CW_HUB_PIPELINE[$col] ?? ucfirst($col);
?>
<div class="kb-col" data-column="<?= $col ?>">
  <h6><?= htmlspecialchars($label) ?> (<?= count($leadsByCol[$col]) ?>)</h6>
  <?php foreach ($leadsByCol[$col] as $l):
    $svc = CW_HUB_SERVICIOS[$l['servicio'] ?? ''] ?? ($l['servicio'] ?? '—');
  ?>
  <div class="kb-card" draggable="true" data-id="<?= (int)$l['id'] ?>">
    <h5><?= htmlspecialchars($l['nombre']) ?></h5>
    <small><?= htmlspecialchars($svc) ?></small>
    <small><?= htmlspecialchars($l['telefono'] ?? '') ?></small>
    <div class="kb-actions">
      <a href="/leads/detalle.php?id=<?= (int)$l['id'] ?>" class="btn btn-xs btn-sm btn-primary">Ver</a>
      <?php if ($l['telefono']): ?>
      <a href="https://wa.me/<?= preg_replace('/\D/','',$l['telefono']) ?>" target="_blank" class="btn btn-xs btn-sm btn-success cw-hub-skip"><i class="fab fa-whatsapp"></i></a>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endforeach; ?>
</div><!-- kb-wrap -->
</div><!-- .container-fluid px-0 -->
</div><!-- .adm-page-shell -->
</div><!-- menu container-fluid -->
</div><!-- menu content-wrapper -->
<script>
(function(){
  var dragId = null;
  document.querySelectorAll('.kb-card').forEach(function(card){
    card.addEventListener('dragstart', function(){ dragId = this.dataset.id; });
  });
  document.querySelectorAll('.kb-col').forEach(function(col){
    col.addEventListener('dragover', function(e){ e.preventDefault(); });
    col.addEventListener('drop', function(e){
      e.preventDefault();
      if (!dragId) return;
      var estado = this.dataset.column;
      fetch('/leads/api_pipeline.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'id=' + encodeURIComponent(dragId) + '&pipeline_estado=' + encodeURIComponent(estado)
      }).then(function(){ location.reload(); });
    });
  });
})();
</script>
</body></html>
