<script setup lang="ts">
import type { ClienteListItem } from '@/api/clientes'

defineProps<{
  clientes: ClienteListItem[]
  viewMode: 'table' | 'cards'
  isSuperAdmin: boolean
  getInitials: (name: string) => string
  formatWhatsAppLink: (phone: string | null) => string
}>()

const emit = defineEmits<{
  (e: 'view-detail', id: number): void
  (e: 'edit', client: ClienteListItem): void
}>()
</script>

<template>
  <div
    class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4"
    :class="viewMode === 'table' ? 'md:hidden' : ''"
  >
    <div
      v-for="item in clientes"
      :key="`card-${item.id}`"
      class="p-5 rounded-3xl bg-slate-900/70 border border-slate-800/80 hover:border-slate-700 transition-all space-y-4 shadow-xl flex flex-col justify-between"
    >
      <div>
        <!-- Header Tarjeta -->
        <div class="flex items-start justify-between gap-3">
          <div class="flex items-center space-x-3 min-w-0">
            <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-blue-600/30 to-indigo-600/30 border border-blue-500/30 flex items-center justify-center font-bold text-sm text-blue-400 shrink-0">
              {{ getInitials(item.empresa) }}
            </div>
            <div class="min-w-0">
              <h4 class="font-bold text-white text-sm truncate">{{ item.empresa }}</h4>
              <p class="text-xs text-slate-400 truncate">{{ item.nombre_contacto }}</p>
            </div>
          </div>

          <!-- Estado Cobranza Badge -->
          <div>
            <span
              v-if="item.estado_pago === 'al_dia'"
              class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-950 text-emerald-300 border border-emerald-500/30"
            >
              Al día
            </span>
            <span
              v-else-if="item.estado_pago === 'pendiente'"
              class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-950 text-amber-300 border border-amber-500/40 animate-pulse"
            >
              {{ item.total_pagos_pendientes }} Pend.
            </span>
            <span
              v-else
              class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-950 text-slate-500 border border-slate-800"
            >
              Sin serv.
            </span>
          </div>
        </div>

        <!-- Contacto -->
        <div class="mt-4 pt-3 border-t border-slate-800/80 space-y-1.5 text-xs text-slate-300">
          <div v-if="item.correo" class="flex items-center space-x-2 truncate">
            <i class="pi pi-envelope text-[11px] text-slate-500"></i>
            <a :href="`mailto:${item.correo}`" class="hover:text-blue-400 transition-colors truncate">
              {{ item.correo }}
            </a>
          </div>
          <div v-if="item.telefono" class="flex items-center space-x-2">
            <i class="pi pi-phone text-[11px] text-slate-500"></i>
            <span>{{ item.telefono }}</span>
          </div>
        </div>

        <!-- Badges de Infraestructura -->
        <div class="mt-4 flex items-center space-x-2 text-xs">
          <div class="flex-1 p-2 rounded-xl bg-slate-950/60 border border-slate-800 flex items-center justify-between">
            <span class="text-slate-400 text-[11px]">Dominios</span>
            <span class="font-bold text-blue-400">{{ item.total_dominios }}</span>
          </div>
          <div class="flex-1 p-2 rounded-xl bg-slate-950/60 border border-slate-800 flex items-center justify-between">
            <span class="text-slate-400 text-[11px]">Hosting</span>
            <span class="font-bold text-emerald-400">{{ item.total_hostings }}</span>
          </div>
        </div>
      </div>

      <!-- Botones de Acción Móvil -->
      <div class="pt-3 border-t border-slate-800/80 flex items-center space-x-2">
        <a
          v-if="item.telefono"
          :href="formatWhatsAppLink(item.telefono)"
          target="_blank"
          rel="noopener"
          class="flex-1 py-2 rounded-xl bg-emerald-600/20 hover:bg-emerald-600/40 text-emerald-300 border border-emerald-500/30 text-xs font-bold flex items-center justify-center space-x-1.5 transition-colors"
        >
          <i class="pi pi-whatsapp text-xs"></i>
          <span>WhatsApp</span>
        </a>

        <button
          @click="emit('view-detail', item.id)"
          class="flex-1 py-2 rounded-xl bg-slate-800 hover:bg-blue-600 text-slate-200 hover:text-white border border-slate-700/60 text-xs font-semibold flex items-center justify-center space-x-1.5 transition-colors"
        >
          <i class="pi pi-eye text-xs"></i>
          <span>Detalles</span>
        </button>

        <button
          v-if="isSuperAdmin"
          @click="emit('edit', item)"
          class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700/60 transition-colors"
          title="Editar"
        >
          <i class="pi pi-pencil text-xs"></i>
        </button>
      </div>
    </div>
  </div>
</template>
