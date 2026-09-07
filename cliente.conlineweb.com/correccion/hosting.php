<?php
session_start();
if(isset($_SESSION['id'])!=null && isset($_SESSION['login'])==true){
    include 'conn.php';
    $usrid=$_SESSION['uid'];
    
        function fechaEnEspanol($fecha) {
    // Array con los nombres de los meses en espa«Ðol
    $meses = array(
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
    );
    
    // Convertir la fecha a timestamp
    $timestamp = strtotime($fecha);
    
    // Extraer d«¿a, mes y a«Ðo
    $dia = date('j', $timestamp); // d«¿a sin ceros iniciales
    $mes = date('n', $timestamp); // mes num«±rico sin ceros iniciales
    $anio = date('Y', $timestamp); // a«Ðo con 4 d«¿gitos
    
    // Formatear la fecha en espa«Ðol
    return $dia . ' de ' . $meses[$mes] . ' del ' . $anio;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hosting - &Aacute;rea cliente</title>
</head>
<body>
    <?php include('menu.php'); ?>
    
    <!-- Contenido desktop -->
    <div class="ocultarcel">
        <div style="width: 100%; background-color: #000147; margin: 0px; auto; padding: 10px; padding-top: 20px; border-radius: 0px">  
            <h3><span style="color: #ffffff;"><i class="bi bi-hdd-rack-fill"></i> Hosting</h3>
        </div>
        
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last"></div>
        </div>
        
        <section class="section">
            <div class="row">
                <div class="col-12">
                    <?php
                    $query=mysqli_query($conn, "SELECT * from hosting WHERE cliente_id= $usrid AND eliminado = 0  ORDER BY `hosting`.`id_orden` DESC");
                    while($mostrar=mysqli_fetch_array($query)){
                    ?>
                    <br>
                    <div style="width: 100%; background-color: #ffffff; margin: 0 auto; text-align: left; padding: 20px; padding-top: 15px; border-radius: 5px;">
                        <table border=0 cellpadding=0 width=100%>
                            <tr>
                                <th colspan=0>
                                    <font size="4" style="font-family:Calibri; font-weight: 100;">
                                        <span style="color: #000000;">
                                            <?php echo$mostrar['tipo_producto']?> 
                                                     <strong>
    <span style="color: #00da0d;">
        <?php if ($mostrar['estado_producto'] == 1): ?>
            <a class="boton_activo" style="background-color: #00da0d; color: white; padding: 5px 10px; border-radius: 5px; text-decoration: none;">
                Activo
            </a>
        <?php else: ?>
            <a class="boton_inactivo" style="background-color: gray; color: white; padding: 5px 10px; border-radius: 5px; text-decoration: none;">
                Inactivo
            </a>
        <?php endif; ?>
        
        <!--&nbsp;&nbsp;- &nbsp;-->

        <!--<?php if ($mostrar['registrado'] == 1): ?>-->
        <!--    <span style="color: #00da0d;">Registrado</span>-->
        <!--<?php else: ?>-->
        <!--    <span style="color: red;">No Registrado</span>-->
        <!--<?php endif; ?>-->
    </span>
</strong>
                                        </span>
                                    </font>
                                </th>
                                <td align=center><strong><span style="color: #003366;">Vencimiento</span></strong></th>
                            </tr>
                            
                            <tr>
                                <td colspan=0>
                                    <font size="2" style="font-family:calibri;">
                                        <span style="color: Green;"><i class="bi bi-hdd-rack-fill"></i></span>
                                    </font>
                                    
                                    <?php
                                    // Obtener el nombre del plan desde la tabla planes usando el ID de producto
                                    $plan_nombre = $mostrar['producto']; // Valor por defecto por si no encuentra
                                    $plan_id = $mostrar['producto'];
                                    $plan_query = mysqli_query($conn, "SELECT nombre FROM planes WHERE id = '$plan_id' LIMIT 1");
                                    if ($plan_row = mysqli_fetch_assoc($plan_query)) {
                                        $plan_nombre = $plan_row['nombre'];
                                    }
                                    ?>
                                    <font size="5" style="font-family:Calibri; font-weight: 100;">
                                        <span style="color: #000000;">
                                            <?php echo $plan_nombre; ?>
                                        </span>
                                    </font>
                                    <br>
                                </td>
                                <td align=center><?php echo fechaEnEspanol($mostrar['fecha_pago']);?><br><br></td>
                            </tr>
                            
                            <tr>    
                                <td colspan=0><strong><span style="color: #003366;">Dominio principal</span></strong></td>
                                <td align=center><strong><span style="color: #003366;"> Nombre del host</span></strong></td>
                            </tr>
                            
                            <tr>
                                <td colspan=0><?php echo $mostrar['dominio'];?><br><br></td>
                                <td align=center><?php echo $mostrar['nom_host'];?><br><br></td>
                            </tr>
                            
                            <tr>    
                                <td colspan=0><strong><span style="color: #003366;">Renovar plan</span></strong></td>
                                <td align=center><strong><span style="color: #003366;"> valor</span></strong></td>
                            </tr>
                            
                            <tr>
                                <td colspan=0>
                                    <label class="switch">
                                        <input type="checkbox" checked>
                                        <span class="slider round"></span>
                                    </label>
                                </td>
                                <td align=center>
                                    <span style="color: #003366;">
                                        $<?php echo $mostrar['costo_producto']; ?> 
                                        <?php echo ($mostrar['id_forma_pago'] == 1) ? 'MXN' : 'USD'; ?> (Anual)
                                    </span>
                                    
                                    <br></td>
                            </tr>
                            
                            <tr style=" padding-top:10px">
                                <td colspan=0><strong><span style="color: #003366; margin-top:10px">DNS </span></strong></td>
                            </tr>
                            
                            <tr>
                                <td colspan="0"><font size="3" style="font-family:Calibri;">
                                            <?php
                                            
                                            // Une los valores con "/" solo si hay alguno v«¡lido
                                            echo $mostrar['dns'];
                                            ?>
                                        </font></td>

                                <td colspan=0>
                                    <div class="login">
                                        <form method="post" action="<?php echo $mostrar['url_acceso'];?>/login" target="_blank">  
                                            <input name="user" id="<?php echo $mostrar['usuario'];?>" autofocus="autofocus" value="<?php echo $mostrar['usuario'];?>" placeholder="Indique su nombre de usuario." class="std_textbox" type="text" tabindex="1" required="">
                                            <input name="pass" id="<?php echo $mostrar['usuario'];?>" value="<?php echo $mostrar['contrasena'];?>" placeholder="Indique la contraseÃ±a de su cuenta." class="std_textbox" type="password" tabindex="2" required="">
                                    </div>
                                    <div class="controls">
                                        <div class="login-btn">
                                            <font size="2" style="font-family:Calibri;">
                                                <button class="btn" name="login" type="submit" id="<?php echo $mostrar['usuario'];?>" tabindex="3">
                                                    Ir al cPanel&nbsp;&nbsp; <i class="bi bi-arrow-up-right-square-fill"></i>
                                                </button>
                                            </font>
                                        </div>
                                    </div>
                                    </form>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </section>
    </div>
    
    <!-- Contenido m«Ñvil -->
    <div class="ocultarpc">
        <div style="width: 100%; background-color: #000147; margin: 0px; auto; padding: 10px; padding-top: 20px; border-radius: 0px">  
            <h3><span style="color: #ffffff;"><i class="bi bi-hdd-rack-fill"></i> Hosting</h3>
        </div>
        
        <section class="section">
            <div class="row">
                <div class="col-12">
                    <?php
                    $query=mysqli_query($conn, "SELECT * from hosting  WHERE id=".$usrid . "AND eliminado = 0");
                    while($mostrar=mysqli_fetch_array($query)){
                    ?>
                    <br>
                    <div style="width: 100%; background-color: #ffffff; margin: 0 auto; text-align: left; padding: 10px; border-radius: 10px">
                        <table border=0 cellpadding=0 width=98%>
                            <tr>
                                <th colspan=0>
                                    <font size="2" style="font-family:Calibri; font-weight: 100;">
                                        <span style="color: #000000;">
                                            <?php echo$mostrar['tipo_producto']?> 
                                            <strong>
                                                <a class="boton_<?php echo $mostrar['estado_producto'];?>">
                                                    <?php echo $mostrar['estado_producto'];?> 
                                                </a>
                                            </strong>
                                        </span>
                                    </font>
                                </th>
                                <td align=center>
                                    <strong>
                                        <span style="color: #000000;">
                                            <font size="2" style="font-family:calibri;">Vencimiento</span>
                                        </strong>
                                    </font>
                                </th>
                            </tr>
                            
                            <tr>
                                <td colspan=0>
                                    <font size="2" style="font-family:calibri;">
                                        <span style="color: Green;"><i class="bi bi-hdd-rack-fill"></i></span>
                                    </font>
                                    <font size="3" style="font-family:Calibri; font-weight: 100;">
                                        <span style="color: #000000;"><?php echo $mostrar['producto'];?><br></span>
                                    </font>
                                </td>
                                <td align=center>
                                    <font size="2" style="font-family:calibri;"><?php echo fechaEnEspanol($mostrar['fecha_pago']);?><br></td>
                                </font>
                            </tr>
                            
                            <tr>    
                                <td colspan=0>
                                    <strong>
                                        <font size="2" style="font-family:calibri;">
                                            <span style="color: #003366;">Dominio principal</span>
                                        </strong>
                                    </font>
                                </td>
                                <td align=center>
                                    <strong>
                                        <font size="2" style="font-family:calibri;">
                                            <span style="color: #003366;"> Nombre del host</span>
                                        </strong>
                                    </font>
                                </td>
                            </tr>
                            
                            <tr>
                                <td colspan=0>
                                    <font size="2" style="font-family:calibri;"><?php echo $mostrar['dominio'];?><br><br></td>
                                <td align=center>
                                    <font size="2" style="font-family:calibri;"><?php echo $mostrar['nom_host'];?><br><br></td>
                                </font>
                            </tr>
                            
                            <tr>    
                                <td colspan=0>
                                    <strong>
                                        <span style="color: #003366;">
                                            <font size="2" style="font-family:calibri;">Renovar plan</span>
                                        </strong>
                                    </span>
                                </td>
                                <td align=center>
                                    <strong>
                                        <span style="color: #003366;">
                                            <font size="2" style="font-family:calibri;"> valor</span>
                                        </strong>
                                    </font>
                                </td>
                            </tr>
                            
                            <tr>
                                <td colspan=0>
                                    <a style="background-color: #ffffff; color: #800000; font-family: var( --e-global-typography-text-font-family ); font-weight: var( --e-global-typography-text-font-weight );" href="javascript:window.open('<?php echo $mostrar['url_pago'];?>','','width= 800,height=600');void(null)">
                                        <font size="2" style="font-family:Calibri;">Renovar plan aqu&iacute;</a> 
                                    <a style="background-color: #ffffff; font-family: var( --e-global-typography-text-font-family ); font-weight: var( --e-global-typography-text-font-weight );" href="javascript:window.open('<?php echo $mostrar['url_pago'];?>','','width= 800,height=600');void(null)">
                                        <img src="https://c-onlineweb.com/wp-content/uploads/2020/10/cards3.png" alt="" width="25" height="16" />
                                    </a>
                                </td>
                                <td align=center>
                                    <span style="color: #003366;">
                                        $<?php echo $mostrar['costo_producto']; ?> 
                                        <?php echo ($mostrar['id_forma_pago'] == 1) ? 'MXN' : 'USD'; ?> (Anual)
                                    </span>
                                </font>
                                </td>
                            </tr>
                            
                            <tr>
                                <td colspan=3>
                                    <font size="2" style="font-family:calibri;">
                                        <strong>DNS</strong>
                                    </font>
                                </td>
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
                                <td colspan=1><br></td>
                                <td align=center>
                                    <div class="login">
                                        <form method="post" action="<?php echo $mostrar['url_acceso'];?>/login" target="_blank">  
                                            <input name="user" id="<?php echo $mostrar['usuario'];?>" autofocus="autofocus" value="<?php echo $mostrar['usuario'];?>" placeholder="Indique su nombre de usuario." class="std_textbox" type="text" tabindex="1" required="">
                                            <input name="pass" id="<?php echo $mostrar['usuario'];?>" value="<?php echo $mostrar['contrasena'];?>" placeholder="Indique la contraseÃ±a de su cuenta." class="std_textbox" type="password" tabindex="2" required="">
                                    </div>
                                    <div class="controls">
                                        <div class="login-btn">
                                            <font size="1" style="font-family:Calibri;">
                                                <button class="btn" name="login" type="submit" id="<?php echo $mostrar['usuario'];?>" tabindex="3">
                                                    <font size="2" style="font-family:Calibri;">Administrar&nbsp;&nbsp; <i class="bi bi-arrow-up-right-square-fill"></i>
                                                </button>
                                            </font>
                                        </div>
                                    </div>
                                    </form>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </section>
    </div>
    
    <?php include('footer.php'); ?>
    
    <script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    
    <script>
// Objeto con los datos sensibles solo en JS
var hostingData = {};
<?php
// Generar el objeto JS con los datos de todos los hostings
$query = mysqli_query($conn, "SELECT * from hosting WHERE cliente_id= $usrid AND eliminado = 0 ORDER BY `hosting`.`id_orden` DESC");
while($mostrar = mysqli_fetch_array($query)) {
    $id = $mostrar['id_orden'];
    $usuario = htmlspecialchars($mostrar['usuario'], ENT_QUOTES);
    $contrasena = htmlspecialchars($mostrar['contrasena'], ENT_QUOTES);
    $url_acceso = htmlspecialchars($mostrar['url_acceso'], ENT_QUOTES);
    echo "hostingData['$id'] = {usuario: '$usuario', contrasena: '$contrasena', url_acceso: '$url_acceso'};\n";
}
?>

function loginHosting(id) {
    var data = hostingData[id];
    if (!data) return;
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = data.url_acceso + '/login';
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
    var inputSubmit = document.createElement('input');
    inputSubmit.type = 'submit';
    inputSubmit.name = 'login';
    inputSubmit.value = 'Administrar';
    form.appendChild(inputSubmit);
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}
</script>
</body>
</html>
<?php
}
else{
    header("Location: ingreso.php");
}
?>