<script setup lang="ts">
import type { HostingListItem } from '@/api/hostings'

defineProps<{
  hostings: HostingListItem[]
  isSuperAdmin: boolean
}>()

const emit = defineEmits<{
  (e: 'open-detail', id: number): void
  (e: 'open-edit', hosting: HostingListItem): void
  (e: 'delete-hosting', id: number, nom_host: string): void
  (e: 'send-whatsapp', hosting: HostingListItem): void
}>()

function formatCurrency(val: number, moneda = 'MXN') {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: moneda,
  }).format(val)
}

function openPanel(url?: string) {
  if (!url) return
  let cleanUrl = url.trim()
  if (!cleanUrl.startsWith('http')) {
    cleanUrl = 'https://' + cleanUrl
  }
  window.open(cleanUrl, '_blank')
}
</script>

<template>
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    <div
      v-for="host in hostings"
      :key="host.id_orden"
      class="p-5 rounded-2xl bg-[#0C111E]/90 border border-slate-800 hover:border-slate-700 transition-all duration-150 flex flex-col justify-between group relative overflow-hidden"
    >
      <div>
        <!-- Cabecera de tarjeta -->
        <div class="flex items-start justify-between">
          <div class="flex items-center space-x-3">
            <div
              :class="[
                'w-10 h-10 rounded-xl flex items-center justify-center font-bold text-sm shrink-0 shadow-md',
                host.panel_type === 'whm'
                  ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20'
                  : 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/20'
              ]"
            >
              <i :class="host.panel_type === 'whm' ? 'pi pi-server text-base' : 'pi pi-globe text-base'"></i>
            </div>
            <div>
              <div class="font-bold text-white group-hover:text-cyan-400 transition-colors text-sm truncate max-w-[190px]">
                {{ host.nom_host }}
              </div>
              <div class="text-[11px] text-slate-400 flex items-center space-x-1.5">
                <span>#{{ host.id_orden }}</span>
                <span class="text-slate-600">•</span>
                <span class="font-mono text-cyan-400 truncate max-w-[120px]">{{ host.dominio || 'Sin dominio' }}</span>
              </div>
            </div>
          </div>

          <span
            :class="[
              'px-2 py-0.5 rounded-full text-[10px] font-semibold border',
              host.estado_producto === 1
                ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
                : 'bg-rose-500/10 text-rose-400 border-rose-500/20'
            ]"
          >
            {{ host.estado_producto === 1 ? 'Activo' : 'Inactivo' }}
          </span>
        </div>

        <!-- Información de Cliente y Plan -->
        <div class="mt-4 p-3 rounded-xl bg-slate-900/60 border border-slate-800/60 space-y-1.5 text-xs">
          <div class="flex justify-between items-center text-slate-300">
            <span class="text-slate-500 text-[11px]">Cliente:</span>
            <span class="font-semibold truncate max-w-[170px]">{{ host.cliente_empresa || host.cliente_nombre }}</span>
          </div>
          <div class="flex justify-between items-center text-slate-300">
            <span class="text-slate-500 text-[11px]">Plan:</span>
            <span class="text-cyan-300 truncate max-w-[170px]">{{ host.tipo_producto || 'Alojamiento Web' }}</span>
          </div>
          <div v-if="host.usuario" class="flex justify-between items-center text-slate-300">
            <span class="text-slate-500 text-[11px]">Usuario cPanel:</span>
            <span class="font-mono text-slate-400">{{ host.usuario }}</span>
          </div>
        </div>

        <!-- Vencimiento & Costo -->
        <div class="mt-3 flex items-center justify-between pt-2 border-t border-slate-800/40 text-xs">
          <div>
            <div class="text-[10px] text-slate-500 font-medium">Vencimiento</div>
            <div class="font-semibold text-slate-200 mt-0.5">
              {{ host.fecha_pago || 'Sin fecha' }}
            </div>
          </div>
          <div class="text-right">
            <div class="text-[10px] text-slate-500 font-medium">Costo / Periodo</div>
            <div class="font-bold text-white text-sm">
              {{ formatCurrency(host.costo_producto, host.moneda) }}
              <span class="text-[10px] font-normal text-slate-400">/ {{ host.frecuencia_label }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Botones de Acción -->
      <div class="mt-4 pt-3 border-t border-slate-800 flex items-center justify-between gap-2">
        <button
          v-if="host.url_acceso || host.nom_host"
          @click="openPanel(host.url_acceso || host.nom_host)"
          class="flex-1 py-1.5 px-2 rounded-xl bg-cyan-500/10 hover:bg-cyan-500/20 text-cyan-400 border border-cyan-500/20 text-xs font-semibold flex items-center justify-center space-x-1 transition-colors"
        >
          <i class="pi pi-external-link text-xs"></i>
          <span>Entrar {{ host.panel_type.toUpperCase() }}</span>
        </button>

        <div class="flex items-center space-x-1 shrink-0">
          <button
            v-if="host.cliente_telefono"
            @click="emit('send-whatsapp', host)"
            class="p-2 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/20 transition-colors"
            title="WhatsApp recordatorio"
          >
            <i class="pi pi-whatsapp text-xs"></i>
          </button>
          <button
            @click="emit('open-detail', host.id_orden)"
            class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 transition-colors"
            title="Ver detalle"
          >
            <i class="pi pi-eye text-xs"></i>
          </button>
          <button
            v-if="isSuperAdmin"
            @click="emit('open-edit', host)"
            class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 transition-colors"
            title="Editar"
          >
            <i class="pi pi-pencil text-xs"></i>
          </button>
          <button
            v-if="isSuperAdmin"
            @click="emit('delete-hosting', host.id_orden, host.nom_host)"
            class="p-2 rounded-xl bg-slate-800 hover:bg-rose-500/20 text-slate-400 hover:text-rose-400 transition-colors"
            title="Eliminar"
          >
            <i class="pi pi-trash text-xs"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
