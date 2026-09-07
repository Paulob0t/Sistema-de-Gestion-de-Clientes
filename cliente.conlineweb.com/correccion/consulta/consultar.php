<?php
session_start();
if(isset($_SESSION['id'])!=null && isset($_SESSION['login'])==true){
include 'configuracion.php';

$nombrecliente =$_POST['nombrecliente'];
$apellidocliente= $_POST['apellidocliente'];
$calle =$_POST['calle'];          
$colonia =$_POST['colonia'];
$delegacion =$_POST['delegacionmunicipio'];  
$telefono =$_POST['telefono'];  
$idpersona =$_POST['idpersona'];  


    $query="";

    if($idpersona!=null || $nombrecliente!=null || $apellidocliente!=null || $calle!=null || $colonia!=null || $delegacion!=null || $telefono!=null){
        
        if($idpersona!=null || $nombrecliente!=null || $apellidocliente!=null){

            $query.="SELECT * FROM registro WHERE ";
            
            if($idpersona!=null){
                $query.="id_personal LIKE '%$idpersona%' ";
            }
            if($nombrecliente!=null){
                if($idpersona!=null){
                    $query.="AND ";                        
                }
                $query.="nombre_cliente LIKE '%$nombrecliente%' ";
            }
            if($apellidocliente!=null){
                if($nombrecliente!=null || $idpersona!=null){
                    $query.="AND ";                        
                }
                $query.="apellido_cliente LIKE '%$apellidocliente%' ";
            }
        }
        if($calle!=null || $colonia!=null){
            if($nombrecliente!=null || $apellidocliente!=null || $delegacion!=null || $telefono!=null || $idpersona!=null){
                $query.='AND ';
            }
            else {$query.="SELECT * FROM registro WHERE ";}
            if($calle!=null){ 
                $query.="calle LIKE '%$calle%' ";
            }
            if($colonia!=null){ 
                if($calle!=null){ 
                    $query.="AND ";
                }
                $query.="colonia LIKE '%$colonia%' ";
            }
        }
        
         if($delegacion!=null || $telefono!=null){
            if($nombrecliente!=null || $apellidocliente!=null || $calle!=null || $colonia!=null || $idpersona!=null){
                $query.='AND ';
            }
            else {$query.="SELECT * FROM registro WHERE ";}
            if($delegacion!=null){ 
                $query.="delegacion_municipio LIKE '%$delegacion%' ";
            }
            if($telefono!=null){ 
                if($delegacion!=null){ 
                    $query.="AND ";
                }
                $query.="tel_contacto LIKE '%$telefono%' ";
            }
        }
        $query.=';';      
        }
        else if($_GET['todos']==1){
            $query="SELECT * FROM registro;"; }

?>


<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
<title>Consulta</title>



<style>
body {
  margin: 15px;
  font-family: Arial, Helvetica, sans-serif;
}

.topnav {
  overflow: hidden;
  background-color: #333;
}

.topnav a {
  float: left;
  display: block;
  color: #f2f2f2;
  text-align: center;
  padding: 14px 16px;
  text-decoration: none;
  font-size: 17px;
}

.topnav a:hover {
  background-color: #ddd;
  color: black;
}

.topnav a.active {
  background-color: #4CAF50;
  color: white;
}

.topnav .icon {
  display: none;
}

@media screen and (max-width: 600px) {
  .topnav a:not(:first-child) {display: none;}
  .topnav a.icon {
    float: right;
    display: block;
  }
}

@media screen and (max-width: 600px) {
  .topnav.responsive {position: relative;}
  .topnav.responsive .icon {
    position: absolute;
    right: 0;
    top: 0;
  }
  .topnav.responsive a {
    float: none;
    display: block;
    text-align: left;
  }
}
</style>




<style>
body {
  font-family: Arial;
  font-size: 17px;
  padding: 0px;
}

* {
  box-sizing: border-box;
}

.row {
  display: -ms-flexbox; /* IE10 */
  display: flex;
  -ms-flex-wrap: wrap; /* IE10 */
  flex-wrap: wrap;
  margin: 0 -16px;
}

.col-25 {
  -ms-flex: 25%; /* IE10 */
  flex: 25%;
}

.col-50 {
  -ms-flex: 50%; /* IE10 */
  flex: 50%;
}

.col-75 {
  -ms-flex: 75%; /* IE10 */
  flex: 75%;
}

.col-25,
.col-50,
.col-75 {
  padding: 0 16px;
}

.container {
  background-color: #f2f2f2;
  padding: 5px 20px 15px 20px;
  border: 1px solid lightgrey;
  border-radius: 3px;
}

input[type=text], select {
  width: 100%;
  margin-bottom: 20px;
  padding: 12px;
  border: 1px solid #ccc;
  border-radius: 3px;
}











.btn {
  background-color: #4CAF50;
  color: white;
  padding: 5px;
  margin: 10px 0;
  border: none;
  width: 100%;
  text-align: center;
  border-radius: 3px;
  cursor: pointer;
  font-size: 17px;
}

.btn:hover {
  background-color: #45a049;
}

a {
  color: #2196F3;
}

hr {
  border: 1px solid lightgrey;
}

span.price {
  float: right;
  color: grey;
}

/* Responsive layout - when the screen is less than 800px wide, make the two columns stack on top of each other instead of next to each other (also change the direction - make the "cart" column go on top) */
@media (max-width: 800px) {
  .row {
    flex-direction: column;
  }
  .col-25 {
    margin-bottom: 20px;
  }
  
  
  
/* Solid border */
hr.solid {
  border-top: 3px solid #bbb;
}  
  
  
  
}
</style>





<style>


<style>

.table-registros {
 table-layout: fixed;
 
    
}

.table-registros   {
   vertical-align: top;
   border: 0px solid #000;
   border-collapse: collapse;
   padding: 1px;
   caption-side: bottom;
   color: #000000;
   text-align: center;
   font-size: 11px;
   font-weight: 200;
   
   background: #ffffff;

}



.btnn {
  background-color: #4CAF50;
  color: white;
  padding: 4px;
  margin: 4px 4px;
  border: none;
  width: 100%;
  text-align: center;
  border-radius: 3px;
  cursor: pointer;
  font-size: 11px;
}


.boton {
  background-color: #ababab;
  color: white;
  padding: 5px;
  margin: 10px 0;
  border: none;
  width: 100%;
  text-align: center;
  border-radius: 3px;
  cursor: pointer;
  font-size: 17px;
}

.modal fade modal-fullscreen{

display: -ms-flexbox; /* IE10 */
  display: flex;
  -ms-flex-wrap: wrap; /* IE10 */
  flex-wrap: wrap;
  margin: 0 -16px;

}

/* Solid border */
hr.solid {
  border-top: 3px solid #bbb;
}  
  

</style>



</head>
<body>

<div class="topnav" id="myTopnav">
  <a href="#home" class="active">Consulta</a>
  <a href="index.php">Registro</a>
  <a href="carga.php">Carga</a>
  <a href="cerrarSesion.php" >Cerrar Sesion</a>
  <a href="javascript:void(0);" class="icon" onclick="myFunction()">
    <span style="color: #ffffff;"><img
src="https://albainmobiliaria.info/wp-content/uploads/2021/03/Menu-hamburguesa.png"
      width="40" height="20"></span>
   
  </a>
</div>



<script>
function myFunction() {
  var x = document.getElementById("myTopnav");
  if (x.className === "topnav") {
    x.className += " responsive";
  } else {
    x.className = "topnav";
  }
}
</script>

 
 
</div>

<br><br>
<div class="row">
      <div class="col-75">
        <div class="container">
            <h2>Consulta</h2><br>
            <form name="consulta" id="consulta" method="post" action="consultar.php" enctype="multipart/form-data">  
            <div class="row">
                
                 <div class="col-25">

<label for="id">ID Personal</label>
            <select name="idpersona" id="idpersona" required=""><option selected></option>

<option value="1">1</option>
<option value="2">2</option>
<option value="3">3</option>
<option value="4">4</option>
<option value="5">5</option>
<option value="6">6</option>
<option value="7">7</option>
<option value="8">8</option>
<option value="9">9</option>
<option value="10">10</option>

</select><br>
</div>
                
                
                <div class="col-25">
                  <label for="nombrecliente">Nombre del cliente </label>
                  <input type="text" id="nombrecliente" name="nombrecliente"  placeholder="Buscar cliente">
                </div>
                <div class="col-25">
                    <label for="apellidocliente">Apellido del cliente </label>
                        <input type="text" id="apellidocliente" name="apellidocliente"  placeholder="Buscar cliente">
                </div>
            </div>
            
            

            
            <div class="row">
                <div class="col-50">
                    <label for="calle">Calle</label>
                    <input type="text" id="calle" name="calle"  placeholder="Buscar calle">
                </div>
                <div class="col-50">
                    <label for="colonia">Colonia</label>
                    <input type="text" id="colonia" name="colonia"  placeholder="Buscar colonia">
                </div>
            </div> 
            
            
            <div class="row">
                <div class="col-50">
                    <label for="delegacionmunicipio">Delegaci&oacute;n o municipio</label>
                    <input type="text" id="delegacionmunicipio" name="delegacionmunicipio"  placeholder="Buscar delegaci&oacute;n o municipio">
                </div>
                <div class="col-50">
                    <label for="telefono">Tel&eacute;fono</label>
                    <input type="text" id="telefono" name="telefono"  placeholder="Buscar tel&eacute;fono">
                </div>
            </div> 
                <button class="btn" id="btn_buscar">Buscar</button>
            </form>   
            
            
                <?php     
                $imprimir="";
    
                $res = mysqli_query($conn, $query);

                if($res){
                
                echo '
                <div class="table-registros">
                    <form method="GET">
                    <table border="1" width="100%">
        		<tr>
        		    <td><font size="2" style = "font-family:calibri;"><strong>Id personal</strong></td>
        			<td><font size="2" style = "font-family:calibri;"><strong>Nombre del cliente</strong></td>
        			<td><font size="2" style = "font-family:calibri;"><strong>Ubicaci&oacute;n</td>
            		<td><font size="2" style = "font-family:calibri;"><strong>Tel&eacute;fono</td>
        			
        			<td><font size="2" style = "font-family:calibri;"><strong>Info </td>
        	    </tr>';  
                    while($nr = mysqli_fetch_array($res)){
                	$imprimir.="<tr>
                	
                	<td>".$nr['id_personal']."</td>
            			<td align = center>&nbsp;&nbsp;".$nr['nombre_cliente']." ".$nr['apellido_cliente']."</td>
            			<td align = center>&nbsp;&nbsp;".$nr['calle'].",  ".$nr['colonia']." - ".$nr['delegacion_municipio']."</td>
            			<td>".$nr['tel_contacto']."</td>
            		 
            			<td>        
            			".'<br><a data-toggle="modal" data-id1="'.$nr['id_registro'].'" class=" btnn  btn_info" data-target="#info_cliente" id="trigger-btn">Ver info </a><br><br>
            			<a data-toggle="modal" data-id1="'.$nr['id_registro'].'" class=" btnn btn_edit" data-target="#editar_cliente" id="trigger-btn">&nbsp;&nbsp;Editar&nbsp;&nbsp;</a><br><br>
            			
            			
        
            			</td>
            		</tr>';		    
                    }   
                    echo $imprimir;
                }
              else{
                    //echo '<div align="center">Registro no encontrado<div>';
                }    
            ?>
        		</table>
        		</form>
        </div>
      </div>
    </div>

    <div class="modal fade modal-fullscreen" 
             id="info_cliente" tabindex="-1" role="dialog" aria- 
             labelledby="myModalLabel">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title" id="myModalLabel">Información de Cliente</h3>
                    </div>
                    <div class="modal-body" id="modal_info">
                    </div>
                    <div class="modal-footer">  
            			<a class="btn" data-dismiss="modal" id="btn_info" href="consultar.php">Cerrar</a>
                    </div>
                </div>
            </div>
        </div>
   
   <div class="modal fade modal-fullscreen" 
             id="editar_cliente" tabindex="-1" role="dialog" aria- 
             labelledby="myModalLabel">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title" id="myModalLabel">Editar Cliente</h3>
                    </div>
                    <div class="modal-body" id="modal_edit">
                    </div>
                    <div class="modal-footer">  
            			<a class="btn" data-dismiss="modal" id="btn_edit">Cerrar</a>
                    </div>
                </div>
            </div>
        </div>
    
    
 
    
    <script>
    $(document).on('click', '.btn_info', function(){
    var id= $(this).data("id1");

        $.ajax({
            url:"buscar.php",
            method:"GET",
            data:{id:id},
            dataType:"html",
            success:function(data){
                $('#modal_info').html(data);
            }
        });
    });
    
    $(document).on('click', '.btn_edit', function(){
    var id= $(this).data("id1");

        $.ajax({
            url:"modificar.php",
            method:"GET",
            data:{id:id},
            dataType:"html",
            success:function(data){
                $('#modal_edit').html(data);
            }
        });
    });
    
    
    
   
    
   $('#btn_buscar').on('click', function(e){
        e.preventDefault();

        document.forms['consulta'].action='consultar.php?todos=1';
        document.forms['consulta'].submit();

    });
    
    
    </script>
<?php
}
else{
    header("Location: login.php");
}
?>
</body>    
</html>