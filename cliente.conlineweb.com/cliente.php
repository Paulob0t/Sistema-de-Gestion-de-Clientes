<?php
require_once __DIR__ . '/includes/cliente_session.php';
cliente_start_session();
if (cliente_is_logged_in()) {
    include 'conn.php';
    require_once __DIR__ . '/includes/cw_constancia_fiscal.php';
    $usrid = $_SESSION['uid'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#000147">
    <?php require_once __DIR__ . '/includes/cliente_head_meta.php'; $clientePageTitle = 'Mis datos'; ?>
    <title><?= htmlspecialchars(cliente_document_title('Mis datos'), ENT_QUOTES, 'UTF-8') ?></title>
    <?= cliente_favicon_markup() ?>
    <style>
        :root {
            --sidebar-width: 300px;
            --header-height: 70px;
            --primary-color: #000147;
            --info-color: #324f9a;
            --secondary-color: #10b981;
            --dark-color: #1e293b;
            --light-color: #f8fafc;
            --gray-light: #e2e8f0;
        }
        
        /* Estilos específicos para la página de cliente */
        .form-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
            border: 1px solid var(--gray-light);
            padding: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .form-control {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid var(--gray-light);
            border-radius: 5px;
            font-size: 0.95rem;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(0, 21, 71, 0.1);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: background-color 0.2s;
        }
        
        .btn-primary:hover {
            background-color: #1a1a6e;
        }
        
        .alert-verify {
            background-color: #ffecb3;
            color: #333;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .alert-verify strong {
            color: var(--primary-color);
        }
        
        /* Estilos para campos de facturación */
        .facturacion-section {
            border: 1px solid var(--gray-light);
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .facturacion-title {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .facturacion-field-label {
            display: block;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 0.4rem;
        }

        .facturacion-toggle {
            display: flex;
            align-items: center;
            gap: 14px;
            width: 100%;
            min-height: 46px;
            margin: 0;
            padding: 10px 14px;
            border: 1px solid var(--gray-light, #e2e8f0);
            border-radius: 12px;
            background: #f8fafc;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
            user-select: none;
        }
        .facturacion-toggle:hover {
            border-color: #c7d2fe;
            background: #f5f7ff;
        }
        .facturacion-toggle.is-on {
            border-color: #a5b4fc;
            background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
        }
        .facturacion-toggle__switch {
            position: relative;
            width: 44px;
            height: 26px;
            flex-shrink: 0;
            border-radius: 999px;
            background: #cbd5e1;
            transition: background 0.2s;
        }
        .facturacion-toggle.is-on .facturacion-toggle__switch {
            background: var(--primary-color, #000147);
        }
        .facturacion-toggle__switch::after {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #fff;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.2);
            transition: transform 0.2s;
        }
        .facturacion-toggle.is-on .facturacion-toggle__switch::after {
            transform: translateX(18px);
        }
        .facturacion-toggle__copy {
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 0;
        }
        .facturacion-toggle__title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
        }
        .facturacion-toggle__title i {
            color: var(--primary-color, #000147);
        }
        .facturacion-toggle__hint {
            font-size: 0.75rem;
            font-weight: 500;
            color: #64748b;
            line-height: 1.3;
        }
        .facturacion-toggle input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
            width: 0;
            height: 0;
        }

        .constancia-preview {
            margin-top: 0.75rem;
            border: 1px solid var(--gray-light);
            border-radius: 10px;
            background: #f8fafc;
            overflow: hidden;
        }

        .constancia-preview__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.65rem 0.85rem;
            background: #fff;
            border-bottom: 1px solid var(--gray-light);
            font-size: 0.82rem;
        }

        .constancia-preview__name {
            font-weight: 600;
            color: var(--dark-color);
            word-break: break-all;
        }

        .constancia-preview__link {
            color: var(--info-color);
            text-decoration: none;
            font-weight: 600;
            white-space: nowrap;
        }

        .constancia-preview__link:hover {
            text-decoration: underline;
        }

        .constancia-preview__body {
            padding: 0.75rem;
            min-height: 120px;
        }

        .constancia-preview__body iframe {
            width: 100%;
            height: 320px;
            border: 1px solid var(--gray-light);
            border-radius: 8px;
            background: #fff;
        }

        .constancia-preview__body img {
            max-width: 100%;
            max-height: 320px;
            border-radius: 8px;
            border: 1px solid var(--gray-light);
            display: block;
            margin: 0 auto;
        }

        .constancia-preview__empty {
            margin: 0;
            font-size: 0.85rem;
            color: #64748b;
        }

        .constancia-preview--new {
            display: none;
        }

        .constancia-preview--new.is-visible {
            display: block;
        }
        
        .login {
            display: none;
        }
        
        /* MEJORAS RESPONSIVE PARA MÓVIL - Mismos ajustes que la otra vista */
        @media (max-width: 768px) {
            /* Contenedor principal al 100% */
            .page-content {
                padding: 0 5px !important;
                margin: 0 auto !important;
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
            }
            
            /* Header más ancho */
            .header-section {
                padding: 15px 12px !important;
                margin: 0 !important;
                width: 100% !important;
                box-sizing: border-box !important;
            }
            
            .header-content {
                gap: 12px !important;
            }
            
            /* Form container más ancho */
            .form-container {
                margin: 10px 0 !important;
                padding: 15px 12px !important;
                width: 100% !important;
                box-sizing: border-box !important;
                max-width: 100% !important;
            }
            
            /* Grid mejor distribuido */
            .row.g-3 {
                margin: 0 -3px !important;
                width: 100% !important;
            }
            
            .col-md-6 {
                padding: 0 3px !important;
                margin-bottom: 6px !important;
                flex: 0 0 100% !important;
                max-width: 100% !important;
            }
            
            /* Botones más accesibles */
            .btn-primary {
                width: 100% !important;
                min-height: 50px !important;
                font-size: 16px !important;
                padding: 12px 5px !important;
                margin: 0 !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 6px !important;
            }
            
            /* Alert verify mejor distribuido */
            .alert-verify {
                flex-direction: column !important;
                gap: 12px !important;
                text-align: center !important;
                padding: 12px !important;
                margin: 10px 0 !important;
            }
            
            /* Facturación section más ancha */
            .facturacion-section {
                margin: 15px 0 !important;
                padding: 15px 12px !important;
                width: 100% !important;
                box-sizing: border-box !important;
            }
            
            /* Textos mejor ajustados */
            .header-title {
                font-size: 1.4rem !important;
            }
            
            .header-subtitle {
                font-size: 0.95rem !important;
            }
            
            .info-title, .facturacion-title {
                font-size: 1.2rem !important;
            }
            
            .form-label {
                font-size: 14px !important;
            }
            
            .form-control {
                font-size: 16px !important; /* Mejor para móvil */
                min-height: 44px !important; /* Mejor tacto */
            }
            
            /* Asegurar que todo use el ancho completo */
            .col-12 {
                padding: 0 !important;
                width: 100% !important;
                margin: 0 !important;
            }
            
            #app {
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
            }
            
            body {
                margin: 0 !important;
                padding: 0 !important;
            }
        }
        
        /* Móviles muy pequeños - ajustes adicionales */
        @media (max-width: 480px) {
            .page-content {
                padding: 0 3px !important;
            }
            
            .form-container {
                padding: 12px 10px !important;
                margin: 8px 0 !important;
            }
            
            .header-section {
                padding: 12px 10px !important;
            }
            
            .facturacion-section {
                padding: 12px 10px !important;
            }
            
            .btn-primary {
                min-height: 48px !important;
                font-size: 15px !important;
                padding: 10px 4px !important;
            }
            
            .header-title {
                font-size: 1.3rem !important;
            }
            
            .col-md-6 {
                padding: 0 2px !important;
                margin-bottom: 4px !important;
            }
        }
        
        /* Responsive original para tablets y desktop.
           El posicionamiento del sidebar lo controla cliente-menu.css
           (drawer con transform); aquí solo se ajusta el contenido. */
        @media (max-width: 992px) {
            .header-section {
                padding: 1.5rem;
                margin: 1rem 0;
            }
            
            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .header-icon-container {
                padding: 0.8rem;
            }
            
            .header-icon {
                font-size: 2rem;
            }
            
            .header-title {
                font-size: 1.5rem;
            }
            
            .header-subtitle {
                font-size: 0.95rem;
            }
            
            .form-container {
                padding: 1rem;
            }
        }
        
        @media (max-width: 576px) {
            .header-section {
                padding: 1.25rem;
            }
            
            .header-title {
                font-size: 1.3rem;
            }
        }
    </style>
</head>

<body>
    <div id="app">
        <?php include 'menu.php'; ?>
        
        <div class="page-content">
            <!-- Cabecera con icono a la izquierda -->
            <div class="header-section">
                <div class="header-content">
                    <div class="header-icon-container">
                        <i class="bi bi-person-circle header-icon"></i>
                    </div>
                    <div class="header-text">
                        <h1 class="header-title">Mi Cuenta</h1>
                        <p class="header-subtitle">Administra tus datos personales y de facturación</p>
                    </div>
                </div>
            </div>
            
            <div class="form-container">
                <?php
                $query = mysqli_query($conn, "SELECT * FROM clientes WHERE id=" . $usrid);
                while ($mostrar = mysqli_fetch_array($query)) {
                    if (isset($mostrar['actualizar_correo']) && $mostrar['actualizar_correo'] == 1) {
                        echo '<div class="alert-verify">
                            <div><strong>¡Tienes un correo pendiente de verificar!</strong> ' . 
                            htmlspecialchars($mostrar['correo_pendiente_actualizar']) . '</div>
                            <button id="btn-verificar-correo" data-id="' . $mostrar['id'] . '" class="btn-primary">
                                Reenviar correo
                            </button>
                        </div>';
                    }
                ?>
                
                <form id="datosForm" method="post" enctype="multipart/form-data">
                    <input type="hidden" id="id" name="id" value="<?php echo $mostrar['id']; ?>">
                    <input type="hidden" id="display" name="display" value="none">
                    
                    <h3 class="info-title" style="margin-bottom: 1.5rem;">
                        <i class="bi bi-card-heading"></i> Datos personales
                    </h3>
                    
                    <?php
                    $requiereFacturacion = (isset($mostrar['facturacion']) && (int) $mostrar['facturacion'] === 1)
                        || (!empty($mostrar['rfc']));
                    ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="nombre" class="form-label">Nombre</label>
                                <input type="text" id="nombre" name="nombre" class="form-control" required 
                                    value="<?php echo htmlspecialchars($mostrar['nombre_contacto']); ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="correo" class="form-label">Correo</label>
                                <input type="email" id="correo" name="correo" class="form-control" required
                                    value="<?php echo htmlspecialchars($mostrar['correo']); ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="tel" class="form-label">Teléfono móvil</label>
                                <input type="tel" id="tel" name="tel" class="form-control" required
                                    value="<?php echo htmlspecialchars($mostrar['telefono']); ?>">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="empresa_fact" class="form-label">Empresa</label>
                                <input type="text" id="empresa_fact" name="empresa_fact" class="form-control"
                                    value="<?php echo htmlspecialchars($mostrar['empresa'] ?? ''); ?>"
                                    placeholder="Nombre de la empresa">
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="form-group">
                                <span class="facturacion-field-label">Facturación</span>
                                <label class="facturacion-toggle <?php echo $requiereFacturacion ? 'is-on' : ''; ?>" id="facturacionToggle">
                                    <input class="form-check-input" type="checkbox" id="requiereFacturacion"
                                        name="requiereFacturacion" <?php echo $requiereFacturacion ? 'checked' : ''; ?>>
                                    <span class="facturacion-toggle__switch" aria-hidden="true"></span>
                                    <span class="facturacion-toggle__copy">
                                        <span class="facturacion-toggle__title">
                                            <i class="bi bi-receipt"></i>
                                            Requiere facturación
                                        </span>
                                        <span class="facturacion-toggle__hint">Activa IVA y datos fiscales de tu cuenta</span>
                                    </span>
                                </label>
                                <input type="hidden" name="facturacion" id="facturacion" value="<?php echo $requiereFacturacion ? '1' : '0'; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div id="seccionFacturacion" style="<?php echo $requiereFacturacion ? '' : 'display:none;'; ?>">
                        <div class="facturacion-section">
                            <h3 class="facturacion-title">
                                <i class="bi bi-file-earmark-text"></i> Datos fiscales
                            </h3>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="rsocial_fact" class="form-label">Razón Social</label>
                                        <input type="text" id="rsocial_fact" name="rsocial_fact" class="form-control" 
                                            value="<?php echo htmlspecialchars($mostrar['rsocial']); ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="rfc_fact" class="form-label">RFC</label>
                                        <input type="text" id="rfc_fact" name="rfc_fact" class="form-control" 
                                            value="<?php echo htmlspecialchars($mostrar['rfc']); ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="constancia_fiscal" class="form-label">Constancia de Situación Fiscal</label>
                                        <input type="file" id="constancia_fiscal" name="constancia_fiscal" class="form-control" 
                                            accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">

                                        <?php
                                        $constanciaInfo = cw_constancia_fiscal_parse($mostrar['constancia_situacion_fiscal'] ?? null);
                                        if ($constanciaInfo):
                                        ?>
                                        <div class="constancia-preview" id="constanciaPreviewCurrent">
                                            <div class="constancia-preview__head">
                                                <span class="constancia-preview__name"><?php echo htmlspecialchars($constanciaInfo['filename']); ?></span>
                                                <?php if ($constanciaInfo['exists']): ?>
                                                <a class="constancia-preview__link" href="<?php echo htmlspecialchars($constanciaInfo['url']); ?>" target="_blank" rel="noopener">
                                                    Abrir archivo
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                            <div class="constancia-preview__body">
                                                <?php if ($constanciaInfo['exists']): ?>
                                                    <?php if ($constanciaInfo['type'] === 'image'): ?>
                                                        <img src="<?php echo htmlspecialchars($constanciaInfo['url']); ?>" alt="Vista previa constancia fiscal">
                                                    <?php else: ?>
                                                        <iframe src="<?php echo htmlspecialchars($constanciaInfo['url']); ?>#toolbar=0" title="Vista previa constancia fiscal"></iframe>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <p class="constancia-preview__empty">
                                                        El archivo no está en <code>constancias_fiscales/</code> de este portal.
                                                        Sube de nuevo la constancia para actualizarla.
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>

                                        <div class="constancia-preview constancia-preview--new" id="constanciaPreviewNew" aria-live="polite">
                                            <div class="constancia-preview__head">
                                                <span class="constancia-preview__name" id="constanciaPreviewNewName">Nuevo archivo</span>
                                            </div>
                                            <div class="constancia-preview__body" id="constanciaPreviewNewBody"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <h3 class="facturacion-title" style="margin-top: 1.5rem;">
                                <i class="bi bi-geo-alt"></i> Dirección fiscal
                            </h3>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="calle_fact" class="form-label">Calle</label>
                                        <input type="text" id="calle_fact" name="calle_fact" class="form-control" 
                                            value="<?php echo htmlspecialchars($mostrar['calle']); ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="next_fact" class="form-label">N° Exterior</label>
                                        <input type="text" id="next_fact" name="next_fact" class="form-control" 
                                            value="<?php echo htmlspecialchars($mostrar['next']); ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="nint_fact" class="form-label">N° Interior</label>
                                        <input type="text" id="nint_fact" name="nint_fact" class="form-control" 
                                            value="<?php echo htmlspecialchars($mostrar['nint']); ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="colonia_fact" class="form-label">Colonia</label>
                                        <input type="text" id="colonia_fact" name="colonia_fact" class="form-control" 
                                            value="<?php echo htmlspecialchars($mostrar['col']); ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="cp_fact" class="form-label">Código Postal</label>
                                        <input type="text" id="cp_fact" name="cp_fact" class="form-control" 
                                            value="<?php echo htmlspecialchars($mostrar['cp']); ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="pais_fact" class="form-label">País</label>
                                        <input type="text" id="pais_fact" name="pais_fact" class="form-control" 
                                            value="<?php echo htmlspecialchars($mostrar['pais']); ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="estado_fact" class="form-label">Estado</label>
                                        <select id="estado_fact" name="estado_fact" class="form-control">
                                            <option value="">Selecciona un estado</option>
                                            <?php
                                            $estados = mysqli_query($conn, "SELECT id_estado, estado FROM estados ORDER BY estado");
                                            while ($row = mysqli_fetch_assoc($estados)) {
                                                $selected = ($mostrar['estado'] == $row['estado'] || $mostrar['estado'] == $row['id_estado']) ? 'selected' : '';
                                                echo '<option value="' . htmlspecialchars($row['id_estado']) . '" ' . $selected . '>' . htmlspecialchars($row['estado']) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="municipio" class="form-label">Ciudad</label>
                                        <select id="municipio" name="municipio" class="form-control" required>
                                            <option value="" selected disabled>Selecciona una ciudad</option>
                                        </select>
                                        <input type="hidden" id="ciudad_actual_id" value="<?php echo htmlspecialchars($mostrar['ciudad_id'] ?? $mostrar['ciudad'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row g-3 mt-4">
                        <div class="col-md-6">
                            <button type="submit" class="btn-primary" id="reg_btn">Actualizar mis datos</button>
                        </div>
                        
                        <div class="col-md-6 text-end">
                            <a href="javascript:window.open('https://c-onlineweb.com/politica-de-privacidad/','','width=800,height=600');void(null)" 
                               class="text-primary" style="text-decoration: none;">
                                Política de privacidad
                            </a>
                        </div>
                    </div>
                </form>
                <?php } ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Controlar visibilidad de sección de facturación
            function toggleFacturacion() {
                const mostrar = $('#requiereFacturacion').prop('checked');
                $('#facturacion').val(mostrar ? '1' : '0');
                $('#seccionFacturacion').toggle(mostrar);
                $('#facturacionToggle').toggleClass('is-on', mostrar);

                // Solo datos fiscales / dirección (empresa queda fuera)
                const camposFacturacion = $('#seccionFacturacion').find('input:not([type="file"]):not([type="hidden"]), select');
                camposFacturacion.prop('required', false);
                if (mostrar) {
                    $('#rsocial_fact, #rfc_fact, #calle_fact, #next_fact, #colonia_fact, #cp_fact, #pais_fact, #estado_fact, #municipio').prop('required', true);
                    $('#nint_fact, #constancia_fiscal').prop('required', false);
                } else {
                    camposFacturacion.prop('required', false);
                }
            }
            
            $('#requiereFacturacion').change(toggleFacturacion);
            toggleFacturacion(); // Inicializar estado

            let constanciaPreviewObjectUrl = null;
            $('#constancia_fiscal').on('change', function() {
                const file = this.files && this.files[0] ? this.files[0] : null;
                const $newBox = $('#constanciaPreviewNew');
                const $body = $('#constanciaPreviewNewBody');
                const $name = $('#constanciaPreviewNewName');

                if (constanciaPreviewObjectUrl) {
                    URL.revokeObjectURL(constanciaPreviewObjectUrl);
                    constanciaPreviewObjectUrl = null;
                }

                if (!file) {
                    $newBox.removeClass('is-visible');
                    $body.empty();
                    return;
                }

                $name.text(file.name);
                $newBox.addClass('is-visible');

                if (file.type.startsWith('image/')) {
                    constanciaPreviewObjectUrl = URL.createObjectURL(file);
                    $body.html('<img src="' + constanciaPreviewObjectUrl + '" alt="Vista previa del archivo seleccionado">');
                } else if (file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')) {
                    constanciaPreviewObjectUrl = URL.createObjectURL(file);
                    $body.html('<iframe src="' + constanciaPreviewObjectUrl + '#toolbar=0" title="Vista previa PDF"></iframe>');
                } else {
                    $body.html('<p class="constancia-preview__empty">Vista previa no disponible para este formato.</p>');
                }
            });
            
            // Cargar municipios según estado seleccionado
            $('#estado_fact').change(function() {
                const estadoId = $(this).val();
                if (estadoId) {
                    $.ajax({
                        url: 'obtener_municipios.php',
                        type: 'POST',
                        data: { estados_id_estado: estadoId },
                        success: function(data) {
                            $('#municipio').html(data);
                        }
                    });
                } else {
                    $('#municipio').html('<option value="" selected disabled>Selecciona una ciudad</option>');
                }
            });
            
            // Inicializar municipio si ya hay estado seleccionado
            const estadoSeleccionado = $('#estado_fact').val();
            const ciudadActualId = $('#ciudad_actual_id').val();
            if (estadoSeleccionado) {
                $.ajax({
                    url: 'obtener_municipios.php',
                    type: 'POST',
                    data: { estados_id_estado: estadoSeleccionado },
                    success: function(data) {
                        $('#municipio').html(data);
                        if (ciudadActualId) {
                            $('#municipio').val(ciudadActualId);
                        }
                    }
                });
            }
            
            // Envío del formulario
            $('#datosForm').submit(function(e) {
                e.preventDefault();
                
                Swal.fire({
                    title: 'Procesando...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });
                
                const formData = new FormData(this);
                const requiereFacturacion = $('#requiereFacturacion').prop('checked');
                formData.set('facturacion', requiereFacturacion ? '1' : '0');
                formData.set('empresa_fact', $('#empresa_fact').val() || '');
                
                $.ajax({
                    type: 'POST',
                    url: 'actulizacion_clientes.php',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Éxito!',
                            text: 'Datos actualizados correctamente',
                            confirmButtonColor: '#000147'
                        }).then(() => location.reload());
                    },
                    error: function(xhr) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Ocurrió un error al actualizar los datos',
                            confirmButtonColor: '#000147'
                        });
                        console.error(xhr.responseText);
                    }
                });
            });
            
            // Reenviar correo de verificación
            $('#btn-verificar-correo').click(function() {
                var idCliente = $(this).data('id');
                
                Swal.fire({
                    title: 'Enviando correo...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });
                
                $.post('reenviar_correo_verificar_correo.php', {id: idCliente}, function(response) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Correo enviado!',
                        text: 'Se ha reenviado el correo de verificación',
                        confirmButtonColor: '#000147'
                    });
                }).fail(function(xhr) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'No se pudo reenviar el correo',
                        confirmButtonColor: '#000147'
                    });
                    console.error(xhr.responseText);
                });
            });
        });
    </script>
</body>
</html>
<?php include('footer.php'); ?>
<?php
} else {
    header("Location: ingreso.php");
}
?>