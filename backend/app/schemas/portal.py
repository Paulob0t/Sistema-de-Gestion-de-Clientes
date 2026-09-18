from typing import Optional, List
from pydantic import BaseModel


class PortalSitioItem(BaseModel):
    id: int
    url_dominio: str
    proveedor: Optional[str] = None
    fecha_pago: Optional[str] = None
    estatus_pago: int = 0
    url_cpanel: Optional[str] = None


class PortalPagoItem(BaseModel):
    id: int
    concepto: str
    monto: float
    currency: str = "MXN"
    fecha: Optional[str] = None
    fecha_limite_pago: Optional[str] = None
    estatus: int = 0


class PortalTicketItem(BaseModel):
    id: int
    titulo: str
    estado: str
    prioridad: str
    fecha_solicitud: Optional[str] = None
    total_notas: int = 0


class PortalInicioResponse(BaseModel):
    cliente_id: int
    cliente_nombre: str
    cliente_empresa: Optional[str] = None
    cliente_correo: Optional[str] = None
    saludo: str
    total_sitios: int = 0
    total_pendientes: int = 0
    monto_total_pendiente: float = 0.0
    total_tickets_abiertos: int = 0
    total_hostings: int = 0
    total_dominios: int = 0
    doc_url: str = "https://conlineweb.com"
    sitios_recientes: List[PortalSitioItem] = []
    pagos_pendientes_recientes: List[PortalPagoItem] = []
    tickets_recientes: List[PortalTicketItem] = []
