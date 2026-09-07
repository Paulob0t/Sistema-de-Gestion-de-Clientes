<?php
/**
 * Historial de accesos / sesiones del portal.
 * Compatible cPanel: includes locales + errores visibles (no pantalla en blanco).
 */
declare(strict_types=1);

@ini_set('display_errors', '0');
@ini_set('log_errors', '1');

require_once dirname(__DIR__) . '/auth_middleware.php';
require_once dirname(__DIR__) . '/conn.php';
require_once dirname(__DIR__) . '/includes/adm_paths.php';

$cwLoadShared = static function ($basename) {
    $candidates = [
        dirname(__DIR__) . '/includes/' . $basename,
        dirname(__DIR__) . '/../includes/' . $basename,
        '/home/conlineweb/includes/' . $basename,
    ];
    foreach ($candidates as $path) {
        if (is_file($path)) {
            require_once $path;
            return true;
        }
    }
    return false;
};

$loadOk = true;
$loadErr = '';
try {
    if (!$cwLoadShared('cw_portal_login_log.php')) {
        $loadOk = false;
        $loadErr = 'No se encontró cw_portal_login_log.php';
    }
    if ($loadOk && !$cwLoadShared('cw_portal_login_alert.php')) {
        // alert es opcional para etiquetas; fallback inline abajo
    }
} catch (Throwable $e) {
    $loadOk = false;
    $loadErr = $e->getMessage();
    error_log('seguridad/accesos load: ' . $loadErr);
}

$tipoUser = (int) ($_SESSION['tipo'] ?? 0);
if ($tipoUser !== 1 && $tipoUser !== 2) {
    header('Location: ' . adm_href('index.php') . '?hub_error=acceso_denegado');
    exit;
}

/*
 * Cierre de sesiones del panel.
 *
 * Va antes de imprimir el menú porque termina en una redirección, y una vez
 * que se ha enviado HTML ya no se pueden mandar cabeceras.
 *
 * Solo alcanza a sesiones de portal='adm': las de clientes no se tocan desde
 * aquí. La sesión propia queda excluida para que nadie se expulse sin querer.
 */
if (empty($_SESSION['cw_acc_csrf'])) {
    $_SESSION['cw_acc_csrf'] = bin2hex(random_bytes(32));
}
$cwAccCsrf = (string) $_SESSION['cw_acc_csrf'];

/* Identificador del evento de la sesión con la que se ve esta página. */
$cwAccOwnEventId = (int) ($_SESSION['cw_login_event_id'] ?? 0);

$cwAccFlash = '';
$cwAccFlashType = 'success';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && ($_POST['accion'] ?? '') === 'cerrar_sesion'
    && $loadOk
    && isset($conn) && $conn instanceof mysqli) {

    $enviado = (string) ($_POST['csrf'] ?? '');
    $objetivo = (int) ($_POST['event_id'] ?? 0);

    if (!hash_equals($cwAccCsrf, $enviado)) {
        $cwAccFlash = 'La solicitud caducó. Vuelve a intentarlo.';
        $cwAccFlashType = 'danger';
    } elseif ($objetivo <= 0) {
        $cwAccFlash = 'No se indicó qué sesión cerrar.';
        $cwAccFlashType = 'danger';
    } elseif ($objetivo === $cwAccOwnEventId) {
        $cwAccFlash = 'Esa es tu propia sesión. Usa "Cerrar sesión" del menú.';
        $cwAccFlashType = 'danger';
    } else {
        $destino = cw_portal_login_log_find($conn, $objetivo);
        $estadoDestino = $destino !== null && function_exists('cw_portal_login_log_session_state')
            ? cw_portal_login_log_session_state($destino)
            : ['is_open' => false];
        if ($destino === null || (string) ($destino['event_type'] ?? '') !== 'success') {
            $cwAccFlash = 'No se encontró esa sesión.';
            $cwAccFlashType = 'danger';
        } elseif (empty($estadoDestino['is_open'])) {
            $cwAccFlash = 'Esa sesión ya estaba cerrada';
            if (!empty($estadoDestino['ended_fmt']) && $estadoDestino['ended_fmt'] !== '—') {
                $cwAccFlash .= ' el ' . $estadoDestino['ended_fmt'];
            }
            if (!empty($estadoDestino['close_label'])) {
                $cwAccFlash .= ' (' . $estadoDestino['close_label'] . ')';
            }
            $cwAccFlash .= '.';
            $cwAccFlashType = 'warning';
        } else {
            $quien = trim((string) ($_SESSION['usuario'] ?? ''));
            if ($quien === '') {
                $quien = 'UID #' . (int) ($_SESSION['uid'] ?? 0);
            }
            $ok = cw_portal_login_log_revoke($conn, $objetivo, (int) ($_SESSION['uid'] ?? 0), $quien);
            if ($ok) {
                $portalTxt = (string) ($destino['portal'] ?? '') === 'adm' ? 'panel' : 'portal cliente';
                $cwAccFlash = 'Sesión de ' . (string) ($destino['usuario'] ?? '—')
                    . ' (' . $portalTxt . ') cerrada a las '
                    . (new DateTimeImmutable('now', new DateTimeZone('America/Mexico_City')))->format('H:i')
                    . '. El dispositivo perderá el acceso en su siguiente acción.';
                $cwAccFlashType = 'success';
            } else {
                $cwAccFlash = 'Esa sesión ya estaba cerrada.';
                $cwAccFlashType = 'warning';
            }
        }
    }

    /* Petición-redirección-lectura: al recargar no se repite la acción. */
    $_SESSION['cw_acc_flash'] = ['msg' => $cwAccFlash, 'type' => $cwAccFlashType];
    $volverA = array_filter([
        'filter' => (string) ($_POST['r_filter'] ?? 'open'),
        'portal' => (string) ($_POST['r_portal'] ?? ''),
        'q' => (string) ($_POST['r_q'] ?? ''),
    ], static function ($v) {
        return $v !== '';
    });
    header('Location: ' . adm_href('seguridad/accesos.php') . '?' . http_build_query($volverA));
    exit;
}

if (!empty($_SESSION['cw_acc_flash'])) {
    $cwAccFlash = (string) ($_SESSION['cw_acc_flash']['msg'] ?? '');
    $cwAccFlashType = (string) ($_SESSION['cw_acc_flash']['type'] ?? 'success');
    unset($_SESSION['cw_acc_flash']);
}

$GLOBALS['admDocumentTitle'] = 'Accesos del portal';
include dirname(__DIR__) . '/menu.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    echo '<div class="adm-page-shell p-4"><div class="alert alert-danger">Sin conexión a base de datos.</div></div></div></div></body></html>';
    exit;
}

if (!$loadOk || !function_exists('cw_portal_login_log_ensure_table')) {
    echo '<div class="adm-page-shell p-4"><div class="alert alert-danger">'
        . '<strong>No se pudo cargar el módulo de accesos.</strong><br>'
        . htmlspecialchars($loadErr !== '' ? $loadErr : 'Falta includes/cw_portal_login_log.php en el servidor.', ENT_QUOTES, 'UTF-8')
        . '<br><small>Sube <code>adm.conlineweb.com/includes/cw_portal_login_log.php</code> y <code>cw_portal_login_alert.php</code>.</small>'
        . '</div></div></div></div></body></html>';
    exit;
}

try {
    cw_portal_login_log_ensure_table($conn);
} catch (Throwable $e) {
    echo '<div class="adm-page-shell p-4"><div class="alert alert-danger">Error al preparar la tabla: '
        . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
        . '</div></div></div></div></body></html>';
    exit;
}

$filter = strtolower(trim((string) ($_GET['filter'] ?? 'all')));
$allowedFilters = ['all', 'live', 'open', 'success', 'fail', 'blocked'];
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}
$portal = strtolower(trim((string) ($_GET['portal'] ?? '')));
if ($portal !== 'adm' && $portal !== 'cliente') {
    $portal = '';
}
$q = trim((string) ($_GET['q'] ?? ''));

try {
    $stats = cw_portal_login_log_stats($conn, 24, 5);
    $topIps = cw_portal_login_log_top_ips($conn, 24, 8);
    $rows = cw_portal_login_log_list($conn, $filter, $q, $portal, 250, 0);
} catch (Throwable $e) {
    echo '<div class="adm-page-shell p-4"><div class="alert alert-danger">Error al consultar historial: '
        . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
        . '</div></div></div></div></body></html>';
    exit;
}

if (!function_exists('cw_portal_login_alert_tipo_label')) {
    function cw_portal_login_alert_tipo_label($tipo)
    {
        switch ((int) $tipo) {
            case 0: return 'Cliente';
            case 1: return 'Administrador';
            case 2: return 'Solicitudes / soporte';
            case 3: return 'Agente / desarrollador';
            case 4: return 'Empresa externa';
            case 5: return 'CRM / Leads';
            default: return 'Tipo ' . $tipo;
        }
    }
}

$sessionTypeLabel = static function ($portal, $tipo) {
    $portalKey = strtolower(trim((string) $portal));
    $tipoInt = $tipo === null || $tipo === '' ? null : (int) $tipo;
    if ($portalKey !== 'adm' && $portalKey !== 'cliente') {
        $portalKey = ($tipoInt !== null && $tipoInt >= 1 && $tipoInt <= 5) ? 'adm' : 'cliente';
    }
    $portalName = $portalKey === 'adm' ? 'ADM' : 'Cliente';
    $role = $tipoInt === null ? 'Sin identificar' : cw_portal_login_alert_tipo_label($tipoInt);
    return [
        'portal' => $portalName,
        'portal_key' => $portalKey,
        'role' => $role,
        'full' => $portalName . ' · ' . $role,
    ];
};

$eventBadge = static function ($type, $endedAt, $revokedAt = null) {
    $type = (string) $type;
    if ($type === 'success' && $revokedAt !== null && $revokedAt !== '') {
        return '<span class="cw-acc-badge cw-acc-badge--revoked">Cerrada por admin</span>';
    }
    if ($type === 'success' && ($endedAt === null || $endedAt === '')) {
        return '<span class="cw-acc-badge cw-acc-badge--open">Abierta</span>';
    }
    if ($type === 'success') {
        return '<span class="cw-acc-badge cw-acc-badge--ok">Éxito</span>';
    }
    if ($type === 'fail' || $type === 'captcha_fail') {
        return '<span class="cw-acc-badge cw-acc-badge--fail">Fallido</span>';
    }
    if ($type === 'blocked') {
        return '<span class="cw-acc-badge cw-acc-badge--block">Bloqueo</span>';
    }

    return '<span class="cw-acc-badge">' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '</span>';
};

$reasonLabel = static function ($reason, $type) {
    $reason = (string) $reason;
    $type = (string) $type;
    if ($reason === '') {
        return $type === 'success' ? 'OK' : '—';
    }
    switch ($reason) {
        case 'ok':
            return 'OK';
        case 'bad_password':
            return 'Contraseña incorrecta';
        case 'user_not_found':
            return 'Usuario no existe';
        case 'empty_credentials':
            return 'Campos vacíos';
        case 'recaptcha':
            return 'Captcha';
        case 'rate_limit_ip':
            return 'Rate limit IP';
        case 'rate_limit_user':
            return 'Rate limit usuario';
        case 'revocada_panel':
            return 'Cerrada desde panel';
        case 'logout_usuario':
            return 'Cierre de sesión (usuario)';
        default:
            return $reason;
    }
};

$estadoBadgeClass = static function (array $estado): string {
    $badgeClass = 'cw-acc-badge';
    if ($estado['code'] === 'live') {
        $badgeClass .= ' cw-acc-badge--live';
    } elseif ($estado['code'] === 'active') {
        $badgeClass .= ' cw-acc-badge--active';
    } elseif ($estado['code'] === 'idle') {
        $badgeClass .= ' cw-acc-badge--idle';
    } elseif ($estado['code'] === 'closed') {
        $badgeClass .= ' cw-acc-badge--closed';
    } elseif ($estado['code'] === 'fail') {
        $badgeClass .= ' cw-acc-badge--fail';
    } elseif ($estado['code'] === 'blocked') {
        $badgeClass .= ' cw-acc-badge--block';
    } else {
        $badgeClass .= ' cw-acc-badge--ok';
    }

    return $badgeClass;
};

$cwAccPollUrl = adm_href('seguridad/accesos_poll.php');

$baseQs = static function (array $override = []) use ($filter, $portal, $q) {
    $params = array_merge([
        'filter' => $filter,
        'portal' => $portal,
        'q' => $q,
    ], $override);
    $clean = [];
    foreach ($params as $k => $v) {
        if ($v !== '' && $v !== null) {
            $clean[$k] = $v;
        }
    }
    return '?' . http_build_query($clean);
};
?>
<style>
.cw-acc-wrap{--navy:#000147;--ink:#0f172a;--muted:#64748b;--line:#e2e8f0;--soft:#f8fafc}
.cw-acc-wrap{font-family:"Plus Jakarta Sans",system-ui,sans-serif;color:var(--ink)}
.cw-acc-hero{margin:8px 0 22px}
.cw-acc-hero h1{font-size:1.55rem;font-weight:800;margin:0 0 6px;color:var(--navy);letter-spacing:-.02em}
.cw-acc-hero p{margin:0;color:var(--muted);font-size:.95rem;max-width:52rem}
.cw-acc-kpis{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:12px;margin-bottom:18px}
@media(max-width:1200px){.cw-acc-kpis{grid-template-columns:repeat(4,minmax(0,1fr))}}
@media(max-width:640px){.cw-acc-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
.cw-acc-kpi{background:#fff;border:1px solid var(--line);border-radius:14px;padding:14px 16px}
.cw-acc-kpi--live{border-color:#a7f3d0;background:linear-gradient(180deg,#f0fdf4 0%,#fff 100%)}
.cw-acc-kpi .lbl{font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);font-weight:700}
.cw-acc-kpi .num{font-size:1.55rem;font-weight:800;color:var(--navy);margin-top:4px;line-height:1.1}
.cw-acc-kpi .hint{font-size:.78rem;color:var(--muted);margin-top:4px}
.cw-acc-panel{background:#fff;border:1px solid var(--line);border-radius:16px;margin-bottom:16px;overflow:hidden}
.cw-acc-panel-h{padding:14px 18px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.cw-acc-panel-h h2{margin:0;font-size:1rem;font-weight:800;color:var(--navy)}
.cw-acc-panel-b{padding:16px 18px}
.cw-acc-tabs{display:flex;flex-wrap:wrap;gap:8px}
.cw-acc-tab{display:inline-flex;align-items:center;gap:6px;padding:8px 12px;border-radius:999px;border:1px solid var(--line);background:var(--soft);color:var(--ink);font-size:.84rem;font-weight:600;text-decoration:none}
.cw-acc-tab.is-active{background:var(--navy);border-color:var(--navy);color:#fff}
.cw-acc-filters{display:flex;flex-wrap:wrap;gap:10px;align-items:end}
.cw-acc-filters label{display:block;font-size:.72rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px}
.cw-acc-filters input,.cw-acc-filters select{border:1px solid var(--line);border-radius:10px;padding:8px 10px;min-width:160px;font-size:.9rem}
.cw-acc-filters button{border:0;border-radius:10px;background:var(--navy);color:#fff;font-weight:700;padding:9px 14px}
.cw-acc-table{width:100%;border-collapse:collapse;font-size:.86rem}
.cw-acc-table th{text-align:left;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);padding:10px 8px;border-bottom:1px solid var(--line);white-space:nowrap}
.cw-acc-table td{padding:11px 8px;border-bottom:1px solid #f1f5f9;vertical-align:top}
.cw-acc-table tr:hover td{background:#fafbff}
.cw-acc-badge{display:inline-block;padding:3px 8px;border-radius:999px;font-size:.72rem;font-weight:700}
.cw-acc-badge--ok{background:#ecfdf5;color:#047857}
.cw-acc-badge--open{background:#eff6ff;color:#1d4ed8}
.cw-acc-badge--fail{background:#fff7ed;color:#c2410c}
.cw-acc-badge--block{background:#fef2f2;color:#b91c1c}
.cw-acc-badge--revoked{background:#f5f3ff;color:#6d28d9}
.cw-acc-badge--active{background:#ecfdf5;color:#047857}
.cw-acc-badge--live{background:#d1fae5;color:#047857}
.cw-acc-badge--live::before{content:'';display:inline-block;width:7px;height:7px;margin-right:6px;border-radius:50%;background:#10b981;vertical-align:middle;animation:cwAccPulse 1.4s ease-in-out infinite}
@keyframes cwAccPulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.35;transform:scale(.85)}}
.cw-acc-badge--idle{background:#fffbeb;color:#b45309}
.cw-acc-badge--closed{background:#f1f5f9;color:#475569}
.cw-acc-time{display:flex;flex-direction:column;gap:2px;min-width:8.5rem}
.cw-acc-time strong{font-size:.8rem;color:var(--ink)}
.cw-acc-time span{font-size:.72rem;color:var(--muted)}
.cw-acc-kill{display:inline-flex;align-items:center;gap:6px;border:1px solid #fecaca;background:#fef2f2;color:#b91c1c;font-size:.78rem;font-weight:700;padding:6px 10px;border-radius:9px;cursor:pointer;white-space:nowrap}
.cw-acc-kill:hover{background:#fee2e2;border-color:#fca5a5}
.cw-acc-self{font-size:.75rem;font-weight:700;color:#047857;white-space:nowrap}
.cw-acc-gps{display:inline-block;margin-top:3px;padding:2px 7px;border-radius:999px;background:#ecfdf5;color:#047857;font-size:.7rem;font-weight:700;text-decoration:none}
.cw-acc-gps:hover{background:#d1fae5;color:#065f46}
.cw-acc-sess{display:flex;flex-direction:column;gap:4px;min-width:9.5rem}
.cw-acc-sess-portal{display:inline-flex;align-items:center;width:fit-content;padding:3px 8px;border-radius:999px;font-size:.72rem;font-weight:800;letter-spacing:.02em}
.cw-acc-sess-portal--adm{background:#eef2ff;color:#312e81}
.cw-acc-sess-portal--cliente{background:#ecfeff;color:#0e7490}
.cw-acc-sess-role{font-size:.8rem;font-weight:600;color:var(--ink)}
.cw-acc-mono{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.8rem}
.cw-acc-muted{color:var(--muted)}
.cw-acc-ua{max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.cw-acc-ip-list{display:grid;gap:8px}
.cw-acc-ip-row{display:flex;justify-content:space-between;gap:12px;padding:10px 12px;border:1px solid var(--line);border-radius:12px;background:var(--soft)}
.cw-acc-closed-info{display:flex;flex-direction:column;gap:2px;font-size:.78rem;color:#475569;max-width:11rem}
.cw-acc-closed-info strong{font-size:.8rem;color:var(--ink)}
.cw-acc-live-dot{display:inline-flex;align-items:center;gap:6px;font-size:.72rem;font-weight:700;color:#047857}
.cw-acc-live-dot::before{content:'';width:7px;height:7px;border-radius:50%;background:#10b981;animation:cwAccPulse 1.4s ease-in-out infinite}
.cw-acc-poll{font-size:.78rem;color:var(--muted);display:flex;align-items:center;gap:8px}
.cw-acc-empty{padding:28px;text-align:center;color:var(--muted)}
</style>

<link rel="stylesheet" href="<?= adm_href('vendor/datatables/dataTables.bootstrap4.min.css') ?>">
<link rel="stylesheet" href="<?= adm_href('css/admin-datatables.css') ?>?v=20250831a">

<div class="adm-page-shell cw-acc-wrap">
  <div class="container-fluid px-0">
    <header class="cw-acc-hero">
      <h1>Accesos del portal</h1>
      <p>Sesiones con estado en vivo, hora de inicio, cierre y quién cerró (usuario o administrador). Hora Ciudad de México. La tabla se actualiza automáticamente cada 30 segundos.</p>
    </header>

    <?php if ($cwAccFlash !== ''): ?>
    <div class="alert alert-<?= htmlspecialchars($cwAccFlashType, ENT_QUOTES, 'UTF-8') ?>" role="status">
      <?= htmlspecialchars($cwAccFlash, ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>

    <section class="cw-acc-kpis" aria-label="Resumen" id="cwAccKpis">
      <article class="cw-acc-kpi cw-acc-kpi--live">
        <div class="lbl">En vivo</div>
        <div class="num" data-kpi="live"><?= (int) $stats['live'] ?></div>
        <div class="hint">Actividad &lt; 5 min</div>
      </article>
      <article class="cw-acc-kpi">
        <div class="lbl">Abiertas</div>
        <div class="num" data-kpi="open"><?= (int) $stats['open'] ?></div>
        <div class="hint">Sin cierre · 24 h</div>
      </article>
      <article class="cw-acc-kpi">
        <div class="lbl">Abiertas ADM</div>
        <div class="num" data-kpi="open_adm"><?= (int) $stats['open_adm'] ?></div>
        <div class="hint"><span data-kpi="live_adm"><?= (int) $stats['live_adm'] ?></span> en vivo</div>
      </article>
      <article class="cw-acc-kpi">
        <div class="lbl">Abiertas cliente</div>
        <div class="num" data-kpi="open_cliente"><?= (int) $stats['open_cliente'] ?></div>
        <div class="hint"><span data-kpi="live_cliente"><?= (int) $stats['live_cliente'] ?></span> en vivo</div>
      </article>
      <article class="cw-acc-kpi">
        <div class="lbl">Éxitos 24 h</div>
        <div class="num" data-kpi="ok_24h"><?= (int) $stats['ok_24h'] ?></div>
        <div class="hint">Logins OK</div>
      </article>
      <article class="cw-acc-kpi">
        <div class="lbl">Fallidos 24 h</div>
        <div class="num" data-kpi="fail_24h"><?= (int) $stats['fail_24h'] ?></div>
        <div class="hint">Clave / captcha</div>
      </article>
      <article class="cw-acc-kpi">
        <div class="lbl">Bloqueos 24 h</div>
        <div class="num" data-kpi="blocked_24h"><?= (int) $stats['blocked_24h'] ?></div>
        <div class="hint">Rate limit / spam</div>
      </article>
    </section>

    <div class="row">
      <div class="col-lg-9">
        <section class="cw-acc-panel">
          <div class="cw-acc-panel-h">
            <h2>Historial</h2>
            <div class="d-flex flex-wrap align-items-center gap-3">
              <span class="cw-acc-poll" id="cwAccPollStatus" aria-live="polite">
                <span class="cw-acc-live-dot">En vivo</span>
                <span id="cwAccPollAt">—</span>
              </span>
              <nav class="cw-acc-tabs" aria-label="Filtros rápidos">
              <?php
              $tabs = [
                  'all' => 'Todos',
                  'live' => 'En vivo',
                  'open' => 'Abiertas',
                  'success' => 'Éxitos',
                  'fail' => 'Fallidos',
                  'blocked' => 'Bloqueos',
              ];
              foreach ($tabs as $key => $label):
                  $href = adm_href('seguridad/accesos.php') . $baseQs(['filter' => $key]);
              ?>
              <a class="cw-acc-tab<?= $filter === $key ? ' is-active' : '' ?>" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
              <?php endforeach; ?>
              </nav>
            </div>
          </div>
          <div class="cw-acc-panel-b">
            <form class="cw-acc-filters mb-3" method="get" action="">
              <input type="hidden" name="filter" value="<?= htmlspecialchars($filter, ENT_QUOTES, 'UTF-8') ?>">
              <div>
                <label for="cwAccQ">Buscar</label>
                <input id="cwAccQ" type="search" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Usuario, IP o ciudad">
              </div>
              <div>
                <label for="cwAccPortal">Portal</label>
                <select id="cwAccPortal" name="portal">
                  <option value=""<?= $portal === '' ? ' selected' : '' ?>>Todos</option>
                  <option value="adm"<?= $portal === 'adm' ? ' selected' : '' ?>>ADM</option>
                  <option value="cliente"<?= $portal === 'cliente' ? ' selected' : '' ?>>Cliente</option>
                </select>
              </div>
              <button type="submit">Filtrar</button>
            </form>

            <?php if ($rows === []): ?>
              <div class="cw-acc-empty">Aún no hay eventos. Aparecerán en el próximo login o intento fallido.</div>
            <?php else: ?>
            <div class="table-responsive">
              <table class="cw-acc-table table table-sm" id="cwAccTable" width="100%">
                <thead>
                  <tr>
                    <th>Estado</th>
                    <th>Inicio</th>
                    <th>Cierre</th>
                    <th>Cierre por</th>
                    <th>Última actividad</th>
                    <th>Usuario</th>
                    <th>Portal</th>
                    <th>IP / ubicación</th>
                    <th>Acción</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row):
                    $etype = (string) ($row['event_type'] ?? '');
                    $userShow = trim((string) ($row['usuario'] ?? ''));
                    if ($userShow === '') {
                        $userShow = trim((string) ($row['attempted_user'] ?? ''));
                    }
                    if ($userShow === '') {
                        $userShow = '—';
                    }
                    $uidShow = isset($row['uid']) && $row['uid'] !== null && (int) $row['uid'] > 0
                        ? 'UID #' . (int) $row['uid']
                        : '';
                    $tipoVal = isset($row['tipo']) && $row['tipo'] !== null ? (int) $row['tipo'] : null;
                    $sess = $sessionTypeLabel(isset($row['portal']) ? (string) $row['portal'] : null, $tipoVal);

                    $eventoId = (int) ($row['id'] ?? 0);
                    $estado = function_exists('cw_portal_login_log_session_state')
                        ? cw_portal_login_log_session_state($row)
                        : [
                            'code' => 'other',
                            'label' => '—',
                            'is_open' => false,
                            'is_closed' => false,
                            'is_live' => false,
                            'started' => '—',
                            'started_fmt' => '—',
                            'ended' => '—',
                            'ended_fmt' => '—',
                            'ended_note' => '',
                            'last_seen' => '—',
                            'last_seen_fmt' => '—',
                            'close_by' => '',
                            'close_label' => '',
                        ];

                    $badgeClass = $estadoBadgeClass($estado);
                    $startedRaw = trim((string) ($row['created_at'] ?? ''));

                    $sePuedeCerrar = $etype === 'success'
                        && !empty($estado['is_open'])
                        && $eventoId > 0
                        && $eventoId !== $cwAccOwnEventId;
                    $esPropia = $eventoId > 0 && $eventoId === $cwAccOwnEventId;
                    $closeByLabel = '—';
                    if (!empty($estado['close_label'])) {
                        $closeByLabel = $estado['close_label'];
                    } elseif ($etype === 'success' && !empty($estado['is_open'])) {
                        $closeByLabel = '—';
                    }
                ?>
                  <tr data-event-id="<?= $eventoId > 0 ? $eventoId : '' ?>">
                    <td class="cw-acc-col-estado">
                      <span class="<?= htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8') ?> cw-acc-estado-badge"><?= htmlspecialchars($estado['label'], ENT_QUOTES, 'UTF-8') ?></span>
                      <?php if ($estado['ended_note'] !== ''): ?>
                      <div class="cw-acc-muted small cw-acc-estado-note"><?= htmlspecialchars($estado['ended_note'], ENT_QUOTES, 'UTF-8') ?></div>
                      <?php else: ?>
                      <div class="cw-acc-muted small cw-acc-estado-note" style="display:none"></div>
                      <?php endif; ?>
                    </td>
                    <td class="cw-acc-mono cw-acc-col-inicio" data-order="<?= htmlspecialchars($startedRaw !== '' ? $startedRaw : '', ENT_QUOTES, 'UTF-8') ?>">
                      <?= htmlspecialchars($estado['started_fmt'], ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td class="cw-acc-mono cw-acc-col-cierre" data-order="<?= htmlspecialchars($estado['ended'] !== '—' ? $estado['ended'] : '', ENT_QUOTES, 'UTF-8') ?>">
                      <?= htmlspecialchars($estado['ended_fmt'], ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td class="cw-acc-col-cierre-por">
                      <?php if ($closeByLabel !== '—'): ?>
                      <span class="cw-acc-badge <?= $estado['close_by'] === 'admin' ? 'cw-acc-badge--revoked' : 'cw-acc-badge--closed' ?>"><?= htmlspecialchars($closeByLabel, ENT_QUOTES, 'UTF-8') ?></span>
                      <?php else: ?>
                      <span class="cw-acc-muted">—</span>
                      <?php endif; ?>
                    </td>
                    <td class="cw-acc-mono small cw-acc-col-actividad" data-order="<?= htmlspecialchars($estado['last_seen'] !== '—' ? $estado['last_seen'] : '', ENT_QUOTES, 'UTF-8') ?>">
                      <?= htmlspecialchars($estado['last_seen_fmt'], ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td>
                      <strong><?= htmlspecialchars($userShow, ENT_QUOTES, 'UTF-8') ?></strong>
                      <?php if ($uidShow !== ''): ?>
                      <div class="cw-acc-muted small"><?= htmlspecialchars($uidShow, ENT_QUOTES, 'UTF-8') ?></div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div class="cw-acc-sess">
                        <span class="cw-acc-sess-portal cw-acc-sess-portal--<?= htmlspecialchars($sess['portal_key'], ENT_QUOTES, 'UTF-8') ?>">
                          <?= htmlspecialchars($sess['portal'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <span class="cw-acc-sess-role"><?= htmlspecialchars($sess['role'], ENT_QUOTES, 'UTF-8') ?></span>
                      </div>
                    </td>
                    <td>
                      <div class="cw-acc-mono"><?= htmlspecialchars((string) ($row['ip'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></div>
                      <div class="cw-acc-muted small"><?= htmlspecialchars((string) (($row['geo_label'] ?? '') !== '' ? $row['geo_label'] : '—'), ENT_QUOTES, 'UTF-8') ?></div>
                      <?php
                      $lat = $row['gps_lat'] ?? null;
                      $lng = $row['gps_lng'] ?? null;
                      if ($lat !== null && $lng !== null && $lat !== '' && $lng !== ''):
                          $mapa = 'https://www.google.com/maps?q=' . urlencode($lat . ',' . $lng);
                          $prec = isset($row['gps_accuracy']) && $row['gps_accuracy'] !== null
                              ? ' ±' . (int) $row['gps_accuracy'] . ' m'
                              : '';
                      ?>
                      <a class="cw-acc-gps" href="<?= htmlspecialchars($mapa, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                        GPS<?= htmlspecialchars($prec, ENT_QUOTES, 'UTF-8') ?>
                      </a>
                      <?php endif; ?>
                    </td>
                    <td class="cw-acc-col-accion">
                      <?php if ($esPropia): ?>
                        <span class="cw-acc-self">Esta sesión</span>
                      <?php elseif ($sePuedeCerrar): ?>
                      <form method="post" action="" class="cw-acc-close-form" onsubmit="return confirm('¿Cerrar la sesión de <?= htmlspecialchars(addslashes($userShow), ENT_QUOTES, 'UTF-8') ?>? Perderá el acceso en su siguiente acción.');">
                        <input type="hidden" name="accion" value="cerrar_sesion">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($cwAccCsrf, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="event_id" value="<?= $eventoId ?>">
                        <input type="hidden" name="r_filter" value="<?= htmlspecialchars($filter, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="r_portal" value="<?= htmlspecialchars($portal, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="r_q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="cw-acc-kill">Cerrar sesión</button>
                      </form>
                      <?php elseif ($etype === 'success' && !empty($estado['is_closed'])): ?>
                      <div class="cw-acc-closed-info">
                        <strong>Ya cerrada</strong>
                        <span><?= htmlspecialchars($estado['ended_fmt'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if ($estado['close_label'] !== ''): ?>
                        <span class="cw-acc-muted"><?= htmlspecialchars($estado['close_label'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                      </div>
                      <?php else: ?>
                        <span class="cw-acc-muted small">—</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php endif; ?>
          </div>
        </section>
      </div>
      <div class="col-lg-3">
        <section class="cw-acc-panel">
          <div class="cw-acc-panel-h"><h2>IPs con más fallos (24 h)</h2></div>
          <div class="cw-acc-panel-b">
            <?php if ($topIps === []): ?>
              <div class="cw-acc-muted small">Sin fallos recientes.</div>
            <?php else: ?>
            <div class="cw-acc-ip-list">
              <?php foreach ($topIps as $ipRow):
                  $href = adm_href('seguridad/accesos.php') . $baseQs([
                      'filter' => 'fail',
                      'q' => $ipRow['ip'],
                      'portal' => '',
                  ]);
              ?>
              <a class="cw-acc-ip-row text-decoration-none text-reset" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>">
                <div>
                  <div class="cw-acc-mono"><?= htmlspecialchars($ipRow['ip'], ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="cw-acc-muted small"><?= htmlspecialchars($ipRow['geo_label'] !== '' ? $ipRow['geo_label'] : 'Sin geo', ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <strong><?= (int) $ipRow['c'] ?></strong>
              </a>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
        </section>
      </div>
    </div>
  </div>
</div>
</div><!-- menu container-fluid -->
</div><!-- menu content-wrapper -->

<script src="<?= adm_href('vendor/datatables/jquery.dataTables.min.js') ?>"></script>
<script src="<?= adm_href('vendor/datatables/dataTables.bootstrap4.min.js') ?>"></script>
<script>
(function ($) {
  'use strict';

  var pollUrl = <?= json_encode($cwAccPollUrl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var badgeMap = {
    live: 'cw-acc-badge cw-acc-badge--live',
    active: 'cw-acc-badge cw-acc-badge--active',
    idle: 'cw-acc-badge cw-acc-badge--idle',
    closed: 'cw-acc-badge cw-acc-badge--closed',
    fail: 'cw-acc-badge cw-acc-badge--fail',
    blocked: 'cw-acc-badge cw-acc-badge--block',
    other: 'cw-acc-badge cw-acc-badge--ok'
  };

  function esc(s) {
    return $('<div>').text(s == null ? '' : String(s)).html();
  }

  function updateKpis(stats) {
    if (!stats) return;
    Object.keys(stats).forEach(function (key) {
      $('[data-kpi="' + key + '"]').text(stats[key]);
    });
  }

  function renderCloseBy(s) {
    if (!s.close_label) {
      return '<span class="cw-acc-muted">—</span>';
    }
    var cls = s.close_by === 'admin' ? 'cw-acc-badge cw-acc-badge--revoked' : 'cw-acc-badge cw-acc-badge--closed';
    return '<span class="' + cls + '">' + esc(s.close_label) + '</span>';
  }

  function renderAction(s) {
    if (s.is_own) {
      return '<span class="cw-acc-self">Esta sesión</span>';
    }
    if (s.can_close) {
      return null;
    }
    if (s.is_closed) {
      var html = '<div class="cw-acc-closed-info"><strong>Ya cerrada</strong><span>' + esc(s.ended_fmt) + '</span>';
      if (s.close_label) {
        html += '<span class="cw-acc-muted">' + esc(s.close_label) + '</span>';
      }
      html += '</div>';
      return html;
    }
    return '<span class="cw-acc-muted small">—</span>';
  }

  function applySessionRow($tr, s) {
    var code = s.code || 'other';
    var $badge = $tr.find('.cw-acc-estado-badge');
    $badge.attr('class', (badgeMap[code] || badgeMap.other) + ' cw-acc-estado-badge').text(s.label || '—');

    var $note = $tr.find('.cw-acc-estado-note');
    if (s.ended_note) {
      $note.text(s.ended_note).show();
    } else {
      $note.hide().text('');
    }

    $tr.find('.cw-acc-col-cierre').text(s.ended_fmt || '—');
    $tr.find('.cw-acc-col-cierre-por').html(renderCloseBy(s));
    $tr.find('.cw-acc-col-actividad').text(s.last_seen_fmt || '—');

    var actionHtml = renderAction(s);
    var $action = $tr.find('.cw-acc-col-accion');
    if (actionHtml === null) {
      if (!$action.find('.cw-acc-close-form').length) {
        $action.html('<span class="cw-acc-muted small">—</span>');
      }
    } else {
      $action.html(actionHtml);
    }
  }

  function pollAccesos() {
    var ids = [];
    $('#cwAccTable tbody tr[data-event-id]').each(function () {
      var id = parseInt($(this).attr('data-event-id'), 10);
      if (id > 0) ids.push(id);
    });
    if (!ids.length) {
      $('#cwAccPollAt').text('Sin filas · ' + new Date().toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' }));
      return;
    }

    var url = pollUrl + (pollUrl.indexOf('?') >= 0 ? '&' : '?') + 'ids=' + encodeURIComponent(ids.join(','));
    fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (!data || !data.ok) return;
        updateKpis(data.stats);
        $('#cwAccPollAt').text('Actualizado ' + (data.at || ''));
        var sessions = data.sessions || {};
        Object.keys(sessions).forEach(function (id) {
          var $tr = $('#cwAccTable tbody tr[data-event-id="' + id + '"]');
          if ($tr.length) applySessionRow($tr, sessions[id]);
        });
      })
      .catch(function () {
        $('#cwAccPollAt').text('Error al actualizar');
      });
  }

  $(function () {
    if ($.fn.DataTable && $('#cwAccTable tbody tr').length) {
      $('#cwAccTable').DataTable({
        order: [[1, 'desc']],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Todos']],
        language: {
          search: 'Buscar:',
          lengthMenu: 'Mostrar _MENU_',
          info: '_START_–_END_ de _TOTAL_',
          infoEmpty: 'Sin registros',
          infoFiltered: '(filtrado de _MAX_)',
          zeroRecords: 'No hay coincidencias',
          paginate: { first: '«', last: '»', next: '›', previous: '‹' }
        },
        columnDefs: [
          { orderable: false, targets: [8] }
        ]
      });
    }

    pollAccesos();
    setInterval(pollAccesos, 30000);
  });
})(jQuery);
</script>
</body></html>
