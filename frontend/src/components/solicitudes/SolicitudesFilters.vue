<script setup lang="ts">
import type { AgenteSimple } from '@/api/solicitudes'

defineProps<{
  search: string
  selectedEstado: string
  selectedPrioridad: string
  selectedAgenteId: number | null
  soloMias: boolean
  agentes: AgenteSimple[]
  isAgente: boolean
}>()

const emit = defineEmits<{
  (e: 'update:search', val: string): void
  (e: 'update:selectedEstado', val: string): void
  (e: 'update:selectedPrioridad', val: string): void
  (e: 'update:selectedAgenteId', val: number | null): void
  (e: 'update:soloMias', val: boolean): void
  (e: 'new-solicitud'): void
  (e: 'refresh'): void
}>()

const estados = [
  { label: 'Todos', value: 'todos' },
  { label: 'Pendientes', value: 'Pendiente' },
  { label: 'En Proceso', value: 'En Proceso' },
  { label: 'Finalizadas', value: 'Finalizado' },
]

const prioridades = [
  { label: 'Todas las Prioridades', value: 'todos' },
  { label: 'Alta', value: 'Alta' },
  { label: 'Media', value: 'Media' },
  { label: 'Baja', value: 'Baja' },
]
</script>

<template>
  <div class="space-y-3.5">
    <!-- Barra Superior: Búsqueda, Filtro Agente y Botón Nuevo -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
      <!-- Input de búsqueda -->
      <div class="relative flex-1 max-w-md">
        <i class="pi pi-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-sm"></i>
        <input
          :value="search"
          @input="emit('update:search', ($event.target as HTMLInputElement).value)"
          type="text"
          placeholder="Buscar solicitud por título, descripción o cliente..."
          class="w-full bg-[#0D1527] border border-slate-800 rounded-xl pl-10 pr-4 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors"
        />
        <button
          v-if="search"
          @click="emit('update:search', '')"
          class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-white"
        >
          <i class="pi pi-times text-xs"></i>
        </button>
      </div>

      <!-- Controles y Botones -->
      <div class="flex flex-wrap items-center gap-2.5">
        <!-- Toggle Solo Mis Asignadas (Para Agentes) -->
        <button
          v-if="isAgente"
          @click="emit('update:soloMias', !soloMias)"
          :class="[
            'px-3 py-2 rounded-xl border text-xs font-semibold flex items-center space-x-2 transition-all',
            soloMias
              ? 'bg-blue-600/20 border-blue-500 text-blue-400 font-bold'
              : 'bg-slate-900 border-slate-800 text-slate-400 hover:text-white'
          ]"
        >
          <i :class="['pi', soloMias ? 'pi-check-circle text-blue-400' : 'pi-circle text-slate-500', 'text-xs']"></i>
          <span>Solo Mis Asignadas</span>
        </button>

        <!-- Selector de Agente (Para Admins o cuando no está en solo mías) -->
        <select
          v-if="!soloMias"
          :value="selectedAgenteId ?? ''"
          @change="emit('update:selectedAgenteId', ($event.target as HTMLSelectElement).value ? Number(($event.target as HTMLSelectElement).value) : null)"
          class="bg-[#0D1527] border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-300 focus:outline-none focus:border-blue-500"
        >
          <option value="">Todos los Agentes</option>
          <option v-for="a in agentes" :key="a.id" :value="a.id">
            {{ a.nombre }}
          </option>
        </select>

        <!-- Recargar -->
        <button
          @click="emit('refresh')"
          class="p-2 rounded-xl bg-slate-900 border border-slate-800 text-slate-400 hover:text-white hover:border-slate-700 transition-colors"
          title="Actualizar lista"
        >
          <i class="pi pi-refresh text-xs"></i>
        </button>

        <!-- Botón Nueva Solicitud -->
        <button
          @click="emit('new-solicitud')"
          class="px-4 py-2 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white text-xs font-bold shadow-lg shadow-blue-500/25 flex items-center space-x-2 transition-all transform active:scale-95"
        >
          <i class="pi pi-plus text-xs"></i>
          <span>Nueva Solicitud</span>
        </button>
      </div>
    </div>

    <!-- Pestañas de Estado y Prioridad -->
    <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-slate-800/60">
      <!-- Tabs de Estado -->
      <div class="flex flex-wrap items-center gap-1.5 p-1 rounded-xl bg-[#090E1A] border border-slate-800/80">
        <button
          v-for="e in estados"
          :key="e.value"
          @click="emit('update:selectedEstado', e.value)"
          :class="[
            'px-3 py-1.5 rounded-lg text-xs font-medium transition-colors',
            selectedEstado === e.value
              ? 'bg-blue-600 text-white font-semibold shadow-sm'
              : 'text-slate-400 hover:text-white hover:bg-slate-800/60'
          ]"
        >
          {{ e.label }}
        </button>
      </div>

      <!-- Selector / Pills de Prioridad -->
      <div class="flex items-center space-x-1.5">
        <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider mr-1">Prioridad:</span>
        <button
          v-for="p in prioridades"
          :key="p.value"
          @click="emit('update:selectedPrioridad', p.value)"
          :class="[
            'px-2.5 py-1 rounded-lg text-[11px] font-medium border transition-colors',
            selectedPrioridad === p.value
              ? 'bg-slate-800 text-white border-slate-600 font-bold'
              : 'bg-[#0D1527] text-slate-400 border-slate-800/80 hover:border-slate-700 hover:text-slate-200'
          ]"
        >
          {{ p.label }}
        </button>
      </div>
    </div>
  </div>
</template>
