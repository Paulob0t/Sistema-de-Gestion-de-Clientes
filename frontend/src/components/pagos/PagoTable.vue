<script setup lang="ts">
import type { PagoListItem } from '@/api/pagos'

defineProps<{
  pagos: PagoListItem[]
  isLoading: boolean
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
  <div class="overflow-x-auto custom-scrollbar">
    <table class="w-full text-left border-collapse">
      <thead>
        <tr class="border-b border-slate-800/80 bg-slate-900/40 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
          <th class="py-3.5 px-4">ID / Servicio</th>
          <th class="py-3.5 px-4">Cliente / Titular</th>
          <th class="py-3.5 px-4">Concepto</th>
          <th class="py-3.5 px-4">Monto</th>
          <th class="py-3.5 px-4">Método</th>
          <th class="py-3.5 px-4">Fechas</th>
          <th class="py-3.5 px-4 text-center">Estado</th>
          <th class="py-3.5 px-4 text-right">Acciones</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-800/50 text-xs">
        <tr
          v-for="pago in pagos"
          :key="pago.id"
          class="hover:bg-slate-800/30 transition-colors group"
        >
          <!-- 1. ID / Servicio -->
          <td class="py-3.5 px-4">
            <div class="flex items-center space-x-2.5">
              <div
                :class="[
                  'w-8 h-8 rounded-xl flex items-center justify-center shrink-0 font-bold text-xs',
                  pago.tipo_servicio === 1
                    ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20'
                    : pago.tipo_servicio === 2
                    ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/20'
                    : 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20'
                ]"
              >
                <i :class="pago.tipo_servicio === 1 ? 'pi pi-server text-xs' : pago.tipo_servicio === 2 ? 'pi pi-globe text-xs' : 'pi pi-credit-card text-xs'"></i>
              </div>
              <div class="min-w-0">
                <div class="font-bold text-white group-hover:text-cyan-400 transition-colors">
                  #{{ pago.id }}
                </div>
                <div class="text-[10px] text-slate-400">
                  {{ pago.tipo_servicio_label }}
                </div>
              </div>
            </div>
          </td>

          <!-- 2. Cliente / Titular -->
          <td class="py-3.5 px-4">
            <div class="font-semibold text-slate-200 truncate max-w-[170px]">
              {{ pago.cliente_empresa || pago.cliente_nombre }}
            </div>
            <div class="text-[11px] text-slate-400 truncate max-w-[170px]">
              {{ pago.cliente_nombre }}
            </div>
          </td>

          <!-- 3. Concepto -->
          <td class="py-3.5 px-4">
            <div class="font-medium text-slate-200 truncate max-w-[200px]" :title="pago.concepto">
              {{ pago.concepto }}
            </div>
            <div v-if="pago.nombre_servicio" class="text-[11px] text-cyan-400 font-mono truncate max-w-[200px]">
              {{ pago.nombre_servicio }}
            </div>
          </td>

          <!-- 4. Monto -->
          <td class="py-3.5 px-4">
            <div
              :class="[
                'font-extrabold text-sm',
                pago.estatus === 1 ? 'text-emerald-400' : 'text-amber-400'
              ]"
            >
              {{ formatCurrency(pago.monto, pago.currency) }}
            </div>
            <div class="text-[10px] text-slate-400">
              {{ pago.currency }}
            </div>
          </td>

          <!-- 5. Método de Pago -->
          <td class="py-3.5 px-4">
            <div class="text-slate-300 font-medium">
              {{ pago.forma_pago_label }}
            </div>
            <div v-if="pago.id_pago" class="text-[10px] text-slate-500 font-mono truncate max-w-[120px]">
              ref: {{ pago.id_pago }}
            </div>
          </td>

          <!-- 6. Fechas -->
          <td class="py-3.5 px-4">
            <div class="text-slate-300 text-[11px]">
              <span class="text-slate-500">Emisión:</span> {{ pago.fecha }}
            </div>
            <div v-if="pago.estatus === 1 && pago.fecha_pago" class="text-emerald-400 text-[11px]">
              <span class="text-emerald-500/70">Pagado:</span> {{ pago.fecha_pago }}
            </div>
            <div v-else-if="pago.fecha_limite_pago" class="text-amber-400 text-[11px]">
              <span class="text-amber-500/70">Límite:</span> {{ pago.fecha_limite_pago }}
            </div>
          </td>

          <!-- 7. Estado -->
          <td class="py-3.5 px-4 text-center">
            <button
              v-if="isSuperAdmin"
              @click="emit('toggle-status', pago)"
              :class="[
                'inline-flex items-center space-x-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold border transition-all cursor-pointer hover:scale-105 active:scale-95',
                pago.estatus === 1
                  ? 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30 hover:bg-emerald-500/25'
                  : 'bg-amber-500/15 text-amber-400 border-amber-500/30 hover:bg-amber-500/25'
              ]"
              :title="pago.estatus === 1 ? 'Clic para marcar como pendiente' : 'Clic para acreditar pago'"
            >
              <i :class="pago.estatus === 1 ? 'pi pi-check text-[9px]' : 'pi pi-clock text-[9px]'"></i>
              <span>{{ pago.estatus === 1 ? 'Acreditado' : 'Pendiente' }}</span>
            </button>
            <span
              v-else
              :class="[
                'inline-flex items-center space-x-1 px-2.5 py-1 rounded-full text-[10px] font-bold border',
                pago.estatus === 1
                  ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
                  : 'bg-amber-500/10 text-amber-400 border-amber-500/20'
              ]"
            >
              <span>{{ pago.estatus === 1 ? 'Acreditado' : 'Pendiente' }}</span>
            </span>
          </td>

          <!-- 8. Acciones -->
          <td class="py-3.5 px-4 text-right">
            <div class="flex items-center justify-end space-x-1">
              <!-- WhatsApp -->
              <button
                v-if="pago.cliente_telefono"
                @click="emit('send-whatsapp', pago)"
                class="p-1.5 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/20 transition-colors"
                title="Aviso de cobro WhatsApp"
              >
                <i class="pi pi-whatsapp text-xs"></i>
              </button>

              <!-- Ver Recibo / Detalle -->
              <button
                @click="emit('open-detail', pago.id)"
                class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition-colors"
                title="Ver recibo / detalle"
              >
                <i class="pi pi-eye text-xs"></i>
              </button>

              <!-- Editar -->
              <button
                v-if="isSuperAdmin"
                @click="emit('open-edit', pago)"
                class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition-colors"
                title="Editar pago"
              >
                <i class="pi pi-pencil text-xs"></i>
              </button>

              <!-- Eliminar -->
              <button
                v-if="isSuperAdmin"
                @click="emit('delete-pago', pago.id, pago.concepto)"
                class="p-1.5 rounded-lg bg-slate-800 hover:bg-rose-500/20 text-slate-400 hover:text-rose-400 transition-colors"
                title="Mover a papelera"
              >
                <i class="pi pi-trash text-xs"></i>
              </button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
