<?php
require_once 'conn.php';
require_once __DIR__ . '/includes/cliente_email_template.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// POST: actualizar contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';

    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres.']);
        exit;
    }

    // Buscar token válido
    $sql = "SELECT pr.id, pr.user_id, pr.expires_at, c.correo FROM password_resets pr JOIN clientes c ON c.id = pr.user_id WHERE pr.token = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Este enlace ya no es válido. Solicita uno nuevo.']);
        exit;
    }

    if (new DateTime($row['expires_at']) < new DateTime()) {
        echo json_encode(['success' => false, 'message' => 'Este enlace ya expiró. Solicita uno nuevo para continuar.']);
        exit;
    }

    // Actualizar contraseña (password_hash; deja de usar MD5)
    require_once dirname(__DIR__) . '/includes/cw_portal_security.php';
    $newHash = cw_portal_password_hash($password);

    // Obtener usuario en login por email
    $email = $row['correo'];
    $up = $conn->prepare("UPDATE login SET contrasena = ? WHERE usuario = ?");
    $up->bind_param('ss', $newHash, $email);
    $ok = $up->execute();
    $up->close();

    if ($ok) {
        // Send notification email
        require 'PHPMailer/src/Exception.php';
        require 'PHPMailer/src/PHPMailer.php';
        require 'PHPMailer/src/SMTP.php';

        $mail = new PHPMailer(true);
        try {
            $correoRemitente = 'servicios@conlineweb.com';
            $nombreRemitente = 'Conlineweb';

            $mail->isSMTP();
            $mail->SMTPAuth = true;
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;
            $mail->Host = 'smtp.gmail.com';
            $mail->Username = $correoRemitente;
            $mail->Password = "wcglkgcxfebsauqo";

            $mail->SMTPDebug = 0;

            $mail->setFrom($correoRemitente, $nombreRemitente);
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'Contraseña actualizada - ConlineWeb';
            $mail->Body = cliente_email_password_changed();
            $mail->CharSet = 'UTF-8';

            $mail->send();
        } catch (Exception $e) {
            // Log error but don't fail the process
            error_log('Error al enviar correo de cambio de contraseña: ' . $e->getMessage());
        }

        // Borrar tokens del usuario para evitar reuse
        $del = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
        $del->bind_param('i', $row['user_id']);
        $del->execute();
        $del->close();

        echo json_encode(['success' => true, 'message' => 'Listo. Ya puedes iniciar sesión con tu nueva contraseña.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No pudimos guardar la contraseña. Inténtalo de nuevo.']);
    }
    exit;
}

// GET: mostrar formulario si token válido
$token = $_GET['token'] ?? '';
$tokenValid = false;
$tokenEmail = '';
$tokenError = '';

if (!$token) {
    $tokenError = 'Este enlace no es válido. Pide uno nuevo desde “¿Olvidaste tu contraseña?”.';
} else {
    $sql = "SELECT pr.id, pr.user_id, pr.expires_at, c.correo FROM password_resets pr JOIN clientes c ON c.id = pr.user_id WHERE pr.token = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!$row || new DateTime($row['expires_at']) < new DateTime()) {
        $tokenError = 'Este enlace ya expiró o ya se usó. Pide uno nuevo para continuar.';
    } else {
        $tokenValid = true;
        $tokenEmail = (string) $row['correo'];
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#000147">
    <?php require_once __DIR__ . '/includes/cliente_head_meta.php'; ?>
    <title><?= htmlspecialchars(cliente_document_title('Restablecer contraseña'), ENT_QUOTES, 'UTF-8') ?></title>
    <?= cliente_favicon_markup() ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
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
            margin-bottom: 1.25rem;
            line-height: 1.5;
            text-align: center;
        }

        .cw-account {
            display: flex;
            align-items: center;
            gap: .65rem;
            padding: .75rem .9rem;
            margin-bottom: 1.2rem;
            border-radius: 12px;
            border: 1.5px solid var(--cw-line);
            background: #fff;
            font-size: .84rem;
            font-weight: 600;
            color: #334155;
        }
        .cw-account i { color: var(--cw-navy); font-size: 1rem; }
        .cw-account span { word-break: break-all; }

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

        .cw-field__box > i:first-child {
            position: absolute;
            left: .95rem;
            color: #94a3b8;
            font-size: .98rem;
            pointer-events: none;
            transition: color .2s ease;
        }
        .cw-field__box:focus-within > i:first-child { color: var(--cw-navy); }

        .cw-input {
            width: 100%;
            padding: .9rem 2.7rem .9rem 2.7rem;
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

        .cw-toggle-eye {
            position: absolute;
            right: .85rem;
            border: 0;
            background: transparent;
            color: #94a3b8;
            cursor: pointer;
            padding: .2rem;
            display: inline-flex;
            font-size: 1.05rem;
            line-height: 1;
        }
        .cw-toggle-eye:hover { color: var(--cw-navy); }

        .cw-hint {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            margin-top: .45rem;
            font-size: .78rem;
            font-weight: 600;
            color: var(--cw-muted);
        }
        .cw-hint i { color: var(--cw-navy); }

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
            text-decoration: none;
        }
        .cw-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 32px rgba(0,1,71,.28);
            color: #fff;
        }
        .cw-submit:active { transform: translateY(0); }

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
            display: inline-flex;
            align-items: center;
            gap: .35rem;
        }
        .cw-links a:hover { color: var(--cw-mint); }

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

        @media (prefers-reduced-motion: reduce) {
            .cw-login__brand::before,
            .cw-login__orb,
            .cw-login__brand-inner,
            .cw-login__form { animation: none; }
        }

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

                <?php if ($tokenValid): ?>
                    <p class="cw-login__form-kicker">Casi listo</p>
                    <h2 class="cw-login__form-title">Crea tu nueva contraseña</h2>
                    <p class="cw-login__form-sub">Elige una contraseña fácil de recordar y confírmala abajo.</p>

                    <div class="cw-account">
                        <i class="bi bi-person-badge-fill" aria-hidden="true"></i>
                        <span><?= htmlspecialchars($tokenEmail, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>

                    <form id="resetForm" autocomplete="on">
                        <div class="cw-field">
                            <label for="password">Nueva contraseña</label>
                            <div class="cw-field__box">
                                <i class="bi bi-lock-fill" aria-hidden="true"></i>
                                <input type="password" id="password" name="password" class="cw-input" placeholder="Escribe tu nueva contraseña" required autocomplete="new-password">
                                <button type="button" class="cw-toggle-eye" data-target="password" aria-label="Mostrar u ocultar contraseña">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                            <p class="cw-hint"><i class="bi bi-info-circle" aria-hidden="true"></i> Usa al menos 6 caracteres</p>
                        </div>

                        <div class="cw-field">
                            <label for="password2">Confirmar contraseña</label>
                            <div class="cw-field__box">
                                <i class="bi bi-lock-fill" aria-hidden="true"></i>
                                <input type="password" id="password2" name="password2" class="cw-input" placeholder="Escríbela otra vez" required autocomplete="new-password">
                                <button type="button" class="cw-toggle-eye" data-target="password2" aria-label="Mostrar u ocultar contraseña">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>

                        <input type="hidden" id="token" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

                        <button type="submit" class="cw-submit" id="cwResetSubmit">
                            <i class="bi bi-shield-check" aria-hidden="true"></i>
                            Guardar contraseña
                        </button>
                    </form>
                <?php else: ?>
                    <p class="cw-login__form-kicker">Enlace vencido</p>
                    <h2 class="cw-login__form-title">Este enlace ya no sirve</h2>
                    <p class="cw-login__form-sub"><?= htmlspecialchars($tokenError, ENT_QUOTES, 'UTF-8') ?></p>
                    <a class="cw-submit" href="reset_request.php">
                        <i class="bi bi-envelope-fill" aria-hidden="true"></i>
                        Pedir un enlace nuevo
                    </a>
                <?php endif; ?>

                <div class="cw-links">
                    <a href="ingreso.php"><i class="bi bi-arrow-left" aria-hidden="true"></i> Volver a iniciar sesión</a>
                </div>
            </div>
        </section>
    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function cwAlert(opts) {
            return Swal.fire(Object.assign({
                customClass: {
                    popup: 'cw-swal',
                    confirmButton: 'cw-swal-btn'
                },
                buttonsStyling: true,
                confirmButtonText: 'Aceptar',
                confirmButtonColor: '#000147',
                focusConfirm: true
            }, opts));
        }

        $('.cw-toggle-eye').on('click', function () {
            var targetId = $(this).data('target');
            var $input = $('#' + targetId);
            var $icon = $(this).find('i');
            var show = $input.attr('type') === 'password';
            $input.attr('type', show ? 'text' : 'password');
            $icon.toggleClass('bi-eye bi-eye-slash');
        });

        <?php if ($tokenValid): ?>
        $('#resetForm').on('submit', function (e) {
            e.preventDefault();
            var p1 = $('#password').val();
            var p2 = $('#password2').val();
            var token = $('#token').val();
            var $btn = $('#cwResetSubmit').prop('disabled', true);

            if (p1.length < 6) {
                $btn.prop('disabled', false);
                cwAlert({
                    icon: 'warning',
                    title: 'Contraseña muy corta',
                    text: 'Escribe al menos 6 caracteres.'
                });
                return;
            }

            if (p1 !== p2) {
                $btn.prop('disabled', false);
                cwAlert({
                    icon: 'warning',
                    title: 'No coinciden',
                    text: 'Las dos contraseñas deben ser iguales.'
                });
                return;
            }

            $.post('reset_password.php', { password: p1, token: token }, function (resp) {
                if (resp.success) {
                    cwAlert({
                        icon: 'success',
                        title: 'Contraseña guardada',
                        text: resp.message || 'Listo. Ya puedes iniciar sesión con tu nueva contraseña.',
                        timer: 3600,
                        timerProgressBar: true
                    }).then(function () {
                        window.location = 'ingreso.php';
                    });
                } else {
                    cwAlert({
                        icon: 'error',
                        title: 'No se pudo guardar',
                        text: resp.message || 'Inténtalo de nuevo o pide un enlace nuevo.'
                    });
                }
            }, 'json').fail(function () {
                cwAlert({
                    icon: 'error',
                    title: 'Sin conexión',
                    text: 'No pudimos guardar ahora. Espera un momento e inténtalo otra vez.'
                });
            }).always(function () {
                $btn.prop('disabled', false);
            });
        });
        <?php endif; ?>
    </script>
</body>
</html>
