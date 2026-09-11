<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  formData: {
    costo_dominio?: number
    id_forma_pago?: number
    registrado?: number
    estado_dominio?: number
    fecha_contratacion?: string | null
    fecha_pago?: string | null
  }
  selectedClientFacturacion?: number
}>()

const costoConIva = computed(() => {
  const base = Number(props.formData.costo_dominio) || 0
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
        <div class="w-9 h-9 rounded-xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center font-bold">
          <i class="pi pi-credit-card text-sm"></i>
        </div>
        <div>
          <h2 class="text-sm font-bold text-white">Costos, Estado & Vigencia</h2>
          <p class="text-[11px] text-slate-400">Precios, gestión de renovación y fechas contractuales</p>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Costo Base -->
      <div>
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
          Costo Base <span class="text-rose-500">*</span>
        </label>
        <div class="relative flex items-center">
          <span class="px-3 py-2.5 rounded-l-xl bg-slate-900 border border-r-0 border-slate-800 text-xs text-slate-400 select-none">
            {{ formData.id_forma_pago === 2 ? 'US$' : '$' }}
          </span>
          <input
            v-model.number="formData.costo_dominio"
            type="number"
            step="0.01"
            min="0"
            required
            placeholder="0.00"
            class="w-full px-3.5 py-2.5 rounded-r-xl bg-slate-950/80 border border-slate-800 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 font-mono"
          />
        </div>
        <div v-if="selectedClientFacturacion === 1" class="mt-1.5 p-2 rounded-lg bg-indigo-500/10 border border-indigo-500/20 text-[11px] text-indigo-300 flex justify-between items-center">
          <span>Con IVA (16%):</span>
          <span class="font-bold">${{ costoConIva }}</span>
        </div>
      </div>

      <!-- Moneda -->
      <div>
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
          Moneda <span class="text-rose-500">*</span>
        </label>
        <select
          v-model="formData.id_forma_pago"
          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white focus:outline-none focus:border-indigo-500"
        >
          <option :value="1">MXN (Pesos Mexicanos)</option>
          <option :value="2">USD (Dólares Americanos)</option>
        </select>
      </div>

      <!-- Gestión de Dominio -->
      <div>
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
          Gestión del Dominio
        </label>
        <select
          v-model="formData.registrado"
          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white focus:outline-none focus:border-indigo-500"
        >
          <option :value="1">Gestionado por NexusBot</option>
          <option :value="0">Proveedor Externo</option>
        </select>
        <span class="text-[10px] text-slate-500 mt-1 block">¿Quién gestiona la renovación?</span>
      </div>

      <!-- Estado -->
      <div>
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
          Estado del Dominio
        </label>
        <select
          v-model="formData.estado_dominio"
          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white focus:outline-none focus:border-indigo-500"
        >
          <option :value="1">Activo</option>
          <option :value="0">Inactivo</option>
        </select>
      </div>

      <!-- Fecha Contratación -->
      <div class="sm:col-span-2">
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
          Fecha de Contratación <span class="text-rose-500">*</span>
        </label>
        <input
          v-model="formData.fecha_contratacion"
          type="date"
          required
          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white focus:outline-none focus:border-indigo-500 [color-scheme:dark]"
        />
      </div>

      <!-- Fecha Renovación / Pago -->
      <div class="sm:col-span-2">
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
          Fecha de Renovación / Pago <span class="text-rose-500">*</span>
        </label>
        <input
          v-model="formData.fecha_pago"
          type="date"
          required
          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white focus:outline-none focus:border-indigo-500 [color-scheme:dark]"
        />
        <span class="text-[10px] text-slate-500 mt-1 block">Fecha límite en la que vence el dominio</span>
      </div>
    </div>
  </div>
</template>
