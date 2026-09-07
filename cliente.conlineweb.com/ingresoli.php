<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#000147">
    <title>Iniciar Sesión</title>
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://www.google.com/recaptcha/api.js"></script>
    <style>
        :root {
            --primary-color: #000147;
            --secondary-color: #10b981;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --gray-color: #6c757d;
            --border-radius: 12px;
            --box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: var(--dark-color);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .login-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 3rem;
            width: 100%;
            max-width: 450px;
            transition: var(--transition);
        }
        
        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }
        
        h1 {
            font-size: 2rem;
            margin-bottom: 1.5rem;
            color: var(--primary-color);
            text-align: center;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .form-control {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 1, 71, 0.1);
        }
        
        .show-password {
            display: flex;
            align-items: center;
            margin-top: 0.5rem;
            font-size: 0.9rem;
            color: var(--gray-color);
        }
        
        .show-password input {
            margin-right: 0.5rem;
        }
        
        .btn {
            display: inline-block;
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            width: 100%;
            text-align: center;
        }
        
        .btn:hover {
            background-color: #1a1a6e;
            transform: translateY(-2px);
        }
        
        .g-recaptcha {
            margin: 1.5rem 0;
            display: flex;
            justify-content: center;
        }
        
        @media (max-width: 768px) {
            .login-card {
                padding: 2rem;
            }
            
            h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-card">
        <h1>Iniciar sesión</h1>
        
        <!-- Mostrar mensaje de error si existe -->
        <script>
        const urlParams = new URLSearchParams(window.location.search);
        const errorMsg = urlParams.get('error');
        if (errorMsg) {
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'warning',
                    title: 'Acceso Requerido',
                    text: decodeURIComponent(errorMsg),
                    confirmButtonColor: '#000147'
                });
            });
        }
        </script>
        
        <form method="post" id="loginForm">
            <div class="form-group">
                <input type="text" name="txusuario" placeholder="Usuario" id="usr" class="form-control" required>
            </div>
            
            <div class="form-group">
                <input type="password" name="txpassword" placeholder="Contraseña" id="myInput" class="form-control" required>
                <label class="show-password">
                    <input type="checkbox" onclick="myFunction()"> Mostrar Contraseña
                </label>
            </div>
            
            <div class="g-recaptcha" data-sitekey="6LfPDTQmAAAAALvmYsR12ZcGgcvRmK3eLTcKfj9l"></div>
            
            <button type="button" id="enviar_btn" class="btn">Ingresar</button>
        </form>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Sonido al hacer clic
        var sonido = new Audio();
        sonido.src = "https://conlineweb.com/wp-content/uploads/2021/11/sonido.mp3";
        
        // Mostrar/ocultar contraseña
        function myFunction() {
            var x = document.getElementById("myInput");
            if (x.type === "password") {
                x.type = "text";
            } else {
                x.type = "password";
            }
        }
        
        // Validación y envío del formulario
        $("#enviar_btn").on('click', function(e){
            e.preventDefault();
            sonido.play();

            var user = $('#usr').val();
            var pass = $('#myInput').val();
            var captcha = grecaptcha.getResponse();

            if (!user || !pass) {
                Swal.fire('Error', 'Usuario y contraseña son requeridos', 'warning');
                return;
            }

            if (!captcha) {
                Swal.fire('Error', 'Por favor completa el CAPTCHA', 'warning');
                return;
            }

            enviarLoginLi(user, pass, captcha);
        });

        function enviarLoginLi(user, pass, captcha) {
            var datos = {
                txusuario: user,
                txpassword: pass,
                'g-recaptcha-response': captcha
            };

            $.ajax({
                url: "iniciarSesion.php",
                type: "POST",
                dataType: "json",
                data: datos,
                success: function(data) {
                    if (data.status === "error") {
                        Swal.fire('Error', 'Credenciales incorrectas', 'error');
                        grecaptcha.reset();
                    } else {
                        // Para agentes (tipo 3), admin (tipo 1) y empresas (tipo 4), siempre usar la redirección del sistema
                        if (data.tipo === 3 || data.tipo === 1 || data.tipo === 4) {
                            window.location.href = data.redirect;
                        } else {
                            // Solo para otros tipos, verificar si hay URL de retorno
                            const urlParams = new URLSearchParams(window.location.search);
                            const returnUrl = urlParams.get('return_url');
                            
                            // Si hay URL de retorno y es válida, usar esa; sino usar la del sistema
                            //if (returnUrl && returnUrl.includes('conlineweb.com')) {

                            if (returnUrl && returnUrl.includes('conlineweb.com/ingresoli.php')) {
                                window.location.href = returnUrl;
                            } else {
                                window.location.href = data.redirect;
                            }
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error(xhr.responseText);
                    Swal.fire('Error', 'Error de conexión: ' + error, 'error');
                }
            });
        }
    </script>
</body>
</html>