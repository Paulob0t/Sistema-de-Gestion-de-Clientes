<script setup lang="ts">
defineProps<{
  docUrl: string
}>()

const emit = defineEmits<{
  (e: 'open-ticket'): void
}>()
</script>

<template>
  <div class="rounded-2xl bg-gradient-to-r from-slate-900 via-slate-900 to-blue-950/40 border border-slate-800 p-6 md:p-8 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
    <div class="flex items-start gap-4">
      <div class="w-12 h-12 rounded-xl bg-blue-500/10 border border-blue-500/20 text-blue-400 flex items-center justify-center shrink-0">
        <i class="pi pi-question-circle text-2xl"></i>
      </div>
      <div>
        <h3 class="text-base font-bold text-white tracking-tight">¿Tienes dudas o requieres asistencia técnica?</h3>
        <p class="text-xs md:text-sm text-slate-400 mt-1 max-w-xl leading-relaxed">
          Consulta nuestras guías y preguntas frecuentes para configurar tu correo, WordPress o cPanel, o levanta un ticket técnico con nuestro equipo.
        </p>
      </div>
    </div>

    <div class="flex items-center gap-3 w-full md:w-auto shrink-0">
      <a
        :href="docUrl"
        target="_blank"
        rel="noopener noreferrer"
        class="flex-1 md:flex-none inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-semibold transition-colors"
      >
        <i class="pi pi-book text-xs"></i>
        <span>Guías & Ayuda</span>
      </a>
      <button
        @click="emit('open-ticket')"
        class="flex-1 md:flex-none inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold transition-colors shadow-lg shadow-blue-600/20"
      >
        <i class="pi pi-ticket text-xs"></i>
        <span>Abrir Ticket</span>
      </button>
    </div>
  </div>
</template>
