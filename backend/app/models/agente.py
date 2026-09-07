from sqlalchemy import Column, Integer, String, Text
from app.core.database import Base


class Agente(Base):
    __tablename__ = "agentes"

    id = Column(Integer, primary_key=True, index=True, autoincrement=True)
    Idusu = Column(Integer, nullable=False, index=True)
    nombre = Column(String(100), nullable=False)
    correo = Column(String(50), nullable=True)
    idEmpresa = Column(Text, nullable=True)

    def __repr__(self) -> str:
        return f"<Agente(id={self.id}, Idusu={self.Idusu}, nombre='{self.nombre}')>"
