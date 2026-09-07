    <?php
    require_once __DIR__ . '/includes/cliente_session.php';
    cliente_start_session();

    // Verificar que el usuario esté logueado
    if (!cliente_is_logged_in()) {
        echo '<div class="alert alert-danger">No autorizado</div>';
        exit();
    }

    include 'conn.php';
    require_once __DIR__ . '/config_uploads.php';
    $usrid = $_SESSION['uid'];

    // Función para compatibilidad con PHP < 8
    if (!function_exists('str_starts_with')) {
        function str_starts_with($haystack, $needle) {
            return strpos($haystack, $needle) === 0;
        }
    }

    // Construir URL pública respetando subcarpeta del sitio
    if(!function_exists('cw_build_public_url')) {
        function cw_build_public_url($path){
            if(preg_match('#^https?://#i', $path)) return $path;
            $path = ltrim($path, '/');
            $base = dirname($_SERVER['SCRIPT_NAME']);
            if($base === '/' || $base === '\\' || $base === '.') $base = '';
            $base = rtrim($base, '/');
            if($base && str_starts_with('/'.$path, $base.'/')) {
                return $base . '/' . $path;
            }
            return ($base ? $base : '') . '/' . $path;
        }
    }

    // Validar que se recibió el ID del ticket
    if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        echo '<div class="alert alert-danger">ID de ticket inválido</div>';
        exit();
    }

    $ticket_id = (int)$_GET['id'];

    // Obtener los detalles del ticket
    $query = mysqli_query($conn, "
        SELECT s.*, p.nombre_proyecto, c.nombre_contacto, c.empresa
        FROM solicitudes s 
        LEFT JOIN proyectos p ON s.id_proyecto = p.id_proyecto 
        LEFT JOIN clientes c ON s.id_cliente = c.id
        WHERE s.id = $ticket_id AND s.id_cliente = $usrid
    ");

    if(mysqli_num_rows($query) == 0) {
        echo '<div class="alert alert-danger">Ticket no encontrado o no tienes permisos para verlo</div>';
        exit();
    }

    $ticket = mysqli_fetch_assoc($query);

    // Formatear fechas
    $fecha_creacion = date('d/m/Y H:i', strtotime($ticket['fecha_solicitud']));
    $fecha_limite = ($ticket['fecha_lim'] && $ticket['fecha_lim'] != '0000-00-00 00:00:00') ? 
                date('d/m/Y H:i', strtotime($ticket['fecha_lim'])) : 'Sin fecha';
    $fecha_termina = ($ticket['fecha_termina'] && $ticket['fecha_termina'] != '0000-00-00 00:00:00') ? 
                    date('d/m/Y H:i', strtotime($ticket['fecha_termina'])) : 'Sin finalizar';

    // Decodificar descripción JSON si es necesario
    $descripcion = $ticket['descripcion'];
    $descripcion_texto = '';
    $imagenes = [];
    $archivos = [];

    if($descripcion && $descripcion[0] == '{') {
        $desc_json = json_decode($descripcion, true);
        if($desc_json) {
            $descripcion_texto = $desc_json['text'] ?? $descripcion;
            
            // Manejar imágenes (formato antiguo y nuevo)
            if(isset($desc_json['images'])) {
                $imagenes_raw = $desc_json['images'];
                foreach($imagenes_raw as $imagen) {
                    if(is_string($imagen)) {
                        // Formato antiguo: solo la ruta como string
                        $imagenes[] = [
                            'ruta' => $imagen,
                            'nombre_original' => basename($imagen),
                            'tamaño' => file_exists($imagen) ? filesize($imagen) : 0,
                            'extension' => pathinfo($imagen, PATHINFO_EXTENSION)
                        ];
                    } elseif(is_array($imagen)) {
                        // Formato nuevo: array con toda la información
                        $imagenes[] = $imagen;
                    }
                }
            }
            
            // Manejar archivos (formato antiguo y nuevo)
            if(isset($desc_json['files'])) {
                $archivos_raw = $desc_json['files'];
                foreach($archivos_raw as $archivo) {
                    if(is_string($archivo)) {
                        // Formato antiguo: solo la ruta como string
                        $archivos[] = [
                            'ruta' => $archivo,
                            'nombre_original' => basename($archivo),
                            'tamaño' => file_exists($archivo) ? filesize($archivo) : 0,
                            'extension' => pathinfo($archivo, PATHINFO_EXTENSION)
                        ];
                    } elseif(is_array($archivo)) {
                        // Formato nuevo: array con toda la información
                        $archivos[] = $archivo;
                    }
                }
            }
        }
    } else {
        // Descripción en texto plano (formato muy antiguo)
        $descripcion_texto = $descripcion;
        
        // Buscar referencias a imágenes en el texto (formato legacy)
        preg_match_all('/\b(?:https?:\/\/)?(?:www\.)?[^\s]+\.(?:jpg|jpeg|png|gif|bmp|webp)\b/i', $descripcion, $matches);
        if(!empty($matches[0])) {
            foreach($matches[0] as $imagen_url) {
                $imagenes[] = [
                    'ruta' => $imagen_url,
                    'nombre_original' => basename($imagen_url),
                    'tamaño' => 0,
                    'extension' => pathinfo($imagen_url, PATHINFO_EXTENSION)
                ];
            }
        }
    }

    // Clases CSS para estado y prioridad
    $estado_class = '';
    $estado_icon = '';
    switch($ticket['estado']) {
        case 'Pendiente':
            $estado_class = 'status-badge status-pending';
            $estado_icon = 'bi-clock';
            break;
        case 'En Proceso':
            $estado_class = 'status-badge status-process';
            $estado_icon = 'bi-gear';
            break;
        case 'Finalizado':
            $estado_class = 'status-badge status-completed';
            $estado_icon = 'bi-check-circle';
            break;
    }

    $prioridad_class = '';
    $prioridad_icon = '';
    switch($ticket['prioridad']) {
        case 'Alta':
            $prioridad_class = 'priority-badge priority-high';
            $prioridad_icon = 'bi-exclamation-triangle-fill';
            break;
        case 'Media':
            $prioridad_class = 'priority-badge priority-medium';
            $prioridad_icon = 'bi-exclamation-circle-fill';
            break;
        case 'Baja':
            $prioridad_class = 'priority-badge priority-low';
            $prioridad_icon = 'bi-info-circle-fill';
            break;
    }
    ?>

    <div class="ticket-detail">
        <!-- Encabezado del ticket -->
        <div class="ticket-header mb-4">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h4 class="mb-2">
                        <i class="bi bi-card-list me-2 text-primary"></i>
                        Ticket #<?php echo $ticket['id']; ?>
                    </h4>
                    <h5 class="text-dark"><?php echo htmlspecialchars($ticket['titulo']); ?></h5>
                </div>
                <div class="text-end">
                    <div class="mb-2">
                        <span class="<?php echo $estado_class; ?>">
                            <i class="bi <?php echo $estado_icon; ?> me-1"></i>
                            <?php echo $ticket['estado']; ?>
                        </span>
                    </div>
                    <div>
                        <span class="<?php echo $prioridad_class; ?>">
                            <i class="bi <?php echo $prioridad_icon; ?> me-1"></i>
                            Prioridad <?php echo $ticket['prioridad']; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información del ticket -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="info-card">
                    <h6><i class="bi bi-calendar-check me-2"></i>Fechas importantes</h6>
                    <div class="info-item">
                        <span class="info-label">Creado:</span>
                        <span class="info-value"><?php echo $fecha_creacion; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Fecha estimada:</span>
                        <span class="info-value"><?php echo $fecha_limite; ?></span>
                    </div>
                    <?php if($ticket['estado'] == 'Finalizado'): ?>
                    <div class="info-item">
                        <span class="info-label">Finalizado:</span>
                        <span class="info-value text-success"><?php echo $fecha_termina; ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="info-card">
                    <h6><i class="bi bi-folder me-2"></i>Información del proyecto</h6>
                    <div class="info-item">
                        <span class="info-label">Proyecto:</span>
                        <span class="info-value">
                            <?php echo $ticket['nombre_proyecto'] ? htmlspecialchars($ticket['nombre_proyecto']) : '<em>Sin proyecto asignado</em>'; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Cliente:</span>
                        <span class="info-value"><?php echo htmlspecialchars($ticket['nombre_contacto']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Empresa:</span>
                        <span class="info-value"><?php echo htmlspecialchars($ticket['empresa']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Descripción del ticket -->
        <div class="description-section mb-4">
            <h6><i class="bi bi-file-text me-2"></i>Descripción</h6>
            <div class="description-content">
                <?php echo nl2br(htmlspecialchars($descripcion_texto)); ?>
            </div>
        </div>

        <!-- Archivos adjuntos (si existen) -->
        <?php if(!empty($imagenes) || !empty($archivos)): ?>
        <div class="attachments-section mb-4">
            <h6><i class="bi bi-paperclip me-2"></i>Archivos adjuntos</h6>
            
            <?php if(!empty($imagenes)): ?>
            <div class="images-section mb-3">
                <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-images me-2 text-primary"></i>
                    <strong>Imágenes adjuntas:</strong>
                </div>
                <div class="row">
                    <?php foreach($imagenes as $imagen): 
                        $imagen_ruta_original = $imagen['ruta'] ?? '';
                        $imagen_ruta_web = $imagen_ruta_original;
                        $imagen_existe = false;

                        // Si ya es URL absoluta (http/https)
                        if(filter_var($imagen_ruta_original, FILTER_VALIDATE_URL)) {
                            $imagen_existe = true;
                        } else {
                            $basename = basename($imagen_ruta_original);

                            // Posibles bases físicas donde pudo guardarse
                            $bases_fs = [
                                __DIR__ . '/uploads/tickets/',                      // local tickets
                                __DIR__ . '/uploads/',                               // local uploads genérico
                                __DIR__ . '/../solicitudes/uploads/',               // carpeta compartida admin (relativa)
                                dirname(__DIR__) . '/solicitudes/uploads/',         // otra variante
                            ];

                            foreach($bases_fs as $base) {
                                if(is_dir($base) && file_exists($base . $basename)) {
                                    $imagen_existe = true;
                                    // Determinar ruta web asociada
                                    if(strpos($base, 'solicitudes') !== false) {
                                        // Carpeta admin externa
                                        $imagen_ruta_web = 'https://adm.conlineweb.com/solicitudes/uploads/' . $basename;
                                    } elseif(strpos($base, 'uploads' . DIRECTORY_SEPARATOR . 'tickets') !== false) {
                                        $imagen_ruta_web = 'uploads/tickets/' . $basename;
                                    } elseif(strpos($base, 'uploads') !== false) {
                                        $imagen_ruta_web = 'uploads/' . $basename;
                                    }
                                    break;
                                }
                            }
                        }

                        // Fallback: si aún no se determinó existencia local y no es URL absoluta, intentar armar URL remota conocida
                        if(!$imagen_existe) {
                            $basename = basename($imagen_ruta_original);
                            // Heurística: si el path original menciona 'solicitudes' o termina sólo en nombre de archivo
                            if($basename) {
                                $posible_url = 'https://adm.conlineweb.com/solicitudes/uploads/' . $basename;
                                // No podemos verificar con file_exists remoto, asumimos intento (onerror ya maneja fallo visual)
                                $imagen_ruta_web = $posible_url;
                                $imagen_existe = true; // Permitimos que se intente cargar
                            }
                        }

                        // Si la ruta final es relativa, prefijar base
                        if(!preg_match('#^https?://#',$imagen_ruta_web)) {
                            // Asegurar que usemos la URL base de config
                            $basenameImg = basename(parse_url($imagen_ruta_web, PHP_URL_PATH));
                            $imagen_ruta_web = TICKETS_UPLOAD_URL . $basenameImg;
                        }

                        // Modo debug opcional (?debug_imgs=1) para mostrar ruta original y final
                        $debug_imgs = isset($_GET['debug_imgs']);
                    ?>
                    <div class="col-md-4 col-sm-6 mb-3">
                        <div class="attachment-card">
                            <div class="attachment-preview">
                                <?php if($imagen_existe): ?>
                                <img src="<?php echo htmlspecialchars($imagen_ruta_web); ?>" 
                                    class="attachment-image" 
                                    alt="<?php echo htmlspecialchars($imagen['nombre_original']); ?>"
                                    data-original="<?php echo htmlspecialchars($imagen_ruta_web); ?>"
                                    data-fallback-local-tickets="<?php echo htmlspecialchars(cw_build_public_url('uploads/tickets/' . basename(parse_url($imagen_ruta_web, PHP_URL_PATH)))); ?>"
                                    data-fallback-local-uploads="<?php echo htmlspecialchars(cw_build_public_url('uploads/' . basename(parse_url($imagen_ruta_web, PHP_URL_PATH)))); ?>"
                                    onclick="openImageModal(this.src, '<?php echo htmlspecialchars($imagen['nombre_original']); ?>')"
                                    onerror="handleImageError(this)">
                                <?php if($debug_imgs): ?>
                                    <div class="position-absolute top-0 start-0 p-1 small bg-dark text-white opacity-75" style="font-size:10px;max-width:100%;overflow:hidden;white-space:nowrap;">
                                        <?php echo htmlspecialchars($imagen_ruta_original); ?> => <?php echo htmlspecialchars($imagen_ruta_web); ?>
                                    </div>
                                <?php endif; ?>
                                <?php else: ?>
                                <div class="attachment-placeholder">
                                    <i class="bi bi-image text-muted fs-1"></i>
                                    <p class="text-muted small">Imagen no disponible</p>
                                    <small class="text-muted"><?php echo htmlspecialchars(basename($imagen_ruta_original)); ?></small>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="attachment-info">
                                <div class="attachment-name" title="<?php echo htmlspecialchars($imagen['nombre_original']); ?>">
                                    <?php echo htmlspecialchars(strlen($imagen['nombre_original']) > 20 ? substr($imagen['nombre_original'], 0, 20) . '...' : $imagen['nombre_original']); ?>
                                </div>
                                <div class="attachment-details">
                                    <small class="text-muted">
                                        <?php 
                                        if(isset($imagen['tamaño']) && $imagen['tamaño'] > 0) {
                                            echo number_format($imagen['tamaño'] / 1024, 1) . ' KB';
                                        } else {
                                            echo 'Tamaño desconocido';
                                        }
                                        ?>
                                    </small>
                                    <?php if($imagen_existe): ?>
                                    <a href="<?php echo htmlspecialchars($imagen_ruta_web); ?>" 
                                    target="_blank" 
                                    class="tk-btn tk-btn--ghost tk-btn--sm ms-2"
                                    title="Descargar imagen">
                                        <i class="bi bi-download"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if(!empty($archivos)): ?>
            <div class="files-section">
                <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-file-earmark me-2 text-primary"></i>
                    <strong>Archivos adjuntos:</strong>
                </div>
                <div class="files-list">
                    <?php foreach($archivos as $archivo): ?>
                    <div class="file-attachment-item">
                        <div class="file-icon-container">
                            <?php 
                            $ext = strtolower($archivo['extension']);
                            $iconClass = 'bi-file-earmark';
                            $iconColor = '#6c757d';
                            
                            if($ext == 'pdf') {
                                $iconClass = 'bi-file-earmark-text';
                                $iconColor = '#dc3545';
                            } elseif(in_array($ext, ['doc', 'docx'])) {
                                $iconClass = 'bi-file-earmark-word';
                                $iconColor = '#0066cc';
                            } elseif($ext == 'txt') {
                                $iconClass = 'bi-file-earmark-text';
                                $iconColor = '#495057';
                            } elseif(in_array($ext, ['zip', 'rar'])) {
                                $iconClass = 'bi-file-earmark-zip';
                                $iconColor = '#28a745';
                            }
                            ?>
                            <i class="bi <?php echo $iconClass; ?>" style="color: <?php echo $iconColor; ?>; font-size: 1.5rem;"></i>
                        </div>
                        <div class="file-info">
                            <div class="file-name">
                                <?php echo htmlspecialchars($archivo['nombre_original']); ?>
                            </div>
                            <div class="file-details">
                                <span class="file-size">
                                    <?php 
                                    if(isset($archivo['tamaño']) && $archivo['tamaño'] > 0) {
                                        echo number_format($archivo['tamaño'] / 1024, 1) . ' KB';
                                    } else {
                                        echo 'Tamaño desconocido';
                                    }
                                    ?>
                                </span>
                                <span class="file-type"><?php echo strtoupper($archivo['extension'] ?? 'FILE'); ?></span>
                            </div>
                        </div>
                        <div class="file-actions">
                            <?php 
                            $archivo_ruta = $archivo['ruta'];
                            $archivo_existe = file_exists($archivo_ruta);
                            
                            if(!$archivo_existe && !filter_var($archivo_ruta, FILTER_VALIDATE_URL)) {
                                // Intentar con diferentes rutas posibles
                                $rutas_posibles = [
                                    $archivo_ruta,
                                    'uploads/tickets/' . basename($archivo_ruta),
                                    '../uploads/tickets/' . basename($archivo_ruta)
                                ];
                                
                                foreach($rutas_posibles as $ruta) {
                                    if(file_exists($ruta)) {
                                        $archivo_ruta = $ruta;
                                        $archivo_existe = true;
                                        break;
                                    }
                                }
                            }
                            
                            if(!preg_match('#^https?://#',$archivo_ruta)) {
                                $archivo_ruta = cw_build_public_url($archivo_ruta);
                            }
                            if($archivo_existe || filter_var($archivo_ruta, FILTER_VALIDATE_URL)): ?>
                            <a href="<?php echo htmlspecialchars($archivo_ruta); ?>" 
                            target="_blank" 
                            class="tk-btn tk-btn--ghost tk-btn--sm"
                            title="Descargar archivo">
                                <i class="bi bi-download me-1"></i>Descargar
                            </a>
                            <?php else: ?>
                            <span class="text-muted small">Archivo no disponible</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if(isset($_GET['debug_imgs']) && (int)$_GET['debug_imgs'] === 2 && !empty($imagenes)): ?>
        <div class="alert alert-secondary" style="font-size:12px; white-space:pre-wrap; max-height:300px; overflow:auto;">
<strong>DEBUG IMÁGENES (nivel 2)</strong>\n<?php
foreach($imagenes as $img){
    $orig = $img['ruta'] ?? '';
    $basename = basename($orig);
    $cands = [
        $orig,
        __DIR__ . '/' . ltrim($orig,'/'),
        __DIR__ . '/uploads/tickets/' . $basename,
        __DIR__ . '/uploads/' . $basename,
        __DIR__ . '/../solicitudes/uploads/' . $basename,
        dirname(__DIR__) . '/solicitudes/uploads/' . $basename
    ];
    echo "\nImagen: $orig";
    foreach($cands as $c){
        $exists = file_exists($c) ? 'SI' : 'NO';
        echo "\n  - FS: $c => $exists";
    }
}
?>
\nFin debug.
        </div>
        <?php endif; ?>

        <!-- Progreso del ticket -->
        <div class="progress-section">
            <h6><i class="bi bi-graph-up me-2"></i>Historial de estado</h6>
            <div class="progress-timeline">
                <div class="timeline-item <?php echo $ticket['estado'] != 'Pendiente' ? 'completed' : 'current'; ?>">
                    <div class="timeline-marker"></div>
                    <div class="timeline-content">
                        <strong>Ticket Creado</strong>
                        <small class="d-block text-muted"><?php echo $fecha_creacion; ?></small>
                    </div>
                </div>
                
                <div class="timeline-item <?php echo $ticket['estado'] == 'Finalizado' ? 'completed' : ($ticket['estado'] == 'En Proceso' ? 'current' : ''); ?>">
                    <div class="timeline-marker"></div>
                    <div class="timeline-content">
                        <strong>En Proceso</strong>
                        <small class="d-block text-muted">
                            <?php echo $ticket['estado'] == 'En Proceso' ? 'Trabajando en tu solicitud' : 'Pendiente de iniciar'; ?>
                        </small>
                    </div>
                </div>
                
                <div class="timeline-item <?php echo $ticket['estado'] == 'Finalizado' ? 'completed' : ''; ?>">
                    <div class="timeline-marker"></div>
                    <div class="timeline-content">
                        <strong>Finalizado</strong>
                        <small class="d-block text-muted">
                            <?php echo $ticket['estado'] == 'Finalizado' ? $fecha_termina : 'Pendiente de completar'; ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Comentarios del cliente -->
        <?php
        $comentarios_cliente = [];
        $query_notas = mysqli_query($conn, "SELECT autor, nota, fecha_creacion FROM solicitudes_notas WHERE solicitud_id = $ticket_id ORDER BY fecha_creacion ASC");
        while($nota = mysqli_fetch_assoc($query_notas)) {
            $nota_data = json_decode($nota['nota'], true);
            if($nota_data) {
                $comentarios_cliente[] = [
                    'autor' => $nota['autor'],
                    'comentario' => $nota_data['text'] ?? '',
                    'fecha' => $nota['fecha_creacion'],
                    'images' => $nota_data['images'] ?? []
                ];
            }
        }
        ?>
        
        <?php if(!empty($comentarios_cliente)): ?>
        <div class="comments-section mb-4">
            <h6><i class="bi bi-chat-left-text me-2"></i>Comentarios</h6>
            <div class="comments-list">
                <?php foreach($comentarios_cliente as $comentario): ?>
                <div class="comment-item">
                    <div class="comment-header">
                        <i class="bi bi-person-circle me-2"></i>
                        <strong><?php echo htmlspecialchars($comentario['autor']); ?></strong>
                        <span class="comment-date"><?php echo date('d/m/Y H:i', strtotime($comentario['fecha'])); ?></span>
                    </div>
                    <div class="comment-text">
                        <?php echo nl2br(htmlspecialchars($comentario['comentario'])); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Agregar nuevo comentario (solo si el ticket no está finalizado) -->
        <?php if($ticket['estado'] != 'Finalizado'): ?>
        <div class="add-comment-section mb-4">
            <h6><i class="bi bi-plus-circle me-2"></i>Agregar comentario</h6>
            <form id="formAgregarComentario" class="mt-3">
                <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                <div class="mb-3">
                    <textarea class="form-control" name="comentario" rows="4" 
                            placeholder="Agrega información adicional, aclaraciones o detalles que puedan ayudar a resolver tu solicitud más rápido..."
                            required></textarea>
                </div>
                <button type="submit" class="tk-btn tk-btn--primary">
                    <i class="bi bi-send-fill"></i> Enviar comentario
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Mensaje informativo -->
        <div class="alert alert-info mt-4">
            <i class="bi bi-info-circle me-2"></i>
            <strong>¿Necesitas ayuda inmediata?</strong> 
            También puedes contactarnos a través del 
            <a href="javascript:void(0);" role="button"
            class="alert-link"
            onclick="if(window.cwChatWidget){cwChatWidget.open();}else if(document.getElementById('cwChatFab')){document.getElementById('cwChatFab').click();}return false;">chat de soporte en vivo</a> mencionando el número de ticket #<?php echo $ticket['id']; ?>.
        </div>
    </div>

    <!-- Modal para mostrar imágenes -->
    <div id="imageModal" class="image-modal">
        <div class="image-modal-content">
            <span class="image-modal-close" onclick="closeImageModal()">&times;</span>
            <div class="image-modal-header">
                <h5 id="imageModalTitle">Vista de imagen</h5>
            </div>
            <div class="image-modal-body">
                <img id="imageModalImg" src="" alt="Vista ampliada">
            </div>
        </div>
    </div>

    <script>
    function handleImageError(img){
        const tried = img.getAttribute('data-tried') || '';
        const fb1 = img.getAttribute('data-fallback-local-tickets');
        const fb2 = img.getAttribute('data-fallback-local-uploads');
        if(fb1 && !tried.includes('tickets') && img.src !== fb1){
            img.setAttribute('data-tried', tried + ' tickets');
            img.src = fb1; return;
        }
        if(fb2 && !tried.includes('uploads') && img.src !== fb2){
            img.setAttribute('data-tried', tried + ' uploads');
            img.src = fb2; return;
        }
        img.parentElement.innerHTML = '<div class="attachment-placeholder"><i class="bi bi-image text-muted fs-1"></i><p class="text-muted small">Error al cargar imagen</p></div>';
    }
    function openImageModal(imageSrc, imageName) {
        const modal = document.getElementById('imageModal');
        const modalImg = document.getElementById('imageModalImg');
        const modalTitle = document.getElementById('imageModalTitle');
        
        modal.style.display = 'block';
        modalImg.src = imageSrc;
        modalTitle.textContent = imageName;
        
        // Prevenir scroll del body
        document.body.style.overflow = 'hidden';
    }

    function closeImageModal() {
        const modal = document.getElementById('imageModal');
        modal.style.display = 'none';
        
        // Restaurar scroll del body
        document.body.style.overflow = 'auto';
    }

    // Cerrar modal con tecla ESC
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeImageModal();
        }
    });

    // Cerrar modal al hacer clic fuera de la imagen
    document.getElementById('imageModal').addEventListener('click', function(event) {
        if (event.target === this) {
            closeImageModal();
        }
    });
    </script>