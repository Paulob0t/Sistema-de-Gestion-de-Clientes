<script setup lang="ts">
defineProps<{
  formData: {
    empresa: string
    nombre: string
    apellido: string
    correo?: string | null
    telefono?: string | null
    requiereFacturacion: boolean
  }
}>()

const emit = defineEmits<{
  (e: 'toggle-facturacion'): void
}>()
</script>

<template>
  <div class="p-6 rounded-3xl bg-slate-900/70 border border-slate-800/80 shadow-xl space-y-4">
    <div class="flex items-center justify-between pb-3 border-b border-slate-800/60">
      <div class="flex items-center space-x-3">
        <div class="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center font-bold">
          <i class="pi pi-user text-sm"></i>
        </div>
        <div>
          <h2 class="text-sm font-bold text-white">Información del Titular & Empresa</h2>
          <p class="text-[11px] text-slate-400">Datos principales para identificación y contacto</p>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <!-- Empresa -->
      <div class="sm:col-span-2 lg:col-span-1">
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
          Nombre de la Empresa <span class="text-rose-500">*</span>
        </label>
        <input
          v-model="formData.empresa"
          type="text"
          required
          placeholder="ej. Innovaciones Digitales S.A."
          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all"
        />
      </div>

      <!-- Nombre -->
      <div>
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
          Nombre(s) <span class="text-rose-500">*</span>
        </label>
        <input
          v-model="formData.nombre"
          type="text"
          required
          placeholder="Juan Carlos"
          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all"
        />
      </div>

      <!-- Apellido -->
      <div>
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
          Apellidos
        </label>
        <input
          v-model="formData.apellido"
          type="text"
          placeholder="Pérez García"
          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all"
        />
      </div>

      <!-- Correo -->
      <div>
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
          Correo Electrónico <span class="text-rose-500">*</span>
        </label>
        <input
          v-model="formData.correo"
          type="email"
          required
          placeholder="cliente@empresa.com"
          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all"
        />
      </div>

      <!-- Teléfono -->
      <div>
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
          Teléfono / WhatsApp <span class="text-rose-500">*</span>
        </label>
        <input
          v-model="formData.telefono"
          type="tel"
          required
          placeholder="55 1234 5678"
          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all"
        />
      </div>

      <!-- Switch Facturación -->
      <div class="sm:col-span-2 lg:col-span-1 flex items-end">
        <div
          @click="emit('toggle-facturacion')"
          :class="[
            'w-full p-2.5 rounded-xl border flex items-center justify-between cursor-pointer select-none transition-all',
            formData.requiereFacturacion
              ? 'bg-indigo-950/30 border-indigo-500/50 text-white'
              : 'bg-slate-950/60 border-slate-800 text-slate-400 hover:text-slate-300'
          ]"
        >
          <div class="flex items-center space-x-2">
            <i class="pi pi-file-o text-indigo-400"></i>
            <div>
              <span class="text-xs font-bold block">Requiere Facturación</span>
              <span class="text-[10px] text-slate-500">Activa campos de RFC y Razón Social</span>
            </div>
          </div>
          <div
            :class="[
              'w-9 h-5 rounded-full p-0.5 transition-colors relative flex items-center',
              formData.requiereFacturacion ? 'bg-indigo-600' : 'bg-slate-700'
            ]"
          >
            <div
              :class="[
                'w-4 h-4 rounded-full bg-white transition-transform',
                formData.requiereFacturacion ? 'translate-x-4' : 'translate-x-0'
              ]"
            ></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
