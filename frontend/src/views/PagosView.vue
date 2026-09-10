<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { usePagos } from '@/composables/usePagos'
import { useToast } from '@/composables/useToast'
import { pagosApi, type PagoListItem, type PagoDetail, type PagoPayload } from '@/api/pagos'
import { clientesApi } from '@/api/clientes'

import AppSidebar from '@/components/layout/AppSidebar.vue'
import AppToast from '@/components/common/AppToast.vue'
import PagoKpis from '@/components/pagos/PagoKpis.vue'
import PagoTable from '@/components/pagos/PagoTable.vue'
import PagoCardGrid from '@/components/pagos/PagoCardGrid.vue'
import PagoDetailModal from '@/components/pagos/PagoDetailModal.vue'
import PagoFormModal from '@/components/pagos/PagoFormModal.vue'

const router = useRouter()
const authStore = useAuthStore()
const { showToast } = useToast()

const isMobileSidebarOpen = ref(false)
const user = computed(() => authStore.user)
const isSuperAdmin = computed(() => authStore.isSuperAdmin || (user.value?.id_tipo_usuario ?? 0) >= 1)

// Composable de Pagos
const {
  isLoading,
  isSaving,
  pagos,
  stats,
  searchQuery,
  activeFilter,
  fechaDesde,
  fechaHasta,
  currentPage,
  totalItems,
  totalPages,
  viewMode,
  loadPagos,
  handleSearchInput,
  clearSearch,
  setFilter,
  changePage,
  togglePaymentStatus,
  removePago,
  formatWhatsAppPaymentLink,
} = usePagos()

// Modales y Estados
const isDetailOpen = ref(false)
const isFormOpen = ref(false)
const isEditing = ref(false)
const isLoadingDetail = ref(false)
const currentDetail = ref<PagoDetail | null>(null)
const editingPagoId = ref<number | null>(null)
const clientesList = ref<Array<{ id: number; empresa: string; nombre_contacto: string }>>([])

const formData = ref<PagoPayload>({
  id_clie: 0,
  monto: 1000.0,
  currency: 'MXN',
  concepto: '',
  forma_pago: 1,
  estatus: 0,
  tipo_servicio: 0,
  id_servicio: 0,
  fecha: new Date().toISOString().split('T')[0],
  fecha_limite_pago: '',
  id_pago: '',
  manual: 1,
  frecuencia_pago: 0,
})

async function loadClientesDropdown() {
  try {
    const res = await clientesApi.getClientes({ limit: 100 })
    clientesList.value = res.items.map((c) => ({
      id: c.id,
      empresa: c.empresa,
      nombre_contacto: c.nombre_contacto,
    }))
  } catch {}
}

async function openDetail(id: number) {
  isDetailOpen.value = true
  isLoadingDetail.value = true
  try {
    currentDetail.value = await pagosApi.getPagoDetail(id)
  } catch {
    showToast('No se pudo cargar el detalle del cobro', 'error')
    isDetailOpen.value = false
  } finally {
    isLoadingDetail.value = false
  }
}

function openCreate() {
  isEditing.value = false
  editingPagoId.value = null
  formData.value = {
    id_clie: clientesList.value.length > 0 ? clientesList.value[0].id : 0,
    monto: 1000.0,
    currency: 'MXN',
    concepto: '',
    forma_pago: 1,
    estatus: 0,
    tipo_servicio: 0,
    id_servicio: 0,
    fecha: new Date().toISOString().split('T')[0],
    fecha_limite_pago: '',
    id_pago: '',
    manual: 1,
    frecuencia_pago: 0,
  }
  isFormOpen.value = true
}

async function openEdit(pago: PagoListItem | PagoDetail) {
  isEditing.value = true
  editingPagoId.value = pago.id
  try {
    const d = await pagosApi.getPagoDetail(pago.id)
    formData.value = {
      id_clie: d.id_clie,
      monto: d.monto,
      currency: d.currency,
      concepto: d.concepto,
      forma_pago: d.forma_pago,
      estatus: d.estatus,
      tipo_servicio: d.tipo_servicio,
      id_servicio: d.id_servicio,
      fecha: d.fecha,
      fecha_pago: d.fecha_pago || '',
      fecha_limite_pago: d.fecha_limite_pago || '',
      id_pago: d.id_pago || '',
      manual: d.manual,
      frecuencia_pago: d.frecuencia_pago,
    }
  } catch {
    formData.value.concepto = pago.concepto
  }
  isFormOpen.value = true
}

async function handleSavePago() {
  if (!formData.value.concepto.trim() || !formData.value.id_clie || !formData.value.monto) {
    showToast('Cliente, concepto y monto son requeridos', 'error')
    return
  }
  isSaving.value = true
  try {
    if (isEditing.value && editingPagoId.value) {
      const updated = await pagosApi.updatePago(editingPagoId.value, formData.value)
      showToast(`Cobro #${updated.id} actualizado con éxito`)
      if (currentDetail.value && currentDetail.value.id === updated.id) {
        currentDetail.value = updated
      }
    } else {
      const created = await pagosApi.createPago(formData.value)
      showToast(`Cobro #${created.id} registrado con éxito`)
    }
    isFormOpen.value = false
    loadPagos()
  } catch (err: any) {
    showToast(err.response?.data?.detail || 'Error al guardar cobro', 'error')
  } finally {
    isSaving.value = false
  }
}

function handleWhatsApp(pago: PagoListItem) {
  const link = formatWhatsAppPaymentLink(pago)
  window.open(link, '_blank')
}

function handleLogout() {
  authStore.logout()
  router.push('/login')
}

onMounted(() => {
  loadPagos()
  loadClientesDropdown()
})
</script>

<template>
  <div class="h-screen w-screen bg-[#080C14] text-slate-100 selection:bg-cyan-600 selection:text-white flex overflow-hidden">
    <!-- MENÚ LATERAL -->
    <AppSidebar
      :is-mobile-open="isMobileSidebarOpen"
      @close-mobile="isMobileSidebarOpen = false"
    />

    <!-- CONTENEDOR PRINCIPAL -->
    <div class="flex-1 flex flex-col min-w-0 h-screen overflow-y-auto pb-16">
      <!-- HEADER SUPERIOR -->
      <header class="sticky top-0 z-30 bg-[#0A0F1D]/80 backdrop-blur-md border-b border-slate-800/80 h-16 shrink-0">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-full flex items-center justify-between">
          <div class="flex items-center space-x-3">
            <button
              @click="isMobileSidebarOpen = true"
              class="lg:hidden p-2 rounded-xl bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 transition-colors"
            >
              <i class="pi pi-bars text-base"></i>
            </button>
            <div class="flex items-center space-x-2">
              <span class="text-sm font-bold text-white">Consulta de Pagos</span>
              <span class="text-slate-600 hidden sm:inline">•</span>
              <span class="text-xs text-cyan-400 font-semibold uppercase tracking-wider hidden sm:inline">Tesorería & Facturación</span>
            </div>
          </div>

          <div class="flex items-center space-x-3">
            <!-- Botón Nuevo Cobro -->
            <button
              v-if="isSuperAdmin"
              @click="openCreate"
              class="px-3.5 py-2 rounded-xl bg-gradient-to-r from-emerald-600 to-cyan-600 hover:from-emerald-500 hover:to-cyan-500 text-white text-xs font-bold shadow-lg shadow-emerald-500/20 flex items-center space-x-1.5 transition-all duration-200 active:scale-95"
            >
              <i class="pi pi-plus text-xs"></i>
              <span>Nuevo Cobro</span>
            </button>

            <!-- Botón Salir / Logout -->
            <button
              @click="handleLogout"
              class="p-2 rounded-xl bg-slate-800/80 hover:bg-rose-500/20 text-slate-400 hover:text-rose-400 border border-slate-700/60 transition-colors"
              title="Cerrar sesión"
            >
              <i class="pi pi-sign-out text-sm"></i>
            </button>
          </div>
        </div>
      </header>

      <!-- NOTIFICACIONES TOAST -->
      <AppToast />

      <!-- CONTENIDO -->
      <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 space-y-6 w-full">
        <!-- 1. KPIS SUPERIORES -->
        <PagoKpis
          :stats="stats"
          :active-filter="activeFilter"
          @select-filter="setFilter"
        />

        <!-- 2. BARRA DE BÚSQUEDA Y FILTROS -->
        <div class="p-4 sm:p-5 rounded-3xl bg-slate-900/70 border border-slate-800/80 space-y-4">
          <div class="flex flex-col md:flex-row gap-3 items-center justify-between">
            <div class="relative w-full md:w-96">
              <i class="pi pi-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-sm"></i>
              <input
                v-model="searchQuery"
                @input="handleSearchInput"
                type="text"
                placeholder="Buscar por concepto, cliente, folio o monto..."
                class="w-full pl-10 pr-10 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all"
              />
              <button
                v-if="searchQuery"
                @click="clearSearch"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-white"
              >
                <i class="pi pi-times text-xs"></i>
              </button>
            </div>

            <!-- Toggle Tabla / Tarjetas & Recargar -->
            <div class="flex items-center space-x-2 w-full md:w-auto justify-end">
              <div class="flex p-1 rounded-xl bg-slate-950/80 border border-slate-800">
                <button
                  @click="viewMode = 'table'"
                  :class="[
                    'px-3 py-1.5 rounded-lg text-xs font-semibold transition-all',
                    viewMode === 'table' ? 'bg-cyan-600 text-white shadow-md' : 'text-slate-400 hover:text-white'
                  ]"
                  title="Vista Tabla"
                >
                  <i class="pi pi-list"></i>
                </button>
                <button
                  @click="viewMode = 'cards'"
                  :class="[
                    'px-3 py-1.5 rounded-lg text-xs font-semibold transition-all',
                    viewMode === 'cards' ? 'bg-cyan-600 text-white shadow-md' : 'text-slate-400 hover:text-white'
                  ]"
                  title="Vista Tarjetas"
                >
                  <i class="pi pi-th-large"></i>
                </button>
              </div>

              <button
                @click="loadPagos"
                class="p-2.5 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700/60 transition-colors"
                title="Actualizar pagos"
              >
                <i :class="['pi pi-refresh text-xs', isLoading ? 'pi-spin' : '']"></i>
              </button>
            </div>
          </div>

          <!-- Filtros Rápidos y Rango de Fechas -->
          <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 pt-2 border-t border-slate-800/60">
            <div class="flex items-center space-x-2 overflow-x-auto pb-1 text-xs custom-scrollbar">
              <button
                v-for="f in [
                  { key: 'todos', label: 'Todos' },
                  { key: 'pendientes', label: 'Pendientes' },
                  { key: 'pagados', label: 'Acreditados' },
                  { key: 'vencidos', label: 'Vencidos' },
                  { key: 'hosting', label: 'Hosting' },
                  { key: 'dominios', label: 'Dominios' },
                  { key: 'manuales', label: 'Manuales' },
                  { key: 'eliminados', label: 'Papelera' },
                ]"
                :key="f.key"
                @click="setFilter(f.key)"
                :class="[
                  'px-3 py-1 rounded-xl font-semibold whitespace-nowrap transition-all',
                  activeFilter === f.key
                    ? 'bg-cyan-500/20 text-cyan-400 border border-cyan-500/30'
                    : 'bg-slate-950/60 text-slate-400 hover:text-white border border-slate-800/60'
                ]"
              >
                {{ f.label }}
              </button>
            </div>

            <!-- Selector de Rango de Fechas -->
            <div class="flex items-center space-x-2 text-xs">
              <span class="text-slate-500 text-[11px] whitespace-nowrap">Rango:</span>
              <input
                v-model="fechaDesde"
                @change="loadPagos"
                type="date"
                class="px-2.5 py-1 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white focus:outline-none focus:border-cyan-500"
                title="Fecha inicio"
              />
              <span class="text-slate-600">-</span>
              <input
                v-model="fechaHasta"
                @change="loadPagos"
                type="date"
                class="px-2.5 py-1 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white focus:outline-none focus:border-cyan-500"
                title="Fecha fin"
              />
              <button
                v-if="fechaDesde || fechaHasta"
                @click="fechaDesde = ''; fechaHasta = ''; loadPagos()"
                class="p-1 text-slate-500 hover:text-white"
                title="Limpiar fechas"
              >
                <i class="pi pi-times text-xs"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- 3. LISTADO (TABLA O TARJETAS) -->
        <div class="rounded-3xl bg-slate-900/70 border border-slate-800/80 overflow-hidden shadow-xl">
          <div v-if="isLoading" class="p-16 flex flex-col items-center justify-center space-y-3">
            <i class="pi pi-spin pi-spinner text-3xl text-cyan-400"></i>
            <span class="text-xs text-slate-400 font-medium">Cargando registros de pagos...</span>
          </div>

          <div v-else-if="pagos.length === 0" class="p-16 text-center space-y-3">
            <div class="w-12 h-12 rounded-2xl bg-slate-800/80 text-slate-500 flex items-center justify-center mx-auto text-xl">
              <i class="pi pi-credit-card"></i>
            </div>
            <div class="text-sm font-semibold text-slate-300">No se encontraron pagos registrados</div>
            <p class="text-xs text-slate-500 max-w-sm mx-auto">
              No hay pagos que coincidan con los filtros o término de búsqueda aplicados actualmente.
            </p>
          </div>

          <div v-else>
            <PagoTable
              v-if="viewMode === 'table'"
              :pagos="pagos"
              :is-loading="isLoading"
              :is-super-admin="isSuperAdmin"
              @open-detail="openDetail"
              @open-edit="openEdit"
              @toggle-status="togglePaymentStatus"
              @delete-pago="removePago"
              @send-whatsapp="handleWhatsApp"
            />
            <div v-else class="p-5">
              <PagoCardGrid
                :pagos="pagos"
                :is-super-admin="isSuperAdmin"
                @open-detail="openDetail"
                @open-edit="openEdit"
                @toggle-status="togglePaymentStatus"
                @delete-pago="removePago"
                @send-whatsapp="handleWhatsApp"
              />
            </div>

            <!-- Paginación -->
            <div class="p-4 border-t border-slate-800/80 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-400 bg-slate-950/40">
              <div>
                Mostrando <span class="font-bold text-white">{{ pagos.length }}</span> de <span class="font-bold text-white">{{ totalItems }}</span> registros
              </div>
              <div class="flex items-center space-x-1.5">
                <button
                  @click="changePage(currentPage - 1)"
                  :disabled="currentPage === 1"
                  class="p-2 rounded-lg bg-slate-800 hover:bg-slate-700 disabled:opacity-30 disabled:cursor-not-allowed text-slate-300"
                >
                  <i class="pi pi-chevron-left text-xs"></i>
                </button>
                <span class="px-3 py-1 font-semibold text-white">
                  Página {{ currentPage }} de {{ totalPages }}
                </span>
                <button
                  @click="changePage(currentPage + 1)"
                  :disabled="currentPage === totalPages"
                  class="p-2 rounded-lg bg-slate-800 hover:bg-slate-700 disabled:opacity-30 disabled:cursor-not-allowed text-slate-300"
                >
                  <i class="pi pi-chevron-right text-xs"></i>
                </button>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>

    <!-- MODAL DE DETALLE -->
    <PagoDetailModal
      :is-open="isDetailOpen"
      :pago="currentDetail"
      :is-loading="isLoadingDetail"
      :is-super-admin="isSuperAdmin"
      @close="isDetailOpen = false"
      @edit="openEdit"
      @toggle-status="togglePaymentStatus"
      @send-whatsapp="handleWhatsApp"
    />

    <!-- MODAL DE CREACIÓN / EDICIÓN -->
    <PagoFormModal
      :is-open="isFormOpen"
      :is-editing="isEditing"
      :is-saving="isSaving"
      :form-data="formData"
      :clientes-list="clientesList"
      @close="isFormOpen = false"
      @save="handleSavePago"
    />
  </div>
</template>
