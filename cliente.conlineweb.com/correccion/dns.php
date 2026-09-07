<?php
session_start();
if(isset($_SESSION['id'])!=null && isset($_SESSION['login'])==true){
    include 'conn.php';
    $usrid=$_SESSION['uid'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis datos - &Aacute;rea cliente</title>
   
</head>

<body>
    <?php include('menu.php'); ?>
    
    <div id="layoutSidenav_content">
        <main>
            <div class="container-fluid">
                <div style="width: 100%; background-color: #000147; margin: 0px; auto; padding: 10px; padding-top: 20px; border-radius: 0px">  
                    <h3><span style="color: #ffffff;"><i class="bi bi-hdd-rack-fill"></i> Plataforma de alojamiento</h3>
                </div>
                <br>

                <div style="width: 100%; background-color: #ffffff; margin: 0px; auto; padding: 10px; padding-top: 10px; border-radius: 5px;">
                    <?php
                    $query=mysqli_query($conn, "SELECT * from dominios WHERE cliente_id=".$usrid);
                    while($mostrar=mysqli_fetch_array($query)){
                    ?>    
                    
                    <form method="post" action="actulizar_dns.php">
                        <font size="5" style="font-family:calibri; font-weight: 900;"><span style="color: #000147;">Zona DNS <?php echo $mostrar['url_dominio'];?></span></font>
                        
                        <?php date_default_timezone_set('Mexico/General'); $hoy= date("Y-m-d"); $hora= date('H:i');?>
                        <br><br>
                        <font size="4" style="font-family:calibri; font-weight: 700;"><span style="color: #000147;">
                        
                        <div class="row"> 
                            <div class="login">
                                <label for="">id</label>
                                <input type="text" id="id" name="id" required="" value="<?php echo $mostrar['id_dominio'];?>" autocomplete="nope" autofocus>
                            </div>
                            
                            <div class="col-25">
                                <label for="">Servidor 1</label>
                                <input type="text" id="" name="ns1" value="<?php echo $mostrar['ns1'];?>" autocomplete="nope" autofocus>
                            </div>

                            <div class="col-25">
                                <label for="">Servidor 2</label>
                                <input type="text" id="" name="ns2" value="<?php echo $mostrar['ns2'];?>">
                            </div>

                            <div class="col-25">
                                <label for="">Servidor 3</label>
                                <input type="text" id="" name="ns3" value="<?php echo $mostrar['ns3'];?>">
                            </div>
                        </div>

                        <div class="row"> 
                            <div class="col-25">
                                <label for="">Servidor 4</label>
                                <input type="text" id="" name="ns4" value="<?php echo $mostrar['ns4'];?>">
                            </div>

                            <div class="col-25">
                                <label for="">Servidor 5</label>
                                <input type="text" id="" name="ns5" value="<?php echo $mostrar['ns5'];?>">
                            </div>

                            <div class="col-25">
                                <label for="">Servidor 6</label>
                                <input type="text" id="" name="ns6" value="<?php echo $mostrar['ns6'];?>">
                            </div>
                        </div> 
                        
                        <div class="row"> 
                            <div class="col-25">
                                <font size="4" style="font-family:Calibri; font-weight: 100;">
                                    <span style="color: #000000;">
                                        <i class="bi bi-exclamation-octagon"></i> Después de la configuración, puede tardar hasta 72 horas para que las informaciones sean propagadas en el plan de hosting elegido. Su sitio podrá estar online después de este período.<br>
                                        <i class="bi bi-exclamation-octagon"></i> Importante: Si la configuración se realiza para un servidor fuera del alojamiento, la creación del sitio, los registros en la zona DNS, los apuntamientos de correo electrónico y otras configuraciones deberán realizarse en el otro proveedor.<br><br>
                                    </span>
                                </font>
                            </div>
                        </div>

                        <hr class="solid">
                        
                        <div class="row"> 
                            <div class="col-10">
                                <button class="button" id="reg_btn" onclick="alta_cliente()">Actualizar DNS</button>
                            </div>
                            
                            <div class="col-25">
                            </div>
                        </div>
                    </form>
                    
                    <?php } ?>
                    
                    <hr class="solid">
                </div>
                <br><br><br><br>
            </div>
        </main>
    </div>

    <style>
   
    </style>

    <script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    
    <?php include('footer.php'); ?>
</body>
</html>

<?php
} else {
    header("Location: ingreso.php");
}
?>