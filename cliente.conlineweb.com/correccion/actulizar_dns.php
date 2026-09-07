<?php
session_start();
if(isset($_SESSION['id']) != null && isset($_SESSION['login']) == true){
    include 'conn.php'; // Conexión a la base de datos
include ("dns.php");
    // Verifica si los datos del formulario están presentes
    if(isset($_POST['id']) && isset($_POST['ns1']) && isset($_POST['ns2']) && isset($_POST['ns3']) && isset($_POST['ns4']) && isset($_POST['ns5']) && isset($_POST['ns6'])){
        
        // Obtener los datos del formulario
        $id = $_POST['id'];
        $ns1 = $_POST['ns1'];
        $ns2 = $_POST['ns2'];
        $ns3 = $_POST['ns3'];
        $ns4 = $_POST['ns4'];
        $ns5 = $_POST['ns5'];
        $ns6 = $_POST['ns6'];

        // Realizar la actualización en la base de datos
        $query = "UPDATE dominios SET ns1='$ns1', ns2='$ns2', ns3='$ns3', ns4='$ns4', ns5='$ns5', ns6='$ns6' WHERE id_dominio='$id'";
        $result = mysqli_query($conn, $query);

        if($result){
            // Mostrar alerta de éxito
            echo "<script>
                Swal.fire({
                    title: 'Registro exitoso!',
                    icon: 'success',
                    text: 'Una vez realizada la configuración, la propagación de los registros DNS puede tardar hasta 72 horas, dependiendo del plan de hosting contratado. Su sitio estará disponible en línea una vez completado este proceso.',
                    showConfirmButton: true,
                    confirmButtonText: 'Aceptar',
                    confirmButtonColor: 'green',
                    allowOutsideClick: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href='dns.php';
                    }
                });
            </script>";
        } else {
            // Mostrar alerta de error
            echo "<script>
                Swal.fire({
                    title: 'Error al actualizar',
                    icon: 'error',
                    text: 'Ocurrió un error al intentar actualizar los DNS. Por favor, intente nuevamente.',
                    showConfirmButton: true,
                    confirmButtonText: 'Aceptar',
                    confirmButtonColor: 'red',
                    allowOutsideClick: false
                });
            </script>";
        }
    } else {
        // Mostrar alerta si faltan datos
        echo "<script>
            Swal.fire({
                title: 'Datos incompletos',
                icon: 'warning',
                text: 'Faltan datos para actualizar los DNS. Por favor, complete todos los campos.',
                showConfirmButton: true,
                confirmButtonText: 'Aceptar',
                confirmButtonColor: 'orange',
                allowOutsideClick: false
            });
        </script>";
    }

} else {
    header("Location: ingreso.php");
}
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
