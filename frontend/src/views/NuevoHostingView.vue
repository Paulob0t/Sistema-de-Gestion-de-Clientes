<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { hostingsApi, type HostingPayload, type HostingDetail } from '@/api/hostings'
import type { ClienteListItem } from '@/api/clientes'

import AppSidebar from '@/components/layout/AppSidebar.vue'
import AppToast from '@/components/common/AppToast.vue'
import HostingFormClientDomain from '@/components/hostings/HostingFormClientDomain.vue'
import HostingFormCredentials from '@/components/hostings/HostingFormCredentials.vue'
import HostingFormPlanPricing from '@/components/hostings/HostingFormPlanPricing.vue'
import HostingFormDnsDates from '@/components/hostings/HostingFormDnsDates.vue'
import HostingSuccessModal from '@/components/hostings/HostingSuccessModal.vue'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const { showToast } = useToast()

const isMobileSidebarOpen = ref(false)
const isSaving = ref(false)
const showSuccessModal = ref(false)
const createdHosting = ref<HostingDetail | null>(null)
const selectedClientFacturacion = ref(0)

const today = new Date().toISOString().split('T')[0]
const nextYear = new Date()
nextYear.setFullYear(nextYear.getFullYear() + 1)
const defaultNextYear = nextYear.toISOString().split('T')[0]

const formData = ref<HostingPayload>({
  cliente_id: 0,
  nom_host: '',
  dominio: '',
  usuario: 'admin',
  contrasena_normal: '',
  tipo_producto: 'Hosting Compartido',
  producto: 1,
  costo_producto: 699.0,
  id_forma_pago: 1,
  dns: '',
  url_pago: '',
  url_acceso: '',
  ns1: 'ns1.nexusbot.io',
  ns2: 'ns2.nexusbot.io',
  ns3: '',
  ns4: '',
  ns5: '',
  ns6: '',
  fecha_contratacion: today,
  fecha_pago: defaultNextYear,
  estado_producto: 1,
  IVA: 1,
  frecuencia_pago: 2,
})

onMounted(() => {
  if (route.query.cliente_id) {
    const cid = Number(route.query.cliente_id)
    if (!isNaN(cid) && cid > 0) {
      formData.value.cliente_id = cid
    }
  }
  if (route.query.dominio) {
    const dom = String(route.query.dominio).trim()
    if (dom) {
      formData.value.dominio = dom
      formData.value.nom_host = `cpanel.${dom}`
      formData.value.url_acceso = `https://cpanel.${dom}:2083/`
    }
  }
})

function onClientSelected(client: ClienteListItem) {
  formData.value.cliente_id = client.id
  selectedClientFacturacion.value = client.facturacion || 0
}

function generateSecurePassword() {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%&*'
  let pass = ''
  for (let i = 0; i < 12; i++) {
    pass += chars.charAt(Math.floor(Math.random() * chars.length))
  }
  formData.value.contrasena_normal = pass
  showToast('Contraseña de servidor generada con éxito')
}

function resetForm() {
  showSuccessModal.value = false
  createdHosting.value = null
  formData.value = {
    cliente_id: 0,
    nom_host: '',
    dominio: '',
    usuario: 'admin',
    contrasena_normal: '',
    tipo_producto: 'Hosting Compartido',
    producto: 1,
    costo_producto: 699.0,
    id_forma_pago: 1,
    dns: '',
    url_pago: '',
    url_acceso: '',
    ns1: 'ns1.nexusbot.io',
    ns2: 'ns2.nexusbot.io',
    ns3: '',
    ns4: '',
    ns5: '',
    ns6: '',
    fecha_contratacion: today,
    fecha_pago: defaultNextYear,
    estado_producto: 1,
    IVA: 1,
    frecuencia_pago: 2,
  }
}

async function handleSubmit() {
  if (!formData.value.cliente_id || formData.value.cliente_id <= 0) {
    showToast('Por favor selecciona un cliente titular', 'error')
    return
  }

  if (!formData.value.nom_host || formData.value.nom_host.trim().length < 3) {
    showToast('Por favor ingresa un nombre de host / servidor válido', 'error')
    return
  }

  isSaving.value = true
  try {
    const payload: HostingPayload = {
      cliente_id: formData.value.cliente_id,
      nom_host: formData.value.nom_host.trim(),
      dominio: formData.value.dominio?.trim() || '',
      usuario: formData.value.usuario?.trim() || '',
      contrasena_normal: formData.value.contrasena_normal?.trim() || '',
      tipo_producto: formData.value.tipo_producto?.trim() || 'Hosting Compartido',
      producto: Number(formData.value.producto) || 1,
      costo_producto: Number(formData.value.costo_producto) || 0.0,
      id_forma_pago: Number(formData.value.id_forma_pago) || 1,
      dns: formData.value.dns?.trim() || '',
      url_pago: formData.value.url_pago?.trim() || '',
      url_acceso: formData.value.url_acceso?.trim() || `https://${formData.value.nom_host.trim()}:2083/`,
      ns1: formData.value.ns1?.trim() || '',
      ns2: formData.value.ns2?.trim() || '',
      ns3: formData.value.ns3?.trim() || '',
      ns4: formData.value.ns4?.trim() || '',
      ns5: formData.value.ns5?.trim() || '',
      ns6: formData.value.ns6?.trim() || '',
      fecha_contratacion: formData.value.fecha_contratacion || today,
      fecha_pago: formData.value.fecha_pago || defaultNextYear,
      estado_producto: formData.value.estado_producto ?? 1,
      IVA: formData.value.IVA ?? 1,
      frecuencia_pago: Number(formData.value.frecuencia_pago) || 2,
    }

    const res = await hostingsApi.createHosting(payload)
    createdHosting.value = res
    showToast(`Hosting "${res.nom_host}" aprovisionado exitosamente`)
    showSuccessModal.value = true
  } catch (err: any) {
    showToast(err.response?.data?.detail || 'Error al guardar el servicio de hosting', 'error')
  } finally {
    isSaving.value = false
  }
}

function handleLogout() {
  authStore.logout()
  router.push('/login')
}
</script>

<template>
  <div class="h-screen w-screen bg-[#080C14] text-slate-100 selection:bg-amber-600 selection:text-white flex overflow-hidden">
    <!-- MENÚ LATERAL -->
    <AppSidebar
      :is-mobile-open="isMobileSidebarOpen"
      @close-mobile="isMobileSidebarOpen = false"
    />

    <!-- CONTENEDOR PRINCIPAL -->
    <div class="flex-1 flex flex-col min-w-0 h-screen overflow-y-auto pb-16">
      <!-- HEADER SUPERIOR -->
      <header class="sticky top-0 z-30 bg-[#0A0F1D]/80 backdrop-blur-md border-b border-slate-800/80 h-16 shrink-0">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 h-full flex items-center justify-between">
          <div class="flex items-center space-x-3">
            <button
              @click="isMobileSidebarOpen = true"
              class="lg:hidden p-2 rounded-xl bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 transition-colors"
            >
              <i class="pi pi-bars text-base"></i>
            </button>
            <button
              @click="router.push('/hostings')"
              class="p-2 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white transition-colors"
              title="Volver a lista de hostings"
            >
              <i class="pi pi-arrow-left text-xs"></i>
            </button>
            <div class="flex items-center space-x-2">
              <span class="text-sm font-bold text-white">Asignar Hosting</span>
              <span class="text-slate-600 hidden sm:inline">•</span>
              <span class="text-xs text-amber-400 font-semibold uppercase tracking-wider hidden sm:inline">Servidor & Planes</span>
            </div>
          </div>

          <div class="flex items-center space-x-3">
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

      <!-- FORMULARIO PRINCIPAL -->
      <main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 w-full space-y-6">
        <form @submit.prevent="handleSubmit" class="space-y-6">
          <!-- 1. Cliente y Dominio -->
          <HostingFormClientDomain
            :form-data="formData"
            :selected-client-facturacion="selectedClientFacturacion"
            @client-selected="onClientSelected"
          />

          <!-- 2. Credenciales y Panel -->
          <HostingFormCredentials
            :form-data="formData"
            @generate-password="generateSecurePassword"
          />

          <!-- 3. Plan, Tarifas y Facturación -->
          <HostingFormPlanPricing
            :form-data="formData"
            :selected-client-facturacion="selectedClientFacturacion"
          />

          <!-- 4. DNS, Fechas y Estado -->
          <HostingFormDnsDates
            :form-data="formData"
          />

          <!-- BOTONES DE ACCIÓN -->
          <div class="flex items-center justify-end space-x-4 pt-2">
            <button
              type="button"
              @click="router.push('/hostings')"
              class="px-5 py-3 rounded-2xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold transition-colors"
            >
              Cancelar
            </button>
            <button
              type="submit"
              :disabled="isSaving"
              class="px-8 py-3 rounded-2xl bg-gradient-to-r from-amber-600 via-orange-600 to-amber-500 hover:from-amber-500 hover:to-orange-500 text-white text-xs font-extrabold shadow-xl shadow-amber-500/20 flex items-center space-x-2 transition-all disabled:opacity-50 active:scale-95"
            >
              <i v-if="isSaving" class="pi pi-spin pi-spinner text-xs"></i>
              <i v-else class="pi pi-check text-xs"></i>
              <span>{{ isSaving ? 'Aprovisionando Hosting...' : 'Asignar Hosting' }}</span>
            </button>
          </div>
        </form>
      </main>
    </div>

    <!-- MODAL DE ÉXITO -->
    <HostingSuccessModal
      :is-open="showSuccessModal"
      :hosting="createdHosting"
      :raw-password="formData.contrasena_normal || undefined"
      @close="showSuccessModal = false"
      @reset-form="resetForm"
    />
  </div>
</template>
