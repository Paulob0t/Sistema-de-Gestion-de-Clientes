<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  formData: {
    concepto: string
    monto: number
    currency?: string
  }
  selectedClientFacturacion?: number
}>()

const popularConcepts = [
  'Renovación Anual de Dominio',
  'Servicio Anual de Hosting',
  'Mensualidad de Hosting & Correo',
  'Desarrollo Web & Landing Page',
  'Mantenimiento Mensual & Soporte',
  'Certificado SSL & Seguridad Web',
]

function setConcept(c: string) {
  props.formData.concepto = c
}

const montoConIva = computed(() => {
  const base = Number(props.formData.monto) || 0
  if (props.selectedClientFacturacion === 1) {
    return (base * 1.16).toFixed(2)
  }
  return base.toFixed(2)
})
</script>

<template>
  <div class="p-6 rounded-3xl bg-slate-900/70 border border-slate-800/80 shadow-xl space-y-4">
    <div class="flex items-center justify-between pb-3 border-b border-slate-800/60">
      <div class="flex items-center space-x-3">
        <div class="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center font-bold">
          <i class="pi pi-dollar text-sm"></i>
        </div>
        <div>
          <h2 class="text-sm font-bold text-white">Concepto & Monto del Cobro</h2>
          <p class="text-[11px] text-slate-400">Detalles del concepto facturable y desglose económico</p>
        </div>
      </div>
    </div>

    <!-- Concepto -->
    <div>
      <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
        Concepto / Descripción del Pago <span class="text-rose-500">*</span>
      </label>
      <input
        v-model="formData.concepto"
        type="text"
        required
        placeholder="ej. Renovación Anual de Dominio y Hosting"
        class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 mb-2"
      />
      <!-- Pills sugerencias -->
      <div class="flex flex-wrap gap-1.5">
        <button
          v-for="c in popularConcepts"
          :key="c"
          type="button"
          @click="setConcept(c)"
          class="px-2.5 py-1 rounded-lg text-[10px] font-medium transition-colors"
          :class="formData.concepto === c ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' : 'bg-slate-800/80 text-slate-400 hover:text-white'"
        >
          {{ c }}
        </button>
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 pt-1">
      <!-- Monto Base -->
      <div>
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
          Monto <span class="text-rose-500">*</span>
        </label>
        <div class="relative flex items-center">
          <span class="px-3 py-2.5 rounded-l-xl bg-slate-900 border border-r-0 border-slate-800 text-xs text-slate-400 select-none">
            {{ formData.currency === 'USD' ? 'US$' : '$' }}
          </span>
          <input
            v-model.number="formData.monto"
            type="number"
            step="0.01"
            min="1"
            required
            placeholder="0.00"
            class="w-full px-3.5 py-2.5 rounded-r-xl bg-slate-950/80 border border-slate-800 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 font-mono"
          />
        </div>
        <div v-if="selectedClientFacturacion === 1" class="mt-1.5 p-2 rounded-lg bg-indigo-500/10 border border-indigo-500/20 text-[11px] text-indigo-300 flex justify-between items-center">
          <span>Total con IVA (16%):</span>
          <span class="font-bold">${{ montoConIva }}</span>
        </div>
      </div>

      <!-- Moneda -->
      <div>
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
          Moneda <span class="text-rose-500">*</span>
        </label>
        <select
          v-model="formData.currency"
          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white focus:outline-none focus:border-emerald-500"
        >
          <option value="MXN">MXN (Pesos Mexicanos)</option>
          <option value="USD">USD (Dólares Americanos)</option>
        </select>
      </div>
    </div>
  </div>
</template>
