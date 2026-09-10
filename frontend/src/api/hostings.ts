import apiClient from './client'

export interface HostingListItem {
  id_orden: number
  cliente_id: number
  cliente_nombre: string
  cliente_empresa: string
  cliente_correo?: string | null
  cliente_telefono?: string | null
  nom_host: string
  dominio?: string
  usuario?: string
  tipo_producto?: string
  producto?: number
  costo_producto: number
  id_forma_pago: number
  moneda: string
  url_acceso?: string
  panel_type: 'cpanel' | 'whm'
  fecha_contratacion?: string | null
  fecha_pago?: string | null
  dias_restantes?: number | null
  estado_vencimiento: 'vencido' | 'prox7' | 'prox15' | 'prox30' | 'ok' | 'sin_fecha'
  estado_producto: number
  frecuencia_pago: number
  frecuencia_label: string
  eliminado: number
}

export interface HostingDetail extends HostingListItem {
  contrasena_normal?: string | null
  dns?: string
  url_pago?: string
  ns1?: string
  ns2?: string
  ns3?: string
  ns4?: string
  ns5?: string
  ns6?: string
  IVA: number
}

export interface HostingStats {
  total: number
  activos: number
  inactivos: number
  por_vencer_30d: number
  por_vencer_15d: number
  por_vencer_7d: number
  vencidos: number
  eliminados: number
}

export interface HostingsListResponse {
  items: HostingListItem[]
  total: number
  page: number
  total_pages: number
  stats: HostingStats
}

export interface HostingPayload {
  cliente_id: number
  nom_host: string
  dominio?: string
  usuario?: string
  contrasena_normal?: string
  tipo_producto?: string
  producto?: number
  costo_producto?: number
  id_forma_pago?: number
  dns?: string
  url_pago?: string
  url_acceso?: string
  ns1?: string
  ns2?: string
  ns3?: string
  ns4?: string
  ns5?: string
  ns6?: string
  fecha_contratacion?: string
  fecha_pago?: string
  estado_producto?: number
  IVA?: number
  frecuencia_pago?: number
}

export const hostingsApi = {
  getHostings: async (params?: {
    search?: string
    filtro?: string
    page?: number
    limit?: number
  }): Promise<HostingsListResponse> => {
    const response = await apiClient.get<HostingsListResponse>('/hostings', { params })
    return response.data
  },

  getHostingDetail: async (id: number): Promise<HostingDetail> => {
    const response = await apiClient.get<HostingDetail>(`/hostings/${id}`)
    return response.data
  },

  createHosting: async (data: HostingPayload): Promise<HostingDetail> => {
    const response = await apiClient.post<HostingDetail>('/hostings', data)
    return response.data
  },

  updateHosting: async (id: number, data: Partial<HostingPayload>): Promise<HostingDetail> => {
    const response = await apiClient.put<HostingDetail>(`/hostings/${id}`, data)
    return response.data
  },

  deleteHosting: async (id: number, permanent = false): Promise<{ status: string; message: string; eliminado?: number }> => {
    const response = await apiClient.delete(`/hostings/${id}`, { params: { permanent } })
    return response.data
  },
}
