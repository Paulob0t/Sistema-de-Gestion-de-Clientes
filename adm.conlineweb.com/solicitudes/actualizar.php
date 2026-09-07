<?php
// Actualizar estado de ticket (AJAX-friendly)
// Si viene ajax=1 o X-Requested-With, responde JSON y NO redirige.
// Soluciona el problema de CORS provocado por el redirect a index.php.

require_once __DIR__.'/../auth_middleware.php'; // valida sesión (tipos 1-4 aceptados salvo restricciones específicas)
require_once __DIR__.'/bootstrap_conexion.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$isAjax = (
	(isset($_POST['ajax']) && $_POST['ajax'] == '1') ||
	(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
);

function jsonResponse($arr, $status=200){
	http_response_code($status);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode($arr, JSON_UNESCAPED_UNICODE);
	exit;
}

// Validar sesión mínima
if (empty($_SESSION['uid'])) {
	if ($isAjax) jsonResponse(['ok'=>false,'error'=>'No autenticado'],401);
	header('Location: index.php');
	exit;
}

$userType = intval($_SESSION['tipo'] ?? 0); // 1=admin,2=solicitudes,3=agente,4=empresa
// Restringir empresas (4) a no poder cambiar estado aquí
if ($userType === 4) {
	if ($isAjax) jsonResponse(['ok'=>false,'error'=>'Permiso denegado'],403);
	header('Location: index.php?error=permiso');
	exit;
}

// Entradas
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$estado = isset($_POST['estado']) ? trim($_POST['estado']) : '';

if ($id <= 0 || $estado === '') {
	if ($isAjax) jsonResponse(['ok'=>false,'error'=>'Parámetros inválidos'],422);
	header('Location: index.php?error=param');
	exit;
}

// Validar estado permitido
$allowedEstados = ['Pendiente','En Proceso','Finalizado'];
if (!in_array($estado, $allowedEstados, true)) {
	if ($isAjax) jsonResponse(['ok'=>false,'error'=>'Estado no permitido'],422);
	header('Location: index.php?error=estado');
	exit;
}

// (Opcional) Si es tipo 3 (agente/desarrollador) podríamos validar que sea el asignado
if ($userType === 3) {
	if ($stmtChk = $conexion->prepare('SELECT usuario_asignado FROM solicitudes WHERE id=?')) {
		$stmtChk->bind_param('i',$id);
		$stmtChk->execute();
		$stmtChk->bind_result($asignado);
		if ($stmtChk->fetch()) {
			// Si hay un usuario asignado y no coincide, podríamos bloquear.
			// Descomentar si deseas estricta propiedad:
			// if (!empty($asignado) && (int)$asignado !== (int)($_SESSION['agente_id'] ?? 0)) {
			//     if ($isAjax) jsonResponse(['ok'=>false,'error'=>'No autorizado para este ticket'],403);
			//     header('Location: index.php?error=ownership'); exit;
			// }
		}
		$stmtChk->close();
	}
}

$fechaTermina = null;
if ($estado === 'Finalizado') {
	$fechaTermina = date('Y-m-d H:i:s');
	$stmt = $conexion->prepare('UPDATE solicitudes SET estado=?, fecha_termina=? WHERE id=?');
	if(!$stmt){ if($isAjax) jsonResponse(['ok'=>false,'error'=>'Error prepare']); header('Location: index.php?error=sql'); exit; }
	$stmt->bind_param('ssi',$estado,$fechaTermina,$id);
} else {
	$stmt = $conexion->prepare('UPDATE solicitudes SET estado=? WHERE id=?');
	if(!$stmt){ if($isAjax) jsonResponse(['ok'=>false,'error'=>'Error prepare']); header('Location: index.php?error=sql'); exit; }
	$stmt->bind_param('si',$estado,$id);
}

if(!$stmt->execute()){
	$err = $stmt->error;
	$stmt->close();
	if ($isAjax) jsonResponse(['ok'=>false,'error'=>'Error al actualizar','detalle'=>$err],500);
	header('Location: index.php?error=update');
	exit;
}
$stmt->close();

// Enviar correo de finalización al jefe/admin si el estado es "Finalizado"
$emailEnviado = false;
if ($estado === 'Finalizado') {
	@file_put_contents(__DIR__.'/email_debug.log', date('c')." [DEBUG] ⭐ INICIO FINALIZACIÓN - Ticket #$id - Usuario tipo: $userType\n", FILE_APPEND);
	@file_put_contents(__DIR__.'/email_debug.log', date('c')." [DEBUG] Stack trace: " . print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3), true) . "\n", FILE_APPEND);
	try {
		require_once __DIR__.'/enviar_correo_finalizacion_jefe.php';
		@file_put_contents(__DIR__.'/email_debug.log', date('c')." [DEBUG] Archivo enviar_correo_finalizacion_jefe.php cargado correctamente\n", FILE_APPEND);
		
		$emailEnviado = enviar_correo_finalizacion($conexion, $id);
		@file_put_contents(__DIR__.'/email_debug.log', date('c')." [DEBUG] Función enviar_correo_finalizacion retornó: " . ($emailEnviado ? 'true' : 'false') . "\n", FILE_APPEND);
		
		if (!$emailEnviado) {
			// Log del error sin interrumpir el proceso
			$errorMsg = $GLOBALS['ENVIAR_CORREO_FIN_ERROR'] ?? 'Error desconocido al enviar correo de finalización';
			@file_put_contents(__DIR__.'/email_errors.log', date('c')." [FINALIZACION] Error enviando correo a jefe/admin para ticket #$id: $errorMsg\n", FILE_APPEND);
			@file_put_contents(__DIR__.'/email_debug.log', date('c')." [DEBUG] Error: $errorMsg\n", FILE_APPEND);
		} else {
			@file_put_contents(__DIR__.'/email_finalizacion.log', date('c')." [FINALIZACION] Correo enviado exitosamente a jefe/admin para ticket #$id\n", FILE_APPEND);
			@file_put_contents(__DIR__.'/email_debug.log', date('c')." [DEBUG] ¡Correos enviados exitosamente!\n", FILE_APPEND);
		}
	} catch (Exception $e) {
		// Log del error sin interrumpir el proceso
		@file_put_contents(__DIR__.'/email_errors.log', date('c')." [FINALIZACION] Exception enviando correo a jefe/admin para ticket #$id: ".$e->getMessage()."\n", FILE_APPEND);
		@file_put_contents(__DIR__.'/email_debug.log', date('c')." [DEBUG] Exception: ".$e->getMessage()."\n", FILE_APPEND);
		$emailEnviado = false;
	}
}

if ($isAjax) {
	jsonResponse([
		'ok'=>true,
		'id'=>$id,
		'estado'=>$estado,
		'fecha_termina'=>$fechaTermina,
		'email_enviado'=>$emailEnviado
	]);
} else {
	// Redirección clásica (fallback)
	header('Location: index.php?updated=1');
}
?>
