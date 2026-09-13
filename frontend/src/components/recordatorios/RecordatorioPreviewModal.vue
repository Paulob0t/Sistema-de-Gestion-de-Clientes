<script setup lang="ts">
import { ref, watch } from 'vue'
import type { RecordatorioPreviewResponse } from '@/api/recordatorios'

const props = defineProps<{
  visible: boolean
  previewData: RecordatorioPreviewResponse | null
  loading: boolean
  sending: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'send', payload: { asunto: string; customEmail?: string }): void
}>()

const editableSubject = ref('')
const customRecipient = ref('')

watch(
  () => props.previewData,
  (newVal) => {
    if (newVal) {
      editableSubject.value = newVal.asunto
      customRecipient.value = newVal.destinatario_correo
    }
  },
  { immediate: true }
)

function handleSend() {
  emit('send', {
    asunto: editableSubject.value,
    customEmail: customRecipient.value
  })
}
</script>

<template>
  <Teleport to="body">
    <transition name="fade">
      <div
        v-if="visible"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm"
      >
        <div class="w-full max-w-3xl max-h-[90vh] bg-[#0F172A] border border-slate-800 rounded-2xl shadow-2xl flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200">
          <!-- Cabecera -->
          <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-[#0B1120]">
            <div class="flex items-center space-x-3">
              <div class="w-9 h-9 rounded-xl bg-blue-500/10 border border-blue-500/20 text-blue-400 flex items-center justify-center">
                <i class="pi pi-envelope text-base"></i>
              </div>
              <div>
                <h3 class="text-sm font-bold text-white">Vista Previa del Recordatorio</h3>
                <p class="text-xs text-slate-400">Verifique el contenido antes de despachar el correo electrónico</p>
              </div>
            </div>
            <button
              @click="emit('close')"
              class="w-8 h-8 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 flex items-center justify-center transition-colors"
            >
              <i class="pi pi-times text-sm"></i>
            </button>
          </div>

          <!-- Metadatos de Envío Editables -->
          <div class="px-6 py-3.5 bg-slate-900/60 border-b border-slate-800/80 space-y-2.5 text-xs">
            <!-- Destinatario -->
            <div class="flex flex-col sm:flex-row sm:items-center gap-2">
              <span class="w-24 font-semibold text-slate-400">Para:</span>
              <div class="flex-1 flex items-center space-x-2">
                <input
                  v-model="customRecipient"
                  type="email"
                  class="flex-1 bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 font-mono text-xs"
                  placeholder="correo@ejemplo.com"
                />
                <span class="text-slate-500 text-[11px] whitespace-nowrap">({{ previewData?.destinatario_nombre || 'Cliente' }})</span>
              </div>
            </div>

            <!-- Asunto -->
            <div class="flex flex-col sm:flex-row sm:items-center gap-2">
              <span class="w-24 font-semibold text-slate-400">Asunto:</span>
              <input
                v-model="editableSubject"
                type="text"
                class="flex-1 bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 font-semibold text-xs"
                placeholder="Asunto del correo"
              />
            </div>
          </div>

          <!-- Cuerpo HTML con Iframe / Sandbox -->
          <div class="flex-1 overflow-y-auto p-4 bg-[#070B14] min-h-[340px]">
            <div v-if="loading" class="h-64 flex flex-col items-center justify-center text-slate-500">
              <i class="pi pi-spin pi-spinner text-3xl text-blue-500 mb-2"></i>
              <span class="text-xs">Generando plantilla de correo...</span>
            </div>

            <div v-else-if="previewData?.html" class="w-full rounded-xl overflow-hidden shadow-inner border border-slate-800 bg-white">
              <iframe
                :srcdoc="previewData.html"
                class="w-full h-[400px] border-0"
                sandbox="allow-same-origin"
              ></iframe>
            </div>
          </div>

          <!-- Pie del Modal: Botones -->
          <div class="px-6 py-4 border-t border-slate-800 bg-[#0B1120] flex items-center justify-between">
            <button
              @click="emit('close')"
              class="px-4 py-2 rounded-xl bg-slate-900 border border-slate-800 text-xs font-semibold text-slate-300 hover:text-white hover:border-slate-700 transition-colors"
            >
              Cancelar
            </button>

            <button
              @click="handleSend"
              :disabled="sending || !customRecipient"
              class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 disabled:opacity-50 text-white text-xs font-bold shadow-lg shadow-blue-500/25 flex items-center space-x-2 transition-all transform active:scale-95"
            >
              <i v-if="sending" class="pi pi-spin pi-spinner text-xs"></i>
              <i v-else class="pi pi-send text-xs"></i>
              <span>{{ sending ? 'Despachando...' : 'Enviar Correo Ahora' }}</span>
            </button>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>
