<script setup lang="ts">
defineProps<{
  saludo: string
  nombre: string
  empresa?: string
  totalPendientes: number
}>()

const emit = defineEmits<{
  (e: 'open-ticket'): void
}>()
</script>

<template>
  <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-slate-900 via-blue-950 to-indigo-950 border border-slate-800/80 p-6 md:p-8 shadow-xl shadow-blue-950/20">
    <!-- Glow background accents -->
    <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-blue-500/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute right-1/3 -top-12 w-48 h-48 bg-indigo-500/10 rounded-full blur-2xl pointer-events-none"></div>

    <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-blue-600/20 border border-blue-500/30 flex items-center justify-center text-blue-400 shadow-inner shrink-0">
          <i class="pi pi-home text-2xl md:text-3xl"></i>
        </div>
        <div>
          <div class="flex items-center gap-2 flex-wrap">
            <h1 class="text-xl md:text-2xl lg:text-3xl font-extrabold text-white tracking-tight">
              {{ saludo }}, <span class="text-blue-400">{{ nombre }}</span>
            </h1>
            <span
              v-if="empresa"
              class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-blue-500/15 text-blue-300 border border-blue-500/20"
            >
              {{ empresa }}
            </span>
          </div>
          <p class="text-xs md:text-sm text-slate-400 mt-1">
            Resumen general y estado en tiempo real de tus servicios contratados.
          </p>
        </div>
      </div>

      <div class="flex items-center gap-3 w-full md:w-auto shrink-0">
        <button
          @click="emit('open-ticket')"
          class="flex-1 md:flex-none inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold transition-all shadow-lg shadow-blue-600/30 active:scale-95"
        >
          <i class="pi pi-plus text-xs"></i>
          <span>Nuevo Ticket</span>
        </button>
        <a
          href="https://wa.me/521"
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-300 border border-emerald-500/30 text-xs font-semibold transition-all active:scale-95"
          title="Contacto directo WhatsApp"
        >
          <i class="pi pi-whatsapp text-sm"></i>
          <span class="hidden sm:inline">WhatsApp</span>
        </a>
      </div>
    </div>
  </div>
</template>
