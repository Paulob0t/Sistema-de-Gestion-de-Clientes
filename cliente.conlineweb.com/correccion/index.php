<?php
session_start();
if(isset($_SESSION['id'])!=null && isset($_SESSION['login'])==true){
    include 'conn.php';
    $usrid = $_SESSION['uid'];
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Web - Área Cliente</title>
    <link rel="shortcut icon" href="https://c-onlineweb.com/imagenes/c-online_isotipo.png">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
    
    <style>
        :root {
            --primary-color: #000147;
            --secondary-color: #10b981;
            --dark-color: #1e293b;
            --light-color: #f8fafc;
            --gray-light: #e2e8f0;
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #f5f7fa;
            color: #334155;
            line-height: 1.6;
        }
        
        /* Ajuste del contenido principal para el menú lateral */
        #main {
            padding: 20px;
            margin-left: 300px;
            transition: all 0.3s;
        }
        
        /* CABECERA CON BORDE REDONDEADO */
        .header-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, #1a1a6e 100%);
            padding: 2rem;
            margin: 1rem 0 2rem 0;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .header-content {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        
        .header-icon-container {
            background: rgba(255,255,255,0.1);
            padding: 1rem;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .header-icon {
            color: var(--secondary-color);
            font-size: 2.5rem;
        }
        
        .header-text {
            flex: 1;
        }
        
        .header-title {
            color: white;
            font-weight: 600;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        
        .header-subtitle {
            color: rgba(255,255,255,0.85);
            font-weight: 400;
            font-size: 1.05rem;
            margin: 0;
        }
        
        /* TARJETAS DE DOMINIO */
        .card-domain {
            background: white;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
            border: 1px solid var(--gray-light);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .card-domain:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
        }
        
        .domain-title {
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--dark-color);
        }
        
        .domain-status {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
        }
        
        /* BOTONES */
        .boton_admin {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.6rem 1rem;
            font-weight: 500;
            font-size: 0.875rem;
            color: var(--dark-color);
            background-color: white;
            border-radius: 8px;
            border: 1px solid var(--gray-light);
            transition: all 0.2s ease;
            width: 100%;
            text-decoration: none !important;
        }
        
        .boton_admin:hover {
            background-color: #f1f5f9;
            color: var(--primary-color);
            border-color: #cbd5e1;
        }
        
        .boton_admin i {
            margin-right: 0.5rem;
            font-size: 1rem;
        }
        
        /* SECCIÓN INFORMATIVA */
        .info-section {
            background-color: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 2rem;
            border: 1px solid var(--gray-light);
        }
        
        .info-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 1rem;
        }
        
        /* ESTADOS */
        .status-active {
            background-color: #dcfce7;
            color: #166534;
        }
        
        .status-inactive {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        /* RESPONSIVE */
        @media (max-width: 992px) {
            #main {
                margin-left: 0;
            }
            
            .header-section {
                padding: 1.5rem;
                margin: 1rem 0;
            }
            
            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .header-icon-container {
                padding: 0.8rem;
            }
            
            .header-icon {
                font-size: 2rem;
            }
            
            .header-title {
                font-size: 1.5rem;
            }
            
            .header-subtitle {
                font-size: 0.95rem;
            }
        }
    </style>
</head>

<body>
    <div id="app">
        <!-- Incluir el menú lateral -->
        <?php include('menu.php'); ?>
        
        <!-- Contenido principal -->
        <div id="main">
            <header class="mb-3">
                <a href="#" class="burger-btn d-block d-xl-none">
                    <i class="bi bi-justify fs-3"></i>
                </a>
            </header>
            
            <!-- CABECERA CON ICONO A LA IZQUIERDA -->
            <div class="header-section">
                <div class="header-content">
                    <div class="header-icon-container">
                        <i class="bi bi-grid-3x3-gap-fill header-icon"></i>
                    </div>
                    <div class="header-text">
                        <h1 class="header-title">Panel de Sitios Web</h1>
                        <p class="header-subtitle">Administra todos tus sitios desde un único lugar</p>
                    </div>
                </div>
            </div>
             <div class="info-section">
                        <h3 class="info-title">
                            <i class="bi bi-info-circle me-2" style="color: var(--primary-color);"></i>
                            Información del Sitio
                        </h3>
                        <div class="info-content">
                            <p>Todos tus sitios web están alojados en nuestros servidores premium con certificado SSL incluido. Accede a los paneles de administración para gestionar tu contenido o configuración técnica.</p>
                            
                            <p class="mb-0"><strong>¿Necesitas ayuda?</strong> Consulta nuestra <a href="/soporte" style="color: var(--primary-color);">documentación</a> o abre un <a href="/tickets" style="color: var(--primary-color);">ticket de soporte</a>.</p>
                        </div>
                    </div>
            <div class="row">
                <div class="col-12">
                    <?php
                    $query = mysqli_query($conn, "SELECT * FROM dominios WHERE cliente_id = $usrid AND eliminado = 0 ORDER BY `dominios`.`id_dominio` DESC");
                    while($mostrar = mysqli_fetch_array($query)){
                    ?> 
                    <div class="card-domain p-4 mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-globe2 me-3" style="color: var(--primary-color); font-size: 1.5rem;"></i>
                                <div>
                                    <a href="https://<?php echo $mostrar['url_dominio']; ?>" target="_blank" rel="noopener" style="color: inherit; text-decoration: none;">
                                        <span class="domain-title"><?php echo $mostrar['url_dominio']; ?></span>
                                    </a>
                                </div>
                            </div>
                            <?php if ($mostrar['estado_dominio'] == 1): ?>
                                <span class="domain-status status-active">Activo <i class="bi bi-check-circle-fill ms-1"></i></span>
                            <?php else: ?>
                                <span class="domain-status status-inactive">Inactivo <i class="bi bi-exclamation-circle-fill ms-1"></i></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-3 col-6">
                                <button type="button" class="boton_admin" onclick="loginAdmin('<?php echo $mostrar['id_dominio']; ?>')">
                                    <i class="bi bi-gear"></i> Administrar
                                </button>
                            </div>
                            
                            <div class="col-md-3 col-6">
                                <button type="button" class="boton_admin" onclick="loginCpanel('<?php echo $mostrar['id_dominio']; ?>')">
                                    <i class="bi bi-terminal"></i> cPanel
                                </button>
                            </div>
                            
                            <div class="col-md-3 col-6">
                                <button type="button" class="boton_admin" onclick="window.open('https://<?php echo $mostrar['url_dominio'];?>', '_blank')">
                                    <i class="bi bi-eye"></i> Visitar Sitio
                                </button>
                            </div>
                            
                            <div class="col-md-3 col-6">
                                <button type="button" class="boton_admin" onclick="window.open('<?php echo $mostrar['url_plataforma'];?>', '_blank')">
                                    <i class="bi bi-layers"></i> Plataforma
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php } ?>
                    
                    <!-- Sección informativa -->
                   
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        var dominiosData = {};
        <?php
        $query = mysqli_query($conn, "SELECT * FROM dominios WHERE cliente_id = $usrid AND eliminado = 0 ORDER BY `dominios`.`id_dominio` DESC");
        while($mostrar = mysqli_fetch_array($query)) {
            $id = $mostrar['id_dominio'];
            $usuario = htmlspecialchars($mostrar['usuario'], ENT_QUOTES);
            $contrasena = htmlspecialchars($mostrar['contrasena'], ENT_QUOTES);
            $url_admin = htmlspecialchars($mostrar['url_admin'], ENT_QUOTES);
            $url_cpanel = htmlspecialchars($mostrar['url_cpanel'], ENT_QUOTES);
            echo "dominiosData['$id'] = {usuario: '$usuario', contrasena: '$contrasena', url_admin: '$url_admin', url_cpanel: '$url_cpanel'};\n";
        }
        ?>

        function loginAdmin(id) {
            var data = dominiosData[id];
            if (!data) return;
            
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = data.url_admin;
            form.target = '_blank';
            form.style.display = 'none';
            
            var inputUser = document.createElement('input');
            inputUser.name = 'log';
            inputUser.value = data.usuario;
            form.appendChild(inputUser);
            
            var inputPass = document.createElement('input');
            inputPass.name = 'pwd';
            inputPass.value = data.contrasena;
            form.appendChild(inputPass);
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        function loginCpanel(id) {
            var data = dominiosData[id];
            if (!data) return;
            
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = data.url_cpanel + '/login';
            form.target = '_blank';
            form.style.display = 'none';
            
            var inputUser = document.createElement('input');
            inputUser.name = 'user';
            inputUser.value = data.usuario;
            form.appendChild(inputUser);
            
            var inputPass = document.createElement('input');
            inputPass.name = 'pass';
            inputPass.value = data.contrasena;
            form.appendChild(inputPass);
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
        
        // Toggle sidebar en mobile
        document.querySelector('.burger-btn').addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('main').classList.toggle('active');
        });
    </script>
</body>

<?php include('footer.php'); ?>

</html>
<?php
} else {
    header("Location: ingreso.php");
}
?>