import apiClient from './client'

export interface DashboardKPIs {
  total_pendientes_count: number
  total_pendientes_monto: number
  total_pagados_mes_count: number
  total_pagados_mes_monto: number
  total_ingresos_proyectados: number
  tasa_cumplimiento: number
  total_clientes: number
  total_dominios_activos: number
  total_hosting_activos: number
  vencidos_count: number
  prox_7_dias_count: number
}

export interface PagoItem {
  id: number
  id_clie: number
  cliente_nombre: string
  cliente_correo?: string
  cliente_telefono?: string
  concepto: string
  monto: number
  currency: string
  tipo_servicio: number
  tipo_servicio_label: string
  nombre_servicio?: string
  fecha_limite?: string
  dias_restantes?: number
  estado_vencimiento: 'vencido' | 'prox7' | 'prox30' | 'ok' | 'sin_fecha'
  manual: number
  estatus: number
}

export interface MonthlyTrendItem {
  mes: string
  pendientes: number
  pagados: number
}

export interface ServiceDistribution {
  dominios: number
  hosting: number
  servicios: number
}

export interface DashboardResponse {
  status: string
  sistema: string
  kpis: DashboardKPIs
  pagos_pendientes: PagoItem[]
  monthly_trend: MonthlyTrendItem[]
  distribution: ServiceDistribution
}

export const dashboardApi = {
  getStats: async (
    sistema: string = 'conlineweb',
    mes?: number,
    anio?: number
  ): Promise<DashboardResponse> => {
    const params: Record<string, any> = { sistema }
    if (mes) params.mes = mes
    if (anio) params.anio = anio
    const response = await apiClient.get<DashboardResponse>('/dashboard/stats', { params })
    return response.data
  }
}
