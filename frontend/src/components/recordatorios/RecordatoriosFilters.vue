<script setup lang="ts">
defineProps<{
  search: string
  selectedTipo: string
  selectedEstado: string
  selectedCount: number
  totalCount: number
}>()

const emit = defineEmits<{
  (e: 'update:search', val: string): void
  (e: 'update:selectedTipo', val: string): void
  (e: 'update:selectedEstado', val: string): void
  (e: 'send-batch'): void
  (e: 'select-all'): void
  (e: 'clear-selection'): void
  (e: 'test-smtp'): void
  (e: 'refresh'): void
}>()

const tipos = [
  { label: 'Todos los Servicios', value: 'todos', icon: 'pi-th-large' },
  { label: 'Cobros / Pagos', value: 'pagos', icon: 'pi-credit-card' },
  { label: 'Dominios', value: 'dominios', icon: 'pi-globe' },
  { label: 'Hosting', value: 'hostings', icon: 'pi-server' },
]

const estados = [
  { label: 'Todos', value: 'todos' },
  { label: 'Vencidos / Hoy', value: 'vencidos', badgeClass: 'bg-rose-500/20 text-rose-300' },
  { label: 'Críticos (≤ 3 días)', value: 'criticos', badgeClass: 'bg-orange-500/20 text-orange-300' },
  { label: 'Próximos 7 días', value: 'proximos_7d', badgeClass: 'bg-amber-500/20 text-amber-300' },
  { label: 'Próximos 30 días', value: 'proximos_30d', badgeClass: 'bg-blue-500/20 text-blue-300' },
]
</script>

<template>
  <div class="space-y-4">
    <!-- Barra superior: Búsqueda y Acciones Rápidas -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
      <!-- Input de búsqueda -->
      <div class="relative flex-1 max-w-md">
        <i class="pi pi-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-sm"></i>
        <input
          :value="search"
          @input="emit('update:search', ($event.target as HTMLInputElement).value)"
          type="text"
          placeholder="Buscar por cliente, correo, dominio o concepto..."
          class="w-full bg-[#0D1527] border border-slate-800 rounded-xl pl-10 pr-4 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors"
        />
        <button
          v-if="search"
          @click="emit('update:search', '')"
          class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-white"
        >
          <i class="pi pi-times text-xs"></i>
        </button>
      </div>

      <!-- Botones de Acción Masiva y Estado SMTP -->
      <div class="flex items-center space-x-2.5 shrink-0">
        <button
          @click="emit('test-smtp')"
          class="px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-xs font-semibold text-slate-300 hover:text-white hover:border-slate-700 flex items-center space-x-2 transition-colors"
          title="Verificar conexión con el servidor SMTP"
        >
          <i class="pi pi-send text-xs text-blue-400"></i>
          <span class="hidden sm:inline">Probar SMTP</span>
        </button>

        <button
          @click="emit('refresh')"
          class="p-2 rounded-xl bg-slate-900 border border-slate-800 text-slate-400 hover:text-white hover:border-slate-700 transition-colors"
          title="Actualizar lista"
        >
          <i class="pi pi-refresh text-xs"></i>
        </button>

        <!-- Botón Envío Masivo -->
        <button
          v-if="selectedCount > 0"
          @click="emit('send-batch')"
          class="px-4 py-2 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white text-xs font-bold shadow-lg shadow-blue-500/25 flex items-center space-x-2 transition-all transform active:scale-95"
        >
          <i class="pi pi-envelope text-xs"></i>
          <span>Enviar a Seleccionados ({{ selectedCount }})</span>
        </button>
      </div>
    </div>

    <!-- Filtros de Tipo y Estado de Vencimiento -->
    <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-slate-800/60">
      <!-- Tabs de Tipo -->
      <div class="flex flex-wrap items-center gap-1.5 p-1 rounded-xl bg-[#090E1A] border border-slate-800/80">
        <button
          v-for="t in tipos"
          :key="t.value"
          @click="emit('update:selectedTipo', t.value)"
          :class="[
            'px-3 py-1.5 rounded-lg text-xs font-medium flex items-center space-x-1.5 transition-colors',
            selectedTipo === t.value
              ? 'bg-blue-600 text-white font-semibold shadow-sm'
              : 'text-slate-400 hover:text-white hover:bg-slate-800/60'
          ]"
        >
          <i :class="['pi', t.icon, 'text-[11px]']"></i>
          <span>{{ t.label }}</span>
        </button>
      </div>

      <!-- Pills de Urgencia -->
      <div class="flex flex-wrap items-center gap-1.5">
        <button
          v-for="e in estados"
          :key="e.value"
          @click="emit('update:selectedEstado', e.value)"
          :class="[
            'px-2.5 py-1 rounded-lg text-[11px] font-medium border transition-colors',
            selectedEstado === e.value
              ? 'bg-slate-800 text-white border-slate-600 font-bold'
              : 'bg-[#0D1527] text-slate-400 border-slate-800/80 hover:border-slate-700 hover:text-slate-200'
          ]"
        >
          {{ e.label }}
        </button>
      </div>
    </div>
  </div>
</template>
