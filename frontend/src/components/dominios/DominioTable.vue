<script setup lang="ts">
import type { DominioListItem } from '@/api/dominios'

defineProps<{
  dominios: DominioListItem[]
  isSuperAdmin: boolean
  formatCurrency: (amount: number, currency?: string) => string
  formatWhatsAppRenewalLink: (phone: string | null, cliente: string, dominio: string, vencimiento: string | null) => string
}>()

const emit = defineEmits<{
  (e: 'view-detail', id: number): void
  (e: 'edit', dom: DominioListItem): void
  (e: 'delete', dom: DominioListItem): void
}>()
</script>

<template>
  <div class="hidden md:block rounded-3xl bg-[#0D121F]/90 border border-slate-800/80 overflow-hidden shadow-2xl">
    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs">
        <thead class="bg-[#0A0F1D] text-slate-400 uppercase tracking-wider font-semibold border-b border-slate-800/80 text-[10px]">
          <tr>
            <th class="py-4 px-5">Dominio Web</th>
            <th class="py-4 px-5">Cliente / Empresa</th>
            <th class="py-4 px-5">Registrador & Precio</th>
            <th class="py-4 px-5 text-center">Vencimiento</th>
            <th class="py-4 px-5 text-center">Estado Cobro</th>
            <th class="py-4 px-5 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-800/50">
          <tr
            v-for="dom in dominios"
            :key="dom.id_dominio"
            class="hover:bg-[#131A2D]/70 transition-colors duration-100 group"
          >
            <!-- Dominio -->
            <td class="py-4 px-5">
              <div class="flex items-center space-x-3.5">
                <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-cyan-600/30 via-blue-600/20 to-indigo-500/20 border border-cyan-500/30 flex items-center justify-center font-bold text-xs text-cyan-300 shrink-0 shadow-sm">
                  <i class="pi pi-globe text-sm"></i>
                </div>
                <div class="min-w-0">
                  <a
                    :href="`https://${dom.url_dominio}`"
                    target="_blank"
                    rel="noopener"
                    class="font-bold text-white text-sm truncate hover:text-cyan-400 transition-colors flex items-center space-x-1.5"
                  >
                    <span>{{ dom.url_dominio }}</span>
                    <i class="pi pi-external-link text-[10px] text-slate-500 group-hover:text-cyan-400"></i>
                  </a>
                  <div class="text-[11px] text-slate-400 flex items-center space-x-2 mt-0.5">
                    <span class="font-mono text-slate-500">ID: #{{ dom.id_dominio }}</span>
                    <span v-if="dom.registrado === 1" class="px-1.5 py-0.2 rounded text-[9px] bg-blue-950 text-blue-300 border border-blue-500/30 font-semibold">NexusBot</span>
                    <span v-else class="px-1.5 py-0.2 rounded text-[9px] bg-slate-900 text-slate-400 border border-slate-700 font-semibold">Externo</span>
                  </div>
                </div>
              </div>
            </td>

            <!-- Cliente -->
            <td class="py-4 px-5">
              <div class="space-y-0.5">
                <div class="font-semibold text-white text-xs">{{ dom.cliente_empresa }}</div>
                <div class="text-[11px] text-slate-400 flex items-center space-x-1.5">
                  <span class="truncate max-w-[170px]">{{ dom.cliente_nombre }}</span>
                </div>
              </div>
            </td>

            <!-- Proveedor y Costo -->
            <td class="py-4 px-5">
              <div class="space-y-0.5">
                <div class="font-black text-white text-xs">{{ formatCurrency(dom.costo_dominio) }}</div>
                <div class="text-[11px] text-slate-400 flex items-center space-x-1">
                  <i class="pi pi-server text-[10px] text-slate-500"></i>
                  <span>{{ dom.proveedor || 'NexusBot' }}</span>
                </div>
              </div>
            </td>

            <!-- Vencimiento -->
            <td class="py-4 px-5 text-center">
              <div class="inline-flex flex-col items-center">
                <span
                  v-if="dom.estado_vencimiento === 'vencido'"
                  class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-500/15 text-rose-300 border border-rose-500/30 flex items-center space-x-1"
                >
                  <i class="pi pi-exclamation-triangle text-[10px]"></i>
                  <span>Venció hace {{ Math.abs(dom.dias_restantes ?? 0) }}d</span>
                </span>
                <span
                  v-else-if="dom.estado_vencimiento === 'prox7'"
                  class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-500/15 text-amber-300 border border-amber-500/30 flex items-center space-x-1 animate-pulse"
                >
                  <i class="pi pi-clock text-[10px]"></i>
                  <span>Vence en {{ dom.dias_restantes }}d</span>
                </span>
                <span
                  v-else-if="dom.estado_vencimiento === 'prox30'"
                  class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-500/10 text-amber-300 border border-amber-500/20"
                >
                  Vence en {{ dom.dias_restantes }}d
                </span>
                <span
                  v-else
                  class="px-2.5 py-1 rounded-full text-[11px] font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20"
                >
                  {{ dom.fecha_pago || 'Activo' }}
                </span>
                <span v-if="dom.fecha_pago" class="text-[10px] text-slate-500 mt-0.5">{{ dom.fecha_pago }}</span>
              </div>
            </td>

            <!-- Estado Cobro -->
            <td class="py-4 px-5 text-center">
              <span
                v-if="dom.estatus_pago === 1"
                class="inline-flex items-center space-x-1.5 px-3 py-1 rounded-full text-[11px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30"
              >
                <i class="pi pi-check text-[10px]"></i>
                <span>Pagado</span>
              </span>
              <span
                v-else
                class="inline-flex items-center space-x-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-amber-500/15 text-amber-300 border border-amber-500/30"
              >
                <i class="pi pi-clock text-[10px]"></i>
                <span>Pendiente</span>
              </span>
            </td>

            <!-- Acciones Rápidas -->
            <td class="py-4 px-5 text-right">
              <div class="flex items-center justify-end space-x-1.5">
                <!-- WhatsApp Renovación -->
                <a
                  v-if="dom.cliente_telefono"
                  :href="formatWhatsAppRenewalLink(dom.cliente_telefono, dom.cliente_nombre, dom.url_dominio, dom.fecha_pago)"
                  target="_blank"
                  rel="noopener"
                  class="w-8 h-8 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/25 text-emerald-400 hover:text-emerald-300 border border-emerald-500/30 flex items-center justify-center transition-colors"
                  title="WhatsApp Recordatorio"
                >
                  <i class="pi pi-whatsapp text-xs"></i>
                </a>

                <!-- Ver Detalle / DNS -->
                <button
                  @click="emit('view-detail', dom.id_dominio)"
                  class="w-8 h-8 rounded-xl bg-slate-800/80 hover:bg-blue-600 text-slate-300 hover:text-white border border-slate-700/60 flex items-center justify-center transition-colors"
                  title="Ver DNS & Credenciales"
                >
                  <i class="pi pi-eye text-xs"></i>
                </button>

                <!-- Editar -->
                <button
                  v-if="isSuperAdmin"
                  @click="emit('edit', dom)"
                  class="w-8 h-8 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700/60 flex items-center justify-center transition-colors"
                  title="Editar Dominio"
                >
                  <i class="pi pi-pencil text-xs"></i>
                </button>

                <!-- Eliminar -->
                <button
                  v-if="isSuperAdmin && dom.eliminado === 0"
                  @click="emit('delete', dom)"
                  class="w-8 h-8 rounded-xl bg-slate-800/80 hover:bg-rose-600 text-slate-400 hover:text-white border border-slate-700/60 flex items-center justify-center transition-colors"
                  title="Eliminar Dominio"
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
