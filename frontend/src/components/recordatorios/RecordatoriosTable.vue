<script setup lang="ts">
import type { RecordatorioItem } from '@/api/recordatorios'

defineProps<{
  items: RecordatorioItem[]
  loading: boolean
  selectedIds: string[]
}>()

const emit = defineEmits<{
  (e: 'toggle-select', id: string): void
  (e: 'toggle-select-all'): void
  (e: 'preview', item: RecordatorioItem): void
  (e: 'send-direct', item: RecordatorioItem): void
}>()

function getUrgenciaBadge(estado: string, dias: number) {
  if (estado === 'vencido') {
    return {
      label: `Venció hace ${Math.abs(dias)}d`,
      classes: 'bg-rose-500/15 text-rose-400 border-rose-500/30 font-bold',
      dotClass: 'bg-rose-400'
    }
  }
  if (estado === 'hoy') {
    return {
      label: 'Vence Hoy',
      classes: 'bg-rose-500/20 text-rose-300 border-rose-500/40 font-bold animate-pulse',
      dotClass: 'bg-rose-400'
    }
  }
  if (estado === 'critico_3d') {
    return {
      label: `${dias} días restantes`,
      classes: 'bg-orange-500/15 text-orange-400 border-orange-500/30 font-semibold',
      dotClass: 'bg-orange-400'
    }
  }
  if (estado === 'proximo_7d') {
    return {
      label: `${dias} días restantes`,
      classes: 'bg-amber-500/15 text-amber-400 border-amber-500/30',
      dotClass: 'bg-amber-400'
    }
  }
  return {
    label: `${dias} días restantes`,
    classes: 'bg-slate-800/60 text-slate-400 border-slate-700/60',
    dotClass: 'bg-slate-400'
  }
}

function getTipoBadge(tipo: string) {
  switch (tipo) {
    case 'pago':
      return { label: 'Cobro / Pago', icon: 'pi-credit-card', classes: 'bg-blue-500/10 text-blue-400 border-blue-500/20' }
    case 'dominio':
      return { label: 'Dominio', icon: 'pi-globe', classes: 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20' }
    case 'hosting':
      return { label: 'Hosting', icon: 'pi-server', classes: 'bg-amber-500/10 text-amber-400 border-amber-500/20' }
    default:
      return { label: tipo, icon: 'pi-tag', classes: 'bg-slate-500/10 text-slate-400 border-slate-500/20' }
  }
}
</script>

<template>
  <div class="rounded-2xl bg-[#0D1527]/90 border border-slate-800/80 shadow-xl overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="border-b border-slate-800 bg-[#090E1A]/80 text-[11px] font-bold text-slate-400 uppercase tracking-wider select-none">
            <th class="p-3.5 w-10 text-center">
              <input
                type="checkbox"
                :checked="items.length > 0 && selectedIds.length === items.length"
                @change="emit('toggle-select-all')"
                class="rounded border-slate-700 bg-slate-900 text-blue-600 focus:ring-blue-500"
              />
            </th>
            <th class="p-3.5">Cliente</th>
            <th class="p-3.5">Servicio / Concepto</th>
            <th class="p-3.5">Monto</th>
            <th class="p-3.5">Vencimiento</th>
            <th class="p-3.5">Urgencia</th>
            <th class="p-3.5 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-800/60 text-xs text-slate-300">
          <tr v-if="loading">
            <td colspan="7" class="p-8 text-center text-slate-500">
              <i class="pi pi-spin pi-spinner text-2xl text-blue-500 mb-2"></i>
              <div class="text-xs">Cargando recordatorios pendientes...</div>
            </td>
          </tr>

          <tr v-else-if="items.length === 0">
            <td colspan="7" class="p-12 text-center text-slate-500">
              <i class="pi pi-check-circle text-3xl text-emerald-500/70 mb-2"></i>
              <div class="text-sm font-semibold text-slate-300">¡Todo al día!</div>
              <p class="text-xs text-slate-500 mt-1">No hay servicios o cobros pendientes que requieran recordatorio con los filtros actuales.</p>
            </td>
          </tr>

          <tr
            v-for="item in items"
            :key="item.id"
            :class="[
              'hover:bg-slate-800/30 transition-colors group',
              selectedIds.includes(item.id) ? 'bg-blue-950/20' : ''
            ]"
          >
            <!-- Checkbox -->
            <td class="p-3.5 text-center">
              <input
                type="checkbox"
                :checked="selectedIds.includes(item.id)"
                @change="emit('toggle-select', item.id)"
                class="rounded border-slate-700 bg-slate-900 text-blue-600 focus:ring-blue-500"
              />
            </td>

            <!-- Cliente -->
            <td class="p-3.5">
              <div class="font-semibold text-white group-hover:text-blue-400 transition-colors">
                {{ item.cliente_nombre }}
              </div>
              <div class="text-[11px] text-slate-400 flex items-center space-x-1.5 mt-0.5">
                <i class="pi pi-envelope text-[10px]" :class="item.cliente_correo ? 'text-slate-400' : 'text-rose-400'"></i>
                <span v-if="item.cliente_correo" class="truncate max-w-[180px]">{{ item.cliente_correo }}</span>
                <span v-else class="text-rose-400 italic">Sin correo registrado</span>
              </div>
            </td>

            <!-- Servicio / Concepto -->
            <td class="p-3.5">
              <div class="flex items-center space-x-2">
                <span
                  :class="[
                    'px-2 py-0.5 rounded-md text-[10px] font-semibold border inline-flex items-center space-x-1 shrink-0',
                    getTipoBadge(item.tipo_servicio).classes
                  ]"
                >
                  <i :class="['pi', getTipoBadge(item.tipo_servicio).icon, 'text-[9px]']"></i>
                  <span>{{ getTipoBadge(item.tipo_servicio).label }}</span>
                </span>
                <span class="font-medium text-slate-200 truncate max-w-[200px]" :title="item.concepto">
                  {{ item.concepto }}
                </span>
              </div>
              <div v-if="item.detalles_servicio" class="text-[11px] text-slate-500 mt-0.5">
                {{ item.detalles_servicio }}
              </div>
            </td>

            <!-- Monto -->
            <td class="p-3.5 whitespace-nowrap">
              <span class="font-bold text-white">${{ item.monto.toLocaleString('es-MX', { minimumFractionDigits: 2 }) }}</span>
              <span class="text-[10px] text-slate-400 ml-1 font-semibold">{{ item.currency }}</span>
            </td>

            <!-- Vencimiento -->
            <td class="p-3.5 whitespace-nowrap">
              <div class="font-medium text-slate-300">
                {{ item.fecha_vencimiento || 'Sin fecha' }}
              </div>
            </td>

            <!-- Urgencia -->
            <td class="p-3.5 whitespace-nowrap">
              <span
                :class="[
                  'px-2.5 py-1 rounded-full text-[11px] border inline-flex items-center space-x-1.5',
                  getUrgenciaBadge(item.estado_vencimiento, item.dias_restantes).classes
                ]"
              >
                <span class="w-1.5 h-1.5 rounded-full" :class="getUrgenciaBadge(item.estado_vencimiento, item.dias_restantes).dotClass"></span>
                <span>{{ getUrgenciaBadge(item.estado_vencimiento, item.dias_restantes).label }}</span>
              </span>
            </td>

            <!-- Acciones -->
            <td class="p-3.5 text-right whitespace-nowrap">
              <div class="flex items-center justify-end space-x-1.5">
                <!-- Vista Previa -->
                <button
                  @click="emit('preview', item)"
                  class="px-2.5 py-1.5 rounded-lg bg-slate-900 border border-slate-700/80 hover:border-slate-600 text-slate-300 hover:text-white flex items-center space-x-1 text-[11px] transition-colors"
                  title="Ver plantilla de correo y previsualizar"
                >
                  <i class="pi pi-eye text-xs text-cyan-400"></i>
                  <span class="hidden lg:inline">Vista Previa</span>
                </button>

                <!-- Enviar Directo -->
                <button
                  @click="emit('send-direct', item)"
                  :disabled="!item.cliente_correo"
                  :class="[
                    'px-2.5 py-1.5 rounded-lg border text-[11px] font-semibold flex items-center space-x-1 transition-colors',
                    item.cliente_correo
                      ? 'bg-blue-600/20 border-blue-500/40 text-blue-400 hover:bg-blue-600 hover:text-white'
                      : 'bg-slate-900 border-slate-800 text-slate-600 cursor-not-allowed'
                  ]"
                  :title="item.cliente_correo ? 'Enviar correo inmediatamente' : 'Cliente sin correo'"
                >
                  <i class="pi pi-send text-xs"></i>
                  <span>Enviar</span>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
