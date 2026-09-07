<?php
require_once dirname(__DIR__) . '/auth_middleware.php';
require_once dirname(__DIR__) . '/conn.php';
require_once dirname(__DIR__) . '/includes/cw_hub_permissions.php';
require_once dirname(__DIR__) . '/includes/cw_chat_migrate.php';
require_once dirname(__DIR__) . '/includes/cw_chat_service.php';

cw_hub_require('hub.crm.view');
cw_chat_migrate($conn);

$stats = [
    'conversaciones_hoy' => 0,
    'activas' => 0,
    'cerradas' => 0,
    'escaladas' => 0,
    'mensajes_bot' => 0,
    'mensajes_cliente' => 0,
    'mensajes_agente' => 0,
    'sin_respuesta' => 0,
    'top_faq' => [],
    'sin_respuesta_list' => [],
];

$r = $conn->query("SELECT COUNT(*) c FROM cw_chat_conversaciones WHERE DATE(iniciada_at) = CURDATE()");
if ($r) {
    $stats['conversaciones_hoy'] = (int) ($r->fetch_assoc()['c'] ?? 0);
}

$r = $conn->query("SELECT COUNT(*) c FROM cw_chat_conversaciones WHERE estado NOT IN ('cerrada')");
if ($r) {
    $stats['activas'] = (int) ($r->fetch_assoc()['c'] ?? 0);
}

$r = $conn->query("SELECT COUNT(*) c FROM cw_chat_conversaciones WHERE estado = 'cerrada'");
if ($r) {
    $stats['cerradas'] = (int) ($r->fetch_assoc()['c'] ?? 0);
}

$r = $conn->query("SELECT COUNT(*) c FROM cw_chat_conversaciones WHERE estado IN ('pendiente_humano','humano')");
if ($r) {
    $stats['escaladas'] = (int) ($r->fetch_assoc()['c'] ?? 0);
}

$r = $conn->query("SELECT remitente_tipo, COUNT(*) c FROM cw_chat_mensajes WHERE DATE(enviado_at)=CURDATE() GROUP BY remitente_tipo");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        if ($row['remitente_tipo'] === 'bot') {
            $stats['mensajes_bot'] = (int) $row['c'];
        }
        if ($row['remitente_tipo'] === 'cliente') {
            $stats['mensajes_cliente'] = (int) $row['c'];
        }
        if ($row['remitente_tipo'] === 'agente') {
            $stats['mensajes_agente'] = (int) $row['c'];
        }
    }
}

$r = $conn->query('SELECT COUNT(*) c FROM cw_chat_preguntas_sin_respuesta WHERE resuelta = 0');
if ($r) {
    $stats['sin_respuesta'] = (int) ($r->fetch_assoc()['c'] ?? 0);
}

$r = $conn->query('SELECT titulo, veces_usada FROM cw_chat_conocimiento WHERE activo=1 ORDER BY veces_usada DESC LIMIT 8');
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $stats['top_faq'][] = $row;
    }
}

$r = $conn->query('SELECT mensaje, veces, intent_detectado FROM cw_chat_preguntas_sin_respuesta WHERE resuelta=0 ORDER BY veces DESC LIMIT 10');
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $stats['sin_respuesta_list'][] = $row;
    }
}

include dirname(__DIR__) . '/menu.php';

$admPageTitle = 'Estadísticas del chat';
$admPageSubtitle = 'Indicadores en tiempo real del centro de atención';
$admPageIcon = 'bi bi-graph-up';
$admPageActions = '<a href="' . adm_href('chat/index.php') . '" class="btn btn-outline-secondary btn-sm">Base de conocimiento</a>';
?>
<div class="adm-page-shell chat-admin-page">
<div class="container-fluid px-0">
  <?php include dirname(__DIR__) . '/includes/adm_page_header.php'; ?>
  <div class="row g-3 mt-1">
    <?php
    $cards = [
      ['Conversaciones hoy', $stats['conversaciones_hoy'], 'bi-chat-dots'],
      ['Activas', $stats['activas'], 'bi-lightning'],
      ['Escaladas', $stats['escaladas'], 'bi-person-raised-hand'],
      ['Cerradas', $stats['cerradas'], 'bi-check-circle'],
      ['Msgs cliente (hoy)', $stats['mensajes_cliente'], 'bi-person'],
      ['Msgs bot (hoy)', $stats['mensajes_bot'], 'bi-robot'],
      ['Msgs agente (hoy)', $stats['mensajes_agente'], 'bi-headset'],
      ['Sin respuesta', $stats['sin_respuesta'], 'bi-question-circle'],
    ];
    foreach ($cards as $card): ?>
    <div class="col-md-3 col-6">
      <div class="ch-stat-card">
        <i class="bi <?= $card[2] ?>"></i>
        <div><strong><?= (int) $card[1] ?></strong><span><?= htmlspecialchars($card[0]) ?></span></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="row g-3 mt-2">
    <div class="col-lg-6">
      <div class="card ch-card adm-content-card"><div class="card-body">
        <h2 class="adm-section-title">Preguntas más frecuentes (FAQ usadas)</h2>
        <ul class="ch-list"><?php foreach ($stats['top_faq'] as $f): ?><li><?= htmlspecialchars($f['titulo']) ?> <em>(<?= (int)$f['veces_usada'] ?>)</em></li><?php endforeach; ?></ul>
      </div></div>
    </div>
    <div class="col-lg-6">
      <div class="card ch-card adm-content-card"><div class="card-body">
        <h2 class="adm-section-title">Preguntas sin respuesta</h2>
        <ul class="ch-list"><?php foreach ($stats['sin_respuesta_list'] as $f): ?><li><?= htmlspecialchars(mb_substr($f['mensaje'],0,120)) ?> <em>(<?= (int)$f['veces'] ?>)</em></li><?php endforeach; ?></ul>
      </div></div>
    </div>
  </div>
</div>
</div>
</div></div></body></html>
