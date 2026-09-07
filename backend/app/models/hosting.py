from sqlalchemy import Column, Integer, String, Float, Date
from app.core.database import Base


class Hosting(Base):
    __tablename__ = "hosting"

    id_orden = Column(Integer, primary_key=True, index=True, autoincrement=True)
    cliente_id = Column(Integer, index=True, nullable=False)
    dominio = Column(String(100), nullable=True)
    nom_host = Column(String(100), nullable=False)
    usuario = Column(String(100), nullable=True)
    contrasena = Column(String(100), nullable=True)
    tipo_producto = Column(String(150), nullable=True)
    producto = Column(Integer, nullable=True, default=0)
    costo_producto = Column(Float, nullable=True, default=0.0)
    fecha_contratacion = Column(Date, nullable=True)
    fecha_pago = Column(Date, nullable=True)
    estado_producto = Column(Integer, nullable=True, default=1)
    eliminado = Column(Integer, nullable=False, default=0)

    def __repr__(self) -> str:
        return f"<Hosting(id={self.id_orden}, host='{self.nom_host}', cliente={self.cliente_id})>"
