from sqlalchemy import Column, Integer, String, Float, Date
from app.core.database import Base


class Hosting(Base):
    __tablename__ = "hosting"

    id_orden = Column(Integer, primary_key=True, index=True, autoincrement=True)
    cliente_id = Column(Integer, index=True, nullable=False)
    dominio = Column(String(100), nullable=True, default="")
    nom_host = Column(String(100), nullable=False, default="")
    usuario = Column(String(100), nullable=True, default="")
    contrasena = Column(String(100), nullable=True, default="")
    contrasena_normal = Column(String(300), nullable=True, default="")
    tipo_producto = Column(String(150), nullable=True, default="Servicio de alojamiento")
    producto = Column(Integer, nullable=True, default=1)
    costo_producto = Column(Float, nullable=True, default=0.0)
    id_forma_pago = Column(Integer, nullable=True, default=1)  # 1 = MXN, 2 = USD
    dns = Column(String(150), nullable=True, default="")
    url_pago = Column(String(150), nullable=True, default="")
    url_acceso = Column(String(150), nullable=True, default="")
    ns1 = Column(String(50), nullable=True, default="")
    ns2 = Column(String(50), nullable=True, default="")
    ns3 = Column(String(50), nullable=True, default="")
    ns4 = Column(String(50), nullable=True, default="")
    ns5 = Column(String(50), nullable=True, default="")
    ns6 = Column(String(50), nullable=True, default="")
    fecha_contratacion = Column(Date, nullable=True)
    fecha_pago = Column(Date, nullable=True)
    estado_producto = Column(Integer, nullable=True, default=1)  # 1 = Activo, 0 = Inactivo
    IVA = Column(Integer, nullable=True, default=1)
    eliminado = Column(Integer, nullable=False, default=0)
    frecuencia_pago = Column(Integer, nullable=True, default=2)  # 1 = Mensual, 2 = Anual

    def __repr__(self) -> str:
        return f"<Hosting(id={self.id_orden}, host='{self.nom_host}', cliente={self.cliente_id})>"

