<script setup lang="ts">
import { watch } from 'vue'

const props = defineProps<{
  formData: {
    url_dominio: string
    proveedor?: string | null
    url_admin?: string | null
    url_cpanel?: string | null
  }
}>()

const emit = defineEmits<{
  (e: 'domain-changed', domain: string): void
}>()

const popularProviders = ['NexusBot', 'GoDaddy', 'Namecheap', 'Hostinger', 'Cloudflare', 'Google Domains']

function setProvider(p: string) {
  props.formData.proveedor = p
}

watch(
  () => props.formData.url_dominio,
  (val) => {
    if (val) {
      let clean = val.trim().toLowerCase().replace(/^https?:\/\//, '').replace(/\/$/, '')
      props.formData.url_dominio = clean
      if (!props.formData.url_admin || props.formData.url_admin.includes('/wp-login.php')) {
        props.formData.url_admin = clean ? `https://${clean}/wp-login.php` : ''
      }
      if (!props.formData.url_cpanel || props.formData.url_cpanel.includes(':2083/')) {
        props.formData.url_cpanel = clean ? `https://cpanel.${clean}:2083/` : ''
      }
      emit('domain-changed', clean)
    }
  }
)
</script>

<template>
  <div class="p-6 rounded-3xl bg-slate-900/70 border border-slate-800/80 shadow-xl space-y-4">
    <div class="flex items-center justify-between pb-3 border-b border-slate-800/60">
      <div class="flex items-center space-x-3">
        <div class="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center font-bold">
          <i class="pi pi-globe text-sm"></i>
        </div>
        <div>
          <h2 class="text-sm font-bold text-white">Dominio & Proveedor</h2>
          <p class="text-[11px] text-slate-400">Identificador web y registrador del dominio</p>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <!-- Dominio URL -->
      <div class="sm:col-span-2 lg:col-span-1">
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
          Nombre de Dominio <span class="text-rose-500">*</span>
        </label>
        <div class="relative flex items-center">
          <span class="px-3 py-2.5 rounded-l-xl bg-slate-900 border border-r-0 border-slate-800 text-xs text-slate-400 select-none">
            https://
          </span>
          <input
            v-model="formData.url_dominio"
            type="text"
            required
            placeholder="empresa.com"
            class="w-full px-3.5 py-2.5 rounded-r-xl bg-slate-950/80 border border-slate-800 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500 font-mono"
          />
        </div>
      </div>

      <!-- Proveedor -->
      <div>
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
          Proveedor Registrador <span class="text-rose-500">*</span>
        </label>
        <input
          v-model="formData.proveedor"
          type="text"
          required
          placeholder="ej. NexusBot / GoDaddy"
          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500 mb-1.5"
        />
        <!-- Quick pills -->
        <div class="flex flex-wrap gap-1">
          <button
            v-for="prov in popularProviders"
            :key="prov"
            type="button"
            @click="setProvider(prov)"
            class="px-2 py-0.5 rounded-md text-[10px] font-medium transition-colors"
            :class="formData.proveedor === prov ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/40' : 'bg-slate-800/80 text-slate-400 hover:text-white'"
          >
            {{ prov }}
          </button>
        </div>
      </div>

      <!-- URL Admin WordPress -->
      <div>
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
          URL Administrador (WP)
        </label>
        <input
          v-model="formData.url_admin"
          type="url"
          placeholder="https://empresa.com/wp-login.php"
          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500 font-mono text-[11px]"
        />
        <span class="text-[10px] text-slate-500 mt-1 block">Acceso al panel administrativo o WordPress</span>
      </div>
    </div>
  </div>
</template>
