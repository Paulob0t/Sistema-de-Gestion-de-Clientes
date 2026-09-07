<?php
session_start();
if(isset($_SESSION['id']) != null && isset($_SESSION['login']) == true) {
    include 'conn.php';
    $usrid = $_SESSION['uid'];
    
    include('menu.php');
    
    function fechaEnEspanol($fecha) {
    // Array con los nombres de los meses en español
    $meses = array(
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
    );
    
    // Convertir la fecha a timestamp
    $timestamp = strtotime($fecha);
    
    // Extraer día, mes y año
    $dia = date('j', $timestamp); // día sin ceros iniciales
    $mes = date('n', $timestamp); // mes numérico sin ceros iniciales
    $anio = date('Y', $timestamp); // año con 4 dígitos
    
    // Formatear la fecha en español
    return $dia . ' de ' . $meses[$mes] . ' del ' . $anio;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dominios - Área cliente</title>
    <style>
        /* Estilos generales */
        td {
            text-align: left;
            width: 50%;
            border: 0.0rem solid #f6f6f6;
            padding: 0px;
        }

        th {
            text-align: left;
            width: 50%;
            border: 0.0rem solid #f6f6f6;
            padding: 10px;
        }

        table {
            padding: 20px;
            border: 0px solid #ffffff;
            margin: 0px;
            background-color: #ffffff;
            padding: 15px;
            border-radius: 0px;
        }

        pc {
            padding: 20px;
            width: 100%;
            border: 0px solid #ffffff;
            margin: 0px;
            font-size: 15px;
            background-color: #ffffff;
            padding: 15px;
            border-radius: 10px;
        }

        /* Estilos para botones */
        .boton_Activo {
            text-decoration: none;
            padding: 3px;
            padding-left: 5px;
            padding-right: 5px;
            font-family: 'Roboto', sans-serif;
            font-weight: 400;
            font-size: 13px;
            color: #FFFFFF;
            background-color: Green;
            border-radius: 3px;    
        }

        .boton_Inactivo {
            text-decoration: none;
            padding: 3px;
            padding-left: 5px;
            padding-right: 5px;
            font-family: 'Roboto', sans-serif;
            font-weight: 400;
            font-size: 13px;
            color: #FFFFFF;
            background-color: Red;
            border-radius: 3px;
        }

        /* Estilos para el switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 23px;
        }

        .switch input { 
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            -webkit-transition: .4s;
            transition: .4s;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 15px;
            width: 15px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            -webkit-transition: .4s;
            transition: .4s;
        }

        input:checked + .slider {
            background-color: #000147;
        }

        input:focus + .slider {
            box-shadow: 0 0 1px #2196F3;
        }

        input:checked + .slider:before {
            -webkit-transform: translateX(26px);
            -ms-transform: translateX(26px);
            transform: translateX(26px);
        }

        .slider.round {
            border-radius: 34px;
        }

        .slider.round:before {
            border-radius: 50%;
        }

        /* Estilos para botón */
         .btn {
                background-color: #000147;
                border: none;
                color: white;
                padding: 3px 8px 3px 8px;
                cursor: pointer;
            }
            
            .btn:hover {
                background-color: green;
                color: white;
            }

        /* Media queries para responsive */
        @media screen and (max-width: 590px) {
            .ocultarcel {
                display: none;
            }
        }

        @media screen and (min-width: 550px) {
            .ocultarpc {
                display: none;
            }
        }
    </style>
</head>

<body>
    <!-- Versión para desktop -->
    <div class="ocultarcel">
        <div style="width: 100%; background-color: #000147; margin: 0px; auto; padding: 10px; padding-top: 20px; border-radius: 0px"> 
            <h3><span style="color: #ffffff;"><i class="bi bi-globe2"></i> Dominios</span></h3>
        </div>
                    
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last"></div>
        </div>
        
        <section class="section">
            <div class="row">
                <div class="col-12">
                    <?php
                    $query = mysqli_query($conn, "SELECT * 
FROM dominios 
WHERE cliente_id = $usrid 
  AND eliminado = 0 
ORDER BY fecha_pago DESC;
");
                    while($mostrar = mysqli_fetch_array($query)) {
                    ?> 
                    <br>
                    <div style="width: 100%; background-color: #ffffff; margin: 0 auto; text-align: left; padding: 20px; padding-top: 5px; border-radius: 5px;">
                        <table border="0" cellpadding="0" width="100%">
                            <tr>
                                <th colspan="0">
                                    <font size="3" style="font-family:calibri;">
                                        <span style="color: Green;"><i class="bi bi-globe"></i></span>
                                    </font>
                                    <font size="5" style="font-family:Calibri; font-weight: 100;">
                                        <span style="color: #003366;">
                                            <a href="https://<?php echo $mostrar['url_dominio'];?>" target="_blank" rel="noopener">
                                                <?php echo $mostrar['url_dominio'];?>
                                            </a>
                                        </span>
                                    </font>
                                </th>
                                <td align="center" colspan="1">
                                    <strong><span style="color: #003366;">Vencimiento</span></strong>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="0">
                                    <font size="2" style="font-family:Calibri;">
                                        <strong>
    <span style="color: #00da0d;">
        <?php if ($mostrar['estado_dominio'] == 1): ?>
            <a class="boton_activo" style="background-color: #00da0d; color: white; padding: 5px 10px; border-radius: 5px; text-decoration: none;">
                Activo
            </a>
        <?php else: ?>
            <a class="boton_inactivo" style="background-color: gray; color: white; padding: 5px 10px; border-radius: 5px; text-decoration: none;">
                Inactivo
            </a>
        <?php endif; ?>
        
        &nbsp;&nbsp;- &nbsp;

        <?php if ($mostrar['registrado'] == 1): ?>
            <span style="color: #00da0d;">Registrado</span>
        <?php else: ?>
            <span style="color: red;">No Registrado</span>
        <?php endif; ?>
    </span>
</strong>

                                    </font>
                                    <br><br>
                                </td>
                                <td align="center" colspan="3">
                                    <span style="color: #003366;"><?php echo fechaEnEspanol($mostrar['fecha_pago']);?></span>
                                    <br><br>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="0"><strong><span style="color: #003366;">Renovar dominio</span></strong></td>
                                <td align="center" colspan="3"><span style="color: #003366;"><strong>Valor</strong></span></td>
                            </tr>  
                            <tr>
                                <td colspan="0">
                                    <label class="switch">
                                        <input type="checkbox" checked>
                                        <span class="slider round" id="checkbox"></span>
                                    </label>
                                </td>
                                <td colspan="3">
                                    <span style="color: #003366;">
                                        $<?php echo $mostrar['costo_dominio']; ?> 
                                        <?php echo ($mostrar['id_forma_pago'] == 1) ? 'MXN' : 'USD'; ?> (Anual)
                                    </span>
                                    <br><br><br>
                                </td>
                            </tr> 
                            
                            
                            <tr>
                            <td colspan="0"><strong><span style="color: #003366;" class="mb-3">DNS</span></strong></td>

                                <td align=center></td> 
                            </tr>
                            
                            <tr>
                                <td colspan=3>
                                    <font size="4" style="font-family:Calibri; ">
                                        <?php
                                        // Mostrar DNS ns1 a ns6 separados por '/' solo si existen, sin diagonales flotando
                                        $dns = [];
                                        for ($i = 1; $i <= 6; $i++) {
                                            $key = 'ns' . $i;
                                            if (!empty($mostrar[$key])) {
                                                $dns[] = $mostrar[$key];
                                            }
                                        }
                                        echo implode(' / ', $dns);
                                        ?><br>
                                    </font>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="0"><strong><span style="color: #003366;"></span></strong></td>
                                <td align="center" colspan="3">
                                    <span style="color: #003366;"><strong>Administra Zona DNS</strong></span>
                                    <br><br>
                                </td>
                            </tr>  
                            <tr>
                                <td colspan="0"></td>
                                <td colspan="3">
                                    <span style="color: #003366;">  
                                        <font size="2" style="font-family:Calibri;">
                                            <a href="dns.php">
                                                <button class="btn" name="">Ir Zona DNS&nbsp;&nbsp; <i class="bi bi-arrow-up-right-square-fill"></i>
                                                </button>
                                            </a>
                                        </font>
                                    </span>
                                </td>
                            </tr>  
                        </table>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </section>
    </div>
    
    <!-- Versión para móvil -->
    <div class="ocultarpc">
        <div style="width: 100%; background-color: #000147; margin: 0px; auto; padding: 10px; padding-top: 20px; border-radius: 0px"> 
            <h4><span style="color: #ffffff;"><i class="bi bi-globe2"></i> Dominios</span></h4>
        </div>
        
        <section class="section">
            <div class="row">
                <div class="col-12">
                    <?php
                    $query = mysqli_query($conn, "SELECT * from dominios WHERE cliente_id= $usrid AND eliminado = 0 ORDER BY `dominios`.`id_dominio` DESC");
                    while($mostrar = mysqli_fetch_array($query)) {
                    ?> 
                    <br>
                    <div style="width: 100%; background-color: #ffffff; margin: 0 auto; text-align: left; padding: 10px; border-radius: 10px">
                        <table border="0" cellpadding="10" width="98%">
                            <tr>
                                <th colspan="4">
                                    <font size="2" style="font-family:calibri;">
                                        <span style="color: Green;"><i class="bi bi-globe"></i></span>
                                    </font>
                                    <font size="2" style="font-family:Calibri; font-weight: 100;">
                                        <span style="color: #003366;">
                                            <a href="https://<?php echo $mostrar['url_dominio'];?>" target="_blank" rel="noopener">
                                                <?php echo $mostrar['url_dominio'];?>
                                            </a>
                                        </span>
                                    </font>
                                </th>
                                <td align="center" colspan="4">
                                    <font size="2" style="font-family:Calibri; font-weight: 800;">
                                        <span style="color: #000000;">Vencimiento</span>
                                    </font>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="0">
                                    <font size="2" style="font-family:Calibri;">
                                      <strong>
    <span style="color: #00da0d;">
        <?php if ($mostrar['estado_dominio'] == 1): ?>
            <a class="boton_activo" style="background-color: #00da0d; color: white; padding: 5px 10px; border-radius: 5px; text-decoration: none;">
                Activo
            </a>
        <?php else: ?>
            <a class="boton_inactivo" style="background-color: gray; color: white; padding: 5px 10px; border-radius: 5px; text-decoration: none;">
                Inactivo
            </a>
        <?php endif; ?>
        
        &nbsp;&nbsp;- &nbsp;

        <?php if ($mostrar['registrado'] == 1): ?>
            <span style="color: #00da0d;">Registrado</span>
        <?php else: ?>
            <span style="color: red;">No Registrado</span>
        <?php endif; ?>
    </span>
</strong>

                                    </font>
                                    <br><br>
                                </td>
                                <td align="center" colspan="4">
                                    <font size="2" style="font-family:Calibri;">
                                        <span style="color: #003366;"><?php echo fechaEnEspanol($mostrar['fecha_pago']);?></span>
                                    </font>
                                    <br><br>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="4">
                                    <strong>
                                        <span style="color: #003366;">
                                            <font size="2" style="font-family:Calibri;">Renovar dominio</font>
                                        </span>
                                    </strong>
                                </td>
                                <td align="center" colspan="0">
                                    <font size="2" style="font-family:Calibri;">
                                        <span style="color: #003366;"><strong>Valor</strong></span>
                                    </font>
                                </td>
                            </tr>  
                            <tr>
                                <td colspan="4">
                                    <span style="color: #000000;"></span>
                                    <a style="background-color: #ffffff; color: #800000; font-family: var(--e-global-typography-text-font-family); font-weight: var(--e-global-typography-text-font-weight);" href="javascript:window.open('<?php echo $mostrar['url_pago'];?>','','width=800,height=600');void(null)">
                                        <font size="1" style="font-family:Calibri;">Renovar dominio aquí</font>
                                    </a> 
                                    <a style="background-color: #ffffff; font-family: var(--e-global-typography-text-font-family); font-weight: var(--e-global-typography-text-font-weight);" href="javascript:window.open('<?php echo $mostrar['url_pago'];?>','','width=800,height=600');void(null)">
                                        <img src="https://c-onlineweb.com/wp-content/uploads/2020/10/cards3.png" alt="" width="25" height="16" />
                                    </a>
                                </td>
                                <td align="center" colspan="4">
                                    <font size="2" style="font-family:Calibri;">
                                       <span style="color: #003366;">
                                        $<?php echo $mostrar['costo_dominio']; ?> 
                                        <?php echo ($mostrar['id_forma_pago'] == 1) ? 'MXN' : 'USD'; ?> (Anual)
                                    </span>
                                    </font>
                                </td>
                            </tr>  
                        </table>    
                    </div>
                    <?php } ?>
                </div>
            </div>
        </section>
    </div>

    <footer></footer>
    
    <script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        function on() {
            console.log("Hemos pulsado en on");
        }

        function off() {
            console.log("Hemos pulsado en off");
        }

        var checkbox = document.getElementById('checkbox');

        checkbox.addEventListener("change", comprueba, false);

        function comprueba() {
            if(checkbox.checked) {
                on();
            } else {
                off();
            }
        }
    </script>
</body>
<?php include('footer.php'); ?>
</html>
<?php
} else {
    header("Location: ingreso.php");
}
?>