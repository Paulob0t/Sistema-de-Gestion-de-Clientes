<?php
require_once __DIR__.'/config_link.php';

/**
 * Genera un token firmado para vista de tickets.
 * @param int $idCliente
 * @param int|null $idProyecto
 * @param int|null $expires Timestamp de expiraci¨®n (optional). Si null se aplica NOW + LINK_MAX_AGE.
 * @param string|null $nombreMSJ Opcional: nombreMSJ a fijar en el enlace
 * @return string token listo para incluir en URL
 */
function generar_link_token($idCliente, $idProyecto=null, $expires=null, $nombreMSJ=null){
    $idCliente = (int)$idCliente;
    $idProyecto = $idProyecto !== null ? (int)$idProyecto : 0;
    if($expires === null){
        $expires = time() + LINK_MAX_AGE; // default window
    }
    $payloadParts = [$idCliente, $idProyecto, $expires];
    if($nombreMSJ !== null && $nombreMSJ !== ''){
        // Evitar romper el delimitador: codificamos URL-safe
        $payloadParts[] = rawurlencode($nombreMSJ);
    }
    $payload = implode('|', $payloadParts);
    $sig = hash_hmac('sha256', $payload, LINK_SECRET);
    // Compactar (payload|sig) base64 url safe
    $token = rtrim(strtr(base64_encode($payload.'|'.$sig), '+/', '-_'), '=');
    return $token;
}

/**
 * Valida token y devuelve array con claves: valid, id_cliente, id_proyecto, expired, error
 */
function validar_link_token($token){
    $res = [
        'valid' => false,
        'id_cliente' => 0,
        'id_proyecto' => 0,
        'expired' => false,
        'error' => null,
        'nombreMSJ' => '',
    ];
    if(!$token){ $res['error']='missing_token'; return $res; }
    $raw = base64_decode(strtr($token, '-_', '+/')); // base64 url to normal
    if(!$raw || strpos($raw,'|')===false){ $res['error']='bad_format'; return $res; }
    $parts = explode('|', $raw);
    if(count($parts) !== 4 && count($parts) !== 5){ $res['error']='bad_parts'; return $res; }
    if(count($parts) === 4){
        list($idCliente,$idProyecto,$expires,$sig) = $parts;
        $checkPayload = $idCliente.'|'.$idProyecto.'|'.$expires;
    } else {
        list($idCliente,$idProyecto,$expires,$nombreEnc,$sig) = $parts;
        $checkPayload = $idCliente.'|'.$idProyecto.'|'.$expires.'|'.$nombreEnc;
        // Guardar decodificado (manejar errores suavemente)
        $decoded = $nombreEnc;
        if($decoded !== ''){
            try { $decoded = rawurldecode($decoded); } catch(Exception $e){ /* ignore */ }
        }
        $res['nombreMSJ'] = $decoded;
    }
    $expected = hash_hmac('sha256', $checkPayload, LINK_SECRET);
    if(!hash_equals($expected,$sig)){ $res['error']='invalid_signature'; return $res; }
    $now = time();
    if(!ctype_digit($expires)){ $res['error']='bad_expiry'; return $res; }
    if($now > (int)$expires){ $res['expired']=true; $res['error']='expired'; return $res; }
    $res['valid']=true;
    $res['id_cliente']=(int)$idCliente;
    $res['id_proyecto']=(int)$idProyecto;
    return $res;
}
