<?php
$__cw_host = strtolower((string) preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
$__cw_is_production = $__cw_host !== '' && preg_match('/(^|\.)conlineweb\.com$/', $__cw_host);
$clienteAllowLocalReturnUrl = !$__cw_is_production;
$captchaEnabled = $__cw_is_production;
$returnUrlParam = trim((string) ($_GET['return_url'] ?? ''));
$loginEnvMismatch = $__cw_is_production && $returnUrlParam !== '' && (
    str_contains($returnUrlParam, 'localhost')
    || str_contains($returnUrlParam, '127.0.0.1')
    || str_contains($returnUrlParam, 'conlineweb.local')
);
$localLoginUrl = '';
if ($loginEnvMismatch && preg_match('#^(https?://[^/]+)#i', $returnUrlParam, $localMatch)) {
    $localOrigin = $localMatch[1];
    $project = basename(dirname(__DIR__));
    if (preg_match('#/([^/]+)/adm\.conlineweb\.com#', $returnUrlParam, $projectMatch)) {
        $project = $projectMatch[1];
    }
    $localLoginUrl = $localOrigin . '/' . $project . '/cliente.conlineweb.com/ingreso.php';
    $localLoginUrl .= '?' . http_build_query(['return_url' => $returnUrlParam]);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#000147">
    <?php
    require_once __DIR__ . '/includes/cliente_head_meta.php';
    cliente_boot_security_headers();
    ?>
    <?= cliente_robots_meta_markup() ?>
    <title><?= htmlspecialchars(cliente_document_title('Iniciar sesión'), ENT_QUOTES, 'UTF-8') ?></title>
    <?= cliente_favicon_markup() ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <?php if ($captchaEnabled): ?>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
    <style>
        :root {
            --cw-navy: #000147;
            --cw-navy-mid: #0b0b5c;
            --cw-mint: #10b981;
            --cw-ink: #0f172a;
            --cw-muted: #64748b;
            --cw-line: #e2e8f0;
            --cw-paper: #f7f8fc;
            --cw-font: 'Montserrat', system-ui, sans-serif;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { min-height: 100%; }
        body {
            font-family: var(--cw-font);
            color: var(--cw-ink);
            background: #020617;
        }

        .cw-login {
            min-height: 100vh;
            min-height: 100dvh;
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
        }

        /* ── Marca / HTTPS SSL ── */
        .cw-login__brand {
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: clamp(2rem, 5vw, 3.75rem);
            color: #fff;
            overflow: hidden;
            isolation: isolate;
            background:
                radial-gradient(ellipse at 18% 12%, rgba(16,185,129,0.26), transparent 48%),
                radial-gradient(ellipse at 92% 78%, rgba(67,97,238,0.2), transparent 42%),
                linear-gradient(155deg, #000147 0%, #07074a 48%, #020617 100%);
        }

        .cw-login__brand::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.045) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.045) 1px, transparent 1px);
            background-size: 44px 44px;
            mask-image: linear-gradient(180deg, rgba(0,0,0,.5), transparent 80%);
            pointer-events: none;
            z-index: 0;
            animation: cwGrid 26s linear infinite;
        }

        .cw-login__orb {
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
        }
        .cw-login__orb--a {
            width: 380px; height: 380px;
            top: -100px; right: -70px;
            background: radial-gradient(circle, rgba(16,185,129,.2), transparent 68%);
            animation: cwFloat 11s ease-in-out infinite;
        }
        .cw-login__orb--b {
            width: 260px; height: 260px;
            bottom: 6%; left: -50px;
            background: radial-gradient(circle, rgba(99,102,241,.16), transparent 70%);
            animation: cwFloat 15s ease-in-out infinite reverse;
        }

        @keyframes cwGrid {
            from { transform: translate(0,0); }
            to { transform: translate(44px,44px); }
        }
        @keyframes cwFloat {
            0%,100% { transform: translateY(0); }
            50% { transform: translateY(14px); }
        }
        @keyframes cwRise {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .cw-login__brand-inner {
            position: relative;
            z-index: 1;
            max-width: 460px;
            width: 100%;
            text-align: center;
            animation: cwRise .65s ease both;
        }

        .cw-login__ssl {
            display: block;
            width: min(340px, 88%);
            height: auto;
            margin: 0 auto 1.5rem;
            filter: drop-shadow(0 14px 28px rgba(0,0,0,.35));
        }

        .cw-login__eyebrow {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .4rem;
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: var(--cw-mint);
            margin-bottom: .8rem;
        }

        .cw-login__headline {
            font-size: clamp(1.55rem, 2.8vw, 2.15rem);
            font-weight: 800;
            line-height: 1.18;
            letter-spacing: -.03em;
            margin-bottom: .75rem;
        }

        .cw-login__lede {
            font-size: .95rem;
            line-height: 1.65;
            color: rgba(255,255,255,.72);
            font-weight: 500;
            max-width: 36ch;
            margin: 0 auto;
        }

        .cw-login__meta {
            margin-top: 1.5rem;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: .55rem;
        }

        .cw-login__chip {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .4rem .75rem;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,.12);
            background: rgba(255,255,255,.06);
            font-size: .75rem;
            font-weight: 600;
            color: rgba(255,255,255,.88);
            backdrop-filter: blur(8px);
        }
        .cw-login__chip i { color: var(--cw-mint); font-size: .85rem; }

        .cw-login__ssl-note {
            margin-top: 1.35rem;
            font-size: .82rem;
            font-weight: 600;
            color: rgba(255,255,255,.78);
            line-height: 1.45;
        }
        .cw-login__ssl-note strong {
            color: var(--cw-mint);
            font-weight: 800;
        }

        /* ── Formulario ── */
        .cw-login__form-wrap {
            background: var(--cw-paper);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(1.5rem, 4vw, 3rem);
            position: relative;
        }

        .cw-login__form-wrap::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 100% 0%, rgba(16,185,129,.07), transparent 42%),
                radial-gradient(circle at 0% 100%, rgba(0,1,71,.05), transparent 40%);
            pointer-events: none;
        }

        .cw-login__form {
            width: 100%;
            max-width: 390px;
            position: relative;
            z-index: 1;
            animation: cwRise .6s .08s ease both;
            text-align: left;
        }

        .cw-login__logo {
            display: block;
            width: min(180px, 58vw);
            height: auto;
            margin: 0 auto 1.35rem;
        }

        .cw-login__form-kicker {
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--cw-muted);
            margin-bottom: .45rem;
            text-align: center;
        }

        .cw-login__form-title {
            font-size: 1.55rem;
            font-weight: 800;
            letter-spacing: -.03em;
            color: var(--cw-navy);
            margin-bottom: .35rem;
            text-align: center;
        }

        .cw-login__form-sub {
            font-size: .9rem;
            color: var(--cw-muted);
            font-weight: 500;
            margin-bottom: 1.55rem;
            line-height: 1.5;
            text-align: center;
        }

        .cw-field { margin-bottom: .95rem; }

        .cw-field label {
            display: block;
            font-size: .76rem;
            font-weight: 700;
            color: #334155;
            margin-bottom: .38rem;
        }

        .cw-field__box {
            position: relative;
            display: flex;
            align-items: center;
        }

        .cw-field__box i {
            position: absolute;
            left: .95rem;
            color: #94a3b8;
            font-size: .98rem;
            pointer-events: none;
            transition: color .2s ease;
        }
        .cw-field__box:focus-within i { color: var(--cw-navy); }

        .cw-input {
            width: 100%;
            padding: .9rem 1rem .9rem 2.7rem;
            border: 1.5px solid var(--cw-line);
            border-radius: 12px;
            background: #fff;
            font-family: var(--cw-font);
            font-size: .94rem;
            font-weight: 500;
            color: var(--cw-ink);
            transition: border-color .2s ease, box-shadow .2s ease;
        }
        .cw-input:focus {
            outline: none;
            border-color: var(--cw-navy);
            box-shadow: 0 0 0 4px rgba(0,1,71,.08);
        }
        .cw-input::placeholder { color: #94a3b8; }

        .cw-toggle-pass {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            margin-top: .5rem;
            font-size: .8rem;
            font-weight: 600;
            color: var(--cw-muted);
            cursor: pointer;
            user-select: none;
        }
        .cw-toggle-pass input {
            accent-color: var(--cw-navy);
            width: 14px;
            height: 14px;
            cursor: pointer;
        }

        .g-recaptcha {
            margin: 1rem 0 .3rem;
            display: flex;
            justify-content: flex-start;
        }

        .cw-submit {
            margin-top: 1.05rem;
            width: 100%;
            border: 0;
            border-radius: 12px;
            padding: .95rem 1.2rem;
            background: linear-gradient(135deg, var(--cw-navy) 0%, var(--cw-navy-mid) 100%);
            color: #fff;
            font-family: var(--cw-font);
            font-size: .95rem;
            font-weight: 700;
            letter-spacing: .02em;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            transition: transform .2s ease, box-shadow .2s ease;
            box-shadow: 0 10px 26px rgba(0,1,71,.2);
        }
        .cw-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 32px rgba(0,1,71,.28);
        }
        .cw-submit:active { transform: translateY(0); }
        .cw-submit:disabled,
        .cw-submit.is-loading {
            cursor: wait;
            pointer-events: none;
            opacity: .92;
            transform: none;
            box-shadow: 0 10px 24px rgba(0,1,71,.2);
        }
        .cw-submit__spinner {
            width: 1.05rem;
            height: 1.05rem;
            border: 2.5px solid rgba(255,255,255,.28);
            border-top-color: #fff;
            border-radius: 50%;
            animation: cw-spin .7s linear infinite;
            flex-shrink: 0;
        }
        @keyframes cw-spin { to { transform: rotate(360deg); } }

        /* Overlay loading pantalla completa */
        .cw-login-loading {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background:
                radial-gradient(ellipse 70% 55% at 50% 40%, rgba(11,11,92,.55), transparent 70%),
                rgba(2, 6, 23, .72);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        .cw-login-loading.is-on { display: flex; }
        .cw-login-loading__card {
            width: min(320px, 92vw);
            text-align: center;
            color: #fff;
            padding: 1.75rem 1.4rem 1.5rem;
            border-radius: 20px;
            background: linear-gradient(160deg, rgba(255,255,255,.12), rgba(255,255,255,.05));
            border: 1px solid rgba(255,255,255,.16);
            box-shadow: 0 24px 50px rgba(0,0,0,.35);
        }
        .cw-login-loading__ring {
            width: 52px;
            height: 52px;
            margin: 0 auto 1.1rem;
            border-radius: 50%;
            border: 3px solid rgba(255,255,255,.18);
            border-top-color: #34d399;
            animation: cw-spin .85s linear infinite;
        }
        .cw-login-loading__title {
            font-size: 1.05rem;
            font-weight: 800;
            letter-spacing: -.02em;
            margin: 0 0 .35rem;
        }
        .cw-login-loading__text {
            margin: 0;
            font-size: .86rem;
            font-weight: 500;
            color: rgba(226,232,240,.88);
            line-height: 1.45;
        }
        body.cw-login-busy {
            overflow: hidden;
            pointer-events: none;
        }
        body.cw-login-busy .cw-login-loading { pointer-events: auto; }

        @media (prefers-reduced-motion: reduce) {
            .cw-submit__spinner,
            .cw-login-loading__ring { animation: none; border-top-color: #fff; }
        }

        .cw-links {
            margin-top: 1.2rem;
            display: flex;
            flex-direction: column;
            gap: .7rem;
            align-items: center;
            text-align: center;
        }
        .cw-links a {
            color: var(--cw-navy);
            font-weight: 700;
            font-size: .84rem;
            text-decoration: none;
        }
        .cw-links a:hover { color: var(--cw-mint); }
        .cw-links__muted {
            font-size: .82rem;
            font-weight: 500;
            color: var(--cw-muted);
        }
        .cw-links__muted a {
            font-weight: 700;
            color: var(--cw-navy);
        }

        @media (max-width: 900px) {
            .cw-login { grid-template-columns: 1fr; }
            .cw-login__brand {
                min-height: auto;
                padding: 1.85rem 1.35rem 1.6rem;
            }
            .cw-login__brand-inner { max-width: none; }
            .cw-login__ssl { width: min(240px, 72%); margin-bottom: .9rem; }
            .cw-login__headline { font-size: 1.35rem; }
            .cw-login__lede { max-width: none; font-size: .88rem; }
            .cw-login__logo { margin: 0 auto 1.2rem; }
            .cw-login__form-wrap {
                padding: 1.6rem 1.2rem 2.25rem;
                align-items: flex-start;
            }
        }

        @media (max-width: 380px) {
            .g-recaptcha { transform: scale(.9); transform-origin: left top; }
        }

        @media (prefers-reduced-motion: reduce) {
            .cw-login__brand::before,
            .cw-login__orb,
            .cw-login__brand-inner,
            .cw-login__form { animation: none; }
        }

        /* SweetAlert — estilo ConlineWeb */
        .swal2-container { font-family: var(--cw-font); }
        .cw-swal.swal2-popup {
            border-radius: 18px;
            padding: 1.75rem 1.5rem 1.4rem;
            box-shadow: 0 24px 60px rgba(2, 6, 23, .28);
            border: 1px solid rgba(226, 232, 240, .9);
            width: min(420px, 92vw);
        }
        .cw-swal .swal2-title {
            font-size: 1.2rem;
            font-weight: 800;
            letter-spacing: -.02em;
            color: var(--cw-navy);
            margin: 0 0 .45rem;
        }
        .cw-swal .swal2-html-container {
            font-size: .92rem;
            font-weight: 500;
            color: var(--cw-muted);
            line-height: 1.55;
            margin: 0;
        }
        .cw-swal .swal2-icon {
            margin: .15rem auto .85rem;
            border-width: 3px;
            width: 3.4rem;
            height: 3.4rem;
        }
        .cw-swal .swal2-icon.swal2-success { border-color: #a7f3d0; color: var(--cw-mint); }
        .cw-swal .swal2-icon.swal2-success [class^=swal2-success-line] { background-color: var(--cw-mint); }
        .cw-swal .swal2-icon.swal2-success .swal2-success-ring { border-color: rgba(16,185,129,.28); }
        .cw-swal .swal2-icon.swal2-error { border-color: #fecaca; color: #dc2626; }
        .cw-swal .swal2-icon.swal2-error .swal2-x-mark-line-left,
        .cw-swal .swal2-icon.swal2-error .swal2-x-mark-line-right { background-color: #dc2626; }
        .cw-swal .swal2-icon.swal2-warning { border-color: #fde68a; color: #d97706; }
        .cw-swal .swal2-actions { margin-top: 1.25rem; }
        .cw-swal .swal2-confirm {
            background: linear-gradient(135deg, #000147 0%, #0b0b5c 100%) !important;
            border: 0 !important;
            border-radius: 12px !important;
            padding: .75rem 1.4rem !important;
            font-family: var(--cw-font) !important;
            font-weight: 700 !important;
            font-size: .9rem !important;
            box-shadow: 0 10px 22px rgba(0,1,71,.22) !important;
        }
        .cw-swal .swal2-confirm:hover { filter: brightness(1.06); }
        .cw-swal .swal2-timer-progress-bar { background: rgba(0,1,71,.35); }
        .swal2-backdrop-show { background: rgba(2, 6, 23, .52) !important; }
    </style>
</head>
<body>
    <main class="cw-login">
        <section class="cw-login__brand" aria-label="Seguridad HTTPS SSL">
            <span class="cw-login__orb cw-login__orb--a" aria-hidden="true"></span>
            <span class="cw-login__orb cw-login__orb--b" aria-hidden="true"></span>
            <div class="cw-login__brand-inner">
                <img class="cw-login__ssl" src="images/4__1_.png" alt="HTTPS SSL seguro">
                <p class="cw-login__eyebrow"><i class="bi bi-shield-check" aria-hidden="true"></i> Área de clientes</p>
                <h1 class="cw-login__headline">Tu infraestructura, bajo control.</h1>
                <p class="cw-login__lede">Consulta hosting, dominios, pagos y soporte en un solo lugar.</p>
                <div class="cw-login__meta">
                    <span class="cw-login__chip"><i class="bi bi-shield-lock-fill" aria-hidden="true"></i> Acceso seguro</span>
                    <span class="cw-login__chip"><i class="bi bi-headset" aria-hidden="true"></i> Soporte en vivo</span>
                </div>
                <p class="cw-login__ssl-note"><strong>HTTPS / SSL</strong> para todos tus servicios</p>
            </div>
        </section>

        <section class="cw-login__form-wrap">
            <div class="cw-login__form">
                <img class="cw-login__logo" src="images/logo-conline.png" alt="ConlineWeb">
                <p class="cw-login__form-kicker">Bienvenido</p>
                <h2 class="cw-login__form-title">Iniciar sesión</h2>
                <p class="cw-login__form-sub">Escribe tu correo y contraseña para entrar.</p>

                <form method="post" id="loginForm" autocomplete="on">
                    <div class="cw-field">
                        <label for="usr">Correo o usuario</label>
                        <div class="cw-field__box">
                            <i class="bi bi-person-fill" aria-hidden="true"></i>
                            <input type="text" name="txusuario" id="usr" class="cw-input" placeholder="Ej. tu@correo.com" required autocomplete="username">
                        </div>
                    </div>

                    <div class="cw-field">
                        <label for="myInput">Contraseña</label>
                        <div class="cw-field__box">
                            <i class="bi bi-lock-fill" aria-hidden="true"></i>
                            <input type="password" name="txpassword" id="myInput" class="cw-input" placeholder="Tu contraseña" required autocomplete="current-password">
                        </div>
                        <label class="cw-toggle-pass">
                            <input type="checkbox" id="cwShowPass"> Mostrar contraseña
                        </label>
                    </div>

                    <?php if ($captchaEnabled): ?>
                    <div class="g-recaptcha" data-sitekey="6LfPDTQmAAAAALvmYsR12ZcGgcvRmK3eLTcKfj9l"></div>
                    <?php endif; ?>

                    <button type="button" id="enviar_btn" class="cw-submit">
                        <span class="cw-submit__label">
                            <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
                            Entrar
                        </span>
                    </button>
                </form>

                <div class="cw-links">
                    <a href="reset_request.php">¿Olvidaste tu contraseña?</a>
                    <p class="cw-links__muted">
                        ¿Aún no eres cliente?
                        <a href="https://www.conlineweb.com/" target="_blank" rel="noopener noreferrer">Conoce nuestros servicios</a>
                    </p>
                </div>
            </div>
        </section>
    </main>

    <div id="cwLoginLoading" class="cw-login-loading" hidden aria-live="assertive" aria-busy="false">
        <div class="cw-login-loading__card" role="status">
            <div class="cw-login-loading__ring" aria-hidden="true"></div>
            <p class="cw-login-loading__title">Iniciando sesión</p>
            <p class="cw-login-loading__text">Un momento, estamos verificando tu acceso…</p>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const captchaEnabled = <?= $captchaEnabled ? 'true' : 'false' ?>;
        const allowLocalReturnUrl = <?= $clienteAllowLocalReturnUrl ? 'true' : 'false' ?>;
        const loginEnvMismatch = <?= $loginEnvMismatch ? 'true' : 'false' ?>;
        const localLoginUrl = <?= json_encode($localLoginUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

        function cwAlert(opts) {
            return Swal.fire(Object.assign({
                customClass: {
                    popup: 'cw-swal',
                    confirmButton: 'cw-swal-btn'
                },
                buttonsStyling: true,
                confirmButtonText: 'Entendido'
            }, opts));
        }

        var loginInFlight = false;
        var $btn = $('#enviar_btn');
        var $loading = $('#cwLoginLoading');
        var btnDefaultHtml = $btn.html();

        function setLoginLoading(on, message) {
            loginInFlight = !!on;
            $btn.prop('disabled', on).toggleClass('is-loading', on);
            $('#usr, #myInput, #cwShowPass').prop('disabled', on);
            if (on) {
                $btn.html(
                    '<span class="cw-submit__spinner" aria-hidden="true"></span>' +
                    '<span class="cw-submit__label">Verificando…</span>'
                );
                if (message) {
                    $loading.find('.cw-login-loading__text').text(message);
                }
                $loading.attr('hidden', false).attr('aria-busy', 'true').addClass('is-on');
                $('body').addClass('cw-login-busy');
            } else {
                $btn.html(btnDefaultHtml);
                $loading.attr('hidden', true).attr('aria-busy', 'false').removeClass('is-on');
                $('body').removeClass('cw-login-busy');
            }
        }

        $('#cwShowPass').on('change', function () {
            $('#myInput').attr('type', this.checked ? 'text' : 'password');
        });

        function isAllowedReturnUrl(url) {
            try {
                var u = new URL(url, window.location.href);
                var host = (u.hostname || '').toLowerCase();
                if (allowLocalReturnUrl && (host === 'localhost' || host === '127.0.0.1' || host.endsWith('.local'))) {
                    return true;
                }
                return host === 'adm.conlineweb.com'
                    || host === 'cliente.conlineweb.com'
                    || host.endsWith('.conlineweb.com');
            } catch (e) {
                return false;
            }
        }

        function isAdminReturnUrl(url) {
            try {
                if (!url) return false;
                if (/adm\.conlineweb\.(com|local)/i.test(url) || /\/adm\.conlineweb\.com\//i.test(url)) {
                    return true;
                }
                var u = new URL(url, window.location.href);
                return (u.hostname || '').toLowerCase().indexOf('adm.') === 0;
            } catch (e) {
                return false;
            }
        }

        function extractLocalToken(data) {
            if (data && data.local_token) return String(data.local_token);
            if (!data || !data.redirect) return '';
            try {
                var parsed = new URL(data.redirect, window.location.origin);
                return parsed.searchParams.get('cw_local_token') || '';
            } catch (e) {
                var match = String(data.redirect).match(/[?&]cw_local_token=([^&]+)/);
                return match ? decodeURIComponent(match[1]) : '';
            }
        }

        function setQueryParam(url, key, value) {
            try {
                var u = new URL(url, window.location.href);
                u.searchParams.set(key, value);
                return u.toString();
            } catch (e) {
                return url;
            }
        }

        function resolvePostLoginRedirect(data) {
            var params = new URLSearchParams(window.location.search);
            var returnUrl = params.get('return_url') || '';
            var isStaff = Number(data.tipo) >= 1 && Number(data.tipo) <= 5;

            if (loginEnvMismatch && localLoginUrl) {
                return localLoginUrl;
            }

            if (!isStaff) {
                if (returnUrl && isAllowedReturnUrl(returnUrl) && !isAdminReturnUrl(returnUrl)) {
                    return returnUrl;
                }
                return data.redirect;
            }

            if (returnUrl && !allowLocalReturnUrl && (returnUrl.includes('localhost') || returnUrl.includes('127.0.0.1') || returnUrl.includes('conlineweb.local'))) {
                return data.redirect;
            }

            if (returnUrl && isAllowedReturnUrl(returnUrl) && isAdminReturnUrl(returnUrl)) {
                if (allowLocalReturnUrl) {
                    var token = extractLocalToken(data);
                    if (token) {
                        return setQueryParam(returnUrl, 'cw_local_token', token);
                    }
                    return data.redirect;
                }
                return returnUrl;
            }

            return data.redirect;
        }

        (function () {
            if (loginEnvMismatch && localLoginUrl) {
                cwAlert({
                    icon: 'info',
                    title: 'Login de producción detectado',
                    text: 'El panel admin que intentas abrir está en tu entorno local (MAMP). Inicia sesión en el login local para que la sesión se transfiera correctamente.',
                    confirmButtonText: 'Ir al login local',
                    showCancelButton: true,
                    cancelButtonText: 'Quedarme aquí'
                }).then(function (result) {
                    if (result.isConfirmed) {
                        window.location.href = localLoginUrl;
                    }
                });
                return;
            }
            var errorMsg = new URLSearchParams(window.location.search).get('error');
            if (errorMsg) {
                cwAlert({
                    icon: 'warning',
                    title: 'Inicia sesión para continuar',
                    text: decodeURIComponent(errorMsg)
                });
            }
        })();

        $('#enviar_btn').on('click', function (e) {
            e.preventDefault();
            if (loginInFlight) {
                return;
            }

            var user = $('#usr').val();
            var pass = $('#myInput').val();
            var captcha = captchaEnabled && typeof grecaptcha !== 'undefined' ? grecaptcha.getResponse() : '';

            if (!user || !pass) {
                cwAlert({
                    icon: 'warning',
                    title: 'Faltan datos',
                    text: 'Escribe tu correo o usuario y tu contraseña.'
                });
                return;
            }
            if (captchaEnabled && !captcha) {
                cwAlert({
                    icon: 'warning',
                    title: 'Confirma que no eres un robot',
                    text: 'Marca la casilla de verificación para continuar.'
                });
                return;
            }

            setLoginLoading(true, 'Un momento, estamos verificando tu acceso…');

            enviarLogin(user, pass, captcha);
        });

        function enviarLogin(user, pass, captcha) {
            var datos = {
                txusuario: user,
                txpassword: pass,
                'g-recaptcha-response': captcha
            };

            $.ajax({
                url: 'iniciarSesion.php',
                type: 'POST',
                dataType: 'json',
                timeout: 60000,
                data: datos,
                success: function (data) {
                    if (!data || data.status === 'error') {
                        setLoginLoading(false);
                        cwAlert({
                            icon: 'error',
                            title: 'No pudimos entrar',
                            text: (data && data.message) ? data.message : 'El correo/usuario o la contraseña no son correctos. Inténtalo de nuevo.'
                        });
                        if (captchaEnabled && typeof grecaptcha !== 'undefined') grecaptcha.reset();
                        return;
                    }
                    setLoginLoading(true, 'Listo. Redirigiendo a tu panel…');
                    window.location.href = resolvePostLoginRedirect(data);
                },
                error: function (xhr) {
                    setLoginLoading(false);
                    var msg = 'No pudimos conectar ahora. Espera un momento e inténtalo otra vez.';
                    try {
                        var parsed = xhr && xhr.responseJSON;
                        if (!parsed && xhr && xhr.responseText) {
                            parsed = JSON.parse(xhr.responseText);
                        }
                        if (parsed && parsed.message) {
                            msg = parsed.message;
                        } else if (xhr && xhr.status === 429) {
                            msg = 'Demasiados intentos. Espera unos minutos e inténtalo de nuevo.';
                        } else if (xhr && xhr.status >= 500) {
                            msg = 'El servidor no pudo procesar el login (error ' + xhr.status + ').';
                        } else if (xhr && xhr.statusText === 'timeout') {
                            msg = 'La verificación tardó demasiado. Inténtalo de nuevo.';
                        }
                    } catch (err) {}
                    cwAlert({
                        icon: 'error',
                        title: 'Sin conexión',
                        text: msg
                    });
                    if (captchaEnabled && typeof grecaptcha !== 'undefined') grecaptcha.reset();
                }
            });
        }

        $('#loginForm').on('submit', function (e) {
            e.preventDefault();
            if (!loginInFlight) {
                $('#enviar_btn').trigger('click');
            }
        });
    </script>
</body>
</html>
