<script setup lang="ts">
import type { PagoStats } from '@/api/pagos'

defineProps<{
  stats: PagoStats
  activeFilter: string
}>()

const emit = defineEmits<{
  (e: 'select-filter', filter: string): void
}>()

function formatCurrency(val: number, moneda = 'MXN') {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: moneda,
  }).format(val || 0)
}
</script>

<template>
  <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
    <!-- 1. Recaudación Total MXN -->
    <div
      @click="emit('select-filter', 'pagados')"
      :class="[
        'p-4 rounded-2xl border transition-all duration-150 cursor-pointer select-none group relative overflow-hidden',
        activeFilter === 'pagados'
          ? 'bg-gradient-to-br from-emerald-950/40 via-slate-900 to-slate-900 border-emerald-500/60 shadow-lg shadow-emerald-500/10'
          : 'bg-[#0D121F]/90 border-slate-800/80 hover:border-emerald-500/30 hover:bg-[#111728]'
      ]"
    >
      <div class="flex items-center justify-between">
        <span class="text-[11px] text-emerald-400 font-semibold uppercase tracking-wider">Cobrado (MXN)</span>
        <div class="w-7 h-7 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
          <i class="pi pi-check-circle text-xs"></i>
        </div>
      </div>
      <div class="mt-2.5 text-2xl font-black text-emerald-300 tracking-tight">
        {{ formatCurrency(stats.monto_cobrado_mxn, 'MXN') }}
      </div>
      <div class="mt-1 text-[10px] text-emerald-400/70 font-medium">
        {{ stats.total_pagados }} pagos liquidados
      </div>
    </div>

    <!-- 2. Recaudación Total USD -->
    <div
      @click="emit('select-filter', 'pagados')"
      :class="[
        'p-4 rounded-2xl border transition-all duration-150 cursor-pointer select-none group relative overflow-hidden',
        activeFilter === 'pagados'
          ? 'bg-gradient-to-br from-cyan-950/40 via-slate-900 to-slate-900 border-cyan-500/60 shadow-lg shadow-cyan-500/10'
          : 'bg-[#0D121F]/90 border-slate-800/80 hover:border-cyan-500/30 hover:bg-[#111728]'
      ]"
    >
      <div class="flex items-center justify-between">
        <span class="text-[11px] text-cyan-400 font-semibold uppercase tracking-wider">Cobrado (USD)</span>
        <div class="w-7 h-7 rounded-lg bg-cyan-500/10 text-cyan-400 flex items-center justify-center">
          <i class="pi pi-dollar text-xs"></i>
        </div>
      </div>
      <div class="mt-2.5 text-2xl font-black text-cyan-300 tracking-tight">
        {{ formatCurrency(stats.monto_cobrado_usd, 'USD') }}
      </div>
      <div class="mt-1 text-[10px] text-cyan-400/70 font-medium">Cuentas internacionales</div>
    </div>

    <!-- 3. Pendiente de Cobro MXN -->
    <div
      @click="emit('select-filter', 'pendientes')"
      :class="[
        'p-4 rounded-2xl border transition-all duration-150 cursor-pointer select-none group relative overflow-hidden',
        activeFilter === 'pendientes'
          ? 'bg-gradient-to-br from-amber-950/40 via-slate-900 to-slate-900 border-amber-500/60 shadow-lg shadow-amber-500/10'
          : 'bg-[#0D121F]/90 border-slate-800/80 hover:border-amber-500/30 hover:bg-[#111728]'
      ]"
    >
      <div class="flex items-center justify-between">
        <span class="text-[11px] text-amber-400 font-semibold uppercase tracking-wider">Por Cobrar (MXN)</span>
        <div class="w-7 h-7 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center">
          <i class="pi pi-clock text-xs"></i>
        </div>
      </div>
      <div class="mt-2.5 text-2xl font-black text-amber-300 tracking-tight">
        {{ formatCurrency(stats.monto_pendiente_mxn, 'MXN') }}
      </div>
      <div class="mt-1 text-[10px] text-amber-400/70 font-medium">
        {{ stats.total_pendientes }} recibos por conciliar
      </div>
    </div>

    <!-- 4. Pendiente de Cobro USD -->
    <div
      @click="emit('select-filter', 'pendientes')"
      :class="[
        'p-4 rounded-2xl border transition-all duration-150 cursor-pointer select-none group relative overflow-hidden',
        activeFilter === 'pendientes'
          ? 'bg-gradient-to-br from-orange-950/40 via-slate-900 to-slate-900 border-orange-500/60 shadow-lg shadow-orange-500/10'
          : 'bg-[#0D121F]/90 border-slate-800/80 hover:border-orange-500/30 hover:bg-[#111728]'
      ]"
    >
      <div class="flex items-center justify-between">
        <span class="text-[11px] text-orange-400 font-semibold uppercase tracking-wider">Por Cobrar (USD)</span>
        <div class="w-7 h-7 rounded-lg bg-orange-500/10 text-orange-400 flex items-center justify-center">
          <i class="pi pi-hourglass text-xs"></i>
        </div>
      </div>
      <div class="mt-2.5 text-2xl font-black text-orange-300 tracking-tight">
        {{ formatCurrency(stats.monto_pendiente_usd, 'USD') }}
      </div>
      <div class="mt-1 text-[10px] text-orange-400/70 font-medium">Moneda extranjera</div>
    </div>
  </div>
</template>
