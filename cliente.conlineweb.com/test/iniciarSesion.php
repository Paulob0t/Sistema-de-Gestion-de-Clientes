<?php
session_start();
include "../conn.php";

$nombre = $_POST['txusuario'];
$pass =  $_POST['txpassword'];
$captcha = $_POST['response'];

$secret = '6LfPDTQmAAAAAJXDg4UTYJbGonWOzsOYQ-QR5jP_';
$response = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=$secret&response=$captcha");
$arr = json_decode($response, TRUE);
if ($arr['success']){
    $_SESSION['id']=null;
    $_SESSION['login']=false;
    //$_SESSION['uid']=null;
    
    $query = mysqli_query($conn, "SELECT * FROM login WHERE usuario = '".$nombre."'and contrasena = '".$pass."'");
    $nr = mysqli_num_rows($query);
    $res=$query->fetch_assoc();
    
    if($nr == 1)
    {
    //    $query = mysqli_query($conn, "SELECT * FROM clientes WHERE usuario = '".$nombre."'and contraseña = '".$pass."'");
        $_SESSION['id']= session_id();
        $_SESSION['uid']=$res['id'];
        $_SESSION['login']=true;
        $data= $nr;
    //    header('Location:index.php');
    }
    else
    {
        $data="null";
    }
}
else
$data = "";

header("Content-type: application/json; charset=utf-8"); //inform the browser we're sending JSON data
echo json_encode($arr); //echoing JSON encoded data as the response for the AJAX call

?>