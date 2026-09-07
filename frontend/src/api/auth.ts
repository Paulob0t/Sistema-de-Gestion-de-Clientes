import apiClient from './client'

export interface LoginCredentials {
  usuario: string
  contrasena: string
}

export interface UserProfile {
  id: number
  usuario: string
  id_tipo_usuario: number
  rol: string
  nombre?: string
  correo?: string
  empresa?: string
  agente_id?: number
}

export interface LoginResponse {
  status: string
  access_token: string
  token_type: string
  expires_in_minutes: number
  redirect: string
  user: UserProfile
}

export const authApi = {
  login: async (credentials: LoginCredentials): Promise<LoginResponse> => {
    const response = await apiClient.post<LoginResponse>('/auth/login', credentials)
    return response.data
  },

  getMe: async (): Promise<UserProfile> => {
    const response = await apiClient.get<UserProfile>('/auth/me')
    return response.data
  },

  logout: async (): Promise<{ status: string; message: string }> => {
    const response = await apiClient.post('/auth/logout')
    return response.data
  }
}
