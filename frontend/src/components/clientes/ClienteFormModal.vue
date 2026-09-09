<script setup lang="ts">
import type { ClientePayload } from '@/api/clientes'

defineProps<{
  isOpen: boolean
  isEditing: boolean
  isSaving: boolean
  formData: ClientePayload
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'submit'): void
}>()
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-950/80 backdrop-blur-md"
  >
    <div class="w-full max-w-2xl bg-slate-900 border border-slate-800 rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
      <!-- Header Form -->
      <div class="p-6 bg-slate-950/60 border-b border-slate-800 flex items-center justify-between shrink-0">
        <h3 class="text-base font-bold text-white">
          {{ isEditing ? 'Editar Cliente' : 'Registrar Nuevo Cliente' }}
        </h3>
        <button
          @click="emit('close')"
          class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white transition-colors"
        >
          <i class="pi pi-times text-sm"></i>
        </button>
      </div>

      <!-- Form Fields -->
      <form @submit.prevent="emit('submit')" class="p-6 overflow-y-auto flex-1 space-y-4 text-xs">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <!-- Empresa -->
          <div>
            <label class="block text-slate-300 font-semibold mb-1">Nombre de la Empresa / Negocio *</label>
            <input
              v-model="formData.empresa"
              type="text"
              required
              placeholder="Ej. Acme Corp"
              class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-white focus:outline-none focus:border-blue-500"
            />
          </div>

          <!-- Contacto -->
          <div>
            <label class="block text-slate-300 font-semibold mb-1">Persona de Contacto *</label>
            <input
              v-model="formData.nombre_contacto"
              type="text"
              required
              placeholder="Ej. Juan Pérez"
              class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-white focus:outline-none focus:border-blue-500"
            />
          </div>

          <!-- Correo -->
          <div>
            <label class="block text-slate-300 font-semibold mb-1">Correo Electrónico</label>
            <input
              v-model="formData.correo"
              type="email"
              placeholder="contacto@empresa.com"
              class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-white focus:outline-none focus:border-blue-500"
            />
          </div>

          <!-- Teléfono -->
          <div>
            <label class="block text-slate-300 font-semibold mb-1">Teléfono / WhatsApp</label>
            <input
              v-model="formData.telefono"
              type="text"
              placeholder="+52 477 123 4567"
              class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-white focus:outline-none focus:border-blue-500"
            />
          </div>

          <!-- RFC -->
          <div>
            <label class="block text-slate-300 font-semibold mb-1">RFC</label>
            <input
              v-model="formData.rfc"
              type="text"
              placeholder="XAXX010101000"
              class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-white uppercase focus:outline-none focus:border-blue-500"
            />
          </div>

          <!-- Razón Social -->
          <div>
            <label class="block text-slate-300 font-semibold mb-1">Razón Social</label>
            <input
              v-model="formData.rsocial"
              type="text"
              placeholder="Razón Social SA de CV"
              class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-white focus:outline-none focus:border-blue-500"
            />
          </div>
        </div>

        <!-- Dirección -->
        <div class="pt-2 border-t border-slate-800 space-y-3">
          <h5 class="font-bold text-slate-300">Dirección y Ubicación</h5>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="sm:col-span-2">
              <input
                v-model="formData.calle"
                type="text"
                placeholder="Calle y Número"
                class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-white focus:outline-none focus:border-blue-500"
              />
            </div>
            <div>
              <input
                v-model="formData.col"
                type="text"
                placeholder="Colonia"
                class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-white focus:outline-none focus:border-blue-500"
              />
            </div>
            <div>
              <input
                v-model="formData.cp"
                type="text"
                placeholder="Código Postal"
                class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-white focus:outline-none focus:border-blue-500"
              />
            </div>
            <div>
              <input
                v-model="formData.ciudad"
                type="text"
                placeholder="Ciudad"
                class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-white focus:outline-none focus:border-blue-500"
              />
            </div>
            <div>
              <input
                v-model="formData.estado"
                type="text"
                placeholder="Estado"
                class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-white focus:outline-none focus:border-blue-500"
              />
            </div>
          </div>
        </div>

        <!-- Especificaciones -->
        <div class="pt-2 border-t border-slate-800">
          <label class="block text-slate-300 font-semibold mb-1">Notas o Requerimientos</label>
          <textarea
            v-model="formData.especificacion"
            rows="3"
            placeholder="Detalles sobre los servicios contratados, notas internas, etc."
            class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-white focus:outline-none focus:border-blue-500 resize-none"
          ></textarea>
        </div>

        <!-- Botones de Acción -->
        <div class="pt-4 border-t border-slate-800 flex items-center justify-end space-x-3 shrink-0">
          <button
            type="button"
            @click="emit('close')"
            class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition-colors"
          >
            Cancelar
          </button>
          <button
            type="submit"
            :disabled="isSaving"
            class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold shadow-lg shadow-blue-500/20 flex items-center space-x-2 transition-all disabled:opacity-50"
          >
            <i v-if="isSaving" class="pi pi-spin pi-spinner text-xs"></i>
            <span>{{ isEditing ? 'Guardar Cambios' : 'Registrar Cliente' }}</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
