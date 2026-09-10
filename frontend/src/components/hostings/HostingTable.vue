<script setup lang="ts">
import type { HostingListItem } from '@/api/hostings'

defineProps<{
  hostings: HostingListItem[]
  isLoading: boolean
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
  <div class="overflow-x-auto custom-scrollbar">
    <table class="w-full text-left border-collapse">
      <thead>
        <tr class="border-b border-slate-800/80 bg-slate-900/40 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
          <th class="py-3.5 px-4">Host / Servidor</th>
          <th class="py-3.5 px-4">Cliente / Titular</th>
          <th class="py-3.5 px-4">Plan & Servicio</th>
          <th class="py-3.5 px-4">Vencimiento</th>
          <th class="py-3.5 px-4">Costo / Frecuencia</th>
          <th class="py-3.5 px-4 text-center">Estado</th>
          <th class="py-3.5 px-4 text-right">Acciones</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-800/50 text-xs">
        <tr
          v-for="host in hostings"
          :key="host.id_orden"
          class="hover:bg-slate-800/30 transition-colors group"
        >
          <!-- 1. Host / Servidor -->
          <td class="py-3.5 px-4">
            <div class="flex items-center space-x-2.5">
              <div
                :class="[
                  'w-8 h-8 rounded-xl flex items-center justify-center shrink-0 font-bold text-xs',
                  host.panel_type === 'whm'
                    ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20'
                    : 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/20'
                ]"
              >
                <i :class="host.panel_type === 'whm' ? 'pi pi-server text-xs' : 'pi pi-globe text-xs'"></i>
              </div>
              <div class="min-w-0">
                <div class="font-bold text-white group-hover:text-cyan-400 transition-colors truncate max-w-[200px]">
                  {{ host.nom_host }}
                </div>
                <div class="flex items-center space-x-1.5 text-[11px] text-slate-400">
                  <span class="text-slate-500">#{{ host.id_orden }}</span>
                  <span v-if="host.usuario" class="text-slate-600">•</span>
                  <span v-if="host.usuario" class="text-slate-400 font-mono text-[10px]">u: {{ host.usuario }}</span>
                </div>
              </div>
            </div>
          </td>

          <!-- 2. Cliente / Titular -->
          <td class="py-3.5 px-4">
            <div class="font-semibold text-slate-200 truncate max-w-[180px]">
              {{ host.cliente_empresa || host.cliente_nombre }}
            </div>
            <div class="text-[11px] text-slate-400 truncate max-w-[180px]">
              {{ host.cliente_nombre }}
            </div>
          </td>

          <!-- 3. Plan & Servicio -->
          <td class="py-3.5 px-4">
            <div class="font-medium text-slate-200 truncate max-w-[160px]">
              {{ host.tipo_producto || 'Alojamiento Web' }}
            </div>
            <div v-if="host.dominio" class="text-[11px] text-cyan-400 font-mono truncate max-w-[160px]">
              {{ host.dominio }}
            </div>
          </td>

          <!-- 4. Vencimiento -->
          <td class="py-3.5 px-4">
            <div class="font-medium text-slate-200">
              {{ host.fecha_pago || 'Sin fecha' }}
            </div>
            <div class="mt-0.5">
              <span
                v-if="host.estado_vencimiento === 'vencido'"
                class="inline-flex items-center space-x-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/15 text-rose-400 border border-rose-500/20"
              >
                <i class="pi pi-times-circle text-[9px]"></i>
                <span>Vencido ({{ Math.abs(host.dias_restantes || 0) }}d)</span>
              </span>
              <span
                v-else-if="host.estado_vencimiento === 'prox7'"
                class="inline-flex items-center space-x-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-orange-500/15 text-orange-400 border border-orange-500/20"
              >
                <i class="pi pi-exclamation-triangle text-[9px]"></i>
                <span>¡Crítico! {{ host.dias_restantes }}d</span>
              </span>
              <span
                v-else-if="host.estado_vencimiento === 'prox15'"
                class="inline-flex items-center space-x-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/15 text-amber-400 border border-amber-500/20"
              >
                <i class="pi pi-hourglass text-[9px]"></i>
                <span>Próximo ({{ host.dias_restantes }}d)</span>
              </span>
              <span
                v-else-if="host.estado_vencimiento === 'prox30'"
                class="inline-flex items-center space-x-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-500/15 text-blue-400 border border-blue-500/20"
              >
                <i class="pi pi-calendar text-[9px]"></i>
                <span>{{ host.dias_restantes }} días</span>
              </span>
              <span
                v-else-if="host.estado_vencimiento === 'ok'"
                class="inline-flex items-center space-x-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-emerald-500/15 text-emerald-400 border border-emerald-500/20"
              >
                <i class="pi pi-check text-[9px]"></i>
                <span>Al día ({{ host.dias_restantes }}d)</span>
              </span>
              <span v-else class="text-slate-500 text-[10px]">No registrado</span>
            </div>
          </td>

          <!-- 5. Costo & Frecuencia -->
          <td class="py-3.5 px-4">
            <div class="font-bold text-white">
              {{ formatCurrency(host.costo_producto, host.moneda) }}
            </div>
            <div class="text-[10px] text-slate-400">
              {{ host.frecuencia_label }} • {{ host.moneda }}
            </div>
          </td>

          <!-- 6. Estado -->
          <td class="py-3.5 px-4 text-center">
            <span
              :class="[
                'inline-flex items-center space-x-1 px-2.5 py-1 rounded-full text-[10px] font-semibold border',
                host.estado_producto === 1
                  ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
                  : 'bg-rose-500/10 text-rose-400 border-rose-500/20'
              ]"
            >
              <span class="w-1.5 h-1.5 rounded-full" :class="host.estado_producto === 1 ? 'bg-emerald-400' : 'bg-rose-400'"></span>
              <span>{{ host.estado_producto === 1 ? 'Activo' : 'Inactivo' }}</span>
            </span>
          </td>

          <!-- 7. Acciones -->
          <td class="py-3.5 px-4 text-right">
            <div class="flex items-center justify-end space-x-1">
              <!-- Botón acceso cPanel/WHM -->
              <button
                v-if="host.url_acceso || host.nom_host"
                @click="openPanel(host.url_acceso || host.nom_host)"
                class="p-1.5 rounded-lg bg-cyan-500/10 hover:bg-cyan-500/20 text-cyan-400 border border-cyan-500/20 transition-colors"
                :title="`Entrar al panel (${host.panel_type.toUpperCase()})`"
              >
                <i class="pi pi-external-link text-xs"></i>
              </button>

              <!-- WhatsApp -->
              <button
                v-if="host.cliente_telefono"
                @click="emit('send-whatsapp', host)"
                class="p-1.5 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/20 transition-colors"
                title="Aviso WhatsApp"
              >
                <i class="pi pi-whatsapp text-xs"></i>
              </button>

              <!-- Ver Detalle -->
              <button
                @click="emit('open-detail', host.id_orden)"
                class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition-colors"
                title="Ver detalle completo"
              >
                <i class="pi pi-eye text-xs"></i>
              </button>

              <!-- Editar -->
              <button
                v-if="isSuperAdmin"
                @click="emit('open-edit', host)"
                class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition-colors"
                title="Editar hosting"
              >
                <i class="pi pi-pencil text-xs"></i>
              </button>

              <!-- Eliminar -->
              <button
                v-if="isSuperAdmin"
                @click="emit('delete-hosting', host.id_orden, host.nom_host)"
                class="p-1.5 rounded-lg bg-slate-800 hover:bg-rose-500/20 text-slate-400 hover:text-rose-400 transition-colors"
                title="Mover a papelera"
              >
                <i class="pi pi-trash text-xs"></i>
              </button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
