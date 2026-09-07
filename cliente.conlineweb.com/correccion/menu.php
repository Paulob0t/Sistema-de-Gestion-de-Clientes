<?php
if(isset($_SESSION['id']) != null && isset($_SESSION['login']) == true) {
    include 'conn.php';
    $usrid = $_SESSION['uid'];
    
    // Consultar pagos pendientes
    $query_pagos = mysqli_query($conn, "SELECT * FROM adeudos WHERE id = $usrid AND estado = 'pendiente' ORDER BY vencimiento ASC LIMIT 1");
    $pago_pendiente = mysqli_fetch_assoc($query_pagos);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <!-- [Resto de los meta tags y enlaces a CSS permanecen igual] -->
    <style>
        /* [Todos los estilos CSS anteriores permanecen igual] */
        
        /* Estilo específico para la nueva alerta */
        .alerta-pago {
            padding: 12px 15px;
            margin: 10px 0;
            background-color: #fff3cd;
            border-left: 5px solid #ffc107;
            color: #856404;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 4px;
        }
        
        .alerta-pago .contenido {
            flex-grow: 1;
        }
        
        .alerta-pago .cerrar {
            color: #856404;
            font-size: 20px;
            cursor: pointer;
            margin-left: 15px;
        }
        
        .alerta-pago a {
            color: #d63384;
            font-weight: bold;
            text-decoration: underline;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div id="app">
        <!-- [Todo el código del sidebar permanece igual] -->
        
        <div id="main">
            <header class="mb-3">
                <a href="#" class="burger-btn d-block d-xl-none">
                    <i class="bi bi-justify fs-3"></i>
                </a>
                
                <!-- Nueva alerta de pago pendiente -->
                <?php if($pago_pendiente): ?>
                <div class="alerta-pago">
                    <div class="contenido">
                        <span>
                            Pago pendiente - <?php echo htmlspecialchars($pago_pendiente['servicio']); ?> - 
                            <?php echo htmlspecialchars($pago_pendiente['valor']); ?> - 
                            <?php echo date('d/m/Y', strtotime($pago_pendiente['vencimiento'])); ?>
                        </span>
                        <a href="https://cliente.conlineweb.com/pagos.php">Realizar pago aquí</a>
                    </div>
                    <span class="cerrar" onclick="this.parentElement.style.display='none';">&times;</span>
                </div>
                <?php endif; ?>
            </header>
            
            <!-- [Resto del código permanece igual] -->
        </div>
    </div>
    
    <!-- [Scripts JS permanecen igual] -->
</body>
</html>

<?php
} else {
    header("Location: ingreso.php");
}
?>