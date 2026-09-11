import apiClient from './client'

export interface DominioListItem {
  id_dominio: number
  url_dominio: string
  cliente_id: number
  cliente_nombre: string
  cliente_empresa: string
  cliente_correo: string | null
  cliente_telefono: string | null
  proveedor: string | null
  costo_dominio: number
  fecha_contratacion: string | null
  fecha_pago: string | null
  dias_restantes: number | null
  estado_vencimiento: 'vencido' | 'prox7' | 'prox30' | 'ok' | 'sin_fecha'
  estado_dominio: number
  estatus_pago: number
  registrado: number
  eliminado: number
}

export interface DominioStats {
  total: number
  activos: number
  por_vencer_30d: number
  vencidos: number
  pagados: number
  pendientes_pago: number
}

export interface DominiosListResponse {
  items: DominioListItem[]
  total: number
  page: number
  limit: number
  total_pages: number
  stats: DominioStats
}

export interface DominioDetail {
  id_dominio: number
  cliente_id: number
  cliente_nombre: string
  cliente_empresa: string
  cliente_correo: string | null
  cliente_telefono: string | null
  url_dominio: string
  proveedor: string | null
  url_pago: string | null
  url_admin: string | null
  usuario: string | null
  contrasena_normal: string | null
  url_cpanel: string | null
  ns1: string | null
  ns2: string | null
  ns3: string | null
  ns4: string | null
  ns5?: string | null
  ns6?: string | null
  costo_dominio: number
  id_forma_pago?: number
  frecuencia_pago?: number
  fecha_contratacion: string | null
  fecha_pago: string | null
  dias_restantes: number | null
  estado_vencimiento: 'vencido' | 'prox7' | 'prox30' | 'ok' | 'sin_fecha'
  estado_dominio: number
  registrado: number
  eliminado: number
  estatus_pago: number
}

export interface DominioPayload {
  cliente_id: number
  url_dominio: string
  proveedor?: string | null
  costo_dominio?: number
  id_forma_pago?: number
  frecuencia_pago?: number
  fecha_contratacion?: string | null
  fecha_pago?: string | null
  url_admin?: string | null
  usuario?: string | null
  contrasena?: string | null
  contrasena_normal?: string | null
  url_cpanel?: string | null
  ns1?: string | null
  ns2?: string | null
  ns3?: string | null
  ns4?: string | null
  ns5?: string | null
  ns6?: string | null
  estado_dominio?: number
  registrado?: number
  estatus_pago?: number
}

export const dominiosApi = {
  async getDominios(params: {
    search?: string
    filtro?: string
    sistema?: string
    page?: number
    limit?: number
  } = {}): Promise<DominiosListResponse> {
    const response = await apiClient.get<DominiosListResponse>('/dominios', { params })
    return response.data
  },

  async getDominioDetail(id: number): Promise<DominioDetail> {
    const response = await apiClient.get<DominioDetail>(`/dominios/${id}`)
    return response.data
  },

  async createDominio(payload: DominioPayload): Promise<DominioDetail> {
    const response = await apiClient.post<DominioDetail>('/dominios', payload)
    return response.data
  },

  async updateDominio(id: number, payload: Partial<DominioPayload>): Promise<DominioDetail> {
    const response = await apiClient.put<DominioDetail>(`/dominios/${id}`, payload)
    return response.data
  },

  async deleteDominio(id: number): Promise<{ status: string; message: string }> {
    const response = await apiClient.delete<{ status: string; message: string }>(`/dominios/${id}`)
    return response.data
  },
}
