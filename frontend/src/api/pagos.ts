import apiClient from './client'

export interface PagoListItem {
  id: number
  id_clie: number
  cliente_nombre: string
  cliente_empresa: string
  cliente_correo?: string | null
  cliente_telefono?: string | null
  id_servicio: number
  nombre_servicio?: string | null
  monto: number
  currency: string
  concepto: string
  forma_pago: number
  forma_pago_label: string
  estatus: number
  estatus_label: string
  tipo_servicio: number
  tipo_servicio_label: string
  fecha: string
  fecha_pago?: string | null
  fecha_limite_pago?: string | null
  dias_restantes?: number | null
  estado_vencimiento: 'pagado' | 'vencido' | 'prox7' | 'prox15' | 'prox30' | 'ok' | 'sin_fecha'
  id_pago?: string
  manual: number
  Registro: number
}

export interface PagoDetail extends PagoListItem {
  hora?: string | null
  hora_pago?: string | null
  session_id?: string | null
  pago_grupal_id?: string | null
  frecuencia_pago: number
  pago_recurrente: number
  sistema?: string
}

export interface PagoStats {
  total_registros: number
  total_pendientes: number
  total_pagados: number
  total_vencidos: number
  total_eliminados: number
  monto_cobrado_mxn: number
  monto_cobrado_usd: number
  monto_pendiente_mxn: number
  monto_pendiente_usd: number
}

export interface PagosListResponse {
  items: PagoListItem[]
  total: number
  page: number
  total_pages: number
  stats: PagoStats
}

export interface PagoPayload {
  id_clie: number
  monto: number
  currency?: string
  concepto: string
  forma_pago?: number
  estatus?: number
  tipo_servicio?: number
  id_servicio?: number
  fecha?: string
  fecha_pago?: string
  fecha_limite_pago?: string
  id_pago?: string
  manual?: number
  frecuencia_pago?: number
  pago_recurrente?: number
}

export const pagosApi = {
  getPagos: async (params?: {
    search?: string
    filtro?: string
    fecha_desde?: string
    fecha_hasta?: string
    page?: number
    limit?: number
  }): Promise<PagosListResponse> => {
    const response = await apiClient.get<PagosListResponse>('/pagos', { params })
    return response.data
  },

  getPagoDetail: async (id: number): Promise<PagoDetail> => {
    const response = await apiClient.get<PagoDetail>(`/pagos/${id}`)
    return response.data
  },

  createPago: async (data: PagoPayload): Promise<PagoDetail> => {
    const response = await apiClient.post<PagoDetail>('/pagos', data)
    return response.data
  },

  updatePago: async (id: number, data: Partial<PagoPayload>): Promise<PagoDetail> => {
    const response = await apiClient.put<PagoDetail>(`/pagos/${id}`, data)
    return response.data
  },

  toggleStatus: async (id: number, estatus: number, fecha_pago?: string): Promise<PagoDetail> => {
    const response = await apiClient.patch<PagoDetail>(`/pagos/${id}/toggle-status`, { estatus, fecha_pago })
    return response.data
  },

  deletePago: async (id: number, permanent = false): Promise<{ status: string; message: string; Registro?: number }> => {
    const response = await apiClient.delete(`/pagos/${id}`, { params: { permanent } })
    return response.data
  },
}
