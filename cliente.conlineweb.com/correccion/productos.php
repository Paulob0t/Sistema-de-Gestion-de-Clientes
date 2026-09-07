<?php
session_start();
if(isset($_SESSION['id'])!=null && isset($_SESSION['login'])==true){
    include 'conn.php';
    $usrid=$_SESSION['uid'];
?>


<?php

include('menu.php');

?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
 
    <title>Plataforma - &Aacute;rea cliente</title>

   
</head>   
 
  <style>
 
 @media screen and (max-width: 600px) {
.ocultarcel{
display:none;
}


 </style>
 
 
 <div class="ocultarcel">
 
 <div style="width: 100%; background-color: #000147; margin: 0px; auto;
  padding: 10px; padding-top: 20px; border-radius: 0px ">  
  
  <h3><span style="color: #ffffff;"><i class="bi bi-grid-3x3-gap-fill"></i> Plataformas</h3>
 </div>
 
 
 
                    
<div class="row">
<div class="col-12 col-md-6 order-md-1 order-last">
                            
                                 
                           
</div>
</div>
</div>
                        
   
                  
                        
                    


                        
                        <div class="col-12 col-md-6 order-md-2 order-first">
                            <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                                <ol class="breadcrumb">

                                </ol>
                            </nav>
                        
                   
                </div>
                <section class="section">
                    <div class="row">
  
   
                        <div class="col-12">
                            

   <style>



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
   padding: 0px;
}

table {
  padding: 0px;
  border: 0px solid #ffffff;
  margin: 0px;
  background-color: #ffffff;
  padding: 10px;
  border-radius: 0px;
}



</style>                             
                            
   <?php
    $query=mysqli_query($conn, "SELECT * from productos WHERE id=".$usrid);
    while($mostrar=mysqli_fetch_array($query)){
?>    
                            
                            
    <div class="ocultarcel">                                

 
                           <br><div class="ocultarcel">                   
<div  style="width: 100%;  background-color: #ffffff; margin:
  0 auto; text-align: left; padding: 20px; padding-top: 15px;  border-radius: 5px;">
                                    


 
    
   
<table border = 0 cellpadding = 0 width = 100%>
    
    

 
        
<tr>

 <th colspan = 0><font size="4" style = "font-family:Calibri; font-weight: 100;"><span style="color: #000000;"><?php echo$mostrar['tipo_producto']?> <strong><a class="boton_<?php echo $mostrar['estado_producto'];?>"><?php echo $mostrar['estado_producto'];?> </a></span></strong></font></td>
 
 <td align = center ><strong><span style="color: #000000;">Vencimiento</span></strong></th>
 
 </tr>
 
 
 
  
 
 
  
<style type="text/css">


 
 .boton_Activo{
text-decoration: none;
    padding: 3px;
    padding-left: 5px;
    padding-right: 5px;
   font-family: 'Roboto', sans-serif;
    font-weight: 400;
    font-size: 13px;
    font-style: ;
    color: #FFFFFF;
    background-color: Green;
    border-radius: 3px;    
}

.boton_Inactivo{
text-decoration: none;
    padding: 3px;
    padding-left: 5px;
    padding-right: 5px;
   font-family: 'Roboto', sans-serif;
    font-weight: 400;
    font-size: 13px;
    font-style: ;
    color: #FFFFFF;
    background-color: Red;
    border-radius: 3px;
    
}

</style>

 
 
 
 
 




        
<tr>
    
    <td colspan = 0 ><font size="4" style = "font-family:calibri;"><span style="color: Green;"><i class="bi bi-grid-3x3-gap-fill"></i></span></font><font size="5" style = "font-family:Calibri; font-weight: 100;"> <span style="color: #000000;"><?php echo $mostrar['producto'];?><br><br></span></font></td>

 <td align = center ><?php echo $mostrar['fecha_pago'];?><br><br></td>
 
 </tr>


  





    
<tr>    

<td colspan = 0><strong><span style="color: #003366;">Renovar plan</span></strong>
<span style="color: #000000;"></span></td>

<td align = center><strong><span style="color: #003366;"> valor</span></strong></td>

</tr>
<tr>

<td colspan = 0><a style="background-color: #ffffff; color: Green; font-family: var( --e-global-typography-text-font-family ); font-weight: var( --e-global-typography-text-font-weight );" href="javascript:window.open('<?php echo $mostrar['url_pago'];?>','','width= 800,height=600');void(null)"><font size="4" style = "font-family:Calibri; font-weight: 100;">Renovar plan aqu&iacute;</a> <a style="background-color: #ffffff; font-family: var( --e-global-typography-text-font-family ); font-weight: var( --e-global-typography-text-font-weight );" href="javascript:window.open('<?php echo $mostrar['url_pago'];?>','','width= 800,height=600');void(null)"><img src="https://c-onlineweb.com/wp-content/uploads/2020/10/cards3.png" alt="" width="25" height="16" /></a></td>


<td align = center><span style="color: #003366;"><?php echo $mostrar['costo_producto'];?></span></td>
</tr>

 </table>    
  

</div>  


<?php 
    }
 ?>
                     </div>
 </div>                   </div>
                </section>
            </div>



 <style>
 
  @media screen and (min-width:400px) {
.ocultarpc{
display:none;
}


 </style>
 
 
 <div class="ocultarpc">
 
 <div style="width: 100%; background-color: #000147; margin: 0px; auto;
  padding: 10px; padding-top: 20px; border-radius: 0px ">  
  
  <h3><span style="color: #ffffff;"><i class="bi bi-grid-3x3-gap-fill"></i> Productos</h3>
 </div>
 
 
 
                    
<div class="row">
<div class="col-12 col-md-6 order-md-1 order-last">
                            
                                 
                           
</div>
</div>
</div>
                        
   
                 
                        
                    


                        
                        <div class="col-12 col-md-6 order-md-2 order-first">
                            <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                                <ol class="breadcrumb">

                                </ol>
                            </nav>
                        
                   
                </div>
                <section class="section">
                    <div class="row">
  
   
                        <div class="col-12">
                            

   <style>



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
   padding: 0px;
}

table {
  padding: 0px;
  border: 0px solid #ffffff;
  margin: 0px;
  background-color: #ffffff;
  padding: 10px;
  border-radius: 0px;
}



</style>                             
                            
   <?php
    $query=mysqli_query($conn, "SELECT * from productos WHERE id=".$usrid);
    while($mostrar=mysqli_fetch_array($query)){
?>    
                            
                            
                                  

 
                           <br><div class="ocultarpc">                   
 <div  style="width: 100%;  background-color: #ffffff; margin:
  0 auto; text-align: left; padding: 10px;  border-radius: 10px">
  
                                    


 
    
   
<table border = 0 cellpadding = 0 width = 98%>
    
    

 
        
<tr>

 <th colspan = 0><font size="4" style = "font-family:Calibri; font-weight: 100;"><span style="color: #000000;"><?php echo$mostrar['tipo_producto']?> <strong><a class="boton_<?php echo $mostrar['estado_producto'];?>"><?php echo $mostrar['estado_producto'];?> </a></span></strong></font></td>
 
 <td align = center ><strong><span style="color: #000000;">Vencimiento</span></strong></th>
 
 </tr>
 
 
 
  
 
 
  
<style type="text/css">


 
 .boton_Activo{
text-decoration: none;
    padding: 3px;
    padding-left: 5px;
    padding-right: 5px;
   font-family: 'Roboto', sans-serif;
    font-weight: 400;
    font-size: 13px;
    font-style: ;
    color: #FFFFFF;
    background-color: Green;
    border-radius: 3px;    
}

.boton_Inactivo{
text-decoration: none;
    padding: 3px;
    padding-left: 5px;
    padding-right: 5px;
   font-family: 'Roboto', sans-serif;
    font-weight: 400;
    font-size: 13px;
    font-style: ;
    color: #FFFFFF;
    background-color: Red;
    border-radius: 3px;
    
}

</style>

 
 
 
 
 




        
<tr>
    
    <td colspan = 0 ><font size="4" style = "font-family:calibri;"><span style="color: Green;"><i class="bi bi-grid-3x3-gap-fill"></i></span></font><font size="3" style = "font-family:Calibri; font-weight: 100;"> <span style="color: #000000;"><?php echo $mostrar['producto'];?><br></span></font></td>

 <td align = center ><?php echo $mostrar['fecha_pago'];?><br></td>
 
 </tr>


  





    
<tr>    

<td colspan = 0><strong><span style="color: #003366;">Renovar plan</span></strong>
<span style="color: #000000;"></span></td>

<td align = center><strong><span style="color: #003366;"> valor</span></strong></td>

</tr>
<tr>

<td colspan = 0><a style="background-color: #ffffff; color: #800000; font-family: var( --e-global-typography-text-font-family ); font-weight: var( --e-global-typography-text-font-weight );" href="javascript:window.open('<?php echo $mostrar['url_pago'];?>','','width= 800,height=600');void(null)"><font size="3" style = "font-family:Calibri;">Renovar plan aqu&iacute;</a> <a style="background-color: #ffffff; font-family: var( --e-global-typography-text-font-family ); font-weight: var( --e-global-typography-text-font-weight );" href="javascript:window.open('<?php echo $mostrar['url_pago'];?>','','width= 800,height=600');void(null)"><img src="https://c-onlineweb.com/wp-content/uploads/2020/10/cards3.png" alt="" width="25" height="16" /></a></td>


<td align = center><span style="color: #003366;"><?php echo $mostrar['costo_producto'];?></span></td>
</tr>
  
 </table>    
  

</div>  


<?php 
    }
 ?>
                     </div>
 </div>                   </div>
                </section>
            </div>




            <footer>
                
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