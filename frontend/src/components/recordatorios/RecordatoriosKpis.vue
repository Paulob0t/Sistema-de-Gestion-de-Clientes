<script setup lang="ts">
import type { RecordatoriosKpis } from '@/api/recordatorios'

defineProps<{
  kpis: RecordatoriosKpis
  loading: boolean
}>()
</script>

<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <!-- Total Pendientes -->
    <div class="p-4 rounded-2xl bg-[#0D1527]/80 border border-slate-800/80 shadow-lg relative overflow-hidden group hover:border-slate-700 transition-colors">
      <div class="flex items-center justify-between">
        <div>
          <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Pendientes</span>
          <div class="text-2xl font-black text-white mt-1">
            <span v-if="loading" class="animate-pulse text-slate-600">---</span>
            <span v-else>{{ kpis.total_pendientes }}</span>
          </div>
        </div>
        <div class="w-11 h-11 rounded-xl bg-blue-500/10 text-blue-400 border border-blue-500/20 flex items-center justify-center text-lg shadow-inner">
          <i class="pi pi-inbox"></i>
        </div>
      </div>
      <div class="mt-3 flex items-center text-xs text-slate-400">
        <span class="text-blue-400 font-semibold mr-1.5">{{ kpis.con_correo_valido }}</span>
        <span>con correo registrado</span>
      </div>
    </div>

    <!-- Vencidos Críticos -->
    <div class="p-4 rounded-2xl bg-[#0D1527]/80 border border-rose-500/20 shadow-lg relative overflow-hidden group hover:border-rose-500/40 transition-colors">
      <div class="flex items-center justify-between">
        <div>
          <span class="text-xs font-semibold text-rose-400 uppercase tracking-wider">Vencidos / Vencen Hoy</span>
          <div class="text-2xl font-black text-rose-400 mt-1">
            <span v-if="loading" class="animate-pulse text-slate-600">---</span>
            <span v-else>{{ kpis.vencidos }}</span>
          </div>
        </div>
        <div class="w-11 h-11 rounded-xl bg-rose-500/10 text-rose-400 border border-rose-500/20 flex items-center justify-center text-lg shadow-inner">
          <i class="pi pi-exclamation-triangle"></i>
        </div>
      </div>
      <div class="mt-3 flex items-center text-xs text-slate-400">
        <span class="text-rose-400 font-bold mr-1.5">Urgente</span>
        <span>requieren recordatorio inmediato</span>
      </div>
    </div>

    <!-- Próximos 7 Días -->
    <div class="p-4 rounded-2xl bg-[#0D1527]/80 border border-amber-500/20 shadow-lg relative overflow-hidden group hover:border-amber-500/40 transition-colors">
      <div class="flex items-center justify-between">
        <div>
          <span class="text-xs font-semibold text-amber-400 uppercase tracking-wider">Próximos 7 Días</span>
          <div class="text-2xl font-black text-amber-400 mt-1">
            <span v-if="loading" class="animate-pulse text-slate-600">---</span>
            <span v-else>{{ kpis.proximos_7_dias }}</span>
          </div>
        </div>
        <div class="w-11 h-11 rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/20 flex items-center justify-center text-lg shadow-inner">
          <i class="pi pi-calendar-times"></i>
        </div>
      </div>
      <div class="mt-3 flex items-center text-xs text-slate-400">
        <span class="text-amber-400 font-semibold mr-1.5">Preventivo</span>
        <span>vencimiento en menos de una semana</span>
      </div>
    </div>

    <!-- Importe Total Pendiente -->
    <div class="p-4 rounded-2xl bg-[#0D1527]/80 border border-emerald-500/20 shadow-lg relative overflow-hidden group hover:border-emerald-500/40 transition-colors">
      <div class="flex items-center justify-between">
        <div>
          <span class="text-xs font-semibold text-emerald-400 uppercase tracking-wider">Monto Total por Cobrar</span>
          <div class="text-2xl font-black text-emerald-400 mt-1">
            <span v-if="loading" class="animate-pulse text-slate-600">---</span>
            <span v-else>${{ kpis.monto_total_pendiente_mxn.toLocaleString('es-MX', { minimumFractionDigits: 2 }) }}</span>
          </div>
        </div>
        <div class="w-11 h-11 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center justify-center text-lg shadow-inner">
          <i class="pi pi-dollar"></i>
        </div>
      </div>
      <div class="mt-3 flex items-center text-xs text-slate-400">
        <span class="text-emerald-400 font-semibold mr-1.5">MXN</span>
        <span v-if="kpis.monto_total_pendiente_usd > 0">+ ${{ kpis.monto_total_pendiente_usd.toLocaleString('es-MX', { minimumFractionDigits: 2 }) }} USD</span>
        <span v-else>en servicios y cobros pendientes</span>
      </div>
    </div>
  </div>
</template>
