<script setup lang="ts">
import type { PagoItem } from '@/api/dashboard'

defineProps<{
  pagos: PagoItem[]
  searchQuery: string
  activeFilter: string
  formatCurrency: (amount: number, currency?: string) => string
  formatWhatsAppLink: (phone: string | null, cliente: string, monto: number, concepto: string) => string
}>()

const emit = defineEmits<{
  (e: 'update:search-query', val: string): void
  (e: 'update:active-filter', val: string): void
  (e: 'copy-details', pago: PagoItem): void
}>()
</script>

<template>
  <div class="rounded-3xl bg-[#0D121F]/90 border border-slate-800/80 p-5 sm:p-7 space-y-5 shadow-2xl">
    <!-- Header y Filtros -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
      <div>
        <h3 class="text-base font-bold text-white tracking-tight flex items-center space-x-2.5">
          <span>Cobranza & Pagos Pendientes</span>
          <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-500/10 text-amber-300 border border-amber-500/30">
            {{ pagos.length }} Registros
          </span>
        </h3>
        <p class="text-xs text-slate-400 mt-0.5">Acciones rápidas para WhatsApp, correo y notas de cobro.</p>
      </div>

      <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
        <!-- Input de Búsqueda -->
        <div class="relative">
          <i class="pi pi-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
          <input
            :value="searchQuery"
            @input="emit('update:search-query', ($event.target as HTMLInputElement).value)"
            type="text"
            placeholder="Buscar cliente, concepto..."
            class="w-full sm:w-60 pl-9 pr-4 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:border-blue-500 transition-colors"
          />
        </div>

        <!-- Filtros Rápidos -->
        <div class="flex items-center space-x-1 p-1 rounded-xl bg-slate-950/80 border border-slate-800 overflow-x-auto text-xs">
          <button
            v-for="f in [
              { id: 'todos', label: 'Todos' },
              { id: 'vencidos', label: 'Vencidos' },
              { id: 'prox7', label: 'Próx. 7 días' },
              { id: 'dominios', label: 'Dominios' },
              { id: 'hosting', label: 'Hosting' },
            ]"
            :key="f.id"
            @click="emit('update:active-filter', f.id)"
            :class="[
              'px-2.5 py-1 rounded-lg font-medium transition-colors shrink-0',
              activeFilter === f.id ? 'bg-blue-600 text-white font-semibold' : 'text-slate-400 hover:text-white'
            ]"
          >
            {{ f.label }}
          </button>
        </div>
      </div>
    </div>

    <!-- Tabla -->
    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs">
        <thead class="bg-[#0A0F1D] text-slate-400 uppercase tracking-wider font-semibold border-b border-slate-800/80 text-[10px]">
          <tr>
            <th class="py-3.5 px-4">Cliente / Servicio</th>
            <th class="py-3.5 px-4">Concepto</th>
            <th class="py-3.5 px-4">Monto</th>
            <th class="py-3.5 px-4">Vencimiento</th>
            <th class="py-3.5 px-4 text-center">Acciones Rápidas</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-800/50">
          <tr
            v-for="pago in pagos"
            :key="pago.id"
            class="hover:bg-[#131A2D]/70 transition-colors duration-100 group"
          >
            <!-- Cliente / Dominio -->
            <td class="py-3.5 px-4">
              <div class="font-bold text-white text-sm">{{ pago.cliente_nombre }}</div>
              <div class="text-slate-400 text-[11px] flex items-center space-x-1.5 mt-0.5">
                <span v-if="pago.nombre_servicio" class="text-blue-400 flex items-center font-mono">
                  <i class="pi pi-globe text-[10px] mr-1"></i>{{ pago.nombre_servicio }}
                </span>
                <span v-else>{{ pago.cliente_correo || 'Sin correo' }}</span>
              </div>
            </td>

            <!-- Concepto y Tipo -->
            <td class="py-3.5 px-4">
              <div class="font-medium text-slate-200">{{ pago.concepto }}</div>
              <span class="inline-block mt-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-slate-900 text-slate-400 border border-slate-800">
                {{ pago.tipo_servicio_label }}
              </span>
            </td>

            <!-- Monto -->
            <td class="py-3.5 px-4 font-black text-white text-sm">
              {{ formatCurrency(pago.monto, pago.currency) }}
            </td>

            <!-- Estado Vencimiento -->
            <td class="py-3.5 px-4">
              <div class="flex items-center space-x-1.5">
                <span
                  v-if="pago.estado_vencimiento === 'vencido'"
                  class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-500/15 text-rose-300 border border-rose-500/30 flex items-center space-x-1"
                >
                  <i class="pi pi-exclamation-triangle text-[10px]"></i>
                  <span>Vencido hace {{ Math.abs(pago.dias_restantes ?? 0) }}d</span>
                </span>
                <span
                  v-else-if="pago.estado_vencimiento === 'prox7'"
                  class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-500/15 text-amber-300 border border-amber-500/30 flex items-center space-x-1"
                >
                  <i class="pi pi-clock text-[10px]"></i>
                  <span>Vence en {{ pago.dias_restantes }}d</span>
                </span>
                <span
                  v-else
                  class="px-2.5 py-1 rounded-full text-[11px] font-medium bg-slate-900 text-slate-300 border border-slate-800"
                >
                  {{ pago.fecha_limite || 'Sin fecha' }}
                </span>
              </div>
            </td>

            <!-- Acciones -->
            <td class="py-3.5 px-4 text-center">
              <div class="flex items-center justify-center space-x-1.5">
                <a
                  v-if="pago.cliente_telefono"
                  :href="formatWhatsAppLink(pago.cliente_telefono, pago.cliente_nombre, pago.monto, pago.concepto)"
                  target="_blank"
                  rel="noopener"
                  class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500 hover:text-white border border-emerald-500/30 flex items-center justify-center transition-colors"
                  title="Enviar WhatsApp de Cobro"
                >
                  <i class="pi pi-whatsapp text-xs"></i>
                </a>
                <a
                  v-if="pago.cliente_correo"
                  :href="`mailto:${pago.cliente_correo}?subject=Aviso de Renovación - ${pago.concepto}&body=Estimado ${pago.cliente_nombre}, le informamos sobre el vencimiento de su servicio por un monto de ${pago.monto} ${pago.currency}.`"
                  class="w-8 h-8 rounded-xl bg-blue-500/10 text-blue-400 hover:bg-blue-500 hover:text-white border border-blue-500/30 flex items-center justify-center transition-colors"
                  title="Enviar Correo"
                >
                  <i class="pi pi-envelope text-xs"></i>
                </a>
                <button
                  @click="emit('copy-details', pago)"
                  class="w-8 h-8 rounded-xl bg-slate-800/80 text-slate-400 hover:bg-slate-700 hover:text-white border border-slate-700 flex items-center justify-center transition-colors"
                  title="Copiar Datos de Cobranza"
                >
                  <i class="pi pi-copy text-xs"></i>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
