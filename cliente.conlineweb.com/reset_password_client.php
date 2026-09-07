<?php
require_once __DIR__ . '/includes/cliente_session.php';
cliente_start_session();
if (!cliente_is_logged_in()) {
    header('Location: ingreso.php');
    exit;
}

require_once 'conn.php';
require_once __DIR__ . '/includes/cliente_email_template.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

$usrid = (int) $_SESSION['uid'];

$sql = 'SELECT correo FROM clientes WHERE id = ? LIMIT 1';
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $usrid);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo 'Usuario no encontrado.';
    exit;
}

$email = $user['correo'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (strlen($new_password) < 6) {
        echo json_encode(['success' => false, 'message' => 'La nueva contraseña debe tener al menos 6 caracteres.']);
        exit;
    }

    if ($new_password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'Las contraseñas nuevas no coinciden.']);
        exit;
    }

    require_once dirname(__DIR__) . '/includes/cw_portal_security.php';

    $sql_check = 'SELECT contrasena FROM login WHERE usuario = ? LIMIT 1';
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param('s', $email);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $login_user = $result_check->fetch_assoc();
    $stmt_check->close();

    if (!$login_user || !cw_portal_password_verify($current_password, (string) ($login_user['contrasena'] ?? ''))) {
        echo json_encode(['success' => false, 'message' => 'La contraseña actual es incorrecta.']);
        exit;
    }

    $new_hash = cw_portal_password_hash($new_password);
    $up = $conn->prepare('UPDATE login SET contrasena = ? WHERE usuario = ?');
    $up->bind_param('ss', $new_hash, $email);
    $ok = $up->execute();
    $up->close();

    if ($ok) {
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
            $mail->Password = 'wcglkgcxfebsauqo';
            $mail->SMTPDebug = 0;

            $mail->setFrom($correoRemitente, $nombreRemitente);
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Contraseña actualizada - ConlineWeb';
            $mail->Body = cliente_email_password_changed();
            $mail->CharSet = 'UTF-8';

            $mail->send();
        } catch (Exception $e) {
            error_log('Error al enviar correo de cambio de contraseña: ' . $e->getMessage());
        }

        session_destroy();

        echo json_encode(['success' => true, 'message' => 'Contraseña actualizada correctamente. Se ha cerrado la sesión.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se pudo actualizar la contraseña.']);
    }
    exit;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#000147">
    <?php require_once __DIR__ . '/includes/cliente_head_meta.php'; ?>
    <title><?= htmlspecialchars(cliente_document_title('Cambiar contraseña'), ENT_QUOTES, 'UTF-8') ?></title>
    <?= cliente_favicon_markup() ?>
    <style>
        .pw-layout {
            width: 100%;
        }

        .pw-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.75rem;
            width: 100%;
            box-shadow: 0 2px 8px rgba(0, 1, 71, 0.04);
        }

        .pw-top {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .pw-account {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px 16px;
            height: 100%;
        }

        .pw-account__icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #eff6ff;
            color: #000147;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .pw-account__label {
            display: block;
            font-size: 0.75rem;
            color: #94a3b8;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .pw-account__email {
            display: block;
            font-size: 0.95rem;
            color: #1e293b;
            font-weight: 600;
            word-break: break-word;
        }

        .pw-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem 1.25rem;
        }

        .pw-field {
            margin-bottom: 0;
        }

        .pw-field--full {
            grid-column: 1 / -1;
        }

        .pw-label {
            display: block;
            font-size: 0.88rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 6px;
        }

        .pw-input-wrap {
            position: relative;
        }

        .pw-input-wrap input {
            width: 100%;
            padding: 12px 44px 12px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .pw-input-wrap input:focus {
            outline: none;
            border-color: #000147;
            box-shadow: 0 0 0 3px rgba(0, 1, 71, 0.1);
        }

        .pw-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            padding: 4px;
            font-size: 1rem;
        }

        .pw-toggle:hover { color: #000147; }

        .pw-hint {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.82rem;
            color: #64748b;
            padding: 10px 12px;
            background: #f8fafc;
            border-radius: 8px;
            grid-column: 1 / -1;
        }

        .pw-hint i { color: #10b981; }

        .pw-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e2e8f0;
        }

        .pw-btn {
            background: linear-gradient(135deg, #000147 0%, #1a1a6e 100%);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 14px 28px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .pw-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(0, 1, 71, 0.25);
        }

        .pw-links {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            flex-wrap: wrap;
        }

        .pw-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.88rem;
            color: #000147;
            text-decoration: none;
            font-weight: 500;
        }

        .pw-link:hover { color: #10b981; text-decoration: underline; }

        .pw-note {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            background: #fffbeb;
            border: 1px solid #fcd34d;
            border-left: 3px solid #f59e0b;
            border-radius: 10px;
            padding: 14px 16px;
            font-size: 0.88rem;
            color: #92400e;
            line-height: 1.45;
            height: 100%;
        }

        .pw-note i { flex-shrink: 0; margin-top: 2px; }

        @media (max-width: 992px) {
            .pw-top {
                grid-template-columns: 1fr;
            }

            .pw-form-grid {
                grid-template-columns: 1fr;
            }

            .pw-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .pw-btn {
                width: 100%;
                justify-content: center;
            }

            .pw-links {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 768px) {
            .pw-card { padding: 1.25rem; }
        }
    </style>
</head>
<body>
    <div id="app">
        <?php include 'menu.php'; ?>

        <div class="main-content container-fluid">
            <div class="header-section">
                <div class="header-content">
                    <div class="header-icon-container">
                        <i class="bi bi-key-fill header-icon"></i>
                    </div>
                    <div class="header-text">
                        <h1 class="header-title">Cambiar contraseña</h1>
                        <p class="header-subtitle">Actualiza la contraseña de acceso a tu Área Cliente</p>
                    </div>
                </div>
            </div>

            <div class="pw-layout">
                <div class="pw-card">
                    <div class="pw-top">
                        <div class="pw-note">
                            <i class="bi bi-shield-lock-fill"></i>
                            <span>Al guardar los cambios se cerrará tu sesión y deberás iniciar sesión con la nueva contraseña.</span>
                        </div>

                        <div class="pw-account">
                            <div class="pw-account__icon"><i class="bi bi-envelope-fill"></i></div>
                            <div>
                                <span class="pw-account__label">Cuenta</span>
                                <span class="pw-account__email"><?php echo htmlspecialchars($email); ?></span>
                            </div>
                        </div>
                    </div>

                    <form id="changeForm">
                        <div class="pw-form-grid">
                            <div class="pw-field pw-field--full">
                                <label class="pw-label" for="current_password">Contraseña actual</label>
                                <div class="pw-input-wrap">
                                    <input type="password" id="current_password" name="current_password" placeholder="Escribe tu contraseña actual" required autocomplete="current-password">
                                    <button type="button" class="pw-toggle" data-target="current_password" aria-label="Mostrar contraseña"><i class="bi bi-eye"></i></button>
                                </div>
                            </div>

                            <div class="pw-field">
                                <label class="pw-label" for="new_password">Nueva contraseña</label>
                                <div class="pw-input-wrap">
                                    <input type="password" id="new_password" name="new_password" placeholder="Escribe tu nueva contraseña" required autocomplete="new-password">
                                    <button type="button" class="pw-toggle" data-target="new_password" aria-label="Mostrar contraseña"><i class="bi bi-eye"></i></button>
                                </div>
                            </div>

                            <div class="pw-field">
                                <label class="pw-label" for="confirm_password">Confirmar nueva contraseña</label>
                                <div class="pw-input-wrap">
                                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Repite la nueva contraseña" required autocomplete="new-password">
                                    <button type="button" class="pw-toggle" data-target="confirm_password" aria-label="Mostrar contraseña"><i class="bi bi-eye"></i></button>
                                </div>
                            </div>

                            <div class="pw-hint">
                                <i class="bi bi-info-circle-fill"></i>
                                <span>Mínimo 6 caracteres</span>
                            </div>
                        </div>

                        <div class="pw-actions">
                            <button type="submit" class="pw-btn">
                                <i class="bi bi-check-circle-fill"></i> Guardar nueva contraseña
                            </button>
                            <div class="pw-links">
                                <a href="index.php" class="pw-link"><i class="bi bi-house-door-fill"></i> Volver al Centro de Ayuda</a>
                                <a href="reset_request.php" class="pw-link"><i class="bi bi-question-circle-fill"></i> ¿Olvidaste tu contraseña?</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('changeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            var current = document.getElementById('current_password').value;
            var newPass = document.getElementById('new_password').value;
            var confirmPass = document.getElementById('confirm_password').value;

            if (newPass.length < 6) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Contraseña muy corta',
                    text: 'La nueva contraseña debe tener al menos 6 caracteres',
                    confirmButtonColor: '#000147'
                });
                return;
            }

            if (newPass !== confirmPass) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Las contraseñas no coinciden',
                    text: 'Por favor, verifica que ambas contraseñas nuevas sean iguales',
                    confirmButtonColor: '#000147'
                });
                return;
            }

            var formData = new FormData();
            formData.append('current_password', current);
            formData.append('new_password', newPass);
            formData.append('confirm_password', confirmPass);

            fetch('reset_password_client.php', { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(resp) {
                    if (resp.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Contraseña actualizada!',
                            text: resp.message,
                            confirmButtonColor: '#000147',
                            timer: 3000,
                            timerProgressBar: true
                        }).then(function() {
                            window.location = 'ingreso.php';
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: resp.message,
                            confirmButtonColor: '#000147'
                        });
                    }
                })
                .catch(function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error en el servidor',
                        text: 'No se pudo procesar la solicitud. Intente nuevamente.',
                        confirmButtonColor: '#000147'
                    });
                });
        });

        document.querySelectorAll('.pw-toggle').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var input = document.getElementById(btn.getAttribute('data-target'));
                var icon = btn.querySelector('i');
                var isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                icon.classList.toggle('bi-eye', !isPassword);
                icon.classList.toggle('bi-eye-slash', isPassword);
            });
        });

        document.querySelector('.burger-btn')?.addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('sidebar')?.classList.toggle('active');
            document.getElementById('main')?.classList.toggle('active');
        });
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>
