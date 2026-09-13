<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import AppSidebar from '@/components/layout/AppSidebar.vue'
import RecordatoriosKpis from '@/components/recordatorios/RecordatoriosKpis.vue'
import RecordatoriosFilters from '@/components/recordatorios/RecordatoriosFilters.vue'
import RecordatoriosTable from '@/components/recordatorios/RecordatoriosTable.vue'
import RecordatorioPreviewModal from '@/components/recordatorios/RecordatorioPreviewModal.vue'
import RecordatoriosBatchModal from '@/components/recordatorios/RecordatoriosBatchModal.vue'
import AppToast from '@/components/common/AppToast.vue'
import {
  recordatoriosApi,
  type RecordatorioItem,
  type RecordatoriosKpis as KpisType,
  type RecordatorioPreviewResponse,
  type RecordatorioSendResponse,
} from '@/api/recordatorios'

const isMobileOpen = ref(false)
const loading = ref(false)
const items = ref<RecordatorioItem[]>([])
const selectedIds = ref<string[]>([])

// KPIs iniciales
const kpis = reactive<KpisType>({
  total_pendientes: 0,
  vencidos: 0,
  proximos_7_dias: 0,
  con_correo_valido: 0,
  monto_total_pendiente_mxn: 0,
  monto_total_pendiente_usd: 0,
})

// Filtros
const search = ref('')
const selectedTipo = ref('todos')
const selectedEstado = ref('todos')

// Toast
const toast = reactive({
  visible: false,
  message: '',
  type: 'success' as 'success' | 'error' | 'info',
})

function showToast(msg: string, type: 'success' | 'error' | 'info' = 'success') {
  toast.message = msg
  toast.type = type
  toast.visible = true
}

// Modales
const previewModal = reactive({
  visible: false,
  loading: false,
  sending: false,
  data: null as RecordatorioPreviewResponse | null,
  activeItem: null as RecordatorioItem | null,
})

const batchModal = reactive({
  visible: false,
  sending: false,
  result: null as RecordatorioSendResponse | null,
})

async function fetchPendientes() {
  loading.value = true
  try {
    const res = await recordatoriosApi.getPendientes({
      search: search.value || undefined,
      tipo: selectedTipo.value,
      estado: selectedEstado.value,
    })
    items.value = res.data.items
    Object.assign(kpis, res.data.kpis)
    // Limpiar seleccionados que ya no existan
    const existingIds = new Set(items.value.map(it => it.id))
    selectedIds.value = selectedIds.value.filter(id => existingIds.has(id))
  } catch (err: any) {
    showToast(err.response?.data?.detail || 'Error al cargar recordatorios pendientes', 'error')
  } finally {
    loading.value = false
  }
}

// Selección
function toggleSelect(id: string) {
  if (selectedIds.value.includes(id)) {
    selectedIds.value = selectedIds.value.filter(i => i !== id)
  } else {
    selectedIds.value.push(id)
  }
}

function toggleSelectAll() {
  if (selectedIds.value.length === items.value.length) {
    selectedIds.value = []
  } else {
    selectedIds.value = items.value.map(it => it.id)
  }
}

// Vista Previa
async function handlePreview(item: RecordatorioItem) {
  previewModal.activeItem = item
  previewModal.visible = true
  previewModal.loading = true
  try {
    const res = await recordatoriosApi.getPreview({
      tipo: item.tipo_servicio,
      id: item.raw_id,
      cliente_id: item.cliente_id,
    })
    previewModal.data = res.data
  } catch (err: any) {
    showToast(err.response?.data?.detail || 'Error al generar vista previa', 'error')
    previewModal.visible = false
  } finally {
    previewModal.loading = false
  }
}

// Enviar individual desde modal de preview
async function handleSendFromPreview(payload: { asunto: string; customEmail?: string }) {
  if (!previewModal.activeItem) return
  previewModal.sending = true
  try {
    const res = await recordatoriosApi.enviarIndividual({
      tipo: previewModal.activeItem.tipo_servicio,
      id: previewModal.activeItem.raw_id,
      cliente_id: previewModal.activeItem.cliente_id,
      destinatario_correo: payload.customEmail,
      asunto: payload.asunto,
    })
    showToast(res.data.message || 'Correo enviado con éxito', 'success')
    previewModal.visible = false
  } catch (err: any) {
    showToast(err.response?.data?.detail || 'Error al enviar el correo', 'error')
  } finally {
    previewModal.sending = false
  }
}

// Enviar directo desde la tabla
async function handleSendDirect(item: RecordatorioItem) {
  if (!item.cliente_correo) {
    showToast('El cliente no tiene correo registrado', 'error')
    return
  }
  try {
    const res = await recordatoriosApi.enviarIndividual({
      tipo: item.tipo_servicio,
      id: item.raw_id,
      cliente_id: item.cliente_id,
    })
    showToast(res.data.message || 'Recordatorio enviado exitosamente', 'success')
  } catch (err: any) {
    showToast(err.response?.data?.detail || 'Error al enviar recordatorio', 'error')
  }
}

// Envío masivo
function openBatchModal() {
  batchModal.result = null
  batchModal.visible = true
}

async function handleConfirmBatch() {
  batchModal.sending = true
  try {
    const selectedItems = items.value.filter(it => selectedIds.value.includes(it.id))
    const batchPayload = selectedItems.map(it => ({
      tipo: it.tipo_servicio,
      id: it.raw_id,
    }))
    const res = await recordatoriosApi.enviarMasivo(batchPayload)
    batchModal.result = res.data
    selectedIds.value = []
    showToast(res.data.message, res.data.success ? 'success' : 'error')
  } catch (err: any) {
    showToast(err.response?.data?.detail || 'Error al procesar envío masivo', 'error')
  } finally {
    batchModal.sending = false
  }
}

// Prueba SMTP
async function handleTestSmtp() {
  showToast('Probando conexión con servidor SMTP...', 'info')
  try {
    const res = await recordatoriosApi.testSmtp()
    if (res.data.success) {
      showToast(`✅ Conexión SMTP exitosa (${res.data.host}:${res.data.port})`, 'success')
    } else {
      showToast(`❌ Error SMTP: ${res.data.message}`, 'error')
    }
  } catch (err: any) {
    showToast(err.response?.data?.detail || 'Error al conectar con el servidor SMTP', 'error')
  }
}

onMounted(() => {
  fetchPendientes()
})
</script>

<template>
  <div class="flex h-screen bg-[#070B14] text-slate-100 overflow-hidden font-sans">
    <!-- Sidebar Modular -->
    <AppSidebar :isMobileOpen="isMobileOpen" @close-mobile="isMobileOpen = false" />

    <!-- Área Principal -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
      <!-- Barra Superior Móvil / Breadcrumbs -->
      <header class="h-16 px-4 md:px-8 border-b border-slate-800/80 bg-[#0A0F1D]/90 flex items-center justify-between shrink-0">
        <div class="flex items-center space-x-3">
          <button
            @click="isMobileOpen = true"
            class="lg:hidden p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 focus:outline-none"
          >
            <i class="pi pi-bars text-lg"></i>
          </button>
          <div>
            <div class="flex items-center space-x-2 text-xs text-slate-400">
              <span>Comunicación</span>
              <i class="pi pi-chevron-right text-[10px]"></i>
              <span class="text-blue-400 font-semibold">Recordatorios por Correo</span>
            </div>
            <h1 class="text-lg font-extrabold text-white tracking-tight">Centro de Recordatorios</h1>
          </div>
        </div>

        <div class="flex items-center space-x-2">
          <div class="hidden sm:flex items-center space-x-1.5 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-semibold">
            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
            <span>SMTP Listo</span>
          </div>
        </div>
      </header>

      <!-- Contenedor con Scroll -->
      <main class="flex-1 overflow-y-auto p-4 md:p-8 space-y-6 custom-scrollbar">
        <!-- Tarjetas KPIs -->
        <RecordatoriosKpis :kpis="kpis" :loading="loading" />

        <!-- Filtros y Toolbar -->
        <RecordatoriosFilters
          v-model:search="search"
          v-model:selectedTipo="selectedTipo"
          v-model:selectedEstado="selectedEstado"
          :selectedCount="selectedIds.length"
          :totalCount="items.length"
          @update:search="fetchPendientes"
          @update:selectedTipo="fetchPendientes"
          @update:selectedEstado="fetchPendientes"
          @send-batch="openBatchModal"
          @select-all="toggleSelectAll"
          @clear-selection="selectedIds = []"
          @test-smtp="handleTestSmtp"
          @refresh="fetchPendientes"
        />

        <!-- Tabla de Recordatorios Pendientes -->
        <RecordatoriosTable
          :items="items"
          :loading="loading"
          :selectedIds="selectedIds"
          @toggle-select="toggleSelect"
          @toggle-select-all="toggleSelectAll"
          @preview="handlePreview"
          @send-direct="handleSendDirect"
        />
      </main>
    </div>

    <!-- Modal Vista Previa -->
    <RecordatorioPreviewModal
      :visible="previewModal.visible"
      :previewData="previewModal.data"
      :loading="previewModal.loading"
      :sending="previewModal.sending"
      @close="previewModal.visible = false"
      @send="handleSendFromPreview"
    />

    <!-- Modal Envío Masivo -->
    <RecordatoriosBatchModal
      :visible="batchModal.visible"
      :count="selectedIds.length"
      :sending="batchModal.sending"
      :result="batchModal.result"
      @close="batchModal.visible = false"
      @confirm="handleConfirmBatch"
    />

    <!-- Toast de Notificaciones -->
    <AppToast
      :visible="toast.visible"
      :message="toast.message"
      :type="toast.type"
      @close="toast.visible = false"
    />
  </div>
</template>
