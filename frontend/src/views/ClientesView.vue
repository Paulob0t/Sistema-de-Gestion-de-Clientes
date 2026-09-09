<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import {
  clientesApi,
  type ClienteListItem,
  type ClienteDetail,
  type ClienteStats,
  type ClientePayload
} from '@/api/clientes'
import AppSidebar from '@/components/layout/AppSidebar.vue'

const authStore = useAuthStore()

const isMobileSidebarOpen = ref(false)
const user = computed(() => authStore.user)
const isSuperAdmin = computed(() => authStore.isSuperAdmin || (user.value?.id_tipo_usuario ?? 0) >= 1)

// Control de datos
const isLoading = ref(true)
const isSaving = ref(false)
const clientes = ref<ClienteListItem[]>([])
const stats = ref<ClienteStats>({
  total: 0,
  activos: 0,
  con_pagos_pendientes: 0,
  transferidos: 0,
  eliminados: 0,
})

// Paginación y Filtros
const searchQuery = ref('')
const activeFilter = ref<string>('activos')
const currentSistema = ref<'conlineweb' | 'hostingpro'>('conlineweb')
const currentPage = ref(1)
const itemsPerPage = ref(20)
const totalItems = ref(0)
const totalPages = ref(1)
const viewMode = ref<'table' | 'cards'>('table')

// Modales y Drawers
const isDetailOpen = ref(false)
const isFormOpen = ref(false)
const isEditing = ref(false)
const currentDetail = ref<ClienteDetail | null>(null)
const isLoadingDetail = ref(false)
const detailTab = ref<'info' | 'dominios' | 'hosting' | 'pagos'>('info')

// Notificaciones Toast
const toastMessage = ref<string | null>(null)
const toastType = ref<'success' | 'error'>('success')

function showToast(msg: string, type: 'success' | 'error' = 'success') {
  toastMessage.value = msg
  toastType.value = type
  setTimeout(() => {
    toastMessage.value = null
  }, 3500)
}

// Formulario de Cliente
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
const editingClientId = ref<number | null>(null)

// Cargar Clientes
async function loadClientes() {
  isLoading.value = true
  try {
    const res = await clientesApi.getClientes({
      search: searchQuery.value || undefined,
      filtro: activeFilter.value,
      sistema: currentSistema.value,
      page: currentPage.value,
      limit: itemsPerPage.value,
    })
    clientes.value = res.items
    totalItems.value = res.total
    totalPages.value = res.total_pages
    stats.value = res.stats
  } catch (err: any) {
    showToast(err.response?.data?.detail || 'Error al cargar el directorio de clientes', 'error')
  } finally {
    isLoading.value = false
  }
}

// Debounce para búsqueda
let searchTimeout: any = null
function handleSearchInput() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    currentPage.value = 1
    loadClientes()
  }, 350)
}

function clearSearch() {
  searchQuery.value = ''
  currentPage.value = 1
  loadClientes()
}

function setFilter(filtro: string) {
  activeFilter.value = filtro
  currentPage.value = 1
  loadClientes()
}

function changePage(page: number) {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page
    loadClientes()
  }
}

// Ver Detalle de Cliente
async function openClientDetail(clientId: number) {
  isDetailOpen.value = true
  isLoadingDetail.value = true
  detailTab.value = 'info'
  try {
    const data = await clientesApi.getClienteDetail(clientId)
    currentDetail.value = data
  } catch {
    showToast('No se pudo obtener los datos del cliente', 'error')
    isDetailOpen.value = false
  } finally {
    isLoadingDetail.value = false
  }
}

// Abrir Formulario Nuevo
function openCreateModal() {
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

// Abrir Formulario Editar
async function openEditModal(client: ClienteListItem | ClienteDetail) {
  isEditing.value = true
  editingClientId.value = client.id
  
  if ('calle' in client) {
    formData.value = {
      empresa: client.empresa || '',
      nombre_contacto: client.nombre_contacto || '',
      correo: client.correo || '',
      telefono: client.telefono || '',
      rfc: client.rfc || '',
      rsocial: client.rsocial || '',
      calle: client.calle || '',
      next: client.next || '',
      nint: client.nint || '',
      col: client.col || '',
      cp: client.cp || '',
      ciudad: client.ciudad || '',
      estado: client.estado || '',
      pais: client.pais || 'México',
      especificacion: client.especificacion || '',
    }
  } else {
    try {
      const detail = await clientesApi.getClienteDetail(client.id)
      formData.value = {
        empresa: detail.empresa || '',
        nombre_contacto: detail.nombre_contacto || '',
        correo: detail.correo || '',
        telefono: detail.telefono || '',
        rfc: detail.rfc || '',
        rsocial: detail.rsocial || '',
        calle: detail.calle || '',
        next: detail.next || '',
        nint: detail.nint || '',
        col: detail.col || '',
        cp: detail.cp || '',
        ciudad: detail.ciudad || '',
        estado: detail.estado || '',
        pais: detail.pais || 'México',
        especificacion: detail.especificacion || '',
      }
    } catch {
      formData.value = {
        empresa: client.empresa || '',
        nombre_contacto: client.nombre_contacto || '',
        correo: client.correo || '',
        telefono: client.telefono || '',
        rfc: client.rfc || '',
        rsocial: '',
        calle: '',
        next: '',
        nint: '',
        col: '',
        cp: '',
        ciudad: client.ciudad || '',
        estado: client.estado || '',
        pais: 'México',
        especificacion: '',
      }
    }
  }
  isFormOpen.value = true
}

// Guardar Cliente
async function submitClientForm() {
  if (!formData.value.empresa.trim() || !formData.value.nombre_contacto.trim()) {
    showToast('La empresa y el contacto son obligatorios', 'error')
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
    showToast(err.response?.data?.detail || 'Error al guardar los datos del cliente', 'error')
  } finally {
    isSaving.value = false
  }
}

// Eliminar Cliente
async function deleteClient(client: ClienteListItem) {
  const confirmDelete = window.confirm(`¿Estás seguro de que deseas dar de baja al cliente "${client.empresa}"?`)
  if (!confirmDelete) return

  try {
    await clientesApi.deleteCliente(client.id)
    showToast(`Cliente "${client.empresa}" marcado como eliminado`)
    if (isDetailOpen.value) {
      isDetailOpen.value = false
    }
    loadClientes()
  } catch (err: any) {
    showToast(err.response?.data?.detail || 'Error al eliminar cliente', 'error')
  }
}

function formatWhatsAppLink(phone: string | null): string {
  if (!phone) return '#'
  const cleanPhone = phone.replace(/[^0-9]/g, '')
  return `https://api.whatsapp.com/send?phone=${cleanPhone}&text=Hola,%20nos%20comunicamos%20de%20NexusBot`
}

function formatCurrency(amount: number, currency: string = 'MXN'): string {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: currency || 'MXN',
    minimumFractionDigits: 2
  }).format(amount)
}

function getInitials(name: string): string {
  if (!name) return 'NB'
  const parts = name.trim().split(/\s+/)
  if (parts.length >= 2) {
    return (parts[0][0] + parts[1][0]).toUpperCase()
  }
  return name.substring(0, 2).toUpperCase()
}

watch(currentSistema, () => {
  currentPage.value = 1
  loadClientes()
})

onMounted(() => {
  loadClientes()
})
</script>

<template>
  <div class="min-h-screen bg-[#0F172A] text-slate-100 selection:bg-blue-600 selection:text-white flex overflow-x-hidden">
    <!-- MENÚ LATERAL (SIDEBAR) -->
    <AppSidebar
      :is-mobile-open="isMobileSidebarOpen"
      @close-mobile="isMobileSidebarOpen = false"
    />

    <!-- CONTENEDOR PRINCIPAL -->
    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto pb-16">
      <!-- HEADER SUPERIOR -->
      <header class="sticky top-0 z-30 bg-slate-900/80 backdrop-blur-xl border-b border-slate-800/80 h-16 shrink-0">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-full flex items-center justify-between">
          <!-- Botón Menú Móvil y Título -->
          <div class="flex items-center space-x-3">
            <button
              @click="isMobileSidebarOpen = true"
              class="lg:hidden p-2 rounded-xl bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 transition-colors"
              title="Abrir menú"
            >
              <i class="pi pi-bars text-base"></i>
            </button>
            <div class="flex items-center space-x-2">
              <span class="text-sm font-bold text-white">Directorio de Clientes</span>
              <span class="text-slate-600 hidden sm:inline">•</span>
              <span class="text-xs text-blue-400 font-semibold uppercase tracking-wider hidden sm:inline">{{ currentSistema }}</span>
            </div>
          </div>

          <!-- Acciones Superiores -->
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
              @click="openCreateModal"
              class="px-3.5 py-2 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white text-xs font-bold shadow-lg shadow-blue-500/20 flex items-center space-x-1.5 transition-all duration-200 active:scale-95"
            >
              <i class="pi pi-plus text-xs"></i>
              <span>Nuevo Cliente</span>
            </button>
          </div>
        </div>
      </header>

      <!-- NOTIFICACIÓN TOAST -->
      <transition name="fade">
        <div
          v-if="toastMessage"
          :class="[
            'fixed bottom-6 right-6 z-50 px-4 py-3 rounded-xl border text-xs font-semibold shadow-2xl flex items-center space-x-2',
            toastType === 'success' ? 'bg-slate-900 border-emerald-500/40 text-emerald-300' : 'bg-slate-900 border-red-500/40 text-red-300'
          ]"
        >
          <i :class="toastType === 'success' ? 'pi pi-check-circle text-emerald-400' : 'pi pi-exclamation-triangle text-red-400'"></i>
          <span>{{ toastMessage }}</span>
        </div>
      </transition>

      <!-- CONTENIDO PRINCIPAL -->
      <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 space-y-6 w-full">
        <!-- BARRA DE KPIS SUPERIORES -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
          <!-- Total -->
          <div
            @click="setFilter('todos')"
            :class="[
              'p-4 rounded-2xl border transition-all cursor-pointer select-none',
              activeFilter === 'todos'
                ? 'bg-slate-800/90 border-blue-500/50 shadow-lg shadow-blue-500/10'
                : 'bg-slate-900/60 border-slate-800/80 hover:border-slate-700'
            ]"
          >
            <div class="flex items-center justify-between">
              <span class="text-xs text-slate-400 font-medium">Total Clientes</span>
              <i class="pi pi-users text-xs text-slate-500"></i>
            </div>
            <div class="mt-2 text-2xl font-bold text-white">{{ stats.total }}</div>
          </div>

          <!-- Activos -->
          <div
            @click="setFilter('activos')"
            :class="[
              'p-4 rounded-2xl border transition-all cursor-pointer select-none',
              activeFilter === 'activos'
                ? 'bg-emerald-950/40 border-emerald-500/50 shadow-lg shadow-emerald-500/10'
                : 'bg-slate-900/60 border-slate-800/80 hover:border-emerald-900/50'
            ]"
          >
            <div class="flex items-center justify-between">
              <span class="text-xs text-emerald-400 font-medium">Activos</span>
              <div class="w-2 h-2 rounded-full bg-emerald-400"></div>
            </div>
            <div class="mt-2 text-2xl font-bold text-white">{{ stats.activos }}</div>
          </div>

          <!-- Pagos Pendientes -->
          <div
            @click="setFilter('pendientes')"
            :class="[
              'p-4 rounded-2xl border transition-all cursor-pointer select-none',
              activeFilter === 'pendientes'
                ? 'bg-amber-950/40 border-amber-500/50 shadow-lg shadow-amber-500/10'
                : 'bg-slate-900/60 border-slate-800/80 hover:border-amber-900/50'
            ]"
          >
            <div class="flex items-center justify-between">
              <span class="text-xs text-amber-400 font-medium">Con Pagos Pend.</span>
              <i class="pi pi-clock text-xs text-amber-400"></i>
            </div>
            <div class="mt-2 text-2xl font-bold text-amber-300">{{ stats.con_pagos_pendientes }}</div>
          </div>

          <!-- Transferidos -->
          <div
            @click="setFilter('transferidos')"
            :class="[
              'p-4 rounded-2xl border transition-all cursor-pointer select-none',
              activeFilter === 'transferidos'
                ? 'bg-indigo-950/40 border-indigo-500/50 shadow-lg shadow-indigo-500/10'
                : 'bg-slate-900/60 border-slate-800/80 hover:border-indigo-900/50'
            ]"
          >
            <div class="flex items-center justify-between">
              <span class="text-xs text-indigo-400 font-medium">Transferidos</span>
              <i class="pi pi-sync text-xs text-indigo-400"></i>
            </div>
            <div class="mt-2 text-2xl font-bold text-white">{{ stats.transferidos }}</div>
          </div>

          <!-- Eliminados -->
          <div
            @click="setFilter('eliminados')"
            :class="[
              'col-span-2 sm:col-span-1 p-4 rounded-2xl border transition-all cursor-pointer select-none',
              activeFilter === 'eliminados'
                ? 'bg-red-950/40 border-red-500/50 shadow-lg shadow-red-500/10'
                : 'bg-slate-900/60 border-slate-800/80 hover:border-red-900/50'
            ]"
          >
            <div class="flex items-center justify-between">
              <span class="text-xs text-rose-400 font-medium">Bajas / Eliminados</span>
              <i class="pi pi-trash text-xs text-rose-400"></i>
            </div>
            <div class="mt-2 text-2xl font-bold text-white">{{ stats.eliminados }}</div>
          </div>
        </div>

        <!-- BARRA DE BÚSQUEDA Y FILTROS -->
        <div class="p-4 sm:p-5 rounded-3xl bg-slate-900/70 border border-slate-800/80 space-y-4">
          <div class="flex flex-col md:flex-row gap-3 items-center justify-between">
            <!-- Input de Búsqueda -->
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

            <!-- Selector de Vista (Tabla / Tarjetas) & Conteo -->
            <div class="flex items-center justify-between w-full md:w-auto space-x-3">
              <span class="text-xs text-slate-400">
                Mostrando <strong class="text-white">{{ clientes.length }}</strong> de <strong class="text-white">{{ totalItems }}</strong>
              </span>

              <div class="flex p-1 rounded-xl bg-slate-950/80 border border-slate-800">
                <button
                  @click="viewMode = 'table'"
                  :class="[
                    'p-1.5 rounded-lg text-xs transition-colors',
                    viewMode === 'table' ? 'bg-slate-800 text-blue-400' : 'text-slate-400 hover:text-white'
                  ]"
                  title="Vista en tabla"
                >
                  <i class="pi pi-table"></i>
                </button>
                <button
                  @click="viewMode = 'cards'"
                  :class="[
                    'p-1.5 rounded-lg text-xs transition-colors',
                    viewMode === 'cards' ? 'bg-slate-800 text-blue-400' : 'text-slate-400 hover:text-white'
                  ]"
                  title="Vista en tarjetas"
                >
                  <i class="pi pi-th-large"></i>
                </button>
              </div>
            </div>
          </div>

          <!-- Pestañas de Filtro Rápido -->
          <div class="flex items-center gap-2 overflow-x-auto pb-1 text-xs select-none">
            <button
              @click="setFilter('activos')"
              :class="[
                'px-3 py-1.5 rounded-xl font-medium transition-colors shrink-0',
                activeFilter === 'activos' ? 'bg-blue-600 text-white font-semibold' : 'bg-slate-800/80 text-slate-400 hover:text-white'
              ]"
            >
              Clientes Activos
            </button>
            <button
              @click="setFilter('pendientes')"
              :class="[
                'px-3 py-1.5 rounded-xl font-medium transition-colors shrink-0 flex items-center space-x-1.5',
                activeFilter === 'pendientes' ? 'bg-amber-600 text-white font-semibold' : 'bg-slate-800/80 text-amber-400/80 hover:text-amber-300'
              ]"
            >
              <span>Con Pagos Pendientes</span>
              <span v-if="stats.con_pagos_pendientes > 0" class="px-1.5 py-0.2 rounded-full bg-amber-950 text-[10px] text-amber-300 font-bold border border-amber-500/30">
                {{ stats.con_pagos_pendientes }}
              </span>
            </button>
            <button
              @click="setFilter('sin_servicios')"
              :class="[
                'px-3 py-1.5 rounded-xl font-medium transition-colors shrink-0',
                activeFilter === 'sin_servicios' ? 'bg-blue-600 text-white font-semibold' : 'bg-slate-800/80 text-slate-400 hover:text-white'
              ]"
            >
              Sin Servicios Activos
            </button>
            <button
              @click="setFilter('transferidos')"
              :class="[
                'px-3 py-1.5 rounded-xl font-medium transition-colors shrink-0',
                activeFilter === 'transferidos' ? 'bg-blue-600 text-white font-semibold' : 'bg-slate-800/80 text-slate-400 hover:text-white'
              ]"
            >
              Transferidos
            </button>
            <button
              @click="setFilter('eliminados')"
              :class="[
                'px-3 py-1.5 rounded-xl font-medium transition-colors shrink-0',
                activeFilter === 'eliminados' ? 'bg-rose-600 text-white font-semibold' : 'bg-slate-800/80 text-rose-400/80 hover:text-rose-300'
              ]"
            >
              Eliminados
            </button>
            <button
              @click="setFilter('todos')"
              :class="[
                'px-3 py-1.5 rounded-xl font-medium transition-colors shrink-0',
                activeFilter === 'todos' ? 'bg-blue-600 text-white font-semibold' : 'bg-slate-800/80 text-slate-400 hover:text-white'
              ]"
            >
              Todos
            </button>
          </div>
        </div>

        <!-- ESTADO DE CARGA -->
        <div v-if="isLoading" class="py-16 text-center space-y-3">
          <i class="pi pi-spin pi-spinner text-3xl text-blue-500"></i>
          <p class="text-xs text-slate-400">Consultando base de clientes...</p>
        </div>

        <!-- LISTADO VACÍO -->
        <div v-else-if="clientes.length === 0" class="p-12 text-center rounded-3xl bg-slate-900/40 border border-slate-800/60 space-y-3">
          <i class="pi pi-inbox text-4xl text-slate-600"></i>
          <h3 class="text-base font-bold text-white">No se encontraron clientes</h3>
          <p class="text-xs text-slate-400 max-w-sm mx-auto">
            Intenta cambiar los términos de búsqueda o selecciona otro filtro de estado.
          </p>
          <button
            @click="clearSearch(); setFilter('activos')"
            class="mt-2 px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-white transition-colors"
          >
            Restablecer Filtros
          </button>
        </div>

        <!-- CONTENIDO: VISTA EN TABLA (ESCRITORIO & TABLETS GRANDES) -->
        <div v-else-if="viewMode === 'table'" class="hidden md:block rounded-3xl bg-slate-900/70 border border-slate-800/80 overflow-hidden shadow-2xl">
          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead class="bg-slate-950/80 text-slate-400 uppercase tracking-wider font-semibold border-b border-slate-800">
                <tr>
                  <th class="py-3.5 px-4">Cliente / Empresa</th>
                  <th class="py-3.5 px-4">Contacto & Correo</th>
                  <th class="py-3.5 px-4 text-center">Infraestructura</th>
                  <th class="py-3.5 px-4 text-center">Estado Cobranza</th>
                  <th class="py-3.5 px-4 text-right">Acciones Rápidas</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800/60">
                <tr
                  v-for="item in clientes"
                  :key="item.id"
                  class="hover:bg-slate-800/40 transition-colors group"
                >
                  <!-- Empresa & Avatar -->
                  <td class="py-3.5 px-4">
                    <div class="flex items-center space-x-3">
                      <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-600/30 to-indigo-600/30 border border-blue-500/30 flex items-center justify-center font-bold text-xs text-blue-400 shrink-0">
                        {{ getInitials(item.empresa) }}
                      </div>
                      <div class="min-w-0">
                        <div class="font-bold text-white text-sm truncate max-w-xs flex items-center space-x-2">
                          <span>{{ item.empresa }}</span>
                          <span v-if="item.transferido === 1" class="px-1.5 py-0.5 rounded text-[10px] bg-indigo-950 text-indigo-300 border border-indigo-500/30 font-normal">Transferido</span>
                          <span v-if="item.eliminado === 1" class="px-1.5 py-0.5 rounded text-[10px] bg-rose-950 text-rose-300 border border-rose-500/30 font-normal">Baja</span>
                        </div>
                        <div class="text-[11px] text-slate-400 flex items-center space-x-1.5 mt-0.5">
                          <span>ID: #{{ item.id }}</span>
                          <span v-if="item.rfc">• RFC: {{ item.rfc }}</span>
                        </div>
                      </div>
                    </div>
                  </td>

                  <!-- Contacto & Correo -->
                  <td class="py-3.5 px-4">
                    <div class="space-y-0.5">
                      <div class="font-medium text-slate-200">{{ item.nombre_contacto }}</div>
                      <div class="text-[11px] text-slate-400 flex items-center space-x-1">
                        <i class="pi pi-envelope text-[10px] text-slate-500"></i>
                        <a :href="`mailto:${item.correo}`" class="hover:text-blue-400 transition-colors truncate max-w-[180px]">
                          {{ item.correo || 'Sin correo' }}
                        </a>
                      </div>
                    </div>
                  </td>

                  <!-- Infraestructura (Dominios y Hosting) -->
                  <td class="py-3.5 px-4 text-center">
                    <div class="inline-flex items-center justify-center space-x-1.5">
                      <!-- Badge Dominios -->
                      <span
                        :class="[
                          'px-2.5 py-1 rounded-lg text-xs font-bold flex items-center space-x-1',
                          item.total_dominios > 0
                            ? 'bg-blue-950/80 text-blue-300 border border-blue-500/40'
                            : 'bg-slate-950/60 text-slate-500 border border-slate-800'
                        ]"
                        title="Dominios Web Activos"
                      >
                        <i class="pi pi-globe text-[11px]"></i>
                        <span>{{ item.total_dominios }}</span>
                      </span>

                      <!-- Badge Hosting -->
                      <span
                        :class="[
                          'px-2.5 py-1 rounded-lg text-xs font-bold flex items-center space-x-1',
                          item.total_hostings > 0
                            ? 'bg-emerald-950/80 text-emerald-300 border border-emerald-500/40'
                            : 'bg-slate-950/60 text-slate-500 border border-slate-800'
                        ]"
                        title="Planes de Hosting Activos"
                      >
                        <i class="pi pi-server text-[11px]"></i>
                        <span>{{ item.total_hostings }}</span>
                      </span>
                    </div>
                  </td>

                  <!-- Estado Cobranza -->
                  <td class="py-3.5 px-4 text-center">
                    <span
                      v-if="item.estado_pago === 'al_dia'"
                      class="inline-flex items-center space-x-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-950/80 text-emerald-300 border border-emerald-500/30"
                    >
                      <i class="pi pi-check text-[10px]"></i>
                      <span>Al corriente</span>
                    </span>

                    <span
                      v-else-if="item.estado_pago === 'pendiente'"
                      class="inline-flex items-center space-x-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-950/80 text-amber-300 border border-amber-500/40 animate-pulse"
                    >
                      <i class="pi pi-clock text-[10px]"></i>
                      <span>{{ item.total_pagos_pendientes }} Pendiente{{ item.total_pagos_pendientes > 1 ? 's' : '' }}</span>
                    </span>

                    <span
                      v-else
                      class="inline-flex items-center space-x-1 px-2.5 py-1 rounded-full text-[11px] font-medium bg-slate-950 text-slate-500 border border-slate-800"
                    >
                      <span>Sin servicios</span>
                    </span>
                  </td>

                  <!-- Acciones Rápidas -->
                  <td class="py-3.5 px-4 text-right">
                    <div class="flex items-center justify-end space-x-1.5">
                      <!-- WhatsApp -->
                      <a
                        v-if="item.telefono"
                        :href="formatWhatsAppLink(item.telefono)"
                        target="_blank"
                        rel="noopener"
                        class="p-2 rounded-xl bg-emerald-950/60 hover:bg-emerald-600/80 text-emerald-400 hover:text-white border border-emerald-500/30 transition-all"
                        title="Enviar WhatsApp"
                      >
                        <i class="pi pi-whatsapp text-xs"></i>
                      </a>

                      <!-- Ver Ficha / Detalle -->
                      <button
                        @click="openClientDetail(item.id)"
                        class="p-2 rounded-xl bg-slate-800 hover:bg-blue-600 text-slate-300 hover:text-white border border-slate-700/60 transition-all"
                        title="Ver Perfil Completo"
                      >
                        <i class="pi pi-eye text-xs"></i>
                      </button>

                      <!-- Editar -->
                      <button
                        v-if="isSuperAdmin"
                        @click="openEditModal(item)"
                        class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700/60 transition-all"
                        title="Editar Datos"
                      >
                        <i class="pi pi-pencil text-xs"></i>
                      </button>

                      <!-- Eliminar -->
                      <button
                        v-if="isSuperAdmin && item.eliminado === 0"
                        @click="deleteClient(item)"
                        class="p-2 rounded-xl bg-slate-800 hover:bg-rose-600/80 text-slate-400 hover:text-rose-200 border border-slate-700/60 transition-all"
                        title="Dar de baja"
                      >
                        <i class="pi pi-trash text-xs"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- CONTENIDO: VISTA EN TARJETAS (MÓVIL O MODO GRID) -->
        <div v-if="viewMode === 'cards' || true" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" :class="viewMode === 'table' ? 'md:hidden' : ''">
          <div
            v-for="item in clientes"
            :key="`card-${item.id}`"
            class="p-5 rounded-3xl bg-slate-900/70 border border-slate-800/80 hover:border-slate-700 transition-all space-y-4 shadow-xl flex flex-col justify-between"
          >
            <div>
              <!-- Header Tarjeta -->
              <div class="flex items-start justify-between gap-3">
                <div class="flex items-center space-x-3 min-w-0">
                  <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-blue-600/30 to-indigo-600/30 border border-blue-500/30 flex items-center justify-center font-bold text-sm text-blue-400 shrink-0">
                    {{ getInitials(item.empresa) }}
                  </div>
                  <div class="min-w-0">
                    <h4 class="font-bold text-white text-sm truncate">{{ item.empresa }}</h4>
                    <p class="text-xs text-slate-400 truncate">{{ item.nombre_contacto }}</p>
                  </div>
                </div>

                <!-- Estado Cobranza Badge -->
                <div>
                  <span
                    v-if="item.estado_pago === 'al_dia'"
                    class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-950 text-emerald-300 border border-emerald-500/30"
                  >
                    Al día
                  </span>
                  <span
                    v-else-if="item.estado_pago === 'pendiente'"
                    class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-950 text-amber-300 border border-amber-500/40 animate-pulse"
                  >
                    {{ item.total_pagos_pendientes }} Pend.
                  </span>
                  <span
                    v-else
                    class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-950 text-slate-500 border border-slate-800"
                  >
                    Sin serv.
                  </span>
                </div>
              </div>

              <!-- Contacto -->
              <div class="mt-4 pt-3 border-t border-slate-800/80 space-y-1.5 text-xs text-slate-300">
                <div v-if="item.correo" class="flex items-center space-x-2 truncate">
                  <i class="pi pi-envelope text-[11px] text-slate-500"></i>
                  <a :href="`mailto:${item.correo}`" class="hover:text-blue-400 transition-colors truncate">
                    {{ item.correo }}
                  </a>
                </div>
                <div v-if="item.telefono" class="flex items-center space-x-2">
                  <i class="pi pi-phone text-[11px] text-slate-500"></i>
                  <span>{{ item.telefono }}</span>
                </div>
              </div>

              <!-- Badges de Infraestructura -->
              <div class="mt-4 flex items-center space-x-2 text-xs">
                <div class="flex-1 p-2 rounded-xl bg-slate-950/60 border border-slate-800 flex items-center justify-between">
                  <span class="text-slate-400 text-[11px]">Dominios</span>
                  <span class="font-bold text-blue-400">{{ item.total_dominios }}</span>
                </div>
                <div class="flex-1 p-2 rounded-xl bg-slate-950/60 border border-slate-800 flex items-center justify-between">
                  <span class="text-slate-400 text-[11px]">Hosting</span>
                  <span class="font-bold text-emerald-400">{{ item.total_hostings }}</span>
                </div>
              </div>
            </div>

            <!-- Botones de Acción Móvil -->
            <div class="pt-3 border-t border-slate-800/80 flex items-center space-x-2">
              <a
                v-if="item.telefono"
                :href="formatWhatsAppLink(item.telefono)"
                target="_blank"
                rel="noopener"
                class="flex-1 py-2 rounded-xl bg-emerald-600/20 hover:bg-emerald-600/40 text-emerald-300 border border-emerald-500/30 text-xs font-bold flex items-center justify-center space-x-1.5 transition-colors"
              >
                <i class="pi pi-whatsapp text-xs"></i>
                <span>WhatsApp</span>
              </a>

              <button
                @click="openClientDetail(item.id)"
                class="flex-1 py-2 rounded-xl bg-slate-800 hover:bg-blue-600 text-slate-200 hover:text-white border border-slate-700/60 text-xs font-semibold flex items-center justify-center space-x-1.5 transition-colors"
              >
                <i class="pi pi-eye text-xs"></i>
                <span>Detalles</span>
              </button>

              <button
                v-if="isSuperAdmin"
                @click="openEditModal(item)"
                class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700/60 transition-colors"
                title="Editar"
              >
                <i class="pi pi-pencil text-xs"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- PAGINACIÓN -->
        <div v-if="totalPages > 1" class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-slate-800">
          <span class="text-xs text-slate-400">
            Página <strong class="text-white">{{ currentPage }}</strong> de <strong class="text-white">{{ totalPages }}</strong>
          </span>

          <div class="flex items-center space-x-1.5">
            <button
              @click="changePage(currentPage - 1)"
              :disabled="currentPage === 1"
              class="px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-800 text-xs font-medium text-slate-300 hover:text-white disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            >
              Anterior
            </button>

            <template v-for="p in Math.min(totalPages, 5)" :key="`p-${p}`">
              <button
                @click="changePage(p)"
                :class="[
                  'w-8 h-8 rounded-xl text-xs font-bold transition-colors',
                  currentPage === p ? 'bg-blue-600 text-white' : 'bg-slate-900 border border-slate-800 text-slate-400 hover:text-white'
                ]"
              >
                {{ p }}
              </button>
            </template>

            <button
              @click="changePage(currentPage + 1)"
              :disabled="currentPage === totalPages"
              class="px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-800 text-xs font-medium text-slate-300 hover:text-white disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            >
              Siguiente
            </button>
          </div>
        </div>
      </main>
    </div>

    <!-- MODAL DETALLE DE CLIENTE (DRAWER / OVERLAY) -->
    <div
      v-if="isDetailOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-950/80 backdrop-blur-md"
    >
      <div class="w-full max-w-4xl bg-slate-900 border border-slate-800 rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
        <!-- Header Modal -->
        <div class="p-6 bg-slate-950/60 border-b border-slate-800 flex items-center justify-between shrink-0">
          <div class="flex items-center space-x-3">
            <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-600 flex items-center justify-center font-bold text-white text-base shadow-lg shadow-blue-500/20">
              {{ getInitials(currentDetail?.empresa || '') }}
            </div>
            <div>
              <h3 class="text-lg font-bold text-white">{{ currentDetail?.empresa || 'Cargando...' }}</h3>
              <p class="text-xs text-slate-400">{{ currentDetail?.nombre_contacto }} • ID: #{{ currentDetail?.id }}</p>
            </div>
          </div>

          <div class="flex items-center space-x-2">
            <button
              v-if="currentDetail && isSuperAdmin"
              @click="openEditModal(currentDetail)"
              class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold border border-slate-700 flex items-center space-x-1.5 transition-colors"
            >
              <i class="pi pi-pencil text-xs"></i>
              <span>Editar</span>
            </button>
            <button
              @click="isDetailOpen = false"
              class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white transition-colors"
            >
              <i class="pi pi-times text-sm"></i>
            </button>
          </div>
        </div>

        <!-- Pestañas del Detalle -->
        <div class="flex items-center px-6 pt-3 border-b border-slate-800 bg-slate-950/30 gap-4 text-xs font-semibold shrink-0">
          <button
            @click="detailTab = 'info'"
            :class="[
              'pb-3 transition-colors border-b-2',
              detailTab === 'info' ? 'border-blue-500 text-blue-400' : 'border-transparent text-slate-400 hover:text-white'
            ]"
          >
            Información General & Fiscal
          </button>
          <button
            @click="detailTab = 'dominios'"
            :class="[
              'pb-3 transition-colors border-b-2 flex items-center space-x-1.5',
              detailTab === 'dominios' ? 'border-blue-500 text-blue-400' : 'border-transparent text-slate-400 hover:text-white'
            ]"
          >
            <span>Dominios</span>
            <span class="px-1.5 py-0.2 rounded-full bg-blue-950 text-[10px] text-blue-300 font-bold border border-blue-500/30">
              {{ currentDetail?.total_dominios ?? 0 }}
            </span>
          </button>
          <button
            @click="detailTab = 'hosting'"
            :class="[
              'pb-3 transition-colors border-b-2 flex items-center space-x-1.5',
              detailTab === 'hosting' ? 'border-blue-500 text-blue-400' : 'border-transparent text-slate-400 hover:text-white'
            ]"
          >
            <span>Hosting</span>
            <span class="px-1.5 py-0.2 rounded-full bg-emerald-950 text-[10px] text-emerald-300 font-bold border border-emerald-500/30">
              {{ currentDetail?.total_hostings ?? 0 }}
            </span>
          </button>
          <button
            @click="detailTab = 'pagos'"
            :class="[
              'pb-3 transition-colors border-b-2 flex items-center space-x-1.5',
              detailTab === 'pagos' ? 'border-blue-500 text-blue-400' : 'border-transparent text-slate-400 hover:text-white'
            ]"
          >
            <span>Historial Pagos</span>
            <span v-if="(currentDetail?.total_pagos_pendientes ?? 0) > 0" class="px-1.5 py-0.2 rounded-full bg-amber-950 text-[10px] text-amber-300 font-bold border border-amber-500/30">
              {{ currentDetail?.total_pagos_pendientes }}
            </span>
          </button>
        </div>

        <!-- Cuerpo del Modal -->
        <div class="p-6 overflow-y-auto flex-1 space-y-6">
          <div v-if="isLoadingDetail" class="py-12 text-center">
            <i class="pi pi-spin pi-spinner text-3xl text-blue-500"></i>
          </div>

          <template v-else-if="currentDetail">
            <!-- TAB 1: INFORMACIÓN GENERAL -->
            <div v-if="detailTab === 'info'" class="space-y-6 text-xs">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="p-4 rounded-2xl bg-slate-950/60 border border-slate-800/80 space-y-3">
                  <h4 class="font-bold text-white text-sm">Datos de Contacto</h4>
                  <div class="space-y-2 text-slate-300">
                    <div>
                      <span class="text-slate-500 block text-[11px]">Nombre de Contacto</span>
                      <strong class="text-white">{{ currentDetail.nombre_contacto || 'N/D' }}</strong>
                    </div>
                    <div>
                      <span class="text-slate-500 block text-[11px]">Correo Electrónico</span>
                      <a :href="`mailto:${currentDetail.correo}`" class="text-blue-400 hover:underline">
                        {{ currentDetail.correo || 'N/D' }}
                      </a>
                    </div>
                    <div>
                      <span class="text-slate-500 block text-[11px]">Teléfono / WhatsApp</span>
                      <div class="flex items-center space-x-2 mt-0.5">
                        <span class="text-white">{{ currentDetail.telefono || 'N/D' }}</span>
                        <a
                          v-if="currentDetail.telefono"
                          :href="formatWhatsAppLink(currentDetail.telefono)"
                          target="_blank"
                          class="px-2 py-0.5 rounded bg-emerald-950 text-emerald-400 border border-emerald-500/30 text-[10px] font-bold hover:bg-emerald-900"
                        >
                          Chat WhatsApp
                        </a>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="p-4 rounded-2xl bg-slate-950/60 border border-slate-800/80 space-y-3">
                  <h4 class="font-bold text-white text-sm">Datos Fiscales & Facturación</h4>
                  <div class="space-y-2 text-slate-300">
                    <div>
                      <span class="text-slate-500 block text-[11px]">Razón Social</span>
                      <strong class="text-white">{{ currentDetail.rsocial || 'N/D' }}</strong>
                    </div>
                    <div>
                      <span class="text-slate-500 block text-[11px]">RFC</span>
                      <span class="px-2 py-0.5 rounded bg-slate-900 text-blue-300 border border-blue-500/30 font-mono font-bold">
                        {{ currentDetail.rfc || 'XAXX010101000' }}
                      </span>
                    </div>
                    <div>
                      <span class="text-slate-500 block text-[11px]">Dirección Registrada</span>
                      <span class="text-slate-300">
                        {{ [currentDetail.calle, currentDetail.next, currentDetail.col, currentDetail.ciudad, currentDetail.estado].filter(Boolean).join(', ') || 'Sin dirección fiscal registrada' }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Especificaciones / Notas -->
              <div v-if="currentDetail.especificacion" class="p-4 rounded-2xl bg-slate-950/60 border border-slate-800/80 space-y-2">
                <h4 class="font-bold text-white text-xs">Especificaciones / Requerimientos del Cliente</h4>
                <p class="text-slate-300 leading-relaxed whitespace-pre-wrap">{{ currentDetail.especificacion }}</p>
              </div>
            </div>

            <!-- TAB 2: DOMINIOS -->
            <div v-else-if="detailTab === 'dominios'" class="space-y-4 text-xs">
              <div v-if="currentDetail.dominios.length === 0" class="py-8 text-center text-slate-500">
                <i class="pi pi-globe text-3xl mb-2"></i>
                <p>No hay dominios web asociados a este cliente.</p>
              </div>

              <div v-else class="space-y-2.5">
                <div
                  v-for="dom in currentDetail.dominios"
                  :key="dom.id"
                  class="p-4 rounded-2xl bg-slate-950/60 border border-slate-800/80 flex items-center justify-between"
                >
                  <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 rounded-xl bg-blue-600/20 text-blue-400 flex items-center justify-center">
                      <i class="pi pi-globe"></i>
                    </div>
                    <div>
                      <a :href="`https://${dom.dominio}`" target="_blank" class="font-bold text-white hover:text-blue-400 text-sm">
                        {{ dom.dominio }}
                      </a>
                      <div class="text-[11px] text-slate-400">
                        Vencimiento: <strong>{{ dom.fecha_vencimiento || 'Sin fecha' }}</strong>
                      </div>
                    </div>
                  </div>

                  <div class="text-right">
                    <span
                      v-if="dom.dias_restantes !== null && dom.dias_restantes < 0"
                      class="px-2 py-0.5 rounded text-[10px] bg-red-950 text-red-300 border border-red-500/30 font-bold"
                    >
                      Venció hace {{ Math.abs(dom.dias_restantes) }} días
                    </span>
                    <span
                      v-else-if="dom.dias_restantes !== null && dom.dias_restantes <= 30"
                      class="px-2 py-0.5 rounded text-[10px] bg-amber-950 text-amber-300 border border-amber-500/30 font-bold"
                    >
                      Vence en {{ dom.dias_restantes }} días
                    </span>
                    <span
                      v-else
                      class="px-2 py-0.5 rounded text-[10px] bg-emerald-950 text-emerald-300 border border-emerald-500/30 font-bold"
                    >
                      Activo
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- TAB 3: HOSTING -->
            <div v-else-if="detailTab === 'hosting'" class="space-y-4 text-xs">
              <div v-if="currentDetail.hostings.length === 0" class="py-8 text-center text-slate-500">
                <i class="pi pi-server text-3xl mb-2"></i>
                <p>No hay planes de hosting asignados a este cliente.</p>
              </div>

              <div v-else class="space-y-2.5">
                <div
                  v-for="h in currentDetail.hostings"
                  :key="h.id"
                  class="p-4 rounded-2xl bg-slate-950/60 border border-slate-800/80 flex items-center justify-between"
                >
                  <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 rounded-xl bg-emerald-600/20 text-emerald-400 flex items-center justify-center">
                      <i class="pi pi-server"></i>
                    </div>
                    <div>
                      <h5 class="font-bold text-white text-sm">{{ h.nombre_plan }}</h5>
                      <p class="text-[11px] text-slate-400">{{ h.dominio }}</p>
                    </div>
                  </div>

                  <div class="text-right">
                    <span class="text-xs font-bold text-white block">{{ formatCurrency(h.precio) }}</span>
                    <span class="text-[10px] text-slate-400">Renovación: {{ h.fecha_vencimiento || 'N/D' }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- TAB 4: HISTORIAL DE PAGOS -->
            <div v-else-if="detailTab === 'pagos'" class="space-y-4 text-xs">
              <div v-if="currentDetail.pagos.length === 0" class="py-8 text-center text-slate-500">
                <i class="pi pi-credit-card text-3xl mb-2"></i>
                <p>No hay registros de pago para este cliente.</p>
              </div>

              <div v-else class="space-y-2.5">
                <div
                  v-for="pago in currentDetail.pagos"
                  :key="pago.id"
                  class="p-4 rounded-2xl bg-slate-950/60 border border-slate-800/80 flex items-center justify-between"
                >
                  <div>
                    <h5 class="font-bold text-white">{{ pago.concepto }}</h5>
                    <p class="text-[11px] text-slate-400">Vencimiento: {{ pago.fecha_vencimiento || 'N/D' }}</p>
                  </div>

                  <div class="text-right space-y-1">
                    <div class="font-bold text-white">{{ formatCurrency(pago.monto, pago.moneda) }}</div>
                    <span
                      :class="[
                        'px-2 py-0.5 rounded text-[10px] font-bold',
                        pago.estatus === 1 ? 'bg-emerald-950 text-emerald-300 border border-emerald-500/30' : 'bg-amber-950 text-amber-300 border border-amber-500/30'
                      ]"
                    >
                      {{ pago.estatus_texto }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>

    <!-- MODAL FORMULARIO NUEVO / EDITAR CLIENTE -->
    <div
      v-if="isFormOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-950/80 backdrop-blur-md"
    >
      <div class="w-full max-w-2xl bg-slate-900 border border-slate-800 rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
        <!-- Header Form -->
        <div class="p-6 bg-slate-950/60 border-b border-slate-800 flex items-center justify-between shrink-0">
          <h3 class="text-base font-bold text-white">
            {{ isEditing ? 'Editar Cliente' : 'Registrar Nuevo Cliente' }}
          </h3>
          <button
            @click="isFormOpen = false"
            class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white transition-colors"
          >
            <i class="pi pi-times text-sm"></i>
          </button>
        </div>

        <!-- Form Fields -->
        <form @submit.prevent="submitClientForm" class="p-6 overflow-y-auto flex-1 space-y-4 text-xs">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Empresa -->
            <div>
              <label class="block text-slate-300 font-semibold mb-1">Nombre de la Empresa / Negocio *</label>
              <input
                v-model="formData.empresa"
                type="text"
                required
                placeholder="Ej. Acme Corp"
                class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-white focus:outline-none focus:border-blue-500"
              />
            </div>

            <!-- Contacto -->
            <div>
              <label class="block text-slate-300 font-semibold mb-1">Persona de Contacto *</label>
              <input
                v-model="formData.nombre_contacto"
                type="text"
                required
                placeholder="Ej. Juan Pérez"
                class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-white focus:outline-none focus:border-blue-500"
              />
            </div>

            <!-- Correo -->
            <div>
              <label class="block text-slate-300 font-semibold mb-1">Correo Electrónico</label>
              <input
                v-model="formData.correo"
                type="email"
                placeholder="contacto@empresa.com"
                class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-white focus:outline-none focus:border-blue-500"
              />
            </div>

            <!-- Teléfono -->
            <div>
              <label class="block text-slate-300 font-semibold mb-1">Teléfono / WhatsApp</label>
              <input
                v-model="formData.telefono"
                type="text"
                placeholder="+52 477 123 4567"
                class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-white focus:outline-none focus:border-blue-500"
              />
            </div>

            <!-- RFC -->
            <div>
              <label class="block text-slate-300 font-semibold mb-1">RFC</label>
              <input
                v-model="formData.rfc"
                type="text"
                placeholder="XAXX010101000"
                class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-white uppercase focus:outline-none focus:border-blue-500"
              />
            </div>

            <!-- Razón Social -->
            <div>
              <label class="block text-slate-300 font-semibold mb-1">Razón Social</label>
              <input
                v-model="formData.rsocial"
                type="text"
                placeholder="Razón Social SA de CV"
                class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-white focus:outline-none focus:border-blue-500"
              />
            </div>
          </div>

          <!-- Dirección -->
          <div class="pt-2 border-t border-slate-800 space-y-3">
            <h5 class="font-bold text-slate-300">Dirección y Ubicación</h5>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div class="sm:col-span-2">
                <input
                  v-model="formData.calle"
                  type="text"
                  placeholder="Calle y Número"
                  class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-white focus:outline-none focus:border-blue-500"
                />
              </div>
              <div>
                <input
                  v-model="formData.col"
                  type="text"
                  placeholder="Colonia"
                  class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-white focus:outline-none focus:border-blue-500"
                />
              </div>
              <div>
                <input
                  v-model="formData.cp"
                  type="text"
                  placeholder="Código Postal"
                  class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-white focus:outline-none focus:border-blue-500"
                />
              </div>
              <div>
                <input
                  v-model="formData.ciudad"
                  type="text"
                  placeholder="Ciudad"
                  class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-white focus:outline-none focus:border-blue-500"
                />
              </div>
              <div>
                <input
                  v-model="formData.estado"
                  type="text"
                  placeholder="Estado"
                  class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-white focus:outline-none focus:border-blue-500"
                />
              </div>
            </div>
          </div>

          <!-- Especificaciones -->
          <div class="pt-2 border-t border-slate-800">
            <label class="block text-slate-300 font-semibold mb-1">Notas o Requerimientos</label>
            <textarea
              v-model="formData.especificacion"
              rows="3"
              placeholder="Detalles sobre los servicios contratados, notas internas, etc."
              class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-white focus:outline-none focus:border-blue-500 resize-none"
            ></textarea>
          </div>

          <!-- Botones de Acción -->
          <div class="pt-4 border-t border-slate-800 flex items-center justify-end space-x-3 shrink-0">
            <button
              type="button"
              @click="isFormOpen = false"
              class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition-colors"
            >
              Cancelar
            </button>
            <button
              type="submit"
              :disabled="isSaving"
              class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold shadow-lg shadow-blue-500/20 flex items-center space-x-2 transition-all disabled:opacity-50"
            >
              <i v-if="isSaving" class="pi pi-spin pi-spinner text-xs"></i>
              <span>{{ isEditing ? 'Guardar Cambios' : 'Registrar Cliente' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(10px);
}
</style>
