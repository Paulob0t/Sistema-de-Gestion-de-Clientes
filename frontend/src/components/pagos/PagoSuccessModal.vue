<script setup lang="ts">
import { useRouter } from 'vue-router'
import type { PagoDetail } from '@/api/pagos'

const router = useRouter()

const props = defineProps<{
  isOpen: boolean
  pago: PagoDetail | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'reset-form'): void
}>()

function sendPagoWhatsApp() {
  if (!props.pago) return
  const phone = props.pago.cliente_telefono?.replace(/[^0-9]/g, '') || ''
  const isPaid = props.pago.estatus === 1

  let texto = `¡Hola ${props.pago.cliente_nombre}! Te compartimos los detalles de tu comprobante en NexusBot:\n\n`
  texto += `📄 Folio / Concepto: ${props.pago.concepto}\n`
  texto += `💵 Monto: $${Number(props.pago.monto).toFixed(2)} ${props.pago.currency}\n`
  texto += `💳 Método: ${props.pago.forma_pago_label}\n`
  texto += `📌 Estatus: ${isPaid ? '✅ Acreditado / Pagado' : '⏳ Pendiente de Pago'}\n`
  if (!isPaid && props.pago.fecha_limite_pago) {
    texto += `📅 Fecha límite de pago: ${props.pago.fecha_limite_pago}\n`
  }
  if (props.pago.id_pago) {
    texto += `🔖 Referencia: ${props.pago.id_pago}\n`
  }
  texto += `\n¡Gracias por tu preferencia!`

  window.open(`https://wa.me/${phone}?text=${encodeURIComponent(texto)}`, '_blank')
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="isOpen && pago"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-md"
      @click.self="emit('close')"
    >
      <div class="w-full max-w-lg bg-[#0B101D] border border-emerald-500/40 rounded-3xl shadow-2xl p-6 space-y-5 animate-fadeIn relative">
        <button
          @click="emit('close')"
          class="absolute top-5 right-5 text-slate-400 hover:text-white p-1 rounded-lg bg-slate-800/60 hover:bg-slate-700 transition-colors"
          title="Cerrar"
        >
          <i class="pi pi-times text-xs"></i>
        </button>

        <div class="flex items-center space-x-3.5">
          <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 flex items-center justify-center text-xl font-bold">
            <i class="pi pi-check-circle"></i>
          </div>
          <div>
            <h3 class="text-base font-extrabold text-white">¡Cobro Registrado con Éxito!</h3>
            <p class="text-xs text-slate-400">Folio #{{ pago.id }} • {{ pago.id_pago || 'Sin referencia' }}</p>
          </div>
        </div>

        <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-800 space-y-2 text-xs">
          <div class="flex justify-between text-slate-300">
            <span class="text-slate-500">Cliente:</span>
            <span class="font-semibold text-white">{{ pago.cliente_empresa }} ({{ pago.cliente_nombre }})</span>
          </div>
          <div class="flex justify-between text-slate-300">
            <span class="text-slate-500">Concepto:</span>
            <span class="text-slate-200 font-medium truncate max-w-[240px]">{{ pago.concepto }}</span>
          </div>
          <div class="flex justify-between text-slate-300">
            <span class="text-slate-500">Monto:</span>
            <span class="text-emerald-400 font-extrabold text-sm">${{ Number(pago.monto).toFixed(2) }} {{ pago.currency }}</span>
          </div>
          <div class="flex justify-between text-slate-300">
            <span class="text-slate-500">Estatus:</span>
            <span
              class="px-2 py-0.5 rounded-md font-bold text-[10px]"
              :class="pago.estatus === 1 ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400'"
            >
              {{ pago.estatus_label }}
            </span>
          </div>
        </div>

        <!-- Acciones Rápidas -->
        <div class="space-y-2">
          <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">¿Qué deseas hacer ahora?</span>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
            <button
              @click="sendPagoWhatsApp"
              class="p-3 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/20 font-bold flex items-center space-x-2 transition-colors"
            >
              <i class="pi pi-whatsapp text-sm"></i>
              <span>Enviar WhatsApp</span>
            </button>
            <button
              @click="emit('reset-form')"
              class="p-3 rounded-xl bg-cyan-500/10 hover:bg-cyan-500/20 text-cyan-400 border border-cyan-500/20 font-bold flex items-center space-x-2 transition-colors"
            >
              <i class="pi pi-plus text-sm"></i>
              <span>Registrar Otro Pago</span>
            </button>
            <button
              @click="router.push('/pagos')"
              class="p-3 rounded-xl bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-400 border border-indigo-500/20 font-bold flex items-center space-x-2 transition-colors"
            >
              <i class="pi pi-receipt text-sm"></i>
              <span>Ver Directorio de Pagos</span>
            </button>
            <button
              @click="router.push('/clientes')"
              class="p-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold flex items-center space-x-2 transition-colors"
            >
              <i class="pi pi-users text-sm"></i>
              <span>Ver Clientes</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
