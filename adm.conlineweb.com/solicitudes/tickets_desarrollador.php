<?php
require_once __DIR__ . '/../auth_middleware.php';
require_once __DIR__ . '/bootstrap_conexion.php';
require_once __DIR__ . '/helpers_agentes.php';

error_reporting(E_ALL);
ini_set('display_errors', '0');

$tipoSesion = (int) ($_SESSION['tipo'] ?? 0);
$uidSesion = (int) ($_SESSION['uid'] ?? 0);
$agenteIdSesion = (int) ($_SESSION['agente_id'] ?? 0);

// Resolver agente del usuario logueado (sesión o BD)
if ($agenteIdSesion <= 0 && $uidSesion > 0 && isset($conexion) && $conexion instanceof mysqli) {
    $stAg = $conexion->prepare('SELECT id, nombre FROM agentes WHERE Idusu = ? LIMIT 1');
    if ($stAg) {
        $stAg->bind_param('i', $uidSesion);
        $stAg->execute();
        $rowAg = $stAg->get_result()->fetch_assoc();
        $stAg->close();
        if ($rowAg) {
            $agenteIdSesion = (int) $rowAg['id'];
            $_SESSION['agente_id'] = $agenteIdSesion;
            $_SESSION['agente_nombre'] = (string) ($rowAg['nombre'] ?? '');
        }
    }
}

$agenteNombre = trim((string) ($_SESSION['agente_nombre'] ?? ''));
if ($agenteNombre === '' && $agenteIdSesion > 0 && isset($conexion) && $conexion instanceof mysqli) {
    $stNom = $conexion->prepare('SELECT nombre FROM agentes WHERE id = ? LIMIT 1');
    if ($stNom) {
        $stNom->bind_param('i', $agenteIdSesion);
        $stNom->execute();
        $rn = $stNom->get_result()->fetch_assoc();
        $stNom->close();
        if ($rn) {
            $agenteNombre = (string) ($rn['nombre'] ?? '');
            $_SESSION['agente_nombre'] = $agenteNombre;
        }
    }
}

$idGet = isset($_GET['id']) ? (int) $_GET['id'] : 0;

/**
 * Construye query string preservando filtros, forzando id del agente.
 */
function td_redirect_with_id(int $agenteId): void
{
    $qs = $_GET;
    $qs['id'] = $agenteId;
    header('Location: tickets_desarrollador.php?' . http_build_query($qs));
    exit;
}

// Tipo 3 (desarrollador): siempre su propio agente
if ($tipoSesion === 3) {
    if ($agenteIdSesion <= 0) {
        header('Location: cerrarSesion.php');
        exit;
    }
    if ($idGet !== $agenteIdSesion) {
        td_redirect_with_id($agenteIdSesion);
    }
} elseif ($idGet <= 0 && $agenteIdSesion > 0) {
    // Admin u otros con agente vinculado: al entrar sin ?id= → su usuario
    td_redirect_with_id($agenteIdSesion);
}

$id_usuario = $idGet > 0 ? $idGet : $agenteIdSesion;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets · <?= $agenteNombre !== '' ? htmlspecialchars($agenteNombre, ENT_QUOTES, 'UTF-8') : 'Desarrollador' ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        :root {
            --primary: #000147;
            --secondary: #1d4ed8;
            --success: #0d9488;
            --danger: #e11d48;
            --warning: #f59e0b;
            --info: #0891b2;
            --light: #f8f9fa;
            --dark: #0f172a;
            --gray: #475569;
            --light-gray: #d6e2f2;
            --main-bg: #eef1f6;
            --card-bg: #ffffff;
            --brand: #000147;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Manrope', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background:
                radial-gradient(circle at 8% 0%, rgba(0, 1, 71, 0.08), transparent 42%),
                radial-gradient(circle at 92% 8%, rgba(67, 97, 238, 0.10), transparent 36%),
                var(--main-bg);
            color: #1e293b;
            line-height: 1.6;
            padding: 16px 20px 28px;
        }
        body::before {
            content: '';
            display: block;
            height: 4px;
            background: linear-gradient(90deg, #000147 0%, #4361ee 45%, #7c3aed 100%);
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 9999;
        }

        .container {
            max-width: 1800px;
            margin: 0 auto;
            padding: 0 15px;
            width: 100%;
        }

        header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            margin: 8px 0 16px;
            padding: 14px 20px;
            border: 1px solid rgba(15, 23, 42, 0.08);
            background: rgba(255, 255, 255, 0.92);
            border-radius: 16px;
            box-shadow: 0 8px 28px rgba(15, 23, 42, 0.06);
            gap: 14px;
            position: relative;
            overflow: hidden;
        }

        header::after {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            top: 0;
            height: 3px;
            background: linear-gradient(90deg, #000147 0%, #4361ee 55%, #7c3aed 100%);
        }

        .td-header-left {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }
        .td-header-left img {
            height: 36px;
            width: auto;
        }
        .td-header-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }
        .td-user-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            font-size: 0.86rem;
            font-weight: 600;
            color: #0f172a;
        }
        .td-user-chip i { color: var(--brand); }
        .td-link-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 10px;
            font-size: 0.84rem;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #334155;
            transition: background .15s ease, border-color .15s ease;
        }
        .td-link-btn:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #0f172a;
            text-decoration: none;
        }
        .td-link-btn--danger {
            color: #b91c1c;
            border-color: #fecaca;
            background: #fef2f2;
        }

        h1, h2 {
            color: #000147;
            font-size: 1.35rem;
            margin-bottom: 0;
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        h1 {
            color: #000147;
        }

        h1 i {
            color: #4361ee;
        }
        
        h2 {
            font-size: 1.4rem;
        }

        .welcome-text {
            background: #f4f8ff;
            padding: 22px;
            border-radius: 14px;
            margin-bottom: 25px;
            border: 1px solid #dbe7ff;
        }

        .welcome-text p {
            margin-bottom: 10px;
            line-height: 1.6;
            color: #000147;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.07);
            text-align: center;
            border-top: 4px solid var(--primary);
            border: 1px solid #dbe4f0;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::after {
            content: '';
            position: absolute;
            right: -24px;
            top: -24px;
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: rgba(37, 99, 235, 0.10);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 30px rgba(15, 23, 42, 0.12);
        }

        .stat-card.pending { border-top-color: var(--warning); }
        .stat-card.in-progress { border-top-color: var(--info); }
        .stat-card.completed { border-top-color: var(--success); }

        .stat-card.pending::after { background: rgba(245, 158, 11, 0.14); }
        .stat-card.in-progress::after { background: rgba(8, 145, 178, 0.14); }
        .stat-card.completed::after { background: rgba(13, 148, 136, 0.14); }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: #0b2c8a;
            margin-bottom: 5px;
            position: relative;
            z-index: 1;
        }

        .stat-label {
            font-size: 1rem;
            color: var(--gray);
            font-weight: 600;
            position: relative;
            z-index: 1;
        }

        .btn-primary {
            background-color: #000147;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            font-size: 0.9rem;
        }

        .btn-primary:hover {
            background-color: var(--secondary);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .card {
            background: var(--card-bg);
            border-radius: 14px;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
            padding: 20px;
            margin-bottom: 20px;
            overflow: hidden;
            border: 1px solid #dbe4f0;
        }

        .card h2 {
            color: #0b2c8a;
            margin-bottom: 8px;
        }

        .table-container {
            width: 100%;
            overflow-x: auto;
            margin-top: 15px;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        th, td {
            padding: 12px 10px;
            text-align: left;
            border-bottom: 1px solid var(--light-gray);
            font-size: 0.9rem;
        }

        th {
            background-color: #000147;
            color: white;
            font-weight: 600;
            position: sticky;
            top: 0;
        }

        tr:hover {
            background-color: rgba(37, 99, 235, 0.08);
        }

        /* Color por prioridad en filas de DataTable */
        #ticketsTablePendientes tbody tr.priority-high,
        #ticketsTableFinalizadas tbody tr.priority-high {
            background-color: #fff1f2;
        }

        #ticketsTablePendientes tbody tr.priority-medium,
        #ticketsTableFinalizadas tbody tr.priority-medium {
            background-color: #fffbeb;
        }

        #ticketsTablePendientes tbody tr.priority-low,
        #ticketsTableFinalizadas tbody tr.priority-low {
            background-color: #ecfeff;
        }

        #ticketsTablePendientes tbody tr.priority-high:hover,
        #ticketsTableFinalizadas tbody tr.priority-high:hover {
            background-color: #ffe4e6;
        }

        #ticketsTablePendientes tbody tr.priority-medium:hover,
        #ticketsTableFinalizadas tbody tr.priority-medium:hover {
            background-color: #fef3c7;
        }

        #ticketsTablePendientes tbody tr.priority-low:hover,
        #ticketsTableFinalizadas tbody tr.priority-low:hover {
            background-color: #cffafe;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
            text-align: center;
            min-width: 70px;
        }

        .badge-pendiente {
            background-color: #fff3cd;
            color: #856404;
        }

        .badge-proceso {
            background-color: #cce5ff;
            color: #004085;
        }

        .badge-finalizado {
            background-color: #d4edda;
            color: #155724;
        }

        .badge-alta {
            background-color: #f8d7da;
            color: #721c24;
        }

        .badge-media {
            background-color: #fff3cd;
            color: #856404;
        }

        .badge-baja {
            background-color: #d4edda;
            color: #155724;
        }

        /* Badges de agentes (soporte múltiples agentes) */
        .agente-badge {
            display: inline-block;
            padding: 4px 10px;
            margin: 2px 3px;
            border-radius: 14px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #fff;
            white-space: nowrap;
        }

        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 6px;
            border-radius: 5px;
            transition: all 0.2s;
            font-size: 0.9rem;
        }

        .action-btn:hover {
            background-color: var(--light-gray);
        }

        /* Contador de notas en botones */
        .action-btn.btn-notas { position:relative; display:inline-flex; align-items:center; gap:4px; }
        .notes-count { display:inline-block; min-width:16px; padding:2px 5px; border-radius:10px; font-size:.55rem; font-weight:700; line-height:1; background:#e2e8f0; color:#334155; text-align:center; }
        .notes-count.has { background:#3f37c9; color:#fff; }
        .notes-count.empty { opacity:.5; }

        .btn-delete {
            color: var(--danger);
        }

        .btn-edit {
            color: var(--info);
        }

        .estado-select {
            padding: 6px 30px 6px 10px;
            border-radius: 8px;
            border: 1px solid var(--light-gray);
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            width: auto;
            min-width: 138px;
            max-width: 100%;
            white-space: nowrap;
            box-sizing: border-box;
            background-color: #fff;
            appearance: auto;
            -webkit-appearance: menulist;
        }

        /* Ajustar colores según estado usando la flecha custom */
        .estado-select.estado-pendiente { background-color:#fff8e1; border-color:#ffeaa7; }
        .estado-select.estado-proceso { background-color:#fff2cc; border-color:#ffdf7e; }
        .estado-select.estado-finalizado { background-color:#d4edda; border-color:#c3e6cb; }

        .estado-select.estado-pendiente { 
            background-color: #fff3cd; 
            color: #856404; 
            border-color: #ffeaa7; 
        }
        
        .estado-select.estado-proceso { 
            background-color: #ffeaa7; 
            color: #7a5a00; 
            border-color: #ffdf7e; 
        }
        
        .estado-select.estado-finalizado { 
            background-color: #d4edda; 
            color: #155724; 
            border-color: #c3e6cb; 
        }

        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
        }

        .filter-select {
            padding: 8px;
            border-radius: 6px;
            border: 1px solid var(--light-gray);
            flex: 1;
            min-width: 140px;
            font-size: 0.9rem;
        }

        .tabs {
            display: flex;
            margin-bottom: 15px;
            border-bottom: 1px solid var(--light-gray);
            gap: 8px;
        }

        .tab-btn {
            padding: 9px 16px;
            background: #f1f5f9;
            border: 1px solid #d8e2f1;
            border-bottom: 1px solid #d8e2f1;
            border-radius: 10px 10px 0 0;
            cursor: pointer;
            font-weight: 600;
            color: #334155;
            transition: all 0.3s;
        }

        .tab-btn.active {
            color: #0b2c8a;
            background: #ffffff;
            border-bottom-color: #ffffff;
            box-shadow: 0 -6px 12px rgba(15, 23, 42, 0.05);
        }

        /* DataTables custom styles */
        .dataTables_wrapper {
            position: relative;
            width: 100%;
        }
        
        .dataTables_length,
        .dataTables_filter,
        .dataTables_info,
        .dataTables_paginate {
            margin: 15px 0;
        }
        
        .dataTables_length select {
            padding: 6px;
            border-radius: 4px;
            border: 1px solid var(--light-gray);
            margin: 0 5px;
        }
        
        .dataTables_filter input {
            padding: 6px;
            border-radius: 4px;
            border: 1px solid var(--light-gray);
            margin-left: 5px;
        }
        
        .dt-buttons {
            margin-bottom: 15px;
        }
        
        /* Estilo mejorado para el botón de Excel */
        .dt-button.excel-btn {
            background: linear-gradient(135deg, #21b374 0%, #18965f 100%) !important;
            color: white !important;
            border: none !important;
            padding: 10px 16px !important;
            border-radius: 8px !important;
            cursor: pointer !important;
            font-weight: 600 !important;
            transition: all 0.3s ease !important;
            box-shadow: 0 3px 8px rgba(33, 179, 116, 0.25) !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
        }
        
        .dt-button.excel-btn:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 5px 15px rgba(33, 179, 116, 0.4) !important;
            background: linear-gradient(135deg, #25c481 0%, #1bab6b 100%) !important;
        }
        
        .dt-button.excel-btn:active {
            transform: translateY(0) !important;
            box-shadow: 0 2px 5px rgba(33, 179, 116, 0.3) !important;
        }
        
        .dataTables_paginate .paginate_button {
            padding: 5px 10px;
            margin: 0 2px;
            border: 1px solid var(--light-gray);
            border-radius: 4px;
            cursor: pointer;
        }
        
        .dataTables_paginate .paginate_button.current {
            background-color: #000147;
            color: white;
            border-color: #000147;
        }

        /* Modal styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 15px;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal {
            background: white;
            border-radius: 10px;
            width: 100%;
            max-width: 95%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            transform: translateY(30px);
            transition: transform 0.4s;
        }

        .modal-overlay.active .modal {
            transform: translateY(0);
        }

        /* Notas modal: más compacto */
        .modal-notas { 
            max-width: 95%; 
        }
        
        .modal-notas .modal-header { 
            padding: 14px 16px; 
        }
        
        .modal-notas .modal-title { 
            font-size: 1.2rem; 
        }
        
        .modal-notas .modal-body { 
            padding: 16px; 
        }
        
        .modal-notas .btn { 
            padding: 7px 12px; 
            font-size: 0.9rem;
        }
        
        .modal-notas .form-control { 
            padding: 8px 10px; 
            font-size: 0.9rem; 
        }

        .modal-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.3rem;
            color: var(--primary);
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray);
        }

        .modal-body {
            padding: 20px;
            max-height: 60vh;
            overflow-y: auto;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: var(--dark);
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--light-gray);
            border-radius: 6px;
            font-size: 0.9rem;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .modal-footer {
            padding: 16px 20px;
            border-top: 1px solid var(--light-gray);
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 0.9rem;
        }

        .btn-cancel {
            background-color: var(--light);
            color: var(--dark);
        }

        .btn-cancel:hover {
            background-color: var(--gray);
            color: white;
        }

        .btn-submit {
            background-color: var(--primary);
            color: white;
        }

        .btn-submit:hover {
            background-color: var(--secondary);
        }

        /* Descripción en móviles */
        .desc-container {
            display: flex;
            flex-direction: column;
        }
        
        .desc-short {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 150px;
        }
        
        .desc-actions {
            display: flex;
            gap: 5px;
            margin-top: 5px;
        }
        
        /* Nuevos estilos para carga de imágenes */
        .image-upload-container {
            margin-top: 10px;
            border: 1px dashed #ccc;
            padding: 10px;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        
        .image-preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        
        .image-preview {
            position: relative;
            width: 100px;
            height: 100px;
            border: 1px solid #ddd;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .image-preview .remove-image {
            position: absolute;
            top: 2px;
            right: 2px;
            background: rgba(255,255,255,0.8);
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 12px;
        }
        
        .btn-add-image {
            background-color: #e9ecef;
            border: 1px solid #ced4da;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 10px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-add-image:hover {
            background-color: #dee2e6;
        }
        
        /* Estilos para imágenes en descripción y notas */
        .content-with-images {
            white-space: normal; /* antes: pre-line; ahora formateamos en JS */
            font-size: 0.97rem;
            line-height: 1.5;
        }
        .content-with-images p { margin: 0 0 10px; }
        .content-with-images ul { margin: 6px 0 10px 20px; padding-left: 18px; list-style: disc; }
        .content-with-images li { margin: 4px 0; }
        .content-with-images strong { color: #0f172a; }
        
        .content-images {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        
        .content-image-thumb {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .content-image-thumb:hover {
            transform: scale(1.05);
        }
        
        /* Archivos adjuntos en descripción */
        .content-files { margin-top: 12px; }
        .content-file-item { display:flex; align-items:center; gap:8px; margin-bottom:8px; }
        .content-file-item i { color:#000147; }
        .content-file-item a { color:#0d6efd; text-decoration:none; word-break: break-word; }
        .content-file-item a:hover { text-decoration:underline; }

        /* Lightbox personalizado */
        .lightbox-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }
        
        .lightbox-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .lightbox-content {
            position: relative;
            max-width: 90%;
            max-height: 90%;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .lightbox-content img {
            max-width: 100%;
            max-height: 90vh;
            border-radius: 5px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.5);
        }
        
        .lightbox-close {
            position: absolute;
            top: 20px;
            right: 20px;
            color: white;
            font-size: 30px;
            cursor: pointer;
            background: none;
            border: none;
            z-index: 2001;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(0,0,0,0.5);
            border-radius: 50%;
        }
        
        .lightbox-nav {
            position: absolute;
            top: 50%;
            width: 100%;
            display: flex;
            justify-content: space-between;
            transform: translateY(-50%);
            padding: 0 20px;
            z-index: 2001;
        }
        
        .lightbox-prev, .lightbox-next {
            color: white;
            font-size: 30px;
            cursor: pointer;
            background: rgba(0,0,0,0.5);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            transition: background 0.3s;
        }
        
        .lightbox-prev:hover, .lightbox-next:hover {
            background: rgba(0,0,0,0.8);
        }
        
        .lightbox-counter {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            color: white;
            font-size: 16px;
            background: rgba(0,0,0,0.5);
            padding: 5px 15px;
            border-radius: 20px;
            z-index: 2001;
        }

        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .container {
                max-width: 100%;
            }
        }

        @media (max-width: 992px) {
            table {
                min-width: 900px;
            }
            
            .stats-container {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
            
            .dataTables_wrapper .dataTables_length,
            .dataTables_wrapper .dataTables_filter,
            .dataTables_wrapper .dataTables_info,
            .dataTables_wrapper .dataTables_paginate {
                font-size: 0.9rem;
            }
            
            .welcome-text {
                padding: 15px;
            }
            
            .lightbox-prev, .lightbox-next {
                width: 40px;
                height: 40px;
                font-size: 24px;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            h1 {
                font-size: 1.5rem;
            }
            
            h2 {
                font-size: 1.2rem;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .stat-number {
                font-size: 2rem;
            }
            
            .card {
                padding: 15px;
            }
            
            .filters {
                flex-direction: column;
            }
            
            .filter-select {
                width: 100%;
            }
            
            .dataTables_wrapper .dataTables_length,
            .dataTables_wrapper .dataTables_filter {
                float: none;
                margin-bottom: 10px;
            }
            
            .dataTables_wrapper .dataTables_length select,
            .dataTables_wrapper .dataTables_filter input {
                width: 100%;
                margin: 5px 0;
            }
            
            .dt-buttons {
                text-align: center;
                margin-bottom: 10px;
            }
            
            .dt-button.excel-btn {
                display: inline-block;
                margin: 5px;
                width: calc(50% - 10px);
                padding: 8px 12px !important;
                font-size: 0.85rem !important;
            }
            
            .dataTables_wrapper .dataTables_info,
            .dataTables_wrapper .dataTables_paginate {
                text-align: center;
                float: none;
                margin-top: 10px;
            }
            
            .modal-body {
                padding: 15px;
            }
            
            .modal-header {
                padding: 12px 15px;
            }
            
            .modal-footer {
                padding: 12px 15px;
                flex-direction: column;
            }
            
            .modal-footer .btn {
                width: 100%;
                margin-bottom: 5px;
            }
            
            header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .btn-primary {
                width: 100%;
                justify-content: center;
            }
            
            .lightbox-content img {
                max-width: 95%;
            }
            
            .lightbox-close {
                top: 10px;
                right: 10px;
                font-size: 24px;
                width: 35px;
                height: 35px;
            }
            
            .lightbox-prev, .lightbox-next {
                width: 35px;
                height: 35px;
                font-size: 20px;
            }
        }

        @media (max-width: 576px) {
            th, td {
                padding: 8px 6px;
                font-size: 0.8rem;
            }
            
            .badge {
                font-size: 0.75rem;
                min-width: 60px;
                padding: 4px 8px;
            }
            
            .estado-select {
                font-size: 0.8rem;
                min-width: 130px;
            }
            
            .action-btn {
                padding: 4px;
                font-size: 0.8rem;
            }
            
            .tab-btn {
                padding: 6px 10px;
                font-size: 0.9rem;
            }
            
            .dataTables_wrapper .dataTables_paginate .paginate_button {
                padding: 4px 8px;
                margin: 0 1px;
                font-size: 0.8rem;
            }
            
            .welcome-text {
                padding: 12px;
            }
            
            .stat-card {
                padding: 15px;
            }
            
            .stat-number {
                font-size: 1.8rem;
            }
            
            .content-image-thumb {
                width: 60px;
                height: 60px;
            }
        }

        @media (min-width: 992px) {
            .desc-container {
                flex-direction: row;
                align-items: center;
            }
            
            .desc-actions {
                margin-top: 0;
                margin-left: 5px;
            }
        }

        /* Toast personalizado para notificaciones de finalización */
        .swal2-toast-large {
            min-width: 350px !important;
            font-size: 14px !important;
        }
        
        .swal2-toast-large .swal2-title {
            font-size: 15px !important;
            line-height: 1.4 !important;
        }

        /* Developer hero profesional */
        .dev-hero {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 26px;
        }

        .welcome-text {
            margin-bottom: 0;
            border: 1px solid #dbe7ff;
            background:
                radial-gradient(circle at 84% 16%, rgba(37, 99, 235, 0.24), transparent 42%),
                radial-gradient(circle at 20% 90%, rgba(20, 184, 166, 0.14), transparent 40%),
                linear-gradient(135deg, #e9f1ff 0%, #e5f5ff 45%, #f3fbff 100%);
            box-shadow: 0 12px 28px rgba(12, 35, 94, 0.14);
        }

        .welcome-text h3 {
            margin: 0 0 8px;
            color: #0f2f88;
            font-size: 1.18rem;
            font-weight: 800;
            letter-spacing: 0.1px;
        }

        .welcome-text p {
            margin-bottom: 8px;
            color: #1e3a8a;
        }

        .dev-hero-strip {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .dev-pill {
            background: linear-gradient(140deg, #ffffff 0%, #f8fbff 100%);
            border: 1px solid #d4e2f5;
            border-radius: 999px;
            padding: 8px 12px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #0f2f88;
            font-size: 0.84rem;
            font-weight: 700;
            box-shadow: 0 8px 16px rgba(15, 23, 42, 0.08);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .dev-pill:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 22px rgba(15, 23, 42, 0.13);
        }

        .dev-pill:nth-child(1) {
            border-color: #bfdbfe;
            color: #1d4ed8;
            background: linear-gradient(140deg, #eff6ff 0%, #ffffff 100%);
        }

        .dev-pill:nth-child(2) {
            border-color: #bae6fd;
            color: #0369a1;
            background: linear-gradient(140deg, #ecfeff 0%, #ffffff 100%);
        }

        .dev-pill:nth-child(3) {
            border-color: #99f6e4;
            color: #0f766e;
            background: linear-gradient(140deg, #f0fdfa 0%, #ffffff 100%);
        }

        @media (max-width: 860px) {
            .dev-hero-strip {
                gap: 8px;
            }
        }
    </style>
    <link rel="stylesheet" href="css/ticket_desc_document.css?v=17">
</head>
<body>
    <div class="container">
        <header>
            <div class="td-header-left">
                <img src="../images/logo.png" alt="ConlineWeb" onerror="this.style.display='none'">
                <div>
                    <h1><i class="fas fa-code"></i> Mis tickets</h1>
                    <div style="font-size:.78rem;color:#64748b;font-weight:600;margin-top:2px;">Panel desarrollador</div>
                </div>
            </div>
            <div class="td-header-actions">
                <?php if ($agenteNombre !== ''): ?>
                <span class="td-user-chip"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($agenteNombre, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
                <?php if ($tipoSesion === 1 || $tipoSesion === 2): ?>
                <a class="td-link-btn" href="<?= $tipoSesion === 2 ? 'index.php' : '../index.php' ?>"><i class="fas fa-arrow-left"></i> Panel</a>
                <?php endif; ?>
                <a class="td-link-btn td-link-btn--danger" href="cerrarSesion.php"><i class="fas fa-power-off"></i> Salir</a>
            </div>
        </header>

        <div class="dev-hero">
            <div class="welcome-text">
                <h3><i class="fas fa-clipboard-list"></i> Tus asignaciones</h3>
                <p>Revisa pendientes y en proceso, actualiza estados y deja notas. Usa las pestañas para ver activos o completados.</p>
            </div>
        </div>
<?php

if (!isset($conexion) || !($conexion instanceof mysqli) || $conexion->connect_error) {
    die('Error de conexión a la base de datos.');
}

// $id_usuario ya resuelto arriba (agente del usuario logueado / ?id=)

// === FILTROS POR PERIODO ===
$periodo_tipo = isset($_GET['periodo_tipo']) ? trim($_GET['periodo_tipo']) : '';
$periodo_ano = isset($_GET['periodo_ano']) ? intval($_GET['periodo_ano']) : 0;
$periodo_mes = isset($_GET['periodo_mes']) ? intval($_GET['periodo_mes']) : 0;
$periodo_semana = isset($_GET['periodo_semana']) ? intval($_GET['periodo_semana']) : 0;
$periodo_fecha_inicio = isset($_GET['periodo_fecha_inicio']) ? trim($_GET['periodo_fecha_inicio']) : '';
$periodo_fecha_fin = isset($_GET['periodo_fecha_fin']) ? trim($_GET['periodo_fecha_fin']) : '';

function getWhereFechaPorPeriodo($campoFecha, $periodo_tipo, $ano, $mes, $semana, $fechaInicio, $fechaFin) {
    $condicion = '';
    
    if ($periodo_tipo === 'ano' && $ano > 0) {
        $condicion = "AND YEAR($campoFecha) = $ano";
    } 
    elseif ($periodo_tipo === 'mes' && $ano > 0 && $mes > 0) {
        $condicion = "AND YEAR($campoFecha) = $ano AND MONTH($campoFecha) = $mes";
    }
    elseif ($periodo_tipo === 'semana' && $ano > 0 && $semana > 0) {
        $condicion = "AND YEAR($campoFecha) = $ano AND WEEK($campoFecha, 1) = $semana";
    }
    elseif ($periodo_tipo === 'personalizado' && $fechaInicio && $fechaFin) {
        $fechaInicioEsc = addslashes($fechaInicio);
        $fechaFinEsc = addslashes($fechaFin);
        $condicion = "AND DATE($campoFecha) >= '$fechaInicioEsc' AND DATE($campoFecha) <= '$fechaFinEsc'";
    }
    
    return $condicion;
}

function buildUrlParams($excluir = []) {
    global $id_usuario;
    $params = [];
    $idParam = isset($_GET['id']) ? intval($_GET['id']) : (int) $id_usuario;
    if ($idParam > 0 && !in_array('id', $excluir)) {
        $params['id'] = $idParam;
    }
    if (isset($_GET['periodo_tipo']) && trim($_GET['periodo_tipo']) && !in_array('periodo_tipo', $excluir)) {
        $params['periodo_tipo'] = trim($_GET['periodo_tipo']);
    }
    if (isset($_GET['periodo_ano']) && intval($_GET['periodo_ano']) > 0 && !in_array('periodo_ano', $excluir)) {
        $params['periodo_ano'] = intval($_GET['periodo_ano']);
    }
    if (isset($_GET['periodo_mes']) && intval($_GET['periodo_mes']) > 0 && !in_array('periodo_mes', $excluir)) {
        $params['periodo_mes'] = intval($_GET['periodo_mes']);
    }
    if (isset($_GET['periodo_semana']) && intval($_GET['periodo_semana']) > 0 && !in_array('periodo_semana', $excluir)) {
        $params['periodo_semana'] = intval($_GET['periodo_semana']);
    }
    if (isset($_GET['periodo_fecha_inicio']) && trim($_GET['periodo_fecha_inicio']) && !in_array('periodo_fecha_inicio', $excluir)) {
        $params['periodo_fecha_inicio'] = trim($_GET['periodo_fecha_inicio']);
    }
    if (isset($_GET['periodo_fecha_fin']) && trim($_GET['periodo_fecha_fin']) && !in_array('periodo_fecha_fin', $excluir)) {
        $params['periodo_fecha_fin'] = trim($_GET['periodo_fecha_fin']);
    }
    return http_build_query($params);
}

$anosDisponibles = [];
$resAno = $conexion->query("SELECT DISTINCT YEAR(fecha_solicitud) as ano FROM solicitudes WHERE fecha_solicitud IS NOT NULL AND fecha_solicitud != '0000-00-00' ORDER BY ano DESC");
while ($r = $resAno->fetch_assoc()) {
    $anosDisponibles[] = $r['ano'];
}
$anoActual = date('Y');
if (!in_array($anoActual, $anosDisponibles)) {
    array_unshift($anosDisponibles, $anoActual);
}

$mesesNombre = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

$whereFechaActivos = getWhereFechaPorPeriodo('s.fecha_solicitud', $periodo_tipo, $periodo_ano, $periodo_mes, $periodo_semana, $periodo_fecha_inicio, $periodo_fecha_fin);
$whereFechaFinalizados = getWhereFechaPorPeriodo('s.fecha_termina', $periodo_tipo, $periodo_ano, $periodo_mes, $periodo_semana, $periodo_fecha_inicio, $periodo_fecha_fin);

?>

        <?php
        // Obtener estadísticas (filtradas por agente resuelto)
        $id_usuario = (int) $id_usuario;
        // SOPORTE MÚLTIPLES AGENTES: usar FIND_IN_SET para buscar en CSV
        $whereUsuario = $id_usuario > 0 ? "AND (".generarFiltroAgente($id_usuario).")" : "";
        
        $pendientes = $conexion->query(
            "SELECT COUNT(*) as total FROM solicitudes s 
            WHERE s.estado = 'Pendiente' $whereUsuario $whereFechaActivos"
        )->fetch_assoc()['total'];
        
        $proceso = $conexion->query(
            "SELECT COUNT(*) as total FROM solicitudes s 
            WHERE s.estado = 'En Proceso' $whereUsuario $whereFechaActivos"
        )->fetch_assoc()['total'];
        
        $completados = $conexion->query(
            "SELECT COUNT(*) as total FROM solicitudes s 
            WHERE s.estado = 'Finalizado' $whereUsuario $whereFechaFinalizados"
        )->fetch_assoc()['total'];
        ?>

        <!-- Contadores de tickets -->
        <div class="stats-container">
            <div class="stat-card pending">
                <div class="stat-number" id="countPendientes"><?= $pendientes ?></div>
                <div class="stat-label">Tickets Pendientes</div>
            </div>
            <div class="stat-card in-progress">
                <div class="stat-number" id="countProceso"><?= $proceso ?></div>
                <div class="stat-label">En Proceso</div>
            </div>
            <div class="stat-card completed">
                <div class="stat-number" id="countCompletados"><?= $completados ?></div>
                <div class="stat-label">Completados</div>
            </div>
        </div>

        <?php
        // Helper: formatear fechas en español con abreviatura de mes en minúsculas y hora
        if (!function_exists('format_es_datetime')) {
            function format_es_datetime($datetime, $withTime = false) {
                if (!$datetime || $datetime === '0000-00-00 00:00:00') return '-';
                $timestamp = strtotime($datetime);
                if ($timestamp === false) return $datetime;

                // Intentar IntlDateFormatter si está disponible (abreviatura de mes en español)
                if (class_exists('IntlDateFormatter')) {
                    $pattern = $withTime ? "dd MMM yyyy HH:mm" : "dd MMM yyyy"; // ej: 22 sept. 2025 14:30
                    // Usar locale español (es_ES). Algunas instalaciones requieren ajustar al disponible.
                    $fmt = new IntlDateFormatter('es_ES', IntlDateFormatter::SHORT, $withTime ? IntlDateFormatter::SHORT : IntlDateFormatter::NONE);
                    // Forzar patrón para consistencia
                    $fmt->setPattern($pattern);
                    $result = $fmt->format($timestamp);
                    if ($result !== false) return mb_strtolower($result);
                }

                // Fallback simple
                $format = $withTime ? 'd/m/Y H:i' : 'd/m/Y';
                return date($format, $timestamp);
            }
        }
        ?>

        <div class="card">
            <h2><i class="fas fa-tasks"></i> Gestión de Tickets</h2>
            
            <div class="tabs">
                <button class="tab-btn active" id="tabPendientes">Tickets Activos</button>
                <button class="tab-btn" id="tabFinalizadas">Tickets Completados</button>
            </div>
            
            <form id="filtroPeriodoForm" method="GET" style="margin-bottom: 15px;">
                <input type="hidden" name="id" value="<?= $id_usuario > 0 ? $id_usuario : '' ?>">
                <div class="filters" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end;">
                    <div style="display: flex; flex-direction: column; gap: 4px; min-width: 140px;">
                        <label style="font-size: 0.85rem; font-weight: 600; color: #475569;">Tipo de periodo</label>
                        <select name="periodo_tipo" id="periodo_tipo" class="filter-select" style="padding: 8px; border-radius: 6px; border: 1px solid #d6e2f2; font-size: 0.9rem;">
                            <option value="" <?= $periodo_tipo === '' ? 'selected' : '' ?>>Todo el tiempo</option>
                            <option value="ano" <?= $periodo_tipo === 'ano' ? 'selected' : '' ?>>Por año</option>
                            <option value="mes" <?= $periodo_tipo === 'mes' ? 'selected' : '' ?>>Por mes</option>
                            <option value="semana" <?= $periodo_tipo === 'semana' ? 'selected' : '' ?>>Por semana</option>
                            <option value="personalizado" <?= $periodo_tipo === 'personalizado' ? 'selected' : '' ?>>Personalizado</option>
                        </select>
                    </div>
                    
                    <div id="grupo_ano" style="display: <?= in_array($periodo_tipo, ['ano', 'mes', 'semana']) ? 'flex' : 'none' ?>; flex-direction: column; gap: 4px; min-width: 100px;">
                        <label style="font-size: 0.85rem; font-weight: 600; color: #475569;">Año</label>
                        <select name="periodo_ano" id="periodo_ano" class="filter-select" style="padding: 8px; border-radius: 6px; border: 1px solid #d6e2f2; font-size: 0.9rem;">
                            <?php foreach ($anosDisponibles as $ano): ?>
                                <option value="<?= $ano ?>" <?= ($periodo_ano == $ano || ($periodo_ano == 0 && $ano == $anoActual)) ? 'selected' : '' ?>><?= $ano ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div id="grupo_mes" style="display: <?= $periodo_tipo === 'mes' ? 'flex' : 'none' ?>; flex-direction: column; gap: 4px; min-width: 120px;">
                        <label style="font-size: 0.85rem; font-weight: 600; color: #475569;">Mes</label>
                        <select name="periodo_mes" id="periodo_mes" class="filter-select" style="padding: 8px; border-radius: 6px; border: 1px solid #d6e2f2; font-size: 0.9rem;">
                            <?php foreach ($mesesNombre as $num => $nombre): ?>
                                <option value="<?= $num ?>" <?= ($periodo_mes == $num || ($periodo_mes == 0 && $num == date('n'))) ? 'selected' : '' ?>><?= $nombre ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div id="grupo_semana" style="display: <?= $periodo_tipo === 'semana' ? 'flex' : 'none' ?>; flex-direction: column; gap: 4px; min-width: 100px;">
                        <label style="font-size: 0.85rem; font-weight: 600; color: #475569;">Semana</label>
                        <select name="periodo_semana" id="periodo_semana" class="filter-select" style="padding: 8px; border-radius: 6px; border: 1px solid #d6e2f2; font-size: 0.9rem;">
                            <?php 
                            $semanaActual = date('W');
                            for ($s = 1; $s <= 52; $s++): ?>
                                <option value="<?= $s ?>" <?= ($periodo_semana == $s || ($periodo_semana == 0 && $s == $semanaActual)) ? 'selected' : '' ?>>Semana <?= $s ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div id="grupo_personalizado" style="display: <?= $periodo_tipo === 'personalizado' ? 'flex' : 'none' ?>; flex-wrap: wrap; gap: 10px; align-items: flex-end;">
                        <div style="display: flex; flex-direction: column; gap: 4px; min-width: 140px;">
                            <label style="font-size: 0.85rem; font-weight: 600; color: #475569;">Fecha inicio</label>
                            <input type="date" name="periodo_fecha_inicio" id="periodo_fecha_inicio" value="<?= htmlspecialchars($periodo_fecha_inicio) ?>" 
                                class="filter-select" style="padding: 8px; border-radius: 6px; border: 1px solid #d6e2f2; font-size: 0.9rem;">
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 4px; min-width: 140px;">
                            <label style="font-size: 0.85rem; font-weight: 600; color: #475569;">Fecha fin</label>
                            <input type="date" name="periodo_fecha_fin" id="periodo_fecha_fin" value="<?= htmlspecialchars($periodo_fecha_fin) ?>"
                                class="filter-select" style="padding: 8px; border-radius: 6px; border: 1px solid #d6e2f2; font-size: 0.9rem;">
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 8px; align-items: flex-end;">
                        <button type="submit" class="btn-primary" style="padding: 8px 16px; font-size: 0.9rem;">
                            <i class="fas fa-filter"></i> Aplicar
                        </button>
                        <?php if ($periodo_tipo !== ''): ?>
                            <a href="tickets_desarrollador.php<?= $id_usuario > 0 ? '?id='.$id_usuario : '' ?>" class="btn" style="background: #f1f5f9; color: #475569; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-size: 0.9rem; font-weight: 600;">
                                <i class="fas fa-times"></i> Limpiar
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($periodo_tipo !== ''): ?>
                    <div style="margin-top: 10px; padding: 8px 12px; background: #f0f7ff; border-radius: 6px; font-size: 0.85rem; color: #1d4ed8; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-info-circle"></i>
                        <span>
                            <?php
                            $labelFiltro = '';
                            if ($periodo_tipo === 'ano') {
                                $a = $periodo_ano > 0 ? $periodo_ano : $anoActual;
                                $labelFiltro = "Filtrando por año: " . $a;
                            } elseif ($periodo_tipo === 'mes') {
                                $a = $periodo_ano > 0 ? $periodo_ano : $anoActual;
                                $m = $periodo_mes > 0 ? $periodo_mes : date('n');
                                $labelFiltro = "Filtrando por: " . $mesesNombre[$m] . " de " . $a;
                            } elseif ($periodo_tipo === 'semana') {
                                $a = $periodo_ano > 0 ? $periodo_ano : $anoActual;
                                $s = $periodo_semana > 0 ? $periodo_semana : date('W');
                                $labelFiltro = "Filtrando por: Semana $s de $a";
                            } elseif ($periodo_tipo === 'personalizado') {
                                $labelFiltro = "Filtrando por: $periodo_fecha_inicio hasta $periodo_fecha_fin";
                            }
                            echo $labelFiltro;
                            ?>
                            <?php if ($periodo_tipo === 'personalizado'): ?>
                                <span style="font-size: 0.75rem; color: #64748b; margin-left: 8px;">
                                    (Activos: por fecha solicitud | Completados: por fecha finalización)
                                </span>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endif; ?>
            </form>
            
            <div class="table-container">
                <div id="panelPendientes">
                    <table id="ticketsTablePendientes" class="display" style="width:100%">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Título</th>
                                <th>Empresa</th>
                                <th>Proyecto</th>
                                <th>Descripción</th>
                                <th>Estado</th>
                                <th>Asignado a</th>
                                <th>Prioridad</th>
                                <th>Inicio</th>
                                <th>Fecha Límite</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Detectar si existe la tabla de notas para poder hacer JOIN y contar
                            $hasNotasTable = false;
                            try { if($chk=$conexion->query("SHOW TABLES LIKE 'solicitudes_notas'")){ $hasNotasTable = $chk->num_rows>0; $chk->close(); } } catch(Exception $e){ $hasNotasTable=false; }
                            $joinNotas = $hasNotasTable ? "LEFT JOIN (SELECT solicitud_id, COUNT(*) cnt FROM solicitudes_notas GROUP BY solicitud_id) sn ON sn.solicitud_id = s.id" : "";
                            $campoNotas = $hasNotasTable ? "COALESCE(sn.cnt,0) AS notas_count," : "0 AS notas_count,";
                            
                            // SOPORTE MÚLTIPLES AGENTES: no hacer JOIN con agentes, usar función helper para mostrar
                            $sqlActivos = "SELECT s.*, $campoNotas c.empresa AS empresa_nombre, p.nombre_proyecto ";
                            $sqlActivos .= "FROM solicitudes s ";
                            $sqlActivos .= "LEFT JOIN clientes c ON s.id_cliente = c.id ";
                            $sqlActivos .= "LEFT JOIN proyectos p ON s.id_proyecto = p.id_proyecto ";
                            if($joinNotas) $sqlActivos .= $joinNotas.' ';
                            $sqlActivos .= "WHERE s.estado != 'Finalizado' $whereUsuario $whereFechaActivos ORDER BY s.fecha_solicitud DESC";
                            $resultado = $conexion->query($sqlActivos);
                            while($fila = $resultado->fetch_assoc()){ 
                                $estadoClass = '';
                                if ($fila['estado'] == 'Pendiente') $estadoClass = 'badge-pendiente';
                                if ($fila['estado'] == 'En Proceso') $estadoClass = 'badge-proceso';
                                $prioridadClass = '';
                                if ($fila['prioridad'] == 'Alta') $prioridadClass = 'badge-alta';
                                if ($fila['prioridad'] == 'Media') $prioridadClass = 'badge-media';
                                if ($fila['prioridad'] == 'Baja') $prioridadClass = 'badge-baja';
                                $descripcionData = json_decode($fila['descripcion'], true);
                                $descripcionTexto = isset($descripcionData['text']) ? $descripcionData['text'] : $fila['descripcion'];
                                // Limpia etiquetas fuertes persistidas accidentalmente
                                $previewBase = preg_replace('/<\\/?strong>/i', '', $descripcionTexto);
                                // Quitar secuencias literales \r\n / \n / \r para la vista previa (vienen doble escapadas)
                                $previewBase = preg_replace('/\\\r\\\n|\\\n|\\\r/', ' ', $previewBase);
                                // Compactar espacios múltiples
                                $previewBase = preg_replace('/\s+/', ' ', $previewBase);
                                $descripcionImagenes = isset($descripcionData['images']) ? $descripcionData['images'] : [];
                                $descripcionFiles = isset($descripcionData['files']) ? $descripcionData['files'] : [];
                                $descripcionImagenesJson = htmlspecialchars(json_encode($descripcionImagenes), ENT_QUOTES);
                                $descripcionFilesJson = htmlspecialchars(json_encode($descripcionFiles), ENT_QUOTES);
                            ?>
                            <tr>
                                <td><?= $fila['id'] ?></td>
                                <td><?= htmlspecialchars($fila['titulo']) ?></td>
                                <td><?= htmlspecialchars($fila['empresa_nombre'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($fila['nombre_proyecto'] ?? '-') ?></td>
                                <td>
                                    <div class="desc-container">
                                        <div class="desc-short">
                                            <?= htmlspecialchars(mb_strimwidth(strip_tags($previewBase), 0, 50, '...')) ?>
                                        </div>
                                        <div class="desc-actions">
                                            <button type="button" class="action-btn btn-info btn-ver-desc"
                                                    data-id="<?= (int) $fila['id'] ?>"
                                                    title="Ver descripción completa">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php $notasCount = (int)($fila['notas_count'] ?? 0); ?>
                                            <button type="button" class="action-btn btn-notas" title="Notas" onclick="openNotasModal(<?= $fila['id'] ?>)">
                                                <i class="fas fa-note-sticky"></i><span class="notes-count <?= $notasCount>0?'has':'empty' ?>" data-ticket="<?= $fila['id'] ?>"><?= $notasCount ?></span>
                                            </button>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <form action="actualizar.php" method="POST" class="estado-form" onsubmit="return false;">
                                        <input type="hidden" name="id" value="<?= $fila['id'] ?>">
                                        <input type="hidden" name="return_to" value="tickets_desarrollador.php<?= $id_usuario>0?('?id='.$id_usuario):'' ?>">
                                        <select name="estado" class="estado-select">
                                            <option value="Pendiente" <?= $fila['estado']=='Pendiente'?'selected':'' ?>>Pendiente</option>
                                            <option value="En Proceso" <?= $fila['estado']=='En Proceso'?'selected':'' ?>>En Proceso</option>
                                            <option value="Finalizado" <?= $fila['estado']=='Finalizado'?'selected':'' ?>>Finalizado</option>
                                        </select>
                                        <input type="hidden" name="ajax" value="1">
                                    </form>
                                </td>
                                <td><?= mostrarAgentesHTML($conexion, $fila['usuario_asignado']) ?></td>
                                <td><span class="badge <?= $prioridadClass ?>"><?= $fila['prioridad'] ?></span></td>
                                <td><?= htmlspecialchars(format_es_datetime($fila['fecha_solicitud'], true)) ?></td>
                                <td><?= htmlspecialchars(format_es_datetime($fila['fecha_lim'], true)) ?></td>
                                <td>
                                    <div class="desc-actions">
                                        <button type="button" class="action-btn btn-ver-desc"
                                                data-id="<?= (int) $fila['id'] ?>"
                                                title="Ver descripción">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php $notasCount = (int)($fila['notas_count'] ?? 0); ?>
                                        <button type="button" class="action-btn btn-notas" title="Notas" onclick="openNotasModal(<?= $fila['id'] ?>)">
                                            <i class="fas fa-note-sticky"></i><span class="notes-count <?= $notasCount>0?'has':'empty' ?>" data-ticket="<?= $fila['id'] ?>"><?= $notasCount ?></span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <div id="panelFinalizadas" style="display:none;">
                    <table id="ticketsTableFinalizadas" class="display" style="width:100%">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Título</th>
                                <th>Empresa</th>
                                <th>Proyecto</th>
                                <th>Descripción</th>
                                <th>Estado</th>
                                <th>Asignado a</th>
                                <th>Prioridad</th>
                                <th>Fecha Límite</th>
                                <th>Inicio</th>
                                <th>Completado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // SOPORTE MÚLTIPLES AGENTES: no hacer JOIN con agentes, usar función helper para mostrar
                            $sqlFinalizados = "SELECT s.*, $campoNotas c.empresa AS empresa_nombre, p.nombre_proyecto FROM solicitudes s ";
                            $sqlFinalizados .= "LEFT JOIN clientes c ON s.id_cliente = c.id ";
                            $sqlFinalizados .= "LEFT JOIN proyectos p ON s.id_proyecto = p.id_proyecto ";
                            if($joinNotas) $sqlFinalizados .= $joinNotas.' ';
                            $sqlFinalizados .= "WHERE s.estado = 'Finalizado' $whereUsuario $whereFechaFinalizados ORDER BY s.fecha_termina DESC";
                            $resultado2 = $conexion->query($sqlFinalizados);
                            while($fila = $resultado2->fetch_assoc()){ 
                                $prioridadClass = '';
                                if ($fila['prioridad'] == 'Alta') $prioridadClass = 'badge-alta';
                                if ($fila['prioridad'] == 'Media') $prioridadClass = 'badge-media';
                                if ($fila['prioridad'] == 'Baja') $prioridadClass = 'badge-baja';
                                $descripcionData = json_decode($fila['descripcion'], true);
                                $descripcionTexto = isset($descripcionData['text']) ? $descripcionData['text'] : $fila['descripcion'];
                                $previewBase = preg_replace('/<\\/?strong>/i', '', $descripcionTexto);
                                // Quitar secuencias literales \r\n / \n / \r para la vista previa (vienen doble escapadas)
                                $previewBase = preg_replace('/\\\r\\\n|\\\n|\\\r/', ' ', $previewBase);
                                // Compactar espacios múltiples
                                $previewBase = preg_replace('/\s+/', ' ', $previewBase);
                                $descripcionImagenes = isset($descripcionData['images']) ? $descripcionData['images'] : [];
                                $descripcionFiles = isset($descripcionData['files']) ? $descripcionData['files'] : [];
                                $descripcionImagenesJson = htmlspecialchars(json_encode($descripcionImagenes), ENT_QUOTES);
                                $descripcionFilesJson = htmlspecialchars(json_encode($descripcionFiles), ENT_QUOTES);
                            ?>
                            <tr>
                                <td><?= $fila['id'] ?></td>
                                <td><?= htmlspecialchars($fila['titulo']) ?></td>
                                <td><?= htmlspecialchars($fila['empresa_nombre'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($fila['nombre_proyecto'] ?? '-') ?></td>
                                <td>
                                    <div class="desc-container">
                                        <div class="desc-short">
                                            <?= htmlspecialchars(mb_strimwidth(strip_tags($previewBase), 0, 50, '...')) ?>
                                        </div>
                                        <div class="desc-actions">
                                            <button type="button" class="action-btn btn-info btn-ver-desc"
                                                    data-id="<?= (int) $fila['id'] ?>"
                                                    title="Ver descripción completa">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php $notasCount = (int)($fila['notas_count'] ?? 0); ?>
                                            <button type="button" class="action-btn btn-notas" title="Notas" onclick="openNotasModal(<?= $fila['id'] ?>)">
                                                <i class="fas fa-note-sticky"></i><span class="notes-count <?= $notasCount>0?'has':'empty' ?>" data-ticket="<?= $fila['id'] ?>"><?= $notasCount ?></span>
                                            </button>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge badge-finalizado">Finalizado</span></td>
                                <td><?= mostrarAgentesHTML($conexion, $fila['usuario_asignado']) ?></td>
                                <td><span class="badge <?= $prioridadClass ?>"><?= $fila['prioridad'] ?></span></td>
                                <td><?= htmlspecialchars(format_es_datetime($fila['fecha_lim'], true)) ?></td>
                                <td><?= htmlspecialchars(format_es_datetime($fila['fecha_solicitud'], true)) ?></td>
                                <td><?= htmlspecialchars(format_es_datetime($fila['fecha_termina'], true)) ?></td>
                                <td>
                                    <div class="desc-actions">
                                        <button type="button" class="action-btn btn-ver-desc"
                                                data-id="<?= (int) $fila['id'] ?>"
                                                title="Ver descripción">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php $notasCount = (int)($fila['notas_count'] ?? 0); ?>
                                        <button type="button" class="action-btn btn-notas" title="Notas" onclick="openNotasModal(<?= $fila['id'] ?>)">
                                            <i class="fas fa-note-sticky"></i><span class="notes-count <?= $notasCount>0?'has':'empty' ?>" data-ticket="<?= $fila['id'] ?>"><?= $notasCount ?></span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para ver descripción -->
    <div class="modal-overlay" id="modalVerDesc">
        <div class="modal tk-doc-modal">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title">Brief del ticket</h2>
                    <span class="tk-doc-modal-sub">Orden recomendado para desarrollo</span>
                </div>
                <button class="modal-close" id="closeVerDesc" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body">
                <div id="tkDescMeta"></div>
                <div class="tk-doc-attachments-label" id="labelImagenesDesc" hidden>Referencias visuales</div>
                <div id="contenidoImagenes" class="content-images tk-doc-gallery"></div>
                <div class="tk-doc-attachments-label" id="labelArchivosDesc" hidden>Archivos</div>
                <div id="contenidoArchivos" class="content-files"></div>
                <div id="contenidoDescripcion"></div>
            </div>
            <div class="modal-footer tk-doc-footer">
                <div class="tk-doc-footer-actions">
                    <button type="button" class="btn tk-btn-ghost" id="btnCopyBrief"><i class="fas fa-copy" aria-hidden="true"></i> Copiar brief</button>
                    <button type="button" class="btn tk-btn-ghost" id="btnCopyUrl"><i class="fas fa-link" aria-hidden="true"></i> Copiar URL</button>
                    <button type="button" class="btn tk-btn-ghost" id="btnOpenNotasDesc"><i class="fas fa-note-sticky" aria-hidden="true"></i> Notas</button>
                    <button type="button" class="btn tk-btn-primary" id="btnMarcarProceso"><i class="fas fa-play" aria-hidden="true"></i> En Proceso</button>
                </div>
                <button type="button" class="btn btn-cancel" id="closeDescModal">Cerrar</button>
            </div>
        </div>
    </div>

    <!-- Modal de Notas -->
    <div class="modal-overlay" id="modalNotas">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">Notas del Ticket <span id="notasTicketId"></span></h2>
                <button class="modal-close" id="closeNotas">&times;</button>
            </div>
            <div class="modal-body">
                <div id="listaNotas" style="max-height:300px;overflow:auto; margin-bottom: 20px;"></div>
                <div class="form-group">
                    <label class="form-label">Agregar nueva nota</label>
                    <textarea id="notaTexto" class="form-control" placeholder="Escribe tus comentarios aquí..." rows="3"></textarea>
                    <div class="image-upload-container">
                        <label class="form-label">Imágenes (opcional)</label>
                        <div class="image-preview-container" id="notaImagePreviews"></div>
                        <input type="file" id="notaImageUpload" multiple accept="image/*" style="display: none;">
                        <button type="button" class="btn-add-image" onclick="document.getElementById('notaImageUpload').click()">
                            <i class="fas fa-plus"></i> Añadir imágenes
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancel" id="cancelNotas">Cancelar</button>
                <button type="button" class="btn btn-submit" id="guardarNotaBtn">Guardar Nota</button>
            </div>
        </div>
    </div>

    <!-- Lightbox personalizado -->
    <div class="lightbox-overlay" id="lightboxOverlay">
        <button class="lightbox-close" id="lightboxClose">&times;</button>
        <div class="lightbox-nav">
            <button class="lightbox-prev" id="lightboxPrev">&lt;</button>
            <button class="lightbox-next" id="lightboxNext">&gt;</button>
        </div>
        <div class="lightbox-content">
            <img id="lightboxImage" src="" alt="">
        </div>
        <div class="lightbox-counter" id="lightboxCounter"></div>
    </div>

    <!-- DataTables CSS/JS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <!-- SweetAlert2 JS -->
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

    <script>
        // Variables globales para las imágenes
        let notaImages = [];
        let currentLightboxImages = [];
        let currentLightboxIndex = 0;

        // Utils: normalizar saltos visibles y formatear texto legible
        function decodeVisibleEscapes(str) {
            if (typeof str !== 'string') return str;
            return str
                .replace(/\\r\\n/g, '\n')
                .replace(/\\n/g, '\n')
                .replace(/\\r/g, '\n');
        }
        function escapeHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        // Sanitizador con whitelist: permite solo tags seguros básicos
        function sanitizePreservingAllowed(raw){
            if (typeof raw !== 'string') return '';
            // Eliminar scripts completos
            let out = raw.replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi,'');
            // Eliminar comentarios HTML
            out = out.replace(/<!--([\s\S]*?)-->/g,'');
            // Quitar atributos peligrosos (on* y style) de cualquier tag
            out = out.replace(/<([^>]+)>/g, function(m, inside){
                let parts = inside.trim().split(/\s+/); if(!parts.length) return '';
                let tagName = parts[0].toLowerCase();
                const allowed = ['strong','b','i','em','u','ul','ol','li','br','p'];
                const isClosing = tagName.startsWith('/');
                const baseName = isClosing ? tagName.substring(1) : tagName;
                if(allowed.indexOf(baseName) === -1) return '';
                return isClosing ? ('</'+baseName+'>') : ('<'+baseName+'>');
            });
            return out;
        };

        function formatDescriptionText(raw) {
            const original = decodeVisibleEscapes(raw || '').trim();
            if(!original) return '<p>(Sin descripción)</p>';
            const collapsed = original.replace(/\n{3,}/g, '\n\n');
            const lines = collapsed.split('\n');
            let html = '';
            let inList = false;
            let para = [];
            function flushPara() {
                if (para.length) {
                    const content = para.map(l => sanitizePreservingAllowed(l)).join('<br>');
                    html += `<p>${content}</p>`;
                    para = [];
                }
            }
            function renderListItem(line) {
                const body = line.replace(/^[-*•]\s+/, '');
                const m = body.match(/^([^:]{2,}):\s*(.*)$/);
                if (m) {
                    const label = sanitizePreservingAllowed(m[1].trim());
                    const rest = sanitizePreservingAllowed(m[2]);
                    return `<li><strong>${label}:</strong> ${rest}</li>`;
                }
                return `<li>${sanitizePreservingAllowed(body)}</li>`;
            }
            for (const rawLine of lines) {
                const line = rawLine.trim();
                if (line === '') { 
                    flushPara(); 
                    if (inList) { html += '</ul>'; inList = false; }
                    continue; 
                }
                if (/^[-*•]\s+/.test(line)) {
                    flushPara();
                    if (!inList) { html += '<ul>'; inList = true; }
                    html += renderListItem(line);
                } else {
                    if (inList) { html += '</ul>'; inList = false; }
                    const m = line.match(/^([^:]{2,}):\s*(.*)$/);
                    if (m) {
                        const label = sanitizePreservingAllowed(m[1].trim());
                        const rest = sanitizePreservingAllowed(m[2]);
                        para.push(`<strong>${label}:</strong> ${rest}`);
                    } else {
                        para.push(line);
                    }
                }
            }
            flushPara(); if (inList) html += '</ul>';
            // Si el texto original ya contenía etiquetas permitidas y no se generó nada (caso raro), sanitizar entero
            if(!html) return sanitizePreservingAllowed(original);
            return html;
        }

        // Configuración de DataTables con selector de registros
        function initDataTables() {
            window.dtPendientes = $('#ticketsTablePendientes').DataTable({
                dom: 'Blfrtip',
                buttons: [
                    {
                        extend: 'excelHtml5',
                        text: '<i class="fas fa-file-excel"></i> Exportar a Excel&nbsp;&nbsp;',
                        className: 'excel-btn',
                        title: 'Tickets_Activos'
                    }
                ],
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
                pageLength: 10,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
                },
                responsive: true,
                createdRow: function(row, data) {
                    const prioridad = String(data[7] || '').toLowerCase();
                    row.classList.remove('priority-high', 'priority-medium', 'priority-low');
                    if (prioridad.includes('alta')) row.classList.add('priority-high');
                    else if (prioridad.includes('media')) row.classList.add('priority-medium');
                    else if (prioridad.includes('baja')) row.classList.add('priority-low');
                },
                order: [[0, 'desc']]
            });

            window.dtFinalizadas = $('#ticketsTableFinalizadas').DataTable({
                dom: 'Blfrtip',
                buttons: [
                    {
                        extend: 'excelHtml5',
                        text: '<i class="fas fa-file-excel"></i> Exportar a Excel',
                        className: 'excel-btn',
                        title: 'Tickets_Completados'
                    }
                ],
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
                pageLength: 10,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
                },
                responsive: true,
                createdRow: function(row, data) {
                    const prioridad = String(data[7] || '').toLowerCase();
                    row.classList.remove('priority-high', 'priority-medium', 'priority-low');
                    if (prioridad.includes('alta')) row.classList.add('priority-high');
                    else if (prioridad.includes('media')) row.classList.add('priority-medium');
                    else if (prioridad.includes('baja')) row.classList.add('priority-low');
                },
                order: [[0, 'desc']]
            });
        }

        // Utilidad para aplicar clase de color según estado
        function applyEstadoSelectClass(sel, estado){
            sel.classList.remove('estado-pendiente','estado-proceso','estado-finalizado');
            if(estado === 'Pendiente') sel.classList.add('estado-pendiente');
            else if(estado === 'En Proceso') sel.classList.add('estado-proceso');
            else if(estado === 'Finalizado') sel.classList.add('estado-finalizado');
        }
        window.applyEstadoSelectClass = applyEstadoSelectClass;

        // Inicializar dataset prevEstado y estilos
        document.querySelectorAll('select.estado-select').forEach(sel => {
            sel.dataset.prevEstado = sel.value;
            applyEstadoSelectClass(sel, sel.value);
        });

// --- Real-time sync: poll estados for visible tickets and update UI (agents) ---
(function(){
    const POLL_INTERVAL = 6000; // ms
    let lastLocalChange = {};

    function collectVisibleIds(){
        const ids = [];
        // Scan visible table rows and use the first cell (ID) when present
        document.querySelectorAll('table tbody tr').forEach(tr=>{
            const firstTd = tr.querySelector('td');
            const id = firstTd ? firstTd.textContent.trim() : null;
            if(id && !isNaN(id)) ids.push(id);
        });
        return ids;
    }

    async function pollAndUpdate(){
        const ids = collectVisibleIds();
        if(!ids.length) return;
        try{
            const res = await fetch('api_estados_obtener.php?ids=' + encodeURIComponent(ids.join(',')));
            if(!res.ok) return;
            const data = await res.json();
            if(!data || !data.items) return;

            let anyEstadoChanged = false;

            Object.keys(data.items).forEach(idStr => {
                const info = data.items[idStr];
                const id = String(idStr);
                const row = Array.from(document.querySelectorAll('table tr')).find(r => (r.querySelector('td') && r.querySelector('td').textContent.trim()===id));
                if(!row) return;
                const sel = row.querySelector('select.estado-select');
                if(!sel) return;
                const serverEstado = info.estado || sel.value;
                const prev = sel.dataset.prevEstado || sel.value;
                if(prev !== serverEstado){
                    sel.value = serverEstado;
                    sel.dataset.prevEstado = serverEstado;
                    anyEstadoChanged = true;
                    try{ applyEstadoSelectClass(sel, serverEstado); } catch(e){}
                    const now = Date.now();
                    if(!lastLocalChange[id] || (now - lastLocalChange[id]) > 3000){
                        Swal.fire({ toast:true, position:'top-end', icon:'info', title: `Ticket #${id} cambió a ${serverEstado}`, timer:2400, showConfirmButton:false });
                    }
                    // Actualizar contadores locales
                    updateCounters(prev, serverEstado);
                    
                    // Si cambió a Finalizado y estamos en tabla activos, remover la fila
                    if(serverEstado === 'Finalizado' && window.dtPendientes){
                        try{
                            const rowNode = window.dtPendientes.row(row);
                            if(rowNode && rowNode.length){
                                rowNode.remove().draw(false);
                            }
                        }catch(e){ console.warn('Error removing finalized row:', e); }
                    }
                }
            });

            // Recargar contadores desde servidor si hubo cambios
            if(anyEstadoChanged){
                try{ await recargarContadores(); }catch(e){}
            }
        }catch(e){ }
    }

    // Función para recargar contadores desde el servidor
    async function recargarContadores(){
        try{
            const agenteId = new URLSearchParams(window.location.search).get('id') || '';
            const url = 'api_contadores.php' + (agenteId ? '?id_agente=' + agenteId : '');
            const res = await fetch(url);
            if(!res.ok) return;
            const data = await res.json();
            if(data && data.success && data.counts){
                const pEl = document.getElementById('countPendientes');
                const procEl = document.getElementById('countProceso');
                const finEl = document.getElementById('countCompletados');
                if(pEl) pEl.textContent = data.counts.Pendiente || 0;
                if(procEl) procEl.textContent = data.counts['En Proceso'] || 0;
                if(finEl) finEl.textContent = data.counts.Finalizado || 0;
            }
        }catch(e){ console.warn('Error recargando contadores:', e); }
    }

    // mark local changes to avoid duplicate alerts (handler already exists above)
    document.addEventListener('change', function(e){
        const sel = e.target;
        if(!sel.matches('select.estado-select')) return;
        const id = sel.closest('form')?.querySelector('input[name="id"]')?.value;
        if(id) lastLocalChange[id] = Date.now();
    });

    // Detectar nuevas solicitudes asignadas al agente
    let lastKnownTotal = 0;
    async function checkNewTickets(){
        try{
            const agenteId = new URLSearchParams(window.location.search).get('id');
            if(!agenteId) return; // Solo si hay agente en URL
            
            const res = await fetch('api_contadores.php?id_agente=' + agenteId + '&timestamp=' + Date.now());
            if(!res.ok) return;
            const data = await res.json();
            
            if(data && data.success && data.counts){
                const currentTotal = (data.counts.Pendiente || 0) + (data.counts['En Proceso'] || 0) + (data.counts.Finalizado || 0);
                
                // Inicializar en primera ejecución
                if(lastKnownTotal === 0){
                    lastKnownTotal = currentTotal;
                    return;
                }
                
                // Si hay más tickets que antes, mostrar notificación simple tipo toast
                if(currentTotal > lastKnownTotal){
                    const diff = currentTotal - lastKnownTotal;
                    lastKnownTotal = currentTotal;
                    
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'info',
                        title: `${diff} nueva${diff > 1 ? 's' : ''} solicitud${diff > 1 ? 'es' : ''} asignada${diff > 1 ? 's' : ''}`,
                        showConfirmButton: false,
                        timer: 4000,
                        timerProgressBar: true
                    });
                }
            }
        }catch(e){ console.warn('Error checking new tickets:', e); }
    }

    setInterval(pollAndUpdate, POLL_INTERVAL);
    setTimeout(pollAndUpdate, 800);
    
    // Verificar nuevas solicitudes cada 5 segundos para notificaciones casi en tiempo real
    setInterval(checkNewTickets, 5000);
    setTimeout(checkNewTickets, 2000);
})();

        // Actualizar contadores sin recargar
        function updateCounters(prevE, newE){
            if(prevE === newE) return;
            const pEl = document.getElementById('countPendientes');
            const procEl = document.getElementById('countProceso');
            const finEl = document.getElementById('countCompletados');
            // If none of the counter elements exist, nothing to do
            if(!pEl && !procEl && !finEl) return;
            const toInt = el => (el && el.textContent) ? (parseInt(el.textContent.trim()) || 0) : 0;
            let p = toInt(pEl), pr = toInt(procEl), f = toInt(finEl);
            // Quitar del anterior
            if(prevE === 'Pendiente') p = Math.max(0,p-1);
            else if(prevE === 'En Proceso') pr = Math.max(0,pr-1);
            else if(prevE === 'Finalizado') f = Math.max(0,f-1);
            // Agregar al nuevo
            if(newE === 'Pendiente') p++;
            else if(newE === 'En Proceso') pr++;
            else if(newE === 'Finalizado') f++;
            if(pEl) pEl.textContent = p;
            if(procEl) procEl.textContent = pr;
            if(finEl) finEl.textContent = f;
        }

        // Manejo de cambio de estado sin recargar
        document.addEventListener('change', async (e)=>{
            const sel = e.target;
            if(!sel.matches('select.estado-select')) return;
            const prev = sel.dataset.prevEstado || sel.value;
            const nuevo = sel.value;
            if(prev === nuevo) return; // no cambió
            const id = sel.closest('form')?.querySelector('input[name="id"]').value;
            if(!id){ console.warn('Sin ID de ticket'); return; }
            sel.disabled = true;
            const fd = new FormData();
            fd.append('id', id);
            fd.append('estado', nuevo);
            fd.append('ajax','1');
            try{
                const resp = await fetch('actualizar.php',{method:'POST', body: fd});
                let payload = null;
                let txt = null;
                try{ payload = await resp.json(); }catch(_){
                    try{ txt = await resp.text(); }catch(__){ txt = null; }
                }

                const okByJson = payload && (payload.success || payload.ok);
                const okByText = (typeof txt === 'string') && /(^|\W)(ok|success)(\W|$)/i.test(txt.trim());
                const ok = resp.ok || okByJson || okByText;

                if(!ok){ console.warn('Actualizar response not OK (dev)', { status: resp.status, payload: payload, text: txt }); }
                if(!ok){ throw new Error('Respuesta no OK'); }

                // OPTIONAL: payload may contain fecha_termina or email_enviado
                let data = payload || null;

                // Éxito: actualizar UI
                applyEstadoSelectClass(sel, nuevo);
                updateCounters(prev, nuevo);
                sel.dataset.prevEstado = nuevo;
                // Si finalizado, quitar fila de la tabla de pendientes
                if(nuevo === 'Finalizado'){
                    const rowEl = sel.closest('tr');
                    if(rowEl && window.dtPendientes){
                        window.dtPendientes.row(rowEl).remove().draw();
                    } else if(rowEl){ rowEl.remove(); }
                    
                    // Verificar si se envió el correo al jefe/admin
                    const emailEnviado = data && data.email_enviado;
                    let mensaje = '✅ Ticket finalizado exitosamente';
                    let icon = 'success';
                    
                    if (emailEnviado) {
                        mensaje += '<br><small>📧 Notificación enviada al jefe/administrador</small>';
                    } else {
                        mensaje += '<br><small>⚠️ No se pudo notificar al administrador (verifique logs)</small>';
                        icon = 'warning';
                    }
                    
                    Swal.fire({
                        toast: true, 
                        position: 'top-end', 
                        icon: icon, 
                        html: mensaje,
                        timer: 5000, 
                        showConfirmButton: false,
                        customClass: {
                            popup: 'swal2-toast-large'
                        }
                    });
                } else {
                    Swal.fire({
                        toast:true, position:'top-end', icon:'success', title:'Estado actualizado', timer:1800, showConfirmButton:false
                    });
                }
            } catch(err){
                console.error(err);
                // Revertir selección
                sel.value = prev;
                applyEstadoSelectClass(sel, prev);
                Swal.fire({icon:'error', title:'No se pudo actualizar', text:'Intenta nuevamente', confirmButtonColor:'#4361ee'});
            } finally {
                sel.disabled = false;
            }
        });

        // Modal de descripción
        const modalVerDesc = document.getElementById('modalVerDesc');
        const closeVerDesc = document.getElementById('closeVerDesc');
        const closeDescModal = document.getElementById('closeDescModal');
        const contenidoDescripcion = document.getElementById('contenidoDescripcion');
        const contenidoImagenes = document.getElementById('contenidoImagenes');
        const contenidoArchivos = document.getElementById('contenidoArchivos');
        
        function openVerDesc(desc, images, files, meta) {
            const metaObj = meta || {};
            if (typeof bindTicketDescModalChrome === 'function') bindTicketDescModalChrome();
            if (typeof fillTicketDescModal === 'function') {
                fillTicketDescModal({
                    desc: desc || '',
                    images: images || [],
                    files: files || [],
                    meta: metaObj,
                    onImageClick: openLightbox
                });
            } else {
                const metaHost = document.getElementById('tkDescMeta');
                const metaHtml = (typeof renderTicketMeta === 'function') ? renderTicketMeta(metaObj) : '';
                if (metaHost) metaHost.innerHTML = metaHtml;
                const bodyHtml = (typeof renderTicketDescDocument === 'function')
                    ? renderTicketDescDocument(desc || '', metaObj.id || 0)
                    : formatDescriptionText(desc || '');
                contenidoDescripcion.innerHTML = bodyHtml;
            }
            modalVerDesc.classList.add('active');
        }

        async function openVerDescById(ticketId) {
            contenidoDescripcion.innerHTML = '<p class="tk-muted" style="text-align:center;padding:20px 0">Cargando…</p>';
            if (contenidoImagenes) contenidoImagenes.innerHTML = '';
            if (contenidoArchivos) contenidoArchivos.innerHTML = '';
            const labImg = document.getElementById('labelImagenesDesc');
            const labFil = document.getElementById('labelArchivosDesc');
            if (labImg) labImg.hidden = true;
            if (labFil) labFil.hidden = true;
            modalVerDesc.classList.add('active');
            try {
                const r = await fetch('obtener.php?id=' + encodeURIComponent(ticketId) + '&_ts=' + Date.now());
                const d = await r.json();
                if (!d || !d.success || !d.solicitud) {
                    throw new Error((d && d.error) || 'No se pudo cargar');
                }
                const s = d.solicitud;
                openVerDesc(
                    s.descripcion_text || '',
                    s.descripcion_images || [],
                    s.descripcion_files || [],
                    {
                        id: s.id || ticketId,
                        titulo: s.titulo || '',
                        cliente: s.cliente_nombre || '',
                        proyecto: s.nombre_proyecto || '',
                        url: s.url_proyecto || '',
                        prioridad: s.prioridad || '',
                        estado: s.estado || '',
                        fecha_lim: s.fecha_lim || ''
                    }
                );
            } catch (err) {
                console.error(err);
                contenidoDescripcion.innerHTML = '<p class="tk-muted" style="text-align:center;padding:20px 0">No se pudo cargar la descripción</p>';
            }
        }
        
        function closeVerDescFunc() {
            modalVerDesc.classList.remove('active');
        }
        
        closeVerDesc.addEventListener('click', closeVerDescFunc);
        closeDescModal.addEventListener('click', closeVerDescFunc);
        modalVerDesc.addEventListener('click', (e) => {
            if (e.target === modalVerDesc) closeVerDescFunc();
        });
        
        // Lightbox functionality
        const lightboxOverlay = document.getElementById('lightboxOverlay');
        const lightboxClose = document.getElementById('lightboxClose');
        const lightboxImage = document.getElementById('lightboxImage');
        const lightboxPrev = document.getElementById('lightboxPrev');
        const lightboxNext = document.getElementById('lightboxNext');
        const lightboxCounter = document.getElementById('lightboxCounter');
        
        function openLightbox(images, index) {
            currentLightboxImages = images;
            currentLightboxIndex = index;
            updateLightboxImage();
            lightboxOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
            
            // Agregar evento para teclado
            document.addEventListener('keydown', handleLightboxKeydown);
        }
        
        function updateLightboxImage() {
            lightboxImage.src = currentLightboxImages[currentLightboxIndex];
            lightboxCounter.textContent = `${currentLightboxIndex + 1} / ${currentLightboxImages.length}`;
        }
        
        function closeLightbox() {
            lightboxOverlay.classList.remove('active');
            document.body.style.overflow = 'auto';
            document.removeEventListener('keydown', handleLightboxKeydown);
        }
        
        function navigateLightbox(direction) {
            currentLightboxIndex += direction;
            if (currentLightboxIndex < 0) currentLightboxIndex = currentLightboxImages.length - 1;
            if (currentLightboxIndex >= currentLightboxImages.length) currentLightboxIndex = 0;
            updateLightboxImage();
        }
        
        function handleLightboxKeydown(e) {
            if (e.key === 'Escape') closeLightbox();
            if (e.key === 'ArrowLeft') navigateLightbox(-1);
            if (e.key === 'ArrowRight') navigateLightbox(1);
        }
        
        lightboxClose.addEventListener('click', closeLightbox);
        lightboxPrev.addEventListener('click', () => navigateLightbox(-1));
        lightboxNext.addEventListener('click', () => navigateLightbox(1));
        lightboxOverlay.addEventListener('click', (e) => {
            if (e.target === lightboxOverlay) closeLightbox();
        });

        // Modal de notas
        const modalNotas = document.getElementById('modalNotas');
        const closeNotas = document.getElementById('closeNotas');
        const cancelNotas = document.getElementById('cancelNotas');
        const listaNotas = document.getElementById('listaNotas');
        const notasTicketIdSpan = document.getElementById('notasTicketId');
        const guardarNotaBtn = document.getElementById('guardarNotaBtn');
        const notaTexto = document.getElementById('notaTexto');
        const notaImageUpload = document.getElementById('notaImageUpload');
        const notaImagePreviews = document.getElementById('notaImagePreviews');
        
        let notasTicketIdActual = null;
        
        async function cargarNotas(idTicket) {
            listaNotas.innerHTML = '<p style="text-align: center; padding: 20px; color: #64748b;">Cargando notas...</p>';
            try {
                const response = await fetch('notas_listar.php?id=' + encodeURIComponent(idTicket));
                const html = await response.text();
                listaNotas.innerHTML = html || '<p style="text-align: center; padding: 20px; color: #64748b;">No hay notas aún</p>';
                
                // Inicializar lightbox para imágenes en notas
                listaNotas.querySelectorAll('.content-image-thumb').forEach((img, index) => {
                    const parent = img.closest('.nota-images');
                    if (parent) {
                        const images = Array.from(parent.querySelectorAll('.content-image-thumb')).map(i => i.src);
                        img.onclick = () => openLightbox(images, index);
                    }
                });
            } catch(e) {
                listaNotas.innerHTML = '<p style="text-align: center; padding: 20px; color: #dc2626;">Error al cargar notas</p>';
            }
        }
        
        function openNotasModal(idTicket) {
            notasTicketIdActual = idTicket;
            notasTicketIdSpan.textContent = '#' + idTicket;
            notaImages = [];
            notaImagePreviews.innerHTML = '';
            notaTexto.value = '';
            cargarNotas(idTicket);
            modalNotas.classList.add('active');
        }
        
        function closeNotasModal() {
            modalNotas.classList.remove('active');
            notaTexto.value = '';
        }
        
        closeNotas.addEventListener('click', closeNotasModal);
        cancelNotas.addEventListener('click', closeNotasModal);
        modalNotas.addEventListener('click', (e) => {
            if (e.target === modalNotas) closeNotasModal();
        });
        
        guardarNotaBtn.addEventListener('click', async () => {
            const texto = notaTexto.value.trim(); 
            if (!texto && notaImages.length === 0) return;
            
            const fd = new FormData();
            fd.set('solicitud_id', notasTicketIdActual);
            fd.set('autor', 'desarrollador');
            fd.set('nota', texto);
            
            // Añadir imágenes
            notaImages.forEach((image, index) => {
                fd.append(`nota_images[]`, image);
            });
            
            try {
                const res = await fetch('notas_guardar.php', { method: 'POST', body: fd });
                const ok = await res.text();
                if (ok.trim() === 'OK') { 
                    notaTexto.value = ''; 
                    notaImages = [];
                    notaImagePreviews.innerHTML = '';
                    await cargarNotas(notasTicketIdActual); 
                    // Incrementar contador visualmente (si existe span del ticket abierto)
                    try {
                        const span = document.querySelector('.notes-count[data-ticket="'+notasTicketIdActual+'"]');
                        if(span){
                            const curr = parseInt(span.textContent.trim()) || 0;
                            const next = curr + 1;
                            span.textContent = next;
                            span.classList.remove('empty');
                            span.classList.add('has');
                        }
                    } catch(_e){}
                }
            } catch(e) { 
                console.error(e); 
                Swal.fire({
                    title: 'Error',
                    text: 'No se pudo guardar la nota',
                    icon: 'error',
                    confirmButtonColor: '#4361ee'
                });
            }
        });
        
        // Manejo de carga de imágenes para notas
        notaImageUpload.addEventListener('change', function(e) {
            handleImageUpload(e, notaImages, 'notaImagePreviews');
        });
        
        function handleImageUpload(event, imagesArray, previewContainerId) {
            const files = event.target.files;
            const previewContainer = document.getElementById(previewContainerId);
            
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                if (!file.type.match('image.*')) continue;
                
                imagesArray.push(file);
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.createElement('div');
                    preview.className = 'image-preview';
                    
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    
                    const removeBtn = document.createElement('div');
                    removeBtn.className = 'remove-image';
                    removeBtn.innerHTML = '&times;';
                    removeBtn.onclick = function() {
                        const index = imagesArray.indexOf(file);
                        if (index > -1) {
                            imagesArray.splice(index, 1);
                        }
                        preview.remove();
                    };
                    
                    preview.appendChild(img);
                    preview.appendChild(removeBtn);
                    previewContainer.appendChild(preview);
                };
                
                reader.readAsDataURL(file);
            }
            
            // Resetear el input para permitir cargar la misma imagen otra vez
            event.target.value = '';
        }
        
        // Modal ojito: carga adjuntos desde obtener.php (rutas confiables)
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.btn-ver-desc');
            if (!btn) return;
            const id = parseInt(btn.getAttribute('data-id') || '0', 10);
            if (id > 0) {
                openVerDescById(id);
                return;
            }
            const desc = btn.getAttribute('data-desc');
            const imagesJson = btn.getAttribute('data-images') || '[]';
            const filesJson = btn.getAttribute('data-files') || '[]';
            let images = [];
            let files = [];
            try { images = JSON.parse(imagesJson); } catch (err) { console.error('Error parsing images JSON:', err); }
            try { files = JSON.parse(filesJson); } catch (err) { console.error('Error parsing files JSON:', err); }
            openVerDesc(desc, images, files);
        });

        // === FILTROS DE PERIODO ===
        const periodoTipo = document.getElementById('periodo_tipo');
        const grupoAno = document.getElementById('grupo_ano');
        const grupoMes = document.getElementById('grupo_mes');
        const grupoSemana = document.getElementById('grupo_semana');
        const grupoPersonalizado = document.getElementById('grupo_personalizado');

        function actualizarVisibilidadFiltros() {
            const tipo = periodoTipo ? periodoTipo.value : '';
            
            if (grupoAno) grupoAno.style.display = 'none';
            if (grupoMes) grupoMes.style.display = 'none';
            if (grupoSemana) grupoSemana.style.display = 'none';
            if (grupoPersonalizado) grupoPersonalizado.style.display = 'none';

            if (tipo === 'ano') {
                if (grupoAno) grupoAno.style.display = 'flex';
            } else if (tipo === 'mes') {
                if (grupoAno) grupoAno.style.display = 'flex';
                if (grupoMes) grupoMes.style.display = 'flex';
            } else if (tipo === 'semana') {
                if (grupoAno) grupoAno.style.display = 'flex';
                if (grupoSemana) grupoSemana.style.display = 'flex';
            } else if (tipo === 'personalizado') {
                if (grupoPersonalizado) grupoPersonalizado.style.display = 'flex';
            }
        }

        if (periodoTipo) {
            periodoTipo.addEventListener('change', actualizarVisibilidadFiltros);
        }

        // Tabs
        const tabPendientesBtn = document.getElementById('tabPendientes');
        const tabFinalizadasBtn = document.getElementById('tabFinalizadas');
        const panelPendientes = document.getElementById('panelPendientes');
        const panelFinalizadas = document.getElementById('panelFinalizadas');
        
        function showPendientes() {
            panelPendientes.style.display = 'block';
            panelFinalizadas.style.display = 'none';
            tabPendientesBtn.classList.add('active');
            tabFinalizadasBtn.classList.remove('active');
        }
        
        function showFinalizadas() {
            panelPendientes.style.display = 'none';
            panelFinalizadas.style.display = 'block';
            tabFinalizadasBtn.classList.add('active');
            tabPendientesBtn.classList.remove('active');
        }
        
        tabPendientesBtn.addEventListener('click', showPendientes);
        tabFinalizadasBtn.addEventListener('click', showFinalizadas);
        
        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            actualizarVisibilidadFiltros();
            initDataTables();
            showPendientes();
        });
    </script>
</body>
</html>