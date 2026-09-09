from sqlalchemy import Column, Integer, String, Float, Date, SmallInteger
from app.core.database import Base


class Dominio(Base):
    __tablename__ = "dominios"

    id_dominio = Column(Integer, primary_key=True, index=True, autoincrement=True)
    cliente_id = Column(Integer, index=True, nullable=False)
    proveedor = Column(String(150), nullable=True)
    url_dominio = Column(String(100), nullable=False)
    url_pago = Column(String(100), nullable=True)
    url_admin = Column(String(50), nullable=True)
    usuario = Column(String(100), nullable=True)
    contrasena = Column(String(100), nullable=True)
    contrasena_normal = Column(String(300), nullable=True)
    url_cpanel = Column(String(50), nullable=True)
    ns1 = Column(String(50), nullable=True)
    ns2 = Column(String(50), nullable=True)
    ns3 = Column(String(50), nullable=True)
    ns4 = Column(String(50), nullable=True)
    ns5 = Column(String(50), nullable=True)
    ns6 = Column(String(50), nullable=True)
    costo_dominio = Column(Float, nullable=True, default=0.0)
    id_forma_pago = Column(SmallInteger, nullable=True)
    fecha_contratacion = Column(Date, nullable=True)
    fecha_pago = Column(Date, nullable=True)
    estado_dominio = Column(Integer, nullable=True, default=1)
    registrado = Column(Integer, nullable=True, default=0)
    eliminado = Column(Integer, nullable=False, default=0)
    estatus_pago = Column(SmallInteger, nullable=False, default=0)
    frecuencia_pago = Column(SmallInteger, nullable=False, default=3)

    def __repr__(self) -> str:
        return f"<Dominio(id={self.id_dominio}, url='{self.url_dominio}', cliente={self.cliente_id})>"
