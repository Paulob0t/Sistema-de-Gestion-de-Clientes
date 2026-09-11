<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { dominiosApi, type DominioPayload, type DominioDetail } from '@/api/dominios'
import type { ClienteListItem } from '@/api/clientes'

import AppSidebar from '@/components/layout/AppSidebar.vue'
import AppToast from '@/components/common/AppToast.vue'
import DominioFormClientSelect from '@/components/dominios/DominioFormClientSelect.vue'
import DominioFormBasicInfo from '@/components/dominios/DominioFormBasicInfo.vue'
import DominioFormCredentials from '@/components/dominios/DominioFormCredentials.vue'
import DominioFormDns from '@/components/dominios/DominioFormDns.vue'
import DominioFormFinancial from '@/components/dominios/DominioFormFinancial.vue'
import DominioSuccessModal from '@/components/dominios/DominioSuccessModal.vue'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const { showToast } = useToast()

const isMobileSidebarOpen = ref(false)
const isSaving = ref(false)
const showSuccessModal = ref(false)
const createdDomain = ref<DominioDetail | null>(null)
const selectedClientFacturacion = ref(0)

// Helper fecha hoy y +1 año
const today = new Date().toISOString().split('T')[0]
const nextYear = new Date()
nextYear.setFullYear(nextYear.getFullYear() + 1)
const defaultNextYear = nextYear.toISOString().split('T')[0]

// Estado reactivo del formulario
const formData = ref<DominioPayload>({
  cliente_id: 0,
  url_dominio: '',
  proveedor: 'NexusBot',
  url_admin: '',
  usuario: 'admin',
  contrasena: '',
  contrasena_normal: '',
  url_cpanel: '',
  ns1: '',
  ns2: '',
  ns3: '',
  ns4: '',
  ns5: '',
  ns6: '',
  costo_dominio: 350.0,
  id_forma_pago: 1,
  frecuencia_pago: 3,
  fecha_contratacion: today,
  fecha_pago: defaultNextYear,
  registrado: 1,
  estado_dominio: 1,
  estatus_pago: 0,
})

onMounted(() => {
  if (route.query.cliente_id) {
    const cid = Number(route.query.cliente_id)
    if (!isNaN(cid) && cid > 0) {
      formData.value.cliente_id = cid
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
  formData.value.contrasena = pass
  showToast('Contraseña de administrador generada')
}

function resetForm() {
  showSuccessModal.value = false
  createdDomain.value = null
  formData.value = {
    cliente_id: 0,
    url_dominio: '',
    proveedor: 'NexusBot',
    url_admin: '',
    usuario: 'admin',
    contrasena: '',
    contrasena_normal: '',
    url_cpanel: '',
    ns1: '',
    ns2: '',
    ns3: '',
    ns4: '',
    ns5: '',
    ns6: '',
    costo_dominio: 350.0,
    id_forma_pago: 1,
    frecuencia_pago: 3,
    fecha_contratacion: today,
    fecha_pago: defaultNextYear,
    registrado: 1,
    estado_dominio: 1,
    estatus_pago: 0,
  }
}

async function handleSubmit() {
  if (!formData.value.cliente_id || formData.value.cliente_id <= 0) {
    showToast('Por favor selecciona un cliente titular', 'error')
    return
  }

  if (!formData.value.url_dominio || formData.value.url_dominio.trim().length < 3) {
    showToast('Por favor ingresa un nombre de dominio válido', 'error')
    return
  }

  isSaving.value = true
  try {
    const payload: DominioPayload = {
      cliente_id: formData.value.cliente_id,
      url_dominio: formData.value.url_dominio.trim().toLowerCase(),
      proveedor: formData.value.proveedor?.trim() || 'NexusBot',
      url_admin: formData.value.url_admin?.trim() || undefined,
      usuario: formData.value.usuario?.trim() || undefined,
      contrasena: formData.value.contrasena_normal?.trim() || undefined,
      contrasena_normal: formData.value.contrasena_normal?.trim() || undefined,
      url_cpanel: formData.value.url_cpanel?.trim() || undefined,
      ns1: formData.value.ns1?.trim() || undefined,
      ns2: formData.value.ns2?.trim() || undefined,
      ns3: formData.value.ns3?.trim() || undefined,
      ns4: formData.value.ns4?.trim() || undefined,
      ns5: formData.value.ns5?.trim() || undefined,
      ns6: formData.value.ns6?.trim() || undefined,
      costo_dominio: Number(formData.value.costo_dominio) || 0.0,
      id_forma_pago: Number(formData.value.id_forma_pago) || 1,
      frecuencia_pago: Number(formData.value.frecuencia_pago) || 3,
      fecha_contratacion: formData.value.fecha_contratacion || today,
      fecha_pago: formData.value.fecha_pago || defaultNextYear,
      registrado: formData.value.registrado ?? 1,
      estado_dominio: formData.value.estado_dominio ?? 1,
      estatus_pago: 0,
    }

    const res = await dominiosApi.createDominio(payload)
    createdDomain.value = res
    showToast(`Dominio "${res.url_dominio}" asignado exitosamente`)
    showSuccessModal.value = true
  } catch (err: any) {
    showToast(err.response?.data?.detail || 'Error al registrar el dominio', 'error')
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
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 h-full flex items-center justify-between">
          <div class="flex items-center space-x-3">
            <button
              @click="isMobileSidebarOpen = true"
              class="lg:hidden p-2 rounded-xl bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 transition-colors"
            >
              <i class="pi pi-bars text-base"></i>
            </button>
            <button
              @click="router.push('/dominios')"
              class="p-2 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white transition-colors"
              title="Volver a lista de dominios"
            >
              <i class="pi pi-arrow-left text-xs"></i>
            </button>
            <div class="flex items-center space-x-2">
              <span class="text-sm font-bold text-white">Asignar Dominio</span>
              <span class="text-slate-600 hidden sm:inline">•</span>
              <span class="text-xs text-cyan-400 font-semibold uppercase tracking-wider hidden sm:inline">Registro & DNS</span>
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
          <!-- 1. Selección de Cliente -->
          <DominioFormClientSelect
            v-model="formData.cliente_id"
            :selected-client-facturacion="selectedClientFacturacion"
            @client-selected="onClientSelected"
          />

          <!-- 2. Dominio y Proveedor -->
          <DominioFormBasicInfo
            :form-data="formData"
          />

          <!-- 3. Credenciales y Accesos -->
          <DominioFormCredentials
            :form-data="formData"
            @generate-password="generateSecurePassword"
          />

          <!-- 4. Configuración DNS -->
          <DominioFormDns
            :form-data="formData"
          />

          <!-- 5. Costos, Estados y Fechas -->
          <DominioFormFinancial
            :form-data="formData"
            :selected-client-facturacion="selectedClientFacturacion"
          />

          <!-- BOTONES DE ACCIÓN -->
          <div class="flex items-center justify-end space-x-4 pt-2">
            <button
              type="button"
              @click="router.push('/dominios')"
              class="px-5 py-3 rounded-2xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold transition-colors"
            >
              Cancelar
            </button>
            <button
              type="submit"
              :disabled="isSaving"
              class="px-8 py-3 rounded-2xl bg-gradient-to-r from-cyan-600 via-teal-600 to-emerald-600 hover:from-cyan-500 hover:to-emerald-500 text-white text-xs font-extrabold shadow-xl shadow-cyan-500/20 flex items-center space-x-2 transition-all disabled:opacity-50 active:scale-95"
            >
              <i v-if="isSaving" class="pi pi-spin pi-spinner text-xs"></i>
              <i v-else class="pi pi-check text-xs"></i>
              <span>{{ isSaving ? 'Guardando Dominio...' : 'Asignar Dominio' }}</span>
            </button>
          </div>
        </form>
      </main>
    </div>

    <!-- MODAL DE ÉXITO -->
    <DominioSuccessModal
      :is-open="showSuccessModal"
      :domain="createdDomain"
      :raw-password="formData.contrasena_normal || undefined"
      @close="showSuccessModal = false"
      @reset-form="resetForm"
    />
  </div>
</template>
