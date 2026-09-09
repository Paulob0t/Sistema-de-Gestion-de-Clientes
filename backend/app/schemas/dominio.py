from typing import Optional, List
from pydantic import BaseModel, Field


class DominioBase(BaseModel):
    cliente_id: int = Field(..., description="ID del cliente asociado")
    url_dominio: str = Field(..., min_length=3, description="Nombre del dominio web (ej. midominio.com)")
    proveedor: Optional[str] = "NexusBot"
    url_pago: Optional[str] = None
    url_admin: Optional[str] = None
    usuario: Optional[str] = None
    contrasena: Optional[str] = None
    contrasena_normal: Optional[str] = None
    url_cpanel: Optional[str] = None
    ns1: Optional[str] = None
    ns2: Optional[str] = None
    ns3: Optional[str] = None
    ns4: Optional[str] = None
    costo_dominio: Optional[float] = 0.0
    fecha_contratacion: Optional[str] = None
    fecha_pago: Optional[str] = None
    estado_dominio: Optional[int] = 1
    registrado: Optional[int] = 1
    estatus_pago: Optional[int] = 0


class DominioCreate(DominioBase):
    pass


class DominioUpdate(BaseModel):
    cliente_id: Optional[int] = None
    url_dominio: Optional[str] = None
    proveedor: Optional[str] = None
    url_pago: Optional[str] = None
    url_admin: Optional[str] = None
    usuario: Optional[str] = None
    contrasena_normal: Optional[str] = None
    url_cpanel: Optional[str] = None
    ns1: Optional[str] = None
    ns2: Optional[str] = None
    ns3: Optional[str] = None
    ns4: Optional[str] = None
    costo_dominio: Optional[float] = None
    fecha_contratacion: Optional[str] = None
    fecha_pago: Optional[str] = None
    estado_dominio: Optional[int] = None
    registrado: Optional[int] = None
    estatus_pago: Optional[int] = None
    eliminado: Optional[int] = None


class DominioListItem(BaseModel):
    id_dominio: int
    url_dominio: str
    cliente_id: int
    cliente_nombre: str
    cliente_empresa: str
    cliente_correo: Optional[str] = None
    cliente_telefono: Optional[str] = None
    proveedor: Optional[str] = None
    costo_dominio: float = 0.0
    fecha_contratacion: Optional[str] = None
    fecha_pago: Optional[str] = None
    dias_restantes: Optional[int] = None
    estado_vencimiento: str = "ok"  # "vencido", "prox7", "prox30", "ok", "sin_fecha"
    estado_dominio: int = 1
    estatus_pago: int = 0
    registrado: int = 0
    eliminado: int = 0

    class Config:
        from_attributes = True


class DominioStats(BaseModel):
    total: int = 0
    activos: int = 0
    por_vencer_30d: int = 0
    vencidos: int = 0
    pagados: int = 0
    pendientes_pago: int = 0


class DominiosListResponse(BaseModel):
    items: List[DominioListItem]
    total: int
    page: int
    limit: int
    total_pages: int
    stats: DominioStats


class DominioDetail(BaseModel):
    id_dominio: int
    cliente_id: int
    cliente_nombre: str
    cliente_empresa: str
    cliente_correo: Optional[str] = None
    cliente_telefono: Optional[str] = None
    url_dominio: str
    proveedor: Optional[str] = None
    url_pago: Optional[str] = None
    url_admin: Optional[str] = None
    usuario: Optional[str] = None
    contrasena_normal: Optional[str] = None
    url_cpanel: Optional[str] = None
    ns1: Optional[str] = None
    ns2: Optional[str] = None
    ns3: Optional[str] = None
    ns4: Optional[str] = None
    costo_dominio: float = 0.0
    fecha_contratacion: Optional[str] = None
    fecha_pago: Optional[str] = None
    dias_restantes: Optional[int] = None
    estado_vencimiento: str = "ok"
    estado_dominio: int = 1
    registrado: int = 0
    eliminado: int = 0
    estatus_pago: int = 0
