<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useDominios } from '@/composables/useDominios'
import { useToast } from '@/composables/useToast'
import { dominiosApi, type DominioListItem, type DominioDetail, type DominioPayload } from '@/api/dominios'
import { clientesApi } from '@/api/clientes'

import AppSidebar from '@/components/layout/AppSidebar.vue'
import AppToast from '@/components/common/AppToast.vue'
import DominioKpis from '@/components/dominios/DominioKpis.vue'
import DominioTable from '@/components/dominios/DominioTable.vue'
import DominioCardGrid from '@/components/dominios/DominioCardGrid.vue'
import DominioDetailModal from '@/components/dominios/DominioDetailModal.vue'
import DominioFormModal from '@/components/dominios/DominioFormModal.vue'

const router = useRouter()
const authStore = useAuthStore()
const { showToast } = useToast()

const isMobileSidebarOpen = ref(false)
const user = computed(() => authStore.user)
const isSuperAdmin = computed(() => authStore.isSuperAdmin || (user.value?.id_tipo_usuario ?? 0) >= 1)

// Composable de Dominios
const {
  isLoading,
  isSaving,
  dominios,
  stats,
  searchQuery,
  activeFilter,
  currentSistema,
  currentPage,
  totalItems,
  totalPages,
  viewMode,
  loadDominios,
  handleSearchInput,
  clearSearch,
  setFilter,
  changePage,
  removeDominio,
  formatWhatsAppRenewalLink,
  formatCurrency,
} = useDominios()

// Modales y Estados
const isDetailOpen = ref(false)
const isFormOpen = ref(false)
const isEditing = ref(false)
const isLoadingDetail = ref(false)
const currentDetail = ref<DominioDetail | null>(null)
const editingDominioId = ref<number | null>(null)
const clientesList = ref<Array<{ id: number; empresa: string; nombre_contacto: string }>>([])

const formData = ref<DominioPayload>({
  cliente_id: 0,
  url_dominio: '',
  proveedor: 'NexusBot',
  costo_dominio: 550.0,
  fecha_contratacion: new Date().toISOString().split('T')[0],
  fecha_pago: '',
  ns1: 'ns1.nexusbot.io',
  ns2: 'ns2.nexusbot.io',
  estado_dominio: 1,
  registrado: 1,
  estatus_pago: 0,
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
    currentDetail.value = await dominiosApi.getDominioDetail(id)
  } catch {
    showToast('No se pudo cargar el detalle del dominio', 'error')
    isDetailOpen.value = false
  } finally {
    isLoadingDetail.value = false
  }
}

function openCreate() {
  router.push('/dominios/nuevo')
}

async function openEdit(dom: DominioListItem | DominioDetail) {
  isEditing.value = true
  editingDominioId.value = dom.id_dominio
  try {
    const d = await dominiosApi.getDominioDetail(dom.id_dominio)
    formData.value = {
      cliente_id: d.cliente_id,
      url_dominio: d.url_dominio,
      proveedor: d.proveedor || 'NexusBot',
      costo_dominio: d.costo_dominio,
      fecha_contratacion: d.fecha_contratacion,
      fecha_pago: d.fecha_pago,
      ns1: d.ns1,
      ns2: d.ns2,
      estado_dominio: d.estado_dominio,
      registrado: d.registrado,
      estatus_pago: d.estatus_pago,
    }
  } catch {
    formData.value.url_dominio = dom.url_dominio
  }
  isFormOpen.value = true
}

async function handleSaveDominio() {
  if (!formData.value.url_dominio.trim() || !formData.value.cliente_id) {
    showToast('El dominio y cliente titular son requeridos', 'error')
    return
  }
  isSaving.value = true
  try {
    if (isEditing.value && editingDominioId.value) {
      const updated = await dominiosApi.updateDominio(editingDominioId.value, formData.value)
      showToast(`Dominio "${updated.url_dominio}" actualizado`)
      if (currentDetail.value && currentDetail.value.id_dominio === updated.id_dominio) {
        currentDetail.value = updated
      }
    } else {
      const created = await dominiosApi.createDominio(formData.value)
      showToast(`Dominio "${created.url_dominio}" registrado con éxito`)
    }
    isFormOpen.value = false
    loadDominios()
  } catch (err: any) {
    showToast(err.response?.data?.detail || 'Error al guardar dominio', 'error')
  } finally {
    isSaving.value = false
  }
}

function handleLogout() {
  authStore.logout()
  router.push('/login')
}

onMounted(() => {
  loadDominios()
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
              <span class="text-sm font-bold text-white">Consulta de Dominios</span>
              <span class="text-slate-600 hidden sm:inline">•</span>
              <span class="text-xs text-cyan-400 font-semibold uppercase tracking-wider hidden sm:inline">{{ currentSistema }}</span>
            </div>
          </div>

          <div class="flex items-center space-x-3">
            <!-- Switcher Sistema -->
            <div v-if="isSuperAdmin" class="hidden sm:flex p-1 rounded-xl bg-slate-950/80 border border-slate-800">
              <button
                @click="currentSistema = 'conlineweb'"
                :class="[
                  'px-3 py-1.5 rounded-lg text-xs font-semibold transition-all',
                  currentSistema === 'conlineweb' ? 'bg-cyan-600 text-white shadow-md' : 'text-slate-400 hover:text-white'
                ]"
              >
                ConlineWeb
              </button>
              <button
                @click="currentSistema = 'hostingpro'"
                :class="[
                  'px-3 py-1.5 rounded-lg text-xs font-semibold transition-all',
                  currentSistema === 'hostingpro' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white'
                ]"
              >
                HostingPro
              </button>
            </div>

            <!-- Botón Nuevo Dominio -->
            <button
              v-if="isSuperAdmin"
              @click="openCreate"
              class="px-3.5 py-2 rounded-xl bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-500 hover:to-blue-500 text-white text-xs font-bold shadow-lg shadow-cyan-500/20 flex items-center space-x-1.5 transition-all duration-200 active:scale-95"
            >
              <i class="pi pi-plus text-xs"></i>
              <span>Asignar Dominio</span>
            </button>

            <!-- Perfil / Logout en Header -->
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

      <!-- TOAST -->
      <AppToast />

      <!-- CONTENIDO -->
      <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 space-y-6 w-full">
        <!-- 1. KPIS -->
        <DominioKpis
          :stats="stats"
          :active-filter="activeFilter"
          @select-filter="setFilter"
        />

        <!-- 2. BARRA DE BÚSQUEDA Y FILTROS -->
        <div class="p-4 sm:p-5 rounded-3xl bg-[#0D121F]/90 border border-slate-800/80 space-y-4">
          <div class="flex flex-col md:flex-row gap-3 items-center justify-between">
            <div class="relative w-full md:w-96">
              <i class="pi pi-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-sm"></i>
              <input
                v-model="searchQuery"
                @input="handleSearchInput"
                type="text"
                placeholder="Buscar dominio, empresa, contacto o registrador..."
                class="w-full pl-10 pr-9 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs sm:text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:border-cyan-500 transition-colors"
              />
              <button
                v-if="searchQuery"
                @click="clearSearch"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300"
              >
                <i class="pi pi-times text-xs"></i>
              </button>
            </div>

            <div class="flex items-center justify-between w-full md:w-auto space-x-3">
              <span class="text-xs text-slate-400">
                Mostrando <strong class="text-white">{{ dominios.length }}</strong> de <strong class="text-white">{{ totalItems }}</strong>
              </span>

              <div class="flex p-1 rounded-xl bg-slate-950/80 border border-slate-800">
                <button
                  @click="viewMode = 'table'"
                  :class="['p-1.5 rounded-lg text-xs transition-colors', viewMode === 'table' ? 'bg-slate-800 text-cyan-400' : 'text-slate-400 hover:text-white']"
                  title="Vista en tabla"
                >
                  <i class="pi pi-table"></i>
                </button>
                <button
                  @click="viewMode = 'cards'"
                  :class="['p-1.5 rounded-lg text-xs transition-colors', viewMode === 'cards' ? 'bg-slate-800 text-cyan-400' : 'text-slate-400 hover:text-white']"
                  title="Vista en tarjetas"
                >
                  <i class="pi pi-th-large"></i>
                </button>
              </div>
            </div>
          </div>

          <!-- Pestañas Filtros Rápidos -->
          <div class="flex items-center gap-2 overflow-x-auto pb-1 text-xs select-none">
            <button
              v-for="f in [
                { id: 'activos', label: 'Dominios Activos' },
                { id: 'por_vencer', label: 'Próximos a Vencer (30d)', badge: stats.por_vencer_30d },
                { id: 'vencidos', label: 'Vencidos', badge: stats.vencidos },
                { id: 'pendientes_pago', label: 'Pendientes de Pago', badge: stats.pendientes_pago },
                { id: 'pagados', label: 'Pagados' },
                { id: 'externos', label: 'Externos' },
                { id: 'todos', label: 'Todos' },
              ]"
              :key="f.id"
              @click="setFilter(f.id)"
              :class="[
                'px-3 py-1.5 rounded-xl font-medium transition-colors shrink-0 flex items-center space-x-1.5',
                activeFilter === f.id ? 'bg-cyan-600 text-white font-semibold' : 'bg-slate-800/80 text-slate-400 hover:text-white'
              ]"
            >
              <span>{{ f.label }}</span>
              <span v-if="f.badge && f.badge > 0" class="px-1.5 py-0.2 rounded-full bg-amber-950 text-[10px] text-amber-300 font-bold border border-amber-500/30">
                {{ f.badge }}
              </span>
            </button>
          </div>
        </div>

        <!-- 3. CARGA Y LISTADO VACÍO -->
        <div v-if="isLoading" class="py-16 text-center space-y-3">
          <i class="pi pi-spin pi-spinner text-3xl text-cyan-500"></i>
          <p class="text-xs text-slate-400">Consultando catálogo de dominios...</p>
        </div>

        <div v-else-if="dominios.length === 0" class="p-12 text-center rounded-3xl bg-[#0D121F]/40 border border-slate-800/60 space-y-3">
          <i class="pi pi-globe text-4xl text-slate-600"></i>
          <h3 class="text-base font-bold text-white">No se encontraron dominios</h3>
          <button
            @click="clearSearch(); setFilter('activos')"
            class="mt-2 px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-white transition-colors"
          >
            Restablecer Filtros
          </button>
        </div>

        <template v-else>
          <!-- 4. TABLA DESKTOP -->
          <DominioTable
            v-if="viewMode === 'table'"
            :dominios="dominios"
            :is-super-admin="isSuperAdmin"
            :format-currency="formatCurrency"
            :format-whats-app-renewal-link="formatWhatsAppRenewalLink"
            @view-detail="openDetail"
            @edit="openEdit"
            @delete="removeDominio"
          />

          <!-- 5. GRID MOBILE -->
          <DominioCardGrid
            :dominios="dominios"
            :view-mode="viewMode"
            :is-super-admin="isSuperAdmin"
            :format-currency="formatCurrency"
            :format-whats-app-renewal-link="formatWhatsAppRenewalLink"
            @view-detail="openDetail"
            @edit="openEdit"
          />

          <!-- 6. PAGINACIÓN -->
          <div v-if="totalPages > 1" class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-slate-800">
            <span class="text-xs text-slate-400">
              Página <strong class="text-white">{{ currentPage }}</strong> de <strong class="text-white">{{ totalPages }}</strong>
            </span>
            <div class="flex items-center space-x-1.5">
              <button
                @click="changePage(currentPage - 1)"
                :disabled="currentPage === 1"
                class="px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-800 text-xs font-medium text-slate-300 hover:text-white disabled:opacity-40 transition-colors"
              >
                Anterior
              </button>
              <button
                v-for="p in Math.min(totalPages, 5)"
                :key="`p-${p}`"
                @click="changePage(p)"
                :class="['w-8 h-8 rounded-xl text-xs font-bold transition-colors', currentPage === p ? 'bg-cyan-600 text-white' : 'bg-slate-900 border border-slate-800 text-slate-400 hover:text-white']"
              >
                {{ p }}
              </button>
              <button
                @click="changePage(currentPage + 1)"
                :disabled="currentPage === totalPages"
                class="px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-800 text-xs font-medium text-slate-300 hover:text-white disabled:opacity-40 transition-colors"
              >
                Siguiente
              </button>
            </div>
          </div>
        </template>
      </main>
    </div>

    <!-- MODAL DETALLE -->
    <DominioDetailModal
      :is-open="isDetailOpen"
      :is-loading="isLoadingDetail"
      :dominio="currentDetail"
      :is-super-admin="isSuperAdmin"
      :format-currency="formatCurrency"
      @close="isDetailOpen = false"
      @edit="openEdit"
    />

    <!-- MODAL FORMULARIO -->
    <DominioFormModal
      :is-open="isFormOpen"
      :is-editing="isEditing"
      :is-saving="isSaving"
      :form-data="formData"
      :clientes-list="clientesList"
      @close="isFormOpen = false"
      @submit="handleSaveDominio"
    />
  </div>
</template>
