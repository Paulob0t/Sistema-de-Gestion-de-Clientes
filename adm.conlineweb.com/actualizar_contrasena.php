<?php
header('Content-Type: application/json');
include_once 'conn.php';

$response = ['success' => false, 'message' => ''];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contrasena = $_POST['contrasena'] ?? '';
    $confirmar = $_POST['confirmPassword'] ?? '';
    $uid = $_POST['id'] ?? '';

    // Validaciones básicas
    if (strlen($contrasena) < 8) {
        $response['message'] = 'La contraseña debe tener al menos 8 caracteres';
        echo json_encode($response);
        exit;
    }

    if ($contrasena !== $confirmar) {
        $response['message'] = 'Las contraseñas no coinciden';
        echo json_encode($response);
        exit;
    }

    // Guardar contraseña encriptada y en texto plano
    $contrasena_md5 = md5($contrasena);
    $contrasena_normal = $contrasena;

    try {
        $stmt = $conn->prepare("UPDATE login SET contrasena = ?, contrasena_normal = ?, cambio_contrasena = 1 WHERE id = ?");
        $stmt->bind_param("ssi", $contrasena_md5, $contrasena_normal, $uid);
        $stmt->execute();        // Verificar si el usuario existe primero
        $check_user = $conn->prepare("SELECT id FROM login WHERE id = ?");
        $check_user->bind_param("i", $uid);
        $check_user->execute();
        $check_user->store_result();
        
        if ($check_user->num_rows == 0) {
            $response['message'] = "Error: No se encontró el usuario con ID: $uid";
        } else {
            if ($stmt->affected_rows > 0) {
                $response['success'] = true;
                $response['message'] = 'Contraseña actualizada correctamente';
            } else {
                // Verificar si la contraseña es la misma que ya está guardada
                $check_pass = $conn->prepare("SELECT contrasena FROM login WHERE id = ? AND contrasena = ?");
                $check_pass->bind_param("is", $uid, $contrasena_md5);
                $check_pass->execute();
                $check_pass->store_result();
                
                if ($check_pass->num_rows > 0) {
                    $response['message'] = 'La contraseña nueva es igual a la actual. Por favor, elige una contraseña diferente.';
                } else {
                    $response['message'] = "No se actualizó la contraseña. Detalles: " .
                                         "ID usuario: $uid, " . 
                                         "Error MySQL: " . $stmt->error;
                }
            }
        }
    } catch (Exception $e) {
        $response['message'] = 'Error al actualizar contraseña: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Método no permitido';
}

echo json_encode($response);
?>
