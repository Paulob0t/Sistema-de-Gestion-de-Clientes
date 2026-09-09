<script setup lang="ts">
import type { ClienteStats } from '@/api/clientes'

defineProps<{
  stats: ClienteStats
  activeFilter: string
}>()

const emit = defineEmits<{
  (e: 'select-filter', filter: string): void
}>()
</script>

<template>
  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
    <!-- Total -->
    <div
      @click="emit('select-filter', 'todos')"
      :class="[
        'p-4 rounded-2xl border transition-all cursor-pointer select-none',
        activeFilter === 'todos'
          ? 'bg-slate-800/90 border-blue-500/50 shadow-lg shadow-blue-500/10'
          : 'bg-slate-900/60 border-slate-800/80 hover:border-slate-700'
      ]"
    >
      <div class="flex items-center justify-between">
        <span class="text-xs text-slate-400 font-medium">Total Clientes</span>
        <i class="pi pi-users text-xs text-slate-500"></i>
      </div>
      <div class="mt-2 text-2xl font-bold text-white">{{ stats.total }}</div>
    </div>

    <!-- Activos -->
    <div
      @click="emit('select-filter', 'activos')"
      :class="[
        'p-4 rounded-2xl border transition-all cursor-pointer select-none',
        activeFilter === 'activos'
          ? 'bg-emerald-950/40 border-emerald-500/50 shadow-lg shadow-emerald-500/10'
          : 'bg-slate-900/60 border-slate-800/80 hover:border-emerald-900/50'
      ]"
    >
      <div class="flex items-center justify-between">
        <span class="text-xs text-emerald-400 font-medium">Activos</span>
        <div class="w-2 h-2 rounded-full bg-emerald-400"></div>
      </div>
      <div class="mt-2 text-2xl font-bold text-white">{{ stats.activos }}</div>
    </div>

    <!-- Pagos Pendientes -->
    <div
      @click="emit('select-filter', 'pendientes')"
      :class="[
        'p-4 rounded-2xl border transition-all cursor-pointer select-none',
        activeFilter === 'pendientes'
          ? 'bg-amber-950/40 border-amber-500/50 shadow-lg shadow-amber-500/10'
          : 'bg-slate-900/60 border-slate-800/80 hover:border-amber-900/50'
      ]"
    >
      <div class="flex items-center justify-between">
        <span class="text-xs text-amber-400 font-medium">Con Pagos Pend.</span>
        <i class="pi pi-clock text-xs text-amber-400"></i>
      </div>
      <div class="mt-2 text-2xl font-bold text-amber-300">{{ stats.con_pagos_pendientes }}</div>
    </div>

    <!-- Transferidos -->
    <div
      @click="emit('select-filter', 'transferidos')"
      :class="[
        'p-4 rounded-2xl border transition-all cursor-pointer select-none',
        activeFilter === 'transferidos'
          ? 'bg-indigo-950/40 border-indigo-500/50 shadow-lg shadow-indigo-500/10'
          : 'bg-slate-900/60 border-slate-800/80 hover:border-indigo-900/50'
      ]"
    >
      <div class="flex items-center justify-between">
        <span class="text-xs text-indigo-400 font-medium">Transferidos</span>
        <i class="pi pi-sync text-xs text-indigo-400"></i>
      </div>
      <div class="mt-2 text-2xl font-bold text-white">{{ stats.transferidos }}</div>
    </div>

    <!-- Eliminados -->
    <div
      @click="emit('select-filter', 'eliminados')"
      :class="[
        'col-span-2 sm:col-span-1 p-4 rounded-2xl border transition-all cursor-pointer select-none',
        activeFilter === 'eliminados'
          ? 'bg-red-950/40 border-red-500/50 shadow-lg shadow-red-500/10'
          : 'bg-slate-900/60 border-slate-800/80 hover:border-red-900/50'
      ]"
    >
      <div class="flex items-center justify-between">
        <span class="text-xs text-rose-400 font-medium">Bajas / Eliminados</span>
        <i class="pi pi-trash text-xs text-rose-400"></i>
      </div>
      <div class="mt-2 text-2xl font-bold text-white">{{ stats.eliminados }}</div>
    </div>
  </div>
</template>
