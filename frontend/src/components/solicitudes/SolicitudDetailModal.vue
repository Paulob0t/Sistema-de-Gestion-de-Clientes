<script setup lang="ts">
import { ref, watch } from 'vue'
import type { SolicitudDetail, AgenteSimple } from '@/api/solicitudes'

const props = defineProps<{
  visible: boolean
  solicitud: SolicitudDetail | null
  loading: boolean
  agentes: AgenteSimple[]
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'update-status', newStatus: string): void
  (e: 'assign-agents', agentIds: number[]): void
  (e: 'add-note', text: string): void
}>()

const newNoteText = ref('')
const selectedAgentIds = ref<number[]>([])

watch(
  () => props.solicitud,
  (sol) => {
    if (sol) {
      selectedAgentIds.value = sol.agentes.map(a => a.id)
      newNoteText.value = ''
    }
  },
  { immediate: true }
)

function submitNote() {
  if (!newNoteText.value.trim()) return
  emit('add-note', newNoteText.value.trim())
  newNoteText.value = ''
}

function handleAgentToggle(aid: number) {
  if (selectedAgentIds.value.includes(aid)) {
    selectedAgentIds.value = selectedAgentIds.value.filter(id => id !== aid)
  } else {
    selectedAgentIds.value.push(aid)
  }
  emit('assign-agents', selectedAgentIds.value)
}
</script>

<template>
  <Teleport to="body">
    <transition name="fade">
      <div
        v-if="visible"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm"
      >
        <div class="w-full max-w-4xl max-h-[90vh] bg-[#0F172A] border border-slate-800 rounded-2xl shadow-2xl flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200">
          <!-- Cabecera -->
          <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-[#0B1120]">
            <div class="flex items-center space-x-3">
              <div class="w-9 h-9 rounded-xl bg-blue-500/10 border border-blue-500/20 text-blue-400 flex items-center justify-center">
                <i class="pi pi-ticket text-base"></i>
              </div>
              <div>
                <div class="flex items-center space-x-2">
                  <span class="text-xs font-mono text-slate-400 font-bold">#{{ solicitud?.id }}</span>
                  <h3 class="text-sm font-bold text-white truncate max-w-md">{{ solicitud?.titulo }}</h3>
                </div>
                <p class="text-xs text-slate-400">Detalle del requerimiento y línea de comentarios</p>
              </div>
            </div>
            <button
              @click="emit('close')"
              class="w-8 h-8 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 flex items-center justify-center transition-colors"
            >
              <i class="pi pi-times text-sm"></i>
            </button>
          </div>

          <!-- Contenido Dividido en 2 Columnas -->
          <div class="flex-1 overflow-y-auto p-6 grid grid-cols-1 lg:grid-cols-3 gap-6 bg-[#070B14] custom-scrollbar">
            <!-- Columna Izquierda: Información de la Solicitud (2/3) -->
            <div class="lg:col-span-2 space-y-5">
              <!-- Descripción -->
              <div class="p-4 rounded-xl bg-slate-900/60 border border-slate-800 space-y-2">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Descripción</span>
                <div class="text-xs text-slate-200 whitespace-pre-wrap leading-relaxed">
                  {{ solicitud?.descripcion_texto || 'Sin descripción adicional proporcionada.' }}
                </div>
              </div>

              <!-- Sección de Notas / Timeline -->
              <div class="space-y-3">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider flex items-center space-x-1.5">
                  <i class="pi pi-comments text-blue-400"></i>
                  <span>Notas & Comentarios Internos ({{ solicitud?.notas?.length || 0 }})</span>
                </span>

                <!-- Input para agregar nota -->
                <div class="flex items-start space-x-2">
                  <textarea
                    v-model="newNoteText"
                    rows="2"
                    placeholder="Escribir una actualización o comentario..."
                    class="flex-1 bg-slate-900 border border-slate-800 rounded-xl p-3 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 resize-none"
                  ></textarea>
                  <button
                    @click="submitNote"
                    :disabled="!newNoteText.trim()"
                    class="px-3.5 py-3 rounded-xl bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white text-xs font-bold transition-all shrink-0"
                  >
                    <i class="pi pi-send"></i>
                  </button>
                </div>

                <!-- Lista de Notas -->
                <div v-if="solicitud?.notas && solicitud.notas.length > 0" class="space-y-2.5 max-h-64 overflow-y-auto custom-scrollbar pr-1">
                  <div
                    v-for="nota in solicitud.notas"
                    :key="nota.id"
                    class="p-3 rounded-xl bg-slate-900/40 border border-slate-800/80 space-y-1 text-xs"
                  >
                    <div class="flex items-center justify-between text-[11px]">
                      <span class="font-bold text-blue-400">{{ nota.autor }}</span>
                      <span class="text-slate-500">{{ nota.fecha_creacion }}</span>
                    </div>
                    <p class="text-slate-300 leading-relaxed">{{ nota.nota_texto }}</p>
                  </div>
                </div>
                <div v-else class="text-xs text-slate-500 italic py-2">
                  No hay notas registradas para esta solicitud.
                </div>
              </div>
            </div>

            <!-- Columna Derecha: Metadatos y Asignación (1/3) -->
            <div class="space-y-4">
              <!-- Estado -->
              <div class="p-3.5 rounded-xl bg-slate-900/60 border border-slate-800 space-y-2 text-xs">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Estado Actual</span>
                <div class="grid grid-cols-3 gap-1">
                  <button
                    v-for="st in ['Pendiente', 'En Proceso', 'Finalizado']"
                    :key="st"
                    @click="emit('update-status', st)"
                    :class="[
                      'py-1.5 px-2 rounded-lg text-[10px] font-bold transition-colors text-center',
                      solicitud?.estado === st
                        ? 'bg-blue-600 text-white shadow-sm'
                        : 'bg-slate-800 text-slate-400 hover:text-white'
                    ]"
                  >
                    {{ st }}
                  </button>
                </div>
              </div>

              <!-- Metadatos Básicos -->
              <div class="p-3.5 rounded-xl bg-slate-900/60 border border-slate-800 space-y-2.5 text-xs">
                <div>
                  <span class="text-slate-500 text-[10px] uppercase font-bold">Cliente</span>
                  <div class="font-semibold text-white mt-0.5">{{ solicitud?.cliente_nombre || 'General' }}</div>
                </div>
                <div>
                  <span class="text-slate-500 text-[10px] uppercase font-bold">Prioridad</span>
                  <div class="font-semibold text-white mt-0.5">{{ solicitud?.prioridad }}</div>
                </div>
                <div>
                  <span class="text-slate-500 text-[10px] uppercase font-bold">Fecha Solicitud</span>
                  <div class="text-slate-300 mt-0.5">{{ solicitud?.fecha_solicitud }}</div>
                </div>
                <div v-if="solicitud?.fecha_lim">
                  <span class="text-slate-500 text-[10px] uppercase font-bold">Fecha Límite</span>
                  <div class="text-amber-400 font-semibold mt-0.5">{{ solicitud?.fecha_lim }}</div>
                </div>
              </div>

              <!-- Asignación de Agentes (Multi-select) -->
              <div class="p-3.5 rounded-xl bg-slate-900/60 border border-slate-800 space-y-2 text-xs">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Agentes Asignados</span>
                <div class="space-y-1.5 max-h-44 overflow-y-auto custom-scrollbar">
                  <label
                    v-for="ag in agentes"
                    :key="ag.id"
                    class="flex items-center space-x-2 p-1.5 rounded-lg hover:bg-slate-800/60 cursor-pointer transition-colors"
                  >
                    <input
                      type="checkbox"
                      :checked="selectedAgentIds.includes(ag.id)"
                      @change="handleAgentToggle(ag.id)"
                      class="rounded border-slate-700 bg-slate-900 text-blue-600 focus:ring-blue-500"
                    />
                    <span class="text-slate-300 text-xs">{{ ag.nombre }}</span>
                  </label>
                </div>
              </div>
            </div>
          </div>

          <!-- Pie del Modal -->
          <div class="px-6 py-3.5 border-t border-slate-800 bg-[#0B1120] flex items-center justify-end">
            <button
              @click="emit('close')"
              class="px-4 py-2 rounded-xl bg-slate-900 border border-slate-800 text-xs font-semibold text-slate-300 hover:text-white"
            >
              Cerrar
            </button>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>
