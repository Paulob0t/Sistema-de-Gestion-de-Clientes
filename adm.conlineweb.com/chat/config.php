<?php
require_once dirname(__DIR__) . '/auth_middleware.php';
require_once dirname(__DIR__) . '/conn.php';
require_once dirname(__DIR__) . '/includes/cw_hub_permissions.php';
require_once dirname(__DIR__) . '/includes/cw_chat_migrate.php';
require_once dirname(__DIR__) . '/includes/cw_chat_service.php';

cw_hub_require('hub.crm.view');
cw_chat_migrate($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $keys = ['saludo_inicial','mensaje_espera','mensaje_fuera_horario','mensaje_escalamiento','mensaje_whatsapp','whatsapp_numero','horario_inicio','horario_fin','dias_laborales','alerta_sin_respuesta_minutos','alerta_email_activa','bot_nombre','email_soporte','mensaje_aclaracion','mensaje_sin_respuesta'];
    foreach ($keys as $k) {
        if (isset($_POST[$k])) {
            cw_chat_config_set($conn, $k, trim((string) $_POST[$k]));
        }
    }
    header('Location: ' . adm_href('chat/config.php') . '?saved=1');
    exit;
}

$cfg = [];
$keys = ['saludo_inicial','mensaje_espera','mensaje_fuera_horario','mensaje_escalamiento','mensaje_whatsapp','whatsapp_numero','horario_inicio','horario_fin','dias_laborales','alerta_sin_respuesta_minutos','alerta_email_activa','bot_nombre','email_soporte'];
foreach ($keys as $k) {
    $cfg[$k] = cw_chat_config_get($conn, $k);
}

include dirname(__DIR__) . '/menu.php';

$admPageTitle = 'Configuración del chatbot';
$admPageSubtitle = 'Horarios, mensajes automáticos y alertas del asistente virtual';
$admPageIcon = 'bi bi-gear';
$admHeaderVariant = 'compact';
?>
<div class="adm-page-shell chat-admin-page">
<div class="container-fluid px-0">
  <?php include dirname(__DIR__) . '/includes/adm_page_header.php'; ?>
  <?php if (!empty($_GET['saved'])): ?><div class="alert alert-success">Configuración guardada.</div><?php endif; ?>
  <form method="post" class="card ch-card adm-content-card"><div class="card-body row g-3">
    <div class="col-12"><label class="form-label">Nombre del bot</label><input class="form-control" name="bot_nombre" value="<?= htmlspecialchars($cfg['bot_nombre']) ?>"></div>
    <div class="col-12"><label class="form-label">Saludo inicial</label><textarea class="form-control" name="saludo_inicial" rows="2"><?= htmlspecialchars($cfg['saludo_inicial']) ?></textarea></div>
    <div class="col-12"><label class="form-label">Mensaje fuera de horario</label><textarea class="form-control" name="mensaje_fuera_horario" rows="2"><?= htmlspecialchars($cfg['mensaje_fuera_horario']) ?></textarea></div>
    <div class="col-12"><label class="form-label">Mensaje de escalamiento</label><textarea class="form-control" name="mensaje_escalamiento" rows="2"><?= htmlspecialchars($cfg['mensaje_escalamiento']) ?></textarea></div>
    <div class="col-12"><label class="form-label">Mensaje cuando no hay respuesta en la base</label><textarea class="form-control" name="mensaje_sin_respuesta" rows="2" placeholder="Se ofrece transferir a un asesor"><?= htmlspecialchars($cfg['mensaje_sin_respuesta'] ?? '') ?></textarea></div>
    <div class="col-12"><label class="form-label">Mensaje de aclaración <small class="text-muted">(usa {titulo} para el tema detectado)</small></label><textarea class="form-control" name="mensaje_aclaracion" rows="2"><?= htmlspecialchars($cfg['mensaje_aclaracion'] ?? '') ?></textarea></div>
    <div class="col-md-4"><label class="form-label">Horario inicio</label><input class="form-control" name="horario_inicio" value="<?= htmlspecialchars($cfg['horario_inicio']) ?>"></div>
    <div class="col-md-4"><label class="form-label">Horario fin</label><input class="form-control" name="horario_fin" value="<?= htmlspecialchars($cfg['horario_fin']) ?>"></div>
    <div class="col-md-4"><label class="form-label">Días laborales (1=lun)</label><input class="form-control" name="dias_laborales" value="<?= htmlspecialchars($cfg['dias_laborales']) ?>"></div>
    <div class="col-md-4"><label class="form-label">WhatsApp</label><input class="form-control" name="whatsapp_numero" value="<?= htmlspecialchars($cfg['whatsapp_numero']) ?>"></div>
    <div class="col-md-4"><label class="form-label">Alerta sin respuesta (min)</label><input class="form-control" name="alerta_sin_respuesta_minutos" value="<?= htmlspecialchars($cfg['alerta_sin_respuesta_minutos'] ?? '5') ?>" title="Minutos sin atender antes de enviar correo"></div>
    <div class="col-md-4"><label class="form-label">Correo de soporte</label><input class="form-control" name="email_soporte" value="<?= htmlspecialchars($cfg['email_soporte'] ?? 'servicios@conlineweb.com') ?>"></div>
    <div class="col-md-4"><label class="form-label">Alertas email</label><select class="form-control" name="alerta_email_activa"><option value="1" <?= $cfg['alerta_email_activa']==='1'?'selected':'' ?>>Activas</option><option value="0" <?= $cfg['alerta_email_activa']==='0'?'selected':'' ?>>Desactivadas</option></select></div>
    <div class="col-12"><button class="btn btn-primary" type="submit">Guardar configuración</button></div>
  </div></form>
</div>
</div>
</div></div></body></html>
