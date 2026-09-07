<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { dashboardApi, type DashboardResponse, type PagoItem } from '@/api/dashboard'

import AppSidebar from '@/components/layout/AppSidebar.vue'

const isMobileSidebarOpen = ref(false)
const router = useRouter()
const authStore = useAuthStore()

const user = computed(() => authStore.user)
const isClient = computed(() => authStore.isCliente)

// Estados de control
const currentSistema = ref<'conlineweb' | 'hostingpro'>('conlineweb')
const isLoading = ref(true)
const dashboardData = ref<DashboardResponse | null>(null)
const errorMsg = ref<string | null>(null)

// Filtros y Búsqueda
const searchQuery = ref('')
const activeFilter = ref<'todos' | 'vencidos' | 'prox7' | 'dominios' | 'hosting' | 'manuales'>('todos')
const toastMessage = ref<string | null>(null)

async function loadDashboardData() {
  isLoading.value = true
  errorMsg.value = null
  try {
    const data = await dashboardApi.getStats(currentSistema.value)
    dashboardData.value = data
  } catch (err: any) {
    errorMsg.value = err.response?.data?.detail || 'Error al conectar con la base de datos'
  } finally {
    isLoading.value = false
  }
}

watch(currentSistema, () => {
  loadDashboardData()
})

onMounted(() => {
  loadDashboardData()
})

function handleLogout() {
  authStore.logout()
  router.push('/login')
}

// Formateadores
function formatCurrency(amount: number, currency: string = 'MXN'): string {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: currency || 'MXN',
    minimumFractionDigits: 2
  }).format(amount)
}

function formatDate(dateStr?: string): string {
  if (!dateStr || dateStr === '0000-00-00') return 'Sin fecha'
  try {
    const [year, month, day] = dateStr.split('-')
    return `${day}/${month}/${year}`
  } catch {
    return dateStr
  }
}

// Filtro Reactivo de Pagos
const filteredPagos = computed(() => {
  if (!dashboardData.value?.pagos_pendientes) return []
  let items = dashboardData.value.pagos_pendientes

  // Filtro por Tab
  if (activeFilter.value === 'vencidos') {
    items = items.filter((p) => p.estado_vencimiento === 'vencido')
  } else if (activeFilter.value === 'prox7') {
    items = items.filter((p) => p.estado_vencimiento === 'prox7')
  } else if (activeFilter.value === 'dominios') {
    items = items.filter((p) => p.tipo_servicio === 2)
  } else if (activeFilter.value === 'hosting') {
    items = items.filter((p) => p.tipo_servicio === 1)
  } else if (activeFilter.value === 'manuales') {
    items = items.filter((p) => p.manual === 1)
  }

  // Filtro por Búsqueda
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase()
    items = items.filter(
      (p) =>
        p.cliente_nombre?.toLowerCase().includes(q) ||
        p.concepto?.toLowerCase().includes(q) ||
        p.nombre_servicio?.toLowerCase().includes(q) ||
        p.cliente_correo?.toLowerCase().includes(q) ||
        p.cliente_telefono?.toLowerCase().includes(q)
    )
  }

  return items
})

// Acciones Rápidas
function getWhatsAppUrl(pago: PagoItem): string {
  let tel = (pago.cliente_telefono || '').replace(/[^\d+]/g, '')
  if (tel.startsWith('0')) tel = '52' + tel.substring(1)
  if (!tel.startsWith('+') && tel.length === 10) tel = '52' + tel
  tel = tel.replace('+', '')

  const msg = `Hola ${pago.cliente_nombre}, te contactamos de NexusBot respecto al servicio ${pago.nombre_servicio || pago.concepto} con monto pendiente de ${formatCurrency(pago.monto, pago.currency)}. ¿Deseas que te apoyemos con el enlace de pago?`
  return `https://wa.me/${tel}?text=${encodeURIComponent(msg)}`
}

function copyPaymentInfo(pago: PagoItem) {
  const text = `Cliente: ${pago.cliente_nombre}\nServicio: ${pago.nombre_servicio || pago.concepto}\nMonto: ${formatCurrency(pago.monto, pago.currency)}\nFecha Límite: ${formatDate(pago.fecha_limite)}`
  navigator.clipboard.writeText(text)
  showToast('Datos de pago copiados al portapapeles')
}

function showToast(msg: string) {
  toastMessage.value = msg
  setTimeout(() => {
    toastMessage.value = null
  }, 3000)
}
</script>

<template>
  <div class="min-h-screen bg-[#0F172A] text-slate-100 selection:bg-blue-600 selection:text-white flex overflow-x-hidden">
    <!-- MENÚ LATERAL (SIDEBAR MIGRADO) -->
    <AppSidebar
      :is-mobile-open="isMobileSidebarOpen"
      @close-mobile="isMobileSidebarOpen = false"
    />

    <!-- CONTENEDOR PRINCIPAL CON SCROLL -->
    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto pb-16">
      <!-- BARRA DE NAVEGACIÓN SUPERIOR -->
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
            <div class="hidden sm:flex items-center space-x-2">
              <span class="text-sm font-bold text-white">Panel Principal</span>
              <span class="text-slate-600">•</span>
              <span class="text-xs text-blue-400 font-semibold uppercase tracking-wider">{{ currentSistema }}</span>
            </div>
          </div>

          <!-- Switcher de Sistema y Perfil -->
          <div class="flex items-center space-x-3 sm:space-x-4">
            <!-- Selector ConlineWeb / HostingPro (Solo Admins) -->
            <div v-if="!isClient" class="flex p-1 rounded-xl bg-slate-950/80 border border-slate-800">
              <button
                @click="currentSistema = 'conlineweb'"
                :class="[
                  'px-3 py-1.5 rounded-lg text-xs font-semibold transition-all duration-200',
                  currentSistema === 'conlineweb'
                    ? 'bg-blue-600 text-white shadow-md shadow-blue-600/30'
                    : 'text-slate-400 hover:text-white'
                ]"
              >
                ConlineWeb
              </button>
              <button
                @click="currentSistema = 'hostingpro'"
                :class="[
                  'px-3 py-1.5 rounded-lg text-xs font-semibold transition-all duration-200',
                  currentSistema === 'hostingpro'
                    ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30'
                    : 'text-slate-400 hover:text-white'
                ]"
              >
                HostingPro
              </button>
            </div>
            <button
              @click="currentSistema = 'hostingpro'"
              :class="[
                'px-3 py-1.5 rounded-lg text-xs font-semibold transition-all duration-200',
                currentSistema === 'hostingpro'
                  ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30'
                  : 'text-slate-400 hover:text-white'
              ]"
            >
              HostingPro
            </button>
          </div>

          <!-- Usuario y Logout -->
          <div class="flex items-center space-x-3 pl-2 sm:pl-3 border-l border-slate-800">
            <div class="hidden md:flex flex-col text-right">
              <span class="text-xs font-semibold text-white">{{ user?.nombre || user?.usuario }}</span>
              <span class="text-[10px] text-blue-400 font-medium">{{ user?.rol }}</span>
            </div>
            <button
              @click="handleLogout"
              class="p-2 rounded-xl bg-slate-800/80 hover:bg-red-500/20 text-slate-400 hover:text-red-400 border border-slate-700/60 transition-colors"
              title="Cerrar sesión"
            >
              <i class="pi pi-sign-out text-sm"></i>
            </button>
          </div>
        </div>
      </header>

    <!-- NOTIFICACIÓN TOAST -->
    <transition name="fade">
      <div
        v-if="toastMessage"
        class="fixed bottom-6 right-6 z-50 px-4 py-3 rounded-xl bg-slate-900 border border-emerald-500/40 text-emerald-300 text-xs font-semibold shadow-2xl flex items-center space-x-2"
      >
        <i class="pi pi-check-circle text-emerald-400"></i>
        <span>{{ toastMessage }}</span>
      </div>
    </transition>

    <!-- CONTENIDO PRINCIPAL -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 space-y-8">
      <!-- HEADER DE BIENVENIDA / HERO -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-blue-950/60 via-slate-900 to-indigo-950/60 border border-slate-800/80 p-6 sm:p-8 shadow-2xl">
        <div class="absolute -right-10 -top-10 w-72 h-72 bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 relative z-10">
          <div class="space-y-1.5">
            <div class="flex items-center space-x-2">
              <span class="text-xs font-semibold text-blue-400 uppercase tracking-wider">Panel Principal</span>
              <span class="text-slate-600">•</span>
              <span class="text-xs text-slate-400 font-medium capitalize">{{ currentSistema }}</span>
            </div>
            <h2 class="text-2xl sm:text-3xl font-bold text-white tracking-tight">
              Hola, {{ user?.nombre || user?.usuario }} 👋
            </h2>
            <p class="text-slate-400 text-xs sm:text-sm">
              Resumen ejecutivo de clientes, cobranza activa y vencimientos de servicios.
            </p>
          </div>

          <!-- Botones de Acción Rápida -->
          <div class="flex items-center space-x-2">
            <button
              @click="loadDashboardData"
              class="px-4 py-2 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-200 text-xs font-semibold border border-slate-700/80 transition-all flex items-center space-x-1.5"
            >
              <i class="pi pi-refresh text-xs" :class="{ 'pi-spin': isLoading }"></i>
              <span>Actualizar</span>
            </button>
          </div>
        </div>
      </div>

      <!-- ALERTA DE ERROR SI FALLA -->
      <div v-if="errorMsg" class="p-4 rounded-2xl bg-red-500/10 border border-red-500/30 text-red-300 text-sm flex items-center justify-between">
        <div class="flex items-center space-x-2">
          <i class="pi pi-exclamation-circle text-red-400"></i>
          <span>{{ errorMsg }}</span>
        </div>
        <button @click="loadDashboardData" class="underline font-semibold hover:text-white">Reintentar</button>
      </div>

      <!-- SKELETON LOADING -->
      <div v-if="isLoading && !dashboardData" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div v-for="i in 4" :key="i" class="h-32 rounded-2xl bg-slate-800/40 animate-pulse border border-slate-800"></div>
      </div>

      <!-- KPIS EMPRESARIALES EN VIVO -->
      <div v-else-if="dashboardData" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <!-- Tarjeta 1: Pendientes de Cobro -->
        <div class="p-6 rounded-2xl bg-slate-900/60 border border-slate-800 hover:border-blue-500/40 transition-all duration-200 relative overflow-hidden group">
          <div class="absolute top-0 left-0 h-1 w-full bg-gradient-to-r from-amber-500 to-rose-500"></div>
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Pendiente de Cobro</span>
            <div class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center">
              <i class="pi pi-clock text-sm"></i>
            </div>
          </div>
          <div class="mt-4">
            <div class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">
              {{ formatCurrency(dashboardData.kpis.total_pendientes_monto) }}
            </div>
            <div class="flex items-center space-x-2 mt-1.5 text-xs">
              <span class="font-semibold text-amber-400">{{ dashboardData.kpis.total_pendientes_count }} facturas</span>
              <span class="text-slate-500">•</span>
              <span v-if="dashboardData.kpis.vencidos_count > 0" class="text-rose-400 font-medium">
                {{ dashboardData.kpis.vencidos_count }} vencidas
              </span>
              <span v-else class="text-slate-500">Al corriente</span>
            </div>
          </div>
        </div>

        <!-- Tarjeta 2: Cobrado en el Mes -->
        <div class="p-6 rounded-2xl bg-slate-900/60 border border-slate-800 hover:border-emerald-500/40 transition-all duration-200 relative overflow-hidden group">
          <div class="absolute top-0 left-0 h-1 w-full bg-gradient-to-r from-emerald-500 to-teal-500"></div>
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Cobrado en el Mes</span>
            <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
              <i class="pi pi-check-circle text-sm"></i>
            </div>
          </div>
          <div class="mt-4">
            <div class="text-2xl sm:text-3xl font-extrabold text-emerald-400 tracking-tight">
              {{ formatCurrency(dashboardData.kpis.total_pagados_mes_monto) }}
            </div>
            <div class="flex items-center space-x-2 mt-1.5 text-xs text-slate-400">
              <span class="font-semibold text-emerald-400">{{ dashboardData.kpis.total_pagados_mes_count }} acreditados</span>
              <span class="text-slate-500">•</span>
              <span>Tasa: {{ dashboardData.kpis.tasa_cumplimiento }}%</span>
            </div>
          </div>
        </div>

        <!-- Tarjeta 3: Clientes Registrados -->
        <div class="p-6 rounded-2xl bg-slate-900/60 border border-slate-800 hover:border-blue-500/40 transition-all duration-200 relative overflow-hidden group">
          <div class="absolute top-0 left-0 h-1 w-full bg-gradient-to-r from-blue-500 to-indigo-500"></div>
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Clientes Activos</span>
            <div class="w-8 h-8 rounded-lg bg-blue-500/10 text-blue-400 flex items-center justify-center">
              <i class="pi pi-users text-sm"></i>
            </div>
          </div>
          <div class="mt-4">
            <div class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">
              {{ dashboardData.kpis.total_clientes }}
            </div>
            <div class="text-xs text-slate-400 mt-1.5">
              Empresas y contactos en CRM
            </div>
          </div>
        </div>

        <!-- Tarjeta 4: Infraestructura Activa (Dominios & Hosting) -->
        <div class="p-6 rounded-2xl bg-slate-900/60 border border-slate-800 hover:border-cyan-500/40 transition-all duration-200 relative overflow-hidden group">
          <div class="absolute top-0 left-0 h-1 w-full bg-gradient-to-r from-cyan-500 to-blue-500"></div>
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Infraestructura</span>
            <div class="w-8 h-8 rounded-lg bg-cyan-500/10 text-cyan-400 flex items-center justify-center">
              <i class="pi pi-server text-sm"></i>
            </div>
          </div>
          <div class="mt-4 flex items-baseline space-x-4">
            <div>
              <div class="text-2xl font-bold text-white">{{ dashboardData.kpis.total_dominios_activos }}</div>
              <div class="text-[11px] text-slate-400 font-medium">Dominios</div>
            </div>
            <div class="border-l border-slate-800 pl-4">
              <div class="text-2xl font-bold text-cyan-400">{{ dashboardData.kpis.total_hosting_activos }}</div>
              <div class="text-[11px] text-slate-400 font-medium">Hostings</div>
            </div>
          </div>
        </div>
      </div>

      <!-- SECCIÓN PRINCIPAL: TABLA DE PAGOS Y SERVICIOS PENDIENTES -->
      <div class="rounded-3xl bg-slate-900/70 border border-slate-800/80 overflow-hidden shadow-2xl">
        <!-- Cabecera de la Tabla + Búsqueda y Tabs -->
        <div class="p-6 border-b border-slate-800/80 space-y-5">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
              <h3 class="text-lg font-bold text-white tracking-tight flex items-center space-x-2">
                <span>Servicios y Pagos Pendientes</span>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-500/20 text-blue-400 border border-blue-500/30">
                  {{ filteredPagos.length }}
                </span>
              </h3>
              <p class="text-xs text-slate-400 mt-0.5">Control de renovaciones con vencimiento y alertas de contacto directo</p>
            </div>

            <!-- Buscador en Tiempo Real -->
            <div class="w-full md:w-80 relative">
              <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <i class="pi pi-search text-xs"></i>
              </span>
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Buscar cliente, dominio, correo..."
                class="w-full pl-9 pr-4 py-2 bg-slate-950/80 text-white placeholder-slate-500 rounded-xl border border-slate-700/80 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-xs outline-none transition-all"
              />
            </div>
          </div>

          <!-- Pestañas de Filtro Rápido -->
          <div class="flex items-center space-x-2 overflow-x-auto pb-1 text-xs">
            <button
              @click="activeFilter = 'todos'"
              :class="[
                'px-3.5 py-1.5 rounded-xl font-medium transition-all whitespace-nowrap',
                activeFilter === 'todos'
                  ? 'bg-blue-600 text-white shadow-md shadow-blue-600/30'
                  : 'bg-slate-800/70 text-slate-400 hover:text-white hover:bg-slate-800'
              ]"
            >
              Todos ({{ dashboardData?.pagos_pendientes?.length || 0 }})
            </button>
            <button
              @click="activeFilter = 'vencidos'"
              :class="[
                'px-3.5 py-1.5 rounded-xl font-medium transition-all whitespace-nowrap flex items-center space-x-1.5',
                activeFilter === 'vencidos'
                  ? 'bg-rose-600 text-white shadow-md shadow-rose-600/30'
                  : 'bg-slate-800/70 text-slate-400 hover:text-white hover:bg-slate-800'
              ]"
            >
              <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
              <span>Vencidos ({{ dashboardData?.kpis?.vencidos_count || 0 }})</span>
            </button>
            <button
              @click="activeFilter = 'prox7'"
              :class="[
                'px-3.5 py-1.5 rounded-xl font-medium transition-all whitespace-nowrap flex items-center space-x-1.5',
                activeFilter === 'prox7'
                  ? 'bg-amber-600 text-white shadow-md shadow-amber-600/30'
                  : 'bg-slate-800/70 text-slate-400 hover:text-white hover:bg-slate-800'
              ]"
            >
              <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
              <span>Próximos 7 días ({{ dashboardData?.kpis?.prox_7_dias_count || 0 }})</span>
            </button>
            <button
              @click="activeFilter = 'dominios'"
              :class="[
                'px-3.5 py-1.5 rounded-xl font-medium transition-all whitespace-nowrap',
                activeFilter === 'dominios'
                  ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30'
                  : 'bg-slate-800/70 text-slate-400 hover:text-white hover:bg-slate-800'
              ]"
            >
              Dominios
            </button>
            <button
              @click="activeFilter = 'hosting'"
              :class="[
                'px-3.5 py-1.5 rounded-xl font-medium transition-all whitespace-nowrap',
                activeFilter === 'hosting'
                  ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30'
                  : 'bg-slate-800/70 text-slate-400 hover:text-white hover:bg-slate-800'
              ]"
            >
              Hostings
            </button>
          </div>
        </div>

        <!-- Tabla Responsiva de Pagos -->
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-slate-950/60 text-slate-400 uppercase tracking-wider font-semibold border-b border-slate-800">
              <tr>
                <th class="py-3.5 px-5">Estado</th>
                <th class="py-3.5 px-5">Cliente</th>
                <th class="py-3.5 px-5">Servicio / Concepto</th>
                <th class="py-3.5 px-5">Monto</th>
                <th class="py-3.5 px-5">Fecha Límite</th>
                <th class="py-3.5 px-5 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
              <tr
                v-for="pago in filteredPagos"
                :key="pago.id"
                class="hover:bg-slate-800/40 transition-colors group"
              >
                <!-- Estado de Vencimiento -->
                <td class="py-4 px-5 whitespace-nowrap">
                  <span
                    v-if="pago.estado_vencimiento === 'vencido'"
                    class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/30"
                  >
                    <span class="w-1.5 h-1.5 rounded-full bg-rose-400 mr-1.5 animate-pulse"></span>
                    Vencido ({{ Math.abs(pago.dias_restantes || 0) }}d)
                  </span>
                  <span
                    v-else-if="pago.estado_vencimiento === 'prox7'"
                    class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/30"
                  >
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400 mr-1.5"></span>
                    En {{ pago.dias_restantes }} días
                  </span>
                  <span
                    v-else-if="pago.estado_vencimiento === 'prox30'"
                    class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-medium bg-blue-500/10 text-blue-400 border border-blue-500/20"
                  >
                    Próx. 30d
                  </span>
                  <span
                    v-else
                    class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-medium bg-slate-800 text-slate-400 border border-slate-700"
                  >
                    Sin fecha
                  </span>
                </td>

                <!-- Cliente -->
                <td class="py-4 px-5">
                  <div class="font-bold text-white text-sm">{{ pago.cliente_nombre }}</div>
                  <div class="text-[11px] text-slate-400 flex items-center space-x-2 mt-0.5">
                    <span v-if="pago.cliente_correo">{{ pago.cliente_correo }}</span>
                    <span v-if="pago.cliente_telefono" class="text-slate-500">• {{ pago.cliente_telefono }}</span>
                  </div>
                </td>

                <!-- Servicio / Concepto -->
                <td class="py-4 px-5">
                  <div class="flex items-center space-x-2">
                    <span
                      :class="[
                        'px-2 py-0.5 rounded text-[10px] font-semibold uppercase',
                        pago.tipo_servicio === 2 ? 'bg-indigo-500/20 text-indigo-300' :
                        pago.tipo_servicio === 1 ? 'bg-cyan-500/20 text-cyan-300' : 'bg-slate-800 text-slate-300'
                      ]"
                    >
                      {{ pago.tipo_servicio_label }}
                    </span>
                    <span class="font-semibold text-slate-200">{{ pago.nombre_servicio || pago.concepto }}</span>
                  </div>
                  <div class="text-[11px] text-slate-400 mt-1 line-clamp-1">{{ pago.concepto }}</div>
                </td>

                <!-- Monto -->
                <td class="py-4 px-5 whitespace-nowrap">
                  <div class="text-sm font-extrabold text-white">
                    {{ formatCurrency(pago.monto, pago.currency) }}
                  </div>
                  <div class="text-[10px] text-slate-500 uppercase">{{ pago.currency }}</div>
                </td>

                <!-- Fecha Límite -->
                <td class="py-4 px-5 whitespace-nowrap">
                  <div class="font-medium text-slate-300">{{ formatDate(pago.fecha_limite) }}</div>
                  <div v-if="pago.dias_restantes !== null && pago.dias_restantes !== undefined" class="text-[10px] text-slate-500">
                    {{ (pago.dias_restantes ?? 0) < 0 ? 'Expiró' : 'Faltan ' + pago.dias_restantes + ' días' }}
                  </div>
                </td>

                <!-- Acciones Rápidas -->
                <td class="py-4 px-5 text-right whitespace-nowrap">
                  <div class="flex items-center justify-end space-x-1.5">
                    <!-- WhatsApp Directo -->
                    <a
                      v-if="pago.cliente_telefono"
                      :href="getWhatsAppUrl(pago)"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="p-2 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/25 text-emerald-400 border border-emerald-500/20 transition-all"
                      title="Enviar recordatorio por WhatsApp"
                    >
                      <i class="pi pi-whatsapp text-xs"></i>
                    </a>

                    <!-- Correo -->
                    <a
                      v-if="pago.cliente_correo"
                      :href="`mailto:${pago.cliente_correo}?subject=Aviso de pago pendiente: ${encodeURIComponent(pago.nombre_servicio || pago.concepto)}`"
                      class="p-2 rounded-xl bg-blue-500/10 hover:bg-blue-500/25 text-blue-400 border border-blue-500/20 transition-all"
                      title="Enviar correo"
                    >
                      <i class="pi pi-envelope text-xs"></i>
                    </a>

                    <!-- Copiar Resumen -->
                    <button
                      @click="copyPaymentInfo(pago)"
                      class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700/80 transition-all"
                      title="Copiar detalles"
                    >
                      <i class="pi pi-copy text-xs"></i>
                    </button>
                  </div>
                </td>
              </tr>

              <!-- Fila vacía si no hay resultados -->
              <tr v-if="filteredPagos.length === 0">
                <td colspan="6" class="py-12 text-center text-slate-500">
                  <i class="pi pi-inbox text-3xl text-slate-600 mb-2 block"></i>
                  <span>No se encontraron pagos pendientes con los filtros aplicados.</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- SECCIÓN INFERIOR: TENDENCIA MENSUAL Y DISTRIBUCIÓN -->
      <div v-if="dashboardData" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Tendencias Últimos 6 Meses -->
        <div class="lg:col-span-2 p-6 rounded-3xl bg-slate-900/70 border border-slate-800/80 space-y-4">
          <div class="flex items-center justify-between">
            <div>
              <h4 class="text-sm font-bold text-white tracking-tight">Historial de Cobranza Mensual</h4>
              <p class="text-xs text-slate-400">Comparativa de montos pendientes vs cobrados (últimos 6 meses)</p>
            </div>
            <span class="text-xs text-blue-400 font-semibold">6 Meses</span>
          </div>

          <div class="space-y-3 pt-2">
            <div
              v-for="trend in dashboardData.monthly_trend"
              :key="trend.mes"
              class="space-y-1.5 text-xs"
            >
              <div class="flex justify-between font-medium">
                <span class="text-slate-300">{{ trend.mes }}</span>
                <span class="text-slate-400">
                  Cobrado: <strong class="text-emerald-400">{{ formatCurrency(trend.pagados) }}</strong> •
                  Pendiente: <strong class="text-amber-400">{{ formatCurrency(trend.pendientes) }}</strong>
                </span>
              </div>
              <!-- Barra comparativa -->
              <div class="h-2 w-full bg-slate-950 rounded-full overflow-hidden flex">
                <div
                  class="bg-emerald-500 transition-all duration-500"
                  :style="{ width: `${trend.pagados + trend.pendientes > 0 ? (trend.pagados / (trend.pagados + trend.pendientes)) * 100 : 0}%` }"
                ></div>
                <div
                  class="bg-amber-500/70 transition-all duration-500"
                  :style="{ width: `${trend.pagados + trend.pendientes > 0 ? (trend.pendientes / (trend.pagados + trend.pendientes)) * 100 : 0}%` }"
                ></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Distribución de Servicios -->
        <div class="p-6 rounded-3xl bg-slate-900/70 border border-slate-800/80 space-y-4">
          <div>
            <h4 class="text-sm font-bold text-white tracking-tight">Distribución de Ingresos</h4>
            <p class="text-xs text-slate-400">Por tipo de infraestructura activa</p>
          </div>

          <div class="space-y-4 pt-3 text-xs">
            <div class="p-3.5 rounded-2xl bg-slate-950/60 border border-slate-800/60 flex items-center justify-between">
              <div class="flex items-center space-x-3">
                <div class="w-3 h-3 rounded-full bg-indigo-500"></div>
                <span class="text-slate-300 font-medium">Dominios Web</span>
              </div>
              <span class="font-bold text-white">{{ formatCurrency(dashboardData.distribution.dominios) }}</span>
            </div>

            <div class="p-3.5 rounded-2xl bg-slate-950/60 border border-slate-800/60 flex items-center justify-between">
              <div class="flex items-center space-x-3">
                <div class="w-3 h-3 rounded-full bg-cyan-500"></div>
                <span class="text-slate-300 font-medium">Planes de Hosting</span>
              </div>
              <span class="font-bold text-white">{{ formatCurrency(dashboardData.distribution.hosting) }}</span>
            </div>

            <div class="p-3.5 rounded-2xl bg-slate-950/60 border border-slate-800/60 flex items-center justify-between">
              <div class="flex items-center space-x-3">
                <div class="w-3 h-3 rounded-full bg-amber-500"></div>
                <span class="text-slate-300 font-medium">Servicios Manuales</span>
              </div>
              <span class="font-bold text-white">{{ formatCurrency(dashboardData.distribution.servicios) }}</span>
            </div>
          </div>
        </div>
      </div>
    </main>
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
