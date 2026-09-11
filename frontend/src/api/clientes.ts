import apiClient from './client'

export interface ClienteListItem {
  id: number
  empresa: string
  nombre_contacto: string
  correo: string | null
  telefono: string | null
  rfc: string | null
  ciudad: string | null
  estado: string | null
  eliminado: number
  transferido: number
  total_dominios: number
  total_hostings: number
  total_pagos_pendientes: number
  estado_pago: 'al_dia' | 'pendiente' | 'sin_servicios'
  facturacion?: number
}

export interface ClienteStats {
  total: number
  activos: number
  con_pagos_pendientes: number
  transferidos: number
  eliminados: number
}

export interface ClientesListResponse {
  items: ClienteListItem[]
  total: number
  page: number
  limit: number
  total_pages: number
  stats: ClienteStats
}

export interface DominioSimple {
  id: number
  dominio: string
  fecha_registro: string | null
  fecha_vencimiento: string | null
  dias_restantes: number | null
  precio: number
}

export interface HostingSimple {
  id: number
  nombre_plan: string | null
  dominio: string | null
  fecha_vencimiento: string | null
  dias_restantes: number | null
  precio: number
}

export interface PagoSimple {
  id: number
  concepto: string | null
  monto: number
  moneda: string
  fecha_vencimiento: string | null
  estatus: number
  estatus_texto: string
}

export interface ClienteDetail {
  id: number
  empresa: string
  nombre_contacto: string
  correo: string | null
  telefono: string | null
  rfc: string | null
  rsocial: string | null
  calle: string | null
  next: string | null
  nint: string | null
  col: string | null
  cp: string | null
  pais: string | null
  estado: string | null
  ciudad: string | null
  especificacion: string | null
  eliminado: number
  transferido: number
  dominios: DominioSimple[]
  hostings: HostingSimple[]
  pagos: PagoSimple[]
  total_dominios: number
  total_hostings: number
  total_pagos_pendientes: number
}

export interface ClientePayload {
  empresa: string
  nombre_contacto: string
  correo?: string | null
  telefono?: string | null
  rfc?: string | null
  rsocial?: string | null
  calle?: string | null
  next?: string | null
  nint?: string | null
  col?: string | null
  cp?: string | null
  pais?: string | null
  estado?: string | null
  ciudad?: string | null
  especificacion?: string | null
  facturacion?: number
  contrasena?: string | null
}

export const clientesApi = {
  async getClientes(params: {
    search?: string
    filtro?: string
    sistema?: string
    page?: number
    limit?: number
  } = {}): Promise<ClientesListResponse> {
    const response = await apiClient.get<ClientesListResponse>('/clientes', { params })
    return response.data
  },

  async getClienteDetail(id: number): Promise<ClienteDetail> {
    const response = await apiClient.get<ClienteDetail>(`/clientes/${id}`)
    return response.data
  },

  async createCliente(payload: ClientePayload): Promise<ClienteDetail> {
    const response = await apiClient.post<ClienteDetail>('/clientes', payload)
    return response.data
  },

  async updateCliente(id: number, payload: Partial<ClientePayload>): Promise<ClienteDetail> {
    const response = await apiClient.put<ClienteDetail>(`/clientes/${id}`, payload)
    return response.data
  },

  async deleteCliente(id: number): Promise<{ status: string; message: string }> {
    const response = await apiClient.delete<{ status: string; message: string }>(`/clientes/${id}`)
    return response.data
  },
}
