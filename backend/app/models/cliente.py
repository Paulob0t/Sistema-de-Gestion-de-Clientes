from sqlalchemy import Column, Integer, String, Text, DateTime, SmallInteger
from app.core.database import Base


class Cliente(Base):
    __tablename__ = "clientes"

    id = Column(Integer, primary_key=True, index=True, autoincrement=True)
    nombre_contacto = Column(String(50), nullable=True)
    empresa = Column(String(150), nullable=True)
    correo = Column(String(50), nullable=True)
    telefono = Column(String(50), nullable=True)
    especificacion = Column(Text, nullable=True)
    rsocial = Column(String(100), nullable=True)
    rfc = Column(String(100), nullable=True)
    calle = Column(String(100), nullable=True)
    next = Column(String(100), nullable=True)
    nint = Column(String(100), nullable=True)
    col = Column(String(100), nullable=True)
    cp = Column(String(100), nullable=True)
    pais = Column(String(100), nullable=True)
    estado = Column(String(100), nullable=True)
    ciudad = Column(String(100), nullable=True)
    display = Column(String(50), nullable=True)
    constancia_situacion_fiscal = Column(Text, nullable=True)
    facturacion = Column(Integer, default=0, nullable=True)
    actualizado = Column(Integer, default=0, nullable=True)
    correo_pendiente_actualizar = Column(String(300), nullable=True)
    actualizar_correo = Column(Integer, default=0, nullable=True)
    eliminado = Column(Integer, default=0, nullable=False, index=True)
    id_lead = Column(Integer, default=0, nullable=True)
    usuario_registro = Column(Integer, nullable=True)
    transferido = Column(SmallInteger, default=0, nullable=False, index=True)
    fecha_transferencia = Column(DateTime, nullable=True)
    usuario_transferencia = Column(Integer, nullable=True)

    def __repr__(self) -> str:
        return f"<Cliente(id={self.id}, empresa='{self.empresa}', contacto='{self.nombre_contacto}')>"

