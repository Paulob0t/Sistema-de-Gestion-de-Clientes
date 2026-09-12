<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { clientesApi, type ClienteListItem } from '@/api/clientes'
import { dominiosApi, type DominioListItem } from '@/api/dominios'
import { hostingsApi, type HostingListItem } from '@/api/hostings'

const props = defineProps<{
  formData: {
    id_clie: number
    tipo_servicio?: number
    id_servicio?: number
    concepto?: string
    monto?: number
    currency?: string
  }
  selectedClientFacturacion?: number
}>()

const emit = defineEmits<{
  (e: 'client-selected', client: ClienteListItem): void
}>()

const clients = ref<ClienteListItem[]>([])
const clientDominios = ref<DominioListItem[]>([])
const clientHostings = ref<HostingListItem[]>([])
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

async function fetchClientServices(clientId: number) {
  if (!clientId) {
    clientDominios.value = []
    clientHostings.value = []
    return
  }
  try {
    const [domsRes, hostsRes] = await Promise.all([
      dominiosApi.getDominios({ limit: 50, filtro: 'todos' }),
      hostingsApi.getHostings({ limit: 50, filtro: 'todos' }),
    ])
    clientDominios.value = domsRes.items.filter(d => d.cliente_id === clientId)
    clientHostings.value = hostsRes.items.filter(h => h.cliente_id === clientId)
  } catch {
    clientDominios.value = []
    clientHostings.value = []
  }
}

onMounted(() => {
  fetchClients()
  if (props.formData.id_clie) {
    fetchClientServices(props.formData.id_clie)
  }
})

watch(
  () => props.formData.id_clie,
  (newId) => {
    if (newId) {
      fetchClientServices(newId)
    }
  }
)

const selectedClient = computed(() => {
  if (!props.formData.id_clie) return null
  return clients.value.find(c => c.id === props.formData.id_clie) || null
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
  props.formData.id_clie = client.id
  emit('client-selected', client)
  isDropdownOpen.value = false
  searchQuery.value = ''
}

function onServiceTypeChange(type: number) {
  props.formData.tipo_servicio = type
  props.formData.id_servicio = 0
}

function onDominioSelect(domId: number) {
  const dom = clientDominios.value.find(d => d.id_dominio === domId)
  if (dom) {
    props.formData.id_servicio = dom.id_dominio
    if (!props.formData.concepto || props.formData.concepto.includes('Renovación')) {
      props.formData.concepto = `Renovación de Dominio: ${dom.url_dominio}`
    }
    if (dom.costo_dominio > 0) {
      props.formData.monto = dom.costo_dominio
    }
  }
}

function onHostingSelect(hostId: number) {
  const host = clientHostings.value.find(h => h.id_orden === hostId)
  if (host) {
    props.formData.id_servicio = host.id_orden
    if (!props.formData.concepto || props.formData.concepto.includes('Hosting')) {
      props.formData.concepto = `Servicio de Hosting: ${host.nom_host} (${host.tipo_producto || 'Anual'})`
    }
    if (host.costo_producto > 0) {
      props.formData.monto = host.costo_producto
    }
  }
}
</script>

<template>
  <div class="p-6 rounded-3xl bg-slate-900/70 border border-slate-800/80 shadow-xl space-y-4">
    <div class="flex items-center justify-between pb-3 border-b border-slate-800/60">
      <div class="flex items-center space-x-3">
        <div class="w-9 h-9 rounded-xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center font-bold">
          <i class="pi pi-user text-sm"></i>
        </div>
        <div>
          <h2 class="text-sm font-bold text-white">Cliente & Servicio a Cobrar</h2>
          <p class="text-[11px] text-slate-400">Selecciona el titular y el origen del cobro</p>
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
          Cliente <span class="text-rose-500">*</span>
        </label>
        <button
          type="button"
          @click="isDropdownOpen = !isDropdownOpen"
          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 hover:border-slate-700 text-left flex items-center justify-between transition-all focus:outline-none focus:border-indigo-500"
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

        <!-- Dropdown con búsqueda -->
        <div
          v-if="isDropdownOpen"
          class="absolute left-0 right-0 top-full mt-2 z-40 bg-[#0C1222] border border-slate-700/80 rounded-2xl shadow-2xl overflow-hidden animate-fadeIn"
        >
          <div class="p-2 border-b border-slate-800/80 bg-slate-950/50">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Buscar cliente..."
              class="w-full px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-700 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500"
              autofocus
            />
          </div>
          <div class="max-h-52 overflow-y-auto divide-y divide-slate-800/40">
            <div
              v-for="c in filteredClients"
              :key="c.id"
              @click="selectClient(c)"
              class="p-2.5 hover:bg-indigo-500/10 cursor-pointer flex items-center justify-between transition-colors text-xs"
              :class="selectedClient?.id === c.id ? 'bg-indigo-500/15' : ''"
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

      <!-- Tipo de Servicio -->
      <div>
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
          Tipo de Cobro / Servicio
        </label>
        <div class="grid grid-cols-3 gap-1.5">
          <button
            type="button"
            @click="onServiceTypeChange(0)"
            class="py-2 px-2 rounded-xl text-xs font-bold border transition-colors flex items-center justify-center space-x-1"
            :class="formData.tipo_servicio === 0 ? 'bg-indigo-600 text-white border-indigo-500' : 'bg-slate-950/60 border-slate-800 text-slate-400 hover:text-white'"
          >
            <i class="pi pi-receipt text-[11px]"></i>
            <span>Manual</span>
          </button>
          <button
            type="button"
            @click="onServiceTypeChange(2)"
            class="py-2 px-2 rounded-xl text-xs font-bold border transition-colors flex items-center justify-center space-x-1"
            :class="formData.tipo_servicio === 2 ? 'bg-cyan-600 text-white border-cyan-500' : 'bg-slate-950/60 border-slate-800 text-slate-400 hover:text-white'"
          >
            <i class="pi pi-globe text-[11px]"></i>
            <span>Dominio</span>
          </button>
          <button
            type="button"
            @click="onServiceTypeChange(1)"
            class="py-2 px-2 rounded-xl text-xs font-bold border transition-colors flex items-center justify-center space-x-1"
            :class="formData.tipo_servicio === 1 ? 'bg-amber-600 text-white border-amber-500' : 'bg-slate-950/60 border-slate-800 text-slate-400 hover:text-white'"
          >
            <i class="pi pi-server text-[11px]"></i>
            <span>Hosting</span>
          </button>
        </div>
      </div>

      <!-- Selector de Servicio específico (Dominio o Hosting) -->
      <div>
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
          Servicio Asociado
        </label>

        <!-- Selector de Dominio -->
        <select
          v-if="formData.tipo_servicio === 2"
          :value="formData.id_servicio"
          @change="onDominioSelect(Number(($event.target as HTMLSelectElement).value))"
          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white focus:outline-none focus:border-cyan-500"
        >
          <option :value="0">Selecciona un dominio del cliente</option>
          <option
            v-for="d in clientDominios"
            :key="d.id_dominio"
            :value="d.id_dominio"
          >
            {{ d.url_dominio }} (${{ d.costo_dominio }})
          </option>
        </select>

        <!-- Selector de Hosting -->
        <select
          v-else-if="formData.tipo_servicio === 1"
          :value="formData.id_servicio"
          @change="onHostingSelect(Number(($event.target as HTMLSelectElement).value))"
          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white focus:outline-none focus:border-amber-500"
        >
          <option :value="0">Selecciona un hosting del cliente</option>
          <option
            v-for="h in clientHostings"
            :key="h.id_orden"
            :value="h.id_orden"
          >
            {{ h.nom_host }} (${{ h.costo_producto }} {{ h.frecuencia_label }})
          </option>
        </select>

        <!-- General / Manual -->
        <input
          v-else
          type="text"
          value="Cobro General / Sin servicio específico"
          disabled
          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/40 border border-slate-800 text-xs text-slate-500"
        />
      </div>
    </div>
  </div>
</template>
