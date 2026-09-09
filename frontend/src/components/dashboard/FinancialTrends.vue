<script setup lang="ts">
import type { MonthlyTrendItem } from '@/api/dashboard'

defineProps<{
  trends: MonthlyTrendItem[]
  maxTrendAmount: number
  formatCurrency: (amount: number, currency?: string) => string
}>()
</script>

<template>
  <div class="lg:col-span-2 p-6 rounded-3xl bg-slate-900/70 border border-slate-800/80 space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h4 class="text-sm font-bold text-white tracking-tight">Historial Financiero (Últimos 6 meses)</h4>
        <p class="text-xs text-slate-400">Facturación real cobrada mes a mes</p>
      </div>
      <span class="text-xs text-blue-400 font-semibold flex items-center">
        <i class="pi pi-chart-bar mr-1"></i> Tendencia
      </span>
    </div>

    <!-- Gráfica de Barras -->
    <div class="grid grid-cols-6 gap-2 sm:gap-4 items-end h-44 pt-4 pb-2 border-b border-slate-800/80">
      <div
        v-for="item in trends"
        :key="item.mes"
        class="flex flex-col items-center h-full justify-end group"
      >
        <div class="text-[10px] text-slate-400 font-bold mb-1 opacity-0 group-hover:opacity-100 transition-opacity">
          {{ formatCurrency(item.pagados) }}
        </div>
        <div
          class="w-full max-w-[36px] rounded-t-xl bg-gradient-to-t from-blue-600 to-indigo-500 group-hover:from-blue-500 group-hover:to-cyan-400 transition-all duration-300 min-h-[4px]"
          :style="{ height: `${Math.max(8, (item.pagados / maxTrendAmount) * 100)}%` }"
        ></div>
        <span class="text-[11px] font-semibold text-slate-400 mt-2 uppercase tracking-wider">{{ item.mes }}</span>
      </div>
    </div>
  </div>
</template>
