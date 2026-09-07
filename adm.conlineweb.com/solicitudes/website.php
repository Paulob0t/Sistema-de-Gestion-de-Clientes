<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Sitio Web</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Estilos generales */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Encabezado */
        header {
            background: linear-gradient(135deg, #000000 0%, #2575fc 100%);
            color: white;
            padding: 20px 0;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 28px;
            font-weight: bold;
            display: flex;
            align-items: center;
        }
        
        .logo i {
            margin-right: 10px;
        }
        
        /* Navegación */
        nav ul {
            display: flex;
            list-style: none;
        }
        
        nav ul li {
            margin-left: 25px;
        }
        
        nav ul li a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
            padding: 5px 10px;
            border-radius: 4px;
        }
        
        nav ul li a:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        /* Hero Section */
        .hero {
            background: url('https://images.unsplash.com/photo-1518770660439-4636190af475?ixlib=rb-4.0.3&auto=format&fit=crop&w=1500&q=80') no-repeat center center/cover;
            height: 500px;
            display: flex;
            align-items: center;
            text-align: center;
            color: white;
            position: relative;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
        }
        
        .hero-content {
            position: relative;
            max-width: 800px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .hero h1 {
            font-size: 48px;
            margin-bottom: 20px;
        }
        
        .hero p {
            font-size: 20px;
            margin-bottom: 30px;
        }
        
        .btn {
            display: inline-block;
            background: #ff6b6b;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 50px;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: #ff5252;
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        /* Secciones */
        section {
            padding: 80px 0;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 50px;
        }
        
        .section-title h2 {
            font-size: 36px;
            color: #333;
            margin-bottom: 15px;
        }
        
        .section-title p {
            color: #777;
            font-size: 18px;
            max-width: 700px;
            margin: 0 auto;
        }
        
        /* Servicios */
        .services {
            background-color: white;
        }
        
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }
        
        .service-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            text-align: center;
            transition: transform 0.3s;
        }
        
        .service-card:hover {
            transform: translateY(-10px);
        }
        
        .service-card i {
            font-size: 48px;
            color: #6a11cb;
            margin-bottom: 20px;
        }
        
        .service-card h3 {
            margin-bottom: 15px;
            font-size: 22px;
        }
        
        /* Proyectos */
        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }
        
        .project-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .project-img {
            height: 200px;
            background-color: #6a11cb;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            font-weight: bold;
        }
        
        .project-content {
            padding: 20px;
        }
        
        .project-content h3 {
            margin-bottom: 10px;
        }
        
        /* Contacto */
        .contact {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
        }
        
        .contact .section-title h2 {
            color: white;
        }
        
        .contact-form {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .form-group textarea {
            min-height: 150px;
            resize: vertical;
        }
        
        /* Footer */
        footer {
            background: #333;
            color: white;
            padding: 40px 0;
            text-align: center;
        }
        
        .footer-content {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .social-icons {
            margin: 20px 0;
        }
        
        .social-icons a {
            color: white;
            margin: 0 10px;
            font-size: 20px;
            transition: color 0.3s;
        }
        
        .social-icons a:hover {
            color: #6a11cb;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
            }
            
            nav ul {
                margin-top: 20px;
                justify-content: center;
            }
            
            nav ul li {
                margin: 0 10px;
            }
            
            .hero h1 {
                font-size: 36px;
            }
            
            .hero p {
                font-size: 18px;
            }
        }
        
        /* Modo oscuro */
        .dark-mode {
            background-color: #1a1a1a;
            color: #f0f0f0;
        }
        
        .dark-mode .services,
        .dark-mode .service-card,
        .dark-mode .project-card,
        .dark-mode .contact-form {
            background-color: #2a2a2a;
            color: #f0f0f0;
        }
        
        .dark-mode .form-group label {
            color: #f0f0f0;
        }
        
        .dark-mode .form-group input,
        .dark-mode .form-group textarea {
            background-color: #333;
            border-color: #444;
            color: #f0f0f0;
        }
        
        .dark-mode-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #333;
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            z-index: 1000;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container header-content">
            <div class="logo">
                <i class="fas fa-code"></i>
                <span>MiSitioWeb</span>
            </div>
            <nav>
                <ul>
                    <li><a href="#inicio">Inicio</a></li>
                    <li><a href="#servicios">Servicios</a></li>
                    <li><a href="#proyectos">Proyectos</a></li>
                    <li><a href="#contacto">Contacto</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero" id="inicio">
        <div class="hero-content">
            <h1>Soluciones Digitales para tu Negocio</h1>
            <p>Desarrollamos experiencias web excepcionales que impulsan tu negocio hacia el éxito.</p>
            <a href="#contacto" class="btn">Contáctanos</a>
        </div>
    </section>

    <!-- Servicios -->
    <section class="services" id="servicios">
        <div class="container">
            <div class="section-title">
                <h2>Nuestros Servicios</h2>
                <p>Ofrecemos soluciones completas para tu presencia digital</p>
            </div>
            <div class="services-grid">
                <div class="service-card">
                    <i class="fas fa-laptop-code"></i>
                    <h3>Desarrollo Web</h3>
                    <p>Creación de sitios web modernos, responsivos y optimizados para todos los dispositivos.</p>
                </div>
                <div class="service-card">
                    <i class="fas fa-mobile-alt"></i>
                    <h3>Aplicaciones Móviles</h3>
                    <p>Desarrollo de aplicaciones nativas e híbridas para iOS y Android.</p>
                </div>
                <div class="service-card">
                    <i class="fas fa-chart-line"></i>
                    <h3>Marketing Digital</h3>
                    <p>Estrategias de marketing online para aumentar tu presencia y conversiones.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Proyectos -->
    <section class="projects" id="proyectos">
        <div class="container">
            <div class="section-title">
                <h2>Proyectos Destacados</h2>
                <p>Algunos de nuestros trabajos más recientes</p>
            </div>
            <div class="projects-grid">
                <div class="project-card">
                    <div class="project-img">E-commerce</div>
                    <div class="project-content">
                        <h3>Tienda Online</h3>
                        <p>Desarrollo de una tienda online completa con carrito de compras y pasarela de pagos.</p>
                    </div>
                </div>
                <div class="project-card">
                    <div class="project-img">App Móvil</div>
                    <div class="project-content">
                        <h3>App de Delivery</h3>
                        <p>Aplicación móvil para servicio de delivery con seguimiento en tiempo real.</p>
                    </div>
                </div>
                <div class="project-card">
                    <div class="project-img">Portal Web</div>
                    <div class="project-content">
                        <h3>Portal Corporativo</h3>
                        <p>Portal web corporativo con panel de administración y gestión de contenidos.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contacto -->
    <section class="contact" id="contacto">
        <div class="container">
            <div class="section-title">
                <h2>Contacto</h2>
                <p>¿Tienes un proyecto en mente? ¡Contáctanos!</p>
            </div>
            <div class="contact-form">
                <form id="contactForm">
                    <div class="form-group">
                        <label for="name">Nombre</label>
                        <input type="text" id="name" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" required>
                    </div>
                    <div class="form-group">
                        <label for="message">Mensaje</label>
                        <textarea id="message" required></textarea>
                    </div>
                    <button type="submit" class="btn">Enviar Mensaje</button>
                </form>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container footer-content">
            <div class="logo">
                <i class="fas fa-code"></i>
                <span>MiSitioWeb</span>
            </div>
            <div class="social-icons">
                <a href="#"><i class="fab fa-facebook"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
                <a href="#"><i class="fab fa-linkedin"></i></a>
            </div>
            <p>&copy; 2023 MiSitioWeb. Todos los derechos reservados.</p>
        </div>
    </footer>

    <!-- Botón modo oscuro -->
    <div class="dark-mode-toggle" id="darkModeToggle">
        <i class="fas fa-moon"></i>
    </div>

    <script>
        // Funcionalidad del formulario
        document.getElementById('contactForm').addEventListener('submit', function(e) {
            e.preventDefault();
            alert('¡Gracias por tu mensaje! Te contactaremos pronto.');
            this.reset();
        });

        // Modo oscuro
        const darkModeToggle = document.getElementById('darkModeToggle');
        const body = document.body;
        
        // Comprobar preferencia del usuario
        if (localStorage.getItem('darkMode') === 'enabled') {
            body.classList.add('dark-mode');
            darkModeToggle.innerHTML = '<i class="fas fa-sun"></i>';
        }
        
        darkModeToggle.addEventListener('click', () => {
            body.classList.toggle('dark-mode');
            
            if (body.classList.contains('dark-mode')) {
                localStorage.setItem('darkMode', 'enabled');
                darkModeToggle.innerHTML = '<i class="fas fa-sun"></i>';
            } else {
                localStorage.setItem('darkMode', null);
                darkModeToggle.innerHTML = '<i class="fas fa-moon"></i>';
            }
        });

        // Navegación suave
        document.querySelectorAll('nav a').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                const targetElement = document.querySelector(targetId);
                
                window.scrollTo({
                    top: targetElement.offsetTop - 80,
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>