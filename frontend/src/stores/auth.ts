import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authApi, type UserProfile, type LoginCredentials } from '@/api/auth'

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem('access_token'))
  const user = ref<UserProfile | null>(
    localStorage.getItem('user_data')
      ? JSON.parse(localStorage.getItem('user_data')!)
      : null
  )
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const isSuperAdmin = computed(() => user.value?.id_tipo_usuario === 1)
  const isCliente = computed(() => user.value?.id_tipo_usuario === 0)
  const isAgente = computed(() => user.value?.id_tipo_usuario === 3)

  async function login(credentials: LoginCredentials): Promise<string> {
    isLoading.value = true
    error.value = null
    try {
      const response = await authApi.login(credentials)
      token.value = response.access_token
      user.value = response.user

      localStorage.setItem('access_token', response.access_token)
      localStorage.setItem('user_data', JSON.stringify(response.user))

      return response.redirect || '/dashboard'
    } catch (err: any) {
      const msg =
        err.response?.data?.detail ||
        err.response?.data?.message ||
        'Error al iniciar sesión. Verifica tus datos.'
      error.value = msg
      throw new Error(msg)
    } finally {
      isLoading.value = false
    }
  }

  async function fetchCurrentUser(): Promise<UserProfile | null> {
    if (!token.value) return null
    try {
      const me = await authApi.getMe()
      user.value = me
      localStorage.setItem('user_data', JSON.stringify(me))
      return me
    } catch (err) {
      logout()
      return null
    }
  }

  function logout() {
    try {
      authApi.logout().catch(() => {})
    } finally {
      token.value = null
      user.value = null
      localStorage.removeItem('access_token')
      localStorage.removeItem('user_data')
    }
  }

  return {
    token,
    user,
    isLoading,
    error,
    isAuthenticated,
    isSuperAdmin,
    isCliente,
    isAgente,
    login,
    fetchCurrentUser,
    logout
  }
})
