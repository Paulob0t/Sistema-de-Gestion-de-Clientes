<script setup lang="ts">
import type { HostingPayload } from '@/api/hostings'

defineProps<{
  isOpen: boolean
  isEditing: boolean
  isSaving: boolean
  formData: HostingPayload
  clientesList: Array<{ id: number; empresa: string; nombre_contacto: string }>
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'save'): void
}>()
</script>

<template>
  <Teleport to="body">
    <div
      v-if="isOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm overflow-y-auto"
      @click.self="emit('close')"
    >
      <div class="w-full max-w-2xl bg-[#0B101D] border border-slate-800 rounded-3xl shadow-2xl overflow-hidden my-8 animate-fadeIn">
        <!-- Cabecera -->
        <div class="px-6 py-5 border-b border-slate-800 flex items-center justify-between bg-[#0E1526]">
          <div class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-cyan-600 to-blue-600 p-0.5 flex items-center justify-center text-white">
              <i class="pi pi-server text-sm"></i>
            </div>
            <div>
              <div class="text-base font-extrabold text-white">
                {{ isEditing ? 'Editar Servicio de Hosting' : 'Registrar Nuevo Hosting' }}
              </div>
              <div class="text-xs text-slate-400">Completa los parámetros de aprovisionamiento</div>
            </div>
          </div>
          <button
            @click="emit('close')"
            class="w-8 h-8 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 flex items-center justify-center transition-colors"
          >
            <i class="pi pi-times text-sm"></i>
          </button>
        </div>

        <!-- Formulario -->
        <form @submit.prevent="emit('save')" class="p-6 space-y-4 max-h-[75vh] overflow-y-auto custom-scrollbar">
          <!-- 1. Cliente Titular -->
          <div>
            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
              Cliente Titular <span class="text-rose-500">*</span>
            </label>
            <select
              v-model="formData.cliente_id"
              required
              class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/90 border border-slate-700/80 text-white focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 text-sm"
            >
              <option :value="0" disabled>Selecciona un cliente...</option>
              <option v-for="c in clientesList" :key="c.id" :value="c.id">
                {{ c.empresa ? `${c.empresa} (${c.nombre_contacto})` : c.nombre_contacto }}
              </option>
            </select>
          </div>

          <!-- 2. Hostname y Dominio -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
                Hostname / Servidor <span class="text-rose-500">*</span>
              </label>
              <input
                v-model="formData.nom_host"
                type="text"
                required
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/90 border border-slate-700/80 text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 text-sm"
                placeholder="ej. cpanel.miempresa.com"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
                Dominio Asociado
              </label>
              <input
                v-model="formData.dominio"
                type="text"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/90 border border-slate-700/80 text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 text-sm"
                placeholder="ej. miempresa.com"
              />
            </div>
          </div>

          <!-- 3. Tipo de Plan / Producto -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
                Tipo de Producto / Plan
              </label>
              <input
                v-model="formData.tipo_producto"
                type="text"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/90 border border-slate-700/80 text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 text-sm"
                placeholder="ej. Servicio de alojamiento / VPS Cloud"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
                URL Acceso (cPanel / WHM)
              </label>
              <input
                v-model="formData.url_acceso"
                type="text"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/90 border border-slate-700/80 text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 text-sm font-mono text-xs"
                placeholder="https://cpanel.miempresa.com:2083"
              />
            </div>
          </div>

          <!-- 4. Credenciales cPanel -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
                Usuario cPanel / WHM
              </label>
              <input
                v-model="formData.usuario"
                type="text"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/90 border border-slate-700/80 text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 text-sm font-mono"
                placeholder="usuario_cpanel"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
                Contraseña
              </label>
              <input
                v-model="formData.contrasena_normal"
                type="text"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/90 border border-slate-700/80 text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 text-sm font-mono"
                placeholder="Contraseña del panel"
              />
            </div>
          </div>

          <!-- 5. Precios, Moneda y Frecuencia -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
                Costo del Servicio ($)
              </label>
              <input
                v-model.number="formData.costo_producto"
                type="number"
                step="0.01"
                min="0"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/90 border border-slate-700/80 text-white focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 text-sm"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
                Moneda
              </label>
              <select
                v-model="formData.id_forma_pago"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/90 border border-slate-700/80 text-white focus:outline-none focus:border-cyan-500 text-sm"
              >
                <option :value="1">MXN (Pesos Mexicanos)</option>
                <option :value="2">USD (Dólares)</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
                Frecuencia de Cobro
              </label>
              <select
                v-model="formData.frecuencia_pago"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/90 border border-slate-700/80 text-white focus:outline-none focus:border-cyan-500 text-sm"
              >
                <option :value="1">Mensual</option>
                <option :value="2">Anual</option>
                <option :value="3">Trimestral</option>
                <option :value="4">Semestral</option>
              </select>
            </div>
          </div>

          <!-- 6. Fechas -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
                Fecha Contratación
              </label>
              <input
                v-model="formData.fecha_contratacion"
                type="date"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/90 border border-slate-700/80 text-white focus:outline-none focus:border-cyan-500 text-sm"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
                Fecha Renovación / Pago
              </label>
              <input
                v-model="formData.fecha_pago"
                type="date"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/90 border border-slate-700/80 text-white focus:outline-none focus:border-cyan-500 text-sm"
              />
            </div>
          </div>

          <!-- 7. Nameservers -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Name Server 1</label>
              <input
                v-model="formData.ns1"
                type="text"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/90 border border-slate-700/80 text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500 text-sm font-mono text-xs"
                placeholder="ns1.nexusbot.io"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Name Server 2</label>
              <input
                v-model="formData.ns2"
                type="text"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/90 border border-slate-700/80 text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500 text-sm font-mono text-xs"
                placeholder="ns2.nexusbot.io"
              />
            </div>
          </div>

          <!-- 8. Estado del Servicio -->
          <div>
            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Estado del Servicio</label>
            <select
              v-model="formData.estado_producto"
              class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/90 border border-slate-700/80 text-white focus:outline-none focus:border-cyan-500 text-sm"
            >
              <option :value="1">Activo (En operación)</option>
              <option :value="0">Inactivo / Suspendido</option>
            </select>
          </div>

          <!-- Botones de Guardar / Cancelar -->
          <div class="pt-4 border-t border-slate-800 flex items-center justify-end space-x-3">
            <button
              type="button"
              @click="emit('close')"
              class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition-colors"
            >
              Cancelar
            </button>
            <button
              type="submit"
              :disabled="isSaving"
              class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-500 hover:to-blue-500 text-white text-xs font-bold shadow-lg shadow-cyan-500/20 flex items-center space-x-2 transition-all disabled:opacity-50"
            >
              <i v-if="isSaving" class="pi pi-spin pi-spinner text-xs"></i>
              <i v-else class="pi pi-check text-xs"></i>
              <span>{{ isSaving ? 'Guardando...' : (isEditing ? 'Actualizar Hosting' : 'Guardar Hosting') }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>
</template>
