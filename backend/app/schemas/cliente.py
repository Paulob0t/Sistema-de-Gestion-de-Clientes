from typing import Optional, List, Any
from datetime import datetime
from pydantic import BaseModel, Field


class ClienteBase(BaseModel):
    empresa: Optional[str] = None
    nombre_contacto: Optional[str] = None
    correo: Optional[str] = None
    telefono: Optional[str] = None
    rfc: Optional[str] = None
    rsocial: Optional[str] = None
    calle: Optional[str] = None
    next: Optional[str] = None
    nint: Optional[str] = None
    col: Optional[str] = None
    cp: Optional[str] = None
    pais: Optional[str] = "México"
    estado: Optional[str] = None
    ciudad: Optional[str] = None
    especificacion: Optional[str] = None


class ClienteCreate(ClienteBase):
    empresa: str = Field(..., min_length=1, description="Nombre de la empresa o negocio")
    nombre_contacto: str = Field(..., min_length=1, description="Nombre de la persona de contacto")


class ClienteUpdate(ClienteBase):
    pass


class ClienteListItem(BaseModel):
    id: int
    empresa: str
    nombre_contacto: str
    correo: Optional[str] = None
    telefono: Optional[str] = None
    rfc: Optional[str] = None
    ciudad: Optional[str] = None
    estado: Optional[str] = None
    eliminado: int = 0
    transferido: int = 0
    total_dominios: int = 0
    total_hostings: int = 0
    total_pagos_pendientes: int = 0
    estado_pago: str = "sin_servicios"  # "al_dia", "pendiente", "sin_servicios"

    class Config:
        from_attributes = True


class ClienteStats(BaseModel):
    total: int = 0
    activos: int = 0
    con_pagos_pendientes: int = 0
    transferidos: int = 0
    eliminados: int = 0


class ClientesListResponse(BaseModel):
    items: List[ClienteListItem]
    total: int
    page: int
    limit: int
    total_pages: int
    stats: ClienteStats


class DominioSimple(BaseModel):
    id: int
    dominio: str
    fecha_registro: Optional[str] = None
    fecha_vencimiento: Optional[str] = None
    dias_restantes: Optional[int] = None
    precio: Optional[float] = 0.0


class HostingSimple(BaseModel):
    id: int
    nombre_plan: Optional[str] = None
    dominio: Optional[str] = None
    fecha_vencimiento: Optional[str] = None
    dias_restantes: Optional[int] = None
    precio: Optional[float] = 0.0


class PagoSimple(BaseModel):
    id: int
    concepto: Optional[str] = None
    monto: float = 0.0
    moneda: str = "MXN"
    fecha_vencimiento: Optional[str] = None
    estatus: int = 0
    estatus_texto: str = "Pendiente"


class ClienteDetailOut(BaseModel):
    id: int
    empresa: str
    nombre_contacto: str
    correo: Optional[str] = None
    telefono: Optional[str] = None
    rfc: Optional[str] = None
    rsocial: Optional[str] = None
    calle: Optional[str] = None
    next: Optional[str] = None
    nint: Optional[str] = None
    col: Optional[str] = None
    cp: Optional[str] = None
    pais: Optional[str] = None
    estado: Optional[str] = None
    ciudad: Optional[str] = None
    especificacion: Optional[str] = None
    eliminado: int = 0
    transferido: int = 0
    dominios: List[DominioSimple] = []
    hostings: List[HostingSimple] = []
    pagos: List[PagoSimple] = []
    total_dominios: int = 0
    total_hostings: int = 0
    total_pagos_pendientes: int = 0
