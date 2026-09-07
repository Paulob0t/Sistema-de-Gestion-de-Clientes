<?php
require_once __DIR__ . '/auth_middleware.php';
include_once './menu.php';

$admPageTitleAllowHtml = true;
$admPageTitle = '<i class="bi bi-whatsapp text-success"></i> Conversaciones de WhatsApp '
    . '<span id="pendingMessagesBadge" class="badge badge-danger ml-2" style="display:none;">'
    . '<i class="bi bi-exclamation-circle"></i> <span id="pendingCount">0</span> pendientes</span>';
$admPageSubtitle = 'Monitoreo y respuesta de chats entrantes por WhatsApp';
?>

<div class="adm-page-shell">
<div class="container-fluid px-0">
<?php include __DIR__ . '/includes/adm_page_header.php'; ?>

            <!-- DataTable -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="m-0 font-weight-bold text-primary">Lista de Conversaciones</h6>
                        <div class="mt-2">
                            <small class="text-muted">
                                <i class="bi bi-circle-fill text-danger"></i> Sin responder | 
                                <i class="bi bi-clock-history text-warning"></i> Pendiente reciente | 
                                <i class="bi bi-check-circle-fill text-success"></i> Atendida |
                                <i class="bi bi-cash-coin text-warning"></i> Comprobante de pago
                            </small>
                        </div>
                    </div>
                    <button type="button" class="btn btn-success btn-sm" id="startBotBtn">
                        <i class="bi bi-power"></i> Activar Bot
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="conversacionesTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th width="80">Estado</th>
                                    <th>Teléfono</th>
                                    <th>Cliente / Archivo</th>
                                    <th>Último Mensaje</th>
                                    <th>Última Actualización</th>
                                    <th>Mensajes</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
</div></div>

<!-- Modal Conversación estilo WhatsApp -->
<div class="modal fade" id="conversacionModal" tabindex="-1" role="dialog" aria-labelledby="conversacionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="conversacionModalLabel">
                    <i class="bi bi-whatsapp"></i> Conversación: <span id="modalFileName"></span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0" style="background-color: #e5ddd5;">
                <!-- Info del cliente -->
                <div class="bg-white p-3 border-bottom">
                    <div class="d-flex align-items-center">
                        <div class="avatar-circle bg-success text-white mr-3" style="width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: bold;">
                            <i class="bi bi-person"></i>
                        </div>
                        <div>
                            <h6 class="mb-0" id="modalClientName">Cargando...</h6>
                            <small class="text-muted" id="modalClientPhone"></small>
                            <div id="clientStatus" class="mt-1">
                                <!-- Estado dinámico se insertará aquí -->
                            </div>
                        </div>
                        <div class="ml-auto">
                            <span id="contextAnalysisBadge" class="badge badge-info" style="display: none;">
                                <i class="bi bi-robot"></i> Análisis activo
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Área de mensajes -->
                <div id="chatArea" style="height: 500px; overflow-y: auto; padding: 20px; background-image: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZGVmcz48cGF0dGVybiBpZD0iYSIgcGF0dGVyblVuaXRzPSJ1c2VyU3BhY2VPblVzZSIgd2lkdGg9IjEwMCIgaGVpZ2h0PSIxMDAiPjxyZWN0IGZpbGw9IiNlNWRkZDUiIHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIi8+PHBhdGggZD0iTTAgNTBINTBWMEgweiIgZmlsbD0iI2RkZDRjYyIvPjxwYXRoIGQ9Ik01MCA1MEgxMDBWNTBINTB6IiBmaWxsPSIjZGRkNGNjIi8+PC9wYXR0ZXJuPjwvZGVmcz48cmVjdCBmaWxsPSJ1cmwoI2EpIiB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIi8+PC9zdmc+');">
                    <div class="text-center">
                        <div class="spinner-border text-success" role="status">
                            <span class="sr-only">Cargando...</span>
                        </div>
                        <p class="mt-2 text-muted">Cargando conversación...</p>
                    </div>
                </div>

                <!-- Área de escritura -->
                <div class="bg-white p-3 border-top">
                    <div class="message-composer">
                        <!-- Barra de respuestas rápidas y análisis -->
                        <div class="mb-2 d-flex justify-content-between align-items-center">
                            <div>
                                <button type="button" class="btn btn-outline-success btn-sm" id="showQuickRepliesBtn" title="Respuestas rápidas">
                                    <i class="bi bi-lightning"></i> Respuestas Rápidas
                                </button>
                                <button type="button" class="btn btn-outline-info btn-sm ml-2" id="toggleAutoAnalysisBtn" title="Análisis automático">
                                    <i class="bi bi-robot"></i> Auto-Análisis: <span id="autoAnalysisStatus">OFF</span>
                                </button>
                            </div>
                            <div>
                                <button type="button" class="btn btn-outline-primary btn-sm" id="analyzeContextBtn" title="Analizar contexto">
                                    <i class="bi bi-magic"></i> Analizar Contexto
                                </button>
                            </div>
                        </div>
                        
                        <!-- Contenedor del autocompletado -->
                        <div class="autocomplete-container mb-2">
                            <div class="input-group">
                                <textarea 
                                    id="messageInput" 
                                    class="form-control" 
                                    placeholder="Escribe un mensaje... Presiona / para respuestas rápidas" 
                                    rows="2"
                                    style="resize: none; border-radius: 20px 0 0 20px; padding: 12px 15px; font-size: 14px;"
                                ></textarea>
                                <div class="input-group-append">
                                    <button class="btn btn-success ml-2" id="sendMessageBtn" style="border-radius: 0 20px 20px 0; padding: 8px 20px;">
                                        <i class="bi bi-send"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Dropdown de autocompletado -->
                            <div class="autocomplete-dropdown" id="autocompleteDropdown" style="display: none;">
                                <div class="autocomplete-header">
                                    <div class="d-flex justify-content-between align-items-center p-2 border-bottom">
                                        <h6 class="mb-0"><i class="bi bi-lightning-fill text-warning"></i> Respuestas Rápidas</h6>
                                        <button type="button" class="btn btn-sm btn-link text-muted" id="closeAutocomplete">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </div>
                                    <div class="p-2">
                                        <div class="input-group input-group-sm">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                            </div>
                                            <input type="text" class="form-control" id="quickReplySearch" placeholder="Buscar respuesta...">
                                        </div>
                                        <div class="mt-2">
                                            <div class="category-filters d-flex flex-wrap">
                                                <span class="badge badge-secondary mr-1 mb-1 category-filter active" data-category="all">Todas</span>
                                                <span class="badge badge-primary mr-1 mb-1 category-filter" data-category="greeting">Saludos</span>
                                                <span class="badge badge-success mr-1 mb-1 category-filter" data-category="sales">Ventas</span>
                                                <span class="badge badge-info mr-1 mb-1 category-filter" data-category="technical">Técnico</span>
                                                <span class="badge badge-warning mr-1 mb-1 category-filter" data-category="support">Soporte</span>
                                                <span class="badge badge-danger mr-1 mb-1 category-filter" data-category="development">Desarrollo</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="autocomplete-list" id="autocompleteList">
                                    <!-- Las sugerencias se cargarán aquí -->
                                </div>
                                <div class="autocomplete-footer p-2 border-top">
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle"></i> Sugerencias basadas en contexto de conversación
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Área de sugerencias contextuales -->
                        <div id="contextualSuggestions" class="mt-2" style="display: none;">
                            <div class="card border-info">
                                <div class="card-header bg-info text-white py-1">
                                    <small><i class="bi bi-lightbulb"></i> Sugerencias Contextuales</small>
                                </div>
                                <div class="card-body p-2">
                                    <div class="suggestions-container d-flex flex-wrap" id="suggestionsContainer">
                                        <!-- Las sugerencias contextuales aparecerán aquí -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-2">
                            <small class="text-muted">
                                <i class="bi bi-slash-circle"></i> / para respuestas rápidas • 
                                <i class="bi bi-enter"></i> Enviar • 
                                <i class="bi bi-shift"></i>+<i class="bi bi-enter"></i> Nueva línea
                            </small>
                        </div>
                    </div>
                    <input type="hidden" id="currentTelefono" value="">
                    <input type="hidden" id="currentArchivo" value="">
                </div>
            </div>
            <div class="modal-footer">
                <!-- NUEVO: Botón para acreditar pago de transferencia -->
                <button type="button" class="btn btn-warning" id="acreditarPagoBtn" style="display: none;" title="El cliente envió su comprobante — confirmar y acreditar el pago">
                    <i class="bi bi-cash-coin"></i> Acreditar Pago
                </button>

                <!-- NUEVO: Botón para reactivar bot -->
                <button type="button" class="btn btn-warning" id="reactivarBotBtn" style="display: none;" title="Permitir que el bot automático retome la conversación">
                    <i class="bi bi-robot"></i> Reactivar Bot
                </button>
                
                <!-- Botón para marcar como atendido -->
                <button type="button" class="btn btn-success" id="marcarAtendidoBtn" style="display: none;">
                    <i class="bi bi-check-circle"></i> Marcar como Atendido
                </button>
                
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- DataTables CSS -->
<link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap4.min.css" rel="stylesheet">
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>
<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

<style>
    .message-bubble {
        max-width: 70%;
        padding: 8px 12px;
        margin-bottom: 12px;
        border-radius: 8px;
        word-wrap: break-word;
        position: relative;
        clear: both;
    }
    .message-user { 
        background-color: #ffffff; 
        float: left; 
        margin-right: auto; 
        border-bottom-left-radius: 0; 
        box-shadow: 0 1px 0.5px rgba(0,0,0,0.13); 
    }
    .message-assistant { 
        background-color: #dcf8c6; 
        float: right; 
        margin-left: auto; 
        border-bottom-right-radius: 0; 
    }
    .clearfix::after { content: ""; display: table; clear: both; }
    
    /* Autocompletado */
    .autocomplete-container { position: relative; }
    .autocomplete-dropdown {
        position: absolute;
        bottom: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #ddd;
        border-radius: 8px;
        max-height: 300px;
        overflow-y: auto;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 1050;
        margin-bottom: 5px;
    }
    .autocomplete-list { max-height: 200px; overflow-y: auto; }
    .autocomplete-item {
        padding: 10px 15px;
        cursor: pointer;
        border-bottom: 1px solid #f0f0f0;
        transition: all 0.2s;
    }
    .autocomplete-item:hover { 
        background-color: #f8f9fa; 
        transform: translateX(5px);
    }
    .autocomplete-item.active { 
        background-color: #e3f2fd; 
        border-left: 4px solid #007bff;
    }
    .autocomplete-item-title { font-weight: bold; color: #333; }
    .autocomplete-item-text { color: #666; font-size: 0.9em; margin-top: 3px; }
    .autocomplete-item-category {
        font-size: 0.7em;
        padding: 2px 6px;
        border-radius: 10px;
        margin-left: 5px;
    }
    
    /* Categorías */
    .category-greeting { background-color: #e3f2fd; color: #1565c0; }
    .category-sales { background-color: #e8f5e9; color: #2e7d32; }
    .category-technical { background-color: #f3e5f5; color: #7b1fa2; }
    .category-support { background-color: #fff3e0; color: #ef6c00; }
    .category-development { background-color: #e8eaf6; color: #3949ab; }
    
    /* Botones de sugerencia */
    .suggestion-btn {
        margin: 2px;
        font-size: 0.8em;
        white-space: nowrap;
    }
    
    /* Indicador de análisis */
    .analysis-indicator {
        position: absolute;
        top: 10px;
        right: 10px;
        font-size: 0.8em;
        opacity: 0.8;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% { opacity: 0.8; }
        50% { opacity: 0.4; }
        100% { opacity: 0.8; }
    }
    
    /* Fila pendiente en rojo */
    .pending-row {
        background-color: #ffe6e6 !important;
        border-left: 4px solid #dc3545 !important;
    }
    .pending-row:hover {
        background-color: #ffcccc !important;
    }

    /* Fila con comprobante de pago pendiente de acreditar */
    .comprobante-pendiente-row {
        background-color: #fff8e1 !important;
        border-left: 4px solid #ffc107 !important;
        animation: blink-yellow 2s infinite;
    }
    .comprobante-pendiente-row:hover {
        background-color: #fff3cd !important;
    }
    @keyframes blink-yellow {
        0%, 100% { background-color: #fff8e1 !important; }
        50%       { background-color: #ffe082 !important; }
    }
    
    /* Estado urgente */
    .status-urgent {
        color: #dc3545;
        font-size: 1.2em;
        animation: blink 1.5s infinite;
    }
    
    @keyframes blink {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    
    /* Badge de pendientes */
    .badge-pending {
        background-color: #dc3545;
        color: white;
        font-size: 0.7em;
        padding: 2px 6px;
        border-radius: 10px;
        margin-left: 5px;
    }
    
    /* Parpadeo para notificaciones */
    .blinking {
        animation: blink 1s infinite;
    }
    
    /* Botón activar bot */
    .bot-active {
        background-color: #28a745 !important;
        color: white !important;
    }
    .bot-inactive {
        background-color: #6c757d !important;
        color: white !important;
    }
</style>

<script>
// ==============================================
// BASE DE DATOS EXTENDIDA DE RESPUESTAS RÁPIDAS
// ==============================================
const quickRepliesDatabase = [
    { id: 1, title: "Saludo Inicial", text: "¡Hola! 👋 ¿En qué puedo ayudarte hoy?", category: "greeting", tags: ["saludo", "inicio"] },
    { id: 2, title: "Saludo Profesional", text: "¡Buen día! Soy [Nombre/Asesor]. ¿En qué puedo asistirte?", category: "greeting", tags: ["profesional", "presentacion"] },
    { id: 3, title: "Agradecimiento", text: "¡Gracias por contactarnos! 😊 Estoy aquí para ayudarte.", category: "greeting", tags: ["gracias", "agradecer"] },
    { id: 4, title: "Despedida Cortés", text: "Quedo atento a cualquier duda. ¡Que tengas un excelente día! 👋", category: "greeting", tags: ["despedida", "corte"] },
    { id: 5, title: "Presupuesto Web Básico", text: "Para un sitio web básico (5 páginas, responsive, formulario) el costo aproximado es de $XXX. ¿Te gustaría una cotización detallada?", category: "development", tags: ["presupuesto", "web", "costo"] },
    { id: 6, title: "Tiempo Desarrollo", text: "Un sitio web promedio toma de 2 a 4 semanas, dependiendo de los requerimientos específicos. ¿Ya tienes claro el alcance?", category: "development", tags: ["tiempo", "duracion", "desarrollo"] },
    { id: 7, title: "Hosting y Dominio", text: "Recomendamos hosting desde $XXX/mes y dominios desde $XXX/año. ¿Necesitas ayuda para configurarlo?", category: "development", tags: ["hosting", "dominio", "servidor"] },
    { id: 8, title: "Mantenimiento Web", text: "Ofrecemos mantenimiento desde $XXX/mes incluyendo actualizaciones y monitoreo. ¿Te interesa?", category: "development", tags: ["mantenimiento", "soporte", "actualizaciones"] },
    { id: 9, title: "E-commerce Básico", text: "Para una tienda online básica (hasta 50 productos) el desarrollo parte de $XXX. ¿Qué productos venderías?", category: "development", tags: ["ecommerce", "tienda", "online"] },
    { id: 10, title: "Landing Page", text: "Una landing page optimizada para conversiones tiene un costo de $XXX. ¿Cuál es el objetivo principal?", category: "development", tags: ["landing", "conversiones", "pagina"] },
    { id: 11, title: "WordPress", text: "Sí, trabajamos con WordPress. Es ideal para sitios gestionables por el cliente. ¿Ya tienes hosting?", category: "development", tags: ["wordpress", "cms", "gestionable"] },
    { id: 12, title: "React/Angular", text: "Para aplicaciones web complejas usamos React o Angular. ¿Buscas una SPA (Single Page Application)?", category: "development", tags: ["react", "angular", "spa", "aplicacion"] },
    { id: 13, title: "PHP/Laravel", text: "Usamos Laravel para desarrollo backend robusto y escalable. ¿Es un proyecto empresarial?", category: "development", tags: ["php", "laravel", "backend", "api"] },
    { id: 14, title: "Base de Datos", text: "Utilizamos MySQL o PostgreSQL según necesidades. ¿Requieres manejo de datos complejo?", category: "development", tags: ["database", "mysql", "postgresql"] },
    { id: 15, title: "Problema Carga", text: "Para problemas de carga lenta: 1) Optimizar imágenes 2) Usar CDN 3) Cache. ¿Ya revisaste esto?", category: "technical", tags: ["carga", "lento", "optimizacion"] },
    { id: 16, title: "Error 404", text: "El error 404 significa página no encontrada. ¿Puedes compartir la URL exacta? Verificaremos las rutas.", category: "technical", tags: ["error", "404", "pagina"] },
    { id: 17, title: "Formulario No Envía", text: "Si el formulario no envía: 1) Revisa conexión 2) Verifica campos obligatorios 3) Chequea spam. ¿Qué pasa exactamente?", category: "technical", tags: ["formulario", "envio", "error"] },
    { id: 18, title: "Correo No Llega", text: "Para problemas de correo: 1) Verifica spam 2) Revisa configuración SMTP 3) Prueba otro email. ¿Desde cuándo pasa?", category: "technical", tags: ["correo", "email", "smtp"] },
    { id: 19, title: "Solicitar Info Cliente", text: "Para darte una cotización precisa, necesito saber: 1) Tipo de proyecto 2) Plazo 3) Presupuesto. ¿Podrías compartir esos detalles?", category: "sales", tags: ["cotizacion", "informacion", "cliente"] },
    { id: 20, title: "Propuesta de Valor", text: "Nuestro diferencial: 1) Diseño responsive 2) SEO básico incluido 3) 30 días de soporte gratis. ¿Te interesa?", category: "sales", tags: ["propuesta", "valor", "diferencial"] },
    { id: 21, title: "Proceso de Contratación", text: "El proceso es: 1) Análisis de necesidades 2) Cotización 3) Firma contrato 4) 50% anticipo 5) Desarrollo. ¿Listo?", category: "sales", tags: ["proceso", "contratacion", "inicio"] },
    { id: 22, title: "Pagos y Facturación", text: "Aceptamos transferencia, tarjeta de crédito y PayPal. Emitimos factura electrónica. ¿Necesitas factura?", category: "sales", tags: ["pago", "facturacion", "metodos"] },
    { id: 23, title: "SEO Básico", text: "Incluimos SEO básico: meta tags, sitemap, estructura semántica. ¿Buscas posicionamiento específico?", category: "development", tags: ["seo", "posicionamiento", "google"] },
    { id: 24, title: "Analytics", text: "Configuramos Google Analytics gratis. ¿Quieres seguimiento de conversiones específicas?", category: "development", tags: ["analytics", "estadisticas", "seguimiento"] },
    { id: 25, title: "Garantía", text: "Ofrecemos 3 meses de garantía por defectos de desarrollo. ¿En qué puedo asistirte?", category: "support", tags: ["garantia", "postventa", "soporte"] },
    { id: 26, title: "Capacitación", text: "Incluimos 1 hora de capacitación para que manejes tu sitio. ¿Cuándo te viene bien?", category: "support", tags: ["capacitacion", "entrenamiento", "usuario"] },
    { id: 27, title: "Actualizaciones", text: "Las actualizaciones de seguridad son importantes. Ofrecemos planes de mantenimiento. ¿Te interesaría?", category: "support", tags: ["actualizaciones", "seguridad", "mantenimiento"] },
    { id: 28, title: "Precio General", text: "Los precios varían según complejidad. ¿Podrías describirme brevemente lo que necesitas?", category: "sales", tags: ["precio", "costo", "consultar"] },
    { id: 29, title: "Plazos", text: "Los plazos dependen de la complejidad. ¿Cuándo necesitas el proyecto terminado?", category: "sales", tags: ["plazo", "tiempo", "entrega"] },
    { id: 30, title: "Portafolio", text: "Puedes ver nuestro portafolio en [tu-sitio.com/portafolio]. ¿Qué tipo de proyecto te interesa ver?", category: "sales", tags: ["portafolio", "ejemplos", "trabajos"] }
];

// ==============================================
// SISTEMA DE ANÁLISIS DE CONTEXTO
// ==============================================
class ContextAnalyzer {
    constructor() {
        this.autoAnalysisEnabled = false;
        this.currentCategory = null;
    }
    
    analyzeConversation(messages) {
        if (!messages || messages.length === 0) {
            return { category: 'general', suggestions: this.getGeneralSuggestions() };
        }
        
        const recentMessages = messages.slice(-5);
        const conversationText = recentMessages.map(m => m.content).join(' ').toLowerCase();
        
        const keywordMap = {
            'development': ['sitio web', 'página web', 'desarrollo', 'programación', 'hosting', 'dominio', 'wordpress', 'react', 'php', 'ecommerce'],
            'technical': ['error', 'problema', 'no funciona', 'bug', 'lento', 'carga', '404', 'formulario', 'correo', 'email'],
            'sales': ['precio', 'costo', 'cotización', 'presupuesto', 'pago', 'factura', 'contrato', 'garantía'],
            'support': ['soporte', 'ayuda', 'asistencia', 'mantenimiento', 'actualización', 'capacitación'],
            'greeting': ['hola', 'buenos días', 'gracias', 'adiós', 'saludos', 'presentación']
        };
        
        const scores = {};
        for (const [category, keywords] of Object.entries(keywordMap)) {
            scores[category] = keywords.reduce((count, keyword) => {
                return count + (conversationText.includes(keyword) ? 1 : 0);
            }, 0);
        }
        
        let maxCategory = 'general';
        let maxScore = 0;
        
        for (const [category, score] of Object.entries(scores)) {
            if (score > maxScore) {
                maxScore = score;
                maxCategory = category;
            }
        }
        
        this.currentCategory = maxCategory;
        
        return {
            category: maxCategory,
            score: maxScore,
            suggestions: this.getContextualSuggestions(maxCategory, conversationText)
        };
    }
    
    getContextualSuggestions(category, conversationText) {
        let suggestions = [];
        
        const categoryReplies = quickRepliesDatabase.filter(reply => 
            reply.category === category
        );
        
        // Tomar primeras 3 de la categoría
        suggestions = categoryReplies.slice(0, 3);
        
        if (suggestions.length < 3) {
            const generalSuggestions = quickRepliesDatabase
                .filter(reply => reply.category !== category)
                .slice(0, 3 - suggestions.length);
            suggestions = [...suggestions, ...generalSuggestions];
        }
        
        return suggestions;
    }
    
    getGeneralSuggestions() {
        return quickRepliesDatabase
            .filter(reply => [1, 2, 5, 19, 15].includes(reply.id))
            .slice(0, 3);
    }
    
    enableAutoAnalysis() {
        this.autoAnalysisEnabled = true;
        return this.autoAnalysisEnabled;
    }
    
    disableAutoAnalysis() {
        this.autoAnalysisEnabled = false;
        return this.autoAnalysisEnabled;
    }
    
    isAutoAnalysisEnabled() {
        return this.autoAnalysisEnabled;
    }
}

// ==============================================
// INICIALIZACIÓN PRINCIPAL
// ==============================================
$(document).ready(function() {
    // Variables globales
    var currentArchivoConversacion = null;
    var contextAnalyzer = new ContextAnalyzer();
    var autoAnalysisInterval = null;
    var lastConversationData = null;
    var totalPendingConversations = 0;
    var isCurrentConversationPending = false;
    var botStatus = 'inactive'; // 'active' o 'inactive'
    var isAgentActive = false; // Variable para controlar si el agente está activo en la conversación actual
    
    // Inicializar DataTable
    var table = $('#conversacionesTable').DataTable({
        ajax: {
            url: 'ajax/obtener_conversaciones.php',
            dataSrc: '',
            dataFilter: function(data) {
                var json = JSON.parse(data);
                totalPendingConversations = 0;
                
                if (Array.isArray(json)) {
                    json.forEach(function(conv) {
                        // CORREGIDO: Solo marcar como pendiente si cumple los criterios específicos
                        conv.is_pending = determineIfPending(conv);
                        
                        if (conv.is_pending) {
                            totalPendingConversations++;
                        }
                    });
                }
                
                // Actualizar badge de pendientes
                updatePendingBadge(totalPendingConversations);
                
                return JSON.stringify(json);
            }
        },
        columns: [
            {
                data: null,
                width: '80px',
                render: function(data, type, row) {
                    return renderStatusIcon(data);
                }
            },
            { 
                data: 'telefono',
                render: function(data, type, row) {
                    if (row.is_pending) {
                        return `<strong class="text-danger">${data}</strong>`;
                    }
                    return data;
                }
            },
            { 
                data: null,
                render: function(data, type, row) {
                    // Mostrar cliente y nombre del archivo
                    let html = `<div>`;
                    if (row.nombre_cliente && row.nombre_cliente !== 'Sin nombre') {
                        html += `<strong>${row.nombre_cliente}</strong><br>`;
                    }
                    html += `<small class="text-muted">${row.archivo || 'Sin archivo'}</small>`;
                    
                    if (row.is_pending) {
                        const timeAgo = getTimeAgo(row.ultima_actualizacion);
                        html += ` <span class="badge-pending">Pendiente ${timeAgo}</span>`;
                    }
                    html += `</div>`;
                    return html;
                }
            },
            {
                data: null,
                render: function(data, type, row) {
                    if (row.ultimo_mensaje) {
                        const msg = row.ultimo_mensaje.length > 50 ? 
                            row.ultimo_mensaje.substring(0, 50) + '...' : 
                            row.ultimo_mensaje;
                        
                        if (row.is_pending) {
                            return `<span class="text-danger"><i class="bi bi-chat-left-dots"></i> ${msg}</span>`;
                        }
                        return `<span class="text-muted"><i class="bi bi-chat-left"></i> ${msg}</span>`;
                    }
                    return '<span class="text-muted">Sin mensajes</span>';
                }
            },
            {
                data: 'ultima_actualizacion',
                render: function(data) {
                    if (data) {
                        return new Date(data * 1000).toLocaleString('es-MX');
                    }
                    return '';
                }
            },
            {
                data: 'total_mensajes',
                render: function(data, type, row) {
                    if (row.is_pending) {
                        return `<span class="badge badge-danger">${data} <i class="bi bi-exclamation"></i></span>`;
                    }
                    return `<span class="badge badge-info">${data}</span>`;
                }
            },
            {
                data: null,
                width: '100px',
                render: function(data, type, row) {
                    if (row.is_pending) {
                        return `<button class="btn btn-danger btn-sm ver-conversacion" data-archivo="${data.archivo}" title="Conversación pendiente">
                                    <i class="bi bi-chat-left-text"></i> Atender
                                </button>`;
                    }
                    return `<button class="btn btn-success btn-sm ver-conversacion" data-archivo="${data.archivo}">
                                <i class="bi bi-chat-left-text"></i> Ver
                            </button>`;
                }
            }
        ],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-MX.json'
        },
        order: [[0, 'desc'], [4, 'desc']],
        pageLength: 25,
        createdRow: function(row, data, dataIndex) {
            // CORREGIDO: Solo agregar clase pending-row si realmente es pendiente
            if (data.is_pending) {
                $(row).addClass('pending-row');
                $(row).attr('title', 'Conversación pendiente de atención');
            } else {
                $(row).removeClass('pending-row');
            }
            // Comprobante de transferencia pendiente de acreditar
            if (data.comprobante_pendiente) {
                $(row).addClass('comprobante-pendiente-row');
                $(row).attr('title', '💳 Comprobante de pago enviado — pendiente de acreditar');
            }
        }
    });

    // ==============================================
    // FUNCIONES DE UTILIDAD
    // ==============================================

    // Determinar si una conversación está pendiente (CRITERIOS CORREGIDOS)
    function determineIfPending(conversation) {
        // Comprobante pendiente NO es "pendiente" en el sentido de sin responder, tiene su propio estado
        if (conversation.comprobante_pendiente) return false;

        // El bot marcó esta conversación como que necesita atención del agente
        if (conversation.needs_agent_attention === true || conversation.needs_agent_attention === '1') return true;

        // El último mensaje visible es del usuario (el bot/agente aún no respondió)
        if (conversation.ultimo_rol === 'user') return true;

        return false;
    }

    // Renderizar icono de estado
    function renderStatusIcon(data) {
        if (data.comprobante_pendiente) {
            return `<div title="💳 Comprobante enviado — pendiente de acreditar" style="color:#ffc107;font-size:1.3em;animation:blink 1.5s infinite;">
                      <i class="bi bi-cash-coin"></i>
                    </div>`;
        }
        if (data.is_pending) {
            const timeAgo = getTimeAgo(data.ultima_actualizacion);
            return `<div class="status-urgent" title="Pendiente desde ${timeAgo}">
                      <i class="bi bi-exclamation-circle-fill"></i>
                    </div>`;
        } else if (data.ultimo_rol === 'user') {
            return `<i class="bi bi-clock-history text-warning" title="Reciente"></i>`;
        } else {
            return `<i class="bi bi-check-circle-fill text-success" title="Atendida"></i>`;
        }
    }

    // Obtener tiempo transcurrido
    function getTimeAgo(timestamp) {
        if (!timestamp) return '';
        
        const date = new Date(timestamp * 1000);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / (1000 * 60));
        const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
        const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
        
        if (diffMins < 60) {
            return `hace ${diffMins} min`;
        } else if (diffHours < 24) {
            return `hace ${diffHours} h`;
        } else {
            return `hace ${diffDays} d`;
        }
    }

    // Actualizar badge de pendientes
    function updatePendingBadge(count) {
        const badge = $('#pendingMessagesBadge');
        const countSpan = $('#pendingCount');
        
        if (count > 0) {
            countSpan.text(count);
            badge.fadeIn();
            
            if (count > 5) {
                badge.addClass('blinking');
            } else {
                badge.removeClass('blinking');
            }
        } else {
            badge.fadeOut();
        }
    }

    // Mostrar notificación
    function showNotification(message, type = 'info') {
        const icon = {
            'success': 'bi-check-circle',
            'error': 'bi-exclamation-circle',
            'warning': 'bi-exclamation-triangle',
            'info': 'bi-info-circle'
        }[type];
        
        const toast = $(`
            <div class="toast" role="alert" data-delay="3000">
                <div class="toast-header bg-${type} text-white">
                    <i class="bi ${icon} mr-2"></i>
                    <strong class="mr-auto">Notificación</strong>
                    <button type="button" class="ml-2 mb-1 close text-white" data-dismiss="toast">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="toast-body">
                    ${message}
                </div>
            </div>
        `);
        
        $('body').append(toast);
        toast.toast('show');
        
        toast.on('hidden.bs.toast', function() {
            $(this).remove();
        });
    }

    // ==============================================
    // FUNCIONES PARA ACTIVAR/DESACTIVAR BOT
    // ==============================================

    function activateBot() {
        $.ajax({
            url: 'ajax/activar_bot.php',
            method: 'POST',
            data: { action: 'start' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    botStatus = 'active';
                    $('#startBotBtn')
                        .removeClass('bot-inactive')
                        .addClass('bot-active')
                        .html('<i class="bi bi-power"></i> Bot Activo');
                    showNotification('Bot de WhatsApp activado correctamente', 'success');
                    
                    // Recargar conversaciones
                    table.ajax.reload(null, false);
                } else {
                    showNotification('Error al activar bot: ' + response.error, 'error');
                }
            },
            error: function() {
                showNotification('Error de conexión al activar bot', 'error');
            }
        });
    }

    function deactivateBot() {
        $.ajax({
            url: 'ajax/activar_bot.php',
            method: 'POST',
            data: { action: 'stop' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    botStatus = 'inactive';
                    $('#startBotBtn')
                        .removeClass('bot-active')
                        .addClass('bot-inactive')
                        .html('<i class="bi bi-power"></i> Activar Bot');
                    showNotification('Bot de WhatsApp desactivado', 'warning');
                    
                    // Recargar conversaciones
                    table.ajax.reload(null, false);
                } else {
                    showNotification('Error al desactivar bot: ' + response.error, 'error');
                }
            },
            error: function() {
                showNotification('Error de conexión al desactivar bot', 'error');
            }
        });
    }

    function checkBotStatus() {
        $.ajax({
            url: 'ajax/check_bot_status.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'active') {
                    botStatus = 'active';
                    $('#startBotBtn')
                        .removeClass('bot-inactive')
                        .addClass('bot-active')
                        .html('<i class="bi bi-power"></i> Bot Activo');
                } else {
                    botStatus = 'inactive';
                    $('#startBotBtn')
                        .removeClass('bot-active')
                        .addClass('bot-inactive')
                        .html('<i class="bi bi-power"></i> Activar Bot');
                }
                
                // Recargar conversaciones con nuevo estado
                table.ajax.reload(null, false);
            },
            error: function() {
                // Si no puede verificar, asumir inactivo
                botStatus = 'inactive';
                $('#startBotBtn')
                    .removeClass('bot-active')
                    .addClass('bot-inactive')
                    .html('<i class="bi bi-power"></i> Activar Bot');
            }
        });
    }

    // ==============================================
    // NUEVA FUNCIÓN: REACTIVAR BOT PARA CONVERSACIÓN ACTUAL
    // ==============================================

    function reactivateBotForCurrentConversation() {
        var telefono = $('#currentTelefono').val();
        var archivo = $('#currentArchivo').val();
        
        if (!telefono) {
            Swal.fire({ 
                icon: 'error', 
                title: 'Error', 
                text: 'No hay conversación activa' 
            });
            return;
        }
        
        Swal.fire({
            title: '¿Reactivar el bot automático?',
            text: 'El asistente virtual responderá los próximos mensajes del cliente.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, reactivar bot',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                var $btn = $('#reactivarBotBtn');
                var originalHtml = $btn.html();
                $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Procesando...');
                
                $.ajax({
                    url: 'ajax/desactivar_agente_activo.php',
                    method: 'POST',
                    data: {
                        telefono: telefono
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            isAgentActive = false;
                            showNotification('Bot reactivado para esta conversación', 'success');
                            
                            // Actualizar botón en el modal
                            $('#reactivarBotBtn').hide();
                            
                            // Actualizar tabla
                            table.ajax.reload(null, false);
                            
                            // Si hay conversación abierta, recargarla para reflejar cambios
                            if (archivo && $('#conversacionModal').is(':visible')) {
                                setTimeout(function() {
                                    recargarConversacionSilencioso(archivo);
                                }, 1000);
                            }
                        } else {
                            Swal.fire({ 
                                icon: 'error', 
                                title: 'Error', 
                                text: response.error || 'Error desconocido al reactivar el bot' 
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error en la petición:', error);
                        Swal.fire({ 
                            icon: 'error', 
                            title: 'Error de conexión', 
                            text: 'No se pudo conectar con el servidor' 
                        });
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html(originalHtml);
                    }
                });
            }
        });
    }

    // ==============================================
    // NUEVA FUNCIÓN: ACREDITAR PAGO POR TRANSFERENCIA
    // ==============================================

    function acreditarPago() {
        var telefono = $('#currentTelefono').val();
        var archivo  = $('#currentArchivo').val();

        if (!telefono) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'No hay conversación activa' });
            return;
        }

        Swal.fire({
            title: '¿Acreditar pago del cliente?',
            text: 'Se confirmará el pago, se creará el ticket y se notificará al cliente por WhatsApp.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, acreditar pago',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                var $btn = $('#acreditarPagoBtn');
                var originalHtml = $btn.html();
                $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Acreditando...');

                $.ajax({
                    url: 'ajax/acreditar_pago.php',
                    method: 'POST',
                    data: { telefono: telefono },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $btn.hide();

                            // Quitar clase amarilla de la fila en la tabla
                            var $row = $('.ver-conversacion[data-archivo="' + archivo + '"]').closest('tr');
                            $row.removeClass('comprobante-pendiente-row');
                            table.ajax.reload(null, false);

                            if (archivo && $('#conversacionModal').is(':visible')) {
                                setTimeout(function() { recargarConversacionSilencioso(archivo); }, 1000);
                            }

                            if (!response.ticket_created) {
                                Swal.fire({
                                    icon: 'warning',
                                    title: 'Pago acreditado ✅',
                                    html: 'El cliente fue notificado por WhatsApp.<br><br>' +
                                          '<strong>⚠️ No se encontró una solicitud registrada en el sistema.</strong><br>' +
                                          'Este cliente envió un comprobante sin haber registrado una solicitud con el bot. Por favor crea el ticket manualmente.',
                                    confirmButtonText: 'Entendido'
                                });
                            } else {
                                var msg = '✅ Pago acreditado. Ticket #' + response.ticket_id + ' creado.';
                                if (!response.twilio_ok) { msg += ' ⚠️ No se pudo notificar al cliente por WhatsApp.'; }
                                showNotification(msg, 'success');
                            }
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: response.error || 'Error desconocido' });
                            $btn.prop('disabled', false).html(originalHtml);
                        }
                    },
                    error: function() {
                        Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo conectar con el servidor' });
                        $btn.prop('disabled', false).html(originalHtml);
                    }
                });
            }
        });
    }

    // ==============================================
    // FUNCIONES DE CONVERSACIÓN
    // ==============================================

    // Variables para autocompletado
    var autocompleteActive = false;
    var currentFilterCategory = 'all';

    // Mostrar respuestas rápidas
    function showQuickReplies() {
        autocompleteActive = true;
        const dropdown = $('#autocompleteDropdown');
        const list = $('#autocompleteList');
        
        list.empty();
        
        let filteredReplies = quickRepliesDatabase;
        if (currentFilterCategory !== 'all') {
            filteredReplies = quickRepliesDatabase.filter(reply => 
                reply.category === currentFilterCategory
            );
        }
        
        filteredReplies.forEach((reply, index) => {
            const categoryClass = `category-${reply.category}`;
            const item = $(`
                <div class="autocomplete-item" data-id="${reply.id}" data-index="${index}">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="autocomplete-item-title">${reply.title}</div>
                        <span class="autocomplete-item-category ${categoryClass}">${reply.category}</span>
                    </div>
                    <div class="autocomplete-item-text">${reply.text}</div>
                    <div class="mt-1">
                        ${reply.tags.map(tag => `<span class="badge badge-light badge-sm mr-1">${tag}</span>`).join('')}
                    </div>
                </div>
            `);
            list.append(item);
        });
        
        dropdown.show();
        setTimeout(() => $('#quickReplySearch').focus(), 100);
    }

    // Ocultar autocompletado
    function hideAutocomplete() {
        $('#autocompleteDropdown').hide();
        autocompleteActive = false;
        $('#messageInput').focus();
    }

    // Insertar respuesta rápida
    function insertQuickReply(id) {
        const reply = quickRepliesDatabase.find(r => r.id === id);
        if (!reply) return;
        
        $('#messageInput').val(reply.text);
        hideAutocomplete();
        showNotification(`"${reply.title}" insertado`, 'success');
    }

    // Mostrar sugerencias contextuales
    function showContextualSuggestions(suggestions) {
        const container = $('#suggestionsContainer');
        const suggestionsDiv = $('#contextualSuggestions');
        
        container.empty();
        
        if (suggestions && suggestions.length > 0) {
            suggestions.forEach(suggestion => {
                const button = $(`
                    <button type="button" class="btn btn-outline-info btn-sm suggestion-btn" 
                            data-id="${suggestion.id}" 
                            title="${suggestion.text}">
                        <i class="bi bi-reply"></i> ${suggestion.title}
                    </button>
                `);
                container.append(button);
            });
            
            suggestionsDiv.fadeIn(300);
        } else {
            suggestionsDiv.fadeOut(300);
        }
    }

    // Analizar contexto
    function analyzeConversationContext() {
        if (!lastConversationData || !lastConversationData.mensajes) {
            showNotification('No hay conversación para analizar', 'warning');
            return;
        }
        
        $('#contextAnalysisBadge').fadeIn();
        $('#analyzeContextBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Analizando...');
        
        setTimeout(() => {
            const analysis = contextAnalyzer.analyzeConversation(lastConversationData.mensajes);
            showContextualSuggestions(analysis.suggestions);
            
            $('#analyzeContextBtn').prop('disabled', false).html('<i class="bi bi-magic"></i> Analizar Contexto');
            $('#contextAnalysisBadge').fadeOut();
            
            showNotification(`Contexto analizado: ${analysis.category}`, 'info');
        }, 800);
    }

    // ==============================================
    // FUNCIÓN PARA ENVIAR PLANTILLA DE SALUDO INICIAL
    // ==============================================
    function enviarMensajePlantilla() {
        var telefono = $('#currentTelefono').val();
        var nombre   = $('#modalClientName').text().trim();

        if (!telefono) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'No hay teléfono seleccionado' });
            return;
        }

        var $btn = $('#enviarMensajeInicialBtn');
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Enviando...');

        $.ajax({
            url: 'ajax/enviar_mensaje_plantilla.php',
            method: 'POST',
            data: { telefono: telefono, nombre_cliente: nombre },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showNotification('Mensaje inicial enviado correctamente', 'success');
                    isAgentActive = true;
                    $('#reactivarBotBtn').show();
                    table.ajax.reload(null, false);
                    if (currentArchivoConversacion) {
                        setTimeout(function() {
                            recargarConversacionSilencioso(currentArchivoConversacion);
                        }, 1200);
                    }
                } else {
                    Swal.fire({ icon: 'error', title: 'Error al enviar', text: response.error || 'Error desconocido' });
                    $btn.prop('disabled', false).html('<i class="bi bi-send-fill"></i> Enviar Mensaje Inicial');
                }
            },
            error: function(xhr) {
                var msg = 'Error de conexión con el servidor';
                try {
                    var r = JSON.parse(xhr.responseText);
                    if (r.error) msg = r.error;
                } catch(e) {}
                Swal.fire({ icon: 'error', title: 'Error', text: msg });
                $btn.prop('disabled', false).html('<i class="bi bi-send-fill"></i> Enviar Mensaje Inicial');
            }
        });
    }

    // ==============================================
    // FUNCIÓN PARA ENVIAR MENSAJE
    // ==============================================
    function enviarMensaje() {
        var mensaje = $('#messageInput').val().trim();
        var telefono = $('#currentTelefono').val();
        
        if (!mensaje) {
            Swal.fire({ 
                icon: 'warning', 
                title: 'Mensaje vacío', 
                text: 'Por favor escribe un mensaje' 
            });
            return;
        }

        if (!telefono) {
            Swal.fire({ 
                icon: 'error', 
                title: 'Error', 
                text: 'No se ha seleccionado un teléfono' 
            });
            return;
        }

        var $sendBtn = $('#sendMessageBtn');
        var originalHtml = $sendBtn.html();
        $sendBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        $.ajax({
            url: 'ajax/enviar_mensaje_directo.php',
            method: 'POST',
            data: {
                telefono: telefono,
                mensaje: mensaje
            },
            dataType: 'json',
            success: function(response) {
                if (response.success === true) {
                    $('#messageInput').val('');
                    
                    // Mostrar mensaje en el chat
                    var userMessageHtml = `
                        <div class="clearfix">
                            <div class="message-bubble message-assistant">
                                <div>${mensaje}</div>
                                <div class="message-time">${new Date().toLocaleTimeString('es-MX', {hour: '2-digit', minute:'2-digit'})}</div>
                            </div>
                        </div>
                    `;
                    $('#chatArea').append(userMessageHtml);
                    $('#chatArea').scrollTop($('#chatArea')[0].scrollHeight);
                    
                    // Cuando enviamos un mensaje, el agente se activa automáticamente
                    isAgentActive = true;
                    $('#reactivarBotBtn').show();
                    
                    // Si era una conversación pendiente, marcar como atendida
                    if (isCurrentConversationPending) {
                        markConversationAsAttended(currentArchivoConversacion);
                        isCurrentConversationPending = false;
                        $('#marcarAtendidoBtn').hide();
                    }
                    
                    // Recargar tabla
                    table.ajax.reload(null, false);
                    
                    // Recargar conversación
                    if (currentArchivoConversacion) {
                        setTimeout(function() {
                            recargarConversacionSilencioso(currentArchivoConversacion);
                        }, 1000);
                    }
                    
                    showNotification('Mensaje enviado correctamente', 'success');
                    
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error al enviar',
                        text: response.error || 'Error desconocido'
                    });
                }
            },
            error: function(xhr, status, error) {
                var errorMsg = 'Error de conexión con el servidor';
                try {
                    var jsonResponse = JSON.parse(xhr.responseText);
                    if (jsonResponse.error) {
                        errorMsg = jsonResponse.error;
                    }
                } catch (e) {
                    if (xhr.responseText) {
                        errorMsg = xhr.responseText.substring(0, 100);
                    }
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error de conexión',
                    text: errorMsg
                });
            },
            complete: function() {
                $sendBtn.prop('disabled', false).html(originalHtml);
                $('#messageInput').focus();
            }
        });
    }

    // Marcar conversación como atendida
    function markConversationAsAttended(archivo) {
        // Actualizar interfaz
        const row = $(`.ver-conversacion[data-archivo="${archivo}"]`).closest('tr');
        if (row.length) {
            row.removeClass('pending-row');
            row.find('.btn-danger').removeClass('btn-danger').addClass('btn-success')
                .html('<i class="bi bi-chat-left-text"></i> Ver')
                .attr('title', '');
            
            // Actualizar contador
            if (totalPendingConversations > 0) {
                totalPendingConversations--;
                updatePendingBadge(totalPendingConversations);
            }
        }
        
        // Ocultar botón "Marcar como Atendido"
        $('#marcarAtendidoBtn').hide();
        
        showNotification('Conversación marcada como atendida', 'success');
    }

    // ==============================================
    // CARGAR CONVERSACIÓN
    // ==============================================
    $('#conversacionesTable').on('click', '.ver-conversacion', function() {
        var archivo = $(this).data('archivo');
        cargarConversacion(archivo);
        
        // Cambiar botón temporalmente si es pendiente
        if ($(this).hasClass('btn-danger')) {
            $(this).removeClass('btn-danger').addClass('btn-warning')
                   .html('<i class="bi bi-chat-left-text"></i> Atendiendo...');
        }
    });

    function cargarConversacion(archivo) {
        currentArchivoConversacion = archivo;
        
        $('#conversacionModal').modal('show');
        $('#chatArea').html('<div class="text-center"><div class="spinner-border text-success"></div><p>Cargando...</p></div>');
        $('#contextualSuggestions').hide();
        
        // Mostrar nombre del archivo en el título
        $('#modalFileName').text(archivo || 'Sin nombre');
        
        // Ocultar botones inicialmente
        $('#marcarAtendidoBtn').hide();
        $('#reactivarBotBtn').hide();
        $('#acreditarPagoBtn').hide();
        
        // Verificar si hay agente activo para esta conversación
        var telefono = ''; // Se actualizará cuando se cargue la conversación
        
        $.ajax({
            url: 'ajax/obtener_conversacion_detalle.php',
            method: 'GET',
            data: { archivo: archivo },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    lastConversationData = response.data;
                    
                    // Determinar si es pendiente
                    isCurrentConversationPending = determineIfPending(response.data);
                    
                    // Mostrar conversación
                    mostrarConversacion(response.data);
                    
                    // Verificar si el agente está activo en esta conversación
                    telefono = response.data.telefono_raw || response.data.telefono;
                    if (telefono && botStatus === 'active') {
                        $.ajax({
                            url: 'ajax/verificar_agente_activo.php',
                            method: 'POST',
                            data: { telefono: telefono },
                            dataType: 'json',
                            success: function(agentResponse) {
                                if (agentResponse.success) {
                                    isAgentActive = agentResponse.agente_activo;
                                    if (isAgentActive) {
                                        $('#reactivarBotBtn').show();
                                    }
                                }
                            },
                            error: function() {
                                // Si hay error, asumir que no hay agente activo
                                isAgentActive = false;
                            }
                        });
                    }
                    
                    // Mostrar botón "Marcar como Atendido" si es pendiente
                    if (isCurrentConversationPending) {
                        $('#marcarAtendidoBtn').show();
                    }
                    
                    // Análisis automático si está activo
                    if (contextAnalyzer.isAutoAnalysisEnabled()) {
                        setTimeout(() => {
                            const analysis = contextAnalyzer.analyzeConversation(response.data.mensajes);
                            showContextualSuggestions(analysis.suggestions);
                        }, 500);
                    }
                } else {
                    $('#chatArea').html('<div class="alert alert-danger">Error: ' + (response.error || 'Desconocido') + '</div>');
                }
            },
            error: function() {
                $('#chatArea').html('<div class="alert alert-danger">Error de conexión</div>');
            }
        });
    }

    function mostrarConversacion(data) {
        // Mostrar nombre del archivo
        $('#modalFileName').text(data.archivo || 'Sin nombre');
        
        $('#modalClientName').text(data.nombre_cliente || data.telefono);
        $('#modalClientPhone').text(data.telefono);
        $('#currentTelefono').val(data.telefono_raw || data.telefono);
        $('#currentArchivo').val(data.archivo || '');

        // Mostrar estado
        const statusDiv = $('#clientStatus');
        if (isCurrentConversationPending) {
            statusDiv.html('<span class="badge badge-danger"><i class="bi bi-exclamation-circle"></i> Pendiente de respuesta</span>');
        } else if (botStatus === 'inactive') {
            statusDiv.html('<span class="badge badge-warning"><i class="bi bi-power"></i> Bot inactivo</span>');
        } else if (isAgentActive) {
            statusDiv.html('<span class="badge badge-info"><i class="bi bi-person-fill"></i> Agente activo</span>');
        } else {
            statusDiv.html('<span class="badge badge-success"><i class="bi bi-check-circle"></i> Atendida</span>');
        }

        // Mostrar/ocultar botón Acreditar Pago según comprobante pendiente
        if (data.meta && data.meta.comprobante_pendiente) {
            $('#acreditarPagoBtn').show();
        } else {
            $('#acreditarPagoBtn').hide();
        }

        var html = '';
        var hasVisibleMessages = false;

        if (data.mensajes && data.mensajes.length > 0) {
            data.mensajes.forEach(function(msg) {
                if (msg.role !== 'system') {
                    hasVisibleMessages = true;
                    var bubbleClass = msg.role === 'user' ? 'message-user' : 'message-assistant';
                    var mediaHtml = '';
                    if (msg.media_url) {
                        var mt = msg.media_type || '';
                        if (mt.indexOf('image') !== -1) {
                            mediaHtml = `<div class="mt-1"><img src="${msg.media_url}" style="max-width:220px;max-height:220px;border-radius:8px;cursor:pointer;" onclick="window.open('${msg.media_url}','_blank')" title="Ver imagen completa"></div>`;
                        } else if (mt.indexOf('pdf') !== -1) {
                            mediaHtml = `<div class="mt-1"><a href="${msg.media_url}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-pdf"></i> Ver PDF</a></div>`;
                        } else {
                            mediaHtml = `<div class="mt-1"><a href="${msg.media_url}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-paperclip"></i> Ver archivo adjunto</a></div>`;
                        }
                    }
                    // Ocultar el placeholder si tenemos la imagen real
                    var rawContent = msg.content || '';
                    var isPlaceholder = rawContent.indexOf('[El usuario envió') === 0;
                    var content = isPlaceholder && mediaHtml ? '' : $('<div>').text(rawContent).html().replace(/\n/g, '<br>');
                    html += `
                        <div class="clearfix">
                            <div class="message-bubble ${bubbleClass}">
                                ${content ? `<div>${content}</div>` : ''}
                                ${mediaHtml}
                            </div>
                        </div>
                    `;
                }
            });
        }

        if (!hasVisibleMessages) {
            html = `
                <div class="text-center" style="padding-top: 80px;">
                    <i class="bi bi-chat-dots text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3 mb-4">No hay mensajes en esta conversación.</p>
                    <button type="button" class="btn btn-success btn-lg" id="enviarMensajeInicialBtn">
                        <i class="bi bi-send-fill"></i> Enviar Mensaje Inicial
                    </button>
                    <p class="text-muted mt-2" style="font-size:0.85em;">
                        <i class="bi bi-info-circle"></i> Envía la plantilla de saludo aprobada por WhatsApp
                    </p>
                </div>
            `;
        }

        $('#chatArea').html(html);
        $('#chatArea').scrollTop($('#chatArea')[0].scrollHeight);
    }

    function recargarConversacionSilencioso(archivo) {
        if (!archivo) return;
        
        $.ajax({
            url: 'ajax/obtener_conversacion_detalle.php',
            method: 'GET',
            data: { archivo: archivo },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    lastConversationData = response.data;
                    
                    // Actualizar si sigue siendo pendiente
                    isCurrentConversationPending = determineIfPending(response.data);
                    
                    var html = '';
                    var hasVisibleMessagesSilent = false;
                if (response.data.mensajes && response.data.mensajes.length > 0) {
                        response.data.mensajes.forEach(function(msg) {
                            if (msg.role !== 'system') {
                                hasVisibleMessagesSilent = true;
                                var bubbleClass = msg.role === 'user' ? 'message-user' : 'message-assistant';
                                var mediaHtml = '';
                                if (msg.media_url) {
                                    var mt = msg.media_type || '';
                                    if (mt.indexOf('image') !== -1) {
                                        mediaHtml = `<div class="mt-1"><img src="${msg.media_url}" style="max-width:220px;max-height:220px;border-radius:8px;cursor:pointer;" onclick="window.open('${msg.media_url}','_blank')" title="Ver imagen completa"></div>`;
                                    } else if (mt.indexOf('pdf') !== -1) {
                                        mediaHtml = `<div class="mt-1"><a href="${msg.media_url}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-pdf"></i> Ver PDF</a></div>`;
                                    } else {
                                        mediaHtml = `<div class="mt-1"><a href="${msg.media_url}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-paperclip"></i> Ver archivo adjunto</a></div>`;
                                    }
                                }
                                var rawContent = msg.content || '';
                                var isPlaceholder = rawContent.indexOf('[El usuario envió') === 0;
                                var content = isPlaceholder && mediaHtml ? '' : $('<div>').text(rawContent).html().replace(/\n/g, '<br>');
                                html += `
                                    <div class="clearfix">
                                        <div class="message-bubble ${bubbleClass}">
                                            ${content ? `<div>${content}</div>` : ''}
                                            ${mediaHtml}
                                        </div>
                                    </div>
                                `;
                            }
                        });
                }
                if (!hasVisibleMessagesSilent) {
                    html = `
                        <div class="text-center" style="padding-top: 80px;">
                            <i class="bi bi-chat-dots text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-3 mb-4">No hay mensajes en esta conversación.</p>
                            <button type="button" class="btn btn-success btn-lg" id="enviarMensajeInicialBtn">
                                <i class="bi bi-send-fill"></i> Enviar Mensaje Inicial
                            </button>
                            <p class="text-muted mt-2" style="font-size:0.85em;">
                                <i class="bi bi-info-circle"></i> Envía la plantilla de saludo aprobada por WhatsApp
                            </p>
                        </div>
                    `;
                }
                        $('#chatArea').html(html);
                        $('#chatArea').scrollTop($('#chatArea')[0].scrollHeight);
                        
                        // Actualizar estado
                        const statusDiv = $('#clientStatus');
                        if (isCurrentConversationPending) {
                            statusDiv.html('<span class="badge badge-danger"><i class="bi bi-exclamation-circle"></i> Pendiente de respuesta</span>');
                        } else if (botStatus === 'inactive') {
                            statusDiv.html('<span class="badge badge-warning"><i class="bi bi-power"></i> Bot inactivo</span>');
                        } else if (isAgentActive) {
                            statusDiv.html('<span class="badge badge-info"><i class="bi bi-person-fill"></i> Agente activo</span>');
                        } else {
                            statusDiv.html('<span class="badge badge-success"><i class="bi bi-check-circle"></i> Atendida</span>');
                        }
                        
                        // Actualizar botones
                        if (isCurrentConversationPending) {
                            $('#marcarAtendidoBtn').show();
                        } else {
                            $('#marcarAtendidoBtn').hide();
                        }
                        
                        // Análisis automático
                        if (contextAnalyzer.isAutoAnalysisEnabled() && lastConversationData) {
                            const analysis = contextAnalyzer.analyzeConversation(lastConversationData.mensajes);
                            showContextualSuggestions(analysis.suggestions);
                        }
                }
            }
        });
    }

    // ==============================================
    // EVENT HANDLERS
    // ==============================================

    // Botón activar/desactivar bot
    $('#startBotBtn').on('click', function() {
        if (botStatus === 'inactive') {
            activateBot();
        } else {
            Swal.fire({
                title: '¿Desactivar bot?',
                text: 'El bot dejará de responder automáticamente a los mensajes',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, desactivar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    deactivateBot();
                }
            });
        }
    });

    // Botón respuestas rápidas
    $('#showQuickRepliesBtn').on('click', showQuickReplies);
    
    // Cerrar autocompletado
    $('#closeAutocomplete').on('click', hideAutocomplete);
    
    // Búsqueda en respuestas rápidas
    $('#quickReplySearch').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        const items = $('.autocomplete-item');
        items.each(function() {
            const text = $(this).text().toLowerCase();
            $(this).toggle(text.includes(searchTerm));
        });
    });

    // Filtros de categoría
    $('.category-filter').on('click', function() {
        const category = $(this).data('category');
        currentFilterCategory = category;
        $('.category-filter').removeClass('active');
        $(this).addClass('active');
        if (autocompleteActive) showQuickReplies();
    });

    // Seleccionar respuesta rápida
    $(document).on('click', '.autocomplete-item', function() {
        const id = parseInt($(this).data('id'));
        insertQuickReply(id);
    });

    // Seleccionar sugerencia contextual
    $(document).on('click', '.suggestion-btn', function() {
        const id = parseInt($(this).data('id'));
        insertQuickReply(id);
        $('#contextualSuggestions').fadeOut();
    });

    // Botón analizar contexto
    $('#analyzeContextBtn').on('click', analyzeConversationContext);

    // Botón toggle auto-análisis
    $('#toggleAutoAnalysisBtn').on('click', function() {
        if (contextAnalyzer.isAutoAnalysisEnabled()) {
            contextAnalyzer.disableAutoAnalysis();
            $(this).removeClass('btn-info').addClass('btn-outline-info');
            $('#autoAnalysisStatus').text('OFF');
            showNotification('Análisis automático desactivado', 'warning');
            $('#contextualSuggestions').fadeOut();
            
            if (autoAnalysisInterval) {
                clearInterval(autoAnalysisInterval);
                autoAnalysisInterval = null;
            }
        } else {
            contextAnalyzer.enableAutoAnalysis();
            $(this).removeClass('btn-outline-info').addClass('btn-info');
            $('#autoAnalysisStatus').text('ON');
            showNotification('Análisis automático activado', 'success');
            
            if (lastConversationData) {
                const analysis = contextAnalyzer.analyzeConversation(lastConversationData.mensajes);
                showContextualSuggestions(analysis.suggestions);
            }
            
            autoAnalysisInterval = setInterval(() => {
                if (lastConversationData && $('#conversacionModal').is(':visible')) {
                    const analysis = contextAnalyzer.analyzeConversation(lastConversationData.mensajes);
                    showContextualSuggestions(analysis.suggestions);
                }
            }, 30000);
        }
    });

    // Botón marcar como atendido
    $('#marcarAtendidoBtn').on('click', function() {
        if (currentArchivoConversacion) {
            markConversationAsAttended(currentArchivoConversacion);
        }
    });

    // NUEVO: Botón reactivar bot
    $('#reactivarBotBtn').on('click', function() {
        reactivateBotForCurrentConversation();
    });

    // NUEVO: Botón acreditar pago de transferencia
    $('#acreditarPagoBtn').on('click', function() {
        acreditarPago();
    });

    // Botón enviar mensaje inicial (plantilla) — delegación porque se crea dinámicamente
    $(document).on('click', '#enviarMensajeInicialBtn', function() {
        enviarMensajePlantilla();
    });

    // Navegación con teclado
    $(document).on('keydown', function(e) {
        if (!autocompleteActive) return;
        if (e.key === 'Escape') hideAutocomplete();
    });

    // Tecla / para respuestas rápidas
    $('#messageInput').on('keydown', function(e) {
        if (e.key === '/' && !autocompleteActive) {
            e.preventDefault();
            showQuickReplies();
        }
        
        if (e.key === 'Enter' && !e.shiftKey && !autocompleteActive) {
            e.preventDefault();
            enviarMensaje();
        }
    });

    // Botón enviar mensaje
    $('#sendMessageBtn').on('click', function() {
        if (!autocompleteActive) {
            enviarMensaje();
        }
    });

    // Cerrar modal
    $('#conversacionModal').on('hidden.bs.modal', function() {
        currentArchivoConversacion = null;
        lastConversationData = null;
        isCurrentConversationPending = false;
        isAgentActive = false;
        hideAutocomplete();
        $('#contextualSuggestions').hide();
        $('#marcarAtendidoBtn').hide();
        $('#reactivarBotBtn').hide();
        $('#acreditarPagoBtn').hide();
        table.ajax.reload(null, false);
        
        if (contextAnalyzer.isAutoAnalysisEnabled()) {
            contextAnalyzer.disableAutoAnalysis();
            $('#toggleAutoAnalysisBtn').removeClass('btn-info').addClass('btn-outline-info');
            $('#autoAnalysisStatus').text('OFF');
            
            if (autoAnalysisInterval) {
                clearInterval(autoAnalysisInterval);
                autoAnalysisInterval = null;
            }
        }
    });

    // Recargar tabla periódicamente
    setInterval(function() {
        if (!document.hidden) {
            table.ajax.reload(null, false);
        }
    }, 10000);

    // Verificar estado del bot periódicamente
    setInterval(function() {
        if (!document.hidden) {
            checkBotStatus();
        }
    }, 15000);

    // Inicializar al cargar la página
    $(function () {
        $('[title]').tooltip();
        checkBotStatus(); // Verificar estado del bot al cargar
    });
});
</script>
</div><!-- menu container-fluid -->
</div><!-- menu content-wrapper -->
</body>
</html>