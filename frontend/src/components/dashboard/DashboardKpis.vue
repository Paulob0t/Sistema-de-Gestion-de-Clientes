<script setup lang="ts">
import type { DashboardKPIs } from '@/api/dashboard'

defineProps<{
  kpis: DashboardKPIs
  formatCurrency: (val: number, cur?: string) => string
}>()
</script>

<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
    <!-- Facturación Mensual -->
    <div class="relative overflow-hidden p-5 sm:p-6 rounded-3xl bg-[#0D121F]/90 border border-slate-800/80 shadow-xl group hover:border-emerald-500/30 transition-all duration-150">
      <div class="flex items-center justify-between">
        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Facturado este Mes</span>
        <div class="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center justify-center">
          <i class="pi pi-wallet text-sm"></i>
        </div>
      </div>
      <div class="mt-3.5">
        <div class="text-2xl sm:text-3xl font-black text-white tracking-tight">
          {{ formatCurrency(kpis.total_pagados_mes_monto) }}
        </div>
        <div class="mt-1 flex items-center text-xs text-emerald-400 font-semibold">
          <i class="pi pi-arrow-up-right mr-1 text-[10px]"></i>
          <span>{{ kpis.total_pagados_mes_count }} cobros conciliados</span>
        </div>
      </div>
    </div>

    <!-- Por Cobrar / Pendiente -->
    <div class="relative overflow-hidden p-5 sm:p-6 rounded-3xl bg-[#0D121F]/90 border border-slate-800/80 shadow-xl group hover:border-amber-500/30 transition-all duration-150">
      <div class="flex items-center justify-between">
        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Por Cobrar (Pendiente)</span>
        <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/20 flex items-center justify-center">
          <i class="pi pi-clock text-sm"></i>
        </div>
      </div>
      <div class="mt-3.5">
        <div class="text-2xl sm:text-3xl font-black text-amber-300 tracking-tight">
          {{ formatCurrency(kpis.total_pendientes_monto) }}
        </div>
        <div class="mt-1 flex items-center text-xs text-amber-400/80 font-semibold">
          <i class="pi pi-exclamation-circle mr-1 text-[10px]"></i>
          <span>{{ kpis.total_pendientes_count }} facturas activas</span>
        </div>
      </div>
    </div>

    <!-- Clientes Activos -->
    <div class="relative overflow-hidden p-5 sm:p-6 rounded-3xl bg-[#0D121F]/90 border border-slate-800/80 shadow-xl group hover:border-blue-500/30 transition-all duration-150">
      <div class="flex items-center justify-between">
        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Clientes Totales</span>
        <div class="w-9 h-9 rounded-xl bg-blue-500/10 text-blue-400 border border-blue-500/20 flex items-center justify-center">
          <i class="pi pi-users text-sm"></i>
        </div>
      </div>
      <div class="mt-3.5">
        <div class="text-2xl sm:text-3xl font-black text-white tracking-tight">
          {{ kpis.total_clientes }}
        </div>
        <div class="mt-1 flex items-center text-xs text-blue-400 font-semibold">
          <i class="pi pi-check-circle mr-1 text-[10px]"></i>
          <span>Base de datos activa</span>
        </div>
      </div>
    </div>

    <!-- Vencimientos Próximos -->
    <div class="relative overflow-hidden p-5 sm:p-6 rounded-3xl bg-[#0D121F]/90 border border-slate-800/80 shadow-xl group hover:border-rose-500/30 transition-all duration-150">
      <div class="flex items-center justify-between">
        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Vencimientos (7 días)</span>
        <div class="w-9 h-9 rounded-xl bg-rose-500/10 text-rose-400 border border-rose-500/20 flex items-center justify-center">
          <i class="pi pi-calendar-times text-sm"></i>
        </div>
      </div>
      <div class="mt-3.5">
        <div class="text-2xl sm:text-3xl font-black text-rose-300 tracking-tight">
          {{ kpis.prox_7_dias_count }}
        </div>
        <div class="mt-1 flex items-center text-xs text-rose-400 font-semibold">
          <i class="pi pi-bell mr-1 text-[10px]"></i>
          <span>Requieren contacto</span>
        </div>
      </div>
    </div>
  </div>
</template>
