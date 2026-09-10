from typing import Optional, List
from pydantic import BaseModel, Field


class HostingBase(BaseModel):
    cliente_id: int = Field(..., description="ID del cliente asociado")
    nom_host: str = Field(..., min_length=2, description="Nombre del servidor o host (ej. srv1.nexusbot.io o cpanel.empresa.com)")
    dominio: Optional[str] = Field("", description="Dominio principal asociado")
    usuario: Optional[str] = Field("", description="Usuario cPanel / WHM")
    contrasena_normal: Optional[str] = Field("", description="Contraseña en texto claro")
    tipo_producto: Optional[str] = Field("Servicio de alojamiento", description="Tipo de servicio o plan")
    producto: Optional[int] = Field(1, description="ID o tipo de plan")
    costo_producto: Optional[float] = Field(0.0, description="Costo del servicio")
    id_forma_pago: Optional[int] = Field(1, description="1 = MXN, 2 = USD")
    dns: Optional[str] = Field("", description="DNS o Nameservers generales")
    url_pago: Optional[str] = Field("", description="URL de pago / renovación")
    url_acceso: Optional[str] = Field("", description="URL directa a cPanel / WHM")
    ns1: Optional[str] = Field("", description="Nameserver 1")
    ns2: Optional[str] = Field("", description="Nameserver 2")
    ns3: Optional[str] = Field("", description="Nameserver 3")
    ns4: Optional[str] = Field("", description="Nameserver 4")
    ns5: Optional[str] = Field("", description="Nameserver 5")
    ns6: Optional[str] = Field("", description="Nameserver 6")
    fecha_contratacion: Optional[str] = None
    fecha_pago: Optional[str] = None
    estado_producto: Optional[int] = Field(1, description="1 = Activo, 0 = Inactivo/Suspendido")
    IVA: Optional[int] = Field(1, description="1 = Aplica IVA, 0 = No")
    frecuencia_pago: Optional[int] = Field(2, description="1 = Mensual, 2 = Anual, 3 = Trimestral, 4 = Semestral")


class HostingCreate(HostingBase):
    pass


class HostingUpdate(BaseModel):
    cliente_id: Optional[int] = None
    nom_host: Optional[str] = None
    dominio: Optional[str] = None
    usuario: Optional[str] = None
    contrasena_normal: Optional[str] = None
    tipo_producto: Optional[str] = None
    producto: Optional[int] = None
    costo_producto: Optional[float] = None
    id_forma_pago: Optional[int] = None
    dns: Optional[str] = None
    url_pago: Optional[str] = None
    url_acceso: Optional[str] = None
    ns1: Optional[str] = None
    ns2: Optional[str] = None
    ns3: Optional[str] = None
    ns4: Optional[str] = None
    ns5: Optional[str] = None
    ns6: Optional[str] = None
    fecha_contratacion: Optional[str] = None
    fecha_pago: Optional[str] = None
    estado_producto: Optional[int] = None
    IVA: Optional[int] = None
    frecuencia_pago: Optional[int] = None
    eliminado: Optional[int] = None


class HostingListItem(BaseModel):
    id_orden: int
    cliente_id: int
    cliente_nombre: str
    cliente_empresa: str
    cliente_correo: Optional[str] = None
    cliente_telefono: Optional[str] = None
    nom_host: str
    dominio: Optional[str] = ""
    usuario: Optional[str] = ""
    tipo_producto: Optional[str] = ""
    producto: Optional[int] = 1
    costo_producto: float = 0.0
    id_forma_pago: int = 1
    moneda: str = "MXN"
    url_acceso: Optional[str] = ""
    panel_type: str = "cpanel"
    fecha_contratacion: Optional[str] = None
    fecha_pago: Optional[str] = None
    dias_restantes: Optional[int] = None
    estado_vencimiento: str = "sin_fecha"
    estado_producto: int = 1
    frecuencia_pago: int = 2
    frecuencia_label: str = "Anual"
    eliminado: int = 0

    class Config:
        from_attributes = True


class HostingDetail(HostingListItem):
    contrasena_normal: Optional[str] = None
    dns: Optional[str] = ""
    url_pago: Optional[str] = ""
    ns1: Optional[str] = ""
    ns2: Optional[str] = ""
    ns3: Optional[str] = ""
    ns4: Optional[str] = ""
    ns5: Optional[str] = ""
    ns6: Optional[str] = ""
    IVA: int = 1


class HostingStats(BaseModel):
    total: int = 0
    activos: int = 0
    inactivos: int = 0
    por_vencer_30d: int = 0
    por_vencer_15d: int = 0
    por_vencer_7d: int = 0
    vencidos: int = 0
    eliminados: int = 0


class HostingsListResponse(BaseModel):
    items: List[HostingListItem]
    total: int
    page: int
    total_pages: int
    stats: HostingStats
