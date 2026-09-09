<script setup lang="ts">
import type { DominioStats } from '@/api/dominios'

defineProps<{
  stats: DominioStats
  activeFilter: string
}>()

const emit = defineEmits<{
  (e: 'select-filter', filter: string): void
}>()
</script>

<template>
  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4">
    <!-- Total -->
    <div
      @click="emit('select-filter', 'todos')"
      :class="[
        'p-4 rounded-2xl border transition-all duration-150 cursor-pointer select-none group relative overflow-hidden',
        activeFilter === 'todos'
          ? 'bg-gradient-to-br from-blue-900/30 via-slate-900 to-slate-900 border-blue-500/60 shadow-lg shadow-blue-500/10'
          : 'bg-[#0D121F]/90 border-slate-800/80 hover:border-slate-700 hover:bg-[#111728]'
      ]"
    >
      <div class="flex items-center justify-between">
        <span class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Total Dominios</span>
        <div class="w-7 h-7 rounded-lg bg-blue-500/10 text-blue-400 flex items-center justify-center">
          <i class="pi pi-globe text-xs"></i>
        </div>
      </div>
      <div class="mt-2.5 text-2xl font-black text-white tracking-tight">{{ stats.total }}</div>
      <div class="mt-1 text-[10px] text-slate-500 font-medium">Registrados en Suite</div>
    </div>

    <!-- Activos -->
    <div
      @click="emit('select-filter', 'activos')"
      :class="[
        'p-4 rounded-2xl border transition-all duration-150 cursor-pointer select-none group relative overflow-hidden',
        activeFilter === 'activos'
          ? 'bg-gradient-to-br from-emerald-950/40 via-slate-900 to-slate-900 border-emerald-500/60 shadow-lg shadow-emerald-500/10'
          : 'bg-[#0D121F]/90 border-slate-800/80 hover:border-emerald-500/30 hover:bg-[#111728]'
      ]"
    >
      <div class="flex items-center justify-between">
        <span class="text-[11px] text-emerald-400 font-semibold uppercase tracking-wider">Activos</span>
        <div class="w-7 h-7 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
          <i class="pi pi-check-circle text-xs"></i>
        </div>
      </div>
      <div class="mt-2.5 text-2xl font-black text-emerald-300 tracking-tight">{{ stats.activos }}</div>
      <div class="mt-1 text-[10px] text-emerald-400/60 font-medium">En servicio online</div>
    </div>

    <!-- Por Vencer (30 días) -->
    <div
      @click="emit('select-filter', 'por_vencer')"
      :class="[
        'p-4 rounded-2xl border transition-all duration-150 cursor-pointer select-none group relative overflow-hidden',
        activeFilter === 'por_vencer'
          ? 'bg-gradient-to-br from-amber-950/40 via-slate-900 to-slate-900 border-amber-500/60 shadow-lg shadow-amber-500/10'
          : 'bg-[#0D121F]/90 border-slate-800/80 hover:border-amber-500/30 hover:bg-[#111728]'
      ]"
    >
      <div class="flex items-center justify-between">
        <span class="text-[11px] text-amber-400 font-semibold uppercase tracking-wider">Próx. 30 Días</span>
        <div class="w-7 h-7 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center">
          <i class="pi pi-calendar-plus text-xs"></i>
        </div>
      </div>
      <div class="mt-2.5 text-2xl font-black text-amber-300 tracking-tight">{{ stats.por_vencer_30d }}</div>
      <div class="mt-1 text-[10px] text-amber-400/70 font-medium">Por renovar</div>
    </div>

    <!-- Vencidos -->
    <div
      @click="emit('select-filter', 'vencidos')"
      :class="[
        'p-4 rounded-2xl border transition-all duration-150 cursor-pointer select-none group relative overflow-hidden',
        activeFilter === 'vencidos'
          ? 'bg-gradient-to-br from-rose-950/40 via-slate-900 to-slate-900 border-rose-500/60 shadow-lg shadow-rose-500/10'
          : 'bg-[#0D121F]/90 border-slate-800/80 hover:border-rose-500/30 hover:bg-[#111728]'
      ]"
    >
      <div class="flex items-center justify-between">
        <span class="text-[11px] text-rose-400 font-semibold uppercase tracking-wider">Expirados</span>
        <div class="w-7 h-7 rounded-lg bg-rose-500/10 text-rose-400 flex items-center justify-center">
          <i class="pi pi-exclamation-triangle text-xs"></i>
        </div>
      </div>
      <div class="mt-2.5 text-2xl font-black text-rose-300 tracking-tight">{{ stats.vencidos }}</div>
      <div class="mt-1 text-[10px] text-rose-400/60 font-medium">Requieren rescate</div>
    </div>

    <!-- Pendientes de Pago -->
    <div
      @click="emit('select-filter', 'pendientes_pago')"
      :class="[
        'col-span-2 sm:col-span-1 p-4 rounded-2xl border transition-all duration-150 cursor-pointer select-none group relative overflow-hidden',
        activeFilter === 'pendientes_pago'
          ? 'bg-gradient-to-br from-indigo-950/40 via-slate-900 to-slate-900 border-indigo-500/60 shadow-lg shadow-indigo-500/10'
          : 'bg-[#0D121F]/90 border-slate-800/80 hover:border-indigo-500/30 hover:bg-[#111728]'
      ]"
    >
      <div class="flex items-center justify-between">
        <span class="text-[11px] text-indigo-400 font-semibold uppercase tracking-wider">Pago Pendiente</span>
        <div class="w-7 h-7 rounded-lg bg-indigo-500/10 text-indigo-400 flex items-center justify-center">
          <i class="pi pi-clock text-xs"></i>
        </div>
      </div>
      <div class="mt-2.5 text-2xl font-black text-indigo-300 tracking-tight">{{ stats.pendientes_pago }}</div>
      <div class="mt-1 text-[10px] text-indigo-400/60 font-medium">Por conciliar</div>
    </div>
  </div>
</template>
