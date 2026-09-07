from sqlalchemy import Column, Integer, String, Text
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

    def __repr__(self) -> str:
        return f"<Cliente(id={self.id}, empresa='{self.empresa}', contacto='{self.nombre_contacto}')>"
