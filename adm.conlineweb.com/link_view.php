<?php
// Endpoint público para abrir vista filtrada de tickets SIN exponer interfaz admin.
// Uso: link_view.php?token=XXXXX
// Opcional: ?raw=1 para devolver JSON con validación.
require_once __DIR__.'/link_helper.php';
require_once __DIR__.'/conn.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$wantJson = isset($_GET['raw']);
$val = validar_link_token($token);

if($wantJson){
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($val, JSON_UNESCAPED_UNICODE); exit;
}

if(!$val['valid']){
    http_response_code(400);
    ?><!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Link inválido</title><style>body{font-family:system-ui;background:#101626;color:#eee;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0} .card{background:#1d2533;padding:32px 40px;border-radius:16px;max-width:540px;box-shadow:0 8px 28px -8px rgba(0,0,0,.6);} h1{margin:0 0 14px;font-size:1.4rem;color:#fff;} .err{font-size:.85rem;opacity:.8;margin-bottom:20px;} a.btn{display:inline-block;background:linear-gradient(135deg,#000147,#3f37c9);color:#fff;text-decoration:none;padding:10px 18px;border-radius:10px;font-weight:600;font-size:.8rem;letter-spacing:.5px;} a.btn:hover{filter:brightness(1.1);} </style></head><body><div class="card"><h1>Link inválido</h1><div class="err">Motivo: <?= htmlspecialchars($val['error']??'desconocido') ?></div><a class="btn" href="/">Volver</a></div></body></html><?php
    exit;
}

// Opcional: podríamos verificar que el cliente exista aún
$tokenSafe = urlencode($token);
header('Location: tickets_public.php?token='.$tokenSafe);
exit;
