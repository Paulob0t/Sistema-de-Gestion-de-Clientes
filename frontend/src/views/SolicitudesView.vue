<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import AppSidebar from '@/components/layout/AppSidebar.vue'
import SolicitudesKpis from '@/components/solicitudes/SolicitudesKpis.vue'
import SolicitudesFilters from '@/components/solicitudes/SolicitudesFilters.vue'
import SolicitudesTable from '@/components/solicitudes/SolicitudesTable.vue'
import SolicitudDetailModal from '@/components/solicitudes/SolicitudDetailModal.vue'
import SolicitudFormModal from '@/components/solicitudes/SolicitudFormModal.vue'
import AppToast from '@/components/common/AppToast.vue'
import { useAuthStore } from '@/stores/auth'
import { clientesApi } from '@/api/clientes'
import {
  solicitudesApi,
  type SolicitudItem,
  type SolicitudDetail,
  type AgenteSimple,
  type SolicitudesKpis as KpisType,
} from '@/api/solicitudes'

const authStore = useAuthStore()
const isMobileOpen = ref(false)
const loading = ref(false)
const saving = ref(false)

const isAgente = computed(() => authStore.isAgente)

const items = ref<SolicitudItem[]>([])
const agentes = ref<AgenteSimple[]>([])
const clients = ref<Array<{ id: number; nombre_contacto?: string; empresa?: string }>>([])

// KPIs
const kpis = reactive<KpisType>({
  total: 0,
  pendientes: 0,
  en_proceso: 0,
  finalizadas: 0,
  asignadas_a_mi: 0,
  alta_prioridad: 0,
})

// Filtros
const search = ref('')
const selectedEstado = ref('todos')
const selectedPrioridad = ref('todos')
const selectedAgenteId = ref<number | null>(null)
const soloMias = ref(isAgente.value) // Por defecto activo para agentes

// Paginación
const page = ref(1)
const limit = ref(25)
const totalItems = ref(0)

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
const detailModal = reactive({
  visible: false,
  loading: false,
  solicitud: null as SolicitudDetail | null,
})

const formModal = reactive({
  visible: false,
  solicitudToEdit: null as SolicitudItem | null,
})

async function fetchSolicitudes() {
  loading.value = true
  try {
    const res = await solicitudesApi.list({
      search: search.value || undefined,
      estado: selectedEstado.value,
      prioridad: selectedPrioridad.value,
      agente_id: selectedAgenteId.value ?? undefined,
      solo_mias: soloMias.value,
      page: page.value,
      limit: limit.value,
    })
    items.value = res.data.items
    totalItems.value = res.data.total
    Object.assign(kpis, res.data.kpis)
  } catch (err: any) {
    showToast(err.response?.data?.detail || 'Error al cargar solicitudes', 'error')
  } finally {
    loading.value = false
  }
}

async function fetchInitialData() {
  try {
    const [agentesRes, clientsRes] = await Promise.all([
      solicitudesApi.getAgentes(),
      clientesApi.getClientes({ limit: 100 }),
    ])
    agentes.value = agentesRes.data
    clients.value = clientsRes.items || []
  } catch (e) {
    console.error('Error al cargar datos auxiliares', e)
  }
}

// Abrir Detalle
async function handleViewDetail(item: SolicitudItem) {
  detailModal.visible = true
  detailModal.loading = true
  try {
    const res = await solicitudesApi.getById(item.id)
    detailModal.solicitud = res.data
  } catch (err: any) {
    showToast(err.response?.data?.detail || 'Error al cargar detalle', 'error')
    detailModal.visible = false
  } finally {
    detailModal.loading = false
  }
}

// Cambiar Estado
async function handleUpdateStatus(item: SolicitudItem, newStatus: string) {
  try {
    const res = await solicitudesApi.updateStatus(item.id, newStatus)
    item.estado = res.data.estado
    if (detailModal.visible && detailModal.solicitud?.id === item.id) {
      detailModal.solicitud.estado = res.data.estado
    }
    showToast(`Estado actualizado a ${newStatus}`, 'success')
    fetchSolicitudes()
  } catch (err: any) {
    showToast(err.response?.data?.detail || 'Error al actualizar estado', 'error')
  }
}

// Reasignar Agentes desde modal detalle
async function handleAssignAgents(agentIds: number[]) {
  if (!detailModal.solicitud) return
  try {
    const res = await solicitudesApi.assignAgents(detailModal.solicitud.id, agentIds)
    detailModal.solicitud.agentes = res.data.agentes
    showToast('Agentes actualizados correctamente', 'success')
    fetchSolicitudes()
  } catch (err: any) {
    showToast(err.response?.data?.detail || 'Error al reasignar agentes', 'error')
  }
}

// Agregar Nota
async function handleAddNote(text: string) {
  if (!detailModal.solicitud) return
  try {
    const res = await solicitudesApi.addNota(detailModal.solicitud.id, { nota: text })
    detailModal.solicitud.notas.unshift(res.data)
    detailModal.solicitud.total_notas++
    showToast('Comentario añadido exitosamente', 'success')
  } catch (err: any) {
    showToast(err.response?.data?.detail || 'Error al guardar comentario', 'error')
  }
}

// Crear / Editar
function handleNewSolicitud() {
  formModal.solicitudToEdit = null
  formModal.visible = true
}

function handleEdit(item: SolicitudItem) {
  formModal.solicitudToEdit = item
  formModal.visible = true
}

async function handleSaveSolicitud(payload: any) {
  saving.value = true
  try {
    if (payload.id) {
      await solicitudesApi.update(payload.id, payload)
      showToast('Solicitud actualizada con éxito', 'success')
    } else {
      await solicitudesApi.create(payload)
      showToast('Solicitud creada exitosamente', 'success')
    }
    formModal.visible = false
    fetchSolicitudes()
  } catch (err: any) {
    showToast(err.response?.data?.detail || 'Error al guardar solicitud', 'error')
  } finally {
    saving.value = false
  }
}

// Eliminar
async function handleDelete(item: SolicitudItem) {
  if (!confirm(`¿Estás seguro de eliminar la solicitud #${item.id}: "${item.titulo}"?`)) return
  try {
    await solicitudesApi.delete(item.id)
    showToast('Solicitud eliminada correctamente', 'success')
    fetchSolicitudes()
  } catch (err: any) {
    showToast(err.response?.data?.detail || 'Error al eliminar solicitud', 'error')
  }
}

onMounted(() => {
  fetchInitialData()
  fetchSolicitudes()
})
</script>

<template>
  <div class="flex h-screen bg-[#070B14] text-slate-100 overflow-hidden font-sans">
    <!-- Sidebar -->
    <AppSidebar :isMobileOpen="isMobileOpen" @close-mobile="isMobileOpen = false" />

    <!-- Área Principal -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
      <!-- Barra Superior -->
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
              <span>Operaciones</span>
              <i class="pi pi-chevron-right text-[10px]"></i>
              <span class="text-blue-400 font-semibold">{{ isAgente ? 'Mis Solicitudes de Agente' : 'Gestión de Solicitudes' }}</span>
            </div>
            <h1 class="text-lg font-extrabold text-white tracking-tight">
              {{ isAgente ? 'Panel de Tickets & Solicitudes' : 'Centro de Solicitudes' }}
            </h1>
          </div>
        </div>

        <!-- Perfil / Rol Badge -->
        <div class="flex items-center space-x-2">
          <div class="flex items-center space-x-1.5 px-3 py-1 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-400 text-xs font-semibold">
            <span class="w-2 h-2 rounded-full bg-blue-400"></span>
            <span>{{ isAgente ? 'Modo Agente / Desarrollador' : 'Modo Administrador' }}</span>
          </div>
        </div>
      </header>

      <!-- Contenedor Principal con Scroll -->
      <main class="flex-1 overflow-y-auto p-4 md:p-8 space-y-6 custom-scrollbar">
        <!-- Tarjetas KPIs -->
        <SolicitudesKpis :kpis="kpis" :loading="loading" :isAgente="isAgente" />

        <!-- Filtros y Barra de Acciones -->
        <SolicitudesFilters
          v-model:search="search"
          v-model:selectedEstado="selectedEstado"
          v-model:selectedPrioridad="selectedPrioridad"
          v-model:selectedAgenteId="selectedAgenteId"
          v-model:soloMias="soloMias"
          :agentes="agentes"
          :isAgente="isAgente"
          @update:search="fetchSolicitudes"
          @update:selectedEstado="fetchSolicitudes"
          @update:selectedPrioridad="fetchSolicitudes"
          @update:selectedAgenteId="fetchSolicitudes"
          @update:soloMias="fetchSolicitudes"
          @new-solicitud="handleNewSolicitud"
          @refresh="fetchSolicitudes"
        />

        <!-- Tabla de Solicitudes -->
        <SolicitudesTable
          :items="items"
          :loading="loading"
          @view-detail="handleViewDetail"
          @edit="handleEdit"
          @update-status="handleUpdateStatus"
          @delete="handleDelete"
        />
      </main>
    </div>

    <!-- Modal Detalle & Notas -->
    <SolicitudDetailModal
      :visible="detailModal.visible"
      :solicitud="detailModal.solicitud"
      :loading="detailModal.loading"
      :agentes="agentes"
      @close="detailModal.visible = false"
      @update-status="(st) => detailModal.solicitud && handleUpdateStatus(detailModal.solicitud, st)"
      @assign-agents="handleAssignAgents"
      @add-note="handleAddNote"
    />

    <!-- Modal Crear / Editar -->
    <SolicitudFormModal
      :visible="formModal.visible"
      :solicitudToEdit="formModal.solicitudToEdit"
      :agentes="agentes"
      :clients="clients"
      :saving="saving"
      @close="formModal.visible = false"
      @save="handleSaveSolicitud"
    />

    <!-- Toast -->
    <AppToast
      :visible="toast.visible"
      :message="toast.message"
      :type="toast.type"
      @close="toast.visible = false"
    />
  </div>
</template>
