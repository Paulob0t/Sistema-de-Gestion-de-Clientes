<?php
    session_start();
//    mysqli_close($conn);
    session_unset();
    session_destroy();
    header("Location: ingreso.php");   
?>