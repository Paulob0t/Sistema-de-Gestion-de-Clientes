<?php
require_once __DIR__ . '/includes/cliente_session.php';
cliente_start_session();
if (cliente_is_logged_in()) {
    include 'conn.php';
    $usrid=$_SESSION['uid'];
?>

<?php

$clientePageTitle = 'Mis facturas';
include('menu.php');

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#000147">
 
    <?php require_once __DIR__ . '/includes/cliente_head_meta.php'; ?>
    <title><?= htmlspecialchars(cliente_document_title('Mis facturas'), ENT_QUOTES, 'UTF-8') ?></title>
    <?= cliente_favicon_markup() ?>

   
</head>
                    
                  
                        <div style="width: 100%; background-color: #000147; margin: 0px; auto;
  padding: 10px; padding-top: 20px; border-radius: 0px "> 
  
  <h3><span style="color: #ffffff;"><i class="bi bi-stack"></i> Facturas 2020</span></h3></div>  <br>
                            
                            
     
                        </div>
                        
              <div class="col-12 col-md-6 order-md-1 order-last">          
                    </div>
 <br><div  style="width: 100%;  background-color: #ffffff; margin:
  0 auto; text-align: left; padding: 20px; padding-top: 5px;  border-radius: 5px;">
                            
 <h4 class="card-title">Operaciones</h4>                            
                            
 <style> 

.facant {
	
	width: 250px;
    padding: 5px;
    text-align: center;
    font-weight: 200;
    font-size: 15px;
    cursor: pointer;
    color: #ffffff;
    background: #000147;
    border-radius: 3px;
    border: 0px  double green; 
} 
 
  
  body {font-family: Arial;}

/* Style the tab */
.tab {
  overflow: hidden;
  border: 0px solid #ccc;
  background-color: #ffffff;
  border-radius: 0px;
  
}

/* Style the buttons inside the tab */
.tab button {
  background-color: inherit;
  float: left;
  border: none;
  outline: none;
  cursor: pointer;
  padding: 14px 16px;
  transition: 0.3s;
  font-size: 15px;
  color: #000000;
  font-weight: 100;
}

/* Change background color of buttons on hover */
.tab button:hover {
  background-color: #f5f5f5;
  color: #000000;
}

/* Create an active/current tablink class */
.tab button.active {
  background-color: #000147;
  color: #ffffff;
  margin: 1px;
  
}

/* Style the tab content */
.tabcontent {
      
  display: none;
  padding: 1px 1px;
  border: 0px solid #ccc;
  border-top: none;
}







.circulo {
	text-decoration: none;
    padding: 5px;
    padding-left: 5px;
    padding-right: 5px;
    font-weight: 200;
    font-size: 9px;
    font-style: ;
    color: #00000;
    background-color: ##000000;
    border-radius: 10px;
    border: 0px  double green; 
}





input[type=submit]  {
  background-color: #000000;
  width: 30%;
}



/* Header/logo Title */
.header {
  padding: 10px;
  text-align: left;
  background: #f6f6f6;
  color: white;
}

/* Increase the font size of the heading */
.header h1 {
  font-size: 40px;
}

/* Style the top navigation bar */
.navbar {
  overflow: hidden;
  background-color: #00406d;
}

  </style>
  
<style>



/* El scroll horizontal de tablas lo resuelve cliente-mobile.js/.css
   envolviéndolas en un contenedor, sin romper el reparto de columnas. */

th {
   
   text-align: left;
   
   border: 0.1rem solid #ccc;
   padding: 0px 10px 0px 10px;
   right: 20px;
   font-family: calibri;
   font-size: 16px;
   font-weight: 100;
}



</style>
      
 

<div class="tab">
  <button class="tablinks" onclick="openCity(event, '2020' ) "id="defaultOpen">A&ntilde;o 2020</button>
 <!-- <button class="tablinks" onclick="openCity(event, '2022')">A&ntilde;o 2022</button>-->
</div>

<div id="2020" class="tabcontent">
<div style="width: 100%; background-color: #ffffff; margin: 0 auto;
  padding: 1px; border-radius: 0px ">     


 <script type="text/javascript">
 function toggle_visibility(id) {
  var e = document.getElementById(id);
  if (e.style.display == "block") e.style.display = "none";
  else e.style.display = "block";
}
 
</script>



   


 <?php
 
 //$query=mysqli_query($conn, "SELECT * from facturas WHERE id=".$id);
 $query=mysqli_query($conn, "SELECT * from facturas WHERE id= $usrid ORDER BY `facturas`.`id_operacion` DESC");
 while($mostrar=mysqli_fetch_array($query)){
?> 
<br><div class="header">

<a style="color: #ffffff; text-decoration: nonee; cursor: pointer;" id="show1" onclick="toggle_visibility('<?php echo $mostrar['id_operacion'];?>');">
    
    
    <!-- divicion admin-->
 <table border = 0 cellpadding = 10 width = 100%> 
  <tr>  
    
 <td><div class="circulo"><img class="wp-image-391 alignnone" src="https://conlineweb.com/wp-content/uploads/2021/04/facturas-1.1.png" alt="" width="25" height="25" /> <font size="3" style = "font-family:Calibri;"><span style="color: #000000;">Operaci&oacute;n  <?php echo $mostrar['id_operacion'];?><br><a class="boton_<?php echo $mostrar['estado_factura'];?>"><?php echo $mostrar['estado_factura'];?>  </a> 
<style type="text/css">

.boton_Pagado{
text-decoration: none;
    padding: 2px;
    padding-left: 5px;
    padding-right: 5px;
   font-family: 'Roboto', sans-serif;
    font-weight: 400;
    font-size: 12px;
    font-style: ;
    color: #FFFFFF;
    background-color: Green;
    border-radius: 5px;
    border: 0px  double #ffffff;    
    
}

.boton_Pendiente{
text-decoration: none;
    padding: 2px;
    padding-left: 5px;
    padding-right: 5px;
   font-family: 'Roboto', sans-serif;
    font-weight: 400;
    font-size: 12px;
    font-style: ;
    color: #FFFFFF;
    background-color: Red;
    border-radius: 5px;
    border: 0px  double #ffffff;    
    
}

.boton_Por_vencer{
text-decoration: none;
    padding: 2px;
    padding-left: 5px;
    padding-right: 5px;
   font-family: 'Roboto', sans-serif;
    font-weight: 400;
    font-size: 12px;
    font-style: ;
    color: #FFFFFF;
    background-color: #ffd200;
    border-radius: 5px;
    border: 0px  double #ffffff;    
    
}

</style></font> </div><font size="2" style = "font-family:Calibri;"><span style="color: #000000;">Fecha de pago - (<?php echo $mostrar['fecha_pago'];?>)</span></font></td>
 
 
 <td align = right><img class="wp-image-391 alignnone" src="https://conlineweb.com/wp-content/uploads/2020/12/flecha-hacia-abajo1.png" alt="" width="25" height="20" /></span></a> </td>
  </tr>  
</table>
  <div id="<?php echo $mostrar['id_operacion'];?>" style="display:none;">










<style>

/* El scroll horizontal de tablas lo resuelve cliente-mobile.js/.css. */




th {
   
   text-align: left;
   
   border: 0.1rem solid #ccc;
   padding: 0px 10px 0px 10px;
   padding-left: 25px;
   font-family: calibri;
   font-size: 15px;
   font-weight: 100;
}





</style>
    
    

    


<table border = 0 cellpadding = 0 width = 100%>

                        

<tr>
<th colspan = 3><span style="color: #003366;"><strong>&nbsp;Producto</span></th>
<th align = center colspan = 0 ><center><span style="color: #000000;"> <strong><span style="color: #003366;">Cantidad</span></strong></span></center></th>
<th colspan = 1><strong><span style="color: #003366;">&nbsp;Valor $</span></strong></th>
</tr>

                          <!-- servicio 1 -->
<tr>
<th colspan = 3><span style="color: #000000;"><?php echo $mostrar['servicio1'];?></span></th>
<th align = center><center><span style="color: #000000;"><?php echo $mostrar['cantidad1'];?></span></center></th>
<th><span style="color: #000000;"><?php echo $mostrar['valor1'];?></span></th>
</tr>


                           <!-- servicio 2 -->

<tr>
<th colspan = 3><span style="color: #000000;"><?php echo $mostrar['servicio2'];?></span></th>
<th><center><span style="color: #000000;"><?php echo $mostrar['cantidad2'];?></span></center></th>
<th><span style="color: #000000;"><?php echo $mostrar['valor2'];?></span></th>
</tr>
                            <!-- servicio 3 -->
<tr>
<th colspan = 3><span style="color: #000000;"><?php echo $mostrar['servicio3'];?></span></th>
<th><center><span style="color: #000000;"><?php echo $mostrar['cantidad3'];?></span></center></center></th>
<th><span style="color: #000000;"><?php echo $mostrar['valor3'];?></span></th>
</tr>


                           <!-- servicio 4 -->

<tr>
<th colspan = 3><span style="color: #000000;"><?php echo $mostrar['servicio4'];?></span></th>
<th><center>
<span style="color: #000000;"><?php echo $mostrar['cantidad4'];?></span></center></th>
<th><span style="color: #000000;"><?php echo $mostrar['valor4'];?></span></th>
</tr>


                           <!-- servicio 5 -->

<tr>
<th colspan = 3><span style="color: #000000;"><?php echo $mostrar['servicio5'];?></span></th>
<th><center><span style="color: #000000;"><?php echo $mostrar['cantidad5'];?></span></center></th>
<th><span style="color: #000000;"><?php echo $mostrar['valor5'];?></span></th>
</tr>


                          <!-- servicio 6 -->

<tr>
<th colspan = 3><span style="color: #000000;"><?php echo $mostrar['servicio6'];?></span></th>
<th><center><span style="color: #000000;"><?php echo $mostrar['cantidad6'];?></span></center></th>
<th><span style="color: #000000;"><?php echo $mostrar['valor6'];?></span></th>
</tr>

<tr>
<th colspan = 3><span style="color: #000000;"></span></th>
<th><center><span style="color: #000000;"><strong>Total</strong></span></center></th>
<th><span style="color: #000000;"><?php echo $mostrar['total'];?> </span></th>
</tr>

<tr>
<th colspan = 3></th>
<th><center><span style="color: #000000;"><strong>Pendiente</strong></span></center></th>
<th><span style="color: #000000;"><?php echo $mostrar['pendiente'];?></span></th>
</tr>
<tr>
<th colspan = 3></th>
<th><center><span style="color: #000000;"><strong>Pagado</strong></span></center></th>
<th><span style="color: #000000;"><?php echo $mostrar['ya_pagado'];?></span></th>
</tr>
</tbody>
</table><br>



<center><font size="2" style = "font-family:Calibri;"><span style="text-decoration-line: underline;"><span style="color: #800000;"><a href="https://c-onlineweb.com/area-clientes/facturas/<?php echo $mostrar['url_factura'];?>" download>DESCARGAR RECIBO DE PAGO</a><a href="https://c-onlineweb.com/area-clientes/facturas/<?php echo $mostrar['url_factura'];?>" download><img src="https://conlineweb.com/wp-content/uploads/2020/10/factura.png" alt="" width="25" height="25" /></a></span></span></font></center><br>

<hr class="solidd">



</div>
</div>

<?php 
    }
 ?>  



<style>

hr.solidd {
  border-top: 5px solid black;
  margin: 0px;
}  







</style>
    


</div></div>





</div></div>
  
  
  
  
  
  
</div>

<!--<div id="2022" class="tabcontent">
  <h4>Por el momento no tiene operaciones </h4>
  
</div>-->

<script>
function openCity(evt, cityName) {
  var i, tabcontent, tablinks;
  tabcontent = document.getElementsByClassName("tabcontent");
  for (i = 0; i < tabcontent.length; i++) {
    tabcontent[i].style.display = "none";
  }
  tablinks = document.getElementsByClassName("tablinks");
  for (i = 0; i < tablinks.length; i++) {
    tablinks[i].className = tablinks[i].className.replace(" active", "");
  }
  document.getElementById(cityName).style.display = "block";
  evt.currentTarget.className += " active";
}
document.getElementById("defaultOpen").click();
</script>
  
</div><!-- div final de boton acordion-->

<script>
var acc = document.getElementsByClassName("accordion");
var i;

for (i = 0; i < acc.length; i++) {
  acc[i].addEventListener("click", function() {
    this.classList.toggle("active");
    var panel = this.nextElementSibling;
    if (panel.style.display === "block") {
      panel.style.display = "none";
    } else {
      panel.style.display = "block";
    }
  });
}
</script>






</div>
                                        </div> 
                                        
                                        
                                        
                                </div>
                                    

                                </div>
                            </div>
                        </div>
                    </div>
                </section>
     
      
               
            </footer>
        </div>
    </div>
    <script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>

    <script src="assets/js/main.js"></script>
</body>
<?php

include('footer.php');

?>
</html>


<?php
}
else{
    header("Location: ingreso.php");
}
?>

