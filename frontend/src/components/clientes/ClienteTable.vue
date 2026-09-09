<script setup lang="ts">
import type { ClienteListItem } from '@/api/clientes'

defineProps<{
  clientes: ClienteListItem[]
  isSuperAdmin: boolean
  getInitials: (name: string) => string
  formatWhatsAppLink: (phone: string | null) => string
}>()

const emit = defineEmits<{
  (e: 'view-detail', id: number): void
  (e: 'edit', client: ClienteListItem): void
  (e: 'delete', client: ClienteListItem): void
}>()
</script>

<template>
  <div class="hidden md:block rounded-3xl bg-[#0D121F]/90 border border-slate-800/80 overflow-hidden shadow-2xl">
    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs">
        <thead class="bg-[#0A0F1D] text-slate-400 uppercase tracking-wider font-semibold border-b border-slate-800/80 text-[10px]">
          <tr>
            <th class="py-4 px-5">Cliente / Razón Social</th>
            <th class="py-4 px-5">Contacto Principal</th>
            <th class="py-4 px-5 text-center">Infraestructura</th>
            <th class="py-4 px-5 text-center">Estado Cobranza</th>
            <th class="py-4 px-5 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-800/50">
          <tr
            v-for="item in clientes"
            :key="item.id"
            class="hover:bg-[#131A2D]/70 transition-colors duration-100 group"
          >
            <!-- Empresa & Avatar -->
            <td class="py-4 px-5">
              <div class="flex items-center space-x-3.5">
                <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-blue-600/30 via-indigo-600/20 to-cyan-500/20 border border-blue-500/30 flex items-center justify-center font-bold text-xs text-blue-300 shrink-0 shadow-sm">
                  {{ getInitials(item.empresa) }}
                </div>
                <div class="min-w-0">
                  <div class="font-bold text-white text-sm truncate max-w-xs flex items-center space-x-2">
                    <span class="group-hover:text-blue-300 transition-colors">{{ item.empresa }}</span>
                    <span v-if="item.transferido === 1" class="px-1.5 py-0.5 rounded text-[9px] bg-indigo-950 text-indigo-300 border border-indigo-500/30 font-semibold uppercase">Transferido</span>
                    <span v-if="item.eliminado === 1" class="px-1.5 py-0.5 rounded text-[9px] bg-rose-950 text-rose-300 border border-rose-500/30 font-semibold uppercase">Baja</span>
                  </div>
                  <div class="text-[11px] text-slate-400 flex items-center space-x-2 mt-0.5">
                    <span class="font-mono text-slate-500">#{{ item.id }}</span>
                    <span v-if="item.rfc" class="text-slate-400">• RFC: <strong class="font-mono text-slate-300">{{ item.rfc }}</strong></span>
                  </div>
                </div>
              </div>
            </td>

            <!-- Contacto & Correo -->
            <td class="py-4 px-5">
              <div class="space-y-1">
                <div class="font-semibold text-slate-200">{{ item.nombre_contacto }}</div>
                <div class="text-[11px] text-slate-400 flex items-center space-x-1.5">
                  <i class="pi pi-envelope text-[10px] text-slate-500"></i>
                  <a :href="`mailto:${item.correo}`" class="hover:text-blue-400 transition-colors truncate max-w-[190px]">
                    {{ item.correo || 'Sin correo' }}
                  </a>
                </div>
              </div>
            </td>

            <!-- Infraestructura (Dominios y Hosting) -->
            <td class="py-4 px-5 text-center">
              <div class="inline-flex items-center justify-center space-x-2">
                <!-- Badge Dominios -->
                <span
                  :class="[
                    'px-2.5 py-1 rounded-xl text-xs font-bold flex items-center space-x-1.5 transition-colors',
                    item.total_dominios > 0
                      ? 'bg-blue-500/10 text-blue-400 border border-blue-500/30'
                      : 'bg-slate-900/40 text-slate-600 border border-slate-800/80'
                  ]"
                  title="Dominios Web Activos"
                >
                  <i class="pi pi-globe text-[11px]"></i>
                  <span>{{ item.total_dominios }}</span>
                </span>

                <!-- Badge Hosting -->
                <span
                  :class="[
                    'px-2.5 py-1 rounded-xl text-xs font-bold flex items-center space-x-1.5 transition-colors',
                    item.total_hostings > 0
                      ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30'
                      : 'bg-slate-900/40 text-slate-600 border border-slate-800/80'
                  ]"
                  title="Planes de Hosting Activos"
                >
                  <i class="pi pi-server text-[11px]"></i>
                  <span>{{ item.total_hostings }}</span>
                </span>
              </div>
            </td>

            <!-- Estado Cobranza -->
            <td class="py-4 px-5 text-center">
              <span
                v-if="item.estado_pago === 'al_dia'"
                class="inline-flex items-center space-x-1.5 px-3 py-1 rounded-full text-[11px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30"
              >
                <i class="pi pi-check text-[10px]"></i>
                <span>Al corriente</span>
              </span>

              <span
                v-else-if="item.estado_pago === 'pendiente'"
                class="inline-flex items-center space-x-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-amber-500/15 text-amber-300 border border-amber-500/40 animate-pulse"
              >
                <i class="pi pi-clock text-[10px]"></i>
                <span>{{ item.total_pagos_pendientes }} Pendiente{{ item.total_pagos_pendientes > 1 ? 's' : '' }}</span>
              </span>

              <span
                v-else
                class="inline-flex items-center px-3 py-1 rounded-full text-[11px] font-medium bg-slate-900/60 text-slate-500 border border-slate-800"
              >
                <span>Sin servicios</span>
              </span>
            </td>

            <!-- Acciones Rápidas -->
            <td class="py-4 px-5 text-right">
              <div class="flex items-center justify-end space-x-1.5">
                <!-- WhatsApp -->
                <a
                  v-if="item.telefono"
                  :href="formatWhatsAppLink(item.telefono)"
                  target="_blank"
                  rel="noopener"
                  class="w-8 h-8 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/25 text-emerald-400 hover:text-emerald-300 border border-emerald-500/30 flex items-center justify-center transition-colors"
                  title="Enviar WhatsApp"
                >
                  <i class="pi pi-whatsapp text-xs"></i>
                </a>

                <!-- Ver Ficha / Detalle -->
                <button
                  @click="emit('view-detail', item.id)"
                  class="w-8 h-8 rounded-xl bg-slate-800/80 hover:bg-blue-600 text-slate-300 hover:text-white border border-slate-700/60 flex items-center justify-center transition-colors"
                  title="Ver Perfil Completo"
                >
                  <i class="pi pi-eye text-xs"></i>
                </button>

                <!-- Editar -->
                <button
                  v-if="isSuperAdmin"
                  @click="emit('edit', item)"
                  class="w-8 h-8 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700/60 flex items-center justify-center transition-colors"
                  title="Editar Datos"
                >
                  <i class="pi pi-pencil text-xs"></i>
                </button>

                <!-- Eliminar -->
                <button
                  v-if="isSuperAdmin && item.eliminado === 0"
                  @click="emit('delete', item)"
                  class="w-8 h-8 rounded-xl bg-slate-800/80 hover:bg-rose-600 text-slate-400 hover:text-white border border-slate-700/60 flex items-center justify-center transition-colors"
                  title="Dar de baja"
                >
                  <i class="pi pi-trash text-xs"></i>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
