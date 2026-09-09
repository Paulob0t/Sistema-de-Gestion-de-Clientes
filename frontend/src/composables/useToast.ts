import { ref } from 'vue'

const toastMessage = ref<string | null>(null)
const toastType = ref<'success' | 'error' | 'info' | 'warning'>('success')
let toastTimer: any = null

export function useToast() {
  function showToast(message: string, type: 'success' | 'error' | 'info' | 'warning' = 'success', duration = 3500) {
    if (toastTimer) clearTimeout(toastTimer)
    toastMessage.value = message
    toastType.value = type
    toastTimer = setTimeout(() => {
      toastMessage.value = null
    }, duration)
  }

  function clearToast() {
    if (toastTimer) clearTimeout(toastTimer)
    toastMessage.value = null
  }

  return {
    toastMessage,
    toastType,
    showToast,
    clearToast
  }
}
