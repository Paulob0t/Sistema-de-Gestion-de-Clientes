<?php
include 'conn.php';
ini_set('display_errors', 1);  // Mostrar errores en pantalla
ini_set('display_startup_errors', 1);  // Mostrar errores de inicio
error_reporting(E_ALL);  // Reportar todos los tipos de errores

if(isset($_POST['estados_id_estado'])) {
  $estados_id_estado = $_POST['estados_id_estado'];
  $sql = "SELECT * FROM municipios WHERE estado = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $estados_id_estado);
  $stmt->execute();
  $result = $stmt->get_result();
  
  $output = '<option value="" selected disabled>Selecciona un municipio</option>';
  while($row = $result->fetch_assoc()) {
    $output .= '<option value="'.$row['id_municipio'].'">'.$row['nombre_municipio'].'</option>';
  }
  echo $output;
}
?>