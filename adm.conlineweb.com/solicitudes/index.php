<?php
// Módulo: solicitudes/index.php — NO reemplazar con el index.php del root del admin
require_once __DIR__ . '/../auth_middleware.php';
require_once __DIR__ . '/bootstrap_conexion.php';
require_once __DIR__ . '/helpers_agentes.php';
require_once __DIR__ . '/helpers_clientes.php';
?>
<?php
$__ag_opts = '';
try { $ra = $conexion->query("SELECT id,nombre FROM agentes WHERE idEmpresa IS NULL OR idEmpresa = '' ORDER BY nombre"); if($ra){ while($r=$ra->fetch_assoc()){ $__ag_opts .= '<option value="'.(int)$r['id'].'">'.htmlspecialchars($r['nombre']).'</option>'; } } } catch(Throwable $e){}

// Detectar columnas opcionales
$hasIdCliente = $conexion->query("SHOW COLUMNS FROM solicitudes LIKE 'id_cliente'")->num_rows > 0;
$hasFechaLim = $conexion->query("SHOW COLUMNS FROM solicitudes LIKE 'fecha_lim'")->num_rows > 0;
$hasIdProyecto = $conexion->query("SHOW COLUMNS FROM solicitudes LIKE 'id_proyecto'")->num_rows > 0;
$hasNotasTable = false;
try { if($chk=$conexion->query("SHOW TABLES LIKE 'solicitudes_notas'")){ $hasNotasTable = $chk->num_rows>0; $chk->close(); } } catch(Exception $e){}

$selectEmpresa = $hasIdCliente ? ", c.empresa AS empresa_cliente" : ", NULL AS empresa_cliente";
$joinCliente = $hasIdCliente ? " LEFT JOIN clientes c ON s.id_cliente = c.id " : "";
$selectProyecto = $hasIdProyecto ? ", p.nombre_proyecto AS nombre_proyecto" : ", NULL AS nombre_proyecto";
$joinProyecto = $hasIdProyecto ? " LEFT JOIN proyectos p ON s.id_proyecto = p.id_proyecto " : "";
$joinNotas = $hasNotasTable ? "LEFT JOIN (SELECT solicitud_id, COUNT(*) cnt FROM solicitudes_notas GROUP BY solicitud_id) sn ON sn.solicitud_id = s.id" : "";
$campoNotas = $hasNotasTable ? "COALESCE(sn.cnt,0) AS notas_count," : "0 AS notas_count,";

$clientesActivos = solicitudes_clientes_activos($conexion);
$idClienteUrl = isset($_GET['id_cliente']) ? (int) $_GET['id_cliente'] : 0;
if ($idClienteUrl > 0 && !solicitudes_cliente_es_activo($conexion, $idClienteUrl)) {
    $idClienteUrl = 0;
}

$solTipo = (int) ($_SESSION['tipo'] ?? 0);
$solUid = (int) ($_SESSION['uid'] ?? 0);
$solUserLabel = trim((string) ($_SESSION['nombre'] ?? ''));
if ($solUserLabel === '' && $solUid > 0) {
    try {
        $stU = $conexion->prepare('SELECT usuario FROM login WHERE id = ? LIMIT 1');
        if ($stU) {
            $stU->bind_param('i', $solUid);
            $stU->execute();
            $ru = $stU->get_result()->fetch_assoc();
            $stU->close();
            if ($ru) {
                $solUserLabel = (string) ($ru['usuario'] ?? '');
            }
        }
    } catch (Throwable $e) {
        // ignore
    }
}
if ($solUserLabel === '') {
    $solUserLabel = 'Usuario #' . $solUid;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Solicitudes · ConlineWeb</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth}
body{
  font-family:'Inter','Segoe UI',system-ui,-apple-system,sans-serif;
  background:#eef1f6;
  color:#1e293b;
  line-height:1.6;
  min-height:100vh;
}
body::before{
  content:'';display:block;height:4px;
  background:linear-gradient(90deg,#000147 0%,#4361ee 40%,#7c3aed 70%,#f72585 100%);
  position:fixed;top:0;left:0;right:0;z-index:9999;
}

/* ── HEADER MODERNO ── */
header{
  background:rgba(255,255,255,0.85);
  backdrop-filter:blur(20px) saturate(1.8);
  -webkit-backdrop-filter:blur(20px) saturate(1.8);
  margin:4px 0 0 0;padding:12px 28px;
  border-bottom:1px solid rgba(0,0,0,0.06);
  box-shadow:0 1px 8px rgba(0,0,0,0.03);
  position:sticky;top:0;z-index:500;
}
.app-header-top{display:flex;align-items:center;justify-content:space-between;gap:14px}
.brand-block{display:inline-flex;align-items:center;gap:12px}
.brand-logo{height:40px;width:auto;object-fit:contain}
.header-title-wrap{margin-top:10px;display:flex;justify-content:center;padding:0 12px}
.header-title-pill{
  text-align:center;
  background:linear-gradient(135deg,rgba(255,255,255,0.95),rgba(248,250,252,0.92));
  border:1px solid rgba(0,1,71,0.08);
  border-radius:18px;padding:12px 28px;
  max-width:720px;width:100%;
  box-shadow:0 4px 20px -6px rgba(0,0,0,0.06);
}
.brand-title{margin:0;color:#000147;font-size:1.55rem;font-weight:800;letter-spacing:-0.3px;line-height:1.15}
.brand-subtitle{margin-top:4px;color:#64748b;font-size:0.8rem;font-weight:600;letter-spacing:0.04em;text-transform:uppercase}

/* ── BOTONES ── */
.btn-primary{
  background:linear-gradient(135deg,#000147,#1a1a8a);
  color:#fff;border:none;padding:10px 22px;border-radius:12px;
  cursor:pointer;font-weight:600;font-size:0.9rem;
  display:inline-flex;align-items:center;gap:8px;
  transition:all .25s cubic-bezier(.4,0,.2,1);
  box-shadow:0 4px 14px rgba(0,1,71,0.2);
  white-space:nowrap;
}
.btn-primary:hover{
  transform:translateY(-3px);
  box-shadow:0 8px 25px rgba(0,1,71,0.3);
}
.btn-secondary{
  background:rgba(67,97,238,0.08);color:#4361ee;
  border:1px solid rgba(67,97,238,0.15);
  padding:8px 16px;border-radius:10px;cursor:pointer;
  font-weight:600;font-size:0.85rem;
  transition:all .2s;
}
.btn-secondary:hover{background:rgba(67,97,238,0.15);transform:translateY(-1px)}

.header-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.user-chip{
  display:inline-flex;align-items:center;gap:8px;
  padding:8px 12px;border-radius:999px;
  background:#f1f5f9;border:1px solid #e2e8f0;
  font-size:.82rem;font-weight:600;color:#0f172a;
}
.user-chip i{color:#000147}
.link-chip{
  display:inline-flex;align-items:center;gap:6px;
  padding:8px 12px;border-radius:10px;
  background:#fff;border:1px solid #e2e8f0;
  font-size:.82rem;font-weight:600;color:#334155;text-decoration:none;
}
.link-chip:hover{background:#f8fafc;color:#000147;text-decoration:none;border-color:#cbd5e1}

/* ── CONTAINER ── */
.container{max-width:1920px;margin:0 auto;padding:16px 12px 30px;width:100%}

/* ── LAYOUT PRINCIPAL ── */
.main-layout{display:flex;gap:22px;align-items:flex-start}

/* ── MENU LATERAL ── */
.estado-menu{
  width:230px;min-width:210px;
  display:flex;flex-direction:column;gap:8px;
  padding:16px;border-radius:16px;
  background:rgba(255,255,255,0.75);
  backdrop-filter:blur(16px);
  -webkit-backdrop-filter:blur(16px);
  border:1px solid rgba(255,255,255,0.6);
  box-shadow:0 8px 32px -8px rgba(0,0,0,0.06);
}
.estado-menu-top{position:sticky;top:90px}
.estado-menu-btn{
  width:100%;
  display:flex !important;
  justify-content:flex-start !important;
  align-items:center !important;
  gap:0;
  padding:12px 14px;
  border:1px solid transparent;border-radius:12px;
  background:rgba(255,255,255,0.5);
  color:#374151;font-weight:600;font-size:0.9rem;
  cursor:pointer;transition:all .2s ease;
  text-align:left !important;
}
.estado-menu-btn:hover{
  background:#fff;border-color:#e2e8f0;
  transform:translateX(3px);box-shadow:0 4px 12px rgba(0,0,0,0.04);
}
.estado-menu-btn.active{
  border-color:#000147;background:#fff;
  box-shadow:0 0 0 3px rgba(0,1,71,0.1),0 4px 12px rgba(0,1,71,0.06);
  color:#000147;
}
.estado-menu-label{
  display:inline-flex !important;
  align-items:center;
  justify-content:flex-start;
  gap:8px;
  font-size:0.85rem;
  flex:0 0 auto !important;
  width:max-content !important;
  max-width:100%;
  white-space:nowrap;
  margin:0 !important;
}
.estado-menu-text{line-height:1.2}
.estado-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.estado-menu-btn[data-status="Todos"] .estado-dot{background:linear-gradient(135deg,#6366f1,#8b5cf6)}
.estado-menu-btn[data-status="Pendiente"] .estado-dot{background:#f59e0b}
.estado-menu-btn[data-status="En Proceso"] .estado-dot{background:#3b82f6}
.estado-menu-btn[data-status="Finalizado"] .estado-dot{background:#10b981}
.estado-count{
  display:inline-flex !important;
  align-items:center;
  justify-content:center;
  background:rgba(0,1,71,0.08);color:#000147;
  border-radius:20px;font-size:0.7rem;font-weight:700;
  padding:2px 8px;min-width:22px;
  margin:0 0 0 2px !important;
  flex:0 0 auto !important;
  position:static !important;
  float:none !important;
  transition:all .2s;
}
.estado-menu-btn.active .estado-count{background:#000147;color:#fff}
.estado-menu-actions{margin-top:8px;padding-top:10px;border-top:1px dashed #d8deea}
.logout-side-btn{
  width:100%;display:inline-flex;align-items:center;justify-content:center;
  gap:8px;padding:9px 10px;border-radius:12px;
  border:1px solid #d7deea;background:#fff;
  color:#000147;text-decoration:none;font-weight:700;font-size:0.84rem;
  transition:all .2s ease;
}
.logout-side-btn:hover{border-color:#f72585;background:#fff0f6;color:#b5179e}
.logout-side-btn img{width:16px;height:16px;filter:brightness(0) saturate(100%) invert(8%) sepia(94%) saturate(5276%) hue-rotate(243deg) brightness(77%) contrast(128%)}

/* ── CONTENIDO ── */
.content-stack{flex:1;min-width:0}

/* ── CARD ── */
.card{
  background:#fff;border-radius:16px;
  box-shadow:0 1px 3px rgba(0,0,0,0.03),0 4px 20px rgba(0,0,0,0.04);
  border:1px solid #e9ecef;padding:22px;margin-bottom:20px;
}
.card h2{font-size:1.1rem;color:#000147;font-weight:700;margin-bottom:14px;display:flex;align-items:center;gap:8px}

/* ── FILTROS ── */
.filters{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px}
.filter-select{
  padding:8px 12px;border-radius:10px;border:1px solid #e2e8f0;
  flex:1;min-width:130px;font-size:0.82rem;
  background:#f8fafc;color:#374151;
  transition:all .15s;
}
.filter-select:focus{outline:none;border-color:#4361ee;background:#fff;box-shadow:0 0 0 3px rgba(67,97,238,0.08)}
.date-filters{display:flex;gap:6px;align-items:center;min-width:240px}
.date-filters input[type="date"]{
  padding:7px 10px;border-radius:10px;border:1px solid #e2e8f0;
  background:#f8fafc;font-size:0.82rem;
}
.btn-clear-dates{background:#f1f5f9;border:1px solid #e2e8f0;padding:7px 10px;border-radius:10px;cursor:pointer;color:#0f172a;font-size:0.8rem}

/* ── STATS ── */
.stats-panel{display:flex;gap:10px;flex-wrap:wrap;margin-top:10px}
.stat-box{
  position:relative;flex:1;min-width:130px;
  background:#fff;border:1px solid #e9ecef;border-radius:14px;
  padding:16px 18px 16px 22px;
  text-align:left;cursor:pointer;user-select:none;
  display:flex;align-items:center;gap:12px;
  transition:transform .2s,box-shadow .2s;
  opacity:0;animation:statIn .4s cubic-bezier(.22,.68,0,1.2) forwards;
}
.stat-box:nth-child(1){animation-delay:.04s}
.stat-box:nth-child(2){animation-delay:.1s}
.stat-box:nth-child(3){animation-delay:.16s}
.stat-box:nth-child(4){animation-delay:.22s}
.stat-box:nth-child(5){animation-delay:.28s}
@keyframes statIn{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
.stat-box::before{content:'';position:absolute;top:0;left:0;width:4px;height:100%;border-radius:14px 0 0 14px}
.stat-box:hover{transform:translateY(-3px);box-shadow:0 8px 20px rgba(0,0,0,0.07)}
.stat-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.stat-content{flex:1;min-width:0}
.stat-content .num{font-size:1.6rem;font-weight:800;line-height:1;letter-spacing:-0.5px}
.stat-content .label{font-size:0.75rem;color:#64748b;margin-top:3px;font-weight:600;text-transform:uppercase;letter-spacing:0.3px}
.stat-total::before{background:#64748b}.stat-total .stat-icon{background:#f1f5f9;color:#64748b}.stat-total .num{color:#334155}
.stat-pendiente::before{background:#f59e0b}.stat-pendiente .stat-icon{background:#fffbeb;color:#d97706}.stat-pendiente .num{color:#92400e}
.stat-proceso::before{background:#3b82f6}.stat-proceso .stat-icon{background:#eff6ff;color:#2563eb}.stat-proceso .num{color:#1e40af}
.stat-finalizado::before{background:#10b981}.stat-finalizado .stat-icon{background:#ecfdf5;color:#059669}.stat-finalizado .num{color:#065f46}
.stat-todos::before{background:linear-gradient(135deg,#6366f1,#8b5cf6)}.stat-todos .stat-icon{background:#eef2ff;color:#6366f1}.stat-todos .num{color:#4338ca}
.stats-progress-bar{height:6px;border-radius:3px;overflow:hidden;background:#e9ecef;margin-top:12px;display:flex;gap:2px}
.stats-progress-bar div{height:100%;transition:width .5s cubic-bezier(.4,0,.2,1);border-radius:3px;min-width:0}
.bar-pend{background:linear-gradient(90deg,#fbbf24,#f59e0b)}
.bar-proc{background:linear-gradient(90deg,#60a5fa,#3b82f6)}
.bar-fin{background:linear-gradient(90deg,#34d399,#10b981)}
.stat-box.stat-active{border-color:#000147!important;box-shadow:0 0 0 3px rgba(0,1,71,0.1)!important;background:#f8faff!important}

/* ── TABLA ── */
.table-responsive{width:100%;overflow-x:auto;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.04)}
th{
  font-size:0.72rem;letter-spacing:0.5px;text-transform:uppercase;
  padding:10px 10px;background:#000147;color:#fff;font-weight:700;
  position:sticky;top:0;
}
td{padding:10px 10px;font-size:0.85rem;border-bottom:1px solid #f1f3f5}
tr:hover{background:rgba(67,97,238,0.04)}
tr.row-overdue td:first-child{border-left:4px solid #f72585}
tr.row-overdue{background:linear-gradient(90deg,rgba(247,37,133,0.05),transparent 60%)!important}
tr.row-due-today td:first-child{border-left:4px solid #f59e0b}
tr.row-due-today{background:linear-gradient(90deg,rgba(245,158,11,0.05),transparent 60%)!important}

/* ── BADGES ── */
.badge{padding:4px 10px;border-radius:20px;font-size:0.75rem;font-weight:600;display:inline-block;text-align:center;min-width:65px}
.badge-pendiente{background:#fffbeb;color:#92400e;border:1px solid rgba(245,158,11,0.15)}
.badge-proceso{background:#eff6ff;color:#1e40af;border:1px solid rgba(59,130,246,0.15)}
.badge-finalizado{background:#ecfdf5;color:#065f46;border:1px solid rgba(16,185,129,0.15)}
.badge-alta{background:linear-gradient(135deg,#fde8ea,#fecdd3);color:#be123c;border:1px solid rgba(190,18,60,0.15);font-weight:700}
.badge-media{background:linear-gradient(135deg,#fef9c3,#fde68a);color:#b45309;border:1px solid rgba(180,83,9,0.15);font-weight:700}
.badge-baja{background:linear-gradient(135deg,#dcfce7,#bbf7d0);color:#166534;border:1px solid rgba(22,101,52,0.15);font-weight:700}
.badge-vencido{
  display:inline-flex;align-items:center;gap:3px;
  background:linear-gradient(135deg,#f72585,#b5179e);color:#fff;
  font-size:0.65rem;padding:2px 8px;border-radius:20px;font-weight:700;
  animation:pulse-badge 2s ease-in-out infinite;
}
.badge-hoy{
  display:inline-flex;align-items:center;gap:3px;
  background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;
  font-size:0.65rem;padding:2px 8px;border-radius:20px;font-weight:700;
}
@keyframes pulse-badge{0%,100%{opacity:1}50%{opacity:.65}}
.badge-freq{display:inline-flex;align-items:center;padding:3px 9px;border-radius:999px;font-size:0.72rem;font-weight:700}
.badge-freq-unica{background:#f1f5f9;color:#475569;border:1px solid #e2e8f0}
.badge-freq-diaria{background:#ecfdf5;color:#047857;border:1px solid #a7f3d0}
.badge-freq-semanal{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe}
.badge-freq-mensual{background:#f5f3ff;color:#6d28d9;border:1px solid #ddd6fe}

/* ── TABS FRECUENCIA ── */
.freq-tabs{
  display:flex;flex-wrap:wrap;gap:8px;margin:0 0 14px;padding:4px;
  background:#f1f5f9;border:1px solid #e2e8f0;border-radius:12px;
}
.freq-tab{
  appearance:none;border:1px solid transparent;background:transparent;
  color:#64748b;font-size:0.82rem;font-weight:700;cursor:pointer;
  padding:8px 14px;border-radius:9px;display:inline-flex;align-items:center;gap:8px;
  transition:all .18s ease;font-family:inherit;
}
.freq-tab:hover{background:#fff;color:#0f172a;border-color:#e2e8f0}
.freq-tab.active{
  background:#fff;color:#000147;border-color:#c7d2fe;
  box-shadow:0 2px 8px rgba(15,23,42,0.06);
}
.freq-tab-count{
  min-width:20px;padding:1px 7px;border-radius:999px;font-size:0.7rem;
  background:#e2e8f0;color:#334155;font-weight:800;text-align:center;
}
.freq-tab.active .freq-tab-count{background:#000147;color:#fff}
.freq-tab[data-freq="0"].active{border-color:#cbd5e1}
.freq-tab[data-freq="1"].active{border-color:#6ee7b7;color:#047857}
.freq-tab[data-freq="1"].active .freq-tab-count{background:#047857}
.freq-tab[data-freq="2"].active{border-color:#93c5fd;color:#1d4ed8}
.freq-tab[data-freq="2"].active .freq-tab-count{background:#1d4ed8}
.freq-tab[data-freq="3"].active{border-color:#c4b5fd;color:#6d28d9}
.freq-tab[data-freq="3"].active .freq-tab-count{background:#6d28d9}

/* ── ACCIONES ── */
.action-btn{background:none;border:none;cursor:pointer;width:30px;height:30px;border-radius:8px;transition:all .15s;font-size:0.85rem;display:inline-flex;align-items:center;justify-content:center;margin:0 1px}
.action-btn:hover{background:#f1f3f8}
.btn-edit:hover{background:rgba(72,149,239,0.12)!important;color:#2563eb}
.btn-info:hover{background:rgba(67,97,238,0.12)!important;color:#4361ee}
.btn-notas:hover{background:rgba(0,1,71,0.08)!important;color:#000147}
.btn-txt:hover{background:rgba(76,201,240,0.12)!important;color:#0891b2}
.btn-delete:hover{background:rgba(247,37,133,0.1)!important;color:#f72585}
.action-btn.btn-notas{position:relative;display:inline-flex;align-items:center;gap:4px}
.notes-count{display:inline-block;min-width:16px;padding:2px 5px;border-radius:10px;font-size:.55rem;font-weight:700;line-height:1;background:#e2e8f0;color:#334155;text-align:center}

/* ── SELECCIÓN MÚLTIPLE ── */
.ticket-checkbox{width:18px;height:18px;cursor:pointer;accent-color:#4361ee}
.bulk-actions{display:none;padding:12px 16px;background:#f8fafc;border-radius:12px;margin-bottom:12px;border:1px solid #e2e8f0;align-items:center;gap:10px;flex-wrap:wrap}
.bulk-actions.active{display:flex}
.bulk-actions-label{font-weight:600;color:#000147;font-size:0.85rem}
.btn-danger{background:linear-gradient(135deg,#f72585,#b5179e);color:#fff;border:none;padding:8px 16px;border-radius:10px;cursor:pointer;font-weight:600;font-size:0.85rem;transition:all .2s;display:inline-flex;align-items:center;gap:6px}
.btn-danger:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(247,37,133,0.3)}
.btn-danger:disabled{opacity:0.5;cursor:not-allowed;transform:none}
.btn-select-all{background:#4361ee;color:#fff;border:none;padding:6px 12px;border-radius:8px;cursor:pointer;font-weight:600;font-size:0.8rem;transition:all .2s}
.btn-select-all:hover{background:#3a0ca3;transform:translateY(-1px)}
.btn-select-none{background:#6c757d;color:#fff;border:none;padding:6px 12px;border-radius:8px;cursor:pointer;font-weight:600;font-size:0.8rem;transition:all .2s}
.btn-select-none:hover{background:#5a6268;transform:translateY(-1px)}
.notes-count.has{background:#000147;color:#fff}
.notes-count.empty{opacity:.5}

/* ── ESTADO SELECT ── */
.estado-form{display:inline-block;min-width:132px;max-width:100%;margin:0}
.estado-select{
  padding:6px 28px 6px 10px;border-radius:8px;border:1px solid #e2e8f0;
  width:auto;min-width:132px;max-width:100%;
  cursor:pointer;font-size:0.8rem;font-weight:600;
  white-space:nowrap;box-sizing:border-box;
  appearance:auto;-webkit-appearance:menulist;
}
select.estado-select[data-val="Pendiente"]{border-color:#f59e0b;color:#92400e;background:#fffbeb}
select.estado-select[data-val="En Proceso"]{border-color:#3b82f6;color:#1e40af;background:#eff6ff}
select.estado-select[data-val="Finalizado"]{border-color:#10b981;color:#064e3b;background:#ecfdf5}

/* ── AGENTE ── */
.assign-agent-select{padding:3px 5px;font-size:0.7rem;border:1px solid #cbd5e1;border-radius:6px;background:#f8fafc;cursor:pointer;max-width:130px}
.assign-agent-select:disabled{opacity:.6;cursor:not-allowed}
.agente-badge{display:inline-flex;align-items:center;gap:4px;background:linear-gradient(135deg,#4361ee,#7c3aed);color:#fff;padding:3px 8px;border-radius:16px;font-size:0.7rem;font-weight:600;margin:1px;white-space:nowrap;box-shadow:0 2px 4px rgba(67,97,238,0.15)}

/* ── MODAL ── */
#modalSolicitud.modal-overlay{padding:8px}
.modal-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.45);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;z-index:1000;padding:15px;opacity:0;visibility:hidden;transition:all .3s}
.modal-overlay.active{opacity:1;visibility:visible}
.modal{background:#fff;border-radius:16px;width:100%;max-width:95%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.15);transform:translateY(30px);transition:transform .4s cubic-bezier(.22,.68,0,1)}
.modal-overlay.active .modal{transform:translateY(0)}
.modal-header{padding:16px 20px;border-bottom:1px solid #f1f3f5;display:flex;justify-content:space-between;align-items:center}
.modal-title{font-size:1.2rem;color:#000147;font-weight:700}
.modal-close{background:none;border:none;font-size:1.5rem;cursor:pointer;color:#94a3b8;transition:color .2s}
.modal-close:hover{color:#1e293b}
.modal-body{padding:20px}
.modal-footer{padding:14px 20px;border-top:1px solid #f1f3f5;display:flex;justify-content:flex-end;gap:8px}
.form-group{margin-bottom:14px}
.form-label{display:block;margin-bottom:5px;font-weight:600;color:#1e293b;font-size:0.85rem}
.form-control{width:100%;padding:9px 12px;border:1px solid #e2e8f0;border-radius:10px;font-size:0.85rem;transition:border-color .2s,box-shadow .2s}
.form-control:focus{outline:none;border-color:#4361ee;box-shadow:0 0 0 3px rgba(67,97,238,0.12)}
textarea.form-control{min-height:90px;resize:vertical}
.btn{padding:8px 18px;border-radius:10px;border:none;cursor:pointer;font-weight:600;transition:all .2s;font-size:0.85rem}
.btn-cancel{background:#f1f3f5;color:#374151}
.btn-cancel:hover{background:#e2e8f0}
.btn-submit{background:#000147;color:#fff}
.btn-submit:hover{background:#1a1a8a;transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,1,71,0.2)}
.modal-descripcion{max-width:700px}
.modal-descripcion .modal-body{max-height:60vh;overflow-y:auto}
.modal-notas{max-width:95%}
.modal-notas .modal-header{padding:12px 16px}
.modal-notas .modal-title{font-size:1.1rem}
.modal-notas .modal-body{padding:14px}
.modal-notas .btn{padding:6px 12px;font-size:0.85rem}
.modal-solicitud{
  max-width:min(1580px,calc(100vw - 16px));
  width:min(1580px,calc(100vw - 16px));
  height:96vh;
  max-height:96vh;
  display:flex;
  flex-direction:column;
  overflow:hidden;
}
.modal-solicitud .modal-header{flex-shrink:0}
.modal-solicitud #formSolicitud{
  display:flex;
  flex-direction:column;
  flex:1;
  min-height:0;
  overflow:hidden;
}
.modal-solicitud .modal-footer{flex-shrink:0;margin-top:auto}
.modal-solicitud .asistente-contexto-wrap{flex-shrink:0}
.modal-solicitud .solicitud-tabs{flex-shrink:0}
.modal-solicitud .solicitud-tab-panel{
  flex:1;
  min-height:0;
  overflow:auto;
  display:none;
}
.modal-solicitud .solicitud-tab-panel.active{display:flex;flex-direction:column}
.modal-solicitud .solicitud-tab-panel#panelFormulario.active{overflow-y:auto}
.solicitud-tabs{display:flex;gap:0;border-bottom:1px solid #e8ecf2;background:#f8fafc;padding:0 16px}
.solicitud-tab{
  padding:12px 18px;border:none;background:transparent;cursor:pointer;
  font-weight:600;font-size:0.88rem;color:#64748b;border-bottom:3px solid transparent;
  display:inline-flex;align-items:center;gap:8px;transition:all .2s;
}
.solicitud-tab:hover{color:#000147;background:rgba(67,97,238,0.04)}
.solicitud-tab.active{color:#000147;border-bottom-color:#4361ee;background:#fff}
.solicitud-tab-panel{display:none;padding:20px}
.solicitud-tab-panel.active{display:block}
.asistente-layout{
  display:grid;
  grid-template-columns:1.15fr 0.85fr;
  gap:16px;
  flex:1;
  min-height:min(560px,calc(96vh - 300px));
}
.asistente-chat-wrap{display:flex;flex-direction:column;border:1px solid #e2e8f0;border-radius:12px;background:#fff;overflow:hidden;min-height:0;height:100%}
.asistente-chat-header{padding:10px 14px;background:linear-gradient(135deg,#000147,#1a1a8a);color:#fff;font-weight:600;font-size:0.85rem;display:flex;align-items:center;gap:8px;flex-shrink:0}
.asistente-chat-messages{flex:1;overflow-y:auto;padding:14px;min-height:180px;background:#f8fafc}
.chat-bubble{max-width:92%;padding:10px 12px;border-radius:12px;margin-bottom:10px;font-size:0.86rem;line-height:1.45;word-break:break-word}
.chat-bubble.user{margin-left:auto;background:#4361ee;color:#fff;border-bottom-right-radius:4px}
.chat-bubble.assistant{background:#fff;border:1px solid #e2e8f0;color:#1e293b;border-bottom-left-radius:4px}
.chat-bubble.assistant strong{color:#000147}
.asistente-chat-input{display:flex;gap:8px;padding:12px;border-top:1px solid #e2e8f0;background:#fff;align-items:flex-end;flex-shrink:0}
.asistente-chat-input textarea,#chatInput{
  flex:1;
  min-height:130px;
  max-height:280px;
  height:130px;
  resize:vertical;
  font-size:0.92rem;
  line-height:1.55;
}
.asistente-chat-input .btn-asistente,.asistente-chat-input .btn-mic-audio{align-self:flex-end;margin-bottom:2px}
.asistente-chat-toolbar{display:flex;flex-wrap:wrap;align-items:center;gap:8px;padding:10px 12px;border-top:1px solid #e2e8f0;background:#f8fafc;flex-shrink:0}
.asistente-chat-toolbar .btn-add-image{margin:0;background:#fff;border-color:#c7d2fe;color:#4361ee;font-weight:600}
.asistente-chat-toolbar .btn-add-image:hover{background:#eef0ff}
.chat-attach-hint{color:#64748b;font-size:0.78rem;flex:1;min-width:180px}
.asistente-chat-attachments{padding:0 12px 8px;background:#f8fafc;flex-shrink:0}
.asistente-chat-attachments .image-preview-container{margin:0}
.chat-bubble-images{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}
.chat-bubble-thumb{width:72px;height:72px;object-fit:cover;border-radius:8px;cursor:pointer;border:2px solid rgba(255,255,255,.35)}
.chat-bubble.assistant .chat-bubble-thumb{border-color:#e2e8f0}
.chat-bubble-img-label{font-size:0.72rem;opacity:.85;margin-top:6px}
.btn-mic-audio{background:#fff;color:#4361ee;border:1px solid #c7d2fe;padding:8px 12px;border-radius:10px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;min-width:42px;transition:all .2s}
.btn-mic-audio:hover{background:#eef0ff}
.btn-mic-audio.recording{background:#fee2e2;color:#dc2626;border-color:#fca5a5;animation:micPulse 1s infinite}
.btn-mic-audio.processing{opacity:.6;cursor:wait}
@keyframes micPulse{0%,100%{box-shadow:0 0 0 0 rgba(220,38,38,.35)}50%{box-shadow:0 0 0 8px rgba(220,38,38,0)}}
.voz-estado{font-size:0.75rem;color:#64748b;padding:0 12px 8px;display:none;flex-shrink:0}
.voz-estado.active{display:block}
.voz-estado.recording{color:#dc2626;font-weight:600}
.input-con-voz{display:flex;gap:8px;align-items:flex-start}
.input-con-voz textarea,.input-con-voz .form-control{flex:1}
#descripcionTextarea{min-height:160px;font-size:0.92rem;line-height:1.55}
.asistente-actions{flex-shrink:0}
.asistente-preview{border:1px solid #e2e8f0;border-radius:12px;background:#fff;display:flex;flex-direction:column;overflow:hidden;min-height:0;height:100%}
.asistente-preview-header{padding:10px 14px;background:#f1f5f9;border-bottom:1px solid #e2e8f0;font-weight:700;font-size:0.85rem;color:#000147;flex-shrink:0}
.asistente-preview-body{flex:1;overflow-y:auto;padding:14px;min-height:180px;font-size:0.84rem;line-height:1.5}
.preview-section{margin-bottom:14px}
.preview-section h4{font-size:0.78rem;text-transform:uppercase;letter-spacing:.04em;color:#64748b;margin-bottom:6px}
.preview-empty{color:#94a3b8;font-style:italic;text-align:center;padding:40px 16px}
.preview-rf{margin-bottom:8px;padding:8px 10px;background:#f8fafc;border-radius:8px;border-left:3px solid #4361ee}
.preview-rf-id{font-weight:700;color:#4361ee;font-size:0.75rem}
.preview-badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:0.72rem;font-weight:700;margin-left:6px}
.preview-badge.ready{background:#d1fae5;color:#065f46}
.preview-badge.pending{background:#fef3c7;color:#92400e}
.asistente-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
.btn-asistente{background:linear-gradient(135deg,#4361ee,#7c3aed);color:#fff;border:none;padding:8px 14px;border-radius:10px;font-weight:600;font-size:0.82rem;cursor:pointer;display:inline-flex;align-items:center;gap:6px}
.btn-asistente:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(67,97,238,0.25)}
.btn-asistente:disabled{opacity:.5;cursor:not-allowed;transform:none;box-shadow:none}
.btn-asistente-outline{background:#fff;color:#4361ee;border:1px solid #c7d2fe}
.chat-typing{display:flex;gap:4px;padding:8px 12px}
.chat-typing span{width:7px;height:7px;background:#94a3b8;border-radius:50%;animation:chatDot 1.2s infinite}
.chat-typing span:nth-child(2){animation-delay:.2s}
.chat-typing span:nth-child(3){animation-delay:.4s}
@keyframes chatDot{0%,80%,100%{opacity:.3;transform:scale(.8)}40%{opacity:1;transform:scale(1)}}
.asistente-contexto-wrap{margin:16px 16px 0;padding:12px 14px;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc}
.asistente-contexto-wrap .form-label{font-size:0.8rem;margin-bottom:4px}
.asistente-contexto-resumen{margin-top:10px;padding:10px 12px;background:#fff;border:1px solid #e2e8f0;border-radius:10px;font-size:0.8rem;line-height:1.45;color:#475569;max-height:120px;overflow-y:auto}
.asistente-contexto-resumen strong{color:#000147}
.asistente-contexto-resumen .ctx-tag{display:inline-block;background:#eef0ff;color:#3730a3;padding:2px 8px;border-radius:12px;font-size:0.72rem;margin:2px 4px 2px 0}
.asistente-contexto-vacio{color:#94a3b8;font-style:italic}
.ctx-mode-row{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px;padding-bottom:12px;border-bottom:1px dashed #e2e8f0}
.ctx-mode-label{font-size:0.82rem;color:#64748b;flex:1;min-width:200px}
.ctx-switch{display:inline-flex;align-items:center;gap:10px;cursor:pointer;user-select:none}
.ctx-switch input{position:absolute;opacity:0;width:0;height:0}
.ctx-switch-ui{width:46px;height:26px;background:#cbd5e1;border-radius:999px;position:relative;transition:background .2s;flex-shrink:0}
.ctx-switch-ui::after{content:'';position:absolute;top:3px;left:3px;width:20px;height:20px;background:#fff;border-radius:50%;transition:transform .2s;box-shadow:0 1px 3px rgba(0,0,0,.15)}
.ctx-switch input:checked+.ctx-switch-ui{background:#4361ee}
.ctx-switch input:checked+.ctx-switch-ui::after{transform:translateX(20px)}
.ctx-switch-text{font-weight:600;font-size:0.84rem;color:#1e293b}
@media(max-width:900px){
  .modal-solicitud{max-width:100%;max-height:98vh}
  .asistente-layout{grid-template-columns:1fr;min-height:auto}
  .asistente-chat-messages,.asistente-preview-body{min-height:140px}
  .asistente-chat-input textarea,#chatInput{min-height:100px;height:100px}
}
.descripcion-completa{white-space:pre-wrap;word-break:break-word;margin-bottom:12px;line-height:1.45;font-size:0.92rem}
.descripcion-completa p{margin:0 0 6px}
.descripcion-completa ul{margin:0 0 6px 20px;padding-left:16px}
.descripcion-completa li{margin:0 0 3px}
.content-images{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}
.content-image-thumb{width:70px;height:70px;object-fit:cover;border-radius:6px;cursor:pointer;transition:transform .2s}
.content-image-thumb:hover{transform:scale(1.08)}
.content-files{margin-top:8px}
.content-file-item{display:flex;align-items:center;gap:6px;margin-bottom:4px}
.content-file-item a{color:#0d6efd;text-decoration:none;word-break:break-all}
.content-file-item a:hover{text-decoration:underline}
.image-upload-container{margin-top:8px;border:1px dashed #d1d5db;padding:10px;border-radius:8px;background:#f9fafb}
.image-preview-container{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}
.image-preview{position:relative;width:80px;height:80px;border:1px solid #ddd;border-radius:6px;overflow:hidden}
.image-preview img{width:100%;height:100%;object-fit:cover}
.image-preview .remove-image{position:absolute;top:2px;right:2px;background:rgba(255,255,255,.85);border-radius:50%;width:18px;height:18px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:11px;font-weight:700}
.btn-add-image{background:#e9ecef;border:1px solid #ced4da;padding:5px 10px;border-radius:6px;cursor:pointer;margin-top:8px;display:inline-flex;align-items:center;gap:4px;font-size:0.8rem}

/* ── AGENTES CHECKBOX ── */
select[multiple].form-control{min-height:120px;padding:8px;background:#f8fafc;border:2px solid #e2e8f0;border-radius:8px}
select[multiple].form-control option{padding:6px 10px;margin:2px 0;border-radius:4px;cursor:pointer}
select[multiple].form-control option:checked{background:linear-gradient(90deg,#4361ee,#7c3aed);color:#fff;font-weight:600}
#agentesContainer{max-height:240px;overflow-y:auto}
#agentesContainer::-webkit-scrollbar{width:6px}
#agentesContainer::-webkit-scrollbar-track{background:#f1f1f1;border-radius:3px}
#agentesContainer::-webkit-scrollbar-thumb{background:#4361ee;border-radius:3px}
.agente-checkbox-item{animation:fadeSlide .25s ease forwards}
@keyframes fadeSlide{from{opacity:0;transform:translateX(-8px)}to{opacity:1;transform:translateX(0)}}

/* ── LIGHTBOX ── */
.lightbox-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.85);display:flex;justify-content:center;align-items:center;z-index:2000;opacity:0;visibility:hidden;transition:all .3s}
.lightbox-overlay.active{opacity:1;visibility:visible}
.lightbox-content{max-width:90%;max-height:90%;position:relative}
.lightbox-content img{max-width:100%;max-height:100%;border-radius:8px}
.lightbox-close{position:absolute;top:-40px;right:0;color:#fff;font-size:30px;cursor:pointer;background:none;border:none}
.lightbox-nav{position:absolute;top:50%;width:100%;display:flex;justify-content:space-between;transform:translateY(-50%);padding:0 20px}
.lightbox-prev,.lightbox-next{color:#fff;font-size:28px;cursor:pointer;background:rgba(0,0,0,.5);width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;transition:background .2s}
.lightbox-prev:hover,.lightbox-next:hover{background:rgba(0,0,0,.7)}
#lightboxCounter{position:absolute;bottom:-35px;left:50%;transform:translateX(-50%);color:rgba(255,255,255,.7);font-size:14px}

/* ── DATATABLES ── */
.dataTables_wrapper .dataTables_filter input,.dataTables_wrapper .dataTables_length select{border:1px solid #e2e8f0!important;border-radius:8px!important;padding:5px 10px!important;background:#f8fafc!important;font-size:0.82rem!important}
.dataTables_wrapper .dataTables_filter input:focus,.dataTables_wrapper .dataTables_length select:focus{outline:none!important;border-color:#4361ee!important;box-shadow:0 0 0 3px rgba(67,97,238,.1)!important;background:#fff!important}
.dataTables_paginate .paginate_button{border-radius:8px!important;border:1px solid #e2e8f0!important;color:#374151!important;font-size:0.8rem;margin:0 2px!important;padding:5px 10px!important}
.dataTables_paginate .paginate_button.current,.dataTables_paginate .paginate_button.current:hover{background:#000147!important;border-color:#000147!important;color:#fff!important}
.dataTables_paginate .paginate_button:hover:not(.current){background:#f1f3f8!important;border-color:#d1d5db!important;color:#000147!important}
.dt-buttons .dt-button{background:#000147;color:#fff;border:none;padding:7px 14px;border-radius:8px;cursor:pointer;font-weight:600;font-size:0.8rem;transition:all .2s;margin-right:4px}
.dt-buttons .dt-button:hover{background:#1a1a8a;transform:translateY(-1px)}

/* ── RESPONSIVE ── */
@media(max-width:980px){
  .main-layout{flex-direction:column}
  .estado-menu{width:100%;min-width:0;display:grid;grid-template-columns:repeat(4,minmax(0,1fr))}
  .estado-menu-top{position:static}
  .estado-menu-btn{padding:8px 10px;justify-content:flex-start !important}
  .estado-menu-label{font-size:0.78rem;width:max-content !important}
  .estado-menu-actions{grid-column:span 4}
  .freq-tabs{gap:6px}
  .freq-tab{padding:7px 10px;font-size:0.75rem}
}
@media(max-width:768px){
  .container{padding:10px 6px}
  header{padding:10px 12px}
  .brand-title{font-size:1.4rem}
  .header-title-pill{padding:10px 16px}
  .filters{flex-direction:column}
  .filter-select{width:100%}
  .stat-box{min-width:100px}
  .stats-panel{grid-template-columns:repeat(2,1fr)}
  .estado-menu{grid-template-columns:repeat(2,1fr)}
  .estado-menu-actions{grid-column:span 2}
  .modal-body{padding:14px}
  .modal-footer{flex-direction:column}
  .modal-footer .btn{width:100%}
}
@media(max-width:576px){
  th,td{padding:6px 5px;font-size:0.75rem}
  .badge{font-size:0.7rem;min-width:55px;padding:3px 6px}
  .action-btn{width:26px;height:26px;font-size:0.75rem}
  .stats-panel{grid-template-columns:1fr 1fr}
  .stat-box{min-width:0;padding:12px}
  .stat-content .num{font-size:1.3rem}
}
</style>
<link rel="stylesheet" href="css/ticket_desc_document.css?v=17">
</head>
<body>
<header>
  <div class="app-header-top">
    <div class="brand-block">
      <img src="../images/logo.png" alt="ConlineWeb" class="brand-logo">
    </div>
    <div class="header-actions">
      <span class="user-chip" title="Sesión actual"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($solUserLabel, ENT_QUOTES, 'UTF-8') ?></span>
      <?php if ($solTipo === 1): ?>
      <a class="link-chip" href="../index.php" title="Volver al panel admin"><i class="fas fa-th-large"></i> Admin</a>
      <?php endif; ?>
      <button class="btn-primary" id="btnNuevaSolicitud">
        <i class="fas fa-plus"></i> Nueva solicitud
      </button>
    </div>
  </div>
</header>

<div class="header-title-wrap">
  <div class="header-title-pill">
    <h1 class="brand-title">Gestión de solicitudes</h1>
    <div class="brand-subtitle">Tickets · Asignación · Seguimiento</div>
  </div>
</div>

<div class="container">
  <div class="main-layout">
    <!-- MENU LATERAL -->
    <aside class="estado-menu estado-menu-top">
      <button type="button" class="estado-menu-btn" id="menuTodos" data-status="Todos">
        <span class="estado-menu-label">
          <span class="estado-dot"></span>
          <span class="estado-menu-text">Todos</span>
          <span class="estado-count" id="menuCountAll">0</span>
        </span>
      </button>
      <button type="button" class="estado-menu-btn active" id="menuPendientes" data-status="Pendiente">
        <span class="estado-menu-label">
          <span class="estado-dot"></span>
          <span class="estado-menu-text">Pendientes</span>
          <span class="estado-count" id="menuCountPend">0</span>
        </span>
      </button>
      <button type="button" class="estado-menu-btn" id="menuProceso" data-status="En Proceso">
        <span class="estado-menu-label">
          <span class="estado-dot"></span>
          <span class="estado-menu-text">En Proceso</span>
          <span class="estado-count" id="menuCountProc">0</span>
        </span>
      </button>
      <button type="button" class="estado-menu-btn" id="menuFinalizadas" data-status="Finalizado">
        <span class="estado-menu-label">
          <span class="estado-dot"></span>
          <span class="estado-menu-text">Finalizadas</span>
          <span class="estado-count" id="menuCountFin">0</span>
        </span>
      </button>
      <div class="estado-menu-actions">
        <a href="cerrarSesion.php" class="logout-side-btn" title="Cerrar sesión"><img src="img/power-off.png" alt="Salir"> Cerrar sesión</a>
      </div>
    </aside>

    <div class="content-stack">
      <!-- FILTROS -->
      <div class="card">
        <h2><i class="fas fa-sliders-h"></i> Filtros</h2>
        <div class="filters">
          <select class="filter-select" id="filterEstado">
            <option value="">Todos los estados</option>
            <option value="Pendiente">Pendiente</option>
            <option value="En Proceso">En Proceso</option>
            <option value="Finalizado">Finalizado</option>
          </select>
          <select class="filter-select" id="filterPrioridad">
            <option value="">Todas las prioridades</option>
            <option value="Alta">Alta</option><option value="Media">Media</option><option value="Baja">Baja</option>
          </select>
          <select class="filter-select" id="filterAgente">
            <option value="">Todos los agentes</option>
            <?php $agentes=$conexion->query("SELECT id,nombre FROM agentes WHERE idEmpresa IS NULL OR idEmpresa='' ORDER BY nombre"); while($a=$agentes->fetch_assoc()){echo "<option value='{$a['id']}'>{$a['nombre']}</option>";} ?>
          </select>
          <select class="filter-select" id="filterCliente">
            <option value="">Todos los clientes</option>
            <?php foreach ($clientesActivos as $c): ?>
              <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars(solicitudes_cliente_option_label($c), ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
          <select class="filter-select" id="filterProyecto" disabled><option value="">Todos los proyectos</option></select>
          <div class="date-filters">
            <input type="date" id="filterDateFrom" title="Fecha desde">
            <input type="date" id="filterDateTo" title="Fecha hasta">
            <button type="button" id="clearDateFilters" class="btn-clear-dates"><i class="fas fa-times"></i></button>
          </div>
        </div>

        <!-- STATS -->
        <div class="stats-panel" id="statsPanel">
          <div class="stat-box stat-todos" id="statTodos">
            <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
            <div class="stat-content"><div class="num">0</div><div class="label">Todos</div></div>
          </div>
          <div class="stat-box stat-pendiente" id="statPendientes">
            <div class="stat-icon"><i class="fas fa-hourglass-start"></i></div>
            <div class="stat-content"><div class="num">0</div><div class="label">Pendientes</div></div>
          </div>
          <div class="stat-box stat-proceso" id="statProceso">
            <div class="stat-icon"><i class="fas fa-spinner"></i></div>
            <div class="stat-content"><div class="num">0</div><div class="label">En Proceso</div></div>
          </div>
          <div class="stat-box stat-finalizado" id="statFinalizadas">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-content"><div class="num">0</div><div class="label">Finalizadas</div></div>
          </div>
          <div class="stat-box stat-total" id="statTotal">
            <div class="stat-icon"><i class="fas fa-chart-pie"></i></div>
            <div class="stat-content"><div class="num">0</div><div class="label">Total BD</div></div>
          </div>
        </div>
        <div class="stats-progress-bar" id="statsProgressBar">
          <div class="bar-pend" style="width:0%"></div>
          <div class="bar-proc" style="width:0%"></div>
          <div class="bar-fin" style="width:0%"></div>
        </div>
      </div>

      <!-- TABLAS -->
      <div class="card">
        <h2><i class="fas fa-list"></i> <span id="tabTitle">Lista de Solicitudes</span></h2>

        <div class="freq-tabs" id="freqTabs" role="tablist" aria-label="Filtrar por frecuencia">
          <button type="button" class="freq-tab active" data-freq="" role="tab" aria-selected="true">
            Todas <span class="freq-tab-count" id="freqCountAll">0</span>
          </button>
          <button type="button" class="freq-tab" data-freq="0" role="tab" aria-selected="false">
            <i class="fas fa-bolt"></i> Única <span class="freq-tab-count" id="freqCount0">0</span>
          </button>
          <button type="button" class="freq-tab" data-freq="1" role="tab" aria-selected="false">
            <i class="fas fa-sun"></i> Diaria <span class="freq-tab-count" id="freqCount1">0</span>
          </button>
          <button type="button" class="freq-tab" data-freq="2" role="tab" aria-selected="false">
            <i class="fas fa-calendar-week"></i> Semanal <span class="freq-tab-count" id="freqCount2">0</span>
          </button>
          <button type="button" class="freq-tab" data-freq="3" role="tab" aria-selected="false">
            <i class="fas fa-calendar-alt"></i> Mensual <span class="freq-tab-count" id="freqCount3">0</span>
          </button>
        </div>
        
        <!-- Acciones masivas -->
        <div id="bulkActionsTodos" class="bulk-actions">
          <span class="bulk-actions-label"><i class="fas fa-check-square"></i> <span id="selectedCountTodos">0</span> seleccionado(s)</span>
          <button type="button" class="btn-select-all" onclick="selectAllTickets('dtTodos')"><i class="fas fa-check-double"></i> Seleccionar todos</button>
          <button type="button" class="btn-select-none" onclick="deselectAllTickets('dtTodos')"><i class="fas fa-times"></i> Deseleccionar todos</button>
          <button type="button" class="btn-danger" onclick="deleteSelectedTickets('dtTodos')"><i class="fas fa-trash"></i> Eliminar seleccionados</button>
        </div>

        <div id="bulkActionsPendientes" class="bulk-actions">
          <span class="bulk-actions-label"><i class="fas fa-check-square"></i> <span id="selectedCountPendientes">0</span> seleccionado(s)</span>
          <button type="button" class="btn-select-all" onclick="selectAllTickets('dtPendientes')"><i class="fas fa-check-double"></i> Seleccionar todos</button>
          <button type="button" class="btn-select-none" onclick="deselectAllTickets('dtPendientes')"><i class="fas fa-times"></i> Deseleccionar todos</button>
          <button type="button" class="btn-danger" onclick="deleteSelectedTickets('dtPendientes')"><i class="fas fa-trash"></i> Eliminar seleccionados</button>
        </div>

        <div id="bulkActionsFinalizadas" class="bulk-actions">
          <span class="bulk-actions-label"><i class="fas fa-check-square"></i> <span id="selectedCountFinalizadas">0</span> seleccionado(s)</span>
          <button type="button" class="btn-select-all" onclick="selectAllTickets('dtFinalizadas')"><i class="fas fa-check-double"></i> Seleccionar todos</button>
          <button type="button" class="btn-select-none" onclick="deselectAllTickets('dtFinalizadas')"><i class="fas fa-times"></i> Deseleccionar todos</button>
          <button type="button" class="btn-danger" onclick="deleteSelectedTickets('dtFinalizadas')"><i class="fas fa-trash"></i> Eliminar seleccionados</button>
        </div>

        <div class="table-responsive">

          <!-- TODOS (nuevo módulo) -->
          <div id="tablaTodos" style="display:none;">
          <table id="dtTodos" class="display" style="width:100%">
          <thead><tr>
            <th><input type="checkbox" class="ticket-checkbox" id="selectAllTodos" title="Seleccionar todos"></th>
            <th>ID</th><th>T&iacute;tulo</th><th>Estado</th><th>Asignado a</th><th>Empresa</th><th>Proyecto</th>
            <th>Prioridad</th><th>Frecuencia</th><th>Inicio</th><th>Fecha L&iacute;mite</th><th>Termina</th><th>Acciones</th>
          </tr></thead>
          <tbody>
<?php
$sqlAll = "SELECT s.*, $campoNotas s.usuario_asignado $selectEmpresa $selectProyecto FROM solicitudes s $joinCliente $joinProyecto ";
if($joinNotas) $sqlAll .= $joinNotas.' ';
$sqlAll .= "ORDER BY s.fecha_solicitud DESC";
$rAll = $conexion->query($sqlAll);
while($f = $rAll->fetch_assoc()){
  $ec = $f['estado']; $pc = $f['prioridad'];
  $estClass = $ec=='Pendiente'?'badge-pendiente':($ec=='En Proceso'?'badge-proceso':'badge-finalizado');
  $prClass = $pc=='Alta'?'badge-alta':($pc=='Media'?'badge-media':'badge-baja');
  $dd = json_decode($f['descripcion'],true);
  $dtxt = isset($dd['text'])?$dd['text']:$f['descripcion'];
  $dimg = isset($dd['images'])?array_map(function($x){return is_array($x)?($x['ruta']??''):(string)$x;},$dd['images']):[];
  $dfil = isset($dd['files'])?array_map(function($x){return is_array($x)?($x['ruta']??''):(string)$x;},$dd['files']):[];
  $dimgJ = htmlspecialchars(json_encode($dimg),ENT_QUOTES);
  $dfilJ = htmlspecialchars(json_encode($dfil),ENT_QUOTES);
  $flr = isset($f['fecha_lim'])?$f['fecha_lim']:'';
  $ev=false; $eh=false;
  if($flr && $flr!=='0000-00-00 00:00:00' && $flr!=='0000-00-00'){
    $fd=date('Y-m-d',strtotime($flr)); $hy=date('Y-m-d');
    $ev=$fd<$hy && $ec!=='Finalizado'; $eh=$fd===$hy && $ec!=='Finalizado';
  }
  $rc = $ev?'row-overdue':($eh?'row-due-today':'');
  $rtxt='Única'; $rv=(int)($f['repetir']??0); $rcls='badge-freq-unica';
  if($rv==1){$rtxt='Diaria';$rcls='badge-freq-diaria';}elseif($rv==2){$rtxt='Semanal';$rcls='badge-freq-semanal';}elseif($rv==3){$rtxt='Mensual';$rcls='badge-freq-mensual';}
  $nc=(int)($f['notas_count']??0);
  $cid=isset($f['id_cliente'])?(int)$f['id_cliente']:'';
  $pid=isset($f['id_proyecto'])?(int)$f['id_proyecto']:'';
?>
  <tr data-client-id="<?=$cid?>" data-project-id="<?=$pid?>" data-agent-ids="<?=htmlspecialchars($f['usuario_asignado']??'',ENT_QUOTES)?>" data-priority="<?=htmlspecialchars($pc,ENT_QUOTES)?>" data-fecha-lim="<?=htmlspecialchars($flr)?>" data-ticket-id="<?=$f['id']?>" data-repetir="<?=$rv?>" class="<?=$rc?>">
    <td><input type="checkbox" class="ticket-checkbox row-checkbox" data-id="<?=$f['id']?>" data-table="dtTodos"></td>
    <td><?=$f['id']?></td>
    <td><?=htmlspecialchars($f['titulo'])?></td>
    <td><form action="actualizar.php" method="POST" class="estado-form"><input type="hidden" name="id" value="<?=$f['id']?>"><select name="estado" class="estado-select" data-val="<?=htmlspecialchars($ec)?>"><option value="Pendiente"<?=$ec=='Pendiente'?'selected':''?>>Pendiente</option><option value="En Proceso"<?=$ec=='En Proceso'?'selected':''?>>En Proceso</option><option value="Finalizado"<?=$ec=='Finalizado'?'selected':''?>>Finalizado</option></select></form></td>
    <td><?php if($f['usuario_asignado']){echo '<span class="agent-id" style="display:none">'.htmlspecialchars($f['usuario_asignado']??'').'</span>';echo mostrarAgentesHTML($conexion,$f['usuario_asignado']);}else{?><select class="assign-agent-select" data-ticket-id="<?=$f['id']?>"><option value="">Asignar</option><?=$__ag_opts?></select><?php }?></td>
    <td><?=htmlspecialchars($f['empresa_cliente']??'-')?:'-'?></td>
    <td><?=htmlspecialchars($f['nombre_proyecto']??'-')?:'-'?></td>
    <td><span class="badge <?=$prClass?>"><?=$pc?></span></td>
    <td data-search="<?=$rv?>" data-order="<?=$rv?>" data-repetir="<?=$rv?>"><span class="badge-freq <?=$rcls?>"><?=$rtxt?></span></td>
    <td><?=date('d/m/Y H:i',strtotime($f['fecha_solicitud']))?></td>
    <td><?php if($flr && $flr!=='0000-00-00 00:00:00' && $flr!=='0000-00-00'){?><span class="fecha-lim-cell"><span><?=date('d/m/Y H:i',strtotime($flr))?></span><?php if($ev){?><span class="badge-vencido"><i class="fas fa-exclamation-triangle"></i> Vencido</span><?php }elseif($eh){?><span class="badge-hoy"><i class="fas fa-clock"></i> Hoy</span><?php }?></span><?php }else{?>-<?php }?></td>
    <td><?=$f['fecha_termina']&&$f['fecha_termina']!='0000-00-00 00:00:00'?date('d/m/Y H:i',strtotime($f['fecha_termina'])):'-'?></td>
    <td>
      <button type="button" class="action-btn btn-edit" title="Editar" onclick="editarSolicitud(<?=$f['id']?>)"><i class="fas fa-edit"></i></button>
      <button type="button" class="action-btn btn-info btn-ver-desc" data-id="<?= (int)$f['id'] ?>" title="Ver descripción"><i class="fas fa-eye"></i></button>
      <button type="button" class="action-btn btn-notas" title="Notas" onclick="openNotasModal(<?=$f['id']?>)"><i class="fas fa-note-sticky"></i><span class="notes-count <?=$nc>0?'has':'empty'?>" data-ticket="<?=$f['id']?>"><?=$nc?></span></button>
      <button type="button" class="action-btn btn-txt" title="Descargar TXT" onclick="descargarTicketTXT(<?=$f['id']?>)"><i class="fas fa-file-alt"></i></button>
      <form action="eliminar.php" method="POST" class="form-eliminar" style="display:inline-block;"><input type="hidden" name="id" value="<?=$f['id']?>"><button type="button" class="action-btn btn-delete" onclick="confirmarEliminacion(this)"><i class="fas fa-trash"></i></button></form>
    </td>
  </tr>
<?php } ?>
          </tbody></table>
          </div>

          <!-- PENDIENTES / EN PROCESO -->
          <div id="tablaPendientes">
          <table id="dtPendientes" class="display" style="width:100%">
          <thead><tr>
            <th><input type="checkbox" class="ticket-checkbox" id="selectAllPendientes" title="Seleccionar todos"></th>
            <th>ID</th><th>T&iacute;tulo</th><th>Estado</th><th>Asignado a</th><th>Empresa</th><th>Proyecto</th>
            <th>Prioridad</th><th>Frecuencia</th><th>Inicio</th><th>Fecha L&iacute;mite</th><th>Acciones</th>
          </tr></thead>
          <tbody>
<?php
$sqlPend = "SELECT s.*, $campoNotas s.usuario_asignado $selectEmpresa $selectProyecto FROM solicitudes s $joinCliente $joinProyecto ";
if($joinNotas) $sqlPend .= $joinNotas.' ';
$sqlPend .= "WHERE s.estado != 'Finalizado' ORDER BY s.fecha_solicitud DESC";
$rPend = $conexion->query($sqlPend);
while($f = $rPend->fetch_assoc()){
  $ec = $f['estado']; $pc = $f['prioridad'];
  $estClass = $ec=='Pendiente'?'badge-pendiente':($ec=='En Proceso'?'badge-proceso':'badge-finalizado');
  $prClass = $pc=='Alta'?'badge-alta':($pc=='Media'?'badge-media':'badge-baja');
  $dd = json_decode($f['descripcion'],true);
  $dtxt = isset($dd['text'])?$dd['text']:$f['descripcion'];
  $dimg = isset($dd['images'])?array_map(function($x){return is_array($x)?($x['ruta']??''):(string)$x;},$dd['images']):[];
  $dfil = isset($dd['files'])?array_map(function($x){return is_array($x)?($x['ruta']??''):(string)$x;},$dd['files']):[];
  $dimgJ = htmlspecialchars(json_encode($dimg),ENT_QUOTES);
  $dfilJ = htmlspecialchars(json_encode($dfil),ENT_QUOTES);
  $flr = isset($f['fecha_lim'])?$f['fecha_lim']:'';
  $ev=false; $eh=false;
  if($flr && $flr!=='0000-00-00 00:00:00' && $flr!=='0000-00-00'){
    $fd=date('Y-m-d',strtotime($flr)); $hy=date('Y-m-d');
    $ev=$fd<$hy && $ec!=='Finalizado'; $eh=$fd===$hy && $ec!=='Finalizado';
  }
  $rc = $ev?'row-overdue':($eh?'row-due-today':'');
  $rtxt='Única'; $rv=(int)($f['repetir']??0); $rcls='badge-freq-unica';
  if($rv==1){$rtxt='Diaria';$rcls='badge-freq-diaria';}elseif($rv==2){$rtxt='Semanal';$rcls='badge-freq-semanal';}elseif($rv==3){$rtxt='Mensual';$rcls='badge-freq-mensual';}
  $nc=(int)($f['notas_count']??0);
  $cid=isset($f['id_cliente'])?(int)$f['id_cliente']:'';
  $pid=isset($f['id_proyecto'])?(int)$f['id_proyecto']:'';
?>
  <tr data-client-id="<?=$cid?>" data-project-id="<?=$pid?>" data-agent-ids="<?=htmlspecialchars($f['usuario_asignado']??'',ENT_QUOTES)?>" data-priority="<?=htmlspecialchars($pc,ENT_QUOTES)?>" data-fecha-lim="<?=htmlspecialchars($flr)?>" data-ticket-id="<?=$f['id']?>" data-repetir="<?=$rv?>" class="<?=$rc?>">
    <td><input type="checkbox" class="ticket-checkbox row-checkbox" data-id="<?=$f['id']?>" data-table="dtPendientes"></td>
    <td><?=$f['id']?></td>
    <td><?=htmlspecialchars($f['titulo'])?></td>
    <td><form action="actualizar.php" method="POST" class="estado-form"><input type="hidden" name="id" value="<?=$f['id']?>"><select name="estado" class="estado-select" data-val="<?=htmlspecialchars($ec)?>"><option value="Pendiente"<?=$ec=='Pendiente'?'selected':''?>>Pendiente</option><option value="En Proceso"<?=$ec=='En Proceso'?'selected':''?>>En Proceso</option><option value="Finalizado"<?=$ec=='Finalizado'?'selected':''?>>Finalizado</option></select></form></td>
    <td><?php if($f['usuario_asignado']){echo '<span class="agent-id" style="display:none">'.htmlspecialchars($f['usuario_asignado']??'').'</span>';echo mostrarAgentesHTML($conexion,$f['usuario_asignado']);}else{?><select class="assign-agent-select" data-ticket-id="<?=$f['id']?>"><option value="">Asignar</option><?=$__ag_opts?></select><?php }?></td>
    <td><?=htmlspecialchars($f['empresa_cliente']??'-')?:'-'?></td>
    <td><?=htmlspecialchars($f['nombre_proyecto']??'-')?:'-'?></td>
    <td><span class="badge <?=$prClass?>"><?=$pc?></span></td>
    <td data-search="<?=$rv?>" data-order="<?=$rv?>" data-repetir="<?=$rv?>"><span class="badge-freq <?=$rcls?>"><?=$rtxt?></span></td>
    <td><?=date('d/m/Y H:i',strtotime($f['fecha_solicitud']))?></td>
    <td><?php if($flr && $flr!=='0000-00-00 00:00:00' && $flr!=='0000-00-00'){?><span class="fecha-lim-cell"><span><?=date('d/m/Y H:i',strtotime($flr))?></span><?php if($ev){?><span class="badge-vencido"><i class="fas fa-exclamation-triangle"></i> Vencido</span><?php }elseif($eh){?><span class="badge-hoy"><i class="fas fa-clock"></i> Hoy</span><?php }?></span><?php }else{?>-<?php }?></td>
    <td>
      <button type="button" class="action-btn btn-edit" title="Editar" onclick="editarSolicitud(<?=$f['id']?>)"><i class="fas fa-edit"></i></button>
      <button type="button" class="action-btn btn-info btn-ver-desc" data-id="<?= (int)$f['id'] ?>" title="Ver descripción"><i class="fas fa-eye"></i></button>
      <button type="button" class="action-btn btn-notas" title="Notas" onclick="openNotasModal(<?=$f['id']?>)"><i class="fas fa-note-sticky"></i><span class="notes-count <?=$nc>0?'has':'empty'?>" data-ticket="<?=$f['id']?>"><?=$nc?></span></button>
      <button type="button" class="action-btn btn-txt" title="Descargar TXT" onclick="descargarTicketTXT(<?=$f['id']?>)"><i class="fas fa-file-alt"></i></button>
      <form action="eliminar.php" method="POST" class="form-eliminar" style="display:inline-block;"><input type="hidden" name="id" value="<?=$f['id']?>"><button type="button" class="action-btn btn-delete" onclick="confirmarEliminacion(this)"><i class="fas fa-trash"></i></button></form>
    </td>
  </tr>
<?php } ?>
          </tbody></table>
          </div>

          <!-- FINALIZADAS -->
          <div id="tablaFinalizadas" style="display:none;">
          <table id="dtFinalizadas" class="display" style="width:100%">
          <thead><tr>
            <th><input type="checkbox" class="ticket-checkbox" id="selectAllFinalizadas" title="Seleccionar todos"></th>
            <th>ID</th><th>T&iacute;tulo</th><th>Estado</th><th>Asignado a</th><th>Empresa</th><th>Proyecto</th>
            <th>Prioridad</th><th>Frecuencia</th><th>Inicio</th><th>Fecha L&iacute;mite</th><th>Termina</th><th>Acciones</th>
          </tr></thead>
          <tbody>
<?php
$sqlFin = "SELECT s.*, $campoNotas s.usuario_asignado $selectEmpresa $selectProyecto FROM solicitudes s $joinCliente $joinProyecto ";
if($joinNotas) $sqlFin .= $joinNotas.' ';
$sqlFin .= "WHERE s.estado = 'Finalizado' ORDER BY s.fecha_solicitud DESC";
$rFin = $conexion->query($sqlFin);
while($f = $rFin->fetch_assoc()){
  $ec = $f['estado']; $pc = $f['prioridad'];
  $estClass = $ec=='Pendiente'?'badge-pendiente':($ec=='En Proceso'?'badge-proceso':'badge-finalizado');
  $prClass = $pc=='Alta'?'badge-alta':($pc=='Media'?'badge-media':'badge-baja');
  $dd = json_decode($f['descripcion'],true);
  $dtxt = isset($dd['text'])?$dd['text']:$f['descripcion'];
  $dimg = isset($dd['images'])?array_map(function($x){return is_array($x)?($x['ruta']??''):(string)$x;},$dd['images']):[];
  $dfil = isset($dd['files'])?array_map(function($x){return is_array($x)?($x['ruta']??''):(string)$x;},$dd['files']):[];
  $dimgJ = htmlspecialchars(json_encode($dimg),ENT_QUOTES);
  $dfilJ = htmlspecialchars(json_encode($dfil),ENT_QUOTES);
  $flr = isset($f['fecha_lim'])?$f['fecha_lim']:'';
  $ev=false; $eh=false;
  if($flr && $flr!=='0000-00-00 00:00:00' && $flr!=='0000-00-00'){
    $fd=date('Y-m-d',strtotime($flr)); $hy=date('Y-m-d');
    $ev=$fd<$hy && $ec!=='Finalizado'; $eh=$fd===$hy && $ec!=='Finalizado';
  }
  $rc = $ev?'row-overdue':($eh?'row-due-today':'');
  $rtxt='Única'; $rv=(int)($f['repetir']??0); $rcls='badge-freq-unica';
  if($rv==1){$rtxt='Diaria';$rcls='badge-freq-diaria';}elseif($rv==2){$rtxt='Semanal';$rcls='badge-freq-semanal';}elseif($rv==3){$rtxt='Mensual';$rcls='badge-freq-mensual';}
  $nc=(int)($f['notas_count']??0);
  $cid=isset($f['id_cliente'])?(int)$f['id_cliente']:'';
  $pid=isset($f['id_proyecto'])?(int)$f['id_proyecto']:'';
?>
  <tr data-client-id="<?=$cid?>" data-project-id="<?=$pid?>" data-agent-ids="<?=htmlspecialchars($f['usuario_asignado']??'',ENT_QUOTES)?>" data-priority="<?=htmlspecialchars($pc,ENT_QUOTES)?>" data-fecha-lim="<?=htmlspecialchars($flr)?>" data-ticket-id="<?=$f['id']?>" data-repetir="<?=$rv?>" class="<?=$rc?>">
    <td><input type="checkbox" class="ticket-checkbox row-checkbox" data-id="<?=$f['id']?>" data-table="dtFinalizadas"></td>
    <td><?=$f['id']?></td>
    <td><?=htmlspecialchars($f['titulo'])?></td>
    <td><form action="actualizar.php" method="POST" class="estado-form"><input type="hidden" name="id" value="<?=$f['id']?>"><select name="estado" class="estado-select" data-val="<?=htmlspecialchars($ec)?>"><option value="Pendiente"<?=$ec=='Pendiente'?'selected':''?>>Pendiente</option><option value="En Proceso"<?=$ec=='En Proceso'?'selected':''?>>En Proceso</option><option value="Finalizado"<?=$ec=='Finalizado'?'selected':''?>>Finalizado</option></select></form></td>
    <td><?php if($f['usuario_asignado']){echo '<span class="agent-id" style="display:none">'.htmlspecialchars($f['usuario_asignado']??'').'</span>';echo mostrarAgentesHTML($conexion,$f['usuario_asignado']);}else{?><select class="assign-agent-select" data-ticket-id="<?=$f['id']?>"><option value="">Asignar</option><?=$__ag_opts?></select><?php }?></td>
    <td><?=htmlspecialchars($f['empresa_cliente']??'-')?:'-'?></td>
    <td><?=htmlspecialchars($f['nombre_proyecto']??'-')?:'-'?></td>
    <td><span class="badge <?=$prClass?>"><?=$pc?></span></td>
    <td data-search="<?=$rv?>" data-order="<?=$rv?>" data-repetir="<?=$rv?>"><span class="badge-freq <?=$rcls?>"><?=$rtxt?></span></td>
    <td><?=date('d/m/Y H:i',strtotime($f['fecha_solicitud']))?></td>
    <td><?php if($flr && $flr!=='0000-00-00 00:00:00' && $flr!=='0000-00-00'){?><span class="fecha-lim-cell"><span><?=date('d/m/Y H:i',strtotime($flr))?></span><?php if($ev){?><span class="badge-vencido"><i class="fas fa-exclamation-triangle"></i> Vencido</span><?php }elseif($eh){?><span class="badge-hoy"><i class="fas fa-clock"></i> Hoy</span><?php }?></span><?php }else{?>-<?php }?></td>
    <td><?=$f['fecha_termina']&&$f['fecha_termina']!='0000-00-00 00:00:00'?date('d/m/Y H:i',strtotime($f['fecha_termina'])):'-'?></td>
    <td>
      <button type="button" class="action-btn btn-edit" title="Editar" onclick="editarSolicitud(<?=$f['id']?>)"><i class="fas fa-edit"></i></button>
      <button type="button" class="action-btn btn-info btn-ver-desc" data-id="<?= (int)$f['id'] ?>" title="Ver descripción"><i class="fas fa-eye"></i></button>
      <button type="button" class="action-btn btn-notas" title="Notas" onclick="openNotasModal(<?=$f['id']?>)"><i class="fas fa-note-sticky"></i><span class="notes-count <?=$nc>0?'has':'empty'?>" data-ticket="<?=$f['id']?>"><?=$nc?></span></button>
      <button type="button" class="action-btn btn-txt" title="Descargar TXT" onclick="descargarTicketTXT(<?=$f['id']?>)"><i class="fas fa-file-alt"></i></button>
      <form action="eliminar.php" method="POST" class="form-eliminar" style="display:inline-block;"><input type="hidden" name="id" value="<?=$f['id']?>"><button type="button" class="action-btn btn-delete" onclick="confirmarEliminacion(this)"><i class="fas fa-trash"></i></button></form>
    </td>
  </tr>
<?php } ?>
          </tbody></table>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<!-- MODALES: Ver descripción, Notas, Nueva/Editar Solicitud -->
<div class="modal-overlay" id="modalVerDesc"><div class="modal modal-descripcion tk-doc-modal"><div class="modal-header"><div><h2 class="modal-title">Brief del ticket</h2><span class="tk-doc-modal-sub">Orden recomendado para desarrollo</span></div><button class="modal-close" id="closeVerDesc" aria-label="Cerrar">&times;</button></div><div class="modal-body"><div id="tkDescMeta"></div><div class="tk-doc-attachments-label" id="labelImagenesDesc" hidden>Referencias visuales</div><div class="content-images tk-doc-gallery" id="contenidoImagenes"></div><div class="tk-doc-attachments-label" id="labelArchivosDesc" hidden>Archivos</div><div class="content-files" id="contenidoArchivos"></div><div id="contenidoDescripcion"></div></div><div class="modal-footer tk-doc-footer"><div class="tk-doc-footer-actions"><button type="button" class="btn tk-btn-ghost" id="btnCopyBrief"><i class="fas fa-copy" aria-hidden="true"></i> Copiar brief</button><button type="button" class="btn tk-btn-ghost" id="btnCopyUrl"><i class="fas fa-link" aria-hidden="true"></i> Copiar URL</button><button type="button" class="btn tk-btn-ghost" id="btnOpenNotasDesc"><i class="fas fa-note-sticky" aria-hidden="true"></i> Notas</button><button type="button" class="btn tk-btn-primary" id="btnMarcarProceso"><i class="fas fa-play" aria-hidden="true"></i> En Proceso</button></div><button type="button" class="btn btn-cancel" id="cancelVerDesc">Cerrar</button></div></div></div>

<div class="modal-overlay" id="modalNotas"><div class="modal modal-notas"><div class="modal-header"><h2 class="modal-title">Notas del Ticket <span id="notasTicketId"></span></h2><button class="modal-close" id="closeNotas">&times;</button></div><div class="modal-body"><div id="listaNotas" class="card" style="max-height:200px;overflow:auto;padding:12px;"></div><div class="form-group"><label class="form-label">Nueva nota</label><textarea id="notaTexto" class="form-control" placeholder="Escribe una nota..."></textarea><div class="image-upload-container"><div class="image-preview-container" id="notaImagePreviews"></div><input type="file" id="notaImageUpload" multiple accept="image/*" style="display:none;"><button type="button" class="btn-add-image" onclick="document.getElementById('notaImageUpload').click()"><i class="fas fa-plus"></i> Añadir imágenes</button></div></div></div><div class="modal-footer"><button type="button" class="btn btn-cancel" id="cancelNotas">Cerrar</button><button type="button" class="btn btn-submit" id="guardarNotaBtn">Guardar Nota</button></div></div></div>

<div class="modal-overlay" id="modalSolicitud"><div class="modal modal-solicitud"><div class="modal-header"><h2 class="modal-title" id="modalSolicitudTitle">Nueva Solicitud</h2><button class="modal-close" id="closeModal">&times;</button></div>
<form action="guardar.php" method="POST" id="formSolicitud" enctype="multipart/form-data">
<div class="asistente-contexto-wrap" id="contextoTicketWrap">
  <div class="ctx-mode-row">
    <div class="ctx-mode-label" id="modoProyectoHint">El ticket se vinculará al cliente y al proyecto seleccionado.</div>
    <label class="ctx-switch" for="switchModoProyecto">
      <span class="ctx-switch-text">Vincular proyecto</span>
      <input type="checkbox" id="switchModoProyecto" checked>
      <span class="ctx-switch-ui"></span>
    </label>
  </div>
  <input type="hidden" name="id_proyecto" id="idProyectoVacio" value="" disabled>
  <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
    <div class="form-group" style="margin:0;min-width:240px;flex:1">
      <label class="form-label" for="selectCliente"><i class="fas fa-building"></i> Cliente</label>
      <select name="cliente_id" id="selectCliente" class="form-control">
        <option value="">Seleccionar cliente</option>
        <?php foreach ($clientesActivos as $cli): ?>
          <option value="<?= (int) $cli['id'] ?>"><?= htmlspecialchars(solicitudes_cliente_option_label($cli, true), ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" id="proyectoContextoWrap" style="margin:0;min-width:200px;flex:1">
      <label class="form-label" for="selectProyecto"><i class="fas fa-project-diagram"></i> Proyecto</label>
      <select name="id_proyecto" id="selectProyecto" class="form-control" disabled>
        <option value="">Selecciona primero un cliente</option>
      </select>
    </div>
  </div>
  <div class="asistente-contexto-resumen" id="contextoProyectoResumen">
    <span class="asistente-contexto-vacio">Selecciona cliente y proyecto para que el asistente use descripción, stack y tecnologías del proyecto.</span>
  </div>
</div>
<div class="solicitud-tabs" id="solicitudTabs">
  <button type="button" class="solicitud-tab active" data-tab="asistente" id="tabBtnAsistente"><i class="fas fa-robot"></i> Asistente de requerimientos</button>
  <button type="button" class="solicitud-tab" data-tab="formulario" id="tabBtnFormulario"><i class="fas fa-file-alt"></i> Formulario del ticket</button>
</div>
<div class="solicitud-tab-panel active" id="panelAsistente">
  <div class="asistente-layout">
    <div class="asistente-chat-wrap">
      <div class="asistente-chat-header"><i class="fas fa-comments"></i> Describe tu solicitud con detalle</div>
      <div class="asistente-chat-messages" id="chatMessages"></div>
      <div class="voz-estado" id="chatVozEstado"><i class="fas fa-circle" style="font-size:8px;vertical-align:middle"></i> Grabando… haz clic en el micrófono para terminar y transcribir</div>
      <div class="asistente-chat-attachments" id="chatAttachmentsWrap" style="display:none">
        <div class="image-preview-container" id="chatImagePreviews"></div>
      </div>
      <div class="asistente-chat-toolbar">
        <input type="file" id="chatImageUpload" multiple accept="image/png,image/jpeg,image/jpg,image/gif,image/webp" style="display:none;">
        <button type="button" class="btn-add-image" id="btnChatAddImage"><i class="fas fa-image"></i> Adjuntar capturas</button>
        <span class="chat-attach-hint">PNG, JPG o WEBP · máx. 5 por mensaje · también puedes pegar con Ctrl+V</span>
      </div>
      <div class="asistente-chat-input">
        <button type="button" class="btn-mic-audio" id="btnMicChat" title="Dictado de voz (clic para hablar)"><i class="fas fa-microphone"></i></button>
        <textarea id="chatInput" class="form-control" placeholder="Ej: Cambiar el texto del botón del header a 'Cotizar ahora', que sea color #2563eb y enlace a /contacto. Incluye todos los detalles relevantes..." rows="5"></textarea>
        <button type="button" class="btn-asistente" id="btnEnviarChat" title="Enviar"><i class="fas fa-paper-plane"></i></button>
      </div>
    </div>
    <div class="asistente-preview">
      <div class="asistente-preview-header">Documento de requerimientos <span id="previewBadge" class="preview-badge pending">En progreso</span></div>
      <div class="asistente-preview-body" id="previewDocumento"><div class="preview-empty">El documento se irá armando conforme avances la conversación.</div></div>
    </div>
  </div>
  <div class="asistente-actions">
    <button type="button" class="btn-asistente-outline btn-asistente" id="btnReiniciarChat"><i class="fas fa-redo"></i> Reiniciar charla</button>
    <button type="button" class="btn-asistente" id="btnAplicarRequerimientos" disabled><i class="fas fa-arrow-right"></i> Aplicar al formulario</button>
  </div>
</div>
<div class="solicitud-tab-panel" id="panelFormulario">
<input type="hidden" name="id" id="solicitudId" value="">
<input type="hidden" name="descripcion_existing_images" id="descripcionExistingImages" value="[]">
<input type="hidden" name="descripcion_existing_files" id="descripcionExistingFiles" value="[]">
<div class="form-group"><label class="form-label">Título</label><input type="text" name="titulo" class="form-control" required></div>
<div class="form-group"><label class="form-label">Descripción</label>
<div class="input-con-voz">
<textarea name="descripcion" class="form-control" id="descripcionTextarea" placeholder="Escribe o transcribe con el micrófono"></textarea>
<button type="button" class="btn-mic-audio" id="btnMicDescripcion" title="Dictado de voz (clic para hablar)"><i class="fas fa-microphone"></i></button>
</div>
<div class="voz-estado" id="descVozEstado"><i class="fas fa-circle" style="font-size:8px;vertical-align:middle"></i> Grabando… clic de nuevo para transcribir</div>
<div class="image-upload-container"><label class="form-label">Imágenes (opcional)</label><div class="image-preview-container" id="descImagePreviews"></div><input type="file" id="descImageUpload" multiple accept="image/*" style="display:none;"><button type="button" class="btn-add-image" onclick="document.getElementById('descImageUpload').click()"><i class="fas fa-plus"></i> Añadir imágenes</button></div></div>
<div class="form-group"><label class="form-label">Archivos (opcional)</label><div class="image-upload-container"><small style="color:#6c757d;">PDF, DOC, DOCX, XLS, XLSX, CSV, PPT, PPTX, ZIP, RAR, TXT</small><div id="descExistingFileList" style="margin-top:6px;font-size:0.85rem;"></div><div id="descFileList" style="margin-top:6px;font-size:0.85rem;"></div><input type="file" id="descFileUpload" name="descripcion_files[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.ppt,.pptx,.zip,.rar,.txt"></div></div>
<div class="form-group"><label class="form-label">Fecha Límite</label><input type="datetime-local" name="fecha_lim" class="form-control"></div>
<div class="form-group"><label class="form-label">Nota inicial (opcional)</label><textarea name="nota_inicial" class="form-control" placeholder="Agrega una nota inicial"></textarea><input type="hidden" name="nota_autor_inicial" value="admin"></div>
<div class="form-group"><label class="form-label"><i class="fas fa-users"></i> Asignar a Agentes <span id="agentesCounter" style="font-weight:400;color:#666;font-size:13px;margin-left:6px;">(0)</span></label>
<div style="position:relative;margin-bottom:8px;"><input type="text" id="searchAgentes" class="form-control" placeholder="Buscar agente..." style="padding-left:32px;"><i class="fas fa-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#888;pointer-events:none;"></i></div>
<div id="agentesContainer" style="border:1px solid #ddd;border-radius:8px;padding:10px;background:#fafafa;">
<?php $agentes=$conexion->query("SELECT id,nombre FROM agentes WHERE idEmpresa IS NULL OR idEmpresa='' ORDER BY nombre"); $cols=['#4361ee','#3a0ca3','#7209b7','#f72585','#06a77d','#ffa600','#ff6b6b','#4ecdc4']; $ci=0; while($a=$agentes->fetch_assoc()){$c=$cols[$ci%count($cols)];$ci++;?><div class="agente-checkbox-item" data-nombre="<?=strtolower($a['nombre'])?>" style="margin-bottom:6px;"><label style="display:flex;align-items:center;cursor:pointer;padding:8px;border-radius:6px;transition:all .2s;background:#fff;border:2px solid #e9ecef;gap:8px;" class="agente-label"><input type="checkbox" name="agentes_asignados[]" value="<?=$a['id']?>" style="width:18px;height:18px;cursor:pointer;accent-color:<?=$c?>;"><span style="background:<?=$c?>;color:#fff;padding:3px 8px;border-radius:4px;font-size:11px;font-weight:700;"><?=substr($a['nombre'],0,2)?></span><span style="flex:1;font-size:13px;color:#333;"><?=htmlspecialchars($a['nombre'])?></span></label></div><?php }?></div>
<div style="margin-top:8px;display:flex;gap:6px;"><button type="button" id="btnSeleccionarTodos" style="background:#4361ee;color:#fff;border:none;padding:5px 10px;border-radius:6px;cursor:pointer;font-size:11px;"><i class="fas fa-check-double"></i> Todos</button><button type="button" id="btnDeseleccionarTodos" style="background:#6c757d;color:#fff;border:none;padding:5px 10px;border-radius:6px;cursor:pointer;font-size:11px;"><i class="fas fa-times"></i> Limpiar</button></div></div>
<div class="form-group"><label class="form-label">Prioridad</label><select name="prioridad" class="form-control"><option value="Alta">Alta</option><option value="Media" selected>Media</option><option value="Baja">Baja</option></select></div>
<div class="form-group"><div style="display:flex;align-items:center;margin-bottom:6px;"><input type="checkbox" id="repetirSolicitud" name="repetir_activo" style="margin-right:8px;"><label for="repetirSolicitud" class="form-label" style="margin-bottom:0;cursor:pointer;">Repetir solicitud</label></div><select name="repetir" id="selectRepetir" class="form-control" disabled style="opacity:.6;"><option value="">Seleccionar frecuencia</option><option value="1">Diariamente</option><option value="2">Semanalmente</option><option value="3">Mensualmente</option></select></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-cancel" id="cancelForm">Cancelar</button><button type="submit" class="btn btn-submit">Guardar</button></div></form></div></div>

<div class="lightbox-overlay" id="lightboxOverlay">
  <button class="lightbox-close" id="lightboxClose">&times;</button>
  <div class="lightbox-nav"><button class="lightbox-prev" id="lightboxPrev">&lt;</button><button class="lightbox-next" id="lightboxNext">&gt;</button></div>
  <div class="lightbox-content"><img id="lightboxImage" src="" alt=""></div>
  <div id="lightboxCounter" style="position:absolute;bottom:-35px;left:50%;transform:translateX(-50%);color:rgba(255,255,255,.7);font-size:14px;"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="js/ticket_desc_document.js?v=15"></script>
<script>
<?php
$__cwSn = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/solicitudes/index.php'));
if (preg_match('#^(.*?/solicitudes)(?:/|$)#i', $__cwSn, $__cwM)) {
    $__cwBase = rtrim($__cwM[1], '/') . '/';
} else {
    $__cwDir = rtrim(str_replace('\\', '/', dirname($__cwSn)), '/');
    $__cwBase = ($__cwDir === '' || $__cwDir === '/' || $__cwDir === '.') ? '/solicitudes/' : ($__cwDir . '/');
}
?>
window.SOLICITUDES_BASE = <?= json_encode($__cwBase, JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>

<script>
// ── UTILIDADES ──
function descargarTicketTXT(id){
  Swal.fire({title:'Generando...',text:'Por favor espera',allowOutsideClick:false,didOpen:()=>Swal.showLoading()});
  fetch('generar_txt.php?id='+encodeURIComponent(id)).then(r=>{if(!r.ok)throw Error();return r.blob()}).then(b=>{
    Swal.close();const u=window.URL.createObjectURL(b);const a=document.createElement('a');a.href=u;a.download=`ticket_${id}.txt`;document.body.appendChild(a);a.click();window.URL.revokeObjectURL(u);a.remove();
    Swal.fire({toast:true,position:'top-end',icon:'success',title:'Descargado',timer:1500,showConfirmButton:false});
  }).catch(()=>Swal.fire({icon:'error',title:'Error',text:'No se pudo generar el TXT'}));
}
function decodeVisibleEscapes(s){if(typeof s!=='string')return s;return s.replace(/\\r\\n/g,'\n').replace(/\\n/g,'\n').replace(/\\r/g,'\n')}
function escapeHtml(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;')}
function formatDescriptionText(raw){
  const text=decodeVisibleEscapes(raw||'').trim().replace(/\n{3,}/g,'\n\n');
  const lines=text.split('\n');let html='',inList=false,para=[];
  function flushPara(){if(para.length){html+=`<p>${para.map(escapeHtml).join('<br>')}</p>`;para=[]}}
  for(const l of lines){const line=l.trim();if(!line){flushPara();if(inList){html+='</ul>';inList=false}continue}
  if(/^[-*•]\s+/.test(line)){flushPara();if(!inList){html+='<ul>';inList=true}html+=`<li>${escapeHtml(line.replace(/^[-*•]\s+/,''))}</li>`}
  else{if(inList){html+='</ul>';inList=false}para.push(line)}}
  flushPara();if(inList)html+='</ul>';return html||'<p>(Sin descripción)</p>'
}

// ── VARIABLES GLOBALES ──
const CLIENTES_ACTIVOS_IDS = <?= json_encode(array_map('intval', array_column($clientesActivos, 'id')), JSON_UNESCAPED_UNICODE) ?>;
const ID_CLIENTE_URL = <?= (int) $idClienteUrl ?>;
let descripcionImages=[],notaImages=[],descNewFiles=[];
let existingDescFiles=[],removedDescFiles=new Set();
let currentLightboxImages=[],currentLightboxIndex=0;
let notasTicketIdActual=null;

// ── DATATABLES ──
const dtTodos = $('#dtTodos').DataTable({
  dom:'Blfrtip',buttons:[{extend:'excelHtml5',text:'<i class="fas fa-file-excel"></i> Excel',className:'btn-primary',orientation:'landscape',pageSize:'A4'}],
  lengthMenu:[[10,25,50,100,-1],[10,25,50,100,'Todos']],pageLength:-1,order:[[0,'desc']],
  language:{url:'//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'}
});
const dtPendientes = $('#dtPendientes').DataTable({
  dom:'Blfrtip',buttons:[{extend:'excelHtml5',text:'<i class="fas fa-file-excel"></i> Excel',className:'btn-primary',orientation:'landscape',pageSize:'A4'}],
  lengthMenu:[[10,25,50,100,-1],[10,25,50,100,'Todos']],pageLength:-1,order:[[0,'desc']],
  language:{url:'//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'}
});
const dtFinalizadas = $('#dtFinalizadas').DataTable({
  dom:'Blfrtip',buttons:[{extend:'excelHtml5',text:'<i class="fas fa-file-excel"></i> Excel',className:'btn-primary',orientation:'landscape',pageSize:'A4'}],
  lengthMenu:[[10,25,50,100,-1],[10,25,50,100,'Todos']],pageLength:-1,order:[[0,'desc']],
  language:{url:'//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'}
});

// ── CONTADORES GLOBALES ──
function paintMenuCounts(counts){
  const all=(counts.Pendiente||0)+(counts['En Proceso']||0)+(counts.Finalizado||0);
  const el=(id)=>document.getElementById(id);
  if(el('menuCountAll'))el('menuCountAll').textContent=all;
  if(el('menuCountPend'))el('menuCountPend').textContent=(counts.Pendiente||0);
  if(el('menuCountProc'))el('menuCountProc').textContent=(counts['En Proceso']||0);
  if(el('menuCountFin'))el('menuCountFin').textContent=(counts.Finalizado||0);
}
/** Fallback: contar filas de las tablas si la API falla */
function countsFromTables(){
  const counts={Pendiente:0,'En Proceso':0,Finalizado:0};
  try{
    document.querySelectorAll('#dtTodos tbody tr, #dtPendientes tbody tr, #dtFinalizadas tbody tr').forEach(tr=>{
      if(tr.classList.contains('dataTables_empty')||!tr.cells||tr.cells.length<3)return;
      const sel=tr.querySelector('select.estado-select');
      const est=sel?(sel.value||'').trim():'';
      if(est==='Pendiente')counts.Pendiente++;
      else if(est==='En Proceso')counts['En Proceso']++;
      else if(est==='Finalizado')counts.Finalizado++;
    });
    // dtPendientes + dtFinalizadas se solapan con dtTodos: preferir solo dtTodos si tiene filas
    const todosRows=document.querySelectorAll('#dtTodos tbody tr:not(.dataTables_empty)');
    if(todosRows.length>0){
      counts.Pendiente=0;counts['En Proceso']=0;counts.Finalizado=0;
      todosRows.forEach(tr=>{
        const sel=tr.querySelector('select.estado-select');
        const est=sel?(sel.value||'').trim():'';
        if(est==='Pendiente')counts.Pendiente++;
        else if(est==='En Proceso')counts['En Proceso']++;
        else if(est==='Finalizado')counts.Finalizado++;
      });
    }
  }catch(e){}
  return counts;
}
async function refreshMenuCounts(){
  try{
    const r=await fetch('api_contadores.php?_ts='+Date.now(),{credentials:'same-origin',headers:{'Accept':'application/json'}});
    const text=await r.text();
    let d=null;
    try{d=JSON.parse(text);}catch(e){d=null;}
    if(d&&d.success&&d.counts){paintMenuCounts(d.counts);return;}
  }catch(e){}
  paintMenuCounts(countsFromTables());
}
refreshMenuCounts();setInterval(refreshMenuCounts,3000);
setTimeout(refreshMenuCounts,800);

// ── NAVEGACIÓN ENTRE TABLAS ──
const tablaTodos=document.getElementById('tablaTodos');
const tablaPend=document.getElementById('tablaPendientes');
const tablaFin=document.getElementById('tablaFinalizadas');
const tabTitle=document.getElementById('tabTitle');

function setActiveView(view){ // 'todos','pendientes','proceso','finalizado'
  [tablaTodos,tablaPend,tablaFin].forEach(t=>t.style.display='none');
  document.querySelectorAll('.estado-menu-btn').forEach(b=>b.classList.remove('active'));
  const sel=document.getElementById('filterEstado');
  
  if(view==='todos'){
    tablaTodos.style.display='';
    tabTitle.textContent='Todos los Tickets';
    if(sel)sel.value='';
    document.getElementById('menuTodos').classList.add('active');
  } else if(view==='finalizado'){
    tablaFin.style.display='';
    tabTitle.textContent='Solicitudes Finalizadas';
    if(sel)sel.value='Finalizado';
    document.getElementById('menuFinalizadas').classList.add('active');
  } else {
    tablaPend.style.display='';
    if(view==='pendiente'){
      tabTitle.textContent='Solicitudes Pendientes';
      if(sel)sel.value='Pendiente';
      document.getElementById('menuPendientes').classList.add('active');
    } else if(view==='proceso'){
      tabTitle.textContent='Solicitudes en Proceso';
      if(sel)sel.value='En Proceso';
      document.getElementById('menuProceso').classList.add('active');
    } else {
      tabTitle.textContent='Solicitudes Activas';
      if(sel)sel.value='Pendiente';
      document.getElementById('menuPendientes').classList.add('active');
    }
  }
  aplicarFiltros();
}

// Menu clicks
document.getElementById('menuTodos').addEventListener('click',()=>setActiveView('todos'));
document.getElementById('menuPendientes').addEventListener('click',()=>setActiveView('pendiente'));
document.getElementById('menuProceso').addEventListener('click',()=>setActiveView('proceso'));
document.getElementById('menuFinalizadas').addEventListener('click',()=>setActiveView('finalizado'));

// Default: Pendientes
window.currentFreqFilter='';
setActiveView('pendiente');

// ── FILTROS ──
$.fn.dataTable.ext.search.push(function(s,d,di){
  try{
    const tid=s.nTable.getAttribute('id');if(tid!=='dtPendientes'&&tid!=='dtFinalizadas'&&tid!=='dtTodos')return true;
    const fe=(document.getElementById('filterEstado').value||'').trim();
    if(!fe)return true;
    const tr=s.aoData[di].nTr;if(!tr)return true;
    const sel=tr.querySelector('select.estado-select');return sel?sel.value===fe:true;
  }catch(e){return true}
});
$.fn.dataTable.ext.search.push(function(s,d,di){
  try{
    const tid=s.nTable.getAttribute('id');if(tid!=='dtPendientes'&&tid!=='dtFinalizadas'&&tid!=='dtTodos')return true;
    const tr=s.aoData[di].nTr;
    const rc=(tr.getAttribute('data-client-id')||'').toString(),rp=(tr.getAttribute('data-project-id')||'').toString();
    const sc=(document.getElementById('filterCliente').value||'').toString(),sp=(document.getElementById('filterProyecto').value||'').toString();
    if(sc&&rc!==sc)return false;if(sp&&rp!==sp)return false;return true;
  }catch(e){return true}
});
function ticketTieneAgente(agentIdsCsv, agenteId){
  if(!agenteId)return true;
  if(!agentIdsCsv)return false;
  return agentIdsCsv.split(',').map(x=>x.trim()).filter(Boolean).includes(String(agenteId));
}
function parseDateFromAttr(v){
  if(!v||v.startsWith('0000'))return null;
  const d=new Date(v.replace(' ','T'));
  return isNaN(d.getTime())?null:d;
}
$.fn.dataTable.ext.search.push(function(s,d,di){
  try{
    const tid=s.nTable.getAttribute('id');if(tid!=='dtPendientes'&&tid!=='dtFinalizadas'&&tid!=='dtTodos')return true;
    const fa=(document.getElementById('filterAgente').value||'').trim();
    if(!fa)return true;
    const tr=s.aoData[di].nTr;if(!tr)return true;
    return ticketTieneAgente(tr.getAttribute('data-agent-ids')||'',fa);
  }catch(e){return true}
});
$.fn.dataTable.ext.search.push(function(s,d,di){
  try{
    const tid=s.nTable.getAttribute('id');if(tid!=='dtPendientes'&&tid!=='dtFinalizadas'&&tid!=='dtTodos')return true;
    const fp=(document.getElementById('filterPrioridad').value||'').trim();
    if(!fp)return true;
    const tr=s.aoData[di].nTr;if(!tr)return true;
    return (tr.getAttribute('data-priority')||'')===fp;
  }catch(e){return true}
});
$.fn.dataTable.ext.search.push(function(s,d,di){
  try{
    const tid=s.nTable.getAttribute('id');
    if(tid!=='dtPendientes'&&tid!=='dtFinalizadas'&&tid!=='dtTodos')return true;
    const ff=(window.currentFreqFilter||'').toString();
    if(ff==='')return true;

    function normRepetir(v){
      if(v===null||v===undefined)return null;
      const s=String(v).replace(/<[^>]*>/g,'').trim();
      if(s===''||s==='0'||/^únic/i.test(s)||/^unic/i.test(s))return '0';
      if(s==='1'||/^diari/i.test(s))return '1';
      if(s==='2'||/^seman/i.test(s))return '2';
      if(s==='3'||/^mensu/i.test(s))return '3';
      const n=parseInt(s,10);
      if(n===1||n===2||n===3)return String(n);
      if(n===0)return '0';
      return null;
    }

    let rv=null;
    const row=s.aoData[di];
    const tr=row?row.nTr:null;
    if(tr){
      rv=normRepetir(tr.getAttribute('data-repetir'));
      if(rv===null){
        const td=tr.querySelector('td[data-repetir], td[data-search]');
        if(td){
          rv=normRepetir(td.getAttribute('data-repetir'))
            || normRepetir(td.getAttribute('data-search'))
            || normRepetir(td.textContent);
        }
      }
    }
    if(rv===null&&d&&d.length>8){
      rv=normRepetir(d[8]);
    }
    if(rv===null&&row&&row._aData&&row._aData.length>8){
      rv=normRepetir(row._aData[8]);
    }
    if(rv===null) rv='0';
    return rv===ff;
  }catch(e){return true}
});
$.fn.dataTable.ext.search.push(function(s,d,di){
  try{
    const tid=s.nTable.getAttribute('id');if(tid!=='dtPendientes'&&tid!=='dtFinalizadas'&&tid!=='dtTodos')return true;
    const fv=document.getElementById('filterDateFrom').value,tv=document.getElementById('filterDateTo').value;
    if(!fv&&!tv)return true;
    const tr=s.aoData[di].nTr;if(!tr)return false;
    const cd=parseDateFromAttr(tr.getAttribute('data-fecha-lim')||'');
    if(!cd)return false;
    if(fv&&cd<new Date(fv+'T00:00:00'))return false;
    if(tv&&cd>new Date(tv+'T23:59:59'))return false;
    return true;
  }catch(e){return true}
});

function updateStatsCounts(){
  try{
    const counts={Pendiente:0,'En Proceso':0,Finalizado:0};
    function countFrom(dt){if(!dt)return;
      dt.rows({search:'applied'}).every(function(){
        let et='';try{
          const tn=this.node();if(tn){const sel=tn.querySelector('select.estado-select');
          if(sel)et=(sel.value||'').toString().trim();
          else{const tds=tn.querySelectorAll('td');if(tds&&tds[2])et=(tds[2].textContent||'').toString().trim()}}
        }catch(e){}
        if(['Pendiente','En Proceso','Finalizado'].includes(et))counts[et]=(counts[et]||0)+1;
      });
    }
    countFrom(dtPendientes);countFrom(dtFinalizadas);
    const vP=counts.Pendiente||0,vPr=counts['En Proceso']||0,vF=counts.Finalizado||0,vT=vP+vPr+vF;
    function setNum(s,v){const e=document.querySelector(s);if(e)e.textContent=v}
    setNum('#statTodos .num',vT);
    setNum('#statPendientes .num',vP);
    setNum('#statProceso .num',vPr);
    setNum('#statFinalizadas .num',vF);
    // Total BD from dtTodos
    try{const tbd=dtTodos.rows({search:'applied'}).count();setNum('#statTotal .num',tbd)}catch(e){}
    // Bar
    const bp=document.querySelector('#statsProgressBar .bar-pend');
    const bpr=document.querySelector('#statsProgressBar .bar-proc');
    const bf=document.querySelector('#statsProgressBar .bar-fin');
    if(bp&&vT>0){bp.style.width=(vP/vT*100).toFixed(1)+'%';bpr.style.width=(vPr/vT*100).toFixed(1)+'%';bf.style.width=(vF/vT*100).toFixed(1)+'%'}
  }catch(e){}
}
dtTodos.on('draw',updateStatsCounts);
dtPendientes.on('draw',updateStatsCounts);
dtFinalizadas.on('draw',updateStatsCounts);

function aplicarFiltros(){
  [dtTodos,dtPendientes,dtFinalizadas].forEach(dt=>{
    try{dt.draw(false)}catch(e){try{dt.draw()}catch(e2){}}
  });
  try{updateStatsCounts()}catch(e){}
  try{updateFreqCounts()}catch(e){}
}

window.currentFreqFilter=window.currentFreqFilter||'';
function getActiveFreqTable(){
  if(tablaTodos&&tablaTodos.style.display!=='none')return dtTodos;
  if(tablaFin&&tablaFin.style.display!=='none')return dtFinalizadas;
  return dtPendientes;
}
function updateFreqCounts(){
  const counts={0:0,1:0,2:0,3:0};
  let total=0;
  try{
    const dt=getActiveFreqTable();
    if(dt){
      dt.rows().every(function(){
        const tr=this.node();
        if(!tr||tr.classList.contains('dataTables_empty'))return;
        let r=tr.getAttribute('data-repetir');
        if(r===null||r===''){
          const td=tr.querySelector('td[data-repetir]');
          if(td) r=td.getAttribute('data-repetir');
        }
        r=parseInt(r||'0',10);
        const key=(r===1||r===2||r===3)?r:0;
        counts[key]=(counts[key]||0)+1;
        total++;
      });
    }
  }catch(e){}
  const set=(id,val)=>{const el=document.getElementById(id);if(el)el.textContent=String(val);};
  set('freqCountAll',total);
  set('freqCount0',counts[0]||0);
  set('freqCount1',counts[1]||0);
  set('freqCount2',counts[2]||0);
  set('freqCount3',counts[3]||0);
}
function setFreqFilter(freq){
  window.currentFreqFilter=(freq===null||freq===undefined)?'':String(freq);
  document.querySelectorAll('#freqTabs .freq-tab').forEach(btn=>{
    const on=String(btn.getAttribute('data-freq')||'')===window.currentFreqFilter;
    btn.classList.toggle('active',on);
    btn.setAttribute('aria-selected',on?'true':'false');
  });
  aplicarFiltros();
}
document.querySelectorAll('#freqTabs .freq-tab').forEach(btn=>{
  btn.addEventListener('click',function(){
    setFreqFilter(this.getAttribute('data-freq')||'');
  });
});
dtTodos.on('draw',updateFreqCounts);
dtPendientes.on('draw',updateFreqCounts);
dtFinalizadas.on('draw',updateFreqCounts);
aplicarFiltros();

document.getElementById('filterEstado').addEventListener('change',function(){
  const v=this.value;
  if(!v)setActiveView('todos');
  else if(v==='Finalizado')setActiveView('finalizado');
  else if(v==='Pendiente')setActiveView('pendiente');
  else if(v==='En Proceso')setActiveView('proceso');
});
document.getElementById('filterPrioridad').addEventListener('change',aplicarFiltros);
document.getElementById('filterAgente').addEventListener('change',aplicarFiltros);
['filterDateFrom','filterDateTo'].forEach(id=>{const e=document.getElementById(id);if(e)e.addEventListener('change',aplicarFiltros)});
document.getElementById('clearDateFilters').addEventListener('click',()=>{document.getElementById('filterDateFrom').value='';document.getElementById('filterDateTo').value='';aplicarFiltros()});

// Cliente/Proyecto filters
const filterCliente=document.getElementById('filterCliente');
const filterProyecto=document.getElementById('filterProyecto');
async function cargarProyectosFiltro(cid){
  filterProyecto.innerHTML='<option value="">Todos los proyectos</option>';filterProyecto.disabled=true;
  if(!cid)return;
  try{const r=await fetch('proyectos_por_cliente.php?id_cliente='+encodeURIComponent(cid));const d=await r.json();
  if(d&&d.success&&d.proyectos&&d.proyectos.length){d.proyectos.forEach(p=>{const o=document.createElement('option');o.value=p.id_proyecto;o.textContent='#'+p.id_proyecto+' - '+p.nombre_proyecto;filterProyecto.appendChild(o)});filterProyecto.disabled=false}}catch(e){}
}
filterCliente.addEventListener('change',function(){cargarProyectosFiltro(this.value);aplicarFiltros()});
filterProyecto.addEventListener('change',aplicarFiltros);
if(ID_CLIENTE_URL>0&&filterCliente.querySelector('option[value="'+ID_CLIENTE_URL+'"]')){
  filterCliente.value=String(ID_CLIENTE_URL);
  cargarProyectosFiltro(ID_CLIENTE_URL).then(()=>aplicarFiltros());
}

// ── STAT CARDS CLICKABLE ──
const cardMap={statTodos:'',statPendientes:'Pendiente',statProceso:'En Proceso',statFinalizadas:'Finalizado'};
Object.entries(cardMap).forEach(([id,est])=>{
  const e=document.getElementById(id);if(!e)return;
  e.addEventListener('click',function(){
    document.querySelectorAll('.stat-box').forEach(b=>b.classList.remove('stat-active'));
    if(est)this.classList.add('stat-active');
    const sel=document.getElementById('filterEstado');
    if(sel){sel.value=est;aplicarFiltros();if(!est)setActiveView('todos');else if(est==='Finalizado')setActiveView('finalizado');else setActiveView(est==='Pendiente'?'pendiente':'proceso')}
  });
});

// ── MODAL DESCRIPCIÓN ──
const modalVerDesc=document.getElementById('modalVerDesc');
document.getElementById('closeVerDesc').addEventListener('click',()=>{modalVerDesc.classList.remove('active');document.body.style.overflow='auto'});
document.getElementById('cancelVerDesc').addEventListener('click',()=>{modalVerDesc.classList.remove('active');document.body.style.overflow='auto'});
modalVerDesc.addEventListener('click',(e)=>{if(e.target===modalVerDesc){modalVerDesc.classList.remove('active');document.body.style.overflow='auto'}});
document.addEventListener('click',async function(e){
  const btn=e.target.closest('.btn-ver-desc');if(!btn)return;
  const id=parseInt(btn.getAttribute('data-id')||'0',10);
  const descEl=document.getElementById('contenidoDescripcion');
  if(descEl)descEl.innerHTML='<p class="tk-muted" style="text-align:center;padding:20px 0">Cargando…</p>';
  document.getElementById('contenidoImagenes').innerHTML='';
  const ca=document.getElementById('contenidoArchivos');if(ca)ca.innerHTML='';
  const labImg=document.getElementById('labelImagenesDesc');const labFil=document.getElementById('labelArchivosDesc');
  if(labImg)labImg.hidden=true;if(labFil)labFil.hidden=true;
  modalVerDesc.classList.add('active');document.body.style.overflow='hidden';
  try{
    let desc='',imgs=[],files=[],meta={id:0,titulo:'',cliente:'',proyecto:'',url:'',prioridad:'',estado:'',fecha_lim:''};
    if(id>0){
      const r=await fetch('obtener.php?id='+encodeURIComponent(id)+'&_ts='+Date.now());
      const d=await r.json();
      if(!d||!d.success||!d.solicitud)throw new Error((d&&d.error)||'No se pudo cargar');
      const s=d.solicitud;
      desc=s.descripcion_text||'';
      imgs=s.descripcion_images||[];
      files=s.descripcion_files||[];
      meta={
        id:s.id||id,
        titulo:s.titulo||'',
        cliente:s.cliente_nombre||'',
        proyecto:s.nombre_proyecto||'',
        url:s.url_proyecto||'',
        prioridad:s.prioridad||'',
        estado:s.estado||'',
        fecha_lim:s.fecha_lim||''
      };
    }else{
      desc=btn.getAttribute('data-desc')||'';
      try{imgs=JSON.parse(btn.getAttribute('data-images')||'[]')}catch(_e){}
      try{files=JSON.parse(btn.getAttribute('data-files')||'[]')}catch(_e){}
      meta={
        id:0,
        titulo:btn.getAttribute('data-titulo')||'',
        cliente:btn.getAttribute('data-cliente')||'',
        proyecto:btn.getAttribute('data-proyecto')||'',
        url:btn.getAttribute('data-url')||'',
        prioridad:btn.getAttribute('data-prioridad')||'',
        estado:btn.getAttribute('data-estado')||'',
        fecha_lim:btn.getAttribute('data-fecha')||''
      };
    }
    if(typeof bindTicketDescModalChrome==='function')bindTicketDescModalChrome();
    if(typeof fillTicketDescModal==='function'){
      fillTicketDescModal({desc:desc,images:imgs,files:files,meta:meta,onImageClick:openLightbox});
    }else{
      const metaHost=document.getElementById('tkDescMeta');
      const metaHtml=typeof renderTicketMeta==='function'?renderTicketMeta(meta):'';
      if(metaHost)metaHost.innerHTML=metaHtml;
      document.getElementById('contenidoDescripcion').innerHTML=formatDescriptionText(desc||'');
    }
  }catch(err){
    console.error(err);
    if(descEl)descEl.innerHTML='<p class="tk-muted" style="text-align:center;padding:20px 0">No se pudo cargar la descripción</p>';
  }
});

// ── LIGHTBOX ──
const lo=document.getElementById('lightboxOverlay'),li=document.getElementById('lightboxImage');
const lc=document.getElementById('lightboxClose'),lp=document.getElementById('lightboxPrev'),ln=document.getElementById('lightboxNext');
function openLightbox(imgs,i){currentLightboxImages=imgs;currentLightboxIndex=i;updateLightbox();lo.classList.add('active');document.body.style.overflow='hidden';document.addEventListener('keydown',hlk)}
function updateLightbox(){li.src=currentLightboxImages[currentLightboxIndex];const c=document.getElementById('lightboxCounter');if(c)c.textContent=(currentLightboxIndex+1)+' / '+currentLightboxImages.length}
function closeLightbox(){lo.classList.remove('active');document.body.style.overflow='auto';document.removeEventListener('keydown',hlk)}
function navLightbox(d){currentLightboxIndex=(currentLightboxIndex+d+currentLightboxImages.length)%currentLightboxImages.length;updateLightbox()}
function hlk(e){if(e.key==='Escape')closeLightbox();if(e.key==='ArrowLeft')navLightbox(-1);if(e.key==='ArrowRight')navLightbox(1)}
lc.addEventListener('click',closeLightbox);lp.addEventListener('click',()=>navLightbox(-1));ln.addEventListener('click',()=>navLightbox(1));
lo.addEventListener('click',(e)=>{if(e.target===lo)closeLightbox()});

// ── MODAL NOTAS ──
const modalNotas=document.getElementById('modalNotas');
document.getElementById('closeNotas').addEventListener('click',()=>{modalNotas.classList.remove('active');document.body.style.overflow='auto'});
document.getElementById('cancelNotas').addEventListener('click',()=>{modalNotas.classList.remove('active');document.body.style.overflow='auto'});
modalNotas.addEventListener('click',(e)=>{if(e.target===modalNotas){modalNotas.classList.remove('active');document.body.style.overflow='auto'}});
async function cargarNotas(id){
  document.getElementById('listaNotas').innerHTML='Cargando...';
  try{const r=await fetch('notas_listar.php?id='+encodeURIComponent(id));const h=await r.text();
  document.getElementById('listaNotas').innerHTML=h;
  document.getElementById('listaNotas').querySelectorAll('.nota-texto,.nota-contenido,.nota-body').forEach(el=>{el.innerHTML=formatDescriptionText(el.textContent||'')});
  document.getElementById('listaNotas').querySelectorAll('.content-image-thumb').forEach((img,i)=>{const p=img.closest('.nota-images');if(p){const imgs=Array.from(p.querySelectorAll('.content-image-thumb')).map(x=>x.src);img.onclick=()=>openLightbox(imgs,i)}})
  }catch(e){document.getElementById('listaNotas').innerHTML='No se pudieron cargar las notas'}
}
function openNotasModal(id){
  notasTicketIdActual=id;
  document.getElementById('notasTicketId').textContent='#'+id;
  notaImages=[];document.getElementById('notaImagePreviews').innerHTML='';
  document.getElementById('notaTexto').value='';
  cargarNotas(id);modalNotas.classList.add('active');document.body.style.overflow='hidden';
}
window.openNotasModal=openNotasModal;

document.getElementById('guardarNotaBtn').addEventListener('click',async()=>{
  const txt=document.getElementById('notaTexto').value.trim();
  if(!txt&&notaImages.length===0)return;
  const fd=new FormData();fd.set('solicitud_id',notasTicketIdActual);fd.set('autor','admin');fd.set('nota',txt);
  notaImages.forEach(img=>fd.append('nota_images[]',img));
  try{const r=await fetch('notas_guardar.php',{method:'POST',body:fd});const ok=await r.text();
  if(ok.trim()==='OK'){
    document.getElementById('notaTexto').value='';notaImages=[];document.getElementById('notaImagePreviews').innerHTML='';
    await cargarNotas(notasTicketIdActual);
    const sp=document.querySelector('.notes-count[data-ticket="'+notasTicketIdActual+'"]');
    if(sp){const c=parseInt(sp.textContent.trim())||0;sp.textContent=c+1;sp.classList.remove('empty');sp.classList.add('has')}
  }}catch(e){Swal.fire({title:'Error',text:'No se pudo guardar',icon:'error'})}
});

// ── IMAGE UPLOAD HELPERS ──
function handleImageUpload(ev,arr,pid){
  const files=ev.target.files,pc=document.getElementById(pid);
  for(let i=0;i<files.length;i++){const file=files[i];if(!file.type.match('image.*'))continue;
  arr.push(file);const reader=new FileReader();
  reader.onload=function(e){
    const p=document.createElement('div');p.className='image-preview';
    const img=document.createElement('img');img.src=e.target.result;
    const rb=document.createElement('div');rb.className='remove-image';rb.innerHTML='&times;';
    rb.onclick=function(){const idx=arr.indexOf(file);if(idx>-1)arr.splice(idx,1);p.remove()};
    p.appendChild(img);p.appendChild(rb);pc.appendChild(p)
  };reader.readAsDataURL(file)}
  ev.target.value='';
}
document.getElementById('descImageUpload').addEventListener('change',function(e){handleImageUpload(e,descripcionImages,'descImagePreviews')});
document.getElementById('notaImageUpload').addEventListener('change',function(e){handleImageUpload(e,notaImages,'notaImagePreviews')});

// ── FILE UPLOAD ──
(function(){
  const du=document.getElementById('descFileUpload'),dl=document.getElementById('descFileList');
  if(!du||!dl)return;
  function render(){dl.innerHTML='';
    const t=document.createElement('div');t.style.margin='4px 0';t.style.fontWeight='600';t.textContent='Archivos a subir:';dl.appendChild(t);
    if(!descNewFiles.length){const m=document.createElement('div');m.style.fontSize='0.85rem';m.style.color='#6c757d';m.textContent='Ninguno';dl.appendChild(m);return}
    const ul=document.createElement('ul');ul.style.margin='0';ul.style.paddingLeft='16px';
    descNewFiles.forEach((f,idx)=>{
      const li=document.createElement('li');li.style.display='flex';li.style.alignItems='center';li.style.gap='8px';
      const sz=(f.size/1024).toFixed(1),ns=document.createElement('span');ns.textContent=f.name+' ('+sz+' KB)';
      const rb=document.createElement('button');rb.type='button';rb.style.cssText='padding:1px 6px;font-size:0.75rem;background:#e9ecef;border:1px solid #ced4da;border-radius:4px;cursor:pointer';
      rb.textContent='Quitar';rb.addEventListener('click',()=>{descNewFiles.splice(idx,1);render()});
      li.appendChild(ns);li.appendChild(rb);ul.appendChild(li)
    });dl.appendChild(ul)
  }
  du.addEventListener('change',function(){Array.from(this.files||[]).forEach(f=>descNewFiles.push(f));this.value='';render()});render()
})();

function renderExistingFilesList(container,files){
  if(!container)return;container.innerHTML='';
  const t=document.createElement('div');t.style.margin='4px 0';t.style.fontWeight='600';t.textContent='Archivos ya cargados:';container.appendChild(t);
  if(!files||!files.length){const m=document.createElement('div');m.style.fontSize='0.85rem';m.style.color='#6c757d';m.textContent='No hay';container.appendChild(m);return}
  const ul=document.createElement('ul');ul.style.margin='0';ul.style.paddingLeft='16px';
  files.forEach(f=>{
    const li=document.createElement('li');li.style.display='flex';li.style.alignItems='center';li.style.gap='8px';
    const a=document.createElement('a');a.href=f;a.target='_blank';a.rel='noopener noreferrer';a.textContent=f.split('/').pop()||f.split('\\').pop()||f;
    const lb=document.createElement('label');lb.style.display='inline-flex';lb.style.alignItems='center';lb.style.gap='4px';lb.style.cursor='pointer';
    const cb=document.createElement('input');cb.type='checkbox';cb.dataset.path=f;
    const sp=document.createElement('span');sp.textContent='Quitar';
    cb.addEventListener('change',()=>{if(cb.checked){removedDescFiles.add(f);a.style.textDecoration='line-through';a.style.opacity='.7'}else{removedDescFiles.delete(f);a.style.textDecoration='';a.style.opacity=''}
    const kept=existingDescFiles.filter(x=>!removedDescFiles.has(x));const h=document.getElementById('descripcionExistingFiles');if(h)h.value=JSON.stringify(kept)});
    lb.appendChild(cb);lb.appendChild(sp);li.appendChild(a);li.appendChild(lb);ul.appendChild(li)
  });container.appendChild(ul);
  const h=document.getElementById('descripcionExistingFiles');if(h)h.value=JSON.stringify(files)
}

// ── AUDIO A TEXTO — Chrome, Edge, Safari (dictado vivo + Whisper respaldo) ──
const AudioTranscripcion=(function(){
  let mediaRecorder=null,mediaStream=null,chunks=[],whisperMime='';
  let activeTarget=null,activeBtn=null,activeStatus=null;
  const dictado={
    active:false,listening:false,starting:false,needsNewRec:false,
    rec:null,baseText:'',interim:'',restartTimer:null,micDenied:false
  };

  function isSafari(){
    const ua=navigator.userAgent||'';
    return /Safari/i.test(ua)&&!/Chrome|Chromium|CriOS|FxiOS|EdgiOS|Edg\//i.test(ua);
  }
  function isIOS(){
    return /iPad|iPhone|iPod/i.test(navigator.userAgent||'')||(navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1);
  }
  function isAppleLike(){return isSafari()||isIOS();}

  function speechApi(){
    return window.SpeechRecognition||window.webkitSpeechRecognition||null;
  }
  function dictadoVivoDisponible(){
    return !!speechApi()&&!!window.isSecureContext;
  }
  function grabacionDisponible(){
    return !!window.isSecureContext&&!!(navigator.mediaDevices&&navigator.mediaDevices.getUserMedia);
  }
  function micDisponible(){
    return dictadoVivoDisponible()||grabacionDisponible();
  }

  function clearRestartTimer(){
    if(dictado.restartTimer){
      clearTimeout(dictado.restartTimer);
      dictado.restartTimer=null;
    }
  }

  function appendText(el,text){
    if(!el||!text)return;
    const sep=el.value&& !el.value.endsWith(' ')&&!el.value.endsWith('\n')?' ':'';
    el.value=(el.value||'')+sep+text.trim();
    el.dispatchEvent(new Event('input',{bubbles:true}));
    el.focus();
  }

  function setUiRecording(btn,statusEl,isRec,isProc,modo){
    if(btn){
      btn.classList.toggle('recording',!!isRec);
      btn.classList.toggle('processing',!!isProc);
      btn.disabled=!!isProc;
      const icon=btn.querySelector('i');
      if(icon)icon.className=isProc?'fas fa-spinner fa-spin':(isRec?'fas fa-stop':'fas fa-microphone');
      if(isRec&&modo==='dictado')btn.title='Detener dictado';
      else if(isRec&&modo==='grabacion')btn.title='Detener y transcribir';
      else if(dictadoVivoDisponible())btn.title='Dictado de voz — clic para hablar';
      else btn.title='Grabar audio — clic para hablar, clic de nuevo para transcribir (Safari)';
    }
    if(statusEl){
      statusEl.classList.toggle('active',!!isRec||!!isProc);
      statusEl.classList.toggle('recording',!!isRec);
      if(isProc)statusEl.innerHTML='<i class="fas fa-spinner fa-spin"></i> Transcribiendo audio…';
      else if(isRec&&modo==='dictado')statusEl.innerHTML='<i class="fas fa-circle" style="font-size:8px;vertical-align:middle"></i> Escuchando… habla · clic para detener';
      else if(isRec&&modo==='grabacion')statusEl.innerHTML='<i class="fas fa-circle" style="font-size:8px;vertical-align:middle"></i> Grabando… clic de nuevo para transcribir';
      else statusEl.innerHTML='';
    }
  }

  function applyDictadoAlCampo(target){
    if(!target)return;
    const base=dictado.baseText||'';
    const interim=dictado.interim||'';
    const joined=(base+(base&&interim?' ':'')+interim).replace(/\s+/g,' ').trim();
    if(target.value!==joined){
      target.value=joined;
      target.dispatchEvent(new Event('input',{bubbles:true}));
    }
  }

  function syncTextoDictado(target){
    if(!target)return;
    dictado.baseText=String(target.value||'');
    dictado.interim='';
  }

  function nombreAudioWhisper(blob){
    const t=(blob&&blob.type)||whisperMime||'';
    if(t.includes('mp4')||t.includes('m4a')||t.includes('aac'))return 'grabacion.m4a';
    if(t.includes('ogg'))return 'grabacion.ogg';
    return 'grabacion.webm';
  }

  async function transcribirBlob(blob,filename){
    const fd=new FormData();
    fd.append('audio',blob,filename||nombreAudioWhisper(blob));
    const r=await fetch('api_transcribir_audio.php',{method:'POST',body:fd,credentials:'same-origin'});
    let d=null;
    try{d=await r.json()}catch(parseErr){
      throw new Error('Respuesta inválida del servidor. Recarga la página e intenta de nuevo.');
    }
    if(!r.ok||!d||!d.success)throw new Error((d&&d.error)||'No se pudo transcribir el audio');
    return d.texto||'';
  }

  function msgPermisoMic(){
    if(isIOS()){
      return 'En iPhone/iPad: Ajustes → Safari → Micrófono → Permitir. Luego recarga esta página.';
    }
    if(isSafari()){
      return 'En Safari: Safari → Ajustes → Sitios web → Micrófono → Permitir para este sitio. Luego recarga.';
    }
    return 'Clic en el candado 🔒 junto a la URL → Micrófono → Permitir → recarga la página.';
  }

  function wireRecognitionHandlers(rec,target,btn,statusEl){
    rec.onstart=()=>{
      if(!dictado.active){
        try{rec.stop()}catch(e){}
        return;
      }
      dictado.listening=true;
      dictado.starting=false;
      setUiRecording(btn,statusEl,true,false,'dictado');
    };

    rec.onresult=(ev)=>{
      if(!dictado.active||!target)return;
      let finalChunk='',interimChunk='';
      for(let i=ev.resultIndex;i<ev.results.length;i++){
        const res=ev.results[i];
        const txt=(res[0]&&res[0].transcript)?String(res[0].transcript):'';
        if(!txt)continue;
        if(res.isFinal)finalChunk+=(finalChunk?' ':'')+txt;
        else interimChunk+=(interimChunk?' ':'')+txt;
      }
      if(finalChunk){
        dictado.baseText=(dictado.baseText+' '+finalChunk).replace(/\s+/g,' ').trim();
        dictado.interim='';
      }else{
        dictado.interim=interimChunk.replace(/\s+/g,' ').trim();
      }
      applyDictadoAlCampo(target);
    };

    rec.onerror=(ev)=>{
      const code=(ev&&ev.error)?String(ev.error):'';
      if(code==='aborted'||code==='no-speech')return;
      dictado.active=false;
      dictado.listening=false;
      dictado.starting=false;
      dictado.needsNewRec=true;
      clearRestartTimer();
      setUiRecording(btn,statusEl,false,false);
      const msgs={
        'not-allowed':msgPermisoMic(),
        'service-not-allowed':'Dictado bloqueado. Usa HTTPS o localhost.',
        'network':'Se necesita internet para el dictado. Revisa tu conexión.',
        'audio-capture':'No se detectó micrófono. Conecta uno o revisa permisos.'
      };
      if(code==='not-allowed'||code==='service-not-allowed')dictado.micDenied=true;
      Swal.fire({icon:'warning',title:'Dictado de voz',text:msgs[code]||('Error de dictado: '+code),timer:4500});
    };

    rec.onend=()=>{
      dictado.listening=false;
      clearRestartTimer();
      const restartMs=isAppleLike()?320:200;
      if(dictado.active&&dictado.rec){
        dictado.restartTimer=setTimeout(()=>{
          dictado.restartTimer=null;
          if(!dictado.active)return;
          try{
            if(dictado.rec)dictado.rec.start();
          }catch(e){
            dictado.needsNewRec=true;
            if(!beginDictado(target,btn,statusEl)){
              dictado.active=false;
              dictado.starting=false;
              setUiRecording(btn,statusEl,false,false);
            }
          }
        },restartMs);
        return;
      }
      dictado.active=false;
      dictado.starting=false;
      dictado.interim='';
      setUiRecording(btn,statusEl,false,false);
    };
  }

  function createRecognition(target,btn,statusEl){
    const SR=speechApi();
    if(!SR)return null;
    const rec=new SR();
    rec.lang='es-MX';
    rec.continuous=!isIOS();
    rec.interimResults=true;
    rec.maxAlternatives=1;
    wireRecognitionHandlers(rec,target,btn,statusEl);
    return rec;
  }

  function ensureRecognition(target,btn,statusEl){
    if(dictado.rec&&!dictado.needsNewRec)return dictado.rec;
    dictado.rec=createRecognition(target,btn,statusEl);
    dictado.needsNewRec=false;
    return dictado.rec;
  }

  function beginDictado(target,btn,statusEl){
    if(!dictado.active)return false;
    const rec=ensureRecognition(target,btn,statusEl);
    if(!rec)return false;
    try{
      rec.start();
      return true;
    }catch(e){
      try{rec.abort()}catch(e2){}
      dictado.needsNewRec=true;
      const rec2=ensureRecognition(target,btn,statusEl);
      if(!rec2)return false;
      try{
        rec2.start();
        return true;
      }catch(e3){
        dictado.active=false;
        dictado.listening=false;
        dictado.starting=false;
        dictado.needsNewRec=true;
        setUiRecording(btn,statusEl,false,false);
        return false;
      }
    }
  }

  function detenerDictado(btn,statusEl){
    dictado.active=false;
    dictado.listening=false;
    dictado.starting=false;
    dictado.interim='';
    clearRestartTimer();
    setUiRecording(btn,statusEl,false,false);
    if(!dictado.rec)return;
    try{dictado.rec.stop()}catch(e){
      try{dictado.rec.abort()}catch(e2){}
    }
  }

  function iniciarDictadoVivo(target,btn,statusEl){
    if(!dictadoVivoDisponible())return false;
    if(dictado.micDenied){
      Swal.fire({icon:'info',title:'Micrófono bloqueado',text:msgPermisoMic(),timer:5500});
      return false;
    }
    if(dictado.active){
      detenerDictado(btn,statusEl);
      return true;
    }
    detenerStream();
    if(mediaRecorder&&mediaRecorder.state==='recording'){
      try{mediaRecorder.stop()}catch(e){}
      mediaRecorder=null;
      chunks=[];
    }
    syncTextoDictado(target);
    dictado.active=true;
    dictado.starting=true;
    activeTarget=target;activeBtn=btn;activeStatus=statusEl;
    setUiRecording(btn,statusEl,true,false,'dictado');
    if(!beginDictado(target,btn,statusEl))return false;
    if(!isIOS()){try{target.focus()}catch(e){}}
    return true;
  }

  async function detenerGrabacion(){
    return new Promise((resolve)=>{
      if(!mediaRecorder||mediaRecorder.state==='inactive'){resolve(null);return}
      mediaRecorder.onstop=()=>{
        const mime=mediaRecorder.mimeType||whisperMime||'audio/webm';
        resolve(new Blob(chunks,{type:mime}));
        chunks=[];
      };
      mediaRecorder.stop();
    });
  }

  function detenerStream(){
    if(mediaStream){
      mediaStream.getTracks().forEach(t=>t.stop());
      mediaStream=null;
    }
  }

  function mimeGrabacionSafari(){
    if(typeof MediaRecorder==='undefined')return '';
    if(MediaRecorder.isTypeSupported('audio/mp4'))return 'audio/mp4';
    if(MediaRecorder.isTypeSupported('audio/webm;codecs=opus'))return 'audio/webm;codecs=opus';
    if(MediaRecorder.isTypeSupported('audio/webm'))return 'audio/webm';
    if(MediaRecorder.isTypeSupported('video/mp4'))return 'video/mp4';
    return '';
  }

  async function iniciarGrabacionWhisper(target,btn,statusEl){
    if(!grabacionDisponible())return false;
    try{
      mediaStream=await navigator.mediaDevices.getUserMedia({audio:true});
      whisperMime=mimeGrabacionSafari();
      const opts=whisperMime?{mimeType:whisperMime}:{};
      mediaRecorder=new MediaRecorder(mediaStream,opts);
      if(mediaRecorder.mimeType)whisperMime=mediaRecorder.mimeType;
      chunks=[];
      mediaRecorder.ondataavailable=(e)=>{if(e.data&&e.data.size>0)chunks.push(e.data)};
      mediaRecorder.start(isAppleLike()?500:250);
      activeTarget=target;activeBtn=btn;activeStatus=statusEl;
      setUiRecording(btn,statusEl,true,false,'grabacion');
      return true;
    }catch(e){
      detenerStream();
      return false;
    }
  }

  function toggleMic(target,btn,statusEl){
    if(!target||!btn)return;

    if(!window.isSecureContext){
      Swal.fire({icon:'info',title:'Micrófono',html:'El dictado requiere <strong>HTTPS</strong> o <strong>localhost</strong>.',timer:4500});
      return;
    }

    if(dictado.active&&(activeBtn&&activeBtn!==btn)){
      detenerDictado(activeBtn,activeStatus);
    }
    if(dictado.active||dictado.listening||dictado.starting){
      detenerDictado(btn,statusEl);
      return;
    }

    if(mediaRecorder&&mediaRecorder.state==='recording'){
      toggleMicWhisperStop(target,btn,statusEl);
      return;
    }

    if(dictadoVivoDisponible()){
      if(iniciarDictadoVivo(target,btn,statusEl))return;
      if(grabacionDisponible()){
        toggleMicWhisperStart(target,btn,statusEl);
        return;
      }
      Swal.fire({icon:'warning',title:'Dictado',text:'No se pudo iniciar el micrófono. Recarga e intenta de nuevo.',timer:3500});
      return;
    }

    if(grabacionDisponible()){
      toggleMicWhisperStart(target,btn,statusEl);
      return;
    }

    Swal.fire({icon:'info',title:'Dictado no disponible',text:'Usa Chrome, Edge o Safari reciente con HTTPS.',timer:4000});
  }

  async function toggleMicWhisperStop(target,btn,statusEl){
    setUiRecording(btn,statusEl,false,true);
    const blob=await detenerGrabacion();
    detenerStream();
    mediaRecorder=null;
    try{
      if(blob&&blob.size>800){
        appendText(target,await transcribirBlob(blob,nombreAudioWhisper(blob)));
        Swal.fire({toast:true,position:'top-end',icon:'success',title:'Audio transcrito',timer:1800,showConfirmButton:false});
      }else if(blob&&blob.size>0){
        Swal.fire({toast:true,position:'top-end',icon:'info',title:'Audio muy corto',text:'Habla un poco más e intenta de nuevo.',timer:2500,showConfirmButton:false});
      }
    }catch(e){
      Swal.fire({icon:'warning',title:'Transcripción',text:e.message||'No se pudo transcribir.',timer:3500});
    }
    setUiRecording(btn,statusEl,false,false);
  }

  async function toggleMicWhisperStart(target,btn,statusEl){
    activeTarget=target;activeBtn=btn;activeStatus=statusEl;
    const grabo=await iniciarGrabacionWhisper(target,btn,statusEl);
    if(!grabo){
      Swal.fire({icon:'warning',title:'Micrófono',text:msgPermisoMic(),timer:4500});
      return;
    }
    if(isSafari()&&!dictadoVivoDisponible()){
      Swal.fire({toast:true,position:'top-end',icon:'info',title:'Modo Safari',text:'Habla y vuelve a pulsar el micrófono para transcribir.',timer:3200,showConfirmButton:false});
    }
  }

  function stopAll(){
    detenerDictado(activeBtn,activeStatus);
    if(mediaRecorder&&mediaRecorder.state==='recording'){try{mediaRecorder.stop()}catch(e){}}
    detenerStream();
    mediaRecorder=null;chunks=[];whisperMime='';
    [activeBtn,document.getElementById('btnMicChat'),document.getElementById('btnMicDescripcion')].forEach(b=>{
      if(b)setUiRecording(b,null,false,false);
    });
    ['chatVozEstado','descVozEstado'].forEach(id=>{const el=document.getElementById(id);if(el){el.classList.remove('active','recording');el.innerHTML=''}});
  }

  function bind(btnId,targetId,statusId){
    const btn=document.getElementById(btnId);
    const target=document.getElementById(targetId);
    const statusEl=statusId?document.getElementById(statusId):null;
    if(!btn||!target)return;
    if(!micDisponible())btn.title='Dictado requiere HTTPS (Chrome, Edge o Safari)';
    else if(!dictadoVivoDisponible()&&grabacionDisponible())btn.title='Grabar y transcribir (Safari) — clic, habla, clic de nuevo';
    target.addEventListener('input',function(){
      if(dictado.active)syncTextoDictado(target);
    });
    btn.addEventListener('click',function(e){
      e.preventDefault();
      toggleMic(target,btn,statusEl);
    });
  }

  return {bind,stopAll,micDisponible,dictadoVivoDisponible};
})();
AudioTranscripcion.bind('btnMicChat','chatInput','chatVozEstado');
AudioTranscripcion.bind('btnMicDescripcion','descripcionTextarea','descVozEstado');

// ── MODAL SOLICITUD ──
const modalS=document.getElementById('modalSolicitud');
let chatHistory=[],chatDocumento=null,chatListo=false,chatDescripcionMarkdown='',chatContextoActual=null;
let chatPendingImages=[],chatTicketImages=[];
const CHAT_MAX_IMAGES=5;

async function compressImageForChat(file){
  const maxDim=1280,maxBytes=4*1024*1024;
  if(!file.type.match(/^image\/(jpeg|jpg|png|gif|webp)$/i)){
    Swal.fire({toast:true,position:'top-end',icon:'warning',title:'Formato no válido',text:'Solo imágenes PNG, JPG o WEBP',timer:2500,showConfirmButton:false});
    return null;
  }
  if(file.size>maxBytes){
    Swal.fire({toast:true,position:'top-end',icon:'warning',title:'Imagen muy grande',text:'Máximo 4 MB por archivo',timer:2500,showConfirmButton:false});
    return null;
  }
  return new Promise(resolve=>{
    const img=new Image();
    const reader=new FileReader();
    reader.onload=()=>{
      img.onload=()=>{
        let w=img.width,h=img.height;
        if(w>maxDim||h>maxDim){
          if(w>=h){h=Math.round(h*maxDim/w);w=maxDim;}
          else{w=Math.round(w*maxDim/h);h=maxDim;}
        }
        const canvas=document.createElement('canvas');
        canvas.width=w;canvas.height=h;
        const ctx=canvas.getContext('2d');
        if(!ctx){resolve(null);return;}
        ctx.drawImage(img,0,0,w,h);
        const usePng=file.type==='image/png';
        const mime=usePng?'image/png':'image/jpeg';
        canvas.toBlob(blob=>{
          if(!blob){resolve(null);return;}
          const base=(file.name||'').replace(/\.[^.]+$/,'').replace(/[^a-zA-Z0-9._-]+/g,'_')||'captura';
          const uniq=Date.now().toString(36)+'_'+Math.random().toString(36).slice(2,6);
          const outName=base+'_'+uniq+(usePng?'.png':'.jpg');
          const outFile=new File([blob],outName,{type:mime,lastModified:Date.now()});
          const r2=new FileReader();
          r2.onload=()=>resolve({file:outFile,dataUrl:r2.result});
          r2.onerror=()=>resolve(null);
          r2.readAsDataURL(outFile);
        },mime,usePng?undefined:0.82);
      };
      img.onerror=()=>resolve(null);
      img.src=reader.result;
    };
    reader.onerror=()=>resolve(null);
    reader.readAsDataURL(file);
  });
}

async function addChatImagesFromFiles(files){
  for(const file of files){
    if(chatPendingImages.length>=CHAT_MAX_IMAGES){
      Swal.fire({toast:true,position:'top-end',icon:'warning',title:'Máximo '+CHAT_MAX_IMAGES+' imágenes por mensaje',timer:2500,showConfirmButton:false});
      break;
    }
    const compressed=await compressImageForChat(file);
    if(compressed)chatPendingImages.push(compressed);
  }
  renderChatPendingImages();
}

function renderChatPendingImages(){
  const pc=document.getElementById('chatImagePreviews');
  const wrap=document.getElementById('chatAttachmentsWrap');
  if(!pc)return;
  pc.innerHTML='';
  chatPendingImages.forEach((item,idx)=>{
    const p=document.createElement('div');
    p.className='image-preview';
    const img=document.createElement('img');
    img.src=item.dataUrl;
    img.title=item.file.name;
    const rb=document.createElement('div');
    rb.className='remove-image';
    rb.innerHTML='&times;';
    rb.onclick=()=>{chatPendingImages.splice(idx,1);renderChatPendingImages();};
    p.appendChild(img);p.appendChild(rb);pc.appendChild(p);
  });
  if(wrap)wrap.style.display=chatPendingImages.length?'block':'none';
}

function clearChatPendingImages(){
  chatPendingImages=[];
  renderChatPendingImages();
  const input=document.getElementById('chatImageUpload');
  if(input)input.value='';
}

function appendFileToDescPreviews(file,dataUrl){
  if(!file)return false;
  const already=descripcionImages.some(f=>f===file||(f&&f.name===file.name&&f.size===file.size&&f.lastModified===file.lastModified));
  if(already)return false;
  descripcionImages.push(file);
  const pc=document.getElementById('descImagePreviews');
  if(pc){
    const p=document.createElement('div');
    p.className='image-preview';
    const img=document.createElement('img');
    img.src=dataUrl||URL.createObjectURL(file);
    const rb=document.createElement('div');
    rb.className='remove-image';
    rb.innerHTML='&times;';
    rb.onclick=function(){const i=descripcionImages.indexOf(file);if(i>-1)descripcionImages.splice(i,1);p.remove();};
    p.appendChild(img);p.appendChild(rb);pc.appendChild(p);
  }
  return true;
}

function transferChatImagesToForm(){
  // Incluir enviadas al chat + pendientes aún no enviadas
  const all=[].concat(chatTicketImages||[],chatPendingImages||[]);
  let added=0;
  all.forEach(item=>{
    const file=(item&&item.file)?item.file:item;
    if(!(file instanceof File)&&!(file instanceof Blob))return;
    const asFile=(file instanceof File)?file:new File([file],'captura_'+Date.now()+'.jpg',{type:file.type||'image/jpeg'});
    if(appendFileToDescPreviews(asFile,item&&item.dataUrl?item.dataUrl:null))added++;
  });
  return added;
}

function modoConProyecto(){
  const sw=document.getElementById('switchModoProyecto');
  return !sw||sw.checked;
}
function getContextoEmptyMessage(){
  return modoConProyecto()
    ? 'Selecciona cliente y proyecto para que el asistente use descripción, stack y tecnologías del proyecto.'
    : 'Selecciona un cliente. El ticket se guardará sin proyecto vinculado.';
}
function setModoProyecto(conProyecto){
  const sw=document.getElementById('switchModoProyecto');
  const wrap=document.getElementById('proyectoContextoWrap');
  const hint=document.getElementById('modoProyectoHint');
  const sel=document.getElementById('selectProyecto');
  const hidden=document.getElementById('idProyectoVacio');
  if(sw)sw.checked=!!conProyecto;
  if(wrap)wrap.style.display=conProyecto?'':'none';
  if(hint)hint.textContent=conProyecto
    ? 'El ticket se vinculará al cliente y al proyecto seleccionado.'
    : 'El ticket se guardará solo con el cliente, sin proyecto.';
  if(sel){
    if(conProyecto){
      sel.setAttribute('name','id_proyecto');
    }else{
      sel.removeAttribute('name');
      sel.value='';
      sel.disabled=true;
    }
  }
  if(hidden){
    hidden.disabled=!!conProyecto;
    if(!conProyecto)hidden.value='';
  }
}
function getContextoIds(){
  const idCliente=parseInt(document.getElementById('selectCliente')?.value||'0',10)||0;
  const idProyecto=modoConProyecto()?(parseInt(document.getElementById('selectProyecto')?.value||'0',10)||0):0;
  return {id_cliente:idCliente,id_proyecto:idProyecto};
}

function renderContextoResumen(ctx){
  const el=document.getElementById('contextoProyectoResumen');
  if(!el)return;
  if(!ctx||(!ctx.cliente&&!ctx.proyecto)){
    el.innerHTML='<span class="asistente-contexto-vacio">'+getContextoEmptyMessage()+'</span>';
    return;
  }
  let html='';
  if(ctx.cliente){
    html+=`<div><strong>Cliente:</strong> ${escapeHtml(ctx.cliente.empresa||ctx.cliente.nombre_contacto||('#'+ctx.cliente.id))}</div>`;
    if(!modoConProyecto())html+=`<div style="margin-top:6px;color:#64748b;font-size:0.78rem"><i class="fas fa-info-circle"></i> Modo sin proyecto — el ticket se guardará solo con este cliente.</div>`;
  }
  if(ctx.proyecto){
    html+=`<div style="margin-top:6px"><strong>Proyecto:</strong> ${escapeHtml(ctx.proyecto.nombre_proyecto)}`;
    if(ctx.proyecto.url)html+=` · <a href="${escapeHtml(ctx.proyecto.url)}" target="_blank" rel="noopener">URL</a>`;
    html+='</div>';
    if(ctx.proyecto.tecnologias&&ctx.proyecto.tecnologias.length){
      html+='<div style="margin-top:6px">'+ctx.proyecto.tecnologias.map(t=>`<span class="ctx-tag">${escapeHtml(t)}</span>`).join('')+'</div>';
    }
    if(ctx.proyecto.descripcion_tecnica){
      const prev=ctx.proyecto.descripcion_tecnica.length>180?ctx.proyecto.descripcion_tecnica.slice(0,180)+'…':ctx.proyecto.descripcion_tecnica;
      html+=`<div style="margin-top:6px"><strong>Desc. técnica:</strong> ${escapeHtml(prev)}</div>`;
    }else if(ctx.proyecto.descripcion){
      const prev=ctx.proyecto.descripcion.length>180?ctx.proyecto.descripcion.slice(0,180)+'…':ctx.proyecto.descripcion;
      html+=`<div style="margin-top:6px"><strong>Descripción:</strong> ${escapeHtml(prev)}</div>`;
    }
  }
  el.innerHTML=html;
}

async function cargarContextoChat(actualizarSaludo){
  const ids=getContextoIds();
  try{
    const qs=new URLSearchParams();
    if(ids.id_cliente)qs.set('id_cliente',ids.id_cliente);
    if(ids.id_proyecto)qs.set('id_proyecto',ids.id_proyecto);
    const r=await fetch('api_contexto_proyecto.php?'+qs.toString());
    const d=await r.json();
    if(d&&d.success){
      chatContextoActual=d.contexto||null;
      renderContextoResumen(chatContextoActual);
      if(actualizarSaludo&&d.saludo&&chatHistory.length<=1){
        chatHistory=[{role:'assistant',content:d.saludo}];
        renderChatMessages();
      }
      return d;
    }
  }catch(e){}
  renderContextoResumen(null);
  return null;
}

function switchSolicitudTab(tab){
  document.querySelectorAll('.solicitud-tab').forEach(t=>t.classList.toggle('active',t.dataset.tab===tab));
  document.getElementById('panelAsistente').classList.toggle('active',tab==='asistente');
  document.getElementById('panelFormulario').classList.toggle('active',tab==='formulario');
}
document.querySelectorAll('.solicitud-tab').forEach(btn=>{
  btn.addEventListener('click',()=>switchSolicitudTab(btn.dataset.tab));
});

function renderChatMessages(){
  const box=document.getElementById('chatMessages');
  if(!box)return;
  box.innerHTML='';
  chatHistory.forEach(m=>{
    const div=document.createElement('div');
    div.className='chat-bubble '+(m.role==='user'?'user':'assistant');
    if(m.content){
      const txt=document.createElement('div');
      txt.textContent=m.content;
      div.appendChild(txt);
    }
    if(m.images&&m.images.length){
      const ig=document.createElement('div');
      ig.className='chat-bubble-images';
      m.images.forEach((url,idx)=>{
        const im=document.createElement('img');
        im.src=url;
        im.className='chat-bubble-thumb';
        im.alt='Captura '+(idx+1);
        im.onclick=()=>openLightbox(m.images,idx);
        ig.appendChild(im);
      });
      div.appendChild(ig);
      if(!m.content){
        const lbl=document.createElement('div');
        lbl.className='chat-bubble-img-label';
        lbl.textContent=m.images.length===1?'1 captura adjunta':m.images.length+' capturas adjuntas';
        div.appendChild(lbl);
      }
    }
    box.appendChild(div);
  });
  box.scrollTop=box.scrollHeight;
}

function renderPreviewDocumento(doc,listo){
  const el=document.getElementById('previewDocumento');
  const badge=document.getElementById('previewBadge');
  const btnAplicar=document.getElementById('btnAplicarRequerimientos');
  if(!el||!doc)return;
  const hasContent=!!(doc.resumen_solicitud||doc.detalle_tecnico||(doc.requerimientos_funcionales&&doc.requerimientos_funcionales.length)||(doc.criterios_aceptacion&&doc.criterios_aceptacion.length));
  if(!hasContent){
    el.innerHTML='<div class="preview-empty">El documento se irá armando conforme avances la conversación.</div>';
    if(badge){badge.textContent='En progreso';badge.className='preview-badge pending'}
    if(btnAplicar)btnAplicar.disabled=true;
    return;
  }
  let html='';
  if(doc.titulo_sugerido)html+=`<div class="preview-section"><h4>Título sugerido</h4><strong>${escapeHtml(doc.titulo_sugerido)}</strong></div>`;
  if(doc.resumen_solicitud)html+=`<div class="preview-section"><h4>Resumen de la solicitud</h4><p>${escapeHtml(doc.resumen_solicitud)}</p></div>`;
  if(doc.detalle_tecnico)html+=`<div class="preview-section"><h4>Detalle técnico</h4><p>${escapeHtml(doc.detalle_tecnico)}</p></div>`;
  if(doc.requerimientos_funcionales&&doc.requerimientos_funcionales.length){
    html+='<div class="preview-section"><h4>Requerimientos funcionales</h4>';
    doc.requerimientos_funcionales.forEach(rf=>{
      html+=`<div class="preview-rf"><div class="preview-rf-id">${escapeHtml(rf.id||'RF')}</div><strong>${escapeHtml(rf.titulo||'')}</strong><p style="margin:4px 0 0">${escapeHtml(rf.descripcion||'')}</p></div>`;
    });
    html+='</div>';
  }
  if(doc.criterios_aceptacion&&doc.criterios_aceptacion.length){
    html+='<div class="preview-section"><h4>Criterios de aceptación</h4><ul style="margin:0;padding-left:18px">';
    doc.criterios_aceptacion.forEach(ca=>{html+=`<li>${escapeHtml(ca)}</li>`});
    html+='</ul></div>';
  }
  if(doc.dudas_abiertas&&doc.dudas_abiertas.length){
    html+='<div class="preview-section"><h4>Dudas abiertas</h4><ul style="margin:0;padding-left:18px;color:#b45309">';
    doc.dudas_abiertas.forEach(d=>{html+=`<li>${escapeHtml(d)}</li>`});
    html+='</ul></div>';
  }
  if(doc.prioridad_sugerida)html+=`<div class="preview-section"><h4>Prioridad sugerida</h4><span>${escapeHtml(doc.prioridad_sugerida)}</span></div>`;
  el.innerHTML=html;
  if(badge){
    badge.textContent=listo?'Listo para ticket':'En progreso';
    badge.className='preview-badge '+(listo?'ready':'pending');
  }
  if(btnAplicar)btnAplicar.disabled=!hasContent;
}

async function iniciarChatAsistente(){
  chatHistory=[];chatDocumento=null;chatListo=false;chatDescripcionMarkdown='';
  chatTicketImages=[];clearChatPendingImages();
  renderChatMessages();renderPreviewDocumento({resumen_solicitud:'',detalle_tecnico:'',requerimientos_funcionales:[],criterios_aceptacion:[],dudas_abiertas:[]},false);
  const ids=getContextoIds();
  try{
    const r=await fetch('api_requerimientos_chat.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({reset:true,...ids})});
    const d=await r.json();
    if(d&&d.success&&d.mensaje_chat){
      chatHistory.push({role:'assistant',content:d.mensaje_chat});
      chatDocumento=d.documento||null;
      chatContextoActual=d.contexto||chatContextoActual;
      renderContextoResumen(chatContextoActual);
      renderChatMessages();if(chatDocumento)renderPreviewDocumento(chatDocumento,false);
    }
  }catch(e){
    chatHistory.push({role:'assistant',content:'¡Hola! Describe tu solicitud con detalle. Puedes adjuntar capturas con el botón "Adjuntar capturas" o pegarlas con Ctrl+V.'});
    renderChatMessages();
  }
}

function setAsistenteVisible(visible){
  const tabs=document.getElementById('solicitudTabs');
  const tabAsistente=document.getElementById('tabBtnAsistente');
  if(tabs)tabs.style.display=visible?'':'none';
  if(tabAsistente)tabAsistente.style.display=visible?'':'none';
  if(!visible)switchSolicitudTab('formulario');
}

async function enviarMensajeChat(){
  const input=document.getElementById('chatInput');
  const btn=document.getElementById('btnEnviarChat');
  AudioTranscripcion.stopAll();
  const text=(input?.value||'').trim();
  const pendingImages=[...chatPendingImages];
  if((!text&&!pendingImages.length)||!btn)return;
  if(pendingImages.length>CHAT_MAX_IMAGES){
    Swal.fire({toast:true,position:'top-end',icon:'warning',title:'Máximo '+CHAT_MAX_IMAGES+' imágenes por mensaje',timer:2500,showConfirmButton:false});
    return;
  }
  const imageUrls=pendingImages.map(x=>x.dataUrl);
  const msg={role:'user',content:text};
  if(imageUrls.length)msg.images=imageUrls;
  chatHistory.push(msg);
  pendingImages.forEach(item=>chatTicketImages.push(item));
  input.value='';clearChatPendingImages();renderChatMessages();
  btn.disabled=true;
  const typing=document.createElement('div');
  typing.className='chat-typing';typing.id='chatTyping';
  typing.innerHTML='<span></span><span></span><span></span>';
  document.getElementById('chatMessages').appendChild(typing);
  try{
    const r=await fetch('api_requerimientos_chat.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({messages:chatHistory,...getContextoIds()})});
    const d=await r.json();
    document.getElementById('chatTyping')?.remove();
    if(!d||!d.success){
      chatHistory.push({role:'assistant',content:'Error: '+(d&&d.error?d.error:'No se pudo obtener respuesta. Verifica la configuración de OpenAI.')});
      renderChatMessages();
      return;
    }
    if(d.mensaje_chat)chatHistory.push({role:'assistant',content:d.mensaje_chat});
    chatDocumento=d.documento||chatDocumento;
    chatListo=!!d.listo_para_ticket;
    chatDescripcionMarkdown=d.descripcion_markdown||'';
    renderChatMessages();
    if(chatDocumento)renderPreviewDocumento(chatDocumento,chatListo);
  }catch(e){
    document.getElementById('chatTyping')?.remove();
    chatHistory.push({role:'assistant',content:'Error de conexión. Intenta de nuevo.'});
    renderChatMessages();
  }finally{btn.disabled=false;input.focus()}
}

document.getElementById('btnEnviarChat')?.addEventListener('click',enviarMensajeChat);
document.getElementById('btnChatAddImage')?.addEventListener('click',()=>document.getElementById('chatImageUpload')?.click());
document.getElementById('chatImageUpload')?.addEventListener('change',async function(e){
  await addChatImagesFromFiles(Array.from(e.target.files||[]));
  e.target.value='';
});
document.getElementById('chatInput')?.addEventListener('paste',async e=>{
  const items=Array.from(e.clipboardData?.items||[]);
  const imageFiles=[];
  items.forEach(item=>{
    if(item.type&&item.type.startsWith('image/')){
      const f=item.getAsFile();
      if(f)imageFiles.push(f);
    }
  });
  if(!imageFiles.length)return;
  e.preventDefault();
  await addChatImagesFromFiles(imageFiles);
});
document.getElementById('chatInput')?.addEventListener('keydown',e=>{
  if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();enviarMensajeChat()}
});
document.getElementById('btnReiniciarChat')?.addEventListener('click',()=>{
  Swal.fire({title:'¿Reiniciar charla?',text:'Se perderá el progreso del asistente',icon:'question',showCancelButton:true,confirmButtonText:'Sí',cancelButtonText:'Cancelar'}).then(r=>{if(r.isConfirmed)iniciarChatAsistente()});
});
document.getElementById('btnAplicarRequerimientos')?.addEventListener('click',()=>{
  if(!chatDocumento)return;
  const tituloInput=document.querySelector('input[name="titulo"]');
  const descInput=document.getElementById('descripcionTextarea');
  const prioSelect=document.querySelector('select[name="prioridad"]');
  if(tituloInput&&chatDocumento.titulo_sugerido)tituloInput.value=chatDocumento.titulo_sugerido;
  if(descInput)descInput.value=chatDescripcionMarkdown||'';
  if(prioSelect&&chatDocumento.prioridad_sugerida)prioSelect.value=chatDocumento.prioridad_sugerida;
  const nImg=transferChatImagesToForm();
  switchSolicitudTab('formulario');
  const totalImg=descripcionImages.length;
  const imgNote=totalImg?` (${totalImg} imagen${totalImg>1?'es':''} incluida${totalImg>1?'s':''})`:'';
  if((chatTicketImages.length||chatPendingImages.length)&&!nImg&&!totalImg){
    Swal.fire({toast:true,position:'top-end',icon:'warning',title:'El brief se aplicó, pero las imágenes no se pudieron pasar al formulario',timer:3200,showConfirmButton:false});
  }else{
    Swal.fire({toast:true,position:'top-end',icon:'success',title:'Requerimientos aplicados al formulario'+imgNote,timer:2200,showConfirmButton:false});
  }
});

document.getElementById('btnNuevaSolicitud').addEventListener('click',()=>{
  document.querySelector('#formSolicitud').reset();document.querySelector('#solicitudId').value='';
  document.getElementById('descripcionExistingImages').value='[]';
  if(document.getElementById('descripcionExistingFiles'))document.getElementById('descripcionExistingFiles').value='[]';
  descripcionImages=[];chatTicketImages=[];chatPendingImages=[];document.getElementById('descImagePreviews').innerHTML='';
  clearChatPendingImages();
  const dfl=document.getElementById('descFileList');if(dfl)dfl.innerHTML='';
  const dEfl=document.getElementById('descExistingFileList');if(dEfl)dEfl.innerHTML='';
  descNewFiles=[];existingDescFiles=[];removedDescFiles=new Set();
  document.getElementById('selectProyecto').innerHTML='<option value="">Selecciona primero un cliente</option>';
  document.getElementById('selectProyecto').disabled=true;
  setModoProyecto(true);
  document.getElementById('modalSolicitudTitle').textContent='Nueva Solicitud';
  setAsistenteVisible(true);
  switchSolicitudTab('asistente');
  iniciarChatAsistente();
  modalS.classList.add('active');document.body.style.overflow='hidden';
});
document.getElementById('closeModal').addEventListener('click',()=>{AudioTranscripcion.stopAll();modalS.classList.remove('active');document.body.style.overflow='auto'});
document.getElementById('cancelForm').addEventListener('click',()=>{AudioTranscripcion.stopAll();modalS.classList.remove('active');document.body.style.overflow='auto'});

// Cliente/Proyecto en modal
const selectCliente=document.getElementById('selectCliente'),selectProyecto=document.getElementById('selectProyecto');
async function cargarProyectosCliente(clienteId){
  selectProyecto.innerHTML='<option value="">Cargando...</option>';selectProyecto.disabled=true;
  if(!clienteId){selectProyecto.innerHTML='<option value="">Selecciona primero un cliente</option>';return}
  try{
    const r=await fetch('proyectos_por_cliente.php?id_cliente='+encodeURIComponent(clienteId));
    const d=await r.json();
    if(d&&d.success&&d.proyectos&&d.proyectos.length){
      selectProyecto.innerHTML='<option value="">Seleccionar proyecto</option>';
      d.proyectos.forEach(p=>{const o=document.createElement('option');o.value=p.id_proyecto;o.textContent='#'+p.id_proyecto+' - '+p.nombre_proyecto;selectProyecto.appendChild(o)});
      selectProyecto.disabled=false;
    }else{
      selectProyecto.innerHTML='<option value="">Sin proyectos</option>';
      selectProyecto.disabled=true;
    }
  }catch(e){
    selectProyecto.innerHTML='<option value="">Error de conexión</option>';
    selectProyecto.disabled=true;
  }
}
selectCliente.addEventListener('change',async function(){
  const id=this.value;
  if(!id){
    selectProyecto.innerHTML='<option value="">Selecciona primero un cliente</option>';
    selectProyecto.disabled=true;
    renderContextoResumen(null);
    return;
  }
  if(modoConProyecto())await cargarProyectosCliente(id);
  else{
    selectProyecto.innerHTML='<option value="">Sin proyecto</option>';
    selectProyecto.disabled=true;
  }
  await cargarContextoChat(true);
});
selectProyecto.addEventListener('change',()=>cargarContextoChat(true));
document.getElementById('switchModoProyecto')?.addEventListener('change',async function(){
  setModoProyecto(this.checked);
  if(this.checked&&selectCliente.value)await cargarProyectosCliente(selectCliente.value);
  else if(!this.checked){
    selectProyecto.innerHTML='<option value="">Sin proyecto</option>';
    selectProyecto.value='';
    selectProyecto.disabled=true;
  }
  await cargarContextoChat(true);
});
setModoProyecto(true);

// Repetir checkbox
document.getElementById('repetirSolicitud').addEventListener('change',function(){
  const rs=document.getElementById('selectRepetir');
  if(this.checked){rs.disabled=false;rs.style.opacity='1'}else{rs.disabled=true;rs.style.opacity='.6';rs.value=''}
});

// ── AGENTES CHECKBOX ──
(function(){
  const si=document.getElementById('searchAgentes'),ac=document.getElementById('agentesContainer'),cnt=document.getElementById('agentesCounter');
  if(!si||!ac||!cnt)return;
  function uc(){const c=ac.querySelectorAll('input[type="checkbox"]:checked').length;cnt.textContent='('+c+')';cnt.style.color=c>0?'#4361ee':'#666'}
  si.addEventListener('input',function(){
    const t=this.value.toLowerCase().trim();
    ac.querySelectorAll('.agente-checkbox-item').forEach(it=>{it.style.display=it.getAttribute('data-nombre').includes(t)?'block':'none'});
    const vis=Array.from(ac.querySelectorAll('.agente-checkbox-item')).filter(it=>it.style.display!=='none');
    const msg=document.getElementById('noResultsMsg');
    if(vis.length===0&&t!==''){if(!msg){const m=document.createElement('div');m.id='noResultsMsg';m.style.cssText='text-align:center;padding:14px;color:#888;font-size:13px;';m.innerHTML='<i class="fas fa-search" style="font-size:24px;margin-bottom:6px;display:block;"></i>No se encontraron agentes';ac.appendChild(m)}}else if(msg)msg.remove()
  });
  ac.addEventListener('change',uc);
  document.getElementById('btnSeleccionarTodos').addEventListener('click',function(){
    Array.from(ac.querySelectorAll('input[type="checkbox"]')).filter(cb=>cb.closest('.agente-checkbox-item').style.display!=='none').forEach(cb=>cb.checked=true);uc()
  });
  document.getElementById('btnDeseleccionarTodos').addEventListener('click',function(){ac.querySelectorAll('input[type="checkbox"]').forEach(cb=>cb.checked=false);uc()});
  ac.querySelectorAll('.agente-label').forEach(l=>{
    l.addEventListener('mouseenter',function(){this.style.borderColor='#4361ee';this.style.background='#f0f4ff';this.style.transform='translateX(3px)'});
    l.addEventListener('mouseleave',function(){const cb=this.querySelector('input[type="checkbox"]');if(!cb||!cb.checked){this.style.borderColor='#e9ecef';this.style.background='#fff';this.style.fontWeight='400'}this.style.transform='translateX(0)'});
    const cb=l.querySelector('input[type="checkbox"]');if(cb)cb.addEventListener('change',function(){this.checked?(l.style.borderColor='#4361ee',l.style.background='#f0f4ff',l.style.fontWeight='500'):(l.style.borderColor='#e9ecef',l.style.background='#fff',l.style.fontWeight='400')})
  });uc()
})();

// ── ELIMINACIÓN ──
async function confirmarEliminacion(btn){
  const form=btn.closest('.form-eliminar'),id=form.querySelector('input[name="id"]').value;
  const r=await Swal.fire({title:'¿Eliminar ticket #'+id+'?',text:'Esta acción no se puede deshacer',icon:'warning',showCancelButton:true,confirmButtonColor:'#f72585',cancelButtonColor:'#6c757d',confirmButtonText:'Sí, eliminar',cancelButtonText:'Cancelar'});
  if(!r.isConfirmed)return;
  Swal.fire({title:'Eliminando...',allowOutsideClick:false,didOpen:()=>Swal.showLoading()});
  try{const fd=new FormData(form);fd.append('ajax','1');const resp=await fetch('eliminar.php',{method:'POST',body:fd});const data=await resp.json();
  if(data.success){
    await Swal.fire({toast:true,position:'top-end',icon:'success',title:'Eliminado',timer:2000,showConfirmButton:false});
    const row=btn.closest('tr');if(row){row.style.transition='opacity .3s,transform .3s';row.style.opacity='0';row.style.transform='translateX(-20px)';
    setTimeout(()=>{row.remove();try{dtPendientes.draw(false)}catch(e){}try{dtFinalizadas.draw(false)}catch(e){}try{dtTodos.draw(false)}catch(e){}updateStatsCounts()},300)}
  }else{Swal.fire({icon:'error',title:'Error',text:data.message||'No se pudo eliminar'})}
  }catch(e){Swal.fire({icon:'error',title:'Error de conexión',text:'Intenta nuevamente'})}
}
window.confirmarEliminacion=confirmarEliminacion;

// ── FORMULARIO (AJAX) ──
document.getElementById('formSolicitud').addEventListener('submit',async function(e){
  e.preventDefault();
  AudioTranscripcion.stopAll();
  Swal.fire({title:'Procesando',text:'Guardando...',allowOutsideClick:false,didOpen:()=>Swal.showLoading()});
  try{
    // Red de seguridad: si vino del chat y no se pulsó Aplicar, igual subirlas
    transferChatImagesToForm();
    const hEF=document.getElementById('descripcionExistingFiles');
    if(hEF){const kept=existingDescFiles.filter(x=>!removedDescFiles.has(x));hEF.value=JSON.stringify(kept)}
    const fd=new FormData(this);
    descripcionImages.forEach((img,idx)=>{
      if(img instanceof File||img instanceof Blob){
        const name=(img instanceof File&&img.name)?img.name:('chat_img_'+idx+'.jpg');
        fd.append('descripcion_images[]',img,name);
      }
    });
    if(descNewFiles.length)descNewFiles.forEach(f=>fd.append('descripcion_files[]',f));
    fd.append('ajax','1');
    const resp=await fetch('guardar.php',{method:'POST',body:fd});
    const data=await resp.json();
    if(data.ok){
      const isEdit=!!document.getElementById('solicitudId').value;
      const savedImgs=Number(data.images_count||0);
      modalS.classList.remove('active');document.body.style.overflow='auto';
      document.getElementById('formSolicitud').reset();document.getElementById('solicitudId').value='';
      descripcionImages=[];chatTicketImages=[];chatPendingImages=[];descNewFiles=[];existingDescFiles=[];removedDescFiles=new Set();
      document.getElementById('descImagePreviews').innerHTML='';
      const dfl=document.getElementById('descFileList');if(dfl)dfl.innerHTML='';
      const dEfl=document.getElementById('descExistingFileList');if(dEfl)dEfl.innerHTML='';
      selectProyecto.innerHTML='<option value="">Selecciona primero un cliente</option>';selectProyecto.disabled=true;
      setModoProyecto(true);
      const imgMsg=savedImgs?(' · '+savedImgs+' img'):'';
      Swal.fire({toast:true,position:'top-end',icon:'success',title:(isEdit?'Actualizada':'Creada')+(data.email_enviado?' + correo':'')+imgMsg,timer:3000,showConfirmButton:false});
      setTimeout(()=>window.location.reload(),1500);
    }else{Swal.fire({icon:'error',title:'Error',text:data.error||'Error desconocido'})}
  }catch(e){Swal.fire({icon:'error',title:'Error',text:'Error de conexión: '+e.message})}
});

// ── EDITAR ──
async function editarSolicitud(id){
  try{
    const r=await fetch('obtener.php?id='+encodeURIComponent(id)+'&_ts='+Date.now());
    const raw=await r.text();
    let d=null;
    try{d=JSON.parse(raw)}catch(_e){
      Swal.fire({icon:'error',title:'Error',text:'El servidor no devolvió JSON al cargar la solicitud'});
      console.error('obtener.php non-JSON', raw.slice(0,400));
      return;
    }
    if(!d||!d.success){Swal.fire({icon:'error',title:'Error',text:d&&d.error?d.error:'No se pudo cargar'});return}
    const s=d.solicitud;
    document.getElementById('modalSolicitudTitle').textContent='Editar Solicitud';
    document.getElementById('solicitudId').value=s.id;
    document.querySelector('input[name="titulo"]').value=s.titulo||'';
    document.getElementById('descripcionTextarea').value=decodeVisibleEscapes(s.descripcion_text||'');
    document.getElementById('descripcionExistingImages').value=JSON.stringify(s.descripcion_images||[]);
    if(document.getElementById('descripcionExistingFiles'))document.getElementById('descripcionExistingFiles').value=JSON.stringify(s.descripcion_files||[]);
    const fl=document.querySelector('input[name="fecha_lim"]');if(fl)fl.value=s.fecha_lim||'';
    const idClienteEdit=Number(s.id_cliente||0);
    if(idClienteEdit>0&&CLIENTES_ACTIVOS_IDS.includes(idClienteEdit)){
      selectCliente.value=String(idClienteEdit);
    }else{
      selectCliente.value='';
    }
    setModoProyecto(!!s.id_proyecto);
    if(idClienteEdit>0&&CLIENTES_ACTIVOS_IDS.includes(idClienteEdit)){
      if(modoConProyecto()){
        await cargarProyectosCliente(s.id_cliente);
        if(s.id_proyecto)selectProyecto.value=String(s.id_proyecto);
      }else{
        selectProyecto.innerHTML='<option value="">Sin proyecto</option>';
        selectProyecto.disabled=true;
      }
    }else{
      selectProyecto.innerHTML='<option value="">Selecciona primero un cliente</option>';
      selectProyecto.disabled=true;
    }
    // Checkboxes
    document.querySelectorAll('input[name="agentes_asignados[]"]').forEach(cb=>{cb.checked=false;const l=cb.closest('.agente-label');if(l){l.style.borderColor='#e9ecef';l.style.background='#fff';l.style.fontWeight='400'}});
    let asignados=[];if(s.usuario_asignado&&typeof s.usuario_asignado==='string')asignados=s.usuario_asignado.split(',').map(x=>x.trim()).filter(x=>x!=='');else if(s.usuario_asignado&&typeof s.usuario_asignado==='number')asignados=[String(s.usuario_asignado)];
    asignados.forEach(aid=>{const cb=document.querySelector('input[name="agentes_asignados[]"][value="'+aid+'"]');if(cb){cb.checked=true;const l=cb.closest('.agente-label');if(l){l.style.borderColor='#4361ee';l.style.background='#f0f4ff';l.style.fontWeight='500'}}});
    const cnt=document.getElementById('agentesCounter');if(cnt){cnt.textContent='('+asignados.length+')';cnt.style.color=asignados.length>0?'#4361ee':'#666'}
    document.querySelector('select[name="prioridad"]').value=s.prioridad||'Media';
    const dEflC=document.getElementById('descExistingFileList');
    existingDescFiles=Array.isArray(s.descripcion_files)?s.descripcion_files.slice():[];removedDescFiles=new Set();
    renderExistingFilesList(dEflC,existingDescFiles);
    const rp=document.getElementById('repetirSolicitud'),rs=document.getElementById('selectRepetir');
    if(s.repetir&&Number(s.repetir)>0){rp.checked=true;rs.disabled=false;rs.style.opacity='1';rs.value=String(s.repetir)}else{rp.checked=false;rs.disabled=true;rs.style.opacity='.6';rs.value=''}
    setAsistenteVisible(false);
    switchSolicitudTab('formulario');
    await cargarContextoChat(false);
    modalS.classList.add('active');document.body.style.overflow='hidden';
  }catch(e){console.error(e);Swal.fire({icon:'error',title:'Error',text:'No se pudo cargar la solicitud'})}
}
window.editarSolicitud=editarSolicitud;

// ── ASIGNAR AGENTE ──
document.addEventListener('focusin',function(e){const t=e.target;if(t&&t.classList&&t.classList.contains('assign-agent-select'))t.dataset.prev=t.value||''});
document.addEventListener('change',function(e){
  const sel=e.target;if(!sel.classList.contains('assign-agent-select'))return;
  const aid=(sel.value||'').trim(),prev=sel.dataset.prev||'',tid=sel.getAttribute('data-ticket-id');if(!tid)return;
  if(!aid){sel.dataset.prev='';return}
  Swal.fire({title:'Asignar agente',text:'¿Asignar al ticket #'+tid+'?',icon:'question',showCancelButton:true,confirmButtonColor:'#4361ee',confirmButtonText:'Sí',cancelButtonText:'Cancelar'}).then(async res=>{
    if(!res.isConfirmed){sel.value=prev;return}
    sel.disabled=true;Swal.fire({title:'Asignando...',allowOutsideClick:false,didOpen:()=>Swal.showLoading()});
    try{const fd=new FormData();fd.append('id',tid);fd.append('usuario_asignado',aid);const resp=await fetch('api_asignar_agente.php',{method:'POST',body:fd});let pl;try{pl=await resp.json()}catch(_){pl={success:resp.ok}}
    const ok=!!((pl&&(pl.success||pl.ok))||resp.ok);
    if(ok){Swal.close();Swal.fire({toast:true,position:'top-end',icon:'success',title:'Asignado',timer:1400,showConfirmButton:false});sel.dataset.prev=sel.value;sel.disabled=false}
    else{sel.value=prev;sel.disabled=false;Swal.fire('Error',(pl&&(pl.message||pl.error))||'No se pudo asignar','error')}
    }catch(err){sel.value=prev;sel.disabled=false;Swal.fire('Error','Error de red','error')}
  });
});

// ── ESTADO SELECT SYNC ──
document.querySelectorAll('select.estado-select').forEach(s=>{s.dataset.prevEstado=s.value||'';s.dataset.val=s.value});
document.addEventListener('change',async e=>{
  const sel=e.target;if(!sel.matches('select.estado-select'))return;
  const prev=sel.dataset.prevEstado||sel.value,nuevo=sel.value;
  if(prev===nuevo)return;
  const tr=sel.closest('tr');
  const id=sel.closest('.estado-form')?.querySelector('input[name="id"]')?.value
    ||tr?.dataset.ticketId
    ||tr?.querySelector('td:nth-child(2)')?.textContent?.trim();
  if(!id)return;
  const ans=await Swal.fire({title:'Cambiar estado',text:'Ticket #'+id+' a "'+nuevo+'"?',icon:'question',showCancelButton:true,confirmButtonText:'Sí',cancelButtonText:'Cancelar'});
  if(!ans.isConfirmed){sel.value=prev;return}
  sel.disabled=true;
  const fd=new FormData();fd.append('id',id);fd.append('estado',nuevo);fd.append('ajax','1');
  try{const resp=await fetch('actualizar.php',{method:'POST',body:fd});let pl;try{pl=await resp.json()}catch(_){}const ok=pl&&(pl.success||pl.ok);if(!ok)throw Error();
  sel.dataset.prevEstado=nuevo;sel.dataset.val=nuevo;
  try{updateStatsCounts()}catch(e){}try{refreshMenuCounts()}catch(e){}
  Swal.fire({toast:true,position:'top-end',icon:'success',title:'Estado actualizado',timer:1400,showConfirmButton:false})
  }catch(err){sel.value=prev;sel.dataset.val=prev;Swal.fire({icon:'error',title:'Error',text:'No se pudo actualizar'})
  }finally{sel.disabled=false}
});

// ── POLLING ──
(function(){
  let lastKnownCount=0;
  async function poll(){
    try{const r=await fetch('api_contadores.php?_ts='+Date.now());if(!r.ok)return;const d=await r.json();
    if(d&&d.success&&d.counts){const cur=(d.counts.Pendiente||0)+(d.counts['En Proceso']||0);if(lastKnownCount===0){lastKnownCount=cur;return}if(cur>lastKnownCount&&!window.justCreatedSolicitud){Swal.fire({toast:true,position:'top-end',icon:'success',title:'Nueva solicitud creada',timer:8000,showConfirmButton:false})}lastKnownCount=cur}
    }catch(e){}
  }
  setTimeout(poll,2000);setInterval(poll,5000);
})();

// INIT
setTimeout(()=>{try{updateStatsCounts()}catch(e){}},300);

// ── BULK ACTIONS ──
// Función para actualizar el conteo de seleccionados
function updateCheckboxCount(tableId) {
  const checked = $(`#${tableId} tbody .row-checkbox:checked`).length;
  const countSpan = tableId === 'dtTodos' ? '#selectedCountTodos' 
    : tableId === 'dtPendientes' ? '#selectedCountPendientes' 
    : '#selectedCountFinalizadas';
  const bulkDiv = tableId === 'dtTodos' ? '#bulkActionsTodos' 
    : tableId === 'dtPendientes' ? '#bulkActionsPendientes' 
    : '#bulkActionsFinalizadas';
  
  $(countSpan).text(checked);
  
  if (checked > 0) {
    $(bulkDiv).addClass('active');
  } else {
    $(bulkDiv).removeClass('active');
  }
}

// Eventos para checkboxes de filas
$(document).on('change', '.row-checkbox', function() {
  const tableId = $(this).data('table');
  updateCheckboxCount(tableId);
  
  // Actualizar checkbox del header si todos están seleccionados
  const allCheckboxes = $(`#${tableId} tbody .row-checkbox`);
  const allChecked = allCheckboxes.length > 0 && allCheckboxes.length === allCheckboxes.filter(':checked').length;
  const headerCheckbox = tableId === 'dtTodos' ? '#selectAllTodos' 
    : tableId === 'dtPendientes' ? '#selectAllPendientes' 
    : '#selectAllFinalizadas';
  
  $(headerCheckbox).prop('checked', allChecked);
});

// Eventos para checkboxes del header (seleccionar todos)
$('#selectAllTodos, #selectAllPendientes, #selectAllFinalizadas').on('change', function() {
  const tableId = this.id === 'selectAllTodos' ? 'dtTodos' 
    : this.id === 'selectAllPendientes' ? 'dtPendientes' 
    : 'dtFinalizadas';
  
  const isChecked = $(this).prop('checked');
  $(`#${tableId} tbody .row-checkbox`).prop('checked', isChecked);
  updateCheckboxCount(tableId);
});

// Función para seleccionar todos los tickets
function selectAllTickets(tableId) {
  $(`#${tableId} tbody .row-checkbox`).prop('checked', true);
  const headerCheckbox = tableId === 'dtTodos' ? '#selectAllTodos' 
    : tableId === 'dtPendientes' ? '#selectAllPendientes' 
    : '#selectAllFinalizadas';
  $(headerCheckbox).prop('checked', true);
  updateCheckboxCount(tableId);
}

// Función para deseleccionar todos los tickets
function deselectAllTickets(tableId) {
  $(`#${tableId} tbody .row-checkbox`).prop('checked', false);
  const headerCheckbox = tableId === 'dtTodos' ? '#selectAllTodos' 
    : tableId === 'dtPendientes' ? '#selectAllPendientes' 
    : '#selectAllFinalizadas';
  $(headerCheckbox).prop('checked', false);
  updateCheckboxCount(tableId);
}

// Función para eliminar tickets seleccionados
async function deleteSelectedTickets(tableId) {
  const checkboxes = $(`#${tableId} tbody .row-checkbox:checked`);
  
  if (checkboxes.length === 0) {
    Swal.fire({
      icon: 'warning',
      title: 'Atención',
      text: 'No hay tickets seleccionados'
    });
    return;
  }
  
  const ids = [];
  checkboxes.each(function() {
    ids.push($(this).data('id'));
  });
  
  const result = await Swal.fire({
    title: '¿Confirmar eliminación?',
    html: `Se eliminarán <strong>${ids.length}</strong> ticket(s) seleccionado(s).<br><small>Esta acción no se puede deshacer.</small>`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar'
  });
  
  if (!result.isConfirmed) {
    return;
  }
  
  // Mostrar loading
  Swal.fire({
    title: 'Eliminando...',
    text: 'Por favor espere',
    allowOutsideClick: false,
    didOpen: () => {
      Swal.showLoading();
    }
  });
  
  try {
    const formData = new FormData();
    formData.append('ids', JSON.stringify(ids));
    formData.append('ajax', '1');
    
    const response = await fetch('eliminar_masivo.php', {
      method: 'POST',
      body: formData
    });
    
    const data = await response.json();
    
    if (data.success) {
      Swal.fire({
        icon: 'success',
        title: '¡Eliminado!',
        text: `Se eliminaron ${data.deleted} ticket(s) correctamente`,
        timer: 2000,
        showConfirmButton: false
      });
      
      // Recargar la página para actualizar las tablas
      setTimeout(() => {
        location.reload();
      }, 2000);
    } else {
      throw new Error(data.message || 'Error al eliminar tickets');
    }
  } catch (error) {
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: error.message || 'No se pudieron eliminar los tickets'
    });
  }
}
</script>
</body>
</html>