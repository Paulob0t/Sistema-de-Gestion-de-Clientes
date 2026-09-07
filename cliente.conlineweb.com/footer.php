<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#000147">
        <meta name="description" content="" />
        <meta name="author" content="" />
       
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/js/all.min.js" crossorigin="anonymous"></script>
    </head>
    

   <!-- Ajustes móviles transversales. Van al final a propósito: así ganan la
        cascada sobre el CSS que cada página carga en su propia cabecera. -->
   <link rel="stylesheet" href="assets/css/cliente-mobile.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/cliente-mobile.css') ?>">

   <script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/cliente-mobile.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/cliente-mobile.js') ?>" defer></script>
                   



<script >
var sonido = new Audio();
sonido.src="https://conlineweb.com/wp-content/uploads/2021/11/sonido.mp3";

</script>



	<!--<button class="contact100-form-btn" id="enviar" onmousedown="sonido.play()">
						<i class="fab fa-whatsapp"></i>
					</button>-->



<a href="javascript:window.open('https://www.tidio.com/talk/29qawnoe8xwfekvz1pem3m2ey72chtqt','','width=800,height=650');void(null)" style="display:none" id="tidioLegacyLink"></a>
<?php include __DIR__ . '/includes/chat_widget.php'; ?>
                </footer>
                
</html>