<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useClientes } from '@/composables/useClientes'
import { useToast } from '@/composables/useToast'
import { clientesApi, type ClienteListItem, type ClienteDetail, type ClientePayload } from '@/api/clientes'

import AppSidebar from '@/components/layout/AppSidebar.vue'
import AppToast from '@/components/common/AppToast.vue'
import ClienteKpis from '@/components/clientes/ClienteKpis.vue'
import ClienteTable from '@/components/clientes/ClienteTable.vue'
import ClienteCardGrid from '@/components/clientes/ClienteCardGrid.vue'
import ClienteDetailModal from '@/components/clientes/ClienteDetailModal.vue'
import ClienteFormModal from '@/components/clientes/ClienteFormModal.vue'

const router = useRouter()
const authStore = useAuthStore()
const { showToast } = useToast()

const isMobileSidebarOpen = ref(false)
const user = computed(() => authStore.user)
const isSuperAdmin = computed(() => authStore.isSuperAdmin || (user.value?.id_tipo_usuario ?? 0) >= 1)

// Composable de Clientes
const {
  isLoading,
  isSaving,
  clientes,
  stats,
  searchQuery,
  activeFilter,
  currentSistema,
  currentPage,
  totalItems,
  totalPages,
  viewMode,
  loadClientes,
  handleSearchInput,
  clearSearch,
  setFilter,
  changePage,
  removeCliente,
  formatWhatsAppLink,
  formatCurrency,
  getInitials,
} = useClientes()

// Modales y Estados Locales
const isDetailOpen = ref(false)
const isFormOpen = ref(false)
const isEditing = ref(false)
const isLoadingDetail = ref(false)
const currentDetail = ref<ClienteDetail | null>(null)
const editingClientId = ref<number | null>(null)

const formData = ref<ClientePayload>({
  empresa: '',
  nombre_contacto: '',
  correo: '',
  telefono: '',
  rfc: '',
  rsocial: '',
  calle: '',
  next: '',
  nint: '',
  col: '',
  cp: '',
  ciudad: '',
  estado: '',
  pais: 'México',
  especificacion: '',
})

async function openDetail(id: number) {
  isDetailOpen.value = true
  isLoadingDetail.value = true
  try {
    currentDetail.value = await clientesApi.getClienteDetail(id)
  } catch {
    showToast('No se pudo cargar el detalle del cliente', 'error')
    isDetailOpen.value = false
  } finally {
    isLoadingDetail.value = false
  }
}

function openCreate() {
  isEditing.value = false
  editingClientId.value = null
  formData.value = {
    empresa: '',
    nombre_contacto: '',
    correo: '',
    telefono: '',
    rfc: '',
    rsocial: '',
    calle: '',
    next: '',
    nint: '',
    col: '',
    cp: '',
    ciudad: '',
    estado: '',
    pais: 'México',
    especificacion: '',
  }
  isFormOpen.value = true
}

async function openEdit(client: ClienteListItem | ClienteDetail) {
  isEditing.value = true
  editingClientId.value = client.id
  try {
    const d = 'calle' in client ? client : await clientesApi.getClienteDetail(client.id)
    formData.value = {
      empresa: d.empresa || '',
      nombre_contacto: d.nombre_contacto || '',
      correo: d.correo || '',
      telefono: d.telefono || '',
      rfc: d.rfc || '',
      rsocial: d.rsocial || '',
      calle: d.calle || '',
      next: d.next || '',
      nint: d.nint || '',
      col: d.col || '',
      cp: d.cp || '',
      ciudad: d.ciudad || '',
      estado: d.estado || '',
      pais: d.pais || 'México',
      especificacion: d.especificacion || '',
    }
  } catch {
    formData.value.empresa = client.empresa
    formData.value.nombre_contacto = client.nombre_contacto
  }
  isFormOpen.value = true
}

async function handleSaveClient() {
  if (!formData.value.empresa.trim() || !formData.value.nombre_contacto.trim()) {
    showToast('Empresa y Contacto son obligatorios', 'error')
    return
  }
  isSaving.value = true
  try {
    if (isEditing.value && editingClientId.value) {
      const updated = await clientesApi.updateCliente(editingClientId.value, formData.value)
      showToast(`Cliente "${updated.empresa}" actualizado con éxito`)
      if (currentDetail.value && currentDetail.value.id === updated.id) {
        currentDetail.value = updated
      }
    } else {
      const created = await clientesApi.createCliente(formData.value)
      showToast(`Cliente "${created.empresa}" registrado con éxito`)
    }
    isFormOpen.value = false
    loadClientes()
  } catch (err: any) {
    showToast(err.response?.data?.detail || 'Error al guardar cliente', 'error')
  } finally {
    isSaving.value = false
  }
}

function handleLogout() {
  authStore.logout()
  router.push('/login')
}

onMounted(() => {
  loadClientes()
})
</script>

<template>
  <div class="h-screen w-screen bg-[#0F172A] text-slate-100 selection:bg-blue-600 selection:text-white flex overflow-hidden">
    <!-- MENÚ LATERAL -->
    <AppSidebar
      :is-mobile-open="isMobileSidebarOpen"
      @close-mobile="isMobileSidebarOpen = false"
    />

    <!-- CONTENEDOR PRINCIPAL -->
    <div class="flex-1 flex flex-col min-w-0 h-screen overflow-y-auto pb-16">
      <!-- HEADER SUPERIOR -->
      <header class="sticky top-0 z-30 bg-slate-900/80 backdrop-blur-xl border-b border-slate-800/80 h-16 shrink-0">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-full flex items-center justify-between">
          <div class="flex items-center space-x-3">
            <button
              @click="isMobileSidebarOpen = true"
              class="lg:hidden p-2 rounded-xl bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 transition-colors"
            >
              <i class="pi pi-bars text-base"></i>
            </button>
            <div class="flex items-center space-x-2">
              <span class="text-sm font-bold text-white">Directorio de Clientes</span>
              <span class="text-slate-600 hidden sm:inline">•</span>
              <span class="text-xs text-blue-400 font-semibold uppercase tracking-wider hidden sm:inline">{{ currentSistema }}</span>
            </div>
          </div>

          <div class="flex items-center space-x-3">
            <!-- Switcher Sistema -->
            <div v-if="isSuperAdmin" class="hidden sm:flex p-1 rounded-xl bg-slate-950/80 border border-slate-800">
              <button
                @click="currentSistema = 'conlineweb'"
                :class="[
                  'px-3 py-1.5 rounded-lg text-xs font-semibold transition-all',
                  currentSistema === 'conlineweb' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-400 hover:text-white'
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

            <!-- Botón Nuevo Cliente -->
            <button
              v-if="isSuperAdmin"
              @click="openCreate"
              class="px-3.5 py-2 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white text-xs font-bold shadow-lg shadow-blue-500/20 flex items-center space-x-1.5 transition-all duration-200 active:scale-95"
            >
              <i class="pi pi-plus text-xs"></i>
              <span>Nuevo Cliente</span>
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
        <ClienteKpis
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
                placeholder="Buscar por empresa, contacto, correo, RFC..."
                class="w-full pl-10 pr-9 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs sm:text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:border-blue-500 transition-colors"
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
                Mostrando <strong class="text-white">{{ clientes.length }}</strong> de <strong class="text-white">{{ totalItems }}</strong>
              </span>

              <div class="flex p-1 rounded-xl bg-slate-950/80 border border-slate-800">
                <button
                  @click="viewMode = 'table'"
                  :class="['p-1.5 rounded-lg text-xs transition-colors', viewMode === 'table' ? 'bg-slate-800 text-blue-400' : 'text-slate-400 hover:text-white']"
                  title="Vista en tabla"
                >
                  <i class="pi pi-table"></i>
                </button>
                <button
                  @click="viewMode = 'cards'"
                  :class="['p-1.5 rounded-lg text-xs transition-colors', viewMode === 'cards' ? 'bg-slate-800 text-blue-400' : 'text-slate-400 hover:text-white']"
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
                { id: 'activos', label: 'Clientes Activos' },
                { id: 'pendientes', label: 'Con Pagos Pendientes', badge: stats.con_pagos_pendientes },
                { id: 'sin_servicios', label: 'Sin Servicios' },
                { id: 'transferidos', label: 'Transferidos' },
                { id: 'eliminados', label: 'Eliminados' },
                { id: 'todos', label: 'Todos' },
              ]"
              :key="f.id"
              @click="setFilter(f.id)"
              :class="[
                'px-3 py-1.5 rounded-xl font-medium transition-colors shrink-0 flex items-center space-x-1.5',
                activeFilter === f.id ? 'bg-blue-600 text-white font-semibold' : 'bg-slate-800/80 text-slate-400 hover:text-white'
              ]"
            >
              <span>{{ f.label }}</span>
              <span v-if="f.badge && f.badge > 0" class="px-1.5 py-0.2 rounded-full bg-amber-950 text-[10px] text-amber-300 font-bold border border-amber-500/30">
                {{ f.badge }}
              </span>
            </button>
          </div>
        </div>

        <!-- 3. ESTADOS DE CARGA Y LISTA -->
        <div v-if="isLoading" class="py-16 text-center space-y-3">
          <i class="pi pi-spin pi-spinner text-3xl text-blue-500"></i>
          <p class="text-xs text-slate-400">Consultando base de clientes...</p>
        </div>

        <div v-else-if="clientes.length === 0" class="p-12 text-center rounded-3xl bg-slate-900/40 border border-slate-800/60 space-y-3">
          <i class="pi pi-inbox text-4xl text-slate-600"></i>
          <h3 class="text-base font-bold text-white">No se encontraron clientes</h3>
          <button
            @click="clearSearch(); setFilter('activos')"
            class="mt-2 px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-white transition-colors"
          >
            Restablecer Filtros
          </button>
        </div>

        <template v-else>
          <!-- 4. TABLA DESKTOP -->
          <ClienteTable
            v-if="viewMode === 'table'"
            :clientes="clientes"
            :is-super-admin="isSuperAdmin"
            :get-initials="getInitials"
            :format-whats-app-link="formatWhatsAppLink"
            @view-detail="openDetail"
            @edit="openEdit"
            @delete="removeCliente"
          />

          <!-- 5. GRID TARJETAS MOBILE -->
          <ClienteCardGrid
            :clientes="clientes"
            :view-mode="viewMode"
            :is-super-admin="isSuperAdmin"
            :get-initials="getInitials"
            :format-whats-app-link="formatWhatsAppLink"
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
                :class="['w-8 h-8 rounded-xl text-xs font-bold transition-colors', currentPage === p ? 'bg-blue-600 text-white' : 'bg-slate-900 border border-slate-800 text-slate-400 hover:text-white']"
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
    <ClienteDetailModal
      :is-open="isDetailOpen"
      :is-loading="isLoadingDetail"
      :cliente="currentDetail"
      :is-super-admin="isSuperAdmin"
      :get-initials="getInitials"
      :format-whats-app-link="formatWhatsAppLink"
      :format-currency="formatCurrency"
      @close="isDetailOpen = false"
      @edit="openEdit"
    />

    <!-- MODAL FORMULARIO -->
    <ClienteFormModal
      :is-open="isFormOpen"
      :is-editing="isEditing"
      :is-saving="isSaving"
      :form-data="formData"
      @close="isFormOpen = false"
      @submit="handleSaveClient"
    />
  </div>
</template>
