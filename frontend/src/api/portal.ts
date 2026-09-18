import axios from 'axios'

export interface PortalSitioItem {
  id: number
  url_dominio: string
  proveedor?: string
  fecha_pago?: string
  estatus_pago: number
  url_cpanel?: string
}

export interface PortalPagoItem {
  id: number
  concepto: string
  monto: number
  currency: string
  fecha?: string
  fecha_limite_pago?: string
  estatus: number
}

export interface PortalTicketItem {
  id: number
  titulo: string
  estado: string
  prioridad: string
  fecha_solicitud?: string
  total_notas: number
}

export interface PortalInicioResponse {
  cliente_id: number
  cliente_nombre: string
  cliente_empresa?: string
  cliente_correo?: string
  saludo: string
  total_sitios: number
  total_pendientes: number
  monto_total_pendiente: number
  total_tickets_abiertos: number
  total_hostings: number
  total_dominios: number
  doc_url: string
  sitios_recientes: PortalSitioItem[]
  pagos_pendientes_recientes: PortalPagoItem[]
  tickets_recientes: PortalTicketItem[]
}

const API_BASE = '/api/v1/portal'

export const portalApi = {
  getInicio(clienteId?: number): Promise<PortalInicioResponse> {
    const token = localStorage.getItem('access_token')
    const params = clienteId ? { cliente_id: clienteId } : {}
    return axios
      .get(`${API_BASE}/inicio`, {
        params,
        headers: {
          Authorization: `Bearer ${token}`
        }
      })
      .then((res) => res.data)
  }
}
