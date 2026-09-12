<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { pagosApi, type PagoPayload, type PagoDetail } from '@/api/pagos'
import type { ClienteListItem } from '@/api/clientes'

import AppSidebar from '@/components/layout/AppSidebar.vue'
import AppToast from '@/components/common/AppToast.vue'
import PagoFormClientService from '@/components/pagos/PagoFormClientService.vue'
import PagoFormConceptAmount from '@/components/pagos/PagoFormConceptAmount.vue'
import PagoFormMethodStatus from '@/components/pagos/PagoFormMethodStatus.vue'
import PagoFormDatesRecurrence from '@/components/pagos/PagoFormDatesRecurrence.vue'
import PagoSuccessModal from '@/components/pagos/PagoSuccessModal.vue'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const { showToast } = useToast()

const isMobileSidebarOpen = ref(false)
const isSaving = ref(false)
const showSuccessModal = ref(false)
const createdPago = ref<PagoDetail | null>(null)
const selectedClientFacturacion = ref(0)

const today = new Date().toISOString().split('T')[0]
const inThreeDays = new Date(Date.now() + 3 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]

const formData = ref<PagoPayload>({
  id_clie: 0,
  monto: 0,
  currency: 'MXN',
  concepto: '',
  forma_pago: 1,
  estatus: 0,
  tipo_servicio: 0,
  id_servicio: 0,
  fecha: today,
  fecha_pago: today,
  fecha_limite_pago: inThreeDays,
  id_pago: '',
  manual: 1,
  frecuencia_pago: 0,
  pago_recurrente: 0,
})

onMounted(() => {
  if (route.query.cliente_id) {
    const cid = Number(route.query.cliente_id)
    if (!isNaN(cid) && cid > 0) {
      formData.value.id_clie = cid
    }
  }
  if (route.query.tipo_servicio) {
    const ts = Number(route.query.tipo_servicio)
    if (!isNaN(ts)) {
      formData.value.tipo_servicio = ts
    }
  }
  if (route.query.id_servicio) {
    const is = Number(route.query.id_servicio)
    if (!isNaN(is)) {
      formData.value.id_servicio = is
    }
  }
  if (route.query.monto) {
    const m = Number(route.query.monto)
    if (!isNaN(m) && m > 0) {
      formData.value.monto = m
    }
  }
  if (route.query.concepto) {
    formData.value.concepto = String(route.query.concepto)
  }
})

function onClientSelected(client: ClienteListItem) {
  formData.value.id_clie = client.id
  selectedClientFacturacion.value = client.facturacion || 0
}

function resetForm() {
  showSuccessModal.value = false
  createdPago.value = null
  formData.value = {
    id_clie: 0,
    monto: 0,
    currency: 'MXN',
    concepto: '',
    forma_pago: 1,
    estatus: 0,
    tipo_servicio: 0,
    id_servicio: 0,
    fecha: today,
    fecha_pago: today,
    fecha_limite_pago: inThreeDays,
    id_pago: '',
    manual: 1,
    frecuencia_pago: 0,
    pago_recurrente: 0,
  }
}

async function handleSubmit() {
  if (!formData.value.id_clie || formData.value.id_clie <= 0) {
    showToast('Por favor selecciona un cliente titular', 'error')
    return
  }

  if (!formData.value.concepto || formData.value.concepto.trim().length < 3) {
    showToast('Por favor ingresa una descripción o concepto válido', 'error')
    return
  }

  if (!formData.value.monto || Number(formData.value.monto) <= 0) {
    showToast('Por favor ingresa un monto válido mayor a 0', 'error')
    return
  }

  isSaving.value = true
  try {
    const payload: PagoPayload = {
      id_clie: formData.value.id_clie,
      monto: Number(formData.value.monto),
      currency: formData.value.currency || 'MXN',
      concepto: formData.value.concepto.trim(),
      forma_pago: Number(formData.value.forma_pago) || 1,
      estatus: Number(formData.value.estatus) || 0,
      tipo_servicio: Number(formData.value.tipo_servicio) || 0,
      id_servicio: Number(formData.value.id_servicio) || 0,
      fecha: formData.value.fecha || today,
      fecha_pago: formData.value.estatus === 1 ? (formData.value.fecha_pago || today) : undefined,
      fecha_limite_pago: formData.value.fecha_limite_pago || inThreeDays,
      id_pago: formData.value.id_pago?.trim() || undefined,
      manual: 1,
      frecuencia_pago: Number(formData.value.frecuencia_pago) || 0,
      pago_recurrente: formData.value.frecuencia_pago && formData.value.frecuencia_pago > 0 ? 1 : 0,
    }

    const res = await pagosApi.createPago(payload)
    createdPago.value = res
    showToast(`Cobro registrado exitosamente (#${res.id})`)
    showSuccessModal.value = true
  } catch (err: any) {
    showToast(err.response?.data?.detail || 'Error al registrar el cobro', 'error')
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
  <div class="h-screen w-screen bg-[#080C14] text-slate-100 selection:bg-indigo-600 selection:text-white flex overflow-hidden">
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
              @click="router.push('/pagos')"
              class="p-2 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white transition-colors"
              title="Volver a lista de pagos"
            >
              <i class="pi pi-arrow-left text-xs"></i>
            </button>
            <div class="flex items-center space-x-2">
              <span class="text-sm font-bold text-white">Registrar Pago</span>
              <span class="text-slate-600 hidden sm:inline">•</span>
              <span class="text-xs text-indigo-400 font-semibold uppercase tracking-wider hidden sm:inline">Cobranza & Facturación</span>
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
          <!-- 1. Cliente y Servicio -->
          <PagoFormClientService
            :form-data="formData"
            :selected-client-facturacion="selectedClientFacturacion"
            @client-selected="onClientSelected"
          />

          <!-- 2. Concepto y Monto -->
          <PagoFormConceptAmount
            :form-data="formData"
            :selected-client-facturacion="selectedClientFacturacion"
          />

          <!-- 3. Método de Pago y Estatus -->
          <PagoFormMethodStatus
            :form-data="formData"
          />

          <!-- 4. Fechas y Recurrencia -->
          <PagoFormDatesRecurrence
            :form-data="formData"
          />

          <!-- BOTONES DE ACCIÓN -->
          <div class="flex items-center justify-end space-x-4 pt-2">
            <button
              type="button"
              @click="router.push('/pagos')"
              class="px-5 py-3 rounded-2xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold transition-colors"
            >
              Cancelar
            </button>
            <button
              type="submit"
              :disabled="isSaving"
              class="px-8 py-3 rounded-2xl bg-gradient-to-r from-indigo-600 via-purple-600 to-indigo-500 hover:from-indigo-500 hover:to-purple-500 text-white text-xs font-extrabold shadow-xl shadow-indigo-500/20 flex items-center space-x-2 transition-all disabled:opacity-50 active:scale-95"
            >
              <i v-if="isSaving" class="pi pi-spin pi-spinner text-xs"></i>
              <i v-else class="pi pi-check text-xs"></i>
              <span>{{ isSaving ? 'Generando Cobro...' : 'Registrar Pago' }}</span>
            </button>
          </div>
        </form>
      </main>
    </div>

    <!-- MODAL DE ÉXITO -->
    <PagoSuccessModal
      :is-open="showSuccessModal"
      :pago="createdPago"
      @close="showSuccessModal = false"
      @reset-form="resetForm"
    />
  </div>
</template>
