<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { clientesApi, type ClienteListItem } from '@/api/clientes'
import { dominiosApi, type DominioListItem } from '@/api/dominios'

const props = defineProps<{
  formData: {
    cliente_id: number
    dominio?: string
    nom_host: string
    url_acceso?: string
  }
  selectedClientFacturacion?: number
}>()

const emit = defineEmits<{
  (e: 'client-selected', client: ClienteListItem): void
}>()

const clients = ref<ClienteListItem[]>([])
const clientDomains = ref<DominioListItem[]>([])
const isLoadingClients = ref(false)
const searchQuery = ref('')
const isDropdownOpen = ref(false)

async function fetchClients() {
  isLoadingClients.value = true
  try {
    const res = await clientesApi.getClientes({ limit: 100, filtro: 'activos' })
    clients.value = res.items
  } catch {
    clients.value = []
  } finally {
    isLoadingClients.value = false
  }
}

async function fetchClientDomains(clienteId: number) {
  if (!clienteId) {
    clientDomains.value = []
    return
  }
  try {
    const res = await dominiosApi.getDominios({ limit: 50, filtro: 'todos' })
    clientDomains.value = res.items.filter(d => d.cliente_id === clienteId)
  } catch {
    clientDomains.value = []
  }
}

onMounted(() => {
  fetchClients()
  if (props.formData.cliente_id) {
    fetchClientDomains(props.formData.cliente_id)
  }
})

watch(
  () => props.formData.cliente_id,
  (newId) => {
    if (newId) {
      fetchClientDomains(newId)
    }
  }
)

const selectedClient = computed(() => {
  if (!props.formData.cliente_id) return null
  return clients.value.find(c => c.id === props.formData.cliente_id) || null
})

const filteredClients = computed(() => {
  if (!searchQuery.value.trim()) return clients.value
  const q = searchQuery.value.toLowerCase().trim()
  return clients.value.filter(c =>
    c.empresa?.toLowerCase().includes(q) ||
    c.nombre_contacto?.toLowerCase().includes(q) ||
    c.correo?.toLowerCase().includes(q) ||
    String(c.id).includes(q)
  )
})

function selectClient(client: ClienteListItem) {
  props.formData.cliente_id = client.id
  emit('client-selected', client)
  isDropdownOpen.value = false
  searchQuery.value = ''
}

function onDomainChange(domain: string) {
  props.formData.dominio = domain
  const clean = domain.trim().toLowerCase().replace(/^https?:\/\//, '').replace(/\/$/, '')
  if (clean) {
    props.formData.nom_host = `cpanel.${clean}`
    props.formData.url_acceso = `https://cpanel.${clean}:2083/`
  }
}
</script>

<template>
  <div class="p-6 rounded-3xl bg-slate-900/70 border border-slate-800/80 shadow-xl space-y-4">
    <div class="flex items-center justify-between pb-3 border-b border-slate-800/60">
      <div class="flex items-center space-x-3">
        <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center font-bold">
          <i class="pi pi-user text-sm"></i>
        </div>
        <div>
          <h2 class="text-sm font-bold text-white">Cliente & Dominio Asociado</h2>
          <p class="text-[11px] text-slate-400">Titular de la cuenta y servidor asignado</p>
        </div>
      </div>
      <span v-if="selectedClientFacturacion === 1" class="px-2.5 py-1 rounded-lg bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 text-[10px] font-bold">
        Facturación Activa (+16% IVA)
      </span>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <!-- Selector de Cliente -->
      <div class="sm:col-span-2 lg:col-span-1 relative">
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
          Cliente Asignado <span class="text-rose-500">*</span>
        </label>
        <button
          type="button"
          @click="isDropdownOpen = !isDropdownOpen"
          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 hover:border-slate-700 text-left flex items-center justify-between transition-all focus:outline-none focus:border-amber-500"
        >
          <div v-if="selectedClient" class="flex items-center space-x-2.5 min-w-0">
            <span class="text-xs font-bold text-white truncate">{{ selectedClient.empresa }}</span>
            <span class="text-[10px] text-slate-500">#{{ selectedClient.id }}</span>
          </div>
          <span v-else class="text-xs text-slate-500 truncate">
            {{ isLoadingClients ? 'Cargando clientes...' : 'Seleccionar cliente...' }}
          </span>
          <i class="pi text-xs text-slate-400" :class="isDropdownOpen ? 'pi-chevron-up' : 'pi-chevron-down'"></i>
        </button>

        <!-- Dropdown -->
        <div
          v-if="isDropdownOpen"
          class="absolute left-0 right-0 top-full mt-2 z-40 bg-[#0C1222] border border-slate-700/80 rounded-2xl shadow-2xl overflow-hidden animate-fadeIn"
        >
          <div class="p-2 border-b border-slate-800/80 bg-slate-950/50">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Buscar cliente..."
              class="w-full px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-700 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-amber-500"
              autofocus
            />
          </div>
          <div class="max-h-52 overflow-y-auto divide-y divide-slate-800/40">
            <div
              v-for="c in filteredClients"
              :key="c.id"
              @click="selectClient(c)"
              class="p-2.5 hover:bg-amber-500/10 cursor-pointer flex items-center justify-between transition-colors text-xs"
              :class="selectedClient?.id === c.id ? 'bg-amber-500/15' : ''"
            >
              <div class="min-w-0">
                <span class="font-bold text-white block truncate">{{ c.empresa }}</span>
                <span class="text-[10px] text-slate-400 block truncate">{{ c.nombre_contacto }}</span>
              </div>
              <span class="text-[10px] text-slate-500 shrink-0">#{{ c.id }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Dominio Asociado -->
      <div>
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
          Dominio Asociado
        </label>
        <div class="space-y-1.5">
          <input
            v-model="formData.dominio"
            @input="onDomainChange(($event.target as HTMLInputElement).value)"
            type="text"
            placeholder="ej. empresa.com"
            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 font-mono"
          />
          <!-- Sugerencias de dominios del cliente -->
          <div v-if="clientDomains.length > 0" class="flex flex-wrap gap-1 items-center">
            <span class="text-[10px] text-slate-500">Del cliente:</span>
            <button
              v-for="d in clientDomains"
              :key="d.id_dominio"
              type="button"
              @click="onDomainChange(d.url_dominio)"
              class="px-2 py-0.5 rounded text-[10px] font-mono transition-colors"
              :class="formData.dominio === d.url_dominio ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-slate-800/80 text-slate-400 hover:text-white'"
            >
              {{ d.url_dominio }}
            </button>
          </div>
        </div>
      </div>

      <!-- Nombre Host / cPanel Host -->
      <div>
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
          Nombre de Host / Servidor <span class="text-rose-500">*</span>
        </label>
        <input
          v-model="formData.nom_host"
          type="text"
          required
          placeholder="cpanel.empresa.com"
          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 font-mono"
        />
        <span class="text-[10px] text-slate-500 mt-1 block">Host para la conexión y enlace de hosting</span>
      </div>
    </div>
  </div>
</template>
