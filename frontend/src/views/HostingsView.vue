<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useHostings } from '@/composables/useHostings'
import { useToast } from '@/composables/useToast'
import { hostingsApi, type HostingListItem, type HostingDetail, type HostingPayload } from '@/api/hostings'
import { clientesApi } from '@/api/clientes'

import AppSidebar from '@/components/layout/AppSidebar.vue'
import AppToast from '@/components/common/AppToast.vue'
import HostingKpis from '@/components/hostings/HostingKpis.vue'
import HostingTable from '@/components/hostings/HostingTable.vue'
import HostingCardGrid from '@/components/hostings/HostingCardGrid.vue'
import HostingDetailModal from '@/components/hostings/HostingDetailModal.vue'
import HostingFormModal from '@/components/hostings/HostingFormModal.vue'

const router = useRouter()
const authStore = useAuthStore()
const { showToast } = useToast()

const isMobileSidebarOpen = ref(false)
const user = computed(() => authStore.user)
const isSuperAdmin = computed(() => authStore.isSuperAdmin || (user.value?.id_tipo_usuario ?? 0) >= 1)

// Composable de Hostings
const {
  isLoading,
  isSaving,
  hostings,
  stats,
  searchQuery,
  activeFilter,
  currentPage,
  totalItems,
  totalPages,
  viewMode,
  loadHostings,
  handleSearchInput,
  clearSearch,
  setFilter,
  changePage,
  removeHosting,
  formatWhatsAppRenewalLink,
} = useHostings()

// Modales y Estados
const isDetailOpen = ref(false)
const isFormOpen = ref(false)
const isEditing = ref(false)
const isLoadingDetail = ref(false)
const currentDetail = ref<HostingDetail | null>(null)
const editingHostingId = ref<number | null>(null)
const clientesList = ref<Array<{ id: number; empresa: string; nombre_contacto: string }>>([])

const formData = ref<HostingPayload>({
  cliente_id: 0,
  nom_host: '',
  dominio: '',
  usuario: '',
  contrasena_normal: '',
  tipo_producto: 'Servicio de alojamiento',
  producto: 1,
  costo_producto: 1500.0,
  id_forma_pago: 1,
  dns: '',
  url_pago: '',
  url_acceso: '',
  ns1: 'ns1.nexusbot.io',
  ns2: 'ns2.nexusbot.io',
  fecha_contratacion: new Date().toISOString().split('T')[0],
  fecha_pago: '',
  estado_producto: 1,
  IVA: 1,
  frecuencia_pago: 2,
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
    currentDetail.value = await hostingsApi.getHostingDetail(id)
  } catch {
    showToast('No se pudo cargar el detalle del hosting', 'error')
    isDetailOpen.value = false
  } finally {
    isLoadingDetail.value = false
  }
}

function openCreate() {
  isEditing.value = false
  editingHostingId.value = null
  formData.value = {
    cliente_id: clientesList.value.length > 0 ? clientesList.value[0].id : 0,
    nom_host: '',
    dominio: '',
    usuario: '',
    contrasena_normal: '',
    tipo_producto: 'Servicio de alojamiento',
    producto: 1,
    costo_producto: 1500.0,
    id_forma_pago: 1,
    dns: '',
    url_pago: '',
    url_acceso: '',
    ns1: 'ns1.nexusbot.io',
    ns2: 'ns2.nexusbot.io',
    fecha_contratacion: new Date().toISOString().split('T')[0],
    fecha_pago: '',
    estado_producto: 1,
    IVA: 1,
    frecuencia_pago: 2,
  }
  isFormOpen.value = true
}

async function openEdit(host: HostingListItem | HostingDetail) {
  isEditing.value = true
  editingHostingId.value = host.id_orden
  try {
    const d = await hostingsApi.getHostingDetail(host.id_orden)
    formData.value = {
      cliente_id: d.cliente_id,
      nom_host: d.nom_host,
      dominio: d.dominio,
      usuario: d.usuario,
      contrasena_normal: d.contrasena_normal || '',
      tipo_producto: d.tipo_producto,
      producto: d.producto,
      costo_producto: d.costo_producto,
      id_forma_pago: d.id_forma_pago,
      dns: d.dns,
      url_pago: d.url_pago,
      url_acceso: d.url_acceso,
      ns1: d.ns1,
      ns2: d.ns2,
      fecha_contratacion: d.fecha_contratacion || '',
      fecha_pago: d.fecha_pago || '',
      estado_producto: d.estado_producto,
      IVA: d.IVA,
      frecuencia_pago: d.frecuencia_pago,
    }
  } catch {
    formData.value.nom_host = host.nom_host
  }
  isFormOpen.value = true
}

async function handleSaveHosting() {
  if (!formData.value.nom_host.trim() || !formData.value.cliente_id) {
    showToast('El hostname y cliente titular son requeridos', 'error')
    return
  }
  isSaving.value = true
  try {
    if (isEditing.value && editingHostingId.value) {
      const updated = await hostingsApi.updateHosting(editingHostingId.value, formData.value)
      showToast(`Hosting "${updated.nom_host}" actualizado con éxito`)
      if (currentDetail.value && currentDetail.value.id_orden === updated.id_orden) {
        currentDetail.value = updated
      }
    } else {
      const created = await hostingsApi.createHosting(formData.value)
      showToast(`Hosting "${created.nom_host}" registrado con éxito`)
    }
    isFormOpen.value = false
    loadHostings()
  } catch (err: any) {
    showToast(err.response?.data?.detail || 'Error al guardar hosting', 'error')
  } finally {
    isSaving.value = false
  }
}

function handleWhatsApp(host: HostingListItem) {
  const link = formatWhatsAppRenewalLink(host)
  window.open(link, '_blank')
}

function handleLogout() {
  authStore.logout()
  router.push('/login')
}

onMounted(() => {
  loadHostings()
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
              <span class="text-sm font-bold text-white">Consulta de Hosting</span>
              <span class="text-slate-600 hidden sm:inline">•</span>
              <span class="text-xs text-cyan-400 font-semibold uppercase tracking-wider hidden sm:inline">Servidores & Alojamiento</span>
            </div>
          </div>

          <div class="flex items-center space-x-3">
            <!-- Botón Nuevo Hosting -->
            <button
              v-if="isSuperAdmin"
              @click="openCreate"
              class="px-3.5 py-2 rounded-xl bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-500 hover:to-blue-500 text-white text-xs font-bold shadow-lg shadow-cyan-500/20 flex items-center space-x-1.5 transition-all duration-200 active:scale-95"
            >
              <i class="pi pi-plus text-xs"></i>
              <span>Nuevo Hosting</span>
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
        <HostingKpis
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
                placeholder="Buscar por host, dominio, cliente o usuario..."
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
                @click="loadHostings"
                class="p-2.5 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700/60 transition-colors"
                title="Actualizar datos"
              >
                <i :class="['pi pi-refresh text-xs', isLoading ? 'pi-spin' : '']"></i>
              </button>
            </div>
          </div>

          <!-- Filtros Rápidos -->
          <div class="flex items-center space-x-2 overflow-x-auto pb-1 text-xs custom-scrollbar">
            <button
              v-for="f in [
                { key: 'activos', label: 'Activos' },
                { key: 'inactivos', label: 'Inactivos' },
                { key: 'por_vencer_30', label: 'Próx. 30 Días' },
                { key: 'por_vencer_7', label: 'Crítico ≤7d' },
                { key: 'vencidos', label: 'Vencidos' },
                { key: 'todos', label: 'Todos' },
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
        </div>

        <!-- 3. LISTADO (TABLA O TARJETAS) -->
        <div class="rounded-3xl bg-slate-900/70 border border-slate-800/80 overflow-hidden shadow-xl">
          <div v-if="isLoading" class="p-16 flex flex-col items-center justify-center space-y-3">
            <i class="pi pi-spin pi-spinner text-3xl text-cyan-400"></i>
            <span class="text-xs text-slate-400 font-medium">Cargando servidores de hosting...</span>
          </div>

          <div v-else-if="hostings.length === 0" class="p-16 text-center space-y-3">
            <div class="w-12 h-12 rounded-2xl bg-slate-800/80 text-slate-500 flex items-center justify-center mx-auto text-xl">
              <i class="pi pi-server"></i>
            </div>
            <div class="text-sm font-semibold text-slate-300">No se encontraron servicios de hosting</div>
            <p class="text-xs text-slate-500 max-w-sm mx-auto">
              No hay registros con los filtros o término de búsqueda aplicados actualmente.
            </p>
          </div>

          <div v-else>
            <HostingTable
              v-if="viewMode === 'table'"
              :hostings="hostings"
              :is-loading="isLoading"
              :is-super-admin="isSuperAdmin"
              @open-detail="openDetail"
              @open-edit="openEdit"
              @delete-hosting="removeHosting"
              @send-whatsapp="handleWhatsApp"
            />
            <div v-else class="p-5">
              <HostingCardGrid
                :hostings="hostings"
                :is-super-admin="isSuperAdmin"
                @open-detail="openDetail"
                @open-edit="openEdit"
                @delete-hosting="removeHosting"
                @send-whatsapp="handleWhatsApp"
              />
            </div>

            <!-- Paginación -->
            <div class="p-4 border-t border-slate-800/80 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-400 bg-slate-950/40">
              <div>
                Mostrando <span class="font-bold text-white">{{ hostings.length }}</span> de <span class="font-bold text-white">{{ totalItems }}</span> registros
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
    <HostingDetailModal
      :is-open="isDetailOpen"
      :hosting="currentDetail"
      :is-loading="isLoadingDetail"
      :is-super-admin="isSuperAdmin"
      @close="isDetailOpen = false"
      @edit="openEdit"
      @send-whatsapp="handleWhatsApp"
    />

    <!-- MODAL DE CREACIÓN / EDICIÓN -->
    <HostingFormModal
      :is-open="isFormOpen"
      :is-editing="isEditing"
      :is-saving="isSaving"
      :form-data="formData"
      :clientes-list="clientesList"
      @close="isFormOpen = false"
      @save="handleSaveHosting"
    />
  </div>
</template>
