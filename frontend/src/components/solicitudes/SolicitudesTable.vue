<script setup lang="ts">
import type { SolicitudItem } from '@/api/solicitudes'

defineProps<{
  items: SolicitudItem[]
  loading: boolean
}>()

const emit = defineEmits<{
  (e: 'view-detail', item: SolicitudItem): void
  (e: 'edit', item: SolicitudItem): void
  (e: 'update-status', item: SolicitudItem, newStatus: string): void
  (e: 'delete', item: SolicitudItem): void
}>()

function getEstadoBadge(estado: string) {
  switch (estado) {
    case 'Pendiente':
      return { label: 'Pendiente', classes: 'bg-amber-500/15 text-amber-400 border-amber-500/30' }
    case 'En Proceso':
      return { label: 'En Proceso', classes: 'bg-cyan-500/15 text-cyan-400 border-cyan-500/30 font-bold animate-pulse' }
    case 'Finalizado':
      return { label: 'Finalizado', classes: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30' }
    default:
      return { label: estado, classes: 'bg-slate-800 text-slate-400 border-slate-700' }
  }
}

function getPrioridadBadge(prioridad: string) {
  switch (prioridad) {
    case 'Alta':
      return { label: 'Alta', classes: 'bg-rose-500/15 text-rose-400 border-rose-500/30 font-bold' }
    case 'Media':
      return { label: 'Media', classes: 'bg-blue-500/15 text-blue-400 border-blue-500/30' }
    case 'Baja':
      return { label: 'Baja', classes: 'bg-slate-800 text-slate-400 border-slate-700' }
    default:
      return { label: prioridad, classes: 'bg-slate-800 text-slate-400 border-slate-700' }
  }
}
</script>

<template>
  <div class="rounded-2xl bg-[#0D1527]/90 border border-slate-800/80 shadow-xl overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="border-b border-slate-800 bg-[#090E1A]/80 text-[11px] font-bold text-slate-400 uppercase tracking-wider select-none">
            <th class="p-3.5 w-16 text-center">ID</th>
            <th class="p-3.5">Título / Solicitud</th>
            <th class="p-3.5">Cliente</th>
            <th class="p-3.5">Agente(s) Asignado(s)</th>
            <th class="p-3.5">Prioridad</th>
            <th class="p-3.5">Estado</th>
            <th class="p-3.5">Fecha / Límite</th>
            <th class="p-3.5 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-800/60 text-xs text-slate-300">
          <tr v-if="loading">
            <td colspan="8" class="p-8 text-center text-slate-500">
              <i class="pi pi-spin pi-spinner text-2xl text-blue-500 mb-2"></i>
              <div class="text-xs">Cargando solicitudes...</div>
            </td>
          </tr>

          <tr v-else-if="items.length === 0">
            <td colspan="8" class="p-12 text-center text-slate-500">
              <i class="pi pi-inbox text-3xl text-slate-600 mb-2"></i>
              <div class="text-sm font-semibold text-slate-300">No se encontraron solicitudes</div>
              <p class="text-xs text-slate-500 mt-1">Prueba cambiando los filtros o crea una nueva solicitud.</p>
            </td>
          </tr>

          <tr
            v-for="item in items"
            :key="item.id"
            class="hover:bg-slate-800/30 transition-colors group"
          >
            <!-- ID -->
            <td class="p-3.5 text-center font-mono text-[11px] text-slate-500 font-bold">
              #{{ item.id }}
            </td>

            <!-- Título y descripción -->
            <td class="p-3.5 max-w-xs">
              <div
                @click="emit('view-detail', item)"
                class="font-semibold text-white group-hover:text-blue-400 cursor-pointer transition-colors truncate"
                :title="item.titulo"
              >
                {{ item.titulo }}
              </div>
              <div class="text-[11px] text-slate-500 truncate mt-0.5" :title="item.descripcion_texto">
                {{ item.descripcion_texto || 'Sin descripción adicional' }}
              </div>
              <!-- Indicador de notas adjuntas -->
              <div v-if="item.total_notas > 0" class="inline-flex items-center space-x-1 text-[10px] text-blue-400/80 mt-1">
                <i class="pi pi-comments text-[9px]"></i>
                <span>{{ item.total_notas }} {{ item.total_notas === 1 ? 'nota' : 'notas' }}</span>
              </div>
            </td>

            <!-- Cliente -->
            <td class="p-3.5 whitespace-nowrap">
              <div class="font-medium text-slate-200">
                {{ item.cliente_nombre || item.cliente_empresa || 'Cliente General' }}
              </div>
              <div v-if="item.cliente_empresa && item.cliente_nombre" class="text-[10px] text-slate-500">
                {{ item.cliente_empresa }}
              </div>
            </td>

            <!-- Agentes -->
            <td class="p-3.5">
              <div v-if="item.agentes && item.agentes.length > 0" class="flex flex-wrap gap-1">
                <span
                  v-for="ag in item.agentes"
                  :key="ag.id"
                  class="px-2 py-0.5 rounded-md bg-indigo-500/15 border border-indigo-500/30 text-indigo-300 text-[10px] font-medium"
                >
                  {{ ag.nombre }}
                </span>
              </div>
              <span v-else class="text-[11px] text-slate-500 italic">
                Sin asignar
              </span>
            </td>

            <!-- Prioridad -->
            <td class="p-3.5 whitespace-nowrap">
              <span
                :class="[
                  'px-2 py-0.5 rounded-full text-[10px] border font-semibold inline-block',
                  getPrioridadBadge(item.prioridad).classes
                ]"
              >
                {{ item.prioridad }}
              </span>
            </td>

            <!-- Estado con Selector Rápido -->
            <td class="p-3.5 whitespace-nowrap">
              <select
                :value="item.estado"
                @change="emit('update-status', item, ($event.target as HTMLSelectElement).value)"
                :class="[
                  'px-2.5 py-1 rounded-lg text-[11px] font-semibold border cursor-pointer focus:outline-none transition-colors',
                  getEstadoBadge(item.estado).classes,
                  'bg-[#0D1527]'
                ]"
              >
                <option value="Pendiente" class="bg-slate-900 text-amber-400 font-semibold">Pendiente</option>
                <option value="En Proceso" class="bg-slate-900 text-cyan-400 font-semibold">En Proceso</option>
                <option value="Finalizado" class="bg-slate-900 text-emerald-400 font-semibold">Finalizado</option>
              </select>
            </td>

            <!-- Fechas -->
            <td class="p-3.5 whitespace-nowrap">
              <div class="text-[11px] text-slate-300">
                {{ item.fecha_solicitud || '---' }}
              </div>
              <div v-if="item.fecha_lim" class="text-[10px] text-amber-400 mt-0.5 flex items-center space-x-1">
                <i class="pi pi-calendar text-[9px]"></i>
                <span>Límite: {{ item.fecha_lim }}</span>
              </div>
            </td>

            <!-- Acciones -->
            <td class="p-3.5 text-right whitespace-nowrap">
              <div class="flex items-center justify-end space-x-1.5">
                <!-- Ver Detalle / Notas -->
                <button
                  @click="emit('view-detail', item)"
                  class="p-1.5 rounded-lg bg-slate-900 border border-slate-800 text-slate-400 hover:text-cyan-400 hover:border-slate-700 transition-colors"
                  title="Ver detalle y comentarios"
                >
                  <i class="pi pi-eye text-xs"></i>
                </button>

                <!-- Editar -->
                <button
                  @click="emit('edit', item)"
                  class="p-1.5 rounded-lg bg-slate-900 border border-slate-800 text-slate-400 hover:text-blue-400 hover:border-slate-700 transition-colors"
                  title="Editar solicitud"
                >
                  <i class="pi pi-pencil text-xs"></i>
                </button>

                <!-- Eliminar -->
                <button
                  @click="emit('delete', item)"
                  class="p-1.5 rounded-lg bg-slate-900 border border-slate-800 text-slate-400 hover:text-rose-400 hover:border-slate-700 transition-colors"
                  title="Eliminar solicitud"
                >
                  <i class="pi pi-trash text-xs"></i>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
