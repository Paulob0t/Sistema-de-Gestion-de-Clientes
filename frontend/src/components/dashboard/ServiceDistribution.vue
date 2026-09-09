<script setup lang="ts">
import type { ServiceDistribution } from '@/api/dashboard'

defineProps<{
  distribution: ServiceDistribution
  formatCurrency: (amount: number, currency?: string) => string
}>()
</script>

<template>
  <div class="p-6 rounded-3xl bg-slate-900/70 border border-slate-800/80 space-y-4">
    <div>
      <h4 class="text-sm font-bold text-white tracking-tight">Distribución de Ingresos</h4>
      <p class="text-xs text-slate-400">Por tipo de infraestructura activa</p>
    </div>

    <div class="space-y-4 pt-3 text-xs">
      <div class="p-3.5 rounded-2xl bg-slate-950/60 border border-slate-800/60 flex items-center justify-between">
        <div class="flex items-center space-x-3">
          <div class="w-3 h-3 rounded-full bg-indigo-500"></div>
          <span class="text-slate-300 font-medium">Dominios Web</span>
        </div>
        <span class="font-bold text-white">{{ formatCurrency(distribution.dominios) }}</span>
      </div>

      <div class="p-3.5 rounded-2xl bg-slate-950/60 border border-slate-800/60 flex items-center justify-between">
        <div class="flex items-center space-x-3">
          <div class="w-3 h-3 rounded-full bg-cyan-500"></div>
          <span class="text-slate-300 font-medium">Planes de Hosting</span>
        </div>
        <span class="font-bold text-white">{{ formatCurrency(distribution.hosting) }}</span>
      </div>

      <div class="p-3.5 rounded-2xl bg-slate-950/60 border border-slate-800/60 flex items-center justify-between">
        <div class="flex items-center space-x-3">
          <div class="w-3 h-3 rounded-full bg-amber-500"></div>
          <span class="text-slate-300 font-medium">Servicios Manuales</span>
        </div>
        <span class="font-bold text-white">{{ formatCurrency(distribution.servicios) }}</span>
      </div>
    </div>
  </div>
</template>
