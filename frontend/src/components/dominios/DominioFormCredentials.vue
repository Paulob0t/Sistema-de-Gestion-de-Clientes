<script setup lang="ts">
import { ref } from 'vue'

defineProps<{
  formData: {
    usuario?: string | null
    contrasena?: string | null
    contrasena_normal?: string | null
    url_cpanel?: string | null
  }
}>()

const emit = defineEmits<{
  (e: 'generate-password'): void
}>()

const showPassword = ref(false)
</script>

<template>
  <div class="p-6 rounded-3xl bg-slate-900/70 border border-slate-800/80 shadow-xl space-y-4">
    <div class="flex items-center justify-between pb-3 border-b border-slate-800/60">
      <div class="flex items-center space-x-3">
        <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center font-bold">
          <i class="pi pi-key text-sm"></i>
        </div>
        <div>
          <h2 class="text-sm font-bold text-white">Credenciales & Accesos</h2>
          <p class="text-[11px] text-slate-400">Accesos administrativos de WordPress y cPanel</p>
        </div>
      </div>
      <button
        type="button"
        @click="emit('generate-password')"
        class="px-3 py-1.5 rounded-xl bg-amber-500/10 hover:bg-amber-500/20 text-amber-300 border border-amber-500/20 text-xs font-semibold flex items-center space-x-1.5 transition-colors"
      >
        <i class="pi pi-bolt text-xs"></i>
        <span>Generar Clave</span>
      </button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <!-- Usuario Admin -->
      <div>
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
          Usuario Administrador
        </label>
        <input
          v-model="formData.usuario"
          type="text"
          placeholder="admin / contacto@empresa.com"
          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-amber-500"
        />
      </div>

      <!-- Contraseña Admin -->
      <div>
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
          Contraseña Administrador
        </label>
        <div class="relative">
          <input
            v-model="formData.contrasena_normal"
            :type="showPassword ? 'text' : 'password'"
            placeholder="Clave de acceso"
            class="w-full pl-3.5 pr-10 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white font-mono placeholder-slate-500 focus:outline-none focus:border-amber-500"
          />
          <button
            type="button"
            @click="showPassword = !showPassword"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white"
          >
            <i :class="showPassword ? 'pi pi-eye-slash text-xs' : 'pi pi-eye text-xs'"></i>
          </button>
        </div>
      </div>

      <!-- URL cPanel -->
      <div>
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
          URL cPanel
        </label>
        <input
          v-model="formData.url_cpanel"
          type="url"
          placeholder="https://cpanel.empresa.com:2083/"
          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 font-mono text-[11px]"
        />
      </div>
    </div>
  </div>
</template>
