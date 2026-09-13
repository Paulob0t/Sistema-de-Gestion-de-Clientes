<script setup lang="ts">
import type { RecordatorioSendResponse } from '@/api/recordatorios'

defineProps<{
  visible: boolean
  count: number
  sending: boolean
  result: RecordatorioSendResponse | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'confirm'): void
}>()
</script>

<template>
  <Teleport to="body">
    <transition name="fade">
      <div
        v-if="visible"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm"
      >
        <div class="w-full max-w-lg bg-[#0F172A] border border-slate-800 rounded-2xl shadow-2xl flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200">
          <!-- Cabecera -->
          <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-[#0B1120]">
            <div class="flex items-center space-x-3">
              <div class="w-9 h-9 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 flex items-center justify-center">
                <i class="pi pi-send text-base"></i>
              </div>
              <div>
                <h3 class="text-sm font-bold text-white">Envío Masivo de Recordatorios</h3>
                <p class="text-xs text-slate-400">Despacho automatizado vía SMTP</p>
              </div>
            </div>
            <button
              v-if="!sending"
              @click="emit('close')"
              class="w-8 h-8 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 flex items-center justify-center transition-colors"
            >
              <i class="pi pi-times text-sm"></i>
            </button>
          </div>

          <!-- Contenido -->
          <div class="p-6 space-y-4 text-xs">
            <!-- Estado 1: Confirmación Previa -->
            <div v-if="!result && !sending" class="space-y-3">
              <p class="text-slate-300 leading-relaxed">
                Está a punto de enviar <strong>{{ count }} recordatorios de correo</strong> a los clientes y servicios seleccionados.
              </p>
              <div class="p-3.5 rounded-xl bg-blue-500/10 border border-blue-500/20 text-blue-300 flex items-start space-x-2.5">
                <i class="pi pi-info-circle text-base text-blue-400 shrink-0 mt-0.5"></i>
                <div>
                  <span class="font-semibold block mb-0.5">Plantilla Inteligente</span>
                  Cada destinatario recibirá su correo personalizado con su propio concepto, importe y enlaces correspondientes.
                </div>
              </div>
            </div>

            <!-- Estado 2: Enviando / Spinner -->
            <div v-else-if="sending" class="py-8 flex flex-col items-center justify-center text-center space-y-3">
              <i class="pi pi-spin pi-spinner text-4xl text-blue-500"></i>
              <div class="text-sm font-bold text-white">Enviando recordatorios...</div>
              <p class="text-xs text-slate-400">Por favor espere mientras el servidor procesa y despacha los correos.</p>
            </div>

            <!-- Estado 3: Resultados -->
            <div v-else-if="result" class="space-y-4">
              <div
                :class="[
                  'p-4 rounded-xl border flex items-center space-x-3',
                  result.success ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300' : 'bg-rose-500/10 border-rose-500/30 text-rose-300'
                ]"
              >
                <i :class="['pi text-xl', result.success ? 'pi-check-circle text-emerald-400' : 'pi-times-circle text-rose-400']"></i>
                <div>
                  <div class="font-bold text-sm">{{ result.message }}</div>
                  <div class="text-[11px] opacity-80 mt-0.5">
                    Exitosos: {{ result.enviados }} | Fallidos / Omitidos: {{ result.fallidos }}
                  </div>
                </div>
              </div>

              <!-- Lista de detalles si hay -->
              <div v-if="result.detalles && result.detalles.length > 0" class="max-h-48 overflow-y-auto rounded-xl bg-slate-950 border border-slate-800 p-2 divide-y divide-slate-800/60 custom-scrollbar">
                <div
                  v-for="(det, idx) in result.detalles"
                  :key="idx"
                  class="py-1.5 px-2 flex items-center justify-between text-[11px]"
                >
                  <span class="text-slate-300 truncate max-w-[240px]">{{ det.email || det.reason || 'Sin correo' }}</span>
                  <span
                    :class="[
                      'px-2 py-0.5 rounded text-[10px] font-semibold',
                      det.status === 'sent' ? 'bg-emerald-500/15 text-emerald-400' : 'bg-rose-500/15 text-rose-400'
                    ]"
                  >
                    {{ det.status === 'sent' ? 'Enviado' : 'Error' }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Pie del Modal -->
          <div class="px-6 py-4 border-t border-slate-800 bg-[#0B1120] flex items-center justify-end space-x-3">
            <button
              v-if="!result"
              @click="emit('close')"
              :disabled="sending"
              class="px-4 py-2 rounded-xl bg-slate-900 border border-slate-800 text-xs font-semibold text-slate-300 hover:text-white"
            >
              Cancelar
            </button>

            <button
              v-if="!result"
              @click="emit('confirm')"
              :disabled="sending || count === 0"
              class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 disabled:opacity-50 text-white text-xs font-bold shadow-lg shadow-blue-500/25 flex items-center space-x-2"
            >
              <i class="pi pi-send text-xs"></i>
              <span>Confirmar Envío ({{ count }})</span>
            </button>

            <button
              v-else
              @click="emit('close')"
              class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold shadow-md"
            >
              Entendido / Cerrar
            </button>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>
