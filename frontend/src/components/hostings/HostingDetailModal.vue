<script setup lang="ts">
import { ref } from 'vue'
import type { HostingDetail, HostingListItem } from '@/api/hostings'

defineProps<{
  isOpen: boolean
  hosting: HostingDetail | null
  isLoading: boolean
  isSuperAdmin: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'edit', hosting: HostingDetail): void
  (e: 'send-whatsapp', hosting: HostingListItem): void
}>()

const showPassword = ref(false)

function formatCurrency(val?: number, moneda = 'MXN') {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: moneda,
  }).format(val || 0)
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
  <Teleport to="body">
    <div
      v-if="isOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm overflow-y-auto"
      @click.self="emit('close')"
    >
      <div class="w-full max-w-2xl bg-[#0B101D] border border-slate-800 rounded-3xl shadow-2xl overflow-hidden my-8 animate-fadeIn">
        <!-- Cabecera del Modal -->
        <div class="px-6 py-5 border-b border-slate-800 flex items-center justify-between bg-[#0E1526]">
          <div class="flex items-center space-x-3">
            <div
              :class="[
                'w-10 h-10 rounded-2xl flex items-center justify-center font-bold text-sm',
                hosting?.panel_type === 'whm'
                  ? 'bg-amber-500/15 text-amber-400 border border-amber-500/30'
                  : 'bg-cyan-500/15 text-cyan-400 border border-cyan-500/30'
              ]"
            >
              <i :class="hosting?.panel_type === 'whm' ? 'pi pi-server text-lg' : 'pi pi-globe text-lg'"></i>
            </div>
            <div>
              <div class="text-base font-extrabold text-white flex items-center space-x-2">
                <span>{{ hosting?.nom_host || 'Detalle del Hosting' }}</span>
                <span class="text-xs text-slate-500 font-normal">#{{ hosting?.id_orden }}</span>
              </div>
              <div class="text-xs text-cyan-400 font-medium">{{ hosting?.tipo_producto || 'Alojamiento Web' }}</div>
            </div>
          </div>
          <button
            @click="emit('close')"
            class="w-8 h-8 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 flex items-center justify-center transition-colors"
          >
            <i class="pi pi-times text-sm"></i>
          </button>
        </div>

        <!-- Contenido del Modal -->
        <div v-if="isLoading" class="p-12 flex flex-col items-center justify-center space-y-3">
          <i class="pi pi-spin pi-spinner text-3xl text-cyan-400"></i>
          <span class="text-xs text-slate-400 font-medium">Cargando información del servidor...</span>
        </div>

        <div v-else-if="hosting" class="p-6 space-y-5 text-xs max-h-[75vh] overflow-y-auto custom-scrollbar">
          <!-- 1. Tarjeta de Cliente Titular -->
          <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-800/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
              <span class="text-[10px] uppercase font-bold text-slate-500 tracking-wider">Cliente Asignado</span>
              <div class="text-sm font-bold text-white mt-0.5">{{ hosting.cliente_empresa || hosting.cliente_nombre }}</div>
              <div class="text-slate-400 text-xs">{{ hosting.cliente_nombre }} (ID #{{ hosting.cliente_id }})</div>
            </div>
            <div class="flex items-center space-x-2">
              <button
                v-if="hosting.cliente_telefono"
                @click="emit('send-whatsapp', hosting)"
                class="px-3 py-2 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/20 font-semibold flex items-center space-x-1.5 transition-colors"
              >
                <i class="pi pi-whatsapp text-xs"></i>
                <span>WhatsApp</span>
              </button>
              <button
                v-if="hosting.url_acceso || hosting.nom_host"
                @click="openPanel(hosting.url_acceso || hosting.nom_host)"
                class="px-3 py-2 rounded-xl bg-cyan-500/10 hover:bg-cyan-500/20 text-cyan-400 border border-cyan-500/20 font-semibold flex items-center space-x-1.5 transition-colors"
              >
                <i class="pi pi-external-link text-xs"></i>
                <span>Acceder a {{ hosting.panel_type.toUpperCase() }}</span>
              </button>
            </div>
          </div>

          <!-- 2. Credenciales y Acceso -->
          <div class="space-y-2">
            <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Credenciales de Servidor / Panel</span>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800">
                <span class="text-slate-500 text-[10px] block">Usuario cPanel / WHM</span>
                <span class="font-mono text-cyan-300 font-semibold text-xs">{{ hosting.usuario || 'Sin usuario' }}</span>
              </div>
              <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800 flex items-center justify-between">
                <div>
                  <span class="text-slate-500 text-[10px] block">Contraseña</span>
                  <span v-if="!hosting.contrasena_normal" class="text-slate-500 text-xs italic">Protegida</span>
                  <span v-else class="font-mono text-cyan-300 font-semibold text-xs">
                    {{ showPassword ? hosting.contrasena_normal : '••••••••••••' }}
                  </span>
                </div>
                <button
                  v-if="hosting.contrasena_normal"
                  @click="showPassword = !showPassword"
                  class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white transition-colors"
                  :title="showPassword ? 'Ocultar' : 'Mostrar'"
                >
                  <i :class="showPassword ? 'pi pi-eye-slash text-xs' : 'pi pi-eye text-xs'"></i>
                </button>
              </div>
            </div>
          </div>

          <!-- 3. Fechas y Vencimiento -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800">
              <span class="text-slate-500 text-[10px] block">Fecha Contratación</span>
              <span class="font-semibold text-slate-200">{{ hosting.fecha_contratacion || 'Sin registrar' }}</span>
            </div>
            <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800">
              <span class="text-slate-500 text-[10px] block">Fecha de Pago / Renovación</span>
              <span class="font-semibold text-white">{{ hosting.fecha_pago || 'Sin fecha' }}</span>
            </div>
            <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800">
              <span class="text-slate-500 text-[10px] block">Días Restantes</span>
              <span
                :class="[
                  'font-bold',
                  hosting.estado_vencimiento === 'vencido' ? 'text-rose-400' :
                  hosting.estado_vencimiento === 'prox7' ? 'text-orange-400' :
                  hosting.estado_vencimiento === 'prox15' ? 'text-amber-400' :
                  'text-emerald-400'
                ]"
              >
                {{ hosting.dias_restantes !== null ? `${hosting.dias_restantes} días` : 'N/D' }}
              </span>
            </div>
          </div>

          <!-- 4. Costo y Facturación -->
          <div class="p-4 rounded-2xl bg-gradient-to-r from-blue-950/20 via-slate-900 to-slate-900 border border-slate-800 grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div>
              <span class="text-slate-500 text-[10px] block">Precio / Costo</span>
              <span class="font-bold text-white text-sm">{{ formatCurrency(hosting.costo_producto, hosting.moneda) }}</span>
            </div>
            <div>
              <span class="text-slate-500 text-[10px] block">Moneda</span>
              <span class="font-semibold text-slate-200">{{ hosting.moneda }}</span>
            </div>
            <div>
              <span class="text-slate-500 text-[10px] block">Periodicidad</span>
              <span class="font-semibold text-slate-200">{{ hosting.frecuencia_label }}</span>
            </div>
            <div>
              <span class="text-slate-500 text-[10px] block">Estado</span>
              <span class="font-semibold" :class="hosting.estado_producto === 1 ? 'text-emerald-400' : 'text-rose-400'">
                {{ hosting.estado_producto === 1 ? 'Activo' : 'Inactivo' }}
              </span>
            </div>
          </div>

          <!-- 5. DNS y Nameservers -->
          <div v-if="hosting.ns1 || hosting.ns2 || hosting.dns" class="space-y-2">
            <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Configuración DNS / Nameservers</span>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs font-mono">
              <div v-if="hosting.ns1" class="p-2.5 rounded-xl bg-slate-900 border border-slate-800 flex items-center justify-between">
                <span class="text-slate-500 text-[10px]">NS1:</span>
                <span class="text-cyan-300">{{ hosting.ns1 }}</span>
              </div>
              <div v-if="hosting.ns2" class="p-2.5 rounded-xl bg-slate-900 border border-slate-800 flex items-center justify-between">
                <span class="text-slate-500 text-[10px]">NS2:</span>
                <span class="text-cyan-300">{{ hosting.ns2 }}</span>
              </div>
              <div v-if="hosting.dns" class="col-span-1 sm:col-span-2 p-2.5 rounded-xl bg-slate-900 border border-slate-800 flex items-center justify-between">
                <span class="text-slate-500 text-[10px]">DNS Secundarios:</span>
                <span class="text-slate-300">{{ hosting.dns }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer del Modal -->
        <div class="px-6 py-4 border-t border-slate-800 bg-[#0E1526] flex items-center justify-between">
          <button
            @click="emit('close')"
            class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition-colors"
          >
            Cerrar
          </button>
          <button
            v-if="isSuperAdmin && hosting"
            @click="emit('edit', hosting)"
            class="px-4 py-2 rounded-xl bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-500 hover:to-blue-500 text-white text-xs font-bold shadow-lg shadow-cyan-500/20 flex items-center space-x-1.5 transition-all"
          >
            <i class="pi pi-pencil text-xs"></i>
            <span>Editar Hosting</span>
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
