<script setup lang="ts">
import { useToast } from '@/composables/useToast'

const { toastMessage, toastType, clearToast } = useToast()
</script>

<template>
  <transition name="toast-fade">
    <div
      v-if="toastMessage"
      :class="[
        'fixed bottom-6 right-6 z-50 px-4 py-3 rounded-2xl border text-xs font-semibold shadow-2xl flex items-center space-x-2.5 backdrop-blur-md',
        toastType === 'success' ? 'bg-slate-900/95 border-emerald-500/40 text-emerald-300' :
        toastType === 'error' ? 'bg-slate-900/95 border-rose-500/40 text-rose-300' :
        toastType === 'warning' ? 'bg-slate-900/95 border-amber-500/40 text-amber-300' :
        'bg-slate-900/95 border-blue-500/40 text-blue-300'
      ]"
    >
      <i
        :class="[
          'text-sm',
          toastType === 'success' ? 'pi pi-check-circle text-emerald-400' :
          toastType === 'error' ? 'pi pi-exclamation-triangle text-rose-400' :
          toastType === 'warning' ? 'pi pi-exclamation-circle text-amber-400' :
          'pi pi-info-circle text-blue-400'
        ]"
      ></i>
      <span>{{ toastMessage }}</span>
      <button @click="clearToast" class="ml-2 text-slate-400 hover:text-white transition-colors">
        <i class="pi pi-times text-xs"></i>
      </button>
    </div>
  </transition>
</template>

<style scoped>
.toast-fade-enter-active,
.toast-fade-leave-active {
  transition: all 0.25s ease-out;
}
.toast-fade-enter-from,
.toast-fade-leave-to {
  opacity: 0;
  transform: translateY(12px) scale(0.95);
}
</style>
