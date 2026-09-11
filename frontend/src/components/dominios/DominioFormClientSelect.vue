<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { clientesApi, type ClienteListItem } from '@/api/clientes'

const props = defineProps<{
  modelValue: number | null
  selectedClientFacturacion?: number
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: number): void
  (e: 'client-selected', client: ClienteListItem): void
}>()

const clients = ref<ClienteListItem[]>([])
const isLoading = ref(false)
const searchQuery = ref('')
const isDropdownOpen = ref(false)

async function fetchClients() {
  isLoading.value = true
  try {
    const res = await clientesApi.getClientes({ limit: 100, filtro: 'activos' })
    clients.value = res.items
  } catch {
    clients.value = []
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  fetchClients()
})

const selectedClient = computed(() => {
  if (!props.modelValue) return null
  return clients.value.find(c => c.id === props.modelValue) || null
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
  emit('update:modelValue', client.id)
  emit('client-selected', client)
  isDropdownOpen.value = false
  searchQuery.value = ''
}
</script>

<template>
  <div class="p-6 rounded-3xl bg-slate-900/70 border border-slate-800/80 shadow-xl space-y-4">
    <div class="flex items-center justify-between pb-3 border-b border-slate-800/60">
      <div class="flex items-center space-x-3">
        <div class="w-9 h-9 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center font-bold">
          <i class="pi pi-user text-sm"></i>
        </div>
        <div>
          <h2 class="text-sm font-bold text-white">Cliente Titular</h2>
          <p class="text-[11px] text-slate-400">Selecciona el cliente al que se le asignará el dominio</p>
        </div>
      </div>
      <span v-if="selectedClient?.facturacion === 1" class="px-2.5 py-1 rounded-lg bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 text-[10px] font-bold">
        Requiere Factura (+16% IVA)
      </span>
    </div>

    <!-- Buscador / Dropdown personalizado -->
    <div class="relative">
      <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
        Cliente Asignado <span class="text-rose-500">*</span>
      </label>

      <!-- Botón de apertura / estado actual -->
      <button
        type="button"
        @click="isDropdownOpen = !isDropdownOpen"
        class="w-full px-4 py-3 rounded-2xl bg-slate-950/80 border border-slate-800 hover:border-slate-700 text-left flex items-center justify-between transition-all focus:outline-none focus:border-cyan-500"
      >
        <div v-if="selectedClient" class="flex items-center space-x-3 min-w-0">
          <div class="w-8 h-8 rounded-xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center text-xs font-bold shrink-0">
            #{{ selectedClient.id }}
          </div>
          <div class="min-w-0">
            <span class="block text-xs font-bold text-white truncate">{{ selectedClient.empresa }}</span>
            <span class="block text-[11px] text-slate-400 truncate">{{ selectedClient.nombre_contacto }} • {{ selectedClient.correo || 'Sin correo' }}</span>
          </div>
        </div>
        <div v-else class="text-xs text-slate-500 flex items-center space-x-2">
          <i class="pi pi-search text-xs"></i>
          <span>{{ isLoading ? 'Cargando directorio de clientes...' : 'Haz clic para seleccionar o buscar un cliente...' }}</span>
        </div>
        <i class="pi text-xs text-slate-400 transition-transform" :class="isDropdownOpen ? 'pi-chevron-up' : 'pi-chevron-down'"></i>
      </button>

      <!-- Panel desplegable -->
      <div
        v-if="isDropdownOpen"
        class="absolute left-0 right-0 top-full mt-2 z-40 bg-[#0C1222] border border-slate-700/80 rounded-2xl shadow-2xl overflow-hidden animate-fadeIn"
      >
        <div class="p-3 border-b border-slate-800/80 bg-slate-950/50">
          <div class="relative">
            <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Buscar por empresa, contacto, ID o correo..."
              class="w-full pl-9 pr-3 py-2 rounded-xl bg-slate-900 border border-slate-700/60 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500"
              autofocus
            />
          </div>
        </div>

        <div class="max-h-60 overflow-y-auto divide-y divide-slate-800/40">
          <div
            v-for="c in filteredClients"
            :key="c.id"
            @click="selectClient(c)"
            class="p-3 hover:bg-cyan-500/10 cursor-pointer flex items-center justify-between transition-colors"
            :class="selectedClient?.id === c.id ? 'bg-cyan-500/15' : ''"
          >
            <div class="min-w-0 pr-3">
              <div class="flex items-center space-x-2">
                <span class="text-xs font-bold text-white truncate">{{ c.empresa }}</span>
                <span class="text-[10px] text-slate-500">#{{ c.id }}</span>
              </div>
              <p class="text-[11px] text-slate-400 truncate">{{ c.nombre_contacto }} • {{ c.correo || 'Sin correo' }}</p>
            </div>
            <span v-if="c.facturacion === 1" class="text-[10px] px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-400 shrink-0 font-medium">
              IVA
            </span>
          </div>

          <div v-if="filteredClients.length === 0" class="p-4 text-center text-xs text-slate-500">
            No se encontraron clientes activos con ese criterio
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
