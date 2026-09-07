<?php
// === MODIFICAR EL LOGIN ORIGINAL ===
// En cliente.conlineweb.com - después de session_start()

session_start();

// Configurar la cookie de sesión para que funcione en todos los subdominios
ini_set('session.cookie_domain', '.conlineweb.com');
ini_set('session.cookie_path', '/');
ini_set('session.cookie_secure', true); // Solo si usas HTTPS
ini_set('session.cookie_httponly', true);

include "conn.php";

$nombre = $_POST['txusuario'];
$pass = md5($_POST['txpassword']);
$captcha = $_POST['g-recaptcha-response'];

$secret = '6LccysAdAAAAABx9GvXHFtpORAORoXdjRiI_6gdQ';

$_SESSION['id'] = null;
$_SESSION['login'] = false;

$query = mysqli_query($conn, "SELECT * FROM login WHERE usuario = '$nombre' AND contrasena = '$pass'");
$nr = mysqli_num_rows($query);
$res = $query->fetch_assoc();

if ($nr == 1) {
    $_SESSION['id'] = session_id();
    $_SESSION['uid'] = $res['id'];
    $_SESSION['login'] = true; // CORREGIDO: era 'loggin' en tu validador
    $_SESSION['tipo'] = $res['id_tipo_usuario'];
    $_SESSION['last_activity'] = time();
    $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];

    // Crear cookie adicional con datos de sesión para compartir entre dominios
    setcookie('user_session_data', json_encode([
        'uid' => $res['id'],
        'tipo' => $res['id_tipo_usuario'],
        'login' => true,
        'timestamp' => time()
    ]), time() + 3600, '/', '.conlineweb.com', true, true);

    $data = [
        'status' => 'ok',
        'tipo' => intval($res['id_tipo_usuario'])
    ];
} else {
    $response = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=$secret&response=$captcha");
    $arr = json_decode($response, TRUE);
    
    $data = [
        'status' => 'error'
    ];
}

header("Content-type: application/json; charset=utf-8");
echo json_encode($data);
?>