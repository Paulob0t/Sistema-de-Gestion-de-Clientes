<script setup lang="ts">
import type { PagoDetail, PagoListItem } from '@/api/pagos'

defineProps<{
  isOpen: boolean
  pago: PagoDetail | null
  isLoading: boolean
  isSuperAdmin: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'edit', pago: PagoDetail): void
  (e: 'toggle-status', pago: PagoListItem): void
  (e: 'send-whatsapp', pago: PagoListItem): void
}>()

function formatCurrency(val?: number, moneda = 'MXN') {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: moneda || 'MXN',
  }).format(val || 0)
}

function printReceipt() {
  window.print()
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="isOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm overflow-y-auto"
      @click.self="emit('close')"
    >
      <div class="w-full max-w-xl bg-[#0B101D] border border-slate-800 rounded-3xl shadow-2xl overflow-hidden my-8 animate-fadeIn">
        <!-- Cabecera del Modal / Recibo -->
        <div class="px-6 py-5 border-b border-slate-800 flex items-center justify-between bg-[#0E1526]">
          <div class="flex items-center space-x-3">
            <div
              :class="[
                'w-10 h-10 rounded-2xl flex items-center justify-center font-bold text-sm',
                pago?.estatus === 1
                  ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30'
                  : 'bg-amber-500/15 text-amber-400 border border-amber-500/30'
              ]"
            >
              <i :class="pago?.estatus === 1 ? 'pi pi-check-circle text-lg' : 'pi pi-clock text-lg'"></i>
            </div>
            <div>
              <div class="text-base font-extrabold text-white flex items-center space-x-2">
                <span>Comprobante de Cobro</span>
                <span class="text-xs text-slate-500 font-mono">#{{ pago?.id }}</span>
              </div>
              <div class="text-xs text-cyan-400 font-medium">{{ pago?.tipo_servicio_label }}</div>
            </div>
          </div>
          <div class="flex items-center space-x-1.5">
            <button
              @click="printReceipt"
              class="w-8 h-8 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 flex items-center justify-center transition-colors"
              title="Imprimir comprobante"
            >
              <i class="pi pi-print text-sm"></i>
            </button>
            <button
              @click="emit('close')"
              class="w-8 h-8 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 flex items-center justify-center transition-colors"
            >
              <i class="pi pi-times text-sm"></i>
            </button>
          </div>
        </div>

        <!-- Contenido del Modal -->
        <div v-if="isLoading" class="p-12 flex flex-col items-center justify-center space-y-3">
          <i class="pi pi-spin pi-spinner text-3xl text-cyan-400"></i>
          <span class="text-xs text-slate-400 font-medium">Cargando datos del cobro...</span>
        </div>

        <div v-else-if="pago" class="p-6 space-y-5 text-xs max-h-[75vh] overflow-y-auto custom-scrollbar">
          <!-- 1. Tarjeta de Estado & Monto -->
          <div
            :class="[
              'p-5 rounded-2xl border flex items-center justify-between',
              pago.estatus === 1
                ? 'bg-emerald-950/20 border-emerald-500/30'
                : 'bg-amber-950/20 border-amber-500/30'
            ]"
          >
            <div>
              <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Total del Recibo</span>
              <div
                :class="[
                  'text-2xl font-black tracking-tight mt-0.5',
                  pago.estatus === 1 ? 'text-emerald-300' : 'text-amber-300'
                ]"
              >
                {{ formatCurrency(pago.monto, pago.currency) }}
                <span class="text-xs font-semibold text-slate-400">({{ pago.currency }})</span>
              </div>
            </div>
            <div class="text-right">
              <span
                :class="[
                  'inline-flex items-center space-x-1.5 px-3 py-1 rounded-full text-xs font-bold border',
                  pago.estatus === 1
                    ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/40'
                    : 'bg-amber-500/20 text-amber-400 border-amber-500/40'
                ]"
              >
                <i :class="pago.estatus === 1 ? 'pi pi-check text-[10px]' : 'pi pi-clock text-[10px]'"></i>
                <span>{{ pago.estatus === 1 ? 'Pago Acreditado' : 'Pago Pendiente' }}</span>
              </span>
              <div v-if="pago.fecha_pago" class="text-[10px] text-emerald-400/80 mt-1">
                Acreditado el {{ pago.fecha_pago }}
              </div>
            </div>
          </div>

          <!-- 2. Cliente Titular -->
          <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-800/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
              <span class="text-[10px] uppercase font-bold text-slate-500 tracking-wider">Cliente</span>
              <div class="text-sm font-bold text-white mt-0.5">{{ pago.cliente_empresa || pago.cliente_nombre }}</div>
              <div class="text-slate-400 text-xs">{{ pago.cliente_nombre }} (ID #{{ pago.id_clie }})</div>
              <div v-if="pago.cliente_correo" class="text-slate-400 text-[11px]">{{ pago.cliente_correo }}</div>
            </div>
            <button
              v-if="pago.cliente_telefono"
              @click="emit('send-whatsapp', pago)"
              class="px-3 py-2 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/20 font-semibold flex items-center space-x-1.5 transition-colors self-start sm:self-auto"
            >
              <i class="pi pi-whatsapp text-xs"></i>
              <span>Enviar WhatsApp</span>
            </button>
          </div>

          <!-- 3. Desglose del Concepto -->
          <div class="space-y-2">
            <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Detalle del Concepto</span>
            <div class="p-4 rounded-2xl bg-slate-900/60 border border-slate-800 space-y-2.5">
              <div class="font-semibold text-white text-sm">
                {{ pago.concepto }}
              </div>
              <div v-if="pago.nombre_servicio" class="text-cyan-400 font-mono text-xs flex items-center space-x-1.5">
                <i class="pi pi-link text-[10px]"></i>
                <span>Servicio: {{ pago.nombre_servicio }}</span>
              </div>
            </div>
          </div>

          <!-- 4. Parámetros de Facturación y Método -->
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800">
              <span class="text-slate-500 text-[10px] block">Método de Pago</span>
              <span class="font-semibold text-slate-200">{{ pago.forma_pago_label }}</span>
            </div>
            <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800">
              <span class="text-slate-500 text-[10px] block">Referencia / Folio</span>
              <span class="font-mono text-cyan-300 font-semibold text-xs truncate block">
                {{ pago.id_pago || 'Sin referencia' }}
              </span>
            </div>
            <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800">
              <span class="text-slate-500 text-[10px] block">Fecha de Emisión</span>
              <span class="font-semibold text-slate-200">{{ pago.fecha }}</span>
            </div>
            <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800">
              <span class="text-slate-500 text-[10px] block">Fecha Límite</span>
              <span class="font-semibold text-white">{{ pago.fecha_limite_pago || 'Sin límite' }}</span>
            </div>
            <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800">
              <span class="text-slate-500 text-[10px] block">Tipo de Registro</span>
              <span class="font-semibold text-slate-200">{{ pago.manual === 1 ? 'Manual' : 'Automático' }}</span>
            </div>
            <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800">
              <span class="text-slate-500 text-[10px] block">Frecuencia</span>
              <span class="font-semibold text-slate-200">
                {{ pago.frecuencia_pago === 1 ? 'Mensual' : pago.frecuencia_pago === 2 ? 'Anual' : 'Único' }}
              </span>
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
          <div class="flex items-center space-x-2">
            <button
              v-if="isSuperAdmin && pago"
              @click="emit('toggle-status', pago)"
              :class="[
                'px-4 py-2 rounded-xl font-bold text-xs transition-colors flex items-center space-x-1.5',
                pago.estatus === 1
                  ? 'bg-amber-500/20 text-amber-300 hover:bg-amber-500/30'
                  : 'bg-emerald-600 hover:bg-emerald-500 text-white shadow-lg shadow-emerald-500/20'
              ]"
            >
              <i :class="pago.estatus === 1 ? 'pi pi-undo' : 'pi pi-check'"></i>
              <span>{{ pago.estatus === 1 ? 'Marcar Pendiente' : 'Acreditar Pago' }}</span>
            </button>
            <button
              v-if="isSuperAdmin && pago"
              @click="emit('edit', pago)"
              class="px-4 py-2 rounded-xl bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-500 hover:to-blue-500 text-white text-xs font-bold shadow-lg shadow-cyan-500/20 flex items-center space-x-1.5 transition-all"
            >
              <i class="pi pi-pencil text-xs"></i>
              <span>Editar</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
