<script setup lang="ts">
import type { DominioPayload } from '@/api/dominios'

defineProps<{
  isOpen: boolean
  isEditing: boolean
  isSaving: boolean
  formData: DominioPayload
  clientesList: Array<{ id: number; empresa: string; nombre_contacto: string }>
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
    <div class="w-full max-w-2xl bg-[#0D121F] border border-slate-800 rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
      <!-- Header -->
      <div class="p-5 bg-slate-950/80 border-b border-slate-800 flex items-center justify-between shrink-0">
        <h3 class="text-base font-bold text-white">
          {{ isEditing ? 'Editar Dominio Web' : 'Registrar Nuevo Dominio' }}
        </h3>
        <button
          @click="emit('close')"
          class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white transition-colors"
        >
          <i class="pi pi-times text-sm"></i>
        </button>
      </div>

      <!-- Form -->
      <form @submit.prevent="emit('submit')" class="p-6 overflow-y-auto flex-1 space-y-4 text-xs">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <!-- Cliente Asignado -->
          <div class="sm:col-span-2">
            <label class="block text-slate-300 font-semibold mb-1">Cliente Titular *</label>
            <select
              v-model="formData.cliente_id"
              required
              class="w-full px-3 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-white focus:outline-none focus:border-blue-500"
            >
              <option disabled :value="0">-- Selecciona un Cliente --</option>
              <option v-for="c in clientesList" :key="c.id" :value="c.id">
                {{ c.empresa }} ({{ c.nombre_contacto }})
              </option>
            </select>
          </div>

          <!-- Dominio URL -->
          <div>
            <label class="block text-slate-300 font-semibold mb-1">Nombre del Dominio (URL) *</label>
            <input
              v-model="formData.url_dominio"
              type="text"
              required
              placeholder="ejemplo.com"
              class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-white focus:outline-none focus:border-blue-500"
            />
          </div>

          <!-- Proveedor / Registrador -->
          <div>
            <label class="block text-slate-300 font-semibold mb-1">Registrador / Proveedor</label>
            <input
              v-model="formData.proveedor"
              type="text"
              placeholder="GoDaddy, HostGator, NexusBot..."
              class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-white focus:outline-none focus:border-blue-500"
            />
          </div>

          <!-- Costo Anual -->
          <div>
            <label class="block text-slate-300 font-semibold mb-1">Costo Anual (MXN)</label>
            <input
              v-model.number="formData.costo_dominio"
              type="number"
              step="0.01"
              placeholder="550.00"
              class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-white focus:outline-none focus:border-blue-500"
            />
          </div>

          <!-- Fecha de Vencimiento / Renovación -->
          <div>
            <label class="block text-slate-300 font-semibold mb-1">Fecha de Renovación</label>
            <input
              v-model="formData.fecha_pago"
              type="date"
              class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-white focus:outline-none focus:border-blue-500"
            />
          </div>

          <!-- Estado de Pago -->
          <div>
            <label class="block text-slate-300 font-semibold mb-1">Estado de Cobro</label>
            <select
              v-model.number="formData.estatus_pago"
              class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-white focus:outline-none focus:border-blue-500"
            >
              <option :value="1">Pagado / Al corriente</option>
              <option :value="0">Pendiente de Pago</option>
            </select>
          </div>

          <!-- Gestión Registrado -->
          <div>
            <label class="block text-slate-300 font-semibold mb-1">Tipo de Gestión</label>
            <select
              v-model.number="formData.registrado"
              class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-white focus:outline-none focus:border-blue-500"
            >
              <option :value="1">Registrado por Nosotros (NexusBot)</option>
              <option :value="0">Administrado Externo</option>
            </select>
          </div>
        </div>

        <!-- Servidores DNS -->
        <div class="pt-2 border-t border-slate-800 space-y-3">
          <h5 class="font-bold text-slate-300">Servidores DNS (Nameservers)</h5>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <input
              v-model="formData.ns1"
              type="text"
              placeholder="NS1 (ej. ns1.conlineweb.com)"
              class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-white font-mono focus:outline-none focus:border-blue-500"
            />
            <input
              v-model="formData.ns2"
              type="text"
              placeholder="NS2 (ej. ns2.conlineweb.com)"
              class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-white font-mono focus:outline-none focus:border-blue-500"
            />
          </div>
        </div>

        <!-- Botones -->
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
            <span>{{ isEditing ? 'Guardar Cambios' : 'Registrar Dominio' }}</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
