<?php
/**
 * Primera visita al ADM por dispositivo: pide ubicación una vez y la recuerda 90 días.
 */
declare(strict_types=1);

define('CW_ADM_SKIP_GEO_GATE', true);

require_once __DIR__ . '/auth_middleware.php';
require_once dirname(__DIR__) . '/includes/cw_adm_device_geo.php';

if (!isAdminSessionValid()) {
    redirectToLogin('Inicia sesión para acceder al panel.');
    exit;
}

$uid = (int) ($_SESSION['uid'] ?? 0);
$return = trim((string) ($_GET['return'] ?? '/'));
if ($return === '' || $return[0] !== '/' || str_contains($return, '//')) {
    $return = '/';
}

if (!adm_is_production_host() || !cw_adm_device_geo_requires_prompt($uid)) {
    header('Location: ' . $return);
    exit;
}

adm_send_noindex_headers();
$pageTitle = 'Confirmar ubicación';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Permissions-Policy" content="geolocation=(self), microphone=(), camera=()">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> · ConlineWeb</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f4f6fb; font-family: system-ui, sans-serif; }
        .card { max-width: 420px; width: 100%; border: 0; box-shadow: 0 12px 40px rgba(0,0,20,.08); border-radius: 16px; }
    </style>
</head>
<body>
<div class="card p-4 p-md-5">
    <h1 class="h4 mb-2">Ubicación del dispositivo</h1>
    <p class="text-muted small mb-4">Por seguridad registramos la ubicación <strong>una vez por dispositivo</strong> al entrar al panel admin. No volveremos a pedirla en este equipo durante 90 días.</p>
    <button type="button" class="btn btn-primary w-100 mb-2" id="btnGeo">Permitir ubicación y continuar</button>
    <a href="<?= htmlspecialchars(LOGIN_URL, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-link btn-sm w-100">Volver al login</a>
    <p class="text-danger small mt-3 mb-0 d-none" id="geoErr"></p>
</div>
<script>
(function () {
    var returnUrl = <?= json_encode($return, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    function deviceId() {
        var key = 'cw_adm_device_id';
        try {
            var id = localStorage.getItem(key);
            if (id && /^[a-f0-9\-]{16,64}$/i.test(id)) return id;
            id = (crypto && crypto.randomUUID) ? crypto.randomUUID() : ('d' + Date.now().toString(16) + Math.random().toString(16).slice(2));
            localStorage.setItem(key, id);
            return id;
        } catch (e) {
            return 'fallback-' + Date.now();
        }
    }

    function showErr(msg) {
        var el = document.getElementById('geoErr');
        el.textContent = msg;
        el.classList.remove('d-none');
    }

    document.getElementById('btnGeo').addEventListener('click', function () {
        var btn = this;
        btn.disabled = true;
        if (!navigator.geolocation) {
            showErr('Tu navegador no soporta geolocalización.');
            btn.disabled = false;
            return;
        }
        if (!window.isSecureContext) {
            showErr('Necesitas entrar por HTTPS para usar la ubicación.');
            btn.disabled = false;
            return;
        }
        navigator.geolocation.getCurrentPosition(function (pos) {
            var body = new URLSearchParams();
            body.set('device_id', deviceId());
            body.set('geo_lat', String(pos.coords.latitude));
            body.set('geo_lng', String(pos.coords.longitude));
            body.set('geo_accuracy', String(pos.coords.accuracy || ''));
            fetch('/api/geo_device.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                credentials: 'same-origin',
                body: body.toString()
            }).then(function (r) { return r.json(); }).then(function (data) {
                if (data && data.status === 'ok') {
                    window.location.href = returnUrl;
                    return;
                }
                showErr((data && data.message) ? data.message : 'No se pudo guardar la ubicación.');
                btn.disabled = false;
            }).catch(function () {
                showErr('Error de conexión. Inténtalo de nuevo.');
                btn.disabled = false;
            });
        }, function (err) {
            var msg = 'Permiso denegado. Candado en la URL → Ubicación → Permitir y recarga.';
            if (err && err.code === 2) msg = 'No pudimos leer tu posición. Activa el GPS del dispositivo.';
            if (err && err.code === 3) msg = 'Tiempo agotado. Inténtalo de nuevo.';
            showErr(msg);
            btn.disabled = false;
        }, { enableHighAccuracy: true, timeout: 30000, maximumAge: 0 });
    });
})();
</script>
</body>
</html>
