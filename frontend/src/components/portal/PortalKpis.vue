<script setup lang="ts">
import { useRouter } from 'vue-router'

defineProps<{
  totalSitios: number
  totalPendientes: number
  totalTicketsAbiertos: number
  totalHostings: number
  totalDominios: number
}>()

const router = useRouter()
</script>

<template>
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <!-- 1. Sitios Web -->
    <div
      @click="router.push('/dominios')"
      class="group cursor-pointer rounded-2xl bg-slate-900/80 hover:bg-slate-900 border border-slate-800 hover:border-blue-500/40 p-5 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-blue-500/10"
    >
      <div class="flex items-center justify-between">
        <span class="text-xs font-semibold text-slate-400">Sitios Web</span>
        <div class="w-9 h-9 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center group-hover:scale-110 transition-transform">
          <i class="pi pi-globe text-base"></i>
        </div>
      </div>
      <div class="mt-3 flex items-baseline gap-2">
        <span class="text-2xl lg:text-3xl font-black text-white tracking-tight">{{ totalSitios }}</span>
        <span class="text-xs text-slate-400">activos</span>
      </div>
    </div>

    <!-- 2. Pagos Pendientes -->
    <div
      @click="router.push('/pagos')"
      :class="[
        'group cursor-pointer rounded-2xl border p-5 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg',
        totalPendientes > 0
          ? 'bg-amber-950/20 hover:bg-amber-950/30 border-amber-500/30 hover:border-amber-500/50 hover:shadow-amber-500/10'
          : 'bg-slate-900/80 hover:bg-slate-900 border-slate-800 hover:border-emerald-500/40 hover:shadow-emerald-500/10'
      ]"
    >
      <div class="flex items-center justify-between">
        <span class="text-xs font-semibold" :class="totalPendientes > 0 ? 'text-amber-300' : 'text-slate-400'">
          Pagos Pendientes
        </span>
        <div
          :class="[
            'w-9 h-9 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform',
            totalPendientes > 0 ? 'bg-amber-500/20 text-amber-400' : 'bg-emerald-500/10 text-emerald-400'
          ]"
        >
          <i :class="['pi', totalPendientes > 0 ? 'pi-exclamation-circle' : 'pi-check-circle', 'text-base']"></i>
        </div>
      </div>
      <div class="mt-3 flex items-baseline gap-2">
        <span
          class="text-2xl lg:text-3xl font-black tracking-tight"
          :class="totalPendientes > 0 ? 'text-amber-400' : 'text-emerald-400'"
        >
          {{ totalPendientes }}
        </span>
        <span class="text-xs text-slate-400">por liquidar</span>
      </div>
    </div>

    <!-- 3. Soporte / Tickets -->
    <div
      @click="router.push('/solicitudes')"
      class="group cursor-pointer rounded-2xl bg-slate-900/80 hover:bg-slate-900 border border-slate-800 hover:border-purple-500/40 p-5 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-purple-500/10"
    >
      <div class="flex items-center justify-between">
        <span class="text-xs font-semibold text-slate-400">Tickets de Soporte</span>
        <div class="w-9 h-9 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center group-hover:scale-110 transition-transform">
          <i class="pi pi-ticket text-base"></i>
        </div>
      </div>
      <div class="mt-3 flex items-baseline gap-2">
        <span class="text-2xl lg:text-3xl font-black text-white tracking-tight">{{ totalTicketsAbiertos }}</span>
        <span class="text-xs text-slate-400">en atención</span>
      </div>
    </div>

    <!-- 4. Hosting y Dominios -->
    <div
      @click="router.push('/hostings')"
      class="group cursor-pointer rounded-2xl bg-slate-900/80 hover:bg-slate-900 border border-slate-800 hover:border-cyan-500/40 p-5 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-cyan-500/10"
    >
      <div class="flex items-center justify-between">
        <span class="text-xs font-semibold text-slate-400">Servicios Hosting</span>
        <div class="w-9 h-9 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center group-hover:scale-110 transition-transform">
          <i class="pi pi-server text-base"></i>
        </div>
      </div>
      <div class="mt-3 flex items-baseline gap-2">
        <span class="text-2xl lg:text-3xl font-black text-white tracking-tight">{{ totalHostings }}</span>
        <span class="text-xs text-slate-400">planes activos</span>
      </div>
    </div>
  </div>
</template>
