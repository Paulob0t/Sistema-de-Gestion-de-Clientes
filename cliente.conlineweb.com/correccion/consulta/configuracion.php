<?php

$dbhost = "localhost";
$dbuser = "conlinew_login";
$dbpass = "conlineweb15032020*@";
$dbname = "conlinew_prueba-clientes";

$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

if(!$conn){
    echo "alert('no hay conexion: '".$mysql_connect_error()."');";
}
?>