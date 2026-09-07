<?php

// envio datos login

$email = $_POST['email'];
//$contrasena = $_POST['contrasena'];
$psswd = substr( md5(microtime()), 2, 10);


include ('conn.php');

//consulta id login 
$sql = "select Max(id) from login";
$res= mysqli_query($conn, $sql);

$resultado=$res->fetch_assoc();
$id=$resultado['Max(id)'] + 1;






// consulta id dominios
$sql = "select Max(id_dominio) from dominios";
$res2= mysqli_query($conn, $sql);

$resultado2=$res2->fetch_assoc();
$iddom=$resultado2['Max(id_dominio)'] + 1;






$sql = "INSERT INTO `login` (`id`, `usuario`, `contrasena`) VALUES ('$id','$email', '$psswd')";




if (mysqli_query($conn, $sql)) {
       header("Location: https://conlineweb.com/carrito/");
      //echo "<a href=https://c-onlineweb.com/login/login.html#inicio>Volver</a>";
} else {
       echo "<script type='text/javascript'>alert('Utiliza otra cuenta de correo'); window.location.href='https://conlineweb.com/dominios/';</script>";
     //echo "<a href=https://c-onlineweb.com/login/login.html#registro>REGISTRARME</a>";
      
      
}      


// envio datos clientes


$email = $_POST['email'];
$nombre = $_POST['nombre'];
$tel = $_POST['tel'];
$code = $_POST['code'];

$telefono = $code . $tel;

//consulta id clientes
$sql = "select Max(id) from clientes";
$res1= mysqli_query($conn, $sql);

$resultado1=$res1->fetch_assoc();
$idclie=$resultado1['Max(id)'] + 1;


$sql = "INSERT INTO `clientes` (`id`, `nombre contacto`, `empresa`, `correo`, `telefono`, `especificacion`, `rsocial`, `rfc`, `calle`, `next`, `nint`, `col`, `cp`, `pais`, `estado`, `ciudad`, `display`) VALUES ('$idclie', '$nombre', '---', '$email', '$telefono', '---', '---', '---', '---', '---', '---', '---', '---', '---', '---', '---', '---')";


if (mysqli_query($conn, $sql)) {
     // echo "Tu cuenta se creo con exito";
     //echo "<a href=https://c-onlineweb.com/login/login.html#inicio>Volver</a>";
} else {
     echo "Error: " . $sql . "<br>" . mysqli_error($conn);
}


// envio datos dominios

$dominio = $_POST['dominio'];
$domminus = strtolower($dominio);
$hoy = date("d/m/Y");
$pago = date("d/m/Y",strtotime($pago."+ 1 year"));
$nombre = $_POST['nombre'];
$tel = $_POST['tel'];
$moneda = $_POST['moneda'];



//consulta id clientes
$sql = "select Max(id) from clientes";
$res1= mysqli_query($conn, $sql);

$resultado1=$res1->fetch_assoc();
$idclie=$resultado1['Max(id)'];


// consulta id dominios
$sql = "select Max(id_dominio) from dominios";
$res2= mysqli_query($conn, $sql);

$resultado2=$res2->fetch_assoc();
$iddom=$resultado2['Max(id_dominio)'] + 1;

$sql = "INSERT INTO `dominios` (`id_dominio`, `id`, `estado_dominio`, `registrado`, `url_dominio`, `fecha_contratacion`, `fecha_pago`, `costo_dominio`, `url_pago`, `url_admin`, `usuario_admin`, `contrasena_admin`, `url_cpanel`, `url_plataforma`) VALUES ('$iddom', '$idclie', 'Activo', 'Registrado', '$domminus', '$hoy', '$pago', '$moneda', '---', 'https://$domminus/wp-login.php', '---', '---', 'https://cpanel.$domminus:2083/', '---')";


if (mysqli_query($conn, $sql)) {
      //echo "Tu cuenta se creo con exito";
     //echo "<a href=https://c-onlineweb.com/login/login.html#inicio>Volver</a>";
} else {
     echo "Error: " . $sql . "<br>" . mysqli_error($conn);
}



// envio datos hosting

$dominio = $_POST['dominio'];
$domminus = strtolower($dominio);
$hoy = date("d/m/Y");
$pagoh = date("d/m/Y",strtotime($pagoh."+ 1 year"));
$nombre = $_POST['nombre'];
$tel = $_POST['tel'];
$moneda1 = $_POST['moneda1'];



//consulta id clientes
$sql = "select Max(id) from clientes";
$res1= mysqli_query($conn, $sql);

$resultado1=$res1->fetch_assoc();
$idclie=$resultado1['Max(id)'];


// consulta id hostig
$sql = "select Max(id_orden) from hosting";
$res3= mysqli_query($conn, $sql);

$resultado3=$res3->fetch_assoc();
$idhost=$resultado3['Max(id_orden)'] + 1;



$sql = "INSERT INTO `hosting` (`id_orden`, `id`, `dominio`, `nom_host`, `usuario`, `contrasena`, `estado_producto`, `tipo_producto`, `producto`, `fecha_contratacion`, `fecha_pago`, `costo_producto`, `dns`, `url_pago`, `url_acceso`) VALUES ('$idhost', '$idclie', '$domminus', 'cpanel.$domminus', '---', '---', 'Activo', 'Servicio de alojamiento', 'Plan basico', '$hoy', '$pagoh', '$moneda1', 'ns1.$domminus / ns2.$domminus', '---', 'https://cpanel.$domminus:2083')";


if (mysqli_query($conn, $sql)) {
      //echo "Tu cuenta se creo con exito";
     //echo "<a href=https://c-onlineweb.com/login/login.html#inicio>Volver</a>";
} else {
     echo "Error: " . $sql . "<br>" . mysqli_error($conn);
}





// envio datos alertas


//consulta id clientes
$sql = "select Max(id) from clientes";
$res1= mysqli_query($conn, $sql);

$resultado1=$res1->fetch_assoc();
$idclie=$resultado1['Max(id)'];


// consulta id alertas
$sql = "select Max(id_alerta) from alertas";
$res4= mysqli_query($conn, $sql);

$resultado4=$res4->fetch_assoc();
$idaler=$resultado4['Max(id_alerta)'] + 1;



$sql = "INSERT INTO `alertas` (`id_alerta`, `id`, `display`) VALUES ('$idaler', '$idclie', '---')";


if (mysqli_query($conn, $sql)) {
     // echo "Tu cuenta se creo con exito";
     //echo "<a href=https://c-onlineweb.com/login/login.html#inicio>Volver</a>";
} else {
     echo "Error: " . $sql . "<br>" . mysqli_error($conn);
}





//$final= $id + 1;

echo $hoy.' '.$id.' '.$idclie.' '.$idhost.' '.$iddom;


?>