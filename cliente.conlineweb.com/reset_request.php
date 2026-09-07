<?php
// Formulario y endpoint para solicitar restablecimiento de contraseña
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

require_once 'conn.php';
require_once __DIR__ . '/includes/cliente_email_template.php';

// Si es POST, procesar la solicitud
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Ese correo no parece válido. Revisa que esté bien escrito.']);
        exit;
    }

    // Buscar usuario por correo en tabla login
    $sql = "SELECT id, usuario FROM login WHERE usuario = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    // Si no se encuentra el usuario, mostrar alerta de error
    if (!$user) {
        echo json_encode([
            'success' => false,
            'message' => 'No encontramos una cuenta con ese correo. Revisa el dato o contáctanos si crees que es un error.'
        ]);
        exit;
    }

    // Si el usuario existe, continuar con el proceso
    if ($user) {
        // Generar token seguro
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', time() + 3600); // 1 hora

        // Crear tabla password_resets si no existe (solo intento seguro, puede fallar si permisos restringidos)
        $create = "CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            user_email VARCHAR(255) DEFAULT '',
            token VARCHAR(128) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (token),
            INDEX (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $conn->query($create);

        // Insertar token (guardamos también el email tal como está en la tabla login)
        $ins = $conn->prepare("INSERT INTO password_resets (user_id, user_email, token, expires_at) VALUES (?, ?, ?, ?)");
        $ins->bind_param('isss', $user['id'], $user['usuario'], $token, $expires_at);
        $ins->execute();
        $ins->close();

        // Enviar correo con enlace
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
            // Usar la misma contraseña SMTP que otros scripts del proyecto (app password)
            $mail->Password = "wcglkgcxfebsauqo";

            $mail->SMTPDebug = 0;

            error_log('Reset password: sending to email=' . $email . ' user_id=' . $user['id'] . ' db_email=' . $user['usuario']);

            $mail->setFrom($correoRemitente, $nombreRemitente);
            // Validar correo destino y evitar addAddress si está vacío o inválido
            $dest = trim($user['usuario']);
            if (empty($dest) || !filter_var($dest, FILTER_VALIDATE_EMAIL)) {
                error_log('Reset password: correo destino inválido o vacío, abortando envío for user_id=' . $user['id'] . ' dest=' . $dest);
            } else {
                $mail->addAddress($dest);

                $mail->isHTML(true);
                $mail->Subject = 'Restablecer contraseña - ConlineWeb';

                $resetLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://')
                    . $_SERVER['HTTP_HOST']
                    . rtrim(dirname($_SERVER['PHP_SELF']), '/\\')
                    . '/reset_password.php?token='
                    . urlencode($token);

                $mail->Body = cliente_email_password_reset_link($resetLink);
                $mail->CharSet = 'UTF-8';

                $mail->send();
            }
        } catch (Exception $e) {
            error_log('Error al enviar correo de restablecimiento: ' . $e->getMessage());
            // No revelamos esto al usuario
        }
    }

    echo json_encode(['success' => true, 'message' => 'Te enviamos un enlace a tu correo. Ábrelo para crear una contraseña nueva.']);
    exit;
}

// Si es GET, mostrar formulario
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#000147">
    <?php require_once __DIR__ . '/includes/cliente_head_meta.php'; ?>
    <title><?= htmlspecialchars(cliente_document_title('Recuperar contraseña'), ENT_QUOTES, 'UTF-8') ?></title>
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
                <p class="cw-login__form-kicker">Ayuda de acceso</p>
                <h2 class="cw-login__form-title">¿Olvidaste tu contraseña?</h2>
                <p class="cw-login__form-sub">Escribe el correo de tu cuenta y te mandamos un enlace para cambiarla.</p>

                <form id="resetRequestForm" method="post" action="reset_request.php" autocomplete="on">
                    <div class="cw-field">
                        <label for="email">Correo de tu cuenta</label>
                        <div class="cw-field__box">
                            <i class="bi bi-envelope-fill" aria-hidden="true"></i>
                            <input type="email" name="email" id="email" class="cw-input" placeholder="Ej. tu@correo.com" required autocomplete="email">
                        </div>
                    </div>

                    <button type="submit" class="cw-submit" id="cwResetSubmit">
                        <i class="bi bi-send-fill" aria-hidden="true"></i>
                        Enviar enlace
                    </button>
                </form>

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

        $('#resetRequestForm').on('submit', function (e) {
            e.preventDefault();
            var email = $('#email').val();
            var $btn = $('#cwResetSubmit').prop('disabled', true);

            $.post('reset_request.php', { email: email }, function (resp) {
                if (resp.success) {
                    cwAlert({
                        icon: 'success',
                        title: 'Revisa tu correo',
                        text: resp.message,
                        timer: 4800,
                        timerProgressBar: true
                    }).then(function () {
                        window.location = 'ingreso.php';
                    });
                } else {
                    cwAlert({
                        icon: 'error',
                        title: 'No encontramos tu cuenta',
                        text: resp.message
                    });
                }
            }, 'json').fail(function () {
                cwAlert({
                    icon: 'error',
                    title: 'Algo salió mal',
                    text: 'No pudimos enviar el enlace ahora. Inténtalo de nuevo en un momento.'
                });
            }).always(function () {
                $btn.prop('disabled', false);
            });
        });
    </script>
</body>
</html>
