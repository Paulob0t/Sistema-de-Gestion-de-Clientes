<script setup lang="ts">
import type { SolicitudesKpis } from '@/api/solicitudes'

defineProps<{
  kpis: SolicitudesKpis
  loading: boolean
  isAgente: boolean
}>()
</script>

<template>
  <div class="grid grid-cols-2 lg:grid-cols-5 gap-3.5">
    <!-- Asignadas a Mí (Si es agente) o Total -->
    <div
      v-if="isAgente"
      class="p-4 rounded-2xl bg-[#0D1527]/80 border border-blue-500/30 shadow-lg relative overflow-hidden group hover:border-blue-500/50 transition-colors"
    >
      <div class="flex items-center justify-between">
        <div>
          <span class="text-[11px] font-bold text-blue-400 uppercase tracking-wider">Mis Asignadas</span>
          <div class="text-2xl font-black text-white mt-1">
            <span v-if="loading" class="animate-pulse text-slate-600">---</span>
            <span v-else>{{ kpis.asignadas_a_mi }}</span>
          </div>
        </div>
        <div class="w-10 h-10 rounded-xl bg-blue-500/15 text-blue-400 border border-blue-500/30 flex items-center justify-center text-base shadow-inner">
          <i class="pi pi-user-check"></i>
        </div>
      </div>
      <div class="mt-2 text-[11px] text-slate-400">
        Pendientes de resolución
      </div>
    </div>

    <!-- Total General -->
    <div class="p-4 rounded-2xl bg-[#0D1527]/80 border border-slate-800/80 shadow-lg relative overflow-hidden group hover:border-slate-700 transition-colors">
      <div class="flex items-center justify-between">
        <div>
          <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Tickets</span>
          <div class="text-2xl font-black text-white mt-1">
            <span v-if="loading" class="animate-pulse text-slate-600">---</span>
            <span v-else>{{ kpis.total }}</span>
          </div>
        </div>
        <div class="w-10 h-10 rounded-xl bg-slate-800 text-slate-300 border border-slate-700 flex items-center justify-center text-base shadow-inner">
          <i class="pi pi-ticket"></i>
        </div>
      </div>
      <div class="mt-2 text-[11px] text-slate-400">
        Registros históricos
      </div>
    </div>

    <!-- Pendientes -->
    <div class="p-4 rounded-2xl bg-[#0D1527]/80 border border-amber-500/20 shadow-lg relative overflow-hidden group hover:border-amber-500/40 transition-colors">
      <div class="flex items-center justify-between">
        <div>
          <span class="text-[11px] font-bold text-amber-400 uppercase tracking-wider">Pendientes</span>
          <div class="text-2xl font-black text-amber-400 mt-1">
            <span v-if="loading" class="animate-pulse text-slate-600">---</span>
            <span v-else>{{ kpis.pendientes }}</span>
          </div>
        </div>
        <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/20 flex items-center justify-center text-base shadow-inner">
          <i class="pi pi-clock"></i>
        </div>
      </div>
      <div class="mt-2 text-[11px] text-slate-400">
        Sin iniciar aún
      </div>
    </div>

    <!-- En Proceso -->
    <div class="p-4 rounded-2xl bg-[#0D1527]/80 border border-cyan-500/20 shadow-lg relative overflow-hidden group hover:border-cyan-500/40 transition-colors">
      <div class="flex items-center justify-between">
        <div>
          <span class="text-[11px] font-bold text-cyan-400 uppercase tracking-wider">En Proceso</span>
          <div class="text-2xl font-black text-cyan-400 mt-1">
            <span v-if="loading" class="animate-pulse text-slate-600">---</span>
            <span v-else>{{ kpis.en_proceso }}</span>
          </div>
        </div>
        <div class="w-10 h-10 rounded-xl bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 flex items-center justify-center text-base shadow-inner">
          <i class="pi pi-sync"></i>
        </div>
      </div>
      <div class="mt-2 text-[11px] text-slate-400">
        En desarrollo activo
      </div>
    </div>

    <!-- Finalizadas -->
    <div class="p-4 rounded-2xl bg-[#0D1527]/80 border border-emerald-500/20 shadow-lg relative overflow-hidden group hover:border-emerald-500/40 transition-colors">
      <div class="flex items-center justify-between">
        <div>
          <span class="text-[11px] font-bold text-emerald-400 uppercase tracking-wider">Finalizadas</span>
          <div class="text-2xl font-black text-emerald-400 mt-1">
            <span v-if="loading" class="animate-pulse text-slate-600">---</span>
            <span v-else>{{ kpis.finalizadas }}</span>
          </div>
        </div>
        <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center justify-center text-base shadow-inner">
          <i class="pi pi-check-circle"></i>
        </div>
      </div>
      <div class="mt-2 text-[11px] text-slate-400">
        Entregadas con éxito
      </div>
    </div>
  </div>
</template>
