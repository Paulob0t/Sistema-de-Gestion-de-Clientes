<?php 

	$conexion=mysqli_connect('localhost','conlinew_login','conlineweb15032020*@','conlinew_prueba-clientes');

 ?>



<?php 
		$sql="SELECT * from dominios";
		$result=mysqli_query($conexion,$sql);

		while($mostrar=mysqli_fetch_array($result)){
		 ?>

<html>
<head>
	<title>mostrar datos</title>
	
	<html>
    <html lang="en">
<head>        
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
   <title>Iniciar sesion</title>
   <meta name="viewport" content="widht=device-widht, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@500;700;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Oswald&family=Roboto:wght@500;700&display=swap" rel="stylesheet">
 
 </head> 
</head>
<body>

<br>

	<table border="1" >
		<tr>
			<td>No.Dominio</td>
			<td>Id cliente</td>
			<td>Domino</td>
			<td>Vencimiento</td>
			<td>Costo</td>
				
		</tr>

		
<center><p>Dominio <?php echo $mostrar['url_dominio'] ?></p>


		<tr>
			<td><?php echo $mostrar['id_dominio'] ?></td>
			<td><?php echo $mostrar['id'] ?></td>
			<td><?php echo $mostrar['url_dominio'] ?></td>
			<td><?php echo $mostrar['fecha_pago'] ?></td>
			<td><?php echo $mostrar['costo_dominio'] ?></td>
		
		</tr>
	<?php 
	}
	 ?>
	</table>
	
		</table>

</body>
</html>




<style>

form
{

border-radius: 15px;
border: 1px solid #ffffff;
padding: 5px;
width: 100%;
} 


input[type=text], input[type=password], select, textarea, table
{
    

width: 100%;
height:60px;
border-radius: 5px;
padding: 5px 5px;
border: 1px solid #000000;
box-sizing: border-box
font-family: 'Roboto', sans-serif;
font-weight: 50;
font-size: 15px;
}   
    


input[type=submit] 
{


 background-color: #000000;
    border-radius: 5px;
    border: 1px double #000000;
    padding: 10px;
    width: 100%;
    padding-left: 0px;
    padding-right: 0px;
 font-family: 'Roboto', sans-serif;
    font-weight: 500;
    font-size: 18px;
    font-style: ;
    color: #FFFFFF;
}



label1 
{

 padding: 1px;
    padding-left: 200px;
    padding-right: 0px;
 font-family: helvetica;
    font-weight: 700;
    font-size: 10px;
    font-style: ;
    color: #ffffff;
}



label5
{

 padding: 1px;
    padding-left: 0px;
    padding-right: 0px;
 font-family: 'Roboto', sans-serif;
    font-weight: 500;
    font-size: 17px;
    font-style: ;
    color: #000000;
}

 

  

</style>    



    




<style>

label6 
{

 padding: 1px;
    padding-left: 0px;
    padding-right: 0px;
 font-family: 'Roboto', sans-serif;
 text-align:center;
    font-weight: 500;
    font-size: 15px;
    font-style: ;
    color: #000000;
}



label7 
{

 padding: 1px;
    padding-left: 200px;
    padding-right: 0px;
 font-family: 'Roboto', sans-serif;
    font-weight: 700;
    font-size: 10px;
    font-style: ;
    color: #000000;
}




</style>