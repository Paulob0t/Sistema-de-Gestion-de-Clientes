<?php
/**
 * Helper para templates de correo de HostPro
 */

/**
 * Template para correo de pago pendiente (HostPro)
 * 
 * @param string $nombre_cliente Nombre del cliente
 * @param string $concepto Concepto del pago
 * @param string $monto Monto formateado (ej: "1,500.00")
 * @param string $moneda Moneda (MXN, USD, etc)
 * @param string $fecha_vencimiento Fecha límite formateada
 * @param string $url_pago URL de la sesión de Stripe
 * @param string $tipo_servicio Tipo de servicio (hosting, dominio, pago, etc)
 * @return string HTML del correo
 */
function hostpro_template_pago_pendiente($nombre_cliente, $concepto, $monto, $moneda, $fecha_vencimiento, $url_pago, $tipo_servicio = 'pago') {
    $titulo = "Pago Pendiente - $concepto";
    
    $mensaje = "
<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>$titulo</title>
    <style type='text/css'>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap');
        body { margin:0; padding:0; background:#04060f; font-family:'Montserrat',Arial,Helvetica,sans-serif; color:#e8edf8; -webkit-text-size-adjust:100%; }
        body, table, td, p, a, h1, h2, h3, h4, span, strong, li { font-family:'Montserrat',Arial,Helvetica,sans-serif !important; }
        .email-wrapper { width:100%; background:#04060f; padding:32px 16px; box-sizing:border-box; }
        .email-card { max-width:600px; margin:0 auto; background:#0c1225; border-radius:20px; overflow:hidden; border:1px solid #1a2545; box-shadow:0 24px 64px rgba(0,229,255,0.07), 0 8px 32px rgba(0,0,0,0.7); }
        .email-header { background:linear-gradient(160deg,#0b1a3e 0%,#060e26 100%); text-align:center; padding:40px 28px 36px; border-bottom:1px solid rgba(0,229,255,0.12); }
        .email-logo img { max-width:210px; height:auto; display:block; margin:0 auto; }
        .email-tagline { margin:10px 0 0; color:#7a8aaa; font-size:12px; letter-spacing:2.5px; text-transform:uppercase; }
        .status-badge { display:inline-block; background:rgba(255,165,0,0.1); border:1px solid rgba(255,165,0,0.25); border-radius:999px; padding:6px 18px; font-size:13px; color:#ffa500; font-weight:700; letter-spacing:0.5px; margin-top:12px; }
        .email-content { padding:36px 32px; line-height:1.75; font-size:15px; color:#b8c4d8; }
        .email-content p { margin:0 0 16px; }
        .email-content strong { color:#e8edf8; }
        .info-card { background:rgba(255,255,255,0.03); border:1px solid #1a2545; border-radius:14px; padding:24px; margin:24px 0; }
        .info-card h3 { margin:0 0 14px; color:#e8edf8; font-size:17px; font-weight:700; }
        .info-card ul { margin:0; padding-left:20px; color:#9fb1d4; line-height:2.1; font-size:14px; list-style:none; }
        .info-card ul li { margin:8px 0; position:relative; padding-left:8px; }
        .info-card ul li strong { color:#e8edf8; font-size:14px; }
        .cta-wrap { text-align:center; margin:28px 0; }
        .cta-btn { display:inline-block; background:#00e5ff; color:#000000 !important; text-decoration:none !important; padding:16px 44px; border-radius:999px; font-weight:900; font-size:16px; letter-spacing:0.5px; border:2px solid #00b8d9; }
        .notice { background:rgba(0,229,255,0.05); border:1px solid rgba(0,229,255,0.18); border-radius:12px; padding:18px 20px; margin:22px 0; font-size:14px; color:#9fb1d4; line-height:1.7; }
        .notice a { color:#00e5ff; text-decoration:none; }
        .bank-box { background:rgba(168,255,62,0.06); border:1px solid rgba(168,255,62,0.22); border-radius:12px; padding:20px; margin:22px 0; }
        .bank-box h4 { margin:0 0 14px; color:#a8ff3e; font-size:16px; font-weight:700; }
        .bank-box ul { margin:0; padding:0; list-style:none; color:#b8c4d8; font-size:13px; line-height:2; }
        .bank-box ul li strong { color:#e8edf8; }
        .bank-box .hint { margin:14px 0 0; font-size:12px; color:#7a8aaa; font-style:italic; }
        .email-footer { background:#080d1c; text-align:center; padding:26px; border-top:1px solid #1a2545; font-size:12px; color:#7a8aaa; line-height:1.8; }
        .email-footer strong { color:#b8c4d8; font-size:13px; }
        @media (max-width: 600px) { 
            .email-content { padding:24px 20px; } 
            .info-card { padding:18px; }
        }
    </style>
</head>
<body>
    <table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0' class='email-wrapper'>
        <tr>
            <td align='center'>
                <table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0' class='email-card'>
                    <tr>
                        <td class='email-header'>
                            <div class='email-logo'>
                                <img src='https://hostpro.com.mx/cliente/images/Gemini_Generated_Image_s3qiu6s3qiu6s3qi__1_-removebg-preview.png' alt='HostingPro' width='210' border='0'>
                            </div>
                            <p class='email-tagline'>Pago Pendiente</p>
                            <span class='status-badge'>⏳ Requiere atención</span>
                        </td>
                    </tr>
                    <tr>
                        <td class='email-content'>
                            <p>Hola <strong>$nombre_cliente</strong>,</p>
                            
                            <p>Te escribimos para recordarte que tienes un <strong>pago pendiente</strong> que requiere tu atención. A continuación encontrarás los detalles y opciones de pago:</p>
                            
                            <div class='info-card'>
                                <h3>📋 Detalles del Pago</h3>
                                <ul>
                                    <li><strong>Concepto:</strong> $concepto</li>
                                    <li><strong>Monto:</strong> $$monto $moneda</li>
                                    <li><strong>Fecha límite:</strong> $fecha_vencimiento</li>
                                </ul>
                            </div>
                            
                            <h4 style='color:#00e5ff;font-size:16px;margin:28px 0 16px;'>💳 Opción 1: Pago con Tarjeta</h4>
                            <p>Realiza tu pago de forma <strong>rápida y segura</strong> con tarjeta de crédito o débito a través de Stripe:</p>
                            
                            <div class='cta-wrap'>
                                <a class='cta-btn' style='color:#000000;text-decoration:none;' href='$url_pago'>Pagar Ahora</a>
                            </div>
                            
                            <div class='notice'>
                                📎 Si el botón no funciona, copia este enlace en tu navegador:<br>
                                <small style='word-break:break-all;color:#00e5ff;'>$url_pago</small>
                            </div>
                            
                            <div class='bank-box'>
                                <h4>🏦 Opción 2: Transferencia Bancaria</h4>
                                <ul>
                                    <li><strong>Titular:</strong> Jose Antonio Martinez Karam</li>
                                    <li><strong>Banco:</strong> Santander</li>
                                    <li><strong>Cuenta:</strong> 60622161632</li>
                                    <li><strong>CLABE:</strong> 014225606221616325</li>
                                    <li><strong>Referencia:</strong> $concepto</li>
                                </ul>
                                <p class='hint'>💡 Envía tu comprobante por WhatsApp al <strong>477 118 1285</strong> para validar tu pago.</p>
                            </div>
                            
                            <div class='notice'>
                                <strong>📞 ¿Necesitas ayuda?</strong><br>
                                WhatsApp: <strong>477 118 1285</strong><br>
                                Email: <a href='mailto:info@conlineweb.com'>info@conlineweb.com</a>
                            </div>
                            
                            <p style='text-align:center;margin-top:30px;font-weight:600;'>¡Gracias por confiar en <strong>HostingPro</strong>!</p>
                        </td>
                    </tr>
                    <tr>
                        <td class='email-footer'>
                            <strong>Equipo HostingPro</strong><br>
                            &copy; " . date('Y') . " HostingPro &mdash; Este es un correo transaccional relacionado con tu servicio.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>";
    
    return $mensaje;
}

/**
 * Template para renovación de hosting (HostPro)
 */
function hostpro_template_renovacion_hosting($nombre_cliente, $servicio, $fecha_vencimiento, $monto, $moneda, $url_pago) {
    return hostpro_template_pago_pendiente(
        $nombre_cliente, 
        "Renovación de Hosting: $servicio", 
        $monto, 
        $moneda, 
        $fecha_vencimiento, 
        $url_pago, 
        'hosting'
    );
}

/**
 * Template para renovación de dominio (HostPro)
 */
function hostpro_template_renovacion_dominio($nombre_cliente, $dominio, $fecha_vencimiento, $monto, $moneda, $url_pago) {
    return hostpro_template_pago_pendiente(
        $nombre_cliente, 
        "Renovación de Dominio: $dominio", 
        $monto, 
        $moneda, 
        $fecha_vencimiento, 
        $url_pago, 
        'dominio'
    );
}
?>
