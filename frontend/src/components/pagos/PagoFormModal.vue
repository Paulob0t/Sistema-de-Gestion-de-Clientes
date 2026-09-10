<script setup lang="ts">
import type { PagoPayload } from '@/api/pagos'

defineProps<{
  isOpen: boolean
  isEditing: boolean
  isSaving: boolean
  formData: PagoPayload
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
      <div class="w-full max-w-xl bg-[#0B101D] border border-slate-800 rounded-3xl shadow-2xl overflow-hidden my-8 animate-fadeIn">
        <!-- Header -->
        <div class="px-6 py-5 border-b border-slate-800 flex items-center justify-between bg-[#0E1526]">
          <div class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-emerald-600 to-cyan-600 p-0.5 flex items-center justify-center text-white">
              <i class="pi pi-credit-card text-sm"></i>
            </div>
            <div>
              <div class="text-base font-extrabold text-white">
                {{ isEditing ? 'Editar Cobro / Pago' : 'Registrar Nuevo Cobro' }}
              </div>
              <div class="text-xs text-slate-400">Genera un recibo o registra un pago recibido</div>
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
              Cliente <span class="text-rose-500">*</span>
            </label>
            <select
              v-model="formData.id_clie"
              required
              class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/90 border border-slate-700/80 text-white focus:outline-none focus:border-cyan-500 text-sm"
            >
              <option :value="0" disabled>Selecciona un cliente...</option>
              <option v-for="c in clientesList" :key="c.id" :value="c.id">
                {{ c.empresa ? `${c.empresa} (${c.nombre_contacto})` : c.nombre_contacto }}
              </option>
            </select>
          </div>

          <!-- 2. Concepto -->
          <div>
            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
              Concepto / Descripción <span class="text-rose-500">*</span>
            </label>
            <input
              v-model="formData.concepto"
              type="text"
              required
              class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/90 border border-slate-700/80 text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500 text-sm"
              placeholder="ej. Renovación anual de Hosting y Dominio"
            />
          </div>

          <!-- 3. Monto y Moneda -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
                Monto ($) <span class="text-rose-500">*</span>
              </label>
              <input
                v-model.number="formData.monto"
                type="number"
                step="0.01"
                min="0.01"
                required
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/90 border border-slate-700/80 text-white focus:outline-none focus:border-cyan-500 text-sm font-bold"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
                Moneda
              </label>
              <select
                v-model="formData.currency"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/90 border border-slate-700/80 text-white focus:outline-none focus:border-cyan-500 text-sm"
              >
                <option value="MXN">MXN (Pesos Mexicanos)</option>
                <option value="USD">USD (Dólares)</option>
              </select>
            </div>
          </div>

          <!-- 4. Tipo de Servicio & Método de Pago -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
                Tipo de Servicio
              </label>
              <select
                v-model="formData.tipo_servicio"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/90 border border-slate-700/80 text-white focus:outline-none focus:border-cyan-500 text-sm"
              >
                <option :value="0">Manual / Otro servicio</option>
                <option :value="1">Hosting / Servidor</option>
                <option :value="2">Dominio</option>
                <option :value="3">Desarrollo Web / Diseño</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
                Forma / Método de Pago
              </label>
              <select
                v-model="formData.forma_pago"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/90 border border-slate-700/80 text-white focus:outline-none focus:border-cyan-500 text-sm"
              >
                <option :value="1">Transferencia / Depósito</option>
                <option :value="2">Efectivo / OXXO</option>
                <option :value="3">Tarjeta / Stripe</option>
                <option :value="4">PayPal</option>
              </select>
            </div>
          </div>

          <!-- 5. Fechas -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
                Fecha de Emisión
              </label>
              <input
                v-model="formData.fecha"
                type="date"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/90 border border-slate-700/80 text-white focus:outline-none focus:border-cyan-500 text-sm"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
                Fecha Límite de Pago
              </label>
              <input
                v-model="formData.fecha_limite_pago"
                type="date"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/90 border border-slate-700/80 text-white focus:outline-none focus:border-cyan-500 text-sm"
              />
            </div>
          </div>

          <!-- 6. Estatus y Referencia -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
                Estatus del Pago
              </label>
              <select
                v-model="formData.estatus"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/90 border border-slate-700/80 text-white focus:outline-none focus:border-cyan-500 text-sm font-semibold"
              >
                <option :value="0">Pendiente de Cobro</option>
                <option :value="1">Acreditado / Pagado</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
                Referencia / Folio
              </label>
              <input
                v-model="formData.id_pago"
                type="text"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/90 border border-slate-700/80 text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500 text-sm font-mono"
                placeholder="ej. TRANS-98234"
              />
            </div>
          </div>

          <!-- Botones -->
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
              class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-cyan-600 hover:from-emerald-500 hover:to-cyan-500 text-white text-xs font-bold shadow-lg shadow-emerald-500/20 flex items-center space-x-2 transition-all disabled:opacity-50"
            >
              <i v-if="isSaving" class="pi pi-spin pi-spinner text-xs"></i>
              <i v-else class="pi pi-check text-xs"></i>
              <span>{{ isSaving ? 'Guardando...' : (isEditing ? 'Actualizar Cobro' : 'Registrar Cobro') }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>
</template>
