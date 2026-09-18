<script setup lang="ts">
import { useRouter } from 'vue-router'

defineProps<{
  totalPendientes: number
  montoPendiente: number
}>()

const router = useRouter()

function goToPagos() {
  router.push('/pagos')
}
</script>

<template>
  <div>
    <!-- ALERTA URGENTE: Pagos Pendientes -->
    <div
      v-if="totalPendientes > 0"
      @click="goToPagos"
      class="group cursor-pointer rounded-2xl bg-gradient-to-r from-amber-950/40 via-amber-900/20 to-slate-900 border border-amber-500/30 p-4 md:p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 transition-all hover:border-amber-500/50 hover:shadow-lg hover:shadow-amber-500/10"
    >
      <div class="flex items-start sm:items-center gap-3.5">
        <div class="w-10 h-10 rounded-xl bg-amber-500/20 border border-amber-500/30 text-amber-400 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
          <i class="pi pi-exclamation-triangle text-lg"></i>
        </div>
        <div>
          <div class="flex items-center gap-2">
            <h3 class="text-sm font-bold text-amber-300">
              {{ totalPendientes }} {{ totalPendientes === 1 ? 'pago pendiente' : 'pagos pendientes' }}
            </h3>
            <span v-if="montoPendiente > 0" class="px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-200 text-xs font-semibold">
              ${{ montoPendiente.toLocaleString('es-MX', { minimumFractionDigits: 2 }) }} MXN
            </span>
          </div>
          <p class="text-xs text-slate-300 mt-0.5">
            Liquídalo{{ totalPendientes === 1 ? '' : 's' }} a tiempo para mantener tus servicios, hosting y dominios activos sin interrupciones.
          </p>
        </div>
      </div>

      <div class="flex items-center gap-2 text-xs font-bold text-amber-400 group-hover:text-amber-300 transition-colors shrink-0 self-end sm:self-center">
        <span>Pagar ahora</span>
        <i class="pi pi-arrow-right text-xs group-hover:translate-x-1 transition-transform"></i>
      </div>
    </div>

    <!-- ALERTA OK: Cuenta al corriente -->
    <div
      v-else
      class="rounded-2xl bg-gradient-to-r from-emerald-950/30 via-slate-900 to-slate-900 border border-emerald-500/20 p-4 md:p-5 flex items-center justify-between gap-4"
    >
      <div class="flex items-center gap-3.5">
        <div class="w-10 h-10 rounded-xl bg-emerald-500/20 border border-emerald-500/30 text-emerald-400 flex items-center justify-center shrink-0">
          <i class="pi pi-check-circle text-lg"></i>
        </div>
        <div>
          <h3 class="text-sm font-bold text-emerald-300">Tu cuenta está al corriente</h3>
          <p class="text-xs text-slate-400 mt-0.5">
            No tienes pagos vencidos ni pendientes. Te notificaremos con anticipación antes de cada renovación.
          </p>
        </div>
      </div>
      <span class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/15 text-emerald-400 text-xs font-semibold border border-emerald-500/20">
        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
        Al día
      </span>
    </div>
  </div>
</template>
