<script setup lang="ts">
import { ref } from 'vue'

const props = defineProps<{
  formData: {
    ns1?: string | null
    ns2?: string | null
    ns3?: string | null
    ns4?: string | null
    ns5?: string | null
    ns6?: string | null
  }
}>()

const showAdvancedNs = ref(false)

function applyCloudflarePreset() {
  props.formData.ns1 = 'ns1.cloudflare.com'
  props.formData.ns2 = 'ns2.cloudflare.com'
}

function applyNexusPreset() {
  props.formData.ns1 = 'ns1.nexusbot.io'
  props.formData.ns2 = 'ns2.nexusbot.io'
}

function clearDns() {
  props.formData.ns1 = ''
  props.formData.ns2 = ''
  props.formData.ns3 = ''
  props.formData.ns4 = ''
  props.formData.ns5 = ''
  props.formData.ns6 = ''
}
</script>

<template>
  <div class="p-6 rounded-3xl bg-slate-900/70 border border-slate-800/80 shadow-xl space-y-4">
    <div class="flex items-center justify-between pb-3 border-b border-slate-800/60">
      <div class="flex items-center space-x-3">
        <div class="w-9 h-9 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center font-bold">
          <i class="pi pi-server text-sm"></i>
        </div>
        <div>
          <h2 class="text-sm font-bold text-white">Configuración DNS & Nameservers</h2>
          <p class="text-[11px] text-slate-400">Servidores de nombres para resolución del dominio</p>
        </div>
      </div>
      <div class="flex items-center space-x-2">
        <button
          type="button"
          @click="applyNexusPreset"
          class="px-2.5 py-1 rounded-lg bg-purple-500/10 hover:bg-purple-500/20 text-purple-300 border border-purple-500/20 text-[10px] font-semibold transition-colors"
        >
          Preset Nexus
        </button>
        <button
          type="button"
          @click="applyCloudflarePreset"
          class="px-2.5 py-1 rounded-lg bg-orange-500/10 hover:bg-orange-500/20 text-orange-300 border border-orange-500/20 text-[10px] font-semibold transition-colors"
        >
          Preset Cloudflare
        </button>
        <button
          type="button"
          @click="clearDns"
          class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white text-[10px] font-semibold transition-colors"
        >
          Limpiar
        </button>
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <!-- NS1 -->
      <div>
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
          Nameserver 1 (NS1)
        </label>
        <input
          v-model="formData.ns1"
          type="text"
          placeholder="ns1.dominio.com"
          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-purple-500 font-mono text-[11px]"
        />
      </div>

      <!-- NS2 -->
      <div>
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
          Nameserver 2 (NS2)
        </label>
        <input
          v-model="formData.ns2"
          type="text"
          placeholder="ns2.dominio.com"
          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-purple-500 font-mono text-[11px]"
        />
      </div>
    </div>

    <!-- Toggle Advanced NS -->
    <div class="pt-1">
      <button
        type="button"
        @click="showAdvancedNs = !showAdvancedNs"
        class="text-xs text-purple-400 hover:text-purple-300 font-semibold flex items-center space-x-1.5 transition-colors"
      >
        <i class="pi" :class="showAdvancedNs ? 'pi-chevron-up' : 'pi-chevron-down'"></i>
        <span>{{ showAdvancedNs ? 'Ocultar NS3 - NS6 adicionales' : 'Mostrar Nameservers adicionales (NS3, NS4, NS5, NS6)' }}</span>
      </button>

      <div v-if="showAdvancedNs" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-3 animate-fadeIn">
        <div>
          <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">NS3</label>
          <input
            v-model="formData.ns3"
            type="text"
            placeholder="ns3.dominio.com"
            class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-purple-500 font-mono text-[11px]"
          />
        </div>
        <div>
          <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">NS4</label>
          <input
            v-model="formData.ns4"
            type="text"
            placeholder="ns4.dominio.com"
            class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-purple-500 font-mono text-[11px]"
          />
        </div>
        <div>
          <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">NS5</label>
          <input
            v-model="formData.ns5"
            type="text"
            placeholder="ns5.dominio.com"
            class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-purple-500 font-mono text-[11px]"
          />
        </div>
        <div>
          <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">NS6</label>
          <input
            v-model="formData.ns6"
            type="text"
            placeholder="ns6.dominio.com"
            class="w-full px-3 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-purple-500 font-mono text-[11px]"
          />
        </div>
      </div>
    </div>
  </div>
</template>
