<script setup lang="ts">
import { reactive, watch } from 'vue'
import type { SolicitudItem, AgenteSimple } from '@/api/solicitudes'

const props = defineProps<{
  visible: boolean
  solicitudToEdit: SolicitudItem | null
  agentes: AgenteSimple[]
  clients: Array<{ id: number; nombre_contacto?: string; empresa?: string }>
  saving: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'save', payload: any): void
}>()

const form = reactive({
  id: null as number | null,
  titulo: '',
  descripcion: '',
  id_cliente: null as number | null,
  agentes_ids: [] as number[],
  prioridad: 'Media',
  fecha_lim: '',
  repetir: 0,
})

watch(
  () => props.solicitudToEdit,
  (sol) => {
    if (sol) {
      form.id = sol.id
      form.titulo = sol.titulo
      form.descripcion = sol.descripcion_texto
      form.id_cliente = sol.id_cliente ?? null
      form.agentes_ids = sol.agentes.map(a => a.id)
      form.prioridad = sol.prioridad || 'Media'
      form.fecha_lim = sol.fecha_lim || ''
      form.repetir = 0
    } else {
      form.id = null
      form.titulo = ''
      form.descripcion = ''
      form.id_cliente = null
      form.agentes_ids = []
      form.prioridad = 'Media'
      form.fecha_lim = ''
      form.repetir = 0
    }
  },
  { immediate: true }
)

function handleAgentToggle(aid: number) {
  if (form.agentes_ids.includes(aid)) {
    form.agentes_ids = form.agentes_ids.filter(id => id !== aid)
  } else {
    form.agentes_ids.push(aid)
  }
}

function handleSubmit() {
  if (!form.titulo.trim()) return
  emit('save', { ...form })
}
</script>

<template>
  <Teleport to="body">
    <transition name="fade">
      <div
        v-if="visible"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm"
      >
        <div class="w-full max-w-2xl max-h-[90vh] bg-[#0F172A] border border-slate-800 rounded-2xl shadow-2xl flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200">
          <!-- Cabecera -->
          <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-[#0B1120]">
            <div class="flex items-center space-x-3">
              <div class="w-9 h-9 rounded-xl bg-blue-500/10 border border-blue-500/20 text-blue-400 flex items-center justify-center">
                <i :class="['pi text-base', form.id ? 'pi-pencil' : 'pi-plus']"></i>
              </div>
              <div>
                <h3 class="text-sm font-bold text-white">
                  {{ form.id ? 'Editar Solicitud #' + form.id : 'Nueva Solicitud de Agente' }}
                </h3>
                <p class="text-xs text-slate-400">Complete los detalles del requerimiento o ticket</p>
              </div>
            </div>
            <button
              @click="emit('close')"
              class="w-8 h-8 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 flex items-center justify-center transition-colors"
            >
              <i class="pi pi-times text-sm"></i>
            </button>
          </div>

          <!-- Formulario -->
          <form @submit.prevent="handleSubmit" class="flex-1 overflow-y-auto p-6 space-y-4 text-xs bg-[#070B14] custom-scrollbar">
            <!-- Título -->
            <div class="space-y-1">
              <label class="font-semibold text-slate-300">Título de la Solicitud *</label>
              <input
                v-model="form.titulo"
                type="text"
                required
                placeholder="Ej: Corrección de formulario de contacto en sitio web"
                class="w-full bg-[#0D1527] border border-slate-800 rounded-xl px-3.5 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500"
              />
            </div>

            <!-- Descripción -->
            <div class="space-y-1">
              <label class="font-semibold text-slate-300">Descripción Detallada</label>
              <textarea
                v-model="form.descripcion"
                rows="4"
                placeholder="Instrucciones, requerimientos técnicos, enlaces..."
                class="w-full bg-[#0D1527] border border-slate-800 rounded-xl p-3 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 resize-none"
              ></textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <!-- Cliente -->
              <div class="space-y-1">
                <label class="font-semibold text-slate-300">Cliente Asociado</label>
                <select
                  v-model="form.id_cliente"
                  class="w-full bg-[#0D1527] border border-slate-800 rounded-xl px-3 py-2.5 text-white focus:outline-none focus:border-blue-500"
                >
                  <option :value="null">Sin cliente específico / General</option>
                  <option v-for="c in clients" :key="c.id" :value="c.id">
                    {{ c.nombre_contacto || c.empresa || ('Cliente #' + c.id) }}
                  </option>
                </select>
              </div>

              <!-- Prioridad -->
              <div class="space-y-1">
                <label class="font-semibold text-slate-300">Prioridad</label>
                <select
                  v-model="form.prioridad"
                  class="w-full bg-[#0D1527] border border-slate-800 rounded-xl px-3 py-2.5 text-white focus:outline-none focus:border-blue-500"
                >
                  <option value="Alta">Alta</option>
                  <option value="Media">Media</option>
                  <option value="Baja">Baja</option>
                </select>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <!-- Fecha Límite -->
              <div class="space-y-1">
                <label class="font-semibold text-slate-300">Fecha Límite</label>
                <input
                  v-model="form.fecha_lim"
                  type="date"
                  class="w-full bg-[#0D1527] border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-blue-500"
                />
              </div>

              <!-- Repetición (Si es nueva) -->
              <div v-if="!form.id" class="space-y-1">
                <label class="font-semibold text-slate-300">Programar Repetición</label>
                <select
                  v-model="form.repetir"
                  class="w-full bg-[#0D1527] border border-slate-800 rounded-xl px-3 py-2.5 text-white focus:outline-none focus:border-blue-500"
                >
                  <option :value="0">No repetir (Única vez)</option>
                  <option :value="1">Diario</option>
                  <option :value="2">Semanal</option>
                  <option :value="3">Mensual</option>
                </select>
              </div>
            </div>

            <!-- Asignar Agentes (Multi-select) -->
            <div class="space-y-1.5 pt-2 border-t border-slate-800">
              <label class="font-semibold text-slate-300 block">Asignar Agentes / Desarrolladores</label>
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 max-h-36 overflow-y-auto custom-scrollbar p-1">
                <label
                  v-for="ag in agentes"
                  :key="ag.id"
                  class="flex items-center space-x-2 p-2 rounded-xl bg-slate-900 border border-slate-800 hover:border-slate-700 cursor-pointer transition-colors"
                >
                  <input
                    type="checkbox"
                    :checked="form.agentes_ids.includes(ag.id)"
                    @change="handleAgentToggle(ag.id)"
                    class="rounded border-slate-700 bg-slate-900 text-blue-600 focus:ring-blue-500"
                  />
                  <span class="text-slate-300 truncate text-[11px]">{{ ag.nombre }}</span>
                </label>
              </div>
            </div>
          </form>

          <!-- Pie del Modal -->
          <div class="px-6 py-4 border-t border-slate-800 bg-[#0B1120] flex items-center justify-between">
            <button
              @click="emit('close')"
              type="button"
              class="px-4 py-2 rounded-xl bg-slate-900 border border-slate-800 text-xs font-semibold text-slate-300 hover:text-white"
            >
              Cancelar
            </button>

            <button
              @click="handleSubmit"
              :disabled="saving || !form.titulo.trim()"
              class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 disabled:opacity-50 text-white text-xs font-bold shadow-lg shadow-blue-500/25 flex items-center space-x-2 transition-all transform active:scale-95"
            >
              <i v-if="saving" class="pi pi-spin pi-spinner text-xs"></i>
              <i v-else class="pi pi-check text-xs"></i>
              <span>{{ saving ? 'Guardando...' : (form.id ? 'Guardar Cambios' : 'Crear Solicitud') }}</span>
            </button>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>
