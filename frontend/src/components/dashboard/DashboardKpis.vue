<script setup lang="ts">
import type { DashboardKPIs } from '@/api/dashboard'

defineProps<{
  kpis: DashboardKPIs
  formatCurrency: (val: number, cur?: string) => string
}>()
</script>

<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
    <!-- Facturación Mensual -->
    <div class="relative overflow-hidden p-6 rounded-3xl bg-slate-900/70 border border-slate-800/80 shadow-xl group hover:border-slate-700 transition-all">
      <div class="flex items-center justify-between">
        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Facturado este Mes</span>
        <div class="w-10 h-10 rounded-2xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center justify-center">
          <i class="pi pi-wallet text-sm"></i>
        </div>
      </div>
      <div class="mt-4">
        <div class="text-2xl sm:text-3xl font-bold text-white tracking-tight">
          {{ formatCurrency(kpis.total_pagados_mes_monto) }}
        </div>
        <div class="mt-1 flex items-center text-xs text-emerald-400 font-medium">
          <i class="pi pi-arrow-up-right mr-1"></i>
          <span>{{ kpis.total_pagados_mes_count }} cobros realizados</span>
        </div>
      </div>
    </div>

    <!-- Por Cobrar / Pendiente -->
    <div class="relative overflow-hidden p-6 rounded-3xl bg-slate-900/70 border border-slate-800/80 shadow-xl group hover:border-slate-700 transition-all">
      <div class="flex items-center justify-between">
        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Por Cobrar (Pendiente)</span>
        <div class="w-10 h-10 rounded-2xl bg-amber-500/10 text-amber-400 border border-amber-500/20 flex items-center justify-center">
          <i class="pi pi-clock text-sm"></i>
        </div>
      </div>
      <div class="mt-4">
        <div class="text-2xl sm:text-3xl font-bold text-amber-300 tracking-tight">
          {{ formatCurrency(kpis.total_pendientes_monto) }}
        </div>
        <div class="mt-1 flex items-center text-xs text-amber-400/80 font-medium">
          <i class="pi pi-exclamation-circle mr-1"></i>
          <span>{{ kpis.total_pendientes_count }} facturas activas</span>
        </div>
      </div>
    </div>

    <!-- Clientes Activos -->
    <div class="relative overflow-hidden p-6 rounded-3xl bg-slate-900/70 border border-slate-800/80 shadow-xl group hover:border-slate-700 transition-all">
      <div class="flex items-center justify-between">
        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Clientes Totales</span>
        <div class="w-10 h-10 rounded-2xl bg-blue-500/10 text-blue-400 border border-blue-500/20 flex items-center justify-center">
          <i class="pi pi-users text-sm"></i>
        </div>
      </div>
      <div class="mt-4">
        <div class="text-2xl sm:text-3xl font-bold text-white tracking-tight">
          {{ kpis.total_clientes }}
        </div>
        <div class="mt-1 flex items-center text-xs text-blue-400 font-medium">
          <i class="pi pi-check-circle mr-1"></i>
          <span>En el sistema activo</span>
        </div>
      </div>
    </div>

    <!-- Vencimientos Próximos -->
    <div class="relative overflow-hidden p-6 rounded-3xl bg-slate-900/70 border border-slate-800/80 shadow-xl group hover:border-slate-700 transition-all">
      <div class="flex items-center justify-between">
        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Vencimientos (7 días)</span>
        <div class="w-10 h-10 rounded-2xl bg-rose-500/10 text-rose-400 border border-rose-500/20 flex items-center justify-center">
          <i class="pi pi-calendar-times text-sm"></i>
        </div>
      </div>
      <div class="mt-4">
        <div class="text-2xl sm:text-3xl font-bold text-rose-300 tracking-tight">
          {{ kpis.prox_7_dias_count }}
        </div>
        <div class="mt-1 flex items-center text-xs text-rose-400 font-medium">
          <i class="pi pi-bell mr-1"></i>
          <span>Requieren seguimiento</span>
        </div>
      </div>
    </div>
  </div>
</template>
