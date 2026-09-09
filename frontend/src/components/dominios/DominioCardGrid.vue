<script setup lang="ts">
import type { DominioListItem } from '@/api/dominios'

defineProps<{
  dominios: DominioListItem[]
  viewMode: 'table' | 'cards'
  isSuperAdmin: boolean
  formatCurrency: (amount: number, currency?: string) => string
  formatWhatsAppRenewalLink: (phone: string | null, cliente: string, dominio: string, vencimiento: string | null) => string
}>()

const emit = defineEmits<{
  (e: 'view-detail', id: number): void
  (e: 'edit', dom: DominioListItem): void
}>()
</script>

<template>
  <div
    class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4"
    :class="viewMode === 'table' ? 'md:hidden' : ''"
  >
    <div
      v-for="dom in dominios"
      :key="`card-dom-${dom.id_dominio}`"
      class="p-5 rounded-3xl bg-[#0D121F]/90 border border-slate-800/80 hover:border-slate-700 transition-all space-y-4 shadow-xl flex flex-col justify-between"
    >
      <div>
        <!-- Header -->
        <div class="flex items-start justify-between gap-3">
          <div class="flex items-center space-x-3 min-w-0">
            <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-cyan-600/30 via-blue-600/20 to-indigo-500/20 border border-cyan-500/30 flex items-center justify-center font-bold text-xs text-cyan-300 shrink-0">
              <i class="pi pi-globe text-sm"></i>
            </div>
            <div class="min-w-0">
              <h4 class="font-bold text-white text-sm truncate">{{ dom.url_dominio }}</h4>
              <p class="text-xs text-slate-400 truncate">{{ dom.cliente_empresa }}</p>
            </div>
          </div>

          <span
            v-if="dom.estatus_pago === 1"
            class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30"
          >
            Pagado
          </span>
          <span
            v-else
            class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/15 text-amber-300 border border-amber-500/30"
          >
            Pendiente
          </span>
        </div>

        <!-- Info Grid -->
        <div class="mt-4 pt-3 border-t border-slate-800/80 space-y-2 text-xs text-slate-300">
          <div class="flex items-center justify-between">
            <span class="text-slate-400">Registrador:</span>
            <span class="font-medium text-slate-200">{{ dom.proveedor || 'NexusBot' }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-slate-400">Costo:</span>
            <span class="font-black text-white">{{ formatCurrency(dom.costo_dominio) }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-slate-400">Vencimiento:</span>
            <span
              :class="[
                'font-bold',
                dom.estado_vencimiento === 'vencido' ? 'text-rose-400' :
                dom.estado_vencimiento === 'prox7' ? 'text-amber-400' : 'text-emerald-400'
              ]"
            >
              {{ dom.fecha_pago || 'Activo' }}
            </span>
          </div>
        </div>
      </div>

      <!-- Botones Táctiles Móvil -->
      <div class="pt-3 border-t border-slate-800/80 flex items-center space-x-2">
        <a
          v-if="dom.cliente_telefono"
          :href="formatWhatsAppRenewalLink(dom.cliente_telefono, dom.cliente_nombre, dom.url_dominio, dom.fecha_pago)"
          target="_blank"
          rel="noopener"
          class="flex-1 py-2 rounded-xl bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-300 border border-emerald-500/30 text-xs font-bold flex items-center justify-center space-x-1.5 transition-colors"
        >
          <i class="pi pi-whatsapp text-xs"></i>
          <span>WhatsApp</span>
        </a>

        <button
          @click="emit('view-detail', dom.id_dominio)"
          class="flex-1 py-2 rounded-xl bg-slate-800 hover:bg-blue-600 text-slate-200 hover:text-white border border-slate-700/60 text-xs font-semibold flex items-center justify-center space-x-1.5 transition-colors"
        >
          <i class="pi pi-eye text-xs"></i>
          <span>DNS / Info</span>
        </button>

        <button
          v-if="isSuperAdmin"
          @click="emit('edit', dom)"
          class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700/60 transition-colors"
          title="Editar"
        >
          <i class="pi pi-pencil text-xs"></i>
        </button>
      </div>
    </div>
  </div>
</template>
