<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Clientes potenciales - Facebook Ads</title>
  <style>
    body { 
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      padding: 20px;
      margin: 0;
    }
    .container {
      max-width: 1200px;
      margin: 0 auto;
      background: white;
      border-radius: 12px;
      padding: 30px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    }
    h1 {
      color: #333;
      margin-bottom: 10px;
    }
    .config {
      background: #f8f9fa;
      padding: 20px;
      border-radius: 8px;
      margin-bottom: 20px;
      border-left: 4px solid #667eea;
    }
    .config input, .config select {
      width: 100%;
      padding: 10px;
      margin: 8px 0;
      border: 1px solid #ddd;
      border-radius: 6px;
      font-size: 14px;
      box-sizing: border-box;
    }
    .radio-group {
      margin: 15px 0;
    }
    .radio-group label {
      display: block;
      padding: 10px;
      margin: 5px 0;
      background: white;
      border: 2px solid #ddd;
      border-radius: 6px;
      cursor: pointer;
      transition: all 0.3s;
    }
    .radio-group input[type="radio"] {
      margin-right: 10px;
      width: auto;
    }
    .radio-group label:hover {
      border-color: #667eea;
      background: #f0f4ff;
    }
    .radio-group input[type="radio"]:checked + span {
      font-weight: bold;
      color: #667eea;
    }
    button {
      background: #667eea;
      color: white;
      border: none;
      padding: 12px 30px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 16px;
      font-weight: 600;
      transition: all 0.3s;
    }
    button:hover {
      background: #5568d3;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }
    button:disabled {
      background: #ccc;
      cursor: not-allowed;
      transform: none;
    }
    .status {
      padding: 12px;
      border-radius: 6px;
      margin: 10px 0;
      font-weight: 500;
    }
    .status.info { background: #d1ecf1; color: #0c5460; border-left: 4px solid #17a2b8; }
    .status.success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
    .status.warning { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; }
    .status.error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
    pre {
      background: #282c34;
      color: #abb2bf;
      padding: 20px;
      border-radius: 8px;
      overflow-x: auto;
      font-size: 13px;
      line-height: 1.6;
    }
    .lead-card {
      background: white;
      border: 1px solid #e0e0e0;
      border-radius: 8px;
      padding: 20px;
      margin: 15px 0;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      transition: all 0.3s;
    }
    .lead-card:hover {
      box-shadow: 0 4px 16px rgba(0,0,0,0.15);
      transform: translateY(-2px);
    }
    .lead-card h3 {
      margin: 0 0 15px 0;
      color: #667eea;
      border-bottom: 2px solid #667eea;
      padding-bottom: 10px;
    }
    .lead-field {
      display: grid;
      grid-template-columns: 200px 1fr;
      gap: 10px;
      padding: 8px 0;
      border-bottom: 1px solid #f0f0f0;
    }
    .lead-field:last-child {
      border-bottom: none;
    }
    .lead-field strong {
      color: #555;
    }
    .stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin: 20px 0;
    }
    .stat-card {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 20px;
      border-radius: 8px;
      text-align: center;
    }
    .stat-card h2 {
      margin: 0;
      font-size: 36px;
    }
    .stat-card p {
      margin: 10px 0 0 0;
      opacity: 0.9;
    }
    .loading {
      display: inline-block;
      width: 20px;
      height: 20px;
      border: 3px solid #f3f3f3;
      border-top: 3px solid #667eea;
      border-radius: 50%;
      animation: spin 1s linear infinite;
      vertical-align: middle;
      margin-left: 10px;
    }
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    .export-btn {
      background: #28a745;
      margin-left: 10px;
    }
    .export-btn:hover {
      background: #218838;
    }
    .help-box {
      background: #fff3cd;
      border-left: 4px solid #ffc107;
      padding: 15px;
      border-radius: 6px;
      margin: 20px 0;
    }
    .help-box h4 {
      margin: 0 0 10px 0;
      color: #856404;
    }
    .critical-box {
      background: #f8d7da;
      border-left: 4px solid #dc3545;
      padding: 15px;
      border-radius: 6px;
      margin: 20px 0;
    }
    .critical-box h4 {
      margin: 0 0 10px 0;
      color: #721c24;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>📊 Recuperador de Clientes Potenciales - Facebook</h1>
    <p style="color: #666; margin-bottom: 30px;">Obtén todos los leads de tus formularios de Facebook</p>
    
    <div class="config">
      <h3>⚙️ Configuración</h3>
      
      <div class="radio-group">
        <strong>Selecciona el método de búsqueda:</strong>
        <label>
          <input type="radio" name="method" value="page" checked>
          <span>🔵 Buscar desde Página (Recomendado)</span>
        </label>
        <label>
          <input type="radio" name="method" value="adaccount">
          <span>🟢 Buscar desde Anuncios (Método alternativo)</span>
        </label>
      </div>

      <label id="pageIdLabel">
        <strong>ID de la Página:</strong>
        <input type="text" id="pageId" value="106656890882615" placeholder="ID de tu página de Facebook">
      </label>

      <label id="adAccountLabel" style="display:none;">
        <strong>ID de Cuenta de Anuncios:</strong>
        <input type="text" id="adAccountId" placeholder="act_123456789 o 123456789">
        <small style="color: #666; display: block; margin-top: 5px;">
          💡 Encuéntralo en: Meta Business Suite → Configuración → Cuentas de anuncios
        </small>
      </label>

      <label>
        <strong>Token de Acceso:</strong>
        <input type="password" id="accessToken" value="" placeholder="Tu Page Access Token">
      </label>

      <label>
        <strong>Versión de API:</strong>
        <select id="apiVersion">
          <option value="v24.0" selected>v24.0 (Más reciente)</option>
          <option value="v20.0">v20.0</option>
          <option value="v19.0">v19.0</option>
        </select>
      </label>

      <div style="margin-top: 20px;">
        <button id="startBtn" onclick="startRetrieval()">🚀 Obtener Leads</button>
        <button id="exportBtn" class="export-btn" onclick="exportToCSV()" style="display:none;">📥 Exportar CSV</button>
        <button onclick="clearResults()" style="background: #6c757d;">🗑️ Limpiar</button>
      </div>
    </div>

    <div class="critical-box">
      <h4>⚠️ IMPORTANTE: Sobre los leads de Facebook</h4>
      <p><strong>Facebook tiene una política estricta de retención de leads:</strong></p>
      <ul>
        <li>Los leads se eliminan automáticamente después de <strong>90 días</strong></li>
        <li>Una vez descargados mediante la API, pueden ser eliminados inmediatamente</li>
        <li>Si ya descargaste los leads antes, es posible que ya no estén disponibles</li>
      </ul>
      <p>✅ <strong>Solución:</strong> Configura una descarga automática periódica o usa CRM integrado con Facebook.</p>
    </div>

    <div class="help-box" id="helpBox" style="display:none;">
      <h4>🔍 Análisis de respuesta de la API</h4>
      <p>Vamos a verificar la respuesta exacta de Facebook para diagnosticar el problema...</p>
    </div>

    <div id="stats" style="display:none;">
      <div class="stats">
        <div class="stat-card">
          <h2 id="totalForms">0</h2>
          <p>Formularios encontrados</p>
        </div>
        <div class="stat-card">
          <h2 id="totalLeads">0</h2>
          <p>Leads recuperados</p>
        </div>
      </div>
    </div>

    <div id="output"></div>
  </div>

  <script>
    let allLeads = [];
    
    document.querySelectorAll('input[name="method"]').forEach(radio => {
      radio.addEventListener('change', function() {
        const adAccountLabel = document.getElementById('adAccountLabel');
        const pageIdLabel = document.getElementById('pageIdLabel');
        
        if (this.value === 'adaccount') {
          adAccountLabel.style.display = 'block';
          pageIdLabel.style.display = 'none';
        } else {
          adAccountLabel.style.display = 'none';
          pageIdLabel.style.display = 'block';
        }
      });
    });
    
    function log(message, type = 'info') {
      const output = document.getElementById('output');
      const div = document.createElement('div');
      div.className = `status ${type}`;
      div.innerHTML = message;
      output.appendChild(div);
      output.scrollTop = output.scrollHeight;
    }

    function logJSON(title, data) {
      const output = document.getElementById('output');
      const div = document.createElement('div');
      div.innerHTML = `<h3>${title}</h3>`;
      const pre = document.createElement('pre');
      pre.textContent = JSON.stringify(data, null, 2);
      div.appendChild(pre);
      output.appendChild(div);
    }

    function showLeadCard(formName, lead, index) {
      const output = document.getElementById('output');
      const card = document.createElement('div');
      card.className = 'lead-card';
      
      let fieldsHTML = '';
      if (lead.field_data && lead.field_data.length > 0) {
        lead.field_data.forEach(field => {
          fieldsHTML += `
            <div class="lead-field">
              <strong>${field.name}:</strong>
              <span>${field.values.join(', ')}</span>
            </div>
          `;
        });
      } else {
        fieldsHTML = '<p style="color: #999;">Sin datos de campos disponibles</p>';
      }

      const adInfo = lead.ad_name ? `
        <div class="lead-field">
          <strong>📢 Anuncio:</strong>
          <span>${lead.ad_name || 'N/A'}</span>
        </div>
        <div class="lead-field">
          <strong>🎯 Campaña:</strong>
          <span>${lead.campaign_name || 'N/A'}</span>
        </div>
      ` : '';

      card.innerHTML = `
        <h3>📝 Lead #${index} - ${formName}</h3>
        <div class="lead-field">
          <strong>🆔 ID:</strong>
          <span>${lead.id}</span>
        </div>
        <div class="lead-field">
          <strong>📅 Fecha:</strong>
          <span>${new Date(lead.created_time).toLocaleString('es-MX', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
          })}</span>
        </div>
        ${adInfo}
        ${fieldsHTML}
      `;
      output.appendChild(card);
    }

    async function getLeadsFromAds(adAccountId, apiVersion, accessToken) {
      log('🔍 Método alternativo: Buscando leads desde anuncios activos...', 'info');
      
      const cleanAdAccountId = adAccountId.replace('act_', '');
      
      // Obtener campañas activas
      const campaignsEndpoint = `https://graph.facebook.com/${apiVersion}/act_${cleanAdAccountId}/campaigns?fields=id,name&effective_status=["ACTIVE","PAUSED"]&access_token=${accessToken}`;
      const campaignsRes = await fetch(campaignsEndpoint);
      const campaignsData = await campaignsRes.json();
      
      if (campaignsData.error) {
        log(`❌ Error al obtener campañas: ${campaignsData.error.message}`, 'error');
        return [];
      }
      
      const campaigns = campaignsData.data || [];
      log(`✅ ${campaigns.length} campaña(s) encontrada(s)`, 'success');
      
      let allLeads = [];
      
      for (const campaign of campaigns) {
        // Obtener ads de la campaña
        const adsEndpoint = `https://graph.facebook.com/${apiVersion}/${campaign.id}/ads?fields=id,name,lead_gen_form_id&access_token=${accessToken}`;
        const adsRes = await fetch(adsEndpoint);
        const adsData = await adsRes.json();
        
        if (adsData.error) continue;
        
        const ads = adsData.data || [];
        
        for (const ad of ads) {
          if (ad.lead_gen_form_id) {
            // Obtener leads del formulario
            const leadsEndpoint = `https://graph.facebook.com/${apiVersion}/${ad.lead_gen_form_id}/leads?fields=id,created_time,field_data,ad_id,ad_name,campaign_name&access_token=${accessToken}`;
            const leadsRes = await fetch(leadsEndpoint);
            const leadsData = await leadsRes.json();
            
            if (leadsData.data && leadsData.data.length > 0) {
              leadsData.data.forEach(lead => {
                lead.form_name = ad.name;
                lead.form_id = ad.lead_gen_form_id;
              });
              allLeads = allLeads.concat(leadsData.data);
            }
          }
        }
      }
      
      return allLeads;
    }

    async function startRetrieval() {
      const method = document.querySelector('input[name="method"]:checked').value;
      const pageId = document.getElementById('pageId').value.trim();
      const adAccountId = document.getElementById('adAccountId').value.trim();
      const accessToken = document.getElementById('accessToken').value.trim();
      const apiVersion = document.getElementById('apiVersion').value;
      const startBtn = document.getElementById('startBtn');
      const output = document.getElementById('output');
      
      if (!accessToken) {
        log('❌ Por favor ingresa el token de acceso', 'error');
        return;
      }

      if (method === 'page' && !pageId) {
        log('❌ Por favor ingresa el ID de la página', 'error');
        return;
      }

      if (method === 'adaccount' && !adAccountId) {
        log('❌ Por favor ingresa el ID de la cuenta de anuncios', 'error');
        return;
      }

      startBtn.disabled = true;
      startBtn.innerHTML = '⏳ Procesando... <span class="loading"></span>';
      output.innerHTML = '';
      allLeads = [];
      document.getElementById('stats').style.display = 'none';
      document.getElementById('exportBtn').style.display = 'none';
      document.getElementById('helpBox').style.display = 'none';

      try {
        log('🔍 Verificando token y permisos...', 'info');
        
        const debugEndpoint = `https://graph.facebook.com/${apiVersion}/debug_token?input_token=${accessToken}&access_token=${accessToken}`;
        const debugRes = await fetch(debugEndpoint);
        const debugData = await debugRes.json();
        
        if (debugData.data) {
          const expireDate = debugData.data.expires_at ? new Date(debugData.data.expires_at * 1000).toLocaleDateString('es-MX') : 'Nunca';
          log(`✅ Token válido | App: ${debugData.data.app_id} | Expira: ${expireDate}`, 'success');
          
          if (debugData.data.scopes) {
            const hasLeadRetrieval = debugData.data.scopes.includes('leads_retrieval');
            if (!hasLeadRetrieval) {
              log('⚠️ ADVERTENCIA: El token NO tiene el permiso "leads_retrieval"', 'warning');
            } else {
              log('✅ Permiso "leads_retrieval" confirmado', 'success');
            }
          }
        }

        let totalLeads = 0;
        let totalForms = 0;
        
        if (method === 'page') {
          // Método desde página
          log('📋 Obteniendo formularios desde la página...', 'info');
          const formsEndpoint = `https://graph.facebook.com/${apiVersion}/${pageId}/leadgen_forms?access_token=${accessToken}`;
          const formsRes = await fetch(formsEndpoint);
          const formsData = await formsRes.json();

          if (formsData.error) {
            log(`❌ Error al obtener formularios: ${formsData.error.message}`, 'error');
            logJSON('Detalles del error', formsData.error);
            return;
          }

          const forms = formsData.data || [];
          totalForms = forms.length;
          log(`✅ ${forms.length} formulario(s) encontrado(s)`, 'success');
          
          // Mostrar análisis detallado
          document.getElementById('helpBox').style.display = 'block';
          
          for (const form of forms) {
            log(`🔎 Analizando: "${form.name}" (ID: ${form.id})`, 'info');
            
            // Probar acceso directo al formulario primero
            const formCheckEndpoint = `https://graph.facebook.com/${apiVersion}/${form.id}?fields=id,name,status,leads_count&access_token=${accessToken}`;
            const formCheckRes = await fetch(formCheckEndpoint);
            const formCheckData = await formCheckRes.json();
            
            if (formCheckData.leads_count !== undefined) {
              log(`📊 Formulario tiene ${formCheckData.leads_count} lead(s) según Facebook`, formCheckData.leads_count > 0 ? 'success' : 'warning');
            }
            
            // Intentar obtener leads con múltiples configuraciones
            const fieldConfigs = [
              'id,created_time,field_data,ad_id,ad_name,adset_name,campaign_name,form_id,is_organic,platform',
              'id,created_time,field_data',
              'id,created_time'
            ];
            
            let leadsFound = false;
            
            for (const fields of fieldConfigs) {
              const leadsEndpoint = `https://graph.facebook.com/${apiVersion}/${form.id}/leads?fields=${fields}&limit=1000&access_token=${accessToken}`;
              const leadsRes = await fetch(leadsEndpoint);
              const leadsData = await leadsRes.json();
              
              // Mostrar respuesta cruda para diagnóstico
              logJSON(`📡 Respuesta API para "${form.name}"`, leadsData);
              
              if (leadsData.error) {
                log(`⚠️ Error al intentar obtener leads: ${leadsData.error.message}`, 'warning');
                continue;
              }
              
              if (leadsData.data) {
                const leads = leadsData.data;
                
                if (leads.length > 0) {
                  log(`✅ ¡${leads.length} lead(s) encontrado(s) en "${form.name}"!`, 'success');
                  leadsFound = true;
                  totalLeads += leads.length;
                  
                  leads.forEach(lead => {
                    lead.form_name = form.name;
                    lead.form_id = form.id;
                    allLeads.push(lead);
                  });
                  
                  // Paginación
                  let nextPage = leadsData.paging?.next;
                  while (nextPage && totalLeads < 10000) {
                    const nextRes = await fetch(nextPage);
                    const nextData = await nextRes.json();
                    if (nextData.data && nextData.data.length > 0) {
                      nextData.data.forEach(lead => {
                        lead.form_name = form.name;
                        lead.form_id = form.id;
                        allLeads.push(lead);
                      });
                      totalLeads += nextData.data.length;
                      nextPage = nextData.paging?.next;
                    } else {
                      break;
                    }
                  }
                  
                  break;
                } else {
                  log(`📭 El formulario "${form.name}" devolvió un array vacío`, 'warning');
                }
              }
            }
            
            if (!leadsFound) {
              log(`❌ No se pudieron recuperar leads de "${form.name}" con ninguna configuración`, 'error');
            }
            
            await new Promise(resolve => setTimeout(resolve, 500));
          }
          
        } else {
          // Método desde anuncios
          const leads = await getLeadsFromAds(adAccountId, apiVersion, accessToken);
          allLeads = leads;
          totalLeads = leads.length;
        }

        document.getElementById('totalForms').textContent = totalForms;
        document.getElementById('totalLeads').textContent = totalLeads;
        document.getElementById('stats').style.display = 'block';
        
        if (totalLeads > 0) {
          log(`🎉 ¡Excelente! Mostrando ${totalLeads} lead(s) recuperados...`, 'success');
          
          allLeads.forEach((lead, idx) => {
            showLeadCard(lead.form_name, lead, idx + 1);
          });
          
          document.getElementById('exportBtn').style.display = 'inline-block';
        } else {
          log('⚠️ NO SE ENCONTRARON LEADS DISPONIBLES', 'error');
          log('', 'info');
          log('🔍 <strong>Diagnóstico del problema:</strong>', 'info');
          log('', 'info');
          log('✅ Token válido y con permisos correctos', 'success');
          log('✅ Formularios encontrados y accesibles', 'success');
          log('❌ Los formularios NO contienen leads disponibles', 'error');
          log('', 'info');
          log('<strong>📌 Posibles causas:</strong>', 'warning');
          log('1️⃣ <strong>Los leads ya fueron descargados:</strong> Facebook elimina leads después de ser descargados mediante la API', 'info');
          log('2️⃣ <strong>Retención expirada:</strong> Facebook elimina automáticamente los leads después de 90 días', 'info');
          log('3️⃣ <strong>Sin envíos:</strong> Los formularios no han recibido envíos recientemente', 'info');
          log('', 'info');
          log('💡 <strong>Solución recomendada:</strong>', 'success');
          log('• Verifica en el Administrador de Anuncios de Facebook si realmente hay leads pendientes', 'info');
          log('• Configura una descarga automática periódica para evitar pérdida de datos', 'info');
          log('• Considera usar un CRM integrado con Facebook Lead Ads', 'info');
        }

      } catch (error) {
        log(`❌ Error general: ${error.message}`, 'error');
        console.error(error);
      } finally {
        startBtn.disabled = false;
        startBtn.innerHTML = '🚀 Obtener Leads';
      }
    }

    function exportToCSV() {
      if (allLeads.length === 0) {
        log('❌ No hay leads para exportar', 'error');
        return;
      }

      const headers = ['ID', 'Fecha', 'Formulario', 'Form ID', 'Campaña', 'Anuncio'];
      const fieldNames = new Set();
      
      allLeads.forEach(lead => {
        if (lead.field_data) {
          lead.field_data.forEach(field => fieldNames.add(field.name));
        }
      });
      
      headers.push(...Array.from(fieldNames));

      const rows = allLeads.map(lead => {
        const row = [
          lead.id,
          new Date(lead.created_time).toLocaleString('es-MX'),
          lead.form_name || 'N/A',
          lead.form_id || 'N/A',
          lead.campaign_name || 'N/A',
          lead.ad_name || 'N/A'
        ];
        
        fieldNames.forEach(fieldName => {
          const field = lead.field_data?.find(f => f.name === fieldName);
          row.push(field ? field.values.join('; ') : '');
        });
        
        return row;
      });

      const csv = [headers, ...rows]
        .map(row => row.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(','))
        .join('\n');

      const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
      const link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.download = `facebook_leads_${new Date().toISOString().split('T')[0]}.csv`;
      link.click();
      
      log('✅ Archivo CSV descargado exitosamente', 'success');
    }

    function clearResults() {
      document.getElementById('output').innerHTML = '';
      document.getElementById('stats').style.display = 'none';
      document.getElementById('exportBtn').style.display = 'none';
      document.getElementById('helpBox').style.display = 'none';
      allLeads = [];
    }
  </script>
</body>
</html>