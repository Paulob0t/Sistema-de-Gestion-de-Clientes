<script setup lang="ts">
defineProps<{
  formData: {
    rfc?: string | null
    rsocial?: string | null
    especificacion?: string | null
  }
}>()
</script>

<template>
  <div class="p-6 rounded-3xl bg-slate-900/70 border border-indigo-500/30 shadow-xl space-y-4 animate-fadeIn">
    <div class="flex items-center justify-between pb-3 border-b border-slate-800/60">
      <div class="flex items-center space-x-3">
        <div class="w-9 h-9 rounded-xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center font-bold">
          <i class="pi pi-dollar text-sm"></i>
        </div>
        <div>
          <h2 class="text-sm font-bold text-white">Datos Fiscales y de Facturación</h2>
          <p class="text-[11px] text-slate-400">Información para emisión de CFDI y timbrado fiscal</p>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <div>
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
          RFC <span class="text-rose-500">*</span>
        </label>
        <input
          v-model="formData.rfc"
          type="text"
          required
          placeholder="XAXX010101000"
          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white uppercase placeholder-slate-500 focus:outline-none focus:border-indigo-500 font-mono"
        />
      </div>

      <div class="sm:col-span-2">
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
          Razón Social / Nombre Fiscal <span class="text-rose-500">*</span>
        </label>
        <input
          v-model="formData.rsocial"
          type="text"
          required
          placeholder="Innovaciones Digitales y Comercio S.A. de C.V."
          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500"
        />
      </div>

      <div class="sm:col-span-3">
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
          Régimen Fiscal / Especificaciones
        </label>
        <input
          v-model="formData.especificacion"
          type="text"
          placeholder="ej. 601 General de Ley Personas Morales / Uso CFDI: G03"
          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500"
        />
      </div>
    </div>
  </div>
</template>
