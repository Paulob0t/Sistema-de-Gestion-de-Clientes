<script setup lang="ts">
defineProps<{
  formData: {
    calle?: string | null
    next?: string | null
    nint?: string | null
    col?: string | null
    cp?: string | null
    estado?: string | null
    ciudad?: string | null
    pais?: string | null
  }
}>()
</script>

<template>
  <div class="p-6 rounded-3xl bg-slate-900/70 border border-slate-800/80 shadow-xl space-y-4">
    <div class="flex items-center justify-between pb-3 border-b border-slate-800/60">
      <div class="flex items-center space-x-3">
        <div class="w-9 h-9 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center font-bold">
          <i class="pi pi-map-marker text-sm"></i>
        </div>
        <div>
          <h2 class="text-sm font-bold text-white">Domicilio & Ubicación</h2>
          <p class="text-[11px] text-slate-400">Dirección para registro y geolocalización de servicios</p>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="sm:col-span-2">
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Calle</label>
        <input
          v-model="formData.calle"
          type="text"
          placeholder="Av. Insurgentes Sur"
          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500"
        />
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">N° Exterior</label>
        <input
          v-model="formData.next"
          type="text"
          placeholder="123"
          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500"
        />
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">N° Interior</label>
        <input
          v-model="formData.nint"
          type="text"
          placeholder="Piso 4"
          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500"
        />
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Colonia</label>
        <input
          v-model="formData.col"
          type="text"
          placeholder="Del Valle"
          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500"
        />
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Código Postal</label>
        <input
          v-model="formData.cp"
          type="text"
          placeholder="03100"
          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500"
        />
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Estado</label>
        <input
          v-model="formData.estado"
          type="text"
          placeholder="Ciudad de México"
          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500"
        />
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Ciudad / Municipio</label>
        <input
          v-model="formData.ciudad"
          type="text"
          placeholder="Benito Juárez"
          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500"
        />
      </div>
    </div>
  </div>
</template>
