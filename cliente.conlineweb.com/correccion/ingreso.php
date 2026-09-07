<!DOCTYPE html>
<html>
<head><meta charset="gb18030">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="shortcut icon" href="https://c-onlineweb.com/imagenes/c-online_isotipo.png">
<title>Sistema Cliente/Ingreso  </title>



    <link rel="stylesheet" href="assets/vendors/iconly/bold.css">

    <link rel="stylesheet" href="assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/app.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.js"></script>
<script type="text/javascript"></script>

<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet" />
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

	<script src='https://www.google.com/recaptcha/api.js'></script>
<!--
<script src="//cdn.jsdelivr.net/npm/sweetalert2@10"></script>
<script src="sweetalert2.min.js"></script>
<link rel="stylesheet" href="sweetalert2.min.css">
-->





<a href='https://conlineweb.com' title="Volver al sitio web" onmousedown="sonido.play()"><div class="logo" onmousedown="sonido.play()"><img src="https://conlineweb.com/wp-content/uploads/2021/09/c-onlineweb-Logo-principal.png" style="max-width:100%;width:auto;height:auto;">   </div></a>





<style>



.logo {
    display:scroll;
        position:fixed;
        bottom:320px;
        top: 1%;
        left: 30px;
	width: 130px;
	height: 60px;
	border-radius: 150px 150px 150px 150px;
	background: ##000147;
	float: right;
	display: flex;
	color: #ffffff;
	justify-content: center;
	align-items: center;
	text-align: center;
  margin:0px;
  padding: 5px 0px 5px 0px;
  
  	font-size: 40px;
}

.logo {
  background-color: ##000000;
}


.time-right1 {
  float: right;
  color: #aaa;
}



input[type=text], input[type=password]
{
    

width: 100%;
height:50px;
border-radius: 5px;
padding: 5px 5px;
border: 2px solid #d5d5d5;
box-sizing: border-box
font-family: 'Roboto', sans-serif;
font-weight: 500;
font-size: 15px;
}   

 
    

body {
  font-family: Arial, Helvetica, sans-serif;
}

* {
  box-sizing: border-box;
}

/* style the container */
.container {
  position: relative;
  text-align: center;
  border-radius: 20px;
  background-color: #ffffff;
  padding: 5px;
} 

/* style inputs and link buttons */
input,
button {
  width: 100%;
  padding: 12px;
  border: none;
  border-radius: 4px;
  margin: 5px 0;
  opacity: 0.85;
  font-family: ubuntu;
  display: inline-block;
  font-size: 20px;
  line-height: 20px;
  text-decoration: none; /* remove underline from anchors */
}

input:hover,
.btn:hover {
  opacity: 1;
}

/* add appropriate colors to fb, twitter and google buttons */

/* style the submit button */
input[type=submit] {
  background-color: #4CAF50;
  color: white;
  cursor: pointer;
}

button[type=submit]  {
  background-color: #000147;
  color: white;

}

/* Two-column layout */
.col {
  float: left;
  
  width: 50%;
  margin: auto;
  padding: 0 5px;
  margin-top: 30px;
}

/* Clear floats after the columns */
.row:after {
  content: "";
  display: table;
  clear: both;
}

/* vertical line */
.vl {
  position: absolute;
  left: 50%;
  transform: translate(-50%);
  border: 2px solid #ddd;
  height: 95%;
}

/* text inside the vertical line */
.vl-innertext {
  position: absolute;
  top: 95%;
  transform: translate(-50%, -50%);
  background-color: #f1f1f1;
  border: 1px solid #ccc;
  border-radius: 30%;
  padding: 5px 0px 0px 0px;
}





/* Responsive layout - when the screen is less than 650px wide, make the two columns stack on top of each other instead of next to each other */
@media screen and (max-width: 650px) {
  .col {
    width: 100%;
    margin-top: 0;
    
  }
  /* hide the vertical line */
  .vl {
    display: none;
  }
  /* show the hidden text on small screens */
  .hide-md-lg {
    display: block;
    text-align: center;
  }


}


.principal-central {
  /* IMPORTANTE */
  text-align: center;
  width: 100%;
  
}

.sub-central {
  width: 321px;
  background-color: #f9f9f9;
  padding: 5px 10px 20px 10px;
  margin: 0px;
  font-size: 18px;
  font-weight: 100;
border-radius: 0px 50px 0px 50px;

  /* IMPORTANTE */
  display: inline-block;

}
</style>








<br><br><br>

<div class="container">
  <form method="post" action="iniciarSesion.php" >   
    <div class="row">
      
      <div class="vl">
        <span class="vl-innertext" onmousedown="sonido.play()"><a href='https://conlineweb.com' title="Volver al sitio web"><img
src="https://conlineweb.com/wp-content/uploads/2021/03/c-online-simbolo.png"
      width="48" height="48"></span></a>
      </div>

      <div class="col">
          
<div class="principal-central">
<div class="sub-central">
    
      
 <br><br><font size="5" style = "font-family:ubuntu; font-weight: 900;"> <span style="color: #000147;">
     <i class="bi bi-people-fill"></i>&nbsp;&nbsp;Iniciar sesi&oacute;n</span></font><br><br>

          
<input type="text" name="txusuario" placeholder="Usuario" id="usr"/><br>

<input type="password" name="txpassword" placeholder="Contraseña" id="myInput"/>
<label><input type="checkbox" onclick="myFunction()"> Mostrar Contraseña</label><br>

<div class="g-recaptcha" data-sitekey="6LfPDTQmAAAAALvmYsR12ZcGgcvRmK3eLTcKfj9l"></div><br>

<center>
  <button class="input" onmousedown="sonido.play()" type="button" id="enviar_btn">Ingresar</button>
</center><br>

</form>
      </div></div></div>


<script >
var sonido = new Audio();
sonido.src="https://conlineweb.com/wp-content/uploads/2021/11/sonido.mp3";

</script>      
      
<style>
    
    label {
    width: 13px;
    display: block;
    padding-left: 0px;
    text-indent: 5px;
    height: 100%;
    padding: 0;
    margin:0;
    vertical-align: bottom;
    position: relative;
    top: -1px;
    *overflow: hidden;
   
    
    font-family: 'Roboto', sans-serif;
    font-weight: 700;
    font-size: 10px;

}
 
.input[type=checkbox] {
   
}
    
</style>   
   
      
      
     <script>
function myFunction() {
  var x = document.getElementById("myInput");
  if (x.type === "password") {
    x.type = "text";
  } else {
    x.type = "password";
  }
}
</script>



      <div class="col">
        <div class="hide-md-lg">
          
        </div>
        
        
<style>
 
 @media screen and (max-width:600px) {
.ocultarcel{
display:none;
}





 
 </style>        
        
        <div class="ocultarcel">

<center><div style="width: 80%; background-color: #ffffff; margin: 0px; text-align: center;
  padding: 5% 10% 0% 10%; border-radius: 10px ">



        <img
src="https://conlineweb.com/wp-content/uploads/2021/09/SSLc-onlineweb.png"
      style="max-width:90%;width:auto;height:auto;"><br><br>
      
      <a href='https://conlineweb.com/mis-productos/' title="Ver todos los planes web" onmousedown="sonido.play()" style="text-decoration:none"><font size="2" style = "font-family:ubuntu; font-weight: 900;">&#191;No eres miembro a&uacute;n&#63; Elige un plan web y empieza ahora</font></a>
      
      </div></div></div></div></center><br><br>

<script>
$("#enviar_btn").on('click', function(e){
    e.preventDefault();

    var user = $('#usr').val();
    var pass = $('#myInput').val();
    var captcha = grecaptcha.getResponse(); // 👈 importante

    if (user === "" || pass === "") {
        Swal.fire({
            icon: 'warning',
            title: 'Ingrese usuario y contraseña'
        });
        return false;
    }

    if (captcha.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Verifique el CAPTCHA'
        });
        return false;
    }

    $.ajax({
        url: "iniciarSesion.php",
        type: "POST",
        dataType: "json",
        data: {
            txusuario: user,
            txpassword: pass,
            'g-recaptcha-response': captcha
        },
        success: function(data) {
            console.log("Respuesta del servidor:", data); // 👈 depuración

            if (data.status === "error") {
                Swal.fire({
                    icon: 'error',
                    title: 'Usuario o contraseña incorrectos'
                });
            } else {
                Swal.fire({
                    icon: 'success',
                    title: 'Bienvenido',
                    confirmButtonColor: 'green',
                    confirmButtonText: 'Aceptar'
                }).then((result) => {
                    if (result.value) {
                        if (parseInt(data.tipo) === 1) {
                            window.location.href = "https://adm.conlineweb.com/";
                        } else {
                            window.location.href = "index.php";
                        }
                    }
                });
            }
        },
        error: function(xhr, status, error) {
            console.error("AJAX error:", status, error);
        }
    });
});
</script>


<?php

include('footer2.php');

?>
</body>
</html>
    
    
<script language=JavaScript>
<!--

function inhabilitar(){
    alert ("Esta funcion esta inhabilitada.\n\n.")
    return false
}

document.oncontextmenu=inhabilitar

// -->
</script>










