from typing import Optional, List
from pydantic import BaseModel, Field


class PagoBase(BaseModel):
    id_clie: int = Field(..., description="ID del cliente asociado")
    monto: float = Field(..., gt=0, description="Monto del pago")
    currency: Optional[str] = Field("MXN", description="Moneda: MXN o USD")
    concepto: str = Field(..., min_length=3, description="Concepto o descripción del cobro")
    forma_pago: Optional[int] = Field(1, description="1 = Transferencia/Depósito, 2 = Efectivo/OXXO, 3 = Tarjeta/Stripe, 4 = PayPal")
    estatus: Optional[int] = Field(0, description="0 = Pendiente, 1 = Pagado/Acreditado")
    tipo_servicio: Optional[int] = Field(0, description="0 = Manual/Otro, 1 = Hosting, 2 = Dominio")
    id_servicio: Optional[int] = Field(0, description="ID del servicio asociado (hosting o dominio)")
    fecha: Optional[str] = None
    hora: Optional[str] = None
    fecha_pago: Optional[str] = None
    hora_pago: Optional[str] = None
    fecha_limite_pago: Optional[str] = None
    id_pago: Optional[str] = Field("", description="ID de transacción, referencia bancaria o folio")
    manual: Optional[int] = Field(1, description="1 = Cobro manual, 0 = Automático de sistema")
    pago_recurrente: Optional[int] = Field(0, description="1 = Si, 0 = No")
    frecuencia_pago: Optional[int] = Field(0, description="0 = Único, 1 = Mensual, 2 = Anual")


class PagoCreate(PagoBase):
    pass


class PagoUpdate(BaseModel):
    id_clie: Optional[int] = None
    monto: Optional[float] = None
    currency: Optional[str] = None
    concepto: Optional[str] = None
    forma_pago: Optional[int] = None
    estatus: Optional[int] = None
    tipo_servicio: Optional[int] = None
    id_servicio: Optional[int] = None
    fecha: Optional[str] = None
    hora: Optional[str] = None
    fecha_pago: Optional[str] = None
    hora_pago: Optional[str] = None
    fecha_limite_pago: Optional[str] = None
    id_pago: Optional[str] = None
    manual: Optional[int] = None
    pago_recurrente: Optional[int] = None
    frecuencia_pago: Optional[int] = None
    Registro: Optional[int] = None


class PagoStatusToggle(BaseModel):
    estatus: int = Field(..., description="0 = Pendiente, 1 = Acreditado")
    fecha_pago: Optional[str] = None


class PagoListItem(BaseModel):
    id: int
    id_clie: int
    cliente_nombre: str
    cliente_empresa: str
    cliente_correo: Optional[str] = None
    cliente_telefono: Optional[str] = None
    id_servicio: int = 0
    nombre_servicio: Optional[str] = None
    monto: float
    currency: str = "MXN"
    concepto: str
    forma_pago: int = 1
    forma_pago_label: str = "Transferencia"
    estatus: int = 0
    estatus_label: str = "Pendiente"
    tipo_servicio: int = 0
    tipo_servicio_label: str = "Manual"
    fecha: str
    fecha_pago: Optional[str] = None
    fecha_limite_pago: Optional[str] = None
    dias_restantes: Optional[int] = None
    estado_vencimiento: str = "sin_fecha"
    id_pago: Optional[str] = ""
    manual: int = 1
    Registro: int = 0

    class Config:
        from_attributes = True


class PagoDetail(PagoListItem):
    hora: Optional[str] = None
    hora_pago: Optional[str] = None
    session_id: Optional[str] = None
    pago_grupal_id: Optional[str] = None
    frecuencia_pago: int = 0
    pago_recurrente: int = 0
    sistema: Optional[str] = "nexus"


class PagoStats(BaseModel):
    total_registros: int = 0
    total_pendientes: int = 0
    total_pagados: int = 0
    total_vencidos: int = 0
    total_eliminados: int = 0
    monto_cobrado_mxn: float = 0.0
    monto_cobrado_usd: float = 0.0
    monto_pendiente_mxn: float = 0.0
    monto_pendiente_usd: float = 0.0


class PagosListResponse(BaseModel):
    items: List[PagoListItem]
    total: int
    page: int
    total_pages: int
    stats: PagoStats
