<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import AppSidebar from '@/components/layout/AppSidebar.vue'
import PortalHeroGreeting from '@/components/portal/PortalHeroGreeting.vue'
import PortalAlertBanner from '@/components/portal/PortalAlertBanner.vue'
import PortalKpis from '@/components/portal/PortalKpis.vue'
import PortalQuickGrid from '@/components/portal/PortalQuickGrid.vue'
import PortalRecentActivity from '@/components/portal/PortalRecentActivity.vue'
import PortalHelpSection from '@/components/portal/PortalHelpSection.vue'
import SolicitudFormModal from '@/components/solicitudes/SolicitudFormModal.vue'
import AppToast from '@/components/common/AppToast.vue'
import { useAuthStore } from '@/stores/auth'
import { portalApi, type PortalInicioResponse } from '@/api/portal'
import { solicitudesApi, type SolicitudCreatePayload } from '@/api/solicitudes'

const authStore = useAuthStore()
const router = useRouter()
const isMobileOpen = ref(false)
const loading = ref(true)
const savingTicket = ref(false)
const isCreateTicketModalOpen = ref(false)

const portalData = reactive<PortalInicioResponse>({
  cliente_id: 0,
  cliente_nombre: '',
  cliente_empresa: '',
  cliente_correo: '',
  saludo: 'Hola',
  total_sitios: 0,
  total_pendientes: 0,
  monto_total_pendiente: 0,
  total_tickets_abiertos: 0,
  total_hostings: 0,
  total_dominios: 0,
  doc_url: 'https://conlineweb.com',
  sitios_recientes: [],
  pagos_pendientes_recientes: [],
  tickets_recientes: []
})

// Toast
const toast = reactive({
  visible: false,
  message: '',
  type: 'success' as 'success' | 'error' | 'info'
})

function showToast(msg: string, type: 'success' | 'error' | 'info' = 'success') {
  toast.message = msg
  toast.type = type
  toast.visible = true
}

async function fetchPortalData() {
  loading.value = true
  try {
    const data = await portalApi.getInicio()
    Object.assign(portalData, data)
  } catch (err: any) {
    showToast('Error al cargar la información del portal.', 'error')
  } finally {
    loading.value = false
  }
}

function handleOpenTicket() {
  isCreateTicketModalOpen.value = true
}

async function handleSaveTicket(data: SolicitudCreatePayload) {
  savingTicket.value = true
  try {
    // Asignar el ID de cliente actual si no se incluyó
    if (!data.id_cliente && portalData.cliente_id) {
      data.id_cliente = portalData.cliente_id
    }
    await solicitudesApi.create(data)
    isCreateTicketModalOpen.value = false
    showToast('Ticket creado exitosamente. Nuestro equipo técnico lo atenderá a la brevedad.')
    await fetchPortalData()
  } catch (err: any) {
    showToast('Error al crear el ticket. Inténtalo de nuevo.', 'error')
  } finally {
    savingTicket.value = false
  }
}

function handleLogout() {
  authStore.logout()
  router.push('/login')
}

onMounted(() => {
  fetchPortalData()
})
</script>

<template>
  <div class="flex h-screen bg-slate-950 text-slate-100 overflow-hidden font-sans">
    <!-- Sidebar -->
    <AppSidebar
      :is-mobile-open="isMobileOpen"
      @close-mobile="isMobileOpen = false"
    />

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden bg-slate-950">
      <!-- Topbar Header -->
      <header class="h-16 border-b border-slate-800 bg-slate-900/60 backdrop-blur-md px-4 md:px-8 flex items-center justify-between z-10 shrink-0">
        <div class="flex items-center gap-3">
          <button
            @click="isMobileOpen = true"
            class="md:hidden p-2 rounded-xl bg-slate-800 text-slate-300 hover:text-white"
          >
            <i class="pi pi-bars text-lg"></i>
          </button>
          <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-widest">Portal de Clientes</span>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <button
            @click="fetchPortalData"
            class="p-2 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-300 transition-colors"
            title="Actualizar datos"
          >
            <i class="pi pi-refresh text-xs" :class="{ 'animate-spin': loading }"></i>
          </button>

          <div class="h-5 w-px bg-slate-800"></div>

          <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-xl bg-blue-600/20 text-blue-400 border border-blue-500/30 flex items-center justify-center text-xs font-bold">
              {{ portalData.cliente_nombre ? portalData.cliente_nombre.charAt(0).toUpperCase() : 'C' }}
            </div>
            <div class="hidden sm:block text-left">
              <p class="text-xs font-bold text-white leading-none">{{ portalData.cliente_nombre || 'Cliente' }}</p>
              <p class="text-[10px] text-slate-400 mt-0.5 leading-none">{{ portalData.cliente_empresa || 'Cuenta personal' }}</p>
            </div>
            <button
              @click="handleLogout"
              class="p-2 rounded-xl text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 transition-colors"
              title="Cerrar sesión"
            >
              <i class="pi pi-sign-out text-sm"></i>
            </button>
          </div>
        </div>
      </header>

      <!-- Scrollable Main View Container -->
      <main class="flex-1 overflow-y-auto p-4 md:p-8 space-y-6 md:space-y-8 max-w-7xl mx-auto w-full">
        <!-- Skeleton Loading State -->
        <div v-if="loading" class="space-y-6 animate-pulse">
          <div class="h-32 rounded-2xl bg-slate-900 border border-slate-800"></div>
          <div class="h-16 rounded-2xl bg-slate-900 border border-slate-800"></div>
          <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div v-for="i in 4" :key="i" class="h-28 rounded-2xl bg-slate-900 border border-slate-800"></div>
          </div>
          <div class="h-64 rounded-2xl bg-slate-900 border border-slate-800"></div>
        </div>

        <template v-else>
          <!-- 1. Hero Greeting Banner -->
          <PortalHeroGreeting
            :saludo="portalData.saludo"
            :nombre="portalData.cliente_nombre"
            :empresa="portalData.cliente_empresa"
            :total-pendientes="portalData.total_pendientes"
            @open-ticket="handleOpenTicket"
          />

          <!-- 2. Alert Banner (Urgent or OK) -->
          <PortalAlertBanner
            :total-pendientes="portalData.total_pendientes"
            :monto-pendiente="portalData.monto_total_pendiente"
          />

          <!-- 3. Key Metrics / KPIs -->
          <PortalKpis
            :total-sitios="portalData.total_sitios"
            :total-pendientes="portalData.total_pendientes"
            :total-tickets-abiertos="portalData.total_tickets_abiertos"
            :total-hostings="portalData.total_hostings"
            :total-dominios="portalData.total_dominios"
          />

          <!-- 4. Quick Access Grid (6 Cards) -->
          <PortalQuickGrid
            :total-sitios="portalData.total_sitios"
            :total-pendientes="portalData.total_pendientes"
            :total-tickets-abiertos="portalData.total_tickets_abiertos"
          />

          <!-- 5. Recent Activity (Websites & Tickets) -->
          <PortalRecentActivity
            :sitios="portalData.sitios_recientes"
            :tickets="portalData.tickets_recientes"
            @open-ticket="handleOpenTicket"
          />

          <!-- 6. Help & Documentation Section -->
          <PortalHelpSection
            :doc-url="portalData.doc_url"
            @open-ticket="handleOpenTicket"
          />
        </template>
      </main>
    </div>

    <!-- Modal Crear Ticket -->
    <SolicitudFormModal
      :visible="isCreateTicketModalOpen"
      :solicitud-to-edit="null"
      :agentes="[]"
      :clients="[{ id: portalData.cliente_id, nombre_contacto: portalData.cliente_nombre, empresa: portalData.cliente_empresa }]"
      :saving="savingTicket"
      @close="isCreateTicketModalOpen = false"
      @save="handleSaveTicket"
    />

    <!-- Toast Notification -->
    <AppToast
      :visible="toast.visible"
      :message="toast.message"
      :type="toast.type"
      @close="toast.visible = false"
    />
  </div>
</template>
