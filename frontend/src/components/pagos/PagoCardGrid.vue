<script setup lang="ts">
import type { PagoListItem } from '@/api/pagos'

defineProps<{
  pagos: PagoListItem[]
  isSuperAdmin: boolean
}>()

const emit = defineEmits<{
  (e: 'open-detail', id: number): void
  (e: 'open-edit', pago: PagoListItem): void
  (e: 'toggle-status', pago: PagoListItem): void
  (e: 'delete-pago', id: number, concepto: string): void
  (e: 'send-whatsapp', pago: PagoListItem): void
}>()

function formatCurrency(val: number, moneda = 'MXN') {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: moneda,
  }).format(val)
}
</script>

<template>
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    <div
      v-for="pago in pagos"
      :key="pago.id"
      class="p-5 rounded-2xl bg-[#0C111E]/90 border border-slate-800 hover:border-slate-700 transition-all duration-150 flex flex-col justify-between group relative overflow-hidden"
    >
      <div>
        <!-- Header de la tarjeta -->
        <div class="flex items-start justify-between">
          <div class="flex items-center space-x-3">
            <div
              :class="[
                'w-10 h-10 rounded-xl flex items-center justify-center font-bold text-sm shrink-0 shadow-md',
                pago.tipo_servicio === 1
                  ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20'
                  : pago.tipo_servicio === 2
                  ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/20'
                  : 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20'
              ]"
            >
              <i :class="pago.tipo_servicio === 1 ? 'pi pi-server text-base' : pago.tipo_servicio === 2 ? 'pi pi-globe text-base' : 'pi pi-credit-card text-base'"></i>
            </div>
            <div>
              <div class="font-bold text-white group-hover:text-cyan-400 transition-colors text-sm">
                Recibo #{{ pago.id }}
              </div>
              <div class="text-[11px] text-slate-400">
                {{ pago.tipo_servicio_label }} • {{ pago.forma_pago_label }}
              </div>
            </div>
          </div>

          <button
            v-if="isSuperAdmin"
            @click="emit('toggle-status', pago)"
            :class="[
              'px-2.5 py-1 rounded-full text-[10px] font-bold border transition-all cursor-pointer hover:scale-105 active:scale-95',
              pago.estatus === 1
                ? 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30 hover:bg-emerald-500/25'
                : 'bg-amber-500/15 text-amber-400 border-amber-500/30 hover:bg-amber-500/25'
            ]"
            :title="pago.estatus === 1 ? 'Marcar pendiente' : 'Acreditar pago'"
          >
            {{ pago.estatus === 1 ? 'Acreditado' : 'Pendiente' }}
          </button>
        </div>

        <!-- Información de Cliente y Concepto -->
        <div class="mt-4 p-3 rounded-xl bg-slate-900/60 border border-slate-800/60 space-y-1.5 text-xs">
          <div class="flex justify-between items-center text-slate-300">
            <span class="text-slate-500 text-[11px]">Cliente:</span>
            <span class="font-semibold truncate max-w-[170px]">{{ pago.cliente_empresa || pago.cliente_nombre }}</span>
          </div>
          <div class="flex justify-between items-center text-slate-300">
            <span class="text-slate-500 text-[11px]">Concepto:</span>
            <span class="text-slate-200 truncate max-w-[170px] font-medium">{{ pago.concepto }}</span>
          </div>
          <div v-if="pago.nombre_servicio" class="flex justify-between items-center text-slate-300">
            <span class="text-slate-500 text-[11px]">Servicio:</span>
            <span class="text-cyan-400 font-mono text-[11px] truncate max-w-[170px]">{{ pago.nombre_servicio }}</span>
          </div>
        </div>

        <!-- Monto & Fechas -->
        <div class="mt-3 flex items-center justify-between pt-2 border-t border-slate-800/40 text-xs">
          <div>
            <div class="text-[10px] text-slate-500 font-medium">Fecha Emisión</div>
            <div class="font-semibold text-slate-200 mt-0.5">
              {{ pago.fecha }}
            </div>
          </div>
          <div class="text-right">
            <div class="text-[10px] text-slate-500 font-medium">Monto Total</div>
            <div
              :class="[
                'font-extrabold text-base',
                pago.estatus === 1 ? 'text-emerald-400' : 'text-amber-400'
              ]"
            >
              {{ formatCurrency(pago.monto, pago.currency) }}
            </div>
          </div>
        </div>
      </div>

      <!-- Botones de Acción -->
      <div class="mt-4 pt-3 border-t border-slate-800 flex items-center justify-between gap-2">
        <button
          v-if="pago.cliente_telefono"
          @click="emit('send-whatsapp', pago)"
          class="flex-1 py-1.5 px-2 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/20 text-xs font-semibold flex items-center justify-center space-x-1.5 transition-colors"
        >
          <i class="pi pi-whatsapp text-xs"></i>
          <span>Aviso WhatsApp</span>
        </button>

        <div class="flex items-center space-x-1 shrink-0">
          <button
            @click="emit('open-detail', pago.id)"
            class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 transition-colors"
            title="Ver recibo / detalle"
          >
            <i class="pi pi-eye text-xs"></i>
          </button>
          <button
            v-if="isSuperAdmin"
            @click="emit('open-edit', pago)"
            class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 transition-colors"
            title="Editar"
          >
            <i class="pi pi-pencil text-xs"></i>
          </button>
          <button
            v-if="isSuperAdmin"
            @click="emit('delete-pago', pago.id, pago.concepto)"
            class="p-2 rounded-xl bg-slate-800 hover:bg-rose-500/20 text-slate-400 hover:text-rose-400 transition-colors"
            title="Eliminar"
          >
            <i class="pi pi-trash text-xs"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
